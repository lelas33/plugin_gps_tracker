<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../../3rdparty/api_tkstar.php';
require_once dirname(__FILE__) . '/../../3rdparty/api_traccar.php';


define("GPS_FILES_DIR_CL", "/../../data/");


// 2 fichiers pour enregistrer les trajets en détails
// car_trips.log: liste des trajets
//  * TRIP_STS: Start Timestamp 
//  * TRIP_ETS: End Timestamp
//  * TRIP_DTS: Distance parcourue pendant le trajet

// car_gps.log : liste des positions de l'objet suivi
//  * PTS_TS: Timestamp
//  * PTS_LAT: Lattitude GPS
//  * PTS_LON: Longitude GPS
//  * PTS_ALT: Altitude
//  * PTS_SPD: Vitesse
//  * PTS_MLG: Kilométrage courant
//  * PTS_KIN: Object en mouvement (0:still, 1:on_foot, 2:running, 3:on_bicycle, 4:in_vehicle)


// Classe principale du plugin
// ===========================
class gps_tracker extends eqLogic {
    /*     * *************************Attributs****************************** */
    /*     * ***********************Methode static*************************** */

	


//    public function postInsert()
//    {
//        $this->postUpdate();
//    }
    
    public function preSave() {
    }

    private function getListeDefaultCommandes()
    {
        return array( "photo"                => array('Photo objet suivi',   'action','slider',     "", 0, 1, "GENERIC_ACTION", 'gps_tracker::img_gpstr', 'gps_tracker::img_gpstr'),
                      "kilometrage"          => array('Kilometrage',         'info',  'numeric',  "km", 1, 1, "GENERIC_INFO",   'core::badge', 'core::badge'),
                      "record_period"        => array('Période enregistrement','info','numeric',    "", 1, 0, "GENERIC_INFO",   'core::badge', 'core::badge'),
                      "battery_tracker"      => array('Batterie traceur',    'info',  'numeric',   "%", 1, 1, "GENERIC_INFO",   'gps_tracker::battery_status_mmi_gpstr', 'gps_tracker::battery_status_mmi_gpstr'),
                      "gps_position"         => array('Position GPS',        'info',  'string',     "", 0, 1, "GENERIC_INFO",   'gps_tracker::opensmap_gpstr',   'gps_tracker::opensmap_gpstr'),
                      "gps_vitesse"          => array('Vitesse dépacement',  'info',  'numeric',"km/h", 1, 1, "GENERIC_INFO",   'core::badge', 'core::badge'),
                      "gps_position_lat"     => array('Position GPS Lat.',   'info',  'numeric',    "", 0, 0, "GENERIC_INFO",   'core::badge', 'core::badge'),
                      "gps_position_lon"     => array('Position GPS Lon.',   'info',  'numeric',    "", 0, 0, "GENERIC_INFO",   'core::badge', 'core::badge'),
                      "gps_dist_home"        => array('Distance maison',     'info',  'numeric',  "km", 1, 1, "GENERIC_INFO",   'core::line', 'core::line'),
                      "kinetic_moving"       => array('En mouvement',        'info',  'binary',     "", 1, 1, "GENERIC_INFO",   'gps_tracker::veh_moving_gpstr', 'gps_tracker::veh_moving_gpstr')
        );
    }

    // public function postSave() : Called after equipement saving
    // ==========================================================
    public function postSave()
    {
      // filtrage premier passage
      $tracker_type = $this->getConfiguration("type_tracker");
      $default_image = $this->getConfiguration("img_default");
      $eq_id = $this->getId();
      log::add('gps_tracker','debug',"postSave:Type traceur:".$tracker_type." / Eq.ID=".$eq_id);

      // Pour les traceurs TKSTAR, verification du Login API
      if ($tracker_type == "TKS") {
        $imei_id     = $this->getConfiguration("tkstar_imei");
        $tk_account  = $this->getConfiguration("tkstar_account");
        $tk_password = $this->getConfiguration("tkstar_password");
        if (($imei_id == "") || ($tk_account == "") || ($tk_password == "")) {
          log::add('gps_tracker','error',"postSave: TKS->Paramètres de Login API Traceur GPS non définis");
          return;
        }
        $session_gps_tracker = new api_tkstar();
        $session_gps_tracker->login($tk_account, $tk_password, NULL);
        $login_token = $session_gps_tracker->tkstar_api_login();   // Authentification
        if ($login_token["status"] == "KO") {
          log::add('gps_tracker','error',"postSave: TKS->Erreur Login API Traceur GPS");
          return;  // Erreur de login API Traceur GPS
        }
        $data_dir = "tks_".$imei_id;
        log::add('gps_tracker','debug',"postSave: TKS-> IMEI=".$imei_id." / login success=".$login_token["status"]." (données:data/".$data_dir.")" );
      }
      // Pour les traceurs JeedomConnect, verification de la commande "get GPS position"
      else if ($tracker_type == "JCN") {
        $jd_getposition_cmd  = $this->getConfiguration("cmd_jc_position");
        if ($jd_getposition_cmd == "") {
          log::add('gps_tracker','error',"postSave: JCN->Commande d'accès à la position GPS non definie");
          return;
        }
        $data_dir = "jcn_".$eq_id;
        log::add('gps_tracker','debug',"postSave: JCN-> EQ_ID=".$eq_id." (données:data/".$data_dir.")" );
      }
      // Pour les traceurs JeeMate, verification de la commande "get GPS position"
      else if ($tracker_type == "JMT") {
        $jd_getposition_cmd  = $this->getConfiguration("cmd_jm_position");
        if ($jd_getposition_cmd == "") {
          log::add('gps_tracker','error',"postSave: JMT->Commande d'accès à la position GPS non definie");
          return;
        }
        $data_dir = "jmt_".$eq_id;
        log::add('gps_tracker','debug',"postSave: JMT-> EQ_ID=".$eq_id." (données:data/".$data_dir.")" );
      }
      // Pour les traceurs Traccar, verification du login a la base traccar
      else if ($tracker_type == "TRC") {
        $trc_url      = $this->getConfiguration("trc_url");
        $trc_account  = $this->getConfiguration("trc_account");
        $trc_password = $this->getConfiguration("trc_password");
        $trc_idunic   = $this->getConfiguration("trc_idunic");
        if (($trc_url == "") || ($trc_account == "") || ($trc_password == "") || ($trc_idunic == "")) {
          log::add('gps_tracker','error',"postSave: TRC->Paramètres de Login Traccar non définis");
          return;
        }
        $session_traccar = new api_traccar();
        $session_traccar->login($trc_url, $trc_account, $trc_password);
        $res = $session_traccar->traccar_get_devices();  // liste devices existants
        if ($res["nb_dev"] == 0) {
          log::add('gps_tracker','error',"postSave: TRC->Erreur Login Traccar");
          return;  // Erreur de Login Traccar
        }
        $data_dir = "trc_".$trc_idunic;
        log::add('gps_tracker','debug',"postSave: TRC-> ID Unique=".$trc_idunic." (données:data/".$data_dir.")" );
      }
      // Pour les traceur Generiques, verification de la commande "get GPS position"
      else if ($tracker_type == "GEN") {
        $jd_getposition_cmd  = $this->getConfiguration("cmd_gen_position");
        $jd_getlat_cmd       = $this->getConfiguration("cmd_gen_info_lat");
        $jd_getlon_cmd       = $this->getConfiguration("cmd_gen_info_lon");
        if (($jd_getposition_cmd == "") && (($jd_getlat_cmd == "") || ($jd_getlon_cmd == ""))) {
          log::add('gps_tracker','error',"postSave: GEN->Commande d'accès à la position GPS non definie");
          return;
        }
        $data_dir = "gen_".$eq_id;
        log::add('gps_tracker','debug',"postSave: GEN-> EQ_ID=".$eq_id." (données:data/".$data_dir.")" );
      }

      // creation de la liste des commandes / infos
      foreach( $this->getListeDefaultCommandes() as $id => $data) {
        list($name, $type, $subtype, $unit, $hist, $visible, $generic_type, $template_dashboard, $template_mobile) = $data;
        $cmd = $this->getCmd(null, $id);
        if (! is_object($cmd)) {
          // New CMD
          $cmd = new gps_trackerCmd();
          $cmd->setName($name);
          $cmd->setEqLogic_id($this->getId());
          $cmd->setType($type);
          if ($type == "info") {
            $cmd->setDisplay ("showStatsOndashboard",0);
            $cmd->setDisplay ("showStatsOnmobile",0);
          }
          $cmd->setSubType($subtype);
          $cmd->setUnite($unit);
          $cmd->setLogicalId($id);
          $cmd->setIsHistorized($hist);
          $cmd->setIsVisible($visible);
          $cmd->setDisplay('generic_type', $generic_type);
          $cmd->setTemplate('dashboard', $template_dashboard);
          $cmd->setTemplate('mobile', $template_mobile);
          if ($id == "gps_position") {
            // Création des parametres de suivi des trajets
            $cmd->setConfiguration('trip_start_ts', 0);
            $cmd->setConfiguration('trip_start_mileage',  0);
            $cmd->setConfiguration('trip_in_progress', 0);
            $cmd->save();
          }
          else if ($id == "photo") {
            $img_dir = $data_dir;
            if ($default_image == True) {
              if      ($tracker_type == "TKS") $img_dir = "tks_def";
              else if ($tracker_type == "JCN") $img_dir = "jcn_def";
              else if ($tracker_type == "JMT") $img_dir = "jmt_def";
              else if ($tracker_type == "TRC") $img_dir = "trc_def";
              else if ($tracker_type == "GEN") $img_dir = "gen_def";
            }
            $param = $this->getId().','.$img_dir;
            $cmd->setConfiguration('listValue', 'PARAM|'.'&'.$param.'~');
            $cmd->save();
          }
          else {
            $cmd->save();
          }
        }
        else {
          // Upadate CMD
          $cmd->setType($type);
          if ($type == "info") {
            $cmd->setDisplay ("showStatsOndashboard",0);
            $cmd->setDisplay ("showStatsOnmobile",0);
          }
          $cmd->setSubType($subtype);
          $cmd->setUnite($unit);
          // $cmd->setIsHistorized($hist);
          // $cmd->setIsVisible($visible);
          $cmd->setDisplay('generic_type', $generic_type);
          // $cmd->setTemplate('dashboard', $template_dashboard);
          // $cmd->setTemplate('mobile', $template_mobile);
          if ($id == "gps_position") {
            // Création des parametres de suivi des trajets
            $cmd->setConfiguration('trip_start_ts', 0);
            $cmd->setConfiguration('trip_start_mileage',  0);
            $cmd->setConfiguration('trip_in_progress', 0);
            $cmd->save();
          }
          else if ($id == "photo") {
            $img_dir = $data_dir;
            if ($default_image == True) {
              if      ($tracker_type == "TKS") $img_dir = "tks_def";
              else if ($tracker_type == "JCN") $img_dir = "jcn_def";
              else if ($tracker_type == "JMT") $img_dir = "jmt_def";
              else if ($tracker_type == "TRC") $img_dir = "trc_def";
              else if ($tracker_type == "GEN") $img_dir = "gen_def";
            }
            $param = $this->getId().','.$img_dir;
            $cmd->setConfiguration('listValue', 'PARAM|'.'&'.$param.'~');
            $cmd->save();
            log::add('gps_tracker','debug',"postSave: param=".$param);
          }
          else {
            $cmd->save();
          }
        }
      }
      
      // ajout de la commande refresh data
      $refresh = $this->getCmd(null, 'refresh');
      if (!is_object($refresh)) {
        $refresh = new gps_trackerCmd();
        $refresh->setName(__('Rafraichir', __FILE__));
      }
      $refresh->setEqLogic_id($this->getId());
      $refresh->setLogicalId('refresh');
      $refresh->setType('action');
      $refresh->setSubType('other');
      $refresh->save();
      log::add('gps_tracker','debug','postSave:Ajout ou Mise à jour traceur GPS:'.$data_dir);
      
      // Creation du dossier pour stocker les donnees de l'objet suivi : "data/xxxx/" du plugin
      $data_fulldir = dirname(__FILE__).GPS_FILES_DIR_CL.$data_dir;
      if (!file_exists($data_fulldir)) {
        mkdir($data_fulldir, 0777);
      }

      // recopie de l'image de l'objet suivi dans ce dossier
      $img_tmp_path = dirname(__FILE__).GPS_FILES_DIR_CL."tmp/img.png";
      $img_dst_path = dirname(__FILE__).GPS_FILES_DIR_CL.$data_dir."/img.png";
      if (file_exists($img_tmp_path)) {      
        copy($img_tmp_path, $img_dst_path);
        unlink($img_tmp_path);  // suppression fichier
      }

    }

    public function preRemove() {
    }

    // ==========================================================================================
    // Fonction appelée au rythme de 1 mn (recuperation des informations courantes de la voiture)
    // ==========================================================================================
    public static function pull() {
      foreach (self::byType('gps_tracker') as $eqLogic) {
        $eqLogic->periodic_state(0);
      }
    }

    // Calcul de la distance entre 2 position GPS. Retour en km
    public function distance_compute ($lat0, $lon0, $lat1, $lon1) {
      $lat0r = deg2rad($lat0);
      $lon0r = deg2rad($lon0);
      $lat1r = deg2rad($lat1);
      $lon1r = deg2rad($lon1);
      $distance = 6371.01 * acos(sin($lat0r)*sin($lat1r) + cos($lat0r)* cos($lat1r)*cos($lon0r - $lon1r)); // calcul de la distance
      // $dist_home = round($distance, 3);
      if (is_nan($distance)) {
        // log::add('gps_tracker','error', "->Erreur sur le calcul de distance:".$dist_prev);
        $distance = 0.0;
      }
      // log::add('gps_tracker','debug', "distance_compute-> lat0/lon0=".$lat0."/".$lon0.", lat1/lon1=".$lat1."/".$lon1.", dist=".$distance);
      return($distance);
    }

    // Analyse des donnees du GPS JeedomConnect
    public function analyse_jcn ($date, $gps_posi, $prev_posi, $prev_mlg) {
      $gps_array = explode(",", $gps_posi);
      if (count($gps_array) < 5) {
        throw new Exception(__('Il manque des informations dans la commande GPS position', __FILE__));
      }
      $lat = floatval($gps_array[0]);
      $lon = floatval($gps_array[1]);
      $alt = floatval($gps_array[2]);
      $activite   = $gps_array[3];
      $batt_level = $gps_array[4];
      $vitesse = 0;
      if ($activite == "still")
        $kinetic_moving = 0;
      elseif ($activite == "on_foot")
        $kinetic_moving = 1;
      elseif ($activite == "running")
        $kinetic_moving = 2;
      elseif ($activite == "on_bicycle")
        $kinetic_moving = 3;
      elseif ($activite == "in_vehicle")
        $kinetic_moving = 4;
      else
        $kinetic_moving = 0;
      // Point GPS precedent
      $gps_array = explode(",", $prev_posi);
      $prev_lat = floatval($gps_array[0]);
      $prev_lon = floatval($gps_array[1]);
      // Distance depuis le point précédent
      $dist = $this->distance_compute ($lat, $lon, $prev_lat, $prev_lon);

      // Calcul du kilometrage courant
      $mlg = round($prev_mlg + $dist, 1);
      // Mise au format du fichier a generer
      $ret_gps["ts"] = $date;
      $ret_gps["posi"] = $lat.",".$lon.",".$alt;
      $ret_gps["misc"] = $vitesse.",".$mlg.",".$kinetic_moving;
      $ret_gps["batt"] = $batt_level;
      $ret_gps["lat"]  = $lat;
      $ret_gps["lon"]  = $lon;
      $ret_gps["vit"]  = $vitesse;
      $ret_gps["mlg"]  = $mlg;
      $ret_gps["kinect"] = $kinetic_moving;
      return($ret_gps);
    }
    
    // Analyse des donnees du GPS JeeMate
    public function analyse_jmt ($date, $gps_posi, $prev_posi, $activite, $prev_mlg, $batt_level) {
      $gps_array = explode(",", $gps_posi);
      if (count($gps_array) < 2) {
        throw new Exception(__('Il manque des informations dans la commande GPS position', __FILE__));
      }
      $lat = floatval($gps_array[0]);
      $lon = floatval($gps_array[1]);
      if (count($gps_array) == 3)
        $alt = floatval($gps_array[2]);
      else
        $alt = 0;
      $vitesse = 0;
      if ($activite == "still")  // Valeurs possibles: still, walking, in_vehicle
        $kinetic_moving = 0;
      elseif ($activite == "walking")
        $kinetic_moving = 1;
      // elseif ($activite == "running")
        // $kinetic_moving = 2;
      // elseif ($activite == "on_bicycle")
        // $kinetic_moving = 3;
      elseif ($activite == "in_vehicle")
        $kinetic_moving = 4;
      else
        $kinetic_moving = 0;
      // Point GPS precedent
      $gps_array = explode(",", $prev_posi);
      $prev_lat = floatval($gps_array[0]);
      $prev_lon = floatval($gps_array[1]);
      // Distance depuis le point précédent
      $dist = $this->distance_compute ($lat, $lon, $prev_lat, $prev_lon);

      // Calcul du kilometrage courant
      $mlg = round($prev_mlg + $dist, 1);
      // Mise au format du fichier a generer
      $ret_gps["ts"] = $date;
      $ret_gps["posi"] = $lat.",".$lon.",".$alt;
      $ret_gps["misc"] = $vitesse.",".$mlg.",".$kinetic_moving;
      $ret_gps["batt"] = $batt_level;
      $ret_gps["lat"]  = $lat;
      $ret_gps["lon"]  = $lon;
      $ret_gps["vit"]  = $vitesse;
      $ret_gps["mlg"]  = $mlg;
      $ret_gps["kinect"] = $kinetic_moving;
      return($ret_gps);
    }

    // Analyse des donnees du GPS Generique
    public function analyse_gen ($date, $gps_posi, $prev_posi, $param, $prev_mlg, &$mvt_cpt) {
      $gps_array = explode(",", $gps_posi);
      $nb_param = count($gps_array);
      $lat = "";
      $lon = "";
      $alt = 0;
      $vitesse = 0;
      for ($i=0; $i<4; $i++) {
        if ($nb_param > $i) {
          if ($param[$i] == "LAT")
            $lat = floatval($gps_array[$i]);
          else if ($param[$i] == "LON")
            $lon = floatval($gps_array[$i]);
          else if ($param[$i] == "ALT")
            $alt = floatval($gps_array[$i]);
          else if ($param[$i] == "SPD")
            $vitesse = floatval($gps_array[$i]);
        }
      }
      if (($lat == "") && ($lon == "")) {
        throw new Exception(__('Il manque des informations dans la commande GPS position', __FILE__));
      }
      // Point GPS precedent
      $gps_array = explode(",", $prev_posi);
      $prev_lat = floatval($gps_array[0]);
      $prev_lon = floatval($gps_array[1]);
      // Distance depuis le point précédent
      $dist = $this->distance_compute ($lat, $lon, $prev_lat, $prev_lon);
      // log::add('gps_tracker','debug', "distance:".$dist);
     // Gestion du deplacement
      if (($dist >= 0.050) && ($mvt_cpt < 5)) {      // 50m
        $mvt_cpt = $mvt_cpt + 1;
      }
      else if (($dist < 0.020) && ($mvt_cpt > 0)) {  // 20m
        $mvt_cpt = $mvt_cpt - 1;
      }
      $kinetic_moving = 0;
      if ($mvt_cpt >= 3)
        $kinetic_moving = 1;

      // Calcul du kilometrage courant
      $mlg = round($prev_mlg + $dist, 1);
      // Mise au format du fichier a generer
      $ret_gps["ts"] = $date;
      $ret_gps["posi"] = $lat.",".$lon.",".$alt;
      $ret_gps["misc"] = $vitesse.",".$mlg.",".$kinetic_moving;
      $ret_gps["batt"] = 0;
      $ret_gps["lat"]  = $lat;
      $ret_gps["lon"]  = $lon;
      $ret_gps["vit"]  = $vitesse;
      $ret_gps["mlg"]  = $mlg;
      $ret_gps["kinect"] = $kinetic_moving;
      return($ret_gps);
    }

    // Traitement periodique: lecture de l'état des traceurs GPS
    // =========================================================
    public function periodic_state($rfh) {
      $tracker_type = $this->getConfiguration("type_tracker");
      $minute = intval(date("i"));
      $heure  = intval(date("G"));
      $tracker_name = $this->getHumanName();
      $eq_id = $this->getId();

      // Pour les traceur TKSTAR, verification du Login API
      if ($tracker_type == "TKS") {
        $imei_id     = $this->getConfiguration("tkstar_imei");
        $tk_account  = $this->getConfiguration("tkstar_account");
        $tk_password = $this->getConfiguration("tkstar_password");
        if (($imei_id == "") || ($tk_account == "") || ($tk_password == "")) {
          return;
        }
        $data_dir = "tks_".$imei_id;
      }
      // Pour les traceurs JeedomConnect, verification de la commande "get GPS position"
      else if ($tracker_type == "JCN") {
        $jd_getposition_cmd  = $this->getConfiguration("cmd_jc_position");
        if ($jd_getposition_cmd == "") {
          return;
        }
        $data_dir = "jcn_".$eq_id;
      }
      // Pour les traceurs JeeMate, verification de la commande "get GPS position"
      else if ($tracker_type == "JMT") {
        $jd_getposition_cmd  = $this->getConfiguration("cmd_jm_position");
        if ($jd_getposition_cmd == "") {
          return;
        }
        $data_dir = "jmt_".$eq_id;
      }
      // Pour les traceurs tracccar, verification verification du Login au serveur
      else if ($tracker_type == "TRC") {
        $trc_url      = $this->getConfiguration("trc_url");
        $trc_account  = $this->getConfiguration("trc_account");
        $trc_password = $this->getConfiguration("trc_password");
        $trc_id       = $this->getConfiguration("trc_id");
        if (($trc_url == "") || ($trc_account == "") || ($trc_password == "") || ($trc_id == "")) {
          return;
        }
        $data_dir = "";  // Pas d'enregistrement pour un traceur "traccar" ; on utilise la base traccar
      }
      // Pour les traceurs Generiques, verification de la commande "get GPS position"
      else if ($tracker_type == "GEN") {
        $jd_getposition_cmd  = $this->getConfiguration("cmd_gen_position");
        $jd_getlat_cmd       = $this->getConfiguration("cmd_gen_info_lat");
        $jd_getlon_cmd       = $this->getConfiguration("cmd_gen_info_lon");
        if (($jd_getposition_cmd == "") && (($jd_getlat_cmd == "") || ($jd_getlon_cmd == ""))) {
          return;
        }
        $data_dir = "gen_".$eq_id;
      }

      // Fichiers de logs
      $fn_car_gps   = dirname(__FILE__).GPS_FILES_DIR_CL.$data_dir.'/gps.log';
      $fn_car_trips = dirname(__FILE__).GPS_FILES_DIR_CL.$data_dir.'/trips.log';

      // Appel API pour le statut courant du vehicule
      if ($this->getIsEnable()) {
        $cmd_record_period = $this->getCmd(null, "record_period");
        $record_period = $cmd_record_period->execCmd();
        if ($record_period == NULL)
          $record_period = 0;
        // log::add('gps_tracker','debug', $tracker_name."->record_period:".$record_period);

       
        // Toutes les mn => Mise à jour des informations du traceur
        // ========================================================
        $cmd_mlg = $this->getCmd(null, "kilometrage");
        $previous_mileage = $cmd_mlg->execCmd();
        $previous_ts = $cmd_mlg->getConfiguration('prev_ctime');
        $ctime = time();
        $cmd_gps = $this->getCmd(null, "gps_position");
        $previous_gps_position = $cmd_gps->execCmd();
        $cmd_kinetic = $this->getCmd(null, "kinetic_moving");
        $previous_kinetic_moving = $cmd_kinetic->execCmd();

        // Recuperation des donnees du traceur GPS, selon son type
        if ($tracker_type == "TKS") {
          // ------- Traceur TKSTAR ------
          $last_login_token = $cmd_record_period->getConfiguration('save_auth');
          if ((!isset($last_login_token)) || ($last_login_token == "") || ($rfh==1))
            $last_login_token = NULL;
          $session_gps_tracker = new api_tkstar();
          $session_gps_tracker->login($tk_account, $tk_password, $last_login_token);
          if ($last_login_token == NULL) {
            $login_token = $session_gps_tracker->tkstar_api_login();   // Authentification
            if ($login_token["status"] != "OK") {
              log::add('gps_tracker','error', $tracker_name."->Erreur Login API Traceur GPS (Pas de session en cours)");
              return;  // Erreur de login API Traceur GPS
            }
            $cmd_record_period->setConfiguration ('save_auth', $login_token);
            $cmd_record_period->save();
            log::add('gps_tracker','info', $tracker_name."->Pas de session en cours => New login");
          }
          else if ($session_gps_tracker->state_login() == 0) {
            $login_token = $session_gps_tracker->tkstar_api_login();   // Authentification
            if ($login_token["status"] != "OK") {
              log::add('gps_tracker','error', $tracker_name."->Erreur Login API Traceur GPS (Session expirée)");
              return;  // Erreur de login API Traceur GPS
            }
            $cmd_record_period->setConfiguration ('save_auth', $login_token);
            $cmd_record_period->save();
            log::add('gps_tracker','info', $tracker_name."->Session expirée => New login");
          }
          // Capture des donnes courantes issues du GPS
          $ret = $session_gps_tracker->tkstar_api_getdata();
          if ($ret["status"] == "KO") {
            log::add('gps_tracker','error', $tracker_name."->Erreur Login API Traceur GPS (Access data)");
            return;  // Erreur de login API Traceur GPS
            }
          $gps_position_hist = [];
          // extraction des donnees utiles
          $lat = floatval($ret["result"]->lat);
          $lon = floatval($ret["result"]->lng);
          $alt = 0;
          $vitesse = round(floatval($ret["result"]->speed), 1);
          $kinetic_moving = ($ret["result"]->isStop == "1") ? 0 : 1;
          $batt_level = $ret["result"]->battery;
          // Calcul distance point precedent
          $previous_gps_latlon = explode(",", $previous_gps_position);
          $dist_prev = $this->distance_compute(floatval($previous_gps_latlon[0]), floatval($previous_gps_latlon[1]), $lat, $lon);
          $new_mlg = round($previous_mileage + $dist_prev, 1);
          $record1 = $lat.",".$lon.",".$alt;
          $record2 = $vitesse.",".$new_mlg.",".$kinetic_moving;
        }
        else if ($tracker_type == "JCN") {
          // ------- Traceur Jeedom Connect ------
          // execution commande position pour l'objet suivi
          $jd_getposition_cmdname = str_replace('#', '', $this->getConfiguration('cmd_jc_position'));
          $jd_getposition_cmd  = cmd::byId($jd_getposition_cmdname);
          if (!is_object($jd_getposition_cmd)) {
            throw new Exception(__('Impossible de trouver la commande gps position', __FILE__));
          }
          // Capture l'historique de la derniere minute
          $debut = date("Y-m-d H:i:s", strtotime("Now")-60);
          $fin = date("Y-m-d H:i:s", strtotime("Now")); 
          $cmdId = $jd_getposition_cmd->getId();
          $values = history::all($cmdId, $debut, $fin);
          $nb_data_histo = count($values);
          // log::add('gps_tracker','debug', $tracker_name."->history: nb_data_histo =".$nb_data_histo);
          $gps_position_hist = [];
          $prev_posi = $previous_gps_position;
          $current_milleage = $previous_mileage;
          foreach ($values as $value) {
            $date = $value->getDatetime();
            $posi = $value->getValue();
            $ret_gps = $this->analyse_jcn (strtotime($date), $posi, $prev_posi, $current_milleage);
            $record = $ret_gps["ts"].",".$ret_gps["posi"].",".$ret_gps["misc"];
            array_push($gps_position_hist, $record);
            log::add('gps_tracker','debug', $tracker_name."->history: record ".$record);
            $prev_posi = $posi;
            $current_milleage = $ret_gps["mlg"];
          }
          // Capture la position courante (si pas fait par l'historique précédent)
          if ($nb_data_histo == 0) {
            $gps_position = $jd_getposition_cmd->execCmd();
            $ret_gps = $this->analyse_jcn ($ctime, $gps_position, $prev_posi, $current_milleage);
            // log::add('gps_tracker','debug', $tracker_name."->gps_position: ".$gps_position." & prev_posi: ".$prev_posi);
          }
          $lat = $ret_gps["lat"];
          $lon = $ret_gps["lon"];
          $vitesse = $ret_gps["vit"];
          $batt_level     = $ret_gps["batt"];
          $kinetic_moving = $ret_gps["kinect"];
          $new_mlg = $ret_gps["mlg"];
          $record1 = $ret_gps["posi"];
          $record2 = $ret_gps["misc"];
        }
        else if ($tracker_type == "JMT") {
          // ------- Traceur JeeMate ------
          // execution commande position pour l'objet suivi
          $jd_getposition_cmdname = str_replace('#', '', $this->getConfiguration('cmd_jm_position'));
          $jd_getposition_cmd  = cmd::byId($jd_getposition_cmdname);
          if (!is_object($jd_getposition_cmd)) {
            throw new Exception(__('Impossible de trouver la commande gps position', __FILE__));
          }
          // execution commande activite pour l'objet suivi (Jeedom Connect)
          $jd_getactivite_cmdname = str_replace('#', '', $this->getConfiguration('cmd_jm_activite'));
          $jd_getactivite_cmd  = cmd::byId($jd_getactivite_cmdname);
          if (!is_object($jd_getactivite_cmd)) {
            throw new Exception(__('Impossible de trouver la commande gps activité', __FILE__));
          }
          $activite = $jd_getactivite_cmd->execCmd();
          // execution commande batterie pour l'objet suivi (Jeedom Connect)
          $jd_getbatterie_cmdname = str_replace('#', '', $this->getConfiguration('cmd_jm_batterie'));
          $jd_getbatterie_cmd  = cmd::byId($jd_getbatterie_cmdname);
          if (!is_object($jd_getbatterie_cmd)) {
            throw new Exception(__('Impossible de trouver la commande gps batterie', __FILE__));
          }
          $batt_level = $jd_getbatterie_cmd->execCmd();

          // Capture l'historique de la derniere minute
          $debut = date("Y-m-d H:i:s", strtotime("Now")-60);
          $fin = date("Y-m-d H:i:s", strtotime("Now")); 
          $cmdId = $jd_getposition_cmd->getId();
          $values = history::all($cmdId, $debut, $fin);
          $gps_position_hist = [];
          $prev_posi = $previous_gps_position;
          $current_milleage = $previous_mileage;
          foreach ($values as $value) {
            $date = $value->getDatetime();
            $posi = $value->getValue();
            $ret_gps = $this->analyse_jmt (strtotime($date), $posi, $prev_posi, $activite, $current_milleage, $batt_level);
            $record = $ret_gps["ts"].",".$ret_gps["posi"].",".$ret_gps["misc"];
            array_push($gps_position_hist, $record);
            log::add('gps_tracker','debug', $tracker_name."->history: record ".$record);
            $prev_posi = $posi;
            $current_milleage = $ret_gps["mlg"];
          }
          // Capture la position courante
          $gps_position = $jd_getposition_cmd->execCmd();
          // log::add('gps_tracker','debug', $tracker_name."->gps_position: ".$gps_position);
          $ret_gps = $this->analyse_jmt ($ctime, $gps_position, $prev_posi, $activite, $current_milleage, $batt_level);
          $lat = $ret_gps["lat"];
          $lon = $ret_gps["lon"];
          $vitesse = $ret_gps["vit"];
          $kinetic_moving = $ret_gps["kinect"];
          $new_mlg = $ret_gps["mlg"];
          $record1 = $ret_gps["posi"];
          $record2 = $ret_gps["misc"];
        }
        if ($tracker_type == "TRC") {
          // ------- Traceur Traccar ------
          $trc_url      = $this->getConfiguration("trc_url");
          $trc_account  = $this->getConfiguration("trc_account");
          $trc_password = $this->getConfiguration("trc_password");
          $trc_id       = $this->getConfiguration("trc_id");
          if (($trc_url == "") || ($trc_account == "") || ($trc_password == "") || ($trc_id == "")) {
            log::add('gps_tracker','error',"postSave: TRC->Paramètres de Login Traccar non définis");
            return;
          }
          $session_traccar = new api_traccar();
          $session_traccar->login($trc_url, $trc_account, $trc_password);
          // Capture des donnes courantes issues du GPS
          $res = $session_traccar->traccar_get_positions($trc_id);
          if ($res["status"] == "KO") {
            log::add('gps_tracker','error', $tracker_name."->Erreur position traceur traccar");
            $lat = 0;
            $lon = 0;
            $vitesse = 0;
            $batt_level = 0;
            $kinetic_moving = false;
          }
          else {
            // extraction des donnees utiles
            $lat = floatval($res["lat"]);
            $lon = floatval($res["lon"]);
            $vitesse = round(floatval($res["spd"]), 1);
            $batt_level = floatval($res["batt"]);
            $kinetic_moving = floatval($res["motion"]);
          }
          $gps_position_hist = [];
          $alt = 0 ;
          $previous_gps_latlon = explode(",", $previous_gps_position);
          $dist_prev = $this->distance_compute(floatval($previous_gps_latlon[0]), floatval($previous_gps_latlon[1]), $lat, $lon);
          $new_mlg = round($previous_mileage + $dist_prev, 1);
          $record1 = $lat.",".$lon.",".$alt;
          $record2 = $vitesse.",".$new_mlg.",".$kinetic_moving;
        }
        else if ($tracker_type == "GEN") {
          // ------- Traceur Generique ------
          // execution commande position pour l'objet suivi
          $jd_getposition_cmdname = str_replace('#', '', $this->getConfiguration('cmd_gen_position'));
          $gen_sep = ($jd_getposition_cmdname == "")? 1:0;
          $jd_getposition_cmd= cmd::byId($jd_getposition_cmdname);
          $jd_getlat_cmdname = str_replace('#', '', $this->getConfiguration('cmd_gen_info_lat'));
          $jd_getlat_cmd     = cmd::byId($jd_getlat_cmdname);
          $jd_getlon_cmdname = str_replace('#', '', $this->getConfiguration('cmd_gen_info_lon'));
          $jd_getlon_cmd     = cmd::byId($jd_getlon_cmdname);
          // log::add('gps_tracker','debug', $tracker_name."-> gen_sep: ".$gen_sep);
          if (($gen_sep == 0) && (!is_object($jd_getposition_cmd))) {
            throw new Exception(__('Impossible de trouver la commande gps position: Gen', __FILE__));
          }
          if (($gen_sep == 1) && ((!is_object($jd_getlat_cmd)) || (!is_object($jd_getlon_cmd)))) {
            throw new Exception(__('Impossible de trouver la commande gps position: Gen sep', __FILE__));
          }
          $mvt_cpt = $cmd_kinetic->getConfiguration('mvt_cpt');
          if (!isset($mvt_cpt) || ($mvt_cpt == ""))
            $mvt_cpt = 0;
          $batt_level = 0;
          $debut = date("Y-m-d H:i:s", strtotime("Now")-60);
          $fin = date("Y-m-d H:i:s", strtotime("Now")); 

          if ($gen_sep == 0) {
            // cas des donnes sur un champ info
            $param = array($this->getConfiguration("gen_param1"), $this->getConfiguration("gen_param2"),
                           $this->getConfiguration("gen_param3"), $this->getConfiguration("gen_param4"));
            // Capture l'historique de la derniere minute
            $cmdId = $jd_getposition_cmd->getId();
            $values = history::all($cmdId, $debut, $fin);
            $gps_position_hist = [];
            $prev_posi = $previous_gps_position;
            $current_milleage = $previous_mileage;
            foreach ($values as $value) {
              $date = $value->getDatetime();
              $posi = $value->getValue();
              $ret_gps = $this->analyse_gen (strtotime($date), $posi, $prev_posi, $param, $current_milleage, $mvt_cpt);
              $record = $ret_gps["ts"].",".$ret_gps["posi"].",".$ret_gps["misc"];
              array_push($gps_position_hist, $record);
              if ($mvt_cpt != 0)
                log::add('gps_tracker','debug', $tracker_name."->history: record ".$record." / mvt_cpta = ".$mvt_cpt);
              $prev_posi = $ret_gps["posi"];
              $current_milleage = $ret_gps["mlg"];
            }
            // Capture la position courante
            // $gps_position = $jd_getposition_cmd->execCmd();
            // log::add('gps_tracker','debug', $tracker_name."->gps_position: ".$gps_position);
            // $ret_gps = $this->analyse_gen ($ctime, $gps_position, $prev_posi, $param, $current_milleage, $mvt_cpt);
            // $record = $ret_gps["ts"].",".$ret_gps["posi"].",".$ret_gps["misc"];
            // if ($mvt_cpt != 0)
              // log::add('gps_tracker','debug', $tracker_name."->history: record ".$record." / mvt_cptb = ".$mvt_cpt);
          }
          else {
            // cas des donnes sur plusieurs champs info
            $prev_posi = $previous_gps_position;
            $current_milleage = $previous_mileage;
            $param = array("LAT", "LON", "ALT", "SPD");
            $jd_getalt_cmd = cmd::byId(str_replace('#', '', $this->getConfiguration('cmd_gen_info_alt')));
            $jd_getspd_cmd = cmd::byId(str_replace('#', '', $this->getConfiguration('cmd_gen_info_spd')));

            // Capture l'historique de la derniere minute
            $cmdId = $jd_getlat_cmd->getId();
            $values_lat = history::all($cmdId, $debut, $fin);
            $cmdId = $jd_getlon_cmd->getId();
            $values_lon = history::all($cmdId, $debut, $fin);
            // log::add('gps_tracker','debug', $tracker_name."->history_lat / history_lon: ".count($values_lat)." / ".count($values_lon));
            if (is_object($jd_getalt_cmd)) {
              $cmdId = $jd_getalt_cmd->getId();
              $values_alt = history::all($cmdId, $debut, $fin);
              // log::add('gps_tracker','debug', $tracker_name."->history_alt: ".count($values_alt));
            }
            if (is_object($jd_getspd_cmd)) {
              $cmdId = $jd_getspd_cmd->getId();
              $values_spd = history::all($cmdId, $debut, $fin);
              // log::add('gps_tracker','debug', $tracker_name."->history_spd: ".count($values_spd));
            }
            $gps_position_hist = [];
            $idx = 0;
            foreach ($values as $values_lat) {
              $date = $value->getDatetime();
              $posi_lat = $value->getValue();
              $posi_lon = $values_lon[$idx]->getValue();
              $posi = $posi_lat.",".$posi_lon;
              if (is_object($jd_getalt_cmd)) {
                $posi_alt = $values_alt[$idx]->getValue();
                if ($posi_alt != "") $posi .= ",".$posi_alt;
              }
              if (is_object($jd_getspd_cmd)) {
                $posi_spd = $values_spd[$idx]->getValue();
                if ($posi_spd != "") $posi .= ",".$posi_spd;
              }
              $ret_gps = $this->analyse_gen (strtotime($date), $posi, $prev_posi, $param, $current_milleage, $mvt_cpt);
              $record = $ret_gps["ts"].",".$ret_gps["posi"].",".$ret_gps["misc"];
              array_push($gps_position_hist, $record);
              log::add('gps_tracker','debug', $tracker_name."->history: record ".$record." / mvt_cpta = ".$mvt_cpt);
              $prev_posi = $ret_gps["posi"];
              $current_milleage = $ret_gps["mlg"];
              $idx++;
            }

            // Capture la position courante
            $lat = $jd_getlat_cmd->execCmd();
            $lon = $jd_getlon_cmd->execCmd();
            $gps_position = $lat.",".$lon;
            if (is_object($jd_getalt_cmd)) {
              $alt = $jd_getalt_cmd->execCmd();
              if ($alt != "") $gps_position .= ",".$alt;
            }
            if (is_object($jd_getspd_cmd)) {
              $spd = $jd_getspd_cmd->execCmd();
              if ($spd != "") $gps_position .= ",".$spd;
            }
            $ret_gps = $this->analyse_gen ($ctime, $gps_position, $prev_posi, $param, $current_milleage, $mvt_cpt);
            $record = $ret_gps["ts"].",".$ret_gps["posi"].",".$ret_gps["misc"];
            log::add('gps_tracker','debug', $tracker_name."->history: record ".$record." / mvt_cptb = ".$mvt_cpt);
          }

          $lat = $ret_gps["lat"];
          $lon = $ret_gps["lon"];
          $vitesse = $ret_gps["vit"];
          $kinetic_moving = $ret_gps["kinect"];
          $new_mlg = $ret_gps["mlg"];
          $record1 = $ret_gps["posi"];
          $record2 = $ret_gps["misc"];
          $cmd_kinetic->setConfiguration('mvt_cpt', $mvt_cpt);
          $cmd_kinetic->save();
          // log::add('gps_tracker','debug', $tracker_name."->: mvt_cpt=".$mvt_cpt);
        }

        // Traitement des informations retournees
        // log::add('gps_tracker','debug', $tracker_name."->MAJ des données du traceur GPS: ".$data_dir);
        $cmd = $this->getCmd(null, "gps_vitesse");
        $cmd->event($vitesse);
        $cmd = $this->getCmd(null, "battery_tracker");
        $cmd->event($batt_level);
        $cmd_kinetic->event($kinetic_moving);

        // Etat courant du trajet
        $trip_start_ts       = $cmd_gps->getConfiguration('trip_start_ts');
        $trip_start_mileage  = $cmd_gps->getConfiguration('trip_start_mileage');
        $trip_in_progress    = $cmd_gps->getConfiguration('trip_in_progress');
        if (($lat == 0) && ($lon == 0))
          $gps_pts_ok = false; // points GPS non valide
        else
          $gps_pts_ok = true;

        if ($gps_pts_ok == true) {
          // log::add('gps_tracker','debug',$tracker_name."->Refresh:GPS position =>".$record1);
          // log::add('gps_tracker','debug',$tracker_name."->Refresh:previous_gps_position=".$previous_gps_position);
          $eq_id = $this->getId();
          $cmd_gps->event($record1.",".$eq_id);   // ajout de l'ID plugin pour transmission au process AJAX sur serveur
          $cmd_gpslat = $this->getCmd(null, "gps_position_lat");
          $cmd_gpslat->event($lat);
          $cmd_gpslon = $this->getCmd(null, "gps_position_lon");
          $cmd_gpslon->event($lon);

          // Calcul distance maison
          $dist_home = $this->distance_compute(floatval(config::byKey("info::latitude")), floatval(config::byKey("info::longitude")), $lat, $lon);
          $cmd_dis_home = $this->getCmd(null, "gps_dist_home");
          $cmd_dis_home->event(round($dist_home, 2));
          // Nouveau kilometrage
          $cmd_mlg->event($new_mlg);
        }

        // Analyse debut et fin de trajet
        $trip_event = 0;
        if ($trip_in_progress == 0) {
          // Pas de trajet en cours
          if (($kinetic_moving > 0) && ($previous_mileage != 0)) {
            // debut de trajet
            $trip_start_ts       = $previous_ts;
            $trip_start_mileage  = $previous_mileage;
            $trip_in_progress    = 1;
            $trip_event = 1;
            $cmd_gps->setConfiguration('trip_start_ts', $trip_start_ts);
            $cmd_gps->setConfiguration('trip_start_mileage', $trip_start_mileage);
            $cmd_gps->setConfiguration('trip_in_progress', $trip_in_progress);
            $cmd_gps->save();
          }
        }
        else {
          // Un trajet est en cours
          if (($kinetic_moving == 0) && ($previous_kinetic_moving == 0)) {
            // fin de trajet
            $trip_end_ts       = $ctime;
            $trip_end_mileage  = $new_mlg;
            $trip_in_progress  = 0;
            $trip_event = 1;
            // enregistrement d'un trajet
            $trip_distance = round($trip_end_mileage - $trip_start_mileage, 1);
            $trip_log_dt = $trip_start_ts.",".$trip_end_ts.",".$trip_distance."\n";
            log::add('gps_tracker','info', $tracker_name."->Refresh->recording Trip_dt=".$trip_log_dt);
            file_put_contents($fn_car_trips, $trip_log_dt, FILE_APPEND | LOCK_EX);
            $cmd_gps->setConfiguration('trip_in_progress', $trip_in_progress);
            $cmd_gps->save();
          }
        }
        // Log position courante vers GPS log file (pas si vehicule à l'arrêt "à la maison"). Et pas d'écriture pour le serveur traccar (data_dir = "")
        if (($gps_pts_ok == true) && (($kinetic_moving > 0) || ($previous_kinetic_moving > 0)) && ($data_dir != "")) {
          // Record des donnees issues de l'historique si elles existent
          if (count($gps_position_hist) > 0) {
            foreach ($gps_position_hist as $gps_posi) {
              $gps_log_dt = $gps_posi."\n";
              log::add('gps_tracker','debug', $tracker_name."->Refresh->recording hist Gps_dt=".$gps_log_dt);
              file_put_contents($fn_car_gps, $gps_log_dt, FILE_APPEND | LOCK_EX);
            }
          }
          // Record des donnees courantes
          $gps_log_dt = $ctime.",".$record1.",".$record2."\n";
          log::add('gps_tracker','debug', $tracker_name."->Refresh->recording Gps_dt=".$gps_log_dt);
          file_put_contents($fn_car_gps, $gps_log_dt, FILE_APPEND | LOCK_EX);
        }
        // enregistre le ts du point courant
        $cmd_mlg->setConfiguration('prev_ctime', $ctime);
        $cmd_mlg->save();

      }
    }

}


// Classe pour les commandes du plugin
// ===================================
class gps_trackerCmd extends cmd 
{
    /*     * *************************Attributs****************************** */
    public function execute($_options = null) {
        //log::add('gps_tracker','info',"execute:".$_options['message']);
        if ($this->getLogicalId() == 'refresh') {
          foreach (eqLogic::byType('gps_tracker') as $eqLogic) {
            $eqLogic->periodic_state(1);
          }
        }
        

        
    }


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */
    

    /*     * **********************Getteur Setteur*************************** */
}

?>

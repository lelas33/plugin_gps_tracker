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
      log::add('gps_tracker','debug',"postSave:Type traceur:".$tracker_type);

      // Pour les traceur TKSTAR, verification du Login API
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
      // Pour les traceur JeedomConnect, verification de la commande "get GPS position"
      else if ($tracker_type == "JCN") {
        $jd_getposition_cmd  = $this->getConfiguration("cmd_jc_position");
        if ($jd_getposition_cmd == "") {
          log::add('gps_tracker','error',"postSave: JCN->Commande d'accès à la position GPS non definie");
          return;
        }
        $jd_getposition_cmdf = str_replace ('#', '', $jd_getposition_cmd);
        $data_dir = "jcn_".$jd_getposition_cmdf;
        log::add('gps_tracker','debug',"postSave: JCN-> TEL_ID=".$jd_getposition_cmdf." (données:data/".$data_dir.")" );
      }
      // Pour les traceur JeeMate, verification de la commande "get GPS position"
      else if ($tracker_type == "JMT") {
        $jd_getposition_cmd  = $this->getConfiguration("cmd_jm_position");
        if ($jd_getposition_cmd == "") {
          log::add('gps_tracker','error',"postSave: JMT->Commande d'accès à la position GPS non definie");
          return;
        }
        $jd_getposition_cmdf = str_replace ('#', '', $jd_getposition_cmd);
        $data_dir = "jmt_".$jd_getposition_cmdf;
        log::add('gps_tracker','debug',"postSave: JMT-> TEL_ID=".$jd_getposition_cmdf." (données:data/".$data_dir.")" );
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
            if ($default_image == True) {
              if      ($tracker_type == "TKS") $data_dir = "tks_def";
              else if ($tracker_type == "JCN") $data_dir = "jcn_def";
              else if ($tracker_type == "JMT") $data_dir = "jmt_def";
            }
            $param = $this->getId().','.$data_dir;
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
            // $cmd->setConfiguration('listValue', 'PATH|'.'&'.$data_dir.'~');
            if ($default_image == True) {
              if      ($tracker_type == "TKS") $data_dir = "tks_def";
              else if ($tracker_type == "JCN") $data_dir = "jcn_def";
              else if ($tracker_type == "JMT") $data_dir = "jmt_def";
            }
            $param = $this->getId().','.$data_dir;
            $cmd->setConfiguration('listValue', 'PARAM|'.'&'.$param.'~');
            $cmd->save();
            log::add('gps_tracker','debug',"postSave: param=".$param);
          }
          else {
            $cmd->save($data_dir);
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

    // Calcul de la distance entre 2 position GPS
    public function distance_compute ($lat0, $lon0, $lat1, $lon1) {
      $lat0r = deg2rad($lat0);
      $lon0r = deg2rad($lon0);
      $lat1r = deg2rad($lat1);
      $lon1r = deg2rad($lon1);
      $distance = 6371.01 * acos(sin($lat0r)*sin($lat1r) + cos($lat0r)* cos($lat1r)*cos($lon0r - $lon1r)); // calcul de la distance
      // $dist_home = round($distance, 3);
      if (is_nan($distance)) {
        // log::add('gps_tracker','error', $tracker_name."->Erreur sur le calcul de distance:".$dist_prev);
        $distance = 0.0;
      }
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
    public function analyse_jmt ($date, $gps_posi, $prev_posi, $activite, $prev_mlg) {
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

    // Lecture des statuts du vehicule connecté
    public function periodic_state($rfh) {
      $tracker_type = $this->getConfiguration("type_tracker");
      $minute = intval(date("i"));
      $heure  = intval(date("G"));
      $tracker_name = $this->getHumanName();

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
      // Pour les traceur JeedomConnect, verification de la commande "get GPS position"
      else if ($tracker_type == "JCN") {
        $jd_getposition_cmd  = $this->getConfiguration("cmd_jc_position");
        if ($jd_getposition_cmd == "") {
          return;
        }
        $jd_getposition_cmdf = str_replace ('#', '', $jd_getposition_cmd);
        $data_dir = "jcn_".$jd_getposition_cmdf;
      }
      // Pour les traceur JeeMate, verification de la commande "get GPS position"
      else if ($tracker_type == "JMT") {
        $jd_getposition_cmd  = $this->getConfiguration("cmd_jm_position");
        if ($jd_getposition_cmd == "") {
          return;
        }
        $jd_getposition_cmdf = str_replace ('#', '', $jd_getposition_cmd);
        $data_dir = "jmt_".$jd_getposition_cmdf;
      }

      // Appel API pour le statut courant du vehicule
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
          // Capture la position courante
          $gps_position = $jd_getposition_cmd->execCmd();
          // log::add('gps_tracker','debug', $tracker_name."->gps_position: ".$gps_position);
          $ret_gps = $this->analyse_jcn ($ctime, $gps_position, $prev_posi, $current_milleage);
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
            $ret_gps = $this->analyse_jmt (strtotime($date), $posi, $prev_posi, $activite, $current_milleage);
            $record = $ret_gps["ts"].",".$ret_gps["posi"].",".$ret_gps["misc"];
            array_push($gps_position_hist, $record);
            log::add('gps_tracker','debug', $tracker_name."->history: record ".$record);
            $prev_posi = $posi;
            $current_milleage = $ret_gps["mlg"];
          }
          // Capture la position courante
          $gps_position = $jd_getposition_cmd->execCmd();
          // log::add('gps_tracker','debug', $tracker_name."->gps_position: ".$gps_position);
          $ret_gps = $this->analyse_jmt ($ctime, $gps_position, $prev_posi, $activite, $current_milleage);
          $lat = $ret_gps["lat"];
          $lon = $ret_gps["lon"];
          $vitesse = $ret_gps["vit"];
          $kinetic_moving = $ret_gps["kinect"];
          $new_mlg = $ret_gps["mlg"];
          $record1 = $ret_gps["posi"];
          $record2 = $ret_gps["misc"];

        }
        // Traitement des informations retournees
        // log::add('gps_tracker','debug', $tracker_name."->MAJ des données du traceur GPS: ".$data_dir);
        $cmd = $this->getCmd(null, "gps_vitesse");
        $cmd->event($vitesse);
        $cmd = $this->getCmd(null, "battery_tracker");
        $cmd->event($batt_level);
        $cmd = $this->getCmd(null, "kinetic_moving");
        $previous_kinetic_moving = $cmd->execCmd();
        $cmd->event($kinetic_moving);

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
        // Log position courante vers GPS log file (pas si vehicule à l'arrêt "à la maison" et pas si "trajets alternatifs")
        if (($gps_pts_ok == true) && (($kinetic_moving > 0) || ($previous_kinetic_moving > 0))) {
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

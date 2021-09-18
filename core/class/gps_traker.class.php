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
class gps_traker extends eqLogic {
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
        return array( "photo"                => array('Photo objet suivi',   'action','slider',     "", 0, 1, "GENERIC_ACTION", 'gps_traker::img_gpstr', 'gps_traker::img_gpstr'),
                      "kilometrage"          => array('Kilometrage',         'info',  'numeric',  "km", 1, 1, "GENERIC_INFO",   'core::badge', 'core::badge'),
                      "record_period"        => array('Période enregistrement','info','numeric',    "", 1, 0, "GENERIC_INFO",   'core::badge', 'core::badge'),
                      "battery_traker"       => array('Batterie traceur',    'info',  'numeric',   "%", 1, 1, "GENERIC_INFO",   'gps_traker::battery_status_mmi_gpstr', 'gps_traker::battery_status_mmi_gpstr'),
                      "gps_position"         => array('Position GPS',        'info',  'string',     "", 0, 1, "GENERIC_INFO",   'gps_traker::opensmap_gpstr',   'gps_traker::opensmap_gpstr'),
                      "gps_vitesse"          => array('Vitesse dépacement',  'info',  'numeric',"km/h", 1, 1, "GENERIC_INFO",   'core::badge', 'core::badge'),
                      "gps_position_lat"     => array('Position GPS Lat.',   'info',  'numeric',    "", 0, 0, "GENERIC_INFO",   'core::badge', 'core::badge'),
                      "gps_position_lon"     => array('Position GPS Lon.',   'info',  'numeric',    "", 0, 0, "GENERIC_INFO",   'core::badge', 'core::badge'),
                      "gps_dist_home"        => array('Distance maison',     'info',  'numeric',  "km", 1, 1, "GENERIC_INFO",   'core::line', 'core::line'),
                      "kinetic_moving"       => array('Voiture en mouvement','info',  'binary',     "", 1, 1, "GENERIC_INFO",   'gps_traker::veh_moving_gpstr', 'gps_traker::veh_moving_gpstr')
        );
    }

    // public function postSave() : Called after equipement saving
    // ==========================================================
    public function postSave()
    {
      // filtrage premier passage
      $traker_type = $this->getConfiguration("type_traker");
      log::add('gps_traker','debug',"postSave:Type traceur:".$traker_type);

      // Pour les traceur TKSTAR, verification du Login API
      if ($traker_type == "TKS") {
        $imei_id     = $this->getConfiguration("tkstar_imei");
        $tk_account  = $this->getConfiguration("tkstar_account");
        $tk_password = $this->getConfiguration("tkstar_password");
        if (($imei_id == "") || ($tk_account == "") || ($tk_password == "")) {
          log::add('gps_traker','error',"postSave: TKS->Paramètres de Login API Traceur GPS non définis");
          return;
        }
        $session_gps_traker = new api_tkstar();
        $session_gps_traker->login($tk_account, $tk_password, NULL);
        $login_token = $session_gps_traker->tkstar_api_login();   // Authentification
        if ($login_token["status"] == "KO") {
          log::add('gps_traker','error',"postSave: TKS->Erreur Login API Traceur GPS");
          return;  // Erreur de login API Traceur GPS
        }
        $data_dir = "tks_".$imei_id;
        log::add('gps_traker','debug',"postSave: TKS-> IMEI=".$imei_id." / login success=".$login_token["status"]." (données:data/".$data_dir.")" );
      }
      // Pour les traceur JeedomConnect, verification de la commande "get GPS position"
      else if ($traker_type == "JCN") {
        $jd_getposition_cmd  = $this->getConfiguration("cmd_jc_position");
        if ($jd_getposition_cmd == "") {
          log::add('gps_traker','error',"postSave: JCN->Commande d'accès à la position GPS non definie");
          return;
        }
        $jd_getposition_cmdf = str_replace ('#', '', $jd_getposition_cmd);
        $data_dir = "jcn_".$jd_getposition_cmdf;
        log::add('gps_traker','debug',"postSave: JCN-> TEL_ID=".$jd_getposition_cmdf." (données:data/".$data_dir.")" );
      }

      // creation de la liste des commandes / infos
      foreach( $this->getListeDefaultCommandes() as $id => $data) {
        list($name, $type, $subtype, $unit, $hist, $visible, $generic_type, $template_dashboard, $template_mobile) = $data;
        $cmd = $this->getCmd(null, $id);
        if (! is_object($cmd)) {
          // New CMD
          $cmd = new gps_trakerCmd();
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
            // $cmd->setConfiguration('listValue', 'IMEI|'.$imei_id);
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
            $param = $this->getId().','.$data_dir;
            $cmd->setConfiguration('listValue', 'PARAM|'.'&'.$param.'~');
            $cmd->save();
            log::add('gps_traker','debug',"postSave: param=".$param);
          }
          else {
            $cmd->save($data_dir);
          }
        }
      }
      
      // ajout de la commande refresh data
      $refresh = $this->getCmd(null, 'refresh');
      if (!is_object($refresh)) {
        $refresh = new gps_trakerCmd();
        $refresh->setName(__('Rafraichir', __FILE__));
      }
      $refresh->setEqLogic_id($this->getId());
      $refresh->setLogicalId('refresh');
      $refresh->setType('action');
      $refresh->setSubType('other');
      $refresh->save();
      log::add('gps_traker','debug','postSave:Ajout ou Mise à jour traceur GPS:'.$data_dir);
      
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
      foreach (self::byType('gps_traker') as $eqLogic) {
        $eqLogic->periodic_state(0);
      }
    }
    
    // Lecture des statuts du vehicule connecté
    public function periodic_state($rfh) {
      $traker_type = $this->getConfiguration("type_traker");
      $minute = intval(date("i"));
      $heure  = intval(date("G"));
      $traker_name = $this->getHumanName();

      // Pour les traceur TKSTAR, verification du Login API
      if ($traker_type == "TKS") {
        $imei_id     = $this->getConfiguration("tkstar_imei");
        $tk_account  = $this->getConfiguration("tkstar_account");
        $tk_password = $this->getConfiguration("tkstar_password");
        if (($imei_id == "") || ($tk_account == "") || ($tk_password == "")) {
          return;
        }
        $data_dir = "tks_".$imei_id;
      }
      // Pour les traceur JeedomConnect, verification de la commande "get GPS position"
      else if ($traker_type == "JCN") {
        $jd_getposition_cmd  = $this->getConfiguration("cmd_jc_position");
        if ($jd_getposition_cmd == "") {
          return;
        }
        $jd_getposition_cmdf = str_replace ('#', '', $jd_getposition_cmd);
        $data_dir = "jcn_".$jd_getposition_cmdf;
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
        // log::add('gps_traker','debug', $traker_name."->record_period:".$record_period);

       
        // Toutes les mn => Mise à jour des informations du traceur
        // ========================================================
        // Recuperation des donnees du traceur GPS, selon son type
        if ($traker_type == "TKS") {
          $last_login_token = $cmd_record_period->getConfiguration('save_auth');
          if ((!isset($last_login_token)) || ($last_login_token == "") || ($rfh==1))
            $last_login_token = NULL;
          $session_gps_traker = new api_tkstar();
          $session_gps_traker->login($tk_account, $tk_password, $last_login_token);
          if ($last_login_token == NULL) {
            $login_token = $session_gps_traker->tkstar_api_login();   // Authentification
            if ($login_token["status"] != "OK") {
              log::add('gps_traker','error', $traker_name."->Erreur Login API Traceur GPS (Pas de session en cours)");
              return;  // Erreur de login API Traceur GPS
            }
            $cmd_record_period->setConfiguration ('save_auth', $login_token);
            $cmd_record_period->save();
            log::add('gps_traker','info', $traker_name."->Pas de session en cours => New login");
          }
          else if ($session_gps_traker->state_login() == 0) {
            $login_token = $session_gps_traker->tkstar_api_login();   // Authentification
            if ($login_token["status"] != "OK") {
              log::add('gps_traker','error', $traker_name."->Erreur Login API Traceur GPS (Session expirée)");
              return;  // Erreur de login API Traceur GPS
            }
            $cmd_record_period->setConfiguration ('save_auth', $login_token);
            $cmd_record_period->save();
            log::add('gps_traker','info', $traker_name."->Session expirée => New login");
          }
          // Capture des donnes courantes issues du GPS
          $ret = $session_gps_traker->tkstar_api_getdata();
          if ($ret["status"] == "KO") {
            log::add('gps_traker','error', $traker_name."->Erreur Login API Traceur GPS (Access data)");
            return;  // Erreur de login API Traceur GPS
            }
          // extraction des donnees utiles
          $lat = floatval($ret["result"]->lat);
          $lon = floatval($ret["result"]->lng);
          $alt = 0;
          $vitesse = round(floatval($ret["result"]->speed), 1);
          $kinetic_moving = ($ret["result"]->isStop == "1") ? 0 : 1;
          $batt_level = $ret["result"]->battery;
        }
        else if ($traker_type == "JCN") {
          // execution commande position pour l'objet suivi (Jeedom Connect)
          $jd_getposition_cmdname = str_replace('#', '', $this->getConfiguration('cmd_jc_position'));
          $jd_getposition_cmd  = cmd::byId($jd_getposition_cmdname);
          if (!is_object($jd_getposition_cmd)) {
            throw new Exception(__('Impossible de trouver la commande gps position', __FILE__));
          }
          $gps_position = $jd_getposition_cmd->execCmd();
          # log::add('gps_traker','debug', $traker_name."->gps_position: ".$gps_position);
          $gps_array = explode(",", $gps_position);
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
        }
        // Traitement des informations retournees
        // log::add('gps_traker','debug', $traker_name."->MAJ des données du traceur GPS: ".$data_dir);
        $cmd_mlg = $this->getCmd(null, "kilometrage");
        $previous_mileage = $cmd_mlg->execCmd();
        $previous_ts = $cmd_mlg->getConfiguration('prev_ctime');
        $cmd = $this->getCmd(null, "gps_vitesse");
        $cmd->event($vitesse);
        $cmd = $this->getCmd(null, "battery_traker");
        $cmd->event($batt_level);
        $cmd = $this->getCmd(null, "kinetic_moving");
        $previous_kinetic_moving = $cmd->execCmd();
        $cmd->event($kinetic_moving);

        // Etat courant du trajet
        $cmd_gps = $this->getCmd(null, "gps_position");
        $trip_start_ts       = $cmd_gps->getConfiguration('trip_start_ts');
        $trip_start_mileage  = $cmd_gps->getConfiguration('trip_start_mileage');
        $trip_in_progress    = $cmd_gps->getConfiguration('trip_in_progress');
        if (($lat == 0) && ($lon == 0))
          $gps_pts_ok = false; // points GPS non valide
        else
          $gps_pts_ok = true;

        if ($gps_pts_ok == true) {
          $gps_position = $lat.",".$lon.",".$alt;
          $previous_gps_position = $cmd_gps->execCmd();
          // log::add('gps_traker','debug',"GPS position =>".$gps_position);
          // log::add('gps_traker','debug',"Refresh log previous_gps_position=".$previous_gps_position);
          $eq_id = $this->getId();
          $cmd_gps->event($gps_position.",".$eq_id);   // ajout de l'ID plugin pour transmission au process AJAX sur serveur
          // $cmd_gps->event($gps_position);
          $cmd_gpslat = $this->getCmd(null, "gps_position_lat");
          $cmd_gpslat->event($lat);
          $cmd_gpslon = $this->getCmd(null, "gps_position_lon");
          $cmd_gpslon->event($lon);

          // Calcul distance maison
          $lat_home = deg2rad(floatval(config::byKey("info::latitude")));
          $lon_home = deg2rad(floatval(config::byKey("info::longitude")));
          $lat_veh = deg2rad($lat);
          $lon_veh = deg2rad($lon);
          $dist_home = 6371.01 * acos(sin($lat_home)*sin($lat_veh) + cos($lat_home)* cos($lat_veh)*cos($lon_home - $lon_veh)); // calcul de la distance
          $dist_home = round($dist_home, 3);
          $cmd_dis_home = $this->getCmd(null, "gps_dist_home");
          $cmd_dis_home->event($dist_home);
          
          // Calcul distance point precedent
          $previous_gps_latlon = explode(",", $previous_gps_position);
          $lat_prev = deg2rad(floatval($previous_gps_latlon[0]));
          $lon_prev = deg2rad(floatval($previous_gps_latlon[1]));
          $dist_prev = 6371.01 * acos(sin($lat_prev)*sin($lat_veh) + cos($lat_prev)* cos($lat_veh)*cos($lon_prev - $lon_veh)); // calcul de la distance
          // log::add('gps_traker','debug',"Refresh log dist_prev=".$dist_prev);
          if (is_nan($dist_prev)) {
            log::add('gps_traker','error', $traker_name."->Erreur sur le calcul de distance:".$dist_prev);
            $dist_prev = 0.0;
          }
          $mileage = round($previous_mileage + $dist_prev, 1);
          $cmd_mlg->event($mileage);
        }

        // Analyse debut et fin de trajet
        $ctime = time();
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
            $trip_end_mileage  = $mileage;
            $trip_in_progress  = 0;
            $trip_event = 1;
            // enregistrement d'un trajet
            $trip_distance = round($trip_end_mileage - $trip_start_mileage, 1);
            $trip_log_dt = $trip_start_ts.",".$trip_end_ts.",".$trip_distance."\n";
            log::add('gps_traker','info', $traker_name."->Refresh->recording Trip_dt=".$trip_log_dt);
            file_put_contents($fn_car_trips, $trip_log_dt, FILE_APPEND | LOCK_EX);
            $cmd_gps->setConfiguration('trip_in_progress', $trip_in_progress);
            $cmd_gps->save();
          }
        }
        // Log position courante vers GPS log file (pas si vehicule à l'arrêt "à la maison" et pas si "trajets alternatifs")
        if (($gps_pts_ok == true) && (($kinetic_moving > 0) || ($previous_kinetic_moving > 0))) {
          $gps_log_dt = $ctime.",".$gps_position.",".$vitesse.",".$mileage.",".$kinetic_moving."\n";
          log::add('gps_traker','debug', $traker_name."->Refresh->recording Gps_dt=".$gps_log_dt);
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
class gps_trakerCmd extends cmd 
{
    /*     * *************************Attributs****************************** */
    public function execute($_options = null) {
        //log::add('gps_traker','info',"execute:".$_options['message']);
        if ($this->getLogicalId() == 'refresh') {
          foreach (eqLogic::byType('gps_traker') as $eqLogic) {
            $eqLogic->periodic_state(1);
          }
        }
        

        
    }


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */
    

    /*     * **********************Getteur Setteur*************************** */
}

?>

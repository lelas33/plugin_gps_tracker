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

require_once dirname(__FILE__) . '/../../3rdparty/api_tkstar.php';

define("GPS_FILES_DIR", "/../../data/");

global $cars_dt;
global $cars_dt_gps;
global $report;

// =====================================================
// Fonction de lecture de tous les trajets d'une voiture
// =====================================================
function get_car_trips_gps($imei, $ts_start, $ts_end)
{
  global $cars_dt;
  
  // Lecture des trajets
  // -------------------
  // ouverture du fichier de log: trajets
  $fn_car = dirname(__FILE__).GPS_FILES_DIR.$imei.'/trips.log';
  $fcar = fopen($fn_car, "r");

  // lecture des donnees
  $line = 0;
  $cars_dt["trips"] = [];
  $first_ts = time();
  $last_ts  = 0;
  if ($fcar) {
    while (($buffer = fgets($fcar, 4096)) !== false) {
      // extrait les timestamps debut et fin du trajet
      list($tr_tss, $tr_tse, $tr_ds) = explode(",", $buffer);
      $tsi_s = intval($tr_tss);
      $tsi_e = intval($tr_tse);
      // selectionne les trajets selon leur date depart&arrive
      if (($tsi_s>=$ts_start) && ($tsi_s<=$ts_end)) {
        $cars_dt["trips"][$line] = $buffer;
        $line = $line + 1;
        // Recherche des ts mini et maxi pour les trajets retenus
        if ($tsi_s<$first_ts)
          $first_ts = $tsi_s;
        if ($tsi_e>$last_ts)
          $last_ts = $tsi_e;
      }
    }
  }
  fclose($fcar);
  log::add('gps_traker', 'debug', 'Ajax:get_car_trips:nb_lines trips =>'.$line);


  // Lecture des points GPS pour ces trajets
  // ---------------------------------------
  // ouverture du fichier de log: points GPS
  $fn_car = dirname(__FILE__).GPS_FILES_DIR.$imei.'/gps.log';
  $fcar = fopen($fn_car, "r");

  // lecture des donnees
  $line = 0;
  $line_all = 0;
  $cars_dt["gps"] = [];
  if ($fcar) {
    while (($buffer = fgets($fcar, 4096)) !== false) {
      // extrait les timestamps debut et fin du trajet
      $tmp=explode(",", $buffer);
      if (count($tmp) == 6) {
        list($pts_ts, $pts_lat, $pts_lon, $pts_speed, $pts_mlg, $pts_moving) = $tmp;
        $pts_tsi = intval($pts_ts);
        // selectionne les trajets selon leur date depart&arrive
        if (($pts_tsi>=$first_ts) && ($pts_tsi<=$last_ts)) {
          $cars_dt["gps"][$line] = $buffer;
          $line = $line + 1;
        }
      }
      else {
        log::add('gps_traker', 'error', 'Ajax:get_car_trips: Erreur dans le fichier gps.log, à la ligne:'.$line_all);
      }
      $line_all = $line_all + 1;
    }
  }
  fclose($fcar);
  // Ajoute les coordonnées du domicile pour utilisation par javascript
  $latitute=config::byKey("info::latitude");
  $longitude=config::byKey("info::longitude");
  $cars_dt["home"] = $latitute.",".$longitude;
  log::add('gps_traker', 'debug', 'Ajax:get_car_trips:nb_lines points=>'.$line);
  return;
}


// ========================================================
// Fonction de capture de la position copurante du vehicule
// ========================================================
function get_current_position($vin)
{
  $eq = eqLogic::byLogicalId($vin, "gps_traker");
  if ($eq->getIsEnable()) {
    $cmd_record_period = $eq->getCmd(null, "record_period");
    $record_period = $cmd_record_period->execCmd();
  }
  else {
    return;
  }
  $current_position = [];
  $current_position["status"] = "OK";

  // Login to API
  $last_login_token = $cmd_record_period->getConfiguration('save_auth');
  if ((!isset($last_login_token)) || ($last_login_token == ""))
    $last_login_token = NULL;
  $session_gps_traker = new api_tkstar();
  $session_gps_traker->login(config::byKey('account', 'gps_traker'), config::byKey('password', 'gps_traker'), $last_login_token);
  if ($last_login_token == NULL) {
    $login_token = $session_gps_traker->tkstar_api_login();   // Authentification
    if ($login_token["status"] != "OK") {
      log::add('gps_traker','error',"Erreur Login API PSA");
      return;  // Erreur de login API PSA
    }
    $cmd_record_period->setConfiguration ('save_auth', $login_token);
    $cmd_record_period->save();
    log::add('gps_traker','debug',"Pas de session en cours => New login");
  }
  else if ($session_gps_traker->state_login() == 0) {
    $login_token = $session_gps_traker->tkstar_api_login();   // Authentification
    if ($login_token["status"] != "OK") {
      log::add('gps_traker','error',"Erreur Login API PSA");
      return;  // Erreur de login API PSA
    }
    $cmd_record_period->setConfiguration ('save_auth', $login_token);
    $cmd_record_period->save();
    log::add('gps_traker','debug',"Session expirée => New login");
  }
  // Capture des donnes courantes issues du GPS
  $ret_sts = $session_gps_traker->tkstar_api_getdata();
  $current_position["veh"]= (floatval($ret_sts["result"]->lat)).",".(floatval($ret_sts["result"]->lng));

  // Ajoute les coordonnées du domicile pour utilisation par javascript
  $latitute=config::byKey("info::latitude");
  $longitude=config::byKey("info::longitude");
  $current_position["home"] = $latitute.",".$longitude;
  // Statut
  if ((floatval($ret_sts["result"]->lat) == 0) && (floatval($ret_sts["result"]->lng) == 0))
    $current_position["status"] = "KO";
  return ($current_position);
}
// ===========================================================
// Fourniture des statistiques sur l'ensemble des trajets
// ===========================================================
function get_car_trips_stats($vin)
{
  // config de la taille batterie
  // ----------------------------
  $eq = eqLogic::byLogicalId($vin, "gps_traker");
  if ($eq->getIsEnable()) {
    $cfg_batt_capacity = floatval($eq->getConfiguration("batt_capacity"));
    $cfg_cots_kwh = floatval($eq->getConfiguration("cost_kwh"));
  }
  else {
    return;
  }

  // Lecture des trajets
  // -------------------
  // ouverture du fichier de log: trajets
  $fn_car = dirname(__FILE__).GPS_FILES_DIR.$vin.'/trips.log';
  $fcar = fopen($fn_car, "r");

  // lecture de l'ensemble des trajets
  $line = 0;
  $trips = [];
  if ($fcar) {
    while (($buffer = fgets($fcar, 4096)) !== false) {
      // extrait les timestamps debut et fin du trajet
      list($tr_tss, $tr_tse, $tr_ds, $tr_batt) = explode(",", $buffer);
      $tsi_s = intval($tr_tss);
      $tsi_e = intval($tr_tse);
      $trips[$line]["tss"]   = intval($tr_tss);
      $trips[$line]["tse"]   = intval($tr_tse);
      $trips[$line]["dist"]  = floatval($tr_ds);
      $trips[$line]["conso"] = (floatval($tr_batt) * $cfg_batt_capacity)/100.0;  // conso en kWh
      $line = $line + 1;
    }
  }
  fclose($fcar);
  $nb_trips = $line;
  
  // calcul des statistiques par mois
  // --------------------------------
  $trip_stat["dist"]  = [[]];
  $trip_stat["conso"] = [[]];
  $trip_stat["nb_trips"] = $nb_trips;
  $trip_stat["cfg_cost_kwh"] = $cfg_cots_kwh;
  for ($tr=0; $tr<$nb_trips; $tr++) {
    $tss = $trips[$tr]["tss"];
    $year  = intval(date('Y', $tss));  // Year => ex 2020
    $month = intval(date('n', $tss));  // Month => 1-12
    if (isset($trip_stat["dist"][$year][$month])){
      $trip_stat["dist"][$year][$month] += $trips[$tr]["dist"];
    }
    else {
      $trip_stat["dist"][$year][$month] = $trips[$tr]["dist"];
    }
    if (isset($trip_stat["conso"][$year][$month])){
      $trip_stat["conso"][$year][$month] += $trips[$tr]["conso"];
    }
    else {
      $trip_stat["conso"][$year][$month] = $trips[$tr]["conso"];
    }
  }
  return($trip_stat);
}


// =============================================
// Fonction de definition du kilometrage courant
// =============================================
function set_mileage($imei, $new_mileage)
{
  $ret = [];
  $eq = eqLogic::byLogicalId($imei, "gps_traker");
  if ($eq->getIsEnable()) {
    $cmd_mlg = $eq->getCmd(null, "kilometrage");
    $cmd_mlg->event($new_mileage);
    $ret["status"] = "OK";
  }
  else {
    $ret["status"] = "KO";
  }
  return ($ret);
}

// =====================================
// Gestion des commandes recues par AJAX
// =====================================
try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

	ajax::init();

  if (init('action') == 'getTripData') {
    log::add('gps_traker', 'info', 'Ajax:getTripData');
    $imei = init('eqLogic_id');
    $ts_start = init('param')[0];
    $ts_end   = init('param')[1];
    log::add('gps_traker', 'debug', 'imei   :'.$imei);
    // Param 0 et 1 sont les timestamp de debut et fin de la periode de log demandée
    get_car_trips_gps($imei, intval ($ts_start), intval ($ts_end));
    $ret_json = json_encode ($cars_dt);
    ajax::success($ret_json);
    }

  else if (init('action') == 'getTripStats') {
    log::add('gps_traker', 'info', 'Ajax:getTripStats');
    $imei = init('eqLogic_id');
    $trip_stat = get_car_trips_stats($imei);
    $ret_json = json_encode ($trip_stat);
    ajax::success($ret_json);
    }

  else if (init('action') == 'getCurrentPosition') {
    $imei = init('eqLogic_id');
    log::add('gps_traker', 'info', 'Ajax:getCurrentPosition');
    $current_position = get_current_position($imei);
    $ret_json = json_encode ($current_position);
    ajax::success($ret_json);
    }

  else if (init('action') == 'setMileage') {
    $imei = init('eqLogic_id');
    log::add('gps_traker', 'info', 'Ajax:setMileage');
    $new_mileage = init('param')[0];
    $ret = set_mileage($imei, $new_mileage);
    $ret_json = json_encode ($ret);
    ajax::success($ret_json);
    }

    throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayExeption($e), $e->getCode());
}
?>

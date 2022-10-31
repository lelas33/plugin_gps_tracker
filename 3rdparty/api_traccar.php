<?php

// Fonctions de connexion aux API du serveur Traccar
// =================================================
class api_traccar {

  // Constantes & Variables globales pour la classe
  protected $mile = 1.609344;  // 1 mile americain en kms
  protected $url_api_traccar;  // = 'http://192.168.1.9:8082/api/';
  protected $login_name;
  protected $login_pass;

  // ==============================
  // General function : login
  // ==============================
  function login($url, $login_name, $login_pass)
  {
    $this->url_api_traccar = $url."/api/";
    $this->login_name = $login_name;
    $this->login_pass = $login_pass;
  }

  // =====================================
  // Functions dedicated to API traccar
  // =====================================
  // GET HTTP command
  private function traccar_get_api($param, $fields = null)
  {
    $session = curl_init();
    $url = $this->url_api_traccar;
    curl_setopt($session, CURLOPT_URL, $url.$param);
    curl_setopt($session, CURLOPT_USERPWD, $this->login_name . ':' . $this->login_pass);  
    curl_setopt($session, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    if (isset($fields)) {
      curl_setopt($session, CURLOPT_POSTFIELDS, $fields);
    }
    $response = curl_exec($session);
    // Vérifie si une erreur survient
    if(curl_errno($session)) {
      $info = [];
      echo 'Erreur Curl : ' . curl_error($session);
    }
    else {
      $info = curl_getinfo($session);
    }
    curl_close($session);

    // $ret_array["info"] = $info;
    $ret_array["result"] = json_decode($response);
    return $ret_array;
  }

  // POST HTTP command
  private function traccar_post_api($param, $fields = null)
  {
    $session = curl_init();
    $url = $this->url_api_traccar;
    curl_setopt($session, CURLOPT_URL, $url.$param);
    curl_setopt($session, CURLOPT_USERPWD, $this->login_name . ':' . $this->login_pass);  
    curl_setopt($session, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($session, CURLOPT_POST, true);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    if (isset($fields)) {
      curl_setopt($session, CURLOPT_POSTFIELDS, $fields);
    }
    $response = curl_exec($session);
    // Vérifie si une erreur survient
    if(curl_errno($session)) {
      $info = [];
      echo 'Erreur Curl : ' . curl_error($session);
    }
    else {
      $info = curl_getinfo($session);
    }
    curl_close($session);

    // $ret_array["info"] = $info;
    $ret_array["result"] = $response;
    return $ret_array;
  }

  // Get the list of devices recorded in the traccar server database
  function traccar_get_devices()  
  {
   $param = "devices";
   $results = $this->traccar_get_api($param);
   $nb_devices = count($results["result"]);
   $res = [];
   $res["nb_dev"] = $nb_devices;
   for ($i = 0; $i<$nb_devices; $i++) {
     $res["dev"][$i]["id"]        = $results["result"][$i]->id;
     $res["dev"][$i]["name"]      = $results["result"][$i]->name;
     $res["dev"][$i]["unique_id"] = $results["result"][$i]->uniqueId;
   }
   return $res;
  }

  // Get some parameter about the traccar server
  function traccar_get_server()  
  {
   $param = "server";
   $results = $this->traccar_get_api($param);
   return $results;
  }

  // Get the list of users recorded in the traccar server database
  function traccar_get_users()  
  {
   $param = "users";
   $results = $this->traccar_get_api($param);
   return $results;
  }
  
  // Get the current position of a tracer identified by its ID
  function traccar_get_positions($tr_id)
  {
   $data='deviceId='.$tr_id;
   $param = "positions?".$data;
   $results = $this->traccar_get_api($param);
   $res = [];
   $nb_dev = count($results["result"]);
   if ($nb_dev != 1) {
     $res["status"] = "KO";
   }
   else {
     $res["status"] = "OK";
     $res["motion"] = $results["result"][0]->attributes->motion;
     $res["batt"] = $results["result"][0]->attributes->batteryLevel;
     $res["lat"] = $results["result"][0]->latitude;
     $res["lon"] = $results["result"][0]->longitude;
     $res["alt"] = $results["result"][0]->altitude;
     $res["spd"] = round(floatval($results["result"][0]->speed)*$this->mile, 1);
   }
   return $res;
  }

  // Get the positions recorded for a tracer identified by its ID, and between 2 dates
  function traccar_get_route($tr_id, $from, $to)
  {
   $data='deviceId='.$tr_id.'&from='.$from.'&to='.$to;
   $param = "reports/route?".$data;
   $results = $this->traccar_get_api($param);
   // formate les resultas: timestamp, lat, lon, alt, speed, mileage, moving
   $nb_pts = count($results["result"]);
   $res = [] ;
   if ($nb_pts > 0) {
     for ($i=0; $i<$nb_pts; $i++) {
       $pts_ts  = $results["result"][$i]->deviceTime;
       $pts_lat = $results["result"][$i]->latitude;
       $pts_lon = $results["result"][$i]->longitude;
       $pts_alt = $results["result"][$i]->altitude;
       $pts_spd = $results["result"][$i]->speed;
       $pts_mlg = $results["result"][$i]->attributes->totalDistance;
       $pts_mvg = $results["result"][$i]->attributes->motion;
       $pts_ts  = strtotime($pts_ts);
       $pts_alt = round(floatval($pts_alt),1);
       $pts_spd = round(floatval($pts_spd)*$this->mile,1);
       $pts_mlg = round(floatval($pts_mlg),1);
       $pts_mvg = intval($pts_mvg);
       $buff = $pts_ts.','.$pts_lat.','.$pts_lon.','.$pts_alt.','.$pts_spd.','.$pts_mlg.','.$pts_mvg;
       $res[$i] = $buff;
     }
   }
   return $res;
  }

  // Get the trips recorded for a tracer identified by its ID, and between 2 dates
  function traccar_get_trips($tr_id, $from, $to)
  {
   $data='deviceId='.$tr_id.'&from='.$from.'&to='.$to;
   $param = "reports/trips?".$data;
   $results = $this->traccar_get_api($param);
   // formate les resultas: ts_start, ts_end, distance
   $nb_trips = count($results["result"]);
   $res = [] ;
   if ($nb_trips > 0) {
     for ($i=0; $i<$nb_trips; $i++) {
       $ts_start = $results["result"][$i]->startTime;
       $ts_end   = $results["result"][$i]->endTime;
       $distance = $results["result"][$i]->distance;       
       $ts_start = strtotime($ts_start);
       $ts_end   = strtotime($ts_end);
       $distance = round(floatval($distance)/1000, 1);
       $buff=[];
       $buff["tss"] = $ts_start;
       $buff["tse"] = $ts_end;
       $buff["dst"] = $distance;
       $buff["type"] = 4;  // type trajet voiture
       $res[$i] = $buff;
     }
   }
   return $res;
  }

}

?>

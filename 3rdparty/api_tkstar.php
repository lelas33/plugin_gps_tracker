<?php

// Fonctions de connexion aux API PSA
// ==================================
class api_tkstar {

  // Constantes pour la classe
  protected $url_api_tkstar = 'https://www.mytkstar.net:8089/';

  protected $login_name;
  protected $login_pass;
  protected $access_token = [];

  protected $login_key = "7DU2DJFDR8321";

  // ==============================
  // General function : login
  // ==============================
  function login($login_name, $login_pass, $token)
  {
    $this->login_name = $login_name;
    $this->login_pass = $login_pass;
    $this->access_token = $token;  // Etat des token des appels précédents
  }

  // Check login state (Tokens still allowed)
  function state_login()
  {
    if (isset($this->access_token["access_token"]) && isset($this->access_token["access_token_ts"]) && isset($this->access_token["access_token_dur"])) {
      $ctime = time();
      //printf("login:ctime=".$ctime."\n");
      if (($ctime >= $this->access_token["access_token_ts"]) && ($ctime < ($this->access_token["access_token_ts"] + $this->access_token["access_token_dur"] - 15))) {
        return(1);  // no need for new login
      }
    }
    else
      return (0);
  }

  // =====================================
  // Functions dedicated to API psa_auth2
  // =====================================
  // GET HTTP command : unsused

  // POST HTTP command
  private function post_api_tkstar($param, $fields = null)
  {
    $session = curl_init();
    $url = $this->url_api_tkstar;
    curl_setopt($session, CURLOPT_URL, $url.$param);
    curl_setopt($session, CURLOPT_HTTPHEADER, array(
       'Content-Type: text/xml;charset=utf-8',
       // 'Accept-Encoding: gzip',
       'Connection: close'));
    curl_setopt($session, CURLOPT_POST, true);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    if (isset($fields)) {
      curl_setopt($session, CURLOPT_POSTFIELDS, $fields);
    }
    $json = curl_exec($session);
    // Vérifie si une erreur survient
    if(curl_errno($session)) {
      $info = [];
      echo 'Erreur Curl : ' . curl_error($session);
    }
    else {
      $info = curl_getinfo($session);
    }
    curl_close($session);

//    throw new Exception(__('La livebox ne repond pas a la demande de cookie.', __FILE__));
    $ret_array["info"] = $info;
    // $ret_array["result"] = json_decode($json);
    $ret_array["result"] = $json;
    return $ret_array;
  }



  // ===================================================
  // Connection aux API: TKSTAR
  // ===================================================
  function tkstar_api_login()
  {
    $param = "openapiv3.asmx";
    $form = '<v:Envelope xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns:d="http://www.w3.org/2001/XMLSchema" xmlns:c="http://schemas.xmlsoap.org/soap/encoding/" xmlns:v="http://schemas.xmlsoap.org/soap/envelope/">';
    $form = $form.'  <v:Header />';
    $form = $form.'  <v:Body>';
    $form = $form.'    <LoginByAndroid xmlns="http://tempuri.org/" id="o0" c:root="1">';
    $form = $form.'      <LoginAPP i:type="d:string">TKSTAR</LoginAPP>';
    $form = $form.'      <Pass i:type="d:string">'.$this->login_pass.'</Pass>';
    $form = $form.'      <LoginType i:type="d:string">1</LoginType>';
    $form = $form.'      <Key i:type="d:string">'.$this->login_key.'</Key>';
    $form = $form.'      <Name i:type="d:string">'.$this->login_name.'</Name>';
    $form = $form.'      <GMT i:type="d:string">0:00</GMT>';
    $form = $form.'    </LoginByAndroid>';
    $form = $form.'  </v:Body>';
    $form = $form.'</v:Envelope>';

    $ret = $this->post_api_tkstar($param, $form);
    // print("=> Result:\n");
    // var_dump($ret["result"]);
    // Analyse du resultat
    $tmp_xml = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><LoginByAndroidResponse xmlns="http://tempuri.org/"><LoginByAndroidResult>{"state":"0","deviceInfo":{"deviceID":"827797","deviceName":"TK905-68570","model":"150","showDW":"1","sendCommand":"0-0-0-0-0","timeZone":"0:00","warnStr":"","key2018":"nXPG0oQc7gzIPUcrqBtX3F6Qj2+Eg7acIPzmgpxbBQkH8+I9NXIjUxK9t+AUm/4w+Qom9XvfycopH0Ay6JEGAy7cByv9sMMDJpcfWWSH8Qo="}}</LoginByAndroidResult></LoginByAndroidResponse></soap:Body></soap:Envelope>';
    $xmlString = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $ret["result"]);
    $xml = simplexml_load_string($xmlString);
    
    // print_r($xml);
    $ret_array = [];
    if (isset ($xml->soapBody->LoginByAndroidResponse->LoginByAndroidResult)) {
      $login_result = $xml->soapBody->LoginByAndroidResponse->LoginByAndroidResult;
      $ret_array["status"] = "OK";
      $ret_array["result"] = json_decode($login_result);
      $this->access_token["access_token"] = $ret_array["result"]->deviceInfo->key2018;
      $this->access_token["device_id"]    = $ret_array["result"]->deviceInfo->deviceID;
      $this->access_token["access_token_ts"]  = time();  // token consented on
      $this->access_token["access_token_dur"] = 3600*12;  // 12h
      $this->access_token["status"] = "OK";
    }
    else
      $this->access_token["status"] = "KO";
    
    return($this->access_token);  // new login performed
  }


  // Retour statut GPS
  // =================
  function tkstar_api_status()
  {
    $param = "openapiv3.asmx";

    $form = '<v:Envelope xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns:d="http://www.w3.org/2001/XMLSchema" xmlns:c="http://schemas.xmlsoap.org/soap/encoding/" xmlns:v="http://schemas.xmlsoap.org/soap/envelope/">';
    $form = $form.'  <v:Header />';
    $form = $form.'  <v:Body>';
    $form = $form.'    <GetDeviceStatus xmlns="http://tempuri.org/" id="o0" c:root="1">';
    $form = $form.'      <TimeZones i:type="d:string">0:00</TimeZones>';
    $form = $form.'      <Language i:type="d:string">en</Language>';
    $form = $form.'      <DeviceID i:type="d:int">'.$this->access_token["device_id"].'</DeviceID>';
    $form = $form.'      <Key i:type="d:string">'.$this->access_token["access_token"].'</Key>';
    $form = $form.'      <FilterWarn i:type="d:string">0011</FilterWarn>';
    $form = $form.'    </GetDeviceStatus>';
    $form = $form.'  </v:Body>';
    $form = $form.'</v:Envelope>';

    $ret = $this->post_api_tkstar($param, $form);
    // print("=> Result:\n");
    // var_dump($ret["result"]);
    // Analyse du resultat
    $xmlString = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $ret["result"]);
    $xml = simplexml_load_string($xmlString);

    $ret_array = [];
    if (isset ($xml->soapBody->GetDeviceStatusResponse->GetDeviceStatusResult)) {
      $data = $xml->soapBody->GetDeviceStatusResponse->GetDeviceStatusResult;
      $ret_array["status"] = "OK";
      $ret_array["result"] = json_decode($data);
    }
    else
      $ret_array["status"] = "KO";

    return($ret_array);  // new login performed
  }

  // Retour des coordonnees GPS
  // ==========================
  function tkstar_api_getdata()
  {
    $param = "openapiv3.asmx";

    $form = '<v:Envelope xmlns:i="http://www.w3.org/2001/XMLSchema-instance" xmlns:d="http://www.w3.org/2001/XMLSchema" xmlns:c="http://schemas.xmlsoap.org/soap/encoding/" xmlns:v="http://schemas.xmlsoap.org/soap/envelope/">';
    $form = $form.'  <v:Header />';
    $form = $form.'  <v:Body>';
    $form = $form.'    <GetTracking xmlns="http://tempuri.org/" id="o0" c:root="1">';
    $form = $form.'      <TimeZones i:type="d:string">0:00</TimeZones>';
    $form = $form.'      <Language i:type="d:string">en</Language>';
    $form = $form.'      <DeviceID i:type="d:int">'.$this->access_token["device_id"].'</DeviceID>';
    $form = $form.'      <Model i:type="d:int">0</Model>';
    $form = $form.'      <MapType i:type="d:string">Baidu</MapType>';
    $form = $form.'      <Key i:type="d:string">'.$this->access_token["access_token"].'</Key>';
    $form = $form.'    </GetTracking>';
    $form = $form.'  </v:Body>';
    $form = $form.'</v:Envelope>';
    // log::add('gps_traker','debug',"tkstar_api_getdata:form:".$form);

    $ret = $this->post_api_tkstar($param, $form);
    // print("=> Result:\n");
    // var_dump($ret["result"]);
    // Analyse du resultat
    $xmlString = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $ret["result"]);
    $xml = simplexml_load_string($xmlString);

    $ret_array = [];
    if (isset ($xml->soapBody->GetTrackingResponse->GetTrackingResult)) {
      $data = $xml->soapBody->GetTrackingResponse->GetTrackingResult;
      $ret_array["status"] = "OK";
      $ret_array["result"] = json_decode($data);
      // log::add('gps_traker','debug',"tkstar_api_getdata:json:".$data);
      // For trace analysis
      // $fn_log_sts = "/var/www/html/plugins/gps_traker/data/traker_log.txt";
      // $date = date("Y-m-d H:i:s");
      // $log_dt = $date." => ".$data."\n";
      // file_put_contents($fn_log_sts, $log_dt, FILE_APPEND | LOCK_EX);
      // end trace analysis
    }
    else
      $ret_array["status"] = "KO";

    return($ret_array);  // new login performed
  }




}


?>






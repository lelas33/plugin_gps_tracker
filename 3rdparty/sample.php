<?php
require_once("api_tkstar.php");

$login_name = "xx";
$login_pass = "yy";


$session_tkstar = new api_tkstar();

$session_tkstar->login($login_name, $login_pass, NULL);

print("Login API TKSTAR:\n");
print("=================\n");
$login = $session_tkstar->tkstar_api_login();
var_dump($login);
print("\n");


print("Get DATA:\n");
print("=========\n");
$ret = $session_tkstar->tkstar_api_getdata();
var_dump($ret);
print("\n");



?>

<?php

//shows how the user.json file was created

require_once('PasswordHash.php');
$hasher = new PasswordHash(8, FALSE);

$response = array();
$response[] = array("login" => "yosko","password" => $hasher->HashPassword("yosko"));
$response[] = array("login" => "idleman","password" => $hasher->HashPassword("idleman"));

$fp = fopen('user.json', 'w');
fwrite($fp, json_encode($response));
fclose($fp);

?>
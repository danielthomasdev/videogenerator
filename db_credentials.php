<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = '127.0.0.1';
$port = '8889'; //remove on windows
$db = 'videos';
$user = 'root';
$pass = '';
$whitelist = array('127.0.0.1', "::1");

/*
if(!in_array($_SERVER['REMOTE_ADDR'], $whitelist)){
    $pass = 'root'; //this needs to change on windows desktop
}
*/
$charset = 'utf8';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
try {
     $pdo = new PDO($dsn, $user, $pass);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
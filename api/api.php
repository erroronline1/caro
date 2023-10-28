<?php
session_start();

ini_set('display_errors', 1); error_reporting(E_ERROR);
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=UTF-8');
//database connection
include_once('dbcredentials.php'); // contains just $pdo = new PDO('mysql:host=127.0.0.1;dbname=webqs;charset=utf8', 'username', 'password');
$pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

$ini=parse_ini_file("setup.ini", true);
//print_r($ini);

// always security logout
if ($_SESSION["user"]){
/*	if (!validUser()['id']) {
		session_unset();
		session_destroy();
	}
*/
}

function validUser($pdo){
	return;
}

function dbSanitize($text){
	return addslashes(trim(str_replace(';', ',', $text)));
}
function scriptFilter($text){
	return htmlspecialchars(trim($text));
}

// read incoming stream
$payload = json_decode(file_get_contents('php://input'));

if ($_SERVER['REQUEST_METHOD'] == 'GET'){
	$payload = (object) $_GET;
}
if ($_SERVER['REQUEST_METHOD']== 'POST' && $_POST){
	// in case of form_data used for file-uploads. otherwise request-parameter will not be processed
	// this does NOT work with put!!!!
	$payload = (object) $_POST;
}

//var_dump($payload);
if (preg_match('/user_/', $payload->request)) require_once('users.php');

//require_once('requests.php');

exit();
?>
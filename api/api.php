<?php
session_start();

ini_set('display_errors', 1); error_reporting(E_ERROR);
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=UTF-8');
define ('INI', parse_ini_file('setup.ini', true));
//database connection
include_once('sqlinterface.php');
include_once('language.php');
include_once('functions.php'); // general unities

//print_r($ini);

// always security logout
if ($_SESSION["user"]){
/*	if (!validUser()['id']) {
		session_unset();
		session_destroy();
	}
*/
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
if (preg_match('/form_/', $payload->request)) require_once('forms.php');
if ($payload->request === 'lang_getall') echo LANG::GETALL();

exit();
?>
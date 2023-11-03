<?php
session_start();

ini_set('display_errors', 1); error_reporting(E_ERROR);
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=UTF-8');
define ('INI', parse_ini_file('setup.ini', true));
define ('REQUEST', explode("/", substr(@$_SERVER['PATH_INFO'], 1)));
include_once('sqlinterface.php');
include_once('language.php');
include_once('utility.php'); // general unities


class API {

	public $_payload = [];
	public $_pdo;
	
	private $_httpResponse = 200;
	
	public function __construct(){
		//$payload = new PAYLOAD;
		$this->_payload = UTILITY::parsePayload();//(object) $payload->_payload;
		
		$this->_pdo = new PDO( INI['sql']['driver'] . ':' . INI['sql']['host'] . ';dbname=webqs;charset=utf8mb4', INI['sql']['user'], INI['sql']['password']);
		$this->_pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));"); // intuitive group by
		$this->_pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true); // reuse tokens in prepared statements
	}
		
	public function response($data, $status = 200){
		if(is_array($data)) {
			$data = json_encode($data);
			$this->_httpResponse = $status;
		}
		else {
			$data = '';
			$this->_httpResponse = 500;
		}
		$this->set_headers();
		echo $data;
		exit;
	}
	
	private function get_status_message(){
		$status = array(
					100 => 'Continue',  
					101 => 'Switching Protocols',  
					200 => 'OK',
					201 => 'Created',  
					202 => 'Accepted',  
					203 => 'Non-Authoritative Information',  
					204 => 'No Content',  
					205 => 'Reset Content',  
					206 => 'Partial Content',  
					300 => 'Multiple Choices',  
					301 => 'Moved Permanently',  
					302 => 'Found',  
					303 => 'See Other',  
					304 => 'Not Modified',  
					305 => 'Use Proxy',  
					306 => '(Unused)',  
					307 => 'Temporary Redirect',  
					400 => 'Bad Request',  
					401 => 'Unauthorized',  
					402 => 'Payment Required',  
					403 => 'Forbidden',  
					404 => 'Not Found',  
					405 => 'Method Not Allowed',  
					406 => 'Not Acceptable',  
					407 => 'Proxy Authentication Required',  
					408 => 'Request Timeout',  
					409 => 'Conflict',  
					410 => 'Gone',  
					411 => 'Length Required',  
					412 => 'Precondition Failed',  
					413 => 'Request Entity Too Large',  
					414 => 'Request-URI Too Long',  
					415 => 'Unsupported Media Type',  
					416 => 'Requested Range Not Satisfiable',  
					417 => 'Expectation Failed',  
					500 => 'Internal Server Error',  
					501 => 'Not Implemented',  
					502 => 'Bad Gateway',  
					503 => 'Service Unavailable',  
					504 => 'Gateway Timeout',  
					505 => 'HTTP Version Not Supported');
		return ($status[$this->_httpResponse])?$status[$this->_httpResponse]:$status[500];
	}

	private function set_headers(){
		header("HTTP/1.1 ".$this->_httpResponse." ".$this->get_status_message());
		header("Content-Type:application/json");
	}

	public function processApi(){
		$func = strtolower($this->_requestedMethod);
		if(method_exists($this, $func))
			$this->$func();
		else
			$this->response([], 404); // If the method not exist with in this class, response would be "Page not found".
	}
}

if (in_array(REQUEST[0], ['application', 'form', 'user'])) require_once(REQUEST[0] . '.php');

exit();
?>
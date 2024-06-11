<?php
session_start();

ini_set('display_errors', 1); error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=UTF-8');
define ('INI', parse_ini_file('setup.ini', true));
define ('REQUEST', explode("/", substr(@utf8_encode($_SERVER['PATH_INFO']), 1)));
include_once('sqlinterface.php');
include_once('language.php');
include_once('utility.php'); // general unities


class API {
	/**
	 * preset all passed parameters
	 */
	public $_payload = [];

	/**
	 * preset database connection
	 */
	public $_pdo;
	
	/**
	 * preset standard response code
	 */
	private $_httpResponse = 200;
	
	/**
	 * constructor prepares payload and database connection
	 * no parameters, no response
	 */
	public function __construct(){
		$this->_payload = UTILITY::parsePayload();
		
		$options = [
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // always fetch assoc
			\PDO::ATTR_EMULATE_PREPARES   => true, // reuse tokens in prepared statements
		];
		$this->_pdo = new PDO( INI['sql'][INI['sql']['use']]['driver'] . ':' . INI['sql'][INI['sql']['use']]['host'] . ';' . INI['sql'][INI['sql']['use']]['database']. ';' . INI['sql'][INI['sql']['use']]['charset'], INI['sql'][INI['sql']['use']]['user'], INI['sql'][INI['sql']['use']]['password'], $options);
		$dbsetup = SQLQUERY::PREPARE('DYNAMICDBSETUP');
		if ($dbsetup) $this->_pdo->exec($dbsetup);

		// update user setting for each request
		if (array_key_exists('user', $_SESSION)){
			$query = SQLQUERY::EXECUTE($this->_pdo, 'application_login', [
				'values' => [
					':token' => $_SESSION['user']['token']
				]
			]);
			if ($query){
				$result = $query[0];
				$_SESSION['user'] = $result;
				$_SESSION['user']['permissions'] = explode(',', $result['permissions']);
				$_SESSION['user']['units'] = explode(',', $result['units']);
				$_SESSION['user']['app_settings'] = $result['app_settings'] ? json_decode($result['app_settings'], true) : [];
				$_SESSION['user']['image'] = './' . $result['image'];
			}
			else {
				session_unset();
				session_destroy();
			}
		}
	}
	
	/**
	 * api response
	 * @param array|string $data what should be responded
	 * @param int $status optional override for error cases
	 * no return, end of api processing
	 */
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
	
	/**
	 * @return str readable http status message based on $this->_httpResponse
	 */
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

	/**
	 * sets document headers in advance of output stream
	 * no return
	 */
	private function set_headers(){
		header("HTTP/1.1 ".$this->_httpResponse." ".$this->get_status_message());
		header("Content-Type:application/json; charset=utf-8");
	}

	/**
	 * executes the called api method
	 * no return
	 */
	public function processApi(){
		$func = strtolower($this->_requestedMethod);
		if(method_exists($this, $func))
			$this->$func();
		else
			$this->response([], 404); // If the method not exist with in this class, response would be "Page not found".
	}

	/**
	 * posts a system message to a user group
	 * @param array $group 'permission'=>[] ||&& 'unit'=>[] reach out for permission holders or unit member or permission holders within units
	 * @param string $message actual message content
	 * no return
	 * 
	 * if permission and unit are both set only permission holders within units get the message! 
	 */
	public function alertUserGroup($group = [], $message = ''){
		$permission = $unit = $recipients = [];
		if (array_key_exists('permission', $group)){
			foreach($group['permission'] as $prmssn){
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('application_get_permission_group'));
				$statement->execute([
					':group' => $prmssn
				]);
				array_push($permission, ...array_column($statement->fetchAll(PDO::FETCH_ASSOC), 'id'));
			}
		}
		if (array_key_exists('unit', $group)){
			foreach($group['unit'] as $unt){
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('application_get_unit_group'));
				$statement->execute([
					':group' => $unt
				]);
				array_push($unit, ...array_column($statement->fetchAll(PDO::FETCH_ASSOC), 'id'));
			}
		}
		if ($permission) $recipients = $permission;
		if ($unit) $recipients = $unit;
		if ($permission && $unit) $recipients = array_intersect($permission, $unit);
		$recipients = array_unique($recipients);
		foreach($recipients as $rcpnt_id) {
			$postmessage = [
				'to_user' => $rcpnt_id,
				'message' => $message
			];
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_post_system_message'));
			$statement->execute($postmessage);
		}
	}

	/**
	 * returns a default content for lack of database entries
	 * @param string $type type of requested but mission content
	 * @return array assemble object
	 */
	public function noContentAvailable($type){
		return [[
			['type' => 'nocontent',
			'content' => LANG::GET('general.no_content_available', [':content' => $type])]
		]];
	}
}

if (in_array(REQUEST[0], ['application', 'audit', 'calendar', 'consumables', 'csvfilter', 'file', 'form', 'message', 'notification', 'order', 'record', 'texttemplate', 'tool', 'user'])) require_once(REQUEST[0] . '.php');

$call = strtoupper(REQUEST[0]);
$api = new $call();
$api->processApi();

exit();
?>
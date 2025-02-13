<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */
session_set_cookie_params([
	'domain' => $_SERVER['HTTP_HOST'],
	'secure' => true,
	'httponly' => true,
]);
session_start();

ini_set('display_errors', 1); error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=UTF-8');
require_once('_config.php');
define ('REQUEST', explode("/", substr(mb_convert_encoding($_SERVER['PATH_INFO'], 'UTF-8', mb_detect_encoding($_SERVER['PATH_INFO'], ['ASCII', 'UTF-8', 'ISO-8859-1'])), 1)));
require_once('_sqlinterface.php');
require_once('_language.php');
require_once('_utility.php'); // general unities


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
	 * current date with correct timezone
	 */
	public $_currentdate;
	
	/**
	 * message array for accumulated user messages
	 * to be populated by message as key and user ids as value array
	 * see alertUserGroup() and alertUserGroupSubmit()
	 */
	private $_messages = [];

	/**
	 * make languagemodel LANG class and its methods available
	 */
	public $_lang = [];

	/**
	 * constructor prepares payload and database connection
	 * no parameters, no response
	 */
	public function __construct(){
		$this->_payload = UTILITY::parsePayload();
		
		$options = [
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // always fetch assoc
			\PDO::ATTR_EMULATE_PREPARES   => true, // reuse tokens in prepared statements
			//\PDO::ATTR_PERSISTENT => true // persistent connection for performance reasons, unsupported as of 2/25 on sqlsrv?
		];
		$this->_pdo = new PDO( CONFIG['sql'][CONFIG['sql']['use']]['driver'] . ':' . CONFIG['sql'][CONFIG['sql']['use']]['host'] . ';' . CONFIG['sql'][CONFIG['sql']['use']]['database']. ';' . CONFIG['sql'][CONFIG['sql']['use']]['charset'], CONFIG['sql'][CONFIG['sql']['use']]['user'], CONFIG['sql'][CONFIG['sql']['use']]['password'], $options);
		$dbsetup = SQLQUERY::PREPARE('DYNAMICDBSETUP');
		if ($dbsetup) $this->_pdo->exec($dbsetup);

		$this->_currentdate = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));

		$this->_lang = new LANG();

		if (isset($_SESSION['lastrequest']) && (time() - $_SESSION['lastrequest'] > CONFIG['lifespan']['idle'])){
			$params = session_get_cookie_params();
			setcookie(session_name(), '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
			session_destroy();
			session_write_close();
			session_unset();
		}
		// check if a registered user with valid token is logged in
		if (isset($_SESSION['user']['token'])){
			$user = SQLQUERY::EXECUTE($this->_pdo, 'application_login', [
				'values' => [
					':token' => $_SESSION['user']['token']
				]
			]);
			if ($user){
				// valid user IS logged in
				// renew session timeout except for defined requests
				if (!in_array(REQUEST[0], ['notification'])) $_SESSION['lastrequest'] = time();

				//update user setting for each request
				$result = $user[0];
				$_SESSION['user'] = $result;
				$_SESSION['user']['permissions'] = explode(',', $result['permissions'] ? : '');
				$_SESSION['user']['units'] = explode(',', $result['units'] ? : '');
				$_SESSION['user']['app_settings'] = json_decode($result['app_settings'] ? : '', true);
				$_SESSION['user']['image'] = './' . $result['image'];

				// override user with submitted user, especially for delayed cached requests by service worker (offline fallback)
				if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])
					&& isset(REQUEST[1]) && REQUEST[1] !== 'login'
				){
					// post and put MUST have _user_post_validation payload
					if (($_user_post_validation = UTILITY::propertySet($this->_payload, '_user_post_validation')) !== false) {
						unset ($this->_payload->_user_post_validation);
						// sanitize arrays from payload as checksum can't handle these from client side
						$payload = json_decode(json_encode($this->_payload), true);
						foreach ($payload as $key => $value){
							if ($value && gettype($value) === 'array') unset($payload[$key]);
						}
						//var_dump(json_encode($payload));
						$payload = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
							return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
							}, json_encode($payload) );
						$payload = preg_replace(['/\\\\r|\\\\n|\\\\t/', '/[\W_]/', '/0D0A/i'], '', $payload);  // harmonized cross browser, 0d0a is carriage return that is somehow not resolved properly on the backend
						//var_dump(strlen($payload), $payload);

						if ($user = $this->session_get_user_from_fingerprint_checksum($_user_post_validation, strlen($payload))){
							//update user setting for each request
							$_SESSION['user'] = $user;
							$_SESSION['user']['permissions'] = explode(',', $user['permissions'] ? : '');
							$_SESSION['user']['units'] = explode(',', $user['units'] ? : '');
							$_SESSION['user']['app_settings'] = json_decode($user['app_settings'] ? : '', true);
							$_SESSION['user']['image'] = './' . $user['image'];
						}
						//else $this->response([strlen($payload), $payload], 401);
						else $this->response([], 401);
					} else $this->response([], 401);
				}
			}
			else {
				$params = session_get_cookie_params();
				setcookie(session_name(), '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
				session_destroy();
				session_write_close();
			}
		}
	}
	
	/**
	 * accumulates system messages to a user or permission group and populates $this->_messages
	 * @param array $group 'permission'=>[] ||&& 'unit'=>[] reach out for permission holders or unit member or permission holders within units
	 * @param string $message actual message content
	 * 
	 * @return bool if any recipients have been added
	 * 
	 * if permission and unit are both set only permission holders within units get the message! 
	 */
	public function alertUserGroup($group = [], $message = ''){
		$permission = $unit = $user = $recipients = [];
		if (isset($group['permission'])){
			foreach($group['permission'] as $prmssn){
				$permissions = SQLQUERY::EXECUTE($this->_pdo, 'application_get_permission_group', [
					'values' => [
						':group' => $prmssn
					]
				]);
				array_push($permission, ...array_column($permissions, 'id'));
			}
		}
		if (isset($group['unit'])){
			foreach($group['unit'] as $unt){
				$groups = SQLQUERY::EXECUTE($this->_pdo, 'application_get_unit_group', [
					'values' => [
						':group' => $unt
					]
				]);
				array_push($unit, ...array_column($groups, 'id'));
			}
		}
		if (isset($group['user']) && count($group['user'])){
			$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
				'replacements' => [
					':id' => '',
					':name' => implode(',', $group['user'])
				]
			]);
			array_push($user, ...array_column($users, 'id'));
		}
		if ($permission) $recipients = $permission;
		if ($unit) $recipients = $unit;
		if ($permission && $unit) $recipients = array_intersect($permission, $unit);
		array_push($recipients, ...$user);
		$recipients = array_unique($recipients);
		// delete system user to receive any messages
		if (($sysusr = array_search('1', $recipients)) !== false) unset($recipients[$sysusr]);
		if (!$recipients) return false;
		if (!isset($this->_messages[$message])) $this->_messages[$message] = [];
		array_push($this->_messages[$message], ...$recipients);
		return true;
	}

	/**
	 * posts system messages according to $this->_messages
	 * this avoids multiple messages e.g. to supervisors of a certain unit, that are also ceo, prrc or qmo
	 * recipient ids will be used uniquely for each message content
	 */
	private function alertUserGroupSubmit(){
		if (!$this->_messages) return;
		$sqlchunks = [];
		foreach($this->_messages as $message => $recipients) {
			$recipients = array_unique($recipients);
			$insertions = [];
			foreach($recipients as $rcpnt_id) {
				$insertions[] = [
					':to_user' => $rcpnt_id,
					':message' => $message
				];
			}
			$sqlchunks = array_merge($sqlchunks, SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('message_post_system_message'), $insertions));
		}
		foreach ($sqlchunks as $chunk){
			SQLQUERY::EXECUTE($this->_pdo, $chunk);
		}
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
			207 => 'Multistatus',
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
			505 => 'HTTP Version Not Supported',
			507 => 'Insufficient Storage',
		);
		return ($status[$this->_httpResponse])?$status[$this->_httpResponse]:$status[500];
	}


	/**
	 * returns a default content for lack of database entries
	 * @param string $type type of requested but mission content
	 * @return array assemble object
	 */
	public function noContentAvailable($type){
		return [[
			['type' => 'nocontent',
			'content' => $this->_lang->GET('general.no_content_available', [':content' => $type])]
		]];
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
			$this->response([], 404); // If the method not exist within this class, response would be "Page not found".
	}

	/**
	 * get a session fingerprint for session user
	 */
	public function session_get_fingerprint(){
		if (isset($_SESSION['user']['id']))
			if ($fingerprint = SQLQUERY::EXECUTE($this->_pdo, 'application_get_session_fingerprint', [
				'values' => [
					':id' => session_id(),
					':user_id' => $_SESSION['user']['id']
				]
			])) return $fingerprint ? $fingerprint[0]['fingerprint'] : null;
		return null;
	}

	public function session_get_user_from_fingerprint_checksum($hash, $checksum){
		if ($user = SQLQUERY::EXECUTE($this->_pdo, 'application_get_user_from_fingerprint_checksum', [
				'values' => [
					':checksum' => $checksum,
					':hash' => $hash
				]
			])) return $user ? $user[0] : null;
		return null;
	}

	/**
	 * store a valid user session, delete outdated
	 */
	public function session_set(){
		$deldate = clone ($this->_currentdate);
		$deldate->modify('-' . CONFIG['lifespan']['sessions'] . ' days');
		SQLQUERY::EXECUTE($this->_pdo, 'application_delete_sessions', [
			'values' => [
				':date' => $deldate->format('Y-m-d H:i:s')
			]
		]);

		// dirty error suppression
		// the session cookie may occasionally not be deleted on automated logout, submitting a false session id, raising a duplicate key error
		// i don't know how to handle this otherwise
		// on failure the user may just not get a meaningful response and has to update the interface.
		// does not happen on proper logout and even on automated logout only rarely
		@SQLQUERY::EXECUTE($this->_pdo, 'application_post_session', [
			'values' => [
				':id' => session_id(),
				':user_id' => $_SESSION['user']['id']
			]
		]);
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
	 * api response and final exiting method executions
	 * @param array|string $data what should be responded
	 * @param int $status optional override for error cases
	 * no return, end of api processing
	 */
	public function response($data, $status = 200){
		$this->alertUserGroupSubmit();

		if(is_array($data)) {
			$data = json_encode($data);
			$this->_httpResponse = $status;
		}
		else {
			$data = '';
			$this->_httpResponse = 500;
		}
		SQLQUERY::CLOSE($this->_pdo);
		$this->set_headers();
		echo $data;
		exit;
	}
}

if (in_array(REQUEST[0], [
	'application',
	'audit',
	'calendar',
	'consumables',
	'csvfilter',
	'file',
	'document',
	'message',
	'measure',
	'notification',
	'order',
	'record',
	'risk',
	'texttemplate',
	'tool',
	'user'])) require_once(REQUEST[0] . '.php');

$call = strtoupper(REQUEST[0]);
$api = new $call();
$api->processApi();

exit();
?>
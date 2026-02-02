<?php
/**
 * [CARO - Cloud Assisted Records and Operations](https://github.com/erroronline1/caro)  
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.  
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.  
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.  
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

namespace CARO\API;

session_set_cookie_params([
	'domain' => $_SERVER['HTTP_HOST'],
	'secure' => true,
	'httponly' => true,
]);
@session_start();

ini_set('display_errors', 1); error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=UTF-8');
require_once('_config.php');
define ('REQUEST',
	array_map(
		fn($param) => $param,//str_replace('%2B', '+', $param),
		explode("/", substr(rawurldecode(mb_convert_encoding($_SERVER['PATH_INFO'], 'UTF-8', mb_detect_encoding($_SERVER['PATH_INFO'], ['ASCII', 'UTF-8', 'ISO-8859-1']))), 1))
	)
);
require_once('_utility.php'); // general utilities
require_once('_sqlinterface.php');
require_once('_language.php');
// import to determine if interface is present
require_once("./_erpinterface.php");

if (!CONFIG['application']['debugging']) {
	ini_set('display_errors', 0); error_reporting(0);
}

class API {
	/**
	 * preset all passed parameters
	 */
	public $_payload = null;

	/**
	 * preset database connection
	 */
	public $_pdo;
	
	/**
	 * preset standard response code
	 */
	private $_httpResponse = 200;

	/**
	 * message array for accumulated user messages
	 * to be populated by message as key and user ids as value array
	 * see alertUserGroup() and alertUserGroupSubmit()
	 */
	private $_messages = [];

	/**
	 * make languagemodel LANG class and its methods available
	 */
	public $_lang = null;

	/**
	 * public preset of descendant classes property to ececute requested method as per REQUEST[1]
	 */
	public $_requestedMethod = null;

	/**
	 * public preset of authentified user data
	 */
	public $_auth = [];

	/**
	 * public date settings to be updated by user settings
	 * containing location, timezone, date format and current date as DateTime-object
	 */
	public $_date = [];

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
		$this->_pdo = new \PDO( CONFIG['sql'][CONFIG['sql']['use']]['driver'] . ':' . CONFIG['sql'][CONFIG['sql']['use']]['host'] . ';' . CONFIG['sql'][CONFIG['sql']['use']]['database']. ';' . CONFIG['sql'][CONFIG['sql']['use']]['charset'], CONFIG['sql'][CONFIG['sql']['use']]['user'], CONFIG['sql'][CONFIG['sql']['use']]['password'], $options);
		$dbsetup = SQLQUERY::PREPARE('DYNAMICDBSETUP');
		if ($dbsetup) $this->_pdo->exec($dbsetup);

		$this->_lang = new LANG();

		// (re)authentify session user
		if ((REQUEST[0] === 'application' && (
			in_array(REQUEST[1], ['menu'])
			|| (REQUEST[1] === 'authentify' && $_SERVER['REQUEST_METHOD'] === 'DELETE')
		))){ // these requests do not need authentification or handle it on their own
			$this->_auth = true;
		}
		else {
			$this->_auth = $this->auth();
		}

		// check if a registered user with valid token is logged in
		if ($this->_auth){
			// valid user IS logged in

			// override user with submitted user, especially for delayed cached requests by service worker (offline fallback)
			if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])
				&& isset(REQUEST[1]) && REQUEST[1] !== 'authentify'
			){
				// post and put MUST have _user_post_validation payload
				if (($_user_post_validation = UTILITY::propertySet($this->_payload, '_user_post_validation')) !== false) {
					unset ($this->_payload->_user_post_validation);
					// sanitation of arrays; synchronization with frontent checksum not possible
					$payload = json_decode(json_encode($this->_payload), true);
					foreach ($payload as $key => $value){
						if ($value && gettype($value) === 'array') unset($payload[$key]);
					}
					//UTILITY::debug(json_encode($payload));
					$payload = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
						return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
						}, json_encode($payload) );
					$payload = preg_replace(['/\\\\r|\\\\n|\\\\t/', '/[\W_]/', '/0D0A/i'], '', $payload);  // harmonized cross browser, 0d0a is carriage return that is somehow not resolved properly on the backend
					//UTILITY::debug(strlen($payload), $payload);

					if ($user = $this->session_get_user_from_fingerprint_checksum($_user_post_validation, strlen($payload))){
						//update user setting for each request
						$_SESSION['user'] = $user;
						$_SESSION['user']['permissions'] = explode(',', $user['permissions'] ? : '');
						$_SESSION['user']['units'] = explode(',', $user['units'] ? : '');
						$_SESSION['user']['app_settings'] = json_decode($user['app_settings'] ? : '', true);
						$_SESSION['user']['image'] = ($user['id'] > 1 ? './api/api.php/file/stream/' : '') . $user['image'];
						// default primary unit if only one unit is assigned
						if (count($_SESSION['user']['units']) && count($_SESSION['user']['units']) < 2 && !isset($_SESSION['user']['app_settings']['primaryUnit'])) $_SESSION['user']['app_settings']['primaryUnit'] = $_SESSION['user']['units'][0];
					}
					else $this->response([strlen($payload), $payload], 401);
					//else $this->response([], 401);
				} else $this->response([], 401);
			}
		}
		else {
			// user validity failed, destroy session
			$params = session_get_cookie_params();
			setcookie(session_name(), '', 1, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
			session_destroy();
			session_write_close();
			session_unset();
		}

		// set date settings according to defaults or session user settings
		$this->_date = $this->date();
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
			foreach ($group['permission'] as $prmssn){
				$permissions = SQLQUERY::EXECUTE($this->_pdo, 'application_get_permission_group', [
					'values' => [
						':group' => $prmssn
					]
				]);
				foreach ($permissions as $userrow){
					if (PERMISSION::filteredUser($userrow)) continue;
					array_push($permission, $userrow['id']);
				}
			}
		}
		if (isset($group['unit'])){
			foreach ($group['unit'] as $unt){
				$groups = SQLQUERY::EXECUTE($this->_pdo, 'application_get_unit_group', [
					'values' => [
						':group' => $unt
					]
				]);
				foreach ($groups as $userrow){
					if (PERMISSION::filteredUser($userrow)) continue;
					array_push($unit, $userrow['id']);
				}
			}
		}
		if (isset($group['user']) && count($group['user'])){
			$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
				'replacements' => [
					':id' => '',
					':name' => implode(',', $group['user'])
				]
			]);
			foreach ($users as $userrow){
				if (PERMISSION::filteredUser($userrow)) continue;
				array_push($user, $userrow['id']);
			}
		}
		if ($permission) $recipients = $permission;
		if ($unit) $recipients = $unit;
		if ($permission && $unit) $recipients = array_intersect($permission, $unit);
		array_push($recipients, ...$user);
		$recipients = array_unique($recipients);
		if (!$recipients) return false;
		if (!isset($this->_messages[$message])) $this->_messages[$message] = [];
		array_push($this->_messages[$message], ...$recipients);
		return true;
	}

	/**
	 * posts system messages according to $this->_messages
	 * this avoids multiple messages e.g. to supervisors of a certain unit, that are also ceo, prrc or qmo
	 * recipient ids will be used uniquely for each message content
	 * public if imported as object (e.g. notification)
	 */
	public function alertUserGroupSubmit(){
		if (!$this->_messages) return;
		$sqlchunks = [];
		foreach ($this->_messages as $message => $recipients) {
			$recipients = array_unique($recipients);
			$insertions = [];
			foreach ($recipients as $rcpnt_id) {
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
	 * user authentification, returns updated user data or inputs to (re)affirm authentification, processed by frontent api dialog
	 * actively requesting auth or terminating sessions is handled by application->authentify() for parent class having no own endpoint
	 * 
	 * @return array either application- and user-settings or render structure for login form
	 */
	public function auth(){
		$reAuthUser = (
			//(REQUEST[0] === 'application' && REQUEST[1] === 'authentify' && $_SERVER['REQUEST_METHOD'] === 'GET') // get requests for intermediate frontent authentification
			//||
			(isset($_SESSION['lastrequest'])
			&& (time() - $_SESSION['lastrequest'] > ($_SESSION['user']['app_settings']['idle'] ?? min(CONFIG['lifespan']['session']['idle'], ini_get('session.gc_maxlifetime'))))) // session timeout
		);
		$returnUser = (
			(!$reAuthUser && isset($_SESSION['user'])) // if there are no reasons for reauthentification on valid user session return if applicable
			|| UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.login', [], true)) // on submitting a token return confirmed
		);

		if (!($token = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.login', [], true))) && isset($_SESSION['user'])) $token = $_SESSION['user']['token'];

		$valid = false;
		if ($returnUser){
			// login or reauth by token
			// get user by token and their application settings for frontend setup
			$user = SQLQUERY::EXECUTE($this->_pdo, 'application_login', [
				'values' => [
					':token' => strval($token)
				]
			]);
			$user = $user ? $user[0]: null;
			if ($user){
				// confirm new login or reauth from previous user
				if (!isset($_SESSION['user']['token']) || $_SESSION['user']['token'] === $user['token']) {
					$_SESSION['user'] = $user;
					$_SESSION['user']['permissions'] = explode(',', $user['permissions'] ? : '');
					$_SESSION['user']['units'] = explode(',', $user['units'] ? : '');
					$_SESSION['user']['app_settings'] = $user['app_settings'] ? json_decode($user['app_settings'], true) : [];
					$_SESSION['user']['image'] = ($user['id'] > 1 ? './api/api.php/file/stream/' : '') . $user['image'];
					// default primary unit if only one unit is assigned
					if (count($_SESSION['user']['units']) && count($_SESSION['user']['units']) < 2 && !isset($_SESSION['user']['app_settings']['primaryUnit'])) $_SESSION['user']['app_settings']['primaryUnit'] = $_SESSION['user']['units'][0];

					// renew session timeout except for defined requests
					if (!in_array(REQUEST[0], ['notification'])) $_SESSION['lastrequest'] = time();
					$this->_date = $this->date();

					$this->session_set();
					$valid = true;
				}
			}
			if ($valid) {
				$this->_lang = new LANG(); // reinstatiate for proper language return
				return [
					'user' => [
						'name' => $_SESSION['user']['name'],
						'image' => $_SESSION['user']['image'],
						'app_settings' => $_SESSION['user']['app_settings'],
						'fingerprint' => $this->session_get_fingerprint(),
						'permissions' => [
							'orderprocessing' => PERMISSION::permissionFor('orderprocessing'),
							'patient' => boolval(array_intersect(['patient'], $_SESSION['user']['permissions']))
						]
					],
					'config' => [
						'application' => [
							'defaultlanguage' => $_SESSION['user']['app_settings']['language'] ?? CONFIG['application']['defaultlanguage'],
							'order_gtin_barcode' => CONFIG['application']['order_gtin_barcode'],
							'debugging' => CONFIG['application']['debugging'],
						],
						'lifespan' => [
							'session' => [
								'idle' => $_SESSION['user']['app_settings']['idle'] ?? min(CONFIG['lifespan']['session']['idle'], ini_get('session.gc_maxlifetime')),
							]
						],
						'limits' => [
							'qr_errorlevel' => CONFIG['limits']['qr_errorlevel']
						],
						'label' => CONFIG['label'],
						'forbidden' => CONFIG['forbidden'],
						'system' => ERPINTERFACE && ERPINTERFACE->_instatiated ? ['erp' => CONFIG['system']['erp']] : []
					],
					'language' => $this->_lang->GETALL()
				];
			}
		}
		// append login screen
		$response = ['render' =>
			[
				'content' => [
					[
						[
							'type' => 'scanner',
							'attributes' => [
								'name' => $this->_lang->GET('application.login', [], true),
								'type' => 'password'
							]
						]
					]
				]
			],
			'language' => $this->_lang->GETALL()
		];

		if (!$reAuthUser){
			// prepare term of service with providable permission settings
			$tos = [];
			$replacements = [
				':issue_mail' => CONFIG['application']['issue_mail'],
				// no use of PERMISSIONS::permissionFor, because this method required a logged in user
				':permissions' => implode(', ', array_map(fn($v) => $this->_lang->_USER['permissions'][$v], ['admin', ...preg_split('/\W+/', CONFIG['permissions']['users'])]))
			];

			// append terms-of-service slider panels
			foreach ($this->_lang->_USER['application']['terms_of_service'] as $description => $content){
				$tos[] = [[
					'type' => 'textsection',
					'attributes' => [
						'name' => $description,
					],
					'content' => strtr($content, $replacements)
				]];
			}
			$response['render']['content'][] = $tos;

			// append tos-acceptance input
			$response['render']['content'][] = [
				[
					'type' => 'checkbox',
					'content' => [
						$this->_lang->GET('application.terms_of_service_accepted', [], true) => ['required' => true]
					]
				]
			];
			// linux style delay of login form wrong attempts
			if (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.login', [], true))) sleep(2);
		}
		$this->response($response, 511);
	}

	/**
	 * converts a date-string to user timezone and formats default ISO 8601 date to custom as per config 
	 * @param string $input YYYY-MM-DD H:i (:s optional)
	 * @param bool $defaultFormat for return of default date format for official exports independent of user preference
	 * @return string *dateformat* H:i (:s optional)
	 */
	public function convertFromServerTime($input, $defaultFormat = false, $timezoneConversion = true){
		if (!$input) return '';

		$parse = $input;
		if (strlen($input) === 16) $parse .= ':00'; // append seconds to get a valid datetime format
		// create a datetime from input
		try {
			$date = new \DateTime($parse);
		}
		catch (\Exception $e) {
			return $input;
		} 
		// convert timezone if user setting exists and differs from server setting
		if ($this->_date['timezone'] !== date_default_timezone_get() && $timezoneConversion){
			$date->setTimezone(new \DateTimeZone($this->_date['timezone']));
		}

		// return formatted datetime string, abbreviated if applicable
		return substr($date->format($defaultFormat ? CONFIG['calendar']['dateformats'][array_key_first(CONFIG['calendar']['dateformats'])] . ' H:i:s' : $this->_date['dateformat'] . ' H:i:s'), 0, strlen($input));
	}

	/**
	 * converts an ISO date-string from user timezone to server time, e.g. on form submissions and other request parameters
	 * @param string $input YYYY-MM-DD H:i:s, supposed to be a payload date
	 * @return string YYYY-MM-DD H:i:s
	 */
	public function convertToServerTime($input){
		if (!$input) return '';
		// create a datetime from input
		try {
			$date = new \DateTime($input, new \DateTimeZone($this->_date['timezone']));
		}
		catch (\Exception $e) {
			return $input;
		}
		// convert timezone if user setting exists and differs from server setting
		if ($this->_date['timezone'] !== date_default_timezone_get()){
			$date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
		}

		// return formatted datetime string, abbreviated if applicable
		return substr($date->format('Y-m-d H:i:s'), 0, strlen($input));
	}

	/**
	 * declares default or set configuration for timezones, desired date format and applicable holidays
	 * @return array containing timezone, dateformat, holidays and current datetime
	 */
	private function date(){
		// top config entries are default
		$return = [
			'timezone' => date_default_timezone_get(),
			'dateformat' => CONFIG['calendar']['dateformats'][array_key_first(CONFIG['calendar']['dateformats'])],
			'locations' => CONFIG['locations'][array_key_first(CONFIG['locations'])],
		];
		// override with user-selected config options
		if (isset($_SESSION['user']['app_settings']['timezone']) && isset(CONFIG['calendar']['timezones'][$_SESSION['user']['app_settings']['timezone']])) $return['timezone'] = CONFIG['calendar']['timezones'][$_SESSION['user']['app_settings']['timezone']];
		if (isset($_SESSION['user']['app_settings']['dateformat']) && isset(CONFIG['calendar']['dateformats'][$_SESSION['user']['app_settings']['dateformat']])) $return['dateformat'] = CONFIG['calendar']['dateformats'][$_SESSION['user']['app_settings']['dateformat']];
		if (isset($_SESSION['user']['app_settings']['location']) && isset(CONFIG['locations'][$_SESSION['user']['app_settings']['location']] )) $return['locations'] = CONFIG['locations'][$_SESSION['user']['app_settings']['location']];

		$return['servertime'] = new \DateTime('now');
		$return['usertime'] = new \DateTime('now', new \DateTimeZone($return['timezone']));
		return $return;
	}
	
	/**
	 * @return string readable http status message based on $this->_httpResponse
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
			511 => 'Network Authentication Required'
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
			[
				'type' => 'nocontent',
				'content' => $this->_lang->GET('general.no_content_available', [':content' => $type])
			]
		]];
	}

	/**
	 * executes the called api method
	 * no return
	 */
	public function processApi(){
		$func = strtolower($this->_requestedMethod);
		if (method_exists($this, $func))
			$this->$func();
		else
			$this->response([], 404); // if the method doesn't exist within this class, response would be "Page not found".
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
		$deldate = clone ($this->_date['servertime']);
		$deldate->modify('-' . CONFIG['lifespan']['session']['records'] . ' days');
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

		if (is_array($data)) {
			$data = UTILITY::json_encode($data);
			$this->_httpResponse = $status;
		}
		else {
			$data = '';
			$this->_httpResponse = 500;
		}
		SQLQUERY::CLOSE($this->_pdo);
		$this->set_headers();
		echo $data;
		exit();
	}
}

if (in_array(REQUEST[0], [
	'application',
	'audit',
	'calendar',
	'consumables',
	'csvfilter',
	'erpquery',
	'file',
	'document',
	'maintenance',
	'message',
	'measure',
	'notification',
	'order',
	'record',
	'responsibility',
	'risk',
	'texttemplate',
	'tool',
	'user'])) require_once(REQUEST[0] . '.php');

$call = "CARO\\API\\" . strtoupper(REQUEST[0]);
$api = new $call();
$api->processApi();

exit();
?>
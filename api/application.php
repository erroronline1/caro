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

// login handler, menu and landing page methods
require_once('_calendarutility.php');
require_once('notification.php');

class APPLICATION extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
    private $_requestedManual = null;
    private $_search = null;

	/**
	 * init parent class and set private requests
	 */
	public function __construct(){
		parent::__construct();
		$this->_requestedManual = $this->_search = isset(REQUEST[2]) ? REQUEST[2] : null;
	}

	/**
	 *           _   _           _         
	 *   ___ _ _| |_| |_ ___ ___|_|___ ___ 
	 *  | .'| | |  _|   | . |  _| |- _| -_|
	 *  |__,|___|_| |_|_|___|_| |_|___|___|
	 *
	 * (re)log in user or destroy session
	 */
	public function authorize(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'DELETE':
				$params = session_get_cookie_params();
				setcookie(session_name(), '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
				session_destroy();
				session_write_close();
				$this->response(['bye' => 'bye']);
				break;
			default:
				$auth = $this->auth(isset($_SESSION['user']));
				$this->response($auth);
		}
	}

	/**
	 *   _     ___     
	 *  |_|___|  _|___ 
	 *  | |   |  _| . |
	 *  |_|_|_|_| |___|
	 *
	 * display application info 
	 */
	public function info(){
		$lines = ['frontend' => 0, 'backend' => 0, 'code' => 0, 'documentation' => 0, 'configuration' => 0];
		foreach (['../', '../js', '../api', '../templates'] as $dir){
			foreach (scandir($dir) as $file){
				if (!isset(pathinfo($file)['extension']) || !in_array(pathinfo($file)['extension'], ['php','ini','js','html','css','md','json'])) continue;
				foreach(file($dir . '/' . $file) as $row){
					if (in_array(pathinfo($file)['extension'], ['php','ini'])){
						$lines['backend']++;
						$lines['code']++;
					}
					if (in_array(pathinfo($file)['extension'], ['json'])){
						$lines['backend']++;
						$lines['configuration']++;
					}

					if (in_array(pathinfo($file)['extension'], ['js','html','css'])){
						$lines['frontend']++;
						$lines['code']++;
					}
					if (in_array(pathinfo($file)['extension'], ['md'])){
						$lines['documentation']++;				
					}
					//$byte+= strlen($row);
				}
			}
		}

		$response = ['render' => ['content' => [
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('application.info.license_header')
				],
				'content' => $this->_lang->GET('application.info.license')
			],
			[
				'type' => 'links',
				'description' => $this->_lang->GET('application.info.source_header'),
				'content' => [
					$this->_lang->GET('application.info.source') => ['target' => '_blank']
				]
			],
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('application.info.lines_header')
				],
				'content' => $this->_lang->GET('application.info.lines', [':code' => $lines['code'], ':documentation' => $lines['documentation'], ':configuration' => $lines['configuration']])
			],
		]]];
		$this->response($response);

	}
	/**
	 *   _
	 *  | |___ ___ ___ _ _ ___ ___ ___
	 *  | | .'|   | . | | | .'| . | -_|
	 *  |_|__,|_|_|_  |___|__,|_  |___|
	 *            |___|       |___|
	 * respond with $this->_lang->_USER as transfer to js frontend
	 */
    public function language(){
		$this->response(['data' => $this->_lang->GETALL()]);
	}

	/**
	 *   _         _
	 *  | |___ ___|_|___
	 *  | | . | . | |   |
	 *  |_|___|_  |_|_|_|
	 *        |___|
	 * log in user or destroy session
	 * without current user respond with login form
	 */
	public function login(){
		/*		
		$auth = $this->auth();
		$this->response($auth);
		return;

		*/
			// on reload this method is requested by default
			// get current user and application settings for frontend setup
			if (!UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.login')) && isset($_SESSION['user'])){
				$this->response([
					'user' => [
					'image' => $_SESSION['user']['image'],
					'app_settings' => $_SESSION['user']['app_settings'],
					'fingerprint' => $this->session_get_fingerprint(),
					'permissions' => [
						'orderprocessing' => PERMISSION::permissionFor('orderprocessing')
					]
				],
				'config' => [
					'application' => [
						'defaultlanguage' => isset($_SESSION['user']['app_settings']['language']) ? $_SESSION['user']['app_settings']['language'] : CONFIG['application']['defaultlanguage'],
						'order_gtin_barcode' => CONFIG['application']['order_gtin_barcode']
					],
					'lifespan' => [
						'idle' => min(CONFIG['lifespan']['idle'], ini_get('session.gc_maxlifetime')),
					],
					'limits' => [
						'qr_errorlevel' => CONFIG['limits']['qr_errorlevel']
					],
					'label' => CONFIG['label'],
					'forbidden' => CONFIG['forbidden']
				]]);
			}

			// select single user based on login token
			$query = SQLQUERY::EXECUTE($this->_pdo, 'application_login', [
				'values' => [
					':token' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.login'))
				]
			]);
			if ($query && UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.terms_of_service_accepted'))){
				// get current user and application settings for frontend setup
				$result = $query[0];
				$_SESSION['user'] = $result;
				$_SESSION['user']['permissions'] = explode(',', $result['permissions']);
				$_SESSION['user']['units'] = explode(',', $result['units']);
				$_SESSION['user']['app_settings'] = $result['app_settings'] ? json_decode($result['app_settings'], true) : [];
				$_SESSION['user']['image'] = './' . $result['image'];

				$this->session_set();

				$this->response([
					'user' => [
					'image' => $_SESSION['user']['image'],
					'app_settings' => $_SESSION['user']['app_settings'],
					'fingerprint' => $this->session_get_fingerprint(),
					'permissions' => [
						'orderprocessing' => PERMISSION::permissionFor('orderprocessing')
					]
				],
				'config' => [
					'application' => [
						'defaultlanguage' => isset($_SESSION['user']['app_settings']['language']) ? $_SESSION['user']['app_settings']['language'] : CONFIG['application']['defaultlanguage'],
						'order_gtin_barcode' => CONFIG['application']['order_gtin_barcode']
					],
					'lifespan' => [
						'idle' => min(CONFIG['lifespan']['idle'], ini_get('session.gc_maxlifetime')),
					],
					'limits' => [
						'qr_errorlevel' => CONFIG['limits']['qr_errorlevel']
					],
					'label' => CONFIG['label'],
					'forbidden' => CONFIG['forbidden']
				]]);
			}
		// user not found is handled by api as well, destroy if logout requested, condition important to avoid errors on session timeout
		if ($_SESSION){
			$params = session_get_cookie_params();
			setcookie(session_name(), '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
			session_destroy();
			session_write_close();
			header('Location:' . preg_split('/[\\/]/m', $_SERVER['PHP_SELF'])[1] . '/../index.html');
			die();
		}
		// append login screen
		$response = ['render' =>
			[
				'form' => [
					'action' => "javascript:api.application('post','login')",
					'data-usecase'=> 'login',
				],
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
			'user' => [],
			'config' => [
				'application' => [
					'defaultlanguage' => CONFIG['application']['defaultlanguage'],
				]
			]
		];

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
		$this->response($response);
	}

	/**
	 *                         _
	 *   _____ ___ ___ _ _ ___| |
	 *  |     | .'|   | | | .'| |
	 *  |_|_|_|__,|_|_|___|__,|_|
	 *
	 * manual edting
	 * POST, PUT and DELETE manual entries or
	 * respond with form to add or edit manual entries 
	 */
	public function manual(){
		if (!PERMISSION::permissionFor('appmanual')) $this->response([], 401);
		$result = ['render' => ['content' => []]];
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				// prepare entry
				$permissions = [];
				$entry = [
					'title' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.title')),
					'content' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.content')),
					'permissions' => '',
				];
				
				// check forbidden names
				if(UTILITY::forbiddenName($entry['title'])) $this->response(['response' => ['msg' => $this->_lang->GET('application.manual.forbidden_name', [':name' => $entry['title']]), 'type' => 'error']]);
		
				// chain checked permission levels
				foreach($this->_lang->_USER['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, str_replace(' ', '_', $description))) {
						$permissions[] = $level;
					}
				}
				$entry['permissions'] = implode(',', $permissions);

				// post manual entry
				$query = SQLQUERY::EXECUTE($this->_pdo, 'application_post_manual', [
					'values' => [
						':title' => $entry['title'],
						':content' => $entry['content'],
						':permissions' => $entry['permissions']
					]
				]);
		
				if ($query) $this->response([
					'response' => [
						'id' => $this->_pdo->lastInsertId(),
						'msg' => $this->_lang->GET('application.manual.saved', [':name' => $entry['title']]),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => false,
						'name' => $this->_lang->GET('application.manual.not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				// prepare entry
				$permissions = [];
				$entry = [
					'title' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.title')),
					'content' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.content')),
					'permissions' => '',
				];
		
				// check forbidden names
				if(UTILITY::forbiddenName($entry['title'])) $this->response(['response' => ['msg' => $this->_lang->GET('application.manual.forbidden_name', [':name' => $entry['title']]), 'type' => 'error']]);
		
				// chain checked permission levels
				foreach($this->_lang->_USER['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, str_replace(' ', '_', $description))) {
						$permissions[] = $level;
					}
				}
				$entry['permissions'] = implode(',', $permissions);

				// update manual entry
				$query = SQLQUERY::EXECUTE($this->_pdo, 'application_put_manual', [
					'values' => [
						':title' => $entry['title'],
						':content' => $entry['content'],
						':permissions' => $entry['permissions'],
						':id' => $this->_requestedManual
					]
				]);
				if ($query) $this->response([
					'response' => [
						'id' => $this->_requestedManual,
						'msg' => $this->_lang->GET('application.manual.saved', [':name' => $entry['title']]),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => false,
						'name' => $this->_lang->GET('application.manual.not_saved'),
						'type' => 'error'
					]]);

				break;
			case 'GET':
				$query = SQLQUERY::EXECUTE($this->_pdo, 'application_get_manual_by_id', [
					'values' => [
						':id' => $this->_requestedManual != 'false' ? $this->_requestedManual : null
					]
				]);

				// prepare entry properties
				if (!$query) $entry = [
					'id' => null,
					'title' => '',
					'content' => '',
					'permissions' => ''
				];
				else $entry = $query[0];

				// append form
				$result['render']['form'] = [
					'data-usecase' => 'manual',
					'action' => "javascript:api.application('" . ($entry['id'] ? 'put' : 'post') . "', 'manual'" . ($entry['id'] ? ", " . $entry['id'] : '') . ")"];

				// prepare all entries selection
				$query = SQLQUERY::EXECUTE($this->_pdo, 'application_get_manual');
				$options = ['...' . $this->_lang->GET('application.manual.new_topic') => (!$this->_requestedManual || $this->_requestedManual === '...' . $this->_lang->GET('application.manual.new_topic')) ? ['selected' => true] : []];
				foreach ($query as $row){
					$options[$row['title']] = ['value' => $row['id']];
					if ($entry['id'] === $row['id']) $options[$row['title']]['selected'] = true; 
				}
				ksort($options);

				// set up available permissions, set checked from selected entry
				$permissions = [];
				$entry['permissions'] = explode(',', $entry['permissions']);
				foreach($this->_lang->_USER['permissions'] as $level => $description){
					$permissions[$description] = in_array($level, $entry['permissions']) ? ['checked' => true] : [];
				}

				// append entry form
				$result['render']['content'] = [
					[
						[
							'type' => 'select',
							'attributes' => [
								'name' => $this->_lang->GET('application.manual.select_topic'),
								'onchange' => "api.application('get', 'manual', this.value)"
							],
							'content' => $options
						],
						[
							'type' => 'text',
							'attributes' => [
								'name' => $this->_lang->GET('application.manual.title'),
								'value' => $entry['title'],
							]
						],
						[
							'type' => 'textarea',
							'attributes' => [
								'rows' => 8,
								'name' => $this->_lang->GET('application.manual.content'),
								'value' => $entry['content']
							]
						],
						[
							'type' => 'checkbox',
							'attributes' => [
								'name' => $this->_lang->GET('application.manual.permissions')
							],
							'content' => $permissions
						]
					]
				];

				// append delete button if applicable
				if ($entry['id']) $result['render']['content'][] = [
						[
							'type' => 'deletebutton',
							'attributes' => [
								'value' => $this->_lang->GET('application.manual.delete'),
								'type' => 'button',
								'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('application.manual.delete_confirm') ."', options: {".
								"'".$this->_lang->GET('general.cancel_button')."': false,".
								"'".$this->_lang->GET('general.ok_button')."': {value: true, class: 'reducedCTA'}".
									"}}).then(confirmation => {if (confirmation) api.application('delete', 'manual', " . $entry['id'] . ")})"
							]
						]
				];
	
				break;
			case 'DELETE':
				$query = SQLQUERY::EXECUTE($this->_pdo, 'application_delete_manual', [
					'values' => [
						':id' => $this->_requestedManual
					]
				]);
				if ($query) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('application.manual.deleted'),
						'id' => false,
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('application.manual.error'),
						'id' => $this->_requestedManual,
						'type' => 'error'
					]]);
				break;
		}
		$this->response($result);
	}

	/**
	 *
	 *   _____ ___ ___ _ _
	 *  |     | -_|   | | |
	 *  |_|_|_|___|_|_|___|
	 *
	 * respond with menu taking user permissions into account to js assemble_helper.userMenu()
	 */
	public function menu(){
		// get permission based menu items
		if (!isset($_SESSION['user'])) $this->response(['render' => [$this->_lang->GET('menu.application.header') => [
			$this->_lang->GET('menu.application.signin') => ['onclick' => "api.application('get', 'login')"],
			$this->_lang->GET('menu.application.info') => ['onclick' => "api.application('get', 'info')"]
			]]]);	// early exit

		//////////////////////////////////
		// default functions for everyone
		//////////////////////////////////
		$menu = [
			// order here defines frontend order
			$this->_lang->GET('menu.communication.header') => [
				$this->_lang->GET('menu.communication.conversations') => ['onclick' => "api.message('get', 'conversation')", 'data-unreadmessages' => '0'],
				$this->_lang->GET('menu.communication.register') => ['onclick' => "api.message('get', 'register')"],
				$this->_lang->GET('menu.communication.texttemplate_texts') => ['onclick' => "api.texttemplate('get', 'text')"],
				$this->_lang->GET('menu.communication.responsibility') => ['onclick' => "api.responsibility('get', 'responsibilities')"],
			],
			$this->_lang->GET('menu.records.header') => [
				$this->_lang->GET('menu.records.record_create_identifier') => ['onclick' => "api.record('get', 'identifier')"],
				$this->_lang->GET('menu.records.record_summary') => ['onclick' => "api.record('get', 'records')"]
			],
			$this->_lang->GET('menu.calendar.header') => [
				$this->_lang->GET('menu.calendar.scheduling') => ['onclick' => "api.calendar('get', 'schedule')"],
				$this->_lang->GET('menu.calendar.longtermplanning') => ['onclick' => "api.calendar('get', 'longtermplanning')"]
			],
			$this->_lang->GET('menu.application.header') => [
				$this->_lang->GET('menu.application.signout_user', [':name' => $_SESSION['user']['name']]) => ['onclick' => "api.application('delete', 'authorize')"],
				$this->_lang->GET('menu.application.start') => ['onclick' => "api.application('get', 'start')"],			
				$this->_lang->GET('menu.application.user_profile') => ['onclick' => "api.user('get', 'profile')"],			
			],
			$this->_lang->GET('menu.files.header') => [
				$this->_lang->GET('menu.files.files') => ['onclick' => "api.file('get', 'files')"],
				$this->_lang->GET('menu.files.bundles') => ['onclick' => "api.file('get', 'bundle')"],
				$this->_lang->GET('menu.files.sharepoint') => ['onclick' => "api.file('get', 'sharepoint')"],
			],
			$this->_lang->GET('menu.purchase.header') => [
				$this->_lang->GET('menu.purchase.order') => ['onclick' => "api.purchase('get', 'order')"],
				$this->_lang->GET('menu.purchase.prepared_orders') => ['onclick' => "api.purchase('get', 'prepared')"],
				$this->_lang->GET('menu.purchase.approved_orders') => ['onclick' => "api.purchase('get', 'approved')"],
				$this->_lang->GET('menu.purchase.vendor') => ['onclick' => "api.purchase('get', 'vendor')"],
				$this->_lang->GET('menu.purchase.product') => ['onclick' => "api.purchase('get', 'product')"],
			],
			$this->_lang->GET('menu.tools.header') => [
				$this->_lang->GET('menu.tools.digital_codes') => ['onclick' => "api.tool('get', 'code')"],
				$this->_lang->GET('menu.tools.scanner') => ['onclick' => "api.tool('get', 'scanner')"],
				$this->_lang->GET('menu.tools.stl_viewer') => ['onclick' => "api.tool('get', 'stlviewer')"],
				$this->_lang->GET('menu.tools.calculator') => ['onclick' => "api.tool('get', 'calculator')"],
				$this->_lang->GET('menu.tools.image') => ['onclick' => "api.tool('get', 'image')"],
			],
		];

		//////////////////////////////////
		// permission based functions
		//////////////////////////////////

		if (!array_intersect(['group'], $_SESSION['user']['permissions'])) $menu[$this->_lang->GET('menu.records.header')][$this->_lang->GET('menu.records.record_bundles')] = ['onclick' => "api.document('get', 'bundles')"];
		if (!array_intersect(['group'], $_SESSION['user']['permissions'])) $menu[$this->_lang->GET('menu.records.header')][$this->_lang->GET('menu.records.record_record')] = ['onclick' => "api.document('get', 'documents')"];
		// make sure risk management comes after documents so this is an exception
		$menu[$this->_lang->GET('menu.records.header')][$this->_lang->GET('menu.records.risk_management')] = ['onclick' => "api.risk('get', 'risk')"];

		if (!array_intersect(['group'], $_SESSION['user']['permissions']) && isset($_SESSION['user']['app_settings']['weeklyhours']))
			$menu[$this->_lang->GET('menu.calendar.header')][$this->_lang->GET('menu.calendar.timesheet')] = ['onclick' => "api.calendar('get', 'timesheet')"];

		if (PERMISSION::permissionFor('audit')){
			$menu[$this->_lang->GET('menu.records.header')][$this->_lang->GET('menu.records.audit')] = ['onclick' => "api.audit('get', 'audit')"];
			$menu[$this->_lang->GET('menu.records.header')][$this->_lang->GET('menu.records.management_review')] = ['onclick' => "api.audit('get', 'managementreview')"];
		}

		if (PERMISSION::permissionFor('users')) $menu[$this->_lang->GET('menu.application.header')][$this->_lang->GET('menu.application.user_manager')] =['onclick' => "api.user('get', 'user')"];

		// make sure measure management comes after texttemplate management so this is an exception
		$menu[$this->_lang->GET('menu.communication.header')][$this->_lang->GET('menu.communication.measure')] = ['onclick' => "api.measure('get', 'measure')"];

		if (PERMISSION::permissionFor('regulatory')) $menu[$this->_lang->GET('menu.tools.header')][$this->_lang->GET('menu.tools.regulatory')] =['onclick' => "api.audit('get', 'checks')"];
		if (PERMISSION::permissionFor('csvfilter')) $menu[$this->_lang->GET('menu.tools.header')][$this->_lang->GET('menu.tools.csvfilter_filter')] =['onclick' => "api.csvfilter('get', 'filter')"];
		if (PERMISSION::permissionFor('documentapproval'))$menu[$this->_lang->GET('menu.records.header')][$this->_lang->GET('menu.records.documents_manage_approval')] = ['onclick' => "api.document('get', 'approval')"];
		if (PERMISSION::permissionFor('appmanual')) $menu[$this->_lang->GET('menu.application.header')][$this->_lang->GET('menu.application.manual_manager')] =['onclick' => "api.application('get', 'manual')"];
		if (PERMISSION::permissionFor('regulatory')) $menu[$this->_lang->GET('menu.purchase.header')][$this->_lang->GET('menu.purchase.incorporated_pending')] =['onclick' => "api.purchase('get', 'pendingincorporations')"];

		// make sure info comes last so this is an exception
		$menu[$this->_lang->GET('menu.application.header')][$this->_lang->GET('menu.application.info')] = ['onclick' => "api.application('get', 'info')"];

		$this->response(['render' => $menu, 'user' => $_SESSION['user']['name']]);
	}

	/**
	 *       _           _
	 *   ___| |_ ___ ___| |_
	 *  |_ -|  _| .'|  _|  _|
	 *  |___|_| |__,|_| |_|
	 *
	 * respond with landing page
	 */
	public function start(){
		if (!isset($_SESSION['user'])) $this->response([], 401);
		$result = ['user' => $_SESSION['user']['name'], 'render' => ['content' => []]];
//		array_merge($result, $this->_auth);

		$tiles = [];

		// set up dashboard notifications
		$notifications = new NOTIFICATION;

		// messages
		$unseen = $notifications->messageunseen();
		if ($unseen) {
			$tiles[] = [
				'type' => 'tile',
				'attributes' => [
					'onclick' => "api.message('get', 'conversation')",
				],
				'content' => [
					[
						'type' => 'textsection',
						'content' => $this->_lang->GET('application.dashboard.messages', [':number' => $unseen]),
						'attributes' => [
							'data-type' => 'message',
							'name' => $this->_lang->GET('menu.communication.conversations')
						]
					]
				]
			];
		}

		// unprocessed orders
		if (PERMISSION::permissionFor('orderprocessing')){
			$unprocessed = $notifications->order();
			if ($unprocessed) {
				$tiles[] = [
					'type' => 'tile',
					'attributes' => [
						'onclick' => "api.purchase('get', 'approved')",
					],
					'content' => [
						[
							'type' => 'textsection',
							'content' => $this->_lang->GET('application.dashboard.orders', [':number' => $unprocessed]),
							'attributes' => [
								'data-type' => 'purchase',
								'name' => $this->_lang->GET('menu.purchase.approved_orders')
							]
						]
					]
				];
			}
		}

		// prepared orders
		if ($_SESSION['user']['orderauth']){
			$prepared = $notifications->preparedorders();
			if ($prepared) {
				$tiles[] = [
					'type' => 'tile',
					'attributes' => [
						'onclick' => "api.purchase('get', 'prepared')",
					],
					'content' => [
						[
							'type' => 'textsection',
							'content' => $this->_lang->GET('application.dashboard.preparedorders', [':number' => $prepared]),
							'attributes' => [
								'data-type' => 'purchase',
								'name' => $this->_lang->GET('menu.purchase.prepared_orders')
							]
						]
					]
				];
			}
		}

		// unclosed case documentation for own unit
		$number = $notifications->records();
		if ($number){
			$tiles[] = [
				'type' => 'tile',
				'attributes' => [
					'onclick' => "api.record('get', 'records')",
				],
				'content' => [
					[
						'type' => 'textsection',
						'content' => $this->_lang->GET('application.dashboard.cases', [':number' => $number]),
						'attributes' => [
							'data-type' => 'record',
							'name' => $this->_lang->GET('menu.records.header')
						]
					]
				]
			];
		}

		// unclosed audits
		if (PERMISSION::permissionFor('audit')){
			$number = $notifications->audits();
			if ($number){
				$tiles[] = [
					'type' => 'tile',
					'attributes' => [
						'onclick' => "api.audit('get', 'audit')",
					],
					'content' => [
						[
							'type' => 'textsection',
							'content' => $this->_lang->GET('application.dashboard.audits', [':number' => $number]),
							'attributes' => [
								'data-type' => 'record',
								'name' => $this->_lang->GET('menu.records.header')
							]
						]
					]
				];
			}
		}

		// unapproved documents and components
		$unapproved = $notifications->documents();
		if ($unapproved){
			$tiles[] = [
				'type' => 'tile',
				'attributes' => [
					'onclick' => "api.document('get', 'approval')",
				],
				'content' => [
					[
						'type' => 'textsection',
						'content' => $this->_lang->GET('application.dashboard.unapproveddocuments', [':number' => $unapproved]),
						'attributes' => [
							'data-type' => 'record',
							'name' => $this->_lang->GET('menu.records.documents_manage_approval')
						]
					]
				]
			];
		}

		// pending product incorporations
		$unapproved = $notifications->consumables();
		if ($unapproved){
			$tiles[] = [
				'type' => 'tile',
				'attributes' => [
					'onclick' => "api.purchase('get', 'pendingincorporations')",
				],
				'content' => [
					[
						'type' => 'textsection',
						'content' => $this->_lang->GET('application.dashboard.pendingincorporations', [':number' => $unapproved]),
						'attributes' => [
							'data-type' => 'purchase',
							'name' => $this->_lang->GET('menu.purchase.incorporated_pending')
						]
					]
				]
			];
		}
		
		// open complaints
		$complaints = $notifications->complaints();
		if ($complaints){
			$tiles[] = [
				'type' => 'tile',
				'attributes' => [
					'onclick' => "api.audit('get', 'checks', 'complaints')",
				],
				'content' => [
					[
						'type' => 'textsection',
						'content' => $this->_lang->GET('application.dashboard.complaints', [':number' => $complaints]),
						'attributes' => [
							'data-type' => 'record',
							'name' => $this->_lang->GET('menu.records.header')
						]
					]
				]
			];
		}
		if (count($tiles)) $result['render']['content'][] = $tiles;

		// storage warning
		$storage = round(disk_free_space("/") / pow(1024, 3), 3);
		if ($storage < CONFIG['limits']['storage_warning'] && PERMISSION::permissionFor('audit')){ // closest permission for matching responsibility with the whole quality management system
			$result['render']['content'][] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('application.storage_warning', [':space' => $storage . ' GB']),
					'class' => 'red'
				]
			];
		}

		// append search function to landing page
		$searchelements = [
			[
				'type' => 'search',
				'attributes' => [
					'name' => $this->_lang->GET('application.search'),
					'value' => $this->_search,
					'onkeypress' => "if (event.key === 'Enter') {api.application('get', 'start', this.value); return false;}",
				]
			]
		];

		if ($this->_search) {
			require_once('_shared.php');
			$search = new SHARED($this->_pdo);

			// search records, style like record overview
			if ($records = $search->recordsearch(['search' => $this->_search])){
				$matches = [];
				foreach ($records as $contextkey => $context){
					foreach($context as $record){
						$display = $this->_lang->GET('record.list_touched', [
							':identifier' => $record['identifier'],
							':date' => $record['last_touch'],
							':document' => $record['last_document']
						]);
						$matches[$display] = [
								'href' => "javascript:api.record('get', 'record', '" . $record['identifier'] . "')"
							];
						foreach($record['case_state'] as $case => $state){
							$matches[$display]['data-' . $case] = $state;
						}
						if ($record['complaint']) $matches[$display]['class'] = 'orange';
						if ($record['closed'])  $matches[$display]['class'] = 'green';
					}
					$searchelements[] = [
						'type' => 'links',
						'description' => $this->_lang->GET('documentcontext.' . $contextkey),
						'content' => $matches
					];
				}
			}

			// search documents
			if ($documents = $search->documentsearch(['search' => $this->_search])){
				$matches = [];
				foreach ($documents as $document){
					$matches[$document['name']] = ['href' => 'javascript:void(0);', 'onclick' => "api.record('get', 'document', '" . $document['name'] . "')"];
				}
				$searchelements[] = [
					'type' => 'links',
					'description' => $this->_lang->GET('menu.records.record_record'),
					'content' => $matches
				];
			}

			// search files
			if ($files = $search->filesearch(['search' => $this->_search])){
				$matches = [];
				foreach ($files as $file){
					$matches[preg_replace('/.+fileserver\//', '', $file)] = ['href' => './api/api.php/file/stream/' . substr($file, 1), 'target' => '_blank'];
				}
				$searchelements[] = [
					'type' => 'links',
					'description' => $this->_lang->GET('menu.files.header'),
					'content' => $matches
				];
			}

			// inform about lack of results if applicable
			if (count($searchelements) < 2) $searchelements[] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('application.search_empty'),
					'class' => 'orange'
				]
			];
		}
		$result['render']['content'][] = $searchelements;

		// calendar scheduled events
		$overview = [];
		$calendar = new CALENDARUTILITY($this->_pdo);
		$week = $calendar->render('week', 'schedule');

		// add overview to calendar view
		$overview[] = [
			'type' => 'calendar',
			'description' => $week['header'],
			'content' => $week['content'],
			'api' => 'schedule'
		];

		$displayevents = $displayabsentmates = '';
		$today = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
		$thisDaysEvents = $calendar->getDay($today->format('Y-m-d'));
		// sort events
		foreach ($thisDaysEvents as $row){
			if (!$row['affected_user']) $row['affected_user'] = $this->_lang->GET('message.deleted_user');
			if ($row['type'] === 'schedule' && (array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) || 
				array_intersect(explode(',', $row['affected_user_units'] ? : ''), $_SESSION['user']['units'])) && !$row['closed']) $displayevents .= "* " . $row['subject'] . ($row['affected_user'] !== $this->_lang->GET('message.deleted_user') ? ' (' . $row['affected_user'] . ')': '') . "\n";
			if ($row['type'] === 'timesheet' && !in_array($row['subject'], CONFIG['calendar']['hide_offduty_reasons']) && array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])) $displayabsentmates .= "* " . $row['affected_user'] . " ". $this->_lang->_USER['calendar']['timesheet']['pto'][$row['subject']] . " ". substr($row['span_start'], 0, 10) . " - ". substr($row['span_end'], 0, 10) . "\n";
		}
		// display todays events
		if ($displayevents) $overview[] = [
			'type' => 'textsection',
			'attributes' => [
					'name' => $this->_lang->GET('calendar.schedule.events_assigned_units')
			],
			'content' => $displayevents
		];
		// display todays absent workmates (sick, vacation, etc.)
		if ($displayabsentmates) $overview[] = [
			'type' => 'textsection',
			'attributes' => [
					'name' => $this->_lang->GET('calendar.timesheet.irregular')
			],
			'content' => $displayabsentmates
		];

		// add past unclosed events
		$today->modify('-1 day');
		$pastEvents = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
		$uncompleted = [];
		foreach ($pastEvents as $row){
			if (!in_array($row, $thisDaysEvents) && $row['type'] === 'schedule' && array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) && !$row['closed']) $uncompleted[$row['subject'] . " (" . substr($row['span_start'], 0, 10) . ")"] = ['href' => "javascript:api.calendar('get', 'schedule', '" . $row['span_start'] . "', '" . $row['span_start'] . "')"];
		}
		if ($uncompleted) $overview[] = [
			'type' => 'links',
			'description' => $this->_lang->GET('calendar.schedule.events_assigned_units_uncompleted'),
			'content' => $uncompleted
		];

		if ($overview) $result['render']['content'][] = $overview;

		// default add manual to landing page filteres by applicable permission
		$query = SQLQUERY::EXECUTE($this->_pdo, 'application_get_manual');
		$topics = [];
		foreach ($query as $row){
			if (PERMISSION::permissionIn($row['permissions'])) $topics[] =
				[[
					'type' => 'textsection',
					'attributes' => [
						'name' => $row['title']
					],
					'content' => $row['content']
				]];
		}
		if ($topics) $result['render']['content'][] = $topics;
		$this->response($result);
	}
}
?>
<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2024 error on line 1 (dev@erroronline.one)
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
    private $_requestedLogout = null;
    private $_requestedManual = null;
    private $_search = null;

	/**
	 * init parent class and set private requests
	 */
	public function __construct(){
		parent::__construct();
		$this->_requestedLogout = $this->_requestedManual = $this->_search = isset(REQUEST[2]) ? REQUEST[2] : null;
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
		if (!$this->_requestedLogout){
			if (!UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.login')) && isset($_SESSION['user'])){
				$this->response([
					'user' => [
					'image' => $_SESSION['user']['image'],
					'app_settings' => $_SESSION['user']['app_settings'],
					'cached_identity' => hash('sha256', $_SESSION['user']['id']),
					'permissions' => [
						'orderprocessing' => PERMISSION::permissionFor('orderprocessing')
					]
				],
				'config' => [
					'application' => [
						'order_gtin_barcode' => CONFIG['application']['order_gtin_barcode']
					],
					'lifespan' => [
						'idle' => min(CONFIG['lifespan']['idle'], ini_get('session.gc_maxlifetime')),
					],
					'limits' => [
						'qr_errorlevel' => CONFIG['limits']['qr_errorlevel']
					],
					'label' => CONFIG['label']
				]]);
			}
			// select single user based on token
			$query = SQLQUERY::EXECUTE($this->_pdo, 'application_login', [
				'values' => [
					':token' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.login'))
				]
			]);
			if ($query && UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.terms_of_service_accepted'))){
				$result = $query[0];
				$_SESSION['user'] = $result;
				$_SESSION['user']['permissions'] = explode(',', $result['permissions']);
				$_SESSION['user']['units'] = explode(',', $result['units']);
				$_SESSION['user']['app_settings'] = $result['app_settings'] ? json_decode($result['app_settings'], true) : [];
				$_SESSION['user']['image'] = './' . $result['image'];
				$this->response([
					'user' => [
					'image' => $_SESSION['user']['image'],
					'app_settings' => $_SESSION['user']['app_settings'],
					'cached_identity' => hash('sha256', $_SESSION['user']['id']),
					'permissions' => [
						'orderprocessing' => PERMISSION::permissionFor('orderprocessing')
					]
				],
				'config' => [
					'application' => [
						'order_gtin_barcode' => CONFIG['application']['order_gtin_barcode']
					],
					'lifespan' => [
						'idle' => min(CONFIG['lifespan']['idle'], ini_get('session.gc_maxlifetime')),
					],
					'limits' => [
						'qr_errorlevel' => CONFIG['limits']['qr_errorlevel']
					],
					'label' => CONFIG['label']
				]]);
			}
		}
		session_unset();
		session_destroy();
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
			'config' => []
		];
		$tos = [];
		$replacements = [
			':issue_mail' => CONFIG['application']['issue_mail'],
			// no use of PERMISSIONS::permissionFor, because this method required a logged in user
			':permissions' => implode(', ', array_map(fn($v) => $this->_lang->_USER['permissions'][$v], ['admin', ...preg_split('/\W+/', CONFIG['permissions']['users'])]))
		];
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
		$result = [
			'user' => $_SESSION['user']['name'],
			'render' => ['content' => []]
		];
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$permissions = [];
				$entry = [
					'title' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.title')),
					'content' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.content')),
					'permissions' => '',
				];
		
				foreach(CONFIG['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $entry['title'], $matches)) $this->response(['response' => ['msg' => $this->_lang->GET('application.manual.forbidden_name', [':name' => $entry['title']]), 'type' => 'error']]);
				}
		
				// chain checked permission levels
				foreach($this->_lang->_USER['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, str_replace(' ', '_', $description))) {
						$permissions[] = $level;
					}
				}
				$entry['permissions'] = implode(',', $permissions);

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
				$permissions = [];
				$entry = [
					'title' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.title')),
					'content' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.content')),
					'permissions' => '',
				];
		
				foreach(CONFIG['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $entry['title'], $matches)) $this->response(['response' => ['msg' => $this->_lang->GET('application.manual.forbidden_name', [':name' => $entry['title']]), 'type' => 'error']]);
				}
		
				// chain checked permission levels
				foreach($this->_lang->_USER['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, str_replace(' ', '_', $description))) {
						$permissions[] = $level;
					}
				}
				$entry['permissions'] = implode(',', $permissions);

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
				if (!$query) $entry =[
					'id' => null,
					'title' => '',
					'content' => '',
					'permissions' => ''
				];
				else $entry = $query[0];

				$result['render']['form'] = [
					'data-usecase' => 'manual',
					'action' => "javascript:api.application('" . ($entry['id'] ? 'put' : 'post') . "', 'manual'" . ($entry['id'] ? ", " . $entry['id'] : '') . ")"];

				$query = SQLQUERY::EXECUTE($this->_pdo, 'application_get_manual');
				$options = ['...' . $this->_lang->GET('application.manual.new_topic') => (!$this->_requestedManual || $this->_requestedManual === '...' . $this->_lang->GET('application.manual.new_topic')) ? ['selected' => true] : []];
				foreach ($query as $row){
					$options[$row['title']] = ['value' => $row['id']];
					if ($entry['id'] === $row['id']) $options[$row['title']]['selected'] = true; 
				}
				ksort($options);

				$permissions = [];
				foreach($this->_lang->_USER['permissions'] as $level => $description){
					$permissions[$description] = in_array($level, explode(',', $entry['permissions'])) ? ['checked' => true] : [];
				}

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
				if ($entry['id']) $result['render']['content'][] = [
						[
							'type' => 'deletebutton',
							'attributes' => [
								'value' => $this->_lang->GET('application.manual.delete'),
								'type' => 'button',
								'onpointerup' => "new Dialog({type: 'confirm', header: '". $this->_lang->GET('application.manual.delete_confirm') ."', options: {".
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
	 * respond with menu taking user permissions into account
	 */
	public function menu(){
		// get permission based menu items
		if (!isset($_SESSION['user'])) $this->response(['body' => [$this->_lang->GET('menu.application.header') => [$this->_lang->GET('menu.application.signin') => []]]]);			
		$menu = [
			$this->_lang->GET('menu.communication.header') => [
				$this->_lang->GET('menu.communication.conversations') => ['onpointerup' => "api.message('get', 'conversation')", 'data-unreadmessages' => '0'],
				$this->_lang->GET('menu.communication.register') => ['onpointerup' => "api.message('get', 'register')"],
				$this->_lang->GET('menu.communication.texttemplate_texts') => ['onpointerup' => "api.texttemplate('get', 'text')"],
			],
			$this->_lang->GET('menu.records.header') => [
				$this->_lang->GET('menu.records.records_create_identifier') => ['onpointerup' => "api.record('get', 'identifier')"],
				$this->_lang->GET('menu.records.records_summary') => ['onpointerup' => "api.record('get', 'records')"]
			],
			$this->_lang->GET('menu.calendar.header') => [
				$this->_lang->GET('menu.calendar.scheduling') => ['onpointerup' => "api.calendar('get', 'schedule')"]
			],
			$this->_lang->GET('menu.application.header') => [
				$this->_lang->GET('menu.application.signout_user', [':name' => $_SESSION['user']['name']]) => ['onpointerup' => "api.application('post', 'login', 'logout')"],
				$this->_lang->GET('menu.application.start') => ['onpointerup' => "api.application('get', 'start')"],			
				$this->_lang->GET('menu.application.user_profile') => ['onpointerup' => "api.user('get', 'profile')"],			
			],
			$this->_lang->GET('menu.files.header') => [
				$this->_lang->GET('menu.files.files') => ['onpointerup' => "api.file('get', 'files')"],
				$this->_lang->GET('menu.files.bundles') => ['onpointerup' => "api.file('get', 'bundle')"],
				$this->_lang->GET('menu.files.sharepoint') => ['onpointerup' => "api.file('get', 'sharepoint')"],
			],
			$this->_lang->GET('menu.purchase.header') => [
				$this->_lang->GET('menu.purchase.order') => ['onpointerup' => "api.purchase('get', 'order')"],
				$this->_lang->GET('menu.purchase.prepared_orders') => ['onpointerup' => "api.purchase('get', 'prepared')"],
				$this->_lang->GET('menu.purchase.approved_orders') => ['onpointerup' => "api.purchase('get', 'approved')"],
				$this->_lang->GET('menu.purchase.vendor') => ['onpointerup' => "api.purchase('get', 'vendor')"],
				$this->_lang->GET('menu.purchase.product') => ['onpointerup' => "api.purchase('get', 'product')"],
			],
			$this->_lang->GET('menu.tools.header') => [
				$this->_lang->GET('menu.tools.digital_codes') => ['onpointerup' => "api.tool('get', 'code')"],
				$this->_lang->GET('menu.tools.scanner') => ['onpointerup' => "api.tool('get', 'scanner')"],
				$this->_lang->GET('menu.tools.stl_viewer') => ['onpointerup' => "api.tool('get', 'stlviewer')"],
				$this->_lang->GET('menu.tools.calculator') => ['onpointerup' => "api.tool('get', 'calculator')"],
				$this->_lang->GET('menu.tools.image') => ['onpointerup' => "api.tool('get', 'image')"],
			],
		];
		if (!array_intersect(['group'], $_SESSION['user']['permissions'])) $menu[$this->_lang->GET('menu.records.header')][$this->_lang->GET('menu.records.records_bundles')] = ['onpointerup' => "api.document('get', 'bundles')"];
		if (!array_intersect(['group'], $_SESSION['user']['permissions'])) $menu[$this->_lang->GET('menu.records.header')][$this->_lang->GET('menu.records.records_record')] = ['onpointerup' => "api.document('get', 'documents')"];
		// make sure risk management comes after documents 
		$menu[$this->_lang->GET('menu.records.header')][$this->_lang->GET('menu.records.risk_management')] = ['onpointerup' => "api.risk('get', 'risk')"];
		if (!array_intersect(['group'], $_SESSION['user']['permissions']) && isset($_SESSION['user']['app_settings']['weeklyhours']))
			$menu[$this->_lang->GET('menu.calendar.header')][$this->_lang->GET('menu.calendar.timesheet')] = ['onpointerup' => "api.calendar('get', 'timesheet')"];

		if (PERMISSION::permissionFor('files')) $menu[$this->_lang->GET('menu.files.header')][$this->_lang->GET('menu.files.file_manager')] = ['onpointerup' => "api.file('get', 'filemanager')"];
		if (PERMISSION::permissionFor('externaldocuments')) $menu[$this->_lang->GET('menu.files.header')][$this->_lang->GET('menu.files.external_file_manager')] = ['onpointerup' => "api.file('get', 'externalfilemanager')"];
		if (PERMISSION::permissionFor('documentcomposer')){
			$menu[$this->_lang->GET('menu.records.header')][$this->_lang->GET('menu.records.documents_manage_components')] = ['onpointerup' => "api.document('get', 'component_editor')"];
			$menu[$this->_lang->GET('menu.records.header')][$this->_lang->GET('menu.records.documents_manage_documents')] = ['onpointerup' => "api.document('get', 'document_editor')"];
			$menu[$this->_lang->GET('menu.records.header')][$this->_lang->GET('menu.records.documents_manage_bundles')] = ['onpointerup' => "api.document('get', 'bundle')"];
		}
		if (PERMISSION::permissionFor('filebundles')) $menu[$this->_lang->GET('menu.files.header')][$this->_lang->GET('menu.files.bundle_manager')] = ['onpointerup' => "api.file('get', 'bundlemanager')"];
		if (PERMISSION::permissionFor('users')) $menu[$this->_lang->GET('menu.application.header')][$this->_lang->GET('menu.application.user_manager')] =['onpointerup' => "api.user('get', 'user')"];
		if (PERMISSION::permissionFor('texttemplates')) {
			$menu[$this->_lang->GET('menu.communication.header')][$this->_lang->GET('menu.communication.texttemplate_chunks')] =['onpointerup' => "api.texttemplate('get', 'chunk')"];
			$menu[$this->_lang->GET('menu.communication.header')][$this->_lang->GET('menu.communication.texttemplate_templates')] =['onpointerup' => "api.texttemplate('get', 'template')"];
		}
		if (PERMISSION::permissionFor('audits')) $menu[$this->_lang->GET('menu.tools.header')][$this->_lang->GET('menu.tools.audit')] =['onpointerup' => "api.audit('get', 'checks')"];
		if (PERMISSION::permissionFor('csvfilter')) $menu[$this->_lang->GET('menu.tools.header')][$this->_lang->GET('menu.tools.csvfilter_filter')] =['onpointerup' => "api.csvfilter('get', 'filter')"];
		if (PERMISSION::permissionFor('documentapproval'))$menu[$this->_lang->GET('menu.records.header')][$this->_lang->GET('menu.records.documents_manage_approval')] = ['onpointerup' => "api.document('get', 'approval')"];
		if (PERMISSION::permissionFor('appmanual')) $menu[$this->_lang->GET('menu.application.header')][$this->_lang->GET('menu.application.manual_manager')] =['onpointerup' => "api.application('get', 'manual')"];
		if (PERMISSION::permissionFor('csvrules')) $menu[$this->_lang->GET('menu.tools.header')][$this->_lang->GET('menu.tools.csvfilter_filter_manager')] =['onpointerup' => "api.csvfilter('get', 'rule')"];
		if (PERMISSION::permissionFor('audits')) $menu[$this->_lang->GET('menu.purchase.header')][$this->_lang->GET('menu.purchase.incorporated_pending')] =['onpointerup' => "api.purchase('get', 'pendingincorporations')"];

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
		$tiles = [];

		$notifications = new NOTIFICATION;

		// messages
		$unseen = $notifications->messageunseen();
		if ($unseen) {
			$tiles[] = [
				'type' => 'tile',
				'attributes' => [
					'onpointerup' => "api.message('get', 'conversation')",
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
						'onpointerup' => "api.purchase('get', 'approved')",
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
						'onpointerup' => "api.purchase('get', 'prepared')",
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
					'onpointerup' => "api.record('get', 'records')",
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

		// unapproved documents and components
		$unapproved = $notifications->documents();
		if ($unapproved){
			$tiles[] = [
				'type' => 'tile',
				'attributes' => [
					'onpointerup' => "api.document('get', 'approval')",
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
					'onpointerup' => "api.purchase('get', 'pendingincorporations')",
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
					'onpointerup' => "api.audit('get', 'checks', 'complaints')",
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
			if ($records = $search->recordsearch(['search' => $this->_search])){
				$matches = [];
				foreach ($records as $contextkey => $context){
					foreach($context as $record){
						$display = $this->_lang->GET('record.record_list_touched', [
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

			if ($documents = $search->documentsearch(['search' => $this->_search])){
				$matches = [];
				foreach ($documents as $document){
					$matches[$document['name']] = ['href' => 'javascript:void(0);', 'onpointerup' => "api.record('get', 'document', '" . $document['name'] . "')"];
				}
				$searchelements[] = [
					'type' => 'links',
					'description' => $this->_lang->GET('menu.records.records_record'),
					'content' => $matches
				];
			}

			if ($files = $search->filesearch(['search' => $this->_search])){
				$matches = [];
				foreach ($files as $file){
					$matches[preg_replace('/.+fileserver\//','', $file)] = ['href' => substr($file, 1), 'target' => '_blank'];
				}
				$searchelements[] = [
					'type' => 'links',
					'description' => $this->_lang->GET('menu.files.header'),
					'content' => $matches
				];
			}

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
		$overview[] = [
			'type' => 'calendar',
			'description' => $week['header'],
			'content' => $week['content'],
			'api' => 'schedule'
		];

		$displayevents = $displayabsentmates = '';
		$today = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
		$thisDaysEvents = $calendar->getDay($today->format('Y-m-d'));
		foreach ($thisDaysEvents as $row){
			if (!$row['affected_user']) $row['affected_user'] = $this->_lang->GET('message.deleted_user');
			if ($row['type'] === 'schedule' && (array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) || array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])) && !$row['closed']) $displayevents .= "* " . $row['subject'] . ($row['affected_user'] !== $this->_lang->GET('message.deleted_user') ? ' (' . $row['affected_user'] . ')': '') . "\n";
			if ($row['type'] === 'timesheet' && !in_array($row['subject'], CONFIG['calendar']['hide_offduty_reasons']) && array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])) $displayabsentmates .= "* " . $row['affected_user'] . " ". $this->_lang->_USER['calendar']['timesheet']['pto'][$row['subject']] . " ". substr($row['span_start'], 0, 10) . " - ". substr($row['span_end'], 0, 10) . "\n";
		}
		if ($displayevents) $overview[] = [
			'type' => 'textsection',
			'attributes' => [
					'name' => $this->_lang->GET('calendar.events_assigned_units')
			],
			'content' => $displayevents
		];
		if ($displayabsentmates) $overview[] = [
			'type' => 'textsection',
			'attributes' => [
					'name' => $this->_lang->GET('calendar.timesheet.irregular')
			],
			'content' => $displayabsentmates
		];

		$today->modify('-1 day');
		$pastEvents = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
		$uncompleted = [];
		foreach ($pastEvents as $row){
			if (!in_array($row, $thisDaysEvents) && $row['type'] === 'schedule' && array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) && !$row['closed']) $uncompleted[$row['subject'] . " (" . substr($row['span_start'], 0, 10) . ")"] = ['href' => "javascript:api.calendar('get', 'schedule', '" . $row['span_start'] . "', '" . $row['span_start'] . "')"];
		}
		if ($uncompleted) $overview[] = [
			'type' => 'links',
			'description' => $this->_lang->GET('calendar.events_assigned_units_uncompleted'),
			'content' => $uncompleted
		];

		if ($overview) $result['render']['content'][] = $overview;

		// manual
		$query = SQLQUERY::EXECUTE($this->_pdo, 'application_get_manual');
		$topics = [];
		foreach ($query as $row){
			if (PERMISSION::permissionIn($row['permissions'])) $topics[]=
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
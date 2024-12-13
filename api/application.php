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

	/**
	 * init parent class and set private requests
	 */
	public function __construct(){
		parent::__construct();
		$this->_requestedLogout = $this->_requestedManual = isset(REQUEST[2]) ? REQUEST[2] : null;
	}

	/**
	 *   _
	 *  | |___ ___ ___ _ _ ___ ___ ___
	 *  | | .'|   | . | | | .'| . | -_|
	 *  |_|__,|_|_|_  |___|__,|_  |___|
	 *            |___|       |___|
	 * respond with constant LANGUAGEFILE as transfer to js frontend
	 */
    public function language(){
		$this->response(['data' => LANG::GETALL()]);
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
			if (!UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.login_description')) && isset($_SESSION['user'])){
				$this->response(['user' => [
					'image' => $_SESSION['user']['image'],
					'app_settings' => $_SESSION['user']['app_settings'],
					'cached_identity' => hash('sha256', $_SESSION['user']['id']),
					'permissions' => [
						'orderprocessing' => PERMISSION::permissionFor('orderprocessing')
					]
				],
				'config' => [
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
					':token' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.login_description'))
				]
			]);
			if ($query && UTILITY::propertySet($this->_payload, LANG::PROPERTY('application.terms_of_service_accepted'))){
				$result = $query[0];
				$_SESSION['user'] = $result;
				$_SESSION['user']['permissions'] = explode(',', $result['permissions']);
				$_SESSION['user']['units'] = explode(',', $result['units']);
				$_SESSION['user']['app_settings'] = $result['app_settings'] ? json_decode($result['app_settings'], true) : [];
				$_SESSION['user']['image'] = './' . $result['image'];
				$this->response(['user' => [
					'image' => $_SESSION['user']['image'],
					'app_settings' => $_SESSION['user']['app_settings'],
					'cached_identity' => hash('sha256', $_SESSION['user']['id']),
					'permissions' => [
						'orderprocessing' => PERMISSION::permissionFor('orderprocessing')
					]
					],
					'config' => [
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
								'name' => LANG::GET('user.login_description', [], true),
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
			':permissions' => implode(', ', array_map(fn($v) => LANGUAGEFILE['permissions'][$v], ['admin', ...preg_split('/\W+/', CONFIG['permissions']['users'])]))
		];
		foreach (LANGUAGEFILE['application']['terms_of_service'] as $description => $content){
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
					LANG::GET('application.terms_of_service_accepted', [], true) => ['required' => true]
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
					'title' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('application.edit_manual_title')),
					'content' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('application.edit_manual_content')),
					'permissions' => '',
				];
		
				foreach(CONFIG['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $entry['title'], $matches)) $this->response(['response' => ['msg' => LANG::GET('application.edit_manual_forbidden_name', [':name' => $entry['title']]), 'type' => 'error']]);
				}
		
				// chain checked permission levels
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
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
						'msg' => LANG::GET('application.edit_manual_saved', [':name' => $entry['title']]),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => false,
						'name' => LANG::GET('application.edit_manual_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				$permissions = [];
				$entry = [
					'title' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('application.edit_manual_title')),
					'content' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('application.edit_manual_content')),
					'permissions' => '',
				];
		
				foreach(CONFIG['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $entry['title'], $matches)) $this->response(['response' => ['msg' => LANG::GET('application.edit_manual_forbidden_name', [':name' => $entry['title']]), 'type' => 'error']]);
				}
		
				// chain checked permission levels
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
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
						'msg' => LANG::GET('application.edit_manual_saved', [':name' => $entry['title']]),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => false,
						'name' => LANG::GET('application.edit_manual_not_saved'),
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
				$options = ['...' . LANG::GET('application.edit_new_manual_topic') => (!$this->_requestedManual || $this->_requestedManual === '...' . LANG::GET('application.edit_new_manual_topic')) ? ['selected' => true] : []];
				foreach ($query as $row){
					$options[$row['title']] = ['value' => $row['id']];
					if ($entry['id'] === $row['id']) $options[$row['title']]['selected'] = true; 
				}
				ksort($options);

				$permissions = [];
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					$permissions[$description] = in_array($level, explode(',', $entry['permissions'])) ? ['checked' => true] : [];
				}

				$result['render']['content'] = [
					[
						[
							'type' => 'select',
							'attributes' => [
								'name' => LANG::GET('application.edit_select_manual_topic'),
								'onchange' => "api.application('get', 'manual', this.value)"
							],
							'content' => $options
						],
						[
							'type' => 'text',
							'attributes' => [
								'name' => LANG::GET('application.edit_manual_title'),
								'value' => $entry['title'],
							]
						],
						[
							'type' => 'textarea',
							'attributes' => [
								'rows' => 8,
								'name' => LANG::GET('application.edit_manual_content'),
								'value' => $entry['content']
							]
						],
						[
							'type' => 'checkbox',
							'attributes' => [
								'name' => LANG::GET('application.edit_manual_permissions')
							],
							'content' => $permissions
						]
					]
				];
				if ($entry['id']) $result['render']['content'][] = [
						[
							'type' => 'deletebutton',
							'attributes' => [
								'value' => LANG::GET('application.edit_manual_delete'),
								'type' => 'button',
								'onpointerup' => "new Dialog({type: 'confirm', header: '". LANG::GET('application.edit_manual_delete_confirm') ."', options: {".
								"'".LANG::GET('general.cancel_button')."': false,".
								"'".LANG::GET('general.ok_button')."': {value: true, class: 'reducedCTA'}".
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
						'msg' => LANG::GET('application.edit_manual_deleted'),
						'id' => false,
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => LANG::GET('application.edit_manual_error'),
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
		if (!isset($_SESSION['user'])) $this->response(['body' => [LANG::GET('menu.application_header') => [LANG::GET('menu.application_signin') => []]]]);			
		$menu = [
			LANG::GET('menu.communication_header') => [
				LANG::GET('menu.message_conversations') => ['onpointerup' => "api.message('get', 'conversation')", 'data-unreadmessages' => '0'],
				LANG::GET('menu.message_register') => ['onpointerup' => "api.message('get', 'register')"],
				LANG::GET('menu.texttemplate_texts') => ['onpointerup' => "api.texttemplate('get', 'text')"],
			],
			LANG::GET('menu.record_header') => [
				LANG::GET('menu.record_create_identifier') => ['onpointerup' => "api.record('get', 'identifier')"],
				LANG::GET('menu.record_summary') => ['onpointerup' => "api.record('get', 'records')"]
			],
			LANG::GET('menu.calendar_header') => [
				LANG::GET('menu.calendar_scheduling') => ['onpointerup' => "api.calendar('get', 'schedule')"]
			],
			LANG::GET('menu.application_header') => [
				LANG::GET('menu.application_signout_user', [':name' => $_SESSION['user']['name']]) => ['onpointerup' => "api.application('post', 'login', 'logout')"],
				LANG::GET('menu.application_start') => ['onpointerup' => "api.application('get', 'start')"],			
				LANG::GET('menu.application_user_profile') => ['onpointerup' => "api.user('get', 'profile')"],			
			],
			LANG::GET('menu.files_header') => [
				LANG::GET('menu.files_files') => ['onpointerup' => "api.file('get', 'files')"],
				LANG::GET('menu.files_bundles') => ['onpointerup' => "api.file('get', 'bundle')"],
				LANG::GET('menu.files_sharepoint') => ['onpointerup' => "api.file('get', 'sharepoint')"],
			],
			LANG::GET('menu.purchase_header') => [
				LANG::GET('menu.purchase_order') => ['onpointerup' => "api.purchase('get', 'order')"],
				LANG::GET('menu.purchase_prepared_orders') => ['onpointerup' => "api.purchase('get', 'prepared')"],
				LANG::GET('menu.purchase_approved_orders') => ['onpointerup' => "api.purchase('get', 'approved')"],
				LANG::GET('menu.purchase_vendor') => ['onpointerup' => "api.purchase('get', 'vendor')"],
				LANG::GET('menu.purchase_product') => ['onpointerup' => "api.purchase('get', 'product')"],
			],
			LANG::GET('menu.tools_header') => [
				LANG::GET('menu.tools_digital_codes') => ['onpointerup' => "api.tool('get', 'code')"],
				LANG::GET('menu.tools_scanner') => ['onpointerup' => "api.tool('get', 'scanner')"],
				LANG::GET('menu.tools_stl_viewer') => ['onpointerup' => "api.tool('get', 'stlviewer')"],
				LANG::GET('menu.tools_calculator') => ['onpointerup' => "api.tool('get', 'calculator')"],
				LANG::GET('menu.tools_image') => ['onpointerup' => "api.tool('get', 'image')"],
			],
		];
		if (!array_intersect(['group'], $_SESSION['user']['permissions'])) $menu[LANG::GET('menu.record_header')][LANG::GET('menu.record_bundles')] = ['onpointerup' => "api.form('get', 'bundles')"];
		if (!array_intersect(['group'], $_SESSION['user']['permissions'])) $menu[LANG::GET('menu.record_header')][LANG::GET('menu.record_record')] = ['onpointerup' => "api.form('get', 'forms')"];
		// make sure risk management comes after forms 
		$menu[LANG::GET('menu.record_header')][LANG::GET('menu.risk_management')] = ['onpointerup' => "api.risk('get', 'risk')"];
		if (!array_intersect(['group'], $_SESSION['user']['permissions']) && isset($_SESSION['user']['app_settings']['weeklyhours']))
			$menu[LANG::GET('menu.calendar_header')][LANG::GET('menu.calendar_timesheet')] = ['onpointerup' => "api.calendar('get', 'timesheet')"];

		if (PERMISSION::permissionFor('files')) $menu[LANG::GET('menu.files_header')][LANG::GET('menu.files_file_manager')] = ['onpointerup' => "api.file('get', 'filemanager')"];
		if (PERMISSION::permissionFor('externaldocuments')) $menu[LANG::GET('menu.files_header')][LANG::GET('menu.files_external_file_manager')] = ['onpointerup' => "api.file('get', 'externalfilemanager')"];
		if (PERMISSION::permissionFor('formcomposer')){
			$menu[LANG::GET('menu.record_header')][LANG::GET('menu.forms_manage_components')] = ['onpointerup' => "api.form('get', 'component_editor')"];
			$menu[LANG::GET('menu.record_header')][LANG::GET('menu.forms_manage_forms')] = ['onpointerup' => "api.form('get', 'form_editor')"];
			$menu[LANG::GET('menu.record_header')][LANG::GET('menu.forms_manage_bundles')] = ['onpointerup' => "api.form('get', 'bundle')"];
		}
		if (PERMISSION::permissionFor('filebundles')) $menu[LANG::GET('menu.files_header')][LANG::GET('menu.files_bundle_manager')] = ['onpointerup' => "api.file('get', 'bundlemanager')"];
		if (PERMISSION::permissionFor('users')) $menu[LANG::GET('menu.application_header')][LANG::GET('menu.application_user_manager')] =['onpointerup' => "api.user('get', 'user')"];
		if (PERMISSION::permissionFor('texttemplates')) {
			$menu[LANG::GET('menu.communication_header')][LANG::GET('menu.texttemplate_chunks')] =['onpointerup' => "api.texttemplate('get', 'chunk')"];
			$menu[LANG::GET('menu.communication_header')][LANG::GET('menu.texttemplate_templates')] =['onpointerup' => "api.texttemplate('get', 'template')"];
		}
		if (PERMISSION::permissionFor('audits')) $menu[LANG::GET('menu.tools_header')][LANG::GET('menu.audit')] =['onpointerup' => "api.audit('get', 'checks')"];
		if (PERMISSION::permissionFor('csvfilter')) $menu[LANG::GET('menu.tools_header')][LANG::GET('menu.csvfilter_filter')] =['onpointerup' => "api.csvfilter('get', 'filter')"];
		if (PERMISSION::permissionFor('formapproval'))$menu[LANG::GET('menu.record_header')][LANG::GET('menu.forms_manage_approval')] = ['onpointerup' => "api.form('get', 'approval')"];
		if (PERMISSION::permissionFor('appmanual')) $menu[LANG::GET('menu.application_header')][LANG::GET('menu.application_manual_manager')] =['onpointerup' => "api.application('get', 'manual')"];
		if (PERMISSION::permissionFor('csvrules')) $menu[LANG::GET('menu.tools_header')][LANG::GET('menu.csvfilter_filter_manager')] =['onpointerup' => "api.csvfilter('get', 'rule')"];
		if (PERMISSION::permissionFor('audits')) $menu[LANG::GET('menu.purchase_header')][LANG::GET('menu.purchase_incorporated_pending')] =['onpointerup' => "api.purchase('get', 'pendingincorporations')"];

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
						'content' => LANG::GET('application.overview_messages', [':number' => $unseen]),
						'attributes' => [
							'data-type' => 'message',
							'name' => LANG::GET('menu.message_conversations')
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
							'content' => LANG::GET('application.overview_orders', [':number' => $unprocessed]),
							'attributes' => [
								'data-type' => 'purchase',
								'name' => LANG::GET('menu.purchase_approved_orders')
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
						'content' => LANG::GET('application.overview_cases', [':number' => $number]),
						'attributes' => [
							'data-type' => 'record',
							'name' => LANG::GET('menu.record_header')
						]
					]
				]
			];
		}

		// unapproved forms and components
		$unapproved = $notifications->forms();
		if ($unapproved){
			$tiles[] = [
				'type' => 'tile',
				'attributes' => [
					'onpointerup' => "api.form('get', 'approval')",
				],
				'content' => [
					[
						'type' => 'textsection',
						'content' => LANG::GET('assemble.approve_landing_page', [':number' => $unapproved]),
						'attributes' => [
							'data-type' => 'record',
							'name' => LANG::GET('menu.forms_manage_approval')
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
						'content' => LANG::GET('consumables.approve_landing_page', [':number' => $unapproved]),
						'attributes' => [
							'data-type' => 'purchase',
							'name' => LANG::GET('menu.purchase_incorporated_pending')
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
						'content' => LANG::GET('record.record_complaints_landing_page', [':number' => $complaints]),
						'attributes' => [
							'data-type' => 'record',
							'name' => LANG::GET('menu.record_header')
						]
					]
				]
			];
		}
		if (count($tiles)) $result['render']['content'][] = $tiles;

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
			if (!$row['affected_user']) $row['affected_user'] = LANG::GET('message.deleted_user');
			if ($row['type'] === 'schedule' && (array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) || array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])) && !$row['closed']) $displayevents .= "* " . $row['subject'] . ($row['affected_user'] !== LANG::GET('message.deleted_user') ? ' (' . $row['affected_user'] . ')': '') . "\n";
			if ($row['type'] === 'timesheet' && !in_array($row['subject'], CONFIG['calendar']['hide_offduty_reasons']) && array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])) $displayabsentmates .= "* " . $row['affected_user'] . " ". LANGUAGEFILE['calendar']['timesheet_pto'][$row['subject']] . " ". substr($row['span_start'], 0, 10) . " - ". substr($row['span_end'], 0, 10) . "\n";
		}
		if ($displayevents) $overview[] = [
			'type' => 'textsection',
			'attributes' => [
					'name' => LANG::GET('calendar.events_assigned_units')
			],
			'content' => $displayevents
		];
		if ($displayabsentmates) $overview[] = [
			'type' => 'textsection',
			'attributes' => [
					'name' => LANG::GET('calendar.timesheet_irregular')
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
			'description' => LANG::GET('calendar.events_assigned_units_uncompleted'),
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
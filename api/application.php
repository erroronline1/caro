<?php
// login handler, menu and landing page methods
class APPLICATION extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
    private $_requestedToken = null;
    private $_requestedManual = null;

	/**
	 * init parent class and set private requests
	 */
	public function __construct(){
		parent::__construct();
		$this->_requestedToken = $this->_requestedManual = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	/**
	 * log in user or destroy session
	 * without current user respond with login form
	 */
	public function login(){
		// select single user based on token
		if (!boolval($this->_requestedToken) && array_key_exists('user', $_SESSION) && $_SESSION['user']){
			$this->response(['body' => $_SESSION['user']]);
		}
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('application_login'));
		$statement->execute([
			':token' => $this->_requestedToken
		]);
		
		if ($result = $statement->fetch(PDO::FETCH_ASSOC)){
			$_SESSION['user'] = [
				'name' => $result['name'],
				'permissions' => explode(',', $result['permissions']),
				'units' => explode(',', $result['units']),
				'image' => './' . $result['image'],
				'id' => $result['id'],
				'orderauth' => boolval($result['orderauth']),
				'app_settings' => json_decode($result['app_settings'], true)
			];
			$this->response(['body' => $_SESSION['user']]);
		}
		session_unset();
		session_destroy();
		$this->response(['body' =>
			[
				'form' => [
					'action' => "javascript:api.application('get','login')"
				],
				'content' => [
					[[
						'type' => 'scanner',
						'attributes' => [
                            'data-usecase'=> 'login',
							'name' => LANG::GET('user.login_description'),
							'type' => 'password'
						]
					]]
				]
			]
		]);
	}

	/**
	 * respond with menu taking user permissions into account
	 */
	public function menu(){
		// get permission based menu items
		if (!array_key_exists('user', $_SESSION)) $this->response(['body' => [LANG::GET('menu.application_header') => [LANG::GET('menu.application_signin') => []]]]);			
		$menu=[
			LANG::GET('menu.application_header') => [
				LANG::GET('menu.application_signout_user', [':name' => $_SESSION['user']['name']]) => ['onpointerup' => "api.application('get','login', 'null')"],
				LANG::GET('menu.application_start') => ['onpointerup' => "api.application('get', 'start')"],			
				LANG::GET('menu.application_user_profile') => ['onpointerup' => "api.user('get', 'profile')"],			
			],
			LANG::GET('menu.communication_header') => [
				LANG::GET('menu.message_inbox') => ['onpointerup' => "api.message('get', 'inbox')", 'data-unreadmessages' => '0'],
				LANG::GET('menu.message_new') => ['onpointerup' => "api.message('get', 'message')"],
				LANG::GET('menu.message_sent') => ['onpointerup' => "api.message('get', 'sent')"],
				LANG::GET('menu.texttemplate_texts') => ['onpointerup' => "api.texttemplate('get', 'text')"],
			],
			LANG::GET('menu.record_header') => [
				LANG::GET('menu.record_create_identifier') => ['onpointerup' => "api.record('get', 'identifier')"],
				LANG::GET('menu.record_summary') => ['onpointerup' => "api.record('get', 'records')"]
			],
			LANG::GET('menu.calendar_header') => [
			],
			LANG::GET('menu.files_header') => [
				LANG::GET('menu.files_files') => ['onpointerup' => "api.file('get', 'files')"],
				LANG::GET('menu.files_bundles') => ['onpointerup' => "api.file('get', 'bundle')"],
				LANG::GET('menu.files_sharepoint') => ['onpointerup' => "api.file('get', 'sharepoint')"],
			],
			LANG::GET('menu.purchase_header') => [
				LANG::GET('menu.purchase_order') => ['onpointerup' => "api.purchase('get', 'order')"],
				LANG::GET('menu.purchase_prepared_orders') => ['onpointerup' => "api.purchase('get', 'prepared')"],
				LANG::GET('menu.purchase_approved_orders') => ['onpointerup' => "api.purchase('get', 'approved')"]
			],
			LANG::GET('menu.tools_header') => [
				LANG::GET('menu.tools_digital_codes') => ['onpointerup' => "api.tool('get', 'code')"],
				LANG::GET('menu.tools_scanner') => ['onpointerup' => "api.tool('get', 'scanner')"],
				LANG::GET('menu.tools_stl_viewer') => ['onpointerup' => "api.tool('get', 'stlviewer')"]
			],
		];
		if (array_intersect(['user'], $_SESSION['user']['permissions'])){
			$menu[LANG::GET('menu.record_header')][LANG::GET('menu.record_record')] = ['onpointerup' => "api.record('get', 'forms')"];
		}
		if (array_intersect(['admin', 'ceo', 'qmo'], $_SESSION['user']['permissions'])){
			$menu[LANG::GET('menu.record_header')][LANG::GET('menu.forms_manage_components')] = ['onpointerup' => "api.form('get', 'component_editor')"];
			$menu[LANG::GET('menu.record_header')][LANG::GET('menu.forms_manage_forms')] = ['onpointerup' => "api.form('get', 'form_editor')"];
			$menu[LANG::GET('menu.record_header')][LANG::GET('menu.forms_manage_bundles')] = ['onpointerup' => "api.form('get', 'bundle')"];
			$menu[LANG::GET('menu.files_header')][LANG::GET('menu.files_bundle_manager')] = ['onpointerup' => "api.file('get', 'bundlemanager')"];
			$menu[LANG::GET('menu.application_header')][LANG::GET('menu.application_user_manager')] =['onpointerup' => "api.user('get', 'user')"];
			$menu[LANG::GET('menu.communication_header')][LANG::GET('menu.texttemplate_chunks')] =['onpointerup' => "api.texttemplate('get', 'chunk')"];
			$menu[LANG::GET('menu.communication_header')][LANG::GET('menu.texttemplate_templates')] =['onpointerup' => "api.texttemplate('get', 'template')"];
			$menu[LANG::GET('menu.tools_header')][LANG::GET('menu.audit')] =['onpointerup' => "api.audit('get', 'checks')"];
		}
		if (array_intersect(['admin', 'office', 'ceo', 'qmo'], $_SESSION['user']['permissions'])){
			$menu[LANG::GET('menu.files_header')][LANG::GET('menu.files_file_manager')] = ['onpointerup' => "api.file('get', 'filemanager')"];
		}
		if (array_intersect(['admin', 'purchase', 'ceo', 'qmo'], $_SESSION['user']['permissions'])){
			$menu[LANG::GET('menu.purchase_header')][LANG::GET('menu.purchase_vendor')] = ['onpointerup' => "api.purchase('get', 'vendor')"];
		}
		if (array_intersect(['admin', 'purchase_assistant', 'purchase', 'ceo', 'qmo'], $_SESSION['user']['permissions'])){
			$menu[LANG::GET('menu.purchase_header')][LANG::GET('menu.purchase_product')] = ['onpointerup' => "api.purchase('get', 'product')"];
		}
		if (array_intersect(['admin', 'office', 'purchase', 'ceo', 'qmo'], $_SESSION['user']['permissions'])){
			$menu[LANG::GET('menu.tools_header')][LANG::GET('menu.csvfilter_filter')] =['onpointerup' => "api.csvfilter('get', 'filter')"];
		}
		if (array_intersect(['admin', 'supervisor', 'qmo', 'ceo'], $_SESSION['user']['permissions'])){
			$menu[LANG::GET('menu.record_header')][LANG::GET('menu.forms_manage_approval')] = ['onpointerup' => "api.form('get', 'approval')"];
		}
		if (array_intersect(['admin'], $_SESSION['user']['permissions'])){
			$menu[LANG::GET('menu.application_header')][LANG::GET('menu.application_manual_manager')] =['onpointerup' => "api.application('get', 'manual')"];
			$menu[LANG::GET('menu.tools_header')][LANG::GET('menu.csvfilter_filter_manager')] =['onpointerup' => "api.csvfilter('get', 'rule')"];
		}
		$this->response(['body' => $menu, 'user' => $_SESSION['user']['name']]);
	}

	/**
	 * respond with LANGUAGEFILE as tarnsfer to js frontend
	 */
    public function language(){
		$this->response(['body' => LANG::GETALL()]);
	}

	/**
	 * respond with manual
	 */
	public function start(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		$result = ['user' => $_SESSION['user']['name'], 'body' => ['content' => []]];
		$tiles = [];

		// messages
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_unseen'));
		$statement->execute([
			':user' => $_SESSION['user']['id']
		]);
		$unseen = $statement->fetch(PDO::FETCH_ASSOC);
		if ($unseen['number']) {
			$tiles[] = [
				'type' => 'tile',
				'attributes' => [
					'onpointerup' => "api.message('get', 'inbox')",
				],
				'content' => [
					[
						'type' => 'text',
						'content' => LANG::GET('application.overview_messages', [':number' => $unseen['number']]),
						'description' => LANG::GET('menu.message_inbox'),
						'attributes' => [
							'data-type' => 'message'
						]
					]
				]
			];
		}

		// unprocessed orders
		if (array_intersect(['purchase'], $_SESSION['user']['permissions'])){
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get_approved_unprocessed'));
			$statement->execute();
			$unprocessed = $statement->fetch(PDO::FETCH_ASSOC);
			if ($unprocessed['num']) {
				$tiles[] = [
					'type' => 'tile',
					'attributes' => [
						'onpointerup' => "api.purchase('get', 'approved')",
					],
					'content' => [
						[
							'type' => 'text',
							'content' => LANG::GET('application.overview_orders', [':number' => $unprocessed['num']]),
							'description' => LANG::GET('menu.purchase_approved_orders'),
							'attributes' => [
								'data-type' => 'cart'
							]
						]
					]
				];
			}
		}

		// unclosed case documentation for own unit
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('records_identifiers'));
		$statement->execute();
		$data = $statement->fetchAll(PDO::FETCH_ASSOC);
		$number = 0;
		foreach ($data as $row){
			if ($row['units'] && $row['context'] == 'casedocumentation' && array_intersect(explode(',', $row['units']), $_SESSION['user']['units']) && !$row['closed']) $number++;
		}
		if ($number){
			$tiles[] = [
				'type' => 'tile',
				'attributes' => [
					'onpointerup' => "api.record('get', 'records')",
				],
				'content' => [
					[
						'type' => 'text',
						'content' => LANG::GET('application.overview_cases', [':number' => $number]),
						'description' => LANG::GET('menu.record_header'),
						'attributes' => [
							'data-type' => 'record'
						]
					]
				]
			];
		}

		if (count($tiles)) $result['body']['content'][] = $tiles;

		// manual
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('application_get_manual'));
		$statement->execute();
		$manual = $statement->fetchAll(PDO::FETCH_ASSOC);
		$topics = [];
		foreach ($manual as $row){
			if (array_intersect(explode(',', $row['permissions']), $_SESSION['user']['permissions'])) $topics[]=
				[[
					'type' => 'text',
					'description' => $row['title'],
					'content' => $row['content']
				]];
		}
		$result['body']['content'][] = $topics;
		$this->response($result);
	}

	
	/**
	 * manual edting
	 * POST, PUT and DELETE manual entries or
	 * respond with form to add or edit manual entries 
	 */
	public function manual(){
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
		$result = [
			'user' => $_SESSION['user']['name'],
			'body' => ['content' => []]
		];
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$permissions = [];
				$entry = [
					'title' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('application.edit_manual_title')),
					'content' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('application.edit_manual_content')),
					'permissions' => '',
				];
		
				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $entry['title'], $matches)) $this->response(['status' => ['msg' => LANG::GET('application.edit_manual_forbidden_name', [':name' => $entry['title']]), 'type' => 'error']]);
				}
		
				// chain checked permission levels
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, str_replace(' ', '_', $description))) {
						$permissions[] = $level;
					}
				}
				$entry['permissions'] = implode(',', $permissions);

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('application_post_manual'));
				if ($statement->execute([
					':title' => $entry['title'],
					':content' => $entry['content'],
					':permissions' => $entry['permissions']
				])) $this->response([
					'status' => [
						'id' => $this->_pdo->lastInsertId(),
						'msg' => LANG::GET('application.edit_manual_saved', [':name' => $entry['title']]),
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
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
		
				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $entry['title'], $matches)) $this->response(['status' => ['msg' => LANG::GET('application.edit_manual_forbidden_name', [':name' => $entry['title']]), 'type' => 'error']]);
				}
		
				// chain checked permission levels
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, str_replace(' ', '_', $description))) {
						$permissions[] = $level;
					}
				}
				$entry['permissions'] = implode(',', $permissions);

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('application_put_manual'));
				if ($statement->execute([
					':title' => $entry['title'],
					':content' => $entry['content'],
					':permissions' => $entry['permissions'],
					':id' => $this->_requestedManual
				])) $this->response([
					'status' => [
						'id' => $this->_requestedManual,
						'msg' => LANG::GET('application.edit_manual_saved', [':name' => $entry['title']]),
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'id' => false,
						'name' => LANG::GET('application.edit_manual_not_saved'),
						'type' => 'error'
					]]);

				break;
			case 'GET':
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('application_get_manual-by-id'));
				$statement->execute([':id' => $this->_requestedManual]);
				if (!$entry = $statement->fetch(PDO::FETCH_ASSOC)) $entry=[
					'id' => null,
					'title' => '',
					'content' => '',
					'permissions' => ''
				];
				$result['body']['form'] = [
					'data-usecase' => 'manual',
					'action' => "javascript:api.application('" . ($entry['id'] ? 'put' : 'post') . "', 'manual'" . ($entry['id'] ? ", " . $entry['id'] : '') . ")"];

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('application_get_manual'));
				$statement->execute();
				$manual = $statement->fetchAll(PDO::FETCH_ASSOC);
				$options = ['...' . LANG::GET('application.edit_new_manual_topic') => (!$this->_requestedManual || $this->_requestedManual === '...' . LANG::GET('application.edit_new_manual_topic')) ? ['selected' => true] : []];
				foreach ($manual as $row){
					$options[$row['title']] = ['value' => $row['id']];
					if ($entry['id'] === $row['id']) $options[$row['title']]['selected'] = true; 
				}

				$permissions = [];
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					$permissions[$description] = in_array($level, explode(',', $entry['permissions'])) ? ['checked' => true] : [];
				}

				$result['body']['content'] = [
					[
						['type' => 'select',
						'attributes' => [
							'name' => LANG::GET('application.edit_select_manual_topic'),
							'onchange' => "api.application('get', 'manual', this.value)"
						],
						'content' => $options],
						['type' => 'textinput',
						'attributes' => [
							'name' => LANG::GET('application.edit_manual_title'),
							'value' => $entry['title'],

						]
						],
						['type' => 'textarea',
						'attributes' => [
							'rows' => 8,
							'name' => LANG::GET('application.edit_manual_content'),
							'value' => $entry['content']
						]
						],
						['type' => 'checkbox',
						'description' => LANG::GET('application.edit_manual_permissions'),
						'content' => $permissions
						]
					]
				];
				if ($entry['id']) $result['body']['content'][] = [
						['type' => 'deletebutton',
						'attributes' => [
							'value' => LANG::GET('application.edit_manual_delete'),
							'type' => 'button',
							'onpointerup' => "if (confirm('" . LANG::GET('application.edit_manual_delete_confirm') . "')) api.application('delete', 'manual', " . $entry['id'] . ")"
						]
						]
				];
	
				break;
			case 'DELETE':
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('application_delete_manual'));
				if ($statement->execute([
					':id' => $this->_requestedManual
				])) $this->response([
					'status' => [
						'msg' => LANG::GET('application.edit_manual_deleted'),
						'id' => false,
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'msg' => LANG::GET('application.edit_manual_error'),
						'id' => $this->_requestedManual,
						'type' => 'error'
					]]);
				break;
		}
		$this->response($result);
	}
}

$api = new APPLICATION();
$api->processApi();

exit;
?>
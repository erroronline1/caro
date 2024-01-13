<?php
// add, edit and delete users
class APPLICATION extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
    private $_requestedToken = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedToken = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

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
				'image' => $result['image'],
				'id' => $result['id']
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
						'description' => LANG::GET('user.login_description'),
						'attributes' => [
                            'data-usecase'=> 'login',
							'name' => 'login',
							'type' => 'password'
						]
					]]
				]
			]
		]);
	}

	public function menu(){
		// get permission based menu items
		if (!array_key_exists('user', $_SESSION)) $this->response(['body' => [LANG::GET('menu.signin_header') => []]]);			
		$menu=[
			'logout' => [LANG::GET('menu.signout_user', [':name' => $_SESSION['user']['name']]) => ['href' => "javascript:api.application('get','login', 'null')"]],
			LANG::GET('menu.message_header') => [
				LANG::GET('menu.message_inbox') => ['href' => "javascript:api.message('get', 'inbox')", 'data-unreadmessages' => '0'],
				LANG::GET('menu.message_new') => ['href' => "javascript:api.message('get', 'message')"],
				LANG::GET('menu.message_sent') => ['href' => "javascript:api.message('get', 'sent')"]
			],
			LANG::GET('menu.files_header') => [
				LANG::GET('menu.files_files') => ['href' => "javascript:api.file('get', 'files')"],
				LANG::GET('menu.files_bundles') => ['href' => "javascript:api.file('get', 'bundle')"],
				LANG::GET('menu.files_sharepoint') => ['href' => "javascript:api.file('get', 'sharepoint')"],
			],
			LANG::GET('menu.purchase_header') => [
				LANG::GET('menu.purchase_order') => ['href' => "javascript:api.purchase('get', 'order')"],
				LANG::GET('menu.purchase_prepared_orders') => ['href' => "javascript:api.purchase('get', 'prepared')"],
				LANG::GET('menu.purchase_approved_orders') => ['href' => "javascript:api.purchase('get', 'approved')"]
			],
			LANG::GET('menu.user_header') => [
				LANG::GET('menu.user_profile') => ['href' => "javascript:api.user('get', 'profile')"],
			]
		];
		if (array_intersect(['admin'], $_SESSION['user']['permissions'])){
			$menu[LANG::GET('menu.files_header')][LANG::GET('menu.files_file_manager')] = ['href' => "javascript:api.file('get', 'filemanager')"];
			$menu[LANG::GET('menu.files_header')][LANG::GET('menu.files_bundle_manager')] = ['href' => "javascript:api.file('get', 'bundlemanager')"];
		}
		if (array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions'])){
			$menu[LANG::GET('menu.purchase_header')][LANG::GET('menu.purchase_vendor')] = ['href' => "javascript:api.purchase('get', 'vendor')"];
			$menu[LANG::GET('menu.purchase_header')][LANG::GET('menu.purchase_product')] = ['href' => "javascript:api.purchase('get', 'product')"];
		}
		if (array_intersect(['admin'], $_SESSION['user']['permissions'])){
			$menu[LANG::GET('menu.user_header')][LANG::GET('menu.user_manager')] =['href' => "javascript:api.user('get', 'user')"];
			$menu[LANG::GET('menu.admin_header')] = [
				LANG::GET('menu.admin_form_components') => ['href' => "javascript:api.form('get', 'component_editor')"],
				LANG::GET('menu.admin_forms') => ['href' => "javascript:api.form('get', 'form_editor')"]
			];
		}

		$this->response(['body' => $menu, 'user' => $_SESSION['user']['name']]);
	}

    public function language(){
		$this->response(['body' => LANG::GETALL()]);
	}
}

$api = new APPLICATION();
$api->processApi();

exit;
?>
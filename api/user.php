<?php
// add, edit and delete users
class USERS extends API {

	public function __construct($payload){
		parent::__construct($payload);
	}

	public function user_save(){
		if (!($_SERVER['REQUEST_METHOD'] == 'POST' && in_array('admin', $_SESSION['user']['permissions']))) $this->response([], 401);
		// initialize varaibles
		$permissions = [];
		$this->_payload->id = SQLQUERY::SANITIZE($this->_payload->id);

		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_save-get_by_id'));
		$statement->execute([
			':id' => $this->_payload->id
		]);
		// prepare or create user-array to populate or update
		if (!$user = $statement->fetch(PDO::FETCH_ASSOC)) $user = [
				'id' => 0,
				'name' => $this->_payload->name,
				'permissions' => '',
				'token' => '',
				'image' => ''
			];

		// checkboxes are not delivered if null, html-value 'on' might have to be converted in given db-structure
		// e.g. $this->_payload->active = $this->_payload->active ? 1 : 0;

		$user['name'] = SQLQUERY::SANITIZE($this->_payload->name);
		// chain checked permission levels
		foreach(LANGUAGEFILE['permissions'] as $level => $description){
			if (property_exists($this->_payload, $description)) {
				$permissions[] = $level;
			}
		}
		$user['permissions'] = implode(',', $permissions);
		// generate token
		if($this->_payload->renew_on_save){
			$user['token'] = hash('sha256', $user['name'] . random_int(100000,999999) . time());
		}
		// convert image
		if ($_FILES['photo']['tmp_name']) {
			$user['image'] = 'data:image/png;base64,' . base64_encode(resizeImage($_FILES['photo']['tmp_name'], 128));
		}

		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_save'));
		if ($statement->execute([
			':id' => $user['id'],
			':name' => $user['name'],
			':permissions' => $user['permissions'],
			':token' => $user['token'],
			':image' => $user['image']
		])){
				$result = ['id' => $this->_pdo->lastInsertId() ? : $this->_payload->id, 'name' => scriptFilter($this->_payload->name)];
				$this->response($result);
		}
	}

	public function user_current(){
		if (!($_SERVER['REQUEST_METHOD'] == 'POST')) $this->response([], 401);
		// select single user based on token
		if ((!property_exists($this->_payload, 'login') || !boolval($this->_payload->login)) && $_SESSION['user']){
			$this->response($_SESSION['user']);
		}
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_current'));
		$statement->execute([
			':token' => SQLQUERY::SANITIZE($this->_payload->login)
		]);
		$result = $statement->fetch(PDO::FETCH_ASSOC);
		if ($result['token']){
			$_SESSION['user'] = [
				'name' => $result['name'],
				'permissions' => explode(',', $result['permissions']),
				'image' => $result['image']
			];
			$this->response($_SESSION['user']);
		}
		session_unset();
		session_destroy();
		$this->response(
			[
				'form' => [
					'data-usecase' => 'user_current',
					'action' => 'javascript:api.start()'
				],
				'content' => [
					[[
						'type' => 'hiddeninput',
						'attributes' => [
							'name' => 'request',
							'value' => 'user_current'
						]
					]],
					[[
						'type' => 'qrscanner',
						'description' => LANG::GET('user.login_description'),
						'attributes' => [
							'name' => 'login',
							'type' => 'password'
						]
					]]
				]
			]
		);
	}

	public function user_delete(){
		if (!($_SERVER['REQUEST_METHOD'] == 'DELETE' && in_array('admin', $_SESSION['user']['permissions']))) $this->response([], 401);
		$this->_payload->id = (property_exists($this->_payload, 'id') && boolval($this->_payload->id)) ? SQLQUERY::SANITIZE($this->_payload->id) : '';
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_delete-selected'));
		$statement->execute([
			':id' => $this->_payload->id
		]);
		$user = $statement->fetch(PDO::FETCH_ASSOC);
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_delete'));
		if ($statement->execute([
			':id' => $user['id']
		])) $this->response(['id' => false, 'name' => scriptFilter($user['name'])]);
		else $this->response(['id' => $user['id'], 'name' => scriptFilter($user['name'])]);
	}

	public function user_edit(){
		if (!($_SERVER['REQUEST_METHOD'] == 'GET' && in_array('admin', $_SESSION['user']['permissions']))) $this->response([], 401);
		$this->_payload->id = (property_exists($this->_payload, 'id') && boolval($this->_payload->id)) ? SQLQUERY::SANITIZE($this->_payload->id) : '';
		$datalist=[];
		$options=['...'=>[]];
		
		// prepare existing users lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_edit-datalist'));
		$statement->execute();
		$result = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach($result as $key => $row) {
			$datalist[] = $row['name'];
			$options[$row['name']] = [];
		}

		// select single user based on id or name
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_edit-selected'));
		$statement->execute([
			':id' => $this->_payload->id
		]);
		$result = $statement->fetch(PDO::FETCH_ASSOC);

		// display form for adding a new user with ini related permissions
		$permissions=[];
		foreach(LANGUAGEFILE['permissions'] as $level => $description){
			$permissions[$description] = in_array($level, explode(',', $result['permissions'])) ? ['checked' => true] : [];
		}
		$form=['content' => [
			[
				['type' => 'hiddeninput',
				'attributes' => [
					'name' => 'request',
					'value' => 'user_save'
				]],
				['type' => 'hiddeninput',
				'attributes' => [
					'name' => 'id',
					'value' => $result['id'] ? : $this->_payload->id
				]],
				['type' => 'datalist',
				'content' => $datalist,
				'attributes' => [
					'id' => 'users'
				]]
			],[
				['type' => 'searchinput',
				'description' => LANG::GET('user.edit_existing_users'),
				'attributes' => [
					'placeholder' => LANG::GET('user.edit_existing_users_label'),
					'list' => 'users',
					'onkeypress' => "if (event.key === 'Enter') {api.user('user_edit', this.value); return false;}"
				]],
				['type' => 'select',
				'description' => LANG::GET('user.edit_existing_users'),
				'attributes' => [
					'onchange' => "api.user('user_edit', this.value)"
				],
				'content' => $options]
			],[
				['type' => 'textinput',
				'description' => LANG::GET('user.edit_name'),
				'attributes' => [
					'name' => 'name',
					'required' => true,
					'value' => $result['name'] ? : ''
				]]
			],[
				['type' => 'checkbox',
				'description' => LANG::GET('user.edit_permissions'),
				'content' => $permissions
				]
			],[
				['type' => 'image',
				'description' => LANG::GET('user.edit_export_user_image'),
				'attributes' => [
					'name' => $result['name'],
					'base64img' => $result['image'] ? : '']
				],
				['type' => 'photo',
				'description' => LANG::GET('user.edit_take_photo'),
				'attributes' => [
					'name' => 'photo'
				]],
			],[
				['type' => 'image',
				'description' => LANG::GET('user.edit_export_qr_token'),
				'attributes' => [
					'name' => $result['name'],
					'qrcode' => $result['token']]
				],
				['type' => 'checkbox',
				'description' => LANG::GET('user.edit_token'),
				'content' => [LANG::GET('user.edit_token_renew') => []]
				],
				['type' => 'deletebutton',
				'description' => LANG::GET('user.edit_delete_button'),
				'attributes' => [
					'type'=>'button', // apparently defaults to submit otherwise
					'onpointerdown' => $result['id'] ? 'if (confirm("'. LANG::GET('user.edit_delete_confirm', [':name' => $result['name']]) .'")) {api.user("user_delete", ' . $result['id'] . ')}' : ''
				]]
			]],
			'form' => [
				'data-usecase' => 'user',
				'action' => 'javascript:api.user("user_save")'
			]];
		$this->response($form);
	}

	public function user_menu(){
		if (!($_SERVER['REQUEST_METHOD'] == 'GET')) $this->response([], 401);
		// get permission based menu items
		if (!$_SESSION['user']) $this->response([LANG::GET('menu.signin_header') => []]);
					
		$menu=[
			'logout' => [LANG::GET('menu.signout_user', [':name' => $_SESSION['user']['name']]) => "javascript:api.start('user_current', 'null')"]
		];
		if (in_array('admin', $_SESSION['user']['permissions'])){
			$menu[LANG::GET('menu.admin_header')] = [
				LANG::GET('menu.admin_users') => "javascript:api.user('user_edit')",
				LANG::GET('menu.admin_form_components') => "javascript:api.form('form_components_edit')",
				LANG::GET('menu.admin_forms') => "javascript:api.form('form_edit')"
			];
		}
		$this->response($menu);
	}
}

$api = new USERS($payload);
$api->processApi();

exit;
?>
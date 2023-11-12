<?php
// add, edit and delete users
class USER extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[0]; // method == api request
	private $_requestedUserId = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedUserId = array_key_exists(1, REQUEST) ? REQUEST[1] : null;
	}

	public function user(){
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$permissions = [];
				$user = [
					'name' => $this->_payload->name,
					'permissions' => '',
					'token' => '',
					'image' => ''
				];
		
				// checkboxes are not delivered if null, html-value 'on' might have to be converted in given db-structure
				// e.g. $this->_payload->active = $this->_payload->active ? 1 : 0;
		
				// chain checked permission levels
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, $description)) {
						$permissions[] = $level;
					}
				}
				$user['permissions'] = implode(',', $permissions);
				// generate token
				if($this->_payload->renew_on_save){
					$user['token'] = hash('sha256', $user['name'] . random_int(100000,999999) . time());
				}
				// save and convert image
				if (array_key_exists('photo', $_FILES) && $_FILES['photo']['tmp_name']) {
					$user['image'] = UTILITY::storeUploadedFiles(['photo'], 'files/users', [$user['name']])[0];
					UTILITY::resizeImage($user['image'], 128, UTILITY_IMAGE_REPLACE);
					$user['image'] = 'api/' . $user['image'];
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_post'));
				if ($statement->execute([
					':name' => $user['name'],
					':permissions' => $user['permissions'],
					':token' => $user['token'],
					':image' => $user['image']
				])){
					$this->response(['id' => $this->_pdo->lastInsertId(), 'name' => UTILITY::scriptFilter($this->_payload->name)]);
				}
				break;

			case 'PUT':
				$permissions = [];
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get-id'));
				$statement->execute([
					':id' => $this->_requestedUserId
				]);
				// prepare user-array to update, return error if not found
				if (!$user = $statement->fetch(PDO::FETCH_ASSOC)) $this->response(null, 406);
		
				$user['name']=$this->_payload->name;
				// checkboxes are not delivered if null, html-value 'on' might have to be converted in given db-structure
				// e.g. $this->_payload->active = $this->_payload->active ? 1 : 0;
		
				// chain checked permission levels
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, $description)) {
						$permissions[] = $level;
					}
				}
				$user['permissions'] = implode(',', $permissions);
				// generate token
				if(UTILITY::propertySet($this->_payload, LANG::GET('user.edit_token_renew'))){
					$user['token'] = hash('sha256', $user['name'] . random_int(100000,999999) . time());
				}
				// convert image
				// save and convert image
				if (array_key_exists('photo', $_FILES) && $_FILES['photo']['tmp_name']) {
					$user['image'] = UTILITY::storeUploadedFiles(['photo'], 'files/users', [$user['name']])[0];
					UTILITY::resizeImage($user['image'], 128, UTILITY_IMAGE_REPLACE);
					$user['image'] = 'api/' . $user['image'];
				}
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_put'));
				if ($statement->execute([
					':id' => $user['id'],
					':name' => $user['name'],
					':permissions' => $user['permissions'],
					':token' => $user['token'],
					':image' => $user['image']
				])){
					$this->response(['id' => $user['id'], 'name' => UTILITY::scriptFilter($this->_payload->name)]);
				}
				break;

			case 'GET':
				$datalist=[];
				$options=['...'=>[]];
				
				// prepare existing users lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get-datalist'));
				$statement->execute();
				$user = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($user as $key => $row) {
					$datalist[] = $row['name'];
					$options[$row['name']] = [];
				}
		
				// select single user based on id or name
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get'));
				$statement->execute([
					':id' => $this->_requestedUserId
				]);
				if (!$user = $statement->fetch(PDO::FETCH_ASSOC)){$user = [
					'id' => null,
					'name' => '',
					'permissions' => '',
					'token' => '',
					'image' => ''
				];}
		
				// display form for adding a new user with ini related permissions
				$permissions=[];
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					$permissions[$description] = in_array($level, explode(',', $user['permissions'])) ? ['checked' => true] : [];
				}
				$form=['content' => [
					[
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
							'onkeypress' => "if (event.key === 'Enter') {api.user('get', this.value); return false;}"
						]],
						['type' => 'select',
						'description' => LANG::GET('user.edit_existing_users'),
						'attributes' => [
							'onchange' => "api.user('get', this.value)"
						],
						'content' => $options]
					],[
						['type' => 'textinput',
						'description' => LANG::GET('user.edit_name'),
						'attributes' => [
							'name' => 'name',
							'required' => true,
							'value' => $user['name'] ? : ''
						]]
					],[
						['type' => 'checkbox',
						'description' => LANG::GET('user.edit_permissions'),
						'content' => $permissions
						]
					],[
						['type' => 'photo',
						'description' => LANG::GET('user.edit_take_photo'),
						'attributes' => [
							'name' => 'photo'
						]],
					],[
						['type' => 'checkbox',
						'description' => LANG::GET('user.edit_token'),
						'content' => [LANG::GET('user.edit_token_renew') => []]
						],
						['type' => 'deletebutton',
						'description' => LANG::GET('user.edit_delete_button'),
						'attributes' => [
							'type' => 'button', // apparently defaults to submit otherwise
							'onpointerdown' => $user['id'] ? 'if (confirm("'. LANG::GET('user.edit_delete_confirm', [':name' => $user['name']]) .'")) {api.user("delete", ' . $user['id'] . ')}' : ''
						]]
					]],
					'form' => [
						'data-usecase' => 'user',
						'action' => $user['id'] ? 'javascript:api.user("put", "' . $user['id'] . '")' : 'javascript:api.user("post")'
					]];

					if ($user['image']) array_splice($form['content'][4], 0, 0,
					[
						['type' => 'image',
						'description' => LANG::GET('user.edit_export_user_image'),
						'attributes' => [
							'name' => $user['name'],
							'url' => $user['image']]
						]
					]
					);
					if ($user['token']) array_splice($form['content'][5], 0, 0,
						[
							['type' => 'image',
							'description' => LANG::GET('user.edit_export_qr_token'),
							'attributes' => [
							'name' => $user['name'],
							'qrcode' => $user['token']]
							]
						]
					);

				$this->response($form);
				break;

			case 'DELETE':
				// prefetch to return proper name after deletion
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_delete-prefetch'));
				$statement->execute([
					':id' => $this->_requestedUserId
				]);
				$user = $statement->fetch(PDO::FETCH_ASSOC);

				unlink(preg_replace('/api\//', '', $user['image']));

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_delete'));
				if ($statement->execute([
					':id' => $user['id']
				])) $this->response(['id' => false, 'name' => UTILITY::scriptFilter($user['name'])]);
				else $this->response(['id' => $user['id'], 'name' => UTILITY::scriptFilter($user['name'])]);
				break;
		}
	}
}

$api = new USER();
$api->processApi();

exit;
?>
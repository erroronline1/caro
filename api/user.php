<?php
// add, edit and delete users
class USER extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	public function profile(){
		if (!$_SESSION['user']['id']) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get'));
				$statement->execute([
					':id' => $_SESSION['user']['id']
				]);
				// prepare user-array to update, return error if not found
				if (!$user = $statement->fetch(PDO::FETCH_ASSOC)) $this->response(null, 406);

				// convert image
				// save and convert image
				if (array_key_exists('photo', $_FILES) && $_FILES['photo']['tmp_name']) {
					if ($user['image'] && $user['id'] > 1) UTILITY::delete($user['image']);

					$user['image'] = UTILITY::storeUploadedFiles(['photo'], UTILITY::directory('user_photos'), [$user['name']])[0];
					UTILITY::resizeImage($user['image'], 256, UTILITY_IMAGE_REPLACE);
					$user['image'] = substr($user['image'], 3);
				}
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_put'));
				if ($statement->execute([
					':id' => $user['id'],
					':name' => $user['name'],
					':permissions' => $user['permissions'],
					':units' => $user['units'],
					':token' => $user['token'],
					':image' => $user['image']
				])) $this->response([
					'status' => [
						'id' => $user['id'],
						'msg' => LANG::GET('user.edit_user_saved', [':name' => $user['name']])
					]]);
				else $this->response([
					'status' => [
						'id' => $user['id'],
						'name' => LANG::GET('user.edit_user_not_saved')
					]]);

				break;
			case 'GET':
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get'));
				$statement->execute([
					':id' => $_SESSION['user']['id']
				]);
				// prepare user-array to update, return error if not found
				if (!$user = $statement->fetch(PDO::FETCH_ASSOC)) $this->response(null, 406);
				$permissions = '';
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					$permissions .= in_array($level, explode(',', $user['permissions'])) ? ', ' . $description : '';
				}
				$units = '';
				foreach(LANGUAGEFILE['units'] as $unit => $description){
					$units .= in_array($unit, explode(',', $user['units'])) ? ', ' . $description : '';
				}
				$result['body']=['content' => [
					[
						['type' => 'text',
						'description' => LANG::GET('user.display_user'),
						'content' => LANG::GET('user.edit_name') . ': ' . $user['name'] . "\n" .
							LANG::GET('user.display_permissions') . ': ' . substr($permissions, 2) . "\n" .
							LANG::GET('user.edit_units') . ': ' . substr($units, 2) . "\n"]
					],[
						['type' => 'photo',
						'description' => LANG::GET('user.edit_take_photo'),
						'attributes' => [
							'name' => 'photo'
						]],
					]
					],
					'form' => [
						'data-usecase' => 'user',
						'action' => "javascript:api.user('put', 'profile')"
					]];

					if ($user['image']) array_splice($result['body']['content'][1], 0, 0,
					[
						['type' => 'image',
						'description' => LANG::GET('user.edit_export_user_image'),
						'attributes' => [
							'name' => $user['name'] . '_pic',
							'url' => $user['image']]
						]
					]
					);

				$this->response($result);

				break;
		}
	
	}

	public function user(){
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$permissions = [];
				$units = [];
				$user = [
					'name' => $this->_payload->name,
					'permissions' => '',
					'units' => '',
					'token' => '',
					'image' => ''
				];
		
				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $user['name'], $matches)) $this->response(['status' => ['msg' => LANG::GET('user.error_forbidden_name', [':name' => $user['name']])]]);
				}
		
				// chain checked permission levels
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, str_replace(' ', '_', $description))) {
						$permissions[] = $level;
					}
				}
				$user['permissions'] = implode(',', $permissions);

				// chain checked organizational units
				foreach(LANGUAGEFILE['units'] as $unit => $description){
					if (UTILITY::propertySet($this->_payload, str_replace(' ', '_', $description))) {
						$units[] = $unit;
					}
				}
				$user['units'] = implode(',', $units);

				// generate token
				if(UTILITY::propertySet($this->_payload, str_replace(' ', '_', LANG::GET('user.edit_token_renew')))){
					$user['token'] = hash('sha256', $user['name'] . random_int(100000,999999) . time());
				}

				// save and convert image
				if (array_key_exists('photo', $_FILES) && $_FILES['photo']['tmp_name']) {
					$user['image'] = UTILITY::storeUploadedFiles(['photo'], UTILITY::directory('user_photos'), [$user['name']])[0];
					UTILITY::resizeImage($user['image'], 256, UTILITY_IMAGE_REPLACE);
					$user['image'] = substr($user['image'], 3);
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_post'));
				if ($statement->execute([
					':name' => $user['name'],
					':permissions' => $user['permissions'],
					':units' => $user['units'],
					':token' => $user['token'],
					':image' => $user['image']
				])) $this->response([
					'status' => [
						'id' => $this->_pdo->lastInsertId(),
						'msg' => LANG::GET('user.edit_user_saved', [':name' => $user['name']])
					]]);
				else $this->response([
					'status' => [
						'id' => false,
						'name' => LANG::GET('user.edit_user_not_saved')
					]]);
				break;

			case 'PUT':
				$permissions = [];
				$units = [];
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				// prepare user-array to update, return error if not found
				if (!$user = $statement->fetch(PDO::FETCH_ASSOC)) $this->response(null, 406);
		
				$user['name']=$this->_payload->name;

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $user['name'], $matches)) $this->response(['status' => ['msg' => LANG::GET('user.error_forbidden_name', [':name' => $user['name']])]]);
				}
				
				// chain checked permission levels
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload,  str_replace(' ', '_', $description))) {
						$permissions[] = $level;
					}
				}
				$user['permissions'] = implode(',', $permissions);

				// chain checked organizational units
				foreach(LANGUAGEFILE['units'] as $unit => $description){
					if (UTILITY::propertySet($this->_payload,  str_replace(' ', '_', $description))) {
						$units[] = $unit;
					}
				}
				$user['units'] = implode(',', $units);

				// generate token
				if(UTILITY::propertySet($this->_payload, str_replace(' ', '_', LANG::GET('user.edit_token_renew')))){
					$user['token'] = hash('sha256', $user['name'] . random_int(100000,999999) . time());
				}
				// convert image
				// save and convert image
				if (array_key_exists('photo', $_FILES) && $_FILES['photo']['tmp_name']) {
					if ($user['image'] && $user['id'] > 1) UTILITY::delete($user['image']);

					$user['image'] = UTILITY::storeUploadedFiles(['photo'], UTILITY::directory('user_photos'), [$user['name']])[0];
					UTILITY::resizeImage($user['image'], 256, UTILITY_IMAGE_REPLACE);
					$user['image'] = substr($user['image'], 3);
				}
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_put'));
				if ($statement->execute([
					':id' => $user['id'],
					':name' => $user['name'],
					':permissions' => $user['permissions'],
					':units' => $user['units'],
					':token' => $user['token'],
					':image' => $user['image']
				])) $this->response([
					'status' => [
						'id' => $user['id'],
						'msg' => LANG::GET('user.edit_user_saved', [':name' => $user['name']])
					]]);
				else $this->response([
					'status' => [
						'id' => $user['id'],
						'name' => LANG::GET('user.edit_user_not_saved')
					]]);
				break;

			case 'GET':
				$datalist = [];
				$options = ['...' => []];
				$result = [];
				
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
					':id' => $this->_requestedID
				]);
				if (!$user = $statement->fetch(PDO::FETCH_ASSOC)){$user = [
					'id' => null,
					'name' => '',
					'permissions' => '',
					'units' => '',
					'token' => '',
					'image' => ''
				];}
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$user['id']) $result['status'] = ['msg' => LANG::GET('user.error_not_found', [':name' => $this->_requestedID])];
		
				// display form for adding a new user with ini related permissions
				$permissions = [];
				foreach(LANGUAGEFILE['permissions'] as $level => $description){
					$permissions[$description] = in_array($level, explode(',', $user['permissions'])) ? ['checked' => true] : [];
				}
				$units = [];
				foreach(LANGUAGEFILE['units'] as $unit => $description){
					$units[$description] = in_array($unit, explode(',', $user['units'])) ? ['checked' => true] : [];
				}
				$result['body']=['content' => [
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
							'onkeypress' => "if (event.key === 'Enter') {api.user('get', 'user', this.value); return false;}"
						]],
						['type' => 'select',
						'description' => LANG::GET('user.edit_existing_users'),
						'attributes' => [
							'onchange' => "api.user('get', 'user', this.value)"
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
						['type' => 'checkbox',
						'description' => LANG::GET('user.edit_units'),
						'content' => $units
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
							'onpointerdown' => $user['id'] ? "if (confirm('". LANG::GET('user.edit_delete_confirm', [':name' => $user['name']]) ."')) {api.user('delete', 'user', ". $user['id'] . ")}" : ''
						]]
					]],
					'form' => [
						'data-usecase' => 'user',
						'action' => $user['id'] ? "javascript:api.user('put', 'user', '" . $user['id'] . "')" : "javascript:api.user('post', 'user')"
					]];

					if ($user['image']) array_splice($result['body']['content'][5], 0, 0,
					[
						['type' => 'image',
						'description' => LANG::GET('user.edit_export_user_image'),
						'attributes' => [
							'name' => $user['name'] . '_pic',
							'url' => $user['image']]
						]
					]
					);
					if ($user['token']) array_splice($result['body']['content'][6], 0, 0,
						[
							['type' => 'image',
							'description' => LANG::GET('user.edit_export_qr_token'),
							'attributes' => [
							'name' => $user['name'] . '_token',
							'qrcode' => $user['token']]
							]
						]
					);

				$this->response($result);
				break;

			case 'DELETE':
				// prefetch to return proper name after deletion
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				$user = $statement->fetch(PDO::FETCH_ASSOC);

				if ($user['image'] && $user['id'] > 1) UTILITY::delete($user['image']);

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_delete'));
				if ($statement->execute([
					':id' => $user['id']
				])) $this->response([
					'status' => [
						'msg' => LANG::GET('user.edit_user_deleted', [':name' => $user['name']]),
						'id' => false
					]]);
				else $this->response([
					'status' => [
						'msg' => LANG::GET('user.edit_user_not_deleted', [':name' => $user['name']]),
						'id' => $user['id']
					]]);
				break;
		}
	}
}

$api = new USER();
$api->processApi();

exit;
?>
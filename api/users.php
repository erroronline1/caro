<?php
// add, edit and delete users

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
	switch ($payload->request){
		case 'user_save':
			if (!in_array('admin', $_SESSION['user']['permissions'])){echo http_response_code(401); break;}
			// initialize varaibles
			$permissions = [];
			$payload->id = SQLQUERY::SANITIZE($payload->id);

			$statement = $pdo->prepare(SQLQUERY::PREPARE('user_save-get_by_id'));
			$statement->execute([
				':id' => $payload->id
			]);
			// prepare or create user-array to populate or update
			if (!$user = $statement->fetch(PDO::FETCH_ASSOC)) $user = [
					'id' => 0,
					'name' => $payload->name,
					'permissions' => '',
					'token' => '',
					'image' => ''
				];

			// checkboxes are not delivered if null, html-value 'on' might have to be converted in given db-structure
			// e.g. $payload->active = $payload->active ? 1 : 0;

			$user['name'] = SQLQUERY::SANITIZE($payload->name);
			// chain checked permission levels
			foreach(INI['permissions'] as $level => $description){
				if ($payload->{$description}) {
					$permissions[] = $level;
				}
			}
			$user['permissions'] = implode(',', $permissions);
			// generate token
			if($payload->renew_on_save){
				$user['token'] = hash('sha256', $user['name'] . random_int(100000,999999) . time());
			}
			// convert image
			if ($_FILES['photo']['tmp_name']) {
				$user['image'] = 'data:image/png;base64,' . base64_encode(resizeImage($_FILES['photo']['tmp_name'], 128));
			}

			$statement = $pdo->prepare(SQLQUERY::PREPARE('user_save'));
			if ($statement->execute([
				':id' => $user['id'],
				':name' => $user['name'],
				':permissions' => $user['permissions'],
				':token' => $user['token'],
				':image' => $user['image']
			])){
					$result = ['id' => $pdo->lastInsertId() ? : $payload->id, 'name' => scriptFilter($payload->name)];
					echo json_encode($result);
			}
		break;
		case 'user_current':
			// select single user based on token
			if (!$payload->Login && $_SESSION['user']){
				echo json_encode($_SESSION['user']);
				break;
			}

			$statement = $pdo->prepare(SQLQUERY::PREPARE('user_current'));
			$statement->execute([
				':token' => SQLQUERY::SANITIZE($payload->Login)
			]);
			$result = $statement->fetch(PDO::FETCH_ASSOC);
			if ($result['token']){
				$_SESSION['user'] = [
					'name' => $result['name'],
					'permissions' => explode(',', $result['permissions']),
					'image' => $result['image']
				];
				echo json_encode($_SESSION['user']);
				break;
			}
			$_SESSION = [];
			echo json_encode(
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
							'description' => 'Login',
							'attributes' => [
								'type' => 'password'
							]
						]]
					]
				]
			);
			break;
	}
	//	else echo http_response_code(401);
}

elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE'){
	switch ($payload->request){
		case 'user_delete':
			if (!in_array('admin', $_SESSION['user']['permissions'])){echo http_response_code(401); break;}
			$payload->id = SQLQUERY::SANITIZE($payload->id);
			$statement = $pdo->prepare(SQLQUERY::PREPARE('user_delete-selected'));
			$statement->execute([
				':id' => $payload->id
			]);
			$user = $statement->fetch(PDO::FETCH_ASSOC);
			$statement = $pdo->prepare(SQLQUERY::PREPARE('user_delete'));
			if ($statement->execute([
				':id' => $user['id']
			])) echo json_encode(['id' => false, 'name' => scriptFilter($user['name'])]);
			else echo json_encode(['id' => $user['id'], 'name' => scriptFilter($user['name'])]);
		break;
	}
	//	else echo http_response_code(401);
}

elseif ($_SERVER['REQUEST_METHOD'] == 'GET'){
	switch ($payload->request){
		case 'user_edit':
			// form to add, edit and delete users. 
			if (!in_array('admin', $_SESSION['user']['permissions'])){echo http_response_code(401); break;}
			$payload->id = SQLQUERY::SANITIZE($payload->id);
			$datalist=[];
			$options=['...'=>[]];
			
			// prepare existing users lists
			$statement = $pdo->prepare(SQLQUERY::PREPARE('user_edit-datalist'));
			$statement->execute();
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach($result as $key => $row) {
				$datalist[] = $row['name'];
				$options[$row['name']] = [];
			}

			// select single user based on id or name
			$statement = $pdo->prepare(SQLQUERY::PREPARE('user_edit-selected'));
			$statement->execute([
				':id' => $payload->id
			]);
			$result = $statement->fetch(PDO::FETCH_ASSOC);

			// display form for adding a new user with ini related permissions
			$permissions=[];
			foreach(INI['permissions'] as $level => $description){
				$permissions[$description] = ['checked' => in_array($level, explode(',', $result['permissions']))];
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
						'value' => $result['id']
					]],
					['type' => 'datalist',
					'content' => $datalist,
					'attributes' => [
						'id' => 'users'
					]]
				],[
					['type' => 'searchinput',
					'description' => 'edit existing users',
					'attributes' => [
						'placeholder' => 'search name',
						'list' => 'users',
						'onkeypress' => "if (event.key === 'Enter') {api.user('user_edit', this.value); return false;}"
					]],
					['type' => 'select',
					'description' => 'edit existing users',
					'attributes' => [
						'onchange' => "api.user('user_edit', this.value)"
					],
					'content' => $options]
				],[
					['type' => 'textinput',
					'description' => 'name',
					'attributes' => [
						'required' => true,
						'value' => $result['name'] ? : ''
					]]
				],[
					['type' => 'checkbox',
					'description' => 'authorized',
					'content' => $permissions
					]
				],[
					['type' => 'image',
					'description' => 'export user image',
					'attributes' => [
						'name' => $result['name'],
						'base64img' => $result['image'] ? : '']
					],
					['type' => 'photo',
					'description' => 'take a photo',
					'attributes' => [
						'name' => 'photo'
					]],
				],[
					['type' => 'image',
					'description' => 'export qr token',
					'attributes' => [
						'name' => $result['name'],
						'qrcode' => $result['token']]
					],
					['type' => 'checkbox',
					'description' => 'access token',
					'content' => ['renew on save' => []]
					],
					['type' => 'deletebutton',
					'description' => 'delete user',
					'attributes' => [
						'type'=>'button', // apparently defaults to submit otherwise
						'onpointerdown' => $result['id'] ? 'if (confirm("delete '. $result['name'] .' permanently?")) {api.user("user_delete", ' . $result['id'] . ')}' : ''
					]]
				]],
				'form' => [
					'data-usecase' => 'user',
					'action' => 'javascript:api.user("user_save")'
				]];
			echo json_encode($form);
			break;
		case 'user_menu':
			// get permission based menu items
			if (!$_SESSION['user']) {echo json_encode(['please login' => []]); break;}
			
			$menu=[
				'logout' => [$_SESSION['user']['name'] . ' logout' => "javascript:api.start('user_current', 'null')"]
			];
			if (in_array('admin', $_SESSION['user']['permissions'])){
				$menu['admin'] = [
					'Users' => "javascript:api.user('user_edit')",
					'Form Components' => "javascript:api.form('form_components_edit')",
					'Forms' => "javascript:api.form('form_edit')"
				];
			}
			echo json_encode($menu);
			break;
		default:
			echo http_response_code(400);
		}
	//	else echo http_response_code(401);
}

?>
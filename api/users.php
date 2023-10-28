<?php
// populate $api_handler on post requests as a return to tell index.php what to do next

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
	switch ($payload->request){
		case 'user_save':
			// initialize varaibles
			$auth = [];
			$update = $token = '';
			$payload->id = dbSanitize($payload->id);
			// unset properties that would conflict with database column names
			unset($payload->request);
			unset($payload->edit_existing_users);

			// chain checked authorization levels
			foreach($ini['authorization']['authorized'] as $level => $description){
				if ($payload->{$description}) {
					$auth[] = $level;
					unset($payload->{$description});
				}
			}

			// checkboxes are not delivered if null, html-value 'on' might have to be converted in given db-structure
			// e.g. $payload->active = $payload->active ? 1 : 0;

			// generate token
			if($payload->renew_on_save){
				$token = hash('sha256', dbSanitize($payload->name) . random_int(100000,999999) . time());
				$update .= ", token='" . $token . "' ";  
				unset($payload->renew_on_save);
			}
			// generate update pairs
			foreach($payload as $key => $value){
				$update .= ", " . dbSanitize($key) . "='" . dbSanitize($value) . "' ";
			}
			$update .= ", permissions='" . implode(',', $auth) . "' ";  

			$statement = $pdo->prepare("INSERT INTO `users` ".
				"(`id`, `name`, `permissions`, `token`) VALUES (" . 
				($payload->id ? : 'NULL') . ", '" . 
				dbSanitize($payload->name) . "', '" . 
				implode(',', $auth) . "', '" . 
				$token . "') ON DUPLICATE KEY UPDATE " . substr($update,2));
			if ($statement->execute()){
					$result = ['id' => $pdo->lastInsertId() ? : $payload->id];
					echo json_encode($result);
			}
		break;
	}
	//	else echo http_response_code(401);
}

elseif ($_SERVER['REQUEST_METHOD'] == 'PUT'){
	switch ($payload->request){
	}
	//	else echo http_response_code(401);
}

elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE'){
	switch ($payload->request){
		case 'user_delete':
			$payload->id = dbSanitize($payload->id);
			$statement = $pdo->prepare("DELETE FROM users WHERE id = " . $payload->id . " LIMIT 1");
			if ($statement->execute()) echo json_encode(['id' => false, 'success' => true]);
			else echo json_encode(['id' => $payload->id, 'success' => false]);
		break;
	}
	//	else echo http_response_code(401);
}

elseif ($_SERVER['REQUEST_METHOD'] == 'GET'){
	switch ($payload->request){
		case 'user_edit':
			// form to add, edit and delete users. 
			$datalist=[];
			$options=['...'=>[]];
			
			// prepare existing users lists
			$statement = $pdo->prepare("SELECT name FROM users ORDER BY name ASC");
			$statement->execute();
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach($result as $key => $row) {
				$datalist[]=$row['name'];
				$options[$row['name']]=[];
			}

			$payload->id = dbSanitize($payload->id);
			$payload->edit_existing_users = dbSanitize($payload->edit_existing_users);

			// select single user based on id or name
			$statement = $pdo->prepare("SELECT * FROM users WHERE id = '" . $payload->id . "' OR name LIKE '" . $payload->id . "' LIMIT 1");
			$statement->execute();
			$result = $statement->fetch(PDO::FETCH_ASSOC);

			// display form for adding a new user with ini related permissions
			$auth=[];
			foreach($ini['authorization']['authorized'] as $level => $description){
				$auth[$description] = ['checked' => in_array($level, explode(',', $result['permissions']))];
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
					'content' => $auth
					]
				],[
					['type' => 'checkbox',
					'description' => 'access token',
					'content' => ['renew on save' => []]
					],['type' => 'button',
					'description' => 'delete user',
					'attributes' => [
						'type'=>'button', // apparently defaults to submit otherwise
						'onpointerdown' => 'if (confirm("delete permanently?")) {api.user("user_delete", ' . $result['id'] . ')}'
					]]
				]],
				'form' => [
					'data-usecase' => 'user',
					'action' => 'javascript:api.user("user_save")'
				]];
			echo json_encode($form);
			break;
		default:
			echo http_response_code(400);
		}
	//	else echo http_response_code(401);
}

?>
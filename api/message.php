<?php
// write and read user messages
class MESSAGE extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_conversation = null;

	public function __construct(){
		parent::__construct();
		$this->_conversation = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	public function message(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				// get recipient id
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get'));
				$statement->execute([
					':id' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('message.to'))
				]);
				if (!$recipient = $statement->fetch(PDO::FETCH_ASSOC)) $this->response(['status' => ['msg' => LANG::GET('user.error_not_found', [':name' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('message.to'))]), 'type' => 'error']], 400);
				if ($recipient['id'] < 2) $this->response(['status' => ['msg' => LANG::GET('message.forbidden'), 'type' => 'error']], 403);
				
				$message = [
					'from_user' => $_SESSION['user']['id'],
					'to_user' => $recipient['id'],
					'message' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('message.message')) ? : UTILITY::propertySet($this->_payload, LANG::PROPERTY('message.message_to', [':user' => $recipient['name']]))
				];

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_post_message'));
				if ($statement->execute($message)) $this->response([
					'status' => [
						'msg' => LANG::GET('message.send_success'),
						'redirect' => false,
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'msg' => LANG::GET('message.send_failure'),
						'redirect' => false,
						'type' => 'error'
					]]);
				break;
		}
		$this->response($result);
	}
	
	public function notification(){
		if (!array_key_exists('user', $_SESSION)) $this->response(['status' => ['msg' => LANG::GET('menu.signin_header'), 'type' => 'info']], 401);
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_unnotified'));
		$statement->execute([
			':user' => $_SESSION['user']['id']
		]);
		$unnotified = $statement->fetch(PDO::FETCH_ASSOC);
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_put_notified'));
		$statement->execute([
			':user' => $_SESSION['user']['id']
		]);
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_unseen'));
		$statement->execute([
			':user' => $_SESSION['user']['id']
		]);
		$unseen = $statement->fetch(PDO::FETCH_ASSOC);
		$this->response([
			'unnotified' => $unnotified['number'], 'unseen' => $unseen['number']
		]);
	}

	public function conversation(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'GET':
				$result = ['body'=>['content'=> []]];
				// prepare existing users lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get-datalist'));
				$statement->execute();
				$user = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($user as $key => $row) {
					if ($row['id'] > 1)	$datalist[] = $row['name'];
				}

				if ($this->_conversation){
					// select conversation
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_conversation'));
					$statement->execute([
						':user' => $_SESSION['user']['id'],
						':conversation' => $this->_conversation
					]);
					$messages = $statement->fetchAll(PDO::FETCH_ASSOC);
					$conversation_content = [];
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get'));
					$statement->execute([
						':id' => $this->_conversation
					]);
					$conversation_user = $statement->fetch(PDO::FETCH_ASSOC);
					foreach($messages as $conversation){
						$conversation_content[] = [
							'type' => 'message',
							'content' => [
								'img' => $conversation['image'],
								'user' => $conversation['conversation_user_name'] ? : LANG::GET('message.deleted_user'),
								'text' => $conversation['message'],
								'date' => $conversation['timestamp'],
							],
							'attributes' =>  [
								'class' => $conversation['sender'] === $_SESSION['user']['id'] ? 'conversation right': 'conversation',
								'onpointerup' => "new Dialog({type: 'input', header: '". LANG::GET('message.forward') ."', body: JSON.parse('" . 
									json_encode(
											[
												[
													'type' => 'datalist',
													'content' => $datalist,
													'attributes' => [
														'id' => 'users'
													]
												],
												[
													'type' => 'textinput',
													'attributes' => [
														'name' => LANG::GET('message.to'),
														'required' => true,
														'list' => 'users',
													]
												],
												[
													'type' => 'textarea',
													'attributes' => [
														'name' => LANG::GET('message.message'),
														'value' => preg_replace(["/\r/","/\n/"], ["\\r", "\\n"], LANG::GET('message.forward_message', [':message' => $conversation['message'], ':user' => $conversation['conversation_user_name'], ':date' => $conversation['timestamp']])),
														'rows' => 8
													]
												]
											]
									 ) . "'), options:{".
									"'".LANG::GET('order.add_information_cancel')."': false,".
									"'".LANG::GET('order.message_to_orderer')."': {value: true, class: 'reducedCTA'},".
									"}}).then(response => {if (response[LANG.GET('message.message')]) {".
										"const formdata = new FormData();".
										"formdata.append('" . LANG::GET('message.to') . "', response[LANG.GET('message.to')]);".
										"formdata.append('" . LANG::GET('message.message') . "', response[LANG.GET('message.message')]);".
										"api.message('post', 'message', formdata)}})"

							]
						];
					}
					$result['body']['content'][] = [
						[
							'type' => 'deletebutton',
							'attributes' => [
								'value' => LANG::GET('message.delete'),
								'type' => 'button',
								'onpointerup' => "new Dialog({type: 'confirm', header: '". LANG::GET('message.delete') ."', options:{".
									"'".LANG::GET('message.delete_confirm_cancel')."': false,".
									"'".LANG::GET('message.delete_confirm_ok')."': {value: true, class: 'reducedCTA'},".
									"}}).then(confirmation => {if (confirmation) api.message('delete', 'conversation', " . $conversation['conversation_user'] . ", 'inbox')})"
							]
						]
					];
					$result['body']['content'][] = $conversation_content;
					if ($conversation['conversation_user'] !== 1 && $conversation_user['name']) {
						$result['body']['content'][] = [
							[
								'type' => 'hiddeninput',
								'attributes' => [
									'name' => LANG::GET('message.to'),
									'value' => $this->_conversation
								]
							],
							[
								'type' => 'textarea',
								'attributes' => [
									'name' => LANG::GET('message.message_to', [':user' => $conversation_user['name']]),
								],
								'hint' => LANG::GET('message.forward_hint')
							]
						];
						$result['body']['form'] = [
							'data-usecase' => 'message',
							'action' => "javascript:api.message('post', 'message', '_')"
						];
					}
				}
				else {
					$result['body']['content'][] = [
						[
							'type' => 'button',
							'attributes' => [
								'value' => LANG::GET('message.new'),
								'type' => 'button',
								'onpointerup' => "new Dialog({type: 'input', header: '". LANG::GET('message.new') ."', body: JSON.parse('" . 
									json_encode(
											[
												[
													'type' => 'datalist',
													'content' => $datalist,
													'attributes' => [
														'id' => 'users'
													]
												],
												[
													'type' => 'textinput',
													'attributes' => [
														'name' => LANG::GET('message.to'),
														'required' => true,
														'list' => 'users',
													]
												],
												[
													'type' => 'textarea',
													'attributes' => [
														'name' => LANG::GET('message.message'),
														'rows' => 8
													]
												]
											]
									 ) . "'), options:{".
									"'".LANG::GET('order.add_information_cancel')."': false,".
									"'".LANG::GET('order.message_to_orderer')."': {value: true, class: 'reducedCTA'},".
									"}}).then(response => {if (response[LANG.GET('message.message')]) {".
										"const formdata = new FormData();".
										"formdata.append('" . LANG::GET('message.to') . "', response[LANG.GET('message.to')]);".
										"formdata.append('" . LANG::GET('message.message') . "', response[LANG.GET('message.message')]);".
										"api.message('post', 'message', formdata)}})"
							]
						]
					];
					// select conversations
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_conversations'));
					$statement->execute([
						':user' => $_SESSION['user']['id']
					]);
					$conversations = $statement->fetchAll(PDO::FETCH_ASSOC);
					if ($conversations) {
						foreach($conversations as $conversation){
							// select unseen per conversation
							$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_unseen_conversations'));
							$statement->execute([
								':user' => $_SESSION['user']['id'],
								':conversation' => $conversation['conversation_user']
							]);
							$unseen = $statement->fetch(PDO::FETCH_ASSOC);

							$conversation['message'] = preg_replace('/\n|\r/', ' ', $conversation['message']);
							$result['body']['content'][] = [
								[
									'type' => 'message',
									'content' => [
										'img' => $conversation['image'],
										'user' => $conversation['conversation_user_name'] ? : LANG::GET('message.deleted_user'),
										'text' => (strlen($conversation['message'])>128 ? substr($conversation['message'], 0, 128) . '...': $conversation['message']),
										'date' => $conversation['timestamp'],
										'unseen' => intval($unseen['unseen'])
									],
									'attributes' =>  [
										'onpointerup' => "api.message('get', 'conversation', '" . $conversation['conversation_user'] . "')",
									]
								]
							];
						}
					} else $result['body']['content'][] = $this->noContentAvailable(LANG::GET('message.no_messages'))[0];
				}
				break;
			case 'DELETE':
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_delete_conversation'));
				if ($statement->execute([
					':conversation' => $this->_conversation,
					':user' => $_SESSION['user']['id']
				])) $this->response([
					'status' => [
						'msg' => LANG::GET('message.delete_success'),
						'redirect' => false,
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'msg' => LANG::GET('message.delete_failure'),
						'redirect' => false,
						'type' => 'error'
					]]);
				break;
		}
		$this->response($result);
	}

	public function register(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		// prepare existing users lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get-datalist'));
		$statement->execute();
		$users = $statement->fetchAll(PDO::FETCH_ASSOC);
		$result = ['units' => [], 'permissions' => [], 'name' => []];

		foreach($users as $user){
			$mailto = [
				LANG::GET('order.message_orderer', [':orderer' => $user['name']]) => ['href' => 'javascript:void(0)', 'data-type' => 'input',
				'onpointerup' => "messageClient('". LANG::GET('order.message_orderer', [':orderer' => $user['name']]) ."', '" . $user['name'] . "', '', null, null)"]
			];
			$result['name'][] = $mailto;
			if ($user['units'])
				foreach(explode(',', $user['units']) as $unit){
					if (!array_key_exists($unit, $result['units'])) $result['units'][$unit] = [];
					$result['units'][$unit][] = $mailto;
				}
			if ($user['permissions'])
				foreach(explode(',', $user['permissions']) as $permission){
					if (!array_key_exists($permission, $result['permissions'])) $result['permissions'][$permission] = [];
					$result['permissions'][$permission][] = $mailto;
				}
		}
		var_dump($result);
	}
}

$api = new MESSAGE();
$api->processApi();

exit;
?>
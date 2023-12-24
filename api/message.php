<?php
// write and read user messages
class MESSAGE extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_redirect = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_redirect = array_key_exists(3, REQUEST) ? REQUEST[3] : null;
	}

	public function message(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				// get recipient id
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get'));
				$statement->execute([
					':id' => $this->_payload->to
				]);
				if (!$recipient = $statement->fetch(PDO::FETCH_ASSOC)) $this->response(['status' => ['msg' => LANG::GET('user.error_not_found', [':name' => $this->_payload->to])]], 400);
				
				$message = [
					'from_user' => $_SESSION['user']['id'],
					'to_user' => $recipient['id'],
					'message' => $this->_payload->message
				];

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_post_message'));
				if ($statement->execute($message)) $this->response([
					'status' => [
						'msg' => LANG::GET('message.send_success'),
						'redirect' => 'sent'
					]]);
				else $this->response([
					'status' => [
						'name' => LANG::GET('message.send_failure'),
						'redirect' => false
					]]);
				break;
			case 'GET':
				$datalist = [];
				$result = [];
				
				// prepare existing users lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get-datalist'));
				$statement->execute();
				$user = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($user as $key => $row) {
					$datalist[] = $row['name'];
				}
		
				// select message
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_message'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				if (!$message = $statement->fetch(PDO::FETCH_ASSOC)){$message = [
					'id' => null,
					'from_user' => '',
					'to_user' => '',
					'message' => '',
					'timestamp' => ''
				];}
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$message['id']) $result['status'] = ['msg' => LANG::GET('message.error_not_found')];
		
				// display form for writing or reading a message
				$result['body']=['content' => [
					[
						['type' => 'datalist',
						'content' => $datalist,
						'attributes' => [
							'id' => 'users'
						]],
					],[
						['type' => 'textinput',
						'collapse' => true,
						'attributes' => [
							'name' => 'to',
							'required' => true,
							'placeholder' => LANG::GET('message.to'),
							'list' => 'users',
							'value' => $message['from_user'] ? : ''
						]],
						['type' => 'textarea',
						'collapse' => true,
						'attributes' => [
							'name' => 'message',
							'required' => true,
							'placeholder' => LANG::GET('message.message'),
							'value' => $message['message'] ? : '',
							'rows' => 10
						]],
						['type' => 'message',
						'collapse' => true
						]
					]
					],
					'form' => [
						'data-usecase' => 'message',
						'action' => $message['id'] ? 'javascript:void(0)' : 'javascript:api.message("post", "message")'
					]];

				break;
			case 'DELETE':
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_message'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				$message = $statement->fetch(PDO::FETCH_ASSOC);

				
				/*
				tired now...
				todo:
				* duplicate emails: owner sender or recipient
				* check ownership on deletion
				* reply
				* forward
				* search
				* mobile problems within inbox and sent?!


				*/


				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_delete_message'));
				if ($statement->execute([
					':id' => $this->_requestedID
				])) $this->response([
					'status' => [
						'msg' => LANG::GET('message.delete_success'),
						'redirect' => 'sent'
					]]);
				else $this->response([
					'status' => [
						'name' => LANG::GET('message.delete_failure'),
						'redirect' => false
					]]);
				break;
		}
		$this->response($result);
	}
	
	public function inbox(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		$result = ['body'=>[]];
		
		// select messages
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_inbox'));
		$statement->execute([
			':user' => $_SESSION['user']['id']
		]);
		$messages = $statement->fetchAll(PDO::FETCH_ASSOC);

		// display form for writing or reading a message
		$content=[];
		foreach($messages as $key => $message) {

			$content[]= [
				['type' => 'textinput',
				'collapse' => true,
				'description' => LANG::GET('message.from'),
				'attributes' => [
					'readonly' => true,
					'placeholder' => LANG::GET('message.from'),
					'value' => $message['from_user'] ? : LANG::GET('message.deleted_user')
				]],
				['type' => 'textarea',
				'collapse' => true,
				'attributes' => [
					'name' => 'message',
					'readonly' => true,
					'value' => $message['message'],
					'rows' => 7
				]],
				['type' => 'text',
				'collapse' => true,
				'content' => '\n' . LANG::GET('message.time') . ' ' . $message['timestamp']
				],
				['type' => 'deletebutton',
				'collapse'=> true,
				'description' => LANG::GET('message.delete'),
				'attributes' => [
					'type' => 'button',
					'onpointerdown' => "api.message('delete', 'message', " . $message['id'] . ", 'inbox')" 
					]],
				['type' => 'message',
				'collapse' => true
				]
			];
		}
		$result['body']['content'] = $content;
		$this->response($result);
	}

	public function sent(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		$result = ['body'=>[]];
		
		// select messages
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_sent'));
		$statement->execute([
			':user' => $_SESSION['user']['id']
		]);
		$messages = $statement->fetchAll(PDO::FETCH_ASSOC);

		// display form for writing or reading a message
		$content=[];
		foreach($messages as $key => $message) {

			$content[]= [
				['type' => 'textinput',
				'collapse' => true,
				'description' => LANG::GET('message.to'),
				'attributes' => [
					'readonly' => true,
					'placeholder' => LANG::GET('message.to'),
					'value' => $message['to_user'] ? : LANG::GET('message.deleted_user')
				]],
				['type' => 'textarea',
				'collapse' => true,
				'attributes' => [
					'name' => 'message',
					'readonly' => true,
					'value' => $message['message'],
					'rows' => 7
				]],
				['type' => 'text',
				'collapse' => true,
				'content' => '\n' . LANG::GET('message.time') . ' ' . $message['timestamp']
				],
				['type' => 'deletebutton',
				'collapse'=> true,
				'description' => LANG::GET('message.delete'),
				'attributes' => [
					'type' => 'button',
					'onpointerdown' => "api.message('delete', 'message', " . $message['id'] . ", 'sent')" 
				]],
				['type' => 'message',
				'collapse' => true
				]
			];
		}
		$result['body']['content'] = $content;
		$this->response($result);
	}	
}

$api = new MESSAGE();
$api->processApi();

exit;
?>
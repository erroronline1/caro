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
				if ($recipient['id'] < 2) $this->response(['status' => ['msg' => LANG::GET('message.forbidden')]], 403);
				
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
				$prefill = [
					'message'=>UTILITY::propertySet($this->_payload, 'message') ? : '',
					'to'=>UTILITY::propertySet($this->_payload, 'to') ? : ''];
				
				// prepare existing users lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get-datalist'));
				$statement->execute();
				$user = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($user as $key => $row) {
					if ($row['id'] > 1)	$datalist[] = $row['name'];
				}
						
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
						'attributes' => [
							'name' => 'to',
							'required' => true,
							'placeholder' => LANG::GET('message.to'),
							'list' => 'users',
							'value' => $prefill['to'] ? : '',
							'data-loss' => 'prevent'
						]],
						['type' => 'textarea',
						'attributes' => [
							'name' => 'message',
							'required' => true,
							'placeholder' => LANG::GET('message.message'),
							'value' => $prefill['message'] ? : '',
							'rows' => 10,
							'data-loss' => 'prevent'
						]],
						['type' => 'message']
					]
					],
					'form' => [
						'data-usecase' => 'message',
						'action' => "javascript:api.message('post', 'message')"
					]];

				break;
			case 'DELETE':
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_delete_message'));
				if ($statement->execute([
					':id' => $this->_requestedID,
					':user' => $_SESSION['user']['id']
				])) $this->response([
					'status' => [
						'msg' => LANG::GET('message.delete_success'),
						'redirect' => $this->_redirect ? : 'inbox'
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
	
	public function notification(){
		if (!array_key_exists('user', $_SESSION)) $this->response(['status' => ['msg' => LANG::GET('menu.signin_header')]], 401);
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

	public function filter(){
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_filter'));
		$statement->execute([
			':user' => $_SESSION['user']['id'],
			':msgfilter' => $this->_requestedID
		]);
		$filtered = $statement->fetchAll(PDO::FETCH_ASSOC);
		$matches = [];
		foreach ($filtered as $row){
			$matches[] = $row['id'];
		}
		$this->response(['status' => [
			'data' => $matches
		]]);
	}

	public function inbox(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		$result = ['body'=>[]];
		
		// set messages to seen on entering inbox
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_put_seen'));
		$statement->execute([
			':user' => $_SESSION['user']['id']
		]);

		// select messages
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_inbox'));
		$statement->execute([
			':user' => $_SESSION['user']['id']
		]);
		$messages = $statement->fetchAll(PDO::FETCH_ASSOC);

		$content=[[
				['type' => 'searchinput',
				'attributes' => [
					'placeholder' => LANG::GET('message.message_filter_label'),
					'onkeypress' => "if (event.key === 'Enter') {api.message('get', 'filter', this.value); return false;}",
					'onblur' => "api.message('get', 'filter', this.value); return false;",
					'id' => 'productsearch'
				]],
				['type' => 'filter']
		]];
		foreach($messages as $message) {
			$content[]= [
				['type' => 'hiddeninput',
				'description' => 'filter',
				'attributes'=>['data-filtered' => $message['id']]],
				['type' => 'textinput',
				'description' => LANG::GET('message.from'),
				'attributes' => [
					'name' => 'to',
					'readonly' => true,
					'data-message' => $message['id'],
					'placeholder' => LANG::GET('message.from'),
					'value' => $message['from_user'] ? : LANG::GET('message.deleted_user')
				]],
				['type' => 'textarea',
				'attributes' => [
					'name' => 'message',
					'data-message' => $message['id'],
					'readonly' => true,
					'value' => $message['message'],
					'rows' => 7
				]],
				['type' => 'text',
				'content' => '\n' . LANG::GET('message.time') . ' ' . $message['timestamp']
				],
				['type' => 'deletebutton',
				'description' => LANG::GET('message.delete'),
				'attributes' => [
					'type' => 'button',
					'onpointerup' => "api.message('delete', 'message', " . $message['id'] . ", 'inbox')" 
					]],
				['type' => 'message']
			];
			if ($message['from_user'] && $message['from_user'] != INI['caroapp']) array_splice($content[count($content)-1], 4, 0, [
				['type' => 'button',
				'description' => LANG::GET('message.reply'),
				'attributes' => [
					'type' => 'button',
					'onpointerup' => "api.message('get', 'message', '[data-message=\"" . $message['id'] . "\"]', 'reply')" 
					]]
				]);
			if ($message['image']) array_splice($content[count($content)-1], 0, 0, [
				['type' => 'image',
				'attributes' => [
					'url' => $message['image'],
					'imageonly' => ['width' => '3em', 'height' => '3em'] 
					]]
				]);
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

		$content=[[
			['type' => 'searchinput',
			'attributes' => [
				'placeholder' => LANG::GET('message.message_filter_label'),
				'onkeypress' => "if (event.key === 'Enter') {api.message('get', 'filter', this.value); return false;}",
				'onblur' => "api.message('get', 'filter', this.value); return false;",
				'id' => 'productsearch'
			]],
			['type' => 'filter']
		]];
		foreach($messages as $key => $message) {
			$content[]= [
				['type' => 'hiddeninput',
				'description' => 'filter',
				'attributes'=>['data-filtered' => $message['id']]],
				['type' => 'textinput',
				'description' => LANG::GET('message.to'),
				'attributes' => [
					'name' => 'to',
					'readonly' => true,
					'data-message' => $message['id'],
					'placeholder' => LANG::GET('message.to'),
					'value' => $message['to_user'] ? : LANG::GET('message.deleted_user')
				]],
				['type' => 'textarea',
				'attributes' => [
					'name' => 'message',
					'data-message' => $message['id'],
					'readonly' => true,
					'value' => $message['message'],
					'rows' => 7
				]],
				['type' => 'text',
				'content' => '\n' . LANG::GET('message.time') . ' ' . $message['timestamp']
				],
				['type' => 'button',
				'description' => LANG::GET('message.forward'),
				'attributes' => [
					'type' => 'button',
					'onpointerup' => "api.message('get', 'message', '[data-message=\"" . $message['id'] . "\"]', 'sent')" 
				]],
				['type' => 'deletebutton',
				'description' => LANG::GET('message.delete'),
				'attributes' => [
					'type' => 'button',
					'onpointerup' => "api.message('delete', 'message', " . $message['id'] . ", 'sent')" 
				]],
				['type' => 'message']
			];
			if ($message['image']) array_splice($content[count($content)-1], 0, 0, [
				['type' => 'image',
				'attributes' => [
					'url' => $message['image'],
					'imageonly' => ['width' => '3em', 'height' => '3em'] 
					]]
				]);
		}
		$result['body']['content'] = $content;
		$this->response($result);
	}	
}

$api = new MESSAGE();
$api->processApi();

exit;
?>
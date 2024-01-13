<?php
// write and read user messages
class TOOL extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_redirect = null;
	private $_recipient = null;
	private $_message = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? (REQUEST[2] != 0 ? REQUEST[2] : null) : null;
		$this->_redirect = array_key_exists(3, REQUEST) ? (REQUEST[3] != 0 ? REQUEST[3] : null) : null;
		$this->_recipient = array_key_exists(4, REQUEST) ? REQUEST[4] : null;
		$this->_message = array_key_exists(5, REQUEST) ? REQUEST[5] : null;
	}

	public function code(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				break;
			case 'GET':
				$result['body']=['content' => [
					],
					'form' => [
						'data-usecase' => 'message',
						'action' => "javascript:api.tool('post', 'code')"
					]];

				break;
		}
		$this->response($result);
	}
	
}

$api = new TOOL();
$api->processApi();

exit;
?>
<?php
// add, edit and delete users
class FILE extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	public $_requestedFolder = null;
	public $_requestedFile = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedFolder = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_requestedFile = array_key_exists(3, REQUEST) ? REQUEST[3] : null;
	}

	public function files(){
		/**
		 * no put method for windows server permissions are a pita
		 * thus directories can not be renamed
		 */

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
			case 'GET':
				if (!(array_intersect(['admin', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
			case 'DELETE':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
		}
		$this->response($result);
	}

	public function directory(){
		/**
		 * no put method for windows server permissions are a pita
		 * thus directories can not be renamed
		 */
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				break;
			case 'GET':
				break;
			case 'DELETE':
				break;
		}
		$this->response($result);
	}

	public function manager(){
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);

	}
}

$api = new FILE();
$api->processApi();

exit;
?>
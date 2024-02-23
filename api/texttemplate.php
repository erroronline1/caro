<?php
// add, edit and delete users
class TEXTTEMPLATE extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
    private $_requestedID = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedID = $this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	public function chunk(){
		if (!array_key_exists('user', $_SESSION)) $this->response(['body' => [LANG::GET('menu.application_header') => [LANG::GET('menu.application_signin') => []]]]);			
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
			case 'PUT':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
			case 'GET':
				$result = [
					'body' => ['content' => []]
				];
		
				break;
			case 'DELETE':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
		}					
	}

	public function text(){
		if (!array_key_exists('user', $_SESSION)) $this->response(['body' => [LANG::GET('menu.application_header') => [LANG::GET('menu.application_signin') => []]]]);			
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
			case 'PUT':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
			case 'GET':
				$result = [
					'body' => ['content' => []]
				];
				break;
			case 'DELETE':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;						
		}
	}

	public function template(){
		if (!array_key_exists('user', $_SESSION)) $this->response(['body' => [LANG::GET('menu.application_header') => [LANG::GET('menu.application_signin') => []]]]);			
	}
}

$api = new TEXTTEMPLATE();
$api->processApi();

exit;
?>
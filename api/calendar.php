<?php
// calendar and planning
class CALENDAR extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedType = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedType = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	
}

$api = new CALENDAR();
$api->processApi();

exit;
?>
<?php
// diverse tools
class AUDIT extends API {
	/**
	 * in case you want to create a code from any values in any form add 
	 * 'data-usecase' => 'tool_create_code'
	 * to inputs attributes (hiddeninput if applicable)
	 */

	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedType = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedType = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	public function checks(){
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
		$result['body'] = ['content' => []];
		$selecttypes = [];
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('checks_get-types'));
		$statement->execute();
		$types = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach($types as $type){
			$selecttypes[LANG::GET('audit.checks_type.' . $type['type'])] = ['value' => $type['type']];
		}
		$result['body']['content'][] = [
			[
				'type' => 'select',
				'content' => $selecttypes,
				'attributes' => [
					'name' => LANG::GET('audit.checks_select_type'),
					'onchange' => "api.audit('get', 'checks', this.value)"
				]
			]
		];

		if ($this->_requestedType) {
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('checks_get'));
			$statement->execute([':type' => $this->_requestedType]);
			$checks = $statement->fetchAll(PDO::FETCH_ASSOC);
			$entries = [];
			foreach($checks as $row){
				$entries[] = [
					'type' => 'text',
					'description' => LANG::GET('audit.check_description', [
						':check' =>LANG::GET('audit.checks_type.' . $this->_requestedType),
						':date' => $row['date'],
						':author' => $row['author']
					]),
					'content' => $row['content']
				];
			}
			$result['body']['content'][] = $entries;
		}

		$this->response($result);
	}
	

	
}

$api = new AUDIT();
$api->processApi();

exit;
?>
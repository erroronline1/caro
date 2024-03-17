<?php
// audit overview and export
require_once('./pdf.php');

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
		
		// checks
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('checks_get-types'));
		$statement->execute();
		$types = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach($types as $type){
			$selecttypes[LANG::GET('audit.checks_type.' . $type['type'])] = ['value' => $type['type']];
			if ($this->_requestedType===$type['type']) $selecttypes[LANG::GET('audit.checks_type.' . $type['type'])]['selected'] = true;
		}
		// user certificates
		//$selecttypes[LANG::GET('audit.checks_type.userfiles')] = ['value' => 'userfiles'];
		//if ($this->_requestedType==='userfiles') $selecttypes[LANG::GET('audit.checks_type.userfiles')]['selected'] = true;

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

			$result['body']['content'][] = [
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('record.record_export'),
					'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "')"
				]
			];
			if ($append = $this->{$this->_requestedType}()) $result['body']['content'][] = $append;					
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('checks_get'));
			$statement->execute([':type' => $this->_requestedType]);
			$checks = $statement->fetchAll(PDO::FETCH_ASSOC);
			$entries = [];
			foreach($checks as $row){
				$entries[] = [
					'type' => 'text',
					'description' => LANG::GET('audit.check_description', [
						':check' => LANG::GET('audit.checks_type.' . $this->_requestedType),
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
	
	private function mdrsamplecheck(){
		// get unchecked articles for MDR §14 sample check
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-not-checked'));
		$statement->execute();
		$sampleCheck = $statement->fetchAll(PDO::FETCH_ASSOC);
		$unchecked = [];
		foreach($sampleCheck as $row){
			if (!in_array($row['vendor_name'], $unchecked)) $unchecked[] = $row['vendor_name'];
		}
		return $unchecked ? [
			[
				'type' => 'text',
				'description' => LANG::GET('audit.mdrsamplecheck_warning_description'),
				'content' => LANG::GET('audit.mdrsamplecheck_warning_content', [':vendors' => implode(', ', $unchecked)])
			]
		] : null;
	}

	private function incorporation(){
		// get unincorporated articles from approved orders
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-not-incorporated'));
		$statement->execute();
		$unincorporated = $statement->fetchAll(PDO::FETCH_ASSOC);

		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get-approved-order-by-substr'));
		$statement->execute([
			':substr' => LANG::PROPERTY('order.ordernumber_label')
		]);
		$approvedorders = $statement->fetchAll(PDO::FETCH_ASSOC);
		$orderedunincorporated = [];
		foreach ($approvedorders as $row){
			$decoded_order_data = json_decode($row['order_data'], true);
			if (array_key_exists(LANG::PROPERTY('order.ordernumber_label'), $decoded_order_data) && ($tocheck = array_search($decoded_order_data[LANG::PROPERTY('order.ordernumber_label')], array_column($unincorporated, 'article_no'))) !== false){
				if (array_key_exists(LANG::PROPERTY('order.vendor_label'), $decoded_order_data) && $unincorporated[$tocheck]['vendor_name'] === $decoded_order_data[LANG::PROPERTY('order.vendor_label')]){
					$article = $decoded_order_data[LANG::PROPERTY('order.ordernumber_label')] . $decoded_order_data[LANG::PROPERTY('order.vendor_label')];
					if (!in_array($article, $orderedunincorporated)) $orderedunincorporated[] = $article;
				}
			}
		}
		return $orderedunincorporated ? [
			[
				'type' => 'text',
				'description' => LANG::GET('audit.incorporation_warning_description'),
				'content' => LANG::GET('audit.incorporation_warning_content', [':amount' => count($orderedunincorporated)])
			]
		] : null;
	}

	public function export(){
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);

		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('checks_get'));
		$statement->execute([':type' => $this->_requestedType]);
		$checks = $statement->fetchAll(PDO::FETCH_ASSOC);
		$summary = [
			'filename' => preg_replace('/[^\w\d]/', '', LANG::GET('audit.checks_type.' . $this->_requestedType) . '_' . date('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('audit.checks_type.' . $this->_requestedType),
			'date' => date('y-m-d H:i')
		];

		foreach($checks as $row){
			$summary['content'][LANG::GET('audit.check_description', [
				':check' => LANG::GET('audit.checks_type.' . $this->_requestedType),
				':date' => $row['date'],
				':author' => $row['author']
			])] = $row['content'];
		}
		$downloadfiles = [];
		$downloadfiles[LANG::GET('menu.record_summary')] = [
			'href' => PDF::auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[
				'type' => 'links',
				'description' =>  LANG::GET('record.record_export_proceed'),
				'content' => $downloadfiles
			]
		);
		$this->response([
			'body' => $body,
		]);
	}
}

$api = new AUDIT();
$api->processApi();

exit;
?>
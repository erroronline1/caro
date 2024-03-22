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
		$selecttypes[LANG::GET('audit.checks_type.userfiles')] = ['value' => 'userfiles'];
		if ($this->_requestedType==='userfiles') $selecttypes[LANG::GET('audit.checks_type.userfiles')]['selected'] = true;
		// forms
		$selecttypes[LANG::GET('audit.checks_type.forms')] = ['value' => 'forms'];
		if ($this->_requestedType==='forms') $selecttypes[LANG::GET('audit.checks_type.forms')]['selected'] = true;

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
			if ($append = $this->{$this->_requestedType}()) array_push($result['body']['content'] , ...$append);
		}
		$this->response($result);
	}
	
	private function mdrsamplecheck(){
		$content = $unchecked = $entries = [];
		// get unchecked articles for MDR §14 sample check
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-not-checked'));
		$statement->execute();
		$sampleCheck = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach($sampleCheck as $row){
			if (!in_array($row['vendor_name'], $unchecked)) $unchecked[] = $row['vendor_name'];
		}
		if ($unchecked) $content[] = [
			[
				'type' => 'text',
				'description' => LANG::GET('audit.mdrsamplecheck_warning_description'),
				'content' => LANG::GET('audit.mdrsamplecheck_warning_content', [':vendors' => implode(', ', $unchecked)])
			]
		];
		// add export button
		$content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('record.record_export'),
					'onpointerup' => "api.audit('get', 'exportchecks', '" . $this->_requestedType . "')"
				]
			]
		];
		// add checks
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('checks_get'));
		$statement->execute([':type' => $this->_requestedType]);
		$checks = $statement->fetchAll(PDO::FETCH_ASSOC);
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
		if ($entries) $content[] = $entries;
		return $content;
	}

	private function incorporation(){
		$content = $orderedunincorporated = $entries = [];
		// get unincorporated articles from approved orders
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-not-incorporated'));
		$statement->execute();
		$unincorporated = $statement->fetchAll(PDO::FETCH_ASSOC);

		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get-approved-order-by-substr'));
		$statement->execute([
			':substr' => LANG::PROPERTY('order.ordernumber_label')
		]);
		$approvedorders = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach ($approvedorders as $row){
			$decoded_order_data = json_decode($row['order_data'], true);
			if (array_key_exists(LANG::PROPERTY('order.ordernumber_label'), $decoded_order_data) && ($tocheck = array_search($decoded_order_data[LANG::PROPERTY('order.ordernumber_label')], array_column($unincorporated, 'article_no'))) !== false){
				if (array_key_exists(LANG::PROPERTY('order.vendor_label'), $decoded_order_data) && $unincorporated[$tocheck]['vendor_name'] === $decoded_order_data[LANG::PROPERTY('order.vendor_label')]){
					$article = $decoded_order_data[LANG::PROPERTY('order.ordernumber_label')] . $decoded_order_data[LANG::PROPERTY('order.vendor_label')];
					if (!in_array($article, $orderedunincorporated)) $orderedunincorporated[] = $article;
				}
			}
		}
		if ($orderedunincorporated) $content[] = [
			[
				'type' => 'text',
				'description' => LANG::GET('audit.incorporation_warning_description'),
				'content' => LANG::GET('audit.incorporation_warning_content', [':amount' => count($orderedunincorporated)])
			]
		];
		// add export button
		$content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('record.record_export'),
					'onpointerup' => "api.audit('get', 'exportchecks', '" . $this->_requestedType . "')"
				]
			]
		];
		// add checks
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('checks_get'));
		$statement->execute([':type' => $this->_requestedType]);
		$checks = $statement->fetchAll(PDO::FETCH_ASSOC);
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
		if ($entries) $content[] = $entries;
		return $content;
	}

	public function exportchecks(){
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

	private function userfiles(){
		$content = [];
		$storedfiles = UTILITY::listFiles(UTILITY::directory('users'), 'asc');
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('user_get-datalist'));
		$statement->execute();
		$users = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach ($users as $user){
			$userfiles = [];
			foreach ($storedfiles as $file){
				if (substr(pathinfo($file)['filename'], 0, strpos(pathinfo($file)['filename'], '_')) === $user['id']) {
					$userfiles[pathinfo($file)['basename']] = ['href' => substr($file, 1)];
				}
			}
			if ($userfiles) {
				$content [] = [
					[
						'type' => 'links',
						'description' => $user['name'],
						'content' => $userfiles
					]
				];
			}
		}
		return $content;
	}

	private function forms(){
		$content = [];

		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-datalist-approved'));
		$statement->execute();
		$forms = $statement->fetchAll(PDO::FETCH_ASSOC);
		$hidden = $currentforms = [];
				foreach($forms as $form){
			if ($form['hidden']) $hidden[] = $form['name']; // since ordered by recent, older items will be skipped
			if (!in_array($form['name'], array_column($currentforms, 'name')) && !in_array($form['name'], $hidden)) $currentforms[] = $form;
		}

		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_bundle-datalist'));
		$statement->execute();
		$bundles = $statement->fetchAll(PDO::FETCH_ASSOC);
		$hidden = $currentbundles = [];
		foreach($bundles as &$bundle){
			if ($bundle['hidden']) $hidden[] = $bundle['name']; // since ordered by recent, older items will be skipped
			if (!in_array($bundle['name'], array_column($currentbundles, 'name')) && !in_array($bundle['name'], $hidden)) $currentbundles[] = $bundle;
		}

		$formscontent = [
			[
				'type' => 'text',
				'description' => LANG::GET('audit.documents_in_use_documents'),
				'content' => ''
			]
		];
		foreach($currentforms as $form){
			$components = explode(',', $form['content']);
			$componentlist = [];
			foreach($components as $component){
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name-approved'));
				$statement->execute([':name' => $component]);
				$cmpnnt = $statement->fetch(PDO::FETCH_ASSOC);
				if ($cmpnnt)
					$componentlist[] = $cmpnnt['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $cmpnnt['author'], ':date' => $cmpnnt['date']]) . "\n" .
						LANG::GET('audit.documents_in_use_approved', [
							':permission' => LANG::GET('permissions.supervisor'),
							':name' => json_decode($cmpnnt['supervisor_approval'], true)['name'],
							':date' => json_decode($cmpnnt['supervisor_approval'], true)['date'],
						]) . "\n" .
						LANG::GET('audit.documents_in_use_approved', [
							':permission' => LANG::GET('permissions.qmo'),
							':name' => json_decode($cmpnnt['qmo_approval'], true)['name'],
							':date' => json_decode($cmpnnt['qmo_approval'], true)['date'],
						]) . "\n" .
						LANG::GET('audit.documents_in_use_approved', [
							':permission' => LANG::GET('permissions.ceo'),
							':name' => json_decode($cmpnnt['ceo_approval'], true)['name'],
							':date' => json_decode($cmpnnt['ceo_approval'], true)['date'],
						]);
			}
			$formscontent[] = [
				'type' => 'text',
				'description' => $form['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $form['author'], ':date' => $form['date']]) . " - " .
					LANG::GET('audit.documents_in_use_approved', [
						':permission' => LANG::GET('permissions.supervisor'),
						':name' => json_decode($form['supervisor_approval'], true)['name'],
						':date' => json_decode($form['supervisor_approval'], true)['date'],
					]) . " - " .
					LANG::GET('audit.documents_in_use_approved', [
						':permission' => LANG::GET('permissions.qmo'),
						':name' => json_decode($form['qmo_approval'], true)['name'],
						':date' => json_decode($form['qmo_approval'], true)['date'],
					]) . " - " .
					LANG::GET('audit.documents_in_use_approved', [
						':permission' => LANG::GET('permissions.ceo'),
						':name' => json_decode($form['ceo_approval'], true)['name'],
						':date' => json_decode($form['ceo_approval'], true)['date'],
					]),
				'content' => implode("\n \n", $componentlist)
			];
		}

		$bundlescontent = [
			[
				'type' => 'text',
				'description' => LANG::GET('audit.documents_in_use_bundles'),
				'content' => ''
			]
		];
		foreach($currentbundles as $bundle){
			$formslist = explode(',', $bundle['content']);
			natsort($formslist);
			$bundlescontent[] = [
				'type' => 'text',
				'description' => $bundle['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $bundle['author'], ':date' => $bundle['date']]),
				'content' => implode("\n", $formslist)
			];
		}

		// add export button
		$content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('record.record_export'),
					'onpointerup' => "api.audit('get', 'exportforms', '" . $this->_requestedType . "')"
				]
			]
		];
		
		$content[] = $formscontent;
		$content[] = $bundlescontent;
		return $content;
	}

	public function exportforms(){
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);

		$summary = [
			'filename' => preg_replace('/[^\w\d]/', '', LANG::GET('audit.checks_type.' . $this->_requestedType) . '_' . date('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('audit.checks_type.' . $this->_requestedType),
			'date' => date('y-m-d H:i')
		];

		$forms = $this->forms();

		for($i = 1; $i<count($forms); $i++){
			foreach($forms[$i] as $item){
				$summary['content'][$item['description']] = $item['content'];	
			}
		}
		$downloadfiles = [];
		$downloadfiles[LANG::GET('menu.record_summary')] = [
			'href' => PDF::auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  LANG::GET('record.record_export_proceed'),
				'content' => $downloadfiles
			]]
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
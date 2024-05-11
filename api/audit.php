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

	/**
	 * init parent class and set private requests
	 */
	public function __construct(){
		parent::__construct();
		$this->_requestedType = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	/**
	 * main entry point for module
	 * displays a selection of available options
	 * calls $this->_requestedType method if set
	 */
	public function checks(){
		if (!(array_intersect(['admin', 'ceo', 'qmo'], $_SESSION['user']['permissions']))) $this->response([], 401);
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
		// forms
		$selecttypes[LANG::GET('audit.checks_type.forms')] = ['value' => 'forms'];
		if ($this->_requestedType==='forms') $selecttypes[LANG::GET('audit.checks_type.forms')]['selected'] = true;
		// user certificates
		$selecttypes[LANG::GET('audit.checks_type.userfiles')] = ['value' => 'userfiles'];
		if ($this->_requestedType==='userfiles') $selecttypes[LANG::GET('audit.checks_type.userfiles')]['selected'] = true;
		// vendor list
		$selecttypes[LANG::GET('audit.checks_type.vendors')] = ['value' => 'vendors'];
		if ($this->_requestedType==='vendors') $selecttypes[LANG::GET('audit.checks_type.vendors')]['selected'] = true;
		// regulatory issues
		$selecttypes[LANG::GET('audit.checks_type.regulatory')] = ['value' => 'regulatory'];
		if ($this->_requestedType==='regulatory') $selecttypes[LANG::GET('audit.checks_type.regulatory')]['selected'] = true;

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
	
	/**
	 * returns all sample checks from the caro_checks database in descending chronological order
	 * displays a warning if a vendor is overdue for sample check
	 */
	private function mdrsamplecheck(){
		$content = $unchecked = $entries = [];
		// get unchecked articles for MDR §14 sample check
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-not-checked'));
		$statement->execute();
		$unchecked = array_unique(array_map(fn($r) => $r['vendor_name'], $statement->fetchAll(PDO::FETCH_ASSOC)));
		// display warning
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
					'value' => LANG::GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'exportchecks', '" . $this->_requestedType . "')"
				]
			]
		];
		// add check records
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

	/**
	 * returns all incorporation records from the caro_checks database in descending chronological order
	 * displays a warning if products within approved orders require an incorporation
	 */
	private function incorporation(){
		$content = $orderedunincorporated = $entries = [];
		// get unincorporated articles from approved orders
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-not-incorporated'));
		$statement->execute();
		$unincorporated = $statement->fetchAll(PDO::FETCH_ASSOC);

		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get-approved-order-by-substr'));
		$statement->execute([
			':substr' => 'ordernumber_label'
		]);
		$approvedorders = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach ($approvedorders as $row){
			$decoded_order_data = json_decode($row['order_data'], true);
			if (array_key_exists('ordernumber_label', $decoded_order_data) && ($tocheck = array_search($decoded_order_data['ordernumber_label'], array_column($unincorporated, 'article_no'))) !== false){
				if (array_key_exists('vendor_label', $decoded_order_data) && $unincorporated[$tocheck]['vendor_name'] === $decoded_order_data['vendor_label']){
					$article = $decoded_order_data['ordernumber_label'] . $decoded_order_data['vendor_label'];
					if (!in_array($article, $orderedunincorporated)) $orderedunincorporated[] = $article;
				}
			}
		}
		// display warning
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
					'value' => LANG::GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'exportchecks', '" . $this->_requestedType . "')"
				]
			]
		];
		// add check records
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

	/**
	 * creates and returns a download link to the export file for requested check
	 * if check type within caro_checks database
	 */
	public function exportchecks(){
		if (!(array_intersect(['admin', 'ceo', 'qmo'], $_SESSION['user']['permissions']))) $this->response([], 401);

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
		// stringify check records
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

	/**
	 * returns all users with file attachments to review e.g. certificates
	 */
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

	/**
	 * returns all current approved forms with their respective components and approvement notes in alphabetical order
	 * also form bundles and available external documents
	 */
	private function forms(){
		$content = [];

		// get all current approved forms
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-datalist-approved'));
		$statement->execute();
		$forms = $statement->fetchAll(PDO::FETCH_ASSOC);
		$hidden = $currentforms = [];
		foreach($forms as $form){
			if ($form['hidden']) $hidden[] = $form['name']; // since ordered by recent, older items will be skipped
			if (!in_array($form['name'], array_column($currentforms, 'name')) && !in_array($form['name'], $hidden)) $currentforms[] = $form;
		}

		// get all current bundles
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

		// iterate over forms an their respective components
		foreach($currentforms as $form){
			$components = explode(',', $form['content'] ? : '');
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
			$regulatory_context = [];
			foreach(explode(',', $form['regulatory_context'] ? : '') as $context){
				if (array_key_exists($context, LANGUAGEFILE['regulatory'])) $regulatory_context[] = LANGUAGEFILE['regulatory'][$context];
			}
			$formscontent[] = [
				'type' => 'text',
				'description' => $form['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $form['author'], ':date' => $form['date']]),
				'content' => LANG::GET('audit.documents_in_use_approved', [
					':permission' => LANG::GET('permissions.supervisor'),
					':name' => json_decode($form['supervisor_approval'], true)['name'],
					':date' => json_decode($form['supervisor_approval'], true)['date'],
				]) . "\n" .
				LANG::GET('audit.documents_in_use_approved', [
					':permission' => LANG::GET('permissions.qmo'),
					':name' => json_decode($form['qmo_approval'], true)['name'],
					':date' => json_decode($form['qmo_approval'], true)['date'],
				]) . "\n" .
				LANG::GET('audit.documents_in_use_approved', [
					':permission' => LANG::GET('permissions.ceo'),
					':name' => json_decode($form['ceo_approval'], true)['name'],
					':date' => json_decode($form['ceo_approval'], true)['date'],
				]) . "\n \n" . implode("\n \n", $componentlist) . "\n \n" . implode("\n", $regulatory_context)
			];
		}

		$externalcontent = [
			[
				'type' => 'text',
				'description' => LANG::GET('audit.documents_in_use_external'),
				'content' => ''
			]
		];
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('file_external_documents-get-active'));
		$statement->execute();
		if ($files = $statement->fetchAll(PDO::FETCH_ASSOC)) {
			foreach ($files as $file){
				$externalcontent[] = [
					'type' => 'text',
					'description' => $file['path'],
					'content' => LANG::GET('file.external_file_introduced', [':user' => $file['user'], ':date' => date('Y-m-d H:i', filemtime($file['path']))])
				];
			}
		}

		$bundlescontent = [
			[
				'type' => 'text',
				'description' => LANG::GET('audit.documents_in_use_bundles'),
				'content' => ''
			]
		];
		foreach($currentbundles as $bundle){
			$formslist = explode(',', $bundle['content'] ? : '');
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
					'value' => LANG::GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'exportforms', '" . $this->_requestedType . "')"
				]
			]
		];
		
		$content[] = $formscontent;
		$content[] = $externalcontent;
		$content[] = $bundlescontent;
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for forms and form bundles
	 * processes the result of $this->forms() and translates the body object into more simple strings
	 */
	public function exportforms(){
		if (!(array_intersect(['admin', 'ceo', 'qmo'], $_SESSION['user']['permissions']))) $this->response([], 401);

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

	/**
	 * returns all current active vendors with stored info, most recent pricelist import, MDR sample check and certificate details in alphabetical order
	 */
	private function vendors(){
		$vendorlist = $hidden = [];
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-vendor-datalist'));
		$statement->execute();
		$vendors = $statement->fetchAll(PDO::FETCH_ASSOC);
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-last_checked'));
		$statement->execute();
		$lastchecks = $statement->fetchAll(PDO::FETCH_ASSOC);

		// add export button
		$content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'exportvendors')"
				]
			]
		];
		foreach($vendors as $vendor){
			$info = '';
			if ($vendor['active']) {
				if ($vendor['info']) $info .= LANG::GET('consumables.edit_vendor_info') . ': ' . $vendor['info'] . "\n";
				$pricelist = json_decode($vendor['pricelist'], true);
				if ($pricelist['validity']) $info .= LANG::GET('consumables.edit_vendor_pricelist_validity') . ' ' . $pricelist['validity'] . "\n";
				if (($samplecheck = array_search($vendor['id'], array_column($lastchecks, 'vendor_id'))) !== false) $info .= LANG::GET('audit.checks_type.mdrsamplecheck') . ' ' . $lastchecks[$samplecheck]['checked'] . "\n";
				$certificate = json_decode($vendor['certificate'], true);
				if ($certificate['validity']) $info .= LANG::GET('consumables.edit_vendor_certificate_validity') . ' ' . $certificate['validity'] . "\n";
				$vendorlist[] = [
					'type' => 'text',
					'description' => $vendor['name'],
					'content' => $info
				];
				
				$certificates = [];
				$certfiles = UTILITY::listFiles(UTILITY::directory('vendor_certificates', [':name' => $vendor['immutable_fileserver']]));
				foreach($certfiles as $path){
					$certificates[pathinfo($path)['basename']] = ['target' => '_blank', 'href' => substr($path, 1)];
				}
				if ($certificates) $vendorlist[] = [
					'type' => 'links',
					'description' => LANG::GET('consumables.edit_vendor_documents_download'),
					'content' => $certificates
				];
			}
		}
		$content[] = $vendorlist;
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for the vendor list
	 * processes the result of $this->vendors() and translates the body object into more simple strings
	 */
	public function exportvendors(){
		if (!(array_intersect(['admin', 'ceo', 'qmo'], $_SESSION['user']['permissions']))) $this->response([], 401);

		$summary = [
			'filename' => preg_replace('/[^\w\d]/', '', LANG::GET('audit.checks_type.vendors') . '_' . date('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('audit.checks_type.vendors'),
			'date' => date('y-m-d H:i')
		];

		$vendors = $this->vendors();
		$previous = ''; // given there's a text followed by links
		foreach($vendors[1] as $item){
			if ($item['type'] === 'text') {
				$summary['content'][$item['description']] = $item['content'];
				$previous = $item['description'];
			}
			if ($item['type'] === 'links') $summary['content'][$previous] .= "\n" . implode("\n", array_keys($item['content']));
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

	/**
	 * returns regulatory items according to language.xx.ini and matches current assigned forms
	 */
	public function regulatory(){
		$content = $issues = [];
		// prepare existing forms lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-datalist-approved'));
		$statement->execute();
		$fd = $statement->fetchAll(PDO::FETCH_ASSOC);
		$hidden = $regulatory = [];
		foreach($fd as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden)) {
				foreach(explode(',', $row['regulatory_context'] ? : '') as $regulatory_context){
					$regulatory[$regulatory_context][$row['name'] . ' (' . $row['date'] . ')'] = ['href' => "javascript:api.record('get', 'form', '" . $row['name'] . "')"];
				}
			}
		}
		// get active external documents
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('file_external_documents-get-active'));
		$statement->execute();
		if ($files = $statement->fetchAll(PDO::FETCH_ASSOC)) {
			foreach ($files as $file){
				foreach(explode(',', $file['regulatory_context']) as $context){
					$regulatory[$context][$file['path'] . ' (' . date('Y-m-d H:i', filemtime($file['path'])) . ')'] = ['href' => substr($file['path'], 1)];
				}
			}
		}

		// add export button
		$content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'exportregulatory')"
				]
			]
		];
		foreach(LANGUAGEFILE['regulatory'] as $key => $issue){
			if (array_key_exists($key, $regulatory)) $issues[] = [
				'type' => 'links',
				'description' => $issue,
				'content' => $regulatory[$key]
			];
			else $issues[] = [
				'type' => 'text',
				'description' => $issue,
				'content' => LANG::GET('audit.regulatory_warning_content')
			];
		}
		$content[] = $issues;
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for the regulatory issue result
	 * processes the result of $this->regulatory() and translates the body object into more simple strings
	 */
	public function exportregulatory(){
		if (!(array_intersect(['admin', 'ceo', 'qmo'], $_SESSION['user']['permissions']))) $this->response([], 401);

		$summary = [
			'filename' => preg_replace('/[^\w\d]/', '', LANG::GET('audit.checks_type.regulatory') . '_' . date('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('audit.checks_type.regulatory'),
			'date' => date('y-m-d H:i')
		];

		$issues = $this->regulatory();
		foreach($issues[1] as $item){
			$summary['content'][$item['description']] = $item['content'];	
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
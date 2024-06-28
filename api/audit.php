<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2024 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// audit overview and export
require_once('./_pdf.php');

class AUDIT extends API {
	/**
	 * in case you want to create a code from any values in any form add 
	 * 'data-usecase' => 'tool_create_code'
	 * to inputs attributes (hidden if applicable)
	 */

	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedType = null;

	/**
	 * init parent class and set private requests
	 */
	public function __construct(){
		parent::__construct();
		if (!PERMISSION::permissionFor('audits')) $this->response([], 401);

		$this->_requestedType = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	/**
	 * returns the latest approved form, component by name from query
	 * @param string $query as defined within sqlinterface
	 * @param string $name
	 * @return array|bool either query row or false
	 */
	private function latestApprovedName($query = '', $name = ''){
		// get latest approved by name
		$elements = SQLQUERY::EXECUTE($this->_pdo, $query, [
			'values' => [
				':name' => $name
			]
		]);
		foreach ($elements as $element){
			if (PERMISSION::fullyapproved('formapproval', $element['approval'])) return $element;
		}
		return false;
	}

	/**
	 * main entry point for module
	 * displays a selection of available options
	 * calls $this->_requestedType method if set
	 */
	public function checks(){
		$result['body'] = ['content' => []];
		$selecttypes = [];
		
		// checks
		$types = SQLQUERY::EXECUTE($this->_pdo, 'checks_get_types');
		foreach($types as $type){
			$selecttypes[LANG::GET('audit.checks_type.' . $type['type'])] = ['value' => $type['type']];
			if ($this->_requestedType===$type['type']) $selecttypes[LANG::GET('audit.checks_type.' . $type['type'])]['selected'] = true;
		}
		// incorporated products
		$selecttypes[LANG::GET('audit.checks_type.incorporation')] = ['value' => 'incorporation'];
		if ($this->_requestedType==='incorporation') $selecttypes[LANG::GET('audit.checks_type.incorporation')]['selected'] = true;
		// forms
		$selecttypes[LANG::GET('audit.checks_type.forms')] = ['value' => 'forms'];
		if ($this->_requestedType==='forms') $selecttypes[LANG::GET('audit.checks_type.forms')]['selected'] = true;
		// user skills and certificates
		$selecttypes[LANG::GET('audit.checks_type.userskills')] = ['value' => 'userskills'];
		if ($this->_requestedType==='userskills') $selecttypes[LANG::GET('audit.checks_type.userskills')]['selected'] = true;
		// skill fulfilment
		$selecttypes[LANG::GET('audit.checks_type.skillfulfilment')] = ['value' => 'skillfulfilment'];
		if ($this->_requestedType==='skillfulfilment') $selecttypes[LANG::GET('audit.checks_type.skillfulfilment')]['selected'] = true;
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
		$validChecked = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_valid_checked');
		$notReusableChecked = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_not_reusable_checked');
		$sampleCheck = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_eligible_sample_check', ['replacements' => [
			':valid_checked' => implode(',', array_column($validChecked, 'vendor_id')),
			':not_reusable' => implode(',', array_column($notReusableChecked, 'id'))
		]]);

		$unchecked = array_unique(array_map(fn($r) => $r['vendor_name'], $sampleCheck));
		// display warning
		if ($unchecked) $content[] = [
			[
				'type' => 'textblock',
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
		$checks = SQLQUERY::EXECUTE($this->_pdo, 'checks_get', [
			'values' => [
				':type' => $this->_requestedType
			]
		]);
		foreach($checks as $row){
			$entries[] = [
				'type' => 'textblock',
				'description' => LANG::GET('audit.check_description', [
					':check' => LANG::GET('audit.checks_type.' . $this->_requestedType),
					':date' => $row['date'],
					':author' => $row['author']
				]),
				'content' => $row['content']
			];
			$entries[] = [
				'type' => 'button',
				'attributes' => [
					'type' => 'button',
					'value' => LANG::GET('audit.sample_check_revoke'),
					'onpointerup' => "new Dialog({type:'confirm', header:'" . LANG::GET('order.disapprove') . "', " .
						"options:{'" . LANG::GET('order.disapprove_message_cancel') . "': false, '" . LANG::GET('audit.sample_check_revoke_confirm') . "': {value: true, class: 'reducedCTA'}}}).then(response => {" .
						"if (response !== false) {" .
						"api.purchase('delete', 'mdrsamplecheck', " . $row['id']. "); this.disabled=true" .
						"}});"
				]
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
		$checks = SQLQUERY::EXECUTE($this->_pdo, 'checks_get', [
			'values' => [
				':type' => $this->_requestedType
			]
		]);
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
	 * returns all incorporation records from the products database in descending chronological order
	 * displays a warning if products within approved orders require an incorporation
	 */
	private function incorporation(){
		$content = $orderedunincorporated = $entries = $incorporated = [];
		// get unincorporated articles from approved orders
		$unincorporated = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products_incorporation');
		foreach($unincorporated as $id => $row){
			if (!$row['incorporated']) continue;
			$row['incorporated'] = json_decode($row['incorporated'], true);
			if (!PERMISSION::fullyapproved('incorporation', $row['incorporated'])) continue;
			$incorporated[] = $row;
			unset($unincorporated[$id]);
		}

		$approvedorders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_substr', [
			'values' => [
				':substr' => 'ordernumber_label'
			]
		]);
		foreach ($approvedorders as $row){
			$decoded_order_data = json_decode($row['order_data'], true);
			if (array_key_exists('ordernumber_label', $decoded_order_data) && ($tocheck = array_search($decoded_order_data['ordernumber_label'], array_column($unincorporated, 'article_no'))) !== false){
				if (array_key_exists('vendor_label', $decoded_order_data) && (array_key_exists($tocheck, $unincorporated) && $unincorporated[$tocheck]['vendor_name'] === $decoded_order_data['vendor_label'])){
					$article = $decoded_order_data['ordernumber_label'] . $decoded_order_data['vendor_label'];
					if (!in_array($article, $orderedunincorporated)) $orderedunincorporated[] = $article;
				}
			}
		}
		// display warning
		if ($orderedunincorporated) $content[] = [
			[
				'type' => 'textblock',
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
					'onpointerup' => "api.audit('get', 'exportincorporation', '" . $this->_requestedType . "')"
				]
			]
		];
		// add incorporations
		// order descending based on approval date of random authorized person. a bit fuzzy though. hope all act within a reasonable time
		$permission = PERMISSION::permissionFor('incorporation', true)[array_rand(PERMISSION::permissionFor('incorporation', true))];
		usort($incorporated, function ($a, $b) use ($permission) {
			if ($a['incorporated'][$permission]['date'] === $b['incorporated'][$permission]['date']) return 0;
			return $a['incorporated'][$permission]['date'] < $b['incorporated'][$permission]['date'] ? -1: 1;
		});

		foreach($incorporated as $product){
			$incorporationInfo = str_replace(["\r", "\n"], ['', " \n"], $product['incorporated']['_check']);
			foreach(['user', ...PERMISSION::permissionFor('incorporation', true)] as $permission){
				if (array_key_exists($permission, $product['incorporated'])) $incorporationInfo .= " \n" . LANGUAGEFILE['permissions'][$permission] . ' ' . $product['incorporated'][$permission]['name'] . ' ' . $product['incorporated'][$permission]['date'];
			}
			$entries[] = [
				'type' => 'textblock',
				'description' => $product['vendor_name'] . ' ' . $product['article_no'] . ' ' . $product['article_name'],
				'content' => $incorporationInfo
			];
		}
		if ($entries) $content[] = $entries;
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for forms and form bundles
	 * processes the result of $this->forms() and translates the body object into more simple strings
	 */
	public function exportincorporation(){
		$summary = [
			'filename' => preg_replace('/[^\w\d]/', '', LANG::GET('audit.checks_type.' . $this->_requestedType) . '_' . date('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('audit.checks_type.' . $this->_requestedType),
			'date' => date('y-m-d H:i')
		];

		$forms = $this->incorporation();

		for($i = 1; $i<count($forms); $i++){
			foreach($forms[$i] as $item){
				if ($item['type'] === 'textblock') $summary['content'][$item['description']] = $item['content'];	
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
	 * returns all users with their skills and file attachments to review e.g. certificates
	 */
	private function userskills(){
		$content = [];
		$unfulfilledskills = [];
		foreach (LANGUAGEFILE['skills'] as $duty => $skills){
			if ($duty === 'LEVEL') continue;
			foreach ($skills as $skill => $skilldescription){
				if ($skill === '_DESCRIPTION') continue;
				$unfulfilledskills[] = LANG::GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription;
			}
		}
		$storedfiles = UTILITY::listFiles(UTILITY::directory('users'), 'asc');
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		foreach ($users as $user){
			$user['skills'] = explode(',', $user['skills'] ?  : '');
			$skillmatrix = '';
			foreach (LANGUAGEFILE['skills'] as $duty => $skills){
				if ($duty === 'LEVEL') continue;
				foreach ($skills as $skill => $skilldescription){
					if ($skill === '_DESCRIPTION') continue;
					foreach(LANGUAGEFILE['skills']['LEVEL'] as $level => $leveldescription){
						if (in_array($duty . '.' . $skill . '.' . $level, $user['skills'])){
							$skillmatrix .=  " \n" . LANG::GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription . ': ' . $leveldescription;
							unset($unfulfilledskills[array_search(LANG::GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription, $unfulfilledskills)]);
						}
					}
				}
			}
			if ($skillmatrix){
				$content[] = [
					[
						'type' => 'textblock',
						'description' => $user['name'],
						'content' => $skillmatrix
					]
				];
			}

			$userfiles = [];
			foreach ($storedfiles as $file){
				if (explode('_', pathinfo($file)['filename'])[0] == $user['id']) {
					$userfiles[pathinfo($file)['basename']] = ['href' => substr($file, 1)];
				}
			}
			if ($userfiles) {
				if ($skillmatrix) $content[count($content) - 1][] = [
					[
						'type' => 'links',
						'content' => $userfiles
					]
				];
				else $content[] = [
					[
						'type' => 'links',
						'description' => $user['name'],
						'content' => $userfiles
					]
				];
			}
		}
		if ($unfulfilledskills){
			$content = [
				[
					'type' => 'textblock',
					'description' => LANG::GET('audit.userskills_warning_description'),
					'content' => implode(', ', $unfulfilledskills)
				],
				...$content
			];
		}
		return $content;
	}

	/**
	 * returns all skills and matching users
	 */
	private function skillfulfilment(){
		$content = $allskills = [];
		foreach (LANGUAGEFILE['skills'] as $duty => $skills){
			if ($duty === 'LEVEL') continue;
			foreach ($skills as $skill => $skilldescription){
				if ($skill === '_DESCRIPTION') continue;
				$allskills[$duty . '.' . $skill] = [];
			}
		}
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		foreach ($users as $user){
			$user['skills'] = explode(',', $user['skills'] ?  : '');
			foreach ($user['skills'] as $skill){
				$allskills[substr($skill,0,strrpos($skill, '.'))][] = $user['name'];
			}
		}
		foreach ($allskills as $skill => $users){
			if (!$skill) continue;
			$skill = explode('.', $skill);
			$content[] = [
				[
					'type' => 'textblock',
					'description' => LANG::GET('skills.' . $skill[0] . '._DESCRIPTION') . ' ' . LANG::GET('skills.' . $skill[0] . '.' . $skill[1]),
					'content' => $users ? implode(', ', $users) : LANG::GET('audit.skillfulfilment_warning')
				]
			];
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
		$forms = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		$hidden = $currentforms = [];
		foreach($forms as $form){
			if (!PERMISSION::fullyapproved('formapproval', $form['approval'])) continue;
			if ($form['hidden']) $hidden[] = $form['name']; // since ordered by recent, older items will be skipped
			if (!in_array($form['name'], array_column($currentforms, 'name')) && !in_array($form['name'], $hidden)) $currentforms[] = $form;
		}

		// get all current bundles
		$bundles = SQLQUERY::EXECUTE($this->_pdo, 'form_bundle_datalist');
		$hidden = $currentbundles = [];
		foreach($bundles as &$bundle){
			if ($bundle['hidden']) $hidden[] = $bundle['name']; // since ordered by recent, older items will be skipped
			if (!in_array($bundle['name'], array_column($currentbundles, 'name')) && !in_array($bundle['name'], $hidden)) $currentbundles[] = $bundle;
		}

		$formscontent = [
			[
				'type' => 'textblock',
				'description' => LANG::GET('audit.documents_in_use_documents'),
				'content' => ''
			]
		];

		// iterate over forms an their respective components
		foreach($currentforms as $form){
			$components = explode(',', $form['content'] ? : '');
			$componentlist = [];
			foreach($components as $component){
				$cmpnnt = $this->latestApprovedName('form_component_get_by_name', $component);
				if ($cmpnnt)
					$cmpnnt['approval'] = json_decode($cmpnnt['approval'], true);
					$entry = $cmpnnt['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $cmpnnt['author'], ':date' => $cmpnnt['date']]) . "\n";
					foreach($cmpnnt['approval'] as $position => $data){
						$entry .= LANG::GET('audit.documents_in_use_approved', [
							':permission' => LANG::GET('permissions.' . $position),
							':name' => $data['name'],
							':date' => $data['date'],
						]) . "\n";
					}
					$componentlist[] = $entry;
			}
			$regulatory_context = [];
			foreach(explode(',', $form['regulatory_context'] ? : '') as $context){
				if (array_key_exists($context, LANGUAGEFILE['regulatory'])) $regulatory_context[] = LANGUAGEFILE['regulatory'][$context];
			}
			$entry = '';
			foreach($cmpnnt['approval'] as $position => $data){
				$entry .= LANG::GET('audit.documents_in_use_approved', [
					':permission' => LANG::GET('permissions.' . $position),
					':name' => $data['name'],
					':date' => $data['date'],
				]) . "\n";
			}

			$formscontent[] = [
				'type' => 'textblock',
				'description' => $form['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $form['author'], ':date' => $form['date']]),
				'content' => $entry . "\n \n" . implode("\n \n", $componentlist) . "\n \n" . implode("\n", $regulatory_context)
			];
		}

		$externalcontent = [
			[
				'type' => 'textblock',
				'description' => LANG::GET('audit.documents_in_use_external'),
				'content' => ''
			]
		];
		if ($files = SQLQUERY::EXECUTE($this->_pdo, 'file_external_documents_get_active')) {
			foreach ($files as $file){
				$externalcontent[] = [
					'type' => 'textblock',
					'description' => $file['path'],
					'content' => LANG::GET('file.external_file_introduced', [':user' => $file['author'], ':date' => date('Y-m-d H:i', filemtime($file['path']))])
				];
			}
		}

		$bundlescontent = [
			[
				'type' => 'textblock',
				'description' => LANG::GET('audit.documents_in_use_bundles'),
				'content' => ''
			]
		];
		foreach($currentbundles as $bundle){
			$formslist = explode(',', $bundle['content'] ? : '');
			natsort($formslist);
			$bundlescontent[] = [
				'type' => 'textblock',
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
		$vendors = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
		$lastchecks = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_last_checked');
		$vendor_info = [
			'infotext' => 'consumables.edit_vendor_info',
			'mail' => 'consumables.edit_vendor_mail',
			'phone' => 'consumables.edit_vendor_phone',
			'address' => 'consumables.edit_vendor_address',
			'sales_representative' => 'consumables.edit_vendor_sales_representative',
			'customer_id' => 'consumables.edit_vendor_customer_id',
		];

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
				if ($vendor['info']) {
					$vendor['info'] = json_decode($vendor['info'], true) ? : [];
					$info .= implode(" \n", array_map(Fn($key, $value) => $value ? LANG::GET($vendor_info[$key]) . ': ' . $value : false, array_keys($vendor['info']), $vendor['info'])) . "\n";
				}
				$pricelist = json_decode($vendor['pricelist'], true);
				if ($pricelist['validity']) $info .= LANG::GET('consumables.edit_vendor_pricelist_validity') . ' ' . $pricelist['validity'] . "\n";
				if (($samplecheck = array_search($vendor['id'], array_column($lastchecks, 'vendor_id'))) !== false) $info .= LANG::GET('audit.checks_type.mdrsamplecheck') . ' ' . $lastchecks[$samplecheck]['checked'] . "\n";
				$certificate = json_decode($vendor['certificate'], true);
				if ($certificate['validity']) $info .= LANG::GET('consumables.edit_vendor_certificate_validity') . ' ' . $certificate['validity'] . "\n";
				$vendorlist[] = [
					'type' => 'textblock',
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
			if ($item['type'] === 'textblock') {
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
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		$hidden = $regulatory = [];
		foreach($fd as $key => $row) {
			if (!PERMISSION::fullyapproved('formapproval', $row['approval'])) continue;
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden)) {
				foreach(explode(',', $row['regulatory_context'] ? : '') as $regulatory_context){
					$regulatory[$regulatory_context][$row['name'] . ' (' . $row['date'] . ')'] = ['href' => "javascript:api.record('get', 'form', '" . $row['name'] . "')"];
				}
			}
		}
		// get active external documents
		if ($files = SQLQUERY::EXECUTE($this->_pdo, 'file_external_documents_get_active')) {
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
				'type' => 'textblock',
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
?>
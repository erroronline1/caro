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
include_once("../libraries/xlsxwriter.class.php");

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
	 *       _           _
	 *   ___| |_ ___ ___| |_ ___
	 *  |  _|   | -_|  _| '_|_ -|
	 *  |___|_|_|___|___|_,_|___|
	 *
	 * main entry point for module
	 * displays a selection of available options
	 * calls $this->_requestedType method if set
	 */
	public function checks(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'GET':
				$result['render'] = ['content' => []];
				$selecttypes = [];
				
				// checks
				$types = SQLQUERY::EXECUTE($this->_pdo, 'checks_get_types');
				foreach($types as $type){
					$selecttypes[LANG::GET('audit.checks_type.' . $type['type'])] = ['value' => $type['type']];
					if ($this->_requestedType === $type['type']) $selecttypes[LANG::GET('audit.checks_type.' . $type['type'])]['selected'] = true;
				}
				foreach([
					'incorporation', // incorporated products
					'forms', // forms and components
					'userskills', // user skills and certificates
					'skillfulfilment', // skill fulfilment
					'userexperience', // experience points per user and year
					'vendors', // vendor list
					'orderstatistics', // order statistics
					'complaints', // complaints within records
					'regulatory', // regulatory issues
					'risks', // risks
					] as $category){
						$selecttypes[LANG::GET('audit.checks_type.' . $category)] = ['value' => $category];
						if ($this->_requestedType === $category) $selecttypes[LANG::GET('audit.checks_type.' . $category)]['selected'] = true;
				}
				ksort($selecttypes);
				$result['render']['content'][] = [
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
					if ($append = $this->{$this->_requestedType}()) array_push($result['render']['content'] , ...$append);
				}
				$this->response($result);
				break;
			case 'DELETE':
				if (!PERMISSION::permissionFor('auditsoperation')) $this->response([], 401);
				$permitted = [
					'orderstatistics'
				];
				if (in_array($this->_requestedType, $permitted)) $this->{'delete' . $this->_requestedType}();
				break;
		}
	}

	/**
	 *                     _     _     _       
	 *   ___ ___ _____ ___| |___|_|___| |_ ___ 
	 *  |  _| . |     | . | | .'| |   |  _|_ -|
	 *  |___|___|_|_|_|  _|_|__,|_|_|_|_| |___|
	 *                |_|
	 * list and link complaints from records, sum by year
	 */
	private function complaints(){
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_identifiers');
		$entries = $content = [];
		foreach ($data as $row){
			if ($row['complaint']){
				$year = substr($row['date'], 0, 4);
				if (!isset($entries[$year])) $entries[$year] = [];
				$closed = SQLQUERY::EXECUTE($this->_pdo, 'records_touched', [
					'values' => [
						':id' => $row['id']
						]
					]);
				$closed = $closed ? $closed[0] : '';
				$entries[$year][$row['identifier']] = ['closed' => json_decode($closed['closed'] ? : '', true), 'units' => $row['units']];
			}
		}
		//order by year descending
		arsort($entries);
		foreach ($entries as $year => $cases){
			$current = $links = [];
			$current[] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => strval($year)
				],
				'content' => LANG::GET('audit.complaints_summary', [':number' => count($cases), ':closed' => count(array_filter($cases, Fn($c) => PERMISSION::fullyapproved('complaintclosing', $c['closed'])))])
			];
			foreach ($cases as $identifier => $property){
				$units = implode(', ', array_map(Fn($u)=> LANGUAGEFILE['units'][$u], explode(',', $property['units'])));
				$linkdescription = LANG::GET('audit.complaints_case_description', [':identifier' => $identifier, ':units' => $units]);
				if (PERMISSION::fullyapproved('complaintclosing', $property['closed'])) {
					$linkdescription .= LANG::GET('audit.complaints_closed');
				}
				$links[$linkdescription] = ['href' => "javascript:api.record('get', 'record', '" . $identifier . "')"];
				if (PERMISSION::pending('complaintclosing', $property['closed'])) {
					$links[$linkdescription]['class'] = 'orange';
				}
			}
			$current[] = [
				'type' => 'links',
				'content' => $links
			];
			$content[] = $current;
		}
		return $content;
	}
	
	/**
	 *                       _
	 *   ___ _ _ ___ ___ ___| |_
	 *  | -_|_'_| . | . |  _|  _|
	 *  |___|_,_|  _|___|_| |_|
	 *          |_|
	 * main entry point for exports
	 * calls export . $this->_requestedType method
	 */
	public function export(){
		if (!PERMISSION::permissionFor('auditsoperation')) $this->response([], 401);
		$static = [
			'incorporation',
			'forms',
			'userskills',
			'skillfulfilment',
			'userexperience',
			'vendors',
			'orderstatistics',
			'complaints',
			'regulatory',
			'risks'
		];
		if (in_array($this->_requestedType, $static)) $this->{'export' . $this->_requestedType}();
		else $this->exportchecks();
	}

	/**
	 *                       _       _           _
	 *   ___ _ _ ___ ___ ___| |_ ___| |_ ___ ___| |_ ___
	 *  | -_|_'_| . | . |  _|  _|  _|   | -_|  _| '_|_ -|
	 *  |___|_,_|  _|___|_| |_| |___|_|_|___|___|_,_|___|
	 *          |_|
	 * creates and returns a download link to the export file for requested check
	 * if check type within caro_checks database
	 */
	private function exportchecks(){
		$checks = SQLQUERY::EXECUTE($this->_pdo, 'checks_get', [
			'values' => [
				':type' => $this->_requestedType
			]
		]);
		$summary = [
			'filename' => preg_replace('/' . INI['forbidden']['names'][0] . '/', '', LANG::GET('audit.checks_type.' . $this->_requestedType) . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('audit.checks_type.' . $this->_requestedType),
			'date' => $this->_currentdate->format('y-m-d H:i')
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
			'render' => $body,
		]);
	}

	/**
	 *   ___
	 *  |  _|___ ___ _____ ___
	 *  |  _| . |  _|     |_ -|
	 *  |_| |___|_| |_|_|_|___|
	 *
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
				'type' => 'textsection',
				'attributes' => [
					'name' => LANG::GET('audit.documents_in_use_documents')
				],
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
				'type' => 'textsection',
				'attributes' => [
					'name' => $form['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $form['author'], ':date' => $form['date']])
				],
				'content' => $entry . "\n \n" . implode("\n \n", $componentlist) . "\n \n" . implode("\n", $regulatory_context)
			];
		}

		$externalcontent = [
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => LANG::GET('audit.documents_in_use_external')
				],
				'content' => ''
			]
		];
		if ($files = SQLQUERY::EXECUTE($this->_pdo, 'file_external_documents_get_active')) {
			foreach ($files as $file){
				$date = new DateTime('@' . filemtime($file['path']), new DateTimeZone(INI['application']['timezone']));
				$externalcontent[] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $file['path']
					],
					'content' => LANG::GET('file.external_file_introduced', [':user' => $file['author'], ':introduced' => $date->format('Y-m-d H:i')])
				];
			}
		}

		$bundlescontent = [
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => LANG::GET('audit.documents_in_use_bundles')
				],
				'content' => ''
			]
		];
		foreach($currentbundles as $bundle){
			$formslist = explode(',', $bundle['content'] ? : '');
			natsort($formslist);
			$bundlescontent[] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => $bundle['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $bundle['author'], ':date' => $bundle['date']])
				],
				'content' => implode("\n", $formslist)
			];
		}

		// add export button
		if (PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
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
	private function exportforms(){
		$summary = [
			'filename' => preg_replace('/' . INI['forbidden']['names'][0] . '/', '', LANG::GET('audit.checks_type.' . $this->_requestedType) . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('audit.checks_type.' . $this->_requestedType),
			'date' => $this->_currentdate->format('y-m-d H:i')
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
			'render' => $body,
		]);
	}

	/**
	 *   _                                 _   _
	 *  |_|___ ___ ___ ___ ___ ___ ___ ___| |_|_|___ ___
	 *  | |   |  _| . |  _| . | . |  _| .'|  _| | . |   |
	 *  |_|_|_|___|___|_| |  _|___|_| |__,|_| |_|___|_|_|
	 *                    |_|
	 * returns all incorporation records from the products database in descending chronological order
	 * displays a warning if products within approved orders require an incorporation
	 */
	private function incorporation(){
		$content = $orderedunincorporated = $entries = $incorporated = [];
		// get unincorporated articles from approved orders
		$unincorporated = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products_incorporation_attention');
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
				'type' => 'textsection',
				'attributes' => [
					'name' => LANG::GET('audit.incorporation_warning_description')
				],
				'content' => LANG::GET('audit.incorporation_warning_content', [':amount' => count($orderedunincorporated)])
			]
		];
		// add export button
		if (PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
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
				'type' => 'textsection',
				'attributes' => [
					'name' => $product['vendor_name'] . ' ' . $product['article_no'] . ' ' . $product['article_name']
				],
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
	private function exportincorporation(){
		$summary = [
			'filename' => preg_replace('/' . INI['forbidden']['names'][0] . '/', '', LANG::GET('audit.checks_type.' . $this->_requestedType) . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('audit.checks_type.' . $this->_requestedType),
			'date' => $this->_currentdate->format('y-m-d H:i')
		];

		$forms = $this->incorporation();

		for($i = 1; $i<count($forms); $i++){
			foreach($forms[$i] as $item){
				if ($item['type'] === 'textsection' && isset($item['attributes']['name'])) $summary['content'][$item['attributes']['name']] = $item['content'];	
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
			'render' => $body,
		]);
	}

	/**
	 *   _     _           _                                 _
	 *  | |___| |_ ___ ___| |_ ___ ___ ___ ___ ___ _ _ ___ _| |___ ___ _____ ___
	 *  | | .'|  _| -_|_ -|  _| .'| . | . |  _| . | | | -_| . |   | .'|     | -_|
	 *  |_|__,|_| |___|___|_| |__,|  _|  _|_| |___|\_/|___|___|_|_|__,|_|_|_|___|
	 *                            |_| |_|
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
	 *           _                       _         _           _
	 *   _____ _| |___ ___ ___ _____ ___| |___ ___| |_ ___ ___| |_
	 *  |     | . |  _|_ -| .'|     | . | | -_|  _|   | -_|  _| '_|
	 *  |_|_|_|___|_| |___|__,|_|_|_|  _|_|___|___|_|_|___|___|_,_|
	 *                              |_|
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
				'type' => 'textsection',
				'attributes' => [
					'name' => LANG::GET('audit.mdrsamplecheck_warning_description')
				],
				'content' => LANG::GET('audit.mdrsamplecheck_warning_content', [':vendors' => implode(', ', $unchecked)])
			]
		];
		// add export button
		if (PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
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
				'type' => 'textsection',
				'attributes' => [
					'name' => LANG::GET('audit.check_description', [
						':check' => LANG::GET('audit.checks_type.' . $this->_requestedType),
						':date' => $row['date'],
						':author' => $row['author']
					])
				],
				'content' => $row['content']
			];
			if(PERMISSION::permissionFor('auditsoperation')) $entries[] = [
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
	 *             _             _       _   _     _   _
	 *   ___ ___ _| |___ ___ ___| |_ ___| |_|_|___| |_|_|___ ___
	 *  | . |  _| . | -_|  _|_ -|  _| .'|  _| |_ -|  _| |  _|_ -|
	 *  |___|_| |___|___|_| |___|_| |__,|_| |_|___|_| |_|___|___|
	 *
	 * returns export and delete options for order statistics
	 */
	private function orderstatistics(){
		$content = [];

		$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_order_statistics');
		$content[] = [
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => LANG::GET('audit.orderstatistics_number', [':number' => count($orders)])
				],
				'content' => count($orders) ? LANG::GET('audit.orderstatistics_info') : ''
			]
		];

		if (count($orders) && PERMISSION::permissionFor('auditsoperation')){
			// add export button
			$content[] = [
				[
					'type' => 'button',
					'attributes' => [
						'value' => LANG::GET('audit.record_export_xlsx'),
						'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
					]
				]
			];
			$content[] = [
				[
					'type' => 'deletebutton',
					'attributes' => [
						'value' => LANG::GET('audit.orderstatistics_truncate'),
						'onpointerup' => "new Dialog({type: 'confirm', header: '". LANG::GET('audit.orderstatistics_truncate') ."', options:{".
						"'".LANG::GET('general.cancel_button')."': false,".
						"'".LANG::GET('audit.orderstatistics_truncate_confirm')."': {value: true, class: 'reducedCTA'},".
						"}}).then(confirmation => {if (confirmation) api.audit('delete', 'checks', '" . $this->_requestedType . "');})",
					]
				]
			];
		}
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for the order statistics
	 * export is an xlsx file with orders grouped by vendor sheets
	 */
	private function exportorderstatistics(){
		$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_order_statistics');

		$columns = [
			'vendor_label' => LANG::GET('order.vendor_label'),
			'ordertype' => LANG::GET('order.order_type'),
			'quantity_label' => LANG::GET('order.quantity_label'),
			'unit_label' => LANG::GET('order.unit_label'),
			'ordernumber_label' => LANG::GET('order.ordernumber_label'),
			'productname_label' => LANG::GET('order.productname_label'),
			'additional_info' => LANG::GET('order.additional_info'),
			'ordered' => LANG::GET('order.ordered'),
			'received' => LANG::GET('order.received'),
			'deliverytime' => LANG::GET('audit.order_statistics_delivery_time_column')
		];

		// prepare result as subsets of vendors
		$vendor_orders = [];
		foreach($orders as $order){
			$order['order_data'] = json_decode($order['order_data'], true);
			$deliverytime = '';
			if ($order['received']){
				$datetimezone = new DateTimeZone(INI['application']['timezone']);
				$now = new DateTime('now', $datetimezone);
				$ordered = new DateTime($order['ordered'], $datetimezone);
				$received = new DateTime($order['received'], $datetimezone);
				$deliverytime = intval($ordered->diff($received)->format('%a'));
			}

			if (!isset($order['order_data']['vendor_label'])) $order['order_data']['vendor_label'] = LANG::GET('audit.order_statistics_undefined_vendor');
			if (!isset($vendor_orders[$order['order_data']['vendor_label']])) $vendor_orders[$order['order_data']['vendor_label']] = [];

			$vendor_orders[$order['order_data']['vendor_label']][] = [
				isset($order['order_data']['vendor_label']) ? $order['order_data']['vendor_label'] : '',
				LANG::GET('order.ordertype.' . $order['ordertype']),
				isset($order['order_data']['quantity_label']) ? $order['order_data']['quantity_label'] : '',
				isset($order['order_data']['unit_label']) ? $order['order_data']['unit_label'] : '',
				isset($order['order_data']['ordernumber_label']) ? $order['order_data']['ordernumber_label'] : '',
				isset($order['order_data']['productname_label']) ? $order['order_data']['productname_label'] : '',
				isset($order['order_data']['additional_info']) ? preg_replace('/\\\\n|\\n/', "\n", $order['order_data']['additional_info']) : '',
				$order['ordered'],
				$order['received'],
				$deliverytime
			];
		}
		$tempFile = UTILITY::directory('tmp') . '/' . preg_replace('/[^\w\d]/', '', LANG::GET('audit.checks_type.orderstatistics') . '_' . $this->_currentdate->format('Y-m-d H:i')) . '.xlsx';
		$writer = new XLSXWriter();
		$writer->setAuthor($_SESSION['user']['name']); 

		foreach($vendor_orders as $vendor => $orders){
			$writer->writeSheetRow($vendor, array_values($columns));
			foreach ($orders as $line)
				$writer->writeSheetRow($vendor, $line, $row_options = array('height' => 30,'wrap_text' => true));
		}

		$writer->writeToFile($tempFile);
		$downloadfiles[LANG::GET('menu.record_summary')] = [
			'href' => substr($tempFile, 1),
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' => LANG::GET('record.record_export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}

	/**
	 * truncates the respective database
	 */
	private function deleteorderstatistics(){
		SQLQUERY::EXECUTE($this->_pdo, 'order_truncate_order_statistics');
		$this->response([
			'response' => [
				'msg' => LANG::GET('audit.orderstatistics_truncate_success'),
				'type' => 'success'
			]
		]);
	}

	/**
	 *                   _     _
	 *   ___ ___ ___ _ _| |___| |_ ___ ___ _ _
	 *  |  _| -_| . | | | | .'|  _| . |  _| | |
	 *  |_| |___|_  |___|_|__,|_| |___|_| |_  |
	 *          |___|                     |___|
	 * returns regulatory items according to language.xx.ini and matches current assigned forms
	 */
	private function regulatory(){
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
				$date = new DateTime('@' . filemtime($file['path']), new DateTimeZone(INI['application']['timezone']));
				foreach(explode(',', $file['regulatory_context']) as $context){
					$regulatory[$context][$file['path'] . ' (' . $date->format('Y-m-d H:i') . ')'] = ['href' => substr($file['path'], 1)];
				}
			}
		}

		// add export button
		if(PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
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
				'type' => 'textsection',
				'attributes' => [
					'class' => 'red',
					'name' => $issue
				],
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
	private function exportregulatory(){
		$summary = [
			'filename' => preg_replace('/' . INI['forbidden']['names'][0] . '/', '', LANG::GET('audit.checks_type.regulatory') . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('audit.checks_type.regulatory'),
			'date' => $this->_currentdate->format('y-m-d H:i')
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
			'render' => $body,
		]);
	}

	/**
	 *       _     _
	 *   ___|_|___| |_ ___
	 *  |  _| |_ -| '_|_ -|
	 *  |_| |_|___|_,_|___|
	 *
	 * returns risks
	 */
	private function risks(){
		$content = $issues = [];
		// prepare existing risks lists
		$risks = SQLQUERY::EXECUTE($this->_pdo, 'risk_datalist');
		// add export button
		if(PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
				]
			]
		];
		$process = '';
		$issues = [];
		foreach($risks as $risk){
			if ($risk['process'] !== $process) $issues[] = [[
				'type' => 'textsection',
				'attributes' => [
					'name' => $risk['process']
				],
			]];
			$process = $risk['process'];
			$last_edit = json_decode($risk['last_edit'], true);
			$issues[count($issues)-1][] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => $risk['risk'] .
					" \n" . LANG::GET('risk.cause') . ': ' . $risk['cause']
				],
				'content' => LANG::GET('risk.effect') . ': ' . $risk['effect'] .
				" \n" . LANG::GET('risk.probability') . ': ' . (isset(LANGUAGEFILE['risk']['probabilities'][$risk['probability']-1]) ? LANGUAGEFILE['risk']['probabilities'][$risk['probability'] - 1] : LANGUAGEFILE['risk']['probabilities'][count(LANGUAGEFILE['risk']['probabilities']) - 1]) .
				" \n" . LANG::GET('risk.damage') . ': ' . (isset(LANGUAGEFILE['risk']['damages'][$risk['damage']-1]) ? LANGUAGEFILE['risk']['damages'][$risk['damage'] - 1] : LANGUAGEFILE['risk']['damages'][count(LANGUAGEFILE['risk']['damages']) - 1]) .
				" \n" . ($risk['probability'] * $risk['damage'] > INI['limits']['risk_acceptance_level'] ? LANG::GET('risk.acceptance_level_above') : LANG::GET('risk.acceptance_level_below')) .
				" \n" . LANG::GET('risk.measure') . ': ' . $risk['measure'] .
				" \n" . LANG::GET('risk.measure_probability') . ': ' . (isset(LANGUAGEFILE['risk']['probabilities'][$risk['measure_probability']-1]) ? LANGUAGEFILE['risk']['probabilities'][$risk['measure_probability'] - 1] : LANGUAGEFILE['risk']['probabilities'][count(LANGUAGEFILE['risk']['probabilities']) - 1]) .
				" \n" . LANG::GET('risk.measure_damage') . ': ' . (isset(LANGUAGEFILE['risk']['damages'][$risk['measure_damage']-1]) ? LANGUAGEFILE['risk']['damages'][$risk['measure_damage'] - 1] : LANGUAGEFILE['risk']['damages'][count(LANGUAGEFILE['risk']['damages']) - 1]) .
				" \n" . ($risk['measure_probability'] * $risk['measure_damage'] > INI['limits']['risk_acceptance_level'] ? LANG::GET('risk.acceptance_level_above') : LANG::GET('risk.acceptance_level_below')) .
				" \n" . LANG::GET('risk.risk_benefit') . ': ' . $risk['risk_benefit'] .
				" \n" . LANG::GET('risk.measure_remainder') . ': ' . $risk['measure_remainder'] .
				(isset($last_edit['user']) ? " \n" . LANG::GET('risk.last_edit', [':user' => $last_edit['user'], ':date' => $last_edit['date']]): '')
			];
		}
		array_push($content, ...$issues);
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for risks
	 * processes the result of $this->risks() and translates the body object into more simple strings
	 */
	private function exportrisks(){
		$summary = [
			'filename' => preg_replace('/' . INI['forbidden']['names'][0] . '/', '', LANG::GET('audit.checks_type.risks') . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('audit.checks_type.risks'),
			'date' => $this->_currentdate->format('y-m-d H:i')
		];

		$issues = $this->risks();
		foreach($issues as $process){
			foreach($process as $risk){
				//var_dump($risk);
				if ($risk['type'] === 'textsection' && isset($risk['attributes']['name'])) $summary['content'][$risk['attributes']['name']] = isset($risk['content']) ? $risk['content'] : ' ';	
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
			'render' => $body,
		]);
	}
	
	/**
	 *                                       _
	 *   _ _ ___ ___ ___ ___ _ _ ___ ___ ___|_|___ ___ ___ ___
	 *  | | |_ -| -_|  _| -_|_'_| . | -_|  _| | -_|   |  _| -_|
	 *  |___|___|___|_| |___|_,_|  _|___|_| |_|___|_|_|___|___|
	 *                          |_|
	 * returns all user experience points by year
	 */
	private function userexperience(){
		// add export button
		if(PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
				]
			]
		];
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', array_column($users, 'id'))
			]
		]);
		$trainings = $trainings ? array_values($trainings) : [];
		foreach ($users as $user){ // ordered by name
			if ($user['id'] < 2) continue;
			$user_id = $user['id'];
			if ($usertrainings = array_filter($trainings, function ($row) use($user_id){
				return $row['user_id'] === $user_id;
			})){
				$years = [];
				foreach ($usertrainings as $row){
					$year = substr($row['date'], 0, 4);
					if ($row['experience_points']){
						if (!array_key_exists($year, $years)) $years[$year] = ['xp' => 0, 'paths' => []];
						$years[$year]['xp'] += $row['experience_points'];
						if ($row['file_path']) $years[$year]['paths'][$row['name'] . ' ' . $row['date']] = ['href' => $row['file_path']];
					}
				}
				if ($years){
					$usercontent = [[
						'type' => 'textsection',
						'attributes' => [
							'name' => $user['name']
						],
					]];
					foreach($years as $year => $summary){
						$usercontent[] = [
							'type' => 'links',
							'description' => LANG::GET('audit.experience_points', [':number' => $summary['xp'], ':year' => $year]),
							'content' => $summary['paths']
						];
					}
					if ($usercontent) $content = [
						...$content,
						$usercontent
					];
				}
			}
		}
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for users experience points
	 * processes the result of $this->userexperience() and translates the body object into more simple strings
	 */
	private function exportuserexperience(){
		$summary = [
			'filename' => preg_replace('/' . INI['forbidden']['names'][0] . '/', '', LANG::GET('audit.checks_type.userexperience') . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('audit.checks_type.userexperience'),
			'date' => $this->_currentdate->format('y-m-d H:i')
		];

		$experience = $this->userexperience();

		for($i = 1; $i < count($experience); $i++){
			foreach($experience[$i] as $item){
				if ($item['type'] === 'textsection') {
					$previous = $item['attributes']['name'];
				}
				if ($item['type'] === 'links') {
					$summary['content'][$previous] = $item['description'];
					$summary['content'][$previous] .= "\n" . implode("\n", array_keys($item['content']));
				}
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
			'render' => $body,
		]);
	}

	/**
	 *                       _   _ _ _
	 *   _ _ ___ ___ ___ ___| |_|_| | |___
	 *  | | |_ -| -_|  _|_ -| '_| | | |_ -|
	 *  |___|___|___|_| |___|_,_|_|_|_|___|
	 *
	 * returns all users with their skills and trainings
	 */
	private function userskills(){
		// add export button
		if(PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
				]
			]
		];
		$unfulfilledskills = [];
		foreach (LANGUAGEFILE['skills'] as $duty => $skills){
			if ($duty === 'LEVEL') continue;
			foreach ($skills as $skill => $skilldescription){
				if ($skill === '_DESCRIPTION') continue;
				$unfulfilledskills[] = LANG::GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription;
			}
		}
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', array_column($users, 'id'))
			]
		]);
		$trainings = $trainings ? array_values($trainings) : [];
		$today = new DateTime('now', new DateTimeZone(INI['application']['timezone']));
		foreach ($users as $user){
			if ($user['id'] < 2) continue;
			$user['skills'] = explode(',', $user['skills'] ?  : '');
			$skillmatrix = '';
			foreach (LANGUAGEFILE['skills'] as $duty => $skills){
				if ($duty === 'LEVEL') continue;
				foreach ($skills as $skill => $skilldescription){
					if ($skill === '_DESCRIPTION') continue;
					foreach(LANGUAGEFILE['skills']['LEVEL'] as $level => $leveldescription){
						if (in_array($duty . '.' . $skill . '.' . $level, $user['skills'])){
							$skillmatrix .=  LANG::GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription . ': ' . $leveldescription . " \n";
							unset($unfulfilledskills[array_search(LANG::GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription, $unfulfilledskills)]);
						}
					}
				}
			}
			$content[] = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => $user['name']
					],
					'content' => $skillmatrix
				]
			];
			$user_id = $user['id'];
			if ($usertrainings = array_filter($trainings, function ($row) use($user_id){
				return $row['user_id'] === $user_id;
			})){
				foreach ($usertrainings as $row){
					$attributes = ['name' => LANG::GET('user.edit_display_training') . ' ' . $row['name'] . ' ' . $row['date']];
					if ($row['expires']){
						$expire = new DateTime($row['expires'], new DateTimeZone(INI['application']['timezone']));
						if ($expire < $today) $attributes = ['class' => 'red'];
						else {
							$expire->modify('-' . INI['lifespan']['training_renewal'] . ' days');
							if ($expire < $today) $attributes = ['class' => 'orange'];
						}
					}
					$content[count($content) - 1][] = [
						'type' => 'textsection',
						'content' => LANG::GET('user.edit_add_training_expires') . ' ' . $row['expires'],
						'attributes' => $attributes
					];
					if ($row['file_path']) $content[count($content) - 1][] = [
						'type' => 'links',
						'content' => [
							$row['file_path'] => ['href' => $row['file_path']]
						]
					];
				}	
			}
		}
		if ($unfulfilledskills){
			$content = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => LANG::GET('audit.userskills_warning_description')
					],
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
					'type' => 'textsection',
					'content' => $users ? implode(', ', $users) : LANG::GET('audit.skillfulfilment_warning'),
					'attributes' => $users ? ['name' => LANG::GET('skills.' . $skill[0] . '._DESCRIPTION') . ' ' . LANG::GET('skills.' . $skill[0] . '.' . $skill[1])] : ['class' => 'red', 'name' => LANG::GET('skills.' . $skill[0] . '._DESCRIPTION') . ' ' . LANG::GET('skills.' . $skill[0] . '.' . $skill[1])]
				]
			];
		}
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for user skills and trainings
	 * processes the result of $this->userskills() and translates the body object into more simple strings
	 */
	private function exportuserskills(){
		$summary = [
			'filename' => preg_replace('/' . INI['forbidden']['names'][0] . '/', '', LANG::GET('audit.checks_type.userskills') . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('audit.checks_type.userskills'),
			'date' => $this->_currentdate->format('y-m-d H:i')
		];

		$skills = $this->userskills();

		for($i = 1; $i < count($skills); $i++){
			foreach($skills[$i] as $item){
				if ($item['type'] === 'textsection') {
					$summary['content'][$item['attributes']['name']] = $item['content'];
					$previous = $item['description'];
				}
				if ($item['type'] === 'links') $summary['content'][$previous] .= "\n" . implode("\n", array_keys($item['content']));
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
			'render' => $body,
		]);
	}

	/**
	 *                 _
	 *   _ _ ___ ___ _| |___ ___ ___
	 *  | | | -_|   | . | . |  _|_ -|
	 *   \_/|___|_|_|___|___|_| |___|
	 *
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
		if(PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => LANG::GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
				]
			]
		];
		foreach($vendors as $vendor){
			$info = '';
			if ($vendor['active']) {
				if ($vendor['info']) {
					$vendor['info'] = json_decode($vendor['info'], true) ? : [];
					$vendor['info'] = array_filter($vendor['info'], function($value){return $value;});
					$info .= implode(" \n", array_map(Fn($key, $value) => $value ? LANG::GET($vendor_info[$key]) . ': ' . $value : false, array_keys($vendor['info']), $vendor['info'])) . "\n";
				}
				$pricelist = json_decode($vendor['pricelist'], true);
				if ($pricelist['validity']) $info .= LANG::GET('consumables.edit_vendor_pricelist_validity') . ' ' . $pricelist['validity'] . "\n";
				if (($samplecheck = array_search($vendor['id'], array_column($lastchecks, 'vendor_id'))) !== false) $info .= LANG::GET('audit.checks_type.mdrsamplecheck') . ' ' . $lastchecks[$samplecheck]['checked'] . "\n";
				$certificate = json_decode($vendor['certificate'], true);
				if ($certificate['validity']) $info .= LANG::GET('consumables.edit_vendor_certificate_validity') . ' ' . $certificate['validity'] . "\n";
				$vendorlist[] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $vendor['name']
					],
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
	private function exportvendors(){
		$summary = [
			'filename' => preg_replace('/' . INI['forbidden']['names'][0] . '/', '', LANG::GET('audit.checks_type.vendors') . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('audit.checks_type.vendors'),
			'date' => $this->_currentdate->format('y-m-d H:i')
		];

		$vendors = $this->vendors();
		$previous = ''; // given there's a text followed by links
		foreach($vendors[1] as $item){
			if ($item['type'] === 'textsection') {
				$summary['content'][$item['attributes']['name']] = $item['content'];
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
			'render' => $body,
		]);
	}
}
?>
<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
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
require_once("../libraries/xlsxwriter.class.php");

class AUDIT extends API {
	/**
	 * in case you want to create a code from any values in any form add 
	 * 'data-usecase' => 'tool_create_code'
	 * to inputs attributes (hidden if applicable)
	 */

	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedType = null;
	private $_requestedDate = null;
	private $_requestedID = null;
	private $_requestedOption = null;
	private $_requestedTime = null;

	/**
	 * init parent class and set private requests
	 */
	public function __construct(){
		parent::__construct();
		if (!PERMISSION::permissionFor('audits')) $this->response([], 401);

		$this->_requestedType = isset(REQUEST[2]) ? REQUEST[2] : null;
		$this->_requestedDate = $this->_requestedID = $this->_requestedOption = isset(REQUEST[3]) ? REQUEST[3] : null;
		$this->_requestedTime = isset(REQUEST[4]) ? REQUEST[4] : null;
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
			case 'POST':
			case 'PUT':
			case 'GET':
				$result['render'] = ['content' => []];
				$selecttypes = [];
				
				// checks
				$types = SQLQUERY::EXECUTE($this->_pdo, 'checks_get_types');
				foreach($types as $type){
					$selecttypes[$this->_lang->GET('audit.checks_type.' . $type['type'])] = ['value' => $type['type']];
					if ($this->_requestedType === $type['type']) $selecttypes[$this->_lang->GET('audit.checks_type.' . $type['type'])]['selected'] = true;
				}
				foreach([
					'incorporation', // incorporated products
					'documents', // documents and components
					'userskills', // user skills and certificates
					'skillfulfilment', // skill fulfilment
					'userexperience', // experience points per user and year
					'vendors', // vendor list
					'orderstatistics', // order statistics
					'complaints', // complaints within records
					'regulatory', // regulatory issues
					'risks', // risks
					'trainingevaluation', // training evaluation
					] as $category){
						$selecttypes[$this->_lang->GET('audit.checks_type.' . $category)] = ['value' => $category];
						if ($this->_requestedType === $category) $selecttypes[$this->_lang->GET('audit.checks_type.' . $category)]['selected'] = true;
				}
				ksort($selecttypes);
				$result['render']['content'][] = [
					[
						'type' => 'select',
						'content' => $selecttypes,
						'attributes' => [
							'name' => $this->_lang->GET('audit.checks_select_type'),
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
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');
		$entries = $content = [];
		foreach ($data as $row){
			if ($row['record_type'] === 'complaint'){
				$year = substr($row['last_touch'], 0, 4);
				if (!isset($entries[$year])) $entries[$year] = [];
				$entries[$year][$row['identifier']] = ['closed' => json_decode($row['closed'] ? : '', true), 'units' => $row['units']];
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
				'content' => $this->_lang->GET('audit.complaints_summary', [':number' => count($cases), ':closed' => count(array_filter($cases, Fn($c) => PERMISSION::fullyapproved('complaintclosing', $c['closed'])))])
			];
			foreach ($cases as $identifier => $property){
				$units = implode(', ', array_map(Fn($u)=> $this->_lang->_USER['units'][$u], explode(',', $property['units'])));
				$linkdescription = $this->_lang->GET('audit.complaints_case_description', [':identifier' => $identifier, ':units' => $units]);
				if (PERMISSION::fullyapproved('complaintclosing', $property['closed'])) {
					$linkdescription .= $this->_lang->GET('audit.complaints_closed');
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
			'documents',
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
			'filename' => preg_replace('/' . CONFIG['forbidden']['names'][0] . '/', '', $this->_lang->GET('audit.checks_type.' . $this->_requestedType) . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.' . $this->_requestedType),
			'date' => $this->_currentdate->format('y-m-d H:i')
		];
		// stringify check records
		foreach($checks as $row){
			$summary['content'][$this->_lang->GET('audit.check_description', [
				':check' => $this->_lang->GET('audit.checks_type.' . $this->_requestedType),
				':date' => $row['date'],
				':author' => $row['author']
			])] = $row['content'];
		}
		$downloadfiles = [];
		$downloadfiles[$this->_lang->GET('menu.records.record_summary')] = [
			'href' => PDF::auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]
		);
		$this->response([
			'render' => $body,
		]);
	}

	/**
	 *     _                           _       
	 *   _| |___ ___ _ _ _____ ___ ___| |_ ___ 
	 *  | . | . |  _| | |     | -_|   |  _|_ -|
	 *  |___|___|___|___|_|_|_|___|_|_|_| |___|
	 *  
	 * returns all current approved documents with their respective components and approvement notes in alphabetical order
	 * also documents bundles and available external documents
	 */
	private function documents(){
		$content = [];

		$this->_requestedDate = $this->_requestedDate ? : $this->_currentdate->format('Y-m-d');
		$this->_requestedTime = $this->_requestedTime ? : $this->_currentdate->format('H:i:59');

		function latestApprovedComponent($components, $requestedTimestamp, $name = ''){
			if (!$name) return false;
			// get latest approved by name
			$named_components = array_filter($components, Fn($component) => $component['name'] === $name);
			foreach ($named_components as $component){
				if (PERMISSION::fullyapproved('documentapproval', $component['approval']) && 
					$component['date'] <= $requestedTimestamp) {
						$component['hidden'] = json_decode($component['hidden'] ? : '', true); 
						if (!$component['hidden'] || $component['hidden']['date'] > $requestedTimestamp) return $component;
						else return false;
					}
			}
			return false;
		}

		// get all current approved document older than given timestamp
		$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$hidden = $currentdocuments = [];
		foreach($documents as $document){
			if (!PERMISSION::fullyapproved('documentapproval', $document['approval']) || $document['date'] >= $this->_requestedDate . ' ' . $this->_requestedTime) continue;
			if ($document['hidden']) {
				$document['hidden'] = json_decode($document['hidden'], true);
				if ($document['hidden']['date'] <= $this->_requestedDate . ' ' . $this->_requestedTime)
					$hidden[] = $document['name']; // since ordered by recent, older items will be skipped
			}
			if (!in_array($document['name'], array_column($currentdocuments, 'name')) && !in_array($document['name'], $hidden)) $currentdocuments[] = $document;
		}
	
		// get all components
		$components = SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist');

		// get all current bundles
		$bundles = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist');
		$hidden = $currentbundles = [];
		foreach($bundles as &$bundle){
			if ($bundle['hidden']) $hidden[] = $bundle['name']; // since ordered by recent, older items will be skipped
			if (!in_array($bundle['name'], array_column($currentbundles, 'name')) && !in_array($bundle['name'], $hidden)) $currentbundles[] = $bundle;
		}

		$documentscontent = [
			[
				'type' => 'date',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents_date'),
					'value' => $this->_requestedDate,
					'id' => '_documents_date'
				]
			],
			[
				'type' => 'time',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents_time'),
					'value' => $this->_requestedTime,
					'id' => '_documents_time' 
				]
			],
			[
				'type' => 'button',
				'attributes' => [
					'data-type' => 'generateupdate',
					'value' => $this->_lang->GET('audit.documents_update_button'),
					'onpointerup' => "api.audit('get', 'checks', 'documents', document.getElementById('_documents_date').value, document.getElementById('_documents_time').value)"
				]
			],
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents_in_use_documents')
				],
				'content' => $this->_lang->GET('audit.documents_export_timestamp', [':timestamp' => $this->_requestedDate . ' ' . $this->_requestedTime])
			]
		];

		// iterate over documents an their respective components
		foreach($currentdocuments as $document){
			$entry = '';
			// display document approval
			foreach(json_decode($document['approval'], true) as $position => $data){
				$entry .= $this->_lang->GET('audit.documents_in_use_approved', [
					':permission' => $this->_lang->GET('permissions.' . $position),
					':name' => $data['name'],
					':date' => $data['date'],
				]) . "\n";
			}
			// display component approval
			$has_components = false;
			foreach(explode(',', $document['content'] ? : '') as $used_component_name){
				if ($cmpnnt = latestApprovedComponent($components, $this->_requestedDate . ' ' . $this->_requestedTime, $used_component_name)){
					$has_components = true;
					$cmpnnt['approval'] = json_decode($cmpnnt['approval'], true);
					$entry .= " \n" . $cmpnnt['name'] . ' ' . $this->_lang->GET('assemble.compose.component.component_author', [':author' => $cmpnnt['author'], ':date' => $cmpnnt['date']]) . "\n";
					foreach($cmpnnt['approval'] as $position => $data){
						$entry .= $this->_lang->GET('audit.documents_in_use_approved', [
							':permission' => $this->_lang->GET('permissions.' . $position),
							':name' => $data['name'],
							':date' => $data['date'],
						]) . "\n";
					}
				}
			}
			foreach(explode(',', $document['regulatory_context'] ? : '') as $context){
				if (isset($this->_lang->_USER['regulatory'][$context])) $entry .= "\n" . $this->_lang->_USER['regulatory'][$context];
			}

			$documentscontent[] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => $document['name'] . ' ' . $this->_lang->GET('assemble.compose.component.component_author', [':author' => $document['author'], ':date' => $document['date']])
				],
				'content' => $entry
			];
			if (!$has_components) {
				$documentscontent[count($documentscontent) - 1]['attributes']['class'] = 'orange';
				$documentscontent[count($documentscontent) - 1]['content'] .="\n \n" . $this->_lang->GET('assemble.render.error_no_approved_components', [':permission' => implode(', ', array_map(fn($v)=>$this->_lang->_USER['permissions'][$v], PERMISSION::permissionFor('documentcomposer', true)))]);
			}
			$documentscontent[] = [
				'type' => 'button',
				'attributes' => [
					'type' => 'button',
					'data-type' => 'download',
					'value' => $this->_lang->GET('assemble.render.export'),
					'onpointerup' => "new Dialog({type: 'input', header: '". $this->_lang->GET('assemble.render.export') . "', render: JSON.parse('" . json_encode(
						[
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $document['name'] . ' ' . $this->_lang->GET('assemble.compose.component.component_author', [':author' => $document['author'], ':date' => $document['date']])
								],
							],
							[
								'type' => 'hidden',
								'attributes' => [
									'name' => '_maxDocumentTimestamp',
									'value' => $this->_requestedDate . ' ' . $this->_requestedTime
								]
							], [
								'type' => 'hidden',
								'attributes' => [
									'name' => '_document_id',
									'value' => $document['id']
								]
							]
						]
					) . "'), options:{".
					"'" . $this->_lang->GET('general.cancel_button') . "': false,".
					"'" . $this->_lang->GET('general.ok_button')  . "': {value: true, class: 'reducedCTA'},".
					"}}).then(response => {if (response) api.document('post', 'export', null, _client.application.dialogToFormdata(response))})"
				]
			];
		}

		$externalcontent = [
			[
				'type' => 'links',
				'description' => $this->_lang->GET('audit.documents_in_use_external'),
				'content' => ''
			]
		];
		$links = [];
		if ($files = SQLQUERY::EXECUTE($this->_pdo, 'file_external_documents_get_active')) {
			foreach ($files as $file){
				if (preg_match('/^\.\.\//', $file['path'])){
					$file['path'] = substr($file['path'], 1);
				}
				$links[$file['path'] . ' ' . $this->_lang->GET('file.external_file.introduced', [':user' => $file['author'], ':introduced' => $file['activated']])] = ['href'=>$file['path'], 'target' => 'blank'];
			}
			$externalcontent[0]['content'] = $links;
		}

		$bundlescontent = [
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents_in_use_bundles')
				],
				'content' => ''
			]
		];
		foreach($currentbundles as $bundle){
			$documentslist = explode(',', $bundle['content'] ? : '');
			natsort($documentslist);
			$bundlescontent[] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => $bundle['name'] . ' ' . $this->_lang->GET('assemble.compose.component.component_author', [':author' => $bundle['author'], ':date' => $bundle['date']])
				],
				'content' => implode("\n", $documentslist)
			];
		}

		// add export button
		if (PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "', document.getElementById('_documents_date').value, document.getElementById('_documents_time').value)",
					'data-type' => 'download'
				]
			]
		];
		
		$content[] = $documentscontent;
		$content[] = $externalcontent;
		$content[] = $bundlescontent;
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for documents and document bundles
	 * processes the result of $this->documents() and translates the body object into more simple strings
	 */
	private function exportdocuments(){
		$summary = [
			'filename' => preg_replace('/' . CONFIG['forbidden']['names'][0] . '/', '', $this->_lang->GET('audit.checks_type.' . $this->_requestedType) . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.' . $this->_requestedType),
			'date' => $this->_currentdate->format('y-m-d H:i')
		];

		$documents = $this->documents();

		for($i = 1; $i < count($documents); $i++){
			foreach($documents[$i] as $item){
				if (isset($item['content'])){
					if (gettype($item['content']) === 'string' && isset($item['attributes']['name']))
						$summary['content'][$item['attributes']['name']] = $item['content'];
					elseif (gettype($item['content']) === 'array' && isset($item['description']))
						$summary['content'][$item['description']] = implode("\n", array_keys($item['content']));
				}
			}
		}
		$downloadfiles = [];
		$downloadfiles[$this->_lang->GET('menu.records.record_summary')] = [
			'href' => PDF::auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
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
			if (isset($decoded_order_data['ordernumber_label']) && ($tocheck = array_search($decoded_order_data['ordernumber_label'], array_column($unincorporated, 'article_no'))) !== false){
				if (isset($decoded_order_data['vendor_label']) && (isset($unincorporated[$tocheck]) && $unincorporated[$tocheck]['vendor_name'] === $decoded_order_data['vendor_label'])){
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
					'name' => $this->_lang->GET('audit.incorporation_warning_description')
				],
				'content' => $this->_lang->GET('audit.incorporation_warning_content', [':amount' => count($orderedunincorporated)])
			]
		];
		// add export button
		if (PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.record_export'),
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
				if (isset($product['incorporated'][$permission])) $incorporationInfo .= " \n" . $this->_lang->_USER['permissions'][$permission] . ' ' . $product['incorporated'][$permission]['name'] . ' ' . $product['incorporated'][$permission]['date'];
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
	 * creates and returns a download link to the export file incorporations
	 * processes the result of $this->incorporation() and translates the body object into more simple strings
	 */
	private function exportincorporation(){
		$summary = [
			'filename' => preg_replace('/' . CONFIG['forbidden']['names'][0] . '/', '', $this->_lang->GET('audit.checks_type.' . $this->_requestedType) . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.' . $this->_requestedType),
			'date' => $this->_currentdate->format('y-m-d H:i')
		];

		$documents = $this->incorporation();

		for($i = 1; $i<count($documents); $i++){
			foreach($documents[$i] as $item){
				if ($item['type'] === 'textsection' && isset($item['attributes']['name'])) $summary['content'][$item['attributes']['name']] = $item['content'];	
			}
		}
		$downloadfiles = [];
		$downloadfiles[$this->_lang->GET('menu.records.record_summary')] = [
			'href' => PDF::auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
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
					'name' => $this->_lang->GET('audit.mdrsamplecheck_warning_description')
				],
				'content' => $this->_lang->GET('audit.mdrsamplecheck_warning_content', [':vendors' => implode(', ', $unchecked)])
			]
		];
		// add export button
		if (PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.record_export'),
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
					'name' => $this->_lang->GET('audit.check_description', [
						':check' => $this->_lang->GET('audit.checks_type.' . $this->_requestedType),
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
					'value' => $this->_lang->GET('audit.sample_check_revoke'),
					'onpointerup' => "new Dialog({type:'confirm', header:'" . $this->_lang->GET('order.disapprove') . "', " .
						"options:{'" . $this->_lang->GET('order.disapprove_message_cancel') . "': false, '" . $this->_lang->GET('audit.sample_check_revoke_confirm') . "': {value: true, class: 'reducedCTA'}}}).then(response => {" .
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
					'name' => $this->_lang->GET('audit.orderstatistics_number', [':number' => count($orders)])
				],
				'content' => count($orders) ? $this->_lang->GET('audit.orderstatistics_info') : ''
			]
		];

		if (count($orders) && PERMISSION::permissionFor('auditsoperation')){
			// add export button
			$content[] = [
				[
					'type' => 'button',
					'attributes' => [
						'value' => $this->_lang->GET('audit.record_export_xlsx'),
						'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
					]
				]
			];
			$content[] = [
				[
					'type' => 'deletebutton',
					'attributes' => [
						'value' => $this->_lang->GET('audit.orderstatistics_truncate'),
						'onpointerup' => "new Dialog({type: 'confirm', header: '". $this->_lang->GET('audit.orderstatistics_truncate') ."', options:{".
						"'".$this->_lang->GET('general.cancel_button')."': false,".
						"'".$this->_lang->GET('audit.orderstatistics_truncate_confirm')."': {value: true, class: 'reducedCTA'},".
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
			'vendor_label' => $this->_lang->GET('order.vendor_label'),
			'ordertype' => $this->_lang->GET('order.order_type'),
			'quantity_label' => $this->_lang->GET('order.quantity_label'),
			'unit_label' => $this->_lang->GET('order.unit_label'),
			'ordernumber_label' => $this->_lang->GET('order.ordernumber_label'),
			'productname_label' => $this->_lang->GET('order.productname_label'),
			'additional_info' => $this->_lang->GET('order.additional_info'),
			'ordered' => $this->_lang->GET('order.order.ordered'),
			'partially_received' => $this->_lang->GET('order.order.partially_received'),
			'received' => $this->_lang->GET('order.order.received'),
			'deliverytime' => $this->_lang->GET('audit.order_statistics_delivery_time_column')
		];

		// prepare result as subsets of vendors
		$vendor_orders = [];
		foreach($orders as $order){
			$order['order_data'] = json_decode($order['order_data'], true);
			$deliverytime = '';
			if ($order['received']){
				$datetimezone = new DateTimeZone(CONFIG['application']['timezone']);
				$now = new DateTime('now', $datetimezone);
				$ordered = new DateTime($order['ordered'], $datetimezone);
				$received = new DateTime($order['received'], $datetimezone);
				$deliverytime = intval($ordered->diff($received)->format('%a'));
			}

			if (!isset($order['order_data']['vendor_label'])) $order['order_data']['vendor_label'] = $this->_lang->GET('audit.order_statistics_undefined_vendor');
			if (!isset($vendor_orders[$order['order_data']['vendor_label']])) $vendor_orders[$order['order_data']['vendor_label']] = [];

			$vendor_orders[$order['order_data']['vendor_label']][] = [
				isset($order['order_data']['vendor_label']) ? $order['order_data']['vendor_label'] : '',
				$this->_lang->GET('order.ordertype.' . $order['ordertype']),
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
		$tempFile = UTILITY::directory('tmp') . '/' . preg_replace('/[^\w\d]/', '', $this->_lang->GET('audit.checks_type.orderstatistics') . '_' . $this->_currentdate->format('Y-m-d H:i')) . '.xlsx';
		$writer = new XLSXWriter();
		$writer->setAuthor($_SESSION['user']['name']); 

		foreach($vendor_orders as $vendor => $orders){
			$writer->writeSheetRow($vendor, array_values($columns));
			foreach ($orders as $line)
				$writer->writeSheetRow($vendor, $line, $row_options = array('height' => 30,'wrap_text' => true));
		}

		$writer->writeToFile($tempFile);
		$downloadfiles[$this->_lang->GET('menu.records.record_summary')] = [
			'href' => substr($tempFile, 1),
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' => $this->_lang->GET('record.export_proceed'),
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
				'msg' => $this->_lang->GET('audit.orderstatistics_truncate_success'),
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
	 * returns regulatory items according to language.xx.ini and matches current assigned documents
	 */
	private function regulatory(){
		$content = $issues = [];
		// prepare existing document lists
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$hidden = $regulatory = [];
		foreach($fd as $key => $row) {
			if (!PERMISSION::fullyapproved('documentapproval', $row['approval'])) continue;
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden)) {
				foreach(explode(',', $row['regulatory_context'] ? : '') as $regulatory_context){
					$satisfied = false;
					if (isset($regulatory[$regulatory_context])){
						foreach ($regulatory[$regulatory_context] as $key => $value){
							if (preg_match('/^' . $row['name'] . ' \(/', $key)) $satisfied = true;
						}
					}
					if (!$satisfied) $regulatory[$regulatory_context][$row['name'] . ' (' . $row['date'] . ')'] = ['href' => "javascript:api.record('get', 'document', '" . $row['name'] . "')"];
				}
			}
		}
		// get active external documents
		if ($files = SQLQUERY::EXECUTE($this->_pdo, 'file_external_documents_get_active')) {
			foreach ($files as $file){
				foreach(explode(',', $file['regulatory_context']) as $context){
					if (preg_match('/^\.\.\//', $file['path'])){
						$file['path'] = substr($file['path'], 1);
					}
					$regulatory[$context][$file['path'] . ' (' . $file['activated'] . ')'] = ['href' => substr($file['path'], 1)];
				}
			}
		}

		// add export button
		if(PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
				]
			]
		];
		foreach($this->_lang->_USER['regulatory'] as $key => $issue){
			if (isset($regulatory[$key])) $issues[] = [
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
				'content' => $this->_lang->GET('audit.regulatory_warning_content')
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
			'filename' => preg_replace('/' . CONFIG['forbidden']['names'][0] . '/', '', $this->_lang->GET('audit.checks_type.regulatory') . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.regulatory'),
			'date' => $this->_currentdate->format('y-m-d H:i')
		];

		$issues = $this->regulatory();
		foreach($issues[1] as $item){
			$summary['content'][$item['description']] = $item['content'];	
		}

		$downloadfiles = [];
		$downloadfiles[$this->_lang->GET('menu.records.record_summary')] = [
			'href' => PDF::auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
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
					'value' => $this->_lang->GET('audit.record_export'),
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
					" \n" . $this->_lang->GET('risk.cause') . ': ' . $risk['cause']
				],
				'content' => $this->_lang->GET('risk.effect') . ': ' . $risk['effect'] .
				" \n" . $this->_lang->GET('risk.probability') . ': ' . (isset($this->_lang->_USER['risk']['probabilities'][$risk['probability']-1]) ? $this->_lang->_USER['risk']['probabilities'][$risk['probability'] - 1] : $this->_lang->_USER['risk']['probabilities'][count($this->_lang->_USER['risk']['probabilities']) - 1]) .
				" \n" . $this->_lang->GET('risk.damage') . ': ' . (isset($this->_lang->_USER['risk']['damages'][$risk['damage']-1]) ? $this->_lang->_USER['risk']['damages'][$risk['damage'] - 1] : $this->_lang->_USER['risk']['damages'][count($this->_lang->_USER['risk']['damages']) - 1]) .
				" \n" . ($risk['probability'] * $risk['damage'] > CONFIG['limits']['risk_acceptance_level'] ? $this->_lang->GET('risk.acceptance_level_above') : $this->_lang->GET('risk.acceptance_level_below')) .
				" \n" . $this->_lang->GET('risk.measure') . ': ' . $risk['measure'] .
				" \n" . $this->_lang->GET('risk.measure_probability') . ': ' . (isset($this->_lang->_USER['risk']['probabilities'][$risk['measure_probability']-1]) ? $this->_lang->_USER['risk']['probabilities'][$risk['measure_probability'] - 1] : $this->_lang->_USER['risk']['probabilities'][count($this->_lang->_USER['risk']['probabilities']) - 1]) .
				" \n" . $this->_lang->GET('risk.measure_damage') . ': ' . (isset($this->_lang->_USER['risk']['damages'][$risk['measure_damage']-1]) ? $this->_lang->_USER['risk']['damages'][$risk['measure_damage'] - 1] : $this->_lang->_USER['risk']['damages'][count($this->_lang->_USER['risk']['damages']) - 1]) .
				" \n" . ($risk['measure_probability'] * $risk['measure_damage'] > CONFIG['limits']['risk_acceptance_level'] ? $this->_lang->GET('risk.acceptance_level_above') : $this->_lang->GET('risk.acceptance_level_below')) .
				" \n" . $this->_lang->GET('risk.risk_benefit') . ': ' . $risk['risk_benefit'] .
				" \n" . $this->_lang->GET('risk.measure_remainder') . ': ' . $risk['measure_remainder'] .
				(isset($last_edit['user']) ? " \n" . $this->_lang->GET('risk.last_edit', [':user' => $last_edit['user'], ':date' => $last_edit['date']]): '')
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
			'filename' => preg_replace('/' . CONFIG['forbidden']['names'][0] . '/', '', $this->_lang->GET('audit.checks_type.risks') . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.risks'),
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
		$downloadfiles[$this->_lang->GET('menu.records.record_summary')] = [
			'href' => PDF::auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}
	
	/**
	 *   _           _     _                     _         _   _         
	 *  | |_ ___ ___|_|___|_|___ ___ ___ _ _ ___| |_ _ ___| |_|_|___ ___ 
	 *  |  _|  _| .'| |   | |   | . | -_| | | .'| | | | .'|  _| | . |   |
	 *  |_| |_| |__,|_|_|_|_|_|_|_  |___|\_/|__,|_|___|__,|_| |_|___|_|_|
	 *                          |___|   
	 */
	private function trainingevaluation(){
		if ($_SERVER['REQUEST_METHOD']==='PUT' && PERMISSION::permissionFor('trainingevaluation')){
			$user = null;
			$training = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get', [
				'values' => [
					':id' => $this->_requestedID
				]
			]);
			$training = $training ? $training[0] : [];
			if ($training) $user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
				'replacements' => [
					':id' => intval($training['user_id']),
					':name' => ''
				]
			]);
			$user = $user ? $user[0] : null;

			if ($training && $user &&
				(PERMISSION::permissionFor('trainingevaluation') &&
				(array_intersect(array_filter(PERMISSION::permissionFor('trainingevaluation', true), fn($permission) => $permission === 'supervisor'), $_SESSION['user']['permissions']) ||
				(array_intersect(['supervisor'], $_SESSION['user']['permissions']) && array_intersect(explode(',', $user['units']), $_SESSION['user']['units']))))
			) {
				foreach($this->_payload as $key => &$value){
					if (gettype($value) === 'array') $value = trim(implode(' ', $value));
					/////////////////////////////////////////
					// BEHOLD! unsetting value==on relies on a prepared formdata/_payload having a dataset containing all selected checkboxes
					////////////////////////////////////////
					if (!$value || $value == 'on') unset($this->_payload->$key);
				}
				if ((array) $this->_payload)
					SQLQUERY::EXECUTE($this->_pdo, 'user_training_put', [
						'values' => [
							':id' => $this->_requestedID,
							':evaluation' => json_encode([
								'user' => $_SESSION['user']['name'],
								'date' => $this->_currentdate->format('Y-m-d H:i'),
								'content' => (array) $this->_payload
							])
						]
					]);
			}
		}

		$content = [];
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', array_column($users, 'id'))
			]
		]);
		$trainings = $trainings ? array_values($trainings) : [];

		$options = [
			$this->_lang->GET('audit.userskills_training_evaluation_pending') => ['onchange' => "api.audit('get', 'checks', 'trainingevaluation')"],
			$this->_lang->GET('audit.userskills_training_evaluation_closed') => ['onchange' => "api.audit('get', 'checks', 'trainingevaluation', 'closed')"],
			$this->_lang->GET('audit.userskills_training_evaluation_all') => ['onchange' => "api.audit('get', 'checks', 'trainingevaluation', 'all')"],
		];
		if (!$this->_requestedOption) $options[$this->_lang->GET('audit.userskills_training_evaluation_pending')]['checked'] = true;
		if ($this->_requestedOption === 'closed') $options[$this->_lang->GET('audit.userskills_training_evaluation_closed')]['checked'] = true;
		if ($this->_requestedOption === 'all') $options[$this->_lang->GET('audit.userskills_training_evaluation_all')]['checked'] = true;
		$content[] = [
			'type' => 'radio',
			'attributes' => [
				'name' => $this->_lang->GET('audit.userskills_training_evaluation_display')
			],
			'content' => $options
		];
		require_once('_shared.php');
		$sharedfunction = new SHARED($this->_pdo);
		$evaluationdocument = $sharedfunction->recentdocument('document_document_get_by_context', [
			'values' => [
				':context' => 'training_evaluation_document'
			]])['content'];

		foreach ($users as $user){
			if (
				$user['id'] < 2 ||
				!(PERMISSION::permissionFor('trainingevaluation') &&
				(array_intersect(array_filter(PERMISSION::permissionFor('trainingevaluation', true), fn($permission) => $permission === 'supervisor'), $_SESSION['user']['permissions']) ||
				(array_intersect(['supervisor'], $_SESSION['user']['permissions']) && array_intersect(explode(',', $user['units']), $_SESSION['user']['units']))))
			) continue;

			$content[] = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => $user['name']
					]
				]
			];
			$user_id = $user['id'];
			if ($usertrainings = array_filter($trainings, function ($row) use($user_id){
				return $row['user_id'] === $user_id;
			})){
				foreach ($usertrainings as $row){
					if (!$this->_requestedOption && $row['evaluation']) continue;
					if ($this->_requestedOption === 'closed' && !$row['evaluation']) continue;

					$row['evaluation'] = json_decode($row['evaluation'] ? : '', true);
					$attributes = ['name' => $this->_lang->GET('user.display_training') . ' ' . $row['name'] . ' ' . $row['date']];
					if ($row['expires']){
						$expire = new DateTime($row['expires'], new DateTimeZone(CONFIG['application']['timezone']));
						if ($expire < $this->_currentdate) $attributes['class'] = 'red';
						else {
							$expire->modify('-' . CONFIG['lifespan']['training_renewal'] . ' days');
							if ($expire < $this->_currentdate) $attributes['class'] = 'orange';
						}
					}

					$evaluation = $row['evaluation'] ? "\n\n" . $this->_lang->GET('audit.userskills_training_evaluation', [
						':user' => $row['evaluation']['user'],
						':date' => $row['evaluation']['date'],
						':evaluation' => implode(" \n", array_map(fn($key, $value) => $key . ': ' . $value, array_keys($row['evaluation']['content']), $row['evaluation']['content']))
					]) : '';

					$content[count($content) - 1][] = [
						'type' => 'textsection',
						'content' => $this->_lang->GET('user.add_training_expires') . ' ' . $row['expires'] . $evaluation,
						'attributes' => $attributes
					];
					if ($row['file_path']) $content[count($content) - 1][] = [
						'type' => 'links',
						'content' => [
							$row['file_path'] => ['href' => $row['file_path']]
						]
					];

					$content[count($content) - 1][] = [
						'type' => 'button',
						'attributes' => [
							'type' => 'button',
							'value' => $this->_lang->GET('audit.checks_type.trainingevaluation'),
							'onpointerup' => "new Dialog({type: 'input', header: '" . $this->_lang->GET('audit.checks_type.trainingevaluation') . " " .$row['name']. " " .$user['name'] . "', render: JSON.parse('" . json_encode(
								$sharedfunction->populatedocument($evaluationdocument, $row['evaluation'] ? $row['evaluation']['content'] : [])
							) . "'), options:{".
							"'" . $this->_lang->GET('general.cancel_button') . "': false,".
							"'" . $this->_lang->GET('general.ok_button')  . "': {value: true, class: 'reducedCTA'},".
							"}}).then(response => {if (response) api.audit('put', 'checks', 'trainingevaluation', '" . $row['id'] . "', _client.application.dialogToFormdata())})"
						]
					];
				}
			}
		}
		foreach($content as $index => $set){
			if (count($set) < 2) unset($content[$index]);
		}

		return $content;
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
					'value' => $this->_lang->GET('audit.record_export'),
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
						if (!isset($years[$year])) $years[$year] = ['xp' => 0, 'paths' => []];
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
							'description' => $this->_lang->GET('audit.experience_points', [':number' => $summary['xp'], ':year' => $year]),
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
			'filename' => preg_replace('/' . CONFIG['forbidden']['names'][0] . '/', '', $this->_lang->GET('audit.checks_type.userexperience') . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.userexperience'),
			'date' => $this->_currentdate->format('y-m-d H:i')
		];

		$experience = $this->userexperience();

		for($i = 1; $i < count($experience); $i++){
			foreach($experience[$i] as $item){
				if ($item['type'] === 'textsection') {
					$previous = $item['attributes']['name'];
				}
				if ($item['type'] === 'links') {
					$summary['content'][$previous] = $item['attributes']['name'];
					$summary['content'][$previous] .= "\n" . implode("\n", array_keys($item['content']));
				}
			}
		}
		$downloadfiles = [];
		$downloadfiles[$this->_lang->GET('menu.records.record_summary')] = [
			'href' => PDF::auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
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
		if ($_SERVER['REQUEST_METHOD']==='POST' && PERMISSION::permissionFor('users')){
			$training = $users = $requested = [];

			if ($name = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('audit.userskills_bulk_user'))) if ($name !== '...' ) $requested[] = $name;
			for ($i = 1; $i < count((array) $this->_payload); $i++){
				if ($name = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('audit.userskills_bulk_user') . '(' . $i . ')')) if ($name !== '...' ) $requested[] = $name;
			}
			$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
				'replacements' => [
					':id' => 0,
					':name' => implode(',', $requested)
				]
			]);
			if ($users && $training[':name'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training'))){

				$date = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
				$training[':date'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_date')) ? : $date->format('Y-m-d');
				$training[':expires'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_expires')) ? : '2079-06-06';
				$training[':experience_points'] = 0;
				$training[':file_path'] = '';
				$training[':evaluation'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_evaluation')) ? json_encode([
					'user' => $_SESSION['user']['name'],
					'date' => $this->_currentdate->format('Y-m-d H:i'),
					'content' => [$this->_lang->PROPERTY('user.add_training_evaluation', [], true) => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.add_training_evaluation'))]
				]): null;

				foreach ($users as $user){
					$training[':user_id'] = $user['id'];
					SQLQUERY::EXECUTE($this->_pdo, 'user_training_post', [
						'values' => $training
					]);
				}
			}
		}
		$content = [];
		// add export button
		if(PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.record_export'),
					'onpointerup' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
				]
			]
		];
		$unfulfilledskills = [];
		$bulkselection = ['...' => ['value' => '']];
		foreach ($this->_lang->_USER['skills'] as $duty => $skills){
			if ($duty === '_LEVEL') continue;
			foreach ($skills as $skill => $skilldescription){
				if ($skill === '_DESCRIPTION') continue;
				$unfulfilledskills[] = $this->_lang->GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription;
			}
		}
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', array_column($users, 'id'))
			]
		]);
		$trainings = $trainings ? array_values($trainings) : [];
		foreach ($users as $user){
			if ($user['id'] < 2) continue;

			$bulkselection[$user['name']] = [];

			$user['skills'] = explode(',', $user['skills'] ?  : '');
			$skillmatrix = '';
			foreach ($this->_lang->_USER['skills'] as $duty => $skills){
				if ($duty === '_LEVEL') continue;
				foreach ($skills as $skill => $skilldescription){
					if ($skill === '_DESCRIPTION') continue;
					foreach($this->_lang->_USER['skills']['_LEVEL'] as $level => $leveldescription){
						if (in_array($duty . '.' . $skill . '.' . $level, $user['skills'])){
							$skillmatrix .=  $this->_lang->GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription . ': ' . $leveldescription . " \n";
							unset($unfulfilledskills[array_search($this->_lang->GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription, $unfulfilledskills)]);
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
					$attributes = ['name' => $this->_lang->GET('user.display_training') . ' ' . $row['name'] . ' ' . $row['date']];
					if ($row['expires']){
						$expire = new DateTime($row['expires'], new DateTimeZone(CONFIG['application']['timezone']));
						if ($expire < $this->_currentdate) $attributes['class'] = 'red';
						else {
							$expire->modify('-' . CONFIG['lifespan']['training_renewal'] . ' days');
							if ($expire < $this->_currentdate) $attributes['class'] = 'orange';
						}
					}
					if ($row['evaluation']){
						$row['evaluation'] = json_decode($row['evaluation'], true);
						$evaluation = $this->_lang->GET('audit.userskills_training_evaluation', [
							':user' => $row['evaluation']['user'],
							':date' => $row['evaluation']['date'],
							':evaluation' => implode(" \n", array_map(fn($key, $value) => $key . ': ' . $value, array_keys($row['evaluation']['content']), $row['evaluation']['content']))
						]);
					} else $evaluation = $this->_lang->GET('audit.userskills_training_evaluation_pending');

					$content[count($content) - 1][] = [
						'type' => 'textsection',
						'content' => $this->_lang->GET('user.add_training_expires') . ' ' . $row['expires'] . " \n" . $evaluation,
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
		// also see user.php
		$skillmatrix = [
			[
				[
					'type' => 'text',
					'attributes' => [
						'name' => $this->_lang->GET('user.add_training')
					],
				], [
					'type' => 'date',
					'attributes' => [
						'name' => $this->_lang->GET('user.add_training_date')
					],
				], [
					'type' => 'date',
					'attributes' => [
						'name' => $this->_lang->GET('user.add_training_expires')
					],
				], [
					'type' => 'select',
					'attributes' => [
						'multiple' => true,
						'name' => $this->_lang->GET('audit.userskills_bulk_user'),
						'id' => '_bulkskillupdate'
					],
					'content' => $bulkselection
				], [
					'type' => 'checkbox',
					'attributes' => [
						'name' => $this->_lang->GET("user.add_training_evaluation")
					],
					'content' => [
						$this->_lang->GET('user.add_training_evaluation_unreasonable') => []
					]
				]
			]
		];

		array_splice($content, 1, 0,  [[
			[
				'type' => 'button',
				'attributes' => [
					'type' => 'button',
					'value' => $this->_lang->GET('audit.userskills_bulk_training'),
					'onpointerup' => "new Dialog({type: 'input', header: '" . $this->_lang->GET('audit.userskills_bulk_training') . "', render: JSON.parse('" . json_encode(
						$skillmatrix
					) . "'), options:{".
					"'" . $this->_lang->GET('general.cancel_button') . "': false,".
					"'" . $this->_lang->GET('general.ok_button')  . "': {value: true, class: 'reducedCTA'},".
					"}}).then(response => {if (response) api.audit('post', 'checks', 'userskills', _client.application.dialogToFormdata())})"
				]
			]
		]]);

		if ($unfulfilledskills){
			$content = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('audit.userskills_warning_description')
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
		foreach ($this->_lang->_USER['skills'] as $duty => $skills){
			if ($duty === '_LEVEL') continue;
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
					'content' => $users ? implode(', ', $users) : $this->_lang->GET('audit.skillfulfilment_warning'),
					'attributes' => $users ? ['name' => $this->_lang->GET('skills.' . $skill[0] . '._DESCRIPTION') . ' ' . $this->_lang->GET('skills.' . $skill[0] . '.' . $skill[1])] : ['class' => 'red', 'name' => $this->_lang->GET('skills.' . $skill[0] . '._DESCRIPTION') . ' ' . $this->_lang->GET('skills.' . $skill[0] . '.' . $skill[1])]
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
			'filename' => preg_replace('/' . CONFIG['forbidden']['names'][0] . '/', '', $this->_lang->GET('audit.checks_type.userskills') . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.userskills'),
			'date' => $this->_currentdate->format('y-m-d H:i')
		];

		$skills = $this->userskills();

		for($i = 1; $i < count($skills); $i++){
			foreach($skills[$i] as $item){
				if ($item['type'] === 'textsection' && isset($item['attributes']['name'])) {
					$summary['content'][$item['attributes']['name']] = $item['content'];
					$previous = $item['attributes']['name'];
				}
				if ($item['type'] === 'links') $summary['content'][$previous] .= "\n" . implode("\n", array_keys($item['content']));
			}
		}
		$downloadfiles = [];
		$downloadfiles[$this->_lang->GET('menu.records.record_summary')] = [
			'href' => PDF::auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
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
			'infotext' => 'consumables.vendor.info',
			'mail' => 'consumables.vendor.mail',
			'phone' => 'consumables.vendor.phone',
			'address' => 'consumables.vendor.address',
			'sales_representative' => 'consumables.vendor.sales_representative',
			'customer_id' => 'consumables.vendor.customer_id',
		];

		// add export button
		if(PERMISSION::permissionFor('auditsoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.record_export'),
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
					$info .= implode(" \n", array_map(Fn($key, $value) => $value ? $this->_lang->GET($vendor_info[$key]) . ': ' . $value : false, array_keys($vendor['info']), $vendor['info'])) . "\n";
				}
				$pricelist = json_decode($vendor['pricelist'], true);
				if ($pricelist['validity']) $info .= $this->_lang->GET('consumables.vendor.pricelist_validity') . ' ' . $pricelist['validity'] . "\n";
				if (($samplecheck = array_search($vendor['id'], array_column($lastchecks, 'vendor_id'))) !== false) $info .= $this->_lang->GET('audit.checks_type.mdrsamplecheck') . ' ' . $lastchecks[$samplecheck]['checked'] . "\n";
				$certificate = json_decode($vendor['certificate'], true);
				if ($certificate['validity']) $info .= $this->_lang->GET('consumables.vendor.certificate_validity') . ' ' . $certificate['validity'] . "\n";
				if ($vendor['evaluation']){
					$vendor['evaluation'] = json_decode($vendor['evaluation'], true);
					$info .= $this->_lang->GET('consumables.vendor.last_evaluation', [':author' => $vendor['evaluation']['_author'], ':date' => $vendor['evaluation']['_date']]) . "\n";
					unset($vendor['evaluation']['_author'], $vendor['evaluation']['_date']);
					foreach($vendor['evaluation'] as $key => $value) $info .= str_replace('_', ' ', $key) . ': ' . $value . "\n";
				}

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
					'description' => $this->_lang->GET('consumables.vendor.documents_download'),
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
			'filename' => preg_replace('/' . CONFIG['forbidden']['names'][0] . '/', '', $this->_lang->GET('audit.checks_type.vendors') . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.vendors'),
			'date' => $this->_currentdate->format('y-m-d H:i')
		];

		$vendors = $this->vendors();
		$previous = ''; // given there's a text followed by links
		foreach($vendors[1] as $item){
			if ($item['type'] === 'textsection') {
				$summary['content'][$item['attributes']['name']] = $item['content'];
				$previous = $item['attributes']['name'];
			}
			if ($item['type'] === 'links') $summary['content'][$previous] .= "\n" . implode("\n", array_keys($item['content']));
		}

		$downloadfiles = [];
		$downloadfiles[$this->_lang->GET('menu.records.record_summary')] = [
			'href' => PDF::auditPDF($summary)
		];
		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}
}
?>
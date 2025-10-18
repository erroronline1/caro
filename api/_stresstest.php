<?php
/**
 * [CARO - Cloud Assisted Records and Operations](https://github.com/erroronline1/caro)  
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.  
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.  
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.  
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

namespace CARO\API;

require_once('_install.php');

class STRESSTEST extends INSTALL{
	/**
	 * identifying prefixes for creation and safe deletion, default values
	 */
	public $_prefix = 'UVIKmdEZsiuOdAYlQbhnm6UfPhD7URBY';
	public $_calendarentries = 20000;
	public $_recordentries = 20000;
	public $_orderentries = 1000;
	public $_autopermission = true;
	public $_csvInput = '../unittests/sample-csv-files-sample-6.csv';

	public $_incorporationApproval = [
		"_check" => "Quality management system migration", // will be appended to the initial message
		"user"=> [ // roles according to the configuration incorporation persmission
			"name"=> "CARO App", // appropriate name of responsible persons
			"date"=> "2025-07-11 23:56" // Y-m-d H:i
		],
		"qmo"=> [
			"name"=> "CARO App",
			"date"=> "2025-07-11 23:56"
		],
		"prrc"=> [
			"name"=> "CARO App",
			"date"=> "2025-07-11 23:56"
		],
		"ceo"=> [
			"name"=> "CARO App",
			"date"=> "2025-07-11 23:56"
		],
		"supervisor"=> [
			"name"=> "CARO App",
			"date"=> "2025-07-11 23:56"
		],
		"hazardous_materials"=> [
			"name"=> "CARO App",
			"date"=> "2025-07-11 23:56"
			]
	];

	// optional overrides of parent properties
	public $_defaultUser = "Caro App";
	public $_defaultLanguage = 'de';

	public function __construct(){
		parent::__construct();
	}

	/**
	 * display stresstest navigation
	 * overrides parent method
	 */
	public function navigation($method){
		if (method_exists($this, $method)) {
			echo '<a href="../_stresstest.php">back</a><br />';

			if ($method !== 'installDatabase' && (!isset($_SESSION['user']) || !array_intersect(['admin'], $_SESSION['user']['permissions']))){
				echo $this->printError('You have to be logged in with administrator privilege to run this. <a href="../../index.html" target="_blank">Open Caro App in new window</a>');
				die();
			}

			echo $this->{$method}();
		}
		else {
			foreach (get_class_vars(get_class($this)) as $varName => $varValue){
				if (in_array(gettype($varValue), ['string', 'integer', 'boolean']))
					echo gettype($varValue) . ': ' . $varName . ': ' . $varValue . '<br />';
				elseif (gettype($varValue) === 'array'){
					echo gettype($varValue) . ': ' . $varName . ': ';
					var_dump($varValue);
					echo '<br />';	
				}
			}
			echo $this->printWarning('DO NOT USE THIS IN PRODUCTION - DELETION OF DOCUMENTS, RISKS AND VENDORS IS CONSIDERED A REGULATORY VIOLATION, AS IS AUTOPERMISSION');
			echo $this->printWarning('AUTOAPPROVAL OF PENDING PRODUCT INCORPORATIONS SHOULD BE PROPERLY DOCUMENTED');
			echo $this->printWarning('USER AND CSVFILTER DELETION IS FOR TEST PURPOSES ONLY AND MAY LEAVE SHADOW ENTRIES.');
			echo '<br /><br />';
			$methods = get_class_methods($this);
			sort($methods);
			$delimiter = '';
			foreach ($methods as $methodName){
				if (!in_array($methodName, [
					'__construct',
					'navigation',
					'defaultPic',
					'executeSQL',
					'importJSON',
					'installDatabase',
					'printError',
					'printSuccess',
					'printWarning'
					])) {
						if ($delimiter !== substr($methodName, 0, 1)){
							echo '--------------<br /><br />';
							$delimiter = substr($methodName, 0, 1);
						}
						echo '<a href="./_stresstest.php/' . $methodName . '">' . $methodName . '</a><br /><br />';
					}
				}
			echo '<br /><br /><a href="../../index.html">exit</a>';
		}
	}

	/**
	 * csvfilter unittest
	 */
	public function csvFilterTest(){
		require_once('./_csvprocessor.php');
		$content = $this->importJSON('../unittests/', 'csvfilter', false);
		$content['filesetting']['source'] = $this->_csvInput;
		if (!isset($content['filesetting']['dialect'])) $content['filesetting']['dialect'] = CONFIG['csv']['dialect'];
		$content['filesetting']['encoding'] = CONFIG['csv']['csvprocessor_source_encoding'];
		
		$datalist = new Listprocessor($content, [
			'processedMonth' => $this->_currentdate->format("m"),
			'processedYear' => $this->_currentdate->format("Y")
		]);

		return '<pre>' . var_export($datalist, true) . '</pre>';
	}

	/**
	 * installs calendar events with prefix
	 */
	public function createCalendarEvents(){
		$this->_currentdate->modify('-12 month');
		for ($i = 0; $i < $this->_calendarentries; $i++){
			if (!($i % intval($this->_calendarentries/12/30))) $this->_currentdate->modify('+1 day');
			SQLQUERY::EXECUTE($this->_pdo, 'calendar_post', [
				'values' => [
					':id' => null,
					':type' => 'tasks',
					':span_start' => $this->_currentdate->format('Y-m-d H:i:s'),
					':span_end' => $this->_currentdate->format('Y-m-d H:i:s'),
					':author_id' => 2,
					':affected_user_id' => 2,
					':organizational_unit' => 'prosthetics2',
					':subject' => $this->_prefix . random_int(0, 1000000),
					':misc' => 'str (e.g. UTILITY::json_encoded whatnot)',
					':closed' => '',
					':alert' => 0,
					':autodelete' => 0
				]
			]);
		}
		return $this->printSuccess($i. ' task entries done, please check the application for performance');
	}

	/**
	 * deletes all calendar events with prefix
	 */
	public function removeCalendarEvents(){
		$entries = SQLQUERY::EXECUTE($this->_pdo, 'calendar_search', [
			'values' => [
				':SEARCH' => $this->_prefix
			],
			'wildcards' => 'contained'
		]);
		foreach ($entries as $entry){
			SQLQUERY::EXECUTE($this->_pdo, 'calendar_delete', [
				'values' => [
					':id' => $entry['id']
				]
			]);
		}
		return $this->printSuccess(count($entries) . ' entries with prefix ' . $this->_prefix . ' deleted');
	}

	/**
	 * installs records with prefix
	 */
	public function createRecords(){
		$this->_currentdate->modify('-12 month');
		$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$records = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');

		for ($i = 0; $i < $this->_recordentries; $i++){
			if (!($i % intval($this->_recordentries/12/30))) {
				$this->_currentdate->modify('+1 day');
			}
			$identifier = 'wolfgang' . $this->_prefix . random_int(1, $this->_recordentries);

			$content = [];
			$names = ['abc','def','ghi','jkl','mno','pqr','stu','vwx','yz'];
			foreach (array_rand($names, 4) as $component){
				$content[$names[$component]] = 'sdf' . random_int(1000, 100000000); 
			}

			$current_record = [];
			shuffle($documents);
			foreach ($documents as $document){
				$current_record[] = [
					'author' => $this->_defaultUser,
					'date' => $this->_currentdate->format('Y-m-d H:i:s'),
					'document' => $document['id'],
					'content' => UTILITY::json_encode($content)
				];

			}
			$exists = [];
			if (($record = array_search($identifier, array_column($records, 'identifier'))) !== false){
				$exists = $records[$record];
				$recordcontent = json_decode($exists['content'], true);
				$recordcontent[] = $current_record;
			}
			else $recordcontent = [$current_record];
			SQLQUERY::EXECUTE($this->_pdo, 'records_post', [
				'values' => [
					':context' => 'casedocumentation',
					':case_state' => null,
					':record_type' => 'treatment',
					':identifier' => $identifier,
					':last_user' => 2,
					':last_document' => $document['name'],
					':content' => UTILITY::json_encode($recordcontent),
					':lifespan' => null,
					':erp_case_number' => null,
					':note' => null,
					':id' => isset($exists['id']) ? $exists['id'] : null,
				]
			]);
		}
		return $this->printSuccess($i. ' records done, please check the application for performance');
	}

	/**
	 * deletes all records with prefix
	 */
	public function removeRecords(){
		$deletion = [
			'mysql' => "DELETE FROM caro_records WHERE identifier LIKE '%" . $this->_prefix . "%'",
			'sqlsrv' => "DELETE FROM caro_records WHERE identifier LIKE '%" . $this->_prefix . "%'"
		];
		$del = SQLQUERY::EXECUTE($this->_pdo, $deletion[CONFIG['sql']['use']]);
		return $this->printSuccess($del . ' entries with prefix ' . $this->_prefix . ' deleted');
	}

	/**
	 * installs approved orders with prefix
	 */
	public function createOrders(){
		$response = '';
		$vendors = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
		$products = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products_by_vendor_id', [
			'replacements' => [
				':ids' => implode(',', array_column($vendors, 'id'))
			] 
		]);
		$orders = [];
		for ($i = 0; $i < $this->_orderentries; $i++){
			$product = $products[array_rand($products)];
				$orders[] = [
				':order_data' => UTILITY::json_encode(
					[
						'quantity_label' => random_int(1,10),
						'unit_label' => random_int(0,1) ? $product['article_unit'] : '',
						'ordernumber_label' => $product['article_no'],
						'productname_label' => $product['article_name'],
						'barcode_label' => $product['article_ean'],
						'vendor_label' => $product['vendor_name'],
						'commission' => 'wolfgang' . $this->_prefix . random_int(1, $this->_orderentries),
						'orderer' => $this->_defaultUser,
						'product_id' => $product['id']
					]
				),
				':organizational_unit' => 'prosthetics2',
				':approval' => 'verified py pin',
				':ordertype' => 'order'
			];
		}
		$order = SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('order_post_approved_order'), $orders);
		foreach ($order as $chunk){
			try {
				if (SQLQUERY::EXECUTE($this->_pdo, $chunk));
			}
			catch (\Exception $e) {
				$response .= $this->printWarning('there has been an issue', [$e, $chunk]);
			}
		}
		$response .= $this->printSuccess($i. ' orders done, please check the application for performance');

		return $response;
	}

	/**
	 * deletes all orders with prefix
	 */
	public function removeOrders(){
		$deletion = [
			'mysql' => "DELETE FROM caro_consumables_approved_orders WHERE order_data LIKE '%" . $this->_prefix . "%'",
			'sqlsrv' => "DELETE FROM caro_consumables_approved_orders WHERE order_data LIKE '%" . $this->_prefix . "%'"
		];
		$del = SQLQUERY::EXECUTE($this->_pdo, $deletion[CONFIG['sql']['use']]);
		return $this->printSuccess($del . ' orders with commission containing prefix ' . $this->_prefix . ' deleted');
	}

	/**
	 * approves all documents and components
	 */
	public function approveDocuments(){
		if ($this->_autopermission) {
			$permissions = [];
			foreach (preg_split('/\W+/', CONFIG['permissions']['documentapproval']) as $permission){
				$permissions[$permission] = [
					'name' => $this->_defaultUser,
					'date' => $this->_currentdate->format("Y-m-d H:i")
				];
			}
			$DBall = [
				...SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist'),
				...SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist')
			];
			foreach ($DBall as $row){
				SQLQUERY::EXECUTE($this->_pdo, 'document_put_approve', [
					'values' => [
						':approval' => UTILITY::json_encode($permissions),
						':id' => $row['id']
					]
				]);
			}
			return $this->printSuccess('all documents in the database have been approved');
		}
		return $this->printError('autopermission has not been enabled');
	}

	/**
	 * approve all pending incorporations
	 */
	public function approvePendingIncorporations(){
		$response = '';
		if ($this->_incorporationApproval){
			$sqlchunks = [];
			$DBall = [...SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products')];
			foreach ($DBall as $row){
				if (!$row['incorporated']) continue;
				$row['incorporated'] = json_decode($row['incorporated'], true);
				if (PERMISSION::fullyapproved('incorporation', $row['incorporated'])) continue;
				
				if (isset($row['incorporated']['_check']) && isset($this->_incorporationApproval['_check'])) $row['incorporated']['_check'] .= ' ' . $this->_incorporationApproval['_check'];
				// fill up missing approvals with + operator
				$row['incorporated'] = $row['incorporated'] + $this->_incorporationApproval;

				$sqlchunks = SQLQUERY::CHUNKIFY($sqlchunks, strtr(SQLQUERY::PREPARE('consumables_put_incorporation'),
				[
					':incorporated' => $this->_pdo->quote(UTILITY::json_encode($row['incorporated'])),
					':id' => $row['id']
				]) . '; ');
			}
			foreach ($sqlchunks as $chunk){
				try {
					SQLQUERY::EXECUTE($this->_pdo, $chunk);
				}
				catch (\Exception $e) {
					$response .= $this->printWarning('there has been an issue', [$e, $chunk]);
				}
			}
			$response .= $this->printSuccess('all pending incorporation have been approved.');			
		}
		else $response .= $this->printError('incorporation approval has not been defined');

		return $response;
	}

	/**
	 * deletes all audit templates according to template file
	 */
	public function removeAudittemplates(){
		$json = $this->importJSON('../templates/', 'audits');

		$DBall = [
			...SQLQUERY::EXECUTE($this->_pdo, 'audit_get_templates')
		];
		$matches = 0;
		foreach ($DBall as $dbentry){
			foreach ($json as $entry){
				if (
					isset($entry['unit']) &&
					$dbentry['unit'] === $entry['unit'] &&
					$dbentry['hint'] == $entry['hint'] && // empty !== null
					$dbentry['objectives'] === $entry['objectives'] &&
					$dbentry['method'] === $entry['method']
					// no checking if $dbentry['content'] === $entry['content'] for db-specific character encoding
				){
					SQLQUERY::EXECUTE($this->_pdo, 'audit_delete_template', [
						'values' => [
							':id' => $dbentry['id']
						]
					]);
					$matches++;
				}
			}
		}
		return $this->printSuccess($matches . ' audit templates according to template file deleted');
	}

	/**
	 * deletes all csv filters according to template file
	 */
	public function removeCSVFilter(){
		$json = $this->importJSON('../templates/', 'csvfilter');

		$DBall = SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_datalist');

		$matches = 0;
		foreach ($DBall as $dbentry){
			foreach ($json as $entry){
				if (
					isset($entry['name']) &&
					$dbentry['name'] === $entry['name'] &&
					json_decode($dbentry['content'] ? : '', true) === $entry['content']
				){
					$deletion = [
						'mysql' => "DELETE FROM caro_csvfilter WHERE id = " . $dbentry['id'],
						'sqlsrv' => "DELETE FROM caro_csvfilter WHERE id = " . $dbentry['id']
					];
					if (SQLQUERY::EXECUTE($this->_pdo, $deletion[CONFIG['sql']['use']]))
						$matches++;
				}
			}
		}
		return $this->printSuccess($matches . ' filters according to template file deleted.');
	}


	/**
	 * deletes all documents, components and bundles according to template file if not already in use by sample records
	 */
	public function removeDocuments(){
		$json = $this->importJSON('../templates/', 'documents');

		$DBall = [
			...SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist'),
			...SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist'),
			...SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist')
		];

		// check if any of the documents have been in use, also see audit.php documentusage()
		$records = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');
		$usedid = [];
		foreach ($records as $record){
			$record['content'] = json_decode($record['content'], true);
			foreach ($record['content'] as $rc){
				if (!isset($rc['document'])) continue;
				if (!isset($usedid[$rc['document']])) $usedid[$rc['document']] = 0;
				$usedid[$rc['document']]++;
			}
		}

		$matches = 0;
		foreach ($DBall as $dbentry){
			foreach ($json as $entry){
				//ensure proper formatting
				if (!isset($entry['name'])) continue;
				$entry['regulatory_context'] = implode(',', preg_split('/[^\w\d]+/m', $entry['regulatory_context'] ? : ''));
				$entry['restricted_access'] = implode(',', preg_split('/[^\w\d]+/m', $entry['restricted_access'] ? : ''));

				if (
					isset($entry['name']) &&
					$dbentry['name'] === $entry['name'] &&
					$dbentry['alias'] === $entry['alias'] &&
					$dbentry['context'] === $entry['context'] &&
					$dbentry['unit'] === $entry['unit'] &&
					$dbentry['author'] === $entry['author'] &&
					!array_diff(explode(',', $dbentry['regulatory_context'] ? : ''), explode(',', $entry['regulatory_context'] ? : '')) &&
					$dbentry['permitted_export'] == $entry['permitted_export'] &&
					!array_diff(explode(',', $dbentry['restricted_access'] ? : ''), explode(',', $entry['restricted_access'] ? : '')) &&
					!in_array($dbentry['id'], array_keys($usedid)) // no deletion if documents are part of sample records
					// no checking if $dbdocument['content'] === $entry['content'] for db-specific character encoding
				){
					SQLQUERY::EXECUTE($this->_pdo, 'document_delete', [
						'values' => [
							':id' => $dbentry['id']
						]
					]);
					$matches++;
				}
			}
		}
		return $this->printSuccess($matches . ' components and documents according to template file deleted, not used within sample records');
	}

	/**
	 * deletes all manual entries according to template file
	 */
	public function removeManual(){
		$json = $this->importJSON('../templates/', 'manuals');

		$DBall = SQLQUERY::EXECUTE($this->_pdo, 'application_get_manual');

		$matches = 0;
		foreach ($DBall as $dbentry){
			foreach ($json as $entry){
				//ensure proper formatting
				$entry['permissions'] = implode(',', preg_split('/[^\w\d]+/m', $entry['permissions'] ? : ''));

				if (
					isset($entry['title']) &&
					$dbentry['title'] === $entry['title'] &&
					$dbentry['content'] === $entry['content'] &&
					!array_diff(explode(',', $dbentry['permissions'] ? : ''), explode(',', $entry['permissions'] ? : ''))
				){
					if (SQLQUERY::EXECUTE($this->_pdo, 'application_delete_manual', ['values' => [':id' => $dbentry['id']]]))
						$matches++;
				}
			}
		}
		return $this->printSuccess($matches . ' manual entries according to template file deleted.');
	}

	/**
	 * deletes all risk entries according to template file
	 */
	public function removeRisks(){
		$json = $this->importJSON('../templates/', 'risks');

		$DBall = SQLQUERY::EXECUTE($this->_pdo, 'risk_datalist');

		$matches = 0;
		foreach ($DBall as $dbentry){
			foreach ($json as $entry){
				//ensure proper formatting
				$entry['risk'] = implode(',', preg_split('/[^\w\d]+/m', $entry['risk'] ? : ''));
				if (
					isset($entry['type']) &&
					$dbentry['type'] === $entry['type'] &&
					$dbentry['process'] === $entry['process'] &&
					!array_diff(explode(',', $dbentry['risk'] ? : ''), explode(',', $entry['risk'] ? : '')) &&
					($dbentry['cause'] === $entry['cause'] || (!$dbentry['cause'] && !$entry['cause'])) &&
					($dbentry['measure'] === $entry['measure'] || (!$dbentry['measure'] && !$entry['measure'])) &&
					$dbentry['author'] === $entry['author']
				){
					$deletion = [
						'mysql' => "DELETE FROM caro_risks WHERE id = " . $dbentry['id'],
						'sqlsrv' => "DELETE FROM caro_risks WHERE id = " . $dbentry['id']
					];
					if (SQLQUERY::EXECUTE($this->_pdo, $deletion[CONFIG['sql']['use']]))
						$matches++;
				}
			}
		}
		return $this->printSuccess( $matches . ' risk entries according to template file deleted.');
	}

	/**
	 * deletes all text templates according to template file
	 */
	public function removeTexttemplates(){
		$json = $this->importJSON('../templates/', 'texts');

		$DBall = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_datalist');

		$matches = 0;
		foreach ($DBall as $dbentry){
			foreach ($json as $entry){
				if (
					isset($texts['type']) &&
					$dbentry['type'] === $entry['type'] &&
					$dbentry['name'] === $entry['name'] &&
					$dbentry['unit'] === $entry['unit'] &&
					$dbentry['author'] === $entry['author'] &&
					$dbentry['language'] === $entry['language']
				){
					$deletion = [
						'mysql' => "DELETE FROM caro_texttemplates WHERE id = " . $dbentry['id'],
						'sqlsrv' => "DELETE FROM caro_texttemplates WHERE id = " . $dbentry['id']
					];
					if (SQLQUERY::EXECUTE($this->_pdo, $deletion[CONFIG['sql']['use']]))
						$matches++;
				}
			}
		}
		return $this->printSuccess($matches . ' text template entries according to template file deleted.');
	}

	/**
	 * deletes all vendors according to template file
	 */
	public function removeUsers(){
		$json = $this->importJSON('../templates/', 'users');

		$DBall = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');

		$matches = 0;
		foreach ($DBall as $dbentry){
			foreach ($json as $entry){
				//ensure proper formatting
				$entry['permissions'] = implode(',', preg_split('/[^\w\d]+/m', $entry['permissions'] ? : ''));
				$entry['units'] = implode(',', preg_split('/[^\w\d]+/m', $entry['units'] ? : ''));
				
				if (
					isset($entry['name']) &&
					$dbentry['name'] === $entry['name'] &&
					!array_diff(explode(',', $dbentry['permissions'] ? : ''), explode(',', $entry['permissions'] ? : '')) &&
					!array_diff(explode(',', $dbentry['units'] ? : ''), explode(',', $entry['units'] ? : ''))
				){
					$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
						'replacements' => [
							':ids' => $dbentry['id'] ? : 0
						]
					]);
					if (SQLQUERY::EXECUTE($this->_pdo, 'user_delete', [
						'values' => [
							':id' => $dbentry['id']
						]
					]))	{
						// delete training attachments (certificates)
						foreach ($trainings as $row){
							if ($row['file_path']) UTILITY::delete('.' . $row['file_path']);
						}
						// delete user image
						if ($dbentry['image'] && $dbentry['id'] > 1) UTILITY::delete('../' . $dbentry['image']);
						$matches++;
					}
				}
			}
		}
		return $this->printSuccess($matches . ' users and their trainings according to template file deleted.');
	}

	/**
	 * deletes all vendors according to template file
	 */
	public function removeVendors(){
		$json = $this->importJSON('../templates/', 'vendors');

		$DBall = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');

		$matches = 0;
		foreach ($DBall as $dbentry){
			foreach ($json as $entry){
				if (
					isset($entry['name']) &&
					$dbentry['name'] === $entry['name'] &&
					json_decode($dbentry['info'] ? : '', true) === $entry['info']
				){
					$deletion = [
						'mysql' => "DELETE FROM caro_consumables_vendors WHERE id = " . $dbentry['id'],
						'sqlsrv' => "DELETE FROM caro_consumables_vendors WHERE id = " . $dbentry['id']
					];
					if (SQLQUERY::EXECUTE($this->_pdo, $deletion[CONFIG['sql']['use']]))
						$matches++;
				}
			}
		}
		return $this->printSuccess($matches . ' vendors according to template file deleted. Special chars within the vendors name may prevent deletion due to character encoding. If you filled the vendor fileserver directories, head over directly to the file system and don\'t mess up production server!');
	}

	/**
	 * 
	 */
	public function markdown(){
		$sample  = <<<'END'
# Plain text (h1 header)

This is a markdown flavour for basic text styling.  
Lines should end with two or more spaces  
to have an intentional linebreak
and not just continuing.

Text can be *italic*, **bold**, ***italic and bold***, ~~striked through~~, and `code style` with two ore more characters between the symbols.  
Some escaping of formatting characters is possible with a leading \ as in
**bold \* asterisk**, ~~striked \~~ through~~ and `code with a \`-character`.  
also ``code with ` escaped by double backticks``

http://some.url, not particularly styled  
a phone number: tel:012345678  
[Styled link to markdown information](https://www.markdownguide.org)

Plain text (h1 header)
-------------------
--------

## Lists (h2 header)

1. Ordered list items start with a number and a period
    * Sublist nesting
    * is possible
    * by indentating with four spaces
        1. and list types
        2. are interchangeable
2. Ordered list item 2
3. Ordered list item 3

* Unordered list items start with asterisk or dash
    1. the number
    1. of ordered lists
    2. actually doesn't
    3. matter at all
* Unordered list item 2
* Unordered list item 3

### Tables (h3 header)

| Table header 1 | Table header 2 | Table header 3 | and 4 |
| --- | --- | --- | --- |
| *emphasis* | **is** | ***possible*** | `too` |
| linebreaks | are | not | though |

- - -

#### Blockquotes and code (h4 header)

> Blockquote  
> with *multiple*  
> lines

    preformatted text/code must
    start with 4 spaces <code>

~~~
or being surrounded by
three \` or ~
~~~

## Other features:  
<http://some.other.url> with brackets, [urlencoded link with title](http://some.url?test2=2&test3=a=(/bcdef "some title") and [javascript: protocol](javascript:alert('hello there'))  
some `code with <brackets>`  
mid*word*emphasis and __underscore emphasis__  
some@mail.address and escaped\@mail.address  
![an image](http://toh.erroronline.one/caro/jackie-chan-confused-meme.jpeg) may not work in caro context because of service worker though  
123\. escaped period avoiding a list

## Nested items in lists

1. List item with
    > Blockquote as item
2. Next list item with
    |Table|Column2|
    |---|---|
    |R1C1|R1C2|
4. Last item

## Nested items in blockquotes

> * List within blockquote 1
> * List within blockquote 2
>     * Nested list
> ~~~
> Code within blockquote
> ~~~
>> Blockquote within blockquote
> 
> | Tables nested | within | blockquotes |
> | :---------- | :-----: | ---: |
> | are | possible | as well |
> | like | aligning | colums |

[top header](#plain-text)  
[second header](#plain-text-1)
END;
		$markdown = new MARKDOWN();
		return $markdown->md2html($sample);
	}
}

$stresstest = new STRESSTEST();
$stresstest->navigation(REQUEST[0]);
exit();
?>
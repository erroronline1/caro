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
			$this->{$method}();
		}
		else {
			foreach(get_class_vars(get_class($this)) as $varName => $varValue){
				if (in_array(gettype($varValue), ['string', 'integer', 'boolean']))
					echo gettype($varValue) . ': ' . $varName . ': ' . $varValue . '<br />';
			}
			echo '<br />[~] DO NOT USE THIS IN PRODUCTION - DELETION OF DOCUMENTS, RISKS AND VENDORS IS A REGULATORY VIOLATION, AS IS AUTOPERMISSION, USER DELETION IS FOR TEST PURPOSES ONLY AND MAY LEAVE SHADOW ENTRIES.';
			echo '<br /><br />';
			$methods = get_class_methods($this);
			sort($methods);
			$delimiter = '';
			foreach($methods as $methodName){
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
	 * installs calendar events with prefix
	 */
	public function createCalendarEvents(){
		$this->_currentdate->modify('-12 month');
		for ($i = 0; $i < $this->_calendarentries; $i++){
			if (!($i % intval($this->_calendarentries/12/30))) $this->_currentdate->modify('+1 day');
			SQLQUERY::EXECUTE($this->_pdo, 'calendar_post', [
				'values' => [
					':type' => 'schedule',
					':span_start' => $this->_currentdate->format('Y-m-d H:i:s'),
					':span_end' => $this->_currentdate->format('Y-m-d H:i:s'),
					':author_id' => 2,
					':affected_user_id' => 2,
					':organizational_unit' => 'prosthetics2',
					':subject' => $this->_prefix . random_int(0, 1000000),
					':misc' => 'str (e.g. UTILITY::json_encoded whatnot)',
					':closed' => '',
					':alert' => 0
				]
			]);
		}
		echo '[*] ' . $i. ' schedule entries done, please check the application for performance';
	}

	/**
	 * deletes all calendar events with prefix
	 */
	public function removeCalendarEvents(){
		$entries = SQLQUERY::EXECUTE($this->_pdo, 'calendar_search', [
			'values' => [
				':subject' => $this->_prefix
			]
		]);
		foreach($entries as $entry){
			SQLQUERY::EXECUTE($this->_pdo, 'calendar_delete', [
				'values' => [
					':id' => $entry['id']
				]
			]);
		}
		echo '[*] ' . count($entries) . ' entries with prefix ' . $this->_prefix . ' deleted';
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
			foreach(array_rand($names, 4) as $component){
				$content[$names[$component]] = 'sdf' . random_int(1000, 100000000); 
			}

			$current_record = [];
			shuffle($documents);
			foreach($documents as $document){
				$current_record[] = [
					'author' => $this->_defaultUser,
					'date' => $this->_currentdate->format('Y-m-d H:i:s'),
					'document' => $document['id'],
					'content' => UTILITY::json_encode($content)
				];

			}
			if (($record = array_search($identifier, array_column($records, 'identifier'))) !== false){
				$exists = $records[$record];
				$records = json_decode($exists['content'], true);
				$records[] = $current_record;
				$success = SQLQUERY::EXECUTE($this->_pdo, 'records_put', [
					'values' => [
						':case_state' => null,
						':record_type' => $exists['record_type'] ? : null,
						':identifier' => $identifier,
						':last_user' => 2,
						':last_document' => $document['name'],
						':content' => UTILITY::json_encode($records),
						':id' => $exists['id']
					]
				]);
			}
			else SQLQUERY::EXECUTE($this->_pdo, 'records_post', [
				'values' => [
					':context' => 'casedocumentation',
					':record_type' => 'treatment',
					':identifier' => $identifier,
					':last_user' => 2,
					':last_document' => $document['name'],
					':content' => UTILITY::json_encode($current_record),
				]
			]);
		}
		echo '[*] ' . $i. ' records done, please check the application for performance';
	}

	/**
	 * deletes all calendar events with prefix
	 */
	public function removeRecords(){
		$deletion = [
			'mysql' => "DELETE FROM caro_records WHERE identifier LIKE '%" . $this->_prefix . "%'",
			'sqlsrv' => "DELETE FROM caro_records WHERE identifier LIKE '%" . $this->_prefix . "%'"
		];
		$del = SQLQUERY::EXECUTE($this->_pdo, $deletion[CONFIG['sql']['use']]);
		echo '[*] ' . $del . ' entries with prefix ' . $this->_prefix . ' deleted';
	}

	/**
	 * installs approved orders with prefix
	 */
	public function createOrders(){
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
						'orderer' => $this->_defaultUser
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
			catch (Exception $e) {
				echo $e, $chunk;
				die();
			}
		}
		echo '[*] ' . $i. ' orders done, please check the application for performance';
	}

	/**
	 * deletes all calendar events with prefix
	 */
	public function removeOrders(){
		$deletion = [
			'mysql' => "DELETE FROM caro_consumables_approved_orders WHERE order_data LIKE '%" . $this->_prefix . "%'",
			'sqlsrv' => "DELETE FROM caro_consumables_approved_orders WHERE order_data LIKE '%" . $this->_prefix . "%'"
		];
		$del = SQLQUERY::EXECUTE($this->_pdo, $deletion[CONFIG['sql']['use']]);
		echo '[*] ' . $del . ' orders with commission containing prefix ' . $this->_prefix . ' deleted';
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
			foreach($DBall as $row){
				SQLQUERY::EXECUTE($this->_pdo, 'document_put_approve', [
					'values' => [
						':approval' => UTILITY::json_encode($permissions),
						':id' => $row['id']
					]
				]);
			}
			echo '[*] all documents in the database have been approved';
		}
		else $this->printError('autopermission has not been enabled');
	}

	/**
	 * deletes all audit templates according to template file
	 */
	public function removeAudittemplates(){
		$file = '../templates/audittemplates.' . $this->_defaultLanguage;
		$json = $this->importJSON($file);

		$DBall = [
			...SQLQUERY::EXECUTE($this->_pdo, 'audit_get_templates')
		];
		$matches = 0;
		foreach($DBall as $dbentry){
			foreach($json as $entry){
				if (
					isset($entry['unit']) &&
					$dbentry['unit'] === $entry['unit'] &&
					$dbentry['hint'] == $entry['hint'] && // empty !== null
					$dbentry['objectives'] === $entry['objectives']
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
		echo '[*] ' . $matches . ' audit templates according to template file deleted';
	}

	/**
	 * deletes all documents, components and bundles according to template file
	 */
	public function removeDocuments(){
		$file = '../templates/documents.' . $this->_defaultLanguage;
		$json = $this->importJSON($file);

		$DBall = [
			...SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist'),
			...SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist'),
			...SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist')
		];
		$matches = 0;
		foreach($DBall as $dbentry){
			foreach($json as $entry){
				//ensure proper formatting
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
					!array_diff(explode(',', $dbentry['restricted_access'] ? : ''), explode(',', $entry['restricted_access'] ? : ''))
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
		echo '[*] ' . $matches . ' components and documents according to template file deleted';
	}

	/**
	 * deletes all manual entries according to template file
	 */
	public function removeManual(){
		$file = '../templates/manual.' . $this->_defaultLanguage;
		$json = $this->importJSON($file);

		$DBall = SQLQUERY::EXECUTE($this->_pdo, 'application_get_manual');

		$matches = 0;
		foreach($DBall as $dbentry){
			foreach($json as $entry){
				//ensure proper formatting
				$entry['permissions'] = implode(',', preg_split('/[^\w\d]+/m', $entry['permissions'] ? : ''));

				if (
					isset($entry['title']) &&
					$dbentry['title'] === $entry['title'] &&
					$dbentry['content'] === $entry['content'] &&
					!array_diff(explode(',', $dbentry['permissions'] ? : ''), explode(',', $entry['permissions'] ? : ''))
				){
					if (SQLQUERY::EXECUTE($this->_pdo, 'application_delete_manual', ['values'=> [':id' => $dbentry['id']]]))
						$matches++;
				}
			}
		}
		echo '[*] ' . $matches . ' manual entries according to template file deleted.';
	}

	/**
	 * deletes all risk entries according to template file
	 */
	public function removeRisks(){
		$file = '../templates/risks.' . $this->_defaultLanguage;
		$json = $this->importJSON($file);

		$DBall = SQLQUERY::EXECUTE($this->_pdo, 'risk_datalist');

		$matches = 0;
		foreach($DBall as $dbentry){
			foreach($json as $entry){
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
		echo '[*] ' . $matches . ' risk entries according to template file deleted.';
	}

	/**
	 * deletes all text templates according to template file
	 */
	public function removeTexttemplates(){
		$file = '../templates/texttemplates.' . $this->_defaultLanguage;
		$json = $this->importJSON($file);

		$DBall = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_datalist');

		$matches = 0;
		foreach($DBall as $dbentry){
			foreach($json as $entry){
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
					if(SQLQUERY::EXECUTE($this->_pdo, $deletion[CONFIG['sql']['use']]))
						$matches++;
				}
			}
		}
		echo '[*] ' . $matches . ' manual entries according to template file deleted.';
	}

	/**
	 * deletes all vendors according to template file
	 */
	public function removeUsers(){
		$file = '../templates/users';
		$json = $this->importJSON($file);

		$DBall = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');

		$matches = 0;
		foreach($DBall as $dbentry){
			foreach($json as $entry){
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
		echo '[*] ' . $matches . ' users and their trainings according to template file deleted.';
	}

	/**
	 * deletes all vendors according to template file
	 */
	public function removeVendors(){
		$file = '../templates/vendors.' . $this->_defaultLanguage;
		$json = $this->importJSON($file);

		$DBall = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');

		$matches = 0;
		foreach($DBall as $dbentry){
			foreach($json as $entry){
				if (
					isset($entry['name']) &&
					$dbentry['name'] === $entry['name'] &&
					json_decode($dbentry['info'] ? : '', true) === $entry['info']
				){
					$deletion = [
						'mysql' => "DELETE FROM caro_consumables_vendors WHERE id = " . $dbentry['id'],
						'sqlsrv' => "DELETE FROM caro_consumables_vendors WHERE id = " . $dbentry['id']
					];
					if(SQLQUERY::EXECUTE($this->_pdo, $deletion[CONFIG['sql']['use']]))
						$matches++;
				}
			}
		}
		echo '[*] ' . $matches . ' vendors according to template file deleted. Special chars within the vendors name may prevent deletion due to character encoding. If you filled the immutable_fileserver directories, head over directly to the file system and don\'t mess up production server!';
	}
}

$stresstest = new STRESSTEST();
$stresstest->navigation(REQUEST[0]);
exit();
?>
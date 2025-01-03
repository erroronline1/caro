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

ini_set('display_errors', 1); error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=UTF-8');
require_once('_config.php');
@define ('REQUEST', explode("/", substr(mb_convert_encoding($_SERVER['PATH_INFO'], 'UTF-8', mb_detect_encoding($_SERVER['PATH_INFO'], ['ASCII', 'UTF-8', 'ISO-8859-1'])), 1)));
require_once('_sqlinterface.php');


class STRESSTEST{
	/**
	 * preset database connection
	 */
	public $_pdo;
	
	/**
	 * current date with correct timezone
	 */
	public $_currentdate;

	/**
	 * identifying prefixes for creation and safe deletion, default values
	 */
	public $_prefix = 'UVIKmdEZsiuOdAYlQbhnm6UfPhD7URBY';
	public $_caleandarentries = 20000;
	public $_recordentries = 20000;
	public $_orderentries = 1000;
	public $_documenttemplate = '../templates/documents.de.json';
	public $_autopermission = true;
	public $_author = "Caro App";
	public $_vendortemplate = '../templates/vendors.de.json';

	public function __construct($method){
		$options = [
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // always fetch assoc
			\PDO::ATTR_EMULATE_PREPARES   => true, // reuse tokens in prepared statements
		];
		$this->_pdo = new PDO( CONFIG['sql'][CONFIG['sql']['use']]['driver'] . ':' . CONFIG['sql'][CONFIG['sql']['use']]['host'] . ';' . CONFIG['sql'][CONFIG['sql']['use']]['database']. ';' . CONFIG['sql'][CONFIG['sql']['use']]['charset'], CONFIG['sql'][CONFIG['sql']['use']]['user'], CONFIG['sql'][CONFIG['sql']['use']]['password'], $options);
		$dbsetup = SQLQUERY::PREPARE('DYNAMICDBSETUP');
		if ($dbsetup) $this->_pdo->exec($dbsetup);

		$this->_currentdate = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
		if (method_exists($this, $method)) {
			echo '<a href="../_stresstest.php">back</a><br />';
			$this->{$method}();
		}
		else {
			foreach(get_class_vars(get_class($this)) as $varName => $varValue){
				if (in_array(gettype($varValue), ['string', 'integer', 'boolean']))
					echo gettype($varValue) . ': ' . $varName . ': ' . $varValue . '<br />';
			}
			echo '<br />';
			foreach(get_class_methods($this) as $methodName){
				if ($methodName !== '__construct') echo '<a href="./_stresstest.php/' . $methodName . '">' . $methodName . '</a><br />';
			}
		}
	}

	public function createCalendarEvents(){
		$this->_currentdate->modify('-12 month');
		for ($i = 0; $i < $this->_caleandarentries; $i++){
			if (!($i % intval($this->_caleandarentries/12/30))) $this->_currentdate->modify('+1 day');
			SQLQUERY::EXECUTE($this->_pdo, 'calendar_post', [
				'values' => [
					':type' => 'schedule',
					':span_start' => $this->_currentdate->format('Y-m-d H:i:s'),
					':span_end' => $this->_currentdate->format('Y-m-d H:i:s'),
					':author_id' => 2,
					':affected_user_id' => 2,
					':organizational_unit' => 'prosthetics2',
					':subject' => $this->_prefix . random_int(0, 1000000),
					':misc' => 'str (e.g. json_encoded whatnot)',
					':closed' => '',
					':alert' => 0
				]
			]);
		}
		echo $i. ' schedule entries done, please check the application for performance';
	}

	public function deleteCalendarEvents(){
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
		echo count($entries) . ' entries with prefix ' . $this->_prefix . ' deleted';
	}

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
					'author' => $this->_author,
					'date' => $this->_currentdate->format('Y-m-d H:i:s'),
					'document' => $document['id'],
					'content' => json_encode($content)
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
						':content' => json_encode($records),
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
					':content' => json_encode($current_record),
				]
			]);
		}
		echo $i. ' records done, please check the application for performance';
	}

	public function deleteRecords(){
		$deletion = [
			'mysql' => "DELETE FROM caro_records WHERE identifier LIKE '%" . $this->_prefix . "%'",
			'sqlsrv' => "DELETE FROM caro_records WHERE identifier LIKE '%" . $this->_prefix . "%'"
		];
		$del = SQLQUERY::EXECUTE($this->_pdo, $deletion[CONFIG['sql']['use']]);
		echo $del . ' entries with prefix ' . $this->_prefix . ' deleted';
	}

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
				':order_data' => json_encode(
					[
						'quantity_label' => random_int(1,10),
						'unit_label' => random_int(0,1) ? $product['article_unit'] : '',
						'ordernumber_label' => $product['article_no'],
						'productname_label' => $product['article_name'],
						'barcode_label' => $product['article_ean'],
						'vendor_label' => $product['vendor_name'],
						'commission' => 'wolfgang' . $this->_prefix . random_int(1, $this->_orderentries),
						'orderer' => $this->_author
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
		echo $i. ' orders done, please check the application for performance';
	}

	public function deleteOrders(){
		$deletion = [
			'mysql' => "DELETE FROM caro_consumables_approved_orders WHERE order_data LIKE '%" . $this->_prefix . "%'",
			'sqlsrv' => "DELETE FROM caro_consumables_approved_orders WHERE order_data LIKE '%" . $this->_prefix . "%'"
		];
		$del = SQLQUERY::EXECUTE($this->_pdo, $deletion[CONFIG['sql']['use']]);
		echo $del . ' orders with commission containing prefix ' . $this->_prefix . ' deleted';
	}

	public function installDocuments(){
		if (!realpath($this->_documenttemplate)) {
			echo $this->_documenttemplate . ' file not found';
			return;
		}

		$documents = file_get_contents(realpath($this->_documenttemplate));
		$documents = json_decode($documents, true);
		$matches = 0;

		$DBcomponents = SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist');
		$DBdocuments = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$DBbundles = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist');

		$DBall = [...$DBcomponents, ...$DBdocuments, ...$DBbundles];

		$permissions = [];
		foreach (preg_split('/\W+/', CONFIG['permissions']['documentapproval']) as $permission){
			$permissions[$permission] = [
				'name' => $this->_author,
				'date' => $this->_currentdate->format("Y-m-d H:i")
			];
		}
		foreach ($documents as $document){
			if (isset($document['name']) && $document['name'] && !in_array($document['name'], array_column($DBall, 'name'))) {
				if (gettype($document['content']) === 'array') $document['content'] = json_encode($document['content']);
				if (SQLQUERY::EXECUTE($this->_pdo, 'document_post', [
					'values' => [
						':name' => $document['name'],
						':alias' => $document['alias'],
						':context' => $document['context'],
						':unit' => $document['unit'],
						':author' => $document['author'],
						':content' => $document['content'],
						':regulatory_context' => $document['regulatory_context'],
						':permitted_export' => $document['permitted_export'],
						':restricted_access' => $document['restricted_access']
					]
				])){
					$matches++;
					if ($this->_autopermission) SQLQUERY::EXECUTE($this->_pdo, 'document_put_approve', [
						'values' => [
							':approval' => json_encode($permissions),
							':id' => $this->_pdo->lastInsertId()
						]
					]);
				}
			}
		}
		echo $matches . ' components, documents and bundles with novel names according to template file inserted, please check the application for performance' . (!$this->_autopermission ? ' and remember you have to approve each to take effect' : '' ) . '!';
	}

	public function deleteDocuments(){
		if (!realpath($this->_documenttemplate)) {
			echo $this->_documenttemplate . ' file not found';
			return;
		}

		$DBcomponents = SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist');
		$DBdocuments = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$DBbundles = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist');

		$documents = file_get_contents(realpath($this->_documenttemplate));
		$documents = json_decode($documents, true);
		$matches = 0;
		foreach([...$DBcomponents, ...$DBdocuments, ...$DBbundles] as $dbdocument){
			foreach($documents as $document){
				if (
					isset($document['name']) &&
					$dbdocument['name'] === $document['name'] &&
					$dbdocument['alias'] === $document['alias'] &&
					$dbdocument['context'] === $document['context'] &&
					$dbdocument['unit'] === $document['unit'] &&
					$dbdocument['author'] === $document['author'] &&
					$dbdocument['regulatory_context'] === $document['regulatory_context'] &&
					$dbdocument['permitted_export'] == $document['permitted_export'] &&
					$dbdocument['restricted_access'] === $document['restricted_access']
					// no checking if $dbdocument['content'] === $document['content'] for db-specific character encoding
				){
					SQLQUERY::EXECUTE($this->_pdo, 'document_delete', [
						'values' => [
							':id' => $dbdocument['id']
						]
					]);
					$matches++;
				}
			}
		}
		echo $matches . ' components and documents according to template file deleted';
	}

	public function installVendors(){
		if (!realpath($this->_vendortemplate)) {
			echo $this->_vendortemplate . ' file not found';
			return;
		}

		$vendors = file_get_contents(realpath($this->_vendortemplate));
		$vendors = json_decode($vendors, true);
		$matches = 0;
		$DBvendors = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');

		foreach ($vendors as $vendor){
			if (isset($vendor['name']) && $vendor['name'] && !in_array($vendor['name'], array_column($DBvendors, 'name'))) {
				$vendordata = [
					'name' => $vendor['name'],
					'active' => 1,
					'info' => $vendor['info'],
					'certificate' => [],
					'pricelist' => ['filter' => $vendor['pricelist']],
					'immutable_fileserver'=> preg_replace(CONFIG['forbidden']['names'][0], '', $vendor['name']) . $this->_currentdate->format('Ymd'),
					'evaluation' => ''
				];
				if (SQLQUERY::EXECUTE($this->_pdo, 'consumables_post_vendor', [
					'values' => [
						':name' => $vendordata['name'],
						':active' => $vendordata['active'],
						':info' => json_encode($vendordata['info']),
						':certificate' => json_encode($vendordata['certificate']),
						':pricelist' => json_encode($vendordata['pricelist']),
						':immutable_fileserver' => $vendordata['immutable_fileserver'],
						':evaluation' => $vendordata['evaluation']
					]
				])){
					$matches++;
				}
			}
		}
		echo $matches . ' vendors installed, please check the application for performance and remember you may have to do vendor evaluation on each! Only vendors with novel names have been added. THIS DOES NOT APPLY FOR DELETION IF NAME AND INFO ARE THE SAME AS IN THE TEMPLATE!';
	}

	public function deleteVendors(){
		if (!realpath($this->_vendortemplate)) {
			echo $this->_vendortemplate . ' file not found';
			return;
		}

		$DBvendors = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');

		$vendors = file_get_contents(realpath($this->_vendortemplate));
		$vendors = json_decode($vendors, true);
		$matches = 0;
		foreach($DBvendors as $dbvendor){
			foreach($vendors as $vendor){
				if (
					isset($vendor['name']) &&
					$dbvendor['name'] === $vendor['name'] &&
					json_decode($dbvendor['info'], true) === $vendor['info']
				){
					$deletion = [
						'mysql' => "DELETE FROM caro_consumables_vendors WHERE id = " . $dbvendor['id'],
						'sqlsrv' => "DELETE FROM caro_consumables_vendors WHERE id = " . $dbvendor['id']
					];
					$del = SQLQUERY::EXECUTE($this->_pdo, $deletion[CONFIG['sql']['use']]);
					$matches++;
				}
			}
		}
		echo $matches . ' vendors according to template file deleted. Special chars within the vendors name may prevent deletion due to character encoding. If you filled the immutable_fileserver directories, head over directly to the file system and don\'t mess up production server!';
	}
}

$stresstest = new STRESSTEST(REQUEST[0]);
exit();
?>
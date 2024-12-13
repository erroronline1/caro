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

ini_set('display_errors', 1); error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=UTF-8');
define ('CONFIG', parse_ini_file('config.ini', true));
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
	public $_template = '../templates/forms.de.json';
	public $_autopermission = true;
	public $_author = "Caro App";

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
		$forms = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
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
			shuffle($forms);
			foreach($forms as $form){
				$current_record[] = [
					'author' => $this->_author,
					'date' => $this->_currentdate->format('Y-m-d H:i:s'),
					'form' => $form['id'],
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
						':last_form' => $form['id'],
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
					':last_form' => $form['id'],
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

	public function installForms(){
		if (!realpath($this->_template)) {
			echo $this->_template . ' file not found';
			return;
		}

		$parameters = [];
		$forms = file_get_contents(realpath($this->_template));
		$forms = json_decode($forms, true);
		$matches = 0;

		$permissions = [];
		foreach (preg_split('/\W+/', CONFIG['permissions']['formapproval']) as $permission){
			$permissions[$permission] = [
				'name' => $this->_author,
				'date' => $this->_currentdate->format("Y-m-d H:i")
			];
		}
		foreach ($forms as $form){
			if (isset($form['name'])) {
				if (gettype($form['content']) === 'array') $form['content'] = json_encode($form['content']);
				if (SQLQUERY::EXECUTE($this->_pdo, 'form_post', [
					'values' => [
						':name' => $form['name'],
						':alias' => $form['alias'],
						':context' => $form['context'],
						':unit' => $form['unit'],
						':author' => $form['author'],
						':content' => $form['content'],
						':regulatory_context' => $form['regulatory_context'],
						':permitted_export' => $form['permitted_export'],
						':restricted_access' => $form['restricted_access']
					]
				])){
					$matches++;
					if ($this->_autopermission) SQLQUERY::EXECUTE($this->_pdo, 'form_put_approve', [
						'values' => [
							':approval' => json_encode($permissions),
							':id' => $this->_pdo->lastInsertId()
						]
						]);
				}
			}
		}
		echo $matches . ' components and forms according to template file inserted, please check the application for performance and remember you have to approve each to take effect!';
	}

	public function deleteForms(){
		if (!realpath($this->_template)) {
			echo $this->_template . ' file not found';
			return;
		}

		$DBcomponents = SQLQUERY::EXECUTE($this->_pdo, 'form_component_datalist');
		$DBforms = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		$DBbundles = SQLQUERY::EXECUTE($this->_pdo, 'form_bundle_datalist');

		$forms = file_get_contents(realpath($this->_template));
		$forms = json_decode($forms, true);
		$matches = 0;
		foreach([...$DBcomponents, ...$DBforms, ...$DBbundles] as $dbform){
			foreach($forms as $form){
				if (
					isset($form['name']) &&
					$dbform['name'] === $form['name'] &&
					$dbform['alias'] === $form['alias'] &&
					$dbform['context'] === $form['context'] &&
					$dbform['unit'] === $form['unit'] &&
					$dbform['author'] === $form['author'] &&
					$dbform['regulatory_context'] === $form['regulatory_context'] &&
					$dbform['permitted_export'] == $form['permitted_export'] &&
					$dbform['restricted_access'] === $form['restricted_access']
					// no checking if $dbform['content'] === $form['content'] for db-specific character encoding
				){
					SQLQUERY::EXECUTE($this->_pdo, 'form_delete', [
						'values' => [
							':id' => $dbform['id']
						]
					]);
					$matches++;
				}
			}
		}
		echo $matches . ' components and forms according to template file deleted';

	}
}

$stresstest = new STRESSTEST(REQUEST[0]);
exit();
?>
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
define ('INI', parse_ini_file('setup.ini', true));
@define ('REQUEST', explode("/", substr(mb_convert_encoding($_SERVER['PATH_INFO'], 'UTF-8', mb_detect_encoding($_SERVER['PATH_INFO'], ['ASCII', 'UTF-8', 'ISO-8859-1'])), 1)));
include_once('_sqlinterface.php');


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
	 * identifying prefixes for creation and safe deletion
	 */
	public $_prefix = 'UVIKmdEZsiuOdAYlQbhnm6UfPhD7URBY';
	public $_number = 10000;

	public function __construct($method){
		$options = [
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // always fetch assoc
			\PDO::ATTR_EMULATE_PREPARES   => true, // reuse tokens in prepared statements
		];
		$this->_pdo = new PDO( INI['sql'][INI['sql']['use']]['driver'] . ':' . INI['sql'][INI['sql']['use']]['host'] . ';' . INI['sql'][INI['sql']['use']]['database']. ';' . INI['sql'][INI['sql']['use']]['charset'], INI['sql'][INI['sql']['use']]['user'], INI['sql'][INI['sql']['use']]['password'], $options);
		$dbsetup = SQLQUERY::PREPARE('DYNAMICDBSETUP');
		if ($dbsetup) $this->_pdo->exec($dbsetup);

		$this->_currentdate = new DateTime('now', new DateTimeZone(INI['application']['timezone']));
		if (method_exists($this, $method)) {
			echo '<a href="../_stresstest.php">back</a><br />';
			$this->{$method}();
		}
		else {
			foreach(get_class_methods($this) as $methodName){
				if ($methodName !== '__construct') echo '<a href="./_stresstest.php/' . $methodName . '">' . $methodName . '</a><br />';
			}
		}
	}

	public function createCalendarEvents(){
		$this->_currentdate->modify('-6 month');
		for ($i = 0; $i < $this->_number;$i++){
			if (!($i % intval($this->_number/12/30))) $this->_currentdate->modify('+1 day');
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
		echo $i. " schedule entries done, please check the application for performance";
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
		$this->_currentdate->modify('-6 month');
		$forms = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		for ($i = 0; $i < $this->_number;$i++){
			if (!($i % intval($this->_number/12/30))) {
				$this->_currentdate->modify('+1 day');
			}
			$form = array_rand($forms);
			$identifier = 'wolfgang' . $this->_prefix . intval($i % intval($this->_number/12));

			$content = [];
			$names = ['abc','def','ghi','jkl','mno','pqr','stu','vwx','yz'];
			foreach(array_rand($names, 4) as $component){
				$content[$names[$component]] = 'sdf' . random_int(1000,100000000); 
			}

			SQLQUERY::EXECUTE($this->_pdo, 'records_post', [
				'values' => [
					':context' => 'casedocumentation',
					':form_name' => $forms[$form]['name'],
					':form_id' => $forms[$form]['id'],
					':identifier' => $identifier,
					':author' => 'error on line 1',
					':author_id' => 2,
					':content' => json_encode($content),
					':entry_timestamp' => $this->_currentdate->format('Y-m-d H:i:s'),
					':record_type' => 'treatment'
				]
			]);
		}
		echo $i. " records done, please check the application for performance";
	}

	public function deleteRecords(){
		$deletion = [
			'mysql' => "DELETE FROM caro_records WHERE identifier LIKE '%" . $this->_prefix . "%'",
			'sqlsrv' => "DELETE FROM caro_records WHERE identifier LIKE '%" . $this->_prefix . "%'"
		];
		$del = SQLQUERY::EXECUTE($this->_pdo, $deletion[INI['sql']['use']]);
		echo $del . ' entries with prefix ' . $this->_prefix . ' deleted';
	}
}

$stresstest = new STRESSTEST(REQUEST[0]);
exit();
?>
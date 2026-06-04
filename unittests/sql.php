<?php
/**
 * SPDX-FileNotice: Part of erroronline1/caro a quality management software.
 * SPDX-FileCopyrightText: © 2023 error on line 1 <dev@erroronline.one>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

namespace CARO\API;
// actions require an application user with admin permission to be logged in onve the database has been installed
session_set_cookie_params([
	'domain' => $_SERVER['HTTP_HOST'],
	'secure' => true,
	'httponly' => true,
]);
session_start();

ini_set('display_errors', 1); error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=UTF-8');
@define ('REQUEST', explode("/", substr(mb_convert_encoding($_SERVER['PATH_INFO'], 'UTF-8', mb_detect_encoding($_SERVER['PATH_INFO'], ['ASCII', 'UTF-8', 'ISO-8859-1'])), 1)));
require_once('../api/_config.php');
require_once('../api/_utility.php');
require_once('../api/_sqlinterface.php');

class SQLTEST {
	private mixed $_sqlinterface = null;
	/**
	 * 10k insertions take about 30s of time, selections about 20s  
	 * set to an appropriate amount, make a cluster switch meanwhile and check for potential losses
	 */
	private int $_insertions = 5_000;
	
	/**
	 * *id* primary key, auto increment  
	 * *value* text | varchar(1000)  
	 * *timestamp* datetime
	 */
	private string $_table = 'dbo.Table_1';

	public function __construct(){
		$this->_sqlinterface = new SQLINTERFACE([
			"driver" => "sqlsrv",
			"host" => "Server=LABSQL-DAG1, 1433",
			"user" => "carosql",
			"password" => "123456",
			"database" => "Database=caro",
			"charset" => "",
			"packagesize" => 4096
		]);
	}

	/**
	 * display navigation
	 * @param string $method
	 */
	public function navigation($method){
		foreach (get_class_vars(get_class($this)) as $varName => $varValue){
			if (in_array(gettype($varValue), ['string', 'integer', 'boolean']))
				echo gettype($varValue) . ': ' . $varName . ': ' . $varValue . '<br />';
			elseif (gettype($varValue) === 'array'){
				echo gettype($varValue) . ': ' . $varName . ': ';
				var_dump($varValue);
				echo '<br />';	
			}
		}
		$methods = get_class_methods($this);
		sort($methods);
		$delimiter = '';
		foreach ($methods as $methodName){
			if (!in_array($methodName, [
				'__construct',
				'navigation',
				'printError',
				'printSuccess',
				'printWarning'
				])) {
					if ($delimiter !== substr($methodName, 0, 1)){
						echo '--------------<br /><br />';
						$delimiter = substr($methodName, 0, 1);
					}
					echo '<a href="' . ($method ? '..' : '.') .'/sql.php/' . $methodName . '">' . $methodName . '</a><br /><br />';
				}
			}

		if (method_exists($this, $method)) {
			if (!isset($_SESSION['user']) || !array_intersect(['admin'], $_SESSION['user']['permissions'])){
				echo $this->printError('You have to be logged in with administrator privilege to run this. <a href="../index.html" target="_blank">Open Caro App in new window</a>');
				die();
			}
			echo $this->{$method}();
		}
	}

	/**
	 * unifies display of errors
	 * @param string $message to display
	 * @param string|array $item entry item from source
	 */
	public function printError($message = '', $item = []){
		return '<br />[X] ' . ($message ? : '') . ($item ? '<br /><code>' . (gettype($item) === 'array' ? UTILITY::json_encode($item) : $item) . '</code>' : '') . '<br />';
	}

	/**
	 * unifies display of success
	 * @param string $message to display
	 * @param string|array $item entry item from source
	 */
	public function printSuccess($message = '', $item = []){
		return '<br />[*] ' . ($message ? : '') . ($item ? '<br /><code>' . (gettype($item) === 'array' ? UTILITY::json_encode($item) : $item) . '</code>' : '') . '<br />';
	}

	/**
	 * unifies display of warnings
	 * @param string $message to display
	 * @param string|array $item entry item from source
	 */
	public function printWarning($message = '', $item = []){
		return '<br />[!] ' . ($message ? : '') . ($item ? '<br /><code>' . (gettype($item) === 'array' ? UTILITY::json_encode($item) : $item) . '</code>' : '') . '<br />';
	}


	/**
	 * executes the set amount of insertions  
	 * measures the time and checks if the row-count matches
	 */
	public function insert(){
		$result = '';
		for ($i = 0; $i < $this->_insertions; $i++){
			$this->_sqlinterface->EXECUTE("INSERT INTO " . $this->_table . " (value, timestamp) VALUES ('". hash("sha512", $i+random_int(0,10000000)) . "', CURRENT_TIMESTAMP)");
		}
		$result .= $this->printSuccess('execution time to write : ' . microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]);

		$num = $this->_sqlinterface->EXECUTE("SELECT COUNT(id) as num FROM " . $this->_table);
		$num = $num ? intval($num[0]['num']) : 0;

		if ($num !== $this->_insertions) $result .=  $this->printError('Oh no! Off by '. $this->_insertions-$num);
		else $result .=  $this->printSuccess('all supposed ' . $this->_insertions . ' rows in database have been inserted');
		return $result;
	}

	/**
	 * reads the set amount of insertions  
	 * measures the time and checks if the ids match the range of insertions
	 */
	public function read(){
		$result = '';
		$asserted = [0];
		for ($i = 0; $i <= $this->_insertions; $i++){
			if ($row = $this->_sqlinterface->EXECUTE("SELECT * FROM " . $this->_table . " WHERE id=" . $i))
				$asserted[] = intval($row[0]['id']);
		}
		$result .=  $this->printSuccess('execution time to read: ' . microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]);

		if ($asserted != range(0, $this->_insertions)) $result .= $this->printError('Oh no! One or more queries have been lost: ' . implode(', ', array_diff($asserted, range(0, $this->_insertions))));
		else $result .= $this->printSuccess('all supposed ' .  $this->_insertions . ' rows in database have been read');
		return $result;
	}

	/**
	 * truncates the table  
	 * required for a successful match of ids for read-test
	 */
	public function truncate(){
		$this->_sqlinterface->EXECUTE("TRUNCATE TABLE " . $this->_table);
		return $this->printSuccess('table has been truncated again');
	}
}

$sqltest = new SQLTEST();
$sqltest->navigation(REQUEST[0]);
exit();
?>
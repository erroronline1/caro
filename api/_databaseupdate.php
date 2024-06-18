<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=UTF-8');
define ('INI', parse_ini_file('setup.ini', true));
include_once('_sqlinterface.php');

class UPDATE{
	public $_pdo;

	public function __construct(){
		$options = [
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // always fetch assoc
			\PDO::ATTR_EMULATE_PREPARES   => true, // reuse tokens in prepared statements
		];
		$this->_pdo = new PDO( INI['sql'][INI['sql']['use']]['driver'] . ':' . INI['sql'][INI['sql']['use']]['host'] . ';' . INI['sql'][INI['sql']['use']]['database']. ';' . INI['sql'][INI['sql']['use']]['charset'], INI['sql'][INI['sql']['use']]['user'], INI['sql'][INI['sql']['use']]['password'], $options);
		$dbsetup = SQLQUERY::PREPARE('DYNAMICDBSETUP');
		if ($dbsetup) $this->_pdo->exec($dbsetup);
	}

	public function update(){
		$driver = INI['sql'][INI['sql']['use']]['driver'];
		foreach(['_2024_06_18'] as $update){
			foreach($this->{$update}()[$driver] as $query){
				echo $query . '<br />';
				SQLQUERY::EXECUTE($this->_pdo, $query);
			}
		}
	}

	private function _2024_06_18(){
		return [
			'mysql' => [
				/*"DELIMITER $$" .
				" DROP PROCEDURE IF EXISTS upgrade_database $$" .
				" CREATE PROCEDURE upgrade_database()" .
				" BEGIN" .
				// add a column safely
				" IF NOT EXISTS( (SELECT * FROM info.COLUMNS WHERE TABLE_SCHEMA=DATABASE()" .
				"    AND COLUMN_NAME='info' AND TABLE_NAME='caro_consumables_products') ) THEN" .
				"    ALTER TABLE caro_consumables_products ADD info TEXT NULL DEFAULT '';" .
				" END IF;" .
				" END $$" .
				" CALL upgrade_database() $$" .
				" DELIMITER ;"*/
				"ALTER TABLE caro_consumables_products ADD COLUMN IF NOT EXISTS info TEXT NULL DEFAULT '';"
			],
			'sqlsrv' => [
				"IF COL_LENGTH('caro_consumables_products', 'info') IS NULL" .
				" BEGIN" .
				"    ALTER TABLE caro_consumables_products" .
				"    ADD info VARCHAR(MAX) NULL DEFAULT ''" .
				" END"
			]
		];
	}
}
$db = new UPDATE();
$db->update();
?>
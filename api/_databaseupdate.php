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
require_once('_sqlinterface.php');

class UPDATE{
	public $_pdo;
	private $driver;

	public function __construct(){
		$options = [
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // always fetch assoc
			\PDO::ATTR_EMULATE_PREPARES   => true, // reuse tokens in prepared statements
		];
		$this->_pdo = new PDO( CONFIG['sql'][CONFIG['sql']['use']]['driver'] . ':' . CONFIG['sql'][CONFIG['sql']['use']]['host'] . ';' . CONFIG['sql'][CONFIG['sql']['use']]['database']. ';' . CONFIG['sql'][CONFIG['sql']['use']]['charset'], CONFIG['sql'][CONFIG['sql']['use']]['user'], CONFIG['sql'][CONFIG['sql']['use']]['password'], $options);
		$dbsetup = SQLQUERY::PREPARE('DYNAMICDBSETUP');
		if ($dbsetup) $this->_pdo->exec($dbsetup);
		$this->driver = CONFIG['sql'][CONFIG['sql']['use']]['driver'];
	}

	public function update(){
		foreach(['_2025_05_15'] as $update){
			foreach($this->{$update}() as $query){
				echo $query . '<br />';
				if (SQLQUERY::EXECUTE($this->_pdo, $this->backup($query)[0]) !== false)	SQLQUERY::EXECUTE($this->_pdo, $query);
				else {
					echo 'This update has been skipped because the database backup failed. Aborting further updates. Please contact the developer' . '<br />';
					die();
				}
			}
		}
	}

	private function backup($query){
		preg_match("/ALTER TABLE (.+?) /m", $query, $table);
		if (!$table[1]) return false;
		return [
			'mysql' => [
				"DROP TABLE IF EXISTS BACKUP_" . $table[1]. "; CREATE TABLE BACKUP_" . $table[1]. " LIKE " . $table[1]. "; INSERT INTO BACKUP_" . $table[1]. " SELECT * FROM " . $table[1] . ";"
			],
			'sqlsrv' => [
				"IF OBJECT_ID(N'dbo.BACKUP_" . $table[1]. "', N'U') IS NOT NULL DROP TABLE BACKUP_" . $table[1]. "; SELECT * INTO BACKUP_" . $table[1]. " FROM " . $table[1] . ";"
			]
		][$this->driver];
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
		][$this->driver];
	}
	private function _2024_09_07(){
		return [
			'mysql' => [
				"ALTER TABLE caro_consumables_products ADD COLUMN IF NOT EXISTS last_order datetime NULL DEFAULT NULL;"
			],
			'sqlsrv' => [
				"IF COL_LENGTH('caro_consumables_products', 'last_order') IS NULL" .
				" BEGIN" .
				"    ALTER TABLE caro_consumables_products" .
				"    ADD last_order smalldatetime NULL DEFAULT NULL" .
				" END"
			]
		][$this->driver];
	}
	private function _2025_05_15(){
		return [
			'mysql' => [
				"ALTER TABLE caro_consumables_products ADD COLUMN IF NOT EXISTS stock_item tinyint NULL DEFAULT NULL;"
			],
			'sqlsrv' => [
				"IF COL_LENGTH('caro_consumables_products', 'stock_item') IS NULL" .
				" BEGIN" .
				"    ALTER TABLE caro_consumables_products" .
				"    ADD stock_item tinyint NULL DEFAULT NULL" .
				" END"
			]
		][$this->driver];
	}

}
$db = new UPDATE();
$db->update();
?>
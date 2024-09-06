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
include_once('_sqlinterface.php');

class UPDATE{
	public $_pdo;
	private $driver;

	public function __construct(){
		$options = [
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // always fetch assoc
			\PDO::ATTR_EMULATE_PREPARES   => true, // reuse tokens in prepared statements
		];
		$this->_pdo = new PDO( INI['sql'][INI['sql']['use']]['driver'] . ':' . INI['sql'][INI['sql']['use']]['host'] . ';' . INI['sql'][INI['sql']['use']]['database']. ';' . INI['sql'][INI['sql']['use']]['charset'], INI['sql'][INI['sql']['use']]['user'], INI['sql'][INI['sql']['use']]['password'], $options);
		$dbsetup = SQLQUERY::PREPARE('DYNAMICDBSETUP');
		if ($dbsetup) $this->_pdo->exec($dbsetup);
		$this->driver = INI['sql'][INI['sql']['use']]['driver'];
	}

	public function update(){
		foreach(['_2024_06_18'] as $update){
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
				"IF OBJECT_ID(N'dbo.caro_user_training', N'U') IS NOT NULL DROP TABLE BACKUP_" . $table[1]. "; CREATE TABLE BACKUP_" . $table[1]. " LIKE " . $table[1]. "; INSERT INTO BACKUP_" . $table[1]. " SELECT * FROM " . $table[1] . ";"
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
}
$db = new UPDATE();
$db->update();
?>
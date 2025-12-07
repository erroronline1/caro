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

ini_set('display_errors', 1); error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=UTF-8');
require_once('_utility.php'); // general utilities
require_once('_config.php');
require_once('_sqlinterface.php');

// didn't bother handling exceptions like missing driver instructions. if you edit this you are supposed to fairly know what you're doing anyway. 

class UPDATE{
	public $_pdo;
	private $driver;

	public function __construct(){
		$options = [
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // always fetch assoc
			\PDO::ATTR_EMULATE_PREPARES   => true, // reuse tokens in prepared statements
		];
		$this->_pdo = new \PDO( CONFIG['sql'][CONFIG['sql']['use']]['driver'] . ':' . CONFIG['sql'][CONFIG['sql']['use']]['host'] . ';' . CONFIG['sql'][CONFIG['sql']['use']]['database']. ';' . CONFIG['sql'][CONFIG['sql']['use']]['charset'], CONFIG['sql'][CONFIG['sql']['use']]['user'], CONFIG['sql'][CONFIG['sql']['use']]['password'], $options);
		$dbsetup = SQLQUERY::PREPARE('DYNAMICDBSETUP');
		if ($dbsetup) $this->_pdo->exec($dbsetup);
		$this->driver = CONFIG['sql'][CONFIG['sql']['use']]['driver'];
	}

	public function update(){
		foreach (['_2025_12_06'] as $update){
			foreach ($this->{$update}()[$this->driver] as $query){
				if (!$this->backup($query)
					|| SQLQUERY::EXECUTE($this->_pdo, $this->backup($query)[$this->driver][0]) !== false){
					$sql = SQLQUERY::EXECUTE($this->_pdo, $query);
					if (!$sql || !isset($sql[1]))
						echo '<br />[*] This update has no errorInfo and was successful or without side effects:<br /><code>' . $query . '</code><br />';
					else
						echo '<br />[X] This update likely has failed with errorInfo ' . json_encode($sql) . ':<br /><code>' . $query . '</code><br />';
				}
				else {
					echo '<br />[!] This update has been skipped because the database backup failed. Aborting further updates. Please contact the developer:<br /><code>' . $query . '</code><br />';
					die();
				}
			}
		}
	}

	private function backup($query){
		preg_match("/ALTER TABLE (\w+)|DROP TABLE (\w+)/m", $query, $table);
		if (!isset($table[1]) || !$table[1]) return false;
		return [
			'mysql' => [
				"DROP TABLE IF EXISTS BACKUP_" . $table[1]. "; CREATE TABLE BACKUP_" . $table[1]. " LIKE " . $table[1]. "; INSERT INTO BACKUP_" . $table[1]. " SELECT * FROM " . $table[1] . ";"
			],
			'sqlsrv' => [
				"IF OBJECT_ID(N'dbo.BACKUP_" . $table[1]. "', N'U') IS NOT NULL DROP TABLE BACKUP_" . $table[1]. "; SELECT * INTO BACKUP_" . $table[1]. " FROM " . $table[1] . ";"
			]
		];
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

	private function _2025_06_13(){
		return [
			'mysql' => [
				"ALTER TABLE caro_calendar ADD COLUMN IF NOT EXISTS autodelete tinyint NULL DEFAULT NULL; " .
				"ALTER TABLE caro_documents ADD COLUMN IF NOT EXISTS patient_access tinyint NULL DEFAULT NULL;"
			],
			'sqlsrv' => [
				"IF COL_LENGTH('caro_calendar', 'autodelete') IS NULL" .
				" BEGIN" .
				"    ALTER TABLE caro_calendar" .
				"    ADD autodelete TINYINT NULL DEFAULT NULL" .
				" END; " .
				"IF COL_LENGTH('caro_documents', 'patient_access') IS NULL" .
				" BEGIN" .
				"    ALTER TABLE caro_documents" .
				"    ADD patient_access TINYINT NULL DEFAULT NULL" .
				" END; "
			]
		];
	}
	
	private function _2025_07_02(){
		return [
			'mysql' => [
				"CREATE TABLE IF NOT EXISTS `caro_announcements` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`author_id` int NOT NULL," .
				"	`date` datetime NOT NULL," .
				"	`organizational_unit` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`span_start` datetime NULL DEFAULT NULL," .
				"	`span_end` datetime NULL DEFAULT NULL," .
				"	`subject` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`text` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
			],
			'sqlsrv' => [
				"IF OBJECT_ID(N'caro_announcements', N'U') IS NULL " .
				"CREATE TABLE caro_announcements (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	author_id int NOT NULL," .
				"	date smalldatetime NOT NULL," .
				"	organizational_unit varchar(MAX) NULL DEFAULT NULL," .
				"	span_start smalldatetime NULL DEFAULT NULL," .
				"	span_end smalldatetime NULL DEFAULT NULL," .
				"	subject varchar(MAX) NOT NULL," .
				"	text varchar(MAX) NULL DEFAULT NULL" .
				");"
			]
		];
	}

	private function _2025_07_19(){
		return [
			'mysql' => [
				"ALTER TABLE caro_records ADD COLUMN IF NOT EXISTS lifespan INT NULL DEFAULT NULL; "
			],
			'sqlsrv' => [
				"IF COL_LENGTH('caro_records', 'lifespan') IS NULL" .
				" BEGIN" .
				"    ALTER TABLE caro_records" .
				"    ADD lifespan INT NULL DEFAULT NULL" .
				" END; "
			]
		];
	}

	private function _2025_07_25(){
		return [
			'mysql' => [
				"ALTER TABLE caro_consumables_vendors DROP COLUMN certificate, DROP COLUMN immutable_fileserver; "
			],
			'sqlsrv' => [
				"ALTER TABLE caro_consumables_vendors DROP COLUMN certificate, immutable_fileserver; "
			]
		];
	}

	private function _2025_08_09(){
		return [
			'mysql' => [
				"ALTER TABLE caro_consumables_products CHANGE protected has_files TINYINT(1) NULL DEFAULT NULL; ",
				"ALTER TABLE caro_user_responsibility DROP COLUMN hidden; "
			],
			'sqlsrv' => [
				"EXEC sp_rename 'dbo.caro_consumables_products.protected', 'has_files', 'COLUMN'; ",
				"ALTER TABLE caro_user_responsibility DROP COLUMN hidden; "
			]
		];
	}

	private function _2025_08_15(){
		return [
			'mysql' => [
				"DROP TABLE caro_file_bundles; ",
				"ALTER TABLE caro_records ADD COLUMN IF NOT EXISTS erp_case_number tinytext COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL; "
			],
			'sqlsrv' => [
				"DROP TABLE caro_file_bundles; ",
				"IF COL_LENGTH('caro_records', 'erp_case_number') IS NULL" .
				" BEGIN" .
				"    ALTER TABLE caro_records" .
				"    ADD erp_case_number varchar(255) NULL DEFAULT NULL" .
				" END; "
			]
		];
	}

	private function _2025_09_27(){
		return [
			'mysql' => [
				"UPDATE caro_calendar SET type = 'tasks' WHERE type = 'schedule'; ",
				"ALTER TABLE caro_records ADD COLUMN IF NOT EXISTS note text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL; ",
				"UPDATE caro_user SET image = 'media/favicon/icon192.png' where id = 1; "
			],
			'sqlsrv' => [
				"UPDATE caro_calendar SET type = 'tasks' WHERE type = 'schedule'; ",
				"IF COL_LENGTH('caro_records', 'note') IS NULL" .
				" BEGIN" .
				"    ALTER TABLE caro_records" .
				"    ADD note varchar(MAX) NULL DEFAULT NULL" .
				" END; ",
				"UPDATE caro_user SET image = 'media/favicon/icon192.png' where id = 1; "
			]
		];
	}

	private function _2025_10_10(){
		return [
			'mysql' => [
				"ALTER TABLE `caro_consumables_vendors` CHANGE `pricelist` `products` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL; "
			],
			'sqlsrv' => [
				"BEGIN TRANSACTION " .
				"SET QUOTED_IDENTIFIER ON " .
				"SET ARITHABORT ON " .
				"SET NUMERIC_ROUNDABORT OFF " .
				"SET CONCAT_NULL_YIELDS_NULL ON " .
				"SET ANSI_NULLS ON " .
				"SET ANSI_PADDING ON " .
				"SET ANSI_WARNINGS ON " .
				"COMMIT " .
				"BEGIN TRANSACTION " .
				"GO " .
				"EXECUTE sp_rename N'dbo.caro_consumables_vendors.pricelist', N'Tmp_products_1', 'COLUMN'  " .
				"GO " .
				"EXECUTE sp_rename N'dbo.caro_consumables_vendors.Tmp_products_1', N'products', 'COLUMN'  " .
				"GO " .
				"ALTER TABLE dbo.caro_consumables_vendors SET (LOCK_ESCALATION = TABLE) " .
				"GO " .
				"COMMIT"
			]
		];
	}

	private function _2025_10_12(){
		return [
			'mysql' => [
				"CREATE TABLE IF NOT EXISTS `caro_whiteboard` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`user_id` int NOT NULL," .
				"	`name` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`last_touch` datetime NULL," .
				"	`organizational_unit` text COLLATE utf8mb4_unicode_ci NULL," .
				"	`content` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
			],
			'sqlsrv' => [
				"IF OBJECT_ID(N'dbo.caro_whiteboard', N'U') IS NULL " .
				"CREATE TABLE caro_whiteboard (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	user_id int NOT NULL," .
				"	name varchar(MAX) NOT NULL," .
				"	last_touch smalldatetime NULL," .
				"	organizational_unit varchar(MAX) NULL DEFAULT NULL," .
				"	content varchar(MAX) NULL DEFAULT NULL" .
				");"
			]
		];
	}

	private function _2025_10_15(){
		return [
			'mysql' => [
				"ALTER TABLE caro_consumables_products ADD COLUMN IF NOT EXISTS thirdparty_order tinyint NULL DEFAULT NULL; ",
			],
			'sqlsrv' => [
				"IF COL_LENGTH('caro_consumables_products', 'thirdparty_order') IS NULL" .
				" BEGIN" .
				"    ALTER TABLE caro_consumables_products" .
				"    ADD thirdparty_order tinyint NULL DEFAULT NULL" .
				" END; ",
			]
		];
	}

	private function _2025_12_06(){
		return [
			'mysql' => [
				"ALTER TABLE `caro_consumables_approved_orders` CHANGE `partially_received` `delivered_partially` DATETIME NULL DEFAULT NULL, " .
				"CHANGE `received` `delivered_full` DATETIME NULL DEFAULT NULL, " .
				"CHANGE `partially_delivered` `issued_partially` DATETIME NULL DEFAULT NULL, " .
				"CHANGE `delivered` `issued_full` DATETIME NULL DEFAULT NULL, " .
				"CHANGE `notified_received` `delivered_notified` INT(11) NULL DEFAULT NULL, " .
				"CHANGE `notified_delivered` `issued_notified` INT(11) NULL DEFAULT NULL; ",
				"ALTER TABLE `caro_consumables_order_statistics` CHANGE `partially_received` `delivered_partially` DATETIME NULL DEFAULT NULL, " .
				"CHANGE `received` `delivered_full` DATETIME NULL DEFAULT NULL; "
			],
			'sqlsrv' => [
				"EXEC sp_rename 'caro.dbo.caro_consumables_approved_orders.partially_received', 'delivered_partially', 'COLUMN';",
				"EXEC sp_rename 'caro.dbo.caro_consumables_approved_orders.received', 'delivered_full', 'COLUMN';",
				"EXEC sp_rename 'caro.dbo.caro_consumables_approved_orders.partially_delivered', 'issued_partially', 'COLUMN';",
				"EXEC sp_rename 'caro.dbo.caro_consumables_approved_orders.delivered', 'issued_full', 'COLUMN';",
				"EXEC sp_rename 'caro.dbo.caro_consumables_approved_orders.notified_received', 'delivered_notified', 'COLUMN';",
				"EXEC sp_rename 'caro.dbo.caro_consumables_approved_orders.notified_delivered', 'issued_notified', 'COLUMN';",
				"EXEC sp_rename 'caro.dbo.caro_consumables_order_statistics.partially_received', 'delivered_partially', 'COLUMN';",
				"EXEC sp_rename 'caro.dbo.caro_consumables_order_statistics.received', 'delivered_full', 'COLUMN';",
			]
		];
	}

}

$db = new UPDATE();
$db->update();
?>
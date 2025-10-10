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
require_once('_config.php');
@define ('REQUEST', explode("/", substr(mb_convert_encoding($_SERVER['PATH_INFO'], 'UTF-8', mb_detect_encoding($_SERVER['PATH_INFO'], ['ASCII', 'UTF-8', 'ISO-8859-1'])), 1)));
require_once('_utility.php'); // general utilities
require_once('_sqlinterface.php');
require_once('_language.php');

define('DEFAULTSQL', [
	'install_tables' => [
		'mysql' => "CREATE TABLE IF NOT EXISTS `caro_announcements` (" .
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
				.
				"CREATE TABLE IF NOT EXISTS `caro_audit_and_management` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`template` int NULL DEFAULT NULL," .
				"	`unit` tinytext COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`content` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`last_touch` datetime NOT NULL," .
				"	`last_user` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`closed` int NULL DEFAULT NULL," .
				"	`notified` int NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_audit_templates` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`content` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`objectives` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`unit` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`date` datetime NOT NULL," .
				"	`author` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`hint` tinytext COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`method` tinytext COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_calendar` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`type` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`span_start` datetime NOT NULL," .
				"	`span_end` datetime NOT NULL," .
				"	`author_id` int NOT NULL," .
				"	`affected_user_id` int NULL DEFAULT NULL," .
				"	`organizational_unit` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`subject` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`misc` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`closed` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`alert` tinyint NULL," .
				"	`autodelete` tinyint NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_consumables_approved_orders` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`order_data` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`organizational_unit` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`approval` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`approved` datetime NOT NULL," .
				"	`ordered` datetime NULL DEFAULT NULL," .
				"	`partially_received` datetime NULL DEFAULT NULL," .
				"	`received` datetime NULL DEFAULT NULL," .
				"	`partially_delivered` datetime NULL DEFAULT NULL," .
				"	`delivered` datetime NULL DEFAULT NULL," .
				"	`archived` datetime NULL DEFAULT NULL," .
				"	`ordertype` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`notified_received` int NULL DEFAULT NULL," .
				"	`notified_delivered` int NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
				.
				"CREATE TABLE IF NOT EXISTS `caro_consumables_order_statistics` (" .
				"	`order_id` int NOT NULL," .
				"	`order_data` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`ordered` datetime NULL DEFAULT NULL," .
				"	`partially_received` datetime NULL DEFAULT NULL," .
				"	`received` datetime NULL DEFAULT NULL," .
				"	`ordertype` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	PRIMARY KEY (`order_id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_consumables_prepared_orders` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`order_data` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_consumables_products` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`vendor_id` int NOT NULL," .
				"	`article_no` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`article_name` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`article_alias` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`article_unit` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`article_ean` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`article_info` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`hidden` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`has_files` tinyint NULL DEFAULT NULL," .
				"	`trading_good` tinyint NULL DEFAULT NULL," .
				"	`checked` datetime NULL DEFAULT NULL," .
				"	`sample_checks` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`incorporated` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`has_expiry_date` tinyint NULL DEFAULT NULL," .
				"	`special_attention` tinyint NULL DEFAULT NULL," .
				"	`last_order` datetime NULL DEFAULT NULL," .
				"	`stock_item` tinyint NULL DEFAULT NULL," .
				"	`erp_id` tinytext NULL DEFAULT NULL," .
				"	`document_reminder` int NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_consumables_vendors` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`hidden` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`name` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`info` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`products` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`evaluation` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
				.
				"CREATE TABLE IF NOT EXISTS `caro_csvfilter` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`name` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`date` datetime NOT NULL," .
				"	`author` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`content` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"   `hidden` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_file_external_documents` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`path` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`author` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`regulatory_context` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`activated` datetime NULL DEFAULT NULL," .
				"	`retired` datetime NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				"  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_documents` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`name` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`alias` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`context` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`unit` tinytext COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`date` datetime NOT NULL," .
				"	`author` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`content` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`hidden` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`approval` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`regulatory_context` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`permitted_export` tinyint NULL DEFAULT NULL," .
				"	`restricted_access` text COLLATE utf8mb4_unicode_ci NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_manual` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`title` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`content` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`permissions` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_measures` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`timestamp` datetime NOT NULL," .
				"	`content` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`user_id` int NULL," .
				"	`votes` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`measures` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`last_user` tinytext COLLATE utf8mb4_unicode_ci NULL," .
				"	`last_touch` datetime NULL," .
				"	`closed` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_messages` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`user_id` int NOT NULL," .
				"	`conversation_user` int NOT NULL," .
				"	`sender` int NOT NULL," .
				"	`message` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`timestamp` datetime NOT NULL," .
				"	`notified` tinyint NULL DEFAULT NULL," .
				"	`seen` tinyint NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_records` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`context` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`case_state` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`record_type` tinytext COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`identifier` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`last_user` int NOT NULL," .
				"	`last_touch` datetime NOT NULL," .
				"	`last_document` text COLLATE utf8mb4_unicode_ci NULL," .
				"	`content` longtext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`closed` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`notified` int NULL DEFAULT NULL," .
				"	`lifespan` int NULL DEFAULT NULL," .
				"	`erp_case_number` tinytext COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`note` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_records_datalist` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`issue` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`unit` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`datalist` longtext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_risks` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`type` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`process` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`risk` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`relevance` tinyint NULL," .
				"	`cause` text COLLATE utf8mb4_unicode_ci NULL," .
				"	`effect` text COLLATE utf8mb4_unicode_ci NULL," .
				"	`probability` int NULL," .
				"	`damage` int NULL," .
				"	`measure` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`measure_probability` int NULL," .
				"	`measure_damage` int NULL," .
				"	`risk_benefit` text COLLATE utf8mb4_unicode_ci NULL," .
				"	`measure_remainder` text COLLATE utf8mb4_unicode_ci NULL," .
				"	`proof` text COLLATE utf8mb4_unicode_ci NULL," .
				"	`date` datetime NOT NULL," .
				"	`author` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`hidden` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_texttemplates` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`name` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`unit` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`date` datetime NOT NULL," .
				"	`author` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`content` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`type` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"   `hidden` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_sessions` (" .
				"	`id` text(1024) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE," .
				"	`user_id` int NOT NULL," .
				"	`date` datetime NOT NULL" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				.
				"CREATE TABLE IF NOT EXISTS `caro_user` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`name` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`permissions` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`units` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`token` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`orderauth` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`image` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`app_settings` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`skills` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
				.
				"CREATE TABLE IF NOT EXISTS `caro_user_responsibility` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`user_id` int NOT NULL," .
				"	`units` text COLLATE utf8mb4_unicode_ci NULL," .
				"	`assigned_users` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`proxy_users` text COLLATE utf8mb4_unicode_ci NULL," .
				"	`span_start` datetime NOT NULL," .
				"	`span_end` datetime NOT NULL," .
				"	`responsibility` text COLLATE utf8mb4_unicode_ci NULL," .
				"	`description` text COLLATE utf8mb4_unicode_ci NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
				.
				"CREATE TABLE IF NOT EXISTS `caro_user_training` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`user_id` int NOT NULL," .
				"	`name` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`date` date NULL," .
				"	`expires` date NULL," .
				"	`experience_points` int NULL," .
				"	`file_path` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`evaluation` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`planned` tinytext COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

		'sqlsrv' => "IF OBJECT_ID(N'caro_announcements', N'U') IS NULL " .
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
				.
				"IF OBJECT_ID(N'caro_audit_and_management', N'U') IS NULL " .
				"CREATE TABLE caro_audit_and_management (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	template int NULL DEFAULT NULL," .
				"	unit varchar(255) NULL DEFAULT NULL," .
				"	content varchar(MAX) NOT NULL," .
				"	last_touch smalldatetime NOT NULL," .
				"	last_user varchar(255) NOT NULL," .
				"	closed tinyint NULL DEFAULT NULL," .
				"	notified int NULL DEFAULT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_audit_templates', N'U') IS NULL " .
				"CREATE TABLE caro_audit_templates (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	content varchar(MAX) NOT NULL," .
				"	objectives varchar(MAX) NOT NULL," .
				"	unit varchar(255) NOT NULL," .
				"	date smalldatetime NOT NULL," .
				"	author varchar(MAX) NULL DEFAULT NULL," .
				"	hint varchar(255) NULL DEFAULT NULL," .
				"	method varchar(255) NULL DEFAULT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_calendar', N'U') IS NULL " .
				"CREATE TABLE caro_calendar (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	type varchar(255) NOT NULL," .
				"	span_start smalldatetime NOT NULL," .
				"	span_end smalldatetime NOT NULL," .
				"	author_id int NOT NULL," .
				"	affected_user_id int NULL DEFAULT NULL," .
				"	organizational_unit varchar(MAX) NULL DEFAULT NULL," .
				"	subject varchar(MAX) NULL DEFAULT NULL," .
				"	misc varchar(MAX) NULL DEFAULT NULL," .
				"	closed varchar(MAX) NULL DEFAULT NULL," .
				"	alert tinyint NULL," .
				"	autodelete tinyint NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_consumables_approved_orders', N'U') IS NULL " .
				"CREATE TABLE caro_consumables_approved_orders (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	order_data varchar(MAX) NOT NULL," .
				"	organizational_unit varchar(255) NOT NULL," .
				"	approval varchar(MAX) NOT NULL," .
				"	approved smalldatetime NOT NULL," .
				"	ordered smalldatetime NULL DEFAULT NULL," .
				"	partially_received smalldatetime NULL DEFAULT NULL," .
				"	received smalldatetime NULL DEFAULT NULL," .
				"	partially_delivered smalldatetime NULL DEFAULT NULL," .
				"	delivered smalldatetime NULL DEFAULT NULL," .
				"	archived smalldatetime NULL DEFAULT NULL," .
				"	ordertype varchar(255) NOT NULL," .
				"	notified_received int NULL DEFAULT NULL," .
				"	notified_delivered int NULL DEFAULT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_consumables_order_statistics', N'U') IS NULL " .
				"CREATE TABLE caro_consumables_order_statistics (" .
				"	id int NOT NULL IDENTITY PRIMARY KEY," .
				"	order_id int NOT NULL," .
				"	order_data varchar(MAX) NOT NULL," .
				"	ordered smalldatetime NULL DEFAULT NULL," .
				"	partially_received smalldatetime NULL DEFAULT NULL," .
				"	received smalldatetime NULL DEFAULT NULL," .
				"	ordertype varchar(255) NOT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_consumables_prepared_orders', N'U') IS NULL " .
				"CREATE TABLE caro_consumables_prepared_orders (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	order_data varchar(MAX) NOT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_consumables_products', N'U') IS NULL " .
				"CREATE TABLE caro_consumables_products (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	vendor_id int NOT NULL," .
				"	article_no varchar(MAX) NULL DEFAULT NULL," .
				"	article_name varchar(MAX) NULL DEFAULT NULL," .
				"	article_alias varchar(255) NULL DEFAULT NULL," .
				"	article_unit varchar(255) NULL DEFAULT NULL," .
				"	article_ean varchar(255) NULL DEFAULT NULL," .
				"	article_info varchar(MAX) NULL DEFAULT NULL," .
				"	hidden varchar(MAX) NULL DEFAULT NULL," .
				"	has_files tinyint NULL DEFAULT NULL," .
				"	trading_good tinyint NULL DEFAULT NULL," .
				"	checked smalldatetime NULL DEFAULT NULL," .
				"	sample_checks varchar(MAX) NULL DEFAULT NULL," .
				"	incorporated varchar(MAX) NULL DEFAULT NULL," .
				"	has_expiry_date tinyint NULL DEFAULT NULL," .
				"	special_attention tinyint NULL DEFAULT NULL," .
				"	last_order smalldatetime NULL DEFAULT NULL," .
				"	stock_item tinyint NULL DEFAULT NULL," .
				"	erp_id varchar(255) NULL DEFAULT NULL," .
				"	document_reminder int NULL DEFAULT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_consumables_vendors', N'U') IS NULL " .
				"CREATE TABLE caro_consumables_vendors (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	hidden varchar(MAX) NULL DEFAULT NULL," .
				"	name varchar(MAX) NOT NULL," .
				"	info varchar(MAX) NULL DEFAULT NULL," .
				"	products varchar(MAX) NULL DEFAULT NULL," .
				"	evaluation varchar(MAX) NULL DEFAULT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_csvfilter', N'U') IS NULL " .
				"CREATE TABLE caro_csvfilter (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	name varchar(255) NOT NULL," .
				"	date smalldatetime NOT NULL," .
				"	author varchar(MAX) NOT NULL," .
				"	content varchar(MAX) NOT NULL," .
				"	hidden varchar(MAX) NULL DEFAULT NULL" .
				");" 
				.
				"IF OBJECT_ID(N'caro_file_external_documents', N'U') IS NULL " .
				"CREATE TABLE caro_file_external_documents (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	path varchar(MAX) NOT NULL," .
				"	author varchar(MAX) NOT NULL," .
				"	regulatory_context varchar(MAX) NOT NULL," .
				"	activated smalldatetime NULL DEFAULT NULL" .
				"	retired smalldatetime NULL DEFAULT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_documents', N'U') IS NULL " .
				"CREATE TABLE caro_documents (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	name varchar(MAX) NOT NULL," .
				"	alias varchar(MAX) NOT NULL," .
				"	context varchar(255) NOT NULL," .
				"	unit varchar(255) NULL DEFAULT NULL," .
				"	date smalldatetime NOT NULL," .
				"	author varchar(MAX) NOT NULL," .
				"	content varchar(MAX) NOT NULL," .
				"	hidden varchar(MAX) NULL DEFAULT NULL," .
				"	approval varchar(MAX) NULL DEFAULT NULL," .
				"	regulatory_context varchar(MAX) NULL DEFAULT NULL," .
				"	permitted_export tinyint NULL DEFAULT NULL," .
				"	restricted_access varchar(MAX) NULL DEFAULT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_manual', N'U') IS NULL " .
				"CREATE TABLE caro_manual (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	title varchar(255) NOT NULL," .
				"	content varchar(MAX) NOT NULL," .
				"	permissions varchar(MAX) NOT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_measures', N'U') IS NULL " .
				"CREATE TABLE caro_measures (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	timestamp smalldatetime NOT NULL," .
				"	content varchar(MAX) NOT NULL," .
				"	user_id int NULL," .
				"	votes varchar(MAX) NULL," .
				"	measures varchar(MAX) NULL," .
				"	last_user varchar(255) NULL," .
				"	last_touch smalldatetime NULL," .
				"	closed varchar(MAX) NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_messages', N'U') IS NULL " .
				"CREATE TABLE caro_messages (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	user_id int NOT NULL," .
				"	conversation_user int NOT NULL," .
				"	sender int NOT NULL," .
				"	message varchar(MAX) NOT NULL," .
				"	timestamp smalldatetime NOT NULL," .
				"	notified tinyint NULL DEFAULT NULL," .
				"	seen tinyint NULL DEFAULT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_records', N'U') IS NULL " .
				"CREATE TABLE caro_records (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	context varchar(255) NOT NULL," .
				"	case_state varchar(MAX) NULL DEFAULT NULL," .
				"	record_type varchar(255) NULL DEFAULT NULL," .
				"	identifier varchar(MAX) NOT NULL," .
				"	last_user int NOT NULL," .
				"	last_touch smalldatetime NOT NULL," .
				"	last_document varchar(MAX) NULL," .
				"	content varchar(MAX) NOT NULL," .
				"	closed varchar(MAX) NULL DEFAULT NULL," .
				"	notified int NULL DEFAULT NULL," .
				"	lifespan int NULL DEFAULT NULL," .
				"	erp_case_number varchar(255) NULL DEFAULT NULL," .
				"	note varchar(MAX) NOT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_records_datalist', N'U') IS NULL " .
				"CREATE TABLE caro_records_datalist (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	issue varchar(MAX) NOT NULL," .
				"	unit varchar(255) NOT NULL," .
				"	datalist varchar(MAX) NOT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_risks', N'U') IS NULL " .
				"CREATE TABLE caro_risks (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	type varchar(255) NOT NULL," .
				"	process varchar(MAX) NOT NULL," .
				"	risk varchar(MAX) NOT NULL," .
				"	relevance tinyint NULL," .
				"	cause varchar(MAX) NULL," .
				"	effect varchar(MAX) NULL," .
				"	probability int NULL," .
				"	damage int NULL," .
				"	measure varchar(MAX) NOT NULL," .
				"	measure_probability int NULL," .
				"	measure_damage int NULL," .
				"	risk_benefit varchar(MAX) NULL," .
				"	measure_remainder varchar(MAX) NULL," .
				"	proof varchar(MAX) NULL," .
				"	date smalldatetime NOT NULL," .
				"	author varchar(MAX) NOT NULL," .
				"	hidden varchar(MAX) NULL DEFAULT NULL" .
				");"
				.
				"IF OBJECT_ID(N'caro_sessions', N'U') IS NULL " .
				"CREATE TABLE caro_sessions (" .
				"	id varchar(1024) NOT NULL," .
				"	user_id int NOT NULL," .
				"	date smalldatetime NOT NULL," .
				"   CONSTRAINT AK_id UNIQUE(id)" .
				");" 
				.
				"IF OBJECT_ID(N'caro_texttemplates', N'U') IS NULL " .
				"CREATE TABLE caro_texttemplates (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	name varchar(255) NOT NULL," .
				"	unit varchar(255) NOT NULL," .
				"	date smalldatetime NOT NULL," .
				"	author varchar(MAX) NOT NULL," .
				"	content varchar(MAX) NOT NULL," .
				"	type varchar(255) NOT NULL," .
				"	hidden varchar(MAX) NULL DEFAULT NULL" .
				");" 
				.
				"IF OBJECT_ID(N'dbo.caro_user', N'U') IS NULL " .
				"CREATE TABLE caro_user (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	name varchar(MAX) NOT NULL," .
				"	permissions varchar(MAX) NOT NULL," .
				"	units varchar(MAX) NOT NULL," .
				"	token varchar(MAX) NOT NULL," .
				"	orderauth varchar(255) NOT NULL," .
				"	image varchar(MAX) NOT NULL," .
				"	app_settings varchar(MAX) NOT NULL," .
				"	skills varchar(MAX) NOT NULL" .
				");"
				.
				"IF OBJECT_ID(N'dbo.caro_user_responsibility', N'U') IS NULL " .
				"CREATE TABLE caro_user_responsibility (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	user_id int NOT NULL," .
				"	units varchar(MAX) NULL DEFAULT NULL," .
				"	assigned_users varchar(MAX) NOT NULL," .
				"	proxy_users varchar(MAX) NULL DEFAULT NULL," .
				"	span_start date NOT NULL," .
				"	span_end date NOT NULL," .
				"	responsibility varchar(MAX) NULL DEFAULT NULL," .
				"	description varchar(MAX) NULL DEFAULT NULL" .
				");"
				.
				"IF OBJECT_ID(N'dbo.caro_user_training', N'U') IS NULL " .
				"CREATE TABLE caro_user_training (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	user_id int NOT NULL," .
				"	name varchar(MAX) NOT NULL," .
				"	date date NULL," .
				"	expires date NULL," .
				"	experience_points int NULL," .
				"	file_path varchar(MAX) NULL DEFAULT NULL," .
				"	evaluation varchar(MAX) NULL DEFAULT NULL," .
				"	planned varchar(255) NULL DEFAULT NULL" .
				");"
		],

	]
);

class INSTALL {
	/**
	 * preset database connection
	 */
	public $_pdo;
	
	/**
	 * current date with correct timezone
	 */
	public $_currentdate;

	/**
	 * make languagemodel LANG class and its methods available
	 */
	public $_lang = null;

	public $_payload = [];

	/**
	 * current settings for install
	 */
	public $_defaultUser = CONFIG['system']['caroapp'];
	public $_defaultLanguage = CONFIG['application']['defaultlanguage'];
	public $_pdoDriver = CONFIG['sql']['use'];

	public function __construct(){
		$options = [
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // always fetch assoc
			\PDO::ATTR_EMULATE_PREPARES   => true, // reuse tokens in prepared statements
		];
		$this->_pdo = new \PDO( CONFIG['sql'][$this->_pdoDriver]['driver'] . ':' . CONFIG['sql'][$this->_pdoDriver]['host'] . ';' . CONFIG['sql'][$this->_pdoDriver]['database']. ';' . CONFIG['sql'][$this->_pdoDriver]['charset'], CONFIG['sql'][$this->_pdoDriver]['user'], CONFIG['sql'][$this->_pdoDriver]['password'], $options);
		$dbsetup = SQLQUERY::PREPARE('DYNAMICDBSETUP');
		if ($dbsetup) $this->_pdo->exec($dbsetup);

		$this->_currentdate = new \DateTime('now');

		$this->_lang = new LANG();

		switch($_SERVER['REQUEST_METHOD']){
			case "GET":
			case "DELETE":
				$inputstream = $_SERVER['QUERY_STRING'];
				break;
			case "POST":
			case "PUT":
				$inputstream = file_get_contents('php://input');
				break;
		}
		$inputstream = preg_replace_callback(
			'/(^|(?<=&))[^=[&]+/',
			function($key) { return bin2hex(urldecode($key[0])); },
			$inputstream
		);
		parse_str($inputstream, $post);
		foreach ($post as $key => $val) {
			$this->_payload[hex2bin($key)] = $val;
		}
		
	}

	/**
	 * display install navigation
	 */
	public function navigation($method){
		if (method_exists($this, $method)) {
			echo '<a href="' . (isset(REQUEST[1]) ? '../': '') . '../_install.php">back</a><br />';

			if ($method !== 'installDatabase' && (!isset($_SESSION['user']) || !array_intersect(['admin'], $_SESSION['user']['permissions']))){
				$this->printError('You have to be logged in with administrator privilege to run this. <a href="../../index.html" target="_blank">Open Caro App in new window</a>');
				die();
			}

			$this->{$method}();
		}
		else {
			foreach (get_class_vars(get_class($this)) as $varName => $varValue){
				if (in_array(gettype($varValue), ['string', 'integer', 'boolean']))
					echo gettype($varValue) . ': ' . $varName . ': ' . $varValue . '<br />';
			}
			echo '<br />Please adhere to the manual for initial database setup, request _install.php/installDatabase/*your_selected_installation_password*<br />';
			echo 'You must be logged in with administrator privileges to do anything beside database installation.<br /><br />';
			$methods = get_class_methods($this);
			sort($methods);
			foreach ($methods as $methodName){
				if (!in_array($methodName, [
					'__construct',
					'navigation',
					'defaultPic',
					'executeSQL',
					'importJSON',
					'printError',
					'printSuccess',
					'printWarning'
					])) echo '<a href="./_install.php/' . $methodName . '">' . $methodName . '</a><br /><br />';
			}
			echo '<br /><a href="../index.html">exit</a>';
		}
	}

	/**
	 * create a default user profile picture from initials
	 * duplicate from user.php
	 * @param string $name username
	 * 
	 * @return string image data
	 */
	public function defaultPic($name){
		$names = explode(' ', $name);
		$initials = strtoupper(substr($names[0], 0, 1));
		if (count($names) >1) $initials .= strtoupper(substr($names[count($names) - 1], 0, 1));

		$image = imagecreatetruecolor(256, 256);
		$font_size = round(256 / 2);
		$y = round(256 / 2 + $font_size / 2.4);
		$x= round(256 / 2 - $font_size *.33 * strlen($initials));
		$background_color = imagecolorallocate($image, 163, 190, 140); // nord green
		imagefill($image, 0, 0, $background_color);
		$text_color = imagecolorallocate($image, 46, 52, 64); // nord dark
		imagefttext($image, $font_size, 0, $x, $y, $text_color, '../media/UbuntuMono-R.ttf', $initials);
		ob_start();
		imagepng($image);
		$image = ob_get_contents();
		ob_end_clean();
		return $image;
	}

	/**
	 * unifies display of errors
	 * @param string $message to display
	 * @param string|array $item entry item from source
	 */
	public function printError($message = '', $item = []){
		echo '<br />[X] ' . ($message ? : '') . ($item ? '<br /><code>' . (gettype($item) === 'array' ? UTILITY::json_encode($item) : $item) . '</code>' : '') . '<br />';
	}

	/**
	 * unifies display of success
	 * @param string $message to display
	 * @param string|array $item entry item from source
	 */
	public function printSuccess($message = '', $item = []){
		echo '<br />[*] ' . ($message ? : '') . ($item ? '<br /><code>' . (gettype($item) === 'array' ? UTILITY::json_encode($item) : $item) . '</code>' : '') . '<br />';
	}

	/**
	 * unifies display of warnings
	 * @param string $message to display
	 * @param string|array $item entry item from source
	 */
	public function printWarning($message = '', $item = []){
		echo '<br />[!] ' . ($message ? : '') . ($item ? '<br /><code>' . (gettype($item) === 'array' ? UTILITY::json_encode($item) : $item) . '</code>' : '') . '<br />';
	}

	/**
	 * execute sql chunks, return success or display exception
	 * @param array $sqlchunks
	 * @return int
	 */
	public function executeSQL($sqlchunks){
		$counter = 0;
		foreach ($sqlchunks as $chunk){
			try {
				if (SQLQUERY::EXECUTE($this->_pdo, $chunk)) {
					$this->printSuccess('Success:', $chunk);
					$counter++;
				}
			}
			catch (\Exception $e) {
				$this->printError($e, $chunk);
				die();
			}
		}
		echo "<br />";
		return $counter;
	}

	/**
	 * imports a json file, overrun with env if applicable, returns array or message if not found or defective
	 * @param string $path filepath with trailing \ for template files
	 * @param string $type template type of
	 * * audits
	 * * csvfilter
	 * * documents
	 * * manuals
	 * * risks
	 * * texts
	 * * users
	 * * vendors
	 * @param bool $defaultLanguage true by default, false to ignore language templates
	 * @return array parsed json
	 */
	public function importJSON($path, $type, $defaultLanguage = true){
		$lookup = $path . '*' . $type . ($defaultLanguage ? '.' . $this->_defaultLanguage : '') . '.*';
		$files = [];
		foreach (glob($lookup) as $file){
			$pinfo = pathinfo($file);
			$files[] = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pinfo['dirname'] . '/' . $pinfo['filename']);
		}
		$files = array_unique($files);
		$data = [];
		foreach ($files as $filename){
			$jsonpath = realpath($filename . '.json');
			$envpath = realpath($filename . '.env');
			$json = $env = [];
			if ($jsonpath){
				$json = file_get_contents($jsonpath);
				$json = json_decode($json, true);
				if (!$json)	{
					$this->printError($filename . '.json is defective and could not be properly parsed.');
					$json = [];
				}
			}
			if ($envpath){
				$env = file_get_contents($envpath);
				$env = json_decode($env, true);
				if (!$env) {
					$this->printError($filename . '.env is defective and could not be properly parsed.');
					$env = [];
				}
			}
			if (!$json && !$env) continue;
			$data = array_unique([...$data, ...$json, ...$env], SORT_REGULAR);
		}
		return $data;
	}

	/**
	 * installs tables and default user if not already present
	 */
	public function installDatabase(){
		//secure fileserver by default
		UTILITY::secureDirectory('../fileserver');

		try {
			// if table is not found this will lead to an exception
			$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
				'replacements' => [
					':id' => 1,
					':name' => $this->_defaultUser
				]
			]);
			$this->printWarning('Databases already installed.');
		}
		catch (\Exception $e){
			if (!$statement = $this->_pdo->query(DEFAULTSQL['install_tables'][$this->_pdoDriver])){
				$this->printError('There has been an error installing the databases!');
				die();
			}
			$statement->closeCursor();
			$this->printSuccess('Databases installed.');

			if (REQUEST[1] && SQLQUERY::EXECUTE($this->_pdo, 'user_post', [
				'values' => [
					':name' => $this->_defaultUser,
					':permissions' => 'admin',
					':units' => '',
					':token' => REQUEST[1],
					':orderauth' => '',
					':image' => 'media/favicon/icon192.png',
					':app_settings' => '',
					':skills' => ''
				]
			])) $this->printSuccess('Default user has been installed.');
			else $this->printError('There has been an error inserting the default user! Did you provide an initial custom login token by requesting _install.php/installDatabase/*your_selected_installation_password*?');
		}
	}

	/**
	 * installs audit templates
	 */
	public function installAudittemplates(){
		$json = $this->importJSON('../templates/', 'audits');
		// gather possibly existing entries
		$DBall = [
			...SQLQUERY::EXECUTE($this->_pdo, 'audit_get_templates'),
		];

		$insertions = [];
		foreach ($json as $entry){
			if (!(
				isset($entry['unit']) && $entry['unit'] &&
				isset($entry['objectives']) && $entry['objectives'] &&
				isset($entry['content']) && $entry['content'] &&
				isset($entry['method']) && $entry['method']
				)){
				$this->printError('The following dataset is invalid and will be skipped:', $entry);
				continue;
			}

			$entry['hint'] = isset($entry['hint']) ? $entry['hint'] : null;
			// keep only by current unit
			$similar = array_filter($DBall, fn($audit) => $audit['unit'] === $entry['unit']);
			// keep only by current objectives
			$similar = array_filter($similar, fn($audit) => $audit['objectives'] === $entry['objectives']);
			// keep only by current method
			$similar = array_filter($similar, fn($audit) => $audit['method'] === $entry['method']);
			// keep only by current hints, compare boolval for empty and null values
			$similar = array_filter($similar, fn($audit) => ($audit['hint'] === $entry['hint'] || boolval($audit['hint']) === boolval($entry['hint'])));
			// proceed only if NO similar items remain
			if (!$similar) {
				foreach ($entry['content'] as $key => &$question){
					// filter empty sets
					if (!$question['question']){
						unset ($entry['content'][$key]);
						continue;
					}
					// ensure proper formatting
					if (isset($question['regulatory']) && $question['regulatory']) $question['regulatory'] = implode(',', preg_split('/[^\w\d]+/m', $question['regulatory'] ? : ''));
				}
				if (!array_filter($entry['content'], fn($q) => boolval($q)) || ($entry['content'] = UTILITY::json_encode($entry['content'])) === false){
					$this->printError('A question set is malformed. This item will be skipped:', $entry);
					continue;
				}

				$insertions[] = [
					':content' => $entry['content'],
					':objectives' => $entry['objectives'],
					':unit' => $entry['unit'],
					':author' => isset($entry['author']) ? $entry['author'] : $this->_defaultUser,
					':hint' => $entry['hint'] ? : null,
					':method' => $entry['method']
				];
			}
		}
		if ($this->executeSQL(SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('audit_post_template'), $insertions)))
			$this->printSuccess('Novel entries by unit and objectives from audits have been installed.');
		else $this->printWarning('There were no novelties to install from audits ressources.');
	}
	
	/**
	 * installs csv filter
	 */
	public function installCSVFilter(){
		$json = $this->importJSON('../templates/', 'csvfilter');
		// gather possibly existing entries
		$DBall = [
			...SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_datalist'),
		];

		$insertions = [];
		foreach ($json as $entry){
			// filters are only transferred if the name is not already taken
			if (!(
				isset($entry['name']) && $entry['name'] &&
				isset($entry['content']) && $entry['content'] && UTILITY::json_encode($entry['content'], JSON_PRETTY_PRINT)
				)){
				$this->printError('The following dataset is invalid and will be skipped:', $entry);
				continue;
			}
			$entry['content'] = UTILITY::json_encode($entry['content'], JSON_PRETTY_PRINT);
			if ((isset($entry['name']) && $entry['name'] && !in_array($entry['name'], array_column($DBall, 'name'))) &&
				!in_array($entry['content'], array_column($DBall, 'content'))
			) {

				$insertions[] = [
					':name' => $entry['name'],
					':author' => isset($entry['author']) ? $entry['author'] : $this->_defaultUser,
					':content' => $entry['content'],
					':hidden' => null,
				];
			}
		}
		if ($this->executeSQL(SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('csvfilter_post'), $insertions)))
			$this->printSuccess('Novel entries by name and content from csv-filter have been installed.');
		else $this->printWarning('There were no novelties to install from csv-filter ressources.');
	}
	
	/**
	 * installs documents by novel name
	 */
	public function installDocuments(){
		$json = $this->importJSON('../templates/', 'documents');
		// gather possibly existing entries
		$DBall = [
			...SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist'),
			...SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist'),
			...SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist')
		];

		/**
		 * recursively verify input names for not being forbidden
		 * @param array $elements
		 * @return bool
		 * 
		 * also see frontend compose_helper.importComponent()
		 */
		function containsForbidden($elements) {
			$forbidden = false;
			foreach ($elements as $element) {
				if (array_is_list($element)) {
					$forbidden = containsForbidden($element);
				} else {					
					if (isset($element['type']) && $element['type'] !== 'textsection'
						&& isset($element['attributes']) && isset($element['attributes']['name'])){
						if ($pattern = UTILITY::forbiddenName($element['attributes']['name'])) {
							$forbidden = ['name' => $element['attributes']['name'], 'pattern' => $pattern];
						}
						if ($forbidden) break;
					}
				}
			}
			return $forbidden;
		}
		
		$name_context = array_map(fn($doc) => $doc['name'] . '_' . $doc['context'], $DBall);

		$insertions = [];
		foreach ($json as $entry){
			// documents are only transferred if the name is not already taken for the context
			if (!(
				isset($entry['name']) && $entry['name'] &&
				isset($entry['context']) && $entry['context'] &&
				isset($entry['unit']) && $entry['unit'] &&
				isset($entry['content']) && $entry['content']
				)){
				$this->printError('The following dataset is invalid and will be skipped:', $entry);
				continue;
			}

			if (in_array($entry['name'] . '_' . $entry['context'], $name_context)){
				$this->printError('Multiple occurences of the name ' . $entry['name'] . ' are not allowed for context ' . $entry['context'] . '. This item may be already installed and in use and will be skipped:', $entry);
				continue;
			}

			if($pattern = UTILITY::forbiddenName($entry['name'])){
				$this->printError('The name ' . $entry['name'] . ' is not allowed by matching ' . $pattern . '. This item will be skipped:', $entry);
				continue;
			}
			if ($entry['context'] === 'component' && $entry['content']){
				if ($forbidden = containsForbidden($entry['content'])){
					$this->printError('The component ' . $entry['name'] . ' contains a forbidden input name: ' . $forbidden['name']. ' is not allowed by matching ' . $forbidden['pattern'] . '. This item will be skipped:', $entry);
					continue;
				}
			}

			// ensure proper formatting
			$entry['regulatory_context'] = implode(',', preg_split('/[^\w\d]+/m', $entry['regulatory_context'] ? : ''));
			$entry['restricted_access'] = implode(',', preg_split('/[^\w\d]+/m', $entry['restricted_access'] ? : ''));

			$name_context[] = $entry['name'] . '_' . $entry['context'];
			$insertions[] = [
				':id' => null,
				':name' => $entry['name'],
				':alias' => isset($entry['alias']) ? $entry['alias'] : '',
				':context' => $entry['context'],
				':unit' => $entry['unit'],
				':author' => isset($entry['author']) ? $entry['author'] : $this->_defaultUser,
				':content' => gettype($entry['content']) === 'array' ? UTILITY::json_encode($entry['content']) : $entry['content'],
				':hidden' => null,
				':approval' => null,
				':regulatory_context' => isset($entry['regulatory_context']) && $entry['regulatory_context'] ? $entry['regulatory_context'] : '',
				':permitted_export' => isset($entry['permitted_export']) && $entry['permitted_export'] ? $entry['permitted_export'] : null,
				':restricted_access' => isset($entry['restricted_access']) && $entry['restricted_access'] ? $entry['restricted_access'] : null,
				':patient_access' => isset($entry['patient_access']) && $entry['patient_access'] ? $entry['patient_access'] : null
			];
		}
		if ($this->executeSQL(SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('document_post'), $insertions)))
			$this->printSuccess('Novel entries by name from documents have been installed.');
		else $this->printWarning('There were no novelties to install from documents ressources.');
	}

	/**
	 * installs manual entries by novel title
	 */
	public function installManual(){
		$json = $this->importJSON('../templates/', 'manuals');
		// gather possibly existing entries
		$DBall = [
			...SQLQUERY::EXECUTE($this->_pdo, 'application_get_manual'),
		];

		$insertions = $names = [];
		foreach ($json as $entry){
			// documents are only transferred if the title is not already taken
			if (!(
				isset($entry['title']) && $entry['title'] &&
				isset($entry['content']) && $entry['content'] &&
				isset($entry['permissions']) && $entry['permissions']
				)){
				$this->printError('The following dataset is invalid and will be skipped:', $entry);
				continue;
			}

			if (isset($entry['title']) && $entry['title'] && !in_array($entry['title'], array_column($DBall, 'title'))) {
				if($pattern = UTILITY::forbiddenName($entry['title'])){
					$this->printError('The title ' . $entry['title'] . ' is not allowed by matching ' . $pattern . '. This item will be skipped:', $entry);
					continue;
				}
				if (in_array($entry['title'], $names)) {
					$this->printError('Multiple occurences of the title are not allowed. This item will be skipped:', $entry);
					continue;
				}

				// ensure proper formatting
				$entry['permissions'] = implode(',', preg_split('/[^\w\d]+/m', $entry['permissions'] ? : ''));

				$names[] = $entry['title'];
				$insertions[] = [
					':title' => $entry['title'],
					':content' => $entry['content'],
					':permissions' => $entry['permissions']
				];
			}
		}
		if ($this->executeSQL(SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('application_post_manual'), $insertions)))
			$this->printSuccess('Novel entries by name from manuals have been installed.');
		else $this->printWarning('There were no novelties to install from manuals ressources.');
	}

	/**
	 * installs risks by novel process+risk+cause+measure
	 */
	public function installRisks(){
		$json = $this->importJSON('../templates/', 'risks');
		// gather possibly existing entries
		$DBall = [];
		foreach (SQLQUERY::EXECUTE($this->_pdo, 'risk_datalist') as $row){
			$DBall[] = $row['process'].$row['risk'].$row['cause'].$row['measure'];
		}
		$insertions = [];

		foreach ($json as $entry){
			// risks are only transferred if process+risk+cause+measure is not already taken
			$valid = true;

			if (isset($entry['type'])) {
				// validity check, match with risk.php
				switch($entry['type']){
					case 'characteristic': // implement further cases if suitable, according to languagefile
						foreach ($entry as $key => $value){
							if (in_array($key, [
								'effect',
								'probability',
								'damage',
								'measure_probability',
								'measure_damage',
								'risk_benefit',
								'measure_remainder',
								'proof'
								])) continue;
							if (isset($entry['relevance']) && $entry['relevance'] === 0 && in_array($key, [
								'risk',
								'cause'
								])) continue;								
							if (!$value && $value !== 0) {
								$valid = false;
								echo var_dump($value) . ' for ' . $key . ' is not allowed!';
								break;
							}
						}		
						break;
					default: //risk
					foreach ($entry as $key => $value){
						if (in_array($key, [
							'cause',
							'measure_remainder',
							'proof'
							])) continue;
						if (isset($entry['relevance']) && $entry['relevance'] === 0 && in_array($key, [
							'effect',
							'probability',
							'damage',
							'measure',
							'measure_probability',
							'measure_damage',
							'risk_benefit'
						])) continue;								
						if (!$value && $value !== 0) {
							$valid = false;
							echo var_dump($value) . ' for ' . $key . ' is not allowed!';
							break;
						}
					}		
					break;
				}

				if ($valid){
					// ensure proper formatting
					$entry['risk'] = implode(',', preg_split('/[^\w\d]+/m', $entry['risk'] ? : ''));

					if (!in_array($entry['process'].$entry['risk'].$entry['cause'].$entry['measure'], $DBall))
					$insertions[] = [
						':type' => $entry['type'],
						':process' => $entry['process'],
						':risk' => $entry['risk'],
						':relevance' => isset($entry['relevance']) ? $entry['relevance'] : 1,
						':cause' => $entry['cause'],
						':effect' => isset($entry['effect']) ? $entry['effect'] : null,
						':probability' => isset($entry['probability']) ? $entry['probability'] : 4,
						':damage' => isset($entry['damage']) ? $entry['damage'] : 4,
						':measure' => isset($entry['measure']) ? $entry['measure'] : '',
						':measure_probability' => isset($entry['measure_probability']) ? $entry['measure_probability'] : 4,
						':measure_damage' => isset($entry['measure_damage']) ? $entry['measure_damage'] : 4,
						':risk_benefit' => isset($entry['risk_benefit']) ? $entry['risk_benefit'] : null,
						':measure_remainder' => isset($entry['measure_remainder']) ? $entry['measure_remainder'] : null,
						':proof' => null,
						':author' => isset($entry['author']) ? $entry['author'] : $this->_defaultUser
					];
					continue;
				}
			}
			$this->printError('The following dataset is invalid and will be skipped:', $entry);
		}
		if ($this->executeSQL(SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('risk_post'), $insertions)))
			$this->printSuccess('Novel entries by process+risk+cause+measure from risks have been installed.');
		else $this->printWarning('There were no novelties to install from risks ressources.');
	}
	/**
	 * installs texttemplates by novel name
	 */
	public function installTexttemplates(){
		$json = $this->importJSON('../templates/', 'texts');
		// gather possibly existing entries
		$DBall = [
			...SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_datalist'),
		];

		// allowed pattern
		// modify ([^\w\s\d\.\[\]\(\)\-ÄÖÜäöüß])
		// unset types and escaped literals
		$allowed = preg_replace('/\\\./m', '', CONFIG['forbidden']['names']['characters']);
		// readd some types
		$allowed = substr_replace($allowed, '\\w\\d', -2, 0);
		// add multiplier
		$allowed = substr_replace($allowed, '+?', -1, 0);

		$insertions = [];
		$names = [
			'template' => [],
			'text' => [],
			'replacement' => []
		];
		foreach ($json as $entry){
			// documents are only transferred if the name is not already taken
			if (!(
				isset($entry['type']) && $entry['type'] &&
				isset($entry['name']) && $entry['name'] &&
				isset($entry['unit']) && $entry['unit'] &&
				isset($entry['content']) && $entry['content'] &&
				isset($entry['type']) && $entry['type']
				)){
				$this->printError('The following dataset is invalid and will be skipped:', $entry);
				continue;
			}

			if (!in_array($entry['name'], array_column($DBall, 'name'))) {
				if ($pattern = UTILITY::forbiddenName($entry['name'], $entry['type'] === 'template' ? [] : ['characters' => $allowed])){
					$this->printError('The name ' . $entry['name'] . ' is not allowed by matching ' . $pattern . '. This item will be skipped:', $entry);
					continue;
				}
				$used = $entry['type'] === 'template' ? $names['template'] : [...$names['text'], ...$names['replacement']];
				foreach ($used as $name){
					if (str_starts_with($entry['name'], $name) || str_starts_with($name, $entry['name']))
					$this->printError($entry['name'] . ' matches ' . $name . '. Multiple occurences of the name or parts of it for placeholders are not allowed. This item will be skipped:', $entry);
					continue;
				}

				$names[$entry['type']][] = $entry['name'];
				$insertions[] = [
					':name' => $entry['name'],
					':unit' => $entry['unit'],
					':author' => isset($entry['author']) ? $entry['author'] : $this->_defaultUser,
					':content' => gettype($entry['content']) === 'array' ? UTILITY::json_encode($entry['content']) : $entry['content'],
					':type' => $entry['type'],
					':hidden' => null
				];
			}
		}
		if ($this->executeSQL(SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('texttemplate_post'), $insertions)))
			$this->printSuccess('Novel entries by name from textx have been installed.');
		else $this->printWarning('There were no novelties to install from texts ressources.');
	}

	/**
	 * installs users by novel name
	 */
	public function installUsers(){
		$json = $this->importJSON('../templates/', 'users');
		// gather possibly existing entries
		$DBall = [
			...SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist'),
		];

		$insertions = $names = $orderauths = [];
		foreach ($json as $entry){
			// documents are only transferred if the name is not already taken
			if (!(
				isset($entry['name']) && $entry['name']
				)){
				$this->printError('The following dataset is invalid and will be skipped:', $entry);
				continue;
			}
			if (!in_array($entry['name'], array_column($DBall, 'name'))) {
				if($pattern = UTILITY::forbiddenName($entry['name'])){
					$this->printError('The name ' . $entry['name'] . ' is not allowed by matching ' . $pattern . '. This item will be skipped:', $entry);
					continue;
				}
				if (in_array($entry['name'], $names)) {
					$this->printError('Multiple occurences of the name are not allowed. This item will be skipped:', $entry);
					continue;
				}

				// property setting as in user.php->user()
				$entry['orderauth'] = '';
				if (isset($entry['booleans']['orderauth']) && $entry['booleans']['orderauth']){
					foreach ($DBall as $row){
						$orderauths[] = $row['orderauth'];
					}
					do {
						$entry['orderauth'] = random_int(10000, max(99999, count($DBall)*100));
					} while (in_array($entry['orderauth'], $orderauths));
					$orderauths[] = $entry['orderauth'];
				}
				$entry['token'] = hash('sha256', $entry['name'] . random_int(100000,999999) . time());

				$tempPhoto = tmpfile();
				fwrite($tempPhoto, $this->defaultPic($entry['name'])); 
				$_FILES[$this->_lang->PROPERTY('user.take_photo')] = [
					'name' => 'defaultpic.png',
					'type' => 'image/png',
					'tmp_name' => stream_get_meta_data($tempPhoto)['uri']
				];
				$entry['image'] = UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('user.take_photo')], UTILITY::directory('users'), ['profilepic_' . $entry['name']])[0];
				UTILITY::alterImage($entry['image'], CONFIG['limits']['user_image'], UTILITY_IMAGE_REPLACE);
				$entry['image'] = substr($entry['image'], 3);

				// gather timesheet setup
				$entry['app_settings']['annualvacation'] = $this->_currentdate->format('Y-m-d'). '; 30';
				$entry['app_settings']['weeklyhours'] =  $this->_currentdate->format('Y-m-d'). '; 38,5';
				$entry['app_settings']['initialovertime'] = 0;
				
				// ensure proper formatting
				$entry['permissions'] = implode(',', preg_split('/[^\w\d]+/m', $entry['permissions'] ? : ''));
				$entry['units'] = preg_split('/[^\w\d]+/m', $entry['units'] ? : '');
				// set default primary unit if only one has been selected
				if (count($entry['units']) && count($entry['units']) < 2)$user['app_settings']['primaryUnit'] = $entry['units'][0];
				$entry['units'] = implode(',', $entry['units']);

				$names[] = $entry['name'];
				$insertions[] = [
					':name' => $entry['name'],
					':permissions' => $entry['permissions'],
					':units' => $entry['units'],
					':token' => $entry['token'],
					':orderauth' => $entry['orderauth'],
					':image' => $entry['image'],
					':app_settings' => UTILITY::json_encode($entry['app_settings']),
					':skills' => ''
				];
			}
		}
		if ($this->executeSQL(SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('user_post'), $insertions)))
			$this->printSuccess('Novel entries by name from users have been installed.');
		else $this->printWarning('There were no novelties to install from user ressources.');
	}

	/**
	 * installs vendors by novel name
	 */
	public function installVendors(){
		$json = $this->importJSON('../templates/', 'vendors');
		// gather possibly existing entries
		$DBall = [
			...SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist'),
		];

		$insertions = $names = [];
		foreach ($json as $entry){
			// documents are only transferred if the name is not already taken
			if (!(
				isset($entry['name']) && $entry['name']
				)){
				$this->printError('The following dataset is invalid and will be skipped:', $entry);
				continue;
			}
			if (!in_array($entry['name'], array_column($DBall, 'name'))) {
				if($pattern = UTILITY::forbiddenName($entry['name'])){
					$this->printError('The name ' . $entry['name'] . ' is not allowed by matching ' . $pattern . '. This item will be skipped:', $entry);
					continue;
				}
				if (in_array($entry['name'], $names)) {
					$this->printError('Multiple occurences of the name are not allowed. This item will be skipped:', $entry);
					continue;
				}

				$names[] = $entry['name'];

				if (isset($entry['products']) && gettype($entry['products']) === 'array' && isset($entry['products']['filefilter'])){
					$productlistfilter = null;
					$productlistfilter = gettype($entry['products']['filefilter']) === 'string' ? $entry['products']['filefilter'] : UTILITY::json_encode($entry['products']['filefilter'], JSON_PRETTY_PRINT);
					$entry['products']['filefilter'] = $productlistfilter;
				}

				$insertions[] = [
					':id' => null,
					':name' => $entry['name'],
					':info' => isset($entry['info']) && gettype($entry['info']) === 'array' ? UTILITY::json_encode($entry['info']) : null,
					':products' => isset($entry['products']) && gettype($entry['products']) === 'array' ? UTILITY::json_encode($entry['products']) : null,
					':evaluation' => null,
					':hidden' => null
				];
			}
		}
		if ($this->executeSQL(SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('consumables_post_vendor'), $insertions)))
			$this->printSuccess('Novel entries by name for vendors have been installed.');
		else $this->printWarning('There were no novelties to install from vendor ressources.');
	}

	/**
	 * update vendors info and/or productlist properties by name
	 * also see maintenance.php
	 */
	public function updateVendors(){
		$json = $this->importJSON('../templates/', 'vendors');
		// gather possibly existing entries
		$DBall = [
			...SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist'),
		];

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$sqlchunks = [];
				$unfound = [];
				foreach ($json as $entry){
					// documents are only transferred if the name is not already taken
					if (!(
						isset($entry['name']) && $entry['name']
						)){
						$this->printError('The following dataset is invalid and will be skipped:', $entry);
						continue;
					}
					$vendor = array_search($entry['name'], array_column($DBall, 'name'));
					if ($vendor === false) {
						$unfound[] = $entry['name'];
						continue;
					}
					$vendor = $DBall[$vendor];
					// whitespaces and periods replaced with underscore as in request parameters
					$infovar = $vendor['name'] . '_info';
					$filtervar = $vendor['name'] . '_productlistfilter';

					if (!(isset($this->_payload[$infovar]) || isset($this->_payload[$filtervar])))
						continue;

					if (isset($this->_payload[$infovar])) {
						$vendor['info'] = isset($entry['info']) && gettype($entry['info']) === 'array' ? UTILITY::json_encode($entry['info']) : $vendor['info'];
					}
					if (isset($this->_payload[$filtervar])) {
						$vendor['products'] = json_decode($vendor['products'] ? : '', true);

						$newproductlistfilter = (isset($entry['products']) && gettype($entry['products']) === 'array' && isset($entry['products']['filefilter'])) ? UTILITY::json_encode($entry['products']['filefilter'], JSON_PRETTY_PRINT) : null;
						$vendor['products']['filefilter'] = $newproductlistfilter ? : (isset($vendor['products']['filefilter']) ? $vendor['products']['filefilter'] : null);

						$newerpfilter = (isset($entry['products']) && gettype($entry['products']) === 'array' && isset($entry['products']['erpfilter'])) ? UTILITY::json_encode($entry['products']['erpfilter'], JSON_PRETTY_PRINT) : null;
						$vendor['products']['erpfilter'] = $newerpfilter ? : (isset($vendor['products']['erpfilter']) ? $vendor['products']['erpfilter'] : null);

						if(isset($entry['products']['samplecheck_interval']) && $entry['products']['samplecheck_interval']) $vendor['products']['samplecheck_interval'] = $entry['products']['samplecheck_interval'];
						if(isset($entry['products']['samplecheck_reusable']) && $entry['products']['samplecheck_reusable']) $vendor['products']['samplecheck_reusable'] = $entry['products']['samplecheck_reusable'];

						$vendor['products'] = UTILITY::json_encode($vendor['products'], JSON_PRETTY_PRINT);
					}
					echo $this->printSuccess($vendor['name'] . " updated");

					$sqlchunks = SQLQUERY::CHUNKIFY($sqlchunks, strtr(SQLQUERY::PREPARE('consumables_put_vendor'),
					[
						':id' => $vendor['id'],
						':name' => $this->_pdo->quote($vendor['name']),
						':evaluation' => $vendor['evaluation'] ? $this->_pdo->quote($vendor['evaluation']) : 'NULL',
						':hidden' => $vendor['hidden'] ? $this->_pdo->quote($vendor['hidden']) : 'NULL',
						':info' => $vendor['info'] ? $this->_pdo->quote($vendor['info']) : 'NULL',
						':products' => $vendor['products'] ? $this->_pdo->quote($vendor['products']) : 'NULL',
					]) . '; ');
				}
				foreach ($sqlchunks as $chunk){
					try {
						SQLQUERY::EXECUTE($this->_pdo, $chunk);
					}
					catch (\Exception $e) {
						$this->printWarning('there has been an issue', [$e, $chunk]);
						die();
					}
				}
				if ($sqlchunks)	$this->printSuccess('Entries by name for vendors have been updated.');
				if ($unfound) $this->printError('The following vendors from import file have not been found in database:', implode(', ', $unfound));
				else $this->printWarning('There were no updates. Select items to update or check vendor names matching your database entries.');
				break;
			case 'GET':
				echo 'Check what to update by vendor. If template has any value it will override the database, otherwise the original will be kept.<br />Missing a vendor? Most probably names don\'t match...';
				echo '<form method="post">';
				$intersections=array_intersect(array_column($DBall, 'name'), array_column($json, 'name'));
				foreach ($DBall as $vendor){
					if (!in_array($vendor['name'], $intersections)) continue;
					echo '<br /><br />' . $vendor['name'] . ($vendor['hidden'] ? ' (this vendor is marked as hidden)' : '');
					echo '<br /><label><input type="checkbox" name="' . $vendor['name'] . '_info" /> info</label> | ' . '<label><input type="checkbox" name="' . $vendor['name'] . '_productlistfilter" /> productlist filter</label>';
				}
				echo '<br /><br /><input type="submit" value="update selected" />';
				echo '</form>';
				break;
		}
	}
}

// check requesting script as stresstest extends install
if (basename($_SERVER["SCRIPT_FILENAME"]) === basename(__FILE__)){
	$install = new INSTALL();
	$install->navigation(REQUEST[0]);
	exit();
}
?>
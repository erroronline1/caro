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
define('CONFIG', parse_ini_file('setup.ini', true));
define('REQUEST', explode("/", substr(mb_convert_encoding($_SERVER['PATH_INFO'], 'UTF-8', mb_detect_encoding($_SERVER['PATH_INFO'], ['ASCII', 'UTF-8', 'ISO-8859-1'])), 1)));

$pdo = new PDO( CONFIG['sql'][CONFIG['sql']['use']]['driver'] . ':' . CONFIG['sql'][CONFIG['sql']['use']]['host'] . ';' . CONFIG['sql'][CONFIG['sql']['use']]['database']. ';' . CONFIG['sql'][CONFIG['sql']['use']]['charset'], CONFIG['sql'][CONFIG['sql']['use']]['user'], CONFIG['sql'][CONFIG['sql']['use']]['password']);

$queries = [
	'precheck' => [
		'mysql' => "SELECT * FROM caro_manual LIMIT 1",
		'sqlsrv' => "SELECT TOP 1 * FROM caro_manual"
	],
	'install' => [
		'mysql' => [
			'tables' => [
				"CREATE TABLE IF NOT EXISTS `caro_calendar` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`type` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`span_start` datetime NOT NULL," .
				"	`span_end` datetime NOT NULL," .
				"	`author_id` int NOT NULL," .
				"	`affected_user_id` int NULL DEFAULT NULL," .
				"	`organizational_unit` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`subject` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`misc` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`closed` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`alert` tinyint NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				,
				"CREATE TABLE IF NOT EXISTS `caro_checks` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`type` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`date` datetime NOT NULL," .
				"	`author` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`content` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				,
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
				,
				"CREATE TABLE IF NOT EXISTS `caro_consumables_order_statistics` (" .
				"	`order_id` int NOT NULL," .
				"	`order_data` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`ordered` datetime NULL DEFAULT NULL," .
				"	`partially_received` datetime NULL DEFAULT NULL," .
				"	`received` datetime NULL DEFAULT NULL," .
				"	`ordertype` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	PRIMARY KEY (`order_id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				,
				"CREATE TABLE IF NOT EXISTS `caro_consumables_prepared_orders` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`order_data` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				,
				"CREATE TABLE IF NOT EXISTS `caro_consumables_products` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`vendor_id` int NOT NULL," .
				"	`article_no` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`article_name` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`article_alias` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`article_unit` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`article_ean` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`active` tinyint NULL DEFAULT NULL," .
				"	`protected` tinyint NULL DEFAULT NULL," .
				"	`trading_good` tinyint NULL DEFAULT NULL," .
				"	`checked` datetime NULL DEFAULT NULL," .
				"	`incorporated` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`has_expiry_date` tinyint NULL DEFAULT NULL," .
				"	`special_attention` tinyint NULL DEFAULT NULL," .
				"	`last_order` datetime NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				,
				"CREATE TABLE IF NOT EXISTS `caro_consumables_vendors` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`active` tinyint(1) NOT NULL," .
				"	`name` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`info` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`certificate` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`pricelist` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`immutable_fileserver` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`evaluation` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
				,
				"CREATE TABLE IF NOT EXISTS `caro_csvfilter` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`name` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`date` datetime NOT NULL," .
				"	`author` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`content` text COLLATE utf8mb4_unicode_ci NOT NULL," . // not text COLLATE utf8mb4_unicode_ci to avoid messing up any almost comprehensible structure
				"   `hidden` tinyint NOT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				,
				"CREATE TABLE IF NOT EXISTS `caro_file_bundles` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`name` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`date` datetime NOT NULL," .
				"	`author` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`content` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`active` tinyint NOT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				,
				"CREATE TABLE IF NOT EXISTS `caro_file_external_documents` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`path` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`author` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`regulatory_context` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`activated` datetime NULL DEFAULT NULL," .
				"	`retired` datetime NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				"  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				,
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
				,
				"CREATE TABLE IF NOT EXISTS `caro_manual` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`title` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`content` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`permissions` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				,
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
				,
				"CREATE TABLE IF NOT EXISTS `caro_records` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`context` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`case_state` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`record_type` tinytext COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`identifier` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`last_user` int NOT NULL," .
				"	`last_touch` datetime NOT NULL," .
				"	`last_document` text COLLATE utf8mb4_unicode_ci NULL," .
				"	`content` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`closed` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`notified` int NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				,
				"CREATE TABLE IF NOT EXISTS `caro_risks` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`process` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`risk` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`cause` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`effect` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`probability` int NOT NULL," .
				"	`damage` int NOT NULL," .
				"	`measure` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`measure_probability` int NOT NULL," .
				"	`measure_damage` int NOT NULL," .
				"	`risk_benefit` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`measure_remainder` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`last_edit` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				,
				"CREATE TABLE IF NOT EXISTS `caro_texttemplates` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`name` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`unit` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`date` datetime NOT NULL," .
				"	`author` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`content` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`language` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`type` tinytext COLLATE utf8mb4_unicode_ci NOT NULL," .
				"   `hidden` tinyint NOT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
				,
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
				,
				"CREATE TABLE IF NOT EXISTS `caro_user_training` (" .
				"	`id` int NOT NULL AUTO_INCREMENT," .
				"	`user_id` int NOT NULL," .
				"	`name` text COLLATE utf8mb4_unicode_ci NOT NULL," .
				"	`date` date NOT NULL," .
				"	`expires` date NULL," .
				"	`experience_points` int NULL," .
				"	`file_path` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	`evaluation` text COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL," .
				"	PRIMARY KEY (`id`)" .
				") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
				,
				],
			'insertions' => [
				'user' => "INSERT INTO caro_user (id, name, permissions, units, token, orderauth, image, app_settings, skills) VALUES (NULL, '" . CONFIG['system']['caroapp'] . "', 'admin', '', '" . REQUEST[0] . "', '', 'media/favicon/ios/256.png', '', '');",
				'manual' => "INSERT INTO `caro_manual` (`id`, `title`, `content`, `permissions`) VALUES (NULL, ':title', ':content', ':permissions');",
			]
		]
		,
		'sqlsrv' => [
			'tables' => [
				"IF OBJECT_ID(N'caro_calendar', N'U') IS NULL " .
				"CREATE TABLE caro_calendar (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	type varchar(MAX) NOT NULL," .
				"	span_start smalldatetime NOT NULL," .
				"	span_end smalldatetime NOT NULL," .
				"	author_id int NOT NULL," .
				"	affected_user_id int NULL DEFAULT NULL," .
				"	organizational_unit varchar(MAX) NOT NULL," .
				"	subject varchar(MAX) NOT NULL," .
				"	misc varchar(MAX) NOT NULL," .
				"	closed varchar(MAX) NOT NULL," .
				"	alert tinyint NULL" .
				");"
				,
				"IF OBJECT_ID(N'caro_checks', N'U') IS NULL " .
				"CREATE TABLE caro_checks (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	type varchar(MAX) NOT NULL," .
				"	date smalldatetime NOT NULL," .
				"	author varchar(MAX) NOT NULL," .
				"	content varchar(MAX) NOT NULL" .
				");"
				,
				"IF OBJECT_ID(N'caro_consumables_approved_orders', N'U') IS NULL " .
				"CREATE TABLE caro_consumables_approved_orders (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	order_data varchar(MAX) NOT NULL," .
				"	organizational_unit varchar(MAX) NOT NULL," .
				"	approval varchar(MAX) NOT NULL," .
				"	approved smalldatetime NOT NULL," .
				"	ordered smalldatetime NULL DEFAULT NULL," .
				"	partially_received smalldatetime NULL DEFAULT NULL," .
				"	received smalldatetime NULL DEFAULT NULL," .
				"	partially_delivered smalldatetime NULL DEFAULT NULL," .
				"	delivered smalldatetime NULL DEFAULT NULL," .
				"	archived smalldatetime NULL DEFAULT NULL," .
				"	ordertype varchar(MAX) NOT NULL," .
				"	notified_received int NULL DEFAULT NULL," .
				"	notified_delivered int NULL DEFAULT NULL" .
				");"
				,
				"IF OBJECT_ID(N'caro_consumables_order_statistics', N'U') IS NULL " .
				"CREATE TABLE caro_consumables_order_statistics (" .
				"	id int NOT NULL IDENTITY PRIMARY KEY," .
				"	order_id int NOT NULL," .
				"	order_data varchar(MAX) NOT NULL," .
				"	ordered smalldatetime NULL DEFAULT NULL," .
				"	partially_received smalldatetime NULL DEFAULT NULL," .
				"	received smalldatetime NULL DEFAULT NULL," .
				"	ordertype varchar(MAX) NOT NULL" .
				");"
				,
				"IF OBJECT_ID(N'caro_consumables_prepared_orders', N'U') IS NULL " .
				"CREATE TABLE caro_consumables_prepared_orders (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	order_data varchar(MAX) NOT NULL" .
				");"
				,
				"IF OBJECT_ID(N'caro_consumables_products', N'U') IS NULL " .
				"CREATE TABLE caro_consumables_products (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	vendor_id int NOT NULL," .
				"	article_no varchar(MAX) NOT NULL," .
				"	article_name varchar(MAX) NOT NULL," .
				"	article_alias varchar(MAX) NOT NULL," .
				"	article_unit varchar(MAX) NOT NULL," .
				"	article_ean varchar(MAX) NOT NULL," .
				"	active tinyint NULL DEFAULT NULL," .
				"	protected tinyint NULL DEFAULT NULL," .
				"	trading_good tinyint NULL DEFAULT NULL," .
				"	checked smalldatetime NULL DEFAULT NULL," .
				"	incorporated varchar(MAX) NOT NULL," .
				"	has_expiry_date tinyint NULL DEFAULT NULL," .
				"	special_attention tinyint NULL DEFAULT NULL," .
				"	last_order smalldatetime NULL DEFAULT NULL" .
				");"
				,
				"IF OBJECT_ID(N'caro_consumables_vendors', N'U') IS NULL " .
				"CREATE TABLE caro_consumables_vendors (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	active tinyint NOT NULL," .
				"	name varchar(MAX) NOT NULL," .
				"	info varchar(MAX) NOT NULL," .
				"	certificate varchar(MAX) NOT NULL," .
				"	pricelist varchar(MAX) NOT NULL," .
				"	immutable_fileserver varchar(MAX) NOT NULL," .
				"	evaluation varchar(MAX) NOT NULL," .
				");"
				,
				"IF OBJECT_ID(N'caro_csvfilter', N'U') IS NULL " .
				"CREATE TABLE caro_csvfilter (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	name varchar(MAX) NOT NULL," .
				"	date smalldatetime NOT NULL," .
				"	author varchar(MAX) NOT NULL," .
				"	content varchar(MAX) NOT NULL," .
				"	hidden tinyint NOT NULL" .
				");" 
				,
				"IF OBJECT_ID(N'caro_file_bundles', N'U') IS NULL " .
				"CREATE TABLE caro_file_bundles (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	name varchar(MAX) NOT NULL," .
				"	date smalldatetime NOT NULL," .
				"	author varchar(MAX) NOT NULL," .
				"	content varchar(MAX) NOT NULL," .
				"	active tinyint NOT NULL" .
				");"
				,
				"IF OBJECT_ID(N'caro_file_external_documents', N'U') IS NULL " .
				"CREATE TABLE caro_file_external_documents (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	path varchar(MAX) NOT NULL," .
				"	author varchar(MAX) NOT NULL," .
				"	regulatory_context varchar(MAX) NOT NULL," .
				"	activated smalldatetime NULL DEFAULT NULL" .
				"	retired smalldatetime NULL DEFAULT NULL" .
				");"
				,
				"IF OBJECT_ID(N'caro_documents', N'U') IS NULL " .
				"CREATE TABLE caro_documents (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	name varchar(MAX) NOT NULL," .
				"	alias varchar(MAX) NOT NULL," .
				"	context varchar(MAX) NOT NULL," .
				"	unit varchar(MAX) NULL DEFAULT NULL," .
				"	date smalldatetime NOT NULL," .
				"	author varchar(MAX) NOT NULL," .
				"	content varchar(MAX) NOT NULL," .
				"	hidden varchar(MAX) NULL DEFAULT NULL," .
				"	approval varchar(MAX) NULL DEFAULT NULL," .
				"	regulatory_context varchar(MAX) NULL DEFAULT NULL," .
				"	permitted_export tinyint NULL DEFAULT NULL," .
				"	restricted_access varchar(MAX) NULL DEFAULT NULL" .
				");"
				,
				"IF OBJECT_ID(N'caro_manual', N'U') IS NULL " .
				"CREATE TABLE caro_manual (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	title varchar(MAX) NOT NULL," .
				"	content varchar(MAX) NOT NULL," .
				"	permissions varchar(MAX) NOT NULL" .
				");"
				,
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
				,
				"IF OBJECT_ID(N'caro_records', N'U') IS NULL " .
				"CREATE TABLE caro_records (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	context varchar(MAX) NOT NULL," .
				"	case_state varchar(MAX) NULL DEFAULT NULL," .
				"	record_type varchar(MAX) NULL DEFAULT NULL," .
				"	identifier varchar(MAX) NOT NULL," .
				"	last_user int NOT NULL," .
				"	last_touch smalldatetime NOT NULL," .
				"	last_document varchar(MAX) NULL," .
				"	content varchar(MAX) NOT NULL," .
				"	closed varchar(MAX) NULL DEFAULT NULL," .
				"	notified int NULL DEFAULT NULL" .
				");"
				,
				"IF OBJECT_ID(N'caro_risks', N'U') IS NULL " .
				"CREATE TABLE caro_risks (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	process varchar(MAX) NOT NULL," .
				"	risk varchar(MAX) NOT NULL," .
				"	cause varchar(MAX) NOT NULL," .
				"	effect varchar(MAX) NOT NULL," .
				"	probability int NOT NULL," .
				"	damage int NOT NULL," .
				"	measure varchar(MAX) NOT NULL," .
				"	measure_probability int NOT NULL," .
				"	measure_damage int NOT NULL," .
				"	risk_benefit varchar(MAX) NOT NULL," .
				"	measure_remainder varchar(MAX) NOT NULL," .
				"	last_edit varchar(MAX) NOT NULL" .
				");"
				,
				"IF OBJECT_ID(N'caro_texttemplates', N'U') IS NULL " .
				"CREATE TABLE caro_texttemplates (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	name varchar(MAX) NOT NULL," .
				"	unit varchar(MAX) NOT NULL," .
				"	date smalldatetime NOT NULL," .
				"	author varchar(MAX) NOT NULL," .
				"	content varchar(MAX) NOT NULL," .
				"	language varchar(MAX) NOT NULL," .
				"	type varchar(MAX) NOT NULL," .
				"	hidden tinyint NOT NULL" .
				");" 
				,
				"IF OBJECT_ID(N'dbo.caro_user', N'U') IS NULL " .
				"CREATE TABLE caro_user (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	name varchar(MAX) NOT NULL," .
				"	permissions varchar(MAX) NOT NULL," .
				"	units varchar(MAX) NOT NULL," .
				"	token varchar(MAX) NOT NULL," .
				"	orderauth varchar(MAX) NOT NULL," .
				"	image varchar(MAX) NOT NULL," .
				"	app_settings varchar(MAX) NOT NULL," .
				"	skills varchar(MAX) NOT NULL" .
				");"
				,
				"IF OBJECT_ID(N'dbo.caro_user_training', N'U') IS NULL " .
				"CREATE TABLE caro_user_training (" .
				"	id int NOT NULL IDENTITY(1,1)," .
				"	user_id varchar(MAX) NOT NULL," .
				"	name varchar(MAX) NOT NULL," .
				"	date date NOT NULL," .
				"	expires date NULL," .
				"	experience_points int NULL," .
				"	file_path varchar(MAX) NULL DEFAULT NULL," .
				"	evaluation varchar(MAX) NULL DEFAULT NULL," .
				");"
				,
				],
			'insertions' => [
				'user' => "INSERT INTO caro_user (name, permissions, units, token, orderauth, image, app_settings) VALUES ('" . CONFIG['system']['caroapp'] . "', 'admin', '', '" . REQUEST[0] . "', '', 'media/favicon/ios/256.png', '', '');",
				'manual' => "INSERT INTO caro_manual (title, content, permissions) VALUES (':title', ':content', ':permissions');",
			]
		]
	]
];

$driver = CONFIG['sql'][CONFIG['sql']['use']]['driver'];

$devupdate = false;
try {
	if ($devupdate) throw new ErrorException('force update');
	$statement = $pdo->query($queries['precheck'][$driver]);
	echo "databases already installed.";
}
catch (Exception $e){
	$processing = $queries['install'][$driver]['tables'];

	if (!$devupdate) {
		// add default user
		$processing[] = $queries['install'][$driver]['insertions']['user'];
		// add default manual entries according to set up language
		if ($file = file_get_contents('./_install.default.' . CONFIG['application']['defaultlanguage'] . '.json')){
			$languagefile = json_decode($file, true);
			foreach($languagefile['defaultmanual'] as $entry){
				$processing[] = strtr($queries['install'][$driver]['insertions']['manual'], [
					':title' => $entry['title'],
					':content' => $entry['content'],
					':permissions' => $entry['permissions']
				]);
			}
			foreach ($processing as $command){
				echo $command . "\n";
				$statement = $pdo->query($command);
			}
		}
	}
} 

header("Location: ../index.html");
die();
?>
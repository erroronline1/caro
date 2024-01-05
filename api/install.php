<?php

ini_set('display_errors', 1); error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/html; charset=UTF-8');
define ('INI', parse_ini_file('setup.ini', true));

$pdo = new PDO( INI['sql'][INI['sql']['use']]['driver'] . ':' . INI['sql'][INI['sql']['use']]['host'] . ';' . INI['sql'][INI['sql']['use']]['database']. ';' . INI['sql'][INI['sql']['use']]['charset'], INI['sql'][INI['sql']['use']]['user'], INI['sql'][INI['sql']['use']]['password']);

$queries = [
	'precheck' => [
		'mysql' => "SELECT * FROM caro_user LIMIT 1",
		'sqlsrv' => "SELECT TOP 1 * FROM caro_user"
	],
	'install' => [
		'mysql' => [
			"CREATE TABLE IF NOT EXISTS `caro_user` (" .
			"	`id` int NOT NULL AUTO_INCREMENT," .
			"	`name` text COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`permissions` text COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`units` text COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`token` text COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`image` text COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	PRIMARY KEY (`id`)" .
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
			,
			"INSERT INTO caro_user (id, name, permissions, units, token, image), (NULL, '" . INI['caroapp'] . "', 'admin', '', '1234', 'media/favicon/logo.png');"
			,
			"CREATE TABLE IF NOT EXISTS `caro_form_components` (" .
			"	`id` int NOT NULL AUTO_INCREMENT," .
			"	`name` text COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`date` timestamp NOT NULL," .
			"	`content` json NOT NULL," .
			"	PRIMARY KEY (`id`)" .
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
			,
			"INSERT INTO `caro_form_components` (`id`, `name`, `date`, `content`) VALUES" .
			"(1, 'template', CURRENT_TIMESTAMP, '{\"form\": {}, \"content\": [[{\"type\": \"textinput\", \"attributes\": {\"placeholder\": \"text input\"}, \"description\": \"text field\"}, {\"type\": \"numberinput\", \"attributes\": {\"placeholder\": \"nummer\"}, \"description\": \"number field\"}, {\"type\": \"textarea\", \"attributes\": {\"rows\": 4, \"value\": \"values are passed as a value pseudoattribute\"}, \"description\": \"textarea (multiline)\"}, {\"type\": \"dateinput\", \"attributes\": {\"placeholder\": \"datum\"}, \"description\": \"date field\"}], [{\"type\": \"file\", \"description\": \"file upload\"}], [{\"type\": \"photo\", \"description\": \"photo upload\"}], [{\"type\": \"text\", \"content\": \"it is very informative\", \"description\": \"this is just a text\"}, {\"type\": \"links\", \"content\": {\"Link 1\": {\"href\": \"http://erroronline.one\"}, \"Link 2\": {\"href\": \"#\"}}, \"description\": \"links\"}], [{\"type\": \"checkbox\", \"content\": {\"Checkbox 3\": {}, \"checkbox 2\": {}, \"checkbox 4\": {}, \"Checkbox 1ä $\": {}}, \"description\": \"checkboxes\"}, {\"type\": \"radio\", \"content\": {\"Radio 1\": {}, \"Radio 2\": {}}, \"description\": \"radiobuttons\"}, {\"type\": \"select\", \"content\": {\"Entry 1\": {\"value\": \"eins\"}, \"Entry 2\": {\"value\": \"zwei\", \"selected\": true}}, \"attributes\": {\"size\": 2}, \"description\": \"select list\"}], [{\"type\": \"signature\", \"description\": \"signature\"}], [{\"type\": \"scanner\", \"description\": \"the text-input will be populated by the result of the scanner\"}]]}');"
				,
				"CREATE TABLE IF NOT EXISTS `caro_consumables_vendors` (" .
			"	`id` int NOT NULL AUTO_INCREMENT," .
			"	`active` tinyint(1) NOT NULL," .
			"	`name` text COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`info` text COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`certificate` json NOT NULL," .
			"	`pricelist` json NOT NULL," .
			"	`immutable_fileserver` text COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	PRIMARY KEY (`id`)" .
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
			,
			"CREATE TABLE IF NOT EXISTS `caro_messages` (" .
			"	`id` int NOT NULL AUTO_INCREMENT," .
			"	`user_id` int NOT NULL," .
			"	`from_user` int NOT NULL," .
			"	`to_user` int NOT NULL," .
			"	`message` text COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`timestamp` timestamp NOT NULL," .
			"	`alert` tinyint DEFAULT NULL," .
			"	PRIMARY KEY (`id`)" .
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
			,
			"CREATE TABLE IF NOT EXISTS `caro_consumables_products` (" .
			"	`id` int NOT NULL AUTO_INCREMENT," .
			"	`vendor_id` int NOT NULL," .
			"	`article_no` text COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`article_name` text COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`article_unit` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`article_ean` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`active` tinyint NOT NULL," .
			"	`protected` tinyint NOT NULL" .
			"	PRIMARY KEY (`id`)" .
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
			,
			"CREATE TABLE IF NOT EXISTS `caro_consumables_prepared_orders` (" .
			"	`id` int NOT NULL AUTO_INCREMENT," .
			"	`order_data` json NOT NULL" .
			"	PRIMARY KEY (`id`)" .
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;" 
			,
			"CREATE TABLE IF NOT EXISTS `caro_consumables_approved_orders` (" .
			"	`id` int NOT NULL AUTO_INCREMENT," .
			"	`order_data` json NOT NULL," .
			"	`organizational_unit` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`approval` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL," .
			"	`approved` timestamp NOT NULL," .
			"	`ordered` timestamp NULL DEFAULT NULL," .
			"	`received` timestamp NULL DEFAULT NULL" .
			"	`archived` timestamp NULL DEFAULT NULL" .
			"	PRIMARY KEY (`id`)" .
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
			]
		,
		'sqlsrv' => [
			"IF OBJECT_ID(N'dbo.caro_user', N'U') IS NULL " .
			"CREATE TABLE caro_user (" .
			"	id int NOT NULL IDENTITY(1,1)," .
			"	name varchar(MAX) NOT NULL," .
			"	permissions varchar(MAX) NOT NULL," .
			"	units varchar(MAX) NOT NULL," .
			"	token varchar(MAX) NOT NULL," .
			"	image varchar(MAX) NOT NULL" .
			");"
			,
			"INSERT INTO caro_user (name, permissions, units, token, image) VALUES ('" . INI['caroapp'] . "', 'admin', '', '1234', 'media/favicon/logo.png');"
			,
			"IF OBJECT_ID(N'caro_form_components', N'U') IS NULL " .
			"CREATE TABLE caro_form_components (" .
			"	id int NOT NULL IDENTITY(1,1)," .
			"	name varchar(MAX) NOT NULL," .
			"	date smalldatetime NOT NULL," .
			"	content varchar(MAX) NOT NULL" .
			");"
			,
			"INSERT INTO caro_form_components (name, date, content) VALUES " .
			"('template', CURRENT_TIMESTAMP, '{\"form\": {}, \"content\": [[{\"type\": \"textinput\", \"attributes\": {\"placeholder\": \"text input\"}, \"description\": \"text field\"}, {\"type\": \"numberinput\", \"attributes\": {\"placeholder\": \"nummer\"}, \"description\": \"number field\"}, {\"type\": \"textarea\", \"attributes\": {\"rows\": 4, \"value\": \"values are passed as a value pseudoattribute\"}, \"description\": \"textarea (multiline)\"}, {\"type\": \"dateinput\", \"attributes\": {\"placeholder\": \"datum\"}, \"description\": \"date field\"}], [{\"type\": \"file\", \"description\": \"file upload\"}], [{\"type\": \"photo\", \"description\": \"photo upload\"}], [{\"type\": \"text\", \"content\": \"it is very informative\", \"description\": \"this is just a text\"}, {\"type\": \"links\", \"content\": {\"Link 1\": {\"href\": \"http://erroronline.one\"}, \"Link 2\": {\"href\": \"#\"}}, \"description\": \"links\"}], [{\"type\": \"checkbox\", \"content\": {\"Checkbox 3\": {}, \"checkbox 2\": {}, \"checkbox 4\": {}, \"Checkbox 1ä $\": {}}, \"description\": \"checkboxes\"}, {\"type\": \"radio\", \"content\": {\"Radio 1\": {}, \"Radio 2\": {}}, \"description\": \"radiobuttons\"}, {\"type\": \"select\", \"content\": {\"Entry 1\": {\"value\": \"eins\"}, \"Entry 2\": {\"value\": \"zwei\", \"selected\": true}}, \"attributes\": {\"size\": 2}, \"description\": \"select list\"}], [{\"type\": \"signature\", \"description\": \"signature\"}], [{\"type\": \"scanner\", \"description\": \"the text-input will be populated by the result of the scanner\"}]]}');"
			,
			"IF OBJECT_ID(N'caro_consumables_vendors', N'U') IS NULL " .
			"CREATE TABLE caro_consumables_vendors (" .
			"	id int NOT NULL IDENTITY(1,1)," .
			"	active tinyint NOT NULL," .
			"	name varchar(MAX) NOT NULL," .
			"	info varchar(MAX)  NOT NULL," .
			"	certificate varchar(MAX) NOT NULL," .
			"	pricelist varchar(MAX) NOT NULL," .
			"	immutable_fileserver varchar(MAX) NOT NULL," .
			");"
			,
			"IF OBJECT_ID(N'caro_messages', N'U') IS NULL " .
			"CREATE TABLE caro_messages (" .
			"	id int NOT NULL IDENTITY(1,1)," .
			"	user_id int NOT NULL," .
			"	from_user int NOT NULL," .
			"	to_user int NOT NULL," .
			"	message varchar(MAX) NOT NULL," .
			"	timestamp smalldatetime NOT NULL," .
			"	alert tinyint DEFAULT NULL," .
			");"
			,
			"IF OBJECT_ID(N'caro_consumables_products', N'U') IS NULL " .
			"CREATE TABLE caro_consumables_products (" .
			"	id int NOT NULL IDENTITY(1,1)," .
			"	vendor_id int NOT NULL," .
			"	article_no varchar(MAX) NOT NULL," .
			"	article_name varchar(MAX) NOT NULL," .
			"	article_unit varchar(MAX) NOT NULL," .
			"	article_ean varchar(MAX) NOT NULL," .
			"	active tinyint NOT NULL," .
			"	protected tinyint NOT NULL" .
			");"
			,
			"IF OBJECT_ID(N'caro_consumables_prepared_orders', N'U') IS NULL " .
			"CREATE TABLE caro_consumables_prepared_orders (" .
			"	id int NOT NULL IDENTITY(1,1)," .
			"	order_data varchar(MAX) NOT NULL" .
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
			"	received smalldatetime NULL DEFAULT NULL," .
			"	archived smalldatetime NULL DEFAULT NULL" .
			");"
			]				
		]
];

try {
	$statement = $pdo->query($queries['precheck'][INI['sql'][INI['sql']['use']]['driver']]);
	echo "databases already installed.";
}
catch (Exception $e){
	foreach ($queries['install'][INI['sql'][INI['sql']['use']]['driver']] as $command){
		$statement = $pdo->query($command);
	}
} 

header("Location: ../index.html");
die();
?>
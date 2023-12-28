<?php

/*
samples - wip

CREATE TABLE IF NOT EXISTS `caro_user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `permissions` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `units` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


type=text for readability within database admin, queries have to convert in order to 

IF OBJECT_ID(N'caro_user', N'U') IS NULL
CREATE TABLE caro_user (
  id int NOT NULL IDENTITY(1,1),
  name text NOT NULL,
  permissions text NOT NULL,
  units text NOT NULL,
  token text  NOT NULL,
  image text  NOT NULL
) ;



TIMESTAMP DEFAULT NOT POSSIBLE IN MSSQL->ADAPT COMPONENTS EDITOR!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

CREATE TABLE IF NOT EXISTS `caro_form_components` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` timestamp NOT NULL,
  `content` json NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

IF OBJECT_ID(N'caro_form_components', N'U') IS NULL
CREATE TABLE caro_form_components (
  id int NOT NULL IDENTITY(1,1),
  name text NOT NULL,
  date smalldatetime NOT NULL,
  content text NOT NULL
);

INSERT INTO `caro_form_components` (`id`, `name`, `date`, `content`) VALUES
(1, 'template', '2023-10-29 14:55:24', '{\"form\": {}, \"content\": [[{\"type\": \"textinput\", \"attributes\": {\"placeholder\": \"text input\"}, \"description\": \"text field\"}, {\"type\": \"numberinput\", \"attributes\": {\"placeholder\": \"nummer\"}, \"description\": \"number field\"}, {\"type\": \"textarea\", \"attributes\": {\"rows\": 4, \"value\": \"values are passed as a value pseudoattribute\"}, \"description\": \"textarea (multiline)\"}, {\"type\": \"dateinput\", \"attributes\": {\"placeholder\": \"datum\"}, \"description\": \"date field\"}], [{\"type\": \"file\", \"description\": \"file upload\"}], [{\"type\": \"photo\", \"description\": \"photo upload\"}], [{\"type\": \"text\", \"content\": \"it is very informative\", \"description\": \"this is just a text\"}, {\"type\": \"links\", \"content\": {\"Link 1\": {\"href\": \"http://erroronline.one\"}, \"Link 2\": {\"href\": \"#\", \"onpointerdown\": \"alert(\'hello\')\"}}, \"description\": \"links\"}], [{\"type\": \"checkbox\", \"content\": {\"Checkbox 3\": {}, \"checkbox 2\": {}, \"checkbox 4\": {}, \"Checkbox 1ä $\": {}}, \"description\": \"checkboxes\"}, {\"type\": \"radio\", \"content\": {\"Radio 1\": {}, \"Radio 2\": {}}, \"description\": \"radiobuttons\"}, {\"type\": \"select\", \"content\": {\"Entry 1\": {\"value\": \"eins\"}, \"Entry 2\": {\"value\": \"zwei\", \"selected\": true}}, \"attributes\": {\"size\": 2}, \"description\": \"select list\"}], [{\"type\": \"signature\", \"description\": \"signature\"}], [{\"type\": \"scanner\", \"description\": \"the text-input will be populated by the result of the scanner\"}]]}'),
(2, 'login', '2023-10-29 22:19:26', '{\"form\": {}, \"content\": [[{\"type\": \"scanner\", \"description\": \"Login\"}]]}'),
(9, 'test', '2023-11-19 00:32:06', '{\"content\": [[{\"type\": \"text\", \"content\": \"this is a test\", \"description\": \"test\"}]]}'),
(10, 'test', '2023-11-19 00:36:02', '{\"content\": [[{\"type\": \"text\", \"content\": \"sdfgsdfg\", \"description\": \"sergsdrg\"}]]}'),
(11, 'test', '2023-11-19 00:40:46', '{\"content\": [[{\"type\": \"text\", \"content\": \"sdfgsdfg\", \"description\": \"sergsdrg\"}]]}'),
(12, 'asd', '2023-12-08 23:43:24', '{\"form\": [], \"content\": [[{\"type\": \"signature\", \"attributes\": {\"id\": \"signature\", \"name\": \"signature\", \"type\": \"file\", \"hidden\": \"1\"}, \"description\": \"Clear Signature\"}]]}');




CREATE TABLE IF NOT EXISTS `caro_consumables_distributors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `info` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `certificate` json NOT NULL,
  `pricelist` json NOT NULL,
  `immutable_fileserver` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

IF OBJECT_ID(N'caro_consumables_distributors', N'U') IS NULL
CREATE TABLE caro_consumables_distributors (
  id int NOT NULL IDENTITY(1,1),
  active tinyint NOT NULL,
  name text NOT NULL,
  info text  NOT NULL,
  certificate text NOT NULL,
  pricelist text NOT NULL,
  immutable_fileserver text NOT NULL,
);




CREATE TABLE `caro_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user` int NOT NULL,
  `from_user` int NOT NULL,
  `to_user` int NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` timestamp NOT NULL,
  `alert` tinyint DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `caro_consumables_products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distributor_id` int NOT NULL,
  `article_no` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `article_name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `article_unit` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `article_ean` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint NOT NULL,
  `protected` tinyint NOT NULL
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `caro_consumables_prepared_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_data` json NOT NULL
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `caro_consumables_approved_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_data` json NOT NULL,
  `organizational_unit` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `approval` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `approved` timestamp NOT NULL,
  `ordered` timestamp NULL DEFAULT NULL,
  `received` timestamp NULL DEFAULT NULL
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





*/

?>
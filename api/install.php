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
*/

?>
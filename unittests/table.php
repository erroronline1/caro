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

// test the openspout abstraction layer / wrapper
namespace CARO\API;
require_once('../api/_config.php');
require_once('../api/_utility.php'); // general utilities

require_once('../api/_table.php');

$t = new TABLE(__DIR__.'/sample-csv-files-sample-6.csv', null, ['headerrow' => 2]);

var_dump(
	$t->dump('table.ods', null, [
		'structured' => true,
		'orientation' => 'landscape',
		'columns' => [
			'first' => [
				'border' => 'bottom',
				'font-size' => 8,
				'width' => 265.8,
				'height' => 64.3
			],
			'date' => [
				'type' => 'date'
			]
		]
	]),

	$t->dump('table.xlsx', null, [
		'structured' => true,
		'orientation' => 'landscape',
		'columns' => [
			'first' => [
				'border' => 'bottom',
				'font-size' => 10,
				'width' => 265.8,
				'height' => 64.3
			],
			'date' => [
				'type' => 'date'
			]
		]
	]),

	$t->dump('table.csv', null, [
		'structured' => true,
		'columns' => [
			'first' => [
				'font-size' => 14,
				'width' => 265.8,
				'height' => 64.3
			],
			'date' => [
				'type' => 'date'
			]
		]
	])
);

// $t = new TABLE(UTILITY::directory('tmp') . '/table.xlsx');
$t = new TABLE(UTILITY::directory('tmp') . '/table.csv');

var_dump(
	$t->dump([])
);

?>
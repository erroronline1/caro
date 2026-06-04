<?php
/**
 * SPDX-FileNotice: Part of erroronline1/caro a quality management software.
 * SPDX-FileCopyrightText: © 2023 error on line 1 <dev@erroronline.one>
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

$_filehandler = new FILEHANDLER();
// $t = new TABLE($_filehandler->directory('tmp') . '/table.xlsx');
$t = new TABLE($_filehandler->directory('tmp') . '/table.csv');

var_dump(
	$t->dump([])
);

?>
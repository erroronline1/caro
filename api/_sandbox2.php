<?php
namespace CARO\API;
require_once('_config.php');
require_once('_utility.php'); // general utilities

require(__DIR__ . '/../vendor/autoload.php');
require_once('_table.php');

$t = new TABLE(__DIR__.'/../unittests/sample-csv-files-sample-6.csv', null, ['headerrow' => 2]);
//var_dump($t->dump([]));
$t->dump(__DIR__.'/../unittests/table.ods', null, ['structured' => true]);

/*use OpenSpout\Reader\CSV\Reader;
use OpenSpout\Reader\CSV\Options;

$options = new Options(false, CONFIG['csv']['dialect']['separator'], CONFIG['csv']['dialect']['enclosure']);
$reader = new Reader($options);
$reader->open('../unittests/sample-csv-files-sample-6.csv');
*/

//use OpenSpout\Writer\CSV\Writer;
//$writer = new Writer();
//$writer->openToFile(__DIR__.'/../unittests/csv2csv.csv');

//use OpenSpout\Writer\ODS\Writer;
//$writer = new Writer();
//$writer->openToFile(__DIR__.'/../unittests/csv2ods.ods');

//use OpenSpout\Writer\XLSX\Writer;
//$writer = new Writer();
//$writer->openToFile(__DIR__.'/../unittests/csv2xlsx.xlsx');

/*
use OpenSpout\Reader\XLSX\Reader;
$reader = new Reader();
$reader->open('../unittests/csv2xlsx.xlsx');

use OpenSpout\Writer\ODS\Writer;
$writer = new Writer();
$writer->openToFile(__DIR__.'/../unittests/xlsx2ods.ods');

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;


foreach ($reader->getSheetIterator() as $sheet) {
    foreach ($sheet->getRowIterator() as $row) { // $row is a "Row" object, not an array
        $rowAsArray = $row->toArray();  // this is the 2.x equivalent

		$cells = [];
		foreach($rowAsArray as $cellvalue){
			$cells[] = new Cell\StringCell($cellvalue);
		};




		$row = new Row([ ...$cells]);
/*			new Cell\BooleanCell(true),
			new Cell\DateIntervalCell(new DateInterval('P1D')),
			new Cell\DateTimeCell(new DateTimeImmutable('now')),
			new Cell\EmptyCell(null),
			new Cell\FormulaCell('=SUM(A1:A2)'),
			new Cell\NumericCell(3),
			new Cell\StringCell('foo'),
		]);
*/

/*
		$writer->addRow($row);
		}
}

$writer->close();
*/



?>
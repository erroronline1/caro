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

CLASS TABLE{
	private $content = [];
	private $unstructuredDataAboveHeader = [];

	public function __construct($src, $type = null, $options = []){
		if (gettype($src) === 'string'){
			// url
			$file = pathinfo($src);
			switch (strtolower($type ?: $file['extension'])){
				case 'csv':
					$csvoptions = new \OpenSpout\Reader\CSV\Options(false, $options['separator'] ?? CONFIG['csv']['dialect']['separator'], $options['enclosure'] ?? CONFIG['csv']['dialect']['enclosure']);
					$reader = new \OpenSpout\Reader\CSV\Reader($csvoptions);
					break;
				case 'ods':
					$reader = new \OpenSpout\Reader\ODS\Reader();
					break;
				case 'xlsx':
					$reader = new \OpenSpout\Reader\XLSX\Reader();
					break;
				default:
					//unsupported type
			}
			$reader->open($src);

			foreach ($reader->getSheetIterator() as $sheet) {
				$sheetname = $sheet->getName();
				$this->content[$sheetname] = [];
				$rownumber = 0;
				$headerkeys = [];
				foreach ($sheet->getRowIterator() as $row) { // $row is a "Row" object, not an array
				if ($rownumber++ < ($options['headerrow'] ?? 1)){
					$this->unstructuredDataAboveHeader[] = $row->toArray(); 
					$headerkeys = $row->toArray();
					continue;
				}
				$this->content[$sheetname][] = array_combine($headerkeys, $row->toArray());
				}
			}

		}
		elseif (gettype($src) === 'array'){
			$this->content = $src;
		}
		elseif (gettype($src) === 'object'){
			$this->content = $src->toArray();
		}
		else {
			//unsupported source
		}
	}

	public function dump($dst, $type = null, $options = []){
		if (gettype($dst) === 'string'){
			// url
			$file = pathinfo($dst);
			switch (strtolower($type ?: $file['extension'])){
				case 'csv':
					$csvoptions = new \OpenSpout\Writer\CSV\Options($options['separator'] ?? CONFIG['csv']['dialect']['separator'], $options['enclosure'] ?? CONFIG['csv']['dialect']['enclosure']);
					$writer = new \OpenSpout\Writer\CSV\Writer($csvoptions);
					break;
				case 'ods':
					$writer = new \OpenSpout\Writer\ODS\Writer();
					break;
				case 'xlsx':
					$writer = new \OpenSpout\Writer\XLSX\Writer();
					break;
				default:
					//unsupported source
			}
			$writer->openToFile($dst);

			foreach($this->content as $sheetname => $sheet) {
				$currentSheet = $writer->getCurrentSheet();
				if ($sheetname) $currentSheet->setName($sheetname);

				$rows = [];

				if (!($options['structured'] ?? false) && count($this->unstructuredDataAboveHeader) > 1){
					for($ln = 0; $ln < count($this->unstructuredDataAboveHeader) - 1; $ln++){
						$rows[] = new \OpenSpout\Common\Entity\Row([
							...array_map(Fn($v) => new \OpenSpout\Common\Entity\Cell\StringCell($v), $this->unstructuredDataAboveHeader[$ln])
						]);
					}
				}

				$rows[] = new \OpenSpout\Common\Entity\Row([
					...array_map(Fn($v) => new \OpenSpout\Common\Entity\Cell\StringCell($v), array_keys($sheet[0]))
				]);
				/*	new Cell\BooleanCell(true),
					new Cell\DateIntervalCell(new DateInterval('P1D')),
					new Cell\DateTimeCell(new DateTimeImmutable('now')),
					new Cell\EmptyCell(null),
					new Cell\FormulaCell('=SUM(A1:A2)'),
					new Cell\NumericCell(3),
					new Cell\StringCell('foo'),
				]);
				*/

				foreach($sheet as $row){
					$rows[] = new \OpenSpout\Common\Entity\Row([
						...array_map(Fn($v) => new \OpenSpout\Common\Entity\Cell\StringCell($v), array_values($row))
					]);
				};
				$writer->addRows($rows);

				$writer->addNewSheetAndMakeItCurrent();
			}
			
			// remove last sheet


			return $writer->close();
		}
		else
			return $this->content;
	}
}
?>
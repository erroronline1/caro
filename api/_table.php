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

require(__DIR__ . '/../vendor/autoload.php');

CLASS TABLE{
	private $content = [];
	private $unstructuredDataAboveHeader = [];

	/**
	 * imports data from a file or a named array
	 * @param string|array|object $src file path or raw data
	 * @param string $type override filetype detected by $src extension if applicable
	 * @param array $options with csv params for 'separator' and 'enclosure'
	 * 
	 * @return bool
	 */
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
					$reader = new \OpenSpout\Reader\XLSX\Reader(
						new \OpenSpout\Reader\XLSX\Options(
							tempFolder: UTILITY::directory('tmp'),
						)
					);
					break;
				default:
					return false;
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
			return false;
		}
		return true;
	}

	/**
	 * add data above the header or flush by providing an empty array
	 * @param $data as tweo dimensional array
	 */
	public function dataAboveHeader($data){
		$this->unstructuredDataAboveHeader = $data;
	}

	/**
	 * returns the internal data, e.g. read files in other formats, read files as array or raw data as files
	 * @param string|array $dst output file path or array
	 * @param string $type override filetype detected by $dst extension if applicable
	 * @param array $options with  
	 * 		* csv params for 'separator' and 'enclosure'
	 * 		* 'structured' to omit everything imported prior to headers
	 * 		* 'creator' name
	 * 		* 'size' paper size
	 * 		* 'orientation' portrait or landscape
	 * 		* 'columns' as an array with column headers/names as keys and another array of [
	 * 				'type' => string, // bool, number, date, formula
	 * 				'border' => top, // right, bottom, left
	 * 				'font-size' => int,
	 * 				'width' => int,
	 * 				'height' => float,
	 * 				'wrap-text': bool,
	 * 				'halign': 'left', // right, center, justify
	 * 				'valign': 'auto', // baseline, bottom, center, distributed, justify, top
	 * 				'border': 'top', // right, bottom, left
	* 			] as value
	 * 
	 * @return null|array
	 * 
	 * exports default the type to string if omitted
	 * not all options of openspout are available, it's about data in this usecase
	 */
	public function dump($dst, $type = null, $options = []){
		if (gettype($dst) === 'string'){
			// url
			$file = pathinfo($dst);
			switch (strtolower($type ?: $file['extension'])){
				case 'csv':
					return $this->files($dst, $type, $options);
					break;
				case 'ods':
				case 'xlsx':
					return $this->sheets($dst, $type, $options);
					break;
				default:
					return null;
					//unsupported source
			}
		}
		else
			return $this->content;
	}

	// write to multiple csv
	private function files($dst, $type = null, $options = []){
		$response = [];
		$csvoptions = new \OpenSpout\Writer\CSV\Options(
			$options['separator'] ?? CONFIG['csv']['dialect']['separator'],
			$options['enclosure'] ?? CONFIG['csv']['dialect']['enclosure'],
		//	SHOULD_ADD_BOM: false
		);
		$writer = new \OpenSpout\Writer\CSV\Writer($csvoptions);
		$sheetName = $recentSheet = null;

		// write sheets
		foreach($this->content as $sheetName => $sheet) {
			$rows = [];
	
			if ($recentSheet !== $sheetName){
				// add a new file
				@$tmp_name = tempnam(UTILITY::directory('tmp'), mt_rand(0, 100000));
				$writer->openToFile($tmp_name);
				$recentSheet = $sheetName;
			}

			// insert data that may have been imported prior from above the header line if not excluded via options
			if (!($options['structured'] ?? false) && count($this->unstructuredDataAboveHeader) > 1){
				for($ln = 0; $ln < count($this->unstructuredDataAboveHeader) - 1; $ln++){
					$rows[] = new \OpenSpout\Common\Entity\Row([
						...array_map(Fn($v) => new \OpenSpout\Common\Entity\Cell\StringCell($v), $this->unstructuredDataAboveHeader[$ln])
					]);
				}
			}
			
			// insert the header line
			$rows[] = new \OpenSpout\Common\Entity\Row([
				...array_map(Fn($v) => new \OpenSpout\Common\Entity\Cell\StringCell($v), array_keys($sheet[0]))
			]);

			// insert data
			foreach($sheet as $row){
				$columns = [];
				foreach($row as $value){
					$value = $value ?: '';
					$columns[] = new \OpenSpout\Common\Entity\Cell\StringCell($value);
				}

				$rows[] = new \OpenSpout\Common\Entity\Row([
					...$columns
				]);
			};
			$writer->addRows($rows);
			$writer->close();
			$response[] = $tmp_name;
		}

		foreach($response as &$path){
			$path = UTILITY::handle($path, $dst, 0, [], UTILITY::directory('tmp'), false);
		}
		return $response;
	}

	// write to ods or xlsx
	private function sheets($dst, $type = null, $options = []){
		// url
		$file = pathinfo($dst);

		$contentToWrite = $this->content;
		switch (strtolower($type ?: $file['extension'])){
			case 'ods':
				$celloptions = new \OpenSpout\Writer\ODS\Options(
					tempFolder: UTILITY::directory('tmp')
					// no page options available yet
				);
				$writer = new \OpenSpout\Writer\ODS\Writer($celloptions);
				if (!empty($options['creator'])) $writer->setCreator($options['creator']);
				break;
			case 'xlsx':
				$celloptions = new \OpenSpout\Writer\XLSX\Options(
					tempFolder: UTILITY::directory('tmp'),
					pageSetup: new \OpenSpout\Writer\XLSX\Options\PageSetup(
						!empty($options['orientation']) ? \OpenSpout\Writer\XLSX\Options\PageOrientation::{strtoupper($options['orientation'])} : null,
						!empty($options['size']) ? \OpenSpout\Writer\XLSX\Options\PaperSize::{strtoupper($options['size'])} : \OpenSpout\Writer\XLSX\Options\PaperSize::A4,
						0,
						1
					),
					properties: new \OpenSpout\Writer\XLSX\Properties(
					    $dst, // public ?string $title = 'Untitled Spreadsheet',
						null, // public ?string $subject = null,
						'CARO APP', // public ?string $application = 'OpenSpout',
						$options['creator'] ?? 'CARO App', // public ?string $creator = 'OpenSpout',
						$options['creator'] ?? 'CARO App', // public ?string $lastModifiedBy = 'OpenSpout',
					),
					headerFooter: new \OpenSpout\Writer\XLSX\Options\HeaderFooter(
						// unfortunately no header per sheet, only global yet
						$file['basename'] . ' - ' . date("Y-m-d H:i"),
						'- &amp;P -'
					)
				);
				$writer = new \OpenSpout\Writer\XLSX\Writer($celloptions);

				$row = null;
				break;
		}

		@$tmp_name = tempnam(UTILITY::directory('tmp'), mt_rand(0, 100000));
		$writer->openToFile($tmp_name);

		// determine defined row heights for data rows
		$rowHeight = max([0, ...array_map(Fn($h) => $h['height'] ?? 0, $options['columns'] ?? [])]);

		// determine column widths if applicable
		$widths = [];
		$border = null;
		foreach(array_keys($contentToWrite[array_key_first($contentToWrite)][0]) as $index => $key){
			if (isset($options['columns'][$key]['width']) && $options['columns'][$key]['width']){
				$w = strval($options['columns'][$key]['width']);
				if (!isset($widths[$w])) $widths[$w] = [];
				$widths[$w][] = $index + 1;
			}
			if (!empty($options['columns'][$key]['border'])) $border = $options['columns'][$key]['border'];
		}

		// sanitize sheet names according to openspout specifications to max length - 4 characters for possible enumeration up to 99
		$sheetname = [];
		foreach(array_keys($contentToWrite) as $sheet){
			if (!boolval($sheet)) continue;
			$sanitizedName = substr(preg_replace('/[^\w\d\s]/', '', $sheet), 0, 31 - 4);
			// enumerate if multiple similar names are present
			if (in_array($sanitizedName, $sheetname)) $sanitizedName .= '(' . array_count_values($sheetname)[$sanitizedName] . ')';
			$sheetname[$sheet] = $sanitizedName;
		}

		// name default first sheet
		$currentSheet = $writer->getCurrentSheet();
		$sheetName = $recentSheet = array_key_first($contentToWrite);
		if (isset($sheetname[$sheetName])) $currentSheet->setName($sheetname[$sheetName]);

		// write sheets
		foreach($contentToWrite as $sheetName => $sheet) {
			$rows = [];
			
			if ($recentSheet !== $sheetName){
				// add and name a new sheet
				$currentSheet = $writer->addNewSheetAndMakeItCurrent();
				$recentSheet = $sheetName;
				if (!empty($sheetName)) $currentSheet->setName($sheetname[$sheetName]);
			}

			// insert data that may have been imported prior from above the header line if not excluded via options
			if (!($options['structured'] ?? false) && count($this->unstructuredDataAboveHeader) > 1){
				for($ln = 0; $ln < count($this->unstructuredDataAboveHeader) - 1; $ln++){
					$rows[] = new \OpenSpout\Common\Entity\Row([
						...array_map(Fn($v) => new \OpenSpout\Common\Entity\Cell\StringCell($v), $this->unstructuredDataAboveHeader[$ln])
					]);
				}
			}
			
			// insert the header line
			$rows[] = new \OpenSpout\Common\Entity\Row([
				...array_map(Fn($v) => new \OpenSpout\Common\Entity\Cell\StringCell($v), array_keys($sheet[0]))
			]);

			// insert data with passed types and formatting if applicable
			foreach($sheet as $row){
				$columns = [];
				foreach($row as $column => $value){
					$value = $value ?: '';

					$options['columns'][$column]['type'] = $options['columns'][$column]['type'] ?? 'string';
					$format = new \OpenSpout\Common\Entity\Style\Style(
						false, // public bool $fontBold = false,
						false, // public bool $fontItalic = false,
						false, // public bool $fontUnderline = false,
						false, // public bool $fontStrikethrough = false,
						$options['columns'][$column]['font-size'] ?? \OpenSpout\Common\Entity\Style\Style::DEFAULT_FONT_SIZE, // public int $fontSize = self::DEFAULT_FONT_SIZE,
						\OpenSpout\Common\Entity\Style\Style::DEFAULT_FONT_COLOR, // public string $fontColor = self::DEFAULT_FONT_COLOR,
						\OpenSpout\Common\Entity\Style\Style::DEFAULT_FONT_NAME, // public string $fontName = self::DEFAULT_FONT_NAME,
						!empty($options['columns'][$column]['halign']) ? \OpenSpout\Common\Entity\Style\CellAlignment::{strtoupper($options['columns'][$column]['halign'])} : null, // public ?CellAlignment $cellAlignment = null,
						!empty($options['columns'][$column]['valign']) ? \OpenSpout\Common\Entity\Style\CellVerticalAlignment::{strtoupper($options['columns'][$column]['valign'])} : null, // public ?CellVerticalAlignment $cellVerticalAlignment = null,
						$options['columns'][$column]['wrap-text'] ?? true, // public ?bool $shouldWrapText = null,
						0, // public int $textRotation = 0,
						true, // public ?bool $shouldShrinkToFit = null,
						$border ? new \OpenSpout\Common\Entity\Style\Border(
							new \OpenSpout\Common\Entity\Style\BorderPart(
								\OpenSpout\Common\Entity\Style\BorderName::{strtoupper($border)},
								\OpenSpout\Common\Entity\Style\BorderPart::DEFAULT_COLOR,
								\OpenSpout\Common\Entity\Style\BorderWidth::THIN
							)
						) : null, // public ?Border $border = null,
						null, // public ?string $backgroundColor = null,
						null, // public ?string $format = null,
					);

					switch ($options['columns'][$column]['type']){
						case 'number':
							$columns[] = new \OpenSpout\Common\Entity\Cell\NumericCell($value, $format);
							break;
						case 'bool':
							$columns[] = new \OpenSpout\Common\Entity\Cell\BooleanCell(boolval($value), $format);
							break;
						case 'date':
							$columns[] = new \OpenSpout\Common\Entity\Cell\DateTimeCell(new \DateTimeImmutable($value), $format);
							break;
						case 'formula':
							$columns[] = new \OpenSpout\Common\Entity\Cell\FormulaCell($value, null, $format);
							break;
						default: // string
							$columns[] = new \OpenSpout\Common\Entity\Cell\StringCell($value, $format);
							break;
					}
				}
				/*	new Cell\BooleanCell(true),
					new Cell\DateIntervalCell(new DateInterval('P1D')),
					new Cell\DateTimeCell(new DateTimeImmutable('now')),
					new Cell\EmptyCell(null),
					new Cell\FormulaCell('=SUM(A1:A2)'),
					new Cell\NumericCell(3),
					new Cell\StringCell('foo'),
				]);
				*/

				$rows[] = new \OpenSpout\Common\Entity\Row([
					...$columns
				], $rowHeight);
			};
			$writer->addRows($rows);

			if ($celloptions) {
				foreach($widths as $width => $columNums){
					$celloptions->setColumnWidth(floatval($width), ...$columNums);
				}
			}
		}
		$writer->close();
		$path = UTILITY::handle($tmp_name, $dst, 0, [], UTILITY::directory('tmp'), false);

		return [$path];
	}
}
?>
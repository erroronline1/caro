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
error_reporting(E_ALL);

require(__DIR__ . '/../vendor/autoload.php');
define('K_PATH_FONTS', realpath(__DIR__ . '/../vendor/tecnickcom/tc-lib-pdf-font/target/fonts'));
require_once(__DIR__ . '/../api/_filehandler.php');

class PDF{
	private array $_pageSetup = [];
	private mixed $_pdf = null;
	public mixed $_sqlinterface = null;
	public mixed $_filehandler = null;
	private mixed $_markdown = null;
	private string $_markdown_css = '';
	private array $_fileContent = [];

	/**
	 * initiates a pdf file with passed page settings
	 * @param array $pageSetup
	 * @param object $pdo passed parent database object for file system handling
	 */
	public function __construct($pageSetup, $pdo = null){
		error_reporting(E_ALL);
		$this->_pageSetup = [
			'format' => $pageSetup['format'] ?? 'A4',
			'unit' => $pageSetup['unit'] ?? 'mm',
			'orientation' => isset($pageSetup['orientation']) && in_array($pageSetup['orientation'], ['landscape', 'L']) ? 'L'  : 'P',
			'margintop' => isset($pageSetup['margintop']) ? intval($pageSetup['margintop']) : 10,
			'marginright' => isset($pageSetup['marginright']) ? intval($pageSetup['marginright']) : 10,
			'marginbottom' => isset($pageSetup['marginbottom']) ? intval($pageSetup['marginbottom']) : 10,
			'marginleft' => isset($pageSetup['marginleft']) ? intval($pageSetup['marginleft']) : 20,
			'header_image' => $pageSetup['header_image'] ?? null,
			'footer_image' => $pageSetup['footer_image'] ?? null,
			'exportimage_maxwidth' => isset($pageSetup['exportimage_maxwidth']) ? min(130, intval($pageSetup['exportimage_maxwidth'])) : 130,
			'exportimage_maxheight' => isset($pageSetup['exportimage_maxheight']) ? intval($pageSetup['exportimage_maxheight']) : 75,
			'rows' => isset($pageSetup['rows']) ? intval($pageSetup['rows']) : 1,
			'row_gap' => isset($pageSetup['row_gap']) ? intval($pageSetup['row_gap']) : 0,
			'columns' => isset($pageSetup['columns']) ? intval($pageSetup['columns']) : 1,
			'column_gap' => isset($pageSetup['column_gap']) ? intval($pageSetup['column_gap']) : 0,
			'fontsize' => isset($pageSetup['fontsize']) ? intval($pageSetup['fontsize']) : 12,
			'codesizelimit' => isset($pageSetup['codesizelimit']) ? intval($pageSetup['codesizelimit']) : null,
			'codepadding' => isset($pageSetup['codepadding']) ? intval($pageSetup['codepadding']) : 2,
			'header' => isset($pageSetup['header']) ? boolval($pageSetup['header']) : true,
			'footer' => isset($pageSetup['footer']) ? boolval($pageSetup['footer']) : true,
		];
		$customsetup = preg_split('/\D{1,}/', $this->_pageSetup['format']);
		if (count($customsetup) > 1 && $customsetup[0] /*not line start*/){
			$this->_pageSetup['format'] = [$customsetup[0], $customsetup[1]];
		}

		$this->_sqlinterface = $pdo;
		$this->_filehandler = new FILEHANDLER($pdo);

		$this->_markdown = new \erroronline1\Markdown\Markdown(8);
		$this->_markdown_css = <<<END
		<style>
			.eol1_odd {
				background-color: #eee;
			}
			td {
				border-right:1px solid #ddd;
				padding: 5px;
			}
			blockquote {
				border-left: 3px solid #ddd;
				padding-left: 5px;
			}
			.input {
				font-size:17px;
				width: 100%;
				padding: 10px;
			}
			textarea {
				width: 100%;
			}
			img {
				display: block;
			}
		</style>
		END;
	}

	/**
	 * initiates a pdf file with the passed content parameters
	 * @param array $fileContent [  
	 *     'filename' => 'without extension',  
	 *     'identifier' => 'qr-code content to the upper left',  
	 *     'content' => [], array of contents handled by the respective method  
	 *     'files' => [], array of files to be embedded  
	 *     'images' => [], array of images to be embedded, also ensures availability via filehandler  
	 *     'title' => 'title of the document',  
	 *     'date' => 'generally used as such but can be any string to show up under the title',  
	 *     'embedfiles' => bool
	 * ]
	 */
	private function init($fileContent){
		$this->_fileContent = $fileContent;
		$this->_fileContent['embedfiles'] = $this->_fileContent['embedfiles'] ?? true;
		
		// create new PDF document and set initial properties
		$this->_pdf = new RECORDTCPDF($this->_pageSetup, true, 
		20, $fileContent['identifier'] ?? null, ['title' => $fileContent['title'] ?? '', 'date' => $fileContent['date'] ?? '']);

		// set document information
		$this->_pdf->SetCreator(CONFIG['system']['caroapp']);
		$this->_pdf->SetAuthor($_SESSION['user']['name']);
		$this->_pdf->SetTitle($fileContent['title'] ?? '');

		// filename sanitation
		$path = $this->_filehandler->directory('tmp') . '/' . $this->_fileContent['filename'] . '.pdf';
		$this->_fileContent['filename'] = substr($this->_fileContent['filename'], 0, 260 - strlen($path));
		$this->_pdf->setPDFFilename($this->_fileContent['filename'] . '.pdf');

		$page = $this->_pdf->page->getPage();
		$this->_markdown_css = str_replace('width: 100%', 'width: '. $page['region'][0]['RW'] - 20 . 'mm', $this->_markdown_css);
	}

	/**
	 * writes the prepared content chunks as name:content to the pages and sets bookmarks
	 * 
	 * @param array $chunks
	 * @param int $bookmarkLevel default 0 but can be overridden
	 * @param bool $init continues after last insertion on false
	 * @return bool to manage init for following calls
	 */
	private function writeStandardHTML($chunks, $bookmarkLevel = 0, $init = true){
		for($i = 0; $i < count(array_keys($chunks)); $i++){
			$name = array_keys($chunks)[$i];
			$content = $chunks[$name];
			/*
			// this works in theory but page breaking is not an easy tasg with long images and within table cells, so this option is postponed

			// set image sizes if not already done so
			// maxheight is set to region height in this case. main appliance would be readme export
			// other contents from within the application are supposed to set the dimensions in advance to calling this function
			preg_match_all('/<img.+?src="(.+?)".+?(width)*.*?>/m', $content, $images);
			if ($images){ // not null
				foreach($images[1] as $index => $image){
					if ($images[2][$index]) continue; // dimensions have been set already
					// sizing
					$getimagesize = @getimagesize($image); // suppress warning
					if (!$getimagesize) continue; // ressource likely not reachable
					list($img_width, $img_height, $img_type, $img_attr) = $getimagesize;
					$ratio = $img_height ? $img_width / $img_height : 1; // prevent division by 0
					// scale to max settings if exceeding these 
					$maxHeight = 30;//$this->_pdf->_contentCoordinates['bottom'] -  $this->_pdf->_contentCoordinates['top'] - 20;
					$out_height = $img_height > $maxHeight ? $maxHeight : $img_height;
					$out_width = $out_height * $ratio;
					$out_width = $out_width > $this->_pageSetup['exportimage_maxwidth'] ? $this->_pageSetup['exportimage_maxwidth'] : $out_width;
					$out_height = $out_width / $ratio;
					// approximately convert pixel to mm; weird factor by trial and error
					$out_width *= (72 / 25.4) * 1.35;
					$out_height *= (72 / 25.4) * 1.35;

					$img_tag = str_replace($image, $image . '" width="' . $out_width . '" height="' . $out_height, $images[0][$index]);
					$content = str_replace($images[0][$index], $img_tag, $content);
				}
			}
			*/
			$page = $this->_pdf->page->getPage();
			$bbox = $init ? ['y' =>  $this->_pdf->_contentCoordinates['top'], 'h' => 0] : $this->_pdf->getLastBBox();
			$init = false;
			if ($name){
				$this->_pdf->setBookmark(
					name: $name,
					level: $bookmarkLevel
				);
				$this->_pdf->addHTMLCell(
					html: '<h3>' . $name . '</h3>',
					posx: $page['region'][0]['RX'],
					posy: $bbox['y'] + $bbox['h'],
					width: $page['region'][0]['RW'],
				);
				$page = $this->_pdf->page->getPage();
				$bbox = $this->_pdf->getLastBBox();
			}
			$this->_pdf->addHTMLCell(
				html: $content,
				posx: $page['region'][0]['RX'] + 20,
				posy: $bbox['y'] + $bbox['h'],
				width: $page['region'][0]['RW'] - 20,
			);
		}
		return $init;
	}

	/**
	 * append embedded files as attachments at the end of the document
	 * @param array $files
	 */
	private function attachments($files){
		if (empty($files) || !$this->_fileContent['embedfiles']) return;

		$page = $this->_pdf->page->getPage();
		$bbox = $this->_pdf->getLastBBox();

		$attachment_caption = $this->_pdf->_lang->GET('record.file_attachments', [], true);
		$this->_pdf->setBookmark(
			name: $attachment_caption
		);
		$this->_pdf->addHTMLCell(
			html: '<h3>' . $attachment_caption . '</h3>',
			posx: $page['region'][0]['RX'],
			posy: $bbox['y'] + $bbox['h'],
			width: $page['region'][0]['RW'],
		);
		
		foreach($files as $file){
			$this->_filehandler->serve($file, false);
			$file = $this->_filehandler->translate_path($file);
			if (!is_file($file)) continue;

			$page = $this->_pdf->page->getPage();
			$bbox = $this->_pdf->getLastBBox();
			$this->_pdf->addHTMLCell(
				html: $file,
				posx: $page['region'][0]['RX'] + 20,
				posy: $bbox['y'] + $bbox['h'],
				width: $page['region'][0]['RW'] - 20,
			);
			$annotId = $this->_pdf->setAnnotation(
				posx: $page['region'][0]['RX'] + $page['region'][0]['RW'] - 10,
				posy: $bbox['y'] + $bbox['h'],
				width: 10,
				height: 10,
				txt: $this->_pdf->_lang->GET('record.export_pdf_attachment', [], true) . ' ' . $file,
				opt: [
					'subtype' => 'FileAttachment',
					'fs'      => realpath($file),
					'name'    => 'PushPin',
					'f'       => 4,
				]
			);
		    $this->_pdf->page->addAnnotRef($annotId);
		}
	}

	/**
	 * final operation to write file and return the saving path 
	 */
	private function return(){
		if ($this->_pageSetup['footer']) $this->_pdf->pageNumeration();

		$this->_pdf->savePDF(__DIR__.'/' .$this->_filehandler->directory('tmp'), $this->_pdf->getOutPDFString());
		return $this->_filehandler->directory('tmp') . '/' . $this->_fileContent['filename'] . '.pdf';
	}

	/**
	 * create audit files
	 * ensure to have html handled before passing, as this defaults to markdown
	 * @param array $fileContent see self::init()
	 */
	public function auditPDF($fileContent){
		$this->init($fileContent);
		// prepare content as html
		foreach ($fileContent['content'] as &$value){
			// values column
			if (gettype($value) === 'array') {
				$value = implode("  \n", array_keys($value));
			}

			if (str_starts_with($value ?: '', '::CODE::')) {
				$value = '<pre style="font-size:small">' . substr($value, 8) . '</pre>';
			}
			else {
				$value = $this->_markdown_css . $this->_markdown->md2html($value);
			}
		}
		$this->writeStandardHTML($fileContent['content']);
		if (!empty($fileContent['files'])) $this->attachments($fileContent['files']);

		return $this->return();
	}

	/**
	 * create a pdf for a document export 
	 * @param array $fileContent see self::init()
	 */
	public function documentsPDF($fileContent){
		$this->init($fileContent);
		$init = true;
		foreach ($fileContent['content'] as $document => $entries){
			$content = [];
			foreach ($entries as $key => $value){
				switch ($value['type']){
					case 'version':
						$content[' '] = $value['value'];
						break;
					case 'textsection':
					case 'markdown':
						$content[$key] = $this->_markdown_css . $this->_markdown->md2html($value['value']);
						break;
					case 'image':
						if (isset($fileContent['images'][$document]) && in_array($value['value'], $fileContent['images'][$document])) {
							// inline image embedding
							$filename = $value['value'];
							$this->_filehandler->serve($filename, false);
							// sizing
							list($img_width, $img_height, $img_type, $img_attr) = getimagesize($filename);
							$ratio = $img_height ? $img_width / $img_height : 1; // prevent division by 0
							// scale to max settings if exceeding these 
							$out_height = $img_height > $this->_pageSetup['exportimage_maxheight'] ? $this->_pageSetup['exportimage_maxheight'] : $img_height;
							$out_width = $out_height * $ratio;
							$out_width = $out_width > $this->_pageSetup['exportimage_maxwidth'] ? $this->_pageSetup['exportimage_maxwidth'] : $out_width;
							$out_height = $out_width / $ratio;
							// approximately convert pixel to mm; weird factor by trial and error
							$out_width *= (72 / 25.4) * 1.35;
							$out_height *= (72 / 25.4) * 1.35;
							// realpath for tc-lib-pdf								
							$content[$key] = $this->_markdown_css . '<img src="' . realpath($filename) . '" width="' . $out_width . '" height="' . $out_height . '" class="eol1_md" />';
						}
						break;
					case 'selection':
						$checkboxes = [];
						foreach ($value['value'] as $option){
							$checked = str_starts_with($option, '_____');
							$option = $checked ? substr($option, 5) : $option;
							$checkboxes[] = '<label><input type="checkbox" ' . ($checked ? 'checked' : '') . ' name="' . $key.$option . '" /> ' . $option . '</label>';
						}
						$content[$key] = $this->_markdown_css . implode('<br /><br />', $checkboxes);
						break;
					case 'multiline':
						if ($value['value']) { // print value if present
							$content[$key] = nl2br($value['value']);
							break;
						}
						// else textarea
						$content[$key] = $this->_markdown_css . '<textarea rows="8" name="' . $key . '"> </textarea>'
							. '<span style="color:white">' . str_repeat('.<br />', 7) . '</span>'; // height is off otherwise
						break;
					case 'links':
						$links = [];
						if ($value['value']) {
							foreach ($value['value'] as $link){
								if ($link) {
									$links[] = '<a href="' . $link . '" class="eol1_md" target="_blank">' . $link . '</a>';
								}
							}
						}
						$content[$key] = $this->_markdown_css . implode('<br />', $links);
						break;
					default:
						if ($value['value']) { // print value if present
							preg_match("/(?:^href=')(.+?)(?:')(.*)/", $value['value'], $link); // link widget value
							if ($link) {
								$content[$key] = $this->_markdown_css . '<a href="' . $link[1] . '" class="eol1_md" target="_blank">' . $link[1] . '</a>' . ($link[2] ? $this->_markdown->md2html($link[2]): '');
							}
							else 
								$content[$key] = $value['value'];
							break;

						}
						$content[$key] = $this->_markdown_css . '<input type="text" class="input" name="' . $key . '" />'
							. '<br /><span style="color:white">.</span>'; // height is off otherwise
				}
			}
			$init = $this->writeStandardHTML($content, 0, $init);
		}

		return $this->return();
	}

	/**
	 * create order export with pagebreaks per commission for delivery notes
	 * markdown is limited like on frontend
	 * @param array $fileContent see self::init()
	 */
	public function orderPDF($fileContent){
		$this->init($fileContent);
		
		$page = 0;
		foreach ($fileContent['content'] as $commission){
			if ($page++) $this->_pdf->AddPage();
			foreach ($commission as $key => &$value){
				// values column
				if (gettype($value) === 'array') {
					$value = implode("  \n", array_keys($value));
				}
				$value = $this->_markdown_css . $this->_markdown->md2html($value, true, ["list", "emphasis", "fontsize", "linebreak"]);
			}
			$this->writeStandardHTML($commission);
		}
		return $this->return();
	}

	/**
	 * create a pdf for a label sheet with qr code and plain text  
	 * or label for label printer as selected or other available type as per config.ini  
	 * or an appointment handout  
	 * @param array $fileContent is an array of [qrcode content, written text beside]  
	 */
	public function qrcodePDF($fileContent){
		$this->init($fileContent);

		// override default cell padding
		$this->_pdf->setDefaultCellPadding(0, 0, 0, 0);

		$page = $this->_pdf->page->getPage();
		$columnwidth = intval( $page['region'][0]['RW'] / $this->_pageSetup['columns'] - $this->_pageSetup['column_gap']);
		$rowheight = intval( $page['region'][0]['RH'] / $this->_pageSetup['rows'] - $this->_pageSetup['row_gap']);
		$distributed_column_gap = $this->_pageSetup['columns'] > 1 ? ($this->_pageSetup['column_gap'] / ($this->_pageSetup['columns'] - 1) * $this->_pageSetup['columns']) : 0;
		$distributed_row_gap = $this->_pageSetup['rows'] > 1 ? ($this->_pageSetup['row_gap'] / ($this->_pageSetup['rows'] - 1) * $this->_pageSetup['rows']) : 0;

		$codesize = intval(min($columnwidth, $rowheight, $this->_pageSetup['codesizelimit'] ? : $rowheight * .7));
		for ($row = 0; $row < $this->_pageSetup['rows']; $row++){
			for ($column = 0; $column < $this->_pageSetup['columns']; $column++){
				$this->_pdf->page->addContent($this->_pdf->getBarcode(
					type: 'QRCODE,' . CONFIG['limits']['quality']['qr_errorlevel'],
					code: $fileContent['content'][0],
					posx: $column * ($columnwidth + $distributed_column_gap) + $page['region'][0]['RX'],
					posy: $row * ($rowheight + $distributed_row_gap) + $page['region'][0]['RY'],
					width: $codesize,
					height: $codesize,
					style: [
						'lineWidth' => 0,
						'lineCap' => 'butt',
						'lineJoin' => 'miter',
						'dashArray' => [],
						'dashPhase' => 0,
						'lineColor' => 'black',
						'fillColor' => 'black',
					]
				));
				$font = $this->_pdf->font->insert($this->_pdf->pon, 'freesans', '', $this->_pageSetup['fontsize']); // font size
				$this->_pdf->page->addContent($font['out']);
				$text = $this->_pdf->getTextCell(
					txt: $fileContent['content'][1],
					posx: $column * ($columnwidth + $distributed_column_gap) + $codesize + $this->_pageSetup['codepadding'] + $page['region'][0]['RX'],
					posy: $row * ($rowheight + $distributed_row_gap) + $page['region'][0]['RY'],
					width: $columnwidth  - $codesize - $this->_pageSetup['codepadding'],
					height: $rowheight,
					valign: 'T',
					halign: 'J'
				);
				$this->_pdf->page->addContent($text);
			}
		}

		return $this->return();
	}

	/**
	 * create a pdf for a record summary  
	 * @param array $fileContent see self::init()
	 */
	public function recordsPDF($fileContent){
		$this->init($fileContent);
		$init = true;
		if ($fileContent['erp_case_number']){
			$init = $this->writeStandardHTML([
				$this->_pdf->_lang->GET('record.erp_case_number', [], true) => $fileContent['erp_case_number']
			]);
		}
		
		foreach ($fileContent['content'] as $document => $entries){
			if (!empty(trim($document))) // write and set bookmark level 0
				$init = $this->writeStandardHTML([
					'<em>' . $document . '</em>' => ''
				], 0, $init);
			foreach ($entries as &$values){
				if (gettype($values) === 'array'){
					foreach ($values as &$value){
						preg_match("/(?:^href=')(.+?)(?:')(.*)/", $value, $link); // link widget value
						if ($link){
							$value = '<a href="' . $link[1] . '" class="eol1_md" target="_blank">' . $link[1] . '</a>' . ($link[2] ? $this->_markdown->md2html($link[2]): '');
							continue;
						}

						preg_match("/(.+?) ~\*\(.+?\)*~$/", $value, $link); // attachment value with contributor for full export
						if (!isset($link[1])) $link = [null, $value];  // attachment value without contributor for simplified export

						$possibleFiles = explode(', ', $link[1]);
						foreach($possibleFiles as $filename){
							// remove possible entry information on full exports
							$filename = preg_replace('/ --\*.+?\*--$/m', '', $filename);
							$filename = $this->_filehandler->translate_path(str_replace('\_', '_', $filename));
							if (!isset($fileContent['attachments'][$document]) || !in_array($filename, $fileContent['attachments'][$document])) {
								$value = $this->_markdown->md2html($value);
								continue;
							}
							$file = pathinfo($filename);
							if (in_array($file['extension'], ['jpg', 'jpeg', 'gif', 'png'])) {
								// inline image embedding
								$this->_filehandler->serve($filename, false);
								// sizing
								list($img_width, $img_height, $img_type, $img_attr) = getimagesize($filename);
								$ratio = $img_height ? $img_width / $img_height : 1; // prevent division by 0
								// scale to max settings if exceeding these 
								$out_height = $img_height > $this->_pageSetup['exportimage_maxheight'] ? $this->_pageSetup['exportimage_maxheight'] : $img_height;
								$out_width = $out_height * $ratio;
								$out_width = $out_width > $this->_pageSetup['exportimage_maxwidth'] ? $this->_pageSetup['exportimage_maxwidth'] : $out_width;
								$out_height = $out_width / $ratio;
								// approximately convert pixel to mm; weird factor by trial and error
								$out_width *= (72 / 25.4) * 1.35;
								$out_height *= (72 / 25.4) * 1.35;
								// realpath for tc-lib-pdf								
								$value = '<img src="' . realpath($filename) . '" width="' . $out_width . '" height="' . $out_height . '" class="eol1_md" /><br />' . $this->_markdown->md2html($value);
							}
						}
					}
					$values = $this->_markdown_css . implode('<br />', $values);
				}
				else {
					$values = $this->_markdown_css . $this->_markdown->md2html($values);
				}
			}
			$init = $this->writeStandardHTML($entries, 1, $init);
			if (!empty($fileContent['attachments'][$document])) $this->attachments($fileContent['attachments'][$document]);
		}

		if (isset($fileContent['recenthash'])) $this->writeStandardHTML([$this->_pdf->_lang->GET('record.verify.verify', [], true) => $fileContent['recenthash']], 0, $init);

		return $this->return();
	}

	/**
	 * create a pdf for a table output  
	 * @param array $fileContent see self::init()
	 */
	public function tablePDF($fileContent){
		// create a pdf for a table output
		$this->init($fileContent);
		if (array_is_list($fileContent['content'])) $fileContent['content'] = [$fileContent['content']];

		$page = 0;
		$init = true;
		foreach($fileContent['content'] as $header => $table){
			if ($page++) {
				$this->_pdf->AddPage();
				$init = true;
			}
			$page = $this->_pdf->page->getPage();
			$bbox = $init ? ['y' =>  $this->_pdf->_contentCoordinates['top'], 'h' => 0] : $this->_pdf->getLastBBox();
			$init = false;
			if (!array_is_list($fileContent['content'])){
				$this->_pdf->addHTMLCell(
					html: '<h3>' . $header . '</h3>',
					posx: $page['region'][0]['RX'],
					posy: $bbox['y'] + $bbox['h'],
					width: $page['region'][0]['RW'],
				);
				$page = $this->_pdf->page->getPage();
				$bbox = $this->_pdf->getLastBBox();
			}

			$html = <<<END
				<style>
					tr.odd {background-color:#ddd}
				</style>
			END;
			
			$html .= '<table>';
			$html .= '<thead><tr>';
			$headers = array_keys($table[array_key_first($table)]);
			foreach($headers as $column => $column_content){
				$html .= '<th' . (isset($fileContent['columns'][$column]) && $fileContent['columns'][$column] ? ' style="width:' . $fileContent['columns'][$column] . '"': '') . '>' . $column_content . '</th>';
			}
			$html .= '</tr></thead>';
	
			$html .=  '<tbody>';
			foreach ($table as $index => $row){
				$odd = $index % 2 ? ' class="odd"' : '';
				$html .= '<tr' . $odd . '>';
				foreach(array_values($row) as $column => $column_content) {
					$html .= '<td' . (isset($fileContent['columns'][$column]) && $fileContent['columns'][$column] ? ' style="width:' . $fileContent['columns'][$column] . '"': '') . '>' . $column_content . '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody>';
			$html .= '</table>';
			$this->_pdf->addHTMLCell(
				html: $html,
				posx: $page['region'][0]['RX'],
				posy: $bbox['y'] + $bbox['h'],
				width: $page['region'][0]['RW'],
			);
		}

		return $this->return();
	}

	/**
	 * create a timesheet output with pagebreaks per user
	 * custom css to ensure content fits on single page
	 * @param array $fileContent see self::init()
	 */
	public function timesheetPDF($fileContent){
		// create a pdf for a timesheet output
		$this->init($fileContent);
		$css = <<<END
		<style>
			td {
				border-right:1px solid #ddd;
				padding: 3px 5px;
				font-size: 10px;
			}
		</style>
		END;
		$page = 0;
		foreach ($fileContent['content'] as $user){
			if ($page++) $this->_pdf->AddPage();
			$table = '<table>';
			$user_name = $user[0][0][0];
			$user[0][0][0] = '';
			foreach ($user as $row){
				$key = isset($row[0]) ? $row[0][0] : '';
				$value = $row[1] ?? '';
				$table .= '<tr>';
				$table .= '<td style="width:20%; line-height:1;' . (isset($row[0]) && $row[0][1] ? 'color:grey;' : '') . '">';
				$table .= $key;
				$table .= '</td><td style="width:80%;">';
				$table .= $value;
				$table .= '</td>';
				$table .= '</tr>';
			}
			$table .= '</table>';
			$this->writeStandardHTML([$user_name => $css . $table]);
		}

		return $this->return();
	}
}

class RECORDTCPDF extends \Com\Tecnick\Pdf\Tcpdf {
	// custom pdf header and footer
	public int $qrcodesize = 0;
	public mixed $qrcodecontent = '';
	public array $header = [];
	public array $_pageSetup = [];
	public mixed $_defaultfont = null;
	public array $_contentCoordinates = [
		'top' => null,
		'bottom' => null
	];
	public mixed $_lang = null;

	public function __construct($pageSetup = [], $unicode = true, $qrcodesize = 20, $qrcodecontent = '', $header = ['title' => '', 'date' => '']){
		parent::__construct(
			unit: $pageSetup['unit'],
			isunicode: $unicode,
			compress: true
		);
		$this->_pageSetup = $pageSetup;
		$this->header = $header;
		$this->qrcodesize = $qrcodesize;
		$this->qrcodecontent = $qrcodecontent;
		$this->_lang = new LANG();

		$this->enableDefaultPageContent();
		$this->enableZeroWidthBreakPoints(true);
		$this->setViewerPreferences(['DisplayDocTitle' => true]);
		$this->setUserRights([
			'enabled'   => true,
			'document'  => '/FullSave',
			'form'      => '/FillIn /Export',
			'annots'    => '/Copy /Export',
			'ef'        => '/Import',
		]);
		$this->setDefaultCellMargin(0, 0, 0, 0);
		$this->font->insert($this->pon, 'helvetica', '', 10); // add helvetica, without there's an error message even if not actively used

		if ($this->_defaultfont === null) {
			$this->_defaultfont = $this->font->insert($this->pon, 'freesans', '', $this->_pageSetup['fontsize']); // add default font
		}
		
		// create a page with defaultPageContent to determine the top and bottom content boundaries after applying defaultPageContent()
		$pageSetup = [
			'orientation' => $this->_pageSetup['orientation'],
			'width' => is_array($this->_pageSetup['format']) ? $this->_pageSetup['format'][0] : null,
			'height' => is_array($this->_pageSetup['format']) ? $this->_pageSetup['format'][1] : null,
			'format' => !is_array($this->_pageSetup['format']) ? $this->_pageSetup['format'] : null
		];
		$page = $this->addPage($pageSetup);
		$this->page->pop();
		$pageSetup = array_merge($pageSetup, [
			'autobreak' => true,
			'margin' => [
				'PL' => $this->_pageSetup['marginleft'],
				'PR' => $this->_pageSetup['marginright'],
				'CT' => $this->_contentCoordinates['top'],
				'CB' => $page['height'] - $this->_contentCoordinates['bottom']
			],
	        'region' => [[
				'RX' =>  $this->_pageSetup['marginleft'],
				'RY' =>  $this->_contentCoordinates['top'],
				'RW' => $page['width'] - $this->_pageSetup['marginleft'] - $this->_pageSetup['marginright'],
				'RH' =>  $this->_contentCoordinates['bottom'] - $this->_contentCoordinates['top'],
			]],
		]);
		// add the actual first page with determined settings
		$page = $this->addPage($pageSetup);
	}

	/**
	 * Sets the page common content like Header and Footer.
	 * Override this method to add custom content to all pages.
	 *
	 * @param int $pid Page index. Omit or set it to -1 for the current page ID.
	 * @return string PDF output code.
	 */
	public function defaultPageContent(int $pid = -1): string
	{
		$page = $this->page->getPage();
		$this->setDefaultCellPadding(0, 3, 0, 3);

		$widths = [
			'headerimage' => 0,
			'identifier' => 0,
			'footerimage' => 0
		];
		$heights = [
			'header' => [0],
			'footer' => [$this->_pageSetup['footer'] ? 8 : 0]
		];

		$out = $this->graph->getStartTransform();
		$out .= $this->color->getPdfColor('black');

		// warning: this has taken me some hours:
		// $this->getLastBBox() returns granular, not necessarily the whole added element but the last added boundary box for a line

		// header
		// insert identifier to the left
		if ($this->qrcodecontent){
			$out .= $this->getBarcode(
				type: 'QRCODE,' . CONFIG['limits']['quality']['qr_errorlevel'],
				code: $this->qrcodecontent,
				posx: min(10, $this->_pageSetup['marginleft']),
				posy: $this->_pageSetup['margintop'],
				width: $this->qrcodesize,
				height: $this->qrcodesize,
				style: [
					'lineWidth' => 0,
					'lineCap' => 'butt',
					'lineJoin' => 'miter',
					'dashArray' => [],
					'dashPhase' => 0,
					'lineColor' => 'black',
					'fillColor' => 'black',
				]
			);
			$heights['header'][] = $this->qrcodesize + 6;

			$footerfont = $this->font->insert($this->pon, 'freesans', '', 8); // font size
			$out .= $footerfont['out'];
			$out .= $this->getTextCell(
				txt: $this->qrcodecontent,
				posx: 0 + $this->qrcodesize + min(10, $this->_pageSetup['marginleft']),
				posy: 0 + $this->_pageSetup['margintop'],
				width: 40,
				valign: 'T',
				halign: 'J',
			);
			$bbox = $this->getLastBBox();
			$heights['header'][] =  $bbox['y'] + $bbox['h'];
			$widths['identifier'] = $this->qrcodesize + 40 + 5;
		}

		// insert header image to the right
		if ($this->_pageSetup['header'] && $image = $this->_pageSetup['header_image'] ?? null){
			// given the image will always be 20mm high
			list($width, $height, $type, $attr) = getimagesize($image);
			if ($width && $height){ // avoid division by zero error for faulty input
				$header_image = $this->image->add(realpath($image));
				$out .= $this->image->getSetImage(
					$header_image,
					$page['width'] - $width / $height * 20 - $this->_pageSetup['marginright'],
					0 + $this->_pageSetup['margintop'],
					$width / $height * 20,
					20,
					$page['height']);
				$heights['header'][] = 0 + 20;
				$widths['headerimage'] = $width * 20 / $height + 5;
			}
		}

		// insert title into remaining space between
		$titleBox = null;
		if ($this->_pageSetup['header'] && $this->header['title']) {
			$titlefont = $this->font->insert($this->pon, 'freesans', 'B', 20); // font size
			$out .= $titlefont['out'];
			$out .= $this->getTextCell(
				txt: $this->header['title'],
				posx: min(10, $this->_pageSetup['marginleft']) + $widths['identifier'],
				posy: $this->_pageSetup['margintop'],
				width: max(40, $page['width'] - $widths['identifier'] - $widths['headerimage'] - min(10, $this->_pageSetup['marginleft']) - $this->_pageSetup['marginright']),
				halign: 'R',
				/*styles: [ // leave for occasionally future debugging
					'all' => [
						'lineWidth' => 1,
						'lineCap' => 'round',
						'lineJoin' => 'round',
						'miterLimit' => 1,
						'dashArray' => [],
						'dashPhase' => 0,
						'lineColor' => 'green',
						'fillColor' => 'yellow',
					],
				]*/
			);
			$titleBox = $this->getLastBBox();
			$heights['header'][] = $titleBox['y'] + $titleBox['h'];
		}
		// subtitle, typically a date goes right below the title 
		if ($this->header['date']){
			$this->_defaultfont = $this->font->insert($this->pon, 'freesans', '', 10); // add default font
			$out .= $this->_defaultfont['out'];
			$out .= $this->getTextCell(
				txt: $this->header['date'],
				posx: min(10, $this->_pageSetup['marginleft']) + $widths['identifier'],
				posy: $titleBox ? $titleBox['y'] + $titleBox['h'] : $this->_pageSetup['margintop'],
				width: max(40, $page['width'] - $widths['identifier'] - $widths['headerimage'] - min(10, $this->_pageSetup['marginleft']) - $this->_pageSetup['marginright']),
				linespace: -1,
				halign: 'R',
			);
			$bbox = $this->getLastBBox();
			$heights['header'][] = $bbox['y'] + $bbox['h'];
		}

		// footer
		// insert footer image to the right
		if ($image = $this->_pageSetup['footer_image'] ?? null){
			list($width, $height, $type, $attr) = getimagesize($image);
			if ($width && $height){ // avoid division by zero error for faulty input
				// given the image will always be 10mm high
				$footer_image = $this->image->add(realpath($image));
				$out .= $this->image->getSetImage(
					iid: $footer_image,
					xpos: $page['width'] - $width / $height * 10 - $this->_pageSetup['marginright'],
					ypos: $page['height'] - 10 - $this->_pageSetup['marginbottom'],
					width: $width / $height * 10,
					height: 10,
					pageheight: $page['height']);
				$widths['footerimage'] = $width * 10 / $height  + 3;
			}
			$heights['footer'][] = 10;
		}

		// insert footer text into the remaining space
		if ($this->_pageSetup['footer'] ?? false) {
			$footerfont = $this->font->insert($this->pon, 'freesans', '', 8); // font size
			$out .= $footerfont['out'];
			$out .= $this->getTextCell(
				txt: $this->_lang->GET('company.address', [], true) . ' | ' . CONFIG['system']['caroapp'],
				posx: $this->_pageSetup['marginleft'] + 20,
				posy: $page['height'] - 10 - $this->_pageSetup['marginbottom'],
				width: $page['width'] - $widths['footerimage'] - 20 - $this->_pageSetup['marginleft'] - $this->_pageSetup['marginright'],
				valign: 'B',
				halign: 'R',
			);
			$bbox = $this->getLastBBox();
			$heights['footer'][] = $bbox['h'] * 2;
		}

		$this->_defaultfont = $this->font->insert($this->pon, 'freesans', '', $this->_pageSetup['fontsize']); // add default font
		$out .= $this->_defaultfont['out'];
		$out .= $this->graph->getStopTransform();
		$this->setDefaultCellPadding(5, 3, 3, 3);

		// determine the max top and bottom y-coordinates for further use
		if (!$this->_contentCoordinates['top']) $this->_contentCoordinates = [
			'top' => max($heights['header']) + $this->_pageSetup['margintop'],
			'bottom' => $page['height'] - max($heights['footer']) - $this->_pageSetup['marginbottom']
		];
		$this->page->setY($this->_contentCoordinates['top'] ?: 0);
		return $out;
	}

	// write page numbers, call after all pages have been created
	// writes to bottom left
	public function pageNumeration(){
		$footerfont = $this->font->insert($this->pon, 'freesans', '', 8); // font size
		$pages = $this->page->getPages();
		foreach($pages as $pid => $page){
            $out = $this->color->getPdfColor('black');
			$out .= $footerfont['out'];
			$out .= $this->getTextCell(
				txt: strval($pid + 1) . ' / ' . strval(count($pages)),
				posx: $this->_pageSetup['marginleft'],
				posy: $page['height'] - 10 - $this->_pageSetup['marginbottom'],
				width: 20,
				height: 10,
				halign: 'L'
			);
			$this->page->addContent($out, $pid);
		}
	}
}

?>
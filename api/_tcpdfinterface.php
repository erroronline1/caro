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
require_once('./_filehandler.php');

class PDF{
	private $_pageSetup = [];
	private $_pdf = null;
	public $_pdo = null;
	public $_filehandler = null;
	private $_markdown = null;
	private $_markdown_css = null;
	private $_fileContent = [];

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
			'orientation' => isset($pageSetup['orientation']) && $pageSetup['orientation'] === 'landscape' ? 'L'  : 'P',
			'margintop' => isset($pageSetup['margintop']) ? intval($pageSetup['margintop']) : 10,
			'marginright' => isset($pageSetup['marginright']) ? intval($pageSetup['marginright']) : 10,
			'marginbottom' => isset($pageSetup['marginbottom']) ? intval($pageSetup['marginbottom']) : 10,
			'marginleft' => isset($pageSetup['marginleft']) ? intval($pageSetup['marginleft']) : 20,
			'header_image' => $pageSetup['header_image'] ?? null,
			'footer_image' => $pageSetup['footer_image'] ?? null,
			'exportimage_maxwidth' => isset($pageSetup['exportimage_maxwidth']) ? min(130, intval($pageSetup['exportimage_maxwidth'])) : 130,
			'exportimage_maxheight' => isset($pageSetup['exportimage_maxheight']) ? intval($pageSetup['exportimage_maxheight']) : 75,
			'rows' => isset($pageSetup['rows']) ? intval($pageSetup['rows']) : 1,
			'columns' => isset($pageSetup['columns']) ? intval($pageSetup['columns']) : 1,
			'fontsize' => isset($pageSetup['fontsize']) ? intval($pageSetup['fontsize']) : 12,
			'codesizelimit' => isset($pageSetup['codesizelimit']) ? intval($pageSetup['codesizelimit']) : null,
			'codepadding' => isset($pageSetup['codepadding']) ? intval($pageSetup['codepadding']) : 0,
			'header' => $pageSetup['header'] ?? true,
			'footer' => $pageSetup['footer'] ?? true,
		];
		$customsetup = preg_split('/\D{1,}/', $this->_pageSetup['format']);
		if (count($customsetup) > 1 && $customsetup[0] /*not line start*/){
			$this->_pageSetup['format'] = [$customsetup[0], $customsetup[1]];
		}

		$this->_pdo = $pdo;
		$this->_filehandler = new FILEHANDLER($pdo);

		$this->_markdown = new \erroronline1\Markdown\Markdown(true);
		$this->_markdown_css = <<<END
		<style>
			.eol1_odd {
				background-color: #eee;
			}
			td {
				border-right:1px solid #ddd;
				padding: 5px;
			}
			blockquote{
				border-left: 3px solid #ddd;
				margin-left: -5px;
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
	 *     'date' => 'generally used as such but can be any string to show up under the title'  
	 * ]
	 */
	private function init($fileContent){
		$this->_fileContent = $fileContent;

		// create new PDF document and set initial properties
		$this->_pdf = new RECORDTCPDF($this->_pageSetup, true, 
		20, $fileContent['identifier'] ?? null, ['title' => $fileContent['title'] ?? '', 'date' => $fileContent['date'] ?? '']);

		// set document information
		$this->_pdf->SetCreator(CONFIG['system']['caroapp']);
		$this->_pdf->SetAuthor($_SESSION['user']['name']);
		$this->_pdf->SetTitle($fileContent['title'] ?? '');
		$this->_pdf->setPDFFilename($fileContent['filename'] . '.pdf');
	}

	/**
	 * writes the prepared content chunks as name:content to the pages and sets bookmarks
	 * 
	 * @param array $chunks
	 * @param array $bookmarkLevel default 0 but can be overridden
	 */
	private function writeStandardHTML($chunks, $bookmarkLevel = 0){
		$init = true;
		for($i = 0; $i < count(array_keys($chunks)); $i++){
			$name = array_keys($chunks)[$i];
			$content = $chunks[$name];
			
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
	}

	/**
	 * append embedded files as attachments at the end of the document
	 * @param array $files
	 */
	private function attachments($files){
		if (empty($files)) return;

		$page = $this->_pdf->page->getPage();
		$bbox = $this->_pdf->getLastBBox();

		$attachment_caption = $this->_pdf->_lang->GET('record.file_attachments');
		$this->_pdf->setBookmark(
			name: $attachment_caption
		);
		$this->_pdf->addHTMLCell(
			html: '<h3>' . $attachment_caption . '</h3>',
			posx: $page['region'][0]['RX'],
			posy: $bbox['y'] + $bbox['h'],
			width: $page['region'][0]['RW'],
		);
		
		$page = $this->_pdf->page->getPage();
		$bbox = $this->_pdf->getLastBBox();

		foreach($files as $file){
			$this->_filehandler->serve($file, false);
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

		$_filehandler = new FILEHANDLER();
		$this->_pdf->savePDF(__DIR__.'/' .$_filehandler->directory('tmp'), $this->_pdf->getOutPDFString());
		return $_filehandler->directory('tmp') . '/' .$this->_fileContent['filename'] . '.pdf';
	}

	/**
	 * create audit files
	 * ensure to have html handled before passing, as this defaults to markdown
	 * @param array $fileContent see self::init()
	 */
	public function auditPDF($fileContent){
		$this->init($fileContent);

		// prepare content as html
		foreach ($fileContent['content'] as $key => &$value){

			// values column
			if (gettype($value) === 'array') {
				$value = implode("  \n", array_keys($value));
			}

			if (str_starts_with($value ?: '', '::CODE::')) {
				$value = '<pre>' . substr($value, 8) . '</pre>';
			}
			else {
				// mask filenames that otherwise my end up formatted with emphasis
				if (!empty($fileContent['files'])){
					foreach($fileContent['files'] as $file){
						$value = preg_replace_callback('/' . preg_quote($file, '/') . '/', function($match){
							return preg_replace('/_/', "\_", $match[0]);
						},
						$value);
					}
				}
				$value = $this->_markdown_css . $this->_markdown->md2html($value);
			}
		}
		$this->writeStandardHTML($fileContent['content']);
		if (!empty($fileContent['files'])) $this->attachments($fileContent['files']);

		return $this->return();
	}

	public function documentsPDF($fileContent){
		// create a pdf for a document export
		$this->init($fileContent);
		// set cell padding
		$this->_pdf->setDefaultCellPadding(5, 5, 5, 5);
		$_markdown = new \erroronline1\Markdown\Markdown(true);
		$_filehandler = new FILEHANDLER($this->_pdo);

		$this->_pdf->setFormDefaultProp(['lineWidth' => 0, 'borderStyle' => 'solid']);

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		// Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false, $alt=false, $altimgs=array())
		
		$height = [
			'multiline' => 31,
			'default' => 5
		];

		foreach ($fileContent['content'] as $document => $entries){
			$this->_pdf->setBookmark($document === ' ' && isset($this->_pdf->header['title']) ? $this->_pdf->header['title'] : $document , 0);
			$this->_pdf->SetFont('helvetica', '', $this->_pageSetup['fontsize'] + 2);
			$this->_pdf->MultiCell(145, 4, $document, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			foreach ($entries as $key => $value){
				// make sure to write on next page if multiline textfield would reach into footer
				if ($value['type'] === "multiline" && !$value['value']
					&& $this->_pdf->GetY() > $this->_pdf->getPageHeight() - $this->_pageSetup['marginbottom'] - $height['multiline']) {
						$this->_pdf->AddPage();
						$this->_pdf->SetY($this->_pageSetup['margintop']);
				}
				// make sure to write on next page if image would reach into footer
				if ($value['type'] === "image" && $value['value']
					&& $this->_pdf->GetY() > $this->_pdf->getPageHeight() - $this->_pageSetup['marginbottom'] - $this->_pageSetup['exportimage_maxheight']) {
						$this->_pdf->AddPage();
						$this->_pdf->SetY($this->_pageSetup['margintop']);
				}

				// version of components to be displayed smallish and ignoring the name column (component name for unique key)
				if ($value['type'] === 'version'){
					$this->_pdf->SetFont('helvetica', '', $this->_pageSetup['fontsize'] - 4);
					$this->_pdf->MultiCell(140, 4, $value['value'], 0, 'R', 0, 1, 60, $this->_pdf->GetY(), true, 0, false, true, 0, 'T', false);
					continue;
				}

				$this->_pdf->setBookmark($key, 1);
				// name column
				$this->_pdf->SetFont('helvetica', 'B', $this->_pageSetup['fontsize']);
				$nameLines = $this->_pdf->MultiCell(50, 4, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 100, 'T', false);
				$this->_pdf->applyCustomPageBreak($nameLines, $this->_pageSetup['fontsize']);

				// values column
				$this->_pdf->SetFont('helvetica', '', $this->_pageSetup['fontsize']);

				switch ($value['type']){
					case 'textsection':
						$textsectionLines = $this->_pdf->MultiCell(140, 4, $value['value'], 0, '', 0, 1, 60, $this->_pdf->GetY(), true, 0, false, true, 0, 'T', false);
						if ($nameLines>$textsectionLines) $this->_pdf->Ln($height['default'] + max([1, $nameLines]) * 5);
						break;
					case 'markdown':
						// writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=false, $reseth=true, $align='', $autopadding=true)
						$textsectionLines = $this->_pdf->writeHTMLCell(140, 4, 60, $this->_pdf->GetY(), $this->_markdown_css . $_markdown->md2html($value['value']), 0, 1, 0, true, '', true);
						if ($nameLines>$textsectionLines) $this->_pdf->Ln($height['default'] + max([1, $nameLines]) * 5);
						break;
					case 'image':
						if (isset($fileContent['images'][$document]) && in_array($value['value'], $fileContent['images'][$document])) {
							$value['value'] = '.' . str_ireplace('./api/api.php/file/stream/' , '', $value['value']);
							$_filehandler->serve($value['value'], false); // make available
							
							$imagedata = pathinfo($value['value']);
							list($img_width, $img_height, $img_type, $img_attr) = getimagesize($value['value']);
							$ratio = $img_height ? $img_width / $img_height : 1; // prevent division by 0
							$outputsize = [
								'width' => $ratio < 1 ? 0 : $this->_pageSetup['exportimage_maxwidth'],
								'height' => $ratio > 1 ? 0 : $this->_pageSetup['exportimage_maxheight']
							];
							$this->_pdf->SetFont('helvetica', 'B', $this->_pageSetup['fontsize']);
							$this->_pdf->MultiCell(50, $this->_pageSetup['exportimage_maxheight'], $imagedata['basename'], 0, '', 0, 0, 15, $this->_pdf->GetY() + $nameLines * 5, true, 0, false, true, 0, 'T', false);
							$this->_pdf->Image($value['value'], null, $this->_pdf->GetY(), $outputsize['width'], $outputsize['height'], '', '', 'R', true, 300, 'R');
							$this->_pdf->Ln(max($this->_pageSetup['exportimage_maxheight'], $outputsize['height']));
						}
						break;
					case 'selection':
						foreach ($value['value'] as $option){
							$this->_pdf->applyCustomPageBreak($nameLines, $this->_pageSetup['fontsize']);

							$this->_pdf->SetFontSize(14);
							$this->_pdf->CheckBox($option, 5, str_starts_with($option, '_____'), [], [], 'OK', 65, $this->_pdf->GetY() + 4);
							$this->_pdf->SetFontSize($this->_pageSetup['fontsize']);
							$this->_pdf->MultiCell(133, 4, (str_starts_with($option, '_____') ? substr($option, 5) : $option), 0, '', 0, 1, 67, $this->_pdf->GetY(), true, 0, false, true, 0, 'T', false);
							$this->_pdf->Ln(-7);
						}
						$this->_pdf->Ln(max([1, $nameLines - count($value['value'])]) * 5);
						break;
					case 'multiline':
						if ($value['value']) { // print value for missing field values on some systems
							$this->_pdf->SetFont('helvetica', 'I', $this->_pageSetup['fontsize']); // font size
							$this->_pdf->MultiCell(140, 4, $value['value'], 0, '', 0, 1, 60, $this->_pdf->GetY(), true, 0, false, true, 0, 'T', false);
							$this->_pdf->SetFont('helvetica', '', $this->_pageSetup['fontsize']); // font size
							break;
						}
						$this->_pdf->SetFontSize(0); // variable font size
						$this->_pdf->TextField($key, 133, $height['multiline'], ['multiline' => true, 'lineWidth' => 0, 'borderStyle' => 'none'], ['v' => $value['value'], 'dv' => $value['value']], 65, $this->_pdf->GetY() + 4);
						$this->_pdf->Ln($height['multiline'] + max([1, $nameLines]) * 5);
						break;
					case 'links':
						if ($value['value']) { // print value for missing field values on some systems
							$this->_pdf->SetFont('helvetica', 'I', $this->_pageSetup['fontsize']); // font size
							foreach ($value['value'] as $link){
								if ($link) {
									// writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=false, $reseth=true, $align='', $autopadding=true)
									$this->_pdf->writeHTMLCell(140, 4, 60, $this->_pdf->GetY(), '<a href="' . $link . '" target="_blank">' . $link . '</a>', 0, 1, 0, true, '', true);
								}
							}
							$this->_pdf->SetFont('helvetica', '', $this->_pageSetup['fontsize']); // font size
							break;
						}
						$this->_pdf->SetFontSize(0); // variable font size
						$this->_pdf->TextField($key, 133, $height['default'], [], ['v' => $value['value'], 'dv' => $value['value']], 65, $this->_pdf->GetY() + 4);
						$this->_pdf->Ln($height['default'] + max([1, $nameLines]) * 5);
						break;
					default:
						if ($value['value']) { // print value for missing field values on some systems
							$this->_pdf->SetFont('helvetica', 'I', $this->_pageSetup['fontsize']); // font size
							preg_match("/(?:^href=')(.+?)(?:')(.*)/", $value['value'], $link); // link widget value
							if ($link) {
								// writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=false, $reseth=true, $align='', $autopadding=true)
								$this->_pdf->writeHTMLCell(140, 4, 60, $this->_pdf->GetY(), '<a href="' . $link[1] . '" target="_blank">' . $link[1] . '</a>' . ($link[2] ? : ''), 0, 1, 0, true, '', true);
							}
							// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
							else $this->_pdf->MultiCell(140, 4, $value['value'], 0, '', 0, 1, 60, $this->_pdf->GetY(), true, 0, false, true, 0, 'T', false);
							$this->_pdf->SetFont('helvetica', '', $this->_pageSetup['fontsize']); // font size
							$this->_pdf->Ln(($height['default'] + max([1, $nameLines])));
							break;
						}
						$this->_pdf->SetFontSize(0); // variable font size
						$this->_pdf->TextField($key, 133, $height['default'], [], ['v' => $value['value'], 'dv' => $value['value']], 65, $this->_pdf->GetY() + 4);
						$this->_pdf->Ln(($height['default'] + max([1, $nameLines]) * 5));
				}
			}
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
				$value = $this->_markdown_css . $this->_markdown->md2html($value, true, ["list", "emphasis", "larger", "linebreak"]);
			}
			$this->writeStandardHTML($commission);
		}
		return $this->return();
	}

	/**
	 * create a pdf for a label sheet with qr code and plain text  
	 * or label for label printer as selected or other available type as per config.ini  
	 * or an appointment handout  
	 * $fileContent['content'] is an array of [qrcode content, written text beside]  
	 * @param array $fileContent
	 * @param float $fontSize defaults to 8 but can be overridden, e.g. by calendar appointment handout
	 */
	public function qrcodePDF($fileContent, $fontSize = 8){
		$this->init($fileContent);

		// override default cell padding
		$this->_pdf->setDefaultCellPadding(1, 1, 1, 1);

		$page = $this->_pdf->page->getPage();
		//var_dump($page);
		$columnwidth = intval(($page['width'] - ($this->_pageSetup['marginleft'] + $this->_pageSetup['marginright'])) / $this->_pageSetup['columns']);
		$rowheight = intval(($page['height'] - ($this->_pageSetup['margintop'] + $this->_pageSetup['marginbottom'])) / $this->_pageSetup['rows']);

		$codesize = intval(min($columnwidth, $rowheight, $this->_pageSetup['codesizelimit'] ? : $rowheight * .7));
		for ($row = 0; $row < $this->_pageSetup['rows']; $row++){
			for ($column = 0; $column < $this->_pageSetup['columns']; $column++){
				$this->_pdf->page->addContent($this->_pdf->getBarcode(
					type: 'QRCODE,' . CONFIG['limits']['quality']['qr_errorlevel'],
					code: $fileContent['content'][0],
					posx: $column * $columnwidth + $page['region'][0]['RX'],//$this->_pageSetup['marginleft'],
					posy: $row * $rowheight + $page['region'][0]['RY'], //$this->_pageSetup['margintop'],
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
				$font = $this->_pdf->font->insert($this->_pdf->pon, 'helvetica', '', $fontSize); // font size
				$this->_pdf->page->addContent($font['out']);
				$text = $this->_pdf->getTextCell(
					txt: $fileContent['content'][1],
					posx: $column * $columnwidth + $codesize + $this->_pageSetup['codepadding'] + $page['region'][0]['RX'],
					posy: $row * $rowheight + $page['region'][0]['RY'], //$this->_pageSetup['margintop'],
					width: $columnwidth - $codesize - $this->_pageSetup['codepadding'],
					height: $rowheight,
					valign: 'T',
					halign: 'J'
				);
				$this->_pdf->page->addContent($text);
			}
		}

		return $this->return();
	}

	public function recordsPDF($fileContent){
		$_lang = new LANG();
		$_filehandler = new FILEHANDLER($this->_pdo);
		// create a pdf for a record summary
		$this->init($fileContent);
		// set cell padding
		$this->_pdf->setDefaultCellPadding(5, 5, 5, 5);

		if ($fileContent['erp_case_number']){
			// name column
			$this->_pdf->SetFont('helvetica', 'B', $this->_pageSetup['fontsize']);
			$this->_pdf->MultiCell(50, 4, $_lang->GET('record.erp_case_number', [], true), 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
			// values column
			$this->_pdf->SetFont('helvetica', '', $this->_pageSetup['fontsize']);
			$this->_pdf->MultiCell(140, 4, $fileContent['erp_case_number'], 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
		}

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		// Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false, $alt=false, $altimgs=array())
		foreach ($fileContent['content'] as $document => $entries){
			$this->_pdf->setBookmark($document === ' ' && isset($this->_pdf->header['title']) ? $this->_pdf->header['title'] : $document , 0);
			$this->_pdf->SetFont('helvetica', '', $this->_pageSetup['fontsize'] + 2); 
			$this->_pdf->MultiCell(140, 4, $document, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			foreach ($entries as $key => $values){
				$this->_pdf->setBookmark($key, 1);
				// name column
				$this->_pdf->SetFont('helvetica', 'B', $this->_pageSetup['fontsize']);
				$nameLines = $this->_pdf->MultiCell(50, 4, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
				$this->_pdf->applyCustomPageBreak($nameLines, $this->_pageSetup['fontsize']);
				
				// values column
				$this->_pdf->SetFont('helvetica', '', $this->_pageSetup['fontsize']);
				$valueLines = 0;
				if (gettype($values) === 'array'){
					foreach ($values as $value){
						preg_match("/(?:^href=')(.+?)(?:')/", $value, $link); // link widget value
						if ($link){
							// writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=false, $reseth=true, $align='', $autopadding=true)
							$valueLines += $this->_pdf->writeHTMLCell(140, 4, 60, $this->_pdf->GetY(), '<a href="' . $link[1] . '" target="_blank">' . $link[1] . '</a>' . ($link[2] ? : ''), 0, 1, 0, true, '', true);
							continue;
						}
						preg_match("/(.+?) (\(.+?\))$/", $value, $link); // attachment value with contributor for full export
						if (!isset($link[1])) $link = [null, $value];  // attachment value without contributor for simplified export

						$possibleFiles = explode(', ', $link[1]);
						if (isset($fileContent['attachments'][$document]) && array_intersect($possibleFiles, $fileContent['attachments'][$document])){
							foreach($possibleFiles as $filename){
								$path = $_filehandler->directory('record_attachments') . '/' . $filename;
								$_filehandler->serve($path, false);
								$file = pathinfo($path);
								if (in_array($file['extension'], ['jpg', 'jpeg', 'gif', 'png'])) {
									// inline image embedding
									$valueLines += $this->_pdf->MultiCell(140, 4, $filename, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
									list($img_width, $img_height, $img_type, $img_attr) = getimagesize($path); 
									$ratio = $img_height ? $img_width / $img_height : 1; // prevent division by 0
									$outputsize = [
										'width' => $ratio < 1 ? 0 : $this->_pageSetup['exportimage_maxwidth'],
										'height' => $ratio > 1 ? 0 : $this->_pageSetup['exportimage_maxheight']
									];
									$this->_pdf->Image($path, null, $this->_pdf->GetY() + 6, $outputsize['width'], $outputsize['height'], '', '', 'R', true, 300, 'R');
									$valueLines += $this->_pdf->Ln(max($this->_pageSetup['exportimage_maxheight'], $outputsize['height']));
								}
								else {
									// file attachment
									$valueLines += $this->_pdf->MultiCell(140, 4, $filename, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
									// Annotation($x, $y, $w, $h, $text, $opt=array('Subtype'=>'Text'), $spaces=0)
									$this->_pdf->Annotation($this->_pdf->getPageWidth() - $this->_pageSetup['marginleft'] + 5, $this->_pdf->GetY() - $this->_pageSetup['fontsize'] * 1.5 , 10, 10, $_lang->GET('record.export_pdf_attachment', [], true) . ' ' . $value, array('Subtype'=>'FileAttachment', 'Name' => 'PushPin', 'FS' => $path));
								}	
							}
						}
						// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
						else $valueLines += $this->_pdf->MultiCell(140, 4, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
					}
				}
				elseif (str_starts_with($values, '::MARKDOWN::')){
					// textsection on full export with enabled markdown for documents textsection widget
					// with prefix PDF can decide better over HTMLCell vs MultiCell
					$_markdown = new \erroronline1\Markdown\Markdown(true);
					$valueLines = $this->_pdf->writeHTMLCell(140, 4, 60, $this->_pdf->GetY(), $this->_markdown_css . $_markdown->md2html(substr($values, 12)), 0, 1, 0, true, '', true);
				}
				else $this->_pdf->MultiCell(140, 4, $values, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);

				$offset = $valueLines < $nameLines ? $nameLines - 1 : 0;
				$this->_pdf->Ln(($offset - 1) * $this->_pageSetup['fontsize'] / 2);
			}
		}

		$this->_pdf->SetFont('helvetica', '', 8); 
		if (isset($fileContent['recenthash'])) $this->_pdf->MultiCell(140, 4, $fileContent['recenthash'], 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);

		return $this->return();
	}

	public function tablePDF($fileContent){
		// create a pdf for a table output
		$this->init($fileContent);
		// set cell padding
		$this->_pdf->setDefaultCellPadding(5, 1, 5, 1);
		
		if (array_is_list($fileContent['content'])) $fileContent['content'] = [$fileContent['content']];

		$page = 0;
		foreach($fileContent['content'] as $header => $table){
			if ($page++) $this->_pdf->AddPage();
			$this->_pdf->SetFont('helvetica', '', 16); // font size
			if (!array_is_list($fileContent['content'])) $this->_pdf->MultiCell(0, 4, $header, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			$this->_pdf->SetFont('helvetica', '', 8); // font size
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
			// writeHTML($html, $ln=true, $fill=false, $reseth=false, $cell=false, $align='')			
			$this->_pdf->writeHTML($html);
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
	public $qrcodesize = null;
	public $qrcodecontent = null;
	public $header = null;
	public $_pageSetup = null;
	public $_defaultfont = null;
	public $_contentCoordinates = [
		'top' => null,
		'bottom' => null
	];
	public $_lang = null;

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
		if ($this->_defaultfont === null) {
			$this->_defaultfont = $this->font->insert($this->pon, 'helvetica', '', 10); // add default font
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

	// forces pagebreak if content exceeds name or page height
	public function applyCustomPageBreak() {

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

			$footerfont = $this->font->insert($this->pon, 'helvetica', '', 8); // font size
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
			$titlefont = $this->font->insert($this->pon, 'helvetica', 'B', 20); // font size
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
			$this->_defaultfont = $this->font->insert($this->pon, 'helvetica', '', 10); // add default font
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
			$footerfont = $this->font->insert($this->pon, 'helvetica', '', 8); // font size
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
			$heights['footer'][] = $bbox['h'];
		}

		$this->_defaultfont = $this->font->insert($this->pon, 'helvetica', '', 10); // add default font
		$out .= $this->_defaultfont['out'];
		$out .= $this->graph->getStopTransform();
		$this->setDefaultCellPadding(5, 3, 3, 3);

		// determine the max top and bottom y-coordinates for further use
		if (!$this->_contentCoordinates['top']) $this->_contentCoordinates = [
			'top' => max($heights['header']) + $this->_pageSetup['margintop'],
			'bottom' => $page['height'] - max($heights['footer']) - $this->_pageSetup['marginbottom'] - 10
		];
		$this->page->setY($this->_contentCoordinates['top'] ?: 0);
		return $out;
	}

	// write page numbers, call after all pages have been created
	// writes to bottom left
	public function pageNumeration(){
		$footerfont = $this->font->insert($this->pon, 'helvetica', '', 8); // font size
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
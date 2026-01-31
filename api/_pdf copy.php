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

//require_once('../vendor/TCPDF/tcpdf.php');
error_reporting(E_ALL);
class PDF{
	private $_setup = [];
	private $_pdf = null;

	public function __construct($setup){
		$this->_setup = [
			'format' => $setup['format'] ?? 'A4',
			'unit' => $setup['unit'] ?? PDF_UNIT,
			'orientation' => $setup['orientation'] ?? PDF_PAGE_ORIENTATION,
			'margintop' => isset($setup['margintop']) ? intval($setup['margintop']) : 30,
			'marginright' => isset($setup['marginright']) ? intval($setup['marginright']) : 15,
			'marginbottom' => isset($setup['marginbottom']) ? intval($setup['marginbottom']) : 20,
			'marginleft' => isset($setup['marginleft']) ? intval($setup['marginleft']) : 20,
			'header_image' => $setup['header_image'] ?? null,
			'footer_image' => $setup['footer_image'] ?? null,
			'exportimage_maxwidth' => isset($setup['exportimage_maxwidth']) ? min(130, intval($setup['exportimage_maxwidth'])) : 130,
			'exportimage_maxheight' => isset($setup['exportimage_maxheight']) ? intval($setup['exportimage_maxheight']) : 75,
			'rows' => isset($setup['rows']) ? intval($setup['rows']) : 1,
			'columns' => isset($setup['columns']) ? intval($setup['columns']) : 1,
			'fontsize' => isset($setup['fontsize']) ? intval($setup['fontsize']) : 12,
			'codesizelimit' => isset($setup['codesizelimit']) ? intval($setup['codesizelimit']) : null,
			'codepadding' => isset($setup['codepadding']) ? intval($setup['codepadding']) : 0,
			'header' => $setup['header'] ?? true,
			'footer' => $setup['footer'] ?? true,
		];
		$customsetup = preg_split('/\D{1,}/', $this->_setup['format']);
		if (count($customsetup) > 1 && $customsetup[0] /*not line start*/){
			$this->_setup['format'] = [$customsetup[0], $customsetup[1]];
		}
	}

	private function init($content){
		// create new PDF document and set initial properties
		$this->_pdf = new RECORDTCPDF($this->_setup, true, 'UTF-8', false, false,
		20, $content['identifier'] ?? null, ['title' => $content['title'] ?? '', 'date' => $content['date'] ?? '']);

		// set document information
		$this->_pdf->SetCreator(CONFIG['system']['caroapp']);
		$this->_pdf->SetAuthor($_SESSION['user']['name']);
		$this->_pdf->SetTitle($content['title']);

		// set margins
		if ($this->_setup['header']){
			$this->_pdf->SetMargins($this->_setup['marginleft'], PDF_MARGIN_HEADER + $this->_setup['margintop'], $this->_setup['marginright'], true);
			$this->_pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		} else {
			$this->_pdf->setPrintHeader(false);
		}
		if ($this->_setup['footer']){
			$this->_pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		} else {
			$this->_pdf->setPrintFooter(false);
		}
		// set auto page breaks
		$this->_pdf->SetAutoPageBreak(TRUE, $this->_setup['marginbottom']); // margin bottom
		// add a page
		$this->_pdf->AddPage();
		// set cell margins
		$this->_pdf->setCellMargins(0, 0, 0, 0);
		// set color for background
		$this->_pdf->SetFillColor(255, 255, 255);
	}

	private function return($content){
		// export pdf to temp and return link
		// move pointer to last page
		$this->_pdf->lastPage();
		$this->_pdf->setProtection(['modify'], '', null, 1);

		$this->_pdf->Output(__DIR__ . '/' . UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 'F');
		return substr(UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 1);
	}

	public function auditPDF($content){
		// create a pdf for a record summary
		$this->init($content);
		// set cell padding
		$this->_pdf->setCellPaddings(5, 5, 5, 5);
		$markdown = new MARKDOWN();

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		
		foreach ($content['content'] as $key => $value){
			$this->_pdf->Bookmark($key, 0);
			// name column
			$this->_pdf->SetFont('helvetica', 'B', $this->_setup['fontsize']);
			$nameLines = $this->_pdf->MultiCell(50, 4, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
			$this->_pdf->applyCustomPageBreak($nameLines, $this->_setup['fontsize']);

			// values column
			$this->_pdf->SetFont('helvetica', '', $this->_setup['fontsize']);
			if (gettype($value) === 'array') {
				$value = implode("  \n", array_keys($value));
			}

			// writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=false, $reseth=true, $align='', $autopadding=true)
			$valueLines = $this->_pdf->writeHTMLCell(145, 4, 60, $this->_pdf->GetY(), $markdown->md2html($value), 0, 1, 0, true, '', true);
			//$valueLines = $this->_pdf->MultiCell(145, 4, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);

			$offset = $valueLines < $nameLines ? $nameLines - 1 : 0;
			$this->_pdf->Ln(($offset - 1) * $this->_setup['fontsize'] / 2);
		}
		if (isset ($content['files'])){
			$_lang = new LANG();
			foreach($content['files'] as $file){
				// file attachment
				$this->_pdf->MultiCell(140, 4, $file, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
				// Annotation($x, $y, $w, $h, $text, $opt=array('Subtype'=>'Text'), $spaces=0)
				$this->_pdf->Annotation($this->_pdf->getPageWidth() - $this->_setup['marginleft'] + 5, $this->_pdf->GetY() - $this->_setup['fontsize'] * 1.5 , 10, 10, $_lang->GET('record.export_pdf_attachment', [], true) . ' ' . $file, array('Subtype'=>'FileAttachment', 'Name' => 'PushPin', 'FS' => '.' . $file));
			}
		}

		return $this->return($content);
	}

	public function documentsPDF($content){
		// create a pdf for a document export
		$this->init($content);
		// set cell padding
		$this->_pdf->setCellPaddings(5, 5, 5, 5);
		$markdown = new MARKDOWN();

		$this->_pdf->setFormDefaultProp(['lineWidth' => 0, 'borderStyle' => 'solid']);

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		// Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false, $alt=false, $altimgs=array())
		
		$height = [
			'multiline' => 31,
			'default' => 5
		];

		foreach ($content['content'] as $document => $entries){
			$this->_pdf->Bookmark($document === ' ' && isset($this->_pdf->header['title']) ? $this->_pdf->header['title'] : $document , 0);
			$this->_pdf->SetFont('helvetica', '', $this->_setup['fontsize'] + 2);
			$this->_pdf->MultiCell(145, 4, $document, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			foreach ($entries as $key => $value){
				// make sure to write on next page if multiline textfield would reach into footer
				if ($value['type'] === "multiline" && !$value['value']
					&& $this->_pdf->GetY() > $this->_pdf->getPageHeight() - $this->_setup['marginbottom'] - $height['multiline']) {
						$this->_pdf->AddPage();
						$this->_pdf->SetY($this->_setup['margintop']);
				}
				// make sure to write on next page if image would reach into footer
				if ($value['type'] === "image" && $value['value']
					&& $this->_pdf->GetY() > $this->_pdf->getPageHeight() - $this->_setup['marginbottom'] - $this->_setup['exportimage_maxheight']) {
						$this->_pdf->AddPage();
						$this->_pdf->SetY($this->_setup['margintop']);
				}

				// version of components to be displayed smallish and ignoring the name column (component name for unique key)
				if ($value['type'] === 'version'){
					$this->_pdf->SetFont('helvetica', '', $this->_setup['fontsize'] - 4);
					$this->_pdf->MultiCell(140, 4, $value['value'], 0, 'R', 0, 1, 60, $this->_pdf->GetY(), true, 0, false, true, 0, 'T', false);
					continue;
				}

				$this->_pdf->Bookmark($key, 1);
				// name column
				$this->_pdf->SetFont('helvetica', 'B', $this->_setup['fontsize']);
				$nameLines = $this->_pdf->MultiCell(50, 4, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 100, 'T', false);
				$this->_pdf->applyCustomPageBreak($nameLines, $this->_setup['fontsize']);

				// values column
				$this->_pdf->SetFont('helvetica', '', $this->_setup['fontsize']);

				switch ($value['type']){
					case 'textsection':
						$textsectionLines = $this->_pdf->MultiCell(140, 4, $value['value'], 0, '', 0, 1, 60, $this->_pdf->GetY(), true, 0, false, true, 0, 'T', false);
						if ($nameLines>$textsectionLines) $this->_pdf->Ln($height['default'] + max([1, $nameLines]) * 5);
						break;
					case 'markdown':
						// writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=false, $reseth=true, $align='', $autopadding=true)
						$textsectionLines = $this->_pdf->writeHTMLCell(140, 4, 60, $this->_pdf->GetY(), $markdown->md2html($value['value']), 0, 1, 0, true, '', true);
						if ($nameLines>$textsectionLines) $this->_pdf->Ln($height['default'] + max([1, $nameLines]) * 5);
						break;
					case 'image':
						if (isset($content['images'][$document]) && in_array($value['value'], $content['images'][$document])) {
							$value['value'] = str_ireplace('./api/api.php/file/stream/' , '', $value['value']);
							$imagedata = pathinfo($value['value']);
							list($img_width, $img_height, $img_type, $img_attr) = getimagesize('.' . $value['value']);
							$ratio = $img_height ? $img_width / $img_height : 1; // prevent division by 0
							$outputsize = [
								'width' => $ratio < 1 ? 0 : $this->_setup['exportimage_maxwidth'],
								'height' => $ratio > 1 ? 0 : $this->_setup['exportimage_maxheight']
							];
							$this->_pdf->SetFont('helvetica', 'B', $this->_setup['fontsize']);
							$this->_pdf->MultiCell(50, $this->_setup['exportimage_maxheight'], $imagedata['basename'], 0, '', 0, 0, 15, $this->_pdf->GetY() + $nameLines * 5, true, 0, false, true, 0, 'T', false);
							$this->_pdf->Image('.' . $value['value'], null, $this->_pdf->GetY(), $outputsize['width'], $outputsize['height'], '', '', 'R', true, 300, 'R');
							$this->_pdf->Ln(max($this->_setup['exportimage_maxheight'], $outputsize['height']));
						}
						break;
					case 'selection':
						foreach ($value['value'] as $option){
							$this->_pdf->applyCustomPageBreak($nameLines, $this->_setup['fontsize']);

							$this->_pdf->SetFontSize(14);
							$this->_pdf->CheckBox($option, 5, str_starts_with($option, '_____'), [], [], 'OK', 65, $this->_pdf->GetY() + 4);
							$this->_pdf->SetFontSize($this->_setup['fontsize']);
							$this->_pdf->MultiCell(133, 4, (str_starts_with($option, '_____') ? substr($option, 5) : $option), 0, '', 0, 1, 67, $this->_pdf->GetY(), true, 0, false, true, 0, 'T', false);
							$this->_pdf->Ln(-7);
						}
						$this->_pdf->Ln(max([1, $nameLines - count($value['value'])]) * 5);
						break;
					case 'multiline':
						if ($value['value']) { // print value for missing field values on some systems
							$this->_pdf->SetFont('helvetica', 'I', $this->_setup['fontsize']); // font size
							$this->_pdf->MultiCell(140, 4, $value['value'], 0, '', 0, 1, 60, $this->_pdf->GetY(), true, 0, false, true, 0, 'T', false);
							$this->_pdf->SetFont('helvetica', '', $this->_setup['fontsize']); // font size
							break;
						}
						$this->_pdf->SetFontSize(0); // variable font size
						$this->_pdf->TextField($key, 133, $height['multiline'], ['multiline' => true, 'lineWidth' => 0, 'borderStyle' => 'none'], ['v' => $value['value'], 'dv' => $value['value']], 65, $this->_pdf->GetY() + 4);
						$this->_pdf->Ln($height['multiline'] + max([1, $nameLines]) * 5);
						break;
					case 'links':
						if ($value['value']) { // print value for missing field values on some systems
							$this->_pdf->SetFont('helvetica', 'I', $this->_setup['fontsize']); // font size
							foreach ($value['value'] as $link){
								if ($link) {
									// writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=false, $reseth=true, $align='', $autopadding=true)
									$this->_pdf->writeHTMLCell(140, 4, 60, $this->_pdf->GetY(), '<a href="' . $link . '" target="_blank">' . $link . '</a>', 0, 1, 0, true, '', true);
								}
							}
							$this->_pdf->SetFont('helvetica', '', $this->_setup['fontsize']); // font size
							break;
						}
						$this->_pdf->SetFontSize(0); // variable font size
						$this->_pdf->TextField($key, 133, $height['default'], [], ['v' => $value['value'], 'dv' => $value['value']], 65, $this->_pdf->GetY() + 4);
						$this->_pdf->Ln($height['default'] + max([1, $nameLines]) * 5);
						break;
					default:
						if ($value['value']) { // print value for missing field values on some systems
							$this->_pdf->SetFont('helvetica', 'I', $this->_setup['fontsize']); // font size
							preg_match("/(?:^href=')(.+?)(?:')(.*)/", $value['value'], $link); // link widget value
							if ($link) {
								// writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=false, $reseth=true, $align='', $autopadding=true)
								$this->_pdf->writeHTMLCell(140, 4, 60, $this->_pdf->GetY(), '<a href="' . $link[1] . '" target="_blank">' . $link[1] . '</a>' . ($link[2] ? : ''), 0, 1, 0, true, '', true);
							}
							// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
							else $this->_pdf->MultiCell(140, 4, $value['value'], 0, '', 0, 1, 60, $this->_pdf->GetY(), true, 0, false, true, 0, 'T', false);
							$this->_pdf->SetFont('helvetica', '', $this->_setup['fontsize']); // font size
							$this->_pdf->Ln(($height['default'] + max([1, $nameLines])));
							break;
						}
						$this->_pdf->SetFontSize(0); // variable font size
						$this->_pdf->TextField($key, 133, $height['default'], [], ['v' => $value['value'], 'dv' => $value['value']], 65, $this->_pdf->GetY() + 4);
						$this->_pdf->Ln(($height['default'] + max([1, $nameLines]) * 5));
				}
			}
		}

		return $this->return($content);
	}

	public function orderPDF($content){
		// create a pdf for order output with pagebreaks per organizational unit for delivery notes
		$this->init($content);
		// set cell padding
		$this->_pdf->setCellPaddings(5, 1, 5, 1);
		$markdown = new MARKDOWN();

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		$this->_pdf->SetFont('helvetica', '', 8); // font size
		
		$page = 0;
		foreach ($content['content'] as $commission){
			//$this->_pdf->startPageGroup();
			if ($page++)$this->_pdf->AddPage();
			foreach ($commission as $key => $value){
				// name column
				$this->_pdf->SetFont('helvetica', 'B', $this->_setup['fontsize']);
				$nameLines = $this->_pdf->MultiCell(50, 4, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
				$this->_pdf->applyCustomPageBreak($nameLines, $this->_setup['fontsize']);

				// values column
				$this->_pdf->SetFont('helvetica', '', $this->_setup['fontsize']);
				if (gettype($value) === 'array') {
					$value = implode("  \n", array_keys($value));
				}

				// writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=false, $reseth=true, $align='', $autopadding=true)
				$valueLines = $this->_pdf->writeHTMLCell(145, 4, 60, $this->_pdf->GetY(), $markdown->md2html($value), 0, 1, 0, true, '', true);
				//$valueLines = $this->_pdf->MultiCell(145, 4, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);

				$offset = $valueLines < $nameLines ? $nameLines - 1 : 0;
				$this->_pdf->Ln(($offset - 1) * $this->_setup['fontsize'] / 2);
			}
		}

		return $this->return($content);
	}

	public function qrcodePDF($content){
		// create a pdf for a label sheet with qr code and plain text
		// or label for label printer as selected or other available type as per config.ini
		// $content['content'] is an array of [qrcode content, written text beside]
		$this->init($content);

		// set cell padding
		$this->_pdf->setCellPaddings(0, 0, 0, 0);

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		$format = [$this->_pdf->getPageWidth(), $this->_pdf->getPageheight()];
		$columnwidth = ($format[0] - ($this->_setup['marginleft'] + $this->_setup['marginright'])) / $this->_setup['columns'];
		$rowheight = ($format[1] - ($this->_setup['margintop'] + $this->_setup['marginbottom'])) / $this->_setup['rows'];

		$codesize = min($columnwidth, $rowheight, $this->_setup['codesizelimit'] ? : $rowheight);
		$style = array(
			'border' => 0,
			'vpadding' => 'auto',
			'hpadding' => 'auto',
			'fgcolor' => array(0,0,0),
			'bgcolor' => false, //array(255,255,255)
			'module_width' => 1, // width of a single module in points
			'module_height' => 1 // height of a single module in points
		);
		for ($row = 0; $row < $this->_setup['rows']; $row++){
			for ($column = 0; $column < $this->_setup['columns']; $column++){
				$this->_pdf->write2DBarcode($content['content'][0], 'QRCODE,' . CONFIG['limits']['qr_errorlevel'], $column * $columnwidth + $this->_setup['marginleft'] , $row * $rowheight + $this->_setup['margintop'], $codesize, $codesize, $style, 'N');
				$this->_pdf->MultiCell($columnwidth - $codesize - $this->_setup['codepadding'], $rowheight, $content['content'][1], 0, '', 0, intval($column === $this->_setup['columns'] - 1), $column * $columnwidth + $codesize + $this->_setup['marginleft'] + $this->_setup['codepadding'], $row * $rowheight + $this->_setup['margintop'], true, 0, false, true, 24, 'T', true);
			}
		}

		return $this->return($content);
	}

	public function recordsPDF($content){
		$_lang = new LANG();
		// create a pdf for a record summary
		$this->init($content);
		// set cell padding
		$this->_pdf->setCellPaddings(5, 5, 5, 5);

		if ($content['erp_case_number']){
			// name column
			$this->_pdf->SetFont('helvetica', 'B', $this->_setup['fontsize']);
			$this->_pdf->MultiCell(50, 4, $_lang->GET('record.erp_case_number', [], true), 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
			// values column
			$this->_pdf->SetFont('helvetica', '', $this->_setup['fontsize']);
			$this->_pdf->MultiCell(140, 4, $content['erp_case_number'], 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
		}

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		// Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false, $alt=false, $altimgs=array())
		foreach ($content['content'] as $document => $entries){
			$this->_pdf->Bookmark($document === ' ' && isset($this->_pdf->header['title']) ? $this->_pdf->header['title'] : $document , 0);
			$this->_pdf->SetFont('helvetica', '', $this->_setup['fontsize'] + 2); 
			$this->_pdf->MultiCell(140, 4, $document, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			foreach ($entries as $key => $values){
				$this->_pdf->Bookmark($key, 1);
				// name column
				$this->_pdf->SetFont('helvetica', 'B', $this->_setup['fontsize']);
				$nameLines = $this->_pdf->MultiCell(50, 4, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
				$this->_pdf->applyCustomPageBreak($nameLines, $this->_setup['fontsize']);
				
				// values column
				$this->_pdf->SetFont('helvetica', '', $this->_setup['fontsize']);
				$valueLines = 0;
				if (gettype($values) === 'array'){
					foreach ($values as $value){
						preg_match("/(?:^href=')(.+?)(?:')/", $value, $link); // link widget value
						if ($link){
							// writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=false, $reseth=true, $align='', $autopadding=true)
							$valueLines += $this->_pdf->writeHTMLCell(140, 4, 60, $this->_pdf->GetY(), '<a href="' . $link[1] . '" target="_blank">' . $link[1] . '</a>' . ($link[2] ? : ''), 0, 1, 0, true, '', true);
							continue;
						}
						preg_match("/(.+?) (\(.+?\))/", $value, $link); // attachment value with contributor for full export
						if (!isset($link[1])) $link = [null, $value];  // attachment value without contributor for simplified export
						$path = substr(UTILITY::directory('record_attachments'), 1) . '/' . $link[1];
						if (isset($content['attachments'][$document]) && in_array($path, $content['attachments'][$document])){
							$file = pathinfo($path);
							if (in_array($file['extension'], ['jpg', 'jpeg', 'gif', 'png'])) {
								// inline image embedding
								$valueLines += $this->_pdf->MultiCell(140, 4, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
								list($img_width, $img_height, $img_type, $img_attr) = getimagesize('.' . $path);
								$ratio = $img_height ? $img_width / $img_height : 1; // prevent division by 0
								$outputsize = [
									'width' => $ratio < 1 ? 0 : $this->_setup['exportimage_maxwidth'],
									'height' => $ratio > 1 ? 0 : $this->_setup['exportimage_maxheight']
								];
								$this->_pdf->Image('.' . $path, null, $this->_pdf->GetY() + 6, $outputsize['width'], $outputsize['height'], '', '', 'R', true, 300, 'R');
								$valueLines += $this->_pdf->Ln(max($this->_setup['exportimage_maxheight'], $outputsize['height']));
							}
							else {
								// file attachment
								$valueLines += $this->_pdf->MultiCell(140, 4, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
								// Annotation($x, $y, $w, $h, $text, $opt=array('Subtype'=>'Text'), $spaces=0)
								$this->_pdf->Annotation($this->_pdf->getPageWidth() - $this->_setup['marginleft'] + 5, $this->_pdf->GetY() - $this->_setup['fontsize'] * 1.5 , 10, 10, $_lang->GET('record.export_pdf_attachment', [], true) . ' ' . $value, array('Subtype'=>'FileAttachment', 'Name' => 'PushPin', 'FS' => '.' . $path));
							}
						}
						// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
						else $valueLines += $this->_pdf->MultiCell(140, 4, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
					}
				}
				elseif (str_starts_with($values, '::MARKDOWN::')){
					// textsection on full export with enabled markdown for document widget	var_dump('asdas');
					$markdown = new MARKDOWN();
					$valueLines = $this->_pdf->writeHTMLCell(140, 4, 60, $this->_pdf->GetY(), $markdown->md2html(substr($values, 12)), 0, 1, 0, true, '', true);
				}
				else $this->_pdf->MultiCell(140, 4, $values, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);

				$offset = $valueLines < $nameLines ? $nameLines - 1 : 0;
				$this->_pdf->Ln(($offset - 1) * $this->_setup['fontsize'] / 2);
			}
		}

		$this->_pdf->SetFont('helvetica', '', 8); 
		if (isset($content['recenthash'])) $this->_pdf->MultiCell(140, 4, $content['recenthash'], 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
		return $this->return($content);
	}

	public function tablePDF($content){
		// create a pdf for a table output
		$this->init($content);
		// set cell padding
		$this->_pdf->setCellPaddings(5, 1, 5, 1);
		
		if (array_is_list($content['content'])) $content['content'] = [$content['content']];

		$page = 0;
		foreach($content['content'] as $header => $table){
			if ($page++) $this->_pdf->AddPage();
			$this->_pdf->SetFont('helvetica', '', 16); // font size
			if (!array_is_list($content['content'])) $this->_pdf->MultiCell(0, 4, $header, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
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
				$html .= '<th' . (isset($content['columns'][$column]) && $content['columns'][$column] ? ' style="width:' . $content['columns'][$column] . '"': '') . '>' . $column_content . '</th>';
			}
			$html .= '</tr></thead>';
	
			$html .=  '<tbody>';
			foreach ($table as $index => $row){
				$odd = $index % 2 ? ' class="odd"' : '';
				$html .= '<tr' . $odd . '>';
				foreach(array_values($row) as $column => $column_content) {
					$html .= '<td' . (isset($content['columns'][$column]) && $content['columns'][$column] ? ' style="width:' . $content['columns'][$column] . '"': '') . '>' . $column_content . '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody>';
			$html .= '</table>';
			// writeHTML($html, $ln=true, $fill=false, $reseth=false, $cell=false, $align='')			
			$this->_pdf->writeHTML($html);
		}

		return $this->return($content);
	}

	public function timesheetPDF($content){
		// create a pdf for a timesheet output
		$this->init($content);
		// set cell padding
		$this->_pdf->setCellPaddings(5, 1, 5, 1);

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		$this->_pdf->SetFont('helvetica', '', 8); // font size
		
		$page = 0;
		foreach ($content['content'] as $user){
			//$this->_pdf->startPageGroup();
			if ($page++) $this->_pdf->AddPage();
			foreach ($user as $row){
				$key = isset($row[0]) ? $row[0][0] : '';
				$value = $row[1] ?? '';
				if (isset($row[0]) && $row[0][1]) $this->_pdf->SetTextColor(192, 192, 192);
				$this->_pdf->MultiCell(50, 2, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
				$this->_pdf->SetTextColor(0, 0, 0);
				$this->_pdf->MultiCell(145, 2, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			}
		}

		return $this->return($content);
	}
}

class RECORDTCPDF extends \Com\Tecnick\Pdf\Tcpdf {
	// custom pdf header and footer
	public $qrcodesize = null;
	public $qrcodecontent = null;
	public $header = null;
	public $_setup = null;

	public function __construct($setup, $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false, $qrcodesize = 20, $qrcodecontent = '', $header = ['title' => '', 'date' => '']){
//		parent::__construct($setup['orientation'], $setup['unit'], $setup['format'], $unicode, $encoding, $diskcache, $pdfa);
		parent::__construct($setup['unit']);
		$this->qrcodesize = $qrcodesize;
		$this->qrcodecontent = $qrcodecontent;
		$this->header = $header;
		$this->_setup = $setup;
	}

	// forces pagebreak if content exceeds name or page height
	public function applyCustomPageBreak($lines, $lineheight) {
		if ($this->GetY() > $this->getPageHeight() - $this->_setup['marginbottom'] - $lines * $lineheight) {
			if ($this->getNumPages() < $this->getPage() + 1) $this->AddPage();
			$this->SetY($this->_setup['margintop']);
		}
		if ($this->getNumPages() > $this->getPage()) $this->setPage($this->getPage() + 1);
	}

	//Page header
	public function Header() {
		// Title
		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		$right_margin = 0;
		if ($image = $this->_setup['header_image']){
			// given the image will always be 20mm high
			list($width, $height, $type, $attr) = getimagesize('../' . $image);
			if ($width && $height){ // avoid division by zero error for faulty input
				$right_margin = $width * 20 / $height;
				// Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false, $alt=false, $altimgs=array())
				$header_image = $this->image->add('../' . $image);
				$header_image_out = $this->image->getSetImage($header_image, 0, 10, $width / $height * 20, 20, 200);
				$this->page->addContent($header_image_out);

				$right_margin += 5;
			}
		}
		if ($this->_setup['header']){
			$this->font->insert($this->pon, 'helvetica', 'B', 20); // font size
			if ($this->header['title']) $this->MultiCell(110 - $right_margin, 0, $this->header['title'], 0, 'R', 0, 1, 90, 10, true, 0, false, true, 10, 'T', true);
			$this->font->insert($this->pon, 'helvetica', '', 10); // font size
			if ($this->header['date']) $this->MultiCell(110 - $right_margin, 0, $this->header['date'], 0, 'R', 0, 1, 90, 20, true, 0, false, true, 10, 'T', true);
		}
		if ($this->qrcodecontent){
			$style = array(
				'border' => 0,
				'vpadding' => 'auto',
				'hpadding' => 'auto',
				'fgcolor' => array(0,0,0),
				'bgcolor' => false, //array(255,255,255)
				'module_width' => 1, // width of a single module in points
				'module_height' => 1 // height of a single module in points
			);
			$this->write2DBarcode($this->qrcodecontent, 'QRCODE,' . CONFIG['limits']['qr_errorlevel'], 10, 10, $this->qrcodesize, $this->qrcodesize, $style, 'N');
			$this->MultiCell(50, $this->qrcodesize, $this->qrcodecontent, 0, '', 0, 0, 10 + $this->qrcodesize, 10, true, 0, false, true, 24, 'T', true);
		}
	}

	// Page footer
	public function Footer() {
		$_lang = new LANG();

		// Position at 15 mm from bottom
		$this->SetY(-15);
		$imageMargin = 0;
		if ($image = $this->_setup['footer_image']){
			list($width, $height, $type, $attr) = getimagesize('../' . $image);
			if ($width && $height){ // avoid division by zero error for faulty input
				// given the image will always be 10mm high
				$imageMargin = $width * 10 / $height;
				// Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false, $alt=false, $altimgs=array())
				$this->Image('../' . $image, $this->getPageWidth() - 10 - $imageMargin, $this->GetY(), '', 10, '', '', 'R', true, 300, '', false, false, 0, false, false, false);
				$imageMargin += 5;
			}
		}
		// Set font
		$this->SetFont('helvetica', 'I', 8);
		// company contacts and page number
		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		$this->MultiCell($this->getPageWidth() - $this->_setup['marginleft'] - 10 - $imageMargin, 10, $_lang->GET('company.address', [], true) . ' | ' . CONFIG['system']['caroapp'] . ' | ' . $this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, 'C', false, 0, $this->_setup['marginleft'], $this->GetY(), true, 0, false, true, $this->_setup['marginbottom'], 'T', true);
	}
}

?>
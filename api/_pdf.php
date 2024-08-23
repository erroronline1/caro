<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2024 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */


require_once('../libraries/TCPDF/tcpdf_import.php');

class PDF{
	public static function identifierPDF($content){
		// create a pdf for a label sheet with qr code and plain text
		// create new PDF document
		$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, INI['pdf']['labelsheet']['format'], true, 'UTF-8', false);

		// set document information
		$pdf->SetCreator(INI['system']['caroapp']);
		$pdf->SetAuthor($_SESSION['user']['name']);
		$pdf->SetTitle(LANG::GET('record.create_identifier'));
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		// set margins
		$pdf->SetMargins(0, 0, 0, 0);
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, 10); // margin bottom
		// set font
		$pdf->SetFont('helvetica', '', 10); // font size
		// add a page
		$pdf->AddPage();
		// set cell padding
		$pdf->setCellPaddings(0, 0, 0, 0);
		// set cell margins
		$pdf->setCellMargins(0, 0, 0, 0);
		// set color for background
		$pdf->SetFillColor(255, 255, 255);

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		$format = TCPDF_STATIC::getPageSizeFromFormat(INI['pdf']['labelsheet']['format']);
		$rowheight = (($format[1] * 25.4 / 72 ) - (INI['pdf']['labelsheet']['margintop'] + INI['pdf']['labelsheet']['marginbottom']))/ INI['pdf']['labelsheet']['rows'];
		$columnwidth = ($format[0] * 25.4 / 72 ) / INI['pdf']['labelsheet']['columns'];
		$codesize = min($columnwidth, $rowheight) - 10; // font size
		$style = array(
			'border' => 0,
			'vpadding' => 'auto',
			'hpadding' => 'auto',
			'fgcolor' => array(0,0,0),
			'bgcolor' => false, //array(255,255,255)
			'module_width' => 1, // width of a single module in points
			'module_height' => 1 // height of a single module in points
		);

		for ($row = 0; $row < INI['pdf']['labelsheet']['rows']; $row++){
			for ($column = 0; $column < INI['pdf']['labelsheet']['columns']; $column++){
				$pdf->write2DBarcode($content, 'QRCODE,M', $column * $columnwidth, $row * $rowheight, $codesize, $codesize, $style, 'N');
				$pdf->MultiCell($columnwidth - $codesize, $rowheight, $content, 0, '', 0, intval($column === INI['pdf']['labelsheet']['columns'] - 1), $column * $columnwidth + $codesize, $row * $rowheight, true, 0, false, true, 24, 'T', true);
			}
		}
		// move pointer to last page
		$pdf->lastPage();

		//Close and output PDF document
		UTILITY::tidydir('tmp', INI['lifespan']['tmp']);
		$filename = preg_replace('/[^\w\d]/', '', $content) . '.pdf';
		$pdf->Output(__DIR__ . '/' . UTILITY::directory('tmp') . '/' .$filename, 'F');
		return substr(UTILITY::directory('tmp') . '/' .$filename, 1);
	}

	public static function recordsPDF($content){
		// create a pdf for a record summary
		// create new PDF document
		$pdf = new RECORDTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, INI['pdf']['record']['format'], true, 'UTF-8', false, false,
		20, $content['identifier'], ['title' => $content['title'], 'date' => $content['date']]);

		// set document information
		$pdf->SetCreator(INI['system']['caroapp']);
		$pdf->SetAuthor($_SESSION['user']['name']);
		$pdf->SetTitle($content['title']);

		// set margins
		$pdf->SetMargins(INI['pdf']['record']['marginleft'], PDF_MARGIN_HEADER + INI['pdf']['record']['margintop'], INI['pdf']['record']['marginright'],1);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, INI['pdf']['record']['marginbottom']); // margin bottom
		// add a page
		$pdf->AddPage();
		// set cell padding
		$pdf->setCellPaddings(5, 5, 5, 5);
		// set cell margins
		$pdf->setCellMargins(0, 0, 0, 0);
		// set color for background
		$pdf->SetFillColor(255, 255, 255);

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		// Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false, $alt=false, $altimgs=array())
		
		foreach($content['content'] as $form => $entries){
			$pdf->SetFont('helvetica', '', 12); // font size
			$pdf->MultiCell(150, 4, $form, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			foreach($entries as $key => $value){
				$pdf->SetFont('helvetica', 'B', 10); // font size
				$pdf->MultiCell(50, 4, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
				$pdf->SetFont('helvetica', '', 10); // font size
				$pdf->MultiCell(150, 4, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			}
			if (array_key_exists($form, $content['images'])){
				$ln = 0;
				foreach ($content['images'][$form] as $image){
					$imagedata = pathinfo($image);
					$pdf->SetFont('helvetica', 'B', 10); // font size
					$pdf->MultiCell(50, INI['pdf']['exportimage']['maxheight'], $imagedata['basename'], 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
					$pdf->Image('.' . $image, null, null, 0, INI['pdf']['exportimage']['maxheight'] - 1, '', '', 'R', true, 300, 'R');
					$pdf->Ln(INI['pdf']['exportimage']['maxheight']);
				}
			}
		}
		// move pointer to last page
		$pdf->lastPage();

		//Close and output PDF document
		UTILITY::tidydir('tmp', INI['lifespan']['tmp']);
		$pdf->Output(__DIR__ . '/' . UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 'F');
		return substr(UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 1);
	}

	public static function formsPDF($content){
		// create a pdf for a form export
		// create new PDF document
		$pdf = new RECORDTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, INI['pdf']['record']['format'], true, 'UTF-8', false, false,
		20, $content['identifier'], ['title' => $content['title'], 'date' => $content['date']]);

		// set document information
		$pdf->SetCreator(INI['system']['caroapp']);
		$pdf->SetAuthor($_SESSION['user']['name']);
		$pdf->SetTitle($content['title']);

		// set margins
		$pdf->SetMargins(INI['pdf']['record']['marginleft'], PDF_MARGIN_HEADER + INI['pdf']['record']['margintop'], INI['pdf']['record']['marginright'],1);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, INI['pdf']['record']['marginbottom']); // margin bottom
		// add a page
		$pdf->AddPage();
		// set cell padding
		$pdf->setCellPaddings(5, 5, 5, 5);
		// set cell margins
		$pdf->setCellMargins(0, 0, 0, 0);
		// set color for background
		$pdf->SetFillColor(255, 255, 255);
		// set default form properties
		$pdf->setFormDefaultProp(['lineWidth' => 0, 'borderStyle' => 'solid']);

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		// Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false, $alt=false, $altimgs=array())
		
		foreach($content['content'] as $form => $entries){
			$pdf->SetFont('helvetica', '', 12); // font size
			$pdf->MultiCell(150, 4, $form, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			foreach($entries as $key => $value){
				$pdf->SetFont('helvetica', 'B', 10); // font size
				$pdf->MultiCell(50, 4, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 100, 'T', false);
				$pdf->SetFont('helvetica', '', 10); // font size
				$originalFontSize = $pdf->getFontSizePt();
				if (gettype($value) === 'string'){
					preg_match('/[\n\r]/m', $value, $lines);
					if (count($lines)<1) {
						if (trim($value))
							if (array_key_exists($form, $content['images']) && in_array($value, $content['images'][$form])) { // image type
								$imagedata = pathinfo($value);
								$pdf->Image('.' . $value, null, $pdf->GetY() + 4, 0, INI['pdf']['exportimage']['maxheight'] - 1, '', '', 'R', true, 300, 'R');
								$pdf->Ln(INI['pdf']['exportimage']['maxheight'] + 4);
							} else { // text type
								$pdf->MultiCell(133, 4, $value, 0, '', 0, 1, 63, $pdf->GetY(), true, 0, false, true, 0, 'T', false);
							}
						else { // text, number, etc
							$height = 5;
							$pdf->SetFontSize(0); // variable font size
							$pdf->TextField($key, 133, $height, [], [], 63, $pdf->GetY() + 4);
							$pdf->Ln($height + 5);
						}
					} else { // textarea
						$height = 31;
						$pdf->SetFontSize(0); // variable font size
						$pdf->TextField($key, 133, $height, ['multiline' => true, 'lineWidth' => 0, 'borderStyle' => 'none'], ['v' => $value, 'dv' => $value], 63, $pdf->GetY() + 4);
						$pdf->Ln($height + 5);
					}
					$pdf->SetFontSize($originalFontSize);
				}
				if (gettype($value) === 'array'){
					foreach($value as $option){
						$pdf->SetFontSize(14);
						$pdf->CheckBox($option, 5, str_starts_with($option, '_____'), [], [], 'OK', 63, $pdf->GetY() + 4);
						$pdf->SetFontSize($originalFontSize);
						$pdf->MultiCell(50, 4, (str_starts_with($option, '_____') ? substr($option, 5) : $option), 0, '', 0, 1, 65, $pdf->GetY(), true, 0, false, true, 0, 'T', false);
						$pdf->Ln(-7);
					}
				}
			}
		}
		// move pointer to last page
		$pdf->lastPage();

		//Close and output PDF document
		UTILITY::tidydir('tmp', INI['lifespan']['tmp']);
		$pdf->Output(__DIR__ . '/' . UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 'F');
		return substr(UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 1);
	}

	public static function auditPDF($content){
		// create a pdf for a record summary
		// create new PDF document
		$pdf = new RECORDTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, INI['pdf']['record']['format'], true, 'UTF-8', false, false,
		20, null, ['title' => $content['title'], 'date' => $content['date']]);

		// set document information
		$pdf->SetCreator(INI['system']['caroapp']);
		$pdf->SetAuthor($_SESSION['user']['name']);
		$pdf->SetTitle($content['title']);

		// set margins
		$pdf->SetMargins(INI['pdf']['record']['marginleft'], PDF_MARGIN_HEADER + INI['pdf']['record']['margintop'], INI['pdf']['record']['marginright'],1);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, INI['pdf']['record']['marginbottom']); // margin bottom
		// add a page
		$pdf->AddPage();
		// set cell padding
		$pdf->setCellPaddings(5, 5, 5, 5);
		// set cell margins
		$pdf->setCellMargins(0, 0, 0, 0);
		// set color for background
		$pdf->SetFillColor(255, 255, 255);

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		
		foreach($content['content'] as $key => $value){
			$pdf->SetFont('helvetica', 'B', 10); // font size
			$pdf->MultiCell(50, 4, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
			$pdf->SetFont('helvetica', '', 10); // font size
			if (gettype($value) === 'array') {
				$value = implode("\n", array_keys($value));
			}
			$pdf->MultiCell(150, 4, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
		}
		// move pointer to last page
		$pdf->lastPage();

		//Close and output PDF document
		UTILITY::tidydir('tmp', INI['lifespan']['tmp']);
		$pdf->Output(__DIR__ . '/' . UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 'F');
		return substr(UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 1);
	}

	public static function timesheetPDF($content){
		// create a pdf for a timesheet output
		// create new PDF document
		$pdf = new RECORDTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, INI['pdf']['record']['format'], true, 'UTF-8', false, false,
		20, null, ['title' => $content['title'], 'date' => $content['date']]);

		// set document information
		$pdf->SetCreator(INI['system']['caroapp']);
		$pdf->SetAuthor($_SESSION['user']['name']);
		$pdf->SetTitle($content['title']);

		// set margins
		$pdf->SetMargins(INI['pdf']['record']['marginleft'], PDF_MARGIN_HEADER + INI['pdf']['record']['margintop'], INI['pdf']['record']['marginright'],1);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, INI['pdf']['record']['marginbottom']); // margin bottom
		// set cell padding
		$pdf->setCellPaddings(5, 1, 5, 1);
		// set cell margins
		$pdf->setCellMargins(0, 0, 0, 0);
		// set color for background
		$pdf->SetFillColor(255, 255, 255);

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		$pdf->SetFont('helvetica', '', 8); // font size
		
		foreach($content['content'] as $user){
			$pdf->startPageGroup();
			$pdf->AddPage();
			foreach($user as $row){
				$key = array_key_exists(0, $row) ? $row[0][0] : '';
				$value = array_key_exists(1, $row) ? $row[1] : '';
				if (array_key_exists(0, $row) && $row[0][1]) $pdf->SetTextColor(192, 192, 192);
				$pdf->MultiCell(50, 2, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
				$pdf->SetTextColor(0, 0, 0);
				$pdf->MultiCell(150, 2, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			}

		}
		// move pointer to last page
		$pdf->lastPage();

		//Close and output PDF document
		UTILITY::tidydir('tmp', INI['lifespan']['tmp']);
		$pdf->Output(__DIR__ . '/' . UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 'F');
		return substr(UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 1);
	}
}

class RECORDTCPDF extends TCPDF {
	// custom pdf header and footer
	public $qrcodesize = null;
	public $qrcodecontent = null;
	public $header = null;

	public function __construct($orientation='P', $unit='mm', $format='A4', $unicode=true, $encoding='UTF-8', $diskcache=false, $pdfa=false, $qrcodesize=20, $qrcodecontent='', $header=['title' => '', 'date' => '']){
		parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
		$this->qrcodesize = $qrcodesize;
		$this->qrcodecontent = $qrcodecontent;
		$this->header = $header;
	}

	//Page header
	public function Header() {
		// Title
		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		$this->SetFont('helvetica', 'B', 20); // font size
		$this->MultiCell(110, 0, $this->header['title'], 0, 'R', 0, 1, 90, 10, true, 0, false, true, 10, 'T', true);
		$this->SetFont('helvetica', '', 10); // font size
		$this->MultiCell(110, 0, $this->header['date'], 0, 'R', 0, 1, 90, 20, true, 0, false, true, 10, 'T', true);

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
			$this->write2DBarcode($this->qrcodecontent, 'QRCODE,M', 10, 10, $this->qrcodesize, $this->qrcodesize, $style, 'N');
			$this->MultiCell(50, $this->qrcodesize, $this->qrcodecontent, 0, '', 0, 0, 10 + $this->qrcodesize, 10, true, 0, false, true, 24, 'T', true);
		}
	}

	// Page footer
	public function Footer() {
		// Position at 15 mm from bottom
		$this->SetY(-15);
		// Set font
		$this->SetFont('helvetica', 'I', 8);
		// Page number
		$this->Cell(0, 10, INI['system']['caroapp'] . ' | ' . LANG::GET('company.address') . ' | '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
	}
}

?>
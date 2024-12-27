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
	public static function identifierPDF($content, $type = 'sheet'){
		// create a pdf for a label sheet with qr code and plain text
		// or label for label printer as selected
		$_lang = new LANG();

		$setup = [
			'orientation' => isset(CONFIG['label'][$type]['orientation']) ? CONFIG['label'][$type]['orientation'] : 'portrait',
			'format' => isset(CONFIG['label'][$type]['format']) ? CONFIG['label'][$type]['format'] : 'A4',
			'title' => $_lang->GET('record.create_identifier'),
			'margin' => [
				'top' => isset(CONFIG['label'][$type]['margintop']) ? CONFIG['label'][$type]['margintop'] : 0,
				'right' => isset(CONFIG['label'][$type]['marginright']) ? CONFIG['label'][$type]['marginright'] : 0,
				'bottom' => isset(CONFIG['label'][$type]['marginbottom']) ? CONFIG['label'][$type]['marginbottom'] : 0 ,
				'left' => isset(CONFIG['label'][$type]['marginleft']) ? CONFIG['label'][$type]['marginleft'] : 0,
			],
			'rows' => isset(CONFIG['label'][$type]['rows']) ? CONFIG['label'][$type]['rows'] : 1,
			'columns' => isset(CONFIG['label'][$type]['columns']) ? CONFIG['label'][$type]['columns'] : 1,
			'fontsize' => isset(CONFIG['label'][$type]['fontsize']) ? CONFIG['label'][$type]['fontsize'] : 10,
			'codesizeoffset' => isset(CONFIG['label'][$type]['codesizeoffset']) ? CONFIG['label'][$type]['codesizeoffset'] :0
		];
		$customsetup = preg_split('/\D{1,}/', $setup['format']);
		if (count($customsetup) > 1){
			$setup['format'] = [$customsetup[0], $customsetup[1]];
		}

		// create new PDF document
		$pdf = new TCPDF($setup['orientation'], PDF_UNIT, $setup['format'], true, 'UTF-8', false);

		// set document information
		$pdf->SetCreator(CONFIG['system']['caroapp']);
		$pdf->SetAuthor($_SESSION['user']['name']);
		$pdf->SetTitle($setup['title']);
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		// set margins
		$pdf->SetMargins($setup['margin']['left'], $setup['margin']['top'], $setup['margin']['right'], true);
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, $setup['margin']['bottom']); // margin bottom
		// set font
		$pdf->SetFont('helvetica', '', $setup['fontsize']); // font size
		// add a page
		$pdf->AddPage();
		// set cell padding
		$pdf->setCellPaddings(0, 0, 0, 0);
		// set cell margins
		$pdf->setCellMargins(0, 0, 0, 0);
		// set color for background
		$pdf->SetFillColor(255, 255, 255);

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		$format = [$pdf->getPageWidth(), $pdf->getPageheight()];
		$columnwidth = ($format[0] - ($setup['margin']['left'] + $setup['margin']['right'])) / $setup['columns'];
		$rowheight = ($format[1] - ($setup['margin']['top'] + $setup['margin']['bottom'])) / $setup['rows'];

		//var_dump($columnwidth, $rowheight);
		$codesize = min($columnwidth, $rowheight) - $setup['codesizeoffset'];
		$style = array(
			'border' => 0,
			'vpadding' => 'auto',
			'hpadding' => 'auto',
			'fgcolor' => array(0,0,0),
			'bgcolor' => false, //array(255,255,255)
			'module_width' => 1, // width of a single module in points
			'module_height' => 1 // height of a single module in points
		);

		for ($row = 0; $row < $setup['rows']; $row++){
			for ($column = 0; $column < $setup['columns']; $column++){
				$pdf->write2DBarcode($content, 'QRCODE,' . CONFIG['limits']['qr_errorlevel'], $column * $columnwidth + $setup['margin']['left'], $row * $rowheight + $setup['margin']['top'], $codesize, $codesize, $style, 'N');
				$pdf->MultiCell($columnwidth - $codesize, $rowheight, $content, 0, '', 0, intval($column === $setup['columns'] - 1), $column * $columnwidth + $codesize + $setup['margin']['left'], $row * $rowheight + $setup['margin']['top'], true, 0, false, true, 24, 'T', true);
			}
		}
		// move pointer to last page
		$pdf->lastPage();

		//Close and output PDF document
		UTILITY::tidydir('tmp', CONFIG['lifespan']['tmp']);
		$filename = preg_replace('/' . CONFIG['forbidden']['names'][0] . '/', '', $content) . '.pdf';
		$pdf->Output(__DIR__ . '/' . UTILITY::directory('tmp') . '/' .$filename, 'F');
		return substr(UTILITY::directory('tmp') . '/' .$filename, 1);
	}

	public static function recordsPDF($content){
		// create a pdf for a record summary
		// create new PDF document
		$pdf = new RECORDTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, CONFIG['pdf']['record']['format'], true, 'UTF-8', false, false,
		20, $content['identifier'], ['title' => $content['title'], 'date' => $content['date']]);

		// set document information
		$pdf->SetCreator(CONFIG['system']['caroapp']);
		$pdf->SetAuthor($_SESSION['user']['name']);
		$pdf->SetTitle($content['title']);

		// set margins
		$pdf->SetMargins(CONFIG['pdf']['record']['marginleft'], PDF_MARGIN_HEADER + CONFIG['pdf']['record']['margintop'], CONFIG['pdf']['record']['marginright'], 1);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, CONFIG['pdf']['record']['marginbottom']); // margin bottom
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
		
		$height = [
			'font' => 10,
		];

		foreach($content['content'] as $document => $entries){
			$pdf->SetFont('helvetica', '', $height['font'] + 2); // font size
			$pdf->MultiCell(140, 4, $document, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			foreach($entries as $key => $value){
				// name column
				$pdf->SetFont('helvetica', 'B', $height['font']); // font size
				$nameLines = $pdf->MultiCell(50, 4, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
				$pdf->applyCustomPageBreak($nameLines, $height['font']);
				
				// values column
				$pdf->SetFont('helvetica', '', $height['font']); // font size
				preg_match("/(?:^href=')(.+?)(?:')(.*)/", $value, $link); // link widget value
				if ($link) {
					// writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=false, $reseth=true, $align='', $autopadding=true)
					$valueLines = $pdf->writeHTMLCell(140, 4, 60, $pdf->GetY(), '<a href="' . $link[1] . '" target="_blank">' . $link[1] . '</a>' . ($link[2] ? : ''), 0, 1, 0, true, '', true);
				}
				// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
				else $valueLines = $pdf->MultiCell(140, 4, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
				
				$offset = $valueLines < $nameLines ? $nameLines - 1 : 0;
				$pdf->Ln(($offset - 1) * $height['font'] / 2);
			}
			if (array_key_exists($document, $content['images'])){
				$ln = 0;
				foreach ($content['images'][$document] as $image){
					$imagedata = pathinfo($image);
					list($img_width, $img_height, $img_type, $img_attr) = getimagesize('.' . $image);
					$pdf->SetFont('helvetica', 'B', $height['font']); // font size
					$pdf->MultiCell(50, CONFIG['pdf']['exportimage']['maxheight'], $imagedata['basename'], 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
					if ($img_width && CONFIG['pdf']['exportimage']['maxheight'] && ($img_height / $img_width > 145 / CONFIG['pdf']['exportimage']['maxheight']))
						$pdf->Image('.' . $image, null, null, 0, CONFIG['pdf']['exportimage']['maxheight'] - 1, '', '', 'R', true, 300, 'R');
					else
						$pdf->Image('.' . $image, null, null, 145, 0, '', '', 'R', true, 300, 'R');
					$pdf->Ln(CONFIG['pdf']['exportimage']['maxheight']);
				}
			}
		}
		// move pointer to last page
		$pdf->lastPage();

		//Close and output PDF document
		UTILITY::tidydir('tmp', CONFIG['lifespan']['tmp']);
		$pdf->Output(__DIR__ . '/' . UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 'F');
		return substr(UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 1);
	}

	public static function documentsPDF($content){
		// create a pdf for a document export
		// create new PDF document
		$pdf = new RECORDTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, CONFIG['pdf']['record']['format'], true, 'UTF-8', false, false,
		20, $content['identifier'], ['title' => $content['title'], 'date' => $content['date']]);

		// set document information
		$pdf->SetCreator(CONFIG['system']['caroapp']);
		$pdf->SetAuthor($_SESSION['user']['name']);
		$pdf->SetTitle($content['title']);

		// set margins
		$pdf->SetMargins(CONFIG['pdf']['record']['marginleft'], PDF_MARGIN_HEADER + CONFIG['pdf']['record']['margintop'], CONFIG['pdf']['record']['marginright'], 1);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, CONFIG['pdf']['record']['marginbottom']); // margin bottom
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
		
		$height = [
			'font' => 10,

			'multiline' => 31,
			'default' => 5
		];

		foreach($content['content'] as $document => $entries){
			$pdf->SetFont('helvetica', '', $height['font'] + 2); // font size
			$pdf->MultiCell(145, 4, $document, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			$keyY = $pdf->GetY();
			foreach($entries as $key => $value){
				// make sure to write on next page if multiline textfield would reach into footer
				if ($value['type'] === "multiline" && !$value['value']
					&& $pdf->GetY() > $pdf->getPageHeight() - CONFIG['pdf']['record']['marginbottom'] - $height['multiline']) {
						$pdf->AddPage();
						$pdf->SetY(CONFIG['pdf']['record']['margintop']);
				}
				// name column
				$pdf->SetFont('helvetica', 'B', $height['font']); // font size
				$nameLines = $pdf->MultiCell(50, 4, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 100, 'T', false);
				$pdf->applyCustomPageBreak($nameLines, $height['font']);

				// values column
				$pdf->SetFont('helvetica', '', $height['font']); // font size

				switch ($value['type']){
					case 'textsection':
						$pdf->MultiCell(140, 4, $value['value'], 0, '', 0, 1, 60, $pdf->GetY(), true, 0, false, true, 0, 'T', false);
						break;
					case 'image':
						if (array_key_exists($document, $content['images']) && in_array($value['value'], $content['images'][$document])) {
							$imagedata = pathinfo($value['value']);
							list($img_width, $img_height, $img_type, $img_attr) = getimagesize('.' . $image);
							$pdf->SetFont('helvetica', 'B', $height['font']); // font size
							$pdf->MultiCell(50, CONFIG['pdf']['exportimage']['maxheight'], $imagedata['basename'], 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
							if ($img_width && CONFIG['pdf']['exportimage']['maxheight'] && ($img_height / $img_width > 145 / CONFIG['pdf']['exportimage']['maxheight']))
								$pdf->Image('.' . $value['value'], null, null, 0, CONFIG['pdf']['exportimage']['maxheight'] - 1, '', '', 'R', true, 300, 'R');
							else
								$pdf->Image('.' . $value['value'], null, null, 145, 0, '', '', 'R', true, 300, 'R');
							$pdf->Ln(CONFIG['pdf']['exportimage']['maxheight'] + 4);
						}
						break;
					case 'selection':
						foreach($value['value'] as $option){
							$pdf->applyCustomPageBreak($nameLines, $height['font']);

							$pdf->SetFontSize(14);
							$pdf->CheckBox($option, 5, str_starts_with($option, '_____'), [], [], 'OK', 65, $pdf->GetY() + 4);
							$pdf->SetFontSize($height['font']);
							$pdf->MultiCell(133, 4, (str_starts_with($option, '_____') ? substr($option, 5) : $option), 0, '', 0, 1, 67, $pdf->GetY(), true, 0, false, true, 0, 'T', false);
							$pdf->Ln(-7);
						}
						$pdf->Ln(max([1, $nameLines - count($value['value'])]) * 5);
						break;
					case 'multiline':
						if ($value['value']) { // print value for missing field values on some systems
							$pdf->SetFont('helvetica', 'I', $height['font']); // font size
							$pdf->MultiCell(140, 4, $value['value'], 0, '', 0, 1, 60, $pdf->GetY(), true, 0, false, true, 0, 'T', false);
							$pdf->SetFont('helvetica', '', $height['font']); // font size
							break;
						}
						$pdf->SetFontSize(0); // variable font size
						$pdf->TextField($key, 133, $height['multiline'], ['multiline' => true, 'lineWidth' => 0, 'borderStyle' => 'none'], ['v' => $value['value'], 'dv' => $value['value']], 65, $pdf->GetY() + 4);
						$pdf->Ln($height['multiline'] + max([1, $nameLines]) * 5);
						break;
					default:
						if ($value['value']) { // print value for missing field values on some systems
							$pdf->SetFont('helvetica', 'I', $height['font']); // font size
							preg_match("/(?:^href=')(.+?)(?:')(.*)/", $value['value'], $link); // link widget value
							if ($link) {
								// writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=false, $reseth=true, $align='', $autopadding=true)
								$pdf->writeHTMLCell(140, 4, 60, $pdf->GetY(), '<a href="' . $link[1] . '" target="_blank">' . $link[1] . '</a>' . ($link[2] ? : ''), 0, 1, 0, true, '', true);
							}
							// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
							else $pdf->MultiCell(140, 4, $value['value'], 0, '', 0, 1, 60, $pdf->GetY(), true, 0, false, true, 0, 'T', false);
							$pdf->SetFont('helvetica', '', $height['font']); // font size
							break;
						}
						$pdf->SetFontSize(0); // variable font size
						$pdf->TextField($key, 133, $height['default'], [], ['v' => $value['value'], 'dv' => $value['value']], 65, $pdf->GetY() + 4);
						$pdf->Ln($height['default'] + max([1, $nameLines]) * 5);
				}
			}
		}
		// move pointer to last page
		$pdf->lastPage();

		//Close and output PDF document
		UTILITY::tidydir('tmp', CONFIG['lifespan']['tmp']);
		$pdf->Output(__DIR__ . '/' . UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 'F');
		return substr(UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 1);
	}

	public static function auditPDF($content){
		// create a pdf for a record summary
		// create new PDF document
		$pdf = new RECORDTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, CONFIG['pdf']['record']['format'], true, 'UTF-8', false, false,
		20, null, ['title' => $content['title'], 'date' => $content['date']]);

		// set document information
		$pdf->SetCreator(CONFIG['system']['caroapp']);
		$pdf->SetAuthor($_SESSION['user']['name']);
		$pdf->SetTitle($content['title']);

		// set margins
		$pdf->SetMargins(CONFIG['pdf']['record']['marginleft'], PDF_MARGIN_HEADER + CONFIG['pdf']['record']['margintop'], CONFIG['pdf']['record']['marginright'],1);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, CONFIG['pdf']['record']['marginbottom']); // margin bottom
		// add a page
		$pdf->AddPage();
		// set cell padding
		$pdf->setCellPaddings(5, 5, 5, 5);
		// set cell margins
		$pdf->setCellMargins(0, 0, 0, 0);
		// set color for background
		$pdf->SetFillColor(255, 255, 255);

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		
		$height = [
			'font' => 10,
		];

		foreach($content['content'] as $key => $value){
			// name column
			$pdf->SetFont('helvetica', 'B', $height['font']); // font size
			$nameLines = $pdf->MultiCell(50, 4, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
			$pdf->applyCustomPageBreak($nameLines, $height['font']);

			// values column
			$pdf->SetFont('helvetica', '', $height['font']); // font size
			if (gettype($value) === 'array') {
				$value = implode("\n", array_keys($value));
			}
			$valueLines = $pdf->MultiCell(145, 4, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);

			$offset = $valueLines < $nameLines ? $nameLines - 1 : 0;
			$pdf->Ln(($offset - 1) * $height['font'] / 2);
}
		// move pointer to last page
		$pdf->lastPage();

		//Close and output PDF document
		UTILITY::tidydir('tmp', CONFIG['lifespan']['tmp']);
		$pdf->Output(__DIR__ . '/' . UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 'F');
		return substr(UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 1);
	}

	public static function timesheetPDF($content){
		// create a pdf for a timesheet output
		// create new PDF document
		$pdf = new RECORDTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, CONFIG['pdf']['record']['format'], true, 'UTF-8', false, false,
		20, null, ['title' => $content['title'], 'date' => $content['date']]);

		// set document information
		$pdf->SetCreator(CONFIG['system']['caroapp']);
		$pdf->SetAuthor($_SESSION['user']['name']);
		$pdf->SetTitle($content['title']);

		// set margins
		$pdf->SetMargins(CONFIG['pdf']['record']['marginleft'], PDF_MARGIN_HEADER + CONFIG['pdf']['record']['margintop'], CONFIG['pdf']['record']['marginright'],1);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, CONFIG['pdf']['record']['marginbottom']); // margin bottom
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
				$pdf->MultiCell(145, 2, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			}
		}
		// move pointer to last page
		$pdf->lastPage();

		//Close and output PDF document
		UTILITY::tidydir('tmp', CONFIG['lifespan']['tmp']);
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

	// forces pagebreak if content exceeds name or page height
	public function applyCustomPageBreak($lines, $lineheight) {
		if ($this->GetY() > $this->getPageHeight() - CONFIG['pdf']['record']['marginbottom'] - $lines * $lineheight) {
			if ($this->getNumPages() < $this->getPage() + 1) $this->AddPage();
			$this->SetY(CONFIG['pdf']['record']['margintop']);
		}
		if ($this->getNumPages() > $this->getPage()) $this->setPage($this->getPage() + 1);
	}

	//Page header
	public function Header() {
		// Title
		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		$right_margin = 0;
		if ($image = CONFIG['pdf']['record']['header_image']){
			// given the image will always be 20mm high
			list($width, $height, $type, $attr) = getimagesize('../' . $image);
			if ($width && $height){ // avoid division by zero error for faulty input
				$right_margin = $width * 20 / $height;
				// Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false, $alt=false, $altimgs=array())
				$this->Image('../' . $image, $this->getPageWidth() - 10 - $right_margin, 10, '', 20, '', '', 'R', true, 300, '', false, false, 0, false, false, false);
				$right_margin += 5;
			}
		}
		$this->SetFont('helvetica', 'B', 20); // font size
		$this->MultiCell(110 - $right_margin, 0, $this->header['title'], 0, 'R', 0, 1, 90, 10, true, 0, false, true, 10, 'T', true);
		$this->SetFont('helvetica', '', 10); // font size
		$this->MultiCell(110 - $right_margin, 0, $this->header['date'], 0, 'R', 0, 1, 90, 20, true, 0, false, true, 10, 'T', true);

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
		if ($image = CONFIG['pdf']['record']['footer_image']){
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
		$this->MultiCell($this->getPageWidth() - CONFIG['pdf']['record']['marginleft'] - 10 - $imageMargin, 10, $_lang->GET('company.address') . ' | ' . CONFIG['system']['caroapp'] . ' | ' . $this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, 'C', false, 0, CONFIG['pdf']['record']['marginleft'], $this->GetY(), true, 0, false, true, CONFIG['pdf']['record']['marginbottom'], 'T', true);
	}
}

?>
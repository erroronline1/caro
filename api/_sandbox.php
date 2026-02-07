<?php
namespace CARO\API;
require_once('_config.php');
require_once('_utility.php'); // general utilities
//require_once('_erpinterface.php');
//var_dump(ERPINTERFACE->orderdata());

require_once('_language.php');
require_once('_pdf copy.php');
$pdf = new RECORDTCPDF([
    'unit' => 'mm',
    'header_image' => realpath(__DIR__ . '/../' . CONFIG['pdf']['record']['header_image']),
    'footer_image' => realpath(__DIR__ . '/../' . CONFIG['pdf']['record']['header_image']),
    ],
    true,
    'UTF-8',
    false,
    false,
    20,
    "hello world *12.13.1415 #lkasfjn",
    ['title' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua',
    'date' => 'today']);

// ----------

$pdf->setCreator('tc-lib-pdf');
$pdf->setAuthor('John Doe');
$pdf->setSubject('tc-lib-pdf generic example');
$pdf->setTitle('Example');
$pdf->setKeywords('TCPDF tc-lib-pdf generic example');

$pdf->setViewerPreferences(['DisplayDocTitle' => true]);

$pdf->enableDefaultPageContent();

$bfont1 = $pdf->font->insert($pdf->pon, 'helvetica', '', 12);

// Add page 9

$page09 = $pdf->addPage();

$pdf->setBookmark('Barcodes', '', 0, -1, 0, 0, 'B', '');

$dest_barcode_page = $pdf->setNamedDestination('barcode');

$pdf->graph->setPageWidth($page09['width']);
$pdf->graph->setPageHeight($page09['height']);

// Barcode

$barcode_style = [
    'lineWidth' => 0,
    'lineCap' => 'butt',
    'lineJoin' => 'miter',
    'dashArray' => [],
    'dashPhase' => 0,
    'lineColor' => 'black',
    'fillColor' => 'black',
];

/*$barcode1 = $pdf->getBarcode(
    'QRCODE,H',
    'https://tecnick.com',
    10,
    40,
    -1,
    -1,
    [0, 0, 0, 0],
    $barcode_style
);
$pdf->page->addContent($barcode1);
*/
$barcode2 = $pdf->getBarcode(
    'IMB',
    '01234567094987654321-01234567891',
    10,
    80,
    -1,
    -2,
    [0, 0, 0, 0],
    $barcode_style
);
$pdf->page->addContent($barcode2);
// =============================================================

// ----------
// Add page 11

$page11 = $pdf->addPage();
$pdf->setBookmark('Text', '', 0, -1, 0, 0, 'B', '');

// Add an internal link to this page
$page11_link = $pdf->addInternalLink();

$pdf->graph->setPageWidth($page11['width']);
$pdf->graph->setPageHeight($page11['height']);

$styletxt = [
    'lineWidth' => 0.25,
    'lineCap' => 'butt',
    'lineJoin' => 'miter',
    'dashArray' => [],
    'dashPhase' => 0,
    'lineColor' => 'red',
    'fillColor' => 'black',
];

$pdf->graph->add($styletxt);


$bfont2 = $pdf->font->insert($pdf->pon, 'times', 'BI', 24);

$pdf->page->addContent($bfont2['out']);
// alternative to set the current font (last entry in the font stack):
// $pdf->page->addContent($pdf->font->getOutCurrentFont());

// Add text
$txt = $pdf->getTextLine(
    'Test PDF text with justification (stretching) % %% %%%',
    0,
    90,
    $page11['width']
);

$pdf->page->addContent($txt);


$pdf->pageNumeration();


// ----------
// get PDF document as raw string
$rawpdf = $pdf->getOutPDFString();

// ----------

// Various output modes:

//$pdf->savePDF(\dirname(__DIR__).'/target', $rawpdf);
$pdf->renderPDF($rawpdf);

//phpinfo();
?>
<?php
namespace CARO\API;
require_once('_config.php');
require_once('_utility.php'); // general utilities
//require_once('_erpinterface.php');
//var_dump(ERPINTERFACE->orderdata());

require_once('_language.php');
require_once('_pdf copy.php');
//$pdf = new RECORDTCPDF(['unit' => 'mm']);

$pdf = new \Com\Tecnick\Pdf\Tcpdf(
    'mm', // string $unit = 'mm',
    true, // bool $isunicode = true,
    false, // bool $subsetfont = false,
    false, // bool $compress = true,
    '', // string $mode = '',
    null, // ?ObjEncrypt $objEncrypt = null,
);

// ----------

$pdf->setCreator('tc-lib-pdf');
$pdf->setAuthor('John Doe');
$pdf->setSubject('tc-lib-pdf generic example');
$pdf->setTitle('Example');
$pdf->setKeywords('TCPDF tc-lib-pdf generic example');
$pdf->setPDFFilename('test_index.pdf');

$pdf->setViewerPreferences(['DisplayDocTitle' => true]);

$pdf->enableDefaultPageContent();

// Add page 9

//$page09 = $pdf->addPage();
var_dump($pdf->page->getPageId());
/*
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

$barcode1 = $pdf->getBarcode(
    'QRCODE,H',
    'https://tecnick.com',
    10,
    10,
    -1,
    -1,
    [0, 0, 0, 0],
    $barcode_style
);
$pdf->page->addContent($barcode1);

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

/*
// ----------
// get PDF document as raw string
$rawpdf = $pdf->getOutPDFString();

// ----------

// Various output modes:

//$pdf->savePDF(\dirname(__DIR__).'/target', $rawpdf);
$pdf->renderPDF($rawpdf);
*/
//phpinfo();
?>
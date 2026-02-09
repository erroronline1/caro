<?php
namespace CARO\API;
require_once('_config.php');
require_once('_utility.php'); // general utilities
//require_once('_erpinterface.php');
//var_dump(ERPINTERFACE->orderdata());

require_once('_language.php');
require_once('_pdf copy.php');
$pdf = new RECORDTCPDF(
	[
		'unit' => 'mm',
		'header_image' => realpath(__DIR__ . '/../' . CONFIG['pdf']['record']['header_image']),
		'footer_image' => realpath(__DIR__ . '/../' . CONFIG['pdf']['record']['header_image']),
		'orientation' => 'P',
		'margintop' => 10, // 10, this does not work yet beyond
		'marginright' => 6,//10,
		'marginbottom' => 8,//10,
		'marginleft' => 5,//20,
	],
	true,
	'UTF-8',
	false,
	false,
	20,
	"hello world *12.13.1415 #lkasfjn Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua",
	['title' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua',
	'date' => 'today']);

// ----------

$contents = [
	'sgfsdfg sdfgs dfgsf g' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
	'wtert db xcfxcvbe ht' => 'Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.',
	'werowpt puehwp98 jkfd' => 'Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi.',
	'hsdlfghewprt kreg' => 'Nam liber tempor cum soluta nobis eleifend option congue nihil imperdiet doming id quod mazim placerat facer possim assum. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat.',
	'gjkshdlg' => 'At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, At accusam aliquyam diam diam dolore dolores duo eirmod eos erat, et nonumy sed tempor et et invidunt justo labore Stet clita ea et gubergren, kasd magna no rebum. sanctus sea sed takimata ut vero voluptua. est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat.',
	'rg hwo4t5jnerlgku serglks jg' => 'Consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus.',
	'shlriguhowhtjerglksjdflkj lskjdhfgsf' => 'Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.',
	'gsdfbs dfgsdfg' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. ',
	'sfmnsldfglkjsöldkfgöshögrihpe5nm ' => 'Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit ameLorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua',
	'sfmnsldfglkjsöldkfgöshögrihpe5nm erjk dfsjlk jgl nsdjfg  sfgl jsgk slfjgklsnfgjnlskj    sf sgjlskdnfg sfdg sfjg sfg sndgjsd fgslfg sjdfglsldkjfg  s fgknskgf ls gkglsnglk sfg sfklg skdf g sdf' => 'Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit ameLorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua',
	'sgsdfgsd f' => 'Vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat',
	'ertdgb cvcb rt' => 'At accusam aliquyam diam diam dolore dolores duo eirmod eos erat, et nonumy sed tempor et et invidunt justo labore Stet clita ea et gubergren, kasd magna no rebum. sanctus sea sed takimata ut vero voluptua. est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat.',
];


$pdf->setCreator('tc-lib-pdf');
$pdf->setAuthor('John Doe');
$pdf->setSubject('tc-lib-pdf generic example');
$pdf->setTitle('Example');
$pdf->setKeywords('TCPDF tc-lib-pdf generic example');

$pdf->setViewerPreferences(['DisplayDocTitle' => true]);



// create a page with defaultPageContent to determine the top and bottom content boundaries
$page = $pdf->addPage([
	'orientation' => 'P'
]);
$pdf->page->delete();
$pageSettings = [
	'autobreak' => true,
	'margin' => [
		'PL' => $pdf->_setup['marginleft'],
		'PR' => $pdf->_setup['marginright'],
		'CT' => $pdf->_contentCoordinates['top'],
		'CB' => $page['height'] - $pdf->_contentCoordinates['top'] - $pdf->_contentCoordinates['bottom']
	],
	[
		'RX' => $pdf->_setup['marginleft'],
		'RY' => $pdf->_contentCoordinates['top'],
		'RW' => $page['width'] - $pdf->_setup['marginright'],
		'RH' => $page['height'] - $pdf->_contentCoordinates['top'] - $pdf->_contentCoordinates['bottom']
	],
	'orientation' => $pdf->_setup['orientation']
];

// add the actual page
$page = $pdf->addPage($pageSettings);


$pdf->graph->setPageWidth($page['width']);
$pdf->graph->setPageHeight($page['height']);

$pdf->setBookmark('test', '', 0, -1, 0, 0, 'B', '');

// init previousBox in most upper left corner of region
$previousBox = $pageStart = [
	'y' => $pdf->_contentCoordinates['top'],
	'h' => 0
];


for($i = 0; $i < count(array_keys($contents)); $i++){
	$name = array_keys($contents)[$i];
	$content = $contents[$name];

	$pdf->page->setY($previousBox['y']  + $previousBox['h']);

	$caption = $pdf->getTextCell(
		$name, // string content
		$page['margin']['PL'], // float x
		$pdf->page->getY(), // float y
		$page['width'] - $page['margin']['PL'] - $page['margin']['PR'] - 30, // float width
		0, // float $height = 0,
		0, // float $offset = 0,
		2, // float $linespace = 0,
		'T', // string $valign = 'C',
		'L', // string $halign = 'C',
		null,
		[
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
		],
		true
	);
	// set current y coordinate and check if a pagebreak is applicable
	$previousBox = $pdf->getLastBBox();
	$pdf->page->setY($previousBox['y']  + $previousBox['h']);
	if ($pdf->page->getY() > $page['height'] - $pdf->_contentCoordinates['bottom']){
		$previousBox = $pageStart;
		$page = $pdf->addPage($pageSettings);
		$i--;
		continue;
	}

	$_content = $pdf->getTextCell(
		$content,// string content
		$page['margin']['PL'] + 10, // float x
		$pdf->page->getY(), // float y
		$page['width'] - $page['margin']['PL'] - $page['margin']['PR'] - 20, // float width
		0, // float $height = 0,
		0, // float $offset = 0,
		2, // float $linespace = 0,
		'T', // string $valign = 'C',
		'J', // string $halign = 'C',
		null,
		[
			'all' => [
				'lineWidth' => 1,
				'lineCap' => 'round',
				'lineJoin' => 'round',
				'miterLimit' => 1,
				'dashArray' => [],
				'dashPhase' => 0,
				'lineColor' => 'red',
				'fillColor' => 'blue',
			]
		],
		true
	);
	// set current y coordinate and check if a pagebreak is applicable
	$previousBox = $pdf->getLastBBox();
	$pdf->page->setY($previousBox['y']  + $previousBox['h']);
	if ($pdf->page->getY() > $page['height'] - $pdf->_contentCoordinates['bottom']){
		$previousBox = $pageStart;
		$page = $pdf->addPage($pageSettings);
		$i--;
		continue;
	}

	$font = $pdf->font->insert($pdf->pon, 'helvetica', 'B', 10);
	$pdf->page->addContent($font['out']);
	$pdf->page->addContent($caption);

	$font = $pdf->font->insert($pdf->pon, 'helvetica', '', 10);
	$pdf->page->addContent($font['out']);
	$pdf->page->addContent($_content);

}


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
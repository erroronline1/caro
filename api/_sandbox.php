<?php
namespace CARO\API;
require_once('_config.php');
require_once('_utility.php'); // general utilities
//require_once('_erpinterface.php');
//var_dump(ERPINTERFACE->orderdata());

function alterImage($file, $maxSize = 1024, $destination = UTILITY_IMAGE_REPLACE, $forceOutputType = false, $label = '', $watermark = '', $watermarkPattern = false){
		if (is_file($file)){
			$filetype = getimagesize($file)[2];
			switch($filetype){
				case "1": //gif
					$input = imagecreatefromgif($file);
					break;
				case "2": //jpeg
					$input = imagecreatefromjpeg($file);
					break;
				case "3": //png
					$input = imagecreatefrompng($file);
					break;
			}
		}
		else $input = imagecreatefromstring($file); // bytestring
		if ($input) {
			$filename = pathinfo($file)['basename'];
			// resizing
			$src = ['w' => imagesx($input), 'h' => imagesy($input)];
			if ($maxSize > 0 && $src['w'] >= $src['h'] && $src['w'] > $maxSize) $resize = $maxSize / $src['w'];
			elseif ($maxSize > 0 && $src['w'] < $src['h'] && $src['h'] > $maxSize) $resize = $maxSize / $src['h'];
			else $resize = 1;
			$new = ['w' => ceil($src['w'] * $resize), 'h' => ceil($src['h'] * $resize)];
			$output = imagecreatetruecolor($new['w'], $new['h']);
			imagefill($output, 0, 0, imagecolorallocatealpha($input, 0, 0, 0, 127));
			imagealphablending($output, false);
			imagesavealpha($output, true);
			imagecolortransparent($output, imagecolorallocate($output, 0, 0, 0));
			imagecopyresampled($output, $input, 0, 0, 0, 0, $new['w'], $new['h'], $src['w'], $src['h']);

			// patterned watermark
			if ($watermarkPattern){
				// create a gradient ressource
				// adapted from https://www.php.net/manual/en/function.imagefill.php#93920
				$gradient = imagecreatetruecolor($new['w'], $new['h']);
				$colors = [
					[255, 0, 0], // top left
					[0, 255, 0], // top right
					[0, 0, 255], // bottom left
					[255, 255, 0], // bottom right
				];

				$rgb = $colors[0]; // start with top left color
				for($x = 0; $x <= $new['w']; $x++) { // loop columns
					for($y = 0; $y <= $new['h']; $y++) { // loop rows
					// set pixel color 
					$col = imagecolorallocate($gradient, $rgb[0], $rgb[1], $rgb[2]);
					imagesetpixel($gradient, $x - 1, $y - 1, $col);
					// calculate new color  
					for($i = 0; $i <= 2; $i++) {
						$rgb[$i] =
							$colors[0][$i] * (($new['w'] - $x) * ($new['h'] - $y) / ($new['w'] * $new['h'])) +
							$colors[1][$i] * ($x * ($new['h'] - $y) / ($new['w'] * $new['h'])) +
							$colors[2][$i] * (($new['w'] - $x) * $y / ($new['w'] * $new['h'])) +
							$colors[3][$i] * ($x * $y / ($new['w'] * $new['h']));
						}
					}

				}

				// load pattern
				$tileSrc = imagecreatefrompng('../media/favicon/watermarkpattern.png');
				$tileSrc_dim = ['w' => imagesx($tileSrc), 'h' => imagesy($tileSrc)];

				// create smaller pattern ressource based on output dimensions
				$tile_w = max($new['w'] * .035, 15);
				$tile_h = $tile_w * $tileSrc_dim['h'] / $tileSrc_dim['w'] ;
				$tile = imagecreatetruecolor($tile_w, $tile_h);
				imagealphablending($tile, false);
				imagesavealpha($tile, true);
				imagecopyresampled($tile, $tileSrc, 0, 0, 0, 0, $tile_w, $tile_h, $tileSrc_dim['w'], $tileSrc_dim['h']);
				$tileSrc = null;

				// create a tiled ressource as mask
				$tiled = imagecreatetruecolor($new['w'], $new['h']);
				imagealphablending($tiled, false);
				imagesavealpha($tiled, true);
				imagesettile($tiled, $tile);
				imagefilledrectangle($tiled, 0, 0, $new['w'], $new['h'], IMG_COLOR_TILED);
				$tile = null;

				// create the gradient masked by tiled
				$watermarkLayer = imagecreatetruecolor($new['w'], $new['h']);;
				imagealphablending($tiled, false);
				imagesavealpha($watermarkLayer, true);
				imagefill($watermarkLayer, 0, 0, imagecolorallocatealpha($watermarkLayer, 0, 0, 0, 127));

				// Perform pixel-based alpha map application
				// adapted from https://stackoverflow.com/a/10942364
				for ($x = 0; $x < $new['w']; $x++) {
					for ($y = 0; $y < $new['h']; $y++) {
						$mask = imagecolorsforindex($tiled, imagecolorat($tiled, $x, $y));
						$color = imagecolorsforindex($gradient, imagecolorat($gradient, $x, $y));
						imagesetpixel($watermarkLayer, $x, $y, imagecolorallocatealpha( $watermarkLayer, $color[ 'red' ], $color[ 'green' ], $color[ 'blue' ], $mask['alpha'])); // apply the masks alpha value
					}
				}

				// merge the gradient watermark with output
				imagecopymerge($output, $watermarkLayer, 0, 0, 0, 0, $new['w'], $new['h'], 35);
				$tiled = $watermarkLayer = null;
			}

			$scale = .15; // of shorter length
			$opacity = 20; // for watermark
			// labelling
			if ($label){
				if ($new['w'] >= $new['h']) $height = $new['h'] * $scale / 1.5;
				else $height = $new['w'] * $scale / 1.5;
				$height = ceil($height);
				$input = imagecreatetruecolor($new['w'], $height);
				imagecolortransparent($input, imagecolorallocate($input, 0, 0, 0));
				$textcolor = imagecolorallocate($input, 1, 1, 1);
				imagefttext($input, $height / 2, 0, ceil($height / 5), $height - ceil($height / 7), $textcolor,  __DIR__ . '/../media/UbuntuMono-R.ttf', $label);
				$textcolor = imagecolorallocate($input, 255, 255, 255);
				imagefttext($input, $height / 2, 0, ceil($height / 6), $height - ceil($height / 6), $textcolor,  __DIR__ . '/../media/UbuntuMono-R.ttf', $label);
				imagecopymerge($output, $input, 0, $new['h'] - $height, 0, 0, $new['w'], $new['h'], 99);
			}

			// watermark on lower right
			if ($watermark && is_file($watermark)){
				$filetype = getimagesize($watermark)[2];
				switch($filetype){
					case "1": //gif
						$input = imagecreatefromgif($watermark);
						break;
					case "2": //jpeg
						$input = imagecreatefromjpeg($watermark);
						break;
					case "3": //png
						$input = imagecreatefrompng($watermark);
						break;
				}
				$wm = ['w' => imagesx($input), 'h' => imagesy($input)];
				if ($new['w'] >= $new['h']) $resize = $new['h'] * $scale / $wm['w'];
				else $resize = $new['w'] * $scale / $wm['h'];
				$newwm = ['w' => ceil($wm['w'] * $resize), 'h' => ceil($wm['h'] * $resize)];
				$stamp = imagecreatetruecolor($newwm['w'], $newwm['h']);
				imagecolortransparent($stamp, imagecolorallocate($stamp, 0, 0, 0));
				imagecopyresampled($stamp, $input, 0, 0, 0, 0, $newwm['w'], $newwm['h'], $wm['w'], $wm['h']);

				imagecopymerge($output, $stamp, $new['w'] - $newwm['w'], $new['h'] - $newwm['h'], 0, 0, $newwm['w'], $newwm['h'], $opacity);
			}
	
			if ($destination & UTILITY_IMAGE_REPLACE){
				chmod($file, 0777);
				switch($filetype){
					case "1": //gif
						imagegif($output, $file);
						break;
					case "2": //jpeg
						imagejpeg($output, $file, 100);
						break;
					case "3": //png
						imagepng($output, $file, 0);
						break;
				}
				return;
			}

			if ($forceOutputType){
				$newtype = array_search($forceOutputType, ['gif', 'jpeg', 'png', 'jpg']);
				if ($newtype !== false){
					if ($newtype == 3) $newtype = 1;
					$filetype = $newtype + 1;
					$filename = pathinfo($file)['filename'] . $forceOutputType;
				}
			}

			if ($destination & UTILITY_IMAGE_STREAM) {
					header("Content-type: application/octet-stream");
					header("Content-Disposition: attachment; filename=" . $filename);
			}
			ob_start();	
			switch($filetype){
				case "1": //gif
					imagegif($output, null);
					break;
				case "2": //jpeg
					imagejpeg($output, null, 100);
					break;
				case "3": //png
					imagepng($output, null, 6);
					break;
			}
			if ($destination & UTILITY_IMAGE_STREAM) {
				ob_flush();
			}
			$return = ob_get_contents();
			ob_end_clean(); 
			return $return;
		}
}


alterImage('../assets/CAROsignature.jpg', 512, UTILITY_IMAGE_STREAM, false, '', '', true);

die();
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
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

define("FILEHANDLER_IMAGE_REPLACE", 0x1);
define("FILEHANDLER_IMAGE_STREAM", 0x2);
define("FILEHANDLER_IMAGE_RESOURCE", 0x4);

class FILEHANDLER{
	public $_pdo = null;

	/**
	 * @param object $_pdo current pdo necessary if files are to be stored in a database
	 */
	public function __construct($_pdo = null){
		$this->_pdo = $_pdo;
	}


	/**
	 *       _ _           _                   
	 *   ___| | |_ ___ ___|_|_____ ___ ___ ___ 
	 *  | .'| |  _| -_|  _| |     | .'| . | -_|
	 *  |__,|_|_| |___|_| |_|_|_|_|__,|_  |___|
	 *                                |___|
	 * @param string $file filename
	 * @param int $maxSize max pixels on longest side, 0 keeps original size
	 * @param int $destination const 2^n FILEHANDLER_IMAGE_REPLACE | FILEHANDLER_IMAGE_STREAM | FILEHANDLER_IMAGE_RESOURCE 
	 * @param string|bool $forceOutputType gif|jpeg|png|jpg
	 * @param string $label written on the image
	 * @param string $watermark path to image for watermarking
	 * @param bool $watermarkPattern gradient watermark overlay especially for digital signatures
	 * 
	 * @return string|object|null a GdImage ressource or no return
	 */
	public static function alterImage($file, $maxSize = 1024, $destination = FILEHANDLER_IMAGE_REPLACE, $forceOutputType = false, $label = '', $watermark = '', $watermarkPattern = false){
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

			// patterned watermark to raise the difficulty in misusing a signature
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
					$col = imagecolorallocate($gradient, intval($rgb[0]), intval($rgb[1]), intval($rgb[2]));
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
				$tile_w = ceil(max($new['w'] * .035, 15));
				$tile_h = ceil($tile_w * $tileSrc_dim['h'] / $tileSrc_dim['w']);
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

				// blur edges a bit to make it more difficult to select edges for removing watermark
				imagefilter($output, IMG_FILTER_SMOOTH, 10);
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
	
			if ($destination & FILEHANDLER_IMAGE_REPLACE){
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

			if ($destination & FILEHANDLER_IMAGE_STREAM) {
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
			if ($destination & FILEHANDLER_IMAGE_STREAM) {
				ob_flush();
			}
			$return = ob_get_contents();
			ob_end_clean(); 
			return $return;
		}
	}

	/**
	 *                   _         _ _             _               
	 *   ___ ___ ___ ___| |_ ___ _| |_|___ ___ ___| |_ ___ ___ _ _ 
	 *  |  _|  _| -_| .'|  _| -_| . | |  _| -_|  _|  _| . |  _| | |
	 *  |___|_| |___|__,|_| |___|___|_|_| |___|___|_| |___|_| |_  |
	 *                                                        |___|
	 * creates a directory and secures by default (even if parent directory might already be inaccessible)
	 * @param string $dir path
	 * 
	 * @return bool on success
	 */
	public static function createDirectory($dir){
		if (!file_exists($dir) && mkdir($dir, 0777, true)) {
			if (!file_exists($dir . '/.htaccess')) {
				$file = fopen($dir . '/.htaccess', 'w');
				fwrite($file, "Order deny,allow\n<FilesMatch \"*\" >\nDeny from all\n</FilesMatch>");
				fclose($file);
			}
			if (!file_exists($dir . '/web.config')) {
				$file = fopen($dir . '/web.config', 'w');
				fwrite($file, '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><rewrite><rules><rule name="deny"><match url=".*" ignoreCase="true" /><action type="CustomResponse" statusCode="403" statusReason="Forbidden" statusDescription="Forbidden" /></rule></rules></rewrite></system.webServer></configuration>');
				fclose($file);
			}
			return true;
		}
		return false;
	}

	/**
	 *     _     _     _
	 *   _| |___| |___| |_ ___
	 *  | . | -_| | -_|  _| -_|
	 *  |___|___|_|___|_| |___|
	 *
	 * deletes files and folders recursively unregarding of content!
	 * 
	 * @param string|array $paths 
	 * 
	 * @return bool about success
	 */
	public static function delete($paths = []){
		$result = false;
		$allowed = false;
		if (gettype($paths) === 'string') $paths=[$paths];
		foreach ($paths as $path) {
			foreach (array_keys(CONFIG['fileserver']) as $fileserver){
				if (stristr($path, self::directory($fileserver))) $allowed = true;
			}
			if (!$allowed) return false;
			if (is_file($path)){
				$result = unlink($path);
			}
			elseif (is_dir($path)){
				foreach (scandir($path) as $subdir){
					if (is_file($path . '/' . $subdir)) unlink($path . '/' . $subdir);
					if (is_dir($path . '/' . $subdir) && !in_array($subdir, ['.','..'])) self::delete($path . '/' . $subdir);
				}
				$result = rmdir($path);
			}
		}
		return $result;
	}

	/**
	 *     _ _             _
	 *   _| |_|___ ___ ___| |_ ___ ___ _ _
	 *  | . | |  _| -_|  _|  _| . |  _| | |
	 *  |___|_|_| |___|___|_| |___|_| |_  |
	 *                                |___|
	 * returns folders defined in config.ini
	 * 
	 * @param string $request key
	 * @param array $replace optional named array with replacements
	 * 
	 * @return string directory
	 */
	public static function directory($request, $replace = []){
		if (!isset(CONFIG['fileserver'][$request])){
			return '../fileserver';
		}
		$patterns = [];
		$replacements = [];
		if ($replace){
			foreach ($replace as $pattern => $replacement){
				$patterns[] = '/' . $pattern . '/';
				$replacements[] = $replacement;
			}
		}
		else {
			$patterns[] = '/\/:\w{1,}/';
			$replacements[] = '';
		}
		return '../' . preg_replace($patterns, $replacements, CONFIG['fileserver'][$request]);
	}

	/**
	 *   _ _     _   
	 *  | |_|___| |_ 
	 *  | | |   | '_|
	 *  |_|_|_|_|_,_|
	 *
	 * unified link modifier, creating previews where applicable
	 * 
	 * @param array $attributes anchor attributes
	 * @return array modified attributes
	 */
	public static function link($attributes){
		if ($attributes['href']){
			$file = pathinfo($attributes['href']);
			if (in_array(strtolower($file['extension']), ['stl', 'obj'])){
				$attributes['href'] = "javascript:new _client.Dialog({type: 'preview', header: '" . $file['basename'] . "', render:{type: 'stl', name: '" . $file['basename'] . "', url: '" . $attributes['href'] . "'}})";
				$attributes['data-type'] = 'stl';
			}
			elseif (in_array(strtolower($file['extension']), ['png','jpg', 'jpeg', 'gif'])){
				$attributes['href'] = "javascript:new _client.Dialog({type: 'preview', header: '" . $file['basename'] . "', render:{type: 'image', name: '" . $file['basename'] . "', content: '" . $attributes['href'] . "'}})";
				$attributes['data-type'] = 'imagelink';
			}
			else {
				$attributes['target'] = '_blank';
			}
		}
		return $attributes;
	}

	/**
	 *   _ _     _     _ _             _           _
	 *  | |_|___| |_ _| |_|___ ___ ___| |_ ___ ___|_|___ ___
	 *  | | |_ -|  _| . | |  _| -_|  _|  _| . |  _| | -_|_ -|
	 *  |_|_|___|_| |___|_|_| |___|___|_| |___|_| |_|___|___|
	 *
	 * scans a directory and returns contained subdirectories
	 * 
	 * @param string $folder folder to scan
	 * @param string $order asc|desc by default
	 * 
	 * @return array file list 
	 */
	public static function listDirectories($folder, $order = 'desc'){
		$result = [];
		if (!file_exists($folder)) return $result;
		switch ($order){
			case 'desc':
				$dir = scandir($folder, SCANDIR_SORT_DESCENDING);
				break;
			case 'asc':
				$dir = scandir($folder);
				break;
		}
		foreach ($dir as $file){
			if (is_dir($folder . '/' . $file) && !in_array($file, ['.', '..'])) $result[] = $folder . '/' . $file;
		}
		return $result;
	}

	/**
	 *   _ _     _   ___ _ _
	 *  | |_|___| |_|  _|_| |___ ___
	 *  | | |_ -|  _|  _| | | -_|_ -|
	 *  |_|_|___|_| |_| |_|_|___|___|
	 *
	 * scans a directory and returns contained files
	 * 
	 * @param string $folder folder to scan
	 * @param string $order asc|desc by default
	 * 
	 * @return array file list 
	 */
	public static function listFiles($folder, $order = 'desc'){
		$result = [];
		if (!file_exists($folder)) return $result;
		switch ($order){
			case 'desc':
				$dir = scandir($folder, SCANDIR_SORT_DESCENDING);
				break;
			case 'asc':
				$dir = scandir($folder);
				break;
		}
		foreach ($dir as $file){
			if (is_file($folder . '/' . $file) && !in_array($file, [
				'.htaccess',
				'web.config'
			])) $result[] = $folder . '/' . $file;
		}
		return $result;
	}

	/**
	 *       _                       _           _       _ ___ _ _
	 *   ___| |_ ___ ___ ___ _ _ ___| |___ ___ _| |___ _| |  _|_| |___ ___
	 *  |_ -|  _| . |  _| -_| | | . | | . | .'| . | -_| . |  _| | | -_|_ -|
	 *  |___|_| |___|_| |___|___|  _|_|___|__,|___|___|___|_| |_|_|___|___|
	 *                          |_|
	 * routes the uploaded files to the destination handler
	 * 
	 * @param array $input mandatory array of input names
	 * @param array $destination  
	 * **either database:** [  
	 *     'context' => string, mandatory
	 *     'expiration_date' => string Y-m-d H:i:s,  
	 *     'metadata' => array|string named array or stringified json  
	 * ]  
	 * **or filesystem:** [  
	 *     'path' => string, mandatory  
	 *     'replace' => bool, to replace files, otherwise enumerated if applicable  
	 * ]
	 * @param array $naming [  
	 *     'prefix' => string|array, to add to filename, array length according to $files,  
	 *     'rename' => string|array, to rename file, array length according to $files  
	 * ]
	 * @param array $imageoptions [  
	 *     'forceimgtype' => string, change image type  
	 *     'label' => string, add a text label in lower left corner  
	 *     'size' => integer, max value for the longer side  
	 *     'watermark' => bool, add a watermark in lower right corner according to config-file  
	 * ]
	 * 
	 * @return array paths of stored files or media-table ids
	*/
	public function storeUploadedFiles($input = [], $destination = [], $naming = [], $imageoptions = []){
		if (!$input || (empty($destination['path']) && empty($destination['context']))) return [];
		$targets = [];

		if (isset($destination['path']) && !file_exists($destination['path'])) self::createDirectory($destination['path']);

		// iterate over $_FILE input names
		for ($i = 0; $i < count($input); $i++) {
			$inputname = $input[$i];
			if (gettype($_FILES[$inputname]['name']) !== 'array') {
				// convert to nested $_FILES just to be sure
				foreach($_FILES[$inputname] as $key => &$value){
					$value = [$value];
				}
			}

			//iterate over the provided files for the current input
			for ($j = 0; $j < count($_FILES[$inputname]['name']); $j++){
				if (!$_FILES[$inputname]['tmp_name'][$j]) continue;
				$file = pathinfo($_FILES[$inputname]['name'][$j]);

				// apply renames and prefixes if applicable
				if ($rename = $naming['rename'] ?? null){
					if (gettype($rename) === 'array' && isset($rename[$j])) $rename = $rename[$j]; // otherwise supposedly a string or int, you never know
					$file['filename'] = strval($rename);
				}
				if ($prefix = $naming['prefix'] ?? null){
					if (gettype($prefix) === 'array' && isset($prefix[$j])) $prefix = $prefix[$j]; // otherwise supposedly a string or int, you never know
					$file['filename'] = strval($prefix) . '_'. $file['filename'];
				}
				// resanitze filename just to be sure
				$file['filename'] = preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $file['filename']);

				// process current file and expect a path or database id
				$targets[] = 
					isset($destination['path'])
					? $this->saveToFilesystem(
						$_FILES[$inputname]['tmp_name'][$j],
						$destination['path'] . '/' . $file['filename'] . '.' . $file['extension'],
						$destination['replace'] ?? null,
						$imageoptions)
					: $this->saveToDatabase(
						$this->_pdo,
						$_FILES[$inputname]['tmp_name'][$j],
						$file['filename'] . '.' . $file['extension'],
						$_FILES[$inputname]['type'][$j],
						$destination,
						$imageoptions)
				;
			}
		}
		return $targets; // including path e.g. to store in database if needed, has to be prefixed with "api/" eventually 
	}
	/**
	 * @param string $tmpname temporary file
	 * @param string destination expected path with full filename and extension
	 * @param bool $replace overwrite existing file or enumerate if already present
	 * @param array $imageoptions
	 * 
	 * @return string destination path, occasionally enumerated 
	 */
	public static function saveToFilesystem($tmpname, $destination, $replace = false, $imageoptions = []){
		$file = pathinfo($destination);
		if (!$replace){
			$extension = '.' . $file['extension'];
			$files = glob(str_replace($extension, '*' . $extension, $destination)); // find all ./directory/subdirectory/filename*.ext
			$destination = self::enumerate($destination, $files);
		}
		// move_uploaded_file is for post only, else rename for put files
		if ($tmpname && (@move_uploaded_file($tmpname, $destination) || rename( $tmpname, $destination))){
			// alter images by default to strip metadata for data safety reasons
			// also apply watermark pattern for CARO signatures by default.
			if (in_array(strtolower($file['extension']), ['jpg', 'jpeg', 'png', 'gif'])){
				self::alterImage($destination, $imageoptions['size'] ?? null, FILEHANDLER_IMAGE_REPLACE, false, $imageoptions['label'] ?? null, $imageoptions['watermark'] ?? null, boolval(stristr($file['filename'], 'CAROsignature')));
			}
			return $destination;
		}
	}
	/**
	 * @param string $tmpname temporary file
	 * @param string destination expected path with full filename and extension
	 * @param bool $replace overwrite existing file or enumerate if already present
	 * @param array $imageoptions
	 * 
	 * @return int destination database id 
	 */
	public static function saveToDatabase($_pdo, $tmpname, $filename, $mime_type, $destination, $imageoptions = []){
		$file = pathinfo($filename);
		$present = SQLQUERY::EXECUTE($_pdo, 'media_get_info_by_name_and_context', [
			'values' => [
				':name' => $file['filename'],
				':extension' => '.' . $file['extension'],
				':context' => $destination['context']
			]
		]);
		
		$filename = self::enumerate($filename, array_column($present, 'name'));
		$fileHandle = fopen($tmpname, 'rb');
		$fileContents = fread($fileHandle, filesize($tmpname));
    	fclose($fileHandle);

		// alter images by default to strip metadata for data safety reasons
		// also apply watermark pattern for CARO signatures by default.
		if (in_array(strtolower($file['extension']), ['jpg', 'jpeg', 'png', 'gif'])){
			$fileContents = self::alterImage($fileContents, $imageoptions['size'] ?? null, FILEHANDLER_IMAGE_RESOURCE, false, $imageoptions['label'] ?? null, $imageoptions['watermark'] ?? null, boolval(stristr($file['name'], 'CAROsignature')));
		}

		$destination['metadata'] = $destination['metadata'] ?? null;
		if (gettype($destination['metadata']) === 'array') $destination['metadata'] = UTILITY::json_encode($destination['metadata']);
		if (SQLQUERY::EXECUTE($_pdo, 'media_post', [
			'values' => [
				':context' => $destination['context'],
				':name' => $filename,
				':mime_type' => $mime_type,
				':content' => $fileContents,
				':upload_date' => date('Y-m-d H:i:s'),
				':expiry_date' => $destination['expiry_date'] ?? null,
				':metadata' => $destination['metadata']
			]
		])) return $_pdo->lastInsertId();
	}
	/**
	 * @param string $target filename to be appenden a number if found in
	 * @param array $withinfiles as list of filenames
	 */
	private static function enumerate($target, $withinfiles){
		if (in_array($target, $withinfiles)){
			$pi_target = pathinfo($target);
			preg_match('/\((\d+)\)$/m', $pi_target['filename'], $matches, PREG_OFFSET_CAPTURE, 0);
			if ($matches) $enumeratedTarget = str_replace($matches[0][0], '(' . (intval($matches[1][0]) + 1) . ')', $pi_target['filename']);
			else $enumeratedTarget = $pi_target['filename'] . '(2)';
			$target = self::enumerate(str_replace($pi_target['filename'], $enumeratedTarget, $target), $withinfiles);
		}
		return $target;
	}

	/**
	 *   _   _   _       _ _
	 *  | |_|_|_| |_ _ _| |_|___
	 *  |  _| | . | | | . | |  _|
	 *  |_| |_|___|_  |___|_|_|
	 *            |___|
	 * prepares a folder according to config.ini and deletes files if lifespan is set
	 * 
	 * @param string $dir one of the fileserver keys
	 * @param int $lifespan in hours
	 * 
	 * @return bool if 
	 */
	public static function tidydir($dir = '', $lifespan = null){
		if (!$dir) return false;
		$success = !file_exists(self::directory($dir)) ? self::createDirectory(self::directory($dir)) : true;
		if ($lifespan && file_exists(self::directory($dir))){
			$files = self::listFiles(self::directory($dir), 'asc');
			foreach ($files as $file){
				if ((time() - filemtime($file)) / 3600 > $lifespan) {
					self::delete($file);
				}
			}
		}
		return $success;
	}
}

?>
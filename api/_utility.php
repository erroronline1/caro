<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
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

define("UTILITY_IMAGE_REPLACE", 0x1);
define("UTILITY_IMAGE_STREAM", 0x2);
define("UTILITY_IMAGE_RESOURCE", 0x4);

class UTILITY {

	/**
	 *       _ _           _                   
	 *   ___| | |_ ___ ___|_|_____ ___ ___ ___ 
	 *  | .'| |  _| -_|  _| |     | .'| . | -_|
	 *  |__,|_|_| |___|_| |_|_|_|_|__,|_  |___|
	 *                                |___|
	 * @param string $file filename
	 * @param int $maxSize max pixels on longest side, 0 keeps original size
	 * @param int $destination const 2^n UTILITY_IMAGE_REPLACE | UTILITY_IMAGE_STREAM | UTILITY_IMAGE_RESOURCE 
	 * @param string|bool $forceOutputType gif|jpeg|png|jpg
	 * @param string $label written on the image
	 * @param string $watermark path to image for watermarking
	 * 
	 * @return string|object|null a GdImage ressource or no return
	 */
	public static function alterImage($file, $maxSize = 1024, $destination = UTILITY_IMAGE_REPLACE, $forceOutputType = false, $label = '', $watermark = ''){
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
			imagedestroy($input);

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
				imagefttext($input, $height / 2, 0, ceil($height / 5), $height - ceil($height / 7), $textcolor, '../media/UbuntuMono-R.ttf', $label);
				$textcolor = imagecolorallocate($input, 255, 255, 255);
				imagefttext($input, $height / 2, 0, ceil($height / 6), $height - ceil($height / 6), $textcolor, '../media/UbuntuMono-R.ttf', $label);
				imagecopymerge($output, $input, 0, $new['h'] - $height, 0, 0, $new['w'], $new['h'], 99);
				imagedestroy($input);
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
				imagedestroy($input);

				imagecopymerge($output, $stamp, $new['w'] - $newwm['w'], $new['h'] - $newwm['h'], 0, 0, $newwm['w'], $newwm['h'], $opacity);
				imagedestroy($stamp);
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
				imagedestroy($output);
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
			imagedestroy($output);
			if ($destination & UTILITY_IMAGE_STREAM) {
				ob_flush();
			}
			$return = ob_get_contents();
			ob_end_clean(); 
			return $return;
		}
	}

	/**
	 *       _             _             _
	 *   ___| |___ ___ ___|_|___ ___ _ _| |_ ___
	 *  |  _| | -_| .'|   | |   | . | | |  _|_ -|
	 *  |___|_|___|__,|_|_|_|_|_|  _|___|_| |___|
	 *                          |_|
	 * trim input data
	 */
	public static function cleanInputs($data){
		$clean_input = [];
		if(is_array($data)){
			foreach($data as $k => $v){
				$clean_input[$k] = self::cleanInputs($v);
			}
		} else {
			if ($data) $clean_input = trim($data);
		}
		return $clean_input;
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
	private static function createDirectory($dir){
		if (!file_exists($dir) && mkdir($dir, 0777, true)) {
			self::secureDirectory($dir);
			return true;
		}
		return false;
	}

	/**
	 *     _     _           
	 *   _| |___| |_ _ _ ___ 
	 *  | . | -_| . | | | . |
	 *  |___|___|___|___|_  |
	 *                  |___|
	 * displays error reports by var_dumping id debug mode is on per config
	 */
	public static function debug(...$vars){
		if (CONFIG['application']['debugging'])	var_dump(...$vars);
		else echo "there may have been an error, however debug mode has been turned off." . PHP_EOL;
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
				foreach(scandir($path) as $subdir){
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
			foreach($replace as $pattern => $replacement){
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
	 *   ___         _   _   _   _                           
	 *  |  _|___ ___| |_|_|_| |_| |___ ___ ___ ___ _____ ___ 
	 *  |  _| . |  _| . | | . | . | -_|   |   | .'|     | -_|
	 *  |_| |___|_| |___|_|___|___|___|_|_|_|_|__,|_|_|_|___|
	 *
	 * matches a string against CONFIG['forbidden']['names']
	 * optional override of a defined pattern if key matches config or append if not
	 * @param string $name
	 * @param array $pattern
	 * @return string|false matched pattern or not matched
	 */
	public static function forbiddenName($name, $extendedpattern = []){
		foreach(CONFIG['forbidden']['names'] as $key => $pattern) {
			if (isset($extendedpattern[$key])) $pattern = $extendedpattern[$key];
			preg_match('/' . $pattern. '/m', $name, $match);
			if ($match) {
				return $pattern;
			}
		}
		foreach($extendedpattern as $key => $pattern) {
			preg_match('/' . $pattern. '/m', $name, $match);
			if ($match) {
				return $pattern;
			}
		}
		return false;
	}

	/**
	 *   _   _   _   _                 _   _         
	 *  | |_|_|_| |_| |___ ___ ___ ___| |_|_|___ ___ 
	 *  |   | | . | . | -_|   | . | . |  _| | . |   |
	 *  |_|_|_|___|___|___|_|_|___|  _|_| |_|___|_|_|
	 *                            |_|
	 * returns an altered option for selections
	 * it ain't much but is honest work by having the same pattern over the whole api
	 * adjust frontent assemble/dialog accordingly to return the correct values
	 * 
	 * @param string $string input
	 * @return string output
	 */
	public static function hiddenOption($string){
		return $string . ' [X]';
	}

	/**
	 *     _                                     _     
	 *    |_|___ ___ ___       ___ ___ ___ ___ _| |___ 
	 *    | |_ -| . |   |     | -_|   |  _| . | . | -_|
	 *   _| |___|___|_|_|_____|___|_|_|___|___|___|___|
	 *  |___|           |_____|
	 * wrapper for easier harmonization of cross api behaviour
	 * 
	 * @param array $array input
	 * @param flags normal bitmasked json_encode flags
	 * 
	 * @return string output
	 */
	public static function json_encode($array = [], $flags = null){
		return json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | $flags);
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
			if (in_array(strtolower($file['extension']), ['stl'])){
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
		$result=[];
		if (!file_exists($folder)) return $result;
		switch ($order){
			case 'desc':
				$dir = scandir($folder, SCANDIR_SORT_DESCENDING);
				break;
			case 'asc':
				$dir = scandir($folder);
				break;
		}
		foreach($dir as $i => $file){
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
		$result=[];
		if (!file_exists($folder)) return $result;
		switch ($order){
			case 'desc':
				$dir = scandir($folder, SCANDIR_SORT_DESCENDING);
				break;
			case 'asc':
				$dir = scandir($folder);
				break;
		}
		foreach($dir as $i => $file){
			if (is_file($folder . '/' . $file) && !in_array($file, [
				'.htaccess',
				'web.config'
			])) $result[] = $folder . '/' . $file;
		}
		return $result;
	}

	/**
	 *                                   _           _
	 *   ___ ___ ___ ___ ___ ___ ___ _ _| |___ ___ _| |
	 *  | . | .'|  _|_ -| -_| . | .'| | | | . | .'| . |
	 *  |  _|__,|_| |___|___|  _|__,|_  |_|___|__,|___|
	 *  |_|                 |_|     |___|
	 * prepares passed request parameters, mimics post data for put method
	 * 
	 * @return object with request parameters and their value
	 */
	public static function parsePayload(){
		switch($_SERVER['REQUEST_METHOD']){
			case "POST":
				if (!$_POST) { // has not been sent via multipartform
					$_POST = json_decode(file_get_contents("php://input"), true);
				}
				$payload = self::cleanInputs($_POST);
				break;
			case "GET":
				$payload = self::cleanInputs($_GET);
				break;
			case "DELETE":
				$payload = json_decode(file_get_contents("php://input"), true);
				$payload = self::cleanInputs($payload);
				break;
			case "PUT":
				/* PUT data comes in on the stdin stream */
				$putdata = fopen("php://input", "r");

				$raw_data = '';
				/* Read the data 1 KB at a time
				and write to the file */
				while ($chunk = fread($putdata, 1024))
					$raw_data .= $chunk;

				/* Close the streams */
				fclose($putdata);

				// Fetch content and determine boundary
				$boundary = substr($raw_data, 0, strpos($raw_data, "\r\n"));

				if(empty($boundary)){
					parse_str($raw_data,$data);
				}
				else {
					// Fetch each part
					$parts = array_slice(explode($boundary, $raw_data), 1);
					$data = array();

					foreach ($parts as $part){
						// If this is the last part, break
						if ($part == "--\r\n") break;

						// Separate content from headers
						$part = ltrim($part, "\r\n");
						list($raw_headers, $body) = explode("\r\n\r\n", $part, 2);

						// Parse the headers list
						$raw_headers = explode("\r\n", $raw_headers);
						$headers = array();
						foreach ($raw_headers as $header){
							//list($name, $value) = explode(':', $header); this original does not work with input names containing colons
							preg_match("/^(.+?):(.+?)$/m", $header, $formerlyexploded);
							list(, $name, $value) = $formerlyexploded;
							$headers[strtolower($name)] = ltrim($value, ' ');
						}

						// Parse the Content-Disposition to get the field name, etc.
						if (isset($headers['content-disposition'])){
							$filename = null;
							$tmp_name = null;
							preg_match(
								'/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/',
								$headers['content-disposition'],
								$matches
							);
							list(, $type, $name) = $matches;

							//Parse File
							if(isset($matches[4])){
								//get filename
								$filename = $matches[4];
								$fieldname = str_replace(' ', '_', preg_replace('/\[\]/', '', $matches[2]));

								//get tmp name
								$filename_parts = pathinfo( $filename );
								$tmp_name = tempnam( ini_get('upload_tmp_dir'), $filename_parts['filename']);

								//populate $_FILES with information, size may be off in multibyte situation
								$_files = [
									'error' => 0,
									'name' => $filename,
									'tmp_name' => $tmp_name,
									'size' => strlen( $body ),
									'type' => $value
								];
								
								$_FILES[$fieldname]['error'][] = $_files['error'];
								$_FILES[$fieldname]['name'][] = $_files['name'];
								$_FILES[$fieldname]['tmp_name'][] = $_files['tmp_name'];
								$_FILES[$fieldname]['size'][] = $_files['size'];
								$_FILES[$fieldname]['type'][] = $_files['type'];

								//place in temporary directory
								file_put_contents($tmp_name, $body);
							}
							//Parse Field
							else
							{
								$name = str_replace(' ', '_', $name);
								if (substr($name, -2) == '[]') { //is array
									$name = substr($name, 0, strlen($name)-2);
									if (isset($data[$name])) $data[$name][] = substr($body, 0, strlen($body) - 2);
									else $data[$name] = [substr($body, 0, strlen($body) - 2)];
								}
								else $data[$name] = substr($body, 0, strlen($body) - 2);
							}
						}
					}
				}
				$payload = self::cleanInputs($data);
				break;
			default:
				return [];
				break;
		}
		return (object) $payload;
	}		
	
	/**
	 *                           _               _
	 *   ___ ___ ___ ___ ___ ___| |_ _ _ ___ ___| |_
	 *  | . |  _| . | . | -_|  _|  _| | |_ -| -_|  _|
	 *  |  _|_| |___|  _|___|_| |_| |_  |___|___|_|
	 *  |_|         |_|             |___|
	 * shorthand checking for a set property
	 * 
	 * @param object|array $object to look within
	 * @param string $property to look for
	 * 
	 * @return array|string|bool property value or false
	 */
	public static function propertySet($object, $property){
		if (gettype($object) === 'array') return isset($object[$property]) ? $object[$property] : false;
		return (property_exists($object, $property) && boolval($object->{$property}) && $object->{$property} !== 'undefined') ? $object->{$property} : false;
	}

	/**
	 *                             _ _             _
	 *   ___ ___ ___ _ _ ___ ___ _| |_|___ ___ ___| |_ ___ ___ _ _
	 *  |_ -| -_|  _| | |  _| -_| . | |  _| -_|  _|  _| . |  _| | |
	 *  |___|___|___|___|_| |___|___|_|_| |___|___|_| |___|_| |_  |
	 *                                                        |___|
	 * secures a directory by inserting an .htaccess- and web.config file preventing any access without authorization
	 * @param string $dir path
	 * 
	 * @return bool on success
	 */
	public static function secureDirectory($dir){
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

	/**
	 *       _                       _           _       _ ___ _ _
	 *   ___| |_ ___ ___ ___ _ _ ___| |___ ___ _| |___ _| |  _|_| |___ ___
	 *  |_ -|  _| . |  _| -_| | | . | | . | .'| . | -_| . |  _| | | -_|_ -|
	 *  |___|_| |___|_| |___|___|  _|_|___|__,|___|___|___|_| |_|_|___|___|
	 *                          |_|
	 * moves uploaded files to folder according to input name, adds possible prefix
	 * 
	 * @param array $name mandatory array of input names
	 * @param string $folder where to store
	 * @param array $prefix to add to filename, length according to $files
	 * @param array $rename to rename file, length according to $files
	 * @param bool $replace to replace files, false adds an enumerator
	 * 
	 * @return array paths of stored files
	 */
	public static function storeUploadedFiles($name = [], $folder = '', $prefix = [], $rename = [], $replace = true){
		/* process $_FILES, store to folder and return an array of destination paths */
		if (!file_exists($folder)) self::createDirectory($folder);
		$targets = [];
		for ($i = 0; $i < count($name); $i++) {
			if (gettype($_FILES[$name[$i]]['name']) !== 'array') {
				$file = pathinfo($_FILES[$name[$i]]['name']);
				if ($rename && isset($rename[$i])) $file['filename'] = $rename[$i];
				if ($_FILES[$name[$i]]['tmp_name']) $targets[] = self::handle($_FILES[$name[$i]]['tmp_name'], $file['filename'] . '.' . $file['extension'], $i, $prefix, $folder, $replace);
		}
			else {
				for ($j = 0; $j < count($_FILES[$name[$i]]['name']); $j++){
					if (!$_FILES[$name[$i]]['tmp_name'][$j]) continue;
					$file = pathinfo($_FILES[$name[$i]]['name'][$j]);
					if ($rename && isset($rename[$i])) $file['filename'] = $rename[$i];
					$targets[] = self::handle($_FILES[$name[$i]]['tmp_name'][$j], $file['filename'] . '.' . $file['extension'], $i, $prefix, $folder, $replace);
				}
			}
		}
		return $targets; // including path e.g. to store in database if needed, has to be prefixed with "api/" eventually 
	}
	private static function handle($tmpname, $name, $i, $prefix, $folder, $replace = false){
		$_prefix = $prefix ? $prefix[(key_exists($i, $prefix) ? $i : count($prefix) - 1)] : null;
		$filename = preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', ($_prefix ? $_prefix . '_' : '') . $name);
		$target = $folder . '/' . $filename;
		if (!$replace){
			$extension = '.' . pathinfo($target)['extension'];
			$files = glob(str_replace($extension, '*' . $extension, $target));
			$target = self::enumerate($target, $files);
		}
		// move_uploaded_file is for post only, else rename for put files
		if ($tmpname && (@move_uploaded_file( $tmpname, $target) || rename( $tmpname, $target))){
			return $target;
		}
	}
	private static function enumerate($target, $withinfiles){
		if (in_array($target, $withinfiles)){
			$pi_target = pathinfo($target);
			preg_match('/\((\d)\)$/m', $pi_target['filename'], $matches, PREG_OFFSET_CAPTURE, 0);
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
			if ($files){
				foreach ($files as $file){
					$file = ['path' => $file, 'name' => pathinfo($file)['basename']];
					if ((time() - filemtime($file['path'])) / 3600 > $lifespan) {
						UTILITY::delete($file['path']);
					}
				}
			}
		}
		return $success;
	}
}

/**
 * permission handling, checking if necessary permissions have been given
 * 
 */
class PERMISSION {

	/**
	 *   ___     _ _                                   _
	 *  |  _|_ _| | |_ _ ___ ___ ___ ___ ___ _ _ ___ _| |
	 *  |  _| | | | | | | .'| . | . |  _| . | | | -_| . |
	 *  |_| |___|_|_|_  |__,|  _|  _|_| |___|\_/|___|___|
	 *              |___|   |_| |_|
	 * check whether an approvalcolumn has been fully approved according to function
	 * @param string $function as defined within config.ini
	 * @param string|array $approvalcolumn 'approval'-column
	 * @return bool
	 * 
	 */
	public static function fullyapproved($function = '', $approvalcolumn = ''){
		if (gettype($approvalcolumn) === 'string') $approvalcolumn = $approvalcolumn ? json_decode($approvalcolumn, true) : [];
		$approved = true;
		foreach(self::permissionFor($function, true) as $permission){
			if (!$approvalcolumn || !isset($approvalcolumn[$permission])) $approved = false;
		}
		return $approved;
	}

	/**
	 *                 _ _
	 *   ___ ___ ___ _| |_|___ ___
	 *  | . | -_|   | . | |   | . |
	 *  |  _|___|_|_|___|_|_|_|_  |
	 *  |_|                   |___|
	 * check whether an approvalcolumn has pending approvals according to function
	 * check per user permission so there is only one count per unapproved element even on multiple permissions
	 * @param string $function as defined within config.ini
	 * @param string|array $approvalcolumn 'approval'-column
	 * @return array of pending approval permission
	 * 
	 */
	public static function pending($function = '', $approvalcolumn = ''){
		if (gettype($approvalcolumn) === 'string') $approvalcolumn = $approvalcolumn ? json_decode($approvalcolumn, true) : [];
		$pending = [];
		foreach(self::permissionFor($function, true) as $permission){
			if (!$approvalcolumn || array_intersect(['admin', $permission], $_SESSION['user']['permissions']) && !isset($approvalcolumn[$permission])) $pending[] = $permission;
		}
		return $pending;
	}
	
	/**
	 *                     _         _         ___
	 *   ___ ___ ___ _____|_|___ ___|_|___ ___|  _|___ ___
	 *  | . | -_|  _|     | |_ -|_ -| | . |   |  _| . |  _|
	 *  |  _|___|_| |_|_|_|_|___|___|_|___|_|_|_| |___|_|
	 *  |_|
	 * returns a boolean if user is authorized for requested app-function, array of permissions if $returnvalues argument is true
	 * @param string $function as defined within config.ini
	 * @param bool $returnvalues
	 * @return bool|array
	 */
	public static function permissionFor($function = '', $returnvalues = false){
		if (!isset($_SESSION['user']) || !isset($_SESSION['user']['permissions'])) return [];
		if (isset(CONFIG['permissions'][$function])){
			if (!$returnvalues) {
				// limited functions don't include admin by default
				if (in_array($function, ['productslimited'])) return boolval(array_intersect([...preg_split('/\W+/', CONFIG['permissions'][$function])], $_SESSION['user']['permissions']));
				return boolval(array_intersect(['admin', ...preg_split('/\W+/', CONFIG['permissions'][$function])], $_SESSION['user']['permissions']));
			}
			return preg_split('/\W+/', CONFIG['permissions'][$function]);
		}
		UTILITY::debug('permission ' . $function . ' not found in config.ini file');
	}

	/**
	 *                     _         _         _     
	 *   ___ ___ ___ _____|_|___ ___|_|___ ___|_|___ 
	 *  | . | -_|  _|     | |_ -|_ -| | . |   | |   |
	 *  |  _|___|_| |_|_|_|_|___|___|_|___|_|_|_|_|_|
	 *  |_|
	 * 
	 * returns a boolean if user matches with passed authorizations
	 * @param array|string $auth as either array or comma separated string
	 * @return bool
	 */
	public static function permissionIn($auth){
		if (!$auth) return true;
		if (gettype($auth) === 'string') $auth = preg_split('/\W+/', $auth);
		return array_intersect(['admin', ...$auth], $_SESSION['user']['permissions']);
	}
}
?>
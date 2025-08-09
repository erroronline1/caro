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

namespace CARO\API;

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
			foreach ($data as $k => $v){
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
		foreach (CONFIG['forbidden']['names'] as $key => $pattern) {
			if (isset($extendedpattern[$key])) $pattern = $extendedpattern[$key];
			preg_match('/' . $pattern. '/m', $name, $match);
			if ($match) {
				return $pattern;
			}
		}
		foreach ($extendedpattern as $key => $pattern) {
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
	 *                                   _           _
	 *   ___ ___ ___ ___ ___ ___ ___ _ _| |___ ___ _| |
	 *  | . | .'|  _|_ -| -_| . | .'| | | | . | .'| . |
	 *  |  _|__,|_| |___|___|  _|__,|_  |_|___|__,|___|
	 *  |_|                 |_|     |___|
	 * prepares passed request parameters, mimics post data for put method
	 * preserves literal variable names without replacing whitepace, questionmark and alike with _
	 * empty file inputs are stripped from the payload
	 * 
	 * @return object with request parameters and their value
	 */
	public static function parsePayload(){
		switch($_SERVER['REQUEST_METHOD']){
			case "GET":
			case "DELETE":
				// according to https://stackoverflow.com/a/18209799/6087758
				$inputstream = preg_replace_callback(
					'/(^|(?<=&))[^=[&]+/',
					function($key) { return bin2hex(urldecode($key[0])); },
					$_SERVER['QUERY_STRING']
				);
				parse_str($inputstream, $post);
				$data = array();
				foreach ($post as $key => $val) {
					$data[hex2bin($key)] = $val;
				}
				break;
			case "POST":
			case "PUT":
				$raw_data = file_get_contents('php://input');
				// linebreak depending on apache vs iis
				$linebreak = stristr("\r\n", $raw_data) ? "\r\n" : "\n";

				// Fetch content and determine boundary
				$boundary = rtrim(substr($raw_data, 0, strpos($raw_data, $linebreak)));

				if (empty($boundary)){
					// according to https://stackoverflow.com/a/18209799/6087758
					$raw_data = preg_replace_callback(
						'/(^|(?<=&))[^=[&]+/',
						function($key) { return bin2hex(urldecode($key[0])); },
						$raw_data
					);
					parse_str($raw_data, $input);
					$data = [];
					foreach ($input as $key => $val) {
						$data[hex2bin($key)] = $val;
					}
				}
				else {
					// fetch each part
					$parts = array_slice(explode($boundary, $raw_data), 1);
					$data = array();

					foreach ($parts as $part){
						// linebreak depending on apache vs iis
						$linebreak = stristr("\r\n", $part) ? "\r\n" : "\n";

						// if this is the last part, break
						if (str_starts_with($part, "--")) break;

						// separate content from headers
						$part = ltrim($part, $linebreak);

						if (!$part) continue;

						if ($linebreak === "\n"){
							// iis
							list(, $raw_headers, $type, $body) = explode($linebreak, $part, 4);
							$body = ltrim($body);
						}
						else {
							// apache
							list($raw_headers, $type, $body) = explode($linebreak . $linebreak, $part, 3);
						}
						// retrieve type like Content-Type: application/pdf or '' if not file
						$type = explode(': ', $type);
						$type = isset($type[1]) ? $type[1] : '';

						// parse the headers list
						$raw_headers = explode($linebreak, $raw_headers);
						$headers = array();
						foreach ($raw_headers as $header){
							// list($name, $value) = explode(':', $header); this original does not work with input names containing colons
							preg_match("/^(.+?):(.+?)$/m", $header, $formerlyexploded);
							list(, $name, $value) = $formerlyexploded;
							$headers[strtolower($name)] = ltrim($value, ' ');
						}

						// parse the Content-Disposition to get the field name, etc.
						if (isset($headers['content-disposition'])){
							$filename = null;
							$tmp_name = null;
							preg_match(
								'/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/',
								$headers['content-disposition'],
								$matches
							);
							list(, , $name) = $matches;

							// parse file
							if (isset($matches[4])){
								// get filename
								$filename = $matches[4];
								$fieldname = preg_replace('/\[\]/', '', $matches[2]);

								// get tmp name
								$filename_parts = pathinfo( $filename );
								//$tmp_name = tempnam( ini_get('upload_tmp_dir'), $filename_parts['filename']);
								@$tmp_name = tempnam( sys_get_temp_dir(), preg_replace('/\W/', '', $filename_parts['filename']));
								
								// populate $_FILES with information, size may be off in multibyte situation
								$_files = [
									'error' => 0,
									'name' => $filename,
									'tmp_name' => $tmp_name,
									'size' => strlen( $body ),
									'type' => $type
								];
								
								$_FILES[$fieldname]['error'][] = $_files['error'];
								$_FILES[$fieldname]['name'][] = $_files['name'];
								$_FILES[$fieldname]['tmp_name'][] = $_files['tmp_name'];
								$_FILES[$fieldname]['size'][] = $_files['size'];
								$_FILES[$fieldname]['type'][] = $_files['type'];

								// place in temporary directory
								file_put_contents($tmp_name, $body);
							}
							// parse Field
							else {
								if (substr($name, -2) == '[]') { // is array
									$name = substr($name, 0, strlen($name) - 2);
									if (isset($data[$name])) $data[$name][] = substr($body, 0, strlen($body) - 2);
									else $data[$name] = [substr($body, 0, strlen($body) - 2)];
								}
								else $data[$name] = substr($body, 0, strlen($body) - 2);
							}
						}
					}
				}
				break;
			default:
				return [];
				break;
		}
		$payload = self::cleanInputs($data);
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
			foreach ($files as $file){
				if ((time() - filemtime($file)) / 3600 > $lifespan) {
					UTILITY::delete($file);
				}
			}
		}
		return $success;
	}
}

/**
 * permission handling, checking if necessary permissions have been given
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
		foreach (self::permissionFor($function, true) as $permission){
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
		foreach (self::permissionFor($function, true) as $permission){
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

	/**
	 *   ___ _ _ _                 _                 
	 *  |  _|_| | |_ ___ ___ ___ _| |_ _ ___ ___ ___ 
	 *  |  _| | |  _| -_|  _| -_| . | | |_ -| -_|  _|
	 *  |_| |_|_|_| |___|_| |___|___|___|___|___|_|  
	 *
	 * user filter
	 * skips system user and patients by default if not specified otherwise
	 * @param array $user database row
	 * @param array $filter with id, permission or unit as arrays
	 * @return bool
	 */
	public static function filteredUser($user, $filter = ['id' => [1], 'permission' => ['patient']]) {
		if (isset($filter['id']) && isset($user['id'])) {
			if (in_array(intval($user['id']), array_map(fn($id) => intval($id), $filter['id']))) return true;
		}
		if (isset($filter['permission']) && isset($user['permissions'])) {
			if (gettype($user['permissions']) !== 'array') $user['permissions'] = explode(',', $user['permissions'] ? : '');
			if ($user['permissions'] && array_intersect($user['permissions'], $filter['permission'])) return true;
		}
		if (isset($filter['unit']) && isset($user['units'])) {
			if (gettype($user['units']) !== 'array') $user['units'] = explode(',', $user['units'] ? : '');
			if ($user['units'] && array_intersect($user['units'], $filter['unit'])) return true;
		}
		return false;
	}

}

class MARKDOWN {
	/*
	markdown parser.
	supposed to match github-flavour (https://github.github.com/gfm/) to a reasonable amount

	Current limitations:
	* code blocks are not parsed as <code> due to limited compatibility with the [TCPDF](#ressources)-implementation, but <span> with inline monospace style instead
	* multiple lines for list items must end with one or more spaces on the previous line, linebreaks within lists behave a bit different than regular Markdown
	* this flavour currently lacks support of
		* setext headers by unterlining due to h1 and h2 being to big in context of this application
		* definitions
		* multiline code within lists
		* inline HTML on purpose
	*/

	private $_a_auto = '/(?<!\]\()(?:\<{0,1})((?:https*|ftps*|tel):(?:\/\/)*[^\n\s,>]+)(?:\>{0,1})/i'; // auto url linking, including some schemes
	private $_a_md = '/(?:(?<!!)\[)(.+?)(?:\])(?:\()(.+?)((?: \").+(?:\"))*(?:\))([^\)]|$)/m'; // regular md links
	private $_blockquote = '/(^>{1,} .*(?:\n|$))+/m';
	private $_br = '/ +\n/';
	private $_code_block = '/^ {0,3}([`~]{3})\n((?:.+?\n)+)^ {0,3}([`~]{3})/m';
		private $_code_inline = '/(?<!\\)(`{1,2})([^\n]+?)(?<!\\| |\n)\1/'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_emphasis = '/(?<!\\)((?<!\S)\_{1,3}|\*{1,3}(?! ))([^\n]+?)((?<!\\| |\n)\1)/'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_escape = '/\\(\*|-|~|`|\.|@|\|)/'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_header = '/^\n*^(#+ )(.+?)(#*)$/m'; // must have a linebreak before
	private $_hr = '/^ {0,3}(?:\-|\- |\*|\* ){3,}$/m';
	private $_img = '/(?:!\[)(.+?)(?:\])(?:\()(.+?)(?:\))([^\)])/';
	private $_list_any = '/(?:^ {0,3}|<blockquote>)((\*|\-|\+|\d+\.) (?:.|\n)+?)(?:^(?! |\* |\- |\+ |\d+\. )|<blockquote>)/mi';
	private $_list_nested = '/\n(^ {4}.+?\n)+/m';
	private $_list_ol = '/(^( ){0,3}(\d+\.) (.+?\n))+/m';
	private $_list_ul = '/(^( ){0,3}(\*|\-|\+) (.+?\n))+/m';
		private $_mail = '/([^\s<]+(?<!\\)@[^\s<]+\.[^\s<]+)/'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_p = '/^$\n((?<!^<table|^<ul|^<ol|^<h\d|^<blockquote|^<pre)(?:(\n|.)(?!table>$|ul>$|ol>$|h\d>$|blockquote>$|pre>$))+?)\n^$/mi';
	private $_pre = '/^ {4}([^\*\-\d].+)+/m';
		private $_s = '/(?<!\\)~~([^\n]+?)(?<!\\| |\n)~~/'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_table = '/^((?:\|.+?){1,}\|)\n((?:\| *-+ *?){1,}\|)\n(((?:\|.+?){1,}\|(?:\n|$))+)/m';

	private $headers = [];
	private $headerchars = '/[\w\d\-\sÄÖÜäöüßêÁáÉéÍíÓóÚúÀàÈèÌìÒòÙù]+/';

	public function __construct()
	{
		$this->_code_inline = '/(?<!' . preg_quote('\\','/'). ')(`{1,2})([^\n]+?)(?<!' . preg_quote('\\','/'). '| |\n)\1/'; // rewrite working regex101.com expression on construction for correct escaping of \
		$this->_emphasis = '/(?<!' . preg_quote('\\','/'). ')((?<!\S)\_{1,3}|\*{1,3}(?! ))([^\n]+?)((?<!' . preg_quote('\\','/'). '| |\n)\1)/';
		$this->_escape = '/' . preg_quote('\\','/'). '(\*|-|~|`|\.|@|\|)/';
		$this->_mail = '/([^\s<]+(?<!' . preg_quote('\\','/'). ')@[^\s<]+\.[^\s<]+)/';
		$this->_s = '/(?<!' . preg_quote('\\','/'). ')~~([^\n]+?)(?<!' . preg_quote('\\','/'). '| |\n)~~/';
	}

	/**
	 * @param string $path filepath to csv
	 * @param array $csv dialect options
	 * @return string|exception Marktown table or exception for lack of rows
	 */
	public function csv2md($path, $csv = ['separator' => ';', 'enclosure' => '"', 'escape' => '']){
		$csvfile = fopen($path, 'r');
		if (fgets($csvfile, 4) !== "\xef\xbb\xbf") rewind($csvfile); // BOM not found - rewind pointer to start of file.
		$rownum = 0;
		$md = '';
		while(($row = fgetcsv(
			$csvfile,
			null,
			$csv['separator'],
			$csv['enclosure'],
			$csv['escape']
			)) !== false) {
			if ($rownum < 1){
				if (count($row) < 2){
					throw new \Exception(mb_convert_encoding(implode(', ', $row), 'UTF-8', mb_detect_encoding(implode(', ', $row), ['ASCII', 'UTF-8', 'ISO-8859-1'])));
					return;
				}
				// set header as data keys
				foreach($row as &$column){
					if ($column) {
						$bom = pack('H*','EFBBBF'); //coming from excel this is utf8
						// delete bom, convert linebreaks to space
						$column = preg_replace(["/^$bom/", '/\n/'], ['',' '], $column);
					}
				}
				$md .= '| ' . implode(' | ', $row) . " |\n";
				$md .= '| ' . implode(' | ', array_fill(0, count($row), ' ----- ')) . " |\n";
			}
			else {
				$row = array_filter($row, fn($column) => $column !== null);
				if ($row) $md .= '| ' . implode(' | ', $row) . " |\n";
			}
			$rownum++;
		}
		fclose($csvfile);
		$md .= "\n";
		return $md;
	}

	/**
	 * @param string $content Markdown table
	 * @param array $csv dialect options
	 * @return array|exception [tempfile => string, headers => string] or exception due to lack of identified tables
	 */
	public function md2csv($content, $csv = ['separator' => ';', 'enclosure' => '"', 'escape' => '']){
		$data = [];
		$content = preg_replace('/\r/', '', $content);
		preg_match_all($this->_table, $content, $table);
		if (isset($table[0]) && $table[0]) {
			for ($i = 0; $i < count($table[0]); $i++){
				foreach(explode("\n", $table[0][$i]) as $rowindex => $row){
					if (!$row) continue;
					$columns = array_filter(preg_split('/(?<!' . preg_quote('\\', '/'). ')\|/', $row), fn($c) => boolval(trim($c)));
					switch($rowindex){
						case 1:
							break;
						default:
							$data[] = array_map(fn($column) => trim($column), array_filter($columns, fn($column) => boolval($column)));
					}
				}
			}
		}

		if (!$data) {
			throw new \Exception('no table identified');
			return;
		}

		@$tmp_name = tempnam( sys_get_temp_dir(), preg_replace('/\W/', '', implode('_', $data[0])));
		$file = fopen($tmp_name, 'w');
		fwrite($file, b"\xEF\xBB\xBF"); // tell excel this is utf8
		foreach($data as $row){
			fputcsv(
				$file,
				$row,
				$csv['separator'],
				$csv['enclosure'],
				$csv['escape']
			);
		}
		fclose($file);
		return [
			'tmpfile' => $tmp_name, 
			'headers' => preg_replace(CONFIG['forbidden']['names']['characters'], '_', implode('_', $data[0]))];
	}

	/**
	 * @param string $text Markdown styled
	 * @return string as HTML
	 */
	public function md2html($text){
		$text = preg_replace("/\r/", '', $text);

		$text = $this->blockquote($text); // should come first to enable nesting
		$text = $this->a($text);
		$text = $this->code($text);
		$text = $this->hr($text); // before emphasis avoiding matching *** as emphasis
		$text = $this->emphasis($text);
		$text = $this->header($text);
		$text = $this->img($text);
		$text = $this->list($text);
		$text = $this->mail($text);
		$text = $this->pre($text);
		$text = $this->s($text);
		$text = $this->table($text);
		$text = $this->p($text); // must come after anything previous to not mess up pattern recognitions relying on linebreaks and filtering out previously converted tags
		$text = $this->br($text);
		$text = $this->escape($text); // should come after other stylings have been applied

		return $text;
	}

	private function a($content){
		// replace links in this order
		$content = preg_replace($this->_a_auto,
			'<a href="$1" target="_blank" class="inline">$1</a>',
			$content);
		$content = preg_replace_callback($this->_a_md,
			function($match){
				$url = '';
				if (str_starts_with($match[2], 'javascript:')) $url = $match[2];
				elseif (str_starts_with($match[2], '#')) $url = $match[2];
				else {
					$component = parse_url($match[2]);
					if (isset($component['query'])){
						parse_str($component['query'], $query);
						$url = substr($match[2], 0, strpos($match[2], '?')) . '?' . http_build_query($query);
					}
					else $url = $match[2];
					$url .= '" target="_blank';
				}
				if (isset($match[3]) && $match[3]) $url .= '" title="' . substr($match[3], 2, -1);
				return '<a href="' . $url . '" class="inline">' . $match[1] . '</a>' . $match[4];
			},
			$content
		);
		return $content;
	}

	private function br($content){
		// replace linebreaks
		return preg_replace($this->_br,
			"<br />",
			$content
		);
	}

	private function blockquote($content, $sub = false){
		// replace blockquotes recursively
		$content = preg_replace_callback($this->_blockquote,
			function($match) use ($sub){
				$match[0] = $this->blockquote(preg_replace(['/^\n/', '/\n$/', '/^>/m', '/^ /m'], '', $match[0])); // remove leading and trailing linebreak, blockquote character and possible whitespace and check recursively for nested blockquotes
				if (!$sub) return "<blockquote>\n" . $match[0] . "\n</blockquote>"; // fence with tag, add linebreak for pattern recognition
				return "<blockquote>" . $match[0] . "</blockquote>"; // fence with tag, add linebreak for pattern recognition
			},
			$content
		);
		return $content;
	}

	private function code($content){
		// replace code
		$content = preg_replace_callback($this->_code_block,
			function($match){
				if ($match[1] == $match[3])	return '<pre>' . str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $match[2]) . '</pre>'; // i'd rather use code, but tcpdf does not support that
				return $match[0];
			},
			$content);
		$content = preg_replace_callback($this->_code_inline,
			function($match){
				return '<span style="font-family: monospace;">' . str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $match[2]) . '</span>'; // i'd rather use code, but tcpdf does not support that
			},
			$content
		);
		return $content;
	}

	private function emphasis($content){
		// replace all em and strong formatting
		return preg_replace_callback($this->_emphasis, 
			function($match) {
				// check whether **opening and closing*** match
				$wrapper = strlen($match[1]);
				$tags = [
					[], // wrapper offset, easier than reducing index
					['<em>', '</em>'],
					['<strong>', '</strong>'],
					['<em><strong>', '</strong></em>']
				];
				return $tags[$wrapper][0] . $match[2] . $tags[$wrapper][1];
			},
			$content
		);
	}

	private function escape($content){
		// replace escaped characters
		return preg_replace($this->_escape,
			'$1',
			$content
		);
	}

	private function header($content){
		// replace headers
		$content = preg_replace_callback($this->_header,
			function($match){
				$size = min(strlen($match[1]) - 1, 6);
				preg_match($this->headerchars, trim($match[2]), $id);
				if ($id[0]){
					$id = strtolower(preg_replace(['/\s/'], ['-'], trim($id[0])));
					// enumerate
					$existing = array_filter($this->headers, fn($e) => str_starts_with($e, $id));
					if ($existing) {
						sort($existing);
						$last = array_pop($existing);
						preg_match('/.+?-(\d)$/m', $last, $numerate);
						if (isset($numerate[1]) && $numerate[1]) $id .= '-' . intval($numerate[1]) + 1;
						else $id .= '-1';
					}
					$this->headers[] = $id;
				}
				return '<h' . $size . ' id="' . $id . '">' . $match[2] . '</h' . $size . ">";
			},
			$content
		);
		return $content;
	}

	private function hr($content){
		// replace hr	
		return preg_replace($this->_hr,
			"<hr>",
			$content
		);
	}
	
	private function img($content){
		// replace images
		$content = preg_replace($this->_img,
			'<img alt="$1" src="$2" style="float:left; max-width:100%" />',
			$content
		);
		return $content;
	}

	private function list($content, $sub = false){
		// detect any lists
		// recursively replace nested lists
		$content = preg_replace_callback($this->_list_any,
			function($list){
				// check lists for subelements, lists, blockquote, code, table or pre
				return preg_replace_callback($this->_list_nested,
					function($nested){
						return preg_replace('/^\n/', '', $this->list(preg_replace('/^ {4}/m', '', $nested[0] . "\n"), true));  // drop leading linebreak, but add one to end for pattern recognition
					},
					$list[1]
				);
			},
			$content
		);

		if ($sub){
			// replace possible nested blocks in advance to list matching
			$content = $this->blockquote($content, true);
			$content = $this->code($content);
			$content = $this->table($content);
			$content = $this->pre($content);
		}

		//replace unordered lists
		$content = preg_replace_callback($this->_list_ul,
			function($match){
				$output = "<ul>";
				foreach (explode("\n", $match[0]) as $item){
					if ($item) $output .= "<li>" . preg_replace('/^ *[\*\+\-] /m','', $item) . "</li>";
				}
				$output .= "</ul>";
				return $output;
			},
			$content
		);
		// replace ordered lists 
		$content = preg_replace_callback($this->_list_ol,
			function($match) {
				$output = "<ol>";
				foreach (explode("\n", $match[0]) as $item){
					if ($item) $output .= "<li>" . str_repeat('&nbsp;', 3) . preg_replace('/^ *\d+\. /m','', $item) . "</li>"; // &nbsp; looks a bit weird on screen but improves pdfs
				}
				$output .= "</ol>";
				return $output;
			},
			$content
		);
		return $content;//preg_replace('/^\n/', '', $content);
	}

	private function mail($content){
		// replace code
		$content = preg_replace_callback($this->_mail,
			function($match){
				$encoded_email = '';
				for ($a = 0, $b = strlen($match[0]); $a < $b; $a++)
				{
					$encoded_email .= '&#' . (mt_rand(0, 1) == 0  ? 'x' . dechex(ord($match[0][$a])) : ord($match[0][$a])) . ';';
				}
				return '<a href="mailto:' . $encoded_email . '">' . $encoded_email . '</a>';
			},
			$content
		);
		return $content;
	}

	private function p($content){
		// replace p
		return preg_replace($this->_p,
			"<p>$1</p>\n",
			$content);
	}

	private function pre($content){
		// replace code/pre
		$content = preg_replace_callback($this->_pre,
			function($match){
				return "<pre>" . str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], preg_replace('/^ {4}/m', '', $match[0])) . "</pre>";
			},
			$content
		);
		return $content;
	}

	private function s($content){
		// replace s
		return preg_replace($this->_s,
			"<s>$1</s>",
			$content
		);
	}

	private function table($content){
		// replace tables
		$content = preg_replace_callback($this->_table,
			function($match){
				$output = '<table>';
				foreach(explode("\n", $match[0]) as $rowindex => $row){
					if (!$row) continue;
					$columns = array_filter(preg_split('/(?<!' . preg_quote('\\', '/'). ')\|/', $row), fn($c) => boolval(trim($c)));
					switch($rowindex){
						case 1:
							break;
						case 0:
							$output .= '<tr>' . implode('', array_map(fn($column) => '<th>' . trim($column) . '</th>', $columns)) . '</tr>';
							break;
						default:
							$output .= '<tr>' . implode('', array_map(fn($column) => '<td>' . trim($column) . '</td>', $columns)) . '</tr>';
					}
				}
				$output .= '</table>';
				return $output;
			},
			$content
		);
		return $content;
	}
}
?>
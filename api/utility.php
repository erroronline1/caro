<?php

define("UTILITY_IMAGE_REPLACE", 0x1);
define("UTILITY_IMAGE_STREAM", 0x2);
define("UTILITY_IMAGE_RESOURCE", 0x4);

class UTILITY {

	/**
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
							list($name, $value) = explode(':', $header);
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
									if (array_key_exists($name, $data)) $data[$name][] = substr($body, 0, strlen($body) - 2);
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
	 * @param string $file filename
	 * @param int $maxSize max pixels on longest side
	 * @param flag $destination UTILITY_IMAGE_REPLACE | UTILITY_IMAGE_STREAM | UTILITY_IMAGE_RESOURCE 
	 * @param string|bool $forceOutputType gif|jpeg|png|jpg
	 * 
	 * @return object|null a GdImage ressource or no return
	 */
	public static function resizeImage($file, $maxSize = 1024, $destination = UTILITY_IMAGE_REPLACE, $forceOutputType = false){
		if (is_file($file)){
			$filetype=getimagesize($file)[2];
			switch($filetype){
				case"1": //gif
					$image = imagecreatefromgif($file);
					break;
				case "2": //jpeg
					$image = imagecreatefromjpeg($file);
					break;
				case "3": //png
					$image = imagecreatefrompng($file);
					break;
			}
		}
		else $image = imagecreatefromstring($file); // bytestring
		if ($image) {
			$filename = pathinfo($file)['basename'];
			$owidth = imagesx($image);
			$oheight = imagesy($image);		
			if ($owidth >= $oheight && $owidth > $maxSize) $resize = $maxSize/$owidth;
			elseif ($owidth < $oheight && $oheight > $maxSize) $resize = $maxSize/$oheight;
			else $resize = 1;
			$image2 = imagecreatetruecolor(ceil($owidth * $resize), ceil($oheight * $resize));
			imagealphablending($image2, false);
			imagesavealpha($image2, true);
			$transparent = imagecolorallocatealpha($image2, 255, 255, 255, 127);
			imagefilledrectangle($image2, 0, 0, ceil($owidth * $resize), ceil($oheight * $resize), $transparent);
			imagecopyresampled($image2, $image, 0, 0, 0, 0, ceil($owidth * $resize), ceil($oheight * $resize), $owidth, $oheight);
			imagedestroy($image);

			if ($destination & UTILITY_IMAGE_REPLACE){
				chmod($file, 0777);
				switch($filetype){
					case "1": //gif
						imagegif($image2, $file);
						break;
					case "2": //jpeg
						imagejpeg($image2, $file, 100);
						break;
					case "3": //png
						imagepng($image2, $file, 0);
						break;
				}
				imagedestroy($image2);
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
					imagegif($image2, null);
					break;
				case "2": //jpeg
					imagejpeg($image2, null, 100);
					break;
				case "3": //png
					imagepng($image2, null, 6);
					break;
			}
			imagedestroy($image2);
			if ($destination & UTILITY_IMAGE_STREAM) {
				ob_flush();
			}
			$return = ob_get_contents();
			ob_end_clean(); 
			return $return;
		}
	}

	/**
	 * shorthand checking for a set property
	 * 
	 * @param object $object to look within
	 * @param string $property to look for
	 * 
	 * @return string|bool property value or false
	 */
	public static function propertySet($object, $property){
		return (property_exists($object, $property) && boolval($object->{$property}) && $object->{$property} !== 'undefined') ? $object->{$property} : false;
	}

	/**
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
		if (!file_exists($folder)) mkdir($folder, 0777, true);
		$targets = [];
		for ($i = 0; $i < count($name); $i++) {
			if (gettype($_FILES[$name[$i]]['name']) !== 'array') {
				if ($_FILES[$name[$i]]['tmp_name']) $targets[] = self::handle($_FILES[$name[$i]]['tmp_name'], $_FILES[$name[$i]]['name'], $i, $prefix, $folder, $replace);
			}
			else {
				for ($j = 0; $j < count($_FILES[$name[$i]]['name']); $j++){
					if (!$_FILES[$name[$i]]['tmp_name'][$j]) continue;
					$file = pathinfo($_FILES[$name[$i]]['name'][$j]);
					if ($rename && array_key_exists($i, $rename) && $rename[$i]) $file['filename'] = $rename[$i];
					$targets[] = self::handle($_FILES[$name[$i]]['tmp_name'][$j], $file['filename'] . '.' . $file['extension'], $i, $prefix, $folder, $replace);
				}
			}
		}
		return $targets; // including path e.g. to store in database if needed, has to be prefixed with "api/" eventually 
	}
	private static function handle($tmpname, $name, $i, $prefix, $folder, $replace = false){
		$_prefix = $prefix ? $prefix[(key_exists($i, $prefix) ? $i : count($prefix) - 1)] : null;
		$target = $folder . '/' . ($_prefix ? $_prefix . '_' : '') . $name;
		if (!$replace){
			$extension = '.' . pathinfo($target)['extension'];
			$files = glob(str_replace($extension, '*' . $extension, $target));
			$target = self::enumerate($target, $files);
		}
		// move_uploaded_file is for post only, else rename for put files
		if ($tmpname && (move_uploaded_file( $tmpname, $target) || rename( $tmpname, $target))){
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
			foreach (array_keys(INI['fileserver']) as $fileserver){
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
			if (is_file($folder . '/' . $file)) $result[] = $folder . '/' . $file;
		}
		return $result;
	}

	/**
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
	 * returns folders defined in setup.ini
	 * 
	 * @param string $request key
	 * @param array $replace optional named array with replacements
	 * 
	 * @return string directory
	 */
	public static function directory($request, $replace = []){
		if (!array_key_exists($request, INI['fileserver'])){
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
		return '../' . preg_replace($patterns, $replacements, INI['fileserver'][$request]);
	}

	/**
	 * prepares a folder according to setup.ini and deletes files if lifespan is set
	 * 
	 * @param string $dir one of the fileserver keys
	 * @param int $lifespan in hours
	 * 
	 * @return bool if 
	 */
	public static function tidydir($dir = '', $lifespan = null){
		if (!$dir) return false;
		$success = !file_exists(self::directory($dir)) ? mkdir(self::directory($dir), 0777, true) : true;
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
	 * returns a boolean if user is authorized for requested app-function, array of permissions if $returnvalues argument is true
	 * @param string $function as defined within setup.ini
	 * @param bool $returnvalues
	 * @return bool|array
	 */
	public static function permissionFor($function = '', $returnvalues = false){
		if (!array_key_exists('user', $_SESSION) || !array_key_exists('permissions', $_SESSION['user'])) return [];
		if (array_key_exists($function, INI['permissions'])){
			if (!$returnvalues) {
				// limited functions don't include admin by default
				if (in_array($function, ['productslimited'])) return boolval(array_intersect([...preg_split('/\W+/', INI['permissions'][$function])], $_SESSION['user']['permissions']));
				return boolval(array_intersect(['admin', ...preg_split('/\W+/', INI['permissions'][$function])], $_SESSION['user']['permissions']));
			}
			return preg_split('/\W+/', INI['permissions'][$function]);
		}
		var_dump('permission ' . $function . ' not found in setup.ini file');
	}

	/**
	 * check whether an approvalcolumn has been fully approved according to function
	 * @param string $function as defined within setup.ini
	 * @param string|array $approvalcolumn 'approval'-column
	 * @return bool
	 * 
	 */
	public static function fullyapproved($function = '', $approvalcolumn = ''){
		if (gettype($approvalcolumn) === 'string') $approvalcolumn = $approvalcolumn ? json_decode($approvalcolumn, true) : [];
		$approved = true;
		foreach(self::permissionFor($function, true) as $permission){
			if (!array_key_exists($permission, $approvalcolumn)) $approved = false;
		}
		return $approved;
	}

	/**
	 * check whether an approvalcolumn has pending approvals according to function
	 * check per user permission so there is only one count per unapproved element even on multiple permissions
	 * @param string $function as defined within setup.ini
	 * @param string|array $approvalcolumn 'approval'-column
	 * @return array of pending approval permission
	 * 
	 */
	public static function pending($function = '', $approvalcolumn = ''){
		if (gettype($approvalcolumn) === 'string') $approvalcolumn = $approvalcolumn ? json_decode($approvalcolumn, true) : [];
		$pending = [];
		foreach(self::permissionFor($function, true) as $permission){
			if (array_intersect(['admin', $permission], $_SESSION['user']['permissions']) && !array_key_exists($permission, $approvalcolumn)) $pending[] = $permission;
		}
		return $pending;
	}
}
?>
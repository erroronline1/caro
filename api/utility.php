<?php
class UTILITY {

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
				//$payload = json_decode(file_get_contents("php://input"), true);
				global $_PUT;

				/* PUT data comes in on the stdin stream */
				$putdata = fopen("php://input", "r");

				/* Open a file for writing */
				// $fp = fopen("myputfile.ext", "w");

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
					$GLOBALS[ '_PUT' ] = $data;
					return;
				}

				// Fetch each part
				$parts = array_slice(explode($boundary, $raw_data), 1);
				$data = array();

				foreach ($parts as $part) {
					// If this is the last part, break
					if ($part == "--\r\n") break;

					// Separate content from headers
					$part = ltrim($part, "\r\n");
					list($raw_headers, $body) = explode("\r\n\r\n", $part, 2);

					// Parse the headers list
					$raw_headers = explode("\r\n", $raw_headers);
					$headers = array();
					foreach ($raw_headers as $header) {
						list($name, $value) = explode(':', $header);
						$headers[strtolower($name)] = ltrim($value, ' ');
					}

					// Parse the Content-Disposition to get the field name, etc.
					if (isset($headers['content-disposition'])) {
						$filename = null;
						$tmp_name = null;
						preg_match(
							'/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/',
							$headers['content-disposition'],
							$matches
						);
						list(, $type, $name) = $matches;

						//Parse File
						if( isset($matches[4]) )
						{
							//if labeled the same as previous, skip
							if( isset( $_FILES[ $matches[ 2 ] ] ) )
							{
								continue;
							}

							//get filename
							$filename = $matches[4];

							//get tmp name
							$filename_parts = pathinfo( $filename );
							$tmp_name = tempnam( ini_get('upload_tmp_dir'), $filename_parts['filename']);

							//populate $_FILES with information, size may be off in multibyte situation
							$_FILES[ $matches[ 2 ] ] = array(
								'error'=>0,
								'name'=>$filename,
								'tmp_name'=>$tmp_name,
								'size'=>strlen( $body ),
								'type'=>$value
							);

							//place in temporary directory
							file_put_contents($tmp_name, $body);
						}
						//Parse Field
						else
						{
							$data[$name] = substr($body, 0, strlen($body) - 2);
						}
					}

				}
				$GLOBALS[ '_PUT' ] = $data;
				$payload = self::cleanInputs($_PUT);
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
			$clean_input = trim($data);
		}
		return $clean_input;
	}

	public static function resizeImage($file, $maxSize = 128){
		if (is_file($file)){
			$imgtype=getimagesize($file);
			if ($imgtype[2] == "1"){ $image = imagecreatefromgif($file); } //gif
			elseif ($imgtype[2] == "2"){ $image = imagecreatefromjpeg($file); } //jpeg
			elseif ($imgtype[2] == "3"){ $image = imagecreatefrompng($file); } //png
		}
		else $image = imagecreatefromstring($file); // bytestring
		if ($image) {
			$owidth = imagesx($image);
			$oheight = imagesy($image);		
			if ($owidth >= $oheight && $owidth > $maxSize) $resize = $maxSize/$owidth;
			elseif ($owidth < $oheight && $oheight > $maxSize) $resize = $maxSize/$oheight;
			else $resize = 1;
			$image2 = imagecreatetruecolor(ceil($owidth * $resize), ceil($oheight * $resize));
			imagecopyresampled($image2, $image, 0, 0, 0, 0, ceil($owidth * $resize), ceil($oheight * $resize), $owidth, $oheight);

			ob_start();
			imagepng($image2, NULL, 0);
			$return = ob_get_clean();
			ob_end_clean(); 
			imagedestroy($image);
			imagedestroy($image2);

			return $return;
		}
	}

	public static function propertySet($object, $property){
		return (property_exists($object, $property) && boolval($object->{$property})) ? $object->{$property} : false;
	}

	public static function scriptFilter($text){
		return htmlspecialchars(trim($text));
	}

	public static function storeUploadedFiles($_files, $files = [], $prefix = ''){
		/* process $_FILES, store to folder and return an array of destination paths */
		if (!file_exists('files')) mkdir('files', 0777, true);
		$targets =[];
		foreach ($files as $file) {
			$target = 'files/' . ($prefix ? $prefix . '_' : '') . $_files[$file]['name'];
			// move_uploaded_file is for post only, else rename for put files
			if (move_uploaded_file( $_files[$file]['tmp_name'], $target) ||	rename( $_files[$file]['tmp_name'], $target))
				$targets[] = 'api/' . $target;
		}
		return $targets; // including path e.g. to store in database
	}
}
?>
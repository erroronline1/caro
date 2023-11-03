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
				$payload = json_decode(file_get_contents("php://input"), true);
				$payload = self::cleanInputs($payload);
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
}
?>
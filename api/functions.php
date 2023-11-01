<?php

class PAYLOAD {
	public $_payload = [];

	public function __construct(){
		$this->inputs();
	}

	private function inputs(){
		switch($_SERVER['REQUEST_METHOD']){
			case "POST":
				if (!$_POST) { // has not been sent via multipartform
					$_POST = json_decode(file_get_contents("php://input"), true);
				}
				$this->_payload = $this->cleanInputs($_POST);
				break;
			case "GET":
				$this->_payload = $this->cleanInputs($_GET);
				break;
			case "DELETE":
				$this->_payload = json_decode(file_get_contents("php://input"), true);
				$this->_payload = $this->cleanInputs($this->_payload);
				break;
			case "PUT":
				$this->_payload = json_decode(file_get_contents("php://input"), true);
				$this->_payload = $this->cleanInputs($this->_payload);
				break;
			default:
				return [];
				break;
		}
	}		
	
	private function cleanInputs($data){
		$clean_input = [];
		if(is_array($data)){
			foreach($data as $k => $v){
				$clean_input[$k] = $this->cleanInputs($v);
			}
		} else {
			$clean_input = trim($data);
		}
		return $clean_input;
	}
}

function resizeImage($file, $maxSize = 128){
	$imgtype=getimagesize($file);
	if ($imgtype[2] == "1" || $imgtype[2] == "2" || $imgtype[2] == "3"){
		if ($imgtype[2] == "1"){ $image = imagecreatefromgif($file); } //gif
		elseif ($imgtype[2] == "2"){ $image = imagecreatefromjpeg($file); } //jpeg
		elseif ($imgtype[2] == "3"){ $image = imagecreatefrompng($file); } //png
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


function scriptFilter($text){
	return htmlspecialchars(trim($text));
}

?>
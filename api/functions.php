<?php

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

?>
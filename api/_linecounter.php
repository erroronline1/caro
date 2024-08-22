<?php
$lines = 0;
$byte = 0;
$files = 0;
foreach (['../', '../js', '../api'] as $dir){
	foreach (scandir($dir) as $file){
		if (!in_array(pathinfo($file)['extension'], ['php','ini','js','html','css','md'])) continue;
		$files++;
		foreach(file($dir.'/'.$file) as $row){
			$lines++;
			$byte+= strlen($row);
		}
	}
}
echo $lines, ' lines, ', $byte, ' byte, ', $files, ' files';


function parts_of_attribute($attributes = '', $target = 0){
	$attributes = preg_split('/\W+/', $attributes);
	$parts = [];
	$fs=0;
	do {
		$closest = null;
		foreach ($attributes as $attribute) {
			if ($closest === null || abs($target - $closest) > abs($attribute - $target)) {
				$closest = $attribute;
			}
		}
		$parts[] = $closest;
		var_dump($parts, array_sum($parts)/count($parts));
		$fs++;
	} while (array_sum($parts)/count($parts) != $target && $fs<20);
	$destination = [];
	foreach (array_count_values($parts) as $part => $occurence) $destination[] = $occurence . " x " . $part . ' (' . round($count($parts)/$occurence, 2) .' %)';
	return implode(' / ', $destination);
}

echo parts_of_attribute('20,35,65', 45);
//phpinfo();
?>
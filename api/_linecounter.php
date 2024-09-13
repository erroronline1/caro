<?php
$lines = ['frontend' => 0,'backend' => 0, 'code' => 0, 'documentation' => 0];
$byte = 0;
$files = 0;
foreach (['../', '../js', '../api'] as $dir){
	foreach (scandir($dir) as $file){
		if (!isset(pathinfo($file)['extension']) || !in_array(pathinfo($file)['extension'], ['php','ini','js','html','css','md'])) continue;
		$files++;
		foreach(file($dir.'/'.$file) as $row){
			if (in_array(pathinfo($file)['extension'], ['php','ini'])){
				$lines['backend']++;
				$lines['code']++;
			}
			if (in_array(pathinfo($file)['extension'], ['js','html','css'])){
				$lines['frontend']++;
				$lines['code']++;
			}
			if (in_array(pathinfo($file)['extension'], ['md'])){
				$lines['documentation']++;				
			}
			$byte+= strlen($row);
		}
	}
}
var_dump($lines);
echo '<br />', $lines['code']+$lines['documentation'], ' lines, ', $byte, ' byte, ', $files, ' files<br />';

//phpinfo();
?>
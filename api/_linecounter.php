<?php
$lines = ['frontend' => 0,'backend' => 0, 'code' => 0, 'documentation' => 0];
$byte = 0;
$files = 0;
foreach (['../', '../js', '../api', '../templates'] as $dir){
	foreach (scandir($dir) as $file){
		if (!isset(pathinfo($file)['extension']) || !in_array(pathinfo($file)['extension'], ['php','ini','js','html','css','md','de','en'])) continue;
		$files++;
		foreach(file($dir.'/'.$file) as $row){
			if (in_array(pathinfo($file)['extension'], ['php','ini','de','en'])){
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

/*
$sim = [
	['müller, liese *10.11.2012 unterschenkelorthese 2024-10-13 21:12', 'müller, liese *10.11.2012 oberschenkelorthese 2023-05-02 08:23'],
	['müller, liese *10.11.2012 unterschenkelorthese 2024-10-13 21:12', 'müller, liese *10.11.2012 dafo 2023-05-02 08:23'],
	['müller, liese *10.11.2012 unterschenkelorthese 2024-10-13 21:12', 'doe, jane *05.03.1958 dafo 2024-02-23 11:44'],
	['müller, liese *10.11.2012 unterschenkelorthese 2024-10-13 21:12', 'doe, jane *05.03.1958 mieder 2025-02-23 11:44']
];

foreach ($sim as $set){

	$possibledate = [substr($set[0], -16), substr($set[1], -16)];
	try {
		new DateTime($possibledate[0]);
		$set[0] = substr($set[0], 0, -16);
		new DateTime($possibledate[1]);
		$set[1] = substr($set[1], 0, -16);

	}
	catch (Exception $e){
	}

	similar_text($set[0], $set[1], $percent);
	var_dump($set, $percent, '<br />');
}
*/

?>
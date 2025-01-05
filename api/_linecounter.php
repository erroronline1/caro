<?php
$lines = ['frontend' => 0,'backend' => 0, 'code' => 0, 'documentation' => 0];
$byte = 0;
$files = 0;
foreach (['../', '../js', '../api', '../templates'] as $dir){
	foreach (scandir($dir) as $file){
		if (!isset(pathinfo($file)['extension']) || !in_array(pathinfo($file)['extension'], ['php','ini','js','html','css','md','json'])) continue;
		$files++;
		foreach(file($dir.'/'.$file) as $row){
			if (in_array(pathinfo($file)['extension'], ['php','ini','json'])){
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
die();
require_once('_config.php');
$driver = CONFIG['sql']['use'];
$pdo = new PDO( CONFIG['sql'][$driver]['driver'] . ':' . CONFIG['sql'][$driver]['host'] . ';' . CONFIG['sql'][$driver]['database']. ';' . CONFIG['sql'][$driver]['charset'], CONFIG['sql'][$driver]['user'], CONFIG['sql'][$driver]['password']);
$queries = [
	'install' => [
		'mysql' => [
			'insertions' => [
				'manual' => "INSERT INTO caro_manual (id, title, content, permissions) VALUES (NULL, :title, :content, :permissions);",
			]
		],
		'sqlsrv' => [
			'insertions' => [
				'manual' => "INSERT INTO caro_manual (title, content, permissions) VALUES (:title, :content, :permissions);",
			]
		]
	]
];

$pdo->query('TRUNCATE TABLE caro_manual');
if ($file = file_get_contents('./_install.default.en.json')){
	$languagefile = json_decode($file, true);
	foreach($languagefile['defaultmanual'] as $entry){
		$processing[] = strtr($queries['install'][$driver]['insertions']['manual'], [
			':title' => $pdo->quote($entry['title']),
			':content' => $pdo->quote($entry['content']),
			':permissions' => $pdo->quote($entry['permissions'])
		]);
	}
}
// execute stack
foreach ($processing as $command){
	echo $command . '<br />';
	$statement = $pdo->query($command);
}



?>
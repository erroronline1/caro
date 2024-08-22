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

//phpinfo();
?>
<?php
$lines = 0;
foreach (['../', '../js', '../api'] as $dir){
    foreach (scandir($dir) as $file){
        if (!in_array(pathinfo($file)['extension'], ['php','ini','js','html','css','md'])) continue;
        $lines += count(file($dir.'/'.$file));
    }
}
echo $lines, ' lines';
?>
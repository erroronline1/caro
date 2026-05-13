<?php
namespace CARO\API;
require_once('./_config.php');
require_once('./_utility.php'); // general utilities
require_once('./_sqlinterface.php');
require_once('./_language.php');

$config_copy = file_get_contents('config.ini');
$config_copy = preg_replace('/^erp =.*?(;|$)/m', 'erp = no $1', $config_copy);
var_dump($config_copy);



//require_once('_erpinterface.php');
//var_dump(ERPINTERFACE->orderdata());

//phpinfo();
?>
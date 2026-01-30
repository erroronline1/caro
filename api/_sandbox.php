<?php
namespace CARO\API;
require_once('_config.php');
require_once('_utility.php'); // general utilities
//require_once('_erpinterface.php');
//var_dump(ERPINTERFACE->orderdata());

var_dump(UTILITY::identifier('test', '2025-08-15 21:14:00'));
echo "<br>";
var_dump(UTILITY::identifier('test #t11urc', '', false, false, true));
echo "<br>";
var_dump(UTILITY::identifier('another test #t11urc', '', false, false, true));
echo "<br>";
var_dump(UTILITY::identifier('another test #t11urc abc', '', false, false, true));

//phpinfo();
?>
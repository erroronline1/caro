<?php
namespace CARO\API;
require_once('_config.php');
require_once('_utility.php'); // general utilities

$x = new BLOCKCHAIN;
$y = [
    'test'=>123
];
$z = $x->add([],$y);
// static class can be instatiated to a variable too
//var_dump($z);
//var_dump($x->verified($z, true));


$reflector = new \ReflectionMethod('CARO\API\BLOCKCHAIN::verified');
$reflector_start = $reflector->getStartLine()-1;
$reflector_end = $reflector->getEndLine()-1;
echo $reflector->__toString();

foreach(file('_utility.php') as $line => $codeline){
    if ($line < $reflector_start) continue;
    echo $codeline;
    if ($line > $reflector_end) break;
}



//phpinfo();
?>
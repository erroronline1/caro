<?php
namespace CARO\API;
require_once('_config.php');
require_once('_utility.php'); // general utilities


$content = [
    [
        'data' => 'abc'
    ],
    [
        'data' => 'def'
    ],
    [
        'data' => 'ghi'
    ]
];

$chain = [];

foreach ($content as $block){
$chain = BLOCKCHAIN::add($chain, $block);
}

var_dump($chain);
echo "<br>";

$chain[1]['data'] = 'deff';
var_dump($chain);
echo "<br>";

var_dump(BLOCKCHAIN::verified($chain) ? 'true' : 'false');




//phpinfo();
?>
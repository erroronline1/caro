<?php
namespace CARO\API;
require_once('_config.php');
require_once('_utility.php'); // general utilities


$embed = file_get_contents('../assets/test.pdf');


$i = new IPTC("../assets/test.jpg");
echo $i->set('IPTC_SOURCE', $embed); 
$i->write();

$i = new IPTC('../assets/test.jpg');

echo $i->get('IPTC_SOURCE'); 

file_put_contents("../assets/test2.pdf", $i->get('IPTC_SOURCE'));

//phpinfo();
?>
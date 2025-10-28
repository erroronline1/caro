<?php
namespace CARO\API;
require_once('_config.php');
require_once('_utility.php'); // general utilities


// well, this worked like a charm under linux. not iis though
$embed = file_get_contents('../assets/test.pdf');

$i = new IPTC("../assets/test.jpg");
//$i->set('IPTC_CAPTION', $embed);
$i->set('IPTC_CAPTION', "hello");
$i->write();

$i = new IPTC('../assets/test2.jpg');
$i->dump(); 

//file_put_contents("../assets/test2.pdf", $i->get('IPTC_SOURCE'));

//phpinfo();
?>
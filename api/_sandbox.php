<?php
namespace CARO\API;
require_once('_config.php');
require_once('_utility.php'); // general utilities
//require_once('_erpinterface.php');
//var_dump(ERPINTERFACE->orderdata());

/*require_once('_sqlinterface.php');
$options = [
	\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // always fetch assoc
	\PDO::ATTR_EMULATE_PREPARES   => true, // reuse tokens in prepared statements
	//\PDO::ATTR_PERSISTENT => true // persistent connection for performance reasons, unsupported as of 2/25 on sqlsrv?
];
$_pdo = new \PDO( CONFIG['sql'][CONFIG['sql']['use']]['driver'] . ':' . CONFIG['sql'][CONFIG['sql']['use']]['host'] . ';' . CONFIG['sql'][CONFIG['sql']['use']]['database']. ';' . CONFIG['sql'][CONFIG['sql']['use']]['charset'], CONFIG['sql'][CONFIG['sql']['use']]['user'], CONFIG['sql'][CONFIG['sql']['use']]['password'], $options);
$dbsetup = SQLQUERY::PREPARE($this->_pdo, 'DYNAMICDBSETUP');
if ($dbsetup) $_pdo->exec($dbsetup);
*/

$_SESSION['user']['name'] = 'error on line 1';
require_once('_language.php');
require_once('_pdf copy.php');
$pdf = new PDF(CONFIG['pdf']['record']);

$pdf->test([
			'filename' => 'testfilename',
			'identifier' => 'hello world *12.13.1415 #lkasfjn Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua',
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua',
			'date' => 'today'
		]);


// ----------
die();

//phpinfo();
?>
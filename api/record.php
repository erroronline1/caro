<?php
// add and export records
// Y U NO DELETE? because of audit safety, that's why!



class record extends API {
   // processed parameters for readability
   public $_requestedMethod = REQUEST[1];
   private $_requestedID = null;
   private $PDFLIBRARY = '../libraries/TCPDF/tcpdf_import.php';

	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	public function identifier(){
		if (!(array_intersect(['user'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if ($content=UTILITY::propertySet($this->_payload, LANG::PROPERTY('record.create_identifier'))) $content .= ' ' . date('Y-m-d H:i');
				if ($content){
					$downloadfiles = [];
					$downloadfiles[LANG::GET('record.create_identifier')] = [
						'href' => $this->identifierPDF($content)
					];
					$this->response([
						'log' => LANG::GET('record.create_identifier_proceed'),
						'links' => $downloadfiles
					]);
	
				}
				else $this->response(['status' => [
					'msg' => LANG::GET('record.create_identifier_error')
				]]);
				break;
			case 'GET':
				$result=['body'=>
				[
					'form' => [
						'data-usecase' => 'record',
						'action' => "javascript:api.record('post', 'identifier')"],
					'content'=>[
						[
							[
								'type' => 'text',
								'description' => LANG::GET('record.create_identifier_info')
							], [
								'type' => 'scanner',
								'hint' => LANG::GET('record.create_identifier_hint'),
								'attributes' => [
									'name' => LANG::GET('record.create_identifier'),
									'maxlength' => 128
								]
							]
						]
					]
				]];
				$this->response($result);
				break;
		}
	}

	public function filter(){
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-datalist'));
		$statement->execute();
		$fd = $statement->fetchAll(PDO::FETCH_ASSOC);
		$hidden = $matches = [];
		foreach($fd as $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['id'], $matches) && !in_array($row['name'], $hidden)) {
				$terms = [$row['name']];
				foreach(preg_split('/[^\w\d]/', $row['alias']) as $alias) array_push($terms, $alias);
				foreach ($terms as $term){
					similar_text($this->_requestedID, $term, $percent);
					if (($percent >= INI['likeliness']['file_search_similarity'] || !$this->_requestedID) && !in_array($row['id'], $matches)) $matches[] = strval($row['id']);
				}
			}
		}
		$this->response(['status' => [
			'data' => $matches
		]]);
	}


	public function forms(){
		if (!(array_intersect(['user'], $_SESSION['user']['permissions']))) $this->response([], 401);
		$formdatalist = $forms = [];
		$return = [];

		// prepare existing forms lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-datalist'));
		$statement->execute();
		$fd = $statement->fetchAll(PDO::FETCH_ASSOC);
		$hidden = [];
		foreach($fd as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $formdatalist) && !in_array($row['name'], $hidden)) {
				$formdatalist[] = $row['name'];
				$forms[$row['name']] = ['href' => "javascript:api.record('get', 'form', '" . $row['name'] . "')", 'data-filtered' => $row['id']];
				foreach(preg_split('/[^\w\d]/', $row['alias']) as $alias) $formdatalist[] = $alias;
			}
		}
		$return['body'] = [
			'content' => [
				[
					[
						'type' => 'datalist',
						'content' => $formdatalist,
						'attributes' => [
							'id' => 'forms'
						]
					], [
						'type' => 'filterinput',
						'attributes' => [
							'name' => LANG::GET('record.form_filter'),
							'list' => 'forms',
							'onkeypress' => "if (event.key === 'Enter') {api.record('get', 'filter', this.value); return false;}",
							'onblur' => "api.record('get', 'filter', this.value); return false;",
							]
					]
				], [
					[
						'type' => 'links',
						'description' => LANG::GET('record.form_all'),
						'content' => $forms
					]
				]
			]];
		$this->response($return);
	}

	public function form(){
		if (!(array_intersect(['user'], $_SESSION['user']['permissions']))) $this->response([], 401);

		// prepare existing forms lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-get-latest-by-name'));
		$statement->execute([
			':name' => $this->_requestedID
		]);
		$form = $statement->fetch(PDO::FETCH_ASSOC);
		$return = ['title'=> $form['name'], 'body' => [
			'form' => [
				'data-usecase' => 'record',
				'action' => "javascript:api.record('post', 'record')"],
			'content' => []
			]];

		foreach(explode(',', $form['content']) as $usedcomponent) {
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name'));
			$statement->execute([
				':name' => $usedcomponent
			]);
			$component = $statement->fetch(PDO::FETCH_ASSOC);
			$component['content'] = json_decode($component['content'], true);
			//$component['content']['name'] = $usedcomponent;
			array_push($return['body']['content'], ...$component['content']['content']);
		}
		$this->response($return);
	}


	private function identifierPDF($content){
		// create a pdf for a label sheet with qr code and plain text
		require_once($this->PDFLIBRARY);
		// create new PDF document
		$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, INI['pdf']['labelsheet']['format'], true, 'UTF-8', false);

		// set document information
		$pdf->SetCreator(INI['system']['caroapp']);
		$pdf->SetAuthor($_SESSION['user']['name']);
		$pdf->SetTitle(LANG::GET('record.create_identifier'));
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		// set margins
		$pdf->SetMargins(0, 0, 0, 0);
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, 10); // margin bottom
		// set font
		$pdf->SetFont('helvetica', '', 10); // font size
		// add a page
		$pdf->AddPage();
		// set cell padding
		$pdf->setCellPaddings(0, 0, 0, 0);
		// set cell margins
		$pdf->setCellMargins(0, 0, 0, 0);
		// set color for background
		$pdf->SetFillColor(255, 255, 255);

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		$format = TCPDF_STATIC::getPageSizeFromFormat(INI['pdf']['labelsheet']['format']);
		$rowheight = (($format[1] * 25.4 / 72 ) - (INI['pdf']['labelsheet']['margintop'] + INI['pdf']['labelsheet']['marginbottom']))/ INI['pdf']['labelsheet']['rows'];
		$columnwidth = ($format[0] * 25.4 / 72 ) / INI['pdf']['labelsheet']['columns'];
		$codesize = min($columnwidth, $rowheight) - 10; // font size
		$style = array(
			'border' => 0,
			'vpadding' => 'auto',
			'hpadding' => 'auto',
			'fgcolor' => array(0,0,0),
			'bgcolor' => false, //array(255,255,255)
			'module_width' => 1, // width of a single module in points
			'module_height' => 1 // height of a single module in points
		);

		for ($row = 0; $row < INI['pdf']['labelsheet']['rows']; $row++){
			for ($column = 0; $column < INI['pdf']['labelsheet']['columns']; $column++){

				$pdf->write2DBarcode($content, 'QRCODE,H', $column * $columnwidth, $row * $rowheight, $codesize, $codesize, $style, 'N');
				//$pdf->Text($column * $columnwidth, $row * $rowheight + $codesize, $content);
				$pdf->MultiCell($columnwidth - $codesize, $rowheight, $content, 0, '', 0, intval($column === INI['pdf']['labelsheet']['columns'] - 1), $column * $columnwidth + $codesize, $row * $rowheight, true, 0, false, true, 24, 'T', true);
			}
		}
		// move pointer to last page
		$pdf->lastPage();

		//Close and output PDF document
		if (!file_exists(UTILITY::directory('tmp'))) mkdir(UTILITY::directory('tmp'), 0777, true);
		$filename = preg_replace('/[^\w\d]/', '', $content);
		$pdf->Output(__DIR__ . '/' . UTILITY::directory('tmp') . '/' .$filename, 'F');
		return substr(UTILITY::directory('tmp') . '/' .$filename, 1);
	}

}

$api = new record();
$api->processApi();

exit;
?>
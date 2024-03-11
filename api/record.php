<?php
// add and export records
// Y U NO DELETE? because of audit safety, that's why!
require_once('../libraries/TCPDF/tcpdf_import.php');

class RECORDTCPDF extends TCPDF {
	// custom pdf header and footer
	public $qrcodesize = null;
	public $qrcodecontent = null;
	public $header = null;

	public function __construct($orientation='P', $unit='mm', $format='A4', $unicode=true, $encoding='UTF-8', $diskcache=false, $pdfa=false, $qrcodesize=20, $qrcodecontent='', $header=['title' => '', 'date' => '']){
		parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
		$this->qrcodesize = $qrcodesize;
		$this->qrcodecontent = $qrcodecontent;
		$this->header = $header;
	}

    //Page header
    public function Header() {
        // Title
		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		$this->SetFont('helvetica', 'B', 20); // font size
		$this->MultiCell(110, 0, $this->header['title'], 0, 'R', 0, 1, 90, 10, true, 0, false, true, 10, 'T', true);
		$this->SetFont('helvetica', '', 10); // font size
		$this->MultiCell(110, 0, $this->header['date'], 0, 'R', 0, 1, 90, 20, true, 0, false, true, 10, 'T', true);

		if ($this->qrcodecontent){
			$style = array(
				'border' => 0,
				'vpadding' => 'auto',
				'hpadding' => 'auto',
				'fgcolor' => array(0,0,0),
				'bgcolor' => false, //array(255,255,255)
				'module_width' => 1, // width of a single module in points
				'module_height' => 1 // height of a single module in points
			);
			$this->write2DBarcode($this->qrcodecontent, 'QRCODE,H', 10, 10, $this->qrcodesize, $this->qrcodesize, $style, 'N');
			$this->MultiCell(50, $this->qrcodesize, $this->qrcodecontent, 0, '', 0, 0, 10 + $this->qrcodesize, 10, true, 0, false, true, 24, 'T', true);
		}
	}

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, LANG::GET('company.location') . ' | '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

class record extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_passedIdentify = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_passedIdentify = array_key_exists(3, REQUEST) ? REQUEST[3] : '';
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
					$body = [
						[
							'type' => 'links',
							'description' => LANG::GET('record.create_identifier_proceed'),
							'content' => $downloadfiles
						]
					];
					$this->response([
						'body' => $body
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

	public function formfilter(){
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

	public function recordfilter(){
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('records_identifiers'));
		$statement->execute();
		$records = $statement->fetchAll(PDO::FETCH_ASSOC);
		$matches = [];
		foreach($records as $row) {
			similar_text($this->_requestedID, $row['identifier'], $percent);
			if (($percent >= INI['likeliness']['records_search_similarity'] || !$this->_requestedID) && !in_array($row['id'], $matches)) $matches[] = strval($row['id']);
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
							'onkeypress' => "if (event.key === 'Enter') {api.record('get', 'formfilter', this.value); return false;}",
							'onblur' => "api.record('get', 'formfilter', this.value); return false;",
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
		if (!$form = $statement->fetch(PDO::FETCH_ASSOC)) $this->response(['status' => ['msg' => LANG::GET('assemble.error_form_not_found', [':name' => $this->_requestedID])]]);

		$return = ['title'=> $form['name'], 'body' => [
			'form' => [
				'data-usecase' => 'record',
				'action' => "javascript:api.record('post', 'record')"],
			'content' => []
			]];

		function setidentifier($element, $identify){
			$content = [];
			foreach($element as $subs){
				if (!array_key_exists('type', $subs)){
					$content[] = setidentifier($subs, $identify);
				}
				else {
					if ($subs['type'] === 'identify'){
						$subs['attributes']['value'] = $identify;
					}
					if (in_array($subs['type'], ['textarea', 'textinput', 'scanner', 'textinput', 'numberinput', 'dateinput', 'timeinput'])){
						$subs['attributes']['data-loss'] = 'prevent';
					}
					$content[] = $subs;
				}
			}
			return $content;
		};

		foreach(explode(',', $form['content']) as $usedcomponent) {
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name'));
			$statement->execute([
				':name' => $usedcomponent
			]);
			$component = $statement->fetch(PDO::FETCH_ASSOC);
			$component['content'] = json_decode($component['content'], true);
			array_push($return['body']['content'], ...setidentifier($component['content']['content'], $this->_passedIdentify));
		}
		$context = [
			[
				'type' => 'hiddeninput',
				'attributes' => [
					'name' => 'context',
					'value' => $form['context']
				]
			], [
				'type' => 'hiddeninput',
				'attributes' => [
					'name' => 'form_name',
					'value' => $form['name']
				]
			], [
				'type' => 'hiddeninput',
				'attributes' => [
					'name' => 'form_id',
					'value' => $form['id']
				]
			]
		];
		$exportpermissions = ['admin', 'supervisor'];
		if (array_intersect($exportpermissions, $_SESSION['user']['permissions'])){
			$return['body']['content'][]= [
				[
					'type' => 'button',
					'hint' => LANG::GET('record.form_export_hint'),
					'attributes' => [
						'type' => 'button',
						'value' => LANG::GET('record.form_export'),
						'onpointerup' => "api.record('get', 'exportform', " . $form['id'] . ")"
					]
				]
			];
		}
		else {
			$return['body']['content'][]= [
				[
					'type' => 'text',
					'description' => LANG::GET('record.form_export_permission', [':permissions' => implode(', ', array_map(fn($v)=>LANGUAGEFILE['permissions'][$v], $exportpermissions))])
				]
			];
		}
		if (array_key_exists('type', $return['body']['content'][0][0])) array_push($return['body']['content'][0], ...$context);
		else array_push($return['body']['content'][0][0], ...$context);
		$this->response($return);
	}

	public function matchbundles(){
		if (!(array_intersect(['user'], $_SESSION['user']['permissions']))) $this->response([], 401);
		$forms = [];
		$return = [];

		// prepare existing bundle lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_bundle-get-latest-by-name'));
		$statement->execute([
			':name' => $this->_requestedID
		]);
		$bundle = $statement->fetch(PDO::FETCH_ASSOC);
		$necessaryforms = explode(',', $bundle['content']);

		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('records_import'));
		$statement->execute([
			':identifier' => $this->_passedIdentify
		]);
		$data = $statement->fetchAll(PDO::FETCH_ASSOC);
		$considered = [];
		foreach($data as $row){
			$considered[] = $row['form_name'];
		}
		foreach(array_diff($necessaryforms, $considered) as $needed){
			$forms[$needed] = ['href' => "javascript:api.record('get', 'form', '" . $needed . "', '" . $this->_passedIdentify . "')"];
		}

		if ($forms) $return['body'] = [
			[
					'type' => 'links',
					'description' => LANG::GET('record.record_append_missing_form'),
					'content' => $forms
			]
		];
		else  $return['body'] =[
			[
					'type' => 'text',
					'content' => LANG::GET('record.record_append_missing_form_unneccessary'),
			]
		];
		$this->response($return);
	}

	public function record(){
		if (!(array_intersect(['user'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$context = $form_name = $form_id = null;
				$identifier = '';
				$grouped_checkboxes = [];
				if ($context = UTILITY::propertySet($this->_payload, 'context')) unset($this->_payload->context);
				if ($form_name = UTILITY::propertySet($this->_payload, 'form_name')) unset($this->_payload->form_name);
				if ($form_id = UTILITY::propertySet($this->_payload, 'form_id')) unset($this->_payload->form_id);
				foreach($this->_payload as $key => &$value){
					if (substr($key, 0, 12) === 'IDENTIFY_BY_'){
						$identifier = $value;
						unset ($this->_payload->$key);
					}
					if (gettype($value) === 'array') $value = trim(implode(' ', $value));
					/////////////////////////////////////////
					// BEHOLD! unsetting value==on relies on a prepared formdata/_payload having a dataset containing all selected checkboxes
					////////////////////////////////////////
					if (!$value || $value == 'on') unset($this->_payload->$key);
				}

				if (!file_exists(UTILITY::directory('record_attachments'))) mkdir(UTILITY::directory('record_attachments'), 0777, true);
				$attachments = [];
				foreach ($_FILES as $fileinput => $files){
					if ($uploaded = UTILITY::storeUploadedFiles([$fileinput], UTILITY::directory('record_attachments'), [preg_replace('/[^\w\d]/m', '', $identifier . '_' . $fileinput)], null, false)){
						if (gettype($files['name']) === 'array'){
							for($i = 0; $i < count($files['name']); $i++){
								if (array_key_exists($fileinput, $attachments)) $attachments[$fileinput][]= substr($uploaded[$i], 1);
								else $attachments[$fileinput] = [substr($uploaded[$i], 1)];
							}
						}
						else $attachments[$fileinput] = [substr($uploaded[0], 1)];
					}
				}
				foreach($attachments as $input => $files){
					$this->_payload->$input = implode(', ', $files);
				}
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('records_post'));
				if (boolval((array) $this->_payload) && $statement->execute([
					':context' => $context,
					':form_name' => $form_name,
					':form_id' => $form_id,
					':identifier' => $identifier,
					':author' => $_SESSION['user']['name'],
					':author_id' => $_SESSION['user']['id'],
					':content' => json_encode($this->_payload)
				])) $this->response([
					'status' => [
						'msg' => LANG::GET('record.record_saved')
					]]);
				else $this->response([
					'status' => [
						'msg' => LANG::GET('record.record_error')
					]]);
				break;
			case 'GET':
				$return = ['body' =>[]];
				$body = [];
				// summarize content
				$content = $this->summarizeRecord();
				$body[] = [[
					'type' => 'text',
					'description' => LANG::GET('record.create_identifier'),
					'content' => $this->_requestedID
				]];
				foreach($content['content'] as $form => $entries){
					$body[] = [
						[
							'type' => 'text',
							'description' => $form
						]
					];
					foreach($entries as $key => $value){
					array_push($body[count($body) -1],
						[
							'type' => 'text',
							'description' => $key,
							'content' => $value
						]); 
					}
					if (array_key_exists($form, $content['images'])){
						foreach ($content['images'][$form] as $image){
							$imagedata = pathinfo($image);
							array_push($body[count($body) -1],
							[
								'type' => 'image',
								'description' => $imagedata['basename'],
								'attributes' => [
									'name' => $imagedata['basename'],
									'url' => $image
								]
							]); 
						}
					}
					if (array_key_exists($form, $content['files'])){
						array_push($body[count($body) -1],
						[
							'type' => 'links',
							'description' => LANG::GET('record.record_file_attachments'),
							'content' => $content['files'][$form]
						]); 
					}
				}
				
		
				$return['body']['content'] = $body;

				$bundles = ['...' . LANG::GET('record.record_match_bundles_default') => ['value' => '0']];
				// match against bundles
				// prepare existing bundle lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_bundle-datalist'));
				$statement->execute();
				$bd = $statement->fetchAll(PDO::FETCH_ASSOC);
				$hidden = [];
				foreach($bd as $key => $row) {
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (!in_array($row['name'], $bundles) && !in_array($row['name'], $hidden)) {
						$bundles[$row['name']] = ['value' => $row['name']];
					}
				}

				$return['body']['content'][] = [
					[
						[
							'type' => 'select',
							'attributes' => [
								'name' => LANG::GET('record.record_match_bundles'),
								'onchange' => "if (this.value != '0') api.record('get', 'matchbundles', this.value, '" . $this->_requestedID . "')"
							],
							'hint' => LANG::GET('record.record_match_bundles_hint'),
							'content' => $bundles
						], [
							'type' => 'button',
							'attributes' => [
								'value' => LANG::GET('record.record_export'),
								'onpointerup' => "api.record('get', 'export', '" . $this->_requestedID . "')"
							]
						]
					]
				];
				$this->response($return);
				break;
			default:
				$this->response([], 401);
		}
	}

	public function import(){
		if (!(array_intersect(['user'], $_SESSION['user']['permissions']))) $this->response([], 401);
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('records_import'));
		$statement->execute([
			':identifier' => $this->_payload->IDENTIFY_BY_
		]);
		$data = $statement->fetchAll(PDO::FETCH_ASSOC);
		if ($data) {
			$result = [];
			foreach($data as $row)
				foreach(json_decode($row['content'], true) as $key => $value) $result[$key] = $value;
			$this->response([
			'status' => [
				'msg' => LANG::GET('record.record_import_success'),
				'data' => $result
			]]);
		}
		else $this->response([
			'status' => [
				'msg' => LANG::GET('record.record_import_error')
			]]);

	}

	public function records(){
		if (!(array_intersect(['user'], $_SESSION['user']['permissions']))) $this->response([], 401);
		$return = ['body' => ['content' => []]];
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('records_identifiers'));
		$statement->execute();
		$data = $statement->fetchAll(PDO::FETCH_ASSOC);
		if (!$data) {
			$result['body']['content'] = $this->noContentAvailable(LANG::GET('message.no_messages'));
			$this->response($result);		
		}
		$recorddatalist = $contexts = [];

		// sort records to user units, others and these that can not be assigned due to deleted user ids
		$unassigned = [];
		foreach($data as $row){
			if (!in_array($row['identifier'], $recorddatalist)) $recorddatalist[] = $row['identifier'];
			if ($row['units']){
				if (array_intersect(explode(',', $row['units']), $_SESSION['user']['units'])) $target = 'units';
				else $target = 'other';
			} else $target = 'unassigned';
			if (!array_key_exists($row['context'], $contexts)) $contexts[$row['context']] = ['units' => [], 'other' => [], 'unassigned' => []];
			$contexts[$row['context']][$target][$row['identifier']] = ['href' => "javascript:api.record('get', 'record', '" . $row['identifier'] . "')", 'data-filtered' => $row['id']];
		}

		$content = [
			[
				[
					'type' => 'datalist',
					'content' => $recorddatalist,
					'attributes' => [
						'id' => 'records'
					]
				], [
					'type' => 'scanner',
					'destination' => 'recordfilter',
					'description' => LANG::GET('record.record_scan')
				], [
					'type' => 'filterinput',
					'attributes' => [
						'id' => 'recordfilter',
						'name' => LANG::GET('record.record_filter'),
						'list' => 'records',
						'onkeypress' => "if (event.key === 'Enter') {api.record('get', 'recordfilter', this.value); return false;}",
						'onblur' => "api.record('get', 'recordfilter', this.value); return false;",
						]
				]
			]
		];
		$contextrows = [];
		foreach($contexts as $context => $targets){
			$contextcolumns = [];
			if ($targets) foreach($targets as $target => $identifiers){
				if ($identifiers) {
					$contextcolumns[] = 
					[[
						'type' => 'links',
						'description' => LANG::GET('record.record_list.' . $target, [':context' => LANG::GET('formcontext.' . $context)]),
						'content' => $identifiers
					]];
				}
			}
			if ($contextcolumns) $contextrows[] = $contextcolumns;
		}
		array_push($content, ...$contextrows);

		$result['body']['content'] = $content;
		$this->response($result);		
	}

	public function export(){
		if (!(array_intersect(['user'], $_SESSION['user']['permissions']))) $this->response([], 401);
		$content = $this->summarizeRecord();
		$downloadfiles = [];
		$downloadfiles[LANG::GET('menu.record_summary')] = [
			'href' => $this->recordsPDF($content)
		];

		$body = [];
		array_push($body, 
			[
				'type' => 'links',
				'description' =>  LANG::GET('record.record_export_proceed'),
				'content' => $downloadfiles
			]
		);
		$this->response([
			'body' => $body,
		]);
	}

	public function exportform(){
		if (!(array_intersect(['admin', 'supervisor'], $_SESSION['user']['permissions']))) $this->response([], 401);
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_get'));
		$statement->execute([
			':id' => $this->_requestedID
		]);
		$form = $statement->fetch(PDO::FETCH_ASSOC);
		$summary = [
			'filename' => preg_replace('/[^\w\d]/', '', $form['name'] . '_' . date('Y-m-d H:i')),
			'identifier' => LANG::GET('record.form_export_identifier'),
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('record.record_export_form', [':form' => $form['name'], ':date' => $form['date']]),
			'date' => LANG::GET('record.form_export_printed', [':date' => date('y-m-d H:i')])
		];

		function printable($element){
			// todo: enumerate names
			$content = ['content' => [], 'images' => []];
			foreach($element as $subs){
				if (!array_key_exists('type', $subs)){
					$subcontent = printable($subs);
					$content['content'] = array_merge($content['content'], $subcontent['content']);
					$content['images'] = array_merge($content['images'], $subcontent['images']);
				}
				else {
					if (in_array($subs['type'], ['identify', 'file', 'photo', 'links'])) continue;
					if (in_array($subs['type'], ['radio', 'checkbox', 'select'])){
						if ($subs['type'] ==='checkbox') $name = $subs['description'];
						else $name = $subs['attributes']['name'];
						$content['content'][$name] = '';
						foreach($subs['content'] as $key => $v){
							$content['content'][$name] .= "(  ) " . $key . "\n";
						}
					}
					elseif ($subs['type']==='text'){
						$content['content'][$subs['description']] = array_key_exists('content', $subs) ? $subs['content'] : '';
					}
					elseif ($subs['type']==='textarea'){
						$content['content'][$subs['attributes']['name']] = str_repeat("\n", 5);
					}
					elseif ($subs['type']==='image'){
						$content['content'][$subs['description']] = $subs['attributes']['url'];
						$file = pathinfo($subs['attributes']['url']);
						if (in_array($file['extension'], ['jpg', 'jpeg', 'gif', 'png'])) {
							$content['images'][] = $subs['attributes']['url'];
						}
					}
					else {
						$content['content'][$subs['attributes']['name']] = str_repeat("\n", 2);
					}
				}
			}
			return $content;
		};
		$componentscontent = [];
		foreach(explode(',', $form['content']) as $usedcomponent) {
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name'));
			$statement->execute([
				':name' => $usedcomponent
			]);
			$component = $statement->fetch(PDO::FETCH_ASSOC);
			$component['content'] = json_decode($component['content'], true);

			$printablecontent = printable($component['content']['content']);
			$summary['content'] = array_merge($summary['content'], $printablecontent['content']);
			$summary['images'] = array_merge($summary['images'], $printablecontent['images']);
		}
		$summary['content'] = [' ' => $summary['content']];
		$summary['images'] = [' ' => $summary['images']];
		$downloadfiles[LANG::GET('record.form_export')] = [
			'href' => $this->recordsPDF($summary)
		];
		$this->response([
			'body' => [
				[
					'type' => 'links',
					'description' =>  LANG::GET('record.form_export_proceed'),
					'content' => $downloadfiles
				]
			],
		]);
	}

	private function summarizeRecord(){
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('records_import'));
		$statement->execute([
			':identifier' => $this->_requestedID
		]);
		$data = $statement->fetchAll(PDO::FETCH_ASSOC);
		$summary = [
			'filename' => preg_replace('/[^\w\d]/', '', $this->_requestedID . '_' . date('Y-m-d H:i')),
			'identifier' => $this->_requestedID,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('menu.record_summary'),
			'date' => date('y-m-d H:i')
		];
		$accumulatedcontent = [];
		foreach ($data as $row){
			$form = LANG::GET('record.record_export_form', [':form' => $row['form_name'], ':date' => $row['form_date']]);
			if (!array_key_exists($form, $accumulatedcontent)) $accumulatedcontent[$form] = [];

			$content = json_decode($row['content'], true);
			foreach($content as $key => $value){
				$key = str_replace('_', ' ', $key);
				if (!array_key_exists($key, $accumulatedcontent[$form])) $accumulatedcontent[$form][$key] = [['value' => $value, 'author' => LANG::GET('record.record_export_author', [':author' => $row['author'], ':date' => $row['date']])]];
				else $accumulatedcontent[$form][$key][] = ['value' => $value, 'author' => LANG::GET('record.record_export_author', [':author' => $row['author'], ':date' => $row['date']])];
			}
		}
		foreach($accumulatedcontent as $form => $entries){
			$summary['content'][$form] = [];
			foreach($entries as $key => $data){
			$summary['content'][$form][$key] = '';
			$value = '';
			foreach($data as $entry){
				if ($entry['value'] !== $value){
					$displayvalue = $entry['value'];
					// guess file url; special regex delimiter
					if (stripos($entry['value'], substr(UTILITY::directory('record_attachments'), 1)) !== false) {
						$file = pathinfo($entry['value']);
						if (in_array($file['extension'], ['jpg', 'jpeg', 'gif', 'png'])) {
							if (!array_key_exists($form, $summary['images'])) $summary['images'][$form] = [];
							$summary['images'][$form][] = $entry['value'];
						}
						else {
							if (!array_key_exists($form, $summary['files'])) $summary['files'][$form] = [];
							$summary['files'][$form][$file['basename']] = ['href' => $entry['value']];
						}
						$displayvalue = $file['basename'];
					}
					$summary['content'][$form][$key] .= $displayvalue . ' (' . $entry['author'] . ")\n";
					$value = $entry['value'];
				}
			}
		}}
		return $summary;
	}

	private function identifierPDF($content){
		// create a pdf for a label sheet with qr code and plain text
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
				$pdf->MultiCell($columnwidth - $codesize, $rowheight, $content, 0, '', 0, intval($column === INI['pdf']['labelsheet']['columns'] - 1), $column * $columnwidth + $codesize, $row * $rowheight, true, 0, false, true, 24, 'T', true);
			}
		}
		// move pointer to last page
		$pdf->lastPage();

		//Close and output PDF document
		UTILITY::tidydir('tmp', INI['lifespan']['tmp']);
		$filename = preg_replace('/[^\w\d]/', '', $content) . '.pdf';
		$pdf->Output(__DIR__ . '/' . UTILITY::directory('tmp') . '/' .$filename, 'F');
		return substr(UTILITY::directory('tmp') . '/' .$filename, 1);
	}

	private function recordsPDF($content){
		// create a pdf for a record summary
		// create new PDF document
		$pdf = new RECORDTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, INI['pdf']['record']['format'], true, 'UTF-8', false, false,
		20, $content['identifier'], ['title' => $content['title'], 'date' => $content['date']]);

		// set document information
		$pdf->SetCreator(INI['system']['caroapp']);
		$pdf->SetAuthor($_SESSION['user']['name']);
		$pdf->SetTitle($content['title']);

		// set margins
		$pdf->SetMargins(INI['pdf']['record']['marginleft'], PDF_MARGIN_HEADER + INI['pdf']['record']['margintop'], INI['pdf']['record']['marginright'],1);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, INI['pdf']['record']['marginbottom']); // margin bottom
		// add a page
		$pdf->AddPage();
		// set cell padding
		$pdf->setCellPaddings(5, 5, 5, 5);
		// set cell margins
		$pdf->setCellMargins(0, 0, 0, 0);
		// set color for background
		$pdf->SetFillColor(255, 255, 255);

		// MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x=null, $y=null, $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)
		// Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='', $align='', $resize=false, $dpi=300, $palign='', $ismask=false, $imgmask=false, $border=0, $fitbox=false, $hidden=false, $fitonpage=false, $alt=false, $altimgs=array())
		
		foreach($content['content'] as $form => $entries){
			$pdf->SetFont('helvetica', '', 12); // font size
			$pdf->MultiCell(150, 4, $form, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			foreach($entries as $key => $value){
				$pdf->SetFont('helvetica', 'B', 10); // font size
				$pdf->MultiCell(50, 4, $key, 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
				$pdf->SetFont('helvetica', '', 10); // font size
				$pdf->MultiCell(150, 4, $value, 0, '', 0, 1, 60, null, true, 0, false, true, 0, 'T', false);
			}
			if (array_key_exists($form, $content['images'])){
				$ln = 0;
				foreach ($content['images'][$form] as $image){
					$imagedata = pathinfo($image);
					$pdf->SetFont('helvetica', 'B', 10); // font size
					$pdf->MultiCell(50, INI['pdf']['exportimage']['maxheight'], $imagedata['basename'], 0, '', 0, 0, 15, null, true, 0, false, true, 0, 'T', false);
					$pdf->Image('.' . $image, null, null, 0, INI['pdf']['exportimage']['maxheight'] - 1, '', '', 'R', true, 300, 'R');
					$pdf->Ln(INI['pdf']['exportimage']['maxheight']);
				}
			}
		}

		// move pointer to last page
		$pdf->lastPage();

		//Close and output PDF document
		UTILITY::tidydir('tmp', INI['lifespan']['tmp']);
		$pdf->Output(__DIR__ . '/' . UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 'F');
		return substr(UTILITY::directory('tmp') . '/' .$content['filename'] . '.pdf', 1);
	}
}

$api = new record();
$api->processApi();

exit;
?>
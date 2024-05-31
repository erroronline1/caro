<?php
// add and export records
// Y U NO DELETE? because of audit safety, that's why!
require_once('./pdf.php');
require_once('calendarutility.php');


class record extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_passedIdentify = null;
	private $_formExport = null;

	public function __construct(){
		parent::__construct();
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);

		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_passedIdentify = $this->_formExport = array_key_exists(3, REQUEST) ? REQUEST[3] : '';
	}

	/**
	 * returns the latest approved form, component by name from query
	 * @param string $query as defined within sqlinterface
	 * @param string $name
	 * @return array|bool either query row or false
	 */
	private function latestApprovedName($query = '', $name = ''){
		// get latest approved by name
		$element = [];
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE($query));
		$statement->execute([
			':name' => $name
		]);
		$elements = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach ($elements as $element){
			if (PERMISSION::fullyapproved('formapproval', $element['approval'])) return $element;
		}
		return false;
	}

	public function identifier(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if ($content = UTILITY::propertySet($this->_payload, LANG::PROPERTY('record.create_identifier'))) {
					$possibledate = substr($content, -16);
					try {
						new DateTime($possibledate);
					}
					catch (Exception $e){
						$now = new DateTime('now', new DateTimeZone(INI['timezone']));
						$content .= ' ' . $now->format('Y-m-d H:i');
					}
				}
				if ($content){
					$downloadfiles = [];
					$downloadfiles[LANG::GET('record.create_identifier')] = [
						'href' => PDF::identifierPDF($content)
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
					'msg' => LANG::GET('record.create_identifier_error'),
					'type' => 'error'
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
			'data' => $matches,
			'filter' => !$this->_requestedID ? 'all': 'some'
		]]);
	}

	public function forms(){
		$formdatalist = $forms = [];
		$return = [];

		// prepare existing forms lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-datalist'));
		$statement->execute();
		$fd = $statement->fetchAll(PDO::FETCH_ASSOC);
		$hidden = [];
		foreach($fd as $key => $row) {
			if (!PERMISSION::fullyapproved('formapproval', $row['approval'])) continue;
			if ($row['hidden'] || in_array($row['context'], array_keys(LANGUAGEFILE['formcontext']['notdisplayedinrecords']))) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $formdatalist) && !in_array($row['name'], $hidden)) {
				$formdatalist[] = $row['name'];
				$forms[$row['context']][$row['name']] = ['href' => "javascript:api.record('get', 'form', '" . $row['name'] . "')", 'data-filtered' => $row['id']];
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
				]
			]];
		foreach ($forms as $context => $list){
			$contexttranslation = '';
			foreach (LANGUAGEFILE['formcontext'] as $formcontext => $contexts){
				if (array_key_exists($context, $contexts)){
					$contexttranslation = $contexts[$context];
					break;
				}
			}
			$return['body']['content'][] = 					[
				'type' => 'links',
				'description' => $contexttranslation,
				'content' => $list
			];

		}
		$this->response($return);
	}

	public function form(){
		// prepare existing forms lists
		$form = $this->latestApprovedName('form_form-get-by-name', $this->_requestedID);
		if (!$form || $form['hidden']) $this->response(['status' => ['msg' => LANG::GET('assemble.error_form_not_found', [':name' => $this->_requestedID]), 'type' => 'error']]);

		$return = ['title'=> $form['name'], 'body' => [
			'form' => [
				'data-usecase' => 'record',
				'action' => "javascript:api.record('post', 'record')",
				'data-confirm' => true],
			'content' => []
			]];

			$calendar = new CALENDARUTILITY($this->_pdo);
			function setidentifier($element, $identify, $calendar){
			$content = [];
			foreach($element as $subs){
				if (!array_key_exists('type', $subs)){
					$content[] = setidentifier($subs, $identify, $calendar);
				}
				else {
					if ($subs['type'] === 'identify'){
						$subs['attributes']['value'] = $identify;
					}
					if ($subs['type'] === 'calendarbutton'){
						$subs['attributes']['value'] = LANG::GET('calendar.event_new');
						$subs['attributes']['onpointerup'] = $calendar->dialog([':type'=>'schedule']);
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
			$component = $this->latestApprovedName('form_component-get-by-name', $usedcomponent);
			if ($component){
				$component['content'] = json_decode($component['content'], true);
				array_push($return['body']['content'], ...setidentifier($component['content']['content'], $this->_passedIdentify, $calendar));
			}
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
		if (PERMISSION::permissionFor('formexport')){
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
					'description' => LANG::GET('record.form_export_permission', [':permissions' => implode(', ', array_map(fn($v)=>LANGUAGEFILE['permissions'][$v], PERMISSION::permissionFor('formexport', true)))])
				]
			];
		}
		if (array_key_exists('type', $return['body']['content'][0][0])) array_push($return['body']['content'][0], ...$context);
		else array_push($return['body']['content'][0][0], ...$context);
		$this->response($return);
	}

	public function matchbundles(){
		$forms = [];
		$return = [];

		// prepare existing bundle lists
		$bundle = $this->latestApprovedName('form_bundle-get-by-name', $this->_requestedID);
		if(!$bundle) $bundle = ['content' => []];
		$necessaryforms = $bundle['content'] ? explode(',', $bundle['content']) : [];

		// unset hidden forms from bundle presets
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-datalist'));
		$statement->execute();
		$allforms = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach($allforms as $row){
			if (!PERMISSION::fullyapproved('formapproval', $row['approval'])) continue;
			if ($row['hidden'] && ($key = array_search($row['name'], $necessaryforms)) !== false) unset($necessaryforms[$key]);
		}

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
					if ($uploaded = UTILITY::storeUploadedFiles([$fileinput], UTILITY::directory('record_attachments'), [preg_replace('/[^\w\d]/m', '', $identifier . '_' . date('YmdHis') . '_' . $fileinput)], null, false)){
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
						'msg' => LANG::GET('record.record_saved'),
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'msg' => LANG::GET('record.record_error'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$return = ['body' =>[]];
				$body = [];
				$closed = false;
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
					array_push($body[count($body) -1],[
						'type' => 'button',
						'attributes' => [
							'value' => LANG::GET('record.record_form_export'),
							'onpointerup' => "api.record('get', 'formexport', '" . $this->_requestedID . "', '" . $form . "')"
						]
						]);
				}
		
				$return['body']['content'] = $body;

				if (!array_intersect(['group'], $_SESSION['user']['permissions'])){
					// simple groups are not allowed to append to form
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
								'value' => LANG::GET('record.record_full_export'),
								'onpointerup' => "api.record('get', 'fullexport', '" . $this->_requestedID . "')"
							]
						], [
							'type' => 'button',
							'attributes' => [
								'value' => LANG::GET('record.record_simplified_export'),
								'onpointerup' => "api.record('get', 'simplifiedexport', '" . $this->_requestedID . "')"
							]
						]
					];
					if (PERMISSION::permissionFor('recordsclosing') && !$content['closed']){
						array_unshift($return['body']['content'][count($return['body']['content']) - 1], [
							'type' => 'button',
							'attributes' => [
								'value' => LANG::GET('record.record_mark_as_closed'),
								'onpointerup' => "new Dialog({type: 'confirm', header: '". LANG::GET('record.record_mark_as_closed') ."', 'body': '" . LANG::GET('record.record_mark_as_closed_info') . "', 'options':{".
									"'" . LANG::GET('general.cancel_button') . "': false,".
									"'" . LANG::GET('record.record_mark_as_closed') . "': {value: true, class: 'reducedCTA'},".
									"}}).then(confirmation => {if (confirmation) {this.disabled = true; api.record('put', 'close', '" . $this->_requestedID . "')}})"
							]
						]);
					}
				}
				$this->response($return);
				break;
			default:
				$this->response([], 401);
		}
	}

	public function close(){
		if (!PERMISSION::permissionFor('recordsclosing')) $this->response([], 401);
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('records_close'));
		$statement->execute([
			':identifier' => $this->_requestedID
		]);
		$this->response([
			'status' => [
				'msg' => LANG::GET('record.record_mark_as_closed_info'),
				'type' => 'success'
			]]);

	}

	public function import(){
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
				'data' => $result,
				'type' => 'success'
			]]);
		}
		else $this->response([
			'status' => [
				'msg' => LANG::GET('record.record_import_error'),
				'type' => 'error'
			]]);

	}

	public function records(){
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
		$targets = array_keys(LANGUAGEFILE['record']['record_list']); // ['units', 'other', 'unassigned']
		foreach($data as $row){
			if (!in_array($row['identifier'], $recorddatalist)) $recorddatalist[] = $row['identifier'];
			if ($row['units']){
				if (array_intersect(explode(',', $row['units']), $_SESSION['user']['units'])) $target = 0;
				else $target = 1;
			} else $target = 2;
			foreach(LANGUAGEFILE['formcontext'] as $key => $subkeys){
				if (in_array($row['context'], array_keys($subkeys))) $row['context'] = $key . '.' . $row['context'];
			}
			if (!array_key_exists($row['context'], $contexts)) $contexts[$row['context']] = ['units' => [], 'other' => [], 'unassigned' => []];
			$contexts[$row['context']][$targets[$target]][$row['identifier']] = ['href' => "javascript:api.record('get', 'record', '" . $row['identifier'] . "')", 'data-filtered' => $row['id']];
			if ($row['closed'] || count($contexts[$row['context']][$targets[$target]]) > INI['limits']['max_records']) {
				$contexts[$row['context']][$targets[$target]][$row['identifier']]['style'] = 'display:none';
				$contexts[$row['context']][$targets[$target]][$row['identifier']]['data-filtered_max'] = $row['id'];
			}
		}
		// delete double entries, reset filted_max state
		foreach($contexts as &$context){
			$previouslydeleted = null;
			foreach ($context['unassigned'] as $identifier => $attributes){
				if ($previouslydeleted) {
					unset($context['unassigned'][$identifier]['data-filtered_max']);
					unset($context['unassigned'][$identifier]['style']);
				}
				if (array_key_exists($identifier, $context['units']) || array_key_exists($identifier, $context['other'])) {
					unset ($context['unassigned'][$identifier]);
					$previouslydeleted = true;
				}
				else $previouslydeleted = null;
			}
		}
		unset($context); // error otherwise

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
					'hint' => LANG::GET('record.record_filter_hint', [':max' => INI['limits']['max_records']]),
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

	public function fullexport(){
		$this->export('full');
	}

	public function simplifiedexport(){
		$this->export('simplified');
	}

	public function formexport(){
		$this->export('form');
	}

	private function export($summarize = "full"){
		$content = $this->summarizeRecord($summarize);
		$downloadfiles = [];
		$downloadfiles[LANG::GET('menu.record_summary')] = [
			'href' => PDF::recordsPDF($content)
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
		if (!PERMISSION::permissionFor('formexport')) $this->response([], 401);
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_get'));
		$statement->execute([
			':id' => $this->_requestedID
		]);
		$form = $statement->fetch(PDO::FETCH_ASSOC);
		$summary = [
			'filename' => preg_replace('/[^\w\d]/', '', $form['name'] . '_' . date('Y-m-d H:i')),
			'identifier' => in_array($form['context'], array_keys(LANGUAGEFILE['formcontext']['identify'])) ? LANG::GET('record.form_export_identifier'): null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('record.record_export_form', [':form' => $form['name'], ':date' => $form['date']]),
			'date' => LANG::GET('record.form_export_exported', [':date' => date('y-m-d H:i')])
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
					if (in_array($subs['type'], ['identify', 'file', 'photo', 'links', 'calendarbutton'])) continue;
					if (in_array($subs['type'], ['radio', 'checkbox', 'select'])){
						if ($subs['type'] ==='checkbox') $name = $subs['description'];
						else $name = $subs['attributes']['name'];
						$content['content'][$name] = [];
						foreach($subs['content'] as $key => $v){
							$content['content'][$name][] = $key;
						}
					}
					elseif ($subs['type']==='text'){
						$content['content'][$subs['description']] = array_key_exists('content', $subs) ? $subs['content'] : '';
					}
					elseif ($subs['type']==='textarea'){
						$content['content'][$subs['attributes']['name']] = str_repeat(" \n", 2);
					}
					elseif ($subs['type']==='image'){
						$content['content'][$subs['description']] = $subs['attributes']['url'];
						$file = pathinfo($subs['attributes']['url']);
						if (in_array($file['extension'], ['jpg', 'jpeg', 'gif', 'png'])) {
							$content['images'][] = $subs['attributes']['url'];
						}
					}
					else {
						$content['content'][$subs['attributes']['name']] = " ";
					}
				}
			}
			return $content;
		};
		$componentscontent = [];
		foreach(explode(',', $form['content']) as $usedcomponent) {
			$component = $this->latestApprovedName('form_component-get-by-name', $usedcomponent);
			$component['content'] = json_decode($component['content'], true);

			$printablecontent = printable($component['content']['content']);
			$summary['content'] = array_merge($summary['content'], $printablecontent['content']);
			$summary['images'] = array_merge($summary['images'], $printablecontent['images']);
		}
		$summary['content'] = [' ' => $summary['content']];
		$summary['images'] = [' ' => $summary['images']];
		$downloadfiles[LANG::GET('record.form_export')] = [
			'href' => PDF::formsPDF($summary)
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

	private function summarizeRecord($type = 'full'){
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
			'date' => date('y-m-d H:i'),
			'closed' => false
		];
		$accumulatedcontent = [];
		foreach ($data as $row){
			$summary['closed'] = boolval($row['closed']);
			$form = LANG::GET('record.record_export_form', [':form' => $row['form_name'], ':date' => $row['form_date']]);
			if (!array_key_exists($form, $accumulatedcontent)) $accumulatedcontent[$form] = [];

			$content = json_decode($row['content'], true);
			foreach($content as $key => $value){
				$key = str_replace('_', ' ', $key);
				if (!array_key_exists($key, $accumulatedcontent[$form])) $accumulatedcontent[$form][$key] = [['value' => $value, 'author' => LANG::GET('record.record_export_author', [':author' => $row['author'], ':date' => $row['date']])]];
				else $accumulatedcontent[$form][$key][] = ['value' => $value, 'author' => LANG::GET('record.record_export_author', [':author' => $row['author'], ':date' => $row['date']])];
			}
		}

		if ($type === 'form') {
			foreach(array_keys($accumulatedcontent) as $key) if ($key !==$this->_formExport) unset($accumulatedcontent[$key]);
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
						switch ($type){
							case 'form':
							case 'full':
								$summary['content'][$form][$key] .= $displayvalue . ' (' . $entry['author'] . ")\n";
								break;
							case 'simplified':
								$summary['content'][$form][$key] = $displayvalue . "\n";
								break;
						}
						$value = $entry['value'];
					}
				}
			}
		}
		return $summary;
	}
}

$api = new record();
$api->processApi();

exit;
?>
<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2024 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

 // add and export records
// Y U NO DELETE? because of audit safety, that's why!
require_once('./_pdf.php');
require_once('./_calendarutility.php');


class RECORD extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_appendDate = null;
	private $_passedIdentify = null;
	private $_formExport = null;

	public function __construct(){
		parent::__construct();
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);

		$this->_requestedID = $this->_appendDate = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_passedIdentify = $this->_formExport = array_key_exists(3, REQUEST) ? REQUEST[3] : '';
	}

	/**
	 *       _
	 *   ___| |___ ___ ___
	 *  |  _| | . |_ -| -_|
	 *  |___|_|___|___|___|
	 *
	 */
	public function close(){
		if (!PERMISSION::permissionFor('recordsclosing') && !PERMISSION::permissionFor('complaintclosing')) $this->response([], 401);
		if (!in_array($this->_passedIdentify, PERMISSION::permissionFor('recordsclosing', true)) && !in_array($this->_passedIdentify, PERMISSION::permissionFor('complaintclosing', true))) $this->response([], 401);
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_import', [
			'values' => [
				':identifier' => $this->_requestedID
			]
		]);
		$data = $data ? $data[count($data) - 1] : []; // most recent entry suffices 
		if (!$data) $this->response([], 204);
		$data['closed'] = $data['closed'] ? json_decode($data['closed'], true) : [];

		$time = new DateTime('now', new DateTimeZone(INI['application']['timezone']));
		$data['closed'][$this->_passedIdentify] = [
			'name' => $_SESSION['user']['name'],
			'date' => $time->format('Y-m-d H:i')
		];

		SQLQUERY::EXECUTE($this->_pdo, 'records_close', [
			'values' => [
				':closed' => json_encode($data['closed']),
				':identifier' => $this->_requestedID
			]
		]);
		$this->response([
			'response' => [
				'msg' => LANG::GET('record.record_mark_as_closed_info'),
				'type' => 'success'
			]]);
	}

	/**
	 *                       _
	 *   ___ _ _ ___ ___ ___| |_
	 *  | -_|_'_| . | . |  _|  _|
	 *  |___|_,_|  _|___|_| |_|
	 *          |_|
	 */
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
			'render' => $body,
		]);
	}

	/**
	 *                       _   ___
	 *   ___ _ _ ___ ___ ___| |_|  _|___ ___ _____
	 *  | -_|_'_| . | . |  _|  _|  _| . |  _|     |
	 *  |___|_,_|  _|___|_| |_| |_| |___|_| |_|_|_|
	 *          |_|
	 */
	public function exportform(){

		$context = $form_id = null;
		$identifier = null;
		if ($context = UTILITY::propertySet($this->_payload, 'context')) unset($this->_payload->context);
		if ($form_id = UTILITY::propertySet($this->_payload, 'form_id')) unset($this->_payload->form_id);
		if ($entry_date = UTILITY::propertySet($this->_payload, 'DEFAULT_' . LANG::PROPERTY('record.record_date'))) unset($this->_payload->{'DEFAULT_' . LANG::PROPERTY('record.record_date')});
		if ($entry_time = UTILITY::propertySet($this->_payload, 'DEFAULT_' . LANG::PROPERTY('record.record_time'))) unset($this->_payload->{'DEFAULT_' . LANG::PROPERTY('record.record_time')});

		$form = SQLQUERY::EXECUTE($this->_pdo, 'form_get', [
			'values' => [
				':id' => $form_id
			]
		]);
		$form = $form ? $form[0] : null;
		if (!PERMISSION::permissionFor('formexport') && !$form['permitted_export'] && !PERMISSION::permissionIn($form['restricted_access'])) $this->response([], 401);

		$entry_timestamp = $entry_date . ' ' . $entry_time;
		if (strlen($entry_timestamp) > 16) { // yyyy-mm-dd hh:ii
			$now = new DateTime('now', new DateTimeZone(INI['application']['timezone']));
			$entry_timestamp = $now->format('Y-m-d H:i');
		}

		foreach($this->_payload as $key => &$value){
			if (substr($key, 0, 12) === 'IDENTIFY_BY_'){
				$identifier = $value;
				unset ($this->_payload->$key);
				$possibledate = substr($identifier, -16);
				try {
					new DateTime($possibledate);
				}
				catch (Exception $e){
					$identifier .= ' ' . $entry_timestamp;
				}
			}
			if (gettype($value) === 'array') $value = trim(implode(' ', $value));
			/////////////////////////////////////////
			// BEHOLD! unsetting value==on relies on a prepared formdata/_payload having a dataset containing all selected checkboxes
			////////////////////////////////////////
			if (!$value || $value == 'on') unset($this->_payload->$key);
		}
		if (!$identifier) $identifier = in_array($form['context'], array_keys(LANGUAGEFILE['formcontext']['identify'])) ? LANG::GET('record.form_export_identifier'): null;
		$summary = [
			'filename' => preg_replace('/' . INI['forbidden']['names'][0] . '/', '', $form['name'] . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => $identifier,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('record.record_export_form', [':form' => $form['name'], ':date' => substr($form['date'], 0, -3)]),
			'date' => LANG::GET('record.form_export_exported', [':date' => $this->_currentdate->format('y-m-d H:i')])
		];

		function printable($element, $payload, $enumerate = []){
			$content = ['content' => [], 'images' => []];
			foreach($element as $subs){
				if (!array_key_exists('type', $subs)){
					$subcontent = printable($subs, $payload, $enumerate);
					$content['content'] = array_merge($content['content'], $subcontent['content']);
					$content['images'] = array_merge($content['images'], $subcontent['images']);
				}
				else {
					if (in_array($subs['type'], ['identify', 'file', 'photo', 'links', 'calendarbutton', 'formbutton'])) continue;
					if (in_array($subs['type'], ['checkbox', 'textblock', 'image'])) {
						$name = $subs['description'];
					}
					else $name = $subs['attributes']['name'];
					if (isset($enumerate[$name])){
						$enumerate[$name]++;
						$name .= '(' . $enumerate[$name] . ')';
					}
					else $enumerate[$name] = 1;

					$postname = str_replace(' ', '_', $name);

					if (in_array($subs['type'], ['radio', 'checkbox', 'select'])){
						$content['content'][$name] = [];
						foreach($subs['content'] as $key => $v){
							$selected = '';
							if (UTILITY::propertySet($payload, $name) && (
								($subs['type'] !== 'checkbox' && $key === UTILITY::propertySet($payload, $postname)) ||
								($subs['type'] === 'checkbox' && in_array($key, explode(', ', UTILITY::propertySet($payload, $postname))))
								)) $selected = '_____';
							$content['content'][$name][] = $selected . $key;
						}
					}
					elseif ($subs['type']==='textblock'){
						$content['content'][$name] = isset($subs['content']) ? $subs['content'] : '';
					}
					elseif ($subs['type']==='textarea'){
						$content['content'][$name] = UTILITY::propertySet($payload, $postname) ? : str_repeat(" \n", 2);
					}
					elseif ($subs['type']==='signature'){
						$content['content'][$name] = str_repeat(" \n", 2);
					}
					elseif ($subs['type']==='image'){
						$content['content'][$name] = $subs['attributes']['url'];
						$file = pathinfo($subs['attributes']['url']);
						if (in_array($file['extension'], ['jpg', 'jpeg', 'gif', 'png'])) {
							$content['images'][] = $subs['attributes']['url'];
						}
					}
					elseif ($subs['type']==='range'){
						$content['content'][$name] = '(' . (isset($subs['attributes']['min']) ? $subs['attributes']['min'] : 0) . ' - ' . (isset($subs['attributes']['min']) ? $subs['attributes']['max'] : 100) . ') ' . (UTILITY::propertySet($payload, $postname) ? : '');
					}
					else {
						if (isset($name)) $content['content'][$name] = UTILITY::propertySet($payload, $postname) ? : ' ';
					}
				}
			}
			return $content;
		};
		$componentscontent = [];
		foreach(explode(',', $form['content']) as $usedcomponent) {
			$component = $this->latestApprovedName('form_component_get_by_name', $usedcomponent);
			$component['content'] = json_decode($component['content'], true);

			$printablecontent = printable($component['content']['content'], $this->_payload);
			$summary['content'] = array_merge($summary['content'], $printablecontent['content']);
			$summary['images'] = array_merge($summary['images'], $printablecontent['images']);
		}
		$summary['content'] = [' ' => $summary['content']];
		$summary['images'] = [' ' => $summary['images']];

		$downloadfiles[LANG::GET('record.form_export')] = [
			'href' => PDF::formsPDF($summary)
		];
		$this->response([
			'render' => [
				[
					'type' => 'links',
					'description' =>  LANG::GET('record.form_export_proceed'),
					'content' => $downloadfiles
				]
			],
		]);
	}
	
	/**
	 *   ___
	 *  |  _|___ ___ _____
	 *  |  _| . |  _|     |
	 *  |_| |___|_| |_|_|_|
	 *
	 */
	public function form(){
		// prepare existing forms lists
		$form = $this->latestApprovedName('form_form_get_by_name', $this->_requestedID);
		if (!$form || $form['hidden'] || !PERMISSION::permissionIn($form['restricted_access'])) $this->response(['response' => ['msg' => LANG::GET('assemble.error_form_not_found', [':name' => $this->_requestedID]), 'type' => 'error']]);

		$return = ['title'=> $form['name'], 'render' => [
			'content' => []
		]];

		// prefill identify if passed, prepare calendar button if part of the form
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
					if (in_array($subs['type'], ['textarea', 'scanner', 'text', 'number', 'date', 'time'])){
						$subs['attributes']['data-loss'] = 'prevent';
					}
					$content[] = $subs;
				}
			}
			return $content;
		};
		foreach(explode(',', $form['content']) as $usedcomponent) {
			$component = $this->latestApprovedName('form_component_get_by_name', $usedcomponent);
			if ($component){
				$component['content'] = json_decode($component['content'], true);
				array_push($return['render']['content'], ...setidentifier($component['content']['content'], $this->_passedIdentify, $calendar));
			}
		}

		// check if a submit button is applicable
		function saveable($element){
			$saveable = false;
			foreach($element as $subs){
				if (!array_key_exists('type', $subs)){
					if ($saveable = saveable($subs)) return true;
				}
				else {
					if (!in_array($subs['type'], ['textblock', 'image', 'links', 'hidden', 'button'])) return true;
				}
			}
			return $saveable;
		}
		if (saveable($return['render']['content'])) $return['render']['form'] = [
			'data-usecase' => 'record',
			'action' => "javascript:api.record('post', 'record')",
			'data-confirm' => true];

		$context = [
			[
				'type' => 'hidden',
				'attributes' => [
					'name' => 'context',
					'value' => $form['context']
				]
			], [
				'type' => 'hidden',
				'attributes' => [
					'name' => 'form_name',
					'value' => $form['name']
				]
			], [
				'type' => 'hidden',
				'attributes' => [
					'name' => 'form_id',
					'value' => $form['id']
				]
			]
		];

		if (isset($return['render']['form'])) {
			$now = new DateTime('now', new DateTimeZone(INI['application']['timezone']));
			$defaults = [
				[
					'type' => 'date',
					'attributes' => [
						'name' => 'DEFAULT_' . LANG::GET('record.record_date'),
						'value' => $now->format('Y-m-d'),
						'required' => true
					]
				], [
					'type' => 'time',
					'attributes' => [
						'name' => 'DEFAULT_' . LANG::GET('record.record_time'),
						'value' => $now->format('H:i'),
						'required' => true
					]
				]
			];
			if (in_array($form['context'], ['casedocumentation'])) {
				$options = [];
				foreach (LANGUAGEFILE['record']['record_type'] as $key => $value){
					$options[$value] = boolval(INI['application']['require_record_type_selection']) ? ['value' => $key, 'required' => true] : ['value' => $key];
				}
				$defaults[] = [
					'type' => 'radio',
					'attributes' => [
						'name' => 'DEFAULT_' . LANG::GET('record.record_type_description')
					],
					'content' => $options
				];
			}
			$return['render']['content'][] = $defaults;
		}

		if (PERMISSION::permissionFor('formexport') || $form['permitted_export']){
			$return['render']['content'][] = [
				[
					'type' => 'button',
					'hint' => LANG::GET('record.form_export_hint'),
					'attributes' => [
						'type' => 'submit',
						'value' => LANG::GET('record.form_export'),
						'formaction' => "javascript:api.record('post', 'exportform')"
					]
				]
			];
		}
		else {
			$return['render']['content'][] = [
				[
					'type' => 'textblock',
					'description' => LANG::GET('record.form_export_permission', [':permissions' => implode(', ', array_map(fn($v)=>LANGUAGEFILE['permissions'][$v], PERMISSION::permissionFor('formexport', true)))])
				]
			];
		}
		if (array_key_exists('type', $return['render']['content'][0][0])) array_push($return['render']['content'][0], ...$context);
		else array_push($return['render']['content'][0][0], ...$context);
		$this->response($return);
	}
	
	/**
	 *   ___                                   _
	 *  |  _|___ ___ _____ ___ _ _ ___ ___ ___| |_
	 *  |  _| . |  _|     | -_|_'_| . | . |  _|  _|
	 *  |_| |___|_| |_|_|_|___|_,_|  _|___|_| |_|
	 *                            |_|
	 */
	public function formexport(){
		$this->export('form');
	}
	
	/**
	 *   ___               ___ _ _ _
	 *  |  _|___ ___ _____|  _|_| | |_ ___ ___
	 *  |  _| . |  _|     |  _| | |  _| -_|  _|
	 *  |_| |___|_| |_|_|_|_| |_|_|_| |___|_|
	 *
	 */
	public function formfilter(){
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		$hidden = $matches = [];

		function findInComponent($element, $search){
			$found = false;
			foreach($element as $subs){
				if (!array_key_exists('type', $subs)){
					$found = findInComponent($subs, $search);
				}
				else {
					foreach (['description', 'content', 'hint'] as $property){
						if (array_key_exists($property, $subs)){
							if (is_array($subs[$property])){ // links, checkboxes,etc
								foreach(array_keys($subs[$property]) as $key) {
									similar_text($search, $key, $percent);
									if ($percent >= INI['likeliness']['file_search_similarity']) {
										return true;
									}
								}
							}
							else {
								if (stristr($subs[$property], $search) !== false) return true;
							}
						}
					}
					if (array_key_exists('attributes', $subs)){
						foreach (['name', 'value'] as $property){
							if (array_key_exists($property, $subs['attributes']) && stristr($subs['attributes'][$property], $search) !== false) return true;
						}
					}
				}
			}
			return $found;
		};

		foreach($fd as $row) {
			if ($row['hidden'] || !PERMISSION::permissionIn($row['restricted_access'])) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['id'], $matches) && !in_array($row['name'], $hidden)) {
				$terms = [$row['name']];
				foreach(preg_split('/[^\w\d]/', $row['alias']) as $alias) array_push($terms, $alias);
				foreach ($terms as $term){
					similar_text($this->_requestedID, $term, $percent);
					if (($percent >= INI['likeliness']['file_search_similarity'] || !$this->_requestedID) && !in_array($row['id'], $matches)) {
						$matches[] = strval($row['id']);
						continue;
					}
					foreach(explode(',', $row['regulatory_context']) as $context) {
						if (stristr(LANG::GET('regulatory.' . $context), $this->_requestedID) !== false) {
							$matches[] = strval($row['id']);
							continue;	
						}
					}
					foreach(explode(',', $row['content']) as $usedcomponent) {
						$component = $this->latestApprovedName('form_component_get_by_name', $usedcomponent);
						if ($component){
							$component['content'] = json_decode($component['content'], true);
							if (findInComponent($component['content']['content'], $this->_requestedID)) {
								$matches[] = strval($row['id']);
								break;
							}
						}
					}
				}
			}
		}
		$this->response([
			'data' => array_values(array_unique($matches))
		]);
	}
	
	/**
	 *   ___                   
	 *  |  _|___ ___ _____ ___ 
	 *  |  _| . |  _|     |_ -|
	 *  |_| |___|_| |_|_|_|___|
	 *   
	 */
	public function forms(){
		$formdatalist = $forms = [];
		$return = [];

		// prepare existing forms lists
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		$hidden = [];
		foreach($fd as $key => $row) {
			if (!PERMISSION::fullyapproved('formapproval', $row['approval']) || !PERMISSION::permissionIn($row['restricted_access'])) continue;
			if ($row['hidden'] || in_array($row['context'], array_keys(LANGUAGEFILE['formcontext']['notdisplayedinrecords']))) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $formdatalist) && !in_array($row['name'], $hidden)) {
				$formdatalist[] = $row['name'];
				$forms[$row['context']][$row['name']] = ['href' => "javascript:api.record('get', 'form', '" . $row['name'] . "')", 'data-filtered' => $row['id']];
				foreach(preg_split('/[^\w\d]/', $row['alias']) as $alias) $formdatalist[] = $alias;
			}
		}
		$return['render'] = [
			'content' => [
				[
					[
						'type' => 'datalist',
						'content' => array_values(array_unique($formdatalist)),
						'attributes' => [
							'id' => 'forms'
						]
					], [
						'type' => 'filtered',
						'attributes' => [
							'name' => LANG::GET('record.form_filter'),
							'list' => 'forms',
							'onkeypress' => "if (event.key === 'Enter') {api.record('get', 'formfilter', this.value); return false;}",
							'onblur' => "api.record('get', 'formfilter', this.value); return false;",
						],
						'hint' => LANG::GET('record.form_filter_hint')
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
			$return['render']['content'][] = 					[
				'type' => 'links',
				'description' => $contexttranslation,
				'content' => $list
			];

		}
		$this->response($return);
	}

	/**
	 *   ___     _ _                     _
	 *  |  _|_ _| | |___ _ _ ___ ___ ___| |_
	 *  |  _| | | | | -_|_'_| . | . |  _|  _|
	 *  |_| |___|_|_|___|_,_|  _|___|_| |_|
	 *                      |_|
	 */
	public function fullexport(){
		$this->export('full');
	}

	/**
	 *   _   _         _   _ ___ _
	 *  |_|_| |___ ___| |_|_|  _|_|___ ___
	 *  | | . | -_|   |  _| |  _| | -_|  _|
	 *  |_|___|___|_|_|_| |_|_| |_|___|_|
	 *
	 */
	public function identifier(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if ($content = UTILITY::propertySet($this->_payload, LANG::PROPERTY('record.create_identifier'))) {
					$possibledate = substr($content, -16);
					try {
						new DateTime($possibledate);
					}
					catch (Exception $e){
						$now = new DateTime('now', new DateTimeZone(INI['application']['timezone']));
						if ($this->_appendDate) $content .= ' ' . $now->format('Y-m-d H:i');
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
						'render' => $body
					]);
				}
				else $this->response(['response' => [
					'msg' => LANG::GET('record.create_identifier_error'),
					'type' => 'error'
				]]);
				break;
			case 'GET':
				$result=['render' =>
				[
					'form' => [
						'data-usecase' => 'record',
						'action' => "javascript:api.record('post', 'identifier', 'appendDate')"],
					'content'=>[
						[
							[
								'type' => 'textblock',
								'description' => LANG::GET('record.create_identifier_info')
							], [
								'type' => 'scanner',
								'hint' => LANG::GET('record.create_identifier_hint'),
								'attributes' => [
									'name' => LANG::GET('record.create_identifier'),
									'maxlength' => INI['limits']['identifier']
								]
							]
						]
					]
				]];
				$this->response($result);
				break;
		}
	}
	
	/**
	 *   _                   _
	 *  |_|_____ ___ ___ ___| |_
	 *  | |     | . | . |  _|  _|
	 *  |_|_|_|_|  _|___|_| |_|
	 *          |_|
	 */
	public function import(){
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_import', [
			'values' => [
				':identifier' => UTILITY::propertySet($this->_payload, 'IDENTIFY_BY_')
			]
		]);
		if ($data) {
			$result = [];
			foreach($data as $row)
				if (!PERMISSION::permissionIn($row['restricted_access'])) continue;
				foreach(json_decode($row['content'], true) as $key => $value) $result[$key] = $value;
				$result['DEFAULT_' . LANG::PROPERTY('record.record_type_description')] = $row['record_type'];

			$this->response([
				'data' => $result,
				'response' => [
					'msg' => LANG::GET('record.record_import_success'),
					'type' => 'success'
				]
			]);
		}
		else $this->response([
			'response' => [
				'msg' => LANG::GET('record.record_import_error'),
				'type' => 'error'
			]]);
	}
	
	/**
	 *   _     _           _                                 _
	 *  | |___| |_ ___ ___| |_ ___ ___ ___ ___ ___ _ _ ___ _| |___ ___ _____ ___
	 *  | | .'|  _| -_|_ -|  _| .'| . | . |  _| . | | | -_| . |   | .'|     | -_|
	 *  |_|__,|_| |___|___|_| |__,|  _|  _|_| |___|\_/|___|___|_|_|__,|_|_|_|___|
	 *                            |_| |_|
	 * returns the latest approved form, component by name from query
	 * @param string $query as defined within sqlinterface
	 * @param string $name
	 * @return array|bool either query row or false
	 */
	private function latestApprovedName($query = '', $name = ''){
		// get latest approved by name
		$element = [];
		$elements = SQLQUERY::EXECUTE($this->_pdo, $query, [
			'values' => [
				':name' => $name
			]
		]);
		foreach ($elements as $element){
			if (in_array($element['context'], ['bundle'])) return $element;
			if (PERMISSION::fullyapproved('formapproval', $element['approval'])) return $element;
		}
		return false;
	}

	/**
	 *             _       _   _             _ _
	 *   _____ ___| |_ ___| |_| |_ _ _ ___ _| | |___ ___
	 *  |     | .'|  _|  _|   | . | | |   | . | | -_|_ -|
	 *  |_|_|_|__,|_| |___|_|_|___|___|_|_|___|_|___|___|
	 *
	 */
	public function matchbundles(){
		$forms = [];
		$return = [];

		// prepare existing bundle lists
		$bundle = $this->latestApprovedName('form_bundle_get_by_name', $this->_requestedID);
		if(!$bundle) $bundle = ['content' => []];
		$necessaryforms = $bundle['content'] ? explode(',', $bundle['content']) : [];

		// unset hidden forms from bundle presets
		$allforms = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		foreach($allforms as $row){
			if (!PERMISSION::fullyapproved('formapproval', $row['approval']) || !PERMISSION::permissionIn($row['restricted_access'])) continue;
			if ($row['hidden'] && ($key = array_search($row['name'], $necessaryforms)) !== false) unset($necessaryforms[$key]);
		}
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_import', [
			'values' => [
				':identifier' => $this->_passedIdentify
			]
		]);
		$considered = [];
		foreach($data as $row){
			$considered[] = $row['form_name'];
		}
		foreach(array_diff($necessaryforms, $considered) as $needed){
			$forms[$needed] = ['href' => "javascript:api.record('get', 'form', '" . $needed . "', '" . $this->_passedIdentify . "')"];
		}

		if ($forms) $return['render'] = [
			[
				'type' => 'links',
				'description' => LANG::GET('record.record_append_missing_form'),
				'content' => $forms
			]
		];
		else $return['render'] =[
			[
				'type' => 'textblock',
				'content' => LANG::GET('record.record_append_missing_form_unneccessary'),
			]
		];
		$this->response($return);
	}

	/**
	 *                         _
	 *   ___ ___ ___ ___ ___ _| |
	 *  |  _| -_|  _| . |  _| . |
	 *  |_| |___|___|___|_| |___|
	 *
	 */
	public function record(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (array_intersect(['group'], $_SESSION['user']['permissions'])) $this->response([], 401);

				$context = $form_name = $form_id = null;
				$identifier = '';
				if ($context = UTILITY::propertySet($this->_payload, 'context')) unset($this->_payload->context);
				if ($form_name = UTILITY::propertySet($this->_payload, 'form_name')) unset($this->_payload->form_name);
				if ($form_id = UTILITY::propertySet($this->_payload, 'form_id')) unset($this->_payload->form_id);
				if ($entry_date = UTILITY::propertySet($this->_payload, 'DEFAULT_' . LANG::PROPERTY('record.record_date'))) unset($this->_payload->{'DEFAULT_' . LANG::PROPERTY('record.record_date')});
				if ($entry_time = UTILITY::propertySet($this->_payload, 'DEFAULT_' . LANG::PROPERTY('record.record_time'))) unset($this->_payload->{'DEFAULT_' . LANG::PROPERTY('record.record_time')});
				if ($record_type = UTILITY::propertySet($this->_payload, 'DEFAULT_' . LANG::PROPERTY('record.record_type_description'))) unset($this->_payload->{'DEFAULT_' . LANG::PROPERTY('record.record_type_description')});

				$entry_timestamp = $entry_date . ' ' . $entry_time;
				if (strlen($entry_timestamp) > 16) { // yyyy-mm-dd hh:ii
					$now = new DateTime('now', new DateTimeZone(INI['application']['timezone']));
					$entry_timestamp = $now->format('Y-m-d H:i');
				}

				foreach($this->_payload as $key => &$value){
					if (substr($key, 0, 12) === 'IDENTIFY_BY_'){
						$identifier = $value;
						unset ($this->_payload->$key);
						$possibledate = substr($identifier, -16);
						try {
							new DateTime($possibledate);
						}
						catch (Exception $e){
							$identifier .= ' ' . $entry_timestamp;
						}
					}
					if (gettype($value) === 'array') $value = trim(implode(' ', $value));
					/////////////////////////////////////////
					// BEHOLD! unsetting value==on relies on a prepared formdata/_payload having a dataset containing all selected checkboxes
					////////////////////////////////////////
					if (!$value || $value == 'on') unset($this->_payload->$key);
				}
				if (!$identifier) {
					$identifier = $form_name . ' ' . $entry_timestamp;
				}
				$entry_timestamp .= ':00'; // append seconds for database format

				if (!file_exists(UTILITY::directory('record_attachments'))) mkdir(UTILITY::directory('record_attachments'), 0777, true);
				$attachments = [];
				foreach ($_FILES as $fileinput => $files){
					if ($uploaded = UTILITY::storeUploadedFiles([$fileinput], UTILITY::directory('record_attachments'), [preg_replace('/[^\w\d]/m', '', $identifier . '_' . $this->_currentdate->format('YmdHis') . '_' . $fileinput)], null, false)){
						if (gettype($files['name']) === 'array'){
							for($i = 0; $i < count($files['name']); $i++){
								if (in_array(strtolower(pathinfo($uploaded[$i])['extension']), ['jpg', 'jpeg', 'gif', 'png'])) UTILITY::resizeImage($uploaded[$i], INI['limits']['record_image'], UTILITY_IMAGE_REPLACE);

								if (array_key_exists($fileinput, $attachments)) $attachments[$fileinput][]= substr($uploaded[$i], 1);
								else $attachments[$fileinput] = [substr($uploaded[$i], 1)];
							}
						}
						else {
							if (in_array(strtolower(pathinfo($uploaded[0])['extension']), ['jpg', 'jpeg', 'gif', 'png'])) UTILITY::resizeImage($uploaded[0], INI['limits']['record_image'], UTILITY_IMAGE_REPLACE);
							$attachments[$fileinput] = [substr($uploaded[0], 1)];
						}
					}
				}
				foreach($attachments as $input => $files){
					$this->_payload->$input = implode(', ', $files);
				}
				if (boolval((array) $this->_payload) && SQLQUERY::EXECUTE($this->_pdo, 'records_post', [
					'values' => [
						':context' => $context,
						':form_name' => $form_name,
						':form_id' => $form_id,
						':identifier' => $identifier,
						':author' => $_SESSION['user']['name'],
						':author_id' => $_SESSION['user']['id'],
						':content' => json_encode($this->_payload),
						':entry_timestamp' => $entry_timestamp,
						':record_type' => $record_type ? : null
					]
				])) $this->response([
					'response' => [
						'msg' => LANG::GET('record.record_saved'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => LANG::GET('record.record_error'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$return = ['render' => []];
				$body = [];
				// summarize content
				$content = $this->summarizeRecord('full', PERMISSION::permissionFor('recordsretyping'));
				$body[] = [
					[
						'type' => 'textblock',
						'description' => LANG::GET('record.create_identifier'),
						'content' => $this->_requestedID
					], [
						'type' => 'button',
						'attributes' => [
							'value' => LANG::GET('menu.record_create_identifier'),
							'onpointerup' => "_client.application.postLabelSheet('" . $this->_requestedID . "')"
						]
					]
				];
				foreach($content['content'] as $form => $entries){
					$body[] = [
						[
							'type' => 'textblock',
							'description' => $form
						]
					];
					foreach($entries as $key => $value){
						array_push($body[count($body) -1],
							[
								'type' => 'textblock',
								'description' => $key,
								'linkedcontent' => $value
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
					if ($form != LANG::GET('record.record_retype_pseudoform_name')){
						array_push($body[count($body) -1],[
							'type' => 'button',
							'attributes' => [
								'value' => LANG::GET('record.record_form_export'),
								'onpointerup' => "api.record('get', 'formexport', '" . $this->_requestedID . "', '" . $form . "')"
							]
						]);
					}
				}
		
				$return['render']['content'] = $body;

				if (!array_intersect(['group'], $_SESSION['user']['permissions'])){
					// simple groups are not allowed to append to form
					$bundles = ['...' . LANG::GET('record.record_match_bundles_default') => ['value' => '0']];
					// match against bundles
					// prepare existing bundle lists
					$bd = SQLQUERY::EXECUTE($this->_pdo, 'form_bundle_datalist');
					$hidden = [];
					foreach($bd as $key => $row) {
						if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
						if (!in_array($row['name'], $bundles) && !in_array($row['name'], $hidden)) {
							$bundles[$row['name']] = ['value' => $row['name']];
						}
					}

					$return['render']['content'][] = [
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
					
					$content['closed'] = $content['closed'] ? json_decode($content['closed'], true) : [];
					$approvalposition = [];
					foreach ($content['closed'] as $role => $property){
						array_unshift($return['render']['content'][count($return['render']['content']) - 1], [
							'type' => 'textblock',
							'description' => LANG::GET('record.record_closed', [':role' => LANG::GET('permissions.' . $role), ':name' => $property['name'], ':date' => $property['date']])
						]);
					}

					if ($content['complaint'] && PERMISSION::permissionFor('complaintclosing')){
						foreach(PERMISSION::pending('complaintclosing', $content['closed']) as $position){
							$approvalposition[LANG::GET('permissions.' . $position)] = [
								'value' => $position,
								'onchange' => "if (this.checked) new Dialog({type: 'confirm', header: '". LANG::GET('record.record_mark_as_closed') ." ' + this.name, render: '" . LANG::GET('record.record_complaint_mark_as_closed_info') . "', options:{".
								"'" . LANG::GET('general.cancel_button') . "': false,".
								"'" . LANG::GET('record.record_mark_as_closed') . ' ' . LANG::GET('permissions.' . $position) ."': {value: true, class: 'reducedCTA'},".
								"}}).then(confirmation => {if (confirmation) {this.disabled = true; api.record('put', 'close', '" . $this->_requestedID . "', this.value);} else this.checked = false;})"
							];
						}
					}
					elseif (!$content['closed'] && PERMISSION::permissionFor('recordsclosing')) {
						foreach(PERMISSION::pending('recordsclosing', $content['closed']) as $position){
							$approvalposition[LANG::GET('permissions.' . $position)] = [
								'value' => $position,
								'onchange' => "if (this.checked) new Dialog({type: 'confirm', header: '". LANG::GET('record.record_mark_as_closed') ." ' + this.name, render: '" . LANG::GET('record.record_mark_as_closed_info') . "', options:{".
								"'" . LANG::GET('general.cancel_button') . "': false,".
								"'" . LANG::GET('record.record_mark_as_closed') . ' ' . LANG::GET('permissions.' . $position) . "': {value: true, class: 'reducedCTA'},".
								"}}).then(confirmation => {if (confirmation) {this.disabled = true; api.record('put', 'close', '" . $this->_requestedID . "', this.value);} else this.checked = false;})"
							];
						}
					}
					if ($approvalposition){
						array_unshift($return['render']['content'][count($return['render']['content']) - 1], [
							'type' => 'checkbox',
							'content' => $approvalposition,
							'description' => LANG::GET('record.record_mark_as_closed')
						]);
					}
				}
				$this->response($return);
				break;
			default:
				$this->response([], 401);
		}
	}
	
	/**
	 *                         _
	 *   ___ ___ ___ ___ ___ _| |___
	 *  |  _| -_|  _| . |  _| . |_ -|
	 *  |_| |___|___|___|_| |___|___|
	 *
	 */
	public function records(){
		$return = ['render' => ['content' => []]];
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_identifiers');
		if (!$data) {
			$result['render']['content'] = $this->noContentAvailable(LANG::GET('message.no_messages'));
			$this->response($result);		
		}
		$recorddatalist = $contexts = [];

		// sort records to user units, others and these that can not be assigned due to deleted user ids
		$unassigned = [];
		$targets = array_keys(LANGUAGEFILE['record']['record_list']); // ['units', 'other', 'unassigned']
		foreach($data as $row){
			if ($this->_requestedID){
				similar_text($this->_requestedID, $row['identifier'], $percent);
				if ($percent < INI['likeliness']['records_search_similarity']) continue;
			}
			if (!in_array($row['identifier'], $recorddatalist)) $recorddatalist[] = $row['identifier'];
			if ($row['units']){
				if (array_intersect(explode(',', $row['units']), $_SESSION['user']['units'])) $target = 0;
				else $target = 1;
			} else $target = 2;
			foreach(LANGUAGEFILE['formcontext'] as $key => $subkeys){
				if (in_array($row['context'], array_keys($subkeys))) $row['context'] = $key . '.' . $row['context'];
			}
			if (!array_key_exists($row['context'], $contexts)) $contexts[$row['context']] = ['units' => [], 'other' => [], 'unassigned' => []];
			$touched = SQLQUERY::EXECUTE($this->_pdo, 'records_touched', [
				'values' => [
					':id' => $row['id']
					]
				]);
			$touched = $touched ? $touched[0] : '';
			$closed = json_decode($touched['closed'] ? : '', true);
			if (!$this->_requestedID && (($row['complaint'] && PERMISSION::fullyapproved('complaintclosing', $closed))
				|| (!$row['complaint'] && $closed)
				|| count($contexts[$row['context']][$targets[$target]]) > INI['limits']['max_records'])) {
				continue;
			}
			if ($touched['form_name'] === 'recordretype') $touched['form_name'] = LANG::GET('record.record_retype_pseudoform_name');
			$linkdisplay = LANG::GET('record.record_list_touched', [
				':identifier' => $row['identifier'],
				':date' => substr($touched['date'], 0, -3),
				':form' => $touched['form_name']
				]) . ($row['complaint'] ? ' *' : '');
			$contexts[$row['context']][$targets[$target]][$linkdisplay] = ['href' => "javascript:api.record('get', 'record', '" . $row['identifier'] . "')"];
		}
		// delete double entries
		foreach($contexts as &$context){
			$previouslydeleted = null;
			foreach ($context['unassigned'] as $identifier => $attributes){
				if ($previouslydeleted) {
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
					'content' => array_values(array_unique($recorddatalist)),
					'attributes' => [
						'id' => 'records'
					]
				], [
					'type' => 'scanner',
					'destination' => 'recordfilter',
					'description' => LANG::GET('record.record_scan')
				], [
					'type' => 'filtered',
					'hint' => LANG::GET('record.record_filter_hint', [':max' => INI['limits']['max_records']]),
					'attributes' => [
						'id' => 'recordfilter',
						'name' => LANG::GET('record.record_filter'),
						'list' => 'records',
						'onkeypress' => "if (event.key === 'Enter') {api.record('get', 'records', this.value); return false;}",
						'onblur' => "api.record('get', 'records', this.value); return false;",
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

		$result['render']['content'] = $content;
		$this->response($result);		
	}

	/**
	 *           _
	 *   ___ ___| |_ _ _ ___ ___
	 *  |  _| -_|  _| | | . | -_|
	 *  |_| |___|_| |_  |  _|___|
	 *              |___|_|
	 */
	public function retype(){
		if (!PERMISSION::permissionFor('recordsretyping')) $this->response([], 401);
		$entry_id = UTILITY::propertySet($this->_payload, 'entry_id');
		$record_type = UTILITY::propertySet($this->_payload, 'DEFAULT_' . LANG::PROPERTY('record.record_type_description'));

		$original = SQLQUERY::EXECUTE($this->_pdo, 'records_unique', [
			'values' => [
				':id' => $entry_id
			]
		]);
		$original = $original ? $original[0] : null;
		if ($original && $record_type && SQLQUERY::EXECUTE($this->_pdo, 'records_retype', [
			'values' => [
				':id' => $entry_id,
				':record_type' => $record_type
			]
		]) && SQLQUERY::EXECUTE($this->_pdo, 'records_post', [
			'values' => [
				':context' => $original['context'],
				':form_name' => 'recordretype',
				':form_id' => 0,
				':identifier' => $original['identifier'],
				':author' => $_SESSION['user']['name'],
				':author_id' => $_SESSION['user']['id'],
				':content' => json_encode([
					LANG::GET('record.record_retype_pseudoform_name') => LANG::GET('record.record_retype_content', [
						':author' => $original['author'],
						':form' => $original['form_name'],
						':date' => $original['date'],
						':previoustype' => $original['record_type'],
						':newtype' => $record_type
					])
				]),
				':entry_timestamp' => $this->_currentdate->format('Y-m-d H:i:s'),
				':record_type' => $record_type
		]])) $this->response([
			'response' => [
				'msg' => LANG::GET('record.record_saved'),
				'type' => 'success'
			]]);
		else $this->response([
			'response' => [
				'msg' => LANG::GET('record.record_error'),
				'type' => 'error'
			]]);
	}

	/**
	 *       _           _ _ ___ _       _                     _
	 *   ___|_|_____ ___| |_|  _|_|___ _| |___ _ _ ___ ___ ___| |_
	 *  |_ -| |     | . | | |  _| | -_| . | -_|_'_| . | . |  _|  _|
	 *  |___|_|_|_|_|  _|_|_|_| |_|___|___|___|_,_|  _|___|_| |_|
	 *              |_|                           |_|
	 */
	public function simplifiedexport(){
		$this->export('simplified');
	}

	/**
	 *                               _                               _
	 *   ___ _ _ _____ _____ ___ ___|_|___ ___ ___ ___ ___ ___ ___ _| |
	 *  |_ -| | |     |     | .'|  _| |- _| -_|  _| -_|  _| . |  _| . |
	 *  |___|___|_|_|_|_|_|_|__,|_| |_|___|___|_| |___|___|___|_| |___|
	 *
	 * @param str $type full, simplified, form
	 * @param bool $retype based on view and permission link to retype or not
	 * @return array $summary
	 */

	private function summarizeRecord($type = 'full', $retype = false){
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_import', [
			'values' => [
				':identifier' => $this->_requestedID
			]
		]);
		$summary = [
			'filename' => preg_replace('/' . INI['forbidden']['names'][0] . '/', '', $this->_requestedID . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => $this->_requestedID,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('menu.record_summary'),
			'date' => $this->_currentdate->format('y-m-d H:i'),
			'closed' => false,
			'complaint' => false
		];
		$accumulatedcontent = [];
		foreach ($data as $row){
			if (!PERMISSION::permissionIn($row['restricted_access'])) continue;
			$summary['closed'] = $row['closed']; // last row decides
			if ($row['record_type'] === 'complaint') $summary['complaint'] = true; // does record contain any complaints?
			if ($row['form_id'] == 0) { // retype autoform
				if ($type === 'simplified') continue;
				$form = LANG::GET('record.record_retype_pseudoform_name');
			}
			else $form = LANG::GET('record.record_export_form', [':form' => $row['form_name'], ':date' => substr($row['form_date'], 0, -3)]);
			if (!array_key_exists($form, $accumulatedcontent)) $accumulatedcontent[$form] = [];

			$content = json_decode($row['content'], true);
			foreach($content as $key => $value){
				$key = str_replace('_', ' ', $key);
				if (!array_key_exists($key, $accumulatedcontent[$form])) $accumulatedcontent[$form][$key] = [['value' => $value, 'author' => LANG::GET('record.record_export_author', [':author' => $row['author'], ':date' => substr($row['date'], 0, -3)]), 'type' => $row['record_type'], 'id' => $row['id']]];
				else $accumulatedcontent[$form][$key][] = ['value' => $value, 'author' => LANG::GET('record.record_export_author', [':author' => $row['author'], ':date' => substr($row['date'], 0, -3)]), 'type' => $row['record_type'], 'id' => $row['id']];
			}
		}

		if ($type === 'form') {
			foreach(array_keys($accumulatedcontent) as $key) if ($key !== $this->_formExport) unset($accumulatedcontent[$key]);
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
								$addendum = '';
								if (in_array($entry['type'], ['complaint', 'rework'])) {
									if ($retype) {
										$options = [];
										foreach (LANGUAGEFILE['record']['record_type'] as $record_type => $description){
											$options[$description] = ['value' => $record_type];
										}						
										$addendum = " <a href=\"javascript:void(0);\" onpointerup=\"new Dialog({type: 'input', header: '". LANG::GET('record.record_retype_header', [':type' => LANGUAGEFILE['record']['record_type'][$entry['type']]]) . " ', render: JSON.parse('" . json_encode(
											[[
												'type' => 'radio',
												'attributes' => [
													'name' => 'DEFAULT_' . LANG::GET('record.record_type_description')
												],
												'content' => $options
											], [
												'type' => 'hidden',
												'attributes' => [
													'name' => 'entry_id',
													'value' => $entry['id']
												]
											]]
										) . "'), options:{".
										"'" . LANG::GET('general.cancel_button') . "': false,".
										"'" . LANG::GET('general.ok_button')  . "': {value: true, class: 'reducedCTA'},".
										"}}).then(response => { if (response) api.record('post','retype', null, _client.application.dialogToFormdata(response))})"
										. "\">" . LANG::GET('record.record_export_' . $entry['type'] ) . '</a>';
									}
									else $addendum = ' ' . LANG::GET('record.record_export_'. $entry['type']);
								}
								$summary['content'][$form][$key] .= $displayvalue . ' (' . $entry['author'] . $addendum . ")\n";
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

$api = new RECORD();
$api->processApi();

exit;
?>
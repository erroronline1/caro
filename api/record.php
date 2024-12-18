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
	private $_documentExport = null;
	private $_caseState = null;
	private $_unit = null;
	private $_caseStateBoolean = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedID = $this->_appendDate = isset(REQUEST[2]) ? REQUEST[2] : null;
		$this->_passedIdentify = $this->_documentExport = $this->_caseState = $this->_unit = isset(REQUEST[3]) ? REQUEST[3] : '';
		$this->_caseStateBoolean = isset(REQUEST[4]) ? REQUEST[4] : null;
	}


	/**
	 *                       _       _       
	 *   ___ ___ ___ ___ ___| |_ ___| |_ ___ 
	 *  |  _| .'|_ -| -_|_ -|  _| .'|  _| -_|
	 *  |___|__,|___|___|___|_| |__,|_| |___|
	 *
	 */
	public function casestate($context = null, $type = 'checkbox', $action = [], $checked = []){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				if (!PERMISSION::permissionFor('recordscasestate') || array_intersect(['group'], $_SESSION['user']['permissions'])) $this->response([], 401);

				$case = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
					'values' => [
						':identifier' => $this->_requestedID
					]
				]);
				$case = $case ? $case[0] : null;
				if ($case){
					$current_record = [
						'author' => $_SESSION['user']['name'],
						'date' => $this->_currentdate->format('Y-m-d H:i:s'),
						'document' => 0,
						'content' => json_encode([
							LANG::GET('record.record_pseudodocument_' . $case['context'], [], true) => LANG::GET($this->_caseStateBoolean === 'true' ? 'record.record_casestate_set' : 'record.record_casestate_revoked', [':casestate' => LANG::GET('casestate.' . $case['context'] . '.' . $this->_caseState, [], true)], true)
						])
					];
					$records = json_decode($case['content'], true);
					$records[] = $current_record;
					$case_state = json_decode($case['case_state'] ? : '', true);
					if ($this->_caseStateBoolean === 'true') $case_state[$this->_caseState] = true;
					else unset($case_state[$this->_caseState]);
					if (SQLQUERY::EXECUTE($this->_pdo, 'records_put', [
						'values' => [
							':case_state' => json_encode($case_state) ? : null,
							':record_type' => $case['record_type'] ? : null,
							':identifier' => $this->_requestedID,
							':last_user' => $_SESSION['user']['id'],
							':last_document' => null,
							':content' => json_encode($records),
							':id' => $case['id']
						]
					])) $this->response([
						'response' => [
							'msg' => LANG::GET($this->_caseStateBoolean === 'true' ? 'record.record_casestate_set' : 'record.record_casestate_revoked', [':casestate' => LANGUAGEFILE['casestate'][$case['context']][$this->_caseState]]),
							'type' => 'success'
						]]);
				}
				$this->response([
					'response' => [
						'msg' => LANG::GET('record.record_error'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				if (!isset(LANGUAGEFILE['casestate'][$context])) return;
				$content = [];
				$checked = json_decode($checked ? : '', true);
				if ($type === 'radio'){
					$content[LANG::GET('record.record_casestate_filter_all')] = ['onchange' => "_client.record.casestatefilter(undefined)"];
				}
				foreach(LANGUAGEFILE['casestate'][$context] as $state => $translation){
					$content[$translation] = $action;
					$content[$translation]['data-casestate'] = $state;
					if (isset($checked[$state])) $content[$translation]['checked'] = true;
					if($type === 'radio' && isset($_SESSION['user']['app_settings']['primaryRecordState']) && $state === $_SESSION['user']['app_settings']['primaryRecordState']) $content[$translation]['checked'] = true;
					if (!PERMISSION::permissionFor('recordscasestate') && $type === 'checkbox') $content[$translation]['disabled'] = true;
				}
				return [
					'type' => $type,
					'attributes' => [
						'name' => LANG::GET('record.record_pseudodocument_' . $context)
					],
					'content' => $content
				];
		}
	}

	/**
	 *                       _       _           _         _   
	 *   ___ ___ ___ ___ ___| |_ ___| |_ ___ ___| |___ ___| |_ 
	 *  |  _| .'|_ -| -_|_ -|  _| .'|  _| -_| .'| | -_|  _|  _|
	 *  |___|__,|___|___|___|_| |__,|_| |___|__,|_|___|_| |_|  
	 *
	 */
	public function casestatealert(){
		if ($identifier = UTILITY::propertySet($this->_payload, 'identifier')) unset($this->_payload->identifier);
		if ($context = UTILITY::propertySet($this->_payload, 'context')) unset ($this->_payload->context);
		if ($casestate = UTILITY::propertySet($this->_payload, 'casestate')) unset ($this->_payload->casestate);
		if ($casestatestate = UTILITY::propertySet($this->_payload, 'casestatestate')) unset ($this->_payload->casestatestate);
		if ($recipient = UTILITY::propertySet($this->_payload, LANG::PROPERTY('record.record_casestate_change_recipient'))) unset ($this->_payload->{LANG::PROPERTY('record.record_casestate_change_recipient')});
		$recipient = preg_split('/[,;]\s{0,}/', $recipient ? : '');
		// remainder of payload are checked units and maybe supervisor_only flag
		$permission = [];
		if (UTILITY::propertySet($this->_payload, LANG::PROPERTY('record.record_casestate_change_recipient_supervisor_only', [':supervisor' => LANG::PROPERTY('permissions.supervisor')]))) {
			$permission = ['supervisor'];
			unset($this->_payload->{LANG::PROPERTY('record.record_casestate_change_recipient_supervisor_only', [':supervisor' => LANG::PROPERTY('permissions.supervisor')])});
		}
		$message = LANG::GET('record.record_casestate_change_message_content', [
			':user' => $_SESSION['user']['name'],
			':identifier' => '<a href="javascript:void(0);" onpointerup="api.record(\'get\', \'record\', \'' . $identifier . '\')"> ' . $identifier . '</a>',
			':casestate' => LANG::GET($casestatestate === 'true' ? 'record.record_casestate_set' : 'record.record_casestate_revoked', [':casestate' => LANG::GET('casestate.' . $context . '.' . $casestate, [], true)], true)
		], true);

		if ((array_values((array)$this->_payload) || $recipient) && $this->alertUserGroup(['permission' => $permission, 'unit' => array_values((array)$this->_payload), 'user' => $recipient], $message)) $this->response([
			'response' => [
				'msg' => LANG::GET('message.send_success'),
				'type' => 'success'
			]]);
		else $this->response([
			'response' => [
				'msg' => LANG::GET('message.send_failure', [':number' => '']),
				'type' => 'error'
			]]);
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
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
			'values' => [
				':identifier' => $this->_requestedID
			]
		]);
		$data = $data ? $data[count($data) - 1] : []; // most recent entry suffices 
		if (!$data) $this->response([], 204);
		$data['closed'] = $data['closed'] ? json_decode($data['closed'], true) : [];

		$data['closed'][$this->_passedIdentify] = [
			'name' => $_SESSION['user']['name'],
			'date' => $this->_currentdate->format('Y-m-d H:i')
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
	 *     _                           _   
	 *   _| |___ ___ _ _ _____ ___ ___| |_ 
	 *  | . | . |  _| | |     | -_|   |  _|
	 *  |___|___|___|___|_|_|_|___|_|_|_|  
	 * 
	 */
	public function document(){
		// prepare existing documents lists
		$document = $this->latestApprovedName('document_document_get_by_name', $this->_requestedID);
		if (!$document || $document['hidden'] || !PERMISSION::permissionIn($document['restricted_access'])) $this->response(['response' => ['msg' => LANG::GET('assemble.error_document_not_found', [':name' => $this->_requestedID]), 'type' => 'error']]);

		$return = ['title'=> $document['name'], 'render' => [
			'content' => []
		]];

		// prefill identify if passed, prepare calendar button if part of the document
		$calendar = new CALENDARUTILITY($this->_pdo);
		function setidentifier($element, $identify, $calendar){
			$content = [];
			foreach($element as $subs){
				if (!isset($subs['type'])){
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
					if (in_array($subs['type'], [
							'checkbox',
							'date',
							'email',
							'number',
							'productselection',
							'radio',
							'range',
							'scanner',
							'select',
							'tel',
							'text',
							'textarea',
							'time',
							])){
						$subs['attributes']['data-loss'] = 'prevent';
					}
					$content[] = $subs;
				}
			}
			return $content;
		};
		$has_components = false;
		foreach(explode(',', $document['content']) as $usedcomponent) {
			$component = $this->latestApprovedName('document_component_get_by_name', $usedcomponent);
			if ($component){
				$has_components = true;
				$component['content'] = json_decode($component['content'], true);
				array_push($return['render']['content'], ...setidentifier($component['content']['content'], $this->_passedIdentify, $calendar));
			}
		}
		if (!$has_components) array_push($return['render']['content'], [[
			'type' => 'textsection',
			'attributes' => [
				'class' => 'orange',
				'name' => LANG::GET('assemble.error_no_approved_components', [':permission' => implode(', ', array_map(fn($v) => LANGUAGEFILE['permissions'][$v], PERMISSION::permissionFor('documentcomposer', true)))])
			]
		]]);

		// check if a submit button is applicable
		function saveable($element){
			$saveable = false;
			foreach($element as $subs){
				if (!isset($subs['type'])){
					if ($saveable = saveable($subs)) return true;
				}
				else {
					if (!in_array($subs['type'], ['textsection', 'image', 'links', 'hidden', 'button'])) return true;
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
					'name' => '_context',
					'value' => $document['context']
				]
			], [
				'type' => 'hidden',
				'attributes' => [
					'name' => '_document_name',
					'value' => $document['name']
				]
			], [
				'type' => 'hidden',
				'attributes' => [
					'name' => '_document_id',
					'value' => $document['id']
				]
			]
		];

		if (isset($return['render']['form'])) {
			$defaults = [
				[
					'type' => 'date',
					'attributes' => [
						'name' => 'DEFAULT_' . LANG::GET('record.record_date'),
						'value' => $this->_currentdate->format('Y-m-d'),
						'required' => true
					]
				], [
					'type' => 'time',
					'attributes' => [
						'name' => 'DEFAULT_' . LANG::GET('record.record_time'),
						'value' => $this->_currentdate->format('H:i'),
						'required' => true
					]
				]
			];
			if (in_array($document['context'], ['casedocumentation'])) {
				$options = [];
				foreach (LANGUAGEFILE['record']['record_type'] as $key => $value){
					$options[$value] = boolval(CONFIG['application']['require_record_type_selection']) ? ['value' => $key, 'required' => true] : ['value' => $key];
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

		if (PERMISSION::permissionFor('documentexport') || $document['permitted_export']){
			$return['render']['content'][] = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => LANG::GET('assemble.required_asterisk')
					]
				],
				[
					'type' => 'button',
					'hint' => LANG::GET('assemble.document_export_hint'),
					'attributes' => [
						'type' => 'submit',
						'formnovalidate' => true,
						'value' => LANG::GET('assemble.document_export'),
						'formaction' => "javascript:api.document('post', 'export')"
					]
				]
			];
		}
		else {
			$return['render']['content'][] = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => LANG::GET('assemble.document_export_permission', [':permissions' => implode(', ', array_map(fn($v) => LANGUAGEFILE['permissions'][$v], PERMISSION::permissionFor('documentexport', true)))])
					]
				]
			];
		}
		if (isset($return['render']['content'][0][0]['type'])) array_push($return['render']['content'][0], ...$context);
		else array_push($return['render']['content'][0][0], ...$context);
		$this->response($return);
	}
	
	/**
	 *                       _
	 *   ___ _ _ ___ ___ ___| |_
	 *  | -_|_'_| . | . |  _|  _|
	 *  |___|_,_|  _|___|_| |_|
	 *          |_|
	 */
	private function export($summarize = "full", $export = false){
		if (!PERMISSION::permissionFor('recordsexport')) $this->response([], 401);
		$content = $this->summarizeRecord($summarize, false, $export);
		if (!$content) $this->response([], 404);
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
	 *   ___                                   _
	 *  |  _|___ ___ _____ ___ _ _ ___ ___ ___| |_
	 *  |  _| . |  _|     | -_|_'_| . | . |  _|  _|
	 *  |_| |___|_| |_|_|_|___|_,_|  _|___|_| |_|
	 *                            |_|
	 */
	public function documentexport(){
		$this->export('document');
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
						if ($this->_appendDate) $content .= ' ' . $this->_currentdate->format('Y-m-d H:i');
					}
				}
				if ($content){
					$downloadfiles = [];
					$downloadfiles[LANG::GET('record.create_identifier')] = [
						'href' => PDF::identifierPDF($content, UTILITY::propertySet($this->_payload, '_type') ? : 'A4')
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
				$result = ['render' =>
				[
					'content'=>[
						[
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => LANG::GET('record.create_identifier_info')
								]
							], [
								'type' => 'scanner',
								'hint' => LANG::GET('record.create_identifier_hint'),
								'attributes' => [
									'name' => LANG::GET('record.create_identifier'),
									'maxlength' => CONFIG['limits']['identifier'],
									'id' => '_identifier'
								]
							]
						]
					]
				]];
				foreach(CONFIG['label'] as $type => $setting){
					$result['render']['content'][] = [
						'type' => 'button',
						'attributes' => [
							'type' => 'button',
							'onpointerup' => "_client.application.postLabelSheet(document.getElementById('_identifier').value, 'appendDate', {_type:'" . $type . "'});",
							'value' => LANG::GET('record.create_identifier_type', [':format' => $setting['format']])
						]
					];
				}
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
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
			'values' => [
				':identifier' => UTILITY::propertySet($this->_payload, 'IDENTIFY_BY_')
			]
		]);
		$data = $data ? $data[0] : null;

		if ($data) {
			$result = [];
			$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');

			$records = json_decode($data['content'], true);
			foreach($records as $record){
				$document = $documents[array_search($record['document'], array_column($documents, 'id'))] ? : ['name' => null, 'restricted_access' => null];
				if (!PERMISSION::permissionIn($document['restricted_access'])) continue;
				if ($record['document'] == 0) continue;
				if (gettype($record['content']) === 'string') $record['content'] = json_decode($record['content'], true);
				foreach($record['content'] as $key => $value){
					$result[$key] = $value;
				}
				$result['DEFAULT_' . LANG::PROPERTY('record.record_type_description')] = $data['record_type'];
			} 
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
	 * returns the latest approved document, component by name from query
	 * @param string $query as defined within sqlinterface
	 * @param string $name
	 * @return array|bool either query row or false
	 */
	private function latestApprovedName($query = '', $name = '', $requestedTimestamp = null){
		$requestedTimestamp = $requestedTimestamp ? : $this->_currentdate->format('Y-m-d') . ' ' . $this->_currentdate->format('H:i:59');

		// get latest approved by name
		$element = [];
		$elements = SQLQUERY::EXECUTE($this->_pdo, $query, [
			'values' => [
				':name' => $name
			]
		]);
		foreach ($elements as $element){
			if (!$element['hidden'] && in_array($element['context'], ['bundle'])) return $element;
			if (PERMISSION::fullyapproved('documentapproval', $element['approval']) && 
				PERMISSION::permissionIn($element['restricted_access']) && 
				$element['date'] <= $requestedTimestamp) {
					$element['hidden'] = json_decode($element['hidden'] ? : '', true); 
					if(!$element['hidden'] || $element['hidden']['date'] > $requestedTimestamp) return $element;
					else return false;
				}
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
		$documents = [];
		$return = [];

		// prepare existing bundle lists
		$bundle = $this->latestApprovedName('document_bundle_get_by_name', $this->_requestedID);
		if(!$bundle) $bundle = ['content' => []];
		$necessarydocuments = $bundle['content'] ? explode(',', $bundle['content']) : [];

		// unset hidden documents from bundle presets
		$alldocuments = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		foreach($alldocuments as $row){
			if (!PERMISSION::fullyapproved('documentapproval', $row['approval']) || !PERMISSION::permissionIn($row['restricted_access'])) continue;
			if ($row['hidden'] && ($key = array_search($row['name'], $necessarydocuments)) !== false) unset($necessarydocuments[$key]);
		}
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
			'values' => [
				':identifier' => $this->_passedIdentify
			]
		]);
		$considered = [];
		foreach($data as $row){
			foreach (json_decode($row['content'], true) as $record){
				if (($documentIndex = array_search($record['document'], array_column($alldocuments, 'id'))) !== false)
					$considered[] = $alldocuments[$documentIndex]['name'];
			}
		}
		foreach(array_diff($necessarydocuments, $considered) as $needed){
			$documents[$needed] = ['href' => "javascript:api.record('get', 'document', '" . $needed . "', '" . $this->_passedIdentify . "')"];
		}

		if ($documents) $return['render'] = [
			[
				'type' => 'links',
				'description' => LANG::GET('record.record_append_missing_document'),
				'content' => $documents
			]
		];
		else $return['render'] =[
			[
				'type' => 'textsection',
				'content' => LANG::GET('record.record_append_missing_document_unneccessary'),
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

				$context = $document_name = null;
				$identifier = '';
				if ($context = UTILITY::propertySet($this->_payload, '_context')) unset($this->_payload->_context);
				if ($document_name = UTILITY::propertySet($this->_payload, '_document_name')) unset($this->_payload->_document_name);
				// document id is stored to the entry that the content remains hidden if the document has restricted access
				// used in summarizeRecord() and not easier to check with the name 
				if ($document_id = UTILITY::propertySet($this->_payload, '_document_id')) unset($this->_payload->_document_id);
				if ($entry_date = UTILITY::propertySet($this->_payload, 'DEFAULT_' . LANG::PROPERTY('record.record_date'))) unset($this->_payload->{'DEFAULT_' . LANG::PROPERTY('record.record_date')});
				if ($entry_time = UTILITY::propertySet($this->_payload, 'DEFAULT_' . LANG::PROPERTY('record.record_time'))) unset($this->_payload->{'DEFAULT_' . LANG::PROPERTY('record.record_time')});
				if ($record_type = UTILITY::propertySet($this->_payload, 'DEFAULT_' . LANG::PROPERTY('record.record_type_description'))) unset($this->_payload->{'DEFAULT_' . LANG::PROPERTY('record.record_type_description')});

				$entry_timestamp = $entry_date . ' ' . $entry_time;
				if (strlen($entry_timestamp) > 16) { // yyyy-mm-dd hh:ii
					$entry_timestamp = $this->_currentdate->format('Y-m-d H:i');
				}

				foreach($this->_payload as $key => &$value){
					if (substr($key, 0, 12) === 'IDENTIFY_BY_'){
						$identifier = $value;
						if (gettype($identifier) !== 'string') $identifier = ''; // empty value is passed as array by frontend
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
					if ($value === "...") unset($this->_payload->$key); // e.g. empty selections
				}
				if (!$identifier) {
					if (!in_array($context, array_keys(LANGUAGEFILE['documentcontext']['identify']))) $identifier = $document_name . ' ' . $entry_timestamp;
					else $this->response([
						'response' => [
							'msg' => LANG::GET('record.record_error'),
							'type' => 'error'
						]]);
				}
				$entry_timestamp .= ':00'; // append seconds for database format

				if (!file_exists(UTILITY::directory('record_attachments'))) mkdir(UTILITY::directory('record_attachments'), 0777, true);
				$attachments = [];
				foreach ($_FILES as $fileinput => $files){
					if ($uploaded = UTILITY::storeUploadedFiles([$fileinput], UTILITY::directory('record_attachments'), [preg_replace('/[^\w\d]/m', '', $identifier . '_' . $this->_currentdate->format('YmdHis') . '_' . $fileinput)], null, false)){
						if (gettype($files['name']) === 'array'){
							for($i = 0; $i < count($files['name']); $i++){
								if (in_array(strtolower(pathinfo($uploaded[$i])['extension']), ['jpg', 'jpeg', 'gif', 'png'])) UTILITY::alterImage($uploaded[$i], CONFIG['limits']['record_image'], UTILITY_IMAGE_REPLACE);

								if (isset($attachments[$fileinput])) $attachments[$fileinput][]= substr($uploaded[$i], 1);
								else $attachments[$fileinput] = [substr($uploaded[$i], 1)];
							}
						}
						else {
							if (in_array(strtolower(pathinfo($uploaded[0])['extension']), ['jpg', 'jpeg', 'gif', 'png'])) UTILITY::alterImage($uploaded[0], CONFIG['limits']['record_image'], UTILITY_IMAGE_REPLACE);
							$attachments[$fileinput] = [substr($uploaded[0], 1)];
						}
					}
				}
				foreach($attachments as $input => $files){
					$this->_payload->$input = implode(', ', $files);
				}

				$current_record = [
					'author' => $_SESSION['user']['name'],
					'date' => $entry_timestamp,
					'document' => $document_id,
					'content' => json_encode($this->_payload)
				];
				if (boolval((array) $this->_payload)){
					$case = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
						'values' => [
							':identifier' => $identifier
						]
					]);
					$case = $case ? $case[0] : null;
					if ($case){
						$records = json_decode($case['content'], true);
						$records[] = $current_record;
						$success = SQLQUERY::EXECUTE($this->_pdo, 'records_put', [
							'values' => [
								':case_state' => $case['case_state'] ? : null,
								':record_type' => $case['record_type'] ? : null,
								':identifier' => $identifier,
								':last_user' => $_SESSION['user']['id'],
								':last_document' => $document_name,
								':content' => json_encode($records),
								':id' => $case['id']
							]
						]);
					}
					else {
						$success = SQLQUERY::EXECUTE($this->_pdo, 'records_post', [
							'values' => [
								':context' => $context,
								':record_type' => $record_type ? : null,
								':identifier' => $identifier,
								':last_user' => $_SESSION['user']['id'],
								':last_document' => $document_name,
								':content' => json_encode([$current_record]),
							]
						]);
					}
					if ($success){
						// get document recommendations
						$bd = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist');
						$hidden = $recommended = [];
						foreach($bd as $key => $row) {
							if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
							if (!in_array($row['name'], $hidden)) {
								$necessarydocuments = $row['content'] ? explode(',', $row['content']) : [];
								if ($necessarydocuments && ($documentindex = array_search($document_name, $necessarydocuments)) !== false) {
									if (isset($necessarydocuments[++$documentindex])) {
										// recurring queries to make sure linked documents are permitted
										if ($document = $this->latestApprovedName('document_document_get_by_name', $necessarydocuments[$documentindex]))
											$recommended[$document['name']] = ['href' => "javascript:api.record('get', 'document', '" . $document['name'] . "', '" . $identifier . "')"];
									}
								}
							}
						}
						ksort($recommended);
						if ($recommended)
							$this->response([
								'response' => [
									'msg' => LANG::GET('record.record_saved'),
									'type' => 'success'
								],
								'render' => [
									'content' => [
										[
											'type' => 'links',
											'description' => LANG::GET('record.record_recommended_continue'),
											'content' => $recommended
										]
									]
								]
							]);
						$this->response([
							'response' => [
								'msg' => LANG::GET('record.record_saved'),
								'type' => 'success'
							],
						]);
					}
				}
				$this->response([
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
				if (!$content) $this->response([], 404);
				$body[] = [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => LANG::GET('record.create_identifier')
						],
						'content' => $this->_requestedID
					]
				];

				foreach(CONFIG['label'] as $type => $setting){
					$body[count($body) -1][] = [
						'type' => 'button',
						'attributes' => [
							'type' => 'button',
							'onpointerup' => "_client.application.postLabelSheet('" . $this->_requestedID . "', null, {_type:'" . $type . "'});",
							'value' => LANG::GET('record.create_identifier_type', [':format' => $setting['format']])
						]
					];
				}
				
				$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
					'values' => [
						':identifier' => $this->_requestedID
					]
				]);
				$data = $data ? $data[0] : null;
				$messagedialog = [LANG::GET('record.record_casestate_change_recipient_supervisor_only', [':supervisor' => LANG::GET('permissions.supervisor')]) => []];
				foreach($data['units'] ? explode(',', $data['units']) : [] as $unit){
					$messagedialog[LANGUAGEFILE['units'][$unit]] = ['value' => $unit];
				}
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
				$datalist = [];
				foreach($user as $key => $row) {
					if ($row['id'] > 1 && $row['id'] !== $_SESSION['user']['id']) $datalist[] = $row['name'];
				}

				if ($casestate = $this->casestate($data['context'], 'checkbox', ['onchange' => "api.record('put', 'casestate', '" . $this->_requestedID. "', this.dataset.casestate, this.checked);"
					. " new Dialog({type: 'input', header: '" . LANG::GET('record.record_casestate_change_message') . "', render: JSON.parse('"
					. json_encode([
						[
							'type' => 'checkbox',
							'attributes' => [
								'name' => LANG::GET('record.record_casestate_change_recipient_unit')
							],
							'content' => $messagedialog
						],
						[
							'type' => 'datalist',
							'attributes' => [
								'id' => 'rcptlist'
							],
							'content' => $datalist
						],
						[
							'type' => 'hidden',
							'attributes' => [
								'name' => 'identifier',
								'value' => $this->_requestedID
							]
						],
						[
							'type' => 'hidden',
							'attributes' => [
								'name' => 'context',
								'value' => $data['context']
							]
						],
						[
							'type' => 'text',
							'attributes' => [
								'name' => LANG::GET('record.record_casestate_change_recipient'),
								'list' => 'rcptlist'
							]
						]
					])
					. "'.replace()), options: JSON.parse('"
					. json_encode([
						LANG::GET('general.cancel_button') => false,
						LANG::GET('general.submit_button') => ['value' => true, 'class'=> 'reducedCTA']
					])
					."')}).then((response) => { if (response) { response.casestate = this.dataset.casestate; response.casestatestate = this.checked; api.record('post', 'casestatealert', null, _client.application.dialogToFormdata(response)); }});"
					], $data['case_state'])){
					$body[] = [$casestate];
				}

				// get document recommendations
				$bd = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist');
				$hidden = $bundles = [];
				foreach($bd as $key => $row) {
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (!in_array($row['name'], $hidden) && !isset($bundles[$row['name']])) {
						$bundles[$row['name']] = $row['content'] ? explode(',', $row['content']) : [];
					}
				}
				ksort($bundles);
				$includedDocuments = array_keys($content['content']);

				// prepare available documents to control appending button
				$validDocuments = [];
				$fd = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
				$hidden = [];
				foreach($fd as $key => $row) {
					if (!PERMISSION::fullyapproved('documentapproval', $row['approval']) || !PERMISSION::permissionIn($row['restricted_access'])) continue;
					if ($row['hidden'] || in_array($row['context'], array_keys(LANGUAGEFILE['documentcontext']['notdisplayedinrecords']))) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (!in_array($row['name'], $validDocuments) && !in_array($row['name'], $hidden)) {
						$validDocuments[] = $row['name'];
					}
				}

				foreach($content['content'] as $document => $entries){
					if ($document === LANG::GET('record.record_altering_pseudodocument_name', [], true)) continue;
					$body[] = [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $document
							]
						]
					];
					foreach($entries as $key => $value){
						array_push($body[count($body) -1],
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $key
								],
								'linkedcontent' => $value
							]); 
					}
					if (isset($content['images'][$document])){
						foreach ($content['images'][$document] as $image){
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
					if (isset($content['files'][$document])){
						array_push($body[count($body) -1],
						[
							'type' => 'links',
							'description' => LANG::GET('record.record_file_attachments'),
							'content' => $content['files'][$document]
						]); 
					}
					
					if ($document != LANG::GET('record.record_altering_pseudodocument_name', [], true) && PERMISSION::permissionFor('recordsexport')){
						if (in_array($document, $includedDocuments) && in_array($document, $validDocuments) && !array_intersect(['group'], $_SESSION['user']['permissions'])) array_push($body[count($body) -1],[
							'type' => 'button',
							'attributes' => [
								'title' => LANG::GET('record.record_append_document'),
								'onpointerup' => "api.record('get', 'document', '" . $document . "', '" . $this->_requestedID . "')",
								'data-type' => 'additem',
								'class' => 'inlinebutton'
							]
						]);
						array_push($body[count($body) -1],[
							'type' => 'button',
							'attributes' => [
								'title' => LANG::GET('record.record_export'),
								'data-type' => 'download',
								'class' => 'inlinebutton',
								'onpointerup' => "new Dialog({type: 'input', header: '". LANG::GET('record.record_export') . "', render: [" . 
								"{type:'button', attributes:{value: LANG.GET('record.record_full_export'), 'data-type': 'download', onpointerup: 'api.record(\'get\', \'documentexport\', \'" . $this->_requestedID . "\', \'" . $document . "\')'}},".
								"{type:'button', attributes:{value: LANG.GET('record.record_simplified_export'), 'data-type': 'download', onpointerup: 'api.record(\'get\', \'simplifieddocumentexport\', \'" . $this->_requestedID . "\', \'" . $document . "\')'}}".
								"], options:{'" . LANG::GET('general.cancel_button') . "': false}})"
							]
						]);
					}
					$recommended = [];
					// append next recommendations inline -> caveat, missing first documents will not be displayed inline
					if (in_array($document, $includedDocuments)) foreach($bundles as $bundle => $necessarydocuments){
						if (($documentindex = array_search($document, $necessarydocuments)) !== false){ // this document is part of the current bundle
							if (isset($necessarydocuments[++$documentindex])) { // there is a document defined in bundle coming afterwards 
								if (array_search($necessarydocuments[$documentindex], $includedDocuments) === false) { // the following document has not been taken into account
									// recurring queries to make sure linked documents are permitted
									if ($approveddocument = $this->latestApprovedName('document_document_get_by_name', $necessarydocuments[$documentindex])) // document is permitted
										$recommended[LANG::GET('record.record_append_missing_document_of_bundle', [':document' => $approveddocument['name'], ':bundle' => $bundle])] = ['href' => "javascript:api.record('get', 'document', '" . $approveddocument['name'] . "', '" . $this->_requestedID . "')"];
								}
							}
						}
					}
					if ($recommended) $body[]= [[
							'type' => 'links',
							'description' => LANG::GET('record.record_append_missing_document'),
							'content' => $recommended
						]];
				}
				// append record_retype_pseudodocument
				if (isset($content['content'][LANG::GET('record.record_altering_pseudodocument_name', [], true)])){
					$entries = $content['content'][LANG::GET('record.record_altering_pseudodocument_name', [], true)];
					$body[] = [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => LANG::GET('record.record_altering_pseudodocument_name')
							]
						]
					];
					foreach($entries as $key => $value){
						array_push($body[count($body) -1],
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $key
								],
								'linkedcontent' => $value
							]); 
					}
				}
		
				$return['render']['content'] = $body;

				if ($content['record_type']) {
					$typeaction = '';
					if (PERMISSION::permissionFor('recordsretyping')){
						$options = [];
						foreach (LANGUAGEFILE['record']['record_type'] as $record_type => $description){
							$options[$description] = ['value' => $record_type];
						}						
						$typeaction = "<a href=\"javascript:void(0);\" onpointerup=\"new Dialog({type: 'input', header: '". LANG::GET('record.record_retype_header', [':type' => LANGUAGEFILE['record']['record_type'][$content['record_type']]]) . "', render: JSON.parse('" . json_encode(
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
									'value' => $content['identifier']
								]
							]]
						) . "'), options:{".
						"'" . LANG::GET('general.cancel_button') . "': false,".
						"'" . LANG::GET('general.ok_button')  . "': {value: true, class: 'reducedCTA'},".
						"}}).then(response => { if (response) api.record('post', 'retype', null, _client.application.dialogToFormdata(response))})"
						. "\">" . LANG::GET('record.record_retype_header', [':type' => LANGUAGEFILE['record']['record_type'][$content['record_type']]]) . '</a>';
					}
					$return['render']['content'][] = [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => LANGUAGEFILE['record']['record_type'][$content['record_type']],
							],
							'linkedcontent' => $typeaction
						]
					];
					$last_element = count($return['render']['content'])-1;
				}
				else $last_element = count($return['render']['content']);
				if (PERMISSION::permissionFor('recordsclosing')){
					// similar dialog on similarity check within reidentify method
					$return['render']['content'][$last_element][] = 
					[
						'type' => 'button',
						'attributes' => [
							'data-type' => 'merge',
							'value' => LANG::GET('record.record_reidentify'),
							'onpointerup' => "new Dialog({type: 'input', header: '". LANG::GET('record.record_reidentify') . "', render: JSON.parse('" . json_encode(
								[
									[
										'type' => 'scanner',
										'hint' => LANG::GET('record.create_identifier_hint'),
										'attributes' => [
											'name' => LANG::GET('record.create_identifier'),
											'maxlength' => CONFIG['limits']['identifier']
										]
									],
									[
										'type' => 'checkbox',
										'content' => [
											LANG::GET('record.record_reidentify_confirm') => ['required' => true] 
										]
									],
									[
										'type' => 'hidden',
										'attributes' => [
											'name' => '_previousIdentifier',
											'value' => $this->_requestedID
										]
									]
								]
							) .
							"'), options:{'" . LANG::GET('general.cancel_button') . "': false,".
							"'" . LANG::GET('general.ok_button')  . "': {value: true, class: 'reducedCTA'},".
							"}}).then(response => { if (response) api.record('post', 'reidentify', null, _client.application.dialogToFormdata(response))})"
						]
					];
				}
				else $return['render']['content'][$last_element][] = [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => LANG::GET('record.record_reidentify_unauthorized_name')
						],
						'content' => LANG::GET('record.record_reidentify_unauthorized_content', [':permissions' => implode(', ', array_map(fn($v)=>LANGUAGEFILE['permissions'][$v], PERMISSION::permissionFor('recordsclosing', true)))])
					]
				];


				if (!array_intersect(['group'], $_SESSION['user']['permissions'])){
					$last_element = count($return['render']['content'])-1;
					// simple groups are not allowed to append to document
					$bundles = ['...' . LANG::GET('record.record_match_bundles_default') => ['value' => '0']];
					// match against bundles
					// prepare existing bundle lists
					$bd = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist');
					$hidden = [];
					foreach($bd as $key => $row) {
						if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
						if (!in_array($row['name'], $bundles) && !in_array($row['name'], $hidden)) {
							$bundles[$row['name']] = ['value' => $row['name']];
						}
					}

					$return['render']['content'][$last_element][] = 
						[
							'type' => 'select',
							'attributes' => [
								'name' => LANG::GET('record.record_match_bundles'),
								'onchange' => "if (this.value != '0') api.record('get', 'matchbundles', this.value, '" . $this->_requestedID . "')"
							],
							'hint' => LANG::GET('record.record_match_bundles_hint'),
							'content' => $bundles
						];
						
					if (PERMISSION::permissionFor('recordsexport'))
						array_push ($return['render']['content'][$last_element], [
							[
								'type' => 'button',
								'attributes' => [
									'title' => LANG::GET('record.record_export'),
									'value' => LANG::GET('record.record_export'),
									'data-type' => 'download',
									'onpointerup' => "new Dialog({type: 'input', header: '". LANG::GET('record.record_export') . "', render: [" . 
									"{type:'button', attributes:{value: LANG.GET('record.record_full_export'), 'data-type': 'download', onpointerup: 'api.record(\'get\', \'fullexport\', \'" . $this->_requestedID . "\')'}},".
									"{type:'button', attributes:{value: LANG.GET('record.record_simplified_export'), 'data-type': 'download', onpointerup: 'api.record(\'get\', \'simplifiedexport\', \'" . $this->_requestedID . "\')'}}".
									"], options:{'" . LANG::GET('general.cancel_button') . "': false}})"
								]
							]
						]);
					
					$content['closed'] = $content['closed'] !== null ? json_decode($content['closed'], true) : [];
					$approvalposition = [];
					foreach ($content['closed'] as $role => $property){
						array_splice($return['render']['content'][$last_element], 1, 0, [[
							'type' => 'textsection',
							'attributes' => [
								'name' => LANG::GET('record.record_closed', [':role' => LANG::GET('permissions.' . $role), ':name' => $property['name'], ':date' => $property['date']])
							]
						]]);
					}

					if ($content['record_type'] === 'complaint' && PERMISSION::permissionFor('complaintclosing')){
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
						array_splice($return['render']['content'][$last_element], 1, 0, [[
							'type' => 'checkbox',
							'content' => $approvalposition,
							'attributes' => [
								'name' => LANG::GET('record.record_mark_as_closed')
							]
						]]);
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

		require_once('_shared.php');
		$search = new SHARED($this->_pdo);
		$data = $search->recordsearch(['search' => $this->_requestedID === 'null' ? null : $this->_requestedID]);

		if (!$data) {
			$result['render']['content'] = $this->noContentAvailable(LANG::GET('message.no_messages'));
			$this->response($result);
		}

		$recorddatalist = $contexts = $available_units = [];
		foreach($data as $contextkey => $context){
			foreach($context as $record){
				// prefilter record datalist for performance reasons
				preg_match('/' . CONFIG['likeliness']['records_identifier_pattern']. '/mi', $record['identifier'], $simplified_identifier);
				if ($simplified_identifier && !in_array($simplified_identifier[0], $recorddatalist)) $recorddatalist[] = $simplified_identifier[0];
				
				// append units of available records 
				if ($record['units']) array_push($available_units, ...$record['units']);
				else $available_units[] = null;
				// add to result
				$linkdisplay = LANG::GET('record.record_list_touched', [
					':identifier' => $record['identifier'],
					':date' => $record['last_touch'],
					':document' => $record['last_document']
					]);
				$contexts[$contextkey][$linkdisplay] = [
					'href' => "javascript:api.record('get', 'record', '" . $record['identifier'] . "')"
				];
				foreach($record['case_state'] as $case => $state){
					$contexts[$contextkey][$linkdisplay]['data-' . $case] = $state;
				}
				if ($record['complaint']) $contexts[$contextkey][$linkdisplay]['class'] = 'orange';
				if ($record['closed']) $contexts[$contextkey][$linkdisplay]['class'] = 'green';
			}
		}

		$organizational_units = [];
		$available_units = array_unique($available_units);
		sort($available_units);
		$assignable = true;
		$organizational_units[LANG::GET('record.record_mine')] = ['name' => LANG::PROPERTY('order.organizational_unit'), 'onchange' => "api.record('get', 'records', document.getElementById('_recordfilter').value || 'null')"];
		if (!$this->_unit) $organizational_units[LANG::GET('record.record_mine')]['checked'] = true;
		foreach($available_units as $unit){
			if (!$unit) {
				$assignable = false;
				continue;
			}
			$organizational_units[LANGUAGEFILE['units'][$unit]] = ['name' => LANG::PROPERTY('order.organizational_unit'), 'onchange' => "api.record('get', 'records', document.getElementById('_recordfilter').value || 'null', '" . $unit . "')"];
			if ($this->_unit === $unit) $organizational_units[LANGUAGEFILE['units'][$unit]]['checked'] = true;
		}
		if (!$assignable) {
			$organizational_units[LANG::GET('record.record_unassigned')] = ['name' => LANG::PROPERTY('order.organizational_unit'), 'onchange' => "api.record('get', 'records', document.getElementById('_recordfilter').value || 'null', '_unassigned')"];
			if ($this->_unit === '_unassigned') $organizational_units[LANG::GET('record.record_unassigned')]['checked'] = true;
		}

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
					'destination' => '_recordfilter',
					'description' => LANG::GET('record.record_scan')
				], [
					'type' => 'filtered',
					'hint' => LANG::GET('record.record_filter_hint', [':max' => CONFIG['limits']['max_records']]),
					'attributes' => [
						'id' => '_recordfilter',
						'name' => LANG::GET('record.record_filter'),
						'list' => 'records',
						'onkeypress' => "if (event.key === 'Enter') {api.record('get', 'records', this.value); return false;}",
						'onblur' => "api.record('get', 'records', this.value); return false;",
						'value' => $this->_requestedID && $this->_requestedID !== 'null' ? : ''
						]
				]
			],
			[
				'type' => 'radio',
				'attributes' => [
					'name' => LANG::GET('order.organizational_unit')
				],
				'content' => $organizational_units,
				'hint' => LANG::GET('record.record_assign_hint')
			]
		];
		foreach($contexts as $context => $links){
			if ($links){
				if ($casestate = $this->casestate(explode('.', $context)[1], 'radio', ['onchange' => "_client.record.casestatefilter(this.dataset.casestate)"]))
					array_push($content, $casestate);
				array_push($content, [
					[
						'type' => 'links',
						'description' => LANG::GET('documentcontext.' . $context),
						'content' => $links
					]
				]);
			}
			else array_push($content, $this->noContentAvailable(LANG::GET('message.no_messages')));
		}


		$result['render']['content'] = $content;
		$this->response($result);		
	}

	/**
	 *           _   _         _   _ ___     
	 *   ___ ___|_|_| |___ ___| |_|_|  _|_ _ 
	 *  |  _| -_| | . | -_|   |  _| |  _| | |
	 *  |_| |___|_|___|___|_|_|_| |_|_| |_  |
	 *                                  |___|
	 */
	public function reidentify(){
		if (!PERMISSION::permissionFor('recordsclosing')) $this->response([], 401);
		$entry_id = UTILITY::propertySet($this->_payload, '_previousIdentifier');
		$new_id = UTILITY::propertySet($this->_payload, LANG::PROPERTY('record.create_identifier'));
		$confirmation = UTILITY::propertySet($this->_payload, LANG::PROPERTY('record.record_reidentify_confirm'));
		$thresholdconfirmation = UTILITY::propertySet($this->_payload, LANG::PROPERTY('record.record_reidentify_similarity_confirm'));
		if (!($entry_id && $new_id && $confirmation)) $this->response([], 406);

		// append timestamp to new id if applicable
		$possibledate = substr($new_id, -16);
		try {
			new DateTime($possibledate);
		}
		catch (Exception $e){
			$new_id .= ' ' . $this->_currentdate->format('Y-m-d H:i');
		}

		// compare identifiers, warn if similarity is too low
		// strip dates for comparison
		$similar_new_id = substr($new_id, 0, -16);
		$possibledate = substr($entry_id, -16);
		try {
			new DateTime($possibledate);
			$similar_entry_id = substr($entry_id, 0, -16);
		}
		catch (Exception $e){
			$similar_entry_id = $entry_id;
		}
		similar_text($similar_new_id, $similar_entry_id, $percent);
		if (!$thresholdconfirmation && $percent < CONFIG['likeliness']['record_reidentify_similarity']) {
				// similar dialog on reidentify button within record method
				$return = ['render' => ['content' => [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => LANG::GET('record.record_reidentify_warning', [':percent' => CONFIG['likeliness']['record_reidentify_similarity']])
						],
						'content' => $entry_id . " \n-> " . $new_id
					],
					[
						'type' => 'button',
						'attributes' => [
							'data-type' => 'merge',
							'value' => LANG::GET('record.record_reidentify'),
							'onpointerup' => "new Dialog({type: 'input', header: '". LANG::GET('record.record_reidentify') . "', render: JSON.parse('" . json_encode(
								[
									[
										'type' => 'scanner',
										'hint' => LANG::GET('record.create_identifier_hint'),
										'attributes' => [
											'name' => LANG::GET('record.create_identifier'),
											'maxlength' => CONFIG['limits']['identifier'],
											'value' => $new_id
										]
									],
									[
										'type' => 'checkbox',
										'content' => [
											LANG::GET('record.record_reidentify_confirm') => ['required' => true],
											LANG::GET('record.record_reidentify_similarity_confirm') => ['required' => true]
										]
									],
									[
										'type' => 'hidden',
										'attributes' => [
											'name' => '_previousIdentifier',
											'value' => $entry_id
										]
									]
								]
							) .
							"'), options:{'" . LANG::GET('general.cancel_button') . "': false,".
							"'" . LANG::GET('general.ok_button')  . "': {value: true, class: 'reducedCTA'},".
							"}}).then(response => { if (response) api.record('post', 'reidentify', null, _client.application.dialogToFormdata(response))})"
						]
					]
				]]];
				$this->response($return);
		}
		
		// check if new id (e.g. scanned) is already taken
		$original = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
			'values' => [
				':identifier' => $new_id
			]
		]);
		$original = $original ? $original[0] : null;

		$merge = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
			'values' => [
				':identifier' => $entry_id
			]
		]);
		$merge = $merge ? $merge[0] : null;
		$merge['content'] = json_decode($merge['content'], true);
		$merge['content'][] = [
			'author' => $_SESSION['user']['name'],
			'date' => $this->_currentdate->format('y-m-d H:i:s'),
			'document' => 0,
			'content' => [
				LANG::GET('record.record_reidentify_pseudodocument_name', [], true) => ($original ? LANG::GET('record.record_reidentify_merge_content', [':identifier' => $entry_id], true) : LANG::GET('record.record_reidentify_identify_content', [':identifier' => $entry_id], true))
			]
		];

		if (!$original) {
			// overwrite identifier, append record altering
			if (SQLQUERY::EXECUTE($this->_pdo, 'records_put', [
				'values' => [
					':case_state' => $merge['case_state'] ? : null,
					':record_type' => $merge['record_type'],
					':identifier' => $new_id,
					':last_user' => $_SESSION['user']['id'],
					':last_document' => $merge['last_document'],
					':content' => json_encode($merge['content']),
					':id' => $merge['id']
			]])) $this->response([
				'response' => [
					'msg' => LANG::GET('record.record_reidentify_success'),
					'type' => 'success'
				]]);
		}
		else {
			$original['content'] = json_decode($original['content'], true);
			foreach($merge['content'] as $record){
				$original['content'][] = $record;
			}
			usort($original['content'], Fn($a, $b) => $a['date'] <=> $b['date']);

	
			if (SQLQUERY::EXECUTE($this->_pdo, 'records_put', [
				'values' => [
					':case_state' => $original['case_state'] ? : null,
					':record_type' => $original['record_type'],
					':identifier' => $new_id,
					':last_user' => $_SESSION['user']['id'],
					// get last considered document, offset -1 because pseudodocument has been added before by default
					':last_document' => $original['content'][count($original['content']) - 2]['document'] ? : LANG::GET('record.record_retype_pseudodocument_name', [], true),
					':content' => json_encode($original['content']),
					':id' => $original['id']
			]]) && SQLQUERY::EXECUTE($this->_pdo, 'records_delete', [
				'values' => [
					':id' => $merge['id']
			]])) $this->response([
				'response' => [
					'msg' => LANG::GET('record.record_reidentify_merged'),
					'type' => 'success'
				]]);
		}
		$this->response([
			'response' => [
				'msg' => LANG::GET('record.record_reidentify_failure'),
				'type' => 'error'
			]]);		
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

		$original = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
			'values' => [
				':identifier' => $entry_id
			]
		]);
		$original = $original ? $original[0] : null;
		if ($original && $record_type){
			$original['content'] = json_decode($original['content'], true);
			$original['content'][] = [
				'author' => $_SESSION['user']['name'],
				'date' => $this->_currentdate->format('y-m-d H:i'),
				'document' => 0,
				'content' => [
					LANG::GET('record.record_retype_pseudodocument_name', [], true) => LANG::GET('record.record_retype_content', [
					':previoustype' => LANG::GET('record.record_type.' . $original['record_type'], [], true),
					':newtype' => LANG::GET('record.record_type.' . $record_type, [], true)
					], true)]
			];

			if (SQLQUERY::EXECUTE($this->_pdo, 'records_put', [
				'values' => [
					':case_state' => $original['case_state'] ? : null,
					':record_type' => $record_type,
					':identifier' => $original['identifier'],
					':last_user' => $_SESSION['user']['id'],
					':last_document' => $original['last_document'],
					':content' => json_encode($original['content']),
					':id' => $original['id']
			]])) $this->response([
				'response' => [
					'msg' => LANG::GET('record.record_saved'),
					'type' => 'success'
				]]);
		}
		$this->response([
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
		$this->export('simplified', true);
	}

	/**
	 *       _           _     ___ _       _ ___                                   _   
	 *   ___|_|_____ ___| |_ _|  _|_|___ _| |  _|___ ___ _____ ___ _ _ ___ ___ ___| |_ 
	 *  |_ -| |     | . | | | |  _| | -_| . |  _| . |  _|     | -_|_'_| . | . |  _|  _|
	 *  |___|_|_|_|_|  _|_|_  |_| |_|___|___|_| |___|_| |_|_|_|___|_,_|  _|___|_| |_|  
	 *              |_|   |___|                                       |_|              
	 */
	public function simplifieddocumentexport(){
		$this->export('simplifieddocument', true);
	}

	/**
	 *                               _                               _
	 *   ___ _ _ _____ _____ ___ ___|_|___ ___ ___ ___ ___ ___ ___ _| |
	 *  |_ -| | |     |     | .'|  _| |- _| -_|  _| -_|  _| . |  _| . |
	 *  |___|___|_|_|_|_|_|_|__,|_| |_|___|___|_| |___|___|___|_| |___|
	 *
	 * @param str $type full, simplified, document
	 * @param bool $retype based on view and permission link to retype or not
	 * @return array $summary
	 */

	private function summarizeRecord($type = 'full', $retype = false, $export = false){
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
			'values' => [
				':identifier' => $this->_requestedID
			]
		]);
		$data = $data ? $data[0] : null;
		if (!$data) return false;

		$summary = [
			'filename' => preg_replace('/' . CONFIG['forbidden']['names'][0] . '/', '', $this->_requestedID . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => $this->_requestedID,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => LANG::GET('menu.record_summary'),
			'date' => $this->_currentdate->format('y-m-d H:i'),
			'closed' => $data['closed'],
			'record_type' => $data['record_type'],
			'units' => $data['units'] ? explode(',', $data['units']) : []
		];
		$accumulatedcontent = [];

		$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');

		$records = json_decode($data['content'], true);
		foreach($records as $record){
			$document = $documents[array_search($record['document'], array_column($documents, 'id'))] ? : ['name' => null, 'restricted_access' => null];
			if (!PERMISSION::permissionIn($document['restricted_access'])) continue;
			if (in_array($type, ['document', 'simplifieddocument']) && ($document['name'] != $this->_documentExport)) continue;
			if ($record['document'] == 0) { // retype and casestate autodocument
				if (in_array($type, ['simplified', 'simplifieddocument'])) continue;
				$useddocument = LANG::GET('record.record_altering_pseudodocument_name', [], true);
			}
			else $useddocument = $document['name'];
			if (!isset($accumulatedcontent[$useddocument])) $accumulatedcontent[$useddocument] = ['last_record' => null, 'content' => []];

			if (gettype($record['content']) === 'string') $record['content'] = json_decode($record['content'], true);
			foreach($record['content'] as $key => $value){
				$key = str_replace('_', ' ', $key);
				$value = str_replace(' | ', "\n\n", $value); // part up multiple selected checkbox options
				$value = str_replace('\n', "\n", $value); // format linebreaks
				if (!isset($accumulatedcontent[$useddocument]['content'][$key])) $accumulatedcontent[$useddocument]['content'][$key] = [];
				$accumulatedcontent[$useddocument]['content'][$key][] = ['value' => $value, 'author' => LANG::GET('record.record_export_author', [':author' => $record['author'], ':date' => substr($record['date'], 0, -3)])];
				if (!$accumulatedcontent[$useddocument]['last_record'] || $accumulatedcontent[$useddocument]['last_record'] > $record['date']) $accumulatedcontent[$useddocument]['last_record'] = $record['date'];
			}
		} 

		foreach($accumulatedcontent as $document => $entries){
			$summary['content'][$document] = [];
			foreach($entries['content'] as $key => $data){
				$summary['content'][$document][$key] = '';
				$value = '';
				foreach($data as $entry){
					if ($entry['value'] !== $value){
						$displayvalue = $entry['value'];
						// guess file url; special regex delimiter
						if (stripos($entry['value'], substr(UTILITY::directory('record_attachments'), 1)) !== false) {
							$file = pathinfo($entry['value']);
							if (in_array($file['extension'], ['jpg', 'jpeg', 'gif', 'png'])) {
								if (!isset($summary['images'][$document])) $summary['images'][$document] = [];
								$summary['images'][$document][] = $entry['value'];
							}
							else {
								if (!isset($summary['files'][$document])) $summary['files'][$document] = [];
								$summary['files'][$document][$file['basename']] = ['href' => $entry['value']];
							}
							$displayvalue = $file['basename'];
						}
						switch ($type){
							case 'document':
							case 'full':
								$summary['content'][$document][$key] .= $displayvalue . ' (' . $entry['author'] . ")\n";
								break;
							case 'simplified':
							case 'simplifieddocument':
								$summary['content'][$document][$key] = $displayvalue . "\n";
								break;
						}
						$value = $entry['value'];
					}
				}
			}
		}

		if ($export) {
			// reiterate over document, add textsections and empty document fields
			require_once('_shared.php');
			$documentfinder = new SHARED($this->_pdo);

			function enumerate($name, $enumerate = [], $number = 1){
				if (isset($enumerate[$name])) $enumerate[$name] += $number;
				else $enumerate[$name] = $number;	
				return $enumerate;
			}
	
			function printable($element, $payload, $type, $enumerate = []){
				$content = ['content' => []];
				foreach($element as $subs){
					if (!isset($subs['type'])){
						$subcontent = printable($subs, $payload, $type, $enumerate);
						foreach($subcontent['enumerate'] as $name => $number){
							$enumerate = enumerate($name, $enumerate,  $number); // add from recursive call
						}
						$content['content'] = array_merge($content['content'], $subcontent['content']);
					}
					else {
						if (in_array($subs['type'], ['identify', 'documentbutton', 'calendarbutton'])) continue;
						if (in_array($subs['type'], ['image', 'links'])) {
							$name = $subs['description'];
						}
						else $name = $subs['attributes']['name'];
						$enumerate = enumerate($name, $enumerate); // enumerate proper names, checkbox gets a generated payload with chained checked values by default
						$originName = $name;
						if ($enumerate[$name] > 1) {
							$name .= '(' . $enumerate[$name] . ')'; // multiple similar form field names -> for fixed component content, not dynamic created multiple fields
						}

						if ($subs['type'] === 'textsection'){
							$value = isset($subs['content']) ? str_replace('\n', "\n", $subs['content']) // format linebreaks
							 : ' ';
						}
						elseif (isset($payload[$name])) {
							$value = $payload[$name];
						}
						else $value = '-';

						$content['content'][$name] = $value;
						$dynamicMultiples = preg_grep('/' . preg_quote($originName, '/') . '\(\d+\)/m', array_keys($payload));
						foreach($dynamicMultiples as $matchkey => $submitted){
							$value = $payload[$submitted];
							$content['content'][$submitted] = $value;
						}
					}
				}
				$content['enumerate'] = $enumerate;
				return $content;
			};

			$printablecontent = $enumerate = [];
			foreach($summary['content'] as $document => $content){
				if ($useddocument = $documentfinder->recentdocument('document_document_get_by_name', [
					'values' => [
						':name' => $document
					]], $accumulatedcontent[$document]['last_record'])) $printablecontent[$document . ' ' . LANG::GET('assemble.document_export_exported', [':version' => substr($useddocument['date'], 0, -3), ':date' => $this->_currentdate->format('y-m-d H:i')])] = printable($useddocument['content'], $content, $type, $enumerate)['content'];
			}
			$summary['content'] = $printablecontent;
			if ($type === 'simplifieddocument'){
				$summary['content'] = [' ' => $printablecontent[$useddocument['name'] . ' ' . LANG::GET('assemble.document_export_exported', [':version' => substr($useddocument['date'], 0, -3), ':date' => $this->_currentdate->format('y-m-d H:i')])]];
				$summary['date'] = LANG::GET('assemble.document_export_exported', [':version' => substr($useddocument['date'], 0, -3), ':date' => $this->_currentdate->format('y-m-d H:i')]);
				$summary['title'] = $useddocument['name'];
				$summary['images'] = [' ' => isset($summary['images'][$useddocument['name']]) ? $summary['images'][$useddocument['name']] : []];
			}
		}
		return $summary;
	}
}
?>
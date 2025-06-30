<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
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

namespace CARO\API;

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
		if (array_intersect(['patient'], $_SESSION['user']['permissions']) && 
			!in_array(REQUEST[1], ['document', 'record'])
		) $this->response([], 401);

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
	 * reusable method
	 * @param string $context as casestate context (according to language.xx.json)
	 * @param string $type checkbox or radio
	 * @param array $action inputs js events for inputs
	 * @param string $checked json-encoded case_state options from the record
	 * 
	 * @return response|array either result of state update or form elements
	 */
	public function casestate($context = null, $type = 'checkbox', $action = [], $checked = ''){
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
						'date' => $this->_date['servertime']->format('Y-m-d H:i:s'),
						'document' => 0,
						'content' => UTILITY::json_encode([
							$this->_lang->GET('record.pseudodocument_' . $case['context'], [], true) => $this->_lang->GET($this->_caseStateBoolean === 'true' ? 'record.casestate_set' : 'record.casestate_revoked', [':casestate' => $this->_lang->GET('casestate.' . $case['context'] . '.' . $this->_caseState, [], true)], true)
						])
					];
					$records = json_decode($case['content'], true);
					$records[] = $current_record;
					$case_state = json_decode($case['case_state'] ? : '', true);
					if ($this->_caseStateBoolean === 'true') $case_state[$this->_caseState] = true;
					else unset($case_state[$this->_caseState]);
					if (SQLQUERY::EXECUTE($this->_pdo, 'records_put', [
						'values' => [
							':case_state' => UTILITY::json_encode($case_state) ? : null,
							':record_type' => $case['record_type'] ? : null,
							':identifier' => $this->_requestedID,
							':last_user' => $_SESSION['user']['id'],
							':last_document' => null,
							':content' => UTILITY::json_encode($records),
							':id' => $case['id']
						]
					])) $this->response([
						'response' => [
							'msg' => $this->_lang->GET($this->_caseStateBoolean === 'true' ? 'record.casestate_set' : 'record.casestate_revoked', [':casestate' => $this->_lang->_USER['casestate'][$case['context']][$this->_caseState]]),
							'type' => 'success'
						]]);
				}
				$this->response([
					'response' => [
						'msg' => $this->_lang->GET('record.error'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				if (!isset($this->_lang->_USER['casestate'][$context])) return;
				$content = [];
				$checked = json_decode($checked ? : '', true);
				if ($type === 'radio'){
					$content[$this->_lang->GET('record.casestate_filter_all')] = ['onchange' => "_client.record.casestatefilter(undefined)"];
					if (!$checked) $content[$this->_lang->GET('record.casestate_filter_all')]['checked'] = true;
				}
				foreach ($this->_lang->_USER['casestate'][$context] as $state => $translation){
					$content[$translation] = $action;
					$content[$translation]['data-casestate'] = $state;
					if (isset($checked[$state])) $content[$translation]['checked'] = true;
					if ($type === 'radio' && isset($_SESSION['user']['app_settings']['primaryRecordState']) && $state === $_SESSION['user']['app_settings']['primaryRecordState']) $content[$translation]['checked'] = true;
					if (!PERMISSION::permissionFor('recordscasestate') && $type === 'checkbox') $content[$translation]['disabled'] = true;
				}
				return [
					'type' => $type,
					'attributes' => [
						'name' => $this->_lang->GET('record.pseudodocument_' . $context)
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
	 * send a system message to selected users, units or supervisors
	 */
	public function casestatealert(){
		if ($identifier = UTILITY::propertySet($this->_payload, 'identifier')) unset($this->_payload->identifier);
		if ($context = UTILITY::propertySet($this->_payload, 'context')) unset ($this->_payload->context);
		if ($casestate = UTILITY::propertySet($this->_payload, 'casestate')) unset ($this->_payload->casestate);
		if ($casestatestate = UTILITY::propertySet($this->_payload, 'casestatestate')) unset ($this->_payload->casestatestate);
		if ($recipient = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('record.casestate_change_recipient'))) unset ($this->_payload->{$this->_lang->PROPERTY('record.casestate_change_recipient')});
		if ($inquiry = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('record.inquiry'))) unset ($this->_payload->{$this->_lang->PROPERTY('record.inquiry')});
		$recipient = $recipient ? preg_split('/[,;]\s{0,}/', $recipient) : [];
		// remainder of payload are checked units and maybe supervisor_only flag
		$permission = [];
		if (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('record.casestate_change_recipient_supervisor_only', [':supervisor' => $this->_lang->PROPERTY('permissions.supervisor')]))) {
			$permission = ['supervisor'];
			unset($this->_payload->{$this->_lang->PROPERTY('record.casestate_change_recipient_supervisor_only', [':supervisor' => $this->_lang->PROPERTY('permissions.supervisor')])});
		}
		if ($casestate)	{
				$message = $this->_lang->GET('record.casestate_change_message_content', [
				':user' => $_SESSION['user']['name'],
				':identifier' => '<a href="javascript:void(0);" onclick="api.record(\'get\', \'record\', \'' . $identifier . '\')"> ' . $identifier . '</a>',
				':casestate' => $this->_lang->GET($casestatestate === 'true' ? 'record.casestate_set' : 'record.casestate_revoked', [':casestate' => $this->_lang->GET('casestate.' . $context . '.' . $casestate, [], true)], true)
			], true);
		}
		elseif ($inquiry) {
				$message = $this->_lang->GET('record.inquiry_message_content', [
				':user' => $_SESSION['user']['name'],
				':identifier' => '<a href="javascript:void(0);" onclick="api.record(\'get\', \'record\', \'' . $identifier . '\')"> ' . $identifier . '</a>',
				':inquiry' => $inquiry
			], true);
		}

		if ((array_values((array)$this->_payload) || $recipient) && $this->alertUserGroup(['permission' => $permission, 'unit' => array_values((array)$this->_payload), 'user' => $recipient], $message)) $this->response([
			'response' => [
				'msg' => $this->_lang->GET('message.send_success'),
				'type' => 'success'
			]]);
		else $this->response([
			'response' => [
				'msg' => $this->_lang->GET('message.send_failure', [':number' => '']),
				'type' => 'error'
			]]);
	}

	 /**
	 *       _
	 *   ___| |___ ___ ___
	 *  |  _| | . |_ -| -_|
	 *  |___|_|___|___|___|
	 *
	 * close a record to hide it witin overview
	 * distinguishes between generic records or complaints and matches if the _passedIdentify type (e.g. supervisor) is allowed to close
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
			'date' => $this->_date['servertime']->format('Y-m-d H:i')
		];

		SQLQUERY::EXECUTE($this->_pdo, 'records_close', [
			'values' => [
				':closed' => UTILITY::json_encode($data['closed']),
				':identifier' => $this->_requestedID
			]
		]);
		$this->response([
			'response' => [
				'msg' => $this->_lang->GET('record.mark_as_closed_info'),
				'type' => 'success'
			]]);
	}

	/**
	 *     _                           _   
	 *   _| |___ ___ _ _ _____ ___ ___| |_ 
	 *  | . | . |  _| | |     | -_|   |  _|
	 *  |___|___|___|___|_|_|_|___|_|_|_|  
	 * 
	 * display any approved document and prepare for submitting a record
	 */
	public function document(){
		// prepare existing documents lists
		$document = $this->latestApprovedName('document_document_get_by_name', $this->_requestedID);
		if (!$document || $document['hidden'] || !PERMISSION::permissionIn($document['restricted_access']) || (!$document['patient_access'] && array_intersect(['patient'], $_SESSION['user']['permissions']))) $this->response(['response' => ['msg' => $this->_lang->GET('assemble.compose.document.document_not_found', [':name' => $this->_requestedID]), 'type' => 'error']]);

		$response = ['title' => $document['name'], 'render' => [
			'content' => []
		]];

		// prefill identify if passed, prepare calendar button and autocomplete if part of the document
		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
		$datalists = SQLQUERY::EXECUTE($this->_pdo, 'records_datalist_get', ['values' => [':unit' => $document['unit']]]);
		function setidentifier($element, $identify, $calendar, $_lang, $datalists){
			$content = [];
			foreach ($element as $subs){
				if (!isset($subs['type'])){
					$content[] = setidentifier($subs, $identify, $calendar, $_lang, $datalists);
				}
				else {
					if ($subs['type'] === 'identify'){
						$subs['attributes']['value'] = $identify;
					}
					if ($subs['type'] === 'calendarbutton'){
						$subs['attributes']['value'] = $_lang->GET('calendar.schedule.new');
						$subs['attributes']['onclick'] = $calendar->dialog([':type' => 'schedule']);
					}
					if (isset($subs['autocomplete'])){
						if (($index = array_search($subs['attributes']['name'], array_column($datalists, 'issue'))) !== false){
							if ($subs['type'] === 'text') {
								$subs['datalist'] = json_decode($datalists[$index]['datalist'], true);
								unset($subs['autocomplete']);
							}
							elseif  ($subs['type'] === 'textarea') {
								$subs['autocomplete'] = json_decode($datalists[$index]['datalist'], true);
							}
						}
						else unset($subs['autocomplete']);
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
							'filereference',
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
		foreach (explode(',', $document['content']) as $usedcomponent) {
			$component = $this->latestApprovedName('document_component_get_by_name', $usedcomponent);
			if ($component){
				$has_components = true;
				$component['content'] = json_decode($component['content'], true);
				array_push($response['render']['content'], ...setidentifier($component['content']['content'], $this->_passedIdentify, $calendar, $this->_lang, $datalists));
			}
		}
		if (!$has_components) array_push($response['render']['content'], [[
			'type' => 'textsection',
			'attributes' => [
				'class' => 'orange',
				'name' => $this->_lang->GET('assemble.render.error_no_approved_components', [':permission' => implode(', ', array_map(fn($v) => $this->_lang->_USER['permissions'][$v], PERMISSION::permissionFor('documentcomposer', true)))])
			]
		]]);

		// check if a submit button is applicable
		function saveable($element){
			$saveable = false;
			foreach ($element as $subs){
				if (!isset($subs['type'])){
					if ($saveable = saveable($subs)) return true;
				}
				else {
					if (!in_array($subs['type'], ['textsection', 'image', 'links', 'hidden', 'button'])) return true;
				}
			}
			return $saveable;
		}
		if (saveable($response['render']['content']) && !array_intersect(['group'], $_SESSION['user']['permissions'])) $response['render']['form'] = [
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

		if (isset($response['render']['form'])) {
			// add record timestamp options if this is a fillable document (and not a process instruction) 
			$record_date = [
				'type' => 'date',
				'attributes' => [
					'name' => 'DEFAULT_' . $this->_lang->GET('record.date'),
					'value' => $this->_date['usertime']->format('Y-m-d'),
					'required' => true
				]
			];
			$record_time = [
				'type' => 'time',
				'attributes' => [
					'name' => 'DEFAULT_' . $this->_lang->GET('record.time'),
					'value' => $this->_date['usertime']->format('H:i'),
					'required' => true
				]
			];
			if (array_intersect(['patient'], $_SESSION['user']['permissions'])) {
				$record_date['attributes']['readonly'] = $record_time['attributes']['readonly'] = true;
			}

			$defaults = [$record_date, $record_time];
			// add record types if applicable
			if (in_array($document['context'], ['casedocumentation'])) {
				$options = [];
				foreach ($this->_lang->_USER['record']['type'] as $key => $value){
					$options[$value] = CONFIG['application']['require_record_type_selection'] ? ['value' => $key, 'required' => true] : ['value' => $key];
				}
				$defaults[] = [
					'type' => 'radio',
					'attributes' => [
						'name' => 'DEFAULT_' . $this->_lang->GET('record.type_description')
					],
					'content' => $options
				];
			}
			$response['render']['content'][] = $defaults;
		}

		// add export options or notifictaion
		if (PERMISSION::permissionFor('documentexport') || ($document['permitted_export'] && !array_intersect(['patient'], $_SESSION['user']['permissions']))){
			if (isset($response['render']['form'])) {
				$export = [
					'type' => 'button',
					'hint' => $this->_lang->GET('assemble.render.export_hint'),
					'attributes' => [
						'type' => 'submit',
						'formnovalidate' => true,
						'value' => $this->_lang->GET('assemble.render.export'),
						'formaction' => "javascript:api.document('post', 'export')"
					]
				];
			}
			else {
				$export = [
					'type' => 'button',
					'attributes' => [
						'value' => $this->_lang->GET('assemble.render.export'),
						'onclick' => "const formdata = new FormData(); "
							. "formdata.append('_document_id', " . $document['id'] . "); "
							. "api.document('post', 'export', null, formdata);"
						]
				];
			} 

			$response['render']['content'][] = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('assemble.render.required_asterisk')
					]
				],
				$export
			];
		}
		else {
			$response['render']['content'][] = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('assemble.render.export_permission', [':permissions' => implode(', ', array_map(fn($v) => $this->_lang->_USER['permissions'][$v], PERMISSION::permissionFor('documentexport', true)))])
					]
				]
			];
		}
		if (isset($response['render']['content'][0][0]['type'])) array_push($response['render']['content'][0], ...$context); // append to first article within a section
		else array_push($response['render']['content'][0][0], ...$context);
		$this->response($response);
	}
	
	/**
	 *     _                           _                       _   
	 *   _| |___ ___ _ _ _____ ___ ___| |_ ___ _ _ ___ ___ ___| |_ 
	 *  | . | . |  _| | |     | -_|   |  _| -_|_'_| . | . |  _|  _|
	 *  |___|___|___|___|_|_|_|___|_|_|_| |___|_,_|  _|___|_| |_|  
	 *                                            |_| 
	 */
	public function documentexport(){
		$this->export('document');
	}
	
	/**
	 *                       _
	 *   ___ _ _ ___ ___ ___| |_
	 *  | -_|_'_| . | . |  _|  _|
	 *  |___|_,_|  _|___|_| |_|
	 *          |_|
	 * export records as pdf
	 * @param string $summarize full|document|simplified|simplifieddocument
	 * 
	 * @return response download link
	 */
	private function export($summarize = "full"){
		if (!PERMISSION::permissionFor('recordsexport')) $this->response([], 401);
		$content = $this->summarizeRecord($summarize, true);
		if (!$content) $this->response([], 404);
		$downloadfiles = [];
		$PDF = new PDF(CONFIG['pdf']['record']);
		$downloadfiles[$this->_lang->GET('menu.records.record_summary')] = [
			'href' => './api/api.php/file/stream/' . $PDF->recordsPDF($content)
		];

		$body = [];
		array_push($body, 
			[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]
		);
		$this->response([
			'render' => $body,
		]);
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
	 * displays a generic identifier label generator form or the download link
	 * in general all identifiers are appended a timestamp if not already present
	 */
	public function identifier(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if ($content = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('record.create_identifier'))) {
					$possibledate = substr($content, -16);
					try {
						new \DateTime($possibledate, new \DateTimeZone($this->_date['timezone']));
					}
					catch (\Exception $e){
						if ($this->_appendDate) $content .= ' ' . $this->_date['usertime']->format('Y-m-d H:i');
					}
				}
				if ($content){
					$downloadfiles = [];
					$PDF = new PDF(CONFIG['label'][UTILITY::propertySet($this->_payload, '_type') ? : 'sheet']);
					$content = [
						'title' => $this->_lang->GET('record.create_identifier', [], true),
						'content' => [$content, $content],
						'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $content)
					];
					$downloadfiles[$this->_lang->GET('record.create_identifier')] = [
						'href' => './api/api.php/file/stream/' . $PDF->qrcodePDF($content)
					];
					$body = [
						[
							'type' => 'links',
							'description' => $this->_lang->GET('record.create_identifier_proceed'),
							'content' => $downloadfiles
						]
					];
					$this->response([
						'render' => $body
					]);
				}
				else $this->response(['response' => [
					'msg' => $this->_lang->GET('record.create_identifier_error'),
					'type' => 'error'
				]]);
				break;
			case 'GET':
				$response = ['render' =>
				[
					'content' => [
						[
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $this->_lang->GET('record.create_identifier_info')
								]
							], [
								'type' => 'scanner',
								'hint' => $this->_lang->GET('record.create_identifier_hint'),
								'attributes' => [
									'name' => $this->_lang->GET('record.create_identifier'),
									'maxlength' => CONFIG['limits']['identifier'],
									'id' => '_identifier'
								]
							]
						]
					]
				]];
				// display available options according to CONFIG
				foreach (CONFIG['label'] as $type => $setting){
					$response['render']['content'][] = [
						'type' => 'button',
						'attributes' => [
							'onclick' => "_client.application.postLabelSheet(document.getElementById('_identifier').value, 'appendDate', {_type:'" . $type . "'});",
							'value' => $this->_lang->GET('record.create_identifier_type', [':format' => $setting['format']])
						]
					];
				}
				$this->response($response);
				break;
		}
	}
	
	/**
	 *   _                   _
	 *  |_|_____ ___ ___ ___| |_
	 *  | |     | . | . |  _|  _|
	 *  |_|_|_|_|  _|___|_| |_|
	 *          |_|
	 * gather data for a given identifier and return form field names and recorded values
	 * check if the respective form for a record is accessible
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
			foreach ($records as $record){
				$document = $documents[array_search($record['document'], array_column($documents, 'id'))] ? : ['name' => null, 'restricted_access' => null];
				if (!PERMISSION::permissionIn($document['restricted_access'])) continue; // check if user has access to form
				if ($record['document'] == 0) continue; // skip pseudoforms
				if (gettype($record['content']) === 'string') $record['content'] = json_decode($record['content'], true);
				foreach ($record['content'] as $key => $value){
					preg_match("/(?:^href=')(.+?)(?:')/", $value, $link); // link widget value
					if ($link) $value = $link[1];
					$result[$key] = $value;
				}
				$result['DEFAULT_' . $this->_lang->PROPERTY('record.type_description')] = $data['record_type'];
			} 
			$this->response([
				'data' => $result,
				'response' => [
					'msg' => $this->_lang->GET('record.import_success'),
					'type' => 'success'
				]
			]);
		}
		else $this->response([
			'response' => [
				'msg' => $this->_lang->GET('record.import_error'),
				'type' => 'error'
			]]);
	}
	
	/**
	 *   _     _           _                                 _
	 *  | |___| |_ ___ ___| |_ ___ ___ ___ ___ ___ _ _ ___ _| |___ ___ _____ ___
	 *  | | .'|  _| -_|_ -|  _| .'| . | . |  _| . | | | -_| . |   | .'|     | -_|
	 *  |_|__,|_| |___|___|_| |__,|  _|  _|_| |___|\_/|___|___|_|_|__,|_|_|_|___|
	 *                            |_| |_|
	 * returns the latest approved document, component by name from query if element is prior to requestedTimestamp
	 * @param string $query as defined within sqlinterface
	 * @param string $name
	 * @param string $requestedTimestamp Y-m-d H:i:s
	 * 
	 * @return array|bool either query row or false
	 */
	private function latestApprovedName($query = '', $name = '', $requestedTimestamp = null){
		// $requestedTimestamp is currently not in use as a parameter...
		$requestedTimestamp = $requestedTimestamp ? : $this->_date['servertime']->format('Y-m-d H:i:59');

		// get latest approved by name
		$element = [];
		$elements = SQLQUERY::EXECUTE($this->_pdo, $query, [
			'values' => [
				':name' => $name
			]
		]);
		foreach ($elements as $element){
			if (!$element['hidden'] && in_array($element['context'], ['bundle'])) return $element; // bundles have no approval
			if (PERMISSION::fullyapproved('documentapproval', $element['approval']) && //is approved
				PERMISSION::permissionIn($element['restricted_access']) && // user has permisson to restricted
				$element['date'] <= $requestedTimestamp) { // element is prior to requestedTimestamp
					$element['hidden'] = json_decode($element['hidden'] ? : '', true); 
					if (!$element['hidden'] || $element['hidden']['date'] > $requestedTimestamp) return $element;
					else return false;
				}
		}
		return false; // fallback
	}

	/**
	 *             _       _   _             _ _
	 *   _____ ___| |_ ___| |_| |_ _ _ ___ _| | |___ ___
	 *  |     | .'|  _|  _|   | . | | |   | . | | -_|_ -|
	 *  |_|_|_|__,|_| |___|_|_|___|___|_|_|___|_|___|___|
	 *
	 * return documents that have not been taken into account within a record
	 */
	public function matchbundles(){
		$documents = [];
		$response = [];

		// prepare existing bundle lists
		$bundle = $this->latestApprovedName('document_bundle_get_by_name', $this->_requestedID); // is bundle valid
		if (!$bundle) $bundle = ['content' => []];
		$necessarydocuments = $bundle['content'] ? explode(',', $bundle['content']) : []; // extract document names

		// unset hidden documents from bundle presets
		$alldocuments = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		foreach ($alldocuments as $row){
			if (!PERMISSION::fullyapproved('documentapproval', $row['approval']) || !PERMISSION::permissionIn($row['restricted_access'])) continue;
			if ($row['hidden'] && ($key = array_search($row['name'], $necessarydocuments)) !== false) unset($necessarydocuments[$key]);
		}
		// retrieve record
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
			'values' => [
				':identifier' => $this->_passedIdentify
			]
		]);
		$data = $data ? $data[0] : null;
		if (!$data) $this->response([], 404); // record not found

		// retrieve considered documents
		$considered = [];
		foreach (json_decode($data['content'], true) as $record){
			if (($documentIndex = array_search($record['document'], array_column($alldocuments, 'id'))) !== false)
				$considered[] = $alldocuments[$documentIndex]['name'];
		}
		// summarize required documents
		foreach (array_diff($necessarydocuments, $considered) as $needed){
			$documents[$needed] = ['href' => "javascript:api.record('get', 'document', '" . $needed . "', '" . $this->_passedIdentify . "')"];
		}

		if ($documents) $response['render'] = [
			[
				'type' => 'links',
				'description' => $this->_lang->GET('record.append_missing_document', [':bundle' => $this->_requestedID]),
				'content' => $documents
			]
		];
		else $response['render'] =[
			[
				'type' => 'textsection',
				'content' => $this->_lang->GET('record.append_missing_document_unneccessary'),
			]
		];
		$this->response($response);
	}

	/**
	 *                         _
	 *   ___ ___ ___ ___ ___ _| |
	 *  |  _| -_|  _| . |  _| . |
	 *  |_| |___|___|___|_| |___|
	 *
	 * post a record by document or display summary
	 */
	public function record(){

		// prepare available documents to control appending button or proof bundle entries valid
		$validDocuments = [];
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$hidden = [];
		foreach ($fd as $key => $row) {
			if (!PERMISSION::fullyapproved('documentapproval', $row['approval']) || !PERMISSION::permissionIn($row['restricted_access'])) continue;
			if ($row['hidden'] || in_array($row['context'], array_keys($this->_lang->_USER['documentcontext']['notdisplayedinrecords']))) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $validDocuments) && !in_array($row['name'], $hidden)) {
				$validDocuments[] = $row['name'];
			}
		}
		
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (array_intersect(['group'], $_SESSION['user']['permissions'])) $this->response([], 401);

				$context = $document_name = null;
				$identifier = '';
				if ($context = UTILITY::propertySet($this->_payload, '_context')) unset($this->_payload->_context);
				if ($document_name = UTILITY::propertySet($this->_payload, '_document_name')) unset($this->_payload->_document_name);
				// document id is stored to the entry so that the content remains hidden if the document has restricted access
				// used in summarizeRecord() and not easier to check with the name 
				if ($document_id = UTILITY::propertySet($this->_payload, '_document_id')) unset($this->_payload->_document_id);
				if ($entry_date = UTILITY::propertySet($this->_payload, 'DEFAULT_' . $this->_lang->PROPERTY('record.date'))) unset($this->_payload->{'DEFAULT_' . $this->_lang->PROPERTY('record.date')});
				if ($entry_time = UTILITY::propertySet($this->_payload, 'DEFAULT_' . $this->_lang->PROPERTY('record.time'))) unset($this->_payload->{'DEFAULT_' . $this->_lang->PROPERTY('record.time')});
				if ($record_type = UTILITY::propertySet($this->_payload, 'DEFAULT_' . $this->_lang->PROPERTY('record.type_description'))) unset($this->_payload->{'DEFAULT_' . $this->_lang->PROPERTY('record.type_description')});

				require_once('_shared.php');
				$documentfinder = new SHARED($this->_pdo, $this->_date);
				$useddocument = $documentfinder->recentdocument('document_get', [
					'values' => [
						':id' => $document_id
					]]);

				if (!$useddocument || (!$useddocument['patient_access'] && array_intersect(['patient'], $_SESSION['user']['permissions']))) $this->response(['response' => ['msg' => $this->_lang->GET('assemble.compose.document.document_not_found', [':name' => $this->_requestedID]), 'type' => 'error']]);

				$entry_timestamp = $entry_date . ' ' . $entry_time;
				if (strlen($entry_timestamp) > 16) { // yyyy-mm-dd hh:ii
					$entry_timestamp = $this->_date['usertime']->format('Y-m-d H:i');
				}

				// create proper identifier with timestamp if not provided
				// unset checkboxes while relying on a prepared additional dataset
				// unset empty values
				foreach ($this->_payload as $key => &$value){
					if (substr($key, 0, 12) === 'IDENTIFY_BY_'){
						$identifier = $value;
						if (gettype($identifier) !== 'string') $identifier = ''; // empty value is passed as array by frontend
						unset ($this->_payload->$key);
						$possibledate = substr($identifier, -16);
						try {
							new \DateTime($possibledate, new \DateTimeZone($this->_date['timezone']));
						}
						catch (\Exception $e){
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
				// check whether a necessary identifier has been submitted 
				if (!$identifier) {
					if (!in_array($context, array_keys($this->_lang->_USER['documentcontext']['identify']))) $identifier = $document_name . ' ' . $entry_timestamp;
					else $this->response([
						'response' => [
							'msg' => $this->_lang->GET('record.error'),
							'type' => 'error'
						]]);
				}
				$entry_timestamp .= ':00'; // append seconds for database format

				// handle attachments and images
				if (!file_exists(UTILITY::directory('record_attachments'))) mkdir(UTILITY::directory('record_attachments'), 0777, true);
				$attachments = [];
				foreach ($_FILES as $fileinput => $files){
					if ($uploaded = UTILITY::storeUploadedFiles([$fileinput], UTILITY::directory('record_attachments'), [preg_replace('/[^\w\d]/m', '', $identifier . '_' . $this->_date['servertime']->format('YmdHis') . '_' . $fileinput)], null, false)){
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
				foreach ($attachments as $input => $files){
					$this->_payload->$input = implode(', ', $files);
				}

				if (boolval((array) $this->_payload)){
					// update record datalists if passed document contains issues permitting autocompletion
					if ($useddocument){

						// recursive check for names that permit autocompletion
						function autocomplete($element){
							$content = [];
							foreach ($element as $subs){
								if (!isset($subs['type'])){
									$content = array_merge($content, autocomplete($subs));
								}
								else {
									if (isset($subs['autocomplete']) && isset($subs['attributes']['name'])) $content[] = $subs['attributes']['name'];
								}
							}
							return $content;
						};

						if ($issues = autocomplete($useddocument['content'])){
							$datalists = SQLQUERY::EXECUTE($this->_pdo, 'records_datalist_get', ['values' => [':unit' => $useddocument['unit']]]);
							foreach ($issues as $issue){
								// gather values even for enumerated submissions
								$values = [];
								foreach (array_keys((array) $this->_payload) as $key){
									preg_match('/' . preg_quote($issue, '/') . '(?:$|\()/m', $key, $matches);
									if ($matches) $values[] = $this->_payload->$key;
								}
								if (!$values) continue;
								// update or create datalist for issue
								if (($index = array_search($issue, array_column($datalists, 'issue'))) !== false){
									// issue found, update unique and sorted datalist
									$datalist = json_decode($datalists[$index]['datalist'], true);
									array_push($datalist, ...$values);
									$datalist = array_values(array_unique($datalist));
									sort($datalist);
									SQLQUERY::EXECUTE($this->_pdo, 'records_datalist_put', ['values' => [
										':issue' => $issue,
										':unit' => $useddocument['unit'],
										':datalist' => UTILITY::json_encode($datalist)
									]]);
								}
								else {
									// issue not found, add unique and sorted datalist
									$datalist = array_values(array_unique($values));
									sort($datalist);
									SQLQUERY::EXECUTE($this->_pdo, 'records_datalist_post', ['values' => [
										':issue' => $issue,
										':unit' => $useddocument['unit'],
										':datalist' => UTILITY::json_encode($datalist)
									]]);
								}
							}
						}
					}

					// set up record
					$current_record = [
						'author' => $_SESSION['user']['name'],
						'date' => $this->convertToServerTime($entry_timestamp),
						'document' => $document_id,
						'content' => UTILITY::json_encode($this->_payload)
					];
					
					$case = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
						'values' => [
							':identifier' => $identifier
						]
					]);
					$case = $case ? $case[0] : null;
					if ($case){ // update record
						$records = json_decode($case['content'], true);
						$records[] = $current_record; // append current record
						$success = SQLQUERY::EXECUTE($this->_pdo, 'records_put', [
							'values' => [
								':case_state' => $case['case_state'] ? : null,
								':record_type' => $case['record_type'] ? : null,
								':identifier' => $identifier,
								':last_user' => $_SESSION['user']['id'],
								':last_document' => $document_name,
								':content' => UTILITY::json_encode($records),
								':id' => $case['id']
							]
						]);
					}
					else { // create a new record
						$success = SQLQUERY::EXECUTE($this->_pdo, 'records_post', [
							'values' => [
								':context' => $context,
								':record_type' => $record_type ? : null,
								':identifier' => $identifier,
								':last_user' => $_SESSION['user']['id'],
								':last_document' => $document_name,
								':content' => UTILITY::json_encode([$current_record]),
							]
						]);
					}
					if ($success){		
						// append next document recommendation for common and matching user units
						$bd = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist');
						$hidden = $recommended = [];
						foreach ($bd as $key => $row) {
							if ($row['hidden'] || !(in_array($row['unit'], $_SESSION['user']['units']) || $row['unit'] === 'common')) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
							if (!in_array($row['name'], $hidden)) {
								$necessarydocuments = $row['content'] ? explode(',', $row['content']) : [];
								if ($necessarydocuments && ($documentindex = array_search($document_name, $necessarydocuments)) !== false) { // position of the current document within bundle
									if (isset($necessarydocuments[++$documentindex]) && in_array($necessarydocuments[$documentindex], $validDocuments)) { // the next document is set and valid
										$recommended[$necessarydocuments[$documentindex]] = ['href' => "javascript:api.record('get', 'document', '" . $necessarydocuments[$documentindex] . "', '" . $identifier . "')"];
									}
								}
							}
						}
						ksort($recommended);
						if ($recommended && !array_intersect(['patient'], $_SESSION['user']['permissions']))
							$this->response([
								'response' => [
									'msg' => $this->_lang->GET('record.saved'),
									'type' => 'success'
								],
								'render' => [
									'content' => [
										[
											'type' => 'links',
											'description' => $this->_lang->GET('record.recommended_continue'),
											'content' => $recommended
										]
									]
								]
							]);
						$this->response([
							'response' => [
								'msg' => $this->_lang->GET('record.saved'),
								'type' => 'success'
							],
						]);
					}
				}
				$this->response([
					'response' => [
						'msg' => $this->_lang->GET('record.error'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				if (array_intersect(['patient'], $_SESSION['user']['permissions'])) $this->response([], 401);
				$response = ['render' => []];
				$body = [];
				// summarize content
				$content = $this->summarizeRecord('full');
				if (!$content) $this->response([], 404);
				// display identifier
				$body[] = [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('record.create_identifier')
						],
						'content' => $this->_requestedID
					]
				];
				// option to export a sticky label according to CONFIG options
				foreach (CONFIG['label'] as $type => $setting){
					$body[count($body) -1][] = [
						'type' => 'button',
						'attributes' => [
							'onclick' => "_client.application.postLabelSheet('" . $this->_requestedID . "', null, {_type:'" . $type . "'});",
							'value' => $this->_lang->GET('record.create_identifier_type', [':format' => $setting['format']])
						]
					];
				}
				
				// set up case state and userAlert form 
				$messagedialog = [$this->_lang->GET('record.casestate_change_recipient_supervisor_only', [':supervisor' => $this->_lang->GET('permissions.supervisor')]) => []];
				foreach ($content['units'] as $unit){
					$messagedialog[$this->_lang->_USER['units'][$unit]] = ['value' => $unit];
				}
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
				$datalist = [];
				foreach ($user as $key => $row) {
					if (PERMISSION::filteredUser($row, ['id' => [1, $_SESSION['user']['id']], 'permission' => ['patient', 'group']])) continue;
					$datalist[] = $row['name'];
				}
				$notification_recipients = [
					[
						'type' => 'checkbox',
						'attributes' => [
							'name' => $this->_lang->GET('record.casestate_change_recipient_unit')
						],
						'content' => $messagedialog
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
							'value' => $content['context']
						]
					],
					[
						'type' => 'text',
						'attributes' => [
							'name' => $this->_lang->GET('record.casestate_change_recipient'),
						],
						'datalist' => $datalist
					]
				];
				if ($casestate = $this->casestate($content['context'], 'checkbox', ['onchange' => "api.record('put', 'casestate', '" . $this->_requestedID. "', this.dataset.casestate, this.checked);"
					. " new _client.Dialog({type: 'input', header: '" . $this->_lang->GET('record.casestate_change_message') . "', render: JSON.parse('"
 					. UTILITY::json_encode($notification_recipients)
					. "'), options: JSON.parse('"
					. UTILITY::json_encode([
						$this->_lang->GET('general.cancel_button') => false,
						$this->_lang->GET('general.submit_button') => ['value' => true, 'class' => 'reducedCTA']
					])
					."')}).then((response) => { if (response) { response.casestate = this.dataset.casestate; response.casestatestate = this.checked; api.record('post', 'casestatealert', null, _client.application.dialogToFormdata(response)); }});"
					], $content['case_state'])){
					$body[] = [$casestate];
				}
				// append general request button
				$body[count($body) - 1][] = [
					'type' => 'button',
					'attributes' => [
						'value' => $this->_lang->GET('record.inquiry'),
						'onclick' => "new _client.Dialog({type: 'input', header: '" . $this->_lang->GET('record.inquiry') . "', render: JSON.parse('"
							. UTILITY::json_encode([
								[
									'type' => 'textarea',
									'attributes' => [
										'name' => $this->_lang->GET('record.inquiry')
									]
								],...$notification_recipients
							])
							. "'), options: JSON.parse('"
							. UTILITY::json_encode([
								$this->_lang->GET('general.cancel_button') => false,
								$this->_lang->GET('general.submit_button') => ['value' => true, 'class' => 'reducedCTA']
							])
							."')}).then((response) => { if (response) { api.record('post', 'casestatealert', null, _client.application.dialogToFormdata(response)); }});"
					]
				];

				// define all considered document names
				$includedDocuments = array_keys($content['content']);

				// display contents of each document
				foreach ($content['content'] as $document => $entries){
					if ($document === $this->_lang->GET('record.altering_pseudodocument_name')) continue;
					$body[] = [
						[
							'type' => 'collapsible',
							'attributes' => [
								'class' => "em12"
							],
							'content' => [
								[
									'type' => 'textsection',
									'attributes' => [
										'name' => $document
									]
								]
							]
						]
					];
					foreach ($entries as $key => $value){
						array_push($body[count($body) -1][0]['content'],
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
							array_push($body[count($body) -1][0]['content'],
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
						array_push($body[count($body) -1][0]['content'],
						[
							'type' => 'links',
							'description' => $this->_lang->GET('record.file_attachments'),
							'content' => $content['files'][$document]
						]); 
					}
					if ($document != $this->_lang->GET('record.altering_pseudodocument_name')){
						// option to append to document entries
						if (in_array($document, $includedDocuments) && in_array($document, $validDocuments) && !array_intersect(['group'], $_SESSION['user']['permissions'])) array_push($body[count($body) -1],[
							'type' => 'button',
							'attributes' => [
								'title' => $this->_lang->GET('record.append_document'),
								'onclick' => "api.record('get', 'document', '" . $document . "', '" . $this->_requestedID . "')",
								'data-type' => 'additem',
								'class' => 'inlinebutton'
							]
						]);
						// option to export document specific record
						if (PERMISSION::permissionFor('recordsexport'))	array_push($body[count($body) -1],[
							'type' => 'button',
							'attributes' => [
								'title' => $this->_lang->GET('record.export'),
								'data-type' => 'download',
								'class' => 'inlinebutton',
								'onclick' => "new _client.Dialog({type: 'input', header: '". $this->_lang->GET('record.export') . "', render: [" . 
								"{type:'button', attributes:{value: api._lang.GET('record.full_export'), 'data-type': 'download', onclick: 'api.record(\'get\', \'documentexport\', \'" . $this->_requestedID . "\', \'" . $document . "\')'}},".
								"{type:'button', attributes:{value: api._lang.GET('record.simplified_export'), 'data-type': 'download', onclick: 'api.record(\'get\', \'simplifieddocumentexport\', \'" . $this->_requestedID . "\', \'" . $document . "\')'}}".
								"], options:{'" . $this->_lang->GET('general.cancel_button') . "': false}})"
							]
						]);
					}
				}
				// append document recommendations for common and matching user units
				// retrieve bundles
				$bd = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist');
				$hidden = $bundles = [];
				foreach ($bd as $key => $row) {
					if ($row['hidden'] || !((in_array($row['unit'], $content['units']) && array_intersect($_SESSION['user']['units'], $content['units'])) || $row['unit'] === 'common')) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (!in_array($row['name'], $hidden) && !isset($bundles[$row['name']]))
						$bundles[$row['name']] = $row['content'] ? explode(',', $row['content']) : [];
				}
				// process bundles
				foreach ($bundles as $bundle => $necessarydocuments){
					$recommendation = [];
					if (array_intersect($necessarydocuments, $includedDocuments)){
						foreach (array_diff($necessarydocuments, $includedDocuments) as $recommended){ // possible missing documents
							if (in_array($recommended, $validDocuments)) // document is permitted
							$recommendation[$recommended] = ['href' => "javascript:api.record('get', 'document', '" . $recommended . "', '" . $this->_requestedID . "')"];
						}
						if ($recommendation) $body[] = [[
							'type' => 'collapsible',
							'attributes' => [
								'class' => "em12"
							],
							'content' => [
								[
									'type' => 'links',
									'description' => $this->_lang->GET('record.append_missing_document', [':bundle' => $bundle]),
									'content' => $recommendation
								]
							]
						]];
					}
				}

				// append record_altering_pseudodocument
				if (isset($content['content'][$this->_lang->GET('record.altering_pseudodocument_name')])){
					$entries = $content['content'][$this->_lang->GET('record.altering_pseudodocument_name')];
					$body[] = [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('record.altering_pseudodocument_name')
							]
						]
					];
					foreach ($entries as $key => $value){
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
		
				$response['render']['content'] = $body;

				// add option for retyping if permitted
				if ($content['record_type']) {
					$typeaction = '';
					if (PERMISSION::permissionFor('recordsretyping')){
						$options = [];
						foreach ($this->_lang->_USER['record']['type'] as $record_type => $description){
							$options[$description] = ['value' => $record_type];
						}						
						$typeaction = "<a href=\"javascript:void(0);\" onclick=\"new _client.Dialog({type: 'input', header: '". $this->_lang->GET('record.retype_header', [':type' => $this->_lang->_USER['record']['type'][$content['record_type']]]) . "', render: JSON.parse('" . UTILITY::json_encode(
							[[
								'type' => 'radio',
								'attributes' => [
									'name' => 'DEFAULT_' . $this->_lang->GET('record.type_description')
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
						"'" . $this->_lang->GET('general.cancel_button') . "': false,".
						"'" . $this->_lang->GET('general.ok_button')  . "': {value: true, class: 'reducedCTA'},".
						"}}).then(response => { if (response) api.record('post', 'retype', null, _client.application.dialogToFormdata(response))})"
						. "\">" . $this->_lang->GET('record.retype_header', [':type' => $this->_lang->_USER['record']['type'][$content['record_type']]]) . '</a>';
					}
					$response['render']['content'][] = [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->_USER['record']['type'][$content['record_type']],
							],
							'linkedcontent' => $typeaction
						]
					];
					// add training button if type would possibly suggest one
					if (in_array($content['record_type'], ['complaint'])) {
						$response['render']['content'][count($response['render']['content']) - 1][] = [
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('user.training.add_training'),
								'onclick' => "api.user('get', 'training')"
							]
						];
					}

					$last_element = count($response['render']['content']) - 1;
				}
				else $last_element = count($response['render']['content']);

				// add option to reidentify record
				if (PERMISSION::permissionFor('recordsclosing')){
					// similar dialog on similarity check within reidentify method
					$response['render']['content'][$last_element][] = 
					[
						'type' => 'button',
						'attributes' => [
							'data-type' => 'merge',
							'value' => $this->_lang->GET('record.reidentify'),
							'onclick' => "new _client.Dialog({type: 'input', header: '". $this->_lang->GET('record.reidentify') . "', render: JSON.parse('" . UTILITY::json_encode(
								[
									[
										'type' => 'scanner',
										'hint' => $this->_lang->GET('record.create_identifier_hint'),
										'attributes' => [
											'name' => $this->_lang->GET('record.create_identifier'),
											'maxlength' => CONFIG['limits']['identifier']
										]
									],
									[
										'type' => 'checkbox',
										'content' => [
											$this->_lang->GET('record.reidentify_confirm') => ['required' => true] 
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
							"'), options:{'" . $this->_lang->GET('general.cancel_button') . "': false,".
							"'" . $this->_lang->GET('general.ok_button')  . "': {value: true, class: 'reducedCTA'},".
							"}}).then(response => { if (response) api.record('post', 'reidentify', null, _client.application.dialogToFormdata(response))})"
						]
					];
				}
				else $response['render']['content'][$last_element][] = [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('record.reidentify_unauthorized_name')
						],
						'content' => $this->_lang->GET('record.reidentify_unauthorized_content', [':permissions' => implode(', ', array_map(fn($v) => $this->_lang->_USER['permissions'][$v], PERMISSION::permissionFor('recordsclosing', true)))])
					]
				];

				// display general bundle matching options
				if (!array_intersect(['group'], $_SESSION['user']['permissions'])){
					$last_element = count($response['render']['content'])-1;
					// simple groups are not allowed to append to document
					$bundles = ['...' . $this->_lang->GET('record.match_bundles_default') => ['value' => '0']];
					// match against bundles
					// prepare existing bundle lists, not reusable forom above because all bundles are supposed to be displayed
					$bd = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist');
					$hidden = [];
					foreach ($bd as $key => $row) {
						if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
						if (!in_array($row['name'], $bundles) && !in_array($row['name'], $hidden)) {
							$bundles[$row['name']] = ['value' => $row['name']];
						}
					}
					$response['render']['content'][$last_element][] = 
						[
							'type' => 'select',
							'attributes' => [
								'name' => $this->_lang->GET('record.match_bundles'),
								'onchange' => "if (this.value != '0') api.record('get', 'matchbundles', this.value, '" . $this->_requestedID . "')"
							],
							'hint' => $this->_lang->GET('record.match_bundles_hint'),
							'content' => $bundles
						];
					
					// option to export records if permitted
					if (PERMISSION::permissionFor('recordsexport'))
						array_push ($response['render']['content'][$last_element], [
							[
								'type' => 'button',
								'attributes' => [
									'title' => $this->_lang->GET('record.export'),
									'value' => $this->_lang->GET('record.export'),
									'data-type' => 'download',
									'onclick' => "new _client.Dialog({type: 'input', header: '". $this->_lang->GET('record.export') . "', render: [" . 
									"{type:'button', attributes:{value: api._lang.GET('record.full_export'), 'data-type': 'download', onclick: 'api.record(\'get\', \'fullexport\', \'" . $this->_requestedID . "\')'}},".
									"{type:'button', attributes:{value: api._lang.GET('record.simplified_export'), 'data-type': 'download', onclick: 'api.record(\'get\', \'simplifiedexport\', \'" . $this->_requestedID . "\')'}}".
									"], options:{'" . $this->_lang->GET('general.cancel_button') . "': false}})"
								]
							]
						]);

					// notify if record is closed
					$content['closed'] = $content['closed'] !== null ? json_decode($content['closed'], true) : [];
					$approvalposition = [];
					foreach ($content['closed'] as $role => $property){
						array_splice($response['render']['content'][$last_element], 1, 0, [[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('record.closed', [':role' => $this->_lang->GET('permissions.' . $role), ':name' => $property['name'], ':date' => $this->convertFromServerTime($property['date'])])
							]
						]]);
					}

					// append option to close record
					if ($content['record_type'] === 'complaint' && PERMISSION::permissionFor('complaintclosing')){
						foreach (PERMISSION::pending('complaintclosing', $content['closed']) as $position){
							$approvalposition[$this->_lang->GET('permissions.' . $position)] = [
								'value' => $position,
								'onchange' => "if (this.checked) new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('record.mark_as_closed') ." ' + this.name, render: '" . $this->_lang->GET('record.complaint_mark_as_closed_info') . "', options:{".
								"'" . $this->_lang->GET('general.cancel_button') . "': false,".
								"'" . $this->_lang->GET('record.mark_as_closed') . ' ' . $this->_lang->GET('permissions.' . $position) ."': {value: true, class: 'reducedCTA'},".
								"}}).then(confirmation => {if (confirmation) {this.disabled = true; api.record('put', 'close', '" . $this->_requestedID . "', this.value);} else this.checked = false;})"
							];
						}
					}
					elseif (!$content['closed'] && PERMISSION::permissionFor('recordsclosing')) {
						foreach (PERMISSION::pending('recordsclosing', $content['closed']) as $position){
							$approvalposition[$this->_lang->GET('permissions.' . $position)] = [
								'value' => $position,
								'onchange' => "if (this.checked) new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('record.mark_as_closed') ." ' + this.name, render: '" . $this->_lang->GET('record.mark_as_closed_info') . "', options:{".
								"'" . $this->_lang->GET('general.cancel_button') . "': false,".
								"'" . $this->_lang->GET('record.mark_as_closed') . ' ' . $this->_lang->GET('permissions.' . $position) . "': {value: true, class: 'reducedCTA'},".
								"}}).then(confirmation => {if (confirmation) {this.disabled = true; api.record('put', 'close', '" . $this->_requestedID . "', this.value);} else this.checked = false;})"
							];
						}
					}
					if ($approvalposition){
						array_splice($response['render']['content'][$last_element], 1, 0, [[
							'type' => 'checkbox',
							'content' => $approvalposition,
							'attributes' => [
								'name' => $this->_lang->GET('record.mark_as_closed')
							]
						]]);
					}
				}
				$this->response($response);
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
	 * display records overview
	 */
	public function records(){
		$response = ['render' => ['content' => []]];
		$this->_requestedID = $this->_requestedID === 'null' ? null : $this->_requestedID;
		// get all records or these fitting the search
		require_once('_shared.php');
		$search = new SHARED($this->_pdo, $this->_date);
		$data = $search->recordsearch(['search' => $this->_requestedID]);

		// prepare datalists, display values, available units to select and styling
		$recorddatalist = $contexts = $available_units = [];
		foreach ($data as $contextkey => $context){
			foreach ($context as $record){
				// prefilter record datalist for performance reasons
				preg_match('/' . CONFIG['likeliness']['records_identifier_pattern']. '/mi', $record['identifier'], $simplified_identifier);
				if ($simplified_identifier && !in_array($simplified_identifier[0], $recorddatalist)) $recorddatalist[] = $simplified_identifier[0];
				
				// append units of available records 
				if ($record['units']) array_push($available_units, ...$record['units']);
				else $available_units[] = null;

				// filter results by selected unit
				if (!$this->_requestedID && (
					(!$this->_unit && !array_intersect($record['units'], $_SESSION['user']['units']))
					|| ($this->_unit === '_unassigned' && $record['units'])
					|| ($this->_unit && $this->_unit !== '_unassigned' && (!$record['units'] || !in_array($this->_unit, $record['units'])))
				)) continue;

				// create entry
				$tile = [
					'type' => 'tile',
					'attributes' => [
						'onclick' => "api.record('get', 'record', '" . $record['identifier'] . "')",
						'onkeydown' => "if (event.key==='Enter') api.record('get', 'record', '" . $record['identifier'] . "')",
						'role' => 'link',
						'tabindex' => '0'
					],
					'content' => [
						[
							'type' => 'textsection',
							'content' => $this->_lang->GET('record.list_touched', [
								':date' => $this->convertFromServerTime($record['last_touch']),
								':document' => $record['last_document']
							]),
							'attributes' => [
								'data-type' => 'record',
								'name' => $record['identifier']
							]
						]
					]
				];
				// append dataset states
				foreach ($record['case_state'] as $case => $state){
					$tile['attributes']['data-' . $case] = $state;
				}
				// style closed and complaints
				if ($record['complaint']) $tile['content'][0]['attributes']['class'] = 'orange';
				if ($record['closed']) $tile['content'][0]['attributes']['class'] = 'green';

				// add to result
				$contexts[$contextkey][] = $tile;
			}
		}

		// append selection of records per unit
		$organizational_units = [];
		$available_units = array_unique($available_units);
		sort($available_units);
		$assignable = true;
		$organizational_units[$this->_lang->GET('record.mine')] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'onchange' => "api.record('get', 'records', encodeURIComponent(document.getElementById('_recordfilter').value) || 'null')"];
		if (!$this->_unit) $organizational_units[$this->_lang->GET('record.mine')]['checked'] = true;
		foreach ($available_units as $unit){
			if (!$unit) {
				$assignable = false;
				continue;
			}
			$organizational_units[$this->_lang->_USER['units'][$unit]] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'onchange' => "api.record('get', 'records', encodeURIComponent(document.getElementById('_recordfilter').value) || 'null', '" . $unit . "')"];
			if ($this->_unit === $unit) $organizational_units[$this->_lang->_USER['units'][$unit]]['checked'] = true;
		}
		if (!$assignable) {
			$organizational_units[$this->_lang->GET('record.unassigned')] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'onchange' => "api.record('get', 'records', encodeURIComponent(document.getElementById('_recordfilter').value) || 'null', '_unassigned')"];
			if ($this->_unit === '_unassigned') $organizational_units[$this->_lang->GET('record.unassigned')]['checked'] = true;
		}

		$content = [
			[
				[
					'type' => 'scanner',
					'destination' => '_recordfilter',
					'description' => $this->_lang->GET('record.scan')
				], [
					'type' => 'filtered',
					'hint' => $this->_lang->GET('record.filter_hint', [':max' => CONFIG['limits']['max_records']]),
					'attributes' => [
						'id' => '_recordfilter',
						'name' => $this->_lang->GET('record.filter'),
						'onkeydown' => "if (event.key === 'Enter') {api.record('get', 'records', encodeURIComponent(this.value));}",
						'value' => ($this->_requestedID && $this->_requestedID !== 'null') ? $this->_requestedID : ''
					],
					'datalist' => array_values(array_unique($recorddatalist))
				],
				[
					'type' => 'radio',
					'attributes' => [
						'name' => $this->_lang->GET('order.organizational_unit')
					],
					'content' => $organizational_units,
					'hint' => $this->_lang->GET('record.assign_hint')
				]
			]
		];
		// append records
		if ($contexts){
			foreach ($contexts as $context => $tiles){
				if ($tiles){
					if ($casestate = $this->casestate(explode('.', $context)[1], 'radio', ['onchange' => "_client.record.casestatefilter(this.dataset.casestate)"], isset($_SESSION['user']['app_settings']['primaryRecordState']) ? $_SESSION['user']['app_settings']['primaryRecordState'] : null))
					array_push($content, $casestate);

					$article = [];
					array_push($article, [
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('documentcontext.' . $context)
						]
					]);
					array_push($article, ...$tiles);
					array_push($content, $article);
				}
				else array_push($content, $this->noContentAvailable($this->_lang->GET('record.no_records')));
			}
		}
		else array_push($content, ...$this->noContentAvailable($this->_lang->GET('record.no_records')));

		$response['render']['content'] = $content;
		$this->response($response);		
	}

	/**
	 *           _   _         _   _ ___     
	 *   ___ ___|_|_| |___ ___| |_|_|  _|_ _ 
	 *  |  _| -_| | . | -_|   |  _| |  _| | |
	 *  |_| |___|_|___|___|_|_|_| |_|_| |_  |
	 *                                  |___|
	 * set up a new identifier for a record or merge with an existing one
	 */
	public function reidentify(){
		if (!PERMISSION::permissionFor('recordsclosing')) $this->response([], 401);
		$entry_id = UTILITY::propertySet($this->_payload, '_previousIdentifier');
		$new_id = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('record.create_identifier'));
		$confirmation = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('record.reidentify_confirm'));
		$thresholdconfirmation = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('record.reidentify_similarity_confirm'));
		if (!($entry_id && $new_id && $confirmation)) $this->response([], 406);

		// append timestamp to new id if applicable
		$possibledate = substr($new_id, -16);
		try {
			new \DateTime($possibledate, new \DateTimeZone($this->_date['timezone']));
		}
		catch (\Exception $e){
			$new_id .= ' ' . $this->_date['usertime']->format('Y-m-d H:i');
		}

		// compare identifiers, warn if similarity is too low
		// strip dates for comparison
		$similar_new_id = substr($new_id, 0, -16);
		$possibledate = substr($entry_id, -16);
		try {
			new \DateTime($possibledate, new \DateTimeZone($this->_date['timezone']));
			$similar_entry_id = substr($entry_id, 0, -16);
		}
		catch (\Exception $e){
			$similar_entry_id = $entry_id;
		}
		similar_text($similar_new_id, $similar_entry_id, $percent);
		if (!$thresholdconfirmation && $percent < CONFIG['likeliness']['record_reidentify_similarity']) {
				// similar dialog on reidentify button within record method
				$response = ['render' => ['content' => [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('record.reidentify_warning', [':percent' => CONFIG['likeliness']['record_reidentify_similarity']])
						],
						'content' => $entry_id . " \n-> " . $new_id
					],
					[
						'type' => 'button',
						'attributes' => [
							'data-type' => 'merge',
							'value' => $this->_lang->GET('record.reidentify'),
							'onclick' => "new _client.Dialog({type: 'input', header: '". $this->_lang->GET('record.reidentify') . "', render: JSON.parse('" . UTILITY::json_encode(
								[
									[
										'type' => 'scanner',
										'hint' => $this->_lang->GET('record.create_identifier_hint'),
										'attributes' => [
											'name' => $this->_lang->GET('record.create_identifier'),
											'maxlength' => CONFIG['limits']['identifier'],
											'value' => $new_id
										]
									],
									[
										'type' => 'checkbox',
										'content' => [
											$this->_lang->GET('record.reidentify_confirm') => ['required' => true],
											$this->_lang->GET('record.reidentify_similarity_confirm') => ['required' => true]
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
							"'), options:{'" . $this->_lang->GET('general.cancel_button') . "': false,".
							"'" . $this->_lang->GET('general.ok_button')  . "': {value: true, class: 'reducedCTA'},".
							"}}).then(response => { if (response) api.record('post', 'reidentify', null, _client.application.dialogToFormdata(response))})"
						]
					]
				]]];
				$this->response($response);
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
			'date' => $this->_date['servertime']->format('Y-m-d H:i:s'),
			'document' => 0,
			'content' => [
				$this->_lang->GET('record.reidentify_pseudodocument_name', [], true) => ($original ? $this->_lang->GET('record.reidentify_merge_content', [':identifier' => $entry_id], true) : $this->_lang->GET('record.reidentify_identify_content', [':identifier' => $entry_id], true))
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
					':content' => UTILITY::json_encode($merge['content']),
					':id' => $merge['id']
			]])) $this->response([
				'response' => [
					'msg' => $this->_lang->GET('record.reidentify_success'),
					'type' => 'success'
				]]);
		}
		else {
			// append merged content to new identifier
			$original['content'] = json_decode($original['content'], true);
			foreach ($merge['content'] as $record){
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
					':last_document' => $original['content'][count($original['content']) - 2]['document'] ? : $this->_lang->GET('record.retype_pseudodocument_name', [], true),
					':content' => UTILITY::json_encode($original['content']),
					':id' => $original['id']
			]]) && SQLQUERY::EXECUTE($this->_pdo, 'records_delete', [
				'values' => [
					':id' => $merge['id']
			]])) $this->response([
				'response' => [
					'msg' => $this->_lang->GET('record.reidentify_merged'),
					'type' => 'success'
				]]);
		}
		$this->response([
			'response' => [
				'msg' => $this->_lang->GET('record.reidentify_failure'),
				'type' => 'error'
			]]);		
	}

	/**
	 *           _
	 *   ___ ___| |_ _ _ ___ ___
	 *  |  _| -_|  _| | | . | -_|
	 *  |_| |___|_| |_  |  _|___|
	 *              |___|_|
	 * set another record type
	 */
	public function retype(){
		if (!PERMISSION::permissionFor('recordsretyping')) $this->response([], 401);
		$entry_id = UTILITY::propertySet($this->_payload, 'entry_id');
		$record_type = UTILITY::propertySet($this->_payload, 'DEFAULT_' . $this->_lang->PROPERTY('record.type_description'));

		$original = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
			'values' => [
				':identifier' => $entry_id
			]
		]);
		$original = $original ? $original[0] : null;
		if ($original && $record_type){
			// set up record-altering record if record is found and new type is provided
			$original['content'] = json_decode($original['content'], true);
			$original['content'][] = [
				'author' => $_SESSION['user']['name'],
				'date' => $this->_date['servertime']->format('Y-m-d H:i'),
				'document' => 0,
				'content' => [
					$this->_lang->GET('record.retype_pseudodocument_name', [], true) => $this->_lang->GET('record.retype_content', [
					':previoustype' => $this->_lang->GET('record.type.' . $original['record_type'], [], true),
					':newtype' => $this->_lang->GET('record.type.' . $record_type, [], true)
					], true)]
			];
			// update record
			if (SQLQUERY::EXECUTE($this->_pdo, 'records_put', [
				'values' => [
					':case_state' => $original['case_state'] ? : null,
					':record_type' => $record_type,
					':identifier' => $original['identifier'],
					':last_user' => $_SESSION['user']['id'],
					':last_document' => $original['last_document'],
					':content' => UTILITY::json_encode($original['content']),
					':id' => $original['id']
			]])) $this->response([
				'response' => [
					'msg' => $this->_lang->GET('record.saved'),
					'type' => 'success'
				]]);
		}
		$this->response([
			'response' => [
				'msg' => $this->_lang->GET('record.error'),
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
	 *       _           _     ___ _       _ ___                                   _   
	 *   ___|_|_____ ___| |_ _|  _|_|___ _| |  _|___ ___ _____ ___ _ _ ___ ___ ___| |_ 
	 *  |_ -| |     | . | | | |  _| | -_| . |  _| . |  _|     | -_|_'_| . | . |  _|  _|
	 *  |___|_|_|_|_|  _|_|_  |_| |_|___|___|_| |___|_| |_|_|_|___|_,_|  _|___|_| |_|  
	 *              |_|   |___|                                       |_|              
	 */
	public function simplifieddocumentexport(){
		$this->export('simplifieddocument');
	}

	/**
	 *                               _                               _
	 *   ___ _ _ _____ _____ ___ ___|_|___ ___ ___ ___ ___ ___ ___ _| |
	 *  |_ -| | |     |     | .'|  _| |- _| -_|  _| -_|  _| . |  _| . |
	 *  |___|___|_|_|_|_|_|_|__,|_| |_|___|___|_| |___|___|___|_| |___|
	 *
	 * @param str $type full, simplified, document
	 * @param bool $retype based on view and permission link to retype or not
	 * @param bool $export if summary is about to be exported to a pdf
	 * 
	 * @return array $summary
	 */

	private function summarizeRecord($type = 'full', $export = false){
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_identifier', [
			'values' => [
				':identifier' => $this->_requestedID
			]
		]);
		$data = $data ? $data[0] : null;
		if (!$data) return false;
		//set up summary
		$summary = [
			'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $this->_requestedID . '_' . $this->_date['usertime']->format('Y-m-d H:i')),
			'identifier' => $this->_requestedID,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('menu.records.record_summary', [], $export),
			'date' => $this->convertFromServerTime($this->_date['usertime']->format('Y-m-d H:i'), $export),
			'closed' => $data['closed'],
			'record_type' => $data['record_type'],
			'units' => $data['units'] ? explode(',', $data['units']) : [],
			'context' => $data['context'],
			'case_state' => $data['case_state']
		];
		$accumulatedcontent = [];

		$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');

		$records = json_decode($data['content'], true);
		foreach ($records as $record){
			// check whether the record is within a valid and accessible document
			$document = $documents[array_search($record['document'], array_column($documents, 'id'))] ? : ['name' => null, 'restricted_access' => null];
			if (!PERMISSION::permissionIn($document['restricted_access'])) continue;
			if (in_array($type, ['document', 'simplifieddocument']) && ($document['name'] != $this->_documentExport)) continue; 
			if ($record['document'] == 0) { // retype and casestate pseudodocument
				if (in_array($type, ['simplified', 'simplifieddocument'])) continue;
				$useddocument = $this->_lang->GET('record.altering_pseudodocument_name', [], $export);
			}
			else $useddocument = $document['name'];

			// initiate and populate documentwise content
			if (!isset($accumulatedcontent[$useddocument])) $accumulatedcontent[$useddocument] = ['last_record' => null, 'content' => []];
			if (gettype($record['content']) === 'string') $record['content'] = json_decode($record['content'], true);
			foreach ($record['content'] as $key => $value){
				$value = str_replace(' | ', "\n\n", $value); // part up multiple selected checkbox options
				$value = str_replace('\n', "\n", $value); // format linebreaks
				preg_match("/(?:^href=')(.+?)(?:')/", $value, $link); // link widget value
				if ($link && !$export){
					$value = '<a href="javascript:void(0);" onclick="event.preventDefault(); window.open(\'' . $link[1] . '\', \'_blank\').focus();">' . $link[1] . "</a>";
				}
				if (!isset($accumulatedcontent[$useddocument]['content'][$key])) $accumulatedcontent[$useddocument]['content'][$key] = [];
				$accumulatedcontent[$useddocument]['content'][$key][] = ['value' => $value, 'author' => $this->_lang->GET('record.export_author', [':author' => $record['author'], ':date' => $this->convertFromServerTime(substr($record['date'], 0, -3), $export)], $export)];
				if (!$accumulatedcontent[$useddocument]['last_record'] || $accumulatedcontent[$useddocument]['last_record'] > $record['date']) $accumulatedcontent[$useddocument]['last_record'] = $this->convertFromServerTime($record['date'], $export);
			}
		}

		ksort($accumulatedcontent);
		foreach ($accumulatedcontent as $document => $entries){
			$summary['content'][$document] = [];
			foreach ($entries['content'] as $key => $data){
				$summary['content'][$document][$key] = '';
				$value = '';
				foreach ($data as $entry){
					if ($entry['value'] !== $value){
						$displayvalue = $entry['value'];
						// populate file image and attachments based on values containing respective paths and extensions
						// guess file url; special regex delimiter
						if (stripos($entry['value'], substr(UTILITY::directory('record_attachments'), 1)) !== false) {
							$file = pathinfo($entry['value']);
							if (in_array($file['extension'], ['jpg', 'jpeg', 'gif', 'png'])) {
								if (!isset($summary['images'][$document])) $summary['images'][$document] = [];
								$summary['images'][$document][] = $entry['value'];
							}
							else {
								if (!isset($summary['files'][$document])) $summary['files'][$document] = [];
								$summary['files'][$document][$file['basename']] = ['href' => './api/api.php/file/stream/' . $entry['value']];
							}
							$displayvalue = $file['basename'];
						}
						// modify displayed value based on requested type
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
			$documentfinder = new SHARED($this->_pdo, $this->_date);

			function enumerate($name, $enumerate = [], $number = 1){
				if (isset($enumerate[$name])) $enumerate[$name] += $number;
				else $enumerate[$name] = $number;	
				return $enumerate;
			}
	
			/**
			 * recursive content setting according to the most recent document
			 * @param array $element component and subsets
			 * @param array $payload
			 * @param object $_lang $this->_lang can not be referred within the function and has to be passed
			 * @param array $enumerate names of elements that have to be enumerated
			 * 
			 * also see document.php export()
			 */
			function printable($element, $payload, $type, $enumerate = []){
				$content = ['content' => []];
				foreach ($element as $subs){
					if (!isset($subs['type'])){
						$subcontent = printable($subs, $payload, $type, $enumerate);
						foreach ($subcontent['enumerate'] as $name => $number){
							$enumerate = enumerate($name, $enumerate,  $number); // add from recursive call
						}
						$content['content'] = array_merge($content['content'], $subcontent['content']);
					}
					else {
						if (in_array($subs['type'], ['identify', 'documentbutton', 'calendarbutton', 'hr'])) continue;
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
						foreach ($dynamicMultiples as $submitted){
							$value = $payload[$submitted];
							$content['content'][$submitted] = $value;
						}
					}
				}
				$content['enumerate'] = $enumerate;
				return $content;
			};

			$printablecontent = $enumerate = [];
			foreach ($summary['content'] as $document => $content){
				if ($useddocument = $documentfinder->recentdocument('document_document_get_by_name', [
					'values' => [
						':name' => $document
					]], $accumulatedcontent[$document]['last_record'])) $printablecontent[$document] = printable($useddocument['content'], $content, $type, $enumerate)['content'];
				else $printablecontent[$document] = $content; // pseudodocument
			}
			$summary['content'] = $printablecontent;
			if ($type === 'simplifieddocument'){
				// convert summary contents to a simpler view. this allows document formatting suitable to hand over to patients/customers, e.g. a manual with the latest record entries
				$summary['content'] = [' ' => $printablecontent[$useddocument['name']]];
				$summary['date'] = $this->convertFromServerTime($this->_date['usertime']->format('Y-m-d H:i'), $export);
				$summary['title'] = $useddocument['name'];
				$summary['images'] = [' ' => isset($summary['images'][$useddocument['name']]) ? $summary['images'][$useddocument['name']] : []];
			}
		}
		return $summary;
	}
}
?>
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

// audit overview and export
require_once('./_pdf.php');
require_once("../libraries/xlsxwriter.class.php");
require_once('./_calendarutility.php');

class AUDIT extends API {
	/**
	 * in case you want to create a code from any values in any form add 
	 * 'data-usecase' => 'tool_create_code'
	 * to inputs attributes (hidden if applicable)
	 */

	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedType = null;
	private $_requestedTemplate = null;
	private $_requestedDate = null;
	private $_requestedID = null;
	private $_requestedOption = null;
	private $_requestedTime = null;

	/**
	 * init parent class and set private requests
	 */
	public function __construct(){
		parent::__construct();
		if (!PERMISSION::permissionFor('regulatory') || array_intersect(['patient'], $_SESSION['user']['permissions'])) $this->response([], 401);

		$this->_requestedType = $this->_requestedTemplate = isset(REQUEST[2]) ? REQUEST[2] : null;
		$this->_requestedDate = $this->_requestedID = $this->_requestedOption = isset(REQUEST[3]) ? REQUEST[3] : null;
		$this->_requestedTime = isset(REQUEST[4]) ? REQUEST[4] : null;
	}

	/**
	 *             _ _ _   
	 *   ___ _ _ _| |_| |_ 
	 *  | .'| | | . | |  _|
	 *  |__,|___|___|_|_|  
	 *
	 * 
	 */
	public function audit(){
		if (!PERMISSION::permissionFor('audit')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$template = SQLQUERY::EXECUTE($this->_pdo, 'audit_get_template', ['values' => [':id' => $this->_requestedTemplate]]);
				$template = $template ? $template[0] : null;
				if (!$template) $this->response(['msg' => $this->_lang->GET('audit.audit.template.not_found'), 'type' => 'error'], 404);

				// set up general properties
				$audit = [
					':template' => $template['id'],
					':unit' => $template['unit'],
					':content' => [],
					':last_user' => $_SESSION['user']['name'],
					':closed' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('audit.audit.execute.close')) ? 1 : null
				];
				unset($this->_payload->{$this->_lang->PROPERTY('audit.audit.execute.close')});
				$audit[':content'] = [
					'objectives' => $template['objectives'],
					'method' => $template['method'],
					'summary' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('audit.audit.execute.summary')) ? : null,
					'questions' => []
				];
				unset($this->_payload->{$this->_lang->PROPERTY('audit.audit.execute.summary')});

				// process content
				// process files
				foreach ($_FILES as $fileinput => $files){
					if ($uploaded = UTILITY::storeUploadedFiles([$fileinput], UTILITY::directory('audit_attachments'), [preg_replace('/[^\w\d]/m', '', $this->_date['servertime']->format('YmdHis') . '_' . $template['unit'])], null, true)){
						for($i = 0; $i < count($files['name']); $i++){
							if (in_array(strtolower(pathinfo($uploaded[$i])['extension']), ['jpg', 'jpeg', 'gif', 'png'])) UTILITY::alterImage($uploaded[$i], CONFIG['limits']['record_image'], UTILITY_IMAGE_REPLACE);
							preg_match('/^(\d+):_(.+?)(?:\((\d+)\)|$)/m', $fileinput, $set); // get current question set information: [1] setindex, [2] input, isset [3] possible multiple field
							if (isset($audit[':content']['questions'][intval($set[1])]['files'])) $audit[':content']['questions'][intval($set[1])]['files'][] = substr($uploaded[$i], 1);
							else $audit[':content']['questions'][intval($set[1])]['files'] = [substr($uploaded[$i], 1)];
						}
					}
				}

				// iterate over payload, match template question index, input name and possible multiples
				// values always will be stored within an array to handle multiples by default
				foreach ($this->_payload as $key => $value){
					if ($key === 'null') continue;
					if (!$value) $value = ''; // the audit has to contain all questions as planned
					preg_match('/^(\d+):_(.+?)(?:\((\d+)\)|$)/m', $key, $set); // get current question set information: [1] setindex, [2] input, isset [3] possible multiple field
					$set[2] = str_replace('_', ' ', $set[2]);
					if ($input = array_search($set[2], $this->_lang->_USER['audit']['audit']['execute']))
						// translateable system fields
						$audit[':content']['questions'][intval($set[1])][$input][isset($set[3]) ? $set[3] - 1 : 0] = $value;
					else
						// manual human template question
						$audit[':content']['questions'][intval($set[1])][$set[2]][0] = $value;
				}

				$audit[':content'] = UTILITY::json_encode($audit[':content']);

				if (SQLQUERY::EXECUTE($this->_pdo, 'audit_and_management_post', [
					'values' => $audit
				])) {
					if ($audit[':closed']){
						$audit[':content'] = json_decode($audit[':content'], true);
						$summary = $this->_lang->GET('audit.checks_type.audits', [], true) . ' - ' . $this->_lang->_DEFAULT['units'][$audit[':unit']] . "\n \n";
						$summary .= $audit[':last_user'] . "\n";
						$summary .= $this->_lang->GET('audit.audit.objectives', [], true) . ': '. $audit[':content']['objectives'];
						foreach ($audit[':content']['questions'] as $question){
							// start with  question and direct response as initial value
							foreach ($question as $key => $values){
								if (in_array($key, array_keys($this->_lang->_DEFAULT['audit']['audit']['execute']))) continue;
								$summary .= "\n \n" .$key . ': ' . implode("\n", $values) . "\n";
								break;
							}
							// assign question response as value
							foreach ($question as $key => $values){
								if (in_array($key, array_keys($this->_lang->_DEFAULT['audit']['audit']['execute']))){
									$summary .=  "\n" . $this->_lang->_DEFAULT['audit']['audit']['execute'][$key] . ': ';
									switch ($key){
										case 'rating':
											$summary .=  $this->_lang->_DEFAULT['audit']['audit']['execute']['rating_steps'][$values[0]];
											break;
										case 'regulatory':
											$summary .= implode(', ' , array_map(fn($r) => isset($this->_lang->_DEFAULT['regulatory'][$r]) ? $this->_lang->_DEFAULT['regulatory'][$r] : $r, explode(',', $values[0])));
											break;
										default:
										$summary .=  implode("\n", $values);
									}
								}
							}
						}
						$summary .= "\n \n" . $this->_lang->GET('audit.audit.execute.summary', [], true) . ': ' . $audit[':content']['summary'];
						$this->alertUserGroup(['permission' => PERMISSION::permissionFor('regulatory', true), 'unit' => [$audit[':unit']]], $summary);
					}

					$this->response([
					'response' => [
						'msg' => $this->_lang->GET('audit.audit.execute.saved'),
						'id' => $this->_pdo->lastInsertId(),
						'type' => 'success'
					]]);
				}
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('audit.audit.execute.not_saved'),
						'id' => false,
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				$template = SQLQUERY::EXECUTE($this->_pdo, 'audit_get_template', ['values' => [':id' => $this->_requestedTemplate]]);
				$template = $template ? $template[0] : null;
				if (!$template) $this->response(['msg' => $this->_lang->GET('audit.audit.template.not_found'), 'type' => 'error'], 404);
				$audit = SQLQUERY::EXECUTE($this->_pdo, 'audit_and_management_get_by_id', ['values' => [':id' => $this->_requestedID]]);
				$audit = $audit ? $audit[0] : null;
				if (!$audit) $this->response(['msg' => $this->_lang->GET('audit.audit.execute.not_found'), 'type' => 'error'], 404);

				// update general properties
				$audit['last_user'] = $_SESSION['user']['name'];
				$audit['closed'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('audit.audit.execute.close')) ? 1 : null;
				unset($this->_payload->{$this->_lang->PROPERTY('audit.audit.execute.close')});

				// reset content to passed values
				$audit['content'] = [
					'objectives' => $template['objectives'],
					'method' => $template['method'],
					'summary' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('audit.audit.execute.summary')) ? : null,
					'questions' => []
				];
				unset($this->_payload->{$this->_lang->PROPERTY('audit.audit.execute.summary')});

				// process content
				// process files
				foreach ($_FILES as $fileinput => $files){
					if ($uploaded = UTILITY::storeUploadedFiles([$fileinput], UTILITY::directory('audit_attachments'), [preg_replace('/[^\w\d]/m', '', $this->_date['servertime']->format('YmdHis') . '_' . $template['unit'])], null, true)){
						for($i = 0; $i < count($files['name']); $i++){
							if (in_array(strtolower(pathinfo($uploaded[$i])['extension']), ['jpg', 'jpeg', 'gif', 'png'])) UTILITY::alterImage($uploaded[$i], CONFIG['limits']['record_image'], UTILITY_IMAGE_REPLACE);
							preg_match('/^(\d+):_(.+?)(?:\((\d+)\)|$)/m', $fileinput, $set); // get current question set information: [1] setindex, [2] input, isset [3] possible multiple field
							if (isset($audit['content']['questions'][intval($set[1])]['files'])) $audit['content']['questions'][intval($set[1])]['files'][] = substr($uploaded[$i], 1);
							else $audit['content']['questions'][intval($set[1])]['files'] = [substr($uploaded[$i], 1)];
						}
					}
				}

				// iterate over payload, match template question index, input name and possible multiples
				// values always will be stored within an array to handle multiples by default
				foreach ($this->_payload as $key => $value){
					if ($key === 'null') continue;
					if (!$value) $value = ''; // the audit has to contain all questions as planned
					preg_match('/^(\d+):_(.+?)(?:\((\d+)\)|$)/m', $key, $set); // get current question set information: [1] setindex, [2] input, isset [3] possible multiple field
					$set[2] = str_replace('_', ' ', $set[2]);
					if ($input = array_search($set[2], $this->_lang->_USER['audit']['audit']['execute']))
						// translateable system fields
						$audit['content']['questions'][intval($set[1])][$input][isset($set[3]) ? $set[3] - 1 : 0] = $value;
					else
						// manual human template question
						$audit['content']['questions'][intval($set[1])][$set[2]][0] = $value;
				}

				if (SQLQUERY::EXECUTE($this->_pdo, 'audit_and_management_put', [
					'values' => [
						':id' => $audit['id'],
						':content' => UTILITY::json_encode($audit['content']),
						':last_user' => $audit['last_user'],
						':closed' => $audit['closed']
					]
				])) {
					if ($audit['closed']){
						$summary = $this->_lang->GET('audit.checks_type.audits', [], true) . ' - ' . $this->_lang->_DEFAULT['units'][$audit['unit']] . "\n \n";
						$summary .= $audit['last_user'] . "\n";
						$summary .= $this->_lang->GET('audit.audit.objectives', [], true) . ': '. $audit['content']['objectives'];
						foreach ($audit['content']['questions'] as $question){
							// start with  question and direct response as initial value
							foreach ($question as $key => $values){
								if (in_array($key, array_keys($this->_lang->_DEFAULT['audit']['audit']['execute']))) continue;
								$summary .= "\n \n" .$key . ': ' . implode("\n", $values) . "\n";
								break;
							}
							// assign question response as value
							foreach ($question as $key => $values){
								if (in_array($key, array_keys($this->_lang->_DEFAULT['audit']['audit']['execute']))){
									$summary .=  "\n" . $this->_lang->_DEFAULT['audit']['audit']['execute'][$key] . ': ';
									switch ($key){
										case 'rating':
											$summary .=  $this->_lang->_DEFAULT['audit']['audit']['execute']['rating_steps'][$values[0]];
											break;
										case 'regulatory':
											$summary .= implode(', ' , array_map(fn($r) => isset($this->_lang->_DEFAULT['regulatory'][$r]) ? $this->_lang->_DEFAULT['regulatory'][$r] : $r, explode(',', $values[0])));
											break;
										default:
										$summary .=  implode("\n", $values);
									}
								}
							}
						}
						$summary .= "\n \n" . $this->_lang->GET('audit.audit.execute.summary', [], true) . ': ' . $audit['content']['summary'];
						$this->alertUserGroup(['permission' => PERMISSION::permissionFor('regulatory', true), 'unit' => [$audit['unit']]], $summary);
					}
					
					$this->response([
					'response' => [
						'msg' => $this->_lang->GET('audit.audit.execute.saved'),
						'id' => $this->_pdo->lastInsertId(),
						'type' => 'success'
					]]);
				}
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('audit.audit.execute.not_saved'),
						'id' => false,
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$response = [];
				$audit = $template = $recent = null;
				$select = [
					'edit' => [
						'...' => ['value' => '0']
					],
					'templates' => [
						'...' => ['value' => '0']
					]
				];
				$templates = SQLQUERY::EXECUTE($this->_pdo, 'audit_get_templates');
				$audits = SQLQUERY::EXECUTE($this->_pdo, 'audit_get');
				$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
				// drop system user and group accounts
				foreach ($users as $key => $row){
					if (PERMISSION::filteredUser($row, ['id' => [1], 'permission' => ['patient', 'group']])) unset($users[$key]);
				}
				$users = array_column($users, 'name');

				if ($this->_requestedID && $this->_requestedID !== 'false' && ($audit = $audits[array_search($this->_requestedID, array_column($audits, 'id'))]) === false) $response['response'] = ['msg' => $this->_lang->GET('audit.audit.execute.not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

				//template selection
				foreach ($templates as $row){
					$select['templates'][$this->_lang->_USER['units'][$row['unit']] . ($row['hint'] ? ' - ' . $row['hint'] : '') . ' ' . $this->convertFromServerTime($row['date'])] = ['value' => $row['id']];
				}
				if ($this->_requestedTemplate && $this->_requestedTemplate !== 'null' && !$audit){
					$template = $templates[array_search($this->_requestedTemplate, array_column($templates, 'id'))];
				}
				elseif ($audit){
					$template = $templates[array_search($audit['template'], array_column($templates, 'id'))];
				}

				// audit selections
				foreach ($audits as $row){
					if (!$row['closed']){
						$select['edit'][$this->_lang->_USER['units'][$row['unit']] . ' ' . $this->convertFromServerTime($row['last_touch'])] = $row['id'] === $this->_requestedID ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
					}
					elseif (!$recent && !$audit && $template && $row['unit'] === $template['unit']){
						$recent = $row;
					}
				}

				// sanitize $recent preset to only questions and statement
				if ($recent){
					$recent['content'] =  json_decode($recent['content'], true);
					foreach ($recent['content']['questions'] as $i => $set){
						foreach ($set as $key => $value)
						if (in_array($key, array_keys($this->_lang->_USER['audit']['audit']['execute'])) && !in_array($key, ['statement'])) unset($recent['content']['questions'][$i][$key]);
					}
					$recent['content'] =  UTILITY::json_encode(['questions' => $recent['content']['questions']]);
				}

				if (!$audit){
					$audit = [
						'id' => null,
						'template' => isset($template['id']) ? $template['id'] : null,
						'content' => $recent ? $recent['content'] : '',
						'unit' => isset($template['unit']) ? $template['unit'] : null,
						'last_touch' => null,
					];
				}

				if (!$template) {
					// display selections
					$response['render']['content'][] = [
						[
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('audit.navigation.templates'),
								'onclick' => "api.audit('get', 'audittemplate')"
							]
						]
					];
					if (count(array_keys($select['templates'])) > 1) $response['render']['content'][count($response['render']['content']) - 1][] = [
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('audit.audit.template.select'),
							'onchange' => "if (this.value !== '0') api.audit('get', 'audit', this.value);"
						],
						'content' => $select['templates']
					];
					if (count(array_keys($select['edit'])) > 1) $response['render']['content'][count($response['render']['content']) - 1][] = [
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('audit.audit.edit'),
							'onchange' => "if (this.value !== '0') api.audit('get', 'audit', 'null', this.value);"
						],
						'content' => $select['edit']
					];
				}
				else {
					// render template
					$response['render']['form'] = [
						'data-usecase' => 'audit',
						'action' => "javascript:api.audit('" . ($audit['id'] ? 'put' : 'post') . "', 'audit', " . $template['id']. ", " . $audit['id'] . ")"
					];
					$audit['content'] = json_decode($audit['content'], true);

					// display unit and audit objectives
					$response['render']['content'][] = [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->_USER['units'][$template['unit']]
							],
							'content' => $template['objectives'] .
								(!$template['method'] ? '' : " \n" . $this->_lang->GET('audit.audit.method') . ': ' . $this->_lang->GET('audit.audit.methods.' . $template['method'])) .
								($audit['id'] ? "\n \n" . $this->_lang->GET('audit.audit.execute.last_edit', [':date' => $this->convertFromServerTime($audit['last_touch']), ':user' => $audit['last_user']]) : '')
						]
					];

					// display template questions and respective inputs
					$rating = [];
					foreach (json_decode($template['content'], true) as $number => $question){
						$preset = [];
						if (isset($audit['content']['questions'][strval($number + 1)])) $preset = $audit['content']['questions'][strval($number + 1)];

						// set up rating and import preset if applicable
						foreach ($this->_lang->_USER['audit']['audit']['execute']['rating_steps'] as $key => $translation){
							$rating[$translation] = ['value' => $key];
							if (isset($preset['rating']) && $preset['rating'][0] === $key) $rating[$translation] = ['value' => $key, 'checked' => true];
						}
						// set up proof inputs, multiple if applicable due to preset
						$proof = [];
						if (isset($preset['proof'])){
							foreach ($preset['proof'] as $value){
								if ($value) // empty values are stored by default, to have everything an the audits data, clear empty proofs that otherwise would pile up to an array of emptyness
									$proof[] = ['type' => 'scanner',
										'attributes' => [
											'name' => $number + 1 . ': ' . $this->_lang->GET('audit.audit.execute.proof'),
											'multiple' => true,
											'data-loss' => 'prevent',
											'value' => $value
										]
									];
							}
						}
						$proof[] = [
							'type' => 'scanner',
								'attributes' => [
									'name' => $number + 1 . ': ' . $this->_lang->GET('audit.audit.execute.proof'),
									'multiple' => true,
									'data-loss' => 'prevent'
								]
							];
						if (isset($preset['files'])){
							$link = [];
							foreach ($preset['files'] as $file){
								$fileinfo = pathinfo($file);
								$file = [
									'path' => substr($file, 1),
									'name' => $fileinfo['basename'],
									'link' => './api/api.php/file/stream/' . substr($file, 1)
								];
								if (in_array($fileinfo['extension'], ['stl'])) $link[$file['name']] = ['href' => "javascript:new _client.Dialog({type: 'preview', header: '" . $file['name'] . "', render:{type: 'stl', name: '" . $file['name'] . "', url: '" . $file['link'] . "'}})", 'data-filtered' => $file['path'], 'data-type' => 'stl'];
								elseif (in_array($fileinfo['extension'], ['png','jpg', 'jpeg', 'gif'])) $link[$file['name']] = ['href' => "javascript:new _client.Dialog({type: 'preview', header: '" . $file['name'] . "', render:{type: 'image', name: '" . $file['name'] . "', content: '" . $file['link'] . "'}})", 'data-filtered' => $file['path'], 'data-type' => 'imagelink'];
								else $link[$file['name']]= ['href' => $file['link'], 'target' => '_blank', 'data-filtered' => 'breakline'];
							}
							if ($link) {
								$proof[] = [
									'type' => 'links',
									'description' => $this->_lang->GET('audit.audit.execute.files'),
									'content' => $link
								];
								$proof[] = [
									'type' => 'br'
								];
							}
						}
						$proof[] = [
							'type' => 'file',
								'attributes' => [
									'name' => $number + 1 . ': ' . $this->_lang->GET('audit.audit.execute.files'),
									'multiple' => true,
									'data-loss' => 'prevent'
								]
							];
						$proof[] = [
							'type' => 'photo',
								'attributes' => [
									'name' => $number + 1 . ': ' . $this->_lang->GET('audit.audit.execute.photos'),
									'multiple' => true,
									'data-loss' => 'prevent'
								]
							];

						// render regular inputs
						$response['render']['content'][] = [
							[
								'type' => 'text',
								'attributes' => [
									'name' => $number + 1 . ': ' . $this->_lang->GET('audit.audit.execute.conversation_partner'),
									'data-loss' => 'prevent',
									'value' => isset($preset['conversation_partner']) ? $preset['conversation_partner'][0] : ''
								],
								'datalist' => $users
							], [
								'type' => 'textarea',
								'attributes' => [
									'name' => $number + 1 . ': ' . $question['question'],
									'data-loss' => 'prevent',
									'value' => isset($preset[$question['question']]) ? $preset[$question['question']][0] : ''
								]
							], [
								'type' => 'textsection',
								'attributes' => [
									'name' => implode(', ' , array_map(fn($r) => isset($this->_lang->_USER['regulatory'][$r]) ? $this->_lang->_USER['regulatory'][$r] : $r, explode(',', $question['regulatory'])))
								],
								'content' => $question['hint'] ? : ' '
							], [
								'type' => 'hidden',
								'attributes' => [
									'name' => $number + 1 . ': ' . $this->_lang->GET('audit.audit.execute.regulatory'),
									'value' => $question['regulatory']
								]
							], [
								'type' => 'radio',
								'attributes' => [
									'name' => $number + 1 . ': ' . $this->_lang->GET('audit.audit.execute.rating')
								],
								'content' => $rating
							], [
								'type' => 'textarea',
								'attributes' => [
									'name' => $number + 1 . ': ' . $this->_lang->GET('audit.audit.execute.statement'),
									'data-loss' => 'prevent',
									'value' => isset($preset['statement']) ? $preset['statement'][0] : ''
								]
							], ...$proof
						];	
					}

					// append final note, deletion and closing options
					$response['render']['content'][] = [
						[
							'type' => 'textarea',
							'attributes' => [
								'name' => $this->_lang->GET('audit.audit.execute.summary'),
								'value' => isset($audit['content']['summary']) ? $audit['content']['summary'] : ''
							]
						], [
							'type' => 'checkbox',
							'content' => [
								$this->_lang->GET('audit.audit.execute.close') => [
									'onchange' => "if (this.checked) {new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('audit.audit.execute.close_confirm') ."', options:{".
									"'".$this->_lang->GET('general.cancel_button')."': false,".
									"'".$this->_lang->GET('general.ok_button')."': {value: true, class: 'reducedCTA'}".
									"}}).then(confirmation => {if (!confirmation) this.checked = false})}"
								]
							]
						]
					];

					if ($audit['id']){
						$response['render']['content'][count($response['render']['content']) - 1][] = [
							'type' => 'deletebutton',
							'attributes' => [
								'value' => $this->_lang->GET('audit.audit.delete'),
								'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('audit.audit.execute.delete_confirm_header', [':unit' => $this->_lang->_USER['units'][$template['unit']]]) ."', options:{".
								"'".$this->_lang->GET('audit.audit.delete_confirm_cancel')."': false,".
								"'".$this->_lang->GET('audit.audit.execute.delete_confirm_ok')."': {value: true, class: 'reducedCTA'}".
								"}}).then(confirmation => {if (confirmation) api.audit('delete', 'audit', 'null', " . $audit['id'] . ")})"
							]
						];
					}
				}
				break;
			case 'DELETE':
				if (SQLQUERY::EXECUTE($this->_pdo, 'audit_and_management_delete', [
					'values' => [
						':id' => $this->_requestedID
					]
				])) $this->response(['response' => [
					'msg' => $this->_lang->GET('audit.audit.execute.delete_success'),
					'type' => 'deleted'
					]]);
				else $this->response(['response' => [
					'msg' => $this->_lang->GET('audit.audit.execute.delete_error'),
					'type' => 'error'
					]]);
				break;
		}
		$this->response($response);
	}
	/**
	 * creates and returns a download link to the export file for given audit
	 */
	private function exportaudit(){
		$audit = SQLQUERY::EXECUTE($this->_pdo, 'audit_and_management_get_by_id', ['values' => [':id' => $this->_requestedID]]);
		$audit = $audit ? $audit[0] : null;
		if (!$audit) $this->response($return['response'] = ['msg' => $this->_lang->GET('audit.audit.execute.not_found'), 'type' => 'error'], 404);

		$audit['content'] = json_decode($audit['content'], true);

		$summary = [
			'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $this->_lang->GET('audit.checks_type.audits', [], true) . '_' . $audit['last_touch']),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.audits', [], true) . ' - ' . $this->_lang->_DEFAULT['units'][$audit['unit']],
			'date' => $this->convertFromServerTime($audit['last_touch'], true)
		];
		
		$summary['content'][$audit['last_user']] = '';
		$summary['content'][$this->_lang->GET('audit.audit.objectives', [], true)] = $audit['content']['objectives'];
		$summary['content'][$this->_lang->GET('audit.audit.method', [], true)] = isset($audit['content']['method']) ? $this->_lang->GET('audit.audit.methods.' . $audit['content']['method']) : '';
		foreach ($audit['content']['questions'] as $question){
			$currentquestion = null;
			// assign question as key and current question direct response as initial value
			foreach ($question as $key => $values){
				if (in_array($key, array_keys($this->_lang->_DEFAULT['audit']['audit']['execute']))) continue;
				$currentquestion = $key;
				$summary['content'][$currentquestion] = implode("\n", $values) . "\n";
				break;
			}
			if (!$currentquestion) continue;
			// assign question response as value
			foreach ($question as $key => $values){
				if (in_array($key, array_keys($this->_lang->_DEFAULT['audit']['audit']['execute']))){
					$summary['content'][$currentquestion] .= "\n" . $this->_lang->_DEFAULT['audit']['audit']['execute'][$key] . ': ';
					switch ($key){
						case 'rating':
							$summary['content'][$currentquestion] .= $this->_lang->_DEFAULT['audit']['audit']['execute']['rating_steps'][$values[0]];
							break;
						case 'regulatory':
							$summary['content'][$currentquestion] .= implode(', ' , array_map(fn($r) => isset($this->_lang->_DEFAULT['regulatory'][$r]) ? $this->_lang->_DEFAULT['regulatory'][$r] : $r, explode(',', $values[0])));
							break;
						default:
							$summary['content'][$currentquestion] .= implode("\n", $values);
					}
				}
			}
		}
		$summary['content'][$this->_lang->GET('audit.audit.execute.summary', [], true)] = $audit['content']['summary'];

		$downloadfiles = [];
		$PDF = new PDF(CONFIG['pdf']['record']);
		$downloadfiles[$this->_lang->GET('audit.checks_type.audits')] = [
			'href' => './api/api.php/file/stream/' . $PDF->auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}

	/**
	 *             _ _ _       
	 *   ___ _ _ _| |_| |_ ___ 
	 *  | .'| | | . | |  _|_ -|
	 *  |__,|___|___|_|_| |___|
	 *
	 * 
	 */
	public function audits(){
		$content = [];
		$audits = SQLQUERY::EXECUTE($this->_pdo, 'audit_get');
		foreach ($audits as $audit){
			if (!$audit['closed']) continue;
			$audit['content'] = json_decode($audit['content'], true);
			$current = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->_DEFAULT['units'][$audit['unit']] . ' ' . $this->convertFromServerTime($audit['last_touch']) . ' ' . $audit['last_user']
					],
					'content' => $this->_lang->GET('audit.audit.objectives', [], true). "\n \n" . $audit['content']['objectives'] .
						(!isset($audit['content']['method']) ? '' : " \n" . $this->_lang->GET('audit.audit.method') . ': ' . $this->_lang->GET('audit.audit.methods.' . $audit['content']['method']))
				]
			];
			foreach ($audit['content']['questions'] as $question){
				// assign question as key and current question direct response as initial value
				$currentquestion = $currentanswer = '';
				foreach ($question as $key => $values){
					if (in_array($key, array_keys($this->_lang->_DEFAULT['audit']['audit']['execute']))) continue;
					$currentquestion = $key;
					$currentanswer = implode("\n", $values) . "\n";
					break;
				}
				if (!$currentquestion) continue;
				// assign question response as value
				foreach ($question as $key => $values){
					if (in_array($key, array_keys($this->_lang->_DEFAULT['audit']['audit']['execute']))){
						$currentanswer .= (in_array($key, array_keys($this->_lang->_DEFAULT['audit']['audit']['execute'])) ? $this->_lang->_DEFAULT['audit']['audit']['execute'][$key] : $key) . ': ';
						switch ($key){
							case 'rating':
								$currentanswer .= $this->_lang->_DEFAULT['audit']['audit']['execute']['rating_steps'][$values[0]];
								break;
							case 'regulatory':
								$currentanswer .= implode(', ' , array_map(fn($r) => isset($this->_lang->_DEFAULT['regulatory'][$r]) ? $this->_lang->_DEFAULT['regulatory'][$r] : $r, explode(',', $values[0])));
								break;
							case 'files':
								break;
							default:
								$currentanswer .= implode("\n", $values);
						}
						$currentanswer .= "\n";
					}
				}
				
				$current[] = [
					'type' => 'auditsection',
					'attributes' => [
						'name' => $currentquestion
					],
					'content' => $currentanswer
				];

				if (isset($question['files'])){
					$link = [];
					foreach ($question['files'] as $file){
						$fileinfo = pathinfo($file);
						$file = [
							'path' => substr($file, 1),
							'name' => $fileinfo['basename'],
							'link' => './api/api.php/file/stream/' . substr($file, 1)
						];
						if (in_array($fileinfo['extension'], ['stl'])) $link[$file['name']] = ['href' => "javascript:new _client.Dialog({type: 'preview', header: '" . $file['name'] . "', render:{type: 'stl', name: '" . $file['name'] . "', url: '" . $file['link'] . "'}})", 'data-filtered' => $file['path'], 'data-type' => 'stl'];
						elseif (in_array($fileinfo['extension'], ['png','jpg', 'jpeg', 'gif'])) $link[$file['name']] = ['href' => "javascript:new _client.Dialog({type: 'preview', header: '" . $file['name'] . "', render:{type: 'image', name: '" . $file['name'] . "', content: '" . $file['link'] . "'}})", 'data-filtered' => $file['path'], 'data-type' => 'imagelink'];
						else $link[$file['name']]= ['href' => $file['link'], 'target' => '_blank', 'data-filtered' => 'breakline'];
					}
					if ($link) {
						$current[] = [
							'type' => 'links',
							'description' => $this->_lang->GET('audit.audit.execute.files'),
							'content' => $link
						];
						$current[] = [
							'type' => 'br'
						];
					}
				}
			}
			$current[] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('audit.audit.execute.summary', [], true)
				],
				'content' => $audit['content']['summary']
			];
			$current[] = [
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.records.export'),
					'onclick' => "api.audit('get', 'export', 'audit', " . $audit['id'] . ")",
					'data-type' => 'download'
				]
			];
			$content[] = $current;
		}
		return $content;
	}

	/**
	 *             _ _ _   _                 _     _       
	 *   ___ _ _ _| |_| |_| |_ ___ _____ ___| |___| |_ ___ 
	 *  | .'| | | . | |  _|  _| -_|     | . | | .'|  _| -_|
	 *  |__,|___|___|_|_| |_| |___|_|_|_|  _|_|__,|_| |___|
	 *                                  |_|
	 * 
	 */
	public function audittemplate(){
		if (!PERMISSION::permissionFor('audit')) $this->response([], 401);
		// recursively sanitize unpredictable nested frontend input
		function sanitizeQuestionNesting($element, $result = []){
			foreach ($element as $sub){
				if (array_is_list($sub)){
					array_push($result, ...sanitizeQuestionNesting($sub, $result));
				} else {
					$result[] = $sub;					
				}
			}
			return $result;
		}

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$template = [
					':content' => UTILITY::propertySet($this->_payload, 'content'),
					':objectives' => UTILITY::propertySet($this->_payload, 'objectives'),
					':unit' => array_search(UTILITY::propertySet($this->_payload, 'unit'), $this->_lang->_USER['units']),
					':author' => $_SESSION['user']['name'],
					':hint' =>  UTILITY::propertySet($this->_payload, 'hint'),
					':method' => null,
				];

				// sanitize payload content and translate regulatory to keys
				foreach ($this->_lang->_USER['audit']['audit']['methods'] as $method => $description){
					if ($description === UTILITY::propertySet($this->_payload, 'method')){
						$template[':method'] = $method;
						break;
					}
				}
				if (!$template[':content'] || !$template[':unit'] || !$template[':objectives'] || !$template[':method']) $this->response([], 400);
				$template[':content'] = json_decode($template[':content'] ? : '', true);
				$template[':content'] = sanitizeQuestionNesting($template[':content']);
				foreach ($template[':content'] as &$question){
					$question['regulatory'] = explode(', ', $question['regulatory']);
					$question['regulatory'] = implode(',', array_map(fn($r) => array_search($r, $this->_lang->_USER['regulatory']), $question['regulatory']));
				}

				$template[':content'] = UTILITY::json_encode($template[':content']);

				if (SQLQUERY::EXECUTE($this->_pdo, 'audit_post_template', [
					'values' => $template
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('audit.audit.template.saved'),
						'id' => $this->_pdo->lastInsertId(),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('audit.audit.template.not_saved'),
						'id' => false,
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				$template = [
					':content' => UTILITY::propertySet($this->_payload, 'content'),
					':objectives' => UTILITY::propertySet($this->_payload, 'objectives'),
					':unit' => array_search(UTILITY::propertySet($this->_payload, 'unit'), $this->_lang->_USER['units']),
					':author' => $_SESSION['user']['name'],
					':hint' =>  UTILITY::propertySet($this->_payload, 'hint'),
					':id' => $this->_requestedID,
					':method' => null,
				];

				// sanitize payload content and translate regulatory to keys
				foreach ($this->_lang->_USER['audit']['audit']['methods'] as $method => $description){
					if ($description === UTILITY::propertySet($this->_payload, 'method')){
						$template[':method'] = $method;
						break;
					}
				}
				if (!$template[':content'] || !$template[':unit'] || !$template[':objectives'] || !$template[':method']) $this->response([], 400);
				$template[':content'] = json_decode($template[':content'] ? : '', true);
				$template[':content'] = sanitizeQuestionNesting($template[':content']);
				foreach ($template[':content'] as &$question){
					$question['regulatory'] = explode(', ', $question['regulatory']);
					$question['regulatory'] = implode(',', array_map(fn($r) => array_search($r, $this->_lang->_USER['regulatory']), $question['regulatory']));
				}

				$template[':content'] = UTILITY::json_encode($template[':content']);

				if (SQLQUERY::EXECUTE($this->_pdo, 'audit_put_template', [
					'values' => $template
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('audit.audit.template.saved'),
						'id' => $this->_pdo->lastInsertId(),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('audit.audit.template.not_saved'),
						'id' => false,
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$response = [];
				$datalist = ['objectives' => [], 'questions' => [], 'hints' => []];
				$templatehints = [];
				$select = ['...' . $this->_lang->GET('audit.audit.template.new') => ['value' => '0']];
				$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
				$data = SQLQUERY::EXECUTE($this->_pdo, 'audit_get_templates');

				if ($this->_requestedID && $this->_requestedID !== 'false' && ($template = $data[array_search($this->_requestedID, array_column($data, 'id'))]) === false) $return['response'] = ['msg' => $this->_lang->GET('audit.audit.template.not_found'), 'type' => 'error'];

				if (!$data || !$this->_requestedID || !$template){
					$template = [
						'id' => null,
						'content' => '',
						'unit' => '',
						'objectives' => '',
						'author' => '',
						'date' => null,
						'hint' => null,
						'method' => null,
					];
				}

				// set up regulatory selection according to language file
				$regulatory = [];
				foreach ($this->_lang->_USER['regulatory'] as $key => $translation){
					$regulatory[$translation] = ['value' => $key];
				}

				// gather available units
				$units = [];
				foreach ($this->_lang->_USER['units'] as $unit => $description){
					$units[$description] = $unit === $template['unit'] ? ['selected' => true] : [];
				}
				
				// prepare selection and datalists
				foreach ($data as $row){
					// selection
					$select[$this->_lang->_USER['units'][$row['unit']] . ($row['hint'] ? ' - ' . $row['hint'] : '') . ' ' . $row['date']] = intval($this->_requestedID) === $row['id'] ? ['value' => strval($row['id']), 'selected' => true] : ['value' => strval($row['id'])];
					// template hint datalist
					$templatehints[] = $row['hint'];
					// objective datalist
					$datalist['objectives'][] = $row['objectives'];
					// question datalist
					$row['content'] = json_decode($row['content'] ? : '', true);
					foreach ($row['content'] as $question){
						$datalist['questions'][] = $question['question'];
						$datalist['hints'][] = $question['hint'];
					}
				}
				// sanitize datalists
				foreach ($datalist as $data => &$values){
					$values = array_filter($values, fn($v) => boolval($v));
					ksort($values);
				}
				$templatehints = array_unique($templatehints);
				$templatehints = array_filter($values, fn($v) => boolval($v));
				ksort($templatehints);

				// prepare methods
				$auditmethods = [];
				foreach ($this->_lang->_USER['audit']['audit']['methods'] as $method => $description){
					$auditmethods[$description] = $method === $template['method'] ? ['selected' => true] : [];
				}

				// prepare and append content for editor rendering
				if ($template['content'] = json_decode($template['content'] ? : '', true)){
					foreach ($template['content'] as &$question){
						$question['regulatory'] = explode(',', $question['regulatory']);
						$question['regulatory'] = implode(', ', array_map(fn($r) => isset($this->_lang->_USER['regulatory'][$r]) ? $this->_lang->_USER['regulatory'][$r] : $r, $question['regulatory']));
					}
					$response['selected'] = $template['content'];
				}

				$response['render']['form'] = [
					'data-usecase' => 'audittemplate',
					'action' => "javascript:api.audit('" . ($template['id'] ? 'put' : 'post') . "', 'audittemplate', 'null', " . $template['id'] . ")"
				];

				$response['render']['content'][] = [
					[
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('audit.audit.template.edit'),
							'onchange' => "api.audit('get', 'audittemplate', 'null', this.value)"
						],
						'content' => $select
					]
				];
				$response['render']['content'][] = [
					[
						'type' => 'textsection',
						'content' => $template['author'] ? $this->_lang->GET('risk.author', [':author' => $template['author'], ':date' => $this->convertFromServerTime($template['date'])]) : null
					], [
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('user.units'),
							'id' => 'TemplateUnit',
							'data-loss' => 'prevent'
						],
						'content' => $units
					], [
						'type' => 'text',
						'attributes' => [
							'name' => $this->_lang->GET('audit.audit.template.hint'),
							'id' => 'TemplateHint',
							'value' => $template['hint'],
							'data-loss' => 'prevent'
						],
						'datalist' => $templatehints,
						'hint' => $this->_lang->GET('audit.audit.template.hint_hint')
					], [
						'type' => 'textarea',
						'attributes' => [
							'name' => $this->_lang->GET('audit.audit.objectives'),
							'id' => 'TemplateObjectives',
							'value' => $template['objectives'] ? : '',
							'required' => true,
							'data-loss' => 'prevent'
						],
						'autocomplete' => array_values($datalist['objectives']) ? : null
					], [
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('audit.audit.method'),
							'id' => 'TemplateMethod'
						],
						'content' => $auditmethods,
					], [
						'type' => 'button',
						'attributes' => [
							'value' => $this->_lang->GET('audit.audit.template.import_summary'),
							'onclick' => "api.audit('get', 'import', 'auditsummary', document.getElementById('TemplateUnit').value);"
						],
						'hint' => $this->_lang->GET('audit.audit.template.import_summary_hint'),
					], [
						'type' => 'calendarbutton',
						'attributes' => [
							'value' => $this->_lang->GET('audit.audit.template.schedule'),
							'onclick' => $calendar->dialog([':type' => 'schedule'])
						]
					]

				];
				$response['render']['content'][] = [
					[
						'type' => 'textarea',
						'attributes' => [
							'name' => $this->_lang->GET('audit.audit.question'),
							'id' => '_question',
							'rows' => 4,
							'data-loss' => 'prevent',
							'maxlength' => 80,
							'data-type' => 'auditsection' // for composer, not icon
						],
						'autocomplete' => array_values($datalist['questions']) ? : null
					], [
						'type' => 'checkbox2text',
						'attributes' => [
							'name' => $this->_lang->GET('audit.audit.execute.regulatory'),
							'id' => '_regulatory',
							'data-loss' => 'prevent'
						],
						'content' => $regulatory
					], [
						'type' => 'textarea',
						'attributes' => [
							'name' => $this->_lang->GET('audit.audit.hint'),
							'id' => '_hint',
							'rows' => 4,
							'data-loss' => 'prevent'
						],
						'numeration' => 'none',
						'autocomplete' => array_values($datalist['hints']) ? : null
					], [
						'type' => 'button',
						'attributes' => [
							'value' => $this->_lang->GET('audit.audit.add'),
							'onclick' => "Composer.composeNewAuditQuestionCallback(document.getElementById('_question').value, document.getElementById('_regulatory').value, document.getElementById('_hint').value);"
						]
					]
				];

				if ($template['id']){
					// a template can only be deleted if not used by an unfinished audit
					$unused = true;
					$audits = SQLQUERY::EXECUTE($this->_pdo, 'audit_get');
					foreach ($audits as $audit){
						if ($audit['template'] === $template['id'] && !$audit['closed']) {
							$unused = false;
							break;
						}
					}
					if ($unused) $response['render']['content'][count($response['render']['content']) - 1][] = [
						[
							'type' => 'deletebutton',
							'attributes' => [
								'value' => $this->_lang->GET('audit.audit.delete'),
								'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('audit.audit.template.delete_confirm_header', [':unit' => $this->_lang->_USER['units'][$template['unit']]]) ."', options:{".
								"'".$this->_lang->GET('audit.audit.delete_confirm_cancel')."': false,".
								"'".$this->_lang->GET('audit.audit.template.delete_confirm_ok')."': {value: true, class: 'reducedCTA'}".
								"}}).then(confirmation => {if (confirmation) api.audit('delete', 'audittemplate', 'null', " . $template['id'] . ")})"
							]
						]
					];
					else $response['render']['content'][count($response['render']['content']) - 1][] = [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('audit.audit.template.used')
							]
						]
					];
				}

				$response['render']['content'][] = [
					[
						'type' => 'trash',
						'description' => $this->_lang->GET('assemble.compose.edit_trash')
					]
				];
				break;
			case 'DELETE':
				if (SQLQUERY::EXECUTE($this->_pdo, 'audit_delete_template', [
					'values' => [
						':id' => $this->_requestedID
					]
				])) $this->response(['response' => [
					'msg' => $this->_lang->GET('audit.audit.template.delete_success'),
					'type' => 'deleted'
					]]);
				else $this->response(['response' => [
					'msg' => $this->_lang->GET('audit.audit.template.delete_error'),
					'type' => 'error'
					]]);
				break;
		}
		$this->response($response);
	}

	/**
	 *       _           _
	 *   ___| |_ ___ ___| |_ ___
	 *  |  _|   | -_|  _| '_|_ -|
	 *  |___|_|_|___|___|_,_|___|
	 *
	 * main entry point for regulatory evaluations and summaries
	 * displays a selection of available options
	 * calls $this->_requestedType method if set
	 */
	public function checks(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
			case 'PUT':
			case 'GET':
				$response['render'] = ['content' => []];
				$selecttypes = ['...' => []];
				
				foreach ([
					'audits', // internal audits
					'managementreviews',
					'mdrsamplecheck', // sample checks on products
					'incorporation', // incorporated products
					'documents', // documents and components
					'documentusage', // document usage count
					'userskills', // user skills and certificates
					'skillfulfilment', // skill fulfilment
					'userexperience', // experience points per user and year
					'vendors', // vendor list
					'orderstatistics', // order statistics
					'complaints', // complaints within records
					'records',
					'regulatory', // regulatory issues
					'risks', // risks
					'trainingevaluation', // training evaluation
					] as $category){
						$selecttypes[$this->_lang->GET('audit.checks_type.' . $category)] = ['value' => $category];
						if ($this->_requestedType === $category) $selecttypes[$this->_lang->GET('audit.checks_type.' . $category)]['selected'] = true;
				}
				ksort($selecttypes);
				$response['render']['content'][] = [
					[
						'type' => 'select',
						'content' => $selecttypes,
						'attributes' => [
							'name' => $this->_lang->GET('audit.checks_select_type'),
							'onchange' => "if (this.value !== '...') api.audit('get', 'checks', this.value)"
						]
					]
				];

				if ($this->_requestedType && $this->_requestedType !== '...') {
					if ($append = $this->{$this->_requestedType}()) array_push($response['render']['content'] , ...$append);
				}
				$this->response($response);
				break;
			case 'DELETE':
				if (!PERMISSION::permissionFor('regulatoryoperation')) $this->response([], 401);
				$permitted = [
					'orderstatistics'
				];
				if (in_array($this->_requestedType, $permitted)) $this->{'delete' . $this->_requestedType}();
				break;
		}
	}

	/**
	 *                     _     _     _       
	 *   ___ ___ _____ ___| |___|_|___| |_ ___ 
	 *  |  _| . |     | . | | .'| |   |  _|_ -|
	 *  |___|___|_|_|_|  _|_|__,|_|_|_|_| |___|
	 *                |_|
	 * list and link complaints from records, sum by year
	 */
	private function complaints(){
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');
		$entries = $content = [];
		foreach ($data as $row){
			if ($row['record_type'] === 'complaint'){
				$year = substr($row['last_touch'], 0, 4);
				if (!isset($entries[$year])) $entries[$year] = [];
				$entries[$year][$row['identifier']] = ['closed' => json_decode($row['closed'] ? : '', true), 'units' => $row['units']];
			}
		}
		//order by year descending
		arsort($entries);
		foreach ($entries as $year => $cases){
			$current = $links = [];
			$current[] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => strval($year)
				],
				'content' => $this->_lang->GET('audit.complaints._summary', [':number' => count($cases), ':closed' => count(array_filter($cases, Fn($c) => PERMISSION::fullyapproved('complaintclosing', $c['closed'])))])
			];
			foreach ($cases as $identifier => $property){
				$units = implode(', ', array_map(Fn($u) => $this->_lang->_USER['units'][$u], explode(',', $property['units'])));
				$linkdescription = $this->_lang->GET('audit.complaints._case_description', [':identifier' => $identifier, ':units' => $units]);
				if (PERMISSION::fullyapproved('complaintclosing', $property['closed'])) {
					$linkdescription .= $this->_lang->GET('audit.complaints._closed');
				}
				$links[$linkdescription] = ['href' => "javascript:api.record('get', 'record', '" . $identifier . "')"];
				if (PERMISSION::pending('complaintclosing', $property['closed'])) {
					$links[$linkdescription]['class'] = 'orange';
				}
			}
			$current[] = [
				'type' => 'links',
				'content' => $links
			];
			$content[] = $current;
		}
		return $content;
	}
	
	/**
	 *                       _
	 *   ___ _ _ ___ ___ ___| |_
	 *  | -_|_'_| . | . |  _|  _|
	 *  |___|_,_|  _|___|_| |_|
	 *          |_|
	 * main entry point for exports
	 * calls export . $this->_requestedType method
	 */
	public function export(){
		if (!PERMISSION::permissionFor('regulatoryoperation')) $this->response([], 401);
		$static = [
			'audit',
			'managementreview',
			'mdrsamplecheck',
			'incorporation',
			'documents',
			'userskills',
			'skillfulfilment',
			'userexperience',
			'vendors',
			'orderstatistics',
			'complaints',
			'records',
			'regulatory',
			'risks'
		];
		if (in_array($this->_requestedType, $static)) $this->{'export' . $this->_requestedType}();
		else $this->response([], 404);
	}

	/**
	 *     _                           _       
	 *   _| |___ ___ _ _ _____ ___ ___| |_ ___ 
	 *  | . | . |  _| | |     | -_|   |  _|_ -|
	 *  |___|___|___|___|_|_|_|___|_|_|_| |___|
	 *  
	 * returns all current approved documents with their respective components and approvement notes in alphabetical order
	 * also documents bundles and available external documents
	 */
	private function documents(){
		$content = [];

		$this->_requestedDate = $this->_requestedDate ? : $this->_date['usertime']->format('Y-m-d');
		$this->_requestedTime = $this->_requestedTime ? : $this->_date['usertime']->format('H:i:59');
		$requestedTimestamp = $this->convertToServerTime($this->_requestedDate . ' ' . $this->_requestedTime);

		function latestApprovedComponent($components, $requestedTimestamp, $name = ''){
			if (!$name) return false;
			// get latest approved by name
			$named_components = array_filter($components, Fn($component) => $component['name'] === $name);
			foreach ($named_components as $component){
				if (PERMISSION::fullyapproved('documentapproval', $component['approval']) && 
					$component['date'] <= $requestedTimestamp) {
						$component['hidden'] = json_decode($component['hidden'] ? : '', true); 
						if (!$component['hidden'] || $component['hidden']['date'] > $requestedTimestamp) return $component;
						else return false;
					}
			}
			return false;
		}

		// get all current approved document older than given timestamp
		$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$hidden = $currentdocuments = [];
		foreach ($documents as $document){
			if (!PERMISSION::fullyapproved('documentapproval', $document['approval']) || $document['date'] >= $requestedTimestamp) continue;
			if ($document['hidden']) {
				$document['hidden'] = json_decode($document['hidden'], true);
				if ($document['hidden']['date'] <= $requestedTimestamp)
					$hidden[] = $document['name']; // since ordered by recent, older items will be skipped
			}
			if (!in_array($document['name'], array_column($currentdocuments, 'name')) && !in_array($document['name'], $hidden)) $currentdocuments[] = $document;
		}
	
		// get all components
		$components = SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist');

		// get all current bundles
		$bundles = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist');
		$hidden = $currentbundles = [];
		foreach ($bundles as &$bundle){
			if ($bundle['hidden']) $hidden[] = $bundle['name']; // since ordered by recent, older items will be skipped
			if (!in_array($bundle['name'], array_column($currentbundles, 'name')) && !in_array($bundle['name'], $hidden)) $currentbundles[] = $bundle;
		}

		// add export button
		if (PERMISSION::permissionFor('regulatoryoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.records.export'),
					'onclick' => "api.audit('get', 'export', '" . $this->_requestedType . "', document.getElementById('_documents_date').value, document.getElementById('_documents_time').value)",
					'data-type' => 'download'
				]
			]
		];
		
		$content[] = [
			[
				'type' => 'date',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents.date'),
					'value' => $this->_requestedDate,
					'id' => '_documents_date'
				]
			], [
				'type' => 'time',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents.time'),
					'value' => $this->_requestedTime,
					'id' => '_documents_time' 
				]
			], [
				'type' => 'button',
				'attributes' => [
					'data-type' => 'generateupdate',
					'value' => $this->_lang->GET('audit.documents.update_button'),
					'onclick' => "api.audit('get', 'checks', 'documents', document.getElementById('_documents_date').value, document.getElementById('_documents_time').value)"
				]
			], [
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents.in_use_documents')
				],
				'content' => $this->_lang->GET('audit.documents.export_timestamp', [':timestamp' => $this->convertFromServerTime($requestedTimestamp)])
			]
		];

		// iterate over documents an their respective components
		foreach ($currentdocuments as $document){
			$entry = '';
			$documentscontent = [];
			// display document approval
			foreach (json_decode($document['approval'], true) as $position => $data){
				$entry .= $this->_lang->GET('audit.documents.in_use_approved', [
					':permission' => $this->_lang->GET('permissions.' . $position),
					':name' => $data['name'],
					':date' => $this->convertFromServerTime($data['date']),
				]) . "\n";
			}
			// display component approval
			$has_components = false;
			foreach (explode(',', $document['content'] ? : '') as $used_component_name){
				if ($cmpnnt = latestApprovedComponent($components, $requestedTimestamp, $used_component_name)){
					$has_components = true;
					$cmpnnt['approval'] = json_decode($cmpnnt['approval'], true);
					$entry .= " \n" . $cmpnnt['name'] . ' ' . $this->_lang->GET('assemble.compose.component.author', [':author' => $cmpnnt['author'], ':date' => $this->convertFromServerTime($cmpnnt['date'])]) . "\n";
					foreach ($cmpnnt['approval'] as $position => $data){
						$entry .= $this->_lang->GET('audit.documents.in_use_approved', [
							':permission' => $this->_lang->GET('permissions.' . $position),
							':name' => $data['name'],
							':date' => $this->convertFromServerTime($data['date'], true),
						]) . "\n";
					}
				}
			}
			foreach (explode(',', $document['regulatory_context'] ? : '') as $context){
				$entry .= "\n" . (isset($this->_lang->_USER['regulatory'][$context]) ? $this->_lang->_USER['regulatory'][$context] : $context);
			}

			$documentscontent[] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => $document['name'] . ' ' . $this->_lang->GET('assemble.compose.component.author', [':author' => $document['author'], ':date' => $this->convertFromServerTime($document['date'], true)])
				],
				'content' => $entry
			];
			if (!$has_components) {
				$documentscontent[count($documentscontent) - 1]['attributes']['class'] = 'orange';
				$documentscontent[count($documentscontent) - 1]['content'] .="\n \n" . $this->_lang->GET('assemble.render.error_no_approved_components', [':permission' => implode(', ', array_map(fn($v) => $this->_lang->_USER['permissions'][$v], PERMISSION::permissionFor('documentcomposer', true)))]);
			}
			$documentscontent[] = [
				'type' => 'button',
				'attributes' => [
					'data-type' => 'download',
					'value' => $this->_lang->GET('assemble.render.export'),
					'onclick' => "new _client.Dialog({type: 'input', header: '". $this->_lang->GET('assemble.render.export') . "', render: JSON.parse('" . UTILITY::json_encode(
						[
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $document['name'] . ' ' . $this->_lang->GET('assemble.compose.component.author', [':author' => $document['author'], ':date' => $this->convertFromServerTime($document['date'], true)])
								],
							],
							[
								'type' => 'hidden',
								'attributes' => [
									'name' => '_maxDocumentTimestamp',
									'value' => $this->_requestedDate . ' ' . $this->_requestedTime
								]
							], [
								'type' => 'hidden',
								'attributes' => [
									'name' => '_document_id',
									'value' => $document['id']
								]
							]
						]
					) . "'), options:{".
					"'" . $this->_lang->GET('general.cancel_button') . "': false,".
					"'" . $this->_lang->GET('general.ok_button')  . "': {value: true, class: 'reducedCTA'},".
					"}}).then(response => {if (response) api.document('post', 'export', null, _client.application.dialogToFormdata(response))})"
				]
			];
			$content[] = $documentscontent;
		}

		$externalcontent = [
			[
				'type' => 'links',
				'description' => $this->_lang->GET('audit.documents.in_use_external'),
				'content' => ''
			]
		];
		$links = [];
		if ($files = SQLQUERY::EXECUTE($this->_pdo, 'file_external_documents_get_active')) {
			foreach ($files as $file){
				if (preg_match('/^\.\.\//', $file['path'])){
					$file['url'] = './api/api.php/file/stream/' . substr($file['path'], 1);
				}
				$display = pathinfo($file['path'])['basename'] . ' ' . $this->_lang->GET('file.external_file.introduced', [':user' => $file['author'], ':introduced' => $this->convertFromServerTime($file['activated'], true)]);
				foreach (explode(',', $file['regulatory_context'] ? : '') as $context){
					if ($context) $display .= " | " . (isset($this->_lang->_USER['regulatory'][$context]) ? $this->_lang->_USER['regulatory'][$context] : $context);
				}
				$links[$display] = ['href' => $file['url'], 'target' => 'blank'];
			}
			$externalcontent[0]['content'] = $links;
		}
		$content[] = $externalcontent;

		$bundlescontent = [
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents.in_use_bundles')
				],
				'content' => ''
			]
		];
		foreach ($currentbundles as $bundle){
			$documentslist = explode(',', $bundle['content'] ? : '');
			natsort($documentslist);
			$bundlescontent[] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => $bundle['name'] . ' ' . $this->_lang->GET('assemble.compose.component.author', [':author' => $bundle['author'], ':date' => $this->convertFromServerTime($bundle['date'], true)])
				],
				'content' => implode("\n", $documentslist)
			];
		}
		$content[] = $bundlescontent;
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for documents and document bundles
	 * processes the result of $this->documents() and translates the body object into more simple strings
	 */
	private function exportdocuments(){
		$summary = [
			'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $this->_lang->GET('audit.checks_type.' . $this->_requestedType) . '_' . $this->_date['usertime']->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.' . $this->_requestedType),
			'date' => $this->convertFromServerTime($this->_date['usertime']->format('Y-m-d H:i'), true)
		];

		$documents = $this->documents();

		for($i = 1; $i < count($documents); $i++){
			foreach ($documents[$i] as $item){
				if (isset($item['content'])){
					if (gettype($item['content']) === 'string' && isset($item['attributes']['name']))
						$summary['content'][$item['attributes']['name']] = $item['content'];
					elseif (gettype($item['content']) === 'array' && isset($item['description']))
						$summary['content'][$item['description']] = implode("\n", array_keys($item['content']));
				}
			}
		}
		$downloadfiles = [];
		$PDF = new PDF(CONFIG['pdf']['record']);
		$downloadfiles[$this->_lang->GET('record.navigation.summaries')] = [
			'href' => './api/api.php/file/stream/' . $PDF->auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}

	/**
	 *     _                           _                       
	 *   _| |___ ___ _ _ _____ ___ ___| |_ _ _ ___ ___ ___ ___ 
	 *  | . | . |  _| | |     | -_|   |  _| | |_ -| .'| . | -_|
	 *  |___|___|___|___|_|_|_|___|_|_|_| |___|___|__,|_  |___|
	 *                                                |___|
	 * analyses records and displays a use count for selected document contexts
	 */
	private function documentusage(){
		$records = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');
		$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$usedname = $usedid = [];

		foreach ($records as $record){
			$record['content'] = json_decode($record['content'], true);
			foreach ($record['content'] as $rc){
				if (!isset($rc['document'])) continue;
				if (!isset($usedid[$rc['document']])) $usedid[$rc['document']] = 0;
				$usedid[$rc['document']]++;
			}
		}

		// accumulate usecount by name
		$hidden = [];
		foreach ($documents as $row => $document){
			if ($document['hidden']) $hidden[] = $document['name'];

			// skip, and drop for performace reasons
			if (!in_array($document['context'], array_keys($this->_lang->_DEFAULT['documentcontext']['identify'])) // inappropriate contexts
				|| in_array($document['name'], $hidden) // hidden
				|| !PERMISSION::fullyapproved('documentapproval', $document['approval']) // unapproved versions
			){
				unset($documents[$row]);
				continue;
			}

			if (!isset($usedname[$document['name']])) $usedname[$document['name']] = 0;
			if (isset($usedid[$document['id']])) $usedname[$document['name']] += $usedid[$document['id']];
		}
		array_multisort($usedname); // order by count asc
		$documents = array_values($documents); // reassign keys if entries have been dropped

		$content = [
			[
				'type' => 'textsection',
				'content' => $this->_lang->GET('audit.documents.usage_warning')
			]
		];
		foreach ($usedname as $name => $count){
			$document = $documents[array_search($name, array_column($documents, 'name'))]; // since the document datalist is ordered by date desc the first match is suitable

			if ($document['regulatory_context']) $document['regulatory_context'] = array_map(fn($c) => isset($this->_lang->_USER['regulatory'][$c]) ? $this->_lang->_USER['regulatory'][$c] : $c, explode(',', $document['regulatory_context']));

			$color = end($usedname) ? 200 * $count / end($usedname) : 0; // avoid division by zero
			$content[] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => $document['name'],
					'style' => 'color:rgb(' . 200 - $color . ',' . $color . ',0)'
				],
				'linkedcontent' => $this->_lang->GET('audit.documents.usage_info', [
					':date' => $this->convertFromServerTime($document['date']),
					':regulatory' => implode(', ', $document['regulatory_context'] ? : []),
					':count' => $count,
					':unit' => $this->_lang->_USER['units'][$document['unit']]
					]) . "\n" . '<a href="javascript:api.record(\'get\', \'document\', \'' . $document['name'] . '\')">' . $this->_lang->GET('audit.documents.usage_link'). '</a>'
			];
		}
		return $content;
	}

	/**
	 *   _                   _
	 *  |_|_____ ___ ___ ___| |_
	 *  | |     | . | . |  _|  _|
	 *  |_|_|_|_|  _|___|_| |_|
	 *          |_|
	 * imports selected database content based on type and option
	 */
	public function import(){
		switch($this->_requestedType){
			case 'auditsummary':
				$this->_requestedOption = array_search($this->_requestedOption, $this->_lang->_USER['units']);
				$audits = SQLQUERY::EXECUTE($this->_pdo, 'audit_get');
				foreach ($audits as $audit){
					if ($audit['unit'] === $this->_requestedOption && $audit['closed']) {
						$audit['content'] = json_decode($audit['content'], true);
						if (isset($audit['content']['summary']) && $audit['content']['summary']) $this->response(['data' => $audit['content']['summary']]);
					}
				}
				$this->response([], 404);
				break;
		}
		$this->response([], 400);
	}

	/**
	 *   _                                 _   _
	 *  |_|___ ___ ___ ___ ___ ___ ___ ___| |_|_|___ ___
	 *  | |   |  _| . |  _| . | . |  _| .'|  _| | . |   |
	 *  |_|_|_|___|___|_| |  _|___|_| |__,|_| |_|___|_|_|
	 *                    |_|
	 * returns all incorporation records from the products database in descending chronological order
	 * displays a warning if products within approved orders require an incorporation
	 */
	private function incorporation(){
		$content = $orderedunincorporated = $entries = $incorporated = [];

		$this->_requestedDate = $this->_requestedDate ? : '2023-10-01';
		$this->_requestedTime = $this->_requestedTime ? : '00:00';

		// get unincorporated articles from approved orders
		$products = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products');
		foreach ($products as $id => $row){
			if (!$row['incorporated']) continue;
			$row['incorporated'] = json_decode($row['incorporated'] ? : '', true);
			if (!PERMISSION::fullyapproved('incorporation', $row['incorporated'])) continue;
			$fullyapproved = null;
			foreach (PERMISSION::permissionfor('incorporation', true) as $approved){
				$date = $row['incorporated'][$approved]['date'];
				if ($date > $fullyapproved) $fullyapproved = $date;
			}
			if ($fullyapproved < $this->_requestedDate . ' ' . $this->_requestedTime) continue;
			$incorporated[] = $row;
			unset($products[$id]); // to avoid duplicate warnings on orderedunincorporated below
		}

		$approvedorders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_substr', [
			'values' => [
				':substr' => 'ordernumber_label'
			]
		]);
		foreach ($approvedorders as $row){
			$decoded_order_data = json_decode($row['order_data'], true);
			if (isset($decoded_order_data['ordernumber_label']) && ($tocheck = array_search($decoded_order_data['ordernumber_label'], array_column($products, 'article_no'))) !== false){
				if (isset($decoded_order_data['vendor_label']) && (isset($products[$tocheck]) && $products[$tocheck]['vendor_name'] === $decoded_order_data['vendor_label'])){
					$article = $decoded_order_data['ordernumber_label'] . $decoded_order_data['vendor_label'];
					if (!in_array($article, $orderedunincorporated)) $orderedunincorporated[] = $article;
				}
			}
		}
		// display warning
		if ($orderedunincorporated) $content[] = [
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('audit.incorporation.warning_description')
				],
				'content' => $this->_lang->GET('audit.incorporation.warning_content', [':amount' => count($orderedunincorporated)])
			]
		];
		$content[] = [
			[
				'type' => 'date',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents.date'),
					'value' => $this->_requestedDate,
					'id' => '_documents_date'
				]
			], [
				'type' => 'time',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents.time'),
					'value' => $this->_requestedTime,
					'id' => '_documents_time' 
				]
			], [
				'type' => 'button',
				'attributes' => [
					'data-type' => 'generateupdate',
					'value' => $this->_lang->GET('audit.checks_update_button'),
					'onclick' => "api.audit('get', 'checks', 'incorporation', document.getElementById('_documents_date').value, document.getElementById('_documents_time').value)"
				]
			],
		];
		// add export button
		if (PERMISSION::permissionFor('regulatoryoperation')) $content[] = [
			'type' => 'button',
			'attributes' => [
				'value' => $this->_lang->GET('audit.records.export'),
				'onclick' => "api.audit('get', 'export', '" . $this->_requestedType . "', document.getElementById('_documents_date').value, document.getElementById('_documents_time').value)",
				'data-type' => 'download'
			]
		];
		// add incorporations
		// order descending based on approval date of random authorized person. a bit fuzzy though. hope all act within a reasonable time
		$permission = PERMISSION::permissionFor('incorporation', true)[array_rand(PERMISSION::permissionFor('incorporation', true))];
		usort($incorporated, function ($a, $b) use ($permission) {
			if ($a['incorporated'][$permission]['date'] === $b['incorporated'][$permission]['date']) return 0;
			return $a['incorporated'][$permission]['date'] < $b['incorporated'][$permission]['date'] ? -1: 1;
		});

		$incorporations = [];
		$entries[] = [
			'type' => 'textsection',
			'attributes' => [
				'name' => $this->_lang->GET('audit.checks_type.incorporation')
			],
			'content' => $this->_lang->GET('audit.incorporation.export_timestamp', [':timestamp' => $this->convertFromServerTime($this->_requestedDate, true) . ' ' . $this->_requestedTime])
		];
		foreach ($incorporated as $product){
			if (!isset($incorporations[$product['vendor_name']])) $incorporations[$product['vendor_name']] = [];

			$incorporationInfo = str_replace(["\r", "\n"], ['', " \n"], $product['incorporated']['_check']);
			foreach (['user', ...PERMISSION::permissionFor('incorporation', true)] as $permission){
				if (isset($product['incorporated'][$permission])) $incorporationInfo .= " \n" . $this->_lang->_USER['permissions'][$permission] . ' ' . $product['incorporated'][$permission]['name'] . ' ' . $this->convertFromServerTime($product['incorporated'][$permission]['date'], true);
			}
			$incorporations[$product['vendor_name']][] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => $product['article_no'] . ' ' . $product['article_name']
				],
				'linkedcontent' => $incorporationInfo . "\n" . '<a href="javascript:api.purchase(\'get\', \'product\', ' . $product['id'] . ')">' . $this->_lang->GET('audit.incorporation.link') . '</a>'
			];
		}
		ksort($incorporations);
		foreach ($incorporations as $vendor => $vendorchecks){
			$content[] = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('audit.incorporation.export_vendor', [':vendor' => $vendor])
					],
					'content' => $this->_lang->GET('audit.incorporation.export_timestamp', [':timestamp' => $this->convertFromServerTime($this->_requestedDate, true) . ' ' . $this->_requestedTime])
				],
				...$vendorchecks
			];
		}
		return $content;
	}
	/**
	 * creates and returns a download link to the export file incorporations
	 * processes the result of $this->incorporation() and translates the body object into more simple strings
	 */
	private function exportincorporation(){
		$summary = [
			'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $this->_lang->GET('audit.checks_type.' . $this->_requestedType) . '_' . $this->_date['usertime']->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.' . $this->_requestedType),
			'date' => $this->convertFromServerTime($this->_date['usertime']->format('Y-m-d H:i'), true)
		];

		$documents = $this->incorporation();

		for($i = 3; $i<count($documents); $i++){
			foreach ($documents[$i] as $item){
				if (isset($item['content']) || isset($item['linkedcontent'])){
					if (isset($item['content']) && isset($item['attributes']['name']))
						$summary['content'][$item['attributes']['name']] = $item['content'];
					elseif (isset($item['linkedcontent']) && isset($item['attributes']['name']))
						// remove link to product to be only displayed onscreen
						$summary['content'][$item['attributes']['name']] = preg_replace('/' . $this->_lang->GET('audit.incorporation.link') . '$/m', '', strip_tags($item['linkedcontent']));
				}
			}
		}
		$downloadfiles = [];
		$PDF = new PDF(CONFIG['pdf']['record']);
		$downloadfiles[$this->_lang->GET('record.navigation.summaries')] = [
			'href' => './api/api.php/file/stream/' . $PDF->auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}

	 /**
	 *                                           _               _           
	 *   _____ ___ ___ ___ ___ ___ _____ ___ ___| |_ ___ ___ _ _|_|___ _ _ _ 
	 *  |     | .'|   | .'| . | -_|     | -_|   |  _|  _| -_| | | | -_| | | |
	 *  |_|_|_|__,|_|_|__,|_  |___|_|_|_|___|_|_|_| |_| |___|\_/|_|___|_____|
	 *                    |___|
	 * 
	 */
	public function managementreview(){
		if (!PERMISSION::permissionFor('audit')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$managementreview = [
					':template' => null,
					':unit' => null,
					':content' => [],
					':last_user' => $_SESSION['user']['name'],
					':closed' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('audit.managementreview.close')) ? 1 : null
				];
				// process content according to required fields
				foreach ($this->_lang->_USER['audit']['managementreview']['required'] as $key => $value){
					$managementreview[':content'][$key] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('audit.managementreview.required.' . $key)) ? : '';
				}
				$managementreview[':content'] = UTILITY::json_encode($managementreview[':content']);
				if (SQLQUERY::EXECUTE($this->_pdo, 'audit_and_management_post', [
					'values' => $managementreview
				])) {
					if ($managementreview[':closed']){
						$this->alertUserGroup(['permission' => PERMISSION::permissionFor('regulatory', true)], $this->_lang->GET('audit.managementreview.alert', [
							':link' => '<a href="javascript:void(0);" onclick="api.audit(\'get\', \'checks\', \'managementreviews\')">' . $this->_lang->GET('tool.navigation.regulatory', [], true). '</a>'],
							true )
						);
					}
					$this->response([
					'response' => [
						'msg' => $this->_lang->GET('audit.managementreview.saved'),
						'id' => $this->_pdo->lastInsertId(),
						'type' => 'success'
					]]);
				}
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('audit.managementreview.not_saved'),
						'id' => false,
						'type' => 'error'
					]]);
				break;
			case 'PUT';
				$managementreview = SQLQUERY::EXECUTE($this->_pdo, 'audit_and_management_get_by_id', ['values' => [':id' => $this->_requestedID]]);
				$managementreview = $managementreview ? $managementreview[0] : null;
				if (!$managementreview) $this->response(['msg' => $this->_lang->GET('audit.managementreview.not_found'), 'type' => 'error'], 404);
				
				// update general properties
				$managementreview['last_user'] = $_SESSION['user']['name'];
				$managementreview['closed'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('audit.managementreview.close')) ? 1 : null;
				$managementreview['content'] = json_decode($managementreview['content'], true);
				// process content according to required fields
				foreach ($this->_lang->_USER['audit']['managementreview']['required'] as $key => $value){
					$managementreview['content'][$key] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('audit.managementreview.required.' . $key)) ? : '';
				}
				if (SQLQUERY::EXECUTE($this->_pdo, 'audit_and_management_put', [
					'values' => [
						':id' => $managementreview['id'],
						':content' => UTILITY::json_encode($managementreview['content']),
						':last_user' => $managementreview['last_user'],
						':closed' => $managementreview['closed']
					]
				])) {
				if ($managementreview['closed']){
						$this->alertUserGroup(['permission' => PERMISSION::permissionFor('regulatory', true)], $this->_lang->GET('audit.managementreview.alert', [
							':link' => '<a href="javascript:void(0);" onclick="api.audit(\'get\', \'checks\',  \'managementreviews\')">' . $this->_lang->GET('tool.navigation.regulatory', [], true). '</a>'],
							true )
						);
					}
					$this->response([
					'response' => [
						'msg' => $this->_lang->GET('audit.managementreview.saved'),
						'id' => $this->_pdo->lastInsertId(),
						'type' => 'success'
					]]);
				}
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('audit.managementreview.not_saved'),
						'id' => false,
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$response = $datalist = [];
				$managementreview = $recent = null;
				$select = [
					'edit' => [
						'...' => ['value' => '0']
					]
				];
				$managementreviews = SQLQUERY::EXECUTE($this->_pdo, 'management_get');

				if ($this->_requestedID && $this->_requestedID !== 'false' && ($managementreview = $managementreviews[array_search($this->_requestedID, array_column($managementreviews, 'id'))]) === false) $return['response'] = ['msg' => $this->_lang->GET('audit.managementreview.not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

				// managementreview selections and previous content as autocomplete datalists
				foreach ($managementreviews as $row){
					foreach (json_decode($row['content'], true) as $key => $value){
						if (!isset($datalist[$key])) $datalist[$key] = [];
						$datalist[$key][] = $value;
					}
					if ($row['closed']) {
						if (!$recent) $recent = $row;
						continue;
					}
					$select['edit'][$this->convertFromServerTime($row['last_touch'])] = $row['id'] == $this->_requestedID ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
				}
				// sanitize datalists
				foreach ($datalist as &$values){
					$values = array_filter($values, fn($v) => boolval($v));
					ksort($values);
				}

				if (!$managementreview){
					$managementreview = [
						'id' => null,
						'template' => null,
						'content' => $recent ? $recent['content'] : '',
						'unit' => null,
						'last_touch' => null,
					];
				}

				// render template
				$response['render']['form'] = [
					'data-usecase' => 'audit',
					'action' => "javascript:api.audit('" . ($managementreview['id'] ? 'put' : 'post') . "', 'managementreview', 'null', " . $managementreview['id'] . ")"
				];
				$managementreview['content'] = json_decode($managementreview['content'] ? : '', true);

				// selection of open reviews
				if (count(array_keys($select['edit']))>1){
						$response['render']['content'][] = [
						[
							'type' => 'select',
							'attributes' => [
								'name' => $this->_lang->GET('audit.managementreview.edit'),
								'onchange' => "api.audit('get', 'managementreview', 'null', this.value)"
							],
							'content' => $select['edit']
						]
					];
				}
				// display last edit
				$response['render']['content'][] = [
					[
						'type' => 'textsection',
						'content' => ($managementreview['id'] ? "\n \n" . $this->_lang->GET('audit.managementreview.last_edit', [':date' => $this->convertFromServerTime($managementreview['last_touch'], true), ':user' => $managementreview['last_user']]) : $this->_lang->GET('audit.managementreview.last_version'))
					]
				];
				// display issue inputs
				foreach ($this->_lang->_USER['audit']['managementreview']['required'] as $key => $issue){
					$response['render']['content'][] = [
						[
							'type' => 'textarea',
							'attributes' => [
								'name' => $issue,
								'value' => isset($managementreview['content'][$key]) ? $managementreview['content'][$key] : '',
								'data-loss' => 'prevent'
							],
							'autocomplete' => isset($datalist[$key]) ? array_values($datalist[$key]) : null
						]
					];
				}

				// append deletion and closing options
				$response['render']['content'][] = [
					[
						'type' => 'checkbox',
						'content' => [
							$this->_lang->GET('audit.managementreview.close') => [
								'onchange' => "if (this.checked) {new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('audit.managementreview.close_confirm') ."', options:{".
								"'".$this->_lang->GET('general.cancel_button')."': false,".
								"'".$this->_lang->GET('general.ok_button')."': {value: true, class: 'reducedCTA'}".
								"}}).then(confirmation => {if (!confirmation) this.checked = false})}"
							]
						]
					]
				];

				if ($managementreview['id']){
					$response['render']['content'][count($response['render']['content']) - 1][] = [
						'type' => 'deletebutton',
						'attributes' => [
							'value' => $this->_lang->GET('audit.audit.delete'),
							'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('audit.managementreview.delete_confirm_header', [':unit' => '']) ."', options:{".
							"'".$this->_lang->GET('audit.managementreview.delete_confirm_cancel')."': false,".
							"'".$this->_lang->GET('audit.managementreview.delete_confirm_ok')."': {value: true, class: 'reducedCTA'}".
							"}}).then(confirmation => {if (confirmation) api.audit('delete', 'managementreview', 'null', " . $managementreview['id'] . ")})"
						]
					];
				}
				break;
			case 'DELETE':
				if (SQLQUERY::EXECUTE($this->_pdo, 'audit_and_management_delete', [
					'values' => [
						':id' => $this->_requestedID
					]
				])) $this->response(['response' => [
					'msg' => $this->_lang->GET('audit.managementreview.delete_success'),
					'type' => 'deleted'
					]]);
				else $this->response(['response' => [
					'msg' => $this->_lang->GET('audit.managementreview.delete_error'),
					'type' => 'error'
					]]);
				break;
		}
		$this->response($response);
	}
	/**
	 * creates and returns a download link to the export file for given management review
	 */
	private function exportmanagementreview(){
		$managementreview = SQLQUERY::EXECUTE($this->_pdo, 'audit_and_management_get_by_id', ['values' => [':id' => $this->_requestedID]]);
		$managementreview = $managementreview ? $managementreview[0] : null;
		if (!$managementreview) $this->response($return['response'] = ['msg' => $this->_lang->GET('audit.managementreview.not_found'), 'type' => 'error'], 404);

		$managementreview['content'] = json_decode($managementreview['content'], true);

		$summary = [
			'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $this->_lang->GET('audit.navigation.management_review', [], true) . '_' . $managementreview['last_touch']),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.navigation.management_review', [], true),
			'date' => $this->convertFromServerTime($managementreview['last_touch'], true)
		];
		
		$summary['content'][$managementreview['last_user']] = '';
		foreach ($managementreview['content'] as $issue => $review){
			// translate or keep issue if not found in languagefile 
			$key = isset($this->_lang->_DEFAULT['audit']['managementreview']['required'][$issue]) ? $this->_lang->_DEFAULT['audit']['managementreview']['required'][$issue] : $issue;
			$summary['content'][$key] = $review . "\n";
		}

		$downloadfiles = [];
		$PDF = new PDF(CONFIG['pdf']['record']);
		$downloadfiles[$this->_lang->GET('audit.navigation.management_review')] = [
			'href' => './api/api.php/file/stream/' . $PDF->auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}

	/**
	 *                                           _               _               
	 *   _____ ___ ___ ___ ___ ___ _____ ___ ___| |_ ___ ___ _ _|_|___ _ _ _ ___ 
	 *  |     | .'|   | .'| . | -_|     | -_|   |  _|  _| -_| | | | -_| | | |_ -|
	 *  |_|_|_|__,|_|_|__,|_  |___|_|_|_|___|_|_|_| |_| |___|\_/|_|___|_____|___|
	 *                    |___|
	 * 
	 */
	private function managementreviews(){
		$content = [];
		$managementreviews = SQLQUERY::EXECUTE($this->_pdo, 'management_get');
		foreach ($managementreviews as $managementreview){
			if (!$managementreview['closed']) continue;
			$managementreview['content'] = json_decode($managementreview['content'], true);
			$current = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->convertFromServerTime($managementreview['last_touch'], true) . ' ' . $managementreview['last_user']
					]
				]
			];

			foreach ($managementreview['content'] as $issue => $review){
				$currentquestion = $currentanswer = '';
				// translate or keep issue if not found in languagefile 
				$key = isset($this->_lang->_DEFAULT['audit']['managementreview']['required'][$issue]) ? $this->_lang->_DEFAULT['audit']['managementreview']['required'][$issue] : $issue;
				$currentquestion = $key;
				$currentanswer = $review . "\n";

				$current[] = [
					'type' => 'auditsection',
					'attributes' => [
						'name' => $currentquestion
					],
					'content' => $currentanswer
				];
			}

			$current[] = [
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.records.export'),
					'onclick' => "api.audit('get', 'export', 'managementreview', " . $managementreview['id'] . ")",
					'data-type' => 'download'
				]
			];
			$content[] = $current;
		}
		return $content;
	}

	/**
	 *           _                       _         _           _
	 *   _____ _| |___ ___ ___ _____ ___| |___ ___| |_ ___ ___| |_
	 *  |     | . |  _|_ -| .'|     | . | | -_|  _|   | -_|  _| '_|
	 *  |_|_|_|___|_| |___|__,|_|_|_|  _|_|___|___|_|_|___|___|_,_|
	 *                              |_|
	 * returns all sample checks from the caro_consumables_product database in descending chronological order
	 * displays a warning if a vendor is overdue for sample check
	 */
	private function mdrsamplecheck(){
		$content = $unchecked = [];

		$this->_requestedDate = $this->_requestedDate ? : '2023-10-01';
		$this->_requestedTime = $this->_requestedTime ? : '00:00';

		// get unchecked articles for MDR §14 sample check
		// this is actually faster than a nested sql query
		$vendors = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
		foreach ($vendors as &$vendor){
			$vendor['pricelist'] = json_decode($vendor['pricelist'] ? : '', true); 
		}
		$products = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products_by_vendor_id', [
			'replacements' => [
				':ids' => implode(",", array_column($vendors, 'id'))
			]
		]);
		// get all checkable products
		$checkable = [];
		foreach ($products as $product){
			if (!$product['trading_good']) continue;
			if (!isset($checkable[$product['vendor_name']])) $checkable[$product['vendor_name']] = [];
			if (!$product['checked']){
				$checkable[$product['vendor_name']][] = $product['id'];
				continue;
			}
			$vendor = $vendors[array_search($product['vendor_name'], array_column($vendors, 'name'))];
			$check = new \DateTime($product['checked']);
			if (isset($vendor['pricelist']['samplecheck_reusable']) && intval($check->diff($this->_date['servertime'])->format('%a')) > $vendor['pricelist']['samplecheck_reusable']){
				$checkable[$product['vendor_name']][] = $product['id'];
			}
		}
		// drop vendors that have been checked within their sample check interval
		foreach ($products as $product){
			if (!$product['trading_good'] || !$product['checked'] || !isset($checkable[$product['vendor_name']])) continue;
			$check = new \DateTime($product['checked']);
			if (isset($vendor['pricelist']['samplecheck_interval']) && intval($check->diff($this->_date['servertime'])->format('%a')) <= $vendor['pricelist']['samplecheck_interval']){
				unset($checkable[$product['vendor_name']]);
			}
		}

		$unchecked = array_keys($checkable);
		sort($unchecked);
		// display warning
		if ($unchecked) $content[] = [
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('audit.mdrsamplecheck.warning_description')
				],
				'content' => $this->_lang->GET('audit.mdrsamplecheck.warning_content', [':vendors' => implode(', ', $unchecked)])
			]
		];
		$content[] = [
			[
				'type' => 'date',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents.date'),
					'value' => $this->_requestedDate,
					'id' => '_documents_date'
				]
			], [
				'type' => 'time',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents.time'),
					'value' => $this->_requestedTime,
					'id' => '_documents_time' 
				]
			], [
				'type' => 'button',
				'attributes' => [
					'data-type' => 'generateupdate',
					'value' => $this->_lang->GET('audit.checks_update_button'),
					'onclick' => "api.audit('get', 'checks', '" . $this->_requestedType . "', document.getElementById('_documents_date').value, document.getElementById('_documents_time').value)",
					]
			]
		];
		// add export button
		if (PERMISSION::permissionFor('regulatoryoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.records.export'),
					'onclick' => "api.audit('get', 'export', '" . $this->_requestedType . "', document.getElementById('_documents_date').value, document.getElementById('_documents_time').value)",
					'data-type' => 'download'
				]
			]
		];

		// add check records
		$products = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products');
		// order descending
		usort($products, function ($a, $b) {
			if ($a['checked'] === $b['checked']) return 0;
			return $a['checked'] < $b['checked'] ? -1: 1;
		});

		$checks = [];
		foreach ($products as $product){
			if (!$product['sample_checks']) continue;
			if ($product['checked'] < $this->_requestedDate . ' ' . $this->_requestedTime . ':00') continue;

			if (!isset($checks[$product['vendor_name']])) $checks[$product['vendor_name']] = [];

			$product['sample_checks'] = json_decode($product['sample_checks'], true);
			$productchecks = [];
			foreach ($product['sample_checks'] as $check){
				$productchecks[] = $this->_lang->GET('audit.mdrsamplecheck.edit', [':author' => $check['author'], ':date' => $this->convertFromServerTime($check['date'], true)], true) . "\n" . $check['content'];
			}
			$checks[$product['vendor_name']][] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => implode(' ', [$product['article_no'], $product['article_name']])
				],
				'content' => implode("\n\n", $productchecks)
			];

			if (PERMISSION::permissionFor('regulatoryoperation')) $checks[$product['vendor_name']][] = [
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.mdrsamplecheck.revoke'),
					'onclick' => "new _client.Dialog({type:'confirm', header:'" . $this->_lang->GET('order.disapprove') . "', " .
						"options:{'" . $this->_lang->GET('order.disapprove_message_cancel') . "': false, '" . $this->_lang->GET('audit.mdrsamplecheck.revoke_confirm') . "': {value: true, class: 'reducedCTA'}}}).then(response => {" .
						"if (response !== false) {" .
						"api.purchase('delete', 'mdrsamplecheck', " . $product['id']. "); this.disabled=true" .
						"}});"
				]
			];
		}
		ksort($checks);
		foreach ($checks as $vendor => $vendorchecks){
			$content[] = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('audit.mdrsamplecheck.export_vendor', [':vendor' => $vendor])
					],
					'content' => $this->_lang->GET('audit.incorporation.export_timestamp', [':timestamp' => $this->convertFromServerTime($this->_requestedDate) . ' ' . $this->_requestedTime, true])
				],
				...$vendorchecks
			];
		}
		return $content;
	}
	/**
	 * creates and returns a download link to the export file mdrsamplechecks
	 * processes the result of $this->mdrsamplecheck() and translates the body object into more simple strings
	 */
	private function exportmdrsamplecheck(){
		$summary = [
			'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $this->_lang->GET('audit.checks_type.' . $this->_requestedType) . '_' . $this->_date['usertime']->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.' . $this->_requestedType),
			'date' => $this->convertFromServerTime($this->_date['usertime']->format('Y-m-d H:i'), true)
		];

		$checks = $this->mdrsamplecheck();

		for($i = 3; $i < count($checks); $i++){
			foreach ($checks[$i] as $item){
				if (isset($item['content']) && isset($item['attributes']['name']))
						$summary['content'][$item['attributes']['name']] = $item['content'];
			}
		}
		$downloadfiles = [];
		$PDF = new PDF(CONFIG['pdf']['record']);
		$downloadfiles[$this->_lang->GET('record.navigation.summaries')] = [
			'href' => './api/api.php/file/stream/' . $PDF->auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}

	/**
	 *             _             _       _   _     _   _
	 *   ___ ___ _| |___ ___ ___| |_ ___| |_|_|___| |_|_|___ ___
	 *  | . |  _| . | -_|  _|_ -|  _| .'|  _| |_ -|  _| |  _|_ -|
	 *  |___|_| |___|___|_| |___|_| |__,|_| |_|___|_| |_|___|___|
	 *
	 * returns export and delete options for order statistics
	 */
	private function orderstatistics(){
		$content = [];

		$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_order_statistics');
		$from = $until = '-';
		// process only valid ordered state to avoid errors
		$fromtil = array_filter($orders, fn($o) => $o['ordered']);
		usort($fromtil, function ($a, $b) {
			if ($a['ordered'] === $b['ordered']) return 0;
			return $a['ordered'] < $b['ordered'] ? -1: 1;
		});

		$from = $fromtil && $fromtil[0]['ordered'] ? substr($fromtil[0]['ordered'], 0, 10) : '-';
		$until = $fromtil && $fromtil[0]['ordered'] ? substr($fromtil[count($fromtil) - 1]['ordered'], 0, 10) : '-';
		$content[] = [
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('audit.orderstatistics.number', [':number' => count($orders), ':from' => $this->convertFromServerTime($from), ':until' => $this->convertFromServerTime($until)])
				],
				'content' => count($orders) ? $this->_lang->GET('audit.orderstatistics.info') : ''
			]
		];

		if (count($orders) && PERMISSION::permissionFor('regulatoryoperation')){
			// add export button
			$content[] = [
				[
					'type' => 'button',
					'attributes' => [
						'value' => $this->_lang->GET('audit.records.export_xlsx'),
						'onclick' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
						'data-type' => 'download'
					]
				]
			];
			$content[] = [
				[
					'type' => 'deletebutton',
					'attributes' => [
						'value' => $this->_lang->GET('audit.orderstatistics.truncate'),
						'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('audit.orderstatistics.truncate') ."', options:{".
						"'".$this->_lang->GET('general.cancel_button')."': false,".
						"'".$this->_lang->GET('audit.orderstatistics.truncate_confirm')."': {value: true, class: 'reducedCTA'},".
						"}}).then(confirmation => {if (confirmation) api.audit('delete', 'checks', '" . $this->_requestedType . "');})",
					]
				]
			];
		}
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for the order statistics
	 * export is an xlsx file with orders grouped by vendor sheets
	 */
	private function exportorderstatistics(){
		$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_order_statistics');

		$columns = [
			'vendor_label' => $this->_lang->GET('order.vendor_label'),
			'ordertype' => $this->_lang->GET('order.order_type'),
			'quantity_label' => $this->_lang->GET('order.quantity_label'),
			'unit_label' => $this->_lang->GET('order.unit_label'),
			'ordernumber_label' => $this->_lang->GET('order.ordernumber_label'),
			'productname_label' => $this->_lang->GET('order.productname_label'),
			'additional_info' => $this->_lang->GET('order.additional_info'),
			'ordered' => $this->_lang->GET('order.order.ordered'),
			'partially_received' => $this->_lang->GET('order.order.partially_received'),
			'received' => $this->_lang->GET('order.order.received'),
			'deliverytime' => $this->_lang->GET('audit.orderstatistics.delivery_time_column')
		];

		// prepare result as subsets of vendors
		$vendor_orders = [];
		foreach ($orders as $order){
			$order['order_data'] = json_decode($order['order_data'], true);
			$deliverytime = '';
			if ($order['received']){
				$ordered = $order['ordered'] ? new \DateTime($order['ordered']) : '-';
				$received = $order['received'] ? new \DateTime($order['received']) : '-';
				$deliverytime = ($order['ordered'] && $order['received']) ? intval($ordered->diff($received)->format('%a')) : '-';
			}

			if (!isset($order['order_data']['vendor_label'])) $order['order_data']['vendor_label'] = $this->_lang->GET('audit.orderstatistics.undefined_vendor');
			if (!isset($vendor_orders[$order['order_data']['vendor_label']])) $vendor_orders[$order['order_data']['vendor_label']] = [];

			$vendor_orders[$order['order_data']['vendor_label']][] = [
				isset($order['order_data']['vendor_label']) ? $order['order_data']['vendor_label'] : '',
				$this->_lang->GET('order.ordertype.' . $order['ordertype']),
				isset($order['order_data']['quantity_label']) ? $order['order_data']['quantity_label'] : '',
				isset($order['order_data']['unit_label']) ? $order['order_data']['unit_label'] : '',
				isset($order['order_data']['ordernumber_label']) ? $order['order_data']['ordernumber_label'] : '',
				isset($order['order_data']['productname_label']) ? $order['order_data']['productname_label'] : '',
				isset($order['order_data']['additional_info']) ? preg_replace('/\\\\n|\\n/', "\n", $order['order_data']['additional_info']) : '',
				$this->convertFromServerTime($order['ordered']),
				$this->convertFromServerTime($order['partially_received']),
				$this->convertFromServerTime($order['received']),
				$deliverytime
			];
		}
		$tempFile = UTILITY::directory('tmp') . '/' . preg_replace('/[^\w\d]/', '', $this->_lang->GET('audit.checks_type.orderstatistics') . '_' . $this->_date['usertime']->format('Y-m-d H:i')) . '.xlsx';
		$writer = new \XLSXWriter();
		$writer->setAuthor($_SESSION['user']['name']); 

		foreach ($vendor_orders as $vendor => $orders){
			$writer->writeSheetRow($vendor, array_values($columns));
			foreach ($orders as $line)
				$writer->writeSheetRow($vendor, $line, array('height' => 30, 'wrap_text' => true));
		}

		$writer->writeToFile($tempFile);
		$downloadfiles[$this->_lang->GET('record.navigation.summaries')] = [
			'href' => './api/api.php/file/stream/' . substr($tempFile, 1),
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' => $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}

	/**
	 * truncates the respective database
	 */
	private function deleteorderstatistics(){
		SQLQUERY::EXECUTE($this->_pdo, 'order_truncate_order_statistics');
		$this->response([
			'response' => [
				'msg' => $this->_lang->GET('audit.orderstatistics.truncate_success'),
				'type' => 'deleted'
			]
		]);
	}

	/**
	 *                         _     
	 *   ___ ___ ___ ___ ___ _| |___ 
	 *  |  _| -_|  _| . |  _| . |_ -|
	 *  |_| |___|___|___|_| |___|___|
	 *
	 * creates an xlsx-file with all recent record parameters combined, suitable for data analysis
	 */
	private function records(){
		$content = [];

		if (PERMISSION::permissionFor('regulatoryoperation')){
			// add export button
			$content[] = [
				[
					'type' => 'date',
					'attributes' => [
						'name' => $this->_lang->GET('audit.records.start_date'),
						'value' => '2023-10-01',
						'id' => '_records_start_date'
					]
				], [
					'type' => 'date',
					'attributes' => [
						'name' => $this->_lang->GET('audit.records.end_date'),
						'value' => $this->_date['usertime']->format('Y-m-d'),
						'id' => '_records_end_date'
					]
				], [
					'type' => 'textsection',
					'content' => $this->_lang->GET('audit.records.hint')
				], [
					'type' => 'button',
					'attributes' => [
						'value' => $this->_lang->GET('audit.records.export_csv'),
						'onclick' => "api.audit('get', 'export', '" . $this->_requestedType . "', document.getElementById('_records_start_date').value, document.getElementById('_records_end_date').value)",
						'data-type' => 'download'
					]
				]
			];
		}
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for all of records
	 */
	private function exportrecords(){
		$startDate = $this->convertToServerTime($this->_requestedDate) ? : '2023-10-01';
		$endDate = $this->convertToServerTime($this->_requestedTime ? : $this->_date['usertime']->format('Y-m-d'));

		$records = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');
		$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$result = [];
		// initiate all possible keys aka document fields
		$keys = [];
		$defaultColumn = [
			'identifier' => '_' . $this->_lang->GET('audit.records.identifier', [], true),
			'units' => '_' . $this->_lang->GET('audit.records.units', [], true),
			'from' => '_' . $this->_lang->GET('audit.records.start_date', [], true),
			'until' => '_' . $this->_lang->GET('audit.records.end_date', [], true),
			'type' => '_' . $this->_lang->GET('audit.records.type', [], true),
		];
		// iterate over all entries, create arrays with all available keys and append to result
		foreach ($records as $row){
			if (!in_array($row['context'], ['casedocumentation'])) continue;

			$line = [];
			$skip = false;
			$row['content'] = json_decode($row['content'], true);
			foreach ($row['content'] as $entry){
				$currentdate = substr($entry['date'], 0, 10);
				// check if entry is out of requested timestamp bound
				if ($currentdate < $startDate || $currentdate > $endDate) {
					$skip = true;
					break;
				}
				// set timespan information for record
				if (!isset($line[$defaultColumn['from']])) $line[$defaultColumn['from']] = $this->convertFromServerTime($currentdate);
				$line[$defaultColumn['until']] = $this->convertFromServerTime($currentdate);
				$document_name = '';
				if ($docid = array_search($entry['document'], array_column($documents, 'id'))){
					// get document title to add to issue
					$document_name = $documents[$docid]['name'] . ' - ';
				}

				if (gettype($entry['content']) === 'string') $entry['content'] = json_decode($entry['content'], true);
				// iterate over all entries, fill up result line with the most recent value
				foreach ($entry['content'] as $field => $input){
					$field = $document_name . str_replace('_', ' ', $field);
					if ($input) {
						if (!in_array($field, $keys)) $keys[] = $field;
						$line[$field] = $input;
					}
				}
			}
			if ($skip) continue;

			// complete default columns and append to result
			$line[$defaultColumn['identifier']] = $row['identifier'];
			$line[$defaultColumn['units']] = implode(', ', array_map(fn($v) => isset($this->_lang->_DEFAULT['units'][$v]) ? $this->_lang->_DEFAULT['units'][$v] : $v, explode(',', $row['units'])));
			$line[$defaultColumn['type']] = isset($this->_lang->_DEFAULT['record']['type'][$row['record_type']]) ? $this->_lang->_DEFAULT['record']['type'][$row['record_type']] : '';
			$result[] = $line;
		}

		// sort keys and unshift leading default columns
		sort($keys, SORT_REGULAR);
		array_unshift($keys,
			$defaultColumn['identifier'],
			$defaultColumn['units'],
			$defaultColumn['from'],
			$defaultColumn['until'],
			$defaultColumn['type'],
		);
	
		// write csv file
		$tempFile = UTILITY::directory('tmp') . '/' . $this->_date['usertime']->format('Y-m-d H-i-s ') . $this->_lang->_DEFAULT['audit']['checks_type']['records'] . '.csv';
		$file = fopen($tempFile, 'w');
		fwrite($file, b"\xEF\xBB\xBF"); // tell excel this is utf8
		// header
		fputcsv($file, $keys,
			CONFIG['csv']['dialect']['separator'],
			CONFIG['csv']['dialect']['enclosure'],
			CONFIG['csv']['dialect']['escape']);
		// rows
		foreach ($result as $line){
			// complete and sort line columns, unshift default columns
			foreach (array_diff($keys, array_keys($line)) as $nkey){
				$line[$nkey] = '';
			}
			ksort($line, SORT_REGULAR);
			$line = array_merge([
				$defaultColumn['identifier'] => $line[$defaultColumn['identifier']],
				$defaultColumn['units'] => $line[$defaultColumn['units']],
				$defaultColumn['from'] => $this->convertFromServerTime($line[$defaultColumn['from']]),
				$defaultColumn['until'] => $this->convertFromServerTime($line[$defaultColumn['until']]),
				$defaultColumn['type'] => $line[$defaultColumn['type']]
			], $line);

			// write to file
			fputcsv($file, $line,
			CONFIG['csv']['dialect']['separator'],
			CONFIG['csv']['dialect']['enclosure'],
			CONFIG['csv']['dialect']['escape']);
		}
		fclose($file);

		// provide downloadfile
		$downloadfiles[$this->_lang->GET('csvfilter.use.filter_download', [':file' => $this->_lang->_DEFAULT['audit']['checks_type']['records'] . '.csv'])] = [
			'href' => './api/api.php/file/stream/' . substr(UTILITY::directory('tmp'), 1) . '/' . pathinfo($tempFile)['basename'],
			'download' => $this->_lang->_DEFAULT['audit']['checks_type']['records'] . '.csv'
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}

	/**
	 *                   _     _
	 *   ___ ___ ___ _ _| |___| |_ ___ ___ _ _
	 *  |  _| -_| . | | | | .'|  _| . |  _| | |
	 *  |_| |___|_  |___|_|__,|_| |___|_| |_  |
	 *          |___|                     |___|
	 * returns regulatory items according to language.xx.ini and matches current assigned documents
	 */
	private function regulatory(){
		$content = [];
		// prepare existing document lists
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$hidden = $regulatory = [];
		foreach ($fd as $key => $row) {
			if (!PERMISSION::fullyapproved('documentapproval', $row['approval'])) continue;
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden)) {
				foreach (explode(',', $row['regulatory_context'] ? : '') as $regulatory_context){
					$satisfied = false;
					if (isset($regulatory[$regulatory_context])){
						foreach ($regulatory[$regulatory_context] as $key => $value){
							if (preg_match('/^' . $row['name'] . ' \(/', $key)) $satisfied = true;
						}
					}
					if (!$satisfied) $regulatory[$regulatory_context][$row['name'] . ' (' . $this->convertFromServerTime($row['date']) . ')'] = ['href' => "javascript:api.record('get', 'document', '" . $row['name'] . "')"];
				}
			}
		}
		// get active external documents
		if ($files = SQLQUERY::EXECUTE($this->_pdo, 'file_external_documents_get_active')) {
			foreach ($files as $file){
				foreach (explode(',', $file['regulatory_context']) as $context){
					if (preg_match('/^\.\.\//', $file['path'])){
						$file['path'] = './api/api.php/file/stream/' . substr($file['path'], 1);
					}
					$regulatory[$context][$file['path'] . ' (' . $file['activated'] . ')'] = ['href' => $file['path']];
				}
			}
		}

		// add export button
		if (PERMISSION::permissionFor('regulatoryoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.records.export'),
					'onclick' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
				]
			]
		];
		foreach ($this->_lang->_USER['regulatory'] as $key => $issue){
			if (isset($regulatory[$key])) $content[] = [
				'type' => 'links',
				'description' => $issue,
				'content' => $regulatory[$key]
			];
			else $content[] = [
				'type' => 'textsection',
				'attributes' => [
					'class' => 'red',
					'name' => $issue
				],
				'content' => $this->_lang->GET('audit.regulatory_warning_content')
			];
		}
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for the regulatory issue result
	 * processes the result of $this->regulatory() and translates the body object into more simple strings
	 */
	private function exportregulatory(){
		$summary = [
			'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $this->_lang->GET('audit.checks_type.regulatory') . '_' . $this->_date['usertime']->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.regulatory'),
			'date' => $this->convertFromServerTime($this->_date['usertime']->format('Y-m-d H:i'), true)
		];

		$issues = $this->regulatory();
		foreach ($issues as $item){
			if (!isset($item['type'])) continue;
			switch ($item['type']){
				case 'links':
					$summary['content'][$item['description']] = $item['content'];	
					break;
				case 'textsection':
					$summary['content'][$item['attributes']['name']] = $item['content'];	
					break;
			}
		}

		$downloadfiles = [];
		$PDF = new PDF(CONFIG['pdf']['record']);
		$downloadfiles[$this->_lang->GET('record.navigation.summaries')] = [
			'href' => './api/api.php/file/stream/' . $PDF->auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}

	/**
	 *       _     _
	 *   ___|_|___| |_ ___
	 *  |  _| |_ -| '_|_ -|
	 *  |_| |_|___|_,_|___|
	 *
	 * returns risks
	 */
	private function risks(){
		$content = $issues = [];

		$this->_requestedDate = $this->_requestedDate ? : $this->_date['usertime']->format('Y-m-d');
		$this->_requestedTime = $this->_requestedTime ? : $this->_date['usertime']->format('H:i:59');
		$requestedTimestamp = $this->convertToServerTime($this->_requestedDate . ' ' . $this->_requestedTime);

		// prepare existing risks lists
		$risks = SQLQUERY::EXECUTE($this->_pdo, 'risk_datalist');

		// gathering and distributing entry properties
		$entries = [];
		foreach ($risks as $risk){
			if ($risk['date'] >= $requestedTimestamp) continue;
			if ($risk['hidden']) {
				$risk['hidden'] = json_decode($risk['hidden'], true);
				if ($risk['hidden']['date'] <= $requestedTimestamp)
					continue;
			}

			if (!isset($entries[$risk['process']])) $entries[$risk['process']] = ['characteristic' => [], 'risk' => [], 'assignmenterror' => []];
			$risk['risk'] = explode(',', $risk['risk'] ? : '');
			// detect key errors of risks in case of faulty template imports or changes within languagefile
			if ($missing_assignments = array_filter(array_diff($risk['risk'], array_keys($this->_lang->_USER['risks'])), fn($v) => boolval($v))){
				switch($risk['type']){
					case 'characteristic': // implement further cases if suitable, according to languagefile
						$entries[$risk['process']]['assignmenterror'][] = $this->_lang->_USER['risk']['type'][$risk['type']] . ': ' . $risk['measure'] . ' - ' . implode(', ', $missing_assignments);
						break;
					default: //risks
						$entries[$risk['process']]['assignmenterror'][] = $this->_lang->_USER['risk']['type'][$risk['type']] . ': ' . $risk['cause'] . ' - ' . $risk['effect'] . ' - ' . implode(', ', $missing_assignments);
				}
			}
			// append assigned risks to type
			array_push($entries[$risk['process']][$risk['type']], ...$risk['risk']);
		}

		// match required characteristics risks with actual risks
		$missing = [];
		foreach ($entries as $process => $properties){
			if (!isset($missing[$process])) $missing[$process] = ['characteristic' => [], 'risk' => [], 'assignmenterror' => $properties['assignmenterror']];
			if (!$properties['characteristic'])
				array_push($missing[$process]['characteristic'], ...$properties['risk']); // risks are probably set but not yet defined as required
			else {
				// compare key beginnings to match main risk groups according to languagefile
				foreach ($properties['characteristic'] as $risk){
					$properties['risk'] = array_filter($properties['risk'], fn($v) => !str_starts_with($v, $risk));				
				}
				if ($properties['risk']){ // remaining if not filtered out completely
					$missing[$process]['risk'] = array_diff($properties['characteristic'], $properties['risk']); // all required but not present risks
					$missing[$process]['characteristic'] = array_diff($properties['risk'], $properties['characteristic']); // all set risks not required by characteristics
				}
			}
		}

		// render issues with translated risks or literal property values
		$issues = [];
		foreach ($missing as $process => $properties){
			foreach ($properties as $key => $value) {
				$value = array_unique($value);
				if (!$value) continue;

				$issuecontent = implode("\n", $value);
				if (!in_array($key, ['assignmenterror'])) $issuecontent = implode("\n", array_values(array_map(fn($r) => isset($this->_lang->_USER['risks'][$r]) ? $this->_lang->_USER['risks'][$r] : null, $value)));
				$issues[] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('audit.risk_issues.' . $key, [':process' => $process])
					],
					'content' => $issuecontent
				];
			}
		}
		if (!$issues) {
			$issues = $this->noContentAvailable($this->_lang->GET('audit.risk_issues_none'))[0];
		}

		// add export button
		if (PERMISSION::permissionFor('regulatoryoperation')) $content[] = [
			[
				'type' => 'date',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents.date'),
					'value' => $this->_requestedDate,
					'id' => '_documents_date'
				]
			], [
				'type' => 'time',
				'attributes' => [
					'name' => $this->_lang->GET('audit.documents.time'),
					'value' => $this->_requestedTime,
					'id' => '_documents_time' 
				]
			], [
				'type' => 'button',
				'attributes' => [
					'data-type' => 'generateupdate',
					'value' => $this->_lang->GET('audit.risks_update_button'),
					'onclick' => "api.audit('get', 'checks', 'risks', document.getElementById('_documents_date').value, document.getElementById('_documents_time').value)"
				]
			], [
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.records.export'),
					'onclick' => "api.audit('get', 'export', '" . $this->_requestedType . "', document.getElementById('_documents_date').value, document.getElementById('_documents_time').value)",
					'data-type' => 'download'
				]
			]
		];

		$content[] = [
			'type' => 'textsection',
			'attributes' => [
				'name' => $this->_lang->GET('audit.risk_issues_report', [':date' => $this->convertFromServerTime($requestedTimestamp, true)])
			]		
		];
		
		array_push($content, ...$issues);
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for risks
	 * processes the result of $this->risks() and translates the body object into more simple strings
	 */
	private function exportrisks(){
		$summary = [
			'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $this->_lang->GET('audit.checks_type.risks') . '_' . $this->_date['usertime']->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.risks'),
			'date' => $this->convertFromServerTime($this->_date['usertime']->format('Y-m-d H:i'), true)
		];
		$downloadfiles = [];
		$requestedTimestamp = $this->convertToServerTime($this->_requestedDate . ' ' . $this->_requestedTime);

		// render issue list for pdf export
		$issues = $this->risks();
		foreach ($issues as $issue){
			if (!isset($issue['type'])) continue;
			if ($issue['type'] === 'textsection' && isset($issue['attributes']['name'])) $summary['content'][$issue['attributes']['name']] = isset($issue['content']) ? $issue['content'] : ' ';	
		}
		if (count($summary['content']) > 1){
			$PDF = new PDF(CONFIG['pdf']['record']);
			$downloadfiles[$this->_lang->GET('audit.risk_issues_none')] = [
				'href' => './api/api.php/file/stream/' . $PDF->auditPDF($summary)
			];
		}

		// create risk dump as xlsx, sheetwise processes

		// prepare existing risks lists
		$risks = SQLQUERY::EXECUTE($this->_pdo, 'risk_datalist');

		// gathering and distributing entry properties
		$entries = [];

		foreach ($risks as $risk){
			if ($risk['date'] >= $requestedTimestamp) continue;
			if ($risk['hidden']) {
				$risk['hidden'] = json_decode($risk['hidden'], true);
				if ($risk['hidden']['date'] <= $requestedTimestamp)
					continue;
			}

			// translate
			$risk['risk'] = implode("\n", array_values(array_map(fn($r) => isset($this->_lang->_DEFAULT['risks'][$r]) ? $this->_lang->_DEFAULT['risks'][$r] : $r, explode(',', $risk['risk'] ? : ''))));
			$risk['relevance'] = $risk['relevance'] ? $this->_lang->GET('risk.relevance_yes', [], true) : $this->_lang->GET('risk.relevance_no', [], true);

			// sort to process and type, consider form fields and names within GET RISK->risk() as well
			if (!isset($entries[$risk['process']])) $entries[$risk['process']] = [];
			if (!isset($entries[$risk['process']][$this->_lang->_DEFAULT['risk']['type'][$risk['type']]])) $entries[$risk['process']][$this->_lang->_DEFAULT['risk']['type'][$risk['type']]] = [];
			switch($risk['type']){
				case 'characteristic': // implement further cases if suitable, according to languagefile
					$entries[$risk['process']][$this->_lang->_DEFAULT['risk']['type'][$risk['type']]][] = [
						$this->_lang->GET('risk.type.characteristic', [], true) => $risk['measure'] ? : '',
						$this->_lang->GET('risk.relevance', [], true) => $risk['relevance'],
						$this->_lang->GET('risk.cause', [], true) => $risk['cause'] ? : '',
						$this->_lang->GET('risk.risk_related', [], true) => $risk['risk'],
						$this->_lang->GET('audit.risk_export_column.author', [], true) => $risk['author'] ? $this->_lang->GET('risk.author', [':author' => $risk['author'], ':date' => $this->convertFromServerTime($risk['date'], true)], true) : ''
					];
					break;
				default: //risks
					$entries[$risk['process']][$this->_lang->_DEFAULT['risk']['type'][$risk['type']]][] = [
						$this->_lang->GET('risk.risk_related', [], true) => $risk['risk'],
						$this->_lang->GET('risk.relevance', [], true) => $risk['relevance'],
						$this->_lang->GET('risk.cause', [], true) => $risk['cause'] ? : '',
						$this->_lang->GET('risk.effect', [], true) => $risk['effect'] ? : '',
						$this->_lang->GET('risk.probability', [], true) => $this->_lang->_DEFAULT['risk']['probabilities'][min($risk['probability'], count($this->_lang->_DEFAULT['risk']['probabilities'])) - 1],
						$this->_lang->GET('risk.damage', [], true) => $this->_lang->_DEFAULT['risk']['damages'][min($risk['damage'], count($this->_lang->_DEFAULT['risk']['damages'])) - 1],
						$this->_lang->GET('audit.risk_export_column.acceptancelevel', [], true) => $risk['probability'] * $risk['damage'] > CONFIG['limits']['risk_acceptance_level'] ? $this->_lang->GET('risk.acceptance_level_above', [], true) : $this->_lang->GET('risk.acceptance_level_below', [], true),
						$this->_lang->GET('risk.measure', [], true) => $risk['measure'] ? : '',
						$this->_lang->GET('risk.measure_probability', [], true) => $this->_lang->_DEFAULT['risk']['probabilities'][min($risk['measure_probability'], count($this->_lang->_DEFAULT['risk']['probabilities'])) - 1],
						$this->_lang->GET('risk.measure_damage', [], true) => $this->_lang->_DEFAULT['risk']['damages'][min($risk['measure_damage'], count($this->_lang->_DEFAULT['risk']['damages'])) - 1],
						$this->_lang->GET('audit.risk_export_column.measureacceptancelevel', [], true) => $risk['measure_probability'] * $risk['measure_damage'] > CONFIG['limits']['risk_acceptance_level'] ? $this->_lang->GET('risk.acceptance_level_above', [], true) : $this->_lang->GET('risk.acceptance_level_below', [], true),
						$this->_lang->GET('risk.risk_benefit', [], true) => $risk['risk_benefit'] ? : '',
						$this->_lang->GET('risk.measure_remainder', [], true) => $risk['measure_remainder'] ? : '',
						$this->_lang->GET('risk.proof', [], true) => $risk['proof'] ? implode("\n", explode(', ', $risk['proof'])): '',
						$this->_lang->GET('audit.risk_export_column.author', [], true) => $risk['author'] ? $this->_lang->GET('risk.author', [':author' => $risk['author'], ':date' => $this->convertFromServerTime($risk['date'])], true) : ''
					];
			}
		}
		if ($entries){
			$tempFile = UTILITY::directory('tmp') . '/' . $summary['filename'] . '_' . time() . '.xlsx';
			$writer = new \XLSXWriter();
			$writer->setAuthor($_SESSION['user']['name']); 
			foreach ($entries as $process => $types){
				foreach ($types as $type => $lines){
					// write each to xlsx sheet
					$sheetname = '';
					preg_match_all('/\w+/', $process . ' ' . $type, $words);
					foreach ($words[0] as $word){
						$sheetname .= substr($word, 0, 1) . preg_replace('/[\Waeiou]/i', '', substr($word, 1));
					}
					$writer->writeSheetRow($sheetname, [$process, $type, $this->_lang->GET('audit.risk_export_column.effective_date', [':date' => $this->convertFromServerTime($requestedTimestamp, true)], true)]);
					$writer->writeSheetRow($sheetname, []);

					$writer->writeSheetRow($sheetname, array_keys($lines[0]));
					foreach ($lines as $line)
						$writer->writeSheetRow($sheetname, array_values($line));				
				}
			}
			$writer->writeToFile($tempFile);

			// provide downloadfile			
			$downloadfiles[$this->_lang->GET('csvfilter.use.filter_download', [':file' => pathinfo($tempFile)['basename']])] = [
				'href' => './api/api.php/file/stream/' . substr(UTILITY::directory('tmp'), 1) . '/' . pathinfo($tempFile)['basename']
			];
		}

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}
	
	/**
	 *   _           _     _                     _         _   _         
	 *  | |_ ___ ___|_|___|_|___ ___ ___ _ _ ___| |_ _ ___| |_|_|___ ___ 
	 *  |  _|  _| .'| |   | |   | . | -_| | | .'| | | | .'|  _| | . |   |
	 *  |_| |_| |__,|_|_|_|_|_|_|_  |___|\_/|__,|_|___|__,|_| |_|___|_|_|
	 *                          |___|   
	 */
	private function trainingevaluation(){
		if ($_SERVER['REQUEST_METHOD']==='PUT' && PERMISSION::permissionFor('trainingevaluation')){
			$user = null;
			$training = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get', [
				'values' => [
					':id' => $this->_requestedID
				]
			]);
			$training = $training ? $training[0] : [];
			if ($training) $user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
				'replacements' => [
					':id' => intval($training['user_id']),
					':name' => ''
				]
			]);
			$user = $user ? $user[0] : null;

			if ($training && $user &&
				(PERMISSION::permissionFor('trainingevaluation') &&
				(array_intersect(array_filter(PERMISSION::permissionFor('trainingevaluation', true), fn($permission) => $permission === 'supervisor'), $_SESSION['user']['permissions']) ||
				(array_intersect(['supervisor'], $_SESSION['user']['permissions']) && array_intersect(explode(',', $user['units']), $_SESSION['user']['units']))))
			) {
				foreach ($this->_payload as $key => &$value){
					if (gettype($value) === 'array') $value = trim(implode(' ', $value));
					/////////////////////////////////////////
					// BEHOLD! unsetting value==on relies on a prepared formdata/_payload having a dataset containing all selected checkboxes
					////////////////////////////////////////
					if (!$value || $value == 'on') unset($this->_payload->$key);
				}
				if ((array) $this->_payload)
					SQLQUERY::EXECUTE($this->_pdo, 'user_training_put', [
						'values' => [
							':id' => $this->_requestedID,
							':evaluation' => UTILITY::json_encode([
								'user' => $_SESSION['user']['name'],
								'date' => $this->_date['servertime']->format('Y-m-d H:i'),
								'content' => (array) $this->_payload
							])
						]
					]);
			}
		}

		$content = [];
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', array_column($users, 'id'))
			]
		]);
		$trainings = $trainings ? array_values($trainings) : [];

		$options = [
			$this->_lang->GET('audit.userskills.training_evaluation_pending') => ['onchange' => "api.audit('get', 'checks', 'trainingevaluation')"],
			$this->_lang->GET('audit.userskills.training_evaluation_closed') => ['onchange' => "api.audit('get', 'checks', 'trainingevaluation', 'closed')"],
			$this->_lang->GET('audit.userskills.training_evaluation_all') => ['onchange' => "api.audit('get', 'checks', 'trainingevaluation', 'all')"],
		];
		if (!$this->_requestedOption) $options[$this->_lang->GET('audit.userskills.training_evaluation_pending')]['checked'] = true;
		if ($this->_requestedOption === 'closed') $options[$this->_lang->GET('audit.userskills.training_evaluation_closed')]['checked'] = true;
		if ($this->_requestedOption === 'all') $options[$this->_lang->GET('audit.userskills.training_evaluation_all')]['checked'] = true;
		$content[] = [
			'type' => 'radio',
			'attributes' => [
				'name' => $this->_lang->GET('audit.userskills.training_evaluation_display')
			],
			'content' => $options
		];
		require_once('_shared.php');
		$sharedfunction = new SHARED($this->_pdo, $this->_date);
		$evaluationdocument = $sharedfunction->recentdocument('document_document_get_by_context', [
			'values' => [
				':context' => 'training_evaluation_document'
			]])['content'];

		foreach ($users as $user){
			if (
				PERMISSION::filteredUser($user) ||
				!(PERMISSION::permissionFor('trainingevaluation') &&
				(array_intersect(array_filter(PERMISSION::permissionFor('trainingevaluation', true), fn($permission) => $permission === 'supervisor'), $_SESSION['user']['permissions']) ||
				(array_intersect(['supervisor'], $_SESSION['user']['permissions']) && array_intersect(explode(',', $user['units']), $_SESSION['user']['units']))))
			) continue;

			$content[] = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => $user['name']
					],
					'content' => ' '
				]
			];
			$user_id = $user['id'];
			if ($usertrainings = array_filter($trainings, function ($row) use($user_id){
				return $row['user_id'] === $user_id;
			})){
				foreach ($usertrainings as $row){
					if (!$this->_requestedOption && $row['evaluation']) continue;
					if ($this->_requestedOption === 'closed' && !$row['evaluation']) continue;

					$attributes = ['data-type' => 'skill', 'name' => $this->_lang->GET('user.training.display') . ' ' . $row['name'] . ' ' . $this->convertFromServerTime($row['date'])];
					if ($row['expires']){
						$expire = new \DateTime($row['expires']);
						if ($expire < $this->_date['servertime']) $attributes['class'] = 'red';
						else {
							$expire->modify('-' . CONFIG['lifespan']['training_renewal'] . ' days');
							if ($expire < $this->_date['servertime']) $attributes['class'] = 'orange';
						}
					}

					if ($row['evaluation']){
						$row['evaluation'] = json_decode($row['evaluation'], true);
						$evaluation = $this->_lang->GET('audit.userskills.training_evaluation', [
							':user' => $row['evaluation']['user'],
							':date' => $this->convertFromServerTime($row['evaluation']['date'], true),
							':evaluation' => implode(" \n", array_map(fn($key, $value) => $key . ': ' . $value, array_keys($row['evaluation']['content']), $row['evaluation']['content']))
						]);
					} else $evaluation = $this->_lang->GET('audit.userskills.training_evaluation_pending');

					if ($row['planned']){
						$row['planned'] = json_decode($row['planned'], true);
						$planned = $this->_lang->GET('audit.userskills.training_scheduled', [
							':user' => $row['planned']['user'],
							':date' => $this->convertFromServerTime($row['planned']['date'], true),
							':scheduled' => implode(" \n", array_map(fn($key, $value) => $key . ': ' . $value, array_keys($row['planned']['content']), $row['planned']['content']))
						]);
					} else $planned = '';

					$content[count($content) - 1][] = [
						'type' => 'textsection',
						'content' => ($row['expires'] ? $this->_lang->GET('user.training.add_expires') . ' ' . $this->convertFromServerTime($row['expires']) : '')
							. ($planned ? ($row['expires'] ? " \n" : '') . $planned : '')
							. ($evaluation ? ($row['expires'] || $planned ? " \n" : '') . $evaluation : ''),
						'attributes' => $attributes
					];
					if ($row['file_path']) $content[count($content) - 1][] = [
						'type' => 'links',
						'content' => [
							$row['file_path'] => ['href' => './api/api.php/file/stream/' . $row['file_path']]
						]
					];

					if ($planned) $content[count($content) - 1][] = [
						'type' => 'button',
						'attributes' => [
							'value' => $this->_lang->GET('user.training.edit'),
							'class' => 'inlinebutton',
							'onclick' => "api.user('get', 'training', " . $row['id'] . ")"
						]
					];
					else $content[count($content) - 1][] = [
						'type' => 'button',
						'attributes' => [
							'value' => $this->_lang->GET('audit.checks_type.trainingevaluation'),
							'onclick' => "new _client.Dialog({type: 'input', header: '" . $this->_lang->GET('audit.checks_type.trainingevaluation') . " " .$row['name']. " " .$user['name'] . "', render: JSON.parse('" . UTILITY::json_encode(
								$sharedfunction->populatedocument($evaluationdocument, $row['evaluation'] ? $row['evaluation']['content'] : [])
							) . "'), options:{".
							"'" . $this->_lang->GET('general.cancel_button') . "': false,".
							"'" . $this->_lang->GET('general.ok_button')  . "': {value: true, class: 'reducedCTA'},".
							"}}).then(response => {if (response) api.audit('put', 'checks', 'trainingevaluation', '" . $row['id'] . "', _client.application.dialogToFormdata(response))})"
						]
					];
				}
			}
		}
		foreach ($content as $index => $set){
			if (count($set) < 2) unset($content[$index]);
		}

		return $content;
	}

	/**
	 *                                       _
	 *   _ _ ___ ___ ___ ___ _ _ ___ ___ ___|_|___ ___ ___ ___
	 *  | | |_ -| -_|  _| -_|_'_| . | -_|  _| | -_|   |  _| -_|
	 *  |___|___|___|_| |___|_,_|  _|___|_| |_|___|_|_|___|___|
	 *                          |_|
	 * returns all user experience points by year
	 */
	private function userexperience(){
		// add export button
		if (PERMISSION::permissionFor('regulatoryoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.records.export'),
					'onclick' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
				]
			]
		];
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', array_column($users, 'id'))
			]
		]);
		$trainings = $trainings ? array_values($trainings) : [];
		foreach ($users as $user){ // ordered by name
			if (PERMISSION::filteredUser($user)) continue;
			$user_id = $user['id'];
			if ($usertrainings = array_filter($trainings, function ($row) use($user_id){
				return $row['user_id'] === $user_id;
			})){
				$years = [];
				foreach ($usertrainings as $row){
					if (!$row['date']) continue;
					$year = substr($row['date'], 0, 4);
					if ($row['experience_points']){
						if (!isset($years[$year])) $years[$year] = ['xp' => 0, 'paths' => []];
						$years[$year]['xp'] += $row['experience_points'];
						if ($row['file_path']) $years[$year]['paths'][$row['name'] . ' ' . $this->convertFromServerTime($row['date'], true)] = ['href' => './api/api.php/file/stream/' . $row['file_path']];
					}
				}
				if ($years){
					$usercontent = [[
						'type' => 'textsection',
						'attributes' => [
							'name' => $user['name']
						],
					]];
					foreach ($years as $year => $summary){
						$usercontent[] = [
							'type' => 'links',
							'description' => $this->_lang->GET('audit.experience_points', [':number' => $summary['xp'], ':year' => $year]),
							'content' => $summary['paths']
						];
					}
					if ($usercontent) $content = [
						...$content,
						$usercontent
					];
				}
			}
		}
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for users experience points
	 * processes the result of $this->userexperience() and translates the body object into more simple strings
	 */
	private function exportuserexperience(){
		$summary = [
			'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $this->_lang->GET('audit.checks_type.userexperience') . '_' . $this->_date['usertime']->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.userexperience'),
			'date' => $this->convertFromServerTime($this->_date['usertime']->format('Y-m-d H:i'), true)
		];

		$experience = $this->userexperience();

		for($i = 1; $i < count($experience); $i++){
			foreach ($experience[$i] as $item){
				if ($item['type'] === 'textsection') {
					$previous = $item['attributes']['name'];
				}
				if ($item['type'] === 'links') {
					$summary['content'][$previous] = $item['attributes']['name'];
					$summary['content'][$previous] .= "\n" . implode("\n", array_keys($item['content']));
				}
			}
		}
		$downloadfiles = [];
		$PDF = new PDF(CONFIG['pdf']['record']);
		$downloadfiles[$this->_lang->GET('record.navigation.summaries')] = [
			'href' => './api/api.php/file/stream/' . $PDF->auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}

	/**
	 *                       _   _ _ _
	 *   _ _ ___ ___ ___ ___| |_|_| | |___
	 *  | | |_ -| -_|  _|_ -| '_| | | |_ -|
	 *  |___|___|___|_| |___|_,_|_|_|_|___|
	 *
	 * returns all users with their skills and trainings
	 * batch insert and plan trainings
	 */
	private function userskills(){
		$content = [];

		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');

		$organizational_units = [];
		$organizational_units[$this->_lang->GET('assemble.render.mine')] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'onchange' => "api.audit('get', 'checks', 'userskills')"];
		foreach ($this->_lang->_USER['units'] as $unit => $description){
			$organizational_units[$description] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'onchange' => "api.audit('get', 'checks', 'userskills', '" . $unit . "')"];
		}
		if (!$this->_requestedOption) $organizational_units[$this->_lang->GET('assemble.render.mine')]['checked'] = true;
		else $organizational_units[$this->_lang->GET('units.' . $this->_requestedOption)]['checked'] = true;
		$content[] = [
			[
				'type' => 'radio',
				'attributes' => [
					'name' => $this->_lang->GET('order.organizational_unit')
				],
				'content' => $organizational_units
			]
		];

		// add export button
		if (PERMISSION::permissionFor('regulatoryoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.records.export'),
					'onclick' => "api.audit('get', 'export', '" . $this->_requestedType . "', '" . $this->_requestedOption . "')",
					'data-type' => 'download'
				]
			]
		];
		$unfulfilledskills = [];
		foreach ($this->_lang->_USER['skills'] as $duty => $skills){
			if ($duty === '_LEVEL') continue;
			foreach ($skills as $skill => $skilldescription){
				if ($skill === '_DESCRIPTION') continue;
				$unfulfilledskills[] = $this->_lang->GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription;
			}
		}
		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', array_column($users, 'id'))
			]
		]);
		$trainings = $trainings ? array_values($trainings) : [];
		foreach ($users as $user){
			if (PERMISSION::filteredUser($user)) continue;
			$user['units'] = explode(',', $user['units'] ? : '');

			// construct fulfilled skills
			$user['skills'] = explode(',', $user['skills'] ?  : '');
			$skillmatrix = '';
			foreach ($this->_lang->_USER['skills'] as $duty => $skills){
				if ($duty === '_LEVEL') continue;
				foreach ($skills as $skill => $skilldescription){
					if ($skill === '_DESCRIPTION') continue;
					foreach ($this->_lang->_USER['skills']['_LEVEL'] as $level => $leveldescription){
						if (in_array($duty . '.' . $skill . '.' . $level, $user['skills'])){
							$skillmatrix .=  $this->_lang->GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription . ': ' . $leveldescription . " \n";
							unset($unfulfilledskills[array_search($this->_lang->GET('skills.' . $duty . '._DESCRIPTION') . ' ' . $skilldescription, $unfulfilledskills)]);
						}
					}
				}
			}

			if (!(
				in_array($this->_requestedOption, $user['units']) ||
				((!$this->_requestedOption || $this->_requestedOption == 'null') && array_intersect($_SESSION['user']['units'], $user['units']))
			)) continue;

			$content[] = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => $user['name']
					],
					'content' => $skillmatrix
				]
			];

			// get individual trainings
			$user_id = $user['id'];
			if ($usertrainings = array_filter($trainings, function ($row) use($user_id){
				return $row['user_id'] === $user_id;
			})){
				foreach ($usertrainings as $row){
					$attributes = ['data-type' => 'skill', 'name' => $this->_lang->GET('user.training.display') . ' ' . $row['name'] . ' ' . $this->convertFromServerTime($row['date'])];
					if ($row['expires']){
						$expire = new \DateTime($row['expires']);
						if ($expire < $this->_date['servertime']) $attributes['class'] = 'red';
						else {
							$expire->modify('-' . CONFIG['lifespan']['training_renewal'] . ' days');
							if ($expire < $this->_date['servertime']) $attributes['class'] = 'orange';
						}
					}
					if ($row['evaluation']){
						$row['evaluation'] = json_decode($row['evaluation'], true);
						$evaluation = $this->_lang->GET('audit.userskills.training_evaluation', [
							':user' => $row['evaluation']['user'],
							':date' => $this->convertFromServerTime($row['evaluation']['date'], true),
							':evaluation' => implode(" \n", array_map(fn($key, $value) => $key . ': ' . $value, array_keys($row['evaluation']['content']), $row['evaluation']['content']))
						]);
					} else $evaluation = $this->_lang->GET('audit.userskills.training_evaluation_pending');
					
					if ($row['planned']){
						$row['planned'] = json_decode($row['planned'], true);
						$planned = $this->_lang->GET('audit.userskills.training_scheduled', [
							':user' => $row['planned']['user'],
							':date' => $this->convertFromServerTime($row['planned']['date'], true),
							':scheduled' => implode(" \n", array_map(fn($key, $value) => $key . ': ' . $value, array_keys($row['planned']['content']), $row['planned']['content']))
						]);
					} else $planned = '';

					$content[count($content) - 1][] = [
						'type' => 'textsection',
						'content' => ($row['expires'] ? $this->_lang->GET('user.training.add_expires') . ' ' . $this->convertFromServerTime($row['expires']) : '')
							. ($planned ? ($row['expires'] ? " \n" : '') . $planned : '')
							. ($evaluation ? ($row['expires'] || $planned ? " \n" : '') . $evaluation : ''),
						'attributes' => $attributes
					];
					if ($row['file_path']) $content[count($content) - 1][] = [
						'type' => 'links',
						'content' => [
							$row['file_path'] => ['href' => './api/api.php/file/stream/' . $row['file_path']]
						]
					];

					if ($planned) $content[count($content) - 1][] = [
						'type' => 'button',
						'attributes' => [
							'value' => $this->_lang->GET('user.training.edit'),
							'class' => 'inlinebutton',
							'onclick' => "api.user('get', 'training', " . $row['id'] . ")"
						]
					];
				}	
			}

			if ((array_intersect($_SESSION['user']['permissions'], PERMISSION::permissionFor('regulatory', true)) === ['supervisor']
				&& array_intersect($_SESSION['user']['units'], $user['units']))
				|| count(array_intersect($_SESSION['user']['permissions'], PERMISSION::permissionFor('regulatory', true))) > 1 || array_intersect(['admin'], $_SESSION['user']['permissions'])
				){
					$content[count($content) - 1][] = [
						'type' => 'button',
						'attributes' => [
							'value' => $this->_lang->GET('user.training.add_training'),
							'onclick' => "api.user('get', 'training', 'null', " . $user['id'] . ")"
						]
					];
			}
		}
		if (count(array_intersect($_SESSION['user']['permissions'], PERMISSION::permissionFor('regulatory', true))) > 1 || array_intersect(['admin'], $_SESSION['user']['permissions'])){
			array_splice($content, 1, 0, [[
				[
					'type' => 'button',
					'attributes' => [
						'value' => $this->_lang->GET('audit.userskills.bulk_training'),
						'onclick' => "api.user('get', 'training')"
					]
				]
			]]);
		}

		if ($unfulfilledskills){
			$content = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('audit.userskills.warning_description')
					],
					'content' => implode(', ', $unfulfilledskills)
				],
				...$content
			];
		}
		return $content;
	}

	/**
	 * returns all skills and matching users
	 */
	private function skillfulfilment(){
		$content = $allskills = [];
		foreach ($this->_lang->_USER['skills'] as $duty => $skills){
			if ($duty === '_LEVEL') continue;
			foreach ($skills as $skill => $skilldescription){
				if ($skill === '_DESCRIPTION') continue;
				$allskills[$duty . '.' . $skill] = [];
			}
		}
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		foreach ($users as $user){
			if (PERMISSION::filteredUser($user)) continue;

			$user['skills'] = explode(',', $user['skills'] ?  : '');
			foreach ($user['skills'] as $skill){
				$level = substr($skill, strrpos($skill, '.') + 1);
				$allskills[substr($skill, 0, strrpos($skill, '.'))][] = $user['name'] . ($level ? ' ' . $this->_lang->_USER['skills']['_LEVEL'][$level] : '');
			}
		}
		foreach ($allskills as $skill => $skilledusers){
			if (!$skill) continue;
			$skill = explode('.', $skill);
			$content[] = [
				[
					'type' => 'textsection',
					'content' => $skilledusers ? implode(', ', $skilledusers) : $this->_lang->GET('audit.skillfulfilment_warning'),
					'attributes' => $skilledusers ? ['name' => $this->_lang->GET('skills.' . $skill[0] . '._DESCRIPTION') . ' ' . $this->_lang->GET('skills.' . $skill[0] . '.' . $skill[1])] : ['class' => 'red', 'name' => $this->_lang->GET('skills.' . $skill[0] . '._DESCRIPTION') . ' ' . $this->_lang->GET('skills.' . $skill[0] . '.' . $skill[1])]
				]
			];
		}

		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [ 
			'replacements' => [
				':ids' => implode(',', array_column($users, 'id'))
			]
		]);
		$trainings = $trainings ? array_values($trainings) : [];
		$allskills = [];
		foreach ($trainings as $training){
			if (!isset($allskills[$training['name']])) $allskills[$training['name']] = [];
			if (!$training['date'] || ($training['expires'] && $training['expires'] < $this->_date['servertime']->format('Y-m-d'))) continue;
			if (($user = array_search($training['user_id'], array_column($users, 'id'))) !== false) $allskills[$training['name']][] = $users[$user]['name'];
		}
		foreach ($allskills as $skill => $skilledusers){
			$content[] = [
				[
					'type' => 'textsection',
					'content' => $skilledusers ? implode(', ', $skilledusers) : $this->_lang->GET('audit.skillfulfilment_warning'),
					'attributes' => $skilledusers ? ['name' => $skill] : ['class' => 'red', 'name' => $skill]
				]
			];
		}
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for user skills and trainings
	 * processes the result of $this->userskills() and translates the body object into more simple strings
	 */
	private function exportuserskills(){
		$summary = [
			'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $this->_lang->GET('audit.checks_type.userskills') . '_' . $this->_date['usertime']->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.userskills'),
			'date' => $this->convertFromServerTime($this->_date['usertime']->format('Y-m-d H:i'), true)
		];

		$skills = $this->userskills();

		$summary['content'][''] = (!$this->_requestedOption || $this->_requestedOption == 'null') ? implode(', ', array_map(fn($u) => $this->_lang->_DEFAULT['units'][$u], $_SESSION['user']['units'])) : $this->_lang->_DEFAULT['units'][$this->_requestedOption];

		for($i = 1; $i < count($skills); $i++){
			foreach ($skills[$i] as $item){
				if ($item['type'] === 'textsection' && isset($item['attributes']['name'])) {
					$summary['content'][$item['attributes']['name']] = $item['content'];
					$previous = $item['attributes']['name'];
				}
				if ($item['type'] === 'links') $summary['content'][$previous] .= "\n" . implode("\n", array_keys($item['content']));
			}
		}
		$downloadfiles = [];
		$PDF = new PDF(CONFIG['pdf']['record']);
		$downloadfiles[$this->_lang->GET('record.navigation.summaries')] = [
			'href' => './api/api.php/file/stream/' . $PDF->auditPDF($summary)
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}

	/**
	 *                 _
	 *   _ _ ___ ___ _| |___ ___ ___
	 *  | | | -_|   | . | . |  _|_ -|
	 *   \_/|___|_|_|___|___|_| |___|
	 *
	 * returns all current active vendors with stored info, most recent pricelist import, MDR sample check and certificate details in alphabetical order
	 */
	private function vendors(){
		$vendors = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
		$lastchecks = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_last_checked');
		$vendor_info = [
			'purchase_info' => 'consumables.vendor.purchase_info',
			'infotext' => 'consumables.vendor.info',
			'mail' => 'consumables.vendor.mail',
			'phone' => 'consumables.vendor.phone',
			'address' => 'consumables.vendor.address',
			'sales_representative' => 'consumables.vendor.sales_representative',
			'customer_id' => 'consumables.vendor.customer_id',
		];

		// add export button
		if (PERMISSION::permissionFor('regulatoryoperation')) $content[] = [
			[
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('audit.records.export'),
					'onclick' => "api.audit('get', 'export', '" . $this->_requestedType . "')",
					'data-type' => 'download'
				]
			]
		];
		foreach ($vendors as $vendor){
			$info = '';
			if ($vendor['hidden']) continue;
			if ($vendor['info']) {
				$vendor['info'] = json_decode($vendor['info'] ? : '', true);
				$vendor['info'] = array_filter($vendor['info'], function($value){return $value;});
				$info .= implode(" \n", array_map(Fn($key, $value) => $value ? $this->_lang->GET($vendor_info[$key]) . ': ' . $value : false, array_keys($vendor['info']), $vendor['info'])) . "\n";
			}
			$vendor['pricelist'] = isset($vendor['pricelist']) ? $vendor['pricelist'] : [];
			$pricelist = json_decode($vendor['pricelist'] ? : '', true);
			if (isset($pricelist['validity']) && $pricelist['validity']) $info .= $this->_lang->GET('consumables.vendor.pricelist_validity') . ' ' . $this->convertFromServerTime($pricelist['validity'], true) . "\n";
			if (($samplecheck = array_search($vendor['id'], array_column($lastchecks, 'vendor_id'))) !== false) $info .= $this->_lang->GET('audit.checks_type.mdrsamplecheck') . ' ' . $this->convertFromServerTime($lastchecks[$samplecheck]['checked'], true) . "\n";
			$certificate = json_decode($vendor['certificate'] ? : '', true);
			if (isset($certificate['validity']) && $certificate['validity']) $info .= $this->_lang->GET('consumables.vendor.certificate_validity') . ' ' . $certificate['validity'] . "\n";
			if ($vendor['evaluation']){
				$vendor['evaluation'] = json_decode($vendor['evaluation'] ? : '', true) ? : [];
				foreach ($vendor['evaluation'] as $evaluation){
					$info .= " \n". $this->_lang->GET('consumables.vendor.last_evaluation', [':author' => $evaluation['_author'], ':date' => $this->convertFromServerTime($evaluation['_date'], true)]) . "\n";
					unset($evaluation['_author'], $evaluation['_date']);
					foreach ($evaluation as $key => $value) $info .= str_replace('_', ' ', $key) . ': ' . $value . "\n";	
				}
			}

			$content[] = [
				[
					'type' => 'textsection',
					'attributes' => [
						'name' => $vendor['name']
					],
					'content' => $info
				]
			];
			
			$certificates = [];
			$certfiles = UTILITY::listFiles(UTILITY::directory('vendor_certificates', [':name' => $vendor['immutable_fileserver']]));
			foreach ($certfiles as $path){
				$certificates[pathinfo($path)['basename']] = ['target' => '_blank', 'href' => './api/api.php/file/stream/' . substr($path, 1)];
			}
			if ($certificates) $content[count($content) - 1][] = [
				'type' => 'links',
				'description' => $this->_lang->GET('consumables.vendor.documents_download'),
				'content' => $certificates
			];
		}
		return $content;
	}

	/**
	 * creates and returns a download link to the export file for the vendor list
	 * processes the result of $this->vendors() and translates the body object into more simple strings
	 */
	private function exportvendors(){
		$summary = [
			'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $this->_lang->GET('audit.checks_type.vendors') . '_' . $this->_date['usertime']->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('audit.checks_type.vendors'),
			'date' => $this->convertFromServerTime($this->_date['usertime']->format('Y-m-d H:i'), true)
		];

		$incorporations = $this->vendors();
		$previous = ''; // given there's a text followed by links
		for($i = 1; $i < count($incorporations); $i++){
			foreach ($incorporations[$i] as $item){
				if ($item['type'] === 'textsection') {
					$summary['content'][$item['attributes']['name']] = $item['content'];
					$previous = $item['attributes']['name'];
				}
				if ($item['type'] === 'links') $summary['content'][$previous] .= "\n" . implode("\n", array_keys($item['content']));
			}
		}

		$downloadfiles = [];
		$PDF = new PDF(CONFIG['pdf']['record']);
		$downloadfiles[$this->_lang->GET('record.navigation.summaries')] = [
			'href' => './api/api.php/file/stream/' . $PDF->auditPDF($summary)
		];
		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('record.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}
}
?>
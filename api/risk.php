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

// risk management
class RISK extends API {

	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_search = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedID = $this->_search = isset(REQUEST[2]) ? REQUEST[2] : null;
	}

	/**
	 *       _     _
	 *   ___|_|___| |_
	 *  |  _| |_ -| '_|
	 *  |_| |_|___|_,_|
	 *
	 */
	public function risk(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!PERMISSION::permissionFor('riskmanagement')) $this->response([], 401);
				$risk = [
					':type' => UTILITY::propertySet($this->_payload,'_type'),
					':process' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.process')),
					':risk' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.risk')),
					':relevance' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.relevance')),
					':cause' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.cause')) ? : null,
					':effect' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.effect')) ? : null,
					':probability' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.probability'))) ? : null,
					':damage' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.damage'))) ? : null,
					':measure' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure')) ? : null,
					':measure_probability' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure_probability'))) ? : null,
					':measure_damage' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure_damage'))) ? : null,
					':risk_benefit' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.risk_benefit')) ? : null,
					':measure_remainder' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure_remainder')) ? : null,
					':proof' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.proof')) ? : null,
					':author' =>$_SESSION['user']['name']
				];

				if (!$risk[':type']) $this->response([], 417);

				// convert values to languagefile keys for risks
				$risks_converted = [];
				if ($risk[':risk']) {
					$rsks = explode(', ', $risk[':risk']);
					foreach($rsks as $rsk){
						$risks_converted[] = array_search($rsk, $this->_lang->_USER['risks']); 
					}
					$risk[':risk'] = implode(',', $risks_converted);
				}

				// WHY NO INPUT REQUIRED? because it would be unreasonable to implement frontend conditions for this module
				// as risk management is a pita regulatory requirement once set up rarely beneficial
				switch($risk[':type']){
					case 'characteristic': // implement further cases if suitable, according to languagefile
						// override with specific payload property
						$risk[':measure'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.type.characteristic')) ? : null;
						$risk[':risk'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.risk_related')) ? : null;
						// check if neccessary values have been provided, match with _install.php
						foreach($risk as $key => $value){
							if (in_array($key, [
								':effect',
								':probability',
								':damage',
								':measure_probability',
								':measure_damage',
								':risk_benefit',
								':measure_remainder',
								':proof'
								])) continue;
							if (isset($risk[':relevance']) && $risk[':relevance'] === 0 && in_array($key, [
								':risk',
								':cause'
								])) continue;
							if (!$value && $value !== 0) $this->response([], 417);
						}		
						break;
					default: // risks
						// check if neccessary values have been provided, match with _install.php
						foreach($risk as $key => $value){
							if (in_array($key, [
								':measure_remainder',
								':proof'
								])) continue;
							if (isset($risk[':relevance']) && $risk[':relevance'] === 0 && in_array($key, [
								':cause',
								':effect',
								':probability',
								':damage',
								':measure',
								':measure_probability',
								':measure_damage',
								':risk_benefit'
							])) continue;								
							if (!$value && $value !== 0) $this->response([], 417);
						}		
						break;
				}
				if (SQLQUERY::EXECUTE($this->_pdo, 'risk_post', [
					'values' => $risk
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('risk.risk_saved'),
						'id' => $this->_pdo->lastInsertId(),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('risk.risk_save_error'),
						'id' => false,
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				if (!PERMISSION::permissionFor('riskmanagement')) $this->response([], 401);

				$risk = [
					':id' => intval($this->_requestedID),
					':risk' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.risk')) ? : null,
					':proof' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.proof')) ? : null,
					':hidden' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.availability')) === $this->_lang->GET('risk.hidden') ? json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_currentdate->format('Y-m-d H:i:s')]) : null,
					':probability' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.probability'))) ? : null,
					':damage' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.damage'))) ? : null,
					':measure_probability' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure_probability'))) ? : null,
					':measure_damage' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure_damage'))) ? : null,
				];
				if (!$risk[':risk']) { // characteristic
					$risk[':risk'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.risk_related')) ? : null;
				}

				// convert values to languagefile keys for risks
				$risks_converted = [];
				if ($risk[':risk']) {
					$rsks = explode(', ', $risk[':risk']);
					foreach($rsks as $rsk){
						$risks_converted[] = array_search($rsk, $this->_lang->_USER['risks']); 
					}
					$risk[':risk'] = implode(',', $risks_converted);
				}
				
				if (SQLQUERY::EXECUTE($this->_pdo, 'risk_put', [
					'values' => $risk
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('risk.risk_saved'),
						'id' => intval($this->_requestedID),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('risk.risk_save_error'),
						'id' => false,
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$datalist = $select = [];

				// get requested risk or set up properties
				$risk = SQLQUERY::EXECUTE($this->_pdo, 'risk_get', [
					'values' => [
						':id' => intval($this->_requestedID)
					]
				]);
				$risk = $risk ? $risk[0] : [
					'id' => 0,
					'type' => 'risk',
					'process' => '',
					'risk' => '',
					'relevance' => null,
					'cause' => '',
					'effect' => null,
					'probability' => count($this->_lang->_USER['risk']['probabilities']),
					'damage' => count($this->_lang->_USER['risk']['damages']),
					'measure' => null,
					'measure_probability' => count($this->_lang->_USER['risk']['probabilities']),
					'measure_damage' => count($this->_lang->_USER['risk']['damages']),
					'risk_benefit' => null,
					'measure_remainder' => null,
					'proof' => null,
					'date' => null,
					'author' => null,
					'hidden' => null
				];
				// on button press for new the type is submitted instead of the int risk id
				if (intval($this->_requestedID) != $this->_requestedID) $risk['type'] = $this->_requestedID;

				// set up risk selection according to language file
				$risks = [];
				foreach($this->_lang->_USER['risks'] as $key => $translation){
					$risks[$translation] = ['value' => $key];
				}

				// gather all processes and sort database entries according to type and process to selects
				$risk_datalist = SQLQUERY::EXECUTE($this->_pdo, 'risk_datalist');
				foreach($risk_datalist as $row){
					if (!PERMISSION::permissionFor('riskmanagement') && $row['hidden']) continue;

					// provide datalists with unique values and the most recent letter cases
					foreach(['cause', 'effect', 'process', 'measure', 'risk_benefit', 'measure_remainder'] as $data){
						if (!isset($datalist[$data])) $datalist[$data] = [];
						$row[$data] = trim($row[$data] ? : '');
						if (!$row[$data]) continue;
						if ($risk['type'] === $row['type']) $datalist[$data][strtolower($row[$data])] = $row[$data];
					}

					if (!isset($select[$row['type']])) $select[$row['type']] = [];
					if (!isset($select[$row['type']][$row['process']])) $select[$row['type']][$row['process']] = ['...' => []];
					switch($row['type']){
						case 'characteristic': // implement further cases if suitable, according to languagefile
							$display = $row['measure'] . ($row['cause'] ? ': ' . $row['cause'] : '');
							break;
						default: // risk
							$display = ($row['cause'] ? : '') . ($row['cause'] && $row['effect'] ? ': ': '') . ($row['effect'] ? : '');
							break;
					}
					if ($row['hidden']) $display = UTILITY::hiddenOption($display);
					$select[$row['type']][$row['process']][$display] = intval($this->_requestedID) === $row['id'] ? ['value' => strval($row['id']), 'selected' => true] : ['value' => strval($row['id'])];
				}

				// sanitize datalists
				foreach($datalist as $data => &$values){
					$values = array_filter($values, fn($v) => boolval($v));
					ksort($values);
					// for sanitation of template files:
					//var_dump($data, array_values($values));
				}

				// preselect risk selection according to database response
				foreach(explode(',', $risk['risk'] ? : '') as $selectedrisk){
					if (isset($this->_lang->_USER['risks'][$selectedrisk]) && isset($risks[$this->_lang->_USER['risks'][$selectedrisk]])) $risks[$this->_lang->_USER['risks'][$selectedrisk]]['checked'] = true;
				}

				$result['render'] = ['content' => [
					[
						[
							'type' => 'search',
							'attributes' => [
								'name' => $this->_lang->GET('risk.search'),
								'onkeypress' => "if (event.key === 'Enter') {api.risk('get', 'search', this.value); return false;}",
								]
						]
					],[
						[
							'type' => 'hr'
						]
					]
				]];

				// render selection of types and their content, one selection per process
				$selection = [];
				foreach ($this->_lang->_USER['risk']['type'] as $type => $translation){
					$typeselection = [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $translation
							]
						], [
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('risk.new'),
								'type' => 'button',
								'onclick' => "api.risk('get', 'risk', '" . $type . "')"
							]
						]
					];
					if (isset($select[$type]))
						foreach($select[$type] as $process => $dbrisks){
							$typeselection[] = [
								'type' => 'select',
								'attributes' => [
									'name' => $process,
									'onchange' => "if (this.value !== '...') api.risk('get', 'risk', this.value)"
								],
								'content' => $dbrisks
							];
						}
					$selection[] = $typeselection;
				}
				if ($selection) $result['render']['content'][] = $selection;

				$isactive = !$risk['hidden'] ? ['checked' => true] : [];
				$isinactive = $risk['hidden'] ? ['checked' => true, 'class' => 'red'] : ['class' => 'red'];

				// render form according to type, consider form fields and names within AUDIT->exportrisks() as well
				switch($risk['type']){
					case 'characteristic': // implement further cases if suitable, according to languagefile
						if (PERMISSION::permissionFor('riskmanagement')) {
							$result['render']['form'] = [
								'data-usecase' => 'risk',
								'action' => "javascript:api.risk('" . ($risk['id'] ? 'put' : 'post') . "', 'risk', " . $risk['id'] . ")"
							];

							$result['render']['content'][] = [
								[
									'type' => 'textsection',
									'attributes' => [
										'name' => $this->_lang->_USER['risk']['type'][$risk['type']]
									],
									'content' => $risk['author'] ? $this->_lang->GET('risk.author', [':author' => $risk['author'], ':date' => $risk['date']]) : null
								], [
									'type' => 'hidden',
									'attributes' => [
										'name' => '_type',
										'value' => $risk['type']
									]
								], [
									'type' => 'text',
									'attributes' => [
										'name' => $this->_lang->GET('risk.process'),
										'value' => $risk['process'] ? : '',
									],
									'datalist' => isset($datalist['process']) ? array_values($datalist['process']) : []
								], [
									'type' => 'text',
									'attributes' => [
										'name' => $this->_lang->GET('risk.type.characteristic'),
										'value' => $risk['measure'] ? : '',
									],
									'datalist' => isset($datalist['measure']) ? array_values($datalist['measure']) : []
								], [
									'type' => 'radio',
									'attributes' => [
										'name' => $this->_lang->GET('risk.relevance'),
									],
									'content' => [
										$this->_lang->GET('risk.relevance_yes') => $risk['relevance'] === 1 ? ['checked' => true, 'value' => 1] : ['value' => 1], 
										$this->_lang->GET('risk.relevance_no') => $risk['relevance'] === 0 ? ['checked' => true, 'value' => 0, 'class' => 'red'] : ['value' => 0, 'class' => 'red'],
									]
								], [
									'type' => 'textarea',
									'attributes' => [
										'name' => $this->_lang->GET('risk.cause'),
										'value' => $risk['cause'] ? : '',
										'rows' => 4,
									],
									'autocomplete' => isset($datalist['cause']) ? array_values($datalist['cause']) : null
								], [
									'type' => 'checkbox2text',
									'attributes' => [
										'name' => $this->_lang->GET('risk.risk_related'),
									],
									'content' => $risks
								]
							];

							// disable non editable inputs and append hidden option
							if ($risk['id']){
								$last = count($result['render']['content']) - 1;
								foreach([4, 5, 6, 7] as $index){
									if (isset($result['render']['content'][$last][$index]['content'])){
										foreach ($result['render']['content'][$last][$index]['content'] as $key => $value){
											$result['render']['content'][$last][$index]['content'][$key]['disabled'] = true;
										}
									}
									else {
										unset ($result['render']['content'][$last][$index]['attributes']['onclick']);
										unset ($result['render']['content'][$last][$index]['attributes']['onpointerdown']);
										$result['render']['content'][$last][$index]['attributes']['disabled'] = true;
									}
								}

								$hidden = null;
								if ($risk['hidden']) {
									$hiddenproperties = json_decode($risk['hidden'], true);
									$hidden = $this->_lang->GET('texttemplate.edit_hidden_set', [':date' => $hiddenproperties['date'], ':name' => $hiddenproperties['name']]);
								}
								$result['render']['content'][$last][] = [
									'type' => 'radio',
									'attributes' => [
										'name' => $this->_lang->GET('risk.availability')
									],
									'content' => [
										$this->_lang->GET('risk.available') => $isactive,
										$this->_lang->GET('risk.hidden') => $isinactive
									],
									'hint' => $hidden
								];
							}
						}
						else {
							if ($risk['id'] && !$risk['hidden']){
								$result['render']['content'][] = [
									[
										'type' => 'textsection',
										'attributes' => [
											'name' => $this->_lang->_USER['risk']['type'][$risk['type']]
										],
										'content' => $risk['author'] ? $this->_lang->GET('risk.author', [':author' => $risk['author'], ':date' => $risk['date']]) : null
									], [
										'type' => 'textsection',
										'attributes' => [
											'name' => $this->_lang->GET('risk.process'),
										],
										'content' => $risk['process'] ? : '',
									], [
										'type' => 'textsection',
										'attributes' => [
											'name' => $this->_lang->GET('risk.type.characteristic'),
										],
										'content' => $risk['measure'] ? : '',
									], [
										'type' => 'radio',
										'attributes' => [
											'name' => $this->_lang->GET('risk.relevance'),
										],
										'content' => [
											$this->_lang->GET('risk.relevance_yes') => $risk['relevance'] === 1 ? ['checked' => true, 'value' => 1, 'disabled' => true] : ['value' => 1, 'disabled' => true], 
											$this->_lang->GET('risk.relevance_no') => $risk['relevance'] === 0 ? ['checked' => true, 'value' => 0, 'class' => 'red', 'disabled' => true] : ['value' => 0, 'class' => 'red', 'disabled' => true],
										]
									], [
										'type' => 'textsection',
										'attributes' => [
											'name' => $this->_lang->GET('risk.cause'),
										],
										'content' => $risk['cause'] ? : '',
									], [
										'type' => 'textsection',
										'attributes' => [
											'name' => $this->_lang->GET('risk.risk_related'),
										],
										'content' => implode("\n", array_values(array_map(fn($r)=> $r && isset($this->_lang->_USER['risks'][$r]) ? $this->_lang->_USER['risks'][$r] : null, explode(',', $risk['risk'] ? : ''))))
									]
								];
							}
						}
						break;
					default: // risk
					if (PERMISSION::permissionFor('riskmanagement')) {
						$result['render']['form'] = [
							'data-usecase' => 'risk',
							'action' => "javascript:api.risk('" . ($risk['id'] ? 'put' : 'post') . "', 'risk', " . $risk['id'] . ")"
						];
	
						// fallback for occasional level changes in languagefile during runtime
						$risk['probability'] = min($risk['probability'], count($this->_lang->_USER['risk']['probabilities']));
						$risk['damage'] = min($risk['damage'], count($this->_lang->_USER['risk']['damages']));
						$risk['measure_probability'] = min($risk['measure_probability'], count($this->_lang->_USER['risk']['probabilities']));
						$risk['measure_damage'] = min($risk['measure_damage'], count($this->_lang->_USER['risk']['damages']));

						// set up selections for probabilities and damages translated by index
						$probabilities = $measure_probabilities = $damages = $measure_damages = [];
						foreach($this->_lang->_USER['risk']['probabilities'] as $index => $description){
							$probabilities[$description] = $risk['probability'] == $index + 1 ? ['value' => $index + 1, 'selected' => true] : ['value' => $index + 1];
							$measure_probabilities[$description] = $risk['measure_probability'] == $index + 1 ? ['value' => $index + 1, 'selected' => true] : ['value' => $index + 1];
						}
						foreach($this->_lang->_USER['risk']['damages'] as $index => $description){
							$damages[$description] = $risk['damage'] == $index + 1 ? ['value' => $index + 1, 'selected' => true] : ['value' => $index + 1];
							$measure_damages[$description] = $risk['measure_damage'] == $index + 1 ? ['value' => $index + 1, 'selected' => true] : ['value' => $index + 1];
						}

						// prepare available documents lists
						// get latest approved by name
						$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
						$hidden = $insertdocument = [];
						$selecteddocuments = explode(', ', $risk['proof'] ? : '');
						foreach($documents as $key => $row) {
							if (!PERMISSION::fullyapproved('documentapproval', $row['approval'])) continue;
							if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
							if (!in_array($row['name'], $hidden)) {
									$insertdocument[$row['name']] = ['value' => $row['name']];
									if (in_array($row['name'], $selecteddocuments)) $insertdocument[$row['name']]['checked'] = true;
							}
						}
						ksort($insertdocument);
		
						$result['render']['content'][] = [
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $this->_lang->_USER['risk']['type'][$risk['type']]
								],
								'content' => $risk['author'] ? $this->_lang->GET('risk.author', [':author' => $risk['author'], ':date' => $risk['date']]) : null
							], [
								'type' => 'hidden',
								'attributes' => [
									'name' => '_type',
									'value' => $risk['type']
								]
							],[
								'type' => 'text',
								'attributes' => [
									'name' => $this->_lang->GET('risk.process'),
									'value' => $risk['process'] ? : '',
								],
								'datalist' => isset($datalist['process']) ? array_values($datalist['process']) : []
							], [
								'type' => 'checkbox2text',
								'attributes' => [
									'name' => $this->_lang->GET('risk.risk'),
								],
								'content' => $risks
							], [
								'type' => 'radio',
								'attributes' => [
									'name' => $this->_lang->GET('risk.relevance'),
								],
								'content' => [
									$this->_lang->GET('risk.relevance_yes') => $risk['relevance'] === 1 ? ['checked' => true, 'value' => 1] : ['value' => 1], 
									$this->_lang->GET('risk.relevance_no') => $risk['relevance'] === 0 ? ['checked' => true, 'value' => 0, 'class' => 'red'] : ['value' => 0, 'class' => 'red'],
							]
							], [
								'type' => 'textarea',
								'attributes' => [
									'name' => $this->_lang->GET('risk.cause'),
									'value' => $risk['cause'] ? : '',
									'rows' => 4
								],
								'autocomplete' => isset($datalist['cause']) ? array_values($datalist['cause']) : null
							], [
								'type' => 'br'
							], [
								'type' => 'textarea',
								'attributes' => [
									'name' => $this->_lang->GET('risk.effect'),
									'value' => $risk['effect'] ? : '',
									'rows' => 4,
								],
								'autocomplete' => isset($datalist['effect']) ? array_values($datalist['effect']) : null
							], [
								'type' => 'br'
							], [
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('risk.probability')
								],
								'content' => $probabilities
							], [
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('risk.damage')
								],
								'content' => $damages
							], [
								'type' => 'textsection',
								'attributes' => [
									'class' => $risk['probability'] * $risk['damage'] > CONFIG['limits']['risk_acceptance_level'] ? 'red' : 'green',
									'name' => $risk['probability'] * $risk['damage'] > CONFIG['limits']['risk_acceptance_level'] ? $this->_lang->GET('risk.acceptance_level_above') : $this->_lang->GET('risk.acceptance_level_below')
								]
							], [
								'type' => 'textarea',
								'attributes' => [
									'name' => $this->_lang->GET('risk.measure'),
									'value' => $risk['measure'] ? : '',
									'rows' => 4
								],
								'autocomplete' => isset($datalist['measure']) ? array_values($datalist['measure']) : null
							], [
								'type' => 'br'
							], [
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('risk.measure_probability')
								],
								'content' => $measure_probabilities
							], [
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('risk.measure_damage')
								],
								'content' => $measure_damages
							], [
								'type' => 'textsection',
								'attributes' => [
									'class' => $risk['measure_probability'] * $risk['measure_damage'] > CONFIG['limits']['risk_acceptance_level'] ? 'red' : 'green',
									'name' => $risk['measure_probability'] * $risk['measure_damage'] > CONFIG['limits']['risk_acceptance_level'] ? $this->_lang->GET('risk.acceptance_level_above') : $this->_lang->GET('risk.acceptance_level_below')
								]
							], [
								'type' => 'textarea',
								'attributes' => [
									'name' => $this->_lang->GET('risk.risk_benefit'),
									'value' => $risk['risk_benefit'] ? : '',
									'rows' => 4
								],
								'autocomplete' => isset($datalist['risk_benefit']) ? array_values($datalist['risk_benefit']) : null
							], [
								'type' => 'br'
							], [
								'type' => 'textarea',
								'attributes' => [
									'name' => $this->_lang->GET('risk.measure_remainder'),
									'value' => $risk['measure_remainder'] ? : '',
									'rows' => 4
								],
								'autocomplete' => isset($datalist['measure_remainder']) ? array_values($datalist['measure_remainder']) : null
							], [
								'type' => 'checkbox2text',
								'attributes' => [
									'name' => $this->_lang->GET('risk.proof'),
								],
								'content' => $insertdocument 
							]
						];

						// disable non editable inputs and append hidden option
						if ($risk['id']){
							$last = count($result['render']['content']) - 1;
							foreach([3, 5, 6, 8, 12, 13, 17] as $index){
								if (isset($result['render']['content'][$last][$index]['content'])){
									foreach ($result['render']['content'][$last][$index]['content'] as $key => $value){
										$result['render']['content'][$last][$index]['content'][$key]['disabled'] = true;
									}
								}
								else {
									unset ($result['render']['content'][$last][$index]['attributes']['onclick']);
									unset ($result['render']['content'][$last][$index]['attributes']['onpointerdown']);
									$result['render']['content'][$last][$index]['attributes']['disabled'] = true;
								}
							}
							$hidden = null;
							if ($risk['hidden']) {
								$hiddenproperties = json_decode($risk['hidden'], true);
								$hidden = $this->_lang->GET('risk.edit_hidden_set', [':date' => $hiddenproperties['date'], ':name' => $hiddenproperties['name']]);
							}
							$result['render']['content'][$last][] = [
								'type' => 'radio',
								'attributes' => [
									'name' => $this->_lang->GET('risk.availability')
								],
								'content' => [
									$this->_lang->GET('risk.available') => $isactive,
									$this->_lang->GET('risk.hidden') => $isinactive
								],
								'hint' => $hidden
							];
						}
					}
					else {
						if ($risk['id'] && !$risk['hidden']){
							$result['render']['content'][] = [
								[
									'type' => 'textsection',
									'attributes' => [
										'name' => $this->_lang->_USER['risk']['type'][$risk['type']]
									],
									'content' => $risk['author'] ? $this->_lang->GET('risk.author', [':author' => $risk['author'], ':date' => $risk['date']]) : null
								],[
									'type' => 'textsection',
									'attributes' => [
										'name' => $this->_lang->GET('risk.process'),
									],
									'content' => $risk['process'] ? : '',
								], [
									'type' => 'textsection',
									'attributes' => [
										'name' => $this->_lang->GET('risk.risk_related'),
									],
									'content' => implode("\n", array_values(array_map(fn($r)=> $r && isset($this->_lang->_USER['risks'][$r]) ? $this->_lang->_USER['risks'][$r] : null, explode(',', $risk['risk'] ? : ''))))
								], [
									'type' => 'radio',
									'attributes' => [
										'name' => $this->_lang->GET('risk.relevance'),
									],
									'content' => [
										$this->_lang->GET('risk.relevance_yes') => $risk['relevance'] === 1 ? ['checked' => true, 'value' => 1, 'disabled' => true] : ['value' => 1, 'disabled' => true], 
										$this->_lang->GET('risk.relevance_no') => $risk['relevance'] === 0 ? ['checked' => true, 'value' => 0, 'class' => 'red', 'disabled' => true] : ['value' => 0, 'class' => 'red', 'disabled' => true],
								]
								], [
									'type' => 'textsection',
									'content' => ($risk['cause'] ? $this->_lang->GET('risk.cause') . ': ' . $risk['cause'] . "\n" : '') .
										($risk['effect'] ? $this->_lang->GET('risk.effect') . ': ' . $risk['effect'] . "\n" : '') .
										$this->_lang->GET('risk.probability') . ': ' . $this->_lang->_USER['risk']['probabilities'][$risk['probability'] - 1] . "\n" .
										$this->_lang->GET('risk.damage') . ': ' . $this->_lang->_USER['risk']['damages'][$risk['damage'] - 1] . "\n"
								], [
									'type' => 'textsection',
									'attributes' => [
										'class' => $risk['probability'] * $risk['damage'] > CONFIG['limits']['risk_acceptance_level'] ? 'red' : 'green',
										'name' => $risk['probability'] * $risk['damage'] > CONFIG['limits']['risk_acceptance_level'] ? $this->_lang->GET('risk.acceptance_level_above') : $this->_lang->GET('risk.acceptance_level_below')
									]
								], [
									'type' => 'textsection',
									'content' => ($risk['measure'] ? $this->_lang->GET('risk.measure') . ': ' . $risk['measure'] . "\n" : '') .
										$this->_lang->GET('risk.measure_probability') . ': ' . $this->_lang->_USER['risk']['probabilities'][$risk['measure_probability'] - 1] . "\n" .
										$this->_lang->GET('risk.measure_damage') . ': ' . $this->_lang->_USER['risk']['damages'][$risk['measure_damage'] - 1] . "\n"
								], [
									'type' => 'textsection',
									'attributes' => [
										'class' => $risk['measure_probability'] * $risk['measure_damage'] > CONFIG['limits']['risk_acceptance_level'] ? 'red' : 'green',
										'name' => $risk['measure_probability'] * $risk['measure_damage'] > CONFIG['limits']['risk_acceptance_level'] ? $this->_lang->GET('risk.acceptance_level_above') : $this->_lang->GET('risk.acceptance_level_below')
									]
								], [
									'type' => 'textsection',
									'content' => ($risk['risk_benefit'] ? $this->_lang->GET('risk.risk_benefit') . ': ' . $risk['risk_benefit'] . "\n" : '') .
										($risk['measure_remainder'] ? $this->_lang->GET('risk.measure_remainder') . ': ' . $risk['measure_remainder'] . "\n" : '')
								], [
									'type' => 'textsection',
									'attributes' => [
										'name' => $this->_lang->GET('risk.proof'),
									],
									'linkedcontent' => implode("\n", array_values(array_map(fn($d) => $d ? '<a href="javascript:api.record(\'get\', \'document\', \'' . $d . '\')">' . $d . '</a>': null, explode(', ', $risk['proof'] ? : ''))))
								]
							];
						}
					}
					break;
				}
				$this->response($result);
				break;
		}
	}

	/**
	 *                       _   
	 *   ___ ___ ___ ___ ___| |_ 
	 *  |_ -| -_| .'|  _|  _|   |
	 *  |___|___|__,|_| |___|_|_|
	 * 
	 */
	public function search(){
		require_once('_shared.php');
		$search = new SHARED($this->_pdo);
		$result = ['render' => ['content' => $search->risksearch(['search' => $this->_search])]];
		$this->response($result);
	}
}
?>
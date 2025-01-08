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

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedID = isset(REQUEST[2]) ? REQUEST[2] : null;
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
				$date = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
				$risk = [
					':process' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.process')),
					':risk' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.risk')),
					':cause' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.cause')),
					':effect' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.effect')),
					':probability' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.probability'))),
					':damage' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.damage'))),
					':measure' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure')),
					':measure_probability' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure_probability'))),
					':measure_damage' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure_damage'))),
					':risk_benefit' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.risk_benefit')),
					':measure_remainder' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure_remainder')) ? : '',
					':last_edit' => json_encode(['user' => $_SESSION['user']['name'], 'date' => $date->format('Y-m-d H:i')])
				];
				foreach($risk as $key => $value){
					if ($key === ':measure_remainder') continue;
					if (!$value) $this->response([], 417);
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
				$date = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
				$risk = [
					':id' => intval($this->_requestedID),
					':process' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.process')),
					':risk' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.risk')),
					':cause' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.cause')),
					':effect' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.effect')),
					':probability' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.probability'))),
					':damage' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.damage'))),
					':measure' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure')),
					':measure_probability' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure_probability'))),
					':measure_damage' => intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure_damage'))),
					':risk_benefit' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.risk_benefit')),
					':measure_remainder' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.measure_remainder')) ? : '',
					':last_edit' => json_encode(['user' => $_SESSION['user']['name'], 'date' => $date->format('Y-m-d H:i')])
				];
				foreach($risk as $key => $value){
					if ($key === ':measure_remainder') continue;
					if (!$value) $this->response([], 417);
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
				$processes = $this->_lang->_USER['risk']['preset_process'];
				$risks = $this->_lang->_USER['risk']['preset_risk'];
				$select = [];
				$risk_datalist = SQLQUERY::EXECUTE($this->_pdo, 'risk_datalist');
				foreach($risk_datalist as $row){
					$processes[] = $row['process'];
					$risks[] = $row['risk'];
					if (!isset($select[$row['process']])) $select[$row['process']] = [$this->_lang->GET('risk.new') => ['value' => '0']];
					$select[$row['process']][$row['risk'] . ': ' . $row['cause']] = ['value' => strval($row['id'])];
				}

				$risk = SQLQUERY::EXECUTE($this->_pdo, 'risk_get', [
					'values' => [
						':id' => intval($this->_requestedID)
					]
				]);
				$risk = $risk ? $risk[0] : [
					'id' => 0,
					'process' => '',
					'risk' => '',
					'cause' => '',
					'effect' => '',
					'probability' => count($this->_lang->_USER['risk']['probabilities']),
					'damage' => count($this->_lang->_USER['risk']['damages']),
					'measure' => '',
					'measure_probability' => count($this->_lang->_USER['risk']['probabilities']),
					'measure_damage' => count($this->_lang->_USER['risk']['damages']),
					'risk_benefit' => '',
					'measure_remainder' => '',
					'last_edit' => ''
				];
				
				// fallback for occasional level changes in languagefile
				$risk['probability'] = min($risk['probability'], count($this->_lang->_USER['risk']['probabilities']));
				$risk['damage'] = min($risk['damage'], count($this->_lang->_USER['risk']['damages']));
				$risk['measure_probability'] = min($risk['measure_probability'], count($this->_lang->_USER['risk']['probabilities']));
				$risk['measure_damage'] = min($risk['measure_damage'], count($this->_lang->_USER['risk']['damages']));

				$probabilities = $measure_probabilities = $damages = $measure_damages = [];
				foreach($this->_lang->_USER['risk']['probabilities'] as $index => $description){
					$probabilities[$description] = $risk['probability'] == $index + 1 ? ['value' => $index + 1, 'selected' => true] : ['value' => $index + 1];
					$measure_probabilities[$description] = $risk['measure_probability'] == $index + 1 ? ['value' => $index + 1, 'selected' => true] : ['value' => $index + 1];
				}
				foreach($this->_lang->_USER['risk']['damages'] as $index => $description){
					$damages[$description] = $risk['damage'] == $index + 1 ? ['value' => $index + 1, 'selected' => true] : ['value' => $index + 1];
					$measure_damages[$description] = $risk['measure_damage'] == $index + 1 ? ['value' => $index + 1, 'selected' => true] : ['value' => $index + 1];
				}
				$last_edit = json_decode($risk['last_edit'], true);

				$selection = [];
				foreach ($select as $key => $values){
					$selection[] = [[
						'type' => 'select',
						'attributes' => [
							'name' => $key,
							'onchange' => "api.risk('get', 'risk', this.value)"
						],
						'content' => $values
					]];
				}
				$result['render'] = ['content' => []];
				if ($selection) $result['render']['content'][] = $selection;
				$result['render']['content'][] = [
					[
						'type' => 'datalist',
						'content' => array_values(array_unique($processes)),
						'attributes' => [
							'id' => 'processes'
						]
					], [
						'type' => 'datalist',
						'content' => array_values(array_unique($risks)),
						'attributes' => [
							'id' => 'risks'
						]
					], [
						'type' => 'text',
						'attributes' => [
							'name' => $this->_lang->GET('risk.process'),
							'list' => 'processes',
							'value' => $risk['process'],
							'required' => true
						]
					], [
						'type' => 'text',
						'attributes' => [
							'name' => $this->_lang->GET('risk.risk'),
							'list' => 'risks',
							'value' => $risk['risk'],
							'required' => true
						]
					], [
						'type' => 'textarea',
						'attributes' => [
							'name' => $this->_lang->GET('risk.cause'),
							'value' => $risk['cause'],
							'rows' => 4,
							'required' => true
						]
					], [
						'type' => 'br'
					], [
						'type' => 'textarea',
						'attributes' => [
							'name' => $this->_lang->GET('risk.effect'),
							'value' => $risk['effect'],
							'rows' => 4,
							'required' => true
						]
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
							'value' => $risk['measure'],
							'rows' => 4,
							'required' => true
						]
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
							'value' => $risk['risk_benefit'],
							'rows' => 4,
							'required' => true
						]
					], [
						'type' => 'br'
					], [
						'type' => 'textarea',
						'attributes' => [
							'name' => $this->_lang->GET('risk.measure_remainder'),
							'value' => $risk['measure_remainder'],
							'rows' => 4
						],
						'hint' => (isset($last_edit['user'])) ? $this->_lang->GET('risk.last_edit', [':user' => $last_edit['user'], ':date' => $last_edit['date']]): ''
					]
				];
				if (boolval($risk['id']) && PERMISSION::permissionFor('riskmanagement')) {
					$result['render']['content'][count($result['render']['content']) -1][] = [
						'type' => 'deletebutton',
						'attributes' => [
							'value' => $this->_lang->GET('risk.delete_button'),
							'type' => 'button', // apparently defaults to submit otherwise
							'onpointerup' => $risk['id'] ? "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('risk.delete_confirm_header') ."', options:{".
								"'".$this->_lang->GET('risk.delete_cancel')."': false,".
								"'".$this->_lang->GET('risk.delete_confirm')."': {value: true, class: 'reducedCTA'},".
								"}}).then(confirmation => {if (confirmation) api.risk('delete', 'risk', ". $risk['id'] . ")})" : ''
						]
					];
				}

				if (PERMISSION::permissionFor('riskmanagement')) $result['render']['form'] = [
					'data-usecase' => 'risk',
					'action' => "javascript:api.risk('" . ($risk['id'] ? 'put' : 'post') . "', 'risk', " . $risk['id'] . ")"
				];
				$this->response($result);
				break;
			case 'DELETE':
				if (!PERMISSION::permissionFor('riskmanagement')) $this->response([], 401);
				if (SQLQUERY::EXECUTE($this->_pdo, 'risk_delete', [
					'values' => [
						':id' => intval($this->_requestedID)
					]
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('risk.risk_deleted'),
						'id' => false,
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('risk.risk_delete_error'),
						'id' => intval($this->_requestedID),
						'type' => 'error'
					]]);
				break;
		}
	}
}
?>
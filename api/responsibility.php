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

// add, edit and delete responsibilities

class RESPONSIBILITY extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_unit = null;
	private $_userAcceptance = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedID = isset(REQUEST[2]) ? REQUEST[2] : null;
		$this->_unit = $this->_userAcceptance = isset(REQUEST[3]) ? REQUEST[3] : null;
	}

	/**
	 *                               _ _   _ _ _ _   _
	 *   ___ ___ ___ ___ ___ ___ ___|_| |_|_| |_| |_|_|___ ___
	 *  |  _| -_|_ -| . | . |   |_ -| | . | | | |  _| | -_|_ -|
	 *  |_| |___|___|  _|___|_|_|___|_|___|_|_|_|_| |_|___|___|
	 *              |_|
	 *
	 * display responsibilities and accept assignment
	 */
	public function responsibilities(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				if ($this->_userAcceptance != $_SESSION['user']['id']) $this->response([], 401);
				$responsibility = SQLQUERY::EXECUTE($this->_pdo, 'user_responsibility_get', [
					'values' => [
						':id' => intval($this->_requestedID)
					]
				]);
				$responsibility = $responsibility ? $responsibility[0]: null;
				if (!$responsibility) $this->response([], 404);
				$responsibility['assigned_users'] = json_decode($responsibility['assigned_users'], true);
				if (array_key_exists($this->_userAcceptance, $responsibility['assigned_users'])) $responsibility['assigned_users'][$this->_userAcceptance] = $this->_currentdate->format('Y-m-d');
				else {
					// only if not found for itsybity performance reasons
					$responsibility['proxy_users'] = json_decode($responsibility['proxy_users'], true);
					if (array_key_exists($this->_userAcceptance, $responsibility['proxy_users'])) $responsibility['proxy_users'][$this->_userAcceptance] = $this->_currentdate->format('Y-m-d');
					$responsibility['proxy_users'] = UTILITY::json_encode($responsibility['proxy_users']);
				}
				if (SQLQUERY::EXECUTE($this->_pdo, 'user_responsibility_accept', [
					'values' => [
						':id' => intval($this->_requestedID),
						':assigned_users' => UTILITY::json_encode(($responsibility['assigned_users'])),
						':proxy_users' => $responsibility['proxy_users']
					]
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('responsibility.accepted_success'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('responsibility.accepted_error'),
						'type' => 'error'
					]]);
				
				break;
			case 'GET':
				$result = ['render' => ['content' => []]];
				$responsibilities = SQLQUERY::EXECUTE($this->_pdo, 'user_responsibility_get_all');
				$available_units = [];
				$selected = [];

				// prepare existing responsibilities filtered by unit
				foreach($responsibilities as $row){
					if (!PERMISSION::permissionFor('responsibilities') && $row['hidden']) continue;
					$row['units'] = explode(',', $row['units']);
					$row['assigned_users'] = json_decode($row['assigned_users'], true);
					$row['proxy_users'] = json_decode($row['proxy_users'], true);
					array_push($available_units, ...$row['units']);

					// filter by unit
					if (!$this->_unit && !array_intersect($row['units'], ['common', ...$_SESSION['user']['units']])) continue;
					if ($this->_unit && !in_array($this->_unit, $row['units'])) {
						if ($this->_unit !== '_my')	continue;
						// handle user assigned responsibilities
						if (!array_key_exists($_SESSION['user']['id'], $row['assigned_users']) && !array_key_exists($_SESSION['user']['id'], $row['proxy_users'])) continue;
					}

					// add to result
					$selected[] = $row;
				}

				// append selection of responsibilities per unit
				$organizational_units = [];
				$available_units = array_unique($available_units);
				sort($available_units);
				$organizational_units[$this->_lang->GET('responsibility.my')] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'onchange' => "api.responsibility('get', 'responsibilities', 'null', '_my')"];
				if ($this->_unit === '_my') $organizational_units[$this->_lang->GET('responsibility.my')]['checked'] = true;
				$organizational_units[$this->_lang->GET('assemble.render.mine')] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'onchange' => "api.responsibility('get', 'responsibilities', 'null')"];
				if (!$this->_unit) $organizational_units[$this->_lang->GET('assemble.render.mine')]['checked'] = true;
				foreach($available_units as $unit){
					if (!$unit) {
						continue;
					}
					$organizational_units[$this->_lang->_USER['units'][$unit]] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'onchange' => "api.responsibility('get', 'responsibilities', 'null', '" . $unit . "')"];
					if ($this->_unit === $unit) $organizational_units[$this->_lang->_USER['units'][$unit]]['checked'] = true;
				}

				$result['render']['content'][] = [
					[
						'type' => 'radio',
						'content' => $organizational_units,
						'attributes' => [
							'name' => $this->_lang->GET('order.organizational_unit'),
							'onchange' => "api.responsibility('get', 'responsibilities', this.value)"
						]
					]
				];

				$content = [];
				if ($selected){
					$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
					foreach ($selected as $row){
						$content = [];
						$assigned = [];
						$proxy = [];

						foreach($row['assigned_users'] as $user_id => $date){
							if (($user = array_search($user_id, array_column($users, 'id'))) === false) $user = ['name' => $this->_lang->GET('message.deleted_user')];
							else $user = $users[$user];
							$assigned[$user['name']] = ['checked' => boolval($date)];
							if (!boolval($date) && $_SESSION['user']['id'] == $user_id) $assigned[$user['name']] = ['onchange' =>
								"new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('responsibility.accept', [':task' => $row['responsibility']]) ."', options:{".
								"'".$this->_lang->GET('general.cancel_button')."': false,".
								"'".$this->_lang->GET('general.ok_button')."': {value: true, class: 'reducedCTA'},".
								"}}).then(confirmation => {if (confirmation) {api.responsibility('put', 'responsibilities', ". $row['id'] . ", " . $_SESSION['user']['id'] . "); this.disabled = true;} else this.checked = false;})"
								];
							if (boolval($date) || $_SESSION['user']['id'] != $user_id) $assigned[$user['name']]['disabled'] = true;
						}
						foreach($row['proxy_users'] as $user_id => $date){
							if (($user = array_search($user_id, array_column($users, 'id'))) === false) $user = ['name' => $this->_lang->GET('message.deleted_user')];
							else $user = $users[$user];
							$proxy[$user['name']] = ['checked' => boolval($date)];
							if (!boolval($date) && $_SESSION['user']['id'] == $user_id) $assigned[$user['name']] = ['onchange' =>
								"new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('responsibility.accept', [':task' => $row['responsibility']]) ."', options:{".
								"'".$this->_lang->GET('general.cancel_button')."': false,".
								"'".$this->_lang->GET('general.ok_button')."': {value: true, class: 'reducedCTA'},".
								"}}).then(confirmation => {if (confirmation) {api.responsibility('put', 'responsibilities', ". $row['id'] . ", " . $_SESSION['user']['id'] . "); this.disabled = true;} else this.checked = false;})"
								];
							if (boolval($date) || $_SESSION['user']['id'] != $user_id) $proxy[$user['name']]['disabled'] = true;
						}
						$content[] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $row['responsibility']
							],
							'content' => $row['description']
						];
						$content[] = [
							'type' => 'checkbox',
							'attributes' => [
								'name' => $this->_lang->GET('responsibility.assigned')
							],
							'content' => $assigned
						];
						if ($proxy)	$content[] = [
							'type' => 'checkbox',
							'attributes' => [
								'name' => $this->_lang->GET('responsibility.proxy')
							],
							'content' => $proxy
						];
						$content[] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('responsibility.applicability')
							],
							'content' => $this->_lang->GET('responsibility.apply', [':start' => substr($row['span_start'], 0, 10), ':end' => substr($row['span_end'], 0, 10)])
						];
						if (PERMISSION::permissionFor('responsibilities')) $content[] = [
							'type' => 'button',
							'attributes' => [
								'type' => 'button',
								'value' => $this->_lang->GET('responsibility.edit'),
								'onclick' => "api.responsibility('get', 'responsibility', " . $row['id'] . ")"
							]
						];

						$result['render']['content'][] = $content;
					}
				}
				if (PERMISSION::permissionFor('responsibilities')) {
					$result['render']['content'][] = [
						[
							'type' => 'button',
							'attributes' => [
								'type' => 'button',
								'value' => $this->_lang->GET('responsibility.new'),
								'onclick' => "api.responsibility('get', 'responsibility', 'null')"
							]
						]
					];
				}
				break;
		}
		$this->response($result);
	}


	/**
	 *                               _ _   _ _ _ _
	 *   ___ ___ ___ ___ ___ ___ ___|_| |_|_| |_| |_ _ _
	 *  |  _| -_|_ -| . | . |   |_ -| | . | | | |  _| | |
	 *  |_| |___|___|  _|___|_|_|___|_|___|_|_|_|_| |_  |
	 *              |_|                             |___|
	 *
	 * edit responsibilities
	 */
	public function responsibility(){
		if (!PERMISSION::permissionFor('responsibilities'))  $this->response([], 401);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$responsibility = [
					':user_id' => $_SESSION['user']['id'],
					':units' => [],
					':assigned_users' => [],
					':proxy_users' => [],
					':span_start' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('responsibility.applicability_start')),
					':span_end' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('responsibility.applicability_end')),
					':responsibility' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('responsibility.task')),
					':description' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('responsibility.context')),
					':hidden' => null,
				];
				// process selected units
				if (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('responsibility.units'))) {
					foreach( explode(' | ', UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('responsibility.units'))) as $unit){
						$responsibility[':units'][] = array_search($unit, $this->_lang->_USER['units']);
					}
				}
				$responsibility[':units'] = implode(',', $responsibility[':units']);

				//user datalist
				$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');

				// process assigned users
				foreach($this->_payload as $key => $value){
					if (!str_starts_with($key, $this->_lang->PROPERTY('responsibility.assigned')) || !$value) continue;
					$responsibility[':assigned_users'][$users[array_search($value, array_column($users, 'name'))]['id']] = [];
				}
				if (!$responsibility[':assigned_users'] || !$responsibility[':span_start'] || !$responsibility[':span_end']) $this->response([], 406);
				$responsibility[':assigned_users'] = UTILITY::json_encode($responsibility[':assigned_users']);

				// process proxy users
				foreach($this->_payload as $key => $value){
					if (!str_starts_with($key, $this->_lang->PROPERTY('responsibility.proxy')) || !$value) continue;
					$responsibility[':proxy_users'][$users[array_search($value, array_column($users, 'name'))]['id']] = [];
				}
				$responsibility[':proxy_users'] = UTILITY::json_encode($responsibility[':proxy_users']);
				// insert responsibility into database
				if (SQLQUERY::EXECUTE($this->_pdo, 'user_responsibility_post', [
					'values' => $responsibility
				])) {
					$responsibility[':assigned_users'] = json_decode($responsibility[':assigned_users'], true);
					$responsibility[':proxy_users'] = json_decode($responsibility[':proxy_users'], true);
					$recipients = array_map(fn($id) => $users[array_search($id, array_column($users, 'id'))]['name'], [...array_keys($responsibility[':assigned_users']), ...array_keys($responsibility[':proxy_users'])]);
					$this->alertUserGroup(['user' => $recipients], str_replace('\n', ', ', $this->_lang->GET('responsibility.message', [
						':user' => $_SESSION['user']['name'],
						':task' => $responsibility[':responsibility'],
						':link' => '<a href="javascript:void(0);" onclick="api.responsibility(\'get\', \'responsibilities\', \'null\', \'_my\')">' . $this->_lang->GET('menu.communication.responsibility'). '</a>',
					], true)));

					$this->response([
					'response' => [
						'msg' => $this->_lang->GET('responsibility.save_success'),
						'type' => 'success'
					]]);
				} else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('responsibility.save_error'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				$responsibility = [
					':id' => intval($this->_requestedID),
					':user_id' => $_SESSION['user']['id'],
					':units' => [],
					':assigned_users' => [],
					':proxy_users' => [],
					':span_start' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('responsibility.applicability_start')),
					':span_end' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('responsibility.applicability_end')),
					':responsibility' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('responsibility.task')),
					':description' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('responsibility.context')),
					':hidden' => null,
				];
				// process selected units
				if (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('responsibility.units'))) {
					foreach( explode(' | ', UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('responsibility.units'))) as $unit){
						$responsibility[':units'][] = array_search($unit, $this->_lang->_USER['units']);
					}
				}
				$responsibility[':units'] = implode(',', $responsibility[':units']);

				//user datalist
				$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');

				// process assigned users
				foreach($this->_payload as $key => $value){
					if (!str_starts_with($key, $this->_lang->PROPERTY('responsibility.assigned')) || !$value) continue;
					$responsibility[':assigned_users'][$users[array_search($value, array_column($users, 'name'))]['id']] = [];
				}
				if (!$responsibility[':assigned_users'] || !$responsibility[':span_start'] || !$responsibility[':span_end']) $this->response([], 406);
				$responsibility[':assigned_users'] = UTILITY::json_encode($responsibility[':assigned_users']);

				// process proxy users
				foreach($this->_payload as $key => $value){
					if (!str_starts_with($key, $this->_lang->PROPERTY('responsibility.proxy')) || !$value) continue;
					$responsibility[':proxy_users'][$users[array_search($value, array_column($users, 'name'))]['id']] = [];
				}
				$responsibility[':proxy_users'] = UTILITY::json_encode($responsibility[':proxy_users']);

				// insert responsibility into database
				if (SQLQUERY::EXECUTE($this->_pdo, 'user_responsibility_put', [
					'values' => $responsibility
				])) {
					$responsibility[':assigned_users'] = json_decode($responsibility[':assigned_users'], true);
					$responsibility[':proxy_users'] = json_decode($responsibility[':proxy_users'], true);
					$recipients = array_map(fn($id) => $users[array_search($id, array_column($users, 'id'))]['name'], [...array_keys($responsibility[':assigned_users']), ...array_keys($responsibility[':proxy_users'])]);
					$this->alertUserGroup(['user' => $recipients], str_replace('\n', ', ', $this->_lang->GET('responsibility.message', [
						':user' => $_SESSION['user']['name'],
						':task' => $responsibility[':responsibility'],
						':link' => '<a href="javascript:void(0);" onclick="api.responsibility(\'get\', \'responsibilities\', \'null\', \'_my\')">' . $this->_lang->GET('menu.communication.responsibility', [], true). '</a>',
					], true)));
					
					$this->response([
					'response' => [
						'msg' => $this->_lang->GET('responsibility.save_success'),
						'type' => 'success'
					]]);
				} else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('responsibility.save_error'),
						'type' => 'error'
					]]);

				break;
			case 'GET':
				$result = ['render' => ['form' => [
					'data-usecase' => 'responsibility',
					'action' => "javascript:api.responsibility('" . (intval($this->_requestedID) ? 'put': 'post') . "', 'responsibility', " . intval($this->_requestedID) . ")"
					],
					'content' => []]
				];
				$responsibility = SQLQUERY::EXECUTE($this->_pdo, 'user_responsibility_get', [
					'values' => [
						':id' => intval($this->_requestedID)
					]
				]);
				$responsibility = $responsibility ? $responsibility[0] : [
					'id' => null,
					'user_id' => null,
					'units' => '',
					'assigned_users' => '',
					'proxy_users' => '',
					'span_start' => '',
					'span_end' => '',
					'responsibility' => '',
					'description' => '',
					'hidden' => null,
				];
				$responsibility['units'] = explode(',', $responsibility['units']);
				$responsibility['assigned_users'] = json_decode($responsibility['assigned_users'], true);
				$responsibility['proxy_users'] = json_decode($responsibility['proxy_users'], true);

				//user datalist
				$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
				// unset system user
				unset($users[array_search(1, array_column($users, 'id'))]);
				// reassing generic keys
				$users = array_values($users);
				// set datalist
				$user_datalist = array_column($users, 'name');
				
				// unit selection
				$units = [];
				foreach($this->_lang->_USER['units'] as $unit => $translation){
					$units[$translation] = ['checked' => boolval(in_array($unit, $responsibility['units']))];
				}

				$created = '';
				$creator = array_search($responsibility['user_id'], array_column($users, 'id'));
				if ($creator !== false){
					$creator = $users[$creator];
					$created = $this->_lang->GET('responsibility.created', [':name' => $creator['name']]);
				}
				// default values
				$result['render']['content'][] = [
					[
						'type' => 'textarea',
						'attributes' => [
							'name' => $this->_lang->GET('responsibility.task'),
							'value' => $responsibility['responsibility'] ? : '',
							'required' => true
						]
					], [
						'type' => 'textarea',
						'attributes' => [
							'name' => $this->_lang->GET('responsibility.context'),
							'value' => $responsibility['description'] ? : ''
						]
					], [
						'type' => 'checkbox',
						'attributes' => [
							'name' => $this->_lang->GET('responsibility.units')
						],
						'content' => $units
					], [
						'type' => 'date',
						'attributes' => [
							'name' => $this->_lang->GET('responsibility.applicability_start'),
							'value' => substr($responsibility['span_start'], 0, 10),
							'required' => true
						]
					], [
						'type' => 'date',
						'attributes' => [
							'name' => $this->_lang->GET('responsibility.applicability_end'),
							'value' => substr($responsibility['span_end'], 0, 10),
							'required' => true
						]
					]
				];
				if (intval($this->_requestedID)){
					$result['render']['content'][count($result['render']['content']) - 1][] = [
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('responsibility.update_hint')
						],
						'content' => $created
					];
					$result['render']['content'][count($result['render']['content']) - 1][] = [
						'type' => 'deletebutton',
						'attributes' => [
							'type' => 'button',
							'value' => $this->_lang->GET('responsibility.delete'),
							'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('responsibility.delete_confirm') ."', options:{".
								"'".$this->_lang->GET('general.cancel_button')."': false,".
								"'".$this->_lang->GET('general.ok_button')."': {value: true, class: 'reducedCTA'},".
								"}}).then(confirmation => {if (confirmation) api.responsibility('delete', 'responsibility', ". $responsibility['id'] . ")})"
						]
					];
				}

				// assigned users
				$result['render']['content'][] = [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('responsibility.assigned')
						]
					]
				];
				if ($responsibility['assigned_users']){
					foreach($responsibility['assigned_users'] as $user_id => $property){
						$user = array_search($user_id, array_column($users, 'id'));
						if ($user === false) continue;
						$user = $users[$user];

						$result['render']['content'][count($result['render']['content']) - 1][] = [
							'type' => 'text',
							'attributes' => [
								'name' => $this->_lang->GET('responsibility.assigned'),
								'value' => $user['name'],
							],
							'hint' => $property ? $this->_lang->GET('responsibility.accepted', [':date' => $property]) : null
						];
					}
				}
				// add empty field
				$result['render']['content'][count($result['render']['content']) - 1][] = [
					'type' => 'text',
					'attributes' => [
						'name' => $this->_lang->GET('responsibility.assigned'),
						'multiple' => true
					],
					'datalist' => $user_datalist
				];

				// proxy users
				$result['render']['content'][] = [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('responsibility.proxy')
						]
					]
				];
				if ($responsibility['proxy_users']){
					foreach($responsibility['proxy_users'] as $user_id => $property){
						$user = array_search($user_id, array_column($users, 'id'));
						if ($user === false) continue;
						$user = $users[$user];

						$result['render']['content'][count($result['render']['content']) - 1][] = [
							'type' => 'text',
							'attributes' => [
								'name' => $this->_lang->GET('responsibility.proxy'),
								'value' => $user['name'],
							],
							'hint' => $property ? $this->_lang->GET('responsibility.accepted', [':date' => $property]) : null
						];
					}
				}
				// add empty field
				$result['render']['content'][count($result['render']['content']) - 1][] = [
					'type' => 'text',
					'attributes' => [
						'name' => $this->_lang->GET('responsibility.proxy'),
						'multiple' => true
					],
					'datalist' => $user_datalist
				];
				break;
			case 'DELETE':
				if (SQLQUERY::EXECUTE($this->_pdo, 'user_responsibility_delete', [
					'values' => [
						':id' => $this->_requestedID
					]
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('responsibility.delete_success'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('responsibility.delete_error'),
						'type' => 'error'
					]]);
				break;
		}
		$this->response($result);
	}
}
?>
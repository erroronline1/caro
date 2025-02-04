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

// write and read user messages
class MEASURE extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedID = isset(REQUEST[2]) ? REQUEST[2] : null;
	}

	/**
	 *                                 
	 *   _____ ___ ___ ___ _ _ ___ ___ 
	 *  |     | -_| .'|_ -| | |  _| -_|
	 *  |_|_|_|___|__,|___|___|_| |___|
	 *
	 * add, edit or view measures
	 */
	public function measure(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				// set up general properties
				$measure = [
					':content' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.suggestion')),
					':user_id' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.anonymous')) ? null : $_SESSION['user']['id']
				];
				if (!$measure) $this->response([], 400);
				
				if (SQLQUERY::EXECUTE($this->_pdo, 'measure_post', [
					'values' => $measure
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('measure.suggestion_saved'),
						'id' => $this->_pdo->lastInsertId(),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('measure.suggestion_save_error'),
						'id' => false,
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				if (!PERMISSION::permissionFor('measureedit')) $this->response([], 401);
				$measure = [
					':measures' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.suggestion')),
					':closed' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.closed')) ? json_encode(['date' => $this->_currentdate->format('Y-m-d H:i'), 'user' => $_SESSION['user']['name']]) : null,
					':id' => $this->_requestedID
				];
				
				if (SQLQUERY::EXECUTE($this->_pdo, 'measure_put', [
					'values' => $measure
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('measure.measure_saved'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('measure.measure_save_error'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$result = [];
				$select = [
					'proposal' => [
						$this->_lang->GET('measure.new') => ['value' => '0']
					]
				];
				// get measures and assemble selection
				$measures = SQLQUERY::EXECUTE($this->_pdo, 'measure_get');
				foreach($measures as $measure){
					$select['proposal'][$measure['timestamp'] . ' ' . substr($measure['content'], 0, 64) . '...'] = intval($this->_requestedID) === $measure['id'] ? ['value' => $measure['id'], 'selected' => true] : ['value' => $measure['id']];
				}
				if (($selected = array_search($this->_requestedID, array_column($measures, 'id'))) !== false) $measure = $measures[$selected];
				else $measure = [
					'id' => null,
					'user_name' => null,
					'content' => '',
					'votes' => null,
					'measures' => null,
					'closed' => null,
				];

				$result['render']['form'] = [
					'data-usecase' => 'measure',
					'action' => "javascript:api.measure('" . ($measure['id'] ? 'put' : 'post') . "', 'measure', " . $measure['id']. ")"
				];

				// selection
				$result['render']['content'] = [[
					[
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('measure.new'),
							'onchange' => "api.measure('get', 'measure', this.value);"
						],
						'content' => $select['proposal']
					]
				]];

				if (!$measure['id']){
					// new suggestion
					$result['render']['content'][] = [
						[
							'type' => 'textarea',
							'attributes' => [
								'name' => $this->_lang->GET('measure.suggestion'),
								'required' => true
							]
						], [
							'type' => 'checkbox',
							'attributes' => [
								'name' => $this->_lang->GET('measure.anonymous')
							],
							'content' => [
								$this->_lang->GET('measure.anonymous') => []
							],
							'hint' => $this->_lang->GET('measure.anonymous_hint')
						]
					];
				}
				else {
					if (PERMISSION::permissionFor('measureedit')){
						$measure['closed'] = json_decode($measure['closed'] ? : '', true);
						$result['render']['content'][] = [
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => ($measure['user_name'] ? : $this->_lang->GET('measure.anonymous_user')) . ' ' . $measure['timestamp']
								],
								'content' => $measure['content']
							],	[
								'type' => 'textarea',
								'attributes' => [
									'name' => $this->_lang->GET('measure.measure')
								]
							], [
								'type' => 'checkbox',
								'attributes' => [
									'name' => $this->_lang->GET('measure.closed')
								],
								'content' => [
									$this->_lang->GET('measure.closed') => $measure['closed'] ? ['checked' => true] : []
								],
								'hint' => $measure['closed'] ? $this->_lang->GET('measure.closed_info', [':user' => $measure['closed']['user'], ':date' => $measure['closed']['date']]) : null
							]
						];
						// add votes
					}
					else {

					}
				}





				break;
			case 'DELETE':
				if (!PERMISSION::permissionFor('measureedit')) $this->response([], 401);
				if (SQLQUERY::EXECUTE($this->_pdo, 'measure_delete', [
					'values' => [
						':id' => $this->_requestedID
					]
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('measure.deleted'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('measure.delete_error'),
						'type' => 'error'
					]]);
		}
		$this->response($result);
	}

	/**
	 *           _       
	 *   _ _ ___| |_ ___ 
	 *  | | | . |  _| -_|
	 *   \_/|___|_| |___|
	 *
	 * 
	 */
	public function vote(){
		if (array_intersect(['group'], $_SESSION['user']['permissions'])) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				// anonymous user id upvote 1 downote -1 , able to calculate median
				break;
		}
		$this->response($result);
	}

}
?>
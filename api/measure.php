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
	private $_requestedVote = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedID = isset(REQUEST[2]) ? REQUEST[2] : null;
		$this->_requestedVote = isset(REQUEST[3]) ? REQUEST[3] : null;
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
				])) {
					// get users and trigger system message to all
					$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
					$this->alertUserGroup(['user' => array_column($user, 'name')], str_replace('\n', ', ', $this->_lang->GET('measure.alert_new', [
						':link' => '<a href="javascript:void(0);" onclick="api.measure(\'get\', \'measure\')">' . $this->_lang->GET('menu.communication.measure', [], true). '</a>',
					], true)));

					$this->response([
					'response' => [
						'msg' => $this->_lang->GET('measure.suggestion_saved'),
						'id' => $this->_pdo->lastInsertId(),
						'type' => 'success'
					]]);
				}
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('measure.suggestion_save_error'),
						'id' => false,
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				if (!PERMISSION::permissionFor('measureedit')) $this->response([], 401);
				$measure = SQLQUERY::EXECUTE($this->_pdo, 'measure_get_by_id', [
					'values' => [':id' => $this->_requestedID]
				]);
				$measure = $measure ? $measure[0] : null;
				if (!$measure) $this->response([], 404);

				// check for changes as put request is always triggered through the frontend since empty values are legit
				$changed = !(
					$measure['measures'] == UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.measure')) &&
					boolval($measure['closed']) == boolval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.closed')))
				);
				$measure['measures'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.measure'));
				$measure['closed'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.closed')) ? UTILITY::json_encode(['date' =>$this->_date['servertime']->format('Y-m-d H:i'), 'user' => $_SESSION['user']['name']]) : null;
				$measure['id'] = $this->_requestedID;
				$measure['last_user'] = $_SESSION['user']['name'];

				// delete. as edits are displayed within a modal this is handled by a checkbox instead of a confirming button, hence no delete request-method
				if (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.delete'))){
					if (SQLQUERY::EXECUTE($this->_pdo, 'measure_delete', [
						'values' => [
							':id' => $this->_requestedID
						]
					])) $this->response([
						'response' => [
							'msg' => $this->_lang->GET('measure.deleted'),
							'type' => 'deleted'
						]]);
					else $this->response([
						'response' => [
							'msg' => $this->_lang->GET('measure.delete_error'),
							'type' => 'error'
						]]);
				}

				// put if not deleted
				if (SQLQUERY::EXECUTE($this->_pdo, 'measure_put', [
					'values' =>[
						':measures' => $measure['measures'],
						':closed' => $measure['closed'],
						':id' => $measure['id'],
						':last_user' => $_SESSION['user']['name']
					]
				])) {
					if ($changed) // alert on changes only
						$this->alertUserGroup(['user' => [$measure['user_name']]], str_replace('\n', ', ', $this->_lang->GET('measure.alert_response', [
							':user' => $_SESSION['user']['name'],
							':content' => substr($measure['content'], 0, 64) . (strlen($measure['content']) > 64 ? '...' : ''),
							':link' => '<a href="javascript:void(0);" onclick="api.measure(\'get\', \'measure\')">' . $this->_lang->GET('menu.communication.measure'). '</a>',
						], true)));
					$this->response([
					'response' => [
						'msg' => $this->_lang->GET('measure.measure_saved'),
						'type' => 'success'
					]]);
				}
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('measure.measure_save_error'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$result = [];
				// get measures and assemble selection
				$measures = SQLQUERY::EXECUTE($this->_pdo, 'measure_get');

				// new suggestion button
				$result['render']['content'] = [[
					[
						'type' => 'button',
						'attributes' => [
							'value' => $this->_lang->GET('measure.new'),
							'onclick' => "new _client.Dialog({type: 'input', header: '". $this->_lang->GET('measure.new') ."', render: JSON.parse('".
								UTILITY::json_encode([
									[
										[
											'type' => 'textarea',
											'attributes' => [
												'name' => $this->_lang->GET('measure.suggestion'),
											]
										], [
											'type' => 'checkbox',
											'attributes' => [
												'name' => $this->_lang->GET('measure.anonymous'),
											],
											'content' => [
												$this->_lang->GET('measure.anonymous') => []
											],
											'hint' => $this->_lang->GET('measure.anonymous_hint')
										]
									]
								])
								."'), options:{".
									"'".$this->_lang->GET('general.ok_button')."': {value: true},".
									"'".$this->_lang->GET('general.cancel_button')."': {value: false, class: 'reducedCTA'},".
								"}}).then(response => {if (Object.keys(response).length) {".
									"api.measure('post', 'measure', null, _client.application.dialogToFormdata());}})"
						]
					]
				]];

				// all suggestions
				foreach($measures as $measure){
					$measurecontent = [];
					$measure['closed'] = json_decode($measure['closed'] ? : '', true);
					$measurecontent[] = [
						'type' => 'textsection',
						'attributes' => [
							'name' => ($measure['user_name'] ? : $this->_lang->GET('measure.anonymous_user')) . ' ' . $this->convertFromServerTime($measure['timestamp'])
						],
						'content' => $measure['content']
					];
					if ($measure['closed']){
						$measurecontent[] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('measure.closed')
							],
						];
					}
					else { // vote buttons
						$measure['votes'] = json_decode($measure['votes'] ? : '', true);
						$approval = strval($measure['votes'] ? array_sum(array_filter(array_values($measure['votes']), fn($v) => $v > 0)) : 0);
						$rejection = strval($measure['votes'] ? abs(array_sum(array_filter(array_values($measure['votes']), fn($v) => $v < 0))) : 0);
						$uservote = isset($measure['votes'][$_SESSION['user']['id']]) ? $measure['votes'][$_SESSION['user']['id']] : 0;
						$measurecontent[] = [
							'type' => 'button',
							'attributes' => [
								'value' => $approval,
								'data-type' => 'upvote',
								'class' => 'inlinebutton' . ($uservote > 0 ? ' voted': ''),
								'onclick' => "api.measure('put', 'vote', " . $measure['id'] . ", 1); this.classList.toggle('voted'); this.firstChild.nodeValue = String(parseInt(this.firstChild.nodeValue) + (this.classList.contains('voted') ? 1 : -1));",
								'title' => $this->_lang->GET('measure.thumbs_up') . ($uservote > 0 ? ' ' . $this->_lang->GET('measure.thumbs_you') : '') . ' ' . $this->_lang->GET('measure.thumbs_others', [':num' => $approval - ($uservote > 0 ? $uservote : 0)])
							],
						];
						$measurecontent[] = [
							'type' => 'button',
							'attributes' => [
								'value' => $rejection,
								'data-type' => 'downvote',
								'class' => 'inlinebutton' . ($uservote < 0 ? ' voted': ''),
								'onclick' => "api.measure('put', 'vote', " . $measure['id'] . ", -1); this.classList.toggle('voted'); this.firstChild.nodeValue = String(parseInt(this.firstChild.nodeValue) + (this.classList.contains('voted') ? 1 : -1));",
								'title' => $this->_lang->GET('measure.thumbs_down') . ($uservote < 0 ? ' ' . $this->_lang->GET('measure.thumbs_you') : '') . ' ' . $this->_lang->GET('measure.thumbs_others', [':num' => $rejection + ($uservote < 0 ? $uservote : 0)])
							],
						];
						$measurecontent[] = [
							'type' => 'br'
						];
					}
					if ($measure['measures'])
						$measurecontent[] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('measure.measure')
							],
							'content' => $measure['measures'],
							'hint' => $this->_lang->GET('measure.last_touch', [':user' => $measure['last_user'], ':date' => $this->convertFromServerTime($measure['last_touch'])])
						];
					if (PERMISSION::permissionFor('measureedit')) {
						$measurecontent[] = [
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('measure.measure'),
								'onclick' => "if (!this.disabled) new _client.Dialog({type: 'input', header: '". $this->_lang->GET('measure.measure') ."', render: JSON.parse('".
									UTILITY::json_encode([
										[
											[
												'type' => 'textarea',
												'attributes' => [
													'name' => $this->_lang->GET('measure.measure'),
													'value' => $measure['measures'] ? : ''
												]
											], [
												'type' => 'checkbox',
												'attributes' => [
													'name' => $this->_lang->GET('measure.closed'),
												],
												'content' => [
													$this->_lang->GET('measure.closed') => $measure['closed'] ? ['checked' => true] : [],
													$this->_lang->GET('measure.delete') => ['class' => 'red']
												],
												'hint' => $this->_lang->GET('measure.delete_confirm_header')
											],
										]
									])
									."'), options:{".
										"'".$this->_lang->GET('general.ok_button')."': {value: true},".
									"}}).then(response => {api.measure('put', 'measure', " . $measure['id'] . ", _client.application.dialogToFormdata());}); this.disabled = true;"
							]
						];
					}
					$result['render']['content'][] = $measurecontent;
				}
				break;
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
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				// anonymous user id upvote 1 downote -1 , able to calculate median
				$measure = SQLQUERY::EXECUTE($this->_pdo, 'measure_get_by_id', [
					'values' => [':id' => $this->_requestedID]
				]);
				$measure = $measure ? $measure[0] : null;
				if (!$measure) $this->response([], 404);
				$measure['votes'] = json_decode($measure['votes'] ? : '', true);
				
				// sanitize input or exit error
				if (intval($this->_requestedVote) > 0) $this->_requestedVote = 1; 
				elseif (intval($this->_requestedVote) < 0) $this->_requestedVote = -1;
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('measure.vote_error'),
						'type' => 'error'
					]]);
				if (isset($measure['votes'][$_SESSION['user']['id']]) && $measure['votes'][$_SESSION['user']['id']] === $this->_requestedVote) {
					unset($measure['votes'][$_SESSION['user']['id']]); // revoke vote
					$voted = 'deleted';
				}
				else {
					$measure['votes'][$_SESSION['user']['id']] = $this->_requestedVote; // insert or update vote
					$voted = 'success';
				}

				if (SQLQUERY::EXECUTE($this->_pdo, 'measure_vote', [
					'values' => [
						':votes' => UTILITY::json_encode($measure['votes']),
						':id' => $measure['id']
					]
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('measure.vote_confirm'),
						'type' => $voted
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('measure.vote_error'),
						'type' => 'error'
					]]);

				break;
		}
	}
}
?>
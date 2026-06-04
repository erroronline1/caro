<?php
/**
 * SPDX-FileNotice: Part of erroronline1/caro a quality management software.
 * SPDX-FileCopyrightText: © 2023 error on line 1 <dev@erroronline.one>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

namespace CARO\API;

// write and read user messages
class MEASURE extends API {
	// processed parameters for readability
	public ?string $_requestedMethod = REQUEST[1];
	private mixed $_requestedID = null;
	private mixed $_requestedVote = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user']) || array_intersect(['patient'], $_SESSION['user']['permissions'])) $this->response([], 401);

		$this->_requestedID = REQUEST[2] ?? null;
		$this->_requestedVote = REQUEST[3] ?? null;
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
		$response = [
			'title' => $this->_lang->GET('measure.navigation.measure')
		];
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				// set up general properties
				$measure = [
					':content' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.suggestion')),
					':user_id' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.anonymous')) ? null : $_SESSION['user']['id']
				];
				if (!$measure) $this->response([], 400);
				
				if ($this->_sqlinterface->EXECUTE('measure_post', $measure)) {
					// get users and trigger system message to all
					$user = $this->_sqlinterface->EXECUTE('user_get_datalist');
					$this->alertUserGroup(['user' => array_column($user, 'name')], str_replace('\n', ', ', $this->_lang->GET('measure.alert_new', [
						':link' => '<a href="javascript:void(0);" onclick="api.measure(\'get\', null, \'measure\')">' . $this->_lang->GET('measure.navigation.measure', [], true). '</a>',
					], true)));

					$this->response([
					'toast' => [
						'msg' => $this->_lang->GET('measure.suggestion_saved'),
						'type' => 'success'
					]]);
				}
				else $this->response([
					'toast' => [
						'msg' => $this->_lang->GET('measure.suggestion_save_error'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				if (!PERMISSION::permissionFor('measureedit')) $this->response([], 401);
				$measure = $this->_sqlinterface->EXECUTE('measure_get_by_id', [
					':id' => $this->_requestedID
				]);
				$measure = $measure ? $measure[0] : null;
				if (!$measure) $this->response([], 404);

				// check for changes as put request is always triggered through the frontend since empty values are legit
				$changed = !(
					$measure['measures'] == UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.measure')) &&
					boolval($measure['closed']) == boolval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.closed')))
				);
				$measure['measures'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.measure'));
				$measure['closed'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.closed')) ? UTILITY::json_encode(['date' => $this->_date['servertime']->format('Y-m-d H:i'), 'user' => $_SESSION['user']['name']]) : null;
				$measure['id'] = $this->_requestedID;
				$measure['last_user'] = $_SESSION['user']['name'];

				// delete. as edits are displayed within a modal this is handled by a checkbox instead of a confirming button, hence no delete request-method
				if (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('measure.delete'))){
					if ($this->_sqlinterface->EXECUTE('measure_delete', [
						':id' => $this->_requestedID
					])) $this->response([
						'toast' => [
							'msg' => $this->_lang->GET('measure.deleted'),
							'type' => 'deleted'
						]]);
					else $this->response([
						'toast' => [
							'msg' => $this->_lang->GET('measure.delete_error'),
							'type' => 'error'
						]]);
				}

				// put if not deleted
				if ($this->_sqlinterface->EXECUTE('measure_put', [
					':measures' => $measure['measures'],
					':closed' => $measure['closed'],
					':id' => $measure['id'],
					':last_user' => $_SESSION['user']['name']
				])) {
					if ($changed) // alert on changes only
						$this->alertUserGroup(['user' => [$measure['user_name']]], str_replace('\n', ', ', $this->_lang->GET('measure.alert_response', [
							':user' => $_SESSION['user']['name'],
							':content' => strip_tags(substr($measure['content'], 0, 64) . (strlen($measure['content']) > 64 ? '...' : '')),
							':link' => '<a href="javascript:void(0);" onclick="api.measure(\'get\', null, \'measure\')">' . $this->_lang->GET('measure.navigation.measure'). '</a>',
						], true)));
					$this->response([
					'toast' => [
						'msg' => $this->_lang->GET('measure.measure_saved'),
						'type' => 'success'
					]]);
				}
				else $this->response([
					'toast' => [
						'msg' => $this->_lang->GET('measure.measure_save_error'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				// get measures and assemble selection
				$measures = $this->_sqlinterface->EXECUTE('measure_get');

				// new suggestion button
				$response['render']['content'] = [[
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
								"}}, 'FormData').then(response => {if (response) {".
									"api.measure('post', response, 'measure', null);}})"
						]
					]
				]];

				// all suggestions
				foreach ($measures as $measure){
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
						$uservote = $measure['votes'][$_SESSION['user']['id']] ?? 0;
						$measurecontent[] = [
							'type' => 'button',
							'attributes' => [
								'value' => $approval,
								'data-type' => 'upvote',
								'class' => 'inlinebutton' . ($uservote > 0 ? ' voted': ''),
								'onclick' => "api.measure('put', null, 'vote', " . $measure['id'] . ", 1); this.classList.toggle('voted'); this.firstChild.nodeValue = String(parseInt(this.firstChild.nodeValue) + (this.classList.contains('voted') ? 1 : -1));",
								'title' => $this->_lang->GET('measure.thumbs_up') . ($uservote > 0 ? ' ' . $this->_lang->GET('measure.thumbs_you') : '') . ' ' . $this->_lang->GET('measure.thumbs_others', [':num' => $approval - ($uservote > 0 ? $uservote : 0)])
							],
						];
						$measurecontent[] = [
							'type' => 'button',
							'attributes' => [
								'value' => $rejection,
								'data-type' => 'downvote',
								'class' => 'inlinebutton' . ($uservote < 0 ? ' voted': ''),
								'onclick' => "api.measure('put', null, 'vote', " . $measure['id'] . ", -1); this.classList.toggle('voted'); this.firstChild.nodeValue = String(parseInt(this.firstChild.nodeValue) + (this.classList.contains('voted') ? 1 : -1));",
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
													'value' => $measure['measures'] ? preg_replace(['/\r/','/\n/', '/"/','/\'/'],['', "\\n", '\"', "\'"], $measure['measures']): ''
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
									"}}, 'FormData').then(response => {if (response) { api.measure('put', response, 'measure', " . $measure['id'] . "); this.disabled = true;}});"
							]
						];
					}
					$response['render']['content'][] = $measurecontent;
				}
				break;
		}
		$this->response($response);
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
				$measure = $this->_sqlinterface->EXECUTE('measure_get_by_id', [
					':id' => $this->_requestedID
				]);
				$measure = $measure ? $measure[0] : null;
				if (!$measure) $this->response([], 404);
				$measure['votes'] = json_decode($measure['votes'] ? : '', true);
				
				// sanitize input or exit error
				if (intval($this->_requestedVote) > 0) $this->_requestedVote = 1; 
				elseif (intval($this->_requestedVote) < 0) $this->_requestedVote = -1;
				else $this->response([
					'toast' => [
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

				if ($this->_sqlinterface->EXECUTE('measure_vote', [
					':votes' => UTILITY::json_encode($measure['votes']),
					':id' => $measure['id']
				])) $this->response([
					'toast' => [
						'msg' => $this->_lang->GET('measure.vote_confirm'),
						'type' => $voted
					]]);
				else $this->response([
					'toast' => [
						'msg' => $this->_lang->GET('measure.vote_error'),
						'type' => 'error'
					]]);

				break;
		}
	}
}
?>
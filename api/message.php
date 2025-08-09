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

// write and read user messages
class MESSAGE extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_conversation = null;
	private $_announcement = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user']) || array_intersect(['patient'], $_SESSION['user']['permissions'])) $this->response([], 401);

		$this->_conversation = $this->_announcement = isset(REQUEST[2]) ? REQUEST[2] : null;
	}

	/**
	 *                                                 _
	 *   ___ ___ ___ ___ _ _ ___ ___ ___ _____ ___ ___| |_ 
	 *  | .'|   |   | . | | |   |  _| -_|     | -_|   |  _|
	 *  |__,|_|_|_|_|___|___|_|_|___|___|_|_|_|___|_|_|_|
	 * 
	 * announcement handler
	 */
	public function announcement(){
		if (!PERMISSION::permissionFor('announcements')) $this->response([], 401);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$announcement = [
					':author_id' => $_SESSION['user']['id'],
					':organizational_unit' => null,
					':span_start' => $this->convertToServerTime(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.announcement.start'))) ? : null,
					':span_end' => $this->convertToServerTime(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.announcement.end'))) ? : null,
					':subject' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.announcement.subject')) ? : null,
					':text' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.announcement.text')) ? : null,
				];
				// chain checked units
				$units = [];
				foreach ($this->_lang->_USER['units'] as $unit => $description){
					if (UTILITY::propertySet($this->_payload, $description)) {
						$units[] = $unit;
					}
				}
				if ($units) $announcement[':organizational_unit'] = implode(',', $units);

				if (SQLQUERY::EXECUTE($this->_pdo, 'announcement_post', [
					'values' => $announcement
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('message.announcement.saved_success'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('message.announcement.saved_error'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				$announcement = [
					':id' => $this->_announcement,
					':author_id' => $_SESSION['user']['id'],
					':organizational_unit' => null,
					':span_start' => $this->convertToServerTime(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.announcement.start'))) ? : null,
					':span_end' => $this->convertToServerTime(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.announcement.end'))) ? : null,
					':subject' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.announcement.subject')) ? : null,
					':text' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.announcement.text')) ? : null,
				];
				// chain checked units
				$units = [];
				foreach ($this->_lang->_USER['units'] as $unit => $description){
					if (UTILITY::propertySet($this->_payload, $description)) {
						$units[] = $unit;
					}
				}
				if ($units) $announcement[':organizational_unit'] = implode(',', $units);

				if (SQLQUERY::EXECUTE($this->_pdo, 'announcement_put', [
					'values' => $announcement
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('message.announcement.saved_success'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('message.announcement.saved_error'),
						'type' => 'error'
					]]);
				break;
			case 'DELETE':
				if (SQLQUERY::EXECUTE($this->_pdo, 'announcement_delete', [
					'values' => [
						':id' => $this->_announcement
					]
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('message.announcement.deleted_success'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('message.announcement.deleted_error'),
						'type' => 'error'
					]]);
				break;
		}
	}

	/**
	 *                                                 _       
	 *   ___ ___ ___ ___ _ _ ___ ___ ___ _____ ___ ___| |_ ___ 
	 *  | .'|   |   | . | | |   |  _| -_|     | -_|   |  _|_ -|
	 *  |__,|_|_|_|_|___|___|_|_|___|___|_|_|_|___|_|_|_| |___|
	 * 
	 * 
	 * lists all announcements
	 */
	public function announcements(){
		$announcements = SQLQUERY::EXECUTE($this->_pdo, 'announcement_get_all');
		$response = ['render' => ['content' => []]];

		$units = [];
		foreach($this->_lang->_USER['units'] as $key => $value){
			$units[$value] = [];
		}

		if (PERMISSION::permissionFor('announcements')){
			$response['render']['content'][] = [
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('message.announcement.new'),
					'onclick' => "if (!this.disabled) new _client.Dialog({type: 'input', header: '". $this->_lang->GET('message.announcement.new') ."', render: JSON.parse('".
						UTILITY::json_encode(
							$this->announcementform([
								'units' => $units
							])
						)
						."'), options:{".
							"'".$this->_lang->GET('general.ok_button')."': {value: true},".
						"}}, 'FormData').then(response => {if (response) { api.message('post', 'announcement', 0, response);}});"
				]
			];
		}

		$markdown = new MARKDOWN();
		foreach($announcements as $announcement){
			$announcementunits = $units;
			$concerns = [];
			foreach(array_filter(explode(',', $announcement['organizational_unit'] ? : ''), fn($u) => boolval($u)) as $unit){
				$announcementunits[$this->_lang->_USER['units'][$unit]]['checked'] = true;
				$concerns[] = $this->_lang->_USER['units'][$unit];
			}

			$announcementcontent = [];
			if ($announcement['text']) {
				$announcementcontent[] = $announcement['text'];
				$announcementcontent[] = ' ';
			}
			if ($announcement['span_start']) $announcementcontent[] = $this->_lang->GET('message.announcement.start') . ' ' . $this->convertFromServerTime(substr($announcement['span_start'], 0 ,10));
			if ($announcement['span_end']) $announcementcontent[] = $this->_lang->GET('message.announcement.end') . ' ' . $this->convertFromServerTime(substr($announcement['span_end'], 0 ,10));
			if ($concerns) $announcementcontent[] = $this->_lang->GET('message.announcement.units') . ' ' . implode(', ', $concerns);
			$announcementcontent[] = $this->_lang->GET('message.announcement.last_edit', [':author' => $announcement['author_name'], ':date'=> $this->convertFromServerTime($announcement['date'])]);

			$content = [
				[
					'type' => 'announcementsection',
					'attributes' => [
						'name' => $announcement['subject']
					],
					'htmlcontent' => $markdown->md2html(implode("\n", $announcementcontent)), 
				]
			];
			if (PERMISSION::permissionFor('announcements')){
				$content[] = [
					'type' => 'button',
					'attributes' => [
						'value' => $this->_lang->GET('message.announcement.edit'),
						'onclick' => "if (!this.disabled) new _client.Dialog({type: 'input', header: '". $this->_lang->GET('message.announcement.edit') ."', render: JSON.parse('".
							UTILITY::json_encode(
								[
									...$this->announcementform([
										'subject' => $announcement['subject'] ? : '',
										'text' => $announcement['text'] ? preg_replace(['/\r/','/\n/'],['', "\\n"], $announcement['text']): '',
										'span_start' => $announcement['span_start'] ? $this->convertFromServerTime(substr($announcement['span_start'], 0, 10), true) : '',
										'span_end' => $announcement['span_end'] ? $this->convertFromServerTime(substr($announcement['span_end'], 0, 10), true) : '',
										'units' => $announcementunits
									])
								]
							)
							."'), options:{".
								"'".$this->_lang->GET('general.ok_button')."': {value: true},".
							"}}, 'FormData').then(response => {if (response) { api.message('put', 'announcement', " . $announcement['id'] . ", response); this.disabled = true;}});"
					]
				];
				$content[] = [
					'type' => 'deletebutton',
					'attributes' => [
						'value' => $this->_lang->GET('message.announcement.delete'),
						'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('message.announcement.delete_confirm') ."', options:{".
							"'" . $this->_lang->GET('general.cancel_button') . "': false,".
							"'" . $this->_lang->GET('general.ok_button') . "': {value: true, class: 'reducedCTA'}".
						"}}).then(confirmation => {if (confirmation) {api.message('delete', 'announcement', " . $announcement['id'] . "); this.disabled = true;}})"
					]
				];
			}
			$response['render']['content'][] = $content;
		}
		$this->response($response);
	}

	/**
	 *                                                 _   ___               
	 *   ___ ___ ___ ___ _ _ ___ ___ ___ _____ ___ ___| |_|  _|___ ___ _____ 
	 *  | .'|   |   | . | | |   |  _| -_|     | -_|   |  _|  _| . |  _|     |
	 *  |__,|_|_|_|_|___|___|_|_|___|___|_|_|_|___|_|_|_| |_| |___|_| |_|_|_|
	 *                                                                       
	 * reusable form generator
	 */
	private function announcementform($preset){
		return [
			[
				[
					'type' => 'text',
					'attributes' => [
						'name' => $this->_lang->GET('message.announcement.subject'),
						'required' => true,
						'value' => isset($preset['subject']) ? $preset['subject'] : ''
					]
				], [
					'type' => 'textarea',
					'attributes' => [
						'name' => $this->_lang->GET('message.announcement.text'),
						'value' => isset($preset['text']) ? $preset['text'] : ''
					]
				], [
					'type' => 'button',
					'attributes' => [
						'value' => $this->_lang->GET('tool.markdown.button'),
						'data-type' => 'markdown',
						'class' => 'floatright',
						'onclick' => 'api.tool(\\"get\\", \\"markdown\\")'
					]
				], [
					'type' => 'br'
				], [
					'type' => 'date',
					'attributes' => [
						'name' => $this->_lang->GET('message.announcement.start'),
						'value' => isset($preset['span_start'])? $preset['span_start'] : $this->_date['usertime']->format('Y-m-d')
					]
				], [
					'type' => 'date',
					'attributes' => [
						'name' => $this->_lang->GET('message.announcement.end'),
						'value' => isset($preset['span_end']) ? $preset['span_end'] : ''
					]
				], [
					'type' => 'checkbox',
					'attributes' => [
						'name' => $this->_lang->GET('message.announcement.units'),
					],
					'content' => $preset['units']
				]
			]
		];
	}

	/**
	 *                                   _   _
	 *   ___ ___ ___ _ _ ___ ___ ___ ___| |_|_|___ ___
	 *  |  _| . |   | | | -_|  _|_ -| .'|  _| | . |   |
	 *  |___|___|_|_|\_/|___|_| |___|__,|_| |_|___|_|_|
	 *
	 * view or delete conversations, overview or per user
	 */
	public function conversation(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'GET':
				$datalist = [];
				$response = ['render' => ['content' => []]];
				// prepare existing users lists
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
				foreach ($user as $row) {
					if (PERMISSION::filteredUser($row, ['id' => [1, $_SESSION['user']['id']], 'permission' => ['patient']])) continue;
					$datalist[] = $row['name'];
				}

				if ($this->_conversation){
					// select conversation
					$messages = SQLQUERY::EXECUTE($this->_pdo, 'message_get_conversation', [
						'values' => [
							':user' => $_SESSION['user']['id'],
							':conversation' => $this->_conversation
						]
					]);
					$conversation_content = [];

					// get user info on conversation partner
					$conversation_user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
						'replacements' => [
							':id' => $this->_conversation,
							':name' => ''
						]
					]);
					$conversation_user = $conversation_user ? $conversation_user[0] : null;

					// add up messages to conversation thread
					foreach ($messages as $conversation){
						$conversation_content[] = [
							'type' => 'message',
							'content' => [
								'img' => ($conversation['conversation_user'] > 1 ? './api/api.php/file/stream/' : '') . $conversation['image'],
								'user' => $conversation['conversation_user_name'] ? : $this->_lang->GET('general.deleted_user'),
								'text' => $this->_conversation !== '1' ? strip_tags($conversation['message']) : $conversation['message'],
								'date' => $this->convertFromServerTime($conversation['timestamp']),
							],
							'attributes' =>  [
								'class' => $conversation['sender'] === $_SESSION['user']['id'] ? 'conversation right': 'conversation',
								//inline system links won't work otherwise, therefore this property exists for conversation threads
								'ICON_onclick' => "_client.message.newMessage('". $this->_lang->GET('message.message.forward', [':user' => $conversation['conversation_user_name']]) ."', '', '" . 
									preg_replace(["/\r/", "/\n/", "/'/"], ["\\r", "\\n", "\\'"], $this->_lang->GET('message.message.forward_message', [':message' => strip_tags($conversation['message']), ':user' => $conversation['conversation_user_name'], ':date' => $conversation['timestamp']])) .
									"', {}, '" . implode(',', $datalist). "')",
								'id' => $conversation['id']
							]
						];
					}
					// render mark/unmark all messages button
					$response['render']['content'][] = [
						[
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('message.message.delete_mark'),
								'data-checked' => 0,
								'onclick' => "this.dataset.checked = + !Boolean(parseInt(this.dataset.checked)); document.querySelectorAll('article>input[type=\"radio\"]').forEach (radio => {radio.checked = Boolean(parseInt(this.dataset.checked));})"
							]
						]
					];
					// render delete button for marked messages
					$response['render']['content'][] = [
						[
							'type' => 'deletebutton',
							'attributes' => [
								'value' => $this->_lang->GET('message.message.delete'),
								'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('message.message.delete') ."', options:{".
									"'".$this->_lang->GET('message.message.delete_confirm_cancel')."': false,".
									"'".$this->_lang->GET('message.message.delete_confirm_ok')."': {value: true, class: 'reducedCTA'},".
									"}}).then(confirmation => { if (confirmation) {" .
										"let ids = [];" .
										"document.querySelectorAll('article>input[type=\"radio\"]').forEach (radio => {if (radio.checked) ids.push(radio.name.substring(4))});" .
										"api.message('delete', 'conversation', ids.join('_'));" .
									"} })"
							]
						]
					];

					// append messages
					$response['render']['content'][] = $conversation_content;

					// add reply input if not the system user
					if ($conversation['conversation_user'] != '1' && $conversation_user['name']) {
						$response['render']['content'][] = [
							[
								'type' => 'hidden',
								'attributes' => [
									'name' => $this->_lang->GET('message.message.to'),
									'value' => $conversation_user['name']
								]
							],
							[
								'type' => 'textarea',
								'attributes' => [
									'name' => $this->_lang->GET('message.message.message_to', [':user' => $conversation_user['name']]),
								],
								'hint' => $this->_lang->GET('message.message.forward_hint')
							]
						];
						$response['render']['form'] = [
							'data-usecase' => 'message',
							'action' => "javascript:api.message('post', 'message', '_')"
						];
					}
					require_once('notification.php');
					$notifications = new NOTIFICATION;
					$response['data'] = ['message_unseen' => $notifications->messageunseen()];
				}
				else {
					// display "mailbox"
					// new message to anybody button
					$response['render']['content'][] = [
						[
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('message.message.new'),
								'onclick' => "_client.message.newMessage('". $this->_lang->GET('message.message.new') ."', '', '', {}, '" . implode(',', $datalist). "')"
							]
						]
					];

					// select conversations for viewing user
					$conversations = SQLQUERY::EXECUTE($this->_pdo, 'message_get_conversations', [
						'values' => [
							':user' => $_SESSION['user']['id']
						]
					]);
					if ($conversations) {
						// list up all last messages, with unread mark if applicable
						foreach ($conversations as $conversation){
							// select unseen per conversation
							$unseen = SQLQUERY::EXECUTE($this->_pdo, 'message_get_unseen_conversations', [
								'values' => [
									':user' => $_SESSION['user']['id'],
									':conversation' => $conversation['conversation_user']
								]
							]);
							$unseen = $unseen ? intval($unseen[0]['unseen']) : 0;

							$conversation['message'] = preg_replace('/\n|\r/', ' ', strip_tags($conversation['message']));
							$response['render']['content'][] = [
								[
									'type' => 'message',
									'content' => [
										'img' => ($conversation['conversation_user'] > 1 ? './api/api.php/file/stream/' : '') . $conversation['image'],
										'user' => $conversation['conversation_user_name'] ? : $this->_lang->GET('general.deleted_user'),
										'text' => (strlen($conversation['message']) > 128 ? substr($conversation['message'], 0, 128) . '...': $conversation['message']),
										'date' => $this->convertFromServerTime($conversation['timestamp']),
										'unseen' => $unseen
									],
									'attributes' =>  [
										'onclick' => "api.message('get', 'conversation', '" . $conversation['conversation_user'] . "')",
									]
								]
							];
						}
					} else $response['render']['content'][] = $this->noContentAvailable($this->_lang->GET('message.message.no_messages'))[0];
				}
				break;
			case 'DELETE':
				if (SQLQUERY::EXECUTE($this->_pdo, 'message_delete_messages', [
					'values' => [
						':user' => $_SESSION['user']['id']
					],
					'replacements' => [
						':ids' => implode(',', array_map(fn($id) => intval($id), explode('_', $this->_conversation)))
					]
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('message.message.delete_success'),
						'redirect' => ['conversation'],
						'type' => 'deleted'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('message.message.delete_failure'),
						'redirect' => false,
						'type' => 'error'
					]]);
				break;
		}
		$this->response($response);
	}
	
	/**
	 *
	 *   _____ ___ ___ ___ ___ ___ ___
	 *  |     | -_|_ -|_ -| .'| . | -_|
	 *  |_|_|_|___|___|___|__,|_  |___|
	 *                        |___|
	 * posts a new message
	 */
	public function message(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				// get recipient ids
				$recipients = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => '',
						':name' => implode(',', preg_split('/[,;]\s{0,}/', UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message.to')) ? : ''))
					]
				]);
				if (!$recipients) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('user.error_not_found', [':name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message.to'))]),
						'type' => 'error'
					]]);
				// do not send messages to yourself
				if (($self = array_search($_SESSION['user']['id'], array_column($recipients, 'id'))) !== false) {
					unset($recipients[$self]);
					if (!$recipients) $this->response([
						'response' => [
							'msg' => $this->_lang->GET('message.message.send_failure_self'),
							'type' => 'error'
						]]);
					}
				// post a message to each recipient
				$success = 0;
				foreach ($recipients as $recipient){
					if ($recipient['id'] < 2) continue;
					$message = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message.message')) ? : UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message.message_to', [':user' => $recipient['name']]));
					if (!$message) $this->response([
						'response' => [
							'msg' => $this->_lang->GET('message.message.send_failure', [':number' => count($recipients) - $success]),
							'redirect' => false,
							'type' => 'error'
						]]);
					if (SQLQUERY::EXECUTE($this->_pdo, 'message_post_message', [
						'values' => [
							'from_user' => $_SESSION['user']['id'],
							'to_user' => $recipient['id'],
							'message' => $message
						]
					])) $success++;
				}
				if ($success === count($recipients)) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('message.message.send_success'),
						'redirect' => ['conversation', count($recipients) < 2 ? $recipients[0]['id'] : 0],
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('message.message.send_failure', [':number' => count($recipients) - $success]),
						'redirect' => false,
						'type' => 'error'
					]]);
				break;
		}
	}
	
	/**
	 *               _     _
	 *   ___ ___ ___|_|___| |_ ___ ___
	 *  |  _| -_| . | |_ -|  _| -_|  _|
	 *  |_| |___|_  |_|___|_| |___|_|
	 *          |___|
	 * display all users grouped by units, permissions, etc.
	 */
	public function register(){
		// prepare existing users lists
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		$groups = ['units' => [], 'permissions' => [], 'orderauth' => [], 'name' => []];
		$response = ['render' => ['content' => []]];

		foreach ($users as $user){
			if (PERMISSION::filteredUser($user)) continue;

			// sort to groups, units, etc.
			$groups['name'][] = ['name' => $user['name'], 'image' => './api/api.php/file/stream/' . $user['image']];
			if ($user['orderauth']) $groups['orderauth'][] = ['name' => $user['name'], 'image' => './api/api.php/file/stream/' . $user['image']];
			if ($user['units'])
				foreach (explode(',', $user['units']) as $unit){
					if (!isset($groups['units'][$unit])) $groups['units'][$unit] = [];
					$groups['units'][$unit][] = ['name' => $user['name'], 'image' => './api/api.php/file/stream/' . $user['image']];
				}
			if ($user['permissions'])
				foreach (explode(',', $user['permissions']) as $permission){
					if (in_array($permission, ['user', 'group'])) continue;
					if (!isset($groups['permissions'][$permission])) $groups['permissions'][$permission] = [];
					$groups['permissions'][$permission][] = ['name' => $user['name'], 'image' => './api/api.php/file/stream/' . $user['image']];
				}
		}

		foreach ($groups as $group => $content){
			// display name list and order auth-list as a single panel
			if (in_array($group, ['name', 'orderauth'])){
				$links = [];
				ksort($content);

				// assemble message link
				foreach ($content as $user) $links[$user['name']] = [
					'href' => 'javascript:void(0)',
					'data-type' => 'input',
					'class' => 'messageto',
					'style' => "--icon: url('" . $user['image']. "')",
					'onclick' => "_client.message.newMessage('". $this->_lang->GET('order.message_orderer', [':orderer' => $user['name']]) . "', '" . $user['name'] . "', '', {}, [])"
				];
				switch ($group){
					case 'name':
						$description = $this->_lang->GET('message.register.users');
						break;
					case 'orderauth':
						$description = $this->_lang->GET('message.register.orderauth');
						break;
				}
				$response['render']['content'][] = [
					[
						'type' => 'links',
						'description' => $description,
						'content' => $links,
					]
				];
			} else {
				// display units and permissions as slideable multipanels
				$panel = [];
				foreach ($content as $sub => $users){
					$users = array_unique($users, SORT_REGULAR);
					ksort($users);

					// add "message to all users" of the panel
					$links = [
						$this->_lang->GET('message.register.message_all') => [
							'href' => 'javascript:void(0)',
							'data-type' => 'input',
							'class' => 'messageto',
							'style' => "--icon: url('')",
							'onclick' => "_client.message.newMessage('". $this->_lang->GET('order.message_orderer', [':orderer' => implode(', ', array_column($users, 'name'))]) ."', '" . implode(', ', array_column($users, 'name')) . "', '', {}, [])"
						]
					];

					// add "message to user"
					foreach ($users as $user) $links[$user['name']] = [
						'href' => 'javascript:void(0)',
						'data-type' => 'input',
						'class' => 'messageto',
						'style' => "--icon: url('" . $user['image']. "')",
						'onclick' => "_client.message.newMessage('". $this->_lang->GET('order.message_orderer', [':orderer' => $user['name']]) ."', '" . $user['name'] . "', '', {}, [])"
					];

					// append panel
					$panel[] = [
						[
							'type' => 'links',
							'description' => ($group === 'units' ? $this->_lang->GET('user.units') : $this->_lang->GET('user.display_permissions')) . ' ' . $this->_lang->_USER[$group][$sub],
							'content' => $links,
						]
					];
				}
				$response['render']['content'][] = $panel;
			}
		}
		$this->response($response);
	}
}
?>
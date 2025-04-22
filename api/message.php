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
class MESSAGE extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_conversation = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_conversation = isset(REQUEST[2]) ? REQUEST[2] : null;
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
				$result = ['render' => ['content' => []]];
				// prepare existing users lists
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
				foreach($user as $key => $row) {
					if ($row['id'] > 1 && $row['id'] !== $_SESSION['user']['id']) $datalist[] = $row['name'];
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
					foreach($messages as $conversation){
						$conversation_content[] = [
							'type' => 'message',
							'content' => [
								'img' => $conversation['image'],
								'user' => $conversation['conversation_user_name'] ? : $this->_lang->GET('message.deleted_user'),
								'text' => $this->_conversation !== '1' ? strip_tags($conversation['message']) : $conversation['message'],
								'date' => UTILITY::dateFormat($conversation['timestamp']),
							],
							'attributes' =>  [
								'class' => $conversation['sender'] === $_SESSION['user']['id'] ? 'conversation right': 'conversation',
								//inline system links won't work otherwise, therefore this property exists for conversation threads
								'ICON_onclick' => "_client.message.newMessage('". $this->_lang->GET('message.forward') ."', '', '" . 
									preg_replace(["/\r/", "/\n/", "/'/"], ["\\r", "\\n", "\\'"], $this->_lang->GET('message.forward_message', [':message' => strip_tags($conversation['message']), ':user' => $conversation['conversation_user_name'], ':date' => $conversation['timestamp']])) .
									"', {}, '" . implode(',', $datalist). "')"
							]
						];
					}
					// render delete button for conversation thread
					$result['render']['content'][] = [
						[
							'type' => 'deletebutton',
							'attributes' => [
								'value' => $this->_lang->GET('message.delete'),
								'type' => 'button',
								'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('message.delete') ."', options:{".
									"'".$this->_lang->GET('message.delete_confirm_cancel')."': false,".
									"'".$this->_lang->GET('message.delete_confirm_ok')."': {value: true, class: 'reducedCTA'},".
									"}}).then(confirmation => {if (confirmation) api.message('delete', 'conversation', " . $conversation['conversation_user'] . ")})"
							]
						]
					];

					// append messages
					$result['render']['content'][] = $conversation_content;

					// add reply input if not the system user
					if ($conversation['conversation_user'] != '1' && $conversation_user['name']) {
						$result['render']['content'][] = [
							[
								'type' => 'hidden',
								'attributes' => [
									'name' => $this->_lang->GET('message.to'),
									'value' => $conversation_user['name']
								]
							],
							[
								'type' => 'textarea',
								'attributes' => [
									'name' => $this->_lang->GET('message.message_to', [':user' => $conversation_user['name']]),
								],
								'hint' => $this->_lang->GET('message.forward_hint')
							]
						];
						$result['render']['form'] = [
							'data-usecase' => 'message',
							'action' => "javascript:api.message('post', 'message', '_')"
						];
					}
					require_once('notification.php');
					$notifications = new NOTIFICATION;
					$result['data'] = ['message_unseen' => $notifications->messageunseen()];
				}
				else {
					// display "mailbox"
					// new message to anybody button
					$result['render']['content'][] = [
						[
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('message.new'),
								'type' => 'button',
								'onclick' => "_client.message.newMessage('". $this->_lang->GET('message.new') ."', '', '', {}, '" . implode(',', $datalist). "')"
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
						foreach($conversations as $conversation){
							// select unseen per conversation
							$unseen = SQLQUERY::EXECUTE($this->_pdo, 'message_get_unseen_conversations', [
								'values' => [
									':user' => $_SESSION['user']['id'],
									':conversation' => $conversation['conversation_user']
								]
							]);
							$unseen = $unseen ? intval($unseen[0]['unseen']) : 0;

							$conversation['message'] = preg_replace('/\n|\r/', ' ', strip_tags($conversation['message']));
							$result['render']['content'][] = [
								[
									'type' => 'message',
									'content' => [
										'img' => $conversation['image'],
										'user' => $conversation['conversation_user_name'] ? : $this->_lang->GET('message.deleted_user'),
										'text' => (strlen($conversation['message'])>128 ? substr($conversation['message'], 0, 128) . '...': $conversation['message']),
										'date' => UTILITY::dateFormat($conversation['timestamp']),
										'unseen' => $unseen
									],
									'attributes' =>  [
										'onclick' => "api.message('get', 'conversation', '" . $conversation['conversation_user'] . "')",
									]
								]
							];
						}
					} else $result['render']['content'][] = $this->noContentAvailable($this->_lang->GET('message.no_messages'))[0];
				}
				break;
			case 'DELETE':
				if (SQLQUERY::EXECUTE($this->_pdo, 'message_delete_conversation', [
					'values' => [
						':conversation' => $this->_conversation,
						':user' => $_SESSION['user']['id']
					]
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('message.delete_success'),
						'redirect' => 'conversation',
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('message.delete_failure'),
						'redirect' => false,
						'type' => 'error'
					]]);
				break;
		}
		$this->response($result);
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
						':name' => implode(',', preg_split('/[,;]\s{0,}/', UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.to')) ? : ''))
					]
				]);
				if (!$recipients) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('user.error_not_found', [':name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.to'))]),
						'type' => 'error'
					]]);
				// do not send messages to yourself
				if (($self = array_search($_SESSION['user']['id'], array_column($recipients, 'id'))) !== false) {
					unset($recipients[$self]);
					if (!$recipients) $this->response([
						'response' => [
							'msg' => $this->_lang->GET('message.send_failure_self'),
							'type' => 'error'
						]]);
					}
				// post a message to each recipient
				$success = 0;
				foreach ($recipients as $recipient){
					if ($recipient['id'] < 2) continue;
					$message = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message')) ? : UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message_to', [':user' => $recipient['name']]));
					if (!$message) $this->response([
						'response' => [
							'msg' => $this->_lang->GET('message.send_failure', [':number' => count($recipients) - $success]),
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
						'msg' => $this->_lang->GET('message.send_success'),
						'redirect' => ['conversation', count($recipients) < 2 ? $recipients[0]['id'] : 0],
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('message.send_failure', [':number' => count($recipients) - $success]),
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
		$result = ['render' => ['content' => []]];

		foreach($users as $user){
			if ($user['id'] == 1) continue; // skip system user

			// sort to groups, units, etc.
			$groups['name'][] = $user['name'];
			if ($user['orderauth']) $groups['orderauth'][] = $user['name'];
			if ($user['units'])
				foreach(explode(',', $user['units']) as $unit){
					if (!isset($groups['units'][$unit])) $groups['units'][$unit] = [];
					$groups['units'][$unit][] = $user['name'];
				}
			if ($user['permissions'])
				foreach(explode(',', $user['permissions']) as $permission){
					if (in_array($permission, ['user', 'group'])) continue;
					if (!isset($groups['permissions'][$permission])) $groups['permissions'][$permission] = [];
					$groups['permissions'][$permission][] = $user['name'];
				}
		}

		foreach($groups as $group => $content){

			// display name list and order auth-list as a single panel
			if (in_array($group, ['name', 'orderauth'])){
				$links = [];
				ksort($content);

				// assemble message link
				foreach($content as $user) $links[$user] = [
					'href' => 'javascript:void(0)',
					'data-type' => 'input',
					'onclick' => "_client.message.newMessage('". $this->_lang->GET('order.message_orderer', [':orderer' => $user]) . "', '" . $user . "', '', {}, [])"
				];
				switch ($group){
					case 'name':
						$description = $this->_lang->GET('message.register_users');
						break;
					case 'orderauth':
						$description = $this->_lang->GET('message.register_orderauth');
						break;
				}
				$result['render']['content'][] = [
					[
						'type' => 'links',
						'description' => $description,
						'content' => $links,
					]
				];
			} else {
				// display units and permissions as slideable multipanels
				$panel = [];
				foreach($content as $sub => $users){
					$users = array_unique($users);
					ksort($users);

					// add "message to all users" of the panel
					$links = [
						$this->_lang->GET('message.register_message_all') => [
							'href' => 'javascript:void(0)',
							'data-type' => 'input',
							'onclick' => "_client.message.newMessage('". $this->_lang->GET('order.message_orderer', [':orderer' => implode(', ', $users)]) ."', '" . implode(', ', $users) . "', '', {}, [])"
						]
					];

					// add "message to user"
					foreach($users as $user) $links[$user] = [
						'href' => 'javascript:void(0)',
						'data-type' => 'input',
						'onclick' => "_client.message.newMessage('". $this->_lang->GET('order.message_orderer', [':orderer' => $user]) ."', '" . $user . "', '', {}, [])"
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
				$result['render']['content'][] = $panel;
			}
		}
		$this->response($result);
	}
}
?>
<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2024 error on line 1 (dev@erroronline.one)
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
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);

		$this->_conversation = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	/**
	 *                                   _   _
	 *   ___ ___ ___ _ _ ___ ___ ___ ___| |_|_|___ ___
	 *  |  _| . |   | | | -_|  _|_ -| .'|  _| | . |   |
	 *  |___|___|_|_|\_/|___|_| |___|__,|_| |_|___|_|_|
	 *
	 */
	public function conversation(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'GET':
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
					$conversation_user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
						'replacements' => [
							':id' => $this->_conversation,
							':name' => ''
						]
					]);
					$conversation_user = $conversation_user ? $conversation_user[0] : null;
					foreach($messages as $conversation){
						$conversation_content[] = [
							'type' => 'message',
							'content' => [
								'img' => $conversation['image'],
								'user' => $conversation['conversation_user_name'] ? : LANG::GET('message.deleted_user'),
								'text' => $this->_conversation !== '1' ? strip_tags($conversation['message']) : $conversation['message'],
								'date' => $conversation['timestamp'],
							],
							'attributes' =>  [
								'class' => $conversation['sender'] === $_SESSION['user']['id'] ? 'conversation right': 'conversation',
								//inline system links won't work otherwise
								'ICON_onpointerup' => "_client.message.newMessage('". LANG::GET('message.forward') ."', '', '" . 
									preg_replace(["/\r/","/\n/"], ["\\r", "\\n"], LANG::GET('message.forward_message', [':message' => strip_tags($conversation['message']), ':user' => $conversation['conversation_user_name'], ':date' => $conversation['timestamp']])) .
									"', {}, '" . implode(',', $datalist). "')"
							]
						];
					}
					$result['render']['content'][] = [
						[
							'type' => 'deletebutton',
							'attributes' => [
								'value' => LANG::GET('message.delete'),
								'type' => 'button',
								'onpointerup' => "new Dialog({type: 'confirm', header: '". LANG::GET('message.delete') ."', options:{".
									"'".LANG::GET('message.delete_confirm_cancel')."': false,".
									"'".LANG::GET('message.delete_confirm_ok')."': {value: true, class: 'reducedCTA'},".
									"}}).then(confirmation => {if (confirmation) api.message('delete', 'conversation', " . $conversation['conversation_user'] . ", 'inbox')})"
							]
						]
					];
					$result['render']['content'][] = $conversation_content;
					if ($conversation['conversation_user'] != '1' && $conversation_user['name']) {
						$result['render']['content'][] = [
							[
								'type' => 'hidden',
								'attributes' => [
									'name' => LANG::GET('message.to'),
									'value' => $conversation_user['name']
								]
							],
							[
								'type' => 'textarea',
								'attributes' => [
									'name' => LANG::GET('message.message_to', [':user' => $conversation_user['name']]),
								],
								'hint' => LANG::GET('message.forward_hint')
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
					$result['render']['content'][] = [
						[
							'type' => 'button',
							'attributes' => [
								'value' => LANG::GET('message.new'),
								'type' => 'button',
								'onpointerup' => "_client.message.newMessage('". LANG::GET('message.new') ."', '', '', {}, '" . implode(',', $datalist). "')"
							]
						]
					];
					// select conversations
					$conversations = SQLQUERY::EXECUTE($this->_pdo, 'message_get_conversations', [
						'values' => [
							':user' => $_SESSION['user']['id']
						]
					]);
					if ($conversations) {
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
										'user' => $conversation['conversation_user_name'] ? : LANG::GET('message.deleted_user'),
										'text' => (strlen($conversation['message'])>128 ? substr($conversation['message'], 0, 128) . '...': $conversation['message']),
										'date' => $conversation['timestamp'],
										'unseen' => $unseen
									],
									'attributes' =>  [
										'onpointerup' => "api.message('get', 'conversation', '" . $conversation['conversation_user'] . "')",
									]
								]
							];
						}
					} else $result['render']['content'][] = $this->noContentAvailable(LANG::GET('message.no_messages'))[0];
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
						'msg' => LANG::GET('message.delete_success'),
						'redirect' => false,
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => LANG::GET('message.delete_failure'),
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
	 */
	public function message(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				// get recipient ids
				$recipients = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => '',
						':name' => implode(',', preg_split('/[,;]\s{0,}/', UTILITY::propertySet($this->_payload, LANG::PROPERTY('message.to')) ? : ''))
					]
				]);
				if (!$recipients) $this->response([
					'response' => [
						'msg' => LANG::GET('user.error_not_found', [':name' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('message.to'))]),
						'type' => 'error'
					]]);
				// do not send messages to yourself
				if (($self = array_search($_SESSION['user']['id'], array_column($recipients, 'id'))) !== false) {
					unset($recipients[$self]);
					if (!$recipients) $this->response([
						'response' => [
							'msg' => LANG::GET('message.send_failure_self'),
							'type' => 'error'
						]]);
					}
				$success = 0;
				foreach ($recipients as $recipient){
					if ($recipient['id'] < 2) continue;
					$message = UTILITY::propertySet($this->_payload, LANG::PROPERTY('message.message')) ? : UTILITY::propertySet($this->_payload, LANG::PROPERTY('message.message_to', [':user' => $recipient['name']]));
					if (!$message) $this->response([
						'response' => [
							'msg' => LANG::GET('message.send_failure', [':number' => count($recipients) - $success]),
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
						'msg' => LANG::GET('message.send_success'),
						'redirect' => 'conversation',
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => LANG::GET('message.send_failure', [':number' => count($recipients) - $success]),
						'redirect' => false,
						'type' => 'error'
					]]);
				break;
		}
		$this->response($result);
	}
	
	/**
	 *               _     _
	 *   ___ ___ ___|_|___| |_ ___ ___
	 *  |  _| -_| . | |_ -|  _| -_|  _|
	 *  |_| |___|_  |_|___|_| |___|_|
	 *          |___|
	 */
	public function register(){
		// prepare existing users lists
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		$groups = ['units' => [], 'permissions' => [], 'orderauth' => [], 'name' => []];
		$result = ['render' => ['content' => []]];
		foreach($users as $user){
			if ($user['id'] == 1) continue;
			$mailto =  [
				'href' => 'javascript:void(0)',
				'data-type' => 'input',
				'onpointerup' => "_client.message.newMessage('". LANG::GET('order.message_orderer', [':orderer' => $user['name']]) ."', '" . $user['name'] . "', '', {}, [])"
			];
			$groups['name'][] = $user['name'];
			if ($user['orderauth']) $groups['orderauth'][] = $user['name'];
			if ($user['units'])
				foreach(explode(',', $user['units']) as $unit){
					if (!array_key_exists($unit, $groups['units'])) $groups['units'][$unit] = [];
					$groups['units'][$unit][] = $user['name'];
				}
			if ($user['permissions'])
				foreach(explode(',', $user['permissions']) as $permission){
					if (in_array($permission, ['user', 'group'])) continue;
					if (!array_key_exists($permission, $groups['permissions'])) $groups['permissions'][$permission] = [];
					$groups['permissions'][$permission][] = $user['name'];
				}
		}

		foreach($groups as $group => $content){
			if (in_array($group, ['name', 'orderauth'])){
				$links = [];
				ksort($content);
				foreach($content as $user) $links[$user] = [
					'href' => 'javascript:void(0)',
					'data-type' => 'input',
					'onpointerup' => "_client.message.newMessage('". LANG::GET('order.message_orderer', [':orderer' => $user]) . "', '" . $user . "', '', {}, [])"
				];
				switch ($group){
					case 'name':
						$description = LANG::GET('message.register_users');
						break;
					case 'orderauth':
						$description = LANG::GET('message.register_orderauth');
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
				$panel = [];
				foreach($content as $sub => $users){
					$users = array_unique($users);
					ksort($users);
					$links = [
						LANG::GET('message.register_message_all') => [
							'href' => 'javascript:void(0)',
							'data-type' => 'input',
							'onpointerup' => "_client.message.newMessage('". LANG::GET('order.message_orderer', [':orderer' => implode(', ', $users)]) ."', '" . implode(', ', $users) . "', '', {}, [])"
						]
					];
					foreach($users as $user) $links[$user] = [
						'href' => 'javascript:void(0)',
						'data-type' => 'input',
						'onpointerup' => "_client.message.newMessage('". LANG::GET('order.message_orderer', [':orderer' => $user]) ."', '" . $user . "', '', {}, [])"
					];
					$panel[] = [
						[
							'type' => 'links',
							'description' => ($group === 'units' ? LANG::GET('user.edit_units') : LANG::GET('user.display_permissions')) . ' ' . LANGUAGEFILE[$group][$sub],
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
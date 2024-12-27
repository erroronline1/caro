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

require_once('./_calendarutility.php');

// handle all notification within one call
class NOTIFICATION extends API {
	/**
	 * handle all notification within one call
	 */

	public $_requestedMethod = REQUEST[1];

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);
	}

	/**
	 *           _   _ ___
	 *   ___ ___| |_|_|  _|___
	 *  |   | . |  _| |  _|_ -|
	 *  |_|_|___|_| |_|_| |___|
	 *
	 */
	public function notifs(){
		$result = [
			'calendar_uncompletedevents' => $this->calendar(),
			'consumables_pendingincorporation' => $this->consumables(),
			'document_approval' => $this->documents(),
			'order_unprocessed' => $this->order(),
			'order_prepared' => $this->preparedorders(),
			'message_unnotified' => $this->messageunnotified(),
			'message_unseen' => $this->messageunseen()
		];
		$this->response($result);
	}

	/**
	 *           _           _
	 *   ___ ___| |___ ___ _| |___ ___
	 *  |  _| .'| | -_|   | . | .'|  _|
	 *  |___|__,|_|___|_|_|___|__,|_|
	 *
	 * checks system processable expiry dates, adds calendar reminders if applicable
	 * alerts a user group if selected
	 * used by service worker
	 */
	public function calendar(){
		$calendar = new CALENDARUTILITY($this->_pdo);
		$today = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
		$today->setTime(0, 0);

		// schedule certificate request
		$vendors = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
		foreach ($vendors as $vendor){
			$certificate = json_decode($vendor['certificate'], true);
			if ($certificate['validity']) $validity = new DateTime($certificate['validity'], new DateTimeZone(CONFIG['application']['timezone']));
			else continue;
			if ($validity > $today) continue;
			// check for open reminders. if none add a new. dependent on language setting, may set multiple on language change.
			$reminders = $calendar->search($this->_lang->GET('calendar.alert_vendor_certificate_expired', [':vendor' => $vendor['name']], true));
			$open = false;
			foreach($reminders as $reminder){
				if (!$reminder['closed']) $open = true;
			}
			if (!$open){
				$calendar->post([
					':type' => 'schedule',
					':span_start' => $today->format('Y-m-d H:i:s'),
					':span_end' => $today->format('Y-m-d H:i:s'),
					':author_id' => 1,
					':affected_user_id' => 1,
					':organizational_unit' => 'admin,office',
					':subject' => $this->_lang->GET('calendar.alert_vendor_certificate_expired', [':vendor' => $vendor['name']], true),
					':misc' => '',
					':closed' => '',
					':alert' => 1
					]);		   
			}
		}

		// schedule training evaluation
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', array_column($users, 'id'))
			]
		]);
		foreach($trainings as $training){
			if ($training['evaluation']) continue;
			$trainingdate = new DateTime($training['date'], new DateTimeZone(CONFIG['application']['timezone']));
			if (intval(abs($trainingdate->diff($this->_currentdate)->days)) > CONFIG['lifespan']['training_evaluation']){
				if (($user = array_search($training['user_id'], array_column($users, 'id'))) !== false) {// no deleted users
					// check for open reminders. if none add a new. dependent on language setting, may set multiple on language change.
					$subject = $this->_lang->GET('audit.userskills_notification_message', [
						':user' => $users[$user]['name'],
						':training' => $training['name'],
						':module' => $this->_lang->GET('menu.tools.audit'),
						':date' => $training['date']
					], true);
					$reminders = $calendar->search($subject);
					$open = false;
					foreach($reminders as $reminder){
						if (!$reminder['closed']) $open = true;
					}
					if (!$open){
							$calendar->post([
							':type' => 'schedule',
							':span_start' => $today->format('Y-m-d H:i:s'),
							':span_end' => $today->format('Y-m-d H:i:s'),
							':author_id' => 1,
							':affected_user_id' => null,
							':organizational_unit' => 'admin',
							':subject' => $subject,
							':misc' => '',
							':closed' => '',
							':alert' => 1
							]);		   		
					}
				}
			}
		}

		// schedule archived approved orders review
		$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_archived');
		$units = [];
		foreach ($orders as $order){
			if (!isset($units[$order['organizational_unit']])) $units[$order['organizational_unit']] = 0;
			$units[$order['organizational_unit']]++;
		}
		foreach ($units as $unit => $num){
			if ($num > CONFIG['limits']['order_approved_archived']) {
				$subject = $this->_lang->GET('order.alert_achived_limit', [
					':max' => CONFIG['limits']['order_approved_archived']
				], true);
				$reminders = $calendar->search($subject);
				$open = false;
				foreach($reminders as $reminder){
					if (!$reminder['closed']) $open = true;
				}
				if (!$open){
					$calendar->post([
						':type' => 'schedule',
						':span_start' => $today->format('Y-m-d H:i:s'),
						':span_end' => $today->format('Y-m-d H:i:s'),
						':author_id' => 1,
						':affected_user_id' => null,
						':organizational_unit' => $unit,
						':subject' => $subject,
						':misc' => '',
						':closed' => '',
						':alert' => 1
						]);		   		
				}
			}
		}

		$alerts = $calendar->alert($today->format('Y-m-d'));
		foreach($alerts as $event){
			$this->alertUserGroup(['unit' => $event['organizational_unit'] ? explode(',', $event['organizational_unit']) : explode(',', $event['affected_user_units'])], $this->_lang->GET('calendar.schedule.alert_message', [':content' => (isset($this->_lang->_USER['calendar']['timesheet']['pto'][$event['subject']]) ? $this->_lang->GET('calendar.timesheet.pto.' . $event['subject'], [], true) : $event['subject']), ':date' => substr($event['span_start'], 0, 10), ':author' => $event['author'], ':due' => substr($event['span_end'], 0, 10)], true) . ($event['affected_user'] ? ' (' . $event['affected_user'] . ')': ''));
		}

		$events = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
		$uncompleted = 0;
		foreach ($events as $row){
			if (array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) && $row['type'] !== 'timesheet' && !$row['closed']) $uncompleted++;
		}
		return $uncompleted;
	}

	/**
	 *                     _     _     _       
	 *   ___ ___ _____ ___| |___|_|___| |_ ___ 
	 *  |  _| . |     | . | | .'| |   |  _|_ -|
	 *  |___|___|_|_|_|  _|_|__,|_|_|_|_| |___|
	 *                |_|
	 * notify on open records containing complaints (currently landing page only)
	 * especially to inform any defined roles but supervisors about any existing open complaints
	 * ceo too keeping them informed
	 */
	public function complaints(){
		if (!PERMISSION::permissionFor('complaintclosing') && !array_intersect(['ceo'], $_SESSION['user']['permissions'])) return 0;
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');
		$number = 0;
		foreach ($data as $row){
			if ($row['record_type'] === 'complaint'){
				if (PERMISSION::pending('complaintclosing', $row['closed'])) $number++;
			}
		} 
		return $number;
	}

	/**
	 *                                 _   _
	 *   ___ ___ ___ ___ _ _ _____ ___| |_| |___ ___
	 *  |  _| . |   |_ -| | |     | .'| . | | -_|_ -|
	 *  |___|___|_|_|___|___|_|_|_|__,|___|_|___|___|
	 *
	 * notify on pending incorporations
	 */
	public function consumables(){
		$unapproved = 0;
		if (PERMISSION::permissionFor('incorporation')){
			$allproducts = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products_incorporation_attention');
			foreach($allproducts as $product) {
				if ($product['incorporated'] === '') continue;
				$product['incorporated'] = json_decode($product['incorporated'], true);
				if (isset($product['incorporated']['_denied'])) continue;
				elseif (!PERMISSION::fullyapproved('incorporation', $product['incorporated'])) $unapproved++;
			}
		}
		return $unapproved;
	}

	/**
	 *     _                           _       
	 *   _| |___ ___ _ _ _____ ___ ___| |_ ___ 
	 *  | . | . |  _| | |     | -_|   |  _|_ -|
	 *  |___|___|___|___|_|_|_|___|_|_|_| |___|
	 * 
	 * alerts eligible users about documents and components having to be approved
	 */
	public function documents(){
		// prepare all unapproved elements
		$components = SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist');
		$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$unapproved = 0;
		$hidden = [];
		foreach(array_merge($components, $documents) as $element){
			if ($element['context'] === 'bundle') continue;
			if ($element['hidden']) $hidden[] = $element['context'] . $element['name']; // since ordered by recent, older items will be skipped
			if (!in_array($element['context'] . $element['name'], $hidden)){
				if (PERMISSION::pending('documentapproval', $element['approval'])) $unapproved++;
				$hidden[] = $element['context'] . $element['name']; // hide previous versions at all costs
			}
		}
		return $unapproved;
	}

	/**
	 *                                                 _   _ ___ _       _
	 *   _____ ___ ___ ___ ___ ___ ___ _ _ ___ ___ ___| |_|_|  _|_|___ _| |
	 *  |     | -_|_ -|_ -| .'| . | -_| | |   |   | . |  _| |  _| | -_| . |
	 *  |_|_|_|___|___|___|__,|_  |___|___|_|_|_|_|___|_| |_|_| |_|___|___|
	 *                        |___|
	 * number of new messages that have not been notified of (system alert)
	 */
	public function messageunnotified(){
		$unnotified = SQLQUERY::EXECUTE($this->_pdo, 'message_get_unnotified', [
			'values' => [
				':user' => $_SESSION['user']['id']
			]
		]);
		$unnotified = $unnotified ? intval($unnotified[0]['number']) : 0;
		SQLQUERY::EXECUTE($this->_pdo, 'message_put_notified', [
			'values' => [
				':user' => $_SESSION['user']['id']
			]
			]);
		return $unnotified;
	}
	
	/**
	 *
	 *   _____ ___ ___ ___ ___ ___ ___ _ _ ___ ___ ___ ___ ___
	 *  |     | -_|_ -|_ -| .'| . | -_| | |   |_ -| -_| -_|   |
	 *  |_|_|_|___|___|___|__,|_  |___|___|_|_|___|___|___|_|_|
	 *                        |___|
	 * number of unseen messages
	 */
	public function messageunseen(){
		$unseen = SQLQUERY::EXECUTE($this->_pdo, 'message_get_unseen', [
			'values' => [
				':user' => $_SESSION['user']['id']
			]
		]);
		$unseen = $unseen ? intval($unseen[0]['number']) : 0;
		return $unseen;
	}

	/**
	 *             _
	 *   ___ ___ _| |___ ___
	 *  | . |  _| . | -_|  _|
	 *  |___|_| |___|___|_|
	 *
	 * number of unprocessed orders
	 */
	public function order(){
		$unprocessed = 0;
		$alerts = [];
		if (PERMISSION::permissionFor('orderprocessing')){
			$undelivered = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_unreceived_undelivered');
			foreach($undelivered as $order){
				$update = false;
				$decoded_order_data = null;
				$ordered = new DateTime($order['ordered'] ? : '', new DateTimeZone(CONFIG['application']['timezone']));
				$receive_interval = intval(abs($ordered->diff($this->_currentdate)->days / CONFIG['lifespan']['order_unreceived']));
				if ($order['ordered'] && $order['notified_received'] < $receive_interval){
					$decoded_order_data = json_decode($order['order_data'], true);
					$this->alertUserGroup(
						['permission' => ['purchase']],
						$this->_lang->GET('order.alert_unreceived_order', [
							':days' => $ordered->diff($this->_currentdate)->days,
							':ordertype' => $this->_lang->GET('order.ordertype.' . $order['ordertype'], [], true),
							':quantity' => $decoded_order_data['quantity_label'],
							':unit' => isset($decoded_order_data['unit_label']) ? $decoded_order_data['unit_label'] : '',
							':number' => isset($decoded_order_data['ordernumber_label']) ? $decoded_order_data['ordernumber_label'] : '',
							':name' => isset($decoded_order_data['productname_label']) ? $decoded_order_data['productname_label'] : '',
							':vendor' => isset($decoded_order_data['vendor_label']) ? $decoded_order_data['vendor_label'] : '',
							':commission' => $decoded_order_data['commission'],
							':orderer' => $decoded_order_data['orderer']
						], true)
					);
					$update = true;
				} else $receive_interval = $order['notified_received'];

				$received = new DateTime($order['received'] ? : '', new DateTimeZone(CONFIG['application']['timezone']));
				$delivery_interval = intval(abs($received->diff($this->_currentdate)->days / CONFIG['lifespan']['order_undelivered']));
				if ($order['received'] && $order['notified_delivered'] < $delivery_interval){
					if (!$decoded_order_data) $decoded_order_data = json_decode($order['order_data'], true);
					$this->alertUserGroup(
						['unit' => [$order['organizational_unit']]],
						$this->_lang->GET('order.alert_undelivered_order', [
							':days' => $received->diff($this->_currentdate)->days,
							':ordertype' => '<a href="javascript:void(0);" onpointerup="api.purchase(\'get\', \'approved\')"> ' . $this->_lang->GET('order.ordertype.' . $order['ordertype'], [], true) . '</a>',
							':quantity' => $decoded_order_data['quantity_label'],
							':unit' => isset($decoded_order_data['unit_label']) ? $decoded_order_data['unit_label'] : '',
							':number' => isset($decoded_order_data['ordernumber_label']) ? $decoded_order_data['ordernumber_label'] : '',
							':name' => isset($decoded_order_data['productname_label']) ? $decoded_order_data['productname_label'] : '',
							':vendor' => isset($decoded_order_data['vendor_label']) ? $decoded_order_data['vendor_label'] : '',
							':commission' => $decoded_order_data['commission'],
							':receival' => $order['received']
						], true)
					);
					$update = true;
				} else $delivery_interval = $order['notified_delivered'];

				// prepare alert flags
				if ($update) $alerts = SQLQUERY::CHUNKIFY($alerts, strtr(SQLQUERY::PREPARE('order_notified'),
					[
						':notified_received' => $receive_interval ? : 'NULL',
						':notified_delivered' => $delivery_interval ? : 'NULL',
						':id' => $order['id']
					]) . '; ');

			}
			// set alert flags
			foreach ($alerts as $alert){
				SQLQUERY::EXECUTE($this->_pdo, $alert);
			}

			$unprocessed = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_unprocessed');
			$unprocessed = $unprocessed ? intval($unprocessed[0]['num']) : 0;
		}
		return $unprocessed;
	}

	/**
	 *                                 _           _
	 *   ___ ___ ___ ___ ___ ___ ___ _| |___ ___ _| |___ ___ ___
	 *  | . |  _| -_| . | .'|  _| -_| . | . |  _| . | -_|  _|_ -|
	 *  |  _|_| |___|  _|__,|_| |___|___|___|_| |___|___|_| |___|
	 *  |_|         |_|
	 * 
	 * number of prepared orders for assigned units
	 */
	public function preparedorders(){
		if (!$_SESSION['user']['orderauth']) return 0;
		$prepared = 0;
		$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_prepared_orders');
		$units = $_SESSION['user']['units']; // see only orders for own units
		$organizational_orders = [];
		foreach($orders as $key => $row) {
			$order_data = json_decode($row['order_data'], true);
			if (array_intersect([$order_data['organizational_unit']], $units)) {
				$prepared++;
			}
		}
		return $prepared;
	}

	/**
	 *                         _
	 *   ___ ___ ___ ___ ___ _| |___
	 *  |  _| -_|  _| . |  _| . |_ -|
	 *  |_| |___|___|___|_| |___|___|
	 *
	 * number of unclosed records for assigned units
	 */
	public function records(){
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');
		$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$number = 0;
		$alerts = [];
		foreach ($data as $row){
			if (($row['record_type'] === 'complaint' && !PERMISSION::fullyapproved('complaintclosing', $row['closed']))
				|| ($row['record_type'] !== 'complaint' && !$row['closed'])){
				// rise counter for unit member
				if ($row['units'] && in_array($row['context'], ['casedocumentation', 'incident']) && array_intersect(explode(',', $row['units']), $_SESSION['user']['units'])) $number++;
				// alert if applicable
				$last = new DateTime($row['last_touch'], new DateTimeZone(CONFIG['application']['timezone']));
				$diff = intval(abs($last->diff($this->_currentdate)->days / CONFIG['lifespan']['open_record_reminder']));
				if ($row['notified'] < $diff){
					// get last considered document
					$lastdocument = $documents[array_search($row['last_document'], array_column($documents, 'id'))] ? : ['name' => $this->_lang->GET('record.retype_pseudodocument_name', [], true)];

					$this->alertUserGroup(
						['unit' => explode(',', $row['units'])],
						$this->_lang->GET('record.reminder_message', [
							':days' => $last->diff($this->_currentdate)->days,
							':date' => substr($row['last_touch'], 0, -3),
							':document' => $lastdocument['name'],			
							':identifier' => "<a href=\"javascript:javascript:api.record('get', 'record', '" . $row['identifier'] . "')\">" . $row['identifier'] . "</a>"
						], true)
					);
					// prepare alert flags
					$alerts = SQLQUERY::CHUNKIFY($alerts, strtr(SQLQUERY::PREPARE('records_notified'),
						[
							':notified' => $diff,
							':identifier' => $this->_pdo->quote($row['identifier'])
						]) . '; ');
				}
			}
		}
		// set alert flags
		foreach ($alerts as $alert){
			SQLQUERY::EXECUTE($this->_pdo, $alert);
		}
		return $number;
	}
}
?>
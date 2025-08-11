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

require_once('./_calendarutility.php');

// handle all notification within one call
class NOTIFICATION extends API {
	/**
	 * handle all notification within one call
	 */

	public $_requestedMethod = REQUEST[1];

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user']) || array_intersect(['patient'], $_SESSION['user']['permissions'])) $this->response([], 401);
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
			'audit_closing' => $this->audits(),
			'consumables_pendingincorporation' => $this->consumables(),
			'document_approval' => $this->documents(),
			'order_unprocessed' => $this->order(),
			'order_prepared' => $this->preparedorders(),
			'managementreview' => $this->managementreview(),
			'measure_unclosed' => $this->measures(),
			'responsibilities' => $this->responsibilities(),
			// make the following calls last no matter what to include all possible previous calendar entries and messages
			'calendar_uncompletedevents' => $this->calendar(),
			'message_unnotified' => $this->messageunnotified(),
			'message_unseen' => $this->messageunseen(),
		];
		$this->response($result);
	}

	/**
	 *             _ _ _       
	 *   ___ _ _ _| |_| |_ ___ 
	 *  | .'| | | . | |  _|_ -|
	 *  |__,|___|___|_|_| |___|
	 * 
	 * notify on unclosed audits
	 */
	public function audits(){
		if (!PERMISSION::permissionFor('audit')) return 0;
		$data = SQLQUERY::EXECUTE($this->_pdo, 'audit_get');
		$number = 0;
		foreach ($data as $row){
			if (!$row['closed']) $number++;
		} 
		return $number;
	}

	/**
	 *           _           _
	 *   ___ ___| |___ ___ _| |___ ___
	 *  |  _| .'| | -_|   | . | .'|  _|
	 *  |___|__,|_|___|_|_|___|__,|_|
	 *
	 * checks system processable expiry dates, adds calendar reminders if applicable
	 * alerts a user group if selected
	 */
	public function calendar(){
		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
		$today = new \DateTime('now');
		$today->setTime(0, 0);

		// alert if applicable despite cron for e.g. entries of sick colleagues after cron and still being notified during the day
		$alerts = $calendar->alert($today->format('Y-m-d'));
		foreach ($alerts as $event){
			// alert current events including workmates pto if alert is set
			$this->alertUserGroup(['unit' => $event['organizational_unit'] ? explode(',', $event['organizational_unit']) : explode(',', $event['affected_user_units'] ? : '')], $this->_lang->GET('calendar.schedule.alert_message', [':content' => (isset($this->_lang->_USER['calendar']['timesheet']['pto'][$event['subject']]) ? $this->_lang->GET('calendar.timesheet.pto.' . $event['subject'], [], true) : $event['subject']), ':date' => substr($event['span_start'], 0, 10), ':author' => $event['author'], ':due' => substr($event['span_end'], 0, 10)], true) . ($event['affected_user'] ? ' (' . $event['affected_user'] . ')': ''));
		}

		$events = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
		$uncompleted = 0;
		foreach ($events as $row){
			if (!$row['organizational_unit']) continue; 
			if (array_intersect(explode(',', $row['organizational_unit'] ? : ''), $_SESSION['user']['units']) && $row['type'] !== 'timesheet' && !$row['closed']) $uncompleted++;
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
	 * process reminders for vendor- and product-topics
	 */
	public function consumables(){
		// get pending incorporations
		$unapproved = 0;
		if (PERMISSION::permissionFor('incorporation')){
			$allproducts = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products');
			foreach ($allproducts as $product) {
				if (!$product['incorporated']) continue;
				$product['incorporated'] = json_decode($product['incorporated'] ? : '', true);
				// get latest incorporation entry;
				$latestincorporation = array_pop($product['incorporated']);
				if (!$latestincorporation || isset($latestincorporation['_denied'])) continue;
				elseif (!PERMISSION::fullyapproved('incorporation', $latestincorporation)) $unapproved++;
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
		foreach (array_merge($components, $documents) as $element){
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
	 *                                           _               _           
	 *   _____ ___ ___ ___ ___ ___ _____ ___ ___| |_ ___ ___ _ _|_|___ _ _ _ 
	 *  |     | .'|   | .'| . | -_|     | -_|   |  _|  _| -_| | | | -_| | | |
	 *  |_|_|_|__,|_|_|__,|_  |___|_|_|_|___|_|_|_| |_| |___|\_/|_|___|_____|
	 *                    |___|
	 * notify on unclosed managementreviews
	 */
	public function managementreview(){
		if (!PERMISSION::permissionFor('audit')) return 0;
		$data = SQLQUERY::EXECUTE($this->_pdo, 'management_get');
		$number = 0;
		foreach ($data as $row){
			if ($row['closed']) continue;
			$number++;
		} 
		return $number;
	}

	/**
	 *
	 *   _____ ___ ___ ___ _ _ ___ ___ ___
	 *  |     | -_| .'|_ -| | |  _| -_|_ -|
	 *  |_|_|_|___|__,|___|___|_| |___|___|
	 *
	 * number of unclosed measures 
	 */
	public function measures(){
		$measures = SQLQUERY::EXECUTE($this->_pdo, 'measure_get');
		return count(array_filter($measures, fn($m) => !$m['closed']));
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
		if (PERMISSION::permissionFor('orderprocessing')){
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
		foreach ($orders as $row) {
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
	 * alert message to units interval wise excluding
	 * * common
	 * * admin
	 * * office
	 */
	public function records(){
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');
		$number = 0;
		foreach ($data as $row){
			if (($row['record_type'] === 'complaint' && !PERMISSION::fullyapproved('complaintclosing', $row['closed']))
				|| ($row['record_type'] !== 'complaint' && !$row['closed'])){
				// rise counter for unit member
				if ($row['units'] && in_array($row['context'], ['casedocumentation', 'incident']) && array_intersect(explode(',', $row['units'] ? : ''), $_SESSION['user']['units'])) $number++;
			}
		}
		return $number;
	}

	/**
	 *                               _ _   _ _ _ _   _
	 *   ___ ___ ___ ___ ___ ___ ___|_| |_|_| |_| |_|_|___ ___
	 *  |  _| -_|_ -| . | . |   |_ -| | . | | | |  _| | -_|_ -|
	 *  |_| |___|___|  _|___|_|_|___|_|___|_|_|_|_| |_|___|___|
	 *              |_|
	 *
	 * responsibilities awaiting acceptance
	 */
	public function responsibilities(){
		$number = 0;
		$responsibilities = SQLQUERY::EXECUTE($this->_pdo, 'user_responsibility_get_all');
		foreach ($responsibilities as $row){
			$row['assigned_users'] = json_decode($row['assigned_users'], true);
			if (isset($row['assigned_users'][$_SESSION['user']['id']]) && !$row['assigned_users'][$_SESSION['user']['id']]) {
				$number++;
				continue;
			}
			$row['proxy_users'] = json_decode($row['proxy_users'], true);
			if (isset($row['proxy_users'][$_SESSION['user']['id']]) && !$row['proxy_users'][$_SESSION['user']['id']]) {
				$number++;
			}
		}
		return $number;
	}

	/**
	 *           _         _     _       _ _           _     _
	 *   ___ ___| |_ ___ _| |_ _| |___ _| | |_ ___ ___|_|___|_|___ ___ ___
	 *  |_ -|  _|   | -_| . | | | | -_| . |  _|  _| .'| |   | |   | . |_ -|
	 *  |___|___|_|_|___|___|___|_|___|___|_| |_| |__,|_|_|_|_|_|_|_  |___|
	 *                                                            |___|
	 * schedule training evaluation and insert schedules re-trainings on expiring ones
	 * alert message to units interval wise excluding
	 * * common
	 * * admin
	 * * office
	 * 
	 * currently called from landing page only
	 */
	public function scheduledtrainings(){
		// schedule training evaluation
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', array_column($users, 'id'))
			]
		]);

		$unitusers = [];
		$number = 0;
		// find all users within current users units
		foreach ($users as $user){
			if (array_intersect($_SESSION['user']['units'], explode(',', $user['units'] ? : ''))) $unitusers[] = $user['id'];
		}
		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', $unitusers)
			]
		]);
		foreach ($trainings as $training){
			if ($training['planned']) {
				$number++;
				continue;
			}
		}
		return $number;
	}
}
?>
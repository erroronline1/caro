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
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
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
			'form_approval' => $this->forms(),
			'order_unprocessed' => $this->order(),
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
		$vendors = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
		$today = new DateTime('now', new DateTimeZone(INI['application']['timezone']));
		$today->setTime(0, 0);
		foreach ($vendors as $vendor){
			$certificate = json_decode($vendor['certificate'], true);
			if ($certificate['validity']) $validity = new DateTime($certificate['validity'], new DateTimeZone(INI['application']['timezone']));
			else continue;
			if ($validity > $today) continue;
			// check for open reminders. if none add a new
			$reminders = $calendar->search(LANG::GET('calendar.alert_vendor_certificate_expired', [':vendor' => $vendor['name']]));
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
					':subject' => LANG::GET('calendar.alert_vendor_certificate_expired', [':vendor' => $vendor['name']]),
					':misc' => '',
					':closed' => '',
					':alert' => 1
					]);		   
			}
		}
		$alerts = $calendar->alert($today->format('Y-m-d'));
		foreach($alerts as $event){
			$this->alertUserGroup(['unit' => $event['organizational_unit'] ? explode(',', $event['organizational_unit']) : explode(',', $event['affected_user_units'])], LANG::GET('calendar.event_alert_message', [':content' => (array_key_exists($event['subject'], LANGUAGEFILE['calendar']['timesheet_pto']) ? LANGUAGEFILE['calendar']['timesheet_pto'][$event['subject']] : $event['subject']), ':date' => substr($event['span_start'], 0, 10), ':author' => $event['author'], ':due' => substr($event['span_end'], 0, 10)]));
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
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_identifiers');
		$number = 0;
		foreach ($data as $row){
			if ($row['complaint']){
				$closed = SQLQUERY::EXECUTE($this->_pdo, 'records_touched', [
					'values' => [
						':id' => $row['id']
						]
					]);
				$closed = $closed ? $closed[0] : '';
				if (PERMISSION::pending('complaintclosing', $closed['closed'])) $number++;
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
				if (array_key_exists('_denied', $product['incorporated'])) continue;
				elseif (!PERMISSION::fullyapproved('incorporation', $product['incorporated'])) $unapproved++;
			}
		}
		return $unapproved;
	}

	/**
	 *   ___
	 *  |  _|___ ___ _____ ___
	 *  |  _| . |  _|     |_ -|
	 *  |_| |___|_| |_|_|_|___|
	 *
	 * alerts eligible users about forms and components having to be approved
	 */
	public function forms(){
		// prepare all unapproved elements
		$components = SQLQUERY::EXECUTE($this->_pdo, 'form_component_datalist');
		$forms = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		$unapproved = 0;
		$hidden = [];
		foreach(array_merge($components, $forms) as $element){
			if ($element['context'] === 'bundle') continue;
			if ($element['hidden']) $hidden[] = $element['context'] . $element['name']; // since ordered by recent, older items will be skipped
			if (!in_array($element['context'] . $element['name'], $hidden)){
				if (PERMISSION::pending('formapproval', $element['approval'])) $unapproved++;
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
		$unnotified = $unnotified ? $unnotified[0]['number'] : 0;
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
		$unseen = $unseen ? $unseen[0]['number'] : 0;
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
			$unprocessed = $unprocessed ? $unprocessed[0]['num'] : 0;
		}
		return $unprocessed;
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
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_identifiers');
		$number = 0;
		foreach ($data as $row){
			if ($row['units'] && in_array($row['context'], ['casedocumentation', 'incident']) && array_intersect(explode(',', $row['units']), $_SESSION['user']['units'])){
				$closed = SQLQUERY::EXECUTE($this->_pdo, 'records_touched', [
					'values' => [
						':id' => $row['id']
						]
					]);
				$closed = $closed ? $closed[0] : '';
				if (($row['complaint'] && PERMISSION::fullyapproved('complaintclosing', $closed))
					|| (!$row['complaint'] && $closed)){
					$number++;
				}
			} 
		}
		return $number;
	}
}
?>
<?php
require_once('./calendarutility.php');

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

	public function calendar(){
		$calendar = new CALENDARUTILITY($this->_pdo);
		/**
		 * checks system processable expiry dates, adds calendar reminders if applicable
		 * alerts a user group if selected
		 * used by service worker
		 */
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-vendor-datalist'));
		$statement->execute();
		$vendors = $statement->fetchAll(PDO::FETCH_ASSOC);
		$today = new DateTime('now', new DateTimeZone(INI['timezone']));
		$today->setTime(0, 0);
		foreach ($vendors as $vendor){
			$certificate = json_decode($vendor['certificate'], true);
			if ($certificate['validity']) $validity = new DateTime($certificate['validity'], new DateTimeZone(INI['timezone']));
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
		$alerts = $calendar->alert(date('Y-m-d'));
		foreach($alerts as $event){
			$this->alertUserGroup(['unit' => $event['organizational_unit'] ? explode(',', $event['organizational_unit']) : explode(',', $event['affected_user_units'])], LANG::GET('calendar.event_alert_message', [':content' => (array_key_exists($event['subject'], LANGUAGEFILE['calendar']['timesheet_pto']) ? LANGUAGEFILE['calendar']['timesheet_pto'][$event['subject']] : $event['subject']), ':date' => substr($event['span_start'], 0, 10), ':author' => $event['author'], ':due' => substr($event['span_end'], 0, 10)]));
		}

		$events = $calendar->getWithinDateRange(null, date('Y-m-d'));
		$uncompleted = 0;
		foreach ($events as $row){
			if (array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) && $row['type'] !== 'timesheet' && !$row['closed']) $uncompleted++;
		}
		return $uncompleted;
	}

	/**
	 * notify on pending incorporations
	 */
	public function consumables(){
		$unapproved = 0;
		if (PERMISSION::permissionFor('incorporation')){
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-products-incorporation'));
			$statement->execute();
			$allproducts = $statement->fetchAll(PDO::FETCH_ASSOC);
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
	 * alerts eligible users about forms and components having to be approved
	 */
	public function forms(){
		// prepare all unapproved elements
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-datalist'));
		$statement->execute();
		$components = $statement->fetchAll(PDO::FETCH_ASSOC);
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-datalist'));
		$statement->execute();
		$forms = $statement->fetchAll(PDO::FETCH_ASSOC);
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

	public function messageunnotified(){
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_unnotified'));
		$statement->execute([
			':user' => $_SESSION['user']['id']
		]);
		$unnotified = $statement->fetch(PDO::FETCH_ASSOC);
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_put_notified'));
		$statement->execute([
			':user' => $_SESSION['user']['id']
		]);
		return $unnotified['number'];
	}
	public function messageunseen(){
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('message_get_unseen'));
		$statement->execute([
			':user' => $_SESSION['user']['id']
		]);
		$unseen = $statement->fetch(PDO::FETCH_ASSOC);
		return $unseen['number'];
	}

	public function order(){
		$unprocessed = ['num' => 0];
		if (PERMISSION::permissionFor('orderprocessing')){
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get_approved_unprocessed'));
			$statement->execute([
			]);
			$unprocessed = $statement->fetch(PDO::FETCH_ASSOC);
		}
		return $unprocessed['num'];
	}

	public function records(){
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('records_identifiers'));
		$statement->execute();
		$data = $statement->fetchAll(PDO::FETCH_ASSOC);
		$number = 0;
		foreach ($data as $row){
			if ($row['units'] && $row['context'] == 'casedocumentation' && array_intersect(explode(',', $row['units']), $_SESSION['user']['units']) && !$row['closed']) $number++;
		}
		return $number;
	}
}
?>
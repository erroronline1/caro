<?php
// scheduling, contributing to calendar
require_once('calendarutility.php');

class CALENDAR extends API {

	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedTimespan = null;
	private $_requestedId = null;
	private $_requestedDate = null;
	private $_requestedComplete = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedTimespan = $this->_requestedId = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_requestedDate = $this->_requestedComplete = array_key_exists(3, REQUEST) ? REQUEST[3] : null;
	}

	private function events($dbevents, $calendar){
		$events = [];
		foreach($dbevents as $row){
			$date = new DateTime($row['date'], new DateTimeZone(INI['timezone']));
			$due = new DateTime($row['due'], new DateTimeZone(INI['timezone']));
			if (!array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units'])) continue;
			$display = LANG::GET('calendar.event_date') . ': ' . $date->format('Y-m-d') . "\n" .
				LANG::GET('calendar.event_due') . ': ' . $due->format('Y-m-d') . "\n";
			$display .= implode(', ', array_map(Fn($unit) => LANGUAGEFILE['units'][$unit], explode(',', $row['organizational_unit'])));

			$completed[LANG::GET('calendar.event_complete')] = ['onchange' => "api.calendar('put', 'complete', " . $row['id'] . ", this.checked)"];
			$completed_hint = '';
			if ($row['completed']) {
				$completed[LANG::GET('calendar.event_complete')]['checked'] = true;
				$row['completed'] = json_decode($row['completed'], true);
				$completed_hint = LANG::GET('calendar.event_completed_state', [':user' => $row['completed']['user'], ':date' => $row['completed']['date']]);
			}

			$events[] = [
				'type' => 'tile',
				'content' => [
					[
						'type' => 'text',
						'attributes' => [
							'data-type' => $row['alert'] ? 'alert' : 'text'
						],
						'description' => $row['content'],
						'content' => $display
					],
					[
						'type' => 'checkbox',
						'content' => $completed,
						'hint' => $completed_hint
					],
					
				]
			];
			if (array_intersect(['admin', 'ceo', 'qmo', 'supervisor'], $_SESSION['user']['permissions'])) {
				$events[count($events)-1]['content'][] = [
					'type' => 'button',
					'attributes' => [
						'type' => 'button',
						'value' => LANG::GET('calendar.event_edit'),
						'onpointerup' => $calendar->schedule($date->format('Y-m-d'), $row['type'], $row['content'], $due->format('Y-m-d'), $row['organizational_unit'], $row['alert'], $row['id'])
					],
					'hint' => LANG::GET('calendar.event_author') . ': ' . $row['author']
				];
				$events[count($events)-1]['content'][] = [
					'type' => 'deletebutton',
					'attributes' => [
						'value' => LANG::GET('calendar.event_delete'),
						'onpointerup' => "new Dialog({type:'confirm', header:'" . LANG::GET('calendar.event_delete') . " " . $row['content'] . "', options:{'" . LANG::GET('general.cancel_button') . "': false, '" . LANG::GET('calendar.event_delete') . "': {'value': true, class: 'reducedCTA'}}})" .
							".then(confirmation => {if (confirmation) api.calendar('delete', 'schedule', " . $row['id'] . ");});"
					]
				];
			}
		}
		return $events;
	}

	public function schedule(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		$calendar = new CALENDARUTILITY($this->_pdo);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$event = [
					':date' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_date')),
					':due' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_due')),
					':type' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_type')),
					':author' => $_SESSION['user']['name'],
					':organizational_unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_organizational_unit')),
					':content' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_content')),
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if (!($event[':date'] && $event[':organizational_unit'] && $event[':content'])) $this->response(['status' => ['msg' => LANG::GET('calendar.event_error_missing'), 'type' => 'error']]);
				if (!$event[':due']){
					$due = new DateTime($event[':date'], new DateTimeZone(INI['timezone']));
					$due->modify('+' . INI['calendar']['default_due'] . ' months');
					$event[':due'] = $due->format('Y-m-d');	
				}
				if ($newid = $calendar->post($event[':date'], $event[':due'], $event[':type'], $event[':author'], $event[':organizational_unit'], $event[':content'], $event[':alert'])) $this->response([
					'status' => [
						'id' => $newid,
						'msg' => LANG::GET('calendar.event_success'),
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'id' => false,
						'msg' => LANG::GET('calendar.event_error'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				$event = [
					':id' => UTILITY::propertySet($this->_payload, 'calendarEventId'),
					':date' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_date')),
					':due' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_due')),
					':organizational_unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_organizational_unit')),
					':content' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_content')),
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if (!($event[':date'] && $event[':organizational_unit'] && $event[':content'])) $this->response(['status' => ['msg' => LANG::GET('calendar.event_error_missing'), 'type' => 'error']]);
				if (!$event[':due']){
					$due = new DateTime($event[':date'], new DateTimeZone(INI['timezone']));
					$due->modify('+' . INI['calendar']['default_due'] . ' months');
					$event[':due'] = $due->format('Y-m-d');	
				}
				if ($calendar->put($event[':id'], $event[':date'], $event[':due'], $event[':organizational_unit'], $event[':content'], $event[':alert'])) $this->response([
					'status' => [
						'id' => $event[':id'],
						'msg' => LANG::GET('calendar.event_success'),
						'type' => 'success'
					]]);
				else {
					if ($newid = $calendar->post($event[':date'], $event[':due'], UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_type')), $_SESSION['user']['name'], $event[':organizational_unit'], $event[':content'], $event[':alert'])) $this->response([
						'status' => [
							'id' => $newid,
							'msg' => LANG::GET('calendar.event_success'),
							'type' => 'success'
						]]);
					else $this->response([
						'status' => [
							'id' => false,
							'msg' => LANG::GET('calendar.event_error'),
							'type' => 'error'
						]]);
					}
				break;
			case 'GET':
				$result = ['body' => ['content' => []]];
				$month = $calendar->render('month', $this->_requestedTimespan);
				$previousmonth = clone $calendar->_days[6]; // definetly a date and not a null filler
				$previousmonth->modify('-1 month');
				$nextmonth = clone $calendar->_days[6];
				$nextmonth->modify('+1 month');

				$result['body']['content'][] = [
					[
						'type' => 'scanner',
						'destination' => 'recordfilter',
						'description' => LANG::GET('assemble.scan_button')
					], [
						'type' => 'searchinput',
						'attributes' => [
							'id' => 'recordfilter',
							'name' => LANG::GET('calendar.event_search'),
							'onkeypress' => "if (event.key === 'Enter') {api.calendar('get', 'search', this.value); return false;}",
							'onblur' => "api.calendar('get', 'search', this.value); return false;",
							]
					]
				];
				$result['body']['content'][] = [
					[
						'type' => 'calendar',
						'description' => $month['header'],
						'content' => $month['content']
					],
					[
						'type' => 'button',
						'attributes' => [
							'value' => 'previous',
							'type' => 'button',
							'onpointerup' => "api.calendar('get', 'schedule', ' " . $previousmonth->format('Y-m-d') . "')"
						]
					],
					[
						'type' => 'button',
						'attributes' => [
							'value' => 'next',
							'type' => 'button',
							'onpointerup' => "api.calendar('get', 'schedule', ' " . $nextmonth->format('Y-m-d') . "')"
						]
					],
				];

				if ($this->_requestedDate){
					$events = [];

					$events[] = [
						'type' => 'text',
						'description' => $this->_requestedDate,
						'attributes' => [
							'id' => 'displayspecificdate',
							'data-type' => 'calendar'
						]
					];
					$events[] = [
						'type' => 'calendarbutton',
						'attributes' => [
							'value' => LANG::GET('calendar.event_new'),
							'onpointerup' => $calendar->schedule($this->_requestedDate, 'schedule')
						]
					];
					$dbevents = $calendar->getdate($this->_requestedDate);
					if ($dbevents) array_push($events, ...$this->events($dbevents, $calendar));
					$result['body']['content'][] = $events;

					$today = new DateTime($this->_requestedDate, new DateTimeZone(INI['timezone']));
					$today->modify('-1 day');
					$dbevents = $calendar->getdaterange(null, $today->format('Y-m-d'));
					if ($dbevents) {
						$events = [
							[
								'type' => 'text',
								'description' => LANG::GET('calendar.events_assigned_units_uncompleted'),
								'attributes' => [
									'data-type' => 'calendar'
								]
							]
						];
						$uncompleted = [];
						foreach ($dbevents as $id => $row){
							if (!array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) || $row['completed']) unset($dbevents[$id]);
						}
						array_push($events, ...$this->events($dbevents, $calendar));
						$result['body']['content'][] = $events;
					}
				}
				break;
			case 'DELETE':
				if ($calendar->delete($this->_requestedId)) $this->response([
					'status' => [
						'msg' => LANG::GET('calendar.event_deleted'),
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'msg' => LANG::GET('calendar.event_not_found'),
						'type' => 'error'
					]]);
			
				break;
		}
		$this->response($result);
	}
	
	public function search(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		if (!$this->_requestedId) $this->calendar();
		$result = ['body' => ['content' => [
			[
				[
					'type' => 'scanner',
					'destination' => 'recordfilter',
					'description' => LANG::GET('assemble.scan_button')
				], [
					'type' => 'searchinput',
					'attributes' => [
						'id' => 'recordfilter',
						'name' => LANG::GET('calendar.event_search'),
						'onkeypress' => "if (event.key === 'Enter') {api.calendar('get', 'search', this.value); return false;}",
						'onblur' => "api.calendar('get', 'search', this.value); return false;",
					]
				]
			]
		]]];
		$calendar = new CALENDARUTILITY($this->_pdo);
		$dbevents = $calendar->search($this->_requestedId);
		$events = [
			[
				'type' => 'text',
				'description' => LANG::GET ('calendar.events_none')
			]
		];
		if ($dbevents) $events = $this->events($dbevents, $calendar);
		$result['body']['content'][] = $events;
		$this->response($result);
	}

	public function complete(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		$calendar = new CALENDARUTILITY($this->_pdo);
		if ($calendar->complete($this->_requestedId, $this->_requestedComplete === 'true')) $this->response([
			'status' => [
				'msg' => $this->_requestedComplete === 'true' ? LANG::GET('calendar.event_completed') : LANG::GET('calendar.event_incompleted'),
				'type' => 'success'
			]]);
		else $this->response([
			'status' => [
				'msg' => LANG::GET('calendar.event_not_found'),
				'type' => 'error'
			]]);
	}

	public function alert(){
		$calendar = new CALENDARUTILITY($this->_pdo);
		$alerts = $calendar->alert(date('Y-m-d'));
		foreach($alerts as $event){
			$this->alertUserGroup(['unit' => explode(',', $event['organizational_unit'])], LANG::GET('calendar.event_alert_message', [':content' => $event['content'], ':date' => $event['date'], ':author' => $event['author'], ':due' => $event['due']]));
		}
	}

	public function notification (){
		if (!array_key_exists('user', $_SESSION)) $this->response(['status' => ['msg' => LANG::GET('menu.signin_header'), 'type' => 'info']], 401);
		$calendar = new CALENDARUTILITY($this->_pdo);
		$events = $calendar->getdaterange(null, date('Y-m-d'));
		$uncompleted = 0;
		foreach ($events as $row){
			if (array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) && !$row['completed']) $uncompleted++;
		}
		$this->response([
			'uncompletedevents' => $uncompleted
		]);
	}

	public function timesheet(){
		if (!array_key_exists('user', $_SESSION)) $this->response(['status' => ['msg' => LANG::GET('menu.signin_header'), 'type' => 'info']], 401);
		$calendar = new CALENDARUTILITY($this->_pdo);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$event = [
					':date' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_date')),
					':due' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_due')),
					':type' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_type')),
					':author' => $_SESSION['user']['name'],
					':organizational_unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_organizational_unit')),
					':content' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_content')),
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if (!($event[':date'] && $event[':organizational_unit'] && $event[':content'])) $this->response(['status' => ['msg' => LANG::GET('calendar.event_error_missing'), 'type' => 'error']]);
				if (!$event[':due']){
					$due = new DateTime($event[':date'], new DateTimeZone(INI['timezone']));
					$due->modify('+' . INI['calendar']['default_due'] . ' months');
					$event[':due'] = $due->format('Y-m-d');	
				}
				if ($newid = $calendar->post($event[':date'], $event[':due'], $event[':type'], $event[':author'], $event[':organizational_unit'], $event[':content'], $event[':alert'])) $this->response([
					'status' => [
						'id' => $newid,
						'msg' => LANG::GET('calendar.event_success'),
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'id' => false,
						'msg' => LANG::GET('calendar.event_error'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				$event = [
					':id' => UTILITY::propertySet($this->_payload, 'calendarEventId'),
					':date' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_date')),
					':due' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_due')),
					':organizational_unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_organizational_unit')),
					':content' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_content')),
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if (!($event[':date'] && $event[':organizational_unit'] && $event[':content'])) $this->response(['status' => ['msg' => LANG::GET('calendar.event_error_missing'), 'type' => 'error']]);
				if (!$event[':due']){
					$due = new DateTime($event[':date'], new DateTimeZone(INI['timezone']));
					$due->modify('+' . INI['calendar']['default_due'] . ' months');
					$event[':due'] = $due->format('Y-m-d');	
				}
				if ($calendar->put($event[':id'], $event[':date'], $event[':due'], $event[':organizational_unit'], $event[':content'], $event[':alert'])) $this->response([
					'status' => [
						'id' => $event[':id'],
						'msg' => LANG::GET('calendar.event_success'),
						'type' => 'success'
					]]);
				else {
					if ($newid = $calendar->post($event[':date'], $event[':due'], UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_type')), $_SESSION['user']['name'], $event[':organizational_unit'], $event[':content'], $event[':alert'])) $this->response([
						'status' => [
							'id' => $newid,
							'msg' => LANG::GET('calendar.event_success'),
							'type' => 'success'
						]]);
					else $this->response([
						'status' => [
							'id' => false,
							'msg' => LANG::GET('calendar.event_error'),
							'type' => 'error'
						]]);
					}
				break;
			case 'GET':
				$result = ['body' => ['content' => []]];
				$month = $calendar->render('month', $this->_requestedTimespan);
				$previousmonth = clone $calendar->_days[6]; // definetly a date and not a null filler
				$previousmonth->modify('-1 month');
				$nextmonth = clone $calendar->_days[6];
				$nextmonth->modify('+1 month');

				$result['body']['content'][] = [
					[
						'type' => 'calendar',
						'description' => $month['header'],
						'content' => $month['content']
					],
					[
						'type' => 'button',
						'attributes' => [
							'value' => 'previous',
							'type' => 'button',
							'onpointerup' => "api.calendar('get', 'timesheet', ' " . $previousmonth->format('Y-m-d') . "')"
						]
					],
					[
						'type' => 'button',
						'attributes' => [
							'value' => 'next',
							'type' => 'button',
							'onpointerup' => "api.calendar('get', 'timesheet', ' " . $nextmonth->format('Y-m-d') . "')"
						]
					],
				];

				if ($this->_requestedDate){
					$events = [];

					$events[] = [
						'type' => 'text',
						'description' => $this->_requestedDate,
						'attributes' => [
							'id' => 'displayspecificdate',
							'data-type' => 'calendar'
						]
					];
					$events[] = [
						'type' => 'calendarbutton',
						'attributes' => [
							'value' => LANG::GET('calendar.event_new'),
							'onpointerup' => $calendar->schedule($this->_requestedDate, 'schedule')
						]
					];
					$dbevents = $calendar->getdate($this->_requestedDate);
					if ($dbevents) array_push($events, ...$this->events($dbevents, $calendar));
					$result['body']['content'][] = $events;

					$today = new DateTime($this->_requestedDate, new DateTimeZone(INI['timezone']));
					$today->modify('-1 day');
					$dbevents = $calendar->getdaterange(null, $today->format('Y-m-d'));
					if ($dbevents) {
						$events = [
							[
								'type' => 'text',
								'description' => LANG::GET('calendar.events_assigned_units_uncompleted'),
								'attributes' => [
									'data-type' => 'calendar'
								]
							]
						];
						$uncompleted = [];
						foreach ($dbevents as $id => $row){
							if (!array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) || $row['completed']) unset($dbevents[$id]);
						}
						array_push($events, ...$this->events($dbevents, $calendar));
						$result['body']['content'][] = $events;
					}
				}
				break;
			case 'DELETE':
				if ($calendar->delete($this->_requestedId)) $this->response([
					'status' => [
						'msg' => LANG::GET('calendar.event_deleted'),
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'msg' => LANG::GET('calendar.event_not_found'),
						'type' => 'error'
					]]);
			
				break;
		}
		$this->response($result);

	}
}

$api = new CALENDAR();
$api->processApi();

exit;
?>
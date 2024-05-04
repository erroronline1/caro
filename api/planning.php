<?php
// planning, contributing to calendar
require_once('calendar.php');

class PLANNING extends API {

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
			if (!array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units'])) continue;
			$display = LANG::GET('planning.event_date') . ': ' . $row['date'] . "\n" .
				LANG::GET('planning.event_due') . ': ' . $row['due'] . "\n";
			$display .= implode(', ', array_map(Fn($unit) => LANGUAGEFILE['units'][$unit], explode(',', $row['organizational_unit'])));

			$completed[LANG::GET('planning.event_complete')] = ['onchange' => "api.planning('put', 'complete', " . $row['id'] . ", this.checked)"];
			$completed_hint = '';
			if ($row['completed']) {
				$completed[LANG::GET('planning.event_complete')]['checked'] = true;
				$row['completed'] = json_decode($row['completed'], true);
				$completed_hint = LANG::GET('planning.event_completed_state', [':user' => $row['completed']['user'], ':date' => $row['completed']['date']]);
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
						'value' => LANG::GET('planning.event_edit'),
						'onpointerup' => $calendar->dialog($row['date'], $row['type'], $row['content'], $row['due'], $row['organizational_unit'], $row['alert'], $row['id'])
					],
					'hint' => LANG::GET('planning.event_author') . ': ' . $row['author']
				];
				$events[count($events)-1]['content'][] = [
					'type' => 'deletebutton',
					'attributes' => [
						'value' => LANG::GET('planning.event_delete'),
						'onpointerup' => "new Dialog({type:'confirm', header:'" . LANG::GET('planning.event_delete') . " " . $row['content'] . "', options:{'" . LANG::GET('general.cancel_button') . "': false, '" . LANG::GET('planning.event_delete') . "': {'value': true, class: 'reducedCTA'}}})" .
							".then(confirmation => {if (confirmation) api.planning('delete', 'calendar', " . $row['id'] . ");});"
					]
				];
			}
		}
		return $events;
	}

	public function calendar(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		$calendar = new CALENDAR($this->_pdo);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$event = [
					':date' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('planning.event_date')),
					':due' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('planning.event_due')),
					':type' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('planning.event_type')),
					':author' => $_SESSION['user']['name'],
					':organizational_unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('planning.event_organizational_unit')),
					':content' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('planning.event_content')),
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('planning.event_alert')) ? 1 : 0
				];
				if (!($event[':date'] && $event[':organizational_unit'] && $event[':content'])) $this->response(['status' => ['msg' => LANG::GET('planning.event_error_missing'), 'type' => 'error']]);
				if (!$event[':due']){
					$due = new DateTime($event[':date'], new DateTimeZone(INI['timezone']));
					$due->modify('+' . INI['calendar']['default_due'] . ' months');
					$event[':due'] = $due->format('Y-m-d');	
				}
				if ($newid = $calendar->post($event[':date'], $event[':due'], $event[':type'], $event[':author'], $event[':organizational_unit'], $event[':content'], $event[':alert'])) $this->response([
					'status' => [
						'id' => $newid,
						'msg' => LANG::GET('planning.event_success'),
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'id' => false,
						'msg' => LANG::GET('planning.event_error'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				$event = [
					':id' => UTILITY::propertySet($this->_payload, 'calendarEventId'),
					':date' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('planning.event_date')),
					':due' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('planning.event_due')),
					':organizational_unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('planning.event_organizational_unit')),
					':content' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('planning.event_content')),
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('planning.event_alert')) ? 1 : 0
				];
				if (!($event[':date'] && $event[':organizational_unit'] && $event[':content'])) $this->response(['status' => ['msg' => LANG::GET('planning.event_error_missing'), 'type' => 'error']]);
				if (!$event[':due']){
					$due = new DateTime($event[':date'], new DateTimeZone(INI['timezone']));
					$due->modify('+' . INI['calendar']['default_due'] . ' months');
					$event[':due'] = $due->format('Y-m-d');	
				}
				if ($calendar->put($event[':id'], $event[':date'], $event[':due'], $event[':organizational_unit'], $event[':content'], $event[':alert'])) $this->response([
					'status' => [
						'id' => $event[':id'],
						'msg' => LANG::GET('planning.event_success'),
						'type' => 'success'
					]]);
				else {
					if ($newid = $calendar->post($event[':date'], $event[':due'], UTILITY::propertySet($this->_payload, LANG::PROPERTY('planning.event_type')), $_SESSION['user']['name'], $event[':organizational_unit'], $event[':content'], $event[':alert'])) $this->response([
						'status' => [
							'id' => $newid,
							'msg' => LANG::GET('planning.event_success'),
							'type' => 'success'
						]]);
					else $this->response([
						'status' => [
							'id' => false,
							'msg' => LANG::GET('planning.event_error'),
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
							'name' => LANG::GET('planning.event_search'),
							'onkeypress' => "if (event.key === 'Enter') {api.planning('get', 'search', this.value); return false;}",
							'onblur' => "api.planning('get', 'search', this.value); return false;",
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
							'onpointerup' => "api.planning('get', 'calendar', ' " . $previousmonth->format('Y-m-d') . "')"
						]
					],
					[
						'type' => 'button',
						'attributes' => [
							'value' => 'next',
							'type' => 'button',
							'onpointerup' => "api.planning('get', 'calendar', ' " . $nextmonth->format('Y-m-d') . "')"
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
							'value' => LANG::GET('planning.event_new'),
							'onpointerup' => $calendar->dialog($this->_requestedDate, 'planning')
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
								'description' => LANG::GET('planning.events_assigned_units_uncompleted'),
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
						'msg' => LANG::GET('planning.event_deleted'),
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'msg' => LANG::GET('planning.event_not_found'),
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
						'name' => LANG::GET('planning.event_search'),
						'onkeypress' => "if (event.key === 'Enter') {api.planning('get', 'search', this.value); return false;}",
						'onblur' => "api.planning('get', 'search', this.value); return false;",
					]
				]
			]
		]]];
		$calendar = new CALENDAR($this->_pdo);
		$dbevents = $calendar->search($this->_requestedId);
		$events = [
			[
				'type' => 'text',
				'description' => LANG::GET ('planning.events_none')
			]
		];
		if ($dbevents) $events = $this->events($dbevents, $calendar);
		$result['body']['content'][] = $events;
		$this->response($result);
	}

	public function complete(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		$calendar = new CALENDAR($this->_pdo);
		if ($calendar->complete($this->_requestedId, $this->_requestedComplete === 'true')) $this->response([
			'status' => [
				'msg' => $this->_requestedComplete === 'true' ? LANG::GET('planning.event_completed') : LANG::GET('planning.event_incompleted'),
				'type' => 'success'
			]]);
		else $this->response([
			'status' => [
				'msg' => LANG::GET('planning.event_not_found'),
				'type' => 'error'
			]]);
	}

	public function alert(){
		$calendar = new CALENDAR($this->_pdo);
		$alerts = $calendar->alert(date('Y-m-d'));
		foreach($alerts as $event){
			$this->alertUserGroup(['unit' => explode(',', $event['organizational_unit'])], LANG::GET('planning.event_alert_message', [':content' => $event['content'], ':date' => $event['date'], ':author' => $event['author'], ':due' => $event['due']]));
		}
	}

	public function notification (){
		if (!array_key_exists('user', $_SESSION)) $this->response(['status' => ['msg' => LANG::GET('menu.signin_header'), 'type' => 'info']], 401);
		$calendar = new CALENDAR($this->_pdo);
		$events = $calendar->getdaterange(null, date('Y-m-d'));
		$uncompleted = 0;
		foreach ($events as $row){
			if (array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) && !$row['completed']) $uncompleted++;
		}
		$this->response([
			'uncompletedevents' => $uncompleted
		]);
	}
}

$api = new PLANNING();
$api->processApi();

exit;
?>
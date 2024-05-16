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

	/**
	 * renders scheduled events as tiles
	 * @param array $dbevents db query results
	 * @param object $calendar inherited CALENDARUTILITY-object
	 * 
	 * @return array render options for assemble.js 
 	*/
	private function scheduledEvents($dbevents, $calendar){
		$events = [];
		foreach($dbevents as $row){
			$date = new DateTime($row['span_start'], new DateTimeZone(INI['timezone']));
			$due = new DateTime($row['span_end'], new DateTimeZone(INI['timezone']));
			if (!array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) || $row['type'] !== 'schedule' ) continue;
			$display = LANG::GET('calendar.event_date') . ': ' . $date->format('Y-m-d') . "\n" .
				LANG::GET('calendar.event_due') . ': ' . $due->format('Y-m-d') . "\n";
			$display .= implode(', ', array_map(Fn($unit) => LANGUAGEFILE['units'][$unit], explode(',', $row['organizational_unit'])));

			$completed[LANG::GET('calendar.event_complete')] = ['onchange' => "api.calendar('put', 'complete', " . $row['id'] . ", this.checked)"];
			$completed_hint = '';
			if ($row['closed']) {
				$completed[LANG::GET('calendar.event_complete')]['checked'] = true;
				$row['closed'] = json_decode($row['closed'], true);
				$completed_hint = LANG::GET('calendar.event_completed_state', [':user' => $row['closed']['user'], ':date' => $row['closed']['date']]);
			}

			$events[] = [
				'type' => 'tile',
				'content' => [
					[
						'type' => 'text',
						'attributes' => [
							'data-type' => $row['alert'] ? 'alert' : 'text'
						],
						'description' => $row['subject'],
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
				$columns = [
					':id' => $row['id'],
					':type' => 'schedule',
					':span_start' => $date->format('Y-m-d'),
					':span_end' => $due->format('Y-m-d'),
					':author_id' => $row['author_id'],
					':affected_user_id' => $row['affected_user_id'],
					':organizational_unit' => $row['organizational_unit'],
					':subject' => $row['subject'],
					':misc' => '',
					':closed' => '',
					':alert' => $row['alert']
				];			   
				$events[count($events)-1]['content'][] = [
					'type' => 'button',
					'attributes' => [
						'type' => 'button',
						'value' => LANG::GET('calendar.event_edit'),
						'onpointerup' => $calendar->dialog($columns)
					],
					'hint' => LANG::GET('calendar.event_author') . ': ' . $row['author']
				];
				$events[count($events)-1]['content'][] = [
					'type' => 'deletebutton',
					'attributes' => [
						'value' => LANG::GET('calendar.event_delete'),
						'onpointerup' => "new Dialog({type:'confirm', header:'" . LANG::GET('calendar.event_delete') . " " . $row['subject'] . "', options:{'" . LANG::GET('general.cancel_button') . "': false, '" . LANG::GET('calendar.event_delete') . "': {'value': true, class: 'reducedCTA'}}})" .
							".then(confirmation => {if (confirmation) api.calendar('delete', 'schedule', " . $row['id'] . ");});"
					]
				];
			}
		}
		return $events;
	}

	/**
	 * handle scheduled events
	 * post adds event to calendar
	 * put updates event data
	 * get displays calendar
	 * 		using $this->_requestedTimespan for selection of month
	 * 			  $this->_requestedDate for events for selected date
	 * delete removes scheduled event
	 * 
	 * responds with render data for assemble.js
	 */
	public function schedule(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		$calendar = new CALENDARUTILITY($this->_pdo);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$event = [
					':type' => 'schedule',
					':span_start' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_date')),
					':span_end' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_due')),
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => 0,
					':organizational_unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_organizational_unit')),
					':subject' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_content')),
					':misc' => '',
					':closed' => '',
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if (!($event[':span_start'] && $event[':organizational_unit'] && $event[':subject'])) $this->response(['status' => ['msg' => LANG::GET('calendar.event_error_missing'), 'type' => 'error']]);
				if (!$event[':span_end']){
					$due = new DateTime($event[':span_start'], new DateTimeZone(INI['timezone']));
					$due->modify('+' . INI['calendar']['default_due'] . ' months');
					$event[':span_end'] = $due->format('Y-m-d');	
				}
				if ($newid = $calendar->post($event)) $this->response([
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
					':span_start' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_date')),
					':span_end' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_due')),
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => 0,
					':organizational_unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_organizational_unit')),
					':subject' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_content')),
					':misc' => '',
					':closed' => '',
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if (!($event[':span_start'] && $event[':organizational_unit'] && $event[':subject'])) $this->response(['status' => ['msg' => LANG::GET('calendar.event_error_missing'), 'type' => 'error']]);
				if (!$event[':span_end']){
					$due = new DateTime($event[':span_start'], new DateTimeZone(INI['timezone']));
					$due->modify('+' . INI['calendar']['default_due'] . ' months');
					$event[':span_end'] = $due->format('Y-m-d');	
				}
				if ($calendar->put($event)) $this->response([
					'status' => [
						'id' => $event[':id'],
						'msg' => LANG::GET('calendar.event_success'),
						'type' => 'success'
					]]);
				else {
					unset($calendar[':id']);
					$calendar[':type'] = 'schedule';
					if ($newid = $calendar->post($event)) $this->response([
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
				$month = $calendar->render('month', 'schedule', $this->_requestedTimespan);
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
						'content' => $month['content'],
						'api' => 'schedule'
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
					$columns = [
						':type' => 'schedule',
						':span_start' => $this->_requestedDate,
					];
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
							'onpointerup' => $calendar->dialog($columns)
						]
					];
					$dbevents = $calendar->getDay($this->_requestedDate);
					if ($dbevents) array_push($events, ...$this->scheduledEvents($dbevents, $calendar));
					$result['body']['content'][] = $events;

					$today = new DateTime($this->_requestedDate, new DateTimeZone(INI['timezone']));
					$today->modify('-1 day');
					$dbevents = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
					if ($dbevents) {
						$uncompleted = [];
						foreach ($dbevents as $id => $row){
							if (!array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) || $row['closed']) unset($dbevents[$id]);
						}
						if ($dbevents){
							$events = [
								[
									'type' => 'text',
									'description' => LANG::GET('calendar.events_assigned_units_previous_uncompleted'),
									'attributes' => [
										'data-type' => 'calendar'
									]
								]
							];
							array_push($events, ...$this->scheduledEvents($dbevents, $calendar));
							$result['body']['content'][] = $events;	
						}
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
	
	/**
	 * search scheduled events by $this->_requestedId (search string)
	 * 
	 * reroutes to calendar method without search string
	 * responds with events or empty message
	 */
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
		if ($dbevents) $events = $this->scheduledEvents($dbevents, $calendar);
		$result['body']['content'][] = $events;
		$this->response($result);
	}

	/**
	 * updates scheduled events in terms of completion
	 */
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

	/**
	 * alerts a user group if selected
	 * used by service worker
	 */
	public function alert(){
		$calendar = new CALENDARUTILITY($this->_pdo);
		$alerts = $calendar->alert(date('Y-m-d'));
		foreach($alerts as $event){
			$this->alertUserGroup(['unit' => explode(',', $event['organizational_unit'])], LANG::GET('calendar.event_alert_message', [':content' => $event['content'], ':date' => $event['date'], ':author' => $event['author'], ':due' => $event['due']]));
		}
	}

	/**
	 * checks for uncompleted scheduled tasks and does respond with number
	 * used by service worker
	 */
	public function notification (){
		if (!array_key_exists('user', $_SESSION)) $this->response(['status' => ['msg' => LANG::GET('menu.signin_header'), 'type' => 'info']], 401);
		$calendar = new CALENDARUTILITY($this->_pdo);
		$events = $calendar->getWithinDateRange(null, date('Y-m-d'));
		$uncompleted = 0;
		foreach ($events as $row){
			if (array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) && !$row['paused']) $uncompleted++;
		}
		$this->response([
			'uncompletedevents' => $uncompleted
		]);
	}

	/**
	 * renders timesheet entries as tiles
	 * @param array $dbevents db query results
	 * @param object $calendar inherited CALENDARUTILITY-object
	 * 
	 * @return array render options for assemble.js 
 	*/
	 private function timesheetEntries($dbevents, $calendar){
		$events = [];
		foreach($dbevents as $row){
			$date = new DateTime($row['span_start'], new DateTimeZone(INI['timezone']));
			$due = new DateTime($row['span_end'], new DateTimeZone(INI['timezone']));
			if ($row['type'] !== 'timesheet' || !(
				$row['affected_user_id'] === $_SESSION['user']['id'] ||
				array_intersect(['humanressources', 'admin', 'ceo'], $_SESSION['user']['permissions']) ||
				(array_intersect(['supervisor'], $_SESSION['user']['permissions']) && array_intersect(explode(',', $row['organization_unit']), $_SESSION['user']['units']))
			)) continue;

			$hint = '';
			$display = '';
			if ($row['subject']) $display .= LANG::GET('calendar.timesheet_irregular') . ': ' . $row['subject'] . "\n";
			$display .=	LANG::GET('calendar.timesheet_start') . ': ' . $date->format('Y-m-d H:i') . "\n" .
				LANG::GET('calendar.timesheet_end') . ': ' . $due->format('Y-m-d H:i') . "\n";

			if ($row['misc']){
				$misc = json_decode($row['misc'], true);
				if (array_key_exists('break', $misc)) $display .= LANG::GET('calendar.timesheet_break') . ': ' . $misc['break'] . "\n";
				if (array_key_exists('homeoffice', $misc)) $display .= LANG::GET('calendar.timesheet_homeoffice') . ': ' . $misc['homeoffice'] . "\n";
				if ($row['author_id'] != $row['affected_user_id']) {
					$hint = LANG::GET('calendar.timesheet_foreign_contributor') . ': ' . $row['author'] . "\n";
				}
			}

			$events[] = [
				'type' => 'tile',
				'content' => [
					[
						'type' => 'text',
						'attributes' => [
							'data-type' => $row['alert'] ? 'alert' : 'text'
						],
						'description' => $row['affected_user'],
						'content' => $display,
						'hint' => $hint ? : null
					],					
				]
			];
			if (array_intersect(['admin'], $_SESSION['user']['permissions'])) {
				$columns = [
					':id' => $row['id'],
					':type' => 'timesheet',
					':span_start' => $date->format('Y-m-d'),
					':span_end' => $due->format('Y-m-d'),
					':author_id' => $row['author_id'],
					':affected_user_id' => $row['affected_user_id'],
					':organizational_unit' => $row['organizational_unit'],
					':subject' => $row['subject'],
					':misc' => $row['misc'],
					':closed' => '',
					':alert' => $row['alert']
				];
				$events[count($events)-1]['content'][] = [
					'type' => 'button',
					'attributes' => [
						'type' => 'button',
						'value' => LANG::GET('calendar.event_edit'),
						'onpointerup' => $calendar->dialog($columns)
					]
				];
				$events[count($events)-1]['content'][] = [
					'type' => 'deletebutton',
					'attributes' => [
						'value' => LANG::GET('calendar.event_delete'),
						'onpointerup' => "new Dialog({type:'confirm', header:'" . LANG::GET('calendar.event_delete') . "', options:{'" . LANG::GET('general.cancel_button') . "': false, '" . LANG::GET('calendar.event_delete') . "': {'value': true, class: 'reducedCTA'}}})" .
							".then(confirmation => {if (confirmation) api.calendar('delete', 'schedule', " . $row['id'] . ");});"
					]
				];
			}
		}
		return $events;
	}

	/**
	 * handle timesheet entries
	 * post adds entry to calendar
	 * put updates entry data
	 * get displays calendar
	 * 		using $this->_requestedTimespan for selection of month
	 * 			  $this->_requestedDate for entry for selected date
	 * delete removes entry
	 * 
	 * responds with render data for assemble.js
	 */
	public function timesheet(){
		if (!array_key_exists('user', $_SESSION)) $this->response(['status' => ['msg' => LANG::GET('menu.signin_header'), 'type' => 'info']], 401);
		$calendar = new CALENDARUTILITY($this->_pdo);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$event = [
					':type' => 'timesheet',
					':span_start' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_start_date')) . ' ' . UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_start_time')) . ':00',
					':span_end' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_end_date')) . ' ' . UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_end_time')) . ':00',
//					':author_id' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_foreign_contributor')) ? : $_SESSION['user']['id'],
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $_SESSION['user']['id'],
					':organizational_unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_organizational_unit')),
					':subject' => '',
					':misc' => '',
					':closed' => '',
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				$misc = [
					'weeklyhours' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_weekly_hours')),
					'note' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_pto_note')) ? : '',
					'break' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_break_time'))
				];
				if ($homeoffice = UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_homeoffice'))){
					$misc['homeoffice'] = $homeoffice;
				}
				$pto = UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_pto_exemption'));
				if ($pto !== LANG::GET('calendar.timesheet_pto.regular')){
					$event[':subject'] = $pto;
				}
				$event[':misc'] = json_encode($misc);
				if (!($event[':span_start'] && $event[':span_end'] && $misc['break'])) $this->response(['status' => ['msg' => LANG::GET('calendar.timesheet_error_missing'), 'type' => 'error']]);
				if ($newid = $calendar->post($event)) $this->response([
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
					':span_start' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_start_date')) . ' ' . UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_start_time')) . ':00',
					':span_end' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_end_date')) . ' ' . UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_end_time')) . ':00',
//					':author_id' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_foreign_contributor')) ? : $_SESSION['user']['id'],
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $_SESSION['user']['id'],
					':organizational_unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_organizational_unit')),
					':subject' => '',
					':misc' => '',
					':closed' => '',
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				$misc = [
					'weeklyhours' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_weekly_hours')),
					'note' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_pto_note')) ? : '',
					'break' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_break_time'))
				];
				if ($homeoffice = UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_homeoffice'))){
					$misc['homeoffice'] = $homeoffice;
				}
				$pto = UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_pto_exemption'));
				if ($pto !== LANG::GET('calendar.timesheet_pto.regular')){
					$event[':subject'] = $pto;
				}
				$event[':misc'] = json_encode($misc);
				if (!($event[':span_start'] && $event[':span_end'] && $misc['break'])) $this->response(['status' => ['msg' => LANG::GET('calendar.timesheet_error_missing'), 'type' => 'error']]);
				if ($calendar->put($event)) $this->response([
					'status' => [
						'id' => $event[':id'],
						'msg' => LANG::GET('calendar.event_success'),
						'type' => 'success'
					]]);
				else {
					unset($calendar[':id']);
					$calendar[':type'] = 'timesheet';
					if ($newid = $calendar->post($event)) $this->response([
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
				$month = $calendar->render('month', 'timesheet', $this->_requestedTimespan);
				$previousmonth = clone $calendar->_days[6]; // definetly a date and not a null filler
				$previousmonth->modify('-1 month');
				$nextmonth = clone $calendar->_days[6];
				$nextmonth->modify('+1 month');

				$result['body']['content'][] = [
					[
						'type' => 'calendar',
						'description' => $month['header'],
						'content' => $month['content'],
						'api' => 'timesheet'
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
					$columns = [
						':type' => 'timesheet',
						':span_start' => $this->_requestedDate,
						':organizational_unit' => implode(',', $_SESSION['user']['units'])
					];
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
							'value' => LANG::GET('calendar.timesheet_new'),
							'onpointerup' => $calendar->dialog($columns)
						]
					];
					$dbevents = $calendar->getDay($this->_requestedDate);
					if ($dbevents) array_push($events, ...$this->timesheetEntries($dbevents, $calendar));
					$result['body']['content'][] = $events;

					$today = new DateTime($this->_requestedDate, new DateTimeZone(INI['timezone']));
					$dbevents = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
					if ($dbevents) {
						$uncompleted = [];
						foreach ($dbevents as $id => $row){
							if (!array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) || $row['closed']) unset($dbevents[$id]);
						}
						if ($dbevents){
							$events = [
								[
									'type' => 'text',
									'description' => LANG::GET('calendar.events_assigned_units_uncompleted'),
									'attributes' => [
										'data-type' => 'calendar'
									]
								]
							];
							array_push($events, ...$this->scheduledEvents($dbevents, $calendar));
							$result['body']['content'][] = $events;	
						}
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
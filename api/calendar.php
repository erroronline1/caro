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

// scheduling, contributing to calendar
require_once('./_calendarutility.php');
require_once('./_pdf.php');

class CALENDAR extends API {

	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedTimespan = null;
	private $_requestedId = null;
	private $_requestedDate = null;
	private $_requestedComplete = null;
	private $_requestedCalendarType = null;

	public function __construct(){
		parent::__construct();
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);

		$this->_requestedTimespan = $this->_requestedId = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_requestedDate = $this->_requestedComplete = array_key_exists(3, REQUEST) ? REQUEST[3] : null;
		$this->_requestedCalendarType = array_key_exists(4, REQUEST) ? REQUEST[4] : null;
	}

	/**
	 * search scheduled events by $this->_requestedId (search string)
	 * 
	 * reroutes to calendar method without search string
	 * responds with events or empty message
	 */
	public function search(){
		if (!$this->_requestedId) $this->schedule();
		$result = ['render' => ['content' => [
			[
				[
					'type' => 'scanner',
					'destination' => 'recordfilter',
					'description' => LANG::GET('assemble.scan_button')
				], [
					'type' => 'search',
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
				'type' => 'textblock',
				'description' => LANG::GET ('calendar.events_none')
			]
		];
		if ($dbevents) $events = $this->scheduledEvents($dbevents, $calendar);
		$result['render']['content'][] = $events;
		$this->response($result);
	}

	/**
	 * updates scheduled events in terms of completion
	 * $this->_requestedId string with eventually comma separated integers
	 */
	public function complete(){
		if ($this->_requestedCalendarType === 'timesheet'
			&& !(PERMISSION::permissionFor('calendarfullaccess')
			|| (array_intersect(['supervisor'], $_SESSION['user']['permissions']) 
			&& array_intersect(explode(',', $row['organization_unit']), $_SESSION['user']['units'])))) $this->response([], 401);
		$response = [
			'schedule' => [
				0 => LANG::GET('calendar.event_incompleted'),
				1 => LANG::GET('calendar.event_completed')
			],
			'timesheet' => [
				0 => LANG::GET('calendar.timesheet_disapproved'),
				1 => LANG::GET('calendar.timesheet_approved')
			],
		];
		$alert = null;
		if ($this->_requestedCalendarType === 'schedule') $alert = intval($response[$this->_requestedCalendarType][intval($this->_requestedComplete === 'true')]);

		$calendar = new CALENDARUTILITY($this->_pdo);
		if ($calendar->complete($this->_requestedId, $this->_requestedComplete === 'true', $alert)) $this->response([
			'response' => [
				'msg' => $response[$this->_requestedCalendarType][intval($this->_requestedComplete === 'true')],
				'type' => 'success'
			]]);
		else $this->response([
			'response' => [
				'msg' => LANG::GET('calendar.event_not_found'),
				'type' => 'error'
			]]);
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

			// replace deleted user names
			if (!$row['author']) $row['author'] = LANG::GET('message.deleted_user');
			if (!$row['affected_user']) $row['affected_user'] = LANG::GET('message.deleted_user');

			$completed[LANG::GET('calendar.event_complete')] = ['onchange' => "api.calendar('put', 'complete', '" . $row['id'] . "', this.checked, 'schedule')"];
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
						'type' => 'textblock',
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
			if (PERMISSION::permissionFor('calendaredit')) {
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
		$calendar = new CALENDARUTILITY($this->_pdo);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$event = [
					':type' => 'schedule',
					':span_start' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_date')),
					':span_end' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_due')),
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $_SESSION['user']['id'],
					':organizational_unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_organizational_unit')),
					':subject' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_content')),
					':misc' => '',
					':closed' => '',
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if (!($event[':span_start'] && $event[':organizational_unit'] && $event[':subject'])) $this->response(['response' => ['msg' => LANG::GET('calendar.event_error_missing'), 'type' => 'error']]);
				if (!$event[':span_end']){
					$due = new DateTime($event[':span_start'], new DateTimeZone(INI['timezone']));
					$due->modify('+' . INI['calendar']['default_due'] . ' months');
					$event[':span_end'] = $due->format('Y-m-d');	
				}
				if ($newid = $calendar->post($event)) $this->response([
					'response' => [
						'id' => $newid,
						'msg' => LANG::GET('calendar.event_success'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => false,
						'msg' => LANG::GET('calendar.event_error'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				if (!PERMISSION::permissionFor('calendaredit')) $this->response([], 401);
				$event = [
					':id' => UTILITY::propertySet($this->_payload, 'calendarEventId'),
					':span_start' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_date')),
					':span_end' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_due')),
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $_SESSION['user']['id'],
					':organizational_unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_organizational_unit')),
					':subject' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_content')),
					':misc' => '',
					':closed' => '',
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if (!($event[':span_start'] && $event[':organizational_unit'] && $event[':subject'])) $this->response(['response' => ['msg' => LANG::GET('calendar.event_error_missing'), 'type' => 'error']]);
				if (!$event[':span_end']){
					$due = new DateTime($event[':span_start'], new DateTimeZone(INI['timezone']));
					$due->modify('+' . INI['calendar']['default_due'] . ' months');
					$event[':span_end'] = $due->format('Y-m-d');	
				}
				if ($calendar->put($event)) $this->response([
					'response' => [
						'id' => $event[':id'],
						'msg' => LANG::GET('calendar.event_success'),
						'type' => 'success'
					]]);
				else {
					// without changed values (e.g. on aborting) affected rows returns 0
					// to avoid duplicate entries delete and reinsert
					$calendar->delete($event[':id']);
					unset($event[':id']);
					$event[':type'] = 'schedule';
					if ($newid = $calendar->post($event)) $this->response([
						'response' => [
							'id' => $newid,
							'msg' => LANG::GET('calendar.event_success'),
							'type' => 'success'
						]]);
					else $this->response([
						'response' => [
							'id' => false,
							'msg' => LANG::GET('calendar.event_error'),
							'type' => 'error'
						]]);
					}
				break;
			case 'GET':
				$result = ['render' => ['content' => []]];
				$month = $calendar->render('month', 'schedule', $this->_requestedTimespan);
				$previousmonth = clone $calendar->_days[6]; // definetly a date and not a null filler
				$previousmonth->modify('-1 month');
				$nextmonth = clone $calendar->_days[6];
				$nextmonth->modify('+1 month');

				$result['render']['content'][] = [
					[
						'type' => 'scanner',
						'destination' => 'recordfilter',
						'description' => LANG::GET('assemble.scan_button')
					], [
						'type' => 'search',
						'attributes' => [
							'id' => 'recordfilter',
							'name' => LANG::GET('calendar.event_search'),
							'onkeypress' => "if (event.key === 'Enter') {api.calendar('get', 'search', this.value); return false;}",
							'onblur' => "api.calendar('get', 'search', this.value); return false;",
							]
					]
				];
				$result['render']['content'][] = [
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
					$displayabsentmates = '';

					$thisDaysEvents = $calendar->getDay($this->_requestedDate);
					foreach ($thisDaysEvents as $id => $row){
						if (!$row['affected_user']) $row['affected_user'] = LANG::GET('message.deleted_user');
						if ($row['type'] === 'timesheet' && !in_array($row['subject'], INI['calendar']['hide_offduty_reasons']) && array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])) $displayabsentmates .= "* " . $row['affected_user'] . " ". LANGUAGEFILE['calendar']['timesheet_pto'][$row['subject']] . " ". substr($row['span_start'], 0, 10) . " - ". substr($row['span_end'], 0, 10) . "\n";
					}

					$events[] = [
						'type' => 'textblock',
						'description' => $this->_requestedDate,
						'content' => $displayabsentmates,
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

					if ($thisDaysEvents) array_push($events, ...$this->scheduledEvents($thisDaysEvents, $calendar));
					$result['render']['content'][] = $events;

					$today = new DateTime($this->_requestedDate, new DateTimeZone(INI['timezone']));
					$pastEvents = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
					if ($pastEvents) {
						$uncompleted = [];
						foreach ($pastEvents as $id => $row){
							if (in_array($row, $thisDaysEvents) || $row['type'] !== 'schedule' || !array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) || $row['closed']) unset($pastEvents[$id]);
						}
						if ($pastEvents){
							$events = [
								[
									'type' => 'textblock',
									'description' => LANG::GET('calendar.events_assigned_units_uncompleted'),
									'attributes' => [
										'data-type' => 'calendar'
									]
								]
							];
							array_push($events, ...$this->scheduledEvents($pastEvents, $calendar));
							$result['render']['content'][] = $events;	
						}
					}
				}
				break;
			case 'DELETE':
				if (!PERMISSION::permissionFor('calendaredit')) $this->response([], 401);
				if ($calendar->delete($this->_requestedId)) $this->response([
					'response' => [
						'msg' => LANG::GET('calendar.event_deleted'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => LANG::GET('calendar.event_not_found'),
						'type' => 'error'
					]]);
			
				break;
		}
		$this->response($result);
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
			if ($row['type'] !== 'timesheet'
				|| !($row['affected_user_id'] === $_SESSION['user']['id']
				|| PERMISSION::permissionFor('calendarfulltimesheetexport')
				|| (array_intersect(['supervisor'], $_SESSION['user']['permissions'])
				&& array_intersect(explode(',', $row['organization_unit']), $_SESSION['user']['units']))
			)) continue;

			// replace deleted user names
			if (!$row['author']) $row['author'] = LANG::GET('message.deleted_user');
			if (!$row['affected_user']) $row['affected_user'] = LANG::GET('message.deleted_user');

			$hint = '';
			$display = '';
			if ($row['subject']) $display .= LANG::GET('calendar.timesheet_irregular') . ': ' . LANGUAGEFILE['calendar']['timesheet_pto'][$row['subject']] . "\n";
			$display .=	LANG::GET('calendar.timesheet_start') . ': ' . $date->format($row['subject'] ? 'Y-m-d' : 'Y-m-d H:i') . "\n" .
				LANG::GET('calendar.timesheet_end') . ': ' . $due->format($row['subject'] ? 'Y-m-d' : 'Y-m-d H:i') . "\n";

			if ($row['misc']){
				$misc = json_decode($row['misc'], true);
				if (!$row['subject'] && array_key_exists('break', $misc)) $display .= LANG::GET('calendar.timesheet_break') . ': ' . $misc['break'] . "\n";
				if (!$row['subject'] && array_key_exists('homeoffice', $misc)) $display .= LANG::GET('calendar.timesheet_homeoffice') . ': ' . $misc['homeoffice'] . "\n";
				if ($row['author_id'] != $row['affected_user_id']) {
					$hint = LANG::GET('calendar.timesheet_foreign_contributor') . ': ' . $row['author'] . "\n";
				}
			}

			$events[] = [
				'type' => 'tile',
				'content' => [
					[
						'type' => 'textblock',
						'attributes' => [
							'data-type' => $row['alert'] ? 'alert' : 'text'
						],
						'description' => $row['affected_user'],
						'content' => $display,
						'hint' => $hint ? : null
					],					
				]
			];
			/**
			 * approval can only be set by
			 * admin, ceo and supervisor of assigned unit
			 */
			$completed[LANG::GET('calendar.timesheet_approve')] = ['onchange' => "api.calendar('put', 'complete', '" . $row['id'] . "', this.checked, 'timesheet')"];
			if (!(PERMISSION::permissionFor('calendarfullaccess')
				|| (array_intersect(['supervisor'], $_SESSION['user']['permissions']) 
				&& array_intersect(explode(',', $row['organization_unit']), $_SESSION['user']['units']))))
				$completed[LANG::GET('calendar.timesheet_approve')]['disabled'] = true;
			$completed_hint = '';
			if ($row['closed']) {
				$completed[LANG::GET('calendar.timesheet_approve')]['checked'] = true;
				$row['closed'] = json_decode($row['closed'], true);
				$completed_hint = LANG::GET('calendar.timesheet_approved_state', [':user' => $row['closed']['user'], ':date' => $row['closed']['date']]);
			}
			$events[count($events)-1]['content'][] = [
				'type' => 'checkbox',
				'content' => $completed,
				'hint' => $completed_hint,
			];

			/**
			 * editing and deleting is only allowed for admin and owning user on unapproved entries
			 */
			if (array_intersect(['admin'], $_SESSION['user']['permissions']) || 
				($row['affected_user_id'] === $_SESSION['user']['id'] && !$row['closed'])
			) {
				$columns = [
					':id' => $row['id'],
					':type' => 'timesheet',
					':span_start' => $date->format('Y-m-d H:i'),
					':span_end' => $due->format('Y-m-d H:i'),
					':author_id' => $row['author_id'],
					':affected_user_id' => $row['affected_user_id'],
					':organizational_unit' => '',
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
		$calendar = new CALENDARUTILITY($this->_pdo);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$affected_user_id = UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_affected_user')) ? : $_SESSION['user']['id'];
				if ($affected_user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => $affected_user_id
					]
				])) $affected_user = $affected_user[0];
		
				$event = [
					':type' => 'timesheet',
					':span_start' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_start_date')) . ' ' . UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_start_time')) . ':00',
					':span_end' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_end_date')) . ' ' . UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_end_time')) . ':00',
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $affected_user['id'],
					':organizational_unit' => '',
					':subject' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_pto_exemption')) ? : '',
					':misc' => '',
					':closed' => '',
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if ($event[':subject'] === 'regular') $event[':subject'] = '';
				$misc = $event[':subject'] ? [] : [
					'note' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_pto_note')) ? : '',
					'break' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_break_time'))
				];
				if ($homeoffice = UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_homeoffice'))){
					$misc['homeoffice'] = $homeoffice;
				}
				$event[':misc'] = json_encode($misc);
				if (!($event[':span_start'] && $event[':span_end'] && ((array_key_exists('break', $misc) && $misc['break']) || $event[':subject']))) $this->response(['response' => ['msg' => LANG::GET('calendar.timesheet_error_missing'), 'type' => 'error']]);
				if ($newid = $calendar->post($event)) $this->response([
					'response' => [
						'id' => $newid,
						'msg' => LANG::GET('calendar.event_success'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => false,
						'msg' => LANG::GET('calendar.event_error'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']) || 
				($row['affected_user_id'] === $_SESSION['user']['id'] && !$row['closed']))
				) $this->response([], 401);

				$affected_user_id = UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_affected_user')) ? : $_SESSION['user']['id'];
				if ($affected_user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => $affected_user_id
					]
				])) $affected_user = $affected_user[0];
		
				$event = [
					':id' => UTILITY::propertySet($this->_payload, 'calendarEventId'),
					':span_start' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_start_date')) . ' ' . UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_start_time')) . ':00',
					':span_end' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_end_date')) . ' ' . UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_end_time')) . ':00',
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $affected_user['id'],
					':organizational_unit' => '',
					':subject' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_pto_exemption')) ? : '',
					':misc' => '',
					':closed' => '',
					':alert' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if ($event[':subject'] === 'regular') $event[':subject'] = '';
				$misc = $event[':subject'] ? [] : [
					'note' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_pto_note')) ? : '',
					'break' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_break_time'))
				];
				if ($homeoffice = UTILITY::propertySet($this->_payload, LANG::PROPERTY('calendar.timesheet_homeoffice'))){
					$misc['homeoffice'] = $homeoffice;
				}

				$event[':misc'] = json_encode($misc);
				if (!($event[':span_start'] && $event[':span_end'] && ((array_key_exists('break', $misc) && $misc['break']) || $event[':subject']))) $this->response(['response' => ['msg' => LANG::GET('calendar.timesheet_error_missing'), 'type' => 'error']]);
				if ($calendar->put($event)) $this->response([
					'response' => [
						'id' => $event[':id'],
						'msg' => LANG::GET('calendar.event_success'),
						'type' => 'success'
					]]);
				else {
					// without changed values (e.g. on aborting) affected rows returns 0
					// to avoid duplicate entries delete and reinsert
					$calendar->delete($event[':id']);
					unset($event[':id']);
					$event[':type'] = 'timesheet';
					if ($newid = $calendar->post($event)) $this->response([
						'response' => [
							'id' => $newid,
							'msg' => LANG::GET('calendar.event_success'),
							'type' => 'success'
						]]);
					else $this->response([
						'response' => [
							'id' => false,
							'msg' => LANG::GET('calendar.event_error'),
							'type' => 'error'
						]]);
					}
				break;
			case 'GET':
				$result = ['render' => ['content' => []]];
				$month = $calendar->render('month', 'timesheet', $this->_requestedTimespan);
				$previousmonth = clone $calendar->_days[6]; // definetly a date and not a null filler
				$previousmonth->modify('-1 month');
				$nextmonth = clone $calendar->_days[6];
				$nextmonth->modify('+1 month');

				$result['render']['content'][] = [
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
					];
					$events = $bulkapproval = [];
					$displayabsentmates = '';

					$thisDaysEvents = $calendar->getDay($this->_requestedDate);
					foreach ($thisDaysEvents as $id => $row){
						if (!$row['affected_user']) $row['affected_user'] = LANG::GET('message.deleted_user');
						if ($row['type'] === 'timesheet'
							&& !in_array($row['subject'], INI['calendar']['hide_offduty_reasons'])
							&& array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])) $displayabsentmates .= "* " . $row['affected_user'] . " ". LANGUAGEFILE['calendar']['timesheet_pto'][$row['subject']] . " ". substr($row['span_start'], 0, 10) . " - ". substr($row['span_end'], 0, 10) . "\n";
						if ($row['type'] === 'timesheet'
							&& !$row['closed']
							&& (PERMISSION::permissionFor('calendarfullaccess')
							|| (array_intersect(['supervisor'], $_SESSION['user']['permissions']) && array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])))
							) $bulkapproval[] = $row['id'];
					}
					$events[] = [
						'type' => 'textblock',
						'description' => $this->_requestedDate,
						'content' => $displayabsentmates,
						'attributes' => [
							'id' => 'displayspecificdate',
							'data-type' => 'calendar'
						]
					];
					$displayedEvents = [];
					if ($thisDaysEvents) $displayedEvents = $this->timesheetEntries($thisDaysEvents, $calendar);
					// avoid multiple entries by non authorized users
					if (!$displayedEvents
						|| PERMISSION::permissionFor('calendarfulltimesheetexport')
						|| (array_intersect(['supervisor'], $_SESSION['user']['permissions']) && array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units']))){
						$events[] = [
							'type' => 'calendarbutton',
							'attributes' => [
								'value' => LANG::GET('calendar.timesheet_new'),
								'onpointerup' => $calendar->dialog($columns)
							]
						];
					}
					if ($displayedEvents) array_push($events, ...$displayedEvents);
					if ($bulkapproval){
						$events[] = [
							'type' => 'button',
							'attributes' => [
								'value' => LANG::GET('calendar.timesheet_bulk_approve', [':number' => count($bulkapproval)]),
								'onpointerup' => "api.calendar('put', 'complete', '" . implode(',', $bulkapproval) . "', true, 'timesheet')"
							]
						];
					}
					$events[] = [
						'type' => 'button',
						'attributes' => [
							'value' => LANG::GET('calendar.timesheet_monthly_summary'),
							'onpointerup' => "api.calendar('get', 'monthlyTimesheets', '" . $this->_requestedDate . "')"
						]
					];

					$result['render']['content'][] = $events;

					$today = new DateTime($this->_requestedDate, new DateTimeZone(INI['timezone']));
					$pastEvents = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
					if ($pastEvents) {
						$uncompleted = [];
						foreach ($pastEvents as $id => $row){
							if ($row['type'] !== 'schedule' || !array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units']) || $row['closed']) unset($pastEvents[$id]);
						}
						if ($pastEvents){
							$events = [
								[
									'type' => 'textblock',
									'description' => LANG::GET('calendar.events_assigned_units_uncompleted'),
									'attributes' => [
										'data-type' => 'calendar'
									]
								]
							];
							array_push($events, ...$this->scheduledEvents($pastEvents, $calendar));
							$result['render']['content'][] = $events;	
						}
					}
				}
				break;
			case 'DELETE':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']) || 
					($row['affected_user_id'] === $_SESSION['user']['id'] && !$row['closed']))
				) $this->response([], 401);

				if ($calendar->delete($this->_requestedId)) $this->response([
					'response' => [
						'msg' => LANG::GET('calendar.event_deleted'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => LANG::GET('calendar.event_not_found'),
						'type' => 'error'
					]]);
			
				break;
		}
		$this->response($result);
	}

	/**
	 * retrieve all timesheet entries from the database,
	 * prepare and calculate hours, vacation days and other pto
	 * gathers all the entries though, supposed to be filtered by different methods 
	 */
	public function monthlyTimesheets(){
		$calendar = new CALENDARUTILITY($this->_pdo);
		$calendar->days('month', $this->_requestedTimespan);
		$holidays = $calendar->holidays(substr($this->_requestedTimespan, 0, 4));
		$days = $calendar->_days;
		$first = $last = '';

		function timeStrToFloat($string){
			$string = explode(':', $string);
			return intval($string[0]) + (intval($string[1]) / 60);
		}

		foreach($days as $id => $day){
			if ($day === null) unset($days[$id]);
			else {
				$first = clone $day;
				break;
			}
		}
		$last = clone $days[count($days) - 1];
		$days = array_values($days);
		
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		// retrieve stats in advance
		$timesheet_stats_month = $calendar->timesheetSummary($users, $first->format('Y-m-d'), $last->format('Y-m-d'));
		$timesheet_stats_all = $calendar->timesheetSummary($users, null, $last->format('Y-m-d'));
		// item listing still needs another request for all monthly events and entries
		$month = $calendar->getWithinDateRange($first->format('Y-m-d H:i:s'), $last->format('Y-m-d H:i:s'));

		$timesheets = [];
		// prepare interval for daily hours display
		$minuteInterval = new DateInterval('PT1M');

		//iterate over all days of the selected month
		foreach ($days as $day){
			if (!$day) continue; // null, beginning of month
			// iterate over all entries within the selected month
			foreach ($month as $id => $entry){
				if ($entry['type'] !== 'timesheet'){
					unset($month[$id]); // default delete for next iteration
					continue;
				}
				//retrieve stats for affected user
				$stats_month_row = array_search($entry['affected_user_id'], array_column($timesheet_stats_month, '_id'));
				if ($stats_month_row === false) continue;

				$stats_month_row = $timesheet_stats_month[$stats_month_row];
				$stats_all_row = $timesheet_stats_all[array_search($entry['affected_user_id'], array_column($timesheet_stats_all, '_id'))];
				if (!array_key_exists($entry['affected_user_id'], $timesheets)) {
					$units = array_map(Fn($u)=>LANGUAGEFILE['units'][$u], explode(',', $entry['affected_user_units']));
					$pto = [];
					foreach(LANGUAGEFILE['calendar']['timesheet_pto'] as $key => $translation){
						if (array_key_exists($key, $stats_month_row)) $pto[$key] = $stats_month_row[$key];
					}
					$timesheets[$entry['affected_user_id']] = [
						'name' => $entry['affected_user'],
						'user_id' => $entry['affected_user_id'],
						'units' => implode(', ', $units),
						'month' => LANGUAGEFILE['general']['month'][$day->format('n')] . ' ' . $day->format('Y'),
						'days' => [],
						'pto' => $pto,
						'performed' => $stats_month_row['_performed'],
						'projected' => $stats_month_row['_projected'],
						'weeklyhours' => $stats_month_row['_span_end_weeklyhours'],
						'leftvacation' => $stats_all_row['_leftvacation'],
						'overtime' => $stats_all_row['_overtime'],
						'monthlyovertime' => $stats_month_row['_overtime']
					];
				}
				
				$span_start = new DateTime($entry['span_start'], new DateTimeZone(INI['timezone']));
				$span_end = new DateTime($entry['span_end'], new DateTimeZone(INI['timezone']));
				if (($span_start <= $day || $span_start->format('Y-m-d') === $day->format('Y-m-d'))
					&& ($day <= $span_end || $span_end->format('Y-m-d') === $day->format('Y-m-d'))
					&& !array_key_exists($day->format('Y-m-d'), $timesheets[$entry['affected_user_id']]['days'])){
					// calculate hours for stored regular working days only
					$misc = json_decode($entry['misc'], true);
					if (!strlen($entry['subject'])) {
						$firstday = $days[0]; // copy object for down below method usage
						$lastday = $days[count($days) - 1];  // copy object for down below method usage
						$periods = new DatePeriod($span_start < $firstday ? $firstday : $span_start, $minuteInterval, $span_end > $lastday ? $lastday : $span_end);
						$dailyhours = iterator_count($periods) / 60;
						if (array_key_exists('homeoffice', $misc)) $dailyhours += timeStrToFloat($misc['homeoffice']);
						if (array_key_exists('break', $misc)) $dailyhours -= timeStrToFloat($misc['break']);

						$timesheets[$entry['affected_user_id']]['days'][$day->format('Y-m-d')] = [
							'subject' => ($span_start < $firstday ? $firstday->format('H:i') : $span_start->format('H:i')) . ' - ' . ($span_end > $lastday ? $lastday->format('H:i') : $span_end->format('H:i')),
							'break' => array_key_exists('break', $misc) ? $misc['break'] : '',
							'homeoffice' => array_key_exists('homeoffice', $misc) ? $misc['homeoffice'] : '',
							'note' => array_key_exists('note', $misc) ? $misc['note'] : '',
							'hours' => $dailyhours,
						];
					}
					// else state subject
					else $timesheets[$entry['affected_user_id']]['days'][$day->format('Y-m-d')] = ['subject' => LANGUAGEFILE['calendar']['timesheet_pto'][$entry['subject']], 'note' => array_key_exists('note', $misc) ? $misc['note'] : ''];
				}
			}
		}
		// postprocess array
		foreach($timesheets as $id => $user){
			// append missing dates for overview, after all the output shall be comprehensible
			foreach ($days as $day){
				if (!array_key_exists($day->format('Y-m-d'), $user['days'])) $timesheets[$id]['days'][$day->format('Y-m-d')] = [];
				$timesheets[$id]['days'][$day->format('Y-m-d')]['weekday'] = LANGUAGEFILE['general']['weekday'][$day->format('N')];
				$timesheets[$id]['days'][$day->format('Y-m-d')]['holiday'] = in_array($day->format('Y-m-d'), $holidays) || !in_array($day->format('N'), $calendar->_workdays);
			}
			// sort date keys
			ksort($timesheets[$id]['days']);
		}
		// sort by user name
		usort($timesheets, function ($a, $b) {
			return $a['name'] === $b['name'] ? 0 : ($a['name'] < $b['name'] ? -1 : 1); 
		});
		// set self to top 
		$self = array_splice($timesheets, array_search($_SESSION['user']['id'], array_column($timesheets, 'user_id')), 1);
		array_splice($timesheets, 0, 0, $self);

		$summary = [
			'filename' => preg_replace('/[^\w\d]/', '', LANG::GET('menu.calendar_timesheet') . '_' . date('Y-m-d H:i')),
			'identifier' => null,
			'content' => $this->prepareTimesheetOutput($timesheets),
			'files' => [],
			'images' => [],
			'title' => LANG::GET('menu.calendar_timesheet'),
			'date' => date('y-m-d H:i')
		];

		$downloadfiles = [];
		$downloadfiles[LANG::GET('menu.calendar_timesheet')] = [
			'href' => PDF::timesheetPDF($summary)
		];
		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  LANG::GET('calendar.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}

	/**
	 * filter by permission, prepare output for pdf handler
	 * @param array $timesheets prepared database results
	 * 
	 * @return array prepared for pdf processing
	 * [
	 * 		[ // user
	 * 			[], // empty row
	 * 			[
	 * 				[str description, bool greyed out], // marked holidays and non working day as per ini
	 * 				str content
	 * 			]
	 * 		]
	 * ]
	 */
	private function prepareTimesheetOutput($timesheets = []){
		$result = [];
		foreach($timesheets as $user){
			$rows = [];
			if (PERMISSION::permissionFor('calendarfulltimesheetexport')
				|| (array_intersect(['supervisor'], $_SESSION['user']['permissions']) && array_intersect(explode(',', $user['units']), $_SESSION['user']['units']))
				|| $id === $_SESSION['user']['id']
			){
				// summary
				$rows[] = [
					[$user['name'], false],
					LANG::GET('calendar.export_sheet_subject', [
						':appname' => INI['system']['caroapp'],
						':id' => $user['user_id'],
						':units' => $user['units'],
						':weeklyhours' => $user['weeklyhours'],
					])
				];
				$rows[] = [];
				// days
				foreach ($user['days'] as $date => $day){
					$dayinfo = [];
					if (array_key_exists('subject', $day)) $dayinfo[] = $day['subject'];
					foreach(LANGUAGEFILE['calendar']['export_sheet_daily'] as $key => $value){
						if (array_key_exists($key, $day) && !in_array($day[$key], [0, '00:00'])) $dayinfo[] = $value . ' ' . $day[$key];
					}
					if (array_key_exists('note', $day) && $day['note']) $dayinfo[] = $day['note'];
					
					$rows[] = [
						[$day['weekday'] . ' ' . $date, $day['holiday']],
						implode(', ', $dayinfo)
					];
				}
				$rows[] = [];
				// pto
				foreach ($user['pto'] as $pto => $number){
					$rows[] = [
						[LANGUAGEFILE['calendar']['timesheet_pto'][$pto], false],
						LANG::GET('calendar.export_sheet_exemption_days', [':number' => $number])
					];
				}
				if ($user['pto']) $rows[] = [];
				$rows[] = [
					[LANG::GET('calendar.export_sheet_summary'), false],
					LANG::GET('calendar.export_sheet_summary_text', [
						':name' => $user['name'],
						':performed' => $user['performed'],
						':projected' => $user['projected'],
						':month' => $user['month'],
						':overtime' => $user['overtime'],
						':_monthlyovertime' => $user['monthlyovertime'],
						':vacation' => $user['leftvacation'],
					])
				];
				$rows[] = [];

				$result[] = $rows;				
			}
		}
		return $result;
	}
}
?>
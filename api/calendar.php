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
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedTimespan = $this->_requestedId = isset(REQUEST[2]) ? REQUEST[2] : null;
		$this->_requestedDate = $this->_requestedComplete = isset(REQUEST[3]) ? REQUEST[3] : null;
		$this->_requestedCalendarType = isset(REQUEST[4]) ? REQUEST[4] : null;
	}

	/**
	 *                     _     _
	 *   ___ ___ _____ ___| |___| |_ ___
	 *  |  _| . |     | . | | -_|  _| -_|
	 *  |___|___|_|_|_|  _|_|___|_| |___|
	 *                |_|
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
				0 => $this->_lang->GET('calendar.event_incompleted'),
				1 => $this->_lang->GET('calendar.event_completed')
			],
			'timesheet' => [
				0 => $this->_lang->GET('calendar.timesheet_disapproved'),
				1 => $this->_lang->GET('calendar.timesheet_approved')
			],
		];
		$alert = null;
		if ($this->_requestedCalendarType === 'schedule') $alert = intval($response[$this->_requestedCalendarType][intval($this->_requestedComplete === 'true')]);

		$calendar = new CALENDARUTILITY($this->_pdo);
		require_once('notification.php');
		$notifications = new NOTIFICATION;
		if ($calendar->complete($this->_requestedId, $this->_requestedComplete === 'true', $alert)) $this->response([
			'response' => [
				'msg' => $response[$this->_requestedCalendarType][intval($this->_requestedComplete === 'true')],
				'type' => 'success'
			],
			'data' => ['calendar_uncompletedevents' => $notifications->calendar()]]);
		else $this->response([
			'response' => [
				'msg' => $this->_lang->GET('calendar.event_not_found'),
				'type' => 'error'
			]]);
	}

	/**
	 *                 _   _   _     _   _               _           _
	 *   _____ ___ ___| |_| |_| |_ _| |_|_|_____ ___ ___| |_ ___ ___| |_
	 *  |     | . |   |  _|   | | | |  _| |     | -_|_ -|   | -_| -_|  _|
	 *  |_|_|_|___|_|_|_| |_|_|_|_  |_| |_|_|_|_|___|___|_|_|___|___|_|
	 *                          |___|
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
		$days = array_values($days);
		$last = clone $days[count($days) - 1];
		
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
				if (!isset($timesheets[$entry['affected_user_id']])) {
					$units = array_map(Fn($u) => $this->_lang->_USER['units'][$u], explode(',', $entry['affected_user_units']));
					$pto = [];
					foreach($this->_lang->_USER['calendar']['timesheet_pto'] as $key => $translation){
						if (isset($stats_month_row[$key])) $pto[$key] = $stats_month_row[$key];
					}
					$timesheets[$entry['affected_user_id']] = [
						'name' => $entry['affected_user'],
						'user_id' => $entry['affected_user_id'],
						'units' => implode(', ', $units),
						'month' => $this->_lang->_USER['general']['month'][$day->format('n')] . ' ' . $day->format('Y'),
						'days' => [],
						'pto' => $pto,
						'performed' => $stats_month_row['_performed'],
						'projected' => $stats_month_row['_projected'],
						'weeklyhours' => $stats_month_row['_span_end_weeklyhours'],
						'leftvacation' => $stats_all_row['_leftvacation'],
						'overtime' => $stats_all_row['_overtime'] - $stats_all_row['_initialovertime'],
						'monthlyovertime' => $stats_month_row['_overtime']
					];
				}
				
				$span_start = new DateTime($entry['span_start'], new DateTimeZone(CONFIG['application']['timezone']));
				$span_end = new DateTime($entry['span_end'], new DateTimeZone(CONFIG['application']['timezone']));
				if (($span_start <= $day || $span_start->format('Y-m-d') === $day->format('Y-m-d'))
					&& ($day <= $span_end || $span_end->format('Y-m-d') === $day->format('Y-m-d'))
					&& !isset($timesheets[$entry['affected_user_id']]['days'][$day->format('Y-m-d')])){
					// calculate hours for stored regular working days only
					$misc = json_decode($entry['misc'], true);
					if (!strlen($entry['subject'])) {
						$firstday = $days[0]; // copy object for down below method usage
						$lastday = $days[count($days) - 1];  // copy object for down below method usage
						$periods = new DatePeriod($span_start < $firstday ? $firstday : $span_start, $minuteInterval, $span_end > $lastday ? $lastday : $span_end);
						$dailyhours = iterator_count($periods) / 60;
						if (isset($misc['homeoffice'])) $dailyhours += timeStrToFloat($misc['homeoffice']);
						if (isset($misc['break'])) $dailyhours -= timeStrToFloat($misc['break']);

						$timesheets[$entry['affected_user_id']]['days'][$day->format('Y-m-d')] = [
							'subject' => ($span_start < $firstday ? $firstday->format('H:i') : $span_start->format('H:i')) . ' - ' . ($span_end > $lastday ? $lastday->format('H:i') : $span_end->format('H:i')),
							'break' => isset($misc['break']) ? $misc['break'] : '',
							'homeoffice' => isset($misc['homeoffice']) ? $misc['homeoffice'] : '',
							'note' => isset($misc['note']) ? $misc['note'] : '',
							'hours' => $dailyhours,
						];
					}
					// else state subject
					else $timesheets[$entry['affected_user_id']]['days'][$day->format('Y-m-d')] = ['subject' => $this->_lang->_USER['calendar']['timesheet_pto'][$entry['subject']], 'note' => isset($misc['note']) ? $misc['note'] : ''];
				}
			}
		}
		// postprocess array
		foreach($timesheets as $id => $user){
			// append missing dates for overview, after all the output shall be comprehensible
			foreach ($days as $day){
				if (!isset($user['days'][$day->format('Y-m-d')])) $timesheets[$id]['days'][$day->format('Y-m-d')] = [];
				$timesheets[$id]['days'][$day->format('Y-m-d')]['weekday'] = $this->_lang->_USER['general']['weekday'][$day->format('N')];
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
			'filename' => preg_replace('/[^\w\d]/', '', $this->_lang->GET('menu.calendar_timesheet') . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => $this->prepareTimesheetOutput($timesheets),
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('menu.calendar_timesheet'),
			'date' => $this->_currentdate->format('y-m-d H:i')
		];

		$downloadfiles = [];
		$downloadfiles[$this->_lang->GET('menu.calendar_timesheet')] = [
			'href' => PDF::timesheetPDF($summary)
		];
		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('calendar.export_proceed'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
		]);
	}

	/**
	 *                               _   _               _           _           _           _
	 *   ___ ___ ___ ___ ___ ___ ___| |_|_|_____ ___ ___| |_ ___ ___| |_ ___ _ _| |_ ___ _ _| |_
	 *  | . |  _| -_| . | .'|  _| -_|  _| |     | -_|_ -|   | -_| -_|  _| . | | |  _| . | | |  _|
	 *  |  _|_| |___|  _|__,|_| |___|_| |_|_|_|_|___|___|_|_|___|___|_| |___|___|_| |  _|___|_|
	 *  |_|         |_|                                                             |_|
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
					$this->_lang->GET('calendar.export_sheet_subject', [
						':appname' => CONFIG['system']['caroapp'],
						':id' => $user['user_id'],
						':units' => $user['units'],
						':weeklyhours' => $user['weeklyhours'],
					])
				];
				$rows[] = [];
				// days
				foreach ($user['days'] as $date => $day){
					$dayinfo = [];
					if (isset($day['subject'])) $dayinfo[] = $day['subject'];
					foreach($this->_lang->_USER['calendar']['export_sheet_daily'] as $key => $value){
						if (isset($day[$key]) && !in_array($day[$key], [0, '00:00'])) $dayinfo[] = $value . ' ' . $day[$key];
					}
					if (isset($day['note'])) $dayinfo[] = $day['note'];
					
					$rows[] = [
						[$day['weekday'] . ' ' . $date, $day['holiday']],
						implode(', ', $dayinfo)
					];
				}
				$rows[] = [];
				// pto
				foreach ($user['pto'] as $pto => $number){
					$rows[] = [
						[$this->_lang->_USER['calendar']['timesheet_pto'][$pto], false],
						$this->_lang->GET('calendar.export_sheet_exemption_days', [':number' => $number])
					];
				}
				if ($user['pto']) $rows[] = [];
				$rows[] = [
					[$this->_lang->GET('calendar.export_sheet_summary'), false],
					$this->_lang->GET('calendar.export_sheet_summary_text', [
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
				foreach($this->_lang->_USER['calendar']['timesheet_signature'] as $key => $value) $rows[] = [[$value, false], str_repeat('_', 20)];
				$rows[] = [];

				$result[] = $rows;				
			}
		}
		return $result;
	}

	/**
	 *                       _
	 *   ___ ___ ___ ___ ___| |_
	 *  |_ -| -_| .'|  _|  _|   |
	 *  |___|___|__,|_| |___|_|_|
	 *
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
					'description' => $this->_lang->GET('assemble.scan_button')
				], [
					'type' => 'search',
					'attributes' => [
						'value' => $this->_requestedId,
						'id' => 'recordfilter',
						'name' => $this->_lang->GET('calendar.event_search'),
						'onkeypress' => "if (event.key === 'Enter') {api.calendar('get', 'search', this.value); return false;}",
						'onblur' => "api.calendar('get', 'search', this.value); return false;",
					],
					'hint' => $this->_lang->GET('calendar.event_search_hint'),
				]
			]
		]]];
		$calendar = new CALENDARUTILITY($this->_pdo);
		$dbevents = $calendar->search($this->_requestedId);
		$events = $this->scheduledEvents($dbevents, $calendar) ? : [
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET ('calendar.events_none')
				]
			]
		] ;
		$result['render']['content'][] = $events;
		$this->response($result);
	}

	/**
	 *           _         _     _
	 *   ___ ___| |_ ___ _| |_ _| |___
	 *  |_ -|  _|   | -_| . | | | | -_|
	 *  |___|___|_|_|___|___|___|_|___|
	 *
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
		require_once('notification.php');
		$notifications = new NOTIFICATION;

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$affected_user_id = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_affected_user'));
				if (!$affected_user_id || $affected_user_id === '...') $affected_user_id = null;
				$event = [
					':type' => 'schedule',
					':span_start' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_date')),
					':span_end' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_due')),
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $affected_user_id,
					':organizational_unit' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_organizational_unit')),
					':subject' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_content')),
					':misc' => '',
					':closed' => '',
					':alert' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if (!($event[':span_start'] && $event[':organizational_unit'] && $event[':subject'])) $this->response(['response' => ['msg' => $this->_lang->GET('calendar.event_error_missing'), 'type' => 'error']]);
				if (!$event[':span_end']){
					$due = new DateTime($event[':span_start'], new DateTimeZone(CONFIG['application']['timezone']));
					$due->modify('+' . CONFIG['calendar']['default_due'] . ' months');
					$event[':span_end'] = $due->format('Y-m-d');	
				}
				if ($newid = $calendar->post($event)) $this->response([
					'response' => [
						'id' => $newid,
						'msg' => $this->_lang->GET('calendar.event_success'),
						'type' => 'success'
					],
					'data' => ['calendar_uncompletedevents' => $notifications->calendar()]]);
				else $this->response([
					'response' => [
						'id' => false,
						'msg' => $this->_lang->GET('calendar.event_error'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				if (!PERMISSION::permissionFor('calendaredit')) $this->response([], 401);
				$affected_user_id = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_affected_user'));
				if (!$affected_user_id || $affected_user_id === '...') $affected_user_id = null;
				$event = [
					':id' => UTILITY::propertySet($this->_payload, 'calendarEventId'),
					':span_start' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_date')),
					':span_end' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_due')),
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $affected_user_id,
					':organizational_unit' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_organizational_unit')),
					':subject' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_content')),
					':misc' => '',
					':closed' => '',
					':alert' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if (!($event[':span_start'] && $event[':organizational_unit'] && $event[':subject'])) $this->response(['response' => ['msg' => $this->_lang->GET('calendar.event_error_missing'), 'type' => 'error']]);
				if (!$event[':span_end']){
					$due = new DateTime($event[':span_start'], new DateTimeZone(CONFIG['application']['timezone']));
					$due->modify('+' . CONFIG['calendar']['default_due'] . ' months');
					$event[':span_end'] = $due->format('Y-m-d');	
				}
				if ($calendar->put($event)) $this->response([
					'response' => [
						'id' => $event[':id'],
						'msg' => $this->_lang->GET('calendar.event_success'),
						'type' => 'success'
					],
					'data' => ['calendar_uncompletedevents' => $notifications->calendar()]]);
				else {
					// without changed values (e.g. on aborting) affected rows returns 0
					// to avoid duplicate entries delete and reinsert
					$calendar->delete($event[':id']);
					unset($event[':id']);
					$event[':type'] = 'schedule';
					if ($newid = $calendar->post($event)) $this->response([
						'response' => [
							'id' => $newid,
							'msg' => $this->_lang->GET('calendar.event_success'),
							'type' => 'success'
						],
						'data' => ['calendar_uncompletedevents' => $notifications->calendar()]]);
					else $this->response([
						'response' => [
							'id' => false,
							'msg' => $this->_lang->GET('calendar.event_error'),
							'type' => 'error'
						]]);
					}
				break;
			case 'GET':
				$result = ['render' => ['content' => []]];
				$month = $calendar->render('month', 'schedule', $this->_requestedTimespan);
				$previousmonth = clone $calendar->_days[6]; // definetly a date and not a null filler
				$previousmonth->modify('-1 month')->modify('last day of this month');
				$nextmonth = clone $calendar->_days[6];
				$nextmonth->modify('+1 month')->modify('first day of this month');

				$result['render']['content'][] = [
					[
						'type' => 'scanner',
						'destination' => 'recordfilter',
						'description' => $this->_lang->GET('assemble.scan_button')
					], [
						'type' => 'search',
						'attributes' => [
							'id' => 'recordfilter',
							'name' => $this->_lang->GET('calendar.event_search'),
							'onkeypress' => "if (event.key === 'Enter') {api.calendar('get', 'search', this.value); return false;}",
							'onblur' => "api.calendar('get', 'search', this.value); return false;",
						],
						'hint' => $this->_lang->GET('calendar.event_search_hint'),
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
							'value' => $this->_lang->GET('calendar.month_previous'),
							'type' => 'button',
							'onpointerup' => "api.calendar('get', 'schedule', '" . $previousmonth->format('Y-m-d') . "', '" . $previousmonth->format('Y-m-d') . "')",
							'data-type' => 'toleft'
						]
					],
					[
						'type' => 'button',
						'attributes' => [
							'value' => $this->_lang->GET('calendar.month_next') . ' ',
							'type' => 'button',
							'onpointerup' => "api.calendar('get', 'schedule', '" . $nextmonth->format('Y-m-d') . "', '" . $nextmonth->format('Y-m-d') . "')",
							'data-type' => 'toright'
						]
					],
				];
				if (!$this->_requestedDate){
					$today = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
					$this->_requestedDate = $today->format('Y-m-d');
				}
				if ($this->_requestedDate){
					$columns = [
						':type' => 'schedule',
						':span_start' => $this->_requestedDate,
					];
					$events = [];
					$displayabsentmates = '';

					$thisDaysEvents = $calendar->getDay($this->_requestedDate);
					foreach ($thisDaysEvents as $id => $row){
						if (!$row['affected_user']) $row['affected_user'] = $this->_lang->GET('message.deleted_user');
						if ($row['type'] === 'timesheet' && !in_array($row['subject'], CONFIG['calendar']['hide_offduty_reasons']) && array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])) $displayabsentmates .= "* " . $row['affected_user'] . " ". $this->_lang->_USER['calendar']['timesheet_pto'][$row['subject']] . " ". substr($row['span_start'], 0, 10) . " - ". substr($row['span_end'], 0, 10) . "\n";
					}

					$events[] = [
						'type' => 'textsection',
						'content' => $displayabsentmates,
						'attributes' => [
							'id' => 'displayspecificdate',
							'data-type' => 'calendar',
							'name' => $this->_requestedDate
						]
					];
					$events[] = [
						'type' => 'calendarbutton',
						'attributes' => [
							'value' => $this->_lang->GET('calendar.event_new'),
							'onpointerup' => $calendar->dialog($columns)
						]
					];

					if ($thisDaysEvents) array_push($events, ...$this->scheduledEvents($thisDaysEvents, $calendar));
					$result['render']['content'][] = $events;

					$today = new DateTime($this->_requestedDate, new DateTimeZone(CONFIG['application']['timezone']));
					$pastEvents = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
					if ($pastEvents) {
						$uncompleted = [];
						foreach ($pastEvents as $id => $row){
							if (in_array($row, $thisDaysEvents) || $row['type'] !== 'schedule' || !array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) || $row['closed']) unset($pastEvents[$id]);
						}
						if ($pastEvents){
							$events = [
								[
									'type' => 'textsection',
									'attributes' => [
										'data-type' => 'calendar',
										'name' => $this->_lang->GET('calendar.events_assigned_units_uncompleted')
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
						'msg' => $this->_lang->GET('calendar.event_deleted'),
						'type' => 'success'
					],
					'data' => ['calendar_uncompletedevents' => $notifications->calendar()]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('calendar.event_not_found'),
						'type' => 'error'
					]]);
			
				break;
		}
		$this->response($result);
	}
	
	/**
	 *           _         _     _       _                 _
	 *   ___ ___| |_ ___ _| |_ _| |___ _| |___ _ _ ___ ___| |_ ___
	 *  |_ -|  _|   | -_| . | | | | -_| . | -_| | | -_|   |  _|_ -|
	 *  |___|___|_|_|___|___|___|_|___|___|___|\_/|___|_|_|_| |___|
	 *
	 * renders scheduled events as tiles
	 * @param array $dbevents db query results
	 * @param object $calendar inherited CALENDARUTILITY-object
	 * 
	 * @return array render options for assemble.js 
 	*/
	private function scheduledEvents($dbevents, $calendar){
		$events = [];
		foreach($dbevents as $row){
			$date = new DateTime($row['span_start'], new DateTimeZone(CONFIG['application']['timezone']));
			$due = new DateTime($row['span_end'], new DateTimeZone(CONFIG['application']['timezone']));
			if (!array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']) || $row['type'] !== 'schedule' ) continue;
			$display = $this->_lang->GET('calendar.event_date') . ': ' . $date->format('Y-m-d') . "\n" .
				$this->_lang->GET('calendar.event_due') . ': ' . $due->format('Y-m-d') . "\n";
			$display .= implode(', ', array_map(Fn($unit) => $this->_lang->_USER['units'][$unit], explode(',', $row['organizational_unit'])));
			if ($row['affected_user_id']){
				$user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => $row['affected_user_id'],
						':name' => ''
					]
				]);
				$user = $user ? $user[0] : null;
				if ($user['name']) $display .= "\n" . $user['name'];
			}

			// replace deleted user names
			if (!$row['author']) $row['author'] = $this->_lang->GET('message.deleted_user');
			if (!$row['affected_user']) $row['affected_user'] = $this->_lang->GET('message.deleted_user');

			$completed[$this->_lang->GET('calendar.event_complete')] = ['onchange' => "api.calendar('put', 'complete', '" . $row['id'] . "', this.checked, 'schedule')"];
			$completed_hint = '';
			if ($row['closed']) {
				$completed[$this->_lang->GET('calendar.event_complete')]['checked'] = true;
				$row['closed'] = json_decode($row['closed'], true);
				$completed_hint = $this->_lang->GET('calendar.event_completed_state', [':user' => $row['closed']['user'], ':date' => $row['closed']['date']]);
			}

			$events[] = [
				'type' => 'tile',
				'content' => [
					[
						'type' => 'textsection',
						'attributes' => [
							'data-type' => $row['alert'] ? 'alert' : 'text',
							'name' => $row['subject']
						],
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
						'value' => $this->_lang->GET('calendar.event_edit'),
						'onpointerup' => $calendar->dialog($columns)
					],
					'hint' => $this->_lang->GET('calendar.event_author') . ': ' . $row['author']
				];
				$events[count($events)-1]['content'][] = [
					'type' => 'deletebutton',
					'attributes' => [
						'value' => $this->_lang->GET('calendar.event_delete'),
						'onpointerup' => "new Dialog({type:'confirm', header:'" . $this->_lang->GET('calendar.event_delete') . " " . $row['subject'] . "', options:{'" . $this->_lang->GET('general.cancel_button') . "': false, '" . $this->_lang->GET('calendar.event_delete') . "': {'value': true, class: 'reducedCTA'}}})" .
							".then(confirmation => {if (confirmation) api.calendar('delete', 'schedule', " . $row['id'] . "); this.disabled = Boolean(confirmation);});"
					]
				];
			}
		}
		return $events;
	}

	/**
	 *   _   _               _           _
	 *  | |_|_|_____ ___ ___| |_ ___ ___| |_
	 *  |  _| |     | -_|_ -|   | -_| -_|  _|
	 *  |_| |_|_|_|_|___|___|_|_|___|___|_|
	 *
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
				$affected_user_id = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_affected_user'));
				if (!$affected_user_id || $affected_user_id === '...') $affected_user_id = $_SESSION['user']['id'];
				if ($affected_user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => $affected_user_id,
						':name' => ''
					]
				])) $affected_user = $affected_user[0];
		
				$event = [
					':type' => 'timesheet',
					':span_start' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_start_date')) . ' ' . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_start_time')) . ':00',
					':span_end' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_end_date')) . ' ' . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_end_time')) . ':00',
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $affected_user['id'],
					':organizational_unit' => '',
					':subject' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_pto_exemption')) ? : '',
					':misc' => '',
					':closed' => '',
					':alert' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if ($event[':subject'] === 'regular') $event[':subject'] = '';
				$misc = $event[':subject'] ? [] : [
					'note' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_pto_note')) ? : '',
					'break' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_break_time'))
				];
				if ($homeoffice = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_homeoffice'))){
					$misc['homeoffice'] = $homeoffice;
				}
				$event[':misc'] = json_encode($misc);
				if (!($event[':span_start'] && $event[':span_end'] && (isset($misc['break']) || $event[':subject']))) $this->response(['response' => ['msg' => $this->_lang->GET('calendar.timesheet_error_missing'), 'type' => 'error']]);
				if ($newid = $calendar->post($event)) $this->response([
					'response' => [
						'id' => $newid,
						'msg' => $this->_lang->GET('calendar.event_success'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => false,
						'msg' => $this->_lang->GET('calendar.event_error'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']) || 
				($row['affected_user_id'] === $_SESSION['user']['id'] && !$row['closed']))
				) $this->response([], 401);

				$affected_user_id = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_affected_user'));
				if (!$affected_user_id || $affected_user_id === '...') $affected_user_id = $_SESSION['user']['id'];
				if ($affected_user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => $affected_user_id
					]
				])) $affected_user = $affected_user[0];
		
				$event = [
					':id' => UTILITY::propertySet($this->_payload, 'calendarEventId'),
					':span_start' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_start_date')) . ' ' . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_start_time')) . ':00',
					':span_end' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_end_date')) . ' ' . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_end_time')) . ':00',
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $affected_user['id'],
					':organizational_unit' => '',
					':subject' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_pto_exemption')) ? : '',
					':misc' => '',
					':closed' => '',
					':alert' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.event_alert')) ? 1 : 0
				];
				if ($event[':subject'] === 'regular') $event[':subject'] = '';
				$misc = $event[':subject'] ? [] : [
					'note' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_pto_note')) ? : '',
					'break' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_break_time'))
				];
				if ($homeoffice = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet_homeoffice'))){
					$misc['homeoffice'] = $homeoffice;
				}

				$event[':misc'] = json_encode($misc);
				if (!($event[':span_start'] && $event[':span_end'] && (isset($misc['break']) || $event[':subject']))) $this->response(['response' => ['msg' => $this->_lang->GET('calendar.timesheet_error_missing'), 'type' => 'error']]);
				if ($calendar->put($event)) $this->response([
					'response' => [
						'id' => $event[':id'],
						'msg' => $this->_lang->GET('calendar.event_success'),
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
							'msg' => $this->_lang->GET('calendar.event_success'),
							'type' => 'success'
						]]);
					else $this->response([
						'response' => [
							'id' => false,
							'msg' => $this->_lang->GET('calendar.event_error'),
							'type' => 'error'
						]]);
					}
				break;
			case 'GET':
				$result = ['render' => ['content' => []]];
				$month = $calendar->render('month', 'timesheet', $this->_requestedTimespan);
				$previousmonth = clone $calendar->_days[6]; // definetly a date and not a null filler
				$previousmonth->modify('-1 month')->modify('last day of this month');
				$nextmonth = clone $calendar->_days[6];
				$nextmonth->modify('+1 month')->modify('first day of this month');

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
							'value' => $this->_lang->GET('calendar.month_previous'),
							'type' => 'button',
							'onpointerup' => "api.calendar('get', 'timesheet', '" . $previousmonth->format('Y-m-d') . "', '" . $previousmonth->format('Y-m-d') . "')",
							'data-type' => 'toleft'
						]
					],
					[
						'type' => 'button',
						'attributes' => [
							'value' => $this->_lang->GET('calendar.month_next') . ' ',
							'type' => 'button',
							'onpointerup' => "api.calendar('get', 'timesheet', '" . $nextmonth->format('Y-m-d') . "', '" . $nextmonth->format('Y-m-d') . "')",
							'data-type' => 'toright'
						]
					],
				];
				if (!$this->_requestedDate){
					$today = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
					$this->_requestedDate = $today->format('Y-m-d');
				}

				if ($this->_requestedDate){
					$columns = [
						':type' => 'timesheet',
						':span_start' => $this->_requestedDate,
					];
					$events = $bulkapproval = [];
					$displayabsentmates = '';

					$thisDaysEvents = $calendar->getDay($this->_requestedDate);
					foreach ($thisDaysEvents as $id => $row){
						if (!$row['affected_user']) $row['affected_user'] = $this->_lang->GET('message.deleted_user');
						if ($row['type'] === 'timesheet'
							&& !in_array($row['subject'], CONFIG['calendar']['hide_offduty_reasons'])
							&& array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])) $displayabsentmates .= "* " . $row['affected_user'] . " ". $this->_lang->_USER['calendar']['timesheet_pto'][$row['subject']] . " ". substr($row['span_start'], 0, 10) . " - ". substr($row['span_end'], 0, 10) . "\n";
						if ($row['type'] === 'timesheet'
							&& !$row['closed']
							&& (PERMISSION::permissionFor('calendarfullaccess')
							|| (array_intersect(['supervisor'], $_SESSION['user']['permissions']) && array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])))
							) $bulkapproval[] = $row['id'];
					}
					$events[] = [
						'type' => 'textsection',
						'content' => $displayabsentmates,
						'attributes' => [
							'id' => 'displayspecificdate',
							'data-type' => 'calendar',
							'name' => $this->_requestedDate
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
								'value' => $this->_lang->GET('calendar.timesheet_new'),
								'onpointerup' => $calendar->dialog($columns)
							]
						];
					}
					if ($displayedEvents) array_push($events, ...$displayedEvents);
					if ($bulkapproval){
						$events[] = [
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('calendar.timesheet_bulk_approve', [':number' => count($bulkapproval)]),
								'onpointerup' => "api.calendar('put', 'complete', '" . implode(',', $bulkapproval) . "', true, 'timesheet')"
							]
						];
					}
					$today = new DateTime($this->_requestedDate, new DateTimeZone(CONFIG['application']['timezone']));
					if ($thisMonthsEvents = $calendar->getWithinDateRange($today->modify('first day of this month')->format('Y-m-d'), $today->modify('last day of this month')->format('Y-m-d'))) {
						$timesheetentries = false;
						foreach($thisMonthsEvents as $evt) if ($evt['type']==='timesheet') $timesheetentries = true;
						if ($timesheetentries) $events[] = [
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('calendar.timesheet_monthly_summary'),
								'onpointerup' => "api.calendar('get', 'monthlyTimesheets', '" . $this->_requestedDate . "')"
							]
						];
					}

					$result['render']['content'][] = $events;

					$today = new DateTime($this->_requestedDate, new DateTimeZone(CONFIG['application']['timezone']));
					$pastEvents = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
					if ($pastEvents) {
						$uncompleted = [];
						foreach ($pastEvents as $id => $row){
							if ($row['type'] !== 'schedule' || ($row['affected_user_units'] && !array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])) || $row['closed']) unset($pastEvents[$id]);
						}
						if ($pastEvents){
							$events = [
								[
									'type' => 'textsection',
									'attributes' => [
										'data-type' => 'calendar',
										'name' => $this->_lang->GET('calendar.events_assigned_units_uncompleted')
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
						'msg' => $this->_lang->GET('calendar.event_deleted'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('calendar.event_not_found'),
						'type' => 'error'
					]]);
			
				break;
		}
		$this->response($result);
	}

		/**
	 *   _   _               _           _           _       _
	 *  | |_|_|_____ ___ ___| |_ ___ ___| |_ ___ ___| |_ ___|_|___ ___
	 *  |  _| |     | -_|_ -|   | -_| -_|  _| -_|   |  _|  _| | -_|_ -|
	 *  |_| |_|_|_|_|___|___|_|_|___|___|_| |___|_|_|_| |_| |_|___|___|
	 *
	 * renders timesheet entries as tiles
	 * @param array $dbevents db query results
	 * @param object $calendar inherited CALENDARUTILITY-object
	 * 
	 * @return array render options for assemble.js 
 	*/
	 private function timesheetEntries($dbevents, $calendar){
		$events = [];
		foreach($dbevents as $row){
			$date = new DateTime($row['span_start'], new DateTimeZone(CONFIG['application']['timezone']));
			$due = new DateTime($row['span_end'], new DateTimeZone(CONFIG['application']['timezone']));
			if ($row['type'] !== 'timesheet'
				|| !($row['affected_user_id'] === $_SESSION['user']['id']
				|| PERMISSION::permissionFor('calendarfulltimesheetexport')
				|| (array_intersect(['supervisor'], $_SESSION['user']['permissions'])
				&& array_intersect(explode(',', $row['organization_unit']), $_SESSION['user']['units']))
			)) continue;

			// replace deleted user names
			if (!$row['author']) $row['author'] = $this->_lang->GET('message.deleted_user');
			if (!$row['affected_user']) $row['affected_user'] = $this->_lang->GET('message.deleted_user');

			$hint = '';
			$display = '';
			if ($row['subject']) $display .= $this->_lang->GET('calendar.timesheet_irregular') . ': ' . $this->_lang->_USER['calendar']['timesheet_pto'][$row['subject']] . "\n";
			$display .=	$this->_lang->GET('calendar.timesheet_start') . ': ' . $date->format($row['subject'] ? 'Y-m-d' : 'Y-m-d H:i') . "\n" .
				$this->_lang->GET('calendar.timesheet_end') . ': ' . $due->format($row['subject'] ? 'Y-m-d' : 'Y-m-d H:i') . "\n";

			if ($row['misc']){
				$misc = json_decode($row['misc'], true);
				if (!$row['subject'] && isset($misc['break'])) $display .= $this->_lang->GET('calendar.timesheet_break') . ': ' . $misc['break'] . "\n";
				if (!$row['subject'] && isset($misc['homeoffice'])) $display .= $this->_lang->GET('calendar.timesheet_homeoffice') . ': ' . $misc['homeoffice'] . "\n";
				if ($row['author_id'] != $row['affected_user_id']) {
					$hint = $this->_lang->GET('calendar.timesheet_foreign_contributor') . ': ' . $row['author'] . "\n";
				}
			}

			$events[] = [
				'type' => 'tile',
				'content' => [
					[
						'type' => 'textsection',
						'attributes' => [
							'data-type' => $row['alert'] ? 'alert' : 'text',
							'name' => $row['affected_user']
						],
						'content' => $display,
						'hint' => $hint ? : null
					],					
				]
			];
			/**
			 * approval can only be set by
			 * admin, ceo and supervisor of assigned unit
			 */
			$completed[$this->_lang->GET('calendar.timesheet_approve')] = ['onchange' => "api.calendar('put', 'complete', '" . $row['id'] . "', this.checked, 'timesheet')"];
			if (!(PERMISSION::permissionFor('calendarfullaccess')
				|| (array_intersect(['supervisor'], $_SESSION['user']['permissions']) 
				&& array_intersect(explode(',', $row['organization_unit']), $_SESSION['user']['units']))))
				$completed[$this->_lang->GET('calendar.timesheet_approve')]['disabled'] = true;
			$completed_hint = '';
			if ($row['closed']) {
				$completed[$this->_lang->GET('calendar.timesheet_approve')]['checked'] = true;
				$row['closed'] = json_decode($row['closed'], true);
				$completed_hint = $this->_lang->GET('calendar.timesheet_approved_state', [':user' => $row['closed']['user'], ':date' => $row['closed']['date']]);
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
						'value' => $this->_lang->GET('calendar.event_edit'),
						'onpointerup' => $calendar->dialog($columns)
					]
				];
				$events[count($events)-1]['content'][] = [
					'type' => 'deletebutton',
					'attributes' => [
						'value' => $this->_lang->GET('calendar.event_delete'),
						'onpointerup' => "new Dialog({type:'confirm', header:'" . $this->_lang->GET('calendar.event_delete') . "', options:{'" . $this->_lang->GET('general.cancel_button') . "': false, '" . $this->_lang->GET('calendar.event_delete') . "': {'value': true, class: 'reducedCTA'}}})" .
							".then(confirmation => {if (confirmation) api.calendar('delete', 'schedule', " . $row['id'] . "); this.disabled = Boolean(confirmation);});"
					]
				];
			}
		}
		return $events;
	}
}
?>
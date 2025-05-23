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
	 *                   _     _                 _   
	 *   ___ ___ ___ ___|_|___| |_ _____ ___ ___| |_ 
	 *  | .'| . | . | . | |   |  _|     | -_|   |  _|
	 *  |__,|  _|  _|___|_|_|_|_| |_|_|_|___|_|_|_|  
	 *      |_| |_|
	 * display a form to create an appointment handout and ics file
	 */
	public function appointment(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (count((array_keys((array) $this->_payload)))){
					require_once('./_pdf.php');
					$downloadfiles = [];
					$PDF = new PDF(CONFIG['pdf']['appointment']);

					$appointment = [];
					foreach([
						'date',
						'time',
						'occasion',
						'reminder',
						'duration'
					] as $key){
						$appointment[$key] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.appointment.' . $key)) ? : '';
					}

					if (!$appointment['date'] || !$appointment['time'])	$this->response([], 406);

					if ($appointment['reminder'] && !str_ends_with($appointment['reminder'], '.')) $appointment['reminder'].= '.';
					$appointment['reminder'] .= ' ' . $this->_lang->GET('calendar.appointment.reminder_default', [], true);

					$ics = "BEGIN:VCALENDAR\n".
					"PRODID:-//" . CONFIG['system']['caroapp'] . "//CALENDAR//EN\n" .
					"VERSION:2.0\n" .
					"BEGIN:VEVENT\n" .
					"UID:" . implode('-', str_split(md5(CONFIG['system']['caroapp'] . time()), 5)) . "\n" .
					"CREATED:" . date('Ymd\THis') . "\n" .
					"DTSTAMP:" . date('Ymd\THis') . "\n" .
					"DTSTART:" . str_replace('-', '', $appointment['date']) . 'T' . str_replace(':', '', $appointment['time']) . "00\n" .
					"DTEND:" . date("Ymd\THis", strtotime($appointment['date'] . ' ' . $appointment['time']) + intval($appointment['duration']) * 3600) . "\n" .
					wordwrap("DESCRIPTION:" . $appointment['reminder'], 75, "\n ") . "\n" .
					wordwrap("SUMMARY:" . $appointment['occasion'], 75, "\n ")  . "\n" .
					"LOCATION:" . $this->_lang->GET('company.address') . "\n" .
					"CONTACT:" . $this->_lang->GET('company.phone') . "\n" .
					"ORGANIZER:" . $this->_lang->GET('company.mail') . "\n" .
					"END:VEVENT\n" .
					"END:VCALENDAR";

					$content = [
						'title' => $this->_lang->GET('calendar.appointment.title', [], true),
						'date' => $this->dateFormat($this->_date['current']->format('Y-m-d'), true),
						'content' => [
							$ics,
							$this->_lang->GET('calendar.appointment.readable', [
								':company' => $this->_lang->GET('company.address'),
								':occasion' => $appointment['occasion'],
								':start' => $this->dateFormat($appointment['date'] . ' ' . $appointment['time'], true),
								':end' => $this->dateFormat(date("Y-m-d H:i", strtotime($appointment['date'] . ' ' . $appointment['time']) + intval($appointment['duration']) * 3600), true),
								':reminder' => $appointment['reminder'],
								':phone' => $this->_lang->GET('company.phone'),
								':mail' => $this->_lang->GET('company.mail')
							], true)
						],
						'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $this->_lang->GET('calendar.appointment.pdf', [], true) . ' ' . $appointment['occasion'] . ' ' . $this->dateFormat($appointment['date'] . ' ' . $appointment['time'], true))
					];
					$downloadfiles[$this->_lang->GET('calendar.appointment.pdf')] = [
						'href' => './api/api.php/file/stream/' . $PDF->qrcodePDF($content)
					];

					// add ics file to send by mail
					$tempFile = UTILITY::directory('tmp') . '/' . $this->_lang->GET('calendar.appointment.ics', [], true) . ' ' . $appointment['occasion'] . ' ' . $this->dateFormat($appointment['date'] . ' ' . $appointment['time'], true) . '.ics';
					$file = fopen($tempFile, 'w');
					fwrite($file, $ics);
					fclose($file);
					// provide downloadfile
					$downloadfiles[$this->_lang->GET('calendar.appointment.ics')] = [
						'href' => './api/api.php/file/stream/' . substr(UTILITY::directory('tmp'), 1) . '/' . pathinfo($tempFile)['basename'],
						'download' => $this->_lang->GET('calendar.appointment.ics', [], true) . ' ' . $appointment['occasion'] . ' ' . $this->dateFormat($appointment['date'] . ' ' . $appointment['time'], true) . '.ics'
					];

					$body = [
						[
							'type' => 'links',
							'description' => $this->_lang->GET('calendar.appointment.download'),
							'content' => $downloadfiles
						]
					];
					$this->response([
						'render' => $body
					]);
				}
				$this->response([], 406);
				break;
			case "GET":
				// i'll leave payload options here, maybe there is a future use to call this with get parameters as preparation.
				$response = [
					'render' => [
						'form' => [
							'data-usecase' => 'appointment',
							'action' => "javascript:api.calendar('post', 'appointment')"	
						],
						'content' => [
							[
								[
									'type' => 'date',
									'attributes' => [
										'name' => $this->_lang->GET('calendar.appointment.date'),
										'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.appointment.date')) ? : ''
									]
								], [
									'type' => 'time',
									'attributes' => [
										'name' => $this->_lang->GET('calendar.appointment.time'),
										'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.appointment.time')) ? : ''
									]
								], [
									'type' => 'text',
									'hint' => $this->_lang->GET('calendar.appointment.occasion_hint'),
									'attributes' => [
										'name' => $this->_lang->GET('calendar.appointment.occasion'),
										'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.appointment.occasion')) ? : ''
									]
								], [
									'type' => 'text',
									'hint' => $this->_lang->GET('calendar.appointment.reminder_hint'),
									'attributes' => [
										'name' => $this->_lang->GET('calendar.appointment.reminder'),
										'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.appointment.reminder')) ? : ''
									]
								], [
									'type' => 'number',
									'attributes' => [
										'name' => $this->_lang->GET('calendar.appointment.duration'),
										'min' => 1,
										'max' => 200,
										'step' => 1,
										'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.appointment.duration')) ? : 1
									]
								]
							]
						]
					]
				];
				$this->response($response);
				break;
		}
		$this->response([], 400);
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
		$calendarentry = SQLQUERY::EXECUTE($this->_pdo, 'calendar_get_by_id', [
			'replacements' => [
				':id' => $this->_requestedId
			]
		]);
		$calendarentry = $calendarentry ? $calendarentry[0] : null;
		if (!$calendarentry) $this->response([], 404);

		if ($this->_requestedCalendarType === 'timesheet'
			&& !(PERMISSION::permissionFor('calendarfullaccess')
			|| (array_intersect(['supervisor'], $_SESSION['user']['permissions']) 
			&& array_intersect(explode(',', $calendarentry['organizational_unit']), $_SESSION['user']['units'])))) $this->response([], 401);
		// early preparation of responses
		$response = [
			'schedule' => [
				0 => $this->_lang->GET('calendar.schedule.incompleted'),
				1 => $this->_lang->GET('calendar.schedule.completed')
			],
			'timesheet' => [
				0 => $this->_lang->GET('calendar.timesheet.disapproved'),
				1 => $this->_lang->GET('calendar.timesheet.approved')
			],
		];
		$alert = null;
		if ($this->_requestedCalendarType === 'schedule') $alert = intval($response[$this->_requestedCalendarType][intval($this->_requestedComplete === 'true')]);

		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
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
				'msg' => $this->_lang->GET('calendar.schedule.not_found'),
				'type' => 'error'
			]]);
	}

	/**
	 *   _             _                     _             _         
	 *  | |___ ___ ___| |_ ___ ___ _____ ___| |___ ___ ___|_|___ ___ 
	 *  | | . |   | . |  _| -_|  _|     | . | | .'|   |   | |   | . |
	 *  |_|___|_|_|_  |_| |___|_| |_|_|_|  _|_|__,|_|_|_|_|_|_|_|_  |
	 *            |___|                 |_|                     |___|
	 * handle long term planning
	 * post either displays half prepared planning form or adds entry to calendar-db
	 * get displays init form if permitted, selection and selected plan otherwise
	 * delete removes entry
	 * 
	 * responds with render data for assemble.js
	 */

	 public function longtermplanning(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!PERMISSION::permissionFor('longtermplanning')) $this->response([], 401);
				$result = ['render' => [
					'form' => [
						'data-usecase' => 'longtermplanning',
						'action' => "javascript:api.calendar('post', 'longtermplanning')"
					],
					'content' => []]
				];

				// new planning
				if (isset($this->_payload->{$this->_lang->PROPERTY('calendar.longtermplanning.select')})){
					$start = new DateTime(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.longtermplanning.start')), new DateTimeZone($this->_date['timezone']));
					$start->modify('first day of this month');
					$end = new DateTime(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.longtermplanning.end')), new DateTimeZone($this->_date['timezone']));
					$end->modify('last day of this month');
					$span = $start->diff($end)->format('%m') * 2; // half months
					if ($span < 2) $this->response([], 406);

					// import if requested
					$schedule = null;
					$import = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.longtermplanning.import'));
					if ($import && $import > 0) {
						$schedule = SQLQUERY::EXECUTE($this->_pdo, 'calendar_get_by_id', ['replacements' => [':id' => $import]]);
					}
					$schedule = $schedule ? $schedule[0] : ['misc' => ''];
					$schedule['misc'] = json_decode($schedule['misc'], true);
					
					// create default empty timeunits for selected timespan
					$defaulttimeunits = [];
					while ($start < $end){
						$defaulttimeunits[$start->format('y-m-d')] = null;
						$start->modify('+' . (floor($start->format('t') / 2)) . ' day'); // add approximately half a month
						$defaulttimeunits[$start->format('y-m-d')] = null;
						$start->modify('+' . ($start->format('t') - $start->format('d') + 1) . ' day'); // add rest to the next first day of next month 
					}
					
					// import if available, do first to append new given names
					if (isset($schedule['misc']['content'])){
						foreach($schedule['misc']['content'] as $name => $importtimeunit){
							$imports = [];
							foreach($defaulttimeunits as $label => $color){
								$imports[$label] = isset($importtimeunit[$label]) ? $importtimeunit[$label] : $color;
							}
							$content[$name] = $imports;
						}
					}
					// create default content with requested names assigning default timeunits
					$content = [];
					foreach($this->_payload as $key => $value){
						if (str_starts_with($key, $this->_lang->PROPERTY('calendar.longtermplanning.name')) && $value){
							$content[$value] = $defaulttimeunits;
						}
					}
					if (!$content) $this->response([], 406);

					$result['render']['content'][] = [
						[
							'type' => 'longtermplanning_timeline',
							'attributes' => [
								'name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.longtermplanning.subject'))
							],
							'content' => $content,
						], [
							'type' => 'checkbox',
							'content' => [
								$this->_lang->GET('calendar.longtermplanning.closed') => ['id' => '_longtermclosed']
							],
							'hint' => $this->_lang->GET('calendar.longtermplanning.closed_hint')
						], [
							'type' => 'hidden',
							'attributes' => [
								'name' => '_longtermid'
							]
						]
					];
					$result['render']['content'][] = [
						[
							'type' => 'longtermplanning_topics',
							'content' => isset($schedule['misc']['preset']) ? $schedule['misc']['preset'] : null
						]
					];
				} 
				// store planning
				else {
					// payload prepared by _client.calendar.longtermplanning()
					$content = json_decode($this->_payload->content, true);
					$preset = json_decode($this->_payload->preset, true);
					$id = $this->_payload->id;
					$closed = intval($this->_payload->closed) ? UTILITY::json_encode(['user' => $_SESSION['user']['name'], 'date' => $this->_date['current']->format('Y-m-d')]) : null;
					if (!$content) $this->response([], 406);

					if (intval($id) > 0){
						// update
						$columns = [
							':id' => $id,
							':span_start' => '20' . array_key_first($content[array_key_first($content)]) . ' 00:00:00', // first nesting is the affected name. add 20 to adhere to proper date format
							':span_end' => '20' . array_key_last($content[array_key_first($content)]) . ' 23:59:59',
							':author_id' => $_SESSION['user']['id'],
							':affected_user_id' => null,
							':organizational_unit' => null,
							':subject' => $this->_payload->name,
							':misc' => UTILITY::json_encode(['content' => $content, 'preset' => $preset]),
							':closed' => $closed,
							':alert' => null
						];
						if (SQLQUERY::EXECUTE($this->_pdo, 'calendar_put', [
							'values' => $columns
						])) $this->response([
							'response' => [
								'msg' => $this->_lang->GET('calendar.longtermplanning.save_success'),
								'type' => 'success'
							]]);
					} else {
						// post
						$columns = [
							':type' => 'longtermplanning',
							':span_start' => '20' . array_key_first($content[array_key_first($content)]) . ' 00:00:00', // first nesting is the affected name. add 20 to adhere to proper date format
							':span_end' => '20' . array_key_last($content[array_key_first($content)]) . ' 23:59:59',
							':author_id' => $_SESSION['user']['id'],
							':affected_user_id' => null,
							':organizational_unit' => null,
							':subject' => $this->_payload->name,
							':misc' => UTILITY::json_encode(['content' => $content, 'preset' => $preset]),
							':closed' => $closed,
							':alert' => null
						];
						if (SQLQUERY::EXECUTE($this->_pdo, 'calendar_post', [
							'values' => $columns
						])) $this->response([
							'response' => [
								'id' => $this->_pdo->lastInsertId(),
								'msg' => $this->_lang->GET('calendar.longtermplanning.save_success'),
								'type' => 'success'
							]]);
					}

					$this->response([
						'response' => [
							'msg' => $this->_lang->GET('calendar.longtermplanning.save_error'),
							'type' => 'error'
						]
					]);
				}
				break;
			case 'GET':
				$result = ['render' => [
					'content' => []]
				];
				if (PERMISSION::permissionFor('longtermplanning')) $result['render']['form'] = [
						'data-usecase' => 'longtermplanning',
						'action' => "javascript:api.calendar('post', 'longtermplanning')"
					];
				$select = [
					'edit' => [
						'...' => ['value' => '0']
					],
					'import' =>  [
						'...' => ['value' => '0']
					]
				];
				$schedules = SQLQUERY::EXECUTE($this->_pdo, 'calendar_get_type', ['values' => [':type' => 'longtermplanning']]);
				// sort by closed date desc since sql query sorts different
				usort($schedules, function ($a, $b) {
					$a['closed'] = json_decode($a['closed'] ? : '', true);
					if (!isset($a['closed']['date'])) $a['closed']['date'] = 0;
					$b['closed'] = json_decode($b['closed'] ? : '', true);
					if (!isset($b['closed']['date'])) $b['closed']['date'] = 0;
					return $a['closed']['date'] === $b['closed']['date'] ? 0 : ($a['closed']['date'] < $b['closed']['date'] ? 1 : -1); 
				});
				foreach($schedules as $schedule){
					if (!PERMISSION::permissionFor('longtermplanning') && !$schedule['closed']) continue;
					$schedule['closed'] = json_decode($schedule['closed'] ? : '', true);
					$select['edit'][$schedule['subject'] . (isset($schedule['closed']['date']) ? (' - ' . $this->dateFormat($schedule['closed']['date'])) :'')] = $schedule['id'] === $this->_requestedId ? ['value' => $schedule['id'], 'selected' => true] : ['value' => $schedule['id']];
					if ($schedule['span_end'] > $this->_date['current']->format('Y-m-d H:i:s')) $select['import'][$schedule['subject']] = ['value' => $schedule['id']];
				}

				$result['render']['content'][] = [
					[
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('calendar.longtermplanning.select'),
							'onchange' => "if (this.value !== '0') api.calendar('get', 'longtermplanning', this.value);"
						],
						'content' => $select['edit']
					]
				];
				if ($this->_requestedId){
					$planning = $schedules[array_search($this->_requestedId, array_column($schedules, 'id'))];
					if (!$planning) $this->response([], 404);
					$misc = json_decode($planning['misc'], true);
					$result['render']['content'][] = [
						[
							'type' => 'longtermplanning_timeline',
							'attributes' => [
								'name' => $planning['subject'],
								//'readonly' => $planning['closed'] this would result in an error for not creating a proper payload content object without inputs
							],
							'content' => $misc['content'],
						], [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('calendar.longtermplanning.author', [':author' => $planning['author']])
							]
						]
					];
					$result['render']['content'][] = [
						[
							'type' => 'longtermplanning_topics',
							'attributes' => [
								//'readonly' => $planning['closed'] this would result in an error for not creating a proper payload content object without inputs
							],
							'content' => $misc['preset']
						]
					];
					if (PERMISSION::permissionFor('longtermplanning')){
						array_splice($result['render']['content'][count($result['render']['content']) - 2], -1, 0 , [
							[
								'type' => 'checkbox',
								'content' => [
									$this->_lang->GET('calendar.longtermplanning.closed') => $planning['closed'] ? ['id' => '_longtermclosed', 'checked' => true] : ['id' => '_longtermclosed']
								],
								'hint' => $this->_lang->GET('calendar.longtermplanning.closed_hint')
							], [
								'type' => 'deletebutton',
								'attributes' => [
									'value' => $this->_lang->GET('calendar.longtermplanning.delete'),
									'type' => 'button',
									'onclick' => "new _client.Dialog({type:'confirm', header:'" . $this->_lang->GET('calendar.longtermplanning.delete') . "', options:{'" . $this->_lang->GET('general.cancel_button') . "': false, '" . $this->_lang->GET('general.ok_button') . "': {'value': true, class: 'reducedCTA'}}})" .
										".then(confirmation => {if (confirmation) api.calendar('delete', 'longtermplanning', " . $planning['id'] . "); this.disabled = Boolean(confirmation);});"
								]
							], [
								'type' => 'hidden',
								'attributes' => [
									'name' => '_longtermid',
									'value' => $planning['id']
								]
							]
						]);
					} else {
						$result['render']['content'][count($result['render']['content']) - 2][0]['attributes']['readonly'] = $result['render']['content'][count($result['render']['content']) - 1][0]['attributes']['readonly'] = true;
					}
				}
				else {
					if (PERMISSION::permissionFor('longtermplanning')){
						$result['render']['content'][] = [
							[
								'type' => 'text',
								'attributes' => [
									'name' => $this->_lang->GET('calendar.longtermplanning.subject'),
									'required' => true
								]
							], [
								'type' => 'date',
								'attributes' => [
									'name' => $this->_lang->GET('calendar.longtermplanning.start'),
									'required' => true,
									'onchange' => "let start = new Date(this.value), minend; if (!start) return; minend = new Date(start.setDate(start.getDate() + 7 * 4)); document.getElementById('_spanend').min = minend.toISOString().substring(0, 10);"
								]
							], [
								'type' => 'date',
								'attributes' => [
									'name' => $this->_lang->GET('calendar.longtermplanning.end'),
									'id' => '_spanend',
									'required' => true
								],
								'hint' => $this->_lang->GET('calendar.longtermplanning.new_hint')
							], [
								'type' => 'text',
								'attributes' => [
									'name' => $this->_lang->GET('calendar.longtermplanning.name'),
									'multiple' => true
								]
							]
						];
						if (count($select['import']) > 1){
							$result['render']['content'][count($result['render']['content']) - 1][] = [
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('calendar.longtermplanning.import')
								],
								'content' => $select['import']
							];
						}
					}
				}
				break;
			case 'DELETE':
				if (!PERMISSION::permissionFor('longtermplanning')) $this->response([], 401);
				if (SQLQUERY::EXECUTE($this->_pdo, 'calendar_delete', [
					'values' => [
						':id' => $this->_requestedId
					]
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('calendar.longtermplanning.delete_success'),
						'type' => 'deleted'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('calendar.longtermplanning.delete_error'),
						'type' => 'error'
					]]);
				break;
		}
		$this->response($result);
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
		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
		// set up calendar
		$calendar->days('month', $this->_requestedTimespan);
		$holidays = $calendar->holidays(substr($this->_requestedTimespan, 0, 4));
		$days = $calendar->_days;
		$first = $last = '';

		/**
		 * calculates e.g. 5:45 to 5.75 hours
		 * @param string $string
		 * @return float
		 */
		function timeStrToFloat($string){
			$string = explode(':', $string);
			return intval($string[0]) + (intval($string[1]) / 60);
		}

		// set $first day of month to datetime object of first calendar-day
		foreach($days as $id => $day){
			if ($day === null) unset($days[$id]);
			else {
				$first = clone $day;
				break;
			}
		}
		// reset days array indexes
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

				// add summaries to user if not already set
				if (!isset($timesheets[$entry['affected_user_id']])) {
					$units = array_map(Fn($u) => $this->_lang->_DEFAULT['units'][$u], explode(',', $entry['affected_user_units']));
					$pto = [];
					foreach($this->_lang->_DEFAULT['calendar']['timesheet']['pto'] as $key => $translation){
						if (isset($stats_month_row[$key])) $pto[$key] = $stats_month_row[$key];
					}
					$timesheets[$entry['affected_user_id']] = [
						'name' => $entry['affected_user'],
						'user_id' => $entry['affected_user_id'],
						'units' => implode(', ', $units),
						'month' => $this->_lang->_DEFAULT['general']['month'][$day->format('n')] . ' ' . $day->format('Y'),
						'days' => [],
						'pto' => $pto,
						'performed' => round(floatval($stats_month_row['_performed']), 2),
						'projected' => round(floatval($stats_month_row['_projected']), 2),
						'weeklyhours' => $stats_month_row['_span_end_weeklyhours'],
						'leftvacation' => $stats_all_row['_leftvacation'],
						'overtime' => round(floatval($stats_all_row['_overtime'] - $stats_all_row['_initialovertime']), 2),
						'monthlyovertime' => round(floatval($stats_month_row['_overtime']), 2)
					];
				}
				
				$span_start = new DateTime($entry['span_start'], new DateTimeZone($this->_date['timezone']));
				$span_end = new DateTime($entry['span_end'], new DateTimeZone($this->_date['timezone']));
				if (($span_start <= $day || $span_start->format('Y-m-d') === $day->format('Y-m-d'))
					&& ($day <= $span_end || $span_end->format('Y-m-d') === $day->format('Y-m-d'))
					&& !isset($timesheets[$entry['affected_user_id']]['days'][$day->format('Y-m-d')])){
					
					// calculate hours for stored regular working days only
					$misc = json_decode($entry['misc'], true);
					if (!$entry['subject'] || !strlen($entry['subject'])) {
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
							'workinghourscorrection' => isset($misc['workinghourscorrection']) ? $misc['workinghourscorrection'] : '',
							'note' => isset($misc['note']) ? $misc['note'] : '',
							'hours' => round($dailyhours, 2),
						];
					}

					// else state subject
					else $timesheets[$entry['affected_user_id']]['days'][$day->format('Y-m-d')] = ['subject' => $this->_lang->_DEFAULT['calendar']['timesheet']['pto'][$entry['subject']], 'note' => isset($misc['note']) ? $misc['note'] : ''];
				}
			}
		}
		// postprocess array
		foreach($timesheets as $id => $user){
			// append missing dates for overview, after all the output shall be comprehensible
			foreach ($days as $day){
				if (!isset($user['days'][$day->format('Y-m-d')])) $timesheets[$id]['days'][$day->format('Y-m-d')] = [];
				$timesheets[$id]['days'][$day->format('Y-m-d')]['weekday'] = $this->_lang->_DEFAULT['general']['weekday'][$day->format('N')];
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
			'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $this->_lang->GET('menu.calendar.timesheet', [], true) . '_' . $this->_date['current']->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => $this->prepareTimesheetOutput($timesheets),
			'files' => [],
			'images' => [],
			'title' => $this->_lang->GET('menu.calendar.timesheet', [], true),
			'date' => $this->_date['current']->format('Y-m-d H:i')
		];

		$downloadfiles = [];
		$PDF = new PDF(CONFIG['pdf']['record']);
		$downloadfiles[$this->_lang->GET('menu.calendar.timesheet', [], true)] = [
			'href' => './api/api.php/file/stream/' . $PDF->timesheetPDF($summary)
		];
		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('calendar.timesheet.export.proceed'),
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
	 * 		[
	 * 			[
	 * 				[str username, bool false],
	 * 				str user summary
	 * 			],
	 * 			[], // empty row for style reasons
	 * 			[
	 * 				[str description, bool greyed out], // marked holidays and non working day as per ini
	 * 				str content
	 * 			],
	 * 			...,
	 * 			[
	 * 				[str pto, bool false], // per pto reason
	 * 				str number of pto days
	 * 			]
	 * 			...,
	 * 			[
	 * 				[str summary, bool false],
	 * 				str work hours, overtime and remaining vacation days
	 * 			],
	 * 			[
	 * 				[str signature, bool false],
	 * 				str _____________________
	 * 			]
	 * 		],
	 * 		...
	 * ]
	 */
	private function prepareTimesheetOutput($timesheets = []){
		$result = [];
		foreach($timesheets as $user){
			$rows = [];
			if (PERMISSION::permissionFor('calendarfulltimesheetexport')
				|| (array_intersect(['supervisor'], $_SESSION['user']['permissions']) && array_intersect(explode(',', $user['units']), $_SESSION['user']['units']))
				|| $user['user_id'] === $_SESSION['user']['id']
			){
				// user summary
				$rows[] = [
					[$user['name'], false],
					$this->_lang->GET('calendar.timesheet.export.sheet_subject', [
						':appname' => CONFIG['system']['caroapp'],
						':id' => $user['user_id'],
						':units' => $user['units'],
						':weeklyhours' => $user['weeklyhours'],
					], true)
				];
				$rows[] = [];

				// days
				foreach ($user['days'] as $date => $day){
					$dayinfo = [];
					if (isset($day['subject']) && $day['subject']) $dayinfo[] = $day['subject'];
					foreach($this->_lang->_DEFAULT['calendar']['timesheet']['export']['sheet_daily'] as $key => $value){
						//var_dump($key, $value, $day);
						if (isset($day[$key]) && $day[$key] && !in_array($day[$key], [0, '00:00'])) $dayinfo[] = $value . ' ' . $day[$key];
					}
					if (isset($day['note']) && $day['note']) $dayinfo[] = $day['note'];
					
					$rows[] = [
						[$day['weekday'] . ' ' . $this->dateFormat($date), $day['holiday']],
						implode(', ', $dayinfo)
					];
				}
				$rows[] = [];

				// pto
				foreach ($user['pto'] as $pto => $number){
					$rows[] = [
						[$this->_lang->_DEFAULT['calendar']['timesheet']['pto'][$pto], false],
						$this->_lang->GET('calendar.timesheet.export.sheet_exemption_days', [':number' => $number], true)
					];
				}
				if ($user['pto']) $rows[] = [];
				
				// hours, overtime and remaining vacation days
				$rows[] = [
					[$this->_lang->GET('calendar.timesheet.export.sheet_summary', [], true), false],
					$this->_lang->GET('calendar.timesheet.export.sheet_summary_text', [
						':name' => $user['name'],
						':performed' => $user['performed'],
						':projected' => $user['projected'],
						':month' => $user['month'],
						':overtime' => $user['overtime'],
						':_monthlyovertime' => $user['monthlyovertime'],
						':vacation' => $user['leftvacation'],
					], true)					
				];
				$rows[] = [];

				// signatures
				foreach($this->_lang->_DEFAULT['calendar']['timesheet']['signature'] as $key => $value) $rows[] = [[$value, false], str_repeat('_', 20)];

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
		if (!$this->_requestedId) $this->schedule(); // default view instead of redirect

		// append filter inputs
		$result = ['render' => ['content' => [
			[
				[
					'type' => 'scanner',
					'destination' => 'recordfilter', // assuming/hoping record identifiers are used to schedule events/tasks
				], [
					'type' => 'search',
					'attributes' => [
						'value' => $this->_requestedId,
						'id' => 'recordfilter',
						'name' => $this->_lang->GET('calendar.schedule.search'),
						'onkeydown' => "if (event.key === 'Enter') {api.calendar('get', 'search', encodeURIComponent(this.value))}",
					],
					'hint' => $this->_lang->GET('calendar.schedule.search_hint'),
				]
			]
		]]];
		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
		$dbevents = $calendar->search(urldecode($this->_requestedId || ''));

		// append filtered events
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
		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
		require_once('notification.php');
		$notifications = new NOTIFICATION;

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$affected_user_id = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.affected_user'));
				if (!$affected_user_id || $affected_user_id === '...') $affected_user_id = null;

				// set up event properties
				$event = [
					':type' => 'schedule',
					':span_start' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.date')),
					':span_end' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.due')),
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $affected_user_id,
					':organizational_unit' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.organizational_unit')),
					':subject' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.content')),
					':misc' => null,
					':closed' => null,
					':alert' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.alert')) ? 1 : null
				];
				if (!($event[':span_start'] && $event[':organizational_unit'] && $event[':subject'])) $this->response(['response' => ['msg' => $this->_lang->GET('calendar.schedule.error_missing'), 'type' => 'error']]);

				// default end if not provided
				if (!$event[':span_end']){
					$due = new DateTime($event[':span_start'], new DateTimeZone($this->_date['timezone']));
					$due->modify('+' . CONFIG['calendar']['default_due'] . ' months');
					$event[':span_end'] = $due->format('Y-m-d');	
				}

				// post event
				if ($newid = $calendar->post($event)) $this->response([
					'response' => [
						'id' => $newid,
						'msg' => $this->_lang->GET('calendar.schedule.success'),
						'type' => 'success'
					],
					'data' => ['calendar_uncompletedevents' => $notifications->calendar()]]);
				else $this->response([
					'response' => [
						'id' => false,
						'msg' => $this->_lang->GET('calendar.schedule.error'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				if (!PERMISSION::permissionFor('calendaredit')) $this->response([], 401);
				$affected_user_id = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.affected_user'));
				if (!$affected_user_id || $affected_user_id === '...') $affected_user_id = null;

				// set up event properties from payload
				$event = [
					':id' => UTILITY::propertySet($this->_payload, 'calendarEventId'),
					':span_start' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.date')),
					':span_end' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.due')),
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $affected_user_id,
					':organizational_unit' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.organizational_unit')),
					':subject' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.content')),
					':misc' => null,
					':closed' => null,
					':alert' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.alert')) ? 1 : null
				];
				if (!($event[':span_start'] && $event[':organizational_unit'] && $event[':subject'])) $this->response(['response' => ['msg' => $this->_lang->GET('calendar.schedule.error_missing'), 'type' => 'error']]);

				// default end if not provided
				if (!$event[':span_end']){
					$due = new DateTime($event[':span_start'], new DateTimeZone($this->_date['timezone']));
					$due->modify('+' . CONFIG['calendar']['default_due'] . ' months');
					$event[':span_end'] = $due->format('Y-m-d');	
				}

				// update event
				if ($calendar->put($event)) $this->response([
					'response' => [
						'id' => $event[':id'],
						'msg' => $this->_lang->GET('calendar.schedule.success'),
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
							'msg' => $this->_lang->GET('calendar.schedule.success'),
							'type' => 'success'
						],
						'data' => ['calendar_uncompletedevents' => $notifications->calendar()]]);
					else $this->response([
						'response' => [
							'id' => false,
							'msg' => $this->_lang->GET('calendar.schedule.error'),
							'type' => 'error'
						]]);
					}
				break;
			case 'GET':
				$result = ['render' => ['content' => []]];

				// set up calendar
				$month = $calendar->render('month', 'schedule', $this->_requestedTimespan);
				$previousmonth = clone $calendar->_days[6]; // definetly a date and not a null filler
				$previousmonth->modify('-1 month')->modify('last day of this month');
				$nextmonth = clone $calendar->_days[6];
				$nextmonth->modify('+1 month')->modify('first day of this month');

				// append filter inputs
				$result['render']['content'][] = [
					[
						'type' => 'scanner',
						'destination' => 'recordfilter', // assuming/hoping record identifiers are used to schedule events/tasks
					], [
						'type' => 'search',
						'attributes' => [
							'id' => 'recordfilter',
							'name' => $this->_lang->GET('calendar.schedule.search'),
							'onkeydown' => "if (event.key === 'Enter') {api.calendar('get', 'search', encodeURIComponent(this.value))}",
						],
						'hint' => $this->_lang->GET('calendar.schedule.search_hint'),
					]
				];

				// append month overview and navigation buttons
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
							'onclick' => "api.calendar('get', 'schedule', '" . $previousmonth->format('Y-m-d') . "', '" . $previousmonth->format('Y-m-d') . "')",
							'data-type' => 'toleft'
						]
					],
					[
						'type' => 'button',
						'attributes' => [
							'value' => $this->_lang->GET('calendar.month_next') . ' ',
							'type' => 'button',
							'onclick' => "api.calendar('get', 'schedule', '" . $nextmonth->format('Y-m-d') . "', '" . $nextmonth->format('Y-m-d') . "')",
							'data-type' => 'toright'
						]
					],
				];
				// default requestedDate as today
				if (!$this->_requestedDate){
					$today = $this->_date['current'];
					$this->_requestedDate = $today->format('Y-m-d');
				}

				$events = [];
				$displayabsentmates = '';

				// set up default calendar dialog properties
				$columns = [
					':type' => 'schedule',
					':span_start' => $this->_requestedDate,
				];

				// gather events for requested date
				$thisDaysEvents = $calendar->getDay($this->_requestedDate);
				foreach ($thisDaysEvents as $id => $row){
					if (!$row['affected_user']) $row['affected_user'] = $this->_lang->GET('message.deleted_user'); // fallback message
					if ($row['type'] === 'timesheet' && !in_array($row['subject'], CONFIG['calendar']['hide_offduty_reasons']) && array_intersect(explode(',', $row['affected_user_units']), ['common', ...$_SESSION['user']['units']])) $displayabsentmates .= "* " . $row['affected_user'] . " ". $this->_lang->_USER['calendar']['timesheet']['pto'][$row['subject']] . " ". $this->dateFormat(substr($row['span_start'], 0, 10)) . " - ". $this->dateFormat(substr($row['span_end'], 0, 10)) . "\n";
				}

				// add absent mates
				$events[] = [
					'type' => 'textsection',
					'content' => $displayabsentmates,
					'attributes' => [
						'data-type' => 'calendar',
						'name' => $this->dateFormat($this->_requestedDate)
					]
				];

				// add button for new event
				$events[] = [
					'type' => 'calendarbutton',
					'attributes' => [
						'value' => $this->_lang->GET('calendar.schedule.new'),
						'onclick' => $calendar->dialog($columns)
					]
				];

				// add events
				if ($thisDaysEvents) array_push($events, ...$this->scheduledEvents($thisDaysEvents, $calendar));
				$result['render']['content'][] = $events;

				// add past unclosed events for user units
				$today = new DateTime($this->_requestedDate, new DateTimeZone($this->_date['timezone']));
				$pastEvents = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
				if ($pastEvents) {
					$uncompleted = [];
					foreach ($pastEvents as $id => $row){
						if (!$row['organizational_unit']) $row['organizational_unit'] = ''; 
						if (in_array($row, $thisDaysEvents) || $row['type'] !== 'schedule' || !array_intersect(explode(',', $row['organizational_unit']), ['common', ...$_SESSION['user']['units']]) || $row['closed']) unset($pastEvents[$id]);
					}
					if ($pastEvents){
						$events = [
							[
								'type' => 'textsection',
								'attributes' => [
									'data-type' => 'calendar',
									'name' => $this->_lang->GET('calendar.schedule.events_assigned_units_uncompleted')
								]
							]
						];
						array_push($events, ...$this->scheduledEvents($pastEvents, $calendar));
						$result['render']['content'][] = $events;	
					}
				}
				break;
			case 'DELETE':
				if (!PERMISSION::permissionFor('calendaredit')) $this->response([], 401);
				if ($calendar->delete($this->_requestedId)) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('calendar.schedule.deleted'),
						'type' => 'deleted'
					],
					'data' => ['calendar_uncompletedevents' => $notifications->calendar()]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('calendar.schedule.not_found'),
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
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist'); // to eventually match affected_user_id

		foreach($dbevents as $row){
			$date = new DateTime($row['span_start'], new DateTimeZone($this->_date['timezone']));
			$due = new DateTime($row['span_end'], new DateTimeZone($this->_date['timezone']));
			if (!$row['organizational_unit']) $row['organizational_unit'] = ''; 
			if ((!array_intersect(explode(',', $row['organizational_unit']), ['common', ...$_SESSION['user']['units']]) && !in_array($_SESSION['user']['id'], [$row['author_id'], $row['affected_user_id']])) || $row['type'] !== 'schedule' ) continue; // skip not schedule and not user unit affecting

			// construct event information
			$display = $this->_lang->GET('calendar.schedule.date') . ': ' . $this->dateFormat($date->format('Y-m-d')) . "\n" .
				$this->_lang->GET('calendar.schedule.due') . ': ' . $this->dateFormat($due->format('Y-m-d')) . "\n";
			$display .= implode(', ', array_map(Fn($unit) => $this->_lang->_USER['units'][$unit], explode(',', $row['organizational_unit'])));
			if ($row['affected_user_id'] && $userrow = array_search($row['affected_user_id'], array_column($users, 'id'))){
				$display .= "\n" . $users[$userrow]['name'];
			}

			// replace deleted user names
			if (!$row['author']) $row['author'] = $this->_lang->GET('message.deleted_user');
			if (!$row['affected_user']) $row['affected_user'] = $this->_lang->GET('message.deleted_user');

			// construct complete information
			$completed[$this->_lang->GET('calendar.schedule.complete')] = ['onchange' => "api.calendar('put', 'complete', '" . $row['id'] . "', this.checked, 'schedule')"];
			$completed_hint = '';
			if ($row['closed']) {
				$completed[$this->_lang->GET('calendar.schedule.complete')]['checked'] = true;
				$row['closed'] = json_decode($row['closed'], true);
				$completed_hint = $this->_lang->GET('calendar.schedule.completed_state', [':user' => $row['closed']['user'], ':date' => $this->dateFormat($row['closed']['date'])]);
			}

			// add event tile
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
				// prepare information to import to dialog
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

				// add edit button
				$events[count($events)-1]['content'][] = [
					'type' => 'button',
					'attributes' => [
						'type' => 'button',
						'value' => $this->_lang->GET('calendar.schedule.edit'),
						'onclick' => $calendar->dialog($columns)
					],
					'hint' => $this->_lang->GET('calendar.schedule.author') . ': ' . $row['author']
				];

				// add delete button
				$events[count($events)-1]['content'][] = [
					'type' => 'deletebutton',
					'attributes' => [
						'value' => $this->_lang->GET('calendar.schedule.delete'),
						'onclick' => "new _client.Dialog({type:'confirm', header:'" . $this->_lang->GET('calendar.schedule.delete') . " " . $row['subject'] . "', options:{'" . $this->_lang->GET('general.cancel_button') . "': false, '" . $this->_lang->GET('calendar.schedule.delete') . "': {'value': true, class: 'reducedCTA'}}})" .
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
		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$affected_user_id = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.affected_user'));
				if (!$affected_user_id || $affected_user_id === '...') $affected_user_id = $_SESSION['user']['id']; // if not selected default to current user!
				if ($affected_user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => $affected_user_id,
						':name' => ''
					]
				])) $affected_user = $affected_user[0];
				
				// set up event properties
				$event = [
					':type' => 'timesheet',
					':span_start' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.start_date')) . ' ' . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.start_time')) . ':00',
					':span_end' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.end_date')) . ' ' . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.end_time')) . ':00',
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $affected_user['id'],
					':organizational_unit' => null,
					':subject' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.pto_exemption')) ? : null,
					':misc' => null,
					':closed' => null,
					':alert' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.alert')) ? 1 : null
				];
				if ($event[':subject'] === 'regular') $event[':subject'] = null;

				// construct daily information
				$misc = $event[':subject'] ? [] : [
					'note' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.pto_note')) ? : '',
					'break' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.break_time'))
				];
				if ($workinghourscorrection = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.pto.workinghourscorrection'))){
					$misc['workinghourscorrection'] = $workinghourscorrection;
				}
				if ($homeoffice = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.homeoffice'))){
					$misc['homeoffice'] = $homeoffice;
				}
				$event[':misc'] = UTILITY::json_encode($misc);

				// match required properties
				if (!($event[':span_start'] && $event[':span_end'] && (isset($misc['break']) || $event[':subject']))) $this->response(['response' => ['msg' => $this->_lang->GET('calendar.timesheet.error_missing'), 'type' => 'error']]);

				// post timesheet event
				if ($newid = $calendar->post($event)) $this->response([
					'response' => [
						'id' => $newid,
						'msg' => $this->_lang->GET('calendar.schedule.success'),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => false,
						'msg' => $this->_lang->GET('calendar.schedule.error'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				// editing of timesheet entries is allowed for admin and affected user for regulatory security only
				$affected_user_id = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.affected_user'));
				if (!$affected_user_id || $affected_user_id === '...') $affected_user_id = $_SESSION['user']['id'];
				if ($affected_user = SQLQUERY::EXECUTE($this->_pdo, 'user_get', [
					'replacements' => [
						':id' => $affected_user_id,
						':name' => ''
					]
				])) $affected_user = $affected_user[0];

				$calendarentry = SQLQUERY::EXECUTE($this->_pdo, 'calendar_get_by_id', [
					'replacements' => [
						':id' => UTILITY::propertySet($this->_payload, 'calendarEventId')
					]
				]);
				$calendarentry = $calendarentry ? $calendarentry[0] : null;
				if (!$calendarentry) $this->response([], 404);
				
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']) || 
				($affected_user['id'] === $_SESSION['user']['id'] && !$calendarentry['closed']))
				) $this->response([], 401);
				
				// set up event properties with payload
				$event = [
					':id' => UTILITY::propertySet($this->_payload, 'calendarEventId'),
					':span_start' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.start_date')) . ' ' . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.start_time')) . ':00',
					':span_end' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.end_date')) . ' ' . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.end_time')) . ':00',
					':author_id' => $_SESSION['user']['id'],
					':affected_user_id' => $affected_user['id'],
					':organizational_unit' => null,
					':subject' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.pto_exemption')) ? : null,
					':misc' => null,
					':closed' => null,
					':alert' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.schedule.alert')) ? 1 : null
				];
				if ($event[':subject'] === 'regular') $event[':subject'] = '';

				// construct daily information
				$misc = $event[':subject'] ? [] : [
					'note' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.pto_note')) ? : '',
					'break' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.break_time'))
				];
				if ($workinghourscorrection = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.pto.workinghourscorrection'))){
					$misc['workinghourscorrection'] = $workinghourscorrection;
				}
				if ($homeoffice = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('calendar.timesheet.homeoffice'))){
					$misc['homeoffice'] = $homeoffice;
				}
				$event[':misc'] = UTILITY::json_encode($misc);

				// match required properties
				if (!($event[':span_start'] && $event[':span_end'] && (isset($misc['break']) || $event[':subject']))) $this->response(['response' => ['msg' => $this->_lang->GET('calendar.timesheet.error_missing'), 'type' => 'error']]);

				// update timesheet event
				if ($calendar->put($event)) $this->response([
					'response' => [
						'id' => $event[':id'],
						'msg' => $this->_lang->GET('calendar.schedule.success'),
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
							'msg' => $this->_lang->GET('calendar.schedule.success'),
							'type' => 'success'
						]]);
					else $this->response([
						'response' => [
							'id' => false,
							'msg' => $this->_lang->GET('calendar.schedule.error'),
							'type' => 'error'
						]]);
					}
				break;
			case 'GET':
				$result = ['render' => ['content' => []]];
				// set up calendar
				$month = $calendar->render('month', 'timesheet', $this->_requestedTimespan);
				$previousmonth = clone $calendar->_days[6]; // definetly a date and not a null filler
				$previousmonth->modify('-1 month')->modify('last day of this month');
				$nextmonth = clone $calendar->_days[6];
				$nextmonth->modify('+1 month')->modify('first day of this month');

				// append month overview and navigation buttons
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
							'onclick' => "api.calendar('get', 'timesheet', '" . $previousmonth->format('Y-m-d') . "', '" . $previousmonth->format('Y-m-d') . "')",
							'data-type' => 'toleft'
						]
					],
					[
						'type' => 'button',
						'attributes' => [
							'value' => $this->_lang->GET('calendar.month_next') . ' ',
							'type' => 'button',
							'onclick' => "api.calendar('get', 'timesheet', '" . $nextmonth->format('Y-m-d') . "', '" . $nextmonth->format('Y-m-d') . "')",
							'data-type' => 'toright'
						]
					],
				];

				// default requestedDate as today
				if (!$this->_requestedDate){
					$today = $this->_date['current'];
					$this->_requestedDate = $today->format('Y-m-d');
				}

				$events = $bulkapproval = [];
				$displayabsentmates = '';

				// set up default calendar dialog properties
				$columns = [
					':type' => 'timesheet',
					':span_start' => $this->_requestedDate,
				];

				// gather events for requested date
				$thisDaysEvents = $calendar->getDay($this->_requestedDate);
				foreach ($thisDaysEvents as $id => $row){
					if (!$row['affected_user_units']) $row['affected_user_units'] = ''; 
					$row['affected_user_units'] = explode(',', $row['affected_user_units']);
					if (!$row['affected_user']) $row['affected_user'] = $this->_lang->GET('message.deleted_user');

					// display absent workmates for own units
					if ($row['type'] === 'timesheet'
						&& !in_array($row['subject'], CONFIG['calendar']['hide_offduty_reasons'])
						&& array_intersect($row['affected_user_units'], ['common', ...$_SESSION['user']['units']])) $displayabsentmates .= "* " . $row['affected_user'] . " ". $this->_lang->_USER['calendar']['timesheet']['pto'][$row['subject']] . " ". $this->dateFormat(substr($row['span_start'], 0, 10)) . " - ". $this->dateFormat(substr($row['span_end'], 0, 10)) . "\n";
					
					// allow approval for fullaccess and supervisors of affected user units
					if ($row['type'] === 'timesheet'
						&& !$row['closed']
						&& (PERMISSION::permissionFor('calendarfullaccess')
						|| (array_intersect(['supervisor'], $_SESSION['user']['permissions']) && array_intersect($row['affected_user_units'], $_SESSION['user']['units'])))
						) $bulkapproval[] = $row['id'];
				}

				// add header and absent mates if applicable
				$events[] = [
					'type' => 'textsection',
					'content' => $displayabsentmates,
					'attributes' => [
						'data-type' => 'calendar',
						'name' => $this->dateFormat($this->_requestedDate)
					]
				];

				// gather todays events
				$displayedEvents = [];
				if ($thisDaysEvents) $displayedEvents = $this->timesheetEntries($thisDaysEvents, $calendar);
				// avoid multiple entries by non authorized users
				if (!$displayedEvents // current user can contribute one own event per day
					|| PERMISSION::permissionFor('calendarfulltimesheetexport') // authorized can add multiple
					|| array_intersect(['supervisor'], $_SESSION['user']['permissions']) // supervisors can add multiple
					){
					$events[] = [
						'type' => 'calendarbutton',
						'attributes' => [
							'value' => $this->_lang->GET('calendar.timesheet.new'),
							'onclick' => $calendar->dialog($columns)
						]
					];
				}
				if ($displayedEvents) array_push($events, ...$displayedEvents);

				// full access and supervisors can approve all displayed entries
				if ($bulkapproval){
					$events[] = [
						'type' => 'button',
						'attributes' => [
							'value' => $this->_lang->GET('calendar.timesheet.bulk_approve', [':number' => count($bulkapproval)]),
							'onclick' => "api.calendar('put', 'complete', '" . implode(',', $bulkapproval) . "', true, 'timesheet')"
						]
					];
				}

				// display current scheduled events to raise awareness
				$today = new DateTime($this->_requestedDate, new DateTimeZone($this->_date['timezone']));
				if ($thisMonthsEvents = $calendar->getWithinDateRange($today->modify('first day of this month')->format('Y-m-d'), $today->modify('last day of this month')->format('Y-m-d'))) {
					$timesheetentries = false;
					foreach($thisMonthsEvents as $evt) if ($evt['type']==='timesheet') $timesheetentries = true;
					if ($timesheetentries) $events[] = [
						'type' => 'button',
						'attributes' => [
							'data-type' => 'download',
							'value' => $this->_lang->GET('calendar.timesheet.monthly_summary'),
							'onclick' => "api.calendar('get', 'monthlyTimesheets', '" . $this->_requestedDate . "')"
						]
					];
				}
				$result['render']['content'][] = $events;

				// display past unclosed scheduled events to raise awareness
				$today = new DateTime($this->_requestedDate, new DateTimeZone($this->_date['timezone']));
				$pastEvents = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
				if ($pastEvents) {
					foreach ($pastEvents as $id => $row){
						if ($row['type'] !== 'schedule' || ($row['affected_user_units'] && !array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])) || $row['closed']) unset($pastEvents[$id]);
					}
					if ($pastEvents){
						$events = [
							[
								'type' => 'textsection',
								'attributes' => [
									'data-type' => 'calendar',
									'name' => $this->_lang->GET('calendar.schedule.events_assigned_units_uncompleted')
								]
							]
						];
						array_push($events, ...$this->scheduledEvents($pastEvents, $calendar));
						$result['render']['content'][] = $events;	
					}
				}
				break;
			case 'DELETE':
				$calendarentry = SQLQUERY::EXECUTE($this->_pdo, 'calendar_get_by_id', [
					'replacements' => [
						':id' => $this->_requestedId
					]
				]);
				$calendarentry = $calendarentry ? $calendarentry[0] : null;
				if (!$calendarentry) $this->response([], 404);

				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']) || 
					($calendarentry['affected_user_id'] === $_SESSION['user']['id'] && !$calendarentry['closed']))
				) $this->response([], 401);

				if ($calendar->delete($this->_requestedId)) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('calendar.schedule.deleted'),
						'type' => 'deleted'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('calendar.schedule.not_found'),
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
			$date = new DateTime($row['span_start'], new DateTimeZone($this->_date['timezone']));
			$due = new DateTime($row['span_end'], new DateTimeZone($this->_date['timezone']));
			if (!$row['organizational_unit']) $row['organizational_unit'] = ''; 
			$row['organizational_unit'] = explode(',', $row['organizational_unit']);
			if ($row['type'] !== 'timesheet'
				|| !($row['affected_user_id'] === $_SESSION['user']['id']
				|| PERMISSION::permissionFor('calendarfulltimesheetexport')
				|| (array_intersect(['supervisor'], $_SESSION['user']['permissions'])
				&& array_intersect($row['organizational_unit'], $_SESSION['user']['units']))
			)) continue; // skip what is no matter to you

			// replace deleted user names
			if (!$row['author']) $row['author'] = $this->_lang->GET('message.deleted_user');
			if (!$row['affected_user']) $row['affected_user'] = $this->_lang->GET('message.deleted_user');

			// create information
			$hint = '';
			$display = '';
			if ($row['subject']) $display .= $this->_lang->GET('calendar.timesheet.irregular') . ': ' . $this->_lang->_USER['calendar']['timesheet']['pto'][$row['subject']] . "\n";
			$display .=	$this->_lang->GET('calendar.timesheet.start') . ': ' . $this->dateFormat($date->format($row['subject'] ? 'Y-m-d' : 'Y-m-d H:i')) . "\n" .
				$this->_lang->GET('calendar.timesheet.end') . ': ' . $this->dateFormat($due->format($row['subject'] ? 'Y-m-d' : 'Y-m-d H:i')) . "\n";
			if ($row['misc']){
				$misc = json_decode($row['misc'], true);
				if (!$row['subject'] && isset($misc['break'])) $display .= $this->_lang->GET('calendar.timesheet.break') . ': ' . $misc['break'] . "\n";
				if (!$row['subject'] && isset($misc['homeoffice'])) $display .= $this->_lang->GET('calendar.timesheet.homeoffice') . ': ' . $misc['homeoffice'] . "\n";
				if (!$row['subject'] && isset($misc['workinghourscorrection'])) $display .= $this->_lang->GET('calendar.timesheet.pto.workinghourscorrection') . ': ' . $misc['workinghourscorrection'] . "\n";
				if ($row['author_id'] != $row['affected_user_id']) {
					$hint = $this->_lang->GET('calendar.timesheet.foreign_contributor') . ': ' . $row['author'] . "\n";
				}
			}

			// add event tile
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

			// add completion toggle
			$completed[$this->_lang->GET('calendar.timesheet.approve')] = ['onchange' => "api.calendar('put', 'complete', '" . $row['id'] . "', this.checked, 'timesheet')"];
			// completion can only be done by authorized and supervisors of affected user unit
			if (!(PERMISSION::permissionFor('calendarfullaccess')
				|| (array_intersect(['supervisor'], $_SESSION['user']['permissions']) 
				&& array_intersect($row['organizational_unit'], $_SESSION['user']['units']))))
				$completed[$this->_lang->GET('calendar.timesheet.approve')]['disabled'] = true;
			
			// create closed info
			$completed_hint = '';
			if ($row['closed']) {
				$completed[$this->_lang->GET('calendar.timesheet.approve')]['checked'] = true;
				$row['closed'] = json_decode($row['closed'], true);
				$completed_hint = $this->_lang->GET('calendar.timesheet.approved_state', [':user' => $row['closed']['user'], ':date' => $this->dateFormat($row['closed']['date'])]);
			}
			$events[count($events)-1]['content'][] = [
				'type' => 'checkbox',
				'content' => $completed,
				'hint' => $completed_hint,
			];

			// editing of timesheet entries is allowed for admin and affected user for regulatory security only
			if (array_intersect(['admin'], $_SESSION['user']['permissions']) || 
				($row['affected_user_id'] === $_SESSION['user']['id'] && !$row['closed'])
			) {
				// prepare information to import to dialog
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

				// add edit button
				$events[count($events)-1]['content'][] = [
					'type' => 'button',
					'attributes' => [
						'type' => 'button',
						'value' => $this->_lang->GET('calendar.schedule.edit'),
						'onclick' => $calendar->dialog($columns)
					]
				];

				// add delete button
				$events[count($events)-1]['content'][] = [
					'type' => 'deletebutton',
					'attributes' => [
						'value' => $this->_lang->GET('calendar.schedule.delete'),
						'onclick' => "new _client.Dialog({type:'confirm', header:'" . $this->_lang->GET('calendar.schedule.delete') . "', options:{'" . $this->_lang->GET('general.cancel_button') . "': false, '" . $this->_lang->GET('calendar.schedule.delete') . "': {'value': true, class: 'reducedCTA'}}})" .
							".then(confirmation => {if (confirmation) api.calendar('delete', 'schedule', " . $row['id'] . "); this.disabled = Boolean(confirmation);});"
					]
				];
			}
		}
		return $events;
	}
}
?>
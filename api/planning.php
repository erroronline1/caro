<?php
// planning, contributing to calendar
require_once('calendar.php');

class PLANNING extends API {

	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedTimespan = null;
	private $_requestedId = null;
	private $_requestedDate = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedTimespan = $this->_requestedId = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_requestedDate = array_key_exists(3, REQUEST) ? REQUEST[3] : null;
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
				];
				if (!($event[':date'] && $event[':organizational_unit'] && $event[':content'])) $this->response(['status' => ['msg' => LANG::GET('planning.event_error_missing'), 'type' => 'error']]);
				if (!$event[':due']){
					$due = new DateTime($event[':date']);
					$due->modify('+' . INI['calendar']['default_due'] . ' months');
					$event[':due'] = $due->format('Y-m-d');	
				}
				if ($newid = $calendar->post($event[':date'], $event[':due'], $event[':type'], $event[':author'], $event[':organizational_unit'], $event[':content'])) $this->response([
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
				];
				if (!($event[':date'] && $event[':organizational_unit'] && $event[':content'])) $this->response(['status' => ['msg' => LANG::GET('planning.event_error_missing'), 'type' => 'error']]);
				if (!$event[':due']){
					$due = new DateTime($event[':date']);
					$due->modify('+' . INI['calendar']['default_due'] . ' months');
					$event[':due'] = $due->format('Y-m-d');	
				}
				if ($calendar->put($event[':id'], $event[':date'], $event[':due'], $event[':organizational_unit'], $event[':content'])) $this->response([
					'status' => [
						'id' => $event[':id'],
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

					$dbevents = $calendar->getdate($this->_requestedDate);
					foreach($dbevents as $row){
						$events[] = [
							'type' => 'text',
							'description' => $row['content'],
							'content' => implode(', ', $row)
						];
						$events[] = [
							'type' => 'button',
							'attributes' => [
								'type' => 'button',
								'class' => 'inlinebutton',
								'value' => LANG::GET('planning.event_edit'),
								'onpointerup' => $calendar->dialog($row['date'], $row['due'], $row['type'], $row['organizational_unit'], $row['content'], $row['id'])
									]
						];	
						$events[] = [
							'type' => 'deletebutton',
							'attributes' => [
								'value' => LANG::GET('planning.event_delete'),
								'onpointerup' => "new Dialog({type:'confirm', header:'" . LANG::GET('planning.event_delete') . " " . $row['content'] . "', options:{'" . LANG::GET('general.cancel_button') . "': false, '" . LANG::GET('planning.event_delete') . "': {'value': true, class: 'reducedCTA'}}})" .
									".then(confirmation => {if (confirmation) api.planning('delete', 'calendar', " . $row['id'] . ");});"
							]
						];	
					}
					$events[] = [
						'type' => 'button',
						'attributes' => [
							'value' => LANG::GET('planning.event_new'),
							'onpointerup' => $calendar->dialog($this->_requestedDate)
						]
					];
					$result['body']['content'][] = $events;
				}
				break;
			case 'DELETE':
				$calendar->delete($this->_requestedId);
				$this->response([
					'status' => [
						'msg' => LANG::GET('planning.event_deleted'),
						'type' => 'success'
					]]);
				break;
		}
		$this->response($result);
	}
	
}

$api = new PLANNING();
$api->processApi();

exit;
?>
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
		$result = ['body' => ['content' => []]];

		$calendar = new CALENDAR($this->_pdo);
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
			$result['body']['content'][] = [
				[
					'type' => 'text',
					'description' => $this->_requestedDate,
					'attributes' => [
						'id' => 'displayspecificdate',
						'data-type' => 'calendar'
					]
				]
			];
		}

		$this->response($result);
	}
	
}

$api = new PLANNING();
$api->processApi();

exit;
?>
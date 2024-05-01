<?php
// calendar
class CALENDAR {
	/**
	 * calendar handler writing to database and rendering either weeks or months for given date or now by default
	 * include to display calendar or use for planning
	 */


   	/**
	 * preset database connection, passes from main application
	 */
	public $_pdo;

	public function __construct($pdo){
		$this->_pdo = $pdo;
	}

	/**
	 * calculates a calendar view, for given date week starts on monday, month on 1st
	 * 
	 * @param string $type month|week
	 * @param string $date yyyy-mm-dd
	 * 
	 * @return array $calendar [null for display offset || DateTime object]
	 */
	private function days($type = '', $date = ''){
		$result = [];
		$date = new DateTime($date ? : 'now');
		if ($type === 'week') {
			$date->modify('- ' . ($date->format('N') - 1) . ' days');
			while ($date->format('N') < 7){
				$result[] = clone $date;
				$date->modify('+ 1 days');
			}
			$result[] = $date;
		}
		elseif ($type === 'month') {
			$date->modify('first day of this month');
			if ($date->format('N') > 1){
				for ($i = 1; $i < $date->format('N'); $i++){
					$result[] = null;
				}
			}
			while ($date->format('j') < $date->format('t')) {
				$result[] = clone $date;
				$date->modify('+ 1 days');
			}
			$result[] = $date;
		}
		return $result;
	}

	/**
	 * renders a calendar view, for given date week starts on monday, month on 1st, weekday offsets are empty
	 * 
	 * @param string $type month|week
	 * @param string $date yyyy-mm-dd
	 * 
	 * @return array $calendar [null for display offset || day info]
	 */
	public function render($type = '', $date = ''){
		$result = ['header' => null, 'content' => []];
		$days = $this->days($type, $date);

		foreach ($days as $day){
			if ($day === null) $result['content'][] = null;
			else {
				$result['content'][] = LANGUAGEFILE['general']['weekday'][$day->format('N')] . ' ' . $day->format('j');
				if ($result['header']) continue;
				if ($type === 'week') $result['header'] = LANG::GET('general.calendar_week', [':number' => $day->format('W')]) . ' ' . $day->format('Y');
				if ($type === 'month') $result['header'] = LANGUAGEFILE['general']['month'][$day->format('n')] . ' ' . $day->format('Y');
			}
		}
		return $result;
	}

	/**
	 * @param str $date
	 * @param str $due
	 * @param str $type
	 * @param int $author
	 * @param str $organizational_unit
	 * @param str $content
	 */
	public function post($date = '', $due = '', $type = '', $author = 0, $organizational_unit = '', $content = ''){
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('calendar_post'));
		return $statement->execute([
			':date' => $date,
			':due' => $due,
			':type' => $type,
			':author' => $author,
			':organizational_unit' => $organizational_unit,
			':content' => $content
		]);
	}

	/**
	 * @param int $id
	 * @param str $date
	 * @param str $due
	 * @param str $type
	 * @param str $organizational_unit
	 * @param str $content
	 * @param str $completed
	 */
	public function put($id = 0, $date = '', $due = '', $type = '', $author = 0, $organizational_unit = '', $content = '', $completed = ''){
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('calendar_put'));
		return $statement->execute([
			':id' => $id,
			':date' => $date,
			':due' => $due,
			':type' => $type,
			':organizational_unit' => $organizational_unit,
			':content' => $content,
			':completed' => $completed
		]);
	}

	/**
	 * @param int $id
	 */
	public function delete($id = 0){
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('calendar_delete'));
		return $statement->execute([
			':id' => $id
		]);

	}
}
?>
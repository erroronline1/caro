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

/*
filters and returns a named array according to setup.
	if a filterset uses date thresholds or date intervals and month or year are not manually set,
	the current date is processed.

	setup is passed as names array.
	filters and modifications are processed in order of appearance.
	modifications take place with the filtered list only for performance reasons.
	compare lists can be filtered and manipulated likewise. due to recursive implementation the origin list
	can be used as a filter by itself.

	"postProcessing": optional string as hint what to do with the result file
	"filesetting":
		"source": file to process or a named array (the other filesettings don't matter then)
		"headerrow": title row
		"dialect": settings according to php fgetcsv
		"columns": list/array of column names to process and export to destination
		"encoding": comma separated string of possible character encoding of sourcefile

	"filter": list/array of objects/dicts
		"apply": "filter_by_expression"
		"comment": description, will be displayed
		"keep": boolean if matches are kept or omitted
		"match":
			"all": all expressions have to be matched, object/dict with column-name-key, and pattern as value
			"any": at least one expression has to be matched, it's either "all" or "any"

		"apply": "filter_by_monthdiff"
		"comment": description, will be displayed
		"keep": boolean if matches are kept or omitted
		"date": filter by identifier and date diff in months
			"identifier": column name with recurring values, e.g. customer id
			"column": column name with date to process,
			"format": according to https://www.php.net/manual/en/datetime.format.php,
			"threshold": integer for months,
			"bias": < less than, > greater than threshold

		"apply": "filter_by_duplicates",
		"comment": description, will be displayed
		"keep": boolean if matches are kept or omitted
		"duplicates": keep amount of duplicates of column value, ordered by another concatenated column values (asc/desc)
			"orderby": list/array of column names whose values concatenate for comparison
			"descending": boolean,
			"column": column name with recurring values, e.g. customer id of which duplicates are allowed
			"amount": integer > 0

		"apply": "filter_by_comparison_file",
		"comment": description, will be displayed
		"keep": boolean if matches are kept or omitted, not set or null to only transfer matching 
		"compare": keep or discard explicit excemptions as stated in excemption file, based on same identifier
			"filesetting": same structure as base. if source == "SELF" the origin file will be processed
			"filter": same structure as base
			"modify": same structure as base
			"match":
				"all": dict with one or multiple "ORIGINCOLUMN": "COMPAREFILECOLUMN", kept if all match
				"any": dict with one or multiple "ORIGINCOLUMN": "COMPAREFILECOLUMN", kept if at least one matches
		"transfer": add a new column with comparison value of all kept matching rows or first match of any

		"apply": "filter_by_monthinterval",
		"comment": description, will be displayed
		"keep": boolean if matches are kept or omitted
		"interval": discard by not matching interval in months, optional offset from initial column value
			"column": column name with date to process,
			"format": according to https://www.php.net/manual/en/datetime.format.php,
			"interval": integer for months,
			"offset": optional offset in months

		"apply": "filter_by_rand",
		"comment": description, will be displayed
		"keep": boolean if matches are kept or omitted
		"data": select amount of random rows that match given content of asserted column (if multiple, all must be found)
			"columns": object/dict of COLUMN-REGEX-pairs to select from,
			"amount": integer > 0

	"modify": modifies the result
		"add": adds a column with the set value. if the name is already in use this will be replaced!
			   if property is an array with number values and arithmetic operators it will try to calculate
			   comma will be replaced with a decimal point in the latter case. hope for a proper number format.
		"replace": replaces regex matches with the given value either at a specified field or in all
				   according to index 0 being a column name or none/null.
				   if more than one replacement are provided new lines with altered column values will be added to the result
				   replacements on a peculiar position have to be match[2] (full match, group 1 (^ if necessary), group 2, ...rest)
		"remove": remove columns from result, may have been used solely for filtering
		"rewrite": adds newly named columns consisting of concatenated origin column values and separators.
				   original columns will be omitted, nested within a list to make sure to order as given
		"translate": column values to be translated according to specified translation object
		"conditional_and": changes a columns value if all regex matches on other columns, adds column by default with empty value
		"conditional_or": changes a columns value if any regex matches on other columns, adds column by default with empty value

	"split": split output by matched patterns of column values into multiple files (csv) or sheets (xlsx)

	"evaluate": object/dict with colum-name keys and patterns as values that just create a warning, e.g. email verification

	translations can replace e.g. numerical values with legible translations.
	this is an object/dict whose keys can be refered to from the modifier. 
	the dict keys are processed as regex for a possible broader use.
*/

namespace CARO\API;

class Listprocessor {
	/**
	 * define setup
	 * {
	 * "filesetting": {},
	 * "filter: [],
	 * "modify: {},
	 * "evaluate: {},
	 * "translations: {}
	 * 
	 * @var array
	 */
	public $_setting = [];

	/**
	 * define result
	 * 
	 * @var object
	 */
	public $_list = null; // https://www.php.net/SplFixedArray
	/**
	 * define original import result for lossless recursive comparison
	 * 
	 * @var array
	 */
	public $_originallist = []; // dito

	/**
	 * define arguments
	 * 'processedMonth' = int, 'processedYear' = int for date comparison
	 * 'track' => ['column1' => [...'values'], 'column2' => [...'values']]
	 * 
	 * @var array
	 */
	private $_argument = [];

	/**
	 * define log messages
	 * 
	 * @var array
	 */
	public $_log = [];

	/**
	 * define found headers in list
	 * 
	 * @var array
	 */
	public $_headers = [];

	/**
	 * define default regex pattern delimiter
	 * eventually overrun by setup
	 * 
	 * @var string
	 */
	private $_delimiter = '#';

	public function __construct($setup, $argument = []){
		$this->_setting = gettype($setup) === 'array' ? $setup : json_decode($setup, true);
		$this->_argument = $argument;
		if (isset($this->_setting['filesetting']['dialect']['preg_delimiter'])) $this->_delimiter = $this->_setting['filesetting']['dialect']['preg_delimiter'];

		$this->_setting['filesetting']['columns'] = array_map(fn($v) => mb_convert_encoding($v, 'UTF-8', mb_detect_encoding($v, ['ASCII', 'UTF-8', 'ISO-8859-1'], true)), $this->_setting['filesetting']['columns']);

		if (!isset($this->_argument['processedMonth'])) $this->_argument['processedMonth'] = date('m');
		if (!isset($this->_argument['processedYear'])) $this->_argument['processedYear'] = date('Y');

		if (gettype($this->_setting['filesetting']['source']) === 'string' && is_file($this->_setting['filesetting']['source'])) $this->importFile();
		elseif (gettype($this->_setting['filesetting']['source']) === 'array') $this->_list = \SplFixedArray::fromArray($this->_setting['filesetting']['source'], true);
		elseif (gettype($this->_setting['filesetting']['source']) === 'object') $this->_list = \SplFixedArray::fromArray($this->_setting['filesetting']['source']->toArray(), true);
		$this->_originallist = \SplFixedArray::fromArray($this->_list->toArray(), true);
		if ($this->_list) $this->filter();
	}

	/**
	 *           _         _     _
	 *   ___ ___| |___ _ _| |___| |_ ___
	 *  |  _| .'| |  _| | | | .'|  _| -_|
	 *  |___|__,|_|___|___|_|__,|_| |___|
	 *
	 * tries to calculate an expression, returns rounded number, otherwise string
	 * @param array $expression of several strings, occasionally including numbers and arithmetic operators
	 * @return string|float|int result of arithmetic operation or imploded string
	 */
	public function calculate($expression = []){
		$expression = str_replace(',', '.', implode('', $expression));
		if (preg_match($this->_delimiter . '^[0-9\+\-\*\/\(\)\.]+$' . $this->_delimiter, $expression)) {
			return round(eval('return ' . $expression . ';'));
		} else {
			return $expression;
		}
	}
	
	/**
	 *     _     _     _
	 *   _| |___| |___| |_ ___
	 *  | . | -_| | -_|  _| -_|
	 *  |___|___|_|___|_| |___|
	 *
	 */
	public function delete($row, $track = null){
		/* delete row and add tracking to log if applicable */
		if ($track && isset($this->_argument['track'])){
			$thislistrow = $this->_list[$row];
			if ($thislistrow) {
				foreach ($this->_argument['track'] as $column => $values){
					$tracked = array_search($thislistrow[$column], $values);
					if ($tracked !== false)
						$this->_log[] = "[!] tracked deletion " . $values[$tracked] . ' in ' . $column . ': ' . json_encode($track);
				}
			}
		}
		unset ($this->_list[$row]);
	}
	
	/**
	 *   ___ _ _ _
	 *  |  _|_| | |_ ___ ___
	 *  |  _| | |  _| -_|  _|
	 *  |_| |_|_|_| |___|_|
	 *
	 * iterates through filter rules according to passed setting and calls required the methods
	 */
	public function filter(){
		$this->_log[] = '[*] total rows: ' . count($this->_list);
		$remaining = count(array_filter($this->_list->toArray(), fn($row) => $row ? : false));

		/* apply filter or modifications in given order */
		foreach ($this->_setting as $method => $rules){
			switch($method){
				case 'filter':
					foreach ($rules as $filter){
						if (isset($filter['comment'])) 
							$this->_log[] = '[*] applying filter: '. $filter['apply'] . ' ' . $filter['comment'] . '...';

						if (method_exists($this, $filter['apply'])) {
							$this->{$filter['apply']}($filter);
							$remaining = count(array_filter($this->_list->toArray(), fn($row) => $row ? : false));
							$this->_log[] = '[*] remaining filtered: '. $remaining;
							if (!$remaining) break;
						} else $this->_log[] = '[~] ' . $filter['apply'] . ' does not exist and could not be applied!';
					}
					break;
				case 'modify':
					$this->modify();
					$this->_log[] = '[*] modifications done';
					break;
				default:
					// ignore
			}
		}
		if ($remaining){
			/* delete unwanted columns and evaluate values if applicable
			{
				"EMAIL": "^((?!@).)*$"
			}
			*/
			$unsetcolumns = array_diff($this->_headers, $this->_setting['filesetting']['columns']);
			$evaluate_warning = [];
			foreach ($this->_list as $row => $values){
				if (!$values) continue;
				foreach ($unsetcolumns as $unset){
					unset($values[$unset]);
				}
				$this->_list[$row] = $values;  // SplFixedArray has problems accessing nested elements, must assign array to key directly
				if (isset($this->_setting['evaluate'])){
					foreach ($this->_setting['evaluate'] as $column => $pattern){
						if (boolval($values[$column]) && boolval(preg_match($this->_delimiter . $pattern . $this->_delimiter . 'mi', $values[$column]))){
							if (isset($evaluate_warning[$column])) $evaluate_warning[$column]++;
							else $evaluate_warning[$column] = 1;
						}			
					}
				}
			}
			foreach ($evaluate_warning as $column => $amount){
				$this->_log[] = '[!] WARNING: ' . $amount . ' values of ' . $column . ' match the evaluation pattern, please revise in the output';
			}

			$this->_log[] = '[*] result - final rows: ' . count(array_filter($this->_list->toArray(), fn($row) => $row ? : false));

			// split list or at least elevate to n = 1 for output
			$this->split();

			/* add postprocessing message to log if applicable */
			if (isset($this->_setting['postProcessing'])){
				$this->_log[] = '[*] done! '. $this->_setting['postProcessing'];
			}
		} else {
			$this->_list = [];
			$this->_log[] = '[!] all list entries have been filtered out :(';
		}
	}
	
	/**
	 *   ___ _ _ _                 _                                       _                   ___ _ _
	 *  |  _|_| | |_ ___ ___      | |_ _ _       ___ ___ _____ ___ ___ ___|_|___ ___ ___      |  _|_| |___
	 *  |  _| | |  _| -_|  _|     | . | | |     |  _| . |     | . | .'|  _| |_ -| . |   |     |  _| | | -_|
	 *  |_| |_|_|_| |___|_|  _____|___|_  |_____|___|___|_|_|_|  _|__,|_| |_|___|___|_|_|_____|_| |_|_|___|
	 *                      |_____|   |___|_____|             |_|                       |_____|
	 * discard or keep explicit excemptions as stated in comparison file, based on same identifier
	 * comparison file is imported recursively and can be filtered and alteres the same as the parent file
	 *	{
	 *		"apply": "filter_by_comparison_file",
	 *		"comment": "discard or keep explicit excemptions as stated in excemption file, based on same identifier. source with absolute path or in the same working directory",
	 *		"keep": False,
	 *		"filesetting": {
	 *			"source": "excemptions.*?.csv",
	 *			"headerrow": 1,
	 *			"columns": [
	 *				"COMPAREFILEINDEX"
	 *			]
	 *		},
	 *		"filter": [],
	 *		"match": {
	 *			"all":{
	 *				"ORIGININDEX": "COMPAREFILEINDEX"
	 *			},
	 *			"any":{
	 *				"ORIGININDEX": "COMPAREFILEINDEX"
	 *			}
	 *		},
	 *		"transfer": {
	 *			"NEWPARENTCOLUMN": "COMPARECOLUMN"
	 *		}
	 *	},
	 */
	public function filter_by_comparison_file($rule){
		if ($rule['filesetting']['source'] === 'SELF') $rule['filesetting']['source'] = $this->_originallist;
		if (!isset($rule['filesetting']['source']) || !$rule['filesetting']['source'] || !is_file($rule['filesetting']['source'])){
			$this->_log[] = '[X] no comparison file provided';
			return;
		}
		if (isset($this->_setting['translations'])) $rule['translations'] = $this->_setting['translations'];
		if (isset($this->_setting['filesetting']['encoding']) && !isset($rule['filesetting']['encoding'])) $rule['filesetting']['encoding'] = $this->_setting['filesetting']['encoding'];
		if (isset($this->_setting['filesetting']['dialect']) && !isset($rule['filesetting']['dialect'])) $rule['filesetting']['dialect'] = $this->_setting['filesetting']['dialect'];
		$this->_log[] = '[*] comparing with '. (gettype($rule['filesetting']['source']) === 'string' ? $rule['filesetting']['source'] : 'self');
		$compare_list = new Listprocessor($rule, ['processedMonth' => $this->_argument['processedMonth'], 'processedYear' => $this->_argument['processedYear']]);
		if (!isset($compare_list->_list[1])) return;
		$matched = [];
		// reduce current list to avoid key errors on unset items
		$thislistwithoutempty = array_filter($this->_list->toArray(), fn($row, $index) => $row ? true : false, ARRAY_FILTER_USE_BOTH);
	
		// match lists
		foreach ($thislistwithoutempty as $index => $self_row){
			foreach ($rule['match'] as $compareType => $compare_columns){
				$corresponds = [];
				$cmp_index = null;
				// detect index from compare file for matching column
				foreach ($compare_columns as $column => $cmp_column){
					if ($cmp_index = array_search($self_row[$column], array_column($compare_list->_list[1], $cmp_column))){
						if (!isset($corresponds[$index])) $corresponds[$index] = [];
						$corresponds[$index][] = $cmp_index;
					}
					if ($compareType === 'any') break;
				}
				foreach ($corresponds as $index => $corresponding){
					if (($compareType === 'any' && count($corresponding)) ||
						($compareType === 'all' && count($corresponding) === count(array_keys($compare_columns)) && count(array_unique($corresponding)) === 1)) {
						$matched[] = [$index, $cmp_index];
					}
				}
			}
		}
		$matched = array_unique($matched, SORT_REGULAR);
		if (isset($rule['keep']) && $rule['keep'] !== null){
			if ($rule['keep']){
				foreach (array_udiff(array_keys($thislistwithoutempty), array_column($matched, 0), fn($v1, $v2) => $v1 <=> $v2) as $index){
					$track = [
						'filter' => 'filter_by_comparison_file',
						'filtered_by' => json_encode($rule['match'])];
					$this->delete($index, $track);
				}	
			}
			else {
				foreach (array_uintersect(array_keys($thislistwithoutempty), array_column($matched, 0), fn($v1, $v2) => $v1 <=> $v2) as $index){
					$track = [
						'filter' => 'filter_by_comparison_file',
						'filtered_by' => json_encode($rule['match'])];
					$this->delete($index, $track);
				}	
			}
		}
		// transfer values from comparison list to main list
		if (isset($rule['transfer'])){
			$thislistwithoutempty = array_filter($this->_list->toArray(), fn($row, $index) => $row ? true : false, ARRAY_FILTER_USE_BOTH);
			foreach ($rule['transfer'] as $newcolumn => $from){
				if (!in_array($newcolumn, $this->_setting['filesetting']['columns']))
					$this->_setting['filesetting']['columns'][] = $newcolumn;
				foreach ($this->_list as $i => $row){
					if (!$row) continue;
					if (($match = array_search($i, array_column($matched, 0))) !== false){
						$row[$newcolumn] = $compare_list->_list[1][$matched[$match][1]][$from];
					}
					else $row[$newcolumn] = '';
					$this->_list[$i] = $row;
				}
			}
		}
		$compare_list = $thislistwithoutempty = null; // release ressources
	}
	
	/**
	 *   ___ _ _ _                 _               _         _ _         _
	 *  |  _|_| | |_ ___ ___      | |_ _ _       _| |_ _ ___| |_|___ ___| |_ ___ ___
	 *  |  _| | |  _| -_|  _|     | . | | |     | . | | | . | | |  _| .'|  _| -_|_ -|
	 *  |_| |_|_|_| |___|_|  _____|___|_  |_____|___|___|  _|_|_|___|__,|_| |___|___|
	 *                      |_____|   |___|_____|       |_|
	 * keep amount of duplicates of column value, ordered by another concatenated column values (asc/desc)
	 *	{
	 *		"apply": "filter_by_duplicates",
	 *		"comment": "keep amount of duplicates of column value, ordered by another concatenated column values (asc/desc)",
	 *		"keep": True,
	 *		"duplicates": {
	 *			"orderby": ["ORIGININDEX"],
	 *			"descending": False,
	 *			"column": "CUSTOMERID",
	 *			"amount": 1
	 *		}
	 *	},
	 */
	public function filter_by_duplicates($rule){
		$duplicates = [];
		foreach ($this->_list as $i => $row){
			if (!$row) continue;

			$identifier = $row[$rule['duplicates']['column']];
			$orderby = [];
			foreach ($rule['duplicates']['orderby'] as $k => $column){
				$orderby[] = $row[$column]; 
			}
			if (!isset($duplicates[$identifier])) $duplicates[$identifier] = [[implode('', $orderby), $i]];
			else array_push($duplicates[$identifier], [implode('', $orderby), $i]);
		}
		$descending = $rule['duplicates']['descending'];
		
		foreach ($duplicates as &$multiple){
			usort($multiple, function ($a, $b) use ($descending){
				if ($a[0] === $b[0]) return 0;
				if ($descending) return ($a[0] < $b[0]) ? 1: -1;
				return ($a[0] < $b[0]) ? -1: 1;
			});
			foreach ($multiple as $index => $k){
				if ($index < $rule['duplicates']['amount'])	continue;
				else {
					$track = [
						'filter' => 'filter_by_duplicates',
						'value' => $k[0],
						'filtered_by' => $index + 1 . ' of ' . $rule['duplicates']['amount']];
					$this->delete($k[1], $track);
				}
			}
		}
	}
	
	/**
	 *   ___ _ _ _                 _                                         _
	 *  |  _|_| | |_ ___ ___      | |_ _ _       ___ _ _ ___ ___ ___ ___ ___|_|___ ___
	 *  |  _| | |  _| -_|  _|     | . | | |     | -_|_'_| . |  _| -_|_ -|_ -| | . |   |
	 *  |_| |_|_|_| |___|_|  _____|___|_  |_____|___|_,_|  _|_| |___|___|___|_|___|_|_|
	 *                      |_____|   |___|_____|       |_|
	 * keep or discard all entries where column values match regex pattern 
	 *	{
	 *		"apply": "filter_by_expression",
	 *		"comment": "keep if all general patterns match",
	 *		"keep": True,
	 *		"match": {
	 *			"all": {
	 *				"DELIEVERED": "delivered",
	 *				"NAME": ".+?"
	 *			}
	 *		}
	 *	},
	 *	{
	 *		"apply": "filter_by_expression",
	 *		"comment": "discard if any general exclusions match",
	 *		"keep": False,
	 *		"match": {
	 *			"any": {
	 *				"DEATH": ".+?",
	 *				"NAME": "company|special someone",
	 *				"AID": "repair|cancelling|special.*?names"
	 *			}
	 *		}
	 *	},
	 *	{
	 *		"apply": "filter_by_expression",
	 *		"comment": "discard if value is below 400 unless pattern matches",
	 *		"keep": False,
	 *		"match": {
	 *			"all": {
	 *				"PRICE": "^[2-9]\\d\\D|^[1-3]\\d{2,2}\\D",
	 *				"AID": "^(?!(?!.*(not|those)).*(but|these|surely)).*"
	 *			}
	 *		}
	 *	},
	 */
	public function filter_by_expression($rule){
		foreach ($this->_list as $i => $row){
			if (!$row) continue;

			if (isset($rule['match'])){
				$keep = true;
				$track = [];
				if (isset($rule['match']['any'])){
					foreach ($rule['match']['any'] as $column => $filter){
						$track = [
							'filter' => 'filter_by_expression',
							'column' => $column,
							'value' => $row[$column],
							'filtered_by' => $filter];
						$keep = !$rule['keep'];
						if (boolval(preg_match($this->_delimiter . $filter . $this->_delimiter . 'mi', $row[$column]))){
							$keep = $rule['keep'];
							break;
						}
					}
				}
				elseif (isset($rule['match']['all'])){
					foreach ($rule['match']['all'] as $column => $filter){
						$track = [
							'filter' => 'filter_by_expression',
							'column' => $column,
							'value' => $row[$column],
							'filtered_by' => $filter];
						$keep = $rule['keep'];
						if (!boolval(preg_match($this->_delimiter . $filter . $this->_delimiter . 'mi', $row[$column]))){
							$keep = !$rule['keep'];
							break;
						}
					}
				}
				if (!$keep)	$this->delete($i, $track);
			}
		}
	}
	
	/**
	 *   ___ _ _ _                 _                           _   _     _ _ ___ ___
	 *  |  _|_| | |_ ___ ___      | |_ _ _       _____ ___ ___| |_| |_ _| |_|  _|  _|
	 *  |  _| | |  _| -_|  _|     | . | | |     |     | . |   |  _|   | . | |  _|  _|
	 *  |_| |_|_|_| |___|_|  _____|___|_  |_____|_|_|_|___|_|_|_| |_|_|___|_|_| |_|
	 *                      |_____|   |___|_____|
	 *
	 * keep or discard all entries if 'column' meets 'bias' for 'threshold' 
	 *	{
	 *		"apply": "filter_by_monthdiff",
	 *		"comment": "discard by date diff in months, do not contact if last event within x months",
	 *		"keep": False,
	 *		"date": {
	 *			"column": "SOMEDATE",
	 *			"format": according to https://www.php.net/manual/en/datetime.format.php,
	 *			"threshold": 6,
	 *			"bias": "<"
	 *		}
	 *	},
	*/
	public function filter_by_monthdiff($rule){
		foreach ($this->_list as $i => $row){
			if (!$row) continue;

			// format dates to iso
			$entrydate = \DateTime::createFromFormat($rule['date']['format'], $row[$rule['date']['column']])->modify('first day of this month')->setTime(0, 0)->format('Y-m-d');
			$thismonth = new \DateTime($this->_argument['processedYear'] . '-' . $this->_argument['processedMonth'] . '-01');
			$thismonth = $thismonth->format('Y-m-d');

			$timespan = $this->monthdiff($entrydate, $thismonth);
			$filtermatch = ($rule['date']['bias'] === '<' && $timespan <= $rule['date']['threshold']) || ($rule['date']['bias'] === '>' && $timespan >= $rule['date']['threshold']);
			if (($filtermatch && !$rule['keep']) || (!$filtermatch && $rule['keep'])){
				$track = [
					'filter' => 'filter_by_monthdiff',
					'column' => $rule['date']['column'],
					'value' => $row[$rule['date']['column']],
					'filtered_by' => $rule['date']['bias'] . $rule['date']['threshold']];
				$this->delete($i, $track);
			}
		}
	}
	
	/**
	 *   ___ _ _ _                 _                           _   _   _     _                   _
	 *  |  _|_| | |_ ___ ___      | |_ _ _       _____ ___ ___| |_| |_|_|___| |_ ___ ___ _ _ ___| |
	 *  |  _| | |  _| -_|  _|     | . | | |     |     | . |   |  _|   | |   |  _| -_|  _| | | .'| |
	 *  |_| |_|_|_| |___|_|  _____|___|_  |_____|_|_|_|___|_|_|_| |_|_|_|_|_|_| |___|_|  \_/|__,|_|
	 *                      |_____|   |___|_____|
	 *
	 * keep or discard if 'column'-value -+ 'offset' matches 'interval' from current or argument-set date
	 *	{
	 *		"apply": "filter_by_monthinterval",
	 *		"comment": "discard by not matching interval in months, optional offset from initial column value",
	 *		"keep": False,
	 *		"interval": {
	 *			"column": "SOMEDATE",
	 *			"format": according to https://www.php.net/manual/en/datetime.format.php,
	 *			"interval": 6,
	 *			"offset": 0
	 *		}
	 *	},
	 */
	public function filter_by_monthinterval($rule){
		foreach ($this->_list as $i => $row){
			if (!$row) continue;

			// format dates to iso
			$entrydate = \DateTime::createFromFormat($rule['interval']['format'], $row[$rule['interval']['column']])->modify('first day of this month')->setTime(0, 0)->format('Y-m-d');
			$thismonth = new \DateTime($this->_argument['processedYear'] . '-' . $this->_argument['processedMonth'] . '-01');
			$thismonth = $thismonth->format('Y-m-d');

			$offset_edate = $this->monthdelta($entrydate, $rule['interval']['offset']);
			$timespan = $this->monthdiff($offset_edate, $thismonth);
			$filtermatch = !@boolval($timespan % $rule['interval']['interval']); // Implicit conversion from float XX.XX to int loses precision
			if (($filtermatch && !$rule['keep']) || (!$filtermatch && $rule['keep'])){
				$track = [
					'filter' => 'filter_by_monthinterval',
					'column' => $rule['interval']['column'],
					'value' => $row[$rule['interval']['column']],
					'filtered_by' => $rule['interval']['interval'] . ' remainder ' . $filtermatch];
				$this->delete($i, $track);
			}
		}
	}

	/**
	 *   ___ _ _ _                 _                           _
	 *  |  _|_| | |_ ___ ___      | |_ _ _       ___ ___ ___ _| |
	 *  |  _| | |  _| -_|  _|     | . | | |     |  _| .'|   | . |
	 *  |_| |_|_|_| |___|_|  _____|___|_  |_____|_| |__,|_|_|___|
	 *                      |_____|   |___|_____|
	 *
	 * keep or discard amount of random rows that match given column values
	 *	{
	 *		"apply": "filter_by_rand",
	 *		"comment": "keep some random rows",
	 *		"keep": True,
	 *		"data": {
	 *			"columns": {
	 *				"SOMEFILTERCOLUMN", "hasvalue"
	 *			},
	 *			"amount": 10
	 *		}
	 *	}
	 */
	public function filter_by_rand($rule){
		$subset = [];
		foreach ($this->_list as $i => $row){
			if (!$row) continue;

			if (isset($rule['data']['columns'])){
				$match = false;
				$columns = array_keys($rule['data']['columns']);
				for($c = 0; $c < count($columns); $c++){
					$column = $columns[$c];
					$match = boolval(preg_match($this->_delimiter . $rule['data']['columns'][$column] . $this->_delimiter . 'mi', $row[$column]));
					if (!$match) break;
				}
				if (!$match) continue;
				$subset[] = $i;
			}
			else $subset[] = $i;
		}
		$randomset = array_rand($subset, min(count($subset), $rule['data']['amount']));
		foreach ($subset as $i){
			if (in_array($i, $randomset))
				if ($rule['keep']) continue;
			else
				if (!$rule['keep'])	continue;
			$track = [
				'filter' => 'filter_by_rand',
				'filtered_by' => 'randomly selected'];
			$this->delete($i, $track);
		}
	}

	/**
	 *   _                   _   ___ _ _
	 *  |_|_____ ___ ___ ___| |_|  _|_| |___
	 *  | |     | . | . |  _|  _|  _| | | -_|
	 *  |_|_|_|_|  _|___|_| |_| |_| |_|_|___|
	 *          |_|
	 */
	public function importFile(){
		/* import file and create an associative array from rows
		{
			"source": "Export.csv",
			"headerrow": 1,
			"dialect": {
				"separator": ";",
				"enclosure": "\"",
				"escape": "",
				"preg_delimiter": "#"
			},
			"columns": [
				"ORIGININDEX",
				"SOMEDATE",
				"CUSTOMERID",
				"NAME",
				"DEATH",
				"AID",
				"PRICE",
				"DELIVERED",
				"DEPARTMENT",
				"SOMEFILTERCOLUMN"
			]
		}		
		*/
		$this->_list = new \SplFixedArray(0);
		$i = 0;
		$csvfile = fopen($this->_setting['filesetting']['source'], 'r');
		if (fgets($csvfile, 4) !== "\xef\xbb\xbf") rewind($csvfile); // BOM not found - rewind pointer to start of file.
		while(($row = fgetcsv($csvfile, null, $this->_setting['filesetting']['dialect']['separator'], $this->_setting['filesetting']['dialect']['enclosure'], $this->_setting['filesetting']['dialect']['escape'])) !== false) {
			// import headers
			if ($i++ < $this->_setting['filesetting']['headerrow']) {
				$this->_headers = array_map(fn($v) => mb_convert_encoding($v, 'UTF-8', mb_detect_encoding($v, ['ASCII', 'UTF-8', 'ISO-8859-1'], true)), $row);
			}
			else {
				if (boolval(array_diff($this->_setting['filesetting']['columns'], array_intersect($this->_setting['filesetting']['columns'], $this->_headers)))) {
					$this->_log[] = '[~] File Import Error: not all required columns were found, filter aborted... Required: ' . implode(', ', $this->_setting['filesetting']['columns']) . '; Found: ' . implode(', ', $this->_headers);
					break;
				}
				// kindly ignore corrupt formatted and empty lines
				$unique = array_unique($row);
				if (count($this->_headers) === count($row) && count($unique) > 0 && end($unique) !== null){
					$this->_list->setSize(count($this->_list) + 1);
					$this->_list[count($this->_list) - 1] = array_combine($this->_headers, $row);
				}
			}
		}
		fclose($csvfile);

		foreach ($this->_list as $row => $values){
			foreach ($values as $column => &$columnvalue){
				$columnvalue = @mb_convert_encoding(strval($columnvalue), 'UTF-8', $this->_setting['filesetting']['encoding']);
			}
			$this->_list[$row] = $values; // SplFixedArray has problems accessing nested elements, must assign array to key directly
		}
		return true;
	}
	
	/**
	 *               _ _ ___
	 *   _____ ___ _| |_|  _|_ _
	 *  |     | . | . | |  _| | |
	 *  |_|_|_|___|___|_|_| |_  |
	 *                      |___|
	 *	remove, replace or add column with fixed value or formula,
	 *	replace regex pattern in existing column or
	 *	change value in a column if a regex matches in others (add column if not present with empty value b default)
	 *	{
	 *		"add":{
	 *			"NEWCOLUMNNAME": "string",
	 *			"ANOTHERCOLUMNNAME" : ["PRICE", "*1.5"]
	 *		},
	 *		"replace":[
	 *			["NAME", "regex", "replacement"],
	 *			[null, ";", ","],
	 *			["SOMECOLUMN", "regex", "replacement", "replacementnewrow", "replacementanothernewrow"]
	 *		],
	 *		"remove": ["SOMEFILTERCOLUMN", "DEATH"],
	 *		"rewrite":[
	 *			{"Customer": ["CUSTOMERID", " separator ", "NAME"]}
	 *		],
	 *		"translate":{
	 *			"DEPARTMENT": "departments"
	 *		},
	 *		"conditional":[
	 *			["NEWCOLUMNNAME", "anotherstring", ["SOMECOLUMN", "regex"], ["SOMEOTHERCOLUMN", "regex"]]
	 *		]
	 *	},
	 */
	public function modify(){
		foreach ($this->_setting['modify'] as $modify => $modifications){
			foreach ($modifications as $key => $rule){
				switch ($modify){
					case 'add':
						if (!in_array($rule, $this->_setting['filesetting']['columns']))
						$this->_setting['filesetting']['columns'][] = $key;
						foreach ($this->_list as $i => $row){
							if (!$row) continue;

							if (is_array($rule)){
								$expression = [];
								foreach ($rule as $possible_col){
									$expression[] = isset($row[$possible_col]) ? $row[$possible_col] : $possible_col;
								}
							}
							else
								$expression = [$rule];
							$row[$key] = strval($this->calculate($expression));
							$this->_list[$i] = $row;
						}
						break;
					case 'replace':
						foreach ($this->_list as $i => $row){
							if (!$row) continue;

							foreach ($row as $column => $value) {
								for ($replacement = 2; $replacement < count($rule); $replacement++){
									if ((!$rule[0] || $rule[0] == $column) && preg_match($this->_delimiter . $rule[1]. $this->_delimiter . 'm', $value)){
										$replaced_value = preg_replace_callback(
											$this->_delimiter . $rule[1]. $this->_delimiter . 'm',
											function($matches) use ($rule, $replacement){
												// plain replacement of first match
												if (count($matches)<2) return $rule[$replacement];
												// replacement on peculiar position
												// ensure a pattern where $match[2] has to be replaced. match string start (^) if necessary
												// ignore $match[0] for being the whole match w/o groups
												return count($matches)>3 ? implode('', [$matches[1], $rule[$replacement], ...array_slice($matches,3)]) : $matches[1] . $rule[$replacement];	    	
											},
											$value);
										$row[$column] = $replaced_value; // SplFixedArray has problems accessing nested elements, must assign array to key directly
										if ($replacement < 3) {
											$this->_list[$i] = $row;
										} else {
											$this->_list->setSize(count($this->_list) + 1);
											$this->_list[count($this->_list) - 1] = $row;
										}
									}
								}
							}
						}
						break;
					case 'remove':
						$this->_setting['filesetting']['columns'] = array_diff($this->_setting['filesetting']['columns'], [$rule]);
						break;
					case 'rewrite':
						foreach ($rule as $new_column => $combine){
							if (!in_array($new_column, $this->_setting['filesetting']['columns']))
								$this->_setting['filesetting']['columns'][] = $new_column;
							foreach ($this->_list as $i => $row) {
								if (!$row) continue;

								$concatenate = '';
								foreach ($combine as $column){
									if (isset($row[$column])) $concatenate .= $row[$column];
									else $concatenate .= $column;
								}
								$row[$new_column] = $concatenate; // SplFixedArray has problems accessing nested elements, must assign array to key directly
								$this->_list[$i] = $row;
							}
						}
						break;
					case 'translate':
						if (!isset($this->_setting['translations'])) break;
						foreach ($this->_list as $i => $row) {
							if (!$row) continue;

							foreach ($modifications as $translate => $translation){
								if (isset($this->_setting['translations'][$rule][$row[$rule]]))
									$row[$key] = trim(preg_replace($this->_delimiter . '^' . $row[$rule] . '$' . $this->_delimiter . 'm', $this->_setting['translations'][$rule][$row[$rule]], $row[$rule]));
							}
							// SplFixedArray has problems accessing nested elements, must assign array to key directly
							$this->_list[$i] = $row;
						}
						break;
					case 'conditional_and':
						foreach ($this->_list as $i => $row){
							if (!$row) continue;
							if (!in_array($rule[0], $this->_setting['filesetting']['columns']))
								$this->_setting['filesetting']['columns'][] = $rule[0];
							$matches = 0;
							for ($condition = 2; $condition < count($rule); $condition++){
								if (isset($row[$rule[$condition][0]]) && boolval(preg_match($this->_delimiter . $rule[$condition][1] . $this->_delimiter . 'mi', $row[$rule[$condition][0]]))) $matches++;
							}
							if (!isset($row[$rule[0]])) $row[$rule[0]] = '';
							if ($matches == (count($rule) - 2)) $row[$rule[0]] = $rule[1];
							else $row[$rule[0]] = strlen($row[$rule[0]]) ? $row[$rule[0]] : '';
							$this->_list[$i] = $row;
						}
						break;
					case 'conditional_or':
						foreach ($this->_list as $i => $row){
							if (!$row) continue;
							if (!in_array($rule[0], $this->_setting['filesetting']['columns']))
								$this->_setting['filesetting']['columns'][] = $rule[0];
							$matches = 0;
							for ($condition = 2; $condition < count($rule); $condition++){
								if (isset($row[$rule[$condition][0]]) && boolval(preg_match($this->_delimiter . $rule[$condition][1] . $this->_delimiter . 'mi', $row[$rule[$condition][0]]))) $matches++;
							}
							if (!isset($row[$rule[0]])) $row[$rule[0]] = '';
							if ($matches) $row[$rule[0]] = $rule[1];
							else $row[$rule[0]] = strlen($row[$rule[0]]) ? $row[$rule[0]] : '';
							$this->_list[$i] = $row;
						}
						break;
					}
			}
		}
	}

	/**
	 *                 _   _     _     _ _
	 *   _____ ___ ___| |_| |_ _| |___| | |_ ___
	 *  |     | . |   |  _|   | . | -_| |  _| .'|
	 *  |_|_|_|___|_|_|_| |_|_|___|___|_|_| |__,|
	 *
	 * adds a month delta to a passed date
	 * @param string $date 
	 * @param int $delta number of months 
	 * @return array new date
	 */
	public function monthdelta($date = "", $delta = 0){
		$offset_date = new \DateTime($date);
		$offset_date->modify('first day of ' . ($delta === 0 ? 'this' : ($delta > 1 ? '+' . $delta : '-' . $delta)) . ' month' . ($delta !==0 ? 's': ''));
		return $offset_date->format('Y-m-d');
	}
	
	/**
	 *                 _   _     _ _ ___ ___
	 *   _____ ___ ___| |_| |_ _| |_|  _|  _|
	 *  |     | . |   |  _|   | . | |  _|  _|
	 *  |_|_|_|___|_|_|_| |_|_|___|_|_| |_|
	 *
	 * determine approximately difference of months (not taking leap years into account)
	 * passed dates have to be prepared as Y-m-d in advance
	 * @param string $first
	 * @param string $last
	 * @return float difference
	 */
	public function monthdiff($first = "", $last = ""){
		$backthen = new \DateTime($first);
		$processedmonth = new \DateTime($last);
		return round($processedmonth->diff($backthen, true)->days / (365 / 12), 0);
	}

	/**
	 *           _ _ _
	 *   ___ ___| |_| |_
	 *  |_ -| . | | |  _|
	 *  |___|  _|_|_|_|
	 *      |_|
	 */
	public function split() {
		/* split list as desired or at least nest one layer */
		$split_list = [];
		foreach ($this->_list as $i => $row){
			if (!$row) continue;

			if (isset($this->_setting['split'])){
				// create sorting key by matched patterns, mandatory translated if applicable
				$sorting = '';
				foreach ($this->_setting['split'] as $key => $pattern){
					preg_match_all($this->_delimiter . $pattern . $this->_delimiter . 'mi', $row[$key], $match, PREG_OFFSET_CAPTURE);
					// "special company" matched by (special).+(company).+ or by .*
					// (.+) may be critical for matching line end as well and having indifferent results
					foreach ($match as $index => $submatch){
						if (count($match)>1 && $index === 0) continue;
						if (!trim($submatch[0][0])) continue;
						$sorting .= ' ' . $submatch[0][0];
					}
				}
				$sorting = trim($sorting);
				if (!isset($split_list[$sorting])) $split_list[$sorting] = [$row];
				else array_push($split_list[$sorting], $row);
			} else {
				if (!isset($split_list[1])) $split_list[1] = [$row];
				else array_push($split_list[1], $row);
			}
		}
		$this->_list = $split_list;
	}
}
?>


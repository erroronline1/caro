<?php
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
		"headerrowindex": offset for title row
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
			"format": list/array of date format order e.g. ["d", "m", "y"],
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
		"keep": boolean if matches are kept or omitted
		"compare": keep or discard explicit excemptions as stated in excemption file, based on same identifier
			"filesetting": same structure as base. if source == "SELF" the origin file will be processed
			"filter": same structure as base
			"modify": same structure as base
			"match":
				"all": dict with one or multiple "ORIGININDEX": "COMPAREFILEINDEX", kept if all match
				"any": dict with one or multiple "ORIGININDEX": "COMPAREFILEINDEX", kept if at least one matches
		"transfer": add a new column with comparison value

		"apply": "filter_by_monthinterval",
		"comment": description, will be displayed
		"keep": boolean if matches are kept or omitted
		"interval": discard by not matching interval in months, optional offset from initial column value
			"column": column name with date to process,
			"format": list/array of date format order e.g. ["d", "m", "y"],
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
				   replacements on a peculiar position have to be match[2] (full match, group 1 (^ if neccessary), group 2, ...rest)
		"remove": remove columns from result, may have been used solely for filtering
		"rewrite": adds newly named columns consisting of concatenated origin column values and separators.
				   original columns will be omitted, nested within a list to make sure to order as given
		"translate": column values to be translated according to specified translation object

	"split": split output by matched patterns of column values into multiple files (csv) or sheets (xlsx)

	"evaluate": object/dict with colum-name keys and patterns as values that just create a warning, e.g. email verification

	translations can replace e.g. numerical values with legible translations.
	this is an object/dict whose keys can be refered to from the modifier. 
	the dict keys are processed as regex for a possible broader use.
*/

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
	 * define original import result for lossless recursive comparison
	 * 
	 * @var array
	 */
	public $_list = []; // https://www.php.net/SplFixedArray
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
	 * define if class is called recursively
	 * 
	 * @var bool
	 */
	private $_isChild = false;

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

	public function __construct($setup, $argument = [], $isChild = false){
		$this->_setting = gettype($setup) === 'array' ? $setup : json_decode($setup, true);
		$this->_isChild = $isChild;
		$this->_argument = $argument;

		if (!array_key_exists('processedMonth', $this->_argument)) $this->_argument['processedMonth'] = date('m');
		if (!array_key_exists('processedYear', $this->_argument)) $this->_argument['processedMonth'] = date('Y');

		if (gettype($this->_setting['filesetting']['source']) === 'string' && is_file($this->_setting['filesetting']['source'])) $this->importFile();
		elseif (gettype($this->_setting['filesetting']['source']) === 'array') $this->_list = SplFixedArray::fromArray($this->_setting['filesetting']['source'], true);
		elseif (gettype($this->_setting['filesetting']['source']) === 'object') $this->_list = SplFixedArray::fromArray($this->_setting['filesetting']['source']->toArray(), true);
		$this->_originallist = SplFixedArray::fromArray($this->_list->toArray(), true);
		if ($this->_list) $this->filter();
	}

	public function monthdiff($first = [], $last = [], $dateformat = []){
		/* determine approximately difference of months (not taking leap years into account) */
		// force days and months two digit
		$day = array_search('d', $dateformat);
		$first[$day] = '01';//strlen($first[$day]) < 2 ? '0' . $first[$day] : $first[$day];
		$last[$day] = '01';//strlen($last[$day]) < 2 ? '0' . $last[$day] : $last[$day];
		$month = array_search('m', $dateformat);
		$first[$month] = strlen($first[$month]) < 2 ? '0' . $first[$month] : $first[$month];
		$last[$month] = strlen($last[$month]) < 2 ? '0' . $last[$month] : $last[$month];
		
		$first = implode('-', $first);
		$last = implode('-', $last);
		$dateformat = implode('-', $dateformat);
		$backthen = new DateTime(DateTime::createFromFormat($dateformat, $first)->format('Y-m-d'));
		$processedmonth = new DateTime(DateTime::createFromFormat($dateformat, $last)->format('Y-m-d'));
		return round($processedmonth->diff($backthen, true)->days / (365 / 12), 0);
	}

	public function monthdelta($date = [], $dateformat = [], $delta = 0){
		/* adds a delta to a passed date */
		// force days and months two digit
		$day = array_search('d', $dateformat);
		$date[$day] = '01';//strlen($date[$day]) < 2 ? '0' . $date[$day] : $date[$day];
		$month = array_search('m', $dateformat);
		$date[$month] = strlen($date[$month]) < 2 ? '0' . $date[$month] : $date[$month];

		$date = implode('-', $date);
		$dateformat = implode('-', $dateformat);
		$offset_date = new DateTime(DateTime::createFromFormat($dateformat, $date)->format('Y-m-d'));
		$day = $offset_date->format('j');
		$offset_date->modify('first day of ' . ($delta === 0 ? 'this' : ($delta > 1 ? '+' . $delta : '-' . $delta)) . ' month' . ($delta !==0 ? 's': ''));
		return explode('-', $offset_date->format($dateformat));
	}

	public function calculate($expression = []){
		/* tries to calculate an expression, returns rounded number, otherwise string */
		$expression = str_replace(',', '.', implode('', $expression));
		if (preg_match('/^[0-9\+\-\*\/\(\)\.]+$/', $expression)) {
			return round(eval('return ' . $expression . ';'));
		} else {
			return $expression;
		}
	}

	public function importFile(){
		/* import file and create an associative array from rows
		{
			"source": "Export.csv",
			"headerrowindex": 0,
			"dialect": {
				"separator": ";",
				"enclosure": "\"",
				"escape": ""
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
		$this->_list = new SplFixedArray(0);
		$i = 0;
		$csvfile = fopen($this->_setting['filesetting']['source'], 'r');
		if (fgets($csvfile, 4) !== "\xef\xbb\xbf") rewind($csvfile); // BOM not found - rewind pointer to start of file.
		while(($row = fgetcsv($csvfile, null, $this->_setting['filesetting']['dialect']['separator'], $this->_setting['filesetting']['dialect']['enclosure'], $this->_setting['filesetting']['dialect']['escape'])) !== false) {
			// import headers
			if ($i++ < $this->_setting['filesetting']['headerrowindex'] + 1) $this->_headers = $row;
			else {
				if (boolval(array_diff($this->_setting['filesetting']['columns'], array_intersect($this->_setting['filesetting']['columns'], $this->_headers)))) {
					$this->_log[] = '[~] File Import Error: not all required columns were found, filter aborted...';					
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
			foreach($values as $column => &$columnvalue){
				$columnvalue = @mb_convert_encoding($columnvalue, 'UTF-8', $this->_setting['filesetting']['encoding']);
			}
			$this->_list[$row] = $values; // SplFixedArray has problems accessing nested elements, must assign array to key directly
		}
		return true;
	}

	public function filter(){
		/* iterates through filter rules according to passed setting and calls required the methods	*/
		$this->_log[] = '[*] total rows: ' . count($this->_list);
		$remaining = count(array_filter($this->_list->toArray(), fn($row) => $row ? : false));
		/* apply filters */
		if (array_key_exists('filter', $this->_setting)){
			foreach ($this->_setting['filter'] as $filter => $listfilter){
				if (array_key_exists('comment', $listfilter)) 
					$this->_log[] = '[*] applying filter: '. $listfilter['apply'] . ' ' . $listfilter['comment'] . '...';

				if (method_exists($this, $listfilter['apply'])) {
					$this->{$listfilter['apply']}($listfilter);
					$remaining = count(array_filter($this->_list->toArray(), fn($row) => $row ? : false));
					$this->_log[] = '[*] remaining filtered: '. $remaining;
					if (!$remaining) break;
				} else $this->_log[] = '[~] ' . $listfilter['apply'] . ' does not exist and could not be applied!';
			}
		}

		if ($remaining){
			/* modify the result list if applicable */
			if (array_key_exists('modify', $this->_setting)){
				$this->modify();
				$this->_log[] = '[*] modifications done';
			}

			/* delete unwanted columns and evaluate values if applicable 
			
			{
				"EMAIL": "^((?!@).)*$"
			}
			*/
			$unsetcolumns = array_diff($this->_headers, $this->_setting['filesetting']['columns']);
			$evaluate_warning = [];
			foreach ($this->_list as $row => $values){
				if (!$values) continue;
				foreach($unsetcolumns as $unset){
					unset($values[$unset]);
				}
				$this->_list[$row] = $values;  // SplFixedArray has problems accessing nested elements, must assign array to key directly
				if (array_key_exists('evaluate', $this->_setting)){
					foreach ($this->_setting['evaluate'] as $column => $pattern){
						if (boolval($values[$column]) && boolval(preg_match('/' . $pattern . '/mi', $values[$column]))){
							if (array_key_exists($column, $evaluate_warning)) $evaluate_warning[$column]++;
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
			if (array_key_exists('postProcessing', $this->_setting)){
				$this->_log[] = '[*] done! '. $this->_setting['postProcessing'];
			}
		} else {
			$this->_list = [];
			$this->_log[] = '[!] all list entries have been filtered out :(';
		}
	}

	public function delete($row, $track = null){
		/* delete row and add tracking to log if applicable */
		if ($track && array_key_exists('track', $this->_argument)){
			$thislistrow = $this->_list[$row];
			if ($thislistrow) {
				foreach($this->_argument['track'] as $column => $values){
					$tracked = array_search($thislistrow[$column], $values);
					if ($tracked !== false)
						$this->_log[] = "[!] tracked deletion " . $values[$tracked] . ' in ' . $column . ': ' . json_encode($track);
				}
			}
		}
		unset ($this->_list[$row]);
	}

	public function split() {
		/* split list as desired or at least nest one layer */
		$split_list = [];
		foreach ($this->_list as $i => $row){
			if (!$row) continue;

			if (array_key_exists('split', $this->_setting)){
				// create sorting key by matched patterns, mandatory translated if applicable
				$sorting = '';
				foreach ($this->_setting['split'] as $key => $pattern){
					preg_match_all('/' . $pattern . '/m', $row[$key], $match);
					if (count($match)) $sorting += implode(' ', $match);
				}
				$sorting = trim($sorting);
				if (!array_key_exists($sorting, $split_list)) $split_list[$sorting] = [$row];
				else array_push($split_list[$sorting], $row);
			} else {
				if (!array_key_exists(1, $split_list)) $split_list[1] = [$row];
				else array_push($split_list[1], $row);
			}
		}
		$this->_list = $split_list;
	}
	public function filter_by_expression($rule){
		/* keep or discard all entries where column values match regex pattern 
		{
			"apply": "filter_by_expression",
			"comment": "keep if all general patterns match",
			"keep": True,
			"match": {
				"all": {
					"DELIEVERED": "delivered",
					"NAME": ".+?"
				}
			}
		},
		{
			"apply": "filter_by_expression",
			"comment": "discard if any general exclusions match",
			"keep": False,
			"match": {
				"any": {
					"DEATH": ".+?",
					"NAME": "company|special someone",
					"AID": "repair|cancelling|special.*?names"
				}
			}
		},
		{
			"apply": "filter_by_expression",
			"comment": "discard if value is below 400 unless pattern matches",
			"keep": False,
			"match": {
				"all": {
					"PRICE": "^[2-9]\\d\\D|^[1-3]\\d{2,2}\\D",
					"AID": "^(?!(?!.*(not|those)).*(but|these|surely)).*"
				}
			}
		},
		*/
		foreach ($this->_list as $i => $row){
			if (!$row) continue;

			if (array_key_exists('match', $rule)){
				$keep = true;
				$track = [];
				if (array_key_exists('any', $rule['match'])){
					foreach($rule['match']['any'] as $column => $filter){
						$track = [
							'filter' => 'filter_by_expression',
							'column' => $column,
							'value' => $row[$column],
							'filtered_by' => $filter];
						$keep = !$rule['keep'];
						if (boolval(preg_match('/' . $filter . '/mi', $row[$column]))){
							$keep = $rule['keep'];
							break;
						}
					}
				}
				elseif (array_key_exists('all', $rule['match'])){
					foreach($rule['match']['all'] as $column => $filter){
						$track = [
							'filter' => 'filter_by_expression',
							'column' => $column,
							'value' => $row[$column],
							'filtered_by' => $filter];
						$keep = $rule['keep'];
						if (!boolval(preg_match('/' . $filter . '/mi', $row[$column]))){
							$keep = !$rule['keep'];
							break;
						}
					}
				}
				if (!$keep)	$this->delete($i, $track);
			}
		}
	}

	/* keep or discard all entries if 'column' meets 'bias' for 'threshold' 
	{
		"apply": "filter_by_monthdiff",
		"comment": "discard by date diff in months, do not contact if last event within x months",
		"keep": False,
		"date": {
			"column": "SOMEDATE",
			"format": ["d", "m", "y"],
			"threshold": 6,
			"bias": "<"
		}
	},
	*/
	public function filter_by_monthdiff($rule){
		if (($key = array_search('y', $rule['date']['format'])) !== false) $rule['date']['format'][$key] = 'Y'; // make year format 4 digits
		foreach ($this->_list as $i => $row){
			if (!$row) continue;

			preg_match_all('/\d+/mi', $row[$rule['date']['column']], $entrydate);
			if (count($entrydate[0]) < 1) continue;
			$entrydate[0][array_search('d', $rule['date']['format'])] = '01';
			$thismonth = [];
			foreach ($rule['date']['format'] as $key){
				switch($key){
					case 'd':
						$thismonth[] = '01';
						break;
					case 'm':
						$thismonth[] = $this->_argument['processedMonth'];
						break;
					case 'Y':
						$thismonth[] = $this->_argument['processedYear'];
						break;
				}
			}
			$timespan = $this->monthdiff($entrydate[0], $thismonth, $rule['date']['format']);
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

	/* keep or discard if 'column'-value -+ 'offset' matches 'interval' from current or argument-set date
	{
		"apply": "filter_by_monthinterval",
		"comment": "discard by not matching interval in months, optional offset from initial column value",
		"keep": False,
		"interval": {
			"column": "SOMEDATE",
			"format": ["d", "m", "y"],
			"interval": 6,
			"offset": 0
		}
	},
	*/
	public function filter_by_monthinterval($rule){
		if (($key = array_search('y', $rule['interval']['format'])) !== false) $rule['interval']['format'][$key] = 'Y'; // make year format 4 digits
		foreach ($this->_list as $i => $row){
			if (!$row) continue;

			preg_match_all('/\d+/mi', $row[$rule['interval']['column']], $entrydate);
			if (count($entrydate[0]) < 1) continue;
			$entrydate[0][array_search('d', $rule['interval']['format'])] = '01';
			$d = 0;
			$thismonth = [];
			foreach ($rule['interval']['format'] as $key){
				switch($key){
					case 'd':
						$thismonth[] = '01';
						break;
					case 'm':
						$thismonth[] = $this->_argument['processedMonth'];
						break;
					case 'Y':
						$thismonth[] = $this->_argument['processedYear'];
						break;
				}
			}
			$offset_edate = $this->monthdelta($entrydate[0], $rule['interval']['format'], $rule['interval']['offset']);
			$timespan = $this->monthdiff($offset_edate, $thismonth, $rule['interval']['format']);
			$filtermatch = @boolval($timespan % $rule['interval']['interval']); // Implicit conversion from float XX.XX to int loses precision
			if (($filtermatch && !$rule['keep']) || (!$filtermatch && $rule['keep'])){
				$track = [
					'filter' => 'filter_by_monthinterval',
					'column' => $rule['interval']['column'],
					'value' => $row[$rule['interval']['column']],
					'filtered_by' => $rule['interval']['interval'] . ' remainder ' . $filtermatch];
				$this->delete($i);
			}
		}
	}

	/* discard or keep explicit excemptions as stated in comparison file, based on same identifier
	comparison file is imported recursively and can be filtered and alteres the same as the parent file
	{
		"apply": "filter_by_comparison_file",
		"comment": "discard or keep explicit excemptions as stated in excemption file, based on same identifier. source with absolute path or in the same working directory",
		"keep": False,
		"filesetting": {
			"source": "excemptions.*?.csv",
			"headerrowindex": 0,
			"columns": [
				"COMPAREFILEINDEX"
			]
		},
		"filter": [],
		"match": {
			"all":{
				"ORIGININDEX": "COMPAREFILEINDEX"
			},
			"any":{
				"ORIGININDEX": "COMPAREFILEINDEX"
			}
		},
		"transfer": {
			"NEWPARENTCOLUMN": "COMPARECOLUMN"
		}
	},
	*/
	public function filter_by_comparison_file($rule){
		if ($rule['filesetting']['source'] === 'SELF') $rule['filesetting']['source'] = $this->_originallist;
		if (array_key_exists('translations', $this->_setting)) $rule['translations'] = $this->_setting['translations'];
		if (array_key_exists('encoding', $this->_setting['filesetting']) && !array_key_exists('encoding', $rule['filesetting'])) $rule['filesetting']['encoding'] = $this->_setting['filesetting']['encoding'];
		if (array_key_exists('dialect', $this->_setting['filesetting']) && !array_key_exists('dialect', $rule['filesetting'])) $rule['filesetting']['dialect'] = $this->_setting['filesetting']['dialect'];
		$this->_log[] = '[*] comparing with '. (gettype($rule['filesetting']['source']) === 'string' ? $rule['filesetting']['source'] : 'self');
		$compare_list = new Listprocessor($rule, ['processedMonth' => $this->_argument['processedMonth'], 'processedYear' => $this->_argument['processedYear']], True);
		if (!array_key_exists(1, $compare_list->_list)) return;
		$equals = [];
		// reduce current list to avoid key errors on unset items
		$thislistwithoutempty = array_filter($this->_list->toArray(), fn($row, $index) => $row ? true : false, ARRAY_FILTER_USE_BOTH);
	
		// match lists
		foreach($thislistwithoutempty as $index => $self_row){
			foreach ($rule['match'] as $any_or_all => $compare_columns){
				$corresponds = [];
				foreach($compare_columns as $column => $cmp_column){
					if (in_array($self_row[$column], array_column($compare_list->_list[1], $cmp_column))){
						if (!array_key_exists($index, $corresponds)) $corresponds[$index] = [];
						$corresponds[$index][] = true;
					}
					if ($any_or_all === 'any') break;
				}
				foreach($corresponds as $index => $corresponding){
					if ($any_or_all === 'any' && count($corresponding) || ($any_or_all === 'all' && count($corresponding) === count(array_keys($compare_columns)))) {
						$equals[] = $index;
					}	
				}
			}
		}
		$equals = array_unique($equals);
		if ($rule['keep']){
			foreach (array_udiff(array_keys($thislistwithoutempty), $equals, fn($v1, $v2) => $v1 <=> $v2) as $index){
				$track = [
					'filter' => 'filter_by_comparison_file',
					'filtered_by' => json_encode($rule['match'])];
				$this->delete($index, $track);
			}	
		}
		else {
			foreach (array_uintersect(array_keys($thislistwithoutempty), $equals, fn($v1, $v2) => $v1 <=> $v2) as $index){
				$track = [
					'filter' => 'filter_by_comparison_file',
					'filtered_by' => json_encode($rule['match'])];
				$this->delete($index, $track);
			}	
		}

		// transfer values from comparison list to main list
		$thislistwithoutempty = array_filter($this->_list->toArray(), fn($row, $index) => $row ? true : false, ARRAY_FILTER_USE_BOTH);
		if (array_key_exists('transfer', $rule)){
			foreach ($rule['transfer'] as $newcolumn => $from){
				if (!array_key_exists($newcolumn, $this->setting['filesetting']['columns'])) $this->setting['filesetting']['columns'][] = $newcolumn;
				foreach (array_uintersect(array_column($thislistwithoutempty, $column), array_column($compare_list->_list[1], $cmp_column), fn($v1, $v2) => $v1 <=> $v2) as $index => $columnvalue){
					$self_row = $this->_list[$index];						
					$self_row[$newcolumn] = $cmp_row[$from];
					$this->_list[$index] = $self_row;
				}
			}
		}
		$compare_list = $thislistwithoutempty = null; // release ressources
	}

	/* keep amount of duplicates of column value, ordered by another concatenated column values (asc/desc)
	{
		"apply": "filter_by_duplicates",
		"comment": "keep amount of duplicates of column value, ordered by another concatenated column values (asc/desc)",
		"keep": True,
		"duplicates": {
			"orderby": ["ORIGININDEX"],
			"descending": False,
			"column": "CUSTOMERID",
			"amount": 1
		}
	},
	*/
	public function filter_by_duplicates($rule){
		$duplicates = [];
		foreach ($this->_list as $i => $row){
			if (!$row) continue;

			$identifier = $row[$rule['duplicates']['column']];
			$orderby = [];
			foreach($rule['duplicates']['orderby'] as $k => $column){
				$orderby[] = $row[$column]; 
			}
			if (!array_key_exists($identifier, $duplicates)) $duplicates[$identifier] = [[implode('', $orderby), $i]];
			else array_push($duplicates[$identifier], [implode('', $orderby), $i]);
		}
		$descending = $rule['duplicates']['descending'];
		
		foreach($duplicates as &$multiple){
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

	/* keep or discard amount of random rows that match given column values
	{
		"apply": "filter_by_rand",
		"comment": "keep some random rows",
		"keep": True,
		"data": {
			"columns": {
				"SOMEFILTERCOLUMN", "hasvalue"
			},
			"amount": 10
		}
	}
	*/
	public function filter_by_rand($rule){
		$subset = [];
		foreach ($this->_list as $i => $row){
			if (!$row) continue;

			if (array_key_exists('columns', rule['data'])){
				$match = false;
				$columns = array_keys($rule['data']['columns']);
				for($c = 0; $c < count($columns); $c++){
					$column = $columns[$c];
					$match = boolval(preg_match('/' . $rule['data']['columns'][$column] . '/mi', $row[$column]));
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

	/* remove, replace or add column with fixed value or formula or replace regex pattern in existing column
	{
		"add":{
			"NEWCOLUMNNAME": "string",
			"ANOTHERCOLUMNNAME" : ["PRICE", "*1.5"]
		},
		"replace":[
			["NAME", "regex", "replacement"],
			[null, ";", ","],
			["SOMECOLUMN", "regex", "replacement", "replacementnewrow", "replacementanothernewrow"]
		],
		"remove": ["SOMEFILTERCOLUMN", "DEATH"],
		"rewrite":[
			{"Customer": ["CUSTOMERID", " separator ", "NAME"]}
		],
		"translate":{
			"DEPARTMENT": "departments"
		}
	},
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

							if (is_array($modifications[$modify][$key])){
								$expression = [];
								foreach ($modifications[$modify][$key] as $possible_col){
									$expression[] = array_key_exists($possible_col, $row) ? $row[$possible_col] : $possible_col;
								}
							}
							else
								$expression = [$modifications[$modify][$key]];
							$row[$key] = $this->calculate($expression);
							$this->_list[$i] = $row;
						}
						break;
					case 'replace':
						foreach ($this->_list as $i => $row){
							if (!$row) continue;

							foreach ($row as $column => $value) {
								for ($replacement = 2; $replacement < count($rule); $replacement++){
									if ((!$rule[0] || $rule[0] == $column) && preg_match('/'. $rule[1]. '/m', $value)){
										$replaced_value = preg_replace_callback(
											'/'. $rule[1]. '/m',
											function($matches) use ($rule, $replacement){
												// plain replacement of first match
												if (count($matches)<2) return $rule[$replacement];
												// replacement on peculiar position
												// ensure a pattern where $match[2] has to be replaced. match string start (^) if neccessary
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
									if (array_key_exists($column, $row)) $concatenate .= $row[$column];
									else $concatenate .= $column;
								}
								$row[$new_column] = $concatenate; // SplFixedArray has problems accessing nested elements, must assign array to key directly
								$this->_list[$i] = $row;
							}
						}
						break;
					case 'translate':
						if (!array_key_exists('translations', $this->_setting)) break;
						foreach ($this->_list as $i => $row) {
							if (!$row) continue;

							foreach($modifications as $translate => $translation){
								if (array_key_exists($row[$rule], $this->_setting['translations'][$rule]))
									$row[$key] = trim(preg_replace('/^' . $row[$rule] . '$/m', $this->_setting['translations'][$rule][$row[$rule]], $row[$rule]));
							}
							// SplFixedArray has problems accessing nested elements, must assign array to key directly
							$this->_list[$i] = $row;
						}
						break;
				}
			}
		}
	}
}
?>


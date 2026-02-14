<?php
/**
 * [CARO - Cloud Assisted Records and Operations](https://github.com/erroronline1/caro)  
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.  
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.  
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.  
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

namespace CARO\API;

// IF you get a connection to your erp-software via any api or accessible file dumps
// this feels quite like a hacky attempt to get a data interface.
// it might be necessary to dynamically adapt this module according to changing requirements
// especially customerdata regarding document fields

// UTILITY functions may be implemented within the examples, as _utility.php is included by default
// the base class contains examples of all implemented methods during delevopment.
// if you can only serve partially drop the methods or return null from your custom class

// you are responsible for a decent user input sanitation to avoid malicious injections

class _ERPINTERFACE {
	/**
	 * set to true if class has been successfully constructed
	 */
	public $_instatiated = null;

	/**
	 * set to true if products can be directly imported from the erp_interface, depends on successful consumables implementation 
	 */
	public $_productsimport = null;

	/**
	 * path to markdown file explaining the interface
	 */
	public $_readme = '';

	/**
	 * define expected methods to be overridden by actual interface class if available
	 * the application can check whether content can be expected if a call doe not return null
	 */
	public function __construct(){
		
	}

	/**
	 * retrieve users that have a birthday 
	 * @param string $from Y-m-d without time
	 * @return null|array
	 * 
	 * sanitize parameter according to the usecase e.g. dbo driver
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable basic call from notification module
	 */
	public function birthdaymessage($from = null){
		/**
		 * return [
		 * 		[
		 * 			'name'=> string,
		 *			'past' => bool
		 * 		],
		 * 		...
		 * ]
		 */
		return null;
	}

	/**
	 * retrieve current case states based on passed case numbers
	 * @param array $erp_case_numbers
	 * @return null|array
	 * 
	 * sanitize parameter according to the usecase e.g. dbo driver
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable basic call from notification module
	 */
	public function casestate($erp_case_numbers = []){
		/**
		 * return [
		 * 		'{erp_case_number}' => [
		 * 			'reimbursement'=> Y-m-d,
		 *			'inquiry' => Y-m-d,
		 *			'partiallygranted' => Y-m-d,
		 *			'granted' => Y-m-d,
		 *			'production' => Y-m-d,
		 *			'settled' => Y-m-d,
		 * 		],
		 * 		...
		 * ]
		 */
		return null;
	}

	/**
	 * retrieve calculatory case positions based on passed case numbers
	 * @param array $erp_case_numbers
	 * @return null|array
	 * 
	 * sanitize parameter according to the usecase e.g. dbo driver
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable basic call from application integrations
	 */
	public function casepositions($erp_case_numbers = []){
		/**
		 * return [
		 * 		'{erp_case_number}' => [
		 * 			'amount' => string,
		 * 			'text' => string,
		 * 			'contract_position' => string,
		 * 			'header_data' => string
		 * 		],
		 * 		...
		 * ]
		 */
		return null;
	}

	/**
	 * retrieve cases from the erp system by customer  
	 * customer selection is based on customerdata()-response and matches with whatever key is set to clearly identify a customer  
	 * all cases for matched customers are returned
	 * 
	 * returns results to select from on application level
	 * @param array|null $request as named array with columns to match, similar to customerdata()
	 * @return null|array
	 * 
	 * sanitize parameters according to the usecase e.g. dbo driver
	 */
	public function customercases($request = null){
		/**
		 * try to preserve the order passed from customerdata() to keep the refined weighted order
		 * 
		 * on !$request
		 * return [
		 * 		...$this->customerdata(),
		 * 		[
		 * 			'name' => string,
		 * 			'type' => string, // text, date, number
		 * 			'datalist' => array // for recommendations if applicable
		 * 		],
		 * 		...
		 * ]
		 * 
		 * return [
		 * 		'{patient}' => [ // e.g. a concatenation of patient name and date of birth
		 * 			'caseid' => string,
		 * 			'text' => string,
		 * 			'info' => string
		 * 		],
		 * 		...
		 * ]
		 */
		return null;
	}

	/**
	 * retrieve most recent customer data details based on matching requests
	 * returns results to select from on application level
	 * @param array|null $request as named array with columns to match
	 * @return null|array
	 * 
	 * best practice would be to return available columns as input field names with type on empty request to determine options from the interface
	 * types as simple html input type of text, date, number etc.
	 * 
	 * customer data is supposed to be imported on filling out documents for records.
	 * as the interface must be adjusted to your specific usecase it is probably the easiest
	 * to just respond with fieldnames according to the available documents.
	 * i currently see no way nor reason to figure out a regular user way of assigning.
	 * 
	 * sanitize parameters according to the usecase e.g. dbo driver
	*/
	public function customerdata($request = null){
		/**
		 * on !$request
		 * return [
		 * 		[
		 * 			'name' => string,
		 * 			'type' => string, // text, date, number
		 * 			'datalist' => array // for recommendations if applicable
		 * 		],
		 * 		...
		 * ]
		 * 
		 * array keys according to record document field names, drop or append reasonable options, e.g. multilanguage if applicable
		 * return formats are supposed to match the respective input format, e.g. Y-m-d for date
		 * return [
		 * 		[
		 * 			'Family name' => string,
		 * 			'Given name' => string,
		 * 			'Name' => string, // glued
		 * 			'Date of Birth' => Y-m-d
		 * 			'Street' => string,
		 * 			'Number' => string,
		 * 			'Postal code' => string,
		 * 			'City' => string,
		 * 			'Country' => string,
		 * 			'Address' => string, // glued
		 * 			'Phone number' => string,
		 * 			'eMail address' => string,
		 * 			'Insurance' => string,
		 * 			'Patient number' => string // may be also required/useful for continued processing of customercases() if applicable
		 * 		],
		 * 		...
		 * ]
		 */
		return null;
	}

	/**
	 * if database connection is available you can formulate queries to retrieve a custom data dump
	 * without parameter available query keys are returned to be shown within the CARO App
	 * @param string|null $key
	 * @param array|null $params
	 * @return array|string array of available query keys, params or path to csv dump
	*/
	public function customcsvdump($key = null, $params = null){
		return null;

		/*
		$queries = [
			'Vorgangsexport' => [
				'description' => 'This export is suitable for this an that',
				'query' => <<<'END'
					SELECT * FROM database_table WHERE column = :userinput
					END,
				'params' => [
					':userinput' => [
						'name' => 'Column value',
						'type' => 'text', // currently default simple html input types (text, date, time, number)
						'default' => '',
						'function' => function($v){
							return $this->_pdo->quote($v); // process input somehow, date conversion or reasonable input sanitation to avoid malicious injections
						}
					],
				],
				'export' => [
					'pdf' => [],
					'ods' => [],
					'xlsx' => []
				]
			]
		];

		if (!$key) return array_keys($queries);
		if (!isset($queries[$key])) return null;
		$paramfields = [];
		// if not called with params return optional parameter settings for input
		if ($params === null) {
			if (!empty($queries[$key]['params'])) {
				foreach($queries[$key]['params'] as $property){
					$paramfields[$property['name']] = [
						'type' => $property['type'],
						'value' => is_callable($property['default']) ? $property['default']() : $property['default']
					];
				}
			}
			return ['params' => $paramfields, 'export' => !empty($queries[$key]['export']) ? array_keys($queries[$key]['export']): null];
		}
		// iterate over query params and overwrite defaults with passed params, then apply function
		if (isset($queries[$key]['params'])){
			foreach($queries[$key]['params'] as $param => $property){
				$default = is_callable($property['default']) ? $property['default']() : $property['default'];
				$paramfields[$param] = $default;
				if (isset($params[$property['name']])) {
					$paramfields[$param] = $params[$property['name']];
					unset($params[$property['name']]);
				}
				$paramfields[$param] = $property['function']($paramfields[$param]) ?? $property['function']($default);
			}
		}
		// determine export format
		$export = 'csv';
		foreach($params as $void => $value){
			$value = strtolower($value);
			if (isset($queries[$key]['export']) && isset($queries[$key]['export'][$value])) {
				$export = $value;
			}
		}

		try{
			$statement = $this->_pdo->prepare(strtr($queries[$key]['query'], $paramfields));
			$statement->execute();
		}
		catch(\EXCEPTION $e){
			UTILITY::debug($e, $statement->debugDumpParams());
		}
		$result = $statement->fetchAll();
		$statement = null;

		if ($result) {
			switch($export){
				case 'pdf':
					require_once('./_pdf.php');
					$PDF = new PDF(CONFIG['pdf']['table']);		
					$content = [
						'title' => "test",
						'date' => "",
						'content' => $result,
						'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $key)
					];
					return $PDF->tablePDF($content);
					break;
				default:
					$tempFile = UTILITY::directory('tmp') . '/' . date('Y-m-d H:i:s') . $key . '.csv';
					$file = fopen($tempFile, 'w');
					fwrite($file, b"\xEF\xBB\xBF"); // tell excel this is utf8
					fputcsv($file, array_keys($result),
						CONFIG['csv']['dialect']['separator'],
						CONFIG['csv']['dialect']['enclosure'],
						CONFIG['csv']['dialect']['escape']);
					foreach ($result as $line) {
						fputcsv($file, $line,
						CONFIG['csv']['dialect']['separator'],
						CONFIG['csv']['dialect']['enclosure'],
						CONFIG['csv']['dialect']['escape']);
					}
					fclose($file);
					return (substr(UTILITY::directory('tmp'), 1) . '/' . pathinfo($tempFile)['basename']);

					// OR USE INBUILT FUNCTION

					require_once('_table.php');
					$export = new TABLE([$result]);
					if ($files = $export->dump($key . date(' Y-m-d H-i-s') . '.' . $export)){
						return substr($files[0], 1);
					}
			}

		}
		return [];
		*/
	}

	/**
	 * retrieve recent data of erp consumables database
	 * @param array $vendors
	 * @param bool $as_passed false returns the remaining vendors but the passed ones
	 * @return null|array
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable basic call from application integrations
	*/
	public function consumables($vendors = [], $as_passed = true){
		/**
		 * return [
		 * 		'{vendor name}' => [
		 * 			[
		 * 				'article_no' => string|null,
		 * 				'article_name' => string|null,
		 * 				'article_ean' => string|null,
		 * 				'article_unit' => string|null,
		 * 				'article_info' => string|null,
		 * 				'trading_good' => 1|null,
		 * 				'has_expiry_date' => 1|null,
		 * 				'special_attention' => 1|null,
		 * 				'stock_item' => 1|null,
		 * 				'erp_id' => string,
		 * 				'last_order' => Y-m-d H:i:s
		 * 			],
		 * 			...
		 * 		],
		 *		... 		
		 * ]
		 */
		return null;
	}

	/**
	 * retrieve media files based on passed case numbers
	 * @param array $erp_case_numbers
	 * @return null|array
	 * 
	 * sanitize parameter according to the usecase e.g. dbo driver
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable basic call from application integrations
	 */
	public function media($erp_case_numbers = []){
		/**
		 * return [
		 * 		'{erp_case_number}' => [
		 *			'url' => string, // e.g 'data:' . $mime_type . ';base64,' . base64_encode($row['MEDIA']),
		 *			'description' => string,
		 *			'date' => string,
		 *			'filename' => string, // destination filename for accessing and downloading base64 url
		 * 		],
		 *		... 		
		 * ]
		 */
		return null;
	}

	/**
	 * retrieve recent data on processed orders for given timespan  
	 * return an array of orders to compare at application level
	 * @param string|null $from Y-m-d H:i:s
	 * @param string|null $until Y-m-d H:i:s
	 * @return null|array
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable identifier display within order module
	 */
	public function orderdata($from = null, $until = 'now'){
		/**
		 * convert passed dated to DateTime objects, with default values on erroneous parameters
		 * try {
		 * 		$from = new \DateTime($from ? : '2025-01-01 00:00:00');
		 * }
		 * catch (\Exception $e){
		 * 		$from = new \DateTime('2025-01-01 00:00:00');
		 * }
		 * try {
		 * 		$until = new \DateTime($until);
		 * }
		 * catch (\Exception $e){
		 * 		$until = new \DateTime('now');
		 * }
		 * convert to erp supported date format
		 * $from = $from->format('Y-m-d H:i:s');
		 * $until = $until->format('Y-m-d H:i:s');
		 * 
		 * ...
		 * 
		 * foreach ($result as $row){
		 * 		// append to result only if a valid identifier has been found
		 * 		$valid_identifier = UTILITY::identifier($row['BESTELLTEXT'], null, false, false, true);
		 * 		if ($valid_identifier && $identifier = trim($valid_identifier)){ // string, part of order text previously pasted during order process generated by caro app in the scheme `  #sz9623` two space-pound-base 36 unixtime according to UTILITY::identifier(), format accordingly
		 *			if (!isset($response[$identifier])) $response[$identifier] = [];
		 *			$response[$identifier][] = [
		 *				'vendor' => string,
		 *				'article_no' => string,
		 *				'article_name' => string,
		 *				'ordered' =>Y-m-d H:i:s,
		 *				'delivered_partially' => Y-m-d H:i:s,
		 *				'delivered_full' =>Y-m-d H:i:sl,
		 *				'order_reference' => string, some identifier from the erp software, may make things easier for purchase on requests
		 *			];
		 * 		}
		 * }
		 * return $response
		 */
		return null;
	}

	/**
	 * retrieve processed orders for given timespan and customer selection, based on customerdata()-response and matched with whatever key is set to clearly identify a customer  
	 * all processed orders for matched customers are returned
	 * return an array of orders to compare at application level
	 * @param array|null $request as named array with columns to match, similar to customerdata()
	 * @return null|array
	 * 
	 * returns results to select from on application level
	 * availability of the method must be signalled by something, preferably [[]] to enable identifier display within order module
	 * also see orderdata for similarities. preparing response differs though
	 */
	public function pastorders($request = null){
		/**
		 * on !$request
		 * return [
		 * 		...$this->customerdata(),
		 * 		[
		 * 			'name' => string,
		 * 			'type' => string, // text, date, number
		 * 			'datalist' => array // for recommendations if applicable
		 * 		],
		 * 		...
		 * ]
		 * 
		 * return [
		 * 		'{patient}' => [
		 *			'vendor' => string,
		 *			'article_no' => string,
		 *			'article_name' => string,
		 *			'ordered' => Y-m-d,
		 *			'amount' => string,
		 *			'delivered_full' => Y-m-d,
		 * 		],
		 *		... 		
		 * ]
		 */
		return null;	}

	/**
	 * retrieve expected file options for structured uploads of erp-data-files  
	 * possibly used by other methods as custom source if database connections are not available  
	 * returns an array of usecase descriptions and filenames to rename to
	 * @return null|array
	 * 
	 * files are stored within UTILITY::directory('erp_documents'),  
	 * access them from the other methods using UTILITY::directory('erp_documents') . '/intended_name.csv' as path
	 * 
	 * not necessarily csv files but whatever you can process in your custom methods
	 */
	public function upload(){
		return null;

		/**
		 * return [
		 * 		[
		 * 			'option' => 'file named like possible_dump_name.csv as data source for ...' // keep it short as it is part of the option string within select element
		 * 			'rename' => 'intended_name', // without extension!
		 * 		],
		 * 		...
		 * ]
		 */
	}
}

class TEST extends _ERPINTERFACE {
	/**
	 * set to true if class has been successfully constructed
	 */
	public $_instatiated = null; 

	/**
	 * set to true if products can be directly imported from the erp_interface, depends on successful consumables implementation 
	 */
	public $_productsimport = null;

	public function __construct(){
		try {
			parent::__construct();
			$this->_instatiated = true;
		}
		catch(\Exception $e){
			return null;
		}
	}

	/**
	 * retrieve users that have a birthday 
	 * @param string $from Y-m-d without time
	 * @return null|array
	 * 
	 * sanitize parameter according to the usecase e.g. dbo driver
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable basic call from notification module
	 */
	public function birthdaymessage($from = null){
		if (!$from) return [[]];
		
		$csvfile = fopen('../unittests/sample-csv-files-sample-6.csv', 'r');
		if (fgets($csvfile, 4) !== "\xef\xbb\xbf") rewind($csvfile); // BOM not found - rewind pointer to start of file.
		$i=0;
		$today = new \DateTime(date('Y-m-d')); // not 'now' for hours reasons. datetime should be at 0:00 o'clock
		while(($row = fgetcsv($csvfile, null, CONFIG['csv']['dialect']['separator'], CONFIG['csv']['dialect']['enclosure'], CONFIG['csv']['dialect']['escape'])) !== false) {
			if ($i++ < 2) continue;
			$row_date = new \DateTime($row[2]);
			$response[] = [
				'name' => implode(' ', [$row[0], $row[1]]),
				'past' => boolval($row_date->format('m-d') !== $today->format('m-d'))
			];
		}
		fclose($csvfile);
		
		return $response;
	}

	/**
	 * retrieve current case states based on passed case numbers
	 * @param array $erp_case_numbers
	 * @return null|array
	 * 
	 * sanitize parameter according to the usecase e.g. dbo driver
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable basic call from notification module
	 */
	public function casestate($erp_case_numbers = []){
		return [
			'12345' => [
				'reimbursement' => null,
				'inquiry' => null,
				'partiallygranted' => null,
				'granted' => '2025-08-30',
				'production' => null,
				'settled' => '2025-09-01',
				],
		];
	}

	/**
	 * retrieve most recent customer data details based on matching requests
	 * returns results to select from on application level
	 * @param array|null $request as named array with columns to match
	 * @return null|array
	 * 
	 * best practice would be to return available columns as input field names with type on empty request to determine options from the interface
	 * types as simple html input type of text, date, number etc.
	 * 
	 * customer data is supposed to be imported on filling out documents for records.
	 * as the interface must be adjusted to your specific usecase it is probably the easiest
	 * to just respond with fieldnames according to the available documents.
	 * i currently see no way nor reason to figure out a regular user way of assigning.
	 * 
	 * sanitize parameters according to the usecase e.g. dbo driver
	 */
	public function customerdata($request = null){
		if (!$request){
			return [
				[
					'name' => 'Name',
					'type' => 'text'
				],
				[
					'name' => 'Date of birth',
					'type' => 'date'
				],
				[
					'name' => 'ERP ID',
					'type' => 'text'
				],
			];
		}

		return [
			[
				'Name' => 'Jane Doe',
				'Geburtsdatum' => '2003-02-01',
				'Adresse' => 'Somewhere over the Rainbow 5',
				'Telefonnummer' => '01234 56789'
			],
			[
				'Name' => 'Erika Musterfrau',
				'Geburtsdatum' => '2003-02-01',
				'Adresse' => 'Auf dem Holzweg 3',
				'Telefonnummer' => '09876 54321'
			]
		];
	}

	/**
	 * if database connection is available you can formulate queries to retrieve a custom data dump
	 * without parameter available query keys are returned to be shown within the CARO App
	 * @param string|null $key
	 * @param array|null $params
	 * @return array|string array of available query keys, params or path to csv dump
	*/
	public function customcsvdump($key = null, $params = null){
		$queries = [
			'random query' => 'fictional_file.csv',
			'random query 2' => 'another_fictional_file.csv',
		];

		if (!$key) return array_keys($queries);
		if (!isset($queries[$key])) return null;
		if ($params === null) return [];

		return substr(UTILITY::directory('tmp'), 1) . '/' . $queries[$key];
		return [];
	}

	/**
	 * retrieve recent data on processed orders for given timespan  
	 * return an array of orders to compare at application level
	 * @param string|null $from Y-m-d H:i:s
	 * @param string|null $until Y-m-d H:i:s
	 * @return null|array
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable identifier display within order module
	 */
	public function orderdata($from = null, $until = 'now'){
		// convert passed dated to DateTime objects, with default values on erroneous parameters
		try {
			$from = new \DateTime($from ? : '2025-01-01 00:00:00');
		}
		catch (\Exception $e){
			$from = new \DateTime('2025-01-01 00:00:00');
		}
		try {
			$until = new \DateTime($until);
		}
		catch (\Exception $e){
			$until = new \DateTime('now');
		}
		// convert to erp supported date format
		$from = $from->format('Y-m-d H:i:s'); // or 'd.m.Y H:i:s'
		$until = $until->format('Y-m-d H:i:s'); // or 'd.m.Y H:i:s'

		return [
			'#sz9623' => [
				[
					'vendor' => 'Otto Bock HealthCare Deutschland GmbH',
					'article_no' => '99B25',
					'article_name' => 'Schlauch-Strumpf',
					'ordered' => '2025-09-01 21:00:00',
					'delivered_partially' => null,
					'delivered_full' => '2025-09-01 21:00:00',
					'order_reference' => '12345'
				],
			]
		];
	}


	/**
	 * retrieve expected file options for structured uploads of erp-data-files
	 * possibly used by other methods as custom source if database connections are not available  
	 * returns an array of usecase descriptions and filenames to rename to
	 * @return null|array
	 * 
	 * files are stored within UTILITY::directory('erp_documents'),  
	 * access them from the other methods using UTILITY::directory('erp_documents') . '/intended_name.csv' as path
	 * 
	 * not necessarily csv files but whatever you can process in your custom methods
	 */
	public function upload(){
		return [
			[
				'option' => 'ARTIKELMANAGER.csv as product database data source from erp export',
				'rename' => 'ARTIKELMANAGER'
			],
			[
				'option' => 'File named like EXPORT123.456.789.csv as case database data source from erp export',
				'rename' => 'VORGANGSEXPORT'
			],
			[
				'option' => 'AUSSCHLUSS.csv as case exceptions for comparison of erp export',
				'rename' => 'AUSSCHLUSS'
			],
		];
	}
}

class ODEVAVIVA extends _ERPINTERFACE {
	/**
	 * requires an sql connection to the optadata eva viva database
	 * readonly is fine, you'll may have to add the port to the host xyz.host.url, 1234
	 */
	private $_pdo = null;
	public $_instatiated = null;
	public $_readme = './CARO App ERP Interface ODEVAVIVA.md';

	/**
	 * set to true if products can be directly imported from the erp_interface, depends on successful consumables implementation 
	 */
	public $_productsimport = true;

	public function __construct(){
		try {
			parent::__construct();

			$options = [
				\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // always fetch assoc
				\PDO::ATTR_EMULATE_PREPARES   => true, // reuse tokens in prepared statements
				//\PDO::ATTR_PERSISTENT => true // persistent connection for performance reasons, unsupported as of 2/25 on sqlsrv?
			];
			$this->_pdo = new \PDO( CONFIG['sql'][CONFIG['system']['erp']]['driver'] . ':' . CONFIG['sql'][CONFIG['system']['erp']]['host'] . ';' . CONFIG['sql'][CONFIG['system']['erp']]['charset'], CONFIG['sql'][CONFIG['system']['erp']]['user'], CONFIG['sql'][CONFIG['system']['erp']]['password'], $options);
			$this->_instatiated = true;
		}
		catch(\Exception $e){
			var_dump($e);
			return null;
		}
	}

	/**
	 * retrieve users that have a birthday 
	 * @param string $from Y-m-d without time
	 * @return null|array
	 * 
	 * sanitize parameter according to the usecase e.g. dbo driver
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable basic call from notification module
	 */
	public function birthdaymessage($from = null){
		if (!$from) return [[]];
		
		$query = <<<'END'
			SELECT
				a.NAME_1 as NACHNAME,
				a.NAME_2,
				a.NAME_3,
				a.NAME_4,
				SUBSTRING(CONVERT(varchar(255), a.GEBURTSDATUM, 23), 6, 5) AS DATE
			FROM [eva3_02_viva_souh].[dbo].[adressen] AS a INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia ON a.ADRESSART = ia.REFERENZ
			LEFT JOIN [eva3_02_viva_souh].[dbo].[adr_kunden] AS more ON more.ADRESSEN_REFERENZ = a.REFERENZ
			WHERE ia.BEZEICHNUNG = 'Mitarbeiter'
			AND SUBSTRING(CONVERT(varchar(255), a.GEBURTSDATUM, 23), 6, 5) IN (:span)
			END;

		try {
			$date = new \DateTime($from);
		}
		catch(\EXCEPTION $e){
			return [[]];
		}
		$today = new \DateTime(date('Y-m-d')); // not 'now' for hours reasons. datetime should be at 0:00 o'clock
		$span = [
			$this->_pdo->quote($date->format('m-d'))
		];
		while($date < $today){
			$span[] = $this->_pdo->quote($date->modify('+1 day')->format('m-d'));
		}

		$statement = $this->_pdo->prepare(strtr($query, [
			':span' => implode(',', $span)
		]));
		$statement->execute();
		$result = $statement->fetchAll();
		$statement = null;
		$response = [];

		foreach ($result as $row){
			$response[] = [
				'name' => implode(' ', array_filter([$row['NAME_2'], $row['NAME_3'], $row['NAME_4']], Fn($v) => $v)) . ' ' . $row['NACHNAME'],
				'past' => boolval($row['DATE'] !== $today->format('m-d'))
			];
		}
		return $response;
	}

	/**
	 * retrieve current case states based on passed case numbers
	 * @param array $erp_case_numbers
	 * @return null|array
	 * 
	 * sanitize parameter according to the usecase e.g. dbo driver
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable basic call from notification module
	 */
	public function casestate($erp_case_numbers = []){ 
		$query = <<<'END'
		SELECT
			REFERENZ,
			CONVERT(varchar(255), KV_DATUM, 104) AS KV_DATUM,
			CONVERT(varchar(255), GENEHMIGT_DATUM, 104) AS GENEHMIGT_DATUM,
			AUFTRAGSWERT_BRUTTO,
			GENEHMIGT_TEILSUMME,
			CONVERT(varchar(255), FAKTURIERT_DATUM, 104) AS FAKTURIERT_DATUM,
			[sys].GENEHMIGT
		FROM [eva3_02_viva_souh].[dbo].[vorgaenge]
		LEFT JOIN (
			SELECT
				KENNZEICHEN,
				BEZEICHNUNG AS GENEHMIGT
			FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
			WHERE AUSWAHLART = 'AuftragsGenehmigung'
		) AS [sys] ON [sys].KENNZEICHEN = vorgaenge.GENEHMIGT

		WHERE REFERENZ IN (:ref)
		END;

		if (!$erp_case_numbers) return [[]];
		try{
			$statement = $this->_pdo->prepare(strtr($query, [
				':ref' => implode(',', array_map(fn($ref) => $this->_pdo->quote(intval($ref)), $erp_case_numbers))
			]));
			$statement->execute();	
		}
		catch(\EXCEPTION $e){
			UTILITY::debug($e, $statement->debugDumpParams());
		}
		$result = $statement->fetchAll();
		$statement = null;
		$response = [];

		foreach ($result as $row){
			$response[$row['REFERENZ']] = [
				'reimbursement' => $row['KV_DATUM'] ? : null,
				'inquiry' => in_array($row['GENEHMIGT'], ['Klärung']) ? true : null,
				'partiallygranted' => in_array($row['GENEHMIGT'], ['teilgenehmigt']) ? $row['GENEHMIGT_DATUM'] : null,
				'granted' => in_array($row['GENEHMIGT'], ['genehmigt', 'genehmigungsfrei']) ? $row['GENEHMIGT_DATUM'] : null,
				'production' => null,
				'settled' => $row['FAKTURIERT_DATUM'] ? : null,
			];
		}
		return $response;
	}

	/**
	 * retrieve calculatory case positions based on passed case numbers
	 * @param array $erp_case_numbers
	 * @return null|array
	 * 
	 * sanitize parameter according to the usecase e.g. dbo driver
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable basic call from application integrations
	 */
	public function casepositions($erp_case_numbers = []){
		$query = <<<'END'
		SELECT
			pos.VORGAENGE_REFERENZ,
			pos.POSITION,
			pos.ANZAHL,
			pos.POSITIONSTEXT,
			vorgang.KV_DATUM,
			vorgang.GENEHMIGT_DATUM,
			vorgang.LEISTUNG,
			CONCAT(pat.NAME_2, ' ' , pat.NAME_3, ' ' , pat.NAME_4, ' ' , pat.NACHNAME) as patientenname,
			pat.GEBURTSDATUM,
			KOSTENTRAEGER.NAME_1 as KOSTENTRAEGER
		FROM [eva3_02_viva_souh].[dbo].[vor_positionen] as pos
		LEFT JOIN (
			SELECT
				REFERENZ,
				ADRESSEN_REFERENZ,
				CONVERT(varchar(255), KV_DATUM, 104) AS KV_DATUM,
				CONVERT(varchar(255), GENEHMIGT_DATUM, 104) AS GENEHMIGT_DATUM,
				AUFTRAGSWERT_BRUTTO,
				GENEHMIGT_TEILSUMME,
				CONVERT(varchar(255), FAKTURIERT_DATUM, 104) AS FAKTURIERT_DATUM,
				LEISTUNG
			FROM [eva3_02_viva_souh].[dbo].[vorgaenge]
		) AS vorgang ON pos.VORGAENGE_REFERENZ = vorgang.REFERENZ
		LEFT JOIN (
			SELECT a.NAME_1 as NACHNAME,
				a.NAME_2,
				a.NAME_3,
				a.NAME_4,
				CONVERT(varchar(255), a.GEBURTSDATUM, 23) as GEBURTSDATUM,
				a.REFERENZ,
				more.KOSTENTRAEGER AS KOSTENTRAEGER_REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[adressen] AS a INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia ON a.ADRESSART = ia.REFERENZ
			LEFT JOIN [eva3_02_viva_souh].[dbo].[adr_kunden] AS more ON more.ADRESSEN_REFERENZ = a.REFERENZ
			WHERE ia.BEZEICHNUNG = 'Kunden / Patienten'
		) AS pat ON pat.REFERENZ = vorgang.ADRESSEN_REFERENZ 
		LEFT JOIN
		(
			SELECT ka.NAME_1,
			ka.REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[adressen] AS ka INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS kia ON ka.ADRESSART = kia.REFERENZ
			WHERE kia.BEZEICHNUNG = 'Kostenträger'
		) AS KOSTENTRAEGER ON pat.KOSTENTRAEGER_REFERENZ = KOSTENTRAEGER.REFERENZ
		WHERE

		pos.POSITIONSTEXT IS NOT NULL AND pos.POSITIONSTEXT != ''
		AND pos.VORGAENGE_REFERENZ IN (:ref)
		
		ORDER BY ID ASC
		END;

		if (!$erp_case_numbers) return [[]];
		try{
			$statement = $this->_pdo->prepare(strtr($query, [
				':ref' => implode(',', array_map(fn($ref) => $this->_pdo->quote(intval($ref)), $erp_case_numbers))
			]));
			$statement->execute();	
		}
		catch(\EXCEPTION $e){
			UTILITY::debug($e, $statement->debugDumpParams());
		}
		$result = $statement->fetchAll();
		$statement = null;
		$response = [];

		foreach ($result as $row){
			if (!isset($response[$row['VORGAENGE_REFERENZ']])) $response[$row['VORGAENGE_REFERENZ']] = [];
			$response[$row['VORGAENGE_REFERENZ']][] = [
				'amount' => $row['ANZAHL'] ? : '',
				'contract_position' => $row['POSITION'] ? : '',
				'text' => $row['POSITIONSTEXT'] ? : '',
				'header_data' => ($row['LEISTUNG'] ? : '') . ' ' . ($row['patientenname'] ? : '') . ' ' . ($row['GEBURTSDATUM'] ? : '') . ' ' . ($row['KOSTENTRAEGER'])
			];
		}
		return $response;
	}

	/**
	 * retrieve cases from the erp system by customer  
	 * customer selection is based on customerdata()-response and matches with whatever key is set to clearly identify a customer  
	 * all cases for matched customers are returned
	 * 
	 * returns results to select from on application level
	 * @param array|null $request as named array with columns to match, similar to customerdata()
	 * @return null|array
	 * 
	 * sanitize parameters according to the usecase e.g. dbo driver
	 */
	public function customercases($request = null){ 
		$query = <<<'END'
		SELECT
			vorgang.REFERENZ,
			vorgang.LEISTUNG,
			CONVERT(varchar(255), vorgang.KV_DATUM, 104) AS KV_DATUM,
			sys1.GENEHMIGT,
			CONVERT(varchar(255), vorgang.GENEHMIGT_DATUM, 104) AS GENEHMIGT_DATUM,
			sys2.GELIEFERT,
			CONVERT(varchar(255), vorgang.GELIEFERT_DATUM, 104) AS GELIEFERT_DATUM,
			sys3.FAKTURIERT,
			CONVERT(varchar(255), vorgang.FAKTURIERT_DATUM, 104) AS FAKTURIERT_DATUM,
			pat.REFERENZ as Patientennummer,
			UNIT.BETRIEB
		FROM [eva3_02_viva_souh].[dbo].[vorgaenge] as vorgang
		INNER JOIN (
			SELECT
				KENNZEICHEN,
				BEZEICHNUNG AS GENEHMIGT
			FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
			WHERE AUSWAHLART = 'AuftragsGenehmigung'
		) AS [sys1] ON [sys1].KENNZEICHEN = vorgang.GENEHMIGT
		INNER JOIN (
			SELECT
				KENNZEICHEN,
				BEZEICHNUNG AS GELIEFERT
			FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
			WHERE AUSWAHLART = 'AuftragsLieferung'
		) AS [sys2] ON [sys2].KENNZEICHEN = vorgang.GELIEFERT
		INNER JOIN (
			SELECT
				KENNZEICHEN,
				BEZEICHNUNG AS FAKTURIERT
			FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
			WHERE AUSWAHLART = 'AuftragsFakturierung'
		) AS [sys3] ON [sys3].KENNZEICHEN = vorgang.FAKTURIERT
		INNER JOIN (
			SELECT
				a.NAME_1 as NACHNAME,
				a.NAME_2,
				a.NAME_3,
				a.NAME_4,
				CONVERT(varchar(255), a.GEBURTSDATUM, 23) as GEBURTSDATUM,
				a.REFERENZ,
				more.KOSTENTRAEGER AS KOSTENTRAEGER_REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[adressen] AS a INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia ON a.ADRESSART = ia.REFERENZ
			LEFT JOIN [eva3_02_viva_souh].[dbo].[adr_kunden] AS more ON more.ADRESSEN_REFERENZ = a.REFERENZ
			WHERE ia.BEZEICHNUNG = 'Kunden / Patienten'
		) AS pat ON pat.REFERENZ = vorgang.ADRESSEN_REFERENZ
		INNER JOIN
		(
			SELECT
				names.NAME_3 AS BETRIEB,
				unit.ADRESSEN_REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[adr_betrieb] AS unit
			INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS names ON unit.ADRESSEN_REFERENZ = names.REFERENZ
			INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS unita ON unita.REFERENZ = names.ADRESSART
			WHERE unita.BEZEICHNUNG = 'Betrieb / Filiale'
			AND names.NAME_3 LIKE :unit
		) AS UNIT ON vorgang.BETRIEB = UNIT.ADRESSEN_REFERENZ

		WHERE pat.REFERENZ IN (:ref)
		AND vorgang.LEISTUNG IS NOT NULL

		ORDER BY vorgang.ID DESC
		END;

		if (!$request) {
			$inputs = $this->customerdata();

			$unitquery = <<<END
				SELECT
					names.NAME_3 AS BETRIEB,
					unit.ADRESSEN_REFERENZ
				FROM [eva3_02_viva_souh].[dbo].[adr_betrieb] AS unit
				INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS names ON unit.ADRESSEN_REFERENZ = names.REFERENZ
				INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS unita ON unita.REFERENZ = names.ADRESSART
				WHERE unita.BEZEICHNUNG = 'Betrieb / Filiale'
				AND names.NAME_3 IS NOT NULL
			END;

			try{
				$statement = $this->_pdo->prepare($unitquery);
				$statement->execute();	
			}
			catch(\EXCEPTION $e){
				UTILITY::debug($e, $statement->debugDumpParams());
			}
			$result = $statement->fetchAll();
			$statement = null;
			if ($datalist = array_column($result, 'BETRIEB')){
				// this may extend inputs and handle available languages as well!
				$language = $_SESSION['user']['app_settings']['language'] ?? CONFIG['application']['defaultlanguage'];
				switch($language){
					case 'en':
						array_push($inputs, 
							[
								'name' => 'Unit',
								'type' => 'text',
								'datalist' => $datalist
							]
						);
					case 'de':
						array_push($inputs, 
							[
								'name' => 'Fachbereich',
								'type' => 'text',
								'datalist' => $datalist
							]
						);
				}
			}

			return $inputs;
		}

		$requested_unit = $request['Unit'] ?? $request['Fachbereich'];
		$requested_unit = $requested_unit ? trim($requested_unit) : '%';

		if (!($customers = $this->customerdata($request))) return [[]];
		try{
			$statement = $this->_pdo->prepare(strtr($query, [
				':ref' => implode(',', array_map(fn($ref) => $this->_pdo->quote($ref['ERPNR']), $customers)),
				':unit' => $this->_pdo->quote($requested_unit)
			]));
			$statement->execute();	
		}
		catch(\EXCEPTION $e){
			UTILITY::debug($e, $statement->debugDumpParams());
		}
		$result = $statement->fetchAll();
		$statement = null;
		$pre_response = $response = [];

		// prepare response by iterating over customers to preserve customerdata refinement regarding weight
		foreach($customers as $customer){
			$pre_response[$customer['ERPNR']] = [
				'patient' => $customer['Name'] . ' *' . $customer['Geburtsdatum'],
				'cases' => []
			];

		}
		// insert cases
		foreach ($result as $row){
			$pre_response[$row['Patientennummer']]['cases'][] = [
				'caseid' => $row['REFERENZ'],
				'text' => $row['LEISTUNG'],
				'info' => implode(', ', array_filter([
					$row['BETRIEB'],
					($row['KV_DATUM'] ? 'KV (' . $row['KV_DATUM'] . ')' : null ),
					$row['GENEHMIGT'] . ( ($row['GENEHMIGT_DATUM'] ? ' (' . $row['GENEHMIGT_DATUM'] . ')' : '' ) ),
					$row['GELIEFERT'] . ( ($row['GELIEFERT_DATUM'] ? ' (' . $row['GELIEFERT_DATUM'] . ')' : '' ) ),
					$row['FAKTURIERT'] . ( ($row['FAKTURIERT_DATUM'] ? ' (' . $row['FAKTURIERT_DATUM'] . ')' : '' ) )
				], fn($v) => $v)),
			];
		}
		// skip empty, make patient name key for response
		foreach($pre_response as $patient){
			if (!$patient['cases']) continue;
			$response[$patient['patient']] = $patient['cases'];
		}

		return $response;
	}

	/**
	 * retrieve most recent customer data details based on matching requests
	 * returns results to select from on application level
	 * @param array|null $request as named array with columns to match
	 * @return null|array
	 * 
	 * best practice would be to return available columns as input field names with type on empty request to determine options from the interface
	 * types as simple html input type of text, date, number etc.
	 * 
	 * customer data is supposed to be imported on filling out documents for records.
	 * as the interface must be adjusted to your specific usecase it is probably the easiest
	 * to just respond with fieldnames according to the available documents.
	 * i currently see no way nor reason to figure out a regular user way of assigning.
	 * 
	 * sanitize parameters according to the usecase e.g. dbo driver
	 */
	public function customerdata($request = null){
		/**
		 * array keys according to record document field names, drop or append reasonable options, e.g. multilanguage if applicable
		 */
		$query = <<<'END'
		SELECT 
			pat.*,
			KOSTENTRAEGER.NAME_1 AS KOSTENTRAEGER_NAME,
			EMAIL.EMAIL,
			PHONE.PHONE,
			MOBILE.PHONE AS MOBILE
		FROM
		(
			SELECT
				a.NAME_1 as NACHNAME,
				a.NAME_2,
				a.NAME_3,
				a.NAME_4,
				a.GEBURTSNAME,
				CONVERT(varchar(255), a.GEBURTSDATUM, 23) as GEBURTSDATUM,
				a.STRASSE_1,
				a.PLZ_1,
				a.ORT_1,
				a.LKZ_1,
				a.FIBU_NUMMER,
				a.REFERENZ,
				more.KOSTENTRAEGER AS KOSTENTRAEGER_REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[adressen] AS a INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia ON a.ADRESSART = ia.REFERENZ
			LEFT JOIN [eva3_02_viva_souh].[dbo].[adr_kunden] AS more ON more.ADRESSEN_REFERENZ = a.REFERENZ
			WHERE ia.BEZEICHNUNG = 'Kunden / Patienten'
		) as pat
		LEFT JOIN
		(
			SELECT
				ka.NAME_1,
				ka.REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[adressen] AS ka INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS kia ON ka.ADRESSART = kia.REFERENZ
			WHERE kia.BEZEICHNUNG = 'Kostenträger'
		) AS KOSTENTRAEGER ON pat.KOSTENTRAEGER_REFERENZ = KOSTENTRAEGER.REFERENZ
		LEFT JOIN
		(
			SELECT
				mail.NUMMER AS EMAIL,
				mail.ADRESSEN_REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[adz_kontakte] AS mail INNER JOIN [eva3_02_viva_souh].[dbo].[inf_kontaktart] as im ON mail.KONTAKTART = im.REFERENZ
			WHERE im.BEZEICHNUNG = 'E-Mail'
		) AS EMAIL ON pat.REFERENZ = EMAIL.ADRESSEN_REFERENZ
		LEFT JOIN
		(
			SELECT
				mail.NUMMER AS PHONE,
				mail.ADRESSEN_REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[adz_kontakte] AS mail INNER JOIN [eva3_02_viva_souh].[dbo].[inf_kontaktart] as im ON mail.KONTAKTART = im.REFERENZ
			WHERE im.BEZEICHNUNG = 'Telefonnummer'
		) AS PHONE ON pat.REFERENZ = PHONE.ADRESSEN_REFERENZ
		LEFT JOIN
		(
			SELECT
				mail.NUMMER AS PHONE,
				mail.ADRESSEN_REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[adz_kontakte] AS mail INNER JOIN [eva3_02_viva_souh].[dbo].[inf_kontaktart] as im ON mail.KONTAKTART = im.REFERENZ
			WHERE im.BEZEICHNUNG = 'Handynummer'
		) AS MOBILE ON pat.REFERENZ = MOBILE.ADRESSEN_REFERENZ

		WHERE :dob :patientnumber :namesearch
		END;
		
		if (!$request) {
			// this may handle available languages as well!
			$language = $_SESSION['user']['app_settings']['language'] ?? CONFIG['application']['defaultlanguage'];
			switch($language){
				case 'en':
					return [
						[
							'name' => 'Name',
							'type' => 'text'
						],
						[
							'name' => 'Date of birth',
							'type' => 'date'
						],
						[
							'name' => 'Patient number',
							'type' => 'number'
						]
					];
				case 'de':
					return [
						[
							'name' => 'Name',
							'type' => 'text'
						],
						[
							'name' => 'Geburtsdatum',
							'type' => 'date'
						],
						[
							'name' => 'FiBu-Nummer',
							'type' => 'number'
						]
					];
			}
		}

		// read and sanitize requested parameters
		$requested_name = $request['Name'] ?? $request['Name'];
		$requested_name = $requested_name ? trim($requested_name) : '';
		$requested_dob = $request['Date of birth'] ?? $request['Geburtsdatum'];
		$requested_dob = $requested_dob ? trim($requested_dob) : '';
		$requested_patientnumber = $request['Patient number'] ?? $request['FiBu-Nummer'];
		$requested_patientnumber = $requested_patientnumber ? trim($requested_patientnumber) : '';

		if (
			!($requested_name || $requested_dob || $requested_patientnumber)
		) return [];
		
		$name = SEARCH::expressions($requested_name);
		$namesearch = [];
		foreach(['NACHNAME', 'NAME_2', 'NAME_3', 'NAME_4'] as $column){
			foreach($name as $namepart){
				$namesearch[] = 'pat.' . $column . ($namepart['operator'] === '-' ? ' NOT LIKE ' : ' LIKE ') . $this->_pdo->quote($namepart['sqlterm']);
			}
		}

		try{
			$statement = $this->_pdo->prepare(strtr($query, [
				':dob' => $requested_dob ? 'pat.GEBURTSDATUM = CONVERT(DATETIME, ' . $this->_pdo->quote($requested_dob . ' 00:00:00.000') . ', 21)': '',
				':patientnumber' => $requested_patientnumber
					? ($requested_dob ? ' AND ' : '') . 'pat.FIBU_NUMMER = ' . $this->_pdo->quote($requested_patientnumber)
					: '',
				':namesearch' => $namesearch 
					? ($requested_dob || $requested_patientnumber ? ' AND ' : '') . '(' . implode(' OR ', $namesearch) . ')'
					: ''
				]));
			$statement->execute();	
		}
		catch(\EXCEPTION $e){
			UTILITY::debug($e, $statement->debugDumpParams());
		}
		$result = $statement->fetchAll();
		$statement = null;

		$refinequery = implode(' +', array_filter([$requested_name, $requested_dob, $requested_patientnumber], Fn($v) => $v));

		$response = [];

		$result = SEARCH::refine($refinequery, $result, ['NAME_2', 'NAME_3', 'NAME_4', 'NACHNAME', 'GEBURTSDATUM', 'FIBU_NUMMER']);

		foreach ($result as $row){
			if (!SEARCH::filter($refinequery, [
				$row['NAME_2'],
				$row['NAME_3'],
				$row['NAME_4'],
				$row['NACHNAME'],
				$row['GEBURTSDATUM'],
				$row['FIBU_NUMMER'],
				])) continue;

			$patient = [
				//'Nachname' =>  $row['NACHNAME'],
				//'Vorname' => implode(' ', array_filter(array_map(fn($c) => $row[$c] ? : '', ['NAME_2', 'NAME_3', 'NAME_4']), fn($v) => boolval($v))),
				'Name' => implode(' ', array_filter(array_map(fn($c) => $row[$c] ? : '', ['NAME_2', 'NAME_3', 'NAME_4', 'NACHNAME']), fn($v) => boolval($v))),
				//'Straße' => $row['STRASSE_1'],
				//'Postleitzahl' => $row['PLZ_1'],
				'Geburtsdatum' => $row['GEBURTSDATUM'] ? : '', // must be Y-m-d to be importable into date input
				//'Stadt' => $row['ORT_1'],
				//'Land' => $row['LKZ_1'],
				'Adresse' => $row['STRASSE_1'] . ', ' . $row['LKZ_1'] . '-' . $row['PLZ_1'] . ' ' . $row['ORT_1'],
				'Telefonnummer' => $row['PHONE'],
				'Mobil' => $row['MOBILE'],
				'eMailadresse' => $row['EMAIL'],
				'Kostenträger' => $row['KOSTENTRAEGER_NAME'],
				'FIBU Nummer' => $row['FIBU_NUMMER'],
				'ERPNR' => $row['REFERENZ'] // customercases relies on this key
			];
			$response[] = $patient;
		}

		return $response;
	}


	/**
	 * if database connection is available you can formulate queries to retrieve a custom data dump
	 * without parameter available query keys are returned to be shown within the CARO App
	 * @param string|null $key
	 * @param array|null $params
	 * @return array|string array of available query keys, params or path to csv dump
	*/
	public function customcsvdump($key = null, $params = null){
		$queries = [
			'Vorgangsexport für Terminerinnerung' => [
				'description' => 'Alle gelieferten Fälle. Die Liste kann anschließend mit dem CSV-Filter für den Serienbriefversand vorbereitet werden.',
				'query' => <<<'END'
					SELECT
						vorgaenge.REFERENZ AS VORGANG,
						CONVERT(varchar(255), vorgaenge.ANLAGEDATUM, 104) AS ANLAGEDATUM,
						pat.ANREDE,
						pat.[PERSOENLICHE ANREDE],
						pat.REFERENZ AS KUNDENNUMMER,
						pat.NAME,
						pat.VORNAME,
						pat.STRASSE,
						pat.LKZ,
						pat.PLZ,
						pat.ORT,
						CONVERT(varchar(255), pat.GEBURTSDATUM, 104) AS GEBURTSDATUM,
						EMAIL.EMAIL,
						pat.STERBEDATUM,
						vorgaenge.LEISTUNG,
						FORMAT(vorgaenge.AUFTRAGSWERT_BRUTTO, 'C2', 'de-de') AS AUFTRAGSWERT_BRUTTO,
						'geliefert' AS GELIEFERT,
						CONVERT(varchar(255), vorgaenge.GELIEFERT_DATUM, 104) AS GELIEFERT_DATUM,
						[sys].GENEHMIGT,
						'nein' AS OHNE_SERIENBRIEF	
					FROM [eva3_02_viva_souh].[dbo].[vorgaenge]
					INNER JOIN (
						SELECT
							KENNZEICHEN,
							BEZEICHNUNG AS GENEHMIGT
						FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
						WHERE AUSWAHLART = 'AuftragsGenehmigung'
					) AS [sys] ON [sys].KENNZEICHEN = vorgaenge.GENEHMIGT
					INNER JOIN (
						SELECT
							a.NAME_1 AS NAME,
							concat(a.NAME_2, ' ', a.NAME_3, ' ', a.NAME_4) AS VORNAME,
							a.GEBURTSDATUM,
							a.STRASSE_1 AS STRASSE,
							a.PLZ_1 AS PLZ,
							a.ORT_1 AS ORT,
							a.LKZ_1 AS LKZ,
							a.REFERENZ,
							more.STERBEDATUM,
							t.ADRESS_ANREDE AS ANREDE,
							t.BRIEFKOPF_ANREDE AS [PERSOENLICHE ANREDE]
						FROM [eva3_02_viva_souh].[dbo].[adressen] AS a
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia ON a.ADRESSART = ia.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[adr_kunden] AS more ON more.ADRESSEN_REFERENZ = a.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_anreden] AS t ON t.REFERENZ = a.ANREDE 

						WHERE ia.BEZEICHNUNG = 'Kunden / Patienten'
						AND more.STERBEDATUM IS NULL
						AND more.OHNE_SERIENBRIEF = 0
					) AS pat ON vorgaenge.ADRESSEN_REFERENZ = pat.REFERENZ
					LEFT JOIN (
						SELECT
							mail.NUMMER AS EMAIL,
							mail.ADRESSEN_REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[adz_kontakte] AS mail
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_kontaktart] AS im ON mail.KONTAKTART = im.REFERENZ

						WHERE im.BEZEICHNUNG = 'E-Mail'
					) AS EMAIL ON pat.REFERENZ = EMAIL.ADRESSEN_REFERENZ
			
					WHERE vorgaenge.GELIEFERT_DATUM IS NOT NULL
					AND vorgaenge.AUFTRAGSWERT_BRUTTO > 10
					AND [sys].GENEHMIGT NOT IN ('Storno')

					AND vorgaenge.ANLAGEDATUM BETWEEN ':anlagedatumvon' AND ':anlagedatumbis'
					order by vorgaenge.REFERENZ ASC
					END,
				'params' => [
					':anlagedatumvon' => [
						'name' => 'Anlagedatum von',
						'type' => 'date', // currently default simple html input types (text, date, time, number)
						'default' => function(){
							$date = new \DateTime('now');
							return $date->modify('-5 years')->modify('first day of this month')->format('Y-m-d');
						},
						'function' => function($v){
							try {
								$date = new \DateTime($v);
								return $date->format('Y-m-d 0:00:00.000'); // process input somehow, date conversion or reasonable input sanitation to avoid malicious injections
							}
							catch(\EXCEPTION $e){
								return null;
							}
						}
					],
					':anlagedatumbis' => [
						'name' => 'Anlagedatum bis',
						'type' => 'date',
						'default' => date('Y-m-d'),
						'function' => function($v){
							try {
								$date = new \DateTime($v);
								return $date->format('Y-m-d 23:59:59.000');
							}
							catch(\EXCEPTION $e){
								return null;
							}
						}
					]
				]
			],
			'Vorgangsexport - alle' => [
				'description' => 'Liste aller Vorgänge, wie sie auch für die Inventurerfassung der Buchhaltung genutzt wird.',
				'query' => <<<'END'
					SELECT
						vorgaenge.REFERENZ AS VORGANG,
						CONVERT(varchar(255), vorgaenge.ANLAGEDATUM, 104) AS ANLAGEDATUM,
						pat.ANREDE,
						pat.REFERENZ AS KUNDENNUMMER,
						pat.NAME,
						pat.VORNAME,
						CONVERT(varchar(255), pat.GEBURTSDATUM, 104) AS GEBURTSDATUM,
						KOSTENTRAEGER.NAME_1 AS KOSTENTRAEGER,
						vorgaenge.LEISTUNG,
						FORMAT(vorgaenge.AUFTRAGSWERT_BRUTTO, 'C2', 'de-de') AS AUFTRAGSWERT_BRUTTO,
						[sys].GENEHMIGT,
						CONVERT(varchar(255), vorgaenge.GENEHMIGT_DATUM, 104) AS GENEHMIGT_DATUM,
						[sys2].GELIEFERT,
						CONVERT(varchar(255), vorgaenge.GELIEFERT_DATUM, 104) AS GELIEFERT_DATUM,
						[sys3].FAKTURIERT,
						CONVERT(varchar(255), vorgaenge.GELIEFERT_DATUM, 104) AS FAKTURIERT_DATUM,
						UNIT.BETRIEB,
						UNIT.ADRESSEN_REFERENZ AS BETRIEB_REFERENZ
					FROM [eva3_02_viva_souh].[dbo].[vorgaenge]
					INNER JOIN (
						SELECT
							KENNZEICHEN,
							BEZEICHNUNG AS GENEHMIGT
						FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
						WHERE AUSWAHLART = 'AuftragsGenehmigung'
					) AS [sys] ON [sys].KENNZEICHEN = vorgaenge.GENEHMIGT
					INNER JOIN (
						SELECT
							KENNZEICHEN,
							BEZEICHNUNG AS GELIEFERT
						FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
						WHERE AUSWAHLART = 'AuftragsLieferung'
					) AS [sys2] ON [sys2].KENNZEICHEN = vorgaenge.GELIEFERT
					INNER JOIN (
						SELECT
							KENNZEICHEN,
							BEZEICHNUNG AS FAKTURIERT
						FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
						WHERE AUSWAHLART = 'AuftragsFakturierung'
					) AS [sys3] ON [sys3].KENNZEICHEN = vorgaenge.FAKTURIERT
					INNER JOIN (
						SELECT
							a.NAME_1 AS NAME,
							concat(a.NAME_2, ' ', a.NAME_3, ' ', a.NAME_4) AS VORNAME,
							a.GEBURTSDATUM,
							a.STRASSE_1 AS STRASSE,
							a.PLZ_1 AS PLZ,
							a.ORT_1 AS ORT,
							a.LKZ_1 AS LKZ,
							a.REFERENZ,
							more.KOSTENTRAEGER AS KOSTENTRAEGER_REFERENZ,
							more.STERBEDATUM,
							t.ADRESS_ANREDE AS ANREDE
						FROM [eva3_02_viva_souh].[dbo].[adressen] AS a
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia ON a.ADRESSART = ia.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[adr_kunden] AS more ON more.ADRESSEN_REFERENZ = a.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_anreden] AS t ON t.REFERENZ = a.ANREDE 

						WHERE ia.BEZEICHNUNG = 'Kunden / Patienten'
						AND more.STERBEDATUM IS NULL
					) AS pat ON vorgaenge.ADRESSEN_REFERENZ = pat.REFERENZ
					INNER JOIN
					(
						SELECT
							names.NAME_3 AS BETRIEB,
							unit.ADRESSEN_REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[adr_betrieb] AS unit
						INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS names ON unit.ADRESSEN_REFERENZ = names.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS unita ON unita.REFERENZ = names.ADRESSART
						WHERE unita.BEZEICHNUNG = 'Betrieb / Filiale'
					) AS UNIT ON vorgaenge.BETRIEB = UNIT.ADRESSEN_REFERENZ
					LEFT JOIN
					(
						SELECT
							ka.NAME_1,
							ka.REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[adressen] AS ka INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS kia ON ka.ADRESSART = kia.REFERENZ
						WHERE kia.BEZEICHNUNG = 'Kostenträger'
					) AS KOSTENTRAEGER ON pat.KOSTENTRAEGER_REFERENZ = KOSTENTRAEGER.REFERENZ
			
					WHERE
					UNIT.ADRESSEN_REFERENZ IN (12, 14, 15, 16, 17, 18)
					AND [sys].GENEHMIGT NOT IN ('Storno')
					AND vorgaenge.ANLAGEDATUM BETWEEN ':anlagedatumvon' AND ':anlagedatumbis'
					ORDER BY vorgaenge.REFERENZ ASC
					END,
				'params' => [
					':anlagedatumvon' => [
						'name' => 'Anlagedatum von',
						'type' => 'date', // currently default simple html input types (text, date, time, number)
						'default' => function(){
							$date = new \DateTime('now');
							return $date->modify('-5 years')->modify('first day of this month')->format('Y-m-d');
						},
						'function' => function($v){
							try {
								$date = new \DateTime($v);
								return $date->format('Y-m-d 0:00:00.000'); // process input somehow, date conversion or reasonable input sanitation to avoid malicious injections
							}
							catch(\EXCEPTION $e){
								return null;
							}
						}
					],
					':anlagedatumbis' => [
						'name' => 'Anlagedatum bis',
						'type' => 'date',
						'default' => date('Y-m-d'),
						'function' => function($v){
							try {
								$date = new \DateTime($v);
								return $date->format('Y-m-d 23:59:59.000');
							}
							catch(\EXCEPTION $e){
								return null;
							}
						}
					]
				]
			],
			'EVA-Artikelstamm Lagerware z.B. für Inventur' => [
				'description' => 'Als Lagerware markierte Artikel. Die Liste kann anschließend mit dem CSV-Filter bereichsweise sortiert werden.',
				'query' => <<<'END'
					SELECT
						article.ARTIKEL_REFERENZ,
						vendor.NAME_1 as LIEFERANTEN_NAME,
						article.BESTELL_NUMMER,
						article.BESTELL_TEXT,
						unit.BEZEICHNUNG AS BESTELLEINHEIT,
						storage.LAGERORT,
						storage.LAGER_REFERENZ,
						CONVERT(varchar(255), article.WARENEINGANGSDATUM, 104) AS WARENEINGANGSDATUM
					FROM [eva3_02_viva_souh].[dbo].[wws_artikel_lieferanten] AS article
					INNER JOIN (
						SELECT
							BEZEICHNUNG,
							REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[inf_einheit]
					) AS unit ON unit.REFERENZ = article.BESTELL_EINHEIT
					INNER JOIN (
						SELECT
							ARTIKEL_REFERENZ,
							LAGERORT,
							strg_n.BEZEICHNUNG as LAGER_REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[wws_lagerbestand] AS strg
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_lager] AS strg_n ON strg.LAGER_REFERENZ = strg_n.REFERENZ
						WHERE 
						(
							(
								strg_n.BEZEICHNUNG LIKE 'Zentrallager' OR
								strg_n.BEZEICHNUNG LIKE 'Gießraum' OR
								strg_n.BEZEICHNUNG LIKE 'Bandagisten' OR
								strg_n.BEZEICHNUNG LIKE 'Prothetik' OR
								strg_n.BEZEICHNUNG LIKE 'Orthetik' OR
								strg_n.BEZEICHNUNG LIKE 'Armorthetik und -prothetik' OR
								strg_n.BEZEICHNUNG LIKE 'Dysmelie' OR
								strg_n.BEZEICHNUNG LIKE 'Kunststoffabteilung' OR
								strg_n.BEZEICHNUNG LIKE 'Silikonabteilung'
							)
							AND (
								(
									strg.LAGERORT IS NOT NULL AND strg.LAGERORT != 'null' AND strg.LAGER_REFERENZ IS NOT NULL
								) OR (
									strg.LAGER_MINDESTBESTAND > 0
								) 
							)
						) OR
						(
							strg_n.BEZEICHNUNG LIKE 'Ambulan%' OR
							strg_n.BEZEICHNUNG LIKE 'Kopfklinik%' OR
							strg_n.BEZEICHNUNG LIKE 'B1%' OR
							strg_n.BEZEICHNUNG LIKE 'OP%'
						)
					) AS storage ON storage.ARTIKEL_REFERENZ = article.ARTIKEL_REFERENZ
					INNER JOIN (
						SELECT 
							v.NAME_1,
							v.REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia
						INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS v ON v.ADRESSART = ia.REFERENZ
						WHERE ia.BEZEICHNUNG = 'Lieferanten'
					) AS vendor ON article.LIEFERANTEN_REFERENZ = vendor.REFERENZ
					
					WHERE article.STATUS = 0 AND article.PRIMAER_LIEFERANT = 1

					ORDER BY LAGER_REFERENZ, LAGERORT, LIEFERANTEN_NAME, BESTELL_NUMMER, BESTELL_TEXT
					END,
				'params' => []
			],
			'EVA-Artikelstamm komplett' => [
				'description' => 'Der komplette Artikelstamm, wie er auch für die Inventurerfassung der Buchhaltung genutzt wird.',
				'query' => <<<'END'
					SELECT
						article.ARTIKEL_REFERENZ,
						vendor.NAME_1 as LIEFERANTEN_NAME,
						article.BESTELL_NUMMER,
						article.BESTELL_TEXT,
						unit.BEZEICHNUNG AS BESTELLEINHEIT,
						storage.LAGERORT,
						storage.LAGER_REFERENZ,
						CONVERT(varchar(255), article.WARENEINGANGSDATUM, 104) AS WARENEINGANGSDATUM
					FROM [eva3_02_viva_souh].[dbo].[wws_artikel_lieferanten] AS article
					INNER JOIN (
						SELECT
							BEZEICHNUNG,
							REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[inf_einheit]
					) AS unit ON unit.REFERENZ = article.BESTELL_EINHEIT
					LEFT JOIN (
						SELECT
							ARTIKEL_REFERENZ,
							LAGERORT,
							strg_n.BEZEICHNUNG as LAGER_REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[wws_lagerbestand] AS strg
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_lager] AS strg_n ON strg.LAGER_REFERENZ = strg_n.REFERENZ
						WHERE 
						(
							(
								strg_n.BEZEICHNUNG LIKE 'Zentrallager' OR
								strg_n.BEZEICHNUNG LIKE 'Gießraum' OR
								strg_n.BEZEICHNUNG LIKE 'Bandagisten' OR
								strg_n.BEZEICHNUNG LIKE 'Prothetik' OR
								strg_n.BEZEICHNUNG LIKE 'Orthetik' OR
								strg_n.BEZEICHNUNG LIKE 'Armorthetik und -prothetik' OR
								strg_n.BEZEICHNUNG LIKE 'Dysmelie' OR
								strg_n.BEZEICHNUNG LIKE 'Kunststoffabteilung' OR
								strg_n.BEZEICHNUNG LIKE 'Silikonabteilung'
							)
						) OR
						(
							strg_n.BEZEICHNUNG LIKE 'Ambulan%' OR
							strg_n.BEZEICHNUNG LIKE 'Kopfklinik%' OR
							strg_n.BEZEICHNUNG LIKE 'B1%' OR
							strg_n.BEZEICHNUNG LIKE 'OP%'
						)
					) AS storage ON storage.ARTIKEL_REFERENZ = article.ARTIKEL_REFERENZ
					INNER JOIN (
						SELECT 
							v.NAME_1,
							v.REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia
						INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS v ON v.ADRESSART = ia.REFERENZ
						WHERE ia.BEZEICHNUNG = 'Lieferanten'
					) AS vendor ON article.LIEFERANTEN_REFERENZ = vendor.REFERENZ
					
					WHERE article.STATUS = 0 AND article.PRIMAER_LIEFERANT = 1

					ORDER BY LAGER_REFERENZ, LAGERORT, LIEFERANTEN_NAME, BESTELL_NUMMER, BESTELL_TEXT
					END,
				'params' => []
			],
			'Falllisten laufende Fälle' => [
				'description' => 'Alle laufenden Fälle. Die Liste kann anschließend mit dem CSV-Filter bereichsweise sortiert werden.',
				'query' => <<<'END'
					SELECT
						vorgaenge.REFERENZ AS VORGANG,
						CONVERT(varchar(255), vorgaenge.ANLAGEDATUM, 104) AS ANLAGEDATUM,
						CONCAT(pat.ANREDE, ' ', pat.NAME, ', ' , pat.VORNAME, ' *', CONVERT(varchar(255), pat.GEBURTSDATUM, 104)) AS NAME,
						pat.REFERENZ AS KUNDENNUMMER,
						KOSTENTRAEGER.NAME_1 AS KOSTENTRAEGER,
						vorgaenge.LEISTUNG,
						FORMAT(vorgaenge.AUFTRAGSWERT_BRUTTO, 'C2', 'de-de') AS AUFTRAGSWERT_BRUTTO,
						[sys].GENEHMIGT,
						CONVERT(varchar(255), vorgaenge.GENEHMIGT_DATUM, 104) AS GENEHMIGT_DATUM,
						UNIT.BETRIEB,
						CASE WHEN [sys].GENEHMIGT IN ('genehmigt', 'genehmigungsfrei') THEN 'genehmigt' ELSE 'nicht genehmigt' END AS GENEHMIGT_FLAG
					FROM [eva3_02_viva_souh].[dbo].[vorgaenge]
					INNER JOIN (
						SELECT
							KENNZEICHEN,
							BEZEICHNUNG AS GENEHMIGT
						FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
						WHERE AUSWAHLART = 'AuftragsGenehmigung'
					) AS [sys] ON [sys].KENNZEICHEN = vorgaenge.GENEHMIGT
					INNER JOIN (
						SELECT
							KENNZEICHEN,
							BEZEICHNUNG AS GELIEFERT
						FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
						WHERE AUSWAHLART = 'AuftragsLieferung'
					) AS [sys2] ON [sys2].KENNZEICHEN = vorgaenge.GELIEFERT
					INNER JOIN (
						SELECT
							a.NAME_1 AS NAME,
							concat(a.NAME_2, ' ', a.NAME_3, ' ', a.NAME_4) AS VORNAME,
							a.GEBURTSDATUM,
							a.STRASSE_1 AS STRASSE,
							a.PLZ_1 AS PLZ,
							a.ORT_1 AS ORT,
							a.LKZ_1 AS LKZ,
							a.REFERENZ,
							more.STERBEDATUM,
							t.ADRESS_ANREDE AS ANREDE,
							t.BRIEFKOPF_ANREDE AS [PERSOENLICHE ANREDE],
							more.KOSTENTRAEGER AS KOSTENTRAEGER_REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[adressen] AS a
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia ON a.ADRESSART = ia.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[adr_kunden] AS more ON more.ADRESSEN_REFERENZ = a.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_anreden] AS t ON t.REFERENZ = a.ANREDE 
						WHERE ia.BEZEICHNUNG = 'Kunden / Patienten'
						AND more.STERBEDATUM IS NULL
					) AS pat ON vorgaenge.ADRESSEN_REFERENZ = pat.REFERENZ
					INNER JOIN
					(
						SELECT
							names.NAME_3 AS BETRIEB,
							unit.ADRESSEN_REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[adr_betrieb] AS unit
						INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS names ON unit.ADRESSEN_REFERENZ = names.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS unita ON unita.REFERENZ = names.ADRESSART
						WHERE unita.BEZEICHNUNG = 'Betrieb / Filiale'
					) AS UNIT ON vorgaenge.BETRIEB = UNIT.ADRESSEN_REFERENZ
					LEFT JOIN
					(
						SELECT
							ka.NAME_1,
							ka.REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[adressen] AS ka INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS kia ON ka.ADRESSART = kia.REFERENZ
						WHERE kia.BEZEICHNUNG = 'Kostenträger'
					) AS KOSTENTRAEGER ON pat.KOSTENTRAEGER_REFERENZ = KOSTENTRAEGER.REFERENZ
			
					WHERE 
					vorgaenge.STATUS = 0
					AND [sys].GENEHMIGT NOT IN ('abgelehnt', 'Storno', 'verstorben')
					AND [sys2].GELIEFERT NOT IN ('geliefert')
					AND vorgaenge.AUFTRAGSWERT_BRUTTO > 0
					AND vorgaenge.FAKTURIERT_DATUM IS NULL
					AND vorgaenge.ANLAGEDATUM BETWEEN ':anlagedatumvon' AND ':anlagedatumbis'
					AND UNIT.ADRESSEN_REFERENZ IN (12, 14, 15, 16, 17, 18)

					order by vorgaenge.REFERENZ ASC
					END,
				'params' => [
					':anlagedatumvon' => [
						'name' => 'Anlagedatum von',
						'type' => 'date',
						'default' => function(){
							$date = new \DateTime('now');
							return $date->modify('-3 years')->modify('first day of this month')->format('Y-m-d');
						},
						'function' => function($v){
							try {
								$date = new \DateTime($v);
								return $date->format('Y-m-d 0:00:00.000');
							}
							catch(\EXCEPTION $e){
								return null;
							}
						}
					],
					':anlagedatumbis' => [
						'name' => 'Anlagedatum bis',
						'type' => 'date',
						'default' => date('Y-m-d'),
						'function' => function($v){
							try {
								$date = new \DateTime($v);
								return $date->format('Y-m-d 23:59:59.000');
							}
							catch(\EXCEPTION $e){
								return null;
							}
						}
					]
				]
			],
			'Falllisten laufende Fälle - Zusammenfassung' => [
				'description' => 'Bereichsweise Zusammenfassung aller laufenden Fälle',
				'query' => <<<'END'
					SELECT
						UNIT.BETRIEB,
						SUM(CASE WHEN
							[sys].GENEHMIGT IN ('genehmigt', 'genehmigungsfrei')
							THEN 1 ELSE 0 END) as AUFTRAEGE_GENEHMIGT,
						FORMAT(SUM(CASE WHEN
							[sys].GENEHMIGT IN ('genehmigt', 'genehmigungsfrei')
							THEN vorgaenge.AUFTRAGSWERT_BRUTTO ELSE 0 END
						), 'C2', 'de-de') as AUFTRAGSWERT_GENEHMIGT,
						SUM(CASE WHEN
							[sys].GENEHMIGT NOT IN ('genehmigt', 'genehmigungsfrei', 'Storno', 'teilgenehmigt', 'abgelehnt', 'verstorben')
							THEN 1 ELSE 0 END) as AUFTRAEGE_NICHT_GENEHMIGT,
						FORMAT(SUM(CASE WHEN
							[sys].GENEHMIGT NOT IN ('genehmigt', 'genehmigungsfrei', 'Storno', 'teilgenehmigt', 'abgelehnt', 'verstorben')
							THEN vorgaenge.AUFTRAGSWERT_BRUTTO ELSE 0 END
						), 'C2', 'de-de') as AUFTRAGSWERT_NICHT_GENEHMIGT
					FROM [eva3_02_viva_souh].[dbo].[vorgaenge]
					INNER JOIN (
						SELECT
							KENNZEICHEN,
							BEZEICHNUNG AS GENEHMIGT
						FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
						WHERE AUSWAHLART = 'AuftragsGenehmigung'
					) AS [sys] ON [sys].KENNZEICHEN = vorgaenge.GENEHMIGT
					INNER JOIN (
						SELECT
							KENNZEICHEN,
							BEZEICHNUNG AS GELIEFERT
						FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
						WHERE AUSWAHLART = 'AuftragsLieferung'
					) AS [sys2] ON [sys2].KENNZEICHEN = vorgaenge.GELIEFERT
					INNER JOIN
					(
						SELECT
							names.NAME_3 AS BETRIEB,
							unit.ADRESSEN_REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[adr_betrieb] AS unit
						INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS names ON unit.ADRESSEN_REFERENZ = names.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS unita ON unita.REFERENZ = names.ADRESSART
						WHERE unita.BEZEICHNUNG = 'Betrieb / Filiale'
					) AS UNIT ON vorgaenge.BETRIEB = UNIT.ADRESSEN_REFERENZ
			
					WHERE 
					vorgaenge.STATUS = 0
					AND vorgaenge.AUFTRAGSWERT_BRUTTO > 0
					AND vorgaenge.FAKTURIERT_DATUM IS NULL
					AND [sys2].GELIEFERT NOT IN ('geliefert')
					AND vorgaenge.ANLAGEDATUM BETWEEN ':anlagedatumvon' AND ':anlagedatumbis'
					AND UNIT.ADRESSEN_REFERENZ IN (12, 14, 15, 16, 17, 18)

					GROUP BY UNIT.BETRIEB
					END,
				'params' => [
					':anlagedatumvon' => [
						'name' => 'Anlagedatum von',
						'type' => 'date',
						'default' => function(){
							$date = new \DateTime('now');
							return $date->modify('-3 years')->modify('first day of this month')->format('Y-m-d');
						},
						'function' => function($v){
							try {
								$date = new \DateTime($v);
								return $date->format('Y-m-d 0:00:00.000');
							}
							catch(\EXCEPTION $e){
								return null;
							}
						}
					],
					':anlagedatumbis' => [
						'name' => 'Anlagedatum bis',
						'type' => 'date',
						'default' => date('Y-m-d'),
						'function' => function($v){
							try {
								$date = new \DateTime($v);
								return $date->format('Y-m-d 23:59:59.000');
							}
							catch(\EXCEPTION $e){
								return null;
							}
						}
					]
				],
				'export' => [
					'pdf' => [],
					'ods' => [],
					'xlsx' => []
				]
			],
			'Fälle pro Verordner' => [
				'description' => 'Eine Liste aller Fälle sortiert nach Verordner und Erlös',
				'query' => <<<'END'
					SELECT
						vorgaenge.REFERENZ AS VORGANG,
						CONVERT(varchar(255), vorgaenge.ANLAGEDATUM, 104) AS ANLAGEDATUM,
						CONCAT(pat.ANREDE, ' ', pat.NAME, ', ' , pat.VORNAME, ' *', CONVERT(varchar(255), pat.GEBURTSDATUM, 104)) AS NAME,
						vorgaenge.LEISTUNG,
						FORMAT(vorgaenge.AUFTRAGSWERT_BRUTTO, 'C2', 'de-de') AS AUFTRAGSWERT_BRUTTO,
						VERORDNER.NAME AS VERORDNER,
						MITARBEITER.NAME AS MITARBEITER,
						UNIT.BETRIEB
					FROM [eva3_02_viva_souh].[dbo].[vorgaenge]
					INNER JOIN (
						SELECT
							KENNZEICHEN,
							BEZEICHNUNG AS GENEHMIGT
						FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
						WHERE AUSWAHLART = 'AuftragsGenehmigung'
					) AS [sys] ON [sys].KENNZEICHEN = vorgaenge.GENEHMIGT
					INNER JOIN (
						SELECT
							t.ADRESS_ANREDE AS ANREDE,
							a.NAME_1 AS NAME,
							concat(a.NAME_2, ' ', a.NAME_3, ' ', a.NAME_4) AS VORNAME,
							a.GEBURTSDATUM,
							a.REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[adressen] AS a
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia ON a.ADRESSART = ia.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[adr_kunden] AS more ON more.ADRESSEN_REFERENZ = a.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_anreden] AS t ON t.REFERENZ = a.ANREDE 
						WHERE ia.BEZEICHNUNG = 'Kunden / Patienten'
						AND more.STERBEDATUM IS NULL
					) AS pat ON vorgaenge.ADRESSEN_REFERENZ = pat.REFERENZ
					INNER JOIN
					(
						SELECT
							names.NAME_3 AS BETRIEB,
							unit.ADRESSEN_REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[adr_betrieb] AS unit
						INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS names ON unit.ADRESSEN_REFERENZ = names.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS unita ON unita.REFERENZ = names.ADRESSART
						WHERE unita.BEZEICHNUNG = 'Betrieb / Filiale'
					) AS UNIT ON vorgaenge.BETRIEB = UNIT.ADRESSEN_REFERENZ
					INNER JOIN
					(
						SELECT
							concat(names.NAME_1, ' ', names.NAME_2, ' ', names.NAME_3, ' ', names.NAME_4, ' - ', unit.ADRESSEN_REFERENZ) AS NAME,
							unit.ADRESSEN_REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[adr_aerzte] AS unit
						INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS names ON unit.ADRESSEN_REFERENZ = names.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS unita ON unita.REFERENZ = names.ADRESSART
						WHERE unita.BEZEICHNUNG = 'Ärzte'
					) AS VERORDNER ON vorgaenge.VERORDNER = VERORDNER.ADRESSEN_REFERENZ
					LEFT JOIN
					(
						SELECT
							concat(a.NAME_1, ' ', a.NAME_2) AS NAME,
							a.REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[adressen] AS a
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia ON a.ADRESSART = ia.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[adr_kunden] AS more ON more.ADRESSEN_REFERENZ = a.REFERENZ
						WHERE ia.BEZEICHNUNG = 'Mitarbeiter'
					) AS MITARBEITER ON vorgaenge.MITARBEITER = MITARBEITER.REFERENZ
			
					WHERE 
					vorgaenge.STATUS = 0
					AND [sys].GENEHMIGT NOT IN ('abgelehnt', 'Storno', 'verstorben')

					AND vorgaenge.AUFTRAGSWERT_BRUTTO NOT BETWEEN -10.1 AND 10.1
					AND vorgaenge.ANLAGEDATUM BETWEEN ':anlagedatumvon' AND ':anlagedatumbis'
					AND UNIT.ADRESSEN_REFERENZ IN (12, 14, 15, 16, 17, 18)

					order by VERORDNER.NAME ASC, vorgaenge.AUFTRAGSWERT_BRUTTO DESC
					END,
				'params' => [
					':anlagedatumvon' => [
						'name' => 'Anlagedatum von',
						'type' => 'date',
						'default' => function(){
							$date = new \DateTime('now');
							return $date->modify('-3 years')->modify('first day of this month')->format('Y-m-d');
						},
						'function' => function($v){
							try {
								$date = new \DateTime($v);
								return $date->format('Y-m-d 0:00:00.000');
							}
							catch(\EXCEPTION $e){
								return null;
							}
						}
					],
					':anlagedatumbis' => [
						'name' => 'Anlagedatum bis',
						'type' => 'date',
						'default' => date('Y-m-d'),
						'function' => function($v){
							try {
								$date = new \DateTime($v);
								return $date->format('Y-m-d 23:59:59.000');
							}
							catch(\EXCEPTION $e){
								return null;
							}
						}
					]
				]
			],
			'Fälle pro Verordner - Zusammenfassung' => [
				'description' => 'Eine Liste mit der Anzahl und den Erlösen pro Verordner',
				'query' => <<<'END'
					SELECT
						VERORDNER.NAME,
						SUM(CASE WHEN
							vorgaenge.AUFTRAGSWERT_BRUTTO > 0
							THEN 1 ELSE 0 END) as AUFTRAEGE,
						FORMAT(SUM(vorgaenge.AUFTRAGSWERT_BRUTTO), 'C2', 'de-de') as AUFTRAGSWERT
					FROM [eva3_02_viva_souh].[dbo].[vorgaenge]
					INNER JOIN (
						SELECT
							KENNZEICHEN,
							BEZEICHNUNG AS GENEHMIGT
						FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
						WHERE AUSWAHLART = 'AuftragsGenehmigung'
					) AS [sys] ON [sys].KENNZEICHEN = vorgaenge.GENEHMIGT
					INNER JOIN (
						SELECT
							KENNZEICHEN,
							BEZEICHNUNG AS GELIEFERT
						FROM [eva3_02_viva_souh].[dbo].[sys_auswahl]
						WHERE AUSWAHLART = 'AuftragsLieferung'
					) AS [sys2] ON [sys2].KENNZEICHEN = vorgaenge.GELIEFERT
					INNER JOIN
					(
						SELECT
							concat(names.NAME_1, ' ', names.NAME_2, ' ', names.NAME_3, ' ', names.NAME_4, ' - ', unit.ADRESSEN_REFERENZ) AS NAME,
							unit.ADRESSEN_REFERENZ
						FROM [eva3_02_viva_souh].[dbo].[adr_aerzte] AS unit
						INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS names ON unit.ADRESSEN_REFERENZ = names.REFERENZ
						INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS unita ON unita.REFERENZ = names.ADRESSART
						WHERE unita.BEZEICHNUNG = 'Ärzte'
					) AS VERORDNER ON vorgaenge.VERORDNER = VERORDNER.ADRESSEN_REFERENZ
								
					WHERE 
					vorgaenge.STATUS = 0
					AND [sys].GENEHMIGT NOT IN ('abgelehnt', 'Storno', 'verstorben')
					AND vorgaenge.AUFTRAGSWERT_BRUTTO NOT BETWEEN -10.1 AND 10.1
					AND vorgaenge.ANLAGEDATUM BETWEEN ':anlagedatumvon' AND ':anlagedatumbis'
					AND vorgaenge.BETRIEB IN (12, 14, 15, 16, 17, 18)

					GROUP BY VERORDNER.NAME
					ORDER BY SUM(vorgaenge.AUFTRAGSWERT_BRUTTO) DESC, VERORDNER.NAME ASC
					END,
				'params' => [
					':anlagedatumvon' => [
						'name' => 'Anlagedatum von',
						'type' => 'date',
						'default' => function(){
							$date = new \DateTime('now');
							return $date->modify('-3 years')->modify('first day of this month')->format('Y-m-d');
						},
						'function' => function($v){
							try {
								$date = new \DateTime($v);
								return $date->format('Y-m-d 0:00:00.000');
							}
							catch(\EXCEPTION $e){
								return null;
							}
						}
					],
					':anlagedatumbis' => [
						'name' => 'Anlagedatum bis',
						'type' => 'date',
						'default' => date('Y-m-d'),
						'function' => function($v){
							try {
								$date = new \DateTime($v);
								return $date->format('Y-m-d 23:59:59.000');
							}
							catch(\EXCEPTION $e){
								return null;
							}
						}
					]
				],
				'export' => [
					'pdf' => [
						'40%', '10%', '50%'
					],
					'ods' => [],
					'xlsx' => []
				]
			],
			/*'Mitarbeiterliste' => [
				'query' => <<<'END'
					SELECT
						a.NAME_1 AS Nachname,
						a.NAME_2 As Vorname
					FROM [eva3_02_viva_souh].[dbo].[adressen] AS a INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia ON a.ADRESSART = ia.REFERENZ
					LEFT JOIN [eva3_02_viva_souh].[dbo].[adr_kunden] AS more ON more.ADRESSEN_REFERENZ = a.REFERENZ
					WHERE ia.BEZEICHNUNG = 'Mitarbeiter'
					AND a.AKTIV_KENNZEICHEN = 1
					AND a.STATUS = 0
					AND a.NAME_2 IS NOT NULL
					AND a.NAME_2 NOT IN ('- NICHT LÖSCHEN -')
					ORDER BY NAME_1, NAME_2
					END,
				'params' => []
			]*/
		];

		if (!$key) {
			$keys = array_keys($queries);
			sort($keys, SORT_STRING);
			return $keys;
		}
		if (!isset($queries[$key])) return null;
		$paramfields = [];
		// if not called with params return optional parameter settings for input
		if ($params === null) {
			if (!empty($queries[$key]['params'])) {
				foreach($queries[$key]['params'] as $property){
					$paramfields[$property['name']] = [
						'type' => $property['type'],
						'value' => is_callable($property['default']) ? $property['default']() : $property['default']
					];
				}
			}
			return ['params' => $paramfields, 'export' => !empty($queries[$key]['export']) ? array_keys($queries[$key]['export']): null, 'description' => $queries[$key]['description'] ?? ''];
		}
		// iterate over query params and overwrite defaults with passed params, then apply function
		$pdfparam = [];
		if (isset($queries[$key]['params'])){
			foreach($queries[$key]['params'] as $param => $property){
				$default = is_callable($property['default']) ? $property['default']() : $property['default'];
				$paramfields[$param] = $default;
				if (isset($params[$property['name']])) {
					$paramfields[$param] = $params[$property['name']];
					unset($params[$property['name']]);
				}
				$paramfields[$param] = $property['function']($paramfields[$param]) ?? $property['function']($default);
				$pdfparam[$property['name']] = $paramfields[$param];
			}
		}
		// determine export format
		$export = 'csv';
		foreach($params as $value){
			$value = strtolower($value);
			if (isset($queries[$key]['export']) && isset($queries[$key]['export'][$value])) {
				$export = $value;
			}
		}

		try{
			$statement = $this->_pdo->prepare(strtr($queries[$key]['query'], $paramfields));
			$statement->execute();
		}
		catch(\EXCEPTION $e){
			UTILITY::debug($e, $statement->debugDumpParams());
		}
		$result = $statement->fetchAll();
		$statement = null;

		if ($result) {
			switch($export){
				case 'pdf':
					require_once('./_pdf.php');
					$PDF = new PDF(CONFIG['pdf']['table']);
					$content = [
						'title' => $key,
						'date' => date('Y-m-d') . ' - ' . json_encode($pdfparam),
						'content' => $result,
						'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $key),
						'columns' => $queries[$key]['export']['pdf'] 
					];
					return $PDF->tablePDF($content);
					break;
				default:
					require_once('_table.php');
					$fileexport = new TABLE([$result]);
					if ($files = $fileexport->dump($key . date(' Y-m-d H-i-s') . '.' .  $export)){
						return substr($files[0], 1);
					}
			}
		}
		return [];
	}
	
	/**
	 * retrieve recent data of erp consumables database
	 * @param array $vendors
	 * @param bool $as_passed false returns the remaining vendors but the passed ones
	 * @return null|array
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable basic call from application integrations
	 */
	public function consumables($vendors = [], $as_passed = true){
		$query = <<<'END'
		SELECT
			vendor.NAME_1 as LIEFERANTEN_NAME,
			article.BESTELL_NUMMER,
			article.BESTELL_TEXT,
			article.EAN,
			unit.BEZEICHNUNG AS BESTELLEINHEIT,
			article2.ZUSATZINFORMATION,
				/*tradinggood
				expirydate
				specialattention*/
			storage.LAGERORT,
			article.ARTIKEL_REFERENZ,
			CONVERT(varchar(255), article.WARENEINGANGSDATUM, 120) AS WARENEINGANGSDATUM
		FROM [eva3_02_viva_souh].[dbo].[wws_artikel_lieferanten] AS article
		LEFT JOIN (
			SELECT
				BEZEICHNUNG,
				REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[inf_einheit]
		) AS unit ON unit.REFERENZ = article.BESTELL_EINHEIT
		LEFT JOIN (
			SELECT
				REFERENZ,
				ZUSATZINFORMATION
			FROM [eva3_02_viva_souh].[dbo].[wws_artikelstamm]
		) AS article2 ON article2.REFERENZ = article.ARTIKEL_REFERENZ
		LEFT JOIN (
			SELECT
				ARTIKEL_REFERENZ,
				(CASE WHEN LAGERORT IS NULL AND LAGER_MINDESTBESTAND = 0 THEN 'Konsignationslager' ELSE LAGERORT END) AS LAGERORT
			FROM [eva3_02_viva_souh].[dbo].[wws_lagerbestand] AS strg
			INNER JOIN [eva3_02_viva_souh].[dbo].[inf_lager] AS strg_n ON strg.LAGER_REFERENZ = strg_n.REFERENZ
			WHERE 
			(strg_n.BEZEICHNUNG LIKE 'Zentrallager' AND strg.LAGERORT IS NOT NULL AND strg.LAGERORT != 'null')
			OR strg_n.BEZEICHNUNG LIKE 'Konsignations%'
			OR strg.LAGER_MINDESTBESTAND > 0
		) AS storage ON storage.ARTIKEL_REFERENZ = article.ARTIKEL_REFERENZ
		LEFT JOIN (
			SELECT 
				v.NAME_1,
				v.REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia
			INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS v ON v.ADRESSART = ia.REFERENZ
			WHERE ia.BEZEICHNUNG = 'Lieferanten'
		) AS vendor ON article.LIEFERANTEN_REFERENZ = vendor.REFERENZ
		
		WHERE article.BESTELLSTOP = 0 AND article.STATUS = 0 AND article.PRIMAER_LIEFERANT = 1
		AND vendor.NAME_1 :as_passed in (:vendors)
		END;

		if (!$vendors) return [[]];

		try{
			$statement = $this->_pdo->prepare(strtr($query, [
				':vendors' => implode(',', array_map(fn($v) => $this->_pdo->quote($v), $vendors)),
				':as_passed' => $as_passed ? '' : 'NOT'
			]));
			$statement->execute();	
		}
		catch(\EXCEPTION $e){
			UTILITY::debug($e, $statement->debugDumpParams());
		}
		$result = $statement->fetchAll();
		$statement = null;
		$response = [];

		foreach ($result as $row){
			if (!isset($response[$row['LIEFERANTEN_NAME']])) $response[$row['LIEFERANTEN_NAME']] = [];
			$response[$row['LIEFERANTEN_NAME']][] = [
				'article_no' => $row['BESTELL_NUMMER'],
				'article_name' => $row['BESTELL_TEXT'],
				'article_ean' => $row['EAN'],
				'article_unit' => $row['BESTELLEINHEIT'],
				'article_info' => $row['ZUSATZINFORMATION'],
				'trading_good' => null,
				'has_expiry_date' => null,
				'special_attention' => null,
				'stock_item' => $row['LAGERORT'] ? 1 : null,
				'erp_id' => $row['ARTIKEL_REFERENZ'],
				'last_order' => $row['WARENEINGANGSDATUM'] ? : null
			];
		}
		if (!$response) return [[]];
		return $response;
	}
	
	/**
	 * retrieve media files based on passed case numbers
	 * @param array $erp_case_numbers
	 * @return null|array
	 * 
	 * sanitize parameter according to the usecase e.g. dbo driver
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable basic call from application integrations
	 */
	public function media($erp_case_numbers = []){
		$query = <<<'END'
		SELECT
			multimedia.[BESCHREIBUNG],
			CONVERT(varchar(255), multimedia.[ANGELEGT_AM], 104) AS ANGELEGT_AM,
			multimedia.[FILTER_2] as vorgang,
			multimedia.[DATEINAME],
			files.MEDIA
		FROM [eva3_02_viva_souh].[dbo].[multimedia]
		INNER JOIN (
			SELECT 
				REFERENZ,
				MEDIA
			FROM [eva3_mmd_viva_souh].[dbo].[MMD_MEDIA]
		) AS files ON files.REFERENZ = multimedia.MMD_MEDIA_REFERENZ
		
		WHERE multimedia.FILTER_2 IN (:ref)
		END;

		if (!$erp_case_numbers) return [[]];
		try{
			$statement = $this->_pdo->prepare(strtr($query, [
				':ref' => implode(',', array_map(fn($ref) => $this->_pdo->quote($ref), $erp_case_numbers))
			]));
			$statement->execute();	
		}
		catch(\EXCEPTION $e){
			UTILITY::debug($e, $statement->debugDumpParams());
		}
		$result = $statement->fetchAll();
		$statement = null;
		$response = [];

		foreach ($result as $row){
			$f = finfo_open();
			$mime_type = finfo_buffer($f, $row['MEDIA'], FILEINFO_MIME_TYPE);
			$response[$row['vorgang']][] = [
				'url' => 'data:' . $mime_type . ';base64,' . base64_encode($row['MEDIA']),
				'description' => $row['BESCHREIBUNG'],
				'date' => $row['ANGELEGT_AM'],
				'filename' => $row['vorgang'] . ' ' . $row['ANGELEGT_AM'] . ' ' . $row['BESCHREIBUNG'] . '.' . pathinfo($row['DATEINAME'])['extension']
			];
		}
		if (!$response) return [[]];
		return $response;
	}

	/**
	 * retrieve recent data on processed orders for given timespan  
	 * return an array of orders to compare at application level
	 * @param string|null $from Y-m-d H:i:s
	 * @param string|null $until Y-m-d H:i:s
	 * @return null|array
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable identifier display within order module
	 */
	public function orderdata($from = null, $until = 'now'){
		// convert passed dated to DateTime objects, with default values on erroneous parameters
		try {
			$from = new \DateTime($from ? : '2025-09-01 00:00:00');
		}
		catch (\Exception $e){
			$from = new \DateTime('2025-09-01 00:00:00');
		}
		try {
			$until = new \DateTime($until);
		}
		catch (\Exception $e){
			$until = new \DateTime('now');
		}
		// convert to erp supported date format
		$from = $from->format('Y-m-d H:i:s') . '.000';
		$until = $until->format('Y-m-d H:i:s') . '.000';

		$query = <<<'END'
		SELECT
			orders.BESTELLNUMMER,
			orders.BEZEICHNUNG AS BESTELLTEXT,
			article.ARTIKELBEZEICHNUNG,
			CONVERT(varchar(255), orders.ORDER_DATUM, 120) AS ORDER_DATUM,
			orders.MENGE,
			CONVERT(varchar(255), orders2.WE_DATUM, 120) AS WE_DATUM,
			orders2.WE_MENGE,
			orders2.BESTELL_BELEGNUMMER,
			vendor.NAME_1 as LIEFERANTEN_NAME
		FROM [eva3_02_viva_souh].[dbo].[wws_order] as orders
		LEFT JOIN (
			SELECT
				BESTELL_TEXT AS ARTIKELBEZEICHNUNG,
				ARTIKEL_REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[wws_artikel_lieferanten]
		) AS article ON orders.ARTIKELNUMMER = article.ARTIKEL_REFERENZ
		LEFT JOIN (
			SELECT
				REFERENZ,
				WE_DATUM,
				WE_MENGE,
				BESTELL_BELEGNUMMER
			FROM [eva3_02_viva_souh].[dbo].[wws_bestellung]
		) AS orders2 ON orders.BESTELLUNGS_REFERENZ = orders2.REFERENZ
		LEFT JOIN (
			SELECT 
				v.NAME_1,
				v.REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia
			INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS v ON v.ADRESSART = ia.REFERENZ
			WHERE ia.BEZEICHNUNG = 'Lieferanten'
		) AS vendor ON orders.LIEFERANTEN_REFERENZ = vendor.REFERENZ

		WHERE orders.ORDER_DATUM BETWEEN :from AND :until
		END;

		try{
			$statement = $this->_pdo->prepare(strtr($query, [
				':from' => $this->_pdo->quote($from),
				':until' => $this->_pdo->quote($until),
			]));
			$statement->execute();	
		}
		catch(\EXCEPTION $e){
			UTILITY::debug($e, $statement->debugDumpParams());
		}
		$result = $statement->fetchAll();
		$statement = null;
		$response = [];

		require_once('./_utility.php');

		foreach ($result as $row){
			// append to result only if a valid identifier has been found
			// string, part of order text previously pasted during order process generated by caro app in the scheme `  #sz9623` two space-pound-base 36 unixtime according to UTILITY::identifier(), format accordingly
			$valid_identifier = UTILITY::identifier($row['BESTELLTEXT'], null, false, false, true);
			if ($valid_identifier && $identifier = trim($valid_identifier)){
				if (!isset($response[$identifier])) $response[$identifier] = [];
				$response[$identifier][] = [
					'vendor' => $row['LIEFERANTEN_NAME'],
					'article_no' => $row['BESTELLNUMMER'],
					'article_name' => $row['ARTIKELBEZEICHNUNG'],
					'ordered' => $row['ORDER_DATUM'] ? : null,
					'delivered_partially' => $row['WE_DATUM'] && $row['WE_MENGE'] != $row['MENGE'] ? $row['WE_DATUM'] : null,
					'delivered_full' => $row['WE_DATUM'] && $row['WE_MENGE'] == $row['MENGE'] ? $row['WE_DATUM'] : null,
					'order_reference' => $row['BESTELL_BELEGNUMMER']
				];
			}
		}
		if (!$response) return [[]];
		return $response;
	}
	
	/**
	 * retrieve processed orders for given timespan and customer selection, based on customerdata()-response and matched with whatever key is set to clearly identify a customer  
	 * all processed orders for matched customers are returned
	 * return an array of orders to compare at application level
	 * @param array|null $request as named array with columns to match, similar to customerdata()
	 * @return null|array
	 * 
	 * returns results to select from on application level
	 * availability of the method must be signalled by something, preferably [[]] to enable identifier display within order module
	 * also see orderdata for similarities. preparing response differs though
	 */
	public function pastorders($request = null){
		$query = <<<'END'
		SELECT
			orders.BESTELLNUMMER,
			orders.BEZEICHNUNG AS BESTELLTEXT,
			article.ARTIKELBEZEICHNUNG,
			CONVERT(varchar(255), orders.ORDER_DATUM, 23) AS ORDER_DATUM,
			orders.MENGE,
			CONVERT(varchar(255), orders2.WE_DATUM, 23) AS WE_DATUM,
			orders2.WE_MENGE,
			orders2.BESTELL_BELEGNUMMER,
			vendor.NAME_1 as LIEFERANTEN_NAME,
			orders.KUNDEN_REFERENZ
		FROM [eva3_02_viva_souh].[dbo].[wws_order] as orders
		LEFT JOIN (
			SELECT
				BESTELL_TEXT AS ARTIKELBEZEICHNUNG,
				ARTIKEL_REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[wws_artikel_lieferanten]
		) AS article ON orders.ARTIKELNUMMER = article.ARTIKEL_REFERENZ
		LEFT JOIN (
			SELECT
				REFERENZ,
				WE_DATUM,
				WE_MENGE,
				BESTELL_BELEGNUMMER
			FROM [eva3_02_viva_souh].[dbo].[wws_bestellung]
		) AS orders2 ON orders.BESTELLUNGS_REFERENZ = orders2.REFERENZ
		LEFT JOIN (
			SELECT 
				v.NAME_1,
				v.REFERENZ
			FROM [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia
			INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS v ON v.ADRESSART = ia.REFERENZ
			WHERE ia.BEZEICHNUNG = 'Lieferanten'
		) AS vendor ON orders.LIEFERANTEN_REFERENZ = vendor.REFERENZ

		WHERE orders.ORDER_DATUM BETWEEN :from AND :until
		AND orders.KUNDEN_REFERENZ IN (:ref)

		ORDER BY orders.ORDER_DATUM DESC
		END;

		if (!$request) {
			// this may handle available languages as well!
			$language = $_SESSION['user']['app_settings']['language'] ?? CONFIG['application']['defaultlanguage'];
			switch($language){
				case 'en':
					return [
						...$this->customerdata(),
						[
							'name' => 'From',
							'type' => 'date'
						],
						[
							'name' => 'Until',
							'type' => 'date'
						],
						[
							'name' => 'Filter',
							'type' => 'text'
						]
					];
				case 'de':
					return [
						...$this->customerdata(),
						[
							'name' => 'Von',
							'type' => 'date'
						],
						[
							'name' => 'Bis',
							'type' => 'date'
						],
						[
							'name' => 'Filter',
							'type' => 'text'
						]
					];
			}
		}

		if (!($customers = $this->customerdata($request))) return [[]];

		// convert passed dated to DateTime objects, with default values on erroneous parameters
		$from = '';
		if (isset($request['From']) && $request['From']) $from = $request['From'];
		elseif (isset($request['Von']) && $request['Von']) $from = $request['Von'];
		$until = 'now';
		if (isset($request['Until']) && $request['Until']) $until = $request['Until'];
		elseif (isset($request['Bis']) && $request['Bis']) $until = $request['Bis'];

		try {
			$from = new \DateTime($from ? : '2010-01-01 00:00:00'); // intial date for the current erp system
		}
		catch (\Exception $e){
			$from = new \DateTime('2010-01-01 00:00:00');
		}
		try {
			$until = new \DateTime($until);
		}
		catch (\Exception $e){
			$until = new \DateTime('now');
		}
		// convert to erp supported date format
		$from = $from->format('Y-m-d H:i:s') . '.000';
		$until = $until->format('Y-m-d H:i:s') . '.000';
				
		try{
			$statement = $this->_pdo->prepare(strtr($query, [
				':from' => $this->_pdo->quote($from),
				':until' => $this->_pdo->quote($until),
				':ref' => implode(',', array_map(fn($ref) => $this->_pdo->quote($ref['ERPNR']), $customers))
			]));
			$statement->execute();	
		}
		catch(\EXCEPTION $e){
			UTILITY::debug($e, $statement->debugDumpParams());
		}
		$result = $statement->fetchAll();

		if (isset($request['Filter']) && $request['Filter']){
			foreach($result as $index => $order){
				if (!SEARCH::filter($request['Filter'], [$order['BESTELLTEXT'], $order['ARTIKELBEZEICHNUNG']])) unset($result[$index]);
			}
			$result = SEARCH::refine($request['Filter'], $result, ['BESTELLTEXT', 'ARTIKELBEZEICHNUNG']);
		}

		if (!$result) return [[]];
		$statement = null;
		$pre_response = $response = [];

		// prepare response by iterating over customers to preserve customerdata refinement regarding weight
		foreach($customers as $customer){
			$pre_response[$customer['ERPNR']] = [
				'patient' => $customer['Name'] . ' *' . $customer['Geburtsdatum'],
				'orders' => []
			];
		}
		// insert order
		foreach ($result as $row){
			$pre_response[$row['KUNDEN_REFERENZ']]['orders'][] = [
				'vendor' => $row['LIEFERANTEN_NAME'],
				'article_no' => $row['BESTELLNUMMER'],
				'article_name' =>  $row['ARTIKELBEZEICHNUNG'],
				'ordered' => $row['ORDER_DATUM'] ? : null,
				'amount' => $row['WE_MENGE'] ? : null,
				'delivered_full' => $row['WE_DATUM'] ? : null,
				'order_reference' => $row['BESTELL_BELEGNUMMER']
			];
		}
		// skip empty, make patient name key for response
		foreach($pre_response as $patient){
			if (!$patient['orders']) continue;
			$response[$patient['patient']] = $patient['orders'];
		}

		return $response;
	}
}

if (CONFIG['system']['erp']) {
	$call = "CARO\\API\\" . strtoupper(CONFIG['system']['erp']);
	if (class_exists($call)) {
		define("ERPINTERFACE", new $call());
	}
	else define("ERPINTERFACE", null);
}
else define("ERPINTERFACE", null);
?>
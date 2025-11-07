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
	 * define expected methods to be overridden by actual interface class if available
	 * the application can check whether content can be expected if a call doe not return null
	 */
	public function __construct(){
		
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
		 * return [
		 * 		'{patient}' => [ // e.g. a concatenation of patient name and date of birth
		 * 			'caseid' => string,
		 * 			'text' => string,
		 * 			'reimbursement' => string Y-m-d,
		 * 			'settled' => string Y-m-d
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
		 * 		],
		 * 		...
		 * ]
		 * 
		 * array keys according to record document field names, drop or append reasonable options, e.g. multilanguage if applicable
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
	 * @return array|string array of available query keys or path to csv dump
	*/
	public function customcsvdump($key = null){
		return null;

		/*
		$queries = [
			'random query' => 'SELECT * FROM database'
		];

		if (!$key) return array_keys($queries);
		if (!isset($queries[$key])) return null;
		try{
			$statement = $this->_pdo->prepare($queries[$key]);
			$statement->execute();
		}
		catch(\EXCEPTION $e){
			UTILITY::debug($e, $statement->debugDumpParams());
		}
		$result = $statement->fetchAll();
		$statement = null;

		if ($result) {
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

		 * return [
		 * 		[
		 * 			'vendor' => string,
		 * 			'article_no' => string,
		 * 			'article_name' => string,
		 * 			'identifier' => string, part of order text previously pasted during order process generated by caro app in the scheme `  #sz9623` two space-pound-base 36 unixtime according to UTILITY::identifier(), format accordingly
		 * 			'ordered' => Y-m-d H:i:s,
		 * 			'partially_received' => Y-m-d H:i:s,
		 * 			'received' => Y-m-d H:i:s,
		 * 			'order_reference' => string, some identifier from the erp software, may make things easier for purchase on requests
		 * 		],
		 * 		...
		 * ]
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
		 *			'received' => Y-m-d,
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
	 * @return array|string array of available query keys or path to csv dump
	*/
	public function customcsvdump($key = null){
		$queries = [
			'random query' => 'fictional_file.csv',
			'random query 2' => 'another_fictional_file.csv',
		];

		if (!$key) return array_keys($queries);
		if (!isset($queries[$key])) return null;
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
			[
				'vendor' => 'Otto Bock HealthCare Deutschland GmbH',
				'article_no' => '99B25',
				'article_name' => 'Schlauch-Strumpf',
				'identifier' => '  #sz9623',
				'ordered' => '2025-09-01 21:00:00',
				'partially_received' => null,
				'received' => '2025-09-01 21:00:00',
				'order_reference' => '12345'
			],
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
	 * [readme](./CARO%20App%20ERP%20Interface%20OVEVAVIVA.md)
	 */
	private $_pdo = null;
	public $_instatiated = null;

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
			CONVERT(varchar(255), FAKTURIERT_DATUM, 104) AS FAKTURIERT_DATUM
		FROM [eva3_02_viva_souh].[dbo].[vorgaenge]

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
				'inquiry' => null,
				'partiallygranted' => $row['GENEHMIGT_DATUM'] && $row['AUFTRAGSWERT_BRUTTO'] != $row['GENEHMIGT_TEILSUMME'] ? $row['GENEHMIGT_DATUM'] : null,
				'granted' => $row['GENEHMIGT_DATUM'] && (!$row['GENEHMIGT_TEILSUMME'] || $row['AUFTRAGSWERT_BRUTTO'] == $row['GENEHMIGT_TEILSUMME']) ? $row['GENEHMIGT_DATUM'] : null,
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
			CONVERT(varchar(255), vorgang.FAKTURIERT_DATUM, 104) AS FAKTURIERT_DATUM,
			pat.REFERENZ as Patientennummer
		FROM [eva3_02_viva_souh].[dbo].[vorgaenge] as vorgang
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

		WHERE pat.REFERENZ IN (:ref)
		AND vorgang.LEISTUNG IS NOT NULL

		ORDER BY vorgang.ID DESC
		END;

		if (!$request) return [[]];
		if (!($customers = $this->customerdata($request))) return [[]];
		try{
			$statement = $this->_pdo->prepare(strtr($query, [
				':ref' => implode(',', array_map(fn($ref) => $this->_pdo->quote($ref['ERPNR']), $customers))
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
				'reimbursement' => $row['KV_DATUM'],
				'settled' => $row['FAKTURIERT_DATUM']
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
			$language = isset($_SESSION['user']['app_settings']['language']) ? $_SESSION['user']['app_settings']['language'] : CONFIG['application']['defaultlanguage'];
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

		if (
			(!isset($request['Name']) || !trim($request['Name'] ? : '')) &&
			(!isset($request['Date of birth']) || !trim($request['Date of birth'] ? : '')) &&
			(!isset($request['Patient number']) || !trim($request['Patient number'] ? : '')) &&

			(!isset($request['Name']) || !trim($request['Name'] ? : '')) &&
			(!isset($request['Geburtsdatum']) || !trim($request['Geburtsdatum'] ? : '')) &&
			(!isset($request['FiBu-Nummer']) || !trim($request['FiBu-Nummer'] ? : ''))
		) return [];
		
		$name = SEARCH::expressions($request['Name'] ? : '');
		$namesearch = [];
		foreach(['NACHNAME', 'NAME_2', 'NAME_3', 'NAME_4'] as $column){
			foreach($name as $namepart){
				$namesearch[] = 'pat.' . $column . ($namepart['operator'] === '-' ? ' NOT LIKE ' : ' LIKE ') . $this->_pdo->quote($namepart['sqlterm']);
			}
		}

		$dob = isset($request['Date of birth']) ? trim($request['Date of birth']) : trim($request['Geburtsdatum'] ? : '');
		$patientnumber = isset($request['Patient number']) ? trim($request['Patient number']) : trim($request['FiBu-Nummer'] ? : '');

		try{
			$statement = $this->_pdo->prepare(strtr($query, [
				':dob' => $dob ? 'pat.GEBURTSDATUM = CONVERT(DATETIME, ' . $this->_pdo->quote($dob . ' 00:00:00.000') . ', 21)': '',
				':patientnumber' => $patientnumber
					? ($dob ? ' AND ' : '') . 'pat.FIBU_NUMMER = ' . $this->_pdo->quote($patientnumber)
					: '',
				':namesearch' => $namesearch 
					? ($dob || $patientnumber ? ' AND ' : '') . '(' . implode(' OR ', $namesearch) . ')'
					: ''
				]));
			$statement->execute();	
		}
		catch(\EXCEPTION $e){
			UTILITY::debug($e, $statement->debugDumpParams());
		}
		$result = $statement->fetchAll();
		$statement = null;

		$refinequery = ($request['Name'] ? : '') . ($dob ? ' +' . $dob : '') . ($patientnumber ? ' +' . $patientnumber : '');

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
	 * @return array|string array of available query keys or path to csv dump
	*/
	public function customcsvdump($key = null){
		$queries = [
			'Vorgangsexport' => <<<'END'
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
					vorgaenge.AUFTRAGSWERT_BRUTTO,
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

				AND vorgaenge.ANLAGEDATUM > ':date'
				order by vorgaenge.REFERENZ ASC
				END,
		];
		$variables = [
			'Vorgangsexport' => [
				':date' => date('Y-m-d 0:00:00.000', time()-3600*24*(365+2))
			]
		];

		if (!$key) return array_keys($queries);
		if (!isset($queries[$key])) return null;
		try{
			if (isset($variables[$key])) $statement = $this->_pdo->prepare(strtr($queries[$key], $variables[$key]));
			else $statement = $this->_pdo->prepare($queries[$key]);
			$statement->execute();
		}
		catch(\EXCEPTION $e){
			UTILITY::debug($e, $statement->debugDumpParams());
		}
		$result = $statement->fetchAll();
		$statement = null;

		if ($result) {
			$tempFile = UTILITY::directory('tmp') . '/' . $key . date(' Y-m-d H-i-s') . '.csv';
			$file = fopen($tempFile, 'w');
			fwrite($file, b"\xEF\xBB\xBF"); // tell excel this is utf8
			fputcsv($file, array_keys($result[0]),
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
				(CASE WHEN LAGERORT IS NULL THEN 'Konsignationslager' ELSE LAGERORT END) AS LAGERORT
			FROM [eva3_02_viva_souh].[dbo].[wws_lagerbestand] AS strg
			INNER JOIN [eva3_02_viva_souh].[dbo].[inf_lager] AS strg_n ON strg.LAGER_REFERENZ = strg_n.REFERENZ
			WHERE 
			(strg_n.BEZEICHNUNG LIKE 'Zentrallager' AND strg.LAGERORT IS NOT NULL AND strg.LAGERORT != 'null')
			OR strg_n.BEZEICHNUNG LIKE 'Konsignations%'
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
			$response[] = [
				'vendor' => $row['LIEFERANTEN_NAME'],
				'article_no' => $row['BESTELLNUMMER'],
				'article_name' =>  $row['ARTIKELBEZEICHNUNG'],
				//'identifier' => string, part of order text previously pasted during order process generated by caro app in the scheme `  #sz9623` two space-pound-base 36 unixtime according to UTILITY::identifier(), format accordingly
				'identifier' => '  ' . UTILITY::identifier($row['BESTELLTEXT'], null, false, false, true),
				'ordered' => $row['ORDER_DATUM'] ? : null,
				'partially_received' => $row['WE_DATUM'] && $row['WE_MENGE'] != $row['MENGE'] ? $row['WE_DATUM'] : null,
				'received' => $row['WE_DATUM'] && $row['WE_MENGE'] == $row['MENGE'] ? $row['WE_DATUM'] : null,
				'order_reference' => $row['BESTELL_BELEGNUMMER']
			];
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
			$language = isset($_SESSION['user']['app_settings']['language']) ? $_SESSION['user']['app_settings']['language'] : CONFIG['application']['defaultlanguage'];
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
				'received' => $row['WE_DATUM'] ? : null,
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
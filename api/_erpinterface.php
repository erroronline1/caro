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

namespace CARO\API;

// IF you get a connection to your erp-software via any api or accessible file dumps
// this feels quite like a hacky attempt to get a data interface.
// it might be necessary to dynamically adapt this module according to changing requirements
// especially customerdata

// UTILITY functions may be implemented within the examples, as _utility.php is included by default

class _ERPINTERFACE {
	/**
	 * set to true if class has been successfully constructed
	 */
	public $_instatiated = null; 

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
	 * availability of the method must be signalled by something, preferably [[]] to enable basic fall from notification module
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
	 * retrieve most recent customer data details based on matching name / dob
	 * returns results to select from on application level
	 * @param string|null $name
	 * @param string|null $dob date of birth
	 * @return null|array
	 * 
	 * customer data is supposed to be imported on filling out documents for records.
	 * as the interface must be adjusted to your specific usecase it is probably the easiest
	 * to just respond with fieldnames according to the available documents.
	 * i currently see no way nor reason to figure out a regular user way of assigning.
	 * 
	 * sanitize parameters according to the usecase e.g. dbo driver
	*/
	public function customerdata($name = null, $dob = null){
		/**
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
		 * 			'Patient number' => string
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
	 * availability of the method must be signalled by something, preferably [[]] to enable basic fall from notification module
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
		 * 		],
		 * 		...
		 * ]
		 */
		return null;
	}
}

class TEST extends _ERPINTERFACE {
	/**
	 * set to true if class has been successfully constructed
	 */
	public $_instatiated = null; 

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
	 * availability of the method must be signalled by something, preferably [[]] to enable basic fall from notification module
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
	 * retrieve most recent customer data details based on matching name / dob
	 * returns results to select from on application level
	 * @param string|null $name
	 * @param string|null $dob date of birth
	 * @return null|array
	 * 
	 * customer data is supposed to be imported on filling out documents for records.
	 * as the interface must be adjusted to your specific usecase it is probably the easiest
	 * to just respond with fieldnames according to the available documents.
	 * i currently see no way nor reason to figure out a regular user way of assigning.
	 * 
	 * sanitize parameters according to the usecase e.g. dbo driver
	 */
	public function customerdata($name = null, $dob = null){
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
			'random query' => 'this is no a real file path',
			'random query 2' => 'this is another unreal file path',
		];

		if (!$key) return array_keys($queries);
		if (!isset($queries[$key])) return null;
		return $queries[$key];
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

	public function __construct(){
		try {
			parent::__construct();

			$options = [
				\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // always fetch assoc
				\PDO::ATTR_EMULATE_PREPARES   => true, // reuse tokens in prepared statements
				//\PDO::ATTR_PERSISTENT => true // persistent connection for performance reasons, unsupported as of 2/25 on sqlsrv?
			];
			$this->_pdo = new \PDO( CONFIG['sql'][CONFIG['system']['erp']]['driver'] . ':' . CONFIG['sql'][CONFIG['system']['erp']]['host'] . ';' . CONFIG['sql'][CONFIG['system']['erp']]['database']. ';' . CONFIG['sql'][CONFIG['system']['erp']]['charset'], CONFIG['sql'][CONFIG['system']['erp']]['user'], CONFIG['sql'][CONFIG['system']['erp']]['password'], $options);
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
	 * availability of the method must be signalled by something, preferably [[]] to enable basic fall from notification module
	 */
	public function casestate($erp_case_numbers = []){
		$query = <<<'END'
			SELECT
			REFERENZ,
			KV_DATUM,
			GENEHMIGT_DATUM,
			AUFTRAGSWERT_BRUTTO,
			GENEHMIGT_TEILSUMME,
			FAKTURIERT_DATUM
			FROM vorgaenge

			WHERE REFERENZ IN (:ref)
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
			$response[$row['REFERENZ']] = [
				'reimbursement' => $row['KV_DATUM'] ? substr($row['KV_DATUM'], 0, 10) : null,
				'inquiry' => null,
				'partiallygranted' => $row['GENEHMIGT_DATUM'] && $row['AUFTRAGSWERT_BRUTTO'] != $row['GENEHMIGT_TEILSUMME'] ? substr($row['GENEHMIGT_DATUM'], 0, 10) : null,
				'granted' => $row['GENEHMIGT_DATUM'] && (!$row['GENEHMIGT_TEILSUMME'] || $row['AUFTRAGSWERT_BRUTTO'] == $row['GENEHMIGT_TEILSUMME']) ? substr($row['GENEHMIGT_DATUM'], 0, 10) : null,
				'production' => null,
				'settled' => $row['FAKTURIERT_DATUM'] ? substr($row['FAKTURIERT_DATUM'], 0, 10) : null,
			];
		}
		return $response;
	}

	/**
	 * retrieve most recent customer data details based on matching name / dob
	 * returns results to select from on application level
	 * @param string|null $name
	 * @param string|null $dob date of birth
	 * @return null|array
	 * 
	 * customer data is supposed to be imported on filling out documents for records.
	 * as the interface must be adjusted to your specific usecase it is probably the easiest
	 * to just respond with fieldnames according to the available documents.
	 * i currently see no way nor reason to figure out a regular user way of assigning.
	 * 
	 * sanitize parameters according to the usecase e.g. dbo driver
	 */
	public function customerdata($name = null, $dob = null){
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
			SELECT a.NAME_1 as NACHNAME,
			a.NAME_2,
			a.NAME_3,
			a.NAME_4,
			a.GEBURTSNAME,
			a.GEBURTSDATUM,
			a.STRASSE_1,
			a.PLZ_1,
			a.ORT_1,
			a.LKZ_1,
			a.FIBU_NUMMER,
			a.REFERENZ,
			more.KOSTENTRAEGER AS KOSTENTRAEGER_REFERENZ
			FROM adressen AS a INNER JOIN inf_adressart AS ia ON a.ADRESSART = ia.REFERENZ
			LEFT JOIN adr_kunden AS more ON more.ADRESSEN_REFERENZ = a.REFERENZ
			WHERE ia.BEZEICHNUNG = 'Kunden / Patienten'
		) as pat
		LEFT JOIN
		(
			SELECT ka.NAME_1,
			ka.REFERENZ
			FROM adressen AS ka INNER JOIN inf_adressart AS kia ON ka.ADRESSART = kia.REFERENZ
			WHERE kia.BEZEICHNUNG = 'Kostenträger'
		) AS KOSTENTRAEGER ON pat.KOSTENTRAEGER_REFERENZ = KOSTENTRAEGER.REFERENZ
		LEFT JOIN
		(
			SELECT mail.NUMMER AS EMAIL,
			mail.ADRESSEN_REFERENZ
			FROM adz_kontakte AS mail INNER JOIN inf_kontaktart as im ON mail.KONTAKTART = im.REFERENZ
			WHERE im.BEZEICHNUNG = 'E-Mail'
		) AS EMAIL ON pat.REFERENZ = EMAIL.ADRESSEN_REFERENZ
		LEFT JOIN
		(
			SELECT mail.NUMMER AS PHONE,
			mail.ADRESSEN_REFERENZ
			FROM adz_kontakte AS mail INNER JOIN inf_kontaktart as im ON mail.KONTAKTART = im.REFERENZ
			WHERE im.BEZEICHNUNG = 'Telefonnummer'
		) AS PHONE ON pat.REFERENZ = PHONE.ADRESSEN_REFERENZ
		LEFT JOIN
		(
			SELECT mail.NUMMER AS PHONE,
			mail.ADRESSEN_REFERENZ
			FROM adz_kontakte AS mail INNER JOIN inf_kontaktart as im ON mail.KONTAKTART = im.REFERENZ
			WHERE im.BEZEICHNUNG = 'Telefonnummer'
		) AS MOBILE ON pat.REFERENZ = MOBILE.ADRESSEN_REFERENZ

		WHERE pat.GEBURTSDATUM=:dob OR :namesearch
		END;
		
		if (!trim($name) && !trim($dob)) return [];

		$name = preg_split('/\s+/', $name);
		$namesearch = [];
		foreach(['NACHNAME', 'NAME_2', 'NAME_3', 'NAME_4'] as $column){
			foreach($name as $namepart){
				if (!$namepart) continue;
				$namesearch[] = 'pat.' . $column . ' LIKE' . $this->_pdo->quote($namepart);
			}
		}

		try{
			$statement = $this->_pdo->prepare(strtr($query, [
				':dob' => $dob ? $this->_pdo->quote($dob . ' 00:00:00.000'): 'NULL',
				':namesearch' => implode(' OR ', $namesearch)
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
			$patient = [
				//'Nachname' =>  $row['NACHNAME'],
				//'Vorname' => implode(' ', array_filter(array_map(fn($c) => $row[$c] ? : '', ['NAME_2', 'NAME_3', 'NAME_4']), fn($v) => boolval($v))),
				'Name' => implode(' ', array_filter(array_map(fn($c) => $row[$c] ? : '', ['NAME_2', 'NAME_3', 'NAME_4', 'NACHNAME']), fn($v) => boolval($v))),
				//'Straße' => $row['STRASSE_1'],
				//'Postleitzahl' => $row['PLZ_1'],
				'Geburtsdatum' => substr($row['GEBURTSDATUM'] ? : '', 0, 10),
				//'Stadt' => $row['ORT_1'],
				//'Land' => $row['LKZ_1'],
				'Adresse' => $row['STRASSE_1'] . ', ' . $row['LKZ_1'] . '-' . $row['PLZ_1'] . ' ' . $row['ORT_1'],
				'Telefonnummer' => $row['PHONE'],
				'Mobil' => $row['MOBILE'],
				'eMailadresse' => $row['EMAIL'],
				'Kostenträger' => $row['KOSTENTRAEGER_NAME'],
				'FIBU Nummer' => $row['FIBU_NUMMER'],
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
			'random query' => <<<'END'
			SELECT * from database
			END,
			'random query 2' => <<<'END'
			SELECT * from database
			END,
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
	}
	
	/**
	 * retrieve recent data of erp consumables database
	 * @param array $vendors
	 * @param bool $as_passed false returns the remaining vendors but the passed ones
	 * @return null|array
	 * 
	 * availability of the method must be signalled by something, preferably [[]] to enable basic fall from notification module
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
		article2.MINDEST_BESTAND,
		article.ARTIKEL_REFERENZ,
		article.WARENEINGANGSDATUM

		FROM wws_artikel_lieferanten AS article
		left JOIN
		(
			SELECT BEZEICHNUNG,
			REFERENZ
			FROM inf_einheit
		) AS unit ON unit.REFERENZ = article.BESTELL_EINHEIT
		left JOIN
		(
			SELECT
			REFERENZ,
			ZUSATZINFORMATION,
			MINDEST_BESTAND
			FROM wws_artikelstamm
		) AS article2 ON article2.REFERENZ = article.ARTIKEL_REFERENZ
		left JOIN
		(
			SELECT 
			v.NAME_1,
			v.REFERENZ
			FROM inf_adressart AS ia
			INNER JOIN adressen AS v ON v.ADRESSART = ia.REFERENZ
			WHERE ia.BEZEICHNUNG = 'Lieferanten'
		) AS vendor ON article.LIEFERANTEN_REFERENZ = vendor.REFERENZ
		
		WHERE article.BESTELLSTOP = 0 AND article.STATUS = 0
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
				'stock_item' => $row['MINDEST_BESTAND'] ? 1 : null,
				'erp_id' => $row['ARTIKEL_REFERENZ'],
				'last_order' => $row['WARENEINGANGSDATUM'] ? substr($row['WARENEINGANGSDATUM'], 0, 10) : null
			];
		}
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
		orders.ORDER_DATUM,
		orders.MENGE,
		orders2.WE_DATUM,
		orders2.WE_MENGE,
		orders2.BESTELL_BELEGNUMMER,
		vendor.NAME_1 as LIEFERANTEN_NAME
		FROM
		wws_order as orders
		LEFT JOIN
		(
			SELECT
			BESTELL_TEXT AS ARTIKELBEZEICHNUNG,
			ARTIKEL_REFERENZ
			FROM wws_artikel_lieferanten
		) AS article ON orders.ARTIKELNUMMER = article.ARTIKEL_REFERENZ
		LEFT JOIN
		(
			SELECT
			REFERENZ,
			WE_DATUM,
			WE_MENGE,
			BESTELL_BELEGNUMMER
			FROM wws_bestellung
		) AS orders2 ON orders.BESTELLUNGS_REFERENZ = orders2.REFERENZ
		LEFT JOIN
		(
			SELECT 
			v.NAME_1,
			v.REFERENZ
			FROM inf_adressart AS ia
			INNER JOIN adressen AS v ON v.ADRESSART = ia.REFERENZ
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
				'ordered' => $row['ORDER_DATUM'] ? substr($row['ORDER_DATUM'], 0, -4) : null,
				'partially_received' => $row['WE_DATUM'] && $row['WE_MENGE'] != $row['MENGE'] ? substr($row['WE_DATUM'], 0, -4) : null,
				'received' => $row['WE_DATUM'] && $row['WE_MENGE'] == $row['MENGE'] ? substr($row['WE_DATUM'], 0, -4) : null,
			];
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
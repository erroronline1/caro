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

class _ERPINTERFACE {
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
	 * retrieve most recent customer data details based on matching name /dob
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
	 * retrieve recent data on erp consumables database
	 * @return null|array 
	 */
	public function consumables(){
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
		 * 			'identifier' => string, part of order text previously pasted during order process generated by caro app in the scheme `  |sz9623` two space-pipe-base 36 unixtime according to UTILITY::identifier(), format accordingly
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
	public function __construct(){
		parent::__construct();
	}

	/**
	 * retrieve current case states based on passed case numbers
	 * @param array $erp_case_numbers
	 * @return null|array
	 * 
	 * sanitize parameter according to the usecase e.g. dbo driver
	 */
	public function casestate($erp_case_numbers = []){
		/**
		 * return [
		 * 		'{erp_case_number}' => [
		 * 			'reimbursement' => Y-m-d,
		 *			'inquiry' => Y-m-d,
		 *			'partiallygranted' => Y-m-d,
		 *			'granted' => Y-m-d,
		 *			'production' => Y-m-d,
		 *			'settled' => Y-m-d,
		 * 		],
		 * 		...
		 * ]
		 */
		/*
SELECT TOP (1000)
[REFERENZ]
,[KV_DATUM]
,[GENEHMIGT_DATUM]
,[GENEHMIGT_TEILSUMME]
,[GELIEFERT_DATUM]
,[FAKTURIERT_DATUM]
FROM [eva3_02_viva_souh].[dbo].[vorgaenge]
ORDER BY ID DESC
		*/
		return null;
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
	 * retrieve most recent customer data details based on matching name /dob
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
		/*
SELECT 
pat.*,
KOSTENTRAEGER.NAME_1 AS KOSTENTRAEGER_NAME,
EMAIL.EMAIL,
PHONE.PHONE,
MOBILE.PHONE AS MOBILE
FROM
(
    SELECT a.NAME_1,
    a.NAME_2,
    a.NAME_3,
    a.NAME_4,
    a.GEBURTSNAME,
    a.GEBURTSDATUM,
    a.STRASSE_1,
    a.PLZ_1,
    a.ORT_1,
    a.FIBU_NUMMER,
    a.REFERENZ,
    more.KOSTENTRAEGER AS KOSTENTRAEGER_REFERENZ
    FROM [eva3_02_viva_souh].[dbo].[adressen] AS a INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia ON a.ADRESSART = ia.REFERENZ
    LEFT JOIN [eva3_02_viva_souh].[dbo].[adr_kunden] AS more ON more.ADRESSEN_REFERENZ = a.REFERENZ
    WHERE ia.BEZEICHNUNG = 'Kunden / Patienten'
) as pat
LEFT JOIN
(
    SELECT ka.NAME_1,
	ka.REFERENZ
    FROM [eva3_02_viva_souh].[dbo].[adressen] AS ka INNER JOIN [eva3_02_viva_souh].[dbo].[inf_adressart] AS kia ON ka.ADRESSART = kia.REFERENZ
    WHERE kia.BEZEICHNUNG = 'Kostenträger'
) AS KOSTENTRAEGER ON pat.KOSTENTRAEGER_REFERENZ = KOSTENTRAEGER.REFERENZ
LEFT JOIN
(
    SELECT mail.NUMMER AS EMAIL,
	mail.ADRESSEN_REFERENZ
    FROM [eva3_02_viva_souh].[dbo].[adz_kontakte] AS mail INNER JOIN [eva3_02_viva_souh].[dbo].[inf_kontaktart] as im ON mail.KONTAKTART = im.REFERENZ
    WHERE im.BEZEICHNUNG = 'E-Mail'
) AS EMAIL ON pat.REFERENZ = EMAIL.ADRESSEN_REFERENZ
LEFT JOIN
(
    SELECT mail.NUMMER AS PHONE,
	mail.ADRESSEN_REFERENZ
    FROM [eva3_02_viva_souh].[dbo].[adz_kontakte] AS mail INNER JOIN [eva3_02_viva_souh].[dbo].[inf_kontaktart] as im ON mail.KONTAKTART = im.REFERENZ
    WHERE im.BEZEICHNUNG = 'Telefonnummer'
) AS PHONE ON pat.REFERENZ = PHONE.ADRESSEN_REFERENZ
LEFT JOIN
(
    SELECT mail.NUMMER AS PHONE,
	mail.ADRESSEN_REFERENZ
    FROM [eva3_02_viva_souh].[dbo].[adz_kontakte] AS mail INNER JOIN [eva3_02_viva_souh].[dbo].[inf_kontaktart] as im ON mail.KONTAKTART = im.REFERENZ
    WHERE im.BEZEICHNUNG = 'Telefonnummer'
) AS MOBILE ON pat.REFERENZ = MOBILE.ADRESSEN_REFERENZ
		 */
		return null;
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

		/**
		 * return [
		 * 		[
		 * 			'vendor' => string,
		 * 			'article_no' => string,
		 * 			'article_name' => string,
		 * 			'identifier' => string, part of order text previously pasted during order process generated by caro app in the scheme `  |sz9623` two space-pipe-base 36 unixtime according to UTILITY::identifier(), format accordingly
		 * 			'ordered' => Y-m-d H:i:s,
		 * 			'partially_received' => Y-m-d H:i:s,
		 * 			'received' => Y-m-d H:i:s,
		 * 		],
		 * 		...
		 * ]
		 */
		/*
SELECT
orders.BESTELLNUMMER,
orders.BEZEICHNUNG,
orders.BESTELL_DATUM,
orders.BESTELL_MENGE,
orders.WE_MENGE,
orders.BESTELL_BELEGNUMMER,
vendor.NAME_1 as LIEFERANTEN_NAME,
article.BESTELL_TEXT
FROM [eva3_02_viva_souh].[dbo].[wws_bestellung] AS orders
LEFT JOIN
(
    SELECT
    BESTELL_TEXT,
	ARTIKEL_REFERENZ
    FROM [eva3_02_viva_souh].[dbo].[wws_artikel_lieferanten]
) AS article ON orders.ARTIKELNUMMER = article.ARTIKEL_REFERENZ
LEFT JOIN
(
    SELECT 
    v.NAME_1,
	v.REFERENZ
    FROM [eva3_02_viva_souh].[dbo].[inf_adressart] AS ia
    INNER JOIN [eva3_02_viva_souh].[dbo].[adressen] AS v ON v.ADRESSART = ia.REFERENZ
    WHERE ia.BEZEICHNUNG = 'Lieferanten'
) AS vendor ON orders.LIEFERANTEN_REFERENZ = vendor.REFERENZ

WHERE orders.BESTELL_DATUM > '2025-01-01 00:00:00.000'
ORDER BY orders.BESTELL_DATUM DESC

		*/
		return null;
		//return [[]];
		return [
			[
				'vendor' => 'Otto Bock HealthCare Deutschland GmbH',
				'article_no' => '99B25',
				'article_name' => 'Schlauch-Strumpf',
				'identifier' => '  |sz9623',
				'ordered' => '2025-09-01 21:00:00',
				'partially_received' => null,
				'received' => '2025-09-01 21:00:00',
			],
		];
	}
}


$call = "CARO\\API\\" . strtoupper(CONFIG['system']['erp']);
if (CONFIG['system']['erp'] && class_exists($call)) {
	define("ERPINTERFACE", new $call());
}
else define("ERPINTERFACE", null);
?>
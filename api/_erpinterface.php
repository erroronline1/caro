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

// IF you get a connection to your erp-software via any api

class _ERPINTERFACE {
	/**
	 * define expected methods to be overridden by actual interface class if available
	 */
	public function __construct(){
		
	}

	/**
	 * retrieve current case states based on passed case numbers
	 * @param array $casenumbers
	 * @return null|array 
	 */
	public function casestate($casenumbers = []){

		/**
		 * return [
		 * 		[
		 * 			'casenumber' => string,
		 * 			'reimbursement'=> Y-m-d,
		 *			'inquiry'=> Y-m-d,
		 *			'partiallygranted'=> Y-m-d,
		 *			'granted'=> Y-m-d,
		 *			'production'=> Y-m-d,
		 *			'settled'=> Y-m-d,
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
	 */
	public function customerdata($name = null, $dob = null){

		/**
		 * return [
		 * 		[
		 * 			'family_name' => string,
		 * 			'given_name' => string,
		 * 			'name' => string, // glued
		 * 			'birthdate' => Y-m-d
		 * 			'street' => string,
		 * 			'number' => string,
		 * 			'postal_code' => string,
		 * 			'city' => string,
		 * 			'country' => string,
		 * 			'address' => string, // glued
		 * 			'phone' => string,
		 * 			'email' => string,
		 * 			'insurance' => string,
		 * 			'patient_number' => string
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
		$from = $from->format('Y-m-d H:i:s');
		$until = $until->format('Y-m-d H:i:s');

		/**
		 * return [
		 * 		[
		 * 			'vendor' => string,
		 * 			'article_no' => string,
		 * 			'article_name' => string,
		 * 			'commission' => string,
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

class OD extends _ERPINTERFACE {
	public function __construct(){
		parent::__construct();
	}
}


$call = "CARO\\API\\" . strtoupper(CONFIG['system']['erp']);
if (CONFIG['system']['erp'] && class_exists($call)) {
	define("ERPINTERFACE", new $call());
}
else define("ERPINTERFACE", null);
?>
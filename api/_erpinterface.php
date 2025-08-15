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
	 * retrieve current case state based on passed case number?
	 */
	public function casestate($casenumber){
		return null;
	}

	/**
	 * retrieve most recent customer data details based on passed customer id?
	 */
	public function customerdata($customerid){
		return null;
	}

	/**
	 * retrieve recent data on erp consumables database
	 */
	public function consumables(){
		return null;
	}

	/**
	 * retrieve recent data on processed orders
	 */
	public function orderdata(){
		return null;
	}
}

class OD extends _ERPINTERFACE {
	public function __construct(){
		parent::__construct();
	}
}

if (CONFIG['system']['erp'] && class_exists(CONFIG['system']['erp'])) {
	$call = "CARO\\API\\" . strtoupper(CONFIG['system']['erp']);
	define("ERPINTERFACE", new $call());
}
else define("ERPINTERFACE", null);
?>
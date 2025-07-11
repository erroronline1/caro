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

// application maintainance tools
class MAINTENANCE extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedType = null;
	private $_request = null;

	public function __construct(){
		parent::__construct();
		if (!PERMISSION::permissionFor('maintenance') || array_intersect(['patient'], $_SESSION['user']['permissions'])) $this->response([], 401);

		$this->_requestedType = isset(REQUEST[2]) ? REQUEST[2] : null;
		$this->_request = isset(REQUEST[3]) ? REQUEST[3] : null;
	}

	/**
	 *                         _         
	 *   ___ ___ ___ ___      | |___ ___ 
	 *  |  _|  _| . |   |     | | . | . |
	 *  |___|_| |___|_|_|_____|_|___|_  |
	 *                  |_____|     |___|
	 * display and delete log file
	 */
	private function cron_log(){
		// also see application.php->cron()
		$logfile = 'cron.log';
		switch ($_SERVER['REQUEST_METHOD']){
			case 'DELETE':
				if (unlink($logfile)) $this->response([], 410);
				$this->response([], 404);
				break;
			default: // get
				$content = [];
				if (!is_file($logfile)) return [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => 'CRON'
						],
						'content' => $this->_lang->GET('maintenance.cron.not_found')
					]
				];
				$cron = file($logfile);

				$content[] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => 'CRON'
					],
					'content' => implode("\n", $cron)
				];
				$content[] = [
					'type' => 'deletebutton',
					'attributes' => [
						'value' => $this->_lang->GET('maintenance.cron.delete', [':count' => count($cron) - 1]),
						'onclick' => "api.application('delete', 'cron_log')"
					]
				];
		}
		return $content;
	}
	
	/**
	 *   _           _   
	 *  | |_ ___ ___| |_ 
	 *  |  _| .'|_ -| '_|
	 *  |_| |__,|___|_,_|
	 * 
	 * entry point
	 */ 
	public function task(){
		$response['render'] = ['content' => []];
		$selecttypes = ['...' => []];
		
		foreach ([
			'cron_log',
			'records_datalist',
			'vendorupdate',
			] as $category){
				$selecttypes[$this->_lang->GET('maintenance.navigation.' . $category)] = ['value' => $category];
				if ($this->_requestedType === $category) $selecttypes[$this->_lang->GET('maintenance.navigation.' . $category)]['selected'] = true;
		}
		ksort($selecttypes);
		$response['render']['content'][] = [
			[
				'type' => 'select',
				'content' => $selecttypes,
				'attributes' => [
					'name' => $this->_lang->GET('audit.checks_select_type'),
					'onchange' => "if (this.value !== '...') api.maintenance('get', 'task', this.value)"
				]
			]
		];

		if ($this->_requestedType && $this->_requestedType !== '...') {
			if ($append = $this->{$this->_requestedType}()) array_push($response['render']['content'] , ...$append);
		}
		$this->response($response);
	}

	/**
	 *                         _         _     _       _ _     _   
	 *   ___ ___ ___ ___ ___ _| |      _| |___| |_ ___| |_|___| |_ 
	 *  |  _| -_|  _| . |  _| . |     | . | .'|  _| .'| | |_ -|  _|
	 *  |_| |___|___|___|_| |___|_____|___|__,|_| |__,|_|_|___|_|  
	 *                          |_____|
	 * download, edit and upload trained record recommendation
	 * primarily for sanitation purpose, can be used for prepopulation as well
	 * csv for easiest handling
	 */
	private function records_datalist(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				// process csv. if valid truncate table and chunkify insert
				break;
			default:
				// display option and explanation
				// if $this->_request return csv
		}

	}

	/**
	 *                 _                   _     _       
	 *   _ _ ___ ___ _| |___ ___ _ _ ___ _| |___| |_ ___ 
	 *  | | | -_|   | . | . |  _| | | . | . | .'|  _| -_|
	 *   \_/|___|_|_|___|___|_| |___|  _|___|__,|_| |___|
	 *                              |_|
	 * also see install.php
	 * this is only somewhat a duplicate of install.php since the template file can be uploaded to not be reliant on server access
	 */
	private function vendorupdate(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				// store within temp to remain for put action, display matches and selection
				break;
			case 'PUT':
				// actual update vendors as per selection
				break;
			case "GET": 
				// upload form
		}
	}
}
?>
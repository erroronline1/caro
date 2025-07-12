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
		$response = ['render' => ['content' => []]];
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

			if (in_array($this->_requestedType, ['records_datalist', 'vendorupdate']) && $_SERVER['REQUEST_METHOD'] !== 'PUT'){
				$response['render']['form'] = [
					'data-usecase' => 'maintenance',
					'action' => "javascript:api.maintenance('" . ($_SERVER['REQUEST_METHOD'] === 'GET' ? 'post' : 'put') . "', 'task', '" . $this->_requestedType . "')"
				];
			}
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
		$content = [];
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				// store within temp to remain for put action, display matches and selection
				if (isset($_FILES[$this->_lang->PROPERTY('maintenance.vendorupdate.file')]) && $_FILES[$this->_lang->PROPERTY('maintenance.vendorupdate.file')]['tmp_name']) {
					$file = UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('maintenance.vendorupdate.file')], UTILITY::directory('tmp'), [], [$_SESSION['user']['id'] . 'vendorupdate'], true);
					$json = file_get_contents($file[0]);
					$json = json_decode($json, true);
					if (!$json)	{
						return [[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('maintenance.vendorupdate.format_error'),
								'class' => 'red'
							]
						]];
					}
					$content[] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('maintenance.vendorupdate.select_description'),
							]
					];
					// gather possibly existing entries
					$DBall = [
						...SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist'),
					];
					$intersections = array_intersect(array_column($DBall, 'name'), array_column($json, 'name'));
					$options = [];
					foreach ($DBall as $vendor){
						if (!in_array($vendor['name'], $intersections)) continue;
						$options[] = [
							'type' => 'checkbox',
							'attributes' => [
								'name' => $vendor['name']
							],
							'hint' => $vendor['hidden'] ? $this->_lang->GET('maintenance.vendorupdate.vendor_hidden') : null,
							'content' => [
								$this->_lang->GET('maintenance.vendorupdate.update_info') => [],
								$this->_lang->GET('maintenance.vendorupdate.update_pricelist') => [],
							]
						];
					}
					if ($options) $content[] = $options;
					if ($missing = array_diff(array_column($DBall, 'name'), $intersections)){
						$content[] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('maintenance.vendorupdate.vendor_missing'),
							],
							'content' => implode(', ', $missing)
						];
					}
				}
				else {
					return [[
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('maintenance.vendorupdate.file_error'),
							'class' => 'red'
						]
					]];
				}
				break;
			case 'PUT':
				// actual update vendors as per selection

				// no exception handling, since the file validity has been checked upon upload already
				$json = file_get_contents(UTILITY::directory('tmp') . '/' . $_SESSION['user']['id'] . 'vendorupdate.json');
				$json = json_decode($json, true);
				$DBall = [
					...SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist'),
				];
				$intersections = array_intersect(array_column($DBall, 'name'), array_column($json, 'name'));
				
				$sqlchunks = [];
				$success = [];
				foreach($this->_payload as $key => $value){
					if(!in_array($key, $intersections)) continue;

					$vendor = $DBall[array_search($key, array_column($DBall, 'name'))];
					$entry = $json[array_search($key, array_column($json, 'name'))];
					$selected = explode(' | ', $value);
					// sanitize options being numerated
					foreach($selected as &$selection){
						$selection = preg_replace('/\(\d+\)$/', '', $selection);
					}

					if (in_array($this->_lang->GET('maintenance.vendorupdate.update_info'), $selected)){
						$vendor['info'] = isset($entry['info']) && gettype($entry['info']) === 'array' ? UTILITY::json_encode($entry['info']) : $vendor['info'];
					}
					if (in_array($this->_lang->GET('maintenance.vendorupdate.update_pricelist'), $selected)) {
						$vendor['pricelist'] = json_decode($vendor['pricelist'] ? : '', true);
						$newpricelistfilter = (isset($entry['pricelist']) && gettype($entry['pricelist']) === 'array' && isset($entry['pricelist']['filter'])) ? UTILITY::json_encode($entry['pricelist']['filter'], JSON_PRETTY_PRINT) : null;
						$vendor['pricelist']['filter'] = $newpricelistfilter ? : (isset($vendor['pricelist']['filter']) ? $vendor['pricelist']['filter'] : null);
						if(isset($entry['pricelist']['samplecheck_interval']) && $entry['pricelist']['samplecheck_interval']) $vendor['pricelist']['samplecheck_interval'] = $entry['pricelist']['samplecheck_interval'];
						if(isset($entry['pricelist']['samplecheck_reusable']) && $entry['pricelist']['samplecheck_reusable']) $vendor['pricelist']['samplecheck_reusable'] = $entry['pricelist']['samplecheck_reusable'];

						$vendor['pricelist'] = UTILITY::json_encode($vendor['pricelist'], JSON_PRETTY_PRINT);
					}

					$sqlchunks = SQLQUERY::CHUNKIFY($sqlchunks, strtr(SQLQUERY::PREPARE('consumables_put_vendor'),
						[
							':id' => $vendor['id'],
							':name' => $this->_pdo->quote($vendor['name']),
							':evaluation' => $vendor['evaluation'] ? $this->_pdo->quote($vendor['evaluation']) : 'NULL',
							':hidden' => $vendor['hidden'] ? $this->_pdo->quote($vendor['hidden']) : 'NULL',
							':certificate' => $vendor['certificate'] ? $this->_pdo->quote($vendor['certificate']) : 'NULL',
							':info' => $vendor['info'] ? $this->_pdo->quote($vendor['info']) : 'NULL',
							':pricelist' => $vendor['pricelist'] ? $this->_pdo->quote($vendor['pricelist']) : 'NULL',
						]) . '; ');
					$success[] = $vendor['name'];
				}
				foreach ($sqlchunks as $chunk){
					try {
						SQLQUERY::EXECUTE($this->_pdo, $chunk);
					}
					catch (\Exception $e) {
						return [[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('maintenance.vendorupdate.update_error'),
							],
							'content' => $e,
						]];
					}
				}
				if ($success) return [[
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('maintenance.vendorupdate.update_success'),
					],
					'content' => implode('\n', $success),
				]];
				break;
			case "GET": 
				// upload form
				$content[] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('maintenance.navigation.vendorupdate'),
					],
					'content' => $this->_lang->GET('maintenance.vendorupdate.description'),
				];
				$content[] = [
					'type' => 'file',
					'attributes' => [
						'required'=> true,
						'name' => $this->_lang->GET('maintenance.vendorupdate.file'),
					] 
				];
		}
		return $content;
	}
}
?>
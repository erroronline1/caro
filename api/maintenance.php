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

	public function __construct(){
		parent::__construct();
		if (!PERMISSION::permissionFor('maintenance') || array_intersect(['patient'], $_SESSION['user']['permissions'])) $this->response([], 401);

		$this->_requestedType = isset(REQUEST[2]) ? REQUEST[2] : null;
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
				$response = ['render' => ['content' => []]];
				if (!is_file($logfile)) {
					$response['response'] = ['msg' => $this->_lang->GET('maintenance.cron.not_found'), 'type' => 'info'];
					return $response;
				}
				$cron = file($logfile);

				$response['render']['content'][] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => 'CRON'
					],
					'content' => implode("\n", preg_replace('/\n$/m', '', $cron))
				];
				$response['render']['content'][] = [
					'type' => 'deletebutton',
					'attributes' => [
						'value' => $this->_lang->GET('maintenance.cron.delete', [':count' => count($cron) - 1]),
						'onclick' => "api.maintenance('delete', 'task', 'cron_log')"
					]
				];
		}
		return $response;
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
			if ($append = $this->{$this->_requestedType}()) {
				if (isset($append['render']['content']) && $append['render']['content']) array_push($response['render']['content'], ...$append['render']['content']);
				if (isset($append['render']['form']) && $append['render']['form']) $response['render']['form'] = $append['render']['form'];
				if (isset($append['response']) && $append['response']) $response['response'] = $append['response'];
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
		$response = ['render' => ['content' => []]];
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (($unit = array_search(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('maintenance.record_datalist.unit')), $this->_lang->_USER['units'])) === false) die;
				
				if (isset($_FILES[$this->_lang->PROPERTY('maintenance.record_datalist.upload')]) && $_FILES[$this->_lang->PROPERTY('maintenance.record_datalist.upload')]['tmp_name']) {

					$data = [];
					$rownum = 0;
					if (($handle = fopen($_FILES[$this->_lang->PROPERTY('maintenance.record_datalist.upload')]['tmp_name'][0], 'r')) !== false) {
						while (($row = fgetcsv($handle, null,
							CONFIG['csv']['dialect']['separator'],
							CONFIG['csv']['dialect']['enclosure'],
							CONFIG['csv']['dialect']['escape'])) !== FALSE) {
							if ($rownum < 1){
								if (count($row) < 2){
									$response['response'] = ['msg' => $this->_lang->GET('maintenance.record_datalist.update_error'), 'type' => 'error'];
									$response['render']['content'][] = [
										'type' => 'textsection',
										'attributes' => [
											'name' => $this->_lang->GET('maintenance.record_datalist.update_error'),
										],
										'content' => $this->_lang->GET('maintenance.record_datalist.update_abort', [':format' => "\n" . UTILITY::json_encode(CONFIG['csv']['dialect'], JSON_PRETTY_PRINT)]) . "\n" . mb_convert_encoding(implode(', ', $row), 'UTF-8', mb_detect_encoding(implode(', ', $row), ['ASCII', 'UTF-8', 'ISO-8859-1'])),
									];
									return $response;
								}
								// set header as data keys
								foreach($row as $column){
									if ($column) {
										$bom = pack('H*','EFBBBF'); //coming from excel this is utf8
										$column = preg_replace("/^$bom/", '', $column);
										$data[$column] = [];
									}
								}
							}
							else {
								// append content to data keys
								foreach($row as $index => $column){
									if ($column) $data[array_keys($data)[$index]][] = $column;
								}
							}
							$rownum++;
						}
						fclose($handle);
					}

					// sort datalists and append sql query
					$insertions = [];
					foreach($data as $issue => $datalist){
						$datalist = array_values(array_unique($datalist));
						sort($datalist);
						$insertions[] = [
							':issue' => $issue,
							':unit' => $unit,
							':datalist' => UTILITY::json_encode($datalist)
						];
					}

					$sqlchunks = SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('records_datalist_post'), $insertions);
					if ($sqlchunks){
						// drop unit entries
						if (SQLQUERY::EXECUTE($this->_pdo, 'records_datalist_delete', ['values' => [':unit' => $unit]])) $response['render']['content'][] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('maintenance.record_datalist.update_deleted'),
							]
						];

						foreach ($sqlchunks as $chunk){
							try {
								SQLQUERY::EXECUTE($this->_pdo, $chunk);
							}
							catch (\Exception $e) {
								$response['render']['content'][] = [
									'type' => 'textsection',
									'attributes' => [
										'name' => $this->_lang->GET('maintenance.record_datalist.update_error'),
									],
									'content' => $e,
								];
								return $response;
							}
						}
						$response['render']['content'][] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('maintenance.record_datalist.update_success', [':unit' => $this->_lang->_USER['units'][$unit]]),
							]
						];
					}
					else $response['render']['content'][] = [
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('maintenance.record_datalist.update_error'),
						]
					];
				}
				else {
					$datalists = SQLQUERY::EXECUTE($this->_pdo, 'records_datalist_get', ['values' => [':unit' => $unit]]);
					if (!$datalists){
						$response['response'] = [
							'msg' => $this->_lang->GET('maintenance.record_datalist.empty', [':unit' => $this->_lang->_USER['units'][$unit]]),
							'type' => 'error'
						];
						return $response;
					}
					$data = [];
					$maxlength = 0;
					foreach($datalists as $row){
						$data[$row['issue']] = json_decode($row['datalist']);
						if(count($data[$row['issue']]) > $maxlength) $maxlength = count($data[$row['issue']]);
					}
					// fill up datalists with empty values to match maxlength
					// no manipulation by &reference here, doesn't work
					foreach ($data as $issue => $datalist) {
						if (count($datalist) < $maxlength) array_push($data[$issue], ...array_fill(0, $maxlength - count($datalist), ''));
					}

					$name = preg_replace(CONFIG['forbidden']['names']['characters'], '_', $this->_lang->_USER['units'][$unit]);
					$tempFile = UTILITY::directory('tmp') . '/' . $this->_date['usertime']->format('Y-m-d H-i-s ') . $name . '.csv';
					$file = fopen($tempFile, 'w');
					fwrite($file, b"\xEF\xBB\xBF"); // tell excel this is utf8
					fputcsv($file, array_keys($data),
						CONFIG['csv']['dialect']['separator'],
						CONFIG['csv']['dialect']['enclosure'],
						CONFIG['csv']['dialect']['escape']);

					for($i = 0; $i < $maxlength; $i++){
						$row = [];
						foreach ($data as $issue => $datalist) {
							$row[] = $datalist[$i];
						}
						fputcsv($file, $row,
						CONFIG['csv']['dialect']['separator'],
						CONFIG['csv']['dialect']['enclosure'],
						CONFIG['csv']['dialect']['escape']);
					}
					fclose($file);
					// provide downloadfile
					$downloadfiles[pathinfo($tempFile)['basename']] = [
						'href' => './api/api.php/file/stream/' . substr(UTILITY::directory('tmp'), 1) . '/' . pathinfo($tempFile)['basename'],
						'download' => pathinfo($tempFile)['basename']
					];
					$this->response(['links' => $downloadfiles]);
				}
				break;
			case 'GET':
				// display options and explanation

				$response['render']['content'][] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('maintenance.navigation.records_datalist'),
					],
					'content' => $this->_lang->GET('maintenance.record_datalist.description')
				];
				$units = [];
				foreach($this->_lang->_USER['units'] as $unit => $description){
					$units[$description] = [];
				}
				$response['render']['content'][] = [
					'type' => 'radio',
					'attributes' => [
						'name' => $this->_lang->GET('maintenance.record_datalist.unit'),
						'required' => true
					],
					'content' => $units
				];
				$response['render']['content'][] = [
					'type' => 'file',
					'attributes' => [
						'name' => $this->_lang->GET('maintenance.record_datalist.upload'),
						'accept' => '.csv'
					] 
				];
				$response['render']['form'] = [
					'data-usecase' => 'maintenance',
					'action' => "javascript:api.maintenance('post', 'task', '" . $this->_requestedType . "')"
				];
		}
		return $response;
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
		$response = ['render' => ['content' => []]];
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				// store within temp to remain for put action, display matches and selection
				if (isset($_FILES[$this->_lang->PROPERTY('maintenance.vendorupdate.file')]) && $_FILES[$this->_lang->PROPERTY('maintenance.vendorupdate.file')]['tmp_name']) {
					$file = UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('maintenance.vendorupdate.file')], UTILITY::directory('tmp'), [], [$_SESSION['user']['id'] . 'vendorupdate'], true);
					$json = file_get_contents($file[0]);
					$json = json_decode($json, true);
					if (!$json)	{
						$response['response'] = [
							'msg' => $this->_lang->GET('maintenance.vendorupdate.format_error'),
							'type' => 'error'
						];
						return $response;
					}
					$response['render']['content'][] = [
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
					if ($options) $response['render']['content'][] = $options;
					if ($missing = array_diff(array_column($DBall, 'name'), $intersections)){
						$response['render']['content'][] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('maintenance.vendorupdate.vendor_missing'),
							],
							'content' => implode(', ', $missing)
						];
					}
					$response['render']['form'] = [
						'data-usecase' => 'maintenance',
						'action' => "javascript:api.maintenance('put', 'task', '" . $this->_requestedType . "')"
					];	
				}
				else {
					$response['response'] = [
						'msg' => $this->_lang->GET('maintenance.vendorupdate.file_error'),
						'type' => 'error'
					];
					return $response;
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
						$response['render']['content'][] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('maintenance.vendorupdate.update_error'),
							],
							'content' => $e,
						];
						return $response;
					}
				}
				if ($success) {
					$response['render']['content'][] = [
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('maintenance.vendorupdate.update_success'),
						],
						'content' => implode('\n', $success),
						];
					return $response;
				}
				break;
			case "GET": 
				// upload form
				$response['render']['content'][] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('maintenance.navigation.vendorupdate'),
					],
					'content' => $this->_lang->GET('maintenance.vendorupdate.description'),
				];
				$response['render']['content'][] = [
					'type' => 'file',
					'attributes' => [
						'required'=> true,
						'name' => $this->_lang->GET('maintenance.vendorupdate.file'),
					] 
				];
				$response['render']['form'] = [
					'data-usecase' => 'maintenance',
					'action' => "javascript:api.maintenance('post', 'task', '" . $this->_requestedType . "')"
				];
		}
		return $response;
	}
}
?>
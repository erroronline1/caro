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

// application maintainance tools

/*
https://github.com/mauris/ini-writer
*/
class Dumper {
    public function dump($input)
    {
        $output = '';
        foreach ($input as $section => $array) {
            $output .= self::writeSection($section, $array);
        }

        return $output;
    }

    protected static function writeSection($section, $array)
    {
        $subsections = array();
        $output = "[$section]\n";
        foreach ($array as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $key = $section . '.' . $key;
                $subsections[$key] = (array) $value;
            } else {
                $output .= self::normalizeKey($key) . '=';
                if (is_string($value)) {
                    $output .= '"' . addslashes($value) .'"';
                } elseif (is_bool($value)) {
                    $output .= $value ? 'true' : 'false';
                } else {
                    $output .= $value;
                }
                $output .= "\n";
            }
        }

        if ($subsections) {
            $output .= "\n";
            foreach ($subsections as $section => $array) {
                $output .= self::writeSection($section, $array);
            }
        }

        return $output;
    }

    protected static function normalizeKey($key)
    {
        return str_replace('=', '_', $key);
    }
}

class MAINTENANCE extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedType = null;

	public function __construct(){
		parent::__construct();
		if (!PERMISSION::permissionFor('maintenance') || array_intersect(['patient'], $_SESSION['user']['permissions'])) $this->response([], 401);

		$this->_requestedType = REQUEST[2] ?? null;
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
	 *                                             _   _
	 *   ___ ___ ___       ___ ___ ___ ___ ___ ___| |_|_|___ ___
	 *  | -_|  _| . |     |  _| . |   |   | -_|  _|  _| | . |   |
	 *  |___|_| |  _|_____|___|___|_|_|_|_|___|___|_| |_|___|_|_|
	 *          |_| |_____|
	 * temporarily disable erp-connection by overloading CONFIG['system']['erp']
	 */
	private function erp_connection(){
		$response = ['render' => ['content' => []]];
		$config_copy = constant('CONFIG');
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				// reactivate erp_connection by restoring initial config
				$unlinkfile = null;
				if (is_file('_config.env')) {
					// overwrite env file with copy and delete it
					copy('_config.env', 'config.env');
					$unlinkfile = '_config.env';
				}
				else {
					// delete env file als fallback to ini file
					// CAUTION: the api does not know if the env-file is intended. two consecutive calls could mess up the setting.
					// this should somewhat be handled from the frontend. bit messy, i know
					$unlinkfile = 'config.env';
				}
				if ($unlinkfile && unlink($unlinkfile)){
					$response['render']['content'][] = [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('maintenance.erp_connection.confirmation', [':state' => $this->_lang->GET('maintenance.erp_connection.enabled')]),
								'class' => 'orange'
							]
						]
					];
				}
				break;
			case 'DELETE':
				// deactivate erp_connection by overriding config, saving current state as a copy or creating an env file
				if (is_file('config.env')) {
					// create env file copy
					copy('config.env', '_config.env');
				}
				// create or overwrite env file
				$config_copy['system']['erp'] = false;

				$dump = new Dumper();
				if (file_put_contents('config.env', $dump->dump($config_copy))){
					$response['render']['content'][] = [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('maintenance.erp_connection.confirmation', [':state' => $this->_lang->GET('maintenance.erp_connection.disabled')]),
								'class' => 'orange'
							]
						]
					];
				}
				break;
		}

		$selection = [
			$this->_lang->GET('maintenance.erp_connection.enabled') => [
				'onchange' => "api.maintenance('put', 'task', 'erp_connection')",
				'class' => 'green'
			],
			$this->_lang->GET('maintenance.erp_connection.disabled') => [
				'onchange' => "api.maintenance('delete', 'task', 'erp_connection')",
				'class' => 'red'
			]
		];
		if ((ERPINTERFACE && ERPINTERFACE->_instatiated && $config_copy['system']['erp']) || $_SERVER['REQUEST_METHOD'] === 'PUT')
			$selection[$this->_lang->GET('maintenance.erp_connection.enabled')]['checked'] = true;
		else
			$selection[$this->_lang->GET('maintenance.erp_connection.disabled')]['checked'] = true;

		$response['render']['content'][] = [
			[
				'type' => 'textsection',
				'htmlcontent' => $this->_lang->GET('maintenance.erp_connection.explanation', [':announcement' => '<a href="javascript: api.message(\'get\', \'announcements\')" class="inline">' . $this->_lang->GET('message.announcement.new') . '</a>'])
			],
			[
				'type' => 'radio',
				'attributes' => [
					'name' => $this->_lang->GET('maintenance.erp_connection.select')
				],
				'content' => $selection
			]
		];
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
		
		$methods = [
			'cron_log',
			'erp_connection',
			'records_datalist',
			'riskupdate',
			'vendorupdate',
		];
		if (ERPINTERFACE && ERPINTERFACE->_instatiated && method_exists(ERPINTERFACE, 'consumables') && ERPINTERFACE->consumables()) $methods[] = 'productsupdate';

		foreach ($methods as $category){
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
	 *                 _         _                 _     _       
	 *   ___ ___ ___ _| |_ _ ___| |_ ___ _ _ ___ _| |___| |_ ___ 
	 *  | . |  _| . | . | | |  _|  _|_ -| | | . | . | .'|  _| -_|
	 *  |  _|_| |___|___|___|___|_| |___|___|  _|___|__,|_| |___|
	 *  |_|                                 |_|                  
	 */
	private function productsupdate () {
		if (!(ERPINTERFACE && ERPINTERFACE->_instatiated && method_exists(ERPINTERFACE, 'consumables') && ERPINTERFACE->consumables())){
			$this->response([], 405);
		}

		require_once('./erpquery.php');
		$ERPQUERY = new ERPQUERY();

		$response = ['render' => ['content' => $ERPQUERY->productsupdate()]];
		$response['render']['form'] = [
			'data-usecase' => 'maintenance',
			'action' => "javascript:api.maintenance('post', 'task', '" . $this->_requestedType . "')"
		];

		return $response;
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

				require_once('_table.php');
				
				if (isset($_FILES[$this->_lang->PROPERTY('maintenance.record_datalist.upload')]) && $_FILES[$this->_lang->PROPERTY('maintenance.record_datalist.upload')]['tmp_name']) {
					$data = [];

					$sourceproperties = pathinfo($_FILES[$this->_lang->PROPERTY('maintenance.record_datalist.upload')]['name'][0]);
					$source = new TABLE($_FILES[$this->_lang->PROPERTY('maintenance.record_datalist.upload')]['tmp_name'][0], $sourceproperties['extension']);
					$source = $source->dump([]);
					if (!$source || !$source[array_key_first($source)]) {
						$response['response'] = ['msg' => $this->_lang->GET('maintenance.record_datalist.update_error'), 'type' => 'error'];
						$response['render']['content'][] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('maintenance.record_datalist.update_error'),
							],
							'content' => $this->_lang->GET('maintenance.record_datalist.update_abort'),
						];
						return $response;
					}

					foreach($source[array_key_first($source)] as $values){
						foreach($values as $issue => $value){
							if (!$value) continue;
							if(!isset($data[$issue])) $data[$issue] = [];
							$data[$issue][] = $value;
						}
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

					// rotate data matrix - somewhat, since it's a named array
					$_data = [];
					for($i = 0; $i < $maxlength; $i++){
						$row = [];
						foreach ($data as $column => $datalist) {
							$row[$column] = $datalist[$i];
						}
						$_data[] = $row;
					}
					// add required array nesting for TABLE
					$_data = [$_data];

					$export = new TABLE($_data);

					if ($files = $export->dump(preg_replace(CONFIG['forbidden']['names']['characters'], '_', $this->_lang->_USER['units'][$unit]) . '.csv')){
						$downloadfiles[$this->_lang->GET('csvfilter.use.filter_download', [':file' => preg_replace(CONFIG['forbidden']['names']['characters'], '_', $this->_lang->_USER['units'][$unit]) . '.csv'])] = [
							'href' => './api/api.php/file/stream/' . substr($files[0], 1),
							'download' => preg_replace(CONFIG['forbidden']['names']['characters'], '_', $this->_lang->_USER['units'][$unit]) . '.csv'
						];
					}
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
	 *       _     _             _     _       
	 *   ___|_|___| |_ _ _ ___ _| |___| |_ ___ 
	 *  |  _| |_ -| '_| | | . | . | .'|  _| -_|
	 *  |_| |_|___|_,_|___|  _|___|__,|_| |___|
	 *                    |_|
	 * import and update lists from csv files matching the structure of this applications risk exports
	 */
	private function riskupdate(){
		$response = ['render' => ['content' => []]];
		$risks = SQLQUERY::EXECUTE($this->_pdo, 'risk_datalist');

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$process = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('risk.process'));
				$type = array_search(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('maintenance.riskupdate.type')), $this->_lang->_USER['risk']['type']);

				if (!trim($process) || !$type || !isset($_FILES[$this->_lang->PROPERTY('maintenance.riskupdate.file')]) || !$_FILES[$this->_lang->PROPERTY('maintenance.riskupdate.file')]['tmp_name'][0]) $this->response([$process, $type, $_FILES[$this->_lang->PROPERTY('maintenance.riskupdate.file')]['tmp_name'][0]], 417);

				require_once('./_csvprocessor.php');

				$filter = [
					'risk' => [
						'filesetting' => [
							'headerrow' => 3,
							'columns' => [
								$this->_lang->GET('risk.risk_related', [], true),
								$this->_lang->GET('risk.relevance', [], true),
								$this->_lang->GET('risk.cause', [], true),
								$this->_lang->GET('risk.effect', [], true),
								$this->_lang->GET('risk.probability', [], true),
								$this->_lang->GET('risk.damage', [], true),
								$this->_lang->GET('risk.measure', [], true),
								$this->_lang->GET('risk.measure_probability', [], true),
								$this->_lang->GET('risk.measure_damage', [], true),
								$this->_lang->GET('risk.risk_benefit', [], true),
								$this->_lang->GET('risk.measure_remainder', [], true)
							]
						],
						'modify' => [
							'rewrite' => [
								[
									'risk' => [$this->_lang->GET('risk.risk_related', [], true)],
									'relevance' => [$this->_lang->GET('risk.relevance', [], true)],
									'cause' => [$this->_lang->GET('risk.cause', [], true)],
									'effect' => [$this->_lang->GET('risk.effect', [], true)],
									'probability' => [$this->_lang->GET('risk.probability', [], true)],
									'damage' => [$this->_lang->GET('risk.damage', [], true)],
									'measure' => [$this->_lang->GET('risk.measure', [], true)],
									'measure_probability' => [$this->_lang->GET('risk.measure_probability', [], true)],
									'measure_damage' => [$this->_lang->GET('risk.measure_damage', [], true)],
									'risk_benefit' => [$this->_lang->GET('risk.risk_benefit', [], true)],
									'measure_remainder' => [$this->_lang->GET('risk.measure_remainder', [], true)]
								]
							]
						]
					],
					'characteristic' => [
						'filesetting' => [
							'headerrow' => 3,
							'columns' => [
								$this->_lang->GET('risk.type.characteristic', [], true),
								$this->_lang->GET('risk.relevance', [], true),
								$this->_lang->GET('risk.cause', [], true),
								$this->_lang->GET('risk.risk_related', [], true)
							]
						],
						'modify' => [
							'rewrite' => [
								[
									'risk' => [$this->_lang->GET('risk.risk_related', [], true)],
									'relevance' => [$this->_lang->GET('risk.relevance', [], true)],
									'cause' => [$this->_lang->GET('risk.cause', [], true)],
									'measure' => [$this->_lang->GET('risk.type.characteristic', [], true)]
								]
							]
						]
					]
				];

				$content = $filter[$type];

				require_once('_table.php');
				$source = new TABLE($_FILES[$this->_lang->PROPERTY('maintenance.riskupdate.file')]['tmp_name'][0], 'csv', ['headerrow' => 3]);
				$source = $source->dump([]);
				$content['filesetting']['source'] = $source[array_key_first($source)];

				$datalist = new Listprocessor($content);

				if (!isset($datalist->_list[1])) $this->response([
					'response' => [
						'msg' => implode('<br />', $datalist->_log),
						'type' => 'error'
					]
				]);

				$sqlchunks = $anomalies = [];
				$new = count($datalist->_list[1]);
				$update = 0;
				foreach ($datalist->_list[1] as $importrisk){
					$newrisk = [
						':id' => null,
						':type' => $type,
						':process' => $process,
						':risk' => '',
						':relevance' => isset($importrisk['relevance']) ? ($importrisk['relevance'] === $this->_lang->GET('risk.relevance_yes', [], true) ? 1 : 0): null,
						':cause' => $importrisk['cause'] ?? null,
						':effect' => $importrisk['effect'] ?? null,
						':probability' => null,
						':damage' => null,
						':measure' => $importrisk['measure'] ?? null,
						':measure_probability' => isset($importrisk['measure_probability']) ? array_search($importrisk['measure_probability'], $this->_lang->_DEFAULT['risk']['probabilities']) + 1 : null,
						':measure_damage' => isset($importrisk['measure_damage']) ? array_search($importrisk['measure_damage'], $this->_lang->_DEFAULT['risk']['damages']) + 1 : null,
						':risk_benefit' => $importrisk['risk_benefit'] ?? null,
						':measure_remainder' => $importrisk['measure_remainder'] ?? null,
						':proof' => null,
						':hidden' => null,
						':author' => $_SESSION['user']['name']
					];

					// translate risks, probabilies and damages to language file keys
					if (isset($importrisk['risk']) && $importrisk['risk']){
						$translated = [];
						foreach(explode("\n", $importrisk['risk']) as $r){
							if ($key = array_search(trim($r), $this->_lang->_DEFAULT['risks'])) $translated[] = $key;
							else $anomalies[] = $this->_lang->GET('maintenance.riskupdate.anomalies.risk_mismatch', [':risk' => $r]);
						}
						if ($translated) $newrisk[':risk'] = implode(',', $translated);
						else {
							$new--;
							continue;
						}
					}
					foreach(['probability', 'measure_probability'] as $column){
						if (!isset($importrisk[$column]) || !$importrisk[$column]) continue;
						if (($key = array_search($importrisk[$column], $this->_lang->_DEFAULT['risk']['probabilities'])) !== false) $newrisk[':' . $column] = $key + 1;
						else {
							$anomalies[] = $this->_lang->GET('maintenance.riskupdate.anomalies.probability_mismatch', [':probability' => $importrisk[$column]]);
							$newrisk[':' . $column] = count($this->_lang->_DEFAULT['risk']['probabilities']);
						}
					}
					foreach(['damage', 'measure_damage'] as $column){
						if (!isset($importrisk[$column]) || !$importrisk[$column]) continue;
						if (($key = array_search($importrisk[$column], $this->_lang->_DEFAULT['risk']['damages'])) !== false) $newrisk[':' . $column] = $key + 1;
						else {
							$anomalies[] = $this->_lang->GET('maintenance.riskupdate.anomalies.damage_mismatch', [':damage' => $importrisk[$column]]);
							$newrisk[':' . $column] = count($this->_lang->_DEFAULT['risk']['damages']);
						}
					}

					// compare existing risks to consider updating
					foreach($risks as $risk){
						if ($risk['process'] !== $process || $risk['type'] !== $type) continue;

						// if these match, assign existing properties for updating
						if ($risk['cause'] == $newrisk[':cause']
							&& $risk['effect'] == $newrisk[':effect']
							&& $risk['measure'] == $newrisk[':measure']
							&& $risk['risk_benefit'] == $newrisk[':risk_benefit']
						) {
							$newrisk[':id'] = $risk['id'];
							$newrisk[':proof'] = $risk['proof'];
							$new--;
							$update++;
							break;
						}
					}

					foreach($newrisk as $key => $value){
						if (gettype($value) === 'string') $newrisk[$key] = $this->_pdo->quote($value);
						if (gettype($value) === 'NULL') $newrisk[$key] = 'NULL';
					}
					$sqlchunks = SQLQUERY::CHUNKIFY($sqlchunks, strtr(SQLQUERY::PREPARE('risk_post'), $newrisk) . '; ');
				}

				//var_dump($sqlchunks);
				//die();
				foreach ($sqlchunks as $chunk){
					try {
						SQLQUERY::EXECUTE($this->_pdo, $chunk);
					}
					catch (\Exception $e) {
						$anomalies[] = UTILITY::json_encode([$e, $chunk], JSON_PRETTY_PRINT);
					}
				}
				$this->response(
					[
						'response' => [
							'msg' => $this->_lang->GET('maintenance.riskupdate.response', [':new' => $new, ':update' => $update]) . ($anomalies ? implode(', ', $anomalies) : ''),
							'type' => 'info'
						]
					]
				);

				break;
			case 'GET':
				$processes = array_values(array_unique(array_column($risks, 'process')));
				sort($processes);

				// upload form
				$response['render']['content'][] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('maintenance.navigation.riskupdate'),
					],
					'content' => $this->_lang->GET('maintenance.riskupdate.description'),
				];
				$response['render']['content'][] = [
					'type' => 'text',
					'attributes' => [
						'required'=> true,
						'name' => $this->_lang->GET('risk.process'),
					],
					'datalist' => $processes
				];
				$response['render']['content'][] = [
					'type' => 'radio',
					'attributes' => [
						'required'=> true,
						'name' => $this->_lang->GET('maintenance.riskupdate.type'),
					],
					'content' => [
						$this->_lang->GET('risk.type.risk') => [],
						$this->_lang->GET('risk.type.characteristic') => [],
					]
				];
				$response['render']['content'][] = [
					'type' => 'file',
					'attributes' => [
						'required'=> true,
						'name' => $this->_lang->GET('maintenance.riskupdate.file'),
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
	 * update vendor productlist filters and information with an uploaded template file
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
								$this->_lang->GET('maintenance.vendorupdate.update_productlist') => [],
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
					if (in_array($this->_lang->GET('maintenance.vendorupdate.update_productlist'), $selected)) {
						$vendor['products'] = json_decode($vendor['products'] ? : '', true);

						$newproductlistfilter = (isset($entry['products']) && gettype($entry['products']) === 'array' && isset($entry['products']['filefilter'])) ? UTILITY::json_encode($entry['products']['filefilter'], JSON_PRETTY_PRINT) : null;
						$vendor['products']['filefilter'] = $newproductlistfilter ? : ($vendor['products']['filefilter'] ?? null);

						$newerpfilter = (isset($entry['products']) && gettype($entry['products']) === 'array' && isset($entry['products']['erpfilter'])) ? UTILITY::json_encode($entry['products']['erpfilter'], JSON_PRETTY_PRINT) : null;
						$vendor['products']['erpfilter'] = $newerpfilter ? : ($vendor['products']['erpfilter'] ?? null);

						if(isset($entry['products']['samplecheck_interval']) && $entry['products']['samplecheck_interval']) $vendor['products']['samplecheck_interval'] = $entry['products']['samplecheck_interval'];
						if(isset($entry['products']['samplecheck_reusable']) && $entry['products']['samplecheck_reusable']) $vendor['products']['samplecheck_reusable'] = $entry['products']['samplecheck_reusable'];

						$vendor['products'] = UTILITY::json_encode($vendor['products'], JSON_PRETTY_PRINT);
					}

					$sqlchunks = SQLQUERY::CHUNKIFY($sqlchunks, strtr(SQLQUERY::PREPARE('consumables_post_vendor'),
						[
							':id' => $vendor['id'],
							':name' => $this->_pdo->quote($vendor['name']),
							':evaluation' => $vendor['evaluation'] ? $this->_pdo->quote($vendor['evaluation']) : 'NULL',
							':hidden' => $vendor['hidden'] ? $this->_pdo->quote($vendor['hidden']) : 'NULL',
							':info' => $vendor['info'] ? $this->_pdo->quote($vendor['info']) : 'NULL',
							':products' => $vendor['products'] ? $this->_pdo->quote($vendor['products']) : 'NULL',
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
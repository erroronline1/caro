<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2024 error on line 1 (dev@erroronline.one)
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

// add filters and filter csv-files
require_once('./_csvprocessor.php');
require_once("../libraries/xlsxwriter.class.php");

class CSVFILTER extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
    private $_requestedID = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedID = $this->_requestedID = isset(REQUEST[2]) ? REQUEST[2] : null;
	}

	/**
	 *   ___ _ _ _
	 *  |  _|_| | |_ ___ ___
	 *  |  _| | |  _| -_|  _|
	 *  |_| |_|_|_| |___|_|
	 *
	 * get responds with form to select and apply filter
	 * post responds with a download link to the result file after processing
	 */
	public function filter(){
		if (!PERMISSION::permissionFor('csvfilter')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$filter = SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_get_filter', [
					'values' => [
						':id' => intval($this->_requestedID)
						]
				]);
				$filter = $filter ? $filter[0] : null;

				if (!$filter) $this->response([
					'response' => [
						'name' => false,
						'msg' => $this->_lang->GET('csvfilter.edit.error_filter_not_found'),
						'type' => 'error'
					]]);
				$content = json_decode($filter['content'], true);

				$inputfile = isset($_FILES[$this->_lang->PROPERTY('csvfilter.use.filter_input_file')]) ? $_FILES[$this->_lang->PROPERTY('csvfilter.use.filter_input_file')]['tmp_name'] : null;
				if (!$inputfile) $this->response([
					'response' => [
						'name' => false,
						'msg' => $this->_lang->GET('csvfilter.use.filter_no_input_file'),
						'type' => 'error'
					]]);
				$content['filesetting']['source'] = $inputfile;
				if (!isset($content['filesetting']['dialect'])) $content['filesetting']['dialect'] = CONFIG['csv']['dialect'];
				$content['filesetting']['encoding'] = CONFIG['likeliness']['csvprocessor_source_encoding'];

				$comparefileindex = 0;
				foreach($content['filter'] as &$filtertype){
					if ($filtertype['apply'] === 'filter_by_comparison_file' && $filtertype['filesetting']['source'] !== 'SELF') {
						$comparefile = isset($_FILES[$this->_lang->PROPERTY('csvfilter.use.filter_compare_file')]) && isset($_FILES[$this->_lang->PROPERTY('csvfilter.use.filter_compare_file')]['tmp_name'][$comparefileindex]) ? $_FILES[$this->_lang->PROPERTY('csvfilter.use.filter_compare_file')]['tmp_name'][$comparefileindex] : null;
						if (!$comparefile) $this->response([
							'response' => [
								'name' => false,
								'msg' => $this->_lang->GET('csvfilter.use.filter_no_compare_file', [':name' => $filtertype['filesetting']['source']]),
								'type' => 'error'
							]]);
						$filtertype['filesetting']['source'] = $comparefile;
						$comparefileindex++;
					}
				}
				
				$datalist = new Listprocessor($content, [
					'processedMonth' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('csvfilter.use.filter_month')),
					'processedYear' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('csvfilter.use.filter_year'))
				]);

				// clear up tmp folder
				UTILITY::tidydir('tmp', CONFIG['lifespan']['tmp']);

				//create and write to file
				$downloadfiles=[];
				switch (strtolower(pathinfo($content['filesetting']['destination'])['extension'])){
					case 'csv':
						foreach($datalist->_list as $subsetname => $subset){
							if (intval($subsetname)) $subsetname = pathinfo($content['filesetting']['destination'])['filename'];
							$subsetname = preg_replace(CONFIG['forbidden']['names'][0], '_', $subsetname);
							$tempFile = UTILITY::directory('tmp') . '/' . time() . $subsetname . '.csv';
							$file = fopen($tempFile, 'w');
							fwrite($file, b"\xEF\xBB\xBF"); // tell excel this is utf8
							fputcsv($file, $datalist->_setting['filesetting']['columns'],
								$datalist->_setting['filesetting']['dialect']['separator'],
								$datalist->_setting['filesetting']['dialect']['enclosure'],
								$datalist->_setting['filesetting']['dialect']['escape']);
							foreach($subset as $line) {
								fputcsv($file, $line,
								$datalist->_setting['filesetting']['dialect']['separator'],
								$datalist->_setting['filesetting']['dialect']['enclosure'],
								$datalist->_setting['filesetting']['dialect']['escape']);
							}
							fclose($file);
							$downloadfiles[$this->_lang->GET('csvfilter.use.filter_download', [':file' => preg_replace('/.csv$/', (count($datalist->_list) > 1 ? '_' . $subsetname. '.csv' : '.csv'), $content['filesetting']['destination'])])] = [
								'href' => substr(UTILITY::directory('tmp'), 1) . '/' . pathinfo($tempFile)['basename'],
								'download' => preg_replace('/.csv$/', (count($datalist->_list) > 1 ? '_' . $subsetname. '.csv' : '.csv'), $content['filesetting']['destination'])
							];
						}
						break;
					case 'xls': // do nothing, let xlsx catch
					case 'xlsx':
						$tempFile = UTILITY::directory('tmp') . '/' . time() . '.xlsx';
						$writer = new XLSXWriter();
						$writer->setAuthor($_SESSION['user']['name']); 
						foreach($datalist->_list as $subsetname => $subset){
							if (intval($subsetname)) $subsetname = pathinfo($content['filesetting']['destination'])['filename'];
							$writer->writeSheetRow($subsetname, $datalist->_setting['filesetting']['columns']);
							foreach ($subset as $line)
								$writer->writeSheetRow($subsetname, $line);
						}
						$writer->writeToFile($tempFile);
						$content['filesetting']['destination'] = preg_replace('/.xls$/', '.xlsx', $content['filesetting']['destination']);
						$downloadfiles[$this->_lang->GET('csvfilter.use.filter_download', [':file' => $content['filesetting']['destination']])] = [
							'href' => substr(UTILITY::directory('tmp'), 1) . '/' . pathinfo($tempFile)['basename'],
							'download' => $content['filesetting']['destination']
						];
						break;
				}
				
				$this->response([
					'log' => $datalist->_log,
					'links' => $downloadfiles
				]);
				break;
			case 'GET':
				$filterdatalist = [];
				$options = ['...' . $this->_lang->GET('csvfilter.use.filter_select') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$return = [];

				// get selected filter
				if (intval($this->_requestedID)){
					$filter = SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_get_filter', [
						'values' => [
							':id' => $this->_requestedID
						]
					]);
				} else {
					$filter = SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_get_latest_by_name', [
						'values' => [
							':name' => $this->_requestedID
						]
					]);
				}
				$filter = $filter ? $filter[0] : null;
				if (!$filter) $filter = [
					'id' => '',
					'name' => '',
					'content' => ''
				];
				if($this->_requestedID && $this->_requestedID !== 'false' && !$filter['name'] && $this->_requestedID !== '0') $return['response'] = ['msg' => $this->_lang->GET('csvfilter.edit.error_filter_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing templates lists
				$filters = SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_datalist');
				$hidden = [];
				foreach($filters as $key => $row) {
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (!isset($options[$row['name']]) && !in_array($row['name'], $hidden)) {
						$filterdatalist[] = $row['name'];
						$options[$row['name']] = ($row['name'] == $filter['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
					}
				}
				ksort($options);

				$return['render'] = [
					'content' => [
						[
							[
								'type' => 'datalist',
								'content' => array_values(array_unique($filterdatalist)),
								'attributes' => [
									'id' => 'filters'
								]
							], [
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('csvfilter.use.filter_select'),
									'onchange' => "api.csvfilter('get', 'filter', this.value)"
								],
								'content' => $options
							], [
								'type' => 'search',
								'attributes' => [
									'name' => $this->_lang->GET('csvfilter.edit.filter'),
									'list' => 'filters',
									'onkeypress' => "if (event.key === 'Enter') {api.csvfilter('get', 'filter', this.value); return false;}"
								]
							]
						]
					]
				];
				if ($filter['id']){
					$content = json_decode($filter['content'], true);
					$additionalform = [
						[
							'type' => 'file',
							'hint' => $this->_lang->GET('csvfilter.use.filter_input_file_hint', [':name' => $content['filesetting']['source']]),
							'attributes' => [
								'name' => $this->_lang->GET('csvfilter.use.filter_input_file'),
								'required' => true,
								'accept' => '.csv'
							]
						], [
							'type' => 'br'
						], [
							'type' => 'number',
							'attributes' => [
								'name' => $this->_lang->GET('csvfilter.use.filter_month'),
								'value' => $this->_currentdate->format('m'),
								'readonly' => true
							]
						], [
							'type' => 'number',
							'attributes' => [
								'name' => $this->_lang->GET('csvfilter.use.filter_year'),
								'value' => $this->_currentdate->format('Y'),
								'readonly' => true
							]
						]
					];
					foreach($content['filter'] as $filtertype){
						if ($filtertype['apply'] === 'filter_by_comparison_file' && $filtertype['filesetting']['source'] !== 'SELF') array_push($additionalform, [
							'type' => 'file',
							'hint' => $this->_lang->GET('csvfilter.use.filter_input_file_hint', [':name' => $filtertype['filesetting']['source']]),
							'attributes' => [
								'name' => $this->_lang->GET('csvfilter.use.filter_compare_file') . '[]',
								'required' => true,
								'accept' => '.csv'
							]
						]);
					}
					array_push($return['render']['content'], $additionalform);
					$return['render']['form'] = [
						'data-usecase' => 'csvfilter',
						'action' => "javascript:api.csvfilter('post', 'filter', " . $filter['id'] . ")"
					];
				}

				$this->response($return);
				break;
		}
	}
	
	/**
	 *           _
	 *   ___ _ _| |___
	 *  |  _| | | | -_|
	 *  |_| |___|_|___|
	 *
	 * gets and posts filter rules
	 * no putting though for audit safety
	 */
	public function rule(){
		if (!PERMISSION::permissionFor('csvrules')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$filter = [
					':name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('csvfilter.edit.filter_name')),
					':author' => $_SESSION['user']['name'],
					':content' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('csvfilter.edit.filter_content')),
					':hidden' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('csvfilter.edit.filter_hidden')) === $this->_lang->PROPERTY('csvfilter.edit.filter_hidden_hidden')? 1 : 0,
				];

				if (!trim($filter[':name']) || !trim($filter[':content'])) $this->response([], 400);

				// ensure valid json for filters
				if ($filter[':content'] && !json_decode($filter[':content'], true))  $this->response(['response' => ['msg' => $this->_lang->GET('csvfilter.edit.filter_content_hint'), 'type' => 'error']]);

				// put hidden attribute if anything else remains the same
				$exists = SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_get_latest_by_name', [
					'values' => [
						':name' => $filter[':name']
					]
				]);
				$exists = $exists ? $exists[0] : null;
				if ($exists && $exists['content'] === $filter[':content']) {
					if (SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_put', [
						'values' => [
							':hidden' => $filter[':hidden'],
							':id' => $exists['id']
						]
					])) $this->response([
						'response' => [
							'name' => $filter[':name'],
							'msg' => $this->_lang->GET('csvfilter.edit.filter_saved', [':name' => $filter[':name']]),
							'type' => 'success'
						]]);	
				}

				foreach(CONFIG['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $filter[':name'], $matches)) $this->response(['response' => ['msg' => $this->_lang->GET('csvfilter.edit.error_forbidden_name', [':name' => $filter[':name']]), 'type' => 'error']]);
				}

				if (SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_post', [
					'values' => $filter
				])) $this->response([
						'response' => [
							'name' => $filter[':name'],
							'msg' => $this->_lang->GET('csvfilter.edit.filter_saved', [':name' => $filter[':name']]),
							'type' => 'success'
						]]);
				else $this->response([
					'response' => [
						'name' => false,
						'msg' => $this->_lang->GET('csvfilter.edit.filter_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$filterdatalist = [];
				$options = ['...' . $this->_lang->GET('csvfilter.edit.filter_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$alloptions = ['...' . $this->_lang->GET('csvfilter.edit.filter_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$return = [];

				// get selected filter
				if (intval($this->_requestedID)){
					$filter = SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_get_filter', [
						'values' => [
							':id' => $this->_requestedID
						]
					]);
				} else {
					$filter = SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_get_latest_by_name', [
						'values' => [
							':name' => $this->_requestedID
						]
					]);
				}
				$filter = $filter ? $filter[0] : null;
				if (!$filter) $filter = [
					'id' => '',
					'name' => '',
					'content' => ''
				];
				if($this->_requestedID && $this->_requestedID !== 'false' && !$filter['name'] && $this->_requestedID !== '0') $return['response'] = ['msg' => $this->_lang->GET('csvfilter.edit.error_filter_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing filter lists
				$filters = SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_datalist');
				$hidden = [];
				$dependedtemplates = [];
				foreach($filters as $key => $row) {
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (!isset($options[$row['name']]) && !in_array($row['name'], $hidden)) {
						$filterdatalist[] = $row['name'];
						$options[$row['name'] ] = ($row['name'] == $filter['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
					}
					$alloptions[$row['name']. ' ' . $this->_lang->GET('assemble.compose.component.component_author', [':author' => $row['author'], ':date' => $row['date']])] = ($row['name'] == $filter['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
				}
				ksort($options);
				ksort($alloptions);
				$return['render'] = [
					'form' => [
						'data-usecase' => 'csvfilter',
						'action' => "javascript:api.csvfilter('post', 'rule')"],
					'content' => [
						[
							[
								[
									'type' => 'datalist',
									'content' => array_values(array_unique($filterdatalist)),
									'attributes' => [
										'id' => 'filters'
									]
								], [
									'type' => 'select',
									'attributes' => [
										'name' => $this->_lang->GET('csvfilter.edit.filter_select'),
										'onchange' => "api.csvfilter('get', 'rule', this.value)"
									],
									'content' => $options
								], [
									'type' => 'search',
									'attributes' => [
										'name' => $this->_lang->GET('csvfilter.edit.filter'),
										'list' => 'filters',
										'onkeypress' => "if (event.key === 'Enter') {api.csvfilter('get', 'rule', this.value); return false;}"
									]
								]
							], [
								[
									'type' => 'select',
									'attributes' => [
										'name' => $this->_lang->GET('csvfilter.edit.filter_all'),
										'onchange' => "api.csvfilter('get', 'rule', this.value)"
									],
									'content' => $alloptions
								]
							]
						], [
							[
								'type' => 'text',
								'attributes' => [
									'name' => $this->_lang->GET('csvfilter.edit.filter_name'),
									'value' => $filter['name'],
									'required' => true,
									'data-loss' => 'prevent'
								]
							], [
								'type' => 'code',
								'hint' => $this->_lang->GET('csvfilter.edit.filter_content_hint'),
								'attributes' => [
									'name' => $this->_lang->GET('csvfilter.edit.filter_content'),
									'value' => $filter['content'],
									'rows' => 16,
									'id' => 'content',
									'required' => true,
									'data-loss' => 'prevent'
								]
							]
						]
					]
				];
				if ($filter['id']){
					$hidden = [
						'type' => 'radio',
						'attributes' => [
							'name' => $this->_lang->GET('csvfilter.edit.filter_hidden')
						],
						'content' => [
							$this->_lang->GET('csvfilter.edit.filter_hidden_visible') => ['checked' => true],
							$this->_lang->GET('csvfilter.edit.filter_hidden_hidden') => []
						],
						'hint' => $this->_lang->GET('csvfilter.edit.filter_hidden_hint')
					];
					if ($filter['hidden']) $hidden['content'][$this->_lang->GET('csvfilter.edit.filter_hidden_hidden')]['checked'] = true;
					array_push($return['render']['content'][1], $hidden);
				}
				$this->response($return);
				break;
		}					
	}
}
?>
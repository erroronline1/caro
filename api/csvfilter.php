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

// add filters and filter csv-files
// handle erp-interface methods typically involving csv files
require_once('./_csvprocessor.php');
require_once("../libraries/XLSXWriter.php");
require_once("../libraries/XLSXWriter_BuffererWriter.php");

class CSVFILTER extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
    private $_requestedID = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user']) || array_intersect(['patient'], $_SESSION['user']['permissions'])) $this->response([], 401);

		$this->_requestedID = isset(REQUEST[2]) ? REQUEST[2] : null;
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

				// check if requested filter is found
				if (!$filter) $this->response([
					'response' => [
						'name' => false,
						'msg' => $this->_lang->GET('csvfilter.edit.error_filter_not_found'),
						'type' => 'error'
					]]);
				$content = json_decode($filter['content'], true);

				// check if input file is provided
				$inputfile = isset($_FILES[$this->_lang->PROPERTY('csvfilter.use.filter_input_file')]) ? $_FILES[$this->_lang->PROPERTY('csvfilter.use.filter_input_file')]['tmp_name'][0] : null;
				if (!$inputfile) $this->response([
					'response' => [
						'name' => false,
						'msg' => $this->_lang->GET('csvfilter.use.filter_no_input_file'),
						'type' => 'error'
					]]);
				
				// set up file settings for csvprocessor
				$content['filesetting']['source'] = $inputfile;
				if (!isset($content['filesetting']['dialect'])) $content['filesetting']['dialect'] = CONFIG['csv']['dialect'];
				$content['filesetting']['encoding'] = CONFIG['csv']['csvprocessor_source_encoding'];

				// check if neccessary compare file is provided 
				$comparefileindex = 0;
				if (isset($content['filter'])){
					foreach ($content['filter'] as &$filtertype){
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
				}
					
				// process filter
				$datalist = new Listprocessor($content, [
					'processedMonth' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('csvfilter.use.filter_month')),
					'processedYear' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('csvfilter.use.filter_year'))
				]);

				//create and write to file
				$downloadfiles = [];
				switch (strtolower(pathinfo($content['filesetting']['destination'])['extension'])){
					case 'csv':
						if ($files = UTILITY::csv($datalist->_list, $datalist->_setting['filesetting']['columns'],
							$content['filesetting']['destination'], [
							'separator' => isset($datalist->_setting['filesetting']['separator']) ? $datalist->_setting['filesetting']['separator'] : CONFIG['csv']['dialect']['separator'],
							'enclosure' => isset($datalist->_setting['filesetting']['enclosure']) ? $datalist->_setting['filesetting']['enclosure'] : CONFIG['csv']['dialect']['enclosure'],
							'escape' => isset($datalist->_setting['filesetting']['escape']) ? $datalist->_setting['filesetting']['escape'] : CONFIG['csv']['dialect']['escape']])){
							foreach($files as $file){
								if ($file) $downloadfiles[$this->_lang->GET('csvfilter.use.filter_download', [':file' => pathinfo($file)['basename']])] = [
									'href' => './api/api.php/file/stream/' . substr($file, 1),
									'download' => pathinfo($file)['basename']
								];
							}
						}
						break;
					case 'xls': // do nothing, let xlsx catch
					case 'xlsx':
						$downloadfiles = [];
						$content['filesetting']['destination'] = preg_replace('/.xls$/', '.xlsx', $content['filesetting']['destination']);
						// some reasonable defaults
						$format = [
							'file' => [
								'author' => $_SESSION['user']['name']
							],
							'header' => [ // according to xslxwriter implementation
								'font-size' => 8,
							],
							'row' => [ // according to xslxwriter implementation
								'wrap_text' => true,
								'font-size' => 8,
								'halign' => 'left',
								'valign' => 'top'
							]
						];
						if (isset($content['xslxformat'])) $format = array_merge($format, $content['xslxformat']);

						if ($files = UTILITY::xlsx($datalist->_list, [], $content['filesetting']['destination'], $format)){
							foreach($files as $file){
								if ($file) $downloadfiles[$this->_lang->GET('csvfilter.use.filter_download', [':file' => $content['filesetting']['destination']])] = [
									'href' => './api/api.php/file/stream/' . substr($file, 1),
									'download' => pathinfo($file)['basename']
								];
							}
						}
						break;
				}
				
				$this->response([
					'log' => array_map(fn($v) => mb_convert_encoding($v, 'UTF-8', mb_detect_encoding($v, ['ASCII', 'UTF-8', 'ISO-8859-1'])), $datalist->_log),
					'links' => $downloadfiles
				]);
				break;
			case 'GET':
				$filterdatalist = [];
				$options = ['...' . $this->_lang->GET('csvfilter.use.filter_select') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$response = [];

				// get selected filter by int id or string name
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

				// set up filter properties
				if (!$filter) $filter = [
					'id' => '',
					'name' => '',
					'content' => ''
				];
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$filter['name'] && $this->_requestedID !== '0') $response['response'] = ['msg' => $this->_lang->GET('csvfilter.edit.error_filter_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing filter lists
				$filters = SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_datalist');
				$hidden = [];
				foreach ($filters as $row) {
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (!isset($options[$row['name']]) && !in_array($row['name'], $hidden)) {
						$filterdatalist[] = $row['name'];
						$options[$row['name']] = ($row['name'] == $filter['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
					}
				}
				ksort($options);

				// append filter selection
				$response['render'] = [
					'content' => [
						[
							[
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
									'onkeydown' => "if (event.key === 'Enter') {api.csvfilter('get', 'filter', this.value); return false;}"
								],
								'datalist' => array_values(array_unique($filterdatalist))
							]
						]
					]
				];

				if ($filter['id']){
					$content = json_decode($filter['content'], true);

					// add default inputs for filter
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
								'value' => $this->_date['usertime']->format('m'),
								'readonly' => true
							]
						], [
							'type' => 'number',
							'attributes' => [
								'name' => $this->_lang->GET('csvfilter.use.filter_year'),
								'value' => $this->_date['usertime']->format('Y'),
								'readonly' => true
							]
						]
					];

					// add inputs for comparison files if applicable
					if (isset($content['filter'])){
						foreach ($content['filter'] as $filtertype){
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
					}

					// append all filter inputs
					array_push($response['render']['content'], $additionalform);

					// append form
					$response['render']['form'] = [
						'data-usecase' => 'csvfilter',
						'action' => "javascript:api.csvfilter('post', 'filter', " . $filter['id'] . ")"
					];
				}

				if (PERMISSION::permissionFor('csvrules')){
					$response['render']['content'][] = [
						[
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('csvfilter.navigation.filter_manager'),
								'onclick' => "api.csvfilter('get', 'rule'" . ($filter['id'] ? ", " . $filter['id'] : "") . ")"
							]
						]
					];
				}
				
				$this->response($response);
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
				// set up filter properties by payload
				$filter = [
					':name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('csvfilter.edit.filter_name')),
					':author' => $_SESSION['user']['name'],
					':content' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('csvfilter.edit.filter_content')),
					':hidden' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('csvfilter.edit.filter_availability')) === $this->_lang->PROPERTY('csvfilter.edit.filter_hidden')? UTILITY::json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_date['servertime']->format('Y-m-d H:i:s')]) : null,
				];

				// early exit
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

				// check forbidden names
				if (UTILITY::forbiddenName($filter[':name'])) $this->response(['response' => ['msg' => $this->_lang->GET('csvfilter.edit.error_forbidden_name', [':name' => $filter[':name']]), 'type' => 'error']]);

				// post filter
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
				$response = [];

				// get selected filter by int id or string name
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

				// set up filter properties
				if (!$filter) $filter = [
					'id' => '',
					'name' => '',
					'content' => ''
				];
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$filter['name'] && $this->_requestedID !== '0') $response['response'] = ['msg' => $this->_lang->GET('csvfilter.edit.error_filter_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing filter lists
				$filters = SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_datalist');
				$hidden = [];
				foreach ($filters as $row) {
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (!isset($options[$row['name']]) && !in_array($row['name'], $hidden)) {
						$filterdatalist[] = $row['name'];
						$options[$row['name']] = ($row['name'] == $filter['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
					}
					
					$display = $row['name']. ' ' . $this->_lang->GET('assemble.compose.component.author', [':author' => $row['author'], ':date' => $this->convertFromServerTime($row['date'])]);
					if ($row['hidden']) $display = UTILITY::hiddenOption($display);
					$alloptions[$display] = ($row['name'] == $filter['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
				}

				// append form, filter selection and inputs for adding filter 
				$response['render'] = [
					'form' => [
						'data-usecase' => 'csvfilter',
						'action' => "javascript:api.csvfilter('post', 'rule')"],
					'content' => [
						[
							[
								[
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
										'onkeydown' => "if (event.key === 'Enter') {api.csvfilter('get', 'rule', this.value); return false;}"
									],
									'datalist' => array_values(array_unique($filterdatalist))
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

				// append filter toggles
				if ($filter['id']){
					$hidden = [
						'type' => 'radio',
						'attributes' => [
							'name' => $this->_lang->GET('csvfilter.edit.filter_availability')
						],
						'content' => [
							$this->_lang->GET('csvfilter.edit.filter_available') => ['checked' => true],
							$this->_lang->GET('csvfilter.edit.filter_hidden') => ['class' => 'red']
						],
						'hint' => $this->_lang->GET('csvfilter.edit.filter_availability_hint')
					];
					if ($filter['hidden']) {
						$hidden['content'][$this->_lang->GET('csvfilter.edit.filter_hidden')]['checked'] = true;
						$hiddenproperties = json_decode($filter['hidden'], true);
						$hidden['hint'] .= ' ' . $this->_lang->GET('csvfilter.edit.edit_hidden_set', [':date' => $this->convertFromServerTime($hiddenproperties['date']), ':name' => $hiddenproperties['name']]);
					}
					array_push($response['render']['content'][1], $hidden);
				}
				$this->response($response);
				break;
		}					
	}
}
?>
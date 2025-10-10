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

// access patient and case data
// useable as separate module for easy access with erpquery method as entry point
// other methods can and should be reusable from within the application where implemented
class ERPQUERY extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedType = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user']) || array_intersect(['patient'], $_SESSION['user']['permissions'])) $this->response([], 401);
		if (!(ERPINTERFACE && ERPINTERFACE->_instatiated)) $this->response([], 405);

		$this->_requestedType = isset(REQUEST[2]) ? REQUEST[2] : null;
	}

	/**
	 *                                   
	 *   ___ ___ ___ ___ _ _ ___ ___ _ _ 
	 *  | -_|  _| . | . | | | -_|  _| | |
	 *  |___|_| |  _|_  |___|___|_| |_  |
	 *          |_|   |_|           |___|
	 * main entry point for available requests
	 * displays a selection of available options
	 * calls $this->_requestedType method if set
	 */
	public function erpquery(){
		$response['render'] = ['content' => []];
		$selecttypes = ['...' => []];
		
		$categories = [];
		if (method_exists(ERPINTERFACE, 'customerdata') && ERPINTERFACE->customerdata()) $categories[] = 'patientlookup';
		if (PERMISSION::permissionFor('erpcasedata') && method_exists(ERPINTERFACE, 'casepositions') && ERPINTERFACE->casepositions()) $categories[] = 'casedata';
		if (PERMISSION::permissionFor('erpcasedata') && method_exists(ERPINTERFACE, 'customerdata') && ERPINTERFACE->customerdata() && method_exists(ERPINTERFACE, 'customercases') && ERPINTERFACE->customercases()) $categories[] = 'caselist';

		foreach ($categories as $category){
				$selecttypes[$this->_lang->GET('erpquery.navigation.' . $category)] = ['value' => $category];
				if ($this->_requestedType === $category) $selecttypes[$this->_lang->GET('erpquery.navigation.' . $category)]['selected'] = true;
		}
		ksort($selecttypes);
		$response['render']['content'][] = [
			[
				'type' => 'select',
				'content' => $selecttypes,
				'attributes' => [
					'name' => $this->_lang->GET('audit.checks_select_type'),
					'onchange' => "if (this.value !== '...') api.erpquery('get', 'erpquery', this.value)"
				]
			]
		];

		if ($this->_requestedType && $this->_requestedType !== '...') {
			if ($append = $this->{$this->_requestedType}()) {
				array_push($response['render']['content'] , ...$append);
				$response['render']['form'] = [
					'data-usecase' => 'erpquery',
					'action' => "javascript:api.erpquery('post', 'erpquery', '" . $this->_requestedType . "')"
				];
			}
		}
		$this->response($response);
	}

	/**
	 *                     _     _       
	 *   ___ ___ ___ ___ _| |___| |_ ___ 
	 *  |  _| .'|_ -| -_| . | .'|  _| .'|
	 *  |___|__,|___|___|___|__,|_| |__,|
	 *
	 */
	public function casedata(){
		if (!PERMISSION::permissionFor('erpcasedata')) $this->response([], 401);
		$content = $fields = [];
		$fields = [
			'type' => 'text',
			'attributes' => [
				'name' => $this->_lang->GET('erpquery.casedata.case_id'),
				'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('erpquery.casedata.case_id')) ? : ''
			]
		];
		$content[] = $fields;

		if ($_SERVER['REQUEST_METHOD'] === 'POST'){
			$casenumbers = preg_split('/[\s;,]+/', UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('erpquery.casedata.case_id')) ? : '');
			$states = ERPINTERFACE->casestate($casenumbers);
			$positions = ERPINTERFACE->casepositions($casenumbers);
			$files = ERPINTERFACE->media($casenumbers);
			
			foreach($casenumbers as $case){
				$casecontent = [];
				if ($states && isset($states[$case])){
					$options = [];
					foreach($this->_lang->_USER['casestate']['casedocumentation'] as $state => $translation){
						$options[$translation] = ['disabled' => true];
						if (isset($states[$case][$state])) $options[$translation]['checked'] = true;
					}
					$casecontent[] = [
						'type' => 'checkbox',
						'attributes' => [
							'name' => $this->_lang->GET('record.pseudodocument_casedocumentation')
						],
						'content' => $options
					];
				}
				if ($positions && isset($positions[$case])){
					$tablerows = [];
					$tablerows[] = array_map(fn($v) => ['c' => $this->_lang->GET('erpquery.casedata.casepositions.' . $v)], ['amount', 'contract_position', 'text']);
					foreach($positions[$case] as $position){
						unset($position['header_data']);
						$tablerows[] = array_map(fn($v) => ['c' => $v], array_values($position));
					}
					$casecontent[] = [
						'type' => 'table',
						'attributes' => [
							'name' => strval($case) . ' ' . $positions[$case][0]['header_data']
						],
						'content' => $tablerows
					];
				}
				if ($files && isset($files[$case])){
					$attachments = [];
					foreach($files[$case] as $attachment){
						$attachments[$attachment['description'] . ' ' . $attachment['date']] = ['href' => $attachment['url'], 'download' => $attachment['filename']];
					}

					$casecontent[] = [
						'type' => 'textsection',
						'attributes' => [
							'class' => 'orange',
							'name' => $this->_lang->GET('erpquery.download')
						]
					];
					$casecontent[] = [
						'type' => 'links',
						'content' => $attachments
					];
				}
				if ($casecontent) $content[] = $casecontent;
			}
			if (count($content) < 2){
				$content[] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('erpquery.null')
					]
				];
			}
		}
		return $content;
	}

	/**
	 *                   _ _     _
	 *   ___ ___ ___ ___| |_|___| |_
	 *  |  _| .'|_ -| -_| | |_ -|  _|
	 *  |___|__,|___|___|_|_|___|_|
	 *
	 */
	public function caselist(){
		if (!PERMISSION::permissionFor('erpcasedata')) $this->response([], 401);
		$content = $fields = [];
		foreach(ERPINTERFACE->customerdata() as $field){
			$fields[] = [
				'type' => $field['type'],
				'attributes' => [
					'name' => $field['name'],
					'value' => UTILITY::propertySet($this->_payload, $field['name']) ? : ''
				]
			];
		}
		$content[] = $fields;

		if ($_SERVER['REQUEST_METHOD'] === 'POST'){
			$fields = [];
			if ($result = ERPINTERFACE->customercases((array)$this->_payload)) {
				foreach($result as $patient => $cases){
					if (!$cases) continue;
					$links = [];
					foreach($cases as $case){
						$description = $case['caseid'] . ' - ' . $case['text'];
						if ($case['reimbursement']) $description .= ' - ' . $this->_lang->GET('casestate.casedocumentation.reimbursement') . ' ' . $case['reimbursement'];
						if ($case['settled']) $description .= ' - ' . $this->_lang->GET('casestate.casedocumentation.settled') . ' ' . $case['settled'];
						$links[$description] = ['href' => 'javascript:void(0)', 'onclick' => "const formdata = new FormData(); formdata.append('" . $this->_lang->PROPERTY('erpquery.casedata.case_id') . "', " . $case['caseid'] . "); api.record('post', 'erpcasepositions', null, formdata);"];
					}
					$content[] = [
						'type' => 'links',
						'description' => $patient,
						'content' => $links
					];
				}
			}
			if (count($content) < 2){
				$content[] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('erpquery.null')
					]
				];
			}
		}

		return $content;
	}

	/**
	 *           _   _         _   _         _           
	 *   ___ ___| |_|_|___ ___| |_| |___ ___| |_ _ _ ___ 
	 *  | . | .'|  _| | -_|   |  _| | . | . | '_| | | . |
	 *  |  _|__,|_| |_|___|_|_|_| |_|___|___|_,_|___|  _|
	 *  |_|                                         |_|
	 */
	public function patientlookup($rendertype = 'textsection'){
		$content = $fields = [];
		foreach(ERPINTERFACE->customerdata() as $field){
			$fields[] = [
				'type' => $field['type'],
				'attributes' => [
					'name' => $field['name'],
					'value' => UTILITY::propertySet($this->_payload, $field['name']) ? : ''
				]
			];
		}
		$content[] = $fields;

		if ($_SERVER['REQUEST_METHOD'] === 'POST'){
			if ($result = ERPINTERFACE->customerdata((array)$this->_payload)) {

				switch ($rendertype){
					case 'textsection':
						foreach($result as $option){
							$content[] = [
								[
									'type' => 'textsection',
									'content' => implode('<br>', array_map(fn($k, $v) => $k . ': ' . $v, array_keys($option), array_values($option)))
								]
							];
						}
						break;
					case 'radio': // reused e.g. by record.php
						$options = [];
						foreach($result as $option){
							// construct key: value radio input content
							$options[implode('<br>', array_map(fn($k, $v) => $k . ': ' . $v, array_keys($option), array_values($option)))] = [];
						}
						$content = [
							[
								'type' => 'radio',
								'attributes' => [
									'name' => $this->_lang->GET('erpquery.integrations.data_import')
								],
								'content' => $options
							]
						];
				}
			}
			if (count($content) < 2){
				$content[] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('erpquery.null')
					]
				];
			}
		}

		return $content;
	}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// direct accessible methods
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 *                 _               
	 *   ___ ___ _ _ _| |_ _ _____ ___ 
	 *  |  _|_ -| | | . | | |     | . |
	 *  |___|___|\_/|___|___|_|_|_|  _|
	 *                            |_|
	 * retrieve data dumps from the erp-interface as file
	 * get respondes with available options to select from
	 * post responds with a download link to the result file after processing
	 */
	public function csvdump(){
		if (!PERMISSION::permissionFor('erpimport')) $this->response([], 401);
		if(!(ERPINTERFACE && ERPINTERFACE->_instatiated && method_exists(ERPINTERFACE, 'customcsvdump') && ERPINTERFACE->customcsvdump())) $this->response([], 404);
		$response = [];

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$result = ERPINTERFACE->customcsvdump($this->_requestedType);
				if ($result === null ||  !$result) $this->response([
					'response' => [
						'name' => false,
						'msg' => $this->_lang->GET('erpquery.null'),
						'type' => 'error'
					]]);
				
				$resultinfo = pathinfo($result);
				$downloadfiles[$this->_lang->GET('csvfilter.use.filter_download', [':file' => $resultinfo['basename']])] = [
						'href' => './api/api.php/file/stream/' . substr($result, 1),
						'download' => $resultinfo['basename']
					];				
				$this->response([
					'log' => [],
					'links' => $downloadfiles
				]);

				break;
			case 'GET':
				$options = ['...' . $this->_lang->GET('erpquery.csvdump.select') => ['value' => '0']];
				foreach(ERPINTERFACE->customcsvdump() as $key){
					$options[$key] = ['value' => $key];
				}

				// append filter selection
				$response['render'] = [
					'content' => [
						[
							[
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('erpquery.csvdump.select'),
									'onchange' => "api.erpquery('post', 'csvdump', this.value)"
								],
								'content' => $options,
								'hint' => $this->_lang->GET('erpquery.csvdump.select_hint')
							]
						]
					]
				];
		}
		$this->response($response);
	}

	/**
	 *           _           _ 
	 *   _ _ ___| |___ ___ _| |
	 *  | | | . | | . | .'| . |
	 *  |___|  _|_|___|__,|___|
	 *      |_|
	 * retrieve data dumps from the erp-interface as file
	 * get respondes with available options to select from
	 * post responds with a download link to the result file after processing
	 */
	public function upload(){
		if (!PERMISSION::permissionFor('csvfilter')) $this->response([], 401);
		if(!(ERPINTERFACE && ERPINTERFACE->_instatiated && method_exists(ERPINTERFACE, 'upload') && ERPINTERFACE->upload())) $this->response([], 404);
		$response = [];

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$upload = null;
				$rename = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('erpquery.upload.select'));

				if ($rename) $upload = UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('erpquery.upload.file')], UTILITY::directory('erp_documents'), [], [$rename]);
				if ($upload) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('erpquery.upload.success'),
						'type' => 'success'
					]]);

				$this->response([
					'response' => [
						'msg' => $this->_lang->GET('erpquery.upload.failure'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$options = ['...' . $this->_lang->GET('erpquery.upload.select') => ['value' => '0']];
				foreach(ERPINTERFACE->upload() as $set){
					$options[$set['option']] = ['value' => $set['rename']];
				}

				// append filter selection
				$response['render'] = [
					'content' => [
						[
							[
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('erpquery.upload.select'),
									'required' => true
								],
								'content' => $options,
							],
							[
								'type' => 'file',
								'attributes' => [
									'name' => $this->_lang->GET('erpquery.upload.file'),
									'required' => true
								]
							]
						]
					]
				];
				$response['render']['form'] = [
					'data-usecase' => 'erpquery',
					'action' => "javascript:api.erpquery('post', 'upload')"
				];
		}
		$this->response($response);
	}

}
?>
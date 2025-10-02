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
include_once('./_erpinterface.php');

// access patient and case data
// as separate module for easy access
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
	 * main entry point for available requests
	 * displays a selection of available options
	 * calls $this->_requestedType method if set
	 */
	public function erpquery(){
		$response['render'] = ['content' => []];
		$selecttypes = ['...' => []];
		
		$categories = [];
		if (method_exists(ERPINTERFACE, 'customerdata') && ERPINTERFACE->customerdata()) $categories[] = 'patientlookup';
		if (method_exists(ERPINTERFACE, 'casepositions') && ERPINTERFACE->casepositions()) $categories[] = 'casepositions';


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

	private function casepositions(){
		$content = $fields = [];
		$fields = [
			'type' => 'text',
			'attributes' => [
				'name' => $this->_lang->GET('erpquery.casepositions.case_id')
			]
		];
		$content[] = $fields;

		if ($_SERVER['REQUEST_METHOD'] === 'POST'){
			if ($result = ERPINTERFACE->customerdata(preg_split('/[\s;,]+/', UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('erpquery.casepositions.case_id')) ? : ''))) {
				foreach($result as $case => $positions){
					$rows = [];

					$rows[] = array_map(fn($v) => ['c' => $this->_lang->GET('record.erpinterface.casepositions.' . $v)], array_keys($positions[0]));
					foreach($positions as $position){
						$rows[] = array_map(fn($v) => ['c' => $v], array_values($position));
					}

					$content[] = [
						'type' => 'table',
						'attributes' => [
							'name' => strval($case)
						],
						'content' => $rows
					];
				}
			}
		}

		return $content;
	}

	private function patientlookup(){
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
				foreach($result as $option){
					$content[] = [
						[
							'type' => 'textsection',
							'content' => implode('<br>', array_map(fn($k, $v) => $k . ': ' . $v, array_keys($option), array_values($option)))
						]
					];
				}
			}
		}

		return $content;
	}
}
?>
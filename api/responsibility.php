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

// add, edit and delete responsibilities

class RESPONSIBILITY extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_unit = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedID = isset(REQUEST[2]) ? REQUEST[2] : null;
		$this->_unit = isset(REQUEST[3]) ? REQUEST[3] : null;
	}

	/**
	 *                               _ _   _ _ _ _   _
	 *   ___ ___ ___ ___ ___ ___ ___|_| |_|_| |_| |_|_|___ ___
	 *  |  _| -_|_ -| . | . |   |_ -| | . | | | |  _| | -_|_ -|
	 *  |_| |___|___|  _|___|_|_|___|_|___|_|_|_|_| |_|___|___|
	 *              |_|
	 *
	 * edit responsibilities
	 */
	public function responsibilities(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!PERMISSION::permissionFor('responsibilities')) $this->response([], 401);
				break;
			case 'PUT':
				break;
			case 'GET':
				$result = ['render' => ['content' => []]];
				$responsibilities = SQLQUERY::EXECUTE($this->_pdo, 'user_responsibility_get_all');
				$available_units = ['common'];

				foreach($responsibilities as $row){
					if (!PERMISSION::permissionFor('responsibilities') && $row['hidden']) continue;
					array_push($available_units, ...json_decode($row['units'], true));
				}

// see document.php ln 1980

				// append selection of responsibilities per unit
				$organizational_units = [];
				$available_units = array_unique($available_units);
				sort($available_units);
				$organizational_units[$this->_lang->GET('assemble.render.mine')] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'onchange' => "api.responsibility('get', 'responsibilities', document.getElementById('_documentfilter').value || 'null')"];
				if (!$this->_unit) $organizational_units[$this->_lang->GET('assemble.render.mine')]['checked'] = true;
				foreach($available_units as $unit){
					if (!$unit) {
						continue;
					}
					$organizational_units[$this->_lang->_USER['units'][$unit]] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'onchange' => "api.responsibility('get', 'responsibilities', document.getElementById('_documentfilter').value || 'null', '" . $unit . "')"];
					if ($this->_unit === $unit) $organizational_units[$this->_lang->_USER['units'][$unit]]['checked'] = true;
				}

				$result['render']['content'][] = [
					[
						'type' => 'radio',
						'content' => $organizational_units,
						'attributes' => [
							'name' => $this->_lang->GET('order.organizational_unit'),
							'onchange' => "api.responsibility('get', 'responsibilities', this.value)"
						]
					]
				];				
				break;
			case 'DELETE':
				if (!PERMISSION::permissionFor('responsibilities')) $this->response([], 401);
				break;
		}
		$this->response($result);
	}

}
?>
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

// diverse tools
class TOOL extends API {
	/**
	 * in case you want to create a code from any values in any form add 
	 * 'data-usecase' => 'tool_create_code'
	 * to inputs attributes (hidden if applicable)
	 */

	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedType = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedType = isset(REQUEST[2]) ? REQUEST[2] : null;
	}

	/**
	 *           _         _     _
	 *   ___ ___| |___ _ _| |___| |_ ___ ___
	 *  |  _| .'| |  _| | | | .'|  _| . |  _|
	 *  |___|__,|_|___|___|_|__,|_| |___|_|
	 *
	 */
	public function calculator(){
		/**
		 * resin rigid/soft, destination weight -> weight rigid / weight soft
		 * @param str $parts '70/30' \D beside decimal point will be stripped
		 * @param str $weight '730' , replaced by decimal point, other \D will be stripped
		 * @return str '511 / 219'
		 */
		function parts_of_weight($parts = '', $weight = '0'){
			$parts = preg_split('/[^\d\.]+/', $parts);
			$weight = floatval(str_replace(',', '.', $weight));
			$sum = array_sum($parts);
			$destination = [];
			foreach($parts as $part) if ($part) $destination[] = ceil($weight * floatval($part) / $sum);
			return implode(' / ', $destination);
		}

		/**
		 * silicone shore stiffness 20/35/65, destination shore -> parts, percent
		 * @param str $attributes '20,65' \D will be stripped
		 * @param str $target '35' , replaced by decimal point, other \D will be stripped
		 * @return str '1 x 65 (33.33 %) / 2 x 20 (66.67 %)'
		 */
		function parts_of_attribute($attributes = '', $target = '0'){
			$attributes = preg_split('/\D+/', $attributes);
			$target = floatval(str_replace(',', '.', $target));
			$parts = [];
			$failsafe = 0;
			// insert first value to prevent 'infinite' loop
			foreach ($attributes as $attribute) {
				if ($attribute >= $target) {
					$parts[] = $attribute;
				}
			}
			// iterate over values
			do {
				foreach ($attributes as $attribute) {
					$mean = array_sum($parts)/count($parts);
					if (($mean < $target && $attribute > $target) || ($mean > $target && $attribute < $target)) $parts[] = $attribute;
				}
				$failsafe++;
			} while (array_sum($parts)/count($parts) != $target && $failsafe < 200);
			$destination = [];
			foreach (array_count_values($parts) as $part => $occurence) $destination[] = $occurence . " x " . $part . ' (' . round(100 * $occurence/count($parts), 2) .' %)';
			if ($failsafe > 199) return '&#8734;';
			return implode(' / ', $destination);
		}

		/**
		 * circular distance diameter, holes -> distance
		 * @param str $diameter '22.7' , replaced by decimal point, other \D will be stripped
		 * @param str $holes '3' converted to int
		 * @return str '23.77'
		 */
		function circular_distance($diameter = '', $holes = '1'){
			$diameter = floatval(str_replace(',', '.', $diameter));
			$holes = intval($holes);
			if ($holes < 1) return '';
			return strval(round(pi() * $diameter / $holes, 2));
		}

		$types = [
			'pow' => [
				[
					'type' => 'text',
					'attributes' => [
						'name' => LANG::GET('tool.calculator_pow_parts'),
						'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.calculator_pow_parts')) ? : ''
					],
					'hint' => LANG::GET('tool.calculator_pow_hint')
				], [
					'type' => 'text',
					'attributes' => [
						'name' => LANG::GET('tool.calculator_pow_weight'),
						'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.calculator_pow_weight')) ? : ''
					]
				]
			],
			'poa' => [
				[
					'type' => 'text',
					'attributes' => [
						'name' => LANG::GET('tool.calculator_poa_parts'),
						'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.calculator_poa_parts')) ? : ''
					],
					'hint' => LANG::GET('tool.calculator_poa_hint')
				], [
					'type' => 'number',
					'attributes' => [
						'name' => LANG::GET('tool.calculator_poa_target'),
						'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.calculator_poa_target')) ? : ''
					]
				]
			],
			'cd' => [
				[
					'type' => 'text',
					'attributes' => [
						'name' => LANG::GET('tool.calculator_cd_diameter'),
						'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.calculator_cd_diameter')) ? : ''
					],
					'hint' => LANG::GET('tool.calculator_cd_hint')
				], [
					'type' => 'number',
					'attributes' => [
						'name' => LANG::GET('tool.calculator_cd_bores'),
						'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.calculator_cd_bores')) ? : ''
					]
				]
			],
		];

		$result['render'] = ['form' => [
			'data-usecase' => 'tool_calculator',
			'action' => "javascript:api.tool('post', 'calculator', '" . (isset($types[$this->_requestedType]) ? $this->_requestedType : 'pow') . "')"
		],
		'content' => [
			[
				[
					'type' => 'select',
					'attributes' => [
						'name' => LANG::GET('tool.calculator_select_type'),
						'onchange' => "api.tool('get', 'calculator', this.value)"
					],
					'content' => [
						LANG::GET('tool.calculator_pow') => $this->_requestedType === 'pow' ? ['value' => 'pow', 'selected' => true] : ['value' => 'pow'],
						LANG::GET('tool.calculator_poa') => $this->_requestedType === 'poa' ? ['value' => 'poa', 'selected' => true] : ['value' => 'poa'],
						LANG::GET('tool.calculator_cd') => $this->_requestedType === 'cd' ? ['value' => 'cd', 'selected' => true] : ['value' => 'cd'],
					]
				],
				$types[isset($types[$this->_requestedType]) ? $this->_requestedType : 'pow'],
			]
		]];

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$calculation = '';
				switch($this->_requestedType){
					case 'pow':
						$calculation = parts_of_weight(UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.calculator_pow_parts')), UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.calculator_pow_weight')));
						break;
					case 'poa':
						$calculation = parts_of_attribute(UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.calculator_poa_parts')), UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.calculator_poa_target')));
						break;
					case 'cd':
						$calculation = circular_distance(UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.calculator_cd_diameter')), UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.calculator_cd_bores')));
						break;
				}
				$result['render']['content'][] = [
					[
						'type' => 'text',
						'attributes' => [
							'name' => LANG::GET('tool.calculator_result'),
							'value' => $calculation,
							'readonly' => true
						]
					]
				];
				break;
		}
		$this->response($result);
	}

	/**
	 *             _
	 *   ___ ___ _| |___
	 *  |  _| . | . | -_|
	 *  |___|___|___|___|
	 *
	 */
	public function code(){
		$types = [
			'qrcode_text' => ['name' => LANG::GET('tool.qrcode_text'),
				'content'=> [
					[
						'type' => 'textarea',
						'attributes' => [
							'name' => LANG::GET('tool.qrcode_text'),
							'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_text')) ? : ''
						]
					]
				],
				'code' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_text')) ? : ''],
			'qrcode_appointment' => ['name' => LANG::GET('tool.qrcode_appointment'),
				'content'=> [
					[
						'type' => 'date',
						'attributes' => [
							'name' => LANG::GET('tool.qrcode_appointment_date'),
							'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_appointment_date')) ? : ''
						]
					],
					[
						'type' => 'time',
						'attributes' => [
							'name' => LANG::GET('tool.qrcode_appointment_time'),
							'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_appointment_time')) ? : ''
						]
					],
					[
						'type' => 'text',
						'hint' => LANG::GET('tool.qrcode_appointment_occasion_hint'),
						'attributes' => [
							'name' => LANG::GET('tool.qrcode_appointment_occasion'),
							'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_appointment_occasion')) ? : ''
						]
					],
					[
						'type' => 'text',
						'hint' => LANG::GET('tool.qrcode_appointment_reminder_hint'),
						'attributes' => [
							'name' => LANG::GET('tool.qrcode_appointment_reminder'),
							'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_appointment_reminder')) ? : ''
						]
					],
					[
						'type' => 'number',
						'attributes' => [
							'name' => LANG::GET('tool.qrcode_appointment_duration'),
							'min' => 1,
							'max' => 200,
							'step' => 1,
							'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_appointment_duration')) ? : 1
						]
					],
				],
				'code' =>
					"BEGIN:VEVENT\n" .
					"SUMMARY:" . UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_appointment_occasion')) . "\n" .
					"LOCATION:" . LANG::GET('company.address') . "\n" .
					"DESCRIPTION:" . (UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_appointment_reminder')) ? : UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_appointment_occasion'))) . "\n" .
					"DTSTART:" . str_replace('-', '', UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_appointment_date'))) . 'T' . str_replace(':', '', UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_appointment_time'))) . "00\n" .
					"DTEND:" . date("Ymd\THis", strtotime(UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_appointment_date')) . ' ' . UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_appointment_time'))) + intval(UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.qrcode_appointment_duration')))*3600) . "\n" .
					"END:VEVENT"
			],
			'barcode_code128' => ['name' => LANG::GET('tool.barcode_code128'),
				'content'=> [
					[
						'type' => 'text',
						'hint' => LANG::GET('tool.barcode_code128_hint'),
						'attributes' => [
							'name' => LANG::GET('tool.barcode_description'),
							'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.barcode_description')) ? : ''
						]
					],
				],
				'code' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.barcode_description')) ? : ''],
			'barcode_ean13' => ['name' => LANG::GET('tool.barcode_ean13'),
				'content'=> [
					[
						'type' => 'number',
						'hint' => LANG::GET('tool.barcode_ean13_hint'),
						'attributes' => [
							'name' => LANG::GET('tool.barcode_description'),
							'value' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.barcode_description')) ? : ''
						]
					],
				],
				'code' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('tool.barcode_description')) ? : ''],
		];

		$options = [];
		foreach($types as $type => $properties){
			$options[$properties['name']] = ['value' => $type];
			if ($this->_requestedType === $type) $options[$properties['name']]['selected'] = true;
		}

		$result['render'] = ['form' => [
			'data-usecase' => 'tool_create_code',
			'action' => "javascript:api.tool('post', 'code', '" . (isset($types[$this->_requestedType]) ? $this->_requestedType : 'qrcode_text') . "')"
		],
		'content' => [
			[
				[
					'type' => 'select',
					'attributes' => [
						'name' => LANG::GET('tool.code_select_type'),
						'onchange' => "api.tool('get', 'code', this.value)"
					],
					'content' => $options
				],
				$types[isset($types[$this->_requestedType]) ? $this->_requestedType : 'qrcode_text']['content'],
			]
		]];

		if ($this->_requestedType){
			if ($types[$this->_requestedType]['code']){
				if (in_array($this->_requestedType, ['qrcode_text', 'qrcode_appointment'])){
					$result['render']['content'][] = [
						[
							'type' => 'image',
							'description' => LANG::GET('tool.code_created'),
							'attributes' =>[
								'name' => $types[$this->_requestedType]['name'],
								'qrcode' => $types[$this->_requestedType]['code']
							]
						]
					];	
				}
				else { // barcode
					$result['render']['content'][] = [
						[
							'type' => 'image',
							'description' => LANG::GET('tool.code_created'),
							'attributes' => [
								'name' => $types[$this->_requestedType]['name'],
								'barcode' => ['value' => $types[$this->_requestedType]['code'], 'format' => strtoupper(substr(stristr($this->_requestedType, '_'), 1))]
							]
						]
					];	
				}
			}
		}
		$this->response($result);
	}

	/**
	 *
	 *   ___ ___ ___ ___ ___ ___ ___
	 *  |_ -|  _| .'|   |   | -_|  _|
	 *  |___|___|__,|_|_|_|_|___|_|
	 *
	 */
	public function scanner(){
		$result['render'] = ['content' => [
			[
				[
					'type' => 'scanner',
					'description' => LANG::GET('menu.tools_scanner'),
					'destination' => 'tool_scanner'
				], [
					'type' => 'textarea',
					'attributes' =>[
						'name' => LANG::GET('tool.scanner_result'),
						'rows' => 8,
						'readonly' => true,
						'id' => 'tool_scanner'
					]
				]
			]
		]];
		$this->response($result);
	}
	
	/**
	 *       _   _     _
	 *   ___| |_| |_ _|_|___ _ _ _ ___ ___
	 *  |_ -|  _| | | | | -_| | | | -_|  _|
	 *  |___|_| |_|\_/|_|___|_____|___|_|
	 *
	 */
	public function stlviewer(){
		$files = UTILITY::listFiles(UTILITY::directory('sharepoint'),'asc');
		$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
		foreach ($folders as $folder) {
			$files = array_merge($files, UTILITY::listFiles($folder ,'asc'));
		}
		$files = array_merge($files, UTILITY::listFiles(UTILITY::directory('external_documents') ,'asc'));
		$options = ['...' => ['value' => 'null']];
		foreach ($files as $path){
			if (pathinfo($path)['extension'] === 'stl') $options[$path] = ['value' => $path];
		}
		if (count($options) > 1) {
			$result['render'] = ['content' => [
				[
					[
						'type' => 'select',
						'attributes' => [
							'name' => LANG::GET('tool.stl_viewer_select'),
							'onchange' => "_client.tool.initStlViewer('../' + this.value)"
						],
						'content' => $options
					], [
						'type' => 'stlviewer',
					]
				]
			]];
		} else $result['render']['content'] = $this->noContentAvailable(LANG::GET('file.no_files'));
		$this->response($result);
	}
}
?>
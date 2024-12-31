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
						'name' => $this->_lang->GET('tool.calculator.pow_parts'),
						'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.pow_parts')) ? : ''
					],
					'hint' => $this->_lang->GET('tool.calculator.pow_hint')
				], [
					'type' => 'text',
					'attributes' => [
						'name' => $this->_lang->GET('tool.calculator.pow_weight'),
						'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.pow_weight')) ? : ''
					]
				]
			],
			'poa' => [
				[
					'type' => 'text',
					'attributes' => [
						'name' => $this->_lang->GET('tool.calculator.poa_parts'),
						'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.poa_parts')) ? : ''
					],
					'hint' => $this->_lang->GET('tool.calculator.poa_hint')
				], [
					'type' => 'number',
					'attributes' => [
						'name' => $this->_lang->GET('tool.calculator.poa_target'),
						'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.poa_target')) ? : ''
					]
				]
			],
			'cd' => [
				[
					'type' => 'text',
					'attributes' => [
						'name' => $this->_lang->GET('tool.calculator.cd_diameter'),
						'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.cd_diameter')) ? : ''
					],
					'hint' => $this->_lang->GET('tool.calculator.cd_hint')
				], [
					'type' => 'number',
					'attributes' => [
						'name' => $this->_lang->GET('tool.calculator.cd_bores'),
						'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.cd_bores')) ? : ''
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
						'name' => $this->_lang->GET('tool.calculator.select_type'),
						'onchange' => "api.tool('get', 'calculator', this.value)"
					],
					'content' => [
						$this->_lang->GET('tool.calculator.pow') => $this->_requestedType === 'pow' ? ['value' => 'pow', 'selected' => true] : ['value' => 'pow'],
						$this->_lang->GET('tool.calculator.poa') => $this->_requestedType === 'poa' ? ['value' => 'poa', 'selected' => true] : ['value' => 'poa'],
						$this->_lang->GET('tool.calculator.cd') => $this->_requestedType === 'cd' ? ['value' => 'cd', 'selected' => true] : ['value' => 'cd'],
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
						$calculation = parts_of_weight(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.pow_parts')), UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.pow_weight')));
						break;
					case 'poa':
						$calculation = parts_of_attribute(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.poa_parts')), UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.poa_target')));
						break;
					case 'cd':
						$calculation = circular_distance(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.cd_diameter')), UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.cd_bores')));
						break;
				}
				$result['render']['content'][] = [
					[
						'type' => 'text',
						'attributes' => [
							'name' => $this->_lang->GET('tool.calculator.result'),
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
			'qrcode_text' => ['name' => $this->_lang->GET('tool.code.qrcode_text'),
				'content'=> [
					[
						'type' => 'textarea',
						'attributes' => [
							'name' => $this->_lang->GET('tool.code.qrcode_text'),
							'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_text')) ? : ''
						]
					]
				],
				'code' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_text')) ? : ''],
			'qrcode_appointment' => ['name' => $this->_lang->GET('tool.code.qrcode_appointment'),
				'content'=> [
					[
						'type' => 'date',
						'attributes' => [
							'name' => $this->_lang->GET('tool.code.qrcode_appointment_date'),
							'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_appointment_date')) ? : ''
						]
					],
					[
						'type' => 'time',
						'attributes' => [
							'name' => $this->_lang->GET('tool.code.qrcode_appointment_time'),
							'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_appointment_time')) ? : ''
						]
					],
					[
						'type' => 'text',
						'hint' => $this->_lang->GET('tool.code.qrcode_appointment_occasion_hint'),
						'attributes' => [
							'name' => $this->_lang->GET('tool.code.qrcode_appointment_occasion'),
							'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_appointment_occasion')) ? : ''
						]
					],
					[
						'type' => 'text',
						'hint' => $this->_lang->GET('tool.code.qrcode_appointment_reminder_hint'),
						'attributes' => [
							'name' => $this->_lang->GET('tool.code.qrcode_appointment_reminder'),
							'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_appointment_reminder')) ? : ''
						]
					],
					[
						'type' => 'number',
						'attributes' => [
							'name' => $this->_lang->GET('tool.code.qrcode_appointment_duration'),
							'min' => 1,
							'max' => 200,
							'step' => 1,
							'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_appointment_duration')) ? : 1
						]
					],
				],
				'code' =>
					"BEGIN:VEVENT\n" .
					"SUMMARY:" . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_appointment_occasion')) . "\n" .
					"LOCATION:" . $this->_lang->GET('company.address') . "\n" .
					"DESCRIPTION:" . (UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_appointment_reminder')) ? : UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_appointment_occasion'))) . "\n" .
					"DTSTART:" . str_replace('-', '', UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_appointment_date'))) . 'T' . str_replace(':', '', UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_appointment_time'))) . "00\n" .
					"DTEND:" . date("Ymd\THis", strtotime(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_appointment_date')) . ' ' . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_appointment_time'))) + intval(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_appointment_duration')))*3600) . "\n" .
					"END:VEVENT"
			],
			'barcode_code128' => ['name' => $this->_lang->GET('tool.code.barcode_code128'),
				'content'=> [
					[
						'type' => 'text',
						'hint' => $this->_lang->GET('tool.code.barcode_code128_hint'),
						'attributes' => [
							'name' => $this->_lang->GET('tool.code.barcode_description'),
							'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.barcode_description')) ? : ''
						]
					],
				],
				'code' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.barcode_description')) ? : ''],
			'barcode_ean13' => ['name' => $this->_lang->GET('tool.code.barcode_ean13'),
				'content'=> [
					[
						'type' => 'number',
						'hint' => $this->_lang->GET('tool.code.barcode_ean13_hint'),
						'attributes' => [
							'name' => $this->_lang->GET('tool.code.barcode_description'),
							'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.barcode_description')) ? : ''
						]
					],
				],
				'code' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.barcode_description')) ? : ''],
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
						'name' => $this->_lang->GET('tool.code.code_select_type'),
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
							'description' => $this->_lang->GET('tool.code.code_created'),
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
							'description' => $this->_lang->GET('tool.code.code_created'),
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
	 *   _                   
	 *  |_|_____ ___ ___ ___ 
	 *  | |     | .'| . | -_|
	 *  |_|_|_|_|__,|_  |___|
	 *              |___|
	 */
	public function image(){

		$size = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.image.size'));
		$watermark = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.image.options_watermark'));
		$label = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.image.label'));

		$result['render'] = ['form' => [
			'data-usecase' => 'tool_image',
			'action' => "javascript:api.tool('post', 'image')"
		],
		'content' => [
			[
				[
					'type' => 'file',
					'attributes' => [
						'name' => $this->_lang->GET('tool.image.source'),
						'multiple' => true,
						'accept' => '.jpg,.jpeg,.png,.gif'
					]
				],
				[
					'type' => 'br'
				],
				[
					'type' => 'checkbox',
					'attributes' => [
						'name' => $this->_lang->GET('tool.image.options')
					],
					'content' => [
						$this->_lang->GET('tool.image.options_watermark') => $watermark ? ['checked' => true] : []
					]
				],
				[
					'type' => 'text',
					'attributes' => [
						'name' => $this->_lang->GET('tool.image.label'),
						'value' => $label ? : ''
					]
				],
				[
					'type' => 'select',
					'attributes' => [
						'name' => $this->_lang->GET('tool.image.size'),
					],
					'content' => [
						'...' => !$size? ['selected' => true]: [],
						'800 x 600' => $size === '800 x 600' ? ['selected' => true]: [],
						'1024 x 768' => $size === '1024 x 768' ? ['selected' => true]: [],
						'1280 x 1024' => $size === '1280 x 1024' ? ['selected' => true]: [],
						'1600 x 1200' => $size === '1600 x 1200' ? ['selected' => true]: [],
						'3200 x 2400' => $size === '3200 x 2400' ? ['selected' => true]: [],
					]
				],
			]
		]];

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (isset($_FILES[$this->_lang->PROPERTY('tool.image.source')]) && $_FILES[$this->_lang->PROPERTY('tool.image.source')]['tmp_name'][0]) {
					$result['render']['content'][] = [];
					$images = UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('tool.image.source')], UTILITY::directory('tmp'), [$size]);

					foreach($images as $image){
						UTILITY::alterImage($image, 0, UTILITY_IMAGE_REPLACE, null, $label, $watermark ? '../' . CONFIG['application']['watermark'] : '');
						$result['render']['content'][count($result['render']['content']) - 1][] = [
							'type' => 'image',
							'description' => pathinfo($image)['basename'],
							'attributes' => [
								'name' => pathinfo($image)['basename'],
								'url' => substr($image, 3)
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
					'description' => $this->_lang->GET('menu.tools.scanner'),
					'destination' => 'tool_scanner'
				], [
					'type' => 'textarea',
					'attributes' =>[
						'name' => $this->_lang->GET('tool.scanner.result'),
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
		ksort($options);
		if (count($options) > 1) {
			$result['render'] = ['content' => [
				[
					[
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('tool.stl_viewer.select'),
							'onchange' => "_client.tool.initStlViewer('../' + this.value)"
						],
						'content' => $options
					], [
						'type' => 'stlviewer',
					]
				]
			]];
		} else $result['render']['content'] = $this->noContentAvailable($this->_lang->GET('file.no_files'));
		$this->response($result);
	}
}
?>
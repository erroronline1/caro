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
		 * @param string $parts '70/30' \D beside decimal point will be stripped
		 * @param string $weight '730' , replaced by decimal point, other \D will be stripped
		 * @return string '511 / 219'
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
		 * @param string $attributes '20,65' \D will be stripped
		 * @param string $target '35' , replaced by decimal point, other \D will be stripped
		 * @return string '1 x 65 (33.33 %) / 2 x 20 (66.67 %)'
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
		 * @param string $diameter '22.7' , replaced by decimal point, other \D will be stripped
		 * @param string $holes '3' converted to int
		 * @return string '23.77'
		 */
		function circular_distance($diameter = '', $holes = '1'){
			$diameter = floatval(str_replace(',', '.', $diameter));
			$holes = intval($holes);
			if ($holes < 1) return '';
			return strval(round(pi() * $diameter / $holes, 2));
		}

		/**
		 * evenly distributed measure reduction
		 * @param array $measures ['22.7', '21', ...] , replaced by decimal point, other \D will be stripped
		 * @param string $proximal '1' converted to float
		 * @param string $distal '5' converted to float
		 * @return string '23.77'
		 */
		function measure_adjustment($measures = [], $proximal = '1', $distal = '5'){
			if (count($measures) < 2) return '';
			$result = [];
			$distal = floatval(str_replace(',', '.', $distal));
			$proximal = floatval(str_replace(',', '.', $proximal));
			for ($i = 0; $i < count($measures); $i++){
				$percent = $proximal + (($distal - $proximal) / (count($measures) - 1)) * $i;
				$measures[$i] = floatval(str_replace(',', '.', $measures[$i]));
				$result[] = strval($measures[$i]) . " - " . strval(round($percent, 2)) . "% -> " . strval(round($measures[$i] * (100-$percent) / 100, 2));
			}
			return implode("\n", $result);
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
			'ma' => [
				[
					'type' => 'number',
					'attributes' => [
						'name' => $this->_lang->GET('tool.calculator.ma_start'),
						'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.ma_start')) ? : ''
					]
				], [
					'type' => 'number',
					'attributes' => [
						'name' => $this->_lang->GET('tool.calculator.ma_end'),
						'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.ma_end')) ? : '',
					],
					'hint' => $this->_lang->GET('tool.calculator.ma_hint')
				], [
					'type' => 'number',
					'attributes' => [
						'name' => $this->_lang->GET('tool.calculator.ma_measure'),
						'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.ma_measure')) ? : '',
					]
				], [
					'type' => 'number',
					'attributes' => [
						'name' => $this->_lang->GET('tool.calculator.ma_measure'),
						'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.ma_measure') . '(2)') ? : '',
						'multiple' => true
					]
				]
			]
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
						$this->_lang->GET('tool.calculator.ma') => $this->_requestedType === 'ma' ? ['value' => 'ma', 'selected' => true] : ['value' => 'ma'],
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
					case 'ma':
						$measures = [
							UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.ma_measure')),
							UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.ma_measure') . '(2)')
						];
						foreach($this->_payload as $key => $value){
							if (!in_array($key, [$this->_lang->GET('tool.calculator.ma_measure'), $this->_lang->GET('tool.calculator.ma_measure') . '(2)']) && str_starts_with($key, $this->_lang->GET('tool.calculator.ma_measure'))){
								if ($value) $measures[] = $value;
								$result['render']['content'][count($result['render']['content']) -1][] = [
									'type' => 'number',
									'attributes' => [
										'name' => $key,
										'value' => $value,
										'multiple' => true
									]
								];
							}
						}
						$calculation = measure_adjustment($measures, UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.ma_start')), UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.calculator.ma_end')));
						break;
				}
				$result['render']['content'][] = [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('tool.calculator.result')
						],
						'content' => $calculation
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
				'content' => [
					[
						'type' => 'textarea',
						'attributes' => [
							'name' => $this->_lang->GET('tool.code.qrcode_text'),
							'value' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_text')) ? : ''
						]
					]
				],
				'code' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('tool.code.qrcode_text')) ? : ''
			],
			'barcode_code128' => ['name' => $this->_lang->GET('tool.code.barcode_code128'),
				'content' => [
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
				'content' => [
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
			if ($types[$this->_requestedType]['code'] && count((array_keys((array) $this->_payload)))){
				switch ($this->_requestedType){
					case 'qrcode_text':
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
						break;
					default: //barcode
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
					$size = explode(' x ', $size);
					$size = isset ($size[1]) ? max(intval($size[0]), intval($size[1])) : null;
					foreach($images as $image){
						UTILITY::alterImage($image, $size, UTILITY_IMAGE_REPLACE, null, $label, $watermark ? '../' . CONFIG['application']['watermark'] : '');
						$dimensions = getimagesize($image);
						$result['render']['content'][count($result['render']['content']) - 1][] = [
							'type' => 'image',
							'description' => pathinfo($image)['filename'],
							'attributes' => [
								'name' => pathinfo($image)['filename'],
								'url' => substr($image, 3)
							],
							'dimensions'=> [
								'width' => $dimensions[0],
								'height' => $dimensions[1]
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
	 *       _     
	 *   ___|_|___ 
	 *  |- _| | . |
	 *  |___|_|  _|
	 *        |_|  
	 */
	public function zip(){
		$result['render'] = [
			'form' => [
				'data-usecase' => 'tool_zip',
				'action' => "javascript:api.tool('post', 'zip')"
			],
			'content' => [
				[
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('tool.zip.hint')
						],
						'content' => $this->_lang->GET('tool.zip.hint_content')
					], [
						'type' => 'file',
						'attributes' => [
							'name' => $this->_lang->GET('tool.zip.files'),
							'multiple' => true
						]
					]
				]
			]
		];

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (isset($_FILES[$this->_lang->PROPERTY('tool.zip.files')]) && $_FILES[$this->_lang->PROPERTY('tool.zip.files')]['tmp_name'][0]) {
					// create filename by concatenation
					$zipname = '';
					foreach($_FILES[$this->_lang->PROPERTY('tool.zip.files')]['name'] as $filename){
						$zipname .= preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $filename);
					}
					// create zip
					$zip = new ZipArchive();
					$zip->open(UTILITY::directory('tmp') . '/' . $zipname .'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
					foreach($_FILES[$this->_lang->PROPERTY('tool.zip.files')]['tmp_name'] as $index => $file){
						$zip->addFile($file, $_FILES[$this->_lang->PROPERTY('tool.zip.files')]['name'][$index]);
					}
					$zip->close();

					$downloadfiles = [];
					$downloadfiles[$zipname .'.zip'] = [
						'href' => './api/api.php/file/stream/' . substr(UTILITY::directory('tmp'), 3) . '/' . $zipname .'.zip',
						'download' => $zipname .'.zip'
					];			
					$body = [];
					array_push($body, 
						[[
							'type' => 'links',
							'description' =>  $this->_lang->GET('tool.zip.download'),
							'content' => $downloadfiles
						]]
					);
					$this->response([
						'render' => $body,
					]);
				}
		}

		$this->response($result);
	}
}
?>
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
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);

		$this->_requestedType = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

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
			'action' => "javascript:api.tool('post', 'code', '" . (array_key_exists($this->_requestedType, $types) ? $this->_requestedType : 'qrcode_text') . "')"
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
				$types[array_key_exists($this->_requestedType, $types) ? $this->_requestedType : 'qrcode_text']['content'],
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
	
	public function stlviewer(){
		$files = UTILITY::listFiles(UTILITY::directory('sharepoint'),'asc');
		$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
		foreach ($folders as $folder) {
			$files = array_merge($files, UTILITY::listFiles($folder ,'asc'));
		}
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
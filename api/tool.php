<?php
// diverse tools
class TOOL extends API {
	/**
	 * in case you want to create a code from any values in any form add 
	 * 'data-usecase' => 'tool_create_code'
	 * to inputs attributes (hiddeninput if applicable)
	 */

	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedType = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedType = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	public function code(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		$types=[
			'qrcode_text' => ['name' => LANG::GET('tool.qrcode_text'),
				'content'=> [
					['type' => 'textarea',
					'attributes' => [
						'name' => LANG::GET('tool.qrcode_text'),
						'data-usecase' => 'tool_create_code',
						'value' => UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_text'))) ? : ''
					]]
				],
				'code' => UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_text'))) ? : ''],
			'qrcode_appointment' => ['name' => LANG::GET('tool.qrcode_appointment'),
				'content'=>[
					['type' => 'dateinput',
					'attributes' => [
						'name' => LANG::GET('tool.qrcode_appointment_date'),
						'data-usecase' => 'tool_create_code',
						'value' => UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_appointment_date'))) ? : ''
						]
					],
					['type' => 'timeinput',
					'attributes' => [
						'name' => LANG::GET('tool.qrcode_appointment_time'),
						'data-usecase' => 'tool_create_code',
						'value' => UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_appointment_time'))) ? : ''
						]
					],
					['type' => 'textinput',
					'description' => LANG::GET('tool.qrcode_appointment_occasion_description'),
					'attributes' => [
						'name' => LANG::GET('tool.qrcode_appointment_occasion'),
						'data-usecase' => 'tool_create_code',
						'value' => UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_appointment_occasion'))) ? : ''
						]
					],
					['type' => 'textinput',
					'description' => LANG::GET('tool.qrcode_appointment_reminder_description'),
					'attributes' => [
						'name' => LANG::GET('tool.qrcode_appointment_reminder'),
						'data-usecase' => 'tool_create_code',
						'value' => UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_appointment_reminder'))) ? : ''
						]
					],
					['type' => 'numberinput',
					'attributes' => [
						'name' => LANG::GET('tool.qrcode_appointment_duration'),
						'min' => 1,
						'max' => 200,
						'step' => 1,
						'data-usecase' => 'tool_create_code',
						'value' => UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_appointment_duration'))) ? : 1
						]
					],
				],
				'code' =>
					"BEGIN:VEVENT\n" .
					"SUMMARY:" . UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_appointment_occasion'))) . "\n" .
					"LOCATION:" . LANG::GET('company.location') . "\n" .
					"DESCRIPTION:" . (UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_appointment_reminder'))) ? : UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_appointment_occasion')))) . "\n" .
					"DTSTART:" . str_replace('-', '', UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_appointment_date')))) . 'T' . str_replace(':', '', UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_appointment_time')))) . "00\n" .
					"DTEND:" . date("Ymd\THis", strtotime(UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_appointment_date'))) . ' ' . UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_appointment_time')))) + intval(UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.qrcode_appointment_duration'))))*3600) . "\n" .
					"END:VEVENT"
			],
			'barcode_code128' => ['name' => LANG::GET('tool.barcode_code128'),
				'content'=>[
					['type' => 'textinput',
					'description' => LANG::GET('tool.barcode_code128_placeholder'),
					'attributes' => [
						'name' => LANG::GET('tool.barcode_description'),
						'data-usecase' => 'tool_create_code',
						'value' => UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.barcode_description'))) ? : ''
						]
					],
				],
				'code' => UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.barcode_description'))) ? : ''],
			'barcode_ean13' => ['name' => LANG::GET('tool.barcode_ean13'),
				'content'=>[
					['type' => 'numberinput',
					'description' => LANG::GET('tool.barcode_ean13_placeholder'),
					'attributes' => [
						'name' => LANG::GET('tool.barcode_description'),
						'data-usecase' => 'tool_create_code',
						'value' => UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.barcode_description'))) ? : ''
						]
					],
				],
				'code' => UTILITY::propertySet($this->_payload, preg_replace('/\W/', '_', LANG::GET('tool.barcode_description'))) ? : ''],
		];

		$options=[];
		foreach($types as $type => $properties){
			$options[$properties['name']] = ['value' => $type];
			if ($this->_requestedType === $type) $options[$properties['name']]['selected'] = true;
		}


		$result['body']=['content' => [
			[
				['type' => 'select',
				'attributes' => [
					'name' => LANG::GET('tool.code_select_type'),
					'onchange' => "api.tool('get', 'code', this.value)"
				],
				'content' => $options],
				$types[array_key_exists($this->_requestedType, $types) ? $this->_requestedType : 'qrcode_text']['content'],
				['type' => 'submitbutton',
				'description' => LANG::GET('tool.code_create_button'),
				'attributes' =>[
					'type' => 'button',
					'onpointerup' =>  "api.tool('get', 'code', '" . (array_key_exists($this->_requestedType, $types) ? $this->_requestedType : 'qrcode_text') . "', 'display')"
				]]
			]
		]];

		if ($this->_requestedType){
			if ($types[$this->_requestedType]['code']){
				if (in_array($this->_requestedType, ['qrcode_text', 'qrcode_appointment'])){
					$result['body']['content'][] = [
						['type' => 'image',
						'description' => LANG::GET('tool.code_created'),
						'attributes' =>[
							'name' => $types[$this->_requestedType]['name'],
							'qrcode' => $types[$this->_requestedType]['code']
						]]
					];	
				}
				else { // barcode
					$result['body']['content'][] = [
						['type' => 'image',
						'description' => LANG::GET('tool.code_created'),
						'attributes' =>[
							'name' => $types[$this->_requestedType]['name'],
							'barcode' => ['value' => $types[$this->_requestedType]['code'], 'format' => strtoupper(substr(stristr($this->_requestedType, '_'), 1))]
						]]
					];	
				}
			}
		}
		$this->response($result);
	}

	public function scanner(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		$result['body']=['content' => [
			[
				['type' => 'scanner',
				'description' => LANG::GET('menu.tools_scanner'),
				'destination' => 'tool_scanner'
				],
				['type' => 'textarea',
				'attributes' =>[
					'name' => LANG::GET('tool.scanner_result'),
					'rows' => 8,
					'readonly' => true,
					'id' => 'tool_scanner'
				]]
			]
		]];
		$this->response($result);
	}
	
	public function stlviewer(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);

		$files = UTILITY::listFiles('../' . INI['sharepoint']['folder'] ,'asc');
		$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
		foreach ($folders as $folder) {
			$files = array_merge($files, UTILITY::listFiles($folder ,'asc'));
		}
		$options = ['...'=>[]];
		foreach ($files as $path){
			if (pathinfo($path)['extension'] === 'stl') $options[$path] = [];
		}

		$result['body']=['content' => [
			[
				['type' => 'select',
				'attributes' => [
					'name' => LANG::GET('tool.stl_viewer_select'),
					'onchange' => "toolModule.initStlViewer('../' + this.value)"
				],
				'content' => $options],
				['type' => 'stlviewer',
				]
			]
		]];
		$this->response($result);
	}
	
}

$api = new TOOL();
$api->processApi();

exit;
?>
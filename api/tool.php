<?php
// write and read user messages
class TOOL extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedType = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedType = array_key_exists(2, REQUEST) ? (REQUEST[2] != 0 ? REQUEST[2] : null) : null;
	}

	public function code(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		
		$types=[
			'qrcode_text' => ['name' => LANG::GET('tool.qrcode_text'), 'content'=> [
				[
					['type' => 'textarea',
					'description' => LANG::GET('tool.qrcode_text')]
				]
			]],
			'qrcode_appointment' => ['name' => LANG::GET('tool.qrcode_appointment'), 'content'=>[
				[
					['type' => 'dateinput',
					'description' => LANG::GET('tool.qrcode_appointment_date'),
					'collapse' => true
					],
					['type' => 'timeinput',
					'description' => '',
					'collapse' => true
					],
					['type' => 'dateinput',
					'collapse' => true],
				],
				[
					['type' => 'textinput',
					'description' => LANG::GET('tool.qrcode_appointment_occasion'),
					'attributes' => [
						'placeholder' => LANG::GET('tool.qrcode_appointment_occasion_placeholder')
						]
					],
				],
				[
					['type' => 'textinput',
					'description' => LANG::GET('tool.qrcode_appointment_reminder'),
					'attributes' => [
						'placeholder' => LANG::GET('tool.qrcode_appointment_reminder_placeholder')
						]
					],
				],
				[
					['type' => 'numberinput',
					'description' => LANG::GET('tool.qrcode_appointment_duration'),
					'attributes' => [
						'min' => 1,
						'max' => 8,
						'step' => 1,
						'value' => 1
						]
					],
				]
			]],
			'barcode_code128' => ['name' => LANG::GET('tool.barcode_code128'), 'content'=>[
				[
					['type' => 'textinput',
					'description' => LANG::GET('tool.barcode_description'),
					'attributes' => [
						'placeholder' => LANG::GET('tool.barcode_code128_placeholder')
						]
					],
				],

			]],
			'barcode_ean13' => ['name' => LANG::GET('tool.barcode_ean13'), 'content'=>[
				[
					['type' => 'numberinput',
					'description' => LANG::GET('tool.barcode_description'),
					'attributes' => [
						'placeholder' => LANG::GET('tool.barcode_ean13_placeholder'),
						]
					],
				],

			]],
		];
		$result['body']=['content' => $types[array_key_exists($this->_requestedType, $types) ? $this->_requestedType : 'qrcode_text']['content']];

		$options=[];
		foreach($types as $type => $properties){
			$options[$properties['name']] = ['value' => $type];
			if ($this->_requestedType === $type) $options[$properties['name']]['selected'] = true;
		}

		array_unshift($result['body']['content'], [
			['type' => 'select',
			'description' => LANG::GET('tool.code_select_type'),
			'attributes' => [
				'onchange' => "api.tool('get', 'code', this.value)"
			],
			'content' => $options]
		]);
		$result['body']['content'][] = [
			['type' => 'submitbutton',
			'description' => LANG::GET('tool.code_create_button'),
			'attributes' =>[
				'type' => 'button',
				'onpointerup' => "alert('hello')"
			]]
		];
		$this->response($result);
	}
	
}

$api = new TOOL();
$api->processApi();

exit;
?>
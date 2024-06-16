<?php 
// place and process orders
class ORDER extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_subMethod = null;
	private $_borrowedModule = null;
	private $_subMethodState = null;

	public function __construct(){
		parent::__construct();
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);

		$this->_requestedID = array_key_exists(2, REQUEST) ? (REQUEST[2] != 'false' ? REQUEST[2]: null) : null;
		$this->_subMethod = array_key_exists(3, REQUEST) ? REQUEST[3] : null;
		$this->_borrowedModule = $this->_subMethodState = array_key_exists(4, REQUEST) ? REQUEST[4] : null;
	}

	public function prepared(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				$approval = false;
				if ($orderauth = UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.edit_order_authorization'))){
					$result = SQLQUERY::EXECUTE($this->_pdo, 'user_get_orderauth', [
						'values' => [
							':orderauth' => orderauth
						]
					]);
					$result = $result ? $result[0] : null;
					if ($result){
						$approval = $result['name'] . LANG::GET('order.orderauth_verified');
					}
				}
				if (array_key_exists(LANG::PROPERTY('order.add_approval_signature'), $_FILES) && $_FILES[LANG::PROPERTY('order.add_approval_signature')]['tmp_name']){
					$signature = gettype($_FILES[LANG::PROPERTY('order.add_approval_signature')]['tmp_name'])=='array' ? $_FILES[LANG::PROPERTY('order.add_approval_signature')]['tmp_name'][0] : $_FILES[LANG::PROPERTY('order.add_approval_signature')]['tmp_name'];
					$approval = 'data:image/png;base64,' . base64_encode(UTILITY::resizeImage($signature, 512, UTILITY_IMAGE_RESOURCE, 'png'));
				}
				$approvedIDs = UTILITY::propertySet($this->_payload, LANG::PROPERTY('order.bulk_approve_order'));
				if (!$approval) $this->response([], 401);
				if (!$approvedIDs) $this->response([], 406);

				$order_data=['items'=>[]];

				$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_prepared_orders');
				$index=0;
				foreach ($orders as $order){
					if (array_search($order['id'], $approvedIDs) === false) continue;

					foreach (json_decode($order['order_data'], true) as $key => $items){ // data
						if (is_array($items)){
							foreach($items as $item){
								foreach($item as $key => $subvalue){
									if (boolval($subvalue)) $order_data['items'][$index][$key] = trim(preg_replace(["/\\r/", "/\\n/"], ['', '\\n'], $subvalue));
								}
								$index++;
							}
						} else {
							if (boolval($items)) $order_data[$key] = trim(preg_replace(["/\\r/", "/\\n/"], ['', '\\n'], $items));
						}
					}
				}

				if(!count($order_data['items'])) $this->response([], 406);
				$result = $this->postApprovedOrder(['approval' => $approval, 'order_data' => $order_data]);

				if ($result['status']['msg'] === LANG::GET('order.saved')){
					SQLQUERY::EXECUTE($this->_pdo, 'order_delete_prepared_orders', [
						'replacements' => [
							':id' => implode(",", array_map(Fn($id) => intval($id), $approvedIDs))
						]
					]);
				}
				break;
			case 'GET':
				$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_prepared_orders');
				// display all orders assigned to organizational unit
				if ($this->_requestedID) $units = [$this->_requestedID]; // see orders from selected unit
				elseif (PERMISSION::permissionFor('orderdisplayall')) $units = array_keys(LANGUAGEFILE['units']); // see all orders
				else $units = $_SESSION['user']['units']; // see only orders for own units

				$organizational_orders = [];
				foreach($orders as $key => $row) {
					$order_data = json_decode($row['order_data'], true);
					if (array_intersect([$order_data['organizational_unit']], $units)) {
						array_push($organizational_orders, $row);
					}
				}
				$result = ['body' => ['content' => []]];
				if ($_SESSION['user']['orderauth']){
					$organizational_units=[];
					foreach(LANGUAGEFILE['units'] as $unit => $description){
						$organizational_units[$description] = ['name' => LANG::PROPERTY('order.organizational_unit'), 'onchange' => "api.purchase('get', 'prepared', '" . $unit . "')"];
						//$organizational_units[$description]['checked'] = true;
					}
					if (!$this->_requestedID && array_key_exists('primaryUnit', $_SESSION['user']['app_settings'])) $organizational_units[LANG::GET('units.' . $_SESSION['user']['app_settings']['primaryUnit'])]['checked'] = true;
					elseif($this->_requestedID) $organizational_units[LANG::GET('units.' . $this->_requestedID)]['checked'] = true;
					$result['body']['content'][] = [
						['type' => 'radio',
						'attributes' => [
							'name' => LANG::GET('order.organizational_unit')
						],
						'content' => $organizational_units
						]
					];
				}
				if (count($organizational_orders)){
					foreach($organizational_orders as $order){ // order
						$items = $info = '';
						$processedOrderData = json_decode($order['order_data'], true);
						foreach ($processedOrderData as $key => $value){ // data
							if (is_array($value)){
								foreach($value as $item){
									$items .= LANG::GET('order.prepared_order_item', [
										':quantity' => UTILITY::propertySet((object) $item, 'quantity_label') ? : '',
										':unit' => UTILITY::propertySet((object) $item, 'unit_label') ? : '',
										':number' => UTILITY::propertySet((object) $item, 'ordernumber_label') ? : '',
										':name' => UTILITY::propertySet((object) $item, 'productname_label') ? : '',
										':vendor' => UTILITY::propertySet((object) $item, 'vendor_label') ? : ''
									])."\n";
								}
							} else {
								if ($key==='attachments') continue;
								if ($key==='organizational_unit') $value = LANG::GET('units.' . $value);

								$info .= LANG::GET('order.' . $key) . ': ' . $value . "\n";
							}
						}
						array_push($result['body']['content'], [
							['type' => 'text',
							'content' => $items,
							],
							['type' => 'text',
							'content' => $info,
							],
							['type' => 'checkbox',
							'content' => [LANG::GET('order.bulk_approve_order'). '[]' => ['value' => $order['id']]]
							],
							['type' => 'button',
							'attributes' =>[
								'value' => LANG::GET('order.edit_prepared_order'),
								'type' => 'button',
								'onpointerup' => "api.purchase('get', 'order', " . $order['id']. ")"]
							]
						]);
						if (array_key_exists('attachments', $processedOrderData)){
							$files = [];
							foreach(explode(',', $processedOrderData['attachments']) as $file){
								$files[pathinfo($file)['basename']] = ['href' => $file, 'target' => '_blank'];
							}
							array_splice($result['body']['content'][count($result['body']['content']) - 1], 2, 0, [
								[
									['type' => 'links',
									'description' => LANG::GET('order.attached_files'),
									'content' => $files],
									['type' => 'hiddeninput',
									'attributes' => [
										'name' => 'existingattachments',
										'value' => $processedOrderData['attachments']
									]],
									['type' => 'br']
								]
							]);		
						}
					}
					if ($_SESSION['user']['orderauth'] && count($organizational_orders)) {
						array_push($result['body']['content'], [
							[
								['type' => 'signature',
								'attributes' => [
									'name' => LANG::GET('order.add_approval_signature')
								]]
							],
							[
								['type' => 'numberinput',
								'attributes' => [
									'name' => LANG::GET('user.edit_order_authorization'),
									'type' => 'password'
								]]
							]
						]);
						$result['body']['form'] = ['action' => "javascript:api.purchase('put', 'prepared')", 'data-usecase' => 'purchase'];
					}
				}
				else $result['body']['content'][] = $this->noContentAvailable(LANG::GET('order.no_orders'))[0];
				break;
		}
		$this->response($result);
	}

	public function productsearch(){
		// order to be taken into account in utility.js _client.order.addProduct() method and this->order() method as well!
		switch ($_SERVER['REQUEST_METHOD']){
			case 'GET':
				$result = ['body'=>[]];
				if (!$this->_subMethod) {
					$result['body']['content'] = [];
					break;
				}

				$search = SQLQUERY::EXECUTE($this->_pdo, $this->_borrowedModule === 'editconsumables' ? SQLQUERY::PREPARE('consumables_get_product_search') : SQLQUERY::PREPARE('order_get_product_search'), [
					'values' => [
						':search' => $this->_subMethod
					],
					'replacements' => [
						':vendors' => implode(",", array_map(fn($el) => intval($el), explode('_', $this->_requestedID))),
					]
				]);

				$productsPerSlide = 0;
				$matches = [[]];

				foreach($search as $key => $row) {
					foreach($row as $key => $value){
						$row[$key] = $row[$key] ? str_replace("\n", ' ', $row[$key]) : '';
					}
					$article = intval(count($matches) - 1);
					if (empty($productsPerSlide++ % INI['splitresults']['products_per_slide'])){
						$matches[$article][] = [
							['type' => 'text',
							'description' => LANG::GET('order.add_product_search_matches', [':number' => count($search)]),
							]
						];
					}
					$slide = intval(count($matches[$article]) - 1);
					switch ($this->_borrowedModule){
						case 'editconsumables': // consumables.php can make good use of this method!
							$matches[$article][$slide][] = [
								'type' => 'tile',
								'attributes' => [
									'onpointerup' => "api.purchase('get', 'product', " . $row['id'] . ")",
								],
								'content' => [
									[
										'type' => 'text',
										'content' => $row['vendor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit'] . ' ' . $row['article_ean']
									]
								]
							];
							break;
						case 'productinformation': // consumables.php can make good use of this method!
							$matches[$article][$slide][] = [
								'type' => 'tile',
								'attributes' => [
									'onpointerup' => "api.purchase('get', 'productinformation', " . $row['id'] . ")",
								],
								'content' => [
									[
										'type' => 'text',
										'content' => $row['vendor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit']
									]
								]
							];
							break;
						default:
							$incorporationState = '';
							if ($row['incorporated'] === '') $incorporationState = LANG::GET('order.incorporation_neccessary');
							else {
								$row['incorporated'] = json_decode($row['incorporated'], true);
								if (array_key_exists('_denied', $row['incorporated'])) $incorporationState = LANG::GET('order.incorporation_denied');
								elseif (!PERMISSION::fullyapproved('incorporation', $row['incorporated'])) $incorporationState = LANG::GET('order.incorporation_pending');
							}
							$matches[$article][$slide][] = [
								'type' => 'tile',
								'attributes' => [
									'onpointerup' => "_client.order.addProduct('" . $row['article_unit'] . "', '" . $row['article_no'] . "', '" . $row['article_name'] . "', '" . $row['article_ean'] . "', '" . $row['vendor_name'] . "'); return false;",
								],
								'content' => [
									['type' => 'text',
									'description' => $incorporationState,
									'content' => $row['vendor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit'] . ' ' . $row['article_ean']]
								]
							];
					}
				}
				if (!$matches[0]) $matches[0][] = [
					['type' => 'text',
					'description' => LANG::GET('order.add_product_search_matches', [':number' => count($search)]),
					]
				];
				$result['body']['content'] = $matches;
				break;
			}
		$this->response($result);
	}

	public function filter(){
		if (PERMISSION::permissionFor('orderdisplayall')) $units = array_keys(LANGUAGEFILE['units']); // see all orders
		else $units = $_SESSION['user']['units']; // display only orders for own units

		$filtered = SQLQUERY::EXECUTE($this->_pdo, 'order_get_filter', [
			'values' => [
				':orderfilter' => $this->_requestedID
			],
			'replacements' => [
				':organizational_unit' => implode(",", $units),
			]
		]);
		$matches = [];
		foreach ($filtered as $row){
			$matches[] = strval($row['id']);
		}
		$this->response(['status' => [
			'data' => $matches
		]]);
	}

	private function processOrderForm(){
		$unset=LANG::PROPERTY('consumables.edit_product_search');
		unset ($this->_payload->$unset);
		$unset=LANG::PROPERTY('consumables.edit_product_vendor_select');
		unset ($this->_payload->$unset);

		// detect approval
		$approval = false;
		if ($orderauth = UTILITY::propertySet($this->_payload, LANG::PROPERTY('user.edit_order_authorization'))){
			$result = SQLQUERY::EXECUTE($this->_pdo, 'user_get_orderauth', [
				'values' => [
					':orderauth' => $orderauth
				]
			]);
			$result = $result ? $result[0] : null;
			if ($result){
				$approval = $result['name'] . LANG::GET('order.orderauth_verified');
			}
			unset ($this->_payload->{LANG::PROPERTY('user.edit_order_authorization')});
		}
		if (array_key_exists(LANG::PROPERTY('order.add_approval_signature'), $_FILES) && $_FILES[LANG::PROPERTY('order.add_approval_signature')]['tmp_name']){
			$signature = gettype($_FILES[LANG::PROPERTY('order.add_approval_signature')]['tmp_name'])=='array' ? $_FILES[LANG::PROPERTY('order.add_approval_signature')]['tmp_name'][0] : $_FILES[LANG::PROPERTY('order.add_approval_signature')]['tmp_name'];
			$approval = 'data:image/png;base64,' . base64_encode(UTILITY::resizeImage($signature, 512, UTILITY_IMAGE_RESOURCE, 'png'));
		}

		// initiate data
		$order_data = ['items' => []];
		
		// handle attachments
		$attachments = [];
		if (array_key_exists(LANG::PROPERTY('order.attach_photo'), $_FILES) && $_FILES[LANG::PROPERTY('order.attach_photo')]['tmp_name'][0]){
			$attachments = array_merge($attachments, UTILITY::storeUploadedFiles([LANG::PROPERTY('order.attach_photo')], UTILITY::directory('order_attachments'), [date('YmdHis')]));
			foreach($attachments as $key => $value){
				if ($value)	$attachments[$key] = substr($value, str_starts_with($value, '..') ? 1: 0);
				else unset($attachments[$key]);
			}
		}
		if (array_key_exists(LANG::PROPERTY('order.attach_file'), $_FILES) && $_FILES[LANG::PROPERTY('order.attach_file')]['tmp_name'][0]){
			$attachments = array_merge($attachments, UTILITY::storeUploadedFiles([LANG::PROPERTY('order.attach_file')], UTILITY::directory('order_attachments'), [date('YmdHis')]));
			foreach($attachments as $key => $value){
				if ($value)	$attachments[$key] = substr($value, str_starts_with($value, '..') ? 1: 0);
				else unset($attachments[$key]);
			}
		}
		$existingattachments = UTILITY::propertySet($this->_payload, 'existingattachments') ? : '';
		if ($attachments || $existingattachments) {
			$order_data['attachments'] = trim(implode(',', array_merge($attachments, explode(',', $existingattachments))), ',');
			unset ($this->_payload->existingattachments);
		}

		// convert organizations unit from value to key according to language file
		$this->_payload->{LANG::PROPERTY('order.organizational_unit')} = array_search(UTILITY::propertySet($this->_payload, LANG::PROPERTY('order.organizational_unit')), LANGUAGEFILE['units']);

		// translate payload-names to languagefile keys
		$language = [];
		foreach(array_keys(LANGUAGEFILE['order']) as $key){
			$language[$key] = LANG::PROPERTY('order.' . $key);
		}
		// set data
		foreach ($this->_payload as $key => $value){
			$key = array_search($key, $language);
			if (is_array($value)){
				foreach($value as $index => $subvalue){
					if (boolval($subvalue) && $subvalue !== 'undefined') {
						if (!array_key_exists(intval($index), $order_data['items'])) $order_data['items'][]=[];
						$order_data['items'][intval($index)][$key] = trim(preg_replace(["/\\r/", "/\\n/"], ['', '\\n'], $subvalue));
					}
				}
			} else {
				if (boolval($value) && $value !== 'undefined') $order_data[$key] = trim(preg_replace(["/\\r/", "/\\n/"], ['', '\\n'], $value));
			}
		}
		$order_data['orderer'] = $_SESSION['user']['name'];
		if(!count($order_data['items'])) $this->response([], 406);
		return ['approval' => $approval, 'order_data' => $order_data];
	}

	private function postApprovedOrder($processedOrderData){
		$keys = array_keys($processedOrderData['order_data']);
		$order_data2 = [];
		$query = '';
		for ($i = 0; $i < count($processedOrderData['order_data']['items']); $i++){
			$order_data2 = $processedOrderData['order_data']['items'][$i];
			foreach ($keys as $key){
				if (!in_array($key, ['items', 'organizational_unit'])) $order_data2[$key] = $processedOrderData['order_data'][$key];
			}
			$query .= strtr(SQLQUERY::PREPARE('order_post_approved_order'),
			[
				':order_data' => $this->_pdo->quote(json_encode($order_data2, JSON_UNESCAPED_SLASHES)),
				':organizational_unit' => $this->_pdo->quote($processedOrderData['order_data']['organizational_unit']),
				':approval' => $this->_pdo->quote($processedOrderData['approval']),
				':ordertype' => $this->_pdo->quote('order')
			]) . '; ';
		}
		if (SQLQUERY::EXECUTE($this->_pdo, $query)) {
			$result=[
			'status' => [
				'id' => false,
				'msg' => LANG::GET('order.saved'),
				'type' => 'success'
			]];
			$this->alertUserGroup(['permission'=>['purchase']], LANG::GET('order.alert_purchase'));		
		}
		else $result=[
			'status' => [
				'id' => false,
				'msg' => LANG::GET('order.failed_save'),
				'type' => 'error'
			]];
		return $result;
	}

	public function order(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$processedOrderData = $this->processOrderForm();

				if (!$processedOrderData['approval']){
					SQLQUERY::EXECUTE($this->_pdo, 'order_post_prepared_order', [
						'values' => [
							':order_data' => json_encode($processedOrderData['order_data'], JSON_UNESCAPED_SLASHES)
						]
					]);
					$result = [
						'status' => [
							'id' => $this->_pdo->lastInsertId(),
							'msg' => LANG::GET('order.saved_to_prepared'),
							'type' => 'info'
						]];
					break;
				}
				
				$result = $this->postApprovedOrder($processedOrderData);
				break;
			case 'PUT':
				$processedOrderData = $this->processOrderForm();

				if (!$processedOrderData['approval']){
					SQLQUERY::EXECUTE($this->_pdo, 'order_put_prepared_order', [
						'values' => [
							':order_data' => json_encode($processedOrderData['order_data'], JSON_UNESCAPED_SLASHES),
							':id' => $this->_requestedID
						]
					]);
					$result = [
						'status' => [
							'id' => $this->_requestedID,
							'msg' => LANG::GET('order.saved_to_prepared'),
							'type' => 'info'
						]];
					break;
				}
				
				$result = $this->postApprovedOrder($processedOrderData);

				if ($result['status']['msg'] === LANG::GET('order.saved')){
					SQLQUERY::EXECUTE($this->_pdo, 'order_delete_prepared_orders', [
						'replace' => [
							':id' => intval($this->_requestedID)
						]
					]);
				}
				break;
			case 'GET':
				$datalist = [];
				$datalist_unit = [];
				$vendors = [];

				// prepare existing vendor lists
				$vendor = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
				$vendors[LANG::GET('consumables.edit_product_search_all_vendors')] = ['value' => implode('_', array_map(fn($r) => $r['id'], $vendor))];
				
				foreach($vendor as $key => $row) {
					$datalist[] = $row['name'];
					$vendors[$row['name']] = ['value' => $row['id']];
				}
				// prepare existing unit lists
				$vendor = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product_units');
				foreach($vendor as $key => $row) {
					$datalist_unit[] = $row['article_unit'];
				}

				$order = SQLQUERY::EXECUTE($this->_pdo, 'order_get_prepared_order', [
					'values' => [
						':id' => $this->_requestedID
					]
				]);
				$order = $order ? $order[0] : null;

				if (!$order ){
					$order = [
						'additional_info' => '',
						'organizational_unit' => '',
						'commission' => '',
						'delivery_date' => '',
						'items' => false
					];
				} else {
					$order = json_decode($order['order_data'], true);
				}
				$organizational_units=[];
				foreach(LANGUAGEFILE['units'] as $unit => $description){
					$organizational_units[$description] = ['name' => LANG::PROPERTY('order.organizational_unit'), 'required' => true];
					if (array_key_exists('organizational_unit', $order) && in_array($unit, explode(',', $order['organizational_unit']))) $organizational_units[$description]['checked'] = true;
					elseif (array_key_exists('primaryUnit', $_SESSION['user']['app_settings'])) $organizational_units[LANG::GET('units.' . $_SESSION['user']['app_settings']['primaryUnit'])]['checked'] = true;
				}

				$result['body'] = ['form'=>[
					'data-usecase'=> 'purchase',
					'action' => $this->_requestedID ? "javascript:api.purchase('put', 'order', '" . $this->_requestedID . "')" : "javascript:api.purchase('post', 'order')"
				],
				'content' => [
					[
						['type' => 'scanner',
						'destination' => 'productsearch'
						],
						['type' => 'select',
						'content' => $vendors,
						'attributes' => [
							'id' => 'productsearchvendor',
							'name' => LANG::GET('consumables.edit_product_vendor_select')
							]
						],
						['type' => 'searchinput',
						'attributes' => [
							'name' => LANG::GET('consumables.edit_product_search'),
							'onkeypress' => "if (event.key === 'Enter') {api.purchase('get', 'productsearch', document.getElementById('productsearchvendor').value, this.value); return false;}",
							'onblur' => "if (this.value) {api.purchase('get', 'productsearch', document.getElementById('productsearchvendor').value, this.value); return false;}",
							'id' => 'productsearch'
						]],
						['type' => 'button',
						'attributes' => [
							'value' => LANG::GET('order.add_manually'),
							'type' => 'button',
							'onpointerup' => "new Dialog({type: 'input', header: '". LANG::GET('order.add_manually') ."', body: JSON.parse('".
								json_encode([
									[
										['type' => 'datalist',
										'content' => array_values(array_unique($datalist)),
										'attributes' => [
											'id' => 'vendors'
										]],
										['type' => 'datalist',
										'content' => array_values(array_unique($datalist_unit)),
										'attributes' => [
											'id' => 'units'
										]],
										['type' => 'numberinput',
										'attributes' => [
											'name' => LANG::GET('order.quantity_label'),
										]],
										['type' => 'textinput',
										'attributes' => [
											'name' => LANG::GET('order.unit_label'),
											'list' => 'units'
										]],
										['type' => 'textinput',
										'attributes' => [
											'name' => LANG::GET('order.ordernumber_label')
										]],
										['type' => 'textinput',
										'attributes' => [
											'name' => LANG::GET('order.productname_label')
										]],
										['type' => 'textinput',
										'attributes' => [
											'name' => LANG::GET('order.vendor_label'),
											'list' => 'vendors'
										]]
									]
								])
								."'), 'options':{".
									"'".LANG::GET('order.add_manually_confirm')."': true,".
									"'".LANG::GET('order.add_manually_cancel')."': {value: false, class: 'reducedCTA'},".
								"}}).then(response => {if (Object.keys(response).length) {".
									"_client.order.addProduct(response[LANG.GET('order.quantity_label')] || '', response[LANG.GET('order.unit_label')] || '', response[LANG.GET('order.ordernumber_label')] || '', response[LANG.GET('order.productname_label')] || '', response[LANG.GET('order.barcode_label')] || '', response[LANG.GET('order.vendor_label')] || '');".
									"api.preventDataloss.monitor = true;}".
									"document.getElementById('modal').replaceChildren()})", // clear modal to avoid messing up input names
						]]
					],[
						['type' => 'hr']
					],[
						['type' => 'radio',
						'attributes' => [
							'name' => LANG::GET('order.organizational_unit')
						],
						'content' => $organizational_units
						],
						['type' => 'textinput',
						'hint' => LANG::GET('order.commission_hint'),
						'attributes' => [
							'required' => true,
							'name' => LANG::GET('order.commission'),
							'value' => array_key_exists('commission', $order) ? $order['commission'] : '',
							'data-loss' => 'prevent',
							'id' => 'commission'
						]],
						['type' => 'scanner',
						'destination' => 'commission'
						],
						['type' => 'dateinput',
						'attributes' => [
							'name' => LANG::GET('order.delivery_date'),
							'value' => array_key_exists('delivery_date', $order) ? $order['delivery_date'] : ''
						]],
						['type' => 'textarea',
						'attributes' => [
							'name' => LANG::GET('order.additional_info'),
							'value' => array_key_exists('additional_info', $order) ? $order['additional_info'] : '',
							'data-loss' => 'prevent'
						]]
					],[
						[
							['type' => 'file',
							'attributes' => [
								'name' => LANG::GET('order.attach_file'),
								'multiple' => true
							]]
						],[
							['type' => 'photo',
							'attributes' => [
								'name' => LANG::GET('order.attach_photo'),
								'multiple' => true
							]]
						]
					],
					[
						[
							['type' => 'signature',
							'attributes' => [
								'name' => LANG::GET('order.add_approval_signature')
							]]
						],
						[
							['type' => 'numberinput',
							'attributes' => [
								'name' => LANG::GET('user.edit_order_authorization'),
								'type' => 'password'
							]]
						]
					],
				]];
				if (array_intersect(['group'], $_SESSION['user']['permissions'])){
					array_splice($result['body']['content'][2], 1, 0, [[
							'type' => 'textinput',
							'hint' => LANG::GET('order.orderer_group_hint'),
							'attributes' => [
								'name' => LANG::GET('order.orderer_group_identify'),
								'required' => true,
								'value' => array_key_exists('orderer_group_identify', $order) ? $order['orderer_group_identify'] : '',
							]
						]]
					);
				
				}
				if (array_key_exists('attachments', $order)){
					$files = [];
					foreach(explode(',', $order['attachments']) as $file){
						$files[pathinfo($file)['basename']] = ['href' => $file, 'target' => '_blank'];
					}
					array_splice($result['body']['content'], 4, 0, [
						[
							['type' => 'links',
							'description' => LANG::GET('order.attached_files'),
							'content' => $files],
							['type' => 'hiddeninput',
							'attributes' => [
								'name' => 'existingattachments',
								'value' => $order['attachments']
							]]
						]
					]);
				}

				// cart-content has a twin within utility.js _client.order.addProduct() method
				if ($order['items']){
					$items=[];
					for ($i = 0; $i < count($order['items']); $i++){
						array_push($items,
						[
							[
								'type' => 'numberinput',
								'attributes' => [
									'name' => LANG::GET('order.quantity_label') . '[]',
									'value' => UTILITY::propertySet((object) $order['items'][$i], 'quantity_label') ? : ' ',
									'min' => '1',
									'max' => '99999',
									'required' => true,
									'data-loss' => 'prevent'
								]
							],
							[
								'type' => 'text',
								'description' => LANG::GET('order.added_product', [
									':unit' => UTILITY::propertySet((object) $order['items'][$i], 'unit_label') ? : '',
									':number' => UTILITY::propertySet((object) $order['items'][$i], 'ordernumber_label') ? : '',
									':name' => UTILITY::propertySet((object) $order['items'][$i], 'productname_label') ? : '',
									':vendor' => UTILITY::propertySet((object) $order['items'][$i], 'vendor_label') ? : ''
								])
							],
							[
								'type' => 'hiddeninput',
								'attributes' => [
									'name' => LANG::GET('order.unit_label') . '[]',
									'value' => UTILITY::propertySet((object) $order['items'][$i], 'unit_label') ? : ' '
								]
							],
							[
								'type' => 'hiddeninput',
								'attributes' => [
									'name' => LANG::GET('order.ordernumber_label') . '[]',
									'value' => UTILITY::propertySet((object) $order['items'][$i], 'ordernumber_label') ? : ' '
								]
							],
							[
								'type' => 'hiddeninput',
								'attributes' => [
									'name' => LANG::GET('order.productname_label') . '[]',
									'value' => UTILITY::propertySet((object) $order['items'][$i], 'productname_label') ? : ' '
								]
							],
							[
								'type' => 'hiddeninput',
								'attributes' => [
									'name' => LANG::GET('order.barcode_label') . '[]',
									'value' => UTILITY::propertySet((object) $order['items'][$i], 'barcode_label') ?  : ' '
								]
							],
							[
								'type' => 'hiddeninput',
								'attributes' => [
									'name' => LANG::GET('order.vendor_label') . '[]',
									'value' => UTILITY::propertySet((object) $order['items'][$i], 'vendor_label') ? : ' '
								]
							],
							[
								'type' => 'deletebutton',
								'attributes' => [
									'value' => LANG::GET('order.add_delete'),
									'onpointerup' => 'this.parentNode.remove()'
								]
							]
						]);
					}
					array_splice($result['body']['content'], 2, 0, $items);
				}
				if ($this->_requestedID) array_push($result['body']['content'], [
					['type' => 'deletebutton',
					'attributes' => [
						'value' => LANG::GET('order.delete_prepared_order'),
						'type' => 'button', // apparently defaults to submit otherwise
						'onpointerup' => "new Dialog({type: 'confirm', header: '". LANG::GET('order.delete_prepared_order_confirm_header') ."', 'options':{".
							"'".LANG::GET('order.delete_prepared_order_confirm_cancel')."': false,".
							"'".LANG::GET('order.delete_prepared_order_confirm_ok')."': {value: true, class: 'reducedCTA'},".
							"}}).then(confirmation => {if (confirmation) api.purchase('delete', 'order', " . $this->_requestedID . ")})"
					]]
				]);

				break;
			case 'DELETE':
				if (!(PERMISSION::permissionFor('orderprocessing') || array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']))) $this->response([], 401);
				// delete attachments
				$order = SQLQUERY::EXECUTE($this->_pdo, 'order_get_prepared_order', [
					'values' => [
						':id' => intval($this->_requestedID)
					]
				]);
				$order = $order ? $order[0] : null;
				if (!$order) $this->response([], 404);
				$order = json_decode($order['order_data'], true);
				if (array_key_exists('attachments', $order)){
					$files = explode(',', $order['attachments']);
					UTILITY::delete(array_map(fn($value) => '.' . $value, $files));
				}

				// delete prepared order
				if (SQLQUERY::EXECUTE($this->_pdo, 'order_delete_prepared_orders', [
					'values' => [
						':id' => intval($this->_requestedID)
					]
				])) {
					$result=[
					'status' => [
						'id' => false,
						'msg' => LANG::GET('order.deleted'),
						'type' => 'success'
					]];
				}
				else $result=[
					'status' => [
						'id' => $this->_requestedID,
						'msg' => LANG::GET('order.failed_delete'),
						'type' => 'error'
					]];
				break;
			}
		$this->response($result);
	}

	public function approved(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
			$order = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_id', [
				'values' => [
					':id' => intval($this->_requestedID)
				]
			]);
			$order = $order ? $order[0] : null;
			if (!$order) $this->response(['status' => [ 'id' => $this->_requestedID, 'msg' => LANG::GET('order.not_found'), 'type' => 'error']]);
			if (!(PERMISSION::permissionFor('orderprocessing') || array_intersect(explode(',', $row['organizational_unit']), $_SESSION['user']['units']))) $this->response([], 401);
			if (in_array($this->_subMethod, ['ordered', 'received', 'archived'])){
					switch ($this->_subMethod){
						case 'ordered':
							if ($order['ordertype'] === 'cancellation'){
								if ($this->delete_approved_order($order)) {
									$result = [
									'status' => [
										'id' => false,
										'msg' => LANG::GET('order.deleted'),
										'type' => 'success'
									]];
								}
								else $result = [
									'status' => [
										'id' => $this->_requestedID,
										'msg' => LANG::GET('order.failed_delete'),
										'type' => 'error'
									]];
							}
							elseif ($order['ordertype'] === 'return') {
								SQLQUERY::EXECUTE($this->_pdo, 'order_put_approved_order_received', [
									'values' => [
										':id' => intval($this->_requestedID)
									],
									'replacements' => [
										':state' => $this->_subMethodState === 'true' ? 'CURRENT_TIMESTAMP': 'NULL'
									]
								]);
								$query = 'order_put_approved_order_ordered';
							}
							else $query = 'order_put_approved_order_ordered';
							break;
						case 'received':
							$query = 'order_put_approved_order_received';
							break;
						case 'archived':
							$query = 'order_put_approved_order_archived';
							break;
					}
					SQLQUERY::EXECUTE($this->_pdo, $query, [
						'values' => [
							':id' => $this->_requestedID
						],
						'replacements' => [
							':state' => $this->_subMethodState === 'true' ? 'CURRENT_TIMESTAMP': 'NULL'
						]
					]);
				}
				else {
					$decoded_order_data = json_decode($order['order_data'], true);
					// prepare possible keys
					$prepared = [
						'items' => [[]],
						'additional_info' => null,
						'organizational_unit' => $order['organizational_unit'],
						'commission' => null,
						'orderer' => null,
						'delivery_date' => null,
						'attachments' => null
					];
					// fill possible keys
					foreach ($decoded_order_data as $key => $value){
						if (array_key_exists($key, $prepared)) $prepared[$key] = $value;
						else $prepared['items'][0][$key] = $value;
					}
					// add initially approval date
					$prepared['additional_info'] .= ($prepared['additional_info'] ? "\n": '') . LANG::GET('order.approved_on') . ': ' . $order['approved'];
					// clear unused keys
					foreach ($prepared as $key => $value) {
						if (!$value) unset($prepared[$key]);
					}
					switch ($this->_subMethod){
						case 'disapproved':
							// add to prepared orders
							SQLQUERY::EXECUTE($this->_pdo, 'order_post_prepared_order', [
								'values' => [
									':order_data' => json_encode($prepared, JSON_UNESCAPED_SLASHES)
								]
							]);

							// delete approved order
							SQLQUERY::EXECUTE($this->_pdo, 'order_delete_approved_order', [
								'values' => [
									':id' => intval($this->_requestedID)
								]
							]);
							// inform user group
							$messagepayload=[];
							foreach (['quantity'=> 'quantity_label',
								'unit' => 'unit_label',
								'number' => 'ordernumber_label',
								'name' => 'productname_label',
								'vendor' => 'vendor_label',
								'commission' => 'commission'] as $key => $value){
								$messagepayload[':' . $key] = array_key_exists($value, $decoded_order_data) ? $decoded_order_data[$value] : '';
							}
							$messagepayload[':info'] = array_key_exists('additional_info', $decoded_order_data) ? $decoded_order_data['additional_info'] : '';
							$this->alertUserGroup(['unit' => [$prepared['organizational_unit']]], str_replace('\n', ', ', LANG::GET('order.alert_disapprove_order', [
								':order' => LANG::GET('order.message', $messagepayload),
								':unit' => LANG::GET('units.' . $prepared['organizational_unit']),
								':user' => $_SESSION['user']['name']
							])) . "\n \n" . UTILITY::propertySet($this->_payload, LANG::PROPERTY('message.message')));
							break;
						case 'addinformation':
							if (array_key_exists('additional_info', $decoded_order_data)){
								$decoded_order_data['additional_info'] .= "\n" . UTILITY::propertySet($this->_payload, LANG::PROPERTY('order.additional_info'));
							}
							else $decoded_order_data['additional_info'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('order.additional_info'));
							SQLQUERY::EXECUTE($this->_pdo, 'order_put_approved_order_addinformation', [
								'values' => [
									':order_data' => json_encode($decoded_order_data, JSON_UNESCAPED_SLASHES),
									':id' => intval($this->_requestedID)
								]
							]);
							$this->_subMethod = 'add_information_confirmation';
							if (str_starts_with(UTILITY::propertySet($this->_payload, LANG::PROPERTY('order.additional_info')), LANG::GET('order.orderstate_description'))){
								// inform user group
								$messagepayload = [];
								foreach (['quantity'=> 'quantity_label',
									'unit' => 'unit_label',
									'number' => 'ordernumber_label',
									'name' => 'productname_label',
									'vendor' => 'vendor_label',
									'commission' => 'commission'] as $key => $value){
									$messagepayload[':' . $key] = array_key_exists($value, $decoded_order_data) ? $decoded_order_data[$value] : '';
								}
								$messagepayload[':info'] = array_key_exists('additional_info', $decoded_order_data) ? $decoded_order_data['additional_info'] : '';
								$this->alertUserGroup(['unit' => [$prepared['organizational_unit']]], str_replace('\n', ', ', LANG::GET('order.alert_orderstate_change', [
									':order' => LANG::GET('order.message', $messagepayload),
									':unit' => LANG::GET('units.' . $prepared['organizational_unit']),
									':user' => $_SESSION['user']['name']
								])));
							}
							break;
						case 'cancellation':
							if (array_key_exists('additional_info', $decoded_order_data)){
								$decoded_order_data['additional_info'] .= "\n" . UTILITY::propertySet($this->_payload, LANG::PROPERTY('message.message'));
							}
							else $decoded_order_data['additional_info'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('message.message'));
							$decoded_order_data['additional_info'] .= "\n" . LANG::GET('order.approved_on') . ': ' . $order['approved'];
							$decoded_order_data['orderer'] = $_SESSION['user']['name'];
							SQLQUERY::EXECUTE($this->_pdo, 'order_put_approved_order_cancellation', [
								'values' => [
									':order_data' => json_encode($decoded_order_data, JSON_UNESCAPED_SLASHES),
									':id' => intval($this->_requestedID)
								]
							]);
							$this->alertUserGroup(['permission'=>['purchase']], LANG::GET('order.alert_purchase'));		
							break;
						case 'return':
							if (array_key_exists('additional_info', $decoded_order_data)){
								$decoded_order_data['additional_info'] .= "\n" . UTILITY::propertySet($this->_payload, LANG::PROPERTY('message.message'));
							}
							else $decoded_order_data['additional_info'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('message.message'));
							$decoded_order_data['additional_info'] .= "\n" . LANG::GET('order.approved_on') . ': ' . $order['approved'];
							$decoded_order_data['additional_info'] .= "\n" . LANG::GET('order.received') . ': ' . $order['received'];
							$decoded_order_data['orderer'] = $_SESSION['user']['name'];

							if (SQLQUERY::EXECUTE($this->_pdo, 'order_post_approved_order', [
								'values' => [
								':order_data' => json_encode($decoded_order_data),
								':organizational_unit' => $order['organizational_unit'],
								':approval' => $order['approval'],
								':ordertype' => 'return'
								]
							])) {
								$result = [
								'status' => [
									'id' => false,
									'msg' => LANG::GET('order.saved'),
									'type' => 'success'
								]];
								$this->alertUserGroup(['permission'=>['purchase']], LANG::GET('order.alert_purchase'));
							}
							else $result = [
								'status' => [
									'id' => false,
									'msg' => LANG::GET('order.failed_save'),
									'type' => 'error'
								]];
							break;
					}
				}
				$result = isset($result) ? $result: [
					'status' => [
						'msg' => LANG::GET('order.ora_set', [':type' => LANG::GET('order.' . $this->_subMethod)]),
						'type' => 'info'
					]];
				break;
			case 'GET':
				// delete old received unarchived orders
				$old = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_received', [
					'values' => [
						':date_time' => date('Y-m-d h:i:s', time() - (INI['lifespan']['order'] * 24 * 3600)),
					]
				]);
				foreach ($old as $row){
					$this->delete_approved_order($row);
				}

				$result = ['body' => ['content' => [
					[
						['type' => 'radio',
						'attributes' => [
							'name' => LANG::GET('order.order_filter')
						],
						'content' => [
							LANG::GET('order.untreated')=>['checked' => true, 'onchange' => '_client.order.filter()'],
							LANG::GET('order.ordered')=>['onchange' => '_client.order.filter("ordered")'],
							LANG::GET('order.received')=>['onchange' => '_client.order.filter("received")'],
							LANG::GET('order.archived')=>['onchange' => '_client.order.filter("archived")'],
						]],
						['type' => 'searchinput',
						'attributes' => [
							'name' => LANG::GET('order.order_filter_label'),
							'onkeypress' => "if (event.key === 'Enter') {api.purchase('get', 'filter', this.value); return false;}",
							'onblur' => "api.purchase('get', 'filter', this.value); return false;",
							'id' => 'productsearch'
						]],
						['type' => 'filter']
					]
				]]];
				if (PERMISSION::permissionFor('orderdisplayall')) $units = array_keys(LANGUAGEFILE['units']); // see all orders
				else $units = $_SESSION['user']['units']; // display only orders for own units
					
				// get unincorporated
				$allproducts = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products_incorporation');
				$unincorporated = [];
				$incorporationdenied = [];
				$pendingincorporation = [];
				foreach($allproducts as $product) {
					if ($product['incorporated'] === '') {
						$unincorporated[] = ['id' => $product['id'], 'article_no' => $product['article_no'], 'vendor_name' => $product['vendor_name']];
						continue;
					}
					$product['incorporated'] = json_decode($product['incorporated'], true);
					if (array_key_exists('_denied', $product['incorporated'])) {
						$incorporationdenied[] = ['article_no' => $product['article_no'], 'vendor_name' => $product['vendor_name']];
						continue;
					}
					elseif (!PERMISSION::fullyapproved('incorporation', $product['incorporated'])) {
						$pendingincorporation[] = ['article_no' => $product['article_no'], 'vendor_name' => $product['vendor_name']];
					}
				}

				// get unchecked articles for MDR §14 sample check
				$validChecked = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_valid_checked');
				$notReusableChecked = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_not_reusable_checked');
				$sampleCheck = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_eligible_sample_check', ['replacements' => [
					':valid_checked' => implode(',', array_column($validChecked, 'vendor_id')),
					':not_reusable' => implode(',', array_column($notReusableChecked, 'id'))
				]]);

				$statechange = [];
				foreach(LANGUAGEFILE['order']['orderstate'] as $value){
					$statechange[$value] = [];
				}

				$order = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_unit', [
					'replacements' => [
						':organizational_unit' => implode(",", $units)
					]
				]);
				foreach($order as $row) {
					$content = [];
					$text = "\n";
					$decoded_order_data = json_decode($row['order_data'], true);
					if (array_key_exists('barcode_label', $decoded_order_data) && strlen($decoded_order_data['barcode_label'])) $content[] = [
						'type' => 'image',
						'attributes' => [
							'barcode' => ['value' => $decoded_order_data['barcode_label']],
							'imageonly' => ['width' => '15em', 'height' => '6em']
							]
					];
					
					$content[]=
						['type' => 'hiddeninput',
						'description' => 'filter',
						'attributes' => ['data-filtered' => $row['id']]];

					$text .= LANG::GET('order.prepared_order_item', [
						':quantity' => UTILITY::propertySet((object) $decoded_order_data, 'quantity_label') ? : '',
						':unit' => UTILITY::propertySet((object) $decoded_order_data, 'unit_label') ? : '',
						':number' => UTILITY::propertySet((object) $decoded_order_data, 'ordernumber_label') ? : '',
						':name' => UTILITY::propertySet((object) $decoded_order_data, 'productname_label') ? : '',
						':vendor' => UTILITY::propertySet((object) $decoded_order_data, 'vendor_label') ? : ''
					])."\n";

					if ($additional_information = UTILITY::propertySet((object) $decoded_order_data, 'additional_info'))
						$text .= LANG::GET('order.additional_info') . ': ' . $additional_information . "\n \n";
					$text .= LANG::GET('order.organizational_unit') . ': ' . LANG::GET('units.' . $row['organizational_unit']) . "\n";
					if ($orderer_group_identify = UTILITY::propertySet((object) $decoded_order_data, 'orderer_group_identify'))
						$text .= LANG::GET('order.orderer_group_identify') . ': ' . $orderer_group_identify . "\n";
					$text .= LANG::GET('order.approved') . ': ' . $row['approved'] . ' ';
					if (!str_contains($row['approval'], 'data:image/png')) $text .= $row['approval'] . "\n";

					$copy = [
						[
							'type' => 'textinput',
							'attributes' => [
								'value' => UTILITY::propertySet((object) $decoded_order_data, 'ordernumber_label') ? : '',
								'name' => LANG::GET('order.ordernumber_label'),
								'readonly' => true,
								'onpointerup' => '_client.order.toClipboard(this)'
							]
						],
						[
							'type' => 'textinput',
							'attributes' => [
								'value' => UTILITY::propertySet((object) $decoded_order_data, 'commission') ? : '',
								'name' => LANG::GET('order.commission'),
								'readonly' => true,
								'onpointerup' => '_client.order.toClipboard(this)'
							],
							'hint' => LANG::GET('order.copy_values')
						],
					];

					$status = [];
					foreach(['ordered', 'received', 'archived'] as $s){
						$status[LANG::GET('order.' . $s)] = [
							'onchange' => "api.purchase('put', 'approved', " . $row['id']. ", '" . $s . "', this.checked); this.setAttribute('data-".$s."', this.checked.toString());",
							'data-' . $s => boolval($row[$s]) ? 'true' : 'false',
						];
						if (boolval($row[$s])) {
							$status[LANG::GET('order.' . $s)]['checked'] = true;
							$text .= "\n" . LANG::GET('order.' . $s) . ': ' . $row[$s];
						}
						switch ($s){
							case 'ordered':
								if (!PERMISSION::permissionFor('orderprocessing')){
									$status[LANG::GET('order.' . $s)]['disabled'] = true;
								}
								break;
							case 'received':
							case 'archived':
								if (!(array_intersect(['admin'], $_SESSION['user']['permissions']) || array_intersect([$row['organizational_unit']], $_SESSION['user']['units']))){
									$status[LANG::GET('order.' . $s)]['disabled'] = true;
								}
								break;
						}
					}
					if (!($row['ordered'] || $row['received']) && $row['ordertype'] === 'order') $status[LANG::GET('order.disapprove')]=[
						'data_disapproved' => 'false',
						'onchange' => "new Dialog({type:'input', header:'" . LANG::GET('order.disapprove') . "', body:JSON.parse('" . 
							json_encode(
								[
									['type' => 'textarea',
									'attributes' => [
										'name' => LANG::GET('message.message')
									],
									'hint' => LANG::GET('order.disapprove_message', [':unit' => LANG::GET('units.' . $row['organizational_unit'])])
									]
								]
							 ) . "'), " .
							"options:{'" . LANG::GET('order.disapprove_message_cancel') . "': false, '" . LANG::GET('order.disapprove_message_ok') . "': {value: true, class: 'reducedCTA'}}}).then(response => {" .
							"if (response !== false) {" .
							"api.purchase('put', 'approved', " . $row['id']. ", 'disapproved', _client.application.dialogToFormdata(response)); this.disabled=true; this.setAttribute('data-disapproved', 'true');" .
							"} else this.checked = false;});"
					];
					if ($row['ordered'] && !$row['received'] && (PERMISSION::permissionFor('ordercancel') || array_intersect([$row['organizational_unit']], $_SESSION['user']['units']))) $status[LANG::GET('order.cancellation')]=[
						'data_cancellation' => 'false',
						'onchange' => "new Dialog({type:'input', header:'" . LANG::GET('order.cancellation') . "', body:JSON.parse('" . 
							json_encode(
								[
									['type' => 'textarea',
									'attributes' => [
										'name' => LANG::GET('message.message')
									],
									'hint' => LANG::GET('order.cancellation_message')
									]
								]
							 ) . "'), " .
							"options:{'" . LANG::GET('order.cancellation_message_cancel') . "': false, '" . LANG::GET('order.cancellation_message_ok') . "': {value: true, class: 'reducedCTA'}}}).then(response => {" .
							"if (response !== false) {" .
							"api.purchase('put', 'approved', " . $row['id']. ", 'cancellation', _client.application.dialogToFormdata(response)); this.disabled=true; this.setAttribute('data-cancellation', 'true');" .
							"} else this.checked = false;});"
					];
					if ($row['received'] && $row['ordertype'] === 'order' && (PERMISSION::permissionFor('ordercancel') || array_intersect([$row['organizational_unit']], $_SESSION['user']['units']))) $status[LANG::GET('order.return')]=[
						'data_return' => 'false',
						'onchange' => "new Dialog({type:'input', header:'" . LANG::GET('order.return') . "', body:JSON.parse('" . 
							json_encode(
								[
									['type' => 'textarea',
									'attributes' => [
										'name' => LANG::GET('message.message')
									],
									'hint' => LANG::GET('order.return_message')
									]
								]
							 ) . "'), " .
							"options:{'" . LANG::GET('order.return_message_cancel') . "': false, '" . LANG::GET('order.return_message_ok') . "': {value: true, class: 'reducedCTA'}}}).then(response => {" .
							"if (response !== false) {" .
							"api.purchase('put', 'approved', " . $row['id']. ", 'return', _client.application.dialogToFormdata(response)); this.disabled=true; this.setAttribute('data-cancellation', 'true');" .
							"} else this.checked = false;});"
					];

					$content[] = [
						'type' => 'text',
						'content' => LANG::GET('order.ordertype.' . $row['ordertype']) . "\n" . $text,
					];
					if (str_contains($row['approval'], 'data:image/png')){
						$content[]=[
							'type' => 'image',
							'attributes' => [
								'imageonly' => ['width' => '10em', 'height' => '6em', 'margin-top' => '-4em'],
								'name' => LANG::GET('order.approval_image'),
								'url' => $row['approval']],
						];
					}

					$messagepayload=[];
					foreach (['quantity'=> 'quantity_label',
						'unit' => 'unit_label',
						'number' => 'ordernumber_label',
						'name' => 'productname_label',
						'vendor' => 'vendor_label',
						'commission' => 'commission'] as $key => $value){
						$messagepayload[':' . $key] = array_key_exists($value, $decoded_order_data) ? $decoded_order_data[$value] : '';
					}
					$messagepayload[':info'] = array_key_exists('.additional_info', $decoded_order_data) ? $decoded_order_data['additional_info']: '';
					$messageorderer = UTILITY::propertySet((object) $decoded_order_data, 'orderer') ? : '';

					$content[] = [
						'type' => 'links',
						'content' => [
							LANG::GET('order.message_orderer', [':orderer' => $messageorderer]) => ['href' => 'javascript:void(0)', 'data-type' => 'input', 'onpointerup' => "
							_client.message.newMessage('". LANG::GET('order.message_orderer', [':orderer' => $messageorderer]) ."', '" . 
							$messageorderer . "', '" . 
							LANG::GET('order.message', $messagepayload) . "', {".
								"'".LANG::GET('order.add_information_cancel')."': false,".
								"'".LANG::GET('order.message_to_orderer')."': {value: true, class: 'reducedCTA'},".
								"})"]
						]
					];
					$content[] = $copy;

					if (array_key_exists('attachments', $decoded_order_data)){
						$files = [];
						foreach(explode(',', $decoded_order_data['attachments']) as $file){
							$files[pathinfo($file)['basename']] = ['href' => $file, 'target' => '_blank'];
						}
						$content[]=[
							['type' => 'links',
							'description' => LANG::GET('order.attached_files'),
							'content' => $files]
						];
					}

					if (PERMISSION::permissionFor('orderaddinfo') || array_intersect([$row['organizational_unit']], $units)) $content[]=[
						'type' => 'button',
						'attributes' => [
							'value' => LANG::GET('order.add_information'),
							'type' => 'button',
							'onpointerup' => "new Dialog({type: 'input', header: '". LANG::GET('order.add_information') ."', body: JSON.parse('" . 
								json_encode(
									[
										[
											'type' => 'textarea',
											'attributes' => [
												'name' => LANG::GET('order.additional_info')
											],
											'hint' => LANG::GET('order.add_information_modal_body')
										]
									]
								 ) . "'), options:{".
								"'".LANG::GET('order.add_information_cancel')."': false,".
								"'".LANG::GET('order.add_information_ok')."': {value: true, class: 'reducedCTA'},".
								"}}).then(response => {if (response) api.purchase('put', 'approved', " . $row['id']. ", 'addinformation', _client.application.dialogToFormdata(response))})"
						]
					];

					$content[] = [
						'type' => 'checkbox',
						'content' => $status
					];
					$autodelete='';
					if ($row['received'] && !$row['archived']){
						$autodelete = LANG::GET('order.autodelete', [':date' => date('Y-m-d', strtotime($row['received']) + (INI['lifespan']['order'] * 24 * 3600))]);
					}

					// add statechange if applicable
					if ($row['ordered'] && !$row['received'] && (PERMISSION::permissionFor('orderaddinfo') || array_intersect([$row['organizational_unit']], $units))) {
						$content[] = [
							'type' => 'select',
							'content' => $statechange,
							'attributes' => [
								'name' => LANG::GET('order.orderstate_description'),
								'onchange' => "new Dialog({type: 'input', header: LANG.GET('order.orderstate_description') + ' ' + this.value, body: JSON.parse('" . 
									json_encode(
										[
											[
												'type' => 'textarea',
												'attributes' => [
													'name' => LANG::GET('order.additional_info')
												],
												'hint' => LANG::GET('order.disapprove_message', [':unit' => LANG::GET('units.' . $row['organizational_unit'])])
												]
										]
									 ) . "'), options:{".
									"'".LANG::GET('order.add_information_cancel')."': false,".
									"'".LANG::GET('order.add_information_ok')."': {value: true, class: 'reducedCTA'},".
									"}}).then(response => {if (response) {response[LANG.GET('order.additional_info')] = LANG.GET('order.orderstate_description') + ' - ' + this.value + ': ' + response[LANG.GET('order.additional_info')]; api.purchase('put', 'approved', " . $row['id']. ", 'addinformation', _client.application.dialogToFormdata(response))}})"
							]
						];
					}

					// incorporation state
					if (array_key_exists('ordernumber_label', $decoded_order_data) && ($tocheck = array_search($decoded_order_data['ordernumber_label'], array_column($unincorporated, 'article_no'))) !== false){
						if (array_key_exists('vendor_label', $decoded_order_data) && $unincorporated[$tocheck]['vendor_name'] === $decoded_order_data['vendor_label']){
							if (!in_array('group', $_SESSION['user']['permissions'])){
								$content[] = [
									'type' => 'button',
									'attributes' => [
										'value' => LANG::GET('order.incorporation'),
										'type' => 'button',
										'onpointerup' => "if (!this.disabled) api.purchase('get', 'incorporation', " . $unincorporated[$tocheck]['id'] . "); this.disabled=true"
									]
								];
							} else {
								// simple groups are not allowed to make records
								$content[] = [
									'type' => 'text',
									'description' => LANG::GET('order.incorporation_neccessary_by_user')
								];
							}
						}
					}
					elseif (array_key_exists('ordernumber_label', $decoded_order_data) && ($tocheck = array_search($decoded_order_data['ordernumber_label'], array_column($incorporationdenied, 'article_no'))) !== false){
						if (array_key_exists('vendor_label', $decoded_order_data) && $incorporationdenied[$tocheck]['vendor_name'] === $decoded_order_data['vendor_label']){
							$content[] = [
								'type' => 'text',
								'description' => LANG::GET('order.incorporation_denied')
							];
						}
					}
					elseif (array_key_exists('ordernumber_label', $decoded_order_data) && ($tocheck = array_search($decoded_order_data['ordernumber_label'], array_column($pendingincorporation, 'article_no'))) !== false){
						if (array_key_exists('vendor_label', $decoded_order_data) && $pendingincorporation[$tocheck]['vendor_name'] === $decoded_order_data['vendor_label']){
							$content[] = [
								'type' => 'text',
								'description' => LANG::GET('order.incorporation_pending')
							];
						}
					}
					
					// request MDR §14 sample check
					if (!in_array('group', $_SESSION['user']['permissions']) && array_key_exists('ordernumber_label', $decoded_order_data) && ($tocheck = array_search($decoded_order_data['ordernumber_label'], array_column($sampleCheck, 'article_no'))) !== false){
						if (array_key_exists('vendor_label', $decoded_order_data) && $sampleCheck[$tocheck]['vendor_name'] === $decoded_order_data['vendor_label']){
							if (!in_array('group', $_SESSION['user']['permissions'])){
									$content[] = [
									'type' => 'button',
									'attributes' => [
										'value' => LANG::GET('order.sample_check'),
										'type' => 'button',
										'onpointerup' => "if (!this.disabled) api.purchase('get', 'mdrsamplecheck', " . $sampleCheck[$tocheck]['id'] . "); this.disabled=true"
									]
								];
							} else {
								// simple groups are not allowed to make records
								$content[] = [
									'type' => 'text',
									'description' => LANG::GET('order.sample_check_by_user')
								];
							}
						}
					}

					// delete order button if authorized
					if (PERMISSION::permissionFor('ordercancel') || array_intersect([$row['organizational_unit']], $_SESSION['user']['units'])) {
						$content[] = [
							'type' => 'deletebutton',
							'hint' => $autodelete,
							'attributes' => [
								'type' => 'button',
								'value' => LANG::GET('order.delete_prepared_order'),
								'onpointerup' => "new Dialog({type: 'confirm', header: '". LANG::GET('order.delete_prepared_order_confirm_header') ."', options:{".
									"'".LANG::GET('order.delete_prepared_order_confirm_cancel')."': false,".
									"'".LANG::GET('order.delete_prepared_order_confirm_ok')."': {value: true, class: 'reducedCTA'},".
									"}}).then(confirmation => {if (confirmation) api.purchase('delete', 'approved', " . $row['id'] . ")})"
		
							]
						];
						$content[] = [
							'type' => 'br' // to clear after floating delete button
						];
					}
					array_push($result['body']['content'], $content);
				}
				break;
			case 'DELETE':
				$row = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_id', [
					'values' => [
						':id' => $this->_requestedID
					]
				]);
				$row = $row ? $row[0] : null;
				
				if ($row && $this->delete_approved_order($row)) {
					$result = [
					'status' => [
						'id' => false,
						'msg' => LANG::GET('order.deleted'),
						'type' => 'success'
					]];
				}
				else $result = [
					'status' => [
						'id' => $this->_requestedID,
						'msg' => LANG::GET('order.failed_delete'),
						'type' => 'error'
					]];
				break;
			}
		$this->response($result);
	}

	private function delete_approved_order($row){
		// delete order and attachments if not used by any other approved order
		$order = json_decode($row['order_data'], true);
		if (array_key_exists('attachments', $order)){
			$others = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_substr', [
				'values' => [
					':substr' => $order['attachments']
				]
			]);
			if (count($others)<2){
				$files = explode(',', $order['attachments']);
				UTILITY::delete(array_map(fn($value) => '.' . $value, $files));
			}
		}
		return SQLQUERY::EXECUTE($this->_pdo, 'order_delete_approved_order', [
			'values' => [
				':id' => $row['id']
			]
		]);
	}
}
?>
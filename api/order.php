<?php 

class ORDER extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_subMethod = null;

	private $fields = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? (REQUEST[2] != 'false' ? REQUEST[2]: null) : null;
		$this->_subMethod = array_key_exists(3, REQUEST) ? REQUEST[3] : null;

		$this->fields=[
			'name' => LANG::GET('order.productname_label'),
			'unit' => LANG::GET('order.unit_label'),
			'number' => LANG::GET('order.ordernumber_label'),
			'quantity' => LANG::GET('order.quantity_label'),
			'vendor' => LANG::GET('order.vendor_label'),
			'orderer' => LANG::GET('order.orderer'),
			'organizational_unit' => LANG::GET('order.unit'),
			'commission' => LANG::GET('order.commission'),
			'deliverydate' => LANG::GET('order.delivery_date'),
			'info' => LANG::GET('order.additional_info'),
			'barcode' => LANG::GET('order.barcode'),
		];
	}

	public function prepared(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'GET':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get-prepared-orders'));
				$statement->execute();
				$orders = $statement->fetchAll(PDO::FETCH_ASSOC);
				// display all orders assigned to organizational unit
				if (array_intersect(['admin'], $_SESSION['user']['permissions'])) $units = LANGUAGEFILE['units']; // see all orders
				else { // see only orders for own units
					$units = [];
					foreach($_SESSION['user']['units'] as $unit){
						$units[] = LANG::GET('units.' . $unit);
					}
				}
				$organizational_orders=[];
				foreach($orders as $key => $row) {
					$order_data=json_decode($row['order_data'], true);
					if (array_intersect([$order_data['organizational_unit']], $units)) {
						array_push($organizational_orders, $row);
					}
				}
				$result=['body' => ['content' => []]];
				foreach($organizational_orders as $order){ // order
					$text = '';
					foreach (json_decode($order['order_data'], true) as $key => $value){ // data
						if (is_array($value)){
							foreach($value as $index => $item){ // items
								foreach ($item as $itemkey => $itemvalue){
									$text .= $itemkey . ': ' . $itemvalue . '\n';
								}
								$text .= '\n';
							}

						} else {
							$text .= $key . ': ' . $value . '\n';
						}
					}
					array_push($result['body']['content'], [
						['type' => 'text',
						'content' => $text,
						],
						['type' => 'button',
						'description' => LANG::GET('order.edit_prepared_order'),
						'attributes' =>['type' => 'button',
						'onpointerup' => "api.purchase('get', 'order', " . $order['id']. ")"]]
					]);
				}
			break;
		}
		$this->response($result);
	}
	public function productsearch(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'GET':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$result = ['body'=>[]];

				$matches = [];
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get-product-search'));
				$statement->execute([
					':search' => $this->_requestedID
				]);
				$search = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($search as $key => $row) {
					foreach($row as $key => $value){
						$row[$key]=str_replace("\n", ' ', $row[$key]);
					}
					$matches[$row['vendor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit'] . ' ' . $row['article_ean']] = ['href' => 'javascript:void(0);', 'data-filtered' => 'breakline', 'onpointerup' => "orderClient.addProduct('" . $row['article_unit'] . "', '" . $row['vendor_name'] . "', '" . $row['article_no'] . "', '" . $row['article_name'] . "', '" . $row['article_ean'] . "'); return false;"];
				}
				$result['body']['content'] = [
					[
						['type' => 'links',
						'description' => LANG::GET('order.add_product_search_matches', [':number' => count($matches)]),
						'content' => $matches
						]
					]
				];
				break;
			}
		$this->response($result);
	}

	public function filter(){
		if (array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions'])) $displayunits = LANGUAGEFILE['units']; // see all orders
		else { // display only orders for own units
			$displayunits = [];
			foreach($_SESSION['user']['units'] as $unit){
				$displayunits[] = LANG::GET('units.' . $unit);
			}
		}
		// in clause doesnt work without manually preparing
		$query = strtr(SQLQUERY::PREPARE('order_get_filter'),
		[
			':organizational_unit' => "'".implode("','", $displayunits)."'",
			':orderfilter' => "'" . $this->_requestedID . "'"
		]);
		$statement = $this->_pdo->prepare($query);
		$statement->execute();
		$filtered = $statement->fetchAll(PDO::FETCH_ASSOC);
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

		$approval = false;
		if (UTILITY::propertySet($this->_payload, 'approval_token')){
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('application_login'));
			$statement->execute([
				':token' => $this->_payload->approval_token
			]);
			if ($result = $statement->fetch(PDO::FETCH_ASSOC)){
				$approval = $result['name'] . LANG::GET('order.token_verified');
			}
		}

		if (array_key_exists('signature', $_FILES) && $_FILES['signature']['tmp_name']){
			$signature = gettype($_FILES['signature']['tmp_name'])=='array' ? $_FILES['signature']['tmp_name'][0] : $_FILES['signature']['tmp_name'];
			$approval = 'data:image/png;base64,' . base64_encode(UTILITY::resizeImage($signature, 256, UTILITY_IMAGE_RESOURCE, 'png'));
		}
		$unset=LANG::PROPERTY('consumables.add_approval_token');
		unset ($this->_payload->$unset);
		$order_data=['items'=>[]];
		foreach ($this->_payload as $key => $value){
			if (is_array($value)){
				foreach($value as $index => $subvalue){
					if (boolval($subvalue)) $order_data['items'][intval($index)][$key] = $subvalue;
				}
			} else {
				if (boolval($value)) $order_data[$key] = $value;
			}
		}
		$order_data['orderer']=$_SESSION['user']['name'];

		if(!count($order_data['items'])) $this->response([], 406);
		return ['approval' => $approval, 'order_data' => $order_data];
	}

	private function postApprovedOrder($processedOrderData){
		$keys = array_keys($processedOrderData['order_data']);
		$order_data2 = [];
		$query = '';
		for ($i = 0; $i<count($processedOrderData['order_data']['items']);$i++){
			$order_data2 = $processedOrderData['order_data']['items'][$i];
			foreach ($keys as $key){
				if (!in_array($key, ['items','organizational_unit'])) $order_data2[$key] = $processedOrderData['order_data'][$key];
			}
			$query .= strtr(SQLQUERY::PREPARE('order_post-approved-order'),
			[
				':order_data' => "'" . json_encode($order_data2) . "'",
				':organizational_unit' => "'" . $processedOrderData['order_data']['organizational_unit'] . "'",
				':approval' => "'" . $processedOrderData['approval'] . "'",
			]) . '; ';
		}
		$statement = $this->_pdo->prepare($query);
		if ($statement->execute()) {
			$result=[
			'status' => [
				'id' => false,
				'msg' => LANG::GET('order.saved')
			]];
			$this->alertUserGroup('purchase', LANG::GET('order.alert_purchase'), 'permission');		
		}
		else $result=[
			'status' => [
				'id' => false,
				'msg' => LANG::GET('order.failed_save')
			]];
		return $result;
	}

	public function order(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);

				$processedOrderData =$this->processOrderForm();

				if (!$processedOrderData['approval']){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_post-prepared-order'));
					$statement->execute([
						':order_data' => json_encode($processedOrderData['order_data'])
					]);
					$result=[
						'status' => [
							'id' => $this->_pdo->lastInsertId(),
							'msg' => LANG::GET('order.saved_to_prepared')
						]];
					break;
				}
				
				$result = $this->postApprovedOrder($processedOrderData);
				break;
			case 'PUT':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$processedOrderData = $this->processOrderForm();

				if (!$processedOrderData['approval']){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_put-prepared-order'));
					$statement->execute([
						':order_data' => json_encode($processedOrderData['order_data']),
						':id' => $this->_requestedID
					]);
					$result=[
						'status' => [
							'id' => $this->_requestedID,
							'msg' => LANG::GET('order.saved_to_prepared')
						]];
					break;
				}
				
				$result = $this->postApprovedOrder($processedOrderData);

				if ($result['status']['msg']==LANG::GET('order.saved')){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_delete-prepared-order'));
					$statement->execute([
						':id' => $this->_requestedID
					]);
				}
				break;
			case 'GET':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$datalist = [];
				$datalist_unit = [];

				// prepare existing vendor lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-vendor-datalist'));
				$statement->execute();
				$vendor = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($vendor as $key => $row) {
					$datalist[] = $row['name'];
				}
				// prepare existing unit lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-product-units'));
				$statement->execute();
				$vendor = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($vendor as $key => $row) {
					$datalist_unit[] = $row['article_unit'];
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get-prepared-order'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				if (!$order = $statement->fetch(PDO::FETCH_ASSOC)){$order = [
					'info' => '',
					'organizational_unit' => '',
					'commission' => '',
					'deliverydate' => '',
					'items' => false
				];} else {
					$order = json_decode($order['order_data'], true);
				}
				$organizational_units=[];
				foreach(LANGUAGEFILE['units'] as $unit => $description){
					$organizational_units[$description] = ['name' => 'organizational_unit', 'required' => true];
					if (array_key_exists('organizational_unit', $order) && in_array($description, explode(',', $order['organizational_unit']))) $organizational_units[$description]['checked'] = true;
				}

				$result['body'] = ['form'=>[
					'data-usecase'=> 'purchase',
					'action' => $this->_requestedID ? 'javascript:api.purchase("put", "order", "' . $this->_requestedID . '")' : 'javascript:api.purchase("post", "order")'
				],
				'content' => [
					[
						['type' => 'scanner',
						'description' => LANG::GET('consumables.edit_product_search_scan'),
						'destination' => LANG::GET('consumables.edit_product_search')
						],
						['type' => 'searchinput',
						'attributes' => [
							'name' => LANG::GET('consumables.edit_product_search'),
							'value' => $this->_requestedID && $this->_subMethod === 'search' ? $this->_requestedID : '',
							'onkeypress' => "if (event.key === 'Enter') {api.purchase('get', 'productsearch', this.value); return false;}",
							'onblur' => "if (this.value) {api.purchase('get', 'productsearch', this.value); return false;}",
							'id' => 'productsearch'
						]]
					],[
						['type' => 'hr']
					],[
						['type' => 'datalist',
						'content' => $datalist,
						'attributes' => [
							'id' => 'vendors'
						]],
						['type' => 'datalist',
						'content' => $datalist_unit,
						'attributes' => [
							'id' => 'units'
						]]
					],[
						['type' => 'numberinput',
						'attributes' => [
							'name' => LANG::GET('order.quantity_label') . '[]',
							'min' => '1',
							'max' => '99999',
							'onblur' => 'orderClient.required(this.parentNode)',
							'data-loss' => 'prevent'
						]],
						['type' => 'textinput',
						'attributes' => [
							'name' => LANG::GET('order.unit_label') . '[]',
							'list' => 'units',
							'onblur' => 'orderClient.required(this.parentNode)',
							'data-loss' => 'prevent'
						]],
						['type' => 'textinput',
						'attributes' => [
							'name' => LANG::GET('order.vendor_label') . '[]',
							'list' => 'vendors',
							'onblur' => 'orderClient.required(this.parentNode)',
							'data-loss' => 'prevent'
						]],
						['type' => 'textinput',
						'attributes' => [
							'name' => LANG::GET('order.ordernumber_label') . '[]',
							'onblur' => 'orderClient.required(this.parentNode)',
							'data-loss' => 'prevent'
						]],
						['type' => 'textinput',
						'attributes' => [
							'name' => LANG::GET('order.productname_label') . '[]',
							'onblur' => 'orderClient.required(this.parentNode)',
							'data-loss' => 'prevent'
						]],
						['type' => 'hiddeninput',
						'attributes' => [
							'name' => LANG::GET('order.barcode') . '[]',
							'value' => '' // otherwise undefined messes up
						]],
						['type' => 'button',
						'attributes' => [
							'value' => LANG::GET('order.add_button'),
							'type' => 'button',
							'onpointerup' => 'orderClient.cloneNew(this.parentNode)'
						]]
					],[
						['type' => 'textarea',
						'attributes' => [
							'name' => LANG::GET('order.additional_info'),
							'value' => array_key_exists('info', $order) ? $order['info'] : '',
							'data-loss' => 'prevent'
						]],
						['type' => 'radio',
						'description' => LANG::GET('order.unit'),
						'content' => $organizational_units
						],
						['type' => 'scanner',
						'attributes' => [
							'required' => true,
							'name' => LANG::GET('order.add_commission_label'),
							'value' => array_key_exists('commission', $order) ? $order['commission'] : '',
							'data-loss' => 'prevent'
						]],
						['type' => 'dateinput',
						'attributes' => [
							'name' => LANG::GET('order.delivery_date'),
							'value' => array_key_exists('deliverydate', $order) ? $order['deliverydate'] : ''
						]]
					],[
						[
							['type' => 'signature',
							'description' => LANG::GET('order.add_approval_signature'),
							'attributes' => [
								'name' => 'approval_signature'
							]]
						],
						[
							['type' => 'scanner',
							'attributes' => [
								'name' => LANG::GET('order.add_approval_token'),
								'type' => 'password'
							]]
						]
					],
				]];
				if ($order['items']){
					$items=[];
					for ($i = 0; $i < count($order['items']); $i++){
						array_push($items,
						[
							['type' => 'numberinput',
							'attributes' => [
								'min' => '1',
								'max' => '99999',
								'name' => LANG::GET('order.quantity_label') . '[]',
								'onblur' => 'orderClient.required(this.parentNode)',
								'value' => $order['items'][$i]['quantity'],
								'data-loss' => 'prevent'
								]],
							['type' => 'textinput',
							'attributes' => [
								'list' => 'units',
								'name' => LANG::GET('order.unit_label') . '[]',
								'onblur' => 'orderClient.required(this.parentNode)',
								'value' => $order['items'][$i]['unit'],
								'data-loss' => 'prevent'
								]],
							['type' => 'textinput',
							'attributes' => [
								'list' => 'vendors',
								'name' => LANG::GET('order.vendor_label') . '[]',
								'onblur' => 'orderClient.required(this.parentNode)',
								'value' => $order['items'][$i]['vendor'],
								'data-loss' => 'prevent'
								]],
							['type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('order.ordernumber_label') . '[]',
								'onblur' => 'orderClient.required(this.parentNode)',
								'value' => $order['items'][$i]['number'],
								'data-loss' => 'prevent'
								]],
							['type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('order.productname_label') . '[]',
								'onblur' => 'orderClient.required(this.parentNode)',
								'value' => $order['items'][$i]['name'],
								'data-loss' => 'prevent'
								]],
							['type' => 'hiddeninput',
							'attributes' => [
								'name' => LANG::GET('order.barcode') . '[]',
								'value' => array_key_exists ('barcode', $order['items'][$i]) ? $order['items'][$i]['barcode'] : ''
							]],
							['type' => 'button',
							'attributes' => [
								'value' => LANG::GET('order.add_delete'),
								'type' => 'button',
								'onpointerup' => 'this.parentNode.remove()'
							]]
						]);
					}
					array_splice($result['body']['content'], 3, 0, $items);
				}
				if ($this->_requestedID) array_push($result['body']['content'], [
					['type' => 'deletebutton',
					'description' => LANG::GET('order.delete_prepared_order'),
					'attributes' => [
						'type' => 'button', // apparently defaults to submit otherwise
						'onpointerup' => 'if (confirm("'. LANG::GET('order.delete_prepared_order_confirm') .'")) {api.purchase("delete", "order", ' . $this->_requestedID . ')}'
					]]
				]);

				break;
			case 'DELETE':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_delete-prepared-order'));
				if ($statement->execute([
					':id' => $this->_requestedID
					])) {
					$result=[
					'status' => [
						'id' => false,
						'msg' => LANG::GET('order.deleted')
					]];
				}
				else $result=[
					'status' => [
						'id' => $this->_requestedID,
						'msg' => LANG::GET('order.failed_delete')
					]];
				break;
			}
		$this->response($result);
	}
	public function approved(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				if ($this->_subMethod == 'ordered') $statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_put-approved-order-ordered'));
				if ($this->_subMethod == 'received') $statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_put-approved-order-received'));
				if ($this->_subMethod == 'archived') $statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_put-approved-order-archived'));
				if ($this->_subMethod == 'disapproved') $statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get-approved-order-by-id'));
				$statement->execute([
					':id' => $this->_requestedID
					]);
				if ($this->_subMethod == 'disapproved'){
					$order = $statement->fetch(PDO::FETCH_ASSOC);
					$decoded_order_data = json_decode($order['order_data'], true);

					// prepare possible keys
					$prepared = [
						'items' => [[]],
						'info' => null,
						'organizational_unit' => $order['organizational_unit'],
						'commission' => null,
						'orderer' => null,
						'deliverydate' => null
					];
					// fill possible keys
					foreach ($decoded_order_data as $key => $value){
						if (array_key_exists($key, $prepared)) $prepared[$key] = $value;
						else $prepared['items'][0][$key] = $value;
					}
					// add initially approval date
					$prepared['info'] .= ($prepared['info'] ? "\n": '') . LANG::GET('order.initially_approved') . ': ' . $order['approved'];
					// clear unused keys
					foreach ($prepared as $key => $value) {
						if (!$value) unset($prepared[$key]);
					}
					// add to prepared orders
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_post-prepared-order'));
					$statement->execute([
						':order_data' => json_encode($prepared)
					]);
					// delete approved order
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_delete-approved-order'));
					$statement->execute([
						':id' => $this->_requestedID
					]);
					// inform user group
					$messagepayload=[];
					foreach (['quantity', 'unit', 'number', 'name', 'vendor', 'commission'] as $key){
						if (array_key_exists($key, $decoded_order_data)) $messagepayload[':' . $key] = $decoded_order_data[$key];
					}
					$this->alertUserGroup(array_search($order['organizational_unit'], LANGUAGEFILE['units']), LANG::GET('order.alert_disapprove_order',[
						':order' => LANG::GET('order.message', $messagepayload),
						':unit' => $order['organizational_unit'],
						':user' => $_SESSION['user']['name']
					]), 'unit');
				}
				$result=[
					'status' => [
						'msg' => LANG::GET('order.' . $this->_subMethod)
					]];
				break;
			case 'GET':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$result=['body'=>['content'=>[
					[
						['type' => 'radio',
						'content' => [
							LANG::GET('order.untreated')=>['checked' => true, 'onfocus' => 'orderClient.filter()'],
							LANG::GET('order.ordered')=>['onfocus' => 'orderClient.filter("ordered")'],
							LANG::GET('order.received')=>['onfocus' => 'orderClient.filter("received")'],
							LANG::GET('order.archived')=>['onfocus' => 'orderClient.filter("archived")'],
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
				if (array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions'])) $displayunits = LANGUAGEFILE['units']; // see all orders
				else { // display only orders for own units
					$displayunits = [];
					foreach($_SESSION['user']['units'] as $unit){
						$displayunits[] = LANG::GET('units.' . $unit);
					}
				}
				// translate user-units for permission to delete
				$userunits = [];
				foreach($_SESSION['user']['units'] as $unit){
					$userunits[] = LANG::GET('units.' . $unit);
				}

				// in clause doesnt work without manually preparing
				$query = strtr(SQLQUERY::PREPARE('order_get-approved-order-by-unit'),
				[
					':organizational_unit' => "'".implode("','", $displayunits)."'"
				]);
				$statement = $this->_pdo->prepare($query);
				$statement->execute();
				$order = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($order as $row) {
					$content = [];
					$text = '\n';
					$decoded_order_data = json_decode($row['order_data'], true);
					if (array_key_exists('barcode', $decoded_order_data) && strlen($decoded_order_data['barcode'])) $content[]=[
						'type' => 'image',
						'attributes' => [
							'barcode' => ['value' => $decoded_order_data['barcode']],
							'imageonly' => ['width' => '10em', 'height' => '4em']
							]
					];
					
					$content[]=
						['type' => 'hiddeninput',
						'description' => 'filter',
						'attributes'=>['data-filtered' => $row['id']]];
					foreach ($decoded_order_data as $key => $value){ // data
						if (!in_array($key,['barcode', 'orderer'])) $content[]=[
							'type' => 'textinput',
							'attributes' => [
								'value' => $value,
								'name' => $key,
								'readonly' => true,
								'onpointerup' => 'orderClient.toClipboard(this)'
							]
						];
						if ($key == 'orderer') {
							$messagepayload=[];
							foreach (['quantity', 'unit', 'number', 'name', 'vendor', 'commission'] as $key){
								if (array_key_exists($key, $decoded_order_data)) $messagepayload[':' . $key] = $decoded_order_data[$key];
							}
							$content[]=[
								'type' => 'hiddeninput',
								'attributes' => [
									'name' => LANG::PROPERTY('message.to'),
									'value' => $value,
									'data-message' => $row['id']
								]
							];
							$content[]=[
								'type' => 'hiddeninput',
								'attributes' => [
									'name' => LANG::PROPERTY('message.message'),
									'value' => LANG::GET('order.message', $messagepayload),
									'data-message' => $row['id']
								]
							];
	
							$content[]=[
								'type' => 'textinput',
								'attributes' => [
									'value' => $value,
									'name' => LANG::GET('order.message_orderer'),
									'readonly' => true,
									'onpointerup' => "api.message('get', 'message' , '[data-message=\"" . $row['id'] . "\"]')"
								]
/*								'type' => 'links',
								'content' => [
									LANG::GET('order.message_orderer') . ' ' . $this->fields['orderer'] . ' ' . $value => ['href' => '#', 'onpointerup' => "api.message('get', 'message' , '0', '0', '" . $value . "', '" . LANG::GET('order.message', $messagepayload) . "')"]
								]*/

							];
						}
					}

					if (str_contains($row['approval'], 'data:image/png')){
						$content[]=[
							'type' => 'image',
							'attributes' => [
								'imageonly' => ['width' => '10em', 'marginTop' => '1em'],
								'name' => LANG::GET('order.approval_image'),
								'url' => $row['approval']],
						];
					}

					$text .= LANG::GET('order.unit') . ': ' . $row['organizational_unit'] . '\n';
					$text .= LANG::GET('order.approved') . ': ' . $row['approved'] . ' ';
					if (!str_contains($row['approval'], 'data:image/png')) $text .= $row['approval'] . '\n';

					$status=[];
					foreach(['ordered','received','archived'] as $s){
						if (boolval($row[$s])) {
							$status[LANG::GET('order.' . $s)] = ['disabled' => true, 'checked' => true, 'data-' . $s => true];
							$text .= LANG::GET('order.' . $s) . ': ' . $row[$s] . '\n';
						}
						else {
							if (
								(in_array($s, ['received', 'archived']) && (array_intersect(['admin'], $_SESSION['user']['permissions']) || array_intersect([$row['organizational_unit']], $userunits)))
								|| (in_array($s, ['ordered']) && (array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions'])))
							) $status[LANG::GET('order.' . $s)] = [
								'onchange' => "api.purchase('put', 'approved', " . $row['id']. ", '" . $s . "'); this.disabled=true; this.setAttribute('data-".$s."', 'true');",
								'data-'.$s => false
							];
							else $status[LANG::GET('order.' . $s)] = [
								'disabled' => true,
								'data-'.$s => false
							];
						}
					}
					if (!($row['ordered'] || $row['received'] || $row['archived']))	$status[LANG::GET('order.disapprove')]=[
						'data_disapproved' => 'false',
						'onchange' => "api.purchase('put', 'approved', " . $row['id']. ", 'disapproved'); this.disabled=true; this.setAttribute('data-disapproved', 'true');"
					];

					$content[]=[
						'type' => 'text',
						'content' => $text,
					];
					$content[]=[
						'type' => 'checkbox',
						'content' => $status
					];

					if (array_intersect(['admin'], $_SESSION['user']['permissions']) || array_intersect([$row['organizational_unit']], $userunits)) $content[]=[
						'type' => 'deletebutton',
						'description' => LANG::GET('order.delete_prepared_order'),
						'attributes' => [
							'type' => 'button',
							'onpointerup' => "if (confirm(LANG.GET('order.delete_prepared_order_confirm'))) api.purchase('delete', 'approved', " . $row['id'] . ")" 
						]
					];
					array_push($result['body']['content'], $content);
				}
				break;
			case 'DELETE':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_delete-approved-order'));
				if ($statement->execute([
					':id' => $this->_requestedID
					])) {
					$result=[
					'status' => [
						'id' => false,
						'msg' => LANG::GET('order.deleted')
					]];
				}
				else $result=[
					'status' => [
						'id' => $this->_requestedID,
						'msg' => LANG::GET('order.failed_delete')
					]];
				break;
			}
		$this->response($result);
	}
}

$api = new ORDER();
$api->processApi();

exit;
?>
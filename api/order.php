<?php 

class ORDER extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_subMethod = null;
	private $_borrowedModule = null;
	private $_message = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? (REQUEST[2] != 'false' ? REQUEST[2]: null) : null;
		$this->_subMethod = array_key_exists(3, REQUEST) ? REQUEST[3] : null;
		$this->_borrowedModule = $this->_message = array_key_exists(4, REQUEST) ? REQUEST[4] : null;
	}

	public function prepared(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				if (!(array_intersect(['admin', 'purchase', 'order_authorization'], $_SESSION['user']['permissions']))) $this->response([], 401);

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
					$approval = 'data:image/png;base64,' . base64_encode(UTILITY::resizeImage($signature, 512, UTILITY_IMAGE_RESOURCE, 'png'));
				}
				$approvedIDs = UTILITY::propertySet($this->_payload, LANG::PROPERTY('order.bulk_approve_order'));
				if (!$approval || !$approvedIDs) $this->response([], 401);

				$order_data=['items'=>[]];

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get-prepared-orders'));
				$statement->execute();
				$orders = $statement->fetchAll(PDO::FETCH_ASSOC);
				$index=0;
				foreach ($orders as $order){
					if (array_search($order['id'], $approvedIDs) === false) continue;

					foreach (json_decode($order['order_data'], true) as $key => $items){ // data
						if (is_array($items)){
							foreach($items as $item){
								foreach($item as $key => $subvalue){
									if (boolval($subvalue)) $order_data['items'][$index][$key] = trim($subvalue);
								}
								$index++;
							}
						} else {
							if (boolval($items)) $order_data[$key] = trim($items);
						}
					}
				}

				if(!count($order_data['items'])) $this->response([], 406);
				$result = $this->postApprovedOrder(['approval' => $approval, 'order_data' => $order_data]);

				if ($result['status']['msg']==LANG::GET('order.saved')){
					$query=strtr(SQLQUERY::PREPARE('order_delete-prepared-orders'), [':id' => "'" . implode("','", $approvedIDs) . "'"]);
					$statement = $this->_pdo->prepare($query);
					$statement->execute();
				}

				break;
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
					if (array_intersect([$order_data[LANG::PROPERTY('order.unit')]], $units)) {
						array_push($organizational_orders, $row);
					}
				}
				$result=['body' => ['content' => []]];
				if (count($organizational_orders)){
					foreach($organizational_orders as $order){ // order
						$items = $info = '';
						$processedOrderData = json_decode($order['order_data'], true);
						foreach ($processedOrderData as $key => $value){ // data
							if (is_array($value)){
								foreach($value as $item){
								$items .= LANG::GET('order.prepared_order_item', [
									':quantity' => UTILITY::propertySet((object) $item, LANG::PROPERTY('order.quantity_label')) ? : '',
									':unit' => UTILITY::propertySet((object) $item, LANG::PROPERTY('order.unit_label')) ? : '',
									':number' => UTILITY::propertySet((object) $item, LANG::PROPERTY('order.ordernumber_label')) ? : '',
									':name' => UTILITY::propertySet((object) $item, LANG::PROPERTY('order.productname_label')) ? : '',
									':vendor' => UTILITY::propertySet((object) $item, LANG::PROPERTY('order.vendor_label')) ? : ''
								])."\n";
								}
							} else {
								if ($key=='attachments') continue;
								$info .= str_replace('_', ' ', $key) . ': ' . $value . "\n";
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
							'description' => LANG::GET('order.edit_prepared_order'),
							'attributes' =>['type' => 'button',
							'onpointerup' => "api.purchase('get', 'order', " . $order['id']. ")"]]
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
					if (array_intersect(['admin', 'purchase', 'order_authorization'], $_SESSION['user']['permissions']) && count($organizational_orders)) {
						array_push($result['body']['content'], [
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
						]);
						$result['body']['form'] = ['action' => "javascript:api.purchase('put', 'prepared')", 'data-usecase' => 'purchase'];
					}
				}
				else $result['body']['content'] = $this->noContentAvailable(LANG::GET('order.no_orders'));
				break;
		}
		$this->response($result);
	}

	public function productsearch(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'GET':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$result = ['body'=>[]];
				if (!$this->_subMethod) {
					$result['body']['content'] = [];
					break;
				}
				// because in clause doesn't work
				$query= strtr(SQLQUERY::PREPARE('order_get-product-search'),
					[
						':vendors' => "'" . implode("','", explode('_', $this->_requestedID)) . "'",
						':search' => "'" . $this->_subMethod . "'"
					]
				);

				$statement = $this->_pdo->prepare($query);
				$statement->execute();
				$search = $statement->fetchAll(PDO::FETCH_ASSOC);

				$productsPerSlide = 0;
				$matches = [[]];

				foreach($search as $key => $row) {
					foreach($row as $key => $value){
						$row[$key] = str_replace("\n", ' ', $row[$key]);
					}
					$article = intval(count($matches) - 1);
					if (empty($productsPerSlide++ % INI['splitresults']['products_per_slide'])){
						$matches[$article][] = [
							['type' => 'links',
							'description' => LANG::GET('order.add_product_search_matches', [':number' => count($search)]),
							'content' => []
							]
						];
					}
					$slide = intval(count($matches[$article]) - 1);
					if ($this->_borrowedModule == 'editconsumables') // consumables.php can make good use of this method!
						$matches[$article][$slide][0]['content'][$row['vendor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit'] . ' ' . $row['article_ean']] = ['href' => "javascript:api.purchase('get', 'product', " . $row['id'] . ")", 'data-filtered' => 'breakline'];
					else
						$matches[$article][$slide][0]['content'][$row['vendor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit'] . ' ' . $row['article_ean']] = ['href' => 'javascript:void(0);', 'data-filtered' => 'breakline', 'data-type' => 'cart', 'onpointerup' => "orderClient.addProduct('" . $row['article_unit'] . "', '" . $row['article_no'] . "', '" . $row['article_name'] . "', '" . $row['article_ean'] . "', '" . $row['vendor_name'] . "'); return false;"];

				}
				if (!$matches[0]) $matches[0][] = [
					['type' => 'links',
					'description' => LANG::GET('order.add_product_search_matches', [':number' => count($search)]),
					'content' => []
					]
				];

				$result['body']['content'] = $matches;
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
		$unset=LANG::PROPERTY('consumables.edit_product_vendor_select');
		unset ($this->_payload->$unset);

		// detect approval
		$approval = false;
		if (UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.add_approval_token'))){
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('application_login'));
			$statement->execute([
				':token' => $this->_payload->approval_token
			]);
			if ($result = $statement->fetch(PDO::FETCH_ASSOC)){
				$approval = $result['name'] . LANG::GET('order.token_verified');
			}
			$unset=LANG::PROPERTY('consumables.add_approval_token');
			unset ($this->_payload->$unset);
		}
		if (array_key_exists('signature', $_FILES) && $_FILES['signature']['tmp_name']){
			$signature = gettype($_FILES['signature']['tmp_name'])=='array' ? $_FILES['signature']['tmp_name'][0] : $_FILES['signature']['tmp_name'];
			$approval = 'data:image/png;base64,' . base64_encode(UTILITY::resizeImage($signature, 512, UTILITY_IMAGE_RESOURCE, 'png'));
		}
		
		// initiate data
		$order_data = ['items' => []];
		
		// handle attachments
		$attachments = [];
		if (array_key_exists('attachments', $_FILES) && $_FILES['attachments']['tmp_name'][0]){
			$attachments = UTILITY::storeUploadedFiles(['attachments'], '../' . INI['order']['attachment_folder'], [time()]);
			foreach($attachments as $key => $value){
				if ($value)	$attachments[$key] = substr($value, 1);
				else unset($attachments[$key]);
			}
		}
		$existingattachments = UTILITY::propertySet($this->_payload, 'existingattachments') ? : '';
		if ($attachments || $existingattachments) {
			$order_data['attachments'] = trim(implode(',', array_merge($attachments, explode(',', $existingattachments))), ',');
			unset ($this->_payload->existingattachments);
		}

		// set data
		foreach ($this->_payload as $key => $value){
			if (is_array($value)){
				foreach($value as $index => $subvalue){
					// manual adding has the same names but has to be ignores
					// as per layout these names appear before actual ordered items
					// being ignored the items keys are reduced by 1
					if (boolval($subvalue)) {
						if (!array_key_exists(intval($index) - 1, $order_data['items'])) $order_data['items'][]=[];
						$order_data['items'][intval($index) - 1][$key] = trim(preg_replace(["/\\r/", "/\\n/"], ['', '\\n'], $subvalue));
					}
				}
			} else {
				if (boolval($value)) $order_data[$key] = trim(preg_replace(["/\\r/", "/\\n/"], ['', '\\n'], $value));
			}
		}
		$order_data[LANG::GET('order.orderer')]=$_SESSION['user']['name'];

		if(!count($order_data['items'])) $this->response([], 406);
		return ['approval' => $approval, 'order_data' => $order_data];
	}

	private function postApprovedOrder($processedOrderData){
		$keys = array_keys($processedOrderData['order_data']);
		$order_data2 = [];
		for ($i = 0; $i < count($processedOrderData['order_data']['items']); $i++){
			$order_data2 = $processedOrderData['order_data']['items'][$i];
			foreach ($keys as $key){
				if (!in_array($key, ['items',LANG::PROPERTY('order.unit')])) $order_data2[$key] = $processedOrderData['order_data'][$key];
			}
			$query = strtr(SQLQUERY::PREPARE('order_post-approved-order'),
			[
				':order_data' => "'" . json_encode($order_data2, JSON_UNESCAPED_SLASHES) . "'",
				':organizational_unit' => "'" . $processedOrderData['order_data'][LANG::PROPERTY('order.unit')] . "'",
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
			$statement->closeCursor();
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
				$processedOrderData = $this->processOrderForm();

				if (!$processedOrderData['approval']){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_post-prepared-order'));
					$statement->execute([
						':order_data' => json_encode($processedOrderData['order_data'], JSON_UNESCAPED_SLASHES)
					]);
					$result = [
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
						':order_data' => json_encode($processedOrderData['order_data'], JSON_UNESCAPED_SLASHES),
						':id' => $this->_requestedID
					]);
					$result = [
						'status' => [
							'id' => $this->_requestedID,
							'msg' => LANG::GET('order.saved_to_prepared')
						]];
					break;
				}
				
				$result = $this->postApprovedOrder($processedOrderData);

				if ($result['status']['msg'] == LANG::GET('order.saved')){
					$query = strtr(SQLQUERY::PREPARE('order_delete-prepared-orders'), [':id' => "'" . $this->_requestedID . "'"]);
					$statement = $this->_pdo->prepare($query);
					$statement->execute();
				}
				break;
			case 'GET':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$datalist = [];
				$datalist_unit = [];
				$vendors = [];

				// prepare existing vendor lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-vendor-datalist'));
				$statement->execute();

				$vendor = $statement->fetchAll(PDO::FETCH_ASSOC);
				$vendors[LANG::GET('consumables.edit_product_search_all_vendors')] = ['value' => implode('_', array_map(fn($r) => $r['id'], $vendor))];
				
				foreach($vendor as $key => $row) {
					$datalist[] = $row['name'];
					$vendors[$row['name']] = ['value' => $row['id']];
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
					LANG::PROPERTY('order.additional_info') => '',
					LANG::PROPERTY('order.unit') => '',
					LANG::PROPERTY('order.commission') => '',
					LANG::PROPERTY('order.delivery_date') => '',
					'items' => false
				];} else {
					$order = json_decode($order['order_data'], true);
				}
				$organizational_units=[];
				foreach(LANGUAGEFILE['units'] as $unit => $description){
					$organizational_units[$description] = ['name' => LANG::PROPERTY('order.unit'), 'required' => true];
					if (array_key_exists(LANG::PROPERTY('order.unit'), $order) && in_array($description, explode(',', $order[LANG::PROPERTY('order.unit')]))) $organizational_units[$description]['checked'] = true;
				}

				$result['body'] = ['form'=>[
					'data-usecase'=> 'purchase',
					'action' => $this->_requestedID ? "javascript:api.purchase('put', 'order', '" . $this->_requestedID . "')" : "javascript:api.purchase('post', 'order')"
				],
				'content' => [
					[
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
							]]
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
							]],
							['type' => 'hiddeninput',
							'attributes' => [
								'name' => LANG::GET('order.quantity_label') . '[]',
								'value' => ''
							]],
							['type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('order.unit_label') . '[]',
								'list' => 'units',
								'data-loss' => 'prevent'
							]],
							['type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('order.ordernumber_label') . '[]',
								'data-loss' => 'prevent'
							]],
							['type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('order.productname_label') . '[]',
								'data-loss' => 'prevent'
							]],
							['type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('order.vendor_label') . '[]',
								'list' => 'vendors',
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
								'onpointerup' => "orderClient.addProduct(this.parentNode.children[2].value, this.parentNode.children[5].value, this.parentNode.children[9].value, this.parentNode.children[13].value, '', this.parentNode.children[17].value); for (const e of this.parentNode.children) e.value=''"
							]]
						]
					],[
						['type' => 'hr']
					],[
						['type' => 'radio',
						'description' => LANG::GET('order.unit'),
						'content' => $organizational_units
						],
						['type' => 'scanner',
						'destination' => 'commission'
						],
						['type' => 'textinput',
						'hint' => LANG::GET('order.commission_hint'),
						'attributes' => [
							'required' => true,
							'name' => LANG::GET('order.commission'),
							'value' => array_key_exists(LANG::PROPERTY('order.commission'), $order) ? $order[LANG::PROPERTY('order.commission')] : '',
							'data-loss' => 'prevent',
							'id' => 'commission'
						]],
						['type' => 'dateinput',
						'attributes' => [
							'name' => LANG::GET('order.delivery_date'),
							'value' => array_key_exists(LANG::PROPERTY('order.delivery_date'), $order) ? $order[LANG::PROPERTY('order.delivery_date')] : ''
						]],
						['type' => 'textarea',
						'attributes' => [
							'name' => LANG::GET('order.additional_info'),
							'value' => array_key_exists(LANG::PROPERTY('order.additional_info'), $order) ? $order[LANG::PROPERTY('order.additional_info')] : '',
							'data-loss' => 'prevent'
						]]
					],[
						[
							['type' => 'file',
							'description' => LANG::GET('order.attach_file'),
							'attributes' => [
								'name' => 'attachments[]',
								'multiple' => true
							]]
						],[
							['type' => 'photo',
							'description' => LANG::GET('order.attach_photo'),
							'attributes' => [
								'name' => 'attachments[]'
							]]
						]
					],
					[
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

				// cart-content has a twin within utility.js orderClient.addProduct() method
				if ($order['items']){
					$items=[];
					for ($i = 0; $i < count($order['items']); $i++){
						array_push($items,
						[
							[
								'type' => 'numberinput',
								'attributes' => [
									'name' => LANG::GET('order.quantity_label') . '[]',
									'value' => $order['items'][$i][LANG::PROPERTY('order.quantity_label')],
									'min' => '1',
									'max' => '99999',
									'required' => true,
									'data-loss' => 'prevent'
								]
							],
							[
								'type' => 'text',
								'description' => LANG::GET('order.added_product', [
									':unit' => UTILITY::propertySet((object) $order['items'][$i], LANG::PROPERTY('order.unit_label')),
									':number' => $order['items'][$i][LANG::PROPERTY('order.ordernumber_label')],
									':name' => $order['items'][$i][LANG::PROPERTY('order.productname_label')],
									':vendor' => $order['items'][$i][LANG::PROPERTY('order.vendor_label')]
								])
							],
							[
								'type' => 'hiddeninput',
								'attributes' => [
									'name' => LANG::GET('order.unit_label') . '[]',
									'value' => UTILITY::propertySet((object) $order['items'][$i], LANG::PROPERTY('order.unit_label')) ? : ' '
								]
							],
							[
								'type' => 'hiddeninput',
								'attributes' => [
									'name' => LANG::GET('order.ordernumber_label') . '[]',
									'value' => $order['items'][$i][LANG::PROPERTY('order.ordernumber_label')]
								]
							],
							[
								'type' => 'hiddeninput',
								'attributes' => [
									'name' => LANG::GET('order.productname_label') . '[]',
									'value' => $order['items'][$i][LANG::PROPERTY('order.productname_label')]
								]
							],
							[
								'type' => 'hiddeninput',
								'attributes' => [
									'name' => LANG::GET('order.barcode') . '[]',
									'value' => UTILITY::propertySet((object) $order['items'][$i], LANG::PROPERTY('order.barcode')) ?  : ' '
								]
							],
							[
								'type' => 'hiddeninput',
								'attributes' => [
									'name' => LANG::GET('order.vendor_label') . '[]',
									'value' => $order['items'][$i][LANG::PROPERTY('order.vendor_label')]
								]
							],
							[
								'type' => 'deletebutton',
								'description' => LANG::GET('order.add_delete'),
								'attributes' => [
									'onpointerup' => 'this.parentNode.remove()'
								]
							]
						]);
					}
					array_splice($result['body']['content'], 2, 0, $items);
				}
				if ($this->_requestedID) array_push($result['body']['content'], [
					['type' => 'deletebutton',
					'description' => LANG::GET('order.delete_prepared_order'),
					'attributes' => [
						'type' => 'button', // apparently defaults to submit otherwise
						'onpointerup' => "new Dialog({type: 'confirm', header: '". LANG::GET('order.delete_prepared_order_confirm_header') ."', 'options':{".
							"'".LANG::GET('order.delete_prepared_order_confirm_cancel')."': false,".
							"'".LANG::GET('order.delete_prepared_order_confirm_ok')."': {value: true, class: 'reducedCTA'},".
							"}}).then(function(r){if (r.target.returnValue==='true') api.purchase('delete', 'order', " . $this->_requestedID . ")})"
					]]
				]);

				break;
			case 'DELETE':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				
				// delete attachments
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get-prepared-order'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				$order = $statement->fetch(PDO::FETCH_ASSOC);
				$order = json_decode($order['order_data'], true);
				if (array_key_exists('attachments', $order)){
					$files = explode(',', $order['attachments']);
					UTILITY::delete(array_map(fn($value) => '.' . $value, $files));
				}

				// delete prepared order
				$query = strtr(SQLQUERY::PREPARE('order_delete-prepared-orders'), [':id' => "'" . $this->_requestedID . "'"]);
				$statement = $this->_pdo->prepare($query);
				if ($statement->execute()) {
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
				if ($this->_subMethod == 'addinformation') $statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get-approved-order-by-id'));
				$statement->execute([
					':id' => $this->_requestedID
					]);
				if ($this->_subMethod == 'disapproved'){
					$order = $statement->fetch(PDO::FETCH_ASSOC);
					$decoded_order_data = json_decode($order['order_data'], true);

					// prepare possible keys
					$prepared = [
						'items' => [[]],
						LANG::PROPERTY('order.additional_info') => null,
						LANG::PROPERTY('order.unit') => $order['organizational_unit'],
						LANG::PROPERTY('order.commission') => null,
						LANG::PROPERTY('order.orderer') => null,
						LANG::PROPERTY('order.delivery_date') => null,
						'attachments' => null
					];
					// fill possible keys
					foreach ($decoded_order_data as $key => $value){
						if (array_key_exists($key, $prepared)) $prepared[$key] = $value;
						else $prepared['items'][0][$key] = $value;
					}
					// add initially approval date
					$prepared[LANG::PROPERTY('order.additional_info')] .= ($prepared[LANG::PROPERTY('order.additional_info')] ? "\n": '') . LANG::GET('order.initially_approved') . ': ' . $order['approved'];
					// clear unused keys
					foreach ($prepared as $key => $value) {
						if (!$value) unset($prepared[$key]);
					}
					// add to prepared orders
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_post-prepared-order'));
					$statement->execute([
						':order_data' => json_encode($prepared, JSON_UNESCAPED_SLASHES)
					]);
					// delete approved order
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_delete-approved-order'));
					$statement->execute([
						':id' => $this->_requestedID
					]);
					// inform user group
					$messagepayload=[];
					foreach (['quantity'=> LANG::PROPERTY('order.quantity_label'),
						'unit' => LANG::PROPERTY('order.unit_label'),
						'number' => LANG::PROPERTY('order.ordernumber_label'),
						'name' => LANG::PROPERTY('order.productname_label'),
						'vendor' => LANG::PROPERTY('order.vendor_label'),
						'commission' => LANG::PROPERTY('order.commission')] as $key => $value){
						if (array_key_exists($value, $decoded_order_data)) $messagepayload[':' . $key] = $decoded_order_data[$value];
					}
					$this->alertUserGroup(array_search($prepared[LANG::PROPERTY('order.unit')], LANGUAGEFILE['units']), LANG::GET('order.alert_disapprove_order',[
						':order' => LANG::GET('order.message', $messagepayload),
						':unit' => $prepared[LANG::PROPERTY('order.unit')],
						':user' => $_SESSION['user']['name']
					]) . "\n \n" . $this->_message, 'unit');
				}
				if ($this->_subMethod == 'addinformation'){
					$order = $statement->fetch(PDO::FETCH_ASSOC);
					$decoded_order_data = json_decode($order['order_data'], true);
					if (array_key_exists(LANG::PROPERTY('order.additional_info'), $decoded_order_data)){
						$decoded_order_data[LANG::PROPERTY('order.additional_info')] .= "\n".$this->_message;
					}
					else $decoded_order_data[LANG::PROPERTY('order.additional_info')]=$this->_message;
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_put-approved-order-addinformation'));
					$statement->execute([
						':order_data' => json_encode($decoded_order_data, JSON_UNESCAPED_SLASHES),
						':id' => $this->_requestedID
					]);
					$this->_subMethod = 'add_information_confirmation';
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
						'description' => LANG::GET('order.order_filter'),
						'content' => [
							LANG::GET('order.untreated')=>['checked' => true, 'onchange' => 'orderClient.filter()'],
							LANG::GET('order.ordered')=>['onchange' => 'orderClient.filter("ordered")'],
							LANG::GET('order.received')=>['onchange' => 'orderClient.filter("received")'],
							LANG::GET('order.archived')=>['onchange' => 'orderClient.filter("archived")'],
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
					$text = "\n";
					$decoded_order_data = json_decode($row['order_data'], true);
					if (array_key_exists(LANG::PROPERTY('order.barcode'), $decoded_order_data) && strlen($decoded_order_data[LANG::PROPERTY('order.barcode')])) $content[]=[
						'type' => 'image',
						'attributes' => [
							'barcode' => ['value' => $decoded_order_data[LANG::PROPERTY('order.barcode')]],
							'imageonly' => ['width' => '15em', 'height' => '6em']
							]
					];
					
					$content[]=
						['type' => 'hiddeninput',
						'description' => 'filter',
						'attributes' => ['data-filtered' => $row['id']]];

					$fields=[
						'name' => LANG::PROPERTY('order.productname_label'),
						'unit' => LANG::PROPERTY('order.unit_label'),
						'number' => LANG::PROPERTY('order.ordernumber_label'),
						'quantity' => LANG::PROPERTY('order.quantity_label'),
						'vendor' => LANG::PROPERTY('order.vendor_label'),
						'commission' => LANG::PROPERTY('order.commission'),
					];
					$messagepayload=[];
					foreach ($fields as $replace => $with){
						if (array_key_exists($with, $decoded_order_data)) $messagepayload[':' . $replace] = $decoded_order_data[$with];
					}
					$messageorderer=UTILITY::propertySet((object) $decoded_order_data, LANG::PROPERTY('order.orderer')) ? : '';
					$content[]=[
						'type' => 'hiddeninput',
						'numeration' => 'none',
						'attributes' => [
							'name' => LANG::PROPERTY('message.to'),
							'value' => UTILITY::propertySet((object) $decoded_order_data, LANG::PROPERTY('order.orderer')) ? : '',
							'data-message' => $row['id']
						]
					];
					$content[]=[
						'type' => 'hiddeninput',
						'numeration' => 'none',
						'attributes' => [
							'name' => LANG::PROPERTY('message.message'),
							'value' => LANG::GET('order.message', $messagepayload),
							'data-message' => $row['id']
						]
					];

					$text .= LANG::GET('order.prepared_order_item', [
						':quantity' => UTILITY::propertySet((object) $decoded_order_data, LANG::PROPERTY('order.quantity_label')) ? : '',
						':unit' => UTILITY::propertySet((object) $decoded_order_data, LANG::PROPERTY('order.unit_label')) ? : '',
						':number' => UTILITY::propertySet((object) $decoded_order_data, LANG::PROPERTY('order.ordernumber_label')) ? : '',
						':name' => UTILITY::propertySet((object) $decoded_order_data, LANG::PROPERTY('order.productname_label')) ? : '',
						':vendor' => UTILITY::propertySet((object) $decoded_order_data, LANG::PROPERTY('order.vendor_label')) ? : ''
					])."\n";

					if ($additional_information = UTILITY::propertySet((object) $decoded_order_data, LANG::PROPERTY('order.additional_info')))
						$text .= LANG::GET('order.additional_info') . ': ' . $additional_information . "\n \n";
					$text .= LANG::GET('order.unit') . ': ' . $row['organizational_unit'] . "\n";
					$text .= LANG::GET('order.approved') . ': ' . $row['approved'] . ' ';
					if (!str_contains($row['approval'], 'data:image/png')) $text .= $row['approval'] . "\n";

					$copy = [
						[
							'type' => 'textinput',
							'attributes' => [
								'value' => UTILITY::propertySet((object) $decoded_order_data, LANG::PROPERTY('order.ordernumber_label')) ? : '',
								'name' => LANG::GET('order.ordernumber_label'),
								'readonly' => true,
								'onpointerup' => 'orderClient.toClipboard(this)'
							]
						],
						[
							'type' => 'textinput',
							'attributes' => [
								'value' => UTILITY::propertySet((object) $decoded_order_data, LANG::PROPERTY('order.commission')) ? : '',
								'name' => LANG::GET('order.commission'),
								'readonly' => true,
								'onpointerup' => 'orderClient.toClipboard(this)'
							],
							'hint' => LANG::GET('order.copy_values')
						],
					];

					$status=[];
					foreach(['ordered','received','archived'] as $s){
						if (boolval($row[$s])) {
							$status[LANG::GET('order.' . $s)] = ['disabled' => true, 'checked' => true, 'data-' . $s => true];
							$text .= "\n" . LANG::GET('order.' . $s) . ': ' . $row[$s];
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
						'onchange' => "new Dialog({type:'input', header:'" . LANG::GET('order.disapprove') . "', body:'" . LANG::GET('order.disapprove_message', [':unit' => $row['organizational_unit']]) . "', " .
							"options:{'" . LANG::GET('order.disapprove_message_cancel') . "': false, '" . LANG::GET('order.disapprove_message_ok') . "': {'value': true, class: 'reducedCTA'}}}).then((response) => {" .
							"if (response.target.returnValue==='true') {" .
							"api.purchase('put', 'approved', " . $row['id']. ", 'disapproved', document.querySelector('dialog>form>textarea').value); this.disabled=true; this.setAttribute('data-disapproved', 'true');" .
							"} else this.checked = false;});"
					];

					$content[] = [
						'type' => 'text',
						'content' => $text,
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
					$content[]=[
						'type' => 'links',
						'content' => [
							LANG::GET('order.message_orderer', [':orderer' => $messageorderer]) => ['href' => 'javascript:void(0)', 'onpointerup' => "api.message('get', 'message' , '[data-message=\"" . $row['id'] . "\"]')"]
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

					if (array_intersect(['admin'], $_SESSION['user']['permissions']) || array_intersect([$row['organizational_unit']], $userunits)) $content[]=[
						'type' => 'button',
						'description' => LANG::GET('order.add_information'),
						'attributes' => [
							'type' => 'button',
							'onpointerup' => "new Dialog({type: 'input', header: '". LANG::GET('order.add_information') ."', body: '". LANG::GET('order.add_information_modal_body') ."', options:{".
								"'".LANG::GET('order.add_information_cancel')."': false,".
								"'".LANG::GET('order.add_information_ok')."': {value: true, class: 'reducedCTA'},".
								"}}).then(function(r){if (r.target.returnValue==='true') api.purchase('put', 'approved', " . $row['id']. ", 'addinformation', document.querySelector('dialog>form>textarea').value)})"
						]
					];

					$content[] = [
						'type' => 'checkbox',
						'content' => $status
					];

					if (array_intersect(['admin'], $_SESSION['user']['permissions']) || array_intersect([$row['organizational_unit']], $userunits)) $content[]=[
						'type' => 'deletebutton',
						'description' => LANG::GET('order.delete_prepared_order'),
						'attributes' => [
							'type' => 'button',
							'onpointerup' => "new Dialog({type: 'confirm', header: '". LANG::GET('order.delete_prepared_order_confirm_header') ."', options:{".
								"'".LANG::GET('order.delete_prepared_order_confirm_cancel')."': false,".
								"'".LANG::GET('order.delete_prepared_order_confirm_ok')."': {value: true, class: 'reducedCTA'},".
								"}}).then(function(r){if (r.target.returnValue==='true') api.purchase('delete', 'approved', " . $row['id'] . ")})"
	
						]
					];
					array_push($result['body']['content'], $content);
				}
				break;
			case 'DELETE':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);

				// delete attachments if not used by any other approved order
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get-approved-order-by-id'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				$order = $statement->fetch(PDO::FETCH_ASSOC);
				$order = json_decode($order['order_data'], true);
				if (array_key_exists('attachments', $order)){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_get-approved-order-by-substr'));
					$statement->execute([
						':substr' => $order['attachments']
					]);
					$others = $statement->fetchAll(PDO::FETCH_ASSOC);
					if (count($others)<2){
						$files = explode(',', $order['attachments']);
						UTILITY::delete(array_map(fn($value) => '.' . $value, $files));
					}
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_delete-approved-order'));
				if ($statement->execute([
					':id' => $this->_requestedID
					])) {
					$result = [
					'status' => [
						'id' => false,
						'msg' => LANG::GET('order.deleted')
					]];
				}
				else $result = [
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
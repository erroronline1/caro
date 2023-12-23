<?php 

class ORDER extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_subMethod = null;

	private $fields = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_subMethod = array_key_exists(3, REQUEST) ? REQUEST[3] : null;

		$this->fields=[
			'name' => LANG::GET('order.productname_label'),
			'unit' => LANG::GET('order.unit_label'),
			'number' => LANG::GET('order.ordernumber_label'),
			'quantity' => LANG::GET('order.quantity_label'),
			'distributor' => LANG::GET('order.distributor_label'),
			'orderer' => LANG::GET('order.orderer'),
			'organizational_unit' => LANG::GET('order.unit'),
			'commission' => LANG::GET('order.commission'),
			'deliverydate' => LANG::GET('order.delivery_date'),
			'info' => LANG::GET('order.additional_info'),
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
				$userunits=[];
				foreach($_SESSION['user']['units'] as $i => $unit){
					array_push($userunits, LANG::GET('units.'. $unit));
				}
				$organizational_orders=[];
				foreach($orders as $key => $row) {
					$order_data=json_decode($row['order_data'], true);
					if (array_intersect([$order_data['organizational_unit']], $userunits)) {
						array_push($organizational_orders, $row);
					}
				}
				$result=['body' => ['content' => []]];
				foreach($organizational_orders as $order){ // order
					$text = '';
					foreach (json_decode($order['order_data']) as $key => $value){ // data
						if (is_array($value)){
							foreach($value as $index => $item){ // items
								foreach ($item as $itemkey => $itemvalue){
									$text .= $this->fields[$itemkey] . ': ' . $itemvalue . '\n';
								}
								$text .= '\n';
							}

						} else {
							$text .= $this->fields[$key] . ': ' . $value . '\n';
						}
					}
					array_push($result['body']['content'], [
						['type' => 'text',
						'content' => $text,
						'collapse' => true
						],
						['type' => 'button',
						'collapse' => true,
						'description' => LANG::GET('order.edit_prepared_order'),
						'attributes' =>['type' => 'button',
						'onpointerdown' => "api.purchase('get', 'order', " . $order['id']. ")"]],
						['type' => 'cart',
						'collapse' => true
						]
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
					$matches[$row['distributor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit']] = ['href' => 'javascript:void(0);', 'onpointerdown' => "orderClient.addProduct('" . $row['article_unit'] . "', '" . $row['distributor_name'] . "', '" . $row['article_no'] . "', '" . $row['article_name'] . "'); return false;"];
				}
				$result['body']['content']=
					[[
						['type' => 'links',
						'description' => LANG::GET('order.add_product_search_matches', [':number' => count($matches)]),
						'content' => $matches
						]
					]];
				break;
			}
		$this->response($result);
	}

	public function order(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				unset ($this->_payload->Search_product);
				$approval = false;
				if ($this->_payload->approval_token){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('application_login'));
					$statement->execute([
						':token' => $this->_payload->approval_token
					]);
					if ($result = $statement->fetch(PDO::FETCH_ASSOC)){
						$approval = $result['name'] . LANG::GET('order.token_verified');
					}
				}
				if ($_FILES['signature']['tmp_name']){
					$approval = 'data:image/png;base64,' . base64_encode(UTILITY::resizeImage($_FILES['signature']['tmp_name'], 256, UTILITY_IMAGE_RESOURCE, 'png'));
				}
				unset ($this->_payload->approval_token);
				$order_data=['items'=>[]];
				foreach ($this->_payload as $key => $value){
					if (is_array($value)){
						foreach($value as $index => $subvalue){
							if ($subvalue != null) $order_data['items'][$index][$key] = $subvalue;
						}
					} else {
						if ($value != null) $order_data[$key] = $value;
					}
				}
				$order_data['orderer']=$_SESSION['user']['name'];

				if(!count($order_data['items'])) $this->response([], 406);

				if (!$approval){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_post-prepared-order'));
					$statement->execute([
						':order_data' => json_encode($order_data)
					]);
					$result=[
						'status' => [
							'id' => $this->_pdo->lastInsertId(),
							'msg' => LANG::GET('order.saved_to_prepared')
						]];
					break;
				}
				
				$keys = array_keys($order_data);
				$order_data2 = [];
				$query = '';
				for ($i = 0; $i<count($order_data['items']);$i++){
					$order_data2 = $order_data['items'][$i];
					foreach ($keys as $key){
						if (!in_array($key, ['items','organizational_unit'])) $order_data2[$key] = $order_data[$key];
					}
					$query .= strtr(SQLQUERY::PREPARE('order_post-approved-order'),
					[
						':order_data' => "'" . json_encode($order_data2) . "'",
						':organizational_unit' => "'" . $order_data['organizational_unit'] . "'",
						':approval' => "'" . $approval . "'",
					]) . '; ';
				}
				$statement = $this->_pdo->prepare($query);
				if ($statement->execute()) $result=[
					'status' => [
						'id' => false,
						'msg' => LANG::GET('order.saved')
					]];
				else $result=[
					'status' => [
						'id' => false,
						'msg' => LANG::GET('order.failed_save')
					]];
				break;
			case 'PUT':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				unset ($this->_payload->Search_product);
				$approval = false;
				if ($this->_payload->approval_token){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('application_login'));
					$statement->execute([
						':token' => $this->_payload->approval_token
					]);
					if ($result = $statement->fetch(PDO::FETCH_ASSOC)){
						$approval = $result['name'] . LANG::GET('order.token_verified');
					}
				}
				if (array_key_exists('signature', $_FILES) &&  $_FILES['signature']['tmp_name']){
					$approval = 'data:image/png;base64,' . base64_encode(UTILITY::resizeImage($_FILES['signature']['tmp_name'][0], 256, UTILITY_IMAGE_RESOURCE, 'png'));
				}
				unset ($this->_payload->approval_token);
				$order_data=['items'=>[]];
				foreach ($this->_payload as $key => $value){
					if (is_array($value)){
						foreach($value as $index => $subvalue){
							if ($subvalue != null) $order_data['items'][$index][$key] = $subvalue;
						}
					} else {
						if ($value != null) $order_data[$key] = $value;
					}
				}
				$order_data['orderer']=$_SESSION['user']['name'];

				if(!count($order_data['items'])) $this->response([], 406);

				if (!$approval){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_put-prepared-order'));
					$statement->execute([
						':order_data' => json_encode($order_data),
						':id' => $this->_requestedID
					]);
					$result=[
						'status' => [
							'id' => $this->_requestedID,
							'msg' => LANG::GET('order.saved_to_prepared')
						]];
					break;
				}
				
				$keys = array_keys($order_data);
				$order_data2 = [];
				$query = '';
				for ($i = 0; $i<count($order_data['items']);$i++){
					$order_data2 = $order_data['items'][$i];
					foreach ($keys as $key){
						if (!in_array($key, ['items','organizational_unit'])) $order_data2[$key] = $order_data[$key];
					}
					$query .= strtr(SQLQUERY::PREPARE('order_post-approved-order'),
					[
						':order_data' => "'" . json_encode($order_data2) . "'",
						':organizational_unit' => "'" . $order_data['organizational_unit'] . "'",
						':approval' => "'" . $approval . "'",
					]) . '; ';
				}
				$statement = $this->_pdo->prepare($query);
				if ($statement->execute()) {
					$result=[
					'status' => [
						'id' => false,
						'msg' => LANG::GET('order.saved')
					]];
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('order_delete-prepared-order'));
					$statement->execute([
						':id' => $this->_requestedID
					]);
				}
				else $result=[
					'status' => [
						'id' => false,
						'msg' => LANG::GET('order.failed_save')
					]];
				break;
			case 'GET':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$datalist = [];

				// prepare existing distributor lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-distributor-datalist'));
				$statement->execute();
				$distributor = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($distributor as $key => $row) {
					$datalist[] = $row['name'];
				}
				// prepare existing unit lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-product-units'));
				$statement->execute();
				$distributor = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($distributor as $key => $row) {
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
						'description' => LANG::GET('consumables.edit_product_search'),
						'destination' => 'productsearch'
						]
					],[
						['type' => 'searchinput',
						'description' => LANG::GET('consumables.edit_product_search'),
						'attributes' => [
							'placeholder' => LANG::GET('consumables.edit_product_search_label'),
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
							'id' => 'distributors'
						]],
						['type' => 'datalist',
						'content' => $datalist_unit,
						'attributes' => [
							'id' => 'units'
						]]
					],[
						['type' => 'numberinput',
						'collapse' => true,
						'attributes' => [
							'name' => 'quantity[]',
							'min' => '1',
							'max' => '99999',
							'placeholder' => LANG::GET('order.quantity_label'),
							'onblur' => 'orderClient.required(this.parentNode)'
						]],
						['type' => 'textinput',
						'collapse' => true,
						'attributes' => [
							'name' => 'unit[]',
							'list' => 'units',
							'placeholder' => LANG::GET('order.unit_label'),
							'onblur' => 'orderClient.required(this.parentNode)'
						]],
						['type' => 'textinput',
						'collapse' => true,
						'attributes' => [
							'name' => 'distributor[]',
							'list' => 'distributors',
							'placeholder' => LANG::GET('order.distributor_label'),
							'onblur' => 'orderClient.required(this.parentNode)'
						]],
						['type' => 'textinput',
						'collapse' => true,
						'attributes' => [
							'name' => 'number[]',
							'placeholder' => LANG::GET('order.ordernumber_label'),
							'onblur' => 'orderClient.required(this.parentNode)'
						]],
						['type' => 'textinput',
						'collapse' => true,
						'attributes' => [
							'name' => 'name[]',
							'placeholder' => LANG::GET('order.productname_label'),
							'onblur' => 'orderClient.required(this.parentNode)'
						]],
						['type' => 'button',
						'collapse' => true,
						'attributes' => [
							'value' => LANG::GET('order.add_button'),
							'type' => 'button',
							'onpointerdown' => 'orderClient.cloneNew(this.parentNode)'
						]],
						['type' => 'collapsed',
						'collapse' => true],
					],[
						['type' => 'textarea',
						'description' => LANG::GET('order.additional_info'),
						'attributes' => [
							'name' => 'info',
							'value' => array_key_exists('info', $order) ? $order['info'] : ''
						]]
					],[
						['type' => 'radio',
						'description' => LANG::GET('order.unit'),
						'content' => $organizational_units
						]
					],[
						['type' => 'scanner',
						'description' => LANG::GET('order.commission'),
						'attributes' => [
							'name' => 'commission',
							'required' => true,
							'placeholder' => LANG::GET('order.add_commission_placeholder'),
							'value' => array_key_exists('commission', $order) ? $order['commission'] : ''
						]]
					],[
						['type' => 'dateinput',
						'description' => LANG::GET('order.delivery_date'),
						'attributes' => [
							'name' => 'deliverydate',
							'value' => array_key_exists('deliverydate', $order) ? $order['deliverydate'] : ''
						]]
					],[
						['type' => 'signature',
						'description' => LANG::GET('order.add_approval_signature'),
						'attributes' => [
							'name' => 'approval_signature'
						]],
						['type' => 'scanner',
						"description" => LANG::GET('order.add_approval_token'),
						'attributes' => [
							'name' => 'approval_token',
							'type' => 'password'
						]]
					],
				]];
				if ($order['items']){
					$items=[];
					for ($i = 0; $i < count($order['items']); $i++){
						array_push($items,
						[
							['type' => 'numberinput',
							'collapse' => true,
							'attributes' => [
								'name' => 'quantity[]',
								'min' => '1',
								'max' => '99999',
								'placeholder' => LANG::GET('order.quantity_label'),
								'onblur' => 'orderClient.required(this.parentNode)',
								'value' => $order['items'][$i]['quantity']
							]],
							['type' => 'textinput',
							'collapse' => true,
							'attributes' => [
								'name' => 'unit[]',
								'list' => 'units',
								'placeholder' => LANG::GET('order.unit_label'),
								'onblur' => 'orderClient.required(this.parentNode)',
								'value' => $order['items'][$i]['unit']
							]],
							['type' => 'textinput',
							'collapse' => true,
							'attributes' => [
								'name' => 'distributor[]',
								'list' => 'distributors',
								'placeholder' => LANG::GET('order.distributor_label'),
								'onblur' => 'orderClient.required(this.parentNode)',
								'value' => $order['items'][$i]['distributor']
							]],
							['type' => 'textinput',
							'collapse' => true,
							'attributes' => [
								'name' => 'number[]',
								'placeholder' => LANG::GET('order.ordernumber_label'),
								'onblur' => 'orderClient.required(this.parentNode)',
								'value' => $order['items'][$i]['number']
							]],
							['type' => 'textinput',
							'collapse' => true,
							'attributes' => [
								'name' => 'name[]',
								'placeholder' => LANG::GET('order.productname_label'),
								'onblur' => 'orderClient.required(this.parentNode)',
								'value' => $order['items'][$i]['name']
							]],
							['type' => 'button',
							'collapse' => true,
							'attributes' => [
								'value' => LANG::GET('order.add_delete'),
								'type' => 'button',
								'onpointerdown' => 'this.parentNode.remove()'
							]],
							['type' => 'collapsed',
							'collapse' => true],
						]);
					}
					array_splice($result['body']['content'], 4, 0, $items);
				}
				if ($this->_requestedID) array_push($result['body']['content'], [
					['type' => 'deletebutton',
						'description' => LANG::GET('order.delete_prepared_order'),
						'attributes' => [
							'type' => 'button', // apparently defaults to submit otherwise
							'onpointerdown' => 'if (confirm("'. LANG::GET('order.delete_prepared_order_confirm') .'")) {api.purchase("delete", "order", ' . $this->_requestedID . ')}'
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
				$statement->execute([
					':id' => $this->_requestedID
					]);
				$result=[
					'status' => [
						'msg' => LANG::GET('order.' . $this->_subMethod)
					]];
				break;
			case 'GET':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$result=['body'=>['content'=>[]]];
				if (array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions'])) $units = LANGUAGEFILE['units']; // see all orders
				else { // see only orders for own units
					$units = [];
					foreach($_SESSION['user']['units'] as $unit){
						$units[] = LANG::GET('units.' . $unit);
					}
				}
				// in clause doesnt work without manually preparing
				$query = strtr(SQLQUERY::PREPARE('order_get-approved-order'),
				[
					':organizational_unit' => "'".implode("','", $units)."'"
				]);
				$statement = $this->_pdo->prepare($query);
				$statement->execute();
				$order = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($order as $row) {
					$content = [];
					$text = '\n';
					foreach (json_decode($row['order_data']) as $key => $value){ // data
						$content[]=[
							'type' => 'textinput',
							'collapse' => true,
							'attributes' => [
								'value' => $value,
								'placeholder' => $this->fields[$key],
								'readonly' => true,
								'onpointerdown' => 'orderClient.toClipboard(this)'
							]
						];
					}
					$text .= $this->fields['organizational_unit'] . ': ' . $row['organizational_unit'] . '\n';
					$text .= LANG::GET('order.approved') . ': ' . $row['approved'] . ' ';
					if (!str_contains($row['approval'], 'data:image/png')) $text .= $row['approval'] . '\n';
					if ($row['ordered']) $text .= LANG::GET('order.ordered') . ': ' . $row['ordered'] . '\n';
					if ($row['received']) $text .= LANG::GET('order.received') . ': ' . $row['received'] . '\n';

					$status=[];
					if (boolval($row['ordered']))
						$status[LANG::GET('order.ordered')] = ['disabled' => true, 'checked' => true];
					else
						$status[LANG::GET('order.ordered')] = ['onchange' => "api.purchase('put', 'approved', " . $row['id']. ", 'ordered'); this.disabled=true"];
					if (boolval($row['received']))
						$status[LANG::GET('order.received')] = ['disabled' => true, 'checked' => true];
					else
						$status[LANG::GET('order.received')] = ['onchange' => "api.purchase('put', 'approved', " . $row['id']. ", 'received'); this.disabled=true"];
					$content[]=[
						'type' => 'text',
						'content' => $text,
						'collapse' => true,
					];
					$content[]=[
						'type' => 'checkbox',
						'collapse' => true,
						'content' => $status
					];
					if (str_contains($row['approval'], 'data:image/png')){
						$content[]=[
							'type' => 'image',
							'collapse' => true,
							'description' => LANG::GET('order.approval_image'),
							'attributes' => [
								'name' => LANG::GET('order.approval_image'),
								'url' => $row['approval']]
						];
					}
					$content[]=[
						'type' => 'deletebutton',
						'collapse' => true,
						'description' => LANG::GET('order.delete_prepared_order'),
						'attributes' => [
							'type' => 'button',
							'onpointerdown' => "if (confirm(LANG.GET('order.delete_prepared_order_confirm'))) api.purchase('delete', 'approved', " . $row['id'] . ")" 
						]
					];
					$content[]=[
						'type' => 'cart',
						'collapse' => true
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
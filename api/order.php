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

// place and process orders
class ORDER extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_subMethod = null;
	private $_subMethodState = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedID = isset(REQUEST[2]) ? (REQUEST[2] != 'false' ? REQUEST[2]: null) : null;
		$this->_subMethod = isset(REQUEST[3]) ? REQUEST[3] : null;
		$this->_subMethodState = isset(REQUEST[4]) ? REQUEST[4] : null;
	}

	/**
	 *                                 _
	 *   ___ ___ ___ ___ ___ _ _ ___ _| |
	 *  | .'| . | . |  _| . | | | -_| . |
	 *  |__,|  _|  _|_| |___|\_/|___|___|
	 *      |_| |_|
	 * handle approved orders, set states and alert userGroups
	 */
	public function approved(){
		require_once('notification.php');
		$notifications = new NOTIFICATION;

		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				$order = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_id', [
					'values' => [
						':id' => intval($this->_requestedID)
					]
				]);
				$order = $order ? $order[0] : null;
				if (!$order) $this->response(['response' => [ 'id' => $this->_requestedID, 'msg' => $this->_lang->GET('order.not_found'), 'type' => 'error']]);
				if (!(PERMISSION::permissionFor('orderprocessing') || array_intersect(explode(',', $order['organizational_unit']), $_SESSION['user']['units']))) $this->response([], 401);

				// set order process states
				if (in_array($this->_subMethod, ['ordered', 'partially_received', 'received', 'partially_delivered', 'delivered', 'archived'])){
					switch ($this->_subMethod){
						case 'ordered':
							if ($order['ordertype'] === 'cancellation'){
								// ordered aka processed canellation orders are deleted immediately 
								$this->orderStatistics($this->_requestedID); // write to statistics about a cancelled order
								if ($this->delete_approved_order($order)) {
									$result = [
									'response' => [
										'id' => false,
										'msg' => $this->_lang->GET('order.deleted'),
										'type' => 'success'
									],
									'data' => ['order_prepared' => $notifications->preparedorders(), 'order_unprocessed' => $notifications->order(), 'consumables_pendingincorporation' => $notifications->consumables()]];
								}
								else $result = [
									'response' => [
										'id' => $this->_requestedID,
										'msg' => $this->_lang->GET('order.failed_delete'),
										'type' => 'error'
									]];
								$this->response($result);
							}
							elseif ($order['ordertype'] === 'return') {
								// ordered aka processed return orders are received immediately
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
						case 'partially_received':
							$query = 'order_put_approved_order_partially_received';
							break;
						case 'received':
							$query = 'order_put_approved_order_received';
							break;
						case 'partially_delivered':
							$query = 'order_put_approved_order_partially_delivered';
							break;
						case 'delivered':
							// sets last order date for next overview
							$decoded_order_data = json_decode($order['order_data'], true);
							if (isset($decoded_order_data['ordernumber_label']) && isset($decoded_order_data['vendor_label'])){
								$product = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product_by_article_no_vendor', [
									'values' => [
										':article_no' => $decoded_order_data['ordernumber_label'],
										':vendor' => $decoded_order_data['vendor_label']
									]
								]);
								$product = $product ? $product[0] : null;
								if ($product) 
								SQLQUERY::EXECUTE($this->_pdo, 'consumables_put_last_order', [
									'values' => [
										':id' => $product['id']
									]
								]);
							}
							$query = 'order_put_approved_order_delivered';
							break;
						case 'archived':
							$query = 'order_put_approved_order_archived';
							break;
					}
					// generic state update
					SQLQUERY::EXECUTE($this->_pdo, $query, [
						'values' => [
							':id' => $this->_requestedID
						],
						'replacements' => [
							':state' => $this->_subMethodState === 'true' ? 'CURRENT_TIMESTAMP': 'NULL'
						]
					]);
				}

				// disapprove, addinformation, cancel, return order
				else {
					// resolve order data
					$decoded_order_data = json_decode($order['order_data'], true);
					// prepare possible keys
					$prepared = [
						'items' => [[]],
						'additional_info' => null,
						'organizational_unit' => $order['organizational_unit'],
						'commission' => null,
						'orderer' => null,
						'delivery_date' => null,
						'order_type' => null,
						'attachments' => null
					];
					// fill possible keys
					foreach ($decoded_order_data as $key => $value){
						if (array_key_exists($key, $prepared)) $prepared[$key] = $value;
						else $prepared['items'][0][$key] = $value;
					}
					// add initially approval date
					$prepared['additional_info'] .= ($prepared['additional_info'] ? "\n": '') . $this->_lang->GET('order.approved_on', [], true) . ': ' . $order['approved'];
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
							$messagepayload = [];
							foreach (['quantity'=> 'quantity_label',
								'unit' => 'unit_label',
								'number' => 'ordernumber_label',
								'name' => 'productname_label',
								'vendor' => 'vendor_label',
								'aut_idem' => 'aut_idem',
								'commission' => 'commission'] as $key => $value){
								$messagepayload[':' . $key] = isset($decoded_order_data[$value]) ? str_replace("\n", '\\\\n', $decoded_order_data[$value]) : '';
							}
							$messagepayload[':info'] = isset($decoded_order_data['additional_info']) ? $decoded_order_data['additional_info'] : '';
							$this->alertUserGroup(['unit' => [$prepared['organizational_unit']]], str_replace('\n', ', ', $this->_lang->GET('order.alert_disapprove_order', [
								':order' => $this->_lang->GET('order.message', $messagepayload, true),
								':unit' => $this->_lang->GET('units.' . $prepared['organizational_unit'], [], true),
								':user' => '<a href="javascript:void(0);" onpointerup="_client.message.newMessage(\'' . $this->_lang->GET('message.reply', [':user' => $_SESSION['user']['name']]). '\', \'' . $_SESSION['user']['name'] . '\', \'' . str_replace("\n", ', ', $this->_lang->GET('order.message', $messagepayload, true) . ',' . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message'))) . '\')">' . $_SESSION['user']['name'] . '</a>'
							], true)) . "\n \n" . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message')));
							break;
						case 'addinformation':
							// append information to order
							if (isset($decoded_order_data['additional_info'])){
								$decoded_order_data['additional_info'] .= "\n" . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('order.additional_info'));
							}
							else $decoded_order_data['additional_info'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('order.additional_info'));

							SQLQUERY::EXECUTE($this->_pdo, 'order_put_approved_order_addinformation', [
								'values' => [
									':order_data' => json_encode($decoded_order_data, JSON_UNESCAPED_SLASHES),
									':id' => intval($this->_requestedID)
								]
							]);

							// alert userGroup on vendor sided order-state change 
							if (str_starts_with(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('order.additional_info')), $this->_lang->GET('order.orderstate_description'))){
								// inform user group
								$messagepayload = [];
								foreach (['quantity'=> 'quantity_label',
									'unit' => 'unit_label',
									'number' => 'ordernumber_label',
									'name' => 'productname_label',
									'vendor' => 'vendor_label',
									'commission' => 'commission'] as $key => $value){
									$messagepayload[':' . $key] = isset($decoded_order_data[$value]) ? str_replace("\n", '\\\\n', $decoded_order_data[$value]) : '';
								}
								$messagepayload[':info'] = isset($decoded_order_data['additional_info']) ? $decoded_order_data['additional_info'] : '';
								$this->alertUserGroup(['unit' => [$prepared['organizational_unit']]], str_replace('\n', ', ', $this->_lang->GET('order.alert_orderstate_change', [
									':order' => $this->_lang->GET('order.message', $messagepayload, true),
									':unit' => $this->_lang->GET('units.' . $prepared['organizational_unit'], [], true),
									':user' => '<a href="javascript:void(0);" onpointerup="_client.message.newMessage(\'' . $this->_lang->GET('message.reply', [':user' => $_SESSION['user']['name']]). '\', \'' . $_SESSION['user']['name'] . '\', \'' . str_replace("\n", ', ', $this->_lang->GET('order.message', $messagepayload, true)) . '\')">' . $_SESSION['user']['name'] . '</a>',
								])));
							}
							break;
						case 'cancellation':
							// append to information
							if (isset($decoded_order_data['additional_info'])){
								$decoded_order_data['additional_info'] .= "\n" . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message'));
							}
							else $decoded_order_data['additional_info'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message'));
							$decoded_order_data['additional_info'] .= "\n" . $this->_lang->GET('order.approved_on', [], true) . ': ' . $order['approved'];
							$decoded_order_data['orderer'] = $_SESSION['user']['name'];
							
							// rewrite order as cancelled type
							SQLQUERY::EXECUTE($this->_pdo, 'order_put_approved_order_cancellation', [
								'values' => [
									':order_data' => json_encode($decoded_order_data, JSON_UNESCAPED_SLASHES),
									':id' => intval($this->_requestedID)
								]
							]);

							// alert purchase on change
							$this->alertUserGroup(['permission' => ['purchase']], $this->_lang->GET('order.alert_purchase', [], true));		
							break;
						case 'return':
							// append to order info 
							if (isset($decoded_order_data['additional_info'])){
								$decoded_order_data['additional_info'] .= "\n" . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message'));
							}
							else $decoded_order_data['additional_info'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message'));
							$decoded_order_data['additional_info'] .= "\n" . $this->_lang->GET('order.approved_on', [], true) . ': ' . $order['approved'];
							$decoded_order_data['additional_info'] .= "\n" . $this->_lang->GET('order.order.received', [], true) . ': ' . $order['received'];
							$decoded_order_data['additional_info'] .= "\n" . $this->_lang->GET('order.order.delivered', [], true) . ': ' . $order['delivered'];
							$decoded_order_data['orderer'] = $_SESSION['user']['name'];

							// create a new order as return
							if (SQLQUERY::EXECUTE($this->_pdo, 'order_post_approved_order', [
								'values' => [
								':order_data' => json_encode($decoded_order_data),
								':organizational_unit' => $order['organizational_unit'],
								':approval' => $order['approval'],
								':ordertype' => 'return'
								]
							])) {
								$this->_requestedID = $this->_pdo->lastInsertId();
								$result = [
								'response' => [
									'id' => $this->_requestedID,
									'msg' => $this->_lang->GET('order.saved'),
									'type' => 'success'
								]];

								// alert purchase on "new order"
								$this->alertUserGroup(['permission'=>['purchase']], $this->_lang->GET('order.alert_purchase', [], true));
							}
							else $result = [
								'response' => [
									'id' => false,
									'msg' => $this->_lang->GET('order.failed_save'),
									'type' => 'error'
								]];
							break;
					}
				}
				// construct result toast
				$result = isset($result) ? $result: [
					'response' => [
						'msg' => in_array($this->_subMethod, ['addinformation', 'disapproved', 'cancellation']) ? $this->_lang->GET('order.order.' . $this->_subMethod) : $this->_lang->GET('order.order_type_' . ($this->_subMethodState === 'true' ? 'set' : 'revoked'), [':type' => $this->_lang->GET('order.order.' . $this->_subMethod)]),
						'type' => 'info'
					],
					'data' => ['order_prepared' => $notifications->preparedorders(), 'order_unprocessed' => $notifications->order(), 'consumables_pendingincorporation' => $notifications->consumables()]
				];

				// update order statistics
				$this->orderStatistics($this->_requestedID, ($this->_subMethod === 'ordered' && $this->_subMethodState === 'false') || $this->_subMethod === 'disapproved');

				break;
			case 'GET':
				// delete old received unarchived orders
				$old = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_delivered', [
					'values' => [
						':date_time' => date('Y-m-d h:i:s', time() - (CONFIG['lifespan']['order'] * 24 * 3600)),
					]
				]);
				foreach ($old as $row){
					$this->delete_approved_order($row);
				}

				$result = ['data' => ['order' => [], 'approval' => []]];
				// set available units
				if (PERMISSION::permissionFor('orderdisplayall')) $units = array_keys($this->_lang->_USER['units']); // see all orders
				else $units = $_SESSION['user']['units']; // display only orders for own units
					
				$allproducts_key = []; // for quicker matching and access
				$unincorporated = [];
				$incorporationdenied = [];
				$pendingincorporation = [];
				$special_attention = [];
				
				// gather product information on incorporation and special attention
				foreach(SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products') as $product) {
					$allproducts_key[$product['vendor_name'] . '_' . $product['article_no']] = $product;
					if ($product['special_attention']) $special_attention[] = $product['id'];
					if (!$product['incorporated']) {
						$unincorporated[] = $product['id'];
						continue;
					}
					$product['incorporated'] = json_decode($product['incorporated'] ? : '', true);
					if (isset($product['incorporated']['_denied'])) {
						$incorporationdenied[] = $product['id'];
						continue;
					}
					elseif (!PERMISSION::fullyapproved('incorporation', $product['incorporated'])) {
						$pendingincorporation[] = $product['id'];
					}
				}

				// get unchecked articles for MDR §14 sample check
				$validChecked = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_valid_checked');
				$notReusableChecked = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_not_reusable_checked');
				$sampleCheck = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_eligible_sample_check', ['replacements' => [
					':valid_checked' => implode(',', array_column($validChecked, 'vendor_id')),
					':not_reusable' => implode(',', array_column($notReusableChecked, 'id'))
				]]);

				// gather applicable order states
				$statechange = ['...' => ['value' => '']];
				foreach($this->_lang->_USER['order']['orderstate'] as $value){
					$statechange[$value] = [];
				}
				ksort($statechange);

				// get all approved orders by applicable units
				$order = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_unit', [
					'replacements' => [
						':organizational_unit' => implode(",", $units)
					]
				]);

				// request permissions once, avoiding repetitive comparisons within loop
				$permission = [
					'orderaddinfo' => PERMISSION::permissionFor('orderaddinfo'),
					'ordercancel' => PERMISSION::permissionFor('ordercancel') && !in_array('group', $_SESSION['user']['permissions']),
					'orderprocessing' => PERMISSION::permissionFor('orderprocessing')
				];

				// create array with reusable images to reduce payload (approval signatures if allowed per CONFIG)
				foreach($order as $row){
					if (str_contains($row['approval'], 'data:image/png') && !in_array($row['approval'], $result['data']['approval'])) $result['data']['approval'][] = $row['approval'];
				}
				foreach($order as $row) {
					$decoded_order_data = json_decode($row['order_data'], true);
					
					$product = null;
					if (isset($decoded_order_data['ordernumber_label']) && isset($decoded_order_data['vendor_label'] )){
						if (isset($allproducts_key[$decoded_order_data['vendor_label'] . '_' . $decoded_order_data['ordernumber_label']])){
							$product = $allproducts_key[$decoded_order_data['vendor_label'] . '_' . $decoded_order_data['ordernumber_label']];
						}
					}
					// data chunks to be assembled by js _client.order.approved()
					$data = [
						'id' => $row['id'],
						'ordertype' => $row['ordertype'],
						'ordertext' => $this->_lang->GET('order.organizational_unit') . ': ' . $this->_lang->GET('units.' . $row['organizational_unit']),
						'quantity' => UTILITY::propertySet($decoded_order_data, 'quantity_label') ? : null,
						'unit' => UTILITY::propertySet($decoded_order_data, 'unit_label') ? : null,
						'barcode' => UTILITY::propertySet($decoded_order_data, 'barcode_label') ? : null,
						'name' => UTILITY::propertySet($decoded_order_data, 'productname_label') ? : null,
						'vendor' => UTILITY::propertySet($decoded_order_data, 'vendor_label') ? : null,
						'aut_idem' => UTILITY::propertySet($decoded_order_data, 'aut_idem') ? $this->_lang->GET('order.aut_idem') : null,
						'ordernumber' => UTILITY::propertySet($decoded_order_data, 'ordernumber_label') ? : null,
						'commission' => UTILITY::propertySet($decoded_order_data, 'commission') ? : null,
						'approval' => null,
						'information' => null,
						'addinformation' => $permission['orderaddinfo'] || array_intersect([$row['organizational_unit']], $units),
						'lastorder' => $product && $product['last_order'] ? $this->_lang->GET('order.order_last_ordered', [':date' => substr($product['last_order'], 0, -9)]) : null,
						'orderer' => UTILITY::propertySet($decoded_order_data, 'orderer') ? : null,
						'organizationalunit' => $row['organizational_unit'],
						'orderstatechange' => ($row['ordered'] && !$row['received'] && !$row['delivered'] && ($permission['orderaddinfo'] || array_intersect([$row['organizational_unit']], $units))) ? $statechange : [],
						'state' => [],
						'disapprove' => (!($row['ordered'] || $row['received'] || $row['delivered']) && in_array($row['ordertype'], ['order', 'service'])),
						'cancel' => !in_array('group', $_SESSION['user']['permissions']) && ($row['ordered'] && !($row['received'] || $row['delivered']) && ($permission['ordercancel'] || array_intersect([$row['organizational_unit']], $_SESSION['user']['units']))),
						'return' => (($row['received'] || $row['delivered']) && $row['ordertype'] === 'order' && ($permission['ordercancel'] || array_intersect([$row['organizational_unit']], $_SESSION['user']['units']))),
						'attachments' => [],
						'delete' => !in_array('group', $_SESSION['user']['permissions']) && ($permission['ordercancel'] || array_intersect([$row['organizational_unit']], $_SESSION['user']['units'])),
						'autodelete' => null,
						'incorporation' => [],
						'samplecheck' => [],
						'specialattention' => $product ? array_search($product['id'], $special_attention) !== false : null,
						'collapsed' => !$permission['orderprocessing'],
						'addproduct' => null
					];

					// add identified group user
					if ($orderer_group_identify = UTILITY::propertySet($decoded_order_data, 'orderer_group_identify')){
						$data['ordertext'] .= "\n" .$this->_lang->GET('order.orderer_group_identify') . ': ' . $orderer_group_identify;
					}

					// add approval
					$data['ordertext'] .= "\n" . $this->_lang->GET('order.order.approved') . ': ' . $row['approved'] . ' ';
					if (!str_contains($row['approval'], 'data:image/png')) {
						$data['ordertext'] .= "\n". $row['approval'];
					} else {
						$data['approval'] = array_search($row['approval'], $result['data']['approval']);
					}

					// add additional info
					if ($additional_information = UTILITY::propertySet($decoded_order_data, 'additional_info')){
						$data['information'] = preg_replace(['/\r/', '/\\\n/'], ['', "\n"], $additional_information);
					}

					// append order processing states
					foreach(['ordered', 'partially_received', 'received', 'partially_delivered', 'delivered', 'archived'] as $s){
						if (!isset($data['state'][$s])) $data['state'][$s] = [];
						$data['state'][$s]['data-'.$s] = boolval($row[$s]) ? 'true' : 'false';
						if (boolval($row[$s])) {
							$data['ordertext'] .= "\n" . $this->_lang->GET('order.order.' . $s) . ': ' . $row[$s];
						}
						switch ($s){
							case 'ordered':
								if (!$row['received'] && $data['aut_idem']){
									$data['state'][$s]['onchange'] =
										"new _client.Dialog({type:'confirm', header:'" . 
										$this->_lang->GET('order.aut_idem_order_confirmation_header', [':user' => $data['orderer'], ':product' => $data['name']]) .
										"', render:'" . $this->_lang->GET('order.aut_idem_order_confirmation_render', [':user' => $data['orderer']]) .
										"', options:{'" . $this->_lang->GET('general.prevent_dataloss_cancel') . "': false, '" . $this->_lang->GET('general.prevent_dataloss_ok') . "': {'value': true, class: 'reducedCTA'}}}).then(confirmation => {" .
	 									"if (confirmation) {api.purchase('put', 'approved', '" . $data['id'] . "', '" . $s . "', this.checked); this.setAttribute('data-" . $s . "', this.checked.toString());}" .
										"else {this.checked = false; return}" .
										"});";
								}
							case 'received':
								if (!$permission['orderprocessing']){
								$data['state'][$s]['disabled'] = true;
								}
								break;
							case 'partially_received':
								if ($row['received'] || !$permission['orderprocessing']){
									$data['state'][$s]['disabled'] = true;
									}
								break;
							case 'partially_delivered':
								if ($row['delivered'] || !(array_intersect(['admin'], $_SESSION['user']['permissions']) || array_intersect([$row['organizational_unit']], $_SESSION['user']['units']))){
									$data['state'][$s]['disabled'] = true;
								}
								break;
							case 'delivered':
							case 'archived':
								if (!(array_intersect(['admin'], $_SESSION['user']['permissions']) || array_intersect([$row['organizational_unit']], $_SESSION['user']['units']))){
									$data['state'][$s]['disabled'] = true;
								}
								break;
						}
					}

					// order attachments
					if (isset($decoded_order_data['attachments'])){
						foreach(explode(',', $decoded_order_data['attachments']) as $file){
							$data['attachments'][pathinfo($file)['basename']] = ['href' => './api/api.php/file/stream/' . $file];
						}
					}

					// incorporation state
					if ($product && array_search($product['id'], $unincorporated) !== false){
						if (!in_array('group', $_SESSION['user']['permissions'])){
							$data['incorporation']['item'] = $product['id'];
						} else {
							// simple groups are not allowed to make records
							$data['incorporation']['state'] = $this->_lang->GET('order.incorporation.neccessary_by_user');
						}
					}
					elseif ($product && array_search($product['id'], $incorporationdenied) !== false){
						$data['incorporation']['state'] = $this->_lang->GET('order.incorporation.denied');
					}
					elseif ($product && array_search($product['id'], $pendingincorporation) !== false){
						$data['incorporation']['state'] = $this->_lang->GET('order.incorporation.pending');
					}
					
					// request MDR §14 sample check
					if ($product && array_search($product['id'], array_column($sampleCheck, 'id')) !== false){
						if (!in_array('group', $_SESSION['user']['permissions'])){
							$data['samplecheck']['item'] = $product['id'];
						} else {
							// simple groups are not allowed to make records
							$data['samplecheck']['state'] = $this->_lang->GET('order.sample_check.by_user');
						}
					}

					// request adding unknown product
					if (PERMISSION::permissionFor('products') && !PERMISSION::permissionFor('productslimited') && !$product){
						$data['addproduct'] = true;
					}

					array_push($result['data']['order'], array_filter($data, fn($property)=> $property || $property === 0));
				}
				break;
			case 'DELETE':
				$row = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_id', [
					'values' => [
						':id' => $this->_requestedID
					]
				]);
				$row = $row ? $row[0] : null;
				
				if ($row && !in_array('group', $_SESSION['user']['permissions']) && $this->delete_approved_order($row)) {
					$result = [
					'response' => [
						'id' => false,
						'msg' => $this->_lang->GET('order.deleted'),
						'type' => 'success'
					],
					'data' => ['order_prepared' => $notifications->preparedorders(), 'order_unprocessed' => $notifications->order(), 'consumables_pendingincorporation' => $notifications->consumables()]];
				}
				else $result = [
					'response' => [
						'id' => $this->_requestedID,
						'msg' => $this->_lang->GET('order.failed_delete'),
						'type' => 'error'
					]];
				break;
			}
		$this->response($result);
	}
	
	/**
	 *     _     _     _                                           _                 _
	 *   _| |___| |___| |_ ___       ___ ___ ___ ___ ___ _ _ ___ _| |      ___ ___ _| |___ ___
	 *  | . | -_| | -_|  _| -_|     | .'| . | . |  _| . | | | -_| . |     | . |  _| . | -_|  _|
	 *  |___|___|_|___|_| |___|_____|__,|  _|  _|_| |___|\_/|___|___|_____|___|_| |___|___|_|
	 *                        |_____|   |_| |_|                     |_____|
	 * delete order and attachments if not used by any other approved order
	 * @param array $row approved order from database
	 * 
	 * @return int number of affected rows
	 */
	private function delete_approved_order($row){
		$order = json_decode($row['order_data'], true);
		if (isset($order['attachments'])){
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
	
	/**
	 *   ___ _ _ _
	 *  |  _|_| | |_ ___ ___
	 *  |  _| | |  _| -_|  _|
	 *  |_| |_|_|_| |___|_|
	 *
	 * filter orders by search
	 */
	public function filter(){
		if (PERMISSION::permissionFor('orderdisplayall')) $units = array_keys($this->_lang->_USER['units']); // see all orders
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
		// return order ids
		$this->response( [
			'data' => $matches
		]);
	}
	
	/**
	 *             _
	 *   ___ ___ _| |___ ___
	 *  | . |  _| . | -_|  _|
	 *  |___|_| |___|___|_|
	 *
	 * create a new order or edit a prepared one
	 */
	public function order(){
		require_once('notification.php');
		$notifications = new NOTIFICATION;
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$processedOrderData = $this->processOrderForm();

				// check whether an approval has been submitted
				if (!$processedOrderData['approval']){
					SQLQUERY::EXECUTE($this->_pdo, 'order_post_prepared_order', [
						'values' => [
							':order_data' => json_encode($processedOrderData['order_data'], JSON_UNESCAPED_SLASHES)
						]
					]);
					$result = [
						'response' => [
							'id' => $this->_pdo->lastInsertId(),
							'msg' => $this->_lang->GET('order.saved_to_prepared'),
							'type' => 'info'
						],
						'data' => ['order_prepared' => $notifications->preparedorders(), 'order_unprocessed' => $notifications->order(), 'consumables_pendingincorporation' => $notifications->consumables()]
					];
					break;
				}

				// else process approved order
				$result = $this->postApprovedOrder($processedOrderData);

				break;
			case 'PUT':
				$processedOrderData = $this->processOrderForm();

				// check whether an approval has been submitted
				if (!$processedOrderData['approval']){
					SQLQUERY::EXECUTE($this->_pdo, 'order_put_prepared_order', [
						'values' => [
							':order_data' => json_encode($processedOrderData['order_data'], JSON_UNESCAPED_SLASHES),
							':id' => $this->_requestedID
						]
					]);
					$result = [
						'response' => [
							'id' => $this->_requestedID,
							'msg' => $this->_lang->GET('order.saved_to_prepared'),
							'type' => 'info'
						]
					];
					break;
				}

				// else process approved order
				$result = $this->postApprovedOrder($processedOrderData);
				
				// delete prepared order if successfully approved
				if ($result['response']['msg'] === $this->_lang->GET('order.saved')){
					SQLQUERY::EXECUTE($this->_pdo, 'order_delete_prepared_orders', [
						'replacements' => [
							':id' => intval($this->_requestedID)
						]
					]);
					$result['data'] = ['order_prepared' => $notifications->preparedorders(), 'order_unprocessed' => $notifications->order(), 'consumables_pendingincorporation' => $notifications->consumables()];
				}
				break;
			case 'GET':
				$datalist = [];
				$datalist_unit = [];
				$vendors = [];

				// prepare existing vendor lists
				$vendor = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
				$vendors[$this->_lang->GET('consumables.product.search_all_vendors')] = ['value' => implode('_', array_map(fn($r) => $r['id'], $vendor))];
				foreach($vendor as $key => $row) {
					if ($row['hidden']) continue;
					$datalist[] = $row['name'];
					$vendors[$row['name']] = ['value' => $row['id']];
				}
				ksort($vendors);

				// prepare existing sales unit lists
				$vendor = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product_units');
				foreach($vendor as $key => $row) {
					$datalist_unit[] = $row['article_unit'];
				}

				// get a prepared order if id is provided
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
						'order_type' => 'order',
						'items' => false
					];
				} else {
					$order = json_decode($order['order_data'], true);
				}

				// prepare organizational unit selection
				$organizational_units = [];
				foreach($this->_lang->_USER['units'] as $unit => $description){
					$organizational_units[$description] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'required' => true];
					if (isset($order['organizational_unit']) && in_array($unit, explode(',', $order['organizational_unit']))) $organizational_units[$description]['checked'] = true;
					elseif (isset($_SESSION['user']['app_settings']['primaryUnit'])) $organizational_units[$this->_lang->GET('units.' . $_SESSION['user']['app_settings']['primaryUnit'])]['checked'] = true;
				}

				// prepare order type selection
				$order_type = [];
				foreach($this->_lang->_USER['order']['ordertype'] as $key => $description){
					$order_type[$description] = ['value' => $key];
					if (isset($order['order_type']) && $order['order_type'] == $key) $order_type[$description]['selected'] = true;
				}

				// prepare authorization options as per CONFIG
				$authorize = [
					[
						[
							'type' => 'number',
							'attributes' => [
								'name' => $this->_lang->GET('user.order_authorization'),
								'type' => 'password'
							]
						]
					]
				];
				if (preg_match('/token/i', CONFIG['application']['order_auth'])) $authorize[] = [
					[
						'type' => 'scanner',
						'attributes' => [
							'name' => $this->_lang->GET('user.token'),
							'type' => 'password'
						]
					]
				];
				if (preg_match('/signature/i', CONFIG['application']['order_auth'])) $authorize[] = [
					[
						'type' => 'signature',
						'attributes' => [
							'name' => $this->_lang->GET('order.add_approval_signature')
						]
					]
				];

				$result['render'] = ['form' => [
					'data-usecase'=> 'purchase',
					'action' => $this->_requestedID ? "javascript:api.purchase('put', 'order', '" . $this->_requestedID . "')" : "javascript:api.purchase('post', 'order')"
				],
				'content' => [
					[
						[
							'type' => 'scanner',
							'destination' => 'productsearch'
						], [
							'type' => 'select',
							'content' => $vendors,
							'attributes' => [
								'id' => 'productsearchvendor',
								'name' => $this->_lang->GET('consumables.product.vendor_select')
							]
						], [
							'type' => 'search',
							'attributes' => [
								'name' => $this->_lang->GET('consumables.product.search'),
									'onkeypress' => "if (event.key === 'Enter') {api.purchase('get', 'productsearch', document.getElementById('productsearchvendor').value, this.value); return false;}",
								'onblur' => "if (this.value) {api.purchase('get', 'productsearch', document.getElementById('productsearchvendor').value, this.value); return false;}",
								'id' => 'productsearch'
							]
						], [
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('order.add_manually'),
								'type' => 'button',
								'onpointerup' => "new _client.Dialog({type: 'input', header: '". $this->_lang->GET('order.add_manually') ."', render: JSON.parse('".
								json_encode([
									[
										[
											'type' => 'number',
											'attributes' => [
												'name' => $this->_lang->GET('order.quantity_label'),
											]
										], [
											'type' => 'text',
											'attributes' => [
												'name' => $this->_lang->GET('order.unit_label'),
											],
											'datalist' => array_values(array_unique($datalist_unit))
										], [
											'type' => 'text',
											'attributes' => [
												'name' => $this->_lang->GET('order.ordernumber_label')
											]
										], [
											'type' => 'text',
											'attributes' => [
												'name' => $this->_lang->GET('order.productname_label')
											]
										], [
											'type' => 'text',
											'attributes' => [
												'name' => $this->_lang->GET('order.vendor_label'),
											],
											'datalist' => array_values(array_unique($datalist))
										]
									]
								])
								."'), options:{".
									"'".$this->_lang->GET('order.add_manually_confirm')."': true,".
									"'".$this->_lang->GET('order.add_manually_cancel')."': {value: false, class: 'reducedCTA'},".
								"}}).then(response => {if (Object.keys(response).length) {".
									"_client.order.addProduct(response[LANG.GET('order.quantity_label')] || '', response[LANG.GET('order.unit_label')] || '', response[LANG.GET('order.ordernumber_label')] || '', response[LANG.GET('order.productname_label')] || '', response[LANG.GET('order.barcode_label')] || '', response[LANG.GET('order.vendor_label')] || '');".
									"api.preventDataloss.monitor = true;}".
									"document.getElementById('modal').replaceChildren()})", // clear modal to avoid messing up input names
						]]
					],[
						['type' => 'hr']
					],[
						[
							'type' => 'radio',
							'attributes' => [
								'name' => $this->_lang->GET('order.organizational_unit')
							],
							'content' => $organizational_units
						], [
							'type' => 'text',
							'hint' => $this->_lang->GET('order.commission_hint'),
							'attributes' => [
								'required' => true,
								'name' => $this->_lang->GET('order.commission'),
								'value' => isset($order['commission']) ? $order['commission'] : '',
								'data-loss' => 'prevent',
								'id' => 'commission'
							]
						], [
							'type' => 'scanner',
							'destination' => 'commission'
						], [
							'type' => 'select',
							'content' => $order_type,
							'attributes' => [
								'name' => $this->_lang->GET('order.order_type')
							]
						], [
							'type' => 'date',
							'attributes' => [
								'name' => $this->_lang->GET('order.delivery_date'),
								'value' => isset($order['delivery_date']) ? $order['delivery_date'] : ''
							]
						], [
							'type' => 'textarea',
							'attributes' => [
								'name' => $this->_lang->GET('order.additional_info'),
								'value' => isset($order['additional_info']) ? preg_replace('/\\\\n/', "\n", $order['additional_info']) : '',
								'data-loss' => 'prevent'
							]
						]
					],[
						[
							[
								'type' => 'file',
								'attributes' => [
									'name' => $this->_lang->GET('order.attach_file'),
									'multiple' => true
								]
							]
						],[
							[
								'type' => 'photo',
								'attributes' => [
									'name' => $this->_lang->GET('order.attach_photo'),
									'multiple' => true
								]
							]
						]
					],
					$authorize,
				]];

				// append identification for group members
				if (array_intersect(['group'], $_SESSION['user']['permissions'])){
					array_splice($result['render']['content'][2], 1, 0, [[
							'type' => 'text',
							'hint' => $this->_lang->GET('order.orderer_group_hint'),
							'attributes' => [
								'name' => $this->_lang->GET('order.orderer_group_identify'),
								'required' => true,
								'value' => isset($order['orderer_group_identify']) ? $order['orderer_group_identify'] : '',
							]
						]]
					);
				
				}

				// append existing attachments
				if (isset($order['attachments'])){
					foreach(explode(',', $order['attachments']) as $file){
						$files[pathinfo($file)['basename']] = ['href' => './api/api.php/file/stream/' . $file, 'target' => '_blank'];
					}
					array_splice($result['render']['content'], 4, 0, [
						[
							[
								'type' => 'links',
								'description' => $this->_lang->GET('order.attached_files'),
								'content' => $files
							], [
								'type' => 'hidden',
								'attributes' => [
									'name' => 'existingattachments',
									'value' => $order['attachments']
								]
							]
						]
					]);
				}

				// cart-content has a twin within utility.js _client.order.addProduct() method
				if ($order['items']){
					$items = [];
					for ($i = 0; $i < count($order['items']); $i++){
						array_push($items,
						[
							[
								'type' => 'number',
								'attributes' => [
									'name' => $this->_lang->GET('order.quantity_label') . '[]',
									'value' => UTILITY::propertySet($order['items'][$i], 'quantity_label') ? : ' ',
									'min' => '1',
									'max' => '99999',
									'required' => true,
									'data-loss' => 'prevent'
								]
							],
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $this->_lang->GET('order.added_product', [
										':unit' => UTILITY::propertySet($order['items'][$i], 'unit_label') ? : '',
										':number' => UTILITY::propertySet($order['items'][$i], 'ordernumber_label') ? : '',
										':name' => UTILITY::propertySet($order['items'][$i], 'productname_label') ? : '',
										':vendor' => UTILITY::propertySet($order['items'][$i], 'vendor_label') ? : ''
									])
								]
							],
							[
								'type' => 'hidden',
								'attributes' => [
									'name' => $this->_lang->GET('order.unit_label') . '[]',
									'value' => UTILITY::propertySet($order['items'][$i], 'unit_label') ? : ' '
								]
							],
							[
								'type' => 'hidden',
								'attributes' => [
									'name' => $this->_lang->GET('order.ordernumber_label') . '[]',
									'value' => UTILITY::propertySet($order['items'][$i], 'ordernumber_label') ? : ' '
								]
							],
							[
								'type' => 'hidden',
								'attributes' => [
									'name' => $this->_lang->GET('order.productname_label') . '[]',
									'value' => UTILITY::propertySet($order['items'][$i], 'productname_label') ? : ' '
								]
							],
							[
								'type' => 'hidden',
								'attributes' => [
									'name' => $this->_lang->GET('order.barcode_label') . '[]',
									'value' => UTILITY::propertySet($order['items'][$i], 'barcode_label') ?  : ' '
								]
							],
							[
								'type' => 'hidden',
								'attributes' => [
									'name' => $this->_lang->GET('order.vendor_label') . '[]',
									'value' => UTILITY::propertySet($order['items'][$i], 'vendor_label') ? : ' '
								]
							],
							[
								'type' => 'checkbox',
								'inline' => true,
								'attributes' => [
									'name' => $this->_lang->GET('order.aut_idem') . '[]'
								],
								'content' => [
									$this->_lang->GET('order.aut_idem') => UTILITY::propertySet($order['items'][$i], 'aut_idem') ? ['checked' => true] : []
								]
							],
							[
								'type' => 'deletebutton',
								'attributes' => [
									'value' => $this->_lang->GET('order.add_delete'),
									'onpointerup' => 'this.parentNode.remove()'
								]
							]
						]);
					}
					array_splice($result['render']['content'], 2, 0, $items);
				}

				// append delete button
				if ($this->_requestedID) array_push($result['render']['content'], [
					['type' => 'deletebutton',
					'attributes' => [
						'value' => $this->_lang->GET('order.delete_prepared_order'),
						'type' => 'button', // apparently defaults to submit otherwise
						'onpointerup' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('order.delete_prepared_order_confirm_header') ."', options:{".
							"'".$this->_lang->GET('order.delete_prepared_order_confirm_cancel')."': false,".
							"'".$this->_lang->GET('order.delete_prepared_order_confirm_ok')."': {value: true, class: 'reducedCTA'},".
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
				if (isset($order['attachments'])){
					$files = explode(',', $order['attachments']);
					UTILITY::delete(array_map(fn($value) => '.' . $value, $files));
				}

				// delete prepared order
				if (SQLQUERY::EXECUTE($this->_pdo, 'order_delete_prepared_orders', [
					'replacements' => [
						':id' => intval($this->_requestedID)
					]
				])) {
					$result = [
					'response' => [
						'id' => false,
						'msg' => $this->_lang->GET('order.deleted'),
						'type' => 'success'
					]];
				}
				else $result = [
					'response' => [
						'id' => $this->_requestedID,
						'msg' => $this->_lang->GET('order.failed_delete'),
						'type' => 'error'
					]];
				break;
			}
		$this->response($result);
	}
	
	/**
	 *             _             _       _   _     _   _
	 *   ___ ___ _| |___ ___ ___| |_ ___| |_|_|___| |_|_|___ ___
	 *  | . |  _| . | -_|  _|_ -|  _| .'|  _| |_ -|  _| |  _|_ -|
	 *  |___|_| |___|___|_| |___|_| |__,|_| |_|___|_| |_|___|___|
	 *
	 * post to order statistics once an order is processed
	 * reduces order data and updates received state
	 * deletes entry if processed state is revoked
	 * @param int $order_id primary database key
	 * @param bool $delete
	 */
	private function orderStatistics($order_id, $delete = false){
		if (!$order_id) return;
		// delete from statistics if requested in case of revoked ordered state or disapproval
		if ($delete) {
			SQLQUERY::EXECUTE($this->_pdo, 'order_delete_order_statistics', [
				'values' => [
					':order_id' => intval($order_id)
				]
			]);
			return;
		}

		// get approved order to gather data
		$order = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_id', [
			'values' => [
				':id' => intval($order_id)
			]
		]);
		$order = $order ? $order[0] : null;
		if (!$order) return;
		// minimize order data
		$order['order_data'] = json_decode($order['order_data'], true);
		foreach($order['order_data'] as $key => $value){
			if (!in_array($key, [
				'quantity_label',
				'unit_label',
				'ordernumber_label',
				'productname_label',
				'vendor_label',
				'additional_info'])) unset($order['order_data'][$key]);
		}
		$order['order_data'] = json_encode($order['order_data']);
		
		// update or insert order statistics
		SQLQUERY::EXECUTE($this->_pdo, 'order_post_order_statistics', [
			'values' => [
				':order_id' => intval($order_id),
				':order_data' => $order['order_data'],
				':ordered' => $order['ordered'],
				':ordertype' => $order['ordertype']
			],
			'replacements' => [
				':partially_received' => $order['partially_received'] ? : ($order['partially_received'] ? : 'NULL'),
				':received' => $order['received'] ? : ($order['delivered'] ? : 'NULL'),
			]
		]);
	}
	
	/**
	 *               _                                 _           _
	 *   ___ ___ ___| |_ ___ ___ ___ ___ ___ _ _ ___ _| |___ ___ _| |___ ___
	 *  | . | . |_ -|  _| .'| . | . |  _| . | | | -_| . | . |  _| . | -_|  _|
	 *  |  _|___|___|_| |__,|  _|  _|_| |___|\_/|___|___|___|_| |___|___|_|
	 *  |_|                 |_| |_|
	 * prepare orderdata for database
	 * @param array $processedOrderDate
	 * 
	 * @return array render response
	 */
	private function postApprovedOrder($processedOrderData){
		$keys = array_keys($processedOrderData['order_data']);
		$order_data2 = [];
		$sqlchunks = [];
		// iterate over items and create one order per item
		for ($i = 0; $i < count($processedOrderData['order_data']['items']); $i++){
			$order_data2 = $processedOrderData['order_data']['items'][$i];
			foreach ($keys as $key){
				if (!in_array($key, ['items', 'organizational_unit'])) $order_data2[$key] = $processedOrderData['order_data'][$key];
			}
			$sqlchunks = SQLQUERY::CHUNKIFY($sqlchunks, strtr(SQLQUERY::PREPARE('order_post_approved_order'),
			[
				':order_data' => $this->_pdo->quote(json_encode($order_data2, JSON_UNESCAPED_SLASHES)),
				':organizational_unit' => $this->_pdo->quote($processedOrderData['order_data']['organizational_unit']),
				':approval' => $this->_pdo->quote($processedOrderData['approval']),
				':ordertype' => $this->_pdo->quote($processedOrderData['order_data']['order_type'])
			]) . '; ');
		}
		$success = false;
		foreach ($sqlchunks as $chunk){
			try {
				$success = SQLQUERY::EXECUTE($this->_pdo, $chunk);
			}
			catch (Exception $e) {
				echo $e, $chunk;
				die();
			}
		}
		if ($success) {
			$result = [
				'response' => [
					'id' => false,
					'msg' => $this->_lang->GET('order.saved'),
					'type' => 'success'
				]
			];
			// alert user group on new orders
			$this->alertUserGroup(['permission'=>['purchase']], $this->_lang->GET('order.alert_purchase', [], true));		
		}
		else $result = [
			'response' => [
				'id' => false,
				'msg' => $this->_lang->GET('order.failed_save'),
				'type' => 'error'
			]];
		return $result;
	}
	
	/**
	 *                                 _
	 *   ___ ___ ___ ___ ___ ___ ___ _| |
	 *  | . |  _| -_| . | .'|  _| -_| . |
	 *  |  _|_| |___|  _|__,|_| |___|___|
	 *  |_|         |_|
	 * display and approve prepared orders
	 */
	public function prepared(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				// verify approval
				$approval = false;
				if ($orderauth = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.order_authorization'))){
					$result = SQLQUERY::EXECUTE($this->_pdo, 'user_get_orderauth', [
						'values' => [
							':orderauth' => $orderauth
						]
					]);
					$result = $result ? $result[0] : null;
					if ($result){
						$approval = $result['name'] . $this->_lang->GET('order.orderauth_verified');
					}
				}
				elseif ($orderauth = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.token'))){
					$result = SQLQUERY::EXECUTE($this->_pdo, 'application_login', [
						'values' => [
							':token' => $orderauth
						]
					]);
					$result = $result ? $result[0] : null;
					if ($result && $result['orderauth']){
						$approval = $result['name'] . $this->_lang->GET('order.token_verified');
					}
				}
				elseif (isset($_FILES[$this->_lang->PROPERTY('order.add_approval_signature')]) && $_FILES[$this->_lang->PROPERTY('order.add_approval_signature')]['tmp_name']){
					$signature = gettype($_FILES[$this->_lang->PROPERTY('order.add_approval_signature')]['tmp_name'])=='array' ? $_FILES[$this->_lang->PROPERTY('order.add_approval_signature')]['tmp_name'][0] : $_FILES[$this->_lang->PROPERTY('order.add_approval_signature')]['tmp_name'];
					$approval = 'data:image/png;base64,' . base64_encode(UTILITY::alterImage($signature, CONFIG['limits']['order_approvalsignature_image'], UTILITY_IMAGE_RESOURCE, 'png'));
				}
				if (!$approval) $this->response([], 401);

				// gather all approved prepared order
				$approvedIDs = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('order.bulk_approve_order'));
				if (!$approvedIDs) $this->response([], 406);

				$order_data = ['items' => []];

				// iterate over prepared orders
				$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_prepared_orders');
				$index = 0;
				foreach ($orders as $order){
					// check if contained in approved
					if (array_search($order['id'], $approvedIDs) === false) continue;

					// create itemized order data from prepared order
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

				// if successfully posted as approved delete prepared order
				if ($result['response']['msg'] === $this->_lang->GET('order.saved')){
					SQLQUERY::EXECUTE($this->_pdo, 'order_delete_prepared_orders', [
						'replacements' => [
							':id' => implode(",", array_map(fn($id) => intval($id), $approvedIDs))
						]
					]);
					require_once('notification.php');
					$notifications = new NOTIFICATION;
					$result['data'] = ['order_prepared' => $notifications->preparedorders(), 'order_unprocessed' => $notifications->order(), 'consumables_pendingincorporation' => $notifications->consumables()];
				}
				break;
			case 'GET':
				$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_prepared_orders');
				// display all orders assigned to organizational unit
				if ($this->_requestedID) $units = [$this->_requestedID]; // see orders from selected unit
				elseif (PERMISSION::permissionFor('orderdisplayall')) $units = array_keys($this->_lang->_USER['units']); // see all orders
				else $units = $_SESSION['user']['units']; // see only orders for own units

				// filter by organizationla unit
				$organizational_orders = [];
				foreach($orders as $key => $row) {
					$order_data = json_decode($row['order_data'], true);
					if (array_intersect([$order_data['organizational_unit']], $units)) {
						array_push($organizational_orders, $row);
					}
				}
				$result = ['render' => ['content' => []]];

				// users with order authorization can access all prepared orders by request
				if ($_SESSION['user']['orderauth']){
					$organizational_units = [];
					foreach($this->_lang->_USER['units'] as $unit => $description){
						$organizational_units[$description] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'onchange' => "api.purchase('get', 'prepared', '" . $unit . "')"];
					}
					if (!$this->_requestedID && isset($_SESSION['user']['app_settings']['primaryUnit'])) $organizational_units[$this->_lang->GET('units.' . $_SESSION['user']['app_settings']['primaryUnit'])]['checked'] = true;
					elseif($this->_requestedID) $organizational_units[$this->_lang->GET('units.' . $this->_requestedID)]['checked'] = true;
					$result['render']['content'][] = [
						['type' => 'radio',
						'attributes' => [
							'name' => $this->_lang->GET('order.organizational_unit')
						],
						'content' => $organizational_units
						]
					];
				}

				// display selected prepared orders 
				if (count($organizational_orders)){
					foreach($organizational_orders as $order){ // order
						$items = $info = '';
						$order_attributes = [];
						$processedOrderData = json_decode($order['order_data'], true);
						foreach ($processedOrderData as $key => $value){ // data
							if (is_array($value)){
								foreach($value as $item){
									$items .= $this->_lang->GET('order.prepared_order_item', [
										':quantity' => UTILITY::propertySet($item, 'quantity_label') ? : '',
										':unit' => UTILITY::propertySet($item, 'unit_label') ? : '',
										':number' => UTILITY::propertySet($item, 'ordernumber_label') ? : '',
										':name' => UTILITY::propertySet($item, 'productname_label') ? : '',
										':vendor' => UTILITY::propertySet($item, 'vendor_label') ? : '',
										':aut_idem' => UTILITY::propertySet($item, 'aut_idem') ? $this->_lang->GET('order.aut_idem') : ''
									])."\n";
								}
							} else {
								if ($key === 'attachments') continue;
								if ($key === 'organizational_unit') $value = $this->_lang->GET('units.' . $value);
								if ($key === 'order_type') {
									$order_attributes = [
										'name' => $this->_lang->GET('order.ordertype.' . $value),
										'data-type' => $value
									];
									$value = $this->_lang->GET('order.ordertype.' . $value);
								}

								$info .= $this->_lang->GET('order.' . $key) . ': ' . $value . "\n";
							}
						}
						array_push($result['render']['content'], [
							[
								'type' => 'textsection',
								'attributes' => $order_attributes,
								'content' => $items,
							],[
								'type' => 'textsection',
								'content' => $info,
							], [
								'type' => 'checkbox',
								'content' => [
									$this->_lang->GET('order.bulk_approve_order'). '[]' => ['value' => $order['id']]
								]
							], [
								'type' => 'button',
								'attributes' =>[
									'value' => $this->_lang->GET('order.edit_prepared_order'),
									'type' => 'button',
									'onpointerup' => "api.purchase('get', 'order', " . $order['id']. ")"
								]
							]
						]);

						// append attachments
						if (isset($processedOrderData['attachments'])){
							$files = [];
							foreach(explode(',', $processedOrderData['attachments']) as $file){
								$files[pathinfo($file)['basename']] = ['href' => './api/api.php/file/stream/' . $file, 'target' => '_blank'];
							}
							array_splice($result['render']['content'][count($result['render']['content']) - 1], 2, 0, [
								[
									[
										'type' => 'links',
										'description' => $this->_lang->GET('order.attached_files'),
										'content' => $files
									], [
										'type' => 'hidden',
										'attributes' => [
											'name' => 'existingattachments',
											'value' => $processedOrderData['attachments']
										]
									],
									['type' => 'br']
								]
							]);		
						}
					}

					// append authorization methods
					if (count($organizational_orders)){
						$authorize = [];
						if ($_SESSION['user']['orderauth']) {
							$authorize[] = [
								[
									'type' => 'number',
									'attributes' => [
										'name' => $this->_lang->GET('user.order_authorization'),
										'type' => 'password'
									]
								]
							];
							if (preg_match('/token/i', CONFIG['application']['order_auth'])) $authorize[] = [
								[
									'type' => 'scanner',
									'attributes' => [
										'name' => $this->_lang->GET('user.token'),
										'type' => 'password'
									]
								]
							];
						}
						if (preg_match('/signature/i', CONFIG['application']['order_auth'])) $authorize[] = [
							[
								'type' => 'signature',
								'attributes' => [
									'name' => $this->_lang->GET('order.add_approval_signature')
								]
							]
						];
						// append if any authorization methods and orders are available
						if ($authorize){
							array_push($result['render']['content'], $authorize);
							$result['render']['form'] = ['action' => "javascript:api.purchase('put', 'prepared')", 'data-usecase' => 'purchase'];	
						}
					}
				}
				else $result['render']['content'][] = $this->noContentAvailable($this->_lang->GET('order.no_orders'))[0];
				break;
		}
		$this->response($result);
	}

	/**
	 *                                         _         ___
	 *   ___ ___ ___ ___ ___ ___ ___ ___ ___ _| |___ ___|  _|___ ___ _____
	 *  | . |  _| . |  _| -_|_ -|_ -| . |  _| . | -_|  _|  _| . |  _|     |
	 *  |  _|_| |___|___|___|___|___|___|_| |___|___|_| |_| |___|_| |_|_|_|
	 *  |_|
	 */
	private function processOrderForm(){
		$unset = $this->_lang->PROPERTY('consumables.product.search');
		unset ($this->_payload->$unset);
		$unset = $this->_lang->PROPERTY('consumables.product.vendor_select');
		unset ($this->_payload->$unset);

		// detect approval
		$approval = false;
		if ($orderauth = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.order_authorization'))){
			$result = SQLQUERY::EXECUTE($this->_pdo, 'user_get_orderauth', [
				'values' => [
					':orderauth' => $orderauth
				]
			]);
			$result = $result ? $result[0] : null;
			if ($result){
				$approval = $result['name'] . $this->_lang->GET('order.orderauth_verified');
			}
		}
		elseif ($orderauth = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('user.token'))){
			$result = SQLQUERY::EXECUTE($this->_pdo, 'application_login', [
				'values' => [
					':token' => $orderauth
				]
			]);
			$result = $result ? $result[0] : null;
			if ($result && $result['orderauth']){
				$approval = $result['name'] . $this->_lang->GET('order.token_verified');
			}
		}
		unset ($this->_payload->{$this->_lang->PROPERTY('user.order_authorization')});
		unset ($this->_payload->{$this->_lang->PROPERTY('user.token')});

		if (isset($_FILES[$this->_lang->PROPERTY('order.add_approval_signature')]) && $_FILES[$this->_lang->PROPERTY('order.add_approval_signature')]['tmp_name']){
			$signature = gettype($_FILES[$this->_lang->PROPERTY('order.add_approval_signature')]['tmp_name'])=='array' ? $_FILES[$this->_lang->PROPERTY('order.add_approval_signature')]['tmp_name'][0] : $_FILES[$this->_lang->PROPERTY('order.add_approval_signature')]['tmp_name'];
			$approval = 'data:image/png;base64,' . base64_encode(UTILITY::alterImage($signature, CONFIG['limits']['order_approvalsignature_image'], UTILITY_IMAGE_RESOURCE, 'png'));
		}

		// initiate data
		$order_data = ['items' => []];
		
		// handle attachments
		$attachments = [];
		if (isset($_FILES[$this->_lang->PROPERTY('order.attach_photo')]) && $_FILES[$this->_lang->PROPERTY('order.attach_photo')]['tmp_name'][0]){
			$attachments = array_merge($attachments, UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('order.attach_photo')], UTILITY::directory('order_attachments'), [$this->_currentdate->format('YmdHis')]));
			foreach($attachments as $key => $value){
				if ($value)	$attachments[$key] = substr($value, str_starts_with($value, '..') ? 1: 0);
				else unset($attachments[$key]);
			}
		}
		if (isset($_FILES[$this->_lang->PROPERTY('order.attach_file')]) && $_FILES[$this->_lang->PROPERTY('order.attach_file')]['tmp_name'][0]){
			$attachments = array_merge($attachments, UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('order.attach_file')], UTILITY::directory('order_attachments'), [$this->_currentdate->format('YmdHis')]));
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

		// handling the users language should not be an issue as they are passing the data in this moment directly from the latest order form request. not entirely sure about that at two in the morning
		// convert organizations unit from value to key according to language file
		$this->_payload->{$this->_lang->PROPERTY('order.organizational_unit')} = array_search(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('order.organizational_unit')), $this->_lang->_USER['units']);

		// translate payload-names to languagefile keys
		$language = [];
		foreach(array_keys($this->_lang->_USER['order']) as $key){
			$language[$key] = $this->_lang->PROPERTY('order.' . $key);
		}
		// set data itemwise
		foreach ($this->_payload as $key => $value){
			$key = array_search($key, $language);
			if (is_array($value)){
				foreach($value as $index => $subvalue){
					if (boolval($subvalue) && $subvalue !== 'undefined') {
						if (!isset($order_data['items'][intval($index)])) $order_data['items'][] = [];
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
}
?>
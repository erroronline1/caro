<?php 
/**
 * [CARO - Cloud Assisted Records and Operations](https://github.com/erroronline1/caro)  
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.  
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.  
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.  
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

namespace CARO\API;

// place and process orders
class ORDER extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_subMethod = null;
	private $_subMethodState = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user']) || array_intersect(['patient'], $_SESSION['user']['permissions'])) $this->response([], 401);

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
		require_once('./_calendarutility.php');
		require_once('_shared.php');
		$orderstatistics = new ORDERSTATISTICS($this->_pdo);
		$notifications = new NOTIFICATION;
		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'PATCH':
				$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_ids', [
					'replacements' => [
						':ids' => implode(',', array_map(fn($id) => intval($id), explode('_', $this->_requestedID)))
					]
				]);
				if (!$orders) $this->response(['response' => [ 'id' => $this->_requestedID, 'msg' => $this->_lang->GET('order.not_found'), 'type' => 'error']]);

				foreach($orders as $order) {
					if (!(PERMISSION::permissionFor('orderprocessing') || array_intersect(explode(',', $order['organizational_unit']), $_SESSION['user']['units']))) $this->response([], 401);

					// set order process states
					if (in_array($this->_subMethod, ['ordered', 'partially_received', 'received', 'partially_delivered', 'delivered', 'archived'])){
						switch ($this->_subMethod){
							case 'ordered':
								if ($order['ordertype'] === 'cancellation'){
									// ordered aka processed canellation orders are deleted immediately 
									$orderstatistics->update($this->_requestedID); // write to statistics about a cancelled order
									if ($this->delete_approved_order($order)) {
										$response = [
										'response' => [
											'id' => false,
											'msg' => $this->_lang->GET('order.deleted'),
											'type' => 'deleted'
										],
										'data' => ['order_prepared' => $notifications->preparedorders(), 'order_unprocessed' => $notifications->order(), 'consumables_pendingincorporation' => $notifications->consumables()]];
									}
									else $response = [
										'response' => [
											'id' => $this->_requestedID,
											'msg' => $this->_lang->GET('order.delete_failed'),
											'type' => 'error'
										]];
									continue 2;
								}
								elseif ($order['ordertype'] === 'return') {
									// ordered aka processed return orders are received immediately
									SQLQUERY::EXECUTE($this->_pdo, 'order_put_approved_order_state', [
										'values' => [
											':date' => $this->_subMethodState === 'true' ? $this->_date['servertime']->format('Y-m-d H:i:s') : null
										],
										'replacements' => [
											':id' => $this->_requestedID,
											':field' => 'received'
										]
									]);
								}
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
								break;
						}
						// generic state update
						SQLQUERY::EXECUTE($this->_pdo, 'order_put_approved_order_state', [
							'values' => [
								':date' => $this->_subMethodState === 'true' ? $this->_date['servertime']->format('Y-m-d H:i:s') : null
							],
							'replacements' => [
								':id' => implode(',', array_map(fn($id) => intval($id), explode('_', $this->_requestedID))),
								':field' => $this->_subMethod // verified safe by being in above array condition
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
							'administrative_mark' => null,
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
						$prepared['additional_info'] .= ($prepared['additional_info'] ? "\n": '') . $this->_lang->GET('order.approved_on', [], true) . ': ' . $order['approved'] . ' ';
						$prepared['additional_info'] .= UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message.message')) ? : '';
						// clear unused keys
						foreach ($prepared as $key => $value) {
							if (!$value) unset($prepared[$key]);
						}
						switch ($this->_subMethod){
							case 'disapproved':
								// add to prepared orders
								SQLQUERY::EXECUTE($this->_pdo, 'order_post_prepared_order', [
									'values' => [
										':id' => null,
										':order_data' => UTILITY::json_encode($prepared)
									]
								]);

								// delete approved order
								SQLQUERY::EXECUTE($this->_pdo, 'order_delete_approved_order', [
									'values' => [
										':id' => intval($this->_requestedID)
									]
								]);
								
								// inform user on disapproval
								$messagepayload = [];
								foreach (['quantity' => 'quantity_label',
									'unit' => 'unit_label',
									'number' => 'ordernumber_label',
									'name' => 'productname_label',
									'vendor' => 'vendor_label',
									'aut_idem' => 'aut_idem',
									'commission' => 'commission'] as $key => $value){
									$messagepayload[':' . $key] = isset($decoded_order_data[$value]) ? str_replace("\n", '\\\\n', $decoded_order_data[$value]) : '';
								}
								$messagepayload[':info'] = isset($decoded_order_data['additional_info']) ? $decoded_order_data['additional_info'] : '';
								$message = str_replace('\n', ', ', $this->_lang->GET('order.alert_disapprove_order', [
									':order' => $this->_lang->GET('order.message', $messagepayload, true),
									':unit' => $this->_lang->GET('units.' . $prepared['organizational_unit'], [], true),
									':user' => '<a href="javascript:void(0);" onclick="_client.message.newMessage(\'' . $this->_lang->GET('message.message.reply', [':user' => $_SESSION['user']['name']]). '\', \'' . $_SESSION['user']['name'] . '\', \'' . str_replace("\n", ', ', $this->_lang->GET('order.message', $messagepayload, true) . ',' . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message.message'))) . '\')">' . $_SESSION['user']['name'] . '</a>'
									], true)) . "\n \n" . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message.message'));
								// userlist to decode orderer
								$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
								if ($userid = array_search($prepared['orderer'], array_column($users, 'id')))
									$this->alertUserGroup(['user' => [$users[$userid]['name']]], $message);

								// schedule review of disapproved order for unit (in case of vacation, sick leave etc.)
								$calendar->post([
									':id' => null,
									':type' => 'tasks',
									':span_start' => $this->_date['servertime']->format('Y-m-d H:i:s'),
									':span_end' => $this->_date['servertime']->format('Y-m-d H:i:s'),
									':author_id' => $_SESSION['user']['id'],
									':affected_user_id' => null,
									':organizational_unit' => $prepared['organizational_unit'],
									':subject' => preg_replace("/\n/", ' ', strip_tags($message)),
									':misc' => null,
									':closed' => null,
									':alert' => null,
									':autodelete' => 1
								]);
								break;
							case 'addinformation':
								// append information to order
								if (isset($decoded_order_data['additional_info'])){
									$decoded_order_data['additional_info'] .= "\n" . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('order.additional_info'));
								}
								else $decoded_order_data['additional_info'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('order.additional_info'));

								SQLQUERY::EXECUTE($this->_pdo, 'order_put_approved_order_addinformation', [
									'values' => [
										':order_data' => UTILITY::json_encode($decoded_order_data),
										':id' => intval($this->_requestedID)
									]
								]);

								// alert userGroup on vendor sided order-state change 
								if (str_starts_with(UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('order.additional_info')), $this->_lang->GET('order.orderstate_description'))){
									// inform user group
									$messagepayload = [];
									foreach (['quantity' => 'quantity_label',
										'unit' => 'unit_label',
										'number' => 'ordernumber_label',
										'name' => 'productname_label',
										'vendor' => 'vendor_label',
										'aut_idem' => 'aut_idem',
										'commission' => 'commission'] as $key => $value){
										$messagepayload[':' . $key] = isset($decoded_order_data[$value]) ? str_replace("\n", '\\\\n', $decoded_order_data[$value]) : '';
									}
									$messagepayload[':info'] = isset($decoded_order_data['additional_info']) ? $decoded_order_data['additional_info'] : '';
									$this->alertUserGroup(['unit' => [$prepared['organizational_unit']]], str_replace('\n', ', ', $this->_lang->GET('order.alert_orderstate_change', [
										':order' => $this->_lang->GET('order.message', $messagepayload, true),
										':unit' => $this->_lang->GET('units.' . $prepared['organizational_unit'], [], true),
										':user' => '<a href="javascript:void(0);" onclick="_client.message.newMessage(\'' . $this->_lang->GET('message.message.reply', [':user' => $_SESSION['user']['name']]). '\', \'' . $_SESSION['user']['name'] . '\', \'' . str_replace("\n", ', ', $this->_lang->GET('order.message', $messagepayload, true)) . '\')">' . $_SESSION['user']['name'] . '</a>',
									])));
								}
								break;
							case 'cancellation':
								// append to information
								if (isset($decoded_order_data['additional_info'])){
									$decoded_order_data['additional_info'] .= "\n" . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message.message'));
								}
								else $decoded_order_data['additional_info'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message.message'));
								$decoded_order_data['additional_info'] .= "\n" . $this->_lang->GET('order.approved_on', [], true) . ': ' . $order['approved'];
								$decoded_order_data['orderer'] = $_SESSION['user']['id'];
								
								// rewrite order as cancelled type
								SQLQUERY::EXECUTE($this->_pdo, 'order_put_approved_order_cancellation', [
									'values' => [
										':order_data' => UTILITY::json_encode($decoded_order_data),
										':id' => intval($this->_requestedID)
									]
								]);
								break;
							case 'return':
								// append to order info 
								if (isset($decoded_order_data['additional_info'])){
									$decoded_order_data['additional_info'] .= "\n" . UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message.message'));
								}
								else $decoded_order_data['additional_info'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('message.message.message'));
								$decoded_order_data['additional_info'] .= "\n" . $this->_lang->GET('order.approved_on', [], true) . ': ' . $order['approved'];
								$decoded_order_data['additional_info'] .= "\n" . $this->_lang->GET('order.order.received', [], true) . ': ' . $order['received'];
								$decoded_order_data['additional_info'] .= "\n" . $this->_lang->GET('order.order.delivered', [], true) . ': ' . $order['delivered'];
								$decoded_order_data['orderer'] = $_SESSION['user']['id'];

								// determine criticality of return reason
								$return_reason = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('order.return_reason'));
								$criticality = array_search($return_reason, $this->_lang->_USER['orderreturns']['critical']);
								if ($criticality !== false){
									$decoded_order_data['additional_info'] = $this->_lang->GET('orderreturns.critical.' . $criticality, [], true) . "\n" .$decoded_order_data['additional_info'];

									// append incorporation review if applicable and alert eligible users
									if (isset($decoded_order_data['productid'])){
										$product = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product', [
											'values' => [
												':ids' => intval($decoded_order_data['productid'])
											]
										]);
										$product = $product ? $product[0] : null;
										if ($product){
											$product['incorporated'] = json_decode($product['incorporated'] ? : '', true);
											$product['incorporated'][] = [
												'_check' => $this->_lang->GET('consumables.product.incorporation_review', [':orderdata' => $decoded_order_data['additional_info']], true),
												'user' => [
													'name' => CONFIG['system']['caroapp'],
													'date' => $this->_date['servertime']->format('Y-m-d H:i')
												]
											];
											SQLQUERY::EXECUTE($this->_pdo, 'consumables_put_incorporation', [
												'replacements' => [
													':id' => $product['id'],
													':incorporated' => UTILITY::json_encode($product['incorporated'])
												]
											]);
											$this->alertUserGroup(['permission' => PERMISSION::permissionFor('incorporation', true)], 
											'<a href="javascript:void(0);" onclick="api.purchase(\'get\', \'product\', ' . $product['id'] . ')">' . implode(' ', [$decoded_order_data['vendor_label'], $decoded_order_data['ordernumber_label'], $decoded_order_data['productname_label']]) . '</a>'
											. "\n". $this->_lang->GET('consumables.product.incorporation_review', [':orderdata' => $decoded_order_data['additional_info']], true));
										}
									}
								}
								else {
									$criticality = array_search($return_reason, $this->_lang->_USER['orderreturns']['easy']);
									$decoded_order_data['additional_info'] = $this->_lang->GET('orderreturns.easy.' . $criticality, [], true) . "\n" .$decoded_order_data['additional_info'];
								}

								// create a new order as return
								if (SQLQUERY::EXECUTE($this->_pdo, 'order_post_approved_order', [
									'values' => [
									':order_data' => UTILITY::json_encode($decoded_order_data),
									':organizational_unit' => $order['organizational_unit'],
									':approval' => $order['approval'],
									':ordertype' => 'return'
									]
								])) {
									$this->_requestedID = $this->_pdo->lastInsertId();
									$response = [
									'response' => [
										'id' => $this->_requestedID,
										'msg' => $this->_lang->GET('order.saved'),
										'type' => 'success'
									]];
								}
								else $response = [
									'response' => [
										'id' => false,
										'msg' => $this->_lang->GET('order.save_failed'),
										'type' => 'error'
									]];
								break;
						}
					}
					// construct result toast
					$response = isset($response) ? $response : [
						'response' => [
							'msg' => in_array($this->_subMethod, ['addinformation', 'disapproved', 'cancellation']) ? $this->_lang->GET('order.order.' . $this->_subMethod) : $this->_lang->GET('order.order_type_' . ($this->_subMethodState === 'true' ? 'set' : 'revoked'), [':type' => $this->_lang->GET('order.order.' . $this->_subMethod)]),
							'type' => 'info'
						],
						'data' => ['order_prepared' => $notifications->preparedorders(), 'order_unprocessed' => $notifications->order(), 'consumables_pendingincorporation' => $notifications->consumables()]
					];

					// update order statistics
					if (($this->_subMethod === 'ordered' && $this->_subMethodState === 'false') || $this->_subMethod === 'disapproved') $orderstatistics->delete($this->_requestedID);
					else $orderstatistics->update($this->_requestedID);
				}
				break;
			case 'GET':
				// delete old received unarchived orders
				$old = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_delivered', [
					'values' => [
						':date_time' => date('Y-m-d h:i:s', time() - (CONFIG['lifespan']['order']['autodelete'] * 24 * 3600)),
					]
				]);
				foreach ($old as $row){
					$this->delete_approved_order($row);
				}

				// sanitize search
				$this->_requestedID = in_array($this->_requestedID, ['null']) ? '' : trim($this->_requestedID ? : '');

				$response = ['data' => [
					'filter' => $this->_requestedID ? : '', // preset search term
					'state' => $this->_subMethodState ? : 'unprocessed', // preset the appropriate language key
					'order' => [], 'approval' => [],
					'allowedstateupdates'=> [],
					'export' => false,
					'stockfilter' => false]];
				// set available units
				if (PERMISSION::permissionFor('orderdisplayall')) $units = array_keys($this->_lang->_USER['units']); // see all orders
				else $units = $_SESSION['user']['units']; // display only orders for own units
								
				// get unchecked articles for MDR §14 sample check
				// this is actually faster than a nested sql query
				$vendors = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
				foreach ($vendors as &$vendor){
					$vendor['products'] = json_decode($vendor['products'] ? : '', true); 
				}
				$preProducts = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products');
				$products = [];
				// get all checkable products
				$sampleCheck = [];
				foreach ($preProducts as $product){
					// assign id as key, unique anyway, fasten things up later
					// reduce properties to necessities regarding memory load
					$products[$product['id']]= [
						'id' => $product['id'],
						'stock_item' =>$product['stock_item'],
						'erp_id' =>$product['erp_id'],
						'last_order' =>$product['last_order'],
						'special_attention' =>$product['special_attention'],
						'thirdparty_order' =>$product['thirdparty_order'],
						'incorporated' => json_decode($product['incorporated'] ? : '', true)
					];

					if (!$product['trading_good']) continue;
					if (!$product['checked']){
						$sampleCheck[$product['id']] = true;
						continue;
					}
					$vendor = $vendors[array_search($product['vendor_id'], array_column($vendors, 'id'))];
					$check = new \DateTime($product['checked']);
					if (
						(
							// check longer ago than reusable interval
							!isset($vendor['products']['samplecheck_reusable'])
							|| (
								isset($vendor['products']['samplecheck_reusable'])
								&& intval($check->diff($this->_date['servertime'])->format('%a')) > $vendor['products']['samplecheck_reusable']
							)
						) && (
							// check longer ago than vendor interval
							isset($vendor['products']['samplecheck_interval'])
							&& intval($check->diff($this->_date['servertime'])->format('%a')) > $vendor['products']['samplecheck_interval']
						)
					){
						$sampleCheck[$product['id']] = true;
					}
				}
				// gather applicable order states
				// update on delayed shipment, availability, etc.
				$statechange = ['...' => ['value' => '']];
				foreach ($this->_lang->_USER['order']['orderstate'] as $value){
					$statechange[$value] = [];
				}
				ksort($statechange);

				// get all approved orders filtered by
				// applicable units, state and search
				$order = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_search', [
					'values' => [
						':SEARCH' => $this->_requestedID ? : '%'
					],
					'wildcards' => true,
					'replacements' => [
						':organizational_unit' => implode(",", $units),
						':user' => $_SESSION['user']['id']
					]
				]);
				// allow for column condition albeit being unlikely usable and orderdata is not yet decoded at this point
				// weighting is reasonable though
				$order = SEARCH::refine($this->_requestedID, $order, ['order_data']);

				// request permissions once, avoiding repetitive comparisons within loop
				$permission = [
					'orderaddinfo' => PERMISSION::permissionFor('orderaddinfo'),
					'ordercancel' => PERMISSION::permissionFor('ordercancel') && !in_array('group', $_SESSION['user']['permissions']),
					'orderprocessing' => PERMISSION::permissionFor('orderprocessing'),
					'purchasemembers' => [],
					'regularuser' => !in_array('group', $_SESSION['user']['permissions']),
					'products' => PERMISSION::permissionFor('products'),
					'admin' =>  array_intersect(['admin'], $_SESSION['user']['permissions'])
				];
				$response['data']['export'] = $permission['orderprocessing'] && PERMISSION::permissionFor('orderexportstockitems');
				$response['data']['stockfilter'] = $permission['orderprocessing'];

				// userlist to decode orderer
				$preUsers = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
				$users = [];

				// get purchase member names passed to data['editproductrequest'] as array type for message initialization
				// while possible to intersect with products-permission, ceo, prrc and qmo by default may not have time to handle this
				foreach ($preUsers as $user){
					$user['permissions'] = explode(',', $user['permissions'] ? : '');
					if (array_intersect(['purchase', 'admin'], $user['permissions'])) $permission['purchasemembers'][] = $user['name'];
					$users[$user['id']] = ['name' => $user['name'], 'image' => './api/api.php/file/stream/' . $user['image']];
				}

				$erp_interface_available = (ERPINTERFACE && ERPINTERFACE->_instatiated && method_exists(ERPINTERFACE, 'orderdata') && ERPINTERFACE->orderdata());

				foreach ($order as $row) {
					// filter selected state or default to unprocessed
					if ($this->_subMethodState === 'unprocessed') $this->_subMethodState = null;
					if (!$this->_subMethodState && $row['ordered']) continue;
					if ($this->_subMethodState){
						if (!$row[$this->_subMethodState]) continue;
						// skip whatever has the next logical steps already set
						foreach (array_reverse(['ordered', 'partially_received', 'received', 'partially_delivered', 'delivered', 'archived']) as $s){
							if ($this->_subMethodState !== $s && $row[$s]) continue 2;
							if ($this->_subMethodState === $s) break;
						}
					}

					$decoded_order_data = json_decode($row['order_data'], true);

					$product = null;
					if (isset($decoded_order_data['productid']) && isset($products[$decoded_order_data['productid']])){
						$product = $products[$decoded_order_data['productid']];
					}

					if ($this->_subMethod === 'stock' &&
						!isset($product['stock_item'])
					) continue;

					// append to array with reusable images to reduce payload (approval signatures if allowed per CONFIG)
					if (str_contains($row['approval'], 'data:image/png') && !in_array($row['approval'], $response['data']['approval'])) $response['data']['approval'][] = $row['approval'];

					// data chunks to be assembled by js _client.order.approved()
					$orderer = UTILITY::propertySet($decoded_order_data, 'orderer') ? : null;
					if (isset($users[$orderer])) $orderer = $users[$orderer];
					else $orderer = ['name' => $this->_lang->GET('general.deleted_user'), 'image' => null];
					$unit_intersection = boolval(array_intersect([$row['organizational_unit']], $units)) || $orderer['name'] = $_SESSION['user']['name'];
					$data = [
						'id' => $row['id'],
						'ordertype' => $row['ordertype'],
						'ordertext' => ($product && $product['stock_item'] ? $this->_lang->GET('consumables.product.stock_item') . "\n" : '')
							. ($product && $product['erp_id'] ? $this->_lang->GET('consumables.product.erp_id') . ": " . $product['erp_id'] . "\n" : '')
							. " \n" . $this->_lang->GET('order.organizational_unit') . ': ' . $this->_lang->GET('units.' . $row['organizational_unit'])
							. (UTILITY::propertySet($decoded_order_data, 'delivery_date') ? "\n" . $this->_lang->GET('order.delivery_date') . ': ' . $this->convertFromServerTime(UTILITY::propertySet($decoded_order_data, 'delivery_date')) : ''),
						'quantity' => UTILITY::propertySet($decoded_order_data, 'quantity_label') ? : null,
						'unit' => UTILITY::propertySet($decoded_order_data, 'unit_label') ? : null,
						'barcode' => UTILITY::propertySet($decoded_order_data, 'barcode_label') ? : null,
						'name' => UTILITY::propertySet($decoded_order_data, 'productname_label') ? : null,
						'vendor' => UTILITY::propertySet($decoded_order_data, 'vendor_label') ? : null,
						'aut_idem' => UTILITY::propertySet($decoded_order_data, 'aut_idem') ? $this->_lang->GET('order.aut_idem') : null,
						'ordernumber' => UTILITY::propertySet($decoded_order_data, 'ordernumber_label') ? : null,
						'commission' => UTILITY::propertySet($decoded_order_data, 'commission') ? : null,
						'administrative_mark' => UTILITY::propertySet($decoded_order_data, 'administrative_mark') ? : null,
						'approval' => null,
						'information' => null,
						'addinformation' => $permission['orderaddinfo'] || $unit_intersection,
						'lastorder' => $product && $product['last_order'] ? $this->_lang->GET('order.order_last_ordered', [':date' => $this->convertFromServerTime(substr($product['last_order'], 0, -9))]) : null,
						'orderer' => $orderer,
						'organizationalunit' => $row['organizational_unit'],
						'orderstatechange' => ($row['ordered'] && !$row['received'] && !$row['delivered'] && ($permission['orderaddinfo'] || $unit_intersection)) ? $statechange : [],
						'state' => [],
						'disapprove' => (!($row['ordered'] || $row['received'] || $row['delivered']) && in_array($row['ordertype'], ['order', 'service'])),
						'cancel' => $permission['regularuser'] && ($row['ordered'] && !($row['received'] || $row['delivered']) && ($permission['ordercancel'] || $unit_intersection)),
						'return' => (($row['received'] || $row['delivered']) && $row['ordertype'] === 'order' && ($permission['ordercancel'] || $unit_intersection)),
						'attachments' => [],
						'delete' => $permission['regularuser'] && ($permission['ordercancel'] || $unit_intersection),
						'autodelete' => null,
						'incorporation' => [],
						'samplecheck' => [],
						'specialattention' => $product ? $product['special_attention'] : null,
						'collapsed' => !$permission['orderprocessing'],
						'addproduct' => null,
						'editproductrequest' => null,
						'productid' => $product ? $product['id'] : null,
						'identifier' => $erp_interface_available && $permission['orderprocessing'] ? UTILITY::identifier(' ', $row['approved']) : null,
						'calendar' => $row['received'] ? $calendar->dialog([
							':type' => 'tasks',
							':subject' => (UTILITY::propertySet($decoded_order_data, 'ordernumber_label') ? : '') . ' ' .
										(UTILITY::propertySet($decoded_order_data, 'productname_label') ? : '') . ' ' .
										(UTILITY::propertySet($decoded_order_data, 'vendor_label') ? : '') . ' ' .
										(UTILITY::propertySet($decoded_order_data, 'commission') ? : ''),
							':alert' => 1
						]) : null,
						'thirdparty_order' => $product ? $product['thirdparty_order'] : null,
					];

					// add identified group user
					if ($orderer_group_identify = UTILITY::propertySet($decoded_order_data, 'orderer_group_identify')){
						$data['ordertext'] .= "\n" .$this->_lang->GET('order.orderer_group_identify') . ': ' . $orderer_group_identify;
					}

					// add approval
					$data['ordertext'] .= "\n" . $this->_lang->GET('order.order.approved') . ': ' . $this->convertFromServerTime($row['approved']) . ' ';
					if (!str_contains($row['approval'], 'data:image/png')) {
						$data['ordertext'] .= "\n". $row['approval'];
					} else {
						$data['approval'] = array_search($row['approval'], $response['data']['approval']);
					}

					// add additional info
					if ($additional_information = UTILITY::propertySet($decoded_order_data, 'additional_info')){
						$data['information'] = preg_replace(['/\r/', '/\\\n/'], ['', "\n"], $additional_information);
					}

					// add order reference if provided by erp interface
					if ($order_reference = UTILITY::propertySet($decoded_order_data, 'order_reference')){
						$data['ordertext'] .= "\n" . $this->_lang->GET('order.order_reference') . ': ' . $order_reference;
					}

					// append order processing states
					foreach (['ordered', 'partially_received', 'received', 'partially_delivered', 'delivered', 'archived'] as $s){
						if (!isset($data['state'][$s])) $data['state'][$s] = [];
						$data['state'][$s]['data-'.$s] = boolval($row[$s]) ? 'true' : 'false';
						if (boolval($row[$s])) {
							$data['ordertext'] .= "\n" . $this->_lang->GET('order.order.' . $s) . ': ' . $this->convertFromServerTime($row[$s]);
						}
						switch ($s){
							case 'ordered':
								if (!$row['received'] && $data['aut_idem']){
									$data['state'][$s]['onchange'] =
										"new _client.Dialog({type:'confirm', header:'" . 
										$this->_lang->GET('order.aut_idem_order_confirmation_header', [':user' => $data['orderer']['name'], ':product' => $data['name']]) .
										"', render:'" . $this->_lang->GET('order.aut_idem_order_confirmation_render', [':user' => $data['orderer']['name']]) .
										"', options:{'" . $this->_lang->GET('general.prevent_dataloss_cancel') . "': false, '" . $this->_lang->GET('general.prevent_dataloss_ok') . "': {'value': true, class: 'reducedCTA'}}}).then(confirmation => {" .
	 									"if (confirmation) {api.purchase('put', 'approved', '" . $data['id'] . "', '" . $s . "', this.checked); this.setAttribute('data-" . $s . "', this.checked.toString());}" .
										"else {this.checked = false; return}" .
										"});";
								}
							case 'received':
								if (!$permission['orderprocessing']){
									$data['state'][$s]['disabled'] = true;
								}
								else $response['data']['allowedstateupdates'][] = $s;
								break;
							case 'partially_received':
								if ($row['received'] || !$permission['orderprocessing']){
									$data['state'][$s]['disabled'] = true;
								}
								else $response['data']['allowedstateupdates'][] = $s;
								break;
							case 'partially_delivered':
								if ($row['delivered'] || !($permission['admin'] || $unit_intersection || $orderer['name'] === $_SESSION['user']['name'])){
									$data['state'][$s]['disabled'] = true;
								}
								else $response['data']['allowedstateupdates'][] = $s;
								break;
							case 'delivered':
								if ($row['received']){
									$delete = new \DateTime($row['received']);
									$delete->modify('+ ' . CONFIG['lifespan']['order']['autodelete'] . 'days');
									$data['autodelete'] = $this->_lang->GET('order.autodelete', [':date' => $this->convertFromServerTime($delete->format('Y-m-d')), ':unit' => $this->_lang->_USER['units'][$data['organizationalunit']]]);
								}
								// no break
							case 'archived':
								if (!($permission['admin'] || $unit_intersection || $orderer['name'] === $_SESSION['user']['name'])){
									$data['state'][$s]['disabled'] = true;
								}
								else $response['data']['allowedstateupdates'][] = $s;
								break;
						}
					}

					// order attachments
					if (isset($decoded_order_data['attachments'])){
						foreach (explode(',', $decoded_order_data['attachments']) as $file){
							$data['attachments'][pathinfo($file)['basename']] = UTILITY::link(['href' => './api/api.php/file/stream/' . $file]);
						}
					}

					// incorporation state
					if ($product){
						if (!$product['incorporated']){
							if ($permission['regularuser']){
								$data['incorporation']['item'] = $product['id'];
							} else {
								// simple groups are not allowed to make records
								$data['incorporation']['state'] = $this->_lang->GET('order.incorporation.neccessary_by_user');
							}
						}
						elseif (isset($product['incorporated']['_denied'])) {
							$data['incorporation']['state'] = $this->_lang->GET('order.incorporation.denied');
						}
						elseif (!PERMISSION::fullyapproved('incorporation', $product['incorporated'])) {
							$data['incorporation']['state'] = $this->_lang->GET('order.incorporation.pending');
						}						
					}
					
					// request MDR §14 sample check
					if ($product && isset($sampleCheck[$product['id']])){
						if ($permission['regularuser']){
							$data['samplecheck']['item'] = $product['id'];
						} else {
							// simple groups are not allowed to make records
							$data['samplecheck']['state'] = $this->_lang->GET('order.sample_check.by_user');
						}
					}

					// request adding unknown product or editing of product
					if ($permission['products']){
						if (!$product) $data['addproduct'] = true;
					}
					else {
						$data['editproductrequest'] = $product ? $permission['purchasemembers'] : null;
					}

					array_push($response['data']['order'], array_filter($data, fn($property) => $property || $property === 0));
				}
				$response['data']['allowedstateupdates'] = array_unique($response['data']['allowedstateupdates']);
				break;
			case 'DELETE':
				$order = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_order_by_ids', [
					'replacements' => [
						':ids' => implode(',', array_map(fn($id) => intval($id), explode('_', $this->_requestedID)))
					]
				]);
				$order = $order ? $order[0] : null;
				
				if ($order && !in_array('group', $_SESSION['user']['permissions']) && $this->delete_approved_order($order)) {
					$response = [
					'response' => [
						'id' => false,
						'msg' => $this->_lang->GET('order.deleted'),
						'type' => 'deleted'
					],
					'data' => ['order_prepared' => $notifications->preparedorders(), 'order_unprocessed' => $notifications->order(), 'consumables_pendingincorporation' => $notifications->consumables()]];
				}
				else $response = [
					'response' => [
						'id' => $this->_requestedID,
						'msg' => $this->_lang->GET('order.delete_failed'),
						'type' => 'error'
					]];
				break;
			}
		$this->response($response);
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
	 * 
	 * also see application.php->cron() clone for record deletion
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
	 *                       _
	 *   ___ _ _ ___ ___ ___| |_
	 *  | -_|_'_| . | . |  _|  _|
	 *  |___|_,_|  _|___|_| |_|
	 *          |_|
	 * export a printable list from approved orders
	 */
	public function export(){
		require_once('./_pdf.php');
		// set available units
		if (PERMISSION::permissionFor('orderdisplayall')) $units = array_keys($this->_lang->_USER['units']); // see all orders
		else $units = $_SESSION['user']['units']; // display only orders for own units
		// sanitize search
		$this->_requestedID = in_array($this->_requestedID, ['null']) ? '' : trim($this->_requestedID ? : '');
		$this->_subMethodState = $this->_subMethodState === 'unprocessed' ? null : $this->_subMethodState;

		$order = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_search', [
			'values' => [
				':SEARCH' => $this->_requestedID ? : '%'
			],
			'wildcards' => true,
			'replacements' => [
				':organizational_unit' => implode(",", $units),
				':user' =>  $_SESSION['user']['id']
			]
		]);

		// allow for column condition albeit being unlikely usable and orderdata is not yet decoded at this point
		// weighting is reasonable though
		$order = SEARCH::refine($this->_requestedID, $order, ['order_data']);

		// userlist to decode orderer
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');

		// gather product information on stock item flag
		$stock_items = $erp_ids = [];
		foreach (SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products') as $product) {
			if ($product['stock_item']) $stock_items[trim($product['vendor_name'] . '_' . $product['article_no'] . '_' . $product['article_name'])] = true;
			if ($product['erp_id']) $erp_ids[$product['erp_id']] = trim($product['vendor_name'] . '_' . $product['article_no'] . '_' . $product['article_name']);
		}
		$data = [];
		$item = 1;
		foreach ($order as $row) {
			// filter selected state or default to unprocessed
			if ((!$this->_subMethodState && $row['ordered']) || ($this->_subMethodState && !$row[$this->_subMethodState])) continue;
			
			$decoded_order_data = json_decode($row['order_data'], true);

			if (isset($decoded_order_data['vendor_label']) && isset($decoded_order_data['ordernumber_label']) && isset($decoded_order_data['productname_label'])
				&& !isset($stock_items[trim($decoded_order_data['vendor_label'] . '_' . $decoded_order_data['ordernumber_label']. '_' . $decoded_order_data['productname_label'])])
			){
				continue;
			}

			$orderer = UTILITY::propertySet($decoded_order_data, 'orderer') ? : null;
			if ($orderer = array_search($orderer, array_column($users, 'id'))) $orderer = $users[$orderer]['name'];
			else $orderer = $this->_lang->GET('general.deleted_user');

			$erp_id = null;
			if (isset($decoded_order_data['vendor_label']) && isset($decoded_order_data['ordernumber_label']) && isset($decoded_order_data['productname_label'])){
				$erp_id = array_search($decoded_order_data['vendor_label'] . '_' . $decoded_order_data['ordernumber_label']. '_' . $decoded_order_data['productname_label'], $erp_ids);
			}
			$data[$item++] = $this->_lang->GET("order.prepared_order_item", [
				':quantity' => UTILITY::propertySet($decoded_order_data, 'quantity_label') ? : '',
				':unit' => UTILITY::propertySet($decoded_order_data, 'unit_label') ? : '',
				':number' => UTILITY::propertySet($decoded_order_data, 'ordernumber_label') ? : '',
				':name' => UTILITY::propertySet($decoded_order_data, 'productname_label') ? : '',
				':vendor' => UTILITY::propertySet($decoded_order_data, 'vendor_label') ? : '',
				':aut_idem' => UTILITY::propertySet($decoded_order_data, 'aut_idem') ? : '',
				]
			)
			. ($erp_id ? "\n" . $this->_lang->GET('consumables.product.erp_id') . ': ' . $erp_id: '')
			. ("\n" . $this->_lang->GET('order.organizational_unit') . ': ' . $this->_lang->GET('units.' . $row['organizational_unit'])
			. (UTILITY::propertySet($decoded_order_data, 'delivery_date') ? "\n" . $this->_lang->GET('order.delivery_date') . ': ' . $this->convertFromServerTime(UTILITY::propertySet($decoded_order_data, 'delivery_date')) : ''))
			. "\n" . ($this->_lang->GET('order.orderer') . ': ' . $orderer);
		}
		if (!$data) $this->response([], 404);

		//set up summary
		$title = $this->_lang->GET('order.navigation.order') . ' - ' . $this->_lang->GET('consumables.product.stock_item') . ' - ' . $this->_lang->GET('order.order.' . ($this->_subMethodState ? : 'unprocessed'));
		$summary = [
			'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $title . '_' . $this->_date['usertime']->format('Y-m-d H:i')),
			'identifier' => null,
			'content' => $data,
			'files' => [],
			'images' => [],
			'title' => $title,
			'date' => $this->convertFromServerTime($this->_date['usertime']->format('Y-m-d H:i'), true)
		];
		$downloadfiles = [];
		$PDF = new PDF(CONFIG['pdf']['record']);
		$file = $PDF->auditPDF($summary);
		$downloadfiles[$this->_lang->GET('order.export')] = [
			'href' => './api/api.php/file/stream/' . $file,
			'download' => pathinfo($file)['basename']
		];

		$body = [];
		array_push($body, 
			[[
				'type' => 'links',
				'description' =>  $this->_lang->GET('order.export_hint'),
				'content' => $downloadfiles
			]]
		);
		$this->response([
			'render' => $body,
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
			case 'PUT':
				$processedOrderData = $this->processOrderForm();

				// check whether an approval has been submitted
				if (!$processedOrderData['approval']){
					SQLQUERY::EXECUTE($this->_pdo, 'order_post_prepared_order', [
						'values' => [
							':id' => $this->_requestedID,
							':order_data' => UTILITY::json_encode($processedOrderData['order_data'])
						]
					]);
					$response = [
						'response' => [
							'id' => $this->_requestedID ? : $this->_pdo->lastInsertId(),
							'msg' => $this->_lang->GET('order.saved_to_prepared'),
							'type' => 'info'
						],
						'data' => ['order_prepared' => $notifications->preparedorders(), 'order_unprocessed' => $notifications->order(), 'consumables_pendingincorporation' => $notifications->consumables()]
					];
					break;
				}

				// else process approved order
				$response = $this->postApprovedOrder($processedOrderData);

				// delete prepared order if successfully approved
				if ($response['response']['msg'] === $this->_lang->GET('order.saved') && $this->_requestedID){
					SQLQUERY::EXECUTE($this->_pdo, 'order_delete_prepared_orders', [
						'replacements' => [
							':id' => intval($this->_requestedID)
						]
					]);
				$response['data'] = ['order_prepared' => $notifications->preparedorders(), 'order_unprocessed' => $notifications->order(), 'consumables_pendingincorporation' => $notifications->consumables()];
				}

				break;
			case 'GET':
				$datalist = [];
				$datalist_unit = [];
				$vendors = [];

				// prepare existing vendor lists
				$vendor = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
				$vendors[$this->_lang->GET('consumables.product.search_all_vendors')] = ['value' => implode('_', array_map(fn($r) => $r['id'], $vendor))];
				foreach ($vendor as $key => $row) {
					if ($row['hidden']) continue;
					$datalist[] = $row['name'];
					$vendors[$row['name']] = ['value' => $row['id']];
				}
				ksort($vendors);

				// prepare existing sales unit lists
				$vendor = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product_units');
				foreach ($vendor as $key => $row) {
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
						'administrative_mark' => '',
						'delivery_date' => '',
						'order_type' => 'order',
						'items' => false
					];
				} else {
					$order = json_decode($order['order_data'], true);
				}

				// prepare organizational unit selection
				$organizational_units = [];
				foreach ($this->_lang->_USER['units'] as $unit => $description){
					$organizational_units[$description] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'required' => true];
					if (isset($order['organizational_unit']) && in_array($unit, explode(',', $order['organizational_unit']))) $organizational_units[$description]['checked'] = true;
					elseif (isset($_SESSION['user']['app_settings']['primaryUnit']) && $_SESSION['user']['app_settings']['primaryUnit'] === $unit) $organizational_units[$this->_lang->GET('units.' . $_SESSION['user']['app_settings']['primaryUnit'])]['checked'] = true;
				}

				// prepare order type selection
				$order_type = [];
				foreach ($this->_lang->_USER['order']['ordertype'] as $key => $description){
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

				// render search and selection
				require_once('_shared.php');
				$search = new SEARCHHANDLER($this->_pdo, $this->_date);
				$response['render'] = ['form' => [
					'data-usecase' => 'purchase',
					'action' => $this->_requestedID ? "javascript:api.purchase('put', 'order', '" . $this->_requestedID . "')" : "javascript:api.purchase('post', 'order')"
				],
				'content' => [
					...$search->productsearch('order')
					,
					[
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
							'type' => 'scanner',
							'attributes' => [
								'name' => $this->_lang->GET('order.administrative_mark'),
								'value' => isset($order['administrative_mark']) ? $order['administrative_mark'] : '',
								'data-loss' => 'prevent',
							]
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
					array_splice($response['render']['content'][1], 1, 0, [[
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
					foreach (explode(',', $order['attachments']) as $file){
						$files[pathinfo($file)['basename']] = UTILITY::link(['href' => './api/api.php/file/stream/' . $file]);
					}
					array_splice($response['render']['content'], 3, 0, [
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
									'onclick' => 'this.parentNode.remove()'
								]
							]
						]);
					}
					array_splice($response['render']['content'], 1, 0, $items);
				}

				// append delete button
				if ($this->_requestedID) array_push($response['render']['content'], [
					['type' => 'deletebutton',
					'attributes' => [
						'value' => $this->_lang->GET('order.delete_prepared_order'),
						'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('order.delete_prepared_order_confirm_header') ."', options:{".
							"'".$this->_lang->GET('order.delete_prepared_order_confirm_cancel')."': false,".
							"'".$this->_lang->GET('order.delete_prepared_order_confirm_ok')."': {value: true, class: 'reducedCTA'},".
							"}}).then(confirmation => {if (confirmation) api.purchase('delete', 'order', " . $this->_requestedID . ")})"
					]]
				]);

				break;
			case 'DELETE':
				$order = SQLQUERY::EXECUTE($this->_pdo, 'order_get_prepared_order', [
					'values' => [
						':id' => intval($this->_requestedID)
					]
				]);
				$order = $order ? $order[0] : null;
				if (!$order) $this->response([], 404);
				$order = json_decode($order['order_data'], true);
				if (!(PERMISSION::permissionFor('orderprocessing') || array_intersect(explode(',', $order['organizational_unit']), $_SESSION['user']['units']))) $this->response([], 401);
				// delete attachments
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
					$response = [
					'response' => [
						'id' => false,
						'msg' => $this->_lang->GET('order.deleted'),
						'type' => 'deleted'
					]];
				}
				else $response = [
					'response' => [
						'id' => $this->_requestedID,
						'msg' => $this->_lang->GET('order.delete_failed'),
						'type' => 'error'
					]];
				break;
			}
		$this->response($response);
	}
	
	
	/**
	 *               _                                 _           _
	 *   ___ ___ ___| |_ ___ ___ ___ ___ ___ _ _ ___ _| |___ ___ _| |___ ___
	 *  | . | . |_ -|  _| .'| . | . |  _| . | | | -_| . | . |  _| . | -_|  _|
	 *  |  _|___|___|_| |__,|  _|  _|_| |___|\_/|___|___|___|_| |___|___|_|
	 *  |_|                 |_| |_|
	 * prepare orderdata for database
	 * @param array $processedOrderData
	 * 
	 * @return array render response
	 */
	private function postApprovedOrder($processedOrderData){
		$keys = array_keys($processedOrderData['order_data']);
		$order_data2 = [];
		$sqlchunks = [];

		// gather products to assign database id to order data if vendor and ordernumber match
		foreach (SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products') as $product) {
			$allproducts_key[$product['vendor_name'] . '_' . $product['article_no']] = $product;
		}

		// iterate over items and create one order per item
		for ($i = 0; $i < count($processedOrderData['order_data']['items']); $i++){
			$product =null;
			$order_data2 = $processedOrderData['order_data']['items'][$i];
			foreach ($keys as $key){
				if (!in_array($key, ['items', 'organizational_unit'])) $order_data2[$key] = $processedOrderData['order_data'][$key];
			}
			// try to match product id, assign if found
			// this is done here instead of checking on display of orders for performance reasons!
			// i am aware this may lead to buttons for adding a product after product list updates
			if (isset($order_data2['ordernumber_label']) && isset($order_data2['vendor_label'] )){
				if (isset($allproducts_key[$order_data2['vendor_label'] . '_' . $order_data2['ordernumber_label']])){
					$product = $allproducts_key[$order_data2['vendor_label'] . '_' . $order_data2['ordernumber_label']];
				}
				if ($product) $order_data2['productid'] = $product['id'];
			}

			$sqlchunks = SQLQUERY::CHUNKIFY($sqlchunks, strtr(SQLQUERY::PREPARE('order_post_approved_order'),
			[
				':order_data' => $this->_pdo->quote(UTILITY::json_encode($order_data2)),
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
			catch (\Exception $e) {
				echo $e, $chunk;
				die();
			}
		}
		if ($success) {
			$response = [
				'response' => [
					'id' => false,
					'msg' => $this->_lang->GET('order.saved'),
					'type' => 'success'
				]
			];
		}
		else $response = [
			'response' => [
				'id' => false,
				'msg' => $this->_lang->GET('order.save_failed'),
				'type' => 'error'
			]];
		return $response;
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


				// iterate over prepared orders
				$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_prepared_orders');
				$response = null;
				$success = [];
				foreach ($orders as $order){
					// check if contained in approved
					if (array_search($order['id'], $approvedIDs) === false) continue;

					// create itemized order data from prepared order
					$order_data = ['items' => []];
					$index = 0;
					foreach (json_decode($order['order_data'] ? : '', true) as $key => $items){ // data
						if (is_array($items)){ // actual items
							foreach ($items as $item){
								foreach ($item as $key => $subvalue){
									if (boolval($subvalue)) $order_data['items'][$index][$key] = trim(preg_replace(["/\\r/", "/\\n/"], ['', '\\n'], $subvalue));
								}
								$index++;
							}
						} else { // common order info
							if (boolval($items)) $order_data[$key] = trim(preg_replace(["/\\r/", "/\\n/"], ['', '\\n'], $items));
						}
					}
					if (!count($order_data['items'])) continue;
					$response = $this->postApprovedOrder(['approval' => $approval, 'order_data' => $order_data]);
					$success[] = $order['id'];
				}

				// if successfully posted as approved delete prepared order
				if (isset($response['response']['msg']) && $response['response']['msg'] === $this->_lang->GET('order.saved')){
					SQLQUERY::EXECUTE($this->_pdo, 'order_delete_prepared_orders', [
						'replacements' => [
							':id' => implode(",", $success)
						]
					]);
					require_once('notification.php');
					$notifications = new NOTIFICATION;
					$response['data'] = ['order_prepared' => $notifications->preparedorders(), 'order_unprocessed' => $notifications->order(), 'consumables_pendingincorporation' => $notifications->consumables()];
				}
				break;
			case 'GET':
				$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_prepared_orders');
				// display all orders assigned to organizational unit
				if ($this->_requestedID) $units = [$this->_requestedID]; // see orders from selected unit
				else $units = $_SESSION['user']['units']; // see only orders for own units

				// filter by organizational unit
				$organizational_orders = [];
				foreach ($orders as $key => $row) {
					$order_data = json_decode($row['order_data'], true);
					if (array_intersect([$order_data['organizational_unit']], $units)) {
						array_push($organizational_orders, $row);
					}
				}
				$response = ['render' => ['content' => []]];

				// users with order authorization can access all prepared orders by request
				if ($_SESSION['user']['orderauth']){
					$organizational_units = [];
					$organizational_units[$this->_lang->GET('assemble.render.mine')] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'onchange' => "api.purchase('get', 'prepared')"];
					foreach ($this->_lang->_USER['units'] as $unit => $description){
						$organizational_units[$description] = ['name' => $this->_lang->PROPERTY('order.organizational_unit'), 'onchange' => "api.purchase('get', 'prepared', '" . $unit . "')"];
					}
					if (!$this->_requestedID) $organizational_units[$this->_lang->GET('assemble.render.mine')]['checked'] = true;
					else $organizational_units[$this->_lang->GET('units.' . $this->_requestedID)]['checked'] = true;
					$response['render']['content'][] = [
						['type' => 'radio',
						'attributes' => [
							'name' => $this->_lang->GET('order.organizational_unit')
						],
						'content' => $organizational_units
						]
					];
				}

				// userlist to decode orderer
				$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
				$markdown = new MARKDOWN();
				// display selected prepared orders 
				if (count($organizational_orders)){
					foreach ($organizational_orders as $order){ // order
						$items = $info = '';
						$order_attributes = [];
						$processedOrderData = json_decode($order['order_data'], true);
						foreach ($processedOrderData as $key => $value){ // data
							if (is_array($value)){
								foreach ($value as $item){
									$items .= '* ' . $this->_lang->GET('order.prepared_order_item', [
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
								if ($key === 'orderer'){
									if ($orderer = array_search($value, array_column($users, 'id'))) $value = $users[$orderer]['name'];
									else $value = $this->_lang->GET('general.deleted_user');
								}
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
						array_push($response['render']['content'], [
							[
								'type' => 'textsection',
								'attributes' => $order_attributes,
								'htmlcontent' => $markdown->md2html($items),
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
								'attributes' => [
									'value' => $this->_lang->GET('order.edit_prepared_order'),
									'onclick' => "api.purchase('get', 'order', " . $order['id']. ")"
								]
							]
						]);

						// append attachments
						if (isset($processedOrderData['attachments'])){
							$files = [];
							foreach (explode(',', $processedOrderData['attachments']) as $file){
								$files[pathinfo($file)['basename']] = UTILITY::link(['href' => './api/api.php/file/stream/' . $file]);
							}
							array_splice($response['render']['content'][count($response['render']['content']) - 1], 2, 0, [
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
							array_push($response['render']['content'], $authorize);
							$response['render']['form'] = ['action' => "javascript:api.purchase('put', 'prepared')", 'data-usecase' => 'purchase'];	
						}
					}
				}
				else $response['render']['content'][] = $this->noContentAvailable($this->_lang->GET('order.no_orders'))[0];
				break;
		}
		$this->response($response);
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
			$attachments = array_merge($attachments, UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('order.attach_photo')], UTILITY::directory('order_attachments'), [$this->_date['servertime']->format('YmdHis')]));
			foreach ($attachments as $key => $value){
				if ($value)	$attachments[$key] = substr($value, str_starts_with($value, '..') ? 1: 0);
				else unset($attachments[$key]);
			}
		}
		if (isset($_FILES[$this->_lang->PROPERTY('order.attach_file')]) && $_FILES[$this->_lang->PROPERTY('order.attach_file')]['tmp_name'][0]){
			$attachments = array_merge($attachments, UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('order.attach_file')], UTILITY::directory('order_attachments'), [$this->_date['servertime']->format('YmdHis')]));
			foreach ($attachments as $key => $value){
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
		foreach (array_keys($this->_lang->_USER['order']) as $key){
			$language[$key] = $this->_lang->PROPERTY('order.' . $key);
		}
		// set data itemwise
		foreach ($this->_payload as $key => $value){
			$key = array_search($key, $language);
			if (is_array($value)){
				foreach ($value as $index => $subvalue){
					if (boolval($subvalue) && $subvalue !== 'undefined') {
						if (!isset($order_data['items'][intval($index)])) $order_data['items'][] = [];
						$order_data['items'][intval($index)][$key] = trim(preg_replace(["/\\r/", "/\\n/"], ['', '\\n'], $subvalue));
					}
				}
			} else {
				if (boolval($value) && $value !== 'undefined') $order_data[$key] = trim(preg_replace(["/\\r/", "/\\n/"], ['', '\\n'], $value));
			}
		}
		$order_data['orderer'] = $_SESSION['user']['id'];
		if (!count($order_data['items'])) $this->response([], 406);
		return ['approval' => $approval, 'order_data' => $order_data];
	}
}
?>
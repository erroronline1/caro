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

require_once('./_calendarutility.php');

// handle all notification within one call
class NOTIFICATION extends API {
	/**
	 * handle all notification within one call
	 */

	public $_requestedMethod = REQUEST[1];
	public $_cronOverride = REQUEST[2] ?? false;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user']) || array_intersect(['patient'], $_SESSION['user']['permissions'])) $this->response([], 401);
	}

	/**
	 *           _   _ ___
	 *   ___ ___| |_|_|  _|___
	 *  |   | . |  _| |  _|_ -|
	 *  |_|_|___|_| |_|_| |___|
	 *
	 */
	public function notifs(){
		$result = [
			// first call is cron, for tidying up and occasionally creating items
			'cron' => $this->cron(),

			'audit_closing' => $this->audits(),
			'consumables_pendingincorporation' => $this->consumables(),
			'csvfilter_approval' => $this->csvfilter(),
			'document_approval' => $this->documents(),
			'order_unprocessed' => $this->order(),
			'order_prepared' => $this->preparedorders(),
			'managementreview' => $this->managementreview(),
			'measure_unclosed' => $this->measures(),
			'responsibilities' => $this->responsibilities(),
			// make the following calls last no matter what to include all possible previous calendar entries and messages
			'calendar_uncompletedtasks' => $this->tasks(),
			'calendar_uncompletedworklists' => $this->worklists(),
			'message_unnotified' => $this->messageunnotified(),
			'message_unseen' => $this->messageunseen(),
		];
		$this->response($result);
	}

	/**
	 *             _ _ _       
	 *   ___ _ _ _| |_| |_ ___ 
	 *  | .'| | | . | |  _|_ -|
	 *  |__,|___|___|_|_| |___|
	 * 
	 * notify on unclosed audits
	 */
	public function audits(){
		if (!PERMISSION::permissionFor('audit')) return 0;
		$data = SQLQUERY::EXECUTE($this->_pdo, 'audit_get');
		$number = 0;
		foreach ($data as $row){
			if (!$row['closed']) $number++;
		} 
		return $number;
	}

	/**
	 *                     _     _     _       
	 *   ___ ___ _____ ___| |___|_|___| |_ ___ 
	 *  |  _| . |     | . | | .'| |   |  _|_ -|
	 *  |___|___|_|_|_|  _|_|__,|_|_|_|_| |___|
	 *                |_|
	 * notify on open records containing complaints (currently landing page only)
	 * especially to inform any defined roles but supervisors about any existing open complaints
	 * ceo too keeping them informed
	 */
	public function complaints(){
		if (!PERMISSION::permissionFor('complaintclosing') && !array_intersect(['ceo'], $_SESSION['user']['permissions'])) return 0;
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');
		$number = 0;
		foreach ($data as $row){
			if ($row['record_type'] === 'complaint'){
				if (PERMISSION::pending('complaintclosing', $row['closed'])) $number++;
			}
		} 
		return $number;
	}

	/**
	 *                                 _   _
	 *   ___ ___ ___ ___ _ _ _____ ___| |_| |___ ___
	 *  |  _| . |   |_ -| | |     | .'| . | | -_|_ -|
	 *  |___|___|_|_|___|___|_|_|_|__,|___|_|___|___|
	 *
	 * notify on pending incorporations
	 * process reminders for vendor- and product-topics
	 */
	public function consumables(){
		// get pending incorporations
		$unapproved = 0;
		if (PERMISSION::permissionFor('incorporation')){
			$allproducts = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products');
			foreach ($allproducts as $product) {
				if (!$product['incorporated']) continue;
				$product['incorporated'] = json_decode($product['incorporated'] ? : '', true);
				// get latest incorporation entry;
				$latestincorporation = array_pop($product['incorporated']);
				if (!$latestincorporation || isset($latestincorporation['_denied'])) continue;
				elseif (!PERMISSION::fullyapproved('incorporation', $latestincorporation)) $unapproved++;
			}
		}
		return $unapproved;
	}

	/**
	 *
	 *   ___ ___ ___ ___
	 *  |  _|  _| . |   |
	 *  |___|_| |___|_|_|
	 *
	 * clears expired files, automated message alerts and scheduled tasks
	 *
	 * this was supposed to be a cron job but i did not get it to work with iis from cli because of an pdo driver import issue
	 * however as this is handled by the application it does not rely on third party applications like cron or schtask
	 * and is currently not much of a performance issue
	 * 
	 * due to using api methods for user alerts and date handling it is easier to declare as this objects method instead of some class
	 * iterates over respective CONFIG['system]['cron']-array to execute if respective interval has been met
	 * can be extended if additional tasks are to come
	 */
	private function cron(){
		// also see maintenance.php->cron_log()
		$logfile = 'cron.log';
		$log = [];

		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
		$today = new \DateTime('now');
		$today->setTime(0, 0);

		$override = false;
		if ($this->_cronOverride && PERMISSION::permissionFor('cronoverride')) $override = true;

		foreach(CONFIG['system']['cron'] as $task => $minutes){
			try {
				if (!file_exists($logfile) || ($this->_date['servertime']->getTimestamp() - filemtime($logfile)) > $minutes * 60 || $override) {
					$execution = false;
					switch($task){
						case 'erp_interface_birthday_message':
							if (ERPINTERFACE && ERPINTERFACE->_instatiated && method_exists(ERPINTERFACE, 'birthdaymessage') && ERPINTERFACE->birthdaymessage()){
								// exit if no starting point is provided or override is set to avoid duplicates
								if (!file_exists($logfile) || $override) break;

								$from = date('Y-m-d', filemtime($logfile));
								if (!($erpdata = ERPINTERFACE->birthdaymessage($from))) break;
								$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
								foreach ($erpdata as $workmate){
									if (!array_search($workmate['name'], array_column($users, 'name'))) continue;
									$this->alertUserGroup(
										['user' => [$workmate['name']]],
										$this->_lang->GET('erpquery.integrations.user_birthday.' . ($workmate['past'] ? 'past' : 'today'), [
											':user' => $workmate['name']
										], true)
									);
								}
							}

							break;
						case 'erp_interface_casestate':
							// update records case state if set within erp system
							// does only update database null values
							// also see record.php->casestate()
							if (ERPINTERFACE && ERPINTERFACE->_instatiated && method_exists(ERPINTERFACE, 'casestate') && ERPINTERFACE->casestate()){
								$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_unclosed');
								// split multiple erp case numbers within records
								$casenumbers = [];
								foreach ($data as $row => $case){
									$data[$row]['_erp_case_numbers'] = preg_split('/[\s;,]+/', $case['erp_case_number'] ? : '');
									array_push($casenumbers, ...$data[$row]['_erp_case_numbers']);
								}

								if (!($erpdata = ERPINTERFACE->casestate($casenumbers))) break;
								
								// case states will be merged from all case numbers if having content.
								$updates = [];
								foreach ($data as $case){
									if (!$case['_erp_case_numbers']) continue;
									if (!isset($case['context'], $this->_lang->_DEFAULT['casestate'])) continue;
									$current_records = [];
									$case['case_state'] = json_decode($case['case_state'] ? : '', true);
									foreach($case['_erp_case_numbers'] as $casenumber){
										if (!isset($erpdata[$casenumber])) continue;
										foreach($erpdata[$casenumber] as $_ERPcaseState => $value){
											if (!isset($case['case_state'][$_ERPcaseState]) && $value) {
												$case['case_state'][$_ERPcaseState] = true;
												$current_records[] = [
													'author' => $this->_lang->GET('erpquery.integrations.update_via_erp_interface', [':systemuser' => CONFIG['system']['caroapp']], true),
													'date' => $this->_date['servertime']->format('Y-m-d H:i:s'),
													'document' => 0,
													'content' => UTILITY::json_encode([
														$this->_lang->GET('record.pseudodocument_' . $case['context'], [], true) => $this->_lang->GET('record.casestate_set', [':casestate' => $this->_lang->GET('casestate.' . $case['context'] . '.' . $_ERPcaseState, [], true)], true)
													])
												];
											}
										}
									}

									if ($current_records) {
										$records = json_decode($case['content'], true);
										foreach($current_records as $current){
											$records = BLOCKCHAIN::add($records, $current);
										}
										$updates = SQLQUERY::CHUNKIFY($updates, strtr(SQLQUERY::PREPARE('records_post'),
											[
												':context' => $this->_pdo->quote($case['context']),
												':case_state' => $this->_pdo->quote(UTILITY::json_encode($case['case_state'])),
												':record_type' => $this->_pdo->quote($case['record_type']) ? : 'NULL',
												':identifier' => $this->_pdo->quote($case['identifier']),
												':last_user' => $_SESSION['user']['id'],
												':last_document' => 'NULL',
												':content' => $this->_pdo->quote(UTILITY::json_encode($records)),
												':lifespan' => $case['lifespan'] ? intval($case['lifespan']) : 'NULL',
												':erp_case_number' => $this->_pdo->quote($case['erp_case_number']),
												':note' => $this->_pdo->quote($case['note'] ? : ''),
												':id' => $case['id'] // must come after :identifier, otherwise replacements fail
											]) . '; ');
									}
								}
								//file_put_contents($logfile, "\n\n" . json_encode($updates), FILE_APPEND);
								// run updates
								foreach ($updates as $update){
									SQLQUERY::EXECUTE($this->_pdo, $update);
								}
								$execution = true;
							}
							break;
						case 'erp_interface_orderdata':
							// update order state if set within erp system
							// does only update database null values
							// also see order.php->approved()
							if (ERPINTERFACE && ERPINTERFACE->_instatiated && method_exists(ERPINTERFACE, 'orderdata') && ERPINTERFACE->orderdata()){

								$oldest = SQLQUERY::EXECUTE($this->_pdo, 'order_get_appoved_oldest_approval');
								$oldest = $oldest ? $oldest[0]['approved'] : null;

								if (!$oldest || !($erpdata = ERPINTERFACE->orderdata(file_exists($logfile) ? date('Y-m-d H:i:s', filemtime($logfile)) : $oldest))) break;

								$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_search', [
									'values' => [
										':SEARCH' => '%'
									],
									'replacements' => [
										':organizational_unit' => implode(",", array_keys($this->_lang->_USER['units'])),
										':user' => 0
									]
								]);
								
								require_once('order.php');
								$orderstatistics = new ORDER();
								$updates = [];

								$states = [
									'ordered',
									'delivered_partially',
									'delivered_full'
								];

								foreach ($orders as $order){
									if ($order['ordered'] && $order['delivered_full']) continue;
									
									if ($identifiers = array_filter($erpdata, fn($o) => isset($o['identifier']) ? $o['identifier'] === UTILITY::identifier(' ', $order['approved']) : false)){ // identifier matched unless $erpdata ist an empty [[]]-array

										$order['order_data'] = json_decode($order['order_data'], true);

										$articles = array_filter($identifiers, fn($o) => 
											$o['vendor'] === $order['order_data']['vendor_label'] && // vendor matched
											(
												(isset($order['order_data']['ordernumber_label']) && $o['article_no'] === $order['order_data']['ordernumber_label']) || // either article number matches
												(!isset($order['order_data']['ordernumber_label']) && $o['article_name'] === $order['order_data']['productname_label']) // or if special order without article number at least the article name matches
											)
										);

										if ($articles && count($articles) === 1){
											$article = $articles[array_key_first($articles)];
											foreach ($states as $state){
												if ($order[$state] === null && $article[$state]){
													$updates = SQLQUERY::CHUNKIFY($updates, strtr(SQLQUERY::PREPARE('order_put_approved_order_state'),
														[
															':id' => $order['id'],
															':field' => $state,
															':date' => $this->_pdo->quote($article[$state])
														]) . '; ');
												}
											}
											if ($article['order_reference'] && !isset($order['order_data']['order_reference'])) {
												$order['order_data']['order_reference'] = $article['order_reference'];
													$updates = SQLQUERY::CHUNKIFY($updates, strtr(SQLQUERY::PREPARE('order_put_approved_order_addinformation'),
														[
															':id' => $order['id'],
															':order_data' => $this->_pdo->quote(UTILITY::json_encode($order['order_data']))
														]) . '; ');
											}

											//file_put_contents($logfile, "\n\n" . json_encode($updates), FILE_APPEND);
											if ($updates) {
												foreach ($updates as $update){
													SQLQUERY::EXECUTE($this->_pdo, $update);
												}
												$orderstatistics->statistics_update($order['id']);
											}
										}
									}
								}
								$execution = true;
							}
							break;


						case 'alert_new_orders':
							// alert purchase on new orders since last execution
							// prior to this implementation every approved order did a message resulting in spamming and the fear of them becoming dulled to notifications
							if (file_exists($logfile)){
								$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_unprocessed_alert', [
									'values' => [
										':timestamp' => date('Y-m-d H:i:s', filemtime($logfile)) 
									]
								]);
								$orders = $orders ? intval($orders[0]['num']) : null;

								if ($orders){
									$this->alertUserGroup(['permission' => ['purchase']], $this->_lang->GET('order.alert_purchase', [], true));		
								}
								$execution = true;
							}
							break;
						case 'alert_open_records_and_retention_periods':
							// alert unclosed records, records not having set lifespan
							// delete expired records including record attachments and orders that contain identifier e.g. as commission
							$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');
							$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
							$alerts = [];
							foreach ($data as $row){
								// alert about unclosed records if difference between now and last touch is divisible by reminder-interval
								if (($row['record_type'] === 'complaint' && !PERMISSION::fullyapproved('complaintclosing', $row['closed']))
									|| ($row['record_type'] !== 'complaint' && !$row['closed'])){
					
									$last = new \DateTime($row['last_touch']);
									$diff = intval(abs($last->diff($this->_date['servertime'])->days / CONFIG['lifespan']['records']['open_reminder']));
									if ($row['notified'] < $diff){
										$this->alertUserGroup(
											[
												// limit recipients to specialized workforce only, exclude admin, office and common
												'unit' => array_filter(explode(',', $row['units'] ? : ''), fn($u) => !in_array($u, ['common', 'admin', 'office']))
											],
											$this->_lang->GET('record.reminder_message', [
												':days' => $last->diff($this->_date['servertime'])->days,
												':date' => $this->convertFromServerTime(substr($row['last_touch'], 0, -3), true),
												':document' => $row['last_document'] ? : $this->_lang->GET('record.retype_pseudodocument_name', [], true),			
												':identifier' => "<a href=\"javascript:javascript:api.record('get', 'record', '" . $row['identifier'] . "')\">" . $row['identifier'] . "</a>"
											], true)
										);
										// prepare alert flags
										$alerts = SQLQUERY::CHUNKIFY($alerts, strtr(SQLQUERY::PREPARE('records_notified'),
											[
												':notified' => $diff,
												':identifier' => $this->_pdo->quote($row['identifier'])
											]) . '; ');
									}
								}
								// alert about unassigned retention period if difference between now and last touch is divisible by reminder-interval
								// only to set permissions within affected units, e.g. office members of orthotics1
								else {
									$last = new \DateTime($row['last_touch']);
									$diff = intval(abs($last->diff($this->_date['servertime'])->days / CONFIG['lifespan']['records']['open_reminder']));
									if ($row['notified'] < $diff && !$row['lifespan']){
										// skip non case related records for lifespan reminder
										if (!in_array($row['context'], ['casedocumentation', 'incident'])) continue;

										$this->alertUserGroup(
											[
												// limit recipients to specialized workforce only, exclude admin and common. typically matches supervisors and office members
												'permission' => PERMISSION::permissionFor('recordscasestate', true),
												'unit' => array_filter(explode(',', $row['units'] ? : ''), fn($u) => !in_array($u, ['common', 'admin']))
											],
											$this->_lang->GET('record.lifespan.reminder_message', [
												':identifier' => "<a href=\"javascript:javascript:api.record('get', 'record', '" . $row['identifier'] . "')\">" . $row['identifier'] . "</a>"
											], true)
										);
										// prepare alert flags
										$alerts = SQLQUERY::CHUNKIFY($alerts, strtr(SQLQUERY::PREPARE('records_notified'),
											[
												':notified' => $diff,
												':identifier' => $this->_pdo->quote($row['identifier'])
											]) . '; ');
									}
									elseif ($row['lifespan'] && abs($last->diff($this->_date['servertime'])->days) > intval($row['lifespan']) * 365 + ceil(intval($row['lifespan']) / 4)){ // last entry lifespan years + leap days as approximation
										// delete record attachments that begin with the identifier
										if (file_exists(UTILITY::directory('record_attachments'))){
											$delete = [];
											$fileidentifier = preg_replace('/[^\w\d]/m', '', $row['identifier']);
											foreach (glob(UTILITY::directory('record_attachments') . '/' . $fileidentifier . '*') as $file) {
												if($file == '.' || $file == '..') continue;
												$delete[] = $file;
											}
											UTILITY::delete($delete);
										}
										// prepare deletion
										$alerts = SQLQUERY::CHUNKIFY($alerts, strtr(SQLQUERY::PREPARE('records_delete'),
											[
												':id' => intval($row['id'])
											]) . '; ');

										// delete orders containing identifier e.g. archived case related orders having identifier as commission
										if (in_array($row['record_type'], array_keys($this->_lang->_DEFAULT['record']['type']))) {
											$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_search', [
												'values'=> [
													':SEARCH' => $row['identifier']
												],
												'wildcards' => 'contained',
												'replacements' => [
													':organizational_unit' => implode(',', array_keys($this->_lang->_DEFAULT['units'])), // all units
													':user' => ''
												]
											]);
											foreach($orders as $relatedorder){
												// DUPLICATE OF order.php->delete_approved_order()
												$order = json_decode($relatedorder['order_data'], true);
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
												$alerts = SQLQUERY::CHUNKIFY($alerts, strtr(SQLQUERY::PREPARE('order_delete_approved_order'),
													[
														':id' => intval($relatedorder['id'])
													]) . '; ');
											}
										}
									}
								}
							}
							// set alert flags
							foreach ($alerts as $alert){
								SQLQUERY::EXECUTE($this->_pdo, $alert);
							}
							$execution = true;
							break;
						case 'alert_unclosed_audits':
							// notify on unclosed audits
							$data = SQLQUERY::EXECUTE($this->_pdo, 'audit_get');
							foreach ($data as $row){
								if ($row['closed']) continue;
								// alert if applicable
								$last = new \DateTime($row['last_touch']);
								$diff = intval(abs($last->diff($this->_date['servertime'])->days / CONFIG['lifespan']['records']['open_reminder']));
								$row['notified'] = $row['notified'] || 0;
								if ($row['notified'] < $diff){
									$this->alertUserGroup(
										['permission' => [...PERMISSION::permissionFor('audit', true)]],
										$this->_lang->GET('audit.audit.reminder_message', [
											':days' => $last->diff($this->_date['servertime'])->days,
											':date' => $this->convertFromServerTime(substr($row['last_touch'], 0, -3), true),
											':unit' => $this->_lang->_DEFAULT['units'][$row['unit']]
										], true)
									);
									SQLQUERY::EXECUTE($this->_pdo, 'audit_and_management_notified',
										[
											'values' => [
												':notified' => $diff,
												':id' => $row['id']
											]
										]
									);
								}
							}
							$execution = true;
							break;
						case 'alert_undelivered_orders':
							// alert requesting undelivered orders or marking delivered as issued
							$alerts = [];
							$unissued = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_undelivered_unissued');
							// userlist to decode orderer
							$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
							foreach ($unissued as $order){
								$update = false;
								$decoded_order_data = null;

								// alert purchase to enquire information about estimated shipping
								// service gets an individual timespan as per config
								// return or cancellation don't matter as these are handled different on state setting
								$ordered = new \DateTime($order['ordered'] ? : '');
								switch($order['ordertype']){
									case "service":
										$receive_interval = intval(abs($ordered->diff($this->_date['servertime'])->days / CONFIG['lifespan']['service']['undelivered']));
										break;
									default:
										$receive_interval = intval(abs($ordered->diff($this->_date['servertime'])->days / CONFIG['lifespan']['order']['undelivered']));
										break;
								}
								$receive_interval = intval(abs($ordered->diff($this->_date['servertime'])->days / CONFIG['lifespan']['order']['undelivered']));
								if ($order['ordered'] && !$order['issued_full'] && $order['delivered_notified'] < $receive_interval){
									$decoded_order_data = json_decode($order['order_data'], true);
									$this->alertUserGroup(
										['permission' => ['purchase']],
										$this->_lang->GET('order.alert_undelivered_order', [
											':days' => $ordered->diff($this->_date['servertime'])->days,
											':ordertype' => $this->_lang->GET('order.ordertype.' . $order['ordertype'], [], true),
											':quantity' => $decoded_order_data['quantity_label'],
											':unit' => $decoded_order_data['unit_label'] ?? '',
											':number' => $decoded_order_data['ordernumber_label'] ?? '',
											':name' => $decoded_order_data['productname_label'] ?? '',
											':vendor' => $decoded_order_data['vendor_label'] ?? '',
											':commission' => $decoded_order_data['commission'],
											':orderer' => $decoded_order_data['orderer']
										], true)
									);
									$update = true;
								} else $receive_interval = $order['delivered_notified'];

								// alert unit members to mark as issued for orders and items returned from service
								// return or cancellation don't matter as these are not delivered
								$delivered_full = new \DateTime($order['delivered_full'] ? : '');
								$delivery_interval = intval(abs($delivered_full->diff($this->_date['servertime'])->days / CONFIG['lifespan']['order']['unissued']));
								if ($order['delivered_full'] && in_array($order['ordertype'], ['order', 'service']) && $order['issued_notified'] < $delivery_interval){
									if (!$decoded_order_data) $decoded_order_data = json_decode($order['order_data'], true);

									$organizational_unit = [$order['organizational_unit']];
									// if unit is common, add ordering users units except admin
									if ($organizational_unit === 'common' && $user = array_search(UTILITY::propertySet($decoded_order_data, 'orderer'), array_column($users, 'id'))){
										array_push($organizational_unit, ...array_filter(explode(',', $users[$user]['units']), fn($u) => !in_array($u, ['admin'])));
									}

									$this->alertUserGroup(
										['unit' => $organizational_unit],
										$this->_lang->GET('order.alert_unissued_order', [
											':days' => $delivered_full->diff($this->_date['servertime'])->days,
											':ordertype' => '<a href="javascript:void(0);" onclick="api.purchase(\'get\', \'approved\', \'null\', \'delivered_full\')"> ' . $this->_lang->GET('order.ordertype.' . $order['ordertype'], [], true) . '</a>',
											':quantity' => $decoded_order_data['quantity_label'],
											':unit' => $decoded_order_data['unit_label'] ?? '',
											':number' => $decoded_order_data['ordernumber_label'] ?? '',
											':name' => $decoded_order_data['productname_label'] ?? '',
											':vendor' => $decoded_order_data['vendor_label'] ?? '',
											':commission' => $decoded_order_data['commission'],
											':deliverydate' => $this->convertFromServerTime($order['delivered_full'], true)
										], true)
									);
									$update = true;
								} else $delivery_interval = $order['issued_notified'];

								// prepare alert flags
								if ($update) $alerts = SQLQUERY::CHUNKIFY($alerts, strtr(SQLQUERY::PREPARE('order_notified'),
									[
										':delivered_notified' => $receive_interval ? : 'NULL',
										':issued_notified' => $delivery_interval ? : 'NULL',
										':id' => $order['id']
									]) . '; ');

							}
							// set alert flags
							foreach ($alerts as $alert){
								SQLQUERY::EXECUTE($this->_pdo, $alert);
							}
							$execution = true;
							break;
						case 'delete_files_and_calendar':
							// clear up folders with limited files lifespan
							// clear up calendar entries marked as closed and for autodeletion
							UTILITY::tidydir('tmp', CONFIG['lifespan']['files']['tmp']);
							UTILITY::tidydir('sharepoint', CONFIG['lifespan']['files']['sharepoint']);
							$calendar->delete(null);

							// delete order statistics
							$prior_date = clone $this->_date['servertime'];
							SQLQUERY::EXECUTE($this->_pdo, 'order_truncate_order_statistics', [
								'values' => [
									':datetime' => $prior_date->modify('-' . CONFIG['lifespan']['order']['statistics'] . ' years')->format('Y-m-d H:i:s')
								]]);

							// delete messages for users with enabled autodeletion
							$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
							foreach($users as $user){
								$user['app_settings'] = json_decode($user['app_settings'] ? : '', true);
								if (isset($user['app_settings']['autodeleteMessages'])){
									$prior_date = clone $this->_date['servertime'];
									if ( $messages = SQLQUERY::EXECUTE($this->_pdo, 'message_get_messages_prior_date', [
										'values' => [
											':user' => $user['id'],
											':timestamp' => $prior_date->modify('-' . $user['app_settings']['autodeleteMessages'] . ' weeks')->format('Y-m-d H:i:s')
										]
									])) SQLQUERY::EXECUTE($this->_pdo, strtr(SQLQUERY::PREPARE('message_delete_messages'),[
											':user' => $user['id'],
											':ids' => implode(',', array_column($messages, 'id')) 
										]));
								}
							}

							$execution = true;
							break;
						case 'schedule_archived_orders_review':
							// schedule archived approved orders review
							$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_archived');
							$units = [];
							foreach ($orders as $order){
								if (!isset($units[$order['organizational_unit']])) $units[$order['organizational_unit']] = 0;
								$units[$order['organizational_unit']]++;
							}
							foreach ($units as $unit => $num){
								if ($num > CONFIG['limits']['order_approved_archived']) {
									$subject = $this->_lang->GET('order.alert_archived_limit', [
										':max' => CONFIG['limits']['order_approved_archived']
									], true);
									$reminders = $calendar->search('"' . $subject . '"'); // literal
									$open = false;
									foreach ($reminders as $reminder){
										if (!$reminder['closed']) $open = true;
									}
									if (!$open){
										$calendar->post([
											':id' => null,
											':type' => 'tasks',
											':span_start' => $today->format('Y-m-d H:i:s'),
											':span_end' => $today->format('Y-m-d H:i:s'),
											':author_id' => 1,
											':affected_user_id' => null,
											':organizational_unit' => $unit,
											':subject' => $subject,
											':misc' => null,
											':closed' => null,
											':alert' => 1,
											':autodelete' => 1
										]);		   		
									}
								}
							}
							$execution = true;
							break;
						case 'schedule_outdated_consumables_documents_review':
							// schedule consumables document reviews for vendor- and product-documents
							// at best only the most recent file by vendor and filename / productnumber.filename is processed if provided
							$vendors = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
							foreach ($vendors as $vendor){
								if ($vendor['hidden']) continue;
								// process vendor-documents
								if ($docfiles = UTILITY::listFiles(UTILITY::directory('vendor_documents', [':id' => $vendor['id']]))) {
									$documents = [];
									$considered = [];
									foreach ($docfiles as $path){
										$file = pathinfo($path);
										// match expiry date in {vendor}_{uploaddate}-{expirydate}_{filename_with_extension}
										preg_match('/(.+?)_(\d{8,8})-(\d{8,8})_(.+?)$/', $file['basename'], $fileNameComponents);
										if (!isset($fileNameComponents[3]) || $fileNameComponents[3] < $this->_date['servertime']->format('Ymd')) {
											// detect filename and continue on already considered, UTILITTY::listfiles desc by default
											if (isset($fileNameComponents[4])) {
												if (!in_array($fileNameComponents[4], $considered)) $considered[] = $fileNameComponents[4];
												else continue;
											}
											$documents[] = $file['basename'];
										}
									}
									if ($documents){
										// check for open reminders. if none add a new. dependent on language setting, may set multiple on system language change.
										$reminders = $calendar->search('"' . $this->_lang->GET('calendar.tasks.alert_vendor_document_expired', [':vendor' => $vendor['name']], true) . '"'); // literal
										$open = false;
										foreach ($reminders as $reminder){
											if (!$reminder['closed']) $open = true;
										}
										if (!$open){
											$calendar->post([
												':id' => null,
												':type' => 'tasks',
												':span_start' => $today->format('Y-m-d H:i:s'),
												':span_end' => $today->format('Y-m-d H:i:s'),
												':author_id' => 1,
												':affected_user_id' => null,
												':organizational_unit' => 'admin,office',
												':subject' => $this->_lang->GET('calendar.tasks.alert_vendor_document_expired', [':vendor' => $vendor['name']], true) . " - " . implode(" | ", $documents),
												':misc' => null,
												':closed' => null,
												':alert' => 1,
												':autodelete' => 1
												]);		   
										}
									}
								}
								// process product-documents
								if ($docfiles = UTILITY::listFiles(UTILITY::directory('vendor_products', [':id' => $vendor['id']]))) {
									$documents = [];
									$considered = [];
									foreach ($docfiles as $path){
										$file = pathinfo($path);
										// match expiry date in {Vendor}_{uploaddate}-{expirydate}_{articlenumber}_{filename_with_extension}
										preg_match('/(.+?)_(\d{8,8})-(\d{8,8})_(.+?)_(.+?)$/', $file['basename'], $fileNameComponents);
										if (!isset($fileNameComponents[3]) || $fileNameComponents[3] < $this->_date['servertime']->format('Ymd')) {
											// detect filename and continue on already considered, UTILITTY::listfiles desc by default
											if (isset($fileNameComponents[4]) && isset($fileNameComponents[5])) {
												if (!in_array($fileNameComponents[4] . $fileNameComponents[5], $considered)) $considered[] = $fileNameComponents[4] . $fileNameComponents[5];
												else continue;
											}
											$documents[] = $file['basename'];
										}
									}
									if ($documents){
										// check for open reminders. if none add a new. dependent on language setting, may set multiple on system language change.
										$reminders = $calendar->search('"' . $this->_lang->GET('calendar.tasks.alert_product_document_expired', [':vendor' => $vendor['name']], true) . '"'); // literal
										$open = false;
										foreach ($reminders as $reminder){
											if (!$reminder['closed']) $open = true;
										}
										if (!$open){
											$calendar->post([
												':id' => null,
												':type' => 'tasks',
												':span_start' => $today->format('Y-m-d H:i:s'),
												':span_end' => $today->format('Y-m-d H:i:s'),
												':author_id' => 1,
												':affected_user_id' => null,
												':organizational_unit' => 'office',
												':subject' => $this->_lang->GET('calendar.tasks.alert_product_document_expired', [':vendor' => $vendor['name']], true) . " - " . implode(" | ", $documents),
												':misc' => null,
												':closed' => null,
												':alert' => 1,
												':autodelete' => 1
												]);		   
										}
									}
								}
							}
							$execution = true;
							break;
						case 'schedule_responsibilities_renewal':
							// schedule renewal of expired responsibilities
							$responsibilities = SQLQUERY::EXECUTE($this->_pdo, 'user_responsibility_get_all');
							foreach ($responsibilities as $row){
								if (substr($row['span_end'], 0, 10) < $this->_date['servertime']->format('Y-m-d')) {
									// check for open reminders. if none add a new. dependent on language setting, may set multiple on system language change.
									$reminders = $calendar->search('"' . $this->_lang->GET('calendar.tasks.alert_responsibility_expired', [':task' => $row['responsibility'], ':units' => implode(',', array_map(fn($u) => $this->_lang->_DEFAULT['units'][$u], explode(',', $row['units'] ? : '')))], true) . '"'); // literal
									$open = false;
									foreach ($reminders as $reminder){
										if (!$reminder['closed']) $open = true;
									}
									if (!$open){
										$calendar->post([
											':id' => null,
											':type' => 'tasks',
											':span_start' => $today->format('Y-m-d H:i:s'),
											':span_end' => $today->format('Y-m-d H:i:s'),
											':author_id' => 1,
											':affected_user_id' => null,
											':organizational_unit' => 'admin',
											':subject' => $this->_lang->GET('calendar.tasks.alert_responsibility_expired', [':task' => $row['responsibility'], ':units' => implode(',', array_map(fn($u) => $this->_lang->_DEFAULT['units'][$u], explode(',', $row['units'] ? : '')))], true),
											':misc' => null,
											':closed' => null,
											':alert' => 1,
											':autodelete' => 1
										]);		   
									}
								}
							}
							$execution = true;
							break;
						case 'schedule_training_evaluation':
							// schedule training evaluation
							$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
							$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
								'replacements' => [
									':ids' => implode(',', array_column($users, 'id'))
								]
							]);
							foreach ($trainings as $training){
								if ($training['evaluation'] || !$training['date']) continue;
								$trainingdate = new \DateTime($training['date']);
								if (intval(abs($trainingdate->diff($this->_date['servertime'])->days)) > CONFIG['lifespan']['training']['evaluation']){
									if (($user = array_search($training['user_id'], array_column($users, 'id'))) !== false) { // no deleted users
										// check for open reminders. if none add a new. dependent on language setting, may set multiple on system language change.
										$subject = $this->_lang->GET('audit.userskills.notification_message', [
											':user' => $users[$user]['name'],
											':training' => $training['name'],
											':module' => $this->_lang->GET('audit.navigation.regulatory', [], true),
											':date' => $this->convertFromServerTime($training['date'], true)
										], true);
										$reminders = $calendar->search('"' . $subject . '"'); // literal
										$open = false;
										foreach ($reminders as $reminder){
											if (!$reminder['closed']) $open = true;
										}
										if (!$open){
											$calendar->post([
												':id' => null,
												':type' => 'tasks',
												':span_start' => $today->format('Y-m-d H:i:s'),
												':span_end' => $today->format('Y-m-d H:i:s'),
												':author_id' => 1,
												':affected_user_id' => null,
												':organizational_unit' => 'admin',
												':subject' => $subject,
												':misc' => null,
												':closed' => null,
												':alert' => 1,
												':autodelete' => 1
											]);		   		
										}
									}
								}
							}
							$execution = true;
							break;
						case 'schedule_retrainings':
							// schedule retrainings
							$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
							$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
								'replacements' => [
									':ids' => implode(',', array_column($users, 'id'))
								]
							]);
							$reversetrainings = array_reverse($trainings); // reversed to sort out comparison from rear
							foreach ($trainings as $training){
								if ($training['planned']) {
									continue;
								}
								if (!$training['expires']) continue;
								$trainingdate = new \DateTime($training['expires']);
								if (intval(abs($trainingdate->diff($this->_date['servertime'])->days)) < CONFIG['lifespan']['training']['renewal']){
									if (($user = array_search($training['user_id'], array_column($users, 'id'))) !== false) { // no deleted users
										$user = $users[$user];
										// check for scheduled trainings. if none add a new.
										$none = true;
										foreach ($reversetrainings as $scheduled){
											// must be of same name for this user
											if ($scheduled['user_id'] !== $user['id'] || $scheduled['name'] != $training['name']) continue;
											// date has been set and is newer than expiry, obviously a follow up training or already planned
											if ($scheduled['date'] && $scheduled['date'] > $training['expires'] || $scheduled['planned']) {
												$none = false;
												break;
											}
										}
										if ($none) {
											// insert scheduled training and message user and supervisor
											SQLQUERY::EXECUTE($this->_pdo, 'user_training_post', [
												'values' => [
													':name' => $training['name'],
													':user_id' => $user['id'],
													':date' => null,
													':expires' => null,
													':experience_points' => 0,
													':file_path' => null,
													':evaluation' => null,
													':planned' => UTILITY::json_encode([
														'user' => CONFIG['system']['caroapp'], // system user
														'date' => $this->_date['servertime']->format('Y-m-d H:i'),
														'content' => [$this->_lang->GET('user.training.schedule_timespan', [], true) => $this->_lang->GET('user.training.auto_schedule', [':expires' => $this->convertFromServerTime($training['expires'], true)], true)]
													])
												]
											]);
											$this->alertUserGroup([
													'permission' => ['supervisor'],
													'group' => array_filter(explode(',', $user['units'] ? : ''), fn($u) => !in_array($u, ['common', 'admin'])),
													'user' => [$user['name']]
												],
												$this->_lang->GET('user.training.auto_schedule_alert_message', [
													':user' => $user['name'],
													':training' => $training['name'],
													':date' => $this->convertFromServerTime($training['date'], true),
													':expires' => $this->convertFromServerTime($training['expires'], true)
												], true)
											);
										}
									}
								}
							}
							$execution = true;
							break;
					}
					if ($execution)
						$log[] = $this->_date['servertime']->format('Y-m-d H:i:s') . ' ' . $task . ': OK';
				}
			}
			catch (\Exception $e){
				$log[] = $this->_date['servertime']->format('Y-m-d H:i:s') . ' ' . $task . ': ' . str_replace("\n", ' ' , $e);
			}
		}
		if ($log) {
			file_put_contents($logfile, "\n\n" . implode("\n", $log), FILE_APPEND);
			if (array_intersect(['admin'], $_SESSION['user']['permissions']) || $override) {
				return implode("\n", $log);
			}
		}
		return null;
	}


	/**
	 *               ___ _ _ _           
	 *   ___ ___ _ _|  _|_| | |_ ___ ___ 
	 *  |  _|_ -| | |  _| | |  _| -_|  _|
	 *  |___|___|\_/|_| |_|_|_| |___|_|  
	 *  
	 * alerts eligible users about csv-filters having to be approved
	 */
	public function csvfilter(){
		if (!PERMISSION::permissionFor('csvrules')) return 0;
		// prepare all unapproved elements
		$filters = SQLQUERY::EXECUTE($this->_pdo, 'csvfilter_datalist');
		$unapproved = 0;
		$hidden = [];
		foreach ($filters as $element){
			if ($element['hidden']) $hidden[] = $element['name']; // since ordered by recent, older items will be skipped
			if (!in_array($element['name'], $hidden)){
				if (PERMISSION::pending('csvrules', $element['approval'])) $unapproved++;
				$hidden[] = $element['name']; // hide previous versions at all costs
			}
		}
		return $unapproved;
	}


	/**
	 *     _                           _       
	 *   _| |___ ___ _ _ _____ ___ ___| |_ ___ 
	 *  | . | . |  _| | |     | -_|   |  _|_ -|
	 *  |___|___|___|___|_|_|_|___|_|_|_| |___|
	 * 
	 * alerts eligible users about documents and components having to be approved
	 */
	public function documents(){
		// prepare all unapproved elements
		$components = SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist');
		$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$unapproved = 0;
		$hidden = [];
		foreach (array_merge($components, $documents) as $element){
			if ($element['context'] === 'bundle') continue;
			if ($element['hidden']) $hidden[] = $element['context'] . $element['name']; // since ordered by recent, older items will be skipped
			if (!in_array($element['context'] . $element['name'], $hidden)){
				if (PERMISSION::pending('documentapproval', $element['approval'])) $unapproved++;
				$hidden[] = $element['context'] . $element['name']; // hide previous versions at all costs
			}
		}
		return $unapproved;
	}

	/**
	 *                                           _               _           
	 *   _____ ___ ___ ___ ___ ___ _____ ___ ___| |_ ___ ___ _ _|_|___ _ _ _ 
	 *  |     | .'|   | .'| . | -_|     | -_|   |  _|  _| -_| | | | -_| | | |
	 *  |_|_|_|__,|_|_|__,|_  |___|_|_|_|___|_|_|_| |_| |___|\_/|_|___|_____|
	 *                    |___|
	 * notify on unclosed managementreviews
	 */
	public function managementreview(){
		if (!PERMISSION::permissionFor('audit')) return 0;
		$data = SQLQUERY::EXECUTE($this->_pdo, 'management_get');
		$number = 0;
		foreach ($data as $row){
			if ($row['closed']) continue;
			$number++;
		} 
		return $number;
	}

	/**
	 *
	 *   _____ ___ ___ ___ _ _ ___ ___ ___
	 *  |     | -_| .'|_ -| | |  _| -_|_ -|
	 *  |_|_|_|___|__,|___|___|_| |___|___|
	 *
	 * number of unclosed measures 
	 */
	public function measures(){
		$measures = SQLQUERY::EXECUTE($this->_pdo, 'measure_get');
		return count(array_filter($measures, fn($m) => !$m['closed']));
	}

	/**
	 *                                                 _   _ ___ _       _
	 *   _____ ___ ___ ___ ___ ___ ___ _ _ ___ ___ ___| |_|_|  _|_|___ _| |
	 *  |     | -_|_ -|_ -| .'| . | -_| | |   |   | . |  _| |  _| | -_| . |
	 *  |_|_|_|___|___|___|__,|_  |___|___|_|_|_|_|___|_| |_|_| |_|___|___|
	 *                        |___|
	 * number of new messages that have not been notified of (system alert)
	 */
	public function messageunnotified(){
		$unnotified = SQLQUERY::EXECUTE($this->_pdo, 'message_get_unnotified', [
			'values' => [
				':user' => $_SESSION['user']['id']
			]
		]);
		$unnotified = $unnotified ? intval($unnotified[0]['number']) : 0;
		SQLQUERY::EXECUTE($this->_pdo, 'message_put_notified', [
			'values' => [
				':user' => $_SESSION['user']['id']
			]
		]);
		return $unnotified;
	}
	
	/**
	 *
	 *   _____ ___ ___ ___ ___ ___ ___ _ _ ___ ___ ___ ___ ___
	 *  |     | -_|_ -|_ -| .'| . | -_| | |   |_ -| -_| -_|   |
	 *  |_|_|_|___|___|___|__,|_  |___|___|_|_|___|___|___|_|_|
	 *                        |___|
	 * number of unseen messages
	 */
	public function messageunseen(){
		$unseen = SQLQUERY::EXECUTE($this->_pdo, 'message_get_unseen', [
			'values' => [
				':user' => $_SESSION['user']['id']
			]
		]);
		$unseen = $unseen ? intval($unseen[0]['number']) : 0;
		return $unseen;
	}

	/**
	 *             _
	 *   ___ ___ _| |___ ___
	 *  | . |  _| . | -_|  _|
	 *  |___|_| |___|___|_|
	 *
	 * number of unprocessed orders
	 */
	public function order(){
		$unprocessed = 0;
		if (PERMISSION::permissionFor('orderprocessing')){
			$unprocessed = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_unprocessed');
			$unprocessed = $unprocessed ? intval($unprocessed[0]['num']) : 0;
		}
		return $unprocessed;
	}


	/**
	 *                                 _           _
	 *   ___ ___ ___ ___ ___ ___ ___ _| |___ ___ _| |___ ___ ___
	 *  | . |  _| -_| . | .'|  _| -_| . | . |  _| . | -_|  _|_ -|
	 *  |  _|_| |___|  _|__,|_| |___|___|___|_| |___|___|_| |___|
	 *  |_|         |_|
	 * 
	 * number of prepared orders for assigned units
	 */
	public function preparedorders(){
		if (!$_SESSION['user']['orderauth']) return 0;
		$prepared = 0;
		$orders = SQLQUERY::EXECUTE($this->_pdo, 'order_get_prepared_orders');
		// userlist to decode orderer
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');

		foreach ($orders as $row) {
			$order_data = json_decode($row['order_data'], true);
			// if unit intersects, or orderer is own unit member including self, except admin
			$unit_intersection = boolval(array_intersect([UTILITY::propertySet($order_data, 'organizational_unit') ? : ''], $_SESSION['user']['units']));
			if (!$unit_intersection && $user = array_search(UTILITY::propertySet($order_data, 'orderer'), array_column($users, 'id'))){
				$unit_intersection = boolval(array_intersect($_SESSION['user']['units'], array_filter(explode(',', $users[$user]['units']), fn($u) => !in_array($u, ['admin']))));
			}
			if ($unit_intersection) {
				$prepared++;
			}
		}
		return $prepared;
	}

	/**
	 *                         _
	 *   ___ ___ ___ ___ ___ _| |___
	 *  |  _| -_|  _| . |  _| . |_ -|
	 *  |_| |___|___|___|_| |___|___|
	 *
	 * number of unclosed records for assigned units
	 * alert message to units interval wise excluding
	 * * common
	 * * admin
	 * * office
	 */
	public function records(){
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');
		$number = 0;
		foreach ($data as $row){
			if (($row['record_type'] === 'complaint' && !PERMISSION::fullyapproved('complaintclosing', $row['closed']))
				|| ($row['record_type'] !== 'complaint' && !$row['closed'])){
				// rise counter for unit member
				if ($row['units'] && in_array($row['context'], ['casedocumentation', 'incident']) && array_intersect(
					array_filter(explode(',', $row['units'] ? : ''), fn($u) => !in_array($u, ['common', 'admin'])),
					$_SESSION['user']['units']
				)) $number++;
			}
		}
		return $number;
	}

	/**
	 *                               _ _   _ _ _ _   _
	 *   ___ ___ ___ ___ ___ ___ ___|_| |_|_| |_| |_|_|___ ___
	 *  |  _| -_|_ -| . | . |   |_ -| | . | | | |  _| | -_|_ -|
	 *  |_| |___|___|  _|___|_|_|___|_|___|_|_|_|_| |_|___|___|
	 *              |_|
	 *
	 * responsibilities awaiting acceptance
	 */
	public function responsibilities(){
		$number = 0;
		$responsibilities = SQLQUERY::EXECUTE($this->_pdo, 'user_responsibility_get_all');
		foreach ($responsibilities as $row){
			$row['assigned_users'] = json_decode($row['assigned_users'], true);
			if (isset($row['assigned_users'][$_SESSION['user']['id']]) && !$row['assigned_users'][$_SESSION['user']['id']]) {
				$number++;
				continue;
			}
			$row['proxy_users'] = json_decode($row['proxy_users'], true);
			if (isset($row['proxy_users'][$_SESSION['user']['id']]) && !$row['proxy_users'][$_SESSION['user']['id']]) {
				$number++;
			}
		}
		return $number;
	}

	/**
	 *           _         _     _       _ _           _     _
	 *   ___ ___| |_ ___ _| |_ _| |___ _| | |_ ___ ___|_|___|_|___ ___ ___
	 *  |_ -|  _|   | -_| . | | | | -_| . |  _|  _| .'| |   | |   | . |_ -|
	 *  |___|___|_|_|___|___|___|_|___|___|_| |_| |__,|_|_|_|_|_|_|_  |___|
	 *                                                            |___|
	 * schedule training evaluation and insert schedules re-trainings on expiring ones
	 * alert message to units interval wise excluding
	 * * common
	 * * admin
	 * * office
	 * 
	 * currently called from landing page only
	 */
	public function scheduledtrainings(){
		// schedule training evaluation
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', array_column($users, 'id'))
			]
		]);

		$unitusers = [];
		$number = 0;
		// find all users within current users units
		foreach ($users as $user){
			if (array_intersect(array_filter(explode(',', $user['units'] ? : ''), fn($u) => !in_array($u, ['common', 'admin'])), $_SESSION['user']['units'])) $unitusers[] = $user['id'];
		}
		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', $unitusers)
			]
		]);
		foreach ($trainings as $training){
			if ($training['planned']) {
				$number++;
				continue;
			}
		}
		return $number;
	}

	/**
	 *   _           _       
	 *  | |_ ___ ___| |_ ___ 
	 *  |  _| .'|_ -| '_|_ -|
	 *  |_| |__,|___|_,_|___|
	 *                       
	 * alerts a user group if selected
	 */
	public function tasks(){
		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
		$today = new \DateTime('now');
		$today->setTime(0, 0);

		// alert if applicable despite cron for e.g. entries of sick colleagues after cron and still being notified during the day
		// $alerts = $calendar->alert($today->format('Y-m-d'));
		$alerts = $calendar->alert(); // given date not supported anymore?
		foreach ($alerts as $event){
			// alert current events including workmates pto if alert is set
			$this->alertUserGroup(['unit' => $event['organizational_unit'] ? explode(',', $event['organizational_unit']) : explode(',', $event['affected_user_units'] ? : '')],
				$this->_lang->GET('calendar.tasks.alert_message', [
					':content' => (isset($this->_lang->_USER['calendar']['timesheet']['pto'][$event['subject']]) ? $this->_lang->GET('calendar.timesheet.pto.' . $event['subject'], [], true) : $event['subject']),
					':date' => substr($event['span_start'], 0, 10),
					':author' => $event['author'],
					':due' => substr($event['span_end'], 0, 10)
				], true) . ($event['affected_user'] ? ' (' . $event['affected_user'] . ')': ''));
		}

		$events = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
		$uncompleted = 0;
		foreach ($events as $row){
			if (!$row['organizational_unit']) continue; 
			if (array_intersect(explode(',', $row['organizational_unit'] ? : ''), $_SESSION['user']['units']) && $row['type'] === 'tasks' && !$row['closed']) $uncompleted++;
		}
		return $uncompleted;
	}

	/**
	 *                 _   _ _     _       
	 *   _ _ _ ___ ___| |_| |_|___| |_ ___ 
	 *  | | | | . |  _| '_| | |_ -|  _|_ -|
	 *  |_____|___|_| |_,_|_|_|___|_| |___|
	 *
	 */
	public function worklists(){
		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
		$today = new \DateTime('now');
		$today->setTime(0, 0);

		$events = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
		$uncompleted = 0;
		foreach ($events as $row){
			if (!$row['organizational_unit']) continue; 
			if (array_intersect(array_filter(explode(',', $row['organizational_unit'] ? : ''), fn($u) => !in_array($u, ['common', 'admin'])), $_SESSION['user']['units']) && $row['type'] === 'worklists' && !$row['closed']) $uncompleted++;
		}
		return $uncompleted;
	}
}
?>
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

require_once(__DIR__ . '/_calendarutility.php');

class CRON extends API {
	// message alerts and auto scheduled tasks
	// no need for auth and permission handling

	public $_requestedMethod = REQUEST[1];

	public function __construct(){
		parent::__construct();
	}

	public function jobs(){
		$this->clearTemp();
		$this->consumables();
		$this->order();
		$this->records();
		$this->responsibilities();
		$this->trainings();
		echo $this->_date['servertime']->format('Y-m-d H:i:s') . " OK";
	}

	// delete temporary files as defined within config
	private function clearTemp(){
		// clear up tmp folder
		UTILITY::tidydir('tmp', CONFIG['lifespan']['tmp']);

		// gather sharepoint files
		$files = UTILITY::listFiles(UTILITY::directory('sharepoint'), 'asc');
		if ($files){
			foreach ($files as $file){
				// prepare file properies and calculate remaining lifespan
				$file = ['path' => $file, 'name' => pathinfo($file)['basename']];
				$filetime = filemtime($file['path']);

				// delete expired files
				if ((time() - $filetime) / 3600 > CONFIG['lifespan']['sharepoint']) {
					UTILITY::delete($file['path']);
				}
			}
		}
	}

	// reminder to review certificates and product files
	private function consumables(){
		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
		$today = new DateTime('now');
		$today->setTime(0, 0);

		// schedule vendor certificate request
		$vendors = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
		foreach ($vendors as $vendor){
			if ($vendor['hidden']) continue;
			$certificate = json_decode($vendor['certificate'] ? : '', true);
			if (isset($certificate['validity']) && $certificate['validity']) $validity = new DateTime($certificate['validity']);
			else continue;
			if ($validity > $today) continue;
			// check for open reminders. if none add a new. dependent on language setting, may set multiple on system language change.
			$reminders = $calendar->search($this->_lang->GET('calendar.schedule.alert_vendor_certificate_expired', [':vendor' => $vendor['name']], true));
			$open = false;
			foreach($reminders as $reminder){
				if (!$reminder['closed']) $open = true;
			}
			if (!$open){
				$calendar->post([
					':type' => 'schedule',
					':span_start' => $today->format('Y-m-d H:i:s'),
					':span_end' => $today->format('Y-m-d H:i:s'),
					':author_id' => 1,
					':affected_user_id' => null,
					':organizational_unit' => 'admin,office',
					':subject' => $this->_lang->GET('calendar.schedule.alert_vendor_certificate_expired', [':vendor' => $vendor['name']], true),
					':misc' => null,
					':closed' => null,
					':alert' => 1
					]);		   
			}
		}

		// schedule products document evaluation or update

		// gather documents per product by vendor, reducing loops later on
		// keeping only the most recent upload per article number
		$documents = [];
		foreach ($vendors as $vendor){
			if ($vendor['hidden']) continue;
			if ($docfiles = UTILITY::listFiles(UTILITY::directory('vendor_products', [':name' => $vendor['immutable_fileserver']]))) {
				if (!isset($documents[$vendor['id']])) $documents[$vendor['id']] = [];
				foreach($docfiles as $path){
					$file = pathinfo($path);
					$article_no = explode('_', $file['filename'])[2];
					$date = date('Y-m-d', filemtime($path));
					if (!isset($documents[$vendor['id']][$article_no]) || $documents[$vendor['id']][$article_no] < $date) $documents[$vendor['id']][$article_no] = $date;
				}
			}
		}

		$products = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products');
		$alerts = [];
		foreach($products as $product){
			if ($product['hidden']) continue;
			$article_no = preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $product['article_no'] ? : '');
			if (isset($documents[$product['vendor_id']]) && isset($documents[$product['vendor_id']][$article_no])){
				$upload = new DateTime($documents[$product['vendor_id']][$article_no]);
				$diff = intval(abs($upload->diff($this->_date['servertime'])->days / CONFIG['lifespan']['product_documents']));
				if ($product['document_reminder'] < $diff){
					$calendar->post([
						':type' => 'schedule',
						':span_start' => $today->format('Y-m-d H:i:s'),
						':span_end' => $today->format('Y-m-d H:i:s'),
						':author_id' => 1,
						':affected_user_id' => null,
						':organizational_unit' => 'office',
						':subject' => $this->_lang->GET('calendar.schedule.product_document_evaluation', [':number' => $product['article_no'], ':name' => $product['article_name'], ':vendor' => $product['vendor_name'], ':days' => abs($upload->diff($this->_date['servertime'])->days)], true),
						':misc' => null,
						':closed' => null,
						':alert' => 1
						]);
					// prepare alert flags
					$alerts = SQLQUERY::CHUNKIFY($alerts, strtr(SQLQUERY::PREPARE('consumables_put_last_document_evaluation'),
						[
							':notified' => $diff,
							':id' => $product['id']
						]) . '; ');
				}
			}
		}
		// set alert flags
		foreach ($alerts as $alert){
			SQLQUERY::EXECUTE($this->_pdo, $alert);
		}
	}

	private function order(){
		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
		$today = new DateTime('now');
		$today->setTime(0, 0);

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
				$reminders = $calendar->search($subject);
				$open = false;
				foreach($reminders as $reminder){
					if (!$reminder['closed']) $open = true;
				}
				if (!$open){
					$calendar->post([
						':type' => 'schedule',
						':span_start' => $today->format('Y-m-d H:i:s'),
						':span_end' => $today->format('Y-m-d H:i:s'),
						':author_id' => 1,
						':affected_user_id' => null,
						':organizational_unit' => $unit,
						':subject' => $subject,
						':misc' => null,
						':closed' => null,
						':alert' => 1
						]);		   		
				}
			}
		}

		$alerts = [];
		$undelivered = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_unreceived_undelivered');
		foreach($undelivered as $order){
			$update = false;
			$decoded_order_data = null;
			$ordered = new DateTime($order['ordered'] ? : '');
			$receive_interval = intval(abs($ordered->diff($this->_date['servertime'])->days / CONFIG['lifespan']['order_unreceived']));
			if ($order['ordered'] && $order['notified_received'] < $receive_interval){
				$decoded_order_data = json_decode($order['order_data'], true);
				$this->alertUserGroup(
					['permission' => ['purchase']],
					$this->_lang->GET('order.alert_unreceived_order', [
						':days' => $ordered->diff($this->_date['servertime'])->days,
						':ordertype' => $this->_lang->GET('order.ordertype.' . $order['ordertype'], [], true),
						':quantity' => $decoded_order_data['quantity_label'],
						':unit' => isset($decoded_order_data['unit_label']) ? $decoded_order_data['unit_label'] : '',
						':number' => isset($decoded_order_data['ordernumber_label']) ? $decoded_order_data['ordernumber_label'] : '',
						':name' => isset($decoded_order_data['productname_label']) ? $decoded_order_data['productname_label'] : '',
						':vendor' => isset($decoded_order_data['vendor_label']) ? $decoded_order_data['vendor_label'] : '',
						':commission' => $decoded_order_data['commission'],
						':orderer' => $decoded_order_data['orderer']
					], true)
				);
				$update = true;
			} else $receive_interval = $order['notified_received'];

			$received = new DateTime($order['received'] ? : '');
			$delivery_interval = intval(abs($received->diff($this->_date['servertime'])->days / CONFIG['lifespan']['order_undelivered']));
			if ($order['received'] && $order['notified_delivered'] < $delivery_interval){
				if (!$decoded_order_data) $decoded_order_data = json_decode($order['order_data'], true);
				$this->alertUserGroup(
					['unit' => [$order['organizational_unit']]],
					$this->_lang->GET('order.alert_undelivered_order', [
						':days' => $received->diff($this->_date['servertime'])->days,
						':ordertype' => '<a href="javascript:void(0);" onclick="api.purchase(\'get\', \'approved\', \'null\', \'null\', \'received\')"> ' . $this->_lang->GET('order.ordertype.' . $order['ordertype'], [], true) . '</a>',
						':quantity' => $decoded_order_data['quantity_label'],
						':unit' => isset($decoded_order_data['unit_label']) ? $decoded_order_data['unit_label'] : '',
						':number' => isset($decoded_order_data['ordernumber_label']) ? $decoded_order_data['ordernumber_label'] : '',
						':name' => isset($decoded_order_data['productname_label']) ? $decoded_order_data['productname_label'] : '',
						':vendor' => isset($decoded_order_data['vendor_label']) ? $decoded_order_data['vendor_label'] : '',
						':commission' => $decoded_order_data['commission'],
						':receival' => $this->convertFromServerTime($order['received'], true)
					], true)
				);
				$update = true;
			} else $delivery_interval = $order['notified_delivered'];

			// prepare alert flags
			if ($update) $alerts = SQLQUERY::CHUNKIFY($alerts, strtr(SQLQUERY::PREPARE('order_notified'),
				[
					':notified_received' => $receive_interval ? : 'NULL',
					':notified_delivered' => $delivery_interval ? : 'NULL',
					':id' => $order['id']
				]) . '; ');

		}
		// set alert flags
		foreach ($alerts as $alert){
			SQLQUERY::EXECUTE($this->_pdo, $alert);
		}

	}

	private function records(){
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');
		$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$alerts = [];
		foreach ($data as $row){
			if (($row['record_type'] === 'complaint' && !PERMISSION::fullyapproved('complaintclosing', $row['closed']))
				|| ($row['record_type'] !== 'complaint' && !$row['closed'])){

				// alert if applicable
				$last = new DateTime($row['last_touch']);
				$diff = intval(abs($last->diff($this->_date['servertime'])->days / CONFIG['lifespan']['open_record_reminder']));
				if ($row['notified'] < $diff){
					// get last considered document
					$lastdocument = $documents[array_search($row['last_document'], array_column($documents, 'id'))] ? : ['name' => $this->_lang->GET('record.retype_pseudodocument_name', [], true)];

					$this->alertUserGroup(
						['unit' => array_filter(explode(',', $row['units'] ? : ''), fn($u) => !in_array($u, ['common', 'admin', 'office']))],
						$this->_lang->GET('record.reminder_message', [
							':days' => $last->diff($this->_date['servertime'])->days,
							':date' => $this->convertFromServerTime(substr($row['last_touch'], 0, -3), true),
							':document' => $lastdocument['name'],			
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
		}
		// set alert flags
		foreach ($alerts as $alert){
			SQLQUERY::EXECUTE($this->_pdo, $alert);
		}
	}

	private function responsibilities(){
		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
		$today = new DateTime('now');
		$today->setTime(0, 0);

		$responsibilities = SQLQUERY::EXECUTE($this->_pdo, 'user_responsibility_get_all');
		foreach($responsibilities as $row){
			if ($row['hidden']) continue;
			if (substr($row['span_end'], 0, 10) < $this->_date['servertime']->format('Y-m-d')) {
				// check for open reminders. if none add a new. dependent on language setting, may set multiple on system language change.
				$reminders = $calendar->search($this->_lang->GET('calendar.schedule.alert_responsibility_expired', [':task' => $row['responsibility'], ':units' => implode(',', array_map(fn($u) => $this->_lang->_DEFAULT['units'][$u], explode(',', $row['units'] ? : '')))], true));
				$open = false;
				foreach($reminders as $reminder){
					if (!$reminder['closed']) $open = true;
				}
				if (!$open){
					$calendar->post([
						':type' => 'schedule',
						':span_start' => $today->format('Y-m-d H:i:s'),
						':span_end' => $today->format('Y-m-d H:i:s'),
						':author_id' => 1,
						':affected_user_id' => null,
						':organizational_unit' => 'admin',
						':subject' => $this->_lang->GET('calendar.schedule.alert_responsibility_expired', [':task' => $row['responsibility'], ':units' => implode(',', array_map(fn($u) => $this->_lang->_DEFAULT['units'][$u], explode(',', $row['units'] ? : '')))], true),
						':misc' => null,
						':closed' => null,
						':alert' => 1
						]);		   
				}
			}
		}
	}

	private function trainings(){
		$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
		$today = new DateTime('now');
		$today->setTime(0, 0);

		// schedule training evaluation
		$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', array_column($users, 'id'))
			]
		]);
		foreach($trainings as $training){
			if ($training['evaluation'] || !$training['date']) continue;
			$trainingdate = new DateTime($training['date']);
			if (intval(abs($trainingdate->diff($this->_date['servertime'])->days)) > CONFIG['lifespan']['training_evaluation']){
				if (($user = array_search($training['user_id'], array_column($users, 'id'))) !== false) { // no deleted users
					// check for open reminders. if none add a new. dependent on language setting, may set multiple on system language change.
					$subject = $this->_lang->GET('audit.userskills_notification_message', [
						':user' => $users[$user]['name'],
						':training' => $training['name'],
						':module' => $this->_lang->GET('menu.tools.regulatory', [], true),
						':date' => $this->convertFromServerTime($training['date'], true)
					], true);
					$reminders = $calendar->search($subject);
					$open = false;
					foreach($reminders as $reminder){
						if (!$reminder['closed']) $open = true;
					}
					if (!$open){
							$calendar->post([
							':type' => 'schedule',
							':span_start' => $today->format('Y-m-d H:i:s'),
							':span_end' => $today->format('Y-m-d H:i:s'),
							':author_id' => 1,
							':affected_user_id' => null,
							':organizational_unit' => 'admin',
							':subject' => $subject,
							':misc' => null,
							':closed' => null,
							':alert' => 1
							]);		   		
					}
				}
			}
		}

		$trainings = SQLQUERY::EXECUTE($this->_pdo, 'user_training_get_user', [
			'replacements' => [
				':ids' => implode(',', array_column($users, 'id'))
			]
		]);
		$reversetrainings = array_reverse($trainings); // reversed to sort out comparison from rear
		foreach($trainings as $training){
			if ($training['planned']) {
				continue;
			}
			if (!$training['expires']) continue;
			$trainingdate = new DateTime($training['expires']);
			if (intval(abs($trainingdate->diff($this->_date['servertime'])->days)) < CONFIG['lifespan']['training_renewal']){
				if (($user = array_search($training['user_id'], array_column($users, 'id'))) !== false) { // no deleted users
					$user = $users[$user];
					// check for scheduled trainings. if none add a new.
					$none = true;
					foreach($reversetrainings as $scheduled){
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
									'user' => $users[0]['name'], // system user
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
								':date' =>$this->convertFromServerTime($training['date'], true),
								':expires' => $this->convertFromServerTime($training['expires'], true)
							], true)
						);
					}
				}
			}
		}
	}
}
?>
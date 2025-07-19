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

namespace CARO\API;

// authentify endpoint handling, menu and landing page methods
require_once('_calendarutility.php');
require_once('notification.php');

class APPLICATION extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
    private $_requestedManual = null;
    private $_search = null;

	/**
	 * init parent class and set private requests
	 */
	public function __construct(){
		parent::__construct();
		$this->_requestedManual = $this->_search = isset(REQUEST[2]) ? REQUEST[2] : null;
	}

	/**
	 *           _   _           _   _ ___     
	 *   ___ _ _| |_| |_ ___ ___| |_|_|  _|_ _ 
	 *  | .'| | |  _|   | -_|   |  _| |  _| | |
	 *  |__,|___|_| |_|_|___|_|_|_| |_|_| |_  |
	 *                                    |___|
	 * (re)log in user or destroy session
	 */
	public function authentify(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'DELETE':
				$params = session_get_cookie_params();
				setcookie(session_name(), '', 1, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
				session_destroy();
				session_write_close();
				$this->response([
					'user' => [],
					'config' => [
						'application' => [
							'defaultlanguage' => CONFIG['application']['defaultlanguage'],
						]
					]
				]);
				break;
			case 'POST':
				$this->response($this->_auth);
			default:
			$response = [
				'I don\'t make threats. But I do keep promises. And I promise you I shall cause you more trouble than you bargained for... If you don\'t return my property!',
				'An unintelligent enemy is far less dangerous than an intelligent one, Jamie. Just act stupid. Do you think you can manage that?',
				'Courage isn\'t just a matter of not being frightened, you know. It\'s being afraid and doing what you have to do, anyway.',
				'You may be a doctor. But I am the Doctor. The definite article, you might say!',
				'You know how it is - you put things off for a day, and next thing you know, it\'s a hundred years later.',
				'This is a situation that requires tact and finesse. Fortunately, I am blessed with both!',
				'Think about me when you\'re living your life one day after another, all in a neat pattern. Think about the homeless traveler and his old police box, with his days like crazy paving.',
				'I love humans. Always seeing patterns in things that aren\'t there.',
				'Great men are forged in fire. It is the privilege of lesser men to light the flame. Whatever the cost.',
				'Just This Once, Everybody Lives!',
				'Time is more like a big ball of wibbly-wobbly, timey-wimey stuff.',
				'Hello, I\'m the Doctor. Basically... run!',
				'Hardly anything is evil, but most things are hungry. Hunger looks very like evil from the wrong end of the cutlery. Or do you think that your bacon sandwich loves you back?',
				'If I was still a bloke, I could get on with the job and not have to waste time defending myself.',
				'I fought all those battles for all those years, and now I know what for. This. I\'ve never been so happy in my life.',
				'We\'re all dead eventually. There\'s hardly any time that we\'re not dead. Which is a good thing, too! We\'ve got to keep the pace up, otherwise nothing would get done. Dying defines us.',
				'No time to be tired. Still work to do out there. Lives at stake. Armies being born. People need the Doctor.'
			];
			$this->response([$response[array_rand($response)]]);
		}
	}

	/**
	 *       _           _   
	 *   ___| |_ ___ _ _| |_ 
	 *  | .'| . | . | | |  _|
	 *  |__,|___|___|___|_|  
	 * 
	 * display application info 
	 */
	public function about(){
		$lines = ['frontend' => 0, 'backend' => 0, 'code' => 0, 'documentation' => 0, 'configuration' => 0];
		foreach (['../', '../js', '../api'] as $dir){
			foreach (scandir($dir) as $file){
				if (!isset(pathinfo($file)['extension']) || !in_array(pathinfo($file)['extension'], ['php','ini','js','html','css','md','json'])) continue;
				if (in_array(pathinfo($file)['extension'], ['md'])){
					$lines['documentation'] += count(file($dir . '/' . $file));
				}
				else {
					$lines['code'] += count(file($dir . '/' . $file));
				}
			}
		}
		foreach (['../templates'] as $dir){
			foreach (scandir($dir) as $file){
				if (!isset(pathinfo($file)['extension']) || !in_array(pathinfo($file)['extension'], ['php','ini','js','html','css','md','json'])) continue;
				if (in_array(pathinfo($file)['extension'], ['md'])){
					$lines['documentation']+= count(file($dir . '/' . $file));			
				}
				else {
					$lines['configuration']+= count(file($dir . '/' . $file));
				}
			}
		}

		$response = ['render' => ['content' => []]];

		if (isset($_SESSION['user'])){
			// add manual filtered by applicable permission
			$query = SQLQUERY::EXECUTE($this->_pdo, 'application_get_manual');
			$topics = [];
			foreach ($query as $row){
				if (PERMISSION::permissionIn($row['permissions'])) $topics[] =
					[[
						'type' => 'textsection',
						'attributes' => [
							'name' => $row['title']
						],
						'content' => $row['content']
					]];
			}
			if ($topics) $response['render']['content'][] = $topics;
		}
		
		if (PERMISSION::permissionFor('appmanual')) $response['render']['content'][] = [
			'type' => 'button',
			'attributes' => [
				'value' => $this->_lang->GET('application.navigation.manual_manager'),
				'onclick' => "api.application('get', 'manual')"
			]
		];

		array_push($response['render']['content'], ...[
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('application.about.license_header')
				],
				'content' => $this->_lang->GET('application.about.license')
			],
			[
				'type' => 'links',
				'description' => $this->_lang->GET('application.about.source_header'),
				'content' => [
					$this->_lang->GET('application.about.source') => ['target' => '_blank']
				]
			],
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('application.about.lines_header')
				],
				'content' => $this->_lang->GET('application.about.lines', [':code' => $lines['code'], ':documentation' => $lines['documentation'], ':configuration' => $lines['configuration']])
			],
		]);
		$this->response($response);
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
	 */
	private function cron(){
		// also see maintenance.php->cron_log()
		$logfile = 'cron.log';
		// return last entry if last touch has been today
		if (file_exists($logfile) && date('Y-m-d', filemtime($logfile)) === $this->_date['servertime']->format('Y-m-d')) {
			$cron = file($logfile);
			return ['last' => $cron[count($cron) - 1], 'count' => count($cron) - 1];
		}

		try {
			//throw new ErrorException("test error");
			$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
			$today = new \DateTime('now');
			$today->setTime(0, 0);
	
			// clear up folders with limited files lifespan
			UTILITY::tidydir('tmp', CONFIG['lifespan']['files']['tmp']);
			UTILITY::tidydir('sharepoint', CONFIG['lifespan']['files']['sharepoint']);

			// clear up calendar entries marked as closed and for autodeletion
			$calendar->delete(null);

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

			// schedule vendor certificate request
			$vendors = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
			foreach ($vendors as $vendor){
				if ($vendor['hidden']) continue;
				$certificate = json_decode($vendor['certificate'] ? : '', true);
				if (isset($certificate['validity']) && $certificate['validity']) $validity = new \DateTime($certificate['validity']);
				else continue;
				if ($validity > $today) continue;
				// check for open reminders. if none add a new. dependent on language setting, may set multiple on system language change.
				$reminders = $calendar->search($this->_lang->GET('calendar.schedule.alert_vendor_certificate_expired', [':vendor' => $vendor['name']], true));
				$open = false;
				foreach ($reminders as $reminder){
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
						':alert' => 1,
						':autodelete' => 1
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
					foreach ($docfiles as $path){
						$file = pathinfo($path);
						$article_no = explode('_', $file['filename'])[2];
						$date = date('Y-m-d', filemtime($path));
						if (!isset($documents[$vendor['id']][$article_no]) || $documents[$vendor['id']][$article_no] < $date) $documents[$vendor['id']][$article_no] = $date;
					}
				}
			}

			$products = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products');
			$alerts = [];
			foreach ($products as $product){
				if ($product['hidden']) continue;
				$article_no = preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', $product['article_no'] ? : '');
				if (isset($documents[$product['vendor_id']]) && isset($documents[$product['vendor_id']][$article_no])){
					$upload = new \DateTime($documents[$product['vendor_id']][$article_no]);
					$diff = intval(abs($upload->diff($this->_date['servertime'])->days / CONFIG['lifespan']['product']['documents']));
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
							':alert' => 1,
							':autodelete' => 1
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
					foreach ($reminders as $reminder){
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
							':alert' => 1,
							':autodelete' => 1
						]);		   		
					}
				}
			}

			// alert requesting unreceived orders or marking received as delivered
			$alerts = [];
			$undelivered = SQLQUERY::EXECUTE($this->_pdo, 'order_get_approved_unreceived_undelivered');
			foreach ($undelivered as $order){
				$update = false;
				$decoded_order_data = null;
				$ordered = new \DateTime($order['ordered'] ? : '');
				$receive_interval = intval(abs($ordered->diff($this->_date['servertime'])->days / CONFIG['lifespan']['order']['unreceived']));
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

				$received = new \DateTime($order['received'] ? : '');
				$delivery_interval = intval(abs($received->diff($this->_date['servertime'])->days / CONFIG['lifespan']['order']['undelivered']));
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

			// alert unclosed records, records not having set lifespan
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
						// get last considered document
						$lastdocument = $documents[array_search($row['last_document'], array_column($documents, 'id'))] ? : ['name' => $this->_lang->GET('record.retype_pseudodocument_name', [], true)];
						$this->alertUserGroup(
							[
								'unit' => array_filter(explode(',', $row['units'] ? : ''), fn($u) => !in_array($u, ['common', 'admin', 'office']))
							],
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
				// alert about unassigned retention period if difference between now and last touch is divisible by reminder-interval
				// only to set permissions within affected units, e.g. office members of orthotics1
				else {
					$last = new \DateTime($row['last_touch']);
					$diff = intval(abs($last->diff($this->_date['servertime'])->days / CONFIG['lifespan']['records']['open_reminder']));
					if ($row['notified'] < $diff && !$row['lifespan']){
						$this->alertUserGroup(
							[
								'permission' => PERMISSION::permissionFor('recordscasestate', true),
								'unit' => array_filter(explode(',', $row['units'] ? : ''), fn($u) => !in_array($u, ['common', 'admin', 'office']))
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
				}
			}
			// set alert flags
			foreach ($alerts as $alert){
				SQLQUERY::EXECUTE($this->_pdo, $alert);
			}

			// schedule renewal of expired responsibilities
			$responsibilities = SQLQUERY::EXECUTE($this->_pdo, 'user_responsibility_get_all');
			foreach ($responsibilities as $row){
				if ($row['hidden']) continue;
				if (substr($row['span_end'], 0, 10) < $this->_date['servertime']->format('Y-m-d')) {
					// check for open reminders. if none add a new. dependent on language setting, may set multiple on system language change.
					$reminders = $calendar->search($this->_lang->GET('calendar.schedule.alert_responsibility_expired', [':task' => $row['responsibility'], ':units' => implode(',', array_map(fn($u) => $this->_lang->_DEFAULT['units'][$u], explode(',', $row['units'] ? : '')))], true));
					$open = false;
					foreach ($reminders as $reminder){
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
							':alert' => 1,
							':autodelete' => 1
						]);		   
					}
				}
			}

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
							':module' => $this->_lang->GET('tool.navigation.regulatory', [], true),
							':date' => $this->convertFromServerTime($training['date'], true)
						], true);
						$reminders = $calendar->search($subject);
						$open = false;
						foreach ($reminders as $reminder){
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
								':alert' => 1,
								':autodelete' => 1
							]);		   		
						}
					}
				}
			}

			// schedule retrainings
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

			$log = $this->_date['servertime']->format('Y-m-d H:i:s') . ' OK';
		}
		catch (\Exception $e){
			$log = $this->_date['servertime']->format('Y-m-d H:i:s') . ' ' . str_replace("\n", ' ' , $e);
		}

		file_put_contents($logfile, "\n" . $log, FILE_APPEND);
		$cron = file($logfile);
		return ['last' => $cron[count($cron) - 1], 'count' => count($cron) - 1];
	}
	public function cron_log(){
		$logfile = 'cron.log';
		if (!array_intersect(['admin'], $_SESSION['user']['permissions']) || $_SERVER['REQUEST_METHOD'] !== 'DELETE') $this->response([], 403);
		if (unlink($logfile)) $this->response([], 410);
		$this->response([], 404);
	}

	/**
	 *   _
	 *  | |___ ___ ___ _ _ ___ ___ ___
	 *  | | .'|   | . | | | .'| . | -_|
	 *  |_|__,|_|_|_  |___|__,|_  |___|
	 *            |___|       |___|
	 * respond with $this->_lang->_USER as transfer to js frontend
	 */
    public function language(){
		$this->response(['data' => $this->_lang->GETALL()]);
	}

	/**
	 *                         _
	 *   _____ ___ ___ _ _ ___| |
	 *  |     | .'|   | | | .'| |
	 *  |_|_|_|__,|_|_|___|__,|_|
	 *
	 * manual edting
	 * POST, PUT and DELETE manual entries or
	 * respond with form to add or edit manual entries 
	 */
	public function manual(){
		if (!PERMISSION::permissionFor('appmanual')) $this->response([], 401);
		$response = ['render' => ['content' => []]];
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				// prepare entry
				$permissions = [];
				$entry = [
					'title' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.title')),
					'content' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.content')),
					'permissions' => '',
				];
				
				// check forbidden names
				if (UTILITY::forbiddenName($entry['title'])) $this->response(['response' => ['msg' => $this->_lang->GET('application.manual.forbidden_name', [':name' => $entry['title']]), 'type' => 'error']]);
		
				// chain checked permission levels
				foreach ($this->_lang->_USER['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, $description)) {
						$permissions[] = $level;
					}
				}
				$entry['permissions'] = implode(',', $permissions);

				// post manual entry
				$query = SQLQUERY::EXECUTE($this->_pdo, 'application_post_manual', [
					'values' => [
						':title' => $entry['title'],
						':content' => $entry['content'],
						':permissions' => $entry['permissions']
					]
				]);
		
				if ($query) $this->response([
					'response' => [
						'id' => $this->_pdo->lastInsertId(),
						'msg' => $this->_lang->GET('application.manual.saved', [':name' => $entry['title']]),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => false,
						'name' => $this->_lang->GET('application.manual.not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				// prepare entry
				$permissions = [];
				$entry = [
					'title' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.title')),
					'content' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.content')),
					'permissions' => '',
				];
		
				// check forbidden names
				if (UTILITY::forbiddenName($entry['title'])) $this->response(['response' => ['msg' => $this->_lang->GET('application.manual.forbidden_name', [':name' => $entry['title']]), 'type' => 'error']]);
		
				// chain checked permission levels
				foreach ($this->_lang->_USER['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, str_replace(' ', '_', $description))) {
						$permissions[] = $level;
					}
				}
				$entry['permissions'] = implode(',', $permissions);

				// update manual entry
				$query = SQLQUERY::EXECUTE($this->_pdo, 'application_put_manual', [
					'values' => [
						':title' => $entry['title'],
						':content' => $entry['content'],
						':permissions' => $entry['permissions'],
						':id' => $this->_requestedManual
					]
				]);
				if ($query) $this->response([
					'response' => [
						'id' => $this->_requestedManual,
						'msg' => $this->_lang->GET('application.manual.saved', [':name' => $entry['title']]),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => false,
						'name' => $this->_lang->GET('application.manual.not_saved'),
						'type' => 'error'
					]]);

				break;
			case 'GET':
				$query = SQLQUERY::EXECUTE($this->_pdo, 'application_get_manual_by_id', [
					'values' => [
						':id' => $this->_requestedManual != 'false' ? $this->_requestedManual : null
					]
				]);

				// prepare entry properties
				if (!$query) $entry = [
					'id' => null,
					'title' => '',
					'content' => '',
					'permissions' => ''
				];
				else $entry = $query[0];

				// append form
				$response['render']['form'] = [
					'data-usecase' => 'manual',
					'action' => "javascript:api.application('" . ($entry['id'] ? 'put' : 'post') . "', 'manual'" . ($entry['id'] ? ", " . $entry['id'] : '') . ")"];

				// prepare all entries selection
				$query = SQLQUERY::EXECUTE($this->_pdo, 'application_get_manual');
				$options = ['...' . $this->_lang->GET('application.manual.new_topic') => (!$this->_requestedManual || $this->_requestedManual === '...' . $this->_lang->GET('application.manual.new_topic')) ? ['selected' => true] : []];
				foreach ($query as $row){
					$options[$row['title']] = ['value' => $row['id']];
					if ($entry['id'] === $row['id']) $options[$row['title']]['selected'] = true; 
				}
				ksort($options);

				// set up available permissions, set checked from selected entry
				$permissions = [];
				$entry['permissions'] = explode(',', $entry['permissions']);
				foreach ($this->_lang->_USER['permissions'] as $level => $description){
					$permissions[$description] = in_array($level, $entry['permissions']) ? ['checked' => true] : [];
				}

				// append entry form
				$response['render']['content'] = [
					[
						[
							'type' => 'select',
							'attributes' => [
								'name' => $this->_lang->GET('application.manual.select_topic'),
								'onchange' => "api.application('get', 'manual', this.value)"
							],
							'content' => $options
						],
						[
							'type' => 'text',
							'attributes' => [
								'name' => $this->_lang->GET('application.manual.title'),
								'value' => $entry['title'],
							]
						],
						[
							'type' => 'textarea',
							'attributes' => [
								'rows' => 8,
								'name' => $this->_lang->GET('application.manual.content'),
								'value' => $entry['content']
							]
						],
						[
							'type' => 'checkbox',
							'attributes' => [
								'name' => $this->_lang->GET('application.manual.permissions')
							],
							'content' => $permissions
						]
					]
				];

				// append delete button if applicable
				if ($entry['id']) $response['render']['content'][] = [
						[
							'type' => 'deletebutton',
							'attributes' => [
								'value' => $this->_lang->GET('application.manual.delete'),
								'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('application.manual.delete_confirm') ."', options: {".
									"'".$this->_lang->GET('general.cancel_button')."': false,".
									"'".$this->_lang->GET('general.ok_button')."': {value: true, class: 'reducedCTA'}".
								"}}).then(confirmation => {if (confirmation) api.application('delete', 'manual', " . $entry['id'] . ")})"
							]
						]
				];
	
				break;
			case 'DELETE':
				$query = SQLQUERY::EXECUTE($this->_pdo, 'application_delete_manual', [
					'values' => [
						':id' => $this->_requestedManual
					]
				]);
				if ($query) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('application.manual.deleted'),
						'id' => false,
						'type' => 'deleted'
					]]);
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('application.manual.error'),
						'id' => $this->_requestedManual,
						'type' => 'error'
					]]);
				break;
		}
		$this->response($response);
	}

	/**
	 *
	 *   _____ ___ ___ _ _
	 *  |     | -_|   | | |
	 *  |_|_|_|___|_|_|___|
	 *
	 * respond with menu taking user permissions into account to js assemble_helper.userMenu()
	 */
	public function menu(){
		// get permission based menu items
		if (!isset($_SESSION['user'])) $this->response(['render' => [$this->_lang->GET('application.navigation.header') => [
			$this->_lang->GET('application.navigation.signin') => ['onclick' => "api.application('get', 'start')"],
			$this->_lang->GET('application.navigation.about') => ['onclick' => "api.application('get', 'about')"]
			]]]);	// early exit

		//////////////////////////////////////
		// default functions for patient users
		//////////////////////////////////////
		if (array_intersect(['patient'], $_SESSION['user']['permissions'])){
			$this->response(['render' => [
				$this->_lang->GET('application.navigation.header') => [
					$this->_lang->GET('application.navigation.signout_user', [':name' => $_SESSION['user']['name']]) => ['onclick' => "api.application('delete', 'authentify')"],
					$this->_lang->GET('application.navigation.start') => ['onclick' => "api.application('get', 'start')"],			
					$this->_lang->GET('application.navigation.user_profile') => ['onclick' => "api.user('get', 'profile')"],			
				]
			]]);
		}

		///////////////////////////////////////////
		// default functions for every regular user
		///////////////////////////////////////////
		$menu = [
			// order here defines frontend order
			$this->_lang->GET('message.navigation.header') => [
				$this->_lang->GET('message.navigation.conversations') => ['onclick' => "api.message('get', 'conversation')"],
				$this->_lang->GET('message.navigation.announcements') => ['onclick' => "api.message('get', 'announcements')"],
				$this->_lang->GET('texttemplate.navigation.texts') => ['onclick' => "api.texttemplate('get', 'text')"],
				$this->_lang->GET('message.navigation.register') => ['onclick' => "api.message('get', 'register')"],
				$this->_lang->GET('responsibility.navigation.responsibility') => ['onclick' => "api.responsibility('get', 'responsibilities')"],
				$this->_lang->GET('measure.navigation.measure') => ['onclick' => "api.measure('get', 'measure')"],
			],
			$this->_lang->GET('record.navigation.header') => [
				$this->_lang->GET('record.navigation.create_identifier') => ['onclick' => "api.record('get', 'identifier')"],
				$this->_lang->GET('record.navigation.summaries') => ['onclick' => "api.record('get', 'records')"],
				$this->_lang->GET('assemble.navigation.documents') => ['onclick' => "api.document('get', 'documents')"]
			],
			$this->_lang->GET('calendar.navigation.header') => [
				$this->_lang->GET('calendar.navigation.appointment') => ['onclick' => "api.calendar('get', 'appointment')"],
				$this->_lang->GET('calendar.navigation.scheduling') => ['onclick' => "api.calendar('get', 'schedule')"],
				$this->_lang->GET('calendar.navigation.longtermplanning') => ['onclick' => "api.calendar('get', 'longtermplanning')"]
			],
			$this->_lang->GET('application.navigation.header') => [
				$this->_lang->GET('application.navigation.signout_user', [':name' => $_SESSION['user']['name']]) => ['onclick' => "api.application('delete', 'authentify')"],
				$this->_lang->GET('application.navigation.start') => ['onclick' => "api.application('get', 'start')"],			
				$this->_lang->GET('application.navigation.user_profile') => ['onclick' => "api.user('get', 'profile')"],			
			],
			$this->_lang->GET('file.navigation.header') => [
				$this->_lang->GET('file.navigation.sharepoint') => ['onclick' => "api.file('get', 'sharepoint')"],
				$this->_lang->GET('file.navigation.files') => ['onclick' => "api.file('get', 'files')"],
				$this->_lang->GET('file.navigation.bundles') => ['onclick' => "api.file('get', 'bundle')"],
			],
			$this->_lang->GET('consumables.navigation.header') => [
				$this->_lang->GET('order.navigation.order') => ['onclick' => "api.purchase('get', 'order')"],
				$this->_lang->GET('order.navigation.prepared_orders') => ['onclick' => "api.purchase('get', 'prepared')"],
				$this->_lang->GET('order.navigation.approved_orders') => ['onclick' => "api.purchase('get', 'approved')"],
				$this->_lang->GET('consumables.navigation.vendor') => ['onclick' => "api.purchase('get', 'vendor')"],
				$this->_lang->GET('consumables.navigation.product') => ['onclick' => "api.purchase('get', 'product')"],
			],
			$this->_lang->GET('tool.navigation.header') => [
				$this->_lang->GET('tool.navigation.digital_codes') => ['onclick' => "api.tool('get', 'code')"],
				$this->_lang->GET('tool.navigation.scanner') => ['onclick' => "api.tool('get', 'scanner')"],
				$this->_lang->GET('tool.navigation.calculator') => ['onclick' => "api.tool('get', 'calculator')"],
				$this->_lang->GET('tool.navigation.image') => ['onclick' => "api.tool('get', 'image')"],
				$this->_lang->GET('tool.navigation.zip') => ['onclick' => "api.tool('get', 'zip')"],
			],
		];

		//////////////////////////////////
		// permission based functions
		//////////////////////////////////

		// records
		if (!array_intersect(['group'], $_SESSION['user']['permissions'])) $menu[$this->_lang->GET('record.navigation.header')][$this->_lang->GET('assemble.navigation.bundles')] = ['onclick' => "api.document('get', 'bundles')"];
		// make sure risk management comes after documents so this is an order exception without special permission
		$menu[$this->_lang->GET('record.navigation.header')][$this->_lang->GET('risk.navigation.risk_management')] = ['onclick' => "api.risk('get', 'risk')"];
		if (PERMISSION::permissionFor('audit')){
			$menu[$this->_lang->GET('record.navigation.header')][$this->_lang->GET('audit.navigation.audit')] = ['onclick' => "api.audit('get', 'audit')"];
			$menu[$this->_lang->GET('record.navigation.header')][$this->_lang->GET('audit.navigation.management_review')] = ['onclick' => "api.audit('get', 'managementreview')"];
		}
		if (PERMISSION::permissionFor('documentapproval'))$menu[$this->_lang->GET('record.navigation.header')][$this->_lang->GET('assemble.navigation.manage_approval')] = ['onclick' => "api.document('get', 'approval')"];

		// calendar
		if (!array_intersect(['group'], $_SESSION['user']['permissions']) && isset($_SESSION['user']['app_settings']['weeklyhours']))
			$menu[$this->_lang->GET('calendar.navigation.header')][$this->_lang->GET('calendar.navigation.timesheet')] = ['onclick' => "api.calendar('get', 'timesheet')"];

		// application
		if (PERMISSION::permissionFor('users')) $menu[$this->_lang->GET('application.navigation.header')][$this->_lang->GET('application.navigation.user_manager')] =['onclick' => "api.user('get', 'user')"];
		// make sure about comes last so this is an an order exception without special permission
		$menu[$this->_lang->GET('application.navigation.header')][$this->_lang->GET('application.navigation.about')] = ['onclick' => "api.application('get', 'about')"];

		// purchase
		if (PERMISSION::permissionFor('regulatory')) $menu[$this->_lang->GET('consumables.navigation.header')][$this->_lang->GET('consumables.navigation.incorporated_pending')] =['onclick' => "api.purchase('get', 'pendingincorporations')"];

		// tools
		if (PERMISSION::permissionFor('csvfilter')) $menu[$this->_lang->GET('tool.navigation.header')][$this->_lang->GET('csvfilter.navigation.filter')] =['onclick' => "api.csvfilter('get', 'filter')"];
		if (PERMISSION::permissionFor('regulatory')) $menu[$this->_lang->GET('tool.navigation.header')][$this->_lang->GET('tool.navigation.regulatory')] =['onclick' => "api.audit('get', 'checks')"];
		if (PERMISSION::permissionFor('maintenance')) $menu[$this->_lang->GET('tool.navigation.header')][$this->_lang->GET('maintenance.navigation.maintenance')] =['onclick' => "api.maintenance('get', 'task')"];

		$this->response(['render' => $menu]);
	}

	/**
	 *       _           _
	 *   ___| |_ ___ ___| |_
	 *  |_ -|  _| .'|  _|  _|
	 *  |___|_| |__,|_| |_|
	 *
	 * respond with landing page
	 */
	public function start(){
		if (!isset($_SESSION['user'])) $this->response([], 401);
		// cron job
		$cron = $this->cron();
		$response = array_merge(['render' => ['content' => []]], $this->_auth);

		// aria timeout information
		$response['render']['content'][] = [
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('application.timeout_aria', [':minutes' => round((isset($_SESSION['user']['app_settings']['idle']) ? $_SESSION['user']['app_settings']['idle'] : min(CONFIG['lifespan']['session']['idle'], ini_get('session.gc_maxlifetime'))) / 60)])
				]
			]
		];

		//////////////////////////////////////
		// default functions for patient users
		//////////////////////////////////////
		if (array_intersect(['patient'], $_SESSION['user']['permissions'])){
			// also see document.php documents()
			$documentdatalist = $displayeddocuments = [];
			// get all documents or these fitting the search
			require_once('_shared.php');
			$search = new SHARED($this->_pdo, $this->_date);
			$documents = $search->documentsearch(['search' => null]);
			// prepare existing documents lists grouped by context
			foreach ($documents as $row) {
				if (!$row['patient_access'] || in_array($row['context'], array_keys($this->_lang->_USER['documentcontext']['notdisplayedinrecords']))) continue;
				if (!in_array($row['name'], $documentdatalist)) {
					$documentdatalist[] = $row['name'];
					// filter by unit
					if (!in_array($row['unit'], ['common', ...$_SESSION['user']['units']])) continue;

					// add to result
					$displayeddocuments[$row['context']][$row['name']] = ['href' => "javascript:api.record('get', 'document', '" . $row['name'] . "')"];
				}
			}
			// sort by context for easier comprehension
			foreach ($displayeddocuments as $context => $list){
				$contexttranslation = '';
				foreach ($this->_lang->_USER['documentcontext'] as $contexts){
					if (isset($contexts[$context])){
						$contexttranslation = $contexts[$context];
						break;
					}
				}
				$response['render']['content'][] = [
					'type' => 'links',
					'description' => $contexttranslation,
					'content' => $list
				];
			}
		}

		///////////////////////////////////////////
		// default functions for every regular user
		///////////////////////////////////////////
		else {
			// storage warning
			$storage = round(disk_free_space("/") / pow(1024, 3), 3);
			if ($storage < CONFIG['limits']['storage_warning'] && PERMISSION::permissionFor('audit')){ // closest permission for matching responsibility with the whole quality management system
				$response['render']['content'][count($response['render']['content']) - 1][] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('application.storage_warning', [':space' => $storage . ' GB']),
						'class' => 'red'
					]
				];
			}

			// cron job
			if (array_intersect(['admin'], $_SESSION['user']['permissions'])){
				$response['render']['content'][count($response['render']['content']) - 1][] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => 'CRON'
					],
					'content' => $cron['last']
				];
			}
			
			// display current announcements
			$recentannouncements = [];
			$announcements = SQLQUERY::EXECUTE($this->_pdo, 'announcement_get_recent');
			foreach($announcements as $announcement){
				$announcement['organizational_unit'] = array_filter(explode(',', $announcement['organizational_unit'] ? : ''), fn($u) => boolval($u));
				if ($announcement['organizational_unit'] && !array_intersect($announcement['organizational_unit'], $_SESSION['user']['units'])) continue;

				$announcementcontent = [];
				if ($announcement['text']) $announcementcontent[] = $announcement['text'];
				if ($announcement['span_start']) $announcementcontent[] = $this->_lang->GET('message.announcement.start') . ' ' . $this->convertFromServerTime(substr($announcement['span_start'], 0 ,10));
				if ($announcement['span_end']) $announcementcontent[] = $this->_lang->GET('message.announcement.end') . ' ' . $this->convertFromServerTime(substr($announcement['span_end'], 0 ,10));
				$announcementcontent[] = $this->_lang->GET('message.announcement.last_edit', [':author' => $announcement['author_name'], ':date'=> $this->convertFromServerTime($announcement['date'])]);

				$recentannouncements[] = [
					[
						'type' => 'announcementsection',
						'attributes' => [
							'name' => $announcement['subject']
						],
						'content' => implode("\n", $announcementcontent), 
					]
				];
			}
			if ($recentannouncements) $response['render']['content'][] = count($recentannouncements) > 1 ? $recentannouncements : $recentannouncements[0];


			// set up dashboard notifications
			$tiles = [];
			$notifications = new NOTIFICATION;

			// messages
			$unseen = $notifications->messageunseen();
			if ($unseen) {
				$tiles[] = [
					'type' => 'tile',
					'attributes' => [
						'onclick' => "api.message('get', 'conversation')",
						'onkeydown' => "if (event.key==='Enter') api.message('get', 'conversation')",
						'role' => 'link',
						'tabindex' => '0'
					],
					'content' => [
						[
							'type' => 'textsection',
							'content' => $this->_lang->GET('application.dashboard.messages', [':number' => $unseen]),
							'attributes' => [
								'data-type' => 'message',
								'name' => $this->_lang->GET('message.navigation.conversations')
							]
						]
					]
				];
			}

			// unprocessed orders
			if (PERMISSION::permissionFor('orderprocessing')){
				$unprocessed = $notifications->order();
				if ($unprocessed) {
					$tiles[] = [
						'type' => 'tile',
						'attributes' => [
							'onclick' => "api.purchase('get', 'approved')",
							'onkeydown' => "if (event.key==='Enter') api.purchase('get', 'approved')",
							'role' => 'link',
							'tabindex' => '0'
							],
						'content' => [
							[
								'type' => 'textsection',
								'content' => $this->_lang->GET('application.dashboard.orders', [':number' => $unprocessed]),
								'attributes' => [
									'data-type' => 'purchase',
									'name' => $this->_lang->GET('order.navigation.approved_orders')
								]
							]
						]
					];
				}
			}

			// prepared orders
			if ($_SESSION['user']['orderauth']){
				$prepared = $notifications->preparedorders();
				if ($prepared) {
					$tiles[] = [
						'type' => 'tile',
						'attributes' => [
							'onclick' => "api.purchase('get', 'prepared')",
							'onkeydown' => "if (event.key==='Enter') api.purchase('get', 'prepared')",
							'role' => 'link',
							'tabindex' => '0'
							],
						'content' => [
							[
								'type' => 'textsection',
								'content' => $this->_lang->GET('application.dashboard.preparedorders', [':number' => $prepared]),
								'attributes' => [
									'data-type' => 'purchase',
									'name' => $this->_lang->GET('order.navigation.prepared_orders')
								]
							]
						]
					];
				}
			}

			// unclosed case documentation for own unit
			$number = $notifications->records();
			if ($number){
				$tiles[] = [
					'type' => 'tile',
					'attributes' => [
						'onclick' => "api.record('get', 'records')",
						'onkeydown' => "if (event.key==='Enter') api.record('get', 'records')",
						'role' => 'link',
						'tabindex' => '0'
					],
					'content' => [
						[
							'type' => 'textsection',
							'content' => $this->_lang->GET('application.dashboard.cases', [':number' => $number]),
							'attributes' => [
								'data-type' => 'record',
								'name' => $this->_lang->GET('record.navigation.header')
							]
						]
					]
				];
			}

			// unclosed audits
			if (PERMISSION::permissionFor('audit')){
				$number = $notifications->audits();
				if ($number){
					$tiles[] = [
						'type' => 'tile',
						'attributes' => [
							'onclick' => "api.audit('get', 'audit')",
							'onkeydown' => "if (event.key==='Enter') api.audit('get', 'audit')",
							'role' => 'link',
							'tabindex' => '0'
							],
						'content' => [
							[
								'type' => 'textsection',
								'content' => $this->_lang->GET('application.dashboard.audits', [':number' => $number]),
								'attributes' => [
									'data-type' => 'record',
									'name' => $this->_lang->GET('record.navigation.header')
								]
							]
						]
					];
				}
			}

			// unapproved documents and components
			$unapproved = $notifications->documents();
			if ($unapproved){
				$tiles[] = [
					'type' => 'tile',
					'attributes' => [
						'onclick' => "api.document('get', 'approval')",
						'onkeydown' => "if (event.key==='Enter') api.document('get', 'approval')",
						'role' => 'link',
						'tabindex' => '0'
					],
					'content' => [
						[
							'type' => 'textsection',
							'content' => $this->_lang->GET('application.dashboard.unapproveddocuments', [':number' => $unapproved]),
							'attributes' => [
								'data-type' => 'record',
								'name' => $this->_lang->GET('assemble.navigation.manage_approval')
							]
						]
					]
				];
			}

			// pending product incorporations
			$unapproved = $notifications->consumables();
			if ($unapproved){
				$tiles[] = [
					'type' => 'tile',
					'attributes' => [
						'onclick' => "api.purchase('get', 'pendingincorporations')",
						'onkeydown' => "if (event.key==='Enter') api.purchase('get', 'pendingincorporations')",
						'role' => 'link',
						'tabindex' => '0'
					],
					'content' => [
						[
							'type' => 'textsection',
							'content' => $this->_lang->GET('application.dashboard.pendingincorporations', [':number' => $unapproved]),
							'attributes' => [
								'data-type' => 'purchase',
								'name' => $this->_lang->GET('consumables.navigation.incorporated_pending')
							]
						]
					]
				];
			}
			
			// open complaints
			$complaints = $notifications->complaints();
			if ($complaints){
				$tiles[] = [
					'type' => 'tile',
					'attributes' => [
						'onclick' => "api.audit('get', 'checks', 'complaints')",
						'onkeydown' => "if (event.key==='Enter') api.audit('get', 'checks', 'complaints')",
						'role' => 'link',
						'tabindex' => '0'
					],
					'content' => [
						[
							'type' => 'textsection',
							'content' => $this->_lang->GET('application.dashboard.complaints', [':number' => $complaints]),
							'attributes' => [
								'data-type' => 'record',
								'name' => $this->_lang->GET('record.navigation.header')
							]
						]
					]
				];
			}

			// scheduled trainings
			$complaints = $notifications->scheduledtrainings();
			if ($complaints){
				$tiles[] = [
					'type' => 'tile',
					'content' => [
						[
							'type' => 'textsection',
							'content' => $this->_lang->GET('application.dashboard.scheduledtrainings', [':number' => $complaints]),
							'attributes' => [
								'data-type' => 'skill',
								'name' => $this->_lang->GET('audit.checks_type.userskills')
							]
						]
					]
				];
				if (PERMISSION::permissionFor('regulatory')){
					$tiles[count($tiles) - 1]['attributes'] = [
						'onclick' => "api.audit('get', 'checks', 'userskills')",
						'onkeydown' => "if (event.key==='Enter') api.audit('get', 'checks', 'userskills')",
						'role' => 'link',
						'tabindex' => '0'
					];
				}
			}
			if (count($tiles)) $response['render']['content'][] = $tiles;

			// append search function to landing page
			$searchelements = [
				[
					'type' => 'search',
					'attributes' => [
						'name' => $this->_lang->GET('application.search'),
						'value' => $this->_search,
						'onkeydown' => "if (event.key === 'Enter') {api.application('get', 'start', encodeURIComponent(this.value)); return false;}",
					]
				]
			];

			if ($this->_search) {
				require_once('_shared.php');
				$search = new SHARED($this->_pdo, $this->_date);

				// search records, style like record overview
				if ($records = $search->recordsearch(['search' => $this->_search])){
					$matches = [];
					foreach ($records as $contextkey => $context){
						foreach ($context as $record){
							$display = $this->_lang->GET('record.list_touched', [
								':identifier' => $record['identifier'],
								':date' => $this->convertFromServerTime($record['last_touch']),
								':document' => $record['last_document']
							]);
							$matches[$display] = [
									'href' => "javascript:api.record('get', 'record', '" . $record['identifier'] . "')"
								];
							foreach ($record['case_state'] as $case => $state){
								$matches[$display]['data-' . $case] = $state;
							}
							if ($record['complaint']) $matches[$display]['class'] = 'orange';
							if ($record['closed'])  $matches[$display]['class'] = 'green';
						}
						$searchelements[] = [
							'type' => 'links',
							'description' => $this->_lang->GET('documentcontext.' . $contextkey),
							'content' => $matches
						];
					}
				}

				// search documents
				if ($documents = $search->documentsearch(['search' => $this->_search])){
					$matches = [];
					foreach ($documents as $document){
						$matches[$document['name']] = ['href' => 'javascript:void(0);', 'onclick' => "api.record('get', 'document', '" . $document['name'] . "')"];
					}
					$searchelements[] = [
						'type' => 'links',
						'description' => $this->_lang->GET('assemble.navigation.documents'),
						'content' => $matches
					];
				}

				// search files
				if ($files = $search->filesearch(['search' => $this->_search])){
					$matches = [];
					foreach ($files as $file){
						$matches[preg_replace('/.+fileserver\//', '', $file)] = UTILITY::link(['href' => $file]);
					}
					$searchelements[] = [
						'type' => 'links',
						'description' => $this->_lang->GET('file.navigation.header'),
						'content' => $matches
					];
				}

				// inform about lack of results if applicable
				if (count($searchelements) < 2) $searchelements[] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('application.search_empty'),
						'class' => 'orange'
					]
				];
			}
			$response['render']['content'][] = $searchelements;

			// calendar scheduled events
			$overview = [];
			$calendar = new CALENDARUTILITY($this->_pdo, $this->_date);
			$week = $calendar->render('week', 'schedule');

			// add overview to calendar view
			$overview[] = [
				'type' => 'calendar',
				'description' => $week['header'],
				'content' => $week['content'],
				'api' => 'schedule'
			];

			$displayevents = $displayabsentmates = '';
			$today = new \DateTime('now');
			$thisDaysEvents = $calendar->getDay($today->format('Y-m-d'));
			// sort events
			foreach ($thisDaysEvents as $row){
				if (!$row['affected_user']) $row['affected_user'] = $this->_lang->GET('general.deleted_user');
				if ($row['type'] === 'schedule' && (array_intersect(explode(',', $row['organizational_unit']), ['common', ...$_SESSION['user']['units']]) || 
					array_intersect(explode(',', $row['affected_user_units'] ? : ''), $_SESSION['user']['units'])) && !$row['closed']) $displayevents .= "* " . $row['subject'] . ($row['affected_user'] !== $this->_lang->GET('general.deleted_user') ? ' (' . $row['affected_user'] . ')': '') . "\n";
				if ($row['type'] === 'timesheet' && !in_array($row['subject'], CONFIG['calendar']['hide_offduty_reasons']) && array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])) $displayabsentmates .= "* " . $row['affected_user'] . " ". $this->_lang->_USER['calendar']['timesheet']['pto'][$row['subject']] . " ". $this->convertFromServerTime(substr($row['span_start'], 0, 10)) . " - ". $this->convertFromServerTime(substr($row['span_end'], 0, 10)) . "\n";
			}
			// display todays events
			if ($displayevents) $overview[] = [
				'type' => 'textsection',
				'attributes' => [
						'name' => $this->_lang->GET('calendar.schedule.events_assigned_units')
				],
				'content' => $displayevents
			];
			// display todays absent workmates (sick, vacation, etc.)
			if ($displayabsentmates) $overview[] = [
				'type' => 'textsection',
				'attributes' => [
						'name' => $this->_lang->GET('calendar.timesheet.irregular')
				],
				'content' => $displayabsentmates
			];

			// add past unclosed events
			$today->modify('-1 day');
			$pastEvents = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
			$uncompleted = [];
			foreach ($pastEvents as $row){
				if (!in_array($row, $thisDaysEvents) && $row['type'] === 'schedule' && array_intersect(explode(',', $row['organizational_unit']), ['common', ...$_SESSION['user']['units']]) && !$row['closed']) $uncompleted[$row['subject'] . " (" . $this->convertFromServerTime(substr($row['span_start'], 0, 10)) . ")"] = ['href' => "javascript:api.calendar('get', 'schedule', '" . substr($row['span_start'], 0, 10) . "', '" . substr($row['span_start'], 0, 10) . "')"];
			}
			if ($uncompleted) $overview[] = [
				'type' => 'links',
				'description' => $this->_lang->GET('calendar.schedule.events_assigned_units_uncompleted'),
				'content' => $uncompleted
			];

			if ($overview) $response['render']['content'][] = $overview;
		}
		$this->response($response);
	}
}
?>
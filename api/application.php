<?php
/**
 * SPDX-FileNotice: Part of erroronline1/caro a quality management software.
 * SPDX-FileCopyrightText: © 2023 error on line 1 <dev@erroronline.one>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

namespace CARO\API;

// authentify endpoint handling, menu and landing page methods
require_once('./_calendarutility.php');
require_once('./notification.php');

class APPLICATION extends API {
    // processed parameters for readability
    public ?string $_requestedMethod = REQUEST[1];
    private mixed $_requestedManual = null;
    private mixed $_search = null;

	/**
	 * init parent class and set private requests
	 */
	public function __construct($_class_vars  = []){
		parent::__construct($_class_vars);
		$this->_requestedManual = $this->_search = REQUEST[2] ?? null;
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
					'redirect' => ['start']
				]);
				// no break by intent
			case 'POST':
				$this->response($this->_auth);
				break;
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
		if ($this->_requestedManual){
			if (!in_array($this->_requestedManual, ['readme.md', 'readme.de.md'])) $this->response([], 404);
			$content = file_get_contents('../' . $this->_requestedManual);
			/*
			// this works in theory but page breaking is not an easy tasg with long images and within table cells, so this option is postponed
			$summary = [
				'filename' => preg_replace(['/' . CONFIG['forbidden']['names']['characters'] . '/', '/' . CONFIG['forbidden']['filename']['characters'] . '/'], '', str_replace('.', '_', $this->_requestedManual) . '_' . $this->_date['usertime']->format('Y-m-d H:i')),
				'identifier' => null,
				'content' => [$content],
				'files' => [],
				'images' => [],
				'title' => $this->_requestedManual,
				'date' => $this->_date['usertime']->format('Y-m-d H:i')
			];

			$downloadfiles = [];
			require_once('./_tcpdfinterface.php');
			$settings = CONFIG['pdf']['record'];
			$settings['fontsize'] = 8;
			$PDF = new PDF($settings, $this->_sqlinterface);
			$file = $PDF->auditPDF($summary);
			$downloadfiles[$this->_requestedManual] = [
				'href' => $this->_filehandler->getFileLink($file),
				'download' => pathinfo($file)['basename']
			];
			$this->response([
				'dialog' => [
					'render' => [
						[
							'type' => 'links',
							'content' => $downloadfiles
						]					
					]
				]
			]);
			*/
			$this->response([
				'dialog' => [
					'render' => [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_requestedManual
							],
							'mdcontent' => $content
						]					
					]
				]
			]);
		}

		$lines = ['frontend' => 0, 'backend' => 0, 'code' => 0, 'documentation' => 0, 'configuration' => 0];
		foreach (['../', '../js', '../api', '../vendor/erroronline1/markdown/src'] as $dir){
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
				if (!isset(pathinfo($file)['extension']) || !in_array(pathinfo($file)['extension'], ['php','ini','js','html','css','md','json', 'csv'])) continue;
				if (in_array(pathinfo($file)['extension'], ['md'])){
					$lines['documentation']+= count(file($dir . '/' . $file));			
				}
				else {
					$lines['configuration']+= count(file($dir . '/' . $file));
				}
			}
		}

		$response = [
			'title' => $this->_lang->GET('application.navigation.about'),
			'render' => [
				'content' => []
			]
		];

		if (isset($_SESSION['user'])){
			// add manual filtered by applicable permission
			$query = $this->_sqlinterface->EXECUTE('application_get_manual');
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
				'onclick' => "api.application('get', null, 'manual')"
			]
		];

		if (isset($_SESSION['user']) && !array_intersect(['patient'], $_SESSION['user']['permissions']) &&
			ERPINTERFACE && ERPINTERFACE->_readme){
			$erpmd = file_get_contents(ERPINTERFACE->_readme);
			$response['render']['content'][] = [
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('erpquery.integrations.about'),
					'onclick' => "new _client.Dialog({type: 'input', header: '". $this->_lang->GET('erpquery.integrations.about') . "', render: JSON.parse('" . UTILITY::json_encode(
						[
							[
								'type' => 'textsection',
								'mdcontent' => addslashes($erpmd)
							]
						]
					) . "')})"
				]
			];
		}

		array_push($response['render']['content'], ...[
			[
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('application.about.license_header')
				],
				'content' => $this->_lang->GET('_LICENSE')
			], [
				'type' => 'button',
				'attributes' => [
					'value' => 'readme.md',
					'onclick' => 'api.application("get", null, "about", "readme.md")'
				]
			], [
				'type' => 'button',
				'attributes' => [
					'value' => 'readme.de.md',
					'onclick' => 'api.application("get", null, "about", "readme.de.md")'
				]
			], [
				'type' => 'links',
				'description' => $this->_lang->GET('application.about.source_header'),
				'content' => [
					$this->_lang->GET('application.about.source') => ['target' => '_blank']
				]
			], [
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
		$response = [
			'title' => $this->_lang->GET('application.navigation.manual_manager'),
			'render' => [
				'content' => []
			]
		];
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
			case 'PUT':
				// prepare entry
				$permissions = [];
				$entry = [
					'title' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.title')),
					'content' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('application.manual.content')),
					'permissions' => '',
				];
				
				// check forbidden names
				if (UTILITY::forbiddenName($entry['title'])) $this->response(['toast' => ['msg' => $this->_lang->GET('application.manual.forbidden_name', [':name' => $entry['title']]), 'type' => 'error']]);
		
				// chain checked permission levels
				foreach ($this->_lang->_USER['permissions'] as $level => $description){
					if (UTILITY::propertySet($this->_payload, $description)) {
						$permissions[] = $level;
					}
				}
				$entry['permissions'] = implode(',', $permissions);

				// post manual entry
				$query = $this->_sqlinterface->EXECUTE('application_post_manual', [
					':title' => $entry['title'],
					':content' => $entry['content'],
					':permissions' => $entry['permissions'],
					':id' => $this->_requestedManual
				]);
		
				if ($query) $this->response([
					'toast' => [
						'msg' => $this->_lang->GET('application.manual.saved', [':name' => $entry['title']]),
						'type' => 'success'
					],
					'redirect' => $this->_requestedManual ? null : ['manual', null, $this->_sqlinterface->_pdo->lastInsertId()]

				]);
				else $this->response([
					'toast' => [
						'msg' => $this->_lang->GET('application.manual.not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$query = $this->_sqlinterface->EXECUTE('application_get_manual_by_id', [
					':id' => $this->_requestedManual != 'false' ? $this->_requestedManual : null
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
					'action' => "javascript:api.application('" . ($entry['id'] ? 'put' : 'post') . "', '[data-usecase=manual]', 'manual'" . ($entry['id'] ? ", " . $entry['id'] : '') . ")"];

				// prepare all entries selection
				$query = $this->_sqlinterface->EXECUTE('application_get_manual');
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
								'onchange' => "api.application('get', null, 'manual', this.value)"
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
								"}}).then(confirmation => {if (confirmation) api.application('delete', null, 'manual', " . $entry['id'] . ")})"
							]
						]
				];
				break;
			case 'DELETE':
				$query = $this->_sqlinterface->EXECUTE('application_delete_manual', [
					':id' => $this->_requestedManual
				]);
				if ($query) $this->response([
					'toast' => [
						'msg' => $this->_lang->GET('application.manual.deleted'),
						'type' => 'deleted'
					]]);
				else $this->response([
					'toast' => [
						'msg' => $this->_lang->GET('application.manual.error'),
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
			$this->_lang->GET('application.navigation.signin') => ['onclick' => "api.application('get', null, 'start')"],
			$this->_lang->GET('application.navigation.about') => ['onclick' => "api.application('get', null, 'about')"]
			]]]);	// early exit

		//////////////////////////////////////
		// default functions for patient users
		//////////////////////////////////////
		if (array_intersect(['patient'], $_SESSION['user']['permissions'])){
			$this->response(['render' => [
				$this->_lang->GET('application.navigation.header') => [
					$this->_lang->GET('application.navigation.signout_user', [':name' => $_SESSION['user']['name']]) => ['onclick' => "api.application('delete', null, 'authentify')"],
					$this->_lang->GET('application.navigation.start') => ['onclick' => "api.application('get', null, 'start')"],			
					$this->_lang->GET('application.navigation.user_profile') => ['onclick' => "api.user('get', null, 'profile')"],			
				]
			]]);
		}

		///////////////////////////////////////////
		// default functions for every regular user
		///////////////////////////////////////////
		$menu = [
			// order here defines frontend order
			$this->_lang->GET('message.navigation.header') => [
				$this->_lang->GET('message.navigation.conversations') => ['onclick' => "api.message('get', null, 'conversation')"],
				$this->_lang->GET('message.navigation.announcements') => ['onclick' => "api.message('get', null, 'announcements')"],
				$this->_lang->GET('texttemplate.navigation.texts') => ['onclick' => "api.texttemplate('get', null, 'text')"],
				$this->_lang->GET('message.navigation.register') => ['onclick' => "api.message('get', null, 'register')"],
				$this->_lang->GET('responsibility.navigation.responsibility') => ['onclick' => "api.responsibility('get', null, 'responsibilities')"],
				$this->_lang->GET('measure.navigation.measure') => ['onclick' => "api.measure('get', null, 'measure')"],
				$this->_lang->GET('message.navigation.whiteboard') => ['onclick' => "api.message('get', null, 'whiteboards')"],
			],
			$this->_lang->GET('record.navigation.header') => [
				$this->_lang->GET('record.navigation.create_identifier') => ['onclick' => "api.record('get', null, 'identifier')"],
				$this->_lang->GET('record.navigation.summaries') => ['onclick' => "api.record('get', null, 'records')"],
				$this->_lang->GET('assemble.navigation.documents') => ['onclick' => "api.document('get', null, 'documents')"]
			],
			$this->_lang->GET('calendar.navigation.header') => [
				$this->_lang->GET('calendar.navigation.appointment') => ['onclick' => "api.calendar('get', null, 'appointment')"],
				$this->_lang->GET('calendar.navigation.tasks') => ['onclick' => "api.calendar('get', null, 'tasks')"],
				$this->_lang->GET('calendar.navigation.worklists') => ['onclick' => "api.calendar('get', null, 'worklists')"],
				$this->_lang->GET('calendar.navigation.longtermplanning') => ['onclick' => "api.calendar('get', null, 'longtermplanning')"]
			],
			$this->_lang->GET('application.navigation.header') => [
				$this->_lang->GET('application.navigation.signout_user', [':name' => $_SESSION['user']['name']]) => ['onclick' => "api.application('delete', null, 'authentify')"],
				$this->_lang->GET('application.navigation.start') => ['onclick' => "api.application('get', null, 'start')"],			
				$this->_lang->GET('application.navigation.user_profile') => ['onclick' => "api.user('get', null, 'profile')"],			
			],
			$this->_lang->GET('file.navigation.header') => [
				$this->_lang->GET('file.navigation.sharepoint') => ['onclick' => "api.file('get', null, 'sharepoint')"],
				$this->_lang->GET('file.navigation.files') => ['onclick' => "api.file('get', null, 'files')"],
			],
			$this->_lang->GET('consumables.navigation.header') => [
				$this->_lang->GET('order.navigation.order') => ['onclick' => "api.order('get', null, 'order')"],
				$this->_lang->GET('order.navigation.prepared_orders') => ['onclick' => "api.order('get', null, 'prepared')"],
				$this->_lang->GET('order.navigation.approved_orders') => ['onclick' => "api.order('get', null, 'approved')"],
				$this->_lang->GET('consumables.navigation.vendor') => ['onclick' => "api.consumables('get', null, 'vendor')"],
				$this->_lang->GET('consumables.navigation.product') => ['onclick' => "api.consumables('get', null, 'product')"],
			],
			$this->_lang->GET('tool.navigation.header') => [
				$this->_lang->GET('tool.navigation.digital_codes') => ['onclick' => "api.tool('get', null, 'code')"],
				$this->_lang->GET('tool.navigation.scanner') => ['onclick' => "api.tool('get', null, 'scanner')"],
				$this->_lang->GET('tool.navigation.calculator') => ['onclick' => "api.tool('get', null, 'calculator')"],
				$this->_lang->GET('tool.navigation.image') => ['onclick' => "api.tool('get', null, 'image')"],
				$this->_lang->GET('tool.navigation.zip') => ['onclick' => "api.tool('get', null, 'zip')"],
			],
		];

		//////////////////////////////////
		// permission based functions
		//////////////////////////////////

		// records
		if (!array_intersect(['group'], $_SESSION['user']['permissions'])) $menu[$this->_lang->GET('record.navigation.header')][$this->_lang->GET('assemble.navigation.bundles')] = ['onclick' => "api.document('get', null, 'bundles')"];
		// add erpinterface if applicable
		if (ERPINTERFACE && ERPINTERFACE->_instatiated) {
			$menu[$this->_lang->GET('record.navigation.header')][$this->_lang->GET('erpquery.navigation.erpquery')] = ['onclick' => "api.erpquery('get', null, 'erpquery')"];
		}
		// make sure risk management comes after documents so this is an order exception without special permission
		$menu[$this->_lang->GET('record.navigation.header')][$this->_lang->GET('risk.navigation.risk_management')] = ['onclick' => "api.risk('get', null, 'risk')"];
		if (PERMISSION::permissionFor('audit')){
			$menu[$this->_lang->GET('record.navigation.header')][$this->_lang->GET('audit.navigation.audit')] = ['onclick' => "api.audit('get', null, 'audit')"];
			$menu[$this->_lang->GET('record.navigation.header')][$this->_lang->GET('audit.navigation.management_review')] = ['onclick' => "api.audit('get', null, 'managementreview')"];
		}
		if (PERMISSION::permissionFor('documentapproval'))$menu[$this->_lang->GET('record.navigation.header')][$this->_lang->GET('assemble.navigation.manage_approval')] = ['onclick' => "api.document('get', null, 'approval')"];

		// calendar
		if (!array_intersect(['group'], $_SESSION['user']['permissions']))
			$menu[$this->_lang->GET('calendar.navigation.header')][$this->_lang->GET('calendar.navigation.timesheet')] = ['onclick' => "api.calendar('get', null, 'timesheet')"];

		// application
		if (PERMISSION::permissionFor('users')) $menu[$this->_lang->GET('application.navigation.header')][$this->_lang->GET('application.navigation.user_manager')] = ['onclick' => "api.user('get', null, 'user')"];
		// make sure about comes last so this is an an order exception without special permission
		$menu[$this->_lang->GET('application.navigation.header')][$this->_lang->GET('application.navigation.about')] = ['onclick' => "api.application('get', null, 'about')"];

		// purchase
		if (PERMISSION::permissionFor('incorporation')) $menu[$this->_lang->GET('consumables.navigation.header')][$this->_lang->GET('consumables.navigation.incorporated_pending')] = ['onclick' => "api.consumables('get', null, 'pendingincorporations')"];

		// tools
		if (PERMISSION::permissionFor('csvfilter') && ERPINTERFACE && ERPINTERFACE->_instatiated && method_exists(ERPINTERFACE, 'customcsvdump') && ERPINTERFACE->customcsvdump())
			$menu[$this->_lang->GET('tool.navigation.header')][$this->_lang->GET('erpquery.navigation.csvdump')] = ['onclick' => "api.erpquery('get', null, 'csvdump')"];
		if (PERMISSION::permissionFor('csvfilter') && ERPINTERFACE && ERPINTERFACE->_instatiated && method_exists(ERPINTERFACE, 'upload') && ERPINTERFACE->upload())
			$menu[$this->_lang->GET('tool.navigation.header')][$this->_lang->GET('erpquery.navigation.upload')] = ['onclick' => "api.erpquery('get', null, 'upload')"];

		if (PERMISSION::permissionFor('csvfilter')) $menu[$this->_lang->GET('tool.navigation.header')][$this->_lang->GET('csvfilter.navigation.filter')] = ['onclick' => "api.csvfilter('get', null, 'filter')"];
		if (PERMISSION::permissionFor('regulatory')) $menu[$this->_lang->GET('tool.navigation.header')][$this->_lang->GET('audit.navigation.regulatory')] = ['onclick' => "api.audit('get', null, 'checks')"];
		if (PERMISSION::permissionFor('maintenance')) $menu[$this->_lang->GET('tool.navigation.header')][$this->_lang->GET('maintenance.navigation.maintenance')] = ['onclick' => "api.maintenance('get', null, 'task')"];
		if (PERMISSION::permissionFor('cronoverride')) $menu[$this->_lang->GET('tool.navigation.header')][$this->_lang->GET('maintenance.navigation.cronoverride')] = ['onclick' => "api.notification('get', null, 'notifs', 'true');"];

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

		$response = [
			'title' => $this->_lang->GET("general.welcome_header", [":user" => ' ' . $_SESSION['user']['name']]),
			'config' => $this->_auth,
			'render' => [
				'content' => [
					// aria timeout information
					[
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('application.timeout_aria', [':minutes' => round(($_SESSION['user']['app_settings']['idle'] ?? min(CONFIG['lifespan']['session']['idle'], ini_get('session.gc_maxlifetime'))) / 60)])
							]
						]
					]
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
			require_once('./document.php');
			$search = new DOCUMENT(get_class_vars(get_class($this)));
			$documents = $search->documentsearch(['search' => null]);
			// prepare existing documents lists grouped by context
			foreach ($documents as $row) {
				if (!$row['patient_access'] || in_array($row['context'], array_keys($this->_lang->_USER['documentcontext']['embedded']))) continue;
				if (!in_array($row['name'], $documentdatalist)) {
					$documentdatalist[] = $row['name'];
					// filter by unit
					if (!in_array($row['unit'], ['common', ...$_SESSION['user']['units']])) continue;

					// add to result
					$displayeddocuments[$row['context']][$row['name']] = ['href' => "javascript:api.record('get', null, 'document', '" . $row['name'] . "')"];
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
			$calendar = new CALENDARUTILITY($this->_sqlinterface, $this->_date);

			// storage warning
			$storage = round(disk_free_space("/") / pow(1024, 3), 3);
			if ($storage < CONFIG['limits']['storage']['file_storage_warning'] && PERMISSION::permissionFor('audit')){ // closest permission for matching responsibility with the whole quality management system
				$response['render']['content'][count($response['render']['content']) - 1][] = [
					'type' => 'textsection',
					'attributes' => [
						'name' => $this->_lang->GET('application.storage_warning', [':space' => $storage . ' GB']),
						'class' => 'red'
					]
				];
			}

			if (isset($user['app_settings']['weeklyhours']) && isset($user['app_settings']['annualvacation'])){
				// vacation warning
				$user = $this->_sqlinterface->EXECUTE('user_get', [
					':ids' => $_SESSION['user']['id'],
					':names' => $_SESSION['user']['name']
				]);
				$user = $user ? $user[0] : null;
				$timesheet_stats = $calendar->timesheetSummary([$user]);
				$usertimesheet = array_search($user['id'], array_column($timesheet_stats, '_id'));
				if ($usertimesheet !== false) $timesheet_stats = $timesheet_stats[$usertimesheet];
				if (isset($timesheet_stats['_leftvacation']) && isset($timesheet_stats['_annualvacation']) && 
					$timesheet_stats['_leftvacation'] / $timesheet_stats['_annualvacation'][count($timesheet_stats['_annualvacation']) - 1]['value'] > (365-date('z') + 1) / 365 // left vacation / annual vacation > remaining days of year / annual days
				){
					$response['render']['content'][count($response['render']['content']) - 1][] = [
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('calendar.timesheet.vacation_warning', [':number' => $timesheet_stats['_leftvacation']])
						]
					];
				}
			}

			// display current announcements
			$recentannouncements = [];
			$announcements = $this->_sqlinterface->EXECUTE('announcement_get_recent');
			foreach($announcements as $announcement){
				$announcement['organizational_unit'] = array_filter(explode(',', $announcement['organizational_unit'] ? : ''), fn($u) => boolval($u));
				if ($announcement['organizational_unit'] && !array_intersect($announcement['organizational_unit'], $_SESSION['user']['units'])) continue;

				$announcementcontent = [];
				if ($announcement['text']) {
					$announcementcontent[] = $announcement['text'];
					$announcementcontent[] = ' ';
				}
				if ($announcement['span_start']) $announcementcontent[] = $this->_lang->GET('message.announcement.start') . ' ' . $this->convertFromServerTime(substr($announcement['span_start'], 0 ,10));
				if ($announcement['span_end']) $announcementcontent[] = $this->_lang->GET('message.announcement.end') . ' ' . $this->convertFromServerTime(substr($announcement['span_end'], 0 ,10));
				$announcementcontent[] = $this->_lang->GET('message.announcement.last_edit', [':author' => $announcement['author_name'], ':date'=> $this->convertFromServerTime($announcement['date'])]);

				$recentannouncements[] = [
					[
						'type' => 'announcementsection',
						'attributes' => [
							'name' => $announcement['subject'],
							'class' => $announcement['highlight'] ? : null
						],
						'mdcontent' => implode("  \n", $announcementcontent), 
					]
				];
			}
			if ($recentannouncements) $response['render']['content'][] = count($recentannouncements) > 1 ? $recentannouncements : $recentannouncements[0];


			// set up dashboard notifications
			$tiles = [];
			$notifications = new NOTIFICATION(get_class_vars(get_class($this)));

			// messages
			$unseen = $notifications->messageunseen();
			if ($unseen) {
				$tiles[] = [
					'type' => 'tile',
					'attributes' => [
						'onclick' => "api.message('get', null, 'conversation')",
						'onkeydown' => "if (event.key==='Enter') api.message('get', null, 'conversation')",
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
							'onclick' => "api.order('get', null, 'approved')",
							'onkeydown' => "if (event.key==='Enter') api.order('get', null, 'approved')",
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
							'onclick' => "api.order('get', null, 'prepared')",
							'onkeydown' => "if (event.key==='Enter') api.order('get', null, 'prepared')",
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
						'onclick' => "api.record('get', null, 'records')",
						'onkeydown' => "if (event.key==='Enter') api.record('get', null, 'records')",
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
							'onclick' => "api.audit('get', null, 'audit')",
							'onkeydown' => "if (event.key==='Enter') api.audit('get', null, 'audit')",
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
						'onclick' => "api.document('get', null, 'approval')",
						'onkeydown' => "if (event.key==='Enter') api.document('get', null, 'approval')",
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

			// unapproved csv filters
			$unapproved = $notifications->csvfilter();
			if ($unapproved){
				$tiles[] = [
					'type' => 'tile',
					'attributes' => [
						'onclick' => "api.csvfilter('get', null, 'rule')",
						'onkeydown' => "if (event.key==='Enter') api.csvfilter('get', null, 'rule')",
						'role' => 'link',
						'tabindex' => '0'
					],
					'content' => [
						[
							'type' => 'textsection',
							'content' => $this->_lang->GET('application.dashboard.csvfilter', [':number' => $unapproved]),
							'attributes' => [
								'data-type' => 'filtered',
								'name' => $this->_lang->GET('csvfilter.navigation.filter_manager')
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
						'onclick' => "api.consumables('get', null, 'pendingincorporations')",
						'onkeydown' => "if (event.key==='Enter') api.consumables('get', null, 'pendingincorporations')",
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
						'onclick' => "api.audit('get', null, 'checks', 'complaints')",
						'onkeydown' => "if (event.key==='Enter') api.audit('get', null, 'checks', 'complaints')",
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
						'onclick' => "api.audit('get', null, 'checks', 'userskills')",
						'onkeydown' => "if (event.key==='Enter') api.audit('get', null, 'checks', 'userskills')",
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
						'id' => '_landingpagesearch',
						'onkeydown' => "if (event.key === 'Enter') {api.application('get', null, 'start', encodeURIComponent(this.value)); return false;}",
					]
				]
			];

			if ($this->_search) {
				require_once('./record.php');
				$search = new RECORD(get_class_vars(get_class($this)));

				// search records
				if ($records = $search->recordsearch(['search' => $this->_search])){
					$matches = [];
					foreach ($records as $contextkey => $context){
						foreach ($context as $record){
							$display = $record['identifier'] . ', ' . $this->_lang->GET('record.list_touched', [
								':date' => $this->convertFromServerTime($record['last_touch']),
								':document' => $record['last_document'],
								':user' => $record['last_user_name'] ?: $this->_lang->GET('general.deleted_user')
							]);
							$matches[$display] = [
									'href' => "javascript:api.record('get', null, 'record', '" . $record['identifier'] . "')"
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
				include_once('./document.php');
				$search = new DOCUMENT(get_class_vars(get_class($this))); 
				if ($documents = $search->documentsearch(['search' => $this->_search])){
					$matches = [];
					foreach ($documents as $document){
						$matches[$document['name']] = ['href' => 'javascript:void(0);', 'onclick' => "api.record('get', null, 'document', '" . $document['name'] . "')"];
					}
					$searchelements[] = [
						'type' => 'links',
						'description' => $this->_lang->GET('assemble.navigation.documents'),
						'content' => $matches
					];
				}

				// search files
				include_once('./file.php');
				$search = new FILE(get_class_vars(get_class($this))); 
				if ($files = $search->filesearch(['search' => $this->_search])){
					$matches = [];
					foreach ($files as $file){
						$matches[preg_replace('/.+fileserver\//', '', $file)] = $this->_filehandler->link(['href' => $file]);
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
			$week = $calendar->render('week', ['tasks', 'worklists']);

			// add overview to calendar view
			$overview[] = [
				'type' => 'calendar',
				'description' => $week['header'],
				'content' => $week['content'],
				'api' => 'tasks'
			];

			$displaytasks = $displayworklists = $displayabsentmates = '';
			$today = new \DateTime('now');
			$thisDaysEvents = $calendar->getDay($today->format('Y-m-d'));
			// sort events
			foreach ($thisDaysEvents as $row){
				if (!$row['affected_user']) $row['affected_user'] = $this->_lang->GET('general.deleted_user');
				if ((array_intersect(explode(',', $row['organizational_unit'] ? : ''), ['common', ...$_SESSION['user']['units']]) || 
					array_intersect(explode(',', $row['affected_user_units'] ? : ''), $_SESSION['user']['units'])) && !$row['closed']){
						if ($row['type'] === 'tasks') $displaytasks .= "* " . $row['subject'] . ($row['affected_user'] !== $this->_lang->GET('general.deleted_user') ? ' (' . $row['affected_user'] . ')': '') . "\n";
						if ($row['type'] === 'worklists') $displayworklists .= "* " . $row['subject'] . ($row['affected_user'] !== $this->_lang->GET('general.deleted_user') ? ' (' . $row['affected_user'] . ')': '') . "\n";
					}
				if ($row['type'] === 'timesheet' && !in_array($row['subject'], CONFIG['calendar']['hide_offduty_reasons']) && array_intersect(explode(',', $row['affected_user_units']), $_SESSION['user']['units'])) $displayabsentmates .= "* " . $row['affected_user'] . " ". $this->_lang->_USER['calendar']['timesheet']['pto'][$row['subject']] . " ". $this->convertFromServerTime(substr($row['span_start'], 0, 10)) . " - ". $this->convertFromServerTime(substr($row['span_end'], 0, 10)) . "\n";
			}
			// display todays tasks
			if ($displaytasks) $overview[] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('calendar.tasks.events_assigned_units')
				],
				'mdcontent' => $displaytasks,
				'mdrestiction' => [
					'safeMode' => true
				]
			];
			// display todays worklists
			if ($displayworklists) $overview[] = [
				'type' => 'textsection',
				'attributes' => [
					'name' => $this->_lang->GET('calendar.worklists.events_assigned_units')
				],
				'mdcontent' => $displayworklists, 
				'mdrestiction' => [
					'safeMode' => true
				]
			];
			// display todays absent workmates (sick, vacation, etc.)
			if ($displayabsentmates) $overview[] = [
				'type' => 'textsection',
				'attributes' => [
						'name' => $this->_lang->GET('calendar.timesheet.irregular')
				],
				'mdcontent' => $displayabsentmates, 
				'mdrestiction' => [
					'safeMode' => true
				]
			];

			// add past unclosed events
			$today->modify('-1 day');
			$pastEvents = $calendar->getWithinDateRange(null, $today->format('Y-m-d'));
			$uncompleted = [];
			foreach ($pastEvents as $row){
				if (!in_array($row, $thisDaysEvents) && $row['type'] === 'tasks' && array_intersect(explode(',', $row['organizational_unit']), ['common', ...$_SESSION['user']['units']]) && !$row['closed']) $uncompleted[$row['subject'] . " (" . $this->convertFromServerTime(substr($row['span_start'], 0, 10)) . ")"] = ['href' => "javascript:api.calendar('get', null, 'tasks', '" . substr($row['span_start'], 0, 10) . "', '" . substr($row['span_start'], 0, 10) . "')"];
			}
			if ($uncompleted) $overview[] = [
				'type' => 'links',
				'description' => $this->_lang->GET('calendar.tasks.events_assigned_units_uncompleted'),
				'content' => $uncompleted
			];

			if ($overview) $response['render']['content'][] = $overview;
		}
		$this->response($response);
	}
}
?>
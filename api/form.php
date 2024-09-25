<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2024 error on line 1 (dev@erroronline.one)
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

// add and edit form components and forms

class FORM extends API {
   // processed parameters for readability
   public $_requestedMethod = REQUEST[1];
   private $_requestedID = null;

	public function __construct(){
		parent::__construct();
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);

		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	/**
	 *                               _
	 *   ___ ___ ___ ___ ___ _ _ ___| |
	 *  | .'| . | . |  _| . | | | .'| |
	 *  |__,|  _|  _|_| |___|\_/|__,|_|
	 *      |_| |_|
	 */
	public function approval(){
		if (!PERMISSION::permissionFor('formapproval')) $this->response([], 401); // hardcoded for database structure
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				$approveas = UTILITY::propertySet($this->_payload, LANG::PROPERTY('assemble.approve_as_select'));
				if (!$approveas) $this->response([
					'response' => [
						'msg' => LANG::GET('assemble.approve_not_saved'),
						'type' => 'error'
					]]);
				$approveas = explode(' | ', $approveas);

				$approve = SQLQUERY::EXECUTE($this->_pdo, 'form_get', [
					'values' => [
						':id' => $this->_requestedID
					]
				]);
				$approve = $approve ? $approve[0] : null;
				if (!$approve) $this->response([], 404);

				$approve['approval'] = $approve['approval'] ? json_decode($approve['approval'], true) : []; 
				$tobeapprovedby = PERMISSION::permissionFor('formapproval', true);
				$time = new DateTime('now', new DateTimeZone(INI['application']['timezone']));
				foreach($tobeapprovedby as $permission){
					if (array_intersect(['admin', $permission], $_SESSION['user']['permissions']) && in_array(LANG::GET('permissions.' . $permission), $approveas)){
						$approve['approval'][$permission] = [
							'name' => $_SESSION['user']['name'],
							'date' => $time->format('Y-m-d H:i')
						];
					}
				}
				require_once('notification.php');
				$notifications = new NOTIFICATION;

				if (SQLQUERY::EXECUTE($this->_pdo, 'form_put_approve', [
					'values' => [
						':id' => $approve['id'],
						':approval' => json_encode($approve['approval']) ? : ''
					]
				])) $this->response([
						'response' => [
							'msg' => LANG::GET('assemble.approve_saved') . "<br />". (PERMISSION::fullyapproved('formapproval', $approve['approval']) ? LANG::GET('assemble.approve_completed') : LANG::GET('assemble.approve_pending')),
							'type' => 'success',
							'reload' => 'approval',
							],
							'data' => ['form_approval' => $notifications->forms()]]);
				else $this->response([
					'response' => [
						'msg' => LANG::GET('assemble.approve_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$componentselection = $formselection = $approvalposition = [];

				// prepare all unapproved elements
				$components = SQLQUERY::EXECUTE($this->_pdo, 'form_component_datalist');
				$forms = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
				$unapproved = ['forms' => [], 'components' => []];
				$return = ['render'=> ['content' => [[]]]]; // default first nesting
				$hidden = [];
				foreach(array_merge($components, $forms) as $element){
					if ($element['context'] === 'bundle') continue;
					if ($element['hidden']) $hidden[] = $element['context'] . $element['name']; // since ordered by recent, older items will be skipped
					if (!in_array($element['context'] . $element['name'], $hidden)){
						if (!PERMISSION::fullyapproved('formapproval', $element['approval'])) {
							switch ($element['context']){
								case 'component':
									$sort = ['unapproved' => 'components', 'selection' => 'componentselection'];
									break;
								default:
								$sort = ['unapproved' => 'forms', 'selection' => 'formselection'];
							}						
							if (!in_array($element['name'], array_keys($unapproved[$sort['unapproved']]))){
								$unapproved[$sort['unapproved']][$element['name']] = $element['content'];
								if (PERMISSION::pending('formapproval', $element['approval'])){
									${$sort['selection']}[$element['name']] = $this->_requestedID === $element['id'] ? ['value' => $element['id'], 'selected' => true] : ['value' => $element['id']];
								}
							}
						}
						$hidden[] = $element['context'] . $element['name']; // hide previous versions at all costs
					}
				}

				if ($componentselection) $return['render']['content'][0][] = [
					'type' => 'select',
					'attributes' => [
						'name' => LANG::GET('assemble.approve_component_select'),
						'onchange' => "api.form('get', 'approval', this.value)"
					],
					'content' => $componentselection
				];
				if ($formselection) $return['render']['content'][0][] =
				[
					'type' => 'select',
					'attributes' => [
						'name' => LANG::GET('assemble.approve_form_select'),
						'onchange' => "api.form('get', 'approval', this.value)"
					],
					'content' => $formselection
				];
				if ($componentselection || $formselection) $return['render']['content'][] = [
					[
						'type' => 'hr'
					]
				];
				else $this->response(['render' => ['content' => $this->noContentAvailable(LANG::GET('assemble.approve_no_approvals'))]]);

				if ($this->_requestedID){
					$alert = '';
					// recursively delete required attributes
					function unrequire($element){
						$result = [];
						foreach($element as $sub){
							if (array_is_list($sub)){
								array_push($result, ...unrequire($sub));
							} else {
								if (array_key_exists('attributes', $sub)){
									unset ($sub['attributes']['required']);
									unset ($sub['attributes']['data-required']);
								}
								if ($sub) $result[] = $sub;
							}
						}
						return [$result];
					}

					$approve = SQLQUERY::EXECUTE($this->_pdo, 'form_get', [
						'values' => [
							':id' => $this->_requestedID
						]
					]);
					$approve = $approve ? $approve[0] : null;
					if (!$approve) $this->response([], 404);
					foreach(PERMISSION::pending('formapproval', $approve['approval']) as $position){
						$approvalposition[LANG::GET('permissions.' . $position)] = [];
					}
					if ($approve['context'] === 'component'){
						array_push($return['render']['content'], ...unrequire(json_decode($approve['content'], true)['content'])[0]);
					}
					else {
						foreach(explode(',', $approve['content']) as $component){
							// get latest approved by name
							$cmpnnt = $this->latestApprovedName('form_component_get_by_name', $component);
							if ($cmpnnt) {
								if (!PERMISSION::fullyapproved('formapproval', $cmpnnt['approval'])){
									$alert .= LANG::GET('assemble.approve_form_unapproved_component', [':name' => $cmpnnt['name']]). '<br />';
								}
								array_push($return['render']['content'], ...unrequire(json_decode($cmpnnt['content'], true)['content'])[0]);
							}
						}
						if ($alert) $return['response'] = ['msg' => $alert, 'type' => 'info'];
					}
					array_push($return['render']['content'], 
						[
							[
								'type' => 'hr'
							]
						], [
							[
								'type' => 'checkbox',
								'content' => $approvalposition,
								'attributes' => [
									'name' => LANG::GET('assemble.approve_as_select')
								]
							]
						]
					);
					$return['render']['form'] = [
						'data-usecase' => 'approval',
						'action' => "javascript: api.form('put', 'approval', " . $this->_requestedID . ")",
						'data-confirm' => true
					];
					if ($approve['name']) $return['header'] = $approve['name'];
				}
				$this->response($return);
				break;
		}
	}

	/**
	 *   _             _ _
	 *  | |_ _ _ ___ _| | |___
	 *  | . | | |   | . | | -_|
	 *  |___|___|_|_|___|_|___|
	 *
	 */
	public function bundle(){
		if (!PERMISSION::permissionFor('formcomposer')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if ($content = UTILITY::propertySet($this->_payload, LANG::PROPERTY('assemble.edit_bundle_content'))) $content = implode(',', preg_split('/[\n\r]{1,}/', $content));
				else $content = '';
				$bundle = [
					':name' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('assemble.edit_bundle_name')),
					':alias' => '',
					':context' => 'bundle',
					':author' => $_SESSION['user']['name'],
					':content' => $content,
					':regulatory_context' => '',
					':permitted_export' => null,
					':restricted_access' => null
				];

				if (!trim($bundle[':name']) || !trim($bundle[':content'])) $this->response([], 400);

				// put hidden attribute if anything else remains the same
				// get latest by name
				$exists = [];
				$forms = SQLQUERY::EXECUTE($this->_pdo, 'form_bundle_get_by_name', [
					'values' => [
						':name' => $bundle[':name']
					]
				]);
				foreach ($forms as $exists){
					break;
				}
				
				if ($exists && $exists['content'] === $bundle[':content']) {
					if (SQLQUERY::EXECUTE($this->_pdo, 'form_put', [
						'values' => [
							':alias' => $exists['alias'],
							':context' => $exists['context'],
							':hidden' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('assemble.edit_bundle_hidden')) === LANG::PROPERTY('assemble.edit_bundle_hidden_hidden')? 1 : 0,
							':id' => $exists['id'],
							':regulatory_context' => '',
							':permitted_export' => null,
							':restricted_access' => null
						]
					])) $this->response([
						'response' => [
							'name' => $bundle[':name'],
							'msg' => LANG::GET('assemble.edit_bundle_saved', [':name' => $bundle[':name']]),
							'type' => 'success'
						]]);	
				}

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $bundle[':name'], $matches)) $this->response(['response' => ['msg' => LANG::GET('assemble.error_forbidden_name', [':name' => $bundle[':name']]), 'type' => 'error']]);
				}

				if (SQLQUERY::EXECUTE($this->_pdo, 'form_post', [
					'values' => $bundle
				])) $this->response([
						'response' => [
							'name' => $bundle[':name'],
							'msg' => LANG::GET('assemble.edit_bundle_saved', [':name' => $bundle[':name']]),
							'type' => 'success'
						]]);
				else $this->response([
					'response' => [
						'name' => false,
						'msg' => LANG::GET('assemble.edit_bundle_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$bundledatalist = [];
				$options = ['...' . LANG::GET('assemble.edit_existing_bundle_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$alloptions = ['...' . LANG::GET('assemble.edit_existing_bundle_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$insertform = ['...' . LANG::GET('assemble.edit_bundle_insert_default') => ['value' => ' ']];
				$return = [];

				// get selected bundle
				if (intval($this->_requestedID)){
					$bundle = SQLQUERY::EXECUTE($this->_pdo, 'form_get', [
						'values' => [
							':id' => $this->_requestedID
						]
					]);
					$bundle = $bundle ? $bundle[0] : null;
				} else {
					// get latest by name
					$bundle = [];
					$forms = SQLQUERY::EXECUTE($this->_pdo, 'form_bundle_get_by_name', [
						'values' => [
							':name' => $this->_requestedID
						]
					]);
					foreach ($forms as $bundle){
						break;
					}
				}
				if (!$bundle) $bundle = [
					'id' => '',
					'name' => '',
					'alias' => '',
					'context' => '',
					'date' => '',
					'author' => '',
					'content' => '',
					'hidden' => 0
				];
				if($this->_requestedID && $this->_requestedID !== 'false' && !$bundle['name'] && $this->_requestedID !== '0') $return['response'] = ['msg' => LANG::GET('texttemplate.error_template_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing bundle lists
				$bundles = SQLQUERY::EXECUTE($this->_pdo, 'form_bundle_datalist');
				$hidden = [];
				foreach($bundles as $key => $row) {
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
						if (!array_key_exists($row['name'], $options) && !in_array($row['name'], $hidden)) {
							$bundledatalist[] = $row['name'];
							$options[$row['name']] = ($row['name'] == $bundle['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
						}
						$alloptions[$row['name'] . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => substr($row['date'], 0, -3)])] = ($row['name'] == $bundle['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
				}

				// prepare available forms lists
				// get latest approved by name
				$forms = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
				$hidden = [];
				foreach($forms as $key => $row) {
					if (!PERMISSION::fullyapproved('formapproval', $row['approval'])) continue;
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (!in_array($row['name'], $hidden)) {
							$insertform[$row['name']] = ['value' => $row['name'] . "\n"];
					}
				}

				$return['render'] = [
					'form' => [
						'data-usecase' => 'bundle',
						'action' => "javascript:api.form('post', 'bundle')"],
					'content' => [
						[
							[
								[
									'type' => 'datalist',
									'content' => array_values(array_unique($bundledatalist)),
									'attributes' => [
										'id' => 'templates'
									]
								], [
									'type' => 'select',
									'attributes' => [
										'name' => LANG::GET('assemble.edit_existing_bundle_select'),
										'onchange' => "api.form('get', 'bundle', this.value)"
									],
									'content' => $options
								], [
									'type' => 'search',
									'attributes' => [
										'name' => LANG::GET('assemble.edit_existing_bundle'),
										'list' => 'templates',
										'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'bundle', this.value); return false;}"
									]
								]
							], [
								[
									'type' => 'select',
									'attributes' => [
										'name' => LANG::GET('assemble.edit_existing_bundle_all'),
										'onchange' => "api.form('get', 'bundle', this.value)"
									],
									'content' => $alloptions
								]
							]
						], [
							[
								'type' => 'text',
								'attributes' => [
									'name' => LANG::GET('assemble.edit_bundle_name'),
									'value' => $bundle['name'],
									'list' => 'templates',
									'required' => true,
									'data-loss' => 'prevent'
								],
								'hint' => ($bundle['name'] ? LANG::GET('assemble.compose_component_author', [':author' => $bundle['author'], ':date' => substr($bundle['date'], 0, -3)]) . '<br>' : LANG::GET('assemble.compose_component_name_hint'))
							], [
								'type' => 'select',
								'attributes' => [
									'name' => LANG::GET('assemble.edit_bundle_insert_name'),
									'onchange' => "if (this.value.length > 1) _.insertChars(this.value, 'content'); this.selectedIndex = 0;"
								],
								'content' => $insertform
							], [
								'type' => 'textarea',
								'hint' => LANG::GET('assemble.edit_bundle_content_hint'),
								'attributes' => [
									'name' => LANG::GET('assemble.edit_bundle_content'),
									'value' => implode("\n", explode(",", $bundle['content'])),
									'rows' => 6,
									'id' => 'content',
									'required' => true,
									'data-loss' => 'prevent'
								]
							]
						]
					]
				];
				if ($bundle['id']){
					$hidden = [
						'type' => 'radio',
						'attributes' => [
							'name' => LANG::GET('assemble.edit_bundle_hidden')
						],
						'content' => [
							LANG::GET('assemble.edit_bundle_hidden_visible') => ['checked' => true],
							LANG::GET('assemble.edit_bundle_hidden_hidden') => []
						],
						'hint' => LANG::GET('assemble.edit_bundle_hidden_hint')
					];
					if ($bundle['hidden']) $hidden['content'][LANG::GET('assemble.edit_bundle_hidden_hidden')]['checked'] = true;
					array_push($return['render']['content'][1], $hidden);
				}

				$this->response($return);
				break;
		}
	}
	
	/**
	 *                                     _
	 *   ___ ___ _____ ___ ___ ___ ___ ___| |_
	 *  |  _| . |     | . | . |   | -_|   |  _|
	 *  |___|___|_|_|_|  _|___|_|_|___|_|_|_|
	 *                |_|
	 */
	public function component(){
		if (!PERMISSION::permissionFor('formcomposer')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$component = json_decode($this->_payload->composedComponent, true);
				$component_name = $component['name'];
				unset($component['name']);
				$component_hidden = intval($component['hidden']);
				unset($component['hidden']);
				$component_approve = $component['approve'];
				unset($component['approve']);

				// put hidden attribute if anything else remains the same
				// get latest approved by name
				$exists = $this->latestApprovedName('form_component_get_by_name', $this->_requestedID);
				if ($exists && json_decode($exists['content'], true) == $component) {
					if (SQLQUERY::EXECUTE($this->_pdo, 'form_put', [
						'values' => [
							':alias' => '',
							':context' => 'component',
							':hidden' => $component_hidden,
							':regulatory_context' => '',
							':id' => $exists['id'],
							':permitted_export' => NULL,
							':restricted_access' => NULL
						]
					])) $this->response([
							'response' => [
								'name' => $component_name,
								'msg' => LANG::GET('assemble.edit_component_saved', [':name' => $component_name]),
								'type' => 'success'
							]]);	
				}

				if (!($component_approve = array_search($component_approve, LANGUAGEFILE['units']))) $this->response(['response' => ['msg' => LANG::GET('assemble.edit_component_not_saved_missing'), 'type' => 'error']]);

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $component_name, $matches)) $this->response(['response' => ['msg' => LANG::GET('assemble.error_forbidden_name', [':name' => $component_name]), 'type' => 'error']]);
				}
				// recursively replace images with actual $_FILES content according to content nesting
				if (array_key_exists('composedComponent_files', $_FILES)){
					$uploads = UTILITY::storeUploadedFiles(['composedComponent_files'], UTILITY::directory('component_attachments'), [$component_name . '_' . $this->_currentdate->format('YmdHis')]);
					$files=[];
					foreach($uploads as $path){
						UTILITY::resizeImage($path, INI['limits']['form_image'], UTILITY_IMAGE_REPLACE);
						// retrieve actual filename with prefix dropped to compare to upload filename
						// boundary is underscore, actual underscores within uploaded file name will be reinserted
						$filename = implode('_', array_slice(explode('_', pathinfo($path)['basename']) , 2));
						$files[$filename] = substr($path, 1);
					}
					function replace_images($element, $filearray){
						$result = [];
						foreach($element as $sub){
							if (array_is_list($sub)){
								$result[] = replace_images($sub, $filearray);
							} else {
								if ($sub['type'] === 'image'){
									preg_match_all('/[\w\s\d\.]+/m', $sub['attributes']['name'], $fakefilename);
									$filename = $fakefilename[0][count($fakefilename[0])-1];
									if ($filename && array_key_exists($filename, $filearray)){ // replace only if $_FILES exist, in case of updates, where no actual file has been submitted
										$sub['attributes']['name'] = $filename;
										$sub['attributes']['url'] = $filearray[$filename];
									}
								}
								$result[] = $sub;
							}
						}
						return $result;
					}
					$component['content'] = replace_images($component['content'], $files);
				}
				if (SQLQUERY::EXECUTE($this->_pdo, 'form_post', [
					'values' => [
						':name' => $component_name,
						':alias' => '',
						':context' => 'component',
						':author' => $_SESSION['user']['name'],
						':content' => json_encode($component),
						':regulatory_context' => '',
						':permitted_export' => NULL,
						':restricted_access' => NULL
					]
				])) {
						$component_id = $this->_pdo->lastInsertId();
						$message = LANG::GET('assemble.approve_component_request_alert', [':name' => '<a href="javascript:void(0);" onpointerup="api.form(\'get\', \'approval\', ' . $component_id . ')"> ' . $component_name . '</a>']);
						foreach(PERMISSION::permissionFor('formapproval', true) as $permission){
							if ($permission === 'supervisor') $this->alertUserGroup(['permission' => ['supervisor'], 'unit' => [$component_approve]], $message);
							else $this->alertUserGroup(['permission' => [$permission]], $message);
						}
						$this->response([
						'response' => [
							'name' => $component_name,
							'msg' => LANG::GET('assemble.edit_component_saved', [':name' => $component_name]),
							'reload' => 'component_editor',
							'type' => 'success'
						]]);
				}
				else $this->response([
					'response' => [
						'name' => false,
						'msg' => LANG::GET('assemble.edit_component_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				if (intval($this->_requestedID)){
					$component = SQLQUERY::EXECUTE($this->_pdo, 'form_get', [
						'values' => [
							':id' => $this->_requestedID
						]
					]);
					$component = $component ? $component[0] : null;
				} else {
					// get latest approved by name
					$component = $this->latestApprovedName('form_component_get_by_name', $this->_requestedID);
				}
				if ($component){
					$component['content'] = json_decode($component['content']);
					$this->response(['render' => $component]);
				}
				$this->response(['response' => ['msg' => LANG::GET('assemble.error_component_not_found', [':name' => $this->_requestedID]), 'type' => 'error']]);
				break;
			case 'DELETE':
				$component = SQLQUERY::EXECUTE($this->_pdo, 'form_get', [
					'values' => [
						':id' => $this->_requestedID
					]
				]);
				$component = $component ? $component[0] : null;
				if (!$component || PERMISSION::fullyapproved('formapproval', $component['approval'])) $this->response(['response' => ['msg' => LANG::GET('assemble.edit_component_delete_failure'), 'type' => 'error']]);
				// recursively check for identifier
				function deleteImages($element){
					foreach($element as $sub){
						if (array_is_list($sub)){
							deleteImages($sub);
						} else {
							if (array_key_exists('type', $sub) && $sub['type'] === 'image')
								UTILITY::delete('.' . $sub['attributes']['url']);
						}
					}
				}
				deleteImages(json_decode($component['content'], true)['content']);
				if (SQLQUERY::EXECUTE($this->_pdo, 'form_delete', [
					'values' => [
						':id' => $this->_requestedID
					]
				])) $this->response(['response' => [
					'msg' => LANG::GET('assemble.edit_component_delete_success'),
					'type' => 'success',
					'reload' => 'component_editor'
					]]);
				break;
		}
	}
	
	/**
	 *                                     _         _ _ _
	 *   ___ ___ _____ ___ ___ ___ ___ ___| |_ ___ _| |_| |_ ___ ___
	 *  |  _| . |     | . | . |   | -_|   |  _| -_| . | |  _| . |  _|
	 *  |___|___|_|_|_|  _|___|_|_|___|_|_|_| |___|___|_|_| |___|_|
	 *                |_|
	 */
	public function component_editor(){
		if (!PERMISSION::permissionFor('formcomposer')) $this->response([], 401);
		$componentdatalist = [];
		$options = ['...' . LANG::GET('assemble.edit_existing_components_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		$alloptions = ['...' . LANG::GET('assemble.edit_existing_components_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		$return = [];
		
		// get selected component
		if ($this->_requestedID == '0' || intval($this->_requestedID)){
			$component = SQLQUERY::EXECUTE($this->_pdo, 'form_get', [
				'values' => [
					':id' => $this->_requestedID
				]
			]);
			$component = $component ? $component[0] : null;
			if (!$component) $component = ['id' => '', 'name' =>''];
		} else {
			if (!$component = $this->latestApprovedName('form_component_get_by_name', $this->_requestedID)) $component = ['id' => '', 'name' =>''];
		}
		if ($this->_requestedID && $this->_requestedID !== 'false' && !$component['name'] && $this->_requestedID !== '0') $return['response'] = ['msg' => LANG::GET('assemble.error_component_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

		// prepare existing component lists
		$components = SQLQUERY::EXECUTE($this->_pdo, 'form_component_datalist');
		$hidden = [];
		foreach($components as $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!array_key_exists($row['name'], $options) && !in_array($row['name'], $hidden) && PERMISSION::fullyapproved('formapproval', $row['approval'])) {
				$componentdatalist[] = $row['name'];
				$options[$row['name']] = ($row['name'] == $component['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
			}
			$approved = PERMISSION::fullyapproved('formapproval', $row['approval']) ? LANG::GET('assemble.approve_approved') : LANG::GET('assemble.approve_unapproved');
			$alloptions[$row['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => substr($row['date'], 0, -3)]) . ' - ' . $approved] = ($row['name'] == $component['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
		}

		// load approved forms for accasional linking
		// check for dependencies in forms
		$approvedforms = $dependedforms = [];
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		$hidden = [];
		foreach($fd as $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden)) $approvedforms[$row['name']] = []; // prepare for selection
			if (array_key_exists('content', $component) && !in_array($row['name'], $dependedforms) && !in_array($row['name'], $hidden) && in_array($component['name'], explode(',', $row['content']))) {
				$dependedforms[] = $row['name'];
			}
		}

		// prepare unit list for approval
		$approve = [
			'hint' => LANG::GET('assemble.compose_component_approve_hint', [':roles' => implode(', ', array_map(Fn($v) => LANGUAGEFILE['permissions'][$v], PERMISSION::permissionFor('formapproval', true)))]),
			'name' => LANG::GET('assemble.compose_component_approve'),
			'content' => ['...' . LANG::GET('assemble.compose_component_approve_select_default') => ['value' => '0']]
		];
		foreach(LANGUAGEFILE['units'] as $key => $value){
			$approve['content'][$value] = [];
		}

		$return['render'] = [
			'content' => [
				[
					[
						[
							'type' => 'datalist',
							'content' => array_values(array_unique($componentdatalist)),
							'attributes' => [
								'id' => 'components'
							]
						], [
							'type' => 'select',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_components_select'),
								'onchange' => "api.form('get', 'component_editor', this.value)"
							],
							'content' => $options
						],[
							'type' => 'search',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_forms'),
								'list' => 'components',
								'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'component_editor', this.value); return false;}"
							]
						]
					],[
						[
							'type' => 'select',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_components_all'),
								'onchange' => "api.form('get', 'component_editor', this.value)"
							],
							'content' => $alloptions
						],
					]
				],[
					[[
						'type' => 'textsection',
						'attributes' => [
							'name' => LANG::GET('assemble.edit_components_info_description')
						],
						'content' => LANG::GET('assemble.edit_components_info_content')
					]], [[
						'form' => true,
						'type' => 'compose_scanner',
						'description' => LANG::GET('assemble.compose_scanner')
					]], [[
						'form' => true,
						'type' => 'compose_textsection',
						'description' => LANG::GET('assemble.compose_textsection')
					]], [[
						'form' => true,
						'type' => 'compose_image',
						'description' => LANG::GET('assemble.compose_image')
					]], [[
						'form' => true,
						'type' => 'compose_text',
						'description' => LANG::GET('assemble.compose_text')
					]], [[
						'form' => true,
						'type' => 'compose_textarea',
						'description' => LANG::GET('assemble.compose_textarea')
					]], [[
						'form' => true,
						'type' => 'compose_number',
						'description' => LANG::GET('assemble.compose_number')
					]], [[
						'form' => true,
						'type' => 'compose_date',
						'description' => LANG::GET('assemble.compose_date')
					]], [[
						'form' => true,
						'type' => 'compose_tel',
						'description' => LANG::GET('assemble.compose_tel')
					]], [[
						'form' => true,
						'type' => 'compose_email',
						'description' => LANG::GET('assemble.compose_email')
					]], [[
						'form' => true,
						'type' => 'compose_productselection',
						'description' => LANG::GET('assemble.compose_productselection')
					]], [[
						'form' => true,
						'type' => 'compose_range',
						'description' => LANG::GET('assemble.compose_range')
					]], [[
						'form' => true,
						'type' => 'compose_links',
						'description' => LANG::GET('assemble.compose_links')
					]], [[
						'form' => true,
						'type' => 'compose_checkbox',
						'description' => LANG::GET('assemble.compose_checkbox')
					]], [[
						'form' => true,
						'type' => 'compose_radio',
						'description' => LANG::GET('assemble.compose_radio')
					]], [[
						'form' => true,
						'type' => 'compose_select',
						'description' => LANG::GET('assemble.compose_select')
					]], [[
						'form' => true,
						'type' => 'compose_file',
						'description' => LANG::GET('assemble.compose_file')
					]], [[
						'form' => true,
						'type' => 'compose_photo',
						'description' => LANG::GET('assemble.compose_photo')
					]], [[
						'form' => true,
						'type' => 'compose_signature',
						'description' => LANG::GET('assemble.compose_signature')
					]], [[
						'form' => true,
						'type' => 'compose_calendarbutton',
						'description' => LANG::GET('assemble.compose_calendarbutton')
					]], [[
						'form' => true,
						'type' => 'compose_formbutton',
						'description' => LANG::GET('assemble.compose_link_form'),
						'content' => $approvedforms
					]]
				],
				[[
					'type' => 'compose_component',
					'description' => LANG::GET('assemble.compose_component'),
					'value' => $component['name'],
					'hint' => ($component['name'] ? LANG::GET('assemble.compose_component_author', [':author' => $component['author'], ':date' => substr($component['date'], 0, -3)]) . '<br>' : LANG::GET('assemble.compose_component_name_hint')) .
						($dependedforms ? LANG::GET('assemble.compose_component_form_dependencies', [':forms' => implode(',', $dependedforms)]) : ''),
					'hidden' => $component['name'] ? intval($component['hidden']) : 1,
					'approve' => $approve
				]],
				[[
					'type' => 'trash',
					'description' => LANG::GET('assemble.edit_trash')
				]]
			]
		];
		if (array_intersect(['admin'], $_SESSION['user']['permissions'])){
			$return['render']['content'][1][] = [[
				'form' => false,
				'type' => 'compose_raw',
				'description' => LANG::GET('assemble.compose_raw')
			]];
		}
		if ($component['name'] && (!PERMISSION::fullyapproved('formapproval', $component['approval'])))
			$return['render']['content'][count($return['render']['content']) - 2][] = [
				[
					'type' => 'deletebutton',
					'attributes' => [
						'value' => LANG::GET('assemble.edit_delete'),
						'onpointerup' => "api.form('delete', 'component', " . $component['id'] . ")" 
					]
				]
			];

		if (array_key_exists('content', $component)) $return['render']['component'] = json_decode($component['content']);
		if ($component['name']) $return['header'] = $component['name'];
		$this->response($return);
	}
	
	/**
	 *   ___
	 *  |  _|___ ___ _____
	 *  |  _| . |  _|     |
	 *  |_| |___|_| |_|_|_|
	 *
	 */
	public function form(){
		if (!PERMISSION::permissionFor('formcomposer')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!$this->_payload->context) $this->response(['response' => ['msg' => LANG::GET("assemble.edit_form_not_saved_missing"), 'type' => 'error']]);
				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $this->_payload->name, $matches)) $this->response(['response' => ['msg' => LANG::GET('assemble.error_forbidden_name', [':name' => $this->_payload->name]), 'type' => 'error']]);
				}

				// recursively check for identifier
				function check4identifier($element, $hasidentifier = false){
					if ($hasidentifier) return true;
					foreach($element as $sub){
						if (array_is_list($sub)){
							$hasidentifier = check4identifier($sub, $hasidentifier);
						} else {
							if (array_key_exists('type', $sub) && $sub['type'] === 'identify') $hasidentifier = true;
						}
					}
					return $hasidentifier;
				}
				// check for identifier if context makes it mandatory
				// do this in advance of updating in case of selecting such a context
				$this->_payload->context = substr($this->_payload->context, -2) === ' *' ? substr($this->_payload->context, 0, -2) : $this->_payload->context; // unset marking
				if (in_array($this->_payload->context, array_keys(LANGUAGEFILE['formcontext']['identify']))){
					$hasidentifier = false;
					foreach($this->_payload->content as $component){
						// get latest approved by name
						$latestcomponent = $this->latestApprovedName('form_component_get_by_name', $component);
						if (check4identifier(json_decode($latestcomponent['content'], true)['content'])) $hasidentifier = true;
					}
					if (!$hasidentifier) $this->response(['response' => ['msg' => LANG::GET('assemble.compose_context_missing_identifier'), 'type' => 'error']]);
				}
				// convert values to keys for regulatory_context
				$regulatory_context = [];
				if ($this->_payload->regulatory_context) {
					$rc = explode(', ', $this->_payload->regulatory_context);
					foreach($rc as $context){
						$regulatory_context[] = array_search($context, LANGUAGEFILE['regulatory']); 
					}
				}
				// convert values to keys for restricted_access
				$restricted_access = [];
				if ($this->_payload->restricted_access) {
					$rc = explode(', ', $this->_payload->restricted_access);
					foreach($rc as $context){
						$restricted_access[] = array_search($context, LANGUAGEFILE['permissions']); 
					}
				}
				
				// put hidden attribute, alias (uncritical) or context (user error) if anything else remains the same
				// get latest approved by name
				$exists = $this->latestApprovedName('form_form_get_by_name', $this->_payload->name);
				if ($exists && $exists['content'] == implode(',', $this->_payload->content)) {
					if (SQLQUERY::EXECUTE($this->_pdo, 'form_put', [
						'values' => [
							':alias' => gettype($this->_payload->alias) === 'array' ? implode(' ', $this->_payload->alias) : '',
							':context' => $this->_payload->context,
							':hidden' => intval($this->_payload->hidden),
							':regulatory_context' => implode(',', $regulatory_context),
							':id' => $exists['id'],
							':permitted_export' => $this->_payload->permitted_export ? : 0,
							':restricted_access' => $restricted_access ? implode(',', $restricted_access) : NULL
						]
					])) $this->response([
							'response' => [
								'name' => $this->_payload->name,
								'msg' => LANG::GET('assemble.edit_form_saved', [':name' => $this->_payload->name]),
								'type' => 'success'
							]]);	
				}

				// if not updated check if approve is set, not earlier
				if (!in_array($this->_payload->approve, LANGUAGEFILE['units'])) $this->response(['response' => ['msg' => LANG::GET('assemble.edit_form_not_saved_missing'), 'type' => 'error']]);
				$this->_payload->approve = array_search($this->_payload->approve, LANGUAGEFILE['units']);

				if (SQLQUERY::EXECUTE($this->_pdo, 'form_post', [
					'values' => [
						':name' => $this->_payload->name,
						':alias' => gettype($this->_payload->alias) === 'array' ? implode(' ', $this->_payload->alias): '',
						':context' => gettype($this->_payload->context) === 'array' ? '': $this->_payload->context,
						':author' => $_SESSION['user']['name'],
						':content' => implode(',', $this->_payload->content),
						':regulatory_context' => implode(',', $regulatory_context),
						':permitted_export' => $this->_payload->permitted_export ? : 0,
						':restricted_access' => $restricted_access ? implode(',', $restricted_access) : NULL
					]
				])) {
						$form_id = $this->_pdo->lastInsertId();
						$message = LANG::GET('assemble.approve_form_request_alert', [':name' => '<a href="javascript:void(0);" onpointerup="api.form(\'get\', \'approval\', ' . $form_id . ')"> ' . $this->_payload->name . '</a>']);
						foreach(PERMISSION::permissionFor('formapproval', true) as $permission){
							if ($permission === 'supervisor') $this->alertUserGroup(['permission' => ['supervisor'], 'unit' => [$this->_payload->approve]], $message);
							else $this->alertUserGroup(['permission' => [$permission]], $message);
						}
						$this->response([
						'response' => [
							'name' => $this->_payload->name,
							'msg' => LANG::GET('assemble.edit_form_saved', [':name' => $this->_payload->name]),
							'reload' => 'form_editor',
							'type' => 'success'
						]]);
				}
				else $this->response([
					'response' => [
						'name' => false,
						'msg' => LANG::GET('assemble.edit_form_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'DELETE':
				$component = SQLQUERY::EXECUTE($this->_pdo, 'form_get', [
					'values' => [
						':id' => $this->_requestedID
					]
				]);
				$component = $component ? $component[0] : null;
				if (!$component || PERMISSION::fullyapproved('formapproval', $component['approval'])) $this->response(['response' => ['msg' => LANG::GET('assemble.edit_form_delete_failure'), 'type' => 'error']]);
				
				if (SQLQUERY::EXECUTE($this->_pdo, 'form_delete', [
					'values' => [
						':id' => $this->_requestedID
					]
				])) $this->response(['response' => [
					'msg' => LANG::GET('assemble.edit_form_delete_success'),
					'type' => 'success',
					'reload' => 'form_editor',
					]]);
				break;
		}
	}
	
	/**
	 *   ___                     _ _ _
	 *  |  _|___ ___ _____ ___ _| |_| |_ ___ ___
	 *  |  _| . |  _|     | -_| . | |  _| . |  _|
	 *  |_| |___|_| |_|_|_|___|___|_|_| |___|_|
	 *
	 */
	public function form_editor(){
		if (!PERMISSION::permissionFor('formcomposer')) $this->response([], 401);
		$formdatalist = $componentdatalist = [];
		$formoptions = ['...' . LANG::GET('assemble.edit_existing_forms_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		$alloptions = ['...' . LANG::GET('assemble.edit_existing_forms_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		$componentoptions = ['...' => ['value' => '0']];
		$contextoptions = ['...' . LANG::GET('assemble.edit_form_context_default') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		$return = [];
		
		// get selected form
		if ($this->_requestedID == '0' || intval($this->_requestedID)){
			$form = SQLQUERY::EXECUTE($this->_pdo, 'form_get', [
				'values' => [
					':id' => $this->_requestedID
				]
			]);
			$form = $form ? $form[0] : null;
		} else{
			// get latest approved by name
			$form = $this->latestApprovedName('form_form_get_by_name', $this->_requestedID);
		}
		if (!$form) $form= [
			'name' => '',
			'alias' => '',
			'context' => '',
			'regulatory_context' => '',
			'permitted_export' => null,
			'restricted_access' => null
		];
		if($this->_requestedID && $this->_requestedID !== 'false' && !$form['name'] && $this->_requestedID !== '0') $return['response'] = ['msg' => LANG::GET('assemble.error_form_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

		// prepare existing forms lists
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		$hidden = [];
		foreach($fd as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!array_key_exists($row['name'], $formoptions) && !in_array($row['name'], $hidden) && PERMISSION::fullyapproved('formapproval', $row['approval'])) {
				$formdatalist[] = $row['name'];
				$formoptions[$row['name']] = ($row['name'] === $form['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
			}
			$approved = PERMISSION::fullyapproved('formapproval', $row['approval']) ? LANG::GET('assemble.approve_approved') : LANG::GET('assemble.approve_unapproved');
			$alloptions[$row['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => substr($row['date'], 0, -3)]) . ' - ' . $approved] = ($row['name'] === $form['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
		}

		// prepare existing component list of fully approved
		$cd = SQLQUERY::EXECUTE($this->_pdo, 'form_component_datalist');
		$hidden = [];
		foreach($cd as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!array_key_exists($row['name'], $componentoptions) && !in_array($row['name'], $hidden) && PERMISSION::fullyapproved('formapproval', $row['approval'])) {
				$componentdatalist[] = $row['name'];
				//$approved = PERMISSION::fullyapproved('formapproval', $row['approval']) ? LANG::GET('assemble.approve_approved') : LANG::GET('assemble.approve_unapproved');
				$componentoptions[$row['name'] . ' - ' . LANG::GET('assemble.approve_approved')] = ['value' => $row['id']];
			}
		}

		// check for bundle dependencies
		$bd = SQLQUERY::EXECUTE($this->_pdo, 'form_bundle_datalist');
		$hidden = [];
		$dependedbundles = [];
		foreach($bd as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden) && 
			in_array($form['name'], explode(',', $row['content'])) && 
			!in_array($form['name'], $dependedbundles)) $dependedbundles[] = $form['name']; 
		}

		// check for dependencies in approved components (linked forms)
		$dependedcomponents = [];
		if ($form['name']){
			$cd = SQLQUERY::EXECUTE($this->_pdo, 'form_component_datalist');
			$hidden = [];
			foreach($cd as $row) {
				if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
				if (!PERMISSION::fullyapproved('formapproval', $row['approval'])) continue;
				if (!in_array($row['name'], $dependedcomponents) && !in_array($row['name'], $hidden)) {
					// don't bother disassembling content, just look for an expression
					if (stristr($row['content'], '"value":"' . LANG::GET('assemble.compose_link_form_display_button', [':form' => $form['name']]) . '"')
						|| stristr($row['content'], '"value":"' . LANG::GET('assemble.compose_link_form_continue_button', [':form' => $form['name']]) . '"')) $dependedcomponents[] = $row['name'];
				}
			}
		}		

		// prepare existing context list
		foreach(LANGUAGEFILE['formcontext'] as $type => $contexts){
			foreach($contexts as $context => $display){
				if ($type === 'identify') $display .= ' *';
				$contextoptions[$display] = $context===$form['context'] ? ['value' => $context, 'selected' => true] : ['value' => $context];
			}
		}

		// prepare unit list for approval
		$approve = [
			'hint' => LANG::GET('assemble.compose_component_approve_hint', [':roles' => implode(', ', array_map(Fn($v) => LANGUAGEFILE['permissions'][$v], PERMISSION::permissionFor('formapproval', true)))]),
			'name' => LANG::GET('assemble.compose_component_approve'),
			'content' => ['...' . LANG::GET('assemble.compose_component_approve_select_default') => ['value' => '0']]
		];
		foreach(LANGUAGEFILE['units'] as $key => $value){
			$approve['content'][$value] = [];
		}
		$regulatory_context = [];
		$form['regulatory_context'] = explode(',', $form['regulatory_context'] ? : '');
		foreach(LANGUAGEFILE['regulatory'] as $key => $value){
			$regulatory_context[$value] = ['value' => $key];
			if (in_array($key, $form['regulatory_context'])) $regulatory_context[$value]['checked'] = true;
		}
		$permitted_export = [
			'hint' => LANG::GET('assemble.edit_form_permitted_export_hint', [':permissions' => implode(', ', array_map(Fn($v) => LANGUAGEFILE['permissions'][$v], PERMISSION::permissionFor('formexport', true)))]),
			'content' => [
				LANG::GET('assemble.edit_form_permitted_export') => $form['permitted_export'] ? ['checked' => true]: []
			]
		];
		$restricted_access = [
			'description' => LANG::GET('assemble.edit_form_restricted_access'),
			'hint' => LANG::GET('assemble.edit_form_restricted_access_hint'),
			'content' => []
		];
		$form['restricted_access'] = explode(',', strval($form['restricted_access']));
		foreach(LANGUAGEFILE['permissions'] as $value => $translation){
			$restricted_access['content'][$translation] = ['value' => $value];
			if (in_array($value, $form['restricted_access'])) $restricted_access['content'][$translation]['checked'] = true;
		}

		$return['render'] = [
			'content' => [
				[
					[
						[
							'type' => 'datalist',
							'content' => array_values(array_unique($formdatalist)),
							'attributes' => [
								'id' => 'forms'
							]
						], [
							'type' => 'datalist',
							'content' => array_values(array_unique($componentdatalist)),
							'attributes' => [
								'id' => 'components'
							]
						], [
							'type' => 'select',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_forms_select'),
								'onchange' => "api.form('get', 'form_editor', this.value)"
							],
							'content' => $formoptions
						], [
							'type' => 'search',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_forms'),
								'list' => 'forms',
								'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'form_editor', this.value); return false;}"
							]
						]
					],[
						[
							'type' => 'select',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_forms_all'),
								'onchange' => "api.form('get', 'form_editor', this.value)"
							],
							'content' => $alloptions
						]
					]
				], [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => LANG::GET('assemble.edit_forms_info_description')
						],
						'content' => LANG::GET('assemble.edit_forms_info_content')
					], [
						'type' => 'select',
						'attributes' => [
							'name' => LANG::GET('assemble.edit_add_component_select'),
							'onchange' => "api.form('get', 'component', this.value)"
						],
						'content' => $componentoptions
					], [
						'type' => 'search',
						'attributes' => [
							'name' => LANG::GET('assemble.edit_add_component'),
							'list' => 'components',
							'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'component', this.value); return false;}"
						]
					]
				], [
					[
						'type' => 'compose_form',
						'description' => LANG::GET('assemble.compose_form'),
						'value' => $form['name'] ? : '',
						'alias' => [
							'name' => LANG::GET('assemble.edit_form_alias'),
							'value' => $form['alias'] ? : ''
						],
						'context' => [
							'name' => LANG::GET('assemble.edit_form_context'),
							'content' => $contextoptions,
							'hint' => LANG::GET('assemble.edit_form_context_hint')
						],
						'hint' => ($form['name'] ? LANG::GET('assemble.compose_component_author', [':author' => $form['author'], ':date' => substr($form['date'], 0, -3)]) . '<br />' : LANG::GET('assemble.compose_component_name_hint')) .
						($dependedbundles ? LANG::GET('assemble.compose_form_bundle_dependencies', [':bundles' => implode(',', $dependedbundles)]) . '<br />' : '') .
						($dependedcomponents ? LANG::GET('assemble.compose_form_component_dependencies', [':components' => implode(',', $dependedcomponents)]) . '<br />' : '')
						,
						'hidden' => $form['name'] ? intval($form['hidden']) : 1,
						'approve' => $approve,
						'regulatory_context' => $regulatory_context ? : ' ',
						'permitted_export' => $permitted_export,
						'restricted_access' => $restricted_access
					]
				], [
					[
						'type' => 'trash',
						'description' => LANG::GET('assemble.edit_trash')
					]
				]
			]
		];
		if ($form['name'] && (!PERMISSION::fullyapproved('formapproval', $form['approval'])))
			$return['render']['content'][count($return['render']['content']) - 2][] = [
				[
					'type' => 'deletebutton',
					'attributes' => [
						'value' => LANG::GET('assemble.edit_delete'),
						'onpointerup' => "api.form('delete', 'form', " . $form['id'] . ")" 
					]
				]
			];

		// add used components to response
		if (array_key_exists('content', $form)) {
			$return['render']['components'] = [];
			foreach(explode(',', $form['content']) as $usedcomponent) {
				// get latest approved by name
				$component = $this->latestApprovedName('form_component_get_by_name', $usedcomponent);
				if ($component){
					$component['content'] = json_decode($component['content'], true);
					$component['content']['name'] = $usedcomponent;
					$component['content']['hidden'] = boolval(intval($component['hidden']));
					$return['render']['components'][] = $component['content'];
				}
			}
		}
		if ($form['name']) $return['header'] = $form['name'];
		$this->response($return);
	}
	
	/**
	 *   _     _           _                                 _
	 *  | |___| |_ ___ ___| |_ ___ ___ ___ ___ ___ _ _ ___ _| |___ ___ _____ ___
	 *  | | .'|  _| -_|_ -|  _| .'| . | . |  _| . | | | -_| . |   | .'|     | -_|
	 *  |_|__,|_| |___|___|_| |__,|  _|  _|_| |___|\_/|___|___|_|_|__,|_|_|_|___|
	 *                            |_| |_|
	 * returns the latest approved form, component by name from query
	 * @param string $query as defined within sqlinterface
	 * @param string $name
	 * @return array|bool either query row or false
	 */
	private function latestApprovedName($query = '', $name = ''){
		// get latest approved by name
		$element = [];
		$elements = SQLQUERY::EXECUTE($this->_pdo, $query, [
			'values' => [
				':name' => $name
			]
		]);
		foreach ($elements as $element){
			if (PERMISSION::fullyapproved('formapproval', $element['approval'])) return $element;
		}
		return false;
	}
}
?>
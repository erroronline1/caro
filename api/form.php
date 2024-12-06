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
require_once('./_pdf.php');

class FORM extends API {
   // processed parameters for readability
   public $_requestedMethod = REQUEST[1];
   private $_requestedID = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedID = isset(REQUEST[2]) ? REQUEST[2] : null;
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
				$time = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
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

				$pending_approvals = PERMISSION::pending('formapproval', $approve['approval']);
				if (SQLQUERY::EXECUTE($this->_pdo, 'form_put_approve', [
					'values' => [
						':id' => $approve['id'],
						':approval' => json_encode($approve['approval']) ? : ''
					]
				])) $this->response([
						'response' => [
							'msg' => LANG::GET('assemble.approve_saved') . "<br />". ($pending_approvals ? LANG::GET('assemble.approve_pending', [':approvals' => implode(', ', array_map(Fn($permission) => LANGUAGEFILE['permissions'][$permission], $pending_approvals))]) : LANG::GET('assemble.approve_completed')),
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
				ksort($componentselection);
				ksort($formselection);
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
								if (isset($sub['attributes'])){
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

					$formproperties = LANG::GET('assemble.compose_component_author', [':author' => $approve['author'], ':date' => substr($approve['date'], 1, -3)]);
					if ($approve['alias']) $formproperties .= "\n" . LANG::GET('assemble.edit_form_alias') . ': ' . $approve['alias'];
					if ($approve['regulatory_context']) $formproperties .= "\n" . LANG::GET('assemble.compose_form_regulatory_context') . ': ' . implode(', ', array_map(Fn($context) => LANGUAGEFILE['regulatory'][$context], explode(',', $approve['regulatory_context'])));
					if ($approve['restricted_access']) $formproperties .= "\n" . LANG::GET('assemble.edit_form_restricted_access') . ': ' . implode(', ', array_map(Fn($context) => LANGUAGEFILE['permissions'][$context], explode(',', $approve['restricted_access'])));
					if ($approve['permitted_export']) $formproperties .= "\n" . LANG::GET('assemble.edit_form_permitted_export');

					array_push($return['render']['content'], 
						[
							[
								'type' => 'hr'
							]
						], [
							[
								'type' => 'textsection',
								'content' => $formproperties
							],							
							[
								'type' => 'checkbox',
								'content' => $approvalposition,
								'attributes' => [
									'name' => LANG::GET('assemble.approve_as_select')
								]
							]
						]
					);
					if (PERMISSION::permissionFor('formcomposer')) {
						array_push($return['render']['content'][count($return['render']['content']) -1], [
							[
								'type' => 'button',
								'attributes' => [
									'value' => LANG::GET('assemble.edit_existing'),
									'type' => 'button',
									'onpointerup' => "api.form('get', '" . ($approve['context'] === 'component' ? 'component' : 'form') . "_editor', " . $approve['id'] . ")"
								]
							]
						]);
					}

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
					':unit' => null,
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
							':unit' => $exists['unit'],
							':author' => $exists['author'],
							':content' => $exists['content'],
							':hidden' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('assemble.edit_bundle_hidden')) === LANG::PROPERTY('assemble.edit_bundle_hidden_hidden') ? json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_currentdate->format('Y-m-d H:i:s')]) : null,
							':approval' => $exists['approval'],
							':regulatory_context' => '',
							':permitted_export' => null,
							':restricted_access' => null,
							':id' => $exists['id'],
						]
					])) $this->response([
						'response' => [
							'name' => $bundle[':name'],
							'msg' => LANG::GET('assemble.edit_bundle_saved', [':name' => $bundle[':name']]),
							'type' => 'success'
						]]);	
				}

				foreach(CONFIG['forbidden']['names'] as $pattern){
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
					'unit' => '',
					'date' => '',
					'author' => '',
					'content' => '',
					'hidden' => NULL
				];
				if($this->_requestedID && $this->_requestedID !== 'false' && !$bundle['name'] && $this->_requestedID !== '0') $return['response'] = ['msg' => LANG::GET('texttemplate.error_template_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing bundle lists
				$bundles = SQLQUERY::EXECUTE($this->_pdo, 'form_bundle_datalist');
				$hidden = [];
				foreach($bundles as $key => $row) {
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
						if (!isset($options[$row['name']]) && !in_array($row['name'], $hidden)) {
							$bundledatalist[] = $row['name'];
							$options[$row['name']] = ($row['name'] == $bundle['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
						}
						$alloptions[$row['name'] . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => substr($row['date'], 0, -3)])] = ($row['name'] == $bundle['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
				}
				ksort($options);
				ksort($alloptions);
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
				ksort($insertform);

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
					if ($bundle['hidden']) {
						$bundle['hidden'] = json_decode($bundle['hidden'], true);
						$hidden['content'][LANG::GET('assemble.edit_bundle_hidden_hidden')]['checked'] = true;
						$hidden['hint'] .= ' ' . LANG::GET('assemble.edit_hidden_set', [':name' => $bundle['hidden']['name'], ':date' => $bundle['hidden']['date']]);
					}
					array_push($return['render']['content'][1], $hidden);
				}

				$this->response($return);
				break;
		}
	}
	
	/**
	 *   _             _ _         
	 *  | |_ _ _ ___ _| | |___ ___ 
	 *  | . | | |   | . | | -_|_ -|
	 *  |___|___|_|_|___|_|___|___|
	 * 
	 */
	public function bundles(){
		$bd = SQLQUERY::EXECUTE($this->_pdo, 'form_bundle_datalist');
		$hidden = $bundles = [];
		foreach($bd as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if ($this->_requestedID) similar_text($this->_requestedID, $row['name'], $percent);
			if (!in_array($row['name'], $hidden) && (!$this->_requestedID || $percent >= CONFIG['likeliness']['file_search_similarity'])) {
				if (($forms = $row['content'] ? explode(',', $row['content']) : false) !== false){
					if (!isset($bundles[$row['name']])) $bundles[$row['name']] = [];
					foreach ($forms as $key => $formname){
						// recurring queries to make sure linked forms are permitted
						if ($form = $this->latestApprovedName('form_form_get_by_name', $formname))
							$bundles[$row['name']][$form['name']] = ['href' => "javascript:api.record('get', 'form', '" . $form['name'] . "')", 'data-filtered' => $row['id']];
					}
				}
			}
		}
		
		$return['render'] = ['content' => [
			[
				[
					'type' => 'datalist',
					'content' => array_keys($bundles),
					'attributes' => [
						'id' => 'bundles'
					]
				], [
					'type' => 'filtered',
					'attributes' => [
						'name' => LANG::GET('assemble.form_filter'),
						'list' => 'bundles',
						'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'bundles', this.value); return false;}",
						'onblur' => "api.form('get', 'bundles', this.value); return false;",
						'value' => $this->_requestedID ? : ''
					]
				]
			]
		]];
		foreach ($bundles as $bundle => $list){
			$return['render']['content'][] = [
				'type' => 'links',
				'description' => $bundle,
				'content' => $list
			];
		}
		$this->response($return);
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
				$component_approve = array_search($component['approve'], LANGUAGEFILE['units']);
				unset($component['approve']);

				/**
				 * uploads files and populates component image widgets with the final path and file name
				 * @param array $content payload $component['content]
				 * @param string $component_name passed for scope
				 * @param string $timestamp YmdHis passed for scope
				 * 
				 * @return array $content altered
				 */
				function fileupload($content, $component_name, $timestamp){
					// recursively replace images with actual $_FILES content according to content nesting
					if (isset($_FILES['composedComponent_files'])){
						$uploads = UTILITY::storeUploadedFiles(['composedComponent_files'], UTILITY::directory('component_attachments'), [$component_name . '_' . $timestamp]);
						$uploaded_files = [];
						foreach($uploads as $path){
							UTILITY::alterImage($path, CONFIG['limits']['form_image'], UTILITY_IMAGE_REPLACE);
							// retrieve actual filename with prefix dropped to compare to upload filename
							// boundary is underscore, actual underscores within uploaded file name will be reinserted
							$filename = implode('_', array_slice(explode('_', pathinfo($path)['basename']) , 2));
							$uploaded_files[$filename] = substr($path, 1);
						}
						function replace_images($element, $uploaded_filearray){
							$result = [];
							foreach($element as $sub){
								if (array_is_list($sub)){
									$result[] = replace_images($sub, $uploaded_filearray);
								} else {
									if ($sub['type'] === 'image'){
										preg_match_all('/[\w\s\d\.]+/m', $sub['attributes']['name'], $fakefilename);
										$filename = $fakefilename[0][count($fakefilename[0])-1];
										if ($filename && isset($uploaded_filearray[$filename])){ // replace only if $_FILES exist, in case of updates, where no actual file has been submitted
											$sub['attributes']['name'] = $filename;
											$sub['attributes']['url'] = $uploaded_filearray[$filename];
										}
									}
									$result[] = $sub;
								}
							}
							return $result;
						}
						$content = replace_images($content, $uploaded_files);
					}
					return $content;
				}

				// recursively scan for images within content
				function usedImages($element, $result = []){
					foreach($element as $sub){
						if (array_is_list($sub)){
							array_push($result, ...usedImages($sub, $result));
						} else {
							if (isset($sub['type']) && $sub['type'] === 'image')
								$result[] = '.' . $sub['attributes']['url'];
						}
					}
					return $result;
				}
				
				// select latest form by name
				$exists = SQLQUERY::EXECUTE($this->_pdo, 'form_component_get_by_name', [
					'values' => [
						':name' => $component_name
					]
				]);
				$exists = $exists ? $exists[0] : ['approval' => null];
				$approved = PERMISSION::fullyapproved('formapproval', $exists['approval']);

				if (isset($exists['id'])){ 
					if (!$approved) {
						// update anything, delete unused images, reset approval
						$component['content'] = fileupload($component['content'], $exists['name'], $exists['date']);

						$former_images = array_unique(usedImages(json_decode($exists['content'], true)));
						$new_images = array_unique(usedImages($component['content']));
						foreach(array_diff($former_images, $new_images) as $path) UTILITY::delete($path);

						if (SQLQUERY::EXECUTE($this->_pdo, 'form_put', [
							'values' => [
								':alias' => '',
								':context' => 'component',
								':unit' => $component_approve ? : null,
								':author' => $_SESSION['user']['name'],
								':content' => json_encode($component),
								':hidden' => $component_hidden ? json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_currentdate->format('Y-m-d H:i:s')]) : null,
								':approval' => null,
								':regulatory_context' => '',
								':permitted_export' => null,
								':restricted_access' => null,
								':id' => $exists['id'],
							]
						])) $this->response([
								'response' => [
									'name' => $exists['name'],
									'msg' => LANG::GET('assemble.edit_component_saved', [':name' => $exists['name']]),
									'type' => 'success'
								]]);
						else $this->response([
							'response' => [
								'name' => false,
								'msg' => LANG::GET('assemble.edit_component_not_saved'),
								'type' => 'error'
							]]);
					}
					if ($approved && json_decode($exists['content'], true) == $component) {
						// update component properties as long as the content remains unchanged
						if (SQLQUERY::EXECUTE($this->_pdo, 'form_put', [
							'values' => [
								':alias' => '',
								':context' => 'component',
								':unit' => $component_approve ? : null,
								':author' => $exists['author'],
								':content' => $exists['content'],
								':hidden' => $component_hidden ? json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_currentdate->format('Y-m-d H:i:s')]) : null,
								':approval' => $exists['approval'],
								':regulatory_context' => '',
								':permitted_export' => null,
								':restricted_access' => null,
								':id' => $exists['id'],
							]
						])) $this->response([
								'response' => [
									'name' => $exists['name'],
									'msg' => LANG::GET('assemble.edit_component_saved', [':name' => $exists['name']]),
									'type' => 'success'
								]]);
						else $this->response([
							'response' => [
								'name' => false,
								'msg' => LANG::GET('assemble.edit_component_not_saved'),
								'type' => 'error'
							]]);
					}
				}
				// until here the component has not existed, or the content of an approved component has been changed resulting in a new version

				// if not updated check if approve is set, not earlier
				if (!$component_approve) $this->response(['response' => ['msg' => LANG::GET('assemble.edit_component_not_saved_missing'), 'type' => 'error']]);

				foreach(CONFIG['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $component_name, $matches)) $this->response(['response' => ['msg' => LANG::GET('assemble.error_forbidden_name', [':name' => $component_name]), 'type' => 'error']]);
				}

				$component['content'] = fileupload($component['content'], $component_name, $this->_currentdate->format('YmdHis'));
				if (SQLQUERY::EXECUTE($this->_pdo, 'form_post', [
					'values' => [
						':name' => $component_name,
						':alias' => '',
						':context' => 'component',
						':unit' => $component_approve,
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
				// recursively delete images
				function deleteImages($element){
					foreach($element as $sub){
						if (array_is_list($sub)){
							deleteImages($sub);
						} else {
							if (isset($sub['type']) && $sub['type'] === 'image')
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
	 *                                     _               _ _ _           
	 *   ___ ___ _____ ___ ___ ___ ___ ___| |_       ___ _| |_| |_ ___ ___ 
	 *  |  _| . |     | . | . |   | -_|   |  _|     | -_| . | |  _| . |  _|	
	 *  |___|___|_|_|_|  _|___|_|_|___|_|_|_|  _____|___|___|_|_| |___|_|  
	 *                |_|                     |_____|                      
	 */
	public function component_editor(){
		if (!PERMISSION::permissionFor('formcomposer')) $this->response([], 401);
		$componentdatalist = [];
		$options = [];
		$alloptions = [];
		$return = [];
		
		// get selected component
		if ($this->_requestedID == '0' || intval($this->_requestedID)){
			$component = SQLQUERY::EXECUTE($this->_pdo, 'form_get', [
				'values' => [
					':id' => $this->_requestedID
				]
			]);
			$component = $component ? $component[0] : null;
			if (!$component) $component = [
				'id' => '',
				'name' => '',
				'approval' => null,
				'unit' => null
			];
		} else {
			if (!$component = $this->latestApprovedName('form_component_get_by_name', $this->_requestedID)) $component = [
				'id' => '',
				'name' => '',
				'approval' => null,
				'unit' => null
			];
		}
		if ($this->_requestedID && $this->_requestedID !== 'false' && !$component['name'] && $this->_requestedID !== '0') $return['response'] = ['msg' => LANG::GET('assemble.error_component_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

		// prepare existing component lists, sorted by units
		foreach(array_keys(LANGUAGEFILE['units']) as $unit){
			$options[$unit] = ['...' . LANG::GET('assemble.edit_existing_components_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
			$alloptions[$unit] = ['...' . LANG::GET('assemble.edit_existing_components_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		}

		$components = SQLQUERY::EXECUTE($this->_pdo, 'form_component_datalist');
		$hidden = [];
		foreach($components as $row) {
			$row['unit'] = $row['unit'] ? : 'common';
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!isset($options[$row['unit']][$row['name']]) && !in_array($row['name'], $hidden) && PERMISSION::fullyapproved('formapproval', $row['approval'])) {
				$componentdatalist[] = $row['name'];
				$options[$row['unit']][$row['name']] = ($row['name'] == $component['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
			}
			$approved = PERMISSION::fullyapproved('formapproval', $row['approval']) ? LANG::GET('assemble.approve_approved') : LANG::GET('assemble.approve_unapproved');
			$hidden_set = $row['hidden'] ? ' - ' . LANG::GET('assemble.edit_component_form_hidden_hidden') : '';
			$alloptions[$row['unit']][$row['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => substr($row['date'], 0, -3)]) . ' - ' . $approved . $hidden_set] = ($row['name'] == $component['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
		}
		// delete empty selections, order the rest and create remaining selections by unit for easier access
		$options_selection = $alloptions_selection = [];
		foreach($options as $unit => $components){
			if (count($components) < 2) {
				continue;
			}
			ksort($components);
			$options_selection[] = [
				'type' => 'select',
				'attributes' => [
					'name' => LANGUAGEFILE['units'][$unit],
					'onchange' => "api.form('get', 'component_editor', this.value)"
				],
				'content' => $components
			];
		}
		foreach($alloptions as $unit => $components){
			if (count($components) < 2) {
				continue;
			}
			ksort($components);
			$alloptions_selection[] = [
				'type' => 'select',
				'attributes' => [
					'name' => LANGUAGEFILE['units'][$unit],
					'onchange' => "api.form('get', 'component_editor', this.value)"
				],
				'content' => $components
			];
		}

		// load approved forms for occasional linking
		// check for dependencies in forms
		$approvedforms = $dependedforms = [];
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		$hidden = [];
		foreach($fd as $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden)) $approvedforms[$row['name']] = []; // prepare for selection
			if (isset($component['content']) && !in_array($row['name'], $dependedforms) && !in_array($row['name'], $hidden) && in_array($component['name'], explode(',', $row['content']))) {
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
			$approve['content'][$value] = $component['unit'] === $key ? ['selected' => true] : [];
		}

		$pending_approvals = PERMISSION::pending('formapproval', $component['approval']);
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
						],
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_components_select'),
							],
						],
						...$options_selection,
						[
							'type' => 'search',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_forms'),
								'list' => 'components',
								'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'component_editor', this.value); return false;}"
							]
						]
					],[
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_components_all'),
							],
						],
						...$alloptions_selection
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
					'value' => $component['name'],
					'hint' => ($component['name'] ? LANG::GET('assemble.compose_component_author', [':author' => $component['author'], ':date' => substr($component['date'], 0, -3)]) . '\n' : LANG::GET('assemble.compose_component_name_hint')) .
						($pending_approvals ? LANG::GET('assemble.approve_pending', [':approvals' => implode(', ', array_map(Fn($permission) => LANGUAGEFILE['permissions'][$permission], $pending_approvals))]) : LANG::GET('assemble.approve_completed')) . '\n \n' .
						($dependedforms ? LANG::GET('assemble.compose_component_form_dependencies', [':forms' => implode(',', $dependedforms)]) : ''),
					'hidden' => $component['name'] ? json_decode($component['hidden'] ? : '', true) : null,
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
						'value' => LANG::GET('assemble.edit_component_delete'),
						'onpointerup' => "api.form('delete', 'component', " . $component['id'] . ")" 
					]
				]
			];

		if (isset($component['content'])) $return['render']['component'] = json_decode($component['content']);
		if ($component['name']) $return['header'] = $component['name'];
		$this->response($return);
	}
	
	/**
	 *                       _ 
	 *   ___ _ _ ___ ___ ___| |_
	 *  | -_|_'_| . | . |  _|  _|
	 *  |___|_,_|  _|___|_| |_| 
	 *          |_|
	 */
	public function export(){
		$form_id = $identifier = $context = null;
		if ($form_id = UTILITY::propertySet($this->_payload, '_form_id')) unset($this->_payload->_form_id);
		if ($context = UTILITY::propertySet($this->_payload, '_context')) unset($this->_payload->_context);
		if ($record_type = UTILITY::propertySet($this->_payload, 'DEFAULT_' . LANG::PROPERTY('record.record_type_description'))) unset($this->_payload->{'DEFAULT_' . LANG::PROPERTY('record.record_type_description')});
		if ($entry_date = UTILITY::propertySet($this->_payload, 'DEFAULT_' . LANG::PROPERTY('record.record_date'))) unset($this->_payload->{'DEFAULT_' . LANG::PROPERTY('record.record_date')});
		if ($entry_time = UTILITY::propertySet($this->_payload, 'DEFAULT_' . LANG::PROPERTY('record.record_time'))) unset($this->_payload->{'DEFAULT_' . LANG::PROPERTY('record.record_time')});

		// used by audit for export of outdated forms
		if ($maxFormTimestamp = UTILITY::propertySet($this->_payload, '_maxFormTimestamp')) unset($this->_payload->_maxFormTimestamp);
		else $maxFormTimestamp = $this->_currentdate->format('Y-m-d H:i:s');

		$form = SQLQUERY::EXECUTE($this->_pdo, 'form_get', [
			'values' => [
				':id' => $form_id
			]
		]);
		$form = $form ? $form[0] : null;
		if (!PERMISSION::permissionFor('formexport') && !$form['permitted_export'] && !PERMISSION::permissionIn($form['restricted_access'])) $this->response([], 401);
		if (!$form || $form['date'] >= $maxFormTimestamp) $this->response([], 409);

		$entry_timestamp = $entry_date . ' ' . $entry_time;
		if (strlen($entry_timestamp) > 16) { // yyyy-mm-dd hh:ii
			$entry_timestamp = $this->_currentdate->format('Y-m-d H:i');
		}

		foreach($this->_payload as $key => &$value){
			if (substr($key, 0, 12) === 'IDENTIFY_BY_'){
				$identifier = $value;
				if (gettype($identifier) !== 'string') $identifier = ''; // empty value is passed as array by frontend
				unset ($this->_payload->$key);
				try {
					
					$possibledate = substr($identifier, -16);
					new DateTime($possibledate);
				}
				catch (Exception $e){
					$identifier .= ' ' . $entry_timestamp;
				}
			}
			if (gettype($value) === 'array') $value = trim(implode(' ', $value));
			/////////////////////////////////////////
			// BEHOLD! unsetting value==on relies on a prepared formdata/_payload having a dataset containing all selected checkboxes
			////////////////////////////////////////
			if (!$value || $value == 'on') unset($this->_payload->$key);
		}
		if (!$identifier) $identifier = in_array($form['context'], array_keys(LANGUAGEFILE['formcontext']['identify'])) ? LANG::GET('assemble.form_export_identifier'): null;
		$summary = [
			'filename' => preg_replace('/' . CONFIG['forbidden']['names'][0] . '/', '', $form['name'] . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => $identifier,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $form['name'],
			'date' => LANG::GET('assemble.form_export_exported', [':version' => substr($form['date'], 0, -3), ':date' => $this->_currentdate->format('y-m-d H:i')])
		];

		function enumerate($name, $enumerate = [], $number = 1){
			if (isset($enumerate[$name])) $enumerate[$name] += $number;
			else $enumerate[$name] = $number;	
			return $enumerate;
		}

		function printable($element, $payload, $enumerate = []){
			$content = ['content' => [], 'images' => [], 'fillable' => false];
			foreach($element as $subs){
				if (!isset($subs['type'])){
					$subcontent = printable($subs, $payload, $enumerate);
					foreach($subcontent['enumerate'] as $name => $number){
						$enumerate = enumerate($name, $enumerate,  $number); // add from recursive call
					}
					$content['content'] = array_merge($content['content'], $subcontent['content']);
					$content['images'] = array_merge($content['images'], $subcontent['images']);
					$content['fillable'] = $subcontent['fillable'];
				}
				else {
					if (in_array($subs['type'], ['identify'])) continue;
					if (in_array($subs['type'], ['image', 'links'])) {
						$name = $subs['description'];
					}
					if (in_array($subs['type'], ['formbutton'])) {
						$name = $subs['attributes']['value'];
					}
					if (in_array($subs['type'], ['calendarbutton'])) {
						$name = LANG::GET('assemble.form_export_element.' . $subs['type']);
					}
					else $name = $subs['attributes']['name'];
					$enumerate = enumerate($name, $enumerate); // enumerate proper names, checkbox gets a generated payload with chained checked values by default
					$originName = $name;
					$postname = str_replace(' ', '_', $name);
					if ($enumerate[$name] > 1) {
						$postname .= '(' . $enumerate[$name] . ')'; // payload variable name
						$name .= '(' . $enumerate[$name] . ')'; // multiple similar form field names -> for fixed component content, not dynamic created multiple fields
					}
					if (isset($subs['attributes']['required'])) $name .= ' *';
					elseif (isset($subs['content']) && gettype($subs['content']) === 'array'){
						foreach($subs['content'] as $key => $attributes) {
							if (!$attributes) break;
							if (isset($attributes['required'])) {
								$name .= ' *';
								break;
							}
						}
					}

					if (!in_array($subs['type'], ['textsection', 'image', 'links', 'formbutton'])) $content['fillable'] = true;
					if (in_array($subs['type'], ['radio', 'checkbox', 'select'])){
						$content['content'][$name] = ['type' => 'selection', 'value' => []];
						foreach($subs['content'] as $key => $v){
							if ($key === '...') continue;
							$enumerate = enumerate($key, $enumerate); // enumerate checkbox names for following elements by same name
							$selected = '';

							// dynamic multiple select
							$dynamicMultiples = preg_grep('/' . preg_quote(str_replace(' ', '_', $originName), '/') . '\(\d+\)/m', array_keys((array)$payload));
							foreach($dynamicMultiples as $matchkey => $submitted){
								if ($key == UTILITY::propertySet($payload, $submitted)) $selected = '_____';
							}
	
							if (UTILITY::propertySet($payload, $postname) && (
								($subs['type'] !== 'checkbox' && $key == UTILITY::propertySet($payload, $postname)) ||
								($subs['type'] === 'checkbox' && in_array($key, explode(' | ', UTILITY::propertySet($payload, $postname))))
								)) $selected = '_____';
							$content['content'][$name]['value'][] = $selected . $key;
						}
					}
					elseif ($subs['type'] === 'textsection'){
						$content['content'][$name] = ['type' => 'textsection', 'value' => isset($subs['content']) ? $subs['content'] : ''];
					}
					elseif ($subs['type'] === 'textarea'){
						$content['content'][$name] = ['type' => 'multiline', 'value' => UTILITY::propertySet($payload, $postname) ? : ''];
					}
					elseif ($subs['type'] === 'signature'){
						$content['content'][$name] = ['type' => 'multiline', 'value' => ''];
					}
					elseif ($subs['type'] === 'image'){
						$content['content'][$name] = ['type'=> 'image', 'value' => $subs['attributes']['url']];
						$file = pathinfo($subs['attributes']['url']);
						if (in_array($file['extension'], ['jpg', 'jpeg', 'gif', 'png'])) {
							$content['images'][] = $subs['attributes']['url'];
						}
					}
					elseif ($subs['type'] === 'range'){
						$content['content'][$name] = ['type' => 'textsection', 'value' => '(' . (isset($subs['attributes']['min']) ? $subs['attributes']['min'] : 0) . ' - ' . (isset($subs['attributes']['min']) ? $subs['attributes']['max'] : 100) . ') ' . (UTILITY::propertySet($payload, $postname) ? : '')];
					}
					elseif (in_array($subs['type'], ['photo', 'file'])){
						$content['content'][$name] = ['type' => 'textsection', 'value' => LANG::GET('assemble.form_export_element.' . $subs['type'])];
					}
					elseif ($subs['type'] === 'links'){
						$content['content'][$name] = ['type' => 'textsection', 'value' => ''];
						foreach(array_keys($subs['content']) as $link) $content['content'][$name]['value'] .= $link . "\n";
					}
					elseif ($subs['type'] === 'formbutton'){
						$content['content'][LANG::GET('assemble.form_export_element.' . $subs['type']). ': ' . $name] = ['type' => 'textsection', 'value' => ''];
					}
					elseif ($subs['type'] === 'calendarbutton'){
						$content['content'][$name] = ['type' => 'textsection', 'value' => ''];
					}
					else {
						if (isset($name)) $content['content'][$name] = ['type' => 'singleline', 'value'=> UTILITY::propertySet($payload, $postname) ? : ''];
						$dynamicMultiples = preg_grep('/' . preg_quote(str_replace(' ', '_', $originName), '/') . '\(\d+\)/m', array_keys((array)$payload));
						foreach($dynamicMultiples as $matchkey => $submitted){
							$content['content'][$submitted] = ['type' => 'singleline', 'value'=> UTILITY::propertySet($payload, $submitted) ? : ''];
						}
					}
				}
			}
			$content['enumerate'] = $enumerate;
			return $content;
		};

		$componentscontent = [];
		$enumerate = [];
		$fillable = false;
		foreach(explode(',', $form['content']) as $usedcomponent) {
			$component = $this->latestApprovedName('form_component_get_by_name', $usedcomponent, $maxFormTimestamp);
			if (!$component) continue;
			$component['content'] = json_decode($component['content'], true);

			$printablecontent = printable($component['content']['content'], $this->_payload, $enumerate);
			$summary['content'] = array_merge($summary['content'], $printablecontent['content']);
			$summary['images'] = array_merge($summary['images'], $printablecontent['images']);
			$enumerate = $printablecontent['enumerate'];
			if ($printablecontent['fillable']) $fillable = true;
		}
		if ($fillable){
			if (in_array($form['context'], ['casedocumentation'])) {
				$type = ['type' => 'selection', 'value' => []];
				foreach (LANGUAGEFILE['record']['record_type'] as $key => $value){
					$type['value'][] = ($record_type === $key ? '_____': '') . $value;
				}
				$summary['content'] = array_merge([LANG::GET('record.record_type_description') . (CONFIG['application']['require_record_type_selection'] ? ' *' : '') => $type], $summary['content']);
			}
			$summary['content'] = array_merge(['' => ['type' => 'text', 'value' => LANG::GET('assemble.required_asterisk')], LANG::GET('assemble.form_export_by') . ' *' => [
				'type' => 'text',
				'value' => ''
			]], $summary['content']);
		}
		$summary['content'] = [' ' => $summary['content']];
		$summary['images'] = [' ' => $summary['images']];

		$downloadfiles[LANG::GET('assemble.form_export')] = [
			'href' => PDF::formsPDF($summary)
		];
		$this->response([
			'render' => [
				[
					'type' => 'links',
					'description' =>  LANG::GET('assemble.form_export_proceed'),
					'content' => $downloadfiles
				]
			],
		]);
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
				foreach(CONFIG['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $this->_payload->name, $matches)) $this->response(['response' => ['msg' => LANG::GET('assemble.error_forbidden_name', [':name' => $this->_payload->name]), 'type' => 'error']]);
				}

				// recursively check for identifier
				function check4identifier($element, $hasidentifier = false){
					if ($hasidentifier) return true;
					foreach($element as $sub){
						if (array_is_list($sub)){
							$hasidentifier = check4identifier($sub, $hasidentifier);
						} else {
							if (isset($sub['type']) && $sub['type'] === 'identify') $hasidentifier = true;
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
				
				// select latest form by name
				$exists = SQLQUERY::EXECUTE($this->_pdo, 'form_form_get_by_name', [
					'values' => [
						':name' => $this->_payload->name
					]
				]);
				$exists = $exists ? $exists[0] : ['approval' => null];
				$approved = PERMISSION::fullyapproved('formapproval', $exists['approval']);

				$this->_payload->approve = array_search($this->_payload->approve, LANGUAGEFILE['units']);

				if (isset($exists['id'])){ 
					if (!$approved) {
						// update anything, reset approval
						if (SQLQUERY::EXECUTE($this->_pdo, 'form_put', [
							'values' => [
								':alias' => gettype($this->_payload->alias) === 'array' ? implode(' ', $this->_payload->alias) : $this->_payload->alias,
								':context' => $this->_payload->context,
								':unit' => $this->_payload->approve ? : null,
								':author' => $_SESSION['user']['name'],
								':content' => implode(',', $this->_payload->content),
								':hidden' => boolval(intval($this->_payload->hidden)) ? json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_currentdate->format('Y-m-d H:i:s')]) : NULL,
								':approval' => null,
								':regulatory_context' => implode(',', $regulatory_context),
								':permitted_export' => $this->_payload->permitted_export ? : 0,
								':restricted_access' => $restricted_access ? implode(',', $restricted_access) : NULL,
								':id' => $exists['id'],
							]
						])) $this->response([
								'response' => [
									'name' => $this->_payload->name,
									'msg' => LANG::GET('assemble.edit_form_saved', [':name' => $this->_payload->name]),
									'type' => 'success'
								]]);
						else $this->response([
							'response' => [
								'name' => false,
								'msg' => LANG::GET('assemble.edit_form_not_saved'),
								'type' => 'error'
							]]);
					}
					if ($approved && $exists['content'] == implode(',', $this->_payload->content)) {
						// update form properties as long as the content remains unchanged
						if (SQLQUERY::EXECUTE($this->_pdo, 'form_put', [
							'values' => [
								':alias' => gettype($this->_payload->alias) === 'array' ? implode(' ', $this->_payload->alias) : $this->_payload->alias,
								':context' => $this->_payload->context,
								':unit' => $this->_payload->approve ? : null,
								':author' => $exists['author'],
								':content' => $exists['content'],
								':hidden' =>  boolval(intval($this->_payload->hidden)) ? json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_currentdate->format('Y-m-d H:i:s')]) : NULL,
								':approval' => $exists['approval'],
								':regulatory_context' => implode(',', $regulatory_context),
								':permitted_export' => $this->_payload->permitted_export ? : 0,
								':restricted_access' => $restricted_access ? implode(',', $restricted_access) : NULL,
								':id' => $exists['id'],
							]
						])) $this->response([
								'response' => [
									'name' => $this->_payload->name,
									'msg' => LANG::GET('assemble.edit_form_saved', [':name' => $this->_payload->name]),
									'type' => 'success'
								]]);
						else $this->response([
							'response' => [
								'name' => false,
								'msg' => LANG::GET('assemble.edit_form_not_saved'),
								'type' => 'error'
							]]);
					}
				}
				// until here the form has not existed, or the content of an approved form has been changed resulting in a new version

				// if not updated check if approve is set, not earlier
				if (!$this->_payload->approve) $this->response(['response' => ['msg' => LANG::GET('assemble.edit_form_not_saved_missing'), 'type' => 'error']]);

				if (SQLQUERY::EXECUTE($this->_pdo, 'form_post', [
					'values' => [
						':name' => $this->_payload->name,
						':alias' => gettype($this->_payload->alias) === 'array' ? implode(' ', $this->_payload->alias): $this->_payload->alias,
						':context' => gettype($this->_payload->context) === 'array' ? '': $this->_payload->context,
						':unit' => $this->_payload->approve,
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
	 *   ___                           _ _ _           
	 *  |  _|___ ___ _____       ___ _| |_| |_ ___ ___ 
	 *  |  _| . |  _|     |     | -_| . | |  _| . |  _|
	 *  |_| |___|_| |_|_|_|_____|___|___|_|_| |___|_|  
	 *                    |_____|                      
	 *
	 */
	public function form_editor(){
		if (!PERMISSION::permissionFor('formcomposer')) $this->response([], 401);
		$formdatalist = $componentdatalist = [];
		$formoptions = [];
		$alloptions = [];
		$componentoptions = [];
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
		if (!$form) $form = [
			'name' => '',
			'alias' => '',
			'context' => '',
			'unit' => null,
			'regulatory_context' => '',
			'permitted_export' => null,
			'restricted_access' => null,
			'approval' => null
		];
		if($this->_requestedID && $this->_requestedID !== 'false' && !$form['name'] && $this->_requestedID !== '0') $return['response'] = ['msg' => LANG::GET('assemble.error_form_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

		// prepare existing forms lists, sorted by units
		foreach(array_keys(LANGUAGEFILE['units']) as $unit){
			$formoptions[$unit] = ['...' . LANG::GET('assemble.edit_existing_forms_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
			$alloptions[$unit] = ['...' . LANG::GET('assemble.edit_existing_forms_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
			$componentoptions[$unit] = ['...' => ['value' => '']];
		}

		$fd = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		$hidden = [];
		foreach($fd as $key => $row) {
			$row['unit'] = $row['unit'] ? : 'common';
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!isset($formoptions[$row['unit']][$row['name']]) && !in_array($row['name'], $hidden) && PERMISSION::fullyapproved('formapproval', $row['approval'])) {
				$formdatalist[] = $row['name'];
				$formoptions[$row['unit']][$row['name']] = ($row['name'] === $form['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
			}
			$approved = PERMISSION::fullyapproved('formapproval', $row['approval']) ? LANG::GET('assemble.approve_approved') : LANG::GET('assemble.approve_unapproved');
			$hidden_set = $row['hidden'] ? ' - ' . LANG::GET('assemble.edit_component_form_hidden_hidden') : '';
			$alloptions[$row['unit']][$row['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => substr($row['date'], 0, -3)]) . ' - ' . $approved . $hidden_set] = ($row['name'] === $form['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
		}

		// prepare existing component list of fully approved
		$cd = SQLQUERY::EXECUTE($this->_pdo, 'form_component_datalist');
		$hidden = [];
		foreach($cd as $key => $row) {
			$row['unit'] = $row['unit'] ? : 'common';
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!isset($componentoptions[$row['name']]) && !in_array($row['name'], $hidden) && PERMISSION::fullyapproved('formapproval', $row['approval'])) {
				$componentdatalist[] = $row['name'];
				$componentoptions[$row['unit']][$row['name'] . ' - ' . LANG::GET('assemble.approve_approved')] = ['value' => $row['id']];
			}
		}

		// delete empty selections, order the rest and create remaining selections by unit for easier access
		$options_selection = $alloptions_selection = $components_selection = [];
		foreach($formoptions as $unit => $components){
			if (count($components) < 2) {
				continue;
			}
			ksort($components);
			$options_selection[] = [
				'type' => 'select',
				'attributes' => [
					'name' => LANGUAGEFILE['units'][$unit],
					'onchange' => "api.form('get', 'form_editor', this.value)"
				],
				'content' => $components
			];
		}
		foreach($alloptions as $unit => $components){
			if (count($components) < 2) {
				continue;
			}
			ksort($components);
			$alloptions_selection[] = [
				'type' => 'select',
				'attributes' => [
					'name' => LANGUAGEFILE['units'][$unit],
					'onchange' => "api.form('get', 'form_editor', this.value)"
				],
				'content' => $components
			];
		}
		foreach($componentoptions as $unit => $components){
			if (count($components) < 2) {
				continue;
			}
			ksort($components);
			$components_selection[] = [
				'type' => 'select',
				'attributes' => [
					'name' => LANGUAGEFILE['units'][$unit],
					'onchange' => "if (this.value) api.form('get', 'component', this.value)"
				],
				'content' => $components
			];
		}



		// check for bundle dependencies
		$bd = SQLQUERY::EXECUTE($this->_pdo, 'form_bundle_datalist');
		$hidden = [];
		$dependedbundles = [];
		foreach($bd as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden) && 
			in_array($form['name'], explode(',', $row['content'])) && 
			!in_array($form['name'], $dependedbundles)) $dependedbundles[] = $row['name']; 
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
		ksort($contextoptions);

		// prepare unit list for approval
		$approve = [
			'hint' => LANG::GET('assemble.compose_component_approve_hint', [':roles' => implode(', ', array_map(Fn($v) => LANGUAGEFILE['permissions'][$v], PERMISSION::permissionFor('formapproval', true)))]),
			'name' => LANG::GET('assemble.compose_component_approve'),
			'content' => ['...' . LANG::GET('assemble.compose_component_approve_select_default') => ['value' => '0']]
		];
		foreach(LANGUAGEFILE['units'] as $key => $value){
			$approve['content'][$value] = $form['unit'] === $key ? ['selected' => true] : [];
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

		$pending_approvals = PERMISSION::pending('formapproval', $form['approval']);
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
							'type' => 'textsection',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_forms_select'),
							],
						],
						...$options_selection,
						[
							'type' => 'search',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_forms'),
								'list' => 'forms',
								'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'form_editor', this.value); return false;}"
							]
						]
					],[
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_forms_all'),
							],
						],
						...$options_selection
					]
				], [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => LANG::GET('assemble.edit_forms_info_description')
						],
						'content' => LANG::GET('assemble.edit_forms_info_content')
					], 
					...$components_selection,
					[
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
						'hint' => ($form['name'] ? LANG::GET('assemble.compose_component_author', [':author' => $form['author'], ':date' => substr($form['date'], 0, -3)]) . '\n' : LANG::GET('assemble.compose_component_name_hint')) .
						($pending_approvals ? LANG::GET('assemble.approve_pending', [':approvals' => implode(', ', array_map(Fn($permission) => LANGUAGEFILE['permissions'][$permission], $pending_approvals))]) : LANG::GET('assemble.approve_completed')) . '\n \n' .
						($dependedbundles ? LANG::GET('assemble.compose_form_bundle_dependencies', [':bundles' => implode(',', $dependedbundles)]) . '\n' : '') .
						($dependedcomponents ? LANG::GET('assemble.compose_form_component_dependencies', [':components' => implode(',', $dependedcomponents)]) . '\n' : '')
						,
						'hidden' => $form['name'] ? json_decode($form['hidden'] ? : '', true) : null,
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
						'value' => LANG::GET('assemble.edit_form_delete'),
						'onpointerup' => "api.form('delete', 'form', " . $form['id'] . ")" 
					]
				]
			];

		// add used components to response
		if (isset($form['content'])) {
			$return['render']['components'] = [];
			foreach(explode(',', $form['content']) as $usedcomponent) {
				// get latest approved by name
				$component = $this->latestApprovedName('form_component_get_by_name', $usedcomponent);
				if ($component){
					$component['content'] = json_decode($component['content'], true);
					$component['content']['name'] = $usedcomponent;
					$component['content']['hidden'] = json_decode($component['hidden'] ? : '', true);
					$return['render']['components'][] = $component['content'];
				}
			}
		}
		if ($form['name']) $return['header'] = $form['name'];
		$this->response($return);
	}
	
	/**
	 *   ___               ___ _ _ _
	 *  |  _|___ ___ _____|  _|_| | |_ ___ ___
	 *  |  _| . |  _|     |  _| | |  _| -_|  _|
	 *  |_| |___|_| |_|_|_|_| |_|_|_| |___|_|
	 *
	 */
	public function formfilter(){
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		$hidden = $matches = [];

		function findInComponent($element, $search){
			$found = false;
			foreach($element as $subs){
				if (!isset($subs['type'])){
					if ($found = findInComponent($subs, $search)) return true;
				}
				else {
					$comparisons = [];
					foreach (['description', 'content', 'hint'] as $property){
						if (isset($subs[$property])){
							if (is_array($subs[$property])){ // links, checkboxes, etc
								foreach(array_keys($subs[$property]) as $key) $comparisons[] = $key;
							}
							else $comparisons[] = $subs[$property];
						}
					}
					if (isset($subs['attributes'])){
						foreach (['name', 'value'] as $property){
							if (isset($subs['attributes'][$property])) $comparisons[] = $subs['attributes'][$property];
						}
					}
					foreach($comparisons as $term) {
						similar_text($search, $term, $percent);
						if (stristr($term, $search) || $percent >= CONFIG['likeliness']['file_search_similarity']) return true;
					}
				}
			}
			return $found;
		};

		foreach($fd as $row) {
			if ($row['hidden'] || !PERMISSION::permissionIn($row['restricted_access'])) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['id'], $matches) && !in_array($row['name'], $hidden)) {
				$terms = [$row['name']];
				foreach(preg_split('/[^\w\d]/', $row['alias']) as $alias) array_push($terms, $alias);
				foreach ($terms as $term){
					similar_text($this->_requestedID, $term, $percent);
					if (($percent >= CONFIG['likeliness']['file_search_similarity'] || !$this->_requestedID) && !in_array($row['id'], $matches)) {
						$matches[] = strval($row['id']);
						break;
					}
				}
				foreach(explode(',', $row['regulatory_context']) as $context) {
					if (stristr(LANG::GET('regulatory.' . $context), $this->_requestedID) !== false) {
						$matches[] = strval($row['id']);
						break;	
					}
				}
				foreach(explode(',', $row['content']) as $usedcomponent) {
					if ($component = $this->latestApprovedName('form_component_get_by_name', $usedcomponent)){
						$component['content'] = json_decode($component['content'], true);
						if (findInComponent($component['content']['content'], $this->_requestedID)) {
							$matches[] = strval($row['id']);
							break;
						}
					}
				}
			}
		}
		$this->response([
			'data' => array_values(array_unique($matches))
		]);
	}
	
	/**
	 *   ___                   
	 *  |  _|___ ___ _____ ___ 
	 *  |  _| . |  _|     |_ -|
	 *  |_| |___|_| |_|_|_|___|
	 *   
	 */
	public function forms(){
		$formdatalist = $forms = [];
		$return = [];

		// prepare existing forms lists
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'form_form_datalist');
		$hidden = [];
		foreach($fd as $key => $row) {
			if (!PERMISSION::fullyapproved('formapproval', $row['approval']) || !PERMISSION::permissionIn($row['restricted_access'])) continue;
			if ($row['hidden'] || in_array($row['context'], array_keys(LANGUAGEFILE['formcontext']['notdisplayedinrecords']))) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $formdatalist) && !in_array($row['name'], $hidden)) {
				$formdatalist[] = $row['name'];
				$forms[$row['context']][$row['name']] = ['href' => "javascript:api.record('get', 'form', '" . $row['name'] . "')", 'data-filtered' => $row['id']];
				foreach(preg_split('/[^\w\d]/', $row['alias']) as $alias) $formdatalist[] = $alias;
			}
		}
		$return['render'] = [
			'content' => [
				[
					[
						'type' => 'datalist',
						'content' => array_values(array_unique($formdatalist)),
						'attributes' => [
							'id' => 'forms'
						]
					], [
						'type' => 'filtered',
						'attributes' => [
							'name' => LANG::GET('assemble.form_filter'),
							'list' => 'forms',
							'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'formfilter', this.value); return false;}",
							'onblur' => "api.form('get', 'formfilter', this.value); return false;",
						],
						'hint' => LANG::GET('assemble.form_filter_hint')
					]
				]
			]];
		foreach ($forms as $context => $list){
			$contexttranslation = '';
			foreach (LANGUAGEFILE['formcontext'] as $formcontext => $contexts){
				if (isset($contexts[$context])){
					$contexttranslation = $contexts[$context];
					break;
				}
			}
			$return['render']['content'][] = [
				'type' => 'links',
				'description' => $contexttranslation,
				'content' => $list
			];
		}
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
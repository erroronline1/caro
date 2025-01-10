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

// add and edit document components and documents
require_once('./_pdf.php');

class DOCUMENT extends API {
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
	 * sets approval or displays documents trequiring approval
	 */
	public function approval(){
		if (!PERMISSION::permissionFor('documentapproval')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				$approveas = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('assemble.approve.as_select'));
				if (!$approveas) $this->response([ // early exit
					'response' => [
						'msg' => $this->_lang->GET('assemble.approve.not_saved'),
						'type' => 'error'
					]]);
				$approveas = explode(' | ', $approveas);

				$approve = SQLQUERY::EXECUTE($this->_pdo, 'document_get', [
					'values' => [
						':id' => $this->_requestedID
					]
				]);
				$approve = $approve ? $approve[0] : null;
				if (!$approve) $this->response([], 404); // document not found, e.g. deleted before approval

				// append passed approvals
				$approve['approval'] = $approve['approval'] ? json_decode($approve['approval'], true) : []; 
				$tobeapprovedby = PERMISSION::permissionFor('documentapproval', true);
				foreach($tobeapprovedby as $permission){
					if (array_intersect(['admin', $permission], $_SESSION['user']['permissions']) && in_array($this->_lang->GET('permissions.' . $permission), $approveas)){
						$approve['approval'][$permission] = [
							'name' => $_SESSION['user']['name'],
							'date' => $this->_currentdate->format('Y-m-d H:i')
						];
					}
				}
				require_once('notification.php');
				$notifications = new NOTIFICATION;

				// update document approval 
				if (SQLQUERY::EXECUTE($this->_pdo, 'document_put_approve', [
					'values' => [
						':id' => $approve['id'],
						':approval' => json_encode($approve['approval']) ? : ''
					]
				]) !== false) {
					$pending_approvals = PERMISSION::pending('documentapproval', $approve['approval']);
					if (!$pending_approvals){
						// set up alerts about new documents/versions
						$documents = [];
						if (in_array($approve['context'], [...array_keys($this->_lang->_USER['documentcontext']['identify']), ...array_keys($this->_lang->_USER['documentcontext']['anonymous'])])) {
							$documents[] = '<a href="javascript:void(0);" onpointerup="api.record(\'get\', \'document\', \'' . $approve['name'] . '\')">' . $approve['name'] . '</a>';
						}
						elseif ($approve['context'] === 'component') {
							// check for dependencies in documents
							$dependeddocuments = [];
							$fd = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
							$hidden = [];
							foreach($fd as $row) {
								if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
								if (isset($component['content']) && !in_array($row['name'], $dependeddocuments) && !in_array($row['name'], $hidden) && in_array($component['name'], explode(',', $row['content']))) {
									$dependeddocuments[] = $row['name'];
									$documents[] = '<a href="javascript:void(0);" onpointerup="api.record(\'get\', \'document\', \'' . $row['name'] . '\')">' . $row['name'] . '</a>';
								}
							}
						}
						if ($documents){
							// send to all users
							$users = SQLQUERY::EXECUTE($this->_pdo, 'user_get_datalist');
							$this->alertUserGroup(['user' => array_column($users, 'name')], preg_replace(['/\r/'], [''], $this->_lang->GET('assemble.approve.alert', [':documents' => implode("\n", $documents)], true)));
						}
					}
					$this->response([
						'response' => [
							'msg' => $this->_lang->GET('assemble.approve.saved') . "<br />". ($pending_approvals ? $this->_lang->GET('assemble.approve.pending', [':approvals' => implode(', ', array_map(Fn($permission) => $this->_lang->_USER['permissions'][$permission], $pending_approvals))]) : $this->_lang->GET('assemble.approve.completed')),
							'type' => 'success',
							'reload' => 'approval',
						],
						'data' => ['document_approval' => $notifications->documents()]]);
					}
				else $this->response([
					'response' => [
						'msg' => $this->_lang->GET('assemble.approve.not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$componentselection = $documentselection = $approvalposition = [];

				// prepare all unapproved elements
				$components = SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist');
				$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
				$unapproved = ['documents' => [], 'components' => []];
				$return = ['render'=> ['content' => [[]]]]; // default first nesting
				$hidden = [];
				foreach(array_merge($components, $documents) as $element){
					if ($element['context'] === 'bundle') continue;
					if ($element['hidden']) $hidden[] = $element['context'] . $element['name']; // since ordered by recent, older items will be skipped
					if (!in_array($element['context'] . $element['name'], $hidden)){
						if (!PERMISSION::fullyapproved('documentapproval', $element['approval'])) {
							switch ($element['context']){
								case 'component':
									$sort = ['unapproved' => 'components', 'selection' => 'componentselection'];
									break;
								default:
								$sort = ['unapproved' => 'documents', 'selection' => 'documentselection'];
							}						
							if (!in_array($element['name'], array_keys($unapproved[$sort['unapproved']]))){
								$unapproved[$sort['unapproved']][$element['name']] = $element['content'];
								if (PERMISSION::pending('documentapproval', $element['approval'])){
									${$sort['selection']}[$element['name']] = $this->_requestedID === $element['id'] ? ['value' => $element['id'], 'selected' => true] : ['value' => $element['id']];
								}
							}
						}
						$hidden[] = $element['context'] . $element['name']; // hide previous versions at all costs
					}
				}
				// add applicable components
				if ($componentselection) {
					$componentselection['...'] = [];
					ksort($componentselection);
					$return['render']['content'][0][] = [
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('assemble.approve.component_select'),
							'onchange' => "api.document('get', 'approval', this.value)"
						],
						'content' => $componentselection
					];
				}
				// add applicable documents
				if ($documentselection) {
					$documentselection['...'] = [];
					ksort($documentselection);
					$return['render']['content'][0][] =
					[
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('assemble.approve.document_select'),
							'onchange' => "api.document('get', 'approval', this.value)"
						],
						'content' => $documentselection
					];
				}
				if ($componentselection || $documentselection) $return['render']['content'][] = [
					[
						'type' => 'hr'
					]
				];
				else $this->response(['render' => ['content' => $this->noContentAvailable($this->_lang->GET('assemble.approve.no_approvals'))]]);

				// display selected element for review
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

					$approve = SQLQUERY::EXECUTE($this->_pdo, 'document_get', [
						'values' => [
							':id' => $this->_requestedID
						]
					]);
					$approve = $approve ? $approve[0] : null;
					if (!$approve) $this->response([], 404);

					// get remaining approval options
					foreach(PERMISSION::pending('documentapproval', $approve['approval']) as $position){
						$approvalposition[$this->_lang->GET('permissions.' . $position)] = [];
					}

					// unset requires attributes in advance to posting approval to avoid unneccessary formvalidation
					if ($approve['context'] === 'component'){
						array_push($return['render']['content'], ...unrequire(json_decode($approve['content'], true)['content'])[0]);
					}
					else {
						foreach(explode(',', $approve['content']) as $component){
							// get latest approved by name
							$cmpnnt = $this->latestApprovedName('document_component_get_by_name', $component);
							if ($cmpnnt) {
								if (!PERMISSION::fullyapproved('documentapproval', $cmpnnt['approval'])){
									$alert .= $this->_lang->GET('assemble.approve.document_unapproved_component', [':name' => $cmpnnt['name']]). '<br />';
								}
								array_push($return['render']['content'], ...unrequire(json_decode($cmpnnt['content'], true)['content'])[0]);
							}
						}
						if ($alert) $return['response'] = ['msg' => $alert, 'type' => 'info'];
					}

					// gather informal document properties
					$documentproperties = $this->_lang->GET('assemble.compose.component.component_author', [':author' => $approve['author'], ':date' => substr($approve['date'], 1, -3)]);
					if ($approve['alias']) $documentproperties .= "\n" . $this->_lang->GET('assemble.compose.document.document_alias') . ': ' . $approve['alias'];
					if ($approve['regulatory_context']) $documentproperties .= "\n" . $this->_lang->GET('assemble.compose.document.document_regulatory_context') . ': ' . implode(', ', array_map(Fn($context) => $this->_lang->_USER['regulatory'][$context], explode(',', $approve['regulatory_context'])));
					if ($approve['restricted_access']) $documentproperties .= "\n" . $this->_lang->GET('assemble.compose.document.document_restricted_access') . ': ' . implode(', ', array_map(Fn($context) => $this->_lang->_USER['permissions'][$context], explode(',', $approve['restricted_access'])));
					if ($approve['permitted_export']) $documentproperties .= "\n" . $this->_lang->GET('assemble.compose.document.document_permitted_export');

					array_push($return['render']['content'], 
						[
							[
								'type' => 'hr'
							]
						], [
							[
								'type' => 'textsection',
								'content' => $documentproperties
							],							
							[
								'type' => 'checkbox',
								'content' => $approvalposition,
								'attributes' => [
									'name' => $this->_lang->GET('assemble.approve.as_select')
								]
							]
						]
					);
					// optional revision of element if allowed (e.g. not supervisors by default)
					if (PERMISSION::permissionFor('documentcomposer')) {
						array_push($return['render']['content'][count($return['render']['content']) -1], [
							[
								'type' => 'button',
								'attributes' => [
									'value' => $this->_lang->GET('assemble.compose.edit_existing'),
									'type' => 'button',
									'onpointerup' => "api.document('get', '" . ($approve['context'] === 'component' ? 'component' : 'document') . "_editor', " . $approve['id'] . ")"
								]
							]
						]);
					}

					$return['render']['form'] = [
						'data-usecase' => 'approval',
						'action' => "javascript: api.document('put', 'approval', " . $this->_requestedID . ")",
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
	 * bundle editor
	 */
	public function bundle(){
		if (!PERMISSION::permissionFor('documentcomposer')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if ($content = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('assemble.compose.bundle.content'))) $content = implode(',', preg_split('/[\n\r]{1,}/', $content));
				else $content = '';

				// initiate bundle properties
				$bundle = [
					':name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('assemble.compose.bundle.name')),
					':alias' => '',
					':context' => 'bundle',
					':unit' => UTILITY::propertySET($this->_payload, $this->_lang->PROPERTY('assemble.compose.bundle.unit')) ? : 'common',
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
				$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_get_by_name', [
					'values' => [
						':name' => $bundle[':name']
					]
				]);
				foreach ($documents as $exists){
					break;
				}
				// update unit and hidden on latest available bundle if content remains
				if ($exists && $exists['content'] === $bundle[':content']) {
					if (SQLQUERY::EXECUTE($this->_pdo, 'document_put', [
						'values' => [
							':alias' => $exists['alias'],
							':context' => $exists['context'],
							':unit' => $bundle[':unit'],
							':author' => $exists['author'],
							':content' => $exists['content'],
							':hidden' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('assemble.compose.bundle.hidden')) === $this->_lang->PROPERTY('assemble.compose.bundle.hidden_hidden') ? json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_currentdate->format('Y-m-d H:i:s')]) : null,
							':approval' => $exists['approval'],
							':regulatory_context' => '',
							':permitted_export' => null,
							':restricted_access' => null,
							':id' => $exists['id'],
						]
					])) $this->response([
						'response' => [
							'name' => $bundle[':name'],
							'msg' => $this->_lang->GET('assemble.compose.bundle.saved', [':name' => $bundle[':name']]),
							'type' => 'success'
						]]);	
				}

				// check forbidden names
				foreach(CONFIG['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $bundle[':name'], $matches)) $this->response(['response' => ['msg' => $this->_lang->GET('assemble.render.error_forbidden_name', [':name' => $bundle[':name']]), 'type' => 'error']]);
				}
				// append bundle to database
				if (SQLQUERY::EXECUTE($this->_pdo, 'document_post', [
					'values' => $bundle
				])) $this->response([
						'response' => [
							'name' => $bundle[':name'],
							'msg' => $this->_lang->GET('assemble.compose.bundle.saved', [':name' => $bundle[':name']]),
							'type' => 'success'
						]]);
				else $this->response([
					'response' => [
						'name' => false,
						'msg' => $this->_lang->GET('assemble.compose.bundle.not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$bundledatalist = [];
				$options = ['...' . $this->_lang->GET('assemble.compose.bundle.edit_existing_bundle_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$alloptions = ['...' . $this->_lang->GET('assemble.compose.bundle.edit_existing_bundle_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$insertdocument = ['...' . $this->_lang->GET('assemble.compose.bundle.insert_default') => ['value' => ' ']];
				$return = [];

				// get selected bundle
				if (intval($this->_requestedID)){
					$bundle = SQLQUERY::EXECUTE($this->_pdo, 'document_get', [
						'values' => [
							':id' => $this->_requestedID
						]
					]);
					$bundle = $bundle ? $bundle[0] : null;
				} else {
					// get latest by name
					$bundle = [];
					$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_get_by_name', [
						'values' => [
							':name' => $this->_requestedID
						]
					]);
					foreach ($documents as $bundle){
						break;
					}
				}
				// initiate bundle properties
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
				if($this->_requestedID && $this->_requestedID !== 'false' && !$bundle['name'] && $this->_requestedID !== '0') $return['response'] = ['msg' => $this->_lang->GET('assemble.compose.bundle.not_found', [':name' => $this->_requestedID]), 'type' => 'error']; // early exit
		
				// prepare existing bundle lists
				$bundles = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist');
				$hidden = [];
				foreach($bundles as $key => $row) {
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
						if (!isset($options[$row['name']]) && !in_array($row['name'], $hidden)) {
							$bundledatalist[] = $row['name'];
							$options[$row['name']] = ($row['name'] == $bundle['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
						}
						$alloptions[$row['name'] . $this->_lang->GET('assemble.compose.component.component_author', [':author' => $row['author'], ':date' => substr($row['date'], 0, -3)])] = ($row['name'] == $bundle['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
				}
				ksort($options);
				ksort($alloptions);
				// prepare available documents lists
				// get latest approved by name
				$documents = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
				$hidden = [];
				foreach($documents as $key => $row) {
					if (!PERMISSION::fullyapproved('documentapproval', $row['approval'])) continue;
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (!in_array($row['name'], $hidden)) {
							$insertdocument[$row['name']] = ['value' => $row['name'] . "\n"];
					}
				}
				ksort($insertdocument);

				// gather applicable units
				$units = [];
				foreach ($this->_lang->_USER['units'] as $unit => $translation){
					$units[$translation] = ['value' => $unit];
					if ($bundle['unit'] == $unit) $units[$translation]['selected'] = true;
				}

				$return['render'] = [
					'form' => [
						'data-usecase' => 'bundle',
						'action' => "javascript:api.document('post', 'bundle')"],
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
										'name' => $this->_lang->GET('assemble.compose.bundle.edit_existing_bundle_select'),
										'onchange' => "api.document('get', 'bundle', this.value)"
									],
									'content' => $options
								], [
									'type' => 'search',
									'attributes' => [
										'name' => $this->_lang->GET('assemble.compose.bundle.edit_existing_bundle'),
										'list' => 'templates',
										'onkeypress' => "if (event.key === 'Enter') {api.document('get', 'bundle', this.value); return false;}"
									]
								]
							], [
								[
									'type' => 'select',
									'attributes' => [
										'name' => $this->_lang->GET('assemble.compose.bundle.edit_existing_bundle_all'),
										'onchange' => "api.document('get', 'bundle', this.value)"
									],
									'content' => $alloptions
								]
							]
						], [
							[
								'type' => 'text',
								'attributes' => [
									'name' => $this->_lang->GET('assemble.compose.bundle.name'),
									'value' => $bundle['name'],
									'list' => 'templates',
									'required' => true,
									'data-loss' => 'prevent'
								],
								'hint' => ($bundle['name'] ? $this->_lang->GET('assemble.compose.component.component_author', [':author' => $bundle['author'], ':date' => substr($bundle['date'], 0, -3)]) . '<br>' : $this->_lang->GET('assemble.compose.component.component_name_hint'))
							], [
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('assemble.compose.bundle.unit'),
								],
								'content' => $units
							], [
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('assemble.compose.bundle.insert_name'),
									'onchange' => "if (this.value.length > 1) _.insertChars(this.value, 'content'); this.selectedIndex = 0;"
								],
								'content' => $insertdocument
							], [
								'type' => 'textarea',
								'hint' => $this->_lang->GET('assemble.compose.bundle.content_hint'),
								'attributes' => [
									'name' => $this->_lang->GET('assemble.compose.bundle.content'),
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

				// append options for existing bundles
				if ($bundle['id']){
					$hidden = [
						'type' => 'radio',
						'attributes' => [
							'name' => $this->_lang->GET('assemble.compose.bundle.hidden')
						],
						'content' => [
							$this->_lang->GET('assemble.compose.bundle.hidden_visible') => ['checked' => true],
							$this->_lang->GET('assemble.compose.bundle.hidden_hidden') => ['class' => 'red']
						],
						'hint' => $this->_lang->GET('assemble.compose.bundle.hidden_hint')
					];
					if ($bundle['hidden']) {
						$bundle['hidden'] = json_decode($bundle['hidden'], true);
						$hidden['content'][$this->_lang->GET('assemble.compose.bundle.hidden_hidden')]['checked'] = true;
						$hidden['hint'] .= ' ' . $this->_lang->GET('assemble.edit_hidden_set', [':name' => $bundle['hidden']['name'], ':date' => $bundle['hidden']['date']]);
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
	 * display all bundles as groups with their documents
	 */
	public function bundles(){
		$bd = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist');
		$hidden = $bundles = [];
		foreach($bd as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if ($this->_requestedID) similar_text($this->_requestedID, $row['name'], $percent); // filter by similarity if search is requested
			if (!in_array($row['name'], $hidden) && (!$this->_requestedID || $percent >= CONFIG['likeliness']['file_search_similarity'])) {
				if (($documents = $row['content'] ? explode(',', $row['content']) : false) !== false){
					if (!isset($bundles[$row['name']])) $bundles[$row['name']] = [];
					foreach ($documents as $key => $documentname){
						// recurring queries to make sure linked forms are permitted
						if ($document = $this->latestApprovedName('document_document_get_by_name', $documentname))
							if (!$document['hidden']) $bundles[$row['name']][$document['name']] = ['href' => "javascript:api.record('get', 'document', '" . $document['name'] . "')", 'data-filtered' => $row['id']];
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
						'name' => $this->_lang->GET('assemble.compose.document_filter'),
						'list' => 'bundles',
						'onkeypress' => "if (event.key === 'Enter') {api.document('get', 'bundles', this.value); return false;}",
						'onblur' => "api.document('get', 'bundles', this.value); return false;",
						'value' => $this->_requestedID ? : ''
					]
				]
			]
		]];
		// append actual bundles
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
	 * handle a specific component
	 */
	public function component(){
		if (!PERMISSION::permissionFor('documentcomposer')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$component = json_decode($this->_payload->composedComponent, true);
				$component_name = $component['name'];
				unset($component['name']);
				$component_hidden = intval($component['hidden']);
				unset($component['hidden']);
				$component_approve = array_search($component['approve'], $this->_lang->_USER['units']);
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
							UTILITY::alterImage($path, CONFIG['limits']['document_image'], UTILITY_IMAGE_REPLACE);
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
				
				// select latest document by name
				$exists = SQLQUERY::EXECUTE($this->_pdo, 'document_component_get_by_name', [
					'values' => [
						':name' => $component_name
					]
				]);
				$exists = $exists ? $exists[0] : ['approval' => null];
				$approved = PERMISSION::fullyapproved('documentapproval', $exists['approval']);

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
									'msg' => $this->_lang->GET('assemble.compose.component.component_saved', [':name' => $exists['name']]),
									'type' => 'success'
								]]);
						else $this->response([
							'response' => [
								'name' => false,
								'msg' => $this->_lang->GET('assemble.compose.component.component_not_saved'),
								'type' => 'error'
							]]);
					}
					if ($approved && json_decode($exists['content'], true) == $component) {
						// update component properties as long as the content remains unchanged
						if (SQLQUERY::EXECUTE($this->_pdo, 'document_put', [
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
									'msg' => $this->_lang->GET('assemble.compose.component.component_saved', [':name' => $exists['name']]),
									'type' => 'success'
								]]);
						else $this->response([
							'response' => [
								'name' => false,
								'msg' => $this->_lang->GET('assemble.compose.component.component_not_saved'),
								'type' => 'error'
							]]);
					}
				}
				// until here the component has not existed, or the content of an approved component has been changed resulting in a new version

				// if not updated check if approve is set, not earlier
				if (!$component_approve) $this->response(['response' => ['msg' => $this->_lang->GET('assemble.compose.component.component_not_saved_missing'), 'type' => 'error']]);

				// check for forbidden name
				foreach(CONFIG['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $component_name, $matches)) $this->response(['response' => ['msg' => $this->_lang->GET('assemble.render.error_forbidden_name', [':name' => $component_name]), 'type' => 'error']]);
				}

				$component['content'] = fileupload($component['content'], $component_name, $this->_currentdate->format('YmdHis'));
				if (SQLQUERY::EXECUTE($this->_pdo, 'document_post', [
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
					// alert userGroups for approval
					$component_id = $this->_pdo->lastInsertId();
					$message = $this->_lang->GET('assemble.approve.component_request_alert', [':name' => '<a href="javascript:void(0);" onpointerup="api.document(\'get\', \'approval\', ' . $component_id . ')"> ' . $component_name . '</a>'], true);
					foreach(PERMISSION::permissionFor('documentapproval', true) as $permission){
						if ($permission === 'supervisor') $this->alertUserGroup(['permission' => ['supervisor'], 'unit' => [$component_approve]], $message);
						else $this->alertUserGroup(['permission' => [$permission]], $message);
					}
					$this->response([
					'response' => [
						'name' => $component_name,
						'msg' => $this->_lang->GET('assemble.compose.component.component_saved', [':name' => $component_name]),
						'reload' => 'component_editor',
						'type' => 'success'
					]]);
				}
				else $this->response([
					'response' => [
						'name' => false,
						'msg' => $this->_lang->GET('assemble.compose.component.component_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				// return just the content for the composer
				if (intval($this->_requestedID)){
					$component = SQLQUERY::EXECUTE($this->_pdo, 'document_get', [
						'values' => [
							':id' => $this->_requestedID
						]
					]);
					$component = $component ? $component[0] : null;
				} else {
					// get latest approved by name
					$component = $this->latestApprovedName('document_component_get_by_name', $this->_requestedID);
				}
				if ($component){
					$component['content'] = json_decode($component['content']);
					$this->response(['render' => $component]);
				}
				$this->response(['response' => ['msg' => $this->_lang->GET('assemble.compose.component.component_not_found', [':name' => $this->_requestedID]), 'type' => 'error']]);
				break;
			case 'DELETE':
				$component = SQLQUERY::EXECUTE($this->_pdo, 'document_get', [
					'values' => [
						':id' => $this->_requestedID
					]
				]);
				$component = $component ? $component[0] : null;
				if (!$component || PERMISSION::fullyapproved('documentapproval', $component['approval'])) $this->response(['response' => ['msg' => $this->_lang->GET('assemble.compose.component.component_delete_failure'), 'type' => 'error']]); //early exit
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
				if (SQLQUERY::EXECUTE($this->_pdo, 'document_delete', [
					'values' => [
						':id' => $this->_requestedID
					]
				])) $this->response(['response' => [
					'msg' => $this->_lang->GET('assemble.compose.component.component_delete_success'),
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
	 * edit components
	 */
	public function component_editor(){
		if (!PERMISSION::permissionFor('documentcomposer')) $this->response([], 401);
		$componentdatalist = [];
		$options = [];
		$alloptions = [];
		$return = [];
		
		// get selected component
		if ($this->_requestedID == '0' || intval($this->_requestedID)){
			$component = SQLQUERY::EXECUTE($this->_pdo, 'document_get', [
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
			if (!$component = $this->latestApprovedName('document_component_get_by_name', $this->_requestedID)) $component = [
				'id' => '',
				'name' => '',
				'approval' => null,
				'unit' => null
			];
		}
		if ($this->_requestedID && $this->_requestedID !== 'false' && !$component['name'] && $this->_requestedID !== '0') $return['response'] = ['msg' => $this->_lang->GET('assemble.compose.component.component_not_found', [':name' => $this->_requestedID]), 'type' => 'error']; // early exit

		// prepare existing component lists, sorted by units
		foreach(array_keys($this->_lang->_USER['units']) as $unit){
			$options[$unit] = ['...' . $this->_lang->GET('assemble.compose.component.existing_components_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
			$alloptions[$unit] = ['...' . $this->_lang->GET('assemble.compose.component.existing_components_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		}
		$components = SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist');
		$hidden = [];
		foreach($components as $row) {
			$row['unit'] = $row['unit'] ? : 'common';
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!isset($options[$row['unit']][$row['name']]) && !in_array($row['name'], $hidden) && PERMISSION::fullyapproved('documentapproval', $row['approval'])) {
				$componentdatalist[] = $row['name'];
				$options[$row['unit']][$row['name']] = ($row['name'] == $component['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
			}
			$approved = PERMISSION::fullyapproved('documentapproval', $row['approval']) ? $this->_lang->GET('assemble.approve.approved') : $this->_lang->GET('assemble.approve.unapproved');
			$hidden_set = $row['hidden'] ? ' - ' . $this->_lang->GET('assemble.edit_hidden') : '';
			$alloptions[$row['unit']][$row['name'] . ' ' . $this->_lang->GET('assemble.compose.component.component_author', [':author' => $row['author'], ':date' => substr($row['date'], 0, -3)]) . ' - ' . $approved . $hidden_set] = ($row['name'] == $component['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
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
					'name' => $this->_lang->_USER['units'][$unit],
					'onchange' => "api.document('get', 'component_editor', this.value)"
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
					'name' => $this->_lang->_USER['units'][$unit],
					'onchange' => "api.document('get', 'component_editor', this.value)"
				],
				'content' => $components
			];
		}

		// load approved documents for occasional linking
		// check for dependencies in documents
		$approveddocuments = $dependeddocuments = [];
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$hidden = [];
		foreach($fd as $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden)) $approveddocuments[$row['name']] = []; // prepare for selection
			if (isset($component['content']) && !in_array($row['name'], $dependeddocuments) && !in_array($row['name'], $hidden) && in_array($component['name'], explode(',', $row['content']))) {
				$dependeddocuments[] = $row['name'];
			}
		}

		// prepare unit list for approval
		$approve = [
			'hint' => $this->_lang->GET('assemble.compose.component.component_approve_hint', [':roles' => implode(', ', array_map(Fn($v) => $this->_lang->_USER['permissions'][$v], PERMISSION::permissionFor('documentapproval', true)))]),
			'name' => $this->_lang->GET('assemble.compose.component.component_approve'),
			'content' => ['...' . $this->_lang->GET('assemble.compose.component.component_approve_select_default') => ['value' => '0']]
		];
		foreach($this->_lang->_USER['units'] as $key => $value){
			$approve['content'][$value] = $component['unit'] === $key ? ['selected' => true] : [];
		}

		$pending_approvals = PERMISSION::pending('documentapproval', $component['approval']);
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
								'name' => $this->_lang->GET('assemble.compose.component.existing_components_select'),
							],
						],
						...$options_selection,
						[
							'type' => 'search',
							'attributes' => [
								'name' => $this->_lang->GET('assemble.compose.component.existing_components'),
								'list' => 'components',
								'onkeypress' => "if (event.key === 'Enter') {api.document('get', 'component_editor', this.value); return false;}"
							]
						]
					],[
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('assemble.compose.component.existing_components_all'),
							],
						],
						...$alloptions_selection
					]
				],[
					[[
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('assemble.compose.component.components_info_description')
						],
						'content' => $this->_lang->GET('assemble.compose.component.components_info_content')
					]], [[
						'form' => true,
						'type' => 'compose_scanner',
					]], [[
						'form' => true,
						'type' => 'compose_textsection',
					]], [[
						'form' => true,
						'type' => 'compose_image',
					]], [[
						'form' => true,
						'type' => 'compose_text',
					]], [[
						'form' => true,
						'type' => 'compose_textarea',
					]], [[
						'form' => true,
						'type' => 'compose_number',
					]], [[
						'form' => true,
						'type' => 'compose_date',
					]], [[
						'form' => true,
						'type' => 'compose_tel',
					]], [[
						'form' => true,
						'type' => 'compose_email',
					]], [[
						'form' => true,
						'type' => 'compose_link',
					]], [[
						'form' => true,
						'type' => 'compose_productselection',
					]], [[
						'form' => true,
						'type' => 'compose_range',
					]], [[
						'form' => true,
						'type' => 'compose_links',
					]], [[
						'form' => true,
						'type' => 'compose_checkbox',
					]], [[
						'form' => true,
						'type' => 'compose_radio',
					]], [[
						'form' => true,
						'type' => 'compose_select',
					]], [[
						'form' => true,
						'type' => 'compose_file',
					]], [[
						'form' => true,
						'type' => 'compose_photo',
					]], [[
						'form' => true,
						'type' => 'compose_signature',
					]], [[
						'form' => true,
						'type' => 'compose_calendarbutton',
					]], [[
						'form' => true,
						'type' => 'compose_documentbutton',
						'content' => $approveddocuments
					]]
				],
				[[
					'type' => 'compose_component',
					'value' => $component['name'],
					'hint' => ($component['name'] ? $this->_lang->GET('assemble.compose.component.component_author', [':author' => $component['author'], ':date' => substr($component['date'], 0, -3)]) . '\n' : $this->_lang->GET('assemble.compose.component.component_name_hint')) .
						($pending_approvals ? $this->_lang->GET('assemble.approve.pending', [':approvals' => implode(', ', array_map(Fn($permission) => $this->_lang->_USER['permissions'][$permission], $pending_approvals))]) : $this->_lang->GET('assemble.approve.completed')) . '\n \n' .
						($dependeddocuments ? $this->_lang->GET('assemble.compose.component.component_document_dependencies', [':documents' => implode(',', $dependeddocuments)]) : ''),
					'hidden' => $component['name'] ? json_decode($component['hidden'] ? : '', true) : null,
					'approve' => $approve
				]],
				[[
					'type' => 'trash',
					'description' => $this->_lang->GET('assemble.compose.edit_trash')
				]]
			]
		];
		// admins are allowed to insert raw json syntax <3
		if (array_intersect(['admin'], $_SESSION['user']['permissions'])){
			$return['render']['content'][1][] = [[
				'form' => false,
				'type' => 'compose_raw',
				'description' => $this->_lang->GET('assemble.compose.component.raw')
			]];
		}
		// option to delete unapproved component
		if ($component['name'] && (!PERMISSION::fullyapproved('documentapproval', $component['approval'])))
			$return['render']['content'][count($return['render']['content']) - 2][] = [
				[
					'type' => 'deletebutton',
					'attributes' => [
						'value' => $this->_lang->GET('assemble.compose.component.component_delete'),
						'onpointerup' => "api.document('delete', 'component', " . $component['id'] . ")" 
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
	 * export a document as pdf, if earlier than requested maxDocumentTimestmp, with data if provided
	 */
	public function export(){
		$document_id = $identifier = $context = null;
		if ($document_id = UTILITY::propertySet($this->_payload, '_document_id')) unset($this->_payload->_document_id);
		if ($context = UTILITY::propertySet($this->_payload, '_context')) unset($this->_payload->_context);
		if ($record_type = UTILITY::propertySet($this->_payload, 'DEFAULT_' . $this->_lang->PROPERTY('record.type_description'))) unset($this->_payload->{'DEFAULT_' . $this->_lang->PROPERTY('record.type_description')});
		if ($entry_date = UTILITY::propertySet($this->_payload, 'DEFAULT_' . $this->_lang->PROPERTY('record.date'))) unset($this->_payload->{'DEFAULT_' . $this->_lang->PROPERTY('record.date')});
		if ($entry_time = UTILITY::propertySet($this->_payload, 'DEFAULT_' . $this->_lang->PROPERTY('record.time'))) unset($this->_payload->{'DEFAULT_' . $this->_lang->PROPERTY('record.time')});

		// used by audit for export of outdated documents
		if ($maxDocumentTimestamp = UTILITY::propertySet($this->_payload, '_maxDocumentTimestamp')) unset($this->_payload->_maxDocumentTimestamp);
		else $maxDocumentTimestamp = $this->_currentdate->format('Y-m-d H:i:s');

		$document = SQLQUERY::EXECUTE($this->_pdo, 'document_get', [
			'values' => [
				':id' => $document_id
			]
		]);
		$document = $document ? $document[0] : null;
		if (!PERMISSION::permissionFor('documentexport') && !$document['permitted_export'] && !PERMISSION::permissionIn($document['restricted_access'])) $this->response([], 401);
		if (!$document || $document['date'] >= $maxDocumentTimestamp) $this->response([], 409);

		$entry_timestamp = $entry_date . ' ' . $entry_time;
		if (strlen($entry_timestamp) > 16) { // yyyy-mm-dd hh:ii
			$entry_timestamp = $this->_currentdate->format('Y-m-d H:i');
		}

		// create proper identifier with timestamp if not provided
		// unset checkboxes while relying on a prepared additional dataset
		// unset empty values
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
		if (!$identifier) $identifier = in_array($document['context'], array_keys($this->_lang->_USER['documentcontext']['identify'])) ? $this->_lang->GET('assemble.render.export_identifier'): null;
		$summary = [
			'filename' => preg_replace('/' . CONFIG['forbidden']['names'][0] . '/', '', $document['name'] . '_' . $this->_currentdate->format('Y-m-d H:i')),
			'identifier' => $identifier,
			'content' => [],
			'files' => [],
			'images' => [],
			'title' => $document['name'],
			'date' => $this->_lang->GET('assemble.render.export_exported', [':version' => substr($document['date'], 0, -3), ':date' => $this->_currentdate->format('y-m-d H:i')])
		];

		function enumerate($name, $enumerate = [], $number = 1){
			if (isset($enumerate[$name])) $enumerate[$name] += $number;
			else $enumerate[$name] = $number;	
			return $enumerate;
		}

		/**
		 * @param array $element component and subsets
		 * @param array $payload
		 * @param object $_lang $this->_lang can not be referred within the function and has to be passed
		 * @param array $enumerate names of elements that have to be enumerated
		 */
		function printable($element, $payload, $_lang, $enumerate = []){
			$content = ['content' => [], 'images' => [], 'fillable' => false];
			foreach($element as $subs){
				if (!isset($subs['type'])){
					$subcontent = printable($subs, $payload, $_lang, $enumerate);
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
					elseif (in_array($subs['type'], ['documentbutton'])) {
						$name = $subs['attributes']['value'];
					}
					elseif (in_array($subs['type'], ['calendarbutton'])) {
						$name = $_lang->GET('assemble.render.export_element.' . $subs['type']);
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

					if (!in_array($subs['type'], ['textsection', 'image', 'links', 'documentbutton'])) $content['fillable'] = true;
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
						$content['content'][$name] = ['type' => 'textsection', 'value' => $_lang->GET('assemble.render.export_element.' . $subs['type'])];
					}
					elseif ($subs['type'] === 'links'){
						$content['content'][$name] = ['type' => 'links', 'value' => []];
						foreach(array_keys($subs['content']) as $link) $content['content'][$name]['value'][] = $link . "\n";
					}
					elseif ($subs['type'] === 'documentbutton'){
						$content['content'][$_lang->GET('assemble.render.export_element.' . $subs['type']). ': ' . $name] = ['type' => 'textsection', 'value' => ''];
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
		// iterate over components and fill fields with provided values if any
		// maxDocumentTimestamp is used for audit exports of outdated documents on request
		foreach(explode(',', $document['content']) as $usedcomponent) {
			$component = $this->latestApprovedName('document_component_get_by_name', $usedcomponent, $maxDocumentTimestamp);
			if (!$component) continue;
			$component['content'] = json_decode($component['content'], true);

			$printablecontent = printable($component['content']['content'], $this->_payload, $this->_lang, $enumerate);
			$summary['content'] = array_merge($summary['content'], $printablecontent['content']);
			$summary['images'] = array_merge($summary['images'], $printablecontent['images']);
			$enumerate = $printablecontent['enumerate'];
			if ($printablecontent['fillable']) $fillable = true;
		}
		// append default fields for paper that are submitted by default on screen
		if ($fillable){
			if (in_array($document['context'], ['casedocumentation'])) {
				$type = ['type' => 'selection', 'value' => []];
				foreach ($this->_lang->_USER['record']['type'] as $key => $value){
					$type['value'][] = ($record_type === $key ? '_____': '') . $value;
				}
				$summary['content'] = array_merge([$this->_lang->GET('record.type_description') . (CONFIG['application']['require_record_type_selection'] ? ' *' : '') => $type], $summary['content']);
			}
			$summary['content'] = array_merge(['' => ['type' => 'text', 'value' => $this->_lang->GET('assemble.render.required_asterisk')], $this->_lang->GET('assemble.render.export_by') . ' *' => [
				'type' => 'text',
				'value' => ''
			]], $summary['content']);
		}
		$summary['content'] = [' ' => $summary['content']];
		$summary['images'] = [' ' => $summary['images']];

		$downloadfiles[$this->_lang->GET('assemble.render.export')] = [
			'href' => PDF::documentsPDF($summary)
		];
		$this->response([
			'render' => [
				[
					'type' => 'links',
					'description' =>  $this->_lang->GET('assemble.render.export_proceed'),
					'content' => $downloadfiles
				]
			],
		]);
	}
	
	/**
	 *     _                           _   
	 *   _| |___ ___ _ _ _____ ___ ___| |_ 
	 *  | . | . |  _| | |     | -_|   |  _|
	 *  |___|___|___|___|_|_|_|___|_|_|_|  
	 *
	 * handle a specific document
	 */
	public function document(){
		if (!PERMISSION::permissionFor('documentcomposer')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!$this->_payload->context) $this->response(['response' => ['msg' => $this->_lang->GET("assemble.compose.document.document_not_saved_missing"), 'type' => 'error']]); // no content provided
				// check for forbidden names
				foreach(CONFIG['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $this->_payload->name, $matches)) $this->response(['response' => ['msg' => $this->_lang->GET('assemble.render.error_forbidden_name', [':name' => $this->_payload->name]), 'type' => 'error']]);
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
				if (in_array($this->_payload->context, array_keys($this->_lang->_USER['documentcontext']['identify']))){
					$hasidentifier = false;
					foreach($this->_payload->content as $component){
						// get latest approved by name
						$latestcomponent = $this->latestApprovedName('document_component_get_by_name', $component);
						if (check4identifier(json_decode($latestcomponent['content'], true)['content'])) $hasidentifier = true;
					}
					if (!$hasidentifier) $this->response(['response' => ['msg' => $this->_lang->GET('assemble.compose.component.context_missing_identifier'), 'type' => 'error']]);
				}
				// convert values to keys for regulatory_context
				$regulatory_context = [];
				if ($this->_payload->regulatory_context) {
					$rc = explode(', ', $this->_payload->regulatory_context);
					foreach($rc as $context){
						$regulatory_context[] = array_search($context, $this->_lang->_USER['regulatory']); 
					}
				}
				// convert values to keys for restricted_access
				$restricted_access = [];
				if ($this->_payload->restricted_access) {
					$rc = explode(', ', $this->_payload->restricted_access);
					foreach($rc as $context){
						$restricted_access[] = array_search($context, $this->_lang->_USER['permissions']); 
					}
				}
				
				// select latest document by name
				$exists = SQLQUERY::EXECUTE($this->_pdo, 'document_document_get_by_name', [
					'values' => [
						':name' => $this->_payload->name
					]
				]);
				$exists = $exists ? $exists[0] : ['approval' => null];
				$approved = PERMISSION::fullyapproved('documentapproval', $exists['approval']);

				$this->_payload->approve = array_search($this->_payload->approve, $this->_lang->_USER['units']);

				if (isset($exists['id'])){ 
					if (!$approved) {
						// update anything, reset approval
						if (SQLQUERY::EXECUTE($this->_pdo, 'document_put', [
							'values' => [
								':alias' => gettype($this->_payload->alias) === 'array' ? implode(' ', $this->_payload->alias) : $this->_payload->alias,
								':context' => $this->_payload->context,
								':unit' => $this->_payload->approve ? : null,
								':author' => $_SESSION['user']['name'],
								':content' => implode(',', $this->_payload->content),
								':hidden' => boolval(intval($this->_payload->hidden)) ? json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_currentdate->format('Y-m-d H:i:s')]) : null,
								':approval' => null,
								':regulatory_context' => implode(',', $regulatory_context),
								':permitted_export' => $this->_payload->permitted_export ? : null,
								':restricted_access' => $restricted_access ? implode(',', $restricted_access) : NULL,
								':id' => $exists['id'],
							]
						])) $this->response([
								'response' => [
									'name' => $this->_payload->name,
									'msg' => $this->_lang->GET('assemble.compose.document.document_saved', [':name' => $this->_payload->name]),
									'type' => 'success'
								]]);
						else $this->response([
							'response' => [
								'name' => false,
								'msg' => $this->_lang->GET('assemble.compose.document.document_not_saved'),
								'type' => 'error'
							]]);
					}
					if ($approved && $exists['content'] == implode(',', $this->_payload->content)) {
						// update document properties as long as the content remains unchanged
						if (SQLQUERY::EXECUTE($this->_pdo, 'document_put', [
							'values' => [
								':alias' => gettype($this->_payload->alias) === 'array' ? implode(' ', $this->_payload->alias) : $this->_payload->alias,
								':context' => $this->_payload->context,
								':unit' => $this->_payload->approve ? : null,
								':author' => $exists['author'],
								':content' => $exists['content'],
								':hidden' =>  boolval(intval($this->_payload->hidden)) ? json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_currentdate->format('Y-m-d H:i:s')]) : null,
								':approval' => $exists['approval'],
								':regulatory_context' => implode(',', $regulatory_context),
								':permitted_export' => $this->_payload->permitted_export ? : null,
								':restricted_access' => $restricted_access ? implode(',', $restricted_access) : NULL,
								':id' => $exists['id'],
							]
						])) $this->response([
								'response' => [
									'name' => $this->_payload->name,
									'msg' => $this->_lang->GET('assemble.compose.document.document_saved', [':name' => $this->_payload->name]),
									'type' => 'success'
								]]);
						else $this->response([
							'response' => [
								'name' => false,
								'msg' => $this->_lang->GET('assemble.compose.document.document_not_saved'),
								'type' => 'error'
							]]);
					}
				}
				// until here the document has not existed, or the content of an approved document has been changed resulting in a new version

				// if not updated check if approve is set, not earlier
				if (!$this->_payload->approve) $this->response(['response' => ['msg' => $this->_lang->GET('assemble.compose.document.document_not_saved_missing'), 'type' => 'error']]);

				if (SQLQUERY::EXECUTE($this->_pdo, 'document_post', [
					'values' => [
						':name' => $this->_payload->name,
						':alias' => gettype($this->_payload->alias) === 'array' ? implode(' ', $this->_payload->alias): $this->_payload->alias,
						':context' => gettype($this->_payload->context) === 'array' ? '': $this->_payload->context,
						':unit' => $this->_payload->approve,
						':author' => $_SESSION['user']['name'],
						':content' => implode(',', $this->_payload->content),
						':regulatory_context' => implode(',', $regulatory_context),
						':permitted_export' => $this->_payload->permitted_export ? : null,
						':restricted_access' => $restricted_access ? implode(',', $restricted_access) : NULL
					]
				])) {
						$document_id = $this->_pdo->lastInsertId();
						$message = $this->_lang->GET('assemble.approve.document_request_alert', [':name' => '<a href="javascript:void(0);" onpointerup="api.document(\'get\', \'approval\', ' . $document_id . ')"> ' . $this->_payload->name . '</a>'], true);
						foreach(PERMISSION::permissionFor('documentapproval', true) as $permission){
							if ($permission === 'supervisor') $this->alertUserGroup(['permission' => ['supervisor'], 'unit' => [$this->_payload->approve]], $message);
							else $this->alertUserGroup(['permission' => [$permission]], $message);
						}
						$this->response([
						'response' => [
							'name' => $this->_payload->name,
							'msg' => $this->_lang->GET('assemble.compose.document.document_saved', [':name' => $this->_payload->name]),
							'reload' => 'document_editor',
							'type' => 'success'
						]]);
				}
				else $this->response([
					'response' => [
						'name' => false,
						'msg' => $this->_lang->GET('assemble.compose.document.document_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'DELETE':
				$component = SQLQUERY::EXECUTE($this->_pdo, 'document_get', [
					'values' => [
						':id' => $this->_requestedID
					]
				]);
				$component = $component ? $component[0] : null;
				if (!$component || PERMISSION::fullyapproved('documentapproval', $component['approval'])) $this->response(['response' => ['msg' => $this->_lang->GET('assemble.compose.document.document_delete_failure'), 'type' => 'error']]);
				
				if (SQLQUERY::EXECUTE($this->_pdo, 'document_delete', [
					'values' => [
						':id' => $this->_requestedID
					]
				])) $this->response(['response' => [
					'msg' => $this->_lang->GET('assemble.compose.document.document_delete_success'),
					'type' => 'success',
					'reload' => 'document_editor',
					]]);
				break;
		}
	}
	
	/**
	 *     _                           _               _ _ _           
	 *   _| |___ ___ _ _ _____ ___ ___| |_       ___ _| |_| |_ ___ ___ 
	 *  | . | . |  _| | |     | -_|   |  _|     | -_| . | |  _| . |  _|
	 *  |___|___|___|___|_|_|_|___|_|_|_|  _____|___|___|_|_| |___|_|  
	 *                                    |_____|
	 * edit documents
	 *
	 */
	public function document_editor(){
		if (!PERMISSION::permissionFor('documentcomposer')) $this->response([], 401);
		$documentdatalist = $componentdatalist = [];
		$documentoptions = [];
		$alloptions = [];
		$componentoptions = [];
		$contextoptions = ['...' . $this->_lang->GET('assemble.compose.document.document_context_default') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		$return = [];
		
		// get selected document
		if ($this->_requestedID == '0' || intval($this->_requestedID)){
			$document = SQLQUERY::EXECUTE($this->_pdo, 'document_get', [
				'values' => [
					':id' => $this->_requestedID
				]
			]);
			$document = $document ? $document[0] : null;
		} else{
			// get latest approved by name
			$document = $this->latestApprovedName('document_document_get_by_name', $this->_requestedID);
		}
		// set up document properties
		if (!$document) $document = [
			'name' => '',
			'alias' => '',
			'context' => '',
			'unit' => null,
			'regulatory_context' => '',
			'permitted_export' => null,
			'restricted_access' => null,
			'approval' => null
		];
		if($this->_requestedID && $this->_requestedID !== 'false' && !$document['name'] && $this->_requestedID !== '0') $return['response'] = ['msg' => $this->_lang->GET('assemble.compose.document.document__not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

		// prepare existing documents lists, sorted by units
		foreach(array_keys($this->_lang->_USER['units']) as $unit){
			$documentoptions[$unit] = ['...' . $this->_lang->GET('assemble.compose.document.existing_documents_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
			$alloptions[$unit] = ['...' . $this->_lang->GET('assemble.compose.document.existing_documents_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
			$componentoptions[$unit] = ['...' => ['value' => '']];
		}
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$hidden = [];
		foreach($fd as $key => $row) {
			$row['unit'] = $row['unit'] ? : 'common';
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!isset($documentoptions[$row['unit']][$row['name']]) && !in_array($row['name'], $hidden) && PERMISSION::fullyapproved('documentapproval', $row['approval'])) {
				$documentdatalist[] = $row['name'];
				$documentoptions[$row['unit']][$row['name']] = ($row['name'] === $document['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
			}
			$approved = PERMISSION::fullyapproved('documentapproval', $row['approval']) ? $this->_lang->GET('assemble.approve.approved') : $this->_lang->GET('assemble.approve.unapproved');
			$hidden_set = $row['hidden'] ? ' - ' . $this->_lang->GET('assemble.edit_hidden') : '';
			$alloptions[$row['unit']][$row['name'] . ' ' . $this->_lang->GET('assemble.compose.component.component_author', [':author' => $row['author'], ':date' => substr($row['date'], 0, -3)]) . ' - ' . $approved . $hidden_set] = ($row['name'] === $document['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
		}

		// prepare existing component list of fully approved
		$cd = SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist');
		$hidden = [];
		foreach($cd as $key => $row) {
			$row['unit'] = $row['unit'] ? : 'common';
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!isset($componentoptions[$row['name']]) && !in_array($row['name'], $hidden) && PERMISSION::fullyapproved('documentapproval', $row['approval'])) {
				$componentdatalist[] = $row['name'];
				$componentoptions[$row['unit']][$row['name'] . ' - ' . $this->_lang->GET('assemble.approve.approved')] = ['value' => $row['id']];
			}
		}

		// delete empty selections, order the rest and create remaining selections by unit for easier access
		$options_selection = $alloptions_selection = $components_selection = [];
		foreach($documentoptions as $unit => $components){
			if (count($components) < 2) {
				continue;
			}
			ksort($components);
			$options_selection[] = [
				'type' => 'select',
				'attributes' => [
					'name' => $this->_lang->_USER['units'][$unit],
					'onchange' => "api.document('get', 'document_editor', this.value)"
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
					'name' => $this->_lang->_USER['units'][$unit],
					'onchange' => "api.document('get', 'document_editor', this.value)"
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
					'name' => $this->_lang->_USER['units'][$unit],
					'onchange' => "if (this.value) api.document('get', 'component', this.value)"
				],
				'content' => $components
			];
		}



		// check for bundle dependencies
		$bd = SQLQUERY::EXECUTE($this->_pdo, 'document_bundle_datalist');
		$hidden = [];
		$dependedbundles = [];
		foreach($bd as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden) && 
			in_array($document['name'], explode(',', $row['content'])) && 
			!in_array($document['name'], $dependedbundles)) $dependedbundles[] = $row['name']; 
		}

		// check for dependencies in approved components (linked documents)
		$dependedcomponents = [];
		if ($document['name']){
			$cd = SQLQUERY::EXECUTE($this->_pdo, 'document_component_datalist');
			$hidden = [];
			foreach($cd as $row) {
				if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
				if (!PERMISSION::fullyapproved('documentapproval', $row['approval'])) continue;
				if (!in_array($row['name'], $dependedcomponents) && !in_array($row['name'], $hidden)) {
					// don't bother disassembling content, just look for an expression
					if (stristr($row['content'], '"value":"' . $this->_lang->GET('assemble.compose.component.link_document_display_button', [':document' => $document['name']]) . '"')
						|| stristr($row['content'], '"value":"' . $this->_lang->GET('assemble.compose.component.link_document_continue_button', [':document' => $document['name']]) . '"')) $dependedcomponents[] = $row['name'];
				}
			}
		}		

		// prepare existing context list
		foreach($this->_lang->_USER['documentcontext'] as $type => $contexts){
			foreach($contexts as $context => $display){
				if ($type === 'identify') $display .= ' *';
				$contextoptions[$display] = $context===$document['context'] ? ['value' => $context, 'selected' => true] : ['value' => $context];
			}
		}
		ksort($contextoptions);

		// prepare unit list for approval
		$approve = [
			'hint' => $this->_lang->GET('assemble.compose.component.component_approve_hint', [':roles' => implode(', ', array_map(Fn($v) => $this->_lang->_USER['permissions'][$v], PERMISSION::permissionFor('documentapproval', true)))]),
			'name' => $this->_lang->GET('assemble.compose.component.component_approve'),
			'content' => ['...' . $this->_lang->GET('assemble.compose.component.component_approve_select_default') => ['value' => '0']]
		];
		foreach($this->_lang->_USER['units'] as $key => $value){
			$approve['content'][$value] = $document['unit'] === $key ? ['selected' => true] : [];
		}

		// prepare lists to select regulatory context
		$regulatory_context = [];
		$document['regulatory_context'] = explode(',', $document['regulatory_context'] ? : '');
		foreach($this->_lang->_USER['regulatory'] as $key => $value){
			$regulatory_context[$value] = ['value' => $key];
			if (in_array($key, $document['regulatory_context'])) $regulatory_context[$value]['checked'] = true;
		}

		// option for general export permission
		$permitted_export = [
			'hint' => $this->_lang->GET('assemble.compose.document.document_permitted_export_hint', [':permissions' => implode(', ', array_map(Fn($v) => $this->_lang->_USER['permissions'][$v], PERMISSION::permissionFor('documentexport', true)))]),
			'content' => [
				$this->_lang->GET('assemble.compose.document.document_permitted_export') => $document['permitted_export'] ? ['checked' => true]: []
			]
		];

		// prepare list to restrict access to set permissions
		$restricted_access = [
			'description' => $this->_lang->GET('assemble.compose.document.document_restricted_access'),
			'hint' => $this->_lang->GET('assemble.compose.document.document_restricted_access_hint'),
			'content' => []
		];
		$document['restricted_access'] = explode(',', strval($document['restricted_access']));
		foreach($this->_lang->_USER['permissions'] as $value => $translation){
			$restricted_access['content'][$translation] = ['value' => $value];
			if (in_array($value, $document['restricted_access'])) $restricted_access['content'][$translation]['checked'] = true;
		}

		$pending_approvals = PERMISSION::pending('documentapproval', $document['approval']);
		$return['render'] = [
			'content' => [
				[
					[
						[
							'type' => 'datalist',
							'content' => array_values(array_unique($documentdatalist)),
							'attributes' => [
								'id' => 'documents'
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
								'name' => $this->_lang->GET('assemble.compose.document.existing_documents_select'),
							],
						],
						...$options_selection,
						[
							'type' => 'search',
							'attributes' => [
								'name' => $this->_lang->GET('assemble.compose.document.existing_documents'),
								'list' => 'documents',
								'onkeypress' => "if (event.key === 'Enter') {api.document('get', 'document_editor', this.value); return false;}"
							]
						]
					],[
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('assemble.compose.document.existing_documents_all'),
							],
						],
						...$alloptions_selection
					]
				], [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('assemble.compose.document.documents_info_description')
						],
						'content' => $this->_lang->GET('assemble.compose.document.documents_info_content')
					], 
					...$components_selection,
					[
						'type' => 'search',
						'attributes' => [
							'name' => $this->_lang->GET('assemble.compose.document.add_component'),
							'list' => 'components',
							'onkeypress' => "if (event.key === 'Enter') {api.document('get', 'component', this.value); return false;}"
						]
					]
				], [
					[
						'type' => 'compose_document',
						'value' => $document['name'] ? : '',
						'alias' => [
							'name' => $this->_lang->GET('assemble.compose.document.document_alias'),
							'value' => $document['alias'] ? : ''
						],
						'context' => [
							'name' => $this->_lang->GET('assemble.compose.document.document_context'),
							'content' => $contextoptions,
							'hint' => $this->_lang->GET('assemble.compose.document.document_context_hint')
						],
						'hint' => ($document['name'] ? $this->_lang->GET('assemble.compose.component.component_author', [':author' => $document['author'], ':date' => substr($document['date'], 0, -3)]) . '\n' : $this->_lang->GET('assemble.compose.component.component_name_hint')) .
						($pending_approvals ? $this->_lang->GET('assemble.approve.pending', [':approvals' => implode(', ', array_map(Fn($permission) => $this->_lang->_USER['permissions'][$permission], $pending_approvals))]) : $this->_lang->GET('assemble.approve.completed')) . '\n \n' .
						($dependedbundles ? $this->_lang->GET('assemble.compose.document.document_bundle_dependencies', [':bundles' => implode(',', $dependedbundles)]) . '\n' : '') .
						($dependedcomponents ? $this->_lang->GET('assemble.compose.document.document_component_dependencies', [':components' => implode(',', $dependedcomponents)]) . '\n' : '')
						,
						'hidden' => $document['name'] ? json_decode($document['hidden'] ? : '', true) : null,
						'approve' => $approve,
						'regulatory_context' => $regulatory_context ? : ' ',
						'permitted_export' => $permitted_export,
						'restricted_access' => $restricted_access
					]
				], [
					[
						'type' => 'trash',
						'description' => $this->_lang->GET('assemble.compose.edit_trash')
					]
				]
			]
		];

		// delete option if not approved yet
		if ($document['name'] && (!PERMISSION::fullyapproved('documentapproval', $document['approval'])))
			$return['render']['content'][count($return['render']['content']) - 2][] = [
				[
					'type' => 'deletebutton',
					'attributes' => [
						'value' => $this->_lang->GET('assemble.compose.document.document_delete'),
						'onpointerup' => "api.document('delete', 'document', " . $document['id'] . ")" 
					]
				]
			];

		// add used components to response
		if (isset($document['content'])) {
			$return['render']['components'] = [];
			foreach(explode(',', $document['content']) as $usedcomponent) {
				// get latest approved by name
				$component = $this->latestApprovedName('document_component_get_by_name', $usedcomponent);
				if ($component){
					$component['content'] = json_decode($component['content'], true);
					$component['content']['name'] = $usedcomponent;
					$component['content']['hidden'] = json_decode($component['hidden'] ? : '', true);
					$return['render']['components'][] = $component['content'];
				}
			}
		}
		if ($document['name']) $return['header'] = $document['name'];
		$this->response($return);
	}
	
	/**
	 *     _                           _   ___ _ _ _           
	 *   _| |___ ___ _ _ _____ ___ ___| |_|  _|_| | |_ ___ ___ 
	 *  | . | . |  _| | |     | -_|   |  _|  _| | |  _| -_|  _|
	 *  |___|___|___|___|_|_|_|___|_|_|_| |_| |_|_|_| |___|_|
	 * 
	 * filter by searched name, return ids
	 */
	public function documentfilter(){
		require_once('_shared.php');
		$search = new SHARED($this->_pdo);
		$documents = $search->documentsearch(['search' => $this->_requestedID]);
		$this->response([
			'data' => $documents ? array_map(fn($v)=> strval($v), array_column($documents, 'id')) : null
		]);
	}
	
	/**
	 *     _                           _       
	 *   _| |___ ___ _ _ _____ ___ ___| |_ ___ 
	 *  | . | . |  _| | |     | -_|   |  _|_ -|
	 *  |___|___|___|___|_|_|_|___|_|_|_| |___|
	 *  
	 * display list of approved and available documents
	 */
	public function documents(){
		$documentdatalist = $documents = [];
		$return = [];

		// prepare existing documents lists
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$hidden = [];
		foreach($fd as $key => $row) {
			if (!PERMISSION::fullyapproved('documentapproval', $row['approval']) || !PERMISSION::permissionIn($row['restricted_access'])) continue;
			if ($row['hidden'] || in_array($row['context'], array_keys($this->_lang->_USER['documentcontext']['notdisplayedinrecords']))) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $documentdatalist) && !in_array($row['name'], $hidden)) {
				$documentdatalist[] = $row['name'];
				$documents[$row['context']][$row['name']] = ['href' => "javascript:api.record('get', 'document', '" . $row['name'] . "')", 'data-filtered' => $row['id']];
				foreach(preg_split('/[^\w\d]/', $row['alias']) as $alias) $documentdatalist[] = $alias;
			}
		}
		$return['render'] = [
			'content' => [
				[
					[
						'type' => 'datalist',
						'content' => array_values(array_unique($documentdatalist)),
						'attributes' => [
							'id' => 'documents'
						]
					], [
						'type' => 'filtered',
						'attributes' => [
							'name' => $this->_lang->GET('assemble.compose.document_filter'),
							'list' => 'documents',
							'onkeypress' => "if (event.key === 'Enter') {api.document('get', 'documentfilter', this.value); return false;}",
							'onblur' => "api.document('get', 'documentfilter', this.value); return false;",
						],
						'hint' => $this->_lang->GET('assemble.compose.document_filter_hint')
					]
				]
			]];
		// sort by context for easier comprehension
		foreach ($documents as $context => $list){
			$contexttranslation = '';
			foreach ($this->_lang->_USER['documentcontext'] as $documentcontext => $contexts){
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
	 * returns the latest approved document, component by name from query
	 * @param string $query as defined within sqlinterface
	 * @param string $name
	 * @param string $maxtimestamp Y-m-d H:i:s to limit to earlier versions
	 * 
	 * @return array|bool either query row or false
	 */
	private function latestApprovedName($query = '', $name = '', $maxtimestamp = ''){
		// get latest approved by name
		$element = [];
		$elements = SQLQUERY::EXECUTE($this->_pdo, $query, [
			'values' => [
				':name' => $name
			]
		]);
		foreach ($elements as $element){
			if (PERMISSION::fullyapproved('documentapproval', $element['approval'])
				&& (!$maxtimestamp || $element['date']<= $maxtimestamp)
			) return $element;
		}
		return false;
	}
}
?>
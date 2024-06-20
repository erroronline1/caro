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

// add, edit and delete users
class FILE extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	public $_requestedFolder = null;
	public $_requestedId = null;
	public $_requestedFile = null;
	public $_accessible = null;

	public function __construct(){
		parent::__construct();
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);

		$this->_requestedFolder = $this->_requestedId = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_requestedFile = $this->_accessible = array_key_exists(3, REQUEST) ? REQUEST[3] : null;
	}

	/**
	 * returns paths to documents that are available according to database
	 * @return array filepaths
	 */
	private function activeexternalfiles(){
		$files = SQLQUERY::EXECUTE($this->_pdo, 'file_external_documents_get_active');
		if ($files) return array_column($files, 'path');
		return [];
	}

	/**
	 * filters files according to request string and passed folder if applicable
	 * responds with paths matching request
	 */
	public function filter(){
		if ($this->_requestedFolder && $this->_requestedFolder == 'sharepoint') $files = UTILITY::listFiles(UTILITY::directory('sharepoint') ,'asc');
		if ($this->_requestedFolder && $this->_requestedFolder == 'users') $files = UTILITY::listFiles(UTILITY::directory('users') ,'asc');
		else {
			$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
			$files = [];
			foreach ($folders as $folder) {
				$files = array_merge($files, UTILITY::listFiles($folder ,'asc'));
			}
			$files = array_merge($files, UTILITY::listFiles(UTILITY::directory('external_documents') ,'asc'));
		}
		$matches = [];
		foreach ($files as $file){
			similar_text($this->_requestedFile, pathinfo($file)['filename'], $percent);
			if ($percent >= INI['likeliness']['file_search_similarity'] || !$this->_requestedFile) $matches[] = substr($file, 1);
		}
		$this->response(['status' => [
			'data' => $matches
		]]);
	}

	/**
	 * filters file bundles according to request string
	 * responds with bundle ids matching request
	 */
	public function bundlefilter(){
		$bundles = SQLQUERY::EXECUTE($this->_pdo, 'file_bundles_get_active');
		$matches = [];
		foreach($bundles as $row) {
			similar_text($this->_requestedFolder, $row['name'], $percent);
			if ($percent >= INI['likeliness']['file_search_similarity'] || !$this->_requestedFolder) $matches[] = strval($row['id']);
		}
		$this->response(['status' => [
			'data' => $matches
		]]);
	}

	/**
	 * responds with content of available files for restricted uploads and external documents
	 */
	public function files(){
		$result['body'] = [
		'content' => [
				[
					[
						'type' => 'filterinput',
						'attributes' => [
							'name' => LANG::GET('file.file_filter_label'),
							'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', this.value); return false;}",
							'onblur' => "api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', this.value); return false;",
							'id' => 'filesearch'
						]
					]
				]
			]
		];
		$files = [];
		if ($this->_requestedFolder && $this->_requestedFolder != 'null') {
			$folder = UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]);
			$files[$folder] = UTILITY::listFiles($folder ,'asc');
		}
		else {
			if ($external = $this->activeexternalfiles()) $files[UTILITY::directory('external_documents')] = $external;
			$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
			foreach ($folders as $folder) {
				$files[$folder] = UTILITY::listFiles($folder ,'asc');
			}
		}
		if ($files){
			foreach ($files as $folder => $content){
				$matches = [];
				foreach ($content as $file){
					$file=['path' => substr($file,1), 'name' => pathinfo($file)['filename'], 'file' => pathinfo($file)['basename']];
					$matches[$file['file']] = ['href' => $file['path'], 'data-filtered' => $file['path'], 'target' => '_blank'];
				}
				$result['body']['content'][]=
				[
					[
						'type' => 'links',
						'description' => LANG::GET('file.file_list', [':folder' => pathinfo($folder)['filename']]),
						'content' => $matches
					]
				];
			}
		}
		else $result['body']['content'] = $this->noContentAvailable(LANG::GET('file.no_files'));
		$this->response($result);
	}

	/**
	 * get responds with content of available folders or files for restricted uploads
	 * post responds with success state of upload
	 * delete responds with success state of deletion
	 * 
	 * no put method for windows server permissions are a pita
	 * thus directories can not be renamed
	 */
	public function filemanager(){
		if (!PERMISSION::permissionFor('files')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$new_folder = preg_replace(['/[\s-]{1,}/', '/\W/'], ['_', ''], UTILITY::propertySet($this->_payload, LANG::PROPERTY('file.manager_new_folder')));
				if ($new_folder){
					foreach(INI['forbidden']['names'] as $pattern){
						if (preg_match("/" . $pattern . "/m", $new_folder, $matches)) $this->response(['status' => ['msg' => LANG::GET('file.manager_new_folder_forbidden_name', [':name' => $new_folder]), 'type' => 'error']]);
					}
					$new_folder = UTILITY::directory('files_documents', [':category' => $new_folder]);
					UTILITY::storeUploadedFiles([], $new_folder);
					$this->response(['status' => [
						'msg' => LANG::GET('file.manager_new_folder_created', [':name' => $new_folder]),
						'redirect' => ['filemanager'],
						'type' => 'success'
						]]);
				}
				$destination = UTILITY::propertySet($this->_payload, 'destination');
				if (array_key_exists(LANG::PROPERTY('file.manager_new_file'), $_FILES) && $_FILES[LANG::PROPERTY('file.manager_new_file')]['tmp_name'] && $destination) {
					UTILITY::storeUploadedFiles([LANG::PROPERTY('file.manager_new_file')], UTILITY::directory('files_documents', [':category' => $destination]));
					$this->response(['status' => [
						'msg' => LANG::GET('file.manager_new_file_created'),
						'redirect' => ['filemanager', $destination],
						'type' => 'success'
					]]);
				}
				$this->response(['status' => [
					'msg' => LANG::GET('file.manager_error'),
					'type' => 'error'
				]]);
		break;
			case 'GET':
				$result=['body'=>
					[
						'form' => [
							'data-usecase' => 'file',
							'action' => "javascript:api.file('post', 'filemanager')"
						],
						'content'=>[]
					]
				];

				if (!$this->_requestedFolder){
					$result['body']['content'][] = [];
					$folders = UTILITY::listDirectories(UTILITY::directory('files_documents'),'asc');
					if ($folders){
						$content=[];
						foreach ($folders as $folder){
							$foldername = str_replace(UTILITY::directory('files_documents') . '/', '', $folder);
							array_push($result['body']['content'][0],
								[
									'type' => 'links',
									'description' => LANG::GET('file.manager_folder_header', [':date' => date('Y-m-d H:i', filemtime($folder))]),
									'content' => [
										$foldername => ['href' => "javascript:api.file('get', 'filemanager', '" . $foldername . "')"]
									]
								],
								[
									'type' => 'deletebutton',
									'attributes' => [
										'value' => LANG::GET('file.manager_delete_folder'),
										'type' => 'button',
										'onpointerup' => "new Dialog({type: 'confirm', header: '". LANG::GET('file.manager_delete_file_confirmation_header', [':file' => $foldername]) ."', 'options':{".
											"'".LANG::GET('file.manager_delete_file_confirmation_cancel')."': false,".
											"'".LANG::GET('file.manager_delete_file_confirmation_ok')."': {value: true, class: 'reducedCTA'},".
											"}}).then(confirmation => {if (confirmation) api.file('delete', 'filemanager', '" . $foldername . "')})"
									]
								]
							);
						}
					}
					array_push($result['body']['content'][0],
						[
							'type' => 'links',
							'description' => LANG::GET('menu.files_sharepoint'),
							'content' => [
								LANG::GET('menu.files_sharepoint') => ['href' => "javascript:api.file('get', 'filemanager', 'sharepoint')"]
							]
						]
					);
					array_push($result['body']['content'][0],
						[
							'type' => 'links',
							'description' => LANG::GET('menu.application_user_manager'),
							'content' => [
								LANG::GET('menu.application_user_manager') => ['href' => "javascript:api.file('get', 'filemanager', 'users')"]
							]
						]
					);
					$result['body']['content'][]=[
						[
							'type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('file.manager_new_folder'),
								'required' => true
							],
							'hint' => LANG::GET('file.manager_no_external_files')
						]
					];
				}
				else {
					if ($this->_requestedFolder === 'sharepoint') $files = UTILITY::listFiles(UTILITY::directory('sharepoint') ,'asc');
					elseif ($this->_requestedFolder === 'users') {
						$files = UTILITY::listFiles(UTILITY::directory('users'), 'asc');
						$files = array_filter($files, fn($file) => substr(pathinfo($file)['filename'], 0, 10) !== 'profilepic');
					}
					else $files = UTILITY::listFiles(UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]) ,'asc');
					if ($files){
						$result['body']['content'][]=[
							[
								'type' => 'filterinput',
								'attributes' => [
									'name' => LANG::GET('file.file_filter_label'),
									'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', this.value); return false;}",
									'onblur' => "api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', this.value); return false;",
									'id' => 'filefilter'
								]
							]
						];
						$result['body']['content'][] = [];
						foreach ($files as $file){
							if ($file) {
								$file = ['path' => substr($file, 1), 'name' => pathinfo($file)['basename']];
								array_push($result['body']['content'][1],
									[
										'type' => 'links',
										'description' => date('Y-m-d H:i', filemtime('.' . $file['path'])),
										'content' => [
											$file['path'] => ['href' => $file['path'], 'target' => '_blank', 'data-filtered' => $file['path']]
										]
									],
									[
										'type' => 'button',
										'attributes' => [
											'value' => LANG::GET('file.manager_copy_path'),
											'type' => 'button',
											'onpointerup' => "_client.order.toClipboard('" . $file['path'] . "')",
											'class' => 'inlinebutton'
										]
									],
									[
										'type' => 'deletebutton',
										'attributes' => [
											'value' => LANG::GET('file.manager_delete_file'),
											'type' => 'button',
											'data-filtered' => $file['path'],
											'onpointerup' => "new Dialog({type: 'confirm', header: '". LANG::GET('file.manager_delete_file_confirmation_header', [':file' => $file['name']]) ."', 'options':{".
												"'".LANG::GET('file.manager_delete_file_confirmation_cancel')."': false,".
												"'".LANG::GET('file.manager_delete_file_confirmation_ok')."': {value: true, class: 'reducedCTA'},".
												"}}).then(confirmation => {if (confirmation) api.file('delete', 'filemanager', '" . $this->_requestedFolder . "', '" . $file['name'] . "')})"
										]
									]
								);
							}
						}
					}
					else $result['body']['content'] = $this->noContentAvailable(LANG::GET('file.no_files'));
					if (in_array($this->_requestedFolder, ['sharepoint', 'users'])) unset ($result['body']['form']);
					else $result['body']['content'][]=[
						[
							'type' => 'hiddeninput',
							'attributes' => [
								'name' => 'destination',
								'value' => $this->_requestedFolder
							]
						],
						[
							'type' => 'file',
							'attributes' => [
								'name' => LANG::GET('file.manager_new_file'),
								'multiple' => true,
								'required' => true
							],
								'hint' => LANG::GET('file.manager_no_external_files')
						]
					];
				}
				$this->response($result);
				break;
			case 'DELETE':
				if (in_array($this->_requestedFolder, ['sharepoint', 'users'])) $success = UTILITY::delete(UTILITY::directory($this->_requestedFolder) . '/' . $this->_requestedFile);
				else $success = UTILITY::delete(UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]) . ($this->_requestedFile ? '/' . $this->_requestedFile : ''));
				if ($success) $this->response(['status' => [
					'msg' => LANG::GET('file.manager_deleted_file', [':file' => $this->_requestedFile ? : $this->_requestedFolder]),
					'redirect' => ['filemanager',  $this->_requestedFile ? $this->_requestedFolder : null],
					'type' => 'success'
				]]);
				else $this->response(['status' => [
					'msg' => LANG::GET('file.manager_error'),
					'type' => 'error'
				]]);
				break;
		}
	}

	/**
	 * get responds with content of available files for external documents
	 * post responds with success state of upload
	 * put responds with success state of either setting availability or regulatory context
	 * 
	 * no delete for audit safety
	 */
	public function externalfilemanager(){
		if (!PERMISSION::permissionFor('externaldocuments')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (array_key_exists(LANG::PROPERTY('file.manager_new_file'), $_FILES) && $_FILES[LANG::PROPERTY('file.manager_new_file')]['tmp_name']) {
					$files = UTILITY::storeUploadedFiles([LANG::PROPERTY('file.manager_new_file')], UTILITY::directory('external_documents'));
					$insertions = [];
					foreach($files as $file){
						$insertions[] = [
							':author' => $_SESSION['user']['name'],
							':path' => $file
						];
					}
					$sqlchunks = SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('file_external_documents_post'), $insertions);
					foreach ($sqlchunks as $chunk){
						$success = false;
						try {
							if (SQLQUERY::EXECUTE($this->_pdo, $chunk)) $success = true;
						}
						catch (Exception $e) {
							echo $e, $chunk;
							die();
						}
					}
					if ($success){		
						$this->response(['status' => [
							'msg' => LANG::GET('file.manager_new_file_created'),
							'type' => 'success'
						]]);
					}
					$this->response(['status' => [
						'msg' => LANG::GET('file.manager_error'),
						'type' => 'error'
					]]);
				}
				break;
			case 'PUT':
				 switch ($this->_accessible){
					case '0':
						$prepare = 'file_external_documents_retire';
						$tokens = [
							':author' => $_SESSION['user']['name'],
							':id' => $this->_requestedId
						];
						$response = LANG::GET('file.external_file_retired_success');
						break;
					case '1':
						$prepare = 'file_external_documents_unretire';
						$tokens = [
							':author' => $_SESSION['user']['name'],
							':id' => $this->_requestedId
						];
						$response = LANG::GET('file.external_file_available_success');
						break;
					default:
						$regulatory_context = [];
						foreach(explode(', ', $this->_accessible) as $context){
							$regulatory_context[] = array_search($context, LANGUAGEFILE['regulatory']); 
						}
						$prepare = 'file_external_documents_context';
						$tokens = [
							':regulatory_context' => implode(',', $regulatory_context),
							':id' => $this->_requestedId
						];
						$response = LANG::GET('file.external_file_regulatory_context');
				}
				if (SQLQUERY::EXECUTE($this->_pdo, $prepare, [
					'values' => $tokens
				])) $this->response(['status' => [
						'msg' => $response,
						'type' => 'success'
					]]);
				else $this->response(['status' => [
					'msg' => LANG::GET('file.manager_error'),
					'type' => 'error'
				]]);
				break;
			case 'GET':
				$result=['body'=>
				['form' => [
					'data-usecase' => 'file',
					'action' => "javascript:api.file('post', 'externalfilemanager')"],
					'content'=>[]
				]];

				$files = SQLQUERY::EXECUTE($this->_pdo, 'file_external_documents_get');		
				if ($files){
					$result['body']['content'][]=[
						[
							'type' => 'filterinput',
							'attributes' => [
								'name' => LANG::GET('file.file_filter_label'),
								'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'filter', 'null', this.value); return false;}",
								'onblur' => "api.file('get', 'filter', 'null', this.value); return false;",
								'id' => 'filefilter'
							]
						]
					];
					$result['body']['content'][] = [];
					foreach ($files as $file){
						if ($file) {
							$file['name'] = pathinfo($file['path'])['basename'];
							$file['path'] = substr($file['path'], 1);
							$regulatory_context = [];
							$file['regulatory_context'] = explode(',', $file['regulatory_context']);
							foreach(LANGUAGEFILE['regulatory'] as $key => $value){
								$regulatory_context[$value] = ['value' => $key];
								if (in_array($key, $file['regulatory_context'])) $regulatory_context[$value]['checked'] = true;
							}
							array_push($result['body']['content'][1],
								[
									'type' => 'links',
									'description' => ($file['retired'] ? LANG::GET('file.external_file_retired', [':user' => $file['author'], ':date' => date('Y-m-d H:i', filemtime('.' . $file['path'])), ':date2' => $file['retired']]) : LANG::GET('file.external_file_introduced', [':user' => $file['author'], ':date' => date('Y-m-d H:i', filemtime('.' . $file['path']))])),
									'content' => [
										$file['path'] => ['href' => $file['path'], 'target' => '_blank', 'data-filtered' => $file['path']]
									],
									'data-filtered' => $file['path']
								],
								[
									'type' => 'button',
									'attributes' => [
										'value' => LANG::GET('file.manager_copy_path'),
										'type' => 'button',
										'onpointerup' => "_client.order.toClipboard('" . $file['path'] . "')",
										'class' => 'inlinebutton',
										'data-filtered' => $file['path']
									]
								],
								[
									'type' => 'checkbox',
									'content' => [
										LANG::GET('file.external_file_available') => ($file['retired']
										? ['onchange' => "api.file('put', 'externalfilemanager', '" . $file['id'] . "', this.checked ? 1 : 0)", 'data-filtered' => $file['path']]
										: ['checked' => true, 'onchange' => "api.file('put', 'externalfilemanager', '" . $file['id'] . "', this.checked ? 1 : 0)", 'data-filtered' => $file['path']])
									],
								],
								[
									'type' => 'checkboxinput',
									'content' => $regulatory_context,
									'attributes' => [
										'name' => LANG::GET('assemble.compose_form_regulatory_context'),
										'onchange' => "api.file('put', 'externalfilemanager', '" . $file['id'] . "', this.value)",
										'data-filtered' => $file['path']
									],
								]
							);
						}
					}
				}
				else $result['body']['content'] = $this->noContentAvailable(LANG::GET('file.no_files'));
				$result['body']['content'][]=[
					[
						'type' => 'file',
						'attributes' => [
							'name' => LANG::GET('file.manager_new_file'),
							'multiple' => true,
							'required' => true
						],
						'hint' => LANG::GET('file.external_file_hint')
					]
				];
				$this->response($result);
				break;
		}
	}

	/**
	 * responds with content of available file bundles
	 */
	public function bundle(){
		$result['body'] = [
			'content' => [
				[
					[
						'type' => 'filterinput',
						'attributes' => [
							'name' => LANG::GET('file.bundle_filter_label'),
							'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'bundlefilter', this.value); return false;}",
							'onblur' => "api.file('get', 'bundlefilter', this.value); return false;",
							'id' => 'filesearch'
						]
					]
				]
			]
		];
		$bundles = SQLQUERY::EXECUTE($this->_pdo, 'file_bundles_get_active');
		foreach($bundles as $row) {
			$list=[];
			foreach (json_decode($row['content'], true) as $file => $path){
				$list[substr_replace($file, '.', strrpos($file, '_'), 1)]= ['href' => $path, 'target' => '_blank', 'data-filtered' => 'breakline'
			];
			}
			$result['body']['content'][]=
			[
				[
					'type' => 'links',
					'description' => $row['name'],
					'content' => $list,
					'attributes' => [
						'data-filtered' => $row['id']
					]
				]
			];
		}
		$this->response($result);
	}

	/**
	 * get responds with form to select and save file bundles
	 * post responds with success state of saving
	 * 
	 * no delete nor put for audit safety
	 */
	public function bundlemanager(){
		if (!PERMISSION::permissionFor('filebundles')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$unset = LANG::PROPERTY('file.edit_existing_bundle_select');
				unset ($this->_payload->$unset);
				$unset = LANG::PROPERTY('file.edit_existing_bundle');
				unset ($this->_payload->$unset);
				$save_name = LANG::PROPERTY('file.edit_save_bundle');
				$name = $this->_payload->$save_name;
				unset ($this->_payload->$save_name);
				$active = LANG::PROPERTY('file.edit_bundle_active');
				$isactive = $this->_payload->$active === LANG::GET('file.edit_active_bundle') ? 1 : 0;
				unset ($this->_payload->$active);

				if (SQLQUERY::EXECUTE($this->_pdo, 'file_bundles_post', [
					'values' => [
					':name' => $name,
					':content' => json_encode($this->_payload),
					':active' => $isactive
					]
				])) $this->response([
						'status' => [
							'name' => $name,
							'msg' => LANG::GET('file.edit_bundle_saved', [':name' => $name]),
							'type' => 'success'
						]]);
					else $this->response([
						'status' => [
							'name' => false,
							'msg' => LANG::GET('file.edit_bundle_not_saved'),
							'type' => 'error'
						]]);
				break;
			case 'GET':
				$datalist = [];
				$options = ['...' => []];
				$return = [];
				
				$bundle = SQLQUERY::EXECUTE($this->_pdo, 'file_bundles_get', [
					'values' => [
						':name' => $this->_requestedFolder
					]
				]);
				$bundle = $bundle ? $bundle[0] : null;
				if (!$bundle) $bundle = ['name' => '', 'content' => '', 'active' => null];
				if($this->_requestedFolder && $this->_requestedFolder !== 'false' && !$bundle['name']) $return['status'] = ['msg' => LANG::GET('file.bundle_error_not_found', [':name' => $this->_requestedFolder]), 'type' => 'error'];

				// prepare existing bundle lists
				$bundles = SQLQUERY::EXECUTE($this->_pdo, 'file_bundles_datalist');
				foreach($bundles as $row) {
					$datalist[] = $row['name'];
					$options[$row['name']] = $bundle['name'] === $row['name'] ? ['selected' => true] : [];
				}
				
				$return['body'] = [
					'form' => [
						'data-usecase' => 'file',
						'action' => "javascript:api.file('post', 'bundlemanager')"
					],
					'content' => [
						[
							[
								'type' => 'datalist',
								'content' => array_values(array_unique($datalist)),
								'attributes' => [
									'id' => 'bundles'
								]
							],
							[
								'type' => 'select',
								'attributes' => [
									'name' => LANG::GET('file.edit_existing_bundle_select'),
									'onchange' => "api.file('get', 'bundlemanager', this.value)"
								],
								'content' => $options
							],
							[
								'type' => 'searchinput',
								'attributes' => [
									'name' => LANG::GET('file.edit_existing_bundle'),
									'list' => 'bundles',
									'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'bundlemanager', this.value); return false;}"
								]
							]
						]]];

				$files = [];
				$bundle['content']= json_decode($bundle['content'], true);
				$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
				foreach ($folders as $folder) {
					$files[$folder] = UTILITY::listFiles($folder ,'asc');
				}
				if ($external = $this->activeexternalfiles()) $files[UTILITY::directory('external_documents')] = $external;
				$filePerSlide = 0;
				$matches = [];
				$currentfolder = '';
				foreach ($files as $folder => $content){
					foreach ($content as $file){
						$pathinfo = pathinfo($file);
						$file = ['path' => substr($file, 1), 'file' => $pathinfo['basename'], 'folder' => $pathinfo['dirname']];
						if ($currentfolder != $file['folder']) {
							$matches[] = [];
							$currentfolder = $file['folder'];
							$filePerSlide = 0;
						}
						$article = intval(count($matches) - 1);
						if (empty($filePerSlide++ % INI['splitresults']['bundle_files_per_slide'])){
							$matches[$article][] = [
								[
									'type' => 'checkbox',
									'description' => LANG::GET('file.file_list', [':folder' => $folder]),
									'content' => []
								]
							];
						}
						$slide = intval(count($matches[$article]) - 1);
						$matches[$article][$slide][0]['content'][$file['file']] = ['value' => $file['path']];
						if ($bundle['content'] && in_array($file['path'], array_values($bundle['content']))) $matches[$article][$slide][0]['content'][$file['file']]['checked'] = true;
					}
				}
				foreach ($matches as $folder) {
					$return['body']['content'][] = $folder;
				}
				$isactive = $bundle['active'] ? ['checked' => true] : [];
				$isinactive = !$bundle['active'] ? ['checked' => true] : [];
				$return['body']['content'][] = [
					[
						'type' => 'textinput',
						'attributes'=>[
							'name'=> LANG::GET('file.edit_save_bundle'),
							'value' => $bundle['name']
						]
					],
					[
						'type' => 'radio',
						'attributes' => [
							'name' => LANG::GET('file.edit_bundle_active')
						],
						'content'=>[
							LANG::GET('file.edit_active_bundle')=>$isactive,
							LANG::GET('file.edit_inactive_bundle')=>$isinactive,
						]
					]
				];
				if ($bundle['name']) $return['header'] = $bundle['name'];
				$this->response($return);
				break;
		}
	}

	/**
	 * get responds with content of available files, clears overdue files according to defined lifespan
	 * post responds with success state of saving
	 * 
	 * no delete for being automated
	 */
	public function sharepoint(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (array_key_exists(LANG::PROPERTY('file.sharepoint_upload_header'), $_FILES) && $_FILES[LANG::PROPERTY('file.sharepoint_upload_header')]['tmp_name']) {
					UTILITY::storeUploadedFiles([LANG::PROPERTY('file.sharepoint_upload_header')], UTILITY::directory('sharepoint'), [$_SESSION['user']['name']]);
					$this->response(['status' => [
						'msg' => LANG::GET('file.manager_new_file_created'),
						'redirect' => ['sharepoint'],
						'type' => 'success'
					]]);
				}
				$this->response(['status' => [
					'msg' => LANG::GET('file.manager_error'),
					'type' => 'error'
				]]);
		break;
			case 'GET':
				$result=['body'=>
				[
					'form' => [
						'data-usecase' => 'file',
						'action' => "javascript:api.file('post', 'sharepoint')"],
						'content'=>[]
					]
				];

				$files = UTILITY::listFiles(UTILITY::directory('sharepoint'),'asc');
				$display=[];
				if ($files){
					foreach ($files as $file){
						$file = ['path' => $file, 'name' => pathinfo($file)['basename']];
						$filetime = filemtime($file['path']);
						if ((time()-$filetime)/3600 > INI['lifespan']['sharepoint']) {
							UTILITY::delete($file['path']);
						}
						else {
							$name = $file['name'] . ' ' . LANG::GET('file.sharepoint_file_lifespan', [':hours' => round(($filetime + INI['lifespan']['sharepoint']*3600 - time()) / 3600, 1)]);
							$display[$name] = ['href' => substr($file['path'], 1), 'data-filtered' => substr($file['path'], 1), 'target' => '_blank'];
						}
					}
				}
				if ($display){
					$result['body']['content'][]=[
						[
							'type' => 'filterinput',
							'attributes' => [
								'name' => LANG::GET('file.file_filter_label'),
								'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'filter', 'sharepoint', this.value); return false;}",
								'onblur' => "api.file('get', 'filter', 'sharepoint', this.value); return false;",
								'id' => 'filefilter'
							]
						]
					];
					$result['body']['content'][] = [
						[
							'type' => 'links',
							'content' => $display
						]
					];
				}
				else $result['body']['content'] = $this->noContentAvailable(LANG::GET('file.no_files'));
				$result['body']['content'][]=[
					[
						'type' => 'file',
						'attributes' => [
							'name' => LANG::GET('file.sharepoint_upload_header'),
							'multiple' => true,
							'required' => true
						]
					]
				];
				$this->response($result);
				break;
		}
	}
}
?>
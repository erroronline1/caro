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

// add, edit and delete files
class FILE extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	public $_requestedFolder = null;
	public $_requestedId = null;
	public $_requestedFile = null;
	public $_accessible = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user'])) $this->response([], 401);
		if (array_intersect(['patient'], $_SESSION['user']['permissions']) && 
			!in_array(REQUEST[1], ['stream'])
		) $this->response([], 401);

		$this->_requestedFolder = $this->_requestedId = isset(REQUEST[2]) && REQUEST[2] !== 'null' ? REQUEST[2] : null;
		$this->_requestedFile = $this->_accessible = isset(REQUEST[3]) ? REQUEST[3] : null;
	}

	/**
	 *           _   _                 _                   _ ___ _ _
	 *   ___ ___| |_|_|_ _ ___ ___ _ _| |_ ___ ___ ___ ___| |  _|_| |___ ___
	 *  | .'|  _|  _| | | | -_| -_|_'_|  _| -_|  _|   | .'| |  _| | | -_|_ -|
	 *  |__,|___|_| |_|\_/|___|___|_,_|_| |___|_| |_|_|__,|_|_| |_|_|___|___|
	 *
	 * returns paths to documents that are available according to database
	 * @return array filepaths
	 */
	private function activeexternalfiles(){
		$files = SQLQUERY::EXECUTE($this->_pdo, 'file_external_documents_get_active');
		if ($files) return array_column($files, 'path');
		return [];
	}

	
	/**
	 *           _                   _ ___ _ _
	 *   ___ _ _| |_ ___ ___ ___ ___| |  _|_| |___ _____ ___ ___ ___ ___ ___ ___
	 *  | -_|_'_|  _| -_|  _|   | .'| |  _| | | -_|     | .'|   | .'| . | -_|  _|
	 *  |___|_,_|_| |___|_| |_|_|__,|_|_| |_|_|___|_|_|_|__,|_|_|__,|_  |___|_|
	 *                                                              |___|
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
				// $_FILES is submitted always even if empty
				if (isset($_FILES[$this->_lang->PROPERTY('file.manager.new_file')]) && $_FILES[$this->_lang->PROPERTY('file.manager.new_file')]['tmp_name']) {
					// process provided files 
					$files = UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('file.manager.new_file')], UTILITY::directory('external_documents'));
					$insertions = [];
					foreach ($files as $file){
						$insertions[] = [
							':author' => $_SESSION['user']['name'],
							':path' => $file
						];
					}

					// process provided linkes ressources
					foreach ($this->_payload as $key => $value){
						if (preg_match("/^" . $this->_lang->PROPERTY('file.external_file.link') . "/", $key) && $value && preg_match("/(?:^href=')(.+?)(?:')/", $value, $link)){
							$insertions[] = [
								':author' => $_SESSION['user']['name'],
								':path' => $link[1]
							];
						}
					}

					// insert files and ressources to database
					$sqlchunks = SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('file_external_documents_post'), $insertions);
					$success = false;
					foreach ($sqlchunks as $chunk){
						$success = false;
						try {
							if (SQLQUERY::EXECUTE($this->_pdo, $chunk)) $success = true;
						}
						catch (\Exception $e) {
							echo $e, $chunk;
							die();
						}
					}
					if ($success){		
						$this->response(['response' => [
							'msg' => $this->_lang->GET('file.manager.new_file_created'),
							'type' => 'success'
						]]);
					}
					$this->response(['response' => [
						'msg' => $this->_lang->GET('file.manager.error'),
						'type' => 'error'
					]]);
				}
				break;
			case 'PUT':
				// update availability or regulatory context
				// this is updated on the fly on selection within the overview
				switch ($this->_accessible){
					case '0':
						$prepare = 'file_external_documents_retire';
						$tokens = [
							':author' => $_SESSION['user']['name'],
							':id' => $this->_requestedId
						];
						$response = $this->_lang->GET('file.external_file.retired_success');
						break;
					case '1':
						$prepare = 'file_external_documents_unretire';
						$tokens = [
							':author' => $_SESSION['user']['name'],
							':id' => $this->_requestedId
						];
						$response = $this->_lang->GET('file.external_file.available_success');
						break;
					default:
						$regulatory_context = [];
						foreach (explode(', ', $this->_accessible) as $context){
							$regulatory_context[] = array_search($context, $this->_lang->_USER['regulatory']); 
						}
						$prepare = 'file_external_documents_context';
						$tokens = [
							':regulatory_context' => implode(',', $regulatory_context),
							':id' => $this->_requestedId
						];
						$response = $this->_lang->GET('file.external_file.regulatory_context');
				}
				// process prepared database update
				if ($this->_requestedId && SQLQUERY::EXECUTE($this->_pdo, $prepare, [
					'values' => $tokens
				])) $this->response(['response' => [
						'msg' => $response,
						'type' => 'success'
					]]);
				else $this->response(['response' => [
					'msg' => $this->_lang->GET('file.manager.error'),
					'type' => 'error'
				]]);
				break;
			case 'GET':
				$response = ['render' => ['form' => [
					'data-usecase' => 'file',
					'action' => "javascript:api.file('post', 'externalfilemanager')"],
					'content' => []
				]];

				// retrieve all external documents per database
				$files = SQLQUERY::EXECUTE($this->_pdo, 'file_external_documents_get');		
				if ($files){
					// append filter input
					$response['render']['content'][] = [
						[
							'type' => 'filtered',
							'attributes' => [
								'name' => $this->_lang->GET('file.file_filter_label'),
								'onkeydown' => "if (event.key === 'Enter') {api.file('get', 'filter', 'external_documents', this.value)}",
								'id' => 'filefilter'
							]
						]
					];
					$response['render']['content'][] = [];
					foreach ($files as $file){
						if ($file) {
							// distinguish between uploaded files and linked ressources
							$fileinfo = pathinfo($file['path']);

							if (preg_match('/^\.\.\//', $file['path'])){
								$file['name'] = $fileinfo['basename'];
								$file['path'] = './api/api.php/file/stream/' . substr($file['path'], 1);
							}
							else $file['name'] = $file['path'];
							// resolve regulatory context
							$regulatory_context = [];
							$file['regulatory_context'] = explode(',', $file['regulatory_context']);
							foreach ($this->_lang->_USER['regulatory'] as $key => $value){
								$regulatory_context[$value] = ['value' => $key];
								if (in_array($key, $file['regulatory_context'])) $regulatory_context[$value]['checked'] = true;
							}

							$link = [];
							$link[$file['name']] = UTILITY::link(['href' => $file['path'], 'data-filtered' => $file['path']]);
			
							// append file, link and options
							array_push($response['render']['content'][1],
								[
									'type' => 'links',
									'description' => ($file['retired'] ? $this->_lang->GET('file.external_file.retired', [':user' => $file['author'], ':introduced' => $file['activated'], ':retired' => $this->convertFromServerTime($file['retired'])]) : $this->_lang->GET('file.external_file.introduced', [':user' => $file['author'], ':introduced' => $file['activated']])),
									'content' => $link,
									'data-filtered' => $file['path']
								],
								[
									'type' => 'button',
									'attributes' => [
										'value' => $this->_lang->GET('file.manager.copy_path'),
										'onclick' => "_client.application.toClipboard('" . $file['path'] . "')",
										'class' => 'inlinebutton',
										'data-filtered' => $file['path'],
										'data-type' => 'copy'
									]
								],
								[
									'type' => 'checkbox',
									'content' => [
										$this->_lang->GET('file.external_file.available') => ($file['activated'] && !$file['retired']
										? ['checked' => true, 'onchange' => "api.file('put', 'externalfilemanager', '" . $file['id'] . "', this.checked ? 1 : 0)", 'data-filtered' => $file['path']]
										: ['onchange' => "api.file('put', 'externalfilemanager', '" . $file['id'] . "', this.checked ? 1 : 0)", 'data-filtered' => $file['path']])
									],
								],
								[
									'type' => 'checkbox2text',
									'content' => $regulatory_context,
									'attributes' => [
										'name' => $this->_lang->GET('assemble.compose.document.regulatory_context'),
										'onchange' => "api.file('put', 'externalfilemanager', '" . $file['id'] . "', this.value)",
										'data-filtered' => $file['path']
									],
									'numeration' => 'none'
								]
							);
						}
					}
				}
				else $response['render']['content'] = $this->noContentAvailable($this->_lang->GET('file.no_files'));

				// append submission inputs for new files and ressources
				$response['render']['content'][] = [
					[
						'type' => 'link',
						'attributes' => [
							'name' => $this->_lang->GET('file.external_file.link'),
							'multiple' => true
						]
					],
					[
						'type' => 'file',
						'attributes' => [
							'name' => $this->_lang->GET('file.manager.new_file'),
							'multiple' => true
						],
						'hint' => $this->_lang->GET('file.external_file.hint')
					]
				];
				$this->response($response);
				break;
		}
	}
	
	/**
	 *   ___ _ _
	 *  |  _|_| |___ _____ ___ ___ ___ ___ ___ ___
	 *  |  _| | | -_|     | .'|   | .'| . | -_|  _|
	 *  |_| |_|_|___|_|_|_|__,|_|_|__,|_  |___|_|
	 *                                |___|
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
				// overview allows only to create new folder or upload files to a previously selected one

				// sanitize new folder name
				$new_folder = preg_replace(['/[\s-]{1,}/', '/\W/'], ['_', ''], UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('file.manager.new_folder')));
				if ($new_folder){
					// check forbidden names
					if (UTILITY::forbiddenName($new_folder)) $this->response(['response' => ['msg' => $this->_lang->GET('file.manager.new_folder_forbidden_name', [':name' => $new_folder]), 'type' => 'error']]);

					// create new folder if not present
					$new_folder = UTILITY::directory('files_documents', [':category' => $new_folder]);
					UTILITY::storeUploadedFiles([], $new_folder);

					$this->response(['response' => [
						'msg' => $this->_lang->GET('file.manager.new_folder_created', [':name' => $new_folder]),
						'redirect' => ['filemanager'],
						'type' => 'success'
						]]);
				}

				// store uploaded files to reqested folder
				$destination = UTILITY::propertySet($this->_payload, 'destination');
				if (isset($_FILES[$this->_lang->PROPERTY('file.manager.new_file')]) && $_FILES[$this->_lang->PROPERTY('file.manager.new_file')]['tmp_name'] && $destination) {
					UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('file.manager.new_file')], UTILITY::directory('files_documents', [':category' => $destination]));
					$this->response(['response' => [
						'msg' => $this->_lang->GET('file.manager.new_file_created'),
						'redirect' => ['filemanager', $destination],
						'type' => 'success'
					]]);
				}
				$this->response(['response' => [
					'msg' => $this->_lang->GET('file.manager.error'),
					'type' => 'error'
				]]);
		break;
			case 'GET':
				$response = ['render' => [
					'form' => [
						'data-usecase' => 'file',
						'action' => "javascript:api.file('post', 'filemanager')"
					],
					'content' => []
				]];

				// default view lists available custom directories within files_documents
				if (!$this->_requestedFolder){
					$response['render']['content'][] = [];
					$folders = UTILITY::listDirectories(UTILITY::directory('files_documents'),'asc');
					if ($folders){
						foreach ($folders as $folder){
							// prepare each folders properties
							$foldername = str_replace(UTILITY::directory('files_documents') . '/', '', $folder);
							$filedate = new \DateTime('@' . filemtime($folder), new \DateTimeZone($this->_date['timezone']));
							// append folder link and delete button
							array_push($response['render']['content'][0],
								[
									'type' => 'links',
									'description' => $this->_lang->GET('file.manager.folder_header', [':date' => $this->convertFromServerTime($filedate->format('Y-m-d H:i'))]),
									'content' => [
										$foldername => ['href' => "javascript:api.file('get', 'filemanager', '" . $foldername . "')"]
									]
								],
								[
									'type' => 'deletebutton',
									'attributes' => [
										'value' => $this->_lang->GET('file.manager.delete_folder'),
										'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('file.manager.delete_file_confirmation_header', [':file' => $foldername]) ."', options:{".
											"'".$this->_lang->GET('file.manager.delete_file_confirmation_cancel')."': false,".
											"'".$this->_lang->GET('file.manager.delete_file_confirmation_ok')."': {value: true, class: 'reducedCTA'},".
											"}}).then(confirmation => {if (confirmation) api.file('delete', 'filemanager', '" . $foldername . "')})"
									]
								]
							);
						}
					}

					// append entry point to sharepoint as authorized users can delete files prematurely
					array_push($response['render']['content'][0],
						[
							'type' => 'links',
							'description' => $this->_lang->GET('file.navigation.sharepoint'),
							'content' => [
								$this->_lang->GET('file.navigation.sharepoint') => ['href' => "javascript:api.file('get', 'filemanager', 'sharepoint')"]
							]
						]
					);

					// append input for new folder
					$response['render']['content'][] = [
						[
							'type' => 'text',
							'attributes' => [
								'name' => $this->_lang->GET('file.manager.new_folder'),
								'required' => true
							],
							'hint' => $this->_lang->GET('file.manager.no_external_files')
						]
					];
				}
				else {
					// gather files for requested directory
					if ($this->_requestedFolder === 'sharepoint') $files = UTILITY::listFiles(UTILITY::directory('sharepoint') ,'asc');
					else $files = UTILITY::listFiles(UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]) ,'asc');
					if ($files){
						// append file filter
						$response['render']['content'][] = [
							[
								'type' => 'filtered',
								'attributes' => [
									'name' => $this->_lang->GET('file.file_filter_label'),
									'onkeydown' => "if (event.key === 'Enter') {api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', this.value)}",
									'id' => 'filefilter'
								]
							]
						];
						$response['render']['content'][] = [];
						foreach ($files as $file){
							if ($file) {
								// set up file properties
								$fileinfo = pathinfo($file);

								$file = [
									'path' => substr($file, 1),
									'name' => $fileinfo['basename'],
									'link' => './api/api.php/file/stream/' . substr($file, 1)
								];
								$filedate = new \DateTime('@' . filemtime('.' . $file['path']), new \DateTimeZone($this->_date['timezone']));

								$link = [];
								$link[$file['name']] = UTILITY::link(['href' => $file['link'], 'data-filtered' => $file['path']]);
	
								// append file options
								array_push($response['render']['content'][1],
									[
										'type' => 'links',
										'description' => $this->convertFromServerTime($filedate->format('Y-m-d H:i')),
										'content' => $link,
										'data-filtered' => $file['path']
									],
									[
										'type' => 'button',
										'attributes' => [
											'value' => $this->_lang->GET('file.manager.copy_path'),
											'onclick' => "_client.application.toClipboard('" . $file['link'] . "')",
											'class' => 'inlinebutton',
											'data-type' => 'copy',
											'data-filtered' => $file['path']
										]
									],
									[
										'type' => 'deletebutton',
										'attributes' => [
											'value' => $this->_lang->GET('file.manager.delete_file'),
											'data-filtered' => $file['path'],
											'onclick' => "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('file.manager.delete_file_confirmation_header', [':file' => $file['name']]) ."', options:{".
												"'".$this->_lang->GET('file.manager.delete_file_confirmation_cancel')."': false,".
												"'".$this->_lang->GET('file.manager.delete_file_confirmation_ok')."': {value: true, class: 'reducedCTA'},".
												"}}).then(confirmation => {if (confirmation) api.file('delete', 'filemanager', '" . $this->_requestedFolder . "', '" . $file['name'] . "')})"
										]
									]
								);
							}
						}
					}
					else $response['render']['content'] = $this->noContentAvailable($this->_lang->GET('file.no_files'));

					// sharepoint has no option to add files from this place
					if (in_array($this->_requestedFolder, ['sharepoint'])) unset ($response['render']['form']);
					// but any other folder gets one
					else $response['render']['content'][] = [
						[
							'type' => 'hidden',
							'attributes' => [
								'name' => 'destination',
								'value' => $this->_requestedFolder
							]
						],
						[
							'type' => 'file',
							'attributes' => [
								'name' => $this->_lang->GET('file.manager.new_file'),
								'multiple' => true,
								'required' => true
							],
							'hint' => $this->_lang->GET('file.manager.no_external_files')
						]
					];
				}
				$this->response($response);
				break;
			case 'DELETE':
				if (in_array($this->_requestedFolder, ['sharepoint'])) $success = UTILITY::delete(UTILITY::directory($this->_requestedFolder) . '/' . $this->_requestedFile);
				else $success = UTILITY::delete(UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]) . ($this->_requestedFile ? '/' . $this->_requestedFile : ''));
				if ($success) $this->response(['response' => [
					'msg' => $this->_lang->GET('file.manager.deleted_file', [':file' => $this->_requestedFile ? : $this->_requestedFolder]),
					'redirect' => ['filemanager',  $this->_requestedFile ? $this->_requestedFolder : null],
					'type' => 'deleted'
				]]);
				else $this->response(['response' => [
					'msg' => $this->_lang->GET('file.manager.error'),
					'type' => 'error'
				]]);
				break;
		}
	}

	/**
	 *   ___ _ _
	 *  |  _|_| |___ ___
	 *  |  _| | | -_|_ -|
	 *  |_| |_|_|___|___|
	 *
	 * responds with content of available files for restricted uploads and external documents
	 */
	public function files(){
		// append file filter
		$response['render'] = [
		'content' => [
				[
					[
						'type' => 'filtered',
						'attributes' => [
							'name' => $this->_lang->GET('file.file_filter_label'),
							'onkeydown' => "if (event.key === 'Enter') {api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', encodeURIComponent(this.value));}",
							'id' => 'filesearch'
						]
					]
				]
			]
		];
		// append filter by folder option
		$options = [
			$this->_lang->GET('file.navigation.files') => (!$this->_requestedFolder || $this->_requestedFolder == 'null') ? ['checked' => true] : ['onchange' => "api.file('get', 'files')"],
			$this->_lang->GET('file.external_file.folder') => $this->_requestedFolder === 'external_documents' ? ['checked' => true] : ['onchange' => "api.file('get', 'files', 'external_documents')"],

		];
		foreach (UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc') as $folder){
			$folder = pathinfo($folder)['basename'];
			$options[$folder] = $this->_requestedFolder === $folder ? ['checked' => true] : [];
			$options[$folder]['onchange'] = "api.file('get', 'files', '" . $folder . "')";
		}
		$response['render']['content'][count($response['render']['content']) - 1 ][] = [
			'type' => 'radio',
			'attributes' => [
				'name' => $this->_lang->GET('file.file_filter_folder')
			],
			'content' => $options
		];

		// gather files by requestedFolder
		$files = [];

		switch ($this->_requestedFolder){
			case null:
			case 'null':
				// add external files by default if no folder is requested
				if ($external = $this->activeexternalfiles()) $files[UTILITY::directory('external_documents')] = $external;
				$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
				foreach ($folders as $folder) {
					$files[$folder] = UTILITY::listFiles($folder ,'asc');
				}
				break;
			case 'external_documents':
				if ($external = $this->activeexternalfiles()) $files[UTILITY::directory('external_documents')] = $external;
				break;
			default:
				$folder = UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]);
				$files[$folder] = UTILITY::listFiles($folder ,'asc');
		}

		if ($files){
			foreach ($files as $folder => $content){
				// display files and linked ressources by folder
				$matches = [];
				foreach ($content as $file){
					// distinguish between uploaded files and linked ressources
					$fileinfo = pathinfo($file);
					if (preg_match('/^\.\.\//', $file))	$file = ['name' => $fileinfo['basename'], 'path' => './api/api.php/file/stream/' . substr($file, 1)];
					else $file = ['name' => $file, 'path' => $file];

					$matches[$file['name']] = UTILITY::link(['href' => $file['path'], 'data-filtered' => $file['path']]);
				}

				// reassign displayed folder name
				$folder = $folder === UTILITY::directory('external_documents') ? $this->_lang->GET('file.external_file.folder') : pathinfo($folder)['filename'];
				// append folder
				$response['render']['content'][] =
				[
					[
						'type' => 'links',
						'description' => $this->_lang->GET('file.file_list', [':folder' => $folder]),
						'content' => $matches
					]
				];
			}
		}
		else $response['render']['content'] = $this->noContentAvailable($this->_lang->GET('file.no_files'));

		if (PERMISSION::permissionFor('files')){
			$response['render']['content'][] = [
				[
					'type' => 'button',
					'attributes' => [
						'value' => $this->_lang->GET('file.navigation.file_manager'),
						'onclick' => "api.file('get', 'filemanager')"
					]
				]
			];
		}
		if (PERMISSION::permissionFor('externaldocuments')){
			$response['render']['content'][] = [
				[
					'type' => 'button',
					'attributes' => [
						'value' => $this->_lang->GET('file.navigation.external_file_manager'),
						'onclick' => "api.file('get', 'externalfilemanager')"
					]
				]
			];
		}

		$this->response($response);
	}
	
	/**
	 *   ___ _ _ _
	 *  |  _|_| | |_ ___ ___
	 *  |  _| | |  _| -_|  _|
	 *  |_| |_|_|_| |___|_|
	 *
	 * filters files according to request string and passed folder if applicable
	 * responds with paths matching request
	 */
	public function filter(){
		require_once('_shared.php');
		$search = new SEARCHHANDLER($this->_pdo, $this->_date);
		$matches = [];
		if ($files = $search->filesearch(['search' => $this->_requestedFile, 'folder' => $this->_requestedFolder === 'null' ? null : $this->_requestedFolder])){
			foreach ($files as $file){
				$matches[] = preg_match('/^\.\.\//', $file) ? substr($file, 1) : $file;
			}
		}
		$this->response([
			'data' => $matches
		]);
	}

	/**
	 *       _                       _     _
	 *   ___| |_ ___ ___ ___ ___ ___|_|___| |_
	 *  |_ -|   | .'|  _| -_| . | . | |   |  _|
	 *  |___|_|_|__,|_| |___|  _|___|_|_|_|_|
	 *                      |_|
	 * get responds with content of available files, clears overdue files according to defined lifespan
	 * post responds with success state of saving
	 * 
	 * no delete for being automated
	 */
	public function sharepoint(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (isset($_FILES[$this->_lang->PROPERTY('file.sharepoint_upload_header')]) && $_FILES[$this->_lang->PROPERTY('file.sharepoint_upload_header')]['tmp_name']) {
					UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('file.sharepoint_upload_header')], UTILITY::directory('sharepoint'), [$_SESSION['user']['name']]);
					$this->response(['response' => [
						'msg' => $this->_lang->GET('file.manager.new_file_created'),
						'redirect' => ['sharepoint'],
						'type' => 'success'
					]]);
				}
				$this->response(['response' => [
					'msg' => $this->_lang->GET('file.manager.error'),
					'type' => 'error'
				]]);
		break;
			case 'GET':
				$response = ['render' => [
					'form' => [
						'data-usecase' => 'file',
						'action' => "javascript:api.file('post', 'sharepoint')"
					],
					'content' => []
				]];

				// gather sharepoint files
				$files = UTILITY::listFiles(UTILITY::directory('sharepoint'),'asc');
				$display = [];
				if ($files){
					foreach ($files as $file){
						// prepare file properies and calculate remaining lifespan
						$file = ['path' => $file, 'name' => pathinfo($file)['basename']];
						$filetime = filemtime($file['path']);

						// delete expired files
						// tidied up in cron job as well, but duplicate to avoid a faulty lifespan display
						if ((time() - $filetime) / 3600 > CONFIG['lifespan']['files']['sharepoint']) {
							UTILITY::delete($file['path']);
						}
						// add remaining files
						else {
							$file['path'] = './api/api.php/file/stream/' . substr($file['path'], 1);
							$name = $file['name'] . ' ' . $this->_lang->GET('file.sharepoint_file_lifespan', [':hours' => round(($filetime + CONFIG['lifespan']['files']['sharepoint']*3600 - time()) / 3600, 1)]);

							$display[$name] = UTILITY::link(['href' => $file['path'], 'data-filtered' => $file['path']]);
						}
					}
				}
				if ($display){
					// append filter and sharepoint files
					$response['render']['content'][] = [
						[
							'type' => 'filtered',
							'attributes' => [
								'name' => $this->_lang->GET('file.file_filter_label'),
								'onkeydown' => "if (event.key === 'Enter') {api.file('get', 'filter', 'sharepoint', this.value)}",
								'id' => 'filefilter'
							]
						]
					];
					$response['render']['content'][] = [
						[
							'type' => 'links',
							'content' => $display
						]
					];
				}
				else $response['render']['content'] = $this->noContentAvailable($this->_lang->GET('file.no_files'));

				// append upload input
				$response['render']['content'][] = [
					[
						'type' => 'file',
						'attributes' => [
							'name' => $this->_lang->GET('file.sharepoint_upload_header'),
							'multiple' => true,
							'required' => true
						],
						'hint' => $this->_lang->GET('file.sharepoint_lifespan_hint', [':hours' => CONFIG['lifespan']['files']['sharepoint']])
					]
				];
				$this->response($response);
				break;
		}
	}

	/**
	 *       _                     
	 *   ___| |_ ___ ___ ___ _____ 
	 *  |_ -|  _|  _| -_| .'|     |
	 *  |___|_| |_| |___|__,|_|_|_|
	 * 
	 * streams a file as requested by following url parameters
	 * ensuring a requested file is accessed by a valid user since directories are restricted by default
	 */
	public function stream(){
		$file = realpath('../' . implode('/', array_slice(REQUEST, 2)));
		if ($file){
			// filter inactive external files
			if (stristr($file, CONFIG['fileserver']['external_documents']) && !in_array('../' . substr($file, stripos($file, CONFIG['fileserver']['external_documents'])), $this->activeexternalfiles())) {
				http_response_code(410);
				echo $this->_lang->GET('file.external_file.retired_success');
				die();
			}
			header('Content-Type: '.mime_content_type($file));
			header('Content-Disposition: inline; filename=' . pathinfo($file)['basename']);
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: '.filesize($file));
			ob_clean();
			flush();
			readfile($file);
			exit;
		}
		http_response_code(410);
		echo $this->_lang->GET('file.external_file.retired_success');
		die();
	}
}
?>
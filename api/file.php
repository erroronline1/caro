<?php
// add, edit and delete users
class FILE extends API {
	// processed parameters for readability
	public $_requestedMethod = REQUEST[1];
	public $_requestedFolder = null;
	public $_requestedFile = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedFolder = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_requestedFile = array_key_exists(3, REQUEST) ? REQUEST[3] : null;
	}

	public function filter(){
		if ($this->_requestedFolder && $this->_requestedFolder == 'sharepoint') $files = UTILITY::listFiles(UTILITY::directory('sharepoint') ,'asc');
		else {
			$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
			$files = [];
			foreach ($folders as $folder) {
				$files = array_merge($files, UTILITY::listFiles($folder ,'asc'));
			}
		}
		$matches = [];
		foreach ($files as $file){
			similar_text($this->_requestedFile, pathinfo($file)['filename'], $percent);
			if ($percent >= INI['likeliness']['file_search_similarity'] || !$this->_requestedFile) $matches[] = substr($file,1);
		}
		$this->response(['status' => [
			'data' => $matches
		]]);
	}

	public function bundlefilter(){
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('file_bundles-get-active'));
		$statement->execute();
		$bundles = $statement->fetchAll(PDO::FETCH_ASSOC);
		$matches = [];
		foreach($bundles as $row) {
			similar_text($this->_requestedFolder, $row['name'], $percent);
			if ($percent >= INI['likeliness']['file_search_similarity'] || !$this->_requestedFolder) $matches[] = strval($row['id']);
		}
		$this->response(['status' => [
			'data' => $matches
		]]);
	}

	public function files(){
		if (!(array_intersect(['admin', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
		$result['body'] = [
		'content' => [
				[
					['type' => 'filterinput',
					'attributes' => [
						'name' => LANG::GET('file.file_filter_label'),
						'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', this.value); return false;}",
						'onblur' => "api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', this.value); return false;",
						'id' => 'filesearch'
					]]
				]
			]
		];
		$files = [];
		if ($this->_requestedFolder && $this->_requestedFolder != 'null') {
			$folder = UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]);
			$files[$folder] = UTILITY::listFiles($folder ,'asc');
		}
		else {
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
					['type' => 'links',
					'description' => LANG::GET('file.file_list', [':folder' => $folder]),
					'content' => $matches
					]
				];
			}
		}
		else $result['body']['content'] = $this->noContentAvailable(LANG::GET('file.no_files'));
		$this->response($result);
	}

	public function filemanager(){
		/**
		 * no put method for windows server permissions are a pita
		 * thus directories can not be renamed
		 */
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$new_folder = preg_replace(['/[\s-]{1,}/', '/\W/'], ['_', ''], UTILITY::propertySet($this->_payload, LANG::PROPERTY('file.manager_new_folder')));
				if ($new_folder){
					foreach(INI['forbidden']['names'] as $pattern){
						if (preg_match("/" . $pattern . "/m", $new_folder, $matches)) $this->response(['status' => ['msg' => LANG::GET('file.manager_new_folder_forbidden_name', [':name' => $new_folder])]]);
					}
					$new_folder = UTILITY::directory('files_documents', [':category' => $new_folder]);
					UTILITY::storeUploadedFiles([], $new_folder);
					$this->response(['status' => [
						'msg' => LANG::GET('file.manager_new_folder_created', [':name' => $new_folder]),
						'redirect' => ['filemanager']
						]]);
				}
				$destination = UTILITY::propertySet($this->_payload, 'destination');
				if (array_key_exists('files', $_FILES) && $_FILES['files']['tmp_name'] && $destination) {
					UTILITY::storeUploadedFiles(['files'], UTILITY::directory('files_documents', [':category' => $destination]));
					$this->response(['status' => [
						'msg' => LANG::GET('file.manager_new_file_created'),
						'redirect' => ['filemanager', $destination]
					]]);
				}
				$this->response(['status' => [
					'msg' => LANG::GET('file.manager_error')
				]]);
		break;
			case 'GET':
				$result=['body'=>
				['form' => [
					'data-usecase' => 'file',
					'action' => "javascript:api.file('post', 'filemanager')"],
					'content'=>[]
				]];

				if (!$this->_requestedFolder){
					$folders = UTILITY::listDirectories(UTILITY::directory('files_documents'),'asc');
					if ($folders){
						$content=[];
						$result['body']['content'][] = [];
						foreach ($folders as $folder){
							$foldername = str_replace(UTILITY::directory('files_documents') . '/', '', $folder);
							array_push($result['body']['content'][0],
								['type' => 'links',
								'description' => LANG::GET('file.manager_folder_header', [':date' => date('Y-m-d H:i', filemtime($folder))]),
								'content' => [$foldername => ['href' => "javascript:api.file('get', 'filemanager', '" . $foldername . "')"]]],
								['type' => 'deletebutton',
								'attributes' => [
									'value' => LANG::GET('file.manager_delete_folder'),
									'type' => 'button',
									'onpointerup' => "new Dialog({type: 'confirm', header: '". LANG::GET('file.manager_delete_file_confirmation_header', [':file' => $foldername]) ."', 'options':{".
										"'".LANG::GET('file.manager_delete_file_confirmation_cancel')."': false,".
										"'".LANG::GET('file.manager_delete_file_confirmation_ok')."': {value: true, class: 'reducedCTA'},".
										"}}).then(function(r){if (r.target.returnValue==='true') api.file('delete', 'filemanager', '" . $foldername . "')})"
								]]
							);
						}
					}
					array_push($result['body']['content'][0],
						['type' => 'links',
						'description' => LANG::GET('menu.files_sharepoint'),
						'content' => [LANG::GET('menu.files_sharepoint') => ['href' => "javascript:api.file('get', 'filemanager', 'sharepoint')"]]]
					);
					$result['body']['content'][]=[
						['type' => 'textinput',
						'attributes' => [
							'name' => LANG::GET('file.manager_new_folder'),
							'required' => true]]
					];
				}
				else {
					if ($this->_requestedFolder === 'sharepoint') $files = UTILITY::listFiles(UTILITY::directory('sharepoint') ,'asc');
					else $files = UTILITY::listFiles(UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]) ,'asc');
					if ($files){
						$result['body']['content'][]=[
							['type' => 'filterinput',
							'attributes' => [
								'name' => LANG::GET('file.file_filter_label'),
								'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', this.value); return false;}",
								'onblur' => "api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', this.value); return false;",
								'id' => 'filefilter'
							]]
						];
						$result['body']['content'][] = [];
						foreach ($files as $file){
							$file=['path' => substr($file,1), 'name' => pathinfo($file)['basename']];
							array_push($result['body']['content'][1],
								['type' => 'links',
								'description' => date('Y-m-d H:i', filemtime('.' . $file['path'])),
								'content' => [$file['path'] => ['href' => $file['path'], 'target' => '_blank', 'data-filtered' => $file['path']]]],
								['type' => 'deletebutton',
								'attributes' => [
									'value' => LANG::GET('file.manager_delete_file'),
									'type' => 'button',
									'data-filtered' => $file['path'],
									'onpointerup' => "new Dialog({type: 'confirm', header: '". LANG::GET('file.manager_delete_file_confirmation_header', [':file' => $file['name']]) ."', 'options':{".
										"'".LANG::GET('file.manager_delete_file_confirmation_cancel')."': false,".
										"'".LANG::GET('file.manager_delete_file_confirmation_ok')."': {value: true, class: 'reducedCTA'},".
										"}}).then(function(r){if (r.target.returnValue==='true') api.file('delete', 'filemanager', '" . $this->_requestedFolder . "', '" . $file['name'] . "')})"
								]]
							);
						}
					}
					else $result['body']['content'] = $this->noContentAvailable(LANG::GET('file.no_files'));
					if ($this->_requestedFolder === 'sharepoint') unset ($result['body']['form']);
					else $result['body']['content'][]=[
						['type' => 'hiddeninput',
						'attributes' => [
							'name' => 'destination',
							'value' => $this->_requestedFolder]],
						['type' => 'file',
						'description' => LANG::GET('file.manager_new_file'),
						'attributes' => [
							'name' => 'files[]',
							'multiple' => true,
							'required' => true]]
					];
				}
				$this->response($result);
				break;
			case 'DELETE':
				if ($this->_requestedFolder === 'sharepoint') $success = UTILITY::delete(UTILITY::directory('sharepoint') . '/' . $this->_requestedFile);
				else $success = UTILITY::delete(UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]) . ($this->_requestedFile ? '/' . $this->_requestedFile : ''));
				if ($success) $this->response(['status' => [
					'msg' => LANG::GET('file.manager_deleted_file', [':file' => $this->_requestedFile ? : $this->_requestedFolder]),
					'redirect' => ['filemanager',  $this->_requestedFile ? $this->_requestedFolder : null]
				]]);
				else $this->response(['status' => [
					'msg' => LANG::GET('file.manager_error')
				]]);
				break;
		}
	}

	public function bundle(){
		if (!(array_intersect(['admin', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
		$result['body'] = [
			'content' => [
				[
					['type' => 'filterinput',
					'attributes' => [
						'name' => LANG::GET('file.bundle_filter_label'),
						'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'bundlefilter', this.value); return false;}",
						'onblur' => "api.file('get', 'bundlefilter', this.value); return false;",
						'id' => 'filesearch'
					]]
				]
			]
		];
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('file_bundles-get-active'));
		$statement->execute();
		$bundles = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach($bundles as $row) {
			$list=[];
			foreach (json_decode($row['content'], true) as $file => $path){
				$list[substr_replace($file, '.', strrpos($file, '_'), 1)]= ['href' => $path, 'target' => '_blank', 'data-filtered' => 'breakline'
			];
			}
			$result['body']['content'][]=
			[
				['type' => 'links',
				'description' => $row['name'],
				'content' => $list,
				'attributes' => ['data-filtered' => $row['id']]
				]
			];
		}
		$this->response($result);
	}

	public function bundlemanager(){
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
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

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('file_bundles-post'));
				if ($statement->execute([
					':name' => $name,
					':content' => json_encode($this->_payload),
					':active' => $isactive
					])) $this->response([
						'status' => [
							'name' => $name,
							'msg' => LANG::GET('file.edit_bundle_saved', [':name' => $name])
						]]);
					else $this->response([
						'status' => [
							'name' => false,
							'name' => LANG::GET('file.edit_bundle_not_saved')
						]]);
				break;
			case 'GET':
				$datalist = [];
				$options = ['...' => []];
				$return = [];
				
				// prepare existing bundle lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('file_bundles-datalist'));
				$statement->execute();
				$bundles = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($bundles as $row) {
					$datalist[] = $row['name'];
					$options[$row['name']] = [];
				}
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('file_bundles-get'));
				$statement->execute([
					':name' => $this->_requestedFolder
				]);
				if (!$bundle = $statement->fetch(PDO::FETCH_ASSOC)) $bundle = ['name' => '', 'content' => '', 'active' => null];
				if($this->_requestedFolder && $this->_requestedFolder !== 'false' && !$bundle['name']) $return['status'] = ['msg' => LANG::GET('file.bundle_error_not_found', [':name' => $this->_requestedFolder])];
		
				$return['body'] = [
					'form' => [
						'data-usecase' => 'file',
						'action' => "javascript:api.file('post', 'bundlemanager')"
					],
					'content' => [
						[
							['type' => 'datalist',
							'content' => $datalist,
							'attributes' => [
								'id' => 'bundles'
							]],
							['type' => 'select',
							'attributes' => [
								'name' => LANG::GET('file.edit_existing_bundle_select'),
								'onchange' => "api.file('get', 'bundlemanager', this.value)"
							],
							'content' => $options],
							['type' => 'searchinput',
							'attributes' => [
								'name' => LANG::GET('file.edit_existing_bundle'),
								'list' => 'bundles',
								'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'bundlemanager', this.value); return false;}"
							]]
						]]];

				$files = [];
				$bundle['content']= json_decode($bundle['content'], true);
				$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
				foreach ($folders as $folder) {
					$files[$folder] = UTILITY::listFiles($folder ,'asc');
				}
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
							$matches[$article][] = [['type' => 'checkbox',
								'description' => LANG::GET('file.file_list', [':folder' => $folder]),
								'content' => []
								]];
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
					['type' => 'textinput',
					'attributes'=>[
						'name'=> LANG::GET('file.edit_save_bundle'),
						'value' => $bundle['name']]
					],
					['type' => 'radio',
					'description' => LANG::GET('file.edit_bundle_active'),
					'content'=>[
						LANG::GET('file.edit_active_bundle')=>$isactive,
						LANG::GET('file.edit_inactive_bundle')=>$isinactive,
					]]
				];
				$this->response($return);
				break;
		}
	}

	public function sharepoint(){
		if (!$_SESSION['user']) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (array_key_exists('files', $_FILES) && $_FILES['files']['tmp_name']) {
					UTILITY::storeUploadedFiles(['files'], UTILITY::directory('sharepoint'), [$_SESSION['user']['name']]);
					$this->response(['status' => [
						'msg' => LANG::GET('file.manager_new_file_created'),
						'redirect' => ['sharepoint']
					]]);
				}
				$this->response(['status' => [
					'msg' => LANG::GET('file.manager_error')
				]]);
		break;
			case 'GET':
				$result=['body'=>
				['form' => [
					'data-usecase' => 'file',
					'action' => "javascript:api.file('post', 'sharepoint')"],
					'content'=>[]]
				];

				$files = UTILITY::listFiles(UTILITY::directory('sharepoint'),'asc');
				$display=[];
				if ($files){
					foreach ($files as $file){
						$file=['path' => $file, 'name' => pathinfo($file)['basename']];
						$filetime=filemtime($file['path']);
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
						['type' => 'filterinput',
						'attributes' => [
							'name' => LANG::GET('file.file_filter_label'),
							'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'filter', 'sharepoint', this.value); return false;}",
							'onblur' => "api.file('get', 'filter', 'sharepoint', this.value); return false;",
							'id' => 'filefilter'
						]]
					];
					$result['body']['content'][] = [
						['type' => 'links',
						'content' => $display]
					];
				}
				else $result['body']['content'] = $this->noContentAvailable(LANG::GET('file.no_files'));
				$result['body']['content'][]=[
					['type' => 'file',
					'description' => LANG::GET('file.sharepoint_upload_header'),
					'attributes' => [
						'name' => 'files[]',
						'multiple' => true,
						'required' => true]]
				];
				$this->response($result);
				break;
		}
	}

}


$api = new FILE();
$api->processApi();

exit;
?>
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
		if ($this->_requestedFolder && $this->_requestedFolder != 'null') $files = UTILITY::listFiles('../' . INI['sharepoint']['folder'] ,'asc');
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
			if ($percent >= INI['likeliness']['file_search_similarity'] || !$this->_requestedFolder) $matches[] = $row['id'];
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
					['type' => 'searchinput',
					'collapse' => true,
					'attributes' => [
						'placeholder' => LANG::GET('file.file_filter_label'),
						'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', this.value); return false;}",
						'onblur' => "api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', this.value); return false;",
						'id' => 'filesearch'
					]],[
						'type' => 'filter',
						'collapse' => true
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
			$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
			foreach ($folders as $folder) {
				$files[$folder] = UTILITY::listFiles($folder ,'asc');
			}
		}
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
				$new_folder = preg_replace(['/[\s-]{1,}/', '/\W/'], ['_', ''], UTILITY::propertySet($this->_payload, str_replace(' ', '_', LANG::GET('file.manager_new_folder'))));
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
				'content'=>[]]];

				if (!$this->_requestedFolder){
					$folders = UTILITY::listDirectories(UTILITY::directory('files_documents'),'asc');
					if ($folders){
						$content=[];
						foreach ($folders as $folder){
							$foldername = str_replace(UTILITY::directory('files_documents') . '/', '', $folder);
							$result['body']['content'][]=[
								['type' => 'links',
								'description' => LANG::GET('file.manager_folder_header', [':date' => date('Y-m-d H:i', filemtime($folder))]),
								'collapse' => true,
								'content' => [$foldername => ['href' => "javascript:api.file('get', 'filemanager', '" . $foldername . "')"]]],
								['type' => 'button',
								'collapse' => true,
								'description' => LANG::GET('file.manager_delete_folder'),
								'attributes' => [
									'type' => 'button',
									'onpointerup' => "if (confirm('" . LANG::GET('file.manager_delete_file_confirmation', [':file' => $foldername]) . "')) api.file('delete', 'filemanager', '" . $foldername . "')"
								]],
								['type' => 'links',
								'collapse' => true],
								];
						}
					}
					$result['body']['content'][]=[
						['type' => 'textinput',
						'description' => LANG::GET('file.manager_new_folder'),
						'attributes' => [
							'required' => true]]
					];
				}
				else {
					$files = UTILITY::listFiles(UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]) ,'asc');
					if ($files){
						$result['body']['content'][]=[
							['type' => 'searchinput',
							'collapse' => true,
							'attributes' => [
								'placeholder' => LANG::GET('file.file_filter_label'),
								'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', this.value); return false;}",
								'onblur' => "api.file('get', 'filter', '" . ($this->_requestedFolder ? : 'null') . "', this.value); return false;",
								'id' => 'filefilter'
							]],[
								'type' => 'filter',
								'collapse' => true
							]
						];
						foreach ($files as $file){
							$file=['path' => substr($file,1), 'name' => pathinfo($file)['basename']];
							$result['body']['content'][]=[
								['type' => 'links',
								'description' => date('Y-m-d H:i', filemtime('.' . $file['path'])),
								'collapse' => true,
								'content' => [$file['path'] => ['href' => $file['path'], 'target' => '_blank']]],
								['type' => 'hiddeninput',
								'collapse' => true,
								'description' => 'filter',
								'attributes'=>['data-filtered' => $file['path']]],
								['type' => 'button',
								'collapse' => true,
								'description' => LANG::GET('file.manager_delete_file'),
								'attributes' => [
									'type' => 'button',
									'onpointerup' => "if (confirm('" . LANG::GET('file.manager_delete_file_confirmation', [':file' => $file['name']]) . "')) api.file('delete', 'filemanager', '" . $this->_requestedFolder . "', '" . $file['name'] . "')"
								]],
								['type' => 'links',
								'collapse' => true],
							];
						}
					}
					$result['body']['content'][]=[
						['type' => 'hiddeninput',
						'description' => LANG::GET('file.manager_new_file'),
						'collapse' => true,
						'attributes' => [
							'name' => 'destination',
							'value' => $this->_requestedFolder]],
						['type' => 'file',
						'collapse' => true,
						'attributes' => [
							'name' => 'files[]',
							'multiple' => true,
							'required' => true]]
					];
				}
				$this->response($result);
				break;
			case 'DELETE':
				if (UTILITY::delete(UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]) . ($this->_requestedFile ? '/' . $this->_requestedFile : ''))) $this->response(['status' => [
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
					['type' => 'searchinput',
					'collapse' => true,
					'attributes' => [
						'placeholder' => LANG::GET('file.file_filter_label'),
						'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'bundlefilter', this.value); return false;}",
						'onblur' => "api.file('get', 'bundlefilter', this.value); return false;",
						'id' => 'filesearch'
					]],[
						'type' => 'filter',
						'collapse' => true
					]
				]
			]
		];
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('file_bundles-get-active'));
		$statement->execute();
		$bundles = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach($bundles as $row) {
			$list=[];
			foreach (json_decode($row['content'], true) as $name => $path){
				$list[$name]= ['href' => $path, 'target' => '_blank'];
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
				$unset = str_replace(' ', '_', LANG::GET('file.edit_existing_bundle'));
				unset ($this->_payload->$unset);
				$save_name = str_replace(' ', '_', LANG::GET('file.edit_save_bundle'));
				$name=$this->_payload->$save_name;
				unset ($this->_payload->$save_name);
				$active=str_replace(' ', '_', LANG::GET('file.edit_bundle_active'));
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
				
				var_dump($this->_payload);
				die();
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
							]]
						],[
							['type' => 'searchinput',
							'description' => LANG::GET('file.edit_existing_bundle'),
							'attributes' => [
								'placeholder' => LANG::GET('file.edit_existing_bundle_label'),
								'list' => 'bundles',
								'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'bundlemanager', this.value); return false;}"
							]],
							['type' => 'select',
							'description' => LANG::GET('file.edit_existing_bundle'),
							'attributes' => [
								'onchange' => "api.file('get', 'bundlemanager', this.value)"
							],
							'content' => $options]
						]]];

				$files = [];
				$bundle['content']= json_decode($bundle['content'], true);
				$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
				foreach ($folders as $folder) {
					$files[$folder] = UTILITY::listFiles($folder ,'asc');
				}
				foreach ($files as $folder => $content){
					$matches = [];
					foreach ($content as $file){
						$file=['path' => substr($file, 1), 'file' => pathinfo($file)['filename']];
						$matches[$file['file']] = ['value' => $file['path']];
						if ($bundle['content'] && in_array($file['path'], array_values($bundle['content']))) $matches[$file['file']]['checked'] = true;
					}
					$return['body']['content'][] =
					[
						['type' => 'checkbox',
						'description' => LANG::GET('file.file_list', [':folder' => $folder]),
						'content' => $matches
						]
					];
				}
				$return['body']['content'][] = [
					['type' => 'textinput',
					'description' => LANG::GET('file.edit_save_bundle'),
					'attributes'=>['value' => $bundle['name']]]
				];

				$isactive = $bundle['active'] ? ['checked' => true] : [];
				$isinactive = !$bundle['active'] ? ['checked' => true] : [];
				$return['body']['content'][] = [
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
					UTILITY::storeUploadedFiles(['files'], '../' . INI['sharepoint']['folder']);
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
				'content'=>[]]];

				$files = UTILITY::listFiles('../' . INI['sharepoint']['folder'] ,'asc');
				if ($files){
					$result['body']['content'][]=[
						['type' => 'searchinput',
						'collapse' => true,
						'attributes' => [
							'placeholder' => LANG::GET('file.file_filter_label'),
							'onkeypress' => "if (event.key === 'Enter') {api.file('get', 'filter', 'sharepoint', this.value); return false;}",
							'onblur' => "api.file('get', 'filter', 'sharepoint', this.value); return false;",
							'id' => 'filefilter'
						]],[
							'type' => 'filter',
							'collapse' => true
						]
					];
					foreach ($files as $file){
						$file=['path' => $file, 'name' => pathinfo($file)['basename']];
						$filetime=filemtime($file['path']);
						if (time() > $filetime + INI['sharepoint']['lifespan']*3600) {
							UTILITY::delete($file['path']);
						}
						else {
							$result['body']['content'][]=[
								['type' => 'links',
								'collapse' => true,
								'description' => LANG::GET('file.sharepoint_file_lifespan', [':hours' => round(($filetime + INI['sharepoint']['lifespan']*3600 - time()) / 3600, 1)]),
								'content' => [$file['name'] => ['href' => substr($file['path'], 1), 'target' => '_blank']]],
								['type' => 'hiddeninput',
								'collapse' => true,
								'description' => 'filter',
								'attributes'=>['data-filtered' => substr($file['path'], 1)]],
								['type' => 'links',
								'collapse' => true]
							];
						}
					}
				}
				$result['body']['content'][]=[
					['type' => 'hiddeninput',
					'description' => LANG::GET('file.sharepoint_upload_header'),
					'collapse' => true,
					'attributes' => [
						'name' => 'destination',
						'value' => $this->_requestedFolder]],
					['type' => 'file',
					'collapse' => true,
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
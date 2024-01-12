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
		if ($this->_requestedFolder && $this->_requestedFolder != 'null') $files = UTILITY::listFiles(UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]) ,'asc');
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
						'id' => 'productsearch'
					]],[
						'type' => 'filter',
						'collapse' => true
					]
				]
			]
		];
		if ($this->_requestedFolder && $this->_requestedFolder != 'null') $files = UTILITY::listFiles(UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]) ,'asc');
		else {
			$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
			$files = [];
			foreach ($folders as $folder) {
				$files = array_merge($files, UTILITY::listFiles($folder ,'asc'));
			}
		}
		$matches = [];
		foreach ($files as $file){
			$file=['path' => substr($file,1), 'name' => pathinfo($file)['filename'], 'file' => pathinfo($file)['basename']];
			$matches[$file['file']] = ['href' => $file['path'], 'data-filtered' => $file['path']];
		}
		$result['body']['content'][]=
		[
			['type' => 'links',
			'description' => LANG::GET('file.file_filtered'),
			'content' => $matches
			]
		];
		$this->response($result);
	}

	public function manager(){
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
						'redirect' => ['manager']
						]]);
				}
				$destination = UTILITY::propertySet($this->_payload, 'destination');
				if (array_key_exists('files', $_FILES) && $_FILES['files']['tmp_name'] && $destination) {
					UTILITY::storeUploadedFiles(['files'], UTILITY::directory('files_documents', [':category' => $destination]));
					$this->response(['status' => [
						'msg' => LANG::GET('file.manager_new_file_created'),
						'redirect' => ['manager', $destination]
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
					'action' => "javascript:api.file('post', 'manager')"],
				'content'=>[]]];

				if (!$this->_requestedFolder){
					$folders = UTILITY::listDirectories(UTILITY::directory('files_documents'),'asc');
					if ($folders){
						$content=[];
						foreach ($folders as $folder){
							$foldername = str_replace(UTILITY::directory('files_documents') . '/', '', $folder);
							$result['body']['content'][]=[
								['type' => 'links',
								'collapse' => true,
								'content' => [$foldername => ['href' => "javascript:api.file('get', 'manager', '" . $foldername . "')"]]],
								['type' => 'button',
								'collapse' => true,
								'description' => LANG::GET('file.manager_delete_folder'),
								'attributes' => [
									'type' => 'button',
									'onpointerdown' => "if (confirm('" . LANG::GET('file.manager_delete_file_confirmation', [':file' => $foldername]) . "')) api.file('delete', 'manager', '" . $foldername . "')"
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
								'id' => 'productsearch'
							]],[
								'type' => 'filter',
								'collapse' => true
							]
						];
						foreach ($files as $file){
							$file=['path' => substr($file,1), 'name' => pathinfo($file)['basename']];
							$result['body']['content'][]=[
								['type' => 'hiddeninput',
								'collapse' => true,
								'description' => 'filter',
								'attributes'=>['data-filtered' => $file['path']]],
								['type' => 'links',
								'collapse' => true,
								'content' => [$file['path'] => ['href' => $file['path']]]],
								['type' => 'button',
								'collapse' => true,
								'description' => LANG::GET('file.manager_delete_file'),
								'attributes' => [
									'type' => 'button',
									'onpointerdown' => "if (confirm('" . LANG::GET('file.manager_delete_file_confirmation', [':file' => $file['name']]) . "')) api.file('delete', 'manager', '" . $this->_requestedFolder . "', '" . $file['name'] . "')"
								]],
								['type' => 'links',
								'collapse' => true],
							];
						}
					}
					$result['body']['content'][]=[
						['type' => 'hiddeninput',
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
					'redirect' => ['manager',  $this->_requestedFile ? $this->_requestedFolder : null]
				]]);
				else $this->response(['status' => [
					'msg' => LANG::GET('file.manager_error')
				]]);
				break;
		}
	}
}

$api = new FILE();
$api->processApi();

exit;
?>
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

	public function files(){
		/**
		 * no put method for windows server permissions are a pita
		 * thus directories can not be renamed
		 */

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
			case 'GET':
				break;
			case 'DELETE':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
		}
		$this->response($result);
	}

	public function directory(){
		/**
		 * no put method for windows server permissions are a pita
		 * thus directories can not be renamed
		 */
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				break;
			case 'GET':
				break;
			case 'DELETE':
				break;
		}
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
				if (UTILITY::propertySet($this->_payload, str_replace(' ', '_', LANG::GET('file.new_folder')))){
					$new_folder = preg_replace(['/[\s-]{1,}/', '/\W/'], ['_', ''], UTILITY::propertySet($this->_payload, str_replace(' ', '_', LANG::GET('file.new_folder'))));
					if ($new_folder){
						$new_folder = UTILITY::directory('files_documents', [':category' => $new_folder]);
						UTILITY::storeUploadedFiles([], $new_folder);
						$this->response(['status' => [
							'msg' => LANG::GET('file.new_folder_created', [':name' => $new_folder]),
							'redirect' => ['manager']
							]]);
					}
				}
				$destination = UTILITY::propertySet($this->_payload, 'destination');
				if (array_key_exists('files', $_FILES) && $_FILES['files']['tmp_name'] && $destination) {
					UTILITY::storeUploadedFiles(['files'], UTILITY::directory('files_documents', [':category' => $destination]));
					$this->response(['status' => [
						'msg' => LANG::GET('file.new_file_created'),
						'redirect' => ['manager', $destination]
					]]);
				}
				$this->response(['status' => [
					'msg' => LANG::GET('file.creation_error')
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
								'description' => LANG::GET('file.delete_folder'),
								'attributes' => [
									'type' => 'button',
									'onpointerdown' => "if (confirm('" . LANG::GET('file.delete_file_confirmation', [':file' => $foldername]) . "')) api.file('delete', 'manager', '" . $foldername . "')"
								]],
								['type' => 'links',
								'collapse' => true],
								];
						}
					}
					$result['body']['content'][]=[
						['type' => 'textinput',
						'description' => LANG::GET('file.new_folder'),
						'attributes' => [
							'required' => true]]
					];
				}
				else {
					$files = UTILITY::listFiles(UTILITY::directory('files_documents', [':category' => $this->_requestedFolder]) ,'asc');
					if ($files){
						$content=[];
						foreach ($files as $file){
							$file=['path' => substr($file,1), 'name' => pathinfo($file)['basename']];
							$result['body']['content'][]=[
								['type' => 'links',
								'collapse' => true,
								'content' => [$file['path'] => ['href' => $file['path']]]],
								['type' => 'button',
								'collapse' => true,
								'description' => LANG::GET('file.delete_file'),
								'attributes' => [
									'type' => 'button',
									'onpointerdown' => "if (confirm('" . LANG::GET('file.delete_file_confirmation', [':file' => $file['name']]) . "')) api.file('delete', 'manager', '" . $this->_requestedFolder . "', '" . $file['name'] . "')"
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
					'msg' => LANG::GET('file.deleted_file', [':file' => $this->_requestedFile ? : $this->_requestedFolder]),
					'redirect' => ['manager',  $this->_requestedFile ? $this->_requestedFolder : null]
				]]);
				else $this->response(['status' => [
					'msg' => LANG::GET('file.creation_error')
				]]);
				break;
		}
	}
}

$api = new FILE();
$api->processApi();

exit;
?>
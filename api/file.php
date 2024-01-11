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
				$new_folder = preg_replace(['/[\s-]{1,}/', '/\W/'], ['_', ''], UTILITY::propertySet($this->_payload, str_replace(' ', '_', LANG::GET('file.new_folder'))));
				if ($new_folder){
					$new_folder = '../' . UTILITY::directory('files_documents', [':category' => $new_folder]);
					UTILITY::storeUploadedFiles([], $new_folder);
					$this->response(['status' => [
						'msg' => LANG::GET('file.new_folder_created', [':name' => $new_folder])
					]]);
				}
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
							$display = str_replace(UTILITY::directory('files_documents') . '/', '', $folder);
							$content[$display] = ['href'=>"javascript:api.file('get', 'manager', '" . $display . "')"];
						}
						$result['body']['content'][]=[
							['type' => 'links',
							'description' => LANG::GET('file.created_folders'),
							'content' => $content]
						];
					}
					$result['body']['content'][]=[
						['type' => 'textinput',
						'description' => LANG::GET('file.new_folder'),
						'attributes' => [
							'required' => true]]
					];
				}
				else {

				}
				$this->response($result);

				break;
			case 'DELETE':
				break;
		}
	}
}

$api = new FILE();
$api->processApi();

exit;
?>
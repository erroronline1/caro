<?php
// add, edit and delete distributors and orders
class PURCHASE extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
	private $_requestedID = REQUEST[2];

	public function __construct(){
		parent::__construct();
	}

	public function distributor(){
		if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$distributor = [
					'name' => SQLQUERY::SANITIZE($this->_payload->name),
					'info' => SQLQUERY::SANITIZE($this->_payload->info),
					'certificate_validity' => SQLQUERY::SANITIZE($this->_payload->certificate_validity),
					'certificate_path' => '',
					'pricelist_validity' => '',
					'pricelist_filter' => SQLQUERY::SANITIZE($this->_payload->pricelist_filter)
				];
				// checkboxes are not delivered if null, html-value 'on' might have to be converted in given db-structure
				// e.g. $this->_payload->active = $this->_payload->active ? 1 : 0;

				// save certificate
				if ($_FILES['certificate']['tmp_name']) {
					$distributor['certificate_path'] = UTILITY::storeUploadedFiles($_FILES, ['certificate'], $distributor['name'])[0];
				}
				// update pricelist
				if ($_FILES['pricelist']['tmp_name']) {
					$distributor['pricelist_validity'] = '';
				}
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('purchase_post-distributor'));
				if ($statement->execute([
					':name' => $distributor['name'],
					':info' => $distributor['info'],
					':certificate_validity' => $distributor['certificate_validity'],
					':certificate_path' => $distributor['certificate_path'],
					':pricelist_validity' => $distributor['pricelist_validity'],
					':pricelist_filter' => $distributor['pricelist_filter']
				])){
					$this->response(['id' => $this->_pdo->lastInsertId(), 'name' => UTILITY::scriptFilter($this->_payload->name)]);
				}
				break;

			case 'PUT':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$requestedID = SQLQUERY::SANITIZE($this->_requestedID);
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('purchase_get-distributor-id'));
				$statement->execute([
					':id' => $requestedID
				]);
				// prepare distributor-array to update, return error if not found
				if (!($distributor = $statement->fetch(PDO::FETCH_ASSOC))) $this->response(null, 406);

				$distributor['name'] = SQLQUERY::SANITIZE($this->_payload->name);
				$distributor['info'] = SQLQUERY::SANITIZE($this->_payload->info);
				$distributor['certificate_validity'] = SQLQUERY::SANITIZE($this->_payload->certificate_validity);
				$distributor['pricelist_filter'] = SQLQUERY::SANITIZE($this->_payload->pricelist_filter);

				// save certificate
				if ($_FILES['certificate']['tmp_name']) {
					if ($distributor['certificate_path']) unlink($distributor['certificate_path']);
					$distributor['certificate_path'] = UTILITY::storeUploadedFiles($_FILES, ['certificate'], $distributor['name'])[0];
				}
				// update pricelist
				if ($_FILES['pricelist']['tmp_name']) {
					$distributor['pricelist_validity'] = '';
				}
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('purchase_put-distributor'));
				if ($statement->execute([
					':id' => $distributor['id'],
					':name' => $distributor['name'],
					':info' => $distributor['info'],
					':certificate_validity' => $distributor['certificate_validity'],
					':certificate_path' => $distributor['certificate_path'],
					':pricelist_validity' => $distributor['pricelist_validity'],
					':pricelist_filter' => $distributor['pricelist_filter']
				])){
					$this->response(['id' => $distributor['id'], 'name' => UTILITY::scriptFilter($this->_payload->name)]);
				}
				break;

			case 'GET':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$passedID = SQLQUERY::SANITIZE($this->_requestedID);
				$datalist=[];
				$options=['...'=>[]];
				
				// prepare existing users lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('purchase_get-distributor-datalist'));
				$statement->execute();
				$distributor = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($distributor as $key => $row) {
					$datalist[] = $row['name'];
					$options[$row['name']] = [];
				}
		
				// select single user based on id or name
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('purchase_get-distributor'));
				$statement->execute([
					':id' => $passedID
				]);
				$distributor = $statement->fetch(PDO::FETCH_ASSOC);
		
				// display form for adding a new user with ini related permissions
				$form=['content' => [
					[
						['type' => 'datalist',
						'content' => $datalist,
						'attributes' => [
							'id' => 'distributors'
						]]
					],[
						['type' => 'searchinput',
						'description' => LANG::GET('purchase.edit_existing_distributors'),
						'attributes' => [
							'placeholder' => LANG::GET('purchase.edit_existing_distributors_label'),
							'list' => 'distributors',
							'onkeypress' => "if (event.key === 'Enter') {api.purchase('get', 'distributor', this.value); return false;}"
						]],
						['type' => 'select',
						'description' => LANG::GET('purchase.edit_existing_distributors'),
						'attributes' => [
							'onchange' => "api.purchase('get', 'distributor', this.value)"
						],
						'content' => $options]
					],
					[
						["type" => "textinput",
						"description" => LANG::GET('purchase.edit_distributor_name'),
						'attributes' => [
							'name' => 'name',
							'required' => true,
							'value' => $distributor['name'] ? : ''
						]]
					],
					[
						["type" => "textarea",
						"description" => LANG::GET('purchase.edit_distributor_info'),
						'attributes' => [
							'name' => 'info',
							'value' => $distributor['info'] ? : ''
						]]
					],
					[
						["type" => "dateinput",
						"description" => LANG::GET('purchase.edit_distributor_certificate_validity'),
						'attributes' => [
							'name' => 'certificate_validity',
							'value' => $distributor['certificate_validity'] ? : ''
						]],
						["type" => "file",
						"description" => LANG::GET('purchase.edit_distributor_certificate_update'),
						'attributes' => [
							'name' => 'certificate',
						]]
					],
					[
						["type" => "text",
						"description" => LANG::GET('purchase.edit_distributor_pricelist_validity'),
						"content" => $distributor['pricelist_validity'] ? : ''
						],
						["type" => "file",
						"description" => LANG::GET('purchase.edit_distributor_pricelist_update'),
						'attributes' => [
							'name' => 'pricelist',
						]],
						["type" => "textarea",
						"description" => LANG::GET('purchase.edit_distributor_pricelist_filter'),
						'attributes' => [
							'name' => 'pricelist_filter',
							'value' => $distributor['pricelist_filter'] ? : ''
						]]
					],
					[
						['type' => 'deletebutton',
						'description' => LANG::GET('purchase.edit_distributor_delete_button'),
						'attributes' => [
							'type' => 'button', // apparently defaults to submit otherwise
							'onpointerdown' => $distributor['id'] ? 'if (confirm("'. LANG::GET('purchase.edit_distributor_delete_confirm', [':name' => $distributor['name']]) .'")) {api.purchase("delete", "distributor", ' . $distributor['id'] . ')}' : ''
						]]
					]
				],
				'form' => [
					'data-usecase' => 'purchase',
					'action' => $distributor['id'] ? 'javascript:api.purchase("put", "distributor", "' . $distributor['id'] . '")' : 'javascript:api.purchase("post", "distributor")'
				]];

				if ($distributor['certificate_path'])
					array_splice($form['content'][4], 1, 0,
				[[
					'type' => 'links',
					'description' => LANG::GET('purchase.edit_distributor_certificate_download'),
					'content' => [
						$distributor['certificate_path']=> ['target' => '_blank']
					]
				  ]]
				);

				$this->response($form);
				break;

			case 'DELETE':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$passedID = SQLQUERY::SANITIZE($this->_requestedID);
				// prefetch to return proper name after deletion
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('purchase_delete-distributor-prefetch'));
				$statement->execute([
					':id' => $passedID
				]);
				if (!$distributor = $statement->fetch(PDO::FETCH_ASSOC)) $this->response([], 406);

				if ($distributor['certificate_path']) unlink($distributor['certificate_path']);
				
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('purchase_delete-distributor'));
				if ($statement->execute([
					':id' => $distributor['id']
				])) $this->response(['id' => false, 'name' => UTILITY::scriptFilter($distributor['name'])]);
				else $this->response(['id' => $distributor['id'], 'name' => UTILITY::scriptFilter($distributor['name'])]);
				break;
		}
	}
}

$api = new PURCHASE();
$api->processApi();

exit;
?>
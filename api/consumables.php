<?php
// add, edit and delete distributors and products
include_once('csvprocessor.php');

class CONSUMABLES extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_subMethod = null;
	private $filtersample = <<<'END'
	{
		"filesettings": {
			"headerrowindex": 0,
			"dialect": {
				"separator": ";",
				"enclosure": "\"",
				"escape": ""
			},
			"columns": [
				"ArticleNo",
				"Name",
				"Unit"
			]
		},
		"modify": {
			"rewrite": {
				"article_no": ["ArticleNo"],
				"article_name": ["Name"],
				"article_unit": ["Unit"]
			}
		}
	}
	END;

	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_subMethod = array_key_exists(3, REQUEST) ? REQUEST[3] : null;
	}

	private function update_pricelist($file, $filter, $distributorID){
		$filter = json_decode($filter, true);
		$filter['filesetting']['source'] = $file;
		$pricelist = new Listprocessor($filter);
		if (count($pricelist->_list)){
			// purge all unprotected products for a fresh data set
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_delete-all-unprotected-products'));
			$statement->execute([
				':id' => $distributorID
			]);
			// retrieve left items
			$remainder=[];
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-product-search'));
			$statement->execute([
				':search' => $distributorID
			]);
			$remained = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach($remained as $key => $row) {
				$remainder[$row['id']]=$row['article_no'];
			}

			$query = '';
			foreach($pricelist->_list as $i => $row){
				$update = array_search($row['article_no'], $remainder);

				if ($update) $query .= strtr(SQLQUERY::PREPARE('consumables_put-product-protected'),
				[
					':id' => $update,
					':article_name' => "'" . $row['article_name'] . "'",
					':article_unit' => "'" . $row['article_unit'] . "'",
				]) . '; ';

				else $query .= strtr(SQLQUERY::PREPARE('consumables_post-product'),
					[
						':distributor_id' => $distributorID,
						':article_no' => "'" . $row['article_no'] . "'",
						':article_name' => "'" . $row['article_name'] . "'",
						':article_unit' => "'" . $row['article_unit'] . "'",
						':active' => 1,
						':protected' => 0
					]) . '; ';
			}
			$statement = $this->_pdo->prepare($query);
			if ($statement->execute()) return date("d.m.Y");
		}
		return '';
	}

	public function distributor(){
		// Y U NO DELETE? because of audit safety, that's why!
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				
				/**
				 * 'immutable_fileserver' has to be set for windows server permissions are a pita
				 * thus directories can not be renamed on name changes of distributors
				 */

				$distributor = [
					'name' => $this->_payload->name,
					'active' => UTILITY::propertySet($this->_payload, LANG::GET('consumables.edit_distributor_active')) === LANG::GET('consumables.edit_distributor_isactive') ? 1 : 0,
					'info' => $this->_payload->info,
					'certificate' => ['validity' => $this->_payload->certificate_validity],
					'pricelist' => ['validity' => '', 'filter' => $this->_payload->pricelist_filter],
					'immutable_fileserver'=> $this->_payload->name . date('Ymd')
				];
				
				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $distributor['name'], $matches)) $this->response(['status' => ['msg' => LANG::GET('distributor.error_distributor_forbidden_name', [':name' => $distributor['name']])]]);
				}

				// save certificate
				if (array_key_exists('certificate', $_FILES) && $_FILES['certificate']['tmp_name']) {
					UTILITY::storeUploadedFiles(['certificate'], "../" . UTILITY::directory('distributor_certificates', [':name' => $distributor['immutable_fileserver']]), [$distributor['name'] . '_' . date('Ymd')]);
				}
				// save documents
				if (array_key_exists('documents', $_FILES) && $_FILES['documents']['tmp_name']) {
					UTILITY::storeUploadedFiles(['documents'], "../" . UTILITY::directory('distributor_documents', [':name' => $distributor['immutable_fileserver']]), [$distributor['name'] . '_' . date('Ymd')]);
				}
				// update pricelist
				if (array_key_exists('pricelist', $_FILES) && $_FILES['pricelist']['tmp_name']) {
					$distributor['pricelist']['validity'] = $this->update_pricelist($_FILES['pricelist']['tmp_name'][0], $distributor['pricelist']['filter'], $distributor['id']);
				}
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_post-distributor'));
				if ($statement->execute([
					':name' => $distributor['name'],
					':active' => $distributor['active'],
					':info' => $distributor['info'],
					':certificate' => json_encode($distributor['certificate']),
					':pricelist' => json_encode($distributor['pricelist']),
					':immutable_fileserver' => $distributor['immutable_fileserver']
				])) $this->response([
					'status' => [
						'id' => $this->_pdo->lastInsertId(),
						'msg' => LANG::GET('consumables.edit_distributor_saved', [':name' => $distributor['name']])
					]]);
				else $this->response([
					'status' => [
						'id' => false,
						'name' => LANG::GET('consumables.edit_distributor_not_saved')
					]]);
				break;

			case 'PUT':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-distributor'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				// prepare distributor-array to update, return error if not found
				if (!($distributor = $statement->fetch(PDO::FETCH_ASSOC))) $this->response(null, 406);

				$distributor['active'] = UTILITY::propertySet($this->_payload, LANG::GET('consumables.edit_distributor_active')) === LANG::GET('consumables.edit_distributor_isactive') ? 1 : 0;
				$distributor['name'] = $this->_payload->name;
				$distributor['info'] = $this->_payload->info;
				$distributor['certificate'] = json_decode($distributor['certificate'], true);
				$distributor['certificate']['validity'] = $this->_payload->certificate_validity;
				$distributor['pricelist'] = json_decode($distributor['pricelist'], true);
				$distributor['pricelist']['filter'] = $this->_payload->pricelist_filter;

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $distributor['name'], $matches)) $this->response(['status' => ['msg' => LANG::GET('distributor.error_distributor_forbidden_name', [':name' => $distributor['name']])]]);
				}

				// save certificate
				if (array_key_exists('certificate', $_FILES) && $_FILES['certificate']['tmp_name']) {
					UTILITY::storeUploadedFiles(['certificate'], "../" . UTILITY::directory('distributor_certificates', [':name' => $distributor['immutable_fileserver']]), [$distributor['name'] . '_' . date('Ymd')]);
				}
				// save documents
				if (array_key_exists('documents', $_FILES) && $_FILES['documents']['tmp_name']) {
					UTILITY::storeUploadedFiles(['documents'], "../" . UTILITY::directory('distributor_documents', [':name' => $distributor['immutable_fileserver']]), [$distributor['name'] . '_' . date('Ymd')]);
				}
				// update pricelist
				if (array_key_exists('pricelist', $_FILES) && $_FILES['pricelist']['tmp_name']) {
					$distributor['pricelist']['validity'] = $this->update_pricelist($_FILES['pricelist']['tmp_name'][0], $distributor['pricelist']['filter'], $distributor['id']);
				}
				// tidy up consumable products database if inactive
				if (!$distributor['active']){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_delete-all-unprotected-products'));
					$statement->execute([
						':id' => $distributor['id']
					]);
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_put-distributor'));
				if ($statement->execute([
					':id' => $distributor['id'],
					':active' => $distributor['active'],
					':name' => $distributor['name'],
					':info' => $distributor['info'],
					':certificate' => json_encode($distributor['certificate']),
					':pricelist' => json_encode($distributor['pricelist'])
				])) $this->response([
					'status' => [
						'id' => $distributor['id'],
						'msg' => LANG::GET('consumables.edit_distributor_saved', [':name' => $distributor['name']])
					]]);
				else $this->response([
					'status' => [
						'id' => $distributor['id'],
						'name' => LANG::GET('consumables.edit_distributor_not_saved')
					]]);
				break;

			case 'GET':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$datalist = [];
				$options = ['...' => []];
				$result = [];
				
				// prepare existing distributor lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-distributor-datalist'));
				$statement->execute();
				$distributor = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($distributor as $key => $row) {
					$datalist[] = $row['name'];
					$options[$row['name']] = [];
				}
		
				// select single distributor based on id or name
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-distributor'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				if (!$distributor = $statement->fetch(PDO::FETCH_ASSOC)) $distributor = [
					'id' => null,
					'name' => '',
					'active' => 0,
					'info' => '',
					'certificate' => '{"validity":""}',
					'pricelist' => '{"validity":"", "filter": ""}'
				];
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$distributor['id']) $result['status'] = ['msg' => LANG::GET('consumables.error_distributor_not_found', [':name' => $this->_requestedID])];

				$distributor['certificate'] = json_decode($distributor['certificate'], true);
				$distributor['pricelist'] = json_decode($distributor['pricelist'], true);
				$isactive = $distributor['active'] ? ['checked' => true] : [];
				$isinactive = !$distributor['active'] ? ['checked' => true] : [];

				$certificates = [];
				$documents = [];
				if ($distributor['id']) {
					$certfiles = UTILITY::listFiles("../" . UTILITY::directory('distributor_certificates', [':name' => $distributor['immutable_fileserver']]));
					foreach($certfiles as $path){
						$certificates[pathinfo($path)['basename']] = ['target' => '_blank', 'href' => $path];
					}
					$docfiles = UTILITY::listFiles("../" . UTILITY::directory('distributor_documents', [':name' => $distributor['immutable_fileserver']]));
					foreach($docfiles as $path){
						$documents[pathinfo($path)['basename']] = ['target' => '_blank', 'href' => $path];
					}
				}
				// display form for adding a new distributor
				$result['body']=['content' => [
					[
						['type' => 'datalist',
						'content' => $datalist,
						'attributes' => [
							'id' => 'distributors'
						]]
					],[
						['type' => 'searchinput',
						'description' => LANG::GET('consumables.edit_existing_distributors'),
						'attributes' => [
							'placeholder' => LANG::GET('consumables.edit_existing_distributors_label'),
							'list' => 'distributors',
							'onkeypress' => "if (event.key === 'Enter') {api.purchase('get', 'distributor', this.value); return false;}"
						]],
						['type' => 'select',
						'description' => LANG::GET('consumables.edit_existing_distributors'),
						'attributes' => [
							'onchange' => "api.purchase('get', 'distributor', this.value)"
						],
						'content' => $options]
					],
					[
						["type" => "textinput",
						"description" => LANG::GET('consumables.edit_distributor_name'),
						'attributes' => [
							'name' => 'name',
							'required' => true,
							'value' => $distributor['name'] ? : ''
						]]
					],
					[
						["type" => "textarea",
						"description" => LANG::GET('consumables.edit_distributor_info'),
						'attributes' => [
							'name' => 'info',
							'value' => $distributor['info'] ? : ''
						]]
					],
					[
						["type" => "dateinput",
						"description" => LANG::GET('consumables.edit_distributor_certificate_validity'),
						'attributes' => [
							'name' => 'certificate_validity',
							'value' => $distributor['certificate']['validity'] ? : ''
						]],
						["type" => "file",
						"description" => LANG::GET('consumables.edit_distributor_certificate_update'),
						'attributes' => [
							'name' => 'certificate',
						]]
					],
					[
						["type" => "file",
						"description" => LANG::GET('consumables.edit_distributor_documents_update'),
						'attributes' => [
							'name' => 'documents[]',
							'multiple' => true
						]]
					],
					[
						["type" => "file",
						"description" => LANG::GET('consumables.edit_distributor_pricelist_update'),
						'attributes' => [
							'name' => 'pricelist',
							'accept' => '.csv'
						]],
						["type" => "textarea",
						"description" => LANG::GET('consumables.edit_distributor_pricelist_filter'),
						'attributes' => [
							'name' => 'pricelist_filter',
							'value' => $distributor['pricelist']['filter'] ? : '',
							'placeholder' => json_encode(json_decode($this->filtersample, true))
						]]
					],
					[
						["type" => "radio",
						"description" => LANG::GET('consumables.edit_distributor_active'),
						"content" => [
							LANG::GET('consumables.edit_distributor_isactive') => $isactive,
							LANG::GET('consumables.edit_distributor_isinactive') => $isinactive
						]]
					]
				],
				'form' => [
					'data-usecase' => 'purchase',
					'action' => $distributor['id'] ? 'javascript:api.purchase("put", "distributor", "' . $distributor['id'] . '")' : 'javascript:api.purchase("post", "distributor")'
				]];

				if ($certificates) array_splice($result['body']['content'][4], 1, 0,
					[
						['type' => 'links',
						'description' => LANG::GET('consumables.edit_distributor_certificate_download'),
						'content' => $certificates
						]
					]
				);
				if ($documents) array_splice($result['body']['content'][5], 0, 0,
					[
						['type' => 'links',
						'description' => LANG::GET('consumables.edit_distributor_documents_download'),
						'content' => $documents
						]
					]
				);
				if ($distributor['pricelist']['validity']) array_splice($result['body']['content'][6], 0, 0,
					[
						["type" => "text",
						"description" => LANG::GET('consumables.edit_distributor_pricelist_validity'),
						"content" => $distributor['pricelist']['validity']
						]
					]
				);

				$this->response($result);
				break;
		}
	}

	public function product(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				
				$product = [
					'id' => null,
					'distributor_id' => null,
					'distributor_name' => $this->_payload->distributor_select !== '...' ? $this->_payload->distributor_select : $this->_payload->distributor_input,
					'article_no' => $this->_payload->article_no,
					'article_name' => $this->_payload->article_name,
					'article_unit' => $this->_payload->article_unit,
					'active' => UTILITY::propertySet($this->_payload, LANG::GET('consumables.edit_product_active')) === LANG::GET('consumables.edit_product_isactive') ? 1 : 0,
					'protected' => 0
				];

				// validate distributor
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-distributor'));
				$statement->execute([
					':id' => $product['distributor_name']
				]);
				if (!$distributor = $statement->fetch(PDO::FETCH_ASSOC)) $this->response(['status' => ['msg' => LANG::GET('consumables.error_distributor_not_found', [':name' => $product['distributor_name']])]]);
				$product['distributor_id'] = $distributor['id'];

				// save documents
				if (array_key_exists('documents', $_FILES) && $_FILES['documents']['tmp_name'][0]) {
					UTILITY::storeUploadedFiles(['documents'], "../" . UTILITY::directory('distributor_products', [':name' => $distributor['immutable_fileserver']]), [$distributor['name'] . '_' . date('Ymd') . '_' . $product['article_no']]);
					$product['protected'] = 1;
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_post-product'));
				if ($statement->execute([
					':distributor_id' => $product['distributor_id'],
					':article_no' => $product['article_no'],
					':article_name' => $product['article_name'],
					':article_unit' => $product['article_unit'],
					':active' => $product['active'],
					':protected' => $product['protected']
				])) $this->response([
					'status' => [
						'id' => $this->_pdo->lastInsertId(),
						'msg' => LANG::GET('consumables.edit_product_saved', [':name' => $product['article_name']])
					]]);
				else $this->response([
					'status' => [
						'id' => false,
						'name' => LANG::GET('consumables.edit_product_not_saved')
					]]);
				break;

			case 'PUT':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-product'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				// prepare product-array to update, return error if not found
				if (!($product = $statement->fetch(PDO::FETCH_ASSOC))) $result['status'] = ['msg' => LANG::GET('consumables.error_product_not_found', [':name' => $this->_requestedID])];

				$product['distributor_name'] = $this->_payload->distributor_select !== '...' ? $this->_payload->distributor_select : $this->_payload->distributor_input;
				$product['article_no'] = $this->_payload->article_no;
				$product['article_name'] = $this->_payload->article_name;
				$product['article_unit'] = $this->_payload->article_unit;
				$product['active'] = UTILITY::propertySet($this->_payload, LANG::GET('consumables.edit_product_active')) === LANG::GET('consumables.edit_product_isactive') ? 1 : 0;

				// validate distributor
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-distributor'));
				$statement->execute([
					':id' => $product['distributor_name']
				]);
				if (!$distributor = $statement->fetch(PDO::FETCH_ASSOC)) $this->response(['status' => ['msg' => LANG::GET('consumables.error_distributor_not_found', [':name' => $product['distributor_name']])]]);
				$product['distributor_id'] = $distributor['id'];
				
				// save documents
				if (array_key_exists('documents', $_FILES) && $_FILES['documents']['tmp_name'][0]) {
					UTILITY::storeUploadedFiles(['documents'], "../" . UTILITY::directory('distributor_products', [':name' => $distributor['immutable_fileserver']]), [$distributor['name'] . '_' . date('Ymd') . '_' . $product['article_no']]);
					$product['protected'] = 1;
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_put-product'));
				if ($statement->execute([
					':id' => $this->_requestedID,
					':distributor_id' => $product['distributor_id'],
					':article_no' => $product['article_no'],
					':article_name' => $product['article_name'],
					':article_unit' => $product['article_unit'],
					':active' => $product['active'],
					':protected' => $product['protected']
				])) $this->response([
					'status' => [
						'id' => $this->_requestedID,
						'msg' => LANG::GET('consumables.edit_product_saved', [':name' => $product['article_name']])
					]]);
				else $this->response([
					'status' => [
						'id' => $this->_requestedID,
						'name' => LANG::GET('consumables.edit_product_not_saved')
					]]);
				break;

			case 'GET':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$datalist = [];
				$options = ['...' => []];
				$datalist_unit = [];
				$result = [];
				
				// select single product based on id or name
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-product'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				if (!$product = $statement->fetch(PDO::FETCH_ASSOC)) $product = [
					'id' => null,
					'distributor_id' => '',
					'distributor_name' => '',
					'distributor_immutable_fileserver' => '',
					'article_no' => '',
					'article_name' => '',
					'article_unit' => '',
					'active' => 1,
					'protected' => 0
				];
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$product['id'] && !$this->_subMethod) $result['status'] = ['msg' => LANG::GET('consumables.error_product_not_found', [':name' => $this->_requestedID])];

				$isactive = $product['active'] ? ['checked' => true] : [];
				$isinactive = !$product['active'] ? ['checked' => true] : [];

				$certificates = [];
				$documents = [];
				if ($product['id']) {
					$docfiles = UTILITY::listFiles("../" . UTILITY::directory('distributor_products', [':name' => $product['distributor_immutable_fileserver']]));
					foreach($docfiles as $path){
						$file = pathinfo($path);
						$article_no = explode('_', $file['filename'])[2];
						similar_text($article_no, $product['article_no'], $percent);
						if ($percent >= INI['likeliness']['consumables_article_no']) 
							$documents[$file['basename']] = ['target' => '_blank', 'href' => $path];
					}
				}

				// prepare existing distributor lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-distributor-datalist'));
				$statement->execute();
				$distributor = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($distributor as $key => $row) {
					$datalist[] = $row['name'];
					$options[$row['name']] = [];
					if ($row['name'] === $product['distributor_name']) $options[$row['name']]['selected'] = true;
				}

				// prepare existing unit lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-product-units'));
				$statement->execute();
				$distributor = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($distributor as $key => $row) {
					$datalist_unit[] = $row['article_unit'];
				}


				// display form for adding or editing a product
				$result['body']=['content' => [
					[
						['type' => 'datalist',
						'content' => $datalist,
						'attributes' => [
							'id' => 'distributors'
						]],
						['type' => 'datalist',
						'content' => $datalist_unit,
						'attributes' => [
							'id' => 'units'
						]]
					],
					[
						['type' => 'searchinput',
						'description' => LANG::GET('consumables.edit_product_search'),
						'attributes' => [
							'placeholder' => LANG::GET('consumables.edit_product_search_label'),
							'value' => $this->_requestedID && $this->_subMethod === 'search' ? $this->_requestedID : '',
							'onkeypress' => "if (event.key === 'Enter') {api.purchase('get', 'product', this.value, 'search'); return false;}"
						]],
					],
					[
						['type' => 'hr']
					],
					[
						['type' => 'textinput',
						'description' => LANG::GET('consumables.edit_product_distributor'),
						'attributes' => [
							'name' => 'distributor_input',
							'list' => 'distributors',
							'value' => $product['distributor_name']
						]],
						['type' => 'select',
						'description' => LANG::GET('consumables.edit_product_distributor'),
						'attributes' => [
							'name' => 'distributor_select'
						],
						'content' => $options]
					],
					[
						["type" => "textinput",
						"description" => LANG::GET('consumables.edit_product_article_no'),
						'attributes' => [
							'name' => 'article_no',
							'required' => true,
							'value' => $product['article_no']
						]]
					],
					[
						["type" => "textinput",
						"description" => LANG::GET('consumables.edit_product_article_name'),
						'attributes' => [
							'name' => 'article_name',
							'required' => true,
							'value' => $product['article_name']
						]]
					],
					[
						["type" => "textinput",
						"description" => LANG::GET('consumables.edit_product_article_unit'),
						'attributes' => [
							'name' => 'article_unit',
							'list' => 'units',
							'required' => true,
							'value' => $product['article_unit']
						]]
					],
					[
						["type" => "file",
						"description" => LANG::GET('consumables.edit_product_documents_update'),
						'attributes' => [
							'name' => 'documents[]',
							'multiple' => true
						]]
					],
					[
						["type" => "radio",
						"description" => LANG::GET('consumables.edit_product_active'),
						"content" => [
							LANG::GET('consumables.edit_product_isactive') => $isactive,
							LANG::GET('consumables.edit_product_isinactive') => $isinactive
						]]
					]
				],
				'form' => [
					'data-usecase' => 'purchase',
					'action' => $product['id'] ? "javascript:api.purchase('put', 'product', '" . $product['id'] . "')" : "javascript:api.purchase('post', 'product')"
				]];

				if ($documents) array_splice($result['body']['content'][6], 0, 0,
					[
						['type' => 'links',
						'description' => LANG::GET('consumables.edit_product_documents_download'),
						'content' => $documents
						]
					]
				);
				if ($product['id'] && !$product['protected']) array_push($result['body']['content'],
					[
						['type' => 'deletebutton',
						'description' => LANG::GET('consumables.edit_product_delete'),
						'attributes' => [
							'type' => 'button', // apparently defaults to submit otherwise
							'onpointerdown' => $product['id'] ? "if (confirm('" . LANG::GET('consumables.edit_product_delete_confirm', [':name' => $product['article_name']]) ."')) {api.purchase('delete', 'product', " . $product['id'] . ")}" : ""
						]]
					]
				);
				if ($this->_subMethod === 'search'){
					$matches = [];
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-product-search'));
					$statement->execute([
						':search' => $this->_requestedID
					]);
					$search = $statement->fetchAll(PDO::FETCH_ASSOC);
					foreach($search as $key => $row) {
						$matches[$row['distributor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name']] = ['href' => "javascript:api.purchase('get', 'product', " . $row['id'] . ")"];
					}
					array_splice($result['body']['content'], 2, 0,
						[[
							['type' => 'links',
							'description' => LANG::GET('consumables.edit_product_search_matches', [':number' => count($matches)]),
							'content' => $matches
							]
						]]
					);
				}
				$this->response($result);
				break;
		case 'DELETE':
			if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				// prefetch to return proper name after deletion
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-product'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				$product = $statement->fetch(PDO::FETCH_ASSOC);

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_delete-unprotected-product'));
				if ($statement->execute([
					':id' => $product['id']
				])) $this->response([
					'status' => [
						'msg' => LANG::GET('consumables.edit_product_deleted', [':name' => $product['article_name']]),
						'id' => false
					]]);
				else $this->response([
					'status' => [
						'msg' => LANG::GET('consumables.edit_product_not_deleted', [':name' => $product['article_name']]),
						'id' => $product['id']
					]]);
			break;
		}
	}
}

$api = new CONSUMABLES();
$api->processApi();

exit;
?>
<?php
// add, edit and delete vendors and products
include_once('csvprocessor.php');

class CONSUMABLES extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $filtersample = <<<'END'
	{
	  "info": "more rules may apply, see documentation if neccessary",
	  "filesettings": {
	    "columns": [
	      "ArticleNo",
	      "Name",
	      "Unit",
	      "EAN"
	    ]
	  },
	  "modify": {
	    "rewrite": {
	      "article_no": ["ArticleNo"],
	      "article_name": ["Name"],
	      "article_unit": ["Unit"],
	      "article_ean": ["EAN"]
	    }
	  }
	}
	END;

	private $tradingsample = <<<'END'
	{
	  "filesetting": {
	    "columns": ["article_no", "article_name"]
	  },
	  "filter": [
	    {
	      "apply": "filter_by_expression",
	      "comment": "delete unnecessary products",
	      "keep": false
	      "match": {
	        "all": {
	          "article_name": "ANY REGEX PATTERN THAT MIGHT MATCH ARTICLE NAMES THAT QUALIFY AS TRADING GOOD (OR DON'T IN ACCORDANCE TO keep-FLAG)"
	        }
	      }
	    }
	  ]
	}
	END;

	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	private function update_pricelist($file, $filter, $vendorID){
		$filter = json_decode($filter, true);
		$filter['filesetting']['source'] = $file;
		$filter['filesetting']['encoding'] = INI['likeliness']['csvprocessor_source_encoding'];
		if (!array_key_exists('headerrowindex', $filter['filesetting'])) $filter['filesetting']['headerrowindex'] = INI['csv']['headerrowindex'];
		if (!array_key_exists('dialect', $filter['filesetting'])) $filter['filesetting']['dialect'] = INI['csv']['dialect'];
		$pricelist = new Listprocessor($filter);
		$sqlchunks = [];
		$date = '';
		if (count($pricelist->_list[1])){
			// purge all unprotected products for a fresh data set
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_delete-all-unprotected-products'));
			$statement->execute([
				':id' => $vendorID
			]);
			// retrieve left items
			$remainder=[];
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-products-by-vendor-id'));
			$statement->execute([
				':search' => $vendorID
			]);
			$remained = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach($remained as $key => $row) {
				$remainder[$row['id']]=$row['article_no'];
			}

			foreach ($pricelist->_list[1] as $i => $row){
				$update = array_search($row['article_no'], $remainder);
				if ($update) $query = strtr(SQLQUERY::PREPARE('consumables_put-product-protected'),
				[
					':id' => $update,
					':article_name' => "'" . $row['article_name'] . "'",
					':article_unit' => "'" . $row['article_unit'] . "'",
					':article_ean' => "'" . $row['article_ean'] . "'",
					':trading_good' => "'0'",
					':incorporated' => $row['incorporated'] === null ? 'NULL' : $row['incorporated'], //without quotes
				]) . '; ';
				else $query = strtr(SQLQUERY::PREPARE('consumables_post-product'),
					[
						':vendor_id' => $vendorID,
						':article_no' => "'" . $row['article_no'] . "'",
						':article_name' => "'" . $row['article_name'] . "'",
						':article_alias' => "''",
						':article_unit' => "'" . $row['article_unit'] . "'",
						':article_ean' => "'" . $row['article_ean'] . "'",
						':active' => 1,
						':protected' => 0,
						':trading_good' => 0,
						':incorporated' => null
					]) . '; ';

				$sqlchunks = SQLQUERY::CHUNKIFY($sqlchunks, $query);
			}
			foreach ($sqlchunks as $chunk){
				$statement = $this->_pdo->prepare($chunk);
				if ($statement->execute()) $date = date("d.m.Y");
			}
			return $date;
		}
		return '';
	}

	private function update_trading_goods($filter, $vendorID){
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-products-by-vendor-id'));
		$statement->execute([
			':search' => $vendorID
		]);
		$vendorProducts = $statement->fetchAll(PDO::FETCH_ASSOC);
		$assignedArticles = [];
		foreach($vendorProducts as $key => $row) {
			$assignedArticles[$row['id']]=$row['article_no'];
		}

		$filter = json_decode($filter, true);
		$filter['filesetting']['source'] = $vendorProducts;
		$pricelist = new Listprocessor($filter);
		$sqlchunks = [];
		if (count($pricelist->_list[1])){
			foreach ($pricelist->_list[1] as $i => $row){
				$update = array_search($row['article_no'], $assignedArticles);
				if ($update) $query = strtr(SQLQUERY::PREPARE('consumables_put-product-protected'),
				[
					':id' => $update,
					':article_name' => "'" . $row['article_name'] . "'",
					':article_unit' => "'" . $row['article_unit'] . "'",
					':article_ean' => "'" . $row['article_ean'] . "'",
					':trading_good' => "1",
					':incorporated' => "'" . $row['incorporated'] . "'",
				]) . '; ';
				$sqlchunks = SQLQUERY::CHUNKIFY($sqlchunks, $query);
			}
			foreach ($sqlchunks as $chunk){
				$statement = $this->_pdo->prepare($chunk);
				$statement->execute();
			}
		}
		return;
	}

	public function mdrsamplecheck(){
		if (!(array_intersect(['user', 'admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-product'));
			$statement->execute([
				':id' => $this->_requestedID
			]);
			if (!($product = $statement->fetch(PDO::FETCH_ASSOC)) || !$this->_payload->content) $this->response([]);
			$content = implode("\n", [$product['vendor_name'], $product['article_no'], $product['article_name']]) . "\n" . $this->_payload->content;

			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('checks_post'));
			if ($statement->execute([
				':type' => 'mdrsamplecheck',
				':author' => $_SESSION['user']['name'],
				':content' => $content
			])) {
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_put-check'));
				if ($statement->execute([
					':id' => $product['id'],
				])) $this->response([
					'status' => [
						'msg' => LANG::GET('order.sample_check_success')
					]]);
			}
			$this->response([
				'status' => [
					'msg' => LANG::GET('order.sample_check_failure')
				]]);
			break;
		}
	}

	public function incorporation(){
		if (!(array_intersect(['user', 'admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-product'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				if (!($product = $statement->fetch(PDO::FETCH_ASSOC)) || !$this->_payload->content) $this->response([]);
				$content = implode("\n", [$product['vendor_name'], $product['article_no'], $product['article_name']]) . "\n" . $this->_payload->content;

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('checks_post'));
				if ($statement->execute([
					':type' => 'incorporation',
					':author' => $_SESSION['user']['name'],
					':content' => $content
				])) {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_put-incorporation'));
					if ($statement->execute([
						':id' => $product['id'],
						':incorporated' => 1
					])) $this->response([
						'status' => [
							'msg' => LANG::GET('order.incorporation_success')
						]]);
				}
				$this->response([
					'status' => [
						'msg' => LANG::GET('order.incorporation_failure')
					]]);
				break;
			case 'GET':
				$result = [];
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-product'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				if (!($product = $statement->fetch(PDO::FETCH_ASSOC))) $result['status'] = ['msg' => LANG::GET('consumables.error_product_not_found', [':name' => $this->_requestedID])];
		
				$result['body'] = [
					'content' => [
						[
							'type' => 'text',
							'description' => implode(' ', [
								$product['article_no'] ? : '',
								$product['article_name'] ? : '',
								$product['vendor_name'] ? : ''])
						], ...json_decode(LANG::GET('defaultcomponent.incorporation'), true)
					],
					'options' => [
							LANG::GET('order.incorporation_cancel') => false,
							LANG::GET('order.incorporation_submit') => [ 'value' => true, 'class'=> 'reducedCTA']
					],
					'productid' => $product['id']
				];

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('checks_get'));
				$statement->execute([':type' => 'incorporation']);
				$checks = $statement->fetchAll(PDO::FETCH_ASSOC);

				$productsPerSlide = 0;
				$matches = [[]];

				foreach($checks as $row){
					$row['content'] = explode("\n", $row['content']);
					$probability = [ 'article_no' => [], 'vendor_name' => []];
					foreach ($row['content'] as $information){
						similar_text($information, $product['article_no'], $article_no_percent);
						if ($article_no_percent >= INI['likeliness']['consumables_article_no_similarity']) $probability['article_no'][] = $row['id'];
						similar_text($information, $product['vendor_name'], $vendor_name_percent);
						if ($vendor_name_percent >= INI['likeliness']['consumables_article_no_similarity']) $probability['vendor_name'][] = $row['id'];
					}
					if (array_intersect($probability['article_no'], $probability['vendor_name'])){
						$article = intval(count($matches) - 1);
						if (empty($productsPerSlide++ % INI['splitresults']['products_per_slide'])){
							$matches[$article][] = [
								['type' => 'text',
								'description' => LANG::GET('order.incorporation_matching_previous'),
								]
							];
						}
						$slide = intval(count($matches[$article]) - 1);
						$matches[$article][$slide][] = [
							'type' => 'tile',
							'attributes' => [
								'onpointerup' => "document.getElementById('incorporationmatchingprevious').value = '" . $product['vendor_name'] . ' ' . $product['article_no'] . ' ' . $product['article_name'] . "'",
							],
							'content' => [
								[
									'type' => 'text',
									'content' => $product['vendor_name'] . ' ' . $product['article_no'] . ' ' . $product['article_name']
								]
							]
						];

					}
				}
				if ($matches[0]){
					array_push($result['body']['content'], ...$matches);
					$result['body']['content'][] = [
						[
							'type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('order.incorporation_matching_previous'),
								'id' => 'incorporationmatchingprevious',
								'readonly' => true
							]
						]
					];
				}
				$this->response($result);
				break;
		}
	}

	public function vendor(){
		// Y U NO DELETE? because of audit safety, that's why!
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				
				/**
				 * 'immutable_fileserver' has to be set for windows server permissions are a pita
				 * thus directories can not be renamed on name changes of vendors
				 */
				$vendor = [
					'name' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_name')),
					'active' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_active')) === LANG::GET('consumables.edit_vendor_isactive') ? 1 : 0,
					'info' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_info')),
					'certificate' => ['validity' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_certificate_validity'))],
					'pricelist' => ['validity' => '', 'filter' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_pricelist_filter')), 'trading_goods' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_pricelist_trading_filter'))],
					'immutable_fileserver'=> UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_name')) . date('Ymd')
				];
				
				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $vendor['name'], $matches)) $this->response(['status' => ['msg' => LANG::GET('consumables.error_vendor_forbidden_name', [':name' => $vendor['name']])]]);
				}

				// save certificate
				if (array_key_exists(LANG::PROPERTY('consumables.edit_vendor_certificate_update'), $_FILES) && $_FILES[LANG::PROPERTY('consumables.edit_vendor_certificate_update')]['tmp_name']) {
					UTILITY::storeUploadedFiles([LANG::PROPERTY('consumables.edit_vendor_certificate_update')], UTILITY::directory('vendor_certificates', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . date('Ymd')]);
				}
				// save documents
				if (array_key_exists(LANG::PROPERTY('consumables.edit_vendor_documents_update'), $_FILES) && $_FILES[LANG::PROPERTY('consumables.edit_vendor_documents_update')]['tmp_name']) {
					UTILITY::storeUploadedFiles([LANG::PROPERTY('consumables.edit_vendor_documents_update')], UTILITY::directory('vendor_documents', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . date('Ymd')]);
				}
				// update pricelist
				$pricelistImportError = '';
				if (array_key_exists(LANG::PROPERTY('consumables.edit_vendor_pricelist_update'), $_FILES) && $_FILES[LANG::PROPERTY('consumables.edit_vendor_pricelist_update')]['tmp_name']) {
					$vendor['pricelist']['validity'] = $this->update_pricelist($_FILES[LANG::PROPERTY('consumables.edit_vendor_pricelist_update')]['tmp_name'][0], $vendor['pricelist']['filter'], $vendor['id']);
					if (!strlen($vendor['pricelist']['validity'])) $pricelistImportError = LANG::GET('consumables.edit_vendor_pricelist_update_error');
					if (!$pricelistImportError && $vendor['pricelist']['trading_goods']){
						$this->update_trading_goods($vendor['pricelist']['trading_goods'], $vendor['id']);
					}	
				}
	
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_post-vendor'));
				if ($statement->execute([
					':name' => $vendor['name'],
					':active' => $vendor['active'],
					':info' => $vendor['info'],
					':certificate' => json_encode($vendor['certificate']),
					':pricelist' => json_encode($vendor['pricelist']),
					':immutable_fileserver' => $vendor['immutable_fileserver']
				])) $this->response([
					'status' => [
						'id' => $this->_pdo->lastInsertId(),
						'msg' => LANG::GET('consumables.edit_vendor_saved', [':name' => $vendor['name']]) . $pricelistImportError
					]]);
				else $this->response([
					'status' => [
						'id' => false,
						'name' => LANG::GET('consumables.edit_vendor_not_saved')
					]]);
				break;

			case 'PUT':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-vendor'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				// prepare vendor-array to update, return error if not found
				if (!($vendor = $statement->fetch(PDO::FETCH_ASSOC))) $this->response(null, 406);

				$vendor['active'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_active')) === LANG::GET('consumables.edit_vendor_isactive') ? 1 : 0;
				$vendor['name'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_name'));
				$vendor['info'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_info'));
				$vendor['certificate'] = json_decode($vendor['certificate'], true);
				$vendor['certificate']['validity'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_certificate_validity'));
				$vendor['pricelist'] = json_decode($vendor['pricelist'], true);
				$vendor['pricelist']['filter'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_pricelist_filter'));
				$vendor['pricelist']['trading_goods'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_pricelist_trading_filter'));

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $vendor['name'], $matches)) $this->response(['status' => ['msg' => LANG::GET('vendor.error_vendor_forbidden_name', [':name' => $vendor['name']])]]);
				}

				// save certificate
				if (array_key_exists(LANG::PROPERTY('consumables.edit_vendor_certificate_update'), $_FILES) && $_FILES[LANG::PROPERTY('consumables.edit_vendor_certificate_update')]['tmp_name']) {
					UTILITY::storeUploadedFiles([LANG::PROPERTY('consumables.edit_vendor_certificate_update')], UTILITY::directory('vendor_certificates', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . date('Ymd')]);
				}
				// save documents
				if (array_key_exists(LANG::PROPERTY('consumables.edit_vendor_documents_update'), $_FILES) && $_FILES[LANG::PROPERTY('consumables.edit_vendor_documents_update')]['tmp_name']) {
					UTILITY::storeUploadedFiles([LANG::PROPERTY('consumables.edit_vendor_documents_update')], UTILITY::directory('vendor_documents', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . date('Ymd')]);
				}
				// update pricelist
				$pricelistImportError = '';
				if (array_key_exists(LANG::PROPERTY('consumables.edit_vendor_pricelist_update'), $_FILES) && $_FILES[LANG::PROPERTY('consumables.edit_vendor_pricelist_update')]['tmp_name']) {
					$vendor['pricelist']['validity'] = $this->update_pricelist($_FILES[LANG::PROPERTY('consumables.edit_vendor_pricelist_update')]['tmp_name'][0], $vendor['pricelist']['filter'], $vendor['id']);
					if (!strlen($vendor['pricelist']['validity'])) $pricelistImportError = LANG::GET('consumables.edit_vendor_pricelist_update_error');
					if (!$pricelistImportError && $vendor['pricelist']['trading_goods']){
						$this->update_trading_goods($vendor['pricelist']['trading_goods'], $vendor['id']);
					}
				}

				// tidy up consumable products database if inactive
				if (!$vendor['active']){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_delete-all-unprotected-products'));
					$statement->execute([
						':id' => $vendor['id']
					]);
					$vendor['pricelist']['validity'] = '';
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_put-vendor'));
				if ($statement->execute([
					':id' => $vendor['id'],
					':active' => $vendor['active'],
					':name' => $vendor['name'],
					':info' => $vendor['info'],
					':certificate' => json_encode($vendor['certificate']),
					':pricelist' => json_encode($vendor['pricelist'])
				])) $this->response([
					'status' => [
						'id' => $vendor['id'],
						'msg' => LANG::GET('consumables.edit_vendor_saved', [':name' => $vendor['name']]) . $pricelistImportError
					]]);
				else $this->response([
					'status' => [
						'id' => $vendor['id'],
						'name' => LANG::GET('consumables.edit_vendor_not_saved')
					]]);
				break;

			case 'GET':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$datalist = [];
				$options = ['...' . LANG::GET('consumables.edit_existing_vendors_new') => (!$this->_requestedID) ? ['selected' => true] : []];
				$result = [];
				
				// select single vendor based on id or name
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-vendor'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				if (!$vendor = $statement->fetch(PDO::FETCH_ASSOC)) $vendor = [
					'id' => null,
					'name' => '',
					'active' => 0,
					'info' => '',
					'certificate' => '{"validity":""}',
					'pricelist' => '{"validity":"", "filter": "", "trading_goods": ""}'
				];
				if ($this->_requestedID && $this->_requestedID !== 'false' && $this->_requestedID !== '...' . LANG::GET('consumables.edit_existing_vendors_new') && !$vendor['id'])
					$result['status'] = ['msg' => LANG::GET('consumables.error_vendor_not_found', [':name' => $this->_requestedID])];

				$vendor['certificate'] = json_decode($vendor['certificate'], true);
				$vendor['pricelist'] = json_decode($vendor['pricelist'], true);
				$isactive = $vendor['active'] ? ['checked' => true] : [];
				$isinactive = !$vendor['active'] ? ['checked' => true] : [];

				// prepare existing vendor lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-vendor-datalist'));
				$statement->execute();
				$vendorlist = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($vendorlist as $key => $row) {
					$datalist[] = $row['name'];
					$options[$row['name']] = [];
					if ($row['name'] == 
					$vendor['name']) 
					$options[$row['name']]['selected'] = true;
				}
				
				$certificates = [];
				$documents = [];
				if ($vendor['id']) {
					$certfiles = UTILITY::listFiles(UTILITY::directory('vendor_certificates', [':name' => $vendor['immutable_fileserver']]));
					foreach($certfiles as $path){
						$certificates[pathinfo($path)['basename']] = ['target' => '_blank', 'href' => $path];
					}
					$docfiles = UTILITY::listFiles(UTILITY::directory('vendor_documents', [':name' => $vendor['immutable_fileserver']]));
					foreach($docfiles as $path){
						$documents[pathinfo($path)['basename']] = ['target' => '_blank', 'href' => $path];
					}
				}
				// display form for adding a new vendor
				$result['body']=['content' => [
					[
						[
							'type' => 'datalist',
							'content' => $datalist,
							'attributes' => [
								'id' => 'vendors'
							]
						], [
							'type' => 'select',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_existing_vendors'),
								'onchange' => "api.purchase('get', 'vendor', this.value)"
							],
							'content' => $options
						], [
							'type' => 'searchinput',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_existing_vendors_search'),
								'list' => 'vendors',
								'onkeypress' => "if (event.key === 'Enter') {api.purchase('get', 'vendor', this.value); return false;}"
							]
						]
					], [
						[
							'type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_vendor_name'),
								'required' => true,
								'value' => $vendor['name'] ? : ''
							]
						], [
							'type' => 'textarea',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_vendor_info'),
								'value' => $vendor['info'] ? : '',
								'rows' => 8
							]
						], [
							'type' => 'radio',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_vendor_active')
							],
							'content' => [
								LANG::GET('consumables.edit_vendor_isactive') => $isactive,
								LANG::GET('consumables.edit_vendor_isinactive') => $isinactive
							]
						]
					], [
						[
							[
								'type' => 'dateinput',
								'attributes' => [
									'name' => LANG::GET('consumables.edit_vendor_certificate_validity'),
									'value' => $vendor['certificate']['validity'] ? : ''
								]
							]
						], [
							[
								'type' => 'file',
								'attributes' => [
									'name' => LANG::GET('consumables.edit_vendor_certificate_update')
								]
							]
						]
					], [
						[
							'type' => 'file',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_vendor_documents_update'),
								'multiple' => true
							]
						]
					], [
						[
							[
								'type' => 'file',
								'attributes' => [
									'name' => LANG::GET('consumables.edit_vendor_pricelist_update'),
									'accept' => '.csv'
								]
							]
						], [
							[
								'type' => 'textarea',
								'attributes' => [
									'name' => LANG::GET('consumables.edit_vendor_pricelist_filter'),
									'value' => $vendor['pricelist']['filter'] ? : '',
									'placeholder' => $this->filtersample,
									'rows' => 8
								]
							]
						], [
							[
								'type' => 'textarea',
								'attributes' => [
									'name' => LANG::GET('consumables.edit_vendor_pricelist_trading_filter'),
									'value' => array_key_exists('trading_goods', $vendor['pricelist']) && $vendor['pricelist']['trading_goods'] ? $vendor['pricelist']['trading_goods'] : '',
									'placeholder' => $this->tradingsample,
									'rows' => 8
								]
							]
						]
					]
				],
				'form' => [
					'data-usecase' => 'purchase',
					'action' => $vendor['id'] ? "javascript:api.purchase('put', 'vendor', '" . $vendor['id'] . "')" : "javascript:api.purchase('post', 'vendor')",
					'data-confirm' => true
				]];

				if ($certificates) array_splice($result['body']['content'][2], 0, 0,
					[
						[
							'type' => 'links',
							'description' => LANG::GET('consumables.edit_vendor_certificate_download'),
							'content' => $certificates
						]
					]
				);
				if ($documents) $result['body']['content'][3]=[
					[
						[
							'type' => 'links',
							'description' => LANG::GET('consumables.edit_vendor_documents_download'),
							'content' => $documents
						]
					],
					$result['body']['content'][3]
				];
				if ($vendor['pricelist']['validity']) array_splice($result['body']['content'][4], 0, 0,
					[[
						[
							'type' => 'text',
							'description' => LANG::GET('consumables.edit_vendor_pricelist_validity'),
							'content' => $vendor['pricelist']['validity']
						]
					]]
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
					'vendor_id' => null,
					'vendor_name' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_vendor_select')) !== LANG::GET('consumables.edit_product_vendor_select_default') ? UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_vendor_select')) : UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_vendor')),
					'article_no' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_no')),
					'article_name' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_name')),
					'article_alias' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_alias')),
					'article_unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_unit')),
					'article_ean' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_ean')),
					'active' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_active')) === LANG::GET('consumables.edit_product_isactive') ? 1 : 0,
					'protected' => 0,
					'trading_good' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_trading_good')) ? 1 : 0
				];

				// validate vendor
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-vendor'));
				$statement->execute([
					':id' => $product['vendor_name']
				]);
				if (!$vendor = $statement->fetch(PDO::FETCH_ASSOC)) $this->response(['status' => ['msg' => LANG::GET('consumables.error_vendor_not_found', [':name' => $product['vendor_name']])]]);
				$product['vendor_id'] = $vendor['id'];

				// save documents
				if (array_key_exists(LANG::PROPERTY('consumables.edit_product_documents_update'), $_FILES) && $_FILES[LANG::PROPERTY('consumables.edit_product_documents_update')]['tmp_name'][0]) {
					UTILITY::storeUploadedFiles([LANG::PROPERTY('consumables.edit_product_documents_update')], UTILITY::directory('vendor_products', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . date('Ymd') . '_' . $product['article_no']]);
					$product['protected'] = 1;
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_post-product'));
				if ($statement->execute([
					':vendor_id' => $product['vendor_id'],
					':article_no' => $product['article_no'],
					':article_name' => $product['article_name'],
					':article_alias' => $product['article_alias'],
					':article_unit' => $product['article_unit'],
					':article_ean' => $product['article_ean'],
					':active' => $product['active'],
					':protected' => $product['protected'],
					':trading_good' => $product['trading_good'],
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

				$product['vendor_name'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_vendor_select')) !== LANG::GET('consumables.edit_product_vendor_select_default') ? UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_vendor_select')) : UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_vendor'));
				$product['article_no'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_no'));
				$product['article_name'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_name'));
				$product['article_alias'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_alias'));
				$product['article_unit'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_unit'));
				$product['article_ean'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_ean'));
				$product['active'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_active')) === LANG::GET('consumables.edit_product_isactive') ? 1 : 0;
				$product['trading_good'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_trading_good')) ? 1 : 0;
				$product['incorporated'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_incorporated_revoke')) ? null : $product['incorporated'];

				// validate vendor
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-vendor'));
				$statement->execute([
					':id' => $product['vendor_name']
				]);
				if (!$vendor = $statement->fetch(PDO::FETCH_ASSOC)) $this->response(['status' => ['msg' => LANG::GET('consumables.error_vendor_not_found', [':name' => $product['vendor_name']])]]);
				$product['vendor_id'] = $vendor['id'];
				
				// save documents
				if (array_key_exists(LANG::PROPERTY('consumables.edit_product_documents_update'), $_FILES) && $_FILES[LANG::PROPERTY('consumables.edit_product_documents_update')]['tmp_name'][0]) {
					UTILITY::storeUploadedFiles([LANG::PROPERTY('consumables.edit_product_documents_update')], UTILITY::directory('vendor_products', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . date('Ymd') . '_' . $product['article_no']]);
					$product['protected'] = 1;
				}

				// sql server has a problem with actual updating null value
				$query = strtr(SQLQUERY::PREPARE('consumables_put-product'),[
					':incorporated' => $product['incorporated'] === null ? 'NULL' : $product['incorporated'], // without quotes
				]);
				$statement = $this->_pdo->prepare($query);
				if ($statement->execute([
					':id' => $this->_requestedID,
					':vendor_id' => $product['vendor_id'],
					':article_no' => $product['article_no'],
					':article_name' => $product['article_name'],
					':article_alias' => $product['article_alias'],
					':article_unit' => $product['article_unit'],
					':article_ean' => $product['article_ean'],
					':active' => $product['active'],
					':protected' => $product['protected'],
					':trading_good' => $product['trading_good'],
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
				$options = [LANG::GET('consumables.edit_product_vendor_select_default') => []];
				$datalist_unit = [];
				$result = [];
				$vendors=[];

				// select single product based on id or name
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-product'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				if (!$product = $statement->fetch(PDO::FETCH_ASSOC)) $product = [
					'id' => null,
					'vendor_id' => '',
					'vendor_name' => '',
					'vendor_immutable_fileserver' => '',
					'article_no' => '',
					'article_name' => '',
					'article_alias' => '',
					'article_unit' => '',
					'article_ean' => '',
					'active' => 1,
					'protected' => 0,
					'trading_good' => 0,
					'incorporated' => null,
				];
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$product['id']) $result['status'] = ['msg' => LANG::GET('consumables.error_product_not_found', [':name' => $this->_requestedID])];

				$isactive = $product['active'] ? ['checked' => true] : [];
				$isinactive = !$product['active'] ? ['checked' => true] : [];

				$certificates = [];
				$documents = [];
				if ($product['id']) {
					$docfiles = UTILITY::listFiles(UTILITY::directory('vendor_products', [':name' => $product['vendor_immutable_fileserver']]));
					foreach($docfiles as $path){
						$file = pathinfo($path);
						$article_no = explode('_', $file['filename'])[2];
						similar_text($article_no, $product['article_no'], $percent);
						if ($percent >= INI['likeliness']['consumables_article_no_similarity']) 
							$documents[$file['basename']] = ['target' => '_blank', 'href' => $path];
					}
				}

				// prepare existing vendor lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-vendor-datalist'));
				$statement->execute();
				$vendor = $statement->fetchAll(PDO::FETCH_ASSOC);

				$vendors[LANG::GET('consumables.edit_product_search_all_vendors')] = ['value' => implode('_', array_map(fn($r) => $r['id'], $vendor))];

				foreach($vendor as $key => $row) {
					$datalist[] = $row['name'];
					$options[$row['name']] = [];
					if ($row['name'] === $product['vendor_name']) $options[$row['name']]['selected'] = true;
					$vendors[$row['name']] = ['value' => $row['id']];

				}

				// prepare existing unit lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-product-units'));
				$statement->execute();
				$vendor = $statement->fetchAll(PDO::FETCH_ASSOC);
				foreach($vendor as $key => $row) {
					$datalist_unit[] = $row['article_unit'];
				}

				// display form for adding or editing a product
				$result['body'] = ['content' => [
					[
						[
							'type' => 'datalist',
							'content' => $datalist,
							'attributes' => [
								'id' => 'vendors'
							]
						], [
							'type' => 'datalist',
							'content' => $datalist_unit,
							'attributes' => [
								'id' => 'units'
							]
						], [
							'type' => 'button',
							'attributes' => [
								'value' => LANG::GET('consumables.edit_product_add_new'),
								'type' => 'button',
								'onpointerup' => "api.purchase('get', 'product')",
							]
						], [
							'type' => 'scanner',
							'destination' => 'productsearch'
						], [
							'type' => 'select',
							'content' => $vendors,
							'attributes' => [
								'id' => 'productsearchvendor',
								'name' => LANG::GET('consumables.edit_product_vendor_select')
								]
						], [
							'type' => 'searchinput',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_search'),
								'onkeypress' => "if (event.key === 'Enter') {api.purchase('get', 'productsearch', document.getElementById('productsearchvendor').value, this.value, 'editconsumables'); return false;}",
								'id' => 'productsearch'
							]
						]
					], [
						['type' => 'hr']
					], [
						[
							'type' => 'select',
							'numeration' => 'prevent',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_vendor_select'),
							],
							'content' => $options
						], [
							'type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_vendor'),
								'list' => 'vendors',
								'value' => $product['vendor_name']
							]
						], [
							'type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_article_no'),
								'required' => true,
								'value' => $product['article_no']
							]
						], [
							'type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_article_name'),
								'required' => true,
								'value' => $product['article_name']
							]
						], [
							'type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_article_alias'),
								'value' => $product['article_alias']
							]
						], [
							'type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_article_unit'),
								'list' => 'units',
								'required' => true,
								'value' => $product['article_unit']
							]
						], [
							'type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_article_ean'),
								'value' => $product['article_ean']
							]
						], [
							'type' => 'br'
						],
						[
							'type' => 'checkbox',
							'content' => [
								LANG::GET('consumables.edit_product_article_trading_good') => []
							]
						]
					], [
						[
							'type' => 'file',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_documents_update'),
								'multiple' => true
							],
							'hint' => LANG::GET('consumables.edit_product_documents_update_hint')
						]
					], [
						[
							'type' => 'radio',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_active')
							],
							'content' => [
								LANG::GET('consumables.edit_product_isactive') => $isactive,
								LANG::GET('consumables.edit_product_isinactive') => $isinactive
							]
						]
					]
				],
				'form' => [
					'data-usecase' => 'purchase',
					'action' => $product['id'] ? "javascript:api.purchase('put', 'product', '" . $product['id'] . "')" : "javascript:api.purchase('post', 'product')",
					'data-confirm' => true
					]];

				if ($documents) $result['body']['content'][3]=[
					[
						[
							'type' => 'links',
							'description' => LANG::GET('consumables.edit_product_documents_download'),
							'content' => $documents
						]
					],
					$result['body']['content'][3]
				];
				if ($product['trading_good']) $result['body']['content'][2][count($result['body']['content'][2]) -1]['content'][LANG::GET('consumables.edit_product_article_trading_good')] = ['checked' => true];
				if ($product['incorporated'] !== null) {
					array_push($result['body']['content'][2],
						[
							'type' => 'text',
							'description' => $product['incorporated'] ? LANG::GET('consumables.edit_product_incorporated_accepted') : LANG::GET('consumables.edit_product_incorporated_rejected')
						], [
							'type' => 'checkbox',
							'content' => [
								LANG::GET('consumables.edit_product_incorporated_revoke') => []
							]
						]);
				}
				else {
					$result['body']['content'][2][] = [
						'type' => 'text',
						'description' => LANG::GET('consumables.edit_product_incorporated_not')
					];
				}
				if ($product['id'] && !$product['protected']) array_push($result['body']['content'],
					[
						[
							'type' => 'deletebutton',
							'attributes' => [
								'value' => LANG::GET('consumables.edit_product_delete'),
								'type' => 'button', // apparently defaults to submit otherwise
								'onpointerup' => $product['id'] ? "new Dialog({type: 'confirm', header: '". LANG::GET('consumables.edit_product_delete_confirm_header', [':name' => $product['article_name']]) ."', 'options':{".
									"'".LANG::GET('consumables.edit_product_delete_confirm_cancel')."': false,".
									"'".LANG::GET('consumables.edit_product_delete_confirm_ok')."': {value: true, class: 'reducedCTA'},".
									"}}).then(confirmation => {if (confirmation) api.purchase('delete', 'product', " . $product['id'] . ")})" : ""
							]
						]
					]
				);
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
<?php
// add, edit and delete vendors and products
include_once('csvprocessor.php');

class CONSUMABLES extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $filtersample = <<<'END'
	{
		"filesettings": {
			"columns": [
				"Article Number",
				"Article Name",
				"EAN",
				"Sales Unit"
			]
		},
		"modify": {
			"add": {
				"trading_good": "0"
			},
			"replace":[
				["EAN", "\\s+", ""]
			],
			"conditional": [
				["trading_good", "1", ["Article Name", "ANY REGEX PATTERN THAT MIGHT MATCH ARTICLE NAMES THAT QUALIFY AS TRADING GOODS"]]
			],
			"rewrite": [{
				"article_no": ["Article Number"],
				"article_name": ["Article Name"],
				"article_ean": ["EAN"],
				"article_unit": ["Sales Unit"]
			}]
		}
	}
	END;

	/**
	 * init parent class and set private requests
	 */
	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	/**
	 * imports pricelist according to set filter and populates product database
	 * deletes all unprotected entries
	 * updates all protected entries based on vendor name and order number
	 * 
	 * chunkifies requests to avoid overflow
	 */
	private function update_pricelist($file, $filter, $vendorID){
		$filter = json_decode($filter, true);
		$filter['filesetting']['source'] = $file;
		$filter['filesetting']['encoding'] = INI['likeliness']['csvprocessor_source_encoding'];
		if (!array_key_exists('headerrowindex', $filter['filesetting'])) $filter['filesetting']['headerrowindex'] = INI['csv']['headerrowindex'];
		if (!array_key_exists('dialect', $filter['filesetting'])) $filter['filesetting']['dialect'] = INI['csv']['dialect'];
		$pricelist = new Listprocessor($filter);
		$sqlchunks = [];
		$date = '';
		try {
			if (!array_key_exists(1, $pricelist->_list)) $this->response([
				'status' => [
					'msg' => implode("\n", $pricelist->_log),
					'type' => 'error'
				]]);
		}
		catch(Error $e){
			$this->response([
				'status' => [
					'msg' => implode("\n", $pricelist->_log),
					'type' => 'error'
				]]);
		}
		if (count($pricelist->_list[1])){
			// purge all unprotected products for a fresh data set
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_delete-all-unprotected-products'));
			$statement->execute([
				':id' => $vendorID
			]);
			// retrieve left items
			$remainder = [];
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-products-by-vendor-id'));
			$statement->execute([
				':search' => $vendorID
			]);
			$remained = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach($remained as $row) {
				$remainder[] = ['id' => $row['id'], 'article_no' => $row['article_no'], 'incorporated' => ($row['incorporated'] ? (intval($row['incorporated']) === 1 ? 1 : 'NULL') : 0) ];
			}

			foreach (array_uintersect(array_column($pricelist->_list[1], 'article_no'), array_column($remainder, 'article_no'), fn($v1, $v2) => $v1 <=> $v2) as $index => $row){
				$update = array_search($row, array_column($remainder, 'article_no')); // this feels quite unperformant, but i don't know better
				$sqlchunks = SQLQUERY::CHUNKIFY($sqlchunks, strtr(SQLQUERY::PREPARE('consumables_put-product-protected'),
				[
					':id' => $remainder[$update]['id'],
					':article_name' => $this->_pdo->quote($pricelist->_list[1][$index]['article_name']),
					':article_unit' => $this->_pdo->quote($pricelist->_list[1][$index]['article_unit']),
					':article_ean' => $this->_pdo->quote($pricelist->_list[1][$index]['article_ean']),
					':trading_good' => array_key_exists('trading_good', $pricelist->_list[1][$index]) ? $this->_pdo->quote($pricelist->_list[1][$index]['trading_good']) : 0,
					':incorporated' => $remainder[$update]['incorporated']
				]) . '; ');
			}
			$insertions = [];
			foreach (array_udiff(array_column($pricelist->_list[1], 'article_no'), array_column($remainder, 'article_no'), fn($v1, $v2) => $v1 <=> $v2) as $index => $row){
				$insertions[]=[
					':vendor_id' => $vendorID,
					':article_no' => $this->_pdo->quote($pricelist->_list[1][$index]['article_no']),
					':article_name' => $this->_pdo->quote($pricelist->_list[1][$index]['article_name']),
					':article_alias' => "''",
					':article_unit' => $this->_pdo->quote($pricelist->_list[1][$index]['article_unit']),
					':article_ean' => $this->_pdo->quote($pricelist->_list[1][$index]['article_ean']),
					':active' => 1,
					':protected' => 0,
					':trading_good' => array_key_exists('trading_good', $pricelist->_list[1][$index]) ? $this->_pdo->quote($pricelist->_list[1][$index]['trading_good']) : 0,
					':incorporated' => 'NULL'
				];
			}
			$sqlchunks = array_merge($sqlchunks, SQLQUERY::CHUNKIFY_INSERT(SQLQUERY::PREPARE('consumables_post-product'), $insertions));

			foreach ($sqlchunks as $chunk){
				$statement = $this->_pdo->prepare($chunk);
				try {
					if ($statement->execute()) $date = date("d.m.Y");
				}
				catch (Exception $e) {
					echo $e, $chunk;
					die();
				}
			}
			return $date;
		}
		return '';
	}

	/**
	 * retrieves most recent approved form for
	 * sample check or
	 * incorporation
	 * and returns the components as body response for modal
	 */
	private function components($forContext){
		$formBody = [];
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-get-latest-by-context'));
		$statement->execute([':context' => $forContext]);
		if ($form = $statement->fetch(PDO::FETCH_ASSOC))
			foreach(explode(',', $form['content']) as $usedcomponent) {
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name-approved'));
				$statement->execute([
					':name' => $usedcomponent
				]);
				$component = $statement->fetch(PDO::FETCH_ASSOC);
				if ($component){
					$component['content'] = json_decode($component['content'], true);
					array_push($formBody, ...$component['content']['content']);
				}
			}
		return $formBody;
	}

	/**
	 * returns a js dialog script as defined within assemble.js
	 * @param string $target document elementId
	 * @param array $similarproducts prepared named array for checkbox
	 * @param string|array $substring start or [start, end]
	 * @param string $type input|input2
	 */
	private function selectSimilarDialog($target = '', $similarproducts = [], $substring = '0', $type = 'input'){
		if (gettype($substring) === 'array') $substring = implode(',', $substring);
		return "let similarproducts = " . json_encode($similarproducts) . "; selected = document.getElementById('" . $target . "').value.split(','); " .
			"for (const [key, value] of Object.entries(similarproducts)){ if (selected.includes(value.name.substr(1))) similarproducts[key].checked = true; } " .
			"new Dialog({type: '" . $type . "', header: '" . LANG::GET('consumables.edit_product_batch', [':percent' => INI['likeliness']['consumables_article_no_similarity']]) . 
			"', body: [{type: 'checkbox', content: similarproducts}], options:{".
			"'".LANG::GET('consumables.edit_product_delete_confirm_cancel')."': false,".
			"'".LANG::GET('consumables.edit_product_batch_confirm')."': {value: true, class: 'reducedCTA'}".
			"}}).then(response => { document.getElementById('" . $target . "').value = Object.keys(response) ? Object.keys(response).map(key=>{return key.substring(" . $substring . ")}).join(',') : '';})";
	}

	/**
	 * returns the mdr sample check body response for modal
	 * processes contents of the sample check and writes to the caro_checks database
	 * 
	 * $this->_payload->content is a string passed by utility.js _client.order.performSampleCheck()
	 */
	public function mdrsamplecheck(){
		if (!(array_intersect(['user', 'admin', 'ceo', 'qmo'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$statement = $this->_pdo->prepare(strtr(SQLQUERY::PREPARE('consumables_get-product'), [
					':ids' => intval($this->_requestedID)
				]));
				$statement->execute();
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
							'msg' => LANG::GET('order.sample_check_success'),
							'type' => 'success'
						]]);
				}
				$this->response([
					'status' => [
						'msg' => LANG::GET('order.sample_check_failure'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$statement = $this->_pdo->prepare(strtr(SQLQUERY::PREPARE('consumables_get-product'), [
					':ids' => intval($this->_requestedID)
				]));
				$statement->execute();
				if (!($product = $statement->fetch(PDO::FETCH_ASSOC))) $result['status'] = ['msg' => LANG::GET('consumables.error_product_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

				$result = ['body' => [
					'content' => [
						[
							[
								'type' => 'text',
								'description' => implode(' ', [
									$product['article_no'],
									$product['article_name'],
									$product['vendor_name']])
							]
						],
						...$this->components('mdr_sample_check_form')
					],
					'options' => [
						LANG::GET('order.sample_check_cancel') => false,
						LANG::GET('order.sample_check_submit') => ['value' => true, 'class' => 'reducedCTA']
					],
					'productid' => $product['id']
				]];
				$this->response($result);
				break;
		}
	}

	/**
	 * returns the incorporation body response for modal
	 * creates a list of possible identical products to refer to
	 * 
	 * processes contents of the incorporation and writes to the caro_checks database
	 * 
	 * $this->_payload->content is a string passed by utility.js _client.order.performIncorporation()
	 * incorporation denial is detected by pattern matching LANG::GET('order.incorporation_denied')
	 */
	public function incorporation(){
		if (!(array_intersect(['user', 'admin', 'ceo', 'qmo'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$batchincorporate = UTILITY::propertySet($this->_payload, '_batchincorporate');
				$ids = [];
				if ($batchincorporate){
					$ids = explode(',', $batchincorporate);
				}
				$statement = $this->_pdo->prepare(strtr(SQLQUERY::PREPARE('consumables_get-product'), [
					':ids' =>implode(',', [intval($this->_requestedID), ...array_map(Fn($id)=> intval($id), $ids)])
				]));
				$statement->execute();
				if (!($products = $statement->fetchAll(PDO::FETCH_ASSOC)) || !$this->_payload->content) $this->response([]);
				$content = '';
				foreach($products as $product){
					$content .= $product['vendor_name']. ' ' . $product['article_no'] . ' ' . $product['article_name'] . "\n";
				}

				// check denial
				preg_match("/" . LANG::GET('order.incorporation_denied') . ".*/m", $this->_payload->content, $denied);
				if ($denied) $content .= $denied[0];
				else $content .= $this->_payload->content;

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('checks_post'));
				if ($statement->execute([
					':type' => 'incorporation',
					':author' => $_SESSION['user']['name'],
					':content' => $content
				])) {
					$statement = $this->_pdo->prepare(strtr(SQLQUERY::PREPARE('consumables_put-incorporation'),[
						':ids' => implode(',', [intval($product['id']), ...array_map(Fn($id)=> intval($id), $ids)]),
						':incorporated' => intval(!boolval($denied))
					]));
					if ($statement->execute()) $this->response([
						'status' => [
							'msg' => LANG::GET('order.incorporation_success'),
							'type' => 'success'
						]]);
				}
				$this->response([
					'status' => [
						'msg' => LANG::GET('order.incorporation_failure'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$result = [];
				$statement = $this->_pdo->prepare(strtr(SQLQUERY::PREPARE('consumables_get-product'), [
					':ids' => intval($this->_requestedID)
				]));
				$statement->execute();
				if (!($product = $statement->fetch(PDO::FETCH_ASSOC))) $result['status'] = ['msg' => LANG::GET('consumables.error_product_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				$incorporationform = $this->components('product_incorporation_form');
				if ($product['trading_good']) array_push($incorporationform, ...$this->components('mdr_sample_check_form'));

				// select all products from selected vendor
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-products-by-vendor-id'));
				$statement->execute([
					':search' => $product['vendor_id']
				]);
				$vendorproducts = $statement->fetchAll(PDO::FETCH_ASSOC);
				$similarproducts = [];
				foreach($vendorproducts as $vendorproduct){
					if ($vendorproduct['article_no'] === $product['article_no']) continue;
					similar_text($vendorproduct['article_no'], $product['article_no'], $percent);
					if ($percent >= INI['likeliness']['consumables_article_no_similarity']) {
						$similarproducts[$vendorproduct['article_no'] . ' ' . $vendorproduct['article_name']] = ['name' => '_' . $vendorproduct['id']];
					}
				}
				if ($similarproducts){
					$incorporationform[] = [
						'type' => 'button',
						'content' => $similarproducts,
						'attributes' => [
							'type' => 'button',
							'value' => LANG::GET('order.incorporation_batch'),
							'onpointerup' => $this->selectSimilarDialog('_batchincorporate', $similarproducts, '1', 'input2')
						]
					];
					$incorporationform[] = [
						'type' => 'hiddeninput',
						'attributes' => [
							'id' => '_batchincorporate',
							'name' => '_batchincorporate'
						]
					];	
				}

				$result['body'] = [
					'content' => [
						[
							'type' => 'text',
							'description' => implode(' ', [
								$product['article_no'] ? : '',
								$product['article_name'] ? : '',
								$product['vendor_name'] ? : ''])
						], ...$incorporationform
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
				$hideduplicates = [];
				foreach($checks as $check){
					$check['content'] = explode("\n", $check['content']);
					$probability = [ 'article_no' => [], 'vendor_name' => []];
					$identifyproduct = implode(' ', $check['content']);
					if (!in_array($identifyproduct, $hideduplicates)){
						foreach ($check['content'] as $information){
							similar_text($information, $product['article_no'], $article_no_percent);
							if ($article_no_percent >= INI['likeliness']['consumables_article_no_similarity'] && $check['content'][count($check['content'])-1] !== LANG::GET('order.incorporation_revoked')) $probability['article_no'][] = $check['id'];
							similar_text($information, $product['vendor_name'], $vendor_name_percent);
							if ($vendor_name_percent >= INI['likeliness']['consumables_article_no_similarity'] && $check['content'][count($check['content'])-1] !== LANG::GET('order.incorporation_revoked')) $probability['vendor_name'][] = $check['id'];
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
									'onpointerup' => "document.getElementById('incorporationmatchingprevious').value = '" . $identifyproduct . "'",
								],
								'content' => [
									[
										'type' => 'text',
										'content' => implode("\n", $check['content'])
									]
								]
							];
						}
					}
					$hideduplicates[] = $identifyproduct;
				}
				if ($matches[0]){
					array_push($result['body']['content'], ...$matches);
					$result['body']['content'][] = [
						[
							'type' => 'textinput',
							'hint' => LANG::GET('order.incorporation_matching_previous_hint'),
							'attributes' => [
								'name' => LANG::GET('order.incorporation_matching_previous'),
								'id' => 'incorporationmatchingprevious',
								'readonly' => true
							]
						]
					];
				}
				$result['body']['content'][] = [
					[
						'type' => 'textarea',
						'hint' => LANG::GET('order.incorporation_denied_hint'),
						'attributes' => [
							'name' => LANG::GET('order.incorporation_denied'),
						]
					]
				];
				$this->response($result);
				break;
		}
	}

	/**
	 * vendor management
	 * posts new vendors
	 * updates (put) existing vendors
	 * dispays form to choose and edit vendors
	 * 
	 * on disabling vendor all unprotected products will be deleted
	 * 
	 * $this->_payload as genuine form data
	 */
	 public function vendor(){
		if (!(array_intersect(['admin', 'purchase', 'ceo', 'qmo'], $_SESSION['user']['permissions']))) $this->response([], 401);
		// Y U NO DELETE? because of audit safety, that's why!
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				/**
				 * 'immutable_fileserver' has to be set for windows server permissions are a pita
				 * thus directories can not be renamed on name changes of vendors
				 */
				$vendor = [
					'name' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_name')),
					'active' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_active')) === LANG::GET('consumables.edit_vendor_isactive') ? 1 : 0,
					'info' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_info')),
					'certificate' => ['validity' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_certificate_validity'))],
					'pricelist' => ['validity' => '', 'filter' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_pricelist_filter'))],
					'immutable_fileserver'=> UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_vendor_name')) . date('Ymd')
				];
				
				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $vendor['name'], $matches)) $this->response(['status' => ['msg' => LANG::GET('consumables.error_vendor_forbidden_name', [':name' => $vendor['name']]), 'type' => 'error']]);
				}

				// ensure valid json for filters
				if ($vendor['pricelist']['filter'] && !json_decode($vendor['pricelist']['filter'], true))  $this->response(['status' => ['msg' => LANG::GET('consumables.edit_vendor_pricelist_filter_json_error'), 'type' => 'error']]);

				// save certificate
				if (array_key_exists(LANG::PROPERTY('consumables.edit_vendor_certificate_update'), $_FILES) && $_FILES[LANG::PROPERTY('consumables.edit_vendor_certificate_update')]['tmp_name']) {
					UTILITY::storeUploadedFiles([LANG::PROPERTY('consumables.edit_vendor_certificate_update')], UTILITY::directory('vendor_certificates', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . date('Ymd')]);
				}
				// save documents
				if (array_key_exists(LANG::PROPERTY('consumables.edit_vendor_documents_update'), $_FILES) && $_FILES[LANG::PROPERTY('consumables.edit_vendor_documents_update')]['tmp_name']) {
					UTILITY::storeUploadedFiles([LANG::PROPERTY('consumables.edit_vendor_documents_update')], UTILITY::directory('vendor_documents', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . date('Ymd')]);
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
						'msg' => LANG::GET('consumables.edit_vendor_saved', [':name' => $vendor['name']]),
						'type' => 'info'
					]]);
				else $this->response([
					'status' => [
						'id' => false,
						'name' => LANG::GET('consumables.edit_vendor_not_saved'),
						'type' => 'error'
					]]);
				break;

			case 'PUT':
		
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

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $vendor['name'], $matches)) $this->response(['status' => ['msg' => LANG::GET('vendor.error_vendor_forbidden_name', [':name' => $vendor['name']]), 'type' => 'error']]);
				}

				// ensure valid json for filters
				if ($vendor['pricelist']['filter'] && !json_decode($vendor['pricelist']['filter'], true))  $this->response(['status' => ['msg' => LANG::GET('consumables.edit_vendor_pricelist_filter_json_error'), 'type' => 'error']]);

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
						'msg' => LANG::GET('consumables.edit_vendor_saved', [':name' => $vendor['name']]) . $pricelistImportError,
						'type' => 'info'
					]]);
				else $this->response([
					'status' => [
						'id' => $vendor['id'],
						'name' => LANG::GET('consumables.edit_vendor_not_saved'),
						'type' => 'error'
					]]);
				break;

			case 'GET':
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
					'pricelist' => '{"validity":"", "filter": ""}'
				];
				if ($this->_requestedID && $this->_requestedID !== 'false' && $this->_requestedID !== '...' . LANG::GET('consumables.edit_existing_vendors_new') && !$vendor['id'])
					$result['status'] = ['msg' => LANG::GET('consumables.error_vendor_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

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
								'type' => 'textarea',
								'attributes' => [
									'name' => LANG::GET('consumables.edit_vendor_pricelist_filter'),
									'value' => $vendor['pricelist']['filter'] ? : '',
									'placeholder' => $this->filtersample,
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

				if ($vendor['id'] && $vendor['active'] == 1)
					array_splice($result['body']['content'][4], 0, 0,
						[[[
							'type' => 'file',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_vendor_pricelist_update'),
								'accept' => '.csv'
							]
						]]]
					);
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

	/**
	 * product management
	 * posts new products
	 * updates (put) existing products
	 * displays form to choose and edit products
	 * 
	 * only unprotected products can be deleted, otherwise only hidden
	 * 
	 * adding documents sets protected flag
	 * 
	 * revoking incorporation state results in a caro_check entry
	 * 
	 * $this->_payload as genuine form data
	 */
	public function product(){
		if (!(array_intersect(['admin', 'purchase', 'ceo', 'qmo'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
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
					'protected' => intval(boolval(UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_alias')))),
					'trading_good' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_trading_good')) ? 1 : 0
				];

				// validate vendor
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-vendor'));
				$statement->execute([
					':id' => $product['vendor_name']
				]);
				if (!$vendor = $statement->fetch(PDO::FETCH_ASSOC)) $this->response(['status' => ['msg' => LANG::GET('consumables.error_vendor_not_found', [':name' => $product['vendor_name']]), 'type' => 'error']]);
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
						'msg' => LANG::GET('consumables.edit_product_saved', [':name' => $product['article_name']]),
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'id' => false,
						'name' => LANG::GET('consumables.edit_product_not_saved'),
						'type' => 'error'
					]]);
				break;

			case 'PUT':
				$statement = $this->_pdo->prepare(strtr(SQLQUERY::PREPARE('consumables_get-product'), [
					':ids' => intval($this->_requestedID)
				]));
				$statement->execute();
				// prepare product-array to update, return error if not found
				if (!($product = $statement->fetch(PDO::FETCH_ASSOC))) $result['status'] = ['msg' => LANG::GET('consumables.error_product_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

				$product['vendor_name'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_vendor_select')) && UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_vendor_select')) !== LANG::GET('consumables.edit_product_vendor_select_default') ? UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_vendor_select')) : UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_vendor'));
				$product['article_no'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_no'));
				$product['article_name'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_name'));
				$product['article_alias'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_alias'));
				$product['article_unit'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_unit'));
				$product['article_ean'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_ean'));
				$product['active'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_active')) === LANG::GET('consumables.edit_product_isactive') ? 1 : 0;
				$product['trading_good'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_trading_good')) ? 1 : 0;
				$product['incorporated'] = UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_incorporated_revoke')) ? null : $product['incorporated'];
				$product['protected'] = intval(boolval(UTILITY::propertySet($this->_payload, LANG::PROPERTY('consumables.edit_product_article_alias')))) ? : $product['protected'];
				
				// validate vendor
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-vendor'));
				$statement->execute([
					':id' => $product['vendor_name']
				]);
				if (!$vendor = $statement->fetch(PDO::FETCH_ASSOC)) $this->response(['status' => ['msg' => LANG::GET('consumables.error_vendor_not_found', [':name' => $product['vendor_name']]), 'type' => 'error']]);
				$product['vendor_id'] = $vendor['id'];
				
				// save documents
				if (array_key_exists(LANG::PROPERTY('consumables.edit_product_documents_update'), $_FILES) && $_FILES[LANG::PROPERTY('consumables.edit_product_documents_update')]['tmp_name'][0]) {
					UTILITY::storeUploadedFiles([LANG::PROPERTY('consumables.edit_product_documents_update')], UTILITY::directory('vendor_products', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . date('Ymd') . '_' . $product['article_no']]);
					$product['protected'] = 1;
				}

				// activate or deactivate selected similar products
				$batchactive = UTILITY::propertySet($this->_payload, '_batchactive');
				if ($batchactive){
					$ids = explode(',', $batchactive);
					$statement = $this->_pdo->prepare(strtr(SQLQUERY::PREPARE('consumables_put-batch'), [
						':field' => 'active',
						':value' => $product['active'],
						':ids' => implode(',', array_map(Fn($id) => intval($id), $ids)),
					]));
					$statement->execute();
				}
				// apply trading good to selected similar products
				$batchtradinggood = UTILITY::propertySet($this->_payload, '_batchtradinggood');
				if ($batchtradinggood){
					$ids = explode(',', $batchtradinggood);
					$statement = $this->_pdo->prepare(strtr(SQLQUERY::PREPARE('consumables_put-batch'), [
						':field' => 'trading_good',
						':value' => $product['trading_good'],
						':ids' => implode(',', array_map(Fn($id) => intval($id), $ids)),
					]));
					$statement->execute();
				}

				// sql server has a problem with actual updating null value
				$query = strtr(SQLQUERY::PREPARE('consumables_put-product'), [
					':incorporated' => $product['incorporated'] === null ? 'NULL' : $product['incorporated'], // without quotes
				]);
				$batchrevoke = UTILITY::propertySet($this->_payload, '_batchrevoke');
				if ($product['incorporated'] === null){
					$content = implode(' ', [$product['vendor_name'], $product['article_no'], $product['article_name']]);

					if ($batchrevoke){
						$ids = explode(',', $batchrevoke);
						$statement = $this->_pdo->prepare(strtr(SQLQUERY::PREPARE('consumables_put-batch'), [
							':field' => 'incorporated',
							':value' => 'NULL',
							':ids' => implode(',', array_map(Fn($id) => intval($id), $ids)),
						]));
						$statement->execute();
						$statement = $this->_pdo->prepare(strtr(SQLQUERY::PREPARE('consumables_get-product'), [
							':ids' => implode(',', array_map(Fn($id) => intval($id), $ids)),
						]));
						$statement->execute();
						$allsimilar = $statement->fetchAll(PDO::FETCH_ASSOC);
						foreach($allsimilar as $similar){
							$content .= "\n" . implode(' ', [$similar['vendor_name'], $similar['article_no'], $similar['article_name']]);
						}
					}
					$content .= "\n" . LANG::GET('order.incorporation_revoked');

					// record revoked state
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('checks_post'));
					$statement->execute([
						':type' => 'incorporation',
						':author' => $_SESSION['user']['name'],
						':content' => $content
					]);
				}

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
						'msg' => LANG::GET('consumables.edit_product_saved', [':name' => $product['article_name']]) . ($batchactive || $batchtradinggood || $batchrevoke ? '. ' . LANG::GET('consumables.edit_product_batch_saved'): ''),
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'id' => $this->_requestedID,
						'name' => LANG::GET('consumables.edit_product_not_saved'),
						'type' => 'error'
					]]);
				break;

			case 'GET':
				$datalist = [];
				$options = [LANG::GET('consumables.edit_product_vendor_select_default') => []];
				$datalist_unit = [];
				$result = [];
				$vendors=[];

				// select single product based on id or name
				$statement = $this->_pdo->prepare(strtr(SQLQUERY::PREPARE('consumables_get-product'), [
					':ids' => intval($this->_requestedID)
				]));
				$statement->execute();
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
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$product['id']) $result['status'] = ['msg' => LANG::GET('consumables.error_product_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

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
				// select all products from selected vendor
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_get-products-by-vendor-id'));
				$statement->execute([
					':search' => $product['vendor_id']
				]);
				$vendorproducts = $statement->fetchAll(PDO::FETCH_ASSOC);
				$similarproducts = [];
				foreach($vendorproducts as $vendorproduct){
					if ($vendorproduct['article_no'] === $product['article_no']) continue;
					similar_text($vendorproduct['article_no'], $product['article_no'], $percent);
					if ($percent >= INI['likeliness']['consumables_article_no_similarity']) {
						$similarproducts[$vendorproduct['article_no'] . ' ' . $vendorproduct['article_name']] = ['name' => '_' . $vendorproduct['id']];
					}
				}

				$isactive = $product['active'] ? ['checked' => true] : []; // 
				$isinactive = !$product['active'] ? ['checked' => true] : [];
				if ($similarproducts) $isinactive['onchange'] = $isactive['onchange'] = $this->selectSimilarDialog('_batchactive', $similarproducts, '1');
				
				$tradinggood = [LANG::GET('consumables.edit_product_article_trading_good') => []];
				if ($product['trading_good']) $tradinggood[LANG::GET('consumables.edit_product_article_trading_good')] = ['checked' => true];
				if ($similarproducts) $tradinggood[LANG::GET('consumables.edit_product_article_trading_good')]['onchange'] = $this->selectSimilarDialog('_batchtradinggood', $similarproducts, '1');

				$revoke = [LANG::GET('consumables.edit_product_incorporated_revoke') => []];
				if ($similarproducts) $revoke[LANG::GET('consumables.edit_product_incorporated_revoke')]['onchange'] = $this->selectSimilarDialog('_batchrevoke', $similarproducts, '1');

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
								'name' => LANG::GET('consumables.edit_product_filter_vendors')
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
								'value' => $product['vendor_name'],
							]
						], [
							'type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_article_no'),
								'required' => true,
								'value' => $product['article_no'],
							]
						], [
							'type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_article_name'),
								'required' => true,
								'value' => $product['article_name'],
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
								'value' => $product['article_unit'],
							]
						], [
							'type' => 'textinput',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_article_ean'),
								'value' => $product['article_ean'],
							]
						], [
							'type' => 'br'
						],
						[
							'type' => 'checkbox',
							'content' => $tradinggood,
							'hint' => LANG::GET('consumables.edit_product_similar_hint'),
						],
						[
							'type' => 'hiddeninput',
							'attributes' => [
								'id' => '_batchtradinggood',
								'name' => '_batchtradinggood'
							]
						]
					], [
						[
							'type' => 'file',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_documents_update'),
								'multiple' => true,
							],
							'hint' => LANG::GET('consumables.edit_product_documents_update_hint')
						]
					], [
						[
							'type' => 'radio',
							'attributes' => [
								'name' => LANG::GET('consumables.edit_product_active')
							],
							'hint' => LANG::GET('consumables.edit_product_similar_hint'),
							'content' => [
								LANG::GET('consumables.edit_product_isactive') => $isactive,
								LANG::GET('consumables.edit_product_isinactive') => $isinactive
							]
						], [
							'type' => 'hiddeninput',
							'attributes' => [
								'name' => '_batchactive',
								'id' => '_batchactive'
							]
						] 
					]
				],
				'form' => [
					'data-usecase' => 'purchase',
					'action' => $product['id'] ? "javascript:api.purchase('put', 'product', '" . $product['id'] . "')" : "javascript:api.purchase('post', 'product')",
					'data-confirm' => true
					]];
				
				if (array_intersect(['purchase_assistant'], $_SESSION['user']['permissions'])){
					$result['body']['content'][0][2]['attributes']['disabled'] =
					$result['body']['content'][2][0]['attributes']['disabled'] =
					$result['body']['content'][2][1]['attributes']['readonly'] =
					$result['body']['content'][2][2]['attributes']['readonly'] =
					$result['body']['content'][2][3]['attributes']['readonly'] =
					$result['body']['content'][2][5]['attributes']['readonly'] =
					$result['body']['content'][2][6]['attributes']['readonly'] =
					$result['body']['content'][3][0]['attributes']['readonly'] = true;
				}

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
				if ($product['incorporated'] !== null) {
					array_push($result['body']['content'][2],
						[
							'type' => 'text',
							'description' => ($product['incorporated'] ? (intval($product['incorporated']) === 1 ? LANG::GET('order.incorporation_accepted') : LANG::GET('order.incorporation_neccessary')) : LANG::GET('order.incorporation_denied'))
						], [
							'type' => 'checkbox',
							'content' => $revoke,
							'hint' => LANG::GET('consumables.edit_product_similar_hint'),
						], [
							'type' => 'hiddeninput',
							'attributes' => [
								'name' => '_batchrevoke',
								'id' => '_batchrevoke'
							]
						]
					);
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
								'onpointerup' => $product['id'] ? "new Dialog({type: 'confirm', header: '". LANG::GET('consumables.edit_product_delete_confirm_header', [':name' => $product['article_name']]) ."', options:{".
									"'".LANG::GET('consumables.edit_product_delete_confirm_cancel')."': false,".
									"'".LANG::GET('consumables.edit_product_delete_confirm_ok')."': {value: true, class: 'reducedCTA'}".
									"}}).then(confirmation => {if (confirmation) api.purchase('delete', 'product', " . $product['id'] . ")})" : ""
							]
						]
					]
				);
				$this->response($result);
				break;
		case 'DELETE':
				// prefetch to return proper name after deletion
				$statement = $this->_pdo->prepare(strtr(SQLQUERY::PREPARE('consumables_get-product'), [
					':ids' => intval($this->_requestedID)
				]));
				$statement->execute();
				$product = $statement->fetch(PDO::FETCH_ASSOC);

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('consumables_delete-unprotected-product'));
				if ($statement->execute([
					':id' => $product['id']
				])) $this->response([
					'status' => [
						'msg' => LANG::GET('consumables.edit_product_deleted', [':name' => $product['article_name']]),
						'id' => false,
						'type' => 'success'
					]]);
				else $this->response([
					'status' => [
						'msg' => LANG::GET('consumables.edit_product_not_deleted', [':name' => $product['article_name']]),
						'id' => $product['id'],
						'type' => 'error'
					]]);
			break;
		}
	}
}

$api = new CONSUMABLES();
$api->processApi();

exit;
?>
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

// add, edit and delete vendors and products
require_once('./_csvprocessor.php');

class CONSUMABLES extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
	private $_search = '';
	private $_usecase = '';
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
				"trading_good": "0",
				"has_expiry_date": "0",
				"special_attention": "0"
			},
			"replace":[
				["EAN", "\\s+", ""]
			],
			"conditional_and": [
				["trading_good", "1", ["Article Name", "ANY REGEX PATTERN THAT MIGHT MATCH ARTICLE NAMES THAT QUALIFY AS TRADING GOODS"]]
			],
			"conditional_or": [
				["has_expiry_date", "1", ["Article Number", "ANY REGEX PATTERN THAT MIGHT MATCH ARTICLE NUMBERS THAT HAVE AN EXPIRY DATE"]],
				["special_attention", "1", ["Article Number", "ANY REGEX PATTERN THAT MIGHT MATCH ARTICLE NUMBERS THAT NEED SPECIAL ATTENTION (E.G. BATCH NUMBER FOR HAVING SKIN CONTACT"]]
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
		if (!isset($_SESSION['user'])) $this->response([], 401);

		$this->_requestedID = isset(REQUEST[2]) ? REQUEST[2] : null;
		$this->_search = isset(REQUEST[3]) ? REQUEST[3] : null;
		$this->_usecase = isset(REQUEST[4]) ? REQUEST[4] : null;
	}

	/**
	 *                       _           _         _ _     _   
	 *   ___ _ _ ___ ___ ___| |_ ___ ___|_|___ ___| |_|___| |_ 
	 *  | -_|_'_| . | . |  _|  _| . |  _| |  _| -_| | |_ -|  _|
	 *  |___|_,_|  _|___|_| |_| |  _|_| |_|___|___|_|_|___|_|  
	 *          |_|             |_|
	 * exports the pricelist and filter, the latter simply generated if not provided
	 */
	public function exportpricelist(){
		if (!PERMISSION::permissionFor('products')) $this->response([], 401);
		$products = [];
		$vendor = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor', [
			'values' => [
				':id' => intval($this->_requestedID)
			]
		]);
		$vendor = $vendor ? $vendor[0] : [];
		if (!$vendor) $this->response([
			'response' => [
				'msg' => $this->_lang->GET('consumables.vendor.error_vendor_not_found'),
				'type' => 'error'
			]]);
		$products = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products_by_vendor_id', [
			'values' => [
				':ids' => $this->_requestedID
			]
		]);
		if (!$products) $this->response([
			'response' => [
				'msg' => $this->_lang->GET('consumables.vendor.pricelist_empty'),
				'type' => 'error'
			]]);

		// create csv
		$columns = ['article_no', 'article_name', 'article_unit', 'article_ean', 'trading_good', 'has_expiry_date', 'special_attention'];
		$tempFile = UTILITY::directory('tmp') . '/' . time() . $vendor['name'] . 'pricelist.csv';
		$file = fopen($tempFile, 'w');
		fwrite($file, b"\xEF\xBB\xBF"); // tell excel this is utf8
		fputcsv($file, $columns,
			CONFIG['csv']['dialect']['separator'],
			CONFIG['csv']['dialect']['enclosure'],
			CONFIG['csv']['dialect']['escape']);
		foreach($products as $row) {
			fputcsv($file, array_map(fn($column) => $row[$column], $columns),
			CONFIG['csv']['dialect']['separator'],
			CONFIG['csv']['dialect']['enclosure'],
			CONFIG['csv']['dialect']['escape']);
		}
		fclose($file);
		$downloadfiles[$this->_lang->GET('csvfilter.use.filter_download', [':file' => pathinfo($tempFile)['basename']])] = [
			'href' => './api/api.php/file/stream/' . substr($tempFile, 1),
			'download' => pathinfo($tempFile)['basename']
		];
		// create stupid filter for export files if none is provided
		$vendor['pricelist'] = json_decode($vendor['pricelist'] ? : '', true);
		$filter = isset($vendor['pricelist']['filter']) && json_decode($vendor['pricelist']['filter'], true) ? json_decode($vendor['pricelist']['filter'], true) : [
			'filesettings' => [
				'headerrowindex' => 0,
				'columns' => $columns
			]
		];
		$tempFile = UTILITY::directory('tmp') . '/' . time() . $vendor['name'] . 'pricelistfilter.txt';
		$file = fopen($tempFile, 'w');
		fwrite($file, json_encode($filter, JSON_PRETTY_PRINT));
		fclose($file);
		$downloadfiles[$this->_lang->GET('csvfilter.use.filter_download', [':file' => pathinfo($tempFile)['basename']])] = [
			'href' => './api/api.php/file/stream/' . substr(UTILITY::directory('tmp'), 1) . '/' . pathinfo($tempFile)['basename'],
			'download' => pathinfo($tempFile)['basename']
		];
		$this->response([
			'links' => $downloadfiles
		]);
	}

	/**
	 *   _                                 _   _
	 *  |_|___ ___ ___ ___ ___ ___ ___ ___| |_|_|___ ___
	 *  | |   |  _| . |  _| . | . |  _| .'|  _| | . |   |
	 *  |_|_|_|___|___|_| |  _|___|_| |__,|_| |_|___|_|_|
	 *                    |_|
	 * returns the incorporation body response for modal
	 * creates a list of possible identical products to refer to
	 * 
	 * processes contents of the incorporation and updates the products database
	 * 
	 * $this->_payload->content is a string passed by utility.js _client.order.performIncorporation()
	 * incorporation denial is detected by pattern matching $this->_lang->GET('order.incorporation.denied')
	 */
	public function incorporation(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				// retrieve ids from possible multiple selected products for inforporation
				$_batchupdate = UTILITY::propertySet($this->_payload, '_batchupdate');
				$ids = [];
				if ($_batchupdate){
					$ids = explode(',', $_batchupdate);
				}

				$products = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product', [
					'replacements' => [
						':ids' =>implode(',', [intval($this->_requestedID), ...array_map(Fn($id)=> intval($id), $ids)])
					]
				]);
				if (!$products || !$this->_payload->content) $this->response([]);

				// check content denial or not
				preg_match("/" . $this->_lang->GET('order.incorporation.denied') . ".*/m", $this->_payload->content, $denied);
				$approve = ['_check' => $denied ? $denied[0] : $this->_payload->content];
				if ($denied) $approve['_denied'] = true;

				// set approval for all permissions owned by submitting user
				$tobeapprovedby = ['user', ...PERMISSION::permissionFor('documentapproval', true)];
				$time = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
				foreach($tobeapprovedby as $permission){
					if (in_array($permission, $_SESSION['user']['permissions'])){
						$approve[$permission] = [
							'name' => $_SESSION['user']['name'],
							'date' => $time->format('Y-m-d H:i')
						];
					}
				}

				// update incorporation state for selected products
				if (SQLQUERY::EXECUTE($this->_pdo, 'consumables_put_incorporation', [
					'replacements' => [
						':ids' => implode(',', [intval($this->_requestedID), ...array_map(Fn($id)=> intval($id), $ids)]),
						':incorporated' => json_encode($approve)
					]
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('order.incorporation.success'),
						'type' => 'success'
					]]);
				$this->response([
					'response' => [
						'msg' => $this->_lang->GET('order.incorporation.failure'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$result = [];
				$product = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product', [
					'values' => [
						':ids' => intval($this->_requestedID)
					]
				]);
				$product = $product ? $product[0] : null;
				if (!$product) $result['response'] = ['msg' => $this->_lang->GET('consumables.product.error_product_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// get incorporation- and sample-check-documents, chain for eligible products
				require_once('_shared.php');
				$document = new SHARED($this->_pdo);
				$incorporationdocument = $document->recentdocument('document_document_get_by_context', [
					'values' => [
						':context' => 'product_incorporation_document'
					]])['content'];
				if ($product['trading_good']) array_push($incorporationdocument, ...$document->recentdocument('document_document_get_by_context', [
					'values' => [
						':context' => 'mdr_sample_check_document'
					]])['content']);

				// select similar products by article number from selected vendor
				$vendorproducts = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products_by_vendor_id', [
					'values' => [
						':ids' => intval($product['vendor_id'])
					]
				]);
				$similarproducts = [];
				foreach($vendorproducts as $vendorproduct){
					if ($vendorproduct['article_no'] === $product['article_no']) continue;
					similar_text($vendorproduct['article_no'], $product['article_no'], $percent);
					if ($percent >= CONFIG['likeliness']['consumables_article_no_similarity']) {
						$similarproducts[$vendorproduct['article_no'] . ' ' . $vendorproduct['article_name']] = ['name' => '_' . $vendorproduct['id']];
					}
				}
				// add selection of similar products to incorporation document
				if ($similarproducts){
					$incorporationdocument[] = [
						'type' => 'button',
						'content' => $similarproducts,
						'attributes' => [
							'type' => 'button',
							'value' => $this->_lang->GET('order.incorporation.batch'),
							'onpointerup' => $this->selectSimilarDialog('_batchupdate', $similarproducts, '1', 'input2')
						]
					];
					$incorporationdocument[] = [
						'type' => 'hidden',
						'attributes' => [
							'id' => '_batchupdate',
							'name' => '_batchupdate'
						]
					];	
				}

				// append incorporation elements so far to render output
				$result['render'] = [
					'content' => [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => implode(' ', [
									$product['article_no'] ? : '',
									$product['article_name'] ? : '',
									$product['vendor_name'] ? : ''])
							]
						], ...$incorporationdocument
					],
					'options' => [
							$this->_lang->GET('order.incorporation.cancel') => false,
							$this->_lang->GET('order.incorporation.submit') => [ 'value' => true, 'class'=> 'reducedCTA']
					],
					'productid' => $product['id']
				];

				// gather previous incorporation checks to select from
				// option to adopt previous checks based on smilarity of vendor and article number
				$checks = SQLQUERY::EXECUTE($this->_pdo, 'checks_get', [
					'values' => [
						':type' => 'incorporation'
					]
				]);
				$productsPerSlide = 0;
				$matches = [[]];
				$hideduplicates = [];
				foreach($checks as $check){
					$check['content'] = explode("\n", $check['content']); // extract check information
					$probability = [ 'article_no' => [], 'vendor_name' => []];
					$identifyproduct = implode(' ', $check['content']);
					if (!in_array($identifyproduct, $hideduplicates)){
						foreach ($check['content'] as $information){ // iterate over content in search for following similarities
							similar_text($information, $product['article_no'], $article_no_percent);
							if ($article_no_percent >= CONFIG['likeliness']['consumables_article_no_similarity'] && $check['content'][count($check['content'])-1] !== $this->_lang->GET('order.incorporation.revoked')) $probability['article_no'][] = $check['id'];
							similar_text($information, $product['vendor_name'], $vendor_name_percent);
							if ($vendor_name_percent >= CONFIG['likeliness']['consumables_article_no_similarity'] && $check['content'][count($check['content'])-1] !== $this->_lang->GET('order.incorporation.revoked')) $probability['vendor_name'][] = $check['id'];
						}
						// it has been similar enough, add to tile selection
						if (array_intersect($probability['article_no'], $probability['vendor_name'])){
							$article = intval(count($matches) - 1);
							if (empty($productsPerSlide++ % CONFIG['splitresults']['products_per_slide'])){
								$matches[$article][] = [
									[
										'type' => 'textsection',
										'attributes' => [
											'name' => $this->_lang->GET('order.incorporation.matching_previous')
										]
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
										'type' => 'textsection',
										'content' => implode("\n", $check['content'])
									]
								]
							];
						}
					}
					$hideduplicates[] = $identifyproduct;
				}
				// actuall add option to adopt previous, if any similar incorporations have been identified
				if ($matches[0]){
					array_push($result['render']['content'], ...$matches);
					$result['render']['content'][] = [
						[
							'type' => 'text',
							'hint' => $this->_lang->GET('order.incorporation.matching_previous_hint'),
							'attributes' => [
								'name' => $this->_lang->GET('order.incorporation.matching_previous'),
								'id' => 'incorporationmatchingprevious',
								'readonly' => true
							]
						]
					];
				}

				// add text input for denial statement
				$result['render']['content'][] = [
					[
						'type' => 'textarea',
						'hint' => $this->_lang->GET('order.incorporation.denied_hint'),
						'attributes' => [
							'name' => $this->_lang->GET('order.incorporation.denied'),
						]
					]
				];
				$this->response($result);
				break;
		}
	}

	/**
	 *           _                       _         _           _
	 *   _____ _| |___ ___ ___ _____ ___| |___ ___| |_ ___ ___| |_
	 *  |     | . |  _|_ -| .'|     | . | | -_|  _|   | -_|  _| '_|
	 *  |_|_|_|___|_| |___|__,|_|_|_|  _|_|___|___|_|_|___|___|_,_|
	 *                              |_|
	 * returns the mdr sample check body response for modal
	 * processes contents of the sample check and writes to the caro_checks database
	 * 
	 * $this->_payload->content is a string passed by utility.js _client.order.performSampleCheck()
	 */
	public function mdrsamplecheck(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$product = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product', [
					'values' => [
						':ids' => intval($this->_requestedID)
					]
				]);
				$product = $product ? $product[0] : null;

				if (!$product || !$this->_payload->content) $this->response([]);
				$content = implode("\n", [$product['vendor_name'], $product['article_no'], $product['article_name']]) . "\n" . $this->_payload->content;

				if (SQLQUERY::EXECUTE($this->_pdo, 'checks_post', [
					'values' => [
						':type' => 'mdrsamplecheck',
						':author' => $_SESSION['user']['name'],
						':content' => $content
					]
				])){
					if (SQLQUERY::EXECUTE($this->_pdo, 'consumables_put_check', [
						'values' => [
							':ids' => $product['id'],
						],
						'replacements' => [
							':checked' => 'CURRENT_TIMESTAMP'
						]
					])) {
						$this->alertUserGroup(['permission' => PERMISSION::permissionFor('mdrsamplecheck', true)],
						$this->_lang->GET('order.sample_check.alert', [
							':audit' => '<a href="javascript:void(0);" onpointerup="api.audit(\'get\', \'checks\', \'mdrsamplecheck\')">' . $this->_lang->GET('menu.tools.audit') . '</a>'
						], true) . $content);
						$this->response([
						'response' => [
							'msg' => $this->_lang->GET('order.sample_check.success'),
							'type' => 'success'
						]]);
					}
				}
				$this->response([
					'response' => [
						'msg' => $this->_lang->GET('order.sample_check.failure'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$product = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product', [
					'values' => [
						':ids' => intval($this->_requestedID)
					]
				]);
				$product = $product ? $product[0] : null;
				if (!$product) $result['response'] = ['msg' => $this->_lang->GET('consumables.product.error_product_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

				require_once('_shared.php');
				$document = new SHARED($this->_pdo);

				$result = ['render' => [
					'content' => [
						[
							[
								'type' => 'textsection',
								'attributes' => [
								'name' => implode(' ', [
										$product['article_no'],
										$product['article_name'],
										$product['vendor_name']])
								]
							]
						],
						...$document->recentdocument('document_document_get_by_context', [
							'values' => [
								':context' => 'mdr_sample_check_document'
							]])['content']
					],
					'options' => [
						$this->_lang->GET('order.sample_check.cancel') => false,
						$this->_lang->GET('order.sample_check.submit') => ['value' => true, 'class' => 'reducedCTA']
					],
					'productid' => $product['id']
				]];
				$this->response($result);
				break;
			case 'DELETE':
				if (!PERMISSION::permissionFor('mdrsamplecheck')) $this->response([], 401);
				// get check
				$check = SQLQUERY::EXECUTE($this->_pdo, 'checks_get_by_id', [
					'values' => [
						':id' => intval($this->_requestedID)
					]
				]);
				$check = $check ? $check[0] : null;

				// get product(s)
				$content = preg_split("/\n/", $check['content']);
				$vendor = $content[0];
				$article_no = $content[1];

				$products = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product_by_article_no_vendor', [
					'values' => [
						':article_no' => $article_no,
						':vendor' => $vendor
					]
				]);
				// set check to null
				SQLQUERY::EXECUTE($this->_pdo, 'consumables_put_check', [
					'values' => [
						':checked' => null
					],
					'replacements' => [
						':ids' => implode(',', [...array_map(Fn($row)=> intval($row['id']), $products)]),
					]
				]);
				// delete check from check-db
				if (SQLQUERY::EXECUTE($this->_pdo, 'checks_delete', [
					'values' => [
						':id' => intval($this->_requestedID)
					]
				])) $this->response([
					'response' => [
						'msg' => $this->_lang->GET('order.sample_check.revoked'),
						'type' => 'success'
					]]);
				$this->response([
					'response' => [
						'msg' => $this->_lang->GET('order.sample_check.failure'),
						'type' => 'error'
					]]);
				break;
		}
	}

	/**
	 *                 _ _         _                                 _   _
	 *   ___ ___ ___ _| |_|___ ___|_|___ ___ ___ ___ ___ ___ ___ ___| |_|_|___ ___ ___
	 *  | . | -_|   | . | |   | . | |   |  _| . |  _| . | . |  _| .'|  _| | . |   |_ -|
	 *  |  _|___|_|_|___|_|_|_|_  |_|_|_|___|___|_| |  _|___|_| |__,|_| |_|___|_|_|___|
	 *  |_|                   |___|                 |_|
	 * display pending incorporations, links to product editing
	 */
	public function pendingincorporations(){
		if (!PERMISSION::permissionFor('incorporation')) $this->response([], 401);
		$result = ['render' => ['content' => []]];
		$links = [];
		$allproducts = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products_incorporation_attention');
		foreach($allproducts as $product) {
			if ($product['incorporated']) continue;
			$product['incorporated'] = json_decode($product['incorporated'] ? : '', true);
			if (isset($product['incorporated']['_denied'])) continue;
			elseif (!PERMISSION::fullyapproved('incorporation', $product['incorporated'])) $links[$product['vendor_name'] . ' ' . $product['article_no'] . ' ' . $product['article_name']] = ['href' => 'javascript:void(0)', 'onpointerup' => "api.purchase('get', 'product', " . $product['id'] . ")"];
		}
		if ($links){
			$result['render']['content'][] = [
				[
					'type' => 'links',
					'content' => $links
					]
			];
			$this->response($result);
		}
		else $this->response(['render' => ['content' => $this->noContentAvailable($this->_lang->GET('consumables.product.incorporation_no_approvals'))]]);
	}

	/**
	 *                 _         _
	 *   ___ ___ ___ _| |_ _ ___| |_
	 *  | . |  _| . | . | | |  _|  _|
	 *  |  _|_| |___|___|___|___|_|
	 *  |_|
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
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!PERMISSION::permissionFor('products')) $this->response([], 401);
				$product = [
					'id' => null,
					'vendor_id' => null,
					'vendor_name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.vendor_select')) !== $this->_lang->GET('consumables.product.vendor_select_default') ? UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.vendor_select')) : UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.vendor')),
					'article_no' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.article_no')) ? : null,
					'article_name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.article_name')) ? : null,
					'article_alias' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.article_alias')) ? : null,
					'article_unit' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.article_unit')) ? : null,
					'article_ean' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.article_ean')) ? : null,
					'hidden' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.availability')) === $this->_lang->GET('consumables.product.hidden') ? json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_currentdate->format('Y-m-d H:i:s')]) : null,
					'protected' => null,
					'trading_good' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.article_trading_good')) ? 1 : null,
					'has_expiry_date' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.expiry_date')) ? 1 : null,
					'special_attention' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.special_attention')) ? 1 : null,
				];

				// validate vendor
				$vendor = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor', [
					'values' => [
						':id' => $product['vendor_name']
					]
				]);
				$vendor = $vendor ? $vendor[0] : null;
				if (!$vendor) $this->response(['response' => ['msg' => $this->_lang->GET('consumables.vendor.error_vendor_not_found', [':name' => $product['vendor_name']]), 'type' => 'error']]);
				$product['vendor_id'] = $vendor['id'];

				// save documents
				if (isset($_FILES[$this->_lang->PROPERTY('consumables.product.documents_update')]) && $_FILES[$this->_lang->PROPERTY('consumables.product.documents_update')]['tmp_name'][0]) {
					UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('consumables.product.documents_update')], UTILITY::directory('vendor_products', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . $this->_currentdate->format('Ymd') . '_' . $product['article_no']]);
					$product['protected'] = 1;
				}

				if (SQLQUERY::EXECUTE($this->_pdo, 'consumables_post_product', [
					'values' => [
						':vendor_id' => $product['vendor_id'],
						':article_no' => $product['article_no'],
						':article_name' => $product['article_name'],
						':article_alias' => $product['article_alias'],
						':article_unit' => $product['article_unit'],
						':article_ean' => $product['article_ean'],
						':hidden' => $product['hidden'],
						':protected' => $product['protected'],
						':trading_good' => $product['trading_good'],
						':has_expiry_date' => $product['has_expiry_date'],
						':special_attention' => $product['special_attention'],
					]
				])) $this->response([
					'response' => [
						'id' => $this->_pdo->lastInsertId(),
						'msg' => $this->_lang->GET('consumables.product.saved', [':name' => $product['article_name']]),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'id' => false,
						'name' => $this->_lang->GET('consumables.product.not_saved'),
						'type' => 'error'
					]]);
				break;

			case 'PUT':
				if (!PERMISSION::permissionFor('products') && !PERMISSION::permissionFor('productslimited')) $this->response([], 401);
				// prepare product-array to update, return error if not found
				$product = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product', [
					'values' => [
						':ids' => intval($this->_requestedID)
					]
				]);
				$product = $product ? $product[0] : null;
				if (!$product) $result['response'] = ['msg' => $this->_lang->GET('consumables.product.error_product_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

				// hand over payload to product properties
				$product['article_alias'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.article_alias')) ? : null;
				if (!PERMISSION::permissionFor('productslimited')){
					$product['vendor_name'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.vendor_select')) && UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.vendor_select')) !== $this->_lang->GET('consumables.product.vendor_select_default') ? UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.vendor_select')) : UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.vendor'));
					$product['article_no'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.article_no')) ? : null;
					$product['article_name'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.article_name')) ? : null;
					$product['article_unit'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.article_unit')) ? : null;
					$product['article_ean'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.article_ean')) ? : null;
					$product['hidden'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.availability')) === $this->_lang->GET('consumables.product.hidden') ? json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_currentdate->format('Y-m-d H:i:s')]) : null;
					$product['trading_good'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.article_trading_good')) ? 1 : null;
					$product['has_expiry_date'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.expiry_date')) ? 1 : null;
					$product['special_attention'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.product.special_attention')) ? 1 : null;
				}

				// handle incorporation options that have not yet been approved
				if (PERMISSION::permissionFor('incorporation') && $product['incorporated']) {
					if ($incorporation = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('order.incorporation.state_approve'))){
						$incorporation = explode(', ', $incorporation);
						if (in_array($this->_lang->GET('consumables.product.incorporated_revoke'), $incorporation)) $product['incorporated'] = '';
						else {
							$product['incorporated'] = json_decode($product['incorporated'] ? : '', true);
							$time = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
							foreach($this->_lang->_USER['permissions'] as $permission => $translation){
								if (in_array($translation, $incorporation)) $product['incorporated'][$permission] = [
									'name' => $_SESSION['user']['name'],
									'date' => $time->format('Y-m-d H:i')
								];
							}
							$product['incorporated'] = json_encode($product['incorporated']);
						}
					}
				}

				// validate vendor
				$vendor = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor', [
					'values' => [
						':id' => $product['vendor_name']
					]
				]);
				$vendor = $vendor ? $vendor[0] : null;
				if (!$vendor) $this->response(['response' => ['msg' => $this->_lang->GET('consumables.vendor.error_vendor_not_found', [':name' => $product['vendor_name']]), 'type' => 'error']]);
				$product['vendor_id'] = $vendor['id'];
				
				// save documents
				if (PERMISSION::permissionFor('products') && isset($_FILES[$this->_lang->PROPERTY('consumables.product.documents_update')]) && $_FILES[$this->_lang->PROPERTY('consumables.product.documents_update')]['tmp_name'][0]) {
					UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('consumables.product.documents_update')], UTILITY::directory('vendor_products', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . $this->_currentdate->format('Ymd') . '_' . $product['article_no']]);
					$product['protected'] = 1;
				}

				// apply settings on similar products if selected
				// this could have been one block but parting of the respective states is considered relevent

				// activate or deactivate selected similar products
				$batchactive = UTILITY::propertySet($this->_payload, '_batchactive');
				if (PERMISSION::permissionFor('products') && $batchactive){
					$ids = explode(',', $batchactive);
					SQLQUERY::EXECUTE($this->_pdo, 'consumables_put_batch', [
						'values' => [
							':value' => $product['hidden'],
						],
						'replacements' => [
							':field' => 'hidden',
							':ids' => implode(',', array_map(Fn($id) => intval($id), $ids)),	
						]
					]);
				}

				// update trading good, expiry date or special attention on similar products
				$_batchupdate = UTILITY::propertySet($this->_payload, '_batchupdate');
				if (PERMISSION::permissionFor('products') && $_batchupdate){
					$ids = explode(',', $_batchupdate);

					foreach(['trading_good', 'has_expiry_date', 'special_attention'] as $field){ // apply setting to selected similar products
						SQLQUERY::EXECUTE($this->_pdo, 'consumables_put_batch', [
							'values' => [
								':value' => $product[$field],
							],
							'replacements' => [
								':field' => $field,
								':ids' => implode(',', array_map(Fn($id) => intval($id), $ids)),	
							]
						]);	
					}
					// update incorporation state on similar products
					if (PERMISSION::permissionFor('incorporation')){
						SQLQUERY::EXECUTE($this->_pdo, 'consumables_put_batch', [
							'replacements' => [
								':field' => 'incorporated',
								':value' => $product['incorporated'],
								':ids' => implode(',', array_map(Fn($id) => intval($id), $ids)),	
							]
						]);
					}
				}
				require_once('notification.php');
				$notifications = new NOTIFICATION;

				// update product
				if (SQLQUERY::EXECUTE($this->_pdo, 'consumables_put_product', [
					'values' => [
						':id' => $this->_requestedID,
						':vendor_id' => $product['vendor_id'],
						':article_no' => $product['article_no'],
						':article_name' => $product['article_name'],
						':article_alias' => $product['article_alias'] ? : null,
						':article_unit' => $product['article_unit'],
						':article_ean' => $product['article_ean'],
						':hidden' => $product['hidden'],
						':protected' => $product['protected'],
						':trading_good' => $product['trading_good'],
						':incorporated' => $product['incorporated'] ? : null,
						':has_expiry_date' => $product['has_expiry_date'],
						':special_attention' => $product['special_attention'],
					]
				])) $this->response([
					'response' => [
						'id' => $this->_requestedID,
						'msg' => $this->_lang->GET('consumables.product.saved', [':name' => $product['article_name']]) . ($batchactive || $_batchupdate ? '. ' . $this->_lang->GET('consumables.product.batch_saved'): ''),
						'type' => 'success'
						],
						'data' => ['order_unprocessed' => $notifications->order(), 'consumables_pendingincorporation' => $notifications->consumables()]
					]);
				else $this->response([
					'response' => [
						'id' => $this->_requestedID,
						'msg' => $this->_lang->GET('consumables.product.not_saved'),
						'type' => 'error'
					]]);
				break;

			case 'GET':
				$datalist = [];
				$options = [$this->_lang->GET('consumables.product.vendor_select_default') => []];
				$datalist_unit = [];
				$result = [];
				$vendors = [];

				// select single product based on id or name
				$product = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product', [
					'values' => [
						':ids' => intval($this->_requestedID)
						]
				]);
				$product = $product ? $product[0] : null;

				// set up product properties
				if (!$product) $product = [
					'id' => null,
					'vendor_id' => '',
					'vendor_name' => UTILITY::propertySet($this->_payload, 'vendor_name') ? : '',
					'vendor_immutable_fileserver' => '',
					'article_no' => UTILITY::propertySet($this->_payload, 'article_no') ? : '',
					'article_name' => UTILITY::propertySet($this->_payload, 'article_name') ? : '',
					'article_alias' => '',
					'article_unit' => UTILITY::propertySet($this->_payload, 'article_unit') ? : '',
					'article_ean' => '',
					'hidden' => null,
					'protected' => null,
					'trading_good' => null,
					'incorporated' => '',
					'has_expiry_date' => '',
					'special_attention' => '',
				];
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$product['id']) $result['response'] = ['msg' => $this->_lang->GET('consumables.product.error_product_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

				$certificates = [];
				$documents = [];

				// gather files
				$docfiles = UTILITY::listFiles(UTILITY::directory('vendor_products', [':name' => $product['vendor_immutable_fileserver']]));
				foreach($docfiles as $path){
					$file = pathinfo($path);
					$article_no = explode('_', $file['filename'])[2];
					similar_text($article_no, $product['article_no'], $percent);
					if ($percent >= CONFIG['likeliness']['consumables_article_no_similarity']) 
						$documents[$file['basename']] = ['target' => '_blank', 'href' => './api/api.php/file/stream/' . substr($path,1)];
				}
				// select all products from selected vendor, retrieve similar products
				$vendorproducts = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products_by_vendor_id', [
					'values' => [
						':ids' => intval($product['vendor_id'])
					]
				]);
				$similarproducts = [];
				foreach($vendorproducts as $vendorproduct){
					if ($vendorproduct['article_no'] === $product['article_no']) continue;
					similar_text($vendorproduct['article_no'], $product['article_no'], $percent);
					if ($percent >= CONFIG['likeliness']['consumables_article_no_similarity']) {
						$similarproducts[$vendorproduct['article_no'] . ' ' . $vendorproduct['article_name']] = ['name' => '_' . $vendorproduct['id']];
					}
				}

				// set up property toggles and apply dialog selecting similar product if any
				$isactive = !$product['hidden'] ? ['checked' => true] : [];
				$isinactive = $product['hidden'] ? ['checked' => true, 'class' => 'red'] : ['class' => 'red'];
				if ($similarproducts) $isinactive['onchange'] = $isactive['onchange'] = $this->selectSimilarDialog('_batchactive', $similarproducts, '1');
				
				$regulatoryoptions = [
					$this->_lang->GET('consumables.product.article_trading_good') => ($product['trading_good']) ? ['checked' => true] : [],
					$this->_lang->GET('consumables.product.expiry_date') => $product['has_expiry_date'] ? ['checked' => true] : [],
					$this->_lang->GET('consumables.product.special_attention') => $product['special_attention'] ? ['checked' => true] : [],
				];
				if ($similarproducts) {
					$regulatoryoptions[$this->_lang->GET('consumables.product.article_trading_good')]['onchange'] = $this->selectSimilarDialog('_batchupdate', $similarproducts, '1');
					$regulatoryoptions[$this->_lang->GET('consumables.product.expiry_date')]['onchange'] = $this->selectSimilarDialog('_batchupdate', $similarproducts, '1');
					$regulatoryoptions[$this->_lang->GET('consumables.product.special_attention')]['onchange'] = $this->selectSimilarDialog('_batchupdate', $similarproducts, '1');
				}

				// prepare existing vendor lists
				$vendor = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');

				$vendors[$this->_lang->GET('consumables.product.search_all_vendors')] = ['value' => implode('_', array_map(fn($r) => $r['id'], $vendor))];

				foreach($vendor as $key => $row) {
					$datalist[] = $row['name'];
					$display = $row['name'];
					if ($row['hidden']) $display = UTILITY::hiddenOption($display);
					$options[$display] = [];
					if ($row['name'] === $product['vendor_name']) $options[$display]['selected'] = true;
					$vendors[$display] = ['value' => $row['id']];
					if ($row['name'] === $product['vendor_name']) $product['vendor_id'] = $row['id']; 
				}
				ksort($options);
				ksort($vendors);

				// prepare existing delivery unit lists
				$vendor = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product_units');
				foreach($vendor as $key => $row) {
					$datalist_unit[] = $row['article_unit'];
				}

				$hidden = null;
				if ($product['hidden']) {
					$hiddenproperties = json_decode($product['hidden'], true);
					$hidden = $this->_lang->GET('consumables.product.edit_hidden_set', [':date' => $hiddenproperties['date'], ':name' => $hiddenproperties['name']]);
				}

				// switch between display- and edit mode 
				if (!PERMISSION::permissionFor('products') && !PERMISSION::permissionFor('productslimited')) {
					// standard user view
					// render search and selection
					$result['render'] = ['content' => [
						[
							[
								'type' => 'scanner',
								'destination' => 'productsearch'
							], [
								'type' => 'select',
								'content' => $vendors,
								'attributes' => [
									'id' => 'productsearchvendor',
									'name' => $this->_lang->GET('consumables.product.filter_vendors')
									]
							], [
								'type' => 'search',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.search'),
									'onkeypress' => "if (event.key === 'Enter') {api.purchase('get', 'productsearch', document.getElementById('productsearchvendor').value, this.value, 'productinformation'); return false;}",
									'id' => 'productsearch'
								]
							]
						], [
							['type' => 'hr']
						]
					]];
					
					if ($product['id']){
						// deactivate inputs for regular users
						$isactive['disabled'] = $isinactive['disabled'] = true;
						foreach($regulatoryoptions as &$option){
							$option['disabled'] = true;
						}
						// display available product information
						$result['render']['content'][] = [
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $product['article_no'] . ' ' . $product['article_name']. ($product['article_alias'] ? ' (' . $product['article_alias'] . ') ' : ' ') . $product['article_unit']
								],
								'content' => $product['vendor_name']
							],
							[
								'type' => 'br'
							],
							[
								'type' => 'checkbox',
								'content' => $regulatoryoptions,
							],
							[
								'type' => 'radio',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.availability')
								],
								'content' => [
									$this->_lang->GET('consumables.product.available') => $isactive,
									$this->_lang->GET('consumables.product.hidden') => $isinactive
								],
								'hint' => $hidden
							]
						];

						// inform about last order
						if ($product['id'] && $product['last_order']){
							$result['render']['content'][2][] = [
								'type' => 'textsection',
								'attributes' => [
									'name' => $this->_lang->GET('order.order_last_ordered', [':date' => substr($product['last_order'], 0, -9)])
								],
							];
						}

						// inform about incorporation state
						if ($product['incorporated']) {					
							$product['incorporated'] = json_decode($product['incorporated'] ? : '', true);
							$incorporationState = '';
							if (isset($product['incorporated']['_denied'])) $incorporationState = $this->_lang->GET('order.incorporation.denied');
							elseif (!PERMISSION::fullyapproved('incorporation', $product['incorporated'])) $incorporationState = $this->_lang->GET('order.incorporation.pending');
							elseif (PERMISSION::fullyapproved('incorporation', $product['incorporated'])) $incorporationState = $this->_lang->GET('order.incorporation.accepted');
			
							$incorporationInfo = str_replace(["\r", "\n"], ['', " \n"], $product['incorporated']['_check']);
							foreach(['user', ...PERMISSION::permissionFor('incorporation', true)] as $permission){
								if (isset($product['incorporated'][$permission])) $incorporationInfo .= " \n" . $this->_lang->_USER['permissions'][$permission] . ' ' . $product['incorporated'][$permission]['name'] . ' ' . $product['incorporated'][$permission]['date'];
							}
							$result['render']['content'][2][] = [
								'type' => 'textsection',
								'attributes' => [
									'name' => $incorporationState
								],
								'content' => $incorporationInfo
							];
						}
						else {
							$result['render']['content'][2][] = [
								'type' => 'textsection',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.incorporated_not')
								]
							];
						}
						if ($documents) {
							$result['render']['content'][2][] = [
								'type' => 'links',
								'description' => $this->_lang->GET('consumables.product.documents_download'),
								'content' => $documents
							];
						}
					}
				}
				else {
					// display form for adding or editing a product
					$result['render'] = ['content' => [
						[
							[
								'type' => 'button',
								'attributes' => [
									'value' => $this->_lang->GET('consumables.product.add_new'),
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
									'name' => $this->_lang->GET('consumables.product.filter_vendors')
									]
							], [
								'type' => 'search',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.search'),
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
									'name' => $this->_lang->GET('consumables.product.vendor_select'),
								],
								'content' => $options
							], [
								'type' => 'text',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.vendor'),
									'value' => $product['vendor_name'],
								],
								'datalist' => array_values(array_unique($datalist))
							], [
								'type' => 'text',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.article_no'),
									'required' => true,
									'value' => $product['article_no'],
								]
							], [
								'type' => 'text',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.article_name'),
									'required' => true,
									'value' => $product['article_name'],
								]
							], [
								'type' => 'text',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.article_alias'),
									'value' => $product['article_alias']
								]
							], [
								'type' => 'text',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.article_unit'),
									'required' => true,
									'value' => $product['article_unit'],
								],
								'datalist' => array_values(array_unique($datalist_unit))
							], [
								'type' => 'text',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.article_ean'),
									'value' => $product['article_ean'],
								]
							], 
						]
					]];
					// append form for authorized users
					if (PERMISSION::permissionFor('products') || PERMISSION::permissionFor('productslimited')){
						$result['render']['form'] = [
						'data-usecase' => 'purchase',
						'action' => $product['id'] ? "javascript:api.purchase('put', 'product', '" . $product['id'] . "')" : "javascript:api.purchase('post', 'product')",
						'data-confirm' => true
						];
					}

					// deactivate inputs for restricted users
					if (!PERMISSION::permissionFor('products')){
						$result['render']['content'][0][2]['attributes']['disabled'] = // add new product
						$result['render']['content'][2][0]['attributes']['disabled'] = // select vendor
						$result['render']['content'][2][1]['attributes']['readonly'] = // type vendor
						$result['render']['content'][2][2]['attributes']['readonly'] = // article number
						$result['render']['content'][2][3]['attributes']['readonly'] = // article name
						$result['render']['content'][2][4]['attributes']['readonly'] = // article alias
						$result['render']['content'][2][5]['attributes']['readonly'] = // order unit
						true; 
					}
					if (PERMISSION::permissionFor('productslimited')){
						unset($result['render']['content'][2][4]['attributes']['readonly']); // article alias
					}

					// append toggles
					if (PERMISSION::permissionFor('products')){
						$result['render']['content'][] = [
							[
								'type' => 'br'
							],
							[
								'type' => 'checkbox',
								'content' => $regulatoryoptions,
								'hint' => $this->_lang->GET('consumables.product.may_affect_import_filter')
							],
							[
								'type' => 'hidden',
								'attributes' => [
									'id' => '_batchupdate',
									'name' => '_batchupdate'
								]
							],
						];
						
						// inform about number of similar products
						if ($product['vendor_name']) $result['render']['content'][count($result['render']['content']) - 1] = [
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.similar_count', [':number' => count($similarproducts)]) . ($product['vendor_name'] && !$product['vendor_id'] ? ".\n" . $this->_lang->GET('consumables.vendor.error_vendor_not_found', [':name' => $product['vendor_name']]) : '')
								]
							],
							...$result['render']['content'][count($result['render']['content']) - 1]
						];

						// add file upload
						$result['render']['content'][] = [
							[
								'type' => 'file',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.documents_update'),
									'multiple' => true,
								],
								'hint' => $this->_lang->GET('consumables.product.documents_update_hint')
							]
						];

						// add availability toggle
						$result['render']['content'][] = [
							[
								'type' => 'radio',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.availability')
								],
								'hint' => $this->_lang->GET('consumables.product.similar_hint'),
								'content' => [
									$this->_lang->GET('consumables.product.available') => $isactive,
									$this->_lang->GET('consumables.product.hidden') => $isinactive
								],
								'hint' => $hidden
							], [
								'type' => 'hidden',
								'attributes' => [
									'name' => '_batchactive',
									'id' => '_batchactive'
								]
							] 
						];
					}

					// add last order info
					if ($product['id'] && $product['last_order']){
						$result['render']['content'][2][] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('order.order_last_ordered', [':date' => substr($product['last_order'], 0, -9)])
							]
						];
					}

					// add download options for documents
					if ($documents) {
						if (isset($result['render']['content'][4]))
							$result['render']['content'][4] = [
								[
									[
										'type' => 'links',
										'description' => $this->_lang->GET('consumables.product.documents_download'),
										'content' => $documents
									]
								],
								$result['render']['content'][4]
							];
						else $result['render']['content'][] = [
							[
								[
									'type' => 'links',
									'description' => $this->_lang->GET('consumables.product.documents_download'),
									'content' => $documents
								]
							]
						];
					}

					// add incorporation state
					if ($product['incorporated']) {					
						$product['incorporated'] = json_decode($product['incorporated'] ? : '', true);
						$incorporationState = '';
						if (isset($product['incorporated']['_denied'])) $incorporationState = $this->_lang->GET('order.incorporation.denied');
						elseif (!PERMISSION::fullyapproved('incorporation', $product['incorporated'])) $incorporationState = $this->_lang->GET('order.incorporation.pending');

						$incorporationInfo = str_replace(["\r", "\n"], ['', " \n"], $product['incorporated']['_check']);
						foreach(['user', ...PERMISSION::permissionFor('incorporation', true)] as $permission){
							if (isset($product['incorporated'][$permission])) $incorporationInfo .= " \n" . $this->_lang->_USER['permissions'][$permission] . ' ' . $product['incorporated'][$permission]['name'] . ' ' . $product['incorporated'][$permission]['date'];
						}

						array_push($result['render']['content'][3],
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $incorporationState
								],
								'content' => $incorporationInfo
							]);
						if (PERMISSION::permissionFor('incorporation')){
							$incorporation = [];
							foreach(PERMISSION::pending('incorporation', $product['incorporated']) as $position){
								$incorporation[$this->_lang->GET('permissions.' . $position)] = [];
								if ($similarproducts) $incorporation[$this->_lang->GET('permissions.' . $position)]['onchange'] = $this->selectSimilarDialog('_batchupdate', $similarproducts, '1');
							}
							$incorporation[$this->_lang->GET('consumables.product.incorporated_revoke')] = [];
							if ($similarproducts) $incorporation[$this->_lang->GET('consumables.product.incorporated_revoke')]['onchange'] = $this->selectSimilarDialog('_batchupdate', $similarproducts, '1');
							array_push($result['render']['content'][3], [
									'type' => 'checkbox',
									'attributes' => [
										'name' => $this->_lang->GET('order.incorporation.state_approve')
									],
									'content' => $incorporation,
									'hint' => $this->_lang->GET('consumables.product.similar_hint'),
								]
							);
						}
					}
					else {
						$result['render']['content'][3][] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('consumables.product.incorporated_not')
							],
							'hint' => $this->_lang->GET('consumables.product.similar_hint'),
						];
					}

					// add delete button for eligible products
					if ($product['id'] && !$product['protected'] && !$product['article_alias'] && !$product['checked'] && !$product['incorporated'] && !PERMISSION::permissionFor('productslimited')) array_push($result['render']['content'],
						[
							[
								'type' => 'deletebutton',
								'attributes' => [
									'value' => $this->_lang->GET('consumables.product.delete'),
									'type' => 'button', // apparently defaults to submit otherwise
									'onpointerup' => $product['id'] ? "new _client.Dialog({type: 'confirm', header: '". $this->_lang->GET('consumables.product.delete_confirm_header', [':name' => $product['article_name']]) ."', options:{".
										"'".$this->_lang->GET('consumables.product.delete_confirm_cancel')."': false,".
										"'".$this->_lang->GET('consumables.product.delete_confirm_ok')."': {value: true, class: 'reducedCTA'}".
										"}}).then(confirmation => {if (confirmation) api.purchase('delete', 'product', " . $product['id'] . ")})" : ""
								]
							]
						]
					);
				}
				$this->response($result);
				break;
		case 'DELETE':
			if (!PERMISSION::permissionFor('products')) $this->response([], 401);
			// prefetch to return proper name after deletion
			$product = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product', [
				'values' => [
					':ids' => intval($this->_requestedID)
				]
			]);
			$product = $product ? $product[0] : ['id' => null];

			if (SQLQUERY::EXECUTE($this->_pdo, 'consumables_delete_unprotected_product', [
				'values' => [
					':id' => $product['id']
					]
			])) $this->response([
				'response' => [
					'msg' => $this->_lang->GET('consumables.product.deleted', [':name' => $product['article_name']]),
					'id' => false,
					'type' => 'success'
				]]);
			else $this->response([
				'response' => [
					'msg' => $this->_lang->GET('consumables.product.not_deleted', [':name' => $product['article_name']]),
					'id' => $product['id'],
					'type' => 'error'
				]]);
			break;
		}
	}
	
	/**
	 *                 _         _                       _
	 *   ___ ___ ___ _| |_ _ ___| |_ ___ ___ ___ ___ ___| |_
	 *  | . |  _| . | . | | |  _|  _|_ -| -_| .'|  _|  _|   |
	 *  |  _|_| |___|___|___|___|_| |___|___|__,|_| |___|_|_|
	 *  |_|
	 */
	public function productsearch(){
		require_once('_shared.php');
		$search = new SHARED($this->_pdo);
		$result = ['render' => ['content' => $search->productsearch($this->_usecase, ['search' => $this->_search, 'vendors' => $this->_requestedID])]];

		$this->response($result);
	}

	/**
	 *           _         _       _       _ _           _ _     _
	 *   ___ ___| |___ ___| |_ ___|_|_____|_| |___ ___ _| |_|___| |___ ___
	 *  |_ -| -_| | -_|  _|  _|_ -| |     | | | .'|  _| . | | .'| | . | . |
	 *  |___|___|_|___|___|_| |___|_|_|_|_|_|_|__,|_| |___|_|__,|_|___|_  |
	 *                                                                |___|
	 * returns a js dialog script as defined within assemble.js
	 * @param string $target document elementId
	 * @param array $similarproducts prepared named array for checkbox
	 * @param string|array $substring start or [start, end]
	 * @param string $type input|input2 destination for js-dialog class
	 * 
	 * @param string js event
	 */
	private function selectSimilarDialog($target = '', $similarproducts = [], $substring = '0', $type = 'input'){
		if (gettype($substring) === 'array') $substring = implode(',', $substring);
		return "let similarproducts = " . json_encode($similarproducts) . "; selected = document.getElementById('" . $target . "').value.split(','); " .
			"for (const [key, value] of Object.entries(similarproducts)){ if (selected.includes(value.name.substr(1))) similarproducts[key].checked = true; } " .
			"new _client.Dialog({type: '" . $type . "', header: '" . $this->_lang->GET('consumables.product.batch', [':percent' => CONFIG['likeliness']['consumables_article_no_similarity']]) . 
			"', render: [{type: 'checkbox', content: similarproducts}], options:{".
			"'".$this->_lang->GET('consumables.product.delete_confirm_cancel')."': false,".
			"'".$this->_lang->GET('consumables.product.batch_confirm')."': {value: true, class: 'reducedCTA'}".
			"}}).then(response => { document.getElementById('" . $target . "').value = Object.keys(response) ? Object.keys(response).map(key=>{return key.substring(" . $substring . ")}).join(',') : '';})";
	}

	/**
	 *             _     _               _         _ _     _
	 *   _ _ ___ _| |___| |_ ___ ___ ___|_|___ ___| |_|___| |_
	 *  | | | . | . | .'|  _| -_| . |  _| |  _| -_| | |_ -|  _|
	 *  |___|  _|___|__,|_| |___|  _|_| |_|___|___|_|_|___|_|
	 *      |_|                 |_|
	 * imports pricelist according to set filter and populates product database
	 * deletes all unprotected entries
	 * updates all protected entries based on vendor name and order number
	 * 
	 * chunkifies requests to avoid overflow
	 */
	private function update_pricelist($file, $filter, $vendorID){
		$filter = json_decode($filter, true);
		$filter['filesetting']['source'] = $file;
		$filter['filesetting']['encoding'] = CONFIG['likeliness']['csvprocessor_source_encoding'];
		if (!isset($filter['filesetting']['headerrowindex'])) $filter['filesetting']['headerrowindex'] = CONFIG['csv']['headerrowindex'];
		if (!isset($filter['filesetting']['dialect'])) $filter['filesetting']['dialect'] = CONFIG['csv']['dialect'];
		$pricelist = new Listprocessor($filter);
		$sqlchunks = [];
		$date = '';
		try {
			if (!isset($pricelist->_list[1])) $this->response([
				'response' => [
					'msg' => implode("\n", $pricelist->_log),
					'type' => 'error'
				]]);
		}
		catch(Error $e){
			$this->response([
				'response' => [
					'msg' => implode("\n", $pricelist->_log),
					'type' => 'error'
				]]);
		}
		if (count($pricelist->_list[1])){
			// purge all unprotected products for a fresh data set
			SQLQUERY::EXECUTE($this->_pdo, 'consumables_delete_all_unprotected_products', [
				'values' => [
					':id' => $vendorID
				]
			]);
			// retrieve left items
			$remainder = [];
			$remained = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products_by_vendor_id', [
				'values' => [
					':ids' => intval($vendorID)
				]
			]);
			foreach($remained as $row) {
				$remainder[] = ['id' => $row['id'], 'article_no' => $row['article_no'], 'incorporated' => $row['incorporated']];
			}

			// update remainders
			foreach (array_uintersect(array_column($pricelist->_list[1], 'article_no'), array_column($remainder, 'article_no'), fn($v1, $v2) => $v1 <=> $v2) as $index => $row){
				$update = array_search($row, array_column($remainder, 'article_no')); // this feels quite unperformant, but i don't know better
				$sqlchunks = SQLQUERY::CHUNKIFY($sqlchunks, strtr(SQLQUERY::PREPARE('consumables_put_product_pricelist_import'),
				[
					':id' => $remainder[$update]['id'],
					':article_name' => $pricelist->_list[1][$index]['article_name'] ? $this->_pdo->quote(preg_replace('/\n/', '', $pricelist->_list[1][$index]['article_name'])) : 'NULL',
					':article_unit' => $pricelist->_list[1][$index]['article_unit'] ? $this->_pdo->quote(preg_replace('/\n/', '', $pricelist->_list[1][$index]['article_unit'])) : 'NULL',
					':article_ean' => $pricelist->_list[1][$index]['article_ean'] ? $this->_pdo->quote(preg_replace('/\n/', '', $pricelist->_list[1][$index]['article_ean'])) : 'NULL',
					':trading_good' => isset($pricelist->_list[1][$index]['trading_good']) ? intval($pricelist->_list[1][$index]['trading_good']) : 'NULL',
					':has_expiry_date' => isset($pricelist->_list[1][$index]['has_expiry_date']) ? intval($pricelist->_list[1][$index]['has_expiry_date']) : 'NULL',
					':special_attention' => isset($pricelist->_list[1][$index]['special_attention']) ? intval($pricelist->_list[1][$index]['special_attention']) : 'NULL',
					':incorporated' => $remainder[$update]['incorporated'] ? $this->_pdo->quote($remainder[$update]['incorporated']) : 'NULL'
				]) . '; ');
			}

			// insert replacements
			$insertions = [];
			foreach (array_udiff(array_column($pricelist->_list[1], 'article_no'), array_column($remainder, 'article_no'), fn($v1, $v2) => $v1 <=> $v2) as $index => $row){
				$insertions[] = [
					':vendor_id' => $vendorID,
					':article_no' => preg_replace('/\n/', '', $pricelist->_list[1][$index]['article_no']) ? : null,
					':article_name' => preg_replace('/\n/', '', $pricelist->_list[1][$index]['article_name']) ? : null,
					':article_alias' => null,
					':article_unit' => preg_replace('/\n/', '', $pricelist->_list[1][$index]['article_unit']) ? : null,
					':article_ean' => preg_replace('/\n/', '', $pricelist->_list[1][$index]['article_ean']) ? : null,
					':hidden' => null,
					':protected' => null,
					':trading_good' => isset($pricelist->_list[1][$index]['trading_good']) ? intval($pricelist->_list[1][$index]['trading_good']) : null,
					':incorporated' => null,
					':has_expiry_date' => isset($pricelist->_list[1][$index]['has_expiry_date']) ? intval($pricelist->_list[1][$index]['has_expiry_date']) : null,
					':special_attention' => isset($pricelist->_list[1][$index]['special_attention']) ? intval($pricelist->_list[1][$index]['special_attention']) : null,
				];
			}
			$sqlchunks = array_merge($sqlchunks, SQLQUERY::CHUNKIFY_INSERT($this->_pdo, SQLQUERY::PREPARE('consumables_post_product'), $insertions));

			foreach ($sqlchunks as $chunk){
				try {
					if (SQLQUERY::EXECUTE($this->_pdo, $chunk)) $date = $this->_currentdate->format("d.m.Y");
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
	 *                 _
	 *   _ _ ___ ___ _| |___ ___
	 *  | | | -_|   | . | . |  _|
	 *   \_/|___|_|_|___|___|_|
	 *
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
		// Y U NO DELETE? because of audit safety, that's why!
		// dynamic vendor info fields with storage key and lang-property value
		// only defined once here, audit.php and on output form (GET) for typing ?
		$vendor_info = [
			'purchase_info' => 'consumables.vendor.purchase_info',
			'infotext' => 'consumables.vendor.info',
			'mail' => 'consumables.vendor.mail',
			'phone' => 'consumables.vendor.phone',
			'address' => 'consumables.vendor.address',
			'sales_representative' => 'consumables.vendor.sales_representative',
			'customer_id' => 'consumables.vendor.customer_id',
		];

		// retrieve vendor evaluation document
		require_once('_shared.php');
		$sharedfunction = new SHARED($this->_pdo);
		$evaluationdocument = $sharedfunction->recentdocument('document_document_get_by_context', [
			'values' => [
				':context' => 'vendor_evaluation_document'
			]]);
		$evaluationdocument = $evaluationdocument ? $evaluationdocument['content'] : [];

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!PERMISSION::permissionFor('vendors')) $this->response([], 401);
				/**
				 * 'immutable_fileserver' has to be set for windows server permissions are a pita
				 * thus directories can not be renamed on name changes of vendors
				 */
				$vendor = [
					'name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.vendor.name')),
					'hidden' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.vendor.availability')) === $this->_lang->GET('consumables.vendor.hidden') ? json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_currentdate->format('Y-m-d H:i:s')]) : null,
					'info' => array_map(Fn($value) => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY($value)) ? : null, $vendor_info),
					'certificate' => ['validity' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.vendor.certificate_validity'))],
					'pricelist' => ['validity' => '', 'filter' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.vendor.pricelist_filter'))],
					'immutable_fileserver' => preg_replace(CONFIG['forbidden']['names']['characters'], '', UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.vendor.name'))) . $this->_currentdate->format('Ymd'),
					'evaluation' => null
				];
				
				// check forbidden names
				if(UTILITY::forbiddenName($vendor['name'])) $this->response(['response' => ['msg' => $this->_lang->GET('consumables.vendor.error_vendor_forbidden_name', [':name' => $vendor['name']]), 'type' => 'error']]);

				// ensure valid json for filters
				if (isset($vendor['pricelist']['filter']) && !json_decode($vendor['pricelist']['filter'], true))  $this->response(['response' => ['msg' => $this->_lang->GET('consumables.vendor.pricelist_filter_json_error'), 'type' => 'error']]);

				// save certificate
				if (isset($_FILES[$this->_lang->PROPERTY('consumables.vendor.certificate_update')]) && $_FILES[$this->_lang->PROPERTY('consumables.vendor.certificate_update')]['tmp_name']) {
					UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('consumables.vendor.certificate_update')], UTILITY::directory('vendor_certificates', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . $this->_currentdate->format('Ymd')]);
				}
				// save documents
				if (isset($_FILES[$this->_lang->PROPERTY('consumables.vendor.documents_update')]) && $_FILES[$this->_lang->PROPERTY('consumables.vendor.documents_update')]['tmp_name']) {
					UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('consumables.vendor.documents_update')], UTILITY::directory('vendor_documents', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . $this->_currentdate->format('Ymd')]);
				}

				// unset all beckend defined payload variables leaving vendor evaluation inputs
				foreach ([...array_values($vendor_info),
					'consumables.vendor.edit_existing_vendors',
					'consumables.vendor.edit_existing_vendors_search',
					'consumables.vendor.name',
					'consumables.vendor.availability',
					'consumables.vendor.available',
					'consumables.vendor.certificate_validity',
					'consumables.vendor.pricelist_filter',
					'consumables.vendor.certificate_update',
					'consumables.vendor.documents_update',
					'consumables.vendor.message_vendor_select_special_attention_products'
				] as $var) {
					unset($this->_payload->{$this->_lang->PROPERTY($var)});
				}

				// create proper evaluation data
				// unset checkboxes while relying on a prepared additional dataset
				// unset empty values
				$evaluation = [];
				foreach($this->_payload as $key => &$value){
					if (gettype($value) === 'array') $value = trim(implode(' ', $value));
					/////////////////////////////////////////
					// BEHOLD! unsetting value==on relies on a prepared formdata/_payload having a dataset containing all selected checkboxes
					////////////////////////////////////////
					if (!$value || $value === 'on' || $value === '...') unset($this->_payload->$key);
					else $evaluation[$key] = $value;
				}
				// check if any required fields have been left out, else construct evaluation data
				if ($missing = $sharedfunction->unmatchedrequired($evaluationdocument, $evaluation)) {
					$this->response([
						'response' => [
							'id' => $vendor['id'],
							'msg' => $this->_lang->GET('general.missing_form_data') . "\n". implode("\n- ", $missing),
							'type' => 'error'
						]]);
				}
				if ($vendor['evaluation'] = $evaluation){
					$vendor['evaluation']['_author'] = $_SESSION['user']['name'];
					$vendor['evaluation']['_date'] = $this-_currentdate->format('Y-m-d');
				}
				else $vendor['evaluation'] = null;

				// tidy up unused properties
				foreach($vendor['info'] as $key => $value){
					if (!$value) unset($vendor['info'][$key]);
				}
				foreach($vendor['certificate'] as $key => $value){
					if (!$value) unset($vendor['certificate'][$key]);
				}
				foreach($vendor['pricelist'] as $key => $value){
					if (!$value) unset($vendor['pricelist'][$key]);
				}

				// save vendor to database
				if (SQLQUERY::EXECUTE($this->_pdo, 'consumables_post_vendor', [
					'values' => [
						':name' => $vendor['name'],
						':hidden' => $vendor['hidden'],
						':info' => $vendor['info'] ? json_encode($vendor['info']) : null,
						':certificate' => $vendor['certificate'] ? json_encode($vendor['certificate']) : null,
						':pricelist' => $vendor['pricelist'] ? json_encode($vendor['pricelist']) : null,
						':immutable_fileserver' => $vendor['immutable_fileserver'],
						':evaluation' => $vendor['evaluation']
					]
				])) $this->response([
					'response' => [
						'id' => $this->_pdo->lastInsertId(),
						'msg' => $this->_lang->GET('consumables.vendor.saved', [':name' => $vendor['name']]),
						'type' => 'info'
					]]);
				else $this->response([
					'response' => [
						'id' => false,
						'name' => $this->_lang->GET('consumables.vendor.not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'PUT':
				if (!PERMISSION::permissionFor('vendors')) $this->response([], 401);
				// prepare vendor-array to update, return error if not found
				$vendor = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor', [
					'values' => [
						':id' => $this->_requestedID
					]
				]);
				$vendor = $vendor ? $vendor[0] : null;
				if (!$vendor) $this->response(null, 406);

				// update vendor data
				$vendor['hidden'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.vendor.availability')) === $this->_lang->GET('consumables.vendor.hidden') ? json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_currentdate->format('Y-m-d H:i:s')]) : null;
				$vendor['name'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.vendor.name'));
				$vendor['info'] = array_map(Fn($value) => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY($value)) ? : '', $vendor_info);
				$vendor['certificate'] = json_decode($vendor['certificate'] ? : '', true);
				$vendor['certificate']['validity'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.vendor.certificate_validity'));
				$vendor['pricelist'] = json_decode($vendor['pricelist'] ? : '', true);
				$vendor['pricelist']['filter'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.vendor.pricelist_filter'));
				$vendor['pricelist']['samplecheck_interval'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.vendor.samplecheck_interval')) ? : CONFIG['lifespan']['mdr14_sample_interval'];
				$vendor['pricelist']['samplecheck_reusable'] = UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('consumables.vendor.samplecheck_interval_reusable')) ? : CONFIG['lifespan']['mdr14_sample_reusable'];

				// check forbidden names
				if(UTILITY::forbiddenName($vendor['name'])) $this->response(['response' => ['msg' => $this->_lang->GET('consumables.vendor.error_vendor_forbidden_name', [':name' => $vendor['name']]), 'type' => 'error']]);

				// ensure valid json for filters
				if (isset($vendor['pricelist']['filter']) && !json_decode($vendor['pricelist']['filter'], true)) $this->response(['response' => ['msg' => $this->_lang->GET('consumables.vendor.pricelist_filter_json_error'), 'type' => 'error']]);

				// save certificate
				if (isset($_FILES[$this->_lang->PROPERTY('consumables.vendor.certificate_update')]) && $_FILES[$this->_lang->PROPERTY('consumables.vendor.certificate_update')]['tmp_name']) {
					UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('consumables.vendor.certificate_update')], UTILITY::directory('vendor_certificates', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . $this->_currentdate->format('Ymd')]);
				}
				// save documents
				if (isset($_FILES[$this->_lang->PROPERTY('consumables.vendor.documents_update')]) && $_FILES[$this->_lang->PROPERTY('consumables.vendor.documents_update')]['tmp_name']) {
					UTILITY::storeUploadedFiles([$this->_lang->PROPERTY('consumables.vendor.documents_update')], UTILITY::directory('vendor_documents', [':name' => $vendor['immutable_fileserver']]), [$vendor['name'] . '_' . $this->_currentdate->format('Ymd')]);
				}
				// update pricelist
				$pricelistImportError = '';
				if (isset($_FILES[$this->_lang->PROPERTY('consumables.vendor.pricelist_update')]) && $_FILES[$this->_lang->PROPERTY('consumables.vendor.pricelist_update')]['tmp_name']) {
					$vendor['pricelist']['validity'] = $this->update_pricelist($_FILES[$this->_lang->PROPERTY('consumables.vendor.pricelist_update')]['tmp_name'][0], $vendor['pricelist']['filter'], $vendor['id']);
					if (!strlen($vendor['pricelist']['validity'])) $pricelistImportError = $this->_lang->GET('consumables.vendor.pricelist_update_error');
				}

				// tidy up consumable products database if inactive
				if ($vendor['hidden']){
					SQLQUERY::EXECUTE($this->_pdo, 'consumables_delete_all_unprotected_products', [
						'values' => [
							':id' => $vendor['id']
							]
					]);
					unset ($vendor['pricelist']['validity']);
				}

				// unset all beckend defined payload variables leaving vendor evaluation inputs
				foreach ([...array_values($vendor_info),
					'consumables.vendor.edit_existing_vendors',
					'consumables.vendor.edit_existing_vendors_search',
					'consumables.vendor.name',
					'consumables.vendor.availability',
					'consumables.vendor.available',
					'consumables.vendor.certificate_validity',
					'consumables.vendor.pricelist_filter',
					'consumables.vendor.certificate_update',
					'consumables.vendor.documents_update',
					'consumables.vendor.message_vendor_select_special_attention_products'
				] as $var) {
					unset($this->_payload->{$this->_lang->PROPERTY($var)});
				}

				// create proper evaluation data
				// unset checkboxes while relying on a prepared additional dataset
				// unset empty values
				$evaluation = [];
				foreach($this->_payload as $key => &$value){
					if (gettype($value) === 'array') $value = trim(implode(' ', $value));
					/////////////////////////////////////////
					// BEHOLD! unsetting value==on relies on a prepared formdata/_payload having a dataset containing all selected checkboxes
					////////////////////////////////////////
					if (!$value || $value === 'on' || $value === '...') unset($this->_payload->$key);
					else $evaluation[$key] = $value;
				}
				// check if any required fields have been left out, else construct evaluation data
				if ($missing = $sharedfunction->unmatchedrequired($evaluationdocument, $evaluation)) {
					$this->response([
						'response' => [
							'id' => $vendor['id'],
							'msg' => $this->_lang->GET('general.missing_form_data') . "\n". implode("\n- ", $missing),
							'type' => 'error'
						]]);
				}
				$vendor['evaluation'] = json_decode($vendor['evaluation'] ? : '', true);
				$vendor_evaluation_copy = $vendor['evaluation'];
				if (isset($vendor_evaluation_copy['_author'])) unset($vendor_evaluation_copy['_author'], $vendor_evaluation_copy['_date']);
				if ($vendor_evaluation_copy != $evaluation) {
					if ($vendor['evaluation'] = $evaluation){
						$vendor['evaluation']['_author'] = $_SESSION['user']['name'];
						$vendor['evaluation']['_date'] = $this->_currentdate->format('Y-m-d');
					}
					else $vendor['evaluation'] = null;
				}
			
				// tidy up unused properties
				foreach($vendor['info'] as $key => $value){
					if (!$value) unset($vendor['info'][$key]);
				}
				foreach($vendor['certificate'] as $key => $value){
					if (!$value) unset($vendor['certificate'][$key]);
				}
				// tidy up unused properties
				foreach($vendor['pricelist'] as $key => $value){
					if (!$value) unset($vendor['pricelist'][$key]);
				}

				// update vendor
				if (SQLQUERY::EXECUTE($this->_pdo, 'consumables_put_vendor', [
					'values' => [
						':id' => $vendor['id'],
						':hidden' => $vendor['hidden'],
						':name' => $vendor['name'],
						':info' => $vendor['info'] ? json_encode($vendor['info']) : null,
						':certificate' => $vendor['certificate'] ? json_encode($vendor['certificate']) : null,
						':pricelist' => $vendor['pricelist'] ? json_encode($vendor['pricelist']) : null,
						':evaluation' => $vendor['evaluation'] ? json_encode($vendor['evaluation']) : null
					]
				]) !== false) $this->response([
					'response' => [
						'id' => $vendor['id'],
						'msg' => $this->_lang->GET('consumables.vendor.saved', [':name' => $vendor['name']]) . $pricelistImportError,
						'type' => 'info'
					]]);
				else $this->response([
					'response' => [
						'id' => $vendor['id'],
						'name' => $this->_lang->GET('consumables.vendor.not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$datalist = [];
				$options = ['...' . (PERMISSION::permissionFor('vendors') ? $this->_lang->GET('consumables.vendor.edit_existing_vendors_new') : '') => (!$this->_requestedID) ? ['selected' => true] : []];
				$result = [];
				
				// select single vendor based on id or name
				$vendor = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor', [
					'values' => [
						':id' => $this->_requestedID
					]
				]);
				$vendor = $vendor ? $vendor[0] : null;

				// setu up vendor properties
				if (!$vendor) $vendor = [
					'id' => null,
					'name' => '',
					'hidden' => null,
					'info' => null,
					'certificate' => null,
					'pricelist' => null,
					'evaluation' => null
				];
				if ($this->_requestedID && $this->_requestedID !== 'false' && $this->_requestedID !== '...' . $this->_lang->GET('consumables.vendor.edit_existing_vendors_new') && !$vendor['id'])
					$result['response'] = ['msg' => $this->_lang->GET('consumables.vendor.error_vendor_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

				// resolve objects
				$vendor['info'] = json_decode($vendor['info'] ? : '', true) ? : [];
				$vendor['certificate'] = json_decode($vendor['certificate'] ? : '', true);
				$vendor['pricelist'] = json_decode($vendor['pricelist'] ? : '', true);
				$isactive = !$vendor['hidden'] ? ['checked' => true] : [];
				$isinactive = $vendor['hidden'] ? ['checked' => true, 'class' => 'red'] : ['class' => 'red'];

				// prepare existing vendor lists
				$vendorlist = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_vendor_datalist');
				foreach($vendorlist as $key => $row) {
					$datalist[] = $row['name'];
					$display = $row['name'];
					if ($row['hidden']) $display = UTILITY::hiddenOption($display);
					$options[$display] = ['value' => $row['name']];
					if ($row['name'] == $vendor['name']) $options[$display]['selected'] = true;
				}
				ksort($options);
				
				// gather documents
				$certificates = [];
				$documents = [];
				if ($vendor['id']) {
					$certfiles = UTILITY::listFiles(UTILITY::directory('vendor_certificates', [':name' => $vendor['immutable_fileserver']]));
					foreach($certfiles as $path){
						$certificates[pathinfo($path)['basename']] = ['target' => '_blank', 'href' => './api/api.php/file/stream/' . $path];
					}
					$docfiles = UTILITY::listFiles(UTILITY::directory('vendor_documents', [':name' => $vendor['immutable_fileserver']]));
					foreach($docfiles as $path){
						$documents[pathinfo($path)['basename']] = ['target' => '_blank', 'href' => './api/api.php/file/stream/' . substr($path, 1)];
					}
				}

				// get all vendor products
				if ($vendor['id']) $vendorproducts = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_products_by_vendor_id', [
					'values' => [
						':ids' => intval($vendor['id'])
					]
				]);

				$hidden = null;
				if ($vendor['hidden']) {
					$hiddenproperties = json_decode($vendor['hidden'], true);
					$hidden = $this->_lang->GET('consumables.vendor.edit_hidden_set', [':date' => $hiddenproperties['date'], ':name' => $hiddenproperties['name']]);
				}

				// switch between display- and edit mode 
				if (!PERMISSION::permissionFor('products')) {
					// standard user view
					// render search elements, not the same as edit mode languagewise
					$result['render'] = ['content' => [
						[
							[
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.information_vendor'),
									'onchange' => "api.purchase('get', 'vendor', this.value)"
								],
								'content' => $options
							], [
								'type' => 'search',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.vendor.edit_existing_vendors_search'),
									'onkeypress' => "if (event.key === 'Enter') {api.purchase('get', 'vendor', this.value); return false;}"
								],
								'datalist' => array_values(array_unique($datalist))
							]
						]]];
					
					// display selected vendor
					if ($vendor['id']) {
						// deactivate toggle inputs
						$isactive['disabled'] = $isinactive['disabled'] = true;

						// count available products for this vendor
						$available = 0;
						foreach($vendorproducts as $product){
							if (!$product['hidden']) $available++;
						}

						// render information on vendor
						$result['render']['content'][] = [
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $vendor['name']
								],
								'content' => implode(" \n", array_filter(array_map(Fn($key, $value) => $value ? $this->_lang->GET($vendor_info[$key]) . ': ' . $value : false, array_keys($vendor['info']), $vendor['info']), Fn($value) => boolval($value))) .
									(isset($vendor['certificate']['validity']) && $vendor['certificate']['validity'] ? " \n" . $this->_lang->GET('consumables.vendor.certificate_validity') . ': ' . $vendor['certificate']['validity'] : '') .
									" \n" . $this->_lang->GET('consumables.product.information_products_available', [':available' => $available])
							],[
								'type' => 'radio',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.vendor.availability')
								],
								'content' => [
									$this->_lang->GET('consumables.vendor.available') => $isactive,
									$this->_lang->GET('consumables.vendor.hidden') => $isinactive
								]
							]
						];

						// add mailto
						if (isset($vendor['info']['mail']) && $vendor['info']['mail'])
							array_splice($result['render']['content'][1], 1, 0,
								[[[
									'type' => 'links',
									'description' => $this->_lang->GET('consumables.vendor.mail'),
									'content' => [
										$vendor['info']['mail'] => ['href' => 'mailto:' . $vendor['info']['mail']]
									]
								]]]
							);

						// append certificates if applicable
						if ($certificates) $result['render']['content'][1][] = [
								'type' => 'links',
								'description' => $this->_lang->GET('consumables.vendor.certificate_download'),
								'content' => $certificates,
								'hint' => isset($vendor['certificate']['validity']) ? $this->_lang->GET('consumables.vendor.certificate_validity') . $vendor['certificate']['validity'] : false
							];
		
						// append documents if applicable
						if ($documents) $result['render']['content'][1][] = [
							'type' => 'links',
							'description' => $this->_lang->GET('consumables.vendor.documents_download'),
							'content' => $documents
						];
					}
				}
				else {
					// display form for adding a new or edit a current vendor
					$vendor['evaluation'] = json_decode($vendor['evaluation'] ? : '', true);

					// fill evaluation document with last vendor values
					$evaluationdocument = $sharedfunction->populatedocument($evaluationdocument, $vendor['evaluation']);
					if (isset($vendor['evaluation']['_author'])) $evaluationdocument[0][] = [
						'type' => 'textsection',
						'attributes' => [
							'name' => $this->_lang->GET('consumables.vendor.last_evaluation', [':author' => $vendor['evaluation']['_author'], ':date' => $vendor['evaluation']['_date']])
						]
					];

					// render inputs
					$result['render'] = ['content' => [
						[
							[
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.vendor.edit_existing_vendors'),
									'onchange' => "api.purchase('get', 'vendor', this.value)"
								],
								'content' => $options
							], [
								'type' => 'search',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.vendor.edit_existing_vendors_search'),
									'onkeypress' => "if (event.key === 'Enter') {api.purchase('get', 'vendor', this.value); return false;}"
								],
								'datalist' => array_values(array_unique($datalist))
							]
						], [
							[
								[
									'type' => 'text',
									'attributes' => [
										'name' => $this->_lang->GET('consumables.vendor.name'),
										'required' => true,
										'value' => $vendor['name'] ? : ''
									]
								], [
									'type' => 'textarea',
									'attributes' => [
										'name' => $this->_lang->GET('consumables.vendor.info'),
										'value' => isset($vendor['info']['infotext']) ? $vendor['info']['infotext']: '',
										'rows' => 8
									],
									'hint' => $this->_lang->GET('consumables.vendor.info_hint')
								], [
									'type' => 'email',
									'attributes' => [
										'name' => $this->_lang->GET('consumables.vendor.mail'),
										'value' => isset($vendor['info']['mail']) ? $vendor['info']['mail']: '',
									]
								], [
									'type' => 'tel',
									'attributes' => [
										'name' => $this->_lang->GET('consumables.vendor.phone'),
										'value' => isset($vendor['info']['phone']) ? $vendor['info']['phone']: '',
									]
								], [
									'type' => 'text',
									'attributes' => [
										'name' => $this->_lang->GET('consumables.vendor.address'),
										'value' => isset($vendor['info']['address']) ? $vendor['info']['address']: '',
									]
								], [
									'type' => 'text',
									'attributes' => [
										'name' => $this->_lang->GET('consumables.vendor.sales_representative'),
										'value' => isset($vendor['info']['sales_representative']) ? $vendor['info']['sales_representative']: '',
									]
								], [
									'type' => 'text',
									'attributes' => [
										'name' => $this->_lang->GET('consumables.vendor.customer_id'),
										'value' => isset($vendor['info']['customer_id']) ? $vendor['info']['customer_id']: '',
										'id' => 'vendor_customer_id'
									]
								], [
									'type' => 'textarea',
									'attributes' => [
										'name' => $this->_lang->GET('consumables.vendor.purchase_info'),
										'value' => isset($vendor['info']['purchase_info']) ? $vendor['info']['purchase_info']: '',
										'rows' => 8
									],
									'hint' => $this->_lang->GET('consumables.vendor.info_hint')
								], [
									'type' => 'radio',
									'attributes' => [
										'name' => $this->_lang->GET('consumables.vendor.availability')
									],
									'content' => [
										$this->_lang->GET('consumables.vendor.available') => $isactive,
										$this->_lang->GET('consumables.vendor.hidden') => $isinactive
									],
									'hint' => $hidden
								]
							],
							...$evaluationdocument,
						], [
							[
								[
									'type' => 'date',
									'attributes' => [
										'name' => $this->_lang->GET('consumables.vendor.certificate_validity'),
										'value' => isset($vendor['certificate']['validity']) ? $vendor['certificate']['validity'] : '',
										'id' => 'vendor_certificate_validity'
									]
								]
							], [
								[
									'type' => 'file',
									'attributes' => [
										'name' => $this->_lang->GET('consumables.vendor.certificate_update')
									]
								]
							]
						], [
							[
								'type' => 'file',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.vendor.documents_update'),
									'multiple' => true
								]
							]
						], [
							[
								[
									'type' => 'code',
									'attributes' => [
										'name' => $this->_lang->GET('consumables.vendor.pricelist_filter'),
										'value' => isset($vendor['pricelist']['filter']) ? $vendor['pricelist']['filter'] : '',
										'placeholder' => $this->filtersample
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

					// add mailto
					if (isset($vendor['info']['mail']) && $vendor['info']['mail'])
						array_splice($result['render']['content'][1][0], 3, 0,
							[[[
								'type' => 'links',
								'description' => $this->_lang->GET('consumables.vendor.mail'),
								'content' => [
									$vendor['info']['mail'] => ['href' => 'mailto:' . $vendor['info']['mail']]
								]
							]]]
						);

					// add pricelist upload form
					if ($vendor['id'] && !$vendor['hidden'])
						array_splice($result['render']['content'][4], 0, 0,
							[[[
								'type' => 'file',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.vendor.pricelist_update'),
									'accept' => '.csv'
								]
							]]]
						);

					// add certificate download
					if ($certificates) array_splice($result['render']['content'][2], 0, 0,
						[
							[
								'type' => 'links',
								'description' => $this->_lang->GET('consumables.vendor.certificate_download'),
								'content' => $certificates
							]
						]
					);

					// add document downloads
					if ($documents) $result['render']['content'][3]=[
						[
							[
								'type' => 'links',
								'description' => $this->_lang->GET('consumables.vendor.documents_download'),
								'content' => $documents
							]
						],
						$result['render']['content'][3]
					];

					// add pricelist info if provided
					if (isset($vendor['pricelist']['validity'])) array_splice($result['render']['content'][4], 0, 0,
						[[
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.vendor.pricelist_validity')
								],
								'content' => $vendor['pricelist']['validity']
							],
							[
								'type' => 'number',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.vendor.samplecheck_interval'),
									'value' => isset($vendor['pricelist']['samplecheck_interval']) ? $vendor['pricelist']['samplecheck_interval'] : CONFIG['lifespan']['mdr14_sample_interval']
								]
							],
							[
								'type' => 'number',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.vendor.samplecheck_interval_reusable'),
									'value' => isset($vendor['pricelist']['samplecheck_reusable']) ? $vendor['pricelist']['samplecheck_reusable'] : CONFIG['lifespan']['mdr14_sample_reusable']
								]
							]
						]]
					);
					
					// add pricelist export button
					if ($vendor['id'] && $vendorproducts) $result['render']['content'][4][] = [
						[
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('consumables.vendor.pricelist_export'),
								'type' => 'button',
								'onpointerup' => "api.purchase('get', 'exportpricelist', " . $vendor['id']. ")"
							],
							'hint' => $this->_lang->GET('consumables.vendor.pricelist_export_hint')
						]
					];

					// mail vendor e.g. requesting documents regarding products with special attention
					// requires text chunks though
					// using :CID and :PRD as reserved replacement keys
					if ($vendor['id']){
						$special_attention = $texttemplate = [];
						// create selection of products to request, ordered are preselected for relevance 
						foreach($vendorproducts as $product){
							if ($product['special_attention']){
								$prd = $product['article_no'] . " " . $product['article_name'];
								$special_attention[$prd] = ['value' => $prd];
								if ($product['last_order']) $special_attention[$prd]['checked'] = true;
							}
						}
						$texttemplate[] = [
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('consumables.vendor.message_vendor')
							],
							'content' => $special_attention ? $this->_lang->GET('consumables.vendor.message_vendor_hint') : ''
						];
						if ($special_attention) $texttemplate[] = [
							'type' => 'checkbox2text',
							'attributes' => [
								'name' => $this->_lang->GET('consumables.vendor.message_vendor_select_special_attention_products'),
								'id' => 'select_special_attention_products'
							],
							'content' => $special_attention,
							'hint' => $this->_lang->GET('consumables.vendor.message_vendor_select_special_attention_products_hint')
						];
						$texttemplate[] = [
							'type' => 'button',
							'attributes' => [
								'type' => 'button',
								'value' => $this->_lang->GET('menu.communication.texttemplate_texts'),
								'onpointerup' => "api.texttemplate('get', 'text', 'false', 'modal', '" . json_encode([
									':PRD' => 'select_special_attention_products',
									':CID' => 'vendor_customer_id',
									':ECR' => 'vendor_certificate_validity'
								]) . "')",
							]
						];
						$result['render']['content'][] = $texttemplate;
					}
				}
				if ($vendor['name']) $result['header'] = $vendor['name'];
				$this->response($result);
				break;
		}
	}
}
?>
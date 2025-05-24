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

// unlike other helper modules that are supposed to work anonymously this module handles module specific tasks that are similar but not identical over different modules
// import when needed, initialize with pdo and call methods with required parameters

class SHARED {
    private $_pdo = null;
	public $_lang = null;
	public $_date = [];

	public function __construct($pdo, $date){
        $this->_pdo = $pdo;
		$this->_lang = new LANG();
		$this->_date = $date;
	}

	/**
	 *   ___ _ _                         _   
	 *  |  _|_| |___ ___ ___ ___ ___ ___| |_ 
	 *  |  _| | | -_|_ -| -_| .'|  _|  _|   |
	 *  |_| |_|_|___|___|___|__,|_| |___|_|_|
	 * 
	 * return paths to files according to parameters
	 * @param array $parameter having optional search and folder as key
	 * 
	 * @return array with paths
	 */
	public function filesearch($parameter = []){
		$files = [];
		if (!isset($parameter['folder']) || !$parameter['folder'] || in_array($parameter['folder'], ['sharepoint'])) $files = array_merge($files, UTILITY::listFiles(UTILITY::directory('sharepoint') ,'asc')); // sharepoint specified or all
		if (!isset($parameter['folder']) || !$parameter['folder'] || !in_array($parameter['folder'], ['sharepoint', 'external_documents'])){ // all if not specified
			$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
			foreach ($folders as $folder) {
				$files = array_merge($files, UTILITY::listFiles($folder ,'asc'));
			}
		}
		if (!isset($parameter['folder']) || !$parameter['folder']) $files = array_merge($files, array_column(SQLQUERY::EXECUTE($this->_pdo, 'file_external_documents_get_active'), 'path')); // all
		if (isset($parameter['folder']) && in_array($parameter['folder'], ['external_documents'])) $files = UTILITY::listFiles(UTILITY::directory('external_documents') ,'asc'); // external_document specified
		
		// return based on similarity if search is provided
		// also converting the path
		$matches = [];

		$parameter['search'] = isset($parameter['search']) ? trim(urldecode($parameter['search'])) : null;

		foreach ($files as $file){
			similar_text($parameter['search'], pathinfo($file)['basename'], $percent);
			if (!$parameter['search'] || $percent >= CONFIG['likeliness']['file_search_similarity'] || fnmatch($parameter['search'], pathinfo($file)['basename'], FNM_CASEFOLD)) $matches[] = './api/api.php/file/stream/' . substr($file, 1);
		}
		return $matches;
	}

	/**
	 *     _                           _                       _   
	 *   _| |___ ___ _ _ _____ ___ ___| |_ ___ ___ ___ ___ ___| |_ 
	 *  | . | . |  _| | |     | -_|   |  _|_ -| -_| .'|  _|  _|   |
	 *  |___|___|___|___|_|_|_|___|_|_|_| |___|___|__,|_| |___|_|_|
	 * 
	 * returns documents based on search
	 * @param array $parameter with search as key
	 * 
	 * @return array of document names
	 */
	public function documentsearch($parameter = []){
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$hidden = $matches = [];

		$parameter['search'] = isset($parameter['search']) ? trim(urldecode($parameter['search'])) : null;

		/**
		 * looks for names, descriptions, hints and contents similar to search string
		 * @param array $element component
		 * @param string $search keyword
		 */
		function findInComponent($element, $search){
			$found = false;
			foreach($element as $subs){
				if (!isset($subs['type'])){
					if ($found = findInComponent($subs, $search)) return true;
				}
				else {
					$comparisons = [];
					foreach (['description', 'content', 'hint'] as $property){
						if (isset($subs[$property])){
							if (is_array($subs[$property])){ // links, checkboxes, etc
								foreach(array_keys($subs[$property]) as $key) $comparisons[] = $key;
							}
							else $comparisons[] = $subs[$property];
						}
					}
					if (isset($subs['attributes'])){
						foreach (['name', 'value'] as $property){
							if (isset($subs['attributes'][$property])) $comparisons[] = $subs['attributes'][$property];
						}
					}
					foreach($comparisons as $term) {
						similar_text($search, $term, $percent);
						// suppress errors on long terms for fnmatch limit
						if (stristr($term, $search) || $percent >= CONFIG['likeliness']['file_search_similarity'] || @fnmatch($search, $term, FNM_CASEFOLD)) return true;
					}
				}
			}
			return $found;
		};

		foreach($fd as $row) {
			if ($row['hidden'] || !PERMISSION::permissionIn($row['restricted_access']) || !PERMISSION::fullyapproved('documentapproval', $row['approval'])) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden)) {
				// set up search terms with name and alias
				$terms = [$row['name']];
				foreach(preg_split('/[^\w\d]/', $row['alias']) as $alias) array_push($terms, $alias);
				// match similarity
				foreach ($terms as $term){
					// limit search to similarity
					if ($parameter['search']){
						similar_text($parameter['search'], $term, $percent);
						if ($percent < CONFIG['likeliness']['file_search_similarity'] && !fnmatch($parameter['search'], $term, FNM_CASEFOLD)) continue;
					}
					$matches[] = $row;
				}
				if (in_array($row, $matches)) continue;

				// if not found within name, search regulatory contexts
				foreach(explode(',', $row['regulatory_context']) as $context) {
					if (stristr($this->_lang->GET('regulatory.' . $context), $parameter['search']) !== false) {
						$matches[] = $row;
						break;	
					}
				}
				if (in_array($row, $matches)) continue;

				// if not found already search within components
				$document = $this->recentdocument('document_document_get_by_name', [
					'values' => [
						':name' => $row['name']
					]]);
				if ($parameter['search'] && findInComponent($document['content'], $parameter['search'])) {
					$matches[] = $row;
				}
			}
		}
		return $matches;
	}

	/**
	 *                         _                     _   
	 *   ___ ___ ___ ___ ___ _| |___ ___ ___ ___ ___| |_ 
	 *  |  _| -_|  _| . |  _| . |_ -| -_| .'|  _|  _|   |
	 *  |_| |___|___|___|_| |___|___|___|__,|_| |___|_|_|
	 * 
	 * returns records based on matching identifier with search
	 * @param array $parameter with search as key
	 * 
	 * @return array of records
	 */
	public function recordsearch($parameter = []){
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');

		$parameter['search'] = isset($parameter['search']) ? trim(urldecode($parameter['search'])) : null;

		$contexts = [];

		foreach($data as $row){
			// limit search to similarity
			if ($parameter['search']){
				similar_text($parameter['search'], $row['identifier'], $percent);
				if ($percent < CONFIG['likeliness']['records_search_similarity'] // considering typos
					&& !fnmatch($parameter['search'], $row['identifier'], FNM_CASEFOLD) // considering wildcards
					&& !stristr($row['content'], $parameter['search']) // literal search e.g. for serial numbers
				) continue;
			}

			// continue if record has been closed unless explicitly searched for
			if (!$parameter['search'] && (($row['record_type'] !== 'complaint' && $row['closed']) ||
				($row['record_type'] === 'complaint' && PERMISSION::fullyapproved('complaintclosing', $row['closed'])))
			) continue;

			$row['units'] = $row['units'] ? explode(',', $row['units']) : [];
			// continue if record does not match selected (or blank) unit
			if ($row['units']){
				if (isset($parameter['unit']) && $parameter['unit'] && !in_array($parameter['unit'], $row['units'])) continue;
			}
			elseif (isset($parameter['unit']) && $parameter['unit'] !== '_unassigned') continue;

			foreach($this->_lang->_USER['documentcontext'] as $key => $subkeys){
				if (in_array($row['context'], array_keys($subkeys))) $row['context'] = $key . '.' . $row['context'];
			}
			if (isset($contexts[$row['context']])) {
				// limit results per context to max_records
				if (count($contexts[$row['context']]) > CONFIG['limits']['max_records']) continue;
			}
			else $contexts[$row['context']] = [];

			$contexts[$row['context']][] = [
				'identifier' => $row['identifier'],
				'last_touch' => substr($row['last_touch'], 0, -3),
				'last_document' => $row['last_document'] ? : $this->_lang->GET('record.altering_pseudodocument_name'),
				'case_state' => json_decode($row['case_state'] ? : '', true) ? : [],
				'complaint' => $row['record_type'] === 'complaint',
				'closed' => $row['closed'] && ($row['record_type'] !== 'complaint' || ($row['record_type'] === 'complaint' && PERMISSION::fullyapproved('complaintclosing', $row['closed']))),
				'units' => $row['units']
			];
		}
		return $contexts;
	}
	
	/**
	 *       _     _                       _   
	 *   ___|_|___| |_ ___ ___ ___ ___ ___| |_ 
	 *  |  _| |_ -| '_|_ -| -_| .'|  _|  _|   |
	 *  |_| |_|___|_,_|___|___|__,|_| |___|_|_|
	 *  
	 * returns risk tiles based on search
	 * @param array $parameter named array, currently with search string
	 * 
	 * @return array render content
	 */
	public function risksearch($parameter = []){
		$risk_datalist = SQLQUERY::EXECUTE($this->_pdo, 'risk_datalist');
		$productsPerSlide = 0;

		$parameter['search'] = isset($parameter['search']) ? trim(urldecode($parameter['search'])) : null;

		$slides = [
			[
				[
					'type' => 'search',
					'attributes' => [
						'name' => $this->_lang->GET('risk.search'),
						'onkeydown' => "if (event.key === 'Enter') {api.risk('get', 'search', encodeURIComponent(this.value)); return false;}",
						'value' => $parameter['search'] ? : ''
					]
				]
			]
		];
		foreach($risk_datalist as $row){
			if (!PERMISSION::permissionFor('riskmanagement') && $row['hidden']) continue;
			$row['risk'] = implode(' ', array_values(array_map(fn($r)=> $r && isset($this->_lang->_USER['risks'][$r]) ? $this->_lang->_USER['risks'][$r] : null, explode(',', $row['risk'] ? : ''))));
			if ($parameter['search'] && 
				(
					stristr($row['cause'] . ' ' . $row['effect'] . ' ' . $row['measure'] . ' ' . $row['risk_benefit'] . ' ' . $row['measure_remainder'] . ' ' . $row['risk'], $parameter['search']) ||
					// suppress errors on long terms for fnmatch limit
					@fnmatch($parameter['search'], $row['cause'] . ' ' . $row['effect'] . ' ' . $row['measure'] . ' ' . $row['risk_benefit'] . ' ' . $row['measure_remainder'] . ' ' . $row['risk'], FNM_CASEFOLD)
				)){

				if (empty($productsPerSlide++ % CONFIG['splitresults']['products_per_slide'])){
					$slides[] = [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->GET('risk.search_result', [':search' => $parameter['search']])
							],
						]
					];
				}
				$slide = count($slides) - 1;
				$tile = max(1, count($slides[$slide]) - 1);	
				switch ($row['type']){
					case 'characteristic': // implement further cases if suitable, according to languagefile
						$content = $row['process'] . ': ' . $row['measure'] . ($row['cause'] ? ': ' . $row['cause'] : '');
						break;
					default: // risk
						$content = $row['process'] . ': ' . ($row['cause'] ? : '') . ($row['cause'] && $row['effect'] ? ': ': '') . ($row['effect'] ? : '');
						break;
				}
				if ($row['hidden']) $content = UTILITY::hiddenOption($content);
				$slides[$slide][$tile][] = [
					'type' => 'tile',
					'attributes' => [
						'onclick' => "api.risk('get', 'risk', " . $row['id'] . ")",
						'onkeydown' => "if (event.key==='Enter') api.risk('get', 'risk', " . $row['id'] . ")",
						'role' => 'link',
						'tabindex' => '0',
						'title' => $this->_lang->GET('risk.tile_title', [':type' => $this->_lang->_USER['risk']['type'][$row['type']]])
					],
					'content' => [
						[
							'type' => 'textsection',
							'attributes' => [
								'name' => $this->_lang->_USER['risk']['type'][$row['type']],
								'class' => $row['relevance'] ? 'green' : 'red'
							],
							'content' => $content
						]
					]
				];
			}
		}
		if ($parameter['search'] && !isset($slides[1])) return false;
		return [array_values($slides)];
	}

    /**
	 *                 _         _                       _
	 *   ___ ___ ___ _| |_ _ ___| |_ ___ ___ ___ ___ ___| |_
	 *  | . |  _| . | . | | |  _|  _|_ -| -_| .'|  _|  _|   |
	 *  |  _|_| |___|___|___|___|_| |___|___|__,|_| |___|_|_|
	 *  |_|
	 * returns sliders with search form (where applicable) and product tiles based on search
	 * @param string $usecase
	 * @param array $parameter named array, currently with search string and _-separated vendor ids
	 * 
	 * @return array render content
	 */
	public function productsearch($usecase = '', $parameter = []){
		$slides = [];
		// order of output to be taken into account in utility.js _client.order.addProduct() method and order.php->order() method as well!
		switch ($_SERVER['REQUEST_METHOD']){
			case 'GET':
				$vendors = SQLQUERY::EXECUTE($this->_pdo, SQLQUERY::PREPARE('consumables_get_vendor_datalist'));

				if (!isset($parameter['vendors']) || $parameter['vendors'] === 'null'){
					$parameter['vendors'] = implode('_', array_values(array_column($vendors, 'id')));
				}

				$parameter['search'] = isset($parameter['search']) ? trim(urldecode($parameter['search'])) : null;

				$search = [];

				// this is appears to be the most performant way, iterating over the whole database with fnsearch and similarity is horribly slow 2025-05-16
				if ($parameter['search']) $search = SQLQUERY::EXECUTE($this->_pdo, in_array($usecase, ['product']) ? SQLQUERY::PREPARE('consumables_get_product_search') : SQLQUERY::PREPARE('order_get_product_search'), [
					'values' => [
						':search' => $parameter['search']
					],
					'replacements' => [
						':vendors' => implode(",", array_map(fn($el) => intval($el), explode('_', $parameter['vendors']))),
					]
				]);

				$productsPerSlide = 0;

				// insert request specific search to first slide
				switch($usecase){
					case 'productselection': // document.php, record.php, assemble.js don't need prefacing inputs for productselection widget
						break;
					case 'product': // consumables.php can make good use of this method!
						// prepare existing vendor lists
						$vendorselection = [];

						$vendorselection[$this->_lang->GET('consumables.product.search_all_vendors')] = ['value' => 'null'];

						foreach($vendors as $key => $row) {
							$datalist[] = $row['name'];
							$display = $row['name'];
							if ($row['hidden']) $display = UTILITY::hiddenOption($display);
							$vendorselection[$display] = ['value' => $row['id']];
							if ($parameter['vendors'] === strval($row['id'])) $vendorselection[$display]['selected'] = true;
						}
						ksort($vendorselection);
						$slides[] = [
							[
								'type' => 'scanner',
								'destination' => 'productsearch'
							], [
								'type' => 'select',
								'content' => $vendorselection,
								'attributes' => [
									'id' => 'productsearchvendor',
									'name' => $this->_lang->GET('consumables.product.filter_vendors')
									]
							], [
								'type' => 'search',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.search'),
									'onkeydown' => "if (event.key === 'Enter') {api.purchase('get', 'productsearch', document.getElementById('productsearchvendor').value, encodeURIComponent(this.value), '" . $usecase . "'); return false;}",
									'id' => 'productsearch',
									'value' => $parameter['search'] ? : ''
								]
							]
						];
						if (PERMISSION::permissionFor('products') || PERMISSION::permissionFor('productslimited')){
							array_splice($slides[0], 0, 0, [
								[
								'type' => 'button',
								'attributes' => [
									'value' => $this->_lang->GET('consumables.product.add_new'),
									'type' => 'button',
									'onclick' => "api.purchase('get', 'product')",
								]
							]
							]);
						}
						break;
					default: // order.php can make good use of this method!
						$datalist = [];
						$datalist_unit = [];
		
						// prepare existing vendor lists
						$vendorselection[$this->_lang->GET('consumables.product.search_all_vendors')] = ['value' => 'null'];
						foreach($vendors as $key => $row) {
							if ($row['hidden']) continue;
							$datalist[] = $row['name'];
							$vendorselection[$row['name']] = ['value' => $row['id']];
							if ($parameter['vendors'] === strval($row['id'])) $vendorselection[$row['name']]['selected'] = true;
						}
						ksort($vendorselection);
		
						// prepare existing sales unit lists
						$product_units = SQLQUERY::EXECUTE($this->_pdo, 'consumables_get_product_units');
						foreach($product_units as $key => $row) {
							$datalist_unit[] = $row['article_unit'];
						}

						$slides[] = [
							[
								'type' => 'scanner',
								'destination' => 'productsearch'
							], [
								'type' => 'select',
								'content' => $vendorselection,
								'attributes' => [
									'id' => 'productsearchvendor',
									'name' => $this->_lang->GET('consumables.product.vendor_select')
								]
							], [
								'type' => 'search',
								'attributes' => [
									'name' => $this->_lang->GET('consumables.product.search'),
									'onkeydown' => "if (event.key === 'Enter') {api.purchase('get', 'productsearch', document.getElementById('productsearchvendor').value, encodeURIComponent(this.value), 'order'); return false;}",
									'id' => 'productsearch',
									'value' => $parameter['search'] ? : ''
								]
							], [
								'type' => 'button',
								'attributes' => [
									'value' => $this->_lang->GET('order.add_manually'),
									'type' => 'button',
									'onclick' => "new _client.Dialog({type: 'input', header: '". $this->_lang->GET('order.add_manually') ."', render: JSON.parse('".
									UTILITY::json_encode([
										[
											[
												'type' => 'number',
												'attributes' => [
													'name' => $this->_lang->GET('order.quantity_label'),
												]
											], [
												'type' => 'text',
												'attributes' => [
													'name' => $this->_lang->GET('order.unit_label'),
												],
												'datalist' => array_values(array_unique($datalist_unit))
											], [
												'type' => 'text',
												'attributes' => [
													'name' => $this->_lang->GET('order.ordernumber_label')
												]
											], [
												'type' => 'text',
												'attributes' => [
													'name' => $this->_lang->GET('order.productname_label')
												]
											], [
												'type' => 'text',
												'attributes' => [
													'name' => $this->_lang->GET('order.vendor_label'),
												],
												'datalist' => array_values(array_unique($datalist))
											]
										]
									])
									."'), options:{".
										"'".$this->_lang->GET('order.add_manually_cancel')."': {value: false},".
										"'".$this->_lang->GET('order.add_manually_confirm')."': {value: true, class: 'reducedCTA'},".
									"}}).then(response => {if (Object.keys(response).length) {".
										"_client.order.addProduct(response[api._lang.GET('order.quantity_label')] || '', response[api._lang.GET('order.unit_label')] || '', response[api._lang.GET('order.ordernumber_label')] || '', response[api._lang.GET('order.productname_label')] || '', response[api._lang.GET('order.barcode_label')] || '', response[api._lang.GET('order.vendor_label')] || '');".
										"api.preventDataloss.monitor = true;}".
										"document.getElementById('modal').replaceChildren()})", // clear modal to avoid messing up input names
								]
							]
						];
				}

				foreach($search as $key => $row) {
					foreach($row as $key => $value){
						$row[$key] = $row[$key] ? str_replace("\n", ' ', $row[$key]) : '';
					}
					if (empty($productsPerSlide++ % CONFIG['splitresults']['products_per_slide'])){
						$slides[] = [
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => $this->_lang->GET('order.add_product_search_matches', [':number' => count($search)])
								],
							]
						];
					}
					$slide = count($slides) - 1;
					$tiles = max(1, count($slides[$slide]) - 1);
					switch ($usecase){
						case 'product': // consumables.php can make good use of this method!
								$slides[$slide][$tiles][] = [
								'type' => 'tile',
								'attributes' => [
									'onclick' => "api.purchase('get', 'product', " . $row['id'] . ")",
									'onkeydown' => "if (event.key==='Enter') api.purchase('get', 'product', " . $row['id'] . ")",
									'role' => 'link',
									'tabindex' => '0',
									'title' => $this->_lang->GET('consumables.product.tile_title', [':product' => $row['article_name'], ':vendor' => $row['vendor_name']])
								],
								'content' => [
									[
										'type' => 'textsection',
										'content' => $row['vendor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit']
											. ($row['erp_id'] ? "\n" . $this->_lang->GET('consumables.product.erp_id') . ": " . $row['erp_id'] : '')
									]
								]
							];
							break;
						case 'productselection': // document.php, record.php, assemble.js can make good use of this method!
							if (!isset($slides[$slide][$tiles][1])) $slides[$slide][$tiles][] = [
								'type' => 'radio',
								'attributes' => [
									'name' => $this->_lang->GET('order.add_product_search_matches', [':number' => count($search)])
								],
								'content' => []
							];
							$slides[$slide][$tiles][0]['content'][$row['vendor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit']] = [
									'onchange' => "if (this.checked) document.getElementById('_selectedproduct').value = '" . $row['vendor_name'] . " " . $row['article_no'] . " " . $row['article_name'] . " " . $row['article_unit'] ."';",
								];
							break;
						default: // order.php can make good use of this method!
							$incorporationState = '';
							if (!$row['incorporated']) $incorporationState = $this->_lang->GET('order.incorporation.neccessary');
							else {
								$row['incorporated'] = json_decode($row['incorporated'] ? : '', true);
								if (isset($row['incorporated']['_denied'])) $incorporationState = $this->_lang->GET('order.incorporation.denied');
								elseif (!PERMISSION::fullyapproved('incorporation', $row['incorporated'])) $incorporationState = $this->_lang->GET('order.incorporation.pending');
							}
							$slides[$slide][$tiles][] = [
								'type' => 'tile',
								'attributes' => [
									'onclick' => "_client.order.addProduct('" . $row['article_unit'] . "', '" . preg_replace('/\'/', "\'", $row['article_no']) . "', '" . preg_replace('/\'/', "\'", $row['article_name']) . "', '" . $row['article_ean'] . "', '" . $row['vendor_name'] . "'); return false;",
									'onkeydown' => "if (event.key==='Enter') _client.order.addProduct('" . $row['article_unit'] . "', '" . preg_replace('/\'/', "\'", $row['article_no']) . "', '" . preg_replace('/\'/', "\'", $row['article_name']) . "', '" . $row['article_ean'] . "', '" . $row['vendor_name'] . "'); return false;",
									'role' => 'link',
									'tabindex' => '0',
									'title' => $this->_lang->GET('order.tile_title', [':product' => $row['article_name'], ':vendor' => $row['vendor_name']])
								],
								'content' => [
									[
										'type' => 'textsection',
										'attributes' => [
											'name' => ($row['stock_item'] ? $this->_lang->GET('consumables.product.stock_item') : '') . ($incorporationState && $row['stock_item'] ? ' - ' : '') . $incorporationState,
											'data-type' => 'cart'
										],
										'content' => $row['vendor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit'] . ' ' . $row['article_ean']
											. ($row['erp_id'] ? "\n" . $this->_lang->GET('consumables.product.erp_id') . ": " . $row['erp_id'] : '')
									]
								]
							];
					}
				}
				if ($parameter['search'] && !isset($slides[$usecase === 'productselection' ? 0 : 1])) return false;
				break;
			}
		return [array_values($slides)]; // return a proper nested article
	}

	/**
	 *                   _     _         _                           _   
	 *   ___ ___ ___ _ _| |___| |_ ___ _| |___ ___ _ _ _____ ___ ___| |_ 
	 *  | . | . | . | | | | .'|  _| -_| . | . |  _| | |     | -_|   |  _|
	 *  |  _|___|  _|___|_|__,|_| |___|___|___|___|___|_|_|_|___|_|_|_|  
	 *  |_|     |_| 
	 * populate a document with passed payload
	 * @param array $element document structure
	 * @param array $values payload
	 * 
	 * @return array prefilled document structure
	 */
	public function populatedocument($element, $values){
		$content = [];
		foreach($element as $subs){
			if (!isset($subs['type'])){
				$content[] = self::populatedocument($subs, $values);
			}
			else {
				if (!isset($values[$subs['attributes']['name']])) $underscored_name = preg_replace('/[\s\.]/', '_', $subs['attributes']['name']);
				else $underscored_name = $subs['attributes']['name'];
				if (isset($subs['content']) && isset($subs['attributes']['name']) && isset($values[$underscored_name])){
					$settings = explode(' | ', $values[$underscored_name]);
					foreach($subs['content'] as $key => $attributes) if (in_array($key, $settings)) {
						if ($subs['type'] === 'select') $subs['content'][$key]['selected'] = true;
						else $subs['content'][$key]['checked'] = true;
					}
				}
				elseif (isset($values[$underscored_name])){
					$subs['attributes']['value'] = $values[$underscored_name];
				}
				$content[] = $subs;
			}
		}
		return $content;
	}

	/**
	 *                     _       _         _                 _           _ 
	 *   _ _ ___ _____ ___| |_ ___| |_ ___ _| |___ ___ ___ _ _|_|___ ___ _| |
	 *  | | |   |     | .'|  _|  _|   | -_| . |  _| -_| . | | | |  _| -_| . |
	 *  |___|_|_|_|_|_|__,|_| |___|_|_|___|___|_| |___|_  |___|_|_| |___|___|
	 *                                                  |_|   
	 * check whether all required fields of a document have been considered
	 * @param array $element document structure
	 * @param array $values payload
	 * 
	 * @return array of unmatched document names
	 */
	public function unmatchedrequired($element, $values){
		$content = [];
		foreach($element as $subs){
			if (!isset($subs['type'])){
				array_push($content, ...self::unmatchedrequired($subs, $values));
			}
			else {
				$underscored_name = preg_replace('/[\s\.]/', '_', $subs['attributes']['name']);
				if (isset($subs['attributes']['name']) && isset($subs['attributes']['required']) && !(isset($values[$underscored_name]) || isset($values[$subs['attributes']['name']]))){
					$content[] = $subs['attributes']['name'];
				}
			}
		}
		return $content;
	}

	/**
	 *                       _     _                           _   
	 *   ___ ___ ___ ___ ___| |_ _| |___ ___ _ _ _____ ___ ___| |_ 
	 *  |  _| -_|  _| -_|   |  _| . | . |  _| | |     | -_|   |  _|
	 *  |_| |___|___|___|_|_|_| |___|___|___|___|_|_|_|___|_|_|_|  
	 *                                             
	 * retrieves most recent approved document or component
	 * and returns the content as body response e.g. for modal
	 * @param string $query _sqlinterface query
	 * @param array $parameters _ sqlinterface parameters
	 * @param string $requestedTimestamp Y-m-d H:i:s as optional past delimiter
	 * 
	 * @return array document components or document names within bundles
	 */
	public function recentdocument($query = '', $parameters = [], $requestedTimestamp = null){
		$requestedTimestamp = $requestedTimestamp ? : $this->_date['servertime']->format('Y-m-d H:i:59');

		$result = [];
		$contentBody = [];
		$contents = SQLQUERY::EXECUTE($this->_pdo, $query, $parameters);
		if ($contents){
			foreach($contents as $content){
				if (PERMISSION::fullyapproved('documentapproval', $content['approval'])) break;
			}
			$content['hidden'] = json_decode($content['hidden'] ? : '', true); 
			if (!PERMISSION::fullyapproved('documentapproval', $content['approval']) // failsafe if none are approved
				|| ($content['hidden'] && (!$requestedTimestamp || $content['hidden'] <= $requestedTimestamp)) // if hidden and content younger than hidden date
				|| !PERMISSION::permissionIn($content['restricted_access']) // user lacks permission to restricted
				|| $content['date'] > $requestedTimestamp) return []; // document date is younger than requested
			$result = $content;
			if ($content['context'] === 'component') {
				$content['content'] = json_decode($content['content'], true);
				$contentBody = $content['content']['content'];
			}
			elseif ($content['context'] === 'bundle') {
				$contentBody = explode(',', $content['content']);
			}
			else {
				foreach(explode(',', $content['content']) as $usedcomponent) {
					// get latest approved by name
					$components = SQLQUERY::EXECUTE($this->_pdo, 'document_component_get_by_name', [
						'values' => [
							':name' => $usedcomponent
						]
					]);
					foreach ($components as $component){
						$component['hidden'] = json_decode($component['hidden'] ? : '', true); 
						if ((!$component['hidden'] || $component['hidden']['date'] > $requestedTimestamp ) && PERMISSION::fullyapproved('documentapproval', $component['approval'])) break;
						else $component = [];
					}
					if ($component){
						$component['content'] = json_decode($component['content'], true);
						array_push($contentBody, ...$component['content']['content']);
					}
				}
			}
		$result['content'] = $contentBody;
		}
		return $result;
	}
}
?>
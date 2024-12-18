<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2024 error on line 1 (dev@erroronline.one)
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
	private $_currentdate = null;

	public function __construct($pdo = null){
        $this->_pdo = $pdo;
		$this->_currentdate = new DateTime('now', new DateTimeZone(CONFIG['application']['timezone']));
	}

	/**
	 *   ___ _ _                         _   
	 *  |  _|_| |___ ___ ___ ___ ___ ___| |_ 
	 *  |  _| | | -_|_ -| -_| .'|  _|  _|   |
	 *  |_| |_|_|___|___|___|__,|_| |___|_|_|
	 * 
	 *
	 */
	public function filesearch($parameter = []){
		$files = [];
		if (isset($parameter['folder']) && in_array($parameter['folder'], ['sharepoint', 'all'])) $files = array_merge($files, UTILITY::listFiles(UTILITY::directory('sharepoint') ,'asc'));
		if (!isset($parameter['folder']) || !$parameter['folder'] || in_array($parameter['folder'], ['all']) || !in_array($parameter['folder'], ['sharepoint','external_documents'])){
			$folders = UTILITY::listDirectories(UTILITY::directory('files_documents') ,'asc');
			foreach ($folders as $folder) {
				$files = array_merge($files, UTILITY::listFiles($folder ,'asc'));
			}
		}
		if (!isset($parameter['folder']) || !$parameter['folder'] || in_array($parameter['folder'], ['all'])) $files = array_merge($files, array_column(SQLQUERY::EXECUTE($this->_pdo, 'file_external_documents_get_active'), 'path'));
		if (isset($parameter['folder']) && in_array($parameter['folder'], ['external'])) $files = array_merge($files, UTILITY::listFiles(UTILITY::directory('external_documents') ,'asc'));
		
		if (!$parameter['search']) return $files;
		
		$matches = [];
		foreach ($files as $file){
			similar_text($parameter['search'], pathinfo($file)['filename'], $percent);
			if ($percent >= CONFIG['likeliness']['file_search_similarity']) $matches[] = $file;
		}
		return $matches;
	}

	/**
	 *     _                           _                       _   
	 *   _| |___ ___ _ _ _____ ___ ___| |_ ___ ___ ___ ___ ___| |_ 
	 *  | . | . |  _| | |     | -_|   |  _|_ -| -_| .'|  _|  _|   |
	 *  |___|___|___|___|_|_|_|___|_|_|_| |___|___|__,|_| |___|_|_|
	 * 
	 * 
	 */
	public function documentsearch($parameter = []){
		$fd = SQLQUERY::EXECUTE($this->_pdo, 'document_document_datalist');
		$hidden = $matches = [];

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
						if (stristr($term, $search) || $percent >= CONFIG['likeliness']['file_search_similarity']) return true;
					}
				}
			}
			return $found;
		};

		foreach($fd as $row) {
			if ($row['hidden'] || !PERMISSION::permissionIn($row['restricted_access'])) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden)) {
				$terms = [$row['name']];
				foreach(preg_split('/[^\w\d]/', $row['alias']) as $alias) array_push($terms, $alias);
				foreach ($terms as $term){
					similar_text($parameter['search'], $term, $percent);
					if (($percent >= CONFIG['likeliness']['file_search_similarity'])) {
						$matches[] = $row;
						break;
					}
				}
				if (in_array($row, $matches)) continue;

				foreach(explode(',', $row['regulatory_context']) as $context) {
					if (stristr(LANG::GET('regulatory.' . $context), $parameter['search']) !== false) {
						$matches[] = $row;
						break;	
					}
				}
				if (in_array($row, $matches)) continue;

				$document = $this->recentdocument('document_document_get_by_name', [
					'values' => [
						':name' => $row['name']
					]]);
				if (findInComponent($document['content'], $parameter['search'])) {
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
	 * 
	 */
	public function recordsearch($parameter = []){
		$data = SQLQUERY::EXECUTE($this->_pdo, 'records_get_all');

		$contexts = [];

		foreach($data as $row){
			// limit search to similarity
			if ($parameter['search']){
				similar_text($parameter['search'], $row['identifier'], $percent);
				if ($percent < CONFIG['likeliness']['records_search_similarity']) continue;
			}

			// continue if record has been closed unless explicitly searched for
			if (!$parameter['search'] && (($row['record_type'] !== 'complaint' && $row['closed']) ||
				($row['record_type'] === 'complaint' && PERMISSION::fullyapproved('complaintclosing', $row['closed'])))
			) continue;

			$row['units'] = $row['units'] ? explode(',', $row['units']) : [];
			// continue if record does not match selected (or blank) unit
			if ($row['units']){
				if (((!isset($parameter['unit']) || !$parameter['unit']) && !array_intersect($row['units'], $_SESSION['user']['units'])) ||
					(isset($parameter['unit']) && $parameter['unit'] && !in_array($parameter['unit'], $row['units']))
				) continue;
			}
			elseif ($parameter['unit'] !== '_unassigned') continue;

			foreach(LANGUAGEFILE['documentcontext'] as $key => $subkeys){
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
				'last_document' => $row['last_document'] ? : LANG::GET('record.record_altering_pseudodocument_name'),
				'case_state' => json_decode($row['case_state'] ? : '', true) ? : [],
				'complaint' => $row['record_type'] === 'complaint',
				'closed' => $row['closed'] && ($row['record_type'] !== 'complaint' || ($row['record_type'] === 'complaint' && PERMISSION::fullyapproved('complaintclosing', $row['closed']))),
				'units' => $row['units']
			];
			return $contexts;
		}
	}
	
    /**
	 *                 _         _                       _
	 *   ___ ___ ___ _| |_ _ ___| |_ ___ ___ ___ ___ ___| |_
	 *  | . |  _| . | . | | |  _|  _|_ -| -_| .'|  _|  _|   |
	 *  |  _|_| |___|___|___|___|_| |___|___|__,|_| |___|_|_|
	 *  |_|
	 * 
	 * @param string $usecase
	 * @param array $parameter named array, currently with search string and _-separated vendor ids
	 */
	public function productsearch($usecase = '', $parameter = []){
		// order of output to be taken into account in utility.js _client.order.addProduct() method and order.php->order() method as well!
		switch ($_SERVER['REQUEST_METHOD']){
			case 'GET':
				$content;
				if (!isset($parameter['search'])) {
					$content = [];
					break;
				}

				if ($usecase === 'productselection'){
					// prepared by assemble.js vendor ids are passed as 'null'-string
					$vendors = SQLQUERY::EXECUTE($this->_pdo, SQLQUERY::PREPARE('consumables_get_vendor_datalist'));
					$parameter['vendors'] = implode('_', array_values(array_column($vendors, 'id')));
				}
				$search = SQLQUERY::EXECUTE($this->_pdo, $usecase === 'editconsumables' ? SQLQUERY::PREPARE('consumables_get_product_search') : SQLQUERY::PREPARE('order_get_product_search'), [
					'values' => [
						':search' => $parameter['search']
					],
					'replacements' => [
						':vendors' => implode(",", array_map(fn($el) => intval($el), explode('_', $parameter['vendors']))),
					]
				]);

				$productsPerSlide = 0;
				$matches = [[]];

				foreach($search as $key => $row) {
					foreach($row as $key => $value){
						$row[$key] = $row[$key] ? str_replace("\n", ' ', $row[$key]) : '';
					}
					$article = intval(count($matches) - 1);
					if (empty($productsPerSlide++ % CONFIG['splitresults']['products_per_slide'])){
						$matches[$article][] = [
							[
								'type' => 'textsection',
								'attributes' => [
									'name' => LANG::GET('order.add_product_search_matches', [':number' => count($search)])
								],
							]
						];
					}
					$slide = intval(count($matches[$article]) - 1);
					switch ($usecase){
						case 'editconsumables': // consumables.php can make good use of this method!
							$matches[$article][$slide][] = [
								'type' => 'tile',
								'attributes' => [
									'onpointerup' => "api.purchase('get', 'product', " . $row['id'] . ")",
								],
								'content' => [
									[
										'type' => 'textsection',
										'content' => $row['vendor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit'] . ' ' . $row['article_ean']
									]
								]
							];
							break;
						case 'productinformation': // consumables.php can make good use of this method!
							$matches[$article][$slide][] = [
								'type' => 'tile',
								'attributes' => [
									'onpointerup' => "api.purchase('get', 'product', " . $row['id'] . ")",
								],
								'content' => [
									[
										'type' => 'textsection',
										'content' => $row['vendor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit']
									]
								]
							];
							break;
						case 'productselection': // document.php, record.php, assemble.js can make good use of this method!
							if (!isset($matches[$article][$slide][1])) $matches[$article][$slide][] = [
								'type' => 'radio',
								'attributes' => [
									'name' => LANG::GET('order.add_product_search_matches', [':number' => count($search)])
								],
								'content' => []
							];
							$matches[$article][$slide][1]['content'][$row['vendor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit']] = [
									'onchange' => "if (this.checked) document.getElementById('_selectedproduct').value = '" . $row['vendor_name'] . " " . $row['article_no'] . " " . $row['article_name'] . " " . $row['article_unit'] ."';",
								];
							break;
						default: // order.php can make good use of this method!
							$incorporationState = '';
							if ($row['incorporated'] === '') $incorporationState = LANG::GET('order.incorporation_neccessary');
							else {
								$row['incorporated'] = json_decode($row['incorporated'], true);
								if (isset($row['incorporated']['_denied'])) $incorporationState = LANG::GET('order.incorporation_denied');
								elseif (!PERMISSION::fullyapproved('incorporation', $row['incorporated'])) $incorporationState = LANG::GET('order.incorporation_pending');
							}
							$matches[$article][$slide][] = [
								'type' => 'tile',
								'attributes' => [
									'onpointerup' => "_client.order.addProduct('" . $row['article_unit'] . "', '" . preg_replace('/\'/', "\'", $row['article_no']) . "', '" . preg_replace('/\'/', "\'", $row['article_name']) . "', '" . $row['article_ean'] . "', '" . $row['vendor_name'] . "'); return false;",
								],
								'content' => [
									['type' => 'textsection',
									'attributes' => [
										'name' => $incorporationState
									],
									'content' => $row['vendor_name'] . ' ' . $row['article_no'] . ' ' . $row['article_name'] . ' ' . $row['article_unit'] . ' ' . $row['article_ean']]
								]
							];
					}
				}
				if (!$matches[0]) $matches[0][] = [
					[
						'type' => 'textsection',
						'attributes' => [
							'name' => LANG::GET('order.add_product_search_matches', [':number' => count($search)])
						],
					]
				];
				$content = $matches;
				break;
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
		$requestedTimestamp = $requestedTimestamp ? : $this->_currentdate->format('Y-m-d') . ' ' . $this->_currentdate->format('H:i:59');

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
				$contentBody = explode(',', $component['content']);
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
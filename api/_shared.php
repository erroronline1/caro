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
						case 'productselection': // form.php, record.php, assemble.js can make good use of this method!
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
									'onpointerup' => "_client.order.addProduct('" . $row['article_unit'] . "', '" . $row['article_no'] . "', '" . $row['article_name'] . "', '" . $row['article_ean'] . "', '" . $row['vendor_name'] . "'); return false;",
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
	 *                       _   ___               
	 *   ___ ___ ___ ___ ___| |_|  _|___ ___ _____ 
	 *  |  _| -_|  _| -_|   |  _|  _| . |  _|     |
	 *  |_| |___|___|___|_|_|_| |_| |___|_| |_|_|_|
	 *                                             
	 * retrieves most recent approved form or component
	 * and returns the content as body response e.g. for modal
	 * @param string $query _sqlinterface query
	 * @param array $parameters _ sqlinterface parameters
	 * @param string $requestedTimestamp Y-m-d H:i:s as optional past delimiter
	 * 
	 * @return array form components or form names within bundles
	 */
	public function recentform($query = '', $parameters = [], $requestedTimestamp = null){
		$requestedTimestamp = $requestedTimestamp ? : $this->_currentdate->format('Y-m-d') . ' ' . $this->_currentdate->format('H:i:59');

		$contentBody = [];
		$contents = SQLQUERY::EXECUTE($this->_pdo, $query, $parameters);
		if ($contents){
			foreach($contents as $content){
				if (PERMISSION::fullyapproved('formapproval', $content['approval'])) break;
			}
			if (!PERMISSION::fullyapproved('formapproval', $content['approval']) // failsafe if none are approved
				|| $content['hidden']
				|| !PERMISSION::permissionIn($content['restricted_access'])
				|| $content['date'] > $requestedTimestamp) return [];

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
					$components = SQLQUERY::EXECUTE($this->_pdo, 'form_component_get_by_name', [
						'values' => [
							':name' => $usedcomponent
						]
					]);
					foreach ($components as $component){
						$component['hidden'] = json_decode($component['hidden'] ? : '', true); 
						if ((!$component['hidden'] || $component['hidden']['date'] > $requestedTimestamp ) && PERMISSION::fullyapproved('formapproval', $component['approval'])) break;
						else $component = [];
					}
					if ($component){
						$component['content'] = json_decode($component['content'], true);
						array_push($contentBody, ...$component['content']['content']);
					}
				}
			}
		}
		return $contentBody;
	}
}

?>
<?php
// add, edit and delete distributors and orders
include_once('csvprocessor.php');

class PURCHASE extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
	private $_requestedID = null;
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
	}

	private function update_pricelist($file, $filter, $distributorID){
  		$filter='{    "filesetting": {     "headerrowindex": 0,     "dialect": {      "separator": ";",      "enclosure": "\"",      "escape": ""     },     "columns": [      "ArtNr",      "Bezeichnung",      "ME"     ]    },    "modify": {     "rewrite": [{      "article_no": ["ArtNr"],      "article_name": ["Bezeichnung"],      "article_unit": ["ME"]     }]    }   }';

		$filter = json_decode($filter, true);
		$filter['filesetting']['source'] = $file;
		$pricelist = new Listprocessor($filter);
		if (count($pricelist->_list)){
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('purchase_delete-purchase-items'));
			$statement->execute([
				':id' => $distributorID
			]);
			$insert = '';
			foreach($pricelist->_list as $i => $row){
				$insert .= strtr(SQLQUERY::PREPARE('purchase_insert-purchase-items'),
					[
						':distributor_id' => $distributorID,
						':article_no' => "'" . $row['article_no'] . "'",
						':article_name' => "'" . $row['article_name'] . "'",
						':article_unit' => "'" . $row['article_unit'] . "'"
					]) . '; ';
			}
			$statement = $this->_pdo->prepare($insert);
			if ($statement->execute()) return date("d.m.Y");
		}
		return '';
	}

	public function distributor(){
		// Y U NO DELETE? because of audit safety, that's why!

		if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				
				$distributor = [
					'name' => $this->_payload->name,
					'active' => UTILITY::propertySet($this->_payload, LANG::GET('purchase.edit_distributor_active')) === LANG::GET('purchase.edit_distributor_isactive') ? 1 : 0,
					'info' => $this->_payload->info,
					'certificate' => ['validity' => $this->_payload->certificate_validity],
					'pricelist' => ['validity' => '', 'filter' => $this->_payload->pricelist_filter]
				];
				// checkboxes are not delivered if null, html-value 'on' might have to be converted in given db-structure
				// e.g. $this->_payload->active = $this->_payload->active ? 1 : 0;

				// save certificate
				if (array_key_exists('certificate', $_FILES) && $_FILES['certificate']['tmp_name']) {
					UTILITY::storeUploadedFiles($_FILES, ['certificate'], 'files/distributors/' . $distributor['name'] . '/certificates', [$distributor['name'] . '_' . date('Ymd')]);
				}
				// update pricelist
				if (array_key_exists('pricelist', $_FILES) && $_FILES['pricelist']['tmp_name']) {
					$distributor['pricelist']['validity'] = $this->update_pricelist($_FILES['pricelist']['tmp_name'], $distributor['pricelist_filter'], $distributor['id']);
				}
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('purchase_post-distributor'));
				if ($statement->execute([
					':name' => $distributor['name'],
					':active' => $distributor['active'],
					':info' => $distributor['info'],
					':certificate' => json_encode($distributor['certificate']),
					':pricelist' => json_encode($distributor['pricelist'])
				])){
					$this->response(['id' => $this->_pdo->lastInsertId(), 'name' => UTILITY::scriptFilter($this->_payload->name)]);
				}
				break;

			case 'PUT':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$requestedID = $this->_requestedID;
		
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('purchase_get-distributor-id'));
				$statement->execute([
					':id' => $requestedID
				]);
				// prepare distributor-array to update, return error if not found
				if (!($distributor = $statement->fetch(PDO::FETCH_ASSOC))) $this->response(null, 406);

				$distributor['active'] = UTILITY::propertySet($this->_payload, LANG::GET('purchase.edit_distributor_active')) === LANG::GET('purchase.edit_distributor_isactive') ? 1 : 0;
				$distributor['name'] = $this->_payload->name;
				$distributor['info'] = $this->_payload->info;
				$distributor['certificate'] = json_decode($distributor['certificate'], true);
				$distributor['certificate']['validity'] = $this->_payload->certificate_validity;
				$distributor['pricelist'] = json_decode($distributor['pricelist'], true);
				$distributor['pricelist']['filter'] = $this->_payload->pricelist_filter;

				// save certificate
				if (array_key_exists('certificate', $_FILES) && $_FILES['certificate']['tmp_name']) {
					UTILITY::storeUploadedFiles($_FILES, ['certificate'], 'files/distributors/' . $distributor['name'] . '/certificates', [$distributor['name'] . '_' . date('Ymd')]);
				}
				// update pricelist
				if (array_key_exists('pricelist', $_FILES) && $_FILES['pricelist']['tmp_name']) {
					$distributor['pricelist']['validity'] = $this->update_pricelist($_FILES['pricelist']['tmp_name'], $distributor['pricelist_filter'], $distributor['id']);
				}
				// tidy up purchase items database if inactive
				if (!$distributor['active']){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('purchase_delete-purchase-items'));
					$statement->execute([
						':id' => $distributor['id']
					]);
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('purchase_put-distributor'));
				if ($statement->execute([
					':id' => $distributor['id'],
					':active' => $distributor['active'],
					':name' => $distributor['name'],
					':info' => $distributor['info'],
					':certificate' => json_encode($distributor['certificate']),
					':pricelist' => json_encode($distributor['pricelist'])
				])){
					$this->response(['id' => $distributor['id'], 'name' => UTILITY::scriptFilter($this->_payload->name)]);
				}
				break;

			case 'GET':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				$passedID = $this->_requestedID;
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
				if (!$distributor = $statement->fetch(PDO::FETCH_ASSOC)) $distributor = [
					'id' => null,
					'name' => '',
					'active' => 0,
					'info' => '',
					'certificate' => '{"validity":""}',
					'pricelist' => '{"validity":"", "filter": ""}'
				];

				$distributor['certificate'] = json_decode($distributor['certificate'], true);
				$distributor['pricelist'] = json_decode($distributor['pricelist'], true);
				$isactive = $distributor['active'] ? ['checked' => true] : [];
				$isinactive = !$distributor['active'] ? ['checked' => true] : [];

				$certificates = [];
				if ($distributor['id']) {
					$certfiles = UTILITY::listFiles('files/distributors/' . $distributor['name'] . '/certificates');
					foreach($certfiles as $path){
						$certificates['api/' . $path] = ['target' => '_blank'];
					}
				}
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
							'required' => true,
							'value' => $distributor['certificate']['validity'] ? : ''
						]],
						["type" => "file",
						"description" => LANG::GET('purchase.edit_distributor_certificate_update'),
						'attributes' => [
							'name' => 'certificate',
						]]
					],
					[
						["type" => "file",
						"description" => LANG::GET('purchase.edit_distributor_pricelist_update'),
						'attributes' => [
							'name' => 'pricelist',
							'accept' => '.csv'
						]],
						["type" => "textarea",
						"description" => LANG::GET('purchase.edit_distributor_pricelist_filter'),
						'attributes' => [
							'name' => 'pricelist_filter',
							'value' => $distributor['pricelist']['filter'] ? : '',
							'placeholder' => json_encode(json_decode($this->filtersample, true))
						]]
					],
					[
						["type" => "radio",
						"description" => LANG::GET('purchase.edit_distributor_active'),
						"content" => [
							LANG::GET('purchase.edit_distributor_isactive') => $isactive,
							LANG::GET('purchase.edit_distributor_isinactive') => $isinactive
						]]
					]
				],
				'form' => [
					'data-usecase' => 'purchase',
					'action' => $distributor['id'] ? 'javascript:api.purchase("put", "distributor", "' . $distributor['id'] . '")' : 'javascript:api.purchase("post", "distributor")'
				]];

				if ($distributor['pricelist']['validity'])
					array_splice($form['content'][5], 0, 0,
				[
					["type" => "text",
					"description" => LANG::GET('purchase.edit_distributor_pricelist_validity'),
					"content" => $distributor['pricelist']['validity']
					]
				]
				);

				if ($certificates)
					array_splice($form['content'][4], 1, 0,
				[
					['type' => 'links',
					'description' => LANG::GET('purchase.edit_distributor_certificate_download'),
					'content' => 
						$certificates
					]
				]
				);

				$this->response($form);
				break;
		}
	}
	public function order(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
			case 'PUT':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
			case 'GET':
				if (!(array_intersect(['admin', 'purchase', 'user'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
			case 'DELETE':
				if (!(array_intersect(['admin', 'purchase'], $_SESSION['user']['permissions']))) $this->response([], 401);
				break;
			}
	}
}

$api = new PURCHASE();
$api->processApi();

exit;
?>
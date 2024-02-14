<?php
// add and edit form components and forms
// Y U NO DELETE? because of audit safety, that's why!

class FORMS extends API {
   // processed parameters for readability
   public $_requestedMethod = REQUEST[1];
   private $_requestedID = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	public function component(){
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$content=['content' => $this->_payload->content];
				if (property_exists($this->_payload, 'form')) $content['form'] = $this->_payload->form; 
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-post'));
				if ($statement->execute([
					':name' => $this->_payload->name,
					':author' => $_SESSION['user']['name'],
					':content' => json_encode($content)
					])) $this->response([
						'status' => [
							'name' => $this->_payload->name,
							'msg' => LANG::GET('assemble.edit_component_saved', [':name' => $this->_payload->name])
						]]);
					else $this->response([
						'status' => [
							'name' => false,
							'name' => LANG::GET('assemble.edit_component_not_saved')
						]]);
				break;
			case 'GET':
				if ($this->_requestedID == '0' || intval($this->_requestedID)){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get'));
					$statement->execute([
						':id' => $this->_requestedID
					]);
				} else {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name'));
					$statement->execute([
						':name' => $this->_requestedID
					]);
				}
				if ($component = $statement->fetch(PDO::FETCH_ASSOC)){
					$component['content'] = json_decode($component['content']);
					$this->response(['body' => $component, 'name' => $component['name']]);
				}
				$this->response(['status' => ['msg' => LANG::GET('assemble.error_component_not_found', [':name' => $this->_requestedID])]]);
				break;
		}
	}

	public function component_editor(){
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
		$componentdatalist = [];
		$options = ['...' . LANG::GET('assemble.edit_existing_components_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		$alloptions = ['...' . LANG::GET('assemble.edit_existing_components_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		$return = [];
		
		// get selected component
		if ($this->_requestedID == '0' || intval($this->_requestedID)){
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get'));
			$statement->execute([
				':id' => $this->_requestedID
			]);
		} else {
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name'));
			$statement->execute([
				':name' => $this->_requestedID
			]);
		}
		if (!$component = $statement->fetch(PDO::FETCH_ASSOC)) $component = ['name' =>''];
		if($this->_requestedID && $this->_requestedID !== 'false' && !$component['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('assemble.error_component_not_found', [':name' => $this->_requestedID])];

		// prepare existing component lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-datalist'));
		$statement->execute();
		$components = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach($components as $key => $row) {
			if (!array_key_exists($row['name'], $options)) {
				$componentdatalist[] = $row['name'];
				$options[$row['name']] = ($row['name'] === $component['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
			}
			$alloptions[$row['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => $row['date']])] = ($row['name'] === $component['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
		}

		$return['body'] = [
			'content' => [
				[
					[
						[
							'type' => 'datalist',
							'content' => $componentdatalist,
							'attributes' => [
								'id' => 'components'
							]
						], [
							'type' => 'select',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_components_select'),
								'onchange' => "api.form('get', 'component_editor', this.value)"
							],
							'content' => $options
						],[
							'type' => 'searchinput',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_forms'),
								'list' => 'components',
								'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'component_editor', this.value); return false;}"
							]
						]
					],[
						[
							'type' => 'select',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_components_all'),
								'onchange' => "api.form('get', 'component_editor', this.value)"
							],
							'content' => $alloptions
						],
					]
				],[
					[[
						'type' => 'text',
						'description' => LANG::GET('assemble.edit_components_info_description'),
						'content' => LANG::GET('assemble.edit_components_info_content')
					]], [[
						'form' => true,
						'type' => 'compose_text',
						'description' => LANG::GET('assemble.compose_text')
					]], [[
						'form' => true,
						'type' => 'compose_textinput',
						'description' => LANG::GET('assemble.compose_textinput')
					]], [[
						'form' => true,
						'type' => 'compose_textarea',
						'description' => LANG::GET('assemble.compose_textarea')
					]], [[
						'form' => true,
						'type' => 'compose_numberinput',
						'description' => LANG::GET('assemble.compose_numberinput')
					]], [[
						'form' => true,
						'type' => 'compose_dateinput',
						'description' => LANG::GET('assemble.compose_dateinput')
					]], [[
						'form' => true,
						'type' => 'compose_links',
						'description' => LANG::GET('assemble.compose_links')
					]], [[
						'form' => true,
						'type' => 'compose_checkbox',
						'description' => LANG::GET('assemble.compose_checkbox')
					]], [[
						'form' => true,
						'type' => 'compose_radio',
						'description' => LANG::GET('assemble.compose_radio')
					]], [[
						'form' => true,
						'type' => 'compose_select',
						'description' => LANG::GET('assemble.compose_select')
					]], [[
						'form' => true,
						'type' => 'compose_file',
						'description' => LANG::GET('assemble.compose_file')
					]], [[
						'form' => true,
						'type' => 'compose_photo',
						'description' => LANG::GET('assemble.compose_photo')
					]], [[
						'form' => true,
						'type' => 'compose_signature',
						'description' => LANG::GET('assemble.compose_signature')
					]], [[
						'form' => true,
						'type' => 'compose_scanner',
						'description' => LANG::GET('assemble.compose_scanner')
					]]
				],
				[[
					'type' => 'compose_component',
					'description' => LANG::GET('assemble.compose_component'),
					'value' => $component['name'],
					'hint' => $component['name'] ? LANG::GET('assemble.compose_component_author', [':author' => $component['author'], ':date' => $component['date']]) : ''
				]],
				[[
					'type' => 'trash',
					'description' => LANG::GET('assemble.edit_trash')
				]]
			]
		];

		if (array_key_exists('content', $component)) $return['body']['component'] = json_decode($component['content']);

		$this->response($return);
	}

	public function form_editor(){
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
		// form to add and edit form components. 
		$formdatalist = $componentdatalist = [];
		$formoptions = ['...' . LANG::GET('assemble.edit_existing_forms_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		$alloptions = ['...' . LANG::GET('assemble.edit_existing_forms_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		$componentoptions = ['...' => ['value' => '0']];
		$return = [];
		
		// get selected form
		if ($this->_requestedID == '0' || intval($this->_requestedID)){
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-get'));
			$statement->execute([
				':id' => $this->_requestedID
			]);
		} else{
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-get-latest-by-name'));
			$statement->execute([
				':name' => $this->_requestedID
			]);
		}
		if (!$result = $statement->fetch(PDO::FETCH_ASSOC)) $result = ['name' => ''];
		if($this->_requestedID && $this->_requestedID !== 'false' && !$result['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('assemble.error_form_not_found', [':name' => $this->_requestedID])];

		// prepare existing component lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-datalist'));
		$statement->execute();
		$fd = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach($fd as $key => $row) {
			if (!array_key_exists($row['name'], $formoptions)) {
				$formdatalist[] = $row['name'];
				$formoptions[$row['name']] = ($row['name'] === $result['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
			}
			$alloptions[$row['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $component['author'], ':date' => $component['date']])] = ($row['name'] === $component['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
		}

		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-datalist'));
		$statement->execute();
		$cd = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach($cd as $key => $row) {
			if (!array_key_exists($row['name'], $componentoptions)) {
				$componentdatalist[] = $row['name'];
				$componentoptions[$row['name']] = ['value' => $row['id']];
			}
		}
		
		$return['body'] = [
			'content' => [
				[
					[
						[
							'type' => 'datalist',
							'content' => $formdatalist,
							'attributes' => [
								'id' => 'forms'
							]
						], [
							'type' => 'datalist',
							'content' => $componentdatalist,
							'attributes' => [
								'id' => 'components'
							]
						], [
							'type' => 'select',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_forms_select'),
								'onchange' => "api.form('get', 'form_editor', this.value)"
							],
							'content' => $formoptions
						], [
							'type' => 'searchinput',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_forms'),
								'list' => 'forms',
								'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'form_editor', this.value); return false;}"
							]
						]
					],[
						[
							'type' => 'select',
							'attributes' => [
								'name' => LANG::GET('assemble.edit_existing_forms_all'),
								'onchange' => "api.form('get', 'form_editor', this.value)"
							],
							'content' => $alloptions
						]
					]
				], [
					[
						"type" => "text",
						"description" => LANG::GET('assemble.edit_forms_info_description'),
						"content" => LANG::GET('assemble.edit_forms_info_content')
					], [
						'type' => 'select',
						'attributes' => [
							'name' => LANG::GET('assemble.edit_add_component_select'),
							'onchange' => "api.form('get', 'component', this.value)"
						],
						'content' => $componentoptions
					], [
						'type' => 'searchinput',
						'attributes' => [
							'name' => LANG::GET('assemble.edit_add_component'),
							'list' => 'components',
							'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'component', this.value); return false;}"
						]
					]
				], [
					[
						'type' => 'compose_form',
						'description' => LANG::GET('assemble.compose_form'),
						'value' => $result['name'] ? : ''
					]
				], [
					[
						'type' => 'trash',
						'description' => LANG::GET('assemble.edit_trash')
					]
				]
			]
		];

		if (array_key_exists('content', $result)) $return['body']['component'] = json_decode($result['content']);
		$this->response($return);
	}

	public function form(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
				// content : {'context':'...', 'components': [array]}
				break;
			case 'GET':
			// retrieve latest active entries according to requested names
			/*$requestedNames = explode(',',SQLQUERY::SANITIZE($this->_payload->content));
			$statement = $this->_pdo->prepare("SELECT * FROM form_components WHERE id IN (SELECT MAX(id) FROM forms WHERE name IN ('". implode("','", $requestedNames)."') GROUP BY name)");
			$statement->execute();
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			// order by $this->_payload->content sequence with anonymous function passing $this->_payload into scope
			usort($result, function ($a, $b) use ($requestedNames){
				if (array_search($a['name'], $requestedNames) <= array_search($b['name'], $requestedNames)) return -1;
				return 1;
				});
			// rebuild result array
			$form = false;
			$content = [];
			foreach($result as $key => $row) {
				$currentcontent=json_decode($row['content'], true);
				// notice optional form attributes will be overwritten with the latest value
				if (array_key_exists('form', $currentcontent)) $form = array_merge(gettype($form)==='boolean'? []: $form, $currentcontent['form']);
				array_push($content, ...$currentcontent['content']);
			}
			// reassign $result
			$result=[];
			if ($form!==false) $result['form'] = $form;
			$result['content'] = $content;
			echo json_encode($result);*/
				break;
		}

	}
		//case 'form_get':

}

$api = new FORMS();
$api->processApi();

exit;
?>
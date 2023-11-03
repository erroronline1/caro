<?php
// add and edit form components and forms
// Y U NO DELETE? because of audit safety, that's why!

class FORMS extends API {
   // processed parameters for readability
   public $_requestedMethod = REQUEST[1];
   private $_requestedName = REQUEST[2];

	public function __construct(){
		parent::__construct();
	}


	public function component(){
		if (!(in_array('admin', $_SESSION['user']['permissions']))) $this->response([], 401);

		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$content=['content' => $this->_payload->content];
				if (property_exists($this->_payload, 'form')) $content['form'] = $this->_payload->form; 
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-post'));
				if ($statement->execute([
					':name' => SQLQUERY::SANITIZE($this->_payload->name),
					':content' => json_encode($content)
					])){
						$this->response(['name' => UTILITY::scriptFilter($this->_payload->name)]);
				}
				break;
			case 'GET':
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get'));
				$statement->execute([
					':name' => SQLQUERY::SANITIZE($this->_requestedName)
				]);
				$component = $statement->fetch(PDO::FETCH_ASSOC);
				$component['content'] = json_decode($component['content']);
				$this->response($component);
				break;
		}
	}

	public function component_editor(){
		if (!(in_array('admin', $_SESSION['user']['permissions']))) $this->response([], 401);
		$requestedComponent = SQLQUERY::SANITIZE($this->_requestedName);
		$datalist=[];
		$options=['...'=>[]];
		
		// prepare existing component lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-datalist'));
		$statement->execute();
		$components = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach($components as $key => $row) {
			$datalist[]=$row['name'];
			$options[$row['name']]=[];
		}

		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get'));
		$statement->execute([
			':name' => $requestedComponent
		]);
		$component = $statement->fetch(PDO::FETCH_ASSOC);

		$creator = [
			'content' => [
				[
					['type' => 'datalist',
					'content' => $datalist,
					'attributes' => [
						'id' => 'components'
					]]
				],[
					['type' => 'searchinput',
					'description' => LANG::GET('assemble.edit_existing_components'),
					'attributes' => [
						'placeholder' => LANG::GET('assemble.edit_existing_components_label'),
						'list' => 'components',
						'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'component_editor', this.value); return false;}"
					]],
					['type' => 'select',
					'description' => LANG::GET('assemble.edit_existing_components'),
					'attributes' => [
						'onchange' => "api.form('get', 'component_editor', this.value)"
					],
					'content' => $options]
				],[[
					'type' => 'text',
					'description' => LANG::GET('assemble.edit_components_info_description'),
					'content' => LANG::GET('assemble.edit_components_info_content')
				], [
					'form' => true,
					'type' => 'compose_text',
					'description' => LANG::GET('assemble.compose_text')
				], [
					'form' => true,
					'type' => 'compose_textinput',
					'description' => LANG::GET('assemble.compose_textinput')
				], [
					'form' => true,
					'type' => 'compose_textarea',
					'description' => LANG::GET('assemble.compose_textarea')
				], [
					'form' => true,
					'type' => 'compose_numberinput',
					'description' => LANG::GET('assemble.compose_numberinput')
				], [
					'form' => true,
					'type' => 'compose_dateinput',
					'description' => LANG::GET('assemble.compose_dateinput')
				], [
					'form' => true,
					'type' => 'compose_links',
					'description' => LANG::GET('assemble.compose_links')
				], [
					'form' => true,
					'type' => 'compose_radio',
					'description' => LANG::GET('assemble.compose_radio')
				], [
					'form' => true,
					'type' => 'compose_checkbox',
					'description' => LANG::GET('assemble.compose_checkbox')
				], [
					'form' => true,
					'type' => 'compose_select',
					'description' => LANG::GET('assemble.compose_select')
				], [
					'form' => true,
					'type' => 'compose_file',
					'description' => LANG::GET('assemble.compose_file')
				], [
					'form' => true,
					'type' => 'compose_photo',
					'description' => LANG::GET('assemble.compose_photo')
				], [
					'form' => true,
					'type' => 'compose_signature',
					'description' => LANG::GET('assemble.compose_signature')
				], [
					'form' => true,
					'type' => 'compose_qrscanner',
					'description' => LANG::GET('assemble.compose_qrscanner')
				]],
				[[
					'type' => 'compose_component',
					'description' => LANG::GET('assemble.compose_component'),
					'value' => $component['name'] ? : ''
				]],
				[[
					'type' => 'trash',
					'description' => LANG::GET('assemble.edit_trash')
				]]
				]];

		if ($component) $creator['component'] = json_decode($component['content']);

		$this->response($creator);
	}

	public function form_editor(){
		if (!(in_array('admin', $_SESSION['user']['permissions']))) $this->response([], 401);
		$requestedForm = SQLQUERY::SANITIZE($this->_requestedName);
		// form to add and edit form components. 
		$formdatalist = $componentdatalist = [];
		$formoptions = ['...'=>[]];
		$componentoptions = ['...'=>[]];
		
		// prepare existing component lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_datalist'));
		$statement->execute();
		$result = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach($result as $key => $row) {
			$formdatalist[]=$row['name'];
			$formoptions[$row['name']]=[];
		}

		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-datalist'));
		$statement->execute();
		$result = $statement->fetchAll(PDO::FETCH_ASSOC);
		foreach($result as $key => $row) {
			$componentdatalist[]=$row['name'];
			$componentoptions[$row['name']]=[];
		}
			

		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_get'));
		$statement->execute([
			':name' => SQLQUERY::SANITIZE($requestedForm)
		]);
		$result = $statement->fetch(PDO::FETCH_ASSOC);

		$creator = [
			'content' => [
				[
					['type' => 'datalist',
					'content' => $formdatalist,
					'attributes' => [
						'id' => 'forms'
					]],
					['type' => 'datalist',
					'content' => $componentdatalist,
					'attributes' => [
						'id' => 'components'
					]]
				],[
					['type' => 'searchinput',
					'description' => LANG::GET('assemble.edit_existing_forms'),
					'attributes' => [
						'placeholder' => LANG::GET('assemble.edit_existing_forms_label'),
						'list' => 'forms',
						'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'form_editor', this.value); return false;}"
					]],
					['type' => 'select',
					'description' => LANG::GET('assemble.edit_existing_forms'),
					'attributes' => [
						'onchange' => "api.form('get', 'form_editor', this.value)"
					],
					'content' => $formoptions]
					],[[
						"type" => "text",
						"description" => LANG::GET('assemble.edit_forms_info_description'),
						"content" => LANG::GET('assemble.edit_forms_info_content')
					],
					['type' => 'searchinput',
					'description' => LANG::GET('assemble.edit_add_component'),
					'attributes' => [
						'placeholder' => LANG::GET('assemble.edit_add_component_label'),
						'list' => 'components',
						'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'component', this.value); return false;}"
					]],
					['type' => 'select',
					'description' => LANG::GET('assemble.edit_add_component'),
					'attributes' => [
						'onchange' => "api.form('get', 'component', this.value)"
					],
					'content' => $componentoptions],
					[
						'form' => true,
						'type' => 'compose_hiddeninput',
						'description' => LANG::GET('assemble.compose_hiddeninput'),
					]],
					[[
						'type' => 'compose_form',
						'description' => LANG::GET('assemble.compose_form'),
						'value' => $result['name'] ? : ''
					]],
					[[
						'type' => 'trash',
						'description' => LANG::GET('assemble.edit_trash')
					]]
				]];

		if ($result) $creator['component'] = json_decode($result['content']);
		$this->response($creator);
	}
	public function form(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(in_array('admin', $_SESSION['user']['permissions']))) $this->response([], 401);
				
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
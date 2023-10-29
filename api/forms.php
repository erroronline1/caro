<?php
// add and edit form components and forms

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
	switch ($payload->request){
		case 'form_components_save':
			$content=['content' => $payload->content];
			if ($payload->form) $content['form'] = $payload->form; 
			$statement = $pdo->prepare("INSERT INTO `form_components` ".
				"(`id`, `name`, `date`, `content`) VALUES (" . 
				"NULL, '" . 
				dbSanitize($payload->name) . "', " .
				"CURRENT_TIMESTAMP, '" .
				addslashes(json_encode($content)) . "')");
			if ($statement->execute()){
					$result = ['name' => scriptFilter($payload->name)];
					echo json_encode($result);
			}
		break;
		case 'form_save':
			// TODO
		break;
		}
	//	else echo http_response_code(401);
}

// Y U NO DELETE? because of audit safety, that is!

elseif ($_SERVER['REQUEST_METHOD'] == 'GET'){
	switch ($payload->request){
		case 'form_components_edit':
			// form to add and edit form components. 
			$datalist=[];
			$options=['...'=>[]];
			
			// prepare existing component lists
			$statement = $pdo->prepare("SELECT name FROM form_components GROUP BY name ORDER BY name ASC");
			$statement->execute();
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach($result as $key => $row) {
				$datalist[]=$row['name'];
				$options[$row['name']]=[];
			}

			$statement = $pdo->prepare("SELECT name, content FROM form_components WHERE name = '" . $payload->name . "' ORDER BY id DESC LIMIT 1");
			$statement->execute();
			$result = $statement->fetch(PDO::FETCH_ASSOC);
	
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
						'description' => 'edit existing components',
						'attributes' => [
							'placeholder' => 'search name',
							'list' => 'components',
							'onkeypress' => "if (event.key === 'Enter') {api.form('form_components_edit', this.value); return false;}"
						]],
						['type' => 'select',
						'description' => 'edit existing components',
						'attributes' => [
							'onchange' => "api.form('form_components_edit', this.value)"
						],
						'content' => $options]
					],[[
						'type' => 'text',
						'description' => 'what to do',
						'content' => 'choose available elements from this panel. set your parameters and add fields. advanced attributes (href, value, events, etc) have to be set in json-format with double-quotes. change your order by dragging the elements. during composing indicators for containers might not be available. dragging is available on devices with mice only.'
					], [
						'form' => true,
						'type' => 'compose_text',
						'description' => 'add an informative text'
					], [
						'form' => true,
						'type' => 'compose_textinput',
						'description' => 'add a single line text input'
					], [
						'form' => true,
						'type' => 'compose_textarea',
						'description' => 'add a multiline text input'
					], [
						'form' => true,
						'type' => 'compose_numberinput',
						'description' => 'add a number input'
					], [
						'form' => true,
						'type' => 'compose_dateinput',
						'description' => 'add a date input'
					], [
						'form' => true,
						'type' => 'compose_links',
						'description' => 'add a list of links'
					], [
						'form' => true,
						'type' => 'compose_radio',
						'description' => 'add a set of single selection options'
					], [
						'form' => true,
						'type' => 'compose_checkbox',
						'description' => 'add a set of multiple selection options'
					], [
						'form' => true,
						'type' => 'compose_select',
						'description' => 'add a dropdown'
					], [
						'form' => true,
						'type' => 'compose_file',
						'description' => 'add a file upload'
					], [
						'form' => true,
						'type' => 'compose_photo',
						'description' => 'add a photo upload'
					], [
						'form' => true,
						'type' => 'compose_signature',
						'description' => 'add a signature pad'
					], [
						'form' => true,
						'type' => 'compose_qrscanner',
						'description' => 'add a qr scanner field'
					]],
					[[
						'type' => 'compose_component',
						'description' => 'generate/update form component',
						'value' => $result['name'] ? : ''
					]],
					[[
						'type' => 'trash',
						'description' => 'drop panel here to delete'
					]]
					]];

			if ($result) $creator['component'] = json_decode($result['content']);

			echo json_encode($creator);
		break;
		case 'form_edit':
			// form to add and edit form components. 
			$formdatalist = $componentdatalist = [];
			$formoptions = ['...'=>[]];
			$componentoptions = ['...'=>[]];
			
			// prepare existing component lists
			$statement = $pdo->prepare("SELECT name FROM forms GROUP BY name ORDER BY name ASC");
			$statement->execute();
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach($result as $key => $row) {
				$formdatalist[]=$row['name'];
				$formoptions[$row['name']]=[];
			}

			$statement = $pdo->prepare("SELECT name FROM form_components GROUP BY name ORDER BY name ASC");
			$statement->execute();
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach($result as $key => $row) {
				$componentdatalist[]=$row['name'];
				$componentoptions[$row['name']]=[];
			}
				

			$statement = $pdo->prepare("SELECT name, content FROM forms WHERE name = '" . $payload->name . "' ORDER BY id DESC LIMIT 1");
			$statement->execute();
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
						'description' => 'edit existing forms',
						'attributes' => [
							'placeholder' => 'search name',
							'list' => 'forms',
							'onkeypress' => "if (event.key === 'Enter') {api.form('form_edit', this.value); return false;}"
						]],
						['type' => 'select',
						'description' => 'edit existing forms',
						'attributes' => [
							'onchange' => "api.form('form_edit', this.value)"
						],
						'content' => $formoptions]
						],[[
							"type" => "text",
							"description" => "what to do",
							"content" => "choose available form components from this panel. change your order by dragging the components. dragging is available on devices with mice only."
						],
						['type' => 'searchinput',
						'description' => 'add form component',
						'attributes' => [
							'placeholder' => 'search name',
							'list' => 'components',
							'onkeypress' => "if (event.key === 'Enter') {api.form('form_components_add', this.value); return false;}"
						]],
						['type' => 'select',
						'description' => 'add form component',
						'attributes' => [
							'onchange' => "api.form('form_components_add', this.value)"
						],
						'content' => $componentoptions],
						[
							"form" => true,
							"type" => "compose_hiddeninput",
							"description" => "create a hidden field",
						]],
						[[
							'type' => 'compose_form',
							'description' => 'generate/update form',
							'value' => $result['name'] ? : ''
						]],
						[[
							'type' => 'trash',
							'description' => 'drop panel here to delete'
						]]
					]];

			if ($result) $creator['component'] = json_decode($result['content']);

			echo json_encode($creator);
		break;
		case 'form_components_add':
			// retrieve latest form component according to name
			$statement = $pdo->prepare("SELECT name, content FROM form_components WHERE name='" . dbSanitize($payload->name) . "' ORDER BY id DESC");
			$statement->execute();
			$result = $statement->fetch(PDO::FETCH_ASSOC);
			$result['content'] = json_decode($result['content']);
			echo json_encode($result);
		break;
		case 'form_get':
			// retrieve latest active entries according to requested names
			/*$requestedNames = explode(',',dbSanitize($payload->content));
			$statement = $pdo->prepare("SELECT * FROM form_components WHERE id IN (SELECT MAX(id) FROM forms WHERE name IN ('". implode("','", $requestedNames)."') GROUP BY name)");
			$statement->execute();
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			// order by $payload->content sequence with anonymous function passing $payload into scope
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
		default:
			echo http_response_code(400);
		}
	//	else echo http_response_code(401);
}

?>
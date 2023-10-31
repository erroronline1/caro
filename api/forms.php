<?php
// add and edit form components and forms

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
	switch ($payload->request){
		case 'form_components_save':
			if (!in_array('admin', $_SESSION['user']['permissions'])){echo http_response_code(401); break;}
			$content=['content' => $payload->content];
			if ($payload->form) $content['form'] = $payload->form; 
			$statement = $pdo->prepare(SQLQUERY::PREPARE('form_components_save'));
			if ($statement->execute([
				':name' => SQLQUERY::SANITIZE($payload->name),
				':content' => addslashes(json_encode($content))
				])){
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
			if (!in_array('admin', $_SESSION['user']['permissions'])){echo http_response_code(401); break;}
			$datalist=[];
			$options=['...'=>[]];
			
			// prepare existing component lists
			$statement = $pdo->prepare(SQLQUERY::PREPARE('form_components_edit-datalist'));
			$statement->execute();
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach($result as $key => $row) {
				$datalist[]=$row['name'];
				$options[$row['name']]=[];
			}

			$statement = $pdo->prepare(SQLQUERY::PREPARE('form_components_edit-selected'));
			$statement->execute([
				':name' => SQLQUERY::SANITIZE($payload->name)
			]);
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
						'description' => LANG::GET('assemble.edit_existing_components'),
						'attributes' => [
							'placeholder' => LANG::GET('assemble.edit_existing_components_label'),
							'list' => 'components',
							'onkeypress' => "if (event.key === 'Enter') {api.form('form_components_edit', this.value); return false;}"
						]],
						['type' => 'select',
						'description' => LANG::GET('assemble.edit_existing_components'),
						'attributes' => [
							'onchange' => "api.form('form_components_edit', this.value)"
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
						'value' => $result['name'] ? : ''
					]],
					[[
						'type' => 'trash',
						'description' => LANG::GET('assemble.edit_trash')
					]]
					]];

			if ($result) $creator['component'] = json_decode($result['content']);

			echo json_encode($creator);
		break;
		case 'form_edit':
			// form to add and edit form components. 
			if (!in_array('admin', $_SESSION['user']['permissions'])){echo http_response_code(401); break;}
			$formdatalist = $componentdatalist = [];
			$formoptions = ['...'=>[]];
			$componentoptions = ['...'=>[]];
			
			// prepare existing component lists
			$statement = $pdo->prepare(SQLQUERY::PREPARE('form_edit-datalist'));
			$statement->execute();
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach($result as $key => $row) {
				$formdatalist[]=$row['name'];
				$formoptions[$row['name']]=[];
			}

			$statement = $pdo->prepare(SQLQUERY::PREPARE('form_edit-components_datalist'));
			$statement->execute();
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			foreach($result as $key => $row) {
				$componentdatalist[]=$row['name'];
				$componentoptions[$row['name']]=[];
			}
				

			$statement = $pdo->prepare(SQLQUERY::PREPARE('form_edit-selected'));
			$statement->execute([
				':name' => SQLQUERY::SANITIZE($payload->name)
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
							'onkeypress' => "if (event.key === 'Enter') {api.form('form_edit', this.value); return false;}"
						]],
						['type' => 'select',
						'description' => LANG::GET('assemble.edit_existing_forms'),
						'attributes' => [
							'onchange' => "api.form('form_edit', this.value)"
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
							'onkeypress' => "if (event.key === 'Enter') {api.form('form_components_add', this.value); return false;}"
						]],
						['type' => 'select',
						'description' => LANG::GET('assemble.edit_add_component'),
						'attributes' => [
							'onchange' => "api.form('form_components_add', this.value)"
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

			echo json_encode($creator);
		break;
		case 'form_components_add':
			// retrieve latest form component according to name
			if (!in_array('admin', $_SESSION['user']['permissions'])){echo http_response_code(401); break;}
			$statement = $pdo->prepare(SQLQUERY::PREPARE('form_components_add'));
			$statement->execute([
				':name' => SQLQUERY::SANITIZE($payload->name)
			]);
			$result = $statement->fetch(PDO::FETCH_ASSOC);
			$result['content'] = json_decode($result['content']);
			echo json_encode($result);
		break;
		case 'form_get':
			// retrieve latest active entries according to requested names
			/*$requestedNames = explode(',',SQLQUERY::SANITIZE($payload->content));
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
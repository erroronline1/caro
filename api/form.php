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
				$component = json_decode($this->_payload->composedComponent, true);
				$component_name = $component['name'];			
				unset($component['name']);
				$component_hidden = intval($component['hidden']);			
				unset($component['hidden']);

				// put hidden attribute if anything else remains the same
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name'));
				$statement->execute([
					':name' => $component_name
				]);
				$exists = $statement->fetch(PDO::FETCH_ASSOC);
				if ($exists && json_decode($exists['content'], true) == $component) {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_put'));
					if ($statement->execute([
						':alias' => '',
						':context' => 'component',
						':hidden' => $component_hidden,
						':id' => $exists['id']
						])) $this->response([
							'status' => [
								'name' => $component_name,
								'msg' => LANG::GET('assemble.edit_component_saved', [':name' => $component_name])
							]]);	
				}

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $component_name, $matches)) $this->response(['status' => ['msg' => LANG::GET('assemble.error_forbidden_name', [':name' => $component_name])]]);
				}
				// recursively replace images with actual $_FILES content according to content nesting
				if (array_key_exists('composedComponent_files', $_FILES)){
					$uploads = UTILITY::storeUploadedFiles(['composedComponent_files'], UTILITY::directory('component_attachments'), [$component_name . '_' . date('YmdHis')]);
					$files=[];
					foreach($uploads as $path){
						UTILITY::resizeImage($path, 2048, UTILITY_IMAGE_REPLACE);
						preg_match_all('/[\w\s\d\.]+/m', $path, $filename);
						$files[substr(stristr($filename[0][count($filename[0]) - 1], '_'), 1)] = substr($path, 1);
					}

					function replace_images($element, $filearray){
						$result = [];
						foreach($element as $sub){
							if (array_is_list($sub)){
								$result[] = replace_images($sub, $filearray);
							} else {
								if ($sub['type'] === 'image'){
									preg_match_all('/[\w\s\d\.]+/m', $sub['attributes']['name'], $fakefilename);
									$filename = $fakefilename[0][count($fakefilename[0])-1];
									if ($filename && array_key_exists($filename, $filearray)){ // replace only if $_FILES exist, in case of updates, where no actual file has been submitted
										$sub['attributes']['name'] = $filename;
										$sub['attributes']['url'] = $filearray[$filename];
									}
								}
								$result[] = $sub;
							}
						}
						return $result;
					}
					$component['content'] = replace_images($component['content'], $files);
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_post'));
				if ($statement->execute([
					':name' => $component_name,
					':alias' => '',
					':context' => 'component',
					':author' => $_SESSION['user']['name'],
					':content' => json_encode($component)
					])) $this->response([
						'status' => [
							'name' => $component_name,
							'msg' => LANG::GET('assemble.edit_component_saved', [':name' => $component_name])
						]]);
				else $this->response([
					'status' => [
						'name' => false,
						'msg' => LANG::GET('assemble.edit_component_not_saved')
					]]);
				break;
			case 'GET':
				if (intval($this->_requestedID)){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_get'));
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
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_get'));
			$statement->execute([
				':id' => $this->_requestedID
			]);
		} else {
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name'));
			$statement->execute([
				':name' => $this->_requestedID
			]);
		}
		if (!$component = $statement->fetch(PDO::FETCH_ASSOC)) $component = ['id' => '', 'name' =>''];
		if($this->_requestedID && $this->_requestedID !== 'false' && !$component['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('assemble.error_component_not_found', [':name' => $this->_requestedID])];

		// prepare existing component lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-datalist'));
		$statement->execute();
		$components = $statement->fetchAll(PDO::FETCH_ASSOC);
		$hidden = [];
		foreach($components as $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!array_key_exists($row['name'], $options) && !in_array($row['name'], $hidden)) {
				$componentdatalist[] = $row['name'];
				$options[$row['name']] = ($row['name'] == $component['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
			}
			$alloptions[$row['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => $row['date']])] = ($row['name'] == $component['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
		}

		// check for dependencies in forms
		$dependedforms = [];
		if (array_key_exists('content', $component)){
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-datalist'));
			$statement->execute();
			$fd = $statement->fetchAll(PDO::FETCH_ASSOC);
			$hidden = [];
			foreach($fd as $row) {
				if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
				if (!in_array($row['name'], $dependedforms) && !in_array($row['name'], $hidden) && in_array($component['name'], explode(',', $row['content']))) {
					$dependedforms[] = $row['name'];
				}
			}
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
						'type' => 'compose_scanner',
						'description' => LANG::GET('assemble.compose_scanner')
					]], [[
						'form' => true,
						'type' => 'compose_text',
						'description' => LANG::GET('assemble.compose_text')
					]], [[
						'form' => true,
						'type' => 'compose_image',
						'description' => LANG::GET('assemble.compose_image')
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
						'type' => 'compose_telinput',
						'description' => LANG::GET('assemble.compose_telinput')
					]], [[
						'form' => true,
						'type' => 'compose_emailinput',
						'description' => LANG::GET('assemble.compose_emailinput')
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
					]]
				],
				[[
					'type' => 'compose_component',
					'description' => LANG::GET('assemble.compose_component'),
					'value' => $component['name'],
					'hint' => ($component['name'] ? LANG::GET('assemble.compose_component_author', [':author' => $component['author'], ':date' => $component['date']]) . '<br>' : '') .
						($dependedforms ? LANG::GET('assemble.compose_component_form_dependencies', [':forms' => implode(',', $dependedforms)]) : ''),
					'hidden' => $component['name'] ? intval($component['hidden']) : 1
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
		$formdatalist = $componentdatalist = [];
		$formoptions = ['...' . LANG::GET('assemble.edit_existing_forms_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		$alloptions = ['...' . LANG::GET('assemble.edit_existing_forms_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		$componentoptions = ['...' => ['value' => '0']];
		$contextoptions = ['...' . LANG::GET('assemble.edit_form_context_default') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
		$return = [];
		
		// get selected form
		if ($this->_requestedID == '0' || intval($this->_requestedID)){
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_get'));
			$statement->execute([
				':id' => $this->_requestedID
			]);
		} else{
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-get-latest-by-name'));
			$statement->execute([
				':name' => $this->_requestedID
			]);
		}
		if (!$result = $statement->fetch(PDO::FETCH_ASSOC)) $result = [
			'name' => '',
			'alias' => '',
			'context' => ''
		];
		if($this->_requestedID && $this->_requestedID !== 'false' && !$result['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('assemble.error_form_not_found', [':name' => $this->_requestedID])];

		// prepare existing forms lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-datalist'));
		$statement->execute();
		$fd = $statement->fetchAll(PDO::FETCH_ASSOC);
		$hidden = [];
		foreach($fd as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!array_key_exists($row['name'], $formoptions) && !in_array($row['name'], $hidden)) {
				$formdatalist[] = $row['name'];
				$formoptions[$row['name']] = ($row['name'] === $result['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
			}
			$alloptions[$row['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => $row['date']])] = ($row['name'] === $result['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
		}

		// prepare existing component list
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-datalist'));
		$statement->execute();
		$cd = $statement->fetchAll(PDO::FETCH_ASSOC);
		$hidden = [];
		foreach($cd as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!array_key_exists($row['name'], $componentoptions) && !in_array($row['name'], $hidden)) {
				$componentdatalist[] = $row['name'];
				$componentoptions[$row['name']] = ['value' => $row['id']];
			}
		}

		// check for bundle dependencies
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_bundle-datalist'));
		$statement->execute();
		$cd = $statement->fetchAll(PDO::FETCH_ASSOC);
		$hidden = [];
		$dependedbundles = [];
		foreach($cd as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden) && 
			in_array($result['name'], explode(',', $row['content'])) && 
			!in_array($result['name'], $dependedbundles)) $dependedbundles[] = $result['name']; 
		}
		
		// prepare existing context list
		foreach(LANGUAGEFILE['formcontext'] as $context => $display){
			$contextoptions[$display] = $context===$result['context'] ? ['value' => $context, 'selected' => true] : ['value' => $context];
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
						'value' => $result['name'] ? : '',
						'alias' => [
							'name' => LANG::GET('assemble.edit_form_alias'),
							'value' => $result['alias'] ? : ''
						],
						'context' => [
							'name' => LANG::GET('assemble.edit_form_context'),
							'content' => $contextoptions
						],
						'hint' => ($result['name'] ? LANG::GET('assemble.compose_component_author', [':author' => $result['author'], ':date' => $result['date']]) . '<br>' : '') .
						($dependedbundles ? LANG::GET('assemble.compose_form_bundle_dependencies', [':bundles' => implode(',', $dependedbundles)]) : ''),
						'hidden' => $result['name'] ? intval($result['hidden']) : 1
					]
				], [
					[
						'type' => 'trash',
						'description' => LANG::GET('assemble.edit_trash')
					]
				]
			]
		];

		// add used components to response
		if (array_key_exists('content', $result)) {
			$return['body']['components'] = [];
			foreach(explode(',', $result['content']) as $usedcomponent) {
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name'));
				$statement->execute([
					':name' => $usedcomponent
				]);
				$component = $statement->fetch(PDO::FETCH_ASSOC);
				$component['content'] = json_decode($component['content'], true);
				$component['content']['name'] = $usedcomponent;
				$component['content']['hidden'] = boolval(intval($component['hidden']));
				$return['body']['components'][] = $component['content'];
			}
		}
		$this->response($return);
	}

	public function form(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);

				if (!$this->_payload->context) $this->response(['status' => ['msg' => LANG::GET("assemble.edit_form_not_saved_missing")]]);
				// put hidden attribute, alias (uncritical) or context (user error) if anything else remains the same
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-get-latest-by-name'));
				$statement->execute([
					':name' => $this->_payload->name
				]);
				$exists = $statement->fetch(PDO::FETCH_ASSOC);
				if ($exists && $exists['content'] == implode(',', $this->_payload->content)) {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_put'));
					if ($statement->execute([
						':alias' => gettype($this->_payload->alias) === 'array' ? implode(' ', $this->_payload->alias) : '',
						':context' => $this->_payload->context,
						':hidden' => intval($this->_payload->hidden),
						':id' => $exists['id']
						])) $this->response([
							'status' => [
								'name' => $this->_payload->name,
								'msg' => LANG::GET('assemble.edit_form_saved', [':name' => $this->_payload->name])
							]]);	
				}

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $this->_payload->name, $matches)) $this->response(['status' => ['msg' => LANG::GET('assemble.error_forbidden_name', [':name' => $this->_payload->name])]]);
				}

				// recursively check for identifier
				function check4identifier($element){
					$hasindentifier=false;
					foreach($element as $sub){
						if (array_is_list($sub)){
							$hasindentifier = check4identifier($sub);
						} else {
								if (array_key_exists('type', $sub) && $sub['type'] === 'identify') $hasindentifier = true;
						}
					}
					return $hasindentifier;
				}
				$hasindentifier = false;
				foreach($this->_payload->content as $component){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name'));
					$statement->execute([
						':name' => $component
					]);
					$latestcomponent = $statement->fetch(PDO::FETCH_ASSOC);
					if (check4identifier(json_decode($latestcomponent['content'], true)['content'])) $hasindentifier = true;
				}
				if (!$hasindentifier) $this->response(['status' => ['msg' => LANG::GET('assemble.compose_context_missing_identifier')]]);

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_post'));
				if ($statement->execute([
					':name' => $this->_payload->name,
					':alias' => gettype($this->_payload->alias) === 'array' ? implode(' ', $this->_payload->alias): '',
					':context' => gettype($this->_payload->context) === 'array' ? '': $this->_payload->context,
					':author' => $_SESSION['user']['name'],
					':content' => implode(',', $this->_payload->content)
					])) $this->response([
						'status' => [
							'name' => $this->_payload->name,
							'msg' => LANG::GET('assemble.edit_form_saved', [':name' => $this->_payload->name])
						]]);
				else $this->response([
					'status' => [
						'name' => false,
						'msg' => LANG::GET('assemble.edit_form_not_saved')
					]]);
				break;
		}
	}

	public function bundle(){
		if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if ($content = UTILITY::propertySet($this->_payload, LANG::PROPERTY('assemble.edit_bundle_content'))) $content = implode(',', preg_split('/[\n\r]{1,}/', $content));
				else $content = '';
				$bundle = [
					':name' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('assemble.edit_bundle_name')),
					':alias' => '',
					':context' => 'bundle',
					':author' => $_SESSION['user']['name'],
					':content' => $content
				];

				if (!trim($bundle[':name']) || !trim($bundle[':content'])) $this->response([], 400);

				// put hidden attribute if anything else remains the same
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_bundle-get-latest-by-name'));
				$statement->execute([
					':name' => $bundle[':name']
				]);
				$exists = $statement->fetch(PDO::FETCH_ASSOC);
				if ($exists && $exists['content'] === $bundle[':content']) {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_put'));
					if ($statement->execute([
						':alias' => $exists['alias'],
						':context' => $exists['context'],
						':hidden' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('assemble.edit_bundle_hidden')) === LANG::PROPERTY('assemble.edit_bundle_hidden_hidden')? 1 : 0,
						':id' => $exists['id']
						])) $this->response([
							'status' => [
								'name' => $bundle[':name'],
								'msg' => LANG::GET('assemble.edit_bundle_saved', [':name' => $bundle[':name']])
							]]);	
				}

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $bundle[':name'], $matches)) $this->response(['status' => ['msg' => LANG::GET('assemble.error_forbidden_name', [':name' => $bundle[':name']])]]);
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_post'));
				if ($statement->execute($bundle)) $this->response([
						'status' => [
							'name' => $bundle[':name'],
							'msg' => LANG::GET('assemble.edit_bundle_saved', [':name' => $bundle[':name']])
						]]);
				else $this->response([
					'status' => [
						'name' => false,
						'msg' => LANG::GET('assemble.edit_bundle_not_saved')
					]]);
				break;
			case 'GET':
				$bundledatalist = [];
				$options = ['...' . LANG::GET('assemble.edit_existing_bundle_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$alloptions = ['...' . LANG::GET('assemble.edit_existing_bundle_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$insertform = ['...' . LANG::GET('assemble.edit_bundle_insert_default') => ['value' => ' ']];
				$return = [];

				// get selected bundle
				if (intval($this->_requestedID)){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_get'));
					$statement->execute([
						':id' => $this->_requestedID
					]);
				} else {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_bundle-get-latest-by-name'));
					$statement->execute([
						':name' => $this->_requestedID
					]);
				}
				if (!$bundle = $statement->fetch(PDO::FETCH_ASSOC)) $bundle = [
					'id' => '',
					'name' => '',
					'alias' => '',
					'context' => '',
					'date' => '',
					'author' => '',
					'content' => '',
					'hidden' => 0
				];
				if($this->_requestedID && $this->_requestedID !== 'false' && !$bundle['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('texttemplate.error_template_not_found', [':name' => $this->_requestedID])];
		
				// prepare existing templates lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_bundle-datalist-edit'));
				$statement->execute();
				$bundles = $statement->fetchAll(PDO::FETCH_ASSOC);
				$hidden = [];
				foreach($bundles as $key => $row) {
					if ($row['context'] === 'component') continue;
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if ($row['context'] === 'bundle'){
						if (!array_key_exists($row['name'], $options) && !in_array($row['name'], $hidden)) {
							$bundledatalist[] = $row['name'];
							$options[$row['name']] = ($row['name'] == $bundle['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
						}
						$alloptions[$row['name'] . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => $row['date']])] = ($row['name'] == $bundle['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
					}
					if (!in_array($row['context'] , ['bundle', 'template']) && !in_array($row['name'], $hidden)) $insertform[$row['name']] = ['value' => $row['name'] . "\n"];
				}

				$return['body'] = [
					'form' => [
						'data-usecase' => 'bundle',
						'action' => "javascript:api.form('post', 'bundle')"],
					'content' => [
						[
							[
								[
									'type' => 'datalist',
									'content' => $bundledatalist,
									'attributes' => [
										'id' => 'templates'
									]
								], [
									'type' => 'select',
									'attributes' => [
										'name' => LANG::GET('assemble.edit_existing_bundle_select'),
										'onchange' => "api.form('get', 'bundle', this.value)"
									],
									'content' => $options
								], [
									'type' => 'searchinput',
									'attributes' => [
										'name' => LANG::GET('assemble.edit_existing_bundle'),
										'list' => 'templates',
										'onkeypress' => "if (event.key === 'Enter') {api.form('get', 'bundle', this.value); return false;}"
									]
								]
							], [
								[
									'type' => 'select',
									'attributes' => [
										'name' => LANG::GET('assemble.edit_existing_bundle_all'),
										'onchange' => "api.form('get', 'bundle', this.value)"
									],
									'content' => $alloptions
								]
							]
						], [
							[
								'type' => 'textinput',
								'attributes' => [
									'name' => LANG::GET('assemble.edit_bundle_name'),
									'value' => $bundle['name'],
									'list' => 'templates',
									'required' => true,
									'data-loss' => 'prevent'
								],
								'hint' => ($bundle['name'] ? LANG::GET('assemble.compose_component_author', [':author' => $bundle['author'], ':date' => $bundle['date']]) . '<br>' : '')
							], [
								'type' => 'select',
								'attributes' => [
									'name' => LANG::GET('assemble.edit_bundle_insert_name'),
									'onchange' => "if (this.value.length > 1) _.insertChars(this.value, 'content'); this.selectedIndex = 0;"
								],
								'content' => $insertform
							], [
								'type' => 'textarea',
								'hint' => LANG::GET('assemble.edit_bundle_content_hint'),
								'attributes' => [
									'name' => LANG::GET('assemble.edit_bundle_content'),
									'value' => implode("\n", explode(",", $bundle['content'])),
									'rows' => 6,
									'id' => 'content',
									'required' => true,
									'data-loss' => 'prevent'
								]
							]
						]
					]
				];
				if ($bundle['id']){
					$hidden=[
						'type' => 'radio',
						'attributes' => [
							'name' => LANG::GET('assemble.edit_bundle_hidden')
						],
						'content' => [
							LANG::GET('assemble.edit_bundle_hidden_visible') => ['checked' => true],
							LANG::GET('assemble.edit_bundle_hidden_hidden') => []
						],
						'hint' => LANG::GET('assemble.edit_bundle_hidden_hint')
					];
					if ($bundle['hidden']) $hidden['content'][LANG::GET('assemble.edit_bundle_hidden_hidden')]['checked'] = true;
					array_push($return['body']['content'][1], $hidden);
				}

				$this->response($return);
				break;
		}
	}
}

$api = new FORMS();
$api->processApi();

exit;
?>
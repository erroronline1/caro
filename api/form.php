<?php
// add and edit form components and forms

class FORMS extends API {
   // processed parameters for readability
   public $_requestedMethod = REQUEST[1];
   private $_requestedID = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
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
			$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name-approved'));
			$statement->execute([
				':name' => $this->_requestedID
			]);
		}
		if (!$component = $statement->fetch(PDO::FETCH_ASSOC)) $component = ['id' => '', 'name' =>''];
		if($this->_requestedID && $this->_requestedID !== 'false' && !$component['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('assemble.error_component_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

		// prepare existing component lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-datalist'));
		$statement->execute();
		$components = $statement->fetchAll(PDO::FETCH_ASSOC);
		$hidden = [];
		foreach($components as $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!array_key_exists($row['name'], $options) && !in_array($row['name'], $hidden) && ($row['ceo_approval'] && $row['qmo_approval'] && $row['supervisor_approval'])) {
				$componentdatalist[] = $row['name'];
				$options[$row['name']] = ($row['name'] == $component['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
			}
			$approved = ($row['ceo_approval'] && $row['qmo_approval'] && $row['supervisor_approval']) ? LANG::GET('assemble.approve_approved') : LANG::GET('assemble.approve_unapproved');
			$alloptions[$row['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => $row['date']]) . ' - ' . $approved] = ($row['name'] == $component['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
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

		// prepare unit list for approval
		$approve = [
			'hint' => LANG::GET('assemble.compose_component_approve_hint'),
			'name' => LANG::GET('assemble.compose_component_approve'),
			'content' => ['...' . LANG::GET('assemble.compose_component_approve_select_default') => ['value' => '0']]
		];
		foreach(LANGUAGEFILE['units'] as $key => $value){
			$approve['content'][$value] = [];
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
					'hidden' => $component['name'] ? intval($component['hidden']) : 1,
					'approve' => $approve
				]],
				[[
					'type' => 'trash',
					'description' => LANG::GET('assemble.edit_trash')
				]]
			]
		];
		if ($component['name'] && (!$component['ceo_approval'] || !$component['qmo_approval'] || !$component['supervisor_approval']))
			$return['body']['content'][count($return['body']['content']) - 2][] = [
				[
					'type' => 'deletebutton',
					'attributes' => [
						'value' => LANG::GET('assemble.edit_delete'),
						'onpointerup' => "api.form('delete', 'component', " . $component['id'] . ")" 
					]
				]
			];

		if (array_key_exists('content', $component)) $return['body']['component'] = json_decode($component['content']);
		$this->response($return);
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
				$component_approve = $component['approve'];
				unset($component['approve']);

				// put hidden attribute if anything else remains the same
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name-approved'));
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
						':regulatory_context' => '',
						':id' => $exists['id']
						])) $this->response([
							'status' => [
								'name' => $component_name,
								'msg' => LANG::GET('assemble.edit_component_saved', [':name' => $component_name]),
								'type' => 'success'
							]]);	
				}

				if (!($component_approve = array_search($component_approve, LANGUAGEFILE['units']))) $this->response(['status' => ['msg' => LANG::GET('assemble.edit_component_not_saved_missing'), 'type' => 'error']]);

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $component_name, $matches)) $this->response(['status' => ['msg' => LANG::GET('assemble.error_forbidden_name', [':name' => $component_name]), 'type' => 'error']]);
				}
				// recursively replace images with actual $_FILES content according to content nesting
				if (array_key_exists('composedComponent_files', $_FILES)){
					$uploads = UTILITY::storeUploadedFiles(['composedComponent_files'], UTILITY::directory('component_attachments'), [$component_name . '_' . date('YmdHis')]);
					$files=[];
					foreach($uploads as $path){
						UTILITY::resizeImage($path, 2048, UTILITY_IMAGE_REPLACE);
						// retrieve actual filename with prefix dropped to compare to upload filename
						// boundary is underscore, actual underscores within uploaded file name will be reinserted
						$filename = implode('_', array_slice(explode('_', pathinfo($path)['basename']) , 2));
						$files[$filename] = substr($path, 1);
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
					':content' => json_encode($component),
					':regulatory_context' => ''
					])) {
						$message = LANG::GET('assemble.approve_component_request_alert', [':name' => $component_name]);
						$this->alertUserGroup(['permission' => ['supervisor'], 'unit' => [$component_approve]], $message);
						$this->alertUserGroup(['permission' => ['ceo', 'qmo']], $message);
						$this->response([
						'status' => [
							'name' => $component_name,
							'msg' => LANG::GET('assemble.edit_component_saved', [':name' => $component_name]),
							'reload' => 'component_editor',
							'type' => 'success'
						]]);
				}
				else $this->response([
					'status' => [
						'name' => false,
						'msg' => LANG::GET('assemble.edit_component_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				if (intval($this->_requestedID)){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_get'));
					$statement->execute([
						':id' => $this->_requestedID
					]);
				} else {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name-approved'));
					$statement->execute([
						':name' => $this->_requestedID
					]);
				}
				if ($component = $statement->fetch(PDO::FETCH_ASSOC)){
					$component['content'] = json_decode($component['content']);
					$this->response(['body' => $component, 'name' => $component['name']]);
				}
				$this->response(['status' => ['msg' => LANG::GET('assemble.error_component_not_found', [':name' => $this->_requestedID]), 'type' => 'error']]);
				break;
			case 'DELETE':
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_get'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				$component = $statement->fetch(PDO::FETCH_ASSOC);
				if ($component['ceo_approval'] && $component['qmo_approval'] && $component['supervisor_approval']) $this->response(['status' => ['msg' => LANG::GET('assemble.edit_component_delete_failure'), 'type' => 'error']]);
				
				// recursively check for identifier
				function deleteImages($element){
					foreach($element as $sub){
						if (array_is_list($sub)){
							deleteImages($sub);
						} else {
							if (array_key_exists('type', $sub) && $sub['type'] === 'image')
								UTILITY::delete('.' . $sub['attributes']['url']);
						}
					}
				}
				deleteImages(json_decode($component['content'], true)['content']);
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_delete'));
				if ($statement->execute([
					':id' => $this->_requestedID
				])) $this->response(['status' => [
					'msg' => LANG::GET('assemble.edit_component_delete_success'),
					'type' => 'success',
					'reload' => 'component_editor'
					]]);
				break;
		}
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
			'context' => '',
			'regulatory_context' => ''
		];
		if($this->_requestedID && $this->_requestedID !== 'false' && !$result['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('assemble.error_form_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

		// prepare existing forms lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-datalist'));
		$statement->execute();
		$fd = $statement->fetchAll(PDO::FETCH_ASSOC);
		$hidden = [];
		foreach($fd as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!array_key_exists($row['name'], $formoptions) && !in_array($row['name'], $hidden) && ($row['ceo_approval'] && $row['qmo_approval'] && $row['supervisor_approval'])) {
				$formdatalist[] = $row['name'];
				$formoptions[$row['name']] = ($row['name'] === $result['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
			}
			$approved = ($row['ceo_approval'] && $row['qmo_approval'] && $row['supervisor_approval']) ? LANG::GET('assemble.approve_approved') : LANG::GET('assemble.approve_unapproved');
			$alloptions[$row['name'] . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => $row['date']]) . ' - ' . $approved] = ($row['name'] === $result['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
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
				$approved = ($row['ceo_approval'] && $row['qmo_approval'] && $row['supervisor_approval']) ? LANG::GET('assemble.approve_approved') : LANG::GET('assemble.approve_unapproved');
				$componentoptions[$row['name'] . ' - ' . $approved] = ['value' => $row['id']];
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
		foreach(LANGUAGEFILE['formcontext'] as $type => $contexts){
			foreach($contexts as $context => $display){
				$contextoptions[$display] = $context===$result['context'] ? ['value' => $context, 'selected' => true] : ['value' => $context];
			}
		}

		// prepare unit list for approval
		$approve = [
			'hint' => LANG::GET('assemble.compose_component_approve_hint'),
			'name' => LANG::GET('assemble.compose_component_approve'),
			'content' => ['...' . LANG::GET('assemble.compose_component_approve_select_default') => ['value' => '0']]
		];
		foreach(LANGUAGEFILE['units'] as $key => $value){
			$approve['content'][$value] = [];
		}
		$regulatory_context = [];
		if ($result['regulatory_context']){
			foreach(explode(',', $result['regulatory_context']) as $context){
				$regulatory_context[] = LANGUAGEFILE['regulatory'][$context];
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
						'hidden' => $result['name'] ? intval($result['hidden']) : 1,
						'approve' => $approve,
						'regulatory_context' => $regulatory_context ? : ' '
					]
				], [
					[
						'type' => 'trash',
						'description' => LANG::GET('assemble.edit_trash')
					]
				]
			]
		];
		if ($result['name'] && (!$result['ceo_approval'] || !$result['qmo_approval'] || !$result['supervisor_approval']))
			$return['body']['content'][count($return['body']['content']) - 2][] = [
				[
					'type' => 'deletebutton',
					'attributes' => [
						'value' => LANG::GET('assemble.edit_delete'),
						'onpointerup' => "api.form('delete', 'form', " . $result['id'] . ")" 
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
				if ($component){
					$component['content'] = json_decode($component['content'], true);
					$component['content']['name'] = $usedcomponent;
					$component['content']['hidden'] = boolval(intval($component['hidden']));
					$return['body']['components'][] = $component['content'];
				}
			}
		}
		$this->response($return);
	}

	public function form(){
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				if (!(array_intersect(['admin'], $_SESSION['user']['permissions']))) $this->response([], 401);

				if (!$this->_payload->context) $this->response(['status' => ['msg' => LANG::GET("assemble.edit_form_not_saved_missing"), 'type' => 'error']]);
				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $this->_payload->name, $matches)) $this->response(['status' => ['msg' => LANG::GET('assemble.error_forbidden_name', [':name' => $this->_payload->name]), 'type' => 'error']]);
				}

				// recursively check for identifier
				function check4identifier($element){
					$hasindentifier = false;
					foreach($element as $sub){
						if (array_is_list($sub)){
							$hasindentifier = check4identifier($sub);
						} else {
							if (array_key_exists('type', $sub) && $sub['type'] === 'identify') $hasindentifier = true;
						}
					}
					return $hasindentifier;
				}
				// check for identifier if context makes it mandatory
				// do this in advance of updating in case of selecting such a context
				if (in_array($this->_payload->context, array_keys(LANGUAGEFILE['formcontext']['identify']))){
					$hasindentifier = false;
					foreach($this->_payload->content as $component){
						$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name'));
						$statement->execute([
							':name' => $component
						]);
						$latestcomponent = $statement->fetch(PDO::FETCH_ASSOC);
						if (check4identifier(json_decode($latestcomponent['content'], true)['content'])) $hasindentifier = true;
					}
					if (!$hasindentifier) $this->response(['status' => ['msg' => LANG::GET('assemble.compose_context_missing_identifier'), 'type' => 'error']]);
				}
				// convert values to keys for regulatory_context
				$rc = preg_split('/, /m', $this->_payload->regulatory_context);
				$regulatory_context = [];
				foreach($rc as $context){
					$regulatory_context[] = array_search($context, LANGUAGEFILE['regulatory']); 
				}
				
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
						':regulatory_context' => implode(',', $regulatory_context),
						':id' => $exists['id']
						])) $this->response([
							'status' => [
								'name' => $this->_payload->name,
								'msg' => LANG::GET('assemble.edit_form_saved', [':name' => $this->_payload->name]),
								'type' => 'success'
							]]);	
				}

				// if not updated check if approve is set, not earlier
				if (!in_array($this->_payload->approve, LANGUAGEFILE['units'])) $this->response(['status' => ['msg' => LANG::GET('assemble.edit_form_not_saved_missing'), 'type' => 'error']]);
				$this->_payload->approve = array_search($this->_payload->approve, LANGUAGEFILE['units']);

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_post'));
				if ($statement->execute([
					':name' => $this->_payload->name,
					':alias' => gettype($this->_payload->alias) === 'array' ? implode(' ', $this->_payload->alias): '',
					':context' => gettype($this->_payload->context) === 'array' ? '': $this->_payload->context,
					':author' => $_SESSION['user']['name'],
					':content' => implode(',', $this->_payload->content),
					':regulatory_context' => implode(',', $regulatory_context)
					])) {
						$message = LANG::GET('assemble.approve_form_request_alert', [':name' => $this->_payload->name]);
						$this->alertUserGroup(['permission' => ['supervisor'], 'unit' => [$this->_payload->approve]], $message);
						$this->alertUserGroup(['permission' => ['ceo', 'qmo']], $message);
						$this->response([
						'status' => [
							'name' => $this->_payload->name,
							'msg' => LANG::GET('assemble.edit_form_saved', [':name' => $this->_payload->name]),
							'reload' => 'form_editor',
							'type' => 'success'
						]]);
				}
				else $this->response([
					'status' => [
						'name' => false,
						'msg' => LANG::GET('assemble.edit_form_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'DELETE':
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_get'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				$component = $statement->fetch(PDO::FETCH_ASSOC);
				if ($component['ceo_approval'] && $component['qmo_approval'] && $component['supervisor_approval']) $this->response(['status' => ['msg' => LANG::GET('assemble.edit_form_delete_failure'), 'type' => 'error']]);
				
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_delete'));
				if ($statement->execute([
					':id' => $this->_requestedID
				])) $this->response(['status' => [
					'msg' => LANG::GET('assemble.edit_form_delete_success'),
					'type' => 'success',
					'reload' => 'form_editor',
					]]);
				break;
		}
	}

	public function approval(){
		if (!(array_intersect(['admin', 'ceo', 'qmo', 'supervisor'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'PUT':
				$approveas = UTILITY::propertySet($this->_payload, LANG::PROPERTY('assemble.approve_as_select'));
				if (!$approveas) $this->response([
					'status' => [
						'msg' => LANG::GET('assemble.approve_not_saved'),
						'type' => 'error'
					]]);
				$approveas = explode(', ', $approveas);

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_get'));
				$statement->execute([':id' => $this->_requestedID]);
				$approve = $statement->fetch(PDO::FETCH_ASSOC);
				$approval = json_encode(
					[
						'name' => $_SESSION['user']['name'],
						'date' => date('Y-m-d H:i:s')
					]
				);
				if (array_intersect(['admin', 'ceo'], $_SESSION['user']['permissions']) && in_array(LANG::GET('permissions.ceo'), $approveas)){
					$approve['ceo_approval'] = $approval;
				}
				if (array_intersect(['admin', 'qmo'], $_SESSION['user']['permissions']) && in_array(LANG::GET('permissions.qmo'), $approveas)){
					$approve['qmo_approval'] = $approval;
				}
				if (array_intersect(['admin', 'supervisor'], $_SESSION['user']['permissions']) && in_array(LANG::GET('permissions.supervisor'), $approveas)){
					$approve['supervisor_approval'] = $approval;
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_put-approve'));
				if ($statement->execute([
					':id' => $approve['id'],
					':ceo_approval' => $approve['ceo_approval'] ? : '',
					':qmo_approval' =>$approve['qmo_approval'] ? : '',
					':supervisor_approval' => $approve['supervisor_approval'] ? : ''
				])) $this->response([
						'status' => [
							'msg' => LANG::GET('assemble.approve_saved') . "<br />". ($approve['ceo_approval'] && $approve['qmo_approval'] && $approve['supervisor_approval'] ? LANG::GET('assemble.approve_completed') : LANG::GET('assemble.approve_pending')),
							'type' => 'success',
							'reload' => 'approval',
							]]);
				else $this->response([
					'status' => [
						'msg' => LANG::GET('assemble.approve_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$componentselection = $formselection = $approvalposition = [];

				// prepare all unapproved elements
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-datalist'));
				$statement->execute();
				$components = $statement->fetchAll(PDO::FETCH_ASSOC);
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_form-datalist'));
				$statement->execute();
				$forms = $statement->fetchAll(PDO::FETCH_ASSOC);
				$unapproved = ['forms' => [], 'components' => []];
				$return = ['body'=> ['content' => [[]]]]; // default first nesting
				$hidden = [];
				foreach(array_merge($components, $forms) as $element){
					if ($element['context'] === 'bundle') continue;
					if ($element['hidden']) $hidden[] = $element['context'] . $element['name']; // since ordered by recent, older items will be skipped
					if (!in_array($element['context'] . $element['name'], $hidden)){
						if (!$element['ceo_approval'] || !$element['qmo_approval'] || !$element['supervisor_approval']) {
							switch ($element['context']){
								case 'component':
									$sort = ['unapproved' => 'components', 'selection' => 'componentselection'];
									break;
								default:
								$sort = ['unapproved' => 'forms', 'selection' => 'formselection'];
							}						
							if (!in_array($element['name'], array_keys($unapproved[$sort['unapproved']]))){
								$unapproved[$sort['unapproved']][$element['name']] = $element['content'];
								if (array_intersect(['admin', 'ceo'], $_SESSION['user']['permissions']) && !$element['ceo_approval']){
									${$sort['selection']}[$element['name']] = $this->_requestedID === $element['id'] ? ['value' => $element['id'], 'selected' => true] : ['value' => $element['id']];
								}
								if (array_intersect(['admin', 'qmo'], $_SESSION['user']['permissions']) && !$element['qmo_approval']){
									${$sort['selection']}[$element['name']] = $this->_requestedID === $element['id'] ? ['value' => $element['id'], 'selected' => true] : ['value' => $element['id']];
								}
								if (array_intersect(['admin', 'supervisor'], $_SESSION['user']['permissions']) && !$element['supervisor_approval']){
									${$sort['selection']}[$element['name']] = $this->_requestedID === $element['id'] ? ['value' => $element['id'], 'selected' => true] : ['value' => $element['id']];
								}
							}
						}
						$hidden[] = $element['context'] . $element['name']; // hide previous versions at all costs
					}
				}

				if ($componentselection) $return['body']['content'][0][] = [
					'type' => 'select',
					'attributes' => [
						'name' => LANG::GET('assemble.approve_component_select'),
						'onchange' => "api.form('get', 'approval', this.value)"
					],
					'content' => $componentselection
				];
				if ($formselection) $return['body']['content'][0][] =
				[
					'type' => 'select',
					'attributes' => [
						'name' => LANG::GET('assemble.approve_form_select'),
						'onchange' => "api.form('get', 'approval', this.value)"
					],
					'content' => $formselection
				];
				if ($componentselection || $formselection) $return['body']['content'][] = [
					[
						'type' => 'hr'
					]
				];
				else $this->response(['body' => ['content' => $this->noContentAvailable(LANG::GET('assemble.approve_no_approvals'))]]);

				if ($this->_requestedID){
					$alert = '';
					// recursively delete required attributes
					function unrequire($element){
						$result = [];
						foreach($element as $sub){
							if (array_is_list($sub)){
								array_push($result, ...unrequire($sub));
							} else {
								if (array_key_exists('attributes', $sub)){
									unset ($sub['attributes']['required']);
									unset ($sub['attributes']['data-required']);
								}
								if ($sub) $result[] = $sub;
							}
						}
						return [$result];
					}

					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_get'));
					$statement->execute([':id' => $this->_requestedID]);
					$approve = $statement->fetch(PDO::FETCH_ASSOC);
					if (array_intersect(['admin', 'ceo'], $_SESSION['user']['permissions']) && !$approve['ceo_approval']){
						$approvalposition[LANG::GET('permissions.ceo')] = [];
					}
					if (array_intersect(['admin', 'qmo'], $_SESSION['user']['permissions']) && !$approve['qmo_approval']){
						$approvalposition[LANG::GET('permissions.qmo')] = [];
					}
					if (array_intersect(['admin', 'supervisor'], $_SESSION['user']['permissions']) && !$approve['supervisor_approval']){
						$approvalposition[LANG::GET('permissions.supervisor')] = [];
					}
					if ($approve['context'] === 'component'){
						array_push($return['body']['content'], ...unrequire(json_decode($approve['content'], true)['content'])[0]);
					}
					else {
						foreach(explode(',', $approve['content']) as $component){
							$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_component-get-latest-by-name'));
							$statement->execute([':name' => $component]);
							$cmpnnt = $statement->fetch(PDO::FETCH_ASSOC);
							if ($cmpnnt) {
								if (!($cmpnnt['ceo_approval'] && $cmpnnt['qmo_approval'] && $cmpnnt['supervisor_approval'])){
									$alert .= LANG::GET('assemble.approve_form_unapproved_component', [':name' => $cmpnnt['name']]). '<br />';
								}
								array_push($return['body']['content'], ...unrequire(json_decode($cmpnnt['content'], true)['content'])[0]);
							}
						}
						if ($alert) $return['status'] = ['msg' => $alert, 'type' => 'info'];
					}
					array_push($return['body']['content'], 
						[
							[
								'type' => 'hr'
							]
						], [
							[
								'type' => 'checkbox',
								'content' => $approvalposition,
								'description' => LANG::GET('assemble.approve_as_select')
							]
						]
					);
					$return['body']['form'] = [
						'data-usecase' => 'approval',
						'action' => "javascript: api.form('put', 'approval', " . $this->_requestedID . ")",
						'data-confirm' => true
					];
				}
				$this->response($return);
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
								'msg' => LANG::GET('assemble.edit_bundle_saved', [':name' => $bundle[':name']]),
								'type' => 'success'
							]]);	
				}

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $bundle[':name'], $matches)) $this->response(['status' => ['msg' => LANG::GET('assemble.error_forbidden_name', [':name' => $bundle[':name']]), 'type' => 'error']]);
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('form_post'));
				if ($statement->execute($bundle)) $this->response([
						'status' => [
							'name' => $bundle[':name'],
							'msg' => LANG::GET('assemble.edit_bundle_saved', [':name' => $bundle[':name']]),
							'type' => 'success'
						]]);
				else $this->response([
					'status' => [
						'name' => false,
						'msg' => LANG::GET('assemble.edit_bundle_not_saved'),
						'type' => 'error'
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
				if($this->_requestedID && $this->_requestedID !== 'false' && !$bundle['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('texttemplate.error_template_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
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
					if (!in_array($row['context'] , ['bundle']) && !in_array($row['name'], $hidden)) $insertform[$row['name']] = ['value' => $row['name'] . "\n"];
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
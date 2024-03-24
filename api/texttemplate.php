<?php
// add and edit text recommendations
class TEXTTEMPLATE extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
    private $_requestedID = null;
	private $_modal = null;

	public function __construct(){
		parent::__construct();
		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_modal = array_key_exists(3, REQUEST) ? REQUEST[3] : null;
	}

	public function chunk(){
		if (!(array_intersect(['admin', 'ceo', 'qmo'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$chunk = [
					':name' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('texttemplate.edit_chunk_name')),
					':unit' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('texttemplate.edit_chunk_unit')) ? : array_key_first(LANGUAGEFILE['units']),
					':author' => $_SESSION['user']['name'],
					':content' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('texttemplate.edit_chunk_content')),
					':language' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('texttemplate.edit_chunk_language')),
					':type' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('texttemplate.edit_chunk_type')),
					':hidden' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('texttemplate.edit_chunk_hidden')) === LANG::PROPERTY('texttemplate.edit_chunk_hidden_hidden')? 1 : 0,
				];

				if (!trim($chunk[':name']) || !trim($chunk[':content']) || !trim($chunk[':language']) || !$chunk[':type'] || $chunk[':type'] === '0') $this->response([], 400);
				preg_match("/[^\w\d]/m", $chunk[':name'], $matches);
				if ($matches) $this->response(['status' => ['msg' => LANG::GET('assemble.error_forbidden_name', [':name' => $chunk[':name']]), 'type' => 'error']]);

				// put hidden attribute if anything else remains the same
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate_get-latest-by-name'));
				$statement->execute([
					':name' => $chunk[':name']
				]);
				$exists = $statement->fetch(PDO::FETCH_ASSOC);
				if ($exists && $exists['content'] === $chunk[':content'] && $exists['language'] === $chunk[':language'] && $exists['type'] === $chunk[':type']) {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate-put'));
					if ($statement->execute([
						':hidden' => $chunk[':hidden'],
						':id' => $exists['id'],
						':unit' => $chunk[':unit']
						])) $this->response([
							'status' => [
								'name' => $chunk[':name'],
								'msg' => LANG::GET('texttemplate.edit_chunk_saved', [':name' => $chunk[':name']]),
								'type' => 'success'
							]]);	
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate-post'));
				if ($statement->execute($chunk)) $this->response([
						'status' => [
							'name' => $chunk[':name'],
							'msg' => LANG::GET('texttemplate.edit_chunk_saved', [':name' => $chunk[':name']]),
							'type' => 'success'
						]]);
				else $this->response([
					'status' => [
						'name' => false,
						'msg' => LANG::GET('texttemplate.edit_chunk_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$chunkdatalist = [];
				$options = ['...' . LANG::GET('texttemplate.edit_chunk_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$alloptions = ['...' . LANG::GET('texttemplate.edit_chunk_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$insertreplacement = ['...' . LANG::GET('texttemplate.edit_chunk_insert_default') => ['value' => ' ']];
				$languagedatalist = [];
				$return = [];

				// get selected chunk
				if (intval($this->_requestedID)){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate_get-chunk'));
					$statement->execute([
						':id' => $this->_requestedID
					]);
				} else {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate_get-latest-by-name'));
					$statement->execute([
						':name' => $this->_requestedID
					]);
				}
				if (!$chunk = $statement->fetch(PDO::FETCH_ASSOC)) $chunk = [
					'id' => '',
					'name' => '',
					'unit' => '',
					'content' => '',
					'language' => '',
					'type' => ''
				];
				if($this->_requestedID && $this->_requestedID !== 'false' && !$chunk['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('texttemplate.error_chunk_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing chunks lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate-datalist'));
				$statement->execute();
				$chunks = $statement->fetchAll(PDO::FETCH_ASSOC);
				$hidden = [];
				$dependedtemplates = [];
				foreach($chunks as $key => $row) {
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (in_array($row['type'], ['template', 'text'])){
						if (!in_array($row['name'], $dependedtemplates) && !in_array($row['name'], $hidden) && strpos($row['content'], $chunk['name']) !== false) {
							$dependedtemplates[] = $row['name'];
						}
					}
					if (!in_array($row['type'], ['replacement', 'text'])) continue;
					$display = LANG::GET('units.' . $row['unit']) . ' ' . LANG::GET('texttemplate.edit_chunk_types.' . $row['type']) . ' ' . $row['name'] . ' (' . $row['language'] . ')';

					if (!array_key_exists($display, $options) && !in_array($row['name'], $hidden)) {
						$chunkdatalist[] = $row['name'];
						$options[$display] = ($row['name'] == $chunk['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
						if ($row['type'] === 'replacement') $insertreplacement[LANG::GET('units.' . $row['unit']) . ' '. $row['name'] . ' (' . $row['language'] . ')'] = ['value' => ':' . $row['name']];
					}
					$alloptions[$display . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => $row['date']])] = ($row['name'] == $chunk['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
					if (!in_array($row['language'], $languagedatalist)) $languagedatalist[] = $row['language'];
				}

				$units = [];
				foreach (LANGUAGEFILE['units'] as $unit => $translation){
					$units[$translation] = ['value' => $unit];
					if ($chunk['unit'] == $unit) $units[$translation]['selected'] = true;
				}

				$return['body'] = [
					'form' => [
						'data-usecase' => 'texttemplate',
						'action' => "javascript:api.texttemplate('post', 'chunk')"],
					'content' => [
						[
							[
								[
									'type' => 'datalist',
									'content' => $chunkdatalist,
									'attributes' => [
										'id' => 'chunks'
									]
								], [
									'type' => 'datalist',
									'content' => $languagedatalist,
									'attributes' => [
										'id' => 'languages'
									]
								], [
									'type' => 'select',
									'attributes' => [
										'name' => LANG::GET('texttemplate.edit_chunk_select'),
										'onchange' => "api.texttemplate('get', 'chunk', this.value)"
									],
									'content' => $options
								], [
									'type' => 'searchinput',
									'attributes' => [
										'name' => LANG::GET('texttemplate.edit_chunk'),
										'list' => 'chunks',
										'onkeypress' => "if (event.key === 'Enter') {api.texttemplate('get', 'chunk', this.value); return false;}"
									]
								]
							], [
								[
									'type' => 'select',
									'attributes' => [
										'name' => LANG::GET('texttemplate.edit_chunk_all'),
										'onchange' => "api.texttemplate('get', 'chunk', this.value)"
									],
									'content' => $alloptions
								]
							]
						], [
							[
								'type' => 'textinput',
								'hint' => LANG::GET('texttemplate.edit_chunk_name_hint'),
								'attributes' => [
									'name' => LANG::GET('texttemplate.edit_chunk_name'),
									'value' => $chunk['name'],
									'pattern' => '[A-Za-z0-9]+',
									'required' => true,
									'data-loss' => 'prevent'
								]
							], [
								'type' => 'select',
								'attributes' => [
									'name' => LANG::GET('texttemplate.edit_chunk_type'),
									'onchange' => "document.getElementById('insertchunk').disabled = this.value == 'replacement';",
									'required' => true
								],
								'content' => [
									'...' . LANG::GET('texttemplate.edit_template_insert_default') => ['value' => '0'],
									LANG::GET('texttemplate.edit_chunk_types.replacement') => ['value' => 'replacement'],
									LANG::GET('texttemplate.edit_chunk_types.text') => ['value' => 'text'],
								]
							], [
								'type' => 'select',
								'attributes' => [
									'id' => 'insertchunk',
									'disabled' => $chunk['type'] !== 'text',
									'name' => LANG::GET('texttemplate.edit_chunk_insert_name'),
									'onchange' => "if (this.value.length > 1) _.insertChars(this.value, 'content'); this.selectedIndex = 0;"
								],
								'content' => $insertreplacement
							], [
								'type' => 'textarea',
								'hint' => LANG::GET('texttemplate.edit_chunk_content_hint', [':genus' => implode(', ', LANGUAGEFILE['texttemplate']['use_genus'])]),
								'attributes' => [
									'name' => LANG::GET('texttemplate.edit_chunk_content'),
									'value' => $chunk['content'],
									'rows' => 8,
									'id' => 'content',
									'required' => true,
									'data-loss' => 'prevent'
								]
							], [
								'type' => 'textinput',
								'attributes' => [
									'name' => LANG::GET('texttemplate.edit_chunk_language'),
									'list' => 'languages',
									'value' => $chunk['language'],
									'required' => true,
									'data-loss' => 'prevent'
								]
							], [
								'type' => 'select',
								'attributes' => [
									'name' => LANG::GET('texttemplate.edit_chunk_unit')
								],
								'content' => $units
							]
						]
					]
				];
				if ($chunk['type'] === 'text') $return['body']['content'][1][1]['content'][LANG::GET('texttemplate.edit_chunk_types.text')]['selected'] = true;
				if ($chunk['type'] === 'replacement') $return['body']['content'][1][1]['content'][LANG::GET('texttemplate.edit_chunk_types.replacement')]['selected'] = true;
				if ($chunk['id']){

					$hidden = [
						'type' => 'radio',
						'attributes' => [
							'name' => LANG::GET('texttemplate.edit_chunk_hidden')
						],
						'content' => [
							LANG::GET('texttemplate.edit_chunk_hidden_visible') => ['checked' => true],
							LANG::GET('texttemplate.edit_chunk_hidden_hidden') => []
						],
						'hint' => LANG::GET('texttemplate.edit_chunk_hidden_hint')
					];
					if ($chunk['hidden']) $hidden['content'][LANG::GET('texttemplate.edit_chunk_hidden_hidden')]['checked'] = true;
					if (count($dependedtemplates)) $hidden['hint'] = $hidden['hint'] . '\n' . LANG::GET('texttemplate.edit_chunk_dependencies', [':templates' => implode(', ', $dependedtemplates)]);
					array_push($return['body']['content'][1], $hidden);
				}
				$this->response($return);
				break;
		}					
	}

	public function template(){
		if (!(array_intersect(['admin', 'ceo', 'qmo'], $_SESSION['user']['permissions']))) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$template = [
					':name' => $this->_payload->name,
					':unit' => UTILITY::propertySet($this->_payload, 'unit') ? : array_key_first(LANGUAGEFILE['units']),
					':author' => $_SESSION['user']['name'],
					':content' => json_encode($this->_payload->content),
					':language' => $this->_payload->language,
					':type' => 'template',
					':hidden' => $this->_payload->hidden ? 1 : 0,
				];

				if (!trim($template[':name']) || !trim($template[':content']) || !trim($template[':language'])) $this->response([], 400);

				// put hidden attribute if anything else remains the same
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate_get-latest-by-name'));
				$statement->execute([
					':name' => $template[':name']
				]);
				$exists = $statement->fetch(PDO::FETCH_ASSOC);
				if ($exists && $exists['content'] === $template[':content']) {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate-put'));
					if ($statement->execute([
						':hidden' => $template[':hidden'],
						':id' => $exists['id'],
						'unit' => $template[':unit']
						])) $this->response([
							'status' => [
								'name' => $template[':name'],
								'msg' => LANG::GET('texttemplate.edit_template_saved', [':name' => $template[':name']]),
								'type' => 'success'
							]]);	
				}

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $template[':name'], $matches)) $this->response(['status' => ['msg' => LANG::GET('texttemplate.error_forbidden_name', [':name' => $template[':name']]), 'type' => 'error']]);
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate-post'));
				if ($statement->execute($template)) $this->response([
						'status' => [
							'name' => $template[':name'],
							'msg' => LANG::GET('texttemplate.edit_template_saved', [':name' => $template[':name']]),
							'type' => 'success'
						]]);
				else $this->response([
					'status' => [
						'name' => false,
						'msg' => LANG::GET('texttemplate.edit_template_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$templatedatalist = [];
				$options = ['...' . LANG::GET('texttemplate.edit_template_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$alloptions = ['...' . LANG::GET('texttemplate.edit_template_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$insertreplacement = ['...' . LANG::GET('texttemplate.edit_template_insert_default') => ['value' => ' ']];
				$languagedatalist = [];
				$return = [];

				// get selected template
				if (intval($this->_requestedID)){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate_get-chunk'));
					$statement->execute([
						':id' => $this->_requestedID
					]);
				} else {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate_get-latest-by-name'));
					$statement->execute([
						':name' => $this->_requestedID
					]);
				}
				if (!$template = $statement->fetch(PDO::FETCH_ASSOC)) $template = [
					'id' => '',
					'name' => '',
					'unit' => '',
					'content' => '',
					'language' => '',
					'type' => ''
				];
				if($this->_requestedID && $this->_requestedID !== 'false' && !$template['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('texttemplate.error_template_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing templates lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate-datalist'));
				$statement->execute();
				$templates = $statement->fetchAll(PDO::FETCH_ASSOC);
				$hidden = $chunks = [];
				foreach($templates as $key => $row) {
					if ($row['type'] === 'replacement') continue;
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if ($row['type'] === 'template'){
						$display = LANG::GET('units.' . $row['unit']) . ' ' . $row['name'] . ' (' . $row['language'] . ')';

						if (!array_key_exists($display, $options) && !in_array($row['name'], $hidden)) {
							$templatedatalist[] = $row['name'];
							$options[$display] = ($row['name'] == $template['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
						}
						$alloptions[$display . ' ' . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => $row['date']])] = ($row['name'] == $template['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
					}
					if ($row['type'] === 'text'){
						if (!array_key_exists(':' . $row['name'], $chunks) && !in_array($row['name'], $hidden)) {
							$insertreplacement[LANG::GET('units.' . $row['unit']) . ' ' . $row['name'] . ' - ' . substr($row['content'], 0, 50) . (strlen($row['content']) > 50 ? '...' : '')] = ['value' => ':' . $row['name']];
							$chunks[':' . $row['name']] = $row['content'];
						}
					}
					if (!in_array($row['language'], $languagedatalist)) $languagedatalist[] = $row['language'];
				}

				$units = [];
				foreach (LANGUAGEFILE['units'] as $unit => $translation){
					$units[$translation] = ['value' => $unit];
					if ($template['unit'] == $unit) $units[$translation]['selected'] = true;
				}

				$return['data'] = $chunks;
				$return['selected'] = $template['content'] ? json_decode($template['content'], true): [];
				$return['body'] = [
					'content' => [
						[
							[
								[
									'type' => 'datalist',
									'content' => $templatedatalist,
									'attributes' => [
										'id' => 'templates'
									]
								], [
									'type' => 'datalist',
									'content' => $languagedatalist,
									'attributes' => [
										'id' => 'languages'
									]
								], [
									'type' => 'select',
									'attributes' => [
										'name' => LANG::GET('texttemplate.edit_template_select'),
										'onchange' => "api.texttemplate('get', 'template', this.value)"
									],
									'content' => $options
								], [
									'type' => 'searchinput',
									'attributes' => [
										'name' => LANG::GET('texttemplate.edit_template'),
										'list' => 'templates',
										'onkeypress' => "if (event.key === 'Enter') {api.texttemplate('get', 'template', this.value); return false;}"
									]
								]
							], [
								[
									'type' => 'select',
									'attributes' => [
										'name' => LANG::GET('texttemplate.edit_template_all'),
										'onchange' => "api.texttemplate('get', 'template', this.value)"
									],
									'content' => $alloptions
								]
							]
						], [
							[
								'type' => 'textinput',
								'attributes' => [
									'name' => LANG::GET('texttemplate.edit_template_name'),
									'value' => $template['name'],
									'id' => 'TemplateName',
									'required' => true,
									'pattern' => '[A-Za-z0-9_]',
									'data-loss' => 'prevent'
								]
							], [
								'type' => 'select',
								'attributes' => [
									'name' => LANG::GET('texttemplate.edit_template_insert_name'),
									'onchange' => "if (this.value.length > 1) compose_helper.composeNewTextTemplateCallback(this.value);"
								],
								'content' => $insertreplacement
							], [
								'type' => 'textinput',
								'attributes' => [
									'name' => LANG::GET('texttemplate.edit_template_language'),
									'list' => 'languages',
									'value' => $template['language'],
									'id' => 'TemplateLanguage',
									'required' => true,
									'data-loss' => 'prevent'
								]
							], [
								'type' => 'select',
								'attributes' => [
									'name' => LANG::GET('texttemplate.edit_chunk_unit'),
									'id' => 'TemplateUnit'
								],
								'content' => $units
							], [
								'type' => 'button',
								'attributes' => [
									'value' => LANG::GET('texttemplate.edit_template_save'),
									'type' => 'button',
									'onpointerup' => "new Dialog({type: 'confirm', header: '" .
										LANG::GET("texttemplate.edit_template_save") .
										"', options:{" .
										"'" .
										LANG::GET("assemble.compose_form_cancel") .
										"': false," .
										"'" .
										LANG::GET("assemble.compose_form_confirm") .
										"': {value: true, class: 'reducedCTA'}," .
										"}}).then(confirmation => {if (confirmation) api.texttemplate('post', 'template')})",
						
								]
							]
						], [
							[
								'type' => 'trash',
								'description' => LANG::GET('assemble.edit_trash')
							]
						]
					]
				];
				if ($template['id']){
					$hidden=[
						'type' => 'radio',
						'attributes' => [
							'name' => LANG::GET('texttemplate.edit_template_hidden')
						],
						'content' => [
							LANG::GET('texttemplate.edit_template_hidden_visible') => ['checked' => true],
							LANG::GET('texttemplate.edit_template_hidden_hidden') => ['data-hiddenradio'=>'ComponentHidden']
						],
						'hint' => LANG::GET('texttemplate.edit_template_hidden_hint')
					];
					if ($template['hidden']) $hidden['content'][LANG::GET('texttemplate.edit_template_hidden_hidden')]['checked'] = true;
					array_push($return['body']['content'][1], $hidden);
				}

				$this->response($return);
				break;
		}
	}

	public function text(){
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);
		$templatedatalist = $options = $return = $hidden = $texts = $replacements = [];

		// get selected template
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate_get-chunk'));
		$statement->execute([
			':id' => intval($this->_requestedID)
		]);
		if (!$template = $statement->fetch(PDO::FETCH_ASSOC)) $template = [
			'name' => '',
		];

		if($this->_requestedID && $this->_requestedID !== 'false' && !$template['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('texttemplate.error_template_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

		// prepare existing templates lists
		$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('texttemplate-datalist'));
		$statement->execute();
		$templates = $statement->fetchAll(PDO::FETCH_ASSOC);
		if (!$templates) {
			$result['body']['content'] = $this->noContentAvailable(LANG::GET('message.no_messages'));
			$this->response($result);		
		}

		foreach($templates as $key => $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if ($row['type'] !== 'template' && !in_array($row['name'], $hidden)) {
				// prepare in case of template request
				// set up array for strtr on content
				if ($row['type']==='text' && !array_key_exists(':' . $row['name'], $texts)) $texts[':' . $row['name']] = $row['content'] . ' ';
				// set up array with valid replacements
				if ($row['type']==='replacement' && !array_key_exists(':' . $row['name'], $replacements)) $replacements[':' . $row['name']] = $row['content'];
				// skip for datalist and options 
				continue;
			}
			if ($row['type'] !== 'template') continue;
			if (!in_array($row['name'], $hidden)) {
				if (!in_array($row['unit'], $options)) $options[$row['unit']] = ['...' => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				if (!array_key_exists($row['name'] . ' (' . $row['language'] . ')', $options[$row['unit']])){
					$templatedatalist[] = $row['name'];
					$options[$row['unit']][$row['name'] . ' (' . $row['language'] . ')'] = ($row['name'] == $template['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
				}
			}
		}
		$return['body'] = ['content' => [[]]];

		foreach ($options as $unit => $templates) {
			$return['body']['content'][0][] = [
				[
					'type' => 'select',
					'attributes' => [
						'name' => LANG::GET('texttemplate.use_text_select', [':unit' => LANGUAGEFILE['units'][$unit]]),
						'onchange' => "api.texttemplate('get', 'text', this.value" . ($this->_modal ? ", 'modal')" : ")")
					],
					'content' => $templates
				]
			];
		}
		
		if ($template['name']){
			$inputs = $undefined = [];

			$usegenus = [];
			foreach(LANGUAGEFILE['texttemplate']['use_genus'] as $key => $genus){
				$usegenus[$genus] = ['value' => $key, 'data-loss' => 'prevent'];
			}
			$inputs[] = [
				'type' => 'radio',
				'attributes' => [
					'name' => LANG::GET('texttemplate.use_person'),
					'id' => 'genus'
				],
				'content' => $usegenus
			];

			// match and replace placeholders and add paragraph linebreaks
			$content = '';
			foreach(json_decode($template['content']) as $paragraph){
				foreach($paragraph as $chunk){
					$add = $texts[$chunk];
					preg_match_all('/(:[\w\d]+?)(?:\W|$)/m', $add, $placeholders);
					foreach($placeholders[1] as $ph){
						if (!array_key_exists($ph, $replacements)) array_push($undefined, $ph);
					}
					$content .= $add;
				}
				$content .= "\n\n";
				$texts[$paragraph[count($paragraph)-1]] .= "\n\n";
			}
			// add input fields for undefined placeholders
			foreach ($undefined as $placeholder) {
				$inputs[] = [
					'type' => 'textinput',
					'attributes' => [
						'name' => LANG::GET('texttemplate.use_fill_placeholder') . ' ' . $placeholder,
						'id' => preg_replace('/\W/', '', $placeholder),
						'data-usecase' => 'undefinedplaceholder',
						'data-loss' => 'prevent'
					]
				];
			}

			foreach (json_decode($template['content']) as $block){
				$useblocks = [];
				foreach($block as $key => $value){
					$useblocks[preg_replace('/\W/', '', $value)] = ['checked' => true, 'data-usecase' => 'useblocks', 'data-loss' => 'prevent'];
				}
				if (count($useblocks)) $inputs[] = [
					'type' => 'checkbox',
					'description' => LANG::GET('texttemplate.use_blocks'),
					'content' => $useblocks
				];
			}
			$inputs[] = [
				'type' => 'button',
				'attributes' => [
					'type' => 'button',
					'value' => LANG::GET('texttemplate.use_refresh'),
					'onpointerup' => 'texttemplateClient.update();'
				],
				'hint' => LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => $row['date']])
			];
			$return['body']['content'][] = $inputs;

			$return['body']['content'][]=[
				[
					'type' => 'textarea',
					'attributes' => [
						'id' => 'texttemplate',
						'name' => LANG::GET('texttemplate.use_template'),
						'value' => $content,
						'rows' => 13,
						'readonly' => true,
						'onpointerup' => 'orderClient.toClipboard(this.value)'
						]
				]
			];
			$return['data'] = ['blocks' => $texts, 'replacements' => $replacements];
		}
		$this->response($return);
	}
}

$api = new TEXTTEMPLATE();
$api->processApi();

exit;
?>
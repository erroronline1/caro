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

// add and edit text recommendations
class TEXTTEMPLATE extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
    private $_requestedID = null;
	private $_modal = null;

	public function __construct(){
		parent::__construct();
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);

		$this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
		$this->_modal = array_key_exists(3, REQUEST) ? REQUEST[3] : null;
	}

	public function chunk(){
		if (!PERMISSION::permissionFor('texttemplates')) $this->response([], 401);
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
				$exists = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_get_latest_by_name', [
					'values' => [
						':name' => $chunk[':name']
					]
				]);
				$exists = $exists ? $exists[0] : null;
				if ($exists && $exists['content'] === $chunk[':content'] && $exists['language'] === $chunk[':language'] && $exists['type'] === $chunk[':type']) {
					if (SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_put', [
						'values' => [
							':hidden' => $chunk[':hidden'],
							':id' => $exists['id'],
							':unit' => $chunk[':unit']
						]
					])) $this->response([
							'status' => [
								'name' => $chunk[':name'],
								'msg' => LANG::GET('texttemplate.edit_chunk_saved', [':name' => $chunk[':name']]),
								'type' => 'success'
							]]);	
				}

				if (SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_post', [
					'values' => $chunk
				])) $this->response([
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
					$chunk = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_get_chunk', [
						'values' => [
							':id' => intval($this->_requestedID)
						]
					]);
				} else {
					$chunk = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_get_latest_by_name', [
						'values' => [
							':name' => $this->_requestedID
						]
					]);
				}
				$chunk = $chunk ? $chunk[0] : null;
				if (!$chunk) $chunk = [
					'id' => '',
					'name' => '',
					'unit' => '',
					'content' => '',
					'language' => '',
					'type' => ''
				];
				if($this->_requestedID && $this->_requestedID !== 'false' && !$chunk['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('texttemplate.error_chunk_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing chunks lists
				$chunks = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_datalist');
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
									'content' => array_values(array_unique($chunkdatalist)),
									'attributes' => [
										'id' => 'chunks'
									]
								], [
									'type' => 'datalist',
									'content' => array_values(array_unique($languagedatalist)),
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
		if (!PERMISSION::permissionFor('texttemplates')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$template = [
					':name' => UTILITY::propertySet($this->_payload, 'name'),
					':unit' => UTILITY::propertySet($this->_payload, 'unit') ? : array_key_first(LANGUAGEFILE['units']),
					':author' => $_SESSION['user']['name'],
					':content' => json_encode($this->_payload->content),
					':language' => UTILITY::propertySet($this->_payload, 'language'),
					':type' => 'template',
					':hidden' => $this->_payload->hidden ? 1 : 0,
				];

				if (!trim($template[':name']) || !trim($template[':content']) || !trim($template[':language'])) $this->response([], 400);

				// put hidden attribute if anything else remains the same
				$exists = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_get_latest_by_name', [
					'values' => [
						':name' => $template[':name']
					]
				]);
				$exists = $exists ? $exists[0] : null;
				if ($exists && $exists['content'] === $template[':content']) {
					if (SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_put', [
						'values' => [
							':hidden' => $template[':hidden'],
							':id' => $exists['id'],
							'unit' => $template[':unit']
						]
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

				if (SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_post', [
					'values' => $template])) $this->response([
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
					$template = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_get_chunk', [
						'values' => [
							':id' => intval($this->_requestedID)
						]
					]);
				} else {
					$template = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_get_latest_by_name', [
						'values' => [
							':name' => $this->_requestedID
						]
					]);
				}
				$template = $template ? $template[0] : null;
				if (!$template) $template = [
					'id' => '',
					'name' => '',
					'unit' => '',
					'content' => '',
					'language' => '',
					'type' => ''
				];
				if($this->_requestedID && $this->_requestedID !== 'false' && !$template['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('texttemplate.error_template_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing templates lists
				$templates = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_datalist');
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
									'content' => array_values(array_unique($templatedatalist)),
									'attributes' => [
										'id' => 'templates'
									]
								], [
									'type' => 'datalist',
									'content' => array_values(array_unique($languagedatalist)),
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
				if ($template['name']) $return['header'] = $template['name'];
				$this->response($return);
				break;
		}
	}

	public function text(){
		$templatedatalist = $options = $return = $hidden = $texts = $replacements = [];

		// get selected template
		$template = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_get_chunk', [
			'values' => [
				':id' => intval($this->_requestedID)
			]
		]);
		$template = $template ? $template[0] : null;
		if (!$template) $template = [
			'name' => '',
		];

		if($this->_requestedID && $this->_requestedID !== 'false' && !$template['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('texttemplate.error_template_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

		// prepare existing templates lists
		$templates = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_datalist');
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
					'onpointerup' => '_client.texttemplate.update();'
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
						'onpointerup' => '_client.order.toClipboard(this.value)'
						]
				]
			];
			$return['data'] = ['blocks' => $texts, 'replacements' => $replacements];
		}
		$this->response($return);
	}
}
?>
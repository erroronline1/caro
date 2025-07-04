<?php
/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
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

namespace CARO\API;

// add and edit text recommendations
class TEXTTEMPLATE extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
    private $_requestedID = null;
	private $_modal = null;
	private $_clientimport = null;

	public function __construct(){
		parent::__construct();
		if (!isset($_SESSION['user']) || array_intersect(['patient'], $_SESSION['user']['permissions'])) $this->response([], 401);

		$this->_requestedID = isset(REQUEST[2]) ? REQUEST[2] : null;
		$this->_modal = isset(REQUEST[3]) ? REQUEST[3] : null;
		$this->_clientimport = isset(REQUEST[4]) ? REQUEST[4] : null;
	}

	/**
	 *       _           _
	 *   ___| |_ _ _ ___| |_
	 *  |  _|   | | |   | '_|
	 *  |___|_|_|___|_|_|_,_|
	 *
	 * edit text chunks
	 */
	public function chunk(){
		if (!PERMISSION::permissionFor('texttemplates')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				// set up chunk
				$chunk = [
					':name' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('texttemplate.chunk.name')),
					':unit' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('texttemplate.chunk.unit')) ? : array_key_first($this->_lang->_USER['units']),
					':author' => $_SESSION['user']['name'],
					':content' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('texttemplate.chunk.content')),
					':language' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('texttemplate.chunk.language')),
					':type' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('texttemplate.chunk.type')),
					':hidden' => UTILITY::propertySet($this->_payload, $this->_lang->PROPERTY('texttemplate.chunk.availability')) === $this->_lang->PROPERTY('texttemplate.chunk.hidden')? UTILITY::json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_date['servertime']->format('Y-m-d H:i:s')]) : null,
				];

				// check forbidden names
				if (!trim($chunk[':name']) || !trim($chunk[':content']) || !trim($chunk[':language']) || !$chunk[':type'] || $chunk[':type'] === '0') $this->response([], 400);
				// modify ([^\w\s\d\.\[\]\(\)\-ÄÖÜäöüß])
				// unset types and escaped literals
				$pattern = preg_replace('/\\\./m', '', CONFIG['forbidden']['names']['characters']);
				// readd some types
				$pattern = substr_replace($pattern, '\\w\\d', -2, 0);
				// add multiplier
				$pattern = substr_replace($pattern, '+?', -1, 0);

				foreach ([...CONFIG['forbidden']['names'], $pattern] as $pattern){
					if (preg_match("/" . $pattern . "/m", $chunk[':name'], $matches)) $this->response(['response' => ['msg' => $this->_lang->GET('texttemplate.error_forbidden_name', [':name' => $chunk[':name']]) . ' - ' . $pattern, 'type' => 'error']]);
				}

				$exists = null;
				$all = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_datalist');
				foreach ($all as $entry){
					if ($entry['type'] === 'template') continue;
					if ($entry['name'] !== $chunk[':name'] && (str_starts_with($entry['name'], $chunk[':name']) || str_starts_with($chunk[':name'], $entry['name']))) $this->response(['response' => ['msg' => $this->_lang->GET('texttemplate.error_name_taken'), 'type' => 'error']]);
					if ($entry['name'] == $chunk[':name']){
						$exists = $entry;
						break;
					}
				}

				// put hidden attribute if anything else remains the same
				if ($exists && $exists['content'] === $chunk[':content'] && $exists['type'] === $chunk[':type']) {
					if (SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_put', [
						'values' => [
							':hidden' => $chunk[':hidden'],
							':id' => $exists['id'],
							':unit' => $chunk[':unit']
						]
					])) $this->response([
							'response' => [
								'name' => $chunk[':name'],
								'msg' => $this->_lang->GET('texttemplate.chunk.saved', [':name' => $chunk[':name']]),
								'type' => 'success'
							]]);	
				}
				// else post new chunk
				if (SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_post', [
					'values' => $chunk
				])) $this->response([
					'response' => [
						'name' => $chunk[':name'],
						'msg' => $this->_lang->GET('texttemplate.chunk.saved', [':name' => $chunk[':name']]),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'name' => false,
						'msg' => $this->_lang->GET('texttemplate.chunk.not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$chunkdatalist = $replacements = $options = $alloptions = [];
				$insertreplacement = ['...' . $this->_lang->GET('texttemplate.chunk.insert_default') => ['value' => ' ']];
				$response = [];

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
				// set up chunk
				if (!$chunk) $chunk = [
					'id' => '',
					'name' => '',
					'unit' => '',
					'content' => '',
					'type' => ''
				];
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$chunk['name'] && $this->_requestedID !== '0') $response['response'] = ['msg' => $this->_lang->GET('texttemplate.chunk.error_chunk_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing chunks lists
				$chunks = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_datalist');
				$hidden = [];
				$dependedtemplates = [];
				foreach ($chunks as $key => $row) {
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (in_array($row['type'], ['template', 'text'])){
						if (!in_array($row['name'], $dependedtemplates) && !in_array($row['name'], $hidden) && strpos($row['content'], $chunk['name']) !== false) {
							$dependedtemplates[] = $row['name'];
						}
					}
					if (!in_array($row['type'], ['replacement', 'text'])) continue;
					$display = $this->_lang->GET('texttemplate.chunk.types.' . $row['type']) . ' ' . $row['name'];

					// one selection per unit
					if (!in_array($row['unit'], array_keys($options))) $options[$row['unit']] = ['...' . $this->_lang->GET('texttemplate.template.new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
					if (!in_array($row['unit'], array_keys($alloptions))) $alloptions[$row['unit']] = ['...' . $this->_lang->GET('texttemplate.template.new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];

					if (!isset($options[$row['unit']][$display]) && !in_array($row['name'], $hidden)) {
						$chunkdatalist[] = $row['name'];
						$options[$row['unit']][$display] = ($row['name'] == $chunk['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
						if ($row['type'] === 'replacement') {
							$insertreplacement[$this->_lang->GET('units.' . $row['unit']) . ' '. $row['name']] = ['value' => ':' . $row['name']];
							$replacements[] = ':' . $row['name'];
						}
					}

					$display .= ' ' . $this->_lang->GET('assemble.compose.component.author', [':author' => $row['author'], ':date' => $this->convertFromServerTime($row['date'])]);
					if ($row['hidden']) $display = UTILITY::hiddenOption($display);
					$alloptions[$row['unit']][$display] = ($row['name'] == $chunk['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
				}
				// one selection per unit
				$renderoptions = [];
				foreach ($options as $unit => &$templates){
					ksort($templates);
					$renderoptions[] = [
						'type' => 'select',
						'numeration' => 'prevent',
						'attributes' => [
							'name' => $this->_lang->GET('texttemplate.chunk.select', [':unit' => $this->_lang->_USER['units'][$unit]]),
							'onchange' => "api.texttemplate('get', 'chunk', this.value" . ($this->_modal ? ", 'modal'" : "") . ($this->_modal && $this->_clientimport ? ", '" . $this->_clientimport . "'" : "") . ")"
						],
						'content' => $templates
					];
				}
				// one selection per unit
				$renderalloptions = [];
				foreach ($alloptions as $unit => &$templates){
					$renderalloptions[] = [
						'type' => 'select',
						'numeration' => 'prevent',
						'attributes' => [
							'name' => $this->_lang->GET('texttemplate.chunk.all', [':unit' => $this->_lang->_USER['units'][$unit]]),
							'onchange' => "api.texttemplate('get', 'chunk', this.value" . ($this->_modal ? ", 'modal'" : "") . ($this->_modal && $this->_clientimport ? ", '" . $this->_clientimport . "'" : "") . ")"
						],
						'content' => $templates
					];
				}

				foreach ($this->_lang->_USER['texttemplate']['system'] as $key => $value){
					$insertreplacement[$value] = ['value' => ':' . $key];
				}
				ksort($insertreplacement);

				$units = [];
				foreach ($this->_lang->_USER['units'] as $unit => $translation){
					$units[$translation] = ['value' => $unit];
					if ($chunk['unit'] == $unit) $units[$translation]['selected'] = true;
				}

				$response['render'] = [
					'form' => [
						'data-usecase' => 'texttemplate',
						'action' => "javascript:api.texttemplate('post', 'chunk')"],
					'content' => [
						[
							[
								...$renderoptions,
								[
									'type' => 'search',
									'numeration' => 'prevent',
									'attributes' => [
										'name' => $this->_lang->GET('texttemplate.chunk.edit_chunk'),
										'onkeydown' => "if (event.key === 'Enter') {api.texttemplate('get', 'chunk', this.value); return false;}"
									],
									'datalist' => array_values(array_unique($chunkdatalist))
								]
							],
							[
								...$renderalloptions,
								[
									'type' => 'search',
									'numeration' => 'prevent',
									'attributes' => [
										'name' => $this->_lang->GET('texttemplate.chunk.edit_chunk'),
										'onkeydown' => "if (event.key === 'Enter') {api.texttemplate('get', 'chunk', this.value); return false;}"
									],
									'datalist' => array_values(array_unique($chunkdatalist))
								]
							]
						], [
							[
								'type' => 'text',
								'hint' => $this->_lang->GET('texttemplate.chunk.name_hint'),
								'attributes' => [
									'name' => $this->_lang->GET('texttemplate.chunk.name'),
									'value' => $chunk['name'],
									'pattern' => '[A-Za-z0-9]+',
									'required' => true,
									'data-loss' => 'prevent'
								]
							], [
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('texttemplate.chunk.type'),
									'onchange' => "document.getElementById('insertchunk').disabled = this.value == 'replacement';",
									'required' => true
								],
								'content' => [
									'...' . $this->_lang->GET('texttemplate.template.insert_default') => ['value' => '0'],
									$this->_lang->GET('texttemplate.chunk.types.replacement') => ['value' => 'replacement'],
									$this->_lang->GET('texttemplate.chunk.types.text') => ['value' => 'text'],
								]
							], [
								'type' => 'select',
								'attributes' => [
									'id' => 'insertchunk',
									'disabled' => $chunk['type'] !== 'text',
									'name' => $this->_lang->GET('texttemplate.chunk.insert_name'),
									'onchange' => "if (this.value.length > 1) _.insertChars(this.value, 'content'); this.selectedIndex = 0;"
								],
								'content' => $insertreplacement
							], [
								'type' => 'textarea',
								'hint' => $this->_lang->GET('texttemplate.chunk.content_hint', [':genus' => implode(', ', $this->_lang->_USER['texttemplate']['use']['genus'])]),
								'attributes' => [
									'name' => $this->_lang->GET('texttemplate.chunk.content'),
									'value' => $chunk['content'],
									'rows' => 8,
									'id' => 'content',
									'required' => true,
									'data-loss' => 'prevent'
								],
								'autocomplete' => $replacements
							], [
								'type' => 'select',
								'attributes' => [
									'name' => $this->_lang->GET('texttemplate.chunk.unit')
								],
								'content' => $units
							]
						]
					]
				];

				if ($chunk['type'] === 'text') $response['render']['content'][1][1]['content'][$this->_lang->GET('texttemplate.chunk.types.text')]['selected'] = true;
				if ($chunk['type'] === 'replacement') $response['render']['content'][1][1]['content'][$this->_lang->GET('texttemplate.chunk.types.replacement')]['selected'] = true;
				if ($chunk['id']){
					$hidden = [
						'type' => 'radio',
						'attributes' => [
							'name' => $this->_lang->GET('texttemplate.chunk.availability')
						],
						'content' => [
							$this->_lang->GET('texttemplate.chunk.available') => ['checked' => true],
							$this->_lang->GET('texttemplate.chunk.hidden') => ['class' => 'red']
						],
						'hint' => $this->_lang->GET('texttemplate.chunk.availability_hint')
					];
					if ($chunk['hidden']) {
						$hidden['content'][$this->_lang->GET('texttemplate.chunk.hidden')]['checked'] = true;
						$hiddenproperties = json_decode($chunk['hidden'], true);
						$hidden['hint'] .= ' ' . $this->_lang->GET('texttemplate.edit_hidden_set', [':date' => $this->convertFromServerTime($hiddenproperties['date']), ':name' => $hiddenproperties['name']]);
					}
					if (count($dependedtemplates)) $hidden['hint'] = $hidden['hint'] . '\n' . $this->_lang->GET('texttemplate.chunk.dependencies', [':templates' => implode(', ', $dependedtemplates)]);
					array_push($response['render']['content'][1], $hidden);
				}
				$this->response($response);
				break;
		}					
	}

	/**
	 *   _                 _     _
	 *  | |_ ___ _____ ___| |___| |_ ___
	 *  |  _| -_|     | . | | .'|  _| -_|
	 *  |_| |___|_|_|_|  _|_|__,|_| |___|
	 *                |_|
	 * edit templates
	 */
	public function template(){
		if (!PERMISSION::permissionFor('texttemplates')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				//set up template
				$template = [
					':name' => UTILITY::propertySet($this->_payload, 'name'),
					':unit' => UTILITY::propertySet($this->_payload, 'unit') ? : array_key_first($this->_lang->_USER['units']),
					':author' => $_SESSION['user']['name'],
					':content' => UTILITY::propertySet($this->_payload, 'content'),
					':type' => 'template',
					':hidden' => UTILITY::propertySet($this->_payload, 'hidden') && $this->_payload->hidden !== 'false' ? UTILITY::json_encode(['name' => $_SESSION['user']['name'], 'date' => $this->_date['servertime']->format('Y-m-d H:i:s')]) : null,
				];

				if (!trim($template[':name']) || !trim($template[':content']) || !trim($template[':language'])) $this->response([], 400);

				// put hidden attribute if anything else remains the same
				$exists = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_get_latest_by_name', [
					'values' => [
						':name' => $template[':name']
					]
				]);
				foreach ($exists as $row => $entry){
					if ($entry['type'] !== 'template') unset($exists[$row]);
				}
				$exists = $exists ? array_values($exists)[0] : null;
				if ($exists && $exists['content'] === $template[':content']) {
					if (SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_put', [
						'values' => [
							':hidden' => $template[':hidden'],
							':id' => $exists['id'],
							'unit' => $template[':unit']
						]
					])) $this->response([
						'response' => [
							'name' => $template[':name'],
							'msg' => $this->_lang->GET('texttemplate.template.saved', [':name' => $template[':name']]),
							'type' => 'success'
						]]);	
				}
				//check forbidden names
				if ($pattern = UTILITY::forbiddenName($template[':name'])) $this->response(['response' => ['msg' => $this->_lang->GET('texttemplate.error_forbidden_name', [':name' => $template[':name']]) . ' - ' . $pattern, 'type' => 'error']]);

				// else post new template
				if (SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_post', [
					'values' => $template])) $this->response([
					'response' => [
						'name' => $template[':name'],
						'msg' => $this->_lang->GET('texttemplate.template.saved', [':name' => $template[':name']]),
						'type' => 'success'
					]]);
				else $this->response([
					'response' => [
						'name' => false,
						'msg' => $this->_lang->GET('texttemplate.template.not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$templatedatalist = [];
				$options = [];
				$alloptions = [];
				$insertreplacement = [];
				$response = [];

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
				//set up template
				if (!$template) $template = [
					'id' => '',
					'name' => '',
					'unit' => '',
					'content' => '',
					'type' => ''
				];
				if ($this->_requestedID && $this->_requestedID !== 'false' && !$template['name'] && $this->_requestedID !== '0') $response['response'] = ['msg' => $this->_lang->GET('texttemplate.template.error_template_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing templates lists
				$templates = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_datalist');
				$hidden = $chunks = [];
				foreach ($templates as $row) {
					if ($row['type'] === 'replacement') continue;
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if ($row['type'] === 'template'){
						$display = $row['name'];
						// one selection per unit
						if (!in_array($row['unit'], array_keys($options))) $options[$row['unit']] = ['...' . $this->_lang->GET('texttemplate.template.new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
						if (!in_array($row['unit'], array_keys($alloptions))) $alloptions[$row['unit']] = ['...' . $this->_lang->GET('texttemplate.template.new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];

						if (!isset($options[$row['unit']][$display]) && !in_array($row['name'], $hidden)) {
							$templatedatalist[] = $row['name'];
							$options[$row['unit']][$display] = ($row['name'] == $template['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
						}
						$display .= ' ' . $this->_lang->GET('assemble.compose.component.author', [':author' => $row['author'], ':date' => $this->convertFromServerTime($row['date'])]);
						if ($row['hidden']) $display = UTILITY::hiddenOption($display);
						$alloptions[$row['unit']][$display] = ($row['name'] == $template['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
					}
					if ($row['type'] === 'text'){
						if (!isset($chunks[':' . $row['name']]) && !in_array($row['name'], $hidden)) {
							if (!in_array($row['unit'], array_keys($insertreplacement))) $insertreplacement[$row['unit']] = ['...' . $this->_lang->GET('texttemplate.template.insert_default') => ['value' => ' ']];
							$insertreplacement[$row['unit']][$row['name'] . ' - ' . substr($row['content'], 0, 50) . (strlen($row['content']) > 50 ? '...' : '')] = ['value' => ':' . $row['name']];
							$chunks[':' . $row['name']] = $row['content'];
						}
					}
				}

				// one selection per unit
				$renderoptions = [];
				foreach ($options as $unit => &$templates){
					ksort($templates);
					$renderoptions[] = [
						'type' => 'select',
						'numeration' => 'prevent',
						'attributes' => [
							'name' => $this->_lang->GET('texttemplate.template.select', [':unit' => $this->_lang->_USER['units'][$unit]]),
							'onchange' => "api.texttemplate('get', 'template', this.value" . ($this->_modal ? ", 'modal'" : "") . ($this->_modal && $this->_clientimport ? ", '" . $this->_clientimport . "'" : "") . ")"
						],
						'content' => $templates
					];
				}
				// one selection per unit
				$renderalloptions = [];
				foreach ($alloptions as $unit => &$templates){
					$renderalloptions[] = [
						'type' => 'select',
						'numeration' => 'prevent',
						'attributes' => [
							'name' => $this->_lang->GET('texttemplate.template.all', [':unit' => $this->_lang->_USER['units'][$unit]]),
							'onchange' => "api.texttemplate('get', 'template', this.value" . ($this->_modal ? ", 'modal'" : "") . ($this->_modal && $this->_clientimport ? ", '" . $this->_clientimport . "'" : "") . ")"
						],
						'content' => $templates
					];
				}
				// one selection per unit
				$renderinsertreplacement = [];
				foreach ($insertreplacement as $unit => &$templates){
					ksort($templates);
					$renderinsertreplacement[] = [
						'type' => 'select',
						'attributes' => [
							'name' => $this->_lang->GET('texttemplate.template.insert_name', [':unit' => $this->_lang->_USER['units'][$unit]]),
							'onchange' => "if (this.value.length > 1) Composer.composeNewTextTemplateCallback(this.value);"
						],
						'content' => $templates
					];
				}

				$units = [];
				foreach ($this->_lang->_USER['units'] as $unit => $translation){
					$units[$translation] = ['value' => $unit];
					if ($template['unit'] == $unit) $units[$translation]['selected'] = true;
				}

				$response['data'] = $chunks;
				$response['selected'] = $template['content'] ? json_decode($template['content'], true): [];
				$response['render'] = ['content' => [
					[
						[
							...$renderoptions,
							[
								'type' => 'search',
								'numeration' => 'prevent',
								'attributes' => [
									'name' => $this->_lang->GET('texttemplate.template.edit_template'),
									'onkeydown' => "if (event.key === 'Enter') {api.texttemplate('get', 'template', this.value); return false;}"
								],
								'datalist' => array_values(array_unique($templatedatalist))
							]
						],
						[
							...$renderalloptions,
							[
								'type' => 'search',
								'numeration' => 'prevent',
								'attributes' => [
									'name' => $this->_lang->GET('texttemplate.template.edit_template'),
									'onkeydown' => "if (event.key === 'Enter') {api.texttemplate('get', 'template', this.value); return false;}"
								],
								'datalist' => array_values(array_unique($templatedatalist))
							]
						]
					], [
						[
							'type' => 'text',
							'attributes' => [
								'name' => $this->_lang->GET('texttemplate.template.name'),
								'value' => $template['name'],
								'id' => 'TemplateName',
								'required' => true,
								'pattern' => '[A-Za-z0-9_]',
								'data-loss' => 'prevent'
							]
						],
						...$renderinsertreplacement,
						[
							'type' => 'select',
							'attributes' => [
								'name' => $this->_lang->GET('texttemplate.chunk.unit'),
								'id' => 'TemplateUnit'
							],
							'content' => $units
						], [
							'type' => 'button',
							'attributes' => [
								'value' => $this->_lang->GET('texttemplate.template.save'),
								'data-type' => 'submitbutton',
								'onclick' => "new _client.Dialog({type: 'confirm', header: '" .
									$this->_lang->GET("texttemplate.template.save") .
									"', options:{" .
									"'" .
									$this->_lang->GET("assemble.compose.document.cancel") .
									"': false," .
									"'" .
									$this->_lang->GET("assemble.compose.document.confirm") .
									"': {value: true, class: 'reducedCTA'}," .
									"}}).then(confirmation => {if (confirmation) api.texttemplate('post', 'template')})",
							]
						]
					], [
						[
							'type' => 'trash',
							'description' => $this->_lang->GET('assemble.compose.edit_trash')
						]
					]
				]];

				if ($template['id']){
					$hidden = [
						'type' => 'radio',
						'attributes' => [
							'name' => $this->_lang->GET('texttemplate.template.availability')
						],
						'content' => [
							$this->_lang->GET('texttemplate.template.available') => ['checked' => true],
							$this->_lang->GET('texttemplate.template.hidden') => ['data-hiddenradio' => 'ComponentHidden', 'class' => 'red']
						],
						'hint' => $this->_lang->GET('texttemplate.template.availability_hint')
					];
					if ($template['hidden']) {
						$hidden['content'][$this->_lang->GET('texttemplate.template.hidden')]['checked'] = true;
						$hiddenproperties = json_decode($template['hidden'], true);
						$hidden['hint'] .= ' ' . $this->_lang->GET('texttemplate.edit_hidden_set', [':date' => $this->convertFromServerTime($hiddenproperties['date']), ':name' => $hiddenproperties['name']]);
					}
					array_push($response['render']['content'][1], $hidden);
				}
				if ($template['name']) $response['header'] = $template['name'];
				$this->response($response);
				break;
		}
	}

	/**
	 *   _           _
	 *  | |_ ___ _ _| |_
	 *  |  _| -_|_'_|  _|
	 *  |_| |___|_,_|_|
	 *
	 * retrieve the actual templates for use
	 */
	public function text(){
		$templatedatalist = $options = $response = $hidden = $texts = $replacements = [];

		// get selected template
		$template = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_get_chunk', [
			'values' => [
				':id' => intval($this->_requestedID)
			]
		]);
		$template = $template ? $template[0] : null;
		if (!$template) $template = [
			'id' => null,
			'name' => '',
		];

		if ($this->_requestedID && $this->_requestedID !== 'false' && !$template['name'] && $this->_requestedID !== '0') $response['response'] = ['msg' => $this->_lang->GET('texttemplate.template.error_template_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];

		// prepare existing templates lists
		$templates = SQLQUERY::EXECUTE($this->_pdo, 'texttemplate_datalist');
		if (!$templates) {
			$response['render']['content'] = $this->noContentAvailable($this->_lang->GET('message.message.no_messages'));
			$this->response($response);		
		}

		foreach ($templates as $row) {
			if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
			if (!in_array($row['name'], $hidden)) {
				if ($row['type'] !== 'template') {
					// prepare in case of template request
					// set up array for strtr on content
					if ($row['type']==='text' && !isset($texts[':' . $row['name']])) $texts[':' . $row['name']] = $row['content'] . ' ';
					// set up array with valid replacements
					if ($row['type']==='replacement' && !isset($replacements[':' . $row['name']])) $replacements[':' . $row['name']] = $row['content'];
					// skip for datalist and options 
					continue;
				}
				if (!in_array($row['unit'], array_keys($options))) $options[$row['unit']] = ['...' => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				if (!isset($options[$row['unit']][$row['name']])){
					$templatedatalist[] = $row['name'];
					$options[$row['unit']][$row['name']] = ($row['name'] == $template['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
				}
			}
		}
		$response['render'] = ['content' => [[]]];

		// sort templates to units for easier access
		foreach ($options as $unit => $templates) {
			$response['render']['content'][0][] = [
				[
					'type' => 'select',
					'attributes' => [
						'name' => $this->_lang->GET('texttemplate.use.text_select', [':unit' => $this->_lang->_USER['units'][$unit]]),
						'onchange' => "if (this.value) api.texttemplate('get', 'text', this.value" . ($this->_modal ? ", 'modal'" : "") . ($this->_modal && $this->_clientimport ? ", '" . $this->_clientimport . "'" : "") . ")"
					],
					'content' => $templates
				]
			];
		}
		
		if ($template['name']){
			// prepare selected template form
			$inputs = $undefined = [];

			$usegenus = [];
			foreach ($this->_lang->_USER['texttemplate']['use']['genus'] as $key => $genus){
				$usegenus[$genus] = ['value' => $key, 'data-loss' => 'prevent'];
			}
			$inputs[] = [
				'type' => 'radio',
				'attributes' => [
					'name' => $this->_lang->GET('texttemplate.use.person'),
				],
				'content' => $usegenus
			];

			// match and replace placeholders and add paragraph linebreaks
			$content = '';
			
			// modify ([^\w\s\d\.\[\]\(\)\-ÄÖÜäöüß])
			// add match not capture
			$delimiter = substr_replace(CONFIG['forbidden']['names']['characters'], '?:', 1, 0);
			// unset types and escaped literals
			$delimiter = preg_replace('/\\\./m', '', $delimiter);
			// readd some types
			$delimiter = substr_replace($delimiter, '\\w\\d', -2, 0);

			// modify ([^\w\s\d\.\[\]\(\)\-ÄÖÜäöüß])
			// invert first forbidden names to allowed
			$pattern = substr_replace(CONFIG['forbidden']['names']['characters'], '', 2, 1);
			// add colon
			$pattern = substr_replace($pattern, ':', 1, 0);
			// unset types and escaped literals
			$pattern = preg_replace('/\\\./m', '', $pattern);
			// readd some types
			$pattern = substr_replace($pattern, '\\w\\d', -2, 0);
			// add multiplier
			$pattern = substr_replace($pattern, '+?', -1, 0);

			$usedreplacements = $usedtexts = [];
			foreach (json_decode($template['content']) as $paragraph){
				foreach ($paragraph as $chunk){
					$add = isset($texts[$chunk]) ? $texts[$chunk] : $chunk . "\n";
					preg_match_all('/' . $pattern . $delimiter . '/m', $add, $placeholders);
					foreach ($placeholders[1] as $ph){
						if (!isset($replacements[$ph])) array_push($undefined, $ph);
						else $usedreplacements[$ph] = $replacements[$ph]; // reassign to reduce payload
					}
					$content .= $add;
					if (isset($texts[$chunk])) $usedtexts[$chunk] = $texts[$chunk];  // reassign to reduce payload
				}
				$content .= "\n\n";
				$usedtexts[$paragraph[count($paragraph)-1]] .= "\n\n";
			}

			// add input fields for undefined placeholders
			foreach ($undefined as $placeholder) {
				$inputs[] = [
					'type' => 'text',
					'attributes' => [
						'name' => $this->_lang->GET('texttemplate.use.fill_placeholder') . ' ' . $placeholder,
						'id' => $placeholder,
						'data-usecase' => 'undefinedplaceholder',
						'data-loss' => 'prevent'
					]
				];
			}
			// array values for keys are represented as string otherwise, array unique to prevent repetitive inputs for multiple same undefined placeholders
			$inputs = array_values(array_unique($inputs, SORT_REGULAR));

			// import button (values from passed document-element-ids)
			// $this->_clientimport as json-string with ':placeholder' : 'inputid' pairs
			if ($this->_clientimport && $undefined){
				$clientimport = [];
				// only display placeholders defined within chunk on button, not neccessarily all passed ones
				foreach (json_decode($this->_clientimport, true) as $key => $value){
					if (in_array($key, $undefined)) $clientimport[$key] = $value;
				}
				if ($clientimport) $inputs[] = [
					'type' => 'button',
					'attributes' => [
						'value' => $this->_lang->GET('texttemplate.use.import', [':placeholders' => implode(', ', array_keys($clientimport))]),
						'onclick' => "_client.texttemplate.import('" . $this->_clientimport . "');"
					],
					'hint' => $this->_lang->GET('assemble.compose.component.author', [':author' => $row['author'], ':date' => $this->convertFromServerTime($row['date'])])
				];	
			}

			// set up selectable blocks
			foreach (json_decode($template['content']) as $block){
				$useblocks = [];
				foreach ($block as $key => $value){
					$useblocks[substr($value, 1)] = ['checked' => true, 'data-usecase' => 'useblocks', 'data-loss' => 'prevent'];
				}
				if (count($useblocks)) $inputs[] = [
					'type' => 'checkbox',
					'attributes' => [
						'name' => $this->_lang->GET('texttemplate.use.blocks')
					],
					'content' => $useblocks
				];
			}

			// refreshbutton
			$inputs[] = [
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('texttemplate.use.refresh'),
					'onclick' => '_client.texttemplate.update();',
					'data-type' => 'generateupdate'
				],
				'hint' => $this->_lang->GET('assemble.compose.component.author', [':author' => $row['author'], ':date' => $this->convertFromServerTime($row['date'])])
			];

			// append inputs
			$response['render']['content'][] = $inputs;

			// append output 
			$response['render']['content'][] = [
				[
					'type' => 'textarea',
					'attributes' => [
						'id' => 'texttemplate',
						'name' => $this->_lang->GET('texttemplate.use.template'),
						'value' => $content,
						'rows' => 13,
						'readonly' => true,
						'onclick' => '_client.application.toClipboard(this.value)'
						]
				]
			];
			// append data for frontent processing
			$response['data'] = ['blocks' => $usedtexts, 'replacements' => $usedreplacements];
		}
		if (!$this->_modal && PERMISSION::permissionFor('texttemplates')){
			$response['render']['content'][] = [
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('texttemplate.navigation.chunks'),
					'onclick' => "api.texttemplate('get', 'chunk')"
				]
			];
			$response['render']['content'][] = [
				'type' => 'button',
				'attributes' => [
					'value' => $this->_lang->GET('texttemplate.navigation.templates'),
					'onclick' => "api.texttemplate('get', 'template'" . ($template['id'] ? ", " . $template['id'] : "") . ")"
				]
			];
		}
		$this->response($response);
	}
}
?>
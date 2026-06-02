<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// SPDX-FileCopyrightText: © 2026 error on line 1 <dev@erroronline.one>
// SPDX-FileNotice: Part of erroronline1/markdown parser for PHP & ECMA-Script.

namespace erroronline1\Markdown;

class ListTypeGenerator{
	/**
	 * being able to assign/instatiate a new generator function
	 */
	public function generator(){
		while(true){
			yield '1';
			yield 'I';
			yield 'i';
			yield 'A';
			yield 'a';
		}
	}
}

class Markdown {
		private $_anchor = '/(?<!\]\()(?:\<{0,1})(?<!\'|"|`)((?:https*|ftps*|tel):(?:\/\/)*[^\n\s,"\'`<>]+)(?:\>{0,1})|(?:(?<!!|\\)\[)(.+?)(?:(?<!\\)\])(?:\()(.+?)((?: \").+(?:\"))*(?:(?<!\\)\)(?!\)))/im'; // auto url linking, including some schemes and md linking; rewrite working regex101.com expression on construction for correct escaping of \
	private $_blockquote = '/(^>{1,}.*?\n)+/ms';
		private $_code = '/^ {0,3}([`~]{3})(.*?)\n((?:.|\n)+?)\n^ {0,3}\1\n|^\n^ {4}([^\*\-\d].+)+|(?<!\\)(`{1,2})([^\n]+?)(?<!\\| |\n)\5/m'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_comment = '/<!--.*?-->/s';
	private $_compress = '/>\n+|\n *<|[^>]\n+<[^\/]/m';
	private $_definition = '/(^.+?\n)((?:^: .+?\n)+)/m';
		private $_emphasis = '/(?<!\\)(\*{1,3}(?! ))([^\n]+?)(?<!\\| )\1|(?<!\\|\S)(_{1,3}(?! ))([^\n]+?)(?<!\\| )\3(\W)/m'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_footnote = '/\[\^(.+?)\](:.+?\n(?: {4}.*?\n)*)*/';
	private $_headings = '/(?:^)(#+ )(.+?)(?: {#(.+?)}){0,1}(?:#*)$|(?:^)(.+?)\n(={3,}|-{3,})$/m'; // must be first line or have a linebreak before
	private $_horizontal_rule = '/^ {0,3}(?:\-|\- |\*|\* ){3,}$/m';
	private $_image = '/(?:!\[)(.*?)(?:\])(?:\()(.+?)(?:\))/';
		private $_fontsize = '/(?<!\\)((?:\+|-){2,})([^\n]+?)(?<!\\| |\n)\1(?!((?:\+|-)))/'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_linebreak = '/ +\n/';
	private $_list = '/((?:^)(\*|\-|\+|\d+\.) {1,3}(?:.|\n)+?)(?:\n$)/m';
		private $_mailto = '/([^\s<]+(?<!\\)@[^\s<]+\.[^\s<]+)/'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_mark = '/==(.+?)==/';
	private $_paragraph = '/(?:^$\n)(.+?)(?:\n^$)/ms';
		private $_reference = '/(?:(?<!!|\\)\[)(.+?)(?:(?<!\\)\])(?:\[)(.+?)(?:\])|(?:^\[)([^^]+?)(?:\]:)(.+)$/m'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_safeMode = '/<\/{0,1} {0,}(a|applet|audio|body|dialog|form|html|iframe|input|keygen|main|noscript|object|param|script|style|title|textarea|video|xmp)|on\w+?=(\'|").+?(?<!\\)\2/mi'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_strikethrough = '/(?<!\\)~{2}([^\n]+?)(?<!\\| |\n)~{2}/'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_subscript = '/(?<!\\)~{1}([^\n]+?)(?<!\\| |\n)~{1}/'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_superscript = '/(?<!\\)\^{1}([^\n]+?)(?<!\\| |\n)\^{1}/';
	private $_table = '/^((?:\|.+?){1,}\|)\n((?:\| *:{0,1}-+:{0,1} *?){1,}\|)\n((?:(?:\|.+?){1,}\|(?:\s*\n|\s*$))+)/m';
	private $_task = '/\[(\s*x{0,1}\s*)\] (.+?(?:\n))/mi';
		private $_typographer = '/(?<!\\)\((?:c|r|tm|p)(?<!\\)\)|(?<!\\)\+-|(?<!\\)->/i'; // rewrite working regex101.com expression on construction for correct escaping of \

	// class properties to use over multiple methods
	private $_headers = [];
	private $_references = [];
	private $_limitTo = [];

	// predefined character-sets to escape or replace if required
	private $_codeescape = '([*\-\+~`.@$>^[\]()=_#:|\d])';
	public array $_htmlescape = [
		"&" => "&amp;",
		"$" => "&#36;", // able to break code handling if not escaped, at least in ecmas
		"<" => "&lt;",
		">" => "&gt;",
		'"' => "&quot;",
		"'" => "&#039;",
	];

	public array $_typographs = [
		"(c)" => "&copy;",
		"(r)" => "&reg;",
		"(tm)" => "&trade;",
		'(p)' => "&#9413;",
		"+-" => "&#177;",
		"->" => "&rarr;"
	];

	// modifiable lists for using as extended class
	public array $_methodsInProcessingOrder = [
		"code", // must come first to enable escaping to avoid unintended conversion
		"safeMode", // prior to tasks, definition and reference avoiding invalidation of allowed input and anchors
		"footnote", // should come prior to indentation-, list- and superscript-handling
		"blockquote", // should come prior to other blocks to enable nesting
		"reference", // before a and footnote to not mess up with similar patterns
		"definition",
		"headings", // before hr avoiding conversion of ----
		"horizontal_rule", // prior to list avoiding conversion of - - -
		"list",
		"table",
		"image", // prior to anchor for properly linkable images
		"anchor", // safeMode can not render anchors to avoid malicious scripts
		"mailto", // safeMode can not render anchors to avoid malicious scripts
		"task",
		"mark",
		"strikethrough",
		"subscript",
		"superscript",
		"emphasis",
		"fontsize", // after tables for handling -- characters; THIS IS A CUSTOM MARKDOWN PROPERTY TO THIS FLAVOUR
		"typographer",
		"paragraph", // must come after anything previous to not mess up pattern recognitions relying on linebreaks and filtering out previously converted tags
		"linebreak",
	];

	public $_nested_blocks = [
		'code',
		'blockquote',
		'definition',
		'list',
		'table',
	];

	// convert some tags currently not supported by the mentioned library
	// implementation as intval can handle major version behaviour
	private int $TCPDF = 0;

	/**
	 * instatiate the interface
	 * 
	 * @param bool $TCPDF default false switches some tags for compatibility reasons
	 */
	public function __construct($TCPDF = 0)
	{
		// rewrite working regex101.com expression on construction for correct escaping of \
		$this->_anchor = '/(?<!\]\()(?:\<{0,1})(?<!\'|"|`)((?:https*|ftps*|tel):(?:\/\/)*[^\n\s,"\'`<>]+)(?:\>{0,1})|(?:(?<!!|' . preg_quote('\\', '/') . ')\[)(.+?)(?:(?<!' . preg_quote('\\', '/') . ')\])(?:\()(.+?)((?: \").+(?:\"))*(?:(?<!' . preg_quote('\\', '/') . ')\)(?!\)))/m'; // regular md links
		$this->_code = '/^ {0,3}([`~]{3})(.*?)\n((?:.|\n)+?)\n^ {0,3}\1\n|^\n^ {4}([^\*\-\d].+)+|(?<!' . preg_quote('\\', '/') . ')(`{1,2})([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)\5/m';
		$this->_emphasis = '/(?<!' . preg_quote('\\', '/') . ')(\*{1,3}(?! ))([^\n]+?)(?<!' . preg_quote('\\', '/') . '| )\1|(?<!' . preg_quote('\\', '/') . '|\S)(_{1,3}(?! ))([^\n]+?)(?<!' . preg_quote('\\', '/') . '| )\3(\W)/m';
		$this->_mailto = '/([^\s<]+(?<!' . preg_quote('\\', '/') . ')@[^\s<]+\.[^\s<]+)/';
		$this->_fontsize = '/(?<!' . preg_quote('\\', '/') . ')((?:\+|-){2,})([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)\1(?!((?:\+|-)))/';
		$this->_reference = '/(?:(?<!!|' . preg_quote('\\', '/') . ')\[)(.+?)(?:(?<!' . preg_quote('\\', '/') . ')\])(?:\[)(.+?)(?:\])|(?:^\[)([^^]+?)(?:\]:)(.+)$/m';
		$this->_safeMode = '/<\/{0,1} {0,}(a|applet|audio|body|dialog|form|html|iframe|input|keygen|main|noscript|object|param|script|style|title|textarea|video|xmp)|on\w+?=(\'|").+?(?<!' . preg_quote('\\', '/') . ')\2/mi';
		$this->_strikethrough = '/(?<!' . preg_quote('\\', '/') . ')~{2}([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)~{2}/';
		$this->_subscript = '/(?<!' . preg_quote('\\', '/') . ')~{1}([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)~{1}/';
		$this->_superscript = '/(?<!' . preg_quote('\\', '/') . ')\^{1}([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)\^{1}/';
		$this->_typographer = '/(?<!' . preg_quote('\\', '/') . ')\((?:c|r|tm|p)(?<!' . preg_quote('\\', '/') . ')\)|(?<!' . preg_quote('\\', '/') . ')\+-|(?<!' . preg_quote('\\', '/') . ')->/i';
		$this->TCPDF = intval($TCPDF); 
	}

	/**
	 * convert a csv-file to markdown table
	 * 
	 * @param string $path filepath to csv
	 * @param array $csv dialect options
	 * @return string|\Exception Marktown table or exception for lack of rows
	 */
	public function csv2md($path, $csv = ['separator' => ';', 'enclosure' => '"', 'escape' => '']){
		$csvfile = fopen($path, 'r');
		if (fgets($csvfile, 4) !== "\xef\xbb\xbf") rewind($csvfile); // BOM not found - rewind pointer to start of file.
		$rownum = 0;
		$md = '';
		while(($row = fgetcsv(
			$csvfile,
			null,
			$csv['separator'],
			$csv['enclosure'],
			$csv['escape']
			)) !== false) {
			if ($rownum < 1){
				if (count($row) < 2){
					throw new \Exception(mb_convert_encoding(implode(', ', $row), 'UTF-8', mb_detect_encoding(implode(', ', $row), ['ASCII', 'UTF-8', 'ISO-8859-1'])));
					return;
				}
				// set header as data keys
				foreach($row as &$column){
					if ($column) {
						$bom = pack('H*','EFBBBF'); // coming from excel this is utf8
						// delete bom, convert linebreaks to br\\
						$column = preg_replace(["/^$bom/", '/\n/'], ['','<br />'], $column);
					}
				}
				$md .= '| ' . implode(' | ', $row) . " |\n";
				$md .= '| ' . implode(' | ', array_fill(0, count($row), ' ----- ')) . " |\n";
			}
			else {
				$row = array_filter($row, fn($column) => $column !== null);
				$row = array_map(fn($column) => preg_replace('/\n/', '<br />', $column), $row);
				if ($row) $md .= '| ' . implode(' | ', $row) . " |\n";
			}
			$rownum++;
		}
		fclose($csvfile);
		$md .= "\n";
		return $md;
	}

	/**
	 * convert a markdown table to csv
	 * 
	 * @param string $content Markdown table
	 * @param array $csv dialect options
	 * @return array|\Exception [tempfile => string, headers => string] or exception due to lack of identified tables
	 */
	public function md2csv($content, $csv = ['separator' => ';', 'enclosure' => '"', 'escape' => '']){
		$data = [];
		$content = preg_replace('/\r/', '', $content);
		preg_match_all($this->_table, $content, $table);
		if (isset($table[0]) && $table[0]) {
			for ($i = 0; $i < count($table[0]); $i++){
				foreach(explode("\n", $table[0][$i]) as $rowindex => $row){
					if (!$row) continue;
					$columns = array_filter(preg_split('/(?<!' . preg_quote('\\', '/'). ')\|/', $row), fn($c) => boolval(trim($c)));
					switch($rowindex){
						case 1:
							break;
						default:
							$data[] = array_map(fn($column) => preg_replace('/<br {0,1}\/{0,1}>/', "\n", trim($column)), array_filter($columns, fn($column) => boolval($column)));
					}
				}
			}
		}

		if (!$data) {
			throw new \Exception('no table identified');
			return;
		}

		@$tmp_name = tempnam( sys_get_temp_dir(), preg_replace('/\W/', '', implode('_', $data[0])));
		$file = fopen($tmp_name, 'w');
		fwrite($file, b"\xEF\xBB\xBF"); // tell excel this is utf8
		foreach($data as $row){
			fputcsv(
				$file,
				$row,
				$csv['separator'],
				$csv['enclosure'],
				$csv['escape']
			);
		}
		fclose($file);
		return [
			'tmpfile' => $tmp_name, 
			'headers' => preg_replace('/([^\w\s\d,\.\[\]\(\)\-\+&])/', '_', implode('_', $data[0]))];
	}

	/**
	 * entry method to convert a text to markdown
	 * 
	 * @param string $text Markdown styled
	 * @param bool $safeMode returns anchors as specialchars and some
	 * @param array $limitTo process only given methods, empty for all
	 * @return string as HTML
	 */
	public function md2html($text, $safeMode = false, $limitTo = []){
		$this->_limitTo = $limitTo;

		$text = preg_replace(['/\r/','/\t/'], ['', '    '], $text ?: '') . "\n"; // add a new line for improved pattern matching by default

		// detect all comments and replace them with a numbered placeholder to not mess up formatting
		$comment_placeholder = 'eol1_md_comment_placeholder_' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10) . '_';
		preg_match_all($this->_comment, $text, $comments);
		if ($comments){
			$comments = $comments[0];
			foreach($comments as $i => $comment){
				$text = str_replace($comment, '{' . $comment_placeholder . $i . '}', $text);
			}
		}
		// apply methods
		foreach ($this->_methodsInProcessingOrder as $method) {
			if (!$this->_limitTo || in_array($method, $this->_limitTo) || ($safeMode && in_array($method, ["anchor", "safeMode", "reference", "mailto"]))) {
				if (in_array($method, ["anchor", "safeMode", "reference", "mailto"])) $text = $this->$method($text, $safeMode);
				else $text = $this->$method($text);
			}
		}
		$text = $this->deescape($text); // should come after other stylings have been applied
		$text = $this->compress($text);

		// revert comments
		if ($comments){
			$text = preg_replace_callback('/{' . $comment_placeholder . '(\\d+)}/',
				function ($match) use ($comments) {
					return $comments[$match[1]] ?? $match[0];
				},
				$text
			);
			if (!$this->_limitTo || in_array('code', $this->_limitTo)){
			$text = preg_replace_callback('/<code.*?>(?:<pre.*?>)*(.+?)(?:<\/pre>)*<\/code>/s',
				function ($match) {
					return str_replace($match[1], $this->escapeHtml($match[1], false), $match[0]);
				},
				$text
			);

			}
		}
		$text = preg_replace(['/\t/'], ['    '], $text); // revert code indentation
		return "<!-- Markdown parsing by error on line 1, https://github.com/erroronline1/markdown -->\n" . $text;
	}

	/**
	 * strip new lines near tags to compress result 
	 * 
	 * @param string $content
	 * @return string
	 */
	private function compress($content){
		return preg_replace_callback($this->_compress,
			function($match){
				return preg_replace('/[\n\s]+/', ' ', $match[0]);
			},
			$content
		);
	}

	/**
	 * development helper...
	 * @param mixed $content
	 */
	private function debug(...$content){
		echo "<pre>"; var_dump(...$content); echo "</pre>";
	}

	/**
	 * replace escaped characters
	 * 
	 * @param string $content
	 * @return string
	 */
	private function deescape($content){
		return preg_replace('/' . preg_quote('\\', '/') . $this->_codeescape . '/',
			'$1',
			$content
		);
	}

	/**
	 * escape special chars for code, pre and in case of safeMode
	 * </p>
	 * @param string $content
	 * @param bool $ampersand can be excluded to avoid duplicate encoding
	 * @return string
	 */
	private function escapeHtml($content, $ampersand = true){
		$escape = $this->_htmlescape;
		if (!$ampersand) unset($escape['&']);
		return preg_replace_callback('/[' . preg_quote(implode('', array_keys($escape)), '/') . ']/',
			function($match) {
				return $this->_htmlescape[$match[0]];
			}, $content
		);
	}

	/**
	 * methods to run within nested elements like lists and footnotes
	 * 
	 * @param string $content
	 * @return string
	 */
	private function nested_blocks($content){
		foreach($this->_nested_blocks as $method){
			if (!$this->_limitTo || in_array($method, $this->_limitTo)){
				$content = $this->$method($content);
			}
		};
		return $content;
	}

	/**
	 * split the content by found blocks to later zip converted content blocks with separator blocks
	 * 
	 * @param string $content
	 * @param array $by
	 * @return array
	 */
	private function separate($content, $by = []){
		// due to the nature of preg-match results, patterns did not proof suitable for splitting. must be literals
		// $content = preg_split('/' . implode('|', array_map(fn($v) => preg_quote($v, '/'), $by)) . '/', $content);
		// this works in general but will fail on longer readme-files with Compilation failed: regular expression is too large:
		// ecmas is a bit more relax on this
		$return = [];
		foreach($by as $c){
			// find in string
			$pos = strpos($content, $c);
			// add substring to content
			$return[] = substr($content, 0, $pos);
			// remove from text
			$content = substr($content, $pos + strlen($c));
		}
		// add remainder
		$return[] = $content;
		return $return;
	}

	/**
	 * replaces links unless already converted by the reference-method or external links in safeMode
	 * internal links are always rendered since considerable safe
	 * 
	 * @param string $content
	 * @param bool $safeMode
	 * @return string
	 */
	private function anchor($content, $safeMode = false){
		// replace links in this order
		$_references = $this->_references;
		$unescapedCode = function($uccontent){
			preg_match_all('/<code.*?code>|<img.+\/>/', $uccontent, $code);
			$code = $code[0];
			if (!$code) return $this->escapeHtml($uccontent);
			// split the content by found code blocks to later zip code blocks with converted content 
			$nocode = $this->separate($uccontent, $code);
			for ($i = 0; $i < count($nocode); $i++){
				$nocode[$i] = preg_replace('/[\-\+]/', '\\\\$0', $this->escapeHtml($nocode[$i]));
			}
			$uccontent = [];
			for ($i = 0;$i < count($nocode); $i++){
				array_push($uccontent, $nocode[$i], $code[$i] ?? '');
			}
			return implode('', $uccontent);
		};
		return preg_replace_callback($this->_anchor,
			function($match) use ($safeMode, $_references, $unescapedCode){
				if (in_array($match[1], $_references)) return $match[1]; // avoid duplication of link creation from references

				// markdown linking, allow internal links
				if (isset($match[3]) && str_starts_with($match[3], '#')) return '<a href="' . preg_replace('/[\-\+]/', '\\\\$0', $match[3]) . '" class="eol1_md">' . $unescapedCode($match[2]) . '</a>';

				if ($safeMode) return $unescapedCode($match[0]);

				// auto linking with protocols
				if ($match[1]) return '<a href="' . preg_replace('/[\-\+]/', '\\\\$0', $match[1]) . '" class="eol1_md">' . $unescapedCode($match[1]) . '</a>';
				//markdown linking
				$url = '';
				if (str_starts_with($match[3], 'javascript:')) $url = $match[3];
				else {
					$component = parse_url($match[3]);
					if (isset($component['query'])){
						parse_str($component['query'], $query);
						$url = substr($match[3], 0, strpos($match[3], '?')) . '?' . http_build_query($query);
					}
					else $url = $match[3];
					//$url .= '" target="_blank';
				}
				if (isset($match[4]) && $match[4]) $url .= '" title="' . substr($match[4], 2, -1);

				return '<a href="' . preg_replace('/[\-\+]/', '\\\\$0', $url) . '" class="eol1_md">' . $unescapedCode($match[2]) . '</a>';
			},
			$content);
	}

	/**
	 * replace blockquotes recursively
	 * 
	 * @param string $content
	 * @return string
	 */
	private function blockquote($content){
		$content = preg_replace_callback($this->_blockquote,
			function($match){
				$match[0] = $this->nested_blocks(preg_replace(['/^> {0,1}/m'], '', $match[0])); // remove blockquote character and possible whitespace and check recursively for nested blocks
				return '<p><blockquote class="eol1_md">' . $match[0] . "</blockquote></p>";
			},
			$content
		);
		return $content;
	}

	/**
	 * replace indentated, fenced or quoted code
	 * 
	 * @param string $content
	 * @return string
	 */
	private function code($content){
		preg_match_all($this->_code, $content, $code);
		$code = $code[0];
		if (!$code) return $content;
		// split the content by found code blocks to later zip converted code blocks with content 
		$nocode = $this->separate($content, $code);

		$escape = '/' . $this->_codeescape . '/';
		for ($i = 0; $i < count($code); $i++){
			$code[$i] = preg_replace_callback($this->_code,
			function($match) use ($escape){
				if (isset($match[6]) && $match[6]) {
					// inline code
					if ($this->TCPDF) return '<span style="font-family: monospace;">' . preg_replace_callback($escape,
						function ($e_match) {
							return '\\' . $e_match[1];
						},
						$this->escapeHtml($match[6])) . '</span>'; // current implementation of tcpdf does not support code
					
					return '<code class="eol1_md">' . preg_replace_callback($escape,
						function ($e_match) {
							return '\\' . $e_match[1];
						},
						$this->escapeHtml($match[6])) . '</code>';
				}
				else {
					// $match[2] for fenced code would be a specified language, not sure what to do with that yet
					// if match[4] code blocks are written with pure indentation
					$codeblock = isset($match[4]) ? preg_replace('/^ {4}/m', '', $match[0]) : $match[3];
					$codeblock = preg_replace_callback($escape, // escape above special chars
						function ($e_match) {
							return '\\' . $e_match[1];
						},
						$this->escapeHtml($codeblock)
					);
					$codeblock = preg_replace('/ {1,}$/m', '', $codeblock); // delete end spaces that otherwise may be replaced with linebreak
					$codeblock = str_replace('    ', "\t", $codeblock); // replace 4 spaces within code with tabs to avoid possible collisions

					if ($this->TCPDF) return "\n" . (isset($match[4]) ? '    ' : '') . '<pre class="eol1_md">' . $codeblock . "</pre>\n";
					
					return "\n" . (isset($match[4]) ? '    ' : '') . '<code class="eol1_md"><pre class="eol1_md">' . $codeblock . "</pre></code>\n";
				} 
			},
			$code[$i]);
		}
		$content = [];
		for ($i = 0;$i < count($nocode); $i++){
			array_push($content, $nocode[$i], $code[$i] ?? '');
		}
		return implode('', $content);
	}

	/**
	 * replace definition lists
	 * 
	 * @param string $content
	 * @return string
	 */
	private function definition($content){
		return preg_replace_callback($this->_definition, 
			function($match) {
				$definitions = [];
				foreach(explode("\n", $match[2]) as $d){
					if ($d) $definitions[] = substr($d, 2);
				}
				return '<dl class="eol1_md"><dt>' . $match[1] . '</dt><dd>' . implode('</dd><dd>', $definitions) . '</dd></dl>';
			},
			$content
		);
	}

	/**
	 * replace all em and strong formatting
	 * 
	 * @param string $content
	 * @return string
	 */
	private function emphasis($content){
		return preg_replace_callback($this->_emphasis, 
			function($match){
				$wrapper = strlen($match[1] ?: $match[3]);
				$tags = [
					[], // wrapper offset, easier than reducing index
					['<em>', '</em>'],
					['<strong>', '</strong>'],
					['<em><strong>', '</strong></em>']
				];
				return $tags[$wrapper][0] . $this->emphasis($match[2] ?: $match[4]) . $tags[$wrapper][1] . (isset($match[5]) ? $match[5] : ''); // append consumed nonword-character on underscore pattern
			},
			$content
		);
	}

	/**
	 * replace fontsize decorator
	 * THIS IS A CUSTOM MARKDOWN PROPERTY TO THIS FLAVOUR
	 * 
	 * @param string $content
	 * @return string
	 */
	private function fontsize($content){
		return preg_replace_callback($this->_fontsize,
			function ($match){
				return '<span class="eol1_md" style="font-size:' . (substr($match[1], 0, 2) == '++' ? 'larger' : 'smaller') . ';">' . $this->fontsize(substr($match[0], 2, -2)) . '</span>';
			},
			$content
		);
	}

	/**
	 * replace footnote references with links an append an actual footnote list at the end of the content
	 * no need for safeMode, since these are internal links only
	 * 
	 * @param string $content
	 * @return string
	 */
	private function footnote($content){
		// find all footnotes
		preg_match_all($this->_footnote, $content, $footnotes);
		$_footnotes = [];
		
		foreach($footnotes[1] as $key => $value){
			$footnote_block = preg_replace('/^: |^ {4}/m', '', trim($footnotes[2][$key] ?: ''));
			// replace possible nested blocks in footnote item
			$footnote_block = $this->nested_blocks($footnote_block);

			$_footnotes[strval($value)] = $footnote_block;
		}
		$content = preg_replace_callback($this->_footnote, 
			function($match) use ($_footnotes){
				// inline links if available as md superscript
				if (empty($match[2])){
					if (empty($_footnotes[$match[1]])) return '^' . $match[1] . '^';
					$key = array_search($match[1], array_keys($_footnotes)) + 1;
					return '^<a id="fnref:' . $key . '" href="#fn:' . $key . '" class="eol1_md">[' . $key . ']</a>^';
				}
				// delete actual footnote
				return '';
			},
			$content
		);
		// create actual footnotes as ordered md list and re-append to content
		$footnote_appendix = '';
		foreach($_footnotes as $link => $footnote){
			$key = array_search($link, array_keys($_footnotes)) + 1;
			$footnote_appendix .= '1. <a id="fn:' . $key . '" class="eol1_md"></a>' . preg_replace('/^/m', '    ', stripslashes($footnote)) . ' <a href="#fnref:' . $key . '" class="eol1_md">&crarr;</a>' . "  \n";
		}
		return $content . ($footnote_appendix ? "\n<hr>\n" . $footnote_appendix . "\n" : '');
	}

	/**
	 * replace headers and assign auto or custom ids
	 * 
	 * @param string $content
	 * @return string
	 */
	private function headings($content){
		return preg_replace_callback($this->_headings,
			function($match){
				if (!isset($match[4])){
					// atx heading starting with #
					$size = min(strlen($match[1]) - 1, 6);
					$heading = trim($match[2]);
				}
				else {
					// setext heading underlined with === or ---
					$size = str_starts_with($match[5], '=') ? 1 : 2;
					$heading = trim($match[4]);
				}
				$id = preg_replace('/[^\w\d\s]/u', '', $heading);
				if (!empty($match[3]) /*custom id*/ || $id){
					$id = preg_replace(['/\s/'], ['-'], mb_strtolower(trim(!empty($match[3]) ? $match[3] : $id))); // mb_strtolower can handle umlauts
					// enumerate
					$existing = array_filter($this->_headers, fn($e) => str_starts_with($e, $id));
					if ($existing) {
						sort($existing);
						$last = array_pop($existing);
						preg_match('/.+?-(\d)$/m', $last, $numerate);
						if (isset($numerate[1]) && $numerate[1]) $id .= '-' . intval($numerate[1]) + 1;
						else $id .= '-1';
					}
					$this->_headers[] = $id;
				}
				return "<h" . $size . ' id="' . $id . '">' . $heading . '</h' . $size . ">\n";
			},
			$content
		);
	}

	/**
	 * replace hr
	 * 
	 * @param string $content
	 * @return string
	 */
	private function horizontal_rule($content){
		return preg_replace($this->_horizontal_rule,
			"<hr>",
			$content
		);
	}
	
	/**
	 * replace images
	 * 
	 * @param string $content
	 * @return string
	 */
	private function image($content){
		return preg_replace_callback($this->_image,
			function($match){
				return '<img alt="' . preg_replace('/[\-\+]/', '\\\\$0', $match[1] ?: '') . '" src="' . preg_replace('/[\-\+]/', '\\\\$0', $match[2]) . '" class="eol1_md" />';
			},
			$content
		);

	}

	/**
	 * replace linebreaks
	 * 
	 * @param string $content
	 * @return string
	 */
	private function linebreak($content){
		return preg_replace($this->_linebreak,
			"<br />",
			$content
		);
	}

	/**
	 * detects list and replaces list items recursively with available nested blocks
	 * 
	 * @param string $content
	 * @param bool|\Generator $recursion for passing down a generator for ol list styles
	 * @return string
	 */
	private function list($content, $recursion = false){
		$content = preg_replace_callback($this->_list,
			function($match) use ($recursion){
				// used recursion is immutable between each callback function appliance
				// first list item decides for the type
				$bullet = intval($match[2]);
				$li_type = $bullet > 0 ? 'ol' : 'ul';
				$offset = $bullet < 2 ? '' : ' start="' . $bullet . '"';
				$entries = [];
				$item_split = $type = '';
				switch($li_type){
					case 'ol':
						$item_split = '/^\d+\. */m';
						if (gettype($recursion) === 'boolean') {
							$recursion = new ListTypeGenerator();
							$recursion = $recursion->generator();
						}
						else $recursion->next();
						$type = ' type="' . $recursion->current() . '"'; 
						break;
					case 'ul':
						$item_split = '/^[\*|\-|\+] */m';
						$recursion = true;
						$type = '';
						break;
				}
				foreach(preg_split($item_split, $match[0]) as $list_entry){
					if ($list_entry){
						// recursively replace nested items
						$list_entry = $this->list(preg_replace('/^ {4}/m', '', $list_entry . "\n"), $recursion);  // add linebreak to end for pattern recognition
						$entries[] = ($this->TCPDF && $this->TCPDF < 8 && $li_type === 'ol' ? str_repeat('&nbsp;', 3) : '') . trim($list_entry);
					}
				}
				return '<' . $li_type . $type . $offset . ' class="eol1_md"><li>' . implode('</li><li>', $entries) . '</li></' . $li_type . '>';
			},
			$content
		);
		if ($recursion){
			// replace possible nested blocks in list item
			$content = $this->nested_blocks($content);
		}
		return $content;
	}

	/**
	 * replace email adresses with mailto link unless in safeMode
	 * encoded by default
	 * 
	 * @param string $content
	 * @param bool $safeMode
	 * @return string
	 */
	private function mailto($content, $safeMode){
		return preg_replace_callback($this->_mailto,
			function($match) use ($safeMode){
				if($safeMode) return str_replace('_', '\_', $this->escapeHtml(($match[0])));

				$encoded_email = '';
				for ($a = 0, $b = strlen($match[0]); $a < $b; $a++)
				{
					$encoded_email .= '&#' . (mt_rand(0, 1) == 0  ? 'x' . dechex(ord($match[0][$a])) : ord($match[0][$a])) . ';';
				}
				return '<a href="mailto:' . $encoded_email . '">' . $encoded_email . '</a>';
			},
			$content
		);
	}

	/**
	 * replace marked text
	 * 
	 * @param string $content
	 * @return string
	 */
	private function mark($content){
		if ($this->TCPDF) return preg_replace($this->_mark,  // current implementation of tcpdf does not support mark
			"<span style=\"background-color:yellow\">$1</span>",
			$content);

		return preg_replace($this->_mark,
			'<mark class="eol1_md">$1</mark>',
			$content);
	}

	/**
	 * replace paragraphs
	 * 
	 * @param string $content
	 * @return string
	 */
	private function paragraph($content){
		preg_match_all('/<code.*?code>/s', $content, $code);
		$code = $code[0];
		if (!$code) return preg_replace_callback($this->_paragraph,
			function($match) {
				$match[0] = trim($match[0]);
				preg_match('/^(<h|<ol|<ul|<p)/m', $match[0], $nop);
				if ($nop) return $match[0];
				return '<p>' . $match[0] . '</p>';
			},
			$content
		);
		// split the content by found code blocks to later zip code blocks with converted content 
		$nocode = $this->separate($content, $code);
		for ($i = 0; $i < count($nocode); $i++){
			$nocode[$i] = preg_replace_callback($this->_paragraph,
				function($match) {
					$match[0] = trim($match[0]);
					preg_match('/^(<h|<ol|<ul|<p)/m', $match[0], $nop);
					if ($nop) return $match[0];
					return '<p>' . $match[0] . '</p>';
				},
				$nocode[$i]
			);
		}
		$content = [];
		for ($i = 0;$i < count($nocode); $i++){
			array_push($content, $nocode[$i], $code[$i] ?? '');
		}
		return implode('', $content);
	}

	/**
	 * replace references unless in safeMode
	 * then an internal link to the escaped reference will be generated
	 * like a footnote but without altering the position within the content
	 * 
	 * @param string $content
	 * @param bool $safeMode
	 * @return string
	 */
	private function reference($content, $safeMode = false){
		// replace references
		preg_match_all($this->_reference, $content, $matches);
		// look for actual available references
		foreach ($matches[0] as $index => $match) {
			if ($matches[4][$index] && trim($matches[4][$index])) $this->_references[$matches[3][$index]] = trim($matches[4][$index]);
		}
		$_references = $this->_references;
		// link references
		$content = preg_replace_callback($this->_reference,
			function($match) use ($safeMode, $_references) {
				if ($safeMode) {
					// i don't know if this is canon, but i assume this might be a practical solution for safe mode to link to the escaped reference
					if ($match[2]) {
						if (isset($_references[$match[2]])) return '<a href="#ref:' . $match[2] . '">' . $match[1] . "</a>";
						else return $match[1];
					} else if ($match[4]) return '<a id="ref:' . $match[3] . '" class="eol1_md"></a>(' . trim($this->escapeHtml($match[4])) .")";
				} else {
					// return link with reference
					if ($match[2]) {
						if (isset($_references[$match[2]])) return '<a href="' . $_references[$match[2]] . '">' . $match[1] . "</a>";
						else return $match[1];
					}
					// strip reference from text
					else if ($match[4]) return '';
				}
			},
			$content
		);
		return $content;
	}

	/**
	 * replace inline events, href-javascript and some tags with spechialchars
	 * may break some links but better safe than sorry
	 * 
	 * @param string $content
	 * @param bool $safeMode
	 * @return string
	 */
	private function safeMode($content, $safeMode){
		if ($safeMode) {
			return preg_replace_callback($this->_safeMode,
				function($match){
					return $this->escapeHtml($match[0]);
				},
				$content);
		}
		return $content;
	}

	/**
	 * replace strikethrough
	 * 
	 * @param string $content
	 * @return string
	 */
	private function strikethrough($content){
		return preg_replace($this->_strikethrough,
			"<s>$1</s>",
			$content
		);
	}

	/**
	 * replace subscript
	 * 
	 * @param string $content
	 * @return string
	 */
	private function subscript($content){
		return preg_replace($this->_subscript,
			"<sub>$1</sub>",
			$content
		);
	}

	/**
	 * replace superscript
	 * 
	 * @param string $content
	 * @return string
	 */
	private function superscript($content){
		return preg_replace($this->_superscript,
			"<sup>$1</sup>",
			$content
		);
	}

	/**
	 * replace tables
	 * 
	 * @param string $content
	 * @return string
	 */
	private function table($content){
		$content = preg_replace_callback($this->_table,
			function($match){
				$rows = explode("\n", $match[0]);
				// get possible alignments for colums from delimiter row
				$columns = array_filter(preg_split('/(?<!' . preg_quote('\\', '/'). ')\|/', $rows[1]), fn($c) => boolval(trim($c)));
				$alignment = [];
				foreach($columns as $column){
					preg_match('/(:{0,1})-+(:{0,1})/', trim($column), $align);
					if ($align[1] && $align[2]) $alignment[] = ' align="center"';
					elseif ($align[1]) $alignment[] = ' align="left"';
					elseif ($align[2]) $alignment[] = ' align="right"';
					else $alignment[] = '';
				}
				$output = ($this->TCPDF ? '<br />' : '') . '<table class="eol1_md">';
				$odd = 0; // enable odd class for tcpdf
				foreach($rows as $rowindex => $row){
					if (!$row) continue;
					$columns = preg_split('/(?<!' . preg_quote('\\', '/'). ')\|/', $row);
					array_pop($columns);
					array_shift($columns);
					switch($rowindex){
						case 1:
							break;
						case 0:
							$output .= '<tr' . ($this->TCPDF && !($odd % 2) ? ' class="eol1\_odd"' : '') . '>';
							for ($i = 0; $i < count($columns); $i++){
								$output .= '<th' . ($alignment[$i] ?? '') . '>' . trim($columns[$i]) . '</th>';
							}
							$output .= '</tr>';
							break;
						default:
							$output .= '<tr' . ($this->TCPDF && !($odd % 2) ? ' class="eol1\_odd"' : '') . '>';
							for ($i = 0; $i < count($columns); $i++){
								$output .= '<td' . ($alignment[$i] ?? '') . '>' . trim($columns[$i]) . '</td>';
							}
							$output .= '</tr>';
					}
					$odd ++;
				}
				$output .= "</table>\n";
				return $output;
			},
			$content
		);
		return $content;
	}

	/**
	 * replace tasks with checkboxes unless in TCPDF-mode
	 * 
	 * @param string $content
	 * @return string
	 */
	private function task($content){
		if ($this->TCPDF && $this->TCPDF < 8) return preg_replace_callback($this->_task, // tcpdf 6.x does not support html-checkboxes
			function($match){
				return (trim(strtolower($match[1])) ? '[X]': '[&nbsp;&nbsp;]') . ' ' . $match[2];
			},
			$content
		);

		return preg_replace_callback($this->_task,
			function($match){
				return '<input type="checkbox" disabled' . (trim(strtolower($match[1])) ? ' checked': '') . ' class="eol1_md"> ' . $match[2];
			},
			$content
		);
	}

	/**
	 * replace certain strings with their symbol
	 * 
	 * @param string $content
	 * @return string
	 */
	private function typographer($content){
		return preg_replace_callback($this->_typographer,
			function($match){
				return $this->_typographs[strtolower($match[0])];
			},
			$content
		);
	}
}
?>
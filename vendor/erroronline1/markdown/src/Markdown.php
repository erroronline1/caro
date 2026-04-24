<?php
/**
 * [Markdown](https://github.com/erroronline1/markdown)
 * Copyright (C) 2026 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.  
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.  
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.  
 */

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
	private $_anchor_auto = '/(?<!\]\()(?:\<{0,1})((?:https*|ftps*|tel):(?:\/\/)*[^\n\s,"\'>]+)(?:\>{0,1})/i'; // auto url linking, including some schemes
		private $_anchor_md = '/(?:(?<!!|\\)\[)(.+?)(?:(?<!\\)\])(?:\()(.+?)((?: \").+(?:\"))*(?:(?<!\\)\))(?!\))/m'; // regular md links; rewrite working regex101.com expression on construction for correct escaping of \
	private $_blockquote = '/(^>{1,}.*?\n$)+/ms';
	private $_code_block = '/^ {0,3}([`~]{3})(.*?)\n((?:.|\n)+?)\n^ {0,3}\1\n|^ {4}([^\*\-\d].+)+/m';
		private $_code_inline = '/(?<!\\)(`{1,2})([^\n]+?)(?<!\\| |\n)\1/'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_compress = '/>\n+|\n *<|[^>]\n+<[^\/]/m';
	private $_definition = '/(^.+?\n)((?:^: .+?\n)+)/m';
		private $_emphasis = '/(?<!\\)((?<!\S)\_{1,3}|\*{1,3}(?! ))([^\n]+?)((?<!\\| |\n)\1)/'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_escape = '/\\(\*|-|~|`|\.|@|>|\^|\[|\]|\(|\)|\||=)/'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_footnote = '/\[\^(.+?)\](:.+?\n(?: {4}.*?\n)*)*/';
	private $_headings = '/(?:^)(#+ )(.+?)(?: {#(.+?)}){0,1}(?:#*)$|(?:^)(.+?)\n(={3,}|-{3,})$/m'; // must be first line or have a linebreak before
	private $_horizontal_rule = '/^ {0,3}(?:\-|\- |\*|\* ){3,}$/m';
	private $_image = '/(?:!\[)(.+?)(?:\])(?:\()(.+?)(?:\))([^\)])/';
		private $_inlineEvents = '/on\w+?=(\'|").+?(?<!\\)\1|<(script|title|textarea|style|xmp|iframe|noembed|noframes|plaintext).+?\/\2>|href=(\'|")javascript:.+?(?<!\\)\3/mi'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_larger = '/(?<!\\)\^{2}([^\n]+?)(?<!\\| |\n)\^{2}/'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_linebreak = '/ +\n/';
	private $_list = '/((?:^)(\*|\-|\+|\d+\.) {1,3}(?:.|\n)+?)(?:\n$|\Z)/m';
		private $_mailto = '/([^\s<]+(?<!\\)@[^\s<]+\.[^\s<]+)/'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_mark = '/==(.+?)==/';
	private $_paragraph = '/(?:^$\n|\A)((?<!^<)(?:(\n|.)(?!>$))+?)(?:\n^$|\Z)/mi';
		private $_reference = '/(?:(?<!!|\\)\[)(.+?)(?:(?<!\\)\])(?:\[)(.+?)(?:\])|(?:^\[)([^^]+?)(?:\]:)(.+)$/m'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_strikethrough = '/(?<!\\)~{2}([^\n]+?)(?<!\\| |\n)~{2}/'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_subscript = '/(?<!\\)~{1}([^\n]+?)(?<!\\| |\n)~{1}/'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_superscript = '/(?<!\\)\^{1}([^\n]+?)(?<!\\| |\n)\^{1}/';
	private $_table = '/^((?:\|.+?){1,}\|)\n((?:\| *:{0,1}-+:{0,1} *?){1,}\|)\n((?:(?:\|.+?){1,}\|(?:\n|$))+)/m';
	private $_task = '/\[(\s*x{0,1}\s*)\] (.+?(?:\n|\Z))/mi';
		private $_typographer = '/(?<!\\)\((?:c|r|tm|p)(?<!\\)\)|(?<!\\)\+-|(?<!\\)->/i'; // rewrite working regex101.com expression on construction for correct escaping of \

	// class properties to use over multiple methods
	private $_headers = [];
	private $_references = [];
	private $_limitTo = [];

	// predefined character-sets to replace if required
	private $_escaped = [
		"&" => "&amp;",
		"<" => "&lt;",
		">" => "&gt;",
		'"' => "&quot;",
		"'" => "&#039;",
	];

	private $_typographs = [
		"(c)" => "&copy;",
		"(r)" => "&reg;",
		"(tm)" => "&trade;",
		'(p)' => "&#9413;",
		"+-" => "&#177;",
		"->" => "&rarr;"
	];

	// convert some tags currently not supported by the mentioned library
	private $TCPDF = null;

	/**
	 * instatiate the interface
	 * 
	 * @param bool $TCPDF default null switches some tags for compatibility reasons
	 */
	public function __construct($TCPDF = null)
	{
		// rewrite working regex101.com expression on construction for correct escaping of \
		$this->_anchor_md = '/(?:(?<!!|' . preg_quote('\\', '/') . ')\[)(.+?)(?:(?<!' . preg_quote('\\', '/') . ')\])(?:\()(.+?)((?: \").+(?:\"))*(?:(?<!' . preg_quote('\\', '/') . ')\))(?!\))/m'; // regular md links
		$this->_code_inline = '/(?<!' . preg_quote('\\', '/') . ')(`{1,2})([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)\1/';
		$this->_emphasis = '/(?<!' . preg_quote('\\', '/') . ')((?<!\S)\_{1,3}|\*{1,3}(?! ))([^\n]+?)((?<!' . preg_quote('\\', '/') . '| |\n)\1)/';
		$this->_escape = '/' . preg_quote('\\', '/') . '(\*|-|~|`|\.|@|>|\^|\[|\]|\(|\)|\||=)/';
		$this->_mailto = '/([^\s<]+(?<!' . preg_quote('\\', '/') . ')@[^\s<]+\.[^\s<]+)/';
		$this->_inlineEvents = '/on\w+?=(\'|").+?(?<!' . preg_quote('\\', '/') . ')\1|<(script|title|textarea|style|xmp|iframe|noembed|noframes|plaintext).+?\/\2>|(\'|")javascript:.+?(?<!' . preg_quote('\\', '/') . ')\3/mi';
		$this->_larger = '/(?<!' . preg_quote('\\', '/') . ')\^{2}([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)\^{2}/';
		$this->_reference = '/(?:(?<!!|' . preg_quote('\\', '/') . ')\[)(.+?)(?:(?<!' . preg_quote('\\', '/') . ')\])(?:\[)(.+?)(?:\])|(?:^\[)([^^]+?)(?:\]:)(.+)$/m';
		$this->_strikethrough = '/(?<!' . preg_quote('\\', '/') . ')~{2}([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)~{2}/';
		$this->_subscript = '/(?<!' . preg_quote('\\', '/') . ')~{1}([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)~{1}/';
		$this->_superscript = '/(?<!' . preg_quote('\\', '/') . ')\^{1}([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)\^{1}/';
		$this->_typographer = '/(?<!' . preg_quote('\\', '/') . ')\((?:c|r|tm|p)(?<!' . preg_quote('\\', '/') . ')\)|(?<!' . preg_quote('\\', '/') . ')\+-|(?<!' . preg_quote('\\', '/') . ')->/i';
		$this->TCPDF = boolval($TCPDF); 
	}

	/**
	 * convert a csv-file to markdown table
	 * 
	 * @param string $path filepath to csv
	 * @param array $csv dialect options
	 * @return string|exception Marktown table or exception for lack of rows
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
						// delete bom, convert linebreaks to br
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
	 * @return array|exception [tempfile => string, headers => string] or exception due to lack of identified tables
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
	 * @param bool $safeMode returns anchors as specialchars
	 * @param array $limitTo process only given methods, empty for all
	 * @return string as HTML
	 */
	public function md2html($text, $safeMode = false, $limitTo = []){
		$this->_limitTo = $limitTo;
		$text = preg_replace(['/\r/','/\t/'], ['', '    '], $text ?: '') . "\n"; // add a new line for improved pattern matching by default

		foreach ([
			"footnote", // should come first to avoid mishandling indentation and reutilizing list and superscript
			"blockquote", // should come second to enable nesting
			"reference", // before a and footnote to not mess up with similar patterns
			"headings", // before hr avoiding conversion of ----
			"horizontal_rule", // before emphasis avoiding matching *** as emphasis
			"definition",
			"task", // before list otherwise only the first occasionally nested item is converted
			"list",
			"code", // after list to avoid erroneous indentation matching
			"anchor", // safeMode can not render anchors to avoid malicious scripts
			"mailto", // safeMode can not render anchors to avoid malicious scripts
			"emphasis",
			"image",
			"mark",
			"strikethrough",
			"larger", // before superscript for using the same character twice THIS IS A CUSTOM MARKDOWN PROPERTY TO THIS FLAVOUR
			"subscript",
			"superscript",
			"table",
			"typographer",
			"paragraph", // must come after anything previous to not mess up pattern recognitions relying on linebreaks and filtering out previously converted tags
			"linebreak",
			"inlineEvents", // safeMode can not render inline events and scripts to avoid malicious inserts
		] as $method) {
			if (!$limitTo || in_array($method, $limitTo) || ($safeMode && in_array($method, ["anchor", "inlineEvents", "reference", "mailto"]))) {
				if (in_array($method, ["anchor", "inlineEvents", "reference", "mailto"])) $text = $this->$method($text, $safeMode);
				else $text = $this->$method($text);
			}
		}

		$text = $this->escape($text); // should come after other stylings have been applied
		$text = $this->compress($text);
		$text = preg_replace(['/\t/'], ['    '], $text); // revert code indentation

		return "<!-- Markdown parsing by error on line 1, https://github.com/erroronline1/markdown -->\n" . $text;
	}

	/**
	 * escape special chars for code, pre and in case of safeMode
	 * 
	 * @param string $content
	 * @return string
	 */
	private function escapeHtml($content){
		return str_replace(array_keys($this->_escaped), array_values($this->_escaped), $content);
	}

	/**
	 * development helper...
	 */
	private function debug(...$content){
		echo "<pre>"; var_dump(...$content); echo "</pre>";
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
		$content = preg_replace_callback($this->_anchor_auto,
			function($match) use ($safeMode, $_references){
				if (in_array($match[1], $_references)) return $match[1]; // avoid duplication of link creation from references
				if (str_starts_with($match[1],'#')) return '<a href="' . $match[1] . '" class="eol1_md">' . htmlspecialchars($match[1]) . '</a>';
				if ($safeMode) return htmlspecialchars($match[0]);
				return '<a href="' . $match[1] . '" class="eol1_md">' . htmlspecialchars($match[1]) . '</a>';
			},
			$content);
		$content = preg_replace_callback($this->_anchor_md,
			function($match) use ($safeMode){
				if (str_starts_with($match[2], '#')) return '<a href="' . $match[2] . '" class="eol1_md">' . htmlspecialchars($match[1]) . '</a>';
				if ($safeMode) return htmlspecialchars($match[0]);
				$url = '';
				if (str_starts_with($match[2], 'javascript:')) $url = $match[2];
				else {
					$component = parse_url($match[2]);
					if (isset($component['query'])){
						parse_str($component['query'], $query);
						$url = substr($match[2], 0, strpos($match[2], '?')) . '?' . http_build_query($query);
					}
					else $url = $match[2];
					//$url .= '" target="_blank';
				}
				if (isset($match[3]) && $match[3]) $url .= '" title="' . substr($match[3], 2, -1);
				return '<a href="' . $url . '" class="eol1_md">' . $match[1] . '</a>';
			},
			$content
		);
		return $content;
	}

	/**
	 * replace blockquotes recursively
	 * 
	 * @param string $content
	 * @param bool $recursion for altered behaviour on pattern relevant linbreak wrappers
	 * @return string
	 */
	private function blockquote($content, $recursion = false){
		$content = preg_replace_callback($this->_blockquote,
			function($match) use ($recursion){
				$match[0] = $this->blockquote(preg_replace(['/^> {0,1}|^ /m'], '', $match[0]), true); // remove blockquote character and possible whitespace and check recursively for nested blockquotes
				if ($recursion) return '<blockquote class="eol1_md"><p>' . $match[0] . "</p></blockquote>"; // fence with tags
				return '<blockquote class="eol1_md"><p>' . "\n" . $match[0] . "\n</p></blockquote>\n";
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
		$content = preg_replace_callback($this->_code_block,
			function($match){
				// $match[2] for fenced code would be a specified language, not sure what to do with that yet
				// if match[4] code blocks are written with pure indentation
				$code = isset($match[4]) ? preg_replace('/^ {4}/m', '', $match[0]) : $match[3];
				return '<pre class="eol1_md">' . (!$this->TCPDF ? '<code class="eol1_md">' : '') . str_replace('    ', "\t", $this->escapeHtml($code)) . (!$this->TCPDF ? '</code>' : '') . "</pre>"; // replace 4 spaces within code with tabs to avoid collision with pre
			},
			$content);

		if ($this->TCPDF) {
			$content = preg_replace_callback($this->_code_inline,
				function($match){
					return '<span style="font-family: monospace;">' . $this->escapeHtml($match[2]) . '</span>'; // current implementation of tcpdf does not support code
				},
				$content
			);
		}
		else {
			$content = preg_replace_callback($this->_code_inline,
				function($match){
					return '<code class="eol1_md">' . $this->escapeHtml($match[2]) . '</code>';
				},
				$content
			);
		}
		return $content;
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
			function($match) {
				// check whether **opening and closing*** match (or underscore respectively)
				$wrapper = strlen($match[1]);
				$tags = [
					[], // wrapper offset, easier than reducing index
					['<em>', '</em>'],
					['<strong>', '</strong>'],
					['<em><strong>', '</strong></em>']
				];
				return $tags[$wrapper][0] . $this->emphasis($match[2]) . $tags[$wrapper][1];
			},
			$content
		);
	}

	/**
	 * replace escaped characters
	 * 
	 * @param string $content
	 * @return string
	 */
	private function escape($content){
		return preg_replace($this->_escape,
			'$1',
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
			foreach([
				'blockquote',
				'code',
				'definition',
				'table',
			] as $method){
				if (!$this->_limitTo || in_array($method, $this->_limitTo)){
					if (in_array($method, ["blockquote"])) $footnote_block = $this->$method($footnote_block, true);
					else $footnote_block = $this->$method($footnote_block);
				}
			};

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
			$footnote_appendix .= '1. <a id="fn:' . $key . '" class="eol1_md"></a>' . stripslashes(trim($footnote)) . ' <a href="#fnref:' . $key . '" class="eol1_md">&crarr;</a>' . "  \n";
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
					$id = strtolower(preg_replace(['/\s/'], ['-'], trim(!empty($match[3]) ? $match[3] : $id)));
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
				return "\n<h" . $size . ' id="' . $id . '">' . $heading . '</h' . $size . ">";
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
		if ($this->TCPDF) return preg_replace($this->_image,
				'<img alt="$1" src="$2" style="float:left; max-width:100%" />',
				$content
			);
		return preg_replace($this->_image,
			'<img alt="$1" src="$2" class="eol1_md" />',
			$content
		);

	}

	/**
	 * replace inline events, href-javascript and some tags with spechialchars
	 * may break some links but better safe than sorry
	 * 
	 * @param string $content
	 * @param bool $safeMode
	 * @return string
	 */
	private function inlineEvents($content, $safeMode){
		if ($safeMode) {
			return preg_replace_callback($this->_inlineEvents,
				function($match){
					return htmlspecialchars($match[0]);
				},
				$content);
		}
		return $content;
	}

	/**
	 * replace lager font decorator
	 * THIS IS A CUSTOM MARKDOWN PROPERTY TO THIS FLAVOUR
	 * 
	 * @param string $content
	 * @return string
	 */
	private function larger($content){
		return preg_replace($this->_larger,
			'<span class="eol1_md" style="font-size:larger;">$1</span>',
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
	 * @param bool|Generator $recursion for passing down a generator for ol list styles
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
						$entries[] = ($this->TCPDF && $li_type === 'ol' ? str_repeat('&nbsp;', 3) : '') . trim($list_entry);
					}
				}
				return '<' . $li_type . $type . $offset . ' class="eol1_md"><li>' . implode('</li><li>', $entries) . '</li></' . $li_type . '>';
			},
			$content
		);
		if ($recursion){
			// replace possible nested blocks in list item
			foreach([
				'blockquote',
				'code',
				'definition',
				'table',
			] as $method){
				if (!$this->_limitTo || in_array($method, $this->_limitTo)){
					if (in_array($method, ["blockquote"])) $content = $this->$method($content, true);
					else $content = $this->$method($content);
				}
			};
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
		if ($safeMode) {
			return preg_replace_callback($this->_mailto,
				function($match){
					return htmlspecialchars(($match[0]));
				},
				$content);
		}

		return preg_replace_callback($this->_mailto,
			function($match){
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
		return preg_replace($this->_paragraph,
			"<p>$1</p>\n",
			$content);
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
				$alignment = [null]; // offset for array_keys($columns) later 
				foreach($columns as $column){
					preg_match('/(:{0,1})-+(:{0,1})/', trim($column), $align);
					if ($align[1] && $align[2]) $alignment[] = ' align="center"';
					elseif ($align[1]) $alignment[] = ' align="left"';
					elseif ($align[2]) $alignment[] = ' align="right"';
					else $alignment[] = '';
				}
				$table = [];
				foreach($rows as $rowindex => $row){
					if (!$row) continue;
					$columns = array_filter(preg_split('/(?<!' . preg_quote('\\', '/'). ')\|/', $row), fn($c) => boolval(trim($c)));
					switch($rowindex){
						case 1:
							break;
						case 0:
							$table[] = '<tr' . ($this->TCPDF && !(count($table) % 2) ? ' class="eol1_odd"' : '') . '>' . implode('', array_map(fn($i, $column) => '<th' . ($alignment[$i] ?? '') . '>' . trim($column) . '</th>', array_keys($columns), $columns)) . '</tr>';
							break;
						default:
							$table[] = '<tr' . ($this->TCPDF && !(count($table) % 2) ? ' class="eol1_odd"' : '') . '>' . implode('', array_map(fn($i, $column) => '<td' . ($alignment[$i] ?? '') . '>' . trim($column) . '</td>', array_keys($columns), $columns)) . '</tr>';
					}
				}
				$output = ($this->TCPDF ? '<br />' : '') . '<table class="eol1_md">';
				$output .= implode('', $table);
				$output .= '</table>';
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
		if ($this->TCPDF) return preg_replace_callback($this->_task, // current implementation of tcpdf does not support html-checkboxes
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
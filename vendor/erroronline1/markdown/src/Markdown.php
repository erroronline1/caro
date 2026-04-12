<?php
/**
 * [Markdown](https://github.com/erroronline1/markdown)
 * Copyright (C) 2026 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.  
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.  
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.  
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

namespace erroronline1\Markdown;

class Markdown {
	private $_a_auto = '/(?<!\]\()(?:\<{0,1})((?:https*|ftps*|tel):(?:\/\/)*[^\n\s,>]+)(?:\>{0,1})/i'; // auto url linking, including some schemes
		private $_a_md = '/(?:(?<!!|\\)\[)(.+?)(?:(?<!\\)\])(?:\()(.+?)((?: \").+(?:\"))*(?:(?<!\\)\))([^\)]|$)/m'; // regular md links
	private $_blockquote = '/(^>{1,} .*(?:\n|$|\Z))+/m';
	private $_br = '/ +\n/';
	private $_code_block = '/^ {0,3}([`~]{3}.*?)\n((?:.+?\n)+)^ {0,3}([`~]{3})\n/m';
		private $_code_inline = '/(?<!\\)(`{1,2})([^\n]+?)(?<!\\| |\n)\1/'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_definition = '/(^.+?\n)((?:^: .+?\n)+)/m';
		private $_emphasis = '/(?<!\\)((?<!\S)\_{1,3}|\*{1,3}(?! ))([^\n]+?)((?<!\\| |\n)\1)/'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_escape = '/\\(\*|-|~|`|\.|@|>|\^|\[|\]|\(|\)|\|)/'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_footnote = '/\[\^(.+?)\](:.+?\n(?: {4}.*?\n)*)*/';
	private $_headings = '/(?:\A|^\n+^)(#+ )(.+?)(?: {#(.+?)}){0,1}(?:#*)$|(?:^\n*)(.+?)\n(={3,}|-{3,})$/m'; // must be first line or have a linebreak before
	private $_hr = '/^ {0,3}(?:\-|\- |\*|\* ){3,}$/m';
	private $_img = '/(?:!\[)(.+?)(?:\])(?:\()(.+?)(?:\))([^\)])/';
		private $_inlineEvents = '/on\w+?=(\'|").+?(?<!\\)\1|<(script|title|textarea|style|xmp|iframe|noembed|noframes|plaintext).+?\/\2>|href=(\'|")javascript:.+?(?<!\\)\3/mi'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_larger = '/(?<!\\)\^{2}([^\n]+?)(?<!\\| |\n)\^{2}/'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_list_any = '/((?:^ {0,3})(\*|\-|\+|\d+\.) (?:.|\n)+?)(?:\n$|\Z)/mi';
	private $_list_indented = '/\n(^ {4}.+?\n)+/m';
	private $_list_line = '/(^ {0,3}(\*|\-|\+|\d+\.) )*(.+)/';
		private $_mail = '/([^\s<]+(?<!\\)@[^\s<]+\.[^\s<]+)/'; // rewrite working regex101.com expression on construction for correct escaping of \
	private $_mark = '/==(.+?)==/';
	private $_p = '/(?:^$\n|\A)((?<!^<)(?:(\n|.)(?!>$))+?)(?:\n^$|\Z)/mi';
	private $_pre = '/^ {4}([^\*\-\d].+)+/m';
		private $_s = '/(?<!\\)~{2}([^\n]+?)(?<!\\| |\n)~{2}/'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_sub = '/(?<!\\)~{1}([^\n]+?)(?<!\\| |\n)~{1}/'; // rewrite working regex101.com expression on construction for correct escaping of \
		private $_sup = '/(?<!\\)\^{1}([^\n]+?)(?<!\\| |\n)\^{1}/';
	private $_table = '/^((?:\|.+?){1,}\|)\n((?:\| *:{0,1}-+:{0,1} *?){1,}\|)\n(((?:\|.+?){1,}\|(?:\n|$))+)/m';
	private $_task = '/\[(\s*x{0,1}\s*)\] (.+?(?:\n|\Z))/mi';
	private $_tidy_nl = '/>\n+|\n *<|[^>]\n+<[^\/]/m';

	private $_headers = [];
	private $_headerchars = '/[\w\d\-\sÄÖÜäöüßêÁáÉéÍíÓóÚúÀàÈèÌìÒòÙù]+/';

	// convert some tags currently nocht supported by the mentioned library
	private $TCPDF = null;

	/**
	 * instatiate the interface
	 * @param bool $TCPDF default null switches some tags for compatibility reasons
	 */
	public function __construct($TCPDF = null)
	{
		// rewrite working regex101.com expression on construction for correct escaping of \
		$this->_a_md = '/(?:(?<!!|' . preg_quote('\\', '/') . ')\[)(.+?)(?:(?<!' . preg_quote('\\', '/') . ')\])(?:\()(.+?)((?: \").+(?:\"))*(?:(?<!' . preg_quote('\\', '/') . ')\))([^\)]|$)/m'; // regular md links
		$this->_code_inline = '/(?<!' . preg_quote('\\', '/') . ')(`{1,2})([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)\1/';
		$this->_emphasis = '/(?<!' . preg_quote('\\', '/') . ')((?<!\S)\_{1,3}|\*{1,3}(?! ))([^\n]+?)((?<!' . preg_quote('\\', '/') . '| |\n)\1)/';
		$this->_escape = '/' . preg_quote('\\', '/') . '(\*|-|~|`|\.|@|>|\^|\[|\]|\(|\)|\|)/';
		$this->_mail = '/([^\s<]+(?<!' . preg_quote('\\', '/') . ')@[^\s<]+\.[^\s<]+)/';
		$this->_inlineEvents = '/on\w+?=(\'|").+?(?<!' . preg_quote('\\', '/') . ')\1|<(script|title|textarea|style|xmp|iframe|noembed|noframes|plaintext).+?\/\2>|(\'|")javascript:.+?(?<!' . preg_quote('\\', '/') . ')\3/mi';
		$this->_larger = '/(?<!' . preg_quote('\\', '/') . ')\^{2}([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)\^{2}/';
		$this->_s = '/(?<!' . preg_quote('\\', '/') . ')~{2}([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)~{2}/';
		$this->_sub = '/(?<!' . preg_quote('\\', '/') . ')~{1}([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)~{1}/';
		$this->_sup = '/(?<!' . preg_quote('\\', '/') . ')\^{1}([^\n]+?)(?<!' . preg_quote('\\', '/') . '| |\n)\^{1}/';

		$this->TCPDF = boolval($TCPDF); 

	}

	/**
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
						$bom = pack('H*','EFBBBF'); //coming from excel this is utf8
						// delete bom, convert linebreaks to space
						$column = preg_replace(["/^$bom/", '/\n/'], ['',' '], $column);
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
	 * @param string $text Markdown styled
	 * @param bool $safeMode returns anchors as specialchars
	 * @return string as HTML
	 */
	public function md2html($text, $safeMode = false){
		$text = preg_replace(['/\r/','/\t/'], ['', '    '], $text ?: '') . "\n"; // add a new line for improved pattern matching by default

		// ensure a proper processing order
		$text = $this->footnote($text); // should come first to avoid mishandling indentation and reutilizing list and sup
		$text = $this->blockquote($text); // should come second to enable nesting
		$text = $this->a($text, $safeMode); // safeMode can not render anchors to avoid malicious scripts
		$text = $this->code($text);
		$text = $this->headings($text); // before hr avoiding conversion of ----
		$text = $this->hr($text); // before emphasis avoiding matching *** as emphasis
		$text = $this->definition($text);
		$text = $this->emphasis($text);
		$text = $this->img($text);
		$text = $this->task($text); // before list otherwise only the first occasionally nested item is converted
		$text = $this->list($text);
		$text = $this->mail($text, $safeMode); // safeMode can not render anchors to avoid malicious scripts
		$text = $this->mark($text);
		$text = $this->pre($text);
		$text = $this->s($text);
		$text = $this->larger($text); // before sup for using the same character twice
		$text = $this->sub($text);
		$text = $this->sup($text);
		$text = $this->table($text);
		$text = $this->p($text); // must come after anything previous to not mess up pattern recognitions relying on linebreaks and filtering out previously converted tags
		$text = $this->br($text);
		$text = $this->inlineEvents($text, $safeMode); // safeMode can not render inline events and scripts to avoid malicious inserts

		$text = $this->escape($text); // should come after other stylings have been applied
		$text = $this->tidy_nl($text);

		return "<!-- Markdown parsing by error on line 1, https://github.com/erroronline1/markdown -->\n" . $text;
	}

	private function debug(...$content){
		echo "<pre>"; var_dump(...$content); echo "</pre>";
	}

	private function tidy_nl($content){
		// replace links in this order
		return preg_replace_callback($this->_tidy_nl,
			function($match){
				return preg_replace('/[\n\s]+/', ' ', $match[0]);
			},
			$content);
	}

	private function a($content, $safeMode = false){
		// replace links in this order
		$content = preg_replace_callback($this->_a_auto,
			function($match) use ($safeMode){
				if (str_starts_with($match[1],'#')) return '<a href="' . $match[1] . '" class="eol1_md">' . htmlspecialchars($match[1]) . '</a>';
				if ($safeMode) return htmlspecialchars($match[0]);
				return '<a href="' . $match[1] . '" class="eol1_md">' . htmlspecialchars($match[1]) . '</a>';
				//return '<a href="' . $match[0] . '" target="_blank" class="eol1_md">' . $match[0] . '</a>';
			},
			$content);
		$content = preg_replace_callback($this->_a_md,
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
				return '<a href="' . $url . '" class="eol1_md">' . $match[1] . '</a>' . $match[4];
			},
			$content
		);
		return $content;
	}

	private function blockquote($content, $sub = false){
		// replace blockquotes recursively
		$content = preg_replace_callback($this->_blockquote,
			function($match) use ($sub){
				$match[0] = $this->blockquote(preg_replace(['/^\n|\n$/', '/^> {0,1}|^ /m'], '', $match[0]), $sub); // remove leading and trailing linebreak, blockquote character and possible whitespace and check recursively for nested blockquotes
				if ($sub) return '<blockquote class="eol1_md">' . $match[0] . '</blockquote>'; // fence with tag
				return "<blockquote class=\"eol1_md\">\n" . $match[0] . "\n</blockquote>\n"; // fence with tag, add linebreak for pattern recognition
			},
			$content
		);
		return $content;
	}

	private function br($content){
		// replace linebreaks
		return preg_replace($this->_br,
			"<br />",
			$content
		);
	}

	private function code($content, $sub = false){
		// replace code
		$content = preg_replace_callback($this->_code_block,
			function($match) use ($sub){
				if ($match[1] == $match[3])	return '<pre class="eol1_md">' . str_replace(['&', '<', '>', '"', '\''], ['&amp;', '&lt;', '&gt;', '&quot;', '&#039;'], preg_replace('/^\n|\n$/m', '', $match[2])) . "</pre>" . ($sub ? '' : "\n");
				return $match[0];
			},
			$content);

		if ($this->TCPDF) {
			$content = preg_replace_callback($this->_code_inline,
				function($match){
					return '<span style="font-family: monospace;">' . str_replace(['&', '<', '>', '"', '\''], ['&amp;', '&lt;', '&gt;', '&quot;', '&#039;'], $match[2]) . '</span>'; // current implementation of tcpdf does not support code
				},
				$content
			);
		}
		else {
			$content = preg_replace_callback($this->_code_inline,
				function($match){
					return '<code class="eol1_md">' . str_replace(['&', '<', '>', '"', '\''], ['&amp;', '&lt;', '&gt;', '&quot;', '&#039;'], $match[2]) . '</code>';
				},
				$content
			);
		}
		return $content;
	}

	private function definition($content){
		// create a definition block
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

	private function emphasis($content){
		// replace all em and strong formatting
		return preg_replace_callback($this->_emphasis, 
			function($match) {
				// check whether **opening and closing*** match
				$wrapper = strlen($match[1]);
				$tags = [
					[], // wrapper offset, easier than reducing index
					['<em>', '</em>'],
					['<strong>', '</strong>'],
					['<em><strong>', '</strong></em>']
				];
				return $tags[$wrapper][0] . $match[2] . $tags[$wrapper][1];
			},
			$content
		);
	}

	private function escape($content){
		// replace escaped characters
		return preg_replace($this->_escape,
			'$1',
			$content
		);
	}

	private function footnote($content){
		// create footnotes
		// find all footnotes
		preg_match_all($this->_footnote, $content, $footnotes);
		$_footnotes = [];
		
		foreach($footnotes[1] as $key => $value){
			$_footnotes[strval($value)] = preg_replace(['/\n/m', '/^: |^ {4}/m'], ["<br />", ''], ($footnotes[2][$key] ?: ''));
		}
		$content = preg_replace_callback($this->_footnote, 
			function($match) use ($_footnotes){
				// inline links if available as md superscript
				if (empty($match[2])){
					if (empty($_footnotes[$match[1]])) return '^' . $match[1] . '^';
					$key = array_search($match[1], array_keys($_footnotes)) + 1;
					return '^<a id="fnref:' . $key . '" href="#fn:' . $key . '" class="eol1_md">' . $key . '</a>^';
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

	private function headings($content){
		// replace headers
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
				preg_match($this->_headerchars, $heading, $id);

				if (!empty($match[3]) /*custom id*/ || !empty($id[0])){
					$id = strtolower(preg_replace(['/\s/'], ['-'], trim(!empty($match[3]) ? $match[3] : $id[0])));
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

	private function hr($content){
		// replace hr	
		return preg_replace($this->_hr,
			"<hr>",
			$content
		);
	}
	
	private function img($content){
		// replace images
		if ($this->TCPDF) return preg_replace($this->_img,
				'<img alt="$1" src="$2" style="float:left; max-width:100%" />',
				$content
			);
		return preg_replace($this->_img,
			'<img alt="$1" src="$2" class="eol1_md" />',
			$content
		);

	}

	private function inlineEvents($content, $safeMode){
		// replace onclick, on-whatever with specialchars
		if ($safeMode) {
			return preg_replace_callback($this->_inlineEvents,
				function($match){
					return htmlspecialchars($match[0]);
				},
				$content);
		}
		return $content;
	}

	private function larger($content){
		// make font size larger - CUSTOM MARKDOWN
		return preg_replace($this->_larger,
			'<span class="eol1_md" style="font-size:larger;">$1</span>',
			$content
		);
	}

	private function list($content, $sub = false){
		// detect any lists
		// recursively replace nested lists
		$content = preg_replace_callback($this->_list_any,
			function($match){
				// check lists for subelements, lists, blockquote, code, table or pre
				return preg_replace_callback($this->_list_indented,
					function($indented){
						return preg_replace('/^\n/', '', $this->list(preg_replace('/^ {4}/m', '', $indented[0] . "\n"), true));  // drop leading linebreak, but add one to end for pattern recognition
					},
					$match[0]
				);
			},
			$content
		);
		if ($sub){
			// replace possible nested blocks in advance to list matching
			$content = $this->blockquote($content, true);
			$content = $this->code($content, true);
			$content = $this->definition($content);
			$content = $this->table($content);
			$content = $this->pre($content);
		}

		$content = preg_replace_callback($this->_list_any,
			function($match){
				// first list item decides for the type
				$type = intval($match[2]) > 0 ? 'ol' : 'ul';
				$entries = [];
				foreach(explode("\n", $match[1]) as $line){
					preg_match($this->_list_line, $line, $list_line);
					if ($list_line){
						// add some whitespace fpr TCPDF because ordered lists are a bit misaligned, and I couldn't fix that with styling
						if (empty($entries[count($entries) - 1]) || !empty($list_line[2])) $entries[] = ($this->TCPDF && $type === 'ol' ? str_repeat('&nbsp;', 3) : '') .$list_line[3] . "\n"; // add trailing linebreak to preserve pattern recognition
						else $entries[count($entries) - 1] .= ' '. $list_line[3] . "\n"; // add trailing linebreak to preserve pattern recognition
					}
				}
				return '<' . $type . ' class="eol1_md"><li>' . implode('</li><li>', $entries) . '</li></' . $type . '>';
			},
			$content
		);
		return $content;
	}

	private function mail($content, $safeMode){
		// replace mailto
		if ($safeMode) {
			return preg_replace_callback($this->_mail,
				function($match){
					return htmlspecialchars(($match[0]));
				},
				$content);
		}

		return preg_replace_callback($this->_mail,
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

	private function mark($content){
		// replace mark
		if ($this->TCPDF) return preg_replace($this->_mark,  // current implementation of tcpdf does not support mark
			"<span style=\"background-color:yellow\">$1</span>",
			$content);

		return preg_replace($this->_mark,
			'<mark class="eol1_md">$1</mark>',
			$content);
	}

	private function p($content){
		// replace p
		return preg_replace($this->_p,
			"<p>$1</p>\n",
			$content);
	}

	private function pre($content){
		// replace code/pre
		$content = preg_replace_callback($this->_pre,
			function($match){
				return '<pre class="eol1_md">' . str_replace(['&', '<', '>', '"', '\''], ['&amp;', '&lt;', '&gt;', '&quot;', '&#039;'], preg_replace('/^ {4}/m', '', $match[0])) . "</pre>";
			},
			$content
		);
		return $content;
	}

	private function s($content){
		// replace s
		return preg_replace($this->_s,
			"<s>$1</s>",
			$content
		);
	}

	private function sub($content){
		// replace sub
		return preg_replace($this->_sub,
			"<sub>$1</sub>",
			$content
		);
	}

	private function sup($content){
		// replace sup
		return preg_replace($this->_sup,
			"<sup>$1</sup>",
			$content
		);
	}

	private function table($content){
		// replace tables
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

	private function task($content){
		//replace tasks
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
}
?>
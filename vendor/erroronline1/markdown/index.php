<!DOCTYPE html>
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: © 2026 error on line 1 <dev@erroronline.one> -->
<!-- SPDX-FileNotice: Part of erroronline1/markdown parser for PHP & ECMA-Script. -->
<?php
/*
quick preview and benchmark for markdown libraries in two languages
*/
require_once('./src/Markdown.php');

$defaultSample = file_get_contents('./sample.md');

$methods = [
		"code", // must come first to enable escaping to avoid unintended conversion
		//"safeMode", // prior to tasks, definition and reference avoiding invalidation of allowed input and anchors
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

class md extends \erroronline1\Markdown\Markdown{
	public function __construct($TCPDF = 0){
		parent::__construct($TCPDF);
		array_push($this->_methodsInProcessingOrder, "markdown");
	}
	public function markdown($content = ''){
		return preg_replace('/markdown/i', "nwobʞɿɒM", $content);
	}
}

$selectedMethods = [];
foreach($methods as $method){
	if ($_POST[$method] ?? false) $selectedMethods[] = $method;
}
sort($methods);

$sample = $_POST['input'] ?? $defaultSample;
$safeMode = '';
switch ($_SERVER['REQUEST_METHOD']) {
	case 'POST':
		$safeMode = !empty($_POST['safeMode']) ? 'checked' : '';
		$customClass = !empty($_POST['customClass']) ? 'checked' : '';
		$customStyling = !empty($_POST['customStyling']) ? 'checked' : '';
		break;
	default:
		$safeMode = 'checked';
		$customClass = '';
		$customStyling = '';
		break;
}

$start = microtime(true);
if ($customClass) $markdown = new md();
else $markdown = new \erroronline1\Markdown\Markdown();
$PHPMarkdown = $markdown->md2html($sample, boolval($safeMode), $selectedMethods);
$end = microtime(true);
?>
<html lang="de">
<style>
	body {
		background-color: #e5e9f0;
		color: #2e3440;
		font-family: Arial, Helvetica, sans-serif;
	}
	th:not([class]), td:not([class]) {
		width:33vw;
		max-width:33vw;
		vertical-align: top;
		padding: 1em 2em;
	}

	td:not([class]):nth-of-type(2){
		border-left: 1px dashed #5e81ac;
		border-right: 1px dashed #5e81ac;
	}
	textarea {
		width: 100%;
		height: 60vh;
		border: 1px dashed #5e81ac;
		background-color: #eceff4;
		font-size: larger;
		padding:1em;
	}
	label {
		display:inline-block;
		padding:.3em;
		width: 10rem;
		input{
			vertical-align: middle;
			width: 2em;
			height:2em;
		}
	}
	input[type="submit"], input[type="button"] {
		margin:.5em;
		padding:.75em;
		width: 10rem;
		font-size: 1rem;
	}

	table.eol1_md {
		th,
		td:not([class]) {
			border: 1px solid rgba(67, 76, 94, .8);
			padding: 1em;
		}

		th {
			background-color: rgba(67, 76, 94, .3);
		}
	}

	blockquote.eol1_md {
		margin: auto;
		border-left: .2em solid #8fbcbb;
		padding-left: .5em;
	}

	<?php
	if ($customStyling) echo <<<END

	td:not([class]):nth-of-type(2),
	td:not([class]):nth-of-type(3) {
		line-height: 1.7;
		h1, h2, h3, h4 {
			line-height: normal
		}
		input[type="checkbox"]{
			appearance:none;
			width: 1.5em;
		}
		input[type="checkbox"]::after {
			content: "";
			position: absolute;
			margin-top: -1em;
			width: 1.5em;
			height: 1.5em;
			border-radius: 50%;
			background-color: #bf616a;
		}
		input[type="checkbox"]:checked::after {
			background-color: #a3be8c;
		}
		img {
			vertical-align: middle;
		}
		a {
			text-decoration: none;
		}
	}

	pre, code:not(:has(pre)) {
		padding: .3em;
		border-radius: .2em;
		background: #434c5e;
		color: #a3be8c;
		line-height:1.8em;
		overflow: scroll;
	}

	table.eol1_md {
	    border-spacing: 0;
	    border-collapse: collapse;
		th,
		td:not([class]) {
			border: none;
		}
		td {
			min-width: fit-content;
			border-top: 1px solid rgba(67, 76, 94, .8) !important;
		}
		tr:nth-child(odd){
			background-color: #d8dee9;
		}
	}
	
	ul {
		list-style-type: square;
	}

	dt {
		font-weight: bold;
	}
	dd {
		font-style: oblique;
	}

	END;
	?>
</style>

<body>
	<table>
		<tr>
			<th>
				Input
			</th>
			<th>
				PHP (<?= round(($end - $start) * 1000, 2); ?> ms)
			</th>
			<th id="scriptheader">
				ECMAScript
			</th>
		</tr>
		<tr>
			<td>
				<form method="post">
					<textarea name="input"><?= $sample; ?></textarea><br />
					<label><input type="checkbox" name="safeMode" <?= $safeMode; ?> /> safeMode</label><br />
					<?php
						foreach ($methods as $method) {
							echo '<label><input type="checkbox" name="' . $method. '" ' . (in_array($method, $selectedMethods) ? 'checked': '') . ' /> ' . $method. '</label>';
						}
					?><br />
					<label style="width:20rem"><input type="checkbox" name="customClass" <?= $customClass; ?> /> custom PHP class demo</label><br />
					<label style="width:20rem"><input type="checkbox" name="customStyling" <?= $customStyling; ?> /> add a bit of styling</label><br />
					<input type="button" onclick="std_settings()" value="standard of others" title="still a bit more but this is not that granulary" /><br />
					<input type="submit" value="Submit" />
				</form>
				Minimal styling on output for comprehension and eye soothing only. Most is default browser behaviour though unless you tick the box above.
				<br><br>
				<a href="https://github.com/erroronline1/markdown"><svg class="tileimg" fill="currentColor" viewBox="0 0 16 16" style="width:1em; height:1em; vertical-align:middle" version="1.1" aria-hidden="true"><path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0 0 16 8c0-4.42-3.58-8-8-8z"></path></svg> Sourcecode on GitHub</a>
			</td>
			<td>
				<?= $PHPMarkdown; ?>
			</td>
			<td id="scriptcolumn">
			</td>
		</tr>
	</table>
</body>

<script type="module">
	import {
		Markdown
	} from "./src/Markdown.js";
	const MARKDOWN = new Markdown();
	const start = performance.now();
	const content = MARKDOWN.md2html(<?= json_encode($sample, JSON_UNESCAPED_UNICODE); ?>, <?= boolval($safeMode) ? 'true' : 'false'; ?>, [<?= implode(', ', array_map(fn($v) => '"' . $v . '"', $selectedMethods)); ?>]);
	document.getElementById("scriptheader").innerHTML += " (" + (performance.now() - start).toFixed(2) + " ms)";
	document.getElementById("scriptcolumn").innerHTML = content;
</script>
<script>
	function std_settings(){
		[<?= implode(', ', array_map(fn($v) => '"' . $v . '"', $methods)); ?>].forEach(e => {
			document.getElementsByName(e)[0].checked = [
				"code",
				"blockquote",
				"reference",
				"headings",
				"horizontal_rule",
				"list",
				"table",
				"image",
				"anchor",
				"strikethrough",
				"emphasis",
				"paragraph",
				"linebreak",
			].includes(e);
		});
	}
</script>

</html>
<?php
/*
quick preview and benchmark for markdown libraries in two languages
*/
$defaultSample = file_get_contents('./sample.md');

$methods = [
	"footnote", // should come first to avoid mishandling indentation and reutilizing list and superscript
	"blockquote", // should come second to enable nesting
	"reference", // before a and footnote to not mess up with similar patterns
	"anchor", // safeMode can not render anchors to avoid malicious scripts
	"code",
	"headings", // before hr avoiding conversion of ----
	"horizontal_rule", // before emphasis avoiding matching *** as emphasis
	"definition",
	"emphasis",
	"image",
	"task", // before list otherwise only the first occasionally nested item is converted
	"list",
	"mailto", // safeMode can not render anchors to avoid malicious scripts
	"mark",
	"strikethrough",
	"larger", // before superscript for using the same character twice
	"subscript",
	"superscript",
	"table",
	"typographs",
	"paragraph", // must come after anything previous to not mess up pattern recognitions relying on linebreaks and filtering out previously converted tags
	"linebreak",
	//"inlineEvents", // safeMode can not render inline events and scripts to avoid malicious inserts
];

$selectedMethods = [];
foreach($methods as $method){
	if ($_POST[$method] ?? false) $selectedMethods[] = $method;
}
require_once('./src/Markdown.php');

$sample = $_POST['input'] ?? $defaultSample;
$safeMode = '';
switch ($_SERVER['REQUEST_METHOD']) {
	case 'POST':
		$safeMode = !empty($_POST['safeMode']) ? 'checked' : '';
		break;
	default:
		$safeMode = 'checked';
		break;
}

$start = microtime(true);
$markdown = new \erroronline1\Markdown\Markdown();
$PHPMarkdown = $markdown->md2html($sample, boolval($safeMode), $selectedMethods);
$end = microtime(true);

sort($methods);
?>

<html>
<style>
	tr:not([class]), td:not([class]) {
		width:33vw;
		vertical-align: top;
		padding: 2em;
		border-right: 1px solid rgba(0, 0, 0, .8);
	}

	textarea {
		width: 100%;
		height: 60vh;
		border-color: rgba(0, 0, 0, .5);
	}
	label {
		display:inline-block;
		padding:.3em;
		width: 10rem;
	}
	input[type="submit"] {
		margin:.5em;
		padding:.3em;
		width: 10rem;
	}

	table.eol1_md {
		th,
		td:not([class]) {
			border: 1px solid gray;
			padding:1em !important;
		}

		th {
			background-color: gray;
		}
	}

	blockquote.eol1_md {
		margin: auto;
		border-left: .2em solid gray;
		padding-left: .5em;
	}
</style>

<body>
	<table>
		<tr>
			<th>Input</th>
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
					<input type="submit" value="submit" />
				</form>
				minimal styling on output for comprehension only. most is default browser behaviour.<br>
				<a href="https://github.com/erroronline1/markdown">sourcecode</a>
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

</html>
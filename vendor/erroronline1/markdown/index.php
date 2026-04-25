<?php
/*
quick preview and benchmark for markdown libraries in two languages
*/
require_once('./src/Markdown.php');

$defaultSample = file_get_contents('./sample.md');

$methods = [
	"emphasis", // should come first to avoid to avoid modifying custom class insertions having unserscore in their name 
	"footnote", // should come second to avoid mishandling indentation and reutilizing list and superscript
	"blockquote", // should come thirs to enable nesting
	"reference", // before a and footnote to not mess up with similar patterns
	"headings", // before hr avoiding conversion of ----
	"horizontal_rule", // before emphasis avoiding matching *** as emphasis
	"definition",
	"task", // before list otherwise only the first occasionally nested item is converted
	"list",
	"code", // after list to avoid erroneous indentation matching
	"anchor", // safeMode can not render anchors to avoid malicious scripts
	"mailto", // safeMode can not render anchors to avoid malicious scripts
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
	//"inlineEvents", // safeMode can not render inline events and scripts to avoid malicious inserts
];

class md extends \erroronline1\Markdown\Markdown{
	public function __construct($TCPDF = null){
		parent::__construct($TCPDF);
		array_push($this->_methodsInProcessingOrder, "markdown");
	}
	public function markdown($content){
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
<html>
<style>
	body {
		background-color: #e5e9f0;
		color: #2e3440;
		font-family: Arial, Helvetica, sans-serif;
	}
	th:not([class]), td:not([class]) {
		width:33vw;
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
	input[type="submit"] {
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
	}

	pre, code {
		padding: .3em;
		border-radius: .2em;
		background: #434c5e;
		color: #a3be8c;
		line-height:1.8em;
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
					<label style="width:20rem"><input type="checkbox" name="customClass" <?= $customClass; ?> /> custom PHP class demo</label><br />
					<label style="width:20rem"><input type="checkbox" name="customStyling" <?= $customStyling; ?> /> add a bit of styling</label><br />
					<input type="submit" value="Submit" />
				</form>
				Minimal styling on output for comprehension and eye soothing only. Most is default browser behaviour though unless you tick the box above.
				<br>
				<a href="https://github.com/erroronline1/markdown">Sourcecode on GitHub</a>
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
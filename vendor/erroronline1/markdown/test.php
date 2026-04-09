<?php
$sample  = <<<'END'
# Plain text (h1 header)
(ATX)

This is a markdown flavour for basic text styling.  
Lines should end with two or more spaces  
to have an intentional linebreak
and not just continuing.

Text can be *italic*, **bold**, ***italic and bold***, ~~striked through~~, and `code style` with two ore more characters between the symbols.  
Some escaping of formatting characters is possible with a leading \ as in
**bold \* asterisk**, ~~striked \~~ through~~ and `code with a \`-character`.  
also ``code with ` escaped by double backticks`` and ==marked text==  
Subscript like H~2~O and superscript like X^2^  
Custom markdown for this engine for making ^^font bigger^^ 
[ ] task  
[x] accomplished

http://some.url, not particularly styled  
a phone number: tel:012345678  
[Styled link to markdown information](https://www.markdownguide.org)

Plain text (h1 header)
======================
(SETX)

--------

## Lists (h2 header) {#withcustomid}

1. Ordered list items start with a number and a period
	* Unordered list items start with asterisk or dash
    * Sublist nesting
    * is possible
    * by indentating with four spaces
        1. and list types
        2. are interchangeable
2. Ordered list item
with  
multiple lines
    1. the number
    1. of ordered lists
    2. actually doesn't
    3. matter at all

### Nested items in lists

1. List item with
    > Blockquote as item
2. Next list item with
    |Table|Column2|
    |---|---|
    |R1C1|R1C2|
4. List item with
    ~~~
     code with
	multiple line
    ~~~
8. List item with  
[x] accomplished task  
[ ] unaccomplished task

## Tables (h3 header)

| Table header 1 | Table header 2 | Table header 3 | and 4 |
| --- | --- | --- | --- |
| *emphasis* | **is** | ***possible*** | `too` |
| linebreaks | are | not | though<br />without HTML `<br />` |

- - -

#### Blockquotes and code (h4 header)

> Blockquote  
> with *multiple*  
> lines

    preformatted text/code must
    start with 4 spaces <code>

~~~
or being surrounded by
three \` or ~
~~~

#### Nested items in blockquotes

> * List within blockquote 1
> * List within blockquote 2
>     * Nested list
> 
> ~~~
> Code within blockquote
> ~~~
>> Blockquote within blockquote
> 
> | Tables nested | within | blockquotes |
> | :---------- | :-----: | ---: |
> | are | possible | as well |
> | like | aligning | colums |
> 
> definition list
> : first definition
> : second definition

## Definitions and footnotes
definition list
: first definition
: second definition

Here's a simple footnote[^1], and here's a longer one[^bignote]. Footnotes will appear at the bottom later.

[^1]: This is the first footnote.
[^bignote]: Here's one with multiple paragraphs and code.
    Indent paragraphs to include them in the footnote.
    `code`
    Add as many paragraphs as you like.

## Other features:  
<http://some.other.url> with brackets, [urlencoded link with title](http://some.url?test2=2&test3=a=(/bcdef "some title") and [javascript: protocol](javascript:alert('hello there'))  
some `code with <brackets>`  
mid*word*emphasis and __underscore emphasis__  
some@mail.address and escaped\@mail.address  
![an image](https://github.com/erroronline1/caro/raw/master/media/favicon/icon72.png) if loadable  
123\. escaped period avoiding a list

[top header](#plain-text)  
[second header](#plain-text-1)  
[third header](#withcustomid)  

### Safety related content that should pose lesser thread with safeMode

<script>alert('script injection')</script>  
<a href="javascript:void(0)" onclick="alert('click event')">a with click event</a>  
<a href="javascript:alert('click event')">href with click event</a>  
[mdscript js href](javascript:alert('js href'))  
<div onclick="alert('you clicked!')">clickable div</div>
END;

require_once('../vendor/erroronline1/markdown/src/Markdown.php');

		$start = microtime(true);
		$markdown = new \erroronline1\Markdown\Markdown();
		$PHPMarkdown = $markdown->md2html($sample);
		$end = microtime(true);
?>
<html>
	<style>
		td {
			vertical-align: top;
		}
	</style>
	<body>
		<table>
			<tr>
				<th>
					PHP (<?= ($end - $start)*1000; ?> ms)
				</th>
				<th id="scriptheader">
					ECMA-Script
				</th>
			</tr>
			<tr>
				<td>
				<?= $PHPMarkdown; ?>
				</td>
				<td id="scriptcolumn">
				</td>
			</tr>
		</table>
	</body>

<script type="module">
	import { Markdown } from "../vendor/erroronline1/markdown/src/Markdown.js";
	const MARKDOWN = new Markdown();
	const start = performance.now();
	document.getElementById("scriptcolumn").innerHTML = MARKDOWN.md2html(<?= json_encode($sample, JSON_UNESCAPED_UNICODE); ?>);
	document.getElementById("scriptheader").innerHTML += " (" + (performance.now()-start) + " ms)";
</script>
</html>
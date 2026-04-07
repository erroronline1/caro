# Markdown

My markdown parser from scratch  
supposed to match GitHub-flavour (https://github.github.com/gfm/) to a reasonable amount

## But why another?

This parser originates from [another of my projects](https://github.com/erroronline1/caro). This project has high concerns on privacy and data integrity, so I tried to create myself what I have been able to.

[Parsedown](https://parsedown.org) sure has impressive stats. But also not all features are available from them.

Also there is a PHP-library, as well as a ECMA-Script library that are supposed to create the same results. So you can decide if you want to render the content on the server, or let the users machine do the work, while the payload is a bit less bloated.

This may be of use to someone else, easy to tweak and understand at best, so I would not want this to be buried within another project folder.

## Use

Instatiate the Markdown class. In PHP you can choose to override semantic HTML with tags supported by [TCPDF v6.11](https://github.com/tecnickcom/tcpdf).  
```php
// in case you added it with "composer require erroronline1/markdown"
require(__DIR__ . '/../vendor/autoload.php'); // or whatever your directory structure is

// normal mode
$MARKDOWN = new erroronline1\Markdown\Markdown();
// or TCPDF-mode
$MARKDOWN = new erroronline1\Markdown\Markdown(true);
```

Convert you Markdown-content with
```php
// normal mode
$mycontent = $MARKDOWN->md2html($mycontent);
// or secureMode to avoid malicious script insertion
$mycontent = $MARKDOWN->md2html($mycontent, true);
```

The same goes for the ECMA-Script version. Instead of the TCPDF-flag, the md2html-method can be passed selected tags, while others will be ignored. This may improve contextual performance.
```js
import { Markdown } from "../vendor/erroronline1/markdown/src/Markdown.js";
const MARKDOWN = new Markdown();
mycontent = MARKDOWN->md2html(mycontent, true, ['emphasis']);
```
will only render bold and italic. The secureMode will still be applied.

## Composer?
This does not really have any dependencies. Still this is installable via [Composer](https://github.com/composer/composer), just to have a standardized autoloader behaviour and everyone but me is used to that.

## Features

* Link auto-detection, as well as tel-, ftp- and javascript-protocol
* Markdown link titles
* Auto-mailto
* More options for escaping
* Subscript, superscript and mark
* Custom header ids

### Table conversion
The PHP-library has two additional methods to parse a CSV-file to a Markdown-table and vice versa.
```php
$MARKDOWN->csv2md($path, $csv = ['separator' => ';', 'enclosure' => '"', 'escape' => '']);
$MARKDOWN->md2csv($content, $csv = ['separator' => ';', 'enclosure' => '"', 'escape' => '']);
```
handle this task and can take csv-formatting into account. 


## Output
```
# Plain text (h1 header)

This is a markdown flavour for basic text styling.  
Lines should end with two or more spaces  
to have an intentional linebreak
and not just continuing.

Text can be *italic*, **bold**, ***italic and bold***, ~~striked through~~, and `code style` with two ore more characters between the symbols.  
Some escaping of formatting characters is possible with a leading \ as in
**bold \* asterisk**, ~~striked \~~ through~~ and `code with a \`-character`.  
Also ``code with ` escaped by double backticks`` and ==marked text==  
Subscript like H~2~O and superscript like X^2^  
[ ] task  
[x] accomplished

http://some.url, not particularly styled  
a phone number: tel:012345678  
[Styled link to Markdown information](https://www.markdownguide.org)

--------

## Lists (h2 header) {#withcustomid}

1. Ordered list items start with a number and a period
    * Sublist nesting
    * is possible
    * by indentating with four spaces
        1. and list types
        2. are interchangeable
2. Ordered list item 2
3. Ordered list item 3

* Unordered list items start with asterisk or dash
    1. the number
    1. of ordered lists
    2. actually doesn't
    3. matter at all
* Unordered list item 2
    - [x] with task
* Unordered list item 3

### Tables (h3 header)

| Table header 1 | Table header 2 | Table header 3 | and 4 |
| --- | --- | --- | --- |
| *emphasis* | **is** | ***possible*** | `too` |
| linebreaks | are | not | though<br />without<br />html-tag `<br />` |

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

## Other features:  
<http://some.other.url> with brackets, [urlencoded link with title](http://some.url?test2=2&test3=a=(/bcdef "some title") and [javascript: protocol](javascript:alert('hello world'))  
some `code with <brackets>`  
mid*word*emphasis and __underscore emphasis__  
some@mail.address and escaped\@mail.address  
![an external image](https://github.com/erroronline1/caro/raw/master/media/favicon/icon72.png)  
123\. escaped period avoiding a list

### Nested items in lists

1. List item with
    > Blockquote as item
2. Next list item with
    |Table|Column2|
    |---|---|
    |R1C1|R1C2|
4. Last item

### Nested items in blockquotes

> * List within blockquote 1
> * List within blockquote 2
>     * Nested list
> ~~~
> Code within blockquote
> ~~~
>> Blockquote within blockquote
> 
> | Tables nested | within | blockquotes |
> | :---------- | :-----: | ---: |
> | are | possible | as well |
> | like | aligning | colums |

[top header](#plain-text)  
[second header](#withcustomid)
```

[renders to](https://raw.githubusercontent.com/erroronline1/markdown/refs/heads/main/readme.md) (look at the sourcecode...)

<h1 id="plain-text">Plain text (h1 header)</h1>
<p>This is a markdown flavour for basic text styling.<br>Lines should end with two or more spaces<br>to have an intentional linebreak
and not just continuing.</p>
<p>Text can be <em>italic</em>, <strong>bold</strong>, <em><strong>italic and bold</strong></em>, <s>striked through</s>, and <code>code style</code> with two ore more characters between the symbols.<br>Some escaping of formatting characters is possible with a leading \ as in
<strong>bold * asterisk</strong>, <s>striked ~~ through</s> and <code>code with a `-character</code>.<br>also <code>code with ` escaped by double backticks</code> and <mark>marked text</mark><br>Subscript like H<sub>2</sub>O and superscript like X<sup>2</sup><br><input type="checkbox" disabled="" class="markdown"> task<br><input type="checkbox" disabled="" checked="" class="markdown"> accomplished</p>

<a href="http://some.url" target="_blank" class="inline">http://some.url</a>, not particularly styled<br>a phone number: <a href="tel:012345678" target="_blank" class="inline">tel:012345678</a><br><a href="https://www.markdownguide.org" target="_blank" class="inline">Styled link to markdown information</a>

<h2 id="withcustomid">Lists (h2 header)</h2>

<ol><li>&nbsp;&nbsp;&nbsp;Ordered list items start with a number and a period<ul><li>Sublist nesting</li><li>is possible</li><li>by indentating with four spaces<ol><li>&nbsp;&nbsp;&nbsp;and list types</li><li>&nbsp;&nbsp;&nbsp;are interchangeable</li></ol></li></ul></li><li>&nbsp;&nbsp;&nbsp;Ordered list item 2</li><li>&nbsp;&nbsp;&nbsp;Ordered list item 3</li></ol>
<ul><li>Unordered list items start with asterisk or dash<ol><li>&nbsp;&nbsp;&nbsp;the number</li><li>&nbsp;&nbsp;&nbsp;of ordered lists</li><li>&nbsp;&nbsp;&nbsp;actually doesn't</li><li>&nbsp;&nbsp;&nbsp;matter at all</li></ol></li><li>Unordered list item 2<ul><li><input type="checkbox" disabled="" checked="" class="markdown"> with task</li></ul></li><li>Unordered list item 3</li></ul><h3 id="tables">Tables (h3 header)</h3>

<table><tbody><tr><th>Table header 1</th><th>Table header 2</th><th>Table header 3</th><th>and 4</th></tr><tr><td><em>emphasis</em></td><td><strong>is</strong></td><td><em><strong>possible</strong></em></td><td><code>too</code></td></tr><tr><td>linebreaks</td><td>are</td><td>not</td><td>though</td></tr></tbody></table>
<hr>
<h4 id="blockquotes-and-code">Blockquotes and code (h4 header)</h4>

<blockquote>
Blockquote<br>with <em>multiple</em><br>lines
</blockquote>
<pre>preformatted text/code must
start with 4 spaces &lt;code&gt;</pre>

<pre>or being surrounded by
three ` or ~
</pre>
<h2 id="other-features">Other features:</h2>
<a href="http://some.other.url" target="_blank" class="inline">http://some.other.url</a> with brackets, <a href="http://some.url?test2=2&amp;test3=a%3D%28%2Fbcdef" target="_blank" title="some title" class="inline">urlencoded link with title</a> and <a href="javascript:alert('hello there')" class="inline">javascript: protocol</a><br>some <code>code with &lt;brackets&gt;</code><br>mid<em>word</em>emphasis and <strong>underscore emphasis</strong><br><a href="mailto:some@mail.address">some@mail.address</a> and escaped@mail.address<br><img alt="an image" src="https://github.com/erroronline1/caro/raw/master/media/favicon/icon72.png" style="float:left; max-width:100%"><br>123. escaped period avoiding a list
<h2 id="nested-items-in-lists">Nested items in lists</h2>

<ol><li>&nbsp;&nbsp;&nbsp;List item with<blockquote>Blockquote as item</blockquote></li><li>&nbsp;&nbsp;&nbsp;Next list item with<table><tbody><tr><th>Table</th><th>Column2</th></tr><tr><td>R1C1</td><td>R1C2</td></tr></tbody></table></li><li>&nbsp;&nbsp;&nbsp;Last item</li></ol><h2 id="nested-items-in-blockquotes">Nested items in blockquotes</h2>

<blockquote>
<ul><li>List within blockquote 1</li><li>List within blockquote 2<ul><li>Nested list</li></ul></li></ul><pre>Code within blockquote
</pre>
<blockquote>
Blockquote within blockquote
</blockquote>
<table><tbody><tr><th align="left">Tables nested</th><th align="center">within</th><th align="right">blockquotes</th></tr><tr><td align="left">are</td><td align="center">possible</td><td align="right">as well</td></tr><tr><td align="left">like</td><td align="center">aligning</td><td align="right">colums</td></tr></tbody></table></blockquote>
<a href="#plain-text" class="inline">top header</a><br><a href="#withcustomid" class="inline">second header</a>  
  


in about 0.5 ms. Is the sourcecode tidy? Sure not, but does that matter? Also, no. Is it faster and more feature-rich than Parsedown. Looks like it. Currently.

## Current limitations and things feeling off

* Multiple lines for list items must end with one or more spaces on the previous line, linebreaks within lists behave a bit different than regular Markdown
* This flavour currently lacks support of
	* Definitions
	* Multiline code within lists
	* Syntax highlighting
	* Footnotes
	* Emojis

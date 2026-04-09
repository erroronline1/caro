# Markdown

My markdown parser from scratch  
supposed to match GitHub-flavour (https://github.github.com/gfm/) and [extended Markdown sytax](https://www.markdownguide.org/extended-syntax) to a reasonable amount

## But why another?
This parser originates from [another of my projects](https://github.com/erroronline1/caro). This project has high concerns on privacy and data integrity, so I tried to create myself what I have been able to.

This may be of use to someone else, easy to tweak and understand at best, so I would not want this to be buried within another project folder.

There are about 1000 PHP packages on [Packagist](https://packagist.org/search/?query=markdown) matching this topic, BUT
* many have a huge amount of other dependencies which I avoid in general if i can.
* this project not only has a PHP-library but also an ECMA-Script that is supposed to create the same results. So you can decide if you want to render the content on the server, or let the users machine do the work, while the payload is a bit less bloated.
* I can easily implement features I consider helpful for my projects.

## Composer
This does not really have any dependencies. Still this is installable via [Composer](https://github.com/composer/composer), just to have a standardized autoloader behaviour and everyone but me is used to that.

**You know what?** I hate unexpected dependencies and changes like the next guy. You are free to just grab the required file from the src-directory, import it on your own and handle changes and update as you feel comfortable! There is only one file per language. I'm not the boss of you. Just respect the AGPL license.

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

Convert your Markdown-content with
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

## Features
* Link auto-detection, as well as tel-, ftp- and javascript-protocol
* Markdown link titles
* Auto-mailto
* More options for escaping
* Subscript, superscript and mark
* Custom header ids, as well as auto assigning referable ids to headers
* A custom Markdown `^^for bigger text^^`

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
```

[renders to](https://raw.githubusercontent.com/erroronline1/markdown/refs/heads/main/readme.md) (look at the sourcecode...)

<h1 id="plain-text">Plain text (h1 header)</h1>
(ATX)
<p>This is a markdown flavour for basic text styling.<br />Lines should end with two or more spaces<br />to have an intentional linebreak
and not just continuing.</p>
<p>Text can be <em>italic</em>, <strong>bold</strong>, <em><strong>italic and bold</strong></em>, <s>striked through</s>, and <code>code style</code> with two ore more characters between the symbols.<br />Some escaping of formatting characters is possible with a leading \ as in
<strong>bold * asterisk</strong>, <s>striked ~~ through</s> and <code>code with a `-character</code>.<br />also <code>code with ` escaped by double backticks</code> and <mark>marked text</mark><br />Subscript like H<sub>2</sub>O and superscript like X<sup>2</sup><br />Custom markdown for this engine for making <span class="markdown" style="font-size:larger;">font bigger</span><br /><input type="checkbox" disabled class="markdown"> task<br /><input type="checkbox" disabled checked class="markdown"> accomplished</p>

<a href="http://some.url" class="inline">http://some.url</a>, not particularly styled<br />a phone number: <a href="tel:012345678" class="inline">tel:012345678</a><br /><a href="https://www.markdownguide.org" class="inline">Styled link to markdown information</a>

<h1 id="plain-text-1">Plain text (h1 header)</h1>
(SETX)

<hr>

<h2 id="withcustomid">Lists (h2 header)</h2>

<ol><li>Ordered list items start with a number and a period<ul><li>Unordered list items start with asterisk or dash
 </li><li>Sublist nesting
 </li><li>is possible
 </li><li>by indentating with four spaces<ol><li>and list types
  </li><li>are interchangeable
  </li></ol>
 </li></ul>
</li><li>Ordered list item
 with<br /> multiple lines<ol><li>the number
 </li><li>of ordered lists
 </li><li>actually doesn't
 </li><li>matter at all
 </li></ol>
</li></ol>
<h3 id="nested-items-in-lists">Nested items in lists</h3>

<ol><li>List item with<blockquote>Blockquote as item</blockquote>
</li><li>Next list item with<table><tr><th>Table</th><th>Column2</th></tr><tr><td>R1C1</td><td>R1C2</td></tr></table>
</li><li>List item with<pre> code with
 multiple line</pre>
</li><li>List item with<br /> <input type="checkbox" disabled checked class="markdown"> accomplished task<br /> <input type="checkbox" disabled class="markdown"> unaccomplished task
</li></ol>
<h2 id="tables">Tables (h3 header)</h2>

<table><tr><th>Table header 1</th><th>Table header 2</th><th>Table header 3</th><th>and 4</th></tr><tr><td><em>emphasis</em></td><td><strong>is</strong></td><td><em><strong>possible</strong></em></td><td><code>too</code></td></tr><tr><td>linebreaks</td><td>are</td><td>not</td><td>though<br />without HTML <code>&lt;br /&gt;</code></td></tr></table>
<hr>

<h4 id="blockquotes-and-code">Blockquotes and code (h4 header)</h4>

<blockquote>
Blockquote<br />with <em>multiple</em><br />lines
</blockquote>

<pre>preformatted text/code must
start with 4 spaces &lt;code&gt;</pre>

<pre>or being surrounded by
three ` or ~</pre>

<h4 id="nested-items-in-blockquotes">Nested items in blockquotes</h4>

<blockquote>
<ul><li>List within blockquote 1
</li><li>List within blockquote 2<ul><li>Nested list
 </li></ul>
</li></ul>
<pre>Code within blockquote</pre>
<blockquote>
Blockquote within blockquote
</blockquote>

<table><tr><th align="left">Tables nested</th><th align="center">within</th><th align="right">blockquotes</th></tr><tr><td align="left">are</td><td align="center">possible</td><td align="right">as well</td></tr><tr><td align="left">like</td><td align="center">aligning</td><td align="right">colums</td></tr></table>
<dl><dt>definition list
</dt><dd>first definition</dd><dd>second definition</dd></dl></blockquote>

<h2 id="definitions-and-footnotes">Definitions and footnotes</h2>
<dl><dt>definition list
</dt><dd>first definition</dd><dd>second definition</dd></dl>
Here's a simple footnote<sup><a id="fnref:1" href="#fn:1" class="inline">1</a></sup>, and here's a longer one<sup><a id="fnref:2" href="#fn:2" class="inline">2</a></sup>. Footnotes will appear at the bottom later.

<h2 id="other-features">Other features:</h2>
<a href="<http://some.other.url>" class="inline"><http://some.other.url></a> with brackets, <a href="http://some.url?test2=2&test3=a%3D%28%2Fbcdef" title="some title" class="inline">urlencoded link with title</a> and <a href="javascript:alert('hello there')" class="inline">javascript: protocol</a><br />some <code>code with &lt;brackets&gt;</code><br />mid<em>word</em>emphasis and <strong>underscore emphasis</strong><br /><a href="mailto:&#115;&#x6f;&#109;&#x65;&#x40;&#x6d;&#x61;&#x69;&#x6c;&#x2e;&#x61;&#x64;&#100;&#114;&#101;&#115;&#115;">&#115;&#x6f;&#109;&#x65;&#x40;&#x6d;&#x61;&#x69;&#x6c;&#x2e;&#x61;&#x64;&#100;&#114;&#101;&#115;&#115;</a> and escaped@mail.address<br /><img alt="an image" src="https://github.com/erroronline1/caro/raw/master/media/favicon/icon72.png" class="markdown" />if loadable<br />123. escaped period avoiding a list
<p><a href="#plain-text" class="inline">top header</a><br /><a href="#plain-text-1" class="inline">second header</a><br /><a href="#withcustomid" class="inline">third header</a>  </p>

<h3 id="safety-related-content-that-should-pose-lesser-thread-with-safemode">Safety related content that should pose lesser thread with safeMode</h3>

<script>alert('script injection')</script><br /><a href="javascript:void(0)" onclick="alert('click event')">a with click event</a><br /><a href="javascript:alert('click event')">href with click event</a><br /><a href="javascript:alert('js href')" class="inline">mdscript js href</a><br /><div onclick="alert('you clicked!')">clickable div</div>
<hr>
<ol><li><a id="fn:1" class="inline"></a>This is the first footnote.<br /> <a href="#fnref:1" class="inline">&crarr;</a><br /></li><li><a id="fn:2" class="inline"></a>Here's one with multiple paragraphs and code.<br />    Indent paragraphs to include them in the footnote.<br />    <code>code</code><br />    Add as many paragraphs as you like.<br /> <a href="#fnref:2" class="inline">&crarr;</a><br /></li></ol>

<br>

in about 2 ms in PHP and 5 ms in ECMA-Script. Is the sourcecode tidy? Sure not, but does that matter? Also, no. It's about optics anyway, isn't it?

## Current limitations and things feeling off

* This flavour currently lacks support of
	* Syntax highlighting
	* Emojis

# Markdown

My markdown parser from scratch  
supposed to match [GitHub-flavoured](https://github.github.com/gfm/), [basic](https://markdownguide.offshoot.io/basic-syntax/) and [extended](https://www.markdownguide.org/extended-syntax) Markdown sytax to a reasonable amount

## But why another?
This parser originates from [another of my projects](https://github.com/erroronline1/caro). This project has high concerns on privacy and data integrity, so I tried to create myself what I have been able to. Don't trust a framework/library

This may be of use to someone else, easy to tweak and understand at best, so I would not want this to be buried within another project folder.

There are about 1000 PHP packages on [Packagist](https://packagist.org/search/?query=markdown) matching this topic, BUT
* many have a significant amount of other dependencies which I avoid in general if I can.
* this project not only has a PHP-library but also an ECMAScript. You can decide if you want to render the content on the server, or let the users machine do the work, while the payload is a bit less bloated. While using both in your project in general you can expect the same result.
* I can easily implement features I consider helpful for my projects.

It matches common Markdown behaviour as far as I could tell after testing with several examples. See [issues](https://github.com/erroronline1/markdown/issues) for deviations.

## Features
* Link auto-detection, as well as tel- and ftp-protocol
* Markdown link titles
* Auto-mailto
* Escaping code by double-backticks too
* Subscript, superscript and mark
* Custom header ids, as well as auto assigning referable ids to headers
* Auto-cycling list types for ordered lists
* A custom Markdown `^^for larger text^^`

safeMode does not convert links and aims to convert relevant characters for script execution and insertions to HTML-escaped characters to avoid malicious code from untrusted user input. Internal links like `#heading` are not affected though.

The md2html-method of both libraries can be passed selected tags, while others will be ignored. This may improve contextual performance. Without a selection all formatting will be executed.

Most of the major created element tags have a `class="eol1_md"` attribute, so you can style these more easily. This is applicable for
* a
* blockquote
* code
* dl (style dt and dd as children)
* img
* input (type checkbox)
* mark
* ol and ul (style li as children)
* pre
* span (for larger text)
* table (style tr, th and td as children)

If this is not enough you would probably wrap the output into a container and address its content for your CSS and query selectors.  
In TCPDF-mode tables are prefixed with a linebreak to ensure correct nesting within lists. Also every odd row is assigned `class="eol1_odd"`, because pseudo-classes are not supported.

### Table conversion
The PHP-library has two additional methods to parse a CSV-file to a Markdown-table and vice versa.
```php
$MARKDOWN->csv2md($path, $csv = ['separator' => ';', 'enclosure' => '"', 'escape' => '']);
$MARKDOWN->md2csv($content, $csv = ['separator' => ';', 'enclosure' => '"', 'escape' => '']);
```
handle this task and can take CSV-formatting into account. 

### Sample test
See the result from both parsers by loading the provided index.php-file in your browser. Play with the available options on your machine or head over to the [live preview](http://markdown.erroronline.one).

## Installation
You know what? I hate unexpected dependencies and changes like the next guy. You are free to **just grab the required file** from the src-directory, import it on your own and handle changes and update as you feel comfortable! There is only one file per language. I'm not the boss of you. Just respect the AGPL license.

This does not really have any dependencies. Still this is installable via [Composer](https://github.com/composer/composer), just to have a standardized autoloader behaviour and everyone but me is used to that. Run
```bash
composer install erroronline1/markdown
```
in your project directory and import the module with
```php
require(__DIR__ . '/../vendor/autoload.php'); // or whatever your directory structure is
```
or
```js
import { Markdown } from "../vendor/erroronline1/markdown/src/Markdown.js";
```

## Use
Instatiate the Markdown class. In PHP you can choose to override some semantic HTML with tags supported by [TCPDF v6.11](https://github.com/tecnickcom/tcpdf).  
```php
// normal mode
$MARKDOWN = new erroronline1\Markdown\Markdown();
// or TCPDF-mode
$MARKDOWN = new erroronline1\Markdown\Markdown(true);

// normal mode
$mycontent = $MARKDOWN->md2html($mycontent);
// or safeMode to avoid malicious script insertion
$mycontent = $MARKDOWN->md2html($mycontent, true);
```

The same goes for the ECMAScript version. The TCPDF-flag is obviously not available.  
```js
const MARKDOWN = new Markdown();
mycontent = MARKDOWN->md2html(mycontent, true, ["emphasis", "larger", "br"]);
```
will only render bold and italic, my custom larger text and linebreaks. The safeMode will still be applied.

## Output
Use the following sample to check against other Markdown-parsers and decide for yourself which one is more suitable for your needs.

```
# Text formatting (ATX h1 header)

This is a markdown flavour for basic text styling.  
Lines should end with two or more spaces  
to have an intentional linebreak
and not just continuing.

Text can be *italic*, **bold**, ***italic and bold***, ~~striked through~~. 
mid*word*emphasis and __underscore emphasis__, you can *also __mix__ up* emphasis.
Some escaping of formatting characters is possible with a leading \ as in
**bold \* asterisk**, ~~striked \~~ through~~.  
Subscript like H~2~O and superscript like X^2^ and ==marked text==  
Custom markdown for this engine for making ^^font larger^^

Task lists and definitions (SETX h1 header)
======================

Task lists can be created a well  
[ ] task  
[x] accomplished

Definition list containing
: first definition
: second definition

## Links
http://some.url, not particularly styled  
a phone number: tel:012345678  
[Styled link to markdown information](https://www.markdownguide.org)  
<http://some.other.url> with brackets
[urlencoded link with title](http://some.url?test2=2&test3=a=(/bcdef "some title") and [javascript: protocol](javascript:alert('hello there'))  
some@mail.address and escaped\@mail.address  
![an image](https://github.com/erroronline1/caro/raw/master/media/favicon/icon72.png) if loadable  

[this is a reference link with match][referencelink]
[and one without match][noreferencelink]

Here's a simple footnote[^1], and here's a longer one[^bignote]. Footnotes will appear at the bottom later.

[^1]: This is the first footnote.
[^bignote]: Here's one with multiple paragraphs and code.
    Indent paragraphs to include them in the footnote.
    `code`
    Add as many paragraphs as you like.

[referencelink]: http://some.web.site

Internal navigation:  
[top header](#text-formatting)  
[second header](#task-lists-and-definitions)  
[third header](#withcustomid)  

--------

## Lists (h2 header) {#withcustomid}

1. Ordered list items start with a number and a period
    * Unordered list items start with asterisk or dash
    * Sublist nesting
    * is possible
    * by indentating with four spaces
        1. and list types
        2. are interchangeable
        1. and
            2. can
                3. be
                    4. nested
                        5. until
                            6. you're
                                8. tired
2. Ordered list item
with  
multiple lines
    1. the numbers
    1. of ordered lists
    2. actually don't
    3. matter at all
	 * but the indentation does

123\. escaped period avoiding a list

Nested ordered lists cycle through arabic numerals, roman numerals uppercase, roman numerals lowercase, latin alphabet uppercase and latin alphabet lowercase as numeration. 

## Tables (h3 header)

| Table header 1 | Table header 2 | Table header 3 | and 4 |
| --- | --- | --- | --- |
| *emphasis* | **is** | ***possible*** | `too` |
| linebreaks | are | not | though<br />without HTML `<br />` |

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

Inline `code with two ore more characters between the symbols`, also ``code with ` escaped by double backticks``  
and some `code with <brackets>` and `code with an escaped \`-character`.

# Nesting

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

- - -

> 1. List within blockquote 1
> 2. List within blockquote 2
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

## Safety related content that should pose lesser threat with safeMode

<script>alert('script injection')</script>  
<a href="javascript:void(0)" onclick="alert('click event')">a with click event</a>  
<a href="javascript:alert('click event')">href with click event</a>  
[mdscript js href](javascript:alert('js href'))  
<div onclick="alert('you clicked!')">clickable div</div>
```

[renders to](https://raw.githubusercontent.com/erroronline1/markdown/refs/heads/main/readme.md) (look at the sourcecode, since not all features may be available in this preview...)

<!-- Markdown parsing by error on line 1, https://github.com/erroronline1/markdown -->
 <h1 id="text-formatting">Text formatting (ATX h1 header)</h1> <p>This is a markdown flavour for basic text styling.<br />Lines should end with two or more spaces<br />to have an intentional linebreak
and not just continuing.</p> Text can be <em>italic</em>, <strong>bold</strong>, <em><strong>italic and bold</strong></em>, <s>striked through</s>.<br />mid<em>word</em>emphasis and <strong>underscore emphasis</strong>, you can <em>also <strong>mix</strong> up</em> emphasis.
Some escaping of formatting characters is possible with a leading \ as in <strong>bold * asterisk</strong>, <s>striked ~~ through</s>.<br />Subscript like H<sub>2</sub>O and superscript like X<sup>2</sup> and <mark class="eol1_md">marked text</mark><br />Custom markdown for this engine for making <span class="eol1_md" style="font-size:larger;">font larger</span> <h1 id="task-lists-and-definitions">Task lists and definitions (SETX h1 header)</h1> <p>Task lists can be created a well<br /><input type="checkbox" disabled class="eol1_md"> task<br /><input type="checkbox" disabled checked class="eol1_md"> accomplished</p> <dl class="eol1_md"><dt>Definition list containing </dt><dd>first definition</dd><dd>second definition</dd></dl> <h2 id="links">Links</h2> http://some.url, not particularly styled<br />a phone number: tel:012345678<br />[Styled link to markdown information](https://www.markdownguide.org)<br />&lt;http://some.other.url&gt; with brackets
[urlencoded link with title](http://some.url?test2=2&amp;test3=a=(/bcdef &quot;some title&quot;) and [javascript: protocol](javascript:alert(&#039;hello there&#039;))<br />some@mail.address and escaped@mail.address<br /><img alt="an image" src="https://github.com/erroronline1/caro/raw/master/media/favicon/icon72.png" class="eol1_md" />if loadable<br /> <a href="#ref:referencelink">this is a reference link with match</a> and one without match <p>Here's a simple footnote<sup><a id="fnref:1" href="#fn:1" class="eol1_md">1</a></sup>, and here's a longer one<sup><a id="fnref:2" href="#fn:2" class="eol1_md">2</a></sup>. Footnotes will appear at the bottom later.</p> <p> <a id="ref:referencelink" class="eol1_md"></a>( http://some.web.site)</p> <p>Internal navigation:<br /><a href="#text-formatting" class="eol1_md">top header</a><br /><a href="#task-lists-and-definitions" class="eol1_md">second header</a><br /><a href="#withcustomid" class="eol1_md">third header</a>  </p> <hr> <h2 id="withcustomid">Lists (h2 header)</h2> <ol type="1" class="eol1_md"><li>Ordered list items start with a number and a period <ul class="eol1_md"><li>Unordered list items start with asterisk or dash</li><li>Sublist nesting</li><li>is possible</li><li>by indentating with four spaces <ol type="1" class="eol1_md"><li>and list types</li><li>are interchangeable</li><li>and <ol type="I" class="eol1_md"><li>can <ol type="i" class="eol1_md"><li>be <ol type="A" class="eol1_md"><li>nested <ol type="a" class="eol1_md"><li>until <ol type="1" class="eol1_md"><li>you're <ol type="I" class="eol1_md"><li>tired</li></ol></li></ol></li></ol></li></ol></li></ol></li></ol></li></ol></li></ul></li><li>Ordered list item
with<br />multiple lines <ol type="I" class="eol1_md"><li>the numbers</li><li>of ordered lists</li><li>actually don't</li><li>matter at all
 * but the indentation does</li></ol></li></ol> 123. escaped period avoiding a list <p>Nested ordered lists cycle through arabic numerals, roman numerals uppercase, roman numerals lowercase, latin alphabet uppercase and latin alphabet lowercase as numeration. </p> <h2 id="tables">Tables (h3 header)</h2> <table class="eol1_md"><tr><th>Table header 1</th><th>Table header 2</th><th>Table header 3</th><th>and 4</th></tr><tr><td><em>emphasis</em></td><td><strong>is</strong></td><td><em><strong>possible</strong></em></td><td><code class="eol1_md">too</code></td></tr><tr><td>linebreaks</td><td>are</td><td>not</td><td>though<br />without HTML <code class="eol1_md">&lt;br /&gt;</code></td></tr></table> <h4 id="blockquotes-and-code">Blockquotes and code (h4 header)</h4> <blockquote class="eol1_md"><p> Blockquote<br />with <em>multiple</em><br />lines </p></blockquote> <pre class="eol1_md">preformatted text/code must
start with 4 spaces &lt;code&gt;</pre> <pre class="eol1_md"><code class="eol1_md">or being surrounded by
three ` or ~</code></pre> Inline <code class="eol1_md">code with two ore more characters between the symbols</code>, also <code class="eol1_md">code with ` escaped by double backticks</code><br />and some <code class="eol1_md">code with &lt;brackets&gt;</code> and <code class="eol1_md">code with an escaped `-character</code>. <h1 id="nesting">Nesting</h1> <ol type="1" class="eol1_md"><li>List item with <blockquote class="eol1_md"><p>Blockquote as item</p></blockquote></li><li>Next list item with <table class="eol1_md"><tr><th>Table</th><th>Column2</th></tr><tr><td>R1C1</td><td>R1C2</td></tr></table></li><li>List item with <pre class="eol1_md"><code class="eol1_md">code with
multiple line</code></pre></li><li>List item with<br /><input type="checkbox" disabled checked class="eol1_md"> accomplished task<br /><input type="checkbox" disabled class="eol1_md"> unaccomplished task</li></ol> <hr> <blockquote class="eol1_md"><p> <ol type="1" class="eol1_md"><li>List within blockquote 1</li><li>List within blockquote 2 <ul class="eol1_md"><li>Nested list</li></ul></li></ol> <pre class="eol1_md"><code class="eol1_md">Code within blockquote</code></pre><blockquote class="eol1_md"><p>Blockquote within blockquote</p></blockquote> <table class="eol1_md"><tr><th align="left">Tables nested</th><th align="center">within</th><th align="right">blockquotes</th></tr><tr><td align="left">are</td><td align="center">possible</td><td align="right">as well</td></tr><tr><td align="left">like</td><td align="center">aligning</td><td align="right">colums</td></tr></table> <dl class="eol1_md"><dt>definition list </dt><dd>first definition</dd><dd>second definition</dd></dl></p></blockquote> <h2 id="safety-related-content-that-should-pose-lesser-threat-with-safemode">Safety related content that should pose lesser threat with safeMode</h2> &lt;script&gt;alert(&#039;script injection&#039;)&lt;/script&gt;<br /><a href=&quot;javascript:void(0)&quot; onclick=&quot;alert(&#039;click event&#039;)&quot;>a with click event</a><br /><a href=&quot;javascript:alert(&#039;click event&#039;)&quot;>href with click event</a><br />[mdscript js href](javascript:alert(&#039;js href&#039;))<br /><div onclick=&quot;alert(&#039;you clicked!&#039;)&quot;>clickable div</div> <hr> <ol type="1" class="eol1_md"><li><a id="fn:1" class="eol1_md"></a>This is the first footnote.<br /> <a href="#fnref:1" class="eol1_md">&crarr;</a></li><li><a id="fn:2" class="eol1_md"></a>Here's one with multiple paragraphs and code.<br />    Indent paragraphs to include them in the footnote.<br />    <code class="eol1_md">code</code><br />    Add as many paragraphs as you like.<br /> <a href="#fnref:2" class="eol1_md">&crarr;</a></li></ol>

<br>

without safeMode in about 0.5-2 ms in PHP (depending on the server) and 2-15 ms in ECMAScript (depending on the clients calculation power). Is the sourcecode tidy? I tried, but does that matter? It's about visuals anyway, isn't it?  
[Check for yourself.](http://markdown.erroronline.one)

## Current limitations and things feeling off

* This flavour currently lacks support of
	* Syntax highlighting
	* Emojis
    * Proper paragraphs within lists, lists don't accept blank lines
    * Forgiving indentation handling within lists, here you should be accurate

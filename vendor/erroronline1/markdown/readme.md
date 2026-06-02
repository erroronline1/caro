<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
<!-- SPDX-FileCopyrightText: © 2026 error on line 1 <dev@erroronline.one> -->
<!-- SPDX-FileNotice: Part of erroronline1/markdown parser for PHP & ECMA-Script. -->

# Markdown

My markdown parser from scratch  
supposed to match [GitHub-flavoured](https://github.github.com/gfm/), [basic](https://markdownguide.offshoot.io/basic-syntax/) and [extended](https://www.markdownguide.org/extended-syntax) Markdown sytax to a reasonable amount

## But why another?
This parser originates from [another of my projects](https://github.com/erroronline1/caro). This project has high concerns on privacy and data integrity, so I tried to create myself what I have been able to. Don't trust a framework/library you didn't create yourself.

This may still be of use to someone else, easy to tweak and understand at best, so I would not want this to be buried within another project folder.

There are about 1000 PHP packages on [Packagist](https://packagist.org/search/?query=markdown) matching this topic, BUT
* many have a significant amount of other dependencies which I avoid in general if I can.
* this project not only has a PHP-library but also an ECMAScript. You can decide if you want to render the content on the server, or let the users machine do the work, while the payload is a bit less bloated (the provided Markdown sample has about 60% of the bytes compared to the output). While using both in your project in general you can expect the same result.
* I can easily implement features I consider helpful for my projects as inspired by https://parsedown.org/demo, https://markdown-it.github.io/

It matches common Markdown behaviour as far as I could tell after testing with several examples, also a [rather big one](https://github.com/erroronline1/caro/blob/master/readme.md). See [paragraph below](#current-limitations-and-things-feeling-off) or [issues](https://github.com/erroronline1/markdown/issues) for deviations.  

Releases follow [Semantic Versioning](https://semver.org):
* MAJOR - breaking changes
* MINOR - backwards-compatible new features
* PATCH - backwards-compatible bug fixes

## Extended Features
* Link auto-detection, as well as tel- and ftp-protocol
* Markdown link titles
* Auto-mailto
* Escaping code by double-backticks too
* Subscript, superscript and mark
* Custom header ids, as well as auto assigning referable ids to headers
* Auto-cycling list types for ordered lists
* Typographic replacements
* A custom Markdown for `++larger++` and `--smaller--` text

### safeMode
safeMode does not convert external links and aims to convert relevant characters for script execution and insertions to HTML-escaped characters to avoid malicious code from untrusted user input. Internal links like `#heading` are not affected though. By escaping relevant characters the output becomes safe to render, but may result in invalid HTML the dev-tools keep nagging about. Still better than malicious code, imho.  
The brackets of the following tags are escaped: (a|applet|audio|body|dialog|form|html|iframe|input|keygen|main|noscript|object|param|script|style|title|textarea|video) to not trick users into unintended elements, HTML is otherwise considered uncritical and is therefore unaltered.

> References are converted to internal links pointing to the place of the stated address that will be written in plain text

### Formatting selection 
The md2html-method of both libraries can be passed selected formatting methods, while others will be ignored. This may improve contextual performance. Without a selection all formatting will be executed.

### Styling helpers
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
* span (for font size)
* table (style tr, th and td as children)

If this is not enough you would probably wrap the output into a container and address its content for your CSS and query selectors.  
In [TCPDF-mode](#use) tables are prefixed with a linebreak to ensure correct nesting within lists. Also every odd row is assigned `class="eol1_odd"`, because pseudo-classes are not supported.

### Table conversion
The PHP-library has two additional methods to parse a CSV-file to a Markdown-table and vice versa.
```php
$MARKDOWN->csv2md($path, $csv = ['separator' => ';', 'enclosure' => '"', 'escape' => '']);
$MARKDOWN->md2csv($content, $csv = ['separator' => ';', 'enclosure' => '"', 'escape' => '']);
```
handle this task and can take CSV-formatting into account. 

### Sample test
See the result from both parsers by loading the provided index.php-file in your browser. Play with the available options on your machine or head over to the [live preview](http://markdown.erroronline.one).  
Other available tests for your convenience:
* https://github.com/mxstbr/markdown-test-file/blob/master/TEST.md
* https://syki.dev/blog/test-your-markdown-renderer

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
Instatiate the Markdown class.  
```php
$MARKDOWN = new \erroronline1\Markdown\Markdown();

// normal mode
$mycontent = $MARKDOWN->md2html($mycontent);
// or safeMode to avoid malicious script insertion
$mycontent = $MARKDOWN->md2html($mycontent, true);
```

```js
const MARKDOWN = new Markdown();
mycontent = MARKDOWN->md2html(mycontent, true, ["emphasis", "fontsize", "linebreak"]);
```
will only render bold and italic, this custom fontsize handler and linebreaks. The safeMode will still be applied.

### TCPDF and tc-lib-pdf
In PHP you can choose to override some semantic HTML with tags supported by [TCPDF v6.11](https://github.com/tecnickcom/tcpdf) and [tc-lib-pdf](https://github.com/tecnickcom/tc-lib-pdf) as far as i can catch up.  
You can enable the respective mode by passing the required version number on instatiation of the class  
```php
$MARKDOWN = new \erroronline1\Markdown\Markdown(6); // for TCPDF
$MARKDOWN = new \erroronline1\Markdown\Markdown(8); // for tc-lib-pdf
```
To be honest currently the switch is version 8 so any number below or above will process the respective latest versions abilities.  

### Customization
It's easy to implement your own features by just extending the class and add/or override your own methods. Add your method to the `_methodsInProcessingOrder`- and, if applicable, the `_nested-blocks`-property (push, slice, whatever). Of course you can override methods as well. See a working implementation in index.php:

```php
class md extends \erroronline1\Markdown\Markdown{
    public function __construct($TCPDF = 0){
        parent::__construct($TCPDF);
        array_push($this->_methodsInProcessingOrder, "markdown");
    }
    public function markdown($content = ''){
        return preg_replace('/markdown/i', "nwobʞɿɒM", $content);
    }
}
```
## Current limitations and things feeling off

* This flavour currently lacks support of
    * Syntax highlighting
    * Emojis
    * Forgiving indentation handling within lists, here you should be accurate; also lists don't accept blank lines without indentation regarding paragraphs
    * Block sizes (lists, code, blockquote, etc.) longer than 8k characters may fail and impact further processing on php. You can split them though if applicable. This may be considered visually uncritical.

Due to the **intended** capability of turning scripts into links like `[test](javascript:fn())` there may be the need to escape a bit more as in `(this is an example: [test](javascript:fn())\)` for the pattern of parentheses is hard to determine.
# Markdown
* [Text formatting](#text-formatting-atx-h1-header)
* [Tasks and definitions](#tasks-and-definitions-setx-h1-header)
* [Links](#links)
* [Lists](#withcustomid)
* [Tables](#tables-h3-header)
* [Blockquotes and code](#blockquotes-and-code-h4-header)
* [Nesting](#nesting)

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

Tasks and definitions (SETX h1 header)
======================

Task lists can be created a well  
[ ] task  
[x] accomplished

Definition list containing
: first definition line
: second definition line

### Other substitutions

(c) (C) (r) (R) (tm) (TM) (p) (P) +- -> will be replaced by their symbol  
unless escaped: \(c) (C\) \(r\) \(R) (tm\) \(TM\) \(p) (P\) +\- \->

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
[^bignote]: Here's one with multiple lines and other elements.
    Indent the content to include it in the current footnote.  
    Add as many lines as you like.
    ~~~
    code blocks
    are supported
    ~~~
    
    > as well as
    >> blockquotes
    >
    
    | and | of |
    | --- | ----- |
    | course | tables|
    
        1. and lists
        2. if additionally indented

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
        12. unless the start number is other than 1
        25. then you'll have an offset

123\. escaped period avoiding a list

Nested ordered lists cycle through arabic numerals, roman numerals uppercase, roman numerals lowercase, latin alphabet uppercase and latin alphabet lowercase as numeration. 

## Tables (h3 header)

| Table header 1 | Table header 2 | Table header 3 | and 4 |
| --- | ---: | :---: | :--- |
| *emphasis* | **is** | ***possible*** | `too` |
| linebreaks | are | not | though without<br /> HTML `<br />` |
| and | aligning | text | columnwise |

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
> > Blockquote within blockquote
> 
> | Tables nested | within | blockquotes |
> | :---------- | :-----: | ---: |
> | are | possible | as well |
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
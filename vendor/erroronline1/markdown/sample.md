# Markdown
* [Text formatting](#text-formatting-atx-h1-header)
* [Substitutions](#substitutions-setx-h1-header)
* [Links](#links-atx-h2-header)
* [Lists](#lists-setx-h2-header)
* [Tables](#withcustomid)
* [Blockquotes, code and definition lists](#blockquotes-code-and-definition-lists)
* [Nesting](#nesting)

# Text formatting (ATX h1 header)

This is a flavored markdown processor for basic text styling.  
Lines should end with two or more spaces  
to have an intentional linebreak
and not just continuing.

Text can be *italic*, **bold with asterisks** and __underscores__, ***italic and bold***, ~~striked through~~.  
By adding decorators in the right place you achieve mid*word*emphasis and *also __mixed__ up* emphasis.  
Some escaping of formatting characters is possible with a leading \ as in
**bold \* asterisk** or ~~striked \~~ through~~.  
Subscript like H~2~O, superscript like X^2^ and ==marked text== are available.  
A custom markdown for this processor can make ^^font larger^^

Substitutions (SETX h1 header)
======================

Task lists can be created a well  
[ ] where \[ ] and \[x]  
[x] are converted to html checkboxes

(c) (C) (r) (R) (tm) (TM) (p) (P) +- -> will be replaced by their symbol  
unless escaped: \(c) (C\) \(r\) \(R) (tm\) \(TM\) \(p) (P\) +\- \->

## Links (ATX h2 header)
Links will be replaced if not in safeMode, unless they are internal references

http://some.url, not particularly styled, just a detected protocol  
a phone number: tel:012345678  
[Styled link to markdown information](https://www.markdownguide.org)  
<http://some.other.url> with brackets  
[urlencoded link with title](http://some.url?test2=2&test3=a=(/bcdef "some title") and [javascript: protocol](javascript:alert('hello there'))  
some@mail.address converted to mailto: and an escaped\@mail.address  
![an image](https://github.com/erroronline1/caro/raw/master/media/favicon/icon72.png) if loadable  

### Internal references (h3 header)

[this is a reference link with a match somwhere][referencelink]
[and an attempt where the actual reference has been forgotten][noreferencelink]

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

[referencelink]: http://valid.reference.match

#### Other internal navigation (h4 header)
[top header](#text-formatting-atx-h1-header)  
[second header](#substitutions-setx-h1-header)  
[fifth header](#withcustomid)  

--------

Lists (SETX h2 header)
----

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

123\. with an escaped period avoids a list

Nested ordered lists cycle through arabic numerals, roman numerals uppercase, roman numerals lowercase, latin alphabet uppercase and latin alphabet lowercase as numeration. 

# Tables {#withcustomid}

| Table header 1 | Table header 2 | Table header 3 | and 4 |
| --- | ---: | :---: | :--- |
| *emphasis* | **is** | ***possible*** | `too` |
| linebreaks | are | **^^not^^** | though without<br /> HTML `<br />` |
| and | aligning | text | columnwise |

# Blockquotes, code and definition lists

> Blockquote  
> with *multiple*
> lines  
> as seen in many email programs  
> start with a >

    preformatted text/code can
    start with 4 spaces <code>

~~~
or being surrounded by
three \` or ~
~~~

Inline `code with two ore more characters between the symbols`, also ``code with ` escaped by double backticks``  
and some `code with <brackets>` and `code with an escaped \`-character` render inline.

Definition list containing
: definition lines that
: start with a :

# Nesting

1. List items can contain
    > Blockquotes
2. or 
    |Tables|Column2|
    |---|---|
    |R1C1|R1C2|
4. also
    ~~~
    code with
    multiple lines
    ~~~
8. and  
[x] accomplished and  
[ ] unaccomplished tasks

- - -

> ~~~
> Same goes for
> ~~~
> > blockquotes
>
> 1. with
> 2. nested
>     * lists
> 
> definition lists
> : with multiple
> : lines
>
> | Tables nested | within | blockquotes |
> | :---------- | :-----: | ---: |
> | are | possible | as well |

## Safety related content that should pose lesser threat with safeMode

<script>alert('this script injection had been presented by disabled safeMode')</script>  
<a href="javascript:void(0)" onclick="alert('click event')">a with click event</a>  
<a href="javascript:alert('click event')">a href with click event</a>  
[markdown link with js protocol href](javascript:alert('js href'))  
<div onclick="alert('you clicked!')">clickable div</div>
// SPDX-License-Identifier: AGPL-3.0-or-later
// SPDX-FileNotice: Part of erroronline1/markdown parser for PHP & ECMA-Script.

class ListTypeGenerator {
	/**
	 * being able to assign/instatiate a new generator function
	 */
	*generator() {
		while (true) {
			yield "1";
			yield "I";
			yield "i";
			yield "A";
			yield "a";
		}
	}
}

export class Markdown {
	// likely imperfect hack to include literal unicode characters within headers as these are currently not matched by \w
	_unicode_regex = "ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿŒœŠšŸƒ";

	_anchor = /(?<!\]\()(?:\<{0,1})(?<!'|"|`)((?:https*|ftps*|tel|javascript):(?:\/\/)*[^\n\s,"'`<>]+)(?:\>{0,1})|(?:(?<!!|\\)\[)(.+?)(?:(?<!\\)\])(?:\()(.+?)((?: \").+(?:\"))*(?:(?<!\\)\)(?!\)))/gi; // auto url linking, including some schemes and md linking
	_blockquote = /(^>{1,}.*?\n)+/gms;
	_code = /^ {0,3}([`~]{3})(.*?)\n((?:.|\n)+?)\n^ {0,3}\1\n|^\n^ {4}([^\*\-\d].+)+|(?<!\\)(`{1,2})([^\n]+?)(?<!\\| |\n)\5/gm;
	_comment = /<!--.*?-->/gs;
	_compress = />\n+|\n *<|[^>]\n+<[^\/]/gm;
	_definition = /(^.+?\n)((?:^: .+?\n)+)/gm;
	_emphasis = /(?<!\\)(\*{1,3}(?! ))([^\n]+?)(?<!\\| )\1|(?<!\\|\S)(_{1,3}(?! ))([^\n]+?)(?<!\\| )\3(\W)/gm;
	_footnote = /\[\^(.+?)\](:.+?\n(?: {4}.*?\n)*)*/g;
	_headings = /(?:^)(#+ )(.+?)(?: {#(.+?)}){0,1}(?:#*)$|(?:^)(.+?)\n(={3,}|-{3,})$/gm; // must be first line or have a linebreak before
	_horizontal_rule = /^ {0,3}(?:\-|\- |\*|\* ){3,}$/gm;
	_image = /(?:!\[)(.*?)(?:\])(?:\()(.+?)(?:\))/g;
	_fontsize = /(?<!\\)((?:\+|-){2,})([^\n]+?)(?<!\\| |\n)\1(?!((?:\+|-)))/g;
	_linebreak = / +\n/g;
	_list = /((?:^)(\*|\-|\+|\d+\.) {1,3}(?:.|\n)+?)(?:\n$)/gm;
	_mailto = /([^\s<]+(?<!\\)@[^\s<]+\.[^\s<]+)/g;
	_mark = /==(.+?)==/g;
	_paragraph = /(?:^$\n)(.+?)(?:\n^$)/gms;
	_reference = /(?:(?<!!|\\)\[)(.+?)(?:(?<!\\)\])(?:\[)(.+?)(?:\])|(?:^\[)([^^]+?)(?:\]:)(.+)$/gm;
	_safeMode = /<\/{0,1} {0,}(a|applet|audio|body|dialog|form|html|iframe|input|keygen|main|noscript|object|param|script|style|title|textarea|video|xmp)|on\w+?=('|").+?(?<!\\)\2/gi;
	_strikethrough = /(?<!\\)~{2}([^\n]+?)(?<!\\| |\n)~{2}/g;
	_subscript = /(?<!\\)~{1}([^\n]+?)(?<!\\| |\n)~{1}/g;
	_superscript = /(?<!\\)\^{1}([^\n]+?)(?<!\\| |\n)\^{1}/g;
	_table = /^((?:\|.+?){1,}\|)\n((?:\| *:{0,1}-+:{0,1} *?){1,}\|)\n((?:(?:\|.+?){1,}\|(?:\s*\n|\s*$))+)/gm;
	_task = /\[(\s*x{0,1}\s*)\] (.+?(?:\n))/gim;
	_typographer = /(?<!\\)\((?:c|r|tm|p)(?<!\\)\)|(?<!\\)\+-|(?<!\\)->/gi;

	// class properties to use over multiple methods
	_headers = [];
	_references = {};
	_limitTo = [];

	// predefined character-sets to escape or replace if required
	_codeescape = "([*\\-\\+~`.@$>^[\\]()=_#:|\\d])";
	_htmlescape = {
		"&": "&amp;",
		$: "&#36;", // able to break code handling if not escaped, at least in ecmas
		"<": "&lt;",
		">": "&gt;",
		'"': "&quot;",
		"'": "&#039;",
	};

	_typographs = {
		"(c)": "&copy;",
		"(r)": "&reg;",
		"(tm)": "&trade;",
		"(p)": "&#9413;",
		"+-": "&#177;",
		"->": "&rarr;",
	};

	// modifiable lists for using as extended class
	_methodsInProcessingOrder = [
		"code", // must come first to enable escaping to avoid unintended conversion
		"safeMode", // prior to tasks, definition and reference avoiding invalidation of allowed input and anchors
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

	_nested_blocks = ["code", "blockquote", "definition", "table"];

	/**
	 * entry method to convert a text to markdown
	 *
	 * @param {string} text to convert from markdown to html
	 * @param {boolean} safeMode returns anchors as specialchars and some
	 * @param (array) limitTo process only given methods, empty for all
	 * @returns {string}
	 */
	md2html(text = "", safeMode = false, limitTo = []) {
		this._limitTo = limitTo;

		text = text.replaceAll(/\r/g, "").replaceAll(/\t/g, "    ") + "\n"; // add a new line for improved pattern matching by default

		// detect all comments and replace them with a numbered placeholder to not mess up formatting
		const comment_placeholder = `eol1_md_comment_placeholder_${Math.floor(Math.random() * Date.now()).toString(36)}_`,
			comments = [...text.matchAll(this._comment)];
		if (comments.length) {
			for (let i = 0; i < comments.length; i++) {
				text = text.replace(comments[i], comment_placeholder + i);
			}
		}
		// apply methods
		this._methodsInProcessingOrder.forEach((method) => {
			if (!this._limitTo.length || this._limitTo.includes(method) || (safeMode && ["anchor", "safeMode", "reference", "mailto"].includes(method))) {
				if (["anchor", "safeMode", "reference", "mailto"].includes(method)) text = this[method](text, safeMode);
				else text = this[method](text);
			}
		});
		text = this.deescape(text); // should come after other stylings have been applied
		text = this.compress(text);

		// revert comments
		if (comments.length) {
			text = text.replaceAll(new RegExp(comment_placeholder + "(\\d+)", "g"), (...match) => {
				return comments[match[1]] || match[0];
			});
			// escape remaining characters within code if applicable
			if (!this._limitTo.length || this._limitTo.includes("code")) {
				text = text.replaceAll(/<code.*?>(?:<pre.*?>)*(.+?)(?:<\/pre>)*<\/code>/gs, (...match) => {
					return match[0].replace(match[1], this.escapeHtml(match[1], false));
				});
			}
		}

		text = text.replaceAll(/\t/g, "    "); // revert code indentation

		return "<!-- Markdown parsing by error on line 1, https://github.com/erroronline1/markdown -->\n" + text;
	}

	/**
	 * strip new lines near tags to compress result
	 *
	 * @param {string} content
	 * @returns string
	 */
	compress(content) {
		return content.replaceAll(this._compress, (...match) => {
			return match[0].replaceAll(/[\n\s]+/g, " ");
		});
	}

	/**
	 * development helper...
	 */
	debug(...content) {
		console.log(...content);
	}

	/**
	 * replace escaped characters
	 *
	 * @param {string} content
	 * @returns string
	 */
	deescape(content) {
		return content.replaceAll(new RegExp("\\\\" + this._codeescape, "g"), (...match) => {
			return match[1];
		});
	}

	/**
	 * escape special chars for code, pre and in case of safeMode
	 *
	 * @param {string} content
	 * @param {bool} ampersand can be excluded to avoid duplicate encoding
	 * @returns string
	 */
	escapeHtml(content, ampersand = true) {
		let escape = this._htmlescape;
		if (!ampersand) delete escape["&"];
		return content.replaceAll(new RegExp("[" + Object.keys(escape).join("") + "]", "g"), (m) => {
			return this._htmlescape[m];
		});
	}

	/**
	 * methods to run within nested elements like lists and footnotes
	 *
	 * @param {string} content
	 * @returns string
	 */
	nested_blocks(content) {
		this._nested_blocks.forEach((method) => {
			if (!this._limitTo.length || this._limitTo.includes(method)) content = this[method](content);
		});
		return content;
	}

	/**
	 * split the content by found blocks to later zip converted content blocks with separator blocks
	 *
	 * @param {string} content
	 * @param {array} by
	 * @return array
	 */
	separate(content, by = []) {
		// due to the nature of preg-match results, patterns did not proof suitable for splitting. must be literals
		return content.split(new RegExp(by.map((v) => RegExp.escape(v)).join("|"), "g"));
	}

	/**
	 * replaces links unless already converted by the reference-method or external links in safeMode
	 * internal links are always rendered since considerable safe
	 *
	 * @param {string} content
	 * @param {boolean} safeMode
	 * @returns string
	 */
	anchor(content, safeMode = false) {
		// replace links in this order
		const _references = this._references,
			unescapedCode = (uccontent) => {
				let code = uccontent.match(/<code.*?code>|<img.+\/>/g);
				if (!code) return this.escapeHtml(uccontent);
				// split the content by found code blocks to later zip code blocks with converted content
				let nocode = this.separate(uccontent, code);
				for (let i = 0; i < nocode.length; i++) {
					nocode[i] = this.escapeHtml(nocode[i]).replace(/[\-\+]/g, (match) => {
						return `\\${match}`;
					});
				}
				uccontent = [];
				for (let i = 0; i < nocode.length; i++) {
					uccontent.push(nocode[i], code[i] || "");
				}
				return uccontent.join("");
			};
		return content.replaceAll(this._anchor, (...match) => {
			if (match[1] && Object.values(_references).indexOf(match[1]) > -1) return match[1]; // avoid duplication of link creation from references

			// markdown linking, allow internal links
			if (match[3] && match[3].startsWith("#"))
				return `<a href="${match[3].replace(/[\-\+]/g, (match) => {
					return `\\${match}`;
				})}" class="eol1_md">${unescapedCode(match[2])}</a>`;

			if (safeMode) return unescapedCode(match[0]);
			// auto linking with protocols
			if (match[1])
				return `<a href="${match[1].replace(/[\-\+]/g, (match) => {
					return `\\${match}`;
				})}" class="eol1_md">${unescapedCode(match[1])}</a>`;
			//markdown linking
			let url = "";
			if (match[3].startsWith("javascript:")) url = match[3];
			else {
				let component = new URLSearchParams(match[3]);
				if (component.keys().length) {
					url = match[3].substring(0, match[3].indexOf("?")) + "?" + component.toString();
				} else url = match[3];
				//url += '" target="_blank';
			}
			if (match[4]) url += '" title="' + match[4].substring(2, match[4].length - 1);
			return `<a href="${url.replace(/[\-\+]/g, (match) => {
				return `\\${match}`;
			})}" class="eol1_md">${unescapedCode(match[2])}</a>`;
		});
	}

	/**
	 * replace blockquotes recursively
	 *
	 * @param {string} content
	 * @returns string
	 */
	blockquote(content) {
		return content.replaceAll(this._blockquote, (...match) => {
			match[0] = this.nested_blocks(match[0].replaceAll(/^> {0,1}/gm, "")); // remove blockquote character and possible whitespace and check recursively for nested blocks
			return `<p><blockquote class="eol1_md">${match[0]}</blockquote></p>`;
		});
	}

	/**
	 * replace indentated, fenced or quoted code
	 *
	 * @param {string} content
	 * @returns string
	 */
	code(content) {
		let code = content.match(this._code);
		if (!code) return content;
		// split the content by found code blocks to later zip converted code blocks with content
		let nocode = this.separate(content, code),
			escape = new RegExp(this._codeescape, "g");
		for (let i = 0; i < code.length; i++) {
			code[i] = code[i].replaceAll(this._code, (...match) => {
				if (match[6]) {
					// inline code
					return `<code class="eol1_md">${this.escapeHtml(match[6]).replaceAll(escape, (...e_match) => {
						return `\\${e_match[1]}`;
					})}</code>`;
				} else {
					// match[2] for fenced code would be a specified language, not sure what to do with that yet
					// if match[4] code blocks are written with pure indentation
					let codeblock = match[4] ? match[0].replaceAll(/^ {4}/gm, "") : match[3];
					return (
						"\n" +
						(match[4] ? "    " : "") + // to not mess up indentation within lists
						`<code class="eol1_md"><pre class="eol1_md">${this.escapeHtml(codeblock)
							.replaceAll(escape, (...e_match) => {
								// escape above special chars
								return `\\${e_match[1]}`;
							})
							.replaceAll(/ {1,}$/gm, "") // delete end spaces that otherwise may be replaced with linebreak
							.replaceAll("    ", "\t")}</pre></code>\n` // replace 4 spaces within code with tabs to avoid possible collisions
					);
				}
			});
		}
		content = [];
		for (let i = 0; i < nocode.length; i++) {
			content.push(nocode[i], code[i] || "");
		}
		return content.join("");
	}

	/**
	 * replace definition lists
	 *
	 * @param {string} content
	 * @returns string
	 */
	definition(content) {
		return content.replaceAll(this._definition, (...match) => {
			let definitions = [];
			match[2].split("\n").forEach((d) => {
				if (d.length) definitions.push(d.substring(2));
			});
			return `<dl class="eol1_md"><dt>${match[1]}</dt><dd>${definitions.join("</dd><dd>")}</dd></dl>`;
		});
	}

	/**
	 * replace all em and strong formatting
	 *
	 * @param {string} content
	 * @returns string
	 */
	emphasis(content) {
		return content.replaceAll(this._emphasis, (...match) => {
			let wrapper = (match[1] || match[3]).length,
				tags = [
					[], // wrapper offset, easier than reducing index
					["<em>", "</em>"],
					["<strong>", "</strong>"],
					["<em><strong>", "</strong></em>"],
				];
			return tags[wrapper][0] + this.emphasis(match[2] || match[4]) + tags[wrapper][1] + (match[5] ? " " : ""); // append consumed nonword-character on underscore pattern
		});
	}

	/**
	 * replace fontsize decorator
	 * THIS IS A CUSTOM MARKDOWN PROPERTY TO THIS FLAVOUR
	 *
	 * @param {string} content
	 * @returns string
	 */
	fontsize(content) {
		return content.replaceAll(this._fontsize, (...match) => {
			return '<span class="eol1_md" style="font-size:' + (match[1].substring(0, 2) === "++" ? "larger" : "smaller") + ';">' + this.fontsize(match[0].substring(2, match[0].length - 2)) + "</span>";
		});
	}

	/**
	 * replace footnote references with links an append an actual footnote list at the end of the content
	 * no need for safeMode, since these are internal links only
	 *
	 * @param {string} content
	 * @returns string
	 */
	footnote(content) {
		// find all footnotes
		const footnotes = [...content.matchAll(this._footnote)];
		let _footnotes = {},
			key,
			footnote_block;
		for (const value of footnotes) {
			footnote_block = (value[2] || "").trim().replaceAll(/^: |^ {4}/gm, "");
			// replace possible nested blocks in footnote item
			footnote_block = this.nested_blocks(footnote_block);

			_footnotes[value[1]] = footnote_block;
		}
		content = content.replaceAll(this._footnote, (...match) => {
			// inline links if available as md superscript
			if (!match[2]) {
				if (!_footnotes[match[1]]) return "^" + match[1] + "^";
				key = Object.keys(_footnotes).indexOf(match[1]) + 1;
				return `^<a id="fnref:${key}" href="#fn:${key}" class="eol1_md">[${key}]</a>^`;
			}
			// delete actual footnote
			return "";
		});
		// create actual footnotes as ordered md list and re-append to content
		let footnote_appendix = "";
		for (const [link, footnote] of Object.entries(_footnotes)) {
			key = Object.keys(_footnotes).indexOf(link) + 1;
			footnote_appendix += `1. <a id="fn:${key}" class="eol1_md"></a>${footnote.trim().replaceAll(/^/gm, "    ")} <a href="#fnref:${key}" class="eol1_md">&crarr;</a>\n`;
		}
		return content + (footnote_appendix ? `\n<hr>\n${footnote_appendix}\n` : "");
	}

	/**
	 * replace headers and assign auto or custom ids
	 *
	 * @param {string} content
	 * @returns string
	 */
	headings(content) {
		return content.replaceAll(this._headings, (...match) => {
			let size, heading, id;
			if (!match[4]) {
				// atx heading starting with #
				size = Math.min(match[1].length - 1, 6);
				heading = match[2].trim();
			} else {
				// setext heading underlined with === or ---
				size = match[5].startsWith("=") ? 1 : 2;
				heading = match[4].trim();
			}
			id = heading.replaceAll(new RegExp("[^\\w\\d\\s" + this._unicode_regex + "]", "giu"), "");
			if (match[3] || id) {
				id = (match[3] || id).trim().replaceAll(/\s/g, "-").toLowerCase();
				// enumerate
				let existing = this._headers.filter((e) => e.startsWith(id));
				if (existing.length) {
					existing.sort();
					let last = existing.pop();
					let numerate = last.match(/.+?-(\d)$/m);
					if (numerate && numerate[1]) id += "-" + parseInt(numerate[1], 10) + 1;
					else id += "-1";
				}
				this._headers.push(id);
			}
			return `<h${size} id="${id}">${heading}</h${size}>\n`;
		});
	}

	/**
	 * replace hr
	 *
	 * @param {string} content
	 * @returns string
	 */
	horizontal_rule(content) {
		return content.replaceAll(this._horizontal_rule, "<hr>");
	}

	/**
	 * replace image
	 *
	 * @param {string} content
	 * @returns string
	 */
	image(content) {
		return content.replaceAll(this._image, (...match) => {
			return `<img alt="${(match[1] || "").replaceAll(/[\-\+]/g, (match) => {
				return `\\${match}`;
			})}" src="${match[2].replaceAll(/[\-\+]/g, (match) => {
				return `\\${match}`;
			})}" class="eol1_md" />`;
		});
	}

	/**
	 * replace linebreaks
	 *
	 * @param {string} content
	 * @returns string
	 */
	linebreak(content) {
		return content.replaceAll(this._linebreak, "<br />");
	}

	/**
	 * detects list and replaces list items recursively with available nested blocks
	 *
	 * @param {string} content
	 * @param {boolean|Generator} recursion for passing down a generator for ol list styles
	 * @returns string
	 */
	list(content, recursion = false) {
		const passed_recursion = recursion;
		content = content.replaceAll(this._list, (...match) => {
			// passed recursion is mutable between each callback function appliance
			recursion = passed_recursion;
			// first list item decides for the type
			let bullet = parseInt(match[2]);
			let li_type = bullet > 0 ? "ol" : "ul",
				offset = bullet < 2 ? "" : ` start="${bullet}"`,
				entries = [],
				item_split,
				type;
			switch (li_type) {
				case "ol":
					item_split = /^\d+\. */m;
					if (typeof recursion === "boolean") {
						recursion = new ListTypeGenerator();
						recursion = recursion.generator();
					}
					type = ` type="${recursion.next().value}"`;
					break;
				case "ul":
					item_split = /^[\*|\-|\+] */m;
					recursion = true;
					type = "";
					break;
			}
			for (let list_entry of match[0].split(item_split)) {
				if (list_entry) {
					// recursively replace nested items
					list_entry = this.list((list_entry + "\n").replaceAll(/^ {4}/gm, ""), recursion);
					entries.push(list_entry.trim());
				}
			}
			return `<${li_type} ${type} ${offset} class="eol1_md"><li>` + entries.join("</li><li>") + `</li></${li_type}>`;
		});
		if (passed_recursion) {
			// replace possible nested blocks in list item
			content = this.nested_blocks(content);
		}
		return content;
	}

	/**
	 * replace email adresses with mailto link unless in safeMode
	 * no encoding, since the adress is already at the client anyway
	 *
	 * @param {string} content
	 * @param {boolean} safeMode
	 * @returns string
	 */
	mailto(content, safeMode = false) {
		return content.replaceAll(this._mailto, (...match) => {
			if (safeMode) return this.escapeHtml(match[0]);
			return `<a href="mailto:${match[0]}">${this.escapeHtml(match[0])}</a>`;
		});
	}

	/**
	 * replace marked text
	 *
	 * @param {string} content
	 * @returns string
	 */
	mark(content) {
		return content.replaceAll(this._mark, '<mark class="eol1_md">$1</mark>');
	}

	/**
	 * replace paragraphs
	 *
	 * @param {string} content
	 * @returns string
	 */
	paragraph(content) {
		let code = content.match(/<code.*?code>/gs);
		if (!code)
			return content.replaceAll(this._paragraph, (...match) => {
				match[0] = match[0].trim();
				if (match[0].match(/^(<h|<ol|<ul|<p)/m)) return match[0];
				return `<p>${match[0]}</p>`;
			});
		// split the content by found code blocks to later zip code blocks with converted content
		let nocode = this.separate(content, code);
		for (let i = 0; i < nocode.length; i++) {
			nocode[i] = nocode[i].replaceAll(this._paragraph, (...match) => {
				match[0] = match[0].trim();
				if (match[0].match(/^(<h|<ol|<ul|<p)/m)) return match[0];
				return `<p>${match[0]}</p>`;
			});
		}
		content = [];
		for (let i = 0; i < nocode.length; i++) {
			content.push(nocode[i], code[i] || "");
		}
		return content.join("");
	}

	/**
	 * replace references unless in safeMode
	 * then an internal link to the escaped reference will be generated
	 * like a footnote but without altering the position within the content
	 *
	 * @param {string} content
	 * @param {boolean} safeMode
	 * @returns string
	 */
	reference(content, safeMode = false) {
		const matches = content.matchAll(this._reference);
		// look for actual available references
		for (const match of matches) {
			if (match[4] && match[4].trim()) this._references[match[3]] = match[4].trim();
		}
		const _references = this._references;
		// link references
		content = content.replaceAll(this._reference, (...match) => {
			if (safeMode) {
				// i don't know if this is canon, but i assume this might be a practical solution for safe mode to link to the escaped reference
				if (match[2]) {
					if (match[2] in _references) return `<a href="#ref:${match[2]}">${match[1]}</a>`;
					else return match[1];
				} else if (match[4]) return `<a id="ref:${match[3]}" class="eol1_md"></a>(${this.escapeHtml(match[4]).trim()})`;
			} else {
				// return link with reference
				if (match[2]) {
					if (match[2] in _references) return `<a href="${_references[match[2]]}">${match[1]}</a>`;
					else return match[1];
				}
				// strip reference from text
				else if (match[4]) return "";
			}
		});
		return content;
	}

	/**
	 * replace inline events, href-javascript and some tags with spechialchars
	 * may break some links but better safe than sorry
	 *
	 * @param {string} content
	 * @param {boolean} safeMode
	 * @returns string
	 */
	safeMode(content, safeMode = false) {
		if (safeMode)
			return content.replaceAll(this._safeMode, (...match) => {
				return this.escapeHtml(match[0]);
			});
		return content;
	}

	/**
	 * replace strikethrough
	 *
	 * @param {string} content
	 * @returns string
	 */
	strikethrough(content) {
		return content.replaceAll(this._strikethrough, "<s>$1</s>");
	}

	/**
	 * replace subscript
	 *
	 * @param {string} content
	 * @returns string
	 */
	subscript(content) {
		return content.replaceAll(this._subscript, "<sub>$1</sub>");
	}

	/**
	 * replace superscript
	 *
	 * @param {string} content
	 * @returns string
	 */
	superscript(content) {
		return content.replaceAll(this._superscript, "<sup>$1</sup>");
	}

	/**
	 * replace tables
	 *
	 * @param {string} content
	 * @returns string
	 */
	table(content) {
		return content.replaceAll(this._table, (...match) => {
			let rows = match[0].split("\n");
			// get possible alignments for colums from delimiter row
			let columns = rows[1].split(/(?<!\\)\|/).filter((c) => Boolean(c.trim()));
			let alignment = [],
				align;
			columns.forEach((column) => {
				align = column.trim().match(/(:{0,1})-+(:{0,1})/);
				if (align[1] && align[2]) alignment.push('align="center"');
				else if (align[1]) alignment.push('align="left"');
				else if (align[2]) alignment.push('align="right"');
				else alignment.push("");
			});
			let output = '<table class="eol1_md">',
				row;
			for (let rowindex = 0; rowindex < rows.length; rowindex++) {
				row = rows[rowindex];
				if (!row) continue;
				columns = row.split(/(?<!\\)\|/);
				columns.pop();
				columns.shift();
				switch (rowindex) {
					case 1:
						break;
					case 0:
						output += "<tr>";
						for (let i = 0; i < columns.length; i++) {
							output += `<th ${alignment[i] || ""}>${columns[i].trim()}</th>`;
						}
						output += "</tr>";
						break;
					default:
						output += "<tr>";
						for (let i = 0; i < columns.length; i++) {
							output += `<td ${alignment[i] || ""}>${columns[i].trim() || " "}</td>`;
						}
						output += "</tr>";
						break;
				}
			}
			output += "</table>\n";
			return output;
		});
	}

	/**
	 * replace tasks with checkboxes
	 *
	 * @param {string} content
	 * @returns string
	 */
	task(content) {
		return content.replaceAll(this._task, (...match) => {
			return `<input type="checkbox" disabled ${match[1].trim().length ? "checked" : ""} class="eol1_md"> ${match[2]}`;
		});
	}

	/**
	 * replace certain strings with their symbol
	 *
	 * @param {string} content
	 * @returns string
	 */
	typographer(content) {
		return content.replaceAll(this._typographer, (...match) => {
			return this._typographs[match[0].toLowerCase()];
		});
	}
}

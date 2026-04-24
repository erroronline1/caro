/**
 * [Markdown](https://github.com/erroronline1/markdown)
 * Copyright (C) 2026 error on line 1 (dev@erroronline.one)
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

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
	_anchor_auto = /(?<!\]\()(?:\<{0,1})((?:https*|ftps*|tel):(?:\/\/)*[^\n\s,"'>]+)(?:\>{0,1})/gi; // auto url linking, including some schemes
	_anchor_md = /(?:(?<!!|\\)\[)(.+?)(?:(?<!\\)\])(?:\()(.+?)((?: \").+(?:\"))*(?:(?<!\\)\))(?!\))/gm; // regular md links
	_blockquote = /(^>{1,}.*?\n$)+/gms;
	_code_block = /^ {0,3}([`~]{3})(.*?)\n((?:.|\n)+?)\n^ {0,3}\1\n|^ {4}([^\*\-\d].+)+/gm;
	_code_inline = /(?<!\\)(`{1,2})([^\n]+?)(?<!\\| |\n)\1/g;
	_compress = />\n+|\n *<|[^>]\n+<[^\/]/gm;
	_definition = /(^.+?\n)((?:^: .+?\n)+)/gm;
	_emphasis = /(?<!\\)((?<!\S)\_{1,3}|\*{1,3}(?! ))([^\n]+?)((?<!\\| |\n)\1)/g;
	_escape = /\\(\*|-|~|`|\.|@|>|\^|\[|\]|\(|\)|\||=)/g;
	_footnote = /\[\^(.+?)\](:.+?\n(?: {4}.*?\n)*)*/g;
	_headings = /(?:^)(#+ )(.+?)(?: {#(.+?)}){0,1}(?:#*)$|(?:^)(.+?)\n(={3,}|-{3,})$/gm; // must be first line or have a linebreak before
	_horizontal_rule = /^ {0,3}(?:\-|\- |\*|\* ){3,}$/gm;
	_image = /(?:!\[)(.+?)(?:\])(?:\()(.+?)(?:\))([^\)])/g;
	_inlineEvents = /on\w+?=('|").+?(?<!\\)\1|<(script|title|textarea|style|xmp|iframe|noembed|noframes|plaintext).+?\/\2>|href=(\'|")javascript:.+?(?<!\\)\3/gi;
	_larger = /(?<!\\)\^{2}([^\n]+?)(?<!\\| |\n)\^{2}/g;
	_linebreak = / +\n/g;
	_list = /((?:^)(\*|\-|\+|\d+\.) {1,3}(?:.|\n)+?)(?:\n$)/gm;
	_mailto = /([^\s<]+(?<!\\)@[^\s<]+\.[^\s<]+)/g;
	_mark = /==(.+?)==/g;
	_paragraph = /(?:^$\n)((?<!^<)(?:(\n|.)(?!>$))+?)(?:\n^$)/gim;
	_reference = /(?:(?<!!|\\)\[)(.+?)(?:(?<!\\)\])(?:\[)(.+?)(?:\])|(?:^\[)([^^]+?)(?:\]:)(.+)$/gm;
	_strikethrough = /(?<!\\)~{2}([^\n]+?)(?<!\\| |\n)~{2}/g;
	_subscript = /(?<!\\)~{1}([^\n]+?)(?<!\\| |\n)~{1}/g;
	_superscript = /(?<!\\)\^{1}([^\n]+?)(?<!\\| |\n)\^{1}/g;
	_table = /^((?:\|.+?){1,}\|)\n((?:\| *:{0,1}-+:{0,1} *?){1,}\|)\n((?:(?:\|.+?){1,}\|(?:\n|$))+)/gm;
	_task = /\[(\s*x{0,1}\s*)\] (.+?(?:\n))/gim;
	_typographer = /(?<!\\)\((?:c|r|tm|p)(?<!\\)\)|(?<!\\)\+-|(?<!\\)->/gi;

	// class properties to use over multiple methods
	_headers = [];
	_references = {};
	_limitTo = [];

	// predefined character-sets to replace if required
	_escaped = {
		"&": "&amp;",
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

	/**
	 * entry method to convert a text to markdown
	 *
	 * @param {string} text to convert from markdown to html
	 * @param {boolean} safeMode returns anchors as specialchars
	 * @param (array) limitTo process only given methods, empty for all
	 * @returns {string}
	 */
	md2html(text = "", safeMode = false, limitTo = []) {
		this._limitTo = limitTo;
		text = text.replaceAll(/\r/g, "").replaceAll(/\t/g, "    ") + "\n"; // add a new line for improved pattern matching by default

		// ensure a proper processing order
		[
			"footnote", // should come first to avoid mishandling indentation and reutilizing list and superscript
			"blockquote", // should come second to enable nesting
			"reference", // before a and footnote to not mess up with similar patterns
			"headings", // before hr avoiding conversion of ----
			"horizontal_rule", // before emphasis avoiding matching *** as emphasis
			"definition",
			"task", // before list otherwise only the first occasionally nested item is converted
			"list",
			"code", // after list to avoid erroneous indentation matching
			"anchor", // safeMode can not render anchors to avoid malicious scripts
			"mailto", // safeMode can not render anchors to avoid malicious scripts
			"emphasis",
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
			"inlineEvents", // safeMode can not render inline events and scripts to avoid malicious inserts
		].forEach((method) => {
			if (!limitTo.length || limitTo.includes(method) || (safeMode && ["anchor", "inlineEvents", "reference", "mailto"].includes(method))) {
				if (["anchor", "inlineEvents", "reference", "mailto"].includes(method)) text = this[method](text, safeMode);
				else text = this[method](text);
			}
		});

		text = this.escape(text); // should come after other stylings have been applied
		text = this.compress(text);
		text = text.replaceAll(/\t/g, "    "); // revert code indentation

		return "<!-- Markdown parsing by error on line 1, https://github.com/erroronline1/markdown -->\n" + text;
	}

	/**
	 * escape special chars for code, pre and in case of safeMode
	 *
	 * @param {string} content
	 * @returns string
	 */
	escapeHtml(content) {
		return content.replace(/[&<>"']/g, (m) => {
			return this._escaped[m];
		});
	}

	/**
	 * development helper...
	 */
	debug(...content) {
		console.log(...content);
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
	 * replaces links unless already converted by the reference-method or external links in safeMode
	 * internal links are always rendered since considerable safe
	 *
	 * @param {string} content
	 * @param {boolean} safeMode
	 * @returns string
	 */
	anchor(content, safeMode = false) {
		// replace links in this order
		const _references = this._references;
		return content
			.replaceAll(this._anchor_auto, (...match) => {
				if (Object.values(_references).indexOf(match[1]) > -1) return match[1]; // avoid duplication of link creation from references
				if (match[1].startsWith("#")) return `<a href="${match[1]}" class="eol1_md">${match[1]}</a>`;
				if (safeMode) return this.escapeHtml(match[0]);
				return `<a href="${match[1]}" class="eol1_md">${this.escapeHtml(match[1])}</a>`;
			})
			.replaceAll(this._anchor_md, (...match) => {
				if (match[2].startsWith("#")) return `<a href="${match[2]}" class="eol1_md">${this.escapeHtml(match[1])}</a>`;
				if (safeMode) return this.escapeHtml(match[0]);
				let url = "";
				if (match[2].startsWith("javascript:")) url = match[2];
				else if (match[2].startsWith("#")) url = match[2];
				else {
					let component = new URLSearchParams(match[2]);
					if (component.keys().length) {
						url = match[2].substring(0, match[2].indexOf("?")) + "?" + component.toString();
					} else url = match[2];
					//url += '" target="_blank';
				}
				if (match[3]) url += '" title="' + match[3].substring(2, match[3].length - 1);
				return `<a href="${url}" class="eol1_md">${match[1]}</a>`;
			});
	}

	/**
	 * replace blockquotes recursively
	 *
	 * @param {string} content
	 * @param {boolean} recursion for altered behaviour on pattern relevant linbreak wrappers
	 * @returns string
	 */
	blockquote(content, recursion = false) {
		return content.replaceAll(this._blockquote, (...match) => {
			match[0] = this.blockquote(match[0].replaceAll(/^> {0,1}|^ /gm, ""), true); // remove blockquote character and possible whitespace and check recursively for nested blockquotes
			if (recursion) return `<blockquote class="eol1_md"><p>${match[0]}</p></blockquote>`; // fence with tags
			return `<blockquote class="eol1_md"><p>\n${match[0]}\n</p></blockquote>\n`;
		});
	}

	/**
	 * replace indentated, fenced or quoted code
	 *
	 * @param {string} content
	 * @returns string
	 */
	code(content) {
		return content
			.replaceAll(this._code_block, (...match) => {
				// match[2] for fenced code would be a specified language, not sure what to do with that yet
				// if match[4] code blocks are written with pure indentation
				let code = match[4] ? match[0].replaceAll(/^ {4}/gm, "") : match[3];
				return `<pre class="eol1_md"><code class="eol1_md">${this.escapeHtml(code).replaceAll("    ", "\t")}</code></pre>`; // replace 4 spaces within code with tabs to avoid collision with pre
			})
			.replaceAll(this._code_inline, (...match) => {
				return `<code class="eol1_md">${this.escapeHtml(match[2])}</code>`;
			});
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
			// check whether **opening and closing*** match
			let wrapper = match[1].length,
				tags = [
					[], // wrapper offset, easier than reducing index
					["<em>", "</em>"],
					["<strong>", "</strong>"],
					["<em><strong>", "</strong></em>"],
				];
			return tags[wrapper][0] + this.emphasis(match[2]) + tags[wrapper][1];
		});
	}

	/**
	 * replace escaped characters
	 *
	 * @param {string} content
	 * @returns string
	 */
	escape(content) {
		return content.replaceAll(this._escape, "$1");
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
			["blockquote", "code", "definition", "table"].forEach((method) => {
				if (!this._limitTo.length || this._limitTo.includes(method))
					if (["blockquote"].includes(method)) footnote_block = this[method](footnote_block, true);
					else footnote_block = this[method](footnote_block);
			});

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
			footnote_appendix += `1. <a id="fn:${key}" class="eol1_md"></a>${footnote.trim()} <a href="#fnref:${key}" class="eol1_md">&crarr;</a>\n`;
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
			id = heading.replaceAll(/[^\w\d\s]/ug, "");
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
			return `\n<h${size} id="${id}">${heading}</h${size}>`;
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
		return content.replaceAll(this._image, '<img alt="$1" src="$2" class="eol1_md" />');
	}

	/**
	 * replace inline events, href-javascript and some tags with spechialchars
	 * may break some links but better safe than sorry
	 *
	 * @param {string} content
	 * @param {boolean} safeMode
	 * @returns string
	 */
	inlineEvents(content, safeMode = false) {
		if (safeMode)
			return content.replaceAll(this._inlineEvents, (...match) => {
				return this.escapeHtml(match[0]);
			});
		return content;
	}

	/**
	 * replace lager font decorator
	 * THIS IS A CUSTOM MARKDOWN PROPERTY TO THIS FLAVOUR
	 *
	 * @param {string} content
	 * @returns string
	 */
	larger(content) {
		return content.replaceAll(this._larger, '<span class="eol1_md" style="font-size:larger;">$1</span>');
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
		if (recursion) {
			// replace possible nested blocks in list item
			["blockquote", "code", "definition", "table"].forEach((method) => {
				if (!this._limitTo.length || this._limitTo.includes(method))
					if (["blockquote"].includes(method)) content = this[method](content, true);
					else content = this[method](content);
			});
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
		if (safeMode)
			return content.replaceAll(this._mailto, (...match) => {
				return this.escapeHtml(match[0]);
			});

		return content.replaceAll(this._mailto, (...match) => {
			return `<a href="mailto:${match[0]}">${match[0]}</a>`;
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
		return content.replaceAll(this._paragraph, "<p>$1</p>\n");
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
				columns = row.split(/(?<!\\)\|/).filter((c) => Boolean(c.trim()));
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
							output += `<td ${alignment[i] || ""}>${columns[i].trim()}</td>`;
						}
						output += "</tr>";
						break;
				}
			}
			output += "</table>";
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

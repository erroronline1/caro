/**
 * [Markdown](https://github.com/erroronline1/markdown)
 * Copyright (C) 2026 error on line 1 (dev@erroronline.one)
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

export class Markdown {
	_a_auto = /(?<!\]\()(?:\<{0,1})((?:https*|ftps*|tel):(?:\/\/)*[^\n\s,>]+)(?:\>{0,1})/gi; // auto url linking, including some schemes
	_a_md = /(?:(?<!!|\\)\[)(.+?)(?:(?<!\\)\])(?:\()(.+?)((?: \").+(?:\"))*(?:(?<!\\)\))([^\)]|$)/gm; // regular md links
	_bigger = /(?<!\\)\^{2}([^\n]+?)(?<!\\| |\n)\^{2}/g;
	_blockquote = /(^>{1,} .*(?:\n|$))+/gm;
	_br = / +\n/g;
	_code_block = /^ {0,3}([`~]{3}.*?)\n((?:.+?\n)+)^ {0,3}([`~]{3})\n/gm;
	_code_inline = /(?<!\\)(`{1,2})([^\n]+?)(?<!\\| |\n)\1/g;
	_definition = /(^.+?\n)((?:^: .+?\n)+)/gm;
	_emphasis = /(?<!\\)((?<!\S)\_{1,3}|\*{1,3}(?! ))([^\n]+?)((?<!\\| |\n)\1)/g;
	_escape = /\\(\*|-|~|`|\.|@|>|\^|\[|\]|\(|\)|\|)/g;
	_footnote = /\[\^(.+?)\](:.+?\n(?: {4}.*?\n)*)*/g;
	_headings = /(?:^|^\n+^)(#+ )(.+?)(?: {#(.+?)}){0,1}(?:#*)$|(?:^\n*)(.+?)\n(={3,}|-{3,})$/gm; // must be first line or have a linebreak before
	_hr = /^ {0,3}(?:\-|\- |\*|\* ){3,}$/gm;
	_img = /(?:!\[)(.+?)(?:\])(?:\()(.+?)(?:\))([^\)])/g;
	_inlineEvents = /on\w+?=('|").+?(?<!\\)\1|<(script|title|textarea|style|xmp|iframe|noembed|noframes|plaintext).+?\/\2>|href=(\'|")javascript:.+?(?<!\\)\3/gi;
	_list_any = /((?:^ {0,3})(\*|\-|\+|\d+\.) (?:.|\n)+?)(?:\n$)/gim;
	_list_indented = /\n(^ {4}.+?\n)+/gm;
	_list_line = /(^ {0,3}(\*|\-|\+|\d+\.) )*(.+)/;
	_mail = /([^\s<]+(?<!\\)@[^\s<]+\.[^\s<]+)/g;
	_mark = /==(.+?)==/g;
	_p = /(?:^$\n)((?<!^<)(?:(\n|.)(?!>$))+?)(?:\n^$)/gim;
	_pre = /^ {4}([^\*\-\d].+)+/gm;
	_s = /(?<!\\)~{2}([^\n]+?)(?<!\\| |\n)~{2}/g;
	_sub = /(?<!\\)~{1}([^\n]+?)(?<!\\| |\n)~{1}/g;
	_sup = /(?<!\\)\^{1}([^\n]+?)(?<!\\| |\n)\^{1}/g;
	_table = /^((?:\|.+?){1,}\|)\n((?:\| *:{0,1}-+:{0,1} *?){1,}\|)\n(((?:\|.+?){1,}\|(?:\n|$))+)/gm;
	_task = /\[(\s*x{0,1}\s*)\] (.+?(?:\n))/gim;

	_headers = [];
	_headerchars = /[\w\d\-\sÄÖÜäöüßêÁáÉéÍíÓóÚúÀàÈèÌìÒòÙù]+/;

	_escaped = {
		"&": "&amp;",
		"<": "&lt;",
		">": "&gt;",
		'"': "&quot;",
		"'": "&#039;",
	};
	/**
	 *
	 * @param {string} text to convert from markdown to html
	 * @param {bool} safeMode returns anchors as specialchars
	 * @param (array) limitTo process only given methods, empty for all
	 * @returns {string}
	 */
	md2html(text = "", safeMode = false, limitTo = []) {
		text = text.replaceAll(/\r/g, "").replaceAll(/\t/g, "    ");

		// ensure a proper processing order
		[
			"footnote", // should come first to avoid mishandling indentation and reutilizing list and sup
			"blockquote", // should come second to enable nesting
			"a", // safeMode can not render anchors to avoid malicious scripts
			"code",
			"headings", // before hr avoiding conversion of ----
			"hr", // before emphasis avoiding matching *** as emphasis
			"definition",
			"emphasis",
			"img",
			"task", // before list otherwise only the first occasionally nested item is converted
			"list",
			"mail", // safeMode can not render anchors to avoid malicious scripts
			"mark",
			"pre",
			"s",
			"bigger", // before sup for using the same character twice
			"sub",
			"sup",
			"table",
			"p", // must come after anything previous to not mess up pattern recognitions relying on linebreaks and filtering out previously converted tags
			"br",
			"inlineEvents", // safeMode can not render inline events and scripts to avoid malicious inserts
		].forEach((method) => {
			if (!limitTo.length || limitTo.includes(method) || (safeMode && ["a", "mail", "inlineEvents"].includes(method))) {
				if (["a", "mail", "inlineEvents"].includes(method)) text = this[method](text, safeMode);
				else text = this[method](text);
			}
		});

		text = this.escape(text); // should come after other stylings have been applied
		text = text.replaceAll(/>\n+</gm, '><').replaceAll(/\n *<\//gm, '</'); // delete empty lines betwen tags
		
		return "<!-- Markdown parsing by error on line 1, https://github.com/erroronline1/markdown -->\n" + text;
	}

	escapeHtml(content) {
		return content.replace(/[&<>"']/g, (m) => {
			return this._escaped[m];
		});
	}

	debug(...content) {
		console.log(...content);
	}

	a(content, safeMode = false) {
		// replace links in this order
		return content
			.replaceAll(this._a_auto, (...match) => {
				if (match[1].startsWith("#")) return '<a href="' + match[1] + '" class="eol1_md">' + match[1] + "</a>";
				if (safeMode) return this.escapeHtml(match[0]);
				return '<a href="' + match[1] + '" class="eol1_md">' + this.escapeHtml(match[1]) + "</a>";
				//return '<a href="' + match[0] + '" target="_blank" class="eol1_md">' + match[0] + "</a>";
			})
			.replaceAll(this._a_md, (...match) => {
				if (match[2].startsWith("#")) return '<a href="' + match[2] + '" class="eol1_md">' + this.escapeHtml(match[1]) + "</a>";
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
				return '<a href="' + url + '" class="eol1_md">' + match[1] + "</a>" + match[4];
			});
	}

	bigger(content) {
		// make font size bigger - CUSTOM MARKDOWN
		return content.replaceAll(this._bigger, '<span class="eol1_md" style="font-size:larger;">$1</span>');
	}

	blockquote(content, sub = false) {
		// replace blockquotes recursively
		return content.replaceAll(this._blockquote, (...match) => {
			match[0] = this.blockquote(match[0].replaceAll(/^\n|\n$/g, "").replaceAll(/^> {0,1}|^ /gm, ""), sub); // remove leading and trailing linebreak, blockquote character and possible whitespace and check recursively for nested blockquotes
			if (sub) return '<blockquote class="eol1_md">' + match[0] + "</blockquote>"; // fence with tag, add linebreak for pattern recognition
			return "<blockquote class=\"eol1_md\">\n" + match[0] + "\n</blockquote>"; // fence with tag, add linebreak for pattern recognition
		});
	}

	br(content) {
		// replace linebreaks
		return content.replaceAll(this._br, "<br />");
	}

	code(content, sub = false) {
		return content
			.replaceAll(this._code_block, (...match) => {
				if (match[1] == match[3]) return '<pre class="eol1_md">' + this.escapeHtml(match[2].replaceAll(/^\n|\n$/gm, "")) + "</pre>" + (sub ? "" : "\n");
				return match[0];
			})
			.replaceAll(this._code_inline, (...match) => {
				return '<code class="eol1_md">' + this.escapeHtml(match[2]) + "</code>";
			});
	}

	definition(content) {
		// create a definition block
		return content.replaceAll(this._definition, (...match) => {
			let definitions = [];
			match[2].split("\n").forEach((d) => {
				if (d.length) definitions.push(d.substring(2));
			});
			return '<dl class="eol1_md"><dt>' + match[1] + "</dt><dd>" + definitions.join("</dd><dd>") + "</dd></dl>";
		});
	}

	emphasis(content) {
		// replace all em and strong formatting
		return content.replaceAll(this._emphasis, (...match) => {
			// check whether **opening and closing*** match
			let wrapper = match[1].length,
				tags = [
					[], // wrapper offset, easier than reducing index
					["<em>", "</em>"],
					["<strong>", "</strong>"],
					["<em><strong>", "</strong></em>"],
				];
			return tags[wrapper][0] + match[2] + tags[wrapper][1];
		});
	}

	escape(content) {
		return content.replaceAll(this._escape, "$1");
	}

	footnote(content) {
		// create footnotes
		// find all footnotes
		const footnotes = [...content.matchAll(this._footnote)];
		let _footnotes = {},
			key;
		for (const value of footnotes) {
			_footnotes[value[1]] = (value[2] || "").replaceAll(/\n/gm, "<br />").replaceAll(/^: |^ {4}/gm, "");
		}
		content = content.replaceAll(this._footnote, (...match) => {
			// inline links if available as md superscript
			if (!match[2]) {
				if (!_footnotes[match[1]]) return "^" + match[1] + "^";
				key = Object.keys(_footnotes).indexOf(match[1]) + 1;
				return '^<a id="fnref:' + key + '" href="#fn:' + key + '" class="eol1_md">' + key + "</a>^";
			}
			// delete actual footnote
			return "";
		});
		// create actual footnotes as ordered md list and re-append to content
		let footnote_appendix = "";
		for (const [link, footnote] of Object.entries(_footnotes)) {
			key = Object.keys(_footnotes).indexOf(link) + 1;
			footnote_appendix += '1. <a id="fn:' + key + '" class="eol1_md"></a>' + footnote.trim() + ' <a href="#fnref:' + key + '" class="eol1_md">&crarr;</a>' + "  \n";
		}
		return content + (footnote_appendix ? "\n<hr>\n" + footnote_appendix + "\n" : "");
	}

	headings(content) {
		return content.replaceAll(this._headings, (...match) => {
			let size, heading;
			if (!match[4]) {
				// atx heading starting with #
				size = Math.min(match[1].length - 1, 6);
				heading = match[2].trim();
			} else {
				// setext heading underlined with === or ---
				size = match[5].startsWith("=") ? 1 : 2;
				heading = match[4].trim();
			}
			let id = heading.match(this._headerchars);
			if (match[3] || id[0]) {
				id = (match[3] || id[0]).trim().replaceAll(/\s/g, "-").toLowerCase();
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
			return "\n<h" + size + ' id="' + id + '">' + heading + "</h" + size + ">";
		});
	}

	hr(content) {
		return content.replaceAll(this._hr, "<hr>");
	}

	img(content) {
		return content.replaceAll(this._img, '<img alt="$1" src="$2" class="eol1_md" />');
	}

	inlineEvents(content, safeMode = false) {
		// replace onclick, on-whatever with specialchars
		if (safeMode)
			return content.replaceAll(this._inlineEvents, (...match) => {
				return this.escapeHtml(match[0]);
			});
		return content;
	}

	list(content, sub = false) {
		content = content.replaceAll(this._list_any, (...match) => {
			// check lists for subelements, lists, blockquote, code, table or pre
			return match[0].replaceAll(this._list_indented, (...indented) => {
				return this.list((indented[0] + "\n").replaceAll(/^ {4}/gm, ""), true).replaceAll(/^\n/g, ""); // drop leading linebreak and indentation, but add one to end for pattern recognition
			});
		});
		if (sub) {
			// replace possible nested blocks in advance to list matching
			content = this.blockquote(content, true);
			content = this.code(content, true);
			content = this.definition(content);
			content = this.table(content);
			content = this.pre(content);
		}

		content = content.replaceAll(this._list_any, (...match) => {
			// first list item decides for the type
			let type = parseInt(match[2]) > 0 ? "ol" : "ul",
				entries = [],
				list_line;
			for (const line of match[1].split("\n")) {
				list_line = line.match(this._list_line);
				if (list_line[2]) entries.push(list_line[3] + "\n"); // add trailing linebreak to preserve pattern recognition
				else entries[entries.length - 1] += " " + list_line[3] + "\n"; // add trailing linebreak to preserve pattern recognition
			}
			return "<" + type + ' class="eol1_md"><li>' + entries.join("</li><li>") + "</li></" + type + ">";
		});
		return content;
	}

	mail(content, safeMode = false) {
		if (safeMode)
			return content.replaceAll(this._mail, (...match) => {
				return this.escapeHtml(match[0]);
			});

		return content.replaceAll(this._mail, (...match) => {
			return '<a href="mailto:' + match[0] + '">' + match[0] + "</a>";
		});
	}

	mark(content) {
		return content.replaceAll(this._mark, '<mark class="eol1_md">$1</mark>');
	}

	p(content) {
		return content.replaceAll(this._p, "<p>$1</p>\n");
	}

	pre(content) {
		return content.replaceAll(this._pre, (...match) => {
			return '<pre class="eol1_md">' + this.escapeHtml(match[0].replaceAll(/^ {4}/gm, "")) + "</pre>";
		});
	}

	s(content) {
		return content.replaceAll(this._s, "<s>$1</s>");
	}

	sub(content) {
		return content.replaceAll(this._sub, "<sub>$1</sub>");
	}

	sup(content) {
		return content.replaceAll(this._sup, "<sup>$1</sup>");
	}

	table(content) {
		return content.replaceAll(this._table, (...match) => {
			let rows = match[0].split("\n");
			// get possible alignments for colums from delimiter row
			let columns = rows[1].split(/(?<!\\)\|/).filter((c) => Boolean(c.trim()));
			let alignment = [null], // offset for array_keys($columns) later
				align;
			columns.forEach((column) => {
				align = column.trim().match(/(:{0,1})-+(:{0,1})/);
				if (align[1] && align[2]) alignment.push(' align="center"');
				else if (align[1]) alignment.push(' align="left"');
				else if (align[2]) alignment.push(' align="right"');
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
							output += "<th" + (alignment[i] || "") + ">" + columns[i].trim() + "</th>";
						}
						output += "</tr>";
						break;
					default:
						output += "<tr>";
						for (let i = 0; i < columns.length; i++) {
							output += "<td" + (alignment[i] || "") + ">" + columns[i].trim() + "</td>";
						}
						output += "</tr>";
						break;
				}
			}
			output += "</table>";
			return output;
		});
	}

	task(content) {
		return content.replaceAll(this._task, (...match) => {
			return '<input type="checkbox" disabled' + (match[1].trim().length ? " checked" : "") + ' class="eol1_md"> ' + match[2];
		});
	}
}

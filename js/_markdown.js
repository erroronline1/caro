/**
 * [CARO - Cloud Assisted Records and Operations](https://github.com/erroronline1/caro)
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

export class Markdown {
	/*
	markdown parser.
	supposed to match github-flavour (https://github.github.com/gfm/) to a reasonable amount

	Current limitations:
	* multiple lines for list items must end with one or more spaces on the previous line, linebreaks within lists behave a bit different than regular Markdown
	* this flavour currently lacks support of
		* definitions
		* multiline code within lists
		* syntax highlighting
		* footnotes
		* emojis
	*/

	_a_auto = /(?<!\]\()(?:\<{0,1})((?:https*|ftps*|tel):(?:\/\/)*[^\n\s,>]+)(?:\>{0,1})/gi; // auto url linking, including some schemes
	_a_md = /(?:(?<!!|\\)\[)(.+?)(?:(?<!\\)\])(?:\()(.+?)((?: \").+(?:\"))*(?:(?<!\\)\))([^\)]|$)/gm; // regular md links
	_blockquote = /(^>{1,} .*(?:\n|$|\Z))+/gm;
	_br = / +\n/g;
	_code_block = /^ {0,3}([`~]{3}.*?)\n((?:.+?\n)+)^ {0,3}([`~]{3})/gm;
	_code_inline = /(?<!\\)(`{1,2})([^\n]+?)(?<!\\| |\n)\1/g;
	_emphasis = /(?<!\\)((?<!\S)\_{1,3}|\*{1,3}(?! ))([^\n]+?)((?<!\\| |\n)\1)/g;
	_escape = /\\(\*|-|~|`|\.|@|>|\^|\[|\]|\(|\)|\|)/g;
	_headings = /(?:\A|^\n+^)(#+ )(.+?)(?: {#(.+?)}){0,1}(?:#*)$|(?:^\n*)(.+?)\n(={3,}|-{3,})$/gm; // must be first line or have a linebreak before
	_hr = /^ {0,3}(?:\-|\- |\*|\* ){3,}$/gm;
	_img = /(?:!\[)(.+?)(?:\])(?:\()(.+?)(?:\))([^\)])/g;
	_inlineEvents = /on\w+?=('|").+?(?<!\\)\1|<script.+?\/script>/g;
	_list_any = /(?:^ {0,3}|<blockquote>)((\*|\-|\+|\d+\.) (?:.|\n)+?)(?:^(?! |\* |\- |\+ |\d+\. )|<blockquote>|\Z)/gim;
	_list_nested = /\n(^ {4}.+?\n)+/gm;
	_list_ol = /(^( ){0,3}(\d+\.) (.+?(?:\n|\Z)))+/gm;
	_list_ul = /(^( ){0,3}(\*|\-|\+) (.+?(?:\n|\Z)))+/gm;
	_mail = /([^\s<]+(?<!\\)@[^\s<]+\.[^\s<]+)/g;
	_mark = /==(.+?)==/g;
	_p = /(?:^$\n|\A)((?<!^<table|^<ul|^<ol|^<h\d|^<blockquote|^<pre|)(?:(\n|.)(?!table>$|ul>$|ol>$|h\d>$|blockquote>\n*$|pre>$))+?)(?:\n^$|\Z)/gim;
	_pre = /^ {4}([^\*\-\d].+)+/gm;
	_s = /(?<!\\)~{2}([^\n]+?)(?<!\\| |\n)~{2}/g;
	_sub = /(?<!\\)~{1}([^\n]+?)(?<!\\| |\n)~{1}/g;
	_sup = /(?<!\\)\^{1}([^\n]+?)(?<!\\| |\n)\^{1}/g;
	_table = /^((?:\|.+?){1,}\|)\n((?:\| *:{0,1}-+:{0,1} *?){1,}\|)\n(((?:\|.+?){1,}\|(?:\n|$))+)/gm;
	_task = /\[(\s*x{0,1}\s*)\] (.+?(?:\n|\Z))/gim;

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
	 * @returns {string}
	 */
	md2html(text = "", safeMode = false) {
		text = text.replaceAll(/\r/g, "");

		// ensure a proper processing order
		text = this.blockquote(text); // should come first to enable nesting
		text = this.a(text, safeMode); // nonAdminUser-content can not render anchors to avoid malicious scripts
		text = this.code(text);
		text = this.headings(text); // before hr avoiding conversion of ----
		text = this.hr(text); // before emphasis avoiding matching *** as emphasis
		text = this.emphasis(text);
		text = this.img(text);
		text = this.task(text); // before list otherwise only the first occasionally nested item is converted
		text = this.list(text);
		text = this.mail(text, safeMode);
		text = this.mark(text);
		text = this.pre(text);
		text = this.s(text);
		text = this.sub(text);
		text = this.sup(text);
		text = this.table(text);
		text = this.p(text); // must come after anything previous to not mess up pattern recognitions relying on linebreaks and filtering out previously converted tags
		text = this.br(text);
		text = this.inlineEvents(text, safeMode);
		text = this.escape(text); // should come after other stylings have been applied

		return text;
	}

	escapeHtml(content) {
		return content.replace(/[&<>"']/g, (m) => {
			return this._escaped[m];
		});
	}

	a(content, safeMode = false) {
		// replace links in this order
		if (safeMode) {
			return content
				.replaceAll(this._a_auto, (...match) => {
					return this.escapeHtml(match[0]);
				})
				.replaceAll(this._a_md, (...match) => {
					return this.escapeHtml(match[0]);
				});
		}

		return content.replaceAll(this._a_auto, '<a href="$1" target="_blank" class="inline">$1</a>').replaceAll(this._a_md, (...match) => {
			let url = "";
			if (match[2].startsWith("javascript:")) url = match[2];
			else if (match[2].startsWith("#")) url = match[2];
			else {
				let component = new URLSearchParams(match[2]);
				if (component.keys().length) {
					url = match[2].substring(0, match[2].indexOf("?")) + "?" + component.toString();
				} else url = match[2];
				url += '" target="_blank';
			}
			if (match[3]) url += '" title="' + match[3].substring(2, match[3].length - 1);
			return '<a href="' + url + '" class="inline">' + match[1] + "</a>" + match[4];
		});
	}

	br(content) {
		// replace linebreaks
		return content.replaceAll(this._br, "<br />");
	}

	blockquote(content, sub = false) {
		// replace blockquotes recursively
		return content.replaceAll(this._blockquote, (...match) => {
			match[0] = this.blockquote(match[0].replaceAll(/^\n|\n$/g, "").replaceAll(/^> {0,1}|^ /gm, "")); // remove leading and trailing linebreak, blockquote character and possible whitespace and check recursively for nested blockquotes
			if (!sub) return "<blockquote>\n" + match[0] + "\n</blockquote>"; // fence with tag, add linebreak for pattern recognition
			return "<blockquote>" + match[0] + "</blockquote>"; // fence with tag, add linebreak for pattern recognition
		});
	}

	code(content) {
		return content
			.replaceAll(this._code_block, (...match) => {
				if (match[1] == match[3]) return "<pre>" + this.escapeHtml(match[2]) + "</pre>";
				return match[0];
			})
			.replaceAll(this._code_inline, (...match) => {
				return "<code>" + this.escapeHtml(match[2]) + "</code>";
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
					if (numerate[1]) id += "-" + parseInt(numerate[1], 10) + 1;
					else $id += "-1";
				}
				this._headers.push(id);
			}
			return "<h" + size + ' id="' + id + '">' + heading + "</h" + size + ">";
		});
	}

	hr(content) {
		return content.replaceAll(this._hr, "<hr>");
	}

	img(content) {
		return content.replaceAll(this._img, '<img alt="$1" src="$2" style="float:left; max-width:100%" />');
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
		content = content.replaceAll(this._list_any, (...list) => {
			// check lists for subelements, lists, blockquote, code, table or pre
			return list[1].replaceAll(this._list_nested, (...nested) => {
				return this.list(nested[0].replaceAll(/^ {4}/gm, "") + "\n", true).replaceAll(/^\n/g, ""); // drop leading linebreak, but add one to end for pattern recognition
			});
		});

		if (sub) {
			// replace possible nested blocks in advance to list matching
			content = this.blockquote(content, true);
			content = this.code(content);
			content = this.table(content);
			content = this.pre(content);
		}

		//replace unordered lists
		content = content.replaceAll(this._list_ul, (...match) => {
			let output = "<ul>";
			match[0].split("\n").forEach((item) => {
				if (item) output += "<li>" + item.replaceAll(/^ *[\*\+\-] /gm, "") + "</li>";
			});
			output += "</ul>";
			return output;
		});
		// replace ordered lists
		content = content.replaceAll(this._list_ol, (...match) => {
			let output = "<ol>";
			match[0].split("\n").forEach((item) => {
				if (item) output += "<li>" + "&nbsp;".repeat(3) + item.replaceAll(/^ *\d+\. /gm, "") + "</li>"; // &nbsp; may look a bit weird on screen but improves pdfs
			});
			output += "</ol>";
			return output;
		});
		return content; //preg_replace('/^\n/', '', $content);
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
		return content.replaceAll(this._mark, "<mark>$1</mark>");
	}

	p(content) {
		return content.replaceAll(this._p, "<p>$1</p>\n");
	}

	pre(content) {
		return content.replaceAll(this._pre, (...match) => {
			return "<pre>" + this.escapeHtml(match[0].replaceAll(/^ {4}/gm, "")) + "</pre>";
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
			let output = "<table>",
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
			return '<input type="checkbox" disabled' + (match[1].trim().length ? " checked" : "") + ' class="markdown"> ' + match[2];
		});
	}
}

/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2024 error on line 1 (dev@erroronline.one)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// why not importing the json files directly? because the api tells the frontend wich language is user set in the first place

export class Lang {
	/*
	language files have a context level and their chunks
	:tokens can be passed as a named array to be substituted (like nifty pdo prepared statements)
	chunks can be accessed by context.chunk with the period as separator (like nifty javascript objects)
	*/
	_USER = {};

	constructor() {}

	/**
	 * returns a language specific chunk
	 * @param str request dot separated keys of this._USER
	 * @param object replace replacement key:value pairs to replace :placeholders
	 * @return str textchunk with replacements
	 */
	GET(request, replace = {}) {
		request = request.split(".");

		let chunk = this.find(request, this._USER);
		if (chunk) {
			for (const [pattern, replacement] of Object.entries(replace)) {
				chunk = chunk.replaceAll(pattern, replacement);
			}
			return chunk;
		}
		console.trace(request, replace);
		return "undefined or not provided for client";
	}
	/**
	 * recursively find the language chunk independent of nesting depth
	 * @param array chain split request
	 * @param object lang this._USER or passed subset
	 */
	find(chain, lang) {
		let key = chain.shift();
		if (lang[key]) {
			if (typeof lang[key] === "object") {
				if (!chain) return false;
				return this.find(chain, lang[key]);
			} else return lang[key];
		}
		else return false;
	}

	/**
	 * returns a language specific chunk with whitespaces and periods replaced with underscore as in request parameters
	 * @param str request dot separated keys of this._USER
	 * @param object replace replacement key:value pairs to replace :placeholders
	 * @return str textchunk with replacements and whitespaces replaced with underscore as in request parameters
	 */
	PROPERTY(request, replace = {}) {
		return this.GET(request, replace).replaceAll(/[\s\.]/g, "_");
	}
}

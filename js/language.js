/**
 * SPDX-FileNotice: Part of erroronline1/caro a quality management software.
 * SPDX-FileCopyrightText: © 2023 error on line 1 <dev@erroronline.one>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
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
	 * @param {string} request dot separated keys of this._USER
	 * @param {object} replace replacement key:value pairs to replace :placeholders
	 * @return {string} textchunk with replacements
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
		return `${request.join(".")} undefined or not provided for client`;
	}
	/**
	 * recursively find the language chunk independent of nesting depth
	 * @param {array} chain split request
	 * @param {object} lang this._USER or passed subset
	 * @return {string|boolean} found chunk or false
	 */
	find(chain, lang) {
		let key = chain.shift();
		if (lang[key]) {
			if (typeof lang[key] === "object") {
				if (!chain) return false;
				return this.find(chain, lang[key]);
			} else return lang[key];
		} else return false;
	}

	/**
	 * returns a language specific chunk with whitespaces and periods replaced with underscore as in request parameters
	 * @param {string} request dot separated keys of this._USER
	 * @param {object} replace replacement key:value pairs to replace :placeholders
	 * @return {string} textchunk with replacements and whitespaces replaced with underscore as in request parameters
	 */
	PROPERTY(request, replace = {}) {
		return this.GET(request, replace).replaceAll(/[\s\.]/g, "_");
	}
}

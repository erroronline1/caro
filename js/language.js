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

import { api } from "../js/api.js";

await api.application("get", "language");
// assignment of variable needs suprisingly long and i have not been able to manage this reliable with await
await _.sleep(50);

class Lang {
	/*
	language files have a context level and their chunks
	:tokens can be passed as a named array to be substituted (like nifty pdo prepared statements)
	chunks can be accessed by context.chunk with the period as separator (like nifty javascript objects)
	*/
	constructor() {}
	GET(request, replace = {}) {
		request = request.split(".");
		if (
			!(request[0] in LANGUAGEFILE) ||
			!(request[1] in LANGUAGEFILE[request[0]]) ||
			(2 in request && !(request[2] in LANGUAGEFILE[request[0]][request[1]]))
		) {
			return "undefined or not provided for client";
		}
		let result = (2 in request) ? LANGUAGEFILE[request[0]][request[1]][request[2]] : LANGUAGEFILE[request[0]][request[1]];
		for (const [pattern, replacement] of Object.entries(replace)) {
			result = result.replaceAll(pattern, replacement);
		}
		return result;
	}
	PROPERTY(request, replace = {}) {
		return this.GET(request, replace).replaceAll(/[\s\.]/g, "_");
	}
}
export var LANG = new Lang();

/**
 * [CARO - Cloud Assisted Records and Operations](https://github.com/erroronline1/caro)
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

// personal usecase framework by error on line 1 (erroronline.one)
// this is not underscore.js!

Array.prototype.contains = function (values) {
	return _.contains(this, values);
}; // to use intuitive with arrays
String.prototype.contains = function (values) {
	return _.contains(this, values);
}; // to use intuitive with strings
String.prototype._replaceArray = function (find, replace) {
	return _.replaceArray(this, find, replace);
}; // to use intuitive with strings

var httpResponse = {
	100: "Continue",
	101: "Switching Protocols",
	200: "OK",
	201: "Created",
	202: "Accepted",
	203: "Non-Authoritative Information",
	204: "No Content",
	205: "Reset Content",
	206: "Partial Content",
	207: "Multistatus",
	300: "Multiple Choices",
	301: "Moved Permanently",
	302: "Found",
	303: "See Other",
	304: "Not Modified",
	305: "Use Proxy",
	306: "(Unused)",
	307: "Temporary Redirect",
	400: "Bad Request",
	401: "Unauthorized",
	402: "Payment Required",
	403: "Forbidden",
	404: "Not Found",
	405: "Method Not Allowed",
	406: "Not Acceptable",
	407: "Proxy Authentication Required",
	408: "Request Timeout",
	409: "Conflict",
	410: "Gone",
	411: "Length Required",
	412: "Precondition Failed",
	413: "Request Entity Too Large",
	414: "Request-URI Too Long",
	415: "Unsupported Media Type",
	416: "Requested Range Not Satisfiable",
	417: "Expectation Failed",
	500: "Internal Server Error",
	501: "Not Implemented",
	502: "Bad Gateway",
	503: "Service Unavailable",
	504: "Gateway Timeout",
	505: "HTTP Version Not Supported",
	507: "Insufficient Storage",
	511: "Network Authentication Required",
};

const _ = {
	el: function (x) {
		// shortcut for readabilities sake: _.el('element')
		return document.getElementById(x);
	},
	contains: function (obj, values) {
		// searches if at least one element of values (string or array) occurs in obj (string or array)
		return Array.isArray(values) ? values.some((value) => obj.includes(value)) : obj.includes(values);
	},
	replaceArray: function (obj, find, replace) {
		var replaceString = obj;
		var regex;
		for (var i = 0; i < find.length; i++) {
			regex = new RegExp(find[i], "g");
			replaceString = replaceString.replace(regex, replace[i]);
		}
		return replaceString;
	},

	/**
	 * @param {String} method get, put, post, delete, etc
	 * @param {String} destination url
	 * @param {Array|FormData} payload
	 * @param {Array} errorless_status get response instead of error message
	 * @returns
	 */
	api: async function (method, destination, payload, errorless_status = [200]) {
		method = method.toUpperCase();
		let query = "";
		if (["GET", "DELETE"].includes(method) && payload) {
			if (payload instanceof FormData) {
				payload.forEach((value, key) => {
					query += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(value);
				});
			} else {
				for (const [key, value] of Object.entries(payload)) {
					query += "&" + encodeURIComponent(key) + "=" + encodeURIComponent(value);
				}
			}
		}
		const response = await fetch(destination + (query ? "?" + query : ""), {
			method: method, // *GET, POST, PUT, DELETE, etc.
			cache: "no-cache", // *default, no-cache, reload, force-cache, only-if-cached
			body: ["GET", "DELETE"].includes(method) ? null : payload, // body data type must match "Content-Type" header
			timeout: false,
			headers: {
				"Content-Type": payload instanceof FormData ? "application/octet-stream" : "application/json",
			},
		})
			.then(async (response) => {
				if (!Object.keys(response)) throw new Error("empty or null response");
				if (errorless_status.includes(response.status)) {
					let body = await response.json();
					return {
						status: response.status,
						body: body,
					};
				} else {
					throw new Error("server responded " + response.status + ": " + (httpResponse[response.status] ? httpResponse[response.status] : JSON.stringify(response)));
				}
			})
			.catch((e) => {
				return { error: e };
			});
		return response;
		/* use like 
			_.api(method, url, payload-object)
				.then(data => { do something with data })
				.catch((error) => { do something with error });
		*/
	},
	/**
	 * create an sha-256 hash
	 */
	sha256: async (message) => {
		const msgUint8 = new TextEncoder().encode(message); // encode as (utf-8) Uint8Array
		const hashBuffer = await window.crypto.subtle.digest("SHA-256", msgUint8); // hash the message
		const hashArray = Array.from(new Uint8Array(hashBuffer)); // convert buffer to byte array
		const hashHex = hashArray.map((b) => b.toString(16).padStart(2, "0")).join(""); // convert bytes to hex string
		return hashHex;
	},
	insertChars: function (characters, input) {
		// fills or deletes text on cursor position (textareas or inputs)
		let el = document.getElementById(input);
		oldCPos = el.selectionStart;
		if (characters == "\b") {
			// backspace to delete
			el.value = el.value.substring(0, el.selectionStart - 1) + el.value.substring(el.selectionStart, el.value.length);
			el.selectionStart = el.selectionEnd = oldCPos - 1;
		} else {
			el.value = el.value.substring(0, el.selectionStart) + characters + el.value.substring(el.selectionStart, el.value.length);
			el.selectionStart = el.selectionEnd = oldCPos + characters.length;
		}
		el.focus();
	},
	dragNdrop: {
		// add following to draggable elements:
		// draggable="true" ondragstart="_.dragNdrop.drag(event)" ondragover="_.dragNdrop.allowDrop(event)" ondrop="_.dragNdrop.drop(event,this)"
		// or call following method passing the object itself (not just the id)
		add2DragCollection: function (element) {
			element.setAttribute("draggable", "true");
			element.setAttribute("ondragstart", "_.dragNdrop.drag(event)");
			element.setAttribute("ondragover", "_.dragNdrop.allowDrop(event)");
			element.setAttribute("ondrop", "_.dragNdrop.drop_insertbefore(event,this)");
		},
		allowDrop: function (evnt) {
			evnt.preventDefault();
		},
		drag: function (evnt) {
			evnt.dataTransfer.setData("text", evnt.currentTarget.id);
		},
		drop_insertbefore: function (evnt, that) {
			evnt.preventDefault();
			const data = evnt.dataTransfer.getData("text");
			document.getElementById(data).parentNode.insertBefore(document.getElementById(data), that);
		},
		drop_delete: function (evnt) {
			const data = evnt.dataTransfer.getData("text");
			document.getElementById(data).remove();
		},
	},
	/**
	 * returns either a FormData- or generic object with key-value pairs of inputs detected by target
	 * @param {string|Node} target query selector string or node
	 * @param {boolean} form_data return type
	 * @returns FormData or Object
	 */
	getInputs: function (target, form_data = false) {
		// target can either be a querySelector or a passed node object
		let fields, formdata;
		let form = typeof target === "string" ? document.querySelector(target) : target;

		if (form && form.localName === "form") {
			formdata = new FormData(form);

			// prepared inputs having data-wrap="some___thing" inserting value on the three underscores
			for (const input of Object.values(form)) {
				if (input.dataset.wrap && input.value) {
					formdata.set(input.name, input.dataset.wrap.replace("___", input.value));
				}
			}

			// add special pipe separated dataset for checkboxes with data-grouped attribute
			const grouped = document.querySelectorAll("[data-grouped]"),
				groups = {};
			for (const checkbox of grouped) {
				if (checkbox.form === form && checkbox.checked) {
					if (groups[checkbox.dataset.grouped]) groups[checkbox.dataset.grouped].push(checkbox.name);
					else groups[checkbox.dataset.grouped] = [checkbox.name];
				}
			}
			for (const [group, values] of Object.entries(groups)) {
				formdata.append(group, values.join(" | "));
			}

			if (form_data) return formdata;
			// else create object returned at the end
			for (const [key, value] of formdata.entries()) {
				formdata[key] = value;
			}
		} else {
			fields = {};
			let inputs = document.querySelectorAll(target), // inputs must have their own grouping querySelector
				input;
			for (const _input of inputs) {
				input = _input.cloneNode(true); // clone because we do not want to override original node attributes
				switch (input.type) {
					case "radio":
						if (!input.checked) continue;
						if (input.name.contains("[]")) {
							input.name = input.name.replace("[]", "");
							if (typeof fields[input.name] === "object") fields[input.name].push(input.checked ? input.value || input.name : 0);
							else fields[input.name] = [input.checked ? input.value || input.name : 0];
						} else fields[input.name] = input.checked ? input.value || input.name : 0;
						break;
					case "checkbox":
						if (!input.checked) continue;
						if (input.name.contains("[]")) {
							input.name = input.name.replace("[]", "");
							if (typeof fields[input.name] === "object") fields[input.name].push(input.checked ? input.value || input.name : 0);
							else fields[input.name] = [input.checked ? input.value || input.name : 0];
						} else fields[input.name] = input.checked ? input.value || input.name : 0;
						break;
					case "text":
					case "tel":
					case "time":
					case "date":
					case "number":
					case "hidden":
					case "email":
					case "datetime-local":
						if (!input.value) continue;
						// prepared inputs having data-wrap="some___thing" inserting value on the three underscores
						if (input.dataset.wrap && input.value) {
							input.value = input.dataset.wrap.replace("___", input.value);
						}
					default:
						if (!input.value) continue;
						if (input.name.contains("[]")) {
							input.name = input.name.replace("[]", "");
							if (typeof fields[input.name] === "object" && input.value) fields[input.name].push(input.value);
							else if (input.value) fields[input.name] = [input.value];
						} else fields[input.name] = input.value;
				}

				// add special pipe separated dataset for checkboxes with data-grouped attribute
				const grouped = document.querySelectorAll(target + "[data-grouped]"),
					groups = {};
				for (const checkbox of grouped) {
					if (checkbox.checked) {
						if (groups[checkbox.dataset.grouped]) groups[checkbox.dataset.grouped].push(checkbox.name);
						else groups[checkbox.dataset.grouped] = [checkbox.name];
					}
				}
				for (const [group, values] of Object.entries(groups)) {
					fields[group] = values.join(" | ");
				}

				if (form_data) {
					formdata = new FormData();
					for (const [key, value] of Object.entries(fields)) {
						formdata.append(key, value);
					}
					return formdata;
				}
			}
		}
		return fields;
	},
	sleep: function (delay = 500) {
		// use from async function with await _.sleep(ms)
		return new Promise((resolve) => setTimeout(resolve, delay));
	},
	idb: {
		/*
		_.idb.database = {...}
		_.idb.add({'123':'456'})
		_.idb.all()

		you could as well assign the idb-property to a new variable to have a brand new database handler
		*/
		version: 1,
		database: {
			// modify this once after import
			name: "database",
			table: "table",
		},
		promise: function (request) {
			return new Promise((resolve, reject) => {
				request.oncomplete = request.onsuccess = () => resolve(request.result);
				request.onabort = request.onerror = () => reject(request.error);
			});
		},
		open: function (indices) {
			return new Promise((resolve, reject) => {
				let initialize = indexedDB.open(_.idb.database.name, _.idb.version);
				initialize.onupgradeneeded = () => {
					const db = initialize.result;
					// Create an object store named ${store}, or retrieve it if it already exists.
					// Object stores in databases are where data are stored.
					let entry;
					if (!db.objectStoreNames.contains(_.idb.database.table)) {
						entry = db.createObjectStore(_.idb.database.table, {
							autoIncrement: true,
						});
						if (indices != undefined)
							for (index of indices) {
								entry.createIndex(index, index, {
									unique: false,
								});
							}
					} else {
						entry = initialize.transaction.objectStore(_.idb.database.table);
					}
				};
				initialize.onsuccess = () => {
					resolve({
						db: initialize.result,
						table: _.idb.database.table,
					});
				};
				initialize.onerror = () => {
					reject(`error opening database ${initialize.errorCode}`);
				};
			});
		},
		add: function (contents = {}) {
			// contents to be an object with key:value pairs
			return new Promise(async (resolve, reject) => {
				const db = await _.idb.open();
				const transaction = db.db.transaction(db.table, "readwrite"),
					objectStore = transaction.objectStore(db.table),
					entry = {
						timestamp: Date.now(),
					};
				for (const [key, value] of Object.entries(contents)) {
					entry[key] = value;
				}
				const objectStoreRequest = objectStore.add(entry);
				objectStoreRequest.onerror = (event) => {
					reject({ event: event, contents: contents });
				};
				objectStoreRequest.onsuccess = (event) => {
					resolve(event.target.result); // key
				};
			});
		},
		all: async function () {
			//returns all entries in given table with keys
			return new Promise(async (resolve, reject) => {
				const db = await _.idb.open();
				const transaction = db.db.transaction(db.table, "readonly"),
					objectStore = transaction.objectStore(db.table),
					keys = objectStore.getAllKeys(),
					results = {};
				keys.onsuccess = async function (event) {
					for (const k of event.target.result) {
						let entry = await _.idb.promise(objectStore.get(k));
						results[k] = entry;
					}
					resolve(results);
				};
				keys.onerror = function (event) {
					reject(`error getting entries: ${event.target.errorCode}`);
				};
			});
		},
		delete: async function (keys = []) {
			return new Promise(async (resolve, reject) => {
				const db = await _.idb.open();
				const transaction = await db.db.transaction(db.table, "readwrite"),
					store = await transaction.objectStore(db.table);
				for await (const key of keys) {
					store.delete(key);
				}
				transaction.oncomplete = function (event) {
					resolve(keys);
				};
				transaction.onerror = function (event) {
					reject(`error deleting entries: ${event.target.errorCode}`);
				};
			});
		},
	},
	file: {
		// kudos https://web.dev/patterns/files/drag-and-drop-files
		supportsFileSystemAccessAPI: "getAsFileSystemHandle" in DataTransferItem.prototype,
		supportsWebkitGetAsEntry : "webkitGetAsEntry" in DataTransferItem.prototype,

		dragin: async function (dragarea, destination_input) {
			dragarea.addEventListener("dragover", (e) => {
				// Prevent navigation.
				e.preventDefault();
			});
			dragarea.addEventListener("dragenter", (e) => {
				dragarea.style.outlineOffset = ".25em";
				dragarea.style.outline = "dashed rgb(153, 179, 132)";
			});
			dragarea.addEventListener("dragleave", (e) => {
				dragarea.style.outline = "";
			});
			dragarea.addEventListener("drop", async (e) => {
				e.preventDefault();
				dragarea.style.outline = "";
				const fileHandlesPromises = [...e.dataTransfer.items].filter((item) => item.kind === "file").map((item) => (this.supportsFileSystemAccessAPI ? item.getAsFileSystemHandle() : this.supportsWebkitGetAsEntry ? item.webkitGetAsEntry() : item.getAsFile()));
				for await (const handle of fileHandlesPromises) {
					if (handle.kind === "directory" || handle.isDirectory) {
						return;
					} else {
						destination_input.files = e.dataTransfer.files;
					}
				}
				destination_input.dispatchEvent(new Event("change"));
			});
		},
		// kudos https://ryanseddon.com/html5/gmail-dragout/
		// availability most likely limited though
		dragout:async function(anchor){
			if (anchor.dataset.downloadurl) anchor.addEventListener("dragstart",function(e){
					e.dataTransfer.setData("DownloadURL", anchor.dataset.downloadurl);
			},false);
		}
	},
};

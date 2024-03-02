// personal usecase framework by error on line 1 (erroronline.one)
// this is not underscore.js!

Array.prototype.contains = function (values) {
	return _.contains(this, values);
}; // to use intitive with arrays
String.prototype.contains = function (values) {
	return _.contains(this, values);
}; // to use intitive with string

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
	api: async function (method, destination, payload, form_data = false) {
		method = method.toUpperCase();
		let query = "";
		if (method == "GET") {
			query = payload ? "?" : "";
			Object.keys(payload).forEach((key) => {
				query += "&" + key + "=" + payload[key];
			});
		}
		const response = await fetch(destination + query, {
			method: method, // *GET, POST, PUT, DELETE, etc.
			cache: "no-cache", // *default, no-cache, reload, force-cache, only-if-cached
			body: method == "GET" ? null : form_data ? payload : JSON.stringify(payload), // body data type must match "Content-Type" header
		}).then(async (response) => {
			if (response.statusText === "OK")
				return {
					status: response.status,
					body: await response.json(),
				};
			else throw new Error("server responded " + response.status + ": " + httpResponse[response.status]);
		});
		return response;
		/* use like 
			_.api(method, url, payload-object)
				.then(data => { do something with data })
				.catch((error) => { do something with error });
		*/
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
	getInputs: function (querySelector, form_data = false) {
		let fields;
		if (form_data) {
			fields = new FormData(document.querySelector(querySelector));
			// add special comma separated dataset for checkboxes with data-grouped attribute
			const grouped = document.querySelectorAll("[data-grouped]"),
				groups = {};
			for (const checkbox of grouped) {
				if (checkbox.form === document.querySelector(querySelector) && checkbox.checked) {
					if (groups[checkbox.dataset.grouped]) groups[checkbox.dataset.grouped].push(checkbox.name);
					else groups[checkbox.dataset.grouped] = [checkbox.name];
				}
			}
			for (const [group, values] of Object.entries(groups)) {
				fields.append(group, values.join(", "));
			}
		} else {
			fields = {};
			let inputs = document.querySelectorAll(querySelector), // inputs must have their own grouping querySelector
				sanitizedname;
			for (const input of inputs) {
				input.value = encodeURIComponent(input.value);
				sanitizedname = input.name.replaceAll(/\W/g, "_");
				if (input.type == "radio" && input.checked) fields[sanitizedname] = input.value;
				else if (input.type == "checkbox") {
					if (input.name.contains("[]")) {
						input.name = input.name.replace("[]", "");
						if (typeof fields[sanitizedname] === "object" && input.value) fields[sanitizedname].push(input.checked ? input.value : 0);
						else if (input.value) fields[sanitizedname] = [input.checked ? input.value : 0];
					} else fields[sanitizedname] = input.checked ? input.value : 0;
				} else if (["text", "tel", "time", "date", "number", "hidden", "email"].contains(input.type)) {
					if (input.name.contains("[]")) {
						input.name = input.name.replace("[]", "");
						if (typeof fields[sanitizedname] === "object" && input.value) fields[sanitizedname].push(input.value);
						else if (input.value) fields[sanitizedname] = [input.value];
					} else fields[sanitizedname] = input.value;
				} else fields[sanitizedname] = input.value;
			}
		}
		return fields;
	},
	sleep: function (delay) {
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
					console.log("error storing ", contents, event);
					reject(event.target);
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
};

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

import { assemble_helper, Dialog, Toast } from "./assemble.js";
import { compose_helper } from "./compose.js";

export const api = {
	/**
	 *                           _     _     _       _
	 *   ___ ___ ___ _ _ ___ ___| |_ _| |___| |_ ___| |___ ___ ___
	 *  | . |  _| -_| | | -_|   |  _| . | .'|  _| .'| | . |_ -|_ -|
	 *  |  _|_| |___|\_/|___|_|_|_| |___|__,|_| |__,|_|___|___|___|
	 *  |_|
	 *
	 * tracks GET queries and alerts if a changed form is about to get left
	 */
	preventDataloss: {
		// explicitly start this on any eligible successFn
		// on appending queries (e.g. searches) set api.preventDataloss.monitor = false within query routing
		monitor: false,
		event: function (event) {
			// api must define data-loss=prevent for formfields that should be tracked once changed
			if (event.target.dataset.loss !== "prevent") return;
			api.preventDataloss.monitor = true;
		},
		start: function () {
			document.addEventListener("input", api.preventDataloss.event);
			document.addEventListener("textarea", api.preventDataloss.event);
		},
		stop: function () {
			document.removeEventListener("input", api.preventDataloss.event);
			document.removeEventListener("textarea", api.preventDataloss.event);
			api.preventDataloss.monitor = false;
		},
		proceedAnyway: async function (method) {
			if (api.preventDataloss.monitor && method.toUpperCase() === "GET") {
				const options = {};
				options[LANG.GET("general.prevent_dataloss_cancel")] = false;
				options[LANG.GET("general.prevent_dataloss_ok")] = {
					value: true,
					class: "reducedCTA",
				};
				return await new Dialog({
					type: "confirm",
					header: LANG.GET("general.prevent_dataloss"),
					options: options,
				}).then((response) => {
					return response;
				});
			}
			return true;
		},
	},
	/**
	 *                 _
	 *   ___ ___ ___ _| |
	 *  |_ -| -_|   | . |
	 *  |___|___|_|_|___|
	 *
	 * handles prepared requests
	 * @param {string} method get|put|post|delete
	 * @param {array} request url parameters to pass to api
	 * @param {function} successFn handle result
	 * @param {function} errorFn handle error
	 * @param {object} payload FormData object|object
	 * @param {boolean} form_data tell _.api how to handle payload
	 * @returns none / results of successFn
	 */
	send: async (method, request, successFn = null, errorFn = null, payload = {}, form_data = false) => {
		if (!(await api.preventDataloss.proceedAnyway(method))) return false;
		api.preventDataloss.stop();
		api.loadindicator(true);
		if (window._user && window._user.cached_identity && ["post", "put"].includes(method) && payload instanceof FormData) {
			let sanitizedpayload = Object.fromEntries(payload);
			for (const [key, value] of Object.entries(sanitizedpayload)) {
				// remove file keys for being shifted to $_FILES within the stream
				// and quick sanitation of arrays; can't be handled by this object
				if (value instanceof File && (method === "post" || (method === "put" && value.size)) || key.endsWith('[]')) {
					delete sanitizedpayload[key];
				}
				// unset '0' values that are not recognized by backend
				if (value == "0") sanitizedpayload[key] = "";
			}
			sanitizedpayload = JSON.stringify(sanitizedpayload)
				.replaceAll(/\\r|\\n|\\t/g, "")
				.replaceAll(/[\W_]/g, ""); // harmonize cross browser
			const b = new Blob([sanitizedpayload], {
				type: "application/json",
			});
			//console.log(payload, sanitizedpayload, b.size);
			payload.append("_user_cache", await _.sha256(window._user.cached_identity + b.size.toString()));
		}
		await _.api(method, "api/api.php/" + request.join("/"), payload, form_data)
			.then(async (data) => {
				document.querySelector("header>div:nth-of-type(1)").style.display = data.status === 200 ? "none" : "block";
				if (data.status === 203) new Toast(LANG.GET("general.service_worker_get_cache_fallback"), "info");
				if (data.status === 207) {
					new Toast(LANG.GET("general.service_worker_post_cache_fallback"), "info");
					_serviceWorker.onPostCache();
					return;
				}
				if (successFn) await successFn(data.body);
			})
			.catch((error) => {
				console.trace(error);
				if (errorFn != null) errorFn(error);
				new Toast(error, "error");
			});
		api.loadindicator(false);
	},
	/**
	 *   _           _ _       _ _         _
	 *  | |___ ___ _| |_|___ _| |_|___ ___| |_ ___ ___
	 *  | | . | .'| . | |   | . | |  _| .'|  _| . |  _|
	 *  |_|___|__,|___|_|_|_|___|_|___|__,|_| |___|_|
	 *
	 * ui feedback on accuring requests that are expected to take longer
	 * @param {any} toggle initiates inidicator, undefined|null|false disables all
	 * @returns none
	 */
	loadindicator: (toggle) => {
		if (toggle) {
			api.loadindicatorTimeout.push(
				setTimeout(() => {
					document.querySelector("body").style.cursor = "wait";
					document.querySelector(".loader").style.display = "block";
					document.querySelector(".loader").style.opacity = "1";
				}, 500)
			); // wait a bit to avoid flash on every request
			return;
		}
		api.loadindicatorTimeout.map((id) => clearTimeout(id));
		api.loadindicatorTimeout = [];
		document.querySelector("body").style.cursor = "initial";
		document.querySelector(".loader").style.opacity = "0";
		setTimeout(() => {
			document.querySelector(".loader").style.display = "none";
		}, 300);
	},
	loadindicatorTimeout: [],

	/**
	 *             _     _       _             _
	 *   _ _ ___ _| |___| |_ ___| |_ ___ ___ _| |___ ___
	 *  | | | . | . | .'|  _| -_|   | -_| .'| . | -_|  _|
	 *  |___|  _|___|__,|_| |___|_|_|___|__,|___|___|_|
	 *      |_|
	 * 
	 * sets the documents header and is supposed to scroll to top
	 * @param {string} string content
	 */
	update_header: function (string = "") {
		if (string) document.querySelector("header>h1").innerHTML = string;
		window.scrollTo({
			top: 0,
			behavior: "smooth",
		});
	},

	/**
	 *               _ _         _   _
	 *   ___ ___ ___| |_|___ ___| |_|_|___ ___
	 *  | .'| . | . | | |  _| .'|  _| | . |   |
	 *  |__,|  _|  _|_|_|___|__,|_| |_|___|_|_|
	 *      |_| |_|
	 * 
	 * imports serverside defined languagefile
	 * handles user login/logout
	 * loads application menu
	 * loads application landing page
	 * manages manual
	 *
	 * @param {string} method get|post|put|delete
	 * @param {array} request api method, logintoken|manual id
	 * @returns request
	 */
	application: async (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "application");
		let successFn, payload;
		switch (request[1]) {
			case "language":
				successFn = async function (data) {
					window.LANGUAGEFILE = data.data;
				};
				break;
			case "login":
				payload = _.getInputs("[data-usecase=login]", true);

				successFn = async function (data) {
					await api.application("get", "menu");
					if (data.render && data.render.form) {
						const render = new Assemble(data.render);
						document.getElementById("main").replaceChildren(render.initializeSection());
						render.processAfterInsertion();
						let signin = LANG.GET("menu.application_signin"),
							greeting = ", " + signin.charAt(0).toLowerCase() + signin.slice(1);
						api.update_header(
							LANG.GET("general.welcome_header", {
								":user": greeting,
							})
						);
						return;
					}
					window._user = data.user;
					if (_user.image) {
						const firstLabel = document.querySelector("[data-for=userMenuApplication]>label");
						firstLabel.style.backgroundImage = "url('" + _user.image + "')";
						firstLabel.style.maskImage = firstLabel.style.webkitMaskImage = "none";
					}
					if (_user.app_settings) {
						for (const [key, value] of Object.entries(_user.app_settings)) {
							switch (key) {
								case "forceDesktop":
									if (value) {
										let stylesheet;
										for (const [i, sname] of Object.entries(document.styleSheets)) {
											if (!sname.href.includes("style.css")) continue;
											stylesheet = document.styleSheets[i].cssRules;
											break;
										}
										for (let i = 0; i < stylesheet.length; i++) {
											if (stylesheet[i].conditionText === "only screen and (min-width: 64em)") {
												stylesheet[i].media.mediaText = "only screen and (min-width: 4em)";
											}
										}
									}
									break;
								case "theme":
									if (value) {
										document.getElementsByTagName("head")[0].removeChild(document.getElementById("csstheme"));
										const link = document.createElement("link");
										link.href = "./" + value + ".css";
										link.type = "text/css";
										link.rel = "stylesheet";
										link.media = "screen";
										link.id = "csstheme";
										document.getElementsByTagName("head")[0].appendChild(link);
									}
							}
						}
					}
					api.application("get", "start");
				};
				break;
			case "menu":
				successFn = function (data) {
					assemble_helper.userMenu(data.render);
				};
				break;
			case "start":
				successFn = function (data) {
					let signin = LANG.GET("menu.application_signin"),
						greeting = ", " + signin.charAt(0).toLowerCase() + signin.slice(1);
					if (data.user) greeting = " " + data.user;
					api.update_header(
						LANG.GET("general.welcome_header", {
							":user": greeting,
						})
					);
					const render = new Assemble(data.render);
					document.getElementById("main").replaceChildren(render.initializeSection());
					render.processAfterInsertion();
				};
				break;
			case "manual":
				switch (method) {
					case "get":
						successFn = function (data) {
							api.update_header(LANG.GET("menu.application_manual_manager"));
							const render = new Assemble(data.render);
							document.getElementById("main").replaceChildren(render.initializeSection());
							render.processAfterInsertion();
						};
						break;
					case "delete":
						successFn = function (data) {
							new Toast(data.response.msg, data.response.type);
							api.application("get", request[1], data.response.id);
						};
						break;
					default:
						successFn = function (data) {
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							api.application("get", request[1], data.response.id);
						};
						payload = _.getInputs("[data-usecase=manual]", true);
						break;
				}
				break;
			default:
				return;
		}
		await api.send(method, request, successFn, null, payload, method === "post" || method === "put");
	},

	/**
	 *             _ _ _
	 *   ___ _ _ _| |_| |_
	 *  | .'| | | . | |  _|
	 *  |__,|___|___|_|_|
	 *
	 * displays audit contents
	 *
	 * @param {string} method get|post|put|delete
	 * @param {array} request api method
	 * @returns request
	 */
	audit: (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "audit");
		let payload,
			successFn = function (data) {
				if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
				if (data.render !== undefined) {
					const options = {};
					options[LANG.GET("general.ok_button")] = false;
					new Dialog({
						type: "input",
						render: data.render,
						options: options,
					});
				}
			},
			title = {
				checks: LANG.GET("menu.audit"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "export":
						break;
					default:
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] + (request[2] ? (" - " + LANG.GET("audit.checks_type." + request[2])) : ""));
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
				}
				break;
			case "post":
				payload = _.getInputs("[data-usecase=audit]", true);
				break;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},

	/**
	 *           _           _
	 *   ___ ___| |___ ___ _| |___ ___
	 *  |  _| .'| | -_|   | . | .'|  _|
	 *  |___|__,|_|___|_|_|___|__,|_|
	 *
	 * planning and editing calendar entries
	 *
	 * @param {string} method get|post|put|delete
	 * @param  {array} request api method, name|id
	 * @returns request
	 */
	calendar: (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "calendar");
		let payload,
			successFn = function (data) {
				if (data.render) {
					api.update_header(title[request[1]]);
					const render = new Assemble(data.render);
					document.getElementById("main").replaceChildren(render.initializeSection());
					render.processAfterInsertion();
					if (request[3] !== undefined) location.hash = "#displayspecificdate";
				}
				if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
			},
			title = {
				schedule: LANG.GET("menu.calendar_scheduling"),
				timesheet: LANG.GET("menu.calendar_timesheet"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "monthlyTimesheets":
						successFn = function (data) {
							if (data.render !== undefined) {
								const options = {};
								options[LANG.GET("general.ok_button")] = false;
								new Dialog({
									type: "input",
									render: data.render,
									options: options,
								});
							}
						};
				}
				break;
			case "post":
				payload = window.calendarFormData; // as prepared by utility.js _client.calendar.createFormData()
				break;
			case "put":
				switch (request[1]) {
					case "complete":
						break;
					default:
						payload = window.calendarFormData; // as prepared by utility.js _client.calendar.createFormData()
				}
				break;
			case "delete":
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post" || method === "put");
	},

	/**
	 *               ___ _ _ _
	 *   ___ ___ _ _|  _|_| | |_ ___ ___
	 *  |  _|_ -| | |  _| | |  _| -_|  _|
	 *  |___|___|\_/|_| |_|_|_| |___|_|
	 *
	 * loads, executes and manages csv filters
	 *
	 * @param {string} method get|post
	 * @param {array} request api method
	 * @returns request
	 */
	csvfilter: (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "csvfilter");
		let payload,
			successFn = function (data) {
				if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
				if (data.log !== undefined) {
					const dialog = {
						type: "input",
						render: [
							{ type: "textblock", content: data.log.join("\n") },
							{ type: "links", content: data.links },
						],
					};
					new Dialog(dialog);
				}
			},
			title = {
				rule: LANG.GET("menu.csvfilter_filter_manager"),
				filter: LANG.GET("menu.csvfilter_filter"),
			};
		switch (method) {
			case "get":
				successFn = function (data) {
					if (data.render) {
						api.update_header(title[request[1]]);
						const render = new Assemble(data.render);
						document.getElementById("main").replaceChildren(render.initializeSection());
						render.processAfterInsertion();
					}
					if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
				};
				break;
			case "post":
				payload = _.getInputs("[data-usecase=csvfilter]", true);
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},

	/**
	 *   ___ _ _
	 *  |  _|_| |___
	 *  |  _| | | -_|
	 *  |_| |_|_|___|
	 *
	 * displays and manages provided files, either administrative managed or open sharepoint
	 *
	 * @param {string} method get|post|delete
	 * @param  {array} request api method, search term / bundle name | requested directory name, requested filename
	 * @returns request
	 */
	file: async (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "file");
		let successFn = function (data) {
				if (data.render) {
					api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
					const render = new Assemble(data.render);
					document.getElementById("main").replaceChildren(render.initializeSection());
					render.processAfterInsertion();
				}
				if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
				if (data.response !== undefined && data.response.redirect !== undefined) api.file("get", ...data.response.redirect);
			},
			payload,
			title = {
				files: LANG.GET("menu.files_files"),
				bundle: LANG.GET("menu.files_bundles"),
				sharepoint: LANG.GET("menu.files_sharepoint"),
				filemanager: LANG.GET("menu.files_file_manager"),
				bundlemanager: LANG.GET("menu.files_bundle_manager"),
				externalfilemanager: LANG.GET("menu.files_external_file_manager"),
			};

		switch (method) {
			case "get":
				switch (request[1]) {
					case "filter":
						successFn = function (data) {
							if (data.data) {
								const all = document.querySelectorAll("[data-filtered]");
								for (const file of all) {
									if (request[1] === "bundle") {
										file.parentNode.style.display = data.data.includes(file.dataset.filtered) ? "block" : "none";
									} else file.style.display = data.data.includes(file.dataset.filtered) ? "block" : "none";
								}
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					case "bundlefilter":
						successFn = function (data) {
							if (data.data) {
								const all = document.querySelectorAll("[data-filtered]");
								for (const list of all) {
									if (isNaN(list.dataset.filtered)) continue;
									list.parentNode.style.display = data.data.includes(list.dataset.filtered) ? "block" : "none";
								}
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
				}
				break;
			case "post":
				successFn = function (data) {
					if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
					if (data.response !== undefined && data.response.redirect !== undefined) api.file("get", ...data.response.redirect);
				};
				payload = _.getInputs("[data-usecase=file]", true);
				break;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},

	/**
	 *   ___
	 *  |  _|___ ___ _____
	 *  |  _| . |  _|     |
	 *  |_| |___|_| |_|_|_|
	 *
	 * form component and form management with creation, editing and approval
	 *
	 * @param {string} method get|post|put|delete
	 * @param  {array} request api method, name|id
	 * @returns request
	 */
	form: (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "form");
		let successFn,
			payload,
			title = {
				component_editor: LANG.GET("menu.forms_manage_components"),
				form_editor: LANG.GET("menu.forms_manage_forms"),
				approval: LANG.GET("menu.forms_manage_approval"),
				bundle: LANG.GET("menu.forms_manage_bundles"),
			},
			composedComponent;
		switch (method) {
			case "get":
				switch (request[1]) {
					case "component":
						successFn = function (data) {
							if (data.render) {
								data.render.content.name = data.render.name;
								if (data.render.content) compose_helper.importForm([data.render.content]);
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					case "component_editor":
						compose_helper.componentIdentify = 0;
						compose_helper.componentSignature = 0;
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								const render = new Compose(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
								if (data.render.component) compose_helper.importComponent(data.render.component);
								// create multipart form for file uploads
								compose_helper.addComponentMultipartFormToMain();
								api.preventDataloss.start();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					case "approval":
					case "bundle":
					case "form":
						api.update_header(title[request[1]]);
						successFn = function (data) {
							api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
							if (data.render) {
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					case "form_editor":
						compose_helper.componentIdentify = 0;
						compose_helper.componentSignature = 0;
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								const render = new Compose(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
								if (data.render.components) compose_helper.importForm(data.render.components);
								api.preventDataloss.start();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
				}
				break;
			case "put":
				switch (request[1]) {
					case "approval":
						successFn = function (data) {
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							if (data.response !== undefined && data.response.reload !== undefined) api.form("get", data.response.reload);
						};
						payload = _.getInputs("[data-usecase=approval]", true);
						break;
				}
				break;
			case "post":
				switch (request[1]) {
					case "component":
						successFn = function (data) {
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							if (data.response !== undefined && data.response.reload !== undefined) api.form("get", data.response.reload);
						};
						composedComponent = compose_helper.composeNewComponent();
						if (!composedComponent) return;
						compose_helper.addComponentStructureToComponentForm(composedComponent);
						payload = _.getInputs("[data-usecase=component_editor_form]", true);
						break;
					case "form":
						successFn = function (data) {
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							if (data.response !== undefined && data.response.reload !== undefined) api.form("get", data.response.reload);
						};
						if (!(payload = compose_helper.composeNewForm())) return;
						break;
					case "bundle":
						successFn = function (data) {
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						payload = _.getInputs("[data-usecase=bundle]", true);
						break;
				}
				break;
			case "delete":
				successFn = function (data) {
					if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
					if (data.response !== undefined && data.response.reload !== undefined) api.form("get", data.response.reload);
				};
				break;
		}
		api.send(method, request, successFn, null, payload, composedComponent || request[1] === "bundle" || method === "put");
	},

	/**
	 *
	 *   _____ ___ ___ ___ ___ ___ ___
	 *  |     | -_|_ -|_ -| .'| . | -_|
	 *  |_|_|_|___|___|___|__,|_  |___|
	 *                        |___|
	 * handles internal messenger
	 *
	 * @param {string} method get|post|delete
	 * @param  {array} request api method, filter term / message id / message form data, occasionally query selector
	 * @returns request
	 */
	message: (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "message");
		let payload,
			successFn = function (data) {
				new Toast(data.response.msg, data.response.type);
				if (data.response !== undefined && data.response.redirect) api.message("get", data.response.redirect);
			},
			title = {
				conversation: LANG.GET("menu.message_conversations"),
				register: LANG.GET("menu.message_register"),
			};

		switch (method) {
			case "get":
				successFn = function (data) {
					if (data.render) {
						api.update_header(title[request[1]]);
						const render = new Assemble(data.render);
						document.getElementById("main").replaceChildren(render.initializeSection());
						render.processAfterInsertion();
						if (request[2]) window.scrollTo(0, document.body.scrollHeight);
					}
					if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
					if (request[1] === "inbox" && _serviceWorker.worker)
						_serviceWorker.onMessage({
							unseen: 0,
						});
				};
				break;
			case "post":
				if (2 in request && request[2] && typeof request[2] === "object") {
					//passed formdata
					payload = request[2];
					delete request[2];
				} else payload = _.getInputs("[data-usecase=message]", true);
				break;
			case "delete":
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},

	/**
	 *                   _
	 *   ___ _ _ ___ ___| |_ ___ ___ ___
	 *  | . | | |  _|  _|   | .'|_ -| -_|
	 *  |  _|___|_| |___|_|_|__,|___|___|
	 *  |_|
	 * handles vendor and product management
	 * handles orders
	 *
	 * @param {string} method get|post|put|delete
	 * @param  {array} request api method, id / name / filter term, occasionally subrequest, occasionally message
	 * @returns request
	 */
	purchase: (method, ...request) => {
		request = [...request];
		if (["vendor", "product", "mdrsamplecheck", "incorporation", "pendingincorporations", "vendorinformation", "productinformation", "products_with_expiry_dates", "products_with_special_attention"].includes(request[0])) request.splice(0, 0, "consumables");
		else request.splice(0, 0, "order");

		let payload,
			successFn = function (data) {
				new Toast(data.response.msg, data.response.type);
				if (data.response.type !== "error") api.purchase("get", request[1], data.response.id);
			},
			title = {
				vendor: LANG.GET("menu.purchase_vendor"),
				product: LANG.GET("menu.purchase_product"),
				order: LANG.GET("menu.purchase_order"),
				prepared: LANG.GET("menu.purchase_prepared_orders"),
				approved: LANG.GET("menu.purchase_approved_orders"),
				pendingincorporations: LANG.GET("menu.purchase_incorporated_pending"),
				vendorinformation: LANG.GET("menu.purchase_vendor_information"),
				productinformation: LANG.GET("menu.purchase_product_information"),
				products_with_expiry_dates: LANG.GET("menu.purchase_products_with_expiry_dates"),
				products_with_special_attention: LANG.GET("menu.purchase_products_with_special_attention"),
			};
		if (request[2] === LANG.GET("consumables.edit_existing_vendors_new")) request.splice(2, 1);
		switch (method) {
			case "get":
				switch (request[1]) {
					case "productsearch":
						api.preventDataloss.monitor = false;
						successFn = function (data) {
							let list = document.querySelector("hr").previousElementSibling;
							if (list.previousElementSibling) list.remove();
							if (data.render.content) {
								const render = new Assemble(data.render);
								render.initializeSection("hr");
								render.processAfterInsertion();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							api.preventDataloss.monitor = request[4] === "editconsumables";
						};
						break;
					case "filter":
						successFn = function (data) {
							if (data.data) {
								const all = document.querySelectorAll("[data-filtered]");
								for (const order of all) {
									order.parentNode.style.display = data.data.includes(order.dataset.filtered) ? "block" : "none";
								}
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					case "incorporation":
						successFn = function (data) {
							if (data.render) {
								new Dialog({
									type: "input",
									header: LANG.GET("order.incorporation"),
									render: data.render.content,
									options: data.render.options,
								}).then((response) => {
									if (response) {
										_client.order.performIncorporation(response, data.render.productid);
									}
								});
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					case "mdrsamplecheck":
						successFn = function (data) {
							if (data.render) {
								new Dialog({
									type: "input",
									header: LANG.GET("order.sample_check"),
									render: data.render.content,
									options: data.render.options,
								}).then((response) => {
									if (response) {
										_client.order.performSampleCheck(response, data.render.productid);
									}
								});
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					default:
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
								if (request[1] === "approved") _client.order.filter();
								api.preventDataloss.start();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
				}
				break;
			case "post":
				switch (request[1]) {
					case "incorporation":
					case "mdrsamplecheck":
						payload = request[3]; // form data object passed by utility.js
						delete request[3];
						successFn = function (data) {
							new Toast(data.response.msg, data.response.type);
						};
						break;
					default:
						payload = _.getInputs("[data-usecase=purchase]", true);
				}
				break;
			case "put":
				if (["ordered", "received", "delivered", "archived", "disapproved", "cancellation", "return", "addinformation"].includes(request[3])) {
					if (typeof request[4] === "object") {
						payload = request[4];
						delete request[4];
					}
					successFn = function (data) {
						new Toast(data.response.msg, data.response.type);
					};
				}
				if (request[1] == "prepared") {
					successFn = function (data) {
						new Toast(data.response.msg, data.response.type);
						api.purchase("get", "prepared");
					};
				}
				if (request[1] !== "approved" && !payload) payload = _.getInputs("[data-usecase=purchase]", true); // exclude status updates
				break;
			case "delete":
				switch (request[1]) {
					case "mdrsamplecheck":
						successFn = function (data) {
							new Toast(data.response.msg, data.response.type);
						};
						break;
				}
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post" || method === "put");
	},

	/**
	 *                         _
	 *   ___ ___ ___ ___ ___ _| |
	 *  |  _| -_|  _| . |  _| . |
	 *  |_| |___|___|___|_| |___|
	 *
	 * handles records, displays record lists
	 * imports data from other records
	 *
	 * @param {string} method get|post
	 * @param  {array} request api method, occasional identifier to import from
	 * @returns request
	 */
	record: (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "record");
		let payload,
			successFn = function (data) {
				if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
				if (data.render !== undefined) {
					const options = {};
					options[LANG.GET("general.ok_button")] = false;
					new Dialog({
						type: "input",
						render: data.render,
						options: options,
					});
				}
			},
			title = {
				identifier: LANG.GET("menu.record_create_identifier"),
				forms: LANG.GET("menu.record_record"),
				records: LANG.GET("menu.record_summary"),
				record: LANG.GET("menu.record_summary"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "recordfilter":
					case "formfilter":
						api.preventDataloss.monitor = false;
						successFn = function (data) {
							if (data.data) {
								const all = document.querySelectorAll("[data-filtered]"),
									exceeding = document.querySelectorAll("[data-filtered_max]");
								for (const element of all) {
									if (data.filter === undefined || data.filter == "some") element.style.display = data.data.includes(element.dataset.filtered) ? "block" : "none";
									else element.style.display = data.data.includes(element.dataset.filtered) && ![...exceeding].includes(element) ? "block" : "none";
								}
							}
						};
						break;
					case "import":
						successFn = function (data) {
							if (data.data !== undefined) {
								let inputs = document.querySelectorAll("input, textarea, select");
								let inputname, groupname, files, a;
								for (const input of inputs) {
									inputname = input.name.replaceAll(" ", "_");
									if (input.type === "file") {
										if (Object.keys(data.data).includes(inputname.replace("[]", ""))) {
											files = data.data[inputname.replace("[]", "")].split(", ");
											for (const file of files) {
												a = document.createElement("a");
												a.href = file;
												a.target = "_blank";
												a.append(document.createTextNode(file));
												input.parentNode.insertBefore(a, input);
											}
											if (files) input.parentNode.insertBefore(document.createElement("br"), input);
										}
									} else if (input.type === "radio") {
										// nest to avoid overriding values of other radio elements
										input.checked = Object.keys(data.data).includes(inputname) && data.data[inputname] === input.value;
									} else if (input.type === "checkbox") {
										groupname = input.dataset.grouped.replaceAll(" ", "_");
										input.checked = Object.keys(data.data).includes(groupname) && data.data[groupname].split(", ").includes(input.name);
									} else {
										if (Object.keys(data.data).includes(inputname)) input.value = data.data[inputname];
									}
								}
							}
							if (data.response.msg !== undefined) {
								const options = {};
								options[LANG.GET("record.record_import_ok")] = false;
								options[LANG.GET("record.record_import_clear_identifier")] = {
									value: true,
									class: "reducedCTA",
								};

								new Dialog({
									type: "confirm",
									header: LANG.GET("assemble.compose_merge"),
									options: options,
									render: data.response.msg,
								}).then((response) => {
									if (response) document.querySelector("input[name^=IDENTIFY_BY_]").value = "";
								});
							}
						};
						payload = { IDENTIFY_BY_: request[2] };
						break;
					case "displayonly":
						// for linked forms within forms
						successFn = function (data) {
							if (data.render) {
								const options = {};
								options[LANG.GET("general.ok_button")] = false;
								new Dialog({ type: "input", header: data.title, render: data.render, options: options });
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						request[1] = "form";
						break;
					case "fullexport":
					case "simplifiedexport":
					case "formexport": // sorry. exports a form with records, not so paperless after all
					case "exportform": // exports the empty form as editable pdf
					case "matchbundles":
						//prevent default successFn
						break;
					default:
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] || data.title);
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
								api.preventDataloss.start();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
				}
				break;
			case "post":
				if (request[3]) {
					payload = request[3]; // form data object passed by utility.js
					delete request[3];
				} else payload = _.getInputs("[data-usecase=record]", true);
				break;
			case "put":
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},

	/**
	 *       _     _
	 *   ___|_|___| |_
	 *  |  _| |_ -| '_|
	 *  |_| |_|___|_,_|
	 *
	 * risk management
	 * displays form to edit risks or overview, according to permissions 
	 *
	 * @param {string} method get|post
	 * @param  {array} request api method
	 * @returns request
	 */
	risk: (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "risk");
		let payload,
			successFn = function (data) {
				new Toast(data.response.msg, data.response.type);
			},
			title = {
				risk: LANG.GET("menu.risk_management"),
			};
		switch (method) {
			case "get":
				switch (request[3]) {
					default:
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							api.preventDataloss.start();
						};
				}
				break;
			case "post":
			case "put":
				switch (request[1]) {
					default:
						payload = _.getInputs("[data-usecase=risk]", true);
				}
				break;
		}
		api.send(method, request, successFn, null, payload, method === "post" || method === "put");
	},

	/**
	 *   _           _   _                 _     _
	 *  | |_ ___ _ _| |_| |_ ___ _____ ___| |___| |_ ___
	 *  |  _| -_|_'_|  _|  _| -_|     | . | | .'|  _| -_|
	 *  |_| |___|_,_|_| |_| |___|_|_|_|  _|_|__,|_| |___|
	 *                                |_|
	 * manages text templates
	 * displays text template frontend either as body or within a modal
	 *
	 * @param {string} method get|post
	 * @param  {array} request api method, occasional modal destination
	 * @returns request
	 */
	texttemplate: (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "texttemplate");
		let payload,
			successFn = function (data) {
				new Toast(data.response.msg, data.response.type);
			},
			title = {
				chunk: LANG.GET("menu.texttemplate_chunks"),
				template: LANG.GET("menu.texttemplate_templates"),
				text: LANG.GET("menu.texttemplate_texts"),
			};
		switch (method) {
			case "get":
				switch (request[3]) {
					case "modal":
						successFn = function (data) {
							if (data.render) {
								new Dialog({ type: "input", header: LANG.GET("menu.texttemplate_texts"), render: data.render });
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							if (data.data !== undefined) _client.texttemplate.data = data.data;
							if (data.selected !== undefined && data.selected.length) {
								compose_helper.importTextTemplate(data.selected);
							}
						};
						break;
					default:
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							if (data.data !== undefined) _client.texttemplate.data = data.data;
							if (data.selected !== undefined && data.selected.length) {
								compose_helper.importTextTemplate(data.selected);
							}
							api.preventDataloss.start();
						};
				}
				break;
			case "post":
				switch (request[1]) {
					case "template":
						successFn = function (data) {
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						if (!(payload = compose_helper.composeNewTextTemplate())) return;
						break;
					default:
						payload = _.getInputs("[data-usecase=texttemplate]", true);
				}
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post" && request[1] !== "template");
	},

	/**
	 *   _           _
	 *  | |_ ___ ___| |
	 *  |  _| . | . | |
	 *  |_| |___|___|_|
	 *
	 * displays 2d code form and result passed by get query
	 * displays a generic 2d scanner
	 * displays stl viewer for files managed by filemanager and open sharepoint
	 *
	 * @param {string} method get
	 * @param  {array} request api method, occasionally passed values for 2d codes
	 * @returns request
	 */
	tool: (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "tool");
		let payload,
			successFn = function (data) {
				if (data.render) {
					api.update_header(title[request[1]]);
					const render = new Assemble(data.render);
					document.getElementById("main").replaceChildren(render.initializeSection());
					render.processAfterInsertion();
				}
				if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
			},
			title = {
				code: LANG.GET("menu.tools_digital_codes"),
				scanner: LANG.GET("menu.tools_scanner"),
				stlviewer: LANG.GET("menu.tools_stl_viewer"),
			};
		switch (method) {
			case "get":
				break;
			case "post":
				payload = _.getInputs("[data-usecase=tool_create_code]", true);
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post");
	},

	/**
	 *
	 *   _ _ ___ ___ ___
	 *  | | |_ -| -_|  _|
	 *  |___|___|___|_|
	 *
	 * user manager and display of profile
	 *
	 * @param {string} method get|post|put|delete
	 * @param  {array} request api method, name|id
	 * @returns request
	 */
	user: (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "user");
		let payload,
			successFn = function (data) {
				new Toast(data.response.msg, data.response.type);
				api.user("get", request[1], data.response.id);
			},
			title = {
				profile: LANG.GET("menu.application_user_profile"),
				user: LANG.GET("menu.application_user_manager"),
			};
		switch (method) {
			case "get":
				successFn = function (data) {
					if (data.render) {
						api.update_header(title[request[1]]);
						const render = new Assemble(data.render);
						document.getElementById("main").replaceChildren(render.initializeSection());
						render.processAfterInsertion();
					}
					if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
				};
				break;
			case "post":
				payload = _.getInputs("[data-usecase=user]", true);
				break;
			case "put":
				payload = _.getInputs("[data-usecase=user]", true);
				break;
			case "delete":
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload, method === "post" || method === "put");
	},
};

/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
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

import { Assemble, assemble_helper, Dialog, Toast } from "./assemble.js";
import { Compose, compose_helper } from "./compose.js";
import { Lang } from "../js/language.js";

export const api = {
	_settings: {
		user: {},
		config: {},
	},

	_lang: new Lang(),

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
		},
		stop: function () {
			document.removeEventListener("input", api.preventDataloss.event);
			api.preventDataloss.monitor = false;
		},
		proceedAnyway: async function (method) {
			if (api.preventDataloss.monitor && method.toUpperCase() === "GET") {
				const options = {};
				options[api._lang.GET("general.prevent_dataloss_cancel")] = false;
				options[api._lang.GET("general.prevent_dataloss_ok")] = {
					value: true,
					class: "reducedCTA",
				};
				return await new Dialog({
					type: "confirm",
					header: api._lang.GET("general.prevent_dataloss"),
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
	send: async (method, request, successFn = null, errorFn = null, payload = {}) => {
		if (!(await api.preventDataloss.proceedAnyway(method))) return false;
		api.preventDataloss.stop();
		api.loadindicator(true);
		if (api._settings.user && api._settings.user.fingerprint && ["post", "put"].includes(method)) {
			let sanitizedpayload = {};
			if (payload instanceof FormData) {
				sanitizedpayload = Object.fromEntries(payload);
				for (const [key, value] of Object.entries(sanitizedpayload)) {
					// remove file keys for being shifted to $_FILES within the stream
					// and quick sanitation of arrays; can't be handled by this object
					if ((value instanceof File && (method === "post" || (method === "put" && value.size))) || key.endsWith("[]")) {
						delete sanitizedpayload[key];
					}
					// unset '0' values that are not recognized by backend
					if (value == "0") sanitizedpayload[key] = "";
				}
			} else payload = new FormData();

			sanitizedpayload = JSON.stringify(sanitizedpayload)
				.replace(/\\r|\\n|\\t/g, "")
				.replaceAll(/[\W_]/g, ""); // harmonize cross browser
			const b = new Blob([sanitizedpayload], {
				type: "application/json",
			});
			//console.log(payload, sanitizedpayload, b.size);
			payload.append("_user_post_validation", await _.sha256(api._settings.user.fingerprint + b.size.toString()));
		}
		await _.api(method, "api/api.php/" + request.join("/"), payload, ["post", "put"].includes(method.toLowerCase()))
			.then(async (data) => {
				document.querySelector("header>div:nth-of-type(2)").style.display = data.status === 200 ? "none" : "block";
				if (data.status === 203) new Toast(api._lang.GET("general.service_worker_get_cache_fallback"), "info");
				if (data.status === 207) {
					new Toast(api._lang.GET("general.service_worker_post_cache_fallback"), "info");
					_serviceWorker.onPostCache();
					return;
				}
				api.session_timeout.init();
				if (successFn) await successFn(data.body);
			})
			.catch((error) => {
				// erroronline1.js _.api returns something like *Error: server responded 401: Unauthorized*
				// translate errorcode with languagefile translations
				// no altering library
				const date = new Date();
				console.trace(request, date.toUTCString(), error);
				const errorcode = error.message.match(/\d+/g);
				if (api._lang._USER["application"]["error_response"][errorcode]) error = api._lang._USER["application"]["error_response"][errorcode];

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
	 * ui feedback on occuring requests that are expected to take longer
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
		location.hash = "";
		//window.scrollTo({
		//	top: 0,
		//	behavior: "smooth",
		//});
	},

	/**
	 *                   _               _   _                   _
	 *   ___ ___ ___ ___|_|___ ___      | |_|_|_____ ___ ___ _ _| |_
	 *  |_ -| -_|_ -|_ -| | . |   |     |  _| |     | -_| . | | |  _|
	 *  |___|___|___|___|_|___|_|_|_____|_| |_|_|_|_|___|___|___|_|
	 *                            |_____|
	 *
	 * render the session timeout indicator in upper right corner
	 */
	session_timeout: {
		circle: null,
		init: function () {
			if (!("lifespan" in api._settings.config)) api._settings.config.lifespan = { idle: 0 };
			this.stop = new Date().getTime() + api._settings.config.lifespan.idle * 1000;
			if (api.session_timeout.interval) clearInterval(api.session_timeout.interval);
			api.session_timeout.interval = setInterval(function () {
				if (!("lifespan" in api._settings.config)) api._settings.config.lifespan = { idle: 0 };
				const remaining = api.session_timeout.stop - new Date().getTime();
				if (api._settings.config.lifespan.idle > 0 && remaining > 0) {
					document.querySelector("header>div:nth-of-type(3)").style.display = "none";
					api.session_timeout.render((100 * remaining) / (api._settings.config.lifespan.idle * 1000), remaining);
					return;
				}
				api.session_timeout.render(0);
				clearInterval(api.session_timeout.interval);
				clearInterval(_serviceWorker.notif.interval);
				_serviceWorker.notif.interval = null;
				document.querySelector("header>div:nth-of-type(3)").style.display = "block";
			}, 5000);
		},
		render: function (percent, remaining = 0) {
			if (!this.circle) this.circle = document.querySelector(".session-timeout__circle");
			percent = percent < 0 ? 0 : percent;
			if (percent < 0 || !this.circle) return;
			const circumference = this.circle.r.baseVal.value * 2 * Math.PI,
				offset = circumference - (percent / 100) * circumference;
			if (remaining / 1000 < 120) {
				if (!this.circle.classList.contains("warning")) new Toast(api._lang.GET("assemble.render.aria.timeout", { ":seconds": 120 }), "info", 120000);
				this.circle.classList.add("warning");
			} else this.circle.classList.remove("warning");
			this.circle.style.strokeDasharray = `${circumference} ${circumference}`;
			this.circle.style.strokeDashoffset = offset;
		},
		stop: null,
	},

	/**
	 *   ___ _ _ _
	 *  |  _|_| | |_ ___ ___
	 *  |  _| | |  _| -_|  _|
	 *  |_| |_|_|_| |___|_|
	 *
	 * @param {string} tag
	 * @returns string css display property
	 */
	filter: (tag) => {
		switch (tag) {
			case "div":
			case "article":
			case "a":
			case "header":
				return "block";
			case "label":
			case "span":
				return "initial";
			default:
				// input, span,
				return "inline-block";
		}
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
			case "info":
				successFn = function (data) {
					api.update_header(api._lang.GET("menu.application.info"));
					const render = new Assemble(data.render);
					document.getElementById("main").replaceChildren(render.initializeSection());
					render.processAfterInsertion();
				};
				break;
			case "language":
				successFn = async function (data) {
					api._lang._USER = data.data;
				};
				break;
			case "login":
				payload = _.getInputs("[data-usecase=login]", true);

				successFn = async function (data) {
					// import server side settings
					await api.application("get", "language");
					await api.application("get", "menu");
					api._settings.user = data.user || {};
					api._settings.config = data.config || {};

					if (data.user) _serviceWorker.register();
					else {
						clearInterval(_serviceWorker.notif.interval);
						_serviceWorker.notif.interval = null;
					}
					if (data.render && data.render.form) {
						const render = new Assemble(data.render);
						document.getElementById("main").replaceChildren(render.initializeSection());
						render.processAfterInsertion();

						// replace "please sign in" with user name for landing page
						let signin = api._lang.GET("menu.application.signin"),
							greeting = ", " + signin.charAt(0).toLowerCase() + signin.slice(1);
						api.update_header(
							api._lang.GET("general.welcome_header", {
								":user": greeting,
							})
						);
						return;
					}

					// update default language
					if (api._settings.config.application && api._settings.config.application.defaultlanguage) {
						document.querySelector("html").lang = api._settings.config.application.defaultlanguage;
					}

					// update application menu icon with user image
					if (api._settings.user.image) {
						let applicationLabel;
						while (!applicationLabel) {
							await _.sleep(50);
							applicationLabel = document.querySelector("[data-for=userMenu" + api._lang.GET("menu.application.header") + "]>label");
						}
						applicationLabel.style.maskImage = applicationLabel.style.webkitMaskImage = "none";
						applicationLabel.style.backgroundImage = "url('" + api._settings.user.image + "')";
						applicationLabel.style.backgroundSize = "cover";
						applicationLabel.style.borderRadius = "50%";
					}

					// override css properties with user settings
					if (api._settings.user.app_settings) {
						for (const [key, value] of Object.entries(api._settings.user.app_settings)) {
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
											if (stylesheet[i].conditionText === "only screen and (min-width: 64rem)") {
												stylesheet[i].media.mediaText = "only screen and (min-width: 0)";
											}
											if (stylesheet[i].conditionText === "only screen and (max-width: 64rem)") {
												stylesheet[i].media.mediaText = "only screen and (max-width: 0)";
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

					// set general titles to common elements
					document.querySelector("dialog#inputmodal").ariaLabel = document.querySelector("dialog#inputmodal2").ariaLabel = document.querySelector("dialog#modal").ariaLabel = api._lang.GET("assemble.render.aria.dialog");
					document.querySelector("dialog#toast").ariaLabel = api._lang.GET("assemble.render.aria.dialog_toast");

					// retrieve landing page
					api.application("get", "start");
				};
				break;
			case "menu":
				successFn = function (data) {
					// construct backend provided application menu
					assemble_helper.userMenu(data.render);
				};
				break;
			case "start":
				successFn = function (data) {
					// replace "please sign in" with user name for landing page
					// duplicate of login for selection of landing page from menu
					let signin = api._lang.GET("menu.application.signin"),
						greeting = ", " + signin.charAt(0).toLowerCase() + signin.slice(1);
					if (data.user) greeting = " " + data.user;
					api.update_header(
						api._lang.GET("general.welcome_header", {
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
							api.update_header(api._lang.GET("menu.application.manual_manager"));
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
							if (data.response.id) api.application("get", request[1], data.response.id);
						};
						payload = _.getInputs("[data-usecase=manual]", true);
						break;
				}
				break;
			default:
				return;
		}
		await api.send(method, request, successFn, null, payload);
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
					options[api._lang.GET("general.ok_button")] = false;
					new Dialog({
						type: "input",
						render: data.render,
						options: options,
					});
				}
			},
			title = {
				audit: api._lang.GET("menu.records.audit"),
				audittemplate: api._lang.GET("menu.records.audit_templates"),
				checks: api._lang.GET("menu.tools.regulatory"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "audit":
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]]);
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							api.preventDataloss.start();
						};
						break;
					case "audittemplate":
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]]);
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							if (data.selected && data.selected.length) compose_helper.importAuditTemplate(data.selected);
							api.preventDataloss.start();
						};
						break;
					case "export":
						break;
					case "import":
						switch (request[2]) {
							case "auditsummary":
								api.preventDataloss.stop();
								successFn = function (data) {
									if (data.data) document.getElementById("TemplateObjectives").value += "\n\n" + data.data;
									api.preventDataloss.start();
								};
								break;
						}
						break;
					default:
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] + (request[2] ? " - " + api._lang.GET("audit.checks_type." + request[2]) : ""));
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
				}
				break;
			case "post":
				if (3 in request && request[3] && request[3] instanceof FormData) {
					// passed formdata
					payload = request[3];
					delete request[3];
					successFn = function (data) {
						if (data.render) {
							api.update_header(title[request[1]] + (request[2] ? " - " + api._lang.GET("audit.checks_type." + request[2]) : ""));
							const render = new Assemble(data.render);
							document.getElementById("main").replaceChildren(render.initializeSection());
							render.processAfterInsertion();
						}
						if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
					};
				} else if (request[1] === "audittemplate") {
					if (!(payload = compose_helper.composeNewAuditTemplate())) return;
					successFn = function (data) {
						if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						if (data.id) api.audit("get", "audittemplate", null, data.id);
					};
				} else payload = _.getInputs("[data-usecase=audit]", true);
				break;
			case "put":
				if (4 in request && request[4] && request[4] instanceof FormData) {
					// passed formdata
					payload = request[4];
					delete request[4];
					successFn = function (data) {
						if (data.render) {
							api.update_header(title[request[1]] + (request[2] ? " - " + api._lang.GET("audit.checks_type." + request[2]) : ""));
							const render = new Assemble(data.render);
							document.getElementById("main").replaceChildren(render.initializeSection());
							render.processAfterInsertion();
						}
						if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
					};
				} else if (request[1] === "audittemplate") {
					if (!(payload = compose_helper.composeNewAuditTemplate())) return;
					successFn = function (data) {
						if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
					};
				} else payload = _.getInputs("[data-usecase=audit]", true);
				break;
		}
		api.send(method, request, successFn, null, payload);
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
				if (data.data) _serviceWorker.notif.calendar(data.data);
			},
			title = {
				schedule: api._lang.GET("menu.calendar.scheduling"),
				timesheet: api._lang.GET("menu.calendar.timesheet"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "monthlyTimesheets":
						successFn = function (data) {
							if (data.render !== undefined) {
								const options = {};
								options[api._lang.GET("general.ok_button")] = false;
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
		api.send(method, request, successFn, null, payload);
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
							{ type: "textsection", content: data.log.join("\n") },
							{ type: "links", content: data.links },
						],
					};
					new Dialog(dialog);
				}
			},
			title = {
				rule: api._lang.GET("menu.tools.csvfilter_filter_manager"),
				filter: api._lang.GET("menu.tools.csvfilter_filter"),
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
		api.send(method, request, successFn, null, payload);
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
				files: api._lang.GET("menu.files.files"),
				bundle: api._lang.GET("menu.files.bundles"),
				sharepoint: api._lang.GET("menu.files.sharepoint"),
				filemanager: api._lang.GET("menu.files.file_manager"),
				bundlemanager: api._lang.GET("menu.files.bundle_manager"),
				externalfilemanager: api._lang.GET("menu.files.external_file_manager"),
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
										file.parentNode.style.display = data.data.includes(file.dataset.filtered) ? api.filter(file.parentNode.localName) : "none";
									} else file.style.display = data.data.includes(file.dataset.filtered) ? api.filter(file.localName) : "none";
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
									list.parentNode.style.display = data.data.includes(list.dataset.filtered) ? api.filter(list.parentNode.localName) : "none";
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
		api.send(method, request, successFn, null, payload);
	},

	/**
	 *     _                           _
	 *   _| |___ ___ _ _ _____ ___ ___| |_
	 *  | . | . |  _| | |     | -_|   |  _|
	 *  |___|___|___|___|_|_|_|___|_|_|_|
	 *
	 * document component and document management with creation, editing and approval
	 *
	 * @param {string} method get|post|put|delete
	 * @param  {array} request api method, name|id
	 * @returns request
	 */
	document: (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "document");
		let successFn = function (data) {
				if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
				if (data.render !== undefined) {
					const options = {};
					options[api._lang.GET("general.ok_button")] = false;
					new Dialog({
						type: "input",
						render: data.render,
						options: options,
					});
				}
			},
			payload,
			title = {
				component_editor: api._lang.GET("menu.records.documents_manage_components"),
				document_editor: api._lang.GET("menu.records.documents_manage_documents"),
				approval: api._lang.GET("menu.records.documents_manage_approval"),
				bundle: api._lang.GET("menu.records.documents_manage_bundles"),
				bundles: api._lang.GET("menu.records.record_bundles"),
				documents: api._lang.GET("menu.records.record_record"),
			},
			composedComponent;
		switch (method) {
			case "get":
				switch (request[1]) {
					case "component":
						successFn = function (data) {
							if (data.render) {
								data.render.content.name = data.render.name;
								if (data.render.content) compose_helper.importDocument([data.render.content]);
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
					case "document_editor":
						compose_helper.componentIdentify = 0;
						compose_helper.componentSignature = 0;
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								const render = new Compose(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
								if (data.render.components) compose_helper.importDocument(data.render.components);
								api.preventDataloss.start();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					case "documentfilter":
						api.preventDataloss.monitor = false;
						successFn = function (data) {
							if (data.data) {
								const all = document.querySelectorAll("[data-filtered]"),
									exceeding = document.querySelectorAll("[data-filtered_max]");
								for (const element of all) {
									if (data.filter === undefined || data.filter == "some") element.style.display = data.data.includes(element.dataset.filtered) ? api.filter(element.localName) : "none";
									else element.style.display = data.data.includes(element.dataset.filtered) && ![...exceeding].includes(element) ? api.filter(element.localName) : "none";
								}
							}
						};
						break;
					default:
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
				}
				break;
			case "put":
				switch (request[1]) {
					case "approval":
						successFn = function (data) {
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							if (data.response !== undefined && data.response.reload !== undefined) api.document("get", data.response.reload);
							if (data.data) _serviceWorker.notif.records(data.data);
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
							if (data.response !== undefined && data.response.reload !== undefined) api.document("get", data.response.reload);
						};
						composedComponent = compose_helper.composeNewComponent();
						if (!composedComponent) return;
						compose_helper.addComponentStructureToComponentForm(composedComponent);
						payload = _.getInputs("[data-usecase=component_editor_form]", true);
						break;
					case "document":
						successFn = function (data) {
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							if (data.response !== undefined && data.response.reload !== undefined) api.document("get", data.response.reload);
						};
						if (!(payload = compose_helper.composeNewDocument())) return;
						break;
					case "bundle":
						successFn = function (data) {
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						payload = _.getInputs("[data-usecase=bundle]", true);
						break;
					case "export":
						payload = _.getInputs("[data-usecase=record]", true);
						break;
				}
				if (request[3]) {
					payload = request[3]; // form data object passed by utility.js
					delete request[3];
				}
				break;
			case "delete":
				successFn = function (data) {
					if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
					if (data.response !== undefined && data.response.reload !== undefined) api.document("get", data.response.reload);
				};
				break;
		}
		api.send(method, request, successFn, null, payload);
	},

	/**
	 *
	 *   _____ ___ ___ ___ _ _ ___ ___
	 *  |     | -_| .'|_ -| | |  _| -_|
	 *  |_|_|_|___|__,|___|___|_| |___|
	 *
	 * handles proposals and measures
	 *
	 * @param {string} method get|post|delete
	 * @param  {array} request api method, measure id, vote / message form data
	 * @returns request
	 */
	measure: (method, ...request) => {
		request = [...request];
		request.splice(0, 0, "measure");
		let payload,
			successFn = function (data) {
				new Toast(data.response.msg, data.response.type);
			},
			title = {
				measure: api._lang.GET("menu.communication.measure"),
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
			case "put":
			case "post":
				if (3 in request && request[3] && request[3] instanceof FormData) {
					// passed formdata
					payload = request[3];
					delete request[3];
				}
				break;
			case "delete":
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload);
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
				conversation: api._lang.GET("menu.communication.conversations"),
				register: api._lang.GET("menu.communication.register"),
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
					if (data.data) _serviceWorker.notif.communication(data.data);
					if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
					if (request[1] === "inbox" && _serviceWorker.worker)
						_serviceWorker.onMessage({
							unseen: 0,
						});
				};
				break;
			case "post":
				if (2 in request && request[2] && request[2] instanceof FormData) {
					// passed formdata
					payload = request[2];
					delete request[2];
				} else payload = _.getInputs("[data-usecase=message]", true);
				break;
			case "delete":
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload);
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
		if (["vendor", "product", "mdrsamplecheck", "incorporation", "pendingincorporations", "exportpricelist", "productsearch"].includes(request[0])) request.splice(0, 0, "consumables");
		else request.splice(0, 0, "order");

		let payload,
			successFn = function (data) {
				new Toast(data.response.msg, data.response.type);
				if (data.response.type !== "error") api.purchase("get", request[1], data.response.id);
				if (data.data) _serviceWorker.notif.consumables(data.data);
			},
			title = {
				vendor: api._lang.GET("menu.purchase.vendor"),
				product: api._lang.GET("menu.purchase.product"),
				order: api._lang.GET("menu.purchase.order"),
				prepared: api._lang.GET("menu.purchase.prepared_orders"),
				approved: api._lang.GET("menu.purchase.approved_orders"),
				pendingincorporations: api._lang.GET("menu.purchase.incorporated_pending"),
			};
		if (request[2] === api._lang.GET("consumables.vendor.edit_existing_vendors_new")) request.splice(2, 1);
		switch (method) {
			case "get":
				switch (request[1]) {
					case "productsearch":
						switch (request[4]) {
							case "productselection": //coming from assemble.js
								successFn = function (data) {
									let hr = document.querySelector("#inputmodal form article hr");
									let sibling = hr.nextSibling,
										deletesibling;
									if (sibling) {
										do {
											deletesibling = sibling;
											sibling = sibling.nextSibling;
											deletesibling.remove();
										} while (sibling);
									}
									if (data.render.content) {
										const render = new Assemble(data.render);
										render.initializeSection(null, hr);
										render.processAfterInsertion();
									}
									if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
								};
								break;
							default:
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
						}
						break;
					case "filter":
						successFn = function (data) {
							if (data.data) {
								const all = document.querySelectorAll("[data-filtered]");
								for (const order of all) {
									order.parentNode.parentNode.style.display = data.data.includes(order.dataset.filtered) ? api.filter(order.parentNode.parentNode.localName) : "none";
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
									header: api._lang.GET("order.incorporation.incorporation"),
									render: data.render.content,
									options: data.render.options,
								}).then((response) => {
									let submission = _client.application.dialogToFormdata(response);
									if (submission) api.purchase("post", "incorporation", data.render.productid, submission);
									else new Toast(api._lang.GET("order.incorporation.failure"), "error");
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
									header: api._lang.GET("order.sample_check.sample_check"),
									render: data.render.content,
									options: data.render.options,
								}).then((response) => {
									let submission = _client.application.dialogToFormdata(response);
									if (submission) api.purchase("post", "mdrsamplecheck", data.render.productid, submission);
									else new Toast(api._lang.GET("order.incorporation.failure"), "error");
								});
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					case "product":
						if (request[2] && typeof request[2] !== "number") {
							//pass article info as query paramaters for adding unknown articles to database from orders
							payload = JSON.parse(request[2]);
							delete request[2];
						}
					// no break intentional
					default:
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
								api.preventDataloss.start();
							}
							if (request[1] === "approved" && data.data) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								_client.order.approved(data.data);
								_client.order.filter();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							if (data.links !== undefined) {
								const dialog = {
									type: "input",
									render: [{ type: "links", content: data.links }],
								};
								new Dialog(dialog);
							}
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
				if (["ordered", "partially_received", "received", "partially_delivered", "delivered", "archived", "disapproved", "cancellation", "return", "addinformation"].includes(request[3])) {
					if (request[4] instanceof FormData) {
						payload = request[4];
						delete request[4];
					}
					successFn = function (data) {
						new Toast(data.response.msg, data.response.type);
						if (data.data) _serviceWorker.notif.consumables(data.data);
					};
				}
				if (request[1] == "prepared") {
					successFn = function (data) {
						new Toast(data.response.msg, data.response.type);
						if (data.data) _serviceWorker.notif.consumables(data.data);
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
		api.send(method, request, successFn, null, payload);
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
					options[api._lang.GET("general.ok_button")] = false;
					new Dialog({
						type: "input",
						render: data.render,
						options: options,
					});
				}
			},
			title = {
				identifier: api._lang.GET("menu.records.record_create_identifier"),
				record: api._lang.GET("menu.records.record_summary"),
				records: api._lang.GET("menu.records.record_summary"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
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
										input.checked = Object.keys(data.data).includes(groupname) && data.data[groupname].split(" | ").includes(input.name);
									} else {
										if (Object.keys(data.data).includes(inputname)) input.value = data.data[inputname];
										// append multiple text-/number inputs / selects / productselections that have been added dynamically
										let dynamicMultiples = Object.keys(data.data)
											.filter((name) => {
												return name.match(new RegExp(String.raw`${inputname}\(\d+\)$`, "g"));
											})
											.sort();
										if (dynamicMultiples.length) {
											let type = input.previousSibling.dataset.type,
												element = input.nextSibling, // label
												clone = { type: type, attributes: { name: null } },
												content = {};
											if (input.attributes.multiple) clone.attributes.multiple = true;
											if (input.attributes.required) clone.attributes.required = true;
											if (input.attributes["data-loss"]) clone.attributes["data-loss"] = input.attributes["data-loss"].value;
											if (input.attributes.title) clone.attributes.title = input.attributes.title.value;
											switch (type) {
												case "select":
													for (const opt of input.children) {
														if (opt.localName === "optgroup") {
															for (const option of opt.children) {
																content[option.innerHTML] = [];
															}
														} else content[opt.innerHTML] = [];
													}
													break;
												default:
													clone.attributes.value = null;
											}
											dynamicMultiples.forEach((name) => {
												if (element.nextSibling && element.nextSibling.classList.contains("hint")) element = element.nextSibling;
												clone.attributes.name = name.replaceAll("_", " ");
												clone.attributes.value = data.data[name];
												switch (type) {
													case "select":
														Object.keys(content).forEach((key) => {
															if (key === data.data[name]) content[key].selected = true;
															else delete content[key].selected;
														});
														clone.content = content;
														break;
													case "productselection":
													case "scanner":
														element = element.nextSibling; // button
														break;
												}
												if (!document.getElementsByName(name.replaceAll("_", " "))[0]) new Assemble({ content: [[clone]], composer: "elementClone" }).initializeSection(null, element);
												element = document.getElementsByName(name.replaceAll("_", " "))[0].nextSibling; // label
											});
										}
									}
								}
							}
							if (data.response.msg !== undefined) {
								const options = {};
								options[api._lang.GET("record.import_ok")] = false;
								options[api._lang.GET("record.import_clear_identifier")] = {
									value: true,
									class: "reducedCTA",
								};

								new Dialog({
									type: "confirm",
									header: api._lang.GET("assemble.render.merge"),
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
						// for linked documents within documents
						successFn = function (data) {
							if (data.render) {
								const options = {};
								options[api._lang.GET("general.ok_button")] = false;
								new Dialog({ type: "input", header: data.title, render: data.render, options: options });
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						request[1] = "document";
						break;
					case "fullexport":
					case "simplifiedexport":
					case "documentexport": // sorry. exports a document with records, not so paperless after all
					case "simplifieddocumentexport": // sorry. exports a document with records, not so paperless after all
					case "matchbundles":
						//prevent default successFn
						break;
					case "records":
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] || data.title);
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
								_client.record.casestatefilter(api._settings.user.app_settings.primaryRecordState);
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					default:
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] || data.title);
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
		api.send(method, request, successFn, null, payload);
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
				risk: api._lang.GET("menu.records.risk_management"),
				search: api._lang.GET("risk.search"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "search":
						if (!request[2]) {
							api.risk("get", "risk");
							return;
						}
						successFn = function (data) {
							let list = document.querySelector("hr").previousElementSibling;
							if (list.previousElementSibling) list.remove();
							if (data.render.content) {
								const render = new Assemble(data.render);
								render.initializeSection("hr");
								render.processAfterInsertion();
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
		api.send(method, request, successFn, null, payload);
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
				chunk: api._lang.GET("menu.communication.texttemplate_chunks"),
				template: api._lang.GET("menu.communication.texttemplate_templates"),
				text: api._lang.GET("menu.communication.texttemplate_texts"),
			};
		switch (method) {
			case "get":
				switch (request[3]) {
					case "modal":
						successFn = function (data) {
							if (data.render) {
								new Dialog({ type: "input", header: api._lang.GET("menu.communication.texttemplate_texts"), render: data.render });
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
		api.send(method, request, successFn, null, payload);
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
				code: api._lang.GET("menu.tools.digital_codes"),
				calculator: api._lang.GET("menu.tools.calculator"),
				scanner: api._lang.GET("menu.tools.scanner"),
				stlviewer: api._lang.GET("menu.tools.stl_viewer"),
				image: api._lang.GET("menu.tools.image"),
			};
		switch (method) {
			case "get":
				break;
			case "post":
				switch (request[1]) {
					case "calculator":
						payload = _.getInputs("[data-usecase=tool_calculator]", true);
						break;
					case "code":
						payload = _.getInputs("[data-usecase=tool_create_code]", true);
						break;
					case "image":
						payload = _.getInputs("[data-usecase=tool_image]", true);
						break;
				}
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload);
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
				if (data.response.id) api.user("get", request[1], data.response.id);
			},
			title = {
				profile: api._lang.GET("menu.application.user_profile"),
				user: api._lang.GET("menu.application.user_manager"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "token":
						successFn = function (data) {
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							if (data.render !== undefined) {
								const options = {};
								options[api._lang.GET("general.ok_button")] = false;
								new Dialog({
									type: "input",
									render: data.render,
									options: options,
								});
							}
						};
						break;
					default:
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]]);
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
				}
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
		api.send(method, request, successFn, null, payload);
	},
};

/**
 * [CARO - Cloud Assisted Records and Operations](https://github.com/erroronline1/caro)  
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.  
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.  
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.  
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

import { Assemble, Dialog, Toast } from "./assemble.js";
import { Compose } from "./compose.js";
import { Lang } from "../js/language.js";

export const api = {
	// passed from backend
	_settings: {
		user: {},
		config: {},
		session: {
			elementId: 0,
			signatureCanvas: null,
			textareaAutocompleteIndex: null,
			textareaAutocompleteSwipe: null,
			orderTilesGroupBy: "commission",
			toasttimeout: {},
		},
	},

	// instatiate a broadly usable language class
	_lang: new Lang(),

	// to store requests that need to be re-requested on (re)authentication
	_unauthorizedRequest: {},

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
			document.addEventListener("change", api.preventDataloss.event);
		},
		stop: function () {
			document.removeEventListener("input", api.preventDataloss.event);
			document.removeEventListener("change", api.preventDataloss.event);
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

		// ensure passed parameters are encoded e.g. to enable #-characters within a passed api path-parameter
		request = request.map((i) => encodeURIComponent(i).replace("+", "%2B"));

		// default reset masonry breakpoint if former call (current history has been set by recent api call) had prevented this
		// currently masonry is counterproductive for conversations
		if (api._settings.user.app_settings && api._settings.user.app_settings.masonry) await window.Masonry.breakpoints(true);

		if (api._settings.user && api._settings.user.fingerprint && ["post", "put"].includes(method)) {
			let sanitizedpayload = {};
			if (payload instanceof FormData) {
				sanitizedpayload = {};
				for (const [key, value] of Object.entries(Object.fromEntries(payload))) {
					// sanitation of arrays and file inputs; synchronization with backend checksum not possible
					if (key.endsWith("[]") || (value instanceof File && value.name)) {
						continue;
					}
					// unset '0' values that are not recognized by backend
					if (value == "0") sanitizedpayload[key] = "";
					else sanitizedpayload[key] = value;
				}
			} else payload = new FormData();

			sanitizedpayload = JSON.stringify(sanitizedpayload)
				.replace(/\\r|\\n|\\t/g, "")
				.replaceAll(/[\W_]/g, ""); // harmonize cross browser
			const b = new Blob([sanitizedpayload], {
				type: "application/json",
			});
			//_client.application.debug(payload, sanitizedpayload, b.size);
			payload.append("_user_post_validation", await _.sha256(api._settings.user.fingerprint + b.size.toString()));
		}
		await _.api(method, "api/api.php/" + request.join("/"), payload, [200, 203, 207, 511])
			.then(async (data) => {
				if (data.body && (data.body.user || data.body.config || data.body.language)) {
					if (data.body.user) api._settings.user = data.body.user;
					if (data.body.config) api._settings.config = data.body.config;
					if (data.body.language) api._lang._USER = data.body.language;
				}

				if (data.error) {
					const date = new Date();
					let error;
					_client.application.debug(request, date.toUTCString(), data.error);
					const errorcode = data.error.message.match(/\d+/g);
					if (api._lang._USER["application"] && api._lang._USER["application"]["error_response"][errorcode]) error = api._lang._USER["application"]["error_response"][errorcode];

					if (errorFn != null) errorFn(data.error);
					new Toast(error, "error");
					return;
				}

				document.querySelector("header>div:nth-of-type(2)").style.display = [200, 511].includes(data.status) ? "none" : "block";
				if (data.status === 203) new Toast(api._lang.GET("general.service_worker_get_cache_fallback"), "info");
				if (data.status === 207) {
					new Toast(api._lang.GET("general.service_worker_post_cache_fallback"), "info");
					_serviceWorker.onPostCache();
					return;
				}
				if (data.status === 511) {
					// session timeout
					api.loadindicator(false);
					clearInterval(_serviceWorker.notif.interval);
					_serviceWorker.notif.interval = null;
					if (JSON.stringify(request) !== JSON.stringify(["application", "authentify"])) api._unauthorizedRequest = { method: method, request: request };
					const options = {};
					options[api._lang.GET("general.ok_button")] = true;
					options[api._lang.GET("application.navigation.signout_user", { ":name": api._settings.user.name || "" })] = {
						onclick: "api.application('delete', 'authentify'); api.application('get', 'start')",
						class: "reducedCTA",
						value: false,
						type: "button",
					};
					await new Dialog(
						{
							type: "input",
							render: data.body.render,
							options: options,
						},
						"FormData"
					).then((response) => {
						api.application("post", "authentify", response);
					});
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
				_client.application.debug(request, date.toUTCString(), error);
				const errorcode = error.message.match(/\d+/g);
				//if (api._lang._USER["application"]["error_response"][errorcode]) error = api._lang._USER["application"]["error_response"][errorcode];

				if (errorFn != null) errorFn(error);
				//new Toast(error, "error");
				// safari debugging
				new Dialog({ type: "confirm", render: error + JSON.stringify([method, request]) });
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
		window.scrollTo({
			top: 0,
			behavior: "smooth",
		});
		document.querySelector("#menustart").focus();
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
		events: null,
		event_reset: function () {
			api.session_timeout.events++;
			if (!(api.session_timeout.events % 5)) api.session_timeout.reset();
		},
		init: function () {
			new Toast(null, null, null, "sessionwarning");
			this.stop = new Date().getTime() + (api._settings.config.lifespan ? api._settings.config.lifespan.session.idle || 0 : 0) * 1000;
			this.events = null;
			// session timeout event counter and resetter, with binded callback only attached once
			const events = ["mousedown", "mousemove", "keydown", "scroll", "touchstart", "pointerdown"];
			events.forEach(function (name) {
				document.addEventListener(name, api.session_timeout.event_reset, true);
			});
			this.reset();
		},
		render: function (percent, remaining = 0) {
			if (!this.circle) this.circle = document.querySelector(".session-timeout__circle");
			percent = percent < 0 ? 0 : percent;
			if (percent < 0 || !this.circle) return;
			const circumference = this.circle.r.baseVal.value * 2 * Math.PI,
				offset = circumference - (percent / 100) * circumference;
			if (remaining && remaining / 1000 < 120) {
				if (!this.circle.classList.contains("warning")) new Toast(api._lang.GET("assemble.render.aria.timeout_warning", { ":seconds": 120 }), "error", 60000, "sessionwarning");
				this.circle.classList.add("warning");
			} else this.circle.classList.remove("warning");
			this.circle.style.strokeDasharray = `${circumference} ${circumference}`;
			this.circle.style.strokeDashoffset = offset;
		},
		reset: function () {
			if (api.session_timeout.stop - new Date().getTime() < 0) {
				// remove session timeout event counter and resetter to avoid errors during fuzzy transformation to backend timeout
				const events = ["mousedown", "mousemove", "keydown", "scroll", "touchstart", "pointerdown"];
				events.forEach(function (name) {
					document.removeEventListener(name, api.session_timeout.event_reset, true);
				});
				return; // timeout has happended anyway, don't bother
			}
			if (api.session_timeout.interval) clearInterval(api.session_timeout.interval);
			this.stop_visual = new Date().getTime() + (api._settings.config.lifespan ? api._settings.config.lifespan.session.idle || 0 : 0) * 1000; // on event resets (see initializeCARO.js) the indicator will be reset
			api.session_timeout.interval = setInterval(function () {
				if (!("lifespan" in api._settings.config)) api._settings.config.lifespan = { session: { idle: 0 } };
				const remaining = api.session_timeout.stop - new Date().getTime(),
					remaining_visual = api.session_timeout.stop_visual - new Date().getTime();
				if (api._settings.config.lifespan.session.idle > 0 && remaining < 10000 && api.session_timeout.events > 2) {
					// if some events have been counted a small backend request prolongs the session approximately ten seconds before real timeout
					api.session_timeout.events = null;
					new Toast(null, null, null, "sessionwarning");
					api.application("get", "authentify");
					return;
				}
				if (api._settings.config.lifespan.session.idle > 0 && remaining_visual > 0) {
					// render the indicator as long as there is time left
					document.querySelector("header>div:nth-of-type(3)").style.display = "none";
					api.session_timeout.render((100 * remaining_visual) / (api._settings.config.lifespan.session.idle * 1000), remaining_visual);
					return;
				}
				// session has been timed out, display message and clear intervals
				api.session_timeout.render(0);
				if (Object.keys(api._settings.user).length) new Toast(api._lang.GET("assemble.render.aria.timeout"), "error", 1800000, "sessionwarning");
				clearInterval(api.session_timeout.interval);
				clearInterval(_serviceWorker.notif.interval);
				_serviceWorker.notif.interval = null;
				document.querySelector("header>div:nth-of-type(3)").style.display = "block";
			}, 5000);
		},
		stop: null,
		stop_visual: null,
	},

	/**
	 *   _   _     _
	 *  | |_|_|___| |_ ___ ___ _ _
	 *  |   | |_ -|  _| . |  _| | |
	 *  |_|_|_|___|_| |___|_| |_  |
	 *                        |___|
	 * stores and recalls last get requests.
	 */
	history: {
		currentStep: 1,
		/**
		 *
		 * @returns event of altering availability (color or visibility) of navigation buttons depending on reaching either end of history
		 */
		buttoncolor: function () {
			if (!document.querySelector("nav>button:last-of-type")) return; // onload return because not yet rendered
			// "disable" back
			if (this.currentStep < 2) document.querySelector("nav>button:last-of-type").classList.add("inactive");
			else document.querySelector("nav>button:last-of-type").classList.remove("inactive");
			// "disable" forth
			let history = JSON.parse(sessionStorage.getItem("history"));
			if (this.currentStep === history.length) document.querySelector("nav>button:first-of-type").classList.add("inactive");
			else document.querySelector("nav>button:first-of-type").classList.remove("inactive");
		},
		/**
		 * navigates through the history
		 * @param {string} dir
		 * @returns event of calling the request according to current history position
		 */
		go: function (dir) {
			let history = JSON.parse(sessionStorage.getItem("history")),
				request;
			if (!history) return; // history is not written yet!!!
			if (dir === "back") this.currentStep = ++this.currentStep <= history.length ? this.currentStep : history.length;
			else this.currentStep = --this.currentStep > 0 ? this.currentStep : 1;
			if ((request = history[history.length - this.currentStep])) {
				api[request.shift()]("get", ...request);
			}
			this.buttoncolor();
		},
		/**
		 * reset history
		 */
		reset: function () {
			this.currentStep = 1;
			sessionStorage.removeItem("history");
		},
		/**
		 * updates history to sessionStorage
		 * @param {array} request
		 * @returns none
		 */
		write: function (request) {
			let history = JSON.parse(sessionStorage.getItem("history"));
			/**
			 * compares two arrays for similarity
			 * @param {array} a1
			 * @param {array} a2
			 * @returns boolean
			 */
			function areDifferent(a1, a2) {
				if (typeof a1 === "undefined") return true;
				if (JSON.stringify(a1) !== JSON.stringify(a2)) return true;
				return false;
			}
			if (!history) {
				// history is not written yet!!!
				sessionStorage.setItem("history", JSON.stringify([request]));
				return;
			}
			if (areDifferent(history[history.length - this.currentStep], request)) {
				history.splice(history.length - (this.currentStep - 1));
				history.push(request);
				sessionStorage.setItem("history", JSON.stringify(history));
				this.currentStep = 1;
				this.buttoncolor();
			}
		},
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
		if (method === "get" && !["menu", "authentify"].includes(request[0])) api.history.write(["application", ...request]);

		request.splice(0, 0, "application");
		let successFn, payload;
		switch (request[1]) {
			case "authentify":
				switch (method) {
					case "delete":
						successFn = async function (data) {
							api._settings.user = data.user || {};
							api._settings.config = data.config || {};
							document.querySelector("body>header>h1").innerHTML = document.getElementById("main").innerHTML = document.querySelector("body>nav").innerHTML = "";
							api.history.reset();
							api.application("get", "start");
						};
						break;
					case "post":
						successFn = function (data) {
							if (data.user) api._settings.user = data.user;
							if (data.config) api._settings.config = data.config;

							// sometimes an error occures, try to show what's happening
							try {
								if (data.user) {
									// reset notif interval
									_serviceWorker.notif.interval = setInterval(() => {
										_serviceWorker.postMessage("getnotifications");
									}, _serviceWorker.notif.interval_duration);

									if (api._unauthorizedRequest.request && api._unauthorizedRequest.request.length && JSON.stringify(api._unauthorizedRequest.request) !== JSON.stringify(["application", "authentify"])) {
										// resend last request
										const call = api._unauthorizedRequest.request.shift();
										if (api.hasOwnProperty(call)) api[call](api._unauthorizedRequest.method, ...api._unauthorizedRequest.request);
									}
								}
							} catch (error) {
								_client.application.debug(error, api._unauthorizedRequest);
							}
						};
						payload = request.pop();
				}
				break;
			case "about":
				successFn = function (data) {
					api.update_header(api._lang.GET("application.navigation.about"));
					const render = new Assemble(data.render);
					document.getElementById("main").replaceChildren(render.initializeSection());
					render.processAfterInsertion();
				};
				break;
			case "menu":
				successFn = async function (data) {
					// construct backend provided application menu
					if (!data.render) return;

					function skip(text, href, id = null) {
						const ariaSkip = document.createElement("a");
						ariaSkip.append(document.createTextNode(text));
						ariaSkip.href = href;
						if (id) ariaSkip.id = id;
						return ariaSkip;
					}
					const menu = document.querySelector("nav"),
						elements = [],
						icons = {};
					elements.push(skip(api._lang.GET("application.navigation.ariaSkipToMain"), "#main", "menustart"));

					// set up icons css property
					icons[api._lang.GET("application.navigation.header")] = "url('./media/bars.svg')";
					icons[api._lang.GET("message.navigation.header")] = "url('./media/comment.svg')";
					icons[api._lang.GET("record.navigation.header")] = "url('./media/file-signature.svg')";
					icons[api._lang.GET("calendar.navigation.header")] = "url('./media/calendar-alt.svg')";
					icons[api._lang.GET("consumables.navigation.header")] = "url('./media/shopping-bag.svg')";
					icons[api._lang.GET("file.navigation.header")] = "url('./media/folders.svg')";
					icons[api._lang.GET("tool.navigation.header")] = "url('./media/tools.svg')";

					let label, input, div, button, span;

					// back nav
					button = document.createElement("button");
					button.type = "button";
					button.title = api._lang.GET("general.history.back");
					button.classList.add("inactive");
					button.onclick = () => {
						api.history.go("back");
					};
					button.style.maskImage = button.style.webkitMaskImage = "url('./media/angle-left.svg')";
					elements.push(button);

					// iterate over main categories
					let group,
						items,
						menuentries = Object.keys(data.render);
					for (let i = 0; i < menuentries.length; i++) {
						group = menuentries[i];
						items = data.render[menuentries[i]];
						label = document.createElement("label");

						// set up label and notification element
						label.htmlFor = "userMenu" + group;
						label.setAttribute("data-notification", 0);
						label.title = group;
						div = document.createElement("div");
						div.style.maskImage = div.style.webkitMaskImage = icons[group];
						div.setAttribute("data-for", "userMenu" + group.replace(" ", "_"));
						label.append(div);

						// set up radio input for css checked condition
						input = document.createElement("input");
						input.type = "radio";
						input.name = "userMenu";
						input.id = "userMenu" + group;
						// accessibility settings
						input.tabIndex = -1;
						input.setAttribute("inert", true);
						input.title = group;

						// set up div containing subsets of category
						div = document.createElement("div");
						div.classList.add("options");
						div.role = "menu";
						span = document.createElement("span");
						span.append(document.createTextNode(group));
						div.append(span);
						div.style.maxHeight = (Object.entries(items).length + 1) * 4 + "em";

						// iterate over subset
						for (const [description, attributes] of Object.entries(items)) {
							// create button to access subsets action
							if ("onclick" in attributes) {
								button = document.createElement("button");
								for (const [attribute, value] of Object.entries(attributes)) {
									button.setAttribute(attribute, value);
								}
								button.type = "button";
								button.classList.add("discreetButton");
								button.setAttribute("data-for", "userMenuItem" + description.replace(" ", "_"));
								button.setAttribute("data-notification", 0);
								button.appendChild(document.createTextNode(description));
								button.role = "menuitem";
								div.append(button);
							}
							// create description element
							else {
								span = document.createElement("span");
								span.append(document.createTextNode(description));
								div.append(span);
							}
						}

						elements.push(label);
						elements.push(skip(api._lang.GET("application.navigation.ariaSkipToMain"), "#main"));

						if (menuentries[i + 1]) {
							elements.push(skip(api._lang.GET("application.navigation.ariaSkipMenuOption", { ":option": menuentries[i + 1] }), "#userMenu" + menuentries[i + 1]));
						}
						elements.push(input);
						elements.push(div); // div must come immidiately after input
					}

					// forth nav
					button = document.createElement("button");
					button.type = "button";
					button.title = api._lang.GET("general.history.forth");
					button.classList.add("inactive");
					button.onclick = () => {
						api.history.go("forth");
					};
					button.style.maskImage = button.style.webkitMaskImage = "url('./media/angle-right.svg')";
					elements.push(button);
					elements.push(skip(api._lang.GET("application.navigation.ariaSkipToMenu"), "#menustart"));

					const observer = new MutationObserver(async (mutations) => {
						observer.disconnect();
						// trigger notifications only after navigation has been successufully added to the dom
						_serviceWorker.postMessage("getnotifications");
					});
					observer.observe(document.querySelector("nav"), {
						childList: true,
						subtree: true,
					});

					menu.replaceChildren(...elements);
				};
				break;
			case "start":
				successFn = async function (data) {
					// set application and user settings

					if (api._settings.user) await _serviceWorker.register();
					else {
						clearInterval(_serviceWorker.notif.interval);
						_serviceWorker.notif.interval = null;
						_serviceWorker.terminate();
					}

					await api.application("get", "menu");
					api.history.buttoncolor();

					// replace "please sign in" with user name for landing page
					let signin = api._lang.GET("application.navigation.signin"),
						greeting = ", " + signin.charAt(0).toLowerCase() + signin.slice(1);
					api.update_header(
						api._lang.GET("general.welcome_header", {
							":user": api._settings.user.name ? " " + api._settings.user.name : greeting,
						})
					);

					// update default language
					if (api._settings.config.application && api._settings.config.application.defaultlanguage) {
						document.querySelector("html").lang = api._settings.config.application.defaultlanguage;
					}

					// update application menu icon with user image
					if (api._settings.user.image) {
						let applicationLabel;
						while (!applicationLabel) {
							await _.sleep(50);
							applicationLabel = document.querySelector("[data-for=userMenu" + api._lang.GET("application.navigation.header") + "]");
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
											if (stylesheet[i].conditionText === "only screen and (max-width: 64rem)") {
												stylesheet[i].media.mediaText = "only screen and (max-width: 1rem)";
											}
										}
									}
									break;
								case "masonry":
									if (value) {
										await window.Masonry.breakpoints(true);
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

					const render = new Assemble(data.render);
					document.getElementById("main").replaceChildren(render.initializeSection());
					render.processAfterInsertion();

					if (request[2])
						//search
						document.getElementById("_landingpagesearch").scrollIntoView({ block: "center" });
				};
				break;
			case "manual":
				switch (method) {
					case "get":
						successFn = function (data) {
							api.update_header(api._lang.GET("application.navigation.manual_manager"));
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
			case "cron_log":
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
		if (method === "get") api.history.write(["audit", ...request]);

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
				audit: api._lang.GET("audit.navigation.audit"),
				audittemplate: api._lang.GET("audit.navigation.templates"),
				checks: api._lang.GET("audit.navigation.regulatory"),
				managementreview: api._lang.GET("audit.navigation.management_review"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "audit":
					case "managementreview":
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]]);
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
								_client.audit.managementreview();
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
							if (data.selected && data.selected.length) Composer.importAuditTemplate(data.selected);
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
								api.update_header(title[request[1]] + (request[2] && request[2] !== "null" ? " - " + api._lang.GET("audit.checks_type." + request[2]) : ""));
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
					if (!(payload = Composer.composeNewAuditTemplate())) return;
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
					if (!(payload = Composer.composeNewAuditTemplate())) return;
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
		if (method === "get") api.history.write(["calendar", ...request]);

		request.splice(0, 0, "calendar");
		let payload,
			successFn = function (data) {
				if (data.render) {
					api.update_header(title[request[1]]);
					const render = new Assemble(data.render);
					document.getElementById("main").replaceChildren(render.initializeSection());
					render.processAfterInsertion();
				}
				if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
				if (data.response !== undefined && data.response.id !== undefined) {
					let id = document.getElementsByName("_longtermid");
					if (id && id[0]) id[0].value = data.response.id;
				}
				if (data.data) _serviceWorker.notif.calendar(data.data);
				if (["post", "put", "delete"].includes(method) && ["tasks", "timesheet", "worklists"].includes(request[1])) api.history.go("forth"); // updates the view after any change
			},
			title = {
				appointment: api._lang.GET("calendar.navigation.appointment"),
				tasks: api._lang.GET("calendar.navigation.tasks"),
				timesheet: api._lang.GET("calendar.navigation.timesheet"),
				longtermplanning: api._lang.GET("calendar.navigation.longtermplanning"),
				worklists: api._lang.GET("calendar.navigation.worklists"),
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
				switch (request[1]) {
					case "appointment":
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
						payload = _.getInputs("[data-usecase=appointment]", true);
						break;
					case "longtermplanning":
						if (!(payload = _client.calendar.longtermplanning())) payload = _.getInputs("[data-usecase=longtermplanning]", true);
						break;
					default:
						payload = request[2];
						delete request[2];
						// set unit language keys instead of values
						let units = [];
						for (const [key, value] of payload.entries()) {
							if (value === "unit") units.push(Object.keys(api._lang._USER["units"]).find((unit) => api._lang._USER["units"][unit] === key));
						}
						if (units.length) payload.set(api._lang.GET("calendar.tasks.organizational_unit"), units.join(","));
				}
				break;
			case "put":
				switch (request[1]) {
					case "complete":
						break;
					default:
						payload = request[2];
						delete request[2];
						// set unit language keys instead of values
						let units = [];
						for (const [key, value] of payload.entries()) {
							if (value === "unit") units.push(Object.keys(api._lang._USER["units"]).find((unit) => api._lang._USER["units"][unit] === key));
						}
						if (units.length) payload.set(api._lang.GET("calendar.tasks.organizational_unit"), units.join(","));
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
		if (method === "get") api.history.write(["csvfilter", ...request]);

		request.splice(0, 0, "csvfilter");
		let payload,
			successFn = function (data) {
				if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
				if (data.log !== undefined) {
					const dialog = {
						header: api._lang.GET("csvfilter.use.download"),
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
				rule: api._lang.GET("csvfilter.navigation.filter_manager"),
				filter: api._lang.GET("csvfilter.navigation.filter"),
				erpquery: api._lang.GET("csvfilter.navigation.erpquery"),
				erpupload: api._lang.GET("csvfilter.navigation.erpupload"),
			};
		switch (method) {
			case "get":
				successFn = function (data) {
					if (data.render) {
						api.update_header(title[request[1]]);
						const render = new Assemble(data.render);
						document.getElementById("main").replaceChildren(render.initializeSection());
						render.processAfterInsertion();
						api.preventDataloss.start();
					}
					if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
				};
				break;
			case "post":
				switch (request[1]) {
					case "erpupload":
						successFn = function (data) {
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
				}
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
	 * @param  {array} request api method, search term  | requested directory name, requested filename
	 * @returns request
	 */
	file: async (method, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["file", ...request]);

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
				files: api._lang.GET("file.navigation.files"),
				sharepoint: api._lang.GET("file.navigation.sharepoint"),
				filemanager: api._lang.GET("file.navigation.file_manager"),
				externalfilemanager: api._lang.GET("file.navigation.external_file_manager"),
			};

		switch (method) {
			case "get":
				switch (request[1]) {
					case "filter":
						if (request[4] === "filereference") {
							// filereference coming from assemble.js widget
							api.preventDataloss.monitor = false;
							successFn = function (data) {
								let article = document.querySelector("#inputmodal form article");
								let sibling = article.children[2], // as per assemble after label and hidden input
									deletesibling;
								sibling = sibling.nextSibling;
								if (sibling) {
									do {
										deletesibling = sibling;
										sibling = sibling.nextSibling;
										deletesibling.remove();
									} while (sibling);
								}
								if (data.data) {
									const options = _client.record.filereference(data.data);
									const render = new Assemble(options);
									render.initializeSection(null, article.children[2]);
									render.processAfterInsertion();
								}
								if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
								api.preventDataloss.monitor = true;
							};
						} else {
							successFn = function (data) {
								if (data.data) {
									const all = document.querySelectorAll("[data-filtered]");
									for (const file of all) {
										file.style.display = data.data.includes(file.dataset.filtered) ? api.filter(file.localName) : "none";
									}
								}
								if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							};
						}
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
		if (method === "get") api.history.write(["document", ...request]);

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
				component_editor: api._lang.GET("assemble.navigation.manage_components"),
				document_editor: api._lang.GET("assemble.navigation.manage_documents"),
				approval: api._lang.GET("assemble.navigation.manage_approval"),
				bundle: api._lang.GET("assemble.navigation.manage_bundles"),
				bundles: api._lang.GET("assemble.navigation.bundles"),
				documents: api._lang.GET("assemble.navigation.documents"),
			},
			composedComponent;
		switch (method) {
			case "get":
				switch (request[1]) {
					case "component":
						successFn = function (data) {
							if (data.render) {
								data.render.content.name = data.render.name;
								if (data.render.content) Composer.importDocument([data.render.content]);
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					case "component_editor":
						Composer.componentIdentify = 0;
						Composer.componentSignature = 0;
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								const render = new Compose(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
								if (data.render.component) Composer.importComponent(data.render.component);
								// create multipart form for file uploads
								Composer.addComponentMultipartFormToMain();
								api.preventDataloss.start();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					case "document_editor":
						Composer.componentIdentify = 0;
						Composer.componentSignature = 0;
						successFn = function (data) {
							if (data.render) {
								api.update_header(title[request[1]] + String(data.header ? " - " + data.header : ""));
								const render = new Compose(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
								if (data.render.components) Composer.importDocument(data.render.components);
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
						composedComponent = Composer.composeNewComponent();
						if (!composedComponent) return;
						Composer.addComponentStructureToComponentForm(composedComponent);
						payload = _.getInputs("[data-usecase=component_editor_form]", true);
						break;
					case "document":
						successFn = function (data) {
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							if (data.response !== undefined && data.response.reload !== undefined) api.document("get", data.response.reload);
						};
						if (!(payload = Composer.composeNewDocument())) return;
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
	 *             _     _
	 *   _____ ___|_|___| |_ ___ ___ ___ ___ ___ ___
	 *  |     | .'| |   |  _| -_|   | .'|   |  _| -_|
	 *  |_|_|_|__,|_|_|_|_| |___|_|_|__,|_|_|___|___|
	 *
	 *
	 * @param {string} method
	 * @param  {array} request
	 * @returns request
	 */
	maintenance: (method, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["maintenance", ...request]);

		request.splice(0, 0, "maintenance");
		let payload,
			successFn = function (data) {
				if (data.render) {
					api.update_header(title[request[1]]);
					const render = new Assemble(data.render);
					document.getElementById("main").replaceChildren(render.initializeSection());
					render.processAfterInsertion();
					api.preventDataloss.start();
				}
				if (data.links) {
					new Dialog({
						type: "input",
						render: [
							{ type: "textsection", content: api._lang.GET("maintenance.record_datalist.download") },
							{ type: "links", content: data.links },
						],
					});
				}
				if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
			},
			title = {
				task: api._lang.GET("maintenance.navigation.maintenance"),
			};
		switch (method) {
			case "get":
				break;
			case "post":
			case "put":
				payload = _.getInputs("[data-usecase=maintenance]", true);
				break;
			default:
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
		if (method === "get") api.history.write(["measure", ...request]);

		request.splice(0, 0, "measure");
		let payload,
			successFn = function (data) {
				new Toast(data.response.msg, data.response.type);
			},
			title = {
				measure: api._lang.GET("measure.navigation.measure"),
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
	 * @param  {array} request api method, conversation partner id / message form data
	 * @returns request
	 */
	message: (method, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["message", ...request]);

		request.splice(0, 0, "message");
		let payload,
			successFn = function (data) {
				new Toast(data.response.msg, data.response.type);
				if (data.response !== undefined && data.response.redirect) api.message("get", ...data.response.redirect);
			},
			title = {
				announcements: api._lang.GET("message.navigation.announcements"),
				conversation: api._lang.GET("message.navigation.conversations"),
				register: api._lang.GET("message.navigation.register"),
			};

		switch (method) {
			case "get":
				switch (request[1]) {
					case "announcement":
					case "announcements":
						successFn = async function (data) {
							if (data.render) {
								api.update_header(title[request[1]]);
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					default:
						successFn = async function (data) {
							if (data.render) {
								if (request[2]) await window.Masonry.breakpoints(false);
								api.update_header(title[request[1]]);
								const render = new Assemble(data.render);
								document.getElementById("main").replaceChildren(render.initializeSection());
								render.processAfterInsertion();
								if (request[2]) window.scrollTo(0, document.body.scrollHeight);
							}
							if (data.data) _serviceWorker.notif.communication(data.data);
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
				}
				break;
			case "post":
			case "put":
				switch (request[1]) {
					case "announcement":
					case "announcements":
						if (3 in request && request[3] && request[3] instanceof FormData) {
							// passed formdata
							payload = request[3];
							delete request[3];
						}
						break;
					default:
						if (2 in request && request[2] && request[2] instanceof FormData) {
							// passed formdata
							payload = request[2];
							delete request[2];
						} else payload = _.getInputs("[data-usecase=message]", true);
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
		if (method === "get") api.history.write(["purchase", ...request]);

		if (["vendor", "product", "mdrsamplecheck", "incorporation", "pendingincorporations", "exportpricelist", "productsearch"].includes(request[0])) request.splice(0, 0, "consumables");
		else request.splice(0, 0, "order");

		let payload,
			successFn = function (data) {
				new Toast(data.response.msg, data.response.type);
				if (data.response.type !== "error" && data.response.id) api.purchase("get", request[1], data.response.id);
				if (data.data) _serviceWorker.notif.consumables(data.data);
			},
			title = {
				vendor: api._lang.GET("consumables.navigation.vendor"),
				product: api._lang.GET("consumables.navigation.product"),
				order: api._lang.GET("order.navigation.order"),
				prepared: api._lang.GET("order.navigation.prepared_orders"),
				approved: api._lang.GET("order.navigation.approved_orders"),
				pendingincorporations: api._lang.GET("consumables.navigation.incorporated_pending"),
			};
		if (request[2] === api._lang.GET("consumables.vendor.edit_existing_vendors_new")) request.splice(2, 1);
		switch (method) {
			case "get":
				switch (request[1]) {
					case "productsearch":
						switch (request[4]) {
							case "productselection": // coming from assemble.js widget
								successFn = function (data) {
									let article = document.querySelector("#inputmodal form article");
									let sibling = article.children[3], // as per assemble after button, label, hint and hidden input
										deletesibling;
									sibling = sibling.nextSibling;
									if (sibling) {
										do {
											deletesibling = sibling;
											sibling = sibling.nextSibling;
											deletesibling.remove();
										} while (sibling);
									}
									if (data.render && data.render.content) {
										const render = new Assemble(data.render);
										render.initializeSection(null, article.children[3]);
										render.processAfterInsertion();
									}
									if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
								};
								break;
							default: // product management or order
								api.preventDataloss.monitor = false;
								successFn = function (data) {
									let search = document.querySelector("main form, main div").firstElementChild;
									if (data.render && data.render.content) {
										const render = new Assemble(data.render);
										search.replaceWith(...Array.from(render.initializeSection(null, null, "iCanHasNodes")));
										render.processAfterInsertion();
									}
									if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
									api.preventDataloss.monitor = request[4] === "editconsumables";
								};
						}
						break;
					case "incorporation":
						successFn = function (data) {
							if (data.render) {
								new Dialog(
									{
										type: "input",
										header: api._lang.GET("order.incorporation.incorporation"),
										render: data.render.content,
										options: data.render.options,
									},
									"FormData"
								).then((response) => {
									if (response) api.purchase("post", "incorporation", data.render.productid, response);
									else new Toast(api._lang.GET("order.incorporation.failure"), "error");
								});
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					case "mdrsamplecheck":
						successFn = function (data) {
							if (data.render) {
								new Dialog(
									{
										type: "input",
										header: api._lang.GET("order.sample_check.sample_check"),
										render: data.render.content,
										options: data.render.options,
									},
									"FormData"
								).then((response) => {
									if (response) api.purchase("post", "mdrsamplecheck", data.render.productid, response);
									else new Toast(api._lang.GET("order.incorporation.failure"), "error");
								});
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					case "export":
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
					case "product":
						if (request[2] && typeof request[2] !== "number") {
							// pass article info as query parameters for adding unknown articles to database from orders
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
								api.update_header(title[request[1]]);
								_client.order.approved(data.data);
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
					case "productsearch":
						payload = request[3]; // form data object passed by utility.js
						request[3] = "null";
						successFn = function (data) {
							new Dialog({
								type: "input",
								header: api._lang.GET("order.manual_match"),
								render: data.render.content,
							});
						};
						break;
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
		if (method === "get") api.history.write(["record", ...request]);

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
				identifier: api._lang.GET("record.navigation.create_identifier"),
				record: api._lang.GET("record.navigation.summaries"),
				records: api._lang.GET("record.navigation.summaries"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "import":
						successFn = function (data) {
							if (data.data !== undefined) {
								let inputs = document.querySelectorAll("input, textarea, select");
								let groupname, files, a;
								for (const input of inputs) {
									if (input.type === "file") {
										if (Object.keys(data.data).includes(input.name.replace("[]", ""))) {
											files = data.data[input.name.replace("[]", "")].split(", ");
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
										input.checked = Object.keys(data.data).includes(input.name) && data.data[input.name] === input.value;
									} else if (input.type === "checkbox") {
										groupname = input.dataset.grouped;
										input.checked = Object.keys(data.data).includes(groupname) && data.data[groupname].split(" | ").includes(input.name);
									} else {
										if (Object.keys(data.data).includes(input.name)) input.value = data.data[input.name];
										// append multiple text-/number inputs / selects / productselections that have been added dynamically
										let dynamicMultiples = Object.keys(data.data)
											.filter((name) => {
												return name.match(new RegExp(String.raw`${input.name}\(\d+\)$`, "g"));
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
												clone.attributes.name = name;
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
												if (!document.getElementsByName(name)[0]) new Assemble({ content: [[clone]], composer: "elementClone" }).initializeSection(null, element);
												element = document.getElementsByName(name)[0].nextSibling; // label
											});
										}
									}
								}
							}
							if (data.response.msg !== undefined) {
								const options = {};
								options[api._lang.GET("general.cancel_button")] = false;
								if (typeof data.response.msg === "object")
									options[api._lang.GET("record.import.ok")] = {
										value: true,
										class: "reducedCTA",
									};

								new Dialog({
									type: typeof data.response.msg === "object" ? "input" : "confirm",
									header: api._lang.GET("assemble.render.merge"),
									options: options,
									render: data.response.msg,
								}).then((response) => {
									if (response && typeof data.response.msg === "object" && api._lang.GET("record.import.by_name") in response) {
										let result = {};
										// deconstruct key:value<br>...
										for (const match of response[api._lang.GET("record.import.by_name")].matchAll(/(.+?): (.*?)(?:<br>|$)/gm)) {
											result[match[1]] = match[2];
										}
										data = {
											data: result,
											response: {
												msg: api._lang.GET("record.import.success"),
											},
										};
										successFn(data);
									}
								});
							}
						};
						payload = {
							IDENTIFY_BY_: request[2],
							NAMELOOKUP: request[3] || null,
							DOBLOOKUP: request[4] || null,
						};
						delete request[2];
						delete request[3];
						delete request[4];
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
					case "erpcasepositions":
						// fall back to default successFn
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
				if (request[4] instanceof FormData) {
					payload = request[4];
					delete request[4];
				}
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload);
	},

	/**
	 *                               _ _   _ _ _ _
	 *   ___ ___ ___ ___ ___ ___ ___|_| |_|_| |_| |_ _ _
	 *  |  _| -_|_ -| . | . |   |_ -| | . | | | |  _| | |
	 *  |_| |___|___|  _|___|_|_|___|_|___|_|_|_|_| |_  |
	 *              |_|                             |___|
	 *
	 * @param {string} method
	 * @param  {array} request
	 * @returns request
	 */
	responsibility: (method, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["responsibility", ...request]);

		request.splice(0, 0, "responsibility");
		let payload,
			successFn = function (data) {
				if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
			},
			title = {
				responsibilities: api._lang.GET("responsibility.navigation.responsibility"),
			};
		switch (method) {
			case "get":
				successFn = function (data) {
					if (data.render) {
						api.update_header(title[request[1]]);
						const render = new Assemble(data.render);
						document.getElementById("main").replaceChildren(render.initializeSection());
						render.processAfterInsertion();
						api.preventDataloss.start();
					}
					if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
				};
				break;
			case "post":
			case "put":
				payload = _.getInputs("[data-usecase=responsibility]", true);
				break;
			default:
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
		if (method === "get") api.history.write(["risk", ...request]);

		request.splice(0, 0, "risk");
		let payload,
			successFn = function (data) {
				new Toast(data.response.msg, data.response.type);
			},
			title = {
				risk: api._lang.GET("risk.navigation.risk_management"),
				search: api._lang.GET("risk.search"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "search":
						successFn = function (data) {
							let search = document.querySelector("main form").firstElementChild;
							if (data.render && data.render.content) {
								const render = new Assemble(data.render);
								search.replaceWith(...Array.from(render.initializeSection(null, null, "iCanHasNodes")));
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
		if (method === "get") api.history.write(["texttemplate", ...request]);

		request.splice(0, 0, "texttemplate");
		let payload,
			successFn = function (data) {
				new Toast(data.response.msg, data.response.type);
			},
			title = {
				chunk: api._lang.GET("texttemplate.navigation.chunks"),
				template: api._lang.GET("texttemplate.navigation.templates"),
				text: api._lang.GET("texttemplate.navigation.texts"),
			};
		switch (method) {
			case "get":
				switch (request[3]) {
					case "modal":
						successFn = function (data) {
							if (data.render) {
								new Dialog({ type: "input", header: api._lang.GET("texttemplate.navigation.texts"), render: data.render });
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							if (data.data !== undefined) _client.texttemplate.data = data.data;
							if (data.selected !== undefined && data.selected.length) {
								Composer.importTextTemplate(data.selected);
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
								Composer.importTextTemplate(data.selected);
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
						if (!(payload = Composer.composeNewTextTemplate())) return;
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
	 * displays a form for zipping an unzipping files
	 * displays some common calculation options
	 *
	 * @param {string} method get
	 * @param  {array} request api method, occasionally passed values for 2d codes
	 * @returns request
	 */
	tool: (method, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["tool", ...request]);

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
				code: api._lang.GET("tool.navigation.digital_codes"),
				calculator: api._lang.GET("tool.navigation.calculator"),
				scanner: api._lang.GET("tool.navigation.scanner"),
				zip: api._lang.GET("tool.navigation.zip"),
				image: api._lang.GET("tool.navigation.image"),
			};
		switch (method) {
			case "get":
				switch (request[1]) {
					case "markdown":
						successFn = function (data) {
							if (data.render) {
								const options = {};
								options[api._lang.GET("tool.markdown.cancel")] = false;
								options[api._lang.GET("tool.markdown.convert")] = { value: true, class: "reducedCTA" };
								new Dialog(
									{
										type: "input",
										header: request[2] && request[2] === "table" ? api._lang.GET("tool.markdown.csv_conversion") : api._lang.GET("tool.markdown.playground"),
										render: data.render,
										options: options,
									},
									"FormData"
								).then((response) => {
									if (response) api.tool("post", "markdown", request[2] || null, response);
								});
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					default:
				}
				break;
			case "post":
				switch (request[1]) {
					case "calculator":
						payload = _.getInputs("[data-usecase=tool_calculator]", true);
						break;
					case "code":
						payload = _.getInputs("[data-usecase=tool_create_code]", true);
						break;
					case "markdown":
						payload = request[3];
						delete request[3];
						successFn = function (data) {
							if (data.render) {
								const options = {};
								options[api._lang.GET("tool.markdown.cancel")] = false;
								options[api._lang.GET("tool.markdown.convert")] = { value: true, class: "reducedCTA" };
								new Dialog(
									{
										type: "input",
										header: request[2] && request[2] === "table" ? api._lang.GET("tool.markdown.csv_conversion") : api._lang.GET("tool.markdown.playground"),
										render: data.render,
										options: options,
									},
									"FormData"
								).then((response) => {
									if (response) api.tool("post", "markdown", request[2] || null, response);
								});
							}
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
						};
						break;
					case "image":
						payload = _.getInputs("[data-usecase=tool_image]", true);
						break;
					case "zip":
						payload = _.getInputs("[data-usecase=tool_zip]", true);
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
		if (method === "get" && !["training"].includes(request[0])) api.history.write(["user", ...request]);

		request.splice(0, 0, "user");
		let payload,
			successFn = function (data) {
				new Toast(data.response.msg, data.response.type);
				if (data.response.id) api.user("get", request[1], data.response.id);
			},
			title = {
				profile: api._lang.GET("application.navigation.user_profile"),
				user: api._lang.GET("application.navigation.user_manager"),
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
					case "training":
						successFn = function (data) {
							if (data.response !== undefined && data.response.msg !== undefined) new Toast(data.response.msg, data.response.type);
							if (data.render !== undefined) {
								const options = {};
								options[api._lang.GET("general.ok_button")] = { value: true, class: "reducedCTA" };
								options[api._lang.GET("general.cancel_button")] = false;
								new Dialog(
									{
										type: "input",
										render: data.render,
										options: options,
									},
									"FormData"
								).then((response) => {
									if (!response) return;
									api.user(2 in request && request[2] !== "null" ? "put" : "post", "training", 2 in request && request[2] ? request[2] : "null", response);
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
			case "put":
				if (3 in request && request[3] && request[3] instanceof FormData) {
					// training
					payload = request[3]; // form data object passed by utility.js
					delete request[3];
				} else payload = _.getInputs("[data-usecase=user]", true);
				break;
			case "delete":
				break;
			default:
				return;
		}
		api.send(method, request, successFn, null, payload);
	},
};

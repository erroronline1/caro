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
import { _serviceWorker } from "./utility.js";

export const api = {
	// passed from backend
	_settings: {
		user: {},
		config: {},
		session: {
			elementId: 0,
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

		// ensure passed parameters are encoded e.g. to enable #-characters within a passed api path-parameter
		request = request.map((i) => encodeURIComponent(i).replace("+", "%2B"));

		api.loadindicator(request, true);

		// default reset masonry breakpoint if former call (current history has been set by recent api call) had prevented this
		// currently masonry is counterproductive for conversations
		if (api._settings.user.app_settings && api._settings.user.app_settings.masonry) await window.Masonry.breakpoints(true);

		if (api._settings.user && api._settings.user.fingerprint && ["post", "put", "patch"].includes(method)) {
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

				document.querySelector("header>div:nth-of-type(3)").style.display = [200, 511].includes(data.status) ? "none" : "block";
				if (data.status === 203) new Toast(api._lang.GET("general.service_worker_get_cache_fallback"), "info");
				if (data.status === 207) {
					new Toast(api._lang.GET("general.service_worker_post_cache_fallback"), "info");
					_serviceWorker.onPostCache();
					return;
				}
				if (data.status === 511) {
					// session timeout
					api.loadindicator(request, false);
					clearInterval(_serviceWorker.notif.interval);
					_serviceWorker.notif.interval = null;
					if (data.body.config) {
						Object.assign(api._settings, data.body.config);
						if ("language" in data.body.config) {
							api._lang._USER = data.body.config.language;
						}
						api.update_user_settings();
					}

					// add class. without being logged in the entry point always returns this error
					if (api._settings.config.application && api._settings.config.application.is_development) {
						document.querySelector("body>header").classList.add("is_development");
					}

					if (JSON.stringify(request) !== JSON.stringify(["application", "authentify"])) api._unauthorizedRequest = { method: method, payload: payload, request: request };
					const options = {};
					options[api._lang.GET("general.ok_button")] = true;
					options[api._lang.GET("application.navigation.signout_user", { ":name": api._settings.user.name || "" })] = {
						onclick: "api.application('delete', null, 'authentify'); api.application('get', null, 'start')",
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
						api.application("post", response, "authentify");
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
		api.loadindicator(request, false);
	},
	/**
	 *   _           _ _       _ _         _
	 *  | |___ ___ _| |_|___ _| |_|___ ___| |_ ___ ___
	 *  | | . | .'| . | |   | . | |  _| .'|  _| . |  _|
	 *  |_|___|__,|___|_|_|_|___|_|___|__,|_| |___|_|
	 *
	 * ui feedback on occuring requests that are expected to take longer
	 * @param {any} toggle initiates indicator, undefined|null|false disables all
	 * @returns none
	 */
	loadindicator: (request, toggle) => {
		if (toggle) {
			if (!api.loadindicatorTimeout[request]) api.loadindicatorTimeout[request] = [];
			api.loadindicatorTimeout[request].push(
				setTimeout(() => {
					document.querySelector("body").style.cursor = "wait";
					document.querySelector(".loader").style.display = "block";
					document.querySelector(".loader").style.opacity = "1";
				}, 500)
			); // wait a bit to avoid flash on every request
			return;
		}
		if (api.loadindicatorTimeout[request]) {
			api.loadindicatorTimeout[request].map((id) => clearTimeout(id));
			delete api.loadindicatorTimeout[request];

			document.querySelector("body").style.cursor = "initial";
			document.querySelector(".loader").style.opacity = "0";
			setTimeout(() => {
				document.querySelector(".loader").style.display = "none";
			}, 300);
		}
	},
	loadindicatorTimeout: {},

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
		if (document.querySelector("#menustart")) document.querySelector("#menustart").focus();
	},

	/**
	 *             _     _                                           _   _   _
	 *   _ _ ___ _| |___| |_ ___       _ _ ___ ___ ___       ___ ___| |_| |_|_|___ ___ ___
	 *  | | | . | . | .'|  _| -_|     | | |_ -| -_|  _|     |_ -| -_|  _|  _| |   | . |_ -|
	 *  |___|  _|___|__,|_| |___|_____|___|___|___|_|  _____|___|___|_| |_| |_|_|_|_  |___|
	 *      |_|                 |_____|               |_____|                     |___|
	 *
	 */
	update_user_settings: async function () {
		if (!api._settings.user.app_settings) api._settings.user.app_settings = { theme: "light" };
		if (!api._settings.user.app_settings.theme) api._settings.user.app_settings.theme = "light";

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
					break;
				case "language":
					document.querySelector("html").lang = value;
					_client._tts.voice = null;
			}
		}
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
					api.application("get", null, "authentify");
					return;
				}
				if (api._settings.config.lifespan.session.idle > 0 && remaining_visual > 0) {
					// render the indicator as long as there is time left
					document.querySelector("header>div:nth-of-type(4)").style.display = "none";
					api.session_timeout.render((100 * remaining_visual) / (api._settings.config.lifespan.session.idle * 1000), remaining_visual);
					return;
				}
				// session has been timed out, display message and clear intervals
				api.session_timeout.render(0);
				if ("name" in api._settings.user) new Toast(api._lang.GET("assemble.render.aria.timeout"), "error", 1800000, "sessionwarning");
				clearInterval(api.session_timeout.interval);
				clearInterval(_serviceWorker.notif.interval);
				_serviceWorker.notif.interval = null;
				document.querySelector("header>div:nth-of-type(4)").style.display = "block";
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
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request api method, logintoken|manual id
	 */
	application: async (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get" && !["menu", "authentify"].includes(request[0])) api.history.write(["application", payload, ...request]);
		request.splice(0, 0, "application");
		let successFn = async function (data) {
			if (data.config) {
				Object.assign(api._settings, data.config);
				if ("language" in data.config) {
					api._lang._USER = data.config.language;
				}
				api.update_user_settings();
			}

			switch (request[1]) {
				case "authentify":
					switch (method) {
						case "delete":
							api._settings.user = data.user || {};
							api._settings.config = data.config || {};

							document.querySelector("body>header>h1").innerHTML = document.getElementById("main").innerHTML = document.querySelector("body>nav").innerHTML = "";
							api.history.reset();

							// close all modals to avoid access to files once logged out
							for (const opendialog of Object.values(document.querySelectorAll("dialog[open]"))) {
								opendialog.remove();
							}
							break;
						case "post":
							// sometimes an error occures, try to show what's happening
							try {
								if (data.user) {
									// reset notif interval
									if (_serviceWorker.worker)
										// registered
										_serviceWorker.notif.interval = setInterval(() => {
											_serviceWorker.postMessage("getnotifications");
										}, _serviceWorker.notif.interval_duration);
									// faulty certificate or whatever
									else
										_serviceWorker.notif.interval = setInterval(() => {
											api.notification("get", null, "notifs");
										}, _serviceWorker.notif.interval_duration * 2);

									if (api._unauthorizedRequest.request && api._unauthorizedRequest.request.length && JSON.stringify(api._unauthorizedRequest.request) !== JSON.stringify(["application", "authentify"])) {
										// resend last request
										const call = api._unauthorizedRequest.request.shift();
										if (api.hasOwnProperty(call)) api[call](api._unauthorizedRequest.method, api._unauthorizedRequest.payload, ...api._unauthorizedRequest.request);
									}
									api.session_timeout.init();
								}
							} catch (error) {
								_client.application.debug(error, api._unauthorizedRequest);
							}
					}
					break;
			}

			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.render) {
				switch (request[1]) {
					case "menu":
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
						button.style.maskImage = "url('./media/angle-left.svg')";
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
							div.setAttribute("data-for", "userMenu" + group.replace(" ", "_"));
							if (group === api._lang.GET("application.navigation.header") && api._settings.user.image) {
								div.style.maskImage = "none";
								div.style.backgroundImage = "url('" + api._settings.user.image + "')";
								div.style.backgroundSize = "cover";
								div.style.borderRadius = "50%";
							} else div.style.maskImage = icons[group];
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
						button.style.maskImage = "url('./media/angle-right.svg')";
						elements.push(button);
						elements.push(skip(api._lang.GET("application.navigation.ariaSkipToMenu"), "#menustart"));

						const observer = new MutationObserver(async (mutations) => {
							observer.disconnect();
							// trigger notifications only after navigation has been successufully added to the dom
							if (_serviceWorker.worker) _serviceWorker.postMessage("getnotifications");
							else api.notification("get", null, "notifs");
						});
						observer.observe(document.querySelector("nav"), {
							childList: true,
							subtree: true,
						});

						menu.replaceChildren(...elements);

						api.history.buttoncolor();
						break;
					case "start":
						if (api._settings.user) await _serviceWorker.register();
						else {
							clearInterval(_serviceWorker.notif.interval);
							_serviceWorker.notif.interval = null;
							_serviceWorker.terminate();
						}
						await api.application("get", null, "menu");
						await _.sleep(100);
						// ensure proper flagging in case of window refreshs
						if (api._settings.config.application && api._settings.config.application.is_development) {
							document.querySelector("body>header").classList.add("is_development");
						}
					// no break by intent
					default:
						const render = new Assemble(data.render);
						document.getElementById("main").replaceChildren(render.initializeSection());
						render.processAfterInsertion();

						if (request[1] === "start" && request[2])
							//search
							document.getElementById("_landingpagesearch").scrollIntoView({ block: "center" });
				}
			}
			if (data.dialog) {
				const options = {};
				options[api._lang.GET("general.ok_button")] = false;
				new Dialog({
					type: "input",
					render: data.dialog.render,
					options: options,
				});
			}
			if (data.notif) _serviceWorker.notif.calendar(data.notif);
			if (data.redirect) api.application("get", null, ...data.redirect);
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
		api.send(method, request, successFn, null, payload);
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
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request api method
	 */
	audit: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get" && !request[0].startsWith("export")) api.history.write(["audit", payload, ...request]);
		request.splice(0, 0, "audit");
		if (["import"].contains(request[1])) api.preventDataloss.stop();
		let successFn = async function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				const options = {};
				options[api._lang.GET("general.ok_button")] = false;
				new Dialog({
					type: "input",
					render: data.dialog.render,
					options: options,
				});
			}
			if (data.render) {
				let render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
				switch (request[1]) {
					case "managementreview":
						_client.audit.managementreview();
					case "audit":
					case "import":
						api.preventDataloss.start();
				}
			}
			if (data.selected) {
				switch (request[1]) {
					case "import":
						document.getElementById("TemplateObjectives").value += "\n\n" + data.selected;
						break;
					case "audittemplate":
						Composer.importAuditTemplate(data.selected);
						break;
				}
			}
			if (data.notif) _serviceWorker.notif.calendar(data.notif);
			if (data.redirect) api.audit("get", null, ...data.redirect);
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
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
	 * @param {string} method get|post|put|patch|delete
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request api method, name|id
	 */
	calendar: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get" && !["monthlyTimesheets", "yearlyTimesheets"].includes(request[0])) api.history.write(["calendar", payload, ...request]);
		request.splice(0, 0, "calendar");
		let successFn = async function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				const options = {};
				options[api._lang.GET("general.ok_button")] = false;
				new Dialog({
					type: "input",
					render: data.dialog.render,
					options: options,
				});
			}
			if (data.render) {
				let render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
			}
			if (data.selected) {
				let id = document.getElementsByName("_longtermid");
				if (id && id[0]) id[0].value = data.selected;
			}
			if (data.notif) _serviceWorker.notif.calendar(data.notif);
			if (data.redirect) api.calendar("get", null, ...data.redirect);
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
		api.send(method, request, successFn, null, payload);
	},

	/**
	 *                                 _   _
	 *   ___ ___ ___ ___ _ _ _____ ___| |_| |___ ___
	 *  |  _| . |   |_ -| | |     | .'| . | | -_|_ -|
	 *  |___|___|_|_|___|___|_|_|_|__,|___|_|___|___|
	 *
	 *
	 *
	 * @param {string} method
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request
	 */
	consumables: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["consumables", payload, ...request]);
		request.splice(0, 0, "consumables");
		let successFn = function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				new Dialog(
					{
						type: "input",
						header: data.dialog.header,
						render: data.dialog.render,
						options: data.dialog.options,
					},
					"FormData"
				).then((response) => {
					if (response) api.consumables("put", response, "mdrsamplecheck", data.dialog.productid);
					else new Toast(api._lang.GET("order.sample_check.failure"), "error");
				});
			}
			if (data.render) {
				let render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
				api.preventDataloss.start();
			}
			if (data.insert) {
				let render = new Assemble(data.insert);
				switch (request[2]) {
					case "productselection":
						let article = document.querySelector("#_productselectionDialog form article");
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
						render.initializeSection(null, article.children[3]);
						render.processAfterInsertion();
						break;
					default:
						let search = document.querySelector("main form, main div").firstElementChild;
						search.replaceWith(...Array.from(render.initializeSection(null, null, "iCanHasNodes")));
						render.processAfterInsertion();
				}
			}
			if (data.notif) _serviceWorker.notif.consumables(data.notif);
			if (data.redirect) api.order("get", null, ...data.redirect);
			if (data.data) _client.order.approved(data.data);
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
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
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request api method
	 */
	csvfilter: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["csvfilter", payload, ...request]);
		request.splice(0, 0, "csvfilter");
		let successFn = async function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				const options = {};
				options[api._lang.GET("general.ok_button")] = false;
				new Dialog({
					type: "input",
					header: api._lang.GET("csvfilter.use.download"),
					render: data.dialog.render,
					options: options,
				});
			}
			if (data.render) {
				let render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
				api.preventDataloss.start();
			}
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
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
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param  {array} request api method, name|id
	 */
	document: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["document", payload, ...request]);
		request.splice(0, 0, "document");
		let successFn = async function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				const options = {};
				options[api._lang.GET("general.ok_button")] = false;
				new Dialog({
					type: "input",
					render: data.dialog.render,
					options: options,
				});
			}
			if (data.render) {
				let render;
				switch (request[1]) {
					case "component_editor":
					case "document_editor":
						Composer.componentIdentify = 0;
						Composer.componentSignature = 0;
						render = new Compose(data.render);
						document.getElementById("main").replaceChildren(render.initializeSection());
						render.processAfterInsertion();
						break;
					default:
						render = new Assemble(data.render);
						document.getElementById("main").replaceChildren(render.initializeSection());
						render.processAfterInsertion();
				}
			}
			if (data.data) {
			}
			if (data.selected) {
				switch (request[1]) {
					case "component":
						Composer.importDocument(data.selected.content);
						break;
					case "component_editor":
						Composer.importComponent(data.selected);
						// create multipart form for file uploads
						Composer.addComponentMultipartFormToMain();
						api.preventDataloss.start();
						break;
					case "document_editor":
						Composer.importDocument(data.selected);
						api.preventDataloss.start();
						break;
				}
			}
			if (data.notif) _serviceWorker.notif.records(data.notif);
			if (data.redirect) api.document("get", null, ...data.redirect);
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
		api.send(method, request, successFn, null, payload);
	},

	/**
	 *
	 *   ___ ___ ___ ___ _ _ ___ ___ _ _
	 *  | -_|  _| . | . | | | -_|  _| | |
	 *  |___|_| |  _|_  |___|___|_| |_  |
	 *          |_|   |_|           |___|
	 * available data requests for regular users regarding erp data if applicable
	 *
	 * @param {string} method get|post
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request api method
	 */
	erpquery: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["erpquery", payload, ...request]);
		request.splice(0, 0, "erpquery");
		let successFn = async function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				const options = {};
				options[api._lang.GET("general.ok_button")] = false;
				new Dialog({
					type: "input",
					header: api._lang.GET("maintenance.record_datalist.download"),
					render: data.dialog.render,
					options: options,
				});
			}
			if (data.render) {
				let render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
				api.preventDataloss.start();
			}
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
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
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param  {array} request api method, search term  | requested directory name, requested filename
	 */
	file: async (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["file", payload, ...request]);
		request.splice(0, 0, "file");
		if (request[4] === "filereference") api.preventDataloss.monitor = false;
		let successFn = function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.render) {
				let render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
				api.preventDataloss.start();
			}
			if (data.redirect) api.file("get", null, ...data.redirect);
			if (data.data) {
				if (request[4] === "filereference") {
					// this is no real api endpoint but used for routing the behaviour of the successFn
					let article = document.querySelector("#_fileselectionDialog form article");
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
					const options = _client.record.filereference(data.data);
					const render = new Assemble(options);
					render.initializeSection(null, article.children[2]);
					render.processAfterInsertion();
					api.preventDataloss.monitor = true;
				} else {
					const all = document.querySelectorAll("[data-filtered]");
					for (const file of all) {
						file.style.display = data.data.includes(file.dataset.filtered) ? api.filter(file.localName) : "none";
					}
				}
			}
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
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
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request
	 */
	maintenance: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["maintenance", payload, ...request]);
		request.splice(0, 0, "maintenance");
		let successFn = async function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				const options = {};
				options[api._lang.GET("general.ok_button")] = false;
				new Dialog({
					type: "input",
					header: api._lang.GET("maintenance.record_datalist.download"),
					render: data.dialog.render,
					options: options,
				});
			}
			if (data.render) {
				let render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
				api.preventDataloss.start();
			}
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
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
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request api method, measure id, vote / message form data
	 */
	measure: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["measure", payload, ...request]);
		request.splice(0, 0, "measure");
		let successFn = function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.render) {
				let render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
			}
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
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
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request api method, conversation partner id / message form data
	 */
	message: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["message", payload, ...request]);
		request.splice(0, 0, "message");
		let successFn = async function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				const options = {};
				options[api._lang.GET("general.ok_button")] = false;
				new Dialog({
					type: "input",
					header: api._lang.GET("maintenance.record_datalist.download"),
					render: data.dialog.render,
					options: options,
				});
			}
			if (data.render) {
				if (request[1] === "conversation" && request[2]) await window.Masonry.breakpoints(false);

				let render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();

				if (request[1] === "conversation" && request[2])
					setTimeout(function () {
						window.scrollTo(0, document.body.scrollHeight);
					}, 0);
			}
			if (data.notif) _serviceWorker.notif.communication(data.notif);
			if (data.redirect) api.message("get", null, ...data.redirect);
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
		api.send(method, request, successFn, null, payload);
	},

	/**
	 *           _   _ ___ _         _   _
	 *   ___ ___| |_|_|  _|_|___ ___| |_|_|___ ___
	 *  |   | . |  _| |  _| |  _| .'|  _| | . |   |
	 *  |_|_|___|_| |_|_| |_|___|__,|_| |_|___|_|_|
	 *
	 * fallback in case of serviceworker failing to register due to outdated ssl certificate
	 * @param {string} method
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request
	 */
	notification: (method, payload = {}, ...request) => {
		request = [...request];
		request.splice(0, 0, "notification");
		let successFn = function (data) {
			_serviceWorker.onMessage({ data: data });
		};
		api.send(method, request, successFn, null, payload);
	},

	/**
	 *             _
	 *   ___ ___ _| |___ ___
	 *  | . |  _| . | -_|  _|
	 *  |___|_| |___|___|_|
	 *
	 *
	 * @param {string} method
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request
	 */
	order: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["order", payload, ...request]);
		request.splice(0, 0, "order");
		let successFn = function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				const options = {};
				options[api._lang.GET("general.cancel_button")] = false;
				new Dialog({
					type: "input",
					header: api._lang.GET("order.manual_match"),
					render: data.dialog.render,
					options: options,
				});
			}
			if (data.render) {
				let render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
				api.preventDataloss.start();
			}
			if (data.insert) {
				let render = new Assemble(data.insert);
				let search = document.querySelector("main form, main div").firstElementChild;
				search.replaceWith(...Array.from(render.initializeSection(null, null, "iCanHasNodes")));
				render.processAfterInsertion();
			}
			if (data.notif) _serviceWorker.notif.consumables(data.notif);
			if (data.redirect) api.order("get", null, ...data.redirect);
			if (data.data) _client.order.approved(data.data);
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
		if (!payload || !(payload instanceof FormData)) payload = _client.order.approvedFilter();
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
	 * @param {string} method get|post|patch
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request api method, occasional identifier to import from
	 */
	record: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["record", payload, ...request]);
		request.splice(0, 0, "record");
		let successFn = function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				switch (request[1]) {
					case "import":
						const options = {};
						options[api._lang.GET("general.cancel_button")] = false;
						if (!data.toast)
							options[api._lang.GET("record.import.ok")] = {
								value: true,
								class: "reducedCTA",
							};
						new Dialog({
							type: "input",
							header: api._lang.GET("assemble.render.merge"),
							options: options,
							render: data.dialog.render,
						}).then((response) => {
							if (response && api._lang.GET("erpquery.integrations.data_import") in response) {
								let result = {};
								// deconstruct key:value<br>...
								for (const match of response[api._lang.GET("erpquery.integrations.data_import")].matchAll(/(.+?): (.*?)(?:<br>|$)/gm)) {
									result[match[1]] = match[2];
								}
								data = {
									data: result,
									response: {
										msg: api._lang.GET("record.import.success"),
									},
								};
								// close all modals to avoid duplication
								for (const opendialog of Object.values(document.querySelectorAll("dialog[open]"))) {
									opendialog.remove();
								}
								successFn(data);
							}
						});
						break;
					default:
						new Dialog({
							type: "input",
							render: data.dialog.render,
							options: data.dialog.options,
						});
				}
			}
			if (data.render) {
				const render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
				api.preventDataloss.start();
			}
			if (data.data) {
				switch (request[1]) {
					case "records":
						if (api._settings.user.app_settings.recordsLayout) {
							switch (api._settings.user.app_settings.recordsLayout) {
								// in case other options may become implemented also see user.php profile
								case "table":
									_client.record.table(data.data);
									break;
								default:
									_client.record.tile(data.data);
							}
						} else _client.record.tile(data.data);
						break;
					case "import":
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
						break;
				}
			}
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
		if (method === "get" && (!payload || !Object.keys(payload).length)) {
			payload = {
				_filter: document.getElementById("_recordfilter") ? encodeURIComponent(document.getElementById("_recordfilter").value) : null,
				_unit: document.querySelector(`input[name="${api._lang.GET("order.organizational_unit")}"]:checked`) ? document.querySelector(`input[name="${api._lang.GET("order.organizational_unit")}"]:checked`).value : null,
				_state: document.querySelector(`input[name="${api._lang.GET("record.pseudodocument_casedocumentation")}"]:checked`) ? document.querySelector(`input[name="${api._lang.GET("record.pseudodocument_casedocumentation")}"]:checked`).value : null,
			};
			for (const [key, value] of Object.entries(payload)) {
				if (!value) delete payload[key];
			}
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
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request
	 */
	responsibility: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["responsibility", payload, ...request]);
		request.splice(0, 0, "responsibility");
		let successFn = function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				new Dialog(
					{
						type: "input",
						header: request[2] && request[2] === "table" ? api._lang.GET("tool.markdown.csv_conversion") : api._lang.GET("tool.markdown.playground"),
						render: data.dialog.render,
						options: data.dialog.options,
					},
					"FormData"
				).then((response) => {
					if (response) api.tool("post", response, "markdown", request[2] || null);
				});
			}
			if (data.render) {
				const render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
				api.preventDataloss.start();
			}
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
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
	 * @param {string} method get|post|patch
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request api method
	 * @returns request
	 */
	risk: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["risk", payload, ...request]);
		request.splice(0, 0, "risk");
		let successFn = function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				new Dialog({
					type: "input",
					header: api._lang.GET("texttemplate.navigation.texts"),
					render: data.dialog.render,
					options: data.dialog.options,
				});
			}
			if (data.render) {
				let render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
				api.preventDataloss.start();
			}
			if (data.insert) {
				let render = new Assemble(data.insert);
				let search = document.querySelector("main form").firstElementChild;
				search.replaceWith(...Array.from(render.initializeSection(null, null, "iCanHasNodes")));
				render.processAfterInsertion();
			}
			if (data.data !== undefined) _client.texttemplate.data = data.data;
			if (data.selected !== undefined && data.selected.length) {
				Composer.importTextTemplate(data.selected);
			}
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
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
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request api method, occasional modal destination
	 */
	texttemplate: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["texttemplate", payload, ...request]);
		request.splice(0, 0, "texttemplate");
		let successFn = function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				new Dialog({
					type: "input",
					header: api._lang.GET("texttemplate.navigation.texts"),
					render: data.dialog.render,
					options: data.dialog.options,
				});
			}
			if (data.render) {
				const render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();

				api.preventDataloss.start();
			}
			if (data.data) _client.texttemplate.data = data.data;
			if (data.selected && data.selected.length) {
				Composer.importTextTemplate(data.selected);
			}
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
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
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request api method, occasionally passed values for 2d codes
	 */
	tool: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get") api.history.write(["tool", payload, ...request]);
		request.splice(0, 0, "tool");
		let successFn = function (data) {
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				new Dialog(
					{
						type: "input",
						header: request[2] && request[2] === "table" ? api._lang.GET("tool.markdown.csv_conversion") : api._lang.GET("tool.markdown.playground"),
						render: data.dialog.render,
						options: data.dialog.options,
					},
					"FormData"
				).then((response) => {
					if (response) api.tool("post", response, "markdown", request[2] || null);
				});
			}
			if (data.render) {
				const render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
			}
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
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
	 * @param {string} method get|post|put|patch|delete
	 * @param {null|object|string} payload either pass FormData or a query selector string
	 * @param {array} request api method, name|id
	 */
	user: (method, payload = {}, ...request) => {
		request = [...request];
		if (method === "get" && !["training"].includes(request[0])) api.history.write(["user", payload, ...request]);
		request.splice(0, 0, "user");
		let successFn = function (data) {
			if (data.config) {
				Object.assign(api._settings, data.config);
				if ("language" in data.config) api._lang._USER = data.config.language;
				api.update_user_settings();
			}
			if (data.toast) new Toast(data.toast.msg, data.toast.type);
			if (data.dialog) {
				new Dialog(
					{
						type: "input",
						render: data.dialog.render,
						options: data.dialog.options,
					},
					"FormData"
				).then((response) => {
					if (!response) return;
					if (request[1] === "training") api.user(2 in request && request[2] !== "null" ? "put" : "post", response, "training", 2 in request && request[2] ? request[2] : "null");
				});
			}
			if (data.render) {
				const render = new Assemble(data.render);
				document.getElementById("main").replaceChildren(render.initializeSection());
				render.processAfterInsertion();
			}
			if (data.redirect) api.user("get", null, request[1], data.redirect);
			if (data.title) api.update_header(data.title);
		};
		if (typeof payload === "string") payload = _.getInputs(payload, true);
		api.send(method, request, successFn, null, payload);
	},
};

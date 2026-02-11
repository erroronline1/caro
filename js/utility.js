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

export const _serviceWorker = {
	worker: null,
	permission: null,
	notif: {
		/**
		 * updates styleable data-for=userMenu for calendar menu
		 * @requires api
		 * @param {object} data containing calendar_uncompletedevents
		 * @event sets querySelector attribute data-notification
		 */
		calendar: function (data) {
			let notif,
				tasks = 0,
				worklists = 0;
			if ("calendar_uncompletedtasks" in data) {
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("calendar.navigation.tasks").replace(" ", "_") + "]"); // button
				if (notif) notif.setAttribute("data-notification", data.calendar_uncompletedtasks);
				tasks = parseInt(data.calendar_uncompletedtasks, 10);
			}
			if ("calendar_uncompletedworklists" in data) {
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("calendar.navigation.worklists").replace(" ", "_") + "]"); // button
				if (notif) notif.setAttribute("data-notification", data.calendar_uncompletedworklists);
				worklists = parseInt(data.calendar_uncompletedworklists, 10);
			}
			notif = document.querySelector("[for=userMenu" + api._lang.GET("calendar.navigation.header").replace(" ", "_") + "]"); // main menu label
			if (notif) notif.setAttribute("data-notification", tasks + worklists);
		},

		/**
		 * updates styleable data-for=userMenu for records menu
		 * @requires api
		 * @param {object} data containing document_approval
		 * @event sets querySelector attribute data-notification
		 */
		records: function (data) {
			let notif;
			let document_approval = 0,
				audit_closing = 0,
				managementreview = 0;
			if ("document_approval" in data) {
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("assemble.navigation.manage_approval").replace(" ", "_") + "]"); // button
				if (notif) notif.setAttribute("data-notification", data.document_approval);
				document_approval = parseInt(data.document_approval, 10);
			}
			if ("audit_closing" in data) {
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("audit.navigation.audit").replace(" ", "_") + "]"); // button
				if (notif) notif.setAttribute("data-notification", data.audit_closing);
				audit_closing = parseInt(data.audit_closing, 10);
			}
			if ("managementreview" in data) {
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("audit.navigation.management_review").replace(" ", "_") + "]"); // button
				if (notif) notif.setAttribute("data-notification", data.managementreview);
				managementreview = parseInt(data.managementreview, 10);
			}
			notif = document.querySelector("[for=userMenu" + api._lang.GET("record.navigation.header").replace(" ", "_") + "]"); // main menu label
			if (notif) notif.setAttribute("data-notification", document_approval + audit_closing + managementreview);
		},

		interval: null,
		interval_duration: 300000,
		permission: null,

		/**
		 * updates styleable data-for=userMenu for communication menu
		 * @requires api
		 * @param {object} data containing message_unseen
		 * @event sets querySelector attribute data-notification
		 */
		communication: function (data) {
			let notif,
				message_unseen = 0,
				responsibilities = 0;
			if ("message_unseen" in data || "responsibilities" in data) {
				if ("message_unseen" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("message.navigation.conversations").replace(" ", "_") + "]"); // button
					if (notif) notif.setAttribute("data-notification", data.message_unseen);
					message_unseen = parseInt(data.message_unseen, 10);
				}
				if ("responsibilities" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("responsibility.navigation.responsibility").replace(" ", "_") + "]"); // button
					if (notif) notif.setAttribute("data-notification", data.responsibilities);
					responsibilities = parseInt(data.responsibilities, 10);
				}
				notif = document.querySelector("[for=userMenu" + api._lang.GET("message.navigation.header").replace(" ", "_") + "]"); // main menu label
				if (notif) notif.setAttribute("data-notification", message_unseen + responsibilities);
			}
			if ("measure_unclosed" in data) {
				// no appending number to userMenu to avoid distracting from unread messages
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("measure.navigation.measure").replace(" ", "_") + "]"); // button
				if (notif) notif.setAttribute("data-notification", data.measure_unclosed);
			}
		},

		/**
		 * updates styleable data-for=userMenu for purchase menu
		 * @requires api
		 * @param {object} data containing order_prepared, order_unprocessed, consumables_pendingincorporation
		 * @event sets querySelector attribute data-notification
		 */
		consumables: function (data) {
			let notif,
				order_prepared = 0,
				order_unprocessed = 0,
				consumables_pendingincorporation = 0;
			if ("order_prepared" in data) {
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("order.navigation.prepared_orders").replace(" ", "_") + "]"); // button
				if (notif) notif.setAttribute("data-notification", data.order_prepared);
				order_prepared = parseInt(data.order_prepared, 10);
			}
			if ("order_unprocessed" in data) {
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("order.navigation.approved_orders").replace(" ", "_") + "]"); // button
				if (notif) notif.setAttribute("data-notification", data.order_unprocessed);
				order_unprocessed = parseInt(data.order_unprocessed, 10);
			}
			if ("consumables_pendingincorporation" in data) {
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("consumables.navigation.incorporated_pending").replace(" ", "_") + "]"); // button
				if (notif) notif.setAttribute("data-notification", data.consumables_pendingincorporation);
				consumables_pendingincorporation = parseInt(data.consumables_pendingincorporation, 10);
			}
			notif = document.querySelector("[for=userMenu" + api._lang.GET("consumables.navigation.header").replace(" ", "_") + "]");
			if (notif) notif.setAttribute("data-notification", order_prepared + order_unprocessed + consumables_pendingincorporation); // main menu label
		},

		/**
		 * updates styleable data-for=userMenu for tool menu
		 * @requires api
		 * @param {object} data containing csvfilter_approval
		 * @event sets querySelector attribute data-notification
		 */
		tool: function (data) {
			let notif,
				csvfilter_approval = 0;
			if ("csvfilter_approval" in data) {
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("csvfilter.navigation.filter").replace(" ", "_") + "]"); // button
				if (notif) notif.setAttribute("data-notification", data.csvfilter_approval);
				csvfilter_approval = parseInt(data.csvfilter_approval, 10);
			}
			notif = document.querySelector("[for=userMenu" + api._lang.GET("tool.navigation.header").replace(" ", "_") + "]");
			if (notif) notif.setAttribute("data-notification", csvfilter_approval); // main menu label
		},
	},

	/**
	 * shows notification on new messages and updates data-for data-notification
	 * @requires api
	 * @param {string} message containing message_unnotified and others
	 * @event shows notification and updates notifs data-for data-notification
	 */
	onMessage: function (message) {
		const data = message.data;
		if (!Object.keys(data).length) {
			document.querySelector("header>div:nth-of-type(3)").style.display = "block";
			return;
		}
		document.querySelector("header>div:nth-of-type(3)").style.display = "none";

		if ("message_unnotified" in data && parseInt(data.message_unnotified, 10)) {
			let body =
				data.message_unnotified > 1
					? api._lang.GET("message.message.new_messages", {
							":amount": data.message_unnotified,
					  })
					: api._lang.GET("message.message.new_message");
			this.showLocalNotification(api._lang.GET("message.navigation.header"), body);
		}
		if ("cron" in data && Boolean(data.cron)) {
			this.showLocalNotification("CRON", data.cron);
		}
		this.notif.communication(data);
		this.notif.consumables(data);
		this.notif.calendar(data);
		this.notif.records(data);
		this.notif.tool(data);
	},

	/**
	 * disables submit buttons if post and put queries have to be stored due to connectivity loss
	 * @event disables submit buttons
	 */
	onPostCache: function () {
		const buttons = document.querySelectorAll("[type=submit]");
		for (const element of buttons) {
			element.disabled = true;
		}
	},

	/**
	 * triggers serviceWorkers message event listener - possible future use
	 * @param {string} message serviceWorker request
	 *
	 */
	postMessage: function (message) {
		this.worker.active.postMessage(message);
	},

	/**
	 * register the serviceWorker
	 * @event serviceWorker registration
	 */
	register: async function () {
		if ("serviceWorker" in navigator) {
			this.worker = await navigator.serviceWorker.register("./service-worker.js");
			if (document.querySelector('html[data-useragent*="safari"]') === undefined && window.Notification) {
				this.permission = window.Notification.requestPermission();
			} else {
				// safari sucks
			}
			navigator.serviceWorker.ready.then((registration) => {
				if (registration && !_serviceWorker.notif.interval) {
					_serviceWorker.notif.interval = setInterval(() => {
						_serviceWorker.postMessage("getnotifications");
					}, _serviceWorker.notif.interval_duration);
				}
				navigator.serviceWorker.addEventListener("message", (message) => {
					this.onMessage(message);
				});
			});
		} else throw new Error("No Service Worker support!");
	},

	/**
	 * show system notification
	 * @param {string} title
	 * @param {string} body
	 * @event show system notification
	 */
	showLocalNotification: function (title, body) {
		const options = {
			body: body,
			icon: "./media/favicon/android/android-launchericon-192-192.png",
			// here you can add more properties like icon, image, vibrate, etc.
		};
		if (document.querySelector('html[data-useragent*="safari"]'))
			// fallback
			new Toast(body, "info");
		else
			this.worker.showNotification(title, options).catch((e) => {
				// fallback
				new Toast(body, "info");
			});
	},
};

export const _client = {
	// import due to calling from inline events prerendered by backend api into this object being in global scope anyway
	Dialog: Dialog,
	Toast: Toast,

	application: {
		/**
		 * hide menu on touch event outside of menu
		 * @param {event} event => void
		 */
		clearMenu: (event) => {
			const inputs = document.getElementsByName("userMenu");
			for (const input of inputs) {
				input.checked = false;
			}
		},

		/**
		 * closes a dialog if triggered from within on any child node events as this
		 * @param {event} event
		 */
		closeParentDialog: (element) => {
			do {
				if (element.localName === "dialog") {
					element.close();
					element.remove();
					return;
				}
				element = element.parentNode;
			} while (element && element.localname !== "html");
		},

		/**
		 * output for debugging purpose if not forbidden
		 */
		debug: (...$vars) => {
			if (api._settings.config.application && api._settings.config.application.debugging) console.trace(...$vars);
			else console.log("there may have been an error, however debug mode has been turned off.");
		},

		/**
		 * requests a pdf identifier label from backend
		 * @requires api
		 * @param {string} value
		 * @param {bool} appendDate
		 * @param {object} othervalues {_type: string} for label size options passed from server-CONFIG
		 * @event post request
		 */
		postLabelSheet: (value, appendDate = null, othervalues = {}) => {
			const formdata = new FormData();
			formdata.append(api._lang.GET("record.create_identifier"), value);
			for (const [key, val] of Object.entries(othervalues)) {
				formdata.append(key, val);
			}
			api.record("post", "identifier", appendDate, formdata);
		},

		/**
		 * copies value of inputs or content of paragraphs to clipboard
		 * @requires api, Toast
		 * @param {domNode} node
		 * @event navigator.clipboard.writeText()
		 */
		toClipboard: async (node) => {
			let value = node; // passed string by default
			if (["HTMLInputElement", "HTMLTextAreaElement"].includes(node.constructor.name)) {
				node.select();
				node.setSelectionRange(0, 99999); // For mobile devices
				value = node.value;
				node.selectionStart = node.selectionEnd;
			}
			navigator.clipboard
				.writeText(value)
				.then(() => {
					new Toast(api._lang.GET("general.copied_to_clipboard"), "info");
				})
				.catch(() => {
					new Toast(api._lang.GET("general.copied_to_clipboard_error"), "error");
				});
		},

		/**
		 * toggles application to fullscreen
		 * @event fullscreen toggle
		 * @event icon update
		 */
		toggleFullScreen: () => {
			let image,
				imageelement = document.querySelector("header>div:nth-of-type(1)");
			if (!document.fullscreenElement) {
				document.documentElement.requestFullscreen();
				image = "url('./media/compress.svg')";
			} else if (document.exitFullscreen) {
				document.exitFullscreen();
				image = "url('./media/expand.svg')";
			}
			imageelement.style["-webkit-mask-image"] = imageelement.style["mask-image"] = image;
		},
	},
	audit: {
		/**
		 * enables the closing button if all fields are filled
		 */
		managementreview: () => {
			let input,
				clear = true,
				complete = document.getElementsByName(api._lang._USER.audit.managementreview.close);
			for (const [key, issue] of Object.entries(api._lang._USER.audit.managementreview.required)) {
				input = document.getElementsByName(issue);
				if (input && input[0] && !input[0].value) {
					clear = false;
					break;
				}
			}
			if (complete && complete[0]) {
				if (clear) complete[0].removeAttribute("disabled");
				else complete[0].setAttribute("disabled", true);
			}
		},
	},
	calendar: {
		/**
		 * toggles inputs visibility on conditional request
		 * @param {string} inputs json stringified {'input name' : {required: bool, display: bool}
		 * @param {bool} display
		 */
		setFieldVisibilityByNames: (inputs = "", display = true) => {
			inputs = JSON.parse(inputs);
			let fields;
			for (const [name, attributes] of Object.entries(inputs)) {
				fields = document.getElementsByName(name);
				for (const [id, field] of Object.entries(fields)) {
					field.parentNode.style.display = display === attributes.display ? "initial" : "none";
					if (attributes.required) {
						if (display) field.setAttribute("required", true);
						else field.removeAttribute("required");
					}
					if (!field.value) {
						switch (field.type) {
							case "number":
								field.value = 0;
								break;
							case "text":
								field.value = "";
								break;
							case "time":
								field.value = "00:00";
								break;
						}
					}
				}
			}
		},

		/**
		 * converts painted longtermplanning into formdata
		 */
		longtermplanning: () => {
			let names = document.getElementsByName("_schedule[]"),
				name = document.querySelector("[data-type=longtermplanning_timeline"),
				schedules = document.querySelectorAll("div.schedule"),
				colors = document.querySelectorAll("input[type=color]"),
				id = document.getElementsByName("_longtermid"),
				closed = document.getElementById("_longtermclosed"),
				schedule,
				headers,
				result = { name: "", preset: {}, content: {} };
			if (!names || !schedules || !name) return false;
			result.name = name.textContent;
			for (let i = 0; i < names.length; i++) {
				if (!names[i].value) continue;
				result.content[names[i].value] = {};
				schedule = [];
				headers = [];
				for (let c = 0; c < schedules[i].childNodes.length; c++) {
					if (schedules[i].childNodes[c].localName === "div") {
						if (schedules[i].childNodes[c].style.backgroundColor && schedules[i].childNodes[c].style.backgroundColor !== "inherit") schedule.push(schedules[i].childNodes[c].style.backgroundColor);
						else schedule.push(null);
					}
					if (schedules[i].childNodes[c].localName === "label") {
						if (schedules[i].childNodes[c].childNodes.length) headers.push(schedules[i].childNodes[c].childNodes[0].textContent);
						else headers.push(`_${c}`);
					}
				}
				for (let h = 0; h < headers.length; h++) {
					result.content[names[i].value][headers[h]] = schedule[h];
				}
			}
			for (let c = 0; c < colors.length; c++) {
				result.preset[colors[c].name] = colors[c].value;
			}

			let response = new FormData();
			response.append("name", result.name);
			response.append("content", JSON.stringify(result.content));
			response.append("preset", JSON.stringify(result.preset));
			response.append("id", id && id[0] ? id[0].value : 0);
			response.append("closed", closed && closed.checked ? 1 : 0);
			return response;
		},
	},
	message: {
		/**
		 * returns a message modal dialog
		 * @requires api, Dialog
		 * @param {string} dialogheader optional
		 * @param {string} recipient optional preset
		 * @param {string} message optional preset
		 * @param {object} options send, abort
		 * @param {string, array} datalist possible recipients
		 * @event Dialog and eventually resolved post request
		 */
		newMessage: (dialogheader = "", recipient = "", message = "", options = {}, datalist = []) => {
			if (!Object.keys(options)) {
				options[api._lang.GET("order.add_information_cancel")] = false;
				options[api._lang.GET("order.message_to_orderer")] = { value: true, class: "reducedCTA" };
			}

			// add required fields
			const body = [
				{
					type: "hidden",
					attributes: {
						name: api._lang.GET("message.message.to"),
						value: recipient,
					},
				},
				{
					type: "textarea",
					attributes: {
						name: api._lang.GET("message.message.message"),
						rows: 8,
						value: message,
					},
				},
			];

			// add datalist and set recipient type to text instead of hidden
			if (datalist.length) {
				if (typeof datalist === "string") datalist = datalist.split(",");
				body[0].type = "text";
				body[0].datalist = datalist;
			}

			// fire dialog, resolve with post request
			new Dialog(
				{
					type: "input",
					header: dialogheader,
					render: body,
					options: options,
				},
				"FormData"
			).then((response) => {
				if (response) {
					api.message("post", "message", response);
				}
			});
		},
	},
	order: {
		/**
		 * insert nodes with product data
		 * order to be taken into account in consumables.php "productsearch" method as well!
		 * cart-content has a twin within order.php "order"-get method
		 * @requires api, Assemble
		 * @param  {...any} data
		 * @event insert domNodes
		 */
		addProduct: (...data) => {
			if ([...data].length < 6) data = ["", ...data];
			else data = [...data];
			const autidem = {};
			autidem[api._lang.GET("order.aut_idem")] = [];
			const nodes = document.querySelectorAll("main>form>article"),
				cart = {
					content: [
						[
							{
								type: "number",
								attributes: {
									name: api._lang.GET("order.quantity_label") + "[]",
									value: data[0],
									min: "1",
									max: "99999",
									required: true,
									"data-loss": "prevent",
								},
							},
							{
								type: "textsection",
								attributes: {
									name: api._lang.GET("order.added_product", {
										":unit": data[1],
										":number": data[2],
										":name": data[3],
										":vendor": data[5],
									}),
								},
							},
							{
								type: "hidden",
								attributes: {
									name: api._lang.GET("order.unit_label") + "[]",
									value: data[1] ? data[1] : " ",
								},
							},
							{
								type: "hidden",
								attributes: {
									name: api._lang.GET("order.ordernumber_label") + "[]",
									value: data[2],
								},
							},
							{
								type: "hidden",
								attributes: {
									name: api._lang.GET("order.productname_label") + "[]",
									value: data[3],
								},
							},
							{
								type: "hidden",
								attributes: {
									name: api._lang.GET("order.barcode_label") + "[]",
									value: data[4] ? data[4] : " ",
								},
							},
							{
								type: "hidden",
								attributes: {
									name: api._lang.GET("order.vendor_label") + "[]",
									value: data[5],
								},
							},
							{
								type: "checkbox",
								inline: true,
								attributes: {
									name: api._lang.GET("order.aut_idem") + "[]",
								},
								content: autidem,
							},
							{
								type: "deletebutton",
								attributes: {
									value: api._lang.GET("order.add_delete"),
									onclick: "this.parentNode.remove()",
								},
							},
						],
					],
				};
			//new Assemble(cart).initializeSection(nodes[nodes.length - 3]); // always append on bottom of article list
			new Assemble(cart).initializeSection(nodes[1]); // always append on top of article list
			// remove open dialog if present, e.g. manual order
			if (document.querySelector('dialog[open]')) document.querySelector('dialog[open]').remove();

			new Toast(api._lang.GET("order.added_confirmation", { ":name": data[3] }), "info");
		},

		/**
		 * read filter options and return input values to create filter options payload
		 * called from api.js on get approved requests
		 * but set here near the form generation
		 */
		approvedFilter: () => {
			const filter = {};
			if (document.getElementById("filterterm")) filter.term = document.getElementById("filterterm").value;
			if (document.getElementById("timespan")) filter.timespan = document.getElementById("timespan").value;
			if (document.querySelectorAll("[data-grouped='" + api._lang.GET("order.order_filter_etc") + "']:checked").length) {
				filter.etc = [];
				[...document.querySelectorAll("[data-grouped='" + api._lang.GET("order.order_filter_etc") + "']:checked")].forEach((node) => {
					filter.etc.push(node.value);
				});
				filter.etc = filter.etc.join("|");
			}
			if (document.querySelectorAll("[data-grouped='" + api._lang.GET("order.organizational_unit") + "']:checked").length) {
				filter.unit = [];
				[...document.querySelectorAll("[data-grouped='" + api._lang.GET("order.organizational_unit") + "']:checked")].forEach((node) => {
					filter.unit.push(node.value);
				});
				filter.unit = filter.unit.join("|");
			}
			return filter;
		},

		/**
		 * render approved orders on the client side to reduce payload for transmission from the server side
		 * @requires api, _client Dialog, Assemble
		 * @param {object} data containing data-array, approval-array
		 * @event inserts domNodes
		 */
		approved: (data = undefined) => {
			if (!data) return;
			let content = [],
				orderstate = {},
				groupby = {};

			// construct filter checkboxes with events
			orderstate[api._lang.GET("order.order.unprocessed")] = { onchange: 'api.purchase("get", "approved", null, "unprocessed")', value: "unprocessed" };
			orderstate[api._lang.GET("order.order.ordered")] = { onchange: 'api.purchase("get", "approved", null, "ordered")', value: "ordered" };
			orderstate[api._lang.GET("order.order.delivered_partially")] = { onchange: 'api.purchase("get", "approved",null, "delivered_partially")', value: "delivered_partially" };
			orderstate[api._lang.GET("order.order.delivered_full")] = { onchange: 'api.purchase("get", "approved", null, "delivered_full")', value: "delivered_full" };
			orderstate[api._lang.GET("order.order.issued_partially")] = { onchange: 'api.purchase("get", "approved", null,"issued_partially")', value: "issued_partially" };
			orderstate[api._lang.GET("order.order.issued_full")] = { onchange: 'api.purchase("get", "approved", null, "issued_full")', value: "issued_full" };
			orderstate[api._lang.GET("order.order.archived")] = { onchange: 'api.purchase("get", "approved", null, "archived")', value: "archived" };
			orderstate[api._lang.GET("order.order." + data.state)].checked = true;

			content.push([
				{
					type: "radio",
					attributes: {
						name: api._lang.GET("order.order_filter"),
					},
					content: orderstate,
				},
				{
					type: "scanner",
					attributes: {
						name: api._lang.GET("order.order_filter_label"),
						id: "filterterm",
						value: data.filter.term ? data.filter.term : "",
					},
				},
			]);
			if (data.stockfilter) {
				// construct unit selection
				// limiting to units may speed up rendering for purchase members with this permission if necessary
				const organizational_units = {};
				for (const [key, value] of Object.entries(api._lang._USER.units)) {
					organizational_units[value] = {
						value: key,
					};
					if (data.filter.unit && data.filter.unit.includes(key)) organizational_units[value].checked = true;
				}

				// construct other filters
				const etc = {};
				for (const [key, value] of Object.entries({
					stock: api._lang.GET("order.stock_filter"),
					stock_none: api._lang.GET("order.stock_filter_none"),
					returns: api._lang.GET("order.return_filter"),
					returns_none: api._lang.GET("order.return_filter_none"),
				})) {
					etc[value] = {
						value: key,
					};
					if (data.filter.etc && data.filter.etc.includes(key)) etc[value].checked = true;
				}

				content[content.length - 1].push(
					{
						type: "checkbox",
						attributes: {
							name: api._lang.GET("order.organizational_unit"),
						},
						content: organizational_units,
					},
					{
						type: "checkbox",
						attributes: {
							name: api._lang.GET("order.order_filter_etc"),
						},
						content: etc,
					},
					{
						type: "datetime_local",
						attributes: {
							name: api._lang.GET("order.order_filter_datetime"),
							id: "timespan",
							value: data.filter.timespan || "",
						},
					},
					{
						type: "button",
						attributes: {
							value: api._lang.GET("order.export"),
							class: "inlinebutton",
							onclick: 'api.purchase("get", "export", "null", "' + data.state + '")',
						},
					}
				);
			}
			content[content.length - 1].push({
				type: "button",
				attributes: {
					value: api._lang.GET("order.refresh"),
					class: "inlinebutton",
					onclick: 'api.purchase("get", "approved", null, "' + data.state + '")',
				},
			});

			if (api._settings.user.app_settings.orderLayout) {
				switch (api._settings.user.app_settings.orderLayout) {
					// in case other options may become implemented also see user.php profile
					case "table":
						for (const [key, lang] of Object.entries({
							commission: "commission",
							vendor: "vendor_label",
							organizationalunit: "organizational_unit",
						})) {
							groupby[api._lang.GET("order." + lang)] = api._settings.session.orderTilesGroupBy === key ? { selected: true, value: key } : { value: key };
						}
						content[content.length - 1].push(
							{ type: "br" },
							{
								type: "select",
								attributes: {
									name: api._lang.GET("order.tile_view_groupby"),
									onchange: "api._settings.session.orderTilesGroupBy = this.value; document.querySelector('[name=\"" + api._lang.GET("order.order_filter") + "\"]:checked').dispatchEvent(new Event('change'))",
								},
								content: groupby,
							}
						);

						content.push(..._client.order.table(data));
						break;
					case "tile":
						for (const [key, lang] of Object.entries({
							commission: "commission",
							vendor: "vendor_label",
							organizationalunit: "organizational_unit",
						})) {
							groupby[api._lang.GET("order." + lang)] = api._settings.session.orderTilesGroupBy === key ? { selected: true, value: key } : { value: key };
						}
						content[content.length - 1].push(
							{ type: "br" },
							{
								type: "select",
								attributes: {
									name: api._lang.GET("order.tile_view_groupby"),
									onchange: "api._settings.session.orderTilesGroupBy = this.value; document.querySelector('[name=\"" + api._lang.GET("order.order_filter") + "\"]:checked').dispatchEvent(new Event('change'))",
								},
								content: groupby,
							}
						);

						content.push(..._client.order.tiles(data));
						break;
					default:
						content.push(..._client.order.full(data));
				}
			} else content.push(..._client.order.full(data));

			const render = new Assemble({ content: content });
			document.getElementById("main").replaceChildren(render.initializeSection());
			render.processAfterInsertion();
		},
		batchStateUpdate: (stateinput) => {
			const marked = document.querySelectorAll('[name^="' + api._lang.GET("order.tile_view_mark") + '"'), // include numeration
				// gather marked orders
				orders = [];
			for (const mark of marked) {
				if (mark.checked) orders.push(mark.value);
			}
			// set all available state inputs the same
			const inputs = document.querySelectorAll('[data-grouped="' + api._lang.GET("order.bulk_update_state") + '"');
			for (const input of inputs) {
				if (input.value === stateinput.value) input.checked = stateinput.checked;
			}
			api.purchase("patch", "approved", orders.join("_"), stateinput.value, stateinput.checked);
		},
		full: (data, preventcollapsible = undefined) => {
			// displays full fledged information of every item as article
			let content = [],
				order = [],
				collapsible = [],
				productaction = [],
				buttons = {},
				links = {},
				labels = [];

			// iterate over orders and construct Assemble syntax
			for (const element of data.order) {
				// reinstatiate
				collapsible = [];
				// sanitize null data for proper output
				["unit", "ordernumber", "name", "vendor"].forEach((e) => {
					if (!element[e]) element[e] = "";
				});

				// append ordertext
				collapsible.push({
					type: "textsection",
					content:
						api._lang.GET("order.prepared_order_item", {
							":quantity": element.quantity ? element.quantity : "",
							":unit": element.unit ? element.unit : "",
							":number": element.ordernumber ? element.ordernumber : "",
							":name": element.name ? element.name : "",
							":vendor": element.vendor ? element.vendor : "",
							":aut_idem": element.aut_idem ? element.aut_idem : "",
						}) + (element.ordertext ? "\n" + element.ordertext : ""),
					attributes: {
						name: api._lang.GET("order.ordertype." + element.ordertype),
						"data-type": element.ordertype,
						class: "imagealigned",
					},
				});

				// display approval signature, reuse approval signatures if applicable from frugal provided approval array
				if (element.approval != null)
					collapsible.push({
						type: "image",
						attributes: {
							imageonly: {},
							name: api._lang.GET("order.approval_image"),
							url: data.approval[element.approval],
							class: "order2dcode",
						},
					});

				collapsible.push({
					type: "br",
				});

				// append thirdparty_order information
				if (element.thirdparty_order)
					collapsible.push({
						type: "textsection",
						attributes: {
							name: api._lang.GET("consumables.product.thirdparty_order"),
							class: "orange",
						},
					});

				// append orderer and message option
				if (element.orderer) {
					buttons = {};
					buttons[api._lang.GET("order.add_information_cancel")] = false;
					buttons[api._lang.GET("order.message_to_orderer")] = { value: true, class: "reducedCTA" };
					links = {};
					links[api._lang.GET("order.message_orderer", { ":orderer": element.orderer.name })] = {
						href: "javascript:void(0)",
						"data-type": "input",
						class: "messageto",
						style: "--icon: url('" + (element.orderer.image || "") + "')",
						onclick: function () {
							_client.message.newMessage(
								api._lang.GET("order.message_orderer", { ":orderer": "element.orderer" }),
								"element.orderer",
								api._lang
									.GET("order.message", {
										":quantity": "element.quantity",
										":unit": "element.unit",
										":number": "element.ordernumber",
										":name": "element.name",
										":vendor": "element.vendor",
										":info": "element.information" || "",
										":commission": "element.commission",
										":aut_idem": "element.aut_idem",
									})
									.replace("\\n", "\n"),
								buttons
							);
						}
							.toString()
							._replaceArray(
								["element.orderer", "element.quantity", "element.unit", "element.ordernumber", "element.name", "element.vendor", "element.information", "element.commission", "element.aut_idem", "buttons"],
								[
									element.orderer.name,
									element.quantity,
									element.unit ? element.unit.replaceAll('"', '\\"') : "",
									element.ordernumber ? element.ordernumber.replaceAll('"', '\\"') : "",
									element.name ? element.name.replaceAll('"', '\\"') : "",
									element.vendor ? element.vendor.replaceAll('"', '\\"') : "",
									element.information ? element.information.replaceAll('"', '\\"') : "",
									element.commission ? element.commission.replaceAll('"', '\\"') : "",
									element.aut_idem ? element.aut_idem.replaceAll('"', '\\"') : "",
									JSON.stringify(buttons),
								]
							),
					};
					collapsible.push({
						type: "links",
						content: links,
					});
				}

				// append commission
				if (element.commission) {
					buttons = {};
					buttons[api._lang.GET("general.ok_button")] = true;
					labels = [];
					for (const [label, setting] of Object.entries(api._settings.config.label)) {
						labels.push({
							type: "button",
							attributes: {
								value: api._lang.GET("record.create_identifier_type", { ":format": setting.format }),
								onclick: `_client.application.postLabelSheet('element.commission', null, {_type: '${label}'});`,
							},
						});
					}
					// copy- and label-option
					collapsible.push({
						type: "text_copy",
						numeration: "prevent",
						attributes: {
							value: element.commission,
							name: api._lang.GET("order.commission"),
							readonly: true,
							class: "imagealigned",
							// _client.dialog for scope of stringified function is set to window, where Dialog is not directly accessible
							onclick: function () {
								new _client.Dialog({
									type: "input",
									header: api._lang.GET("order.commission"),
									render: [
										[
											{
												type: "text",
												attributes: {
													value: "element.commission",
													name: api._lang.GET("order.commission"),
													readonly: true,
													onclick: "_client.application.toClipboard(this)",
												},
												hint: api._lang.GET("order.copy_value"),
											},
											...labels,
										],
									],
									options: buttons,
								});
							}
								.toString()
								._replaceArray(["labels", "element.commission", "buttons"], [JSON.stringify(labels), element.commission.replaceAll('"', '\\"'), JSON.stringify(buttons)]),
						},
						hint: api._lang.GET("order.copy_or_labelsheet"),
					});

					collapsible.push({
						type: "br",
					});
				}

				// copy- and label-option
				if (element.administrative_mark) {
					collapsible.push({
						type: "text_copy",
						numeration: "prevent",
						attributes: {
							value: element.administrative_mark,
							name: api._lang.GET("order.administrative_mark"),
							readonly: true,
							class: "imagealigned",
							onclick: "_client.application.toClipboard(this)",
						},
						hint: api._lang.GET("order.copy_value"),
					});

					// display qrcode
					collapsible.push({
						type: "image",
						attributes: {
							imageonly: {},
							qrcode: element.commission,
							class: "order2dcode",
							name: api._lang.GET("order.commission"),
						},
					});

					collapsible.push({
						type: "br",
					});
				}

				// append order number
				if (element.ordernumber) {
					buttons = {};
					buttons[api._lang.GET("general.ok_button")] = true;
					labels = [];

					// construct label options
					if (api._settings.user.permissions.orderprocessing && element.state.ordered && element.state.ordered["data-ordered"] === "true") {
						labels = [
							{
								type: "hidden",
								attributes: {
									id: "_vendor",
									value: "element.vendor",
								},
							},
							{
								type: "hidden",
								attributes: {
									id: "_name",
									value: "element.name",
								},
							},
							{
								type: "hidden",
								attributes: {
									id: "_commission",
									value: "element.commission",
								},
							},
							{
								type: "text",
								attributes: {
									name: api._lang.GET("order.trace_label"),
									id: "_trace",
								},
								hint: api._lang.GET("order.trace_label_hint"),
							},
						];
						for (const [label, setting] of Object.entries(api._settings.config.label)) {
							// delivery date is not passed to label because it is not possible to determine if the label is for partial or remaining deliveries
							labels.push({
								type: "button",
								attributes: {
									value: api._lang.GET("record.create_identifier_type", { ":format": setting.format }),
									onclick:
										`let _ordernumber = document.getElementById('_ordernumber').value, _name = document.getElementById('_name').value, _commission = document.getElementById('_commission').value, _vendor = document.getElementById('_vendor').value, _trace = document.getElementById('_trace').value;` +
										` _client.application.postLabelSheet(_ordernumber + ' - ' + _name.substring(0, 64) + ' - ' + _vendor + ' - ' + _commission + ' - ' + _trace, true, {_type: '${label}'});`,
								},
							});
						}
					}

					// copy- and label-option
					collapsible.push({
						type: "text_copy",
						numeration: "prevent",
						attributes: {
							value: element.ordernumber,
							name: api._lang.GET("order.ordernumber_label"),
							readonly: true,
							class: "imagealigned",
							// _client.dialog for scope of stringified function is set to window, where Dialog is not directly accessible
							onclick: function () {
								if (!labels.length) {
									_client.application.toClipboard(this);
									return;
								}
								new _client.Dialog({
									type: "input",
									header: api._lang.GET("order.ordernumber_label"),
									render: [
										[
											{
												type: "text",
												attributes: {
													value: "element.ordernumber",
													name: api._lang.GET("order.ordernumber_label"),
													id: "_ordernumber",
													readonly: true,
													onclick: "_client.application.toClipboard(this)",
												},
												hint: api._lang.GET("order.copy_value"),
											},
											...labels,
										],
									],
									options: buttons,
								});
							}
								.toString()
								._replaceArray(
									["labels", "element.ordernumber", "element.name", "element.commission", "element.vendor", "buttons"],
									[JSON.stringify(labels), element.ordernumber.replaceAll('"', '\\"'), element.name.replaceAll('"', '\\"'), element.commission.replaceAll('"', '\\"'), element.vendor, JSON.stringify(buttons)]
								),
						},
						hint: api._settings.user.permissions.orderprocessing && element.state.ordered && element.state.ordered["data-ordered"] === "true" ? api._lang.GET("order.copy_or_labelsheet") : api._lang.GET("order.copy_value"),
					});

					// display barcode or qr-code
					if (element.barcode && api._settings.config.application.order_gtin_barcode)
						collapsible.push({
							type: "image",
							attributes: {
								imageonly: {},
								barcode: { value: element.barcode },
								class: "order2dcode",
								name: api._lang.GET("order.ordernumber_label"),
							},
						});
					else
						collapsible.push({
							type: "image",
							attributes: {
								imageonly: {},
								qrcode: element.ordernumber,
								class: "order2dcode",
								name: api._lang.GET("order.ordernumber_label"),
							},
						});

					collapsible.push({
						type: "br",
					});
				}

				// append order identifier
				if (element.identifier) {
					buttons = {};
					buttons[api._lang.GET("general.ok_button")] = true;
					// copy-option
					collapsible.push({
						type: "text_copy",
						numeration: "prevent",
						attributes: {
							value: element.identifier,
							name: api._lang.GET("order.identifier"),
							readonly: true,
							onclick: "_client.application.toClipboard(this)",
							class: "imagealigned",
						},
						hint: api._lang.GET("order.identifier_hint") + " " + api._lang.GET("order.copy_value"),
					});

					// display qrcode
					collapsible.push({
						type: "image",
						attributes: {
							imageonly: {},
							qrcode: element.commission,
							class: "order2dcode",
							name: api._lang.GET("order.commission"),
						},
					});
				}

				// append information
				if (element.information) {
					collapsible.push(
						{
							type: "br",
						},
						{
							type: "textarea",
							attributes: {
								value: element.information,
								name: api._lang.GET("order.additional_info"),
								readonly: true,
							},
							numeration: "prevent",
						}
					);
				}

				// append add info button
				if (element.addinformation) {
					buttons = {};
					buttons[api._lang.GET("order.add_information_cancel")] = false;
					buttons[api._lang.GET("order.add_information_ok")] = { value: true, class: "reducedCTA" };
					collapsible.push({
						type: "button",
						attributes: {
							value: api._lang.GET("order.add_information"),
							// _client.dialog for scope of stringified function is set to window, where Dialog is not directly accessible
							onclick: function () {
								new _client.Dialog(
									{
										type: "input",
										header: api._lang.GET("order.add_information"),
										render: [
											{
												type: "textarea",
												attributes: {
													name: api._lang.GET("order.additional_info"),
												},
												hint: api._lang.GET("order.add_information_modal_body"),
											},
										],
										options: buttons,
									},
									"FormData"
								).then((response) => {
									if (response) api.purchase("patch", "approved", "element.id", "addinformation", response);
								});
							}
								.toString()
								._replaceArray(["element.id", "buttons"], [element.id, JSON.stringify(buttons)]),
						},
						hint: element.lastorder ? element.lastorder : null,
					});
				}

				// append special attention information
				if (element.specialattention)
					collapsible.push({
						type: "textsection",
						attributes: {
							name: api._lang.GET("consumables.product.special_attention"),
							class: "orange",
						},
					});

				// append attachments
				if (element.attachments) {
					collapsible.push({
						type: "links",
						content: element.attachments,
					});
				}

				// append orderer and message option
				if (element.purchasemembers) {
					buttons = {};
					buttons[api._lang.GET("order.add_information_cancel")] = false;
					buttons[api._lang.GET("order.message_to_orderer")] = { value: true, class: "reducedCTA" };
					links = {};
					links[api._lang.GET("order.message_orderer", { ":orderer": api._lang.GET("permissions.purchase") })] = {
						href: "javascript:void(0)",
						"data-type": "input",
						class: "messageto",
						onclick: function () {
							_client.message.newMessage(
								api._lang.GET("order.message_orderer", { ":orderer": api._lang.GET("permissions.purchase") }),
								"element.purchasemembers",
								api._lang
									.GET("order.message", {
										":quantity": "element.quantity",
										":unit": "element.unit",
										":number": "element.ordernumber",
										":name": "element.name",
										":vendor": "element.vendor",
										":info": "element.information" || "",
										":commission": "element.commission",
										":aut_idem": "element.aut_idem",
									})
									.replace("\\n", "\n") +
									"element.ordertext".replace("\\n", "\n") +
									"element.identifier".replace("\\n", "\n"),
								buttons
							);
						}
							.toString()
							._replaceArray(
								[
									"element.purchasemembers",
									"element.quantity",
									"element.unit",
									"element.ordernumber",
									"element.name",
									"element.vendor",
									"element.information",
									"element.commission",
									"element.aut_idem",
									"element.ordertext",
									"element.identifier",
									"buttons",
								],
								[
									element.purchasemembers.join(","),
									element.quantity,
									element.unit ? element.unit.replaceAll('"', '\\"') : "",
									element.ordernumber ? element.ordernumber.replaceAll('"', '\\"') : "",
									element.name ? element.name.replaceAll('"', '\\"') : "",
									element.vendor ? element.vendor.replaceAll('"', '\\"') : "",
									element.information ? element.information.replaceAll('"', '\\"') : "",
									element.commission ? element.commission.replaceAll('"', '\\"') : "",
									element.aut_idem ? element.aut_idem.replaceAll('"', '\\"') : "",
									element.ordertext ? "\\n" + element.ordertext.replaceAll('"', '\\"') : "",
									element.identifier ? "\\n" + element.identifier.replaceAll('"', '\\"') : "",
									JSON.stringify(buttons),
								]
							),
					};
					collapsible.push({
						type: "links",
						content: links,
					});
				}

				// append states
				// assemble state toggles
				let states = {};
				for (const [state, attributes] of Object.entries(element.state)) {
					states[api._lang.GET("order.order." + state)] = {};
					for (const [attribute, value] of Object.entries(attributes)) states[api._lang.GET("order.order." + state)][attribute] = value;
					if (attributes["data-" + state] === "true") states[api._lang.GET("order.order." + state)].checked = true;
					if (!attributes.disabled && !states[api._lang.GET("order.order." + state)].onchange)
						states[api._lang.GET("order.order." + state)].onchange = "api.purchase('patch', 'approved', '" + element.id + "', '" + state + "', this.checked); this.setAttribute('data-" + state + "', this.checked.toString());";
				}
				// conditional customizing
				if (states[api._lang.GET("order.order.delivered_partially")] && !states[api._lang.GET("order.order.delivered_partially")].disabled) {
					buttons = {};
					buttons[api._lang.GET("order.add_information_cancel")] = false;
					buttons[api._lang.GET("order.add_information_ok")] = { value: true, class: "reducedCTA" };
					// _client.dialog for scope of stringified function is set to window, where Dialog is not directly accessible
					states[api._lang.GET("order.order.delivered_partially")].onchange = function () {
						api.purchase("patch", "approved", "element.id", "delivered_partially", this.checked);
						this.setAttribute("data-delivered_partially", this.checked.toString());
						if (this.checked)
							new _client.Dialog(
								{
									type: "input",
									header: api._lang.GET("order.add_information"),
									render: [
										{
											type: "textarea",
											attributes: {
												name: api._lang.GET("order.additional_info"),
											},
											hint: api._lang.GET("order.add_information_modal_body"),
										},
									],
									options: buttons,
								},
								"FormData"
							).then((response) => {
								if (response) api.purchase("patch", "approved", "element.id", "addinformation", response);
							});
					}
						.toString()
						._replaceArray(["element.id", "buttons"], [element.id, JSON.stringify(buttons)]);
				}
				// conditional customizing
				if (element.disapprove && element.organizationalunit) {
					buttons = {};
					buttons[api._lang.GET("order.disapprove_message_cancel")] = false;
					buttons[api._lang.GET("order.disapprove_message_ok")] = { value: true, class: "reducedCTA" };
					states[api._lang.GET("order.disapprove")] = {
						data_disapproved: "false",
						// _client.dialog for scope of stringified function is set to window, where Dialog is not directly accessible
						onchange: function () {
							new _client.Dialog(
								{
									type: "input",
									header: api._lang.GET("order.disapprove"),
									render: [
										{
											type: "textarea",
											attributes: {
												name: api._lang.GET("message.message.message"),
											},
											hint: api._lang.GET("order.disapprove_message", { ":unit": api._lang.GET("units." + "element.organizationalunit") }),
										},
									],
									options: buttons,
								},
								"FormData"
							).then((response) => {
								if (response !== false) {
									api.purchase("patch", "approved", "element.id", "disapproved", response);
									this.disabled = true;
									this.setAttribute("data-disapproved", "true");
								} else this.checked = false;
							});
						}
							.toString()
							._replaceArray(["element.organizationalunit", "element.id", "buttons"], [element.organizationalunit, element.id, JSON.stringify(buttons)]),
					};
				}
				// conditional customizing
				if (element.cancel) {
					buttons = {};
					buttons[api._lang.GET("order.cancellation_message_cancel")] = false;
					buttons[api._lang.GET("order.cancellation_message_ok")] = { value: true, class: "reducedCTA" };
					states[api._lang.GET("order.cancellation")] = {
						data_cancellation: "false",
						// _client.dialog for scope of stringified function is set to window, where Dialog is not directly accessible
						onchange: function () {
							new _client.Dialog(
								{
									type: "input",
									header: api._lang.GET("order.cancellation"),
									render: [
										{
											type: "textarea",
											attributes: {
												name: api._lang.GET("message.message.message"),
											},
											hint: api._lang.GET("order.cancellation_message"),
										},
									],
									options: buttons,
								},
								"FormData"
							).then((response) => {
								if (response !== false) {
									api.purchase("patch", "approved", "element.id", "cancellation", response);
									this.disabled = true;
									this.setAttribute("data-cancellation", "true");
								} else this.checked = false;
							});
						}
							.toString()
							._replaceArray(["element.id", "buttons"], [element.id, JSON.stringify(buttons)]),
					};
				}
				// conditional customizing
				if (element.return) {
					buttons = {};
					buttons[api._lang.GET("order.return_message_cancel")] = false;
					buttons[api._lang.GET("order.return_message_ok")] = { value: true, class: "reducedCTA" };

					let reasons = {};

					if (api._lang._USER.orderreturns.critical) for (const [key, value] of Object.entries(api._lang._USER.orderreturns.critical)) reasons[value] = {value: key};
					if (api._lang._USER.orderreturns.easy) for (const [key, value] of Object.entries(api._lang._USER.orderreturns.easy)) reasons[value] = {value: key};
					reasons = Object.keys(reasons)
						.sort()
						.reduce((obj, key) => {
							obj[key] = reasons[key];
							return obj;
						}, {});

					states[api._lang.GET("order.return")] = {
						data_return: "false",
						// _client.dialog for scope of stringified function is set to window, where Dialog is not directly accessible
						onchange: function () {
							new _client.Dialog(
								{
									type: "input",
									header: api._lang.GET("order.return"),
									render: [
										{
											type: "textarea",
											attributes: {
												name: api._lang.GET("message.message.message"),
											},
											hint: api._lang.GET("order.return_message"),
										},
										{
											type: "select",
											attributes: {
												name: api._lang.GET("order.return_reason"),
												required: true,
											},
											content: reasons,
										},
									],
									options: buttons,
								},
								"FormData"
							).then((response) => {
								if (response !== false) {
									api.purchase("patch", "approved", "element.id", "return", response);
									this.disabled = true;
									this.setAttribute("data-return", "true");
								} else this.checked = false;
							});
						}
							.toString()
							._replaceArray(["element.id", "buttons", "reasons"], [element.id, JSON.stringify(buttons), JSON.stringify(reasons)]),
					};
				}
				collapsible.push({ type: "br" });
				collapsible.push({ type: "checkbox", content: states });

				// append orderstatechange
				if (element.orderstatechange && Object.keys(element.orderstatechange).length && element.organizationalunit) {
					buttons = {};
					buttons[api._lang.GET("order.add_information_cancel")] = false;
					buttons[api._lang.GET("order.add_information_ok")] = { value: true, class: "reducedCTA" };
					collapsible.push({
						type: "select",
						content: element.orderstatechange,
						numeration: "prevent",
						attributes: {
							name: api._lang.GET("order.orderstate_description"),
							// _client.dialog for scope of stringified function is set to window, where Dialog is not directly accessible
							onchange: function () {
								if (this.value === "...") return false;
								new _client.Dialog(
									{
										type: "input",
										header: api._lang.GET("order.orderstate_description") + " " + this.value,
										render: [
											{
												type: "textarea",
												attributes: {
													name: api._lang.GET("order.additional_info"),
												},
												hint: api._lang.GET("order.disapprove_message", { ":unit": api._lang.GET("units." + "element.organizationalunit") }),
											},
										],
										options: buttons,
									},
									"FormData"
								).then((response) => {
									if (response) {
										response[api._lang.GET("order.additional_info")] = api._lang.GET("order.orderstate_description") + " - " + this.value + ": " + response[api._lang.GET("order.additional_info")];
										api.purchase("patch", "approved", "element.id", "addinformation", response);
									}
								});
							}
								.toString()
								._replaceArray(["element.organizationalunit", "element.id", "buttons"], [element.organizationalunit, element.id, JSON.stringify(buttons)]),
						},
					});
				}

				// append calendar button
				if (element.calendar) {
					collapsible.push({
						type: "calendarbutton",
						attributes: {
							value: api._lang.GET("order.calendarbutton"),
							onclick: element.calendar,
						},
						hint: api._lang.GET("order.calendarbutton_hint"),
					});
				}

				// append delete button
				if (element.delete) {
					buttons = {};
					buttons[api._lang.GET("order.delete_prepared_order_confirm_cancel")] = false;
					buttons[api._lang.GET("order.delete_prepared_order_confirm_ok")] = { value: true, class: "reducedCTA" };
					collapsible.push({
						type: "deletebutton",
						hint: element.autodelete ? element.autodelete : null,
						attributes: {
							value: api._lang.GET("order.delete_prepared_order"),
							// _client.dialog for scope of stringified function is set to window, where Dialog is not directly accessible
							onclick: function () {
								new _client.Dialog({ type: "confirm", header: api._lang.GET("order.delete_prepared_order_confirm_header"), options: buttons }).then((confirmation) => {
									if (confirmation) api.purchase("delete", "approved", "element.id");
								});
							}
								.toString()
								._replaceArray(["element.id", "buttons"], [element.id, JSON.stringify(buttons)]),
						},
					});
					collapsible.push({
						type: "br", // to clear after floating delete button
					});
				}

				// append options to add product to database
				if (element.addproduct) {
					collapsible.push({
						type: "button",
						attributes: {
							value: api._lang.GET("consumables.product.add_new"),
							onclick:
								"api.purchase('get', 'product', '" +
								JSON.stringify({
									article_no: element.ordernumber ? element.ordernumber : "",
									article_name: element.name ? element.name : "",
									article_unit: element.unit ? element.unit : "",
									vendor_name: element.vendor ? element.vendor : "",
								}) +
								"'); _client.application.closeParentDialog(this);",
						},
					});
				}

				// append options to request edit of product
				if (element.editproductrequest instanceof Array) {
					buttons = {};
					buttons[api._lang.GET("order.add_information_cancel")] = false;
					buttons[api._lang.GET("order.message_to_orderer")] = { value: true, class: "reducedCTA" };
					collapsible.push({
						type: "button",
						attributes: {
							class: "inlinebutton",
							value: api._lang.GET("consumables.product.edit_product_request"),
							onclick: function () {
								_client.message.newMessage(
									api._lang.GET("consumables.product.edit_product_request"),
									"purchasemembers",
									api._lang
										.GET("consumables.product.edit_product_request_message", {
											":number": "element.ordernumber",
											":name": "element.name",
											":vendor": "element.vendor",
										})
										.replace("\\n", "\n"),
									buttons
								);
							}
								.toString()
								._replaceArray(
									["purchasemembers", "element.ordernumber", "element.name", "element.vendor", "buttons"],
									[
										element.editproductrequest.join(", "),
										element.ordernumber ? element.ordernumber.replaceAll('"', '\\"') : "",
										element.name ? element.name.replaceAll('"', '\\"') : "",
										element.vendor ? element.vendor.replaceAll('"', '\\"') : "",
										JSON.stringify(buttons),
									]
								),
						},
					});
				}
				if (element.productid) {
					collapsible.push({
						type: "button",
						attributes: {
							class: "inlinebutton",
							value: api._lang.GET("order.productlink"),
							onclick: "api.purchase('get', 'product', " + element.productid + "); _client.application.closeParentDialog(this);",
						},
					});
				}

				productaction = [];
				// append incorporation state if regular order, otherwise out of reach
				if (["order"].includes(element.ordertype) && element.incorporation) {
					if (element.incorporation.item)
						productaction.push({
							type: "button",
							attributes: {
								value: api._lang.GET("order.incorporation.incorporation"),
								onclick: "if (!this.disabled) api.purchase('get', 'incorporation', " + element.incorporation.item + "); this.disabled = true",
							},
						});
					else if (element.incorporation.state)
						productaction.push({
							type: "textsection",
							attributes: {
								name: element.incorporation.state,
							},
						});
				}
				// append sample check state if regular order, otherwise out of reach
				if (["order"].includes(element.ordertype) && element.samplecheck) {
					if (element.samplecheck.item)
						productaction.push({
							type: "button",
							attributes: {
								value: api._lang.GET("order.sample_check.sample_check"),
								onclick: "if (!this.disabled) api.purchase('get', 'mdrsamplecheck', " + element.samplecheck.item + "); this.disabled = true",
							},
						});
					else if (element.samplecheck.state)
						productaction.push({
							type: "textsection",
							attributes: {
								name: element.samplecheck.state,
							},
						});
				}

				// create order container
				if (preventcollapsible) {
					order = [...collapsible];
				} else {
					order = [
						{
							type: "collapsible",
							attributes: {
								class: "em16" + (!element.collapsed ? " extended" : ""),
							},
							content: collapsible,
						},
					];
				}
				if (productaction) order.push(...productaction);

				content.push(order);
			}
			return content;
		},
		table: (data) => {
			// displays compact information as table with event opening information as dialog
			let content = [],
				groups = {},
				groupname = "",
				order = [],
				orderdata = {},
				buttons = {},
				info = "",
				groupby = api._settings.session.orderTilesGroupBy;

			// iterate over orders and construct Assemble syntax
			for (const element of data.order) {
				["unit", "ordernumber", "name", "vendor", "commission"].forEach((e) => {
					if (!element[e]) element[e] = "";
				});
				order = [
					{
						c: api._lang.GET("order.prepared_order_item", {
							":quantity": element.quantity ? element.quantity : "",
							":unit": element.unit ? element.unit : "",
							":number": element.ordernumber ? element.ordernumber : "",
							":name": element.name ? element.name : "",
							":vendor": element.vendor ? element.vendor : "",
							":aut_idem": element.aut_idem ? element.aut_idem : "",
						}),
						a: {
							"data-type": element.ordertype,
						},
					},
					{ c: api._lang.GET("order.ordertype." + element.ordertype) + " " + api._lang.GET("order.tile_view_info", { ":commission": element.commission, ":orderer": element.orderer.name }) },
				];

				orderdata = {
					approval: {},
					order: [element],
				};
				if (data.approval[element.approval]) orderdata.approval[element.approval] = data.approval[element.approval];
				buttons = {};
				buttons[api._lang.GET("general.ok_button")] = false;

				info = [];
				for (const [state, attributes] of Object.entries(element.state)) {
					if (attributes["data-" + state] === "true") info.push(api._lang.GET("order.order." + state));
				}
				// add info on necessary actions if regular order, otherwise out of reach
				if (["order"].includes(element.ordertype) && element.incorporation) info.push(api._lang.GET("order.incorporation.incorporation"));
				if (["order"].includes(element.ordertype) && element.samplecheck) info.push(api._lang.GET("order.sample_check.sample_check"));
				if (!element.id) info.push(api._lang.GET("order.product_not_in_database"));
				info = info.join(", ");

				order.push({
					c: info,
					a: {
						style: "cursor: pointer;",
						class: "cta",
						onclick: function () {
							new _client.Dialog({
								type: "input",
								header: api._lang.GET("order.ordertype." + "element.ordertype"),
								render: _client.order.full(orderdata, true),
								options: buttons,
							});
						}
							.toString()
							._replaceArray(["element.ordertype", "orderdata", "buttons"], [element.ordertype, JSON.stringify(orderdata), JSON.stringify(buttons)]),
					},
				});
				if (!groups[element[groupby]]) groups[element[groupby]] = [];
				groups[element[groupby]].push(order);
			}

			let ordered_groups = Object.keys(groups)
				.sort() // Sort the keys alphabetically
				.reduce((obj, key) => {
					obj[key] = groups[key]; // Rebuild the object with sorted keys
					return obj;
				}, {});

			for (const [key, group] of Object.entries(ordered_groups)) {
				group.unshift([{ c: api._lang.GET("order.table_order") }, { c: api._lang.GET("order.commission") }, { c: api._lang.GET("order.table_info") }]);
				groupname = api._lang._USER.units[key] || key;
				content.push([
					{
						type: "table",
						attributes: {
							name: groupname,
							"data-type": "order",
						},
						content: group,
					},
				]);
				//if (bulk) content.push(bulk);
			}
			return content;
		},
		tiles: (data) => {
			// displays compact information as tiles with event opening information as dialog
			let content = [],
				order = [],
				tile = [],
				tileinfo = [],
				groups = {},
				buttons = {},
				orderdata = {},
				groupby = api._settings.session.orderTilesGroupBy;
			// iterate over orders and construct Assemble syntax
			for (const element of data.order) {
				// reinstatiate
				order = [];
				// sanitize null data for proper output
				["unit", "ordernumber", "name", "vendor"].forEach((e) => {
					if (!element[e]) element[e] = "";
				});

				// append ordertext
				order.push({
					type: "textsection",
					content: api._lang.GET("order.ordertype." + element.ordertype) + "\n" + api._lang.GET("order.tile_view_info", { ":commission": element.commission, ":orderer": element.orderer.name }),
					attributes: {
						name: api._lang.GET("order.prepared_order_item", {
							":quantity": element.quantity ? element.quantity : "",
							":unit": element.unit ? element.unit : "",
							":number": element.ordernumber ? element.ordernumber : "",
							":name": element.name ? element.name : "",
							":vendor": element.vendor ? element.vendor : "",
							":aut_idem": element.aut_idem ? element.aut_idem : "",
						}),
						"data-type": element.ordertype,
					},
				});
				// pass full info for current element into new object
				orderdata = {
					approval: {},
					order: [element],
				};
				if (data.approval[element.approval]) orderdata.approval[element.approval] = data.approval[element.approval];
				buttons = {};
				buttons[api._lang.GET("general.ok_button")] = false;
				order.push({
					type: "button",
					attributes: {
						value: api._lang.GET("order.tile_view_open"),
						class: "inlinebutton",
						onclick: function () {
							new _client.Dialog({
								type: "input",
								header: api._lang.GET("order.ordertype." + "element.ordertype"),
								render: _client.order.full(orderdata, true),
								options: buttons,
							});
						}
							.toString()
							._replaceArray(["element.ordertype", "orderdata", "buttons"], [element.ordertype, JSON.stringify(orderdata), JSON.stringify(buttons)]),
					},
				});

				if (!element.incorporation && !element.samplecheck && !element.hidebatchupdate && element.id) {
					buttons = {};
					buttons[api._lang.GET("order.tile_view_mark")] = { value: element.id };
					order.push({
						type: "checkbox",
						content: buttons,
					});
				} else {
					tileinfo = [];
					// add info on necessary actions if regular order, otherwise out of reach
					if (["order"].includes(element.ordertype) && element.incorporation) tileinfo.push(api._lang.GET("order.incorporation.incorporation"));
					if (["order"].includes(element.ordertype) && element.samplecheck) tileinfo.push(api._lang.GET("order.sample_check.sample_check"));
					if (element.addproduct) tileinfo.push(api._lang.GET("order.product_not_in_database"));
					if (tileinfo.length)
						order.push({
							type: "textsection",
							attributes: {
								name: tileinfo.join(", "),
							},
						});
				}

				tile = {
					type: "tile",
					attributes: {
						tabindex: "0",
					},
					content: order,
				};
				if (!groups[element[groupby]]) groups[element[groupby]] = [];
				groups[element[groupby]].push(tile);
			}

			const inputs = {};
			let bulk;
			if (data.allowedstateupdates && Object.keys(data.allowedstateupdates).length) {
				// this is suddenly resolved as an object with integer keys, not an array
				// may depend on browser, because it worked prior to os update!?
				// so implementing a security fallback
				if (!data.allowedstateupdates.isArray && Object.keys(data.allowedstateupdates).length) data.allowedstateupdates = Object.values(data.allowedstateupdates);

				// order of options
				const possiblestates = ["ordered", "delivered_partially", "delivered_full", "issued_partially", "issued_full", "archived"];
				// remove possible states
				for (let i = 0; i < possiblestates.length; i++) {
					if (data.state === "unprocessed" || data.state === possiblestates[i]) break;
					if (data.state !== possiblestates[i]) delete possiblestates[i];
				}
				for (const input of possiblestates.filter((value) => data.allowedstateupdates.includes(value))) {
					inputs[api._lang.GET("order.order." + input)] = { value: input, onchange: "_client.order.batchStateUpdate(this)" };
					if (input === data.state) inputs[api._lang.GET("order.order." + input)].checked = true;
				}
				bulk = [
					{
						type: "checkbox",
						attributes: {
							name: api._lang.GET("order.bulk_update_state"),
						},
						content: inputs,
					},
				];
			}

			let ordered_groups = Object.keys(groups)
				.sort() // Sort the keys alphabetically
				.reduce((obj, key) => {
					obj[key] = groups[key]; // Rebuild the object with sorted keys
					return obj;
				}, {});

			for (const group of Object.entries(ordered_groups)) {
				content.push(group);
				if (bulk) content.push(bulk);
			}
			return content;
		},
	},
	record: {
		/**
		 * filter records by case state
		 * @param {string} casestate to display, hide all others
		 * @event hiding or displaying records
		 */
		casestatefilter: (casestate) => {
			document.querySelectorAll("article[role]").forEach((anchor) => {
				anchor.style.display = casestate ? "none" : "block";
			});
			if (casestate)
				document.querySelectorAll("[data-" + casestate + "]").forEach((anchor) => {
					anchor.style.display = "block";
				});
		},
		/**
		 * radio selection with preview
		 * @param {array} files array
		 * @returns assemble radio widget
		 */
		filereference: (files) => {
			const content = {},
				previewoptions = {};
			let path, filename;

			previewoptions[api._lang.GET("assemble.render.filereference_decline")] = false;
			previewoptions[api._lang.GET("assemble.render.filereference_select")] = { value: true, class: "reducedCTA" };

			for (const url of files) {
				path = url.split("documents/");
				filename = url.split("/");
				path = path[1] ? path[1] : path[0];

				content[path] = {
					value: path,
					onchange: function () {
						document.getElementById("_selectedfile").value = this.value;
					},
				};
				if (["stl", "STL", "obj", "OBJ"].some((extension) => path.endsWith(extension))) {
					content[path].onclick = function () {
						new _client.Dialog({
							type: "preview",
							header: "filename",
							render: {
								type: "stl",
								name: "stlpath",
								url: "stlurl",
								transfer: true,
							},
							options: previewoptions,
						}).then((response) => {
							if (!response) {
								this.checked = false;
								document.getElementById("_selectedfile").value = "";
							}
						});
					}
						.toString()
						._replaceArray(["filename", "stlpath", "stlurl", "previewoptions"], [filename[filename.length - 1], path, url, JSON.stringify(previewoptions)]);
				}
				if (["png", "PNG", "jpg", "JPG", "jpeg", "JPEG", "gif", "GIF"].some((extension) => path.endsWith(extension))) {
					content[path].onclick = function () {
						new _client.Dialog({
							type: "preview",
							header: "filename",
							render: {
								type: "image",
								name: "imgpath",
								content: "imgurl",
								transfer: true,
							},
							options: previewoptions,
						}).then((response) => {
							if (!response) {
								this.checked = false;
								document.getElementById("_selectedfile").value = "";
							}
						});
					}
						.toString()
						._replaceArray(["filename", "imgpath", "imgurl", "previewoptions"], [filename[filename.length - 1], path, url, JSON.stringify(previewoptions)]);
				}
			}
			return {
				content: [
					{
						type: "radio",
						attributes: {
							name: api._lang.GET("assemble.render.filereference_results"),
						},
						content: content,
					},
				],
			};
		},
	},
	texttemplate: {
		data: null,
		/**
		 * update a texttemplate with provided placeholders and selected chunks
		 * @requires api
		 * @event update output container value
		 */
		update: () => {
			const replacements = {},
				genii = document.getElementsByName(api._lang.GET("texttemplate.use.person")),
				blocks = document.querySelectorAll("[data-usecase=useblocks]"),
				placeholder = document.querySelectorAll("[data-usecase=undefinedplaceholder]");
			let selectedgenus = 0,
				output = "",
				blockcontent;
			for (const [key, value] of Object.entries(_client.texttemplate.data.replacements)) {
				replacements[key] = value.split(/\r\n|\n/);
			}
			for (let i = 0; i < genii.length; i++) {
				if (genii[i].checked) {
					selectedgenus = i;
					break;
				}
			}
			for (const block of blocks) {
				if (block.checked) {
					blockcontent = _client.texttemplate.data.blocks[":" + block.name.replaceAll(/\(.*?\)/g, "")];
					for (const input of placeholder) {
						if (input.value) blockcontent = blockcontent.replaceAll(input.id, input.value);
					}
					for (const [key, replacement] of Object.entries(replacements)) {
						blockcontent = blockcontent.replaceAll(key, replacement[selectedgenus]);
					}
					output += blockcontent;
				}
			}
			document.getElementById("texttemplate").value = output;
		},
		/**
		 * import placeholders for use within texttemplates as selected during use. currently used within consumables.php for transmission of vendor data to purchase specific text recommendations
		 * @param {string} elements json.stringified object with key: value pairs
		 * @event updates inputs with provided element values
		 */
		import: (elements = "") => {
			try {
				elements = JSON.parse(elements);
			} catch (e) {
				return;
			}
			const converted_elements = {};
			// convert element keys to valid placeholder ids
			for (const [key, value] of Object.entries(elements)) {
				converted_elements[key] = value;
			}
			const placeholder = document.querySelectorAll("[data-usecase=undefinedplaceholder]");
			for (const input of placeholder) {
				if (input.id in converted_elements) input.value = document.getElementById(converted_elements[input.id]) ? document.getElementById(converted_elements[input.id]).value : "";
			}
		},
	},
};

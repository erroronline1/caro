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
			let notif;
			if ("calendar_uncompletedevents" in data) {
				notif = document.querySelector("[for=userMenu" + api._lang.GET("menu.calendar.header").replace(" ", "_") + "]"); // main menu label
				if (notif) notif.setAttribute("data-notification", data.calendar_uncompletedevents);
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.calendar.scheduling").replace(" ", "_") + "]"); // button
				if (notif) notif.setAttribute("data-notification", data.calendar_uncompletedevents);
			}
		},

		/**
		 * updates styleable data-for=userMenu for records menu
		 * @requires api
		 * @param {object} data containing document_approval
		 * @event sets querySelector attribute data-notification
		 */
		records: function (data) {
			let notif;
			if ("document_approval" in data || "audit_closing" in data || "managementreview" in data) {
				let document_approval = 0,
					audit_closing = 0,
					managementreview = 0;
				if ("document_approval" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.records.documents_manage_approval").replace(" ", "_") + "]"); // button
					if (notif) notif.setAttribute("data-notification", data.document_approval);
					document_approval = data.document_approval;
				}
				if ("audit_closing" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.records.audit").replace(" ", "_") + "]"); // button
					if (notif) notif.setAttribute("data-notification", data.audit_closing);
					audit_closing = data.audit_closing;
				}
				if ("managementreview" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.records.management_review").replace(" ", "_") + "]"); // button
					if (notif) notif.setAttribute("data-notification", data.managementreview);
					managementreview = data.managementreview;
				}
				notif = document.querySelector("[for=userMenu" + api._lang.GET("menu.records.header").replace(" ", "_") + "]"); // main menu label
				if (notif) notif.setAttribute("data-notification", parseInt(document_approval, 10) + parseInt(audit_closing, 10) + parseInt(managementreview, 10));
			}
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
			let notif;
			if ("message_unseen" in data || "responsibilities" in data) {
				let message_unseen = 0,
					responsibilities = 0;
				if ("message_unseen" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.communication.conversations").replace(" ", "_") + "]"); // button
					if (notif) notif.setAttribute("data-notification", data.message_unseen);
					message_unseen = data.message_unseen;
				}
				if ("responsibilities" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.communication.responsibility").replace(" ", "_") + "]"); // button
					if (notif) notif.setAttribute("data-notification", data.responsibilities);
					responsibilities = data.responsibilities;
				}
				notif = document.querySelector("[for=userMenu" + api._lang.GET("menu.communication.header").replace(" ", "_") + "]"); // main menu label
				if (notif) notif.setAttribute("data-notification", parseInt(message_unseen, 10) + parseInt(responsibilities, 10));
			}
			if ("measure_unclosed" in data) {
				// no appending number to userMenu to avoid distracting from unread messages
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.communication.measure").replace(" ", "_") + "]"); // button
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
			let notif;
			if ("order_prepared" in data || "order_unprocessed" in data || "consumables_pendingincorporation" in data) {
				let order_prepared = 0,
					order_unprocessed = 0,
					consumables_pendingincorporation = 0;
				if ("order_prepared" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.purchase.prepared_orders").replace(" ", "_") + "]"); // button
					if (notif) notif.setAttribute("data-notification", data.order_prepared);
					order_prepared = data.order_prepared;
				}
				if ("order_unprocessed" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.purchase.approved_orders").replace(" ", "_") + "]"); // button
					if (notif) notif.setAttribute("data-notification", data.order_unprocessed);
					order_unprocessed = data.order_unprocessed;
				}
				if ("consumables_pendingincorporation" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.purchase.incorporated_pending").replace(" ", "_") + "]"); // button
					if (notif) notif.setAttribute("data-notification", data.consumables_pendingincorporation);
					consumables_pendingincorporation = data.consumables_pendingincorporation;
				}
				notif = document.querySelector("[for=userMenu" + api._lang.GET("menu.purchase.header").replace(" ", "_") + "]");
				if (notif) notif.setAttribute("data-notification", parseInt(order_prepared, 10) + parseInt(order_unprocessed, 10) + parseInt(consumables_pendingincorporation, 10)); // main menu label
			}
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
					? api._lang.GET("message.new_messages", {
							":amount": data.message_unnotified,
						})
					: api._lang.GET("message.new_message");
			this.showLocalNotification(api._lang.GET("menu.communication.header"), body);
		}
		this.notif.communication(data);
		this.notif.consumables(data);
		this.notif.calendar(data);
		this.notif.records(data);
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
		 * output for debugging purpose if not forbidden
		 */
		debug: (...$vars)=>{
			if (api._settings.config.application && api._settings.config.application.debugging)
				console.trace(...$vars);
			else console.log("there may have been an error, however debug mode has been turned off.");
		},

		/**
		 * converts an object to formdata
		 * @requires _
		 * @param {object} dialogData
		 * @returns {object} FormData
		 */
		dialogToFormdata: (dialogData = {}) => {
			let formdata;
			if (!Object.keys(dialogData).length) {
				formdata = _.getInputs('[method="dialog"]', true);
				if (!formdata) return false;
			} else {
				formdata = new FormData();
				for (const [key, value] of Object.entries(dialogData)) {
					formdata.append(key, value);
				}
			}
			return formdata;
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
	calendar: {
		/**
		 * creates FormData from a calendar form for new events
		 * @requires api
		 * @param {object} data Dialog response
		 */
		createFormData: (data) => {
			window.calendarFormData = new FormData();
			let units = [];
			for (const [key, value] of Object.entries(data)) {
				if (value === "unit") units.push(Object.keys(api._lang._USER["units"]).find((unit) => api._lang._USER["units"][unit] === key));
				else window.calendarFormData.append(key, value);
			}
			if (units.length) window.calendarFormData.append(api._lang.GET("calendar.schedule.organizational_unit"), units.join(","));
		},

		/**
		 * toggles inputs visibility on conditional request
		 * @param {string} names json stringified {'input name' : required bool}
		 * @param {bool} display
		 */
		setFieldVisibilityByNames: (names = "", display = true) => {
			names = JSON.parse(names);
			let fields;
			for (const [name, required] of Object.entries(names)) {
				fields = document.getElementsByName(name);
				for (const [id, field] of Object.entries(fields)) {
					field.parentNode.style.display = display ? "initial" : "none";
					if (required) {
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
						name: api._lang.GET("message.to"),
						value: recipient,
					},
				},
				{
					type: "textarea",
					attributes: {
						name: api._lang.GET("message.message"),
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
			new Dialog({
				type: "input",
				header: dialogheader,
				render: body,
				options: options,
			}).then((response) => {
				if (response[api._lang.GET("message.message")]) {
					const formdata = new FormData();
					formdata.append(api._lang.GET("message.to"), response[api._lang.GET("message.to")]);
					formdata.append(api._lang.GET("message.message"), response[api._lang.GET("message.message")]);
					api.message("post", "message", formdata);
				}
			});
		},
	},
	order: {
		/**
		 * insert nodes with product data
		 * order to be taken into account in order.php "productsearch" method as well!
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
			new Assemble(cart).initializeSection(nodes[nodes.length - 3]);
			new Toast(api._lang.GET("order.added_confirmation", { ":name": data[3] }), "info");
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
				filter = {},
				order = [],
				collapsible = [],
				buttons = {},
				links = {},
				labels = [];

			// construct filter checkboxes with events
			filter[api._lang.GET("order.order.unprocessed")] = { onchange: 'api.purchase("get", "approved", document.getElementById("productsearch").value)' };
			filter[api._lang.GET("order.order.ordered")] = { onchange: 'api.purchase("get", "approved", document.getElementById("productsearch").value || "null", "null", "ordered")' };
			filter[api._lang.GET("order.order.partially_received")] = { onchange: 'api.purchase("get", "approved", document.getElementById("productsearch").value || "null", "null", "partially_received")' };
			filter[api._lang.GET("order.order.received")] = { onchange: 'api.purchase("get", "approved", document.getElementById("productsearch").value || "null", "null", "received")' };
			filter[api._lang.GET("order.order.partially_delivered")] = { onchange: 'api.purchase("get", "approved", document.getElementById("productsearch").value || "null", "null", "partially_delivered")' };
			filter[api._lang.GET("order.order.delivered")] = { onchange: 'api.purchase("get", "approved", document.getElementById("productsearch").value || "null", "null", "delivered")' };
			filter[api._lang.GET("order.order.archived")] = { onchange: 'api.purchase("get", "approved", document.getElementById("productsearch").value || "null", "null", "archived")' };
			filter[api._lang.GET("order.order." + data.state)].checked = true;

			content.push([
				{
					type: "radio",
					attributes: {
						name: api._lang.GET("order.order_filter"),
					},
					content: filter,
				},
				{
					type: "filtered",
					attributes: {
						name: api._lang.GET("order.order_filter_label"),
						onkeydown: "if (event.key === 'Enter') {api.purchase('get', 'approved', this.value);}",
						id: "productsearch",
						value: data.filter ? data.filter : "",
					},
				},
			]);
			if (data.export) {
				content[content.length - 1].push({
					type: "button",
					attributes: {
						value: api._lang.GET("order.export"),
						onclick: 'api.purchase("get", "export", document.getElementById("productsearch").value || "null", "null", "' + data.state + '")',
					},
				});
			}

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

				collapsible.push({
					type: "br",
				});

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
							// receival date is not passed to label because it is not possible to determine if the label is for partial or remaining deliveries
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

				// append information
				if (element.information) {
					collapsible.push({
						type: "textarea_copy",
						attributes: {
							value: element.information,
							name: api._lang.GET("order.additional_info"),
							readonly: true,
						},
						numeration: "none",
						hint: api._lang.GET("order.copy_value"),
					});
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
								new _client.Dialog({
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
								}).then((response) => {
									if (response) api.purchase("put", "approved", "element.id", "addinformation", _client.application.dialogToFormdata(response));
								});
							}
								.toString()
								._replaceArray(["element.id", "buttons"], [element.id, JSON.stringify(buttons)]),
						},
					});
				}

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

				// append states
				// assemble state toggles
				let states = {};
				for (const [state, attributes] of Object.entries(element.state)) {
					states[api._lang.GET("order.order." + state)] = {};
					for (const [attribute, value] of Object.entries(attributes)) states[api._lang.GET("order.order." + state)][attribute] = value;
					if (attributes["data-" + state] === "true") states[api._lang.GET("order.order." + state)].checked = true;
					if (!attributes.disabled && !states[api._lang.GET("order.order." + state)].onchange)
						states[api._lang.GET("order.order." + state)].onchange = "api.purchase('put', 'approved', '" + element.id + "', '" + state + "', this.checked); this.setAttribute('data-" + state + "', this.checked.toString());";
				}
				// conditional customizing
				if (states[api._lang.GET("order.order.partially_received")] && !states[api._lang.GET("order.order.partially_received")].disabled) {
					buttons = {};
					buttons[api._lang.GET("order.add_information_cancel")] = false;
					buttons[api._lang.GET("order.add_information_ok")] = { value: true, class: "reducedCTA" };
					// _client.dialog for scope of stringified function is set to window, where Dialog is not directly accessible
					states[api._lang.GET("order.order.partially_received")].onchange = function () {
						api.purchase("put", "approved", "element.id", "partially_received", this.checked);
						this.setAttribute("data-partially_received", this.checked.toString());
						if (this.checked)
							new _client.Dialog({
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
							}).then((response) => {
								if (response) api.purchase("put", "approved", "element.id", "addinformation", _client.application.dialogToFormdata(response));
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
							new _client.Dialog({
								type: "input",
								header: api._lang.GET("order.disapprove"),
								render: [
									{
										type: "textarea",
										attributes: {
											name: api._lang.GET("message.message"),
										},
										hint: api._lang.GET("order.disapprove_message", { ":unit": api._lang.GET("units." + "element.organizationalunit") }),
									},
								],
								options: buttons,
							}).then((response) => {
								if (response !== false) {
									api.purchase("put", "approved", "element.id", "disapproved", _client.application.dialogToFormdata(response));
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
							new _client.Dialog({
								type: "input",
								header: api._lang.GET("order.cancellation"),
								render: [
									{
										type: "textarea",
										attributes: {
											name: api._lang.GET("message.message"),
										},
										hint: api._lang.GET("order.cancellation_message"),
									},
								],
								options: buttons,
							}).then((response) => {
								if (response !== false) {
									api.purchase("put", "approved", "element.id", "cancellation", _client.application.dialogToFormdata(response));
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
					states[api._lang.GET("order.return")] = {
						data_return: "false",
						// _client.dialog for scope of stringified function is set to window, where Dialog is not directly accessible
						onchange: function () {
							new _client.Dialog({
								type: "input",
								header: api._lang.GET("order.return"),
								render: [
									{
										type: "textarea",
										attributes: {
											name: api._lang.GET("message.message"),
										},
										hint: api._lang.GET("order.return_message"),
									},
								],
								options: buttons,
							}).then((response) => {
								if (response !== false) {
									api.purchase("put", "approved", "element.id", "return", _client.application.dialogToFormdata(response));
									this.disabled = true;
									this.setAttribute("data-return", "true");
								} else this.checked = false;
							});
						}
							.toString()
							._replaceArray(["element.id", "buttons"], [element.id, JSON.stringify(buttons)]),
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
						numeration: 0,
						attributes: {
							name: api._lang.GET("order.orderstate_description"),
							// _client.dialog for scope of stringified function is set to window, where Dialog is not directly accessible
							onchange: function () {
								if (this.value === "...") return false;
								new _client.Dialog({
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
								}).then((response) => {
									if (response) {
										response[api._lang.GET("order.additional_info")] = api._lang.GET("order.orderstate_description") + " - " + this.value + ": " + response[api._lang.GET("order.additional_info")];
										api.purchase("put", "approved", "element.id", "addinformation", _client.application.dialogToFormdata(response));
									}
								});
							}
								.toString()
								._replaceArray(["element.organizationalunit", "element.id", "buttons"], [element.organizationalunit, element.id, JSON.stringify(buttons)]),
						},
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

				// create order container
				order = [
					{
						type: "collapsible",
						attributes: {
							class: "em16" + (!element.collapsed ? " extended" : ""),
						},
						content: collapsible,
					},
				];

				// append incorporation state
				if (element.incorporation) {
					if (element.incorporation.item)
						order.push({
							type: "button",
							attributes: {
								value: api._lang.GET("order.incorporation.incorporation"),
								onclick: "if (!this.disabled) api.purchase('get', 'incorporation', " + element.incorporation.item + "); this.disabled = true",
							},
						});
					else if (element.incorporation.state)
						order.push({
							type: "textsection",
							attributes: {
								name: element.incorporation.state,
							},
						});
				}
				// append sample check state
				if (element.samplecheck) {
					if (element.samplecheck.item)
						order.push({
							type: "button",
							attributes: {
								value: api._lang.GET("order.sample_check.sample_check"),
								onclick: "if (!this.disabled) api.purchase('get', 'mdrsamplecheck', " + element.samplecheck.item + "); this.disabled = true",
							},
						});
					else if (element.samplecheck.state)
						order.push({
							type: "textsection",
							attributes: {
								name: element.samplecheck.state,
							},
						});
				}
				// append options to add product to database
				if (element.addproduct) {
					order.push({
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
								"');",
						},
					});
				}
				// append options to add product to database
				if (element.editproduct) {
					order.push({
						type: "button",
						attributes: {
							value: api._lang.GET("consumables.product.edit_product"),
							onclick: "api.purchase('get', 'product', " + element.editproduct + ");",
						},
					});
				}
				content.push(order);
			}
			const render = new Assemble({ content: content });
			document.getElementById("main").replaceChildren(render.initializeSection());
			render.processAfterInsertion();
		},
	},
	record: {
		/**
		 * filter records by case state
		 * @param {string} casestate to display, hide all others
		 * @event hiding or displaying records
		 */
		casestatefilter: (casestate) => {
			document.querySelectorAll("article>a").forEach((anchor) => {
				anchor.style.display = casestate ? "none" : "block";
			});
			if (casestate)
				document.querySelectorAll("[data-" + casestate + "]").forEach((anchor) => {
					anchor.style.display = "block";
				});
		},
		/**
		 * radio selection with stl preview
		 * @param {array} files array
		 * @returns assemble radio widget
		 */
		filereference: (files) => {
			const content = {},
				stloptions = {};
			let path, filename;

			stloptions[api._lang.GET("assemble.render.filereference_decline")] = false;
			stloptions[api._lang.GET("assemble.render.filereference_select")] = { value: true, class: "reducedCTA" };

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
				if (path.endsWith(".stl")) {
					content[path]["data-type"] = "stl";
					content[path].onclick = function () {
						new _client.Dialog({
							type: "stl",
							header: "filename",
							render: {
								name: "stlpath",
								url: "stlurl",
								transfer: true,
							},
							options: stloptions,
						}).then((response) => {
							if (!response) {
								this.checked = false;
								document.getElementById("_selectedfile").value = "";
							}
						});
					}
						.toString()
						._replaceArray(["filename", "stlpath", "stlurl", "stloptions"], [filename[filename.length - 1], path, url, JSON.stringify(stloptions)]);
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

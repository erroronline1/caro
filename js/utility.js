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
import QrCreator from "../libraries/qr-creator.js";

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
				notif = document.querySelector("[data-for=userMenu" + api._lang.GET("menu.calendar.header").replace(" ", "_") + "]");
				if (notif) notif.setAttribute("data-notification", data.calendar_uncompletedevents);
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.calendar.scheduling").replace(" ", "_") + "]");
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
			if ("document_approval" in data || "audit_closing" in data) {
				let document_approval = 0,
					audit_closing = 0;
				if ("document_approval" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.records.documents_manage_approval").replace(" ", "_") + "]");
					if (notif) notif.setAttribute("data-notification", data.document_approval);
					document_approval = data.document_approval;
				}
				if ("audit_closing" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.records.audit").replace(" ", "_") + "]");
					if (notif) notif.setAttribute("data-notification", data.audit_closing);
					audit_closing = data.audit_closing;
				}
				notif = document.querySelector("[data-for=userMenu" + api._lang.GET("menu.records.header").replace(" ", "_") + "]");
				if (notif) notif.setAttribute("data-notification", parseInt(document_approval, 10) + parseInt(audit_closing, 10));
			}
		},

		interval: null,

		/**
		 * updates styleable data-for=userMenu for communication menu
		 * @requires api
		 * @param {object} data containing message_unseen
		 * @event sets querySelector attribute data-notification
		 */
		communication: function (data) {
			let notif;
			if ("message_unseen" in data) {
				notif = document.querySelector("[data-for=userMenu" + api._lang.GET("menu.communication.header").replace(" ", "_") + "]");
				if (notif) notif.setAttribute("data-notification", data.message_unseen);
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.communication.conversations").replace(" ", "_") + "]");
				if (notif) notif.setAttribute("data-notification", data.message_unseen);
			}
			if ("measure_unclosed" in data) {
				// no appending number to userMenu to avoid distracting from unread messages
				notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.communication.measure").replace(" ", "_") + "]");
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
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.purchase.prepared_orders").replace(" ", "_") + "]");
					if (notif) notif.setAttribute("data-notification", data.order_prepared);
					order_prepared = data.order_prepared;
				}
				if ("order_unprocessed" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.purchase.approved_orders").replace(" ", "_") + "]");
					if (notif) notif.setAttribute("data-notification", data.order_unprocessed);
					order_unprocessed = data.order_unprocessed;
				}
				if ("consumables_pendingincorporation" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + api._lang.GET("menu.purchase.incorporated_pending").replace(" ", "_") + "]");
					if (notif) notif.setAttribute("data-notification", data.consumables_pendingincorporation);
					consumables_pendingincorporation = data.consumables_pendingincorporation;
				}
				notif = document.querySelector("[data-for=userMenu" + api._lang.GET("menu.purchase.header").replace(" ", "_") + "]");
				if (notif) notif.setAttribute("data-notification", parseInt(order_prepared, 10) + parseInt(order_unprocessed, 10) + parseInt(consumables_pendingincorporation, 10));
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
		if ("message_unnotified" in data) {
			if (parseInt(data.message_unnotified, 10)) {
				let body =
					data.message_unnotified > 1
						? api._lang.GET("message.new_messages", {
								":amount": data.message_unnotified,
						  })
						: api._lang.GET("message.new_message");
				this.showLocalNotification(api._lang.GET("menu.communication.header"), body);
			}
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
			this.permission = await window.Notification.requestPermission();
			navigator.serviceWorker.ready.then((registration) => {
				if (registration && !_serviceWorker.notif.interval) {
					_serviceWorker.notif.interval = setInterval(() => {
						_serviceWorker.postMessage("getnotifications");
					}, 300000);
				}
				navigator.serviceWorker.addEventListener("message", (message) => {
					this.onMessage(message);
				});
			});
		} else throw new Error("No Service Worker support!");
	},

	/**
	 * request permission to send notifications
	 * @event request permission
	 */
	requestNotificationPermission: async function () {
		const permission = await window.Notification.requestPermission();
		// value of permission can be 'granted', 'default', 'denied'
		// granted: user has accepted the request
		// default: user has dismissed the notification permission popup by clicking on x
		// denied: user has denied the request.
		if (permission !== "granted") {
			throw new Error("Permission not granted for Notification");
		}
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
		if (this.worker.active) this.worker.showNotification(title, options);
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

		lazyload: {
			qrCodes: [], // array of objects with canvas id and content, reset on assemble init
			barCodes: [], // array of objects with canvas id and content, reset on assemble init
			images: [], // array of objects with canvas id and content, reset on assemble init
			/**
			 * event handler onscroll
			 * iterates over the above arrays to populate the canvasses if within viewport
			 * removes entries once being handled
			 * neccessary e.g. on thousands of approved orders where repeatedly calling the libraries crashes the browser
			 * 
			 * @requires Assemble populated arrays
			 * @event QrCreator, JsBarcode and/or proprietary img2canvas
			 */
			load: function () {
				let content;
				if (_client.application.lazyload.qrCodes.length) {
					for (let i = 0; i < _client.application.lazyload.qrCodes.length; i++) {
						if (!(content = _client.application.lazyload.qrCodes[i])) continue;
						var rect = document.getElementById(content.id).getBoundingClientRect();
						if (rect.top >= 0 && rect.left >= 0 && rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) && rect.right <= (window.innerWidth || document.documentElement.clientWidth)) {
							// availableSettings = ['text', 'radius', 'ecLevel', 'fill', 'background', 'size']
							QrCreator.render(
								{
									text: content.content,
									size: 1024,
									ecLevel: api._settings.config.limits && api._settings.config.limits.qr_errorlevel ? api._settings.config.limits.qr_errorlevel : "M",
									background: null,
									fill: "#000000",
									radius: 0,
								},
								document.getElementById(content.id)
							);
							delete _client.application.lazyload.qrCodes[i]; // prevent repeatedly rendering
						}
					}
					_client.application.lazyload.qrCodes=_client.application.lazyload.qrCodes.filter(v => v);
				}
				if (_client.application.lazyload.barCodes.length) {
					for (let i = 0; i < _client.application.lazyload.barCodes.length; i++) {
						if (!(content = _client.application.lazyload.barCodes[i])) continue;
						var rect = document.getElementById(content.id).getBoundingClientRect();
						if (rect.top >= 0 && rect.left >= 0 && rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) && rect.right <= (window.innerWidth || document.documentElement.clientWidth)) {
							try {
								JsBarcode("#" + content.id, content.content.value, {
									format: content.content.format || "CODE128",
									background: "transparent",
									displayValue: content.content.displayValue != undefined ? content.content.displayValue : true,
								});
							} catch (e) {
								new Toast(api._lang.GET("jsbarcode.error"), "error");
							}
							delete _client.application.lazyload.barCodes[i]; // prevent repeatedly rendering
						}
					}
					_client.application.lazyload.barCodes=_client.application.lazyload.barCodes.filter(v => v);
				}
				if (_client.application.lazyload.images.length) {
					for (let i = 0; i < _client.application.lazyload.images.length; i++) {
						if (!(content = _client.application.lazyload.images[i])) continue;
						var rect = document.getElementById(content.id).getBoundingClientRect();
						if (rect.top >= 0 && rect.left >= 0 && rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) && rect.right <= (window.innerWidth || document.documentElement.clientWidth)) {
							let imgcanvas = document.getElementById(content.id),
								img = new Image();
							img.src = content.content;
							img.addEventListener("load", function (e) {
								let x = imgcanvas.width,
									y = imgcanvas.height,
									w = this.width,
									h = this.height,
									xoffset = 0,
									yoffset = 0;
								if (imgcanvas.width > w || imgcanvas.height > h) {
									// aka square by default if dimensions have not been passed
									if (w >= h) {
										y = (imgcanvas.height * h) / w;
										yoffset = (x - y) / 2;
									} else {
										x = (imgcanvas.width * w) / h;
										xoffset = (y - x) / 2;
									}
								}
								imgcanvas.getContext("2d").drawImage(this, xoffset, yoffset, x, y);
							});
							img.dispatchEvent(new Event("load"));
							URL.revokeObjectURL(img.src);
							delete _client.application.lazyload.images[i]; // prevent repeatedly rendering
						}
					}
					_client.application.lazyload.images=_client.application.lazyload.images.filter(v => v);
				}
			},
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
		toClipboard: (node) => {
			if (["HTMLInputElement", "HTMLTextAreaElement"].includes(node.constructor.name)) {
				node.select();
				node.setSelectionRange(0, 99999); // For mobile devices
				navigator.clipboard.writeText(node.value);
				node.selectionStart = node.selectionEnd;
			} else navigator.clipboard.writeText(node); // passed string
			new Toast(api._lang.GET("general.copied_to_clipboard"), "info");
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
			filter[api._lang.GET("order.order.unprocessed")] = { checked: true, onchange: "_client.order.filter()" };
			filter[api._lang.GET("order.order.ordered")] = { onchange: '_client.order.filter("ordered")' };
			filter[api._lang.GET("order.order.partially_received")] = { onchange: '_client.order.filter("partially_received")' };
			filter[api._lang.GET("order.order.received")] = { onchange: '_client.order.filter("received")' };
			filter[api._lang.GET("order.order.partially_delivered")] = { onchange: '_client.order.filter("partially_delivered")' };
			filter[api._lang.GET("order.order.delivered")] = { onchange: '_client.order.filter("delivered")' };
			filter[api._lang.GET("order.order.archived")] = { onchange: '_client.order.filter("archived")' };
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
						onkeypress: "if (event.key === 'Enter') {api.purchase('get', 'filter', this.value); return false;}",
						onblur: "api.purchase('get', 'filter', this.value); return false;",
						id: "productsearch",
					},
				},
			]);

			// iterate over orders and construct Assemble syntax
			for (const element of data.order) {
				// reinstatiate with order id for filtering
				collapsible = [{ type: "hidden", description: "filter", attributes: { "data-filtered": element.id } }];
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
							type: "button",
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
					links[api._lang.GET("order.message_orderer", { ":orderer": element.orderer })] = {
						href: "javascript:void(0)",
						"data-type": "input",
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
									})
									.replace("\\n", "\n"),
								buttons
							);
						}
							.toString()
							._replaceArray(
								["element.orderer", "element.quantity", "element.unit", "element.ordernumber", "element.name", "element.vendor", "element.information", "element.commission", "buttons"],
								[
									element.orderer,
									element.quantity,
									element.unit ? element.unit.replaceAll('"', '\\"') : "",
									element.ordernumber ? element.ordernumber.replaceAll('"', '\\"') : "",
									element.name ? element.name.replaceAll('"', '\\"') : "",
									element.vendor ? element.vendor.replaceAll('"', '\\"') : "",
									element.information ? element.information.replaceAll('"', '\\"') : "",
									element.commission ? element.commission.replaceAll('"', '\\"') : "",
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
					Object.keys(element.attachments).forEach((key) => {
						element.attachments[key].target = "_blank";
					});
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
							type: "button",
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
								type: "button",
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
								type: "button",
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
							type: "button",
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
				content.push(order);
			}
			const render = new Assemble({ content: content });
			document.getElementById("main").replaceChildren(render.initializeSection());
			render.processAfterInsertion();
		},

		/**
		 * filter orders by order processing state
		 * @param {string} type to display, hide all others
		 * @event hiding or displaying approved orders
		 */
		filter: (type = undefined) => {
			document.querySelectorAll("[data-ordered]").forEach((article) => {
				article.parentNode.parentNode.style.display = "none";
			});
			const filters = {
				ordered: document.querySelectorAll("[data-ordered=true]"),
				partially_received: document.querySelectorAll("[data-partially_received=true]"),
				received: document.querySelectorAll("[data-received=true]"),
				partially_delivered: document.querySelectorAll("[data-partially_delivered=true]"),
				delivered: document.querySelectorAll("[data-delivered=true]"),
				archived: document.querySelectorAll("[data-archived=true]"),
			};
			let display = [...document.querySelectorAll("[data-ordered=false]")].map(function (node) {
				return node.parentNode.parentNode;
			});
			if (type) {
				let hide = [],
					ignore = true;
				for (const [key, nodes] of Object.entries(filters)) {
					if (type === key) {
						ignore = false;
						continue;
					}
					if (ignore) continue;
					hide = hide.concat(...nodes);
				}
				display = [...filters[type]].map(function (node) {
					return [...hide].map((n) => n.parentNode.parentNode).includes(node.parentNode.parentNode) ? undefined : node.parentNode.parentNode;
				});
			}
			display.forEach((article) => {
				if (article) article.style.display = "block";
			});
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
	tool: {
		stlviewer: null,
		/**
		 * init stl viewer with selected file
		 * @param {string} file
		 * @event stl viewer initialisation
		 */
		initStlViewer: function (file) {
			if (file === "../null") return;
			const canvas = document.getElementById("stlviewer_canvas");
			canvas.title = api._lang.GET("assemble.render.aria.stl", { ":file": file });
			canvas.replaceChildren();
			this.stlviewer = new StlViewer(canvas, {
				models: [
					{
						id: 0,
						filename: file,
					},
				],
			});
		},
	},
};

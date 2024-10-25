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

const _serviceWorker = {
	worker: null,
	permission: null,
	notif: {
		calendar_uncompletedevents: function (data) {
			let notif;
			if ("calendar_uncompletedevents" in data) {
				notif = document.querySelector("[data-for=userMenu" + LANG.GET("menu.calendar_header").replace(" ", "_") + "]");
				if (notif) notif.setAttribute("data-notification", data.calendar_uncompletedevents);
				notif = document.querySelector("[data-for=userMenuItem" + LANG.GET("menu.calendar_scheduling").replace(" ", "_") + "]");
				if (notif) notif.setAttribute("data-notification", data.calendar_uncompletedevents);
			}
		},
		form_approval: function (data) {
			let notif;
			if ("form_approval" in data) {
				notif = document.querySelector("[data-for=userMenu" + LANG.GET("menu.record_header").replace(" ", "_") + "]");
				if (notif) notif.setAttribute("data-notification", data.form_approval);
				notif = document.querySelector("[data-for=userMenuItem" + LANG.GET("menu.forms_manage_approval").replace(" ", "_") + "]");
				if (notif) notif.setAttribute("data-notification", data.form_approval);
			}
		},
		interval: null,
		message_unseen: function (data) {
			if ("message_unseen" in data) {
				let notif;
				notif = document.querySelector("[data-for=userMenu" + LANG.GET("menu.communication_header").replace(" ", "_") + "]");
				if (notif) notif.setAttribute("data-notification", data.message_unseen);
				notif = document.querySelector("[data-for=userMenuItem" + LANG.GET("menu.message_conversations").replace(" ", "_") + "]");
				if (notif) notif.setAttribute("data-notification", data.message_unseen);
			}
		},
		order_unprocessed_consumables_pendingincorporation: function (data) {
			let notif;
			if ("order_unprocessed" in data || "consumables_pendingincorporation" in data) {
				let order_unprocessed = 0,
					consumables_pendingincorporation = 0;
				if ("order_unprocessed" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + LANG.GET("menu.purchase_approved_orders").replace(" ", "_") + "]");
					if (notif) notif.setAttribute("data-notification", data.order_unprocessed);
					order_unprocessed = data.order_unprocessed;
				}
				if ("consumables_pendingincorporation" in data) {
					notif = document.querySelector("[data-for=userMenuItem" + LANG.GET("menu.purchase_incorporated_pending").replace(" ", "_") + "]");
					if (notif) notif.setAttribute("data-notification", data.consumables_pendingincorporation);
					consumables_pendingincorporation = data.consumables_pendingincorporation;
				}
				notif = document.querySelector("[data-for=userMenu" + LANG.GET("menu.purchase_header").replace(" ", "_") + "]");
				if (notif) notif.setAttribute("data-notification", parseInt(order_unprocessed, 10) + parseInt(consumables_pendingincorporation, 10));
			}
		},
	},
	onMessage: function (message) {
		const data = message.data;
		if (!Object.keys(data).length) {
			document.querySelector("header>div:nth-of-type(2)").style.display = "block";
			return;
		}
		document.querySelector("header>div:nth-of-type(2)").style.display = "none";
		if ("message_unnotified" in data) {
			if (parseInt(data.message_unnotified, 10)) {
				let body =
					data.message_unnotified > 1
						? LANG.GET("message.new_messages", {
								":amount": data.message_unnotified,
						  })
						: LANG.GET("message.new_message");
				this.showLocalNotification(LANG.GET("menu.communication_header"), body);
			}
		}
		this.notif.message_unseen(data);
		this.notif.order_unprocessed_consumables_pendingincorporation(data);
		this.notif.calendar_uncompletedevents(data);
		this.notif.form_approval(data);
	},
	onPostCache: function () {
		const buttons = document.querySelectorAll("[type=submit]");
		for (const element of buttons) {
			element.disabled = true;
		}
	},
	postMessage: function (message) {
		this.worker.active.postMessage(message);
	},
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
	showLocalNotification: function (title, body) {
		const options = {
			body: body,
			icon: "./media/favicon/android/android-launchericon-192-192.png",
			// here you can add more properties like icon, image, vibrate, etc.
		};
		if (this.worker.active) this.worker.showNotification(title, options);
	},
};

const _client = {
	application: {
		clearMenu: (event) => {
			const inputs = document.getElementsByName("userMenu");
			for (const input of inputs) {
				input.checked = false;
			}
		},
		dialogToFormdata: (dialogData) => {
			if (!Object.keys.length) return false;
			formdata = new FormData();
			for (const [key, value] of Object.entries(dialogData)) {
				formdata.append(key, value);
			}
			return formdata;
		},
		postLabelSheet: (value, appendDate = null) => {
			const formdata = new FormData();
			formdata.append(LANG.GET("record.create_identifier"), value);
			api.record("post", "identifier", appendDate, formdata);
		},
		toClipboard: (node) => {
			if (["HTMLInputElement", "HTMLTextAreaElement"].includes(node.constructor.name)) {
				node.select();
				node.setSelectionRange(0, 99999); // For mobile devices
				navigator.clipboard.writeText(node.value);
				node.selectionStart = node.selectionEnd;
			} else navigator.clipboard.writeText(node); // passed string
			new Toast(LANG.GET("general.copied_to_clipboard"), "info");
		},
	},
	calendar: {
		createFormData: (data) => {
			window.calendarFormData = new FormData();
			units = [];
			for (const [key, value] of Object.entries(data)) {
				if (value === "unit") units.push(Object.keys(LANGUAGEFILE["units"]).find((unit) => LANGUAGEFILE["units"][unit] === key));
				else window.calendarFormData.append(key, value);
			}
			if (units.length) window.calendarFormData.append(LANG.GET("calendar.event_organizational_unit"), units.join(","));
		},
		setFieldVisibilityByNames: (names = "", display = true) => {
			/**
			 * @param string names json stringified {'input name' : required bool}
			 * @param bool display
			 */
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
		newMessage: (dialogheader = "", recipient = "", message = "", options = {}, datalist = []) => {
			//returns a message modal dialog
			if (!Object.keys(options)) {
				options[LANG.GET("order.add_information_cancel")] = false;
				options[LANG.GET("order.message_to_orderer")] = { value: true, class: "reducedCTA" };
			}

			const body = [
				{
					type: "hidden",
					attributes: {
						name: LANG.GET("message.to"),
						value: recipient,
					},
				},
				{
					type: "textarea",
					attributes: {
						name: LANG.GET("message.message"),
						rows: 8,
						value: message,
					},
				},
			];
			if (datalist.length) {
				if (typeof datalist === "string") datalist = datalist.split(",");
				body[0].type = "text";
				body[0].attributes.list = "rcptlist";
				body.push({
					type: "datalist",
					content: datalist,
					attributes: {
						id: "rcptlist",
					},
				});
			}
			new Dialog({
				type: "input",
				header: dialogheader,
				render: body,
				options: options,
			}).then((response) => {
				if (response[LANG.GET("message.message")]) {
					const formdata = new FormData();
					formdata.append(LANG.GET("message.to"), response[LANG.GET("message.to")]);
					formdata.append(LANG.GET("message.message"), response[LANG.GET("message.message")]);
					api.message("post", "message", formdata);
				}
			});
		},
	},
	order: {
		addProduct: (...data) => {
			// order to be taken into account in order.php "productsearch" method as well!
			// cart-content has a twin within order.php "order"-get method
			if ([...data].length < 6) data = ["", ...data];
			else data = [...data];
			const nodes = document.querySelectorAll("main>form>article"),
				cart = {
					content: [
						[
							{
								type: "number",
								attributes: {
									name: LANG.GET("order.quantity_label") + "[]",
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
									name: LANG.GET("order.added_product", {
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
									name: LANG.GET("order.unit_label") + "[]",
									value: data[1] ? data[1] : " ",
								},
							},
							{
								type: "hidden",
								attributes: {
									name: LANG.GET("order.ordernumber_label") + "[]",
									value: data[2],
								},
							},
							{
								type: "hidden",
								attributes: {
									name: LANG.GET("order.productname_label") + "[]",
									value: data[3],
								},
							},
							{
								type: "hidden",
								attributes: {
									name: LANG.GET("order.barcode_label") + "[]",
									value: data[4] ? data[4] : " ",
								},
							},
							{
								type: "hidden",
								attributes: {
									name: LANG.GET("order.vendor_label") + "[]",
									value: data[5],
								},
							},
							{
								type: "deletebutton",
								attributes: {
									value: LANG.GET("order.add_delete"),
									onpointerup: "this.parentNode.remove()",
								},
							},
						],
					],
				};
			new Assemble(cart).initializeSection(nodes[nodes.length - 3]);
		},
		approved: (data = undefined) => {
			if (!data) return;
			let content = [],
				filter = {},
				order = [],
				collapsible = [];
			options = {};
			filter[LANG.GET("order.untreated")] = { checked: true, onchange: "_client.order.filter()" };
			filter[LANG.GET("order.ordered")] = { onchange: '_client.order.filter("ordered")' };
			filter[LANG.GET("order.received")] = { onchange: '_client.order.filter("received")' };
			filter[LANG.GET("order.delivered")] = { onchange: '_client.order.filter("delivered")' };
			filter[LANG.GET("order.archived")] = { onchange: '_client.order.filter("archived")' };

			content.push([
				{
					type: "radio",
					attributes: {
						name: LANG.GET("order.order_filter"),
					},
					content: filter,
				},
				{
					type: "filtered",
					attributes: {
						name: LANG.GET("order.order_filter_label"),
						onkeypress: "if (event.key === 'Enter') {api.purchase('get', 'filter', this.value); return false;}",
						onblur: "api.purchase('get', 'filter', this.value); return false;",
						id: "productsearch",
					},
				},
			]);
			for (const element of data) {
				// reinstatiate with order id for filtering
				collapsible = [{ type: "hidden", description: "filter", attributes: { "data-filtered": element.id } }];

				// append ordertext
				collapsible.push({
					type: "textsection",
					content:
						LANG.GET("order.prepared_order_item", {
							":quantity": element.quantity,
							":unit": element.unit,
							":number": element.ordernumber,
							":name": element.name,
							":vendor": element.vendor,
						}) +
						"\n" +
						element.ordertext,
					attributes: {
						name: LANG.GET("order.ordertype." + element.ordertype),
						"data-type": element.ordertype,
					},
				});

				// append commission
				options = {};
				options[LANG.GET("general.ok_button")] = true;
				collapsible.push({
					type: "text_copy",
					attributes: {
						value: element.commission,
						name: LANG.GET("order.commission"),
						readonly: true,
						onpointerup: function () {
							new Dialog({
								type: "input",
								header: LANG.GET("order.commission"),
								render: [
									[
										{
											type: "text",
											attributes: {
												value: element.commission,
												name: LANG.GET("order.commission"),
												readonly: true,
												onpointerup: "_client.application.toClipboard(this)",
											},
											hint: LANG.GET("order.copy_value"),
										},
										{
											type: "button",
											attributes: {
												value: LANG.GET("menu.record_create_identifier"),
												onpointerup: function () {
													_client.application.postLabelSheet(element.commission);
												},
											},
										},
									],
								],
								options: options,
							});
						}.toString(),
					},
					hint: LANG.GET("order.copy_or_labelsheet"),
				});

				// append information
				if (element.information) {
					collapsible.push({
						type: "textarea_copy",
						attributes: {
							value: element.information,
							name: LANG.GET("order.additional_info"),
							readonly: true,
						},
						hint: LANG.GET("order.copy_value"),
					});
				}

				// append order number
				collapsible.push({
					type: "text_copy",
					attributes: {
						value: element.ordernumber,
						name: LANG.GET("order.ordernumber_label"),
						readonly: true,
						onpointerup: "_client.application.toClipboard(this)",
					},
					hint: LANG.GET("order.copy_value"),
				});

				// append special attention information
				if (element.specialattention)
					collapsible.push({
						type: "textsection",
						attributes: {
							name: LANG.GET("consumables.edit_product_special_attention"),
							class: "orange",
						},
					});

				// append orderer and message option
				options = { links: {}, buttons: {} };
				options.buttons[LANG.GET("order.add_information_cancel")] = false;
				options.buttons[LANG.GET("order.message_to_orderer")] = { value: true, class: "reducedCTA" };
				options.links[LANG.GET("order.message_orderer", { ":orderer": element.orderer })] = {
					href: "javascript:void(0)",
					"data-type": "input",
					onpointerup: function () {
						_client.message.newMessage(
							LANG.GET("order.message_orderer", { ":orderer": "element.orderer" }),
							"element.orderer",
							LANG.GET("order.message", {
								":quantity": "element.quantity",
								":unit": "element.unit",
								":number": "element.ordernumber",
								":name": "element.name",
								":vendor": "element.vendor",
								":info": "element.information" || '',
								":commission": "element.commission"
							}).replace("\\n", "\n"),
							options.buttons
						);
					}.toString()._replaceArray(["element.orderer", "element.quantity", "element.unit", "element.ordernumber", "element.name", "element.vendor", "element.information", "element.commission", "options.buttons"], [element.orderer, element.quantity, element.unit, element.ordernumber, element.name, element.vendor, element.information, element.commission, JSON.stringify(options.buttons)]),
				};
				collapsible.push({
					type: "links",
					content: options.links,
					hint: element.lastorder,
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

				// append add info button
				if (element.addinfo) {
					options = {};
					options[LANG.GET("order.add_information_cancel")] = false;
					options[LANG.GET("order.add_information_ok")] = { value: true, class: "reducedCTA" };
					collapsible.push({
						type: "button",
						attributes: {
							value: LANG.GET("order.add_information"),
							type: "button",
							onpointerup: function () {
								new Dialog({
									type: "input",
									header: LANG.GET("order.add_information"),
									render: [
										{
											type: "textarea",
											attributes: {
												name: LANG.GET("order.additional_info"),
											},
											hint: LANG.GET("order.add_information_modal_body"),
										},
									],
									options: options,
								}).then((response) => {
									if (response) api.purchase("put", "approved", element.id, "addinformation", _client.application.dialogToFormdata(response));
								});
							}.toString(),
						},
					});
				}

				// append state
				let states = {};
				for (const [state, attributes] of Object.entries(element.state)) {
					states[LANG.GET("order." + state)] = {};
					if (attributes["data-" + state]) states[LANG.GET("order." + state)].checked = true;
					if (!attributes.disabled) states[LANG.GET("order." + state)].onchange = "api.purchase('put', 'approved', '" + element.id + "', '" + state + "', this.checked); this.setAttribute('data-" + state + "', this.checked.toString());";
				}
				if (element.disapprove) {
					options = {};
					options[LANG.GET("order.disapprove_message_cancel")] = false;
					options[LANG.GET("order.disapprove_message_ok")] = { value: true, class: "reducedCTA" };
					states[LANG.GET("order.disapprove")] = {
						data_disapproved: "false",
						onchange: function () {
							new Dialog({
								type: "input",
								header: LANG.GET("order.disapprove"),
								render: [
									{
										type: "textarea",
										attributes: {
											name: LANG.GET("message.message"),
										},
										hint: LANG.GET("order.disapprove_message", { ":unit": LANG.GET("units.".element.organizationalunit) }),
									},
								],
								options: options,
							}).then((response) => {
								if (response !== false) {
									api.purchase("put", "approved", element.id, "disapproved", _client.application.dialogToFormdata(response));
									this.disabled = true;
									this.setAttribute("data-disapproved", "true");
								} else this.checked = false;
							});
						}.toString(),
					};
				}
				if (element.cancel) {
					options = {};
					options[LANG.GET("order.cancellation_message_cancel")] = false;
					options[LANG.GET("order.cancellation_message_ok")] = { value: true, class: "reducedCTA" };
					states[LANG.GET("order.cancellation")] = {
						data_cancellation: "false",
						onchange: function () {
							new Dialog({
								type: "input",
								header: LANG.GET("order.cancellation"),
								render: [
									{
										type: "textarea",
										attributes: {
											name: LANG.GET("message.message"),
										},
										hint: LANG.GET("order.cancellation_message"),
									},
								],
								options: options,
							}).then((response) => {
								if (response !== false) {
									api.purchase("put", "approved", element.id, "cancellation", _client.application.dialogToFormdata(response));
									this.disabled = true;
									this.setAttribute("data-cancellation", "true");
								} else this.checked = false;
							});
						}.toString(),
					};
				}
				if (element.return) {
					options = {};
					options[LANG.GET("order.return_message_cancel")] = false;
					options[LANG.GET("order.return_message_ok")] = { value: true, class: "reducedCTA" };
					states[LANG.GET("order.return")] = {
						data_return: "false",
						onchange: function () {
							new Dialog({
								type: "input",
								header: LANG.GET("order.return"),
								render: [
									{
										type: "textarea",
										attributes: {
											name: LANG.GET("message.message"),
										},
										hint: LANG.GET("order.return_message"),
									},
								],
								options: options,
							}).then((response) => {
								if (response !== false) {
									api.purchase("put", "approved", element.id, "return", _client.application.dialogToFormdata(response));
									this.disabled = true;
									this.setAttribute("data-return", "true");
								} else this.checked = false;
							});
						}.toString(),
					};
				}
				collapsible.push({ type: "checkbox", content: states });

				// append orderstatechange
				if (element.orderstatechange) {
					options = {};
					options[LANG.GET("order.add_information_cancel")] = false;
					options[LANG.GET("order.add_information_ok")] = { value: true, class: "reducedCTA" };
					collapsible.push({
						type: "select",
						content: element.orderstatechange,
						numeration: 0,
						attributes: {
							name: LANG.GET("order.orderstate_description"),
							onchange: function () {
								new Dialog({
									type: "input",
									header: LANG.GET("order.orderstate_description") + " " + this.value,
									render: [
										{
											type: "textarea",
											attributes: {
												name: LANG.GET("order.additional_info"),
											},
											hint: LANG.GET("order.disapprove_message", { ":unit": LANG.GET("units." + element.organizationalunit) }),
										},
									],
									options: options,
								}).then((response) => {
									if (response) {
										response[LANG.GET("order.additional_info")] = LANG.GET("order.orderstate_description") + " - " + this.value + ": " + response[LANG.GET("order.additional_info")];
										api.purchase("put", "approved", element.id, "addinformation", _client.application.dialogToFormdata(response));
									}
								});
							}.toString(),
						},
					});
				}

				// append delete button
				if (element.autodelete) {
					options = {};
					options[LANG.GET("order.delete_prepared_order_confirm_cancel")] = false;
					options[LANG.GET("order.delete_prepared_order_confirm_ok")] = { value: true, class: "reducedCTA" };
					collapsible.push({
						type: "deletebutton",
						hint: $autodelete,
						attributes: {
							type: "button",
							value: LANG.GET("order.delete_prepared_order"),
							onpointerup: function () {
								new Dialog({ type: "confirm", header: LANG.GET("order.delete_prepared_order_confirm_header"), options: options }).then((confirmation) => {
									if (confirmation) api.purchase("delete", "approved", element.id);
								});
							}.toString(),
						},
					});
					collapsible.push({
						type: "br", // to clear after floating delete button
					});
				}

				// create order
				order = [
					{
						type: "collapsible",
						attributes: {
							class: "em18" + (element.collapsed === false ? " extended" : ""),
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
								value: LANG.GET("order.incorporation"),
								type: "button",
								onpointerup: "if (!this.disabled) api.purchase('get', 'incorporation', " + element.incorporation.item + "); this.disabled = true",
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
								value: LANG.GET("order.sample_check"),
								type: "button",
								onpointerup: "if (!this.disabled) api.purchase('get', 'mdrsamplecheck', " + element.samplecheck.item + "); this.disabled = true",
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
				content.push(order);
			}
			const render = new Assemble({content:content});
			document.getElementById("main").replaceChildren(render.initializeSection());
			render.processAfterInsertion();
},
		filter: (type = undefined) => {
			document.querySelectorAll("[data-ordered]").forEach((article) => {
				article.parentNode.parentNode.style.display = "none";
			});
			const filters = {
				ordered: document.querySelectorAll("[data-ordered=true]"),
				received: document.querySelectorAll("[data-received=true]"),
				delivered: document.querySelectorAll("[data-delivered=true]"),
				archived: document.querySelectorAll("[data-archived=true]"),
			};
			let display = [...document.querySelectorAll("[data-ordered=false]")].map(function (node) {
				return node.parentNode.parentNode;
			});
			if (type === "ordered")
				display = [...filters.ordered].map(function (node) {
					return [...filters.received, ...filters.delivered, ...filters.archived].map((n) => n.parentNode.parentNode).includes(node.parentNode.parentNode) ? undefined : node.parentNode.parentNode;
				});
			if (type === "received")
				display = [...filters.received].map(function (node) {
					return [...filters.delivered, ...filters.archived].map((n) => n.parentNode.parentNode).includes(node.parentNode.parentNode) ? undefined : node.parentNode.parentNode;
				});
			if (type === "delivered")
				display = [...filters.delivered].map(function (node) {
					return [...filters.archived].map((n) => n.parentNode.parentNode).includes(node.parentNode.parentNode) ? undefined : node.parentNode.parentNode;
				});
			if (type === "archived")
				display = [...filters.archived].map(function (node) {
					return node.parentNode.parentNode;
				});
			display.forEach((article) => {
				if (article) article.style.display = "block";
			});
		},
		performIncorporation(formdata, productid) {
			const check = [],
				submit = new FormData();
			for (const [key, value] of Object.entries(formdata)) {
				if (key.startsWith("_")) {
					submit.append(key, value);
				} else if (value && value !== "on") check.push(key + ": " + value);
				else check.push(LANG.GET("order.sample_check_checked", { ":checked": key }));
			}
			if (check.length) {
				const result = check.join("\n");
				submit.append("content", result);
				api.purchase("post", "incorporation", productid, submit);
			} else new Toast(LANG.GET("order.incorporation_failure"), "error");
		},
		performSampleCheck(formdata, productid) {
			const check = [];
			for (const [key, value] of Object.entries(formdata)) {
				if (value && value !== "on") check.push(key + ": " + value);
				else check.push(LANG.GET("order.sample_check_checked", { ":checked": key }));
			}
			if (check.length) {
				const result = check.join("\n");
				formdata = new FormData();
				formdata.append("content", result);
				api.purchase("post", "mdrsamplecheck", productid, formdata);
			} else new Toast(LANG.GET("order.sample_check_failure"), "error");
		},
	},
	texttemplate: {
		data: null,
		update: () => {
			const replacements = {},
				genii = document.getElementsByName(LANG.GET("texttemplate.use_person")),
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
						if (input.value) blockcontent = blockcontent.replaceAll(":" + input.id, input.value);
					}
					for (const [key, replacement] of Object.entries(replacements)) {
						blockcontent = blockcontent.replaceAll(key, replacement[selectedgenus]);
					}
					output += blockcontent;
				}
			}
			document.getElementById("texttemplate").value = output;
		},
		import: (elements = "") => {
			try {
				elements = JSON.parse(elements);
			} catch (e) {
				return;
			}
			const converted_elements = {};
			// convert element keys to valid placeholder ids
			for (const [key, value] of Object.entries(elements)) {
				converted_elements[key.replace(/\W/gm, "")] = value;
			}
			const placeholder = document.querySelectorAll("[data-usecase=undefinedplaceholder]");
			for (const input of placeholder) {
				if (input.id in converted_elements) input.value = document.getElementById(converted_elements[input.id]) ? document.getElementById(converted_elements[input.id]).value : "";
			}
		},
	},
	tool: {
		stlviewer: null,
		initStlViewer: function (file) {
			if (file === "../null") return;
			const canvas = document.getElementById("stlviewer_canvas");
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

window.addEventListener("scroll", function () {
	const percentScrolled = (window.scrollY / (document.body.clientHeight - window.innerHeight + 100)) * 100;
	document.querySelector("header>div:last-of-type").style.width = percentScrolled + "%";
});
window.addEventListener("pointerup", _client.application.clearMenu);

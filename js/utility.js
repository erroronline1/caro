const _serviceWorker = {
	worker: null,
	permission: null,
	register: async function () {
		if ("serviceWorker" in navigator) {
			this.worker = await navigator.serviceWorker.register("./service-worker.js");
			this.permission = await window.Notification.requestPermission();
			navigator.serviceWorker.ready.then((registration) => {
				setInterval(() => {
					if (registration) _serviceWorker.postMessage("getnotifications");
				}, 10000);
				navigator.serviceWorker.addEventListener("message", (message) => {
					this.onMessage(message.data);
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
	postMessage: function (message) {
		this.worker.active.postMessage(message);
	},
	onPostCache: function () {
		const buttons = document.querySelectorAll("[type=submit]");
		for (const element of buttons) {
			element.disabled = true;
		}
	},
	onMessage: function (data) {
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
		let notif;

		if ("message_unseen" in data) {
			notif = document.querySelector("[data-for=userMenu" + LANG.GET("menu.communication_header").replace(" ", "_") + "]");
			if (notif) notif.setAttribute("data-notification", data.message_unseen);
			notif = document.querySelector("[data-for=userMenuItem" + LANG.GET("menu.message_conversations").replace(" ", "_") + "]");
			if (notif) notif.setAttribute("data-notification", data.message_unseen);
		}
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
		if ("calendar_uncompletedevents" in data) {
			notif = document.querySelector("[data-for=userMenu" + LANG.GET("menu.calendar_header").replace(" ", "_") + "]");
			if (notif) notif.setAttribute("data-notification", data.calendar_uncompletedevents);
			notif = document.querySelector("[data-for=userMenuItem" + LANG.GET("menu.calendar_scheduling").replace(" ", "_") + "]");
			if (notif) notif.setAttribute("data-notification", data.calendar_uncompletedevents);
		}
		if ("form_approval" in data) {
			notif = document.querySelector("[data-for=userMenu" + LANG.GET("menu.record_header").replace(" ", "_") + "]");
			if (notif) notif.setAttribute("data-notification", data.form_approval);
			notif = document.querySelector("[data-for=userMenuItem" + LANG.GET("menu.forms_manage_approval").replace(" ", "_") + "]");
			if (notif) notif.setAttribute("data-notification", data.form_approval);
		}
	},
};
const _client = {
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
					type: "hiddeninput",
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
				body[0].type = "textinput";
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
				body: body,
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
								type: "numberinput",
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
								type: "text",
								description: LANG.GET("order.added_product", {
									":unit": data[1],
									":number": data[2],
									":name": data[3],
									":vendor": data[5],
								}),
							},
							{
								type: "hiddeninput",
								attributes: {
									name: LANG.GET("order.unit_label") + "[]",
									value: data[1] ? data[1] : " ",
								},
							},
							{
								type: "hiddeninput",
								attributes: {
									name: LANG.GET("order.ordernumber_label") + "[]",
									value: data[2],
								},
							},
							{
								type: "hiddeninput",
								attributes: {
									name: LANG.GET("order.productname_label") + "[]",
									value: data[3],
								},
							},
							{
								type: "hiddeninput",
								attributes: {
									name: LANG.GET("order.barcode_label") + "[]",
									value: data[4] ? data[4] : " ",
								},
							},
							{
								type: "hiddeninput",
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
		toClipboard: (node) => {
			if (node.constructor.name === "HTMLInputElement") {
				node.select();
				node.setSelectionRange(0, 99999); // For mobile devices
				navigator.clipboard.writeText(node.value);
			} else navigator.clipboard.writeText(node); // passed string
			new Toast(LANG.GET("general.copied_to_clipboard"), "info");
		},
		filter: (type = undefined) => {
			document.querySelectorAll("[data-ordered]").forEach((article) => {
				article.parentNode.style.display = "none";
			});
			const filters = {
				ordered: document.querySelectorAll("[data-ordered=true]"),
				received: document.querySelectorAll("[data-received=true]"),
				archived: document.querySelectorAll("[data-archived=true]"),
			};
			let display = [...document.querySelectorAll("[data-ordered=false]")].map(function (node) {
				return node.parentNode;
			});
			if (type === "ordered")
				display = [...filters.ordered].map(function (node) {
					return [...filters.received]
						.map((n) => n.parentNode)
						.concat([...filters.archived].map((n) => n.parentNode))
						.includes(node.parentNode)
						? undefined
						: node.parentNode;
				});
			if (type === "received")
				display = [...filters.received].map(function (node) {
					return [...filters.archived].map((n) => n.parentNode).includes(node.parentNode) ? undefined : node.parentNode;
				});
			if (type === "archived")
				display = [...filters.archived].map(function (node) {
					return node.parentNode;
				});
			display.forEach((article) => {
				if (article) article.style.display = "block";
			});
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
		performIncorporation(formdata, productid) {
			const check = [],
				submit = new FormData();
			console.log(formdata);
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

//general

const applicationModule = {
	clearMenu: (event) => {
		const inputs = document.getElementsByName("userMenu");
		for (const input of inputs) {
			input.checked = false;
		}
	},
};

window.addEventListener("scroll", function () {
	const percentScrolled = (window.scrollY / (document.body.clientHeight - window.innerHeight + 100)) * 100;
	document.querySelector("header>div:last-of-type").style.width = percentScrolled + "%";
});
window.addEventListener("pointerup", applicationModule.clearMenu);

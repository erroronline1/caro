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
				}, 300000);
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
		if ("unnotified" in data) {
			if (parseInt(data.unnotified, 10)) {
				let body =
					data.unnotified > 1
						? LANG.GET("message.new_messages", {
								":amount": data.unnotified,
						  })
						: LANG.GET("message.new_message");
				this.showLocalNotification(LANG.GET("menu.communication_header"), body);
			}
		}
		if ("unseen" in data) {
			const mailnotif = document.querySelector("[data-for=userMenu" + LANG.GET("menu.communication_header") + "]");
			mailnotif.setAttribute("data-notification", data.unseen);
		}
		if ("unprocessed" in data) {
			const ordernotif = document.querySelector("[data-for=userMenu" + LANG.GET("menu.purchase_header") + "]");
			ordernotif.setAttribute("data-notification", data.unprocessed);
		}
		if ("uncompletedevents" in data) {
			const planningnotif = document.querySelector("[data-for=userMenu" + LANG.GET("menu.planning_header") + "]");
			planningnotif.setAttribute("data-notification", data.uncompletedevents);
		}
	},
};

const texttemplateClient = {
	data: null,
	update: () => {
		const replacements = {},
			genii = document.getElementsByName(LANG.GET("texttemplate.use_person")),
			blocks = document.querySelectorAll("[data-usecase=useblocks]"),
			placeholder = document.querySelectorAll("[data-usecase=undefinedplaceholder]");
		let selectedgenus = 0,
			output = "",
			blockcontent;
		for (const [key, value] of Object.entries(texttemplateClient.data.replacements)) {
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
				blockcontent = texttemplateClient.data.blocks[":" + block.name.replaceAll(/\(.*?\)/g, "")];
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
};

const calendarClient = {
	createFormData: (data) => {
		window.calendarFormData = new FormData();
		units = [];
		for (const [key, value] of Object.entries(data)) {
			if (value === "unit") units.push(Object.keys(LANGUAGEFILE["units"]).find((unit) => LANGUAGEFILE["units"][unit] === key));
			else window.calendarFormData.append(key, value);
		}
		if (units.length) window.calendarFormData.append(LANG.GET("calendar.event_organizational_unit"), units.join(","));
	},
};

const orderClient = {
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
		const check = [];
		for (const [key, value] of Object.entries(formdata)) {
			if (value && value !== "on") check.push(key + ": " + value);
			else check.push(LANG.GET("order.sample_check_checked", { ":checked": key }));
		}
		if (check.length) {
			const result = check.join("\n");
			formdata = new FormData();
			formdata.append("content", result);
			api.purchase("post", "incorporation", productid, formdata);
		} else new Toast(LANG.GET("order.incorporation_failure"), "error");
	},
};

const toolModule = {
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
	document.querySelector("header>div").style.width = percentScrolled + "%";
});
window.addEventListener("pointerup", applicationModule.clearMenu);

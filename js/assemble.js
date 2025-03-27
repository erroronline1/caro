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

/*
this module helps to assemble content according to the passed simplified object notation.
*/
import SignaturePad from "../libraries/signature_pad.umd.js";

var ELEMENT_ID = 0,
	SIGNATURE_CANVAS = null,
	TEXTAREA_AUTOCOMPLETE_INDEX = null;

export function getNextElementID() {
	return "elementID" + ++ELEMENT_ID;
}

const EVENTS = ["onclick", "onmouseover", "onmouseout", "onchange", "onpointerdown", "onpointerup", "onkeyup", "onkeydown"];
const VOIDVALUES = ["", "..."];

export const assemble_helper = {
	getNextElementID: getNextElementID,

	/**
	 * creates the application menu
	 * @requires api
	 * @param {object} content render data return from api
	 * @event insertion of menu nodes to menu container
	 */
	userMenu: function (content) {
		if (!content) return;
		const menu = document.querySelector("nav"),
			elements = [],
			icons = {};

		// set up icons css property
		icons[api._lang.GET("menu.application.header")] = "url('./media/bars.svg')";
		icons[api._lang.GET("menu.communication.header")] = "url('./media/comment.svg')";
		icons[api._lang.GET("menu.records.header")] = "url('./media/file-signature.svg')";
		icons[api._lang.GET("menu.calendar.header")] = "url('./media/calendar-alt.svg')";
		icons[api._lang.GET("menu.purchase.header")] = "url('./media/shopping-bag.svg')";
		icons[api._lang.GET("menu.files.header")] = "url('./media/folders.svg')";
		icons[api._lang.GET("menu.tools.header")] = "url('./media/tools.svg')";

		let label, input, div, button, span;
		// iterate over main categories
		for (const [group, items] of Object.entries(content)) {
			label = document.createElement("label");

			// set up label and notification element
			label.htmlFor = "userMenu" + group;
			label.setAttribute("data-notification", 0);
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
			input.setAttribute("aria-hidden", true);
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
			elements.push(input);
			elements.push(div);
		}
		menu.replaceChildren(...elements);
		// trigger notifications
		_serviceWorker.postMessage("getnotifications");
	},
};

export class Dialog {
	/**
	 * @requires api, Html5QrcodeScanner, Assemble
	 * @param {type: str, icon: str, header: str, render: str|array, options:{displayText: value str|bool|{value, class}}} options
	 * @returns promise, prepared answer on resolve according to type
	 * @example ```js
	 * new Dialog({options}).then(response => {
	 * 		doSomethingWithButtonValue(response);
	 * 	});
	 * ```
	 * select and scanner will be implemented by Assemble
	 *
	 * options with boolean values true|false as well as 'true'|'false' return selected booleans not strings. other strings will be returned as such.
	 * @example ```js
	 * new Dialog({type:'alert', header:'acknowledge this!', render:'infotext'})
	 * ```
	 * @example ```js
	 * new Dialog({type:'confirm', header:'confirm this', options:{'abort': false, 'yes, i agree': {'value': true, class: 'reducedCTA'}}}).then(confirmation => {
	 *  	if (confirmation) huzzah());
	 * 	});
	 * ```
	 * input needs button options as well, response keys in accordance to assemble content input names
	 * image and signature are NOT supported for having to be rendered in advance to filling their canvases.
	 * multiple articles and sections are NOT supported due to simplified query selector
	 * THIS IS FOR SIMPLE INPUTS ONLY
	 * @example ```js
	 * new Dialog({type:'input', header:'fill out assembled form', render: [assemble_content], options:{'abort': false, 'submit': {'value': true, class: 'reducedCTA'}}}).then(response => {
	 *  	if (Object.keys(response)) console.log('these are the results of the form:', response);
	 * 	});
	 * ```
	 */
	constructor(options = {}) {
		this.type = options.type || null;
		this.icon = options.icon || null;
		this.header = options.header || null;
		this.render = options.render || null;
		this.options = options.options || {};
		this.scannerElements = {};
		this.assemble = null;
		let modal = "modal";

		let dialog = document.getElementById("modal");
		if (this.type) {
			// define output dialog
			if (this.type === "input") modal = "inputmodal";
			if (this.type === "input2") {
				modal = "inputmodal2";
				this.type = "input";
			}

			// assemble render data
			if (this.type === "input") {
				if (this.render.content === undefined) this.render = { content: this.render };
				this.assemble = new Assemble(this.render);
			}

			// refer to output dialog
			dialog = document.getElementById(modal);

			// create form
			const form = document.createElement("form");
			if (this.type === "message") {
				form.dataset.usecase = "message";
				this.type = "input";
			}
			form.method = "dialog";

			// append close button
			const img = document.createElement("img");
			img.classList.add("close");
			img.src = "./media/times.svg";
			img.alt = api._lang.GET("assemble.render.aria.cancel");
			img.onclick = () => {
				const scanner = document.querySelector("video");
				if (scanner) scanner.srcObject.getTracks()[0].stop();
				document.getElementById(modal).close();
			};
			form.append(img);

			// append provided icon, header and content-string if applicable
			if (this.header || this.render || this.icon) {
				const header = document.createElement("header");
				if (this.icon) {
					const icon = document.createElement("img");
					img.src = this.icon;
					header.append(icon);
				}
				if (this.header) {
					const h3 = document.createElement("h3");
					h3.append(document.createTextNode(this.header));
					header.append(h3);
				}
				if (this.render && this.render.constructor.name === "String") {
					for (const line of this.render.split(/\r\n|\n/)) {
						header.append(document.createTextNode(line));
						header.append(document.createElement("br"));
					}
				}
				form.append(header);
			}

			// append select buttons if applicable
			if (this.type === "select") form.style.display = "flex";
			for (const element of this[this.type]()) {
				if (element) form.append(element);
			}
			dialog.replaceChildren(form);

			// append scanner if applicable
			if (this.type === "scanner") {
				// create scanner
				const scanner = {
					canvas: this.scannerElements.canvas,
					output: this.scannerElements.output,
					button: this.scannerElements.button,
					scanner: new Html5QrcodeScanner(this.scannerElements.canvas.id, {
						fps: 10,
						qrbox: {
							width: 300,
							height: 300,
						},
						rememberLastUsedCamera: true,
						aspectRatio: 1.0,
						useBarCodeDetectorIfSupported: true,
					}),
				};
				/**
				 * report success and port result to defined output container
				 * @param {string} decodedText
				 * @param {*} decodedResult
				 * @event update output container
				 */
				function scanSuccess(decodedText, decodedResult) {
					scanner.output.value = decodedText;
					scanner.button.removeAttribute("disabled");
					scanner.scanner.html5Qrcode.stop();
					scanner.canvas.style.border = "none";
					scanner.canvas.replaceChildren(document.createTextNode(api._lang.GET("general.scan_successful")));
				}
				scanner.scanner.render(scanSuccess);
			}
			if (this.assemble) this.assemble.processAfterInsertion(dialog);

			return new Promise((resolve, reject) => {
				dialog.showModal();
				dialog.onclose = resolve;
			}).then((response) => {
				let result;

				/**
				 * recursive value getter for nested rendered form
				 * @param {node} parent
				 * @returns {object} key:value pairs of input names and values if present
				 */
				function getValues(parent) {
					let result = {};
					parent.childNodes.forEach((node) => {
						if (["input", "textarea", "select"].includes(node.localName) && node.value) {
							// prepared inputs having data-wrap="some___thing" inserting value on the three underscores
							if (node.dataset.wrap && node.value) {
								node.value = node.dataset.wrap.replace("___", node.value);
							}

							if (["checkbox", "radio"].includes(node.type) && node.checked === true) result[node.name] = node.value;
							else if (!["checkbox", "radio"].includes(node.type)) result[node.name] = node.value;
						} else result = { ...result, ...getValues(node) };
					});
					return result;
				}

				// return dialog-form response
				switch (this.type) {
					case "select":
						result = response.target.returnValue;
						if (!result) return;
					case "confirm":
						result = response.target.returnValue;
						if (result && result == "true") return true;
						else if (!result || result == "false") return false;
						return result;
					default:
						if (response.target.returnValue == "true") {
							result = getValues(dialog);
							return result;
						}
						return false;
				}
			});
		}
		dialog.close();
	}

	/**
	 * returns textnodes with linebreak-nodes from string containing \n or &lt;br&gt;
	 * @param {string} string
	 * @returns {domNodes}
	 */
	linebreak(string) {
		let content = string.matchAll(/(.*?)(?:\\n|\n|<br.\/>|<br>|$)/gm),
			result = [];
		for (const match of content) {
			if (!match[1].length) continue;
			result.push(document.createTextNode(match[1]));
			result.push(document.createElement("br"));
		}
		return result;
	}

	/**
	 * default "OK" button
	 * @returns {domNodes} default confirmation button
	 */
	alert() {
		const button = document.createElement("button");
		button.value = true;
		button.append(document.createTextNode(api._lang.GET("general.ok_button")));
		return [button];
	}

	/**
	 * buttons according to this.options
	 * @returns {domNodes} confirmation option buttons
	 */
	confirm() {
		const buttons = [];
		let button;
		for (const [option, value] of Object.entries(this.options)) {
			button = document.createElement("button");
			button.append(document.createTextNode(option));
			button.classList.add("confirmButton");
			if (typeof value === "string" || typeof value === "boolean") button.value = value;
			else {
				button.value = value.value;
				if (value.class) button.classList.add(value.class);
			}
			if (!value) button.formNoValidate = true;
			buttons.push(button);
		}
		return buttons;
	}

	/**
	 * Assemble rendered inputs and this.confirm()
	 * @returns {domNodes} inputs and confirmations
	 */
	input() {
		let result = [...this.assemble.initializeSection(null, null, "iCanHasNodes")];
		if (Object.keys(this.options).length) result = result.concat(this.confirm());
		else result = result.concat(this.alert());
		return result;
	}

	/**
	 * @returns {domNodes} default scanner
	 */
	scanner() {
		const div = document.createElement("div"),
			input = document.createElement("input"),
			button = document.createElement("button");
		div.classList.add("scanner");
		div.id = getNextElementID();
		input.type = "hidden";
		input.name = "scanner";
		button.append(document.createTextNode(api._lang.GET("general.import_scan_result_button_from_modal")));
		button.classList.add("confirmButton");
		button.disabled = true;
		button.value = true;
		this.scannerElements = {
			canvas: div,
			output: input,
			button: button,
		};
		return [div, input, button];
	}

	/**
	 * select modal buttons with pseudo optgroups if provided sorted and length > 12 based on this.options
	 * @returns {domNodes} select buttons with pseudo optgroups
	 */
	select() {
		const buttons = document.createElement("div");
		let button,
			firststring,
			optgroup,
			useoptgroup = JSON.stringify(Object.keys(this.options)) === JSON.stringify(Object.keys(this.options).sort());
		for (let [option, value] of Object.entries(this.options)) {
			if (Object.entries(this.options).length > 12 && firststring !== option.substring(0, 1) && useoptgroup) {
				firststring = option.substring(0, 1);
				optgroup = document.createElement("h3");
				optgroup.classList.add("modaloptgroup");
				optgroup.append(document.createTextNode(firststring));
				buttons.append(optgroup);
			}
			button = document.createElement("button");
			button.classList.add("discreetButton");
			if (option.match(/\[X\]$/gm)) button.classList.add("strike"); // according to backend UTILITY::hiddenOption()
			button.append(...this.linebreak(option));
			/**
			 * i tried a lot, but a valid value has to be provided by the backend. regarding hidden/stroke button options
			 * altering the value by replacing regex even within Assemble.select() returns no value on clicking
			 * even though the value is set correct within the inspected code and unaltered buttons return properly
			 * if no value is set by the backend and the value is assigned the option by Assemble.select() everything
			 * works as expected. i have no explanation.
			 */
			button.value = value;
			buttons.append(button);
		}
		return [buttons];
	}
}

export class Toast {
	/**
	 * displays a toast message for defined time
	 * @param {string} message
	 * @param {string} type success|error|info
	 * @param {int} duration in milliseconds
	 * @param {string} destination toast or sessionwarning
	 * @example new Toast('message', 'success')
	 *
	 * duration is a bit fuzzy, idk why
	 *
	 */
	constructor(message = "", type = "", duration = 4000, destination = "toast") {
		this.message = message || undefined;
		this.duration = duration;
		this.destination = destination;
		this.toast = document.getElementById(destination);
		this.toast.removeAttribute("class");
		if (typeof this.message !== "undefined") {
			const closeimg = document.createElement("img"),
				pauseimg = document.createElement("img"),
				msg = document.createElement("span"),
				div = document.createElement("div");
			if (type) this.toast.classList.add(type);
			closeimg.classList.add("close");
			closeimg.src = "./media/times.svg";
			closeimg.alt = api._lang.GET("assemble.render.aria.cancel");
			closeimg.onclick = new Function("document.getElementById('" + destination + "').close();");
			pauseimg.classList.add("pause");
			pauseimg.src = "./media/equals.svg";
			pauseimg.alt = api._lang.GET("assemble.render.aria.pause");
			pauseimg.onclick = new Function("window.clearTimeout(window.toasttimeout['" + destination + "']);");
			msg.innerHTML = message;
			this.toast.replaceChildren(closeimg, pauseimg, msg, div);
			this.toast.show();
			this.countdown();
		} else this.toast.close();
	}
	/**
	 * updates reversed progress bar and closes toast on timeout
	 * @param {int} percent
	 */
	countdown(percent = 100) {
		const countdowndiv = document.querySelector("#" + this.destination + " > div");
		countdowndiv.style.width = percent + "%";
		window.toasttimeout[this.destination] = window.setTimeout(this.countdown.bind(this), this.duration / 1000, Math.round((percent - 1000 / this.duration) * 1000) / 1000);
		if (percent < 0) {
			window.clearTimeout(window.toasttimeout[this.destination]);
			this.toast.close();
		}
	}
}

export class Assemble {
	/**
	 * assembled forms and screen elements.
	 * deepest nesting of input object is three levels
	 * form:null or {attributes} / nothing creates just a div e.g. just for text and links
	 * content:[ see this.processContent() ]
	 *
	 * elements are assembled by default but can be assigned common html attributes
	 * names are mandatory for input elements
	 * @requires api, _client, JsBarcode, QrCreator, SignaturePad
	 * @param {object} setup render object
	 */
	constructor(setup) {
		this.content = setup.content;
		this.form = setup.form;
		this.section = null;
		this.imageQrCode = [];
		this.imageBarCode = [];
		this.imageUrl = [];
		this.names = setup.names || {};
		this.composer = setup.composer;
		_client.application.lazyload.qrCodes = _client.application.lazyload.barCodes = _client.application.lazyload.images = [];
	}

	/**
	 * @param {domNode} nextSibling inserts before node, used by utility.js order_client
	 * @param {domNode} formerSibling inserts after node, used on multiple photo or scanner type
	 * @param {any} returnOnlyNodes return nodes without container, used by Dialog
	 * @returns container or nodes
	 */
	initializeSection(nextSibling = null, formerSibling = null, returnOnlyNodes = null) {
		if (typeof nextSibling === "string") nextSibling = document.querySelector(nextSibling);

		// set up default form and submit button
		if (this.form && !nextSibling && !returnOnlyNodes) {
			this.section = document.createElement("form");
			this.section.method = "post";
			this.section.enctype = "multipart/form-data";
			this.section = this.apply_attributes(this.form, this.section);
			this.content.push([
				{
					type: "submitbutton",
					attributes: {
						value: api._lang.GET("general.submit_button"),
						type: "submit",
					},
				},
			]);
		} else if (this.composer === "elementClone") this.section = formerSibling.parentNode;
		else if (!this.composer) this.section = document.createElement("div");

		// create content
		this.assembledPanels = this.processContent();

		// return assembled panels or append before or after requested node
		if (!(nextSibling || formerSibling || returnOnlyNodes || this.composer)) {
			this.section.append(...this.assembledPanels);
			return this.section;
		} else if (nextSibling) {
			const tiles = Array.from(this.assembledPanels);
			for (let i = 0; i < tiles.length; i++) {
				nextSibling.parentNode.insertBefore(tiles[i], nextSibling);
			}
		} else if (formerSibling) {
			const tiles = Array.from(this.assembledPanels);
			for (let i = 0; i < tiles.length; i++) {
				// extract article children, there are unlikely sections
				let article = [...tiles[i].children];
				for (let j = 0; j < article.length; j++) {
					formerSibling.after(article[j]);
					formerSibling = article[j];
				}
			}
		} else {
			return this.assembledPanels;
		}
	}

	/**
	 * initialize events after insertion to access rendered nodes
	 * @param {domNode} eventListenerTarget
	 * @event scroll events to process number of sections
	 * @event add trash drag events
	 * @event initialize signature pad
	 * @event populate canvases for barcodes, qr-codes or images if applicable
	 */
	processAfterInsertion(eventListenerTarget = window) {
		const scrollables = document.querySelectorAll("section");
		for (const section of scrollables) {
			if (section.childNodes.length > 1) section.addEventListener("scroll", this.sectionScroller);
			section.dispatchEvent(new Event("scroll"));
		}

		// inititalize drag events
		const trash = document.querySelector("[data-type=trash");
		if (trash) compose_helper.composer_add_trash(trash.parentNode);

		if (this.signaturePad) {
			this.initialize_SignaturePad();
		}

		if (this.imageQrCode.length) _client.application.lazyload.qrCodes = this.imageQrCode;
		if (this.imageBarCode.length) _client.application.lazyload.barCodes = this.imageBarCode;
		if (this.imageUrl.length) _client.application.lazyload.images = this.imageUrl;
		if (this.imageQrCode.length || this.imageBarCode.length || this.imageUrl.length) {
			_client.application.lazyload.load();
			eventListenerTarget.addEventListener("scroll", _client.application.lazyload.load, false);
		} else {
			eventListenerTarget.removeEventListener("scroll", _client.application.lazyload.load);
		}

		if (document.querySelector("[autofocus]")) document.querySelector("[autofocus]").focus();
	}

	/**
	 * recursively processes one panel, with slides if nested accordingly and instatiates the contained widget elements
	 * @param {array|object} elements render instructions for single panel
	 * @returns {domNodes}
	 *
	 * @example content to exist of three nestings
	 * ```json
	 * 	[ panel article>section
	 * 		[ slide article
	 * 			{ element },
	 * 			{ element }
	 * 		],
	 * 		[ slide article
	 * 			...
	 * 		],
	 * 	],
	 * ```
	 * @example or two nestings
	 * ```json
	 *
	 * 	[ panel article
	 * 		{ element },
	 * 		{ element }
	 * 	]
	 * ```
	 */
	processPanel(elements) {
		let content = [],
			widget;
		if (elements.constructor.name === "Array") {
			const section = document.createElement("section");
			section.id = getNextElementID();
			elements.forEach((element) => {
				widget = this.processPanel(element);
				if (elements[0].constructor.name === "Array") {
					const article = document.createElement("article");
					for (const e of widget) {
						if (e) article.append(e);
					}
					section.append(article);
				} else {
					for (const e of widget) {
						if (e) content.push(e);
					}
				}
			});
			if (elements[0].constructor.name === "Array") content = content.concat(section, this.slider(section.id, section.childNodes.length));
		} else {
			this.currentElement = elements;
			try {
				if (elements.type) content = content.concat(this[elements.type]());
			} catch (e) {
				this.currentElement = {
					type: "textsection",
					content: JSON.stringify(elements, null, " "),
					attributes: {
						class: "red",
						name: api._lang.GET("assemble.render.error_faulty_widget"),
					},
				};
				content = content.concat(this.textsection());
				console.trace(e);
			}
		}
		return content;
	}

	/**
	 * iterates over this.content and gathers panels
	 * @returns {domNodes} all assembled panels
	 */
	processContent() {
		let assembledPanels = new Set();
		this.content.forEach((panel) => {
			const raw_nodes = this.processPanel(panel),
				nodes = [];
			// filter undefined
			raw_nodes.forEach((node) => {
				if (node) nodes.push(node);
			});

			let frame = false;
			for (let n = 0; n < nodes.length; n++) {
				if (!(["DATALIST", "HR", "BUTTON"].includes(nodes[n].nodeName) || nodes[n].hidden)) {
					frame = true;
					break;
				}
			}
			if (frame) {
				const article = document.createElement("article");
				article.append(...nodes);
				assembledPanels.add(article);
			} else assembledPanels.add(...nodes);
		});
		return assembledPanels;
	}

	/**
	 * constructs slider navigation and position indicators
	 * @param {string} sectionID
	 * @param {int} length
	 * @returns {domNodes} navigation buttons and slide indicators
	 */
	slider(sectionID, length) {
		if (length < 2) return;
		const indicators = document.createElement("div"),
			toleft = document.createElement("button"),
			toright = document.createElement("button");
		indicators.classList = "sectionindicator";
		indicators.id = sectionID + "indicator";

		// navigate left button
		toleft.addEventListener("click", function (e) {
			document.getElementById(sectionID).scrollBy({
				top: 0,
				left: -400,
				behaviour: "smooth",
			});
		});
		toleft.dataset.type = "toleft";
		toleft.classList.add("inlinebutton");
		toleft.type = "button";
		toleft.title = api._lang.GET("assemble.render.aria.toleft");
		toleft.tabIndex = -1;
		indicators.appendChild(toleft);

		// indicator circles of length with pointerenter event
		for (let i = 0; i < length; i++) {
			let indicator = document.createElementNS("http://www.w3.org/2000/svg", "svg"),
				circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
			indicator.classList = "articleindicator";
			indicator.setAttributeNS(null, "viewbox", "0 0 10 10");
			circle.setAttributeNS(null, "cx", "5");
			circle.setAttributeNS(null, "cy", "5");
			circle.setAttributeNS(null, "r", "4");
			indicator.appendChild(circle);
			indicator.addEventListener("pointerenter", function (e) {
				document.getElementById(sectionID).scrollTo({
					top: 0,
					left: (document.getElementById(sectionID).scrollWidth / length) * i - 1,
					behaviour: "smooth",
				});
			});
			indicators.appendChild(indicator);
		}

		// navigate right button
		toright.addEventListener("click", function (e) {
			document.getElementById(sectionID).scrollBy({
				top: 0,
				left: 400,
				behaviour: "smooth",
			});
		});
		toright.dataset.type = "toright";
		toright.classList.add("inlinebutton");
		toright.type = "button";
		toright.title = api._lang.GET("assemble.render.aria.toright");
		toright.tabIndex = -1;
		indicators.appendChild(toright);
		// accessibility setting, reduce distractions from contextwise inappropriate elements
		indicators.setAttribute("aria-hidden", true);
		return indicators;
	}

	/**
	 * event handler for horizontal scrolling of multiple panels, updating slider position indicators
	 * @param {event} e
	 * @event setTimeout
	 */
	sectionScroller(e) {
		setTimeout(() => {
			if (!e.target.attributes.id) return;
			let indicator = document.getElementById(e.target.attributes.id.value + "indicator");
			for (let panel = 0; panel < e.target.children.length + 1; panel++) {
				try {
					if (panel == Math.round(e.target.scrollLeft / e.target.clientWidth) + 1) indicator.children[panel].firstChild.classList.add("articleactive");
					else indicator.children[panel].firstChild.classList.remove("articleactive");
				} catch (err) {
					continue;
				}
			}
		}, 300);
	}

	/**
	 * check input fields for presence of required content, prepare atypical form content or report missing values
	 * @param {event} event
	 * @returns none after submit event or content update
	 */
	prepareForm(event) {
		const signature = document.getElementById("signaturecanvas"),
			requiredsignature = document.querySelector("[data-required=required]"),
			required = document.querySelectorAll("[required]");
		let missing_required = false;

		// check signature
		if (signature) {
			if (signaturePad.isEmpty()) {
				if (signature == requiredsignature) {
					signature.classList.add("input_required_alert");
					missing_required = true;
				}
				document.getElementById("SIGNATURE").value = null;
			} else {
				let file = new File([this.dataURLToBlob(signaturePad.toDataURL())], "signature.png", {
					type: "image/png",
					lastModified: new Date().getTime(),
				});
				let section = new DataTransfer();
				section.items.add(file);
				document.getElementById("SIGNATURE").files = section.files;
			}
		}

		// check regular inputs
		for (const element of required) {
			if (element.validity.valueMissing && element.form === event.target.form) {
				if (["file", "checkbox", "radio"].includes(element.type)) element.nextElementSibling.classList.add("input_required_alert");
				else element.classList.add("input_required_alert");
				if (!missing_required) {
					element.scrollIntoView({
						behavior: "smooth",
						block: "center",
						inline: "nearest",
					});
				}
				missing_required = true;
			} else if (element.nextElementSibling) element.nextElementSibling.classList.remove("input_required_alert");
		}

		// submit form after confirmation if applicable
		if (!missing_required) {
			if (!event.target.form.dataset.confirm) {
				event.target.form.submit();
				return;
			}
			const options = {};
			options[api._lang.GET("assemble.compose.document.document_cancel")] = false;
			options[api._lang.GET("assemble.compose.document.document_confirm")] = { value: true, class: "reducedCTA" };
			new Dialog({ type: "confirm", header: api._lang.GET("assemble.compose.document.document"), options: options }).then((confirmation) => {
				if (confirmation) event.target.form.submit();
			});
		} else new Toast(api._lang.GET("general.missing_form_data"), "error");
	}

	/**
	 *
	 *   ___ ___ _____ _____ ___ ___
	 *  |  _| . |     |     | . |   |
	 *  |___|___|_|_|_|_|_|_|___|_|_|
	 *
	 */

	initialize_SignaturePad() {
		SIGNATURE_CANVAS = document.getElementById("signaturecanvas");
		window.signaturePad = new SignaturePad(SIGNATURE_CANVAS, {
			// It's Necessary to use an opaque color when saving image as JPEG;
			// this option can be omitted if only saving as PNG or SVG
			//backgroundColor: 'rgb(255, 255, 255)'
			penColor: "rgb(46, 52, 64)",
		});
		// On mobile devices it might make more sense to listen to orientation change,
		// rather than window resize events.
		window.onresize = this.resizeSignatureCanvas;
		this.resizeSignatureCanvas();
	}
	// Adjust canvas coordinate space taking into account pixel ratio,
	// to make it look crisp on mobile devices.
	// This also causes canvas to be cleared.
	resizeSignatureCanvas() {
		// When zoomed out to less than 100%, for some very strange reason,
		// some browsers report devicePixelRatio as less than 1
		// and only part of the canvas is cleared then.
		const ratio = Math.max(window.devicePixelRatio || 1, 1);
		// This part causes the canvas to be cleared
		SIGNATURE_CANVAS.width = SIGNATURE_CANVAS.offsetWidth * ratio;
		SIGNATURE_CANVAS.height = SIGNATURE_CANVAS.offsetHeight * ratio;
		SIGNATURE_CANVAS.getContext("2d").scale(ratio, ratio);
		// This library does not listen for canvas changes, so after the canvas is automatically
		// cleared by the browser, SignaturePad#isEmpty might still return false, even though the
		// canvas looks empty, because the internal data of this library wasn't cleared. To make sure
		// that the state of this library is consistent with visual state of the canvas, you
		// have to clear it manually.
		//signaturePad.clear();
		// If you want to keep the drawing on resize instead of clearing it you can reset the data.
		signaturePad.fromData(signaturePad.toData());
	}
	// One could simply use Canvas#toBlob method instead, but it's just to show
	// that it can be done using result of SignaturePad#toDataURL.
	dataURLToBlob(dataURL) {
		// Code taken from https://github.com/ebidel/filer.js
		const parts = dataURL.split(";base64,");
		const contentType = parts[0].split(":")[1];
		const raw = window.atob(parts[1]);
		const rawLength = raw.length;
		const uInt8Array = new Uint8Array(rawLength);
		for (let i = 0; i < rawLength; ++i) {
			uInt8Array[i] = raw.charCodeAt(i);
		}
		return new Blob([uInt8Array], {
			type: contentType,
		});
	}

	/**
	 * add header based on this.currentElement.description, ~.type, ~.required
	 * @returns {domNodes} header with styleable data-type and data-required if applicable
	 */
	header() {
		if (!this.currentElement.description) return [];
		let header = document.createElement("header");
		header.appendChild(document.createTextNode(this.currentElement.description.replace(/\[\]|DEFAULT_/g, "")));
		header.dataset.type = this.currentElement.type;
		if (this.currentElement["data-filtered"]) header.dataset.filtered = this.currentElement["data-filtered"];
		// required handled by any input on their own but checkbox and radio
		if (this.currentElement.content && typeof this.currentElement.content === "object") {
			if (this.currentElement.attributes && this.currentElement.attributes.required) header.dataset.required = true;
			for (let [key, attributes] of Object.entries(this.currentElement.content)) {
				if (!attributes) break;
				if (attributes.required) {
					header.dataset.required = true;
					break;
				}
			}
		}
		return [header];
	}

	/**
	 * returns a styleable div based on this.currentElement.hint
	 * @returns {domNodes} div
	 */
	hint() {
		if (!this.currentElement.hint) return [];
		let div = document.createElement("div");
		div.classList.add("hint");
		div.role = "note";
		const content = this.currentElement.hint.matchAll(/(.*?)(?:\\n|\n|<br.\/>|<br>|$)/gm);
		for (const part of content) {
			if (!part[1].length) continue;
			div.appendChild(document.createTextNode(part[1]));
			div.appendChild(document.createElement("br"));
		}
		if (["range", "links"].includes(this.currentElement.type)) div.classList.add(this.currentElement.type + "-hint");
		return [div];
	}

	/**
	 * adds attributes to a node (name, id, events, etc.)
	 * @param {object} setup html attributes
	 * @param {node} node
	 * @returns {domNode} with set attributes
	 */
	apply_attributes(setup, node) {
		for (let [key, attribute] of Object.entries(setup)) {
			if (EVENTS.includes(key)) {
				if (attribute) {
					// strip anonymous function wrapping, tabs and linebreaks if applicable
					if (typeof attribute === "function") attribute = attribute.toString();
					if (attribute.startsWith("function")) attribute = attribute.replace(/^function.*?\(\).*?\{|\t{1,}|\n/gm, " ").slice(0, -1);
					if (attribute.startsWith("(")) attribute = attribute.replace(/^.*?\{|\t{1,}|\n|\}$/g, " ").slice(0, -1);
					attribute = attribute.replace(/^\s.?/gm, "");
					try {
						node[key] = new Function(attribute);
					} catch (e) {
						new Toast(e, "error", 10000);
						console.trace(attribute, e);
					}
				}
			} else {
				if (attribute) node.setAttribute(key, attribute);
			}
		}
		return node;
	}

	/**
	 * returns textnodes with linebreak-nodes from string containing \n or &lt;br&gt;
	 * @param {string} string
	 * @returns {domNodes}
	 */
	linebreak(string) {
		let content = string.matchAll(/(.*?)(?:\\n|\n|<br.\/>|<br>|$)/gm),
			result = [];
		for (const match of content) {
			if (!match[1].length) continue;
			result.push(document.createTextNode(match[1]));
			result.push(document.createElement("br"));
		}
		result.pop(); // drop last br
		return result;
	}

	/**
	 * adds a number to names if same is already present, stores within this.names
	 * @param {string} name
	 * @param {*} dontnumerate
	 * @returns name with count number if applicable
	 */
	names_numerator(name, dontnumerate = undefined) {
		// consider record.php exportdocument-method too on changing
		if (dontnumerate || [...name.matchAll(/\[\]/g)].length) return name;
		if (name in this.names) {
			this.names[name] += 1;
			return name + "(" + this.names[name] + ")";
		}
		this.names[name] = 1;
		return name;
	}

	/**
	 *         _   _         _
	 *   _ _ _|_|_| |___ ___| |_ ___
	 *  | | | | | . | . | -_|  _|_ -|
	 *  |_____|_|___|_  |___|_| |___|
	 *              |___|
	 *
	 */

	/**
	 * creates a textarea whose content will be inserted into clipboard
	 * @returns {domNodes}
	 * @see this.textarea()
	 */
	textarea_copy() {
		this.currentElement.attributes.onclick = "_client.application.toClipboard(this)";
		return this.textarea();
	}

	/**
	 * creates a text paragraph styled as question
	 * @returns {domNodes}
	 * @see this.textsection()
	 */
	auditsection() {
		return this.textsection();
	}

	/**
	 * @returns {domNodes} br
	 */
	br() {
		return [document.createElement("br")];
	}

	/**
	 * creates a button
	 * @returns {domNodes} button, hint if applicable, encapsulated if applicable
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "button",
	 * 		"hint": "this button does this or that",
	 * 		"attributes": {
	 * 			"value": "this is displayed on the button",
	 *			"onclick": "alert('hello')"
	 *		}
	 * 	}
	 * ```
	 */
	button() {
		let button = document.createElement("button"),
			imagealigned = false;
		button.id = getNextElementID();

		// write into button
		if (this.currentElement.attributes.value !== undefined) {
			button.append(...this.linebreak(this.currentElement.attributes.value));
			delete this.currentElement.attributes.value;
		}

		// apply attributes
		if (this.currentElement.attributes !== undefined) {
			if (this.currentElement.attributes.class && this.currentElement.attributes.class.match(/imagealigned/)) {
				imagealigned = true;
				this.currentElement.attributes.class = this.currentElement.attributes.class.replace(/imagealigned/, "");
			}
			button = this.apply_attributes(this.currentElement.attributes, button);
		}

		// bind to form if applicable
		if (this.currentElement.type === "submitbutton") button.onclick = this.prepareForm.bind(this);

		// embed in styleable container to prevent from 100% width
		if (imagealigned) {
			const container = document.createElement("div");
			container.classList.add("imagealigned");
			container.append(button, ...this.hint());
			return [container];
		}
		return [button, ...this.hint()];
	}

	/**
	 * displays a calendar view with tiles calling calendar api
	 * @returns {domNodes} header, calendar view
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "calendar",
	 * 		"content": [
	 * 			null,
	 * 			{
	 * 				"date": "Y-m-d",
	 * 				"display": "whatever text, weekday, day, number of appointments"
	 * 			}, ...
	 * 		],
	 * 		"api": "schedule|timesheet"
	 * 	}
	 * ```
	 */
	calendar() {
		let cal = [],
			daytile,
			apicall = this.currentElement.api;
		for (const day of this.currentElement.content) {
			daytile = document.createElement("div");
			daytile.classList = "day";
			if (day !== null) {
				daytile.classList.add("displayDay");
				let display = day.display.split("\n");
				for (const line of display) {
					daytile.append(document.createTextNode(line));
					daytile.append(document.createElement("br"));
				}
				daytile.onclick = function () {
					api.calendar("get", apicall, day.date, day.date);
				};
				if (day.today) daytile.classList.add("today");
				if (day.selected) daytile.classList.add("selected");
				if (day.holiday) daytile.classList.add("holiday");
				daytile.title = day.title;
				daytile.role = "link";
			}
			cal.push(daytile);
		}
		return [...this.header(), ...cal];
	}

	/**
	 * creates a calendar button to add a new calendarevent, click-event is defined by the backend though
	 * @returns {domNodes} br, button
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "calendarbutton"
	 * 	}
	 * ```
	 */
	calendarbutton() {
		// to style it properly by adding data-type to article container
		this.currentElement.attributes["data-type"] = "calendarbutton";
		this.currentElement.attributes.type = "button"; // avoid submitting twice
		return [...this.br(), ...this.button()];
	}

	/**
	 * empty method but neccessary for styling reasons (icon)
	 */
	cart() {}

	/**
	 * creates checkboxes or radio inputs
	 * @param {*} radio if it rather be radio inputs than checkbox by default
	 * @returns {domNodes} header if applicable, inputs and labels, hint if applicable, br
	 * @example this.currentElement
	 * ```json
	 * 	{
	 *		"type": "checkbox|radio"
	 *		"attributes":{
	 *			"name": "checkboxes or radio, serves as grouping name for checkboxes too",
	 *		}
	 *		"inline": "boolean displays only the inputs, can be omitted"
	 *		"numeration": "anything resulting in true to prevent enumeration"
	 *		"content": {
	 *			"Checkbox 1": {
	 *				"attribute": "optional"
	 *			},
	 *			"Checkbox 2": {
	 *				"attribute": "optional"
	 *			}
	 *		},
	 *		"hint": "this selection is for..."
	 * 	}
	 */
	checkbox(radio = null) {
		this.currentElement.description = this.currentElement.attributes && this.currentElement.attributes.name ? this.currentElement.attributes.name.replace(/\[\]/g, "") : null;
		const result = [],
			radioname = this.currentElement.attributes && this.currentElement.attributes.name ? this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration) : null; // keep same name for current article
		if (!this.currentElement.inline) result.push(...this.header());
		for (const [checkbox, attributes] of Object.entries(this.currentElement.content)) {
			let label = document.createElement("label"),
				input = document.createElement("input");
			input.id = getNextElementID();
			if (radio) {
				if ("class" in attributes) {
					label.classList = attributes.class;
					delete attributes.class;
				}
				label.classList.add("radio");
				input = this.apply_attributes(this.currentElement.attributes, input); // e.g. for required
				input.type = "radio";
				input.name = radioname;
				input.value = checkbox;
			} else {
				if ("class" in attributes) {
					label.classList = attributes.class;
					delete attributes.class;
				}
				label.classList.add("checkbox");
				input.type = "checkbox";
				input.dataset.grouped = this.currentElement.description;
				input.name = this.names_numerator(checkbox);
			}
			label.append(...this.linebreak(checkbox.replace(/\[\]|DEFAULT_/g, "")));
			input = this.apply_attributes(attributes, input);
			label.htmlFor = input.id;
			if (input.dataset.filtered) label.dataset.filtered = input.dataset.filtered;
			result.push(input);
			result.push(label);
		}
		if (this.currentElement.inline) return result;
		return [...result, ...this.hint(), document.createElement("br")];
	}

	/**
	 * creates a text typed input with onclick modal with checkbox selection
	 * requires additional options property on this.currentElement containing an options object
	 * @return {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "checkbox2text",
	 * 		"attributes": {
	 * 			"name": "Selected modal checkbox names are chained comma separated, onblur",
	 * 		},
	 * 		"hint": "Makes selections comprehensible while providing a single payload object. As seen in document manager.",
	 * 		"content": {
	 * 			"One": { "value": "1" },
	 * 			"Two": { "value": 2, "checked": true },
	 * 			"Three": { "value": "Three" },
	 * 			'name': {'value':'str|int', 'checked': 'bool'}
	 * 		}
	 * 	}
	 * ```
	 */
	checkbox2text() {
		return this.input("checkbox2text");
	}

	/**
	 * creates a textarea with numbered lines
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "code",
	 * 		"hint": "cool line numers 8)",
	 * 		"attributes": {
	 * 			"name": "code_editor",
	 * 			"id": "_code_editor",
	 * 		}
	 * 	}
	 * ```
	 */
	code() {
		this.currentElement.editor = true;
		return this.textarea();
	}

	/**
	 * initially collapsed by default, extended if attributes.class = "extended"
	 * @returns encapsulated content widgets
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "collapsible",
	 * 		"attributes" : {"eg.": "dataset values for filtering"},
	 * 		"content": ["array of any defined types"]
	 * 	}
	 * ```
	 */
	collapsible() {
		let div = document.createElement("div"),
			img = document.createElement("img");
		img.classList.add("close");
		img.src = "./media/plus.svg";
		img.alt = api._lang.GET("assemble.render.aria.extend");
		img.onclick = () => {
			div.classList.toggle("extended");
		};
		// accessibility setting
		img.setAttribute("aria-hidden", true);
		div.append(img);
		div.classList.add("em8");
		if (this.currentElement.attributes !== undefined) div = this.apply_attributes(this.currentElement.attributes, div);
		div.classList.add("collapsible");
		for (const element of this.currentElement.content) {
			this.currentElement = element;
			div.append(...this[element.type]());
		}
		return [div];
	}

	/**
	 * creates a date input
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "date",
	 * 		"attributes": {
	 * 			"name": "variable name"
	 * 		}
	 * 	}
	 * ```
	 */
	date() {
		return this.input("date");
	}

	/**
	 * creates a delete button (styled)
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "deletebutton",
	 * 		"attributes": {
	 * 			"value": "yeet that!",
	 *			"onclick": "some.deletion.event()"
	 *		}
	 * 	}
	 * ```
	 */
	deletebutton() {
		// to style it properly by adding data-type to article container
		this.currentElement.attributes["data-type"] = "deletebutton";
		return this.button();
	}

	/**
	 * creates a email input
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "email",
	 * 		"attributes": {
	 * 			"name": "variable name"
	 * 		}
	 * 	}
	 * ```
	 */
	email() {
		return this.input("email");
	}

	/**
	 * creates a file input
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "file",
	 * 		"attributes": {
	 * 			"name": "file upload",
	 * 			"multiple": true
	 * 		},
	 * 		"hint": "this file serves as..."
	 * 	}
	 */
	file() {
		/**/
		let input = document.createElement("input"),
			label = document.createElement("button"),
			button = document.createElement("button");
		input.type = "file";
		input.id = getNextElementID();
		this.currentElement.description = this.currentElement.attributes.name.replace(/\[\]/g, "");
		if (this.currentElement.attributes.multiple) {
			if (!this.currentElement.attributes.name.endsWith("[]")) this.currentElement.attributes.name += "[]";
		}
		input.setAttribute("aria-label", this.currentElement.description);
		input = this.apply_attributes(this.currentElement.attributes, input);
		if (this.currentElement.attributes.multiple !== undefined)
			input.onchange = function () {
				this.nextSibling.innerHTML = this.files.length
					? Array.from(this.files)
							.map((x) => x.name)
							.join(", ") +
					  " " +
					  api._lang.GET("assemble.render.files_rechoose")
					: api._lang.GET("assemble.render.files_choose");
			};
		else
			input.onchange = function () {
				this.nextSibling.innerHTML = this.files.length
					? Array.from(this.files)
							.map((x) => x.name)
							.join(", ") +
					  " " +
					  api._lang.GET("assemble.render.file_rechoose")
					: api._lang.GET("assemble.render.file_choose");
			};
		label.onclick = () => {
			document.getElementById(input.id).click();
		};
		label.type = "button";
		label.dataset.type = "file";
		label.classList.add("inlinebutton");
		label.appendChild(document.createTextNode(this.currentElement.attributes.multiple !== undefined ? api._lang.GET("assemble.render.files_choose") : api._lang.GET("assemble.render.file_choose")));
		if (this.currentElement.attributes && this.currentElement.attributes.required) label.dataset.required = true;

		button.onclick = () => {
			let e = document.getElementById(input.id);
			e.value = "";
			e.dispatchEvent(new Event("change"));
		};
		button.appendChild(document.createTextNode("Reset"));
		button.type = "button";
		button.dataset.type = "reset";
		button.classList.add("inlinebutton");
		// accessibility setting; hide "select"-button, as input can be accessed directly
		label.tabIndex = -1;
		label.setAttribute("aria-hidden", true);
		return [...this.header(), input, label, button, ...this.hint()];
	}

	/**
	 * creates a styled search input
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "filtered",
	 * 		"attributes": {
	 * 			"name": "variable name"
	 * 		}
	 * 	}
	 * ```
	 */
	filtered() {
		// filter appears to be reserved
		return this.input("search");
	}

	/**
	 * creates a document button (styled)
	 * @returns {domNodes} br, button
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "documentbutton",
	 * 		"attributes": {
	 * 			"value": "{selected document title} anzeigen",
	 * 			"onclick": "api.record('get', 'displayonly', '{selected document title}')"
	 * 		}
	 * 	}
	 * ```
	 */
	documentbutton() {
		// to style it properly by adding data-type to article container
		this.currentElement.attributes["data-type"] = "documentbutton";
		this.currentElement.attributes.type = "button"; // avoid submitting twice
		return [...this.br(), ...this.button()];
	}

	/**
	 * creates a hidden input
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * {
	 * 		"type": "hidden",
	 * 		"numeration": "anything resulting in true to prevent enumeration"
	 * 		"attributes": {
	 * 			"name": "name", not neccessarily set for pure filter functions
	 * 			"value": 3.14}
	 * 		}
	 * 	}
	 */
	hidden() {
		let input = document.createElement("input");
		input.type = "hidden";
		input.id = getNextElementID();
		input.value = this.currentElement.value;
		if (this.currentElement.attributes.name) this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		input = this.apply_attributes(this.currentElement.attributes, input);
		return [input];
	}

	/**
	 * @returns {domNodes} hr
	 */
	hr() {
		return [document.createElement("hr")];
	}

	/**
	 * creates an identify scanner
	 * @returns scanner
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "identify",
	 * 		"attributes": {
	 * 			"name": "identifier",
	 * 			"required": true
	 * 		}
	 * 	}
	 */
	identify() {
		this.currentElement.attributes.name = "IDENTIFY_BY_" + this.currentElement.attributes.name;
		return this.scanner();
	}

	/**
	 * creates an image canvas for barcodes, qr-code or images
	 * @returns {domNodes} header, canvas, downloadbutton
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "image",
	 * 		"description":"export image",
	 * 		"attributes":{
	 * 			"name": "exportname atypical use of generic attributes on this one",
	 * 			"qrcode": "e.g. token for display of a qrcode with this value",
	 * 			"barcode": {"value":"e.g. tokenfor display of a barcode with this value", "format": "see documentation"},
	 * 			"url": "base64 encoded string|url",
	 * 			"imageonly": {"inline styles": "overriding .imagecanvas"}|undefined
	 * 		},
	 * 		"dimensions"{
	 * 			"width": int,
	 * 			"height": int
	 * 		}
	 * 	}
	 */
	image() {
		let result = [];
		const canvas = document.createElement("canvas");
		let disabled = true;
		canvas.id = getNextElementID();
		canvas.classList.add("imagecanvas");
		if (this.currentElement.attributes.class) canvas.classList.add(this.currentElement.attributes.class);
		if (typeof this.currentElement.attributes.imageonly === "object") {
			for (const [key, value] of Object.entries(this.currentElement.attributes.imageonly)) {
				canvas.style[key] = value;
			}
		} else result = result.concat(this.header());
		if (this.currentElement.dimensions) {
			canvas.width = this.currentElement.dimensions.width;
			canvas.height = this.currentElement.dimensions.height;
		} else canvas.width = canvas.height = 1024;
		if (this.currentElement.attributes.qrcode) {
			this.imageQrCode.push({
				id: canvas.id,
				content: this.currentElement.attributes.qrcode,
			});
			disabled = false;
		}
		if (this.currentElement.attributes.barcode) {
			this.imageBarCode.push({
				id: canvas.id,
				content: this.currentElement.attributes.barcode,
			});
			disabled = false;
		}
		if (this.currentElement.attributes.url) {
			this.imageUrl.push({
				id: canvas.id,
				content: this.currentElement.attributes.url,
			});
			disabled = false;
		}

		// accessibility setting
		canvas.title = api._lang.GET("assemble.render.aria.image", { ":image": this.currentElement.attributes.name });

		result.push(canvas);

		if (!this.currentElement.attributes.imageonly) {
			// this tile does not process attributes, therefore they can be reassigned
			// escape \ for fakepath to avoid SyntaxError: malformed Unicode character escape sequence for filenames starting with u or other supposed control characters during new image type creation within composer
			const create_virtual_link_and_click = {
				name: this.currentElement.attributes.name.replaceAll(/\\fakepath\\/gi, "\\\\fakepath\\\\"),
				canvas_id: canvas.id,
				fn: () => {
					document.getElementById("canvas_id").toBlob(
						function (blob) {
							const blobUrl = URL.createObjectURL(blob),
								link = document.createElement("a");
							link.href = blobUrl;
							link.download = "name" + ".png";
							link.click();
							URL.revokeObjectURL(blobUrl);
						},
						"image/png",
						1
					);
				},
			};

			this.currentElement.attributes = {
				value: this.currentElement.description,
				type: "button",
				class: "inlinebutton",
				"data-type": this.currentElement.type,
				onclick: create_virtual_link_and_click.fn.toString().replace("name", create_virtual_link_and_click.name).replace("canvas_id", create_virtual_link_and_click.canvas_id),
			};
			if (disabled) this.currentElement.attributes.disabled = true;
			result = result.concat(this.button());
		}
		return result;
	}

	/**
	 * creates an input of type
	 * @param {string} type
	 * @returns {domNodes} icon, input, label, hint
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "{type}",
	 * 		"hint": "please provide information about...",
	 * 		"numeration": "anything resulting in true to prevent enumeration"
	 * 		"attributes": {
	 * 			"name": "variable name will be used as label as well"
	 * 		},
	 * 		"datalist": ["some", "predefined", "values"]
	 * 	}
	 * the optional autocomplete-property in composed widgets for documents will be populated with a datalist from the api side if respective contents are found based on the widgets name
	 */
	input(type) {
		let input = document.createElement("input"),
			label = document.createElement("label"),
			span = document.createElement("span"),
			hint = this.hint(),
			imagealigned = false,
			datalist;
		input.type = type;
		const inputClone = structuredClone(this.currentElement);
		if (type === "password") this.currentElement.type = "password";
		input.id = this.currentElement.attributes && this.currentElement.attributes.id ? this.currentElement.attributes.id : getNextElementID();
		input.autocomplete = (this.currentElement.attributes && this.currentElement.attributes.type) === "password" ? "one-time-code" : "off";

		if (this.currentElement.attributes.required) span.dataset.required = true;
		if (this.currentElement.attributes["data-filtered"]) label.dataset.filtered = this.currentElement.attributes["data-filtered"];

		if (type === "number") input.step = ".01";
		if (this.currentElement.type === "link") input.dataset.wrap = "href='___'";

		this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		if (this.currentElement.attributes.multiple) {
			label.dataset.multiple = "multiple";
			input.onchange = () => {
				// arrow function for reference of this.names
				if (input.value) {
					inputClone.attributes.name = inputClone.attributes.name.replace(/\(\d+\)$/gm, "");
					delete inputClone.attributes.value;
					new Assemble({
						content: [[inputClone]],
						composer: "elementClone",
						names: this.names,
					}).initializeSection(null, hint.length ? hint[0] : label);
				}
			};
		}

		if (this.currentElement.attributes.class && this.currentElement.attributes.class.match(/imagealigned/)) {
			imagealigned = true;
			this.currentElement.attributes.class = this.currentElement.attributes.class.replace(/imagealigned/, "");
		}
		input = this.apply_attributes(this.currentElement.attributes, input);

		if (type === "email") input.multiple = true;

		if (type === "checkbox2text") {
			input.type = "text";
			const inputvalue = [];
			for (const [key, value] of Object.entries(this.currentElement.content)) {
				if (value.checked != undefined && value.checked) inputvalue.push(key);
			}
			input.value = inputvalue.join(", ");
			let currentElement = this.currentElement;
			input.onclick = function () {
				const options = {};
				options[api._lang.GET("assemble.compose.document.document_cancel")] = false;
				options[api._lang.GET("assemble.compose.document.document_confirm")] = { value: true, class: "reducedCTA" };
				new Dialog({
					type: "input",
					header: currentElement.attributes.name.replace(/\[\]/g, ""),
					render: [
						[
							{
								type: "checkbox",
								content: currentElement.content,
							},
						],
					],
					options: options,
				}).then((response) => {
					const e = document.getElementById(input.id);
					if (Object.keys(response).length) {
						e.value = Object.keys(response).join(", ");
					}
					e.dispatchEvent(new Event("change"));
					e.blur();
				});
			};
		}

		if (this.currentElement.attributes.hidden !== undefined) return input;

		if (this.currentElement.attributes.class && this.currentElement.attributes.class.match(/imagealigned/)) {
			imagealigned = true;
			this.currentElement.attributes.class = this.currentElement.attributes.class.replace(/imagealigned/, "");
		}
		input = this.apply_attributes(this.currentElement.attributes, input);

		if (this.currentElement.datalist !== undefined && this.currentElement.datalist.length) {
			datalist = document.createElement("datalist");
			let option;
			datalist.id = getNextElementID();
			this.currentElement.datalist.forEach((key) => {
				option = document.createElement("option");
				option.append(document.createTextNode(key));
				datalist.appendChild(option);
			});
			input.setAttribute("list", datalist.id);
		}

		span.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]|DEFAULT_/g, "")));

		label.dataset.type = this.currentElement.type;
		label.append(span, input);

		if (imagealigned) {
			const container = document.createElement("div");
			container.classList.add("imagealigned");
			container.append(label, ...hint);
			if (datalist) container.append(datalist);
			return [container];
		}
		if (datalist) return [label, ...hint, datalist];
		return [label, ...hint];
	}

	/**
	 * creates a styled link input whose value is about to be wrapped
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "link",
	 * 		"attributes": {
	 * 			"name": "variable name"
	 * 		}
	 * 	}
	 * ```
	 */
	link() {
		return this.input("text");
	}

	/**
	 * creates a set of links
	 * @returns {domNodes} header, anchors, hint
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "links",
	 * 		"description": "link list",
	 * 		"content": {
	 * 			"Link 1": {
	 * 				"href": "#"
	 * 			},
	 * 			"Link 2": {
	 * 				"href": #"",
	 * 				"onclick": "alert('hello')"
	 * 			}
	 * 		},
	 * 		"hint": "these links serve the purpose of..."
	 * 		"data-filtered": any
	 * 	}
	 */
	links() {
		let result = [...this.header()];
		if (this.currentElement.attributes !== undefined) result.push(...this.hidden()); // applying data-filtered for css rules
		for (const [link, attributes] of Object.entries(this.currentElement.content)) {
			let a = document.createElement("a");
			a = this.apply_attributes(attributes, a);
			if (!a.href) a.href = link;
			if (!a.href.includes("javascript:") && !a.target) a.target = "_blank";
			a.appendChild(document.createTextNode(link));
			result.push(a);
		}
		return [...result, ...this.hint()];
	}

	/**
	 * creates a message display
	 * @returns {domNodes} div containing message
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "message",
	 * 		"content": {
	 * 			"img": "str profile-picture url",
	 * 			"user": "str name",
	 * 			"text": "str well... text",
	 * 			"date": "str timestamp",
	 * 			"unseen": "null|int styled like notifications for conversations overview"
	 * 		},
	 * 		"attributes":{
	 * 			"onclick" : "event applies to whole container",
	 * 			"class": "right, conversation",
	 * 			"ICON_onclick": "event only applies to user icon"
	 * 		}
	 * 	}
	 */
	message() {
		let message, icon, p, date, unseen, onclick_forward;
		message = document.createElement("div");

		if (this.currentElement.attributes !== undefined && this.currentElement.attributes.ICON_onclick !== undefined) {
			// ugly but effective
			onclick_forward = this.currentElement.attributes.ICON_onclick;
			delete this.currentElement.attributes.ICON_onclick;
		}

		if (this.currentElement.content.img != undefined) {
			icon = document.createElement("img");
			icon.src = this.currentElement.content.img;
			icon.alt = api._lang.GET("assemble.render.aria.image", { ":image": this.currentElement.content.user });
			if (onclick_forward) {
				icon.onclick = new Function(onclick_forward);
				icon.title = api._lang.GET("message.forward");
				icon.role = "link";
			}
			message.append(icon);
		}
		p = document.createElement("p");
		p.append(document.createTextNode(this.currentElement.content.user));
		message.append(p);

		p = document.createElement("p");
		date = document.createElement("small");
		date.append(document.createTextNode(this.currentElement.content.date));
		p.append(date);
		message.append(p);

		let display = this.currentElement.content.text.split(/\r|\n/);
		for (const line of display) {
			p = document.createElement("p");
			// extract and convert links
			// this assumes links are always preceded by some text!!!!
			let textbetween = line.split(/<a.+?\/a>/),
				links = [...line.matchAll(/<a.+?\/a>/g)],
				link,
				a;

			for (let i = 0; i < textbetween.length; i++) {
				p.append(document.createTextNode(textbetween[i]));
				if (i in links) {
					link = links[i][0].match(/(href="(.+?)")( onclick="(.+?)")*>(.+?)</);
					a = document.createElement("a");
					a.href = link[2];
					if (link[4]) a.onclick = new Function(link[4]);
					a.classList.add("inline");
					a.appendChild(document.createTextNode(link[5]));
					p.append(a);
				}
			}
			if (links.length > textbetween.length) {
				for (let j = i; j < links.length; j++) {
					link = links[i][0].match(/(href="(.+?)")( onclick="(.+?)")*>(.+?)</);
					a = document.createElement("a");
					a.href = link[2];
					if (link[4]) a.onclick = new Function(link[4]);
					a.classList.add("inline");
					a.appendChild(document.createTextNode(link[5]));
					p.append(a);
				}
			}
			message.append(p);
		}

		if (this.currentElement.content.unseen != undefined && this.currentElement.content.unseen) {
			unseen = document.createElement("div");
			unseen.append(document.createTextNode(this.currentElement.content.unseen));
			message.append(unseen);
		}
		if (this.currentElement.attributes !== undefined) {
			message = this.apply_attributes(this.currentElement.attributes, message);
			if ("onclick" in this.currentElement.attributes) {
				message.role = "link";
				message.title = api._lang.GET("message.no_messages");
			}
		}
		message.classList.add("message");
		if (this.currentElement.dirright != undefined && this.currentElement.dirright) message.classList.add("right");

		return [message];
	}

	/**
	 * creates a default view for no database content
	 * @returns {domNodes} img, span
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "nocontent",
	 * 		"content": "why are there tally marks on my arm?"
	 * 	}
	 * ```
	 */
	nocontent() {
		const img = document.createElement("div");
		const span = document.createElement("span");
		span.append(document.createTextNode(this.currentElement.content));
		img.classList.add("nocontent");
		span.classList.add("nocontent");
		// accessibility setting;
		img.setAttribute("aria-hidden", true);
		return [img, span];
	}

	/**
	 * creates a number input
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "number",
	 * 		"attributes": {
	 * 			"name": "variable name"
	 * 		}
	 * 	}
	 * ```
	 */
	number() {
		return this.input("number");
	}

	/**
	 * creates a photo upload or file upload for desktops
	 * @returns {domNodes} header, input, img, button, resetbutton, hint
	 * @example this.currentElement
	 * ```json
	 *  {
	 * 		"type": "photo",
	 * 		"attributes": {
	 * 			"name": "photo upload",
	 * 			"multiple": true|undefined
	 * 		}
	 * 		"hint": "this photo serves as..."
	 * 	}
	 * ```
	 */
	photo() {
		let input = document.createElement("input"),
			button = document.createElement("button"),
			img = document.createElement("img"),
			resetbutton = document.createElement("button"),
			addbutton = document.createElement("button"),
			hint = [...this.hint()],
			multiple;
		this.currentElement.description = this.currentElement.attributes.name.replace(/\[\]/g, "");
		if (this.currentElement.attributes && this.currentElement.attributes.multiple) {
			multiple = true;
			if (!this.currentElement.attributes.name.endsWith("[]")) this.currentElement.attributes.name += "[]";
			// delete for input apply_attributes
			delete this.currentElement.attributes.multiple;
		}

		function changeEvent() {
			this.nextSibling.nextSibling.innerHTML = this.files.length
				? Array.from(this.files)
						.map((x) => x.name)
						.join(", ") +
				  " " +
				  api._lang.GET("assemble.render.photo_rechoose")
				: api._lang.GET("assemble.render.photo_choose");
			if (this.files.length) {
				this.nextSibling.src = URL.createObjectURL(this.files[0]);
				this.nextSibling.onload = () => {
					URL.revokeObjectURL(this.nextSibling.src);
				};
			} else this.nextSibling.src = "";
		}

		input.type = "file";
		input.id = getNextElementID();
		input.accept = "image/jpeg, image/gif, image/png";
		input.capture = true;
		input.onchange = changeEvent;
		input = this.apply_attributes(this.currentElement.attributes, input);
		input.setAttribute("aria-label", this.currentElement.description);
		button.onclick = () => {
			document.getElementById(input.id).click();
		};
		button.type = "button";
		button.dataset.type = "photo";
		button.classList.add("inlinebutton");
		button.appendChild(document.createTextNode(api._lang.GET("assemble.render.photo_choose")));
		// accessibility setting; hide "select"-button, as input can be accessed directly
		button.tabIndex = -1;
		button.setAttribute("aria-hidden", true);

		img.classList.add("photoupload");
		img.alt = api._lang.GET("assemble.render.aria.preview");

		resetbutton.onclick = () => {
			let e = document.getElementById(input.id);
			e.value = "";
			e.dispatchEvent(new Event("change"));
		};
		resetbutton.appendChild(document.createTextNode(api._lang.GET("assemble.render.reset")));
		resetbutton.dataset.type = "reset";
		resetbutton.classList.add("inlinebutton");
		resetbutton.type = "button";

		if (multiple) this.currentElement.attributes.multiple = true; // reapply after input apply_attributes
		const photoElementClone = structuredClone(this.currentElement);
		addbutton.onclick = function () {
			new Assemble({
				content: [[photoElementClone]],
				composer: "elementClone",
			}).initializeSection(null, hint.length ? hint[0] : resetbutton);
		};
		addbutton.dataset.type = "additem";
		addbutton.classList.add("inlinebutton");
		addbutton.type = "button";
		addbutton.title = api._lang.GET("assemble.render.aria.add");

		return [...this.header(), input, img, button, multiple ? addbutton : [], resetbutton, ...hint];
	}

	/**
	 * creates a product selection input type text with button to open up an api product search
	 * @returns {domNodes} icon, input, button, label, hint
	 * @example this.currentElement
	 * ```json
	 *  {
	 * 		"type": "productselection",
	 * 		"hint": "somethingsomething"...,
	 * 		"numeration": "anything resulting in true to prevent enumeration",
	 * 		"attributes": {
	 * 			"name": "variable name will be used as label",
	 * 			"multiple": "bool on changing the input field another appends"
	 * 		}
	 * 	}
	 * ```
	 */
	productselection() {
		let input = document.createElement("input"),
			label = document.createElement("label"),
			span = document.createElement("span"),
			button = document.createElement("button"),
			hint = [...this.hint()];
		const productselectionClone = structuredClone(this.currentElement);
		input.type = "text";
		input.id = this.currentElement.attributes && this.currentElement.attributes.id ? this.currentElement.attributes.id : getNextElementID();
		input.autocomplete = "off";
		span.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]|DEFAULT_/g, "")));
		label.classList.add("productselection");
		if (this.currentElement.attributes.required) span.dataset.required = true;
		if (this.currentElement.attributes.multiple) label.dataset.multiple = "multiple";
		if (this.currentElement.attributes["data-filtered"]) label.dataset.filtered = this.currentElement.attributes["data-filtered"];

		this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		if (this.currentElement.attributes.multiple) {
			input.onchange = () => {
				// arrow function for reference of this.names
				if (input.value) {
					productselectionClone.attributes.name = productselectionClone.attributes.name.replace(/\(\d+\)$/gm, "");
					delete productselectionClone.attributes.value;
					new Assemble({
						content: [[productselectionClone]],
						composer: "elementClone",
						names: this.names,
					}).initializeSection(null, hint.length ? hint[0] : label);
				}
			};
		}
		input = this.apply_attributes(this.currentElement.attributes, input);

		label.dataset.type = this.currentElement.type;
		label.append(span, input);

		button.classList.add("productselection");
		button.dataset.type = "search";
		button.title = api._lang.GET("assemble.render.aria.search");
		button.onclick = function () {
			const options = {};
			options[api._lang.GET("assemble.compose.document.document_cancel")] = false;
			options[api._lang.GET("assemble.compose.document.document_confirm")] = { value: true, class: "reducedCTA" };
			new Dialog({
				type: "input",
				header: api._lang.GET("consumables.product.search"),
				render: [
					[
						{
							type: "scanner",
							destination: "productsearch",
						},
						{
							type: "search",
							attributes: {
								name: api._lang.GET("consumables.product.search"),
								onkeypress: "if (event.key === 'Enter') {api.purchase('get', 'productsearch', 'null', this.value, 'productselection'); return false;}",
								onblur: "api.purchase('get', 'productsearch', 'null', this.value, 'productselection'); return false;",
								id: "productsearch",
							},
						},
						{
							type: "hidden",
							attributes: {
								name: "_selectedproduct",
								id: "_selectedproduct",
							},
						},
						{ type: "hr" },
					],
				],
				options: options,
			}).then((response) => {
				if (response) {
					const inputfield = document.getElementById(input.id);
					inputfield.value = response._selectedproduct;
					inputfield.dispatchEvent(new Event("change"));
				}
			});
		};
		return [label, button, ...hint];
	}

	/**
	 * creates radio inputs
	 * @returns {domNodes} header if applicable, inputs and labels, hint if applicable, br
	 * @see this.checkbox()
	 */
	radio() {
		return this.checkbox("radioinstead");
	}

	/**
	 * creates a range input with default datalist if not provided otherwise
	 * @returns {domNodes} header, input, hint
	 * @example this.currentElement
	 * ```json
	 *  {
	 * 		"type": "range",
	 * 		"attributes": {
	 * 			"name": "range",
	 * 			"min": 0,
	 * 			"max": 100,
	 * 			"step": 5
	 * 		},
	 * 		"datalist": ["some", "predefined", "values"]
	 * 	OR	"datalist": [{"label": "some", "value": "a"}, {"label": "predefined", "value": "b"}, {"label": "values", "value": "c"}]
	 * 		"hint": "from 0 to 100 in 20 steps"
	 * 	}
	 * ```
	 */
	range() {
		let input = document.createElement("input"),
			label = document.createElement("label"),
			span = document.createElement("span"),
			hint = this.hint(),
			datalist;
		input.type = "range";
		input.id = this.currentElement.attributes && this.currentElement.attributes.id ? this.currentElement.attributes.id : getNextElementID();
		this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		this.currentElement.description = this.currentElement.attributes.name;
		input = this.apply_attributes(this.currentElement.attributes, input);

		span.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]|DEFAULT_/g, "")));
		label.dataset.type = this.currentElement.type;
		label.append(span, input);

		if (this.currentElement.datalist != undefined) {
			datalist = document.createElement("datalist");
			let option, textlabels, textlabel;
			datalist.id = getNextElementID();
			textlabels = document.createElement("div");
			textlabels.classList.add("rangedatalist");
			this.currentElement.datalist.forEach((key) => {
				option = document.createElement("option");
				option.append(document.createTextNode(key));
				textlabel = document.createElement("span");
				textlabel.append(document.createTextNode(key));
				textlabels.append(textlabel);
				datalist.appendChild(option);
			});
			input.setAttribute("list", datalist.id);
			input.min = 0;
			input.max = this.currentElement.datalist.length - 1;
			return [datalist, label, textlabels, ...hint];
		} else if (this.currentElement.attributes.step !== "any") {
			datalist = document.createElement("datalist");
			let option;
			datalist.id = getNextElementID();
			for (
				let step = this.currentElement.attributes.min ? Number(this.currentElement.attributes.min) : 0;
				step <= (this.currentElement.attributes.max ? Number(this.currentElement.attributes.max) : 100);
				step += this.currentElement.attributes.step ? Number(this.currentElement.attributes.step) : 1
			) {
				option = document.createElement("option");
				option.value = step;
				datalist.appendChild(option);
			}
			input.setAttribute("list", datalist.id);
			return [datalist, label, ...hint, ...this.br()];
		}
		return [...this.header(), input, ...hint];
	}

	/**
	 * creates a scanner input with scanner dialog
	 * @returns {domNodes} icon, input, label, hint, additional buttons if applicable
	 * @example this.currentElement
	 * ```json
	 *  {
	 * 		"type": "scanner",
	 * 		"description": "access credentials",
	 * 		"attributes":{
	 * 			"name": "input name",
	 * 			"type": "password, default text override e.g. for logins",
	 * 			"multiple": "true|undefined, to clone after successful import"
	 * 		},
	 * 		"destination": "elementId force output to other input, e.g. search"
	 * 	}
	 * ```
	 */
	scanner() {
		let result = [],
			input,
			inputid,
			label,
			span,
			multiple,
			originaltype = this.currentElement.type;
		const scannerElementClone = structuredClone(this.currentElement);
		if (this.currentElement.attributes && this.currentElement.attributes.multiple) {
			multiple = true;
			// delete for input apply_attributes
			delete this.currentElement.attributes.multiple;
			this.currentElement.hint = this.currentElement.hint ? this.currentElement.hint + " " + api._lang.GET("assemble.render.scan_multiple") : api._lang.GET("assemble.render.scan_multiple");
		}

		if (this.currentElement.destination !== undefined) {
			inputid = this.currentElement.destination;
		} else {
			input = document.createElement("input");
			span = document.createElement("span");
			label = document.createElement("label");
			input.type = "text";
			input.id = inputid = getNextElementID();
			input.autocomplete = input.type === "password" ? "one-time-code" : "off";
			if (this.currentElement.attributes) {
				span.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]|IDENTIFY_BY_/g, "")));
				if (this.currentElement.attributes.required) span.dataset.required = true;
				if (this.currentElement.attributes.multiple) label.dataset.multiple = "multiple";
				if (this.currentElement.attributes["data-filtered"]) label.dataset.filtered = this.currentElement.attributes["data-filtered"];

				this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
				input = this.apply_attributes(this.currentElement.attributes, input);
			}
			if (multiple) {
				input.onchange = () => {
					// arrow function for reference of this.names
					if (input.value) {
						scannerElementClone.attributes.name = scannerElementClone.attributes.name.replace(/\(\d+\)$/gm, "");
						new Assemble({
							content: [[scannerElementClone]],
							composer: "elementClone",
							names: this.names,
						}).initializeSection(null, button);
					}
				};
			}
			if (input.type === "password") this.currentElement.type = "password"; // for icon

			label.dataset.type = this.currentElement.type;
			label.append(span, input);

			result = result.concat([label, ...this.hint()]);
			this.currentElement.type = originaltype;
		}

		if (multiple) this.currentElement.attributes.multiple = true;

		let button = document.createElement("button");
		button.appendChild(
			document.createTextNode(
				this.currentElement.description
					? this.currentElement.description
					: api._lang.GET("assemble.render.scan_button", { ":field": this.currentElement.attributes ? this.currentElement.attributes.name.replace(/\[\]|IDENTIFY_BY_/g, "") : api._lang.GET("assemble.render.aria.scan_button_generic") })
			)
		);
		button.type = "button";
		button.dataset.type = "scanner";

		button.onclick = () => {
			// arrow function for reference of this.names
			new Dialog({
				type: "scanner",
			}).then((response) => {
				if (response.scanner) {
					document.getElementById(inputid).value = response.scanner;
					if (multiple) {
						scannerElementClone.attributes.name = scannerElementClone.attributes.name.replace(/\(\d+\)$/gm, "");
						new Assemble({
							content: [[scannerElementClone]],
							composer: "elementClone",
							names: this.names,
						}).initializeSection(null, button);
					}
				}
			});
		};
		result.push(button);

		if (originaltype === "identify") {
			let button = document.createElement("button");
			button.appendChild(document.createTextNode(this.currentElement.description ? this.currentElement.description : api._lang.GET("assemble.render.merge")));
			button.type = "button";
			button.dataset.type = "merge";
			button.onclick = function () {
				if (document.getElementById(inputid).value) api.record("get", "import", document.getElementById(inputid).value);
			};
			result.push(button);

			for (const [label, setting] of Object.entries(api._settings.config.label)) {
				button = document.createElement("button");
				button.appendChild(document.createTextNode(api._lang.GET("record.create_identifier_type", { ":format": setting.format })));
				button.type = "button";
				button.onclick = function () {
					if (document.getElementById(inputid).value) _client.application.postLabelSheet(document.getElementById(inputid).value, true, { _type: label });
				};
				result.push(button);
			}
		}
		return result;
	}

	/**
	 * creates a search input
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "search",
	 * 		"attributes": {
	 * 			"name": "variable name"
	 * 		}
	 * 	}
	 * ```
	 */
	search() {
		return this.input("search");
	}

	/**
	 * creates a select input with dialog modal, optgpoups by default id content sorted and > 12
	 * @returns {domNodes} icon, select, label, hint
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "select",
	 * 		"hint": "this is a list",
	 * 		"numeration": "anything resulting in true to prevent enumeration",
	 * 		"content": {
	 * 			"entry one": {
	 * 				"value": 1
	 * 			},
	 * 			"entry two": {
	 * 				"value": "2",
	 * 				"selected": true
	 * 			}
	 * 		},
	 * 		"attributes": {
	 * 			"name": "variable name",
	 * 			"multiple": "bool another one will be appended if selection has value, not a classic multiple selection though"
	 * 		},
	 * 	}
	 * ```
	 */
	select() {
		const groups = {};
		let select = document.createElement("select"),
			label = document.createElement("label"),
			span = document.createElement("span"),
			selectModal = {},
			multiple,
			selectElementClone,
			hint,
			attributes,
			elements;
		if (this.currentElement.attributes.multiple) {
			multiple = true;
			label.dataset.multiple = "multiple";
			delete this.currentElement.attributes.multiple;
			selectElementClone = structuredClone(this.currentElement);
			this.currentElement.hint = this.currentElement.hint ? this.currentElement.hint + " " + api._lang.GET("assemble.render.select_multiple") : api._lang.GET("assemble.render.select_multiple");
		}
		hint = this.hint();
		select.title = this.currentElement.attributes.name.replace(/\[\]/g, "");
		select.id = getNextElementID();

		if (this.currentElement.attributes.required) span.dataset.required = true;
		if (this.currentElement.attributes["data-filtered"]) label.dataset.filtered = this.currentElement.attributes["data-filtered"];

		this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		select = this.apply_attributes(this.currentElement.attributes, select);

		if (JSON.stringify(Object.keys(this.currentElement.content)) === JSON.stringify(Object.keys(this.currentElement.content).sort())) {
			Object.keys(this.currentElement.content)
				.sort()
				.forEach((key) => {
					attributes = this.currentElement.content[key];
					if (groups[key[0]] === undefined) groups[key[0]] = [[key, attributes]];
					else groups[key[0]].push([key, attributes]);
					selectModal[key] = attributes.value || key;
				});
			Object.keys(groups)
				.sort()
				.forEach((group) => {
					elements = groups[group];
					let optgroup = document.createElement("optgroup");
					optgroup.label = group;
					for (const element of Object.entries(elements)) {
						let option = document.createElement("option");
						option = this.apply_attributes(element[1][1], option);
						option.appendChild(document.createTextNode(element[1][0]));
						optgroup.appendChild(option);
					}
					select.appendChild(optgroup);
				});
		} else {
			for (const [key, attributes] of Object.entries(this.currentElement.content)) {
				let option = document.createElement("option");
				option = this.apply_attributes(attributes, option);
				option.appendChild(document.createTextNode(key));
				select.appendChild(option);
				selectModal[key] = attributes.value || key;
			}
		}

		// no onclick because accessability does not mind about a modal and can navigate the generic selection by default
		select.onpointerdown = (e) => {
			// arrow function for reference of this.names
			e.preventDefault();
			if (!e.target.disabled)
				new Dialog({
					type: "select",
					header: select.title,
					options: selectModal,
				}).then((response) => {
					if (response && response != e.target.value) {
						e.target.value = response;
						e.target.dispatchEvent(new Event("change"));
						if (multiple && !VOIDVALUES.includes(response)) {
							selectElementClone.attributes.name = selectElementClone.attributes.name.replace(/\(\d+\)$/gm, "");
							selectElementClone.attributes.multiple = true;
							new Assemble({
								content: [[selectElementClone]],
								composer: "elementClone",
								names: this.names,
							}).initializeSection(null, hint ? hint[0] : label);
						}
					}
				});
		};

		span.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]/g, "")));
		label.dataset.type = this.currentElement.type;
		label.append(span, select);

		return [label, ...hint];
	}

	/**
	 * creates a signaturePad
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "signature",
	 * 		"attributes": {
	 * 			"name": "signature",
	 * 			"required": "optional boolean"
	 * 		},
	 * 		"hint": "this signature is for..."
	 * 	}
	 * ```
	 */
	signature() {
		this.currentElement.description = this.currentElement.attributes.name.replace(/\[\]/g, "");
		let result = [...this.header()];
		const canvas = document.createElement("canvas");
		canvas.id = "signaturecanvas";
		if (this.currentElement.attributes.required) canvas.setAttribute("data-required", "required");
		canvas.title = api._lang.GET("assemble.render.aria.signature");
		result.push(canvas);
		const input = document.createElement("input");
		input.type = "file";
		input.id = "SIGNATURE";
		input.name = this.currentElement.attributes.name;
		input.hidden = true;
		input.tabIndex = -1;
		input.role = "none";
		input.setAttribute("aria-hidden", true);
		result.push(input);
		this.currentElement.attributes = {
			value: api._lang.GET("assemble.render.clear_signature"),
			type: "button",
			onclick: "signaturePad.clear()",
		};
		result = result.concat(this.hint());
		delete this.currentElement.hint;
		result = result.concat(this.deletebutton()); // hint would be added here as well
		this.signaturePad = true;
		return result;
	}

	/**
	 * creates an stlviewer div
	 * @returns {domNode} div
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "stlviewer",
	 * 		"description": "viewstl"
	 * 	}
	 * ```
	 */
	stlviewer() {
		const div = document.createElement("div");
		div.id = "stlviewer_canvas";
		div.classList = "stlviewer";
		return div;
	}

	/**
	 * creates a submit button (styled) for other contexts than general form submission
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "submitbutton",
	 * 		"attributes": {
	 * 			"value": "save that!",
	 *			"onclick": "some.submission.event()"
	 *		}
	 * 	}
	 * ```
	 */
	submitbutton() {
		// to style it properly by adding data-type to article container
		this.currentElement.attributes["data-type"] = "submitbutton";
		this.currentElement.attributes.type = "button"; // avoid submitting twice
		return this.button();
	}

	/**
	 * creates a tel input
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "tel",
	 * 		"attributes": {
	 * 			"name": "variable name"
	 * 		}
	 * 	}
	 * ```
	 */
	tel() {
		return this.input("tel");
	}

	/**
	 * creates a text input
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "text",
	 * 		"attributes": {
	 * 			"name": "variable name"
	 * 		}
	 * 	}
	 * ```
	 */
	text() {
		return this.input("text");
	}

	/**
	 * creates a text input whose content is SUPPOSED to be inserted into clipboard
	 * not by default though, because this is used as a cta as well
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "text_copy",
	 * 		"attributes": {
	 * 			"name": "variable name",
	 * 			"onclick": "_client.application.toClipboard(this)"
	 * 		}
	 * 	}
	 * ```
	 */
	text_copy() {
		return this.input("text");
	}

	/**
	 * creates a textarea with optional access to texttemplates by api request
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "textarea",
	 * 		"hint": "enter a lot of text",
	 * 		"texttemplates": "true or undefined to add a button opening text templates within a modal",
	 * 		"numeration": "anything resulting in true to prevent enumeration",
	 * 		"editor": "anything resulting in true to add line numbers",
	 * 		"attributes": {
	 * 			"name": "somename",
	 * 			"rows": 8,
	 * 			"value": "values can be passed with this pseudo attribute"
	 * 		},
	 * 		"autocomplete": [...string values]
	 * 	}
	 * the optional autocomplete-property in composed widgets for documents will be populated with a autocomplete data set from the api side if respective contents are found based on the widgets name
	 * ```
	 */
	textarea() {
		let textarea = document.createElement("textarea"),
			label = document.createElement("label"),
			span = document.createElement("span");

		textarea.id = getNextElementID();
		textarea.autocomplete = "off";
		this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		span.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]/g, "")));
		if (this.currentElement.attributes.required) span.dataset.required = true;
		textarea = this.apply_attributes(this.currentElement.attributes, textarea);
		if (this.currentElement.attributes.value !== undefined) textarea.appendChild(document.createTextNode(this.currentElement.attributes.value));
		if (this.currentElement.texttemplates !== undefined && this.currentElement.texttemplates) {
			this.currentElement.attributes = {
				value: api._lang.GET("menu.communication.texttemplate_texts"),
				type: "button",
				onclick: "api.texttemplate('get', 'text', 'false', 'modal')",
				class: "floatright",
			};
			delete this.currentElement.hint;
		}
		if (this.currentElement.editor) {
			let div = document.createElement("div"),
				linenumber = document.createElement("div");
			linenumber.id = getNextElementID();
			div.classList.add("editor");
			linenumber.classList.add("line-numbers");
			linenumber.append(document.createElement("span"));
			div.append(linenumber, textarea);
			textarea.addEventListener("keyup", (event) => {
				const numberOfLines = event.target.value.split("\n").length;
				document.getElementById(linenumber.id).innerHTML = Array(numberOfLines).fill("<span></span>").join("");
			});
			div.append(document.createElement("br"));
			textarea = div;
		}

		if (this.currentElement.autocomplete && this.currentElement.autocomplete.length) {
			/**
			 * adds a *simple* autocomplete option for textareas with keyup event listener
			 * appends rest of match if the input so far matches one of the datalist options.
			 * use Alt and AltGr to navigate within results for being the most unintrusive key
			 * has to be provided with unique entries!
			 */
			let autocomplete = this.currentElement.autocomplete;
			const forthKey = api._settings.user.app_settings.autocomplete_forth || "Alt",
				backKey = api._settings.user.app_settings.autocomplete_back || "AltGraph";
			textarea.addEventListener("keydown", (event) => {
				if ([forthKey, backKey].includes(event.key)) {
					event.preventDefault();
				}
			});
			textarea.addEventListener("keyup", (event) => {
				if ((event.key.startsWith("Arrow") || event.key.startsWith("Back")) && ![forthKey, backKey].includes(event.key)) return;
				if ([forthKey, backKey].includes(event.key)) {
					event.preventDefault();
				}
				let cursorPosition = event.target.selectionStart,
					matches = [],
					start = event.target.value.substring(0, event.target.selectionStart),
					end = event.target.value.substring(event.target.selectionEnd),
					words,
					tail;
				// gather possible matches
				// to handle adding at the end the content is rewinded from the back
				words = start.split(" ");
				for (let i = 0; i < words.length; i++) {
					tail = words.slice(i * -1).join(" ");
					if (!tail.length) continue;
					for (const option of autocomplete) {
						if (option.startsWith(tail)) {
							matches.push(option.substring(tail.length));
						}
					}
				}
				if (matches.length) {
					// navigate through matches with alt key
					if (TEXTAREA_AUTOCOMPLETE_INDEX === null) TEXTAREA_AUTOCOMPLETE_INDEX = 0;
					if (TEXTAREA_AUTOCOMPLETE_INDEX > -1 && [forthKey, backKey].includes(event.key)) {
						if (event.key === forthKey) TEXTAREA_AUTOCOMPLETE_INDEX++;
						if (event.key === backKey) TEXTAREA_AUTOCOMPLETE_INDEX--;
						// out of bound
						if (TEXTAREA_AUTOCOMPLETE_INDEX > matches.length - 1) TEXTAREA_AUTOCOMPLETE_INDEX = 0;
						if (TEXTAREA_AUTOCOMPLETE_INDEX < 0) TEXTAREA_AUTOCOMPLETE_INDEX = matches.length - 1;
					} else TEXTAREA_AUTOCOMPLETE_INDEX = 0; // fallback
					event.target.value = start + matches[TEXTAREA_AUTOCOMPLETE_INDEX] + end;
					event.target.selectionEnd = (start + matches[TEXTAREA_AUTOCOMPLETE_INDEX]).length;
				} else TEXTAREA_AUTOCOMPLETE_INDEX = null;
				event.target.selectionStart = cursorPosition;
			});
			this.currentElement.hint = this.currentElement.hint
				? this.currentElement.hint + " " + api._lang.GET("assemble.render.textarea_autocomplete", { ":forth": forthKey, ":back": backKey })
				: api._lang.GET("assemble.render.textarea_autocomplete", { ":forth": forthKey, ":back": backKey });
		}

		label.dataset.type = this.currentElement.type;
		label.append(span, textarea);

		if (this.currentElement.texttemplates !== undefined && this.currentElement.texttemplates) return [label, ...this.hint(), ...this.button(), ...this.br()];
		return [label, ...this.hint()];
	}

	/**
	 * creates a textarea whose content will be inserted into clipboard
	 * @returns {domNodes}
	 * @see this.textarea()
	 */
	textarea_copy() {
		this.currentElement.attributes.onclick = "_client.application.toClipboard(this)";
		return this.textarea();
	}

	/**
	 * creates an informative text paragraph
	 * @returns {domNodes} header, paragraph, hint, occasionally encapsulated for styling reasons to not be 100% width
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "textsection",
	 * 		"content": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua."
	 * 		"linkedcontent": "may contain simple links that are parsed. sourcecode only, no user input <a href="javascript:somefunction()">lorem ipsum</a>"
	 * 		"attributes": {
	 * 			"name": "very informative, content of header, former description"
	 * 			"otherattribute": "value applies to header"
	 * 		}
	 * 	}
	 */
	textsection() {
		let result = [],
			p,
			content,
			imagealigned = false;
		if (this.currentElement.attributes && this.currentElement.attributes.name) {
			this.currentElement.description = this.currentElement.attributes.name;
			result = result.concat(this.header());
			delete this.currentElement.attributes.name;
		}
		if (this.currentElement.content) {
			p = document.createElement("p");
			p.append(...this.linebreak(this.currentElement.content));
			result.push(p);
		}
		if (this.currentElement.linkedcontent) {
			content = this.currentElement.linkedcontent.matchAll(/(.*?)(?:\\n|\n|<br.\/>|<br>|$)/gm);
			p = document.createElement("p");
			for (const part of content) {
				if (!part[1].length) continue;
				// extract and convert links
				// this assumes links are always preceded by some text!!!!
				let textbetween = part[1].split(/<a.+?\/a>/),
					links = [...part[1].matchAll(/<a.+?\/a>/g)],
					link,
					a;

				for (let i = 0; i < textbetween.length; i++) {
					p.append(document.createTextNode(textbetween[i]));
					if (i in links) {
						link = links[i][0].match(/(href="(.+?)")( onclick="(.+?)")*>(.+?)</);
						a = document.createElement("a");
						a.href = link[2];
						if (link[4]) a.onclick = new Function(link[4]);
						a.classList.add("inline");
						a.appendChild(document.createTextNode(link[5]));
						p.append(a);
					}
				}
				if (links.length > textbetween.length) {
					for (let j = i; j < links.length; j++) {
						link = links[j][0].match(/(href="(.+?)")( onclick="(.+?)")*>(.+?)</);
						a = document.createElement("a");
						a.href = link[2];
						if (link[4]) a.onclick = new Function(link[4]);
						a.classList.add("inline");
						a.appendChild(document.createTextNode(link[5]));
						p.append(a);
					}
				}
				p.append(document.createElement("br"));
			}
			result.push(p);
		}

		if (this.currentElement.attributes !== undefined) {
			if (this.currentElement.attributes.class && this.currentElement.attributes.class.match(/imagealigned/)) {
				imagealigned = true;
				this.currentElement.attributes.class = this.currentElement.attributes.class.replace(/imagealigned/, "");
			}
			result[0] = this.apply_attributes(this.currentElement.attributes, result[0]);
		}

		result = result.concat(this.hint());

		if (imagealigned) {
			const container = document.createElement("div");
			container.classList.add("imagealigned");
			container.append(...result);
			return [container];
		}

		return result;
	}

	/**
	 * create a styled tile with provided content
	 * @required this.currentElement
	 * @returns encapsulated content widgets
	 * @example ```json
	 * 	{
	 * 		type: 'tile',
	 * 		attributes : {eg. dataset values for filtering},
	 * 		content: [array of any defined type]
	 * 	}
	 * ```
	 */
	tile() {
		let article = document.createElement("article");
		if (this.currentElement.attributes !== undefined) article = this.apply_attributes(this.currentElement.attributes, article);
		for (const element of this.currentElement.content) {
			this.currentElement = element;
			article.append(...this[element.type]());
		}
		return [article];
	}

	/**
	 * creates a time input
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "time",
	 * 		"attributes": {
	 * 			"name": "variable name"
	 * 		}
	 * 	}
	 * ```
	 */
	time() {
		return this.input("time");
	}

	transfer() {
		let cal = [],
			units = Math.ceil(3.5 * 12 * 2),
			preset=[],
			daytile,
			header,
			current;
		this.currentElement.description = this.currentElement.attributes.name !== undefined ? this.currentElement.attributes.name : "";
		for (const [name, timeunit] of Object.entries(this.currentElement.content)) {
			header = document.createElement("header");
			header.append(document.createTextNode(name));
			cal.push(header);

			for (let i = 0; i < units; i++) {
				daytile = document.createElement("div");
				daytile.style = "border:1px solid black; margin:0; width:calc(90% / " + units + "); height:1em; display:inline-block";
				daytile.onclick = (e) => {
					e.target.style.backgroundColor = document.getElementById("_current").value;
				};
				daytile.addEventListener("pointerdown", (e) => {
					e.target.style.backgroundColor = document.getElementById("_current").value;
					window.POINTERDOWN = true;
				});
				daytile.addEventListener("pointerup", (e) => {
					delete window.POINTERDOWN;
				});
				daytile.addEventListener("pointerenter", (e) => {
					if (window.POINTERDOWN) e.target.style.backgroundColor = document.getElementById("_current").value;
				});
				daytile.addEventListener("contextmenu", (e) => {
					e.preventDefault();
				});
				cal.push(daytile);
			}
		}
		current = document.createElement("input");
		current.type = "hidden";
		current.id = "_current";
		preset.push(current);
		for (const [name, color] of Object.entries(this.currentElement.preset)) {
			current = document.createElement("input");
			current.type = "color";
			current.name = name;
			current.value = color;
			current.addEventListener("click", (e) => {
				document.getElementById("_current").value = e.target.value;
			});
			current.addEventListener("change", (e) => {
				document.getElementById("_current").value = e.target.value;
			});
			preset.push(current);
		}
		return [...this.header(), ...cal, ...preset];
	}

	/**
	 * feature poor method but necessary to display the delete-area for composer or possibly other future use
	 */
	trash() {
		const span = document.createElement("span");
		span.dataset.type = "trash";
		span.append(document.createTextNode(this.currentElement.description));
		return [span];
	}
}

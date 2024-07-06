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

/*
this module helps to assemble forms according to the passed simplified object notation.
*/
import SignaturePad from "../libraries/signature_pad.umd.js";
import QrCreator from "../libraries/qr-creator.js";

var ElementID = 0,
	signaturecanvas = null;

export function getNextElementID() {
	return "elementID" + ++ElementID;
}

const events = ["onclick", "onmouseover", "onmouseout", "onchange", "onpointerdown", "onpointerup"];

export const assemble_helper = {
	getNextElementID: getNextElementID,
	exportCanvas: function (id, name) {
		document.getElementById(id).toBlob(
			function (blob) {
				const blobUrl = URL.createObjectURL(blob),
					link = document.createElement("a"); // Or maybe get it from the current document
				link.href = blobUrl;
				link.download = name + ".png";
				link.click();
			},
			"image/png",
			1
		);
	},
	userMenu: function (content) {
		if (!content) return;
		const menu = document.querySelector("nav"),
			elements = [],
			icons = {};
		icons[LANG.GET("menu.application_header")] = "url('./media/bars.svg')";
		icons[LANG.GET("menu.communication_header")] = "url('./media/comment.svg')";
		icons[LANG.GET("menu.record_header")] = "url('./media/file-signature.svg')";
		icons[LANG.GET("menu.calendar_header")] = "url('./media/calendar-alt.svg')";
		icons[LANG.GET("menu.purchase_header")] = "url('./media/shopping-bag.svg')";
		icons[LANG.GET("menu.files_header")] = "url('./media/folders.svg')";
		icons[LANG.GET("menu.tools_header")] = "url('./media/tools.svg')";

		let label, div, input, div2, button, span;
		for (const [group, items] of Object.entries(content)) {
			label = document.createElement("label");
			div = document.createElement("div");

			label.htmlFor = "userMenu" + group;
			label.style.maskImage = label.style.webkitMaskImage = icons[group];
			div.setAttribute("data-for", "userMenu" + group.replace(" ", "_"));
			div.setAttribute("data-notification", 0);
			div.append(label);

			input = document.createElement("input");
			input.type = "radio";
			input.name = "userMenu";
			input.id = "userMenu" + group;

			div2 = document.createElement("div");
			div2.classList.add("options");
			span = document.createElement("span");
			span.append(document.createTextNode(group));
			div2.append(span);
			div2.style.maxHeight = (Object.entries(items).length + 1) * 4 + "em";
			for (const [description, attributes] of Object.entries(items)) {
				if ("onpointerup" in attributes) {
					button = document.createElement("button");
					for (const [attribute, value] of Object.entries(attributes)) {
						button.setAttribute(attribute, value);
					}
					button.type = "button";
					button.classList.add("discreetButton");
					button.setAttribute("data-for", "userMenuItem" + description.replace(" ", "_"));
					button.setAttribute("data-notification", 0);
					button.appendChild(document.createTextNode(description));
					div2.append(button);
				} else {
					span = document.createElement("span");
					span.append(document.createTextNode(description));
					div2.append(span);
				}
			}
			elements.push(div);
			elements.push(input);
			elements.push(div2);
		}
		menu.replaceChildren(...elements);
		_serviceWorker.postMessage("getnotifications");
	},
};

export class Dialog {
	/**
	 *
	 * @param {type: str, icon: str, header: str, render: str|array, options:{displayText: value str|bool|{value, class}}} options
	 * @returns promise, prepared answer on resolve according to type
	 *
	 * new Dialog({options}).then(response => {
	 * 		doSomethingWithButtonValue(response);
	 * 	});
	 *
	 * select and scanner will be implemented by Assemble
	 *
	 * options with boolean values true|false as well as 'true'|'false' return selected booleans not strings. other strings will be returned as such.
	 *
	 * new Dialog({type:'alert', header:'acknowledge this!', render:'infotext'})
	 *
	 * new Dialog({type:'confirm', header:'confirm this', options:{'abort': false, 'yes, i agree': {'value': true, class: 'reducedCTA'}}}).then(confirmation => {
	 *  	if (confirmation) huzzah());
	 * 	});
	 *
	 * input needs button options as well, response keys in accordance to assemble content input names
	 * image and signature are NOT supported for having to be rendered in advance to filling their canvases.
	 * multiple articles and sections are NOT supported due to simplified query selector
	 * THIS IS FOR SIMPLE INPUTS ONLY
	 *
	 * new Dialog({type:'input', header:'fill out assembled form', render: [assemble_content], options:{'abort': false, 'submit': {'value': true, class: 'reducedCTA'}}}).then(response => {
	 *  	if (Object.keys(response)) console.log('these are the results of the form:', response);
	 * 	});
	 *
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
			if (this.type === "input") modal = "inputmodal";
			if (this.type === "input2") {
				modal = "inputmodal2";
				this.type = "input";
			}
			if (this.type === "input") {
				if (this.render.content === undefined) this.render = { content: this.render };
				this.assemble = new Assemble(this.render);
			}
			dialog = document.getElementById(modal);

			const form = document.createElement("form");
			if (this.type === "message") {
				form.dataset.usecase = "message";
				this.type = "input";
			}
			form.method = "dialog";
			const img = document.createElement("img");
			img.classList.add("close");
			img.src = "./media/times.svg";
			img.onpointerup = new Function("const scanner = document.querySelector('video'); if (scanner) scanner.srcObject.getTracks()[0].stop(); document.getElementById('" + modal + "').close()");
			form.append(img);
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
			if (this.type === "select") form.style.display = "flex";
			for (const element of this[this.type]()) {
				if (element) form.append(element);
			}
			dialog.replaceChildren(form);
			if (this.type === "scanner") {
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
					}),
				};

				function scanSuccess(decodedText, decodedResult) {
					scanner.output.value = decodedText;
					scanner.button.removeAttribute("disabled");
					scanner.scanner.html5Qrcode.stop();
					scanner.canvas.style.border = "none";
					scanner.canvas.replaceChildren(document.createTextNode(LANG.GET("general.scan_successful")));
				}
				scanner.scanner.render(scanSuccess);
			}
			if (this.assemble) this.assemble.processAfterInsertion();
			return new Promise((resolve, reject) => {
				dialog.showModal();
				dialog.onclose = resolve;
			}).then((response) => {
				let result;

				function getValues(parent) {
					let result = {};
					parent.childNodes.forEach((node) => {
						if (["input", "textarea", "select"].includes(node.localName) && node.value) {
							if (["checkbox", "radio"].includes(node.type) && node.checked === true) result[node.name] = node.value;
							else if (!["checkbox", "radio"].includes(node.type)) result[node.name] = node.value;
						} else result = { ...result, ...getValues(node) };
					});
					return result;
				}

				switch (this.type) {
					case "select":
						return response.target.returnValue;
					case "confirm":
						result = response.target.returnValue;
						if (result === "true") return true;
						else if (result === "false") return false;
						return result;
					default:
						if (response.target.returnValue === "true") {
							result = getValues(dialog);
							return result;
						}
						return false;
				}
			});
		}
		dialog.close();
	}

	alert() {
		const button = document.createElement("button");
		button.value = true;
		button.append(document.createTextNode(LANG.GET("general.ok_button")));
		return [button];
	}
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
	select() {
		const buttons = document.createElement("div");
		let button, firststring, optgroup;
		for (const [option, value] of Object.entries(this.options)) {
			if (Object.entries(this.options).length > 12 && firststring !== option.substring(0, 1)) {
				firststring = option.substring(0, 1);
				optgroup = document.createElement("h3");
				optgroup.classList.add("modaloptgroup");
				optgroup.append(document.createTextNode(firststring));
				buttons.append(optgroup);
			}
			button = document.createElement("button");
			button.classList.add("discreetButton");
			button.append(document.createTextNode(option));
			button.value = value;
			buttons.append(button);
		}
		return [buttons];
	}
	input() {
		let result = [...this.assemble.initializeSection(null, null, "iCanHasNodes")];
		if (Object.keys(this.options).length) result = result.concat(this.confirm());
		else result = result.concat(this.alert());
		return result;
	}
	scanner() {
		const div = document.createElement("div"),
			input = document.createElement("input"),
			button = document.createElement("button");
		div.classList.add("scanner");
		div.id = getNextElementID();
		input.type = "hidden";
		input.name = "scanner";
		button.append(document.createTextNode(LANG.GET("general.import_scan_result_button_from_modal")));
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
}

export class Toast {
	/**
	 *
	 * @param str message
	 * @param str type success|error|info
	 * @param int duration in milliseconds
	 *
	 * new Toast('message', 'success')
	 *
	 * duration is a bit fuzzy, idk why
	 *
	 */
	constructor(message = "", type = "", duration = 4000) {
		this.message = message || undefined;
		this.duration = duration;
		this.toast = document.getElementById("toast");
		this.toast.removeAttribute("class");
		if (typeof this.message !== "undefined") {
			const closeimg = document.createElement("img"),
				pauseimg = document.createElement("img"),
				msg = document.createElement("span"),
				div = document.createElement("div");
			if (type) this.toast.classList.add(type);
			closeimg.classList.add("close");
			closeimg.src = "./media/times.svg";
			closeimg.onpointerdown = new Function("document.getElementById('toast').close();");
			pauseimg.classList.add("pause");
			pauseimg.src = "./media/equals.svg";
			pauseimg.onpointerdown = new Function("window.clearTimeout(window.toasttimeout);");
			msg.innerHTML = message;
			this.toast.replaceChildren(closeimg, pauseimg, msg, div);
			this.toast.show();
			this.countdown();
		} else this.toast.close();
	}
	countdown(percent = 100) {
		const countdowndiv = document.querySelector("#toast>div");
		countdowndiv.style.width = percent + "%";
		window.toasttimeout = window.setTimeout(this.countdown.bind(this), this.duration / 1000, percent - 1000 / this.duration);
		if (percent < 0) {
			window.clearTimeout(window.toasttimeout);
			this.toast.close();
		}
	}
}

export class Assemble {
	/* 
	assembles forms and screen elements.
	deepest nesting of input object is three levels
	form:null or {attributes} / nothing creates just a div e.g. just for text and links
	content:[ see this.processContent() ]

	elements are assembled by default but can be assigned common html attributes
	names are mandatory for input elements
	*/
	constructor(setup) {
		this.content = setup.content;
		this.form = setup.form;
		this.section = null;
		this.imageQrCode = [];
		this.imageBarCode = [];
		this.imageUrl = [];
		this.names = {};
		this.composer = setup.composer;
	}
	/**
	 *
	 * @param {*domNode} nextSibling inserts before node, used by utility.js order_client
	 * @param {*domNode} formerSibling inserts after node, used on multiple photo or scanner type
	 * @param {*any} returnOnlyNodes return nodes without container, used by Dialog
	 * @returns container or nodes
	 */
	initializeSection(nextSibling = null, formerSibling = null, returnOnlyNodes = null) {
		if (typeof nextSibling === "string") nextSibling = document.querySelector(nextSibling);
		if (this.form && !nextSibling && !returnOnlyNodes) {
			this.section = document.createElement("form");
			this.section.method = "post";
			this.section.enctype = "multipart/form-data";
			this.section = this.apply_attributes(this.form, this.section);
			this.content.push([
				{
					type: "submitbutton",
					attributes: {
						value: LANG.GET("general.submit_button"),
						type: "submit",
					},
				},
			]);
		} else if (this.composer === "photoOrScanner") this.section = formerSibling.parentNode;
		else if (!this.composer) this.section = document.createElement("div");

		this.assembledPanels = this.processContent();

		if (!(nextSibling || formerSibling || returnOnlyNodes || this.composer) || this.composer === "photoOrScanner") {
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

	processAfterInsertion() {
		const scrollables = document.querySelectorAll("section");
		for (const section of scrollables) {
			if (section.childNodes.length > 1) section.addEventListener("scroll", this.sectionScroller);
			section.dispatchEvent(new Event("scroll"));
		}

		const trash = document.querySelector("[data-type=trash");
		if (trash) compose_helper.composer_add_trash(trash.parentNode);

		if (this.signaturePad) {
			this.initialize_SignaturePad();
		}
		if (this.imageQrCode.length) {
			// availableSettings = ['text', 'radius', 'ecLevel', 'fill', 'background', 'size']
			for (const image of this.imageQrCode) {
				QrCreator.render(
					{
						text: image.content,
						size: 1024,
						ecLevel: "H",
						background: null,
						fill: "#000000",
						radius: 1,
					},
					document.getElementById(image.id)
				);
			}
		}
		if (this.imageBarCode.length) {
			for (const image of this.imageBarCode) {
				JsBarcode("#" + image.id, image.content.value, {
					format: image.content.format || "CODE128",
					background: "transparent",
					displayValue: image.content.displayValue != undefined ? image.content.displayValue : true,
				});
			}
		}
		if (this.imageUrl.length) {
			for (const image of this.imageUrl) {
				let imgcanvas = document.getElementById(image.id),
					img = new Image();
				img.src = image.content;
				img.addEventListener("load", function (e) {
					let x,
						y,
						w = this.width,
						h = this.height,
						xoffset = 0,
						yoffset = 0;
					if (w >= h) {
						x = imgcanvas.width;
						y = (imgcanvas.height * h) / w;
						yoffset = (x - y) / 2;
					} else {
						x = (imgcanvas.width * w) / h;
						y = imgcanvas.height;
						xoffset = (y - x) / 2;
					}
					imgcanvas.getContext("2d").drawImage(this, xoffset, yoffset, x, y);
				});
				img.dispatchEvent(new Event("load"));
			}
		}
	}

	processPanel(elements) {
		/**
		 * content to exist of three nestings
		 * [ panel article>section
		 * 		[ slide article
		 * 			{ element },
		 * 			{ element }
		 * 		],
		 * 		[ slide article
		 *		...],
		 * ],
		 *
		 * or two nestings
		 * [ panel article
		 * 		{ element },
		 * 		{ element }
		 * ]
		 */
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
				console.trace(elements.type, e);
				return false;
			}
		}
		return content;
	}

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

	slider(sectionID, length) {
		if (length < 2) return;
		const indicators = document.createElement("div"),
			toleft = document.createElement("button"),
			toright = document.createElement("button");
		indicators.classList = "sectionindicator";
		indicators.id = sectionID + "indicator";

		toleft.addEventListener("pointerup", function (e) {
			document.getElementById(sectionID).scrollBy({
				top: 0,
				left: -400,
				behaviour: "smooth",
			});
		});
		toleft.setAttribute("data-type", "toleft");
		toleft.classList.add("inlinebutton");
		toleft.type = "button";
		indicators.appendChild(toleft);
		for (let i = 0; i < length; i++) {
			let indicator = document.createElementNS("http://www.w3.org/2000/svg", "svg"),
				circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");
			indicator.classList = "articleindicator";
			indicator.setAttributeNS(null, "viewbox", "0 0 10 10");
			circle.setAttributeNS(null, "cx", "5");
			circle.setAttributeNS(null, "cy", "5");
			circle.setAttributeNS(null, "r", "4");
			indicator.appendChild(circle);
			indicators.appendChild(indicator);
		}
		toright.addEventListener("pointerup", function (e) {
			document.getElementById(sectionID).scrollBy({
				top: 0,
				left: 400,
				behaviour: "smooth",
			});
		});
		toright.setAttribute("data-type", "toright");
		toright.classList.add("inlinebutton");
		toright.type = "button";
		indicators.appendChild(toright);
		return indicators;
	}

	sectionScroller(e) {
		/* event handler for horizontal scrolling of multiple panels */
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

	prepareForm(event) {
		/* check input fields for presence of required content */
		const signature = document.getElementById("signaturecanvas"),
			requiredsignature = document.querySelector("[data-required=required]"),
			required = document.querySelectorAll("[required]");
		let missing_required = false;

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
		if (!missing_required) {
			if (!event.target.form.dataset.confirm) {
				event.target.form.submit();
				return;
			}
			const options = {};
			options[LANG.GET("assemble.compose_form_cancel")] = false;
			options[LANG.GET("assemble.compose_form_confirm")] = { value: true, class: "reducedCTA" };
			new Dialog({ type: "confirm", header: LANG.GET("assemble.compose_form"), options: options }).then((confirmation) => {
				if (confirmation) event.target.form.submit();
			});
		} else new Toast(LANG.GET("general.missing_form_data"), "error");
	}

	initialize_SignaturePad() {
		signaturecanvas = document.getElementById("signaturecanvas");
		window.signaturePad = new SignaturePad(signaturecanvas, {
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
		signaturecanvas.width = signaturecanvas.offsetWidth * ratio;
		signaturecanvas.height = signaturecanvas.offsetHeight * ratio;
		signaturecanvas.getContext("2d").scale(ratio, ratio);
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

	icon() {
		const br = document.createElement("br"),
			span = document.createElement("span");
		span.setAttribute("data-type", this.currentElement.type);
		return [br, span];
	}

	header() {
		if (this.currentElement.description === undefined) return [];
		let header = document.createElement("header");
		header.appendChild(document.createTextNode(this.currentElement.description));
		header.setAttribute("data-type", this.currentElement.type);
		if (this.currentElement["data-filtered"]) header.dataset.filtered = this.currentElement["data-filtered"];
		return [header];
	}

	hint() {
		if (!this.currentElement.hint) return [];
		let div = document.createElement("div");
		div.classList.add("hint");
		const content = this.currentElement.hint.matchAll(/(.*?)(?:\\n|\n|<br.\/>|<br>|$)/gm);
		for (const part of content) {
			if (!part[1].length) continue;
			div.appendChild(document.createTextNode(part[1]));
			div.appendChild(document.createElement("br"));
		}
		if (["textarea", "links"].includes(this.currentElement.type)) div.classList.add(this.currentElement.type + "-hint");
		return [div];
	}

	apply_attributes(setup, node) {
		for (const [key, attribute] of Object.entries(setup)) {
			if (events.includes(key)) {
				if (attribute) node[key] = new Function(attribute);
			} else {
				if (attribute) node.setAttribute(key, attribute);
			}
		}
		return node;
	}

	names_numerator(name, dontnumerate = undefined) {
		if (dontnumerate || [...name.matchAll(/\[\]/g)].length) return name;
		if (name in this.names) {
			this.names[name] += 1;
			return name + "(" + this.names[name] + ")";
		}
		this.names[name] = 1;
		return name;
	}

	textblock() {
		/* {
			type: 'textblock',
			description: 'very informative',
			content: 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.'
			attributes: {
				attribute: value // applies to header
			}
		}*/
		let result = [],
			p;
		if (this.currentElement.description) {
			result = result.concat(this.header());
		}
		if (this.currentElement.content) {
			const content = this.currentElement.content.matchAll(/(.*?)(?:\\n|\n|<br.\/>|<br>|$)/gm);
			p = document.createElement("p");
			for (const part of content) {
				if (!part[1].length) continue;
				p.append(document.createTextNode(part[1]));
				p.append(document.createElement("br"));
			}
			result.push(p);
		}
		if (this.currentElement.attributes !== undefined) {
			result[0] = this.apply_attributes(this.currentElement.attributes, result[0]);
		}
		result = result.concat(this.hint());
		return result;
	}

	input(type) {
		/*{
			type: 'text',
			hint: 'please provide information about...',
			numeration: anything resulting in true to prevent enumeration
			attributes: {
				name: 'variable name' // will be used as an accessible placeholder
			}
		}*/
		let input = document.createElement("input"),
			label;
		input.type = type;
		if (type === "password") this.currentElement.type = "password";
		input.id = this.currentElement.attributes && this.currentElement.attributes.id ? this.currentElement.attributes.id : getNextElementID();
		input.autocomplete = (this.currentElement.attributes && this.currentElement.attributes.type) === "password" ? "one-time-code" : "off";
		label = document.createElement("label");
		label.htmlFor = input.id;
		label.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]|DEFAULT_/g, "")));
		this.currentElement.attributes.placeholder = " "; // to access input:not(:placeholder-shown) query selector
		label.classList.add("input-label");

		if (this.currentElement.attributes.name !== undefined) this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		input = this.apply_attributes(this.currentElement.attributes, input);
		if (type === "email") input.multiple = true;

		if (type === "checkbox2text") {
			input.type = "text";
			const inputvalue = [];
			for (const [key, value] of Object.entries(this.currentElement.content)) {
				if (value.checked != undefined && value.checked) inputvalue.push(key);
			}
			input.value = inputvalue.join(", ");
			input.onpointerup = new Function(
				"new Dialog({type: 'input', header: '" +
					this.currentElement.attributes.name.replace(/\[\]/g, "") +
					"', render:" +
					JSON.stringify([
						[
							{
								type: "checkbox",
								content: this.currentElement.content,
							},
						],
					]) +
					", " +
					"options:{" +
					"'" +
					LANG.GET("assemble.compose_form_cancel") +
					"': false," +
					"'" +
					LANG.GET("assemble.compose_form_confirm") +
					"': {value: true, class: 'reducedCTA'}," +
					"}}).then(response => { const e = document.getElementById('" +
					input.id +
					"'); if (Object.keys(response).length) { e.value = Object.keys(response).join(', ');}; e.dispatchEvent(new Event('change'));})"
			);
		}

		if (this.currentElement.attributes.hidden !== undefined) return input;
		return [...this.icon(), input, label, ...this.hint()];
	}
	text() {
		return this.input("text");
	}
	number() {
		return this.input("number");
	}
	date() {
		return this.input("date");
	}
	time() {
		return this.input("time");
	}
	search() {
		return this.input("search");
	}
	filtered() {
		// filter appears to be reserved
		return this.input("search");
	}
	tel() {
		return this.input("tel");
	}
	email() {
		return this.input("email");
	}
	checkbox2text() {
		// returns a text typed input with onpointerup modal with checkbox selection
		// requires additional options property on this.currentElement containing an options object
		// {'name':{value:str|int, checked: bool}}
		return this.input("checkbox2text");
	}
	hidden() {
		/*{
			type: 'hidden',
			numeration: anything resulting in true to prevent enumeration
			attributes: {
				name: 'name',
				value: '3.14'}
			}
		}*/
		let input = document.createElement("input");
		input.type = "hidden";
		input.id = getNextElementID();
		input.value = this.currentElement.value;
		if (this.currentElement.attributes.name !== undefined) this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		if (this.currentElement.attributes !== undefined) input = this.apply_attributes(this.currentElement.attributes, input);
		return input;
	}
	checkbox(radio = null) {
		/*{
			type: 'checkbox', or 'radio'
			description:'checkboxes',
			numeration: anything resulting in true to prevent enumeration
			content: {
				'Checkbox 1': {
					optional attributes
				},
				'Checkbox 2': {
					optional attributes
				}
			},
			hint: 'this selection is for...'
		}*/
		const result = [...this.header()],
			radioname = this.currentElement.attributes && this.currentElement.attributes.name ? this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration) : null; // keep same name for current article
		for (const [checkbox, attributes] of Object.entries(this.currentElement.content)) {
			let label = document.createElement("label"),
				input = document.createElement("input");
			input.id = getNextElementID();
			if (radio) {
				label.classList.add("radio");
				input.type = "radio";
				input.name = radioname;
				input.value = checkbox;
			} else {
				label.classList.add("checkbox");
				input.type = "checkbox";
				input.dataset.grouped = this.currentElement.description;
				input.name = this.names_numerator(checkbox);
			}
			label.append(document.createTextNode(checkbox.replace(/\[\]/g, "")));
			input = this.apply_attributes(attributes, input);
			label.htmlFor = input.id;
			if (input.dataset.filtered) label.dataset.filtered = input.dataset.filtered;
			result.push(input);
			result.push(label);
		}
		return [...result, ...this.hint(), document.createElement("br")];
	}
	radio() {
		this.currentElement.description = this.currentElement.attributes.name.replace(/\[\]/g, "");
		return this.checkbox("radioinstead");
	}
	file() {
		/*{
			type: 'file',
			attributes: {
				name: 'file upload',
				multiple: true
			}
			hint: 'this file serves as...'
		}*/
		let input = document.createElement("input"),
			label = document.createElement("button"),
			button = document.createElement("button");
		input.type = "file";
		input.id = getNextElementID();
		this.currentElement.description = this.currentElement.attributes.name.replace(/\[\]/g, "");
		if (this.currentElement.attributes.multiple) {
			if (!this.currentElement.attributes.name.endsWith("[]")) this.currentElement.attributes.name += "[]";
		}

		input = this.apply_attributes(this.currentElement.attributes, input);
		if (this.currentElement.attributes.multiple !== undefined)
			input.onchange = function () {
				this.nextSibling.innerHTML = this.files.length
					? Array.from(this.files)
							.map((x) => x.name)
							.join(", ") +
					  " " +
					  LANG.GET("assemble.files_rechoose")
					: LANG.GET("assemble.files_choose");
			};
		else
			input.onchange = function () {
				this.nextSibling.innerHTML = this.files.length
					? Array.from(this.files)
							.map((x) => x.name)
							.join(", ") +
					  " " +
					  LANG.GET("assemble.file_rechoose")
					: LANG.GET("assemble.file_choose");
			};
		label.onpointerup = new Function("document.getElementById('" + input.id + "').click();");
		label.type = "button";
		label.setAttribute("data-type", "file");
		label.classList.add("inlinebutton");
		label.appendChild(document.createTextNode(this.currentElement.attributes.multiple !== undefined ? LANG.GET("assemble.files_choose") : LANG.GET("assemble.file_choose")));

		button.onpointerup = new Function("let e=document.getElementById('" + input.id + "'); e.value=''; e.dispatchEvent(new Event('change'));");
		button.appendChild(document.createTextNode("Reset"));
		button.type = "button";
		button.setAttribute("data-type", "reset");
		button.classList.add("inlinebutton");
		return [...this.header(), input, label, button, ...this.hint()];
	}
	range() {
		/*{
			type: 'range',
			attributes: {
				name: 'range',
				min: 0,
				max: 100,
				step: 5
			}
			hint: 'from 0 to 100 in 20 steps'
		}*/
		let input = document.createElement("input");
		input.type = "range";
		input.id = this.currentElement.attributes && this.currentElement.attributes.id ? this.currentElement.attributes.id : getNextElementID();
		if (this.currentElement.attributes.name !== undefined) this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		this.currentElement.description = this.currentElement.attributes.name;
		input = this.apply_attributes(this.currentElement.attributes, input);
		if (!this.currentElement.attributes.list && this.currentElement.attributes.step !== "any") {
			let datalist = document.createElement("datalist");
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
			return [datalist, ...this.header(), input, ...this.hint()];
		}
		return [...this.header(), input, ...this.hint()];
	}

	datalist() {
		let datalist = document.createElement("datalist");
		let option;
		datalist.id = getNextElementID();
		if (this.currentElement.attributes !== undefined) datalist = this.apply_attributes(this.currentElement.attributes, datalist);
		this.currentElement.content.forEach((key) => {
			option = document.createElement("option");
			if (typeof key === "string") option.value = key;
			else option = this.apply_attributes(key, option);
			datalist.appendChild(option);
		});
		return datalist;
	}

	select() {
		/*{
			type: 'select',
			hint: 'this is a list',
			numeration: anything resulting in true to prevent enumeration
			content: {
				'entry one': {
					value: '1'
				},
				'entry two': {
					value: '2',
					selected: true
				}
			}
			attributes: {
				name: 'variable name'
			},
		}*/
		const groups = {};
		let select = document.createElement("select"),
			label,
			selectModal = {};
		if (this.currentElement.attributes.name !== undefined) this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		select.title = this.currentElement.attributes.name.replace(/\[\]/g, "");
		select.id = getNextElementID();
		if (this.currentElement.attributes !== undefined) select = this.apply_attributes(this.currentElement.attributes, select);

		for (const [key, element] of Object.entries(this.currentElement.content)) {
			if (groups[key[0]] === undefined) groups[key[0]] = [[key, element]];
			else groups[key[0]].push([key, element]);
			selectModal[key] = element.value || key;
		}
		for (const [group, elements] of Object.entries(groups)) {
			let optgroup = document.createElement("optgroup");
			optgroup.label = group;
			for (const element of Object.entries(elements)) {
				let option = document.createElement("option");
				option = this.apply_attributes(element[1][1], option);
				option.appendChild(document.createTextNode(element[1][0]));
				optgroup.appendChild(option);
			}
			select.appendChild(optgroup);
		}
		label = document.createElement("label");
		label.htmlFor = select.id;
		label.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]/g, "")));
		label.classList.add("input-label");
		select.addEventListener("pointerdown", (e) => {
			e.preventDefault();
			if (!e.target.disabled)
				new Dialog({
					type: "select",
					header: select.title,
					options: selectModal,
				}).then((response) => {
					e.target.value = response;
					e.target.dispatchEvent(new Event("change"));
				});
		});
		return [...this.icon(), select, label, ...this.hint()];
	}

	textarea() {
		/*{
			type: 'textarea',
			hint: 'enter a lot of text',
			texttemplates: true or undefined to add a button opening text templates within a modal
			numeration: anything resulting in true to prevent enumeration
			attributes: {
				name:'somename'
				rows:8,
				value:'values can be passed with this pseudo attribute'
			}
		}*/
		let textarea = document.createElement("textarea"),
			label;
		textarea.id = getNextElementID();
		textarea.autocomplete = "off";
		if (this.currentElement.attributes.name !== undefined) {
			this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
			label = document.createElement("label");
			label.htmlFor = textarea.id;
			label.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]/g, "")));
			label.classList.add("textarea-label");
		}
		if (this.currentElement.attributes !== undefined) textarea = this.apply_attributes(this.currentElement.attributes, textarea);
		if (this.currentElement.attributes.value !== undefined) textarea.appendChild(document.createTextNode(this.currentElement.attributes.value));

		if (this.currentElement.texttemplates !== undefined && this.currentElement.texttemplates) {
			const preservedHint = this.hint();
			this.currentElement.attributes = {
				value: LANG.GET("menu.texttemplate_texts"),
				type: "button",
				onpointerup: "api.texttemplate('get', 'text', 'false', 'modal')",
				class: "floatright",
			};
			delete this.currentElement.hint;
			return [...this.icon(), label, textarea, ...preservedHint, ...this.button(), this.br()];
		}
		return [...this.icon(), label, textarea, ...this.hint()];
	}

	button() {
		/*{
			type: 'button',
			hint: 'this button does this or that'
			attributes: {
				value: 'this is displayed on the button',
				onpointerdown: 'alert("hello")'
			}
		}*/
		let button = document.createElement("button");
		button.id = getNextElementID();
		if (this.currentElement.attributes.value !== undefined) {
			button.appendChild(document.createTextNode(this.currentElement.attributes.value));
			delete this.currentElement.attributes.value;
		}
		if (this.currentElement.attributes !== undefined) button = this.apply_attributes(this.currentElement.attributes, button);
		if (this.currentElement.type === "submitbutton") button.onpointerup = this.prepareForm.bind(this);
		return [button, ...this.hint()];
	}
	deletebutton() {
		// to style it properly by adding data-type to article container
		this.currentElement.attributes["data-type"] = "deletebutton";
		return this.button();
	}
	submitbutton() {
		// to style it properly by adding data-type to article container
		this.currentElement.attributes["data-type"] = "submitbutton";
		this.currentElement.attributes.type = "button"; // avoid submitting twice
		return this.button();
	}
	calendarbutton() {
		// to style it properly by adding data-type to article container
		this.currentElement.attributes["data-type"] = "calendarbutton";
		this.currentElement.attributes.type = "button"; // avoid submitting twice
		return [this.br(), ...this.button()];
	}
	formbutton() {
		// to style it properly by adding data-type to article container
		this.currentElement.attributes["data-type"] = "formbutton";
		this.currentElement.attributes.type = "button"; // avoid submitting twice
		return [this.br(), ...this.button()];
	}

	photo() {
		/*{
			type: 'photo',
			attributes: {
				name: 'photo upload',
				multiple: true|undefined
			}
			hint: 'this photo serves as...'
		}*/
		let input = document.createElement("input"),
			button = document.createElement("button"),
			img = document.createElement("img"),
			resetbutton = document.createElement("button"),
			addbutton = document.createElement("button"),
			hint = [...this.hint()],
			multiple;
		this.currentElement.description = this.currentElement.attributes.name.replace(/\[\]/g, "");
		if (this.currentElement.attributes.multiple) {
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
				  LANG.GET("assemble.photo_rechoose")
				: LANG.GET("assemble.photo_choose");
			if (this.files.length) this.nextSibling.src = URL.createObjectURL(this.files[0]);
			else this.nextSibling.src = "";
		}

		input.type = "file";
		input.id = getNextElementID();
		input.accept = "image/jpeg, image/gif, image/png";
		input.capture = true;
		input.onchange = changeEvent;
		input = this.apply_attributes(this.currentElement.attributes, input);
		button.onpointerup = new Function("document.getElementById('" + input.id + "').click();");
		button.type = "button";
		button.setAttribute("data-type", "photo");
		button.classList.add("inlinebutton");
		button.appendChild(document.createTextNode(LANG.GET("assemble.photo_choose")));

		img.classList.add("photoupload");

		resetbutton.onpointerup = new Function("let e=document.getElementById('" + input.id + "'); e.value=''; e.dispatchEvent(new Event('change'));");
		resetbutton.appendChild(document.createTextNode(LANG.GET("assemble.reset")));
		resetbutton.setAttribute("data-type", "reset");
		resetbutton.classList.add("inlinebutton");
		resetbutton.type = "button";

		if (multiple) this.currentElement.attributes.multiple = true; // reapply after input apply_attributes
		const photoElementClone = structuredClone(this.currentElement);
		addbutton.onpointerup = function () {
			new Assemble({
				content: [[photoElementClone]],
				composer: "photoOrScanner",
			}).initializeSection(null, hint.length ? hint : resetbutton);
		};
		addbutton.setAttribute("data-type", "additem");
		addbutton.classList.add("inlinebutton");
		addbutton.type = "button";

		return [...this.header(), input, img, button, multiple ? addbutton : [], resetbutton, ...hint];
	}

	links() {
		/*{
			type: 'links',
			description:'links',
			content: {
				'Link 1': {
					href: '#'
				},
				'Link 2': {
					href: '#',
					onpointerdown: 'alert(\'hello\')'
				}
			},
			hint: 'these links serve the purpose of...'
			data-filtered: any
		}*/
		let result = [...this.header()];
		if (this.currentElement.attributes !== undefined) result.push(this.hidden());
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

	signature() {
		/*{
			type: 'signature',
			attributes: {
				name: 'signature',
				required: optional boolean
			},
			hint: 'this signature is for...'
		} */
		this.currentElement.description = this.currentElement.attributes.name.replace(/\[\]/g, "");
		let result = [...this.header()];
		const canvas = document.createElement("canvas");
		canvas.id = "signaturecanvas";
		if (this.currentElement.attributes.required) canvas.setAttribute("data-required", "required");
		result.push(canvas);
		const input = document.createElement("input");
		input.type = "file";
		input.id = "SIGNATURE";
		input.name = this.currentElement.attributes.name;
		input.hidden = true;
		result.push(input);
		this.currentElement.attributes = {
			value: LANG.GET("assemble.clear_signature"),
			type: "button",
			onpointerup: "signaturePad.clear()",
		};
		result = result.concat(this.deletebutton()); // hint will be added here as well
		this.signaturePad = true;
		return result;
	}

	scanner() {
		/*{
			type: 'scanner',
			description:'access credentials' (e.g.),
			attributes:{name: 'input name', type:'password', multiple: true|undefined} // type: to override e.g. for logins, multiple: to clone after successful import
			destination: elementId // force output to other input, e.g. search
		} */
		let result = [],
			input,
			inputid,
			label,
			multiple,
			originaltype = this.currentElement.type;
		if (this.currentElement.attributes && this.currentElement.attributes.multiple) {
			multiple = true;
			// delete for input apply_attributes
			delete this.currentElement.attributes.multiple;
			this.currentElement.hint = this.currentElement.hint ? this.currentElement.hint + " " + LANG.GET("assemble.scan_multiple") : LANG.GET("assemble.scan_multiple");
		}

		if (this.currentElement.destination !== undefined) {
			inputid = this.currentElement.destination;
		} else {
			input = document.createElement("input");
			input.type = "text";
			input.id = inputid = getNextElementID();
			input.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
			input.placeholder = " "; // to access input:not(:placeholder-shown) query selector
			if (this.currentElement.attributes) input = this.apply_attributes(this.currentElement.attributes, input);
			input.autocomplete = input.type === "password" ? "one-time-code" : "off";

			label = document.createElement("label");
			label.htmlFor = input.id;
			label.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]|IDENTIFY_BY_/g, "")));
			label.classList.add("input-label");
			if (input.type === "password") this.currentElement.type = "password"; // for icon
			result = result.concat([...this.icon(), input, label, ...this.hint()]);
			this.currentElement.type = originaltype;
		}

		if (multiple) this.currentElement.attributes.multiple = true;
		const scannerElementClone = structuredClone(this.currentElement);

		let button = document.createElement("button");
		button.appendChild(document.createTextNode(this.currentElement.description ? this.currentElement.description : LANG.GET("assemble.scan_button")));
		button.type = "button";
		button.setAttribute("data-type", "scanner");

		button.onpointerup = function () {
			new Dialog({
				type: "scanner",
			}).then((response) => {
				if (response.scanner) {
					document.getElementById(inputid).value = response.scanner;
					if (multiple) {
						new Assemble({
							content: [[scannerElementClone]],
							composer: "photoOrScanner",
						}).initializeSection(null, button);
					}
				}
			});
		};
		result.push(button);

		if (originaltype === "identify") {
			let button = document.createElement("button");
			button.appendChild(document.createTextNode(this.currentElement.description ? this.currentElement.description : LANG.GET("assemble.compose_merge")));
			button.type = "button";
			button.setAttribute("data-type", "merge");
			button.onpointerup = function () {
				if (document.getElementById(inputid).value) api.record("get", "import", document.getElementById(inputid).value);
			};
			result.push(button);
		}
		return result;
	}

	identify() {
		this.currentElement.attributes.name = "IDENTIFY_BY_" + this.currentElement.attributes.name;
		return this.scanner();
	}

	image() {
		/*{
			type: 'image',
			description:'export image' (e.g.),
			attributes:{
				name: 'exportname', // atypical use of generic attributes on this one
				qrcode: 'e.g. token', // for display of a qrcode with this value
				barcode: {value:'e.g. token', format: see documentation}, // for display of a barcode with this value
				url: 'base64 encoded string || url' // for display of an image
				imageonly: {inline styles overriding .imagecanvas} || undefined // flag to display without download button
			}
		} */
		let result = [];
		const canvas = document.createElement("canvas");
		let disabled = true;
		canvas.id = getNextElementID();
		canvas.classList.add("imagecanvas");
		if (typeof this.currentElement.attributes.imageonly === "object") {
			for (const [key, value] of Object.entries(this.currentElement.attributes.imageonly)) {
				canvas.style[key] = value;
			}
		} else result = result.concat(this.header());

		canvas.width = canvas.height = 1024;
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

		result.push(canvas);

		if (!this.currentElement.attributes.imageonly) {
			//this tile does not process attributes, therefore they can be reassigned
			this.currentElement.attributes = {
				value: this.currentElement.description,
				type: "button",
				class: "inlinebutton",
				"data-type": this.currentElement.type,
				onpointerup: 'assemble_helper.exportCanvas("' + canvas.id + '", "' + this.currentElement.attributes.name + '")',
			};
			if (disabled) this.currentElement.attributes.disabled = true;
			result = result.concat(this.button());
		}
		return result;
	}

	tile() {
		/*{
			type: 'tile',
			attributes : {}, eg. dataset values for filtering
			content: [] array of any defined type
		} */
		let article = document.createElement("article");
		if (this.currentElement.attributes !== undefined) article = this.apply_attributes(this.currentElement.attributes, article);
		for (const element of this.currentElement.content) {
			this.currentElement = element;
			article.append(...this[element.type]());
		}
		return [article];
	}

	stlviewer() {
		/*{
			type: 'stlviewer',
			description:'viewstl' (e.g.),
		} */
		const div = document.createElement("div");
		div.id = "stlviewer_canvas";
		div.classList = "stlviewer";
		return div;
	}

	nocontent() {
		const img = document.createElement("div");
		const span = document.createElement("span");
		span.append(document.createTextNode(this.currentElement.content));
		img.classList.add("nocontent");
		span.classList.add("nocontent");
		return [img, span];
	}

	trash() {
		// empty method but necessary to display the delete-area for composer or other future use
		return [...this.icon(), document.createTextNode(this.currentElement.description)];
	}

	hr() {
		return document.createElement("hr");
	}

	br() {
		return document.createElement("br");
	}

	cart() {
		// empty method but neccessary for styling reasons (icon)
	}

	message() {
		// empty method but neccessary for styling reasons (icon)
	}

	filter() {
		// empty method but neccessary for styling reasons (icon)
	}

	calendar() {
		/*{
			type: 'calendar',
			content: [
				null,
				{
					date: 'Y-m-d',
					display: 'whatever text, weekday, day, number of appointments
				}, ...
			],
			api: schedule|timesheet
		} */
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
				daytile.onpointerup = function () {
					api.calendar("get", apicall, day.date, day.date);
				};
				if (day.today) daytile.classList.add("today");
				if (day.selected) daytile.classList.add("selected");
				if (day.holiday) daytile.classList.add("holiday");
			}
			cal.push(daytile);
		}
		return [...this.header(), ...cal];
	}

	message() {
		/*{
			type: 'message',
			content: {
				img: str profile-picture url,
				user: str name
				text: str well... text,
				date: str timestamp,
				unseen: null|int
			},
			attributes:{
				onpointerup
				class: right, conversation, 
			}
		} */
		let message, icondiv, icon, p, date, unseen;
		message = document.createElement("div");

		if (this.currentElement.content.img != undefined) {
			icondiv = document.createElement("div");
			icondiv.classList.add("image");
			icon = document.createElement("img");
			icon.src = this.currentElement.content.img;
			icondiv.append(icon);
			message.append(icondiv);
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
			p.append(document.createTextNode(line));
			message.append(p);
		}

		if (this.currentElement.content.unseen != undefined && this.currentElement.content.unseen) {
			unseen = document.createElement("div");
			unseen.append(document.createTextNode(this.currentElement.content.unseen));
			message.append(unseen);
		}
		if (this.currentElement.attributes !== undefined) message = this.apply_attributes(this.currentElement.attributes, message);
		message.classList.add("message");
		if (this.currentElement.dirright != undefined && this.currentElement.dirright) message.classList.add("right");

		return [message];
	}
}

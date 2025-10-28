/**
 * [CARO - Cloud Assisted Records and Operations](https://github.com/erroronline1/caro)
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

/*
this module helps to assemble content according to the passed simplified object notation.
*/
import QrCreator from "../libraries/qr-creator.js";
import Icons from "./icons.json" with { type: "json" };

export function getNextElementID() {
	return "elementID" + ++api._settings.session.elementId;
}

export const Polyfill = {
	/**
	 * download handler to match safaris special needs
	 * kudos: https://www.linkedin.com/pulse/how-i-ate-apple-downloading-pdf-mobile-safari-pwa-version-novikov-98cte
	 */
	a: function (a) {
		if (!a.dataset.type) a.dataset.type = "downloadlink";
		a.removeAttribute("target");

		if (!a.href.includes("javascript:") && (this.isIOS() || this.isStandalone() || !("download" in document.createElement("a")))) {
			let href = a.href;
			a.onclick = function () {
				fetch(href)
					.then((response) => response.blob())
					.then((blob) => {
						const fileURL = URL.createObjectURL(blob),
							pseudoa = document.createElement("a");
						pseudoa.href = fileURL;
						pseudoa.download = a.download;
						pseudoa.target = "_blank";
						document.body.appendChild(pseudoa);
						pseudoa.click();
						document.body.removeChild(pseudoa);
						URL.revokeObjectURL(fileURL);
					})
					.catch((error) => new Toast(error + " " + href));
			};
			a.href = "javascript: void(0)";
		}
		return a;
	},
	isIOS: () => {
		return /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.userAgent.includes("Mac") && "ontouchend" in document);
	},
	isStandalone: () => {
		return window.navigator.standalone || window.matchMedia("(display-mode: standalone)").matches;
	},

	downloadWarning: function () {
		if (!this.isIOS() || this.isStandalone()) return null;
		const span = document.createElement("span");
		span.append(document.createTextNode(api._lang.GET("general.safari.downloads")));
		span.classList.add("orange");
		return span;
	},
};

const EVENTS = ["onclick", "onmouseover", "onmouseout", "onchange", "onpointerdown", "onpointerup", "onkeyup", "onkeydown", "onfocus"];
const VOIDVALUES = ["", "..."];

export class Masonry {
	/**
	 * rearranges the content alternating masonry style by utilizing flex
	 *
	 * kudos: https://tobiasahlin.com/blog/masonry-with-css/
	 *
	 * this class has to be on global scope and its masonry method called upon all dimension and content changes:
	 * * window.Masonry = initiate MutationObserver on instatiation
	 * * collapsible execution => window.Masonry.masonry().catch(()=>{});
	 *
	 * Mutation observer does not fire for unknown reasons for
	 * * longtermplanning adding users and colours => window.Masonry.masonry()
	 * * multiple inputs appending after respective Assemble.initializeSection() => window.Masonry.masonry()
	 *
	 * performance is not that great at above 300 children of different heights:
	 * * at 300 orders stresstest render time is above 10 seconds
	 * -> limit masonry reordering to this.timeout as maximum added time to interface rendering since as of today only orders may be affected by this and shifting is not that crucial here
	 *
	 * breakpoints can be applied or revoked if necessary
	 */

	columns = null;
	cssBreakpoints = {
		"only screen and (min-width: 2000rem)": "only screen and (min-width: 110rem)",
		"only screen and (min-width: 3000rem)": "only screen and (min-width: 165rem)",
	};
	timeout = 5000; // milliseconds

	constructor() {
		this.observer = new MutationObserver(async (mutations) => {
			this.observer.disconnect();

			this.masonry(true)
				.then(() => {
					this.observer.observe(document.querySelector("main"), {
						childList: true,
						subtree: true,
					});
				})
				.catch(() => {
					// user settings do not have masonry enabled, no need to restart observing
				});
		});
		window.addEventListener("resize", async () => {
			this.observer.disconnect();

			this.masonry()
				.then(() => {
					this.observer.observe(document.querySelector("main"), {
						childList: true,
						subtree: true,
					});
				})
				.catch(() => {
					// user settings do not have masonry enabled, no need to restart observing
				});
		});
		this.observer.observe(document.querySelector("main"), {
			childList: true,
			subtree: true,
		});
	}

	/**
	 * sets stylesheet breakpoints
	 * @param {boolean} set
	 */
	async breakpoints(apply = false) {
		let stylesheet;
		for (const [i, sname] of Object.entries(document.styleSheets)) {
			if (!sname.href.includes("style.css")) continue;
			stylesheet = document.styleSheets[i].cssRules;
			break;
		}
		for (let i = 0; i < stylesheet.length; i++) {
			if (apply) {
				if (stylesheet[i].conditionText in this.cssBreakpoints) {
					stylesheet[i].media.mediaText = this.cssBreakpoints[stylesheet[i].conditionText];
				}
			} else {
				if (Object.values(this.cssBreakpoints).includes(stylesheet[i].conditionText)) {
					stylesheet[i].media.mediaText = Object.keys(this.cssBreakpoints).find((key) => this.cssBreakpoints[key] === stylesheet[i].conditionText);
				}
			}
		}
	}

	/**
	 * promise resolving to true after an element has been added to the dom
	 *
	 * kudos: https://stackoverflow.com/a/61511955/6087758
	 *
	 * @param {domNode} node
	 * @param {domNode} after
	 * @returns true
	 */
	async insertNodeAfter(node, after) {
		return new Promise((resolve, reject) => {
			const observer = new MutationObserver((mutations) => {
				observer.disconnect();
				resolve();
			});
			try {
				observer.observe(after.parentNode, {
					childList: true,
					subtree: true,
				});
			} catch (e) {
				reject(e);
			}
			after.after(node);
		});
	}

	/**
	 * iterates over children of container, with optional injections of column shifting invisible nodes
	 * sums up the heights of elements to limit the container height for proper stylesheet column style
	 * note, that this does not affect the order of elements for screen readers handling the sourcecode
	 *
	 * @param {boolean} init  whether to inject nodes for column shifting or not (just recalculating container height)
	 * @returns Promise resolving when finished, rejecting if user settings are not configured to masonry
	 */
	async masonry(init = false) {
		return new Promise(async (resolve, reject) => {
			// reevaluate masonry setting for anyone calling by default inline integration, e.g. collapsible
			if (!(api._settings && api._settings.user && api._settings.user.app_settings && api._settings.user.app_settings.masonry)) reject();
			const startTime = Date.now();
			// retrieve nodes
			let container = document.querySelector("main>form");
			if (!container || !container.firstChild || !["article", "button"].includes(container.firstChild.localName)) container = document.querySelector("main>div:first-of-type"); // e.g. in document composer, where an initial empty form is preplaced before visible content and later filled with shadow inputs
			if (!container || !container.firstChild || !["article", "button"].includes(container.firstChild.localName)) {
				resolve();
				return;
			}
			let children = [...container.childNodes];

			// get number of overall columns as per stylesheet breakpoints
			const columns = Math.round(container.getBoundingClientRect().width / container.firstChild.getBoundingClientRect().width);
			// determine need to recalculate insertions, whether by forced parameter or if column layout has changed since last time
			init = init || this.columns !== columns;

			// remove previous injections
			if (init) {
				this.columns = columns;
				// remove empty pseudo articles, disable transitions for otherwise messing up dimensions
				for (let c = 0; c < children.length; c++) {
					if (!(children[c].hasChildNodes() || children[c].constructor === HTMLHRElement)) {
						children[c].remove(); // remove node
						children.splice(c, 1); // remove from array
						continue;
					}
					children[c].style.transition = "none";
				}
			}
			// one column does not need further recomputation, reset height to initial
			if (columns === 1) {
				container.style.height = null;
				resolve();
				return;
			}

			// get gap size
			const gap = parseFloat(getComputedStyle(container).gap);

			// initiate column height observer and preset with offset values
			const columnHeight = [];
			for (let initCols = 0; initCols < columns; initCols++) columnHeight[initCols] = parseInt(getComputedStyle(container.parentNode).marginBottom);

			// others iterate by forEach but there might be elements being appended for column shift during the loop
			let child, childHeight, column, currentColumn, nextColumn, injectedNode;
			for (let index = 0; index < children.length; index++) {
				if (!children[index]) break;
				child = children[index];
				column = index % columns;
				// get current height
				childHeight = child.hasChildNodes() || child.constructor === HTMLHRElement ? child.getBoundingClientRect().height + gap : 0;
				// insert spacer if and where applicable
				if (init && Date.now() - startTime < this.timeout) {
					// if the next columns bottom including the current childs height is higher (less) than the current columns height including the current childs height
					// then shift child to next column by inserting an invisible pseudo article
					if (childHeight > 0) {
						// check for the following max columns - 1
						for (let addColumn = 0; addColumn < columns - 1; addColumn++) {
							currentColumn = (columnHeight[column + addColumn] !== undefined ? column : -1) + addColumn;
							nextColumn = columnHeight[currentColumn + 1] !== undefined ? currentColumn + 1 : 0;
							// break if the current column would be shorter or equal than the next after appending
							if (columnHeight[currentColumn] + childHeight <= columnHeight[nextColumn] + childHeight) break;

							// create an empty element to inject, affected by grid properties thus influencing the columns
							injectedNode = document.createElement("article");
							injectedNode.style.height = injectedNode.style.padding = injectedNode.style.border = 0;
							injectedNode.style.margin = -gap / 2 + "px";
							injectedNode.ariaHidden = true;

							// insert before current child to shift column
							// and add to children in the current position for next parent iteration
							await this.insertNodeAfter(injectedNode, children[index - 1])
								.then(() => {
									children.splice(index, 0, injectedNode);
								})
								.catch((error) => {
									//_client.application.debug(error);
								});
						}
						// update current child for being a possible injected element
						child = children[index];
						childHeight = child.getBoundingClientRect().height + gap; // child.hasChildNodes() ? child.getBoundingClientRect().height + gap : 0;
					}
				}
				// add current child to column height observer
				columnHeight[column] += Math.round(childHeight);
			}
			// set container height to the max columnHeight and restart MutationObserver
			container.style.height = `${Math.max(...columnHeight)}px`;
			resolve();
		});
	}
}

class ImageHandler {
	/**
	 * image handling. lazyloading on entering viewport, drawing images to canvases
	 * @requires api, QrCreator, JsBarcode
	 * @param {{qrCodes: [], barCodes: [], images: []}}
	 * @returns rendered content
	 *
	 * usable even without proper construction if methods are called directly with passed content options
	 */
	images = {
		qrCodes: [], // array of objects with canvas id and content, reset on assemble init
		barCodes: [], // array of objects with canvas id and content, reset on assemble init
		images: [], // array of objects with canvas id and content, reset on assemble init
	};

	constructor(images = {}) {
		this.images = images;
	}
	/**
	 * event handler onscroll
	 * iterates over the above arrays to populate the canvasses if within viewport
	 * removes entries once being handled
	 * neccessary e.g. on thousands of approved orders where repeatedly calling the libraries crashes the browser
	 *
	 * @requires Assemble populated arrays
	 * @event QrCreator, JsBarcode and/or proprietary img2canvas
	 */
	lazyload() {
		let content;
		if (this.images.qrCodes.length) {
			for (let i = 0; i < this.images.qrCodes.length; i++) {
				if (!(content = this.images.qrCodes[i])) continue;
				if (!document.getElementById(content.id)) {
					delete this.images.qrCodes[i];
					continue; // idk why, but sometimes this is erroneous
				}
				var rect = document.getElementById(content.id).getBoundingClientRect();
				if (rect.top >= 0 && rect.left >= 0 && rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) && rect.right <= (window.innerWidth || document.documentElement.clientWidth)) {
					this.qrcode(content);
					delete this.images.qrCodes[i]; // prevent repeatedly rendering
				}
			}
			this.images.qrCodes = this.images.qrCodes.filter((v) => v);
		}
		if (this.images.barCodes.length) {
			for (let i = 0; i < this.images.barCodes.length; i++) {
				if (!(content = this.images.barCodes[i])) continue;
				if (!document.getElementById(content.id)) {
					delete this.images.qrCodes[i];
					continue; // idk why, but sometimes this is erroneous
				}
				var rect = document.getElementById(content.id).getBoundingClientRect();
				if (rect.top >= 0 && rect.left >= 0 && rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) && rect.right <= (window.innerWidth || document.documentElement.clientWidth)) {
					this.barcode(content);
					delete this.images.barCodes[i]; // prevent repeatedly rendering
				}
			}
			this.images.barCodes = this.images.barCodes.filter((v) => v);
		}
		if (this.images.images.length) {
			for (let i = 0; i < this.images.images.length; i++) {
				if (!(content = this.images.images[i])) continue;
				if (!document.getElementById(content.id)) {
					delete this.images.qrCodes[i];
					continue; // idk why, but sometimes this is erroneous
				}
				var rect = document.getElementById(content.id).getBoundingClientRect();
				if (rect.top >= 0 && rect.left >= 0 && rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) && rect.right <= (window.innerWidth || document.documentElement.clientWidth)) {
					this.image(content);
					delete this.images.images[i]; // prevent repeatedly rendering
				}
			}
			this.images.images = this.images.images.filter((v) => v);
		}
	}
	qrcode(content) {
		const canvas = document.getElementById(content.id);
		// adjust canvas dimensions to square
		let min = Math.min(canvas.height, canvas.width);
		canvas.style.maxHeight = canvas.style.maxWidth = min + "px";
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
			canvas
		);
	}
	barcode(content) {
		try {
			JsBarcode("#" + content.id, content.content.value, {
				format: content.content.format || "CODE128",
				background: "transparent",
				displayValue: content.content.displayValue != undefined ? content.content.displayValue : true,
			});
		} catch (e) {
			new Toast(api._lang.GET("jsbarcode.error"), "error");
		}
	}
	image(content) {
		let canvas = document.getElementById(content.id),
			img = new Image();
		img.src = content.content;
		img.addEventListener("load", function (e) {
			canvas.width = this.width;
			canvas.height = this.height;
			canvas.getContext("2d").drawImage(this, 0, 0, this.width, this.height);
		});
		img.dispatchEvent(new Event("load"));
		// DO NOT URL.revokeObjectURL(img.src);
	}
}

export class Dialog {
	/**
	 * @requires api, Html5QrcodeScanner, StlViewer, Assemble
	 * @param {type: str, icon: str, header: str, render: str|array, options:{displayText: value str|bool|{value, class}}} options
	 * @param {string} returntype object (default) or formdata
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
	 * new Dialog({type: 'alert', header: 'acknowledge this!', render: 'infotext'})
	 * ```
	 * @example ```js
	 * new Dialog({type:'confirm', header:'confirm this', options:{'abort': false, 'yes, i agree': {'value': true, class: 'reducedCTA'}}}).then(confirmation => {
	 *  	if (confirmation) huzzah());
	 * 	});
	 * ```
	 * @example ```js
	 * new Dialog({type:'preview', header:'display and download', render:{type: 'stl', name: 'filename', url: 'urlToFile', transfer: undefined || true});
	 * ```
	 * @example ```js
	 * new Dialog({type:'preview', header:'display and download', render:{type: 'qrcode', name: 'filename', content: 'data'});
	 * ```
	 * @example ```js
	 * new Dialog({type:'preview', header:'display and download', render:{type: 'barcode', name: 'filename', content: {content: 'data', format: 'CODE128'}});
	 * ```
	 * @example ```js
	 * new Dialog({type:'preview', header:'display and download', render:{type: 'image', name: 'filename', content: 'urlToFile'});
	 * ```
	 * input needs button options as well, response keys in accordance to assemble content input names
	 * image and signature are NOT supported for having to be rendered in advance to filling their canvases.
	 * multiple articles and sections are NOT supported due to simplified query selector
	 * THIS IS FOR SIMPLE INPUTS ONLY
	 * @example ```js
	 * new Dialog({type:'input', header:'fill out assembled form', render: [assemble_content], options:{'abort': false, 'submit': {'value': true, class: 'reducedCTA'}}, id : 'peculiarDialogID'}).then(response => {
	 *  	if (Object.keys(response)) console.log('these are the results of the form:', response);
	 * 	});
	 * ```
	 * true options must be assigned {value: true}. false can be assigned without other properties.
	 */
	constructor(options = {}, returntype = "object") {
		this.type = options.type || null;
		this.icon = options.icon || null;
		this.header = options.header || null;
		this.render = options.render || null;
		this.options = options.options || {};
		this.scannerElements = {};
		this.previewElements = {};
		this.assemble = null;
		this.form = null;

		this.dialog = document.createElement("dialog");
		this.dialog.id = options.id || getNextElementID();
		this.dialog.role = "dialog";
		this.dialog.ariaLabel = api._lang.GET("assemble.render.aria.dialog");

		if (this.type) {
			// assemble render data
			if (this.type === "input") {
				if (this.render.content === undefined) this.render = { content: this.render };
				this.assemble = new Assemble(this.render);
			}

			// create form
			this.form = document.createElement("form");
			this.form.method = "dialog";
			if (this.type === "message") {
				this.form.dataset.usecase = "message";
				this.type = "input";
			}

			// append close button
			const img = document.createElement("img");
			img.classList.add("close");
			img.src = "./media/times.svg";
			img.alt = api._lang.GET("assemble.render.aria.cancel");
			img.onclick = () => {
				const scanner = document.querySelector("video");
				if (scanner) scanner.srcObject.getTracks()[0].stop();
				this.dialog.remove();
			};
			this.form.append(img);

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
					for (const line of this.render.split(/\r\n|\n|\\n/)) {
						header.append(document.createTextNode(line));
						header.append(document.createElement("br"));
					}
					header.children[header.children.length - 1].remove(); //remove trailing linebreak
				}
				this.form.append(header);
			}
			// append select buttons if applicable
			if (this.type === "select") this.form.style.display = "flex";
			for (const element of this[this.type]()) {
				if (element) this.form.append(element);
			}
			this.dialog.append(this.form);

			// compare nodelist if similar dialog has been opened just before, aborting display
			function objectifyNode(element) {
				let obj = {};
				obj.name = element.localName;
				obj.attributes = [];
				obj.children = [];
				Array.from(element.attributes).forEach((a) => {
					// ignore id, for and class (e.g. for active slides which is set after insertion)
					if (!["id", "for", "class"].includes(a.name)) obj.attributes.push({ name: a.name, value: a.value });
				});
				Array.from(element.children).forEach((c) => {
					obj.children.push(objectifyNode(c));
				});

				return obj;
			}
			const thisdialog = JSON.stringify(objectifyNode(this.dialog.firstChild));
			for (const opendialog of Object.values(document.querySelectorAll("dialog[open]"))) {
				if (JSON.stringify(objectifyNode(opendialog.firstChild)) == thisdialog) {
					return new Promise((resolve, reject) => {
						reject;
					});
				}
			}

			// append to dom before initializing following library functions to avoid errors
			document.body.append(this.dialog);

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
			if (this.type === "preview") {
				// render after canvases or divs have been appended to the dom
				switch (this.render.type) {
					case "stl":
						this.previewElements.viewer = new StlViewer(this.previewElements.canvas, {
							models: [
								{
									id: 0,
									filename: "../../" + this.render.url, // relative url since StlViewer executes within own library directory
								},
							],
						});

						break;
					case "qrcode":
						if (this.previewElements.canvas) {
							const img = new ImageHandler();
							img.qrcode({
								id: this.previewElements.canvas.id,
								content: this.render.content,
							});
						}
						break;
					case "barcode":
						if (this.previewElements.canvas) {
							const img = new ImageHandler();
							img.barcode({
								id: this.previewElements.canvas.id,
								content: this.render.content,
							});
						}
						break;
					case "image":
						if (this.previewElements.canvas) {
							const img = new ImageHandler();
							img.image({
								id: this.previewElements.canvas.id,
								content: this.render.content,
							});
						}
						break;
				}
			}
			if (this.assemble) this.assemble.processAfterInsertion(this.dialog);

			return new Promise((resolve, reject) => {
				this.dialog.showModal();
				this.dialog.onclose = resolve;
			}).then((response) => {
				let result;

				if (this.previewElements.canvas) {
					// release ressources
					this.previewElements.canvas.remove();
					this.previewElements = {};
					if (!this.render.transfer) {
						this.dialog.remove();
						return;
					}
				}

				/**
				 * recursive value getter for nested rendered form
				 * @param {node} parent
				 * @returns {object} key:value pairs of input names and values if present
				 */
				function dialogForm2Obj(parent) {
					let result = {},
						child,
						keyintersections;
					parent.childNodes.forEach((node) => {
						if (["input", "textarea", "select"].includes(node.localName) && node.value) {
							// prepared inputs having data-wrap="some___thing" inserting value on the three underscores
							if (node.dataset.wrap && node.value) {
								node.value = node.dataset.wrap.replace("___", node.value);
							}
							if (["checkbox"].includes(node.type) && node.checked === true) {
								if (!result[node.dataset.grouped]) result[node.dataset.grouped] = node.name;
								else result[node.dataset.grouped] += " | " + node.name;
								result[node.name] = node.value;
							}
							if (["radio"].includes(node.type) && node.checked === true) {
								result[node.name] = node.value;
							} else if (!["checkbox", "radio"].includes(node.type)) result[node.name] = node.value;
						} else {
							child = dialogForm2Obj(node);
							// filter and append checkbox values if applicable, shouldn't happen but you never know
							keyintersections = Object.keys(result).filter((value) => Object.keys(child).includes(value));
							for (const key of keyintersections) {
								result[key] += " | " + child[key];
								delete child[key];
							}
							result = { ...result, ...child };
						}
					});
					return result;
				}

				// return dialog-form response
				switch (this.type) {
					case "select":
						result = response.target.returnValue;
						if (!result) {
							this.dialog.remove();
							return;
						}
					case "confirm":
						result = response.target.returnValue;
						if (result && result == "true") {
							this.dialog.remove();
							return true;
						} else if (!result || result == "false") {
							this.dialog.remove();
							return false;
						}
						this.dialog.remove();
						return result;
					default:
						if (response.target.returnValue !== "false" && (response.returnValue || response.target.returnValue === "true")) {
							let empty = true;
							switch (returntype.toLowerCase()) {
								case "formdata":
									result = _.getInputs(this.form, true);
									// check for empty object
									for (const prop in result.values()) {
										if (prop) {
											empty = false;
											break;
										}
									}
									if (empty) result = false;
									break;
								default:
									result = dialogForm2Obj(this.dialog);
									// check for empty object
									for (const prop in result) {
										if (Object.hasOwn(result, prop)) {
											empty = false;
											break;
										}
									}
									if (empty) result = false;
							}
							this.dialog.remove();
							return result;
						}
						this.dialog.remove();
						return false;
				}
			});
		}
		this.dialog.close();
		this.dialog.remove();
	}

	/**
	 * returns textnodes with linebreak-nodes from string containing \n or &lt;br&gt;
	 * @param {string} string
	 * @returns {domNodes}
	 */
	linebreak(string) {
		let content = string.matchAll(/(.+?|^)(?:\\r\\n|\r\n|\\n|\n|<br.\/>|<br>|$)/gm),
			result = [];
		for (const match of content) {
			result.push(document.createTextNode(match[1] || ""));
			result.push(document.createElement("br"));
		}
		result.pop(); //remove trailing linebreak
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
		for (const [option, properties] of Object.entries(this.options)) {
			button = document.createElement("button");
			button.append(document.createTextNode(option));
			button.classList.add("confirmButton");
			if (typeof properties === "string" || typeof properties === "boolean") button.value = properties;
			else {
				for (const [property, value] of Object.entries(properties)) {
					if (property === "class") button.classList.add(value);
					else button.setAttribute(property, value);
				}
			}
			if (properties === true && this.form) {
				button.onclick = (event) => {
					event.preventDefault();
					const required = document.querySelectorAll("#" + this.dialog.id + " [required]");
					let missing_required = false;

					// check regular inputs
					for (const element of required) {
						if (element.validity.valueMissing) {
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
					if (!missing_required) this.form.requestSubmit();
				};
			}
			if (!properties) button.formNoValidate = true;
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
				optgroup.id = "opt" + firststring;
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
		// jump to optgoup while dialog has focus right after creation, at least on desktop with physical keyboard
		if (optgroup)
			buttons.addEventListener("keydown", (event) => {
				event.preventDefault(); // prevent starting site search
				const opt = document.getElementById("opt" + event.key.toUpperCase()) || document.getElementById("opt" + event.key);
				if (opt) opt.scrollIntoView();
			});
		return [buttons];
	}

	/**
	 * preview for supported file types
	 * currently stl, qrcode, barcode, image (png, jpg, jpeg, gif)
	 * create canvases for preview and download link if applicable
	 */
	preview() {
		let a = document.createElement("a"),
			warning = Polyfill.downloadWarning();
		if (this.render.type === "stl") {
			const div = document.createElement("div");
			div.id = "stlviewer_canvas";
			div.classList = "preview stlviewer";
			div.title = api._lang.GET("assemble.render.aria.preview", { ":file": this.render.name || this.render.url });
			this.previewElements.canvas = div;

			if (this.render.transfer) {
				return [div, ...this.confirm()];
			} else {
				a.href = this.render.url;
				a.download = this.render.name || this.render.url;
				a = Polyfill.a(a);
				a.append(document.createTextNode(this.render.name || this.render.url));
				if (warning) return [div, warning, a];
				return [div, a];
			}
		}
		let result = [];
		const canvas = document.createElement("canvas");
		canvas.id = getNextElementID();
		canvas.classList.add("preview");
		canvas.title = api._lang.GET("assemble.render.aria.image", { ":image": this.render.name });

		a.href = this.render.content;
		a.download = this.render.name || this.render.content;
		a.dataset.type = "downloadlink";
		a.append(document.createTextNode(this.render.name || this.render.content));

		if (["qrcode", "barcode"].includes(this.render.type)) {
			result.push(canvas);
			this.previewElements.canvas = canvas;
			// invisible pseudo anchor for downloading rendered canvas content from libraries to file
			a.href = "javascript: void(0)";
			a.onclick = () => {
				canvas.toBlob(
					function (blob) {
						const blobUrl = URL.createObjectURL(blob);
						let link = document.createElement("a");
						link.href = blobUrl;
						link.download = a.download + ".png";
						link.click();
						URL.revokeObjectURL(blobUrl);
					},
					"image/png",
					1
				);
			};
		}
		if (this.render.type === "image") {
			const filetype = this.render.content.split(".");
			if (["png", "jpg", "jpeg", "gif"].includes(filetype[filetype.length - 1].toLowerCase()) || this.render.content.startsWith("data:image")) {
				result.push(canvas);
				this.previewElements.canvas = canvas;

				if (this.render.transfer) {
					return [canvas, ...this.confirm()];
				} else {
					a = Polyfill.a(a);
					if (warning) return [canvas, warning, a];
					return [canvas, a];
				}
			} else {
				// not supported; currently no need for fallback as preview is prepared by backend with filtered file types as well
			}
			// direct download, lossless and original format
			if ((warning = Polyfill.downloadWarning())) result.push(warning);
			a = Polyfill.a(a);
		}
		result.push(a);

		return result;
	}
}

export class Toast {
	// an icon list, grouped by available types with arrays of icons that will be selected randomly
	// for pure graphical reasons
	// intended for gaining traction by providing cheap dopamine, given cute icons you have the rights to use
	icons = Icons.toast;

	/**
	 * displays a toast message for defined time
	 * @param {string} message
	 * @param {string} type success|error|deleted|info
	 * @param {int} duration in milliseconds
	 * @param {string} forcedId e.g. sessionwarning
	 * @example new Toast('message', 'success')
	 */
	constructor(message = "", type = "", duration = 5000, forcedId = null) {
		const openmodal = document.querySelector("dialog[open]");
		if (openmodal) {
			// compare messages from probably open modals, stop toast creation if similar message is already being displayed
			const openmodals = document.querySelectorAll("dialog[open]>span");
			for (let i = 0; i < openmodals.length; i++) {
				if (openmodals[i].innerHTML === message) return;
			}
			duration = duration / 2;
		}

		this.message = message || undefined;
		this.duration = duration;
		this.stop = new Date().getTime() + duration;
		this.toast = document.getElementById(forcedId) || document.createElement("dialog");
		this.toast.role = "alert";
		this.toast.id = forcedId ? forcedId : getNextElementID();
		this.toast.ariaLabel = api._lang.GET("assemble.render.aria.dialog_toast");

		if (this.message) {
			const closeimg = document.createElement("img"),
				pauseimg = document.createElement("img"),
				msg = document.createElement("span"),
				div = document.createElement("div");
			if (type) {
				this.toast.classList.add(type);
				if (!openmodal && this.icons[type] && this.icons[type].length) this.toast.style = "--icon: url('" + this.icons[type][Math.floor(Math.random() * this.icons[type].length)] + "')";
			}
			closeimg.classList.add("close");
			closeimg.src = "./media/times.svg";
			closeimg.alt = api._lang.GET("assemble.render.aria.cancel");
			closeimg.onclick = () => {
				this.toast.close();
				this.toast.remove();
			};
			pauseimg.classList.add("pause");
			pauseimg.src = "./media/equals.svg";
			pauseimg.alt = api._lang.GET("assemble.render.aria.pause");
			pauseimg.onclick = () => {
				window.clearTimeout(api._settings.session.toasttimeout[this.toast.id]);
			};
			msg.innerHTML = this.message;
			this.toast.append(closeimg, pauseimg, msg, div);
			// append to dom before initializing following library functions to avoid errors
			document.body.append(this.toast);
			if (openmodal) this.toast.showModal();
			else this.toast.show();
			this.toast.focus();
			this.countdown();
		} else {
			window.clearTimeout(api._settings.session.toasttimeout[this.toast.id]);
			delete api._settings.session.toasttimeout[this.toast.id];
			this.toast.close();
			this.toast.remove();
		}
	}
	/**
	 * updates reversed progress bar and closes toast on timeout
	 */
	countdown() {
		const countdowndiv = document.querySelector("#" + this.toast.id + " > div");
		if (countdowndiv) {
			countdowndiv.style.width = Math.round((100 * (this.stop - new Date().getTime())) / this.duration) + "%";
			api._settings.session.toasttimeout[this.toast.id] = window.setTimeout(this.countdown.bind(this), this.duration / 1000);
		}
		if (!countdowndiv || this.stop - new Date().getTime() < 0) {
			window.clearTimeout(api._settings.session.toasttimeout[this.toast.id]);
			delete api._settings.session.toasttimeout[this.toast.id];
			this.toast.close();
			this.toast.remove();
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
	 * @requires api, _client, JsBarcode, QrCreator, SignaturePad, TLN
	 * @param {object} setup render object
	 */
	constructor(setup) {
		this.content = setup.content;
		this.form = setup.form;
		this.section = null;
		this.imageQrCode = [];
		this.imageBarCode = [];
		this.imageUrl = [];
		this.codeEditor = [];
		this.names = setup.names || {};
		this.composer = setup.composer;
		this.isSafari = navigator.userAgent.toLowerCase().includes("safari");
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
		if (trash) Composer.composer_add_trash(trash.parentNode);

		if (this.signaturePad) {
			this.initialize_SignaturePad();
		}

		const lazyload = new ImageHandler({
			qrCodes: this.imageQrCode,
			barCodes: this.imageBarCode,
			images: this.imageUrl,
		});

		if (this.codeEditor.length) {
			for (const id of this.codeEditor) {
				TLN.append_line_numbers(id);
			}
		}
		if (this.imageQrCode.length || this.imageBarCode.length || this.imageUrl.length) {
			lazyload.lazyload();
			eventListenerTarget.addEventListener(
				"scroll",
				() => {
					lazyload.lazyload();
				},
				false
			);
		} else {
			eventListenerTarget.removeEventListener("scroll", () => {
				lazyload.lazyload();
			});
		}
		document.querySelector("main").focus();
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
				_client.application.debug(e);
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
				circle = document.createElementNS("http://www.w3.org/2000/svg", "circle"),
				title = document.createElementNS("http://www.w3.org/2000/svg", "title");
			indicator.classList = "articleindicator";
			indicator.setAttributeNS(null, "viewbox", "0 0 20 20");
			circle.setAttributeNS(null, "cx", "10");
			circle.setAttributeNS(null, "cy", "10");
			circle.setAttributeNS(null, "r", "9");
			indicator.appendChild(circle);
			title.appendChild(document.createTextNode(i + 1));
			indicator.appendChild(title);
			indicator.addEventListener("click", function (e) {
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
				let file = new File([this.dataURLToBlob(signaturePad.toDataURL())], "CAROsignature.jpg", {
					type: "image/jpeg",
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

		if (event.target.form && event.target.form.noValidate) missing_required = false;

		// submit form after confirmation if applicable
		if (!missing_required) {
			if (!event.target.form.dataset.confirm) {
				event.target.form.submit();
				return;
			}
			const options = {};
			options[api._lang.GET("assemble.compose.document.cancel")] = false;
			options[api._lang.GET("assemble.compose.document.confirm")] = { value: true, class: "reducedCTA" };
			new Dialog({ type: "confirm", header: api._lang.GET("general.save", { ":title": document.querySelector("header>h1").innerHTML }), options: options }).then((confirmation) => {
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
		api._settings.session.signatureCanvas = document.getElementById("signaturecanvas");
		window.signaturePad = new SignaturePad(api._settings.session.signatureCanvas, {
			// It's Necessary to use an opaque color when saving image as JPEG;
			// this option can be omitted if only saving as PNG or SVG
			backgroundColor: 'rgb(236, 239, 244)',
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
		api._settings.session.signatureCanvas.width = api._settings.session.signatureCanvas.offsetWidth * ratio;
		api._settings.session.signatureCanvas.height = api._settings.session.signatureCanvas.offsetHeight * ratio;
		api._settings.session.signatureCanvas.getContext("2d").scale(ratio, ratio);
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

		for (const line of this.currentElement.description.replace(/\[\]|DEFAULT_/g, "").split(/\r\n|\n/)) {
			header.append(document.createTextNode(line));
			header.append(document.createElement("br"));
		}
		header.children[header.children.length - 1].remove(); //remove trailing linebreak

		header.dataset.type = this.currentElement.attributes && this.currentElement.attributes["data-type"] ? this.currentElement.attributes["data-type"] : this.currentElement.type;
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
			div.append(document.createTextNode(part[1]));
			div.append(document.createElement("br"));
		}
		div.children[div.children.length - 1].remove(); //remove trailing linebreak
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
						_client.application.debug(attribute, e);
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
		let content = string.matchAll(/(.+?|^)(?:\\r\\n|\r\n|\\n|\n|<br.\/>|<br>|$)/gm),
			result = [];
		for (const match of content) {
			result.push(document.createTextNode(match[1] || ""));
			result.push(document.createElement("br"));
		}
		result.pop(); //remove trailing linebreak
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
	 * creates a text paragraph styled as announcement
	 * @returns {domNodes}
	 * @see this.textsection()
	 */
	announcementsection() {
		return this.textsection();
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
	 * creates a button of type button if not stated otherwise
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
		button.type = "button"; // default type, overrun by attributes if provided
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
				daytile.children[daytile.children.length - 1].remove(); //remove trailing linebreak

				daytile.onclick = function () {
					api.calendar("get", apicall, day.date, day.date);
				};
				daytile.onkeydown = function (event) {
					if (event.key === "Enter") api.calendar("get", apicall, day.date, day.date);
				};
				if (day.today) daytile.classList.add("today");
				if (day.selected) daytile.classList.add("selected");
				if (day.holiday) daytile.classList.add("holiday");
				daytile.title = day.title;
				daytile.role = "link";
				daytile.tabIndex = 0;
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
		img.onclick = async () => {
			div.classList.toggle("extended");
			await _.sleep(500); // wait for transition
			window.Masonry.masonry().catch(() => {
				/*catch error to prevent console error*/
			});
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
			button = document.createElement("button"),
			hint;
		input.type = "file";
		input.id = getNextElementID();
		this.currentElement.description = this.currentElement.attributes.name.replace(/\[\]/g, "");
		if (this.currentElement.attributes.multiple) {
			if (!this.currentElement.attributes.name.endsWith("[]")) this.currentElement.attributes.name += "[]";
		}
		this.currentElement.hint = (this.currentElement.hint ? this.currentElement.hint + " " : "") + api._lang.GET("assemble.render.files_hint");
		hint = [...this.hint()];

		input.setAttribute("aria-label", this.currentElement.description);
		input = this.apply_attributes(this.currentElement.attributes, input);

		function forbiddenName(files){
			for(const file of Object.values(files)){
				console.log(file);
				if (file.name.match("CAROsignature")){
					new Toast(api._lang.GET("assemble.render.reserved_name", {':name': "CAROsignature"}));
					return true;
				}
			}
			return false;
		}

		if (this.currentElement.attributes.multiple !== undefined)
			input.onchange = function () {
				if (forbiddenName(this.files)){
					delete this.files;
					return;
				}
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
				if (forbiddenName(this.files)){
					delete this.files;
					return;
				}
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
		return [...this.header(), input, label, button, ...hint];
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
		return [...this.br(), ...this.button()];
	}

	/**
	 * creates a stl file selection input type text with button to open up an api stl search
	 * @returns {domNodes} icon, input, button, label, hint
	 * @example this.currentElement
	 * ```json
	 *  {
	 * 		"type": "filereference",
	 * 		"hint": "somethingsomething"...,
	 * 		"numeration": "anything resulting in true to prevent enumeration",
	 * 		"attributes": {
	 * 			"name": "variable name will be used as label",
	 * 			"multiple": "bool on changing the input field another appends"
	 * 		}
	 * 	}
	 * ```
	 */
	filereference() {
		let input = document.createElement("input"),
			label = document.createElement("label"),
			span = document.createElement("span"),
			button = document.createElement("button"),
			hint;
		const productselectionClone = structuredClone(this.currentElement);
		input.type = "text";
		input.id = this.currentElement.attributes && this.currentElement.attributes.id ? this.currentElement.attributes.id : getNextElementID();
		input.autocomplete = "off";
		span.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]|DEFAULT_/g, "")));
		label.classList.add("productselection");
		if (this.currentElement.attributes.required) span.dataset.required = true;
		if (this.currentElement.attributes.multiple) label.dataset.multiple = "multiple";
		if (this.currentElement.attributes["data-filtered"]) label.dataset.filtered = this.currentElement.attributes["data-filtered"];

		if (!this.currentElement.hint) hint = this.br(); // quick and dirty hack to avoid messed up linebreaks after inline buttons
		else hint = [...this.hint()];

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
					window.Masonry.masonry().catch(() => {
						/*catch error to prevent console error*/
					});
				}
			};
		}
		input = this.apply_attributes(this.currentElement.attributes, input);

		label.dataset.type = this.currentElement.type;
		label.append(span, input);

		button.classList.add("productselection");
		button.dataset.type = "search";
		button.type = "button";
		button.title = api._lang.GET("assemble.render.aria.search");
		button.onclick = function () {
			const options = {};
			options[api._lang.GET("assemble.compose.document.cancel")] = false;
			options[api._lang.GET("assemble.compose.document.confirm")] = { value: true, class: "reducedCTA" };
			new Dialog({
				type: "input",
				header: api._lang.GET("file.file_filter_label"),
				render: [
					[
						{
							type: "search",
							attributes: {
								name: api._lang.GET("file.file_filter_label"),
								onkeydown: "if (event.key === 'Enter') {event.preventDefault(); api.file('get', 'filter', 'null', encodeURIComponent(this.value), 'filereference');}",
							},
						},
						{
							type: "hidden",
							attributes: {
								name: "_selectedfile",
								id: "_selectedfile",
							},
						},
					],
				],
				options: options,
			}).then((response) => {
				if (Boolean(response)) {
					const inputfield = document.getElementById(input.id);
					inputfield.value = response._selectedfile || "";
					inputfield.dispatchEvent(new Event("change"));
				}
			});
		};
		return [label, button, ...hint];
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
		this.currentElement.attributes.required = true;
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
		let result = [],
			previewRender = {};
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
			previewRender = { type: "qrcode", name: this.currentElement.attributes.name, content: this.currentElement.attributes.qrcode };
		}
		if (this.currentElement.attributes.barcode) {
			this.imageBarCode.push({
				id: canvas.id,
				content: this.currentElement.attributes.barcode,
			});
			disabled = false;
			previewRender = { type: "barcode", name: this.currentElement.attributes.name, content: this.currentElement.attributes.barcode };
		}
		if (this.currentElement.attributes.url) {
			this.imageUrl.push({
				id: canvas.id,
				content: this.currentElement.attributes.url,
			});
			disabled = false;
			previewRender = { type: "image", name: this.currentElement.attributes.name, content: this.currentElement.attributes.url };
		}

		// accessibility setting
		canvas.title = api._lang.GET("assemble.render.aria.image", { ":image": this.currentElement.attributes.name });

		result.push(canvas);

		if (!this.currentElement.attributes.imageonly) {
			const button = document.createElement("button");
			button.type = "button";
			button.dataset.type = "image";
			button.classList.add("inlinebutton");
			button.append(document.createTextNode(this.currentElement.description));
			button.onclick = () => {
				new Dialog({ type: "preview", header: this.currentElement.attributes.name, render: previewRender });
			};
			if (disabled) button.disabled = true;

			result.push(button);
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
			hint,
			imagealigned = false,
			datalist;
		input.type = type;
		const inputClone = structuredClone(this.currentElement);
		if (type === "password") this.currentElement.type = "password";
		if (type === "search" || "onkeydown" in this.currentElement.attributes) this.currentElement.hint = (this.currentElement.hint || "") + " \u21B5" + api._lang.GET("assemble.render.search_hint");
		hint = this.hint();
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
					window.Masonry.masonry().catch(() => {
						/*catch error to prevent console error*/
					});
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
				const options = {},
					content = currentElement.content,
					value = this.value.split(", ");
				if (value) {
					Object.keys(content).forEach((key) => {
						if (value.includes(key)) content[key].checked = true;
						else delete content[key].checked;
					});
				}
				options[api._lang.GET("assemble.compose.document.cancel")] = false;
				options[api._lang.GET("assemble.compose.document.confirm")] = { value: true, class: "reducedCTA" };
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
						delete response.null;
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

		if ("onkeydown" in this.currentElement.attributes) this.currentElement.attributes.onkeydown = "if (event.key === 'Enter') event.preventDefault(); " + this.currentElement.attributes.onkeydown;
		else this.currentElement.attributes.onkeydown = "if (event.key === 'Enter') event.preventDefault();";
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

		if (["search", "filter"].includes(type)) {
			const search_pattern = document.createElement("a"),
			option = {};
			option[api._lang.GET('general.ok_button')] = false;
			search_pattern.href = "javascript:void(0)";
			search_pattern.dataset.type = "";
			search_pattern.classList.add("inline");
			search_pattern.onclick = function () {
				new Dialog({
					type: "input",
					header: api._lang.GET("general.search_pattern"),
					render: [
						[
							{ type: "textsection", htmlcontent: api._lang.GET("general.search_pattern_content") }
						]
					],
					options: option
				});
			};
			search_pattern.append(document.createTextNode(api._lang.GET("general.search_pattern")));
			if (hint) hint[0].append(search_pattern);
			else {
				let div = document.createElement("div");
				div.classList.add("hint");
				div.role = "note";
				div.append(search_pattern);
				hint = [div];
			}
		}

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
		let result = [...this.header()],
			a;
		if (this.currentElement.attributes !== undefined) result.push(...this.hidden()); // applying data-filtered for css rules
		let warning = Polyfill.downloadWarning(),
			includesDownloads = false;
		for (const [link, attributes] of Object.entries(this.currentElement.content)) {
			a = document.createElement("a");
			a = this.apply_attributes(attributes, a);
			if (!a.href) a.href = link;
			if (!a.download) {
				if (!a.href.includes("javascript:") && !a.target) a.target = "_blank";
			} else {
				includesDownloads = true;
				a = Polyfill.a(a);
			}
			a.appendChild(document.createTextNode(link));
			result.push(a);
		}
		if (warning && includesDownloads) result.push(warning);
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
		let radio, message, icon, p, date, unseen, onclick_forward, mark_deletion;

		message = document.createElement("div");

		if (this.currentElement.attributes !== undefined && this.currentElement.attributes.ICON_onclick !== undefined) {
			// ugly but effective
			onclick_forward = this.currentElement.attributes.ICON_onclick;
			delete this.currentElement.attributes.ICON_onclick;
		}
		if (this.currentElement.attributes !== undefined && this.currentElement.attributes.id !== undefined) {
			// mark message for deletion
			radio = document.createElement("input");
			radio.type = "radio";
			radio.id = radio.name = "_msg" + this.currentElement.attributes.id;
			mark_deletion = function () {
				radio.checked = !radio.checked;
			};
			delete this.currentElement.attributes.id;
		}

		if (this.currentElement.content.img != undefined) {
			icon = document.createElement("img");
			icon.src = this.currentElement.content.img;
			icon.alt = api._lang.GET("assemble.render.aria.image", { ":image": this.currentElement.content.user });
			if (onclick_forward) {
				icon.onclick = new Function(onclick_forward);
				icon.onkeydown = new Function("if (event.key === 'Enter') " + onclick_forward);
				icon.title = api._lang.GET("message.message.forward", { ":user": this.currentElement.content.user });
				icon.role = "link";
				icon.tabIndex = 0;
			}
			message.append(icon);
		}
		p = document.createElement("p");
		if (mark_deletion) p.onclick = mark_deletion;
		p.append(document.createTextNode(this.currentElement.content.user));
		message.append(p);

		p = document.createElement("p");
		if (mark_deletion) p.onclick = mark_deletion;
		date = document.createElement("small");
		date.append(document.createTextNode(this.currentElement.content.date));
		p.append(date);
		message.append(p);

		p = document.createElement("p");
		if (mark_deletion) p.onclick = mark_deletion;
		p.innerHTML = this.currentElement.content.text.replaceAll(/\n|\r\n/g, "<br />");
		message.append(p);

		// display notif of unread messages in overview mode
		if (this.currentElement.content.unseen != undefined && this.currentElement.content.unseen) {
			unseen = document.createElement("div");
			unseen.append(document.createTextNode(this.currentElement.content.unseen));
			message.append(unseen);
		}

		if (this.currentElement.attributes !== undefined) {
			message = this.apply_attributes(this.currentElement.attributes, message);
			if ("onclick" in this.currentElement.attributes) {
				message.role = "link";
				message.title = api._lang.GET("message.message.open", { ":user": this.currentElement.content.user });
				message.onkeydown = new Function("if (event.key === 'Enter') " + this.currentElement.attributes.onclick);
				message.tabIndex = 0;
			}
		}
		message.classList.add("message");
		if (this.currentElement.dirright != undefined && this.currentElement.dirright) message.classList.add("right");

		return radio ? [radio, message] : [message];
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
			hint,
			multiple;
		this.currentElement.description = this.currentElement.attributes.name.replace(/\[\]/g, "");
		if (this.currentElement.attributes && this.currentElement.attributes.multiple) {
			multiple = true;
			if (!this.currentElement.attributes.name.endsWith("[]")) this.currentElement.attributes.name += "[]";
			// delete for input apply_attributes
			delete this.currentElement.attributes.multiple;
		}
		if (!this.currentElement.hint) hint = this.br(); // quick and dirty hack to avoid messed up linebreaks after inline buttons
		else hint = [...this.hint()];

		function forbiddenName(files){
			for(const file of Object.values(files)){
				console.log(file);
				if (file.name.match("CAROsignature")){
					new Toast(api._lang.GET("assemble.render.reserved_name", {':name': "CAROsignature"}));
					return true;
				}
			}
			return false;
		}

		function changeEvent() {
			if (forbiddenName(this.files)){
				delete this.files;
				return;
			}
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
		img.alt = api._lang.GET("assemble.render.aria.preview", { ":file": "" });

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
			hint;
		const productselectionClone = structuredClone(this.currentElement);
		input.type = "text";
		input.id = this.currentElement.attributes && this.currentElement.attributes.id ? this.currentElement.attributes.id : getNextElementID();
		input.autocomplete = "off";
		span.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]|DEFAULT_/g, "")));
		label.classList.add("productselection");
		if (this.currentElement.attributes.required) span.dataset.required = true;
		if (this.currentElement.attributes.multiple) label.dataset.multiple = "multiple";
		if (this.currentElement.attributes["data-filtered"]) label.dataset.filtered = this.currentElement.attributes["data-filtered"];

		if (!this.currentElement.hint) hint = this.br(); // quick and dirty hack to avoid messed up linebreaks after inline buttons
		else hint = [...this.hint()];

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
					window.Masonry.masonry().catch(() => {
						/*catch error to prevent console error*/
					});
				}
			};
		}
		input = this.apply_attributes(this.currentElement.attributes, input);

		label.dataset.type = this.currentElement.type;
		label.append(span, input);

		button.classList.add("productselection");
		button.dataset.type = "search";
		button.title = api._lang.GET("assemble.render.aria.search");
		button.type = "button";
		button.onclick = function () {
			const options = {};
			options[api._lang.GET("assemble.compose.document.cancel")] = false;
			options[api._lang.GET("assemble.compose.document.confirm")] = { value: true, class: "reducedCTA" };
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
								onkeydown: "if (event.key === 'Enter') {event.preventDefault(); api.purchase('get', 'productsearch', 'null', this.value, 'productselection');}",
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
					],
				],
				options: options,
				id: "_productselectionDialog",
			}).then((response) => {
				if (Boolean(response)) {
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
	 * 		"identify_erp_import_fields": [{name, inputtype},...]
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
			originaltype = this.currentElement.type,
			button = document.createElement("button");
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
						window.Masonry.masonry().catch(() => {
							/*catch error to prevent console error*/
						});
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
						window.Masonry.masonry().catch(() => {
							/*catch error to prevent console error*/
						});
					}
				}
			});
		};
		result.push(button);

		if (originaltype === "identify" && !api._settings.user.permissions.patient) {
			let button = document.createElement("button");

			button.appendChild(document.createTextNode(this.currentElement.description ? this.currentElement.description : api._lang.GET("assemble.render.merge")));
			button.type = "button";
			button.dataset.type = "merge";
			let options = {},
				inputs = [
					{
						type: "textsection",
						attributes: {
							name: api._lang.GET("record.import.by_identify"),
							"data-type": "identify",
						},
					},
					{
						type: "scanner",
						attributes: {
							name: this.currentElement.attributes.name,
						},
					},
				];
			if (this.currentElement.identify_erp_import_fields) {
				inputs.push(
					{ type: "hr" },
					{
						type: "textsection",
						attributes: {
							name: api._lang.GET("erpquery.integrations.data_import"),
							"data-type": "identify",
						},
					}
				);
				for (const field of this.currentElement.identify_erp_import_fields) {
					inputs.push({
						type: field.type,
						attributes: {
							name: field.name,
						},
					});
				}
			}
			inputs = JSON.stringify(inputs); // something alters the objects, didn't figure out what, but this solves the problem
			options[api._lang.GET("general.cancel_button")] = false;
			options[api._lang.GET("record.import.import")] = { value: true, class: "reducedCTA" };
			button.onclick = function () {
				new Dialog(
					{
						type: "input",
						header: api._lang.GET("assemble.render.merge"),
						options: options,
						render: JSON.parse(inputs),
					},
					"FormData"
				).then((response) => {
					if (response) {
						api.record("post", "import", null, response);
					}
				});
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
					if (Boolean(response) && response != e.target.value) {
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
							window.Masonry.masonry().catch(() => {
								/*catch error to prevent console error*/
							});
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
			onclick: "signaturePad.clear()",
		};
		result = result.concat(this.hint());
		delete this.currentElement.hint;
		result = result.concat(this.deletebutton()); // hint would be added here as well
		this.signaturePad = true;
		return result;
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
		return this.button();
	}

	/**
	 * creates a simple table with pure text content
	 * events are assignable per cell
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type": "table",
	 * 		"attributes": {
	 * 			"name": "table desciption",
	 * 			"data-type": "whatever is tidy"
	 * 		},
	 * 		"content": [
	 * 			[{"c": "th", "a":{}}, {"c": "th", "a":{}}, {"c": "th", "a":{}}, ...],
	 * 			[{"c": "td", "a":{}}, {"c": "td", "a":{}}, {"c": "td", "a":{}}, ...],
	 * 			...
	 * 		],
	 * 		"hint": "read this like..."
	 *	}
	 * ```
	 */
	table() {
		this.currentElement.description = this.currentElement.attributes.name.replace(/\[\]/g, "");
		let result = [...this.header()],
			tr,
			td,
			cell;
		const table = document.createElement("table");

		for (let row = 0; row < this.currentElement.content.length; row++) {
			tr = document.createElement("tr");
			for (let column = 0; column < this.currentElement.content[row].length; column++) {
				cell = this.currentElement.content[row][column];
				td = document.createElement(row > 0 ? "td" : "th");

				td = this.apply_attributes(cell.a || {}, td);
				if (cell.c) {
					td.append(document.createTextNode(cell.c));
				}
				tr.append(td);
			}
			table.append(tr);
		}
		result.push(table);
		result = result.concat(this.hint());
		return result;
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
	 * 			"onfocus": "_client.application.toClipboard(this)"
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
			span = document.createElement("span"),
			autocompletebuttons = null;

		textarea.id = getNextElementID();
		textarea.autocomplete = "off";
		this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		span.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]/g, "")));
		if (this.currentElement.attributes.required) span.dataset.required = true;
		textarea = this.apply_attributes(this.currentElement.attributes, textarea);
		if (this.currentElement.attributes.value !== undefined) textarea.appendChild(document.createTextNode(this.currentElement.attributes.value));
		if (this.currentElement.texttemplates !== undefined && this.currentElement.texttemplates) {
			this.currentElement.attributes = {
				value: api._lang.GET("texttemplate.navigation.texts"),
				onclick: "api.texttemplate('get', 'text', 'false', 'modal')",
				class: "floatright",
			};
			delete this.currentElement.hint;
		}
		if (this.currentElement.editor) {
			let div = document.createElement("div");
			div.classList.add("editor");
			div.append(textarea);
			this.codeEditor.push(textarea.id);
			div.append(document.createElement("br"));
			textarea = div;
		}

		if (!(this.currentElement.attributes.disabled || this.currentElement.attributes.readonly) && this.currentElement.autocomplete && this.currentElement.autocomplete.length) {
			/**
			 * adds a *simple* autocomplete option for textareas with keyup event listener
			 * appends rest of match if the input so far matches one of the datalist options.
			 * use Alt and AltGr to navigate within results for being the most unintrusive key
			 * has to be provided with unique entries!
			 */
			const autocompleteoptions = this.currentElement.autocomplete,
				forthKey = api._settings.user.app_settings && api._settings.user.app_settings.autocomplete_forth ? api._settings.user.app_settings.autocomplete_forth : "Alt",
				backKey = api._settings.user.app_settings && api._settings.user.app_settings.autocomplete_back ? api._settings.user.app_settings.autocomplete_back : "AltGraph",
				swipe = api._settings.user.app_settings && api._settings.user.app_settings.autocomplete_swipe;

			const autocomplete = (direction) => {
				let cursorPosition = textarea.selectionStart,
					matches = [],
					start = textarea.value.substring(0, textarea.selectionStart),
					end = textarea.value.substring(textarea.selectionEnd),
					words,
					tail;
				// gather possible matches
				// to handle adding at the end the content is rewinded from the back
				words = start.split(" ");
				for (let i = 0; i < words.length; i++) {
					tail = words.slice(i * -1).join(" ");
					if (!tail.length) continue;
					for (const option of autocompleteoptions) {
						if (option.startsWith(tail)) {
							matches.push(option.substring(tail.length));
						}
					}
				}
				if (matches.length) {
					// navigate through matches with alt key
					if (api._settings.session.textareaAutocompleteIndex === null) api._settings.session.textareaAutocompleteIndex = 0;
					if (api._settings.session.textareaAutocompleteIndex > -1) {
						if (direction === "forth") api._settings.session.textareaAutocompleteIndex++;
						if (direction === "back") api._settings.session.textareaAutocompleteIndex--;
						// out of bound
						if (api._settings.session.textareaAutocompleteIndex > matches.length - 1) api._settings.session.textareaAutocompleteIndex = 0;
						if (api._settings.session.textareaAutocompleteIndex < 0) api._settings.session.textareaAutocompleteIndex = matches.length - 1;
					} else api._settings.session.textareaAutocompleteIndex = 0; // fallback
					textarea.value = start + matches[api._settings.session.textareaAutocompleteIndex] + end;
					textarea.selectionEnd = (start + matches[api._settings.session.textareaAutocompleteIndex]).length;
				} else api._settings.session.textareaAutocompleteIndex = null;
				textarea.selectionStart = cursorPosition;
				textarea.focus();
			};

			// toggle by user set back- and forth-keys
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
				autocomplete(event.key === forthKey ? "forth" : "back");
			});

			if (swipe) {
				// toggle by swipe especially for tablets as buttons appeared too occupying, kudos https://www.kirupa.com/html5/detecting_touch_swipe_gestures.htm
				textarea.addEventListener("touchstart", (event) => {
					api._settings.session.textareaAutocompleteSwipe = [event.touches[0].clientX, event.touches[0].clientY];
				});
				textarea.addEventListener("touchmove", (event) => {
					const travel = [api._settings.session.textareaAutocompleteSwipe[0] - event.touches[0].clientX, api._settings.session.textareaAutocompleteSwipe[1] - event.touches[0].clientY];
					// filter for mostly horizontal swipes
					if (Math.abs(travel[0]) - Math.abs(travel[1]) > 0 && Math.abs(travel[0]) > 100) {
						event.preventDefault();
						api._settings.session.textareaAutocompleteSwipe = [event.touches[0].clientX, event.touches[0].clientY];
						autocomplete(travel[0] > 0 ? "forth" : "back");
					}
				});
			}

			let autocomplete_hint = api._lang.GET("assemble.render.textarea_autocomplete", { ":forth": forthKey, ":back": backKey, ":swipe": swipe ? api._lang.GET("assemble.render.textarea_autocomplete_swipe_active") : "" });
			this.currentElement.hint = this.currentElement.hint ? this.currentElement.hint + " " + autocomplete_hint : autocomplete_hint;
		}

		label.dataset.type = this.currentElement.type;
		label.append(span, textarea);

		let nodelist = [];

		if (this.currentElement.texttemplates !== undefined && this.currentElement.texttemplates) nodelist = [label, ...this.hint(), ...this.button(), ...this.br()];
		else nodelist = [label, ...this.hint()];
		if (autocompletebuttons) nodelist.push(...autocompletebuttons);

		return nodelist;
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
	 * 		"htmlcontent": "serverside preparsed <strong>html</strong>"
	 * 		"attributes": {
	 * 			"name": "very informative, content of header, former description"
	 * 			"otherattribute": "value applies to header"
	 * 		},
	 * 		"markdown": boolean, actually only used by the backend for preprocessing if document components are supposed to be parsed as such
	 * 	}
	 */
	textsection() {
		let result = [],
			p,
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
		if (this.currentElement.htmlcontent) {
			p = document.createElement("p");
			p.id = getNextElementID();
			p.innerHTML = this.currentElement.htmlcontent;
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

	/**
	 * creates a part of transfer schedule input or display
	 *
	 * must be used in combination with longtermplanning_topics
	 * separated for structural reasons
	 *
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		type: "longtermplanning_timeline",
	 * 		attributes: {
	 * 			name: "Transfer schedule",
	 * 			readonly: true
	 * 		},
	 * 		content: {
	 * 			"Name 1": [],
	 * 			"Name 2": [],
	 * 		},
	 * 		hint: "lorem ipsum"
	 * 	}
	 * ```
	 */
	longtermplanning_timeline() {
		let cal = [],
			current,
			labels,
			label,
			every = 4;
		this.currentElement.description = this.currentElement.attributes.name !== undefined ? this.currentElement.attributes.name : "";

		/**
		 * create a name input for editing mode
		 * @param {string} name
		 * @returns {domNode} label containing span and input
		 */
		function namefield(name) {
			let label = document.createElement("label"),
				span = document.createElement("span"),
				input = document.createElement("input");
			label.dataset.type = "text";
			span.appendChild(document.createTextNode(api._lang.GET("calendar.longtermplanning.name")));
			input.type = "text";
			input.name = "_schedule[]";
			input.value = name;
			label.append(span, input);
			return label;
		}

		/**
		 * create a schedule display
		 * @param {object} timeunits of label-color pairs
		 * @param {*} readonly whatever compares to boolean
		 * @returns array of dom nodes
		 */
		function schedules(timeunits, readonly = false, every = 4) {
			let daytile,
				label,
				labels = [],
				labelcount = 0,
				result = [];
			for (const [lbl, clr] of Object.entries(timeunits)) {
				daytile = document.createElement("div");
				daytile.style.width = "calc(100% / " + Object.entries(timeunits).length + ")";
				if (clr) daytile.style.backgroundColor = clr;
				if (!readonly) {
					daytile.addEventListener("click", (e) => {
						e.target.style.backgroundColor = document.getElementById("_current").value;
					});
					daytile.addEventListener("contextmenu", (e) => {
						e.preventDefault();
					});
					// add constant drawing features
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
				}
				result.push(daytile);

				label = document.createElement("label");
				label.classList.add("schedule");
				label.style.width = "calc(100% / " + Object.entries(timeunits).length + ")";
				label.append(document.createTextNode(lbl));
				if (labelcount++ % every) label.style.visibility = "hidden";
				labels.push(label);
			}
			result.push(...labels);
			return result;
		}

		// add paintable divs and labels like provided
		for (const [name, timeunit] of Object.entries(this.currentElement.content)) {
			current = document.createElement("div");
			current.classList.add("schedule");

			if (!this.currentElement.attributes.readonly) {
				label = namefield(name);
			} else {
				label = document.createElement("header");
				label.append(document.createTextNode(name));
			}
			cal.push(label);
			current.append(...schedules(timeunit, this.currentElement.attributes.readonly, every));
			labels = timeunit; // pass last timeunit to method scoped labels for reuse in case of appending another name
			cal.push(current);
		}
		if (!this.currentElement.attributes.readonly) {
			// add button to add a new name
			current = document.createElement("button");
			current.type = "button";
			current.append(document.createTextNode(api._lang.GET("calendar.longtermplanning.addname")));
			current.addEventListener("click", (e) => {
				const options = {};
				options[api._lang.GET("general.cancel_button")] = false;
				options[api._lang.GET("general.ok_button")] = { value: true, class: "reducedCTA" };
				new Dialog({
					type: "input",
					header: api._lang.GET("calendar.longtermplanning.addname"),
					render: [
						[
							{
								type: "text",
								attributes: {
									name: api._lang.GET("calendar.longtermplanning.addname"),
								},
							},
						],
					],
					options: options,
				}).then((response) => {
					if (Boolean(response) && response[api._lang.GET("calendar.longtermplanning.addname")]) {
						// add name input
						e.target.parentNode.insertBefore(namefield(response[api._lang.GET("calendar.longtermplanning.addname")]), e.target);
						// add schedule display
						let div = document.createElement("div");
						div.classList.add("schedule");
						Object.keys(labels).forEach((key) => {
							// unset colors
							labels[key] = null;
						});
						div.append(...schedules(labels, false, every));
						e.target.parentNode.insertBefore(div, e.target);
						window.Masonry.masonry().catch(() => {
							/*catch error to prevent console error*/
						});
					}
				});
			});
			cal.push(current);
		}
		// add hint
		if (!this.currentElement.attributes.readonly) {
			this.currentElement.hint = (this.currentElement.hint || "") + " " + api._lang.GET("calendar.longtermplanning.hint");
		}
		return [...this.header(), ...cal, ...this.hint()];
	}

	/**
	 * creates a part of transfer schedule input or display
	 *
	 * must be used in combination with longtermplanning_timeline
	 * separated for structural reasons
	 *
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		type: "longtermplanning_topics",
	 * 		attributes: {
	 * 			readonly: true
	 * 		},
	 * 		content: {
	 * 			"#ff0000": "Unit 1",
	 * 			"#00ff00": "Unit 2",
	 * 			"#0000ff": "Unit 3",
	 * 		},
	 * 		hint: "lorem ipsum"
	 * 	}
	 * ```
	 */
	longtermplanning_topics() {
		let preset = [],
			current;

		/**
		 * create a color input to draw with
		 * @param {string} name for input and label
		 * @param {string} color hex-notation
		 * @param {*} readonly whatever compares to boolean
		 * @returns {domNode} label containing span and input
		 */
		function colorselection(name, color = "#000000", readonly = false) {
			let label = document.createElement("label"),
				span = document.createElement("span"),
				input = document.createElement("input");
			label.dataset.type = "color";
			span.appendChild(document.createTextNode(name));

			input.type = "color";
			input.name = name;
			input.value = color;
			if (!readonly) {
				input.addEventListener("click", (e) => {
					document.getElementById("_current").value = e.target.value;
				});
				input.addEventListener("change", (e) => {
					document.getElementById("_current").value = e.target.value;
				});
				input.addEventListener("contextmenu", (e) => {
					e.preventDefault();
					const options = {};
					options[api._lang.GET("general.cancel_button")] = false;
					options[api._lang.GET("general.ok_button")] = { value: true, class: "reducedCTA" };
					new Dialog({ type: "confirm", header: api._lang.GET("calendar.longtermplanning.delete_color_header"), options: options }).then((confirmation) => {
						if (confirmation) e.target.parentNode.remove();
					});
				});
			} else {
				input.addEventListener("click", (e) => {
					e.preventDefault();
				});
			}
			label.append(span, input);
			return label;
		}

		if (!this.currentElement.attributes || !this.currentElement.attributes.readonly) {
			// add color selection and erase button
			current = document.createElement("input");
			current.type = "hidden";
			current.id = "_current";
			preset.push(current);

			current = document.createElement("button");
			current.type = "button";
			current.append(document.createTextNode(api._lang.GET("calendar.longtermplanning.delete_color")));
			current.addEventListener("click", (e) => {
				document.getElementById("_current").value = "inherit";
			});
			preset.push(current);
		}
		// add colors
		if (this.currentElement.content) {
			for (const [name, color] of Object.entries(this.currentElement.content)) {
				current = colorselection(name, color, this.currentElement.attributes && this.currentElement.attributes.readonly);
				preset.push(current);
			}
		}
		// add button to append another color selection
		// and hint
		if (!this.currentElement.attributes || !this.currentElement.attributes.readonly) {
			current = document.createElement("button");
			current.type = "button";
			current.append(document.createTextNode(api._lang.GET("calendar.longtermplanning.addcolor")));
			current.addEventListener("click", (e) => {
				const options = {};
				options[api._lang.GET("general.cancel_button")] = false;
				options[api._lang.GET("general.ok_button")] = { value: true, class: "reducedCTA" };
				new Dialog({
					type: "input",
					header: api._lang.GET("calendar.longtermplanning.addcolor_name"),
					render: [
						[
							{
								type: "text",
								attributes: {
									name: api._lang.GET("calendar.longtermplanning.addcolor_name"),
								},
							},
						],
					],
					options: options,
				}).then((response) => {
					if (Boolean(response) && response[api._lang.GET("calendar.longtermplanning.addcolor_name")]) {
						let label = colorselection(response[api._lang.GET("calendar.longtermplanning.addcolor_name")]);
						e.target.parentNode.insertBefore(label, e.target);
						window.Masonry.masonry().catch(() => {
							/*catch error to prevent console error*/
						});
					}
				});
			});
			preset.push(current);
			this.currentElement.hint = (this.currentElement.hint || "") + " " + api._lang.GET("calendar.longtermplanning.hint");
		}
		return [...this.header(), ...preset, ...this.hint()];
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

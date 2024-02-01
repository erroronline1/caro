/*
this module helps to assemble forms according to the passed simplified object notation.
*/
import SignaturePad from '../libraries/signature_pad.umd.js';
import QrCreator from '../libraries/qr-creator.js';

var ElementID = 0,
	signaturecanvas = null;

export function getNextElementID() {
	return 'elementID' + ++ElementID;
}

const events = ['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown', 'onpointerup'];

export const assemble_helper = {
	getNextElementID: getNextElementID,
	exportCanvas: function (id, name) {
		document.getElementById(id).toBlob(function (blob) {
			const blobUrl = URL.createObjectURL(blob),
				link = document.createElement('a'); // Or maybe get it from the current document
			link.href = blobUrl;
			link.download = name + '.png';
			link.click();
		}, 'image/png', 1)
	},
	userMenu: function (content) {
		if (!content) return;
		const menu = document.querySelector('nav'),
			elements = [],
			icons = {
				'default': "url('media/bars.svg')"
			};
		icons[LANG.GET('menu.application_header')] = "url('media/bars.svg')";
		icons[LANG.GET('menu.message_header')] = "url('media/envelope.svg')";
		icons[LANG.GET('menu.forms_header')] = "url('media/pencil.svg')";
		icons[LANG.GET('menu.purchase_header')] = "url('media/shopping-cart.svg')";
		icons[LANG.GET('menu.files_header')] = "url('media/cloud-download.svg')";
		icons[LANG.GET('menu.tools_header')] = "url('media/tools.svg')";

		let label, input, ul, li, link;
		for (const [group, items] of Object.entries(content)) {
			label = document.createElement('label');

			label.style.maskImage = label.style.webkitMaskImage = icons[group];
			label.htmlFor = 'userMenu' + group;
			label.setAttribute('data-notification', 0);

			input = document.createElement('input');
			input.type = 'radio';
			input.name = 'userMenu';
			input.id = 'userMenu' + group;

			ul = document.createElement('ul');
			li = document.createElement('li');
			li.append(document.createTextNode(group));
			ul.append(li);
			ul.style.maxHeight = (Object.entries(items).length + 1) * 3 + 2 + 'em';
			for (const [description, attributes] of Object.entries(items)) {
				li = document.createElement('li');
				if ('href' in attributes) {
					link = document.createElement('a');
					for (const [attribute, value] of Object.entries(attributes)) {
						link.setAttribute(attribute, value);
					}
					link.appendChild(document.createTextNode(description));
					li.append(link);
				} else li.append(document.createTextNode(description))
				ul.append(li);
			}
			elements.push(label);
			elements.push(input);
			elements.push(ul);
		}
		menu.replaceChildren(...elements);
	}
}

export class Dialog {
	/**
	 * 
	 * @param {type: str, icon: str, header: str, body: str, options:{displayText: value str|bool|{value, class}}} options 
	 * @returns promise
	 * 
	 * new Dialog({options}).then((response) => {
	 * 		doSomethingWithButtonValue(response.target.returnValue);
	 * 	});
	 * 
	 * select will be implemented by Assemble
	 * 
	 * input needs button options as well
	 * new Dialog({type:'input', header:'textinput', options:{'abort': false, 'send with text': {'value': true, class: 'reducedCTA'}}}).then((response) => {
	 *  	if (response.target.returnValue==='true') console.log('this is the text input:', document.querySelector('dialog>form>textarea').value);
	 * 	});
	 * 
	 * new Dialog({type:'scanner'}).then((response) => {
	 *  	if (response.target.returnValue==='true') console.log('this is the text input:', document.querySelector('dialog>form>input').value);
	 * 	});

	 */
	constructor(options = {}) {
		this.type = options.type || null;
		this.icon = options.icon || null;
		this.header = options.header || null;
		this.body = options.body || null;
		this.options = options.options || {};
		this.scannerElements = {};

		const dialog = document.querySelector('dialog');
		if (this.type) {
			const form = document.createElement('form');
			form.method = 'dialog';
			const img = document.createElement('img');
			img.classList.add('close');
			img.src = './media/close.svg';
			img.onpointerdown = new Function("const scanner = document.querySelector('video'); if (scanner) scanner.srcObject.getTracks()[0].stop(); document.querySelector('dialog').close()");
			form.append(img);
			if (this.header || this.body || this.icon) {
				const header = document.createElement('header');
				if (this.icon) {
					const icon = document.createElement('img');
					img.src = this.icon;
					header.append(icon);
				}
				if (this.header) {
					const h3 = document.createElement('h3');
					h3.append(document.createTextNode(this.header));
					header.append(h3);
				}
				if (this.body) {
					header.append(document.createTextNode(this.body));
				}
				form.append(header);
			}
			if (this.type === 'select') form.style.display = 'flex';
			for (const element of this[this.type]()) {
				if (element) form.append(element);
			}
			dialog.replaceChildren(form);
			if (this.type === 'scanner') {
				const scanner = {
					canvas: this.scannerElements.canvas,
					output: this.scannerElements.output,
					button: this.scannerElements.button,
					scanner: new Html5QrcodeScanner(this.scannerElements.canvas.id, {
						fps: 10,
						qrbox: {
							width: 300,
							height: 300
						},
						rememberLastUsedCamera: true,
						aspectRatio: 1.0
					})
				};

				function scanSuccess(decodedText, decodedResult) {
					scanner.output.value = decodedText;
					scanner.button.removeAttribute('disabled');
					scanner.scanner.html5Qrcode.stop();
					scanner.canvas.style.border = 'none';
					scanner.canvas.replaceChildren(document.createTextNode(LANG.GET('general.scan_successful')));
				}
				scanner.scanner.render(scanSuccess);
			}
			return new Promise((resolve, reject) => {
				dialog.showModal();
				dialog.onclose = resolve;
			});
		}
		dialog.close();
	}

	alert() {
		const button = document.createElement('button');
		button.value = true;
		button.append(document.createTextNode(LANG.GET('general.ok_button')));
		return [button];
	}
	confirm() {
		const buttons = [];
		let button;
		for (const [option, value] of Object.entries(this.options)) {
			button = document.createElement('button');
			button.append(document.createTextNode(option));
			button.classList.add('confirmButton');
			if (typeof value === 'string' || typeof value === 'boolean') button.value = value;
			else {
				button.value = value.value;
				if (value.class) button.classList.add(value.class);
			}
			buttons.push(button);
		}
		return buttons;
	}
	select() {
		const buttons = document.createElement('div');
		let button;
		for (const [option, value] of Object.entries(this.options)) {
			button = document.createElement('button');
			button.classList.add('discreetButton');
			button.append(document.createTextNode(option));
			button.value = value;
			buttons.append(button);
		}
		return [buttons];
	}
	input() {
		const textarea = document.createElement('textarea'),
			buttons = [];
		let button;
		for (const [option, value] of Object.entries(this.options)) {
			button = document.createElement('button');
			button.append(document.createTextNode(option));
			button.classList.add('confirmButton');
			if (typeof value === 'string' || typeof value === 'boolean') button.value = value;
			else {
				button.value = value.value;
				if (value.class) button.classList.add(value.class);
			}
			buttons.push(button);
		}
		return [textarea, ...buttons];
	}
	scanner() {
		const div = document.createElement('div'),
			input = document.createElement('input'),
			button = document.createElement('button');
		div.classList.add('scanner');
		div.id = getNextElementID();
		input.type = 'hidden';
		button.append(document.createTextNode(LANG.GET('general.import_scan_result_button_from_modal')));
		button.classList.add('confirmButton');
		button.disabled = true;
		this.scannerElements = {
			canvas: div,
			output: input,
			button: button
		};
		return [div, input, button];
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
	}

	initializeSection(nextSibling = null) {
		if (typeof nextSibling === 'string') nextSibling = document.querySelector(nextSibling);
		if (this.form && !nextSibling) {
			this.section = document.createElement('form');
			this.section.method = 'post';
			this.section.enctype = 'multipart/form-data';
			this.section.onsubmit = () => {
				return this.prepareForm()
			};
			this.apply_attributes(this.form, this.section);

			this.content.push([{
				type: 'submitbutton',
				description: LANG.GET('general.submit_button'),
				attributes: {
					type: 'submit',
				}
			}]);
		} else this.section = document.createElement('div');

		this.assembledPanels = this.processContent();

		if (!nextSibling) {
			this.section.append(...this.assembledPanels);
			document.getElementById('main').insertAdjacentElement('beforeend', this.section);
		} else {
			const tiles = Array.from(this.assembledPanels);
			for (let i = 0; i < tiles.length; i++) {
				nextSibling.parentNode.insertBefore(tiles[i], nextSibling);
			}
		}

		let scrollables = document.querySelectorAll('section');
		for (const section of scrollables) {
			if (section.childNodes.length > 1) section.addEventListener('scroll', this.sectionScroller);
			section.dispatchEvent(new Event('scroll'));
		}

		if (this.signaturePad) {
			this.initialize_SignaturePad();
		}
		if (this.imageQrCode.length) {
			// availableSettings = ['text', 'radius', 'ecLevel', 'fill', 'background', 'size']
			for (const image of this.imageQrCode) {
				QrCreator.render({
					text: image.content,
					size: 1024,
					ecLevel: 'H',
					background: null,
					fill: '#000000',
					radius: 1
				}, document.getElementById(image.id));
			}
		}
		if (this.imageBarCode.length) {
			for (const image of this.imageBarCode) {
				JsBarcode('#' + image.id, image.content.value, {
					format: image.content.format || 'CODE128',
					background: 'transparent',
					displayValue: image.content.displayValue != undefined ? image.content.displayValue : true,
				});
			}
		}
		if (this.imageUrl.length) {
			for (const image of this.imageUrl) {
				let imgcanvas = document.getElementById(image.id),
					img = new Image();
				img.src = image.content;
				img.addEventListener('load', function (e) {
					let x, y,
						w = this.width,
						h = this.height,
						xoffset = 0,
						yoffset = 0;
					if (w >= h) {
						x = imgcanvas.width;
						y = imgcanvas.height * h / w;
						yoffset = (x - y) / 2;
					} else {
						x = imgcanvas.width * w / h;
						y = imgcanvas.height;
						xoffset = (y - x) / 2;
					}
					imgcanvas.getContext('2d').drawImage(this, xoffset, yoffset, x, y)
				});
				img.dispatchEvent(new Event('load'));
			}
		}
	}

	processContent() {
		/**
		 * content to exist of three nestings
		 * [ panel
		 * 		[ slide
		 * 			{ element },
		 * 			{ element }
		 * 		],
		 * 		[ slide ...],
		 * ],
		 * [ panel ...]
		 * 
		 * or two nestings
		 * [ panel
		 * 		{ element },
		 * 		{ element }
		 * ]
		 */
		function processPanel(elements) {
			let content = [],
				widget;
			if (elements.constructor.name === 'Array') {
				const section = document.createElement('section');
				section.id = getNextElementID();
				elements.forEach(element => {
					widget = processPanel.call(this, element);
					if (!typeof widget === 'array') widget = [widget];
					if (elements[0].constructor.name === 'Array') {
						const article = document.createElement('article');
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
				if (elements[0].constructor.name === 'Array') content = content.concat(section, this.slider(section.id, section.childNodes.length));
			} else {
				this.currentElement = elements;
				content = content.concat(this[elements.type]());
			}
			return content;
		}

		let assembledPanels = new Set();
		this.content.forEach(panel => {
			const raw_nodes = processPanel.call(this, panel),
				nodes = [];
			// filter undefined
			raw_nodes.forEach(node => {
				if (node) nodes.push(node);
			});

			let frame = false;
			for (let n = 0; n < nodes.length; n++) {
				if (!(['DATALIST', 'HR', 'BUTTON'].includes(nodes[n].nodeName) || nodes[n].hidden)) {
					frame = true;
					break;
				}
			}
			if (frame) {
				const article = document.createElement('article');
				article.append(...nodes);
				assembledPanels.add(article);
			} else assembledPanels.add(...nodes);
		})
		return assembledPanels;
	}

	prepareForm() {
		/* check non typical input fields for presence of required content */
		const signature = document.getElementById('signaturecanvas'),
			required = document.querySelector('[data-required=required]');
		if (signature) {
			if (signaturePad.isEmpty()) {
				if (signature == required) {
					signature.classList.add("signature_required_alert");
					return false;
				}
				document.getElementById('signature').value = null;
				return;
			}
			let file = new File([this.dataURLToBlob(signaturePad.toDataURL())], "signature.png", {
				type: "image/png",
				lastModified: new Date().getTime()
			});
			let section = new DataTransfer();
			section.items.add(file);
			document.getElementById('signature').files = section.files;
		}
		return;
	}

	slider(sectionID, length) {
		if (length < 2) return;
		const indicators = document.createElement('div'),
			toleft = document.createElement('button'),
			toright = document.createElement('button');
		indicators.classList = 'sectionindicator';
		indicators.id = sectionID + 'indicator';

		toleft.addEventListener('pointerup', function (e) {
			document.getElementById(sectionID).scrollBy({
				top: 0,
				left: -400,
				behaviour: 'smooth'
			});
		});
		toleft.setAttribute('data-type', 'toleft');
		toleft.classList.add('inlinebutton');
		toleft.type = 'button';
		indicators.appendChild(toleft);
		for (let i = 0; i < length; i++) {
			let indicator = document.createElementNS('http://www.w3.org/2000/svg', 'svg'),
				circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
			indicator.classList = 'articleindicator';
			indicator.setAttributeNS(null, 'viewbox', '0 0 10 10');
			circle.setAttributeNS(null, 'cx', '5');
			circle.setAttributeNS(null, 'cy', '5');
			circle.setAttributeNS(null, 'r', '4');
			indicator.appendChild(circle);
			indicators.appendChild(indicator);
		}
		toright.addEventListener('pointerup', function (e) {
			document.getElementById(sectionID).scrollBy({
				top: 0,
				left: 400,
				behaviour: 'smooth'
			});
		});
		toright.setAttribute('data-type', 'toright');
		toright.classList.add('inlinebutton');
		toright.type = 'button';
		indicators.appendChild(toright);
		return indicators;
	}

	sectionScroller(e) {
		/* event handler for horizontal scrolling of multiple panels */
		setTimeout(() => {
			let indicator = document.getElementById(e.target.attributes.id.value + 'indicator');
			for (let panel = 0; panel < e.target.children.length + 1; panel++) {
				try {
					if (panel == Math.round(e.target.scrollLeft / e.target.clientWidth) + 1) indicator.children[
						panel].firstChild.classList.add('articleactive');
					else indicator.children[panel].firstChild.classList.remove('articleactive');
				} catch (err) {
					continue;
				}
			}
		}, 300)
	}

	initialize_SignaturePad() {
		signaturecanvas = document.getElementById("signaturecanvas");
		window.signaturePad = new SignaturePad(signaturecanvas, {
			// It's Necessary to use an opaque color when saving image as JPEG;
			// this option can be omitted if only saving as PNG or SVG
			//backgroundColor: 'rgb(255, 255, 255)'
			penColor: "rgb(46, 52, 64)"
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
		const parts = dataURL.split(';base64,');
		const contentType = parts[0].split(":")[1];
		const raw = window.atob(parts[1]);
		const rawLength = raw.length;
		const uInt8Array = new Uint8Array(rawLength);
		for (let i = 0; i < rawLength; ++i) {
			uInt8Array[i] = raw.charCodeAt(i);
		}
		return new Blob([uInt8Array], {
			type: contentType
		});
	}

	icon() {
		const br = document.createElement('br'),
			span = document.createElement('span');
		span.setAttribute('data-type', this.currentElement.type);
		return [br, span];
	}

	header() {
		if (this.currentElement.description === undefined) return [];
		let header = document.createElement('header');
		header.appendChild(document.createTextNode(this.currentElement.description));
		header.setAttribute('data-type', this.currentElement.type);
		return [header];
	}

	hint() {
		if (this.currentElement.hint === undefined) return [];
		let div = document.createElement('div');
		div.appendChild(document.createTextNode(this.currentElement.hint));
		return [div];
	}

	apply_attributes(setup, node) {
		for (const [key, attribute] of Object.entries(setup)) {
			if (events.includes(key)) {
				node[key] = new Function(attribute);
			} else node.setAttribute(key, attribute);
		}
		return node;
	}

	names_numerator(name, dontnumerate = undefined) {
		if (dontnumerate || [...name.matchAll(/\[\]/g)].length) return name;
		if (name in this.names) {
			this.names[name] += 1;
			return name + '(' + this.names[name] + ')';
		}
		this.names[name] = 1;
		return name;
	}

	text() {
		/* {
			type: 'text',
			description: 'very informative',
			content: 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.'
		}*/
		let result = [];
		if (this.currentElement.description) {
			result = result.concat(this.header());
		}
		if (this.currentElement.content) {
			const content = this.currentElement.content.matchAll(/(.*?)(?:\\n|\n|<br.\/>|<br>|$)/gm);
			for (const part of content) {
				if (!part[1].length) continue;
				result.push(document.createTextNode(part[1]));
				result.push(document.createElement('br'));
			}
		}
		result.push(document.createElement('br'));
		return result;
	}

	input(type) {
		/*{
			type: 'textinput',
			hint: 'please provide information about...',
			numeration: anything resulting in true to prevent enumeration
			attributes: {
				name: 'variable name' // will be used as an accessible placeholder
			}
		}*/
		let input = document.createElement('input'),
			label;
		input.type = type;
		if (type === 'password') this.currentElement.type = 'password';
		input.id = this.currentElement.attributes && this.currentElement.attributes.id ? this.currentElement.attributes.id : getNextElementID();
		input.autocomplete = (this.currentElement.attributes && this.currentElement.attributes.type) === 'password' ? 'one-time-code' : 'off';
		label = document.createElement('label');
		label.htmlFor = input.id;
		label.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]/g, '')));
		this.currentElement.attributes.placeholder = ' '; // to access input:not(:placeholder-shown) query selector 
		label.classList.add('input-label');

		if (this.currentElement.attributes.name !== undefined) this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		input = this.apply_attributes(this.currentElement.attributes, input);
		if (this.currentElement.attributes.hidden !== undefined) return input;
		return [...this.icon(), input, label, ...this.hint()];
	}
	textinput() {
		return this.input('text');
	}
	numberinput() {
		return this.input('number');
	}
	dateinput() {
		return this.input('date');
	}
	timeinput() {
		return this.input('time');
	}
	searchinput() {
		return this.input('search');
	}
	filterinput() {
		return this.input('search');
	}
	button() {
		/*{
			type: 'button',
			description: 'some button',
			attributes: {
				onpointerdown: 'alert("hello")'
			}
		}*/
		let button = document.createElement('button');
		button.id = getNextElementID();
		if (this.currentElement.description) button.appendChild(document.createTextNode(this.currentElement.description));
		if (this.currentElement.attributes.value !== undefined) {
			button.appendChild(document.createTextNode(this.currentElement.attributes.value));
			delete this.currentElement.attributes.value;
		}
		if (this.currentElement.attributes !== undefined) button = this.apply_attributes(this.currentElement.attributes, button);
		return button;
	}
	deletebutton() { // to style it properly by adding data-type to article container
		this.currentElement.attributes['data-type'] = 'deletebutton';
		return this.button();
	}
	submitbutton() {
		this.currentElement.attributes['data-type'] = 'submitbutton';
		return this.button();
	}

	hiddeninput() {
		/*{
			type: 'hiddeninput',
			numeration: anything resulting in true to prevent enumeration
			attributes: {
				name: 'name',
				value: '3.14'}
			}
		}*/
		let input = document.createElement('input');
		input.type = 'hidden';
		input.id = getNextElementID();
		input.value = this.currentElement.value;
		if (this.currentElement.attributes.name !== undefined) this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		if (this.currentElement.attributes !== undefined) input = this.apply_attributes(this.currentElement.attributes, input);
		return input;
	}
	datalist() {
		let datalist = document.createElement('datalist');
		let option;
		datalist.id = getNextElementID();
		if (this.currentElement.attributes !== undefined) datalist = this.apply_attributes(this.currentElement.attributes, datalist);
		this.currentElement.content.forEach(key => {
			option = document.createElement('option');
			option.value = key;
			datalist.appendChild(option);
		});
		return datalist;
	}

	file() {
		/*{
			type: 'file',
			description: 'file upload',
			attributes: {
				name: 'variable name',
				multiple: true
			}
			hint: 'this file serves as...'
		}*/
		let input = document.createElement('input'),
			label = document.createElement('button'),
			button = document.createElement('button');
		input.type = 'file';
		input.id = getNextElementID();
		input.name = this.currentElement.description;
		if (this.currentElement.attributes !== undefined) input = this.apply_attributes(this.currentElement.attributes, input);
		if (this.currentElement.attributes.multiple !== undefined) input.onchange = function () {
			this.nextSibling.innerHTML = this.files.length ? Array.from(this.files).map(x => x.name).join(
				', ') + ' ' + LANG.GET('assemble.files_rechoose') : LANG.GET('assemble.files_choose');
		}
		else input.onchange = function () {
			this.nextSibling.innerHTML = this.files.length ? Array.from(this.files).map(x => x.name).join(
				', ') + ' ' + LANG.GET('assemble.file_rechoose') : LANG.GET('assemble.file_choose');
		}
		label.onclick = new Function("document.getElementById('" + input.id + "').click();");
		label.type = 'button';
		label.setAttribute('data-type', 'file');
		label.classList.add('inlinebutton');
		label.appendChild(document.createTextNode((this.currentElement.attributes.multiple !== undefined) ? LANG.GET('assemble.files_choose') : LANG.GET('assemble.file_choose')));

		button.onpointerup = new Function("let e=document.getElementById('" + input.id + "'); e.value=''; e.dispatchEvent(new Event('change'));");
		button.appendChild(document.createTextNode('Reset'));
		button.setAttribute('data-type', 'reset');
		button.classList.add('inlinebutton');
		return [...this.header(), input, label, button, ...this.hint()];
	}

	photo() {
		/*{
			type: 'photo',
			description: 'photo upload',
			attributes: {
				name: 'photo'
			}
			hint: 'this photo serves as...'
		}*/
		let input = document.createElement('input'),
			label = document.createElement('button'),
			img = document.createElement('img'),
			resetbutton = document.createElement('button'),
			addbutton = document.createElement('button');

		function changeEvent() {
			this.nextSibling.nextSibling.innerHTML = this.files.length ? Array.from(this.files).map(x => x.name).join(', ') + ' ' + LANG.GET('assemble.photo_rechoose') : LANG.GET('assemble.photo_choose');
			if (this.files.length) this.nextSibling.src = URL.createObjectURL(this.files[0]);
			else this.nextSibling.src = '';
		}

		function cloneNode() {
			const nextPhoto = this.parentNode.cloneNode(true);
			// input type file
			nextPhoto.childNodes[1].id = getNextElementID();
			nextPhoto.childNodes[1].files = null;
			nextPhoto.childNodes[1].onchange = changeEvent;
			// preview image
			nextPhoto.childNodes[2].src = '';
			// label
			nextPhoto.childNodes[3].innerHTML = LANG.GET('assemble.photo_choose');
			nextPhoto.childNodes[3].onclick = new Function("document.getElementById('" + nextPhoto.childNodes[1].id + "').click();");
			// delete button
			if (nextPhoto.childNodes.length < 7) {
				const deletebutton = document.createElement('button');
				deletebutton.setAttribute('data-type', 'deletebutton');
				deletebutton.classList.add('inlinebutton');
				deletebutton.type = 'button';
				nextPhoto.insertBefore(deletebutton, nextPhoto.childNodes[4]);
			}
			nextPhoto.childNodes[4].onpointerup = new Function('this.parentNode.remove();');
			// add button
			nextPhoto.childNodes[5].onpointerup = cloneNode;
			// reset button
			nextPhoto.childNodes[6].onpointerup = new Function("let e=document.getElementById('" + nextPhoto.childNodes[1].id + "'); e.value=''; e.dispatchEvent(new Event('change'));");

			this.parentNode.after(nextPhoto);
		}

		input.type = 'file';
		input.id = getNextElementID();
		input.name = this.currentElement.description + '[]';
		input.accept = 'image/*';
		input.capture = true;
		input.onchange = changeEvent;
		if (this.currentElement.attributes !== undefined) input = this.apply_attributes(this.currentElement.attributes, input);
		label.onclick = new Function("document.getElementById('" + input.id + "').click();");
		label.type = 'button';
		label.setAttribute('data-type', 'photo');
		label.classList.add('inlinebutton');
		label.appendChild(document.createTextNode(LANG.GET('assemble.photo_choose')));

		img.classList.add('photoupload');

		resetbutton.onpointerup = new Function("let e=document.getElementById('" + input.id + "'); e.value=''; e.dispatchEvent(new Event('change'));");
		resetbutton.appendChild(document.createTextNode(LANG.GET('assemble.reset')));
		resetbutton.setAttribute('data-type', 'reset');
		resetbutton.classList.add('inlinebutton');
		resetbutton.type = 'button';

		addbutton.onpointerup = cloneNode;
		addbutton.setAttribute('data-type', 'additem');
		addbutton.classList.add('inlinebutton');
		addbutton.type = 'button';

		return [...this.header(), input, img, label, addbutton, resetbutton, ...this.hint()];
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
		let select = document.createElement('select'),
			label,
			selectModal = {};
		if (this.currentElement.attributes.name !== undefined) this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
		select.title = this.currentElement.attributes.name.replace(/\[\]/g, '');
		if (this.currentElement.attributes !== undefined) select = this.apply_attributes(this.currentElement.attributes, select);

		for (const [key, element] of Object.entries(this.currentElement.content)) {
			if (groups[key[0]] === undefined) groups[key[0]] = [
				[key, element]
			];
			else groups[key[0]].push([key, element]);
			selectModal[key] = element.value || key;
		}
		for (const [group, elements] of Object.entries(groups)) {
			let optgroup = document.createElement('optgroup');
			optgroup.label = group;
			for (const element of Object.entries(elements)) {
				let option = document.createElement('option');
				option = this.apply_attributes(element[1][1], option);
				option.appendChild(document.createTextNode(element[1][0]));
				optgroup.appendChild(option);
			}
			select.appendChild(optgroup);
		}
		label = document.createElement('label');
		label.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]/g, '')));
		label.classList.add('input-label');
		select.addEventListener('pointerdown', (e) => {
			e.preventDefault();
			new Dialog({
				type: 'select',
				header: select.title,
				options: selectModal
			}).then(response => {
				e.target.value = response.target.returnValue;
				e.target.dispatchEvent(new Event('change'));
			});
		});
		return [...this.icon(), select, label, ...this.hint()];
	}

	textarea() {
		/*{
			type: 'textarea',
			hint: 'enter a lot of text',
			numeration: anything resulting in true to prevent enumeration
			attributes: {
				name:'somename'
				rows:8,
				value:'values can be passed with this pseudo attribute'
			}
		}*/
		let textarea = document.createElement('textarea'),
			label;
		textarea.id = getNextElementID();
		textarea.autocomplete = 'off';
		if (this.currentElement.attributes.name !== undefined) {
			this.currentElement.attributes.name = this.names_numerator(this.currentElement.attributes.name, this.currentElement.numeration);
			label = document.createElement('label');
			label.htmlFor = textarea.id;
			label.appendChild(document.createTextNode(this.currentElement.attributes.name.replace(/\[\]/g, '')));
			label.classList.add('textarea-label');
		}
		if (this.currentElement.attributes !== undefined) textarea = this.apply_attributes(this.currentElement.attributes, textarea);
		if (this.currentElement.attributes.value !== undefined) textarea.appendChild(document.createTextNode(this.currentElement.attributes.value));

		return [...this.icon(), label, textarea, ...this.hint()];
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
			radioname = this.currentElement.description ? this.names_numerator(this.currentElement.description, this.currentElement.numeration) : null; // keep same name for current article
		for (const [checkbox, attributes] of Object.entries(this.currentElement.content)) {
			let label = document.createElement('label'),
				input = document.createElement('input');
			label.htmlFor = input.id = getNextElementID();
			if (radio) {
				label.classList.add('radio');
				input.type = 'radio';
				input.name = radioname;
				input.value = checkbox;
			} else {
				label.classList.add('checkbox');
				input.type = 'checkbox';
				input.name = this.names_numerator(checkbox);
			}
			label.append(document.createTextNode(checkbox.replace(/\[\]/g, '')));
			input = this.apply_attributes(attributes, input);
			result.push(input);
			result.push(label);
		}
		return [...result, ...this.hint()];
	}
	radio() {
		return this.checkbox('radioinstead');
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
		}*/
		let result = [...this.header()];
		if (this.currentElement.attributes !== undefined) result.push(this.hiddeninput());
		for (const [link, attributes] of Object.entries(this.currentElement.content)) {
			let a = document.createElement('a');
			a = this.apply_attributes(attributes, a);
			if (!a.href) a.href = link;
			a.appendChild(document.createTextNode(link));
			result.push(a);
		}
		return [...result, ...this.hint()];
	}

	signature() {
		/*{
			type: 'signature',
			description:'signature',
			required: optional boolean,
			hint: 'this signature is for...'
		} */
		let result = [...this.header()];
		const canvas = document.createElement('canvas');
		canvas.id = 'signaturecanvas';
		if (this.currentElement.attributes && this.currentElement.attributes.required) canvas.setAttribute('data-required', 'required');
		result.push(canvas);
		//this tile does not process attributes, therefore they can be reassigned
		this.currentElement.description = LANG.GET('assemble.clear_signature');
		this.currentElement.attributes = {
			'type': 'button',
			'name': '',
			'onpointerup': 'signaturePad.clear()'
		};
		result = result.concat(this.deletebutton());
		const input = document.createElement('input');
		input.type = 'file';
		input.id = input.name = 'signature';
		input.hidden = true;
		result.push(input);
		result = result.concat(this.hint());
		this.signaturePad = true;
		return result;
	}

	scanner() {
		/*{
			type: 'scanner',
			description:'access credentials' (e.g.),
			attributes:{type:'password'} // to override e.g. for logins
			destination: elementId // force output to other input, e.g. search
		} */
		let result = [],
			input, inputid;
		if (this.currentElement.destination !== undefined) {
			inputid = this.currentElement.destination;
		} else {
			if (this.currentElement.attributes.type !== undefined) input = [...this.input(this.currentElement.attributes.type)];
			else input = [...this.input('text')];
			inputid = input[1].id ? input[1].id : input[2].id;
			result = result.concat(input);
		}
		//attributes are processed already, therefore they can be reassigned
		this.currentElement.description = this.currentElement.description ? this.currentElement.description : LANG.GET('assemble.scan_button');
		this.currentElement.attributes = {
			'onpointerup': "new Dialog({type:'scanner'}).then((response) => {" +
				"document.getElementById('" + inputid + "').value = document.querySelector('dialog>form>input').value;" +
				"});",
			'data-type': 'scanner',
			'type': 'button'
		};
		result.push(this.button())
		return result;
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
		const canvas = document.createElement('canvas');
		let disabled = true;
		canvas.id = getNextElementID();
		canvas.classList.add('imagecanvas');
		if (typeof this.currentElement.attributes.imageonly === 'object') {
			for (const [key, value] of Object.entries(this.currentElement.attributes.imageonly)) {
				canvas.style[key] = value;
			}
		} else result = result.concat(this.header());

		canvas.width = canvas.height = 1024;
		if (this.currentElement.attributes.qrcode) {
			this.imageQrCode.push({
				id: canvas.id,
				content: this.currentElement.attributes.qrcode
			});
			disabled = false;
		}
		if (this.currentElement.attributes.barcode) {
			this.imageBarCode.push({
				id: canvas.id,
				content: this.currentElement.attributes.barcode
			});
			disabled = false;
		}
		if (this.currentElement.attributes.url) {
			this.imageUrl.push({
				id: canvas.id,
				content: this.currentElement.attributes.url
			});
			disabled = false;
		}

		result.push(canvas);

		if (!this.currentElement.attributes.imageonly) {
			//this tile does not process attributes, therefore they can be reassigned
			this.currentElement.attributes = {
				'type': 'button',
				'class': 'inlinebutton',
				'data-type': this.currentElement.type,
				'onpointerup': 'assemble_helper.exportCanvas("' + canvas.id + '", "' + this.currentElement.attributes.name + '")'
			};
			if (disabled) this.currentElement.attributes.disabled = true;
			result = result.concat(this.button());
		}
		return result;
	}

	stlviewer() {
		/*{
			type: 'stlviewer',
			description:'viewstl' (e.g.),
		} */
		const div = document.createElement('div');
		div.id = 'stlviewer_canvas';
		div.classList = 'stlviewer';
		return div;
	}

	nocontent() {
		const img = document.createElement('div');
		const span = document.createElement('span');
		span.append(document.createTextNode(this.currentElement.content));
		img.classList.add('nocontent');
		span.classList.add('nocontent');
		return [img, span];
	}

	trash() {
		// empty method but necessary to display the delete-area for composer or other future use
	}

	hr() {
		return (document.createElement('hr'));
	}

	br() {
		return (document.createElement('br'));
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
}
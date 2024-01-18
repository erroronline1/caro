/*
this module helps to assemble forms according to the passed simplified object notation.
*/
import SignaturePad from '../libraries/signature_pad.umd.js';
import QrCreator from '../libraries/qr-creator.js';

var ElementID = 0;

export function getNextElementID() {
	return 'elementID' + ++ElementID;
}

const events = ['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'];

export const assemble_helper = {
	getNextElementID: getNextElementID,
	initialize_scanner: async function (videostream, resultTo) {
		/*
		// todo: get pure scanner api working...
		const scanner = {
				canvas: document.getElementById(videostream),
				output: document.getElementById(resultTo),
				config: {
					videoConstraints: {
						height: 250,
						aspectRatio: 1,
						frameRate: 10
					}
				},
				scanner: new Html5Qrcode(videostream)
			},
			camera = await Html5Qrcode.getCameras();
		console.log(camera);
		scanner.canvas.classList.add('active');
		scanner.canvas.nextElementSibling.nextElementSibling.disabled = 'true';

		function scanSuccess(decodedText, decodedResult) {
			scanner.output.value = decodedText;
			scanner.canvas.classList.remove('active');
			scanner.scanner.stop();
		}
		scanner.scanner.start(camera[0].id, scanner.config, scanSuccess);
		//scanner.scanner.render(scanSuccess);
		*/
		const scanner = {
			canvas: document.getElementById(videostream),
			output: document.getElementById(resultTo),
			scanner: new Html5QrcodeScanner(videostream, {
				fps: 10,
				qrbox: 350
			})
		};
		scanner.canvas.classList.add('active');

		function scanSuccess(decodedText, decodedResult) {
			scanner.output.value = decodedText;
			scanner.canvas.classList.remove('active');
			scanner.scanner.html5Qrcode.stop();
			scanner.canvas.innerHTML = '';
		}
		scanner.scanner.render(scanSuccess);
	},
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
		const elements = [];
		for (const [group, items] of Object.entries(content)) {
			const wrapper = document.createElement('div');
			const header = document.createElement('h3');
			header.appendChild(document.createTextNode(group));
			wrapper.appendChild(header);
			for (const [description, attributes] of Object.entries(items)) {
				const link = document.createElement('a');
				for (const [attribute, value] of Object.entries(attributes)) {
					link.setAttribute(attribute, value);
				}
				link.appendChild(document.createTextNode(description));
				wrapper.appendChild(link);
			};
			elements.push(wrapper);
		};
		document.querySelector('#menu').innerHTML = '';
		document.querySelector('#menu').append(...elements);
	}
}

const sectionScroller = (e) => {
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
};

function prepareForm() {
	/* check non typical input fields for presence of required content */
	const signature = document.getElementById('signaturecanvas'),
		required = document.querySelector('[data-required=required]');
	if (signature) {
		if (signaturePad.isEmpty()) {
			if (signature == required) {
				signature.classList.add("alert");
				return false;
			}
			document.getElementById('signature').value = null;
			return;
		}
		let file = new File([dataURLToBlob(signaturePad.toDataURL())], "signature.png", {
			type: "image/png",
			lastModified: new Date().getTime()
		});
		let section = new DataTransfer();
		section.items.add(file);
		document.getElementById('signature').files = section.files;
	}
	return;
};

export class Assemble {
	/* 
	assembles forms and screen elements.
	deepest nesting of input object is three levels
	form:null or {attributes} / nothing creates just a div e.g. just for text and links
	content:[	see this.processContent() ]

	elements are assembled by default but can be assigned common html attributes
	names are set according to description if not overridden by explicit attribute
	*/
	constructor(setup) {
		this.content = setup.content;
		this.form = setup.form;
		this.multiplearticles = [];
		this.multiplearticleID = null;
		this.section = null;
		this.imageQrCode = [];
		this.imageBarCode = [];
		this.imageUrl = [];
		this.articleAttributes = []; // to be overhauled
	}

	initializeSection(nextSibling = null) {
		nextSibling = document.querySelector(nextSibling);
		if (this.form && !nextSibling) {
			this.section = document.createElement('form');
			this.section.method = 'post';
			this.section.enctype = 'multipart/form-data';
			this.section.onsubmit = () => {
				return prepareForm()
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
		for (let i = 0; i < this.multiplearticles.length; i++) {
			document.getElementById(this.multiplearticles[i]).addEventListener('scroll', sectionScroller);
		}

		if (this.signaturePad) {
			initialize_SignaturePad();
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
		let assembledPanels = new Set();
		this.content.forEach(panel => {
			function processPanel(elements) {
				let content = [];
				if (elements.constructor.name === 'Array') {
					const section = document.createElement('section');
					section.id = getNextElementID();
					elements.forEach(element => {
						if (elements[0].constructor.name === 'Array') section.append(...processPanel.call(this, element));
						else content = content.concat(processPanel.call(this, element));
					});
					if (elements[0].constructor.name === 'Array') content = content.concat(section);
				} else {
					this.currentElement = elements;
					content = content.concat(this[elements.type]());
				}
				return content;
			}
			const nodes = processPanel.call(this, panel);
			let frame = false;
			for (let n = 0; n < nodes.length; n++) {
				if (!(['DATALIST', 'HR'].includes(nodes[n].nodeName) || nodes[n].hidden)) {
					frame = true;
					break;
				}
			}
			if (frame) {
				console.log(nodes[0].hidden);
				const article = document.createElement('article');
				article.append(...nodes);
				assembledPanels.add(article);
			} else assembledPanels.add(...nodes);
		})
		return assembledPanels;
		/*	this.content.forEach(tile => {
				this.articleAttributes = [];
				this.multipletiles = new Set();
				for (let i = 0; i < tile.length; i++) {
					this.currentElement = tile[i];
					collapse = tile[i].collapse || false;
					if (!collapse || i === 0) this.elements = new Set();
					this.description(i);
					this[tile[i].type]();
					if ('article' in tile[i]) this.articleAttributes = tile[i].article;
					if ((tile[i].type === 'hiddeninput' && !this.setup.visible) || tile[i].type === 'datalist' || tile[i].type === 'hr' || (collapse && i < tile.length - 1)) continue;
					if (tile.length < 2 || collapse) {
						this.assembledPanels.add(this.single(tile[i]));
						continue;
					}
					this.multipletiles.add(this.single(tile[i], true))
				}
				if (this.multipletiles.size) this.assembledPanels.add(this.multiple());
			});*/


	}
	single(tileProperties, oneOfFew = false) { // parameters are required by composer method
		const article = document.createElement('article');
		article.setAttribute('data-type', tileProperties.type);
		for (const [attribute, value] of Object.entries(this.articleAttributes)) {
			article.setAttribute(attribute, value);
		}
		article.append(...this.elements);
		return article;
	}
	multiple() {
		const article = document.createElement('article'),
			section = document.createElement('section'),
			indicators = document.createElement('div'),
			toleft = document.createElement('div'),
			toright = document.createElement('div');
		this.multiplearticleID = getNextElementID();
		this.multiplearticles.push(this.multiplearticleID);
		section.classList = 'inset';
		section.id = this.multiplearticleID;
		section.append(...this.multipletiles);
		article.appendChild(section);
		indicators.classList = 'sectionindicator';
		indicators.id = this.multiplearticleID + 'indicator';

		toleft.classList = 'toleft';
		toleft.addEventListener('pointerdown', function (e) {
			section.scrollBy({
				top: 0,
				left: -400,
				behaviour: 'smooth'
			});
		});
		indicators.appendChild(toleft);
		for (let i = 0; i < this.multipletiles.size; i++) {
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
		toright.classList = 'toright';
		toright.addEventListener('pointerdown', function (e) {
			section.scrollBy({
				top: 0,
				left: 400,
				behaviour: 'smooth'
			});
		});
		indicators.appendChild(toright);
		article.appendChild(indicators);
		for (const [attribute, value] of Object.entries(this.articleAttributes)) {
			article.setAttribute(attribute, value);
		}
		return article;
	}

	description(i) {
		if ([undefined, null, false].includes(this.currentElement.description) || (this.currentElement.collapse && i > 0)) return;
		const header = document.createElement('header');
		header.appendChild(document.createTextNode(this.currentElement.description));
		this.elements.add(header);
	}

	apply_attributes(setup, node) {
		for (const [key, attribute] of Object.entries(setup)) {
			if (events.includes(key)) {
				node[key] = new Function(attribute);
			} else node.setAttribute(key, attribute);
		}
		return node;
	}

	has_content(tile) {
		return ('content' in tile || 'attributes' in tile || 'description' in tile);
	}

	text() {
		/* {
			type: 'text',
			description: 'very informative',
			content: 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.'
		}*/
		if (!this.has_content(this.currentElement)) return;
		const content = this.currentElement.content.matchAll(/(.*?)(?:\\n|\n|<br.\/>|<br>|$)/gm),
			result = [];
		for (const part of content) {
			if (!part[1].length) continue;
			result.push(document.createTextNode(part[1]));
			result.push(document.createElement('br'));
		}
		result.push(document.createElement('br'));
		return result;
	}

	input(type) {
		/*{
			type: 'textinput',
			description: 'text input',
			attributes: {
				placeholder: 'text input'
			}
		}*/
		if (!this.has_content(this.currentElement)) return;
		let input = document.createElement('input');
		let label;
		input.type = type;
		input.id = this.currentElement.attributes && this.currentElement.attributes.id ? this.currentElement.attributes.id : getNextElementID();
		input.autocomplete = (this.currentElement.attributes && this.currentElement.attributes.type) === 'password' ? 'one-time-code' : 'off';
		if (this.currentElement.description) input.name = this.currentElement.description;
		input.classList.add('input-field');
		if (this.currentElement.attributes !== undefined) {
			if (this.currentElement.attributes.placeholder) {
				label = document.createElement('label');
				label.htmlFor = input.id;
				label.appendChild(document.createTextNode(this.currentElement.attributes.placeholder));
				this.currentElement.attributes.placeholder = ' ';
				label.classList.add('input-label');
			}
			input = this.apply_attributes(this.currentElement.attributes, input);
		}
		if (label) return [input, label];
		return input;
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
	button() {
		/*{
			type: 'button',
			description: 'some button',
			attributes: {
				onpointerdown: 'alert("hello")'
			}
		}*/
		if (!this.has_content(this.currentElement)) return;
		let button = document.createElement('button');
		button.id = getNextElementID();
		if (this.currentElement.description) button.appendChild(document.createTextNode(this.currentElement.description));
		if (this.currentElement.attributes && 'value' in this.currentElement.attributes) {
			button.appendChild(document.createTextNode(this.currentElement.attributes.value));
			delete this.currentElement.attributes.value;
		}
		if (this.currentElement.attributes !== undefined) button = this.apply_attributes(this.currentElement.attributes, button);
		return button;
	}
	deletebutton() { // to style it properly by adding data-type to article container
		return this.button();
	}
	submitbutton() {
		return this.button();
	}

	hiddeninput() {
		/*{
			type: 'hiddeninput',
			description: 'value of pi',
			attributes: {value: '3.14'}
			}
		}*/
		if (!this.has_content(this.currentElement)) return;
		let input = document.createElement('input');
		input.type = 'hidden';
		input.name = this.currentElement.description;
		input.id = getNextElementID();
		input.value = this.currentElement.value;
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
				multiple: true
			}
		}*/
		if (!this.has_content(this.currentElement)) return;
		let input = document.createElement('input'),
			label = document.createElement('button'),
			button = document.createElement('button');
		input.type = 'file';
		input.id = getNextElementID();
		input.name = this.currentElement.description;
		if (this.currentElement.attributes !== undefined) input = this.apply_attributes(this.currentElement.attributes, input);
		if ('attributes' in this.currentElement && 'multiple' in this.currentElement.attributes) input.onchange = function () {
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
		label.appendChild(document.createTextNode(('attributes' in this.currentElement && 'multiple' in this.currentElement.attributes) ? LANG.GET('assemble.files_choose') : LANG.GET('assemble.file_choose')));

		button.onpointerdown = new Function("let e=document.getElementById('" + input.id + "'); e.value=''; e.dispatchEvent(new Event('change'));");
		button.appendChild(document.createTextNode('Reset'));
		button.setAttribute('data-type', 'reset');
		button.classList.add('inlinebutton');

		return [input, label, button];
	}

	photo() {
		/*{
			type: 'photo',
			description: 'photo upload',
			attributes: {
				name: 'photo'
			}
		}*/
		if (!this.has_content(this.currentElement)) return;
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
			nextPhoto.childNodes[4].onpointerdown = new Function('this.parentNode.remove();');
			// add button
			nextPhoto.childNodes[5].onpointerdown = cloneNode;
			// reset button
			nextPhoto.childNodes[6].onpointerdown = new Function("let e=document.getElementById('" + nextPhoto.childNodes[1].id + "'); e.value=''; e.dispatchEvent(new Event('change'));");

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

		resetbutton.onpointerdown = new Function("let e=document.getElementById('" + input.id + "'); e.value=''; e.dispatchEvent(new Event('change'));");
		resetbutton.appendChild(document.createTextNode(LANG.GET('assemble.reset')));
		resetbutton.setAttribute('data-type', 'reset');
		resetbutton.classList.add('inlinebutton');
		resetbutton.type = 'button';

		addbutton.onpointerdown = cloneNode;
		addbutton.setAttribute('data-type', 'additem');
		addbutton.classList.add('inlinebutton');
		addbutton.type = 'button';

		return [input, img, label, addbutton, resetbutton];
	}

	select() {
		/*{
			type: 'select',
			description: 'list',
			content: {
				'entry one': {
					value: '1'
				},
				'entry two': {
					value: '2',
					selected: true
				}
			}
		}*/
		if (!this.has_content(this.currentElement)) return;
		const groups = {};
		let select = document.createElement('select');

		select.name = select.title = this.currentElement.description;
		if (this.currentElement.attributes !== undefined) select = this.apply_attributes(this.currentElement.attributes, select);

		for (const [key, element] of Object.entries(this.currentElement.content)) {
			if (groups[key[0]] === undefined) groups[key[0]] = [
				[key, element]
			];
			else groups[key[0]].push([key, element]);
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
		return select;
	}

	textarea() {
		/*{
			type: 'textarea',
			description: 'textarea',
			attributes: {
				rows:8,
				value:'values can be passed with this pseudo attribute'
			}
		}*/
		if (!this.has_content(this.currentElement)) return;
		let textarea = document.createElement('textarea');
		textarea.name = this.currentElement.description;
		textarea.autocomplete = 'off';
		if (this.currentElement.attributes !== undefined) textarea = this.apply_attributes(this.currentElement.attributes, textarea);
		if (this.currentElement.attributes && 'value' in this.currentElement.attributes) textarea.appendChild(document.createTextNode(this.currentElement.attributes.value));
		return textarea;
	}

	checkbox(radio = null) {
		/*{
			type: 'checkbox', or 'radio'
			description:'checkboxes',
			content: {
				'Checkbox 1': {
					optional attributes
				},
				'Checkbox 2': {
					optional attributes
				}
			}
		}*/
		if (!this.has_content(this.currentElement)) return;
		const result = [];
		for (const [checkbox, attributes] of Object.entries(this.currentElement.content)) {
			let label = document.createElement('label'),
				input = document.createElement('input');
			if (radio) {
				input.type = 'radio';
				input.name = this.currentElement.description;
				input.value = checkbox;
			} else {
				input.type = 'checkbox';
				input.name = checkbox;
			}

			label.classList.add('check');
			input = this.apply_attributes(attributes, input);
			label.append(input, document.createTextNode(checkbox));
			result.push(label);
		}
		return result;
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
			}
		}*/
		if (!this.has_content(this.currentElement)) return;
		let ul = document.createElement('ul');
		for (const [link, attributes] of Object.entries(this.currentElement.content)) {
			let li = document.createElement('li'),
				a = document.createElement('a');
			a = this.apply_attributes(attributes, a);
			if (!a.href) a.href = link;
			a.appendChild(document.createTextNode(link));
			li.appendChild(a);
			ul.appendChild(li);
		}
		if ('attributes' in this.currentElement) ul = this.apply_attributes(this.currentElement.attributes, ul);
		return ul;
	}

	signature() {
		/*{
			type: 'signature',
			description:'signature',
			required: optional boolean
		} */
		if (!this.has_content(this.currentElement)) return;
		let result = [];
		const canvas = document.createElement('canvas');
		canvas.id = 'signaturecanvas';
		if (this.currentElement.attributes && this.currentElement.attributes.required) canvas.setAttribute('data-required', 'required');
		result.push(canvas);
		//this tile does not process attributes, therefore they can be reassigned
		this.currentElement.description = LANG.GET('assemble.clear_signature');
		this.currentElement.attributes = {
			'type': 'button',
			'name': '',
			'onpointerdown': 'signaturePad.clear()'
		};
		result = result.concat(this.button());
		this.currentElement.attributes = {
			'type': 'file',
			'id': 'signature',
			'name': 'signature',
			'hidden': true
		};
		result = result.concat(this.input('file'));
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
		if (!this.has_content(this.currentElement)) return;
		const result = [];
		const stream = document.createElement('div');
		stream.id = getNextElementID();
		stream.classList.add('scanner');

		result.push(stream);

		const inputid = 'destination' in this.currentElement ? this.currentElement.destination : this.input('text');
		//attributes are processed already, therefore they can be reassigned
		this.currentElement.description = LANG.GET('assemble.scan_button');
		this.currentElement.attributes = {
			'onpointerdown': "assemble_helper.initialize_scanner('" + stream.id + "','" + inputid + "')"
		};
		return result.concat(this.button());
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
		if (!this.has_content(this.currentElement)) return;
		let result = [];
		const canvas = document.createElement('canvas');
		let disabled = true;
		canvas.id = getNextElementID();
		canvas.classList.add('imagecanvas');
		if (typeof this.currentElement.attributes.imageonly === 'object') {
			for (const [key, value] of Object.entries(this.currentElement.attributes.imageonly)) {
				canvas.style[key] = value;
			}
		}
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
				'onpointerdown': 'assemble_helper.exportCanvas("' + canvas.id + '", "' + this.currentElement.attributes.name + '")'
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
		const article = document.createElement('article');
		article.id = 'stlviewer_canvas';
		article.classList = 'stlviewer';
		return article;
	}

	trash() {
		// empty method but necessary to display the delete-area for composer or other future use
	}

	hr() {
		return (document.createElement('hr'));
	}

	collapsed() {
		// empty method but neccessary for styling reasons (icon)
		// multiple input fields within one section
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

var signaturecanvas = null;

function initialize_SignaturePad() {
	signaturecanvas = document.getElementById("signaturecanvas");
	window.signaturePad = new SignaturePad(signaturecanvas, {
		// It's Necessary to use an opaque color when saving image as JPEG;
		// this option can be omitted if only saving as PNG or SVG
		//backgroundColor: 'rgb(255, 255, 255)'
		penColor: "rgb(46, 52, 64)"
	});
	// On mobile devices it might make more sense to listen to orientation change,
	// rather than window resize events.
	window.onresize = resizeSignatureCanvas;
	resizeSignatureCanvas();
}
// Adjust canvas coordinate space taking into account pixel ratio,
// to make it look crisp on mobile devices.
// This also causes canvas to be cleared.
function resizeSignatureCanvas() {
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
function dataURLToBlob(dataURL) {
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
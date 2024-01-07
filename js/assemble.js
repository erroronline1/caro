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
	content:[
		[card
			{element0},
			{element1}
		],
	]

	elements are assembled by default but input elements can be assigned common html attributes
	names are set according to description.

	if any element has an article-attribute consisting of attribute-value pairs the article will have set the attributes
	*/
	constructor(setup) {
		this.setup = setup;
		this.content = setup.content;
		this.form = setup.form;
		this.multiplearticles = [];
		this.multiplearticleID = null;
		this.section = null;
		this.imageQrCode = [];
		this.imageBarCode = [];
		this.imageUrl = [];
		this.articleAttributes = [];
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

		this.assembledTiles = new Set();
		this.processContent();

		if (!nextSibling) {
			this.section.append(...this.assembledTiles);
			document.getElementById('main').insertAdjacentElement('beforeend', this.section);
		} else {
			const tiles = Array.from(this.assembledTiles);
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
		let collapse;
		this.content.forEach(tile => {
			this.articleAttributes = [];
			this.multipletiles = new Set();
			for (let i = 0; i < tile.length; i++) {
				this.tile = tile[i];
				collapse = tile[i].collapse || false;
				if (!collapse || i === 0) this.elements = new Set();
				this.description();
				this[tile[i].type]();
				if ('article' in tile[i]) this.articleAttributes = tile[i].article;
				if ((tile[i].type === 'hiddeninput' && !this.setup.visible) || tile[i].type === 'datalist' || tile[i].type === 'hr' || (collapse && i < tile.length - 1)) continue;
				if (tile.length < 2 || collapse) {
					this.assembledTiles.add(this.single(tile[i]));
					continue;
				}
				this.multipletiles.add(this.single(tile[i], true))
			}
			if (this.multipletiles.size) this.assembledTiles.add(this.multiple());
		});
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
			section.scrollBy(-300, 0)
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
			section.scrollBy(300, 0)
		});
		indicators.appendChild(toright);
		article.appendChild(indicators);
		for (const [attribute, value] of Object.entries(this.articleAttributes)) {
			article.setAttribute(attribute, value);
		}
		return article;
	}

	description() {
		if ([undefined, null, false].includes(this.tile.description) || this.tile.collapse) return;
		const header = document.createElement('header');
		header.appendChild(document.createTextNode(this.tile.description));
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

	text() {
		/* {
			type: 'text',
			description: 'very informative',
			content: 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.'
		}*/

		const content = this.tile.content.matchAll(/(.*?)(?:\n|\\n|<br \/>|$)/gm);
		for (const part of content) {
			this.elements.add(document.createTextNode(part[1]));
			this.elements.add(document.createElement('br'));
		}
	}

	input(type) {
		/*{
			type: 'textinput',
			description: 'text input',
			attributes: {
				placeholder: 'text input'
			}
		}*/
		let input = document.createElement('input');
		let label;
		input.type = type;
		input.id = this.tile.attributes && this.tile.attributes.id ? this.tile.attributes.id : getNextElementID();
		input.autocomplete = (this.tile.attributes && this.tile.attributes.type) === 'password' ? 'one-time-code' : 'off';
		if (this.tile.description) input.name = this.tile.description;
		input.classList.add('input-field');
		if (this.tile.attributes !== undefined) {
			if (this.tile.attributes.placeholder) {
				label = document.createElement('label');
				label.htmlFor = input.id;
				label.appendChild(document.createTextNode(this.tile.attributes.placeholder));
				this.tile.attributes.placeholder = ' ';
				label.classList.add('input-label');
			}
			input = this.apply_attributes(this.tile.attributes, input);
		}
		this.elements.add(input);
		if (label) this.elements.add(label);
		return input.id;
	}
	textinput() {
		this.input('text');
	}
	numberinput() {
		this.input('number');
	}
	dateinput() {
		this.input('date');
	}
	searchinput() {
		this.input('search');
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
		if (this.tile.description) button.appendChild(document.createTextNode(this.tile.description));
		if (this.tile.attributes && 'value' in this.tile.attributes) {
			button.appendChild(document.createTextNode(this.tile.attributes.value));
			delete this.tile.attributes.value;
		}
		if (this.tile.attributes !== undefined) button = this.apply_attributes(this.tile.attributes, button);
		this.elements.add(button);
		return button.id;
	}
	deletebutton() { // to style it properly by adding data-type to article container
		this.button();
	}
	submitbutton() {
		this.button();
	}

	hiddeninput() {
		/*{
			type: 'hiddeninput',
			description: 'value of pi',
			attributes: {value: '3.14'}
			}
		}*/
		let input = document.createElement('input');
		input.type = 'hidden';
		input.name = this.tile.description;
		input.id = getNextElementID();
		input.value = this.tile.value;
		if (this.tile.attributes !== undefined) input = this.apply_attributes(this.tile.attributes, input);
		if (!this.setup.visible) this.elements.add(input);
		else {
			const value = document.createTextNode(input.value);
			this.elements.add(value);
			this.elements.add(input);
		}
		return input.id;
	}
	datalist() {
		let datalist = document.createElement('datalist');
		let option;
		datalist.id = getNextElementID();
		if (this.tile.attributes !== undefined) datalist = this.apply_attributes(this.tile.attributes, datalist);
		this.tile.content.forEach(key => {
			option = document.createElement('option');
			option.value = key;
			datalist.appendChild(option);
		});
		this.assembledTiles.add(datalist);
		return datalist.id;
	}

	file() {
		/*{
			type: 'file',
			description: 'file upload',
			attributes: {
				multiple: true
			}
		}*/
		let input = document.createElement('input'),
			label = document.createElement('button'),
			button = document.createElement('button');
		input.type = 'file';
		input.id = getNextElementID();
		input.name = this.tile.description;
		if (this.tile.attributes !== undefined) input = this.apply_attributes(this.tile.attributes, input);
		if ('attributes' in this.tile && 'multiple' in this.tile.attributes) input.onchange = function () {
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
		label.appendChild(document.createTextNode(('attributes' in this.tile && 'multiple' in this.tile.attributes) ? LANG.GET('assemble.files_choose') : LANG.GET('assemble.file_choose')));

		button.onpointerdown = new Function("let e=document.getElementById('" + input.id + "'); e.value=''; e.dispatchEvent(new Event('change'));");
		button.appendChild(document.createTextNode('Reset'));
		button.setAttribute('data-type', 'reset');
		button.classList.add('inlinebutton');

		this.elements.add(input)
		this.elements.add(label);
		this.elements.add(button);
	}

	photo() {
		/*{
			type: 'photo',
			description: 'photo upload',
			attributes: {
				name: 'photo'
			}
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
			nextPhoto.childNodes[4].onpointerdown = new Function('this.parentNode.remove();');
			// add button
			nextPhoto.childNodes[5].onpointerdown = cloneNode;
			// reset button
			nextPhoto.childNodes[6].onpointerdown = new Function("let e=document.getElementById('" + nextPhoto.childNodes[1].id + "'); e.value=''; e.dispatchEvent(new Event('change'));");

			this.parentNode.after(nextPhoto);
		}

		input.type = 'file';
		input.id = getNextElementID();
		input.name = this.tile.description + '[]';
		input.accept = 'image/*';
		input.capture = true;
		input.onchange = changeEvent;
		if (this.tile.attributes !== undefined) input = this.apply_attributes(this.tile.attributes, input);
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

		this.elements.add(input);
		this.elements.add(img);
		this.elements.add(label);
		this.elements.add(addbutton);
		this.elements.add(resetbutton);
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
		let select = document.createElement('select');
		const groups = {};
		select.name = select.title = this.tile.description;
		if (this.tile.attributes !== undefined) select = this.apply_attributes(this.tile.attributes, select);

		for (const [key, element] of Object.entries(this.tile.content)) {
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
		this.elements.add(select)
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
		let textarea = document.createElement('textarea');
		textarea.name = this.tile.description;
		textarea.autocomplete = 'off';
		if (this.tile.attributes !== undefined) textarea = this.apply_attributes(this.tile.attributes, textarea);
		if (this.tile.attributes && 'value' in this.tile.attributes) textarea.appendChild(document.createTextNode(this.tile.attributes.value));
		this.elements.add(textarea);
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
		for (const [checkbox, attributes] of Object.entries(this.tile.content)) {
			let label = document.createElement('label'),
				input = document.createElement('input');
			if (radio) {
				input.type = 'radio';
				input.name = this.tile.description;
				input.value = checkbox;
			} else {
				input.type = 'checkbox';
				input.name = checkbox;
			}

			label.classList.add('check');
			input = this.apply_attributes(attributes, input);
			label.appendChild(input);
			label.appendChild(document.createTextNode(checkbox));
			this.elements.add(label);
		}
	}
	radio() {
		this.checkbox('radioinstead');
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
		const ul = document.createElement('ul');
		for (const [link, attributes] of Object.entries(this.tile.content)) {
			let li = document.createElement('li'),
				a = document.createElement('a');
			a = this.apply_attributes(attributes, a);
			if (!a.href) a.href = link;
			a.appendChild(document.createTextNode(link));
			li.appendChild(a);
			ul.appendChild(li);
		}
		this.elements.add(ul);
	}

	signature() {
		/*{
			type: 'signature',
			description:'signature',
			required: optional boolean
		} */
		const canvas = document.createElement('canvas');
		canvas.id = 'signaturecanvas';
		if (this.tile.attributes && this.tile.attributes.required) canvas.setAttribute('data-required', 'required');
		this.elements.add(canvas);
		//this tile does not process attributes, therefore they can be reassigned
		this.tile.description = LANG.GET('assemble.clear_signature');
		this.tile.attributes = {
			'type': 'button',
			'name': '',
			'onpointerdown': 'signaturePad.clear()'
		};
		this.button();
		this.tile.attributes = {
			'type': 'file',
			'id': 'signature',
			'name': 'signature',
			'hidden': true
		};
		this.input('file');
		this.signaturePad = true;
	}

	scanner() {
		/*{
			type: 'scanner',
			description:'access credentials' (e.g.),
			attributes:{type:'password'} // to override e.g. for logins
			destination: elementId // force output to other input, e.g. search
		} */
		const stream = document.createElement('div');
		stream.id = getNextElementID();
		stream.classList.add('scanner');

		this.elements.add(stream);

		const inputid = 'destination' in this.tile ? this.tile.destination : this.input('text');
		//attributes are processed already, therefore they can be reassigned
		this.tile.description = LANG.GET('assemble.scan_button');
		this.tile.attributes = {
			'onpointerdown': "assemble_helper.initialize_scanner('" + stream.id + "','" + inputid + "')"
		};
		this.button();
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
		const canvas = document.createElement('canvas');
		let disabled = true;
		canvas.id = getNextElementID();
		canvas.classList.add('imagecanvas');
		if (typeof this.tile.attributes.imageonly === 'object') {
			for (const [key, value] of Object.entries(this.tile.attributes.imageonly)) {
				canvas.style[key] = value;
			}
		}
		canvas.width = canvas.height = 1024;
		if (this.tile.attributes.qrcode) {
			this.imageQrCode.push({
				id: canvas.id,
				content: this.tile.attributes.qrcode
			});
			disabled = false;
		}
		if (this.tile.attributes.barcode) {
			this.imageBarCode.push({
				id: canvas.id,
				content: this.tile.attributes.barcode
			});
			disabled = false;
		}
		if (this.tile.attributes.url) {
			this.imageUrl.push({
				id: canvas.id,
				content: this.tile.attributes.url
			});
			disabled = false;
		}

		this.elements.add(canvas);

		if (!this.tile.attributes.imageonly) {
			//this tile does not process attributes, therefore they can be reassigned
			this.tile.attributes = {
				'type': 'button',
				'class': 'inlinebutton',
				'data-type': this.tile.type,
				'onpointerdown': 'assemble_helper.exportCanvas("' + canvas.id + '", "' + this.tile.attributes.name + '")'
			};
			if (disabled) this.tile.attributes.disabled = true;
			this.button();
		}
	}

	trash() {
		// empty method but necessary to display the delete-area for composer or other future use
	}

	hr() {
		this.assembledTiles.add(document.createElement('hr'));
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
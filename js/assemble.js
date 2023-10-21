/*
this module helps to assemble forms according to the passed simplified object notation.
*/
import QrScanner from '../libraries/qr-scanner.min.js';
import SignaturePad from '../libraries/signature_pad.umd.js';
import {
	_
} from '../libraries/erroronline1.js';

var ElementID = 0;

export function getNextElementID() {
	return 'elementID' + ++ElementID;
}

const events = ['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'];

export const assemble_helper = {
	getNextElementID: getNextElementID,
	initialize_qrScanner: function (videostream, resultTo) {
		const stream = document.getElementById(videostream);
		stream.classList.add('active');
		const scanner = new QrScanner(
			stream,
			result => {
				document.getElementById(resultTo).value = result.data;
				scanner.stop();
				scanner.destroy();
				stream.classList.remove('active');
			}, {
				highlightScanRegion: true
				/* your options or returnDetailedScanResult: true if you're not specifying any other options */
			},
		);
		scanner.start();
	}
}

const sectionScroller = (e) => {
	/* event handler for horizontal scrolling of multiple panels */
	setTimeout(() => {
		let indicator = document.getElementById(e.target.attributes.id.value + 'indicator');
		for (let panel = 0; panel < e.target.children.length; panel++) {
			try {
				if (panel == Math.floor(e.target.scrollLeft / e.target.clientWidth)) indicator.children[
					panel].firstChild.classList.add('articleactive');
				else indicator.children[panel].firstChild.classList.remove('articleactive');
			} catch {
				return;
			}
		}
	}, 500)
};

function prepareForm() {
	/* check non typical input fields for presence of required content */
	const signature = document.getElementById('signaturecanvas');
	if (signature) {
		if (signaturePad.isEmpty()) {
			signature.classList.add("alert");
			return false;
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
		*/
	constructor(setup) {
		this.setup = setup;
		this.content = setup.content;
		this.form = setup.form;
		this.multiplearticles = [];
		this.multiplearticleID = null;
		this.section = null;
	}

	initializeSection() {
		if (this.form) {
			this.section = document.createElement('form');
			this.section.method = 'post';
			this.section.enctype = 'multipart/form-data';
			this.section.onsubmit = () => {
				return prepareForm()
			};
			Object.keys(this.form).forEach(key => {
				if (events.includes(key)) {
					this.section[key] = new Function(this.form[key]);
				} else this.section.setAttribute(key, this.form[key]);
			});

			this.content.push([{
				type: 'submit',
				attributes: {
					value: 'absenden'
				}
			}]);
		} else this.section = document.createElement('div');

		this.assembledTiles = new Set();
		this.processContent();

		this.section.append(...this.assembledTiles);
		document.getElementById('main').insertAdjacentElement('beforeend', this.section);
		for (let i = 0; i < this.multiplearticles.length; i++) {
			document.getElementById(this.multiplearticles[i]).addEventListener('scroll', sectionScroller);
		}

		if (this.signaturePad) {
			initialize_SignaturePad();
		}
	}

	processContent() {
		let originalTileProperties; // composer changes these, so originals must be preserved
		this.content.forEach(tile => {
			this.multipletiles = new Set();
			for (let i = 0; i < tile.length; i++) {
				this.elements = new Set();
				this.tile = originalTileProperties = tile[i];
				this.description();
				this[tile[i].type]();
				if (tile.length < 2) {
					this.assembledTiles.add(this.single(originalTileProperties));
					continue;
				}
				this.multipletiles.add(this.single(originalTileProperties, true))
			}
			if (this.multipletiles.size) this.assembledTiles.add(this.multiple());
		});
	}
	single(tileProperties, oneOfFew = false) { // parameters are required by composer method
		const article = document.createElement('article');
		article.setAttribute('data-type', tileProperties.type);
		article.append(...this.elements);
		return article;
	}
	multiple() {
		const article = document.createElement('article'),
			section = document.createElement('section'),
			indicators = document.createElement('div');
		this.multiplearticleID = getNextElementID();
		this.multiplearticles.push(this.multiplearticleID);
		section.classList = 'inset';
		section.id = this.multiplearticleID;
		section.append(...this.multipletiles);
		article.appendChild(section);
		indicators.classList = 'sectionindicator';
		indicators.id = this.multiplearticleID + 'indicator';
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
		article.appendChild(indicators);
		return article;
	}

	description() {
		if ([undefined, null, false].includes(this.tile.description)) return
		const header = document.createElement('header');
		header.appendChild(document.createTextNode(this.tile.description));
		this.elements.add(header);
	};

	text() {
		/* {
			type: 'text',
			description: 'very informative',
			content: 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.'
		}*/
		const content = document.createTextNode(this.tile.content);
		this.elements.add(content);
	}

	input(type) {
		/*{
			type: 'textinput',
			description: 'text input',
			attributes: {
				placeholder: 'text input'
			}
		}*/
		const input = document.createElement('input');
		let label;
		input.type = type;
		input.id = getNextElementID();
		input.autocomplete = 'off';
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
			Object.keys(this.tile.attributes).forEach(key => {
				if (events.includes(key)) {
					input[key] = new Function(this.tile.attributes[key]);
				} else input.setAttribute(key, this.tile.attributes[key]);
			});
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
	submit() {
		this.input('submit');
	}
	button() {
		this.input('button');
	}
	searchinput() {
		this.input('search');
	}

	file() {
		/*{
			type: 'file',
			description: 'file upload',
			attributes: {
				multiple: true
			}
		}*/
		const input = document.createElement('input'),
			label = document.createElement('label');
		input.type = 'file';
		input.id = getNextElementID();
		input.name = this.tile.description;
		if (this.tile.attributes !== undefined) Object.keys(this.tile.attributes).forEach(key => {
			input[key] = this.tile.attributes[key];
		});
		input.onchange = function () {
			this.nextSibling.innerHTML = this.files.length ? Array.from(this.files).map(x => x.name).join(
				', ') + ' oder ändern...' : 'Datei auswählen...'
		}
		label.htmlFor = input.id;
		label.appendChild(document.createTextNode('Datei auswählen...'));

		this.elements.add(input)
		this.elements.add(label);
	}

	photo() {
		/*{
			type: 'photo',
			description: 'photo upload',
			attributes: {
				name: 'photo'
			}
		}*/
		const input = document.createElement('input'),
			label = document.createElement('label'),
			img = document.createElement('img');

		function changeEvent() {
			this.nextSibling.innerHTML = this.files.length ? Array.from(this.files).map(x => x.name).join(', ') + ' oder ändern...' : 'Photo aufnehmen...';
			this.nextSibling.nextSibling.src = URL.createObjectURL(this.files[0]);
			if (this.files.length) {
				const nextPhoto = this.parentNode.cloneNode(true);
				nextPhoto.childNodes[1].id = nextPhoto.childNodes[2].htmlFor = getNextElementID();
				nextPhoto.childNodes[1].files = null;
				nextPhoto.childNodes[1].onchange = changeEvent;
				nextPhoto.childNodes[2].innerHTML = 'Photo aufnehmen...';
				nextPhoto.childNodes[3].src = '';
				this.parentNode.after(nextPhoto);
			}
		}

		input.type = 'file';
		input.id = getNextElementID();
		input.name = this.tile.description + '[]';
		input.accept = 'image/*';
		input.capture = true;
		input.onchange = changeEvent;
		if (this.tile.attributes !== undefined) Object.keys(this.tile.attributes).forEach(key => {
			input[key] = this.tile.attributes[key];
		});
		label.htmlFor = input.id;
		label.appendChild(document.createTextNode('Photo aufnehmen...'));
		img.classList.add('photoupload');
		this.elements.add(input);
		this.elements.add(label);
		this.elements.add(img);
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
		const select = document.createElement('select');
		select.name = select.title = this.tile.description;
		if (this.tile.attributes !== undefined) Object.keys(this.tile.attributes).forEach(key => {
			if (events.includes(key)) {
				select[key] = new Function(this.tile.attributes[key]);
			} else select[key] = this.tile.attributes[key];
		});
		Object.keys(this.tile.content).forEach(key => {
			let option = document.createElement('option');
			Object.keys(this.tile.content[key]).forEach(attribute => {
				if (events.includes(attribute)) {
					option[attribute] = new Function(this.tile.content[key][attribute]);
				} else option[attribute] = this.tile.content[key][attribute];
			});
			option.appendChild(document.createTextNode(key));
			select.appendChild(option)
		});
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
		const textarea = document.createElement('textarea');
		textarea.name = this.tile.description;
		textarea.autocomplete = 'off';
		if (this.tile.attributes !== undefined) Object.keys(this.tile.attributes).forEach(key => {
			if (events.includes(key)) {
				textarea[key] = new Function(this.tile.attributes[key]);
			} else if (key !== 'value') textarea[key] = this.tile.attributes[key];
		});
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
		Object.keys(this.tile.content).forEach(checkbox => {
			let label = document.createElement('label'),
				input = document.createElement('input'),
				span = document.createElement('span');
			if (radio) {
				input.type = 'radio';
				input.name = this.tile.description;
				input.value = checkbox;
			} else {
				input.type = 'checkbox';
				input.name = checkbox;
			}

			label.classList = 'custominput';
			span.classList = 'checkmark';
			label.appendChild(document.createTextNode(checkbox));
			Object.keys(this.tile.content[checkbox]).forEach(attribute => {
				if (events.includes(attribute)) {
					input[attribute] = new Function(this.tile.content[checkbox][attribute]);
				} else input[attribute] = this.tile.content[checkbox][attribute];
			});
			label.appendChild(input);
			label.appendChild(span);
			this.elements.add(label);
		});
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
		Object.keys(this.tile.content).forEach(link => {
			let li = document.createElement('li'),
				a = document.createElement('a');
			Object.keys(this.tile.content[link]).forEach(attribute => {
				if (events.includes(attribute)) {
					a[attribute] = new Function(this.tile.content[link][attribute]);
				} else a[attribute] = this.tile.content[link][attribute];
			})
			if (!a.href) a.href = link;
			a.appendChild(document.createTextNode(link));
			li.appendChild(a);
			ul.appendChild(li);
		});
		this.elements.add(ul);
	}

	signature() {
		/*{
			type: 'signature',
			description:'signature'
		} */
		const canvas = document.createElement('canvas');
		canvas.id = 'signaturecanvas';
		this.elements.add(canvas);
		//this tile does not process attributes, therefore they can be reassigned
		this.tile.attributes = {
			'name': '',
			'value': 'Unterschrift löschen',
			'onpointerdown': 'signaturePad.clear()'
		};
		this.input('button');
		this.tile.attributes = {
			'type': 'file',
			'id': 'signature',
			'name': 'signature',
			'hidden': true
		};
		this.input('file');
		this.signaturePad = true;
	}

	qr() {
		/*{
			type: 'qr',
			description:'access credentials' (e.g.),
			attributes:{type:'password'} // to override e.g. for logins 
		} */
		const stream = document.createElement('video');
		stream.id = 'qrscanner';

		this.elements.add(stream);

		const inputid = this.input('text');
		//attributes are processed already, therefore they can be reassigned
		this.tile.attributes = {
			'name': '',
			'value': 'start scan',
			'onpointerdown': "assemble_helper.initialize_qrScanner('" + stream.id + "','" + inputid + "')"
		};
		this.input('button');
	}

	trash() {
		// empty method but necessary to display the delete-area for composer or other future use
	}
}

var canvas = null;

function initialize_SignaturePad() {
	canvas = document.getElementById("signaturecanvas");
	window.signaturePad = new SignaturePad(canvas, {
		// It's Necessary to use an opaque color when saving image as JPEG;
		// this option can be omitted if only saving as PNG or SVG
		//backgroundColor: 'rgb(255, 255, 255)'
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
	canvas.width = canvas.offsetWidth * ratio;
	canvas.height = canvas.offsetHeight * ratio;
	canvas.getContext("2d").scale(ratio, ratio);
	// This library does not listen for canvas changes, so after the canvas is automatically
	// cleared by the browser, SignaturePad#isEmpty might still return false, even though the
	// canvas looks empty, because the internal data of this library wasn't cleared. To make sure
	// that the state of this library is consistent with visual state of the canvas, you
	// have to clear it manually.
	signaturePad.clear();
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
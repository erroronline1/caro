import QrScanner from '../libraries/qr-scanner.min.js';
import SignaturePad from '../libraries/signature_pad.umd.js';
import {
	_
} from '../libraries/erroronline1.js';

export var multiplecontainerID = 0;
export var ElementID = 0;

export function getNextContainerID() {
	return 'containerID' + ++multiplecontainerID;
}

export function getNextElementID() {
	return 'elementID' + ++ElementID;
}

const events = ['onclick', 'onmouseover', 'onmouseout', 'onchange', 'onpointerdown'];

export const scroller = (e) => {
	/* event handler for horizontal scrolling of multiple panels */
	setTimeout(() => {
		let indicator = document.getElementById(e.target.attributes.id.value + 'indicator');
		for (let panel = 0; panel < e.target.children.length; panel++) {
			if (panel == Math.floor(e.target.scrollLeft / e.target.clientWidth)) indicator.children[
				panel].firstChild.classList.add('sectionactive');
			else indicator.children[panel].firstChild.classList.remove('sectionactive');
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
		let container = new DataTransfer();
		container.items.add(file);
		document.getElementById('signature').files = container.files;
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
		this.multipleContainers = [];
		this.multiplecontainerID = null;
		this.container = null;
	}

	initializeContainer() {
		if (this.form) {
			this.container = document.createElement('form');
			this.container.method = 'post';
			this.container.enctype = 'multipart/form-data';
			this.container.onsubmit = () => {
				return prepareForm()
			};
			Object.keys(this.form).forEach(key => {
				if (events.includes(key)) {
					this.container[key] = new Function(this.form[key]);
				} else this.container.setAttribute(key, this.form[key]);
			});

			this.content.push([{
				type: 'submit',
				attributes: {
					value: 'absenden'
				}
			}]);
		} else this.container = document.createElement('div');

		this.assembledTiles = new Set();
		this.processContent();

		this.container.append(...this.assembledTiles);
		document.getElementById('main').insertAdjacentElement('beforeend', this.container);
		for (let i = 0; i < this.multipleContainers.length; i++) {
			document.getElementById(this.multipleContainers[i]).addEventListener('scroll', scroller);
		}

		if (this.signaturePad) {
			initialize_SignaturePad();
		}
		return {
			id: this.container.id,
			content: this.content
		};
	}

	processContent() {
		this.content.forEach(tile => {
			this.multipletiles = new Set();
			for (let i = 0; i < tile.length; i++) {
				this.elements = new Set();
				this.tile = tile[i];
				this[tile[i].type]();
				if (tile.length < 2) {
					this.assembledTiles.add(this.single());
					continue;
				}
				this.multipletiles.add(this.single())
			}
			if (this.multipletiles.size) this.assembledTiles.add(this.multiple());
		});
	}
	single() {
		const section = document.createElement('section');
		section.setAttribute('data-type', this.tile.type);

		if ([undefined, null, false].indexOf(this.tile.description) > -1) {
			section.append(...this.elements);
			return section;
		}
		const fieldset = this.fieldset();
		fieldset.append(...this.elements);
		section.appendChild(fieldset);

		this.composer_trash(section);

		return section;
	}
	multiple(classList = null) {
		const section = document.createElement('section'),
			container = document.createElement('div'),
			indicators = document.createElement('div');
		this.multiplecontainerID = getNextContainerID();
		this.multipleContainers.push(this.multiplecontainerID);
		if (classList) section.classList = classList;
		container.classList = 'container inset';
		container.id = this.multiplecontainerID;
		container.append(...this.multipletiles);
		section.appendChild(container);
		indicators.classList = 'containerindicator';
		indicators.id = this.multiplecontainerID + 'indicator';
		for (let i = 0; i < this.multipletiles.size; i++) {
			let indicator = document.createElementNS('http://www.w3.org/2000/svg', 'svg'),
				circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
			indicator.classList = 'sectionindicator';
			indicator.setAttributeNS(null, 'viewbox', '0 0 10 10');
			circle.setAttributeNS(null, 'cx', '5');
			circle.setAttributeNS(null, 'cy', '5');
			circle.setAttributeNS(null, 'r', '4');
			indicator.appendChild(circle);
			indicators.appendChild(indicator);
		}
		section.appendChild(indicators);
		return section;
	}

	fieldset() {
		const fieldset = document.createElement('fieldset'),
			legend = document.createElement('legend'),
			title = document.createTextNode(this.tile.description);
		legend.appendChild(title);
		fieldset.appendChild(legend);
		return fieldset
	}
	text() {
		/* {
			type: 'text',
			collapsed: true,
			content: 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.'
		}*/
		const div = document.createElement('div'),
			content = document.createTextNode(this.tile.content);
		div.appendChild(content);
		if (this.tile.collapsed) {
			div.classList = 'collapsed';
			div.onpointerdown = function () {
				this.classList.toggle('expanded')
			};
		}
		this.elements.add(div);
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
		input.type = type;
		input.id = getNextElementID();
		if (this.tile.description) input.name = this.tile.description;

		if (this.tile.attributes !== undefined) Object.keys(this.tile.attributes).forEach(key => {
			if (events.includes(key)) {
				input[key] = new Function(this.tile.attributes[key]);
			} else input.setAttribute(key, this.tile.attributes[key]);
		});
		this.elements.add(input);
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
			label = document.createElement('label');
		input.type = 'file';
		input.id = getNextElementID();
		input.name = this.tile.description;
		input.accept = 'image/*';
		input.capture = true;
		input.onchange = function () {
			this.nextSibling.innerHTML = this.files.length ? Array.from(this.files).map(x => x.name).join(
				', ') + ' oder ändern...' : 'Photo aufnehmen...'
		};
		if (this.tile.attributes !== undefined) Object.keys(this.tile.attributes).forEach(key => {
			input[key] = this.tile.attributes[key];
		});
		label.htmlFor = input.id;
		label.appendChild(document.createTextNode('Photo aufnehmen...'));
		this.elements.add(input)
		this.elements.add(label);
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
		select.name = this.tile.description;
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
				span = document.createElement('span'),
				execute;
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
		const div = document.createElement('div'),
			ul = document.createElement('ul');
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
		div.appendChild(ul)
		this.elements.add(div);
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
			description:'access credentials' (e.g.)
		} */
		const stream = document.createElement('video');
		stream.id = 'qrscanner';

		this.elements.add(stream);

		const inputid = this.input('text');
		//attributes are processed already, therefore they can be reassigned
		this.tile.attributes = {
			'name': '',
			'value': 'start scan',
			'onpointerdown': "initialize_qrScanner('" + stream.id + "','" + inputid + "')"
		};
		this.input('button');
	}

	trash() {
		// empty method but necessary to display the delete-area
	}
	composer_trash(section) {
		// empty here, overwritten by extending class. don't know how to implement this cleaner. 
	}
}


var canvas = null,
	signaturePad = null;

function initialize_SignaturePad() {
	canvas = document.getElementById("signaturecanvas");
	signaturePad = new SignaturePad(canvas, {
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

function initialize_qrScanner(videostream, resultTo) {
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
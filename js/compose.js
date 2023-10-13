import {
	multiplecontainerID,
	ElementID,
	getNextContainerID,
	getNextElementID,
	Assemble
} from './assemble.js';
import {
	_
} from '../libraries/erroronline1.js';

var newFormElements = [];

const assembleNewElementCallback = (e) => {
	let sibling = e.target.previousElementSibling,
		property, value, element = {};
	do {
		if (sibling.type === 'button') {
			sibling = sibling.previousElementSibling;
			continue;
		}

		if (sibling.localName === 'label') {
			// collapsed text blocks
			if (sibling.firstElementChild.name == 'collapsed' && sibling.firstElementChild.checked) element['collapsed'] = true
		} else {
			element['type'] = sibling.name.match(/(_(.+)-)/)[2];
			property = sibling.name.match(/(-(.+)$)/)[2];
			value = sibling.value;
			if (['links','checkbox', 'radio','select'].includes(element['type'])) {
				if (property === 'description')
					if (value) element[property] = value;
					else return;
				if (property === 'content' && value) {
					if (element.content === undefined) element.content = {};
					element.content[value] = {};
				}
			} else {
				if (property === 'attributes' && value.length) try {
					value = JSON.parse(value);
				} catch {
					return
				};
				if (value) element[property] = value;
			}
		}
		console.log(element);
		sibling = sibling.previousElementSibling;
	} while (sibling !== undefined && sibling != null && sibling.localName !== 'legend');

	if (element.content) {
		// this is really bad. reverses the object order. this is not supposed to be done, but whatever. 
		let contentClone = {},
			keys = Object.keys(element.content);
		for (let i = keys.length - 1; i > -1; i--) {
			contentClone[keys[i]] = element.content[keys[i]];
		}
		element.content = contentClone;
	}

	if (Object.keys(element).length > 1) newFormElements.push(new Compose({
		"draggable": true,
		"content": [
			[element]
		]
	}));
}

export function assembleNewForm() {
	const order = [],
		nodes = document.getElementById('main').childNodes,
		form = {
			"form": {},
			"content": []
		};
	// set dragged/dropped order of elements 
	for (let i = 0; i < nodes.length; i++) {
		if (nodes[i].id) order.push(nodes[i].id);
	}
	// reorder array of new elements (settings temporary saved during creation)
	newFormElements = newFormElements.sort((a, b) => {
		if (order.indexOf(a.id) <= order.indexOf(b.id)) return -1;
		return 1;
	});
	// create json format
	newFormElements.forEach(element => {
		form.content.push(element.content[0])
	});
	console.log(JSON.stringify(form));
}

export class Compose extends Assemble {
	constructor(setup) {
		super(setup);
		this.createButtons = [];
		this.createDraggable = this.setup.draggable;

		this.initializeContainer();
		if (this.createDraggable) {
			this.container.id = getNextElementID();
			_.dragNdrop.add2DragCollection(this.container);
		}
		if (this.createButtons.length) {
			for (let i = 0; i < this.createButtons.length; i++) {
				document.getElementById(this.createButtons[i]).addEventListener('pointerdown', assembleNewElementCallback);
			}
		}
	}


	compose_text() {
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_text-description",
				"placeholder": "add information description"
			}
		};
		this.textinput();
		this.tile = {
			"type": "textarea",
			"description": "compose_text-content",
			"attributes": {
				"name:": "compose_text-content",
				"placeholder": "add information text",
				"rows": 5
			}
		};
		this.textarea();
		this.tile = {
			"type": "checkbox",
			"content": {
				"collapsed": {}
			}
		}
		this.checkbox();
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": "create an informative text block",
			"type": "text",
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add text"
			}
		};
		this.createButtons.push(this.input('button'));
	}
	compose_input(type, description) {
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_" + type.replace(/\s+/g, '') + "-description",
				"placeholder": "add description"
			}
		};
		this.textinput();
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_" + type.replace(/\s+/g, '') + "-attributes",
				"placeholder": "add advanced attributes (json)"
			}
		};
		this.textinput();
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": description,
			"type": type.replace(/\s+/g, ''),
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add " + type
			}
		};
		this.createButtons.push(this.input('button'));
	}
	compose_textinput() {
		this.compose_input('text input', 'create a single line text input');
	}
	compose_numberinput() {
		this.compose_input('number input', 'create a number input');
	}
	compose_dateinput() {
		this.compose_input('date input', 'create a date input');
	}
	compose_submit() {
		this.compose_input('submit');
	}
	compose_file() {
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_file-description",
				"placeholder": "add description"
			}
		};
		this.textinput();
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": "create a file upload",
			"type": "file",
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add file upload"
			}
		};
		this.createButtons.push(this.input('button'));
	}
	compose_photo() {
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_photo-description",
				"placeholder": "add description"
			}
		};
		this.textinput();
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": "create a photo upload",
			"type": "photo",
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add photo upload"
			}
		};
		this.createButtons.push(this.input('button'));
	}
	compose_select() {
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_select-description",
				"placeholder": "add description"
			}
		};
		this.textinput();
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_select-content",
				"placeholder": "add item"
			}
		};
		this.textinput();
		this.tile = {
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add selection item",
				"onpointerdown": "let clone=this.previousElementSibling.cloneNode(); clone.id=getNextElementID(); this.parentNode.insertBefore(clone, this)"
			}
		};
		this.input('button');
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": "create a dropdown block",
			"type": "checkbox",
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add dropdown block"
			}
		};
		this.createButtons.push(this.input('button'));
	}
	compose_textarea() {
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_textarea-description",
				"placeholder": "add description"
			}
		};
		this.textinput();
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_textarea-attributes",
				"placeholder": "add advanced attributes (json)"
			}
		};
		this.textinput();
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": "create a multi line text input",
			"type": "textarea",
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add multi line text input"
			}
		};
		this.createButtons.push(this.input('button'));
	}
	compose_checkbox() {
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_checkbox-description",
				"placeholder": "add description"
			}
		};
		this.textinput();
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_checkbox-content",
				"placeholder": "add item"
			}
		};
		this.textinput();
		this.tile = {
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add selection item",
				"onpointerdown": "let clone=this.previousElementSibling.cloneNode(); clone.id=getNextElementID(); this.parentNode.insertBefore(clone, this)"
			}
		};
		this.input('button');
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": "create a multiple selection block",
			"type": "checkbox",
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add multiple selection block"
			}
		};
		this.createButtons.push(this.input('button'));
	}
	compose_radio() {
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_radio-description",
				"placeholder": "add description"
			}
		};
		this.textinput();
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_radio-content",
				"placeholder": "add item"
			}
		};
		this.textinput();
		this.tile = {
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add selection item",
				"onpointerdown": "let clone=this.previousElementSibling.cloneNode(); clone.id=getNextElementID(); this.parentNode.insertBefore(clone, this)"
			}
		};
		this.input('button');
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": "create a single selection block",
			"type": "radio",
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add single selection block"
			}
		};
		this.createButtons.push(this.input('button'));
	}
	compose_links() {
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_links-description",
				"placeholder": "add description"
			}
		};
		this.textinput();
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_links-content",
				"placeholder": "add link"
			}
		};
		this.textinput();
		this.tile = {
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add link",
				"onpointerdown": "let clone=this.previousElementSibling.cloneNode(); clone.id=getNextElementID(); this.parentNode.insertBefore(clone, this)"
			}
		};
		this.input('button');
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": "create a link block",
			"type": "links",
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add link block"
			}
		};
		this.createButtons.push(this.input('button'));
	}
	compose_signature() {
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_signature-description",
				"placeholder": "add description"
			}
		};
		this.textinput();
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": "create a signature pad",
			"type": "signature",
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add signature pad"
			}
		};
		this.createButtons.push(this.input('button'));
	}
	compose_qr() {
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_qr-description",
				"placeholder": "add description"
			}
		};
		this.textinput();
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": "create a qr-scanner field",
			"type": "qr",
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add qr-scanner"
			}
		};
		this.createButtons.push(this.input('button'));
	}
}
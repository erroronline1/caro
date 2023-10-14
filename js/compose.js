import {
	getNextElementID,
	Assemble
} from './assemble.js';
import {
	_
} from '../libraries/erroronline1.js';

export var newFormElements = [];
const cloneItems = "for (let i = 0; i < 2; i++){let clone = this.previousElementSibling.previousElementSibling.cloneNode(); clone.value=''; clone.id = getNextElementID(); this.parentNode.insertBefore(clone, this);}";

const assembleNewElementCallback = (e) => {
	let sibling = e.target.previousElementSibling,
		property, value, element = {},
		attributes = {};
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
			if (['links', 'checkbox', 'radio', 'select'].includes(element['type'])) {
				if (property === 'description')
					if (value) element[property] = value;
					else return;
				if (property === 'attributes' && value) {
					try {
						attributes = JSON.parse(value);
					} catch (err) {
						return
					};
					sibling = sibling.previousElementSibling;
					continue;
				}
				if (property === 'content' && value) {
					if (element.content === undefined) element.content = {};
					element.content[value] = attributes;
				}
			} else {
				if (property === 'attributes' && value.length) try {
					value = JSON.parse(value);
				} catch {
					return;
				};
				if (value) element[property] = value;
			}
		}
		sibling = sibling.previousElementSibling;
	} while (sibling !== undefined && sibling != null && sibling.localName !== 'legend');

	if (typeof element.content === 'object') {
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


export const dragNdrop = {
	allowDrop: function (evnt) {
		evnt.preventDefault();
	},
	drag: function (evnt) {
		evnt.dataTransfer.setData("text", evnt.currentTarget.id)
	},
	drop_insertbefore: function (evnt, that) {
		evnt.preventDefault();
		const data = evnt.dataTransfer.getData("text");
		document.getElementById(data).parentNode.insertBefore(document.getElementById(data), that);
	},
	drop_delete: function (evnt) {
		const data = evnt.dataTransfer.getData("text");
		for (let i = 0; i < newFormElements.length; i++) {
			if (newFormElements[i].id === data) {
				newFormElements.splice(i, 1);
				break;
			}
		}
		document.getElementById(data).remove();
	}
};

export class Compose extends Assemble {
	constructor(setup) {
		super(setup);
		this.createButtons = [];
		this.createDraggable = this.setup.draggable;

		this.initializeContainer();
		if (this.createDraggable) {
			this.container.id = getNextElementID();
			this.container.setAttribute('draggable', 'true');
			this.container.setAttribute('ondragstart', 'dragNdrop.drag(event)');
			this.container.setAttribute('ondragover', 'dragNdrop.allowDrop(event)');
			this.container.setAttribute('ondrop', 'dragNdrop.drop_insertbefore(event,this)');
		}
		if (this.createButtons.length) {
			for (let i = 0; i < this.createButtons.length; i++) {
				document.getElementById(this.createButtons[i]).addEventListener('pointerdown', assembleNewElementCallback);
			}
		}
		return {
			id: this.container.id,
			content: this.content
		};

	}

	composer_add_trash(section) {
		if (this.tile.type === 'trash') {
			section.setAttribute('ondragstart', 'dragNdrop.drag(event)');
			section.setAttribute('ondragover', 'dragNdrop.allowDrop(event)');
			section.setAttribute('ondrop', 'dragNdrop.drop_delete(event)');
			section.classList.add('inset');
		}
		return section;
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

	compose_input(type) {
		/* type{type, description, addblock} */

		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_" + type.type + "-description",
				"placeholder": "add description"
			}
		};
		this.textinput();
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_" + type.type + "-attributes",
				"placeholder": "add advanced attributes {json}"
			}
		};
		this.textinput();
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": type.description,
			"type": type.type,
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add " + type.addblock
			}
		};
		this.createButtons.push(this.input('button'));
	}
	compose_textinput() {
		this.compose_input({
			type: 'textinput',
			description: 'create a single line text input',
			addblock: 'text input'
		});
	}
	compose_numberinput() {
		this.compose_input({
			type: 'numberinput',
			description: 'create a number input',
			addblock: 'number input'
		});
	}
	compose_dateinput() {
		this.compose_input({
			type: 'dateinput',
			description: 'create a date input',
			addblock: 'date input'
		});
	}
	compose_submit() {
		this.compose_input({
			type: 'submit'
		});
	}
	compose_textarea() {
		this.compose_input({
			type: 'textarea',
			description: 'create a multi line text input',
			addblock: 'multiline text input'
		});

	}

	compose_multilist(type) {
		/* type{type, description, additem, addblock} */
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_" + type.type + "-description",
				"placeholder": "add description"
			}
		};
		this.textinput();
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_" + type.type + "-content",
				"placeholder": "add item"
			}
		};
		this.textinput();
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_" + type.type + "-attributes",
				"placeholder": "add advanced attributes {json}"
			}
		};
		this.textinput();
		this.tile = {
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add " + type.additem + " item",
				"onpointerdown": cloneItems
			}
		};
		this.input('button');
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": type.description,
			"type": type.type,
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add " + type.addblock + " block"
			}
		};
		this.createButtons.push(this.input('button'));

	}
	compose_select() {
		this.compose_multilist({
			type: 'select',
			description: 'create a dropdown block',
			additem: 'selection',
			addblock: 'dropdown'
		});
	}
	compose_checkbox() {
		this.compose_multilist({
			type: 'checkbox',
			description: 'create a multiple selection block',
			additem: 'selection',
			addblock: 'multiple selection'
		});
	}
	compose_radio() {
		this.compose_multilist({
			type: 'radio',
			description: 'create a single selection block',
			additem: 'selection',
			addblock: 'single selection'
		});
	}
	compose_links() {
		this.compose_multilist({
			type: 'links',
			description: 'create a link block',
			additem: 'link',
			addblock: 'link'
		});
	}

	compose_simpleElement(type) {
		/* type{type, description, addblock} */
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_" + type.type + "-description",
				"placeholder": "add description"
			}
		};
		this.textinput();
		// due to the assembler, description and type (for icon) has to be in the last element
		this.tile = {
			"description": type.description,
			"type": type.type,
			"attributes": {
				"data-type": "addButton",
				"value": "➕ add " + type.addblock
			}
		};
		this.createButtons.push(this.input('button'));
	}

	compose_file() {
		this.compose_simpleElement({
			type: 'file',
			description: 'create a file upload',
			addblock: 'file upload'
		});
	}
	compose_photo() {
		this.compose_simpleElement({
			type: 'photo',
			description: 'create a photo upload',
			addblock: 'photo upload'
		});
	}
	compose_signature() {
		this.compose_simpleElement({
			type: 'signature',
			description: 'create a signature pad',
			addblock: 'signature pad'
		});
	}
	compose_qr() {
		this.compose_simpleElement({
			type: 'qr',
			description: 'create a qr-scanner field',
			addblock: 'signature qr-scanner'
		});
	}
}
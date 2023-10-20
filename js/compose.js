import {
	getNextElementID,
	Assemble
} from './assemble.js';
import {
	_
} from '../libraries/erroronline1.js';

export var newFormElements = {};
//chain as much previousElementSibling as iterations. every odd will be deep copied including nodes (aka labels)
const cloneItems = "for (let i = 0; i < 4; i++){let clone = this.previousElementSibling.previousElementSibling.previousElementSibling.previousElementSibling.cloneNode(i % 2); clone.value=''; clone.id = getNextElementID(); this.parentNode.insertBefore(clone, this);}";

export const assembleNewElementCallback = (e) => {
	e = document.getElementById(e).childNodes[0];
	let sibling = e.nextSibling,
		property, value, element = {},
		attributes = {};
	do {
		if (sibling.type === 'button' || sibling.type === 'submit' || ['label', 'header'].includes(sibling.localName)) {
			sibling = sibling.nextSibling;
			continue;
		}
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
				sibling = sibling.nextSibling;
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
		sibling = sibling.nextSibling;
	} while (sibling !== undefined && sibling != null);

	if (Object.keys(element).length > 1) {
		const newElement = new Compose({
			"draggable": true,
			"content": [
				[element]
			]
		});
		newFormElements[newElement.id] = newElement.content;
	}
}

export function constructNewForm() {
	// set dragged/dropped order of elements - wohoo, recursion!
	function nodechildren(node) {
		const nodes = node.childNodes;
		let content = [],
			isSection;
		for (let i = 0; i < nodes.length; i++) {
			if (nodes[i].draggable) {
				isSection = nodes[i].children.item(1).firstChild;
				if (isSection.localName === 'section') {
					content.push(nodechildren(isSection));
					continue;
				}
				content.push(newFormElements[nodes[i].id][0][0]);
			}
		}
		return content;
	}
	return JSON.stringify({
		"form": {},
		"content": nodechildren(document.getElementById('main'))
	});
}

export function importForm(form) {
	Object.keys(form.content).forEach(element => {
		const newElement = new Compose({
			"draggable": true,
			"content": [
				[form.content[element]]
			]
		});
		newFormElements[newElement.id] = newElement.content;
	});
}

export const dragNdrop = {
	stopParentDropEvent: false,
	allowDrop: function (evnt) {
		evnt.preventDefault();
	},
	drag: function (evnt) {
		evnt.dataTransfer.setData("text", evnt.target.id);
		this.stopParentDropEvent = false;
	},
	drop_insert: function (evnt, droppedUpon) {
		evnt.preventDefault();
		if (!evnt.dataTransfer.getData("text")) return;

		const draggedTile = document.getElementById(evnt.dataTransfer.getData("text")),
			newtile = draggedTile.cloneNode(true), // cloned for most likely descendant issues
			originParent = draggedTile.parentNode;
		//console.log("dragged", draggedTile.id, "dropped on", droppedUpon.id);
		if (!draggedTile || this.stopParentDropEvent || draggedTile.id === droppedUpon.id) return;
		if (evnt.target.localName === 'hr') {
			// handle only if dropped within the reorder area
			droppedUpon.parentNode.insertBefore(newtile, droppedUpon);
			droppedUpon.firstChild.classList.remove('hrhover');
			this.stopParentDropEvent = true;
			// sanitize multiple section on lack of elements
			if (originParent.children.length < 2) {
				const section = originParent.parentNode.parentNode; // adapt to changes in section creation!
				section.parentNode.insertBefore(originParent.children[0], section);
				section.remove();
			}
			draggedTile.remove(); // do not remove earlier! insertBefore might reference to this object by chance
			return;
		}
		if (droppedUpon.parentNode.localName === 'main' && draggedTile.parentNode.localName === 'main' &&
			!(droppedUpon.children.item(1).firstChild.localName === 'section' || draggedTile.children.item(1).firstChild.localName === 'section')) { // avoid recursive multiples
			// create a multiple article tile if dropped on a tile
			const container = document.createElement('div'),
				article = document.createElement('article'),
				section = document.createElement('section'),
				insertionarea = document.createElement('hr'),
				previousSibling = droppedUpon.previousElementSibling;
			container.id = getNextElementID();
			container.setAttribute('draggable', 'true');
			container.setAttribute('ondragstart', 'dragNdrop.drag(event)');
			container.setAttribute('ondragover', 'dragNdrop.allowDrop(event)');
			container.setAttribute('ondrop', 'dragNdrop.drop_insert(event,this)');

			section.classList = 'inset';
			section.append(newtile, droppedUpon);
			article.append(section);
			container.append(article);

			insertionarea.setAttribute('ondragover', 'this.classList.add(\'hrhover\')');
			insertionarea.setAttribute('ondragleave', 'this.classList.remove(\'hrhover\')');
			container.insertBefore(insertionarea, container.firstChild);
			previousSibling.parentNode.insertBefore(container, previousSibling.nextSibling);
			draggedTile.remove(); // do not remove earlier! inserBefore might reference to this object by chance
			return;
		}
	},
	drop_delete: function (evnt) {
		document.getElementById(evnt.dataTransfer.getData("text")).remove();
	}
};

export class Compose extends Assemble {
	constructor(setup) {
		super(setup);
		this.createDraggable = this.setup.draggable;

		this.initializeSection();
		if (this.createDraggable) {
			this.section.id = getNextElementID();
			this.section.setAttribute('draggable', 'true');
			this.section.setAttribute('ondragstart', 'dragNdrop.drag(event)');
			this.section.setAttribute('ondragover', 'dragNdrop.allowDrop(event)');
			this.section.setAttribute('ondrop', 'dragNdrop.drop_insert(event,this)');
			const insertionarea = document.createElement('hr');
			insertionarea.setAttribute('ondragover', 'this.classList.add(\'hrhover\')');
			insertionarea.setAttribute('ondragleave', 'this.classList.remove(\'hrhover\')');
			this.section.insertBefore(insertionarea, this.section.firstChild);
		}
		return {
			id: this.section.id,
			content: this.content
		};
	}

	single(tileProperties) { // overriding parent method
		const article = document.createElement('article'),
			form = document.createElement('form');
		article.setAttribute('data-type', this.tile.type);
		article.id = getNextElementID();
		form.action = "javascript:assembleNewElementCallback('" + article.id + "')";
		article.append(...this.elements);
		this.composer_add_trash(article);
		form.append(article);
		if (tileProperties.form) return form;
		return article;
	}

	trash() {
		// empty method but necessary to display the delete-area
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
		// due to the assembler, type (for icon) has to be in the last element
		this.tile = {
			"type": "text",
			"attributes": {
				"data-type": "addButton",
				"value": "✓ add text"
			}
		};
		this.input('submit');
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
				"placeholder": "add advanced attributes {json}",
				"pattern": "^\\{.+\\}$|^$",
				"title": "json object with double quotes"
			}
		};
		this.textinput();
		// due to the assembler, type (for icon) has to be in the last element
		this.tile = {
			"type": type.type,
			"attributes": {
				"data-type": "addButton",
				"value": "✓ add " + type.addblock
			}
		};
		this.input('submit');
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
				"placeholder": "add advanced attributes {json}",
				"pattern": "^\\{.+\\}$|^$",
				"title": "json object with double quotes"
			}
		};
		this.textinput();
		this.tile = {
			"attributes": {
				"data-type": "addButton",
				"value": "✚ add " + type.additem + " item",
				"onpointerdown": cloneItems
			}
		};
		this.input('button');
		// due to the assembler, type (for icon) has to be in the last element
		this.tile = {
			"type": type.type,
			"attributes": {
				"data-type": "addButton",
				"value": "✓ add " + type.addblock + " block"
			}
		};
		this.input('submit');

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
		// due to the assembler, type (for icon) has to be in the last element
		this.tile = {
			"type": type.type,
			"attributes": {
				"data-type": "addButton",
				"value": "✓ add " + type.addblock
			}
		};
		this.input('submit');
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
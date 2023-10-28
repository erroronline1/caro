/*
this module helps to compose and edit forms according to the passed simplified object notation. it makes use of the assemble library.
*/
import {
	getNextElementID,
	Assemble
} from './assemble.js';
import {
	_
} from '../libraries/erroronline1.js';

//chain as much previousElementSibling as iterations. every odd will be deep copied including nodes (aka labels)
const cloneItems = "for (let i = 0; i < 4; i++){let clone = this.previousElementSibling.previousElementSibling.previousElementSibling.previousElementSibling.cloneNode(i % 2); clone.value = ''; clone.id = compose_helper.getNextElementID(); this.parentNode.insertBefore(clone, this);}";

export const compose_helper = {
	newFormElements: {},
	newMetaFormElements: new Set(),
	getNextElementID: getNextElementID,
	composeNewElementCallback: function (e) {
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
			const newElements = new Compose({
				"visible": true,
				"draggable": true,
				"content": [
					[element]
				]
			});
			newElements.forEach(r_element => {
				compose_helper.newFormElements[r_element.id] = r_element.content;
			});
		}
	},

	composeNewForm: function () {
		// set dragged/dropped order of elements - wohoo, recursion!
		let isForm = false;

		function nodechildren(node, recursion = false) {
			const nodes = node.childNodes;
			let content = [],
				isSection;
			for (let i = 0; i < nodes.length; i++) {
				if (nodes[i].draggable) {
					isSection = nodes[i].children[1].firstChild;
					if (isSection.localName === 'section') {
						content.push(nodechildren(isSection, true));
						continue;
					}
					if (nodes[i].children[1].id in compose_helper.newFormElements) {
						if (recursion) content.push(compose_helper.newFormElements[nodes[i].children[1].id]);
						else content.push([compose_helper.newFormElements[nodes[i].children[1].id]]);
						if ('dataset' in nodes[i].children[1] && 'type' in nodes[i].children[1].dataset && nodes[i].children[1].dataset.type != 'text') isForm = true;
					}
				}
			}
			return content;
		}
		const answer = {
			"content": nodechildren(document.getElementById('main'))
		};
		if (isForm) answer.form = {};
		return JSON.stringify(answer);
	},
	composeNewMetaForm: function () {
		// set dragged/dropped order of elements
		const nodes = document.getElementById('main').childNodes;
		let content = [],
			hidden = {};
		for (let i = 0; i < nodes.length; i++) {
			if ('dataset' in nodes[i] && 'name' in nodes[i].dataset) content.push(nodes[i].dataset.name);
			if (nodes[i].childNodes.length && nodes[i].childNodes[1].dataset.type === 'hiddeninput') hidden[nodes[i].childNodes[1].childNodes[2].name] = nodes[i].childNodes[1].childNodes[2].value;
		}
		return JSON.stringify({
			"hidden": hidden,
			"forms": content
		});
	},

	importForm: function (form) {
		Object.keys(form.content).forEach(element => {
			const newElements = new Compose({
				"draggable": true,
				"content": [
					form.content[element]
				]
			});
			newElements.forEach(r_element => {
				compose_helper.newFormElements[r_element.id] = r_element.content;
			});
		});
	},
	importMetaForm: function (form) {
		form.draggable = true;
		new MetaCompose(form);
		compose_helper.newMetaFormElements.add(form.name);
	},

	dragNdrop: {
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
				container.setAttribute('ondragstart', 'compose_helper.dragNdrop.drag(event)');
				container.setAttribute('ondragover', 'compose_helper.dragNdrop.allowDrop(event)');
				container.setAttribute('ondrop', 'compose_helper.dragNdrop.drop_insert(event,this)');

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
	},
	create_draggable: function (element) {
		element.id = getNextElementID();
		element.setAttribute('draggable', 'true');
		element.setAttribute('ondragstart', 'compose_helper.dragNdrop.drag(event)');
		element.setAttribute('ondragover', 'compose_helper.dragNdrop.allowDrop(event)');
		element.setAttribute('ondrop', 'compose_helper.dragNdrop.drop_insert(event,this)');
		const insertionarea = document.createElement('hr');
		insertionarea.setAttribute('ondragover', 'this.classList.add(\'hrhover\')');
		insertionarea.setAttribute('ondragleave', 'this.classList.remove(\'hrhover\')');
		element.insertBefore(insertionarea, element.firstChild);
	},
	composer_add_trash: function (section) {
		section.setAttribute('ondragstart', 'compose_helper.dragNdrop.drag(event)');
		section.setAttribute('ondragover', 'compose_helper.dragNdrop.allowDrop(event)');
		section.setAttribute('ondrop', 'compose_helper.dragNdrop.drop_delete(event)');
	}
};

export class Compose extends Assemble {
	constructor(setup) {
		super(setup);
		this.createDraggable = this.setup.draggable;
		this.createdArticles = [];

		this.initializeSection();
		if (this.createDraggable) compose_helper.create_draggable(this.section);
		return this.createdArticles;
	}

	single(tileProperties, oneOfFew = false) { // overriding parent method
		let dragContainer = document.createElement('div');
		const article = document.createElement('article'),
			form = document.createElement('form');
		article.setAttribute('data-type', this.tile.type);
		article.id = getNextElementID();
		form.action = "javascript:compose_helper.composeNewElementCallback('" + article.id + "')";
		article.append(...this.elements);
		if (this.tile.type === 'trash') compose_helper.composer_add_trash(article);
		form.append(article);
		this.createdArticles.push({
			id: article.id,
			content: tileProperties
		});
		if (tileProperties.form) return form;
		if (this.createDraggable && oneOfFew) {
			compose_helper.create_draggable(dragContainer);
			dragContainer.append(article);
			return dragContainer;
		}
		return article;
	}

	compose_text() {
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_text-description",
				"placeholder": "add information description",
				"required": true
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
			"description": "add text",
			"attributes": {
				"data-type": "addblock",
				"type": "submit"
			}
		};
		this.button();
	}

	compose_input(type) {
		/* type{type, description, addblock} */

		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_" + type.type + "-description",
				"placeholder": "add description",
				"required": true
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
		if (type.attributes) this.tile.attributes.value = type.attributes;
		this.textinput();
		// due to the assembler, type (for icon) has to be in the last element
		this.tile = {
			"type": type.type,
			"description": "add " + type.addblock,
			"attributes": {
				"data-type": "addblock",
				"type": "submit"
			}
		};
		this.button();
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
	compose_hiddeninput() {
		this.compose_input({
			type: 'hiddeninput',
			description: 'create a hidden field',
			addblock: 'hidden field',
			attributes: "{\"value\":\"usecase\"}"
		});
	}

	compose_multilist(type) {
		/* type{type, description, additem, addblock} */
		this.tile = {
			"type": "textinput",
			"attributes": {
				"name": "compose_" + type.type + "-description",
				"placeholder": "add description",
				"required": true
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
			"type": type.type,
			"description": "add " + type.additem + " item",
			"attributes": {
				"data-type": "additem",
				"onpointerdown": cloneItems
			}
		};
		this.button();
		// due to the assembler, type (for icon) has to be in the last element
		this.tile = {
			"type": type.type,
			"description": "add " + type.addblock + " block",
			"attributes": {
				"data-type": "addblock",
				"type": "submit"
			}
		};
		this.button();
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
				"placeholder": "add description",
				"required": true
			}
		};
		this.textinput();
		// due to the assembler, type (for icon) has to be in the last element
		this.tile = {
			"type": type.type,
			"description": "add " + type.addblock,
			"attributes": {
				"data-type": "addblock",
				"type": "submit"
			}
		};
		this.button();
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
	compose_qrscanner() {
		this.compose_simpleElement({
			type: 'qrscanner',
			description: 'create a qr-scanner field',
			addblock: 'qr-scanner'
		});
	}
}

export class MetaCompose extends Assemble {
	constructor(setup) {
		super(setup);

		this.initializeSection();
		if (setup.draggable) compose_helper.create_draggable(this.section);
		this.section.setAttribute('data-name', setup.name);
	}
}
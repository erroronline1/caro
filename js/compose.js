/*
this module helps to compose and edit forms according to the passed simplified object notation. it makes use of the assemble library.
*/
import {
	getNextElementID,
	Assemble
} from './assemble.js';

//chain as much previousElementSibling as iterations. every odd will be deep copied including nodes (aka labels)
const cloneItems = "for (let i = 0; i < 4; i++){let clone = this.previousElementSibling.previousElementSibling.previousElementSibling.previousElementSibling.cloneNode(i % 2); clone.value = ''; clone.id = compose_helper.getNextElementID(); this.parentNode.insertBefore(clone, this);}";

export const compose_helper = {
	newFormComponents: {},
	newFormElements: new Set(),
	getNextElementID: getNextElementID,
	composeNewElementCallback: function (parent) {
		let sibling = parent.childNodes[0].nextSibling,
			setTo, name, value, element = {
				attributes: {}
			};
		const setName = {
			name: ['select', 'signature', 'scanner'],
			description: ['links', 'photo', 'file', 'checkbox', 'radio']
		};
		do {
			if (sibling.type === 'button' || sibling.type === 'submit' || ['label', 'header', 'br'].includes(sibling.localName)) {
				sibling = sibling.nextSibling;
				continue;
			}
			if (sibling.localName === 'span') {
				if (element.type === undefined) element['type'] = sibling.dataset.type;
				sibling = sibling.nextSibling;
				continue;
			}
			name = sibling.name.replace(/\(.*?\)|\[\]/g, '');
			value = sibling.value;

			//names

			if (['links', 'radio', 'select', 'checkbox'].includes(element.type)) {
				if (name === LANG.GET('assemble.compose_multilist_name')) {
					setTo = Object.keys(setName).find(key => setName[key].includes(element.type));
					if (value && setTo === 'name') element.attributes['name'] = value;
					else if (value && setTo === 'description') element['description'] = value;
					else return;
				}
				if (name === LANG.GET('assemble.compose_multilist_add_item') && value) {
					if (element.content === undefined) element.content = {};
					element.content[value] = {};
				}
			} else if (['file', 'photo', 'signature', 'scanner'].includes(element.type)) {
				if (name === LANG.GET('assemble.compose_simple_element')) {
					setTo = Object.keys(setName).find(key => setName[key].includes(element.type));
					if (value && setTo === 'name') element.attributes['name'] = value;
					else if (value && setTo === 'description') element['description'] = value;
					else return;
				}
			} else {
				if (name === LANG.GET('assemble.compose_field_name'))
					if (value) element.attributes['name'] = value;
					else return;
			}
			if (name === LANG.GET('assemble.compose_field_hint') && value) element['hint'] = value;
			if (name === 'required' && sibling.checked) element.attributes['required'] = true;
			if (name === 'multiple' && sibling.checked) element.attributes['multiple'] = true;
			sibling = sibling.nextSibling;
		} while (sibling !== undefined && sibling != null);

		if (Object.keys(element).length > 1) {
			const newElements = new Compose({
				'visible': true,
				'draggable': true,
				'content': [
					[element]
				]
			});
			newElements.forEach(r_element => {
				compose_helper.newFormComponents[r_element.id] = r_element.content;
			});
		}
	},

	composeNewComponent: function () {
		// set dragged/dropped order of elements - wohoo, recursion!
		let isForm = false,
			componentContent,
			name = document.getElementById('ComponentName').value;

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
					if (nodes[i].children[1].id in compose_helper.newFormComponents) {
						if (recursion) content.push(compose_helper.newFormComponents[nodes[i].children[1].id]);
						else content.push([compose_helper.newFormComponents[nodes[i].children[1].id]]);
						if ('dataset' in nodes[i].children[1] && 'type' in nodes[i].children[1].dataset && nodes[i].children[1].dataset.type != 'text') isForm = true;
					}
				}
			}
			return content;
		}
		componentContent = nodechildren(document.getElementById('main'));
		const answer = {
			'name': name,
			'content': componentContent
		};
		if (isForm) answer.form = {};
		if (name && componentContent) return answer;
		return null;
	},
	composeNewForm: function () {
		// set dragged/dropped order of elements
		const nodes = document.getElementById('main').childNodes,
			name = document.getElementById('ComponentName').value;
		let content = [],
			hidden = {};
		for (let i = 0; i < nodes.length; i++) {
			if ('dataset' in nodes[i] && 'name' in nodes[i].dataset) content.push(nodes[i].dataset.name);
			if (nodes[i].childNodes.length && nodes[i].childNodes[1].dataset.type === 'hiddeninput') hidden[nodes[i].childNodes[1].childNodes[2].name] = nodes[i].childNodes[1].childNodes[2].value;
		}
		if (name && content.length) return {
			'hidden': hidden,
			'forms': content,
			'name': name
		};
		return null;
	},

	importComponent: function (form) {
		compose_helper.newFormComponents = {};
		for (const [key, element] of Object.entries(form.content)) {
			const newElements = new Compose({
				'draggable': true,
				'content': [
					element
				]
			});
			newElements.forEach(r_element => {
				compose_helper.newFormComponents[r_element.id] = r_element.content;
			});
		};
	},
	importForm: function (form) {
		form.draggable = true;
		new MetaCompose(form);
		compose_helper.newFormElements.add(form.name);
	},

	dragNdrop: {
		stopParentDropEvent: false,
		allowDrop: function (evnt) {
			evnt.preventDefault();
		},
		drag: function (evnt) {
			evnt.dataTransfer.setData('text', evnt.target.id);
			this.stopParentDropEvent = false;
		},
		drop_insert: function (evnt, droppedUpon) {
			evnt.preventDefault();
			if (!evnt.dataTransfer.getData('text')) return;

			const draggedTile = document.getElementById(evnt.dataTransfer.getData('text')),
				newtile = draggedTile.cloneNode(true), // cloned for most likely descendant issues
				originParent = draggedTile.parentNode;
			//console.log('dragged', draggedTile.id, 'dropped on', droppedUpon.id);
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
				insertionarea.classList.add('insertionarea');
				container.insertBefore(insertionarea, container.firstChild);
				previousSibling.parentNode.insertBefore(container, previousSibling.nextSibling);
				draggedTile.remove(); // do not remove earlier! inserBefore might reference to this object by chance
				return;
			}
		},
		drop_delete: function (evnt) {
			document.getElementById(evnt.dataTransfer.getData('text')).remove();
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
		insertionarea.classList.add('insertionarea');
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
		this.createDraggable = setup.draggable;
		this.createdArticles = [];

		this.initializeSection();
		if (this.createDraggable) compose_helper.create_draggable(this.section);
		return this.createdArticles;
	}

	compose_text() {
		let result = [];
		this.currentElement = {
			type: 'text',
			attributes: {
				name: LANG.GET('assemble.compose_text_description'),
				required: true
			}
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			type: 'textarea',
			attributes: {
				name: LANG.GET('assemble.compose_text_content'),
				rows: 5
			}
		};
		result = result.concat(...this.textarea());
		this.currentElement = {
			attributes: {
				value: LANG.GET('assemble.compose_text'),
				'data-type': 'addblock',
				type: 'submit',
				onpointerup: 'compose_helper.composeNewElementCallback(this.parentNode)'
			}
		};
		result.push(this.button());
		return result;
	}

	compose_input(type) {
		let result = [];
		this.currentElement = {
			type: type.type,
			attributes: {
				name: LANG.GET('assemble.compose_field_name'),
				required: true
			}
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			type: 'text',
			attributes: {
				name: LANG.GET('assemble.compose_field_hint'),
			}
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			content: {}
		};
		this.currentElement.content[LANG.GET('assemble.compose_required')] = {
			name: 'required'
		};
		result = result.concat(this.br(), ...this.checkbox());
		this.currentElement = {
			attributes: {
				value: type.description,
				'data-type': 'addblock',
				type: 'submit',
				onpointerup: 'compose_helper.composeNewElementCallback(this.parentNode)'
			}
		};
		result.push(this.button());
		return result;
	}
	compose_textinput() {
		return this.compose_input({
			type: 'textinput',
			description: LANG.GET('assemble.compose_textinput'),
		});
	}
	compose_numberinput() {
		return this.compose_input({
			type: 'numberinput',
			description: LANG.GET('assemble.compose_numberinput'),
		});
	}
	compose_dateinput() {
		return this.compose_input({
			type: 'dateinput',
			description: LANG.GET('assemble.compose_dateinput'),
		});
	}
	compose_textarea() {
		return this.compose_input({
			type: 'textarea',
			description: LANG.GET('assemble.compose_textarea'),
		});
	}

	compose_multilist(type) {
		let result = [];
		this.currentElement = {
			type: type.type,
			attributes: {
				name: LANG.GET('assemble.compose_multilist_name'),
				required: true
			}
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			type: 'text',
			attributes: {
				name: LANG.GET('assemble.compose_field_hint'),
			}
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			type: 'textinput',
			attributes: {
				name: LANG.GET('assemble.compose_multilist_add_item') + '[]'
			}
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			attributes: {
				value: LANG.GET('assemble.compose_multilist_add_item_button'),
				'data-type': 'additem',
				type: 'button',
				onpointerdown: cloneItems
			}
		};
		result.push(this.button());
		if (type.required !== undefined) {
			this.currentElement = {
				content: {}
			};
			this.currentElement.content[LANG.GET('assemble.compose_required')] = {
				name: 'required'
			};
			result = result.concat(this.br(), ...this.checkbox());
		}
		this.currentElement = {
			attributes: {
				value: type.description,
				'data-type': 'addblock',
				type: 'submit',
				onpointerup: 'compose_helper.composeNewElementCallback(this.parentNode)'
			}
		};
		result.push(this.button());
		return result;
	}
	compose_select() {
		return this.compose_multilist({
			type: 'select',
			description: LANG.GET('assemble.compose_select'),
			required: 'optional'
		});
	}
	compose_checkbox() {
		return this.compose_multilist({
			type: 'checkbox',
			description: LANG.GET('assemble.compose_checkbox'),
		});
	}
	compose_radio() {
		return this.compose_multilist({
			type: 'radio',
			description: LANG.GET('assemble.compose_radio'),
			required: 'optional'
		});
	}
	compose_links() {
		return this.compose_multilist({
			type: 'links',
			description: LANG.GET('assemble.compose_links'),
		});
	}

	compose_simpleElement(type) {
		let result = [];
		this.currentElement = {
			'type': type.type,
			'attributes': {
				'name': LANG.GET('assemble.compose_simple_element'),
				'required': true
			}
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			type: 'text',
			attributes: {
				name: LANG.GET('assemble.compose_field_hint'),
			}
		};
		result = result.concat(...this.textinput());
		if (type.required !== undefined) {
			this.currentElement = {
				content: {}
			};
			this.currentElement.content[LANG.GET('assemble.compose_required')] = {
				name: 'required'
			};
			result = result.concat(this.br(), ...this.checkbox());
		}
		if (type.multiple !== undefined) {
			this.currentElement = {
				content: {}
			};
			this.currentElement.content[LANG.GET('assemble.compose_multiple')] = {
				name: 'multiple'
			};
			result = result.concat(this.br(), ...this.checkbox());
		}
		this.currentElement = {
			attributes: {
				value: type.description,
				'data-type': 'addblock',
				type: 'submit',
				onpointerup: 'compose_helper.composeNewElementCallback(this.parentNode)'
			}
		};
		result.push(this.button());
		return result;
	}

	compose_file() {
		return this.compose_simpleElement({
			type: 'file',
			description: LANG.GET('assemble.compose_file'),
			multiple: 'optional'
		});
	}
	compose_photo() {
		return this.compose_simpleElement({
			type: 'photo',
			description: LANG.GET('assemble.compose_photo'),
		});
	}
	compose_signature() {
		return this.compose_simpleElement({
			type: 'signature',
			description: LANG.GET('assemble.compose_signature'),
			required: 'optional'
		});
	}
	compose_scanner() {
		return this.compose_simpleElement({
			type: 'scanner',
			description: LANG.GET('assemble.compose_scanner'),
		});
	}

	compose_component(std = {
		name: LANG.GET('assemble.compose_component_name'),
		description: LANG.GET('assemble.compose_component'),
		action: 'api.form("post", "component")'
	}) {
		let result = [];
		this.currentElement = {
			content: std.description
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: 'textinput',
			attributes: {
				id: 'ComponentName',
				value: this.currentElement.value || '',
				name: std.name,
				required: true,
			}
		};
		result = result.concat(...this.textinput());
		// due to the assembler, type (for icon) has to be in the last element
		this.currentElement = {
			type: 'save',
			attributes: {
				value: std.description,
				onpointerdown: std.action,
				type: 'button'
			}
		};
		result.push(this.button());
		return result;
	}
	compose_form() {
		this.compose_component({
			placeholder: LANG.GET('assemble.compose_form_label'),
			description: LANG.GET('assemble.compose_form'),
			action: 'api.form("post","form")'
		});
	}
}

export class MetaCompose extends Assemble {
	constructor(setup) {
		delete setup.form;
		super(setup);

		this.initializeSection();
		if (setup.draggable) compose_helper.create_draggable(this.section);
		this.section.setAttribute('data-name', setup.name);
	}
}
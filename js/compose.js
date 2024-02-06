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
			setTo, elementName, value;
		const setName = {
				name: ['select', 'scanner'],
				description: ['links', 'photo', 'file', 'checkbox', 'radio']
			},
			element = {
				attributes: {}
			};
		do {
			if (!(['span', 'input'].includes(sibling.localName))) {
				sibling = sibling.nextSibling;
				continue;
			}
			if (sibling.localName === 'span') {
				if (element.type === undefined) element['type'] = sibling.dataset.type;
				sibling = sibling.nextSibling;
				continue;
			}
			elementName = sibling.name.replace(/\(.*?\)|\[\]/g, '');
			value = sibling.value;
			if (['links', 'radio', 'select', 'checkbox'].includes(element.type)) {
				if (elementName === LANG.GET('assemble.compose_multilist_name')) {
					setTo = Object.keys(setName).find(key => setName[key].includes(element.type));
					if (value && setTo === 'name') element.attributes.name = value;
					else if (value && setTo === 'description') element.description = value;
					else return;
				}
				if (elementName === LANG.GET('assemble.compose_multilist_add_item') && value) {
					if (element.content === undefined) element.content = {};
					element.content[value] = {};
				}
			} else if (['file', 'photo', 'scanner'].includes(element.type)) {
				if (elementName === LANG.GET('assemble.compose_simple_element')) {
					setTo = Object.keys(setName).find(key => setName[key].includes(element.type));
					if (value && setTo === 'name') element.attributes.name = value;
					else if (value && setTo === 'description') element.description = value;
					else return;
				}
			} else if (['text'].includes(element.type)) {
				if (elementName === LANG.GET('assemble.compose_text_description')) {
					if (value) element.description = value;
					else return;
				}
				if (elementName === LANG.GET('assemble.compose_text_content') && value) {
					element.content = value;
				}
			} else {
				if (elementName === LANG.GET('assemble.compose_field_name')) {
					if (value) element.attributes.name = value;
					else return;
				}
			}
			if (elementName === LANG.GET('assemble.compose_field_hint') && value) element.hint = value;
			if (elementName === LANG.GET('assemble.compose_required') && sibling.checked) element.attributes.required = true;
			if (elementName === LANG.GET('assemble.compose_multiple') && sibling.checked) element.attributes.multiple = true;
			sibling = sibling.nextSibling;
		}
		while (sibling);
		if (Object.keys(element).length > 1) {
			const newElement = new Compose({
				'draggable': true,
				'composer': 'component',
				'content': [
					[structuredClone(element)] // element receives attributes from currentElement otherwise
				]
			});
			compose_helper.newFormComponents[newElement.generatedElementIDs[0]] = element;
		}
	},

	composeNewComponent: function () {
		// set dragged/dropped order of elements - wohoo, recursion!
		let isForm = false,
			componentContent = [],
			name = document.getElementById('ComponentName').value;

		function nodechildren(parent) {
			let content = [],
				container;
			[...parent.childNodes].forEach(node => {
				if (node.draggable) {
					container = node.children[1];
					if (container.localName === 'article') {
						if (container.firstChild.localName === 'section') content.push(nodechildren(container.firstChild));
						else content.push(nodechildren(container));
					} else {
						if (node.id in compose_helper.newFormComponents) {
							if (compose_helper.newFormComponents[node.id].attributes != undefined) delete compose_helper.newFormComponents[node.id].attributes['placeholder'];
							content.push(compose_helper.newFormComponents[node.id]);
							if (compose_helper.newFormComponents[node.id].type != 'text') isForm = true;
						}
					}
				}
			});
			return content;
		}
		componentContent = nodechildren(document.querySelector('main'));
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
		const newElements = new Compose({
			'draggable': true,
			'composer': 'component',
			'content': form.content
		});
		// recursive function to assign created ids to form content elements in order of appearance
		const elementIDs = newElements.generatedElementIDs;
		let i = 0;

		function assignIDs(element) {
			for (const container of Object.entries(element)) {
				if (typeof container[0] === 'array') {
					assignIDs(container);
				} else {
					compose_helper.newFormComponents[elementIDs] = container;
					i++;
				}
			}
		}
		assignIDs(form.content);
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

			const draggedElement = document.getElementById(evnt.dataTransfer.getData('text')),
				draggedElementClone = draggedElement.cloneNode(true), // cloned for most likely descendant issues
				originParent = draggedElement.parentNode;
			//console.log('dragged', draggedElement.id, 'dropped on', droppedUpon.id, 'target', evnt.target);
			if (!draggedElement || this.stopParentDropEvent || draggedElement.id === droppedUpon.id) return;

			// dragging single element
			if (draggedElement.classList.contains('draggableFormElement')) {
				// dropping on single element
				if (droppedUpon.classList.contains('draggableFormElement')) {
					droppedUpon.parentNode.insertBefore(draggedElementClone, droppedUpon);
				}
				// dropping on hr creating a new article
				else if (evnt.target.localName === 'hr') {
					let container = document.createElement('div'),
						article = document.createElement('article'),
						insertionArea = document.createElement('hr');
					container = compose_helper.create_draggable(container, false);
					article.append(draggedElementClone);
					insertionArea.setAttribute('ondragover', 'this.classList.add(\'insertionAreaHover\')');
					insertionArea.setAttribute('ondragleave', 'this.classList.remove(\'insertionAreaHover\')');
					insertionArea.classList.add('insertionArea');
					container.append(insertionArea, article);
					droppedUpon.parentNode.insertBefore(container, droppedUpon);
					droppedUpon.firstChild.classList.remove('insertionAreaHover');
				}
				// avoid dropping elsewhere (main, article borders, etc.)
				else return;
				// dropping on self or own container
				this.stopParentDropEvent = true;
				// sanitize article on lack of elements
				if (originParent.children.length < 2) {
					originParent.parentNode.remove(); // adapt to changes in section creation!
				}
				draggedElement.remove(); // do not remove earlier! insertBefore might reference to this object by chance
				return;
			}

			// dragging articles
			// dropping on hr for reordering
			if (evnt.target.localName === 'hr') {
				// handle only if dropped within the reorder area
				droppedUpon.parentNode.insertBefore(draggedElementClone, droppedUpon);
				droppedUpon.firstChild.classList.remove('insertionAreaHover');
				this.stopParentDropEvent = true;
				draggedElement.remove(); // do not remove earlier! insertBefore might reference to this object by chance
				// sanitize section on lack of articles
				if (originParent.children.length < 2) {
					//                                                                                        section    article    container  
					document.getElementById('main').insertBefore(originParent.children[0].cloneNode(true), originParent.parentNode.parentNode); // adapt to changes in section creation!
					originParent.parentNode.parentNode.remove();
				}
				return;
			}
			// dropping on article to create a slider
			if (droppedUpon.parentNode.localName === 'main' && draggedElement.parentNode.localName === 'main' &&
				!(droppedUpon.children.item(1).firstChild.localName === 'section' || draggedElement.children.item(1).firstChild.localName === 'section')) { // avoid recursive multiples
				// create a multiple article tile if dropped on a tile
				let container = document.createElement('div'),
					article = document.createElement('article'),
					section = document.createElement('section'),
					insertionArea = document.createElement('hr'),
					previousSibling = droppedUpon.previousElementSibling;
				container = compose_helper.create_draggable(container, false);

				section.append(draggedElementClone, droppedUpon);
				article.append(section);
				container.append(article);

				insertionArea.setAttribute('ondragover', 'this.classList.add(\'insertionAreaHover\')');
				insertionArea.setAttribute('ondragleave', 'this.classList.remove(\'insertionAreaHover\')');
				insertionArea.classList.add('insertionArea');
				container.insertBefore(insertionArea, container.firstChild);
				previousSibling.parentNode.insertBefore(container, previousSibling.nextSibling);
				draggedElement.remove(); // do not remove earlier! inserBefore might reference to this object by chance
				return;
			}
		},
		drop_delete: function (evnt) {
			const draggedElement = document.getElementById(evnt.dataTransfer.getData('text')),
				originParent = draggedElement.parentNode;
			// sanitize article on lack of elements
			if (originParent.parentNode != document.getElementById('main') && originParent.children.length < 2) {
				originParent.parentNode.remove(); // adapt to changes in section creation!
			}
			draggedElement.remove();
		}
	},

	create_draggable: function (element, insertionArea = true) {
		element.id = getNextElementID();
		element.setAttribute('draggable', 'true');
		element.setAttribute('ondragstart', 'compose_helper.dragNdrop.drag(event)');
		element.setAttribute('ondragover', 'compose_helper.dragNdrop.allowDrop(event); this.classList.add(\'draggableFormElementHover\')');
		element.setAttribute('ondragleave', 'this.classList.remove(\'draggableFormElementHover\')');
		element.setAttribute('ondrop', 'compose_helper.dragNdrop.drop_insert(event,this), this.classList.remove(\'draggableFormElementHover\')');
		if (insertionArea) {
			const insertionArea = document.createElement('hr');
			insertionArea.setAttribute('ondragover', 'this.classList.add(\'insertionAreaHover\')');
			insertionArea.setAttribute('ondragleave', 'this.classList.remove(\'insertionAreaHover\')');
			insertionArea.classList.add('insertionArea');
			element.insertBefore(insertionArea, element.firstChild);
		}
		return element;
	},
	composer_add_trash: function (element) {
		element.setAttribute('ondragstart', 'compose_helper.dragNdrop.drag(event)');
		element.setAttribute('ondragover', 'compose_helper.dragNdrop.allowDrop(event)');
		element.setAttribute('ondrop', 'compose_helper.dragNdrop.drop_delete(event)');
	}
};

export class Compose extends Assemble {
	constructor(setup) {
		super(setup);
		this.createDraggable = setup.draggable;
		this.composer = setup.composer;
		this.generatedElementIDs = [];
		this.initializeSection();
		this.returnID();
	}
	returnID() {
		// a constructor must not return anything
		// idk why, but the passed object is always the whole class, no use trying to pass any individual properties...
		return this;
	}

	processPanel(elements) { // overriding parent method
		/**
		 * content to exist of three nestings
		 * [ panel article>section
		 * 		[ slide article
		 * 			{ element },
		 * 			{ element }
		 * 		],
		 * 		[ slide article
		 *		...],
		 * ],
		 * 
		 * or two nestings
		 * [ panel article
		 * 		{ element },
		 * 		{ element }
		 * ]
		 */
		let content = [],
			widget;
		if (elements.constructor.name === 'Array') {
			const section = document.createElement('section');
			section.id = getNextElementID();
			elements.forEach(element => {
				widget = this.processPanel(element);
				if (elements[0].constructor.name === 'Array') { // article
					const article = document.createElement('article');
					// creation form for adding elements
					if (element[0].form) {
						const form = document.createElement('form');
						form.onsubmit = () => {
							compose_helper.composeNewElementCallback(form);
						};
						form.action = 'javascript:void(0);';
						for (const e of widget) {
							if (e) form.append(e);
						}
						article.append(form);
					}
					// element created
					else {
						for (const e of widget) {
							if (e) article.append(e);
						}
					}
					// creation form for adding elements
					if (!this.createDraggable) {
						section.append(article);
					}
					// element created
					else {
						let div = document.createElement('div');
						div = compose_helper.create_draggable(div);
						div.append(article);
						section.append(div);
					}
				} else { // single element, last in chain
					for (const e of widget) {
						if (e) content.push(e);
					}
				}
			});
			if (elements[0].constructor.name === 'Array') content = content.concat(section, this.createDraggable ? [] : this.slider(section.id, section.childNodes.length));
		} else {
			this.currentElement = elements;
			// creation form for adding elements
			if (!this.createDraggable) {
				content = content.concat(this[this.currentElement.type]());
			}
			// element created
			else {
				let frame = document.createElement('div');
				frame.classList.add('draggableFormElement');
				frame.append(...this[this.currentElement.type]());
				frame = compose_helper.create_draggable(frame, false);
				this.generatedElementIDs.push(frame.id);
				content.push(frame);
			}
		}
		return content;
	}

	processContent() {
		let assembledPanels = new Set();
		this.content.forEach(panel => {
			const raw_nodes = this.processPanel(panel),
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
				if (this.createDraggable) {
					let container = document.createElement('div');
					container = compose_helper.create_draggable(container);
					container.append(article);
					assembledPanels.add(container);
				} else assembledPanels.add(article);
			} else assembledPanels.add(...nodes);
		})
		return assembledPanels;
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
			name: LANG.GET('assemble.compose_required')
		};
		result = result.concat(this.br(), ...this.checkbox());
		this.currentElement = {
			attributes: {
				value: type.description,
				'data-type': 'addblock',
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
				onpointerup: cloneItems
			}
		};
		result.push(this.button());
		if (type.required !== undefined) {
			this.currentElement = {
				content: {}
			};
			this.currentElement.content[LANG.GET('assemble.compose_required')] = {
				name: LANG.GET('assemble.compose_required')
			};
			result = result.concat(this.br(), ...this.checkbox());
		}
		this.currentElement = {
			attributes: {
				value: type.description,
				'data-type': 'addblock',
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
				name: LANG.GET('assemble.compose_required')
			};
			result = result.concat(this.br(), ...this.checkbox());
		}
		if (type.multiple !== undefined) {
			this.currentElement = {
				content: {}
			};
			this.currentElement.content[LANG.GET('assemble.compose_multiple')] = {
				name: LANG.GET('assemble.compose_multiple')
			};
			result = result.concat(this.br(), ...this.checkbox());
		}
		this.currentElement = {
			attributes: {
				value: type.description,
				'data-type': 'addblock',
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
			attributes: {
				value: std.description,
				onpointerup: std.action,
				type: 'button'
			}
		};
		result.push(this.submitbutton());
		return result;
	}
	compose_form() {
		this.compose_component({
			name: LANG.GET('assemble.compose_form_label'),
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
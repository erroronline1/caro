/*
this module helps to compose and edit forms according to the passed simplified object notation. it makes use of the assemble library.
*/
import { getNextElementID, Assemble } from "./assemble.js";

//chain as much previousElementSibling as iterations. every odd will be deep copied including nodes (aka labels)
const cloneItems =
	"for (let i = 0; i < 4; i++){let clone = this.previousElementSibling.previousElementSibling.previousElementSibling.previousElementSibling.cloneNode(i % 2); clone.value = ''; clone.id = compose_helper.getNextElementID(); this.parentNode.insertBefore(clone, this);}";

export const compose_helper = {
	newFormComponents: {},
	newFormElements: new Set(),
	newTextElements: {},
	getNextElementID: getNextElementID,
	composeNewElementCallback: function (parent) {
		let sibling = parent.childNodes[0].nextSibling,
			setTo,
			elementName,
			value;
		const setName = {
				name: ["select", "scanner", "radio", "photo", "file", "signature"],
				description: ["links", "checkbox"],
			},
			element = {
				attributes: {},
			};
		do {
			if (!["span", "input", "textarea"].includes(sibling.localName)) {
				sibling = sibling.nextSibling;
				continue;
			}
			if (sibling.localName === "span") {
				if (element.type === undefined) element["type"] = sibling.dataset.type;
				sibling = sibling.nextSibling;
				continue;
			}
			elementName = sibling.name.replace(/\(.*?\)|\[\]/g, "");
			value = sibling.value;
			if (["links", "radio", "select", "checkbox"].includes(element.type)) {
				if (elementName === LANG.GET("assemble.compose_multilist_name")) {
					setTo = Object.keys(setName).find((key) => setName[key].includes(element.type));
					if (value && setTo === "name") element.attributes.name = value;
					else if (value && setTo === "description") element.description = value;
					else return;
				}
				if (elementName === LANG.GET("assemble.compose_multilist_add_item") && value) {
					if (element.content === undefined) element.content = {};
					element.content[value] = {};
				}
			} else if (["file", "photo", "scanner", "signature", "identify"].includes(element.type)) {
				if (elementName === LANG.GET("assemble.compose_simple_element")) {
					if (value) element.attributes.name = value;
					else return;
				}
				if (elementName === LANG.GET("assemble.compose_context_identify") && sibling.checked) {
					element.attributes.required = true;
					element.attributes.multiple = false;
					element.type = "identify";
				}
			} else if (["text"].includes(element.type)) {
				if (elementName === LANG.GET("assemble.compose_text_description")) {
					if (value) element.description = value;
					else return;
				}
				if (elementName === LANG.GET("assemble.compose_text_content") && value) {
					element.content = value;
				}
			} else if (["image"].includes(element.type)) {
				if (elementName === LANG.GET("assemble.compose_image_description") && value) element.description = value;
				if (elementName === LANG.GET("assemble.compose_image") && value) {
					element.attributes = {
						name: value,
						url: URL.createObjectURL(sibling.files[0]),
					};
					// add component structure to multipart form
					const input = document.createElement("input");
					input.type = "file";
					input.name = "composedComponent_files[]";
					input.files = sibling.files;
					document.querySelector("[data-usecase=component_editor_form]").append(input);
				}
			} else {
				// ...input
				if (elementName === LANG.GET("assemble.compose_field_name")) {
					if (value) element.attributes.name = value;
					else return;
				}
			}
			if (elementName === LANG.GET("assemble.compose_field_hint") && value) element.hint = value;
			if (elementName === LANG.GET("assemble.compose_required") && sibling.checked && !("required" in element.attributes)) element.attributes.required = true;
			if (elementName === LANG.GET("assemble.compose_multiple") && sibling.checked && !("multiple" in element.attributes)) element.attributes.multiple = true;
			sibling = sibling.nextSibling;
		} while (sibling);
		if (Object.keys(element).length > 1) {
			const newElement = new Compose({
				draggable: true,
				content: [
					[structuredClone(element)], // element receives attributes from currentElement otherwise
				],
			});
			compose_helper.newFormComponents[newElement.generatedElementIDs[0]] = element;
		}
	},
	composeNewTextTemplateCallback: function (key) {
		const chunk = new Compose({
			draggable: true,
			content: [
				[
					{
						type: "text",
						description: key,
						content: texttemplateClient.data[key],
					},
				],
			],
		});
		compose_helper.newTextElements[chunk.generatedElementIDs[0]] = key;
	},

	addComponentMultipartFormToMain: function () {
		const form = document.createElement("form");
		form.style.display = "hidden";
		form.dataset.usecase = "component_editor_form";
		form.enctype = "multipart/form-data";
		form.method = "post";
		document.getElementById("main").insertAdjacentElement("afterbegin", form);
	},
	addComponentStructureToComponentForm: function (composedComponent) {
		const cc = document.querySelector("[name=composedComponent]");
		if (cc) {
			cc.value = JSON.stringify(composedComponent);
			return;
		}
		const input = document.createElement("input");
		input.type = "hidden";
		input.name = "composedComponent";
		input.value = JSON.stringify(composedComponent);
		document.querySelector("[data-usecase=component_editor_form]").append(input);
	},

	composeNewComponent: function () {
		// set dragged/dropped order of elements - wohoo, recursion!
		let isForm = false,
			componentContent = [],
			name = document.getElementById("ComponentName").value,
			hidden = document.querySelector("[data-hiddenradio]") ? document.querySelector("[data-hiddenradio]").checked : false;

		function nodechildren(parent) {
			let content = [],
				container;
			[...parent.childNodes].forEach((node) => {
				if (node.draggable) {
					container = node.children[1];
					if (container.localName === "article") {
						if (container.firstChild.localName === "section") content.push(nodechildren(container.firstChild));
						else content.push(nodechildren(container));
					} else {
						if (node.id in compose_helper.newFormComponents) {
							if (compose_helper.newFormComponents[node.id].attributes != undefined) delete compose_helper.newFormComponents[node.id].attributes["placeholder"];
							content.push(compose_helper.newFormComponents[node.id]);
							if (!["text", "links", "image"].includes(compose_helper.newFormComponents[node.id].type)) isForm = true;
						}
					}
				}
			});
			return content;
		}
		componentContent = nodechildren(document.querySelector("main"));
		const answer = {
			name: name,
			content: componentContent,
			hidden: hidden,
		};
		if (isForm) answer.form = {};
		if (name && componentContent) return answer;
		api.toast(LANG.GET("assemble.edit_component_not_saved_missing"));
		return null;
	},
	composeNewForm: function () {
		// set dragged/dropped order of elements
		const nodes = document.getElementById("main").children,
			name = document.getElementById("ComponentName").value,
			alias = document.getElementById("ComponentAlias").value,
			context = document.getElementById("ComponentContext").value,
			hidden = document.querySelector("[data-hiddenradio]") ? document.querySelector("[data-hiddenradio]").checked : false;
		let content = [];
		for (let i = 0; i < nodes.length; i++) {
			if (nodes[i].dataset && nodes[i].dataset.name) content.push(nodes[i].dataset.name);
		}
		if (name && context && content.length)
			return {
				name: name,
				alias: alias,
				context: context,
				content: content,
				hidden: hidden,
			};
		api.toast(LANG.GET("assemble.edit_form_not_saved_missing"));
		return null;
	},
	composeNewTextTemplate: function () {
		// set dragged/dropped order of elements
		const name = document.getElementById("TemplateName").value,
			language = document.getElementById("TemplateLanguage").value,
			unit = document.getElementById("TemplateUnit").value,
			hidden = document.querySelector("[data-hiddenradio]") ? document.querySelector("[data-hiddenradio]").checked : false;
		function nodechildren(parent) {
			let content = [];
			[...parent.childNodes].forEach((node) => {
				if (parent.localName === "main") {
					[...node.childNodes].forEach((div) => {
						if (div && div.draggable && div.children[1] && div.children[1].localName === "article") {
							content.push(nodechildren(div.children[1]));
						}
					});
				} else {
					if (node.id in compose_helper.newTextElements) {
						content.push(compose_helper.newTextElements[node.id]);
					}
				}
			});
			return content;
		}
		const templateContent = nodechildren(document.querySelector("main"));
		if (name && language && templateContent.length)
			return {
				name: name,
				unit: unit,
				language: language,
				content: templateContent,
				hidden: hidden,
			};
		api.toast(LANG.GET("texttemplate.edit_template_not_saved_missing"));
		return null;
	},

	importComponent: function (form) {
		compose_helper.newFormComponents = {};
		const newElements = new Compose({
			draggable: true,
			content: structuredClone(form.content),
		});
		// recursive function to assign created ids to form content elements in order of appearance
		const elementIDs = newElements.generatedElementIDs;
		let i = 0;

		function assignIDs(element) {
			for (const container of element) {
				if (container.constructor.name === "Array") {
					assignIDs(container);
				} else {
					compose_helper.newFormComponents[elementIDs[i]] = container;
					i++;
				}
			}
		}
		assignIDs(form.content);
	},
	importForm: function (components) {
		for (const component of components) {
			component.draggable = true;
			new MetaCompose(component);
			compose_helper.newFormElements.add(component.name);
		}
	},
	importTextTemplate: function (chunks) {
		compose_helper.newTextElements = {};
		for (const paragraph of chunks) {
			let texts = { content: [], keys: [] };
			for (const key of paragraph) {
				texts.content.push({
					type: "text",
					description: key,
					content: texttemplateClient.data[key],
				});
				texts.keys.push(key);
			}
			let chunk = new Compose({
				draggable: true,
				content: [structuredClone(texts.content)],
			});
			for (let i = 0; i < texts.keys.length; i++) {
				compose_helper.newTextElements[chunk.generatedElementIDs[i]] = texts.keys[i];
			}
		}
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
		drop_insert: function (evnt, droppedUpon, allowSections) {
			evnt.preventDefault();
			if (!evnt.dataTransfer.getData("text")) return;

			const draggedElement = document.getElementById(evnt.dataTransfer.getData("text")),
				draggedElementClone = draggedElement.cloneNode(true), // cloned for most likely descendant issues
				originParent = draggedElement.parentNode;
			//console.log('dragged', draggedElement.id, 'dropped on', droppedUpon.id, 'target', evnt.target);
			if (!draggedElement || this.stopParentDropEvent || draggedElement.id === droppedUpon.id) return;

			// dragging single element
			if (draggedElement.classList.contains("draggableFormElement")) {
				// dropping on single element
				if (droppedUpon.classList.contains("draggableFormElement")) {
					droppedUpon.parentNode.insertBefore(draggedElementClone, droppedUpon);
				}
				// dropping on hr creating a new article
				else if (evnt.target.localName === "hr") {
					let container = document.createElement("div"),
						article = document.createElement("article"),
						insertionArea = document.createElement("hr");
					container = compose_helper.create_draggable(container, false);
					article.append(draggedElementClone);
					insertionArea.setAttribute("ondragover", "this.classList.add('insertionAreaHover')");
					insertionArea.setAttribute("ondragleave", "this.classList.remove('insertionAreaHover')");
					insertionArea.classList.add("insertionArea");
					container.append(insertionArea, article);
					droppedUpon.parentNode.insertBefore(container, droppedUpon);
					droppedUpon.firstChild.classList.remove("insertionAreaHover");
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
			if (evnt.target.localName === "hr" || !allowSections) {
				// handle only if dropped within the reorder area
				droppedUpon.parentNode.insertBefore(draggedElementClone, droppedUpon);
				droppedUpon.firstChild.classList.remove("insertionAreaHover");
				this.stopParentDropEvent = true;
				draggedElement.remove(); // do not remove earlier! insertBefore might reference to this object by chance
				// sanitize section on lack of articles
				if (originParent.children.length < 2) {
					//                                                                                        section    article    container
					document.getElementById("main").insertBefore(originParent.children[0].cloneNode(true), originParent.parentNode.parentNode); // adapt to changes in section creation!
					originParent.parentNode.parentNode.remove();
				}
				return;
			}
			// dropping on article to create a slider
			if (
				allowSections &&
				droppedUpon.parentNode.localName === "main" &&
				draggedElement.parentNode.localName === "main" &&
				!(droppedUpon.children.item(1).firstChild.localName === "section" || draggedElement.children.item(1).firstChild.localName === "section")
			) {
				// avoid recursive multiples
				// create a multiple article tile if dropped on a tile
				let container = document.createElement("div"),
					article = document.createElement("article"),
					section = document.createElement("section"),
					insertionArea = document.createElement("hr"),
					previousSibling = droppedUpon.previousElementSibling;
				container = compose_helper.create_draggable(container, false);

				section.append(draggedElementClone, droppedUpon);
				article.append(section);
				container.append(article);

				insertionArea.setAttribute("ondragover", "this.classList.add('insertionAreaHover')");
				insertionArea.setAttribute("ondragleave", "this.classList.remove('insertionAreaHover')");
				insertionArea.classList.add("insertionArea");
				container.insertBefore(insertionArea, container.firstChild);
				previousSibling.parentNode.insertBefore(container, previousSibling.nextSibling);
				draggedElement.remove(); // do not remove earlier! inserBefore might reference to this object by chance
				return;
			}
		},
		drop_delete: function (evnt) {
			const draggedElement = document.getElementById(evnt.dataTransfer.getData("text")),
				originParent = draggedElement.parentNode;
			// sanitize article on lack of elements
			if (originParent.parentNode != document.getElementById("main") && originParent.children.length < 2) {
				originParent.parentNode.remove(); // adapt to changes in section creation!
			}
			draggedElement.remove();
		},
	},

	create_draggable: function (element, insertionArea = true, allowSections = true) {
		element.id = getNextElementID();
		element.setAttribute("draggable", "true");
		element.setAttribute("ondragstart", "compose_helper.dragNdrop.drag(event)");
		element.setAttribute("ondragover", "compose_helper.dragNdrop.allowDrop(event); this.classList.add('draggableFormElementHover')");
		element.setAttribute("ondragleave", "this.classList.remove('draggableFormElementHover')");
		element.setAttribute("ondrop", "compose_helper.dragNdrop.drop_insert(event, this, " + allowSections + "), this.classList.remove('draggableFormElementHover')");
		if (insertionArea) {
			const insertionArea = document.createElement("hr");
			insertionArea.setAttribute("ondragover", "this.classList.add('insertionAreaHover')");
			insertionArea.setAttribute("ondragleave", "this.classList.remove('insertionAreaHover')");
			insertionArea.classList.add("insertionArea");
			element.insertBefore(insertionArea, element.firstChild);
		}
		return element;
	},
	composer_add_trash: function (element) {
		element.setAttribute("ondragstart", "compose_helper.dragNdrop.drag(event)");
		element.setAttribute("ondragover", "compose_helper.dragNdrop.allowDrop(event)");
		element.setAttribute("ondrop", "compose_helper.dragNdrop.drop_delete(event)");
	},
};

export class Compose extends Assemble {
	constructor(setup) {
		super(setup);
		this.createDraggable = setup.draggable;
		this.generatedElementIDs = [];
		this.initializeSection();
		this.returnID();
	}
	returnID() {
		// a constructor must not return anything
		// idk why, but the passed object is always the whole class, no use trying to pass any individual properties...
		return this;
	}

	processPanel(elements) {
		// overriding parent method
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
		if (elements.constructor.name === "Array") {
			const section = document.createElement("section");
			section.id = getNextElementID();
			elements.forEach((element) => {
				widget = this.processPanel(element);
				if (elements[0].constructor.name === "Array") {
					// article
					const article = document.createElement("article");
					// creation form for adding elements
					if (element[0].form) {
						const form = document.createElement("form");
						form.onsubmit = () => {
							compose_helper.composeNewElementCallback(form);
						};
						form.action = "javascript:void(0);";
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
						let div = document.createElement("div");
						div = compose_helper.create_draggable(div);
						div.append(article);
						section.append(div);
					}
				} else {
					// single element, last in chain
					for (const e of widget) {
						if (e) content.push(e);
					}
				}
			});
			if (elements[0].constructor.name === "Array") content = content.concat(section, this.createDraggable ? [] : this.slider(section.id, section.childNodes.length));
		} else {
			this.currentElement = elements;
			// creation form for adding elements
			if (!this.createDraggable) {
				content = content.concat(this[this.currentElement.type]());
			}
			// element created
			else {
				let frame = document.createElement("div");
				frame.classList.add("draggableFormElement");
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
		this.content.forEach((panel) => {
			const raw_nodes = this.processPanel(panel),
				nodes = [];
			// filter undefined
			raw_nodes.forEach((node) => {
				if (node) nodes.push(node);
			});

			let frame = false;
			for (let n = 0; n < nodes.length; n++) {
				if (!(["DATALIST", "HR", "BUTTON"].includes(nodes[n].nodeName) || nodes[n].hidden)) {
					frame = true;
					break;
				}
			}
			if (frame) {
				const article = document.createElement("article");
				article.append(...nodes);
				if (this.createDraggable) {
					let container = document.createElement("div");
					container = compose_helper.create_draggable(container);
					container.append(article);
					assembledPanels.add(container);
				} else assembledPanels.add(article);
			} else assembledPanels.add(...nodes);
		});
		return assembledPanels;
	}

	compose_text() {
		let result = [];
		this.currentElement = {
			type: "text",
			attributes: {
				name: LANG.GET("assemble.compose_text_description"),
				required: true,
			},
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			type: "textarea",
			attributes: {
				name: LANG.GET("assemble.compose_text_content"),
				rows: 5,
			},
		};
		result = result.concat(...this.textarea());
		this.currentElement = {
			attributes: {
				value: LANG.GET("assemble.compose_text"),
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}

	compose_image() {
		let result = [];
		this.currentElement = {
			type: "image",
			attributes: {
				name: LANG.GET("assemble.compose_image_description"),
			},
		};
		result = result.concat(...this.textinput(), this.br());
		this.currentElement = {
			type: "photo",
			attributes: {
				name: LANG.GET("assemble.compose_image"),
			},
		};
		result = result.concat(...this.photo(), this.br());
		this.currentElement = {
			attributes: {
				value: LANG.GET("assemble.compose_image"),
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}

	compose_input(type) {
		let result = [];
		this.currentElement = {
			type: type.type,
			attributes: {
				name: LANG.GET("assemble.compose_field_name"),
				required: true,
			},
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			type: "text",
			attributes: {
				name: LANG.GET("assemble.compose_field_hint"),
			},
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			content: {},
		};
		this.currentElement.content[LANG.GET("assemble.compose_required")] = {
			name: LANG.GET("assemble.compose_required"),
		};
		result = result.concat(this.br(), ...this.checkbox());
		this.currentElement = {
			attributes: {
				value: type.description,
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}
	compose_textinput() {
		return this.compose_input({
			type: "textinput",
			description: LANG.GET("assemble.compose_textinput"),
		});
	}
	compose_numberinput() {
		return this.compose_input({
			type: "numberinput",
			description: LANG.GET("assemble.compose_numberinput"),
		});
	}
	compose_dateinput() {
		return this.compose_input({
			type: "dateinput",
			description: LANG.GET("assemble.compose_dateinput"),
		});
	}
	compose_telinput() {
		return this.compose_input({
			type: "telinput",
			description: LANG.GET("assemble.compose_telinput"),
		});
	}
	compose_emailinput() {
		return this.compose_input({
			type: "emailinput",
			description: LANG.GET("assemble.compose_emailinput"),
		});
	}
	compose_textarea() {
		return this.compose_input({
			type: "textarea",
			description: LANG.GET("assemble.compose_textarea"),
		});
	}

	compose_multilist(type) {
		let result = [];
		this.currentElement = {
			type: type.type,
			attributes: {
				name: LANG.GET("assemble.compose_multilist_name"),
				required: true,
			},
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			type: "text",
			attributes: {
				name: LANG.GET("assemble.compose_field_hint"),
			},
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			type: "textinput",
			attributes: {
				name: LANG.GET("assemble.compose_multilist_add_item") + "[]",
			},
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			attributes: {
				value: LANG.GET("assemble.compose_multilist_add_item_button"),
				"data-type": "additem",
				type: "button",
				onpointerup: cloneItems,
			},
		};
		result = result.concat(...this.button());
		if (type.required !== undefined) {
			this.currentElement = {
				content: {},
			};
			this.currentElement.content[LANG.GET("assemble.compose_required")] = {
				name: LANG.GET("assemble.compose_required"),
			};
			result = result.concat(this.br(), ...this.checkbox());
		}
		this.currentElement = {
			attributes: {
				value: type.description,
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}
	compose_select() {
		return this.compose_multilist({
			type: "select",
			description: LANG.GET("assemble.compose_select"),
			required: "optional",
		});
	}
	compose_checkbox() {
		return this.compose_multilist({
			type: "checkbox",
			description: LANG.GET("assemble.compose_checkbox"),
		});
	}
	compose_radio() {
		return this.compose_multilist({
			type: "radio",
			description: LANG.GET("assemble.compose_radio"),
			required: "optional",
		});
	}
	compose_links() {
		return this.compose_multilist({
			type: "links",
			description: LANG.GET("assemble.compose_links"),
		});
	}

	compose_simpleElement(type) {
		let result = [];
		this.currentElement = {
			type: type.type,
			attributes: {
				name: LANG.GET("assemble.compose_simple_element"),
				required: true,
			},
		};
		result = result.concat(...this.textinput());
		this.currentElement = {
			type: "text",
			attributes: {
				name: LANG.GET("assemble.compose_field_hint"),
			},
		};
		result = result.concat(...this.textinput());
		if (type.required !== undefined) {
			this.currentElement = {
				content: {},
			};
			this.currentElement.content[LANG.GET("assemble.compose_required")] = {
				name: LANG.GET("assemble.compose_required"),
			};
			result = result.concat(this.br(), ...this.checkbox());
		}

		this.currentElement = {
			type: "checkbox",
			content: {},
		};
		if (type.multiple !== undefined) {
			this.currentElement.content[LANG.GET("assemble.compose_multiple")] = {
				name: LANG.GET("assemble.compose_multiple"),
			};
		}
		if (type.type === "scanner") {
			this.currentElement.content[LANG.GET("assemble.compose_context_identify")] = {
				name: LANG.GET("assemble.compose_context_identify"),
			};
		}
		if (Object.keys(this.currentElement.content).length) result = result.concat(this.br(), ...this.checkbox());

		this.currentElement = {
			attributes: {
				value: type.description,
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}

	compose_file() {
		return this.compose_simpleElement({
			type: "file",
			description: LANG.GET("assemble.compose_file"),
			multiple: "optional",
		});
	}
	compose_photo() {
		return this.compose_simpleElement({
			type: "photo",
			description: LANG.GET("assemble.compose_photo"),
			multiple: "optional",
		});
	}
	compose_signature() {
		return this.compose_simpleElement({
			type: "signature",
			description: LANG.GET("assemble.compose_signature"),
			required: "optional",
		});
	}
	compose_scanner() {
		return this.compose_simpleElement({
			type: "scanner",
			description: LANG.GET("assemble.compose_scanner"),
			multiple: "optional",
		});
	}

	compose_component(
		std = {
			name: LANG.GET("assemble.compose_component_name"),
			description: LANG.GET("assemble.compose_component"),
			action:
				"new Dialog({type: 'confirm', header: '" +
				LANG.GET("assemble.compose_component") +
				"', options:{" +
				"'" +
				LANG.GET("assemble.compose_component_cancel") +
				"': false," +
				"'" +
				LANG.GET("assemble.compose_component_confirm") +
				"': {value: true, class: 'reducedCTA'}," +
				"}}).then(confirmation => {if (confirmation) api.form('post', 'component')})",
			hidden: {
				name: LANG.GET("assemble.edit_component_hidden"),
				hint: LANG.GET("assemble.edit_component_hidden_hint"),
			},
		}
	) {
		let result = [],
			alias = this.currentElement.alias,
			context = this.currentElement.context,
			prefilled = Boolean(this.currentElement.value),
			hidden = Boolean(this.currentElement.hidden);
		this.currentElement = {
			type: "textinput",
			hint: this.currentElement.hint,
			attributes: {
				id: "ComponentName",
				value: this.currentElement.value || "",
				name: std.name,
				required: true,
			},
		};
		result = result.concat(...this.textinput());
		if (alias) {
			this.currentElement = {
				type: "textinput",
				hint: alias.hint || null,
				attributes: {
					id: "ComponentAlias",
					value: alias.value || "",
					name: alias.name,
					required: true,
				},
			};
			result = result.concat(...this.textinput());
		}
		if (context) {
			this.currentElement = {
				type: "select",
				hint: context.hint || null,
				attributes: {
					id: "ComponentContext",
					name: context.name,
					required: true,
				},
				content: context.content,
			};
			result = result.concat(...this.select());
		}
		if (prefilled) {
			const options = {};
			options[LANG.GET("assemble.edit_component_form_hidden_visible")] = !hidden
				? {
						checked: true,
				  }
				: {};
			options[LANG.GET("assemble.edit_component_form_hidden_hidden")] = hidden
				? {
						checked: true,
						"data-hiddenradio": "ComponentHidden",
				  }
				: {
						"data-hiddenradio": "ComponentHidden",
				  };
			this.currentElement = {
				type: "radio",
				hint: std.hidden.hint,
				attributes: {
					name: std.hidden.name,
				},
				content: options,
			};
			result = result.concat(...this.radio());
		}
		this.currentElement = {
			attributes: {
				value: std.description,
				onpointerup: std.action,
				type: "button",
			},
		};
		result = result.concat(...this.submitbutton());
		return result;
	}
	compose_form() {
		return this.compose_component({
			name: LANG.GET("assemble.compose_form_label"),
			description: LANG.GET("assemble.compose_form"),
			action:
				"new Dialog({type: 'confirm', header: '" +
				LANG.GET("assemble.compose_form") +
				"', options:{" +
				"'" +
				LANG.GET("assemble.compose_form_cancel") +
				"': false," +
				"'" +
				LANG.GET("assemble.compose_form_confirm") +
				"': {value: true, class: 'reducedCTA'}," +
				"}}).then(confirmation => {if (confirmation) api.form('post', 'form')})",
			hidden: {
				name: LANG.GET("assemble.edit_form_hidden"),
				hint: LANG.GET("assemble.edit_form_hidden_hint"),
			},
		});
	}
}

export class MetaCompose extends Assemble {
	constructor(setup) {
		delete setup.form;
		super(setup);
		this.initializeSection();
		if (setup.draggable) compose_helper.create_draggable(this.section, true, false);
		this.section.setAttribute("data-name", setup.name);
	}
}

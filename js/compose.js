/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2024 error on line 1 (dev@erroronline.one)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/*
this module helps to compose and edit forms according to the passed simplified object notation. it makes use of the assemble library.
*/
import { getNextElementID, Assemble } from "./assemble.js";

export const compose_helper = {
	/**
	 * e.g. adding new input fields for link collections
	 * @param {object} startNode
	 * @param {int} offset
	 * @param {int} numberOfElements
	 * @returns nodes
	 */
	cloneMultipleItems: function (startNode, offset = -4, numberOfElements = 4) {
		// positive offset for nodes after fromNode, negative for previous nodes
		// ids and htmlfor are only assigned within first layer !!!

		//set pointer
		for (let i = 0 || offset; i < (offset < 0 ? 0 : offset); i++) {
			if (offset < 0) startNode = startNode.previousElementSibling;
			if (offset > 0) startNode = startNode.nextElementSibling;
		}

		const elements = [],
			clonedIds = {};
		for (let i = 0; i < numberOfElements; i++) {
			let clone = startNode.cloneNode(true);
			if ("value" in clone) clone.value = "";
			if ("id" in clone && clone.id) {
				clonedIds[clone.id] = compose_helper.getNextElementID();
				clone.id = clonedIds[clone.id];
			}
			if ("htmlFor" in clone && Object.keys(clonedIds).includes(clone.htmlFor)) {
				// does work only if label comes after input
				clone.htmlFor = clonedIds[clone.htmlFor];
			}
			elements.push(clone);
			startNode = startNode.nextElementSibling;
		}
		return elements;
	},
	newFormComponents: {},
	newFormElements: new Set(),
	newTextElements: {},
	componentIdentify: 0,
	componentSignature: 0,
	getNextElementID: getNextElementID,

	/**
	 * create widget from composer and append to view and newFormComponents
	 * @param {object} parent
	 * @returns none
	 */
	composeNewElementCallback: function (parent) {
		let sibling = parent.childNodes[0].nextSibling,
			setTo,
			elementName,
			value;
		const setName = {
				name: ["select", "scanner", "radio", "photo", "file", "signature"],
				description: ["links", "checkbox", "textblock"],
			},
			buttonValues = {
				calendarbutton: LANG.GET("planning.event_new"),
			},
			element = {
				attributes: {},
			};
		do {
			if (!["span", "input", "textarea", "header", "select"].includes(sibling.localName)) {
				sibling = sibling.nextSibling;
				continue;
			}
			if (sibling.localName === "span") {
				if (element.type === undefined) element["type"] = sibling.dataset.type;
				sibling = sibling.nextSibling;
				continue;
			}
			if (sibling.localName === "header") {
				if (element.type === undefined) element["type"] = sibling.dataset.type;
				if (!element.attributes) element["attributes"] = { value: buttonValues[sibling.dataset.type] };
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
					document.getElementById("setIdentify").disabled = true;
					document.getElementById("setIdentify").checked = false;
				}
				if (element.type === "signature") {
					const signatureform = document.querySelector("form>span[data-type=signature").parentNode;
					for (const node of signatureform) {
						if (node.dataset.type === "addblock") {
							node.disabled = true;
							compose_helper.componentSignature++;
						}
					}
				}
			} else if (["textblock"].includes(element.type)) {
				if (elementName === LANG.GET("assemble.compose_textblock_description")) {
					if (value) element.description = value;
					else return;
				}
				if (elementName === LANG.GET("assemble.compose_textblock_content") && value) {
					element.content = value;
				}
			} else if (["formbutton"].includes(element.type)) {
				if (elementName === LANG.GET("assemble.compose_link_form_select")) {
					if (value) element.attributes.value = value;
					else return;
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
			} else if (["range"].includes(element.type)) {
				if (elementName === LANG.GET("assemble.compose_simple_element")) {
					if (value) element.attributes.name = value;
					else return;
				}
				element.attributes.value = "0";
				if (elementName === LANG.GET("assemble.compose_range_min")) element.attributes.min = Number(value);
				if (elementName === LANG.GET("assemble.compose_range_max")) element.attributes.max = Number(value);
				if (elementName === LANG.GET("assemble.compose_range_step")) {
					value = value.replace(",", ".");
					if ((typeof Number(value) === "number" && Number(value) < element.attributes.max) || value === "any") element.attributes.step = value;
				}
			} else {
				// ...input
				if (elementName === LANG.GET("assemble.compose_field_name")) {
					if (value) element.attributes.name = value;
					else return;
				}
			}
			if (elementName === LANG.GET("assemble.compose_texttemplate") && value) element.texttemplates = true;
			if (elementName === LANG.GET("assemble.compose_field_hint") && value) element.hint = value;
			if (elementName === LANG.GET("assemble.compose_required") && sibling.checked && !("required" in element.attributes)) element.attributes.required = true;
			if (elementName === LANG.GET("assemble.compose_multiple") && sibling.checked && !("multiple" in element.attributes)) element.attributes.multiple = true;
			if (elementName === LANG.GET("assemble.compose_link_form_choice") && value === LANG.GET("assemble.compose_link_form_display") && sibling.checked) {
				element.attributes.onpointerup = "api.record('get','displayonly', '" + element.attributes.value + "')";
				element.attributes.value = LANG.GET("assemble.compose_link_form_display_button", { ":form": element.attributes.value });
			}
			if (elementName === LANG.GET("assemble.compose_link_form_choice") && value === LANG.GET("assemble.compose_link_form_continue") && sibling.checked) {
				element.attributes.onpointerup = "api.record('get','form', '" + element.attributes.value + "', document.querySelector('input[name^=IDENTIFY_BY_]') ? document.querySelector('input[name^=IDENTIFY_BY_]').value : null)";
				element.attributes.value = LANG.GET("assemble.compose_link_form_continue_button", { ":form": element.attributes.value });
			}
			sibling = sibling.nextSibling;
		} while (sibling);
		if (Object.keys(element).length > 1) {
			const newElement = new Compose({
				draggable: true,
				content: [
					[structuredClone(element)], // element receives attributes from currentElement otherwise
				],
			});
			document.getElementById("main").append(...newElement.initializeSection());
			newElement.processAfterInsertion();
			compose_helper.newFormComponents[newElement.generatedElementIDs[0]] = element;
		}
	},

	/**
	 * append new text chunk to view and newTextElements. used by texttemplate.php composer
	 * @param {string} key
	 */
	composeNewTextTemplateCallback: function (key) {
		const chunk = new Compose({
			draggable: true,
			allowSections: false,
			content: [
				[
					{
						type: "textblock",
						description: key,
						content: _client.texttemplate.data[key],
					},
				],
			],
		});
		document.getElementById("main").append(...chunk.initializeSection());
		chunk.processAfterInsertion();
		compose_helper.newTextElements[chunk.generatedElementIDs[0]] = key;
	},

	/**
	 * add multipart form for component editor for file uploads. used by api.js
	 */
	addComponentMultipartFormToMain: function () {
		const form = document.createElement("form");
		form.style.display = "hidden";
		form.dataset.usecase = "component_editor_form";
		form.enctype = "multipart/form-data";
		form.method = "post";
		document.getElementById("main").insertAdjacentElement("afterbegin", form);
	},
	/**
	 * appends or updates a hidden form fiels with the components json structure to the editor form. used by api.js
	 * @param {object} composedComponent
	 * @returns none
	 */
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

	/**
	 * creates a component by comparing the contents of newFormComponents and the actual order from view (after reordering/dragging)
	 * @returns object|null
	 */
	composeNewComponent: function () {
		// set dragged/dropped order of elements - wohoo, recursion!
		let isForm = false,
			componentContent = [],
			name = document.getElementById("ComponentName").value,
			approve = document.getElementById("ComponentApprove").value,
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
			approve: approve,
		};
		if (isForm) answer.form = {};
		if (name && componentContent && approve && approve !== "0") return answer;
		new Toast(LANG.GET("assemble.edit_component_not_saved_missing"), "error");
		return null;
	},

	/**
	 * creates a form fetching the actual order of components (names) from view
	 * @returns object|null
	 */
	composeNewForm: function () {
		// set dragged/dropped order of elements
		const nodes = document.getElementById("main").children,
			name = document.getElementById("ComponentName").value,
			alias = document.getElementById("ComponentAlias").value,
			context = document.getElementById("ComponentContext").value,
			approve = document.getElementById("ComponentApprove").value,
			regulatory_context = document.getElementById("ComponentRegulatoryContext").value,
			restricted_access = document.getElementById("ComponentRestrictedAccess").value,
			hidden = document.querySelector("[data-hiddenradio]") ? document.querySelector("[data-hiddenradio]").checked : false,
			permitted_export = document.getElementById("ComponentPermittedExport") ? document.getElementById("ComponentPermittedExport").checked : false;
		let content = [];
		for (let i = 0; i < nodes.length; i++) {
			if (nodes[i].dataset && nodes[i].dataset.name) content.push(nodes[i].dataset.name);
		}
		if (name && context && context !== "0" && content.length)
			return {
				name: name,
				alias: alias,
				context: context,
				content: content,
				hidden: hidden,
				approve: approve,
				regulatory_context: regulatory_context,
				permitted_export: permitted_export,
				restricted_access: restricted_access,
			};
		new Toast(LANG.GET("assemble.edit_form_not_saved_missing"), "error");
		return null;
	},

	/**
	 * creates a text template by comparing the contents of newTextElements and the actual order from view (after reordering/dragging)
	 * @returns object|null
	 */
	composeNewTextTemplate: function () {
		// set dragged/dropped order of elements
		const name = document.getElementById("TemplateName").value,
			language = document.getElementById("TemplateLanguage").value,
			unit = document.getElementById("TemplateUnit").value,
			hidden = document.querySelector("[data-hiddenradio]") ? document.querySelector("[data-hiddenradio]").checked : false;
		function nodechildren(parent) {
			let content = [];
			[...parent.childNodes].forEach((node) => {
				if (node.draggable && node.children.item(1) && node.children.item(1).localName === "article") {
					[...node.childNodes].forEach((element) => {
						if (element.localName === "article") {
							content.push(nodechildren(element));
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
		new Toast(LANG.GET("assemble.edit_template_not_saved_missing"), "error");
		return null;
	},

	/**
	 * appends a component to view and newFormComponents after being fetched by api.js
	 * @param {object} form
	 */
	importComponent: function (form) {
		compose_helper.newFormComponents = {};
		const newElements = new Compose({
			draggable: true,
			content: structuredClone(form.content),
		});
		document.getElementById("main").append(...newElements.initializeSection());
		newElements.processAfterInsertion();
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

	/**
	 * appends components to view and newFormElements after being fetched by api.js
	 * @param {object} form
	 */
	importForm: function (components) {
		function lookupIdentify(element) {
			for (const container of element) {
				if (container.constructor.name === "Array") {
					lookupIdentify(container);
				} else {
					if (container.type === "identify") compose_helper.componentIdentify++;
					if (container.type === "signature") compose_helper.componentSignature++;
				}
			}
		}
		for (const component of components) {
			component.draggable = true;
			let current = new MetaCompose(component);
			document.getElementById("main").append(current.initializeSection());
			current.processAfterInsertion2();
			compose_helper.newFormElements.add(component.name);
			lookupIdentify(current.content);
		}
		if (compose_helper.componentIdentify > 1) new Toast(LANG.GET("assemble.compose_form_multiple_identify"), "error");
		if (compose_helper.componentSignature > 1) new Toast(LANG.GET("assemble.compose_form_multiple_signature"), "error");
	},

	/**
	 * appends text chunks to view and newTextElements after being fetched by api.js
	 * @param {object} form
	 */
	importTextTemplate: function (chunks) {
		compose_helper.newTextElements = {};
		for (const paragraph of chunks) {
			let texts = { content: [], keys: [] };
			for (const key of paragraph) {
				texts.content.push({
					type: "textblock",
					description: key,
					content: _client.texttemplate.data[key],
				});
				texts.keys.push(key);
			}
			let chunk = new Compose({
				draggable: true,
				allowSections: false,
				content: [structuredClone(texts.content)],
			});
			document.getElementById("main").append(...chunk.initializeSection());
			chunk.processAfterInsertion();
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
		/**
		 * inserts widgets or section before dropped upon widget or section
		 */
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
			if (evnt.target.localName === "hr" && !(evnt.target.parentNode.parentNode.localName === "section" && draggedElement.children.item(1) && draggedElement.children.item(1).firstChild.localName === "section")) {
				// no section insertion
				// handle only if dropped within the reorder area				console.log('hello');
				droppedUpon.parentNode.insertBefore(draggedElementClone, droppedUpon);
				droppedUpon.firstChild.classList.remove("insertionAreaHover");
				this.stopParentDropEvent = true;
				draggedElement.remove(); // do not remove earlier! insertBefore might reference to this object by chance
				// sanitize section on lack of articles
				if (originParent.children.length < 2) {
					if (originParent.children.length > 0)
						//    section  article    draggable div                                                                  section    article    container
						originParent.parentNode.parentNode.parentNode.insertBefore(originParent.children[0].cloneNode(true), originParent.parentNode.parentNode); // adapt to changes in section creation!
					originParent.parentNode.parentNode.remove();
				}
				return;
			}
			// dropping on article to create a slider
			if (
				allowSections &&
				!(draggedElement.children.item(1).firstChild && draggedElement.children.item(1).firstChild.localName === "section") &&
				!(droppedUpon.children.item(1).firstChild && droppedUpon.children.item(1).firstChild.localName === "section") &&
				!(droppedUpon.parentNode.localName === "section") &&
				!draggedElement.classList.contains("draggableFormElement") &&
				!droppedUpon.classList.contains("draggableFormElement")
			) {
				// avoid recursive multiples
				// create a multiple article tile if dropped on a tile
				let container = document.createElement("div"),
					article = document.createElement("article"),
					section = document.createElement("section"),
					insertionArea = document.createElement("hr"),
					insertbefore = droppedUpon.nextElementSibling;
				container = compose_helper.create_draggable(container, false, false);

				section.append(draggedElementClone, droppedUpon);
				article.append(section);
				container.append(article);
				insertionArea.setAttribute("ondragover", "this.classList.add('insertionAreaHover')");
				insertionArea.setAttribute("ondragleave", "this.classList.remove('insertionAreaHover')");
				insertionArea.classList.add("insertionArea");
				container.insertBefore(insertionArea, container.firstChild);

				if (insertbefore) insertbefore.parentNode.insertBefore(container, insertbefore);
				else draggedElement.parentNode.insertAdjacentElement("beforeend", container);
				draggedElement.remove(); // do not remove earlier! inserBefore might reference to this object by chance
				return;
			}
		},
		/**
		 * deletes widget or section if dropped on deletion area
		 */
		drop_delete: function (evnt) {
			const draggedElement = document.getElementById(evnt.dataTransfer.getData("text")),
				originParent = draggedElement.parentNode;
			// sanitize article on lack of elements
			if (originParent.parentNode != document.getElementById("main") && originParent.children.length < 2) {
				originParent.parentNode.remove(); // adapt to changes in section creation!
			}
			// enable identifier if previously constructed had been deleted
			function nodechildren(parent) {
				[...parent.childNodes].forEach((node) => {
					if (["article", "div"].includes(node.localName)) {
						if (node.firstChild.localName === "section") nodechildren(node.firstChild);
						else nodechildren(node);
					} else {
						if (node.name && node.name.match(/IDENTIFY_BY_/g)) {
							if (document.getElementById("setIdentify")) document.getElementById("setIdentify").disabled = false;
							compose_helper.componentIdentify--;
						}
						if (node.id && node.id === "signaturecanvas") {
							const signatureformsibling = document.querySelector("form>span[data-type=signature");
							if (signatureformsibling)
								for (const node of signatureformsibling.parentNode) {
									if (node.dataset.type === "addblock") node.disabled = false;
								}
							compose_helper.componentSignature--;
						}
					}
				});
			}
			nodechildren(draggedElement);
			draggedElement.remove();
		},
		/**
		 * populates the respective components editor forms with widgets settings
		 */
		drop_reimport: function (evnt) {
			// this doesn't make sense for some types but idc. actually impossible for image type, so this is a exit case
			const draggedElement = document.getElementById(evnt.dataTransfer.getData("text"));
			let targetform = evnt.target;
			if (targetform.constructor.name !== "HTMLFormElement") targetform = targetform.parentNode;
			const targettype = targetform.children[1].dataset.type;
			if (Object.keys(compose_helper.newFormComponents).includes(draggedElement.id)) {
				const importable = compose_helper.newFormComponents[draggedElement.id];

				if (["image"].includes(importable.type)) return;

				if (importable.type === targettype || (importable.type === "identify" && targettype === "scanner")) {
					// actual inverse compose_helper.composeNewElementCallback

					let sibling = targetform.childNodes[0].nextSibling,
						setTo,
						elementName;
					const setName = {
						name: ["select", "scanner", "radio", "photo", "file", "signature"],
						description: ["links", "checkbox"],
					};
					do {
						if (!["input", "textarea"].includes(sibling.localName)) {
							sibling = sibling.nextSibling;
							continue;
						}
						elementName = sibling.name.replace(/\(.*?\)|\[\]/g, "");
						if (elementName === LANG.GET("assemble.compose_texttemplate") && importable.texttemplates) sibling.checked = true;
						if (elementName === LANG.GET("assemble.compose_field_hint") && importable.hint) sibling.value = importable.hint;
						if (elementName === LANG.GET("assemble.compose_required") && "required" in importable.attributes && importable.attributes.required) sibling.checked = true;
						if (elementName === LANG.GET("assemble.compose_multiple") && "multiple" in importable.attributes && importable.attributes.multiple) sibling.checked = true;
						if (elementName === LANG.GET("assemble.compose_context_identify") && importable.type === "identify") sibling.checked = true;

						if (["links", "radio", "select", "checkbox"].includes(importable.type)) {
							if (elementName === LANG.GET("assemble.compose_multilist_name")) {
								setTo = Object.keys(setName).find((key) => setName[key].includes(importable.type));
								if (setTo === "name") sibling.value = importable.attributes.name;
								else if (setTo === "description") sibling.value = importable.description;
							}
							if (elementName === LANG.GET("assemble.compose_multilist_add_item")) {
								let deletesibling = sibling.nextSibling;
								while (deletesibling.nextElementSibling.constructor.name !== "HTMLButtonElement") {
									deletesibling.nextElementSibling.remove();
								}
								const options = Object.keys(importable.content);
								sibling.value = options[0];
								let next = [],
									all = [];
								for (let i = 1; i < options.length; i++) {
									next = compose_helper.cloneMultipleItems(sibling, -2, 4);
									for (const e of next) {
										if ("value" in e) e.value = options[i];
									}
									all = all.concat(next);
								}
								sibling.nextSibling.after(...all);
								// set pointer to avoid overflow
								for (let i = 0; i < all.length + 1; i++) sibling = sibling.nextSibling;
								continue;
							}
						} else if (["file", "photo", "scanner", "signature", "identify"].includes(importable.type)) {
							if (elementName === LANG.GET("assemble.compose_simple_element")) sibling.value = importable.attributes.name;
						} else if (["textblock"].includes(importable.type)) {
							if (elementName === LANG.GET("assemble.compose_textblock_description")) sibling.value = importable.description;
							if (elementName === LANG.GET("assemble.compose_textblock_content")) if (importable.content) sibling.value = importable.content;
						} else if (["range"].includes(importable.type)) {
							if (elementName === LANG.GET("assemble.compose_simple_element")) sibling.value = importable.attributes.name;
							if (elementName === LANG.GET("assemble.compose_range_min")) sibling.value = importable.attributes.min;
							if (elementName === LANG.GET("assemble.compose_range_max")) sibling.value = importable.attributes.max;
							if (elementName === LANG.GET("assemble.compose_range_step")) sibling.value = importable.attributes.step;
						} else {
							// ...input
							if (elementName === LANG.GET("assemble.compose_field_name")) {
								sibling.value = importable.attributes.name;
							}
						}
						sibling = sibling.nextSibling;
					} while (sibling);
				}
			}
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
	composer_component_form_reimportable: function (element) {
		element.setAttribute("ondragstart", "compose_helper.dragNdrop.drag(event)");
		element.setAttribute("ondragover", "compose_helper.dragNdrop.allowDrop(event)");
		element.setAttribute("ondrop", "compose_helper.dragNdrop.drop_reimport(event)");
	},
};

export class Compose extends Assemble {
	constructor(setup) {
		super(setup);
		this.composer = this.createDraggable = setup.draggable;
		this.allowSections = "allowSections" in setup ? setup.allowSections : undefined;
		this.generatedElementIDs = [];
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
					// composer creation form for adding elements
					if (element[0].form) {
						const form = document.createElement("form");
						compose_helper.composer_component_form_reimportable(form);
						form.onsubmit = () => {
							compose_helper.composeNewElementCallback(form);
						};
						form.action = "javascript:void(0);";
						for (const e of widget) {
							if (e) form.append(e);
						}
						article.append(form);
					}
					// imported component element
					else {
						for (const e of widget) {
							if (e) article.append(e);
						}
					}
					// composer creation form for adding elements
					if (!this.createDraggable) {
						section.append(article);
					}
					// imported component element
					else {
						let div = document.createElement("div");
						div = compose_helper.create_draggable(div, undefined, this.allowSections);
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
			// actual element created
			else {
				let frame = document.createElement("div");
				frame.classList.add("draggableFormElement");
				frame.append(...this[this.currentElement.type]());
				frame = compose_helper.create_draggable(frame, false, this.allowSections);
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
					container = compose_helper.create_draggable(container, undefined, this.allowSections);
					container.append(article);
					assembledPanels.add(container);
				} else assembledPanels.add(article);
			} else assembledPanels.add(...nodes);
		});
		return assembledPanels;
	}

	/**
	 *         _   _         _
	 *   _ _ _|_|_| |___ ___| |_ ___
	 *  | | | | | . | . | -_|  _|_ -|
	 *  |_____|_|___|_  |___|_| |___|
	 *              |___|
	 *
	 */

	compose_calendarbutton() {
		let result = [this.br()];
		this.currentElement = {
			type: "textblock",
			attributes: {
				"data-type": "calendarbutton",
			},
			description: LANG.GET("assemble.compose_calendarbutton"),
			content: LANG.GET("assemble.compose_calendarbutton_not_working"),
		};
		result = result.concat(...this.textblock());
		this.currentElement = {
			attributes: {
				value: LANG.GET("assemble.compose_calendarbutton"),
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}

	compose_checkbox() {
		return this.compose_multilist({
			type: "checkbox",
			description: LANG.GET("assemble.compose_checkbox"),
		});
	}

	compose_component(
		std = {
			name: LANG.GET("assemble.compose_component_name"),
			description: LANG.GET("assemble.compose_component"),
			list: "components",
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
			hidden = Boolean(this.currentElement.hidden),
			approve = this.currentElement.approve,
			regulatory_context = this.currentElement.regulatory_context,
			permitted_export = this.currentElement.permitted_export,
			restricted_access = this.currentElement.restricted_access;
		this.currentElement = {
			type: "text",
			hint: this.currentElement.hint,
			attributes: {
				id: "ComponentName",
				value: this.currentElement.value || "",
				name: std.name,
				required: true,
				list: std.list,
			},
		};
		result = result.concat(...this.text());
		if (alias) {
			this.currentElement = {
				type: "text",
				hint: alias.hint || null,
				attributes: {
					id: "ComponentAlias",
					value: alias.value || "",
					name: alias.name,
					required: true,
				},
			};
			result = result.concat(...this.text());
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
		if (permitted_export) {
			permitted_export.content[Object.keys(permitted_export.content)[0]]["id"] = "ComponentPermittedExport";
			this.currentElement = {
				type: "checkbox",
				hint: permitted_export.hint,
				content: permitted_export.content,
			};
			result = result.concat(...this.checkbox());
		}
		if (restricted_access) {
			this.currentElement = {
				type: "checkbox2text",
				content: restricted_access.content,
				attributes: {
					name: LANG.GET("assemble.edit_form_restricted_access"),
					id: "ComponentRestrictedAccess",
				},
				hint: restricted_access.hint,
			};
			result = result.concat(...this.checkbox2text());
		}
		if (regulatory_context) {
			this.currentElement = {
				type: "checkbox2text",
				content: regulatory_context,
				attributes: {
					name: LANG.GET("assemble.compose_form_regulatory_context"),
					id: "ComponentRegulatoryContext",
				},
			};
			result = result.concat(...this.checkbox2text());
		}
		if (approve) {
			this.currentElement = {
				type: "select",
				hint: approve.hint || null,
				attributes: {
					id: "ComponentApprove",
					name: approve.name,
					required: true,
				},
				content: approve.content,
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

	compose_date() {
		return this.compose_input({
			type: "date",
			description: LANG.GET("assemble.compose_date"),
		});
	}

	compose_email() {
		return this.compose_input({
			type: "email",
			description: LANG.GET("assemble.compose_email"),
		});
	}

	compose_file() {
		return this.compose_simpleElement({
			type: "file",
			description: LANG.GET("assemble.compose_file"),
			multiple: "optional",
		});
	}

	compose_form() {
		return this.compose_component({
			name: LANG.GET("assemble.compose_form_label"),
			description: LANG.GET("assemble.compose_form"),
			list: "forms",
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

	compose_formbutton() {
		let result = [this.br()],
			forms = this.currentElement.content,
			options = {};

		this.currentElement = {
			type: "textblock",
			attributes: {
				"data-type": "formbutton",
			},
			description: LANG.GET("assemble.compose_link_form"),
		};
		result = result.concat(...this.textblock());

		this.currentElement = {
			type: "select",
			attributes: {
				name: LANG.GET("assemble.compose_link_form_select"),
			},
			content: forms,
		};
		result = result.concat(...this.select());

		this.currentElement = {
			type: "radio",
			attributes: { name: LANG.GET("assemble.compose_link_form_choice") },
			content: {},
		};
		this.currentElement.content[LANG.GET("assemble.compose_link_form_display")] = {
			required: true,
		};
		this.currentElement.content[LANG.GET("assemble.compose_link_form_continue")] = {
			required: true,
		};
		result = result.concat(this.br(), ...this.radio());

		this.currentElement = {
			attributes: {
				value: LANG.GET("assemble.compose_link_form"),
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
		result = result.concat(...this.text(), this.br());
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
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textblock",
			attributes: {
				name: LANG.GET("assemble.compose_field_hint"),
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			content: {},
		};
		this.currentElement.content[LANG.GET("assemble.compose_required")] = {
			name: LANG.GET("assemble.compose_required"),
		};
		if (type.type === "productselection")
			this.currentElement.content[LANG.GET("assemble.compose_multiple")] = {
				name: LANG.GET("assemble.compose_multiple"),
			};
		if (type.type === "textarea")
			this.currentElement.content[LANG.GET("assemble.compose_texttemplate")] = {
				name: LANG.GET("assemble.compose_texttemplate"),
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

	compose_links() {
		return this.compose_multilist({
			type: "links",
			description: LANG.GET("assemble.compose_links"),
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
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textblock",
			attributes: {
				name: LANG.GET("assemble.compose_field_hint"),
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "text",
			attributes: {
				name: LANG.GET("assemble.compose_multilist_add_item") + "[]",
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			attributes: {
				value: LANG.GET("assemble.compose_multilist_add_item_button"),
				"data-type": "additem",
				type: "button",
				onpointerup: "for (const e of compose_helper.cloneMultipleItems(this, -4, 4)) this.parentNode.insertBefore(e, this);",
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

	compose_number() {
		return this.compose_input({
			type: "number",
			description: LANG.GET("assemble.compose_number"),
		});
	}

	compose_photo() {
		return this.compose_simpleElement({
			type: "photo",
			description: LANG.GET("assemble.compose_photo"),
			multiple: "optional",
		});
	}

	compose_productselection() {
		return this.compose_input({
			type: "productselection",
			description: LANG.GET("assemble.compose_productselection"),
		});
	}

	compose_radio() {
		return this.compose_multilist({
			type: "radio",
			description: LANG.GET("assemble.compose_radio"),
			required: "optional",
		});
	}

	compose_range() {
		let result = [];
		this.currentElement = {
			type: "range",
			attributes: {
				name: LANG.GET("assemble.compose_simple_element"),
				required: true,
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textblock",
			attributes: {
				name: LANG.GET("assemble.compose_field_hint"),
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "number",
			attributes: {
				name: LANG.GET("assemble.compose_range_min"),
			},
			hint: LANG.GET("assemble.compose_range_min_hint"),
		};
		result = result.concat(...this.number());
		this.currentElement = {
			type: "number",
			attributes: {
				name: LANG.GET("assemble.compose_range_max"),
			},
			hint: LANG.GET("assemble.compose_range_max_hint"),
		};
		result = result.concat(...this.number());
		this.currentElement = {
			type: "text",
			attributes: {
				name: LANG.GET("assemble.compose_range_step"),
			},
			hint: LANG.GET("assemble.compose_range_step_hint"),
		};
		result = result.concat(...this.text());

		this.currentElement = {
			attributes: {
				value: LANG.GET("assemble.compose_range"),
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}

	compose_raw() {
		let result = [];
		this.currentElement = {
			type: "code",
			hint: LANG.GET("assemble.compose_raw_hint"),
			attributes: {
				name: LANG.GET("assemble.compose_raw"),
				id: "_compose_raw",
			},
		};
		result = result.concat(...this.code());
		this.currentElement = {
			attributes: {
				value: LANG.GET("assemble.compose_raw"),
				"data-type": "addblock",
				onpointerup:
					"if (document.getElementById('_compose_raw').value) try {compose_helper.importComponent({content:JSON.parse(document.getElementById('_compose_raw').value)})} catch(e){ new Dialog({type:'alert', header:'" +
					LANG.GET("assemble.compose_raw") +
					"', render:'" +
					LANG.GET("assemble.compose_raw_json_error") +
					"'})}",
			},
		};
		result = result.concat(...this.button());
		return result;
	}

	compose_scanner() {
		return this.compose_simpleElement({
			type: "scanner",
			description: LANG.GET("assemble.compose_scanner"),
			multiple: "optional",
		});
	}

	compose_select() {
		return this.compose_multilist({
			type: "select",
			description: LANG.GET("assemble.compose_select"),
			required: "optional",
		});
	}

	compose_signature() {
		return this.compose_simpleElement({
			type: "signature",
			description: LANG.GET("assemble.compose_signature"),
			required: "optional",
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
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textblock",
			attributes: {
				name: LANG.GET("assemble.compose_field_hint"),
			},
		};
		result = result.concat(...this.text());
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
				id: "setIdentify",
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

	compose_tel() {
		return this.compose_input({
			type: "tel",
			description: LANG.GET("assemble.compose_tel"),
		});
	}

	compose_text() {
		return this.compose_input({
			type: "text",
			description: LANG.GET("assemble.compose_text"),
		});
	}

	compose_textarea() {
		return this.compose_input({
			type: "textarea",
			description: LANG.GET("assemble.compose_textarea"),
		});
	}

	compose_textblock() {
		let result = [];
		this.currentElement = {
			type: "textblock",
			attributes: {
				name: LANG.GET("assemble.compose_textblock_description"),
				required: true,
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textarea",
			attributes: {
				name: LANG.GET("assemble.compose_textblock_content"),
				rows: 5,
			},
		};
		result = result.concat(...this.textarea());
		this.currentElement = {
			attributes: {
				value: LANG.GET("assemble.compose_textblock"),
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}
}

export class MetaCompose extends Assemble {
	constructor(setup) {
		delete setup.form;
		super(setup);
		this.setup = setup;
	}
	processAfterInsertion2() {
		this.processAfterInsertion();
		if (this.setup.draggable) compose_helper.create_draggable(this.section, true, false);
		this.section.setAttribute("data-name", this.setup.name);
		if (this.setup.hidden) this.section.classList.add("hiddencomponent");
	}
}

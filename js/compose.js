/**
 * CARO - Cloud Assisted Records and Operations
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
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
this module helps to compose and edit documents according to the passed simplified object notation. it makes use of the assemble library.
*/
import { getNextElementID, Assemble } from "./assemble.js";

export const compose_helper = {
	/**
	 * e.g. adding new input fields for link collections
	 * @param {object} startNode
	 * @param {int} offset
	 * @param {int} numberOfElements
	 * @returns {domNodes}]
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
			if ("required" in clone) clone.removeAttribute("required");
			if ("htmlFor" in clone && Object.keys(clonedIds).includes(clone.htmlFor)) {
				// does work only if label comes after input
				clone.htmlFor = clonedIds[clone.htmlFor];
			}
			elements.push(clone);
			startNode = startNode.nextElementSibling;
		}
		return elements;
	},
	newDocumentComponents: {},
	newDocumentElements: new Set(),
	newTextElements: {},
	componentIdentify: 0,
	componentSignature: 0,
	getNextElementID: getNextElementID,

	/**
	 * create widget from composer and append to view and newDocumentComponents
	 * @requires Compose
	 * @param {domNode} parent composer form for widget
	 * @event append widget to view
	 * @event add widget structure to this.newDocumentComponents
	 */
	composeNewElementCallback: function (parent) {
		let sibling = parent.childNodes[0].nextSibling,
			setTo,
			elementName,
			value;

		// define which attribute is assigned the name value, and set default values
		const setName = {
				name: ["select", "scanner", "radio", "photo", "file", "signature", "textselection", "checkbox"],
				description: ["links"],
			},
			buttonValues = {
				calendarbutton: api._lang.GET("planning.event_new"),
			},
			element = {
				attributes: {},
			};

		// iterate over all nodes of the respective widgets composer form
		do {
			// skip nodes
			if (!["span", "input", "textarea", "header", "select"].includes(sibling.localName)) {
				sibling = sibling.nextSibling;
				continue;
			}

			// get widget type by icon
			if (sibling.localName === "span") {
				if (element.type === undefined) element["type"] = sibling.dataset.type;
				sibling = sibling.nextSibling;
				continue;
			}

			// get widget type by header
			if (sibling.localName === "header") {
				if (element.type === undefined) element["type"] = sibling.dataset.type;
				if (!element.attributes) element["attributes"] = { value: buttonValues[sibling.dataset.type] };
				sibling = sibling.nextSibling;
				continue;
			}

			// sanitize widget name
			elementName = sibling.name.replace(/\(.*?\)|\[\]/g, "");

			// route values to names, values and attributes
			value = sibling.value;
			if (["links", "radio", "select", "checkbox"].includes(element.type)) {
				if (elementName === api._lang.GET("assemble.compose.component.multilist_name")) {
					setTo = Object.keys(setName).find((key) => setName[key].includes(element.type));
					if (value && setTo === "name") element.attributes.name = value;
					else if (value && setTo === "description") element.description = value;
					else return;
				}
				if (elementName === api._lang.GET("assemble.compose.component.multilist_add_item") && value) {
					if (element.content === undefined) element.content = {};
					element.content[value] = {};
				}
			} else if (["file", "photo", "scanner", "signature", "identify"].includes(element.type)) {
				if (elementName === api._lang.GET("assemble.compose.component.simple_element")) {
					if (value) element.attributes.name = value;
					else return;
				}
				if (elementName === api._lang.GET("assemble.compose.component.context_identify") && sibling.checked) {
					element.attributes.required = true;
					delete element.attributes.multiple;
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
			} else if (["textsection"].includes(element.type)) {
				if (elementName === api._lang.GET("assemble.compose.component.textsection_description")) {
					if (value) element.attributes.name = value;
					else return;
				}
				if (elementName === api._lang.GET("assemble.compose.component.textsection_content") && value) {
					element.content = value;
				}
			} else if (["documentbutton"].includes(element.type)) {
				if (elementName === api._lang.GET("assemble.compose.component.link_document_select")) {
					if (value) element.attributes.value = value;
					else return;
				}
			} else if (["image"].includes(element.type)) {
				if (elementName === api._lang.GET("assemble.compose.component.image_description")) {
					if (value) element.description = value;
				}
				if (elementName === api._lang.GET("assemble.compose.component.image")) {
					if (value) {
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
					} else return;
				}
			} else if (["range"].includes(element.type)) {
				if (elementName === api._lang.GET("assemble.compose.component.simple_element")) {
					if (value) element.attributes.name = value;
					else return;
				}
				element.attributes.value = "0";
				if (elementName === api._lang.GET("assemble.compose.component.range_min")) element.attributes.min = Number(value);
				if (elementName === api._lang.GET("assemble.compose.component.range_max")) element.attributes.max = Number(value);
				if (elementName === api._lang.GET("assemble.compose.component.range_step")) {
					value = value.replace(",", ".");
					if ((typeof Number(value) === "number" && Number(value) < element.attributes.max) || value === "any") element.attributes.step = value;
				}
			} else {
				// ...input
				if (elementName === api._lang.GET("assemble.compose.component.field_name")) {
					if (value) element.attributes.name = value;
					else return;
				}
			}
			if (elementName === api._lang.GET("assemble.component.texttemplate") && value) element.texttemplates = true;
			if (elementName === api._lang.GET("assemble.compose.component.field_hint") && value) element.hint = value;
			if (elementName === api._lang.GET("assemble.compose.component.required") && sibling.checked && !("required" in element.attributes)) element.attributes.required = true;
			if (elementName === api._lang.GET("assemble.compose.component.multiple") && sibling.checked && !("multiple" in element.attributes)) element.attributes.multiple = true;
			if (elementName === api._lang.GET("assemble.compose.component.link_document_choice") && value === api._lang.GET("assemble.compose.component.link_document_display") && sibling.checked) {
				element.attributes.onpointerup = "api.record('get','displayonly', '" + element.attributes.value + "')";
				element.attributes.value = api._lang.GET("assemble.compose.component.link_document_display_button", { ":document": element.attributes.value });
			}
			if (elementName === api._lang.GET("assemble.compose.component.link_document_choice") && value === api._lang.GET("assemble.compose.component.link_document_continue") && sibling.checked) {
				element.attributes.onpointerup = "api.record('get','document', '" + element.attributes.value + "', document.querySelector('input[name^=IDENTIFY_BY_]') ? document.querySelector('input[name^=IDENTIFY_BY_]').value : null)";
				element.attributes.value = api._lang.GET("assemble.compose.component.link_document_continue_button", { ":document": element.attributes.value });
			}
			sibling = sibling.nextSibling;
		} while (sibling);

		// append new widget to dom and update compose_helper.newDocumentComponents
		if (Object.keys(element).length > 1) {
			const newElement = new Compose({
				draggable: true,
				content: [
					[structuredClone(element)], // element receives attributes from currentElement otherwise
				],
			});
			document.getElementById("main").append(...newElement.initializeSection());
			newElement.processAfterInsertion();
			compose_helper.newDocumentComponents[newElement.generatedElementIDs[0]] = element;
		}
	},

	/**
	 * append new text chunk to view and newTextElements. used by texttemplate.php composer
	 * @requires _client, Compose
	 * @param {string} key referring _client.texttemplate.data
	 * @event append texttemplate to view
	 * @event add texttemplate structure to compose_helper.newTextElements
	 */
	composeNewTextTemplateCallback: function (key) {
		const chunk = new Compose({
			draggable: true,
			allowSections: false,
			content: [
				[
					{
						type: "textsection",
						attributes: {
							name: key,
						},
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
	 * @event adds form domNode
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
	 * appends or updates a hidden input for fields with the components json structure to the editor form. used by api.js
	 * @param {object} composedComponent Assemble syntax
	 * @event adds domNodes
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
	 * creates a full component by comparing the contents of newDocumentComponents and the actual order from view (after reordering/dragging)
	 * widgets not present in view will be skipped on compose_helper.newDocumentComponents
	 * @requires api, Toast
	 * @param {bool} raw_import
	 * @returns object|null
	 * @event Toast on errors
	 */
	composeNewComponent: function (raw_import = false) {
		let isForm = false,
			componentContent = [],
			name = document.getElementById("ComponentName").value,
			approve = document.getElementById("ComponentApprove").value,
			hidden = document.querySelector("[data-hiddenradio]") ? document.querySelector("[data-hiddenradio]").checked : false;

		/**
		 * recursively get dragged/dropped order of elements and add to array
		 * @param {domNode} parent passed node to analyze contents
		 * @return {array} array of ordered compose_helper.newDocumentComponents
		 */
		function nodechildren(parent) {
			let content = [],
				container;
			[...parent.childNodes].forEach((node) => {
				if (node.draggable) {
					container = node.children[1];
					if (container && container.localName === "article") {
						if (container.firstChild.localName === "section") content.push(nodechildren(container.firstChild));
						else content.push(nodechildren(container));
					} else {
						if (node.id in compose_helper.newDocumentComponents) {
							if (compose_helper.newDocumentComponents[node.id].attributes != undefined) delete compose_helper.newDocumentComponents[node.id].attributes["placeholder"];
							content.push(compose_helper.newDocumentComponents[node.id]);
							if (!["text", "links", "image"].includes(compose_helper.newDocumentComponents[node.id].type)) isForm = true;
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
		// append form if applicable, aka available inputs
		if (isForm) answer.form = {};
		if (raw_import || (name && componentContent)) return answer;
		new Toast(api._lang.GET("assemble.compose.component.component_not_saved_missing"), "error");
		return null;
	},

	/**
	 * creates a document fetching the actual order of components (names) from view
	 * previously imported components not present in view will be ignored
	 * @requires api, Toast
	 * @returns object|null
	 * @event Toast on errors
	 */
	composeNewDocument: function () {
		// get document order and values
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
		// iterate over main node and gather data-name for components
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
		new Toast(api._lang.GET("assemble.compose.document.document_not_saved_missing"), "error");
		return null;
	},

	/**
	 * creates a text template by comparing the contents of newTextElements and the actual order from view (after reordering/dragging)
	 * textcomponents not present in view will be skipped on compose_helper.newTextElements
	 * @requires api, Toast
	 * @returns object|null
	 * @event Toast on errors
	 */
	composeNewTextTemplate: function () {
		// set dragged/dropped order of elements
		const name = document.getElementById("TemplateName").value,
			language = document.getElementById("TemplateLanguage").value,
			unit = document.getElementById("TemplateUnit").value,
			hidden = document.querySelector("[data-hiddenradio]") ? document.querySelector("[data-hiddenradio]").checked : false;

		/**
		 * recursively get dragged/dropped order of elements and add to array
		 * @param {domNode} parent passed node to analyze contents
		 * @return {array} array of ordered compose_helper.newTextElements
		 */
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
		new Toast(api._lang.GET("assemble.edit_template_not_saved_missing"), "error");
		return null;
	},

	/**
	 * appends a component to view and newDocumentComponents after being fetched by api.js
	 * @requires Compose
	 * @param {object} content Assemble syntax
	 * @event append component
	 * @event update compose_helper.newDocumentComponents
	 */
	importComponent: function (content) {
		compose_helper.newDocumentComponents = {};
		const newElements = new Compose({
			draggable: true,
			content: structuredClone(content.content),
		});
		document.getElementById("main").append(...newElements.initializeSection());
		newElements.processAfterInsertion();
		// recursive function to assign created ids to document content elements in order of appearance
		const elementIDs = newElements.generatedElementIDs;
		let i = 0;

		function assignIDs(element) {
			for (const container of element) {
				if (container.constructor.name === "Array") {
					assignIDs(container);
				} else {
					compose_helper.newDocumentComponents[elementIDs[i]] = container;
					i++;
				}
			}
		}
		assignIDs(content.content);
	},

	/**
	 * appends document components to view and newDocumentElements after being fetched by api.js
	 * @requires MetaCompose, api, Toast
	 * @param {object} content Assemble syntax
	 * @event append components
	 * @event update compose_helper.newDocumentComponents
	 */
	importDocument: function (content) {
		/**
		 * recursively count identify and signaturepad widgets within components
		 * @param {domNode} element domNode
		 * @event update compose_helper.componentIdentify, compose_helper.componentSignature
		 */
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

		// append component and make them draggable
		for (const component of content) {
			component.draggable = true;
			let current = new MetaCompose(component);
			document.getElementById("main").append(current.initializeSection());
			current.processAfterInsertion2();
			compose_helper.newDocumentElements.add(component.name);
			lookupIdentify(current.content);
		}

		// alert on forbidden widget count per document
		if (compose_helper.componentIdentify > 1) new Toast(api._lang.GET("assemble.compose.document.document_multiple_identify"), "error");
		if (compose_helper.componentSignature > 1) new Toast(api._lang.GET("assemble.compose.document.document_multiple_signature"), "error");
	},

	/**
	 * appends text chunks to view and newTextElements after being fetched by api.js
	 * @requires Compose
	 * @param {object} chunks Assemble syntax
	 * @event append chunks
	 * @event update compose_helper.newTextElements
	 */
	importTextTemplate: function (chunks) {
		compose_helper.newTextElements = {};
		for (const paragraph of chunks) {
			let texts = { content: [], keys: [] };
			for (const key of paragraph) {
				texts.content.push({
					type: "textsection",
					attributes: {
						name: key,
					},
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
		/**
		 * displays a context menu (right click) on draggable containers to allow for reordering or deleting event target
		 * @requires api, _client
		 * @param {event} evnt
		 * @event append context menu to body
		 */
		contextMenu: function (evnt) {
			evnt.preventDefault();

			evnt.dataTransfer = new DataTransfer();
			evnt.dataTransfer.setData("text", evnt.target.id);
			for (const element of document.querySelectorAll(".contextmenu")) element.remove();
			let div = document.createElement("div"),
				button,
				target,
				targetClone;

			// determine target:
			// target is element
			if (evnt.target.classList.contains("draggableDocumentElement")) target = evnt.target; // draggable element container
			else if (evnt.target.parentNode.classList.contains("draggableDocumentElement")) target = evnt.target.parentNode; // draggable element container content
			// target is container
			else if (["main", "section"].includes(evnt.target.parentNode.localName)) target = evnt.target; // draggable div container
			else if (["main", "section"].includes(evnt.target.parentNode.parentNode.localName)) target = evnt.target.parentNode; // draggable div container content

			// style context menu and position relative to pointer
			div.classList.add("contextmenu");
			div.style.left = evnt.clientX + "px";
			div.style.top = window.scrollY + evnt.clientY - 10 + "px";

			// add close "button"
			const img = document.createElement("img");
			img.classList.add("close");
			img.src = "./media/times.svg";
			img.onpointerup = () => {
				div.remove();
			};
			div.append(img);

			// to top option
			button = document.createElement("button");
			button.type = "button";
			button.classList.add("discreetButton");
			button.appendChild(document.createTextNode(api._lang.GET("assemble.compose.context_2top")));
			button.onpointerup = () => {
				if (target.previousElementSibling && target.previousElementSibling.draggable) {
					targetClone = target.cloneNode(true);
					target.parentNode.insertBefore(targetClone, target.parentNode.children[0]);
					target.remove();
				}
				div.remove();
			};
			div.append(button);

			// one up option
			button = document.createElement("button");
			button.type = "button";
			button.classList.add("discreetButton");
			button.appendChild(document.createTextNode(api._lang.GET("assemble.compose.context_1up")));
			button.onpointerup = () => {
				if (target.previousElementSibling && target.previousElementSibling.draggable) {
					targetClone = target.cloneNode(true);
					target.parentNode.insertBefore(targetClone, target.previousElementSibling);
					target.remove();
				}
				div.remove();
			};
			div.append(button);

			// one down option
			button = document.createElement("button");
			button.type = "button";
			button.classList.add("discreetButton");
			button.appendChild(document.createTextNode(api._lang.GET("assemble.compose.context_1down")));
			button.onpointerup = () => {
				if (target.nextElementSibling) {
					targetClone = target.cloneNode(true);
					target.nextElementSibling.after(targetClone);
					target.remove();
				}
				div.remove();
			};
			div.append(button);

			// to bottom option
			button = document.createElement("button");
			button.type = "button";
			button.classList.add("discreetButton");
			button.appendChild(document.createTextNode(api._lang.GET("assemble.compose.context_2bottom")));
			button.onpointerup = () => {
				targetClone = target.cloneNode(true);
				target.parentNode.append(targetClone);
				target.remove();
				div.remove();
			};
			div.append(button);

			// delete target
			button = document.createElement("button");
			button.type = "button";
			button.classList.add("discreetButton");
			button.appendChild(document.createTextNode(api._lang.GET("assemble.compose.context_delete")));
			let options = {};
			(options[api._lang.GET("general.cancel_button")] = false),
				(options[api._lang.GET("general.ok_button")] = { value: true, class: "reducedCTA" }),
				(button.onpointerup = () => {
					new _client.Dialog({ type: "confirm", header: api._lang.GET("assemble.compose.context_delete"), options: options }).then((confirmation) => {
						if (confirmation) {
							compose_helper.dragNdrop.drop_delete(evnt);
						}
						div.remove();
					});
				});
			div.append(button);

			// append context menu
			document.querySelector("body").append(div);
			return false;
		},

		drag: function (evnt) {
			evnt.dataTransfer.setData("text", evnt.target.id);
			this.stopParentDropEvent = false;
		},
		/**
		 * inserts widgets or section before dropped upon widget or section
		 * @param {event} evnt
		 * @param {domNode} droppedUpon node
		 * @param {bool} allowSections if a dropped element is allowed to create a slider. only allowed for component editor, not documents or textrecommendations
		 * @event insert dragged element clone, reorder and propapbly nest nodes and delete original
		 */
		drop_insert: function (evnt, droppedUpon, allowSections) {
			evnt.preventDefault();
			if (!evnt.dataTransfer.getData("text")) return;

			const draggedElement = document.getElementById(evnt.dataTransfer.getData("text")),
				draggedElementClone = draggedElement.cloneNode(true), // cloned for most likely descendant issues
				originParent = draggedElement.parentNode;
			//console.log('dragged', draggedElement.id, 'dropped on', droppedUpon.id, 'target', evnt.target);
			if (!draggedElement || this.stopParentDropEvent || draggedElement.id === droppedUpon.id) return;

			// dragging single widget
			if (draggedElement.classList.contains("draggableDocumentElement")) {
				// dropping on single widget
				if (droppedUpon.classList.contains("draggableDocumentElement")) {
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
				// sanitize article on lack of elements if dragged out of article or section
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
				// handle only if dropped within the reorder area
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
				!draggedElement.classList.contains("draggableDocumentElement") &&
				!droppedUpon.classList.contains("draggableDocumentElement")
			) {
				// avoid recursive multiples
				// create a multiple article tile if dropped on a tile (slider)
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
		 * deletes widget or section if dropped on deletion area or confirmed context menu deletion
		 * @param {event} evnt
		 * @event removes event target
		 * @event updates compose_helper.componentIdentify and compose_helper.componentSignature count
		 */
		drop_delete: function (evnt) {
			const draggedElement = document.getElementById(evnt.dataTransfer.getData("text"));
			if (!draggedElement) {
				new _client.Toast(api._lang.GET("assemble.compose.context_delete_error"), "error");
				return;
			}
			const originParent = draggedElement.parentNode;

			// sanitize article on lack of elements
			if (originParent.parentNode != document.getElementById("main") && originParent.children.length < 2) {
				originParent.parentNode.remove(); // adapt to changes in section creation!
			}

			/**
			 * recursively count deleted identifiers or signaturepads within component/document to eventually reenable adding
			 * @param {domNode} parent domNode
			 * @event updates compose_helper.componentIdentify and compose_helper.componentSignature count
			 */
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
		 * @param {event} evnt event
		 * @event populate components editor form with values
		 * @see compose_helper.composeNewElementCallback()
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
						name: ["select", "scanner", "radio", "photo", "file", "signature", "checkbox"],
						description: ["links"],
					};
					do {
						if (!["input", "textarea"].includes(sibling.localName)) {
							sibling = sibling.nextSibling;
							continue;
						}
						elementName = sibling.name.replace(/\(.*?\)|\[\]/g, "");
						if (elementName === api._lang.GET("assemble.compose.component.field_hint") && importable.hint) sibling.value = importable.hint;
						if (elementName === api._lang.GET("assemble.component.texttemplate")) sibling.checked = Boolean(importable.texttemplates);
						if (elementName === api._lang.GET("assemble.compose.component.required")) sibling.checked = Boolean("required" in importable.attributes && importable.attributes.required);
						if (elementName === api._lang.GET("assemble.compose.component.multiple")) sibling.checked = Boolean("multiple" in importable.attributes && importable.attributes.multiple);
						if (elementName === api._lang.GET("assemble.compose.component.context_identify")) sibling.checked = Boolean(importable.type === "identify");

						if (["links", "radio", "select", "checkbox"].includes(importable.type)) {
							if (elementName === api._lang.GET("assemble.compose.component.multilist_name")) {
								setTo = Object.keys(setName).find((key) => setName[key].includes(importable.type));
								if (setTo === "name") sibling.value = importable.attributes.name;
								else if (setTo === "description") sibling.value = importable.description;
							}
							if (elementName === api._lang.GET("assemble.compose.component.multilist_add_item")) {
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
							if (elementName === api._lang.GET("assemble.compose.component.simple_element")) sibling.value = importable.attributes.name;
						} else if (["textsection"].includes(importable.type)) {
							if (elementName === api._lang.GET("assemble.compose.component.textsection_description")) sibling.value = importable.attributes.name;
							if (elementName === api._lang.GET("assemble.compose.component.textsection_content")) if (importable.content) sibling.value = importable.content;
						} else if (["range"].includes(importable.type)) {
							if (elementName === api._lang.GET("assemble.compose.component.simple_element")) sibling.value = importable.attributes.name;
							if (elementName === api._lang.GET("assemble.compose.component.range_min")) sibling.value = importable.attributes.min;
							if (elementName === api._lang.GET("assemble.compose.component.range_max")) sibling.value = importable.attributes.max;
							if (elementName === api._lang.GET("assemble.compose.component.range_step")) sibling.value = importable.attributes.step;
						} else {
							// ...input
							if (elementName === api._lang.GET("assemble.compose.component.field_name")) {
								sibling.value = importable.attributes.name;
							}
						}
						sibling = sibling.nextSibling;
					} while (sibling);
				}
			}
		},
	},

	/**
	 * make an element draggable (widget, component, textchunk)
	 * @param {domNode} element to make draggable
	 * @param {bool} insertionArea for articles to have a hr handle to insert other element before the article
	 * @param {bool} allowSections if a dropped element is allowed to create a slider. only allowed for component editor, not documents or textrecommendations
	 * @returns {domNode} altered element
	 */
	create_draggable: function (element, insertionArea = true, allowSections = true) {
		element.id = getNextElementID();
		element.setAttribute("draggable", "true");
		element.setAttribute("ondragstart", "compose_helper.dragNdrop.drag(event)");
		element.setAttribute("ondragover", "compose_helper.dragNdrop.allowDrop(event); this.classList.add('draggableDocumentElementHover')");
		element.setAttribute("ondragleave", "this.classList.remove('draggableDocumentElementHover')");
		element.setAttribute("ondrop", "compose_helper.dragNdrop.drop_insert(event, this, " + allowSections + "), this.classList.remove('draggableDocumentElementHover')");
		element.setAttribute("oncontextmenu", "compose_helper.dragNdrop.contextMenu(event)");
		if (insertionArea) {
			const insertionArea = document.createElement("hr");
			insertionArea.setAttribute("ondragover", "this.classList.add('insertionAreaHover')");
			insertionArea.setAttribute("ondragleave", "this.classList.remove('insertionAreaHover')");
			insertionArea.classList.add("insertionArea");
			element.insertBefore(insertionArea, element.firstChild);
		}
		return element;
	},

	/**
	 * adds drop event to delete dragged upon nodes
	 * @param {domNode} element to make a trash area for deletion
	 * @event add events
	 */
	composer_add_trash: function (element) {
		element.setAttribute("ondragstart", "compose_helper.dragNdrop.drag(event)");
		element.setAttribute("ondragover", "compose_helper.dragNdrop.allowDrop(event)");
		element.setAttribute("ondrop", "compose_helper.dragNdrop.drop_delete(event)");
	},

	/**
	 * adds drop event for reimport to widget creation forms
	 * @param {domNode} element to allow reimport event
	 * @event add events
	 */
	composer_component_document_reimportable: function (element) {
		element.setAttribute("ondragstart", "compose_helper.dragNdrop.drag(event)");
		element.setAttribute("ondragover", "compose_helper.dragNdrop.allowDrop(event)");
		element.setAttribute("ondrop", "compose_helper.dragNdrop.drop_reimport(event)");
	},
};

export class Compose extends Assemble {
	/**
	 * creates composer specific forms for widget and document creation
	 * @requires api, Assemble
	 * @param {object} setup Assemble syntax
	 * @see Assemble
	 */
	constructor(setup) {
		super(setup);
		this.composer = this.createDraggable = setup.draggable;
		this.allowSections = "allowSections" in setup ? setup.allowSections : undefined;
		this.generatedElementIDs = [];
	}

	/**
	 * recursively processes one panel, with slides if nested accordingly and instatiates the contained widget elements
	 * @override adding draggable container
	 * @param {array|object} elements render instructions for single panel
	 * @returns {domNodes}
	 *
	 * @example content to exist of three nestings
	 * ```json
	 * 	[ panel article>section
	 * 		[ slide article
	 * 			{ element },
	 * 			{ element }
	 * 		],
	 * 		[ slide article
	 * 			...
	 * 		],
	 * 	],
	 * ```
	 * @example or two nestings
	 * ```json
	 *
	 * 	[ panel article
	 * 		{ element },
	 * 		{ element }
	 * 	]
	 * ```
	 */
	processPanel(elements) {
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
						compose_helper.composer_component_document_reimportable(form);
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
				frame.classList.add("draggableDocumentElement");
				frame.append(...this[this.currentElement.type]());
				frame = compose_helper.create_draggable(frame, false, this.allowSections);
				this.generatedElementIDs.push(frame.id);
				content.push(frame);
			}
		}
		return content;
	}

	/**
	 * iterates over this.content and gathers panels
	 * @override for having to add draggable containers
	 * @returns {domNodes} all assembled panels
	 */
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

	/**
	 * creates editor to add a calendar button
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_calendarbutton"
	 * 	}
	 */
	compose_calendarbutton() {
		let result = [...this.br()];
		this.currentElement = {
			type: "textsection",
			attributes: {
				"data-type": "calendarbutton",
				name: api._lang.GET("assemble.compose.component.calendarbutton"),
			},
			content: api._lang.GET("assemble.compose.component.calendarbutton_not_working"),
		};
		result = result.concat(...this.textsection());
		this.currentElement = {
			attributes: {
				value: api._lang.GET("assemble.compose.component.calendarbutton"),
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}

	/**
	 * creates editor to add a checkbox list
	 * @see this.compose_multilist()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_checkbox"
	 * 	}
	 */
	compose_checkbox() {
		return this.compose_multilist({
			type: "checkbox",
			description: api._lang.GET("assemble.compose.component.checkbox"),
		});
	}

	/**
	 * creates the input elements to general settings to a whole component or document
	 * @param {object} std available options and presets
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_component",
	 * 		"value": "A cool component name",
	 * 		"hint": "something cool about this component",
	 * 		"hidden": null,
	 * 		"approve": {
	 * 			"hint": "please approve of this component",
	 * 			"name": "component approval",
	 * 			"content": {
	 * 				"organizational unit 1": [],
	 * 				"organizational unit 2": [],
	 * 				"organizational unit 3": [],
	 * 			},
	 * 		}
	 * 	}
	 */
	compose_component(
		std = {
			name: api._lang.GET("assemble.compose.component.component_name"),
			description: api._lang.GET("assemble.compose.component.component"),
			list: "components",
			action:
				"new _client.Dialog({type: 'confirm', header: '" +
				api._lang.GET("assemble.compose.component.component") +
				"', options:{" +
				"'" +
				api._lang.GET("assemble.compose.component.component_cancel") +
				"': false," +
				"'" +
				api._lang.GET("assemble.compose.component.component_confirm") +
				"': {value: true, class: 'reducedCTA'}," +
				"}}).then(confirmation => {if (confirmation) api.document('post', 'component')})",
			hidden: {
				name: api._lang.GET("assemble.compose.component.component_hidden"),
				hint: api._lang.GET("assemble.compose.component.component_hidden_hint"),
			},
		}
	) {
		let result = [],
			alias = this.currentElement.alias,
			context = this.currentElement.context,
			prefilled = Boolean(this.currentElement.value),
			hidden = this.currentElement.hidden,
			approve = this.currentElement.approve,
			regulatory_context = this.currentElement.regulatory_context,
			permitted_export = this.currentElement.permitted_export,
			restricted_access = this.currentElement.restricted_access;

		// input for component / document name
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

		// input for document alias
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

		// input for document context
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

		// toggle for document permitted export
		if (permitted_export) {
			permitted_export.content[Object.keys(permitted_export.content)[0]]["id"] = "ComponentPermittedExport";
			this.currentElement = {
				type: "checkbox",
				hint: permitted_export.hint,
				content: permitted_export.content,
			};
			result = result.concat(...this.checkbox());
		}

		// input for documents restricted access
		if (restricted_access) {
			this.currentElement = {
				type: "checkbox2text",
				content: restricted_access.content,
				attributes: {
					name: api._lang.GET("assemble.compose.document.document_restricted_access"),
					id: "ComponentRestrictedAccess",
				},
				hint: restricted_access.hint,
			};
			result = result.concat(...this.checkbox2text());
		}

		// input for documents rgulatory context
		if (regulatory_context) {
			this.currentElement = {
				type: "checkbox2text",
				content: regulatory_context,
				attributes: {
					name: api._lang.GET("assemble.compose.document.document_regulatory_context"),
					id: "ComponentRegulatoryContext",
				},
			};
			result = result.concat(...this.checkbox2text());
		}

		// selection for approval of units supervisor beside other set permissions
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

		// prefilling all inputs from selected component / document
		if (prefilled) {
			const options = {};
			options[api._lang.GET("assemble.compose.edit_visible")] = !(hidden && Object.keys(hidden).length)
				? {
						checked: true,
				  }
				: {};
			options[api._lang.GET("assemble.compose.edit_hidden")] =
				hidden && Object.keys(hidden).length
					? {
							checked: true,
							"data-hiddenradio": "ComponentHidden",
					  }
					: {
							"data-hiddenradio": "ComponentHidden",
							class: "red",
					  };
			this.currentElement = {
				type: "radio",
				hint:
					std.hidden.hint +
					(hidden && Object.keys(hidden).length
						? " " +
						  api._lang.GET("assemble.edit_hidden_set", {
								":name": hidden.name,
								":date": hidden.date,
						  })
						: ""),
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

	/**
	 * creates editor to add a date input
	 * @see this.compose_input()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_date"
	 * 	}
	 */
	compose_date() {
		return this.compose_input({
			type: "date",
			description: api._lang.GET("assemble.compose.component.date"),
		});
	}

	/**
	 * creates editor to add an email input
	 * @see this.compose_input()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_email"
	 * 	}
	 */
	compose_email() {
		return this.compose_input({
			type: "email",
			description: api._lang.GET("assemble.compose.component.email"),
		});
	}

	/**
	 * creates editor to add a file input
	 * @see this.compose_simpleElement()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_file"
	 * 	}
	 */
	compose_file() {
		return this.compose_simpleElement({
			type: "file",
			description: api._lang.GET("assemble.compose.component.file"),
			multiple: "optional",
		});
	}

	/**
	 * creates the input elements to general settings to a whole document
	 * @see this.compose_component()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_document",
	 * 		"value": "A cool document name",
	 * 		"alias": "fancy form definition",
	 * 		"context": {
	 * 			"name": "select context",
	 * 			"content" : {
	 * 				"...": [],
	 * 				"casedocumentation": [],
	 * 				"incident": [],
	 * 				...
	 * 			}
	 * 		},
	 * 		"hint": "something cool about this document",
	 * 		"hidden": null,
	 * 		"approve": {
	 * 			"hint": "please approve of this component",
	 * 			"name": "component approval",
	 * 			"content": {
	 * 				"organizational unit 1": [],
	 * 				"organizational unit 2": [],
	 * 				"organizational unit 3": [],
	 * 			},
	 * 		},
	 * 		"regulatory_context": {
	 * 			"iso 1": [],
	 * 			"iso 2": [],
	 * 			...
	 * 		},
	 * 		"permitted_export": {
	 * 			"hint": "please take into account that...",
	 * 			"content": {
	 * 				"export permitted": []
	 * 			}
	 * 		},
	 * 		"restricted_access": {
	 * 			"description": "not everybody to use this",
	 * 			"hint": "please take into account that...",
	 * 			"content": {
	 * 				"permission group a": [],
	 * 				"permission group b": [],
	 * 				"permission group c": [],
	 * 				...
	 * 			}
	 * 		}
	 * 	}
	 */
	compose_document() {
		return this.compose_component({
			name: api._lang.GET("assemble.compose.document.document_label"),
			description: api._lang.GET("assemble.compose.document.document"),
			list: "documents",
			action:
				"new _client.Dialog({type: 'confirm', header: '" +
				api._lang.GET("assemble.compose.document.document") +
				"', options:{" +
				"'" +
				api._lang.GET("assemble.compose.document.document_cancel") +
				"': false," +
				"'" +
				api._lang.GET("assemble.compose.document.document_confirm") +
				"': {value: true, class: 'reducedCTA'}," +
				"}}).then(confirmation => {if (confirmation) api.document('post', 'document')})",
			hidden: {
				name: api._lang.GET("assemble.compose.document.document_hidden"),
				hint: api._lang.GET("assemble.compose.document.document_hidden_hint"),
			},
		});
	}

	/**
	 * creates editor to add a document access button
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_documentbutton"
	 * 		"content": {
	 * 			"Document A": [],
	 * 			"Document B": [],
	 * 			"Process Instruction": [],
	 * 			...
	 * 		}
	 * 	}
	 */
	compose_documentbutton() {
		let result = [...this.br()],
			document = this.currentElement.content;

		this.currentElement = {
			type: "textsection",
			attributes: {
				"data-type": "documentbutton",
				name: api._lang.GET("assemble.compose.component.link_document"),
			},
		};
		result = result.concat(...this.textsection());

		this.currentElement = {
			type: "select",
			attributes: {
				name: api._lang.GET("assemble.compose.component.link_document_select"),
			},
			content: document,
		};
		result = result.concat(...this.select());

		this.currentElement = {
			type: "radio",
			attributes: { name: api._lang.GET("assemble.compose.component.link_document_choice") },
			content: {},
		};
		this.currentElement.content[api._lang.GET("assemble.compose.component.link_document_display")] = {
			required: true,
		};
		this.currentElement.content[api._lang.GET("assemble.compose.component.link_document_continue")] = {
			required: true,
		};
		result = result.concat(...this.br(), ...this.radio());

		this.currentElement = {
			attributes: {
				value: api._lang.GET("assemble.compose.component.link_document"),
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}

	/**
	 * creates editor to add an image to a conponent
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_image"
	 * 	}
	 */
	compose_image() {
		let result = [];
		this.currentElement = {
			type: "image",
			attributes: {
				name: api._lang.GET("assemble.compose.component.image_description"),
			},
		};
		result = result.concat(...this.text(), ...this.br());
		this.currentElement = {
			type: "photo",
			attributes: {
				name: api._lang.GET("assemble.compose.component.image"),
			},
		};
		result = result.concat(...this.photo(), ...this.br());
		this.currentElement = {
			attributes: {
				value: api._lang.GET("assemble.compose.component.image"),
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}

	/**
	 * creates editor to add inputs like text, number, date, etc.
	 * @param {object} type {type: string, description: string, multiple: bool}
	 * @returns {domNodes}
	 */
	compose_input(type) {
		let result = [];
		this.currentElement = {
			type: type.type,
			attributes: {
				name: api._lang.GET("assemble.compose.component.field_name"),
				required: true,
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textsection",
			attributes: {
				name: api._lang.GET("assemble.compose.component.field_hint"),
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			content: {},
		};
		this.currentElement.content[api._lang.GET("assemble.compose.component.required")] = {
			name: api._lang.GET("assemble.compose.component.required"),
		};
		if (type.multiple)
			this.currentElement.content[api._lang.GET("assemble.compose.component.multiple")] = {
				name: api._lang.GET("assemble.compose.component.multiple"),
			};
		if (type.type === "textarea")
			this.currentElement.content[api._lang.GET("assemble.component.texttemplate")] = {
				name: api._lang.GET("assemble.component.texttemplate"),
			};
		result = result.concat(...this.br(), ...this.checkbox());
		this.currentElement = {
			attributes: {
				value: type.description,
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}

	/**
	 * creates editor to add a link input
	 * @see this.compose_input()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_link"
	 * 	}
	 */
	compose_link() {
		return this.compose_input({
			type: "link",
			description: api._lang.GET("assemble.compose.component.link"),
			multiple: "optional",
		});
	}

	/**
	 * creates editor to add a checkbox list
	 * @see this.compose_multilist()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_multilist"
	 * 	}
	 */
	compose_links() {
		return this.compose_multilist({
			type: "links",
			description: api._lang.GET("assemble.compose.component.links"),
		});
	}

	/**
	 * creates editor to add multiple items like link lists, checkboxes, selections, etc.
	 * @param {object} type {type: string, description: string, multiple: bool, required: bool}
	 * @returns {domNodes}
	 */
	compose_multilist(type) {
		let result = [];
		this.currentElement = {
			type: type.type,
			attributes: {
				name: api._lang.GET("assemble.compose.component.multilist_name"),
				required: true,
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textsection",
			attributes: {
				name: api._lang.GET("assemble.compose.component.field_hint"),
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "text",
			attributes: {
				rows: 3,
				name: api._lang.GET("assemble.compose.component.multilist_add_item") + "[]",
				required: true,
			},
		};
		if (type.type === "links") result = result.concat(...this.text());
		else result = result.concat(...this.textarea());
		this.currentElement = {
			attributes: {
				value: api._lang.GET("assemble.compose.component.multilist_add_item_button"),
				"data-type": "additem",
				type: "button",
				onpointerup: function () {
					for (const e of compose_helper.cloneMultipleItems(this, -4, 4)) this.parentNode.insertBefore(e, this);
				}.toString(),
			},
		};
		if (type.type === "select") this.currentElement.hint = api._lang.GET("assemble.compose.component.select_hint");
		result = result.concat(...this.button());
		if (type.required !== undefined || type.multiple !== undefined) {
			this.currentElement = {
				content: {},
			};
			if (type.required !== undefined)
				this.currentElement.content[api._lang.GET("assemble.compose.component.required")] = {
					name: api._lang.GET("assemble.compose.component.required"),
				};
			if (type.multiple !== undefined)
				this.currentElement.content[api._lang.GET("assemble.compose.component.multiple")] = {
					name: api._lang.GET("assemble.compose.component.multiple"),
				};
			result = result.concat(...this.br(), ...this.checkbox());
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

	/**
	 * creates editor to add a number input
	 * @see this.compose_input()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_number"
	 * 	}
	 */
	compose_number() {
		return this.compose_input({
			type: "number",
			description: api._lang.GET("assemble.compose.component.number"),
			multiple: "optional",
		});
	}

	/**
	 * creates editor to add a photo input
	 * @see this.compose_simpleElement()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_photo"
	 * 	}
	 */
	compose_photo() {
		return this.compose_simpleElement({
			type: "photo",
			description: api._lang.GET("assemble.compose.component.photo"),
			multiple: "optional",
		});
	}

	/**
	 * creates editor to add a product selection input
	 * @see this.compose_input()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_productselection"
	 * 	}
	 */
	compose_productselection() {
		return this.compose_input({
			type: "productselection",
			description: api._lang.GET("assemble.compose.component.productselection"),
			multiple: "optional",
		});
	}

	/**
	 * creates editor to add a radio input list
	 * @see this.compose_multilist()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_radio"
	 * 	}
	 */
	compose_radio() {
		return this.compose_multilist({
			type: "radio",
			description: api._lang.GET("assemble.compose.component.radio"),
			required: "optional",
		});
	}

	/**
	 * creates editor to add a range input
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_range"
	 * 	}
	 */
	compose_range() {
		let result = [];
		this.currentElement = {
			type: "range",
			attributes: {
				name: api._lang.GET("assemble.compose.component.simple_element"),
				required: true,
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textsection",
			attributes: {
				name: api._lang.GET("assemble.compose.component.field_hint"),
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "number",
			attributes: {
				name: api._lang.GET("assemble.compose.component.range_min"),
			},
			hint: api._lang.GET("assemble.compose.component.range_min_hint"),
		};
		result = result.concat(...this.number());
		this.currentElement = {
			type: "number",
			attributes: {
				name: api._lang.GET("assemble.compose.component.range_max"),
			},
			hint: api._lang.GET("assemble.compose.component.range_max_hint"),
		};
		result = result.concat(...this.number());
		this.currentElement = {
			type: "text",
			attributes: {
				name: api._lang.GET("assemble.compose.component.range_step"),
			},
			hint: api._lang.GET("assemble.compose.component.range_step_hint"),
		};
		result = result.concat(...this.text());

		this.currentElement = {
			attributes: {
				value: api._lang.GET("assemble.compose.component.range"),
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}

	/**
	 * creates editor to add or retrieve components from or to json Assemble syntax
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_raw"
	 * 	}
	 */
	compose_raw() {
		let result = [];
		this.currentElement = {
			type: "code",
			hint: api._lang.GET("assemble.compose.component.raw_hint"),
			attributes: {
				name: api._lang.GET("assemble.compose.component.raw"),
				id: "_compose_raw",
			},
		};
		result = result.concat(...this.code());
		this.currentElement = {
			attributes: {
				value: api._lang.GET("assemble.compose.component.raw"),
				"data-type": "addblock",
				onpointerup: function () {
					if (document.getElementById("_compose_raw").value)
						try {
							compose_helper.importComponent({ content: JSON.parse(document.getElementById("_compose_raw").value) });
						} catch (e) {
							new _client.Dialog({ type: "alert", header: api._lang.GET("assemble.compose.component.raw"), render: api._lang.GET("assemble.compose.component.raw_json_error") });
						}
				}.toString(),
			},
		};
		result = result.concat(...this.button());
		this.currentElement = {
			attributes: {
				value: api._lang.GET("assemble.compose.component.raw_import"),
				"data-type": "import",
				onpointerup: function () {
					let component = compose_helper.composeNewComponent(true);
					document.getElementById("_compose_raw").value = component ? JSON.stringify(component.content, null, 4) : "";
					document.getElementById("_compose_raw").dispatchEvent(new Event("keyup"));
				}.toString(),
			},
		};
		result = result.concat(...this.button());
		return result;
	}

	/**
	 * creates editor to add a scanner
	 * @see this.compose_simpleElement()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_scanner"
	 * 	}
	 */
	compose_scanner() {
		return this.compose_simpleElement({
			type: "scanner",
			description: api._lang.GET("assemble.compose.component.scanner"),
			multiple: "optional",
		});
	}

	/**
	 * creates editor to add a select list
	 * @see this.compose_multilist()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_select"
	 * 	}
	 */
	compose_select() {
		return this.compose_multilist({
			type: "select",
			description: api._lang.GET("assemble.compose.component.select"),
			required: "optional",
			multiple: "optional",
		});
	}

	/**
	 * creates editor to add a signature pad
	 * @see this.compose_simpleElement()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_signature"
	 * 	}
	 */
	compose_signature() {
		return this.compose_simpleElement({
			type: "signature",
			description: api._lang.GET("assemble.compose.component.signature"),
			required: "optional",
			hint: api._lang.GET("assemble.compose.component.signature_hint"),
		});
	}

	/**
	 * creates editor to add simple items like scanner, signature pad, etc.
	 * @param {object} type {type: string, hint: string, multiple: bool, required: bool}
	 * @returns {domNodes}
	 */
	compose_simpleElement(type) {
		let result = [];
		this.currentElement = {
			type: type.type,
			attributes: {
				name: api._lang.GET("assemble.compose.component.simple_element"),
				required: true,
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textsection",
			attributes: {
				name: api._lang.GET("assemble.compose.component.field_hint"),
			},
			hint: type.hint ? type.hint : null,
		};
		result = result.concat(...this.text());
		if (type.required !== undefined) {
			this.currentElement = {
				content: {},
			};
			this.currentElement.content[api._lang.GET("assemble.compose.component.required")] = {
				name: api._lang.GET("assemble.compose.component.required"),
			};
			result = result.concat(...this.br(), ...this.checkbox());
		}

		this.currentElement = {
			type: "checkbox",
			content: {},
		};
		if (type.multiple !== undefined) {
			this.currentElement.content[api._lang.GET("assemble.compose.component.multiple")] = {
				name: api._lang.GET("assemble.compose.component.multiple"),
			};
		}
		if (type.type === "scanner") {
			this.currentElement.content[api._lang.GET("assemble.compose.component.context_identify")] = {
				name: api._lang.GET("assemble.compose.component.context_identify"),
				id: "setIdentify",
			};
		}
		if (Object.keys(this.currentElement.content).length) result = result.concat(...this.br(), ...this.checkbox());

		this.currentElement = {
			attributes: {
				value: type.description,
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}

	/**
	 * creates editor to add a tel input
	 * @see this.compose_input()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_tel"
	 * 	}
	 */
	compose_tel() {
		return this.compose_input({
			type: "tel",
			description: api._lang.GET("assemble.compose.component.tel"),
			multiple: "optional",
		});
	}

	/**
	 * creates editor to add a text input
	 * @see this.compose_input()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_text"
	 * 	}
	 */
	compose_text() {
		return this.compose_input({
			type: "text",
			description: api._lang.GET("assemble.compose.component.text"),
			multiple: "optional",
		});
	}

	/**
	 * creates editor to add a textarea input
	 * @see this.compose_input()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_textarea"
	 * 	}
	 */
	compose_textarea() {
		return this.compose_input({
			type: "textarea",
			description: api._lang.GET("assemble.compose.component.textarea"),
		});
	}

	/**
	 * creates editor to add a textsection
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_textsection"
	 * 	}
	 */
	compose_textsection() {
		let result = [];
		this.currentElement = {
			type: "textsection",
			attributes: {
				name: api._lang.GET("assemble.compose.component.textsection_description"),
				required: true,
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textarea",
			attributes: {
				name: api._lang.GET("assemble.compose.component.textsection_content"),
				rows: 5,
			},
		};
		result = result.concat(...this.textarea());
		this.currentElement = {
			attributes: {
				value: api._lang.GET("assemble.compose.component.textsection"),
				"data-type": "addblock",
			},
		};
		result = result.concat(...this.button());
		return result;
	}
}

export class MetaCompose extends Assemble {
	/**
	 * creates composer specific forms for document creation
	 * @requires api, Assemble
	 * @param {object} setup Assemble syntax
	 * @see Assemble
	 */
	constructor(setup) {
		delete setup.form;
		super(setup);
		this.setup = setup;
	}
	/**
	 * makes assembled and inserted elements draggable and assigns names to container
	 */
	processAfterInsertion2() {
		this.processAfterInsertion();
		if (this.setup.draggable) compose_helper.create_draggable(this.section, true, false);
		this.section.setAttribute("data-name", this.setup.name);
		if (this.setup.hidden) this.section.classList.add("hiddencomponent");
	}
}

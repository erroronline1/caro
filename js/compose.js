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
import { getNextElementID, Assemble, Toast } from "./assemble.js";

export class Composer {
	constructor() {
		this.newAuditQuestions = {};
		this.newDocumentComponents = {};
		this.newDocumentElements = new Set();
		this.newTextElements = {};
		this.componentIdentify = 0;
		this.componentSignature = 0;
		this.stopParentDropEvent = false;
	}

	/**
	 * e.g. adding new input fields for link collections
	 * @param {object} startNode
	 * @param {int} offset
	 * @param {int} numberOfElements
	 * @returns {domNodes}]
	 */
	cloneMultipleItems(startNode, offset = -1, numberOfElements = 1) {
		// positive offset for nodes after fromNode, negative for previous nodes, zero to take exactly the passed node

		//set pointer
		for (let i = 0 || offset; i < (offset < 0 ? 0 : offset); i++) {
			if (offset < 0) startNode = startNode.previousElementSibling;
			if (offset > 0) startNode = startNode.nextElementSibling;
		}

		const elements = [],
			clonedIds = {};
		for (let i = 0; i < numberOfElements; i++) {
			let clone = startNode.cloneNode(true);
			for (let i = 0; i < clone.childNodes.length; i++) {
				if ("value" in clone.childNodes[i]) clone.childNodes[i].value = "";
				if ("id" in clone.childNodes[i] && clone.childNodes[i].id) {
					clonedIds[clone.childNodes[i].id] = getNextElementID();
					clone.childNodes[i].id = clonedIds[clone.childNodes[i].id];
				}
				if ("required" in clone.childNodes[i]) clone.childNodes[i].removeAttribute("required");
			}
			elements.push(clone);
			startNode = startNode.nextElementSibling;
		}
		return elements;
	}

	/**
	 * append new audit question to view and this.newAuditQuestions. used by audit.php composer
	 * @requires _client, Compose
	 * @param {string} key referring _client.texttemplate.data
	 * @event append question to view
	 * @event add question structure to this..newAuditQuestions
	 */
	composeNewAuditQuestionCallback(question, regulatory, hint) {
		const chunk = new Compose({
			draggable: true,
			allowSections: false,
			content: [
				[
					{
						type: "auditsection",
						attributes: {
							name: api._lang.GET("audit.audit.question"),
						},
						content: question + "\n \n" + api._lang.GET("audit.audit.execute.regulatory") + "\n" + regulatory + "\n \n" + api._lang.GET("audit.audit.hint") + "\n" + hint,
					},
				],
			],
		});
		document.getElementById("main").append(...chunk.initializeSection());
		chunk.processAfterInsertion();
		this.newAuditQuestions[chunk.generatedElementIDs[0]] = { question: question, regulatory: regulatory, hint: hint };
	}

	/**
	 * create widget from composer and append to view and newDocumentComponents
	 * @requires Compose, api
	 * @param {domNode} parent composer form for widget
	 * @event append widget to view
	 * @event add widget structure to this.newDocumentComponents
	 */
	composeNewElementCallback(parent) {
		let sibling, siblingName, siblingValue;

		// define which attribute is assigned the name value, and set default values
		const buttonDefaultValues = {
				calendarbutton: api._lang.GET("calendar.schedule.new"),
			},
			element = {
				attributes: {},
			};

		// iterate over all nodes of the respective widgets composer form
		for (let s = 0; s < parent.childNodes.length; s++) {
			sibling = parent.childNodes[s];
			// skip nodes
			if (!["label", "header", "input"].includes(sibling.localName)) {
				continue;
			}
			if (sibling.localName === "header" && element.type === undefined) {
				// e.g. calendarbutton, hr
				element["type"] = sibling.dataset.type;
				if (buttonDefaultValues[sibling.dataset.type]) element["attributes"] = { value: buttonDefaultValues[sibling.dataset.type] };
				else delete element.attributes;
				continue;
			} else if (sibling.localName === "input") {
				// e.g. file
				siblingName = sibling.name;
				siblingValue = sibling.value;
			} else if (sibling.localName === "label") {
				// first input assigns type
				if (element.type === undefined) element["type"] = sibling.dataset.type;
				for (let i = 0; i < sibling.childNodes.length; i++) {
					if (!["input", "textarea", "select"].includes(sibling.childNodes[i].localName)) continue;
					siblingName = sibling.childNodes[i].name.replace(/\(.*?\)|\[\]/g, "");
					siblingValue = sibling.childNodes[i].value;
					break;
				}
			}

			// route values to names, values and attributes
			if (["links", "radio", "select", "checkbox"].includes(element.type)) {
				if (siblingName === api._lang.GET("assemble.compose.component.multilist_name")) {
					if (siblingValue) {
						if (["links"].includes(element.type)) element.description = siblingValue;
						else element.attributes.name = siblingValue;
					} else return;
				}
				if (siblingName === api._lang.GET("assemble.compose.component.multilist_add_item") && siblingValue) {
					if (element.content === undefined) element.content = {};
					element.content[siblingValue] = {};
				}
			} else if (["file", "photo", "scanner", "signature", "identify", "stlpicker"].includes(element.type)) {
				if (siblingName === api._lang.GET("assemble.compose.component.simple_element")) {
					if (siblingValue) element.attributes.name = siblingValue;
					else return;
				}
				if (siblingName === api._lang.GET("assemble.compose.component.context_identify") && sibling.checked) {
					element.attributes.required = true;
					delete element.attributes.multiple;
					element.type = "identify";
					document.getElementById("setIdentify").disabled = true;
					document.getElementById("setIdentify").checked = false;
				}
				if (element.type === "signature") {
					const signatureform = document.querySelector("form>label[data-type=signature").parentNode;
					for (const node of signatureform) {
						if (node.dataset.type === "addblock") {
							node.disabled = true;
							this.componentSignature++;
						}
					}
				}
			} else if (["textsection"].includes(element.type)) {
				if (siblingName === api._lang.GET("assemble.compose.component.textsection_description")) {
					if (siblingValue) element.attributes.name = siblingValue;
					else return;
				}
				if (siblingName === api._lang.GET("assemble.compose.component.textsection_content") && siblingValue) {
					element["content"] = siblingValue;
				}
			} else if (["documentbutton"].includes(element.type)) {
				if (siblingName === api._lang.GET("assemble.compose.component.link_document")) {
					if (siblingValue) element.attributes.value = siblingValue;
					else return;
				}
			} else if (["image"].includes(element.type)) {
				if (siblingName === api._lang.GET("assemble.compose.component.image_description")) {
					if (siblingValue) element.description = siblingValue;
				}
				if (siblingName === api._lang.GET("assemble.compose.component.image")) {
					if (siblingValue) {
						element.attributes = {
							name: siblingValue,
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
				if (siblingName === api._lang.GET("assemble.compose.component.simple_element")) {
					if (siblingValue) element.attributes.name = siblingValue;
					else return;
				}
				element.attributes.value = "0";
				if (siblingName === api._lang.GET("assemble.compose.component.range_min")) element.attributes.min = Number(siblingValue);
				if (siblingName === api._lang.GET("assemble.compose.component.range_max")) element.attributes.max = Number(siblingValue);
				if (siblingName === api._lang.GET("assemble.compose.component.range_step")) {
					siblingValue = siblingValue.replace(",", ".");
					if ((typeof Number(siblingValue) === "number" && Number(siblingValue) < element.attributes.max) || siblingValue === "any") element.attributes.step = siblingValue;
				}
			} else {
				// ...input
				if (siblingName === api._lang.GET("assemble.compose.component.field_name")) {
					if (siblingValue) element.attributes.name = siblingValue;
					else return;
				}
			}
			if (siblingName === api._lang.GET("assemble.compose.component.texttemplate") && siblingValue) element.texttemplates = true;
			if (siblingName === api._lang.GET("assemble.compose.component.field_hint") && siblingValue) element.hint = siblingValue;
			if (siblingName === api._lang.GET("assemble.compose.component.required") && sibling.checked && !("required" in element.attributes)) element.attributes.required = true;
			if (siblingName === api._lang.GET("assemble.compose.component.multiple") && sibling.checked && !("multiple" in element.attributes)) element.attributes.multiple = true;
			if (siblingName === api._lang.GET("assemble.compose.component.autocomplete") && sibling.checked && !("autocomplete" in element)) element.autocomplete = true;

			// documentbutton value should have been assigned in previous loops
			if (siblingName === api._lang.GET("assemble.compose.component.link_document_choice") && siblingValue === api._lang.GET("assemble.compose.component.link_document_display") && sibling.checked) {
				element.attributes.onclick = "api.record('get','displayonly', '" + element.attributes.value + "')";
				element.attributes.value = api._lang.GET("assemble.compose.component.link_document_display_button", { ":document": element.attributes.value });
			}
			if (siblingName === api._lang.GET("assemble.compose.component.link_document_choice") && siblingValue === api._lang.GET("assemble.compose.component.link_document_continue") && sibling.checked) {
				element.attributes.onclick = "api.record('get','document', '" + element.attributes.value + "', document.querySelector('input[name^=IDENTIFY_BY_]') ? document.querySelector('input[name^=IDENTIFY_BY_]').value : null)";
				element.attributes.value = api._lang.GET("assemble.compose.component.link_document_continue_button", { ":document": element.attributes.value });
			}

			// check if new elements name is allowed
			if (element.attributes && element.attributes.name) {
				for (const pattern of Object.entries(api._settings.config.forbidden.names)) {
					if (element.attributes.name.match(new RegExp(pattern, "gm"))) {
						new Toast(api._lang.GET("assemble.compose.error_forbidden_name", { ":name": element.attributes.name }) + " " + pattern, "error");
						return;
					}
				}
			}
		}
		// append new widget to dom and update this.newDocumentComponents
		if (Object.keys(element).length > 0) {
			const newElement = new Compose({
				draggable: true,
				content: [
					[structuredClone(element)], // element receives attributes from currentElement otherwise
				],
			});
			document.getElementById("main").append(...newElement.initializeSection());
			newElement.processAfterInsertion();
			this.newDocumentComponents[newElement.generatedElementIDs[0]] = element;
		}
	}

	/**
	 * append new text chunk to view and newTextElements. used by texttemplate.php composer
	 * @requires _client, Compose
	 * @param {string} key referring _client.texttemplate.data
	 * @event append texttemplate to view
	 * @event add texttemplate structure to this.newTextElements
	 */
	composeNewTextTemplateCallback(key) {
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
		this.newTextElements[chunk.generatedElementIDs[0]] = key;
	}

	/**
	 * add multipart form for component editor for file uploads. used by api.js
	 * @event adds form domNode
	 */
	addComponentMultipartFormToMain() {
		const form = document.createElement("form");
		form.style.display = "hidden";
		form.dataset.usecase = "component_editor_form";
		form.enctype = "multipart/form-data";
		form.method = "post";
		document.getElementById("main").insertAdjacentElement("afterbegin", form);
	}

	/**
	 * appends or updates a hidden input for fields with the components json structure to the editor form. used by api.js
	 * @param {object} composedComponent Assemble syntax
	 * @event adds domNodes
	 */
	addComponentStructureToComponentForm(composedComponent) {
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
	}

	/**
	 * creates an audit template by comparing the contents of newAuditQuestions and the actual order from view (after reordering/dragging)
	 * textcomponents not present in view will be skipped on this.newAuditQuestions
	 * @requires api, Toast
	 * @returns object|null
	 * @event Toast on errors
	 */
	composeNewAuditTemplate() {
		// set dragged/dropped order of elements
		const unit = document.getElementById("TemplateUnit").value,
			objectives = document.getElementById("TemplateObjectives").value,
			hint = document.getElementById("TemplateHint").value,
			data = new FormData();

		/**
		 * recursively get dragged/dropped order of elements and add to array
		 * @param {domNode} parent passed node to analyze contents
		 * @return {array} array of ordered this.newAuditQuestions
		 */
		function nodechildren(parent) {
			let content = [];
			[...parent.childNodes].forEach((node) => {
				if (node.draggable && node.children.item(1) && node.children.item(1).localName === "article") {
					[...node.childNodes].forEach((element) => {
						if (element.localName === "article") {
							content.push(nodechildren.call(this, element));
						}
					});
				} else {
					if (node.id in this.newAuditQuestions) {
						content.push(this.newAuditQuestions[node.id]);
					}
				}
			});
			return content;
		}
		const templateContent = nodechildren.call(this, document.querySelector("main"));
		if (unit && objectives && templateContent.length) {
			data.append("unit", unit);
			data.append("content", JSON.stringify(templateContent));
			data.append("objectives", objectives);
			data.append("hint", hint);
			return data;
		}
		new Toast(api._lang.GET("audit.audit.template.not_saved_missing"), "error");
		return null;
	}

	/**
	 * creates a full component by comparing the contents of newDocumentComponents and the actual order from view (after reordering/dragging)
	 * widgets not present in view will be skipped on this.newDocumentComponents
	 * @requires api, Toast
	 * @param {bool} raw_import
	 * @returns object|null
	 * @event Toast on errors
	 */
	composeNewComponent(raw_import = false) {
		let isForm = false,
			componentContent = [],
			name = document.getElementById("ComponentName").value,
			approve = document.getElementById("ComponentApprove").value,
			hidden = document.querySelector("[data-hiddenradio]") ? document.querySelector("[data-hiddenradio]").checked : false;

		/**
		 * recursively get dragged/dropped order of elements and add to array
		 * @param {domNode} parent passed node to analyze contents
		 * @return {array} array of ordered this.newDocumentComponents
		 */
		function nodechildren(parent) {
			let content = [],
				container;
			[...parent.childNodes].forEach((node) => {
				if (node.draggable) {
					container = node.children[1];
					if (container && container.localName === "article") {
						if (container.firstChild.localName === "section") content.push(nodechildren.call(this, container.firstChild));
						else content.push(nodechildren.call(this, container));
					} else {
						if (node.id in this.newDocumentComponents) {
							if (this.newDocumentComponents[node.id].attributes != undefined) delete this.newDocumentComponents[node.id].attributes["placeholder"];
							content.push(this.newDocumentComponents[node.id]);
							if (!["text", "links", "image"].includes(this.newDocumentComponents[node.id].type)) isForm = true;
						}
					}
				}
			});
			return content;
		}
		componentContent = nodechildren.call(this, document.querySelector("main"));
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
	}

	/**
	 * creates a document fetching the actual order of components (names) from view
	 * previously imported components not present in view will be ignored
	 * @requires api, Toast
	 * @returns object|null
	 * @event Toast on errors
	 */
	composeNewDocument() {
		// get document order and values
		const nodes = document.getElementById("main").children,
			name = document.getElementById("ComponentName").value,
			alias = document.getElementById("ComponentAlias").value,
			context = document.getElementById("ComponentContext").value,
			approve = document.getElementById("ComponentApprove").value,
			regulatory_context = document.getElementById("ComponentRegulatoryContext").value,
			restricted_access = document.getElementById("ComponentRestrictedAccess").value,
			hidden = document.querySelector("[data-hiddenradio]") ? document.querySelector("[data-hiddenradio]").checked : false,
			permitted_export = document.getElementById("ComponentPermittedExport") ? document.getElementById("ComponentPermittedExport").checked : false,
			data = new FormData();
		let content = [];
		// iterate over main node and gather data-name for components
		for (let i = 0; i < nodes.length; i++) {
			if (nodes[i].dataset && nodes[i].dataset.name) content.push(nodes[i].dataset.name);
		}
		if (name && context && context !== "0" && content.length) {
			data.append("name", name);
			data.append("alias", alias);
			data.append("context", context);
			data.append("content", JSON.stringify(content));
			data.append("hidden", hidden);
			data.append("approve", approve);
			data.append("regulatory_context", regulatory_context);
			data.append("permitted_export", permitted_export);
			data.append("restricted_access", restricted_access);
			return data;
		}
		new Toast(api._lang.GET("assemble.compose.document.document_not_saved_missing"), "error");
		return null;
	}

	/**
	 * creates a text template by comparing the contents of newTextElements and the actual order from view (after reordering/dragging)
	 * textcomponents not present in view will be skipped on this.newTextElements
	 * @requires api, Toast
	 * @returns object|null
	 * @event Toast on errors
	 */
	composeNewTextTemplate() {
		// set dragged/dropped order of elements
		const name = document.getElementById("TemplateName").value,
			unit = document.getElementById("TemplateUnit").value,
			hidden = document.querySelector("[data-hiddenradio]") ? document.querySelector("[data-hiddenradio]").checked : false,
			data = new FormData();

		/**
		 * recursively get dragged/dropped order of elements and add to array
		 * @param {domNode} parent passed node to analyze contents
		 * @return {array} array of ordered this.newTextElements
		 */
		function nodechildren(parent) {
			let content = [];
			[...parent.childNodes].forEach((node) => {
				if (node.draggable && node.children.item(1) && node.children.item(1).localName === "article") {
					[...node.childNodes].forEach((element) => {
						if (element.localName === "article") {
							content.push(nodechildren.call(this, element));
						}
					});
				} else {
					if (node.id in this.newTextElements) {
						content.push(this.newTextElements[node.id]);
					}
				}
			});
			return content;
		}
		const templateContent = nodechildren.call(this, document.querySelector("main"));
		if (name && unit && templateContent.length) {
			data.append("name", name);
			data.append("unit", unit);
			data.append("content", JSON.stringify(templateContent));
			data.append("hidden", hidden);
			return data;
		}
		new Toast(api._lang.GET("texttemplate.template.not_saved_missing"), "error");
		return null;
	}

	/**
	 * appends audit questions to view and newAuditQuestions after being fetched by api.js
	 * @requires Compose
	 * @param {object} chunks Assemble syntax
	 * @event append chunks
	 * @event update this.newAuditQuestions
	 */
	importAuditTemplate(chunks) {
		this.newAuditQuestions = {};
		let question = [];
		for (const questions of chunks) {
			question.push({
				type: "auditsection",
				attributes: {
					name: api._lang.GET("audit.audit.question"),
				},
				content: questions.question + "\n \n" + api._lang.GET("audit.audit.execute.regulatory") + "\n" + questions.regulatory + "\n \n" + api._lang.GET("audit.audit.hint") + "\n" + questions.hint,
			});
		}
		let chunk = new Compose({
			draggable: true,
			allowSections: false,
			content: [structuredClone(question)],
		});
		document.getElementById("main").append(...chunk.initializeSection());
		chunk.processAfterInsertion();
		for (let i = 0; i < chunks.length; i++) {
			this.newAuditQuestions[chunk.generatedElementIDs[i]] = chunks[i];
		}
	}

	/**
	 * appends a component to view and newDocumentComponents after being fetched by api.js
	 * @requires Compose
	 * @param {object} content Assemble syntax
	 * @event append component
	 * @event update this.newDocumentComponents
	 */
	importComponent(content) {
		/**
		 * recursively verify input names for not being forbidden
		 * @param {object} elements object
		 * @return bool
		 *
		 * also see backend _install.php
		 */
		function containsForbidden(elements) {
			let forbidden = false;
			for (const element of elements) {
				if (element.constructor.name === "Array") {
					forbidden = containsForbidden(element);
				} else {
					if (element.type && element.type !== "textsection" && element.attributes && element.attributes.name) {
						for (const pattern of Object.entries(api._settings.config.forbidden.names)) {
							if (element.attributes.name.match(new RegExp(pattern, "gm"))) {
								forbidden = { name: element.attributes.name, pattern: pattern };
								break;
							}
						}
						if (forbidden) break;
					}
				}
			}
			return forbidden;
		}
		const forbidden = containsForbidden(content.content);
		if (forbidden) {
			new _client.Dialog({ type: "alert", header: api._lang.GET("assemble.compose.component.raw"), render: api._lang.GET("assemble.compose.error_forbidden_name", { ":name": forbidden.name }) + " " + forbidden.pattern });
			return;
		}

		this.newDocumentComponents = {};
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
					assignIDs.call(this, container);
				} else {
					this.newDocumentComponents[elementIDs[i]] = container;
					i++;
				}
			}
		}
		assignIDs.call(this, content.content);
	}

	/**
	 * appends document components to view and newDocumentElements after being fetched by api.js
	 * @requires MetaCompose, api, Toast
	 * @param {object} content Assemble syntax
	 * @event append components
	 * @event update this.newDocumentComponents
	 */
	importDocument(content) {
		/**
		 * recursively count identify and signaturepad widgets within components
		 * @param {domNode} element domNode
		 * @event update this.componentIdentify, this.componentSignature
		 */
		function lookupIdentify(element) {
			for (const container of element) {
				if (container.constructor.name === "Array") {
					lookupIdentify.call(this, container);
				} else {
					if (container.type === "identify") this.componentIdentify++;
					if (container.type === "signature") this.componentSignature++;
				}
			}
		}

		// append component and make them draggable
		for (const component of content) {
			component.draggable = true;
			let current = new MetaCompose(component);
			document.getElementById("main").append(current.initializeSection());
			current.processAfterInsertion2();
			this.newDocumentElements.add(component.name);
			lookupIdentify.call(this, current.content);
		}

		// alert on forbidden widget count per document
		if (this.componentIdentify > 1) new Toast(api._lang.GET("assemble.compose.document.document_multiple_identify"), "error");
		if (this.componentSignature > 1) new Toast(api._lang.GET("assemble.compose.document.document_multiple_signature"), "error");
	}

	/**
	 * appends text chunks to view and newTextElements after being fetched by api.js
	 * @requires Compose
	 * @param {object} chunks Assemble syntax
	 * @event append chunks
	 * @event update this.newTextElements
	 */
	importTextTemplate(chunks) {
		this.newTextElements = {};
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
				this.newTextElements[chunk.generatedElementIDs[i]] = texts.keys[i];
			}
		}
	}

	/**
	 * populates the respective components or audit templates editor forms with widgets settings
	 * @param {domNode} widgetcontainer event
	 * @event populate components or audit templates editor form with values
	 * @see compose helper.composeNewAuditQuestionCallback and this.composeNewElementCallback()
	 */
	importWidget(widgetcontainer) {
		// this may not make sense for some types but idc. actually impossible for image type, so this is a exit case

		let type,
			targetform,
			forms = document.querySelectorAll("form"),
			newElements,
			element;

		type = widgetcontainer.childNodes[0].dataset.type;
		if (!type) {
			new Toast(api._lang.GET("assemble.compose.context_edit_error"), "error");
			return;
		}

		if (type === "auditsection") {
			newElements = this.newAuditQuestions;
			Object.keys(newElements).forEach((key) => {
				newElements[key].type = "auditsection";
			});
			targetform = forms[0].childNodes[2]; // article containing questions inputs
		} else {
			// document components
			newElements = this.newDocumentComponents;
			// detect appropriate form
			for (let f = 0; f < forms.length; f++) {
				if (forms[f].childNodes[0] && type === forms[f].childNodes[0].dataset.type) {
					targetform = forms[f];
					break;
				}
			}
		}
		if (!targetform) return;

		if (!(element = newElements[widgetcontainer.id])) {
			new Toast(api._lang.GET("assemble.compose.context_edit_error"), "error");
			return;
		}

		targetform.scrollIntoView();

		// import attributes/values
		if (["image"].includes(element.type)) return;

		if (element.type === type || (element.type === "identify" && type === "scanner")) {
			// actual inverse this.composeNewElementCallback
			let sibling,
				siblingName,
				multilistupdated = false;

			for (let s = 0; s < targetform.childNodes.length; s++) {
				sibling = targetform.childNodes[s];

				if (!["label", "input"].includes(sibling.localName)) {
					continue;
				}

				if (sibling.localName === "label" && sibling.childNodes.length) sibling = sibling.childNodes[sibling.childNodes.length - 1];
				if (!sibling.name) continue;
				siblingName = sibling.name.replace(/\(.*?\)|\[\]/g, "");
				if (siblingName === api._lang.GET("assemble.compose.component.field_hint") && element.hint) sibling.value = element.hint;
				if (siblingName === api._lang.GET("assemble.compose.component.texttemplate")) sibling.checked = Boolean(element.texttemplates);
				if (siblingName === api._lang.GET("assemble.compose.component.required")) sibling.checked = Boolean("required" in element.attributes && element.attributes.required);
				if (siblingName === api._lang.GET("assemble.compose.component.multiple")) sibling.checked = Boolean("multiple" in element.attributes && element.attributes.multiple);
				if (siblingName === api._lang.GET("assemble.compose.component.autocomplete")) sibling.checked = Boolean("autocomplete" in element && element.autocomplete);
				if (siblingName === api._lang.GET("assemble.compose.component.context_identify")) sibling.checked = Boolean(element.type === "identify");

				if (["links", "radio", "select", "checkbox"].includes(element.type)) {
					if (siblingName === api._lang.GET("assemble.compose.component.multilist_name")) {
						if (["links"].includes(element.type)) sibling.value = element.description;
						else sibling.value = element.attributes.name;
					}
					if (siblingName === api._lang.GET("assemble.compose.component.multilist_add_item") && !multilistupdated) {
						let deletesibling = sibling.parentNode; // label container
						while (deletesibling.nextElementSibling && deletesibling.nextElementSibling.localName !== "button") {
							deletesibling.nextElementSibling.remove();
						}
						const options = Object.keys(element.content);
						sibling.value = options[0];
						let next = [],
							all = [];
						for (let i = 1; i < options.length; i++) {
							next = this.cloneMultipleItems(sibling.parentNode, 0);
							for (const e of next) {
								if ("value" in e.childNodes[e.childNodes.length - 1]) e.childNodes[e.childNodes.length - 1].value = options[i];
							}
							all = all.concat(next);
						}
						sibling.parentNode.after(...all);
						multilistupdated = true; // otherwise targetform.childNodes expands indefinitely causing an overflow
						continue;
					}
				} else if (["file", "photo", "scanner", "signature", "identify"].includes(element.type)) {
					if (siblingName === api._lang.GET("assemble.compose.component.simple_element")) sibling.value = element.attributes.name;
				} else if (["textsection"].includes(element.type)) {
					if (siblingName === api._lang.GET("assemble.compose.component.textsection_description")) sibling.value = element.attributes.name;
					if (siblingName === api._lang.GET("assemble.compose.component.textsection_content")) if (element.content) sibling.value = element.content;
				} else if (["range"].includes(element.type)) {
					if (siblingName === api._lang.GET("assemble.compose.component.simple_element")) sibling.value = element.attributes.name;
					if (siblingName === api._lang.GET("assemble.compose.component.range_min")) sibling.value = element.attributes.min;
					if (siblingName === api._lang.GET("assemble.compose.component.range_max")) sibling.value = element.attributes.max;
					if (siblingName === api._lang.GET("assemble.compose.component.range_step")) sibling.value = element.attributes.step;
				} else {
					// ...input

					// document components
					if (siblingName === api._lang.GET("assemble.compose.component.field_name")) {
						sibling.value = element.attributes.name;
						continue;
					}
					// audit question input
					sibling.value = element[sibling.id.substring(1)];
				}
			}
		}
	}

	allowDrop(evnt) {
		evnt.preventDefault();
	}

	/**
	 * displays a context menu (right click) on draggable containers to allow for reordering or deleting event target
	 * @requires api, _client
	 * @param {event} evnt
	 * @event append context menu to body
	 */
	contextMenu(evnt) {
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
		img.onclick = () => {
			div.remove();
		};
		div.append(img);

		// edit option
		button = document.createElement("button");
		button.type = "button";
		button.classList.add("discreetButton");
		button.appendChild(document.createTextNode(api._lang.GET("assemble.compose.context_edit")));
		button.onclick = () => {
			if (!target) return;
			this.importWidget(target);
			div.remove();
		};
		div.append(button);

		// to top option
		button = document.createElement("button");
		button.type = "button";
		button.classList.add("discreetButton");
		button.appendChild(document.createTextNode(api._lang.GET("assemble.compose.context_2top")));
		button.onclick = () => {
			if (!target) return;
			if (target.previousElementSibling && target.previousElementSibling.draggable) {
				targetClone = target.cloneNode(true);
				let c;
				for (c = 0; c < target.parentNode.childNodes.length; c++) {
					if (target.parentNode.childNodes[c].draggable) break;
				}
				target.parentNode.insertBefore(targetClone, target.parentNode.childNodes[c]); // hidden form for submission and undraggable div with general selection, properties and delete area come before
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
		button.onclick = () => {
			if (!target) return;
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
		button.onclick = () => {
			if (!target) return;
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
		button.onclick = () => {
			if (!target) return;
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
			(button.onclick = () => {
				new _client.Dialog({ type: "confirm", header: api._lang.GET("assemble.compose.context_delete"), options: options }).then((confirmation) => {
					if (confirmation) {
						this.drop_delete(evnt);
					}
					div.remove();
				});
			});
		div.append(button);

		// append context menu
		document.querySelector("body").append(div);
		return false;
	}

	drag(evnt) {
		evnt.dataTransfer.setData("text", evnt.target.id);
		this.stopParentDropEvent = false;
	}

	/**
	 * inserts widgets or section before dropped upon widget or section
	 * @param {event} evnt
	 * @param {domNode} droppedUpon node
	 * @param {bool} allowSections if a dropped element is allowed to create a slider. only allowed for component editor, not documents or textrecommendations
	 * @event insert dragged element clone, reorder and propapbly nest nodes and delete original
	 */
	drop_insert(evnt, droppedUpon, allowSections) {
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
				container = this.create_draggable(container, false);
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
			container = this.create_draggable(container, false, false);

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
	}

	/**
	 * deletes widget or section if dropped on deletion area or confirmed context menu deletion
	 * @param {event} evnt
	 * @event removes event target
	 * @event updates this.componentIdentify and this.componentSignature count
	 */
	drop_delete(evnt) {
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
		 * @event updates this.componentIdentify and this.componentSignature count
		 */
		function nodechildren(parent) {
			[...parent.childNodes].forEach((node) => {
				if (["article", "div"].includes(node.localName)) {
					if (node.firstChild.localName === "section") nodechildren.call(this, node.firstChild);
					else nodechildren.call(this, node);
				} else {
					if (node.name && node.name.match(/IDENTIFY_BY_/g)) {
						if (document.getElementById("setIdentify")) document.getElementById("setIdentify").disabled = false;
						this.componentIdentify--;
					}
					if (node.id && node.id === "signaturecanvas") {
						const signatureformsibling = document.querySelector("form>label[data-type=signature");
						if (signatureformsibling)
							for (const node of signatureformsibling.parentNode) {
								if (node.dataset.type === "addblock") node.disabled = false;
							}
						this.componentSignature--;
					}
				}
			});
		}
		nodechildren.call(this, draggedElement);
		draggedElement.remove();
	}

	/**
	 * make an element draggable (widget, component, textchunk)
	 * @param {domNode} element to make draggable
	 * @param {bool} insertionArea for articles to have a hr handle to insert other element before the article
	 * @param {bool} allowSections if a dropped element is allowed to create a slider. only allowed for component editor, not documents or textrecommendations
	 * @returns {domNode} altered element
	 */
	create_draggable(element, insertionArea = true, allowSections = true) {
		element.id = getNextElementID();
		element.setAttribute("draggable", "true");
		element.setAttribute("ondragstart", "Composer.drag(event)");
		element.setAttribute("ondragover", "Composer.allowDrop(event); this.classList.add('draggableDocumentElementHover')");
		element.setAttribute("ondragleave", "this.classList.remove('draggableDocumentElementHover')");
		element.setAttribute("ondrop", "Composer.drop_insert(event, this, " + allowSections + "), this.classList.remove('draggableDocumentElementHover')");
		element.setAttribute("oncontextmenu", "Composer.contextMenu(event)");
		if (insertionArea) {
			const insertionArea = document.createElement("hr");
			insertionArea.setAttribute("ondragover", "this.classList.add('insertionAreaHover')");
			insertionArea.setAttribute("ondragleave", "this.classList.remove('insertionAreaHover')");
			insertionArea.classList.add("insertionArea");
			element.insertBefore(insertionArea, element.firstChild);
		}
		return element;
	}

	/**
	 * adds drop event to delete dragged upon nodes
	 * @param {domNode} element to make a trash area for deletion
	 * @event add events
	 */
	composer_add_trash(element) {
		element.setAttribute("ondragstart", "Composer.drag(event)");
		element.setAttribute("ondragover", "Composer.allowDrop(event)");
		element.setAttribute("ondrop", "Composer.drop_delete(event)");
	}

	/**
	 * adds drop event for reimport to widget creation forms
	 * @param {domNode} element to allow reimport event
	 * @event add events
	 */
	composer_component_document_reimportable(element) {
		element.setAttribute("ondragstart", "Composer.drag(event)");
		element.setAttribute("ondragover", "Composer.allowDrop(event)");
		element.setAttribute("ondrop", "Composer.drop_reimport(event)");
	}
}

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
						window.Composer.composer_component_document_reimportable(form);
						form.onsubmit = () => {
							window.Composer.composeNewElementCallback(form);
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
						div = window.Composer.create_draggable(div, undefined, this.allowSections);
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
				frame = window.Composer.create_draggable(frame, false, this.allowSections);
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
					container = window.Composer.create_draggable(container, undefined, this.allowSections);
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
				name: api._lang.GET("assemble.compose.component.availability"),
				hint: api._lang.GET("assemble.compose.component.availability_hint"),
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
				"data-loss": "prevent",
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
					"data-loss": "prevent",
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
					"data-loss": "prevent",
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
				"data-loss": "prevent",
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
					"data-loss": "prevent",
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
					"data-loss": "prevent",
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
					"data-loss": "prevent",
				},
				content: approve.content,
			};
			result = result.concat(...this.select());
		}

		// prefilling all inputs from selected component / document
		if (prefilled) {
			const options = {};
			options[api._lang.GET("assemble.compose.edit_available")] = !(hidden && Object.keys(hidden).length)
				? {
						checked: true,
				  }
				: {};
			options[api._lang.GET("assemble.compose.edit_hidden")] =
				hidden && Object.keys(hidden).length
					? {
							checked: true,
							"data-hiddenradio": "ComponentHidden",
							class: "red",
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
						  api._lang.GET("assemble.compose.edit_hidden_set", {
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
				onclick: std.action,
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
				name: api._lang.GET("assemble.compose.document.availability"),
				hint: api._lang.GET("assemble.compose.document.availability_hint"),
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
			type: "documentbutton",
			numeration: "prevent",
			attributes: {
				"data-loss": "prevent",
				name: api._lang.GET("assemble.compose.component.link_document"),
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
	 * creates editor to add a hr
	 * @returns {domNodes}
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_hr"
	 * 	}
	 */
	compose_hr() {
		let result = [...this.br()];
		this.currentElement = {
			type: "textsection",
			attributes: {
				"data-type": "hr",
				name: api._lang.GET("assemble.compose.component.hr"),
			},
		};
		result = result.concat(...this.textsection());
		this.currentElement = {
			attributes: {
				value: api._lang.GET("assemble.compose.component.hr"),
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
			numeration: "prevent",
			attributes: {
				"data-loss": "prevent",
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
			numeration: "prevent",
			attributes: {
				name: api._lang.GET("assemble.compose.component.field_name"),
				maxlength: 80,
				required: true,
				"data-loss": "prevent",
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textsection",
			numeration: "prevent",
			attributes: {
				name: api._lang.GET("assemble.compose.component.field_hint"),
				"data-loss": "prevent",
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			content: {},
		};
		this.currentElement.content[api._lang.GET("assemble.compose.component.required")] = {
			name: api._lang.GET("assemble.compose.component.required"),
			"data-loss": "prevent",
		};
		if (type.multiple)
			this.currentElement.content[api._lang.GET("assemble.compose.component.multiple")] = {
				name: api._lang.GET("assemble.compose.component.multiple"),
				"data-loss": "prevent",
			};
		if (type.autocomplete)
			this.currentElement.content[api._lang.GET("assemble.compose.component.autocomplete")] = {
				name: api._lang.GET("assemble.compose.component.autocomplete"),
				"data-loss": "prevent",
			};
		if (type.type === "textarea")
			this.currentElement.content[api._lang.GET("assemble.compose.component.texttemplate")] = {
				name: api._lang.GET("assemble.compose.component.texttemplate"),
				"data-loss": "prevent",
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
			numeration: "prevent",
			attributes: {
				name: api._lang.GET("assemble.compose.component.multilist_name"),
				maxlength: 80,
				required: true,
				"data-loss": "prevent",
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textsection",
			numeration: "prevent",
			attributes: {
				name: api._lang.GET("assemble.compose.component.field_hint"),
				"data-loss": "prevent",
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "text",
			attributes: {
				rows: 3,
				name: api._lang.GET("assemble.compose.component.multilist_add_item") + "[]",
				required: true,
				"data-loss": "prevent",
			},
		};
		if (type.type === "links") result = result.concat(...this.text());
		else result = result.concat(...this.textarea());
		this.currentElement = {
			attributes: {
				value: api._lang.GET("assemble.compose.component.multilist_add_item_button"),
				"data-type": "additem",
				type: "button",
				onclick: function () {
					for (const e of window.Composer.cloneMultipleItems(this, -1, 1)) this.parentNode.insertBefore(e, this);
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
					"data-loss": "prevent",
				};
			if (type.multiple !== undefined)
				this.currentElement.content[api._lang.GET("assemble.compose.component.multiple")] = {
					name: api._lang.GET("assemble.compose.component.multiple"),
					"data-loss": "prevent",
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
			numeration: "prevent",
			attributes: {
				name: api._lang.GET("assemble.compose.component.simple_element"),
				required: true,
				"data-loss": "prevent",
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textsection",
			numeration: "prevent",
			attributes: {
				name: api._lang.GET("assemble.compose.component.field_hint"),
				"data-loss": "prevent",
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "number",
			numeration: "prevent",
			attributes: {
				name: api._lang.GET("assemble.compose.component.range_min"),
				"data-loss": "prevent",
			},
			hint: api._lang.GET("assemble.compose.component.range_min_hint"),
		};
		result = result.concat(...this.number());
		this.currentElement = {
			type: "number",
			numeration: "prevent",
			attributes: {
				name: api._lang.GET("assemble.compose.component.range_max"),
				"data-loss": "prevent",
			},
			hint: api._lang.GET("assemble.compose.component.range_max_hint"),
		};
		result = result.concat(...this.number());
		this.currentElement = {
			type: "text",
			numeration: "prevent",
			attributes: {
				name: api._lang.GET("assemble.compose.component.range_step"),
				"data-loss": "prevent",
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
			numeration: "prevent",
			hint: api._lang.GET("assemble.compose.component.raw_hint"),
			attributes: {
				name: api._lang.GET("assemble.compose.component.raw"),
				id: "_compose_raw",
				"data-loss": "prevent",
			},
		};
		result = result.concat(...this.code());
		this.currentElement = {
			attributes: {
				value: api._lang.GET("assemble.compose.component.raw"),
				"data-type": "addblock",
				onclick: function () {
					if (document.getElementById("_compose_raw").value)
						try {
							window.Composer.importComponent({ content: JSON.parse(document.getElementById("_compose_raw").value) });
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
				onclick: function () {
					let component = window.Composer.composeNewComponent(true);
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
			numeration: "prevent",
			attributes: {
				name: api._lang.GET("assemble.compose.component.simple_element"),
				maxlength: 80,
				required: true,
				"data-loss": "prevent",
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textsection",
			numeration: "prevent",
			attributes: {
				name: api._lang.GET("assemble.compose.component.field_hint"),
				"data-loss": "prevent",
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
				"data-loss": "prevent",
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
				"data-loss": "prevent",
			};
		}
		if (type.type === "scanner") {
			this.currentElement.content[api._lang.GET("assemble.compose.component.context_identify")] = {
				name: api._lang.GET("assemble.compose.component.context_identify"),
				id: "setIdentify",
				"data-loss": "prevent",
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
	 * creates editor to add an stlpicker
	 * @see this.compose_simpleElement()
	 * @example this.currentElement
	 * ```json
	 * 	{
	 * 		"type" : "compose_stlpicker"
	 * 	}
	 */
	compose_stlpicker() {
		return this.compose_simpleElement({
			type: "stlpicker",
			description: api._lang.GET("assemble.compose.component.stlpicker"),
			required: "optional",
			multiple: "optional",
			hint: api._lang.GET("assemble.compose.component.stlpicker_hint"),
		});
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
			autocomplete: "optional",
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
			autocomplete: "optional",
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
			numeration: "prevent",
			attributes: {
				name: api._lang.GET("assemble.compose.component.textsection_description"),
				required: true,
				"data-loss": "prevent",
			},
		};
		result = result.concat(...this.text());
		this.currentElement = {
			type: "textarea",
			numeration: "prevent",
			attributes: {
				name: api._lang.GET("assemble.compose.component.textsection_content"),
				rows: 5,
				"data-loss": "prevent",
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
		if (this.setup.draggable) window.Composer.create_draggable(this.section, true, false);
		this.section.setAttribute("data-name", this.setup.name);
		if (this.setup.hidden) this.section.classList.add("hiddencomponent");
	}
}

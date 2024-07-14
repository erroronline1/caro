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

//test assembling
//api.getForms('qr', 'template', 'qr');

//test api
//api.user('user_new');

//test composing
/*const createForm = {
	"content": [
		[{
			"type": "text",
			"description": "what to do",
			"content": "choose available elements from this panel. set your parameters and add fields. advanced attributes (href, value, events, etc) have to be set in json-format with double-quotes. change your order by dragging the elements. during composing indicators for containers are not available. dragging is available on devies with mice only."
		}, {
			"form": true,
			"type": "compose_text",
			"description": "add an informative text"
		}, {
			"form": true,
			"type": "compose_textinput",
			"description": "add a single line text input"
		}, {
			"form": true,
			"type": "compose_textarea",
			"description": "add a multiline text input"
		}, {
			"form": true,
			"type": "compose_numberinput",
			"description": "add a number input"
		}, {
			"form": true,
			"type": "compose_dateinput",
			"description": "add a date input"
		}, {
			"form": true,
			"type": "compose_links",
			"description": "add a list of links"
		}, {
			"form": true,
			"type": "compose_radio",
			"description": "add a set of single selection options"
		}, {
			"form": true,
			"type": "compose_checkbox",
			"description": "add a set of multiple selection options"
		}, {
			"form": true,
			"type": "compose_select",
			"description": "add a dropdown"
		}, {
			"form": true,
			"type": "compose_file",
			"description": "add a file upload"
		}, {
			"form": true,
			"type": "compose_photo",
			"description": "add a photo upload"
		}, {
			"form": true,
			"type": "compose_signature",
			"description": "add a signature pad"
		}, {
			"form": true,
			"type": "compose_qr",
			"description": "add a qr scanner field"
		}],
		[{
			"type": "button",
			"description":"generate/update form object",
			"attributes": {
				"data-type": "generateupdate",
				"onclick": "console.log(compose_helper.composeNewForm())"
			}
		}],
		[{
			"type": "trash",
			"description": "drop panel here to delete"
		}]
	]
};
new Compose(createForm);
/*
const oldform = {
	"name":"oldform",
	"content": [
		[{
			"type": "radio",
			"description": "strength",
			"content": {
				"1": {},
				"2": {},
				"3": {},
				"4": {},
				"5": {}
			}
		}],
		[{
			"type": "select",
			"description": "strength",
			"content": {
				"1": {},
				"2": {},
				"3": {},
				"4": {},
				"5": {},
				"6": {}
			}
		}, {
			"type": "textinput",
			"description": "comment",
			"attributes": {
				"placeholder": " ",
				"onclick": "alert('hello')"
			}
		}],
		[{
			"type": "text",
			"description": "dfhgdfgh",
			"content": "dfghdfghdfhg"
		}]
	]
};
//compose_helper.importForm(oldform);

const oldform2 = {
	"name":"oldform2",
	"content": [
		[{
			"type": "text",
			"description": "dfhgdfgh1398172693876123",
			"content": "dfghdfghdfhg349871239487 123874 237641 723872o 3479817234871t234 87129873 4"
		}]
	]
};
*/
var unittestCreateForm = {
	content: [
		[
			{
				type: "scanner",
				attributes: {
					name: "Barcode scanner",
					multiple: true,
				},
			},
			{
				type: "textblock",
				description: "Informative text",
				content: "...to inform users about important things",
			},
			{ type: "file", attributes: { name: "Add an upload for files" }, hint: "You can opt for multiple file selection" },
			{ type: "photo", attributes: { name: "Contribute photos right from your camera" }, hint: "Desktop opens another file dialog though" },
			{ type: "signature", attributes: { name: "Add a signature pad" }, hint: "Unfortunately this is not certified, so keep a good measure" },
		],
		[
			{
				type: "text",
				attributes: {
					name: "A simple text input",
				},
				hint: "Clarify with hints",
			},
			{
				type: "textarea",
				attributes: { name: "A multiline text input field", value: "The button opens the templates where you can assemble your text, copy and paste it here" },
				texttemplates: true,
				hint: "With optional access to predefined text templates",
			},
			{ type: "number", attributes: { name: "A number input" }, hint: "By the way: all fields can be optional set to be required, so storing the form is possible with provided input only!" },
			{ type: "date", attributes: { name: "A date field makes input easy with the host systems date selection" } },
			{ type: "tel", attributes: { name: "The phone number field displays a keypad on mobile" } },
			{ type: "email", attributes: { name: "An email field does it similar" } },
		],
		[
			{
				type: "links",
				description: "Links can be added as well",
				content: {
					"https://www.mozilla.org/en-UD/firefox": { href: "https://www.mozilla.org/en-UD/firefox" },
					"https://www.microsoft.com/en-us/edge": { href: "https://www.microsoft.com/en-us/edge" },
					"https://www.google.com/intl/en/chrome": { href: "https://www.google.com/intl/en/chrome" },
				},
				hint: "Popular browsers",
			},
		],
		[
			{ type: "range", attributes: { min: 0, max: 10, value: 3, name: "An easy slider" }, hint: "You can define steps as well" },
			{ type: "checkbox", description: "Select from multiple options at a glance", hint: "Best used with less than 8 for a better user experience", content: { "This is great": {}, "This is awesome": {}, "This is superb": { checked: true } } },
			{
				type: "radio",
				attributes: { name: "Select only one option from a few at a glance" },
				hint: "Less than 8 recommended as well",
				content: {
					"Either this": {},
					"Or this": { checked: true },
					"Or even this": {},
				},
			},
			{
				type: "select",
				attributes: {
					name: "Select one option of many",
				},
				hint: "This opens a longer list that yould otherwise clutter up the screen",
				content: {
					"Selection one": {},
					"Selection two": {},
					"Selection three": {},
				},
			},
		],
		[
			{ type: "calendarbutton", attributes: { value: "Add event to calendar" } },
			{ type: "formbutton", attributes: { value: "Display SILLY FORM" } },
		],
	],
};

export function rendertest() {
	const render = new Assemble(unittestCreateForm);
	document.getElementById("main").replaceChildren(render.initializeSection());
	render.processAfterInsertion();
}

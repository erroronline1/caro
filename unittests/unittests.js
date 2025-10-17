/**
 * [CARO - Cloud Assisted Records and Operations](https://github.com/erroronline1/caro)  
 * Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)
 * 
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.  
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more details.  
 * You should have received a copy of the GNU Affero General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.  
 * Third party libraries are distributed under their own terms (see [readme.md](readme.md#external-libraries))
 */

import { Assemble } from "../js/assemble.js";

export function rendertest(element) {
	let tests = {
		documents: {
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
						type: "textsection",
						attributes: {
							name: "Informative text",
						},
						content: "...to inform users about important things",
					},
					{
						type: "file",
						attributes: {
							name: "Add an upload for files",
							multiple: true,
						},
						hint: "You can opt for multiple file selection",
					},
					{
						type: "photo",
						attributes: {
							name: "Contribute photos right from your camera",
							multiple: true,
						},
						hint: "Desktop opens another file dialog though",
					},
					{
						type: "signature",
						attributes: {
							name: "Add a signature pad",
						},
						hint: "Unfortunately this is not certified, so keep a good measure",
					},
				],
				[
					{
						type: "text",
						attributes: {
							name: "A simple text input",
							required: true,
						},
						hint: "Clarify with hints, required fields are marked with an asterisk",
					},
					{
						type: "textarea",
						attributes: {
							name: "A multiline text input field",
							value: "The button opens the templates where you can assemble your text, copy and paste it here",
						},
						texttemplates: true,
						hint: "With optional access to predefined text templates",
					},
					{
						type: "number",
						attributes: {
							name: "A number input",
						},
						hint: "By the way: all fields can be optional set to be required, so storing the form is possible with provided input only!",
					},
					{
						type: "date",
						attributes: {
							name: "A date field makes input easy with the host systems date selection",
						},
					},
					{
						type: "tel",
						attributes: {
							name: "The phone number field displays a keypad on mobile",
						},
					},
					{
						type: "email",
						attributes: {
							name: "An email field does it similar",
						},
					},
					{
						type: "productselection",
						attributes: {
							name: "Product selector",
							multiple: true,
						},
						hint: "Opens a dialog to select from the product database. If created as multiple another one will be appended.",
					},
					{
						type: "filereference",
						attributes: {
							name: "File reference",
							multiple: true,
						},
						hint: "Opens a Dialog to return the location of a file. If created as multiple another one will be appended.",
					},
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
					{
						type: "image",
						description: "Embedded images can be downloaded",
						attributes: {
							name: "CARO App Logo",
							url: "./media/favicon/windows11/SmallTile.scale-100.png",
						},
					},
				],
				[
					{
						type: "range",
						attributes: { min: 0, max: 10, value: 3, name: "An easy slider" },
						hint: "You can define steps as well",
					},
					{
						type: "checkbox",
						attributes: {
							name: "Select from multiple options at a glance",
						},
						hint: "Best used with less than 8 for a better user experience",
						content: { "This is great": {}, "This is awesome": {}, "This is superb": { checked: true } },
					},
					{
						type: "radio",
						attributes: {
							name: "Select only one option from a few at a glance",
						},
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
							multiple: true,
						},
						hint: "This opens a longer list that would otherwise clutter up the screen, optional multiple.",
						content: {
							"...": {},
							"Selection one": {},
							"Selection two": {},
							"Selection three": {},
						},
					},
				],
				[
					{
						type: "calendarbutton",
						attributes: {
							value: "Add event to calendar",
						},
						hint: "This will be prepared by the backend, even if nothing happens now.",
					},
					{ type: "hr" },
					{
						type: "documentbutton",
						attributes: {
							value: "Display SILLY FORM",
						},
						hint: "This will be prepared by the backend, even if nothing happens now.",
					},
				],
			],
		},
		documents_de: {
			content: [
				[
					{
						type: "scanner",
						attributes: {
							name: "Barcode Scanner",
							multiple: true,
						},
					},
					{
						type: "textsection",
						attributes: {
							name: "Informativer Text",
						},
						content: "...um Nutzer über wichtige Dinge zu informieren",
					},
					{
						type: "file",
						attributes: {
							name: "Füge einen Upload für eine Datei hinzu",
						},
						hint: "Du kannst auch einstellen, dass mehrere Dateien gewählt werden können",
					},
					{
						type: "photo",
						attributes: {
							name: "Stelle Fotos direkt mit der Kamera zur Verfügung",
						},
						hint: "Desktop PCs öffnen allerdings eine weitere Dateiauswahl",
					},
					{
						type: "signature",
						attributes: {
							name: "Füge ein Unterschriftenfeld hinzu",
						},
						hint: "Dies ist allerdings nicht zertifiziert, also liegt es bei dir",
					},
				],
				[
					{
						type: "text",
						attributes: {
							name: "Eine einfache Texteingabe",
							required: true,
						},
						hint: "Erläutere mit Hinweisen, erforderliche Felder werden mit einem Sternchen gekennzeichnet",
					},
					{
						type: "textarea",
						attributes: {
							name: "Ein mehrzeiliges Textfeld",
							value: "Der Knopf öffnet Textvorschläge die kopiert und hier eingefügt werden können",
						},
						texttemplates: true,
						hint: "Mit optionalem Zugriff auf Textvorschläge",
					},
					{
						type: "number",
						attributes: {
							name: "Eine Nummerneingabe",
						},
						hint: "Übrigens: alle Felder können als erforderlich markiert werden, dann kann das Formular nur gespeichert werden, wenn alles vollständig ist!",
					},
					{
						type: "date",
						attributes: {
							name: "Ein Datumfeld macht die Eingabe einfach wenn das Gerät eine Auswahl darstellt",
						},
					},
					{
						type: "tel",
						attributes: {
							name: "Das Telefonnummernfeld zeigt auf mobilen Geräten eine Nummerneingabe an",
						},
					},
					{
						type: "email",
						attributes: {
							name: "Das gilt auch für ein eMail-Feld",
						},
					},
					{
						type: "productselection",
						attributes: {
							name: "Produktwähler",
							multiple: true,
						},
						hint: "Öffnet einen Dialog um ein Produkt aus der Datenbank auszuwählen. Bei Mehrfachauswahl erscheint nach der Eingabe ein weiteres Feld.",
					},
					{
						type: "filereference",
						attributes: {
							name: "Dateiverweis",
							multiple: true,
						},
						hint: "Öffnet einen Dialog um den Speicherort einer Datei zu übernehmen. Bei Mehrfachauswahl erscheint nach der Eingabe ein weiteres Feld.",
					},
				],
				[
					{
						type: "links",
						description: "Verknüpfungen können auch hinzugefügt werden",
						content: {
							"https://www.mozilla.org/en-UD/firefox": { href: "https://www.mozilla.org/en-UD/firefox" },
							"https://www.microsoft.com/en-us/edge": { href: "https://www.microsoft.com/en-us/edge" },
							"https://www.google.com/intl/en/chrome": { href: "https://www.google.com/intl/en/chrome" },
						},
						hint: "Bekannte Browser",
					},
					{
						type: "image",
						description: "Eingebundene Bilder können heruntergeladen werden",
						attributes: {
							name: "CARO App Logo",
							url: "./media/favicon/windows11/SmallTile.scale-100.png",
						},
					},
				],
				[
					{
						type: "range",
						attributes: { min: 0, max: 10, value: 3, name: "Ein einfacher Regler" },
						hint: "Du kannst auch Schritte einstellen",
					},
					{
						type: "checkbox",
						attributes: {
							name: "Wähle aus mehreren Optionen auf einen Blick aus",
						},
						hint: "Am besten aber nicht mehr als 8 Optionen, sonst wird es unübersichtlich",
						content: {
							"Das ist toll": {},
							"Das ist großartig": {},
							"Das ist fabelhaft": { checked: true },
						},
					},
					{
						type: "radio",
						attributes: {
							name: "Wähle nur eine Option aus mehreren auf einen Blick",
						},
						hint: "Auch hier gilt: weniger ist mehr",
						content: {
							"Entweder dies": {},
							"oder das": { checked: true },
							"oder jenes": {},
						},
					},
					{
						type: "select",
						attributes: {
							name: "Wähle eine Option aus vielen aus",
						},
						hint: "Hier öffnet sich eine längere Liste die sonst den Bildschirm überfüllen würde",
						content: {
							"...": {},
							"Auswahl eins": {},
							"Auswahl zwei": {},
							"Auswahl drei": {},
						},
					},
				],
				[
					{
						type: "calendarbutton",
						attributes: {
							value: "Füge ein Ereignis zum Kalender hinzu",
						},
						hint: "Das wird vom Hintergrundsystem vorbereitet, auch wenn hier gerade nichts passiert.",
					},
					{ type: "hr" },
					{
						type: "documentbutton",
						attributes: {
							value: "Zeige TOLLES FORMULAR an",
						},
						hint: "Das wird vom Hintergrundsystem vorbereitet, auch wenn hier gerade nichts passiert.",
					},
				],
			],
		},
		app: {
			content: [
				[
					{
						type: "time",
						attributes: {
							name: "Time input",
						},
						hint: "Easy selection with hosts time picker, if available. As seen in timesheets.",
					},
					{
						type: "search",
						attributes: {
							name: "Search input",
						},
						hint: "Search keyboard and looking glass icon. As seen in new orders.",
					},
					{
						type: "filtered",
						attributes: {
							name: "Filter input",
						},
						hint: "Search keyboard and funnel icon. As seen in record summaries.",
					},
					{
						type: "checkbox2text",
						attributes: {
							name: "Selected modal checkbox names are chained comma separated, onblur",
						},
						hint: "Makes selections comprehensible while providing a single payload object. As seen in document manager.",
						content: {
							One: { value: "1" },
							Two: { value: 2 },
							Three: { value: "Three" },
						},
					},
					{
						type: "range",
						attributes: {
							name: "Range with datalist",
							value: 1,
							onchange: "console.log(this.value)",
						},
						datalist: ["A", "range", "with", "a", "datalist"],
						hint: "As seen in user manager. Look into console to see the new value.",
					},
					{
						type: "text",
						attributes: {
							name: "Text input with datalist",
						},
						datalist: ["A", "textinput", "with", "a", "datalist"],
						hint: "As seen in new conversation.",
					},
					{
						type: "code",
						attributes: {
							name: "Text input with linenumbers",
						},
						hint: "As seen in CSV filter management",
					},
					{
						type: "textarea",
						attributes: {
							name: "Text input without linenumbers",
							rows: 3,
						},
						hint: "As comparison",
					},
					{
						type: "textarea",
						attributes: {
							name: "Text with autocomplete",
							rows: 3,
						},
						hint: 'Start typing "lorem ipsum". As seen in risk management.',
						autocomplete: ["Lorem ipsum dolor sit amet, version one", "Lorem ipsum dolor sit amet, version two", "Lorem ipsum dolor sit amet, version three", "Lorem ipsum dolor sit amet, version four"],
					},
					{
						type: "checkbox",
						inline: true,
						content: {
							these: [],
							checkboxes: [],
							"are displayed": [],
							inline: [],
						},
					},
				],
				[
					{
						type: "textsection",
						content: "Available class options are red, orange, yellow and green",
						attributes: {
							class: "green",
							name: "Highlighted textsection descriptions are possible",
						},
					},
					{
						type: "auditsection",
						content: "Otherwise a proper textsection",
						attributes: {
							name: "Auditsections have a different icon",
						},
					},
					{
						type: "announcementsection",
						content: "Otherwise a proper textsection",
						attributes: {
							name: "Announcementsections have another different icon",
						},
					},
					{
						type: "image",
						description: "A barcode",
						attributes: {
							name: "Barcode CODE128",
							barcode: { value: "a CODE128 barcode", format: "CODE128" },
						},
					},
					{
						type: "image",
						description: "A QR-code",
						attributes: {
							name: "QR code",
							qrcode: "QR code",
						},
					},
				],
				[
					{
						type: "button",
						attributes: {
							value: "A generic button",
							type: "button",
						},
						hint: "Buttons can have hints too!",
					},
					{
						type: "deletebutton",
						attributes: {
							value: "A delete button",
							type: "button",
						},
					},
					{
						type: "submitbutton",
						attributes: {
							value: "A submit button",
							type: "button",
						},
						hint: "Submits the parent form",
					},
				],
				[
					{
						type: "tile",
						content: [
							{
								type: "textsection",
								attributes: {
									name: "textsection on a tile",
								},
								content: "This is half the parents elements width. As seen on landing page.",
							},
						],
					},
					{
						type: "tile",
						content: [
							{
								type: "text",
								attributes: {
									name: "Any element is possible",
								},
								hint: "Not necessarily sensible though.",
							},
						],
					},
				],
				[
					{
						type: "collapsible",
						content: [
							{
								type: "textsection",
								attributes: {
									name: "Collapsed content for reducing distractions",
								},
								content:
									"yet having all content available. As seen on approved orders. \\n \\n Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet,",
							},
						],
					},
				],
				[
					[
						{
							type: "textsection",
							attributes: {
								name: "Sliding panels are possible as well",
							},
							content: "Just try not to distract or obfuscate. Mobile view is just swiping, desktop view has supporting buttons.",
						},
					],
					[
						{
							type: "textsection",
							attributes: {
								name: "It's just another layer of nesting",
							},
							content: "All options above are valid. As seen in user manager",
						},
					],
				],
				[
					{
						type: "table",
						attributes: {
							name: "Table view of data",
							"data-type": "order",
						},
						content: [
							[{ c: "This" }, { c: "is" }, { c: "the" }, { c: "header" }, { c: "row" }],
							[{ c: "This" }, { c: "is" }, { c: "the" }, { c: "first" }, { c: "data row" }],
							[{ c: "Empty" }, { c: "objects" }, {}, { c: "left" }, { c: "blank" }],
							[
								{ c: "Long" },
								{ c: "text" },
								{ c: "is" },
								{ c: "possible" },
								{ c: "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua." },
							],
							[{ c: "Applied attributes" }, { c: "style and onclick", a: { style: "cursor: pointer;", onclick: "new _client.Toast('the table says hello')" } }, { c: "class red", a: { class: "red" } }],
						],
						hint: "As seen in orders. If selected.",
					},
				],
				[
					{
						type: "longtermplanning_timeline",
						attributes: {
							name: "Apprentices training schedule",
						},
						content: {
							"Apprentice 1": {
								"24-08-01": "rgb(255, 255, 255)",
								"24-08-16": "rgb(255, 255, 255)",
								"24-09-01": "rgb(119, 118, 123)",
								"24-09-16": "rgb(119, 118, 123)",
								"24-10-01": "rgb(80, 155, 176)",
								"24-10-16": "rgb(80, 155, 176)",
								"24-11-01": "rgb(80, 155, 176)",
								"24-11-16": "rgb(80, 155, 176)",
								"24-12-01": "rgb(80, 155, 176)",
								"24-12-16": "rgb(80, 155, 176)",
								"25-01-01": "rgb(255, 120, 0)",
								"25-01-16": "rgb(255, 120, 0)",
								"25-02-01": "rgb(255, 120, 0)",
								"25-02-15": "rgb(255, 120, 0)",
								"25-03-01": "rgb(255, 120, 0)",
								"25-03-16": "rgb(255, 0, 0)",
								"25-04-01": "rgb(255, 0, 0)",
								"25-04-16": "rgb(255, 0, 0)",
								"25-05-01": "rgb(255, 0, 0)",
								"25-05-16": "rgb(255, 0, 0)",
								"25-06-01": "rgb(255, 0, 0)",
								"25-06-16": "rgb(255, 0, 0)",
								"25-07-01": "rgb(255, 0, 0)",
								"25-07-16": "rgb(255, 0, 0)",
								"25-08-01": "rgb(255, 0, 0)",
								"25-08-16": "rgb(98, 160, 234)",
								"25-09-01": "rgb(98, 160, 234)",
								"25-09-16": "rgb(98, 160, 234)",
								"25-10-01": "rgb(98, 160, 234)",
								"25-10-16": "rgb(98, 160, 234)",
								"25-11-01": "rgb(98, 160, 234)",
								"25-11-16": "rgb(98, 160, 234)",
								"25-12-01": "rgb(98, 160, 234)",
								"25-12-16": "rgb(98, 160, 234)",
								"26-01-01": "rgb(98, 160, 234)",
								"26-01-16": "rgb(98, 160, 234)",
								"26-02-01": "rgb(97, 53, 131)",
								"26-02-15": "rgb(97, 53, 131)",
								"26-03-01": "rgb(0, 255, 0)",
								"26-03-16": "rgb(0, 255, 0)",
								"26-04-01": "rgb(0, 255, 0)",
								"26-04-16": "rgb(0, 255, 0)",
								"26-05-01": "rgb(0, 255, 0)",
								"26-05-16": "rgb(0, 255, 0)",
								"26-06-01": "rgb(220, 138, 221)",
								"26-06-16": "rgb(220, 138, 221)",
								"26-07-01": "rgb(255, 255, 0)",
								"26-07-16": "rgb(255, 255, 0)",
								"26-08-01": "rgb(255, 255, 0)",
								"26-08-16": "rgb(255, 255, 0)",
								"26-09-01": "rgb(255, 255, 0)",
								"26-09-16": "rgb(255, 255, 0)",
								"26-10-01": "rgb(255, 255, 0)",
								"26-10-16": "rgb(255, 255, 0)",
								"26-11-01": "rgb(255, 255, 0)",
								"26-11-16": "rgb(255, 255, 0)",
								"26-12-01": "rgb(255, 255, 0)",
								"26-12-16": "rgb(255, 255, 0)",
								"27-01-01": "rgb(255, 255, 0)",
								"27-01-16": "rgb(255, 255, 0)",
								"27-02-01": "rgb(152, 106, 68)",
								"27-02-15": "rgb(152, 106, 68)",
								"27-03-01": "rgb(152, 106, 68)",
								"27-03-16": "rgb(152, 106, 68)",
								"27-04-01": "rgb(152, 106, 68)",
								"27-04-16": "rgb(152, 106, 68)",
								"27-05-01": "rgb(152, 106, 68)",
								"27-05-16": "rgb(152, 106, 68)",
								"27-06-01": "rgb(152, 106, 68)",
								"27-06-16": "rgb(97, 53, 131)",
								"27-07-01": "rgb(97, 53, 131)",
								"27-07-16": "rgb(97, 53, 131)",
							},
							"Apprentice 2": {
								"24-08-01": "rgb(255, 255, 255)",
								"24-08-16": "rgb(255, 255, 255)",
								"24-09-01": "rgb(119, 118, 123)",
								"24-09-16": "rgb(119, 118, 123)",
								"24-10-01": "rgb(80, 155, 176)",
								"24-10-16": "rgb(80, 155, 176)",
								"24-11-01": "rgb(80, 155, 176)",
								"24-11-16": "rgb(80, 155, 176)",
								"24-12-01": "rgb(80, 155, 176)",
								"24-12-16": "rgb(80, 155, 176)",
								"25-01-01": "rgb(255, 0, 0)",
								"25-01-16": "rgb(255, 0, 0)",
								"25-02-01": "rgb(255, 0, 0)",
								"25-02-15": "rgb(255, 0, 0)",
								"25-03-01": "rgb(255, 0, 0)",
								"25-03-16": "rgb(255, 0, 0)",
								"25-04-01": "rgb(255, 0, 0)",
								"25-04-16": "rgb(255, 0, 0)",
								"25-05-01": "rgb(255, 0, 0)",
								"25-05-16": "rgb(255, 0, 0)",
								"25-06-01": "rgb(98, 160, 234)",
								"25-06-16": "rgb(98, 160, 234)",
								"25-07-01": "rgb(98, 160, 234)",
								"25-07-16": "rgb(98, 160, 234)",
								"25-08-01": "rgb(98, 160, 234)",
								"25-08-16": "rgb(98, 160, 234)",
								"25-09-01": "rgb(98, 160, 234)",
								"25-09-16": "rgb(98, 160, 234)",
								"25-10-01": "rgb(255, 255, 0)",
								"25-10-16": "rgb(255, 255, 0)",
								"25-11-01": "rgb(255, 255, 0)",
								"25-11-16": "rgb(255, 255, 0)",
								"25-12-01": "rgb(255, 255, 0)",
								"25-12-16": "rgb(255, 255, 0)",
								"26-01-01": "rgb(98, 160, 234)",
								"26-01-16": "rgb(98, 160, 234)",
								"26-02-01": "rgb(97, 53, 131)",
								"26-02-15": "rgb(97, 53, 131)",
								"26-03-01": "rgb(255, 255, 0)",
								"26-03-16": "rgb(255, 255, 0)",
								"26-04-01": "rgb(255, 255, 0)",
								"26-04-16": "rgb(255, 255, 0)",
								"26-05-01": "rgb(255, 255, 0)",
								"26-05-16": "rgb(255, 255, 0)",
								"26-06-01": "rgb(255, 255, 0)",
								"26-06-16": "rgb(255, 255, 0)",
								"26-07-01": "rgb(255, 120, 0)",
								"26-07-16": "rgb(255, 120, 0)",
								"26-08-01": "rgb(255, 120, 0)",
								"26-08-16": "rgb(255, 120, 0)",
								"26-09-01": "rgb(255, 120, 0)",
								"26-09-16": "rgb(0, 255, 0)",
								"26-10-01": "rgb(0, 255, 0)",
								"26-10-16": "rgb(0, 255, 0)",
								"26-11-01": "rgb(0, 255, 0)",
								"26-11-16": "rgb(0, 255, 0)",
								"26-12-01": "rgb(0, 255, 0)",
								"26-12-16": "rgb(0, 255, 0)",
								"27-01-01": "rgb(220, 138, 221)",
								"27-01-16": "rgb(220, 138, 221)",
								"27-02-01": "rgb(152, 106, 68)",
								"27-02-15": "rgb(152, 106, 68)",
								"27-03-01": "rgb(152, 106, 68)",
								"27-03-16": "rgb(152, 106, 68)",
								"27-04-01": "rgb(152, 106, 68)",
								"27-04-16": "rgb(152, 106, 68)",
								"27-05-01": "rgb(152, 106, 68)",
								"27-05-16": "rgb(152, 106, 68)",
								"27-06-01": "rgb(152, 106, 68)",
								"27-06-16": "rgb(97, 53, 131)",
								"27-07-01": "rgb(97, 53, 131)",
								"27-07-16": "rgb(97, 53, 131)",
							},
							"Apprentice 3": {
								"24-08-01": "rgb(255, 0, 0)",
								"24-08-16": "rgb(255, 0, 0)",
								"24-09-01": "rgb(255, 255, 0)",
								"24-09-16": "rgb(255, 255, 0)",
								"24-10-01": "rgb(255, 255, 0)",
								"24-10-16": "rgb(255, 255, 0)",
								"24-11-01": "rgb(255, 255, 0)",
								"24-11-16": "rgb(255, 255, 0)",
								"24-12-01": "rgb(255, 255, 0)",
								"24-12-16": "rgb(255, 255, 0)",
								"25-01-01": "rgb(98, 160, 234)",
								"25-01-16": "rgb(98, 160, 234)",
								"25-02-01": "rgb(97, 53, 131)",
								"25-02-15": "rgb(97, 53, 131)",
								"25-03-01": "rgb(255, 255, 0)",
								"25-03-16": "rgb(255, 255, 0)",
								"25-04-01": "rgb(255, 255, 0)",
								"25-04-16": "rgb(255, 255, 0)",
								"25-05-01": "rgb(255, 120, 0)",
								"25-05-16": "rgb(255, 120, 0)",
								"25-06-01": "rgb(255, 120, 0)",
								"25-06-16": "rgb(255, 120, 0)",
								"25-07-01": "rgb(255, 120, 0)",
								"25-07-16": "rgb(0, 255, 0)",
								"25-08-01": "rgb(0, 255, 0)",
								"25-08-16": "rgb(0, 255, 0)",
								"25-09-01": "rgb(0, 255, 0)",
								"25-09-16": "rgb(0, 255, 0)",
								"25-10-01": "rgb(0, 255, 0)",
								"25-10-16": "rgb(0, 255, 0)",
								"25-11-01": "rgb(0, 255, 0)",
								"25-11-16": "rgb(220, 138, 221)",
								"25-12-01": "rgb(220, 138, 221)",
								"25-12-16": "rgb(152, 106, 68)",
								"26-01-01": "rgb(152, 106, 68)",
								"26-01-16": "rgb(152, 106, 68)",
								"26-02-01": "rgb(152, 106, 68)",
								"26-02-15": "rgb(152, 106, 68)",
								"26-03-01": "rgb(152, 106, 68)",
								"26-03-16": "rgb(152, 106, 68)",
								"26-04-01": "rgb(152, 106, 68)",
								"26-04-16": "rgb(152, 106, 68)",
								"26-05-01": "rgb(152, 106, 68)",
								"26-05-16": "rgb(152, 106, 68)",
								"26-06-01": "rgb(152, 106, 68)",
								"26-06-16": "rgb(97, 53, 131)",
								"26-07-01": "rgb(97, 53, 131)",
								"26-07-16": "rgb(97, 53, 131)",
								"26-08-01": null,
								"26-08-16": null,
								"26-09-01": null,
								"26-09-16": null,
								"26-10-01": null,
								"26-10-16": null,
								"26-11-01": null,
								"26-11-16": null,
								"26-12-01": null,
								"26-12-16": null,
								"27-01-01": null,
								"27-01-16": null,
								"27-02-01": null,
								"27-02-15": null,
								"27-03-01": null,
								"27-03-16": null,
								"27-04-01": null,
								"27-04-16": null,
								"27-05-01": null,
								"27-05-16": null,
								"27-06-01": null,
								"27-06-16": null,
								"27-07-01": null,
								"27-07-16": null,
							},
							"Apprentice 4": {
								"24-08-01": "rgb(255, 120, 0)",
								"24-08-16": "rgb(255, 120, 0)",
								"24-09-01": "rgb(255, 120, 0)",
								"24-09-16": "rgb(255, 120, 0)",
								"24-10-01": "rgb(255, 0, 0)",
								"24-10-16": "rgb(255, 0, 0)",
								"24-11-01": "rgb(255, 0, 0)",
								"24-11-16": "rgb(255, 0, 0)",
								"24-12-01": "rgb(255, 0, 0)",
								"24-12-16": "rgb(255, 0, 0)",
								"25-01-01": "rgb(98, 160, 234)",
								"25-01-16": "rgb(98, 160, 234)",
								"25-02-01": "rgb(97, 53, 131)",
								"25-02-15": "rgb(97, 53, 131)",
								"25-03-01": "rgb(255, 0, 0)",
								"25-03-16": "rgb(255, 0, 0)",
								"25-04-01": "rgb(0, 255, 0)",
								"25-04-16": "rgb(0, 255, 0)",
								"25-05-01": "rgb(0, 255, 0)",
								"25-05-16": "rgb(0, 255, 0)",
								"25-06-01": "rgb(0, 255, 0)",
								"25-06-16": "rgb(0, 255, 0)",
								"25-07-01": "rgb(0, 255, 0)",
								"25-07-16": "rgb(0, 255, 0)",
								"25-08-01": "rgb(0, 255, 0)",
								"25-08-16": "rgb(220, 138, 221)",
								"25-09-01": "rgb(220, 138, 221)",
								"25-09-16": "rgb(255, 255, 0)",
								"25-10-01": "rgb(255, 255, 0)",
								"25-10-16": "rgb(255, 255, 0)",
								"25-11-01": "rgb(255, 255, 0)",
								"25-11-16": "rgb(255, 255, 0)",
								"25-12-01": "rgb(255, 255, 0)",
								"25-12-16": "rgb(152, 106, 68)",
								"26-01-01": "rgb(152, 106, 68)",
								"26-01-16": "rgb(152, 106, 68)",
								"26-02-01": "rgb(152, 106, 68)",
								"26-02-15": "rgb(152, 106, 68)",
								"26-03-01": "rgb(152, 106, 68)",
								"26-03-16": "rgb(152, 106, 68)",
								"26-04-01": "rgb(152, 106, 68)",
								"26-04-16": "rgb(152, 106, 68)",
								"26-05-01": "rgb(152, 106, 68)",
								"26-05-16": "rgb(152, 106, 68)",
								"26-06-01": "rgb(152, 106, 68)",
								"26-06-16": "rgb(97, 53, 131)",
								"26-07-01": "rgb(97, 53, 131)",
								"26-07-16": "rgb(97, 53, 131)",
								"26-08-01": null,
								"26-08-16": null,
								"26-09-01": null,
								"26-09-16": null,
								"26-10-01": null,
								"26-10-16": null,
								"26-11-01": null,
								"26-11-16": null,
								"26-12-01": null,
								"26-12-16": null,
								"27-01-01": null,
								"27-01-16": null,
								"27-02-01": null,
								"27-02-15": null,
								"27-03-01": null,
								"27-03-16": null,
								"27-04-01": null,
								"27-04-16": null,
								"27-05-01": null,
								"27-05-16": null,
								"27-06-01": null,
								"27-06-16": null,
								"27-07-01": null,
								"27-07-16": null,
							},
							"Apprentice 5": {
								"24-08-01": "rgb(220, 138, 221)",
								"24-08-16": "rgb(0, 255, 0)",
								"24-09-01": "rgb(0, 255, 0)",
								"24-09-16": "rgb(0, 255, 0)",
								"24-10-01": "rgb(0, 255, 0)",
								"24-10-16": "rgb(0, 255, 0)",
								"24-11-01": "rgb(0, 255, 0)",
								"24-11-16": "rgb(0, 255, 0)",
								"24-12-01": "rgb(0, 255, 0)",
								"24-12-16": "rgb(152, 106, 68)",
								"25-01-01": "rgb(152, 106, 68)",
								"25-01-16": "rgb(152, 106, 68)",
								"25-02-01": "rgb(152, 106, 68)",
								"25-02-15": "rgb(152, 106, 68)",
								"25-03-01": "rgb(152, 106, 68)",
								"25-03-16": "rgb(152, 106, 68)",
								"25-04-01": "rgb(152, 106, 68)",
								"25-04-16": "rgb(152, 106, 68)",
								"25-05-01": "rgb(152, 106, 68)",
								"25-05-16": "rgb(152, 106, 68)",
								"25-06-01": "rgb(152, 106, 68)",
								"25-06-16": "rgb(97, 53, 131)",
								"25-07-01": "rgb(97, 53, 131)",
								"25-07-16": "rgb(97, 53, 131)",
								"25-08-01": null,
								"25-08-16": null,
								"25-09-01": null,
								"25-09-16": null,
								"25-10-01": null,
								"25-10-16": null,
								"25-11-01": null,
								"25-11-16": null,
								"25-12-01": null,
								"25-12-16": null,
								"26-01-01": null,
								"26-01-16": null,
								"26-02-01": null,
								"26-02-15": null,
								"26-03-01": null,
								"26-03-16": null,
								"26-04-01": null,
								"26-04-16": null,
								"26-05-01": null,
								"26-05-16": null,
								"26-06-01": null,
								"26-06-16": null,
								"26-07-01": null,
								"26-07-16": null,
								"26-08-01": null,
								"26-08-16": null,
								"26-09-01": null,
								"26-09-16": null,
								"26-10-01": null,
								"26-10-16": null,
								"26-11-01": null,
								"26-11-16": null,
								"26-12-01": null,
								"26-12-16": null,
								"27-01-01": null,
								"27-01-16": null,
								"27-02-01": null,
								"27-02-15": null,
								"27-03-01": null,
								"27-03-16": null,
								"27-04-01": null,
								"27-04-16": null,
								"27-05-01": null,
								"27-05-16": null,
								"27-06-01": null,
								"27-06-16": null,
								"27-07-01": null,
								"27-07-16": null,
							},
							"Apprentice 6": {
								"24-08-01": "rgb(0, 255, 0)",
								"24-08-16": "rgb(0, 255, 0)",
								"24-09-01": "rgb(0, 255, 0)",
								"24-09-16": "rgb(0, 255, 0)",
								"24-10-01": "rgb(0, 255, 0)",
								"24-10-16": "rgb(0, 255, 0)",
								"24-11-01": "rgb(0, 255, 0)",
								"24-11-16": "rgb(220, 138, 221)",
								"24-12-01": "rgb(220, 138, 221)",
								"24-12-16": "rgb(152, 106, 68)",
								"25-01-01": "rgb(152, 106, 68)",
								"25-01-16": "rgb(152, 106, 68)",
								"25-02-01": "rgb(152, 106, 68)",
								"25-02-15": "rgb(152, 106, 68)",
								"25-03-01": "rgb(152, 106, 68)",
								"25-03-16": "rgb(152, 106, 68)",
								"25-04-01": "rgb(152, 106, 68)",
								"25-04-16": "rgb(152, 106, 68)",
								"25-05-01": "rgb(152, 106, 68)",
								"25-05-16": "rgb(152, 106, 68)",
								"25-06-01": "rgb(152, 106, 68)",
								"25-06-16": "rgb(97, 53, 131)",
								"25-07-01": "rgb(97, 53, 131)",
								"25-07-16": "rgb(97, 53, 131)",
								"25-08-01": null,
								"25-08-16": null,
								"25-09-01": null,
								"25-09-16": null,
								"25-10-01": null,
								"25-10-16": null,
								"25-11-01": null,
								"25-11-16": null,
								"25-12-01": null,
								"25-12-16": null,
								"26-01-01": null,
								"26-01-16": null,
								"26-02-01": null,
								"26-02-15": null,
								"26-03-01": null,
								"26-03-16": null,
								"26-04-01": null,
								"26-04-16": null,
								"26-05-01": null,
								"26-05-16": null,
								"26-06-01": null,
								"26-06-16": null,
								"26-07-01": null,
								"26-07-16": null,
								"26-08-01": null,
								"26-08-16": null,
								"26-09-01": null,
								"26-09-16": null,
								"26-10-01": null,
								"26-10-16": null,
								"26-11-01": null,
								"26-11-16": null,
								"26-12-01": null,
								"26-12-16": null,
								"27-01-01": null,
								"27-01-16": null,
								"27-02-01": null,
								"27-02-15": null,
								"27-03-01": null,
								"27-03-16": null,
								"27-04-01": null,
								"27-04-16": null,
								"27-05-01": null,
								"27-05-16": null,
								"27-06-01": null,
								"27-06-16": null,
								"27-07-01": null,
								"27-07-16": null,
							},
						},
						hint: "As seen in longterm planning.",
					},
					{
						type: "longtermplanning_topics",
						content: {
							"Introducion with mentor": "#ffffff",
							"Metal basics": "#77767b",
							"Plastics basiscs": "#509bb0",
							"Silicone basics": "#dc8add",
							"Orthotics 1": "#ffff00",
							"Orthotics 2": "#62a0ea",
							"Prosthetics 1": "#ff0000",
							"Prosthetics 2": "#00ff00",
							Rehabilitation: "#ff7800",
							"Exam preparation": "#613583",
							Specialisation: "#986a44",
						},
						hint: "As seen in longterm planning.",
					},
				],
				[
					{
						type: "undefined",
						description: "This is deliberately to fail by the assembler.",
					},
				],
			],
		},
		app_de: {
			content: [
				[
					{
						type: "time",
						attributes: {
							name: "Zeitangabe",
						},
						hint: "Einfache Zeitangabe mit einer Wahlmaske des Geräts, sofern verfügbar. Verwendet in der Zeiterfassung.",
					},
					{
						type: "search",
						attributes: {
							name: "Sucheingabe",
						},
						hint: "Suchtastatur und Lupensymbol, verwendet bei neuen Bestellungen.",
					},
					{
						type: "filtered",
						attributes: {
							name: "Filtereingabe",
						},
						hint: "Suchtastatur und Trichtersymbol, verwendet bei Doumentationen.",
					},
					{
						type: "checkbox2text",
						attributes: {
							name: "Die ausgewählten Optionen des Popups werden mit Kommata verkettet",
						},
						hint: "Vereinfacht eine Auswahlentscheidung und stellt ein einfaches Datenobjekt bereit, verwendet in der Formularverwaltung.",
						content: {
							One: { value: "1" },
							Two: { value: 2 },
							Three: { value: "Drei" },
						},
					},
					{
						type: "range",
						attributes: {
							name: "Regler mit Datenliste",
							value: 1,
							onchange: "console.log(this.value)",
						},
						datalist: ["Ein", "Regler", "mit", "einer", "Datenliste"],
						hint: "Verwendet in der Nutzerverwaltung. Schaue in die Konsole für den neuen Wert.",
					},
					{
						type: "text",
						attributes: {
							name: "Texteingabe mit einer Datenliste",
						},
						datalist: ["Eine", "Texteingabe", "mit", "einer", "Datenliste"],
						hint: "Verwendet in Konversationen.",
					},
					{
						type: "code",
						attributes: {
							name: "Texteingabe mit Zeilennummerierung",
						},
						hint: "Verwendet bei CSV-Filtern",
					},
					{
						type: "textarea",
						attributes: {
							name: "Texteingabe ohne Zeilennummerierung",
							rows: 3,
						},
						hint: "Als Vergleich",
					},
					{
						type: "textarea",
						attributes: {
							name: "Texteingabe mit automatischer Vervollständigung",
							rows: 3,
						},
						hint: 'Starte mit "lorem ipsum". Verwendet im Risikomanagement',
						autocomplete: ["Lorem ipsum dolor sit amet, version Eins", "Lorem ipsum dolor sit amet, version Zwei", "Lorem ipsum dolor sit amet, version Drei", "Lorem ipsum dolor sit amet, version Vier"],
					},
					{
						type: "checkbox",
						inline: true,
						content: {
							"Diese Boxen": [],
							werden: [],
							eingereiht: [],
							angezeigt: [],
						},
					},
				],
				[
					{
						type: "textsection",
						content: "Verfügbare CSS-Klassen sind red, orange, yellow and green",
						attributes: {
							class: "green",
							name: "Hervorgehobene Textüberschriften sind möglich",
						},
					},
					{
						type: "auditsection",
						content: "Ansonsten eine normale textsection",
						attributes: {
							name: "Auditsections haben ein anderes Symbolbild",
						},
					},
					{
						type: "announcementsection",
						content: "Ansonsten eine normale textsection",
						attributes: {
							name: "Announcmentsections haben noch ein anderes Symbolbild",
						},
					},
					{
						type: "image",
						description: "Ein Barcode",
						attributes: {
							name: "Barcode CODE128",
							barcode: { value: "Ein CODE128 Barcode", format: "CODE128" },
						},
					},
					{
						type: "image",
						description: "Ein QR-Code",
						attributes: {
							name: "QR Code",
							qrcode: "QR Code",
						},
					},
				],
				[
					{
						type: "button",
						attributes: {
							value: "Ein einfacher Knopf",
							type: "button",
						},
						hint: "Buttons können auch Hinweise haben!",
					},
					{
						type: "deletebutton",
						attributes: {
							value: "Ein Lösch-Knopf",
							type: "button",
						},
					},
					{
						type: "submitbutton",
						attributes: {
							value: "Ein Absendeknopf",
							type: "button",
						},
						hint: "Sendet das umgebende Formular ab",
					},
				],
				[
					{
						type: "tile",
						content: [
							{
								type: "textsection",
								attributes: {
									name: "textsection auf einer Kachel",
								},
								content: "Dies ist die hälte der Parent-Element-Breite, verwendet auf der Startseite",
							},
						],
					},
					{
						type: "tile",
						content: [
							{
								type: "text",
								attributes: {
									name: "Jeses Element ist möglich",
								},
								hint: "aber nicht unbedingt sinnvoll.",
							},
						],
					},
				],
				[
					{
						type: "collapsible",
						content: [
							{
								type: "textsection",
								attributes: {
									name: "Zusammengeschobener Inhalt um Ablenkung zu reduzieren",
								},
								content:
									"und dennoch alles da. Verwendet bei freigegebenen Bestellungen. \\n \\n Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet,",
							},
						],
					},
				],
				[
					[
						{
							type: "textsection",
							attributes: {
								name: "Verschiebliche Tafeln sind ebenfalls möglich",
							},
							content: "Versuche nur möglichst nicht zu verwirren oder abzulenken. Auf mobilen Geräten muss nur gewischt werden, die Desktop-Ansicht hat auch Knöpfe.",
						},
					],
					[
						{
							type: "textsection",
							attributes: {
								name: "Dies ist nur eine zusätzlich Verschachtelungsebene",
							},
							content: "Alle Optionen sind möglich. Verwendet in der Nutzerverwaltung.",
						},
					],
				],
				[
					{
						type: "table",
						attributes: {
							name: "Tabellarische Datenanzeige",
							"data-type": "order",
						},
						content: [
							[{ c: "Dies" }, { c: "ist" }, { c: "die" }, { c: "Kopf" }, { c: "zeile" }],
							[{ c: "Dies" }, { c: "ist" }, { c: "die" }, { c: "erste" }, { c: "Datenreihe" }],
							[{ c: "Leere" }, { c: "Objekte" }, {}, { c: "frei" }, { c: "gelassen" }],
							[
								{ c: "Langer" },
								{ c: "Text" },
								{ c: "ist" },
								{ c: "möglich" },
								{ c: "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua." },
							],
							[{ c: "Angewandte Eigenschaften" }, { c: "style und onclick", a: { style: "cursor: pointer;", onclick: "new _client.Toast('Die Tabelle sagt hallo')" } }, { c: "class red", a: { class: "red" } }],
						],
						hint: "Verwendet bei Bestellungen. Falls gewählt.",
					},
				],
				[
					{
						type: "longtermplanning_timeline",
						attributes: {
							name: "Ausbildungsplan",
						},
						content: {
							"Azubi 1": {
								"24-08-01": "rgb(255, 255, 255)",
								"24-08-16": "rgb(255, 255, 255)",
								"24-09-01": "rgb(119, 118, 123)",
								"24-09-16": "rgb(119, 118, 123)",
								"24-10-01": "rgb(80, 155, 176)",
								"24-10-16": "rgb(80, 155, 176)",
								"24-11-01": "rgb(80, 155, 176)",
								"24-11-16": "rgb(80, 155, 176)",
								"24-12-01": "rgb(80, 155, 176)",
								"24-12-16": "rgb(80, 155, 176)",
								"25-01-01": "rgb(255, 120, 0)",
								"25-01-16": "rgb(255, 120, 0)",
								"25-02-01": "rgb(255, 120, 0)",
								"25-02-15": "rgb(255, 120, 0)",
								"25-03-01": "rgb(255, 120, 0)",
								"25-03-16": "rgb(255, 0, 0)",
								"25-04-01": "rgb(255, 0, 0)",
								"25-04-16": "rgb(255, 0, 0)",
								"25-05-01": "rgb(255, 0, 0)",
								"25-05-16": "rgb(255, 0, 0)",
								"25-06-01": "rgb(255, 0, 0)",
								"25-06-16": "rgb(255, 0, 0)",
								"25-07-01": "rgb(255, 0, 0)",
								"25-07-16": "rgb(255, 0, 0)",
								"25-08-01": "rgb(255, 0, 0)",
								"25-08-16": "rgb(98, 160, 234)",
								"25-09-01": "rgb(98, 160, 234)",
								"25-09-16": "rgb(98, 160, 234)",
								"25-10-01": "rgb(98, 160, 234)",
								"25-10-16": "rgb(98, 160, 234)",
								"25-11-01": "rgb(98, 160, 234)",
								"25-11-16": "rgb(98, 160, 234)",
								"25-12-01": "rgb(98, 160, 234)",
								"25-12-16": "rgb(98, 160, 234)",
								"26-01-01": "rgb(98, 160, 234)",
								"26-01-16": "rgb(98, 160, 234)",
								"26-02-01": "rgb(97, 53, 131)",
								"26-02-15": "rgb(97, 53, 131)",
								"26-03-01": "rgb(0, 255, 0)",
								"26-03-16": "rgb(0, 255, 0)",
								"26-04-01": "rgb(0, 255, 0)",
								"26-04-16": "rgb(0, 255, 0)",
								"26-05-01": "rgb(0, 255, 0)",
								"26-05-16": "rgb(0, 255, 0)",
								"26-06-01": "rgb(220, 138, 221)",
								"26-06-16": "rgb(220, 138, 221)",
								"26-07-01": "rgb(255, 255, 0)",
								"26-07-16": "rgb(255, 255, 0)",
								"26-08-01": "rgb(255, 255, 0)",
								"26-08-16": "rgb(255, 255, 0)",
								"26-09-01": "rgb(255, 255, 0)",
								"26-09-16": "rgb(255, 255, 0)",
								"26-10-01": "rgb(255, 255, 0)",
								"26-10-16": "rgb(255, 255, 0)",
								"26-11-01": "rgb(255, 255, 0)",
								"26-11-16": "rgb(255, 255, 0)",
								"26-12-01": "rgb(255, 255, 0)",
								"26-12-16": "rgb(255, 255, 0)",
								"27-01-01": "rgb(255, 255, 0)",
								"27-01-16": "rgb(255, 255, 0)",
								"27-02-01": "rgb(152, 106, 68)",
								"27-02-15": "rgb(152, 106, 68)",
								"27-03-01": "rgb(152, 106, 68)",
								"27-03-16": "rgb(152, 106, 68)",
								"27-04-01": "rgb(152, 106, 68)",
								"27-04-16": "rgb(152, 106, 68)",
								"27-05-01": "rgb(152, 106, 68)",
								"27-05-16": "rgb(152, 106, 68)",
								"27-06-01": "rgb(152, 106, 68)",
								"27-06-16": "rgb(97, 53, 131)",
								"27-07-01": "rgb(97, 53, 131)",
								"27-07-16": "rgb(97, 53, 131)",
							},
							"Azubi 2": {
								"24-08-01": "rgb(255, 255, 255)",
								"24-08-16": "rgb(255, 255, 255)",
								"24-09-01": "rgb(119, 118, 123)",
								"24-09-16": "rgb(119, 118, 123)",
								"24-10-01": "rgb(80, 155, 176)",
								"24-10-16": "rgb(80, 155, 176)",
								"24-11-01": "rgb(80, 155, 176)",
								"24-11-16": "rgb(80, 155, 176)",
								"24-12-01": "rgb(80, 155, 176)",
								"24-12-16": "rgb(80, 155, 176)",
								"25-01-01": "rgb(255, 0, 0)",
								"25-01-16": "rgb(255, 0, 0)",
								"25-02-01": "rgb(255, 0, 0)",
								"25-02-15": "rgb(255, 0, 0)",
								"25-03-01": "rgb(255, 0, 0)",
								"25-03-16": "rgb(255, 0, 0)",
								"25-04-01": "rgb(255, 0, 0)",
								"25-04-16": "rgb(255, 0, 0)",
								"25-05-01": "rgb(255, 0, 0)",
								"25-05-16": "rgb(255, 0, 0)",
								"25-06-01": "rgb(98, 160, 234)",
								"25-06-16": "rgb(98, 160, 234)",
								"25-07-01": "rgb(98, 160, 234)",
								"25-07-16": "rgb(98, 160, 234)",
								"25-08-01": "rgb(98, 160, 234)",
								"25-08-16": "rgb(98, 160, 234)",
								"25-09-01": "rgb(98, 160, 234)",
								"25-09-16": "rgb(98, 160, 234)",
								"25-10-01": "rgb(255, 255, 0)",
								"25-10-16": "rgb(255, 255, 0)",
								"25-11-01": "rgb(255, 255, 0)",
								"25-11-16": "rgb(255, 255, 0)",
								"25-12-01": "rgb(255, 255, 0)",
								"25-12-16": "rgb(255, 255, 0)",
								"26-01-01": "rgb(98, 160, 234)",
								"26-01-16": "rgb(98, 160, 234)",
								"26-02-01": "rgb(97, 53, 131)",
								"26-02-15": "rgb(97, 53, 131)",
								"26-03-01": "rgb(255, 255, 0)",
								"26-03-16": "rgb(255, 255, 0)",
								"26-04-01": "rgb(255, 255, 0)",
								"26-04-16": "rgb(255, 255, 0)",
								"26-05-01": "rgb(255, 255, 0)",
								"26-05-16": "rgb(255, 255, 0)",
								"26-06-01": "rgb(255, 255, 0)",
								"26-06-16": "rgb(255, 255, 0)",
								"26-07-01": "rgb(255, 120, 0)",
								"26-07-16": "rgb(255, 120, 0)",
								"26-08-01": "rgb(255, 120, 0)",
								"26-08-16": "rgb(255, 120, 0)",
								"26-09-01": "rgb(255, 120, 0)",
								"26-09-16": "rgb(0, 255, 0)",
								"26-10-01": "rgb(0, 255, 0)",
								"26-10-16": "rgb(0, 255, 0)",
								"26-11-01": "rgb(0, 255, 0)",
								"26-11-16": "rgb(0, 255, 0)",
								"26-12-01": "rgb(0, 255, 0)",
								"26-12-16": "rgb(0, 255, 0)",
								"27-01-01": "rgb(220, 138, 221)",
								"27-01-16": "rgb(220, 138, 221)",
								"27-02-01": "rgb(152, 106, 68)",
								"27-02-15": "rgb(152, 106, 68)",
								"27-03-01": "rgb(152, 106, 68)",
								"27-03-16": "rgb(152, 106, 68)",
								"27-04-01": "rgb(152, 106, 68)",
								"27-04-16": "rgb(152, 106, 68)",
								"27-05-01": "rgb(152, 106, 68)",
								"27-05-16": "rgb(152, 106, 68)",
								"27-06-01": "rgb(152, 106, 68)",
								"27-06-16": "rgb(97, 53, 131)",
								"27-07-01": "rgb(97, 53, 131)",
								"27-07-16": "rgb(97, 53, 131)",
							},
							"Azubi 3": {
								"24-08-01": "rgb(255, 0, 0)",
								"24-08-16": "rgb(255, 0, 0)",
								"24-09-01": "rgb(255, 255, 0)",
								"24-09-16": "rgb(255, 255, 0)",
								"24-10-01": "rgb(255, 255, 0)",
								"24-10-16": "rgb(255, 255, 0)",
								"24-11-01": "rgb(255, 255, 0)",
								"24-11-16": "rgb(255, 255, 0)",
								"24-12-01": "rgb(255, 255, 0)",
								"24-12-16": "rgb(255, 255, 0)",
								"25-01-01": "rgb(98, 160, 234)",
								"25-01-16": "rgb(98, 160, 234)",
								"25-02-01": "rgb(97, 53, 131)",
								"25-02-15": "rgb(97, 53, 131)",
								"25-03-01": "rgb(255, 255, 0)",
								"25-03-16": "rgb(255, 255, 0)",
								"25-04-01": "rgb(255, 255, 0)",
								"25-04-16": "rgb(255, 255, 0)",
								"25-05-01": "rgb(255, 120, 0)",
								"25-05-16": "rgb(255, 120, 0)",
								"25-06-01": "rgb(255, 120, 0)",
								"25-06-16": "rgb(255, 120, 0)",
								"25-07-01": "rgb(255, 120, 0)",
								"25-07-16": "rgb(0, 255, 0)",
								"25-08-01": "rgb(0, 255, 0)",
								"25-08-16": "rgb(0, 255, 0)",
								"25-09-01": "rgb(0, 255, 0)",
								"25-09-16": "rgb(0, 255, 0)",
								"25-10-01": "rgb(0, 255, 0)",
								"25-10-16": "rgb(0, 255, 0)",
								"25-11-01": "rgb(0, 255, 0)",
								"25-11-16": "rgb(220, 138, 221)",
								"25-12-01": "rgb(220, 138, 221)",
								"25-12-16": "rgb(152, 106, 68)",
								"26-01-01": "rgb(152, 106, 68)",
								"26-01-16": "rgb(152, 106, 68)",
								"26-02-01": "rgb(152, 106, 68)",
								"26-02-15": "rgb(152, 106, 68)",
								"26-03-01": "rgb(152, 106, 68)",
								"26-03-16": "rgb(152, 106, 68)",
								"26-04-01": "rgb(152, 106, 68)",
								"26-04-16": "rgb(152, 106, 68)",
								"26-05-01": "rgb(152, 106, 68)",
								"26-05-16": "rgb(152, 106, 68)",
								"26-06-01": "rgb(152, 106, 68)",
								"26-06-16": "rgb(97, 53, 131)",
								"26-07-01": "rgb(97, 53, 131)",
								"26-07-16": "rgb(97, 53, 131)",
								"26-08-01": null,
								"26-08-16": null,
								"26-09-01": null,
								"26-09-16": null,
								"26-10-01": null,
								"26-10-16": null,
								"26-11-01": null,
								"26-11-16": null,
								"26-12-01": null,
								"26-12-16": null,
								"27-01-01": null,
								"27-01-16": null,
								"27-02-01": null,
								"27-02-15": null,
								"27-03-01": null,
								"27-03-16": null,
								"27-04-01": null,
								"27-04-16": null,
								"27-05-01": null,
								"27-05-16": null,
								"27-06-01": null,
								"27-06-16": null,
								"27-07-01": null,
								"27-07-16": null,
							},
							"Azubi 4": {
								"24-08-01": "rgb(255, 120, 0)",
								"24-08-16": "rgb(255, 120, 0)",
								"24-09-01": "rgb(255, 120, 0)",
								"24-09-16": "rgb(255, 120, 0)",
								"24-10-01": "rgb(255, 0, 0)",
								"24-10-16": "rgb(255, 0, 0)",
								"24-11-01": "rgb(255, 0, 0)",
								"24-11-16": "rgb(255, 0, 0)",
								"24-12-01": "rgb(255, 0, 0)",
								"24-12-16": "rgb(255, 0, 0)",
								"25-01-01": "rgb(98, 160, 234)",
								"25-01-16": "rgb(98, 160, 234)",
								"25-02-01": "rgb(97, 53, 131)",
								"25-02-15": "rgb(97, 53, 131)",
								"25-03-01": "rgb(255, 0, 0)",
								"25-03-16": "rgb(255, 0, 0)",
								"25-04-01": "rgb(0, 255, 0)",
								"25-04-16": "rgb(0, 255, 0)",
								"25-05-01": "rgb(0, 255, 0)",
								"25-05-16": "rgb(0, 255, 0)",
								"25-06-01": "rgb(0, 255, 0)",
								"25-06-16": "rgb(0, 255, 0)",
								"25-07-01": "rgb(0, 255, 0)",
								"25-07-16": "rgb(0, 255, 0)",
								"25-08-01": "rgb(0, 255, 0)",
								"25-08-16": "rgb(220, 138, 221)",
								"25-09-01": "rgb(220, 138, 221)",
								"25-09-16": "rgb(255, 255, 0)",
								"25-10-01": "rgb(255, 255, 0)",
								"25-10-16": "rgb(255, 255, 0)",
								"25-11-01": "rgb(255, 255, 0)",
								"25-11-16": "rgb(255, 255, 0)",
								"25-12-01": "rgb(255, 255, 0)",
								"25-12-16": "rgb(152, 106, 68)",
								"26-01-01": "rgb(152, 106, 68)",
								"26-01-16": "rgb(152, 106, 68)",
								"26-02-01": "rgb(152, 106, 68)",
								"26-02-15": "rgb(152, 106, 68)",
								"26-03-01": "rgb(152, 106, 68)",
								"26-03-16": "rgb(152, 106, 68)",
								"26-04-01": "rgb(152, 106, 68)",
								"26-04-16": "rgb(152, 106, 68)",
								"26-05-01": "rgb(152, 106, 68)",
								"26-05-16": "rgb(152, 106, 68)",
								"26-06-01": "rgb(152, 106, 68)",
								"26-06-16": "rgb(97, 53, 131)",
								"26-07-01": "rgb(97, 53, 131)",
								"26-07-16": "rgb(97, 53, 131)",
								"26-08-01": null,
								"26-08-16": null,
								"26-09-01": null,
								"26-09-16": null,
								"26-10-01": null,
								"26-10-16": null,
								"26-11-01": null,
								"26-11-16": null,
								"26-12-01": null,
								"26-12-16": null,
								"27-01-01": null,
								"27-01-16": null,
								"27-02-01": null,
								"27-02-15": null,
								"27-03-01": null,
								"27-03-16": null,
								"27-04-01": null,
								"27-04-16": null,
								"27-05-01": null,
								"27-05-16": null,
								"27-06-01": null,
								"27-06-16": null,
								"27-07-01": null,
								"27-07-16": null,
							},
							"Azubi 5": {
								"24-08-01": "rgb(220, 138, 221)",
								"24-08-16": "rgb(0, 255, 0)",
								"24-09-01": "rgb(0, 255, 0)",
								"24-09-16": "rgb(0, 255, 0)",
								"24-10-01": "rgb(0, 255, 0)",
								"24-10-16": "rgb(0, 255, 0)",
								"24-11-01": "rgb(0, 255, 0)",
								"24-11-16": "rgb(0, 255, 0)",
								"24-12-01": "rgb(0, 255, 0)",
								"24-12-16": "rgb(152, 106, 68)",
								"25-01-01": "rgb(152, 106, 68)",
								"25-01-16": "rgb(152, 106, 68)",
								"25-02-01": "rgb(152, 106, 68)",
								"25-02-15": "rgb(152, 106, 68)",
								"25-03-01": "rgb(152, 106, 68)",
								"25-03-16": "rgb(152, 106, 68)",
								"25-04-01": "rgb(152, 106, 68)",
								"25-04-16": "rgb(152, 106, 68)",
								"25-05-01": "rgb(152, 106, 68)",
								"25-05-16": "rgb(152, 106, 68)",
								"25-06-01": "rgb(152, 106, 68)",
								"25-06-16": "rgb(97, 53, 131)",
								"25-07-01": "rgb(97, 53, 131)",
								"25-07-16": "rgb(97, 53, 131)",
								"25-08-01": null,
								"25-08-16": null,
								"25-09-01": null,
								"25-09-16": null,
								"25-10-01": null,
								"25-10-16": null,
								"25-11-01": null,
								"25-11-16": null,
								"25-12-01": null,
								"25-12-16": null,
								"26-01-01": null,
								"26-01-16": null,
								"26-02-01": null,
								"26-02-15": null,
								"26-03-01": null,
								"26-03-16": null,
								"26-04-01": null,
								"26-04-16": null,
								"26-05-01": null,
								"26-05-16": null,
								"26-06-01": null,
								"26-06-16": null,
								"26-07-01": null,
								"26-07-16": null,
								"26-08-01": null,
								"26-08-16": null,
								"26-09-01": null,
								"26-09-16": null,
								"26-10-01": null,
								"26-10-16": null,
								"26-11-01": null,
								"26-11-16": null,
								"26-12-01": null,
								"26-12-16": null,
								"27-01-01": null,
								"27-01-16": null,
								"27-02-01": null,
								"27-02-15": null,
								"27-03-01": null,
								"27-03-16": null,
								"27-04-01": null,
								"27-04-16": null,
								"27-05-01": null,
								"27-05-16": null,
								"27-06-01": null,
								"27-06-16": null,
								"27-07-01": null,
								"27-07-16": null,
							},
							"Azubi 6": {
								"24-08-01": "rgb(0, 255, 0)",
								"24-08-16": "rgb(0, 255, 0)",
								"24-09-01": "rgb(0, 255, 0)",
								"24-09-16": "rgb(0, 255, 0)",
								"24-10-01": "rgb(0, 255, 0)",
								"24-10-16": "rgb(0, 255, 0)",
								"24-11-01": "rgb(0, 255, 0)",
								"24-11-16": "rgb(220, 138, 221)",
								"24-12-01": "rgb(220, 138, 221)",
								"24-12-16": "rgb(152, 106, 68)",
								"25-01-01": "rgb(152, 106, 68)",
								"25-01-16": "rgb(152, 106, 68)",
								"25-02-01": "rgb(152, 106, 68)",
								"25-02-15": "rgb(152, 106, 68)",
								"25-03-01": "rgb(152, 106, 68)",
								"25-03-16": "rgb(152, 106, 68)",
								"25-04-01": "rgb(152, 106, 68)",
								"25-04-16": "rgb(152, 106, 68)",
								"25-05-01": "rgb(152, 106, 68)",
								"25-05-16": "rgb(152, 106, 68)",
								"25-06-01": "rgb(152, 106, 68)",
								"25-06-16": "rgb(97, 53, 131)",
								"25-07-01": "rgb(97, 53, 131)",
								"25-07-16": "rgb(97, 53, 131)",
								"25-08-01": null,
								"25-08-16": null,
								"25-09-01": null,
								"25-09-16": null,
								"25-10-01": null,
								"25-10-16": null,
								"25-11-01": null,
								"25-11-16": null,
								"25-12-01": null,
								"25-12-16": null,
								"26-01-01": null,
								"26-01-16": null,
								"26-02-01": null,
								"26-02-15": null,
								"26-03-01": null,
								"26-03-16": null,
								"26-04-01": null,
								"26-04-16": null,
								"26-05-01": null,
								"26-05-16": null,
								"26-06-01": null,
								"26-06-16": null,
								"26-07-01": null,
								"26-07-16": null,
								"26-08-01": null,
								"26-08-16": null,
								"26-09-01": null,
								"26-09-16": null,
								"26-10-01": null,
								"26-10-16": null,
								"26-11-01": null,
								"26-11-16": null,
								"26-12-01": null,
								"26-12-16": null,
								"27-01-01": null,
								"27-01-16": null,
								"27-02-01": null,
								"27-02-15": null,
								"27-03-01": null,
								"27-03-16": null,
								"27-04-01": null,
								"27-04-16": null,
								"27-05-01": null,
								"27-05-16": null,
								"27-06-01": null,
								"27-06-16": null,
								"27-07-01": null,
								"27-07-16": null,
							},
						},
						hint: "Verwendet in der Langzeitplanung.",
					},
					{
						type: "longtermplanning_topics",
						content: {
							"Einführung mit Paten": "#ffffff",
							Metallgrunsausbildung: "#77767b",
							Kunststoffgrundausbildung: "#509bb0",
							Silikongrundausbildung: "#dc8add",
							"Orthetik 1": "#ffff00",
							"Orthetik 2": "#62a0ea",
							"Prothetik 1": "#ff0000",
							"Prothetik 2": "#00ff00",
							Reha: "#ff7800",
							Prüfungsvorbereitung: "#613583",
							Spezialisierung: "#986a44",
						},
						hint: "Verwendet in der Langzeitplanung.",
					},
				],
				[
					{
						type: "undefined",
						description: "Dies soll absichtlich einen Fehler im Assembler erzeugen",
					},
				],
			],
		},
	};
	const render = new Assemble(tests[element]);
	document.getElementById("main").replaceChildren(render.initializeSection());
	render.processAfterInsertion();
}

export async function screenshot(lang = null, distinct = null) {
	// import templates in available languages within dev environment and update the database ids for the respective usecases
	// ensure switching languages in your profile AND in config.env or .ini in advance to calling function

	// preferred initial viewport is 1000x1333 pixels in responsive design view with iOS as initial device for styling reasons (firefox)
	// make sure having ressources prepared (ids, identifier, unprocessed orders), scroll to top on landing page

	// one run takes about 5 minutes (default timeout of 10s * 34 screenshots, 4 viewport changes and some...)
	// cut, resize and sanitize names afterwards

	// you may have to adjust announcements for an expressive screenshot in between

	if (!lang) {
		console.log(
			"language en or de have not been specified. call by screenshot('en') or screenshot('de') - optional with distinct index - and copy and repaste ':screenshot --fullpage --filename xyz.png' into console during the countdown to capture the screen after preparing custom language and screen view."
		);
		return;
	}

	const timeout = 10;
	let iterator,
		value,
		s,
		index = 0;

	// yield functions
	function* menucall(index) {
		const targets = document.querySelectorAll("nav > input");
		while (index < targets.length) {
			console.log(`:screenshot --filename "${targets[index].title.toLowerCase()} menu ${lang}.png"`);
			/* 0-6 */ yield targets[index];
			index++;
		}
	}

	function* apicalls1(index) {
		// with still fixed navigation
		const targets = [
			/* 7 */ { en: "api.file('get', 'files')", de: "", screenshot: `:screenshot --filename "files ${lang}.png"` },
			/* 8 */ { en: "api.measure('get', 'measure')", de: "", screenshot: `:screenshot --filename "measure management ${lang}.png"` },
			/* 9 */ { en: "api.message('get', 'announcements')", de: "", screenshot: `:screenshot --filename "announcements ${lang}.png"` },
			/* 10 */ { en: "api.message('get', 'conversation')", de: "", screenshot: `:screenshot --filename "conversation ${lang}.png"` },
			/* 11 */ { en: "api.responsibility('get', 'responsibilities')", de: "", screenshot: `:screenshot --filename "responsibility ${lang}.png"` },
		];
		while (index < targets.length) {
			console.log(targets[index].screenshot);
			yield targets[index][lang] ? targets[index][lang] : targets[index].en;
			index++;
		}
	}

	function* apicalls2(index) {
		// after unfixing navigation
		const targets = [
			/* 12 */ { en: "rendertest('documents')", de: "rendertest('documents_de')", screenshot: `:screenshot --fullpage --filename "sample document elements ${lang}.png"` },
			/* 13 */ { en: "api.application('get', 'start')", de: "", screenshot: `:screenshot --fullpage --filename "dashboard ${lang}.png"` },
			/* 14 */ { en: "api.audit('get', 'audit', 12)", de: "api.audit('get', 'audit', 11)", screenshot: `:screenshot --fullpage --filename "audit ${lang}.png"` }, // customize to appropriate caro_audit_and_management id
			/* 15 */ { en: "api.audit('get', 'audittemplate', 12)", de: "api.audit('get', 'audittemplate', 11)", screenshot: `:screenshot --fullpage --filename "audit template ${lang}.png"` }, // customize to appropriate caro_audit_templates id
			/* 16 */ { en: "api.audit('get', 'checks', 'risks')", de: "", screenshot: `:screenshot --fullpage --filename "regulatory ${lang}.png"` },
			/* 17 */ { en: "api.calendar('get', 'longtermplanning', 11)", de: "api.calendar('get', 'longtermplanning', 58)", screenshot: `:screenshot --fullpage --filename "longtermplanning ${lang}.png"` }, // customize id to appropriate caro_calendar id
			/* 18 */ { en: "api.calendar('get', 'schedule')", de: "", screenshot: `:screenshot --fullpage --filename "calendar ${lang}.png"` },
			/* 19 */ { en: "api.document('get', 'document_editor', 3168)", de: "api.document('get', 'document_editor', 127)", screenshot: `:screenshot --fullpage --filename "document manager ${lang}.png"` }, // customize id to approprate caro_documents id
			/* 20 */ { en: "api.purchase('get', 'approved')", de: "", screenshot: `:screenshot --fullpage --filename "orders ${lang}.png"` },
			/* 21 */ { en: "api.purchase('get', 'vendor', 'Ortho-Reha%20Neuhof%20GmbH')", de: "", screenshot: `:screenshot --fullpage --filename "vendor manager ${lang}.png"` }, // customize vendor name to appropriate caro_consumables_vendors name
			/* 22 */ { en: "api.record('get', 'document', 'Basic data')", de: "api.record('get', 'document', 'Basisdaten')", screenshot: `:screenshot --fullpage --filename "document screen ${lang}.png"` }, // customize document name to appropriate caro_documents name, also see templates - import in respective language within dev environment
			/* 23 */ { en: "api.risk('get', 'risk')", de: "", screenshot: `:screenshot --fullpage --filename "risks ${lang}.png"` },
			/* 24 */ { en: "api.texttemplate('get', 'text', 40)", de: "api.texttemplate('get', 'text', 38)", screenshot: `:screenshot --fullpage --filename "text recommendation ${lang}.png"` }, // customize id to appropriate caro_texttemplates id
			/* 25 */ { en: "api.user('get', 'user', 'error%20on%20line%201')", de: "", screenshot: `:screenshot --fullpage --filename "user ${lang}.png"` }, // customize user name to appropriate caro_user name
			/* 26 */ { en: "api.user('get', 'profile')", de: "", screenshot: `:screenshot --fullpage --filename "profile ${lang}.png"` },
			/* 27 */ {
				en: "api.record('get', 'record', 'Jane Doe *01.02.2003 DAFO 2025-08-09 15:52')",
				de: "api.record('get', 'record', 'Erika Musterfrau *01.02.2003 UA-Prothese 2025-08-09 16:02')",
				screenshot: `:screenshot --fullpage --filename "record screen ${lang}.png"`,
			}, // customize identifier
		];
		const rendertesttitle = [
			{ en: "Sample application elements", de: "Beispiel Anwendungs-Elemente" },
			{ en: "Sample document elements", de: "Beispiel Dokumenten-Elemente" },
		];
		while (index < targets.length) {
			if (rendertesttitle[index]) api.update_header(rendertesttitle[index][lang]);
			console.log(targets[index].screenshot);
			yield targets[index][lang] ? targets[index][lang] : targets[index].en;
			index++;
		}
	}

	/* 28 */ function* apicalls3(index) {
		// dynamic views
		const targets = [
			{
				en: async function () {
					await api.application("get", "start");
					const data = new FormData();
					data.append(
						api._lang.GET("tool.markdown.editor"),
						`
# Plain text (h1 header)

This is a markdown flavour for basic text styling.  
Lines should end with two or more spaces  
to have an intentional linebreak
and not just continuing.

Text can be *italic*, **bold**, ***italic and bold***, ~~striked through~~, and \`code style\` with two ore more characters between the symbols.  
Some escaping of formatting characters is possible with a leading \ as in
**bold \* asterisk**, ~~striked \~~ through~~ and \`code with a \\\`-character\`.

http://some.url, not particularly styled  
a phone number: tel:012345678  
[Styled link to Markdown information](https://www.markdownguide.org)  

--------

## Lists (h2 header)

1. Ordered list items start with a number and a period
    * Sublist nesting
    * is possible
    * by indentating with four spaces
        1. and list types
        2. are interchangeable
2. Ordered list item 2
3. Ordered list item 3

* Unordered list items start with asterisk or dash
    1. the number
    1. of ordered lists
    2. actually doesn't
    3. matter at all
* Unordered list item 2
* Unordered list item 3

***

### Tables (h3 header)

| Table header 1 | Table header 2 | Table header 3 | and 4 |
| --- | --- | --- | --- |
| *emphasis* | **is** | ***possible*** | \`too\` |
| linebreaks | are | not | though |

* * *

#### Blockquotes and code (h4 header)

> Blockquote  
> with *multiple*  
> lines

    preformatted text/code must
    start with 4 spaces <code>

~~~
or being surrounded by
three \` or ~
~~~

## Other features:  
<http://some.other.url> with brackets, [urlencoded link with title](http://some.url?test2=2&test3=a=(/bcdef "some title") and [javascript: protocol](javascript:alert('hello world'))  
some \`code with <brackets>\`  
mid*word*emphasis and __underscore emphasis__  
some@mail.address and escaped\@mail.address  
![an external image](./media/favicon/android/android-launchericon-48-48.png) could, but may not work in caro context because of the Service-Worker though  
123\. escaped period avoiding a list

### Nested items in lists

1. List item with
    > Blockquote as item
2. Next list item with
    |Table|Column2|
    |---|---|
    |R1C1|R1C2|
4. Last item

### Nested items in blockquotes

> * List within blockquote 1
> * List within blockquote 2
>     * Nested list
> ~~~
> Code within blockquote
> ~~~
>> Blockquote within blockquote
> 
> | Tables nested | within | blockquotes |
> | :---------- | :-----: | ---: |
> | are | possible | as well |
> | like | aligning | colums |

[top header](#plain-text)
`
					);
					await api.tool("post", "markdown", null, data);
					await _.sleep(500);
					const dialog = document.querySelectorAll("dialog")[0];
					dialog.style.transform = "translateY(34%)";
					dialog.style.maxHeight = "none";
					document.querySelector("main").style.height = dialog.offsetHeight - 100 + "px";
					console.log(`:screenshot --fullpage --filename "markdown ${lang}.png"`);
				},
				de: async function () {
					await api.application("get", "start");
					const data = new FormData();
					data.append(
						api._lang.GET("tool.markdown.editor"),
						`
# Einfacher Text (h1 Überschrift)

Dies ist eine Markdown-Variante für einfache Textgestaltung.  
Zeilen sollten mit zwei oder mehr Leerzeichen enden  
um einen beabsichtigten Zeilenumbruch zu erzeugen
und nicht einfach fortgeführt zu werden.

Text kann *kursiv*, **fett**, **kursiv und fett, ~~durchgestrichen~~ und im \`quelltextstil\` mit je zwei oder mehr Zeichen zwischen den Symbolen dargestellt werden.  
Das Maskieren von Formatierungszeichen ist mit einem vorangestellten \ möglich, wie in
**fettes \* Sternchen**, ~~durch \~~ gestrichen~~ und \`Code mit einem \\\`-Zeichen\`.

http://eine.url, nicht besonders gestaltet  
eine Telefonnummer: tel:012345678  
[Angepasster Link für weitere Markdown Informationen](https://www.markdownguide.org)  

--------

## Listen (h2 Überschrift)

1. Geordnete Listeneinträge beginnen mit einer Zahl und eine Punkt
    * Verschachtelte Listen
    * sind möglich
    * mit einer Einrückung von vier Leerzeichen
        1. und Listenarten
        2. können kombiniert werden
2. geordneter Listeneintrag 2
3. geordneter Listeneintrag 3

* Ungeordnete Listeneinträge beginnen mit einem Sternchen oder Minus
    1. die Nummerierung
    1. von geordneten Listen
    2. spielt eigentlich
    3. keine Rolle
* ungeordneter Listeneintrag 2
* ungeordneter Listeneintrag 3

***

### Tabellen (h3 Überschrift)

| Tabellenüberschrift 1 | Tabellenüberschrift 2 | Tabellenüberschrift 3 | und 4 |
| --- | --- | --- | --- |
| *Akzentuierung* | **ist** | ***ebenfalls*** | \`möglich\` |
| Zeilenumbrüche | sind es | jedoch | nicht |

* * *

#### Zitatblöcke und Code (h4 Überschrift)

> Zitatblock  
> mit *mehreren*  
> Zeilen

    Vorformatierter Text/Code muss
    mit 4 Leerzeichen eingerückt werden

~~~
oder von drei Gravis' oder Tilde-Zeichen
eingefasst sein
~~~

## Sonstige Funktionen:  
<http://eine.internet.adresse> mit Klammern, [urlencoded Link mit Titel](http://some.url?test2=2&test3=a=(/bcdef "ein Titel") und [javascript: protocol](javascript:alert('hello world'))  
ein \`code mit <Klammern>\`  
Betonung*im*Wort und __Betonung mit Unterstrich__  
eine@email.addresse und maskierte\@email.addresse  
![ein externes Bild](./media/favicon/android/android-launchericon-48-48.png) kann, wird aber im CARO Kontext wegen des Service-Workers üblicherweise nicht dargestellt  
123\. maskierter Punkt um eine Liste zu vermeiden

### Verschachtelte Elemente in Listen

1. Listeneintrag mit
    > Zitatblock
2. Ein weiterer Listeneintrag mit einer
    |Tabelle|Spalte 2|
    |---|---|
    |Z1S1|Z1S2|
4. Letzter Eintrag

### Verschachtelte Elemente in Zitatblöcken

> * Listeneintrag innerhalb eines Zitatblocks 1
> * Listeneintrag innerhalb eines Zitatblocks 2
>     * Unterliste
> ~~~
> Code innerhalb eines Zitatblocks
> ~~~
>> Zitatblock im Zitatblock
> 
> | In Zitatblöcken | verschachtelte | Tabellen |
> | :---------- | :-----: | -----: |
> | sind | auch | möglich |
> | so wie | spaltenweise | Ausrichtung |

[erste Überschrift](#plain-text)
`
					);
					await api.tool("post", "markdown", null, data);
					await _.sleep(500);
					const dialog = document.querySelectorAll("dialog")[0];
					dialog.style.transform = "translateY(34%)";
					dialog.style.maxHeight = "none";
					document.querySelector("main").style.height = dialog.offsetHeight - 100 + "px";
					console.log(`:screenshot --fullpage --filename "markdown ${lang}.png"`);
				},
			},
		];
		while (index < targets.length) {
			yield targets[index][lang] ? targets[index][lang] : targets[index].en;
			index++;
		}
	}

	function* apicalls4(index) {
		// just apicalls to create temp files
		const targets = [
			/* 29 */ {
				en: async function () {
					const data = new FormData();
					data.append(api._lang.GET("record.create_identifier"), "Jane Doe *01.02.2003 DAFO");
					data.append("_type", "label");
					api.record("post", "identifier", "appendDate", data);
					console.log(`sample identifier code ${lang}.png`);
				},
				de: async function () {
					const data = new FormData();
					data.append(api._lang.GET("record.create_identifier"), "Erika Musterfrau *01.02.2003 UA-Prothese");
					data.append("_type", "label");
					api.record("post", "identifier", "appendDate", data);
					console.log(`sample identifier code ${lang}.png`);
				},
			},
			/* 30 */ {
				en: async function () {
					api.record("get", "document", "Basic data"); // customize document name to appropriate caro_documents name, also see templates - import in respective language within dev environment
					await _.sleep(2000);
					api.document("post", "export");
					console.log(`document export ${lang}.png`);
				},
				de: async function () {
					api.record("get", "document", "Basisdaten"); // customize document name to appropriate caro_documents name, also see templates - import in respective language within dev environment
					await _.sleep(2000);
					api.document("post", "export");
					console.log(`document export ${lang}.png`);
				},
			},
			/* 31*/ {
				en: async function () {
					api.calendar("get", "appointment");
					await _.sleep(2000);
					document.getElementsByName(api._lang.GET("calendar.appointment.date"))[0].value = "2025-07-27";
					document.getElementsByName(api._lang.GET("calendar.appointment.time"))[0].value = "10:00";
					document.getElementsByName(api._lang.GET("calendar.appointment.occasion"))[0].value = "Cast for orthosis";
					document.getElementsByName(api._lang.GET("calendar.appointment.reminder"))[0].value = "Remember bringing shoes";
					document.getElementsByName(api._lang.GET("calendar.appointment.duration"))[0].value = "1";
					await _.sleep(500);
					api.calendar("post", "appointment");
					console.log(`appointment ${lang}.png`);
				},
				de: async function () {
					api.calendar("get", "appointment");
					await _.sleep(2000);
					document.getElementsByName(api._lang.GET("calendar.appointment.date"))[0].value = "2025-07-27";
					document.getElementsByName(api._lang.GET("calendar.appointment.time"))[0].value = "10:00";
					document.getElementsByName(api._lang.GET("calendar.appointment.occasion"))[0].value = "Gipsabdruck für Orthese";
					document.getElementsByName(api._lang.GET("calendar.appointment.reminder"))[0].value = "Bringen Sie Schuhe mit";
					document.getElementsByName(api._lang.GET("calendar.appointment.duration"))[0].value = "1";
					await _.sleep(500);
					api.calendar("post", "appointment");
					console.log(`appointment ${lang}.png`);
				},
			},
			/* 32 */ {
				en: async function () {
					api.record("get", "fullexport", "Jane Doe *01.02.2003 DAFO 2025-08-09 15:52"); // customize identifier
					await _.sleep(2000);
					console.log(`record full summary ${lang}.png`);
				},
				de: async function () {
					api.record("get", "fullexport", "Erika Musterfrau *01.02.2003 UA-Prothese 2025-08-09 16:02"); // customize identifier
					await _.sleep(2000);
					console.log(`record full summary ${lang}.png`);
				},
			},
			/* 33 */ {
				en: async function () {
					api.record("get", "simplifiedexport", "Jane Doe *01.02.2003 DAFO 2025-08-09 15:52"); // customize identifier
					await _.sleep(2000);
					console.log(`record reduced summary ${lang}.png`);
				},
				de: async function () {
					api.record("get", "simplifiedexport", "Erika Musterfrau *01.02.2003 UA-Prothese 2025-08-09 16:02"); // customize identifier
					await _.sleep(2000);
					console.log(`record reduced summary ${lang}.png`);
				},
			},
		];
		while (index < targets.length) {
			yield targets[index][lang] ? targets[index][lang] : targets[index].en;
			index++;
		}
	}

	// instructions
	console.log(`copy and repaste the :screenshot command to have proper prepared filenames`);
	console.log(`starting in in ${timeout} seconds. we'll start with menu items, after whose we'll iterate over provided endpoints. in the meantime the menu will be set to unfixed for longer contents.`);
	console.log(`menu items will pop up every ${timeout} seconds, copy and repaste the :screenshot command to have proper prepared filenames.`);
	if (!distinct) await _.sleep(timeout * 1000);

	console.clear();
	iterator = menucall(0);
	while ((value = iterator.next().value)) {
		if (!distinct || distinct == index) {
			s = timeout;
			value.checked = true;
			while (s > 0) {
				console.log(s);
				await _.sleep(1000);
				s--;
			}
			console.clear();
		}
		index++;
	}
	if (distinct && index > distinct) return;

	console.log(`menu will be cleared in ${timeout} seconds. endpoints will load every ${timeout} seconds, copy and repaste ':screenshot --filename xyz.png' into console during the countdown to capture the screen.`);
	if (!distinct) await _.sleep(timeout * 1000);
	for (const item of document.querySelectorAll("nav > input")) {
		item.checked = false;
	}

	console.clear();
	iterator = apicalls1(0);
	while ((value = iterator.next().value)) {
		if (!distinct || distinct == index) {
			s = timeout;
			eval(value);
			while (s > 0) {
				console.log(s);
				await _.sleep(1000);
				s--;
			}
			console.clear();
		}
		index++;
	}
	if (distinct && index > distinct) return;

	console.log(`menu will be set to unfixed in ${timeout} seconds. endpoints will load every ${timeout} seconds, copy and repaste ':screenshot --fullpage --filename xyz.png' into console during the countdown to capture the screen.`);
	if (!distinct) await _.sleep(timeout * 1000);

	// modify nav styling
	const body = document.querySelector("body"),
		nav = document.querySelector("body>nav"),
		main = document.querySelector("main");
	const nav2 = nav.cloneNode(true);
	main.style.marginBottom = "1.5rem";
	nav2.style.position = "unset";
	nav2.style.margin = "0 0 -1rem -1rem";
	nav2.style.width = "calc(100% + 1.9rem)";
	body.append(nav2);
	nav.remove();

	console.clear();
	iterator = apicalls2(0);
	while ((value = iterator.next().value)) {
		if (!distinct || distinct == index) {
			s = timeout;
			eval(value);
			while (s > 0) {
				console.log(s);
				await _.sleep(1000);
				s--;
			}
			console.clear();
		}
		index++;
	}
	if (distinct && index > distinct) return;

	console.clear();
	iterator = apicalls3(0);
	while ((value = iterator.next().value)) {
		if (!distinct || distinct == index) {
			s = timeout;
			await value();
			while (s > 0) {
				console.log(s);
				await _.sleep(1000);
				s--;
			}
			console.clear();
		}
		index++;
	}
	if (distinct && index > distinct) return;

	console.clear();
	console.log("now preparing some exports. don't mind modals. fetch the files from ./fileserver/tmp and convert to:");
	if (!distinct) await _.sleep(timeout * 1000);

	console.clear();
	iterator = apicalls4(0);
	while ((value = iterator.next().value)) {
		if (!distinct || distinct == index) {
			await value();
			await _.sleep(1000);
		}
		index++;
	}

	console.log("done. reload to return to normal.");
	console.log("you may have to trigger " + `record reduced summary ${lang}.png` + " manually though for record summaries are timestamped by the minute only.");
}

export function request_param() {
	const payload = new FormData();
	payload.append("asdföäü._? aksldfjn[]", "1");
	payload.append("asdföäü._? aksldfjn[]", "2");
	payload.append("asdföäü._? aksldfjn[", "3");

	api.send("post", ["application", "start", "api"], null, null, payload);
	api.send("put", ["application", "start", "api"], null, null, payload);
	api.send("get", ["application", "start", "api"], null, null, payload);
	api.send("delete", ["application", "start", "api"], null, null, payload);
}

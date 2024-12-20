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
							required: true
						},
						hint: "Clarify with hints, required fields are makred with an asterisk",
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
					},
					{
						type: "documentbutton",
						attributes: {
							value: "Display SILLY FORM",
						},
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
							required: true
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
					},
					{
						type: "documentbutton",
						attributes: {
							value: "Zeige TOLLES FORMULAR an",
						},
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
							list: "range_datalist",
							min: 0,
							max: 4,
							value: 2,
						},
					},
					{
						type: "datalist",
						attributes: {
							id: "range_datalist",
							class: "rangedatalist",
						},
						content: [{ label: "A" }, { label: "range" }, { label: "with" }, { label: "a" }, { label: "datalist" }],
						hint: "As seen in user manager.",
					},
					{
						type: "datalist",
						attributes: {
							id: "text_datalist",
						},
						content: ["A", "textinput", "with", "a", "datalist"],
					},
					{
						type: "text",
						attributes: {
							name: "Text input with datalist",
							list: "text_datalist",
						},
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
								content: "This is a third width. As seen on landing page.",
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
						hint: "Suchtastatur und Lupensymbol, Verwendet bei neuen Bestellungen.",
					},
					{
						type: "filtered",
						attributes: {
							name: "Filtereingabe",
						},
						hint: "Suchtastatur und Trichtersymbol, Verwendet bei Doumentationen.",
					},
					{
						type: "checkbox2text",
						attributes: {
							name: "Die ausgewählten Optionen des Popups werden mit Kommata verkettet",
						},
						hint: "Vereinfacht eine Auswahlentscheidung und stellt ein einfaches Datenobjekt bereit, Verwendet in der Formularverwaltung.",
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
							list: "range_datalist",
							min: 0,
							max: 4,
							value: 2,
						},
					},
					{
						type: "datalist",
						attributes: {
							id: "range_datalist",
							class: "rangedatalist",
						},
						content: [{ label: "Ein" }, { label: "Regler" }, { label: "mit" }, { label: "einer" }, { label: "Datenliste" }],
						hint: "Verwendet in der Nutzerverwaltung.",
					},
					{
						type: "datalist",
						attributes: {
							id: "text_datalist",
						},
						content: ["Eine", "Texteingabe", "mit", "einer", "Datenliste"],
					},
					{
						type: "text",
						attributes: {
							name: "Texteingabe mit einer Datenliste",
							list: "text_datalist",
						},
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
								content: "Dies ist ein Drittel der Breite, Verwendet auf der Startseite",
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

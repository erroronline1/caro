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
						type: "longtermplanning",
						attributes: {
							name: "Transfer schedule",
						},
						content: {
							"Name 1": {
								"01.25": "#ff0000",
								_1: "#ff0000",
								_2: "#ff0000",
								_3: "#ff0000",
								"03.25": "#ff0000",
								_5: "#ff0000",
								_6: null,
								_7: null,
								"05.25": null,
								_9: "#00ff00",
								_10: "#00ff00",
								_11: "#00ff00",
								"07.25": "#00ff00",
								_13: "#00ff00",
								_14: "#00ff00",
								_15: "#00ff00",
								"09.25": "#00ff00",
								_17: null,
								_18: null,
								_19: "#0000ff",
								11.25: "#0000ff",
								_21: "#0000ff",
								_22: "#0000ff",
								_24: "#0000ff",
							},
							"Name 2": {
								"01.25": "#ff0000",
								_1: "#ff0000",
								_2: "#ff0000",
								_3: "#ff0000",
								"03.25": "#ff0000",
								_5: "#ff0000",
								_6: null,
								_7: null,
								"05.25": null,
								_9: "#00ff00",
								_10: "#00ff00",
								_11: "#00ff00",
								"07.25": "#00ff00",
								_13: "#00ff00",
								_14: "#00ff00",
								_15: "#00ff00",
								"09.25": "#00ff00",
								_17: null,
								_18: null,
								_19: "#0000ff",
								11.25: "#0000ff",
								_21: "#0000ff",
								_22: "#0000ff",
								_23: "#0000ff",
								"01.26": "#ff0000",
								_25: "#ff0000",
								_26: "#ff0000",
								_27: "#ff0000",
								"03.26": "#ff0000",
								_29: "#ff0000",
								_30: null,
								_31: null,
								"05.26": null,
								_33: "#00ff00",
								_34: "#00ff00",
								_35: "#00ff00",
								"07.26": "#00ff00",
								_37: "#00ff00",
								_38: "#00ff00",
								_39: "#00ff00",
								"09.26": "#00ff00",
								_41: null,
								_42: null,
								_43: "#0000ff",
								11.26: "#0000ff",
								_45: "#0000ff",
								_46: "#0000ff",
								_47: "#0000ff",
								"01.27": "#ff0000",
								_49: "#ff0000",
								_50: "#ff0000",
								_51: "#ff0000",
								"03.27": "#ff0000",
								_53: "#ff0000",
								_54: null,
								_55: null,
								"05.27": null,
								_57: "#00ff00",
								_58: "#00ff00",
								_59: "#00ff00",
								"07.27": "#00ff00",
								_61: "#00ff00",
								_62: "#00ff00",
								_63: "#00ff00",
								"09.27": "#00ff00",
								_65: null,
								_66: null,
								_67: "#0000ff",
								11.27: "#0000ff",
								_69: "#0000ff",
								_70: "#0000ff",
								_71: "#0000ff",
								"01.28": "#ff0000",
								_73: "#ff0000",
								_74: "#ff0000",
								_75: "#ff0000",
								"03.28": "#ff0000",
								_77: "#ff0000",
								_78: null,
								_79: null,
								"05.28": null,
								_81: "#00ff00",
								_82: "#00ff00",
								_83: "#00ff00",
							},
						},
						preset: {
							"Unit 1": "#ff0000",
							"Unit 2": "#00ff00",
							"Unit 3": "#0000ff",
						},
					},
				],
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
						autocomplete: ["Lorem ipsum dolor sit amet, Version Eins", "Lorem ipsum dolor sit amet, Version Zwei", "Lorem ipsum dolor sit amet, Version Drei", "Lorem ipsum dolor sit amet, Version Vier"],
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

export function screenshot() {
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
	return "reload to return to normal";
}

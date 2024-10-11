# Content
### Common
* [Identifikator](#identifikator)
* [Basisdaten](#basisdaten)
* [Datenverarbeitung - Auftragserteilung - Schweigepflichtentbindung](#datenverarbeitung--auftragserteilung--schweigepflichtentbindung)
* [Silikonfertigungsauftrag](#silikonfertigungsauftrag)
* [Empfangsbestätigung](#empfangsbestätigung)
* [Erlaubnis zur Ausstellung und Veröffentlichung](#erlaubnis-zur-ausstellung-und-veröffentlichung)
* [Versorgungsmaßnahme](#versorgungsmaßnahme)
* [Versorgungsausführung](#versorgungsausführung)
* [Konformitätserklärung](#konformitätserklärung)
* [Vorgangsprotokoll](#vorgangsprotokoll)
* [Produkteinführung](#produkteinführung)
* [Stichprobenprüfung](#stichprobenprüfung)

### Prosthetics 2
* [Anamnese Prothetik II](#anamnese-prothetik-ii)
* [Maßblatt Prothetik II](#maßblatt-prothetik-ii)
* [Kunststofffertigungsauftrag Prothetik II](#kunststofffertigungsauftrag-prothetik-ii)
* [Versorgungsplanung Prothetik II](#versorgungsplanung-prothetik-ii)
* [Checkliste Prothetik II](#checkliste-prothetik-ii)
* [Abgabeprotokoll Prothetik II](#abgabeprotokoll-prothetik-ii)
* [Gebrauchsanweisung Prothetik II](#gebrauchsanweisung-prothetik-ii)

## Identifikator
```json
[
	[
		{
			"type": "identify",
			"attributes": {
				"name": "Vorgang"
			}
		}
	]
]
```
[Content](#content)

## Basisdaten
```json
[
	[
		{
			"type": "text",
			"attributes": {
				"name": "Name",
				"required": true
			}
		},
		{
			"type": "date",
			"attributes": {
				"name": "Geburtsdatum"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Adresse"
			}
		},
		{
			"type": "tel",
			"attributes": {
				"name": "Telefonnummer"
			}
		},
		{
			"type": "email",
			"attributes": {
				"name": "eMailadresse"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Kostenträger"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Info"
			},
			"hint": "Kontaktperson, Adressbesonderheiten"
		}
	],
	[
		{
			"type": "textarea",
			"attributes": {
				"name": "Leistung, Produkt oder Verordnungstext"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Verordner"
			},
			"hint": "namentlich"
		},
		{
			"type": "text",
			"attributes": {
				"name": "FIBU Nummer"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "EVA Vorgangsnummer"
			}
		}
	]
]
```
[Content](#content)

## Datenverarbeitung- Auftragserteilung- Schweigepflichtentbindung
Export for signature compliance
```json
[
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Datenverarbeitung durch und Auftragserteilung an"
			},
			"content": "Universitätsklinikum Heidelberg, Zentrum für Orthopädie, Unfallchirurgie und Paraplegiologie \\nAbteilung Technische Orthopädie, Schlierbacher Landstraße 200a, 69118 Heidelberg"
		}
	],
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Information zur Datenverarbeitung"
			},
			"content": "Die Bereitstellung und Verarbeitung Ihrer Daten ist im Rahmen Ihrer Versorgung in unserem Haus erforderlich und Bestandteil des Behandlungsvertrags. Bei Bedarf stellen wir Ihnen diese Informationen auch in einem größeren Ausdruck zur Verfügung. Allgemein gilt die Erklärung zur Datenverarbeitung und Datenschutz des Universitätsklinikums Heidelberg. \\nVerarbeitungszwecke im Rahmen Ihrer Hilfsmittelversorgung: Hilfsmittelversorgung, Patientenverwaltung und Abrechnung, interdisziplinäre Konferenzen zur Analyse und Erörterung von Diagnostik und Therapie, Versorgungsdokumentation, Erstellung von Berichten und Stellungnahmen, Qualitätssicherung in Versorgung und ihrer Organisation, Unterrichtung von Mit-/Weiterversorgern im erforderlichen Umfang, Eingabe der Adressdaten und ggf. Rufnummer an Versanddienstleister, Kontaktaufnahme auch hinsichtlich eines Erinnerungsservices nach Versorgungsabschluss."
		},
		{
			"type": "textsection",
			"attributes": {
				"name": "Auftragserteilung"
			},
			"content": "Ich wurde darüber aufgeklärt, dass mir die Wahl eines Leistungserbringers zur Anfertigung bzw. Versorgung mit einem Hilfsmittel freisteht. In diesem Rahmen beauftrage ich den oben genannten Leistungserbringer. Ich wurde vor Inanspruchnahme der Leistung/en darüber beraten, welche/s Hilfsmittel und zusätzliche/n Leistung/en nach § 33 SGB V für die konkrete Versorgungssituation im Einzelfall geeignet und notwendig ist/sind. Ich bin über die Möglichkeit einer aufzahlungsfreien Versorgung, die der vertragsärztlichen Verordnung entspricht und ihren Zweck voll erfüllt, informiert worden. Die gesetzliche Zuzahlung bleibt davon unberührt. Maß- und Sonderanfertigungen sowie gebrauchte Hilfsmittel sind vom Rückgabe- und Umtauschrecht ausgeschlossen. Die Informationen zur Datenverarbeitung habe ich zur Kenntnis genommen und akzeptiere sie. Ich bin darüber aufgeklärt, dass Lieferungen bevorzugt persönlich erfolgen sollen."
		},
		{
			"type": "textarea",
			"attributes": { 
				"name": "Leistung"
			}
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Kostenübernahmeerklärung"
			},
			"content": {
				"Eine Kostenübernahme kann ich persönlich nicht erteilen und möchte die Kostenübernahme durch meinen Kostenträger (gilt auch für postOP-Versorgungen).": [],
				"Ich habe mich nach eingehender Beratung auf eigenen Wunsch für die Versorgung mit einem aufzahlungsfreien Produkt entschieden.": [],
				"Ich wünsche für mich die medizinische Leistung sofort. Deshalb erteile ich den Auftrag mich, unabhängig von einer Kostenzusage meines Kostenträgers, sofort zu versorgen. Sollte meine Krankenkasse die Bezahlung ganz oder teilweise nicht übernehmen, werde ich die Kosten in der Höhe des Kostenvoranschlages an meinen Kostenträger selbst tragen und bezahlen.": [],
				"Ich habe mich nach eingehender Beratung und Auswahl verschiedener Leistungen auf eigenen Wunsch für eine abweichende Versorgungsalternative mit Aufzahlung entschieden und bestätige hiermit die Kosten sowie etwaige Folgekosten, welche durch die höherwertige Leistung ausgelöst werden, selbst zu tragen.": []
			}
		},
		{
			"type": "text",
			"attributes": { 
				"name": "Ausführung, Leistung mit Aufzahlung"
			}
		},
		{
			"type": "number",
			"attributes": { 
				"name": "Voraussichtliche Kosten"
			}
		}
	],
	[
		{
			"type": "checkbox",
			"attributes": {
				"name": "Weiterleitung persönlicher Daten and andere Mit-/Weiterversorger"
			},
			"content": {
				"Ich erteile durch meine Unterschrift mein - im Einzelfall widerrufliches - Einverständnis, dass alle für den Mit-/Weiterversorger meines Hilfsmittels notwendigen personenbezogenen Daten an diesen weitergeleitet werden dürfen.": []
			}
		}
	],
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Schweigepflichtentbindung gegenüber dem Kostenträger"
			},
			"content": "Um für Ihr Hilfsmittel die Kostenzusage Ihres Kostenträgers zu erhalten, übermitteln wir diesem einen Kostenvoranschlag und eine Stellungnahme mit weiterführenden Informationen (u. a. Name, Versicherungsnummer, Diagnose, Angaben zur Erforderlichkeit). Die Stellungnahme wird entweder gleich der Beantragung beigelegt oder aber auf Anfrage seitens Ihres Kostenträgers zugeschickt. Hierzu ist Ihre Einwilligung und Schweigepflichtentbindung erforderlich. Die Einwilligung ist freiwillig, Vorgangsbezogen und für zukünftige Übermittlungen widerruflich. Ohne Ihre Einwilligung ist mit einer zeitlichen Verzögerung bei der Bearbeitung Ihrer Krankenkasse zu rechnen. Möglicherweise kann es dadurch sogar zu einer Ablehnung der Hilfsmittelversorgung kommen."
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Schweigepflichtentbindung"
			},
			"content": {
				"Ich erteile durch meine Unterschrift mein - im Einzelfall widerrufliches - Einverständnis, dass ich meinen oben genannten Leistungserbringer von der Schweigepflicht gegenüber meinem Kostenträger entbinde.": []
			}
		}
	],
	[
		{
			"type": "signature",
			"attributes": { 
				"name": "Datum, Unterschrift"
			}
		}
	]
]
```
[Content](#content)

## Silikonfertigungsauftrag
```json
[
	[
		{
			"type": "select",
			"attributes": {
				"name": "Grundposition obere Extremität",
				"multiple": true
			},
			"content": {
				"...": [],
				"Liner / Silikonschaft UA OA 75S10": [],
				"Außenschaft / Schutzüberzug UA OA 75S40": [],
				"Silikoninlet Schulterschaft 75S50": [],
				"Silikonschulterkappe 75S60": [],
				"Silikonbandage 75S70": [],
				"Handgelenkorthese 75O10": [],
				"Silikonkompressionshandschuh 75O40": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Grundposition untere Extremität",
				"multiple": true
			},
			"content": {
				"...": [],
				"Liner US 75S20": [],
				"Silikoninnenschaft US 75S25": [],
				"Liner OS 75S30": [],
				"Silikoninnenschaft OS 75S35": [],
				"Silikonhose / Beckenschaft 75S90": [],
				"Außenschaft / Schutzüberzug US OS 75S45": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Polster, Narben, Traktion, Sonstiges",
				"multiple": true
			},
			"content": {
				"...": [],
				"HTV-RTV-Polster 75S80": [],
				"RTV-Polster 75S81": [],
				"Platte für Traktionsmanschetten 75O70": [],
				"Steckhülse für Lagerungsschale 75O80": [],
				"RTV-Grundierung für konfektionierte Dichtlippe 75A90": []
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Sonstige Grundposition"
			}
		}
	],
	[
		{
			"type": "select",
			"attributes": {
				"name": "Zusätze zur Grundposition",
				"multiple": true
			},
			"content": {
				"...": [],
				"Gurtband 75A10": [],
				"Carbonverbund 75A40": [],
				"Verschraubung 75A50": [],
				"Dichtmanschette 75A70": [],
				"Kaschierung 75E10": [],
				"RTV-Schicht zur Adhäsionssteigerung 75E20": [],
				"Integrierter kleiner Narbenausgleich 75E30": [],
				"Matrix 75E40": [],
				"Integriertes distales Polster zur Druckentlastung 75E70": [],
				"HeiSens Leitspot pro Elektrode 75E80": [],
				"Silikon Composite 75E90": [],
				"Silikonbeschichtung zur Haftungsreduktion 75E100": [],
				"Individuelle Farbgestaltung 75I10": []
			}
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Reparaturauftrag"
			},
			"content": {
				"Reparatur": []
			},
			"hint": "Modell erforderlich"
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Reparaturausführung, sonstige Zusätze, ggf. Anzahl"
			}
		}
	],
	[
		{
			"type": "text",
			"attributes": {
				"name": "Stärke"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Shore"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Farbe"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Weitere Ausführungen"
			}
		},
		{
			"type": "photo",
			"attributes": {
				"name": "Skizze"
			}
		}
	],
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Fertigung"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Verwendete Materialien im Hautkontakt"
			}
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Silikonfertigung erledigt"
			},
			"content": {
				"Abgeschlossen": []
			}
		}
	]
]
```
[Content](#content)

## Empfangsbestätigung
```json
[
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Empfangsbestätigung"
			},
			"content": "Hiermit bestätige ich, dass ich die folgenden Hilfsmittel vom Leistungserbringer Universitätsklinikum Heidelberg, Technische Orthopädie, Schlierbacher Landstraße 200a, 69118 Heidelberg, erhalten habe:"
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Hilfsmittel"
			}
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Abgabe"
			},
			"content": {
				"zum dauerhaften Verbleib": [],
				"miet-/leihweise": []
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "enthaltene Miet-/Leihkomponenten"
			}
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Erklärung"
			},
			"content": {
				"Ich erkläre hiermit, dass ich das Hilfsmittel in ordnungsgemäßem, gebrauchsfähigem Zustand empfangen habe.": [],
				"Ich erkläre hiermit, dass ich darüber aufgeklärt wurde, dass ausstehende Arbeiten zur Finalisierung meines Hilfsmittels keinen Einfluss auf die Funktion haben und ich eine postalische Zusendung nach Abschluss dieser Arbeiten ausdrücklich wünsche.": [],
				"Eine ausreichende Unterweisung in Gebrauch, Funktionsweise und Risiken durch den Leistungserbringer habe ich erhalten. Mit der Passform, Ausführung, Funktion und Einweisung bin ich zufrieden, soweit ich dies z. Zt. beurteilen kann. Eine Gebrauchsanweisung und die darin enthaltene Konformitätserklärung wurden mir ausgehändigt. Ich verpflichte mich das Hilfsmittel nur zum bestimmungsgemäßen Gebrauch zu verwenden, pfleglich zu behandeln sowie Hinweise in der Gebrauchsanweisung zu beachten.": [],
				"Die oben aufgeführten Artikel die mir miet- oder leihweise überlassen wurden, wie z.B. Miet-/Leihorthesen, Miet-/Leihpassteile, Leihorthesenschuhe, usw., werde ich bis zum unten genannten Datum persönlich oder ausreichend frankiert an den Leistungserbringer zurückgeben. Bei einer verspäteten Rückgabe können mir die miet- oder leihweise überlassenen Hilfsmittel oder Komponenten in Rechnung gestellt werden.": [],
				"Das Hilfsmittel wird mit einer Sonderfreigabe zur Probe zur Nutzung übergeben. Nach Ablauf des nebenstehenden Datums ist das Tragen dieses Hilfsmittels untersagt. Das Nichtbeachten geschieht auf eigenes Risiko. Im derzeitigen Zustand ist das Hilfsmittel nicht für starke Belastungen wie Sport und/oder schwere körperliche Aktivitäten zugelassen. Die endgültige Fertigstellung erfolgt erst nach beendeter Testphase. Ich wurde darüber aufgeklärt, dass ich nach Ablauf der Testphase das Hilfsmittel umgehend zur Endfertigung an das Unternehmen zurückgeben muss. Mir sind die Risiken bei der Benutzung des Hilfsmittels im Testzustand ausreichend dargelegt worden.": []
			}
		},
		{
			"type": "date",
			"attributes":{
				"name": "Testversorgung, Versorgung im Probezustand bis"
			}
		},
		{
			"type": "date",
			"attributes":{
				"name": "Rückgabe der Miet-/ Leihteile bis"
			}
		},
		{
			"type": "signature",
			"attributes": { 
				"name": "Datum, Unterschrift"
			}
		}
	]
]
```
[Content](#content)

## Erlaubnis zur Ausstellung und Veröffentlichung
```json
[
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Empfangsbestätigung"
			},
			"content": "Hiermit erteile ich dem Universitätsklinikum Heidelberg die Erlaubnis zur Ausstellung bzw. der Veröffentlichung der von mir, bzw. meinem Kind gefertigten Foto- und / oder Videoaufnahmen. Diese Erklärung kann jederzeit ohne Angaben von Gründen für künftige Veröffentlichungen widerrufen werden. Dies hat keinen Einfluss auf die Versorgung. Mir ist bewusst, dass bereits erfolgte Veröffentlichungen nachträglich nicht zurückgenommen werden können. Insbesondere bei der Veröffentlichung im Internet entzieht sich die weitere Verbreitung durch Kopien dem Zugriff durch das Universitätsklinikum Heidelberg.\\n \\nDie von mir / meinem Kind gemachten Aufnahmen sollen zu Zwecken der Presse- und Öffentlichkeitsarbeit des Universitätsklinikums Heidelberg verwendet werden. Ich habe die diesbezüglichen Erläuterungen verstanden, und mir wurde ausreichend Gelegenheit gegeben, Fragen zu stellen."
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Erlaubnis zur Veröffentlichung von Aufnahmen in"
			},
			"content": {
				"Fachzeitschriften": [],
				"Zeitungsberichten": [],
				"Vorträgen": [],
				"Messen": [],
				"Poster": [],
				"Internet": [],
				"Sonstiges": []
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Sonstiges"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Erlaubnis zur Veröffentlichung einmalig für"
			}
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Onlineveröffentlichung"
			},
			"content": {
				"Hiermit erteile ich dem Universitätsklinikum Heidelberg meine Einwilligung, die von mir / meinem Kind gemachten Aufnahmen im Rahmen der Presse- und Öffentlichkeitsarbeit des Universitätsklinikums insbesondere in Print- und Onlinemedien zu verbreiten und zu veröffentlichen. Meine Einwilligung umfasst auch das Recht, die Aufnahmen zu bearbeiten und zu retuschieren, soweit dadurch keine Persönlichkeitsrechte verletzt werden.": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Gesicht"
			},
			"content": {
				"kenntlich": [],
				"geschwärzt": []
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Verhältnis zum Patienten"
			}
		},
		{
			"type": "signature",
			"attributes": { 
				"name": "Datum, Unterschrift"
			}
		}
	]
]
```
[Content](#content)

## Versorgungsmaßnahme
```json
[
	[
		{
			"type": "radio",
			"attributes": {
				"name": "Abdruck / Maßnahme"
			},
			"content": {
				"Gipsabdruck": [],
				"Maßnahme": [],
				"Scan": [],
				"Symphony": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Modellieren"
			},
			"content": {
				"Gips": [],
				"digital": []
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Anmerkungen zur Modelltechnik"
			}
		}
	],
	[
		{
			"type": "checkbox",
			"attributes": {
				"name": "Anprobeergebnis"
			},
			"content": {
				"passgerecht": [],
				"statikgerecht": [],
				"funktion gegeben": [],
				"weitere Anprobe geplant": []
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Anmerkungen zur Anprobe"
			},
			"hint": "z.B. weitere Ergebnisse, Dauer, Vereinbarungen"
		},
		{
			"type": "calendarbutton",
			"attributes": {
				"value": "Weitere Vorbereitungen planen"
			}
		}
	]
]
```
[Content](#content)

## Versorgungsausführung
```json
[
	[
		{
			"type": "checkbox",
			"attributes": {
				"name": "definitive Ausführung"
			},
			"content": {
				"wie genehmigt": []
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Abweichungen von Genehmigung"
			}
		}
	],
	[
		{
			"type": "scanner",
			"description": "Barcode oder UDI-Code scannen",
			"attributes": {
				"name": "Passteil / Material",
				"multiple": true
			},
			"hint":"Die Informationen können auch eingegeben werden."
		},
		{
			"type": "photo",
			"attributes": {
				"name": "Produktaufkleber",
				"multiple": true
			},
			"hint": "Idealerweise die Aufkleber zuvor, wenn möglich, zusammenlegen"
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "sonstige Anmerkungen zur Ausführung"
			}
		}
	]
]
```
[Content](#content)

## Konformitätserklärung
```json
[
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Konformitätserklärung"
			},
			"content": "Die Abteilung Technische Orthopädie des Universitätsklinikums Heidelberg, Schlierbacher Landstraße 200a, 69118 Heidelberg, erklärt hiermit alleinverantwortlich, dass die oben genannte Sonderanfertigung für den oben genannten Patienten individuell hergestellt wurde und den grundlegenden Forderungen der EU-Verordnung 2017/745 entspricht. Sollten bestimmte grundlegende Forderungen nicht eingehalten worden sein, so wurden sie aufgeführt. Die entsprechende Produktdokumentation wird aufbewahrt. Diese Konformitätserklärung bezieht sich nur auf die im Rahmen der Sonderanfertigung für den oben genannten Patienten individuell angefertigten Komponenten. Für etwaige konfektionierte Hilfsmittel sind die jeweiligen Hersteller verantwortlich."
		},
		{
			"type": "signature",
			"attributes": {
				"name": "Datum, Unterschrift Mitarbeiter"
			}
		}
	]
]
```
[Content](#content)

## Vorgangsprotokoll
```json
[
	[
		{
			"type": "textarea",
			"attributes": {
				"name": "Protokoll"
			}
		}
	],
	[
		{
			"type": "photo",
			"attributes": {
				"name": "Bildanhang",
				"multiple": true
			}
		},
		{
			"type": "file",
			"attributes": {
				"name": "Dateianhang",
				"multiple": true
			}
		}
	]
]
```
[Content](#content)

## Produkteinführung
```json
[
	[
		{
			"type": "checkbox",
			"attributes": {
				"name": "Übergangsregelung"
			},
			"content": {
				"Das Produkt ist bereits zuvor eingeführt worden": []
			},
			"hint": "Du bestätigst damit eigenverantwortlich, dass die erforderlichen Prüfungen und Unterlagen bereits vorliegen."
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Produkteinführung"
			},
			"content": {
				"Sicherheitsdatenblatt": [],
				"Preis/Leistung angemessen": [],
				"Lagerplatz vorhanden": [],
				"Lagerbedingungen erfüllbar": [],
				"Min/Max Bestand erforderlich": [],
				"Zertifizierung erforderlich": [],
				"Anpassung Risikoanalyse erforderlich": [],
				"Arbeitsanweisung erforderlich": []
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Ersatz für"
			}
		}
	]
]
```
[Content](#content)

## Stichprobenprüfung
```json
[
	[
		{
			"type": "checkbox",
			"attributes": {
				"name": "Allgemein"
			},
			"content": {
				"CE-Kennzeichnung mit aktueller EU-Konformitätserklärung": [],
				"Sonderanfertigungskennzeichnung": [],
				"Angabe des Handelsnamens": [],
				"Angabe worum es sich handelt": [],
				"Hinweis auf Medizinprodukt": [],
				"UDI-Code vorhanden": [],
				"Haltbarkeitsdatum oder Herstellungsdatum und Verwendungszeitraum": [],
				"Lagerungs- und Handhabungsbedingungen": []
			}
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Erforderliche Angaben der Gebrauchsanweisung"
			},
			"content": {
				"Produktname": [],
				"Hersteller und Anschrift": [],
				"Zweckbestimmung und Indikation": [],
				"Kontraindikationen": [],
				"vorgesehene Anwender": [],
				"Restrisiken und Nebenwirkungen": [],
				"Klinischer Nutzen": [],
				"Leistungsmerkmale": [],
				"Schulungen oder Qualifikation zur prüfung durch Fachpersonal": [],
				"Nutzungshinweise für Anwender": [],
				"Aufbereitungsverfahren sofern zum Wiedereinsatz vorgesehen": [],
				"Lagerungs- und Handhabungshinweise": []
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Ausstellungsdatum/Version der Gebrauchsanweisung"
			}
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Angaben sofern zutreffend"
			},
			"content": {
				"Angaben zur Sterilität": [],
				"Hinweis auf Einmalprodukt": [],
				"Zusammensetzung": [],
				"Biologische Inhaltsstoffe": [],
				"Erforderliche Vorbehandlungen": [],
				"Prüfung der Installation": [],
				"Instandhaltungsmaßnahmen, Ersatz von Verbrauchskomponenten, Kalibrationsmaßnahmen": [],
				"Wahl und Einschränkungen von kombinierbaren Produkten": [],
				"Warnungen, Vorsichshinweise, Maßnahmenempfehlungen oder Verwendungsbeschränkungen sofern zurteffend": []
			}
		}
	]
]
```
[Content](#content)

## Anamnese Prothetik II
```json
[
	[
		{
			"type": "radio",
			"attributes": {
				"name": "Geschlecht",
				"required": true
			},
			"content": {
				"weiblich": [],
				"männlich": [],
				"divers": []
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Diagnose"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Ursache"
			}
		},
		{
			"type": "date",
			"attributes": {
				"name": "Datum"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "betroffener Bereich"
			},
			"hint": "lang = distales Drittel \\nmittellang = mittleres Drittel \\nkurz = proximales Drittel \\nultrakurz = proximale 15%"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Bewegungsausmaß Schulter links"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Bewegungsausmaß Schulter rechts"
			},
			"hint": "Retro/Ante 40-0-90, Ele 170, Ab/Ad 90-0-45, Ar/Ir 60-0-70, ggf. OB (ohne Befund)"
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Bewegungsausmaß Schulter ohne Befund"
			},
			"content": {
				"Schulter links": [],
				"Schulter rechts": []
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Bewegungsausmaß Ellenbogen links"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Bewegungsausmaß Ellenbogen rechts"
			},
			"hint": "Ex/Flex 10-0-150, Pro/Sup 90-0-90, ggf. OB (ohne Befund)"
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Bewegungsausmaß Ellenbogen ohne Befund"
			},
			"content": {
				"Ellenbogen links": [],
				"Ellenbogen rechts": []
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Bewegungsausmaß Handwurzel links"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Bewegungsausmaß Handwurzel rechts"
			},
			"hint": "Ex/Flex 70-0-80, ggf. OB (ohne Befund)"
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Bewegungsausmaß Handwurzel ohne Befund"
			},
			"content": {
				"Handwurzel links": [],
				"Handwurzel rechts": []
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "versorgungsrelevante Einschränkungen"
			},
			"hint": "Begleiterkrankungen, funktionelle Einschränkungen anderer Bereiche, Medikamente, Therapien, Infektionen"
		},
		{
			"type": "number",
			"attributes": {
				"name": "Gewicht"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Größe"
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Bevorzugte Seite"
			},
			"content": {
				"links": [],
				"rechts": []
			}
		}
	],
	[
		{
			"type": "radio",
			"attributes": {
				"name": "Stumpfform"
			},
			"content": {
				"zylindrisch": [],
				"birnenförmig": [],
				"kegelförmig": [],
				"unregelmäßig": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Weichteilzustand"
			},
			"content": {
				"muskulös": [],
				"locker/weich": [],
				"athrophiert": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Stumpfschwankungen"
			},
			"content": {
				"nein": [],
				"ja": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "knöcherne Prominenzen"
			},
			"content": {
				"normal": [],
				"kaum ausgeprägt": [],
				"stark ausgeprägt": []
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Narbengewebe"
			},
			"hint": "Einziehungen, Rudimente, Spalthaut, etc. mit Lokalisation"
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Wundheilung abgeschlossen"
			},
			"content": {
				"ja": [],
				"nein": []
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Zusätzliche Läsionen / Druckstellen"
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Belastungsfähigkeit"
			},
			"content": {
				"nein": [],
				"ja": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Sensibilität"
			},
			"content": {
				"unauffällig": [],
				"vemehrt": [],
				"vermindert": []
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Allergien"
			},
			"hint": "Materialien, Lebensmittel"
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Schmerzsymptomatik"
			},
			"content": {
				"keine": [],
				"Stumpfschmerz": [],
				"Phantomschmerz": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Temperatur"
			},
			"content": {
				"seitengleich": [],
				"wärmer": [],
				"kälter": []
			}
		},
		{
			"type": "range",
			"attributes": {
				"name": "Muskelkraft",
				"min": 0,
				"max": 5,
				"step": 0.5
			},
			"hint": "0: komplette Lähmung keine Muskelaktivität erkennbar \\n1: sehr schwere Lähmung Muskelaktivität erkennbar ohne Bewegungsausschlag \\n2: schwere Lähmung Bewegung unter Aufhebung der Schwerkraft möglich \\n3: deutliche Lähmung Bewegung ohne Widerstand möglich \\n4: leichte Lähmung Bewegung und Gegenhalt gegen leichten Widerstand möglich \\n5: normale Kraft Bewegung und Gegenhalt gegen Widerstand möglich"
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Maßnahmen zur Stumpfformung"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Vorversorgungen"
			},
			"hint": "Ggf. Ausführungsbeschreibung"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Tragedauer"
			}
		}
	],
	[
		{
			"type": "textarea",
			"attributes": {
				"name": "Weitere Hilfsmittel"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Wohnumfeld"
			},
			"hint": "z.b. Familie, Kinder, pflegebedürftige Angehörige, Wohn-, Betreuungssituation"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Beruf"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Hobbies"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Therapieziel / Patientenerwartung"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Bemerkungen"
			}
		}
	]
]
```
[Content](#content)

## Maßblatt Prothetik II
```json
[
	[
		{
			"type": "radio",
			"attributes": {
				"name": "Seite"
			},
			"content": {
				"links": [],
				"rechts": []
			}
		}
	],
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Vergleichsmaße"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Länge Mittelfinger"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Umfang MCP (A)"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Umfang Handgelenk (C)"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Umfang Unterarm prox. Drittel (D)"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Länge Beugefalte - Daumenspitze"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Länge Drehpunkt Ellenbogen - Daumenspitze"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Länge Achsel - Unterseite Unterarm"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Länge Schulterhöhe - Unterseite Unterarm"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Umfang Oberarm (F)"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Sonstige Vergleichsmaße"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Farbe"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Handgröße"
			}
		}
	],
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Unterarmstumpf"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Länge Beugefalte - Stumpfende"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Länge Olecranon - Stumpfende"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Umfang Beugefalte - Oberarm abgewinkelt"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Umfang Beugefalte - Olecranon abgewinkelt"
			}
		}
	],
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Oberarmstumpf"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Länge Achsel - Stumpfende"
			}
		},
		{
			"type": "number",
			"attributes": {
				"name": "Länge Schulterhöhe - Stumpfende"
			}
		}
	],
	[
		{
			"type": "checkbox",
			"attributes": {
				"name": "Startmaß an"
			},
			"content": {
				"Stumpfende": [],
				"Ellenbogen Beugefalte": [],
				"Achsel": []
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Umfangsmaß 0"
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Umfangsmaß / Höhe",
				"multiple": true
			}
		}
	],
	[
		{
			"type": "number",
			"attributes": {
				"name": "geplante maximale Reduzierung in %"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Anmerkungen zur Maßdokumentation"
			}
		},
		{
			"type": "photo",
			"attributes": {
				"name": "Fotos",
				"multiple": true
			}
		}
	]
]
```
[Content](#content)

## Kunststofffertigungsauftrag Prothetik II
```json
[
	[
		{
			"type": "date",
			"attributes": {
				"name": "Fertigstellung bis"
			}
		},
		{
			"type": "calendarbutton",
			"attributes": {
				"value": "Weitere Vorbereitungen planen"
			}
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Polster / Inlet"
			},
			"content": {
				"komplett": [],
				"Pads": []
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Material, Stärke, sonstige Polster"
			}
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Tiefziehverfahren"
			},
			"content": {
				"Umlegeverfahren": [],
				"Tiefziehrahmen": []
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Material, Stärke"
			}
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "FVK-Verfahren"
			},
			"content": {
				"PrePreg": [],
				"Acrylguss": [],
				"Epoxidguss": []
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Dekopapier, Stoff, Farbe"
			}
		}
	],
	[
		{
			"type": "select",
			"attributes": {
				"name": "gemäß Armierungsplan"
			},
			"content": {
				"...":[],
				"Armierungsplan Prothetik II 5.1": [],
				"Armierungsplan Prothetik II 5.2": [],
				"Armierungsplan Prothetik II 5.3": [],
				"Armierungsplan Prothetik II 5.4": [],
				"Armierungsplan Prothetik II 5.5": [],
				"Armierungsplan Prothetik II 5.6": []
			}
		},
		{
			"type": "formbutton",
			"attributes": {
				"value": "Armierungsplan Prothetik II anzeigen",
				"onpointerup": "api.record('get','displayonly', 'Armierungsplan Prothetik II')"
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "individueller Armierungsplan"
			}
		},
		{
			"type": "photo",
			"attributes": {
				"name": "Skizze"
			}
		}
	],
	[
		{
			"type": "checkbox",
			"attributes": {
				"name": "Kunststofffertigung erledigt"
			},
			"content": {
				"Abgeschlossen": []
			}
		}
	]
]
```
[Content](#content)

## Versorgungsplanung Prothetik II
```json
[
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Grundposition"
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Habitus Kontaktschaft"
			},
			"content": {
				"...": [],
				"Finger/Daumen EV 20.0000 KA": [],
				"Finger/Daumen FV 20.0010 KA": [],
				"Partialhand EV 20.0100 KA": [],
				"Partialhand WV 20.0110 KA": [],
				"Transcarpal EV 20.0200 KA": [],
				"Transcarpal WV 20.0210 KA": [],
				"Handexartikulation EV 20.0300 KA": [],
				"Handexartikulation WV 20.0310 KA": [],
				"Unterarm lang EV 20.0400 KA": [],
				"Unterarm lang WV 20.0410 KA": [],
				"Unterarm mittellang EV 20.0500 KA": [],
				"Unterarm mittellang WV 20.0510 KA": [],
				"Unterarm kurz EV 20.0600 KA": [],
				"Unterarm kurz WV 20.0610 KA": [],
				"Unterarm ultrakurz EV 20.0700 KA": [],
				"Unterarm ultrakurz WV 20.0710 KA": [],
				"Unterarm Patschhand 20.5000 KA": [],
				"Ellenbogenexartikulation EV 20.0800 KA": [],
				"Ellenbogenexartikulation WV 20.0810 KA": [],
				"Oberarm lang EV 20.0900 KA": [],
				"Oberarm lang WV 20.0910 KA": [],
				"Oberarm mittellang EV 20.1000 KA": [],
				"Oberarm mittellang WV 20.1010 KA": [],
				"Oberarm kurz EV 20.1100 KA": [],
				"Oberarm kurz WV 20.1110 KA": [],
				"Oberarm ultrakurz EV 20.1200 KA": [],
				"Oberarm ultrakurz EV 20.1210 KA": [],
				"Schulter EV 20.1300 KA": [],
				"Schulter WV 20.1310 KA": [],
				"Schulterformausgleich Silikon 20.5006 KA": [],
				"Schulterformausgleich Thmermoplast interim 20.5005 KA": [],
				"Lenkadaption Kinder 20.5002 KA": [],
				"Lenkadaption langer Unterarm 20.5003 KA": [],
				"Lenkadaption kurzer Unterarm 20.5004 KA": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Habitus Linerschaft"
			},
			"content": {
				"...": [],
				"Transcarpal EV 20.0201 KA": [],
				"Transcarpal WV 20.0211 KA": [],
				"Handexartikulation EV 20.0301 KA": [],
				"Handexartikulation WV 20.0311 KA": [],
				"Unterarm lang EV 20.0401 KA": [],
				"Unterarm lang WV 20.0411 KA": [],
				"Unterarm mittellang EV 20.0501 KA": [],
				"Unterarm mittellang WV 20.0511 KA": [],
				"Unterarm kurz EV 20.0601 KA": [],
				"Unterarm kurz WV 20.0611 KA": [],
				"Unterarm ultrakurz EV 20.0701 KA": [],
				"Unterarm ultrakurz WV 20.0711 KA": [],
				"Ellenbogenexartikulation EV 20.0801 KA": [],
				"Ellenbogenexartikulation WV 20.0811 KA": [],
				"Oberarm lang EV 20.0901 KA": [],
				"Oberarm lang WV 20.0911 KA": [],
				"Oberarm mittellang EV 20.1001 KA": [],
				"Oberarm mittellang WV 20.1011 KA": [],
				"Oberarm kurz EV 20.1101 KA": [],
				"Oberarm kurz WV 20.1111 KA": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Mechanisch Kontaktschaft"
			},
			"content": {
				"...": [],
				"Finger/Daumen EV 20.0020 KA": [],
				"Finger/Daumen FV 20.0030 KA": [],
				"Partialhand EV 20.0120 KA": [],
				"Partialhand WV 20.0130 KA": [],
				"Transcarpal EV 20.0220 KA": [],
				"Transcarpal WV 20.0230 KA": [],
				"Handexartikulation EV 20.0320 KA": [],
				"Handexartikulation WV 20.0330 KA": [],
				"Unterarm lang EV 20.0420 KA": [],
				"Unterarm lang WV 20.0430 KA": [],
				"Unterarm mittellang EV 20.0520 KA": [],
				"Unterarm mittellang WV 20.0530 KA": [],
				"Unterarm kurz EV 20.0620 KA": [],
				"Unterarm kurz WV 20.0630 KA": [],
				"Unterarm ultrakurz EV 20.0720 KA": [],
				"Unterarm ultrakurz WV 20.0730 KA": [],
				"Ellenbogenexartikulation EV 20.0820 KA": [],
				"Ellenbogenexartikulation WV 20.0830 KA": [],
				"Oberarm lang EV 20.0920 KA": [],
				"Oberarm lang WV 20.0930 KA": [],
				"Oberarm mittellang EV 20.1020 KA": [],
				"Oberarm mittellang WV 20.1030 KA": [],
				"Oberarm kurz EV 20.1120 KA": [],
				"Oberarm kurz WV 20.1130 KA": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Mechanisch Linerschaft"
			},
			"content": {
				"...": [],
				"Transcarpal EV 20.0221 KA": [],
				"Transcarpal WV 20.0231 KA": [],
				"Handexartikulation EV 20.0321 KA": [],
				"Handexartikulation WV 20.0331 KA": [],
				"Unterarm lang EV 20.0421 KA": [],
				"Unterarm lang WV 20.0431 KA": [],
				"Unterarm mittellang EV 20.0521 KA": [],
				"Unterarm mittellang WV 20.0531 KA": [],
				"Unterarm kurz EV 20.0621 KA": [],
				"Unterarm kurz WV 20.0631 KA": [],
				"Unterarm ultrakurz EV 20.0721 KA": [],
				"Unterarm ultrakurz WV 20.0731 KA": [],
				"Ellenbogenexartikulation EV 20.0821 KA": [],
				"Ellenbogenexartikulation WV 20.0831 KA": [],
				"Oberarm lang EV 20.0921 KA": [],
				"Oberarm lang WV 20.0931 KA": [],
				"Oberarm mittellang EV 20.1021 KA": [],
				"Oberarm mittellang WV 20.1031 KA": [],
				"Oberarm kurz EV 20.1121 KA": [],
				"Oberarm kurz WV 20.1131 KA": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Elektrisch Kontaktschaft"
			},
			"content": {
				"...": [],
				"Finger/Daumen EV 20.0040 KA": [],
				"Finger/Daumen FV 20.0050 KA": [],
				"Partialhand EV 20.0140 KA": [],
				"Partialhand WV 20.0150 KA": [],
				"Partialhand Testversorgung 20.0160 KA": [],
				"Partialhand Fertigstellung 20.0170 KA": [],
				"Transcarpal EV 20.0240 KA": [],
				"Transcarpal WV 20.0250 KA": [],
				"Transcarpal Testversorgung 20.0260 KA": [],
				"Transcarpal Fertigstellung 20.0270 KA": [],
				"Handexartikulation EV 20.0340 KA": [],
				"Handexartikulation WV 20.0350 KA": [],
				"Handexartikulation Testversorgung 20.0360 KA": [],
				"Handexartikulation Fertigstellung 20.0370 KA": [],
				"Unterarm lang EV 20.0440 KA": [],
				"Unterarm lang WV 20.0450 KA": [],
				"Unterarm lang Testversorgung 20.0460 KA": [],
				"Unterarm lang Fertigstellung 20.0470 KA": [],
				"Unterarm mittellang EV 20.0540 KA": [],
				"Unterarm mittellang WV 20.0550 KA": [],
				"Unterarm mittellang Testversorgung 20.0560 KA": [],
				"Unterarm mittellang Fertigstellung 20.0570 KA": [],
				"Unterarm kurz EV 20.0640 KA": [],
				"Unterarm kurz WV 20.0650 KA": [],
				"Unterarm kurz Testversorgung 20.0660 KA": [],
				"Unterarm kurz Fertigstellung 20.0670 KA": [],
				"Unterarm ultrakurz EV 20.0740 KA": [],
				"Unterarm ultrakurz WV 20.0750 KA": [],
				"Unterarm ultrakurz Testversorgung 20.0760 KA": [],
				"Unterarm ultrakurz Fertigstellung 20.0770 KA": [],
				"Ellenbogenexartikulation EV 20.0840 KA": [],
				"Ellenbogenexartikulation WV 20.0850 KA": [],
				"Ellenbogenexartikulation Testversorgung 20.0860 KA": [],
				"Ellenbogenexartikulation Fertigstellung 20.0870 KA": [],
				"Oberarm lang EV 20.0940 KA": [],
				"Oberarm lang WV 20.0950 KA": [],
				"Oberarm lang Testversorgung 20.0960 KA": [],
				"Oberarm lang Fertigstellung 20.0970 KA": [],
				"Oberarm mittellang EV 20.1040 KA": [],
				"Oberarm mittellang WV 20.1050 KA": [],
				"Oberarm mittellang Testversorgung 20.1060 KA": [],
				"Oberarm mittellang Fertigstellung 20.1070 KA": [],
				"Oberarm kurz EV 20.1140 KA": [],
				"Oberarm kurz WV 20.1150 KA": [],
				"Oberarm kurz Testversorgung 20.1160 KA": [],
				"Oberarm kurz Fertigstellung 20.1170 KA": [],
				"Oberarm ultrakurz EV 20.1240 KA": [],
				"Oberarm ultrakurz EV 20.1250 KA": [],
				"Oberarm ultrakurz Testversorgung 20.1260 KA": [],
				"Oberarm ultrakurz Fertigstellung 20.1270 KA": [],
				"Schulter EV 20.1340 KA": [],
				"Schulter WV 20.1350 KA": [],
				"Schulter Testversorgung 20.1360 KA": [],
				"Schulter Fertigstellung 20.1370 KA": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Elektrisch Linerschaft"
			},
			"content": {
				"...": [],
				"Transcarpal EV 20.0241 KA": [],
				"Transcarpal WV 20.0251 KA": [],
				"Transcarpal Testversorgung 20.0261 KA": [],
				"Transcarpal Fertigstellung 20.0271 KA": [],
				"Handexartikulation EV 20.0341 KA": [],
				"Handexartikulation WV 20.0351 KA": [],
				"Handexartikulation Testversorgung 20.0361 KA": [],
				"Handexartikulation Fertigstellung 20.0371 KA": [],
				"Unterarm lang EV 20.0441 KA": [],
				"Unterarm lang WV 20.0451 KA": [],
				"Unterarm lang Testversorgung 20.0461 KA": [],
				"Unterarm lang Fertigstellung 20.0471 KA": [],
				"Unterarm mittellang EV 20.0541 KA": [],
				"Unterarm mittellang WV 20.0551 KA": [],
				"Unterarm mittellang Testversorgung 20.0561 KA": [],
				"Unterarm mittellang Fertigstellung 20.0571 KA": [],
				"Unterarm kurz EV 20.0641 KA": [],
				"Unterarm kurz WV 20.0651 KA": [],
				"Unterarm kurz Testversorgung 20.0661 KA": [],
				"Unterarm kurz Fertigstellung 20.0671 KA": [],
				"Unterarm ultrakurz EV 20.0741 KA": [],
				"Unterarm ultrakurz WV 20.0751 KA": [],
				"Unterarm ultrakurz Testversorgung 20.0761 KA": [],
				"Unterarm ultrakurz Fertigstellung 20.0771 KA": [],
				"Ellenbogenexartikulation EV 20.0841 KA": [],
				"Ellenbogenexartikulation WV 20.0851 KA": [],
				"Ellenbogenexartikulation Testversorgung 20.0861 KA": [],
				"Ellenbogenexartikulation Fertigstellung 20.0871 KA": [],
				"Oberarm lang EV 20.0941 KA": [],
				"Oberarm lang WV 20.0951 KA": [],
				"Oberarm lang Testversorgung 20.0961 KA": [],
				"Oberarm lang Fertigstellung 20.0971 KA": [],
				"Oberarm mittellang EV 20.1041 KA": [],
				"Oberarm mittellang WV 20.1051 KA": [],
				"Oberarm mittellang Testversorgung 20.1061 KA": [],
				"Oberarm mittellang Fertigstellung 20.1071 KA": [],
				"Oberarm kurz EV 20.1141 KA": [],
				"Oberarm kurz WV 20.1151 KA": [],
				"Oberarm kurz Testversorgung 20.1161 KA": [],
				"Oberarm kurz Fertigstellung 20.1171 KA": []
			}
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Mehraufwand zur Grundposition"
			},
			"content": {
				"Tumorversorgung 20.4001 KA": [],
				"Dysmelieversorgung 20.4002 KA": [],
				"Habitus Kinderversorgung 20.4003 KA": [],
				"Myo Kinderversorgung 20.4004 KA": [],
				"bilateral 20.4005 KA": [],
				"zusätzliche Dokumentation 20.4006 KA": [],
				"Gebrauchsschulung fremdsprachlich 20.4017 KA": [],
				"Unterkunft (6 W, 2 Pers.), pauschalen privat 20.4018 KA": [],
				"Videodokumentation 20.4011 KA": [],
				"Demontage der vorhandenen Prothese 20.4012 KA": []
			},
			"hint": "Kinderversorgung bis 12. Lj., bei kognitiven Einschränkungen auch bis 18, nicht gleichzeitig mit Dysmelieversorgung verwendbar"
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Zusatzinformationen zum Mehraufwand"
			},
			"hint": "Arbeitszeiten zu Tumorversorgung, bilateral, ggf. Begründungen"
		},
		{
			"type": "select",
			"attributes": {
				"name": "zusätzlicher Testschaft Habitus"
			},
			"content": {
				"...": [],
				"Partialhand 20.0105 KA": [],
				"Unterarm lang 20.0405 KA": [],
				"Unterarm mittellang 20.0505 KA": [],
				"Unterarm kurz 20.0605 KA": [],
				"Unterarm ultrakurz 20.0705 KA": [],
				"Ellenbogenexartikulation 20.0805 KA": [],
				"Oberarm lang 20.0905 KA": [],
				"Oberarm mittellang 20.1005 KA": [],
				"Oberarm kurz 20.1105 KA": [],
				"Oberarm ultrakurz 20.1205 KA": [],
				"Schulter 20.1305 KA": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "zusätzlicher Testschaft Mechanisch"
			},
			"content": {
				"...": [],
				"Partialhand 20.0125 KA": [],
				"Unterarm lang 20.0425 KA": [],
				"Unterarm mittellang 20.0525 KA": [],
				"Unterarm kurz 20.0625 KA": [],
				"Unterarm ultrakurz 20.0725 KA": [],
				"Ellenbogenexartikulation 20.0825 KA": [],
				"Oberarm lang 20.0925 KA": [],
				"Oberarm mittellang 20.1025 KA": [],
				"Oberarm kurz 20.1125 KA": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "zusätzlicher Testschaft Elektrisch"
			},
			"content": {
				"...": [],
				"Partialhand 20.0145 KA": [],
				"Unterarm lang 20.0445 KA": [],
				"Unterarm mittellang 20.0545 KA": [],
				"Unterarm kurz 20.0645 KA": [],
				"Unterarm ultrakurz 20.0745 KA": [],
				"Ellenbogenexartikulation 20.0845 KA": [],
				"Oberarm lang 20.0945 KA": [],
				"Oberarm mittellang 20.1045 KA": [],
				"Oberarm kurz 20.1145 KA": [],
				"Oberarm ultrakurz 20.1245 KA": [],
				"Schulter 20.1345 KA": []
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Begründung für zusätzlichen Testschaft"
			},
			"hint": "Arbeitszeiten zu Tumorversorgung, bilateral, ggf. Anzahl, Begründungen"
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Bauteile für Testversorgung"
			},
			"content": {
				"Mietgebühren Leihpassteile TOH 20.4016 KA": [],
				"Handling für Leihhände 20.4019 KA": [],
				"Otto Bock AxonBus Rotator SMR-PK=51": [],
				"Otto Bock AxonBus System SMR-PK=55": [],
				"Otto Bock Systemhand SMR-PK=56": [],
				"Össur iLimb 11869 AS": [],
				"Otto Bock BeBionic SMR-PK=53": [],
				"TASKA 21450 AS": [],
				"Otto Bock DynamicArm SMR-PK=50": [],
				"Otto Bock AxonArm SMR-PK=55": []
			},
			"hint": "Mitgebühren je Woche"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Zusatzinformationen zur Testversorgung"
			},
			"hint": "z.B. Anzahl Wochen"
		}
	],
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Schaftsystem"
			}
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Zusätze am Schaftsystem"
			},
			"content": {
				"Kaschierung 75E10": [],
				"HeiSens Leitspot pro Elektrode 75E80": [],
				"RTV-Schicht zur Adhäsionssteigerung 75E20": [],
				"Zusätzliche Gurtverstärkung / Matrix 75E40": [],
				"Zusätzlicher Narbenausgleich 75E30": []
			},
			"hint": "Ein Narbenausgleich ist bereits in der Grundposition enthalten"
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Sonstige Zusätze am Schaftsystem"
			},
			"hint": "oder ggf. Anzahl"
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Mehraufwand am Schaftsystem"
			},
			"content": {
				"Prepreg 20.4007 KA": [],
				"Schaftsonderlösungen 20.4008 KA": []
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Mehrarbeitszeiten für Mehraufwand am Schaftsystem"
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Abweichendes Schaftsystem Habitus"
			},
			"content": {
				"...": [],
				"Gießharzkontaktschaft 20.0423 KA": [],
				"Thermoplastischer Innenschaft Partialhand 20.0102 KA": [],
				"Thermoplastischer Innenschaft Unterarmprothese 20.0502 KA": [],
				"Thermoplastischer Innenschaft Unterarm ultrakurz / Ellenbogenexartikulation 20.0802 KA": [],
				"Thermoplastischer Innenschaft Oberarm 20.1002 KA": [],
				"Thermoplastischer Innenschaft Schulter 20.1302 KA": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Abweichendes Schaftsystem Mechanisch"
			},
			"content": {
				"...": [],
				"Gießharzkontaktschaft 20.0423 KA": [],
				"Thermoplastischer Innenschaft Partialhand 20.0122 KA": [],
				"Thermoplastischer Innenschaft Unterarmprothese 20.0522 KA": [],
				"Thermoplastischer Innenschaft Unterarm ultrakurz / Ellenbogenexartikulation 20.0822 KA": [],
				"Thermoplastischer Innenschaft Oberarm 20.1022 KA": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Abweichendes Schaftsystem Elektrisch"
			},
			"content": {
				"...": [],
				"Gießharzkontaktschaft 20.0423 KA": [],
				"Thermoplastischer Innenschaft Partialhand 20.0142 KA": [],
				"Thermoplastischer Innenschaft Unterarmprothese 20.0542 KA": [],
				"Thermoplastischer Innenschaft Unterarm ultrakurz 20.0842 KA": [],
				"Thermoplastischer Innenschaft Ellenbogenexartikulation 20.0842 KA": [],
				"Thermoplastischer Innenschaft Oberarm 20.1042 KA": [],
				"Thermoplastischer Innenschaft Schulter 20.1342 KA": []
			},
			"hint": "Bei Abweichungen vom Silikonschaftsystem gemäß Grundposition muss die Kalkulation durch den fachlichen Leiter im Einzelfall um den Aufwand korrigiert werden, KV in jedem Fall zu Ansicht."
		},
		{
			"type": "checkbox",
			"attributes": {
				"name": "Anziehtechnik"
			},
			"content": {
				"Otto Bock Anziehlubricant 633S2": [],
				"Protheseus BOA PK022013005 + 20.3003 KA": [],
				"Otto Bock Einzugrohr 99B13=21 + 20.3000 KA": [],
				"Otto Bock Rohrventil 12V10 + 20.3001 KA": [],
				"Ohnhänderschnalle 20.3002 KA": [],
				"Uniprox Anziehhilfe 4618 AS": [],
				"Schlauchbinde 8cm 9757 AS": []
			}
		},
		{
			"type": "productselection",
			"attributes": {
				"name": "Anziehtechnik Artikel",
				"multiple": true
			},
			"hint": "falls abweichend"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Anziehtechnik sonstiges"
			},
			"hint": "Sonstige Optionen, ggf Anzahl der o.g. sofern nicht bei Artikel direkt angepasst"
		}
	],
	[
		{
			"type": "radio",
			"attributes": {
				"name": "Bandage"
			},
			"content": {
				"Haltebandage Silikon 20.1400 KA": [],
				"Haltebandage Thermoplastisch 20.1204 KA": [],
				"Haltebandage Textil 20.1404 KA": [],
				"Haltebandage Textil mit Silikonachselschutz 20.1414 KA": [],
				"Steuerungsbandage Silikon 20.1420 KA": [],
				"Steuerungsbandage Textil 20.1424 KA": [],
				"Steuerungsbandage Textil mit Silikonachselschutz 20.1434 KA": []
			}
		}
	],
	[
		{
			"type": "radio",
			"attributes": {
				"name": "elektrische Steuerung"
			},
			"content": {
				"Össur Remote 50Hz 300mm PL069466B": [],
				"Össur Remote 50Hz 600mm PL069468B": [],
				"Otto Bock Compact 50Hz 13E200=50": [],
				"Otto Bock Compact 60Hz 13E200=60": [],
				"Otto Bock Lineartransducer 9X52": [],
				"Otto Bock Elektrodenkabel 300mm 13E129=G300": [],
				"Otto Bock Elektrodenkabel 1000mm 13E129=G1000": [],
				"zusätzlicher Myotest 20.3103 KA": [],
				"zusätzliche Elektrode (ab der 3.) 20.3104 KA": [],
				"zusätzliches Steuerungselement 20.3101 KA": [],
				"Otto Bock Druckschalter 9X37": [],
				"Otto Bock Kabel für Druckschalter 13E99=1200": [],
				"Schalterinstallation 20.1434 KA": []
			}
		},
		{
			"type": "productselection",
			"attributes": {
				"name": "elektrische Steuerung Artikel",
				"multiple": true
			},
			"hint": "Schalter, Kabel falls abweichend oder zusätzlich erforderlich"
		},
		{
			"type": "text",
			"attributes": {
				"name": "elektrische Steuerung sonstiges"
			},
			"hint": "Sonstige Optionen, ggf Anzahl der o.g. sofern nicht bei Artikel direkt angepasst, zusätzliche Arbeitszeit für Elektroden"
		}
	],
	[
		{
			"type": "radio",
			"attributes": {
				"name": "Akkusystem"
			},
			"content": {
				"Otto Bock MyoEnergy Integral Kinder 757B35=1": [],
				"Otto Bock MyoEnergy Integral 1150mAH 757B35=3": [],
				"Otto Bock MyoEnergy Integral 3450mAH 757B35=5": [],
				"Otto Bock MyoCharge 757L35": [],
				"Neuhof Motion Control zweigeteilt 512B18": [],
				"zusätzliches Akkusystem 20.30201 KA": []
			}
		},
		{
			"type": "productselection",
			"attributes": {
				"name": "Akkusystem Artikel",
				"multiple": true
			},
			"hint": "falls abweichend oder zusätzlich erforderlich"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Akkusystem sonstiges"
			},
			"hint": "Sonstige Optionen, ggf Anzahl der o.g. sofern nicht bei Artikel direkt angepasst, Arbeitszeit für zusätzliches Akkusystem, etc."
		}
	],
	[
		{
			"type": "radio",
			"attributes": {
				"name": "Controller"
			},
			"content": {
				"Otto Bock 7in1 9E420": [],
				"Otto Bock Myorotronic 13E205": [],
				"Otto Bock Analogadapter für DynamicArm 13E100": []
			}
		},
		{
			"type": "productselection",
			"attributes": {
				"name": "Controller Artikel",
				"multiple": true
			},
			"hint": "falls abweichend oder zusätzlich erforderlich"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Controller sonstiges"
			},
			"hint": "Sonstige Optionen, ggf Anzahl der o.g. sofern nicht bei Artikel direkt angepasst"
		}
	],
	[
		{
			"type": "checkbox",
			"attributes": {
				"name": "Schultergelenk"
			},
			"content": {
				"Heidelberger Schultergelenk 4362 AS": [],
				"Otto Bock MovoShoulder Swing 12S6": [],
				"exoskelettal FVK-Verbindungssegment 20.0943 KA": []
			}
		},
		{
			"type": "productselection",
			"attributes": {
				"name": "Schultergelenk Artikel",
				"multiple": true
			},
			"hint": "z.B. 12S4 mit Eingussring, 12S7 Kugelschultergelenk etc. ggf. zzgl. Rohr, Adapter und Eingussring"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Schultergelenk sonstiges"
			},
			"hint": "Sonstige Optionen, ggf Anzahl der o.g. sofern nicht bei Artikel direkt angepasst"
		}
	],
	[
		{
			"type": "checkbox",
			"attributes": {
				"name": "Ellenbogengelenk"
			},
			"content": {
				"MICO Elbow TOH": [],
				"Heidelberger Ellenbogengelenk 4334 AS": [],
				"Neuhof Hosmer Schienengelenk 504D09": [],
				"Mehraufwand umgedrehtes Hosmergelenk 20.3402 KA": [],
				"Otto Bock AFB Beugehilfe 12K39 + 13G50 + 20.3403 KA": [],
				"Otto Bock ErgoArm+ 12K42": [],
				"Otto Bock ErgoArm e+ 12K50": [],
				"Otto Bock AxonArm 12K501": [],
				"Otto Bock DynamicArm 12K100N + SP-12K100N=3+2": [],
				"Otto Bock DynamicArm+ 12K110N + SP-12K110N=3+2": []
			}
		},
		{
			"type": "productselection",
			"attributes": {
				"name": "Ellenbogengelenk Artikel",
				"multiple": true
			},
			"hint": "falls abweichend z.B. 12K6 Passiv, 12R6 Modular TH, 12R7 Modular SH etc. ggf. zzgl. Adapter und Eingussring"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Ellenbogengelenk sonstiges"
			},
			"hint": "Sonstige Optionen, ggf Anzahl der o.g. sofern nicht bei Artikel direkt angepasst"
		}
	],
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Handgelenk"
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Handgelenk habitus",
				"multiple": true
			},
			"content": {
				"...": [],
				"Gewindeadapter TOH f. Habitus 20.3507 KA": [],
				"Otto Bock Rohradapter mit Innengewinde 10R2=M12x1.5": [],
				"Otto Bock Rohradapter mit Innengewinde (Flexion) 10R3=M12x1.5": [],
				"Neuhof Omega 1/2\" 503B54": [],
				"Neuhof Omega Zusatzzapfen 9503B54=1": [],
				"Otto Bock Eingussring Babyhand FV 11D31": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Handgelenk mechanisch",
				"multiple": true
			},
			"content": {
				"...": [],
				"Gewindeadapter TOH f. Habitus 20.3507 KA": [],
				"Otto Bock Rohradapter mit Innengewinde 10R2=M12x1.5": [],
				"Otto Bock Rohradapter mit Innengewinde (Flexion) 10R3=M12x1.5": [],
				"Neuhof Omega 1/2\" 503B54": [],
				"Neuhof Omega Zusatzzapfen 9503B54=1": [],
				"Otto Bock Kugelrasten 10V8": [],
				"Otto Bock Lochteller 10A43": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Handgelenk elektrisch",
				"multiple": true
			},
			"content": {
				"...": [],
				"Otto Bock Eingussring für Kinderhand 10S16=34": [],
				"TASKA Laminiermanschette 45mm TASKA-12BB01": [],
				"Otto Bock Eingussring QWD 10S1": [],
				"Otto Bock Kupplungseinsatz/Koaxialstecker 10S4 + 9E169": [],
				"Össur WD-Eingussplatte SA149003": [],
				"Otto Bock Rotator 10S17 + 20.3502 KA": [],
				"Otto Bock AxonAdapter (passiv) 9S501": [],
				"Otto Bock Axon Rotation 9S503": [],
				"Neuhof Multiflex mit QWD 508Z18": [],
				"Endoskelettalaufnahme für QWD 20.3503 KA": [],
				"Otto Bock Verschlussautomat mit Gewindeaufnahme 11S47=44": [],
				"Elastomerring für MyolinoWrist 20.3504 KA": []
			}
		},
		{
			"type": "productselection",
			"attributes": {
				"name": "Handgelenk Artikel",
				"multiple": true
			},
			"hint": "falls abweichend z.B. MyolinoWrist 10V51=2 zzgl. (Zusatz-)Adapter und Eingussring"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Handgelenk sonstiges"
			},
			"hint": "Sonstige Optionen, ggf Anzahl der o.g. sofern nicht bei Artikel direkt angepasst"
		}
	],
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Handpassteil"
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Handpassteil habitus",
				"multiple": true
			},
			"content": {
				"...": [],
				"ECHO TOH 17061 AS": [],
				"Otto Bock Physolino Babyhand 8K5": [],
				"Neuhof Newlife mit Schaumkern 501B63": [],
				"Otto Bock passiv Innenhand 8S7": [],
				"Schubert&Braun ThreeDeeFlex Innenhand 8PW": [],
				"Neuhof MultiD 1/2\" 502L63": [],
				"Neuhof TRS-Swinger TD 1/2\" 20966 AS": [],
				"Neuhof Point Digit (Stk) 500D11": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Handpassteil mechanisch",
				"multiple": true
			},
			"content": {
				"...": [],
				"Otto Bock MovoHook Aluminium 10A71": [],
				"Otto Bock MovoHook Stahl 10A81": [],
				"Otto Bock Einzughand Perlon 8K22": [],
				"Neuhof Dorrancehook Kindergröße 55054": [],
				"Neuhof Dorrancehook Erwachsenengröße 55044": []
			}
		},
		{
			"type": "select",
			"attributes": {
				"name": "Handpassteil elektrisch",
				"multiple": true
			},
			"content": {
				"...": [],
				"Otto Bock Kinderhand 8E51": [],
				"Neuhof ProHand 508D09": [],
				"Neuhof Arbeitsgreifer ETD2 508D37": [],
				"Otto Bock DMC transcarpal 8E44=6": [],
				"Otto Bock VariPlus QWD 8E38=9": [],
				"Össur iLimb PK3 TBX50472 + TBX5017(3-5)": [],
				"Össur iLimb PK4 (Flexion) TBX50473 + TBX5017(3-5)": [],
				"TASKA CX QWD TASKA DxFx31-U1 + TASKA-DHEW-03": [],
				"TASKA CX LP TASKA DxCx31-U1 + TASKA-DHEW-03": [],
				"TASKA Gen2 QWD TASKA CxFx21-U1 + TASKA-BHEW-03": [],
				"TASKA Gen LP TASKA CxCx21-U1 + TASKA-BHEW-03": []
			}
		},
		{
			"type": "productselection",
			"attributes": {
				"name": "Handpassteil Artikel",
				"multiple": true
			},
			"hint": "falls abweichend zzgl. Gerantie, (Zusatz-)Adapter oder Befestigungsrahmen"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Handpassteil sonstiges"
			},
			"hint": "Sonstige Optionen, ggf Anzahl der o.g. sofern nicht bei Artikel direkt angepasst"
		}
	],
	[
		{
			"type": "checkbox",
			"attributes": {
				"name": "Handschuh"
			},
			"content": {
				"Otto Bock PVC Myo Kinder 8S20N": [],
				"Otto Bock PVC Myo Gr.7 8S13N": [],
				"Otto Bock PVC Myo Erw. 8S11N": [],
				"Otto Bock PVC habitus 8S4N": [],
				"Neuhof PVC Realskin 507H09": [],
				"Neuhof Newlife Myo 507B18": [],
				"Neuhof Newlife ohne Kern 501B45": [],
				"Otto Bock Systeminnenhand 8X18": [],
				"Otto Bock Axon Skin PVC 8S501": [],
				"Otto Bock Axon Skin Silikon 8S710": [],
				"Össur iLimb Skin natural (Pack) TBX50126": [],
				"Mehraufwand Silikonhandschuh 20.3701 KA": [],
				"Mehraufwand Oberflächenbehandlung 20.3809 KA": []
			}
		},
		{
			"type": "productselection",
			"attributes": {
				"name": "Handschuh Artikel",
				"multiple": true
			},
			"hint": "falls abweichend"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Handschuh sonstiges"
			},
			"hint": "Sonstige Optionen, ggf Anzahl der o.g. sofern nicht bei Artikel direkt angepasst"
		}
	],
	[
		{
			"type": "checkbox",
			"attributes": {
				"name": "Kosmetik"
			},
			"content": {
				"elastischer Überzug Unterarm 20.3801 KA": [],
				"elastischer Überzug Oberarm 20.3802 KA": [],
				"elastischer Überzug Schulter 20.3803 KA": [],
				"Silikonschutzüberzug Unterarm 20.3804 + 20.3809 KA": [],
				"Silikonschutzüberzug Oberarm 20.3804 + 20.3809 KA": [],
				"Silikonschutzüberzug Schulter 20.3805 + 20.3809 KA": [],
				"Weichschaumkosmetik Oberarm 20.3807 KA": [],
				"Weichschaumkosmetik Schulter 20.3808 KA": [],
				"individuelle Kosmetik Fremdleistung 20.3806 KA": []
			}
		},
		{
			"type": "productselection",
			"attributes": {
				"name": "Kosmetik Artikel",
				"multiple": true
			},
			"hint": "z.B. 15K10 Weichschaum, 13R9=65 Anschlussscheibe, 13Z157 Carboncover"
		},
		{
			"type": "text",
			"attributes": {
				"name": "Kosmetik sonstiges"
			},
			"hint": "Sonstige Optionen, ggf Anzahl der o.g. sofern nicht bei Artikel direkt angepasst"
		}
	],
	[
		{
			"type": "productselection",
			"attributes": {
				"name": "Sonstige Artikel",
				"multiple": true
			},
			"hint": "z.B. 13Z47 Eingussring bei Schaftneuanfertigung"
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Sonstige Aufwendungen"
			}
		}
	]
]
```
[Content](#content)

## Checkliste Prothetik II
```json
[
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Ausgangsprüfung Checkliste"
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Ränder"
			},
			"content": {
				"sauber entgratet": [],
				"entfällt": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Oberfläche"
			},
			"content": {
				"gereinigt": [],
				"entfällt": []
			},
			"hint": "Anzeichnungen, Klebereste, etc."
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Klebungen"
			},
			"content": {
				"sicher": [],
				"entfällt": []
			},
			"hint": "Randvulkanisation, Haftband, Abdeckungen, etc."
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Gelenk"
			},
			"content": {
				"läuft frei und sperrt zuverlässig": [],
				"entfällt": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Verschlüsse oder Bandage"
			},
			"content": {
				"sicher befestigt und gepolstert": [],
				"entfällt": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Nähte"
			},
			"content": {
				"sauber und verriegelt": [],
				"entfällt": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Schraubverbindungen"
			},
			"content": {
				"gesichert": [],
				"entfällt": []
			},
			"hint": "Schraubensicherung, Drehmoment, etc."
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Steuerung"
			},
			"content": {
				"soweit nachvollziehbar zuverlässig": [],
				"entfällt": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Akku"
			},
			"content": {
				"geladen": [],
				"entfällt": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Kontakte und Gleitlager"
			},
			"content": {
				"gefettet": [],
				"entfällt": []
			},
			"hint": "Silikonfett, Schmiermittel"
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Besonderheiten der Ausführung"
			},
			"hint": "spezielle Verklebungen, Kabelverläufe, etc."
		}
	],
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Lieferumfang"
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Prothesenschaft"
			},
			"content": {
				"abgabebereit": [],
				"entfällt": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Prothesenhand"
			},
			"content": {
				"abgabebereit": [],
				"entfällt": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Wechselhandschuh(e)"
			},
			"content": {
				"abgabebereit": [],
				"entfällt": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Wechselakku(s)"
			},
			"content": {
				"abgabebereit": [],
				"entfällt": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Ladegerät"
			},
			"content": {
				"abgabebereit": [],
				"entfällt": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Anziehhilfe(n)"
			},
			"content": {
				"abgabebereit": [],
				"entfällt": []
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Sonstige abgabebereiten Komponenten"
			}
		}
	]
]
```
[Content](#content)

## Abgabeprotokoll Prothetik II
```json
[
	[
		{
			"type": "textsection",
			"attributes": {
				"name": "Abgabeprotokoll"
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Anziehtechnik"
			},
			"content": {
				"unterwiesen und geprüft": [],
				"entfällt": []
			},
			"hint": "Nutzung von Anziehhilfe, Verschlusssystem, Weichteilverteilung, etc."
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Bedienung"
			},
			"content": {
				"unterwiesen und geprüft": [],
				"entfällt": []
			},
			"hint": "Ein- und Ausschalten, Ladefunktion, Gelenkarretierung, etc."
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Funktion"
			},
			"content": {
				"unterwiesen und geprüft": [],
				"entfällt": []
			},
			"hint": "Steuerung des Prothesenfußes, Rotationsadapter, Kniegelenksfunktion, Hüftfunktion, Umschaltvorgänge"
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Hygienemaßnahmen"
			},
			"content": {
				"unterwiesen und geprüft": [],
				"entfällt": []
			},
			"hint": "Reinigung der Prothesenkomponenten, Hautpflege"
		},
		{
			"type": "radio",
			"attributes": {
				"name": "allgemeine Hinweise"
			},
			"content": {
				"unterwiesen": [],
				"entfällt": []
			},
			"hint": "Einsatzlimitierungen, Anwenderinformationen, tägliche Kontrolle, Wartungshinweise und -intervalle, Warnhinweise"
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Passform und Funktion"
			},
			"content": {
				"gegeben": [],
				"mangelhaft": []
			}
		},
		{
			"type": "radio",
			"attributes": {
				"name": "Versorgungsziel"
			},
			"content": {
				"erreicht": [],
				"verfehlt": []
			}
		},
		{
			"type": "textarea",
			"attributes": {
				"name": "Bemerkungen zur Abgabe"
			}
		}
	]
]
```
[Content](#content)

## Gebrauchsanweisung Prothetik II
```json
```
[Content](#content)

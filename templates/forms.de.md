# Content
### Common
* [Basisdaten](#basisdaten)
* [Datenverarbeitung - Auftragserteilung - Schweigepflichtentbindung](#datenverarbeitung--auftragserteilung--schweigepflichtentbindung)
* [Kunststofffertigungsauftrag](#kunststofffertigungsauftrag)
* [Silikonfertigungsauftrag](#silikonfertigungsauftrag)
* [Empfangsbestätigung](#empfangsbestätigung)
* [Erlaubnis zur Ausstellung und Veröffentlichung](#erlaubnis-zur-ausstellung-und-veröffentlichung)

### Prosthetics 2
* [Anamnese Prothetik II](#anamnese-prothetik-ii)
* [Maßblatt Prothetik II](#maßblatt-prothetik-ii)
* [Versorgungsplanung Prothetik II](#versorgungsplanung-prothetik-ii)
* [Versorgungsdokumentation Prothetik II](#versorgungsdokumentation-prothetik-ii)
* [Gebrauchsanweisung Prothetik II](#gebrauchsanweisung-prothetik-ii)

## Basisdaten
```json
[
	[
		{
			"type": "identify",
			"attributes": {
				"name": "Vorgang"
			}
		},
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
				"name": "Geburtsdatum",
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
			"type": "identify",
			"attributes": {
				"name": "Vorgang"
			}
		},
		{
			"type": "textblock",
			"description": "Datenverarbeitung durch und Auftragserteilung an",
			"content": "Universitätsklinikum Heidelberg, Zentrum für Orthopädie, Unfallchirurgie und Paraplegiologie \\nAbteilung Technische Orthopädie, Schlierbacher Landstraße 200a, 69118 Heidelberg"
		}
	],
	[
		{
			"type": "textblock",
			"description": "Information zur Datenverarbeitung",
			"content": "Die Bereitstellung und Verarbeitung Ihrer Daten ist im Rahmen Ihrer Versorgung in unserem Haus erforderlich und Bestandteil des Behandlungsvertrags. Bei Bedarf stellen wir Ihnen diese Informationen auch in einem größeren Ausdruck zur Verfügung. Allgemein gilt die Erklärung zur Datenverarbeitung und Datenschutz des Universitätsklinikums Heidelberg. \\nVerarbeitungszwecke im Rahmen Ihrer Hilfsmittelversorgung: Hilfsmittelversorgung, Patientenverwaltung und Abrechnung, interdisziplinäre Konferenzen zur Analyse und Erörterung von Diagnostik und Therapie, Versorgungsdokumentation, Erstellung von Berichten und Stellungnahmen, Qualitätssicherung in Versorgung und ihrer Organisation, Unterrichtung von Mit-/Weiterversorgern im erforderlichen Umfang, Eingabe der Adressdaten und ggf. Rufnummer an Versanddienstleister, Kontaktaufnahme auch hinsichtlich eines Erinnerungsservices nach Versorgungsabschluss."
		},
		{
			"type": "textblock",
			"description": "Auftragserteilung",
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
			"description": "Kostenübernahmeerklärung",
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
			"description": "Weiterleitung persönlicher Daten and andere Mit-/Weiterversorger",
			"content": {
				"Ich erteile durch meine Unterschrift mein - im Einzelfall widerrufliches - Einverständnis, dass alle für den Mit-/Weiterversorger meines Hilfsmittels notwendigen personenbezogenen Daten an diesen weitergeleitet werden dürfen.": []
			}
		}
	],
	[
		{
			"type": "textblock",
			"description": "Schweigepflichtentbindung gegenüber dem Kostenträger",
			"content": "Um für Ihr Hilfsmittel die Kostenzusage Ihres Kostenträgers zu erhalten, übermitteln wir diesem einen Kostenvoranschlag und eine Stellungnahme mit weiterführenden Informationen (u. a. Name, Versicherungsnummer, Diagnose, Angaben zur Erforderlichkeit). Die Stellungnahme wird entweder gleich der Beantragung beigelegt oder aber auf Anfrage seitens Ihres Kostenträgers zugeschickt. Hierzu ist Ihre Einwilligung und Schweigepflichtentbindung erforderlich. Die Einwilligung ist freiwillig, Vorgangsbezogen und für zukünftige Übermittlungen widerruflich. Ohne Ihre Einwilligung ist mit einer zeitlichen Verzögerung bei der Bearbeitung Ihrer Krankenkasse zu rechnen. Möglicherweise kann es dadurch sogar zu einer Ablehnung der Hilfsmittelversorgung kommen."
		},
		{
			"type": "checkbox",
			"description": "Schweigepflichtentbindung",
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

## Kunststofffertigungsauftrag
```json
```
[Content](#content)

## Silikonfertigungsauftrag
```json
```
[Content](#content)

## Empfangsbestätigung
```json
[
	[
		{
			"type": "identify",
			"attributes": {
				"name": "Vorgang"
			}
		},
		{
			"type": "textblock",
			"description": "Empfangsbestätigung",
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
			"description": "Abgabe",
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
			"description": "Erklärung",
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
			"type": "identify",
			"attributes": {
				"name": "Vorgang"
			}
		},
		{
			"type": "textblock",
			"description": "Empfangsbestätigung",
			"content": "Hiermit erteile ich dem Universitätsklinikum Heidelberg die Erlaubnis zur Ausstellung bzw. der Veröffentlichung der von mir, bzw. meinem Kind gefertigten Foto- und / oder Videoaufnahmen. Diese Erklärung kann jederzeit ohne Angaben von Gründen für künftige Veröffentlichungen widerrufen werden. Dies hat keinen Einfluss auf die Versorgung. Mir ist bewusst, dass bereits erfolgte Veröffentlichungen nachträglich nicht zurückgenommen werden können. Insbesondere bei der Veröffentlichung im Internet entzieht sich die weitere Verbreitung durch Kopien dem Zugriff durch das Universitätsklinikum Heidelberg.\\n \\nDie von mir / meinem Kind gemachten Aufnahmen sollen zu Zwecken der Presse- und Öffentlichkeitsarbeit des Universitätsklinikums Heidelberg verwendet werden. Ich habe die diesbezüglichen Erläuterungen verstanden, und mir wurde ausreichend Gelegenheit gegeben, Fragen zu stellen."
		},
		{
			"type": "checkbox",
			"description": "Erlaubnis zur Veröffentlichung von Aufnahmen in",
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
			"description": "Onlineveröffentlichung",
			"content": {
				"Hiermit erteile ich dem Universitätsklinikum Heidelberg meine Einwilligung, die von mir / meinem Kind gemachten Aufnahmen im Rahmen der Presse- und Öffentlichkeitsarbeit des Universitätsklinikums insbesondere in Print- und Onlinemedien zu verbreiten und zu veröffentlichen. Meine Einwilligung umfasst auch das Recht, die Aufnahmen zu bearbeiten und zu retuschieren, soweit dadurch keine Persönlichkeitsrechte verletzt werden.": []
			}
		},
		{
			"type": "checkbox",
			"description": "Gesicht",
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

## Anamnese Prothetik II
```json
```
[Content](#content)

## Maßblatt Prothetik II
```json
```
[Content](#content)

## Versorgungsplanung Prothetik II
```json
```
[Content](#content)

## Versorgungsdokumentation Prothetik II
```json
```
[Content](#content)

## Gebrauchsanweisung Prothetik II
```json
```
[Content](#content)


# Content
### Common
* [Basisdaten](#basisdaten)
* [Datenverarbeitung, Auftragserteilung, Schweigepflichtentbindung](#datenverarbeitung-auftragserteilung-schweigepflichtentbindung)
* [Kunststofffertigungsauftrag](#kunststofffertigungsauftrag)
* [Silikonfertigungsauftrag](#silikonfertigungsauftrag)
* [Empfangsbestätigung](#empfangsbestätigung)

### Prosthetics 2
* [Anamnese Prothetik II](#anamnese-prothetik-ii)
* [Maßblatt Prothetik II](#maßblatt-prothetik-ii)
* [Versorgungsplanung Prothetik II](#versorgungsplanung-prothetik-ii)
* [Versorgungsdokumentation Prothetik II](#versorgungsdokumentation-prothetik-ii)
* [Gebrauchsanweisung Prothetik II](#gebrauchsanweisung-prothetik-ii)

## Basisdaten
```json
{
	[
		{
			"type": "scanner",
			"attributes": {
				"name": "Vorgang"
			},
		},
		{
			"type": "text",
			"attributes": {
				"name": "Name",
				"required": true
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
				"name": "Telefonnummer",
			}
		},
		{
			"type": "email",
			"attributes": {
				"name": "eMailadresse",
			}
		},
		{
			"type": "text",
			"attributes": {
				"name": "Kostenträger",
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
			"type": "textarea",
			"attributes": { 
				"name": "Verordner"
			},
			"hint": "namentlich"
		}
	]
}
```
[Content](#content)

## Datenverarbeitung, Auftragserteilung, Schweigepflichtentbindung
Export for signature compliance
```json
{
	[
		{
			"type": "textblock",
			"description": "Datenverarbeitung durch und Auftragserteilung an"
			"content": "Universitätsklinikum Heidelberg, Zentrum für Orthopädie, Unfallchirurgie und Paraplegiologie\\nAbteilung Technische Orthopädie, Schlierbacher Landstraße 200a, 69118 Heidelberg"
		},
		{
			"type": "textblock",
			"description": "Information zur Datenverarbeitung"
			"content": "Die Bereitstellung und Verarbeitung Ihrer Daten ist im Rahmen Ihrer Versorgung in unserem Haus erforderlich und Bestandteil des Behandlungsvertrags. Bei Bedarf stellen wir Ihnen diese Informationen auch in einem größeren Ausdruck zur Verfügung. Allgemein gilt die Erklärung zur Datenverarbeitung und Datenschutz des Universitätsklinikums Heidelberg.\\nVerarbeitungszwecke im Rahmen Ihrer Hilfsmittelversorgung: Hilfsmittelversorgung, Patientenverwaltung und Abrechnung, interdisziplinäre Konferenzen zur Analyse und Erörterung von Diagnostik und Therapie, Versorgungsdokumentation, Erstellung von Berichten und Stellungnahmen, Qualitätssicherung in Versorgung und ihrer Organisation, Unterrichtung von Mit-/Weiterversorgern im erforderlichen Umfang, Eingabe der Adressdaten und ggf. Rufnummer an Versanddienstleister, Kontaktaufnahme auch hinsichtlich eines Erinnerungsservices nach Versorgungsabschluss."
		},
		{
			"type": "textblock",
			"description": "Auftragserteilung"
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
			"attributes": {
				"name": "Kostenübernahmeerklärung"
			},
			"content": {
				"Eine Kostenübernahme kann ich persönlich nicht erteilen und möchte die Kostenübernahme durch meinen Kostenträger (gilt auch für postOP-Versorgungen).": [],
				"Ich habe mich nach eingehender Beratung auf eigenen Wunsch für die Versorgung mit einem aufzahlungsfreien Produkt entschieden.": [],
				"Ich wünsche für mich die medizinische Leistung sofort. Deshalb erteile ich den Auftrag mich, unabhängig von einer Kostenzusage meines Kostenträgers, sofort zu versorgen. Sollte meine Krankenkasse die Bezahlung ganz oder teilweise nicht übernehmen, werde ich die Kosten in der Höhe des Kostenvoranschlages an meinen Kostenträger selbst tragen und bezahlen.": [],
				"Ich habe mich nach eingehender Beratung und Auswahl verschiedener Leistungen auf eigenen Wunsch für eine abweichende Versorgungsalternative mit Aufzahlung entschieden und bestätige hiermit die Kosten sowie etwaige Folgekosten, welche durch die höherwertige Leistung ausgelöst werden, selbst zu tragen.": []
			}
		}
	]
}
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


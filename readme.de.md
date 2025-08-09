![CARO logo](media/favicon/windows11/SmallTile.scale-100.png)
# CARO - Cloud Assisted Records and Operations

## Übersicht
* [Ziele](#ziele)
    * [Wesentliche Aspekte](#wesentliche-aspekte)
    * [Erforderliche Infrastruktur](#erforderliche-infrastruktur)
    * [Was es nicht ist](#was-es-nicht-ist)
    * [Extras](#extras)
    * [Datenintegrität](#datenintegrität)
    * [Tips](#tips)
* [Module](#module)
    * [Anwendung](#anwendung)
        * [Nutzer](#nutzer)
        * [Anleitung](#Anleitung)
    * [Kommunikation](#kommunikation)
        * [Unterhaltungen](#unterhaltungen)
        * [Ankündigungen](#ankündigungen)
        * [Mitarbeiterverzeichnis](#mitarbeiterverzeichnis)
        * [Textvorschläge](#textvorschläge)
        * [Verantwortlichkeiten](#verantwortlichkeiten)
        * [Verbesserungsvorschläge](#verbesserungsvorschläge)
    * [Aufzeichnungen](#aufzeichnungen)
        * [Dokumente](#dokumente)
        * [Aufzeichnungen](#aufzeichnungen-1)
        * [Risikomanagement](#risikomanagement)
        * [Audit](#audit)
        * [Managementbericht](#managementbericht)
    * [Kalender](#kalender)
        * [Terminvereinbarung](#terminvereinbarung)
        * [Kalender](#kalender-1)
        * [Langzeitplanung](#langzeitplanung)
        * [Zeiterfassung](#zeiterfassung)
    * [Dateien](#dateien)
    * [Einkauf](#einkauf)
        * [Lieferanten- und Artikelverwaltung](#lieferanten--und-artikelverwaltung)
        * [Bestellung](#bestellung)
    * [Werkzeuge](#werkzeuge)
    * [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen)
    * [Wartung](#wartung)
* [In der Anwendung verteilte Integrationen](#in-der-anwendung-verteilte-integrationen)
    * [Erinnerungen und automatische Aufgabenplanung](#erinnerungen-und-automatische-aufgabenplanung)
    * [Schulungen](#schulungen)
    * [Suche](#suche)
    * [Markdown](#markdown)
    * [CSV Prozessor](#csv-prozessor)
* [Löschung von Aufzeichnungen](#löschung-von-aufzeichnungen)
* [Vorgesehene regulatorische Zielsetzungen](#vorgesehene-regulatorische-zielsetzungen)
* [Voraussetzungen](#voraussetzungen)
    * [Installation](#installation)
    * [Laufzeitvariablen](#laufzeitvariablen)
    * [Anmerkungen und Hinweise zur Nutzung](#anmerkungen-und-hinweise-zur-nutzung)
    * [Bekannte Schwachstellen](#bekannte-schwachstellen)
    * [Anpassung](#anpassung)
    * [Erwägungen zur Nutzerakzeptanz](#erwägungen-zur-nutzerakzeptanz)
    * [Importierung von Lieferantenpreislisten](#importierung-von-lieferantenpreislisten)
* [Regulatorische Anforderungen an die Software](#regulatorische-anforderungen-an-die-software)
    * [Empfehlungen zur Nutzerunterweisung](#empfehlungen-zur-nutzerunterweisung)
    * [Risikoanalyse](#risikoanalyse)
    * [Erklärung zur Barrierefreiheit](#erklärung-zur-barrierefreiheit)
* [Code Design Vorlagen](#code-design-vorlagen)
* [API Dokumentation](#api-dokumentation)
* [Stellungnahme zu technischen Richtlinien zur Datensicherheit](#stellungnahme-zu-technischen-richtlinien-zur-datensicherheit)
    * [Nutzungsrichtlinien für die Nutzung der Anwendung](#nutzungsrichtlinien-für-die-nutzung-der-anwendung)
* [Bibliotheken](#bibliotheken)
* [Lizenz](#lizenz)

Die aktuellste Dokumentation ist verfügbar auf [https://github.com/erroronline1/caro](https://github.com/erroronline1/caro)

# Ziele
Diese Anwendung möchte bei der Umsetzung eines Qualitätsmanagements nach ISO 13485 und der internen Kommunikation unterstützen. Sie wird als Web-Anwendung auf einem Server verwendet. Datensicherheitsmaßnahmen sind auf die Nutzung innerhalb eines geschlossenen Netzwerks ausgelegt. Die Ausgestaltung ermöglicht es der Belegschaft Daten abzurufen und bereitzustellen wo andere Branchensoftware aufgrund ihrer Lizenzmodelle nur eingeschränkt verwendet werden kann.

Datenerfassung soll dabei weitestgehend digital erfolgen und letztendlich papierbasierte Dokumentationen ablösen. Es mag andere Anwendungen mit dem gleichen Ziel geben, diese verfolgen jedoch einen anderen Grundgedanken - die Verwaltung von Reha-Hilfsmitteln, einem Fokus auf orthopädische Schuhversorgungen oder einer primären Produktivitätsüberwachung - anstelle des primären Dokumentationsgedankens der CARO App für Hilfsmittel als Sonderanfertigungen in kleinen und mittelständigen Unternehmen. Ganz zu schweigen von unübersichtlichen Nutzeroberflächen, die ebenfalls das Ziel einer leicht verständlichen und einfachen Oberfläche steckten.

## Wesentliche Aspekte
* Transparenz: direkter Zugriff auf Daten
* Dokumentenverwaltung: volle Versionskontrolle, wiederverwendbare Komponenten, Zugriff auf frühere Versionen
* einfache Formularerstellung: Formulare einfach zusammenstellen und nahtlose Integration in die Anwendung
* Risikomanagment: mit Abgleich zwischen Risiken und Eigenschaften von Medizinprodukten
* Schulungs- und Qualifikationsverwaltung: Planung, schnelle Übersicht mit Beurteilung und hervorgehobenen Ablaufdaten
* Aufgaben- und Abwesenheitsplaner: Arbeiten planen mit gleichzeitiger Übersicht über Abwesenheiten
* strukturierte Beschaffung: reduzierte Rückfragen, automatisierte Erinnerungen, Einführungsaufzeichnungen und Stichprobenprüfung
* Rollenverwaltung: definierte Nutzerberechtigungen und eine angemessene Informationsfülle
* Auditunterstützung: interne Audits vorbereiten, planen und durchführen, Zusammenfassungen der Anwendungsdaten in aufgeräumten Übersichten
* Verbesserungsvorschläge: jeder kann Verbesserungsvorschläge äußern und bewerten, sowie die Maßnahmen einsehen
* Automatische Planungen, Erinnerungen und Benachrichtigungen
* Geräteunabhängig: Web-Anwendung mit jedem geeigneten Gerät nutzbar
* keine künstliche Intelligenz: volle Datenhoheit behalten

![dashboard screenshot](http://toh.erroronline.one/caro/dashboard%20de.png)

## Erforderliche Infrastruktur 
Es wird ein Server zur Speicherung und Ausführung der Web-Anwendung, sowie Netzwerkzugriff für alle Endgeräte benötigt. Die Anwendung ist dafür ausgelegt primär auf mobilen Geräten wie beispielsweise Android-Tablets oder iPads genutzt zu werden, kann aber auch auf Desktop-Computern genutzt werden. Manche Funktionen wie die Erstellung von Dokumenten, Textvorschlägen und Auditvorlagen sind primär nur auf Desktop-Computern mit Maus-Eingabegeräten möglich.

Das oberste Ziel ist die Ausstattung der gesamten Belegschaft oder zumindest von Schlüsselpositionen und Arbeitsplätzen mit mobilen Geräten. Schließlich kann die Administration nicht nach Digitalisierung verlangen ohne eine geeignete Infrastruktur zur Verfügung zu stellen. Scanner sind optional, da Scannen ebensogut mit in den Geräten eingebauten Kameras umgesetzt werden kann.

Für technische Details siehe [Voraussetzungen](#voraussetzungen).

## Was es nicht ist
Abgesehen von einigen architektonischen Entscheidungen zur Erfüllung regulatorischer Anforderungen ist die Anwendung kein vollständig voreingestelltes Qualitätsmanagementsystem. Prozesse, Dokumente und Verantwortlichkeiten sind selbst zu bestimmen. Die Anwendung soll lediglich dabei unterstützen strukturierte Abläufe und eine halbautomatisierte Erfüllung regulatorischer Anforderungen sicherzustellen. *Berechtigungen in den Flussdiagrammen stellen unverbindliche Standardeinstellungen dar.*

Die Anwendung ersetzt kein ERP-System. Daten für den Einkauf sind nur innerhalb der Oberfläche auf Basis der eigenen Datenbank möglich. Diese bewusste Entscheidung richtet sich gegen aufgeblähte Artikelstammdaten von ERP-Systemen derer kaum Herr zu werden möglich ist und die stets eine geschlossene Benutzeroberfläche erfordern. Die Produktdatenbank kann durch Preislisten der Lieferanten bestückt und dabei von vorneherein regelmäßig um unwichtige Daten bereinigt werden.

Bestellungen können von berechtigen Nutzern und Mitgliedern der bestellenden Bereiche jederzeit und zudem nach Ablauf einer definierten Zeit nach Auslieferung gelöscht werden. Dieses Modul dient in erster Linie der internen Kommunikation und nicht einer dauerhaften Dokumentation.

## Extras
* Textempfehlungen
    * Standard- und anpassbare [Textvorschläge](#textvorschläge) können definiert werden um eine durchgängige Sprachregelung zu unterstützen.
* Dateiverteilung
    * Die Anwendung hat einen [Sharepoint](#dateien) für Dateien mit integriertem STL-Betrachter um vereinfacht Informationen austauschen zu können.
* CSV-Filterung
    * Die Anwendung ist in der Lage CSV-Dateien auf vielfältige Weise zu [filtern und zu verarbeiten](#csv-prozessor).
* Zeitzonen und Bundesländer
    * Die Anwendung kann unterschiedliche Zeitzonen und Bundesländer in Bezug auf Feiertage verarbeiten, je nach Standortverteilung.


[Übersicht](#übersicht)

## Datenintegrität
Aufzeichnungen speichern stets den Namen des übermittelnden Nutzers ab. Gruppen-Nutzer sind daher nicht empfohlen, jedoch mit eingeschränkten Berechtigungen möglich. Individuelle Nutzer sind indes vorgesehen. Berechtigte Nutzer können andere Nutzer anlegen, bearbeiten und löschen. Zur Vereinfachung wird ein 64 Byte Token erstellt. Dieser Token wird in einen QR-Code umgewandelt, welcher bei der Anmeldung gescannt werden kann. Dadurch muss sich kein Nutzername und Kennwort gemerkt werden und es kann auf die Eingabe mehrerer Felder bei der Anmeldung verzichtet werden. Dieser Vorgang ist daher schnell umsetzbar und erlaubt einen raschen Wechsel zwischen unterschiedlichen Anmeldungen bei eingeschränkter Verfügbarkeit von Endgeräten.

Formulardaten und Serveranfragen beinhalten teilweise IDs um spezifische Inhalte zu erreichen. Technisch gesehen ist es möglich diese Daten und Anfragen zu manipulieren. Dennoch wird dieses Vorgehen als angemessen bewertet, da Serververarbeitungen nicht in der Lage sind auf die ursprüngliche Intention zu schließen. Dies erscheint nicht weniger sicher als eine beabsichtige Falschangabe in einer beliebigen papierbasierten Dokumentation.

Die Übermittlung von Formulardaten fügt dem Datenpaket eine verschlüsselte Nutzeridentifikation hinzu, der Server verifiziert die Identität und Datenintegrität durch eine Prüfsumme.

Dokumente können ein digitales Unterschriftenfeld beinhalten. Dabei ist zu beachten, dass es sich hierbei mangels Zertifizierung nur um eine einfache elektronische Signatur (EES) gemäß eIDAS handelt. Ob das Verfahren innerhalb festgelegter Prozesse angemessen ist, ist eine persönliche Ermessenssache.

Zeitstempel sind nicht qualifiziert. Eine geringere Validität als handschriftliche oder gestempelte Datumsangaben auf Papierdokumenten kann jedoch aktuell nicht erkannt werden.

[Übersicht](#übersicht)

## Tips
* Ein Kalender-Element kann in die Überwachungs-Dokumente eingebunden werden um während der Bearbeitung direkt das nächste Fälligkeitdatum festzulegen.
* Die Option einer "Großväterregelung" in der Produkteinführung kann insbesondere beim Übergang von einem anderen Qualitätsmanagementsystem in die CARO App die Dinge vereinfachen. Es muss dabei aber sichergestellt sein, dass die Anforderungen zuvor wirklich erfüllt wurden. Sofern im Import-Filter abgefragt, wird basierend auf der letzten Bestellung im ERP-System eine Produkteinführung initiiert.
* Die Flussdiagramme dieser Beschreibung können als Darstellung innerhalb der Proszessbeschreibungen / Verfahrensanweisungen zur Darstellung genutzt werden, sofern die Abläufe durch die Anwendung festgelegt sind.

![sample document elements screenshot](http://toh.erroronline.one/caro/sample%20document%20elements%20de.png)

[Übersicht](#übersicht)

# Module

## Anwendung
![sample application menu](http://toh.erroronline.one/caro/application%20menu%20de.png)

### Nutzer
Die Anwendung stellt ein zugeordnetes Rollen-Management für registrierte Nutzer zur Verfügung. Der gesamte Inhalt ist nur für angemeldete Nutzer zugänglich. Nutzer können unterschiedliche Berechtigungen erhalten. Diese Berechtigungen steuern, welche Inhalte erreichbar sind oder welche Änderungen erlaubt sind. Die Grundlage basiert auf den für das Unternehmen anpassbaren [Laufzeitvariablen](#laufzeitvariablen). Die Beispiele stellen eine angemessene Einstellung dar, sind aber frei wählbar.

Manche Berechtigungen/Einschränkungen sind jedoch systemisch festgelegt:

Die Zeiterfassung ist nur erreichbar, wenn eine Wochenarbeitszeit für den Nutzer festgelegt ist - das gilt auch für den Anwendungsadministrator.

* Patienten
    * erhalten nur Zugriff auf Dokumente die mit entsprechendem Patienenzugang markiert sind für allgemeine Dokumente und die zugewiesener Bereiche
    * nutzbar für Assessments und Selbsteinschätzungen durch Patienten durch Nutzer mit entsprechender Patienten-Berechtigung je Bereich sofern zutreffend
* Mitarbeiter
    * können nur Bestellungen der eigenen zugewiesenen Bereiche einsehen
    * können nur die eigenen Arbeitszeitdokumentationen exportieren
    * können Stichprobenprüfungen und Produkteinführungen durchführen
* Gruppen
    * können mangels persönlicher Identifizierbarkeit **nicht** zu Aufzeichnungen beitragen
    * können Bestellungen durchführen, müssen jedoch einen Namen angeben
    * können nur Bestellungen der eigenen zugewiesenen Bereiche einsehen
    * können mangels persönlicher Identifizierbarkeit **keine** Stichprobenprüfung und Produkteinführung durchführen
    * haben **keinen** Zugriff auf Arbeitszeitdokumentationen
* Bereichsleiter
    * können alle Arbeitszeitdokumentationen der Mitarbeiter zugewiesener Bereiche exportieren
    * können geplante Kalenderereignisse zugewiesener Bereiche und Arbeitszeiteinträge der Mitarbeiter zugewiesener Bereiche anlegen, ändern und abschließen
* Anwendungsadministratoren
    * haben **vollen Zugriff** und **alle Rechte**
    * können als jede berechtige Nutzergruppe Freigaben erteilen
    * können alle Arbeitszeitdokumentationen exportieren
    * die bei der Installation angelegte Systemnutzerin CARO App hat diese Berechtigung und kann genutzt werden um weitere Nutzer anzulegen.
    * diese Berechtigung sollte idealerweise nur wenigen vertrauenswürdigen Mitarbeitern der Leitungsebene erteilt werden

Nutzer können mehrere unterschiedliche Berechtigungen erhalten und mehreren Bereichen zugeordnet werden.

Bei der Registrierung eines neuen Nutzers wird ein Standard-Profilbild erstellt. Individuelle Profilbilder können mit diesem Bild wieder ersetzt werden. Eine automatisch generierte PIN kann als Berechtigung für die Freigabe von Bestellungen verwendet werden. Das Hinzufügen von Schulungen ist nur für berechtigte Nutzer möglich um sicherzustellen, dass Schulungen bekannt und nicht übersehen werden. Fähigkeiten können gemäß der [geplanten Liste](#anpassung) angepasst werden. Der erstellte Anmeldung-Token kann exportiert und beispielsweise als laminierte Karte verwendet werden.

Nutzernamen können aus gesellschaftlichen Gründen geändert werden. Dies betrifft jedoch nicht in Aufzeichnungen gespeicherte Namen, da diese nicht veknüpft, sondern als Text gespeichert werden um einen Informationsverlust zu vermeiden, sobald ein Nutzer gelöscht wird. Das Profilbild wird im Falle einer Namensänderung stets mit dem Standard-Profilbild überschrieben.

> In seltenen Fällen kann der QR-Code nicht vom eingebauten Scanner gelesen werden. Es wird empfohlen die Kompatibilität mit dem eingebauten Scanner der [Werkzeuge](#werkzeuge) zu prüfen, bevor der Code weitergegeben wird und bei Bedarf einen neuen Zugangstoken generieren zu lassen.

![user screenshot](http://toh.erroronline.one/caro/user%20de.png)

```mermaid
graph TD;
    application((Anwendung))-->login[Anmeldung];
    login-->scan_code;
    scan_code{Code scannen}-->user_db[(Nutzerdatenbank)];
    user_db-->|gefunden|permission{Berechtigung};
    user_db-->|nicht gefunden|login;

    permission-..->manage_users((Nutzerverwaltung));
    manage_users-..->new_user[neuer Nutzer];
    manage_users-.->edit_user[Nutzer bearbeiten];
    new_user-.->user_settings["Namen, Berechtigungen,
    Bereiche, Profilbild, Bestellberechtigungs-PIN,
    Fähigkeiten, Schulungen, Anmelde-Token, Dokumente,
    Arbeitszeiten, Urlaubstage bearbeiten"];
    edit_user-.->user_settings;
    edit_user-.->export_token[Token exportieren];
    user_settings-.->user_db;
    edit_user-.->delete_user[Nutzer löschen];
    delete_user-.->user_db;

    permission==>own_profile((eigenes Profil));
    own_profile==>profile["Informationen einsehen,
    Profilbild und Anwendungeinstellungen anpassen"];
    profile==>user_db;

    permission-->|PIN vorhanden|orders(("Bestellungen
    freigeben"));
    permission-->|Berechtigungen|authorized(("Inhalte gemäß
    Berechtigung einsehen"))
    permission-->|Bereiche|units(("Inhalte gemäß
    Bereichen einsehen"))
```

Nutzer können im Sinne der Transparenz alle persönlichen Informationen in ihrem Profil einsehen. Eine Änderung des Profilbilds und individuelle Anwendungeinstellungen können an dieser Stelle ebenfalls vorgenommen werden.

![user screenshot](http://toh.erroronline.one/caro/profile%20de.png)

[Übersicht](#übersicht)

### Anleitung
Die Anleitung kann gemäß technischem Verständnis und sprachlicher Gepflogenheiten individuell angepasst werden. Einzelne Abschnitte können dabei entsprechend der Berechtigungen markiert werden um diese zugunsten einer vereinfachten Übersicht auf der Startseite für alle anderen auszublenden. Bei der Installation wird eine Standard-Anleitung in der [voreingestellten Systemsprache](#laufzeitvariablen) angelegt. Die Anleitung und die Bearbeitungsschaltfläche (für berechtigte Nutzer) wird in dem Bereich *Über CARO App* angezeigt.

## Kommunikation
![sample communication menu](http://toh.erroronline.one/caro/communication%20menu%20de.png)

### Unterhaltungen
Systeminterne Nachrichten dienen ausschließlich der internen Kommunikation und haben keinen Aufzeichnungscharakter. Nachrichten werden als Unterhaltungen mit dem jeweiligen Gesprächspartner gruppiert. Dabei kann abgesehen von der Systemnutzerin und sich selbst jeder andere angeschrieben und die Unterhaltungen jederzeit gelöscht werden. Mehrere Adressaten können durch Komma oder Semikolon getrennt angesprochen werden. Ein Druck oder Klick auf das Profilbild einer Nachricht erlaubt eine Weiterleitung an andere Mitarbeiter. Neue Nachrichten lösen eine Systembenachrichtigung aus. Die Anwendung sendet im Bedarfsfall auch Nachrichten an Nutzergruppen.

![conversation screenshot](http://toh.erroronline.one/caro/conversation%20de.png)

[Übersicht](#übersicht)

### Ankündigungen
Berechtigte Nutzer können Ankündigungen für jedermann anlegen und bearbeiten um die Unterehmenskommunikation zu verbessern. Ankündigungen werden auf der Startseite angezeigt falls
* die Bereiche -sofern gewählt - übereinstimmen und
* das aktuelle Datum innerhalb der gewählten Zeitspanne liegt oder
* keine Zeitspanne gesetzt wurde

Alle Nutzer können alle Ankündigen unter dem entsprechenden Menüpunkt einsehen wobei die betroffenen Bereiche ausgewiesen sind. Ankündigungen sind keine dauerhaften Aufzeichnungen und können jederzeit bearbeitet und gelöscht werden. Die letzten Berabeitung und der entsprechende Nutzer werden angezeigt.

![announcements screenshot](http://toh.erroronline.one/caro/announcements%20de.png)

[Übersicht](#übersicht)

### Mitarbeiterverzeichnis
Das Mitarbeiterverzeichnis stellt eine Übersicht über die registrierten Nutzer dar, gruppiert nach Bereichen und Berechtigungen. Nutzern und ganzen Bereichen und Berechtigungsgruppen können direkt von dort aus Nachrichten zugesandt werden.

[Übersicht](#übersicht)

### Textvorschläge
Um unnötige und wiederholte Poesie zu vermeiden und einen durchgängigen Wortlaut zu unterstützen können Textvorschläge zur Verfügung gestellt werden. Diese werden aus vorbereiteten Textbausteinen zusammengesetzt, die entweder Pronomina oder allgemeine Abschnitte handhaben. Letztere können erstere verwenden. Ersatzbausteine werden in folgender Form definiert
* weibliche Kindform - das Mädchen
* männliche Kindform - der Junge
* neutrale Kindform - das Kind
* weibliche Erwachsenenform - die Frau
* männliche Erwachsenenform - der Mann
* neutrale Erwachsenenform - die Person
* persönliche Ansprache - Du
* förmliche Ansprache - Sie

Eine solche Ersetzung könnte beispielsweise *Adressat* genannt werden. Sobald ein allgemeiner Textbaustein :Adressat enthält wird dieser Platzhalter durch den aus einer Liste gewählten Genus ersetzt. Wird beispielsweise ein Text an den Kostenträger verfasst kann von einem Patienten gesprochen werden und die geeignete Wahl aus den ersten sechs Optionen getroffen werden; bei einer persönliche Ansprache eine Wahl aus den letzten Beiden, abhängig vom jeweiligen Grad des Vertrauensverhältnisses. Die Auswahl des passenden Genus wird automatisch im Fomular angezeigt und sobald eine Wahl des Genus erfolgt, werden alle weiteren Bausteine entsprechend ersetzt.

Bei der Erstellung eines Textes können die jeweils passenden grammatikalische Fälle vorbereitet verden (z.B. *:AdressatNominativ, *:AdressatAkkusativ, :AdressatDativ, etc.). Undefinierte Platzhalter erzeugen im Formular ein Eingabefeld, welches im weiteren Verlauf wiederverwendet wird:

*"Wir berichten über :AdressatAkkusativ :Name. Wir möchten zusammenfassen, dass die Versorgung von :Name voranschreitet und :AdressatNominativ die Nutzung gut umsetzen kann."*

erzeugt *"Wir berichten über **die Patientin Gladys**. Wir möchten zusammenfassen, dass die Versorgung von **Gladys** voranschreitet und **die Patientin** die Nutzung gut umsetzen kann."*

Die [Lieferantenverwaltung](#lieferanten--und-artikelverwaltung) nutzt systemseitig vordefinierte Platzhalter. Textvorschläge die beispielsweise :CUSTOMERID, :PRODUCTS oder :EXPIREDDOCUMENTS nutzen können für diese vorbereitete Werte importieren.
 
Bei der Erstellung von Textvorschlägen können die Textbausteine individuell sortiert und zu Absätzen zusammengestellt werden. Dazu werden die jeweiligen Abschnitte mit der [Maus an die gewünschte Position gezogen](#verschiedenes). Bei der Nutzung von Textvorschlägen können die vorbereiteten Textbausteine und Abschnitte abgewählt werden um den Text an den Bedarf anzupassen. Eine Gruppierung nach Abschnitten erleichtert die visuelle Darstellung und Wiedererkennung des Formulars.

Der erzeugte Text wird durch Druck oder Klick auf das Ausgabefeld in die Zwischenablage eingefügt.

![text recommendation screenshot](http://toh.erroronline.one/caro/text%20recommendation%20de.png)

```mermaid
graph TD;
    textrecommendation(("Textvorschlag"))==>select[Vorlage auswählen];
    select===>chunks[("Datenbank mit
    Bausteinen und Vorlagen")];
    chunks==>|"Wiedergabe der neuesten
    Vorlage nach Name"|display["Darstellung des
    Textvorschlags und
    der Eingabefelder"];
    display==>|Eingabe und Aktualisierung|render(vorbereiteter Text);

    managechunks(("Textabschnitte
    verwalten"))--->select2["Wahl des neuesten
    nach Name oder Neuanlage"];
    managechunks-->select3["Beliebige Auswahl
    oder Neuanlage"];
    select2-->chunks;
    select3--->chunks;
    chunks--->editchunk[Abschnitt bearbeiten];
    editchunk-->type{Typ};
    type--->|"Ersatz hinzufügen"|chunks;
    type-->|"Text hinzufügen"|chunks;
    
    managetemplate(("Textvorlagen
    bearbeiten"))-..->select4["Wahl des neuesten
    nach Name oder Neuanlage"];
    managetemplate-.->select5["Beliebige Auswahl
    oder Neuanlage"];
    select4-.->chunks;
    select5-..->chunks;
    chunks-...->edittemplate[Vorlage bearbeiten];
    edittemplate-.->|Vorlage hinzufügen|chunks;
```

[Übersicht](#übersicht)

### Verantwortlichkeiten
Berechtigte Nutzer können Verantwortlichkeiten für Aufgaben erstellen und Nutzer und deren Stellvertreter zuordnen. Zugeordnete Nutzer erhalten eine Nachricht und Menü-Benachrichtigung um die Zuordnung zu prüfen und die Verantwortlichkeit zu akzeptieren. Verantwortlichkeiten haben eine verpflichtende Dauer, nach deren Ablauf ein Kalendereintrag die Leitung daran erinnert die Abgelaufenen Einträge zu erneuern. Verantwortlichkeiten können jederzeit geändert werden, aus Transparenzgründen müssen die Nutzer ihre Akzeptanz jedoch erneut bestätigen. Verantwortlichkeiten sind keine Aufzeichnungen, da sie primär der operativen Information dienen. Die Übersicht kann nach Bereichen gefiltert werden oder auch nur die eigenen Verantwortlichkeiten darstellen.

![responsibilities screenshot](http://toh.erroronline.one/caro/responsibility%20de.png)

[Übersicht](#übersicht)

### Verbesserungsvorschläge
Jeder Nutzer kann öffentlich Verbesserungsvorschläge zu beliebigen Themen einreichen, auch Prozessen. Selbst anonyme Einreichungen sind möglich. Bei neuen Vorschlägen wird eine Systemnachricht an alle Nutzer versandt. Nutzer können ihre Zustimmung zu den Themen ausdrücken. Berechtigte Nutzer können Maßnahmen beschreiben oder Bewerten und die Themen abschließen. Der Vorschlagende wird per Nachricht darüber informiert, dass sein Anliegen bearbeitet wurde.

Um nicht zielführende Daten und möglicherweise bösartigen Spam zu begegnen, können Vorschläge gelöscht werden, wenngleich dies Standardmäßig nicht empfohlen wird. Beiträge haben keinen Aufzeichnungscharakter, beabsichtigen aber Meinungsbilder einzusammeln und als Eingabe für Qualitätsverbesserungen und die Managementbewertung zu dienen.

![measure management screenshot](http://toh.erroronline.one/caro/measure%20management%20de.png)

[Übersicht](#übersicht)

## Aufzeichnungen
![sample records menu](http://toh.erroronline.one/caro/records%20menu%20de.png)

### Dokumente
Andere Anwendungen behaupten eine Dokumentenverwaltung und Versionierung zu unterstützen. Tatsächlich importieren viele davon lediglich PDF-Dateien, die außerhalb erstellt werden müssen. Es ist (ohne größere Nachforschungen betrieben zu haben) nicht erkenntlich, wie die Dokumentenlenkung und Versionierung tatsächlich erfolgt. Die CARO App verfolgt einen vollkommen anderen Ansatz: die Dokumente und internen Dokumente sollen bevorzugt innerhalb der Anwendung selbst erzeugt werden. Dies dient dem Ziel einer papierlosen Lösung bereits ausreichend, dennoch sind zusätzlich Exporte als beschreibbare oder vorausgefüllte PDF-Dateien innerhalb festgelegter Grenzen möglich.

Nur nicht so. Bitte vorher Hilfe aufsuchen:

![cloud storage meme](http://toh.erroronline.one/caro/cloudstoragememe.jpg)

Dokumente sind modular. Um gelenkte und versionierte Dokumente anzulegen, müssen zunächst deren wiederverwendbare Komponenten erstellt werden, aus denen sich die Dokumente anschließend zusammensetzen lassen. Komponenten und Dokumente werden Bereichen zugeordnet. Dies verbessert die Übersicht im Editor und reduziert die Ablenkung während der regulären Nutzung da Nutzer die zur Auswahl stehenden Dokumente Bereichweise filtern können.

#### Komponentenbearbeitung
Verfügbare Elemente für Komponenten beziehungsweise Dokumente sind:
* Scannerfeld, optional als Mehrfachauswahl und Identifikator
* Textabschnitt für informative Zwecke ohne Eingabe, optional mit [Markdown](#markdown). Bei dem Export von Aufzeichnungen wird dem Inhalt `::MARKDOWN::`vorangestellt um den Inhalt entsprechend darzustellen. Theoretisch geschieht das auch wenn diese Zeichenketten manuell vorangestellt wird. Dieses möglicherweise unerwünschte Verhalten wird in der [Risikoanalyse](#risikoanalyse) berücksichtigt
* Bild um beispielsweise Informationgrafiken einzubinden
* einfaches Textfeld, optional als Mehrfachauswahl und mit Vorschlag vorheriger Eingaben
* mehrzeiliges Textfeld, optional mit Zugriff auf Textvorschläge und mit Vorschlag vorheriger Eingaben
* Nummernfeld, optional als Mehrfachauswahl. Steuert das Erscheinungbild der Tastatur auf mobilen Geräten
* Datumsfeld. Steuert das Erscheinungbild der Eingabeoptionen
* Telefonnummernfeld. Steuert das Erscheinungsbild der Tastatur auf mobilen Geräten
* eMail-Adressenfeld. Steuert das Erscheinungsbild der Testatur auf mobilen Geräten
* Verknüpfung-Eingabe. Umschließt automatisch den eingegebenen Wert mit der spezifischen Zeichenfolge *href='{WERT}'* um immerhalb der Anwendung als Verknüpfung angezeigt zu werden. Das funktioniert im Prinzip auch manuell bei anderen Eingabefeldern, sofern ein solcher Wert eingegeben wird. Dieses möglicherweise unerwünschte Verhalten wird in der [Risikoanalyse](#risikoanalyse) berücksichtigt
* Produktauswahlfeld, optional als Mehrfachauswahl. Hat Zugriff auf die Artikeldatenbank
* Schieberegler, Mindest-, Höchstwert und Schritte optional
* Verknüpfungen (Links)
* Mehrfachauswahl
* Einfachauswahl (Buttons)
* Einfachauswahl (Liste), optional als Mehrfachauswahl. Einträge erscheinen in der Reihenfolge wie angegeben. Sollte diese alphabetisch sein (Sonderzeichen, A-Z, a-z) wird die Liste ab 12 Einträgen nach Anfangsbuchstaben gruppiert.
* Datei-Upload, optional als Mehrfachauswahl
* Foto-Upload, optional als Mehrfachauswahl. Mobile Geräte greifen auf die Kamera zu, Desktop-Geräte öffnen eine Dateiauswahl
* Unterschriftenfeld
* Kalenderschaltfläche
* Dateiverweis-Auswahl, optional als Mehrfachauswahl. Hat Zugriff auf die bereitgestellten Dateien und übernimmt deren Speicherort, inclusive STL-Vorschau sofern anwendbar. Dateien könnten jedoch nicht dauerhaft verfügbar sein, daher ist dies nur für eine kurzfristige Kommunikation sinnvoll.
* Dokumentenverknüpfung, nur zur Ansicht oder als Weiterleitung mit Übernahme eines Identifikators
* horizontale Linie zur Dokumentenstrukturierung

Die meisten Eingabetypen können zusätzlich optional als erforderlich gekennzeichnet werden. *Mehrfachauswahl* bedeutet, dass ein weiters Eingabefeld nach der Eingabe erscheint. Bei Datei-Uploads erlaubt die Dateiauswahl das Markieren mehrerer Dateien. Nutzer mit [*Administrator*-Berechtigung](#nutzer) können Komponenten direkt als JSON-Notation importieren und exportieren. Theoretisch erlaubt dies Dokumente mit detaillierteren Eigenschaften, diese können jedoch nicht vollständig auf normalem Wege bearbeitet werden.  
Formularfelder die als Mehrfachauswahl gekennzeichnet sind erscheinen in Exporten nur bei gesetztem Wert. Der jeweilige Name wird um einen Zähler in Klammern erweitert.

> [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen) erlauben einen Export von Aufzeichnungsdaten. Dieser Export beinhaltet die jeweils neusten Daten der verschiedenen Fragestellungen innerhalb der Dokumente in einer entsprechenden Tabellenspalte. Es ist hilfreich und empfohlen Fragestellungen innerhalb der Komponenten und Dokumente nicht zu wiederholen. Wiederholungen schaden den Aufzeichnungen an sich nicht, beschränken aber die analytischen Optionen des Datenauszugs.

#### *Caveat:*
Einige Elemente können nur als normale Aufzeichnungen verarbeitet werden.
* Produktauswahlfelder,
* Unterschriftenfelder und
* Kalenderschaltflächen

sind nicht verwertbar für Dokumenten-Kontexts innerhalb der [Sprachdateigruppe](#anpassung) documentcontext.notdisplayedinrecords (Standard: MDR §14 Stichprobenprüfung, Produkteinführung, Schulungsbewertung und Lieferantenbewertung). Diese Eingabefelder werden ignoriert. Vorschläge vorheriger Eingaben sind hier nicht verfügbar.

Elemente können mit der [Maus sortiert und positioniert](#verschiedenes) werden. Die meisten Elemente können bearbeitet werden - ihr Inhalt kann in den entsprechenden Editor importiert werden um geändert oder ergänzt und anschießend wieder hinzugefügt zu werden. Das Original-Element verbleibt dabei und muss manuell entfernt werden.

Die jeweiligen Bearbeitungsmasken zeigen eine Auswahl der neuesten freigegebenen Elemente an, in einer zusätzlichen Auswahl kann aber jedes beliebige Element für die Bearbeitung gewählt werden.

siehe auch [Erwägungen zur Nutzerakzeptanz](#erwägungen-zur-nutzerakzeptanz)

#### Dokumentenbearbeitung
Dokument können durch die Auswahl beliebiger freigegebener Komponenten zusammengestellt werden. Komponenten können mit der [Maus sortiert und positioniert](#verschiedenes) werden.

Dokumente können alternative Suchbegriffe erhalten. Es muss ein Kontext gewählt werden um eine Plausibilitätsprüfung für die Verwendung gegebenenfalls erforderlicher Elemente durchführen zu können. Die Angabe eines regulatorischen Zusammenhangs wird empfohlen.

Dokumente können einen eingeschränkten Zugang erhalten um eine Verwendbarkeit nur durch berechtigte Nutzer zuzulassen. Auf diesem Weg sind Aufzeichnungen möglich, die nicht für die Öffentlichkeit bestimmt sind (z.B. Bewerbungs- oder Mitarbeitergespräche). Dokumente können auch als für Patienten zugänglich markiert werden. Nutzer mit `Patient`-Berechtigung können von der Starseite aus auf diese Dokumente zugreifen und mit Selbsteinschätzungen zu Aufzeichnungen beitragen.

Dokumente können als bearbeitbare PDF-Dateien für die hoffentlich seltene Fälle, in denen eine digitale Bearbeitung problematisch sein könnte, exportiert werden. In diesem Fall werden Foto- und Dateiuploads sowie Bedienfelder durch Hinweise ersetzt und Identifikatoren in der Kopfzeile implementiert. Dokumente können primär nur von berechtigten Nutzern exportiert werden um eine Verbreitung veralteter Versionsstände zu vermeiden und eine bessere Datensammlung innerhalb der Anwendung zu fördern. Ersteller der Dokumente können jedoch eine allgemeine Erlaubnis erteilen. Es wird empfohlen die Daten zu einem späteren Zeitpunkt nachzutragen oder als Foto oder Scan zum Vorgang beizufügen (sofern ein geeignetes Formularfeld bereitgestellt wird), wobei in diesem Fall die Durchsuchbarkeit und Übersicht leidet.

Die jeweiligen Bearbeitungsmasken zeigen eine Auswahl der neuesten freigegebenen Elemente an, in einer zusätzlichen Auswahl kann aber jedes beliebige Element für die Bearbeitung gewählt werden.

![document composer screenshot](http://toh.erroronline.one/caro/document%20manager%20de.png)

#### Dokumentenlenkung
Komponenten und Dokumente müssen von berechtigten Nutzern freigegeben werden bevor sie genutzt werden können. Eine Freigabe-Anfrage für Komponenten und Dokumente wird über den internen [Nachrichtendienst](#unterhaltungen) and die definierten Nutzergruppen versandt; sofern für die Freigabe definiert, Bereichsleiter des in der Bearbeitungsmaske festgelegten Bereichs. Die Freigabe erfolgt durch die Auswahl der zutreffenden Option in der berechtigten Rolle während der Anmeldung in der Anwendung. Alle Nutzer erhalten eine Mitteilung über aktualisierte Dokumente.

Die Versionierung findet über einen Zeitstempel statt.

Zusätzlich können Dokumentenpakete erstellt werden. Auf diese Weise kann jeder prüfen ob alle beabsichtigen Dokumente für den jeweiligen Anwendungsfall berücksichtigt wurden. Dokumentenpakete unterliegen keiner Freigabe oder Versionierung, können aber auch nicht gelöscht sondern nur verborgen werden.

```mermaid
graph TD;
    manage_components(("Komponenten
    verwalten"))===>select["Wahl der neuesten Komponente
    nach Name"];
    select==>documents_database[("Dokumentendatenbank
    mit Komponenten,
    Dokumenten, Paketen")];
    manage_components==>select2["beliebige Komponente"];
    select2==>documents_database;
    manage_components==>|"neue Komponenten"|edit_component["Inhalte bearbeiten,
    hinzufügen oder sortieren"];
    documents_database==>|"bestehende Komponente"|edit_component;
    edit_component==>save[speichern];
    save===>|"neue Version,
    Nachricht mit Freigabeaufforderung"|documents_database;

    manage_documents(("Dokumente
    verwalten"))--->select3["Wahl des neuesten Dokuments
    nach Name"];
    select3-->documents_database;
    manage_documents-->select4["beliebiges Dokument"];
    select4-->documents_database;
    manage_documents-->|neues Dokument|edit_document["Dokument bearbeiten,
    Komponenten hinzufügen oder sortieren"];
    documents_database-->|"bestehendes Dokument"|edit_document;
    edit_document-->save;
    save--->|"neue Version,
    Nachricht mit Freigabeaufforderung"|documents_database;
    
    manage_bundles(("Dokumentenpakete
    verwalten"))-..->select5["Wahl des neuesten Pakets
    nach Name"];
    select5-.->documents_database;
    manage_bundles-.->select6["beliebiges Paket"];
    select6-->documents_database;
    manage_bundles-.->|neues Paket|edit_bundle["Paket bearbeiten,
    Dokumente hinzufügen oder sortieren"];
    documents_database-.->|"bestehendes Paket"|edit_bundle;
    edit_bundle-..->save;
    save-..->|"neue Version,
    Nachricht mit Freigabeaufforderung"|documents_database;

    documents_database o----o returns("gibt auf Anfrage nur den neuesten Datensatz heraus,
    sofern dieser nicht verborgen,
    vollständig freigegeben ist und die Berechtigungen übereinstimmen")
```

Bildschirmformular

![screen document](http://toh.erroronline.one/caro/document%20screen%20de.png)

Exportiertes Dokument

![exported document](http://toh.erroronline.one/caro/document%20export%20de.png)

[Übersicht](#übersicht)

### Aufzeichnungen
Aufzeichnungen speichern alle Eingaben für jedes gewählte Dokument. Manche Dokumenten-Kontexte erfordern einen Identifikator, der alle Aufzeichnungen zu einer Zusammenfassung zusammenstellt. Zusammenfassungen können exportiert werden. Vollständige Zusammenfassungen enthalten alle Aufzeichnungen in chronologischer Reihenfolge, vereinfachte Zusammenfassungen nur den jeweils neuesten Eintrag. In diesem Fall sind die Zusammenfassungen zwar unvollständig, für eine Weitergabe an dritte jedoch zugunsten einer vereinfachten Darstellung aufgeräumter. Ein weiterer Anwendungsfall sind Gebrauchsanleitungen, deren Inhalt für die Dokumentation gespeichert, aber auch jederzeit für die Anwender inclusive Erläuterungen exportiert werden kann. PDF-Exporte beinhalten auch eingebettete Bilder und Dateianhänge sofern bereitgestellt.

Eine vollständig papierlose Lösung könnte für feuchte Umgebungen ungeeignet sein. Daher können einzelne Dokumente ebenfalls exportiert werden um die Daten in Situationen bereithalten zu können, in denen elektronische Geräte Schaden nehmen könnten.

Ein Identifikator ist immer ein QR-Code neben dem der Inhalt zusätzlich in lesbarer Form steht und der sich auch auf Exporten wiederfindet. Um den Arbeitsfluss zu verbessern können Aufkleberbögen erstellt werden, mit deren Hilfe zum Beispiel Produkte und exportierte Fomulare manuell gekennzeichnet werden können. Das Scannen des Codes reduziert eine Fehlerwahrscheinlichkeit bei der Zuordnung. Der Identifikator kann ebenfalls genutzt werden um Daten von anderen Aufzeichnungen zu importieren, beispielsweise bei der Übernahme von vergleichbaren Datensätzen anderer Versorgungsbereiche.

![sample identifier code](http://toh.erroronline.one/caro/sample%20identifier%20code.png)

Bei der Anzeige von Zusammenfassungen erscheinen Empfehlungen für die Vervollständigung von Aufzeichnungen gemäß der Dokumenten-Pakete, die sowohl mit den Bereichen des letzten eintragenden Nutzers als auch des aktuellen Nutzers übereinstimmen; dabei wird davon ausgegangen, dass Vorgägnge typischerweise von Mitgliedern eines Bereichs bearbeitet werden. Die Vollständigkeit kann jedoch auch gegen jedes andere Dokumenten-Paket geprüft werden.

Aufzeichnungen können als abgeschlossen markiert werden. Damit werden sie in der Übersicht und auf der Startseite nicht mehr angezeigt, sind aber mit der Filter-/Suchfunktion und dem entsprechenden Identifikator weiterhin erreichbar. Bei nachfolgenden Eingaben wird der Status als "abgeschlossen" wieder entzogen. Dies betrifft auch Aufzeichnungen die Reklamationen beinhalten. Reklamationen müssen von allen [definierten Rollen](#laufzeitvariablen) abgeschlossen werden, auch wiederholt, sofern zusätzliche Daten zu den Aufzeichnungen hinzugefügt werden.  
An nicht abgeschlossene Aufzeichnungen wird regelmäßig in [definierten Abständen](#laufzeitvariablen) erinnert. Dies erfolgt über eine Nachricht an alle Nutzer der Bereiche des letzten eintragenden Nutzers.

Versorgungsdokumentationen erlauben das Setzen des aktuellen Fallstatus (wie genehemigt, Fertigung beauftragt, etc.). Aufzeichnungen in der Übersicht können entsprechend gefiltert werden. Mitarbeiter, die den Status ändern, haben die Wahl andere Mitarbeiter, Versorgungsbereiche oder deren Bereichsleiter via Nachricht zu informieren. Es gibt auch die Möglichkeit eine Rückfrage zum Fall an einen beliebigen Nutzer zu senden, wobei eine direkte Verknüpfung für einen schnellen Zugriff beinhaltet ist.

Ist eine Aufzeichnung versehentlich als Reklamation markiert worden, können definierte Rollen der Aufzeichnungstyp ändern. Diese Änderung wird ebenfalls dokumentiert.  
Aufzeichnungen können einen neuen Identifikator erhalten, z.B. bei Schreibfehlern oder einer versehentlichen doppelten Anlage. Im zweiten Fall werden die Aufzeichnungen mit bestehenden zusammengeführt sobald der neue Identifikator bereits genutzt wird. Diese Änderung sowie die Neuvergabe eines Identifikators werden ebenfalls dokumentiert.

Falls Aufzeichnungen Daten aus eingeschränkt zugänglichen Dokumenten enthalten, werden diese Datensätze nur dann angezeigt, wenn der anfragende Nutzer auch die Berechtigung zur Verwendung der Dokumente hat. Es ist Ermessenssache ob Dokumentenpakete so sinnvoll eingesetzt werden können:
* Einerseits vereinfacht dies die Übersicht verfügbarer Dokumente und Informationen für manche Bereiche, indem beispielsweise administrative Inhalte gegenüber Mitarbeitern ausgeblendet werden,
* andererseits bedeutet dies mehr Aufmerksamkeit auf die vergebenen Rollen und wer im Falle von Anfragen tatsächlich vollständige Daten exportiert.

Alle Änderungen an Aufzeichnungen (Reidentifizierung, Aufzeichnungstyp, Fallstatus) werden ebenfalls aufgezeichnet.

Aufzeichnungen die außerhalb manuell angelegter Dokumente durch die Anwendung unterstützt werden (z.B. Fallstatus, s.o.) werden in der [Systemsprache](#laufzeitvariablen) gespeichert.

![screen record summary](http://toh.erroronline.one/caro/record%20screen%20de.png)

```mermaid
graph TD;
    identifiersheet(("Erstelle einen Aufkleberbogen
    mit Identifikatoren"));
    identifiersheet-->input[Eingabedaten];
    input-->|Absenden|print("Bogen ausdrucken und
    an Mitarbeiter aushändigen");

    filldocument((Dokument ausfüllen))=========>selectdocument[Dokument wählen];
    selectdocument==>document_db[("Dokument-
    datenbank")];
    document_db==>|"neuestes Fomular nach Name ausgeben
    sofern Berechtigungen übereinstimmen"|displaydocument[Dokument anzeigen];
    displaydocument==>inputdata[Dateneingabe];
    inputdata==>|"neuen Datensatz mit Dokumentenname speichern"|record_db[("Aufzeichnungs-
    datenbank")];

    displaydocument==>idimport[Import mit Identifikator];
    idimport==>record_db;
    record_db==>selectbyid[erhalte alle Datensätze mit Identifikator];
    selectbyid==>|füge neueste Datensätze ein|inputdata;
    displaydocument===>|Exportberechtigung|exportdocument[exportiere editierbares PDF]

    print o-.-o idimport;

    summaries(("Dokumentationen"))----->record_db;
    record_db-->displayids["Zeige Identifikatoren,
    nicht abgeschlossen oder
    Filter zutreffend"];
    displayids-->|Auswahl|summary["zeige Zusammenfassung an
    sofern Berechtigungen übereinstimmen"];
    summary-->close[abschließen];
    close-->complaint{Reklamation};
    complaint-->|ja|complaintclose[Verantwortliche Person, Bereichsleiter UND QMB];
    complaintclose-->record_db;
    complaint-->|nein|nocomplaintclose[Bereichsleiter oder Leitung];
    nocomplaintclose-->record_db;
    summary-->export[exportieren];
    export-->pdf("Zusammenfassung als PDF,
    angehängte Dateie");
    summary-->matchbundles[Abgleich mit Dokumenen-Paketen];
    matchbundles-->missing{fehlendes Dokument};
    missing==>|ja|appenddata[Dokument hinzufügen];
    appenddata==>displaydocument;
    missing-->|nein|nonemissing(Statusbenachrichtigung);
    summary-->retype[Aufzeichnungstyp ändern];
    retype-->record_db;
    summary-->record_state[Vorgangsstatus, Aufbewahrungsfrist];
    record_state-->record_db;

    record_db===>|abgeschlossen, Aufbewahrungsfrist abgelaufen|delete;

    notification((Benachrichtigungen))-....->record_db;
    record_db-..->|"nicht abgeschlossen,
    letzter Eintrag vor X Tagen"|message(Nachricht an Benutzer der Bereiche);
```

Exportierte vollständige Aufzeichnung

![exported full record](http://toh.erroronline.one/caro/record%20full%20summary%20de.png)

Exportierte reduzierte Aufzeichnung

![exported reduced summary](http://toh.erroronline.one/caro/record%20reduced%20summary%20de.png)

[Übersicht](#übersicht)

### Risikomanagement
Das Risikomanagement unterstützt bei der Beschreibung von Risiken gemäß ISO 14971 und richtet sich nach den Empfehlungen der [DGIHV](https://www.dgihv.org). Identifizierte Risiken die je Prozess Beachtung finden können, sind in den [Sprachdateien definiert](#anpassung) (siehe auch [hier](#laufzeitvariablen)).

Wie in der ISO 14971 gefordert können Eigenschaften von Medizinprodukten und dahingehend anwendbare Risiken beschrieben werden. Da die DGIHV erfreulicherweise die Definition von Eigenschaften und Risiken für Gruppen von Medizinprodukten (z.B. Prothesen und Orthesen im allgemeinen) als angemessen betrachtet, werden alle Bewertungen prozessweise zugeordnet.  
Ferner werden Ursache und Auswirkungen erfasst, die Eintrittswahrscheinlichkeit und Schadenshöhe bewertet, Maßnahmen beschrieben, die Wahrscheinlichkeit und der Schaden neubewertet, eine Risko-Nutzen-Bewertung durchgeführt und Restmaßnahmen beschrieben. Das Formular gibt eine Meldung aus, ob das Risko vor und nach der Maßnahme innerhalb des in der [config.ini](#laufzeitvariablen) festgelegten Akzeptanzbereichs liegt. Die Schwelle ist das Produkt aus Wahrscheinlichkeit x Schaden gemäß der jeweiligen Positionen in den Auflistungen der Sprachdateien für risk.probabilities und risk.damages. Diese Methode ist der praktischste Weg einer algorithmischen Verarbeitung und Hervorhebung des Akzeptanzbereichs.

Die Einträge können nicht gelöscht und durch das [Regulatorische Auswertungen und Zusammenfassungen-Modul](#regulatorische-auswertungen-und-zusammenfassungen) exportiert werden. Einträge speichern den Nutzernamen und das Datum der letzten Änderung. 

![risk screenshot](http://toh.erroronline.one/caro/risks%20de.png)

[Übersicht](#übersicht)

### Audit
Die Anwendung ermöglicht die Vorbereitung von internen Audits, inklusive der Ziele, der Auditmethode und eines Imports vorausgegangener Zusammenfassungen des gewählten Bereichs. Die Formulierung von Fragen anderer Vorlagen kann wiederverwendet und den Fragen der regulatorische Zusammenhang zugeordnet werden. Bei der Erstellung eines Auditprogramms können Fragen hinzugefügt, entfernt, [umsortiert](#verschiedenes) und reimportiert werden. Die Zusammenfassung des vorherigen Audits für den jeweiligen Bereich kann importiert werden um beim bevorstehenden Audit Bezug zu nehmen.  
Audits können direkt von diesem Formular aus auch zum Kalender hinzugefügt werden um die jeweiligen Bereiche zu informieren.

![audit template screenshot](http://toh.erroronline.one/caro/audit%20template%20de.png)

Die Durchführung eines Audits beginnt mit der Auswahl einer Vorlage. Antworten und Stellungnahme werden zunächst aus dem lezten abgeschlossenen Audit des gewählten Bereichs übernommen. Unterbrechungen, und Bearbeitungen laufender Audits sind jederzeit möglich solange das Audit nicht als abgeschlossen markiert ist. Danach ist eine Bearbeitung oder Löschung des Audits nicht mehr möglich und es wird zu einer systemseitigen Aufzeichnung. Bei Abschluss eines Audits wird der Auditbericht über eine [Systemnachricht](#unterhaltungen) an alle Nutzer mit [`regulatory`-Berechtigung](#laufzeitvariablen) und alle Mitglieder des auditierten Bereichs umgesetzt. Abgeschlossene Audits können im [Regulatorische Auswertungen und Zusammenfassungen-Modul](#regulatorische-auswertungen-und-zusammenfassungen) eingesehen werden.

![audit screenshot](http://toh.erroronline.one/caro/audit%20de.png)

[Übersicht](#übersicht)

### Managementbericht
Ähnlich wie für die Audits kann auch eine Managementbericht erstellt, gespeichert und später bearbeitet sowie durch das Abschließen zu einer systemseitigen Aufzeichnung umwandeln. Ein neuer Bericht startet als Basis mit den Eingaben des letzten. Die Standard-Sprachdateien beinhalten alle erforderlichen Themen, damit keines vergessen wird. Derzeit sind die Bewertungen reinweg textbasiert, ohne Bilder, Anhänge oder Tabellen. Bei Abschluss eines Managementberichts wird ein Hinweis über eine [Systemnachricht](#unterhaltungen) an alle Nutzer mit [`regulatory`-Berechtigung](#laufzeitvariablen)umgesetzt. Abgeschlossene Managementberichts können im [Regulatorische Auswertungen und Zusammenfassungen-Modul](#regulatorische-auswertungen-und-zusammenfassungen) eingesehen werden.

Der Großteil der CARO ist datenorientiert, Managementberichte können jedoch auch dritte betreffen. Daher ist eine gewisse Layoutbearbeitung mittles [Markdown](#markdown)-Syntax möglich.  
Tabellen können von Markdown nach CSV und andersherum konvertiert werden um der Bequemlichkeit halber in einer Drittanwendung bearbeitet zu werden.

[Übersicht](#übersicht)

## Kalender
![sample calendar menu](http://toh.erroronline.one/caro/calendar%20menu%20de.png)

### Terminvereinbarung
Es kann eine Terminerinnerung für Kunden erstellt werden. Nach der Eingabe der relevanten Daten kann entweder eine ausgedruckte Version mit den Daten sowie einem QR-Code für den Import in einen Kalender auf einem Mobilgerät, oder direkt eine ICS-Datei für den Versand per eMail oder Nachrichtendienst verwendet werden.

![appointment screenshot](http://toh.erroronline.one/caro/appointment%20de.png)

[Übersicht](#übersicht)

### Kalender
Es können Einträge zum Kalender hinzugefügt werden. Die Startseite gibt eine kurze Zusammenfassung der geplanten Termine der aktuellen Kalenderwoche sowie eine Übersicht über Mitarbeiter außer Dienst, sofern in der [Zeiterfassung](#zeiterfassung) eingetragen. Termine können von jedem Nutzer angelegt und abgeschlossen werden, eine Änderung und Löschung ist jedoch nur für berechtigte Nutzer möglich.

Ereignisse können eine [Benachrichtigung](#unterhaltungen) an Nutzergruppen auslösen, wenn diese Einstellung vorgenommen wurde.

Da die Terminplanung primär die Arbeitsplanung (beispielweise die Festlegung von täglichen Aufgaben für einen Bereich) oder Erinnerungen in Zusammenhang mit Aufzeichnungen unterstützen soll, kann nur ein Datum und keine Zeit ausgewählt werden. Dies vereinfacht zudem die Eingabemaske.

Angezeigte Kalender stellen auch Wochenenden und sonstige arbeitsfreie Tage dar, um sicherzustellen, dass versehentlich an einem solchen Tag geplante Ereignisse nicht übersehen werden.

Die Planung von Terminen ist nicht Bestandteil der Aufzeichnungen, da jede Maßnahme ihre eigene [Aufzeichnung mit Zeitstempel](#aufzeichnungen) vorsieht. Ereignisse werden nach einer [einstellbaren Zeit](#laufzeitvariablen) nach Erledigung gelöscht sofern nicht anders angegeben.

![calendar screenshot](http://toh.erroronline.one/caro/calendar%20de.png)

```mermaid
graph TD;
    scheduling((Planung))===>select_day[Auswahl Tag];
    scheduling==>search[Suche];
    select_day==>database[("Kalenderdatenbank
    Typ Planung")];
    select_day==>add[hinzufügen];
    add==>database;
    search==>database;
    database==>matches("Ergebnisse zugewiesener
    Bereiche anzeigen");
    matches==>permission{"Administrator,
    Leitung, QMB,
    Bereichsleiter"};
    permission==>|ja|edit[ändern oder löschen];
    permission==>|nein|complete[abschließen];
    edit==>complete;
    database==>alert["Mitarbeiter ausgewählter Bereiche
    einmalig benachrichtigen"]

    timesheet((Zeiterfassung))-->select_day2[Auswahl Tag];
    select_day2-->add2[hinzufügen];
    add2-->usertype{Nutzertyp};
    usertype-->any["jeder:
    eigene Einträge"];
    usertype-->foreign["Personalverwaltung, Bereichsleiter:
    eigene und fremde Einträge"];
    any-->database2;
    foreign-->database2;
    select_day2-->database2[("Kalenderdatenbank
    Typ Zeiterfassung")];
    database2-->alert;
    database2-->entrytype{Eintragstyp};
    entrytype-->|regulärer Arbeitstag|usertype2{Nutzertyp};
    usertype2-->|betroffener Nutzer|display_edit("Anzeige,
    Änderung,
    Löschung,
    falls nicht abgeschlossen");
    usertype2-->|Bereichsleiter, Leitung, Administrator|display_close("Anzeige,
    abschließen,
    wiedereröffnen");
    entrytype-->|Dienstfrei|usertype3{Nutzertyp};
    usertype3-->usertype2;
    usertype3-->|Personalverwaltung|display_only(Anzeige)
    
    database2-->export[Export des gewählten Monats];
    export-->permission2{Berechtigung};
    permission2-->fullexport["Leitung, Personalverwaltung, Admin:
    vollständiger Export
    aller Zeiterfassungen"];
    permission2-->partexport["Bereichsleiter:
    Export aller Zeiterfassungen
    zugewiesener Bereiche"];
    permission2-->ownexport["Mitarbeiter:
    Export der eigenen Zeiterfassung"]

    landing_page((Startseite))-....->database;
    landing_page-......->database2;
    database-.->summary("Kalenderwoche,
    aktuell geplante Ereignisse,
    nicht abgeschlossene vergangene Ereignisse, Mitarbeiter dienstbefreit");
    database2-.->summary;
    summary-......->select_day
```

[Übersicht](#übersicht)

### Langzeitplanung
Der Kalender unterstützt eine Langzeitplanung, wie sie etwa für die Einteilung von Auszubildenden über die Bereiche hinweg genutzt werden kann. Hier können innerhalb festgelegter Zeiträume farbliche Markierungen genutzt werden um z.B. Zuordnungen darzustellen. Der Editor erlaubt dabei auch den Import von vorausgehenden Planungen in den neuen Zeitraum, sowie das Hinzufügen und Entfernen von Namen und Farbvorlagen. Dabei sind Namen nicht zwingend Personen zuzuordnen und Farben nicht notwendigerweise Bereichen - die Planung kann für beliebige Zwecke verwendet werden. Planungen sind von jedem Nutzer einsehbar sobald die Planung als abgeschlossen markiert wurde, editierbar jedoch nur von Nutzern mit entsprechender Berechtigung. Die Planungen haben nur einen informellen Character und sind keine dauerhaften Aufzeichnungen.

Die Planung ist einfach zu nutzen:
* Bezeichnung und Zeitraum definieren
* betroffene Personen oder Themen benennen
* Farben für Bereiche oder Aufgaben definieren
* den Zeitraum für Namen/Themen mit einer gewählten Farbe einfärben, mit der Maus oder einem geeigneten Zeiger

Gewählte Zeiträume werden auf den ersten Tag des Startmonats und den letzten Tag des Endmonats korrigiert. Die Einfärbung erfolgt für halbe Monate.

![sample longterm planning](http://toh.erroronline.one/caro/longtermplanning%20de.png)

[Übersicht](#übersicht)

### Zeiterfassung
Neben der Terminplanung kann der Kalender für die Erfassung der Arbeitszeiten der Mitarbeiter genutzt werden. Dies steht nur mittelbar in Zusammenhang mit der Arbeitsplanung, soweit Urlaube und andere dienstfreie Zeiten erfasst und angezeigt werden können und die Planungen beeinflussen können. Wo wir aber schon einmal dabei sind können ebensogut die Arbeitszeiten erfasst und berechnet werden. Die Anzeige und der Export ist nur für den betroffenen Nutzer, Bereichsleiter und berechtigte Nutzer möglich. Letztere sind dazu berechtigt für jeden Nutzer eine Eingabe zu machen um beipielsweise Bereiche über Krankenausfälle zu informieren. Nicht abgeschlossene Einträge können nur durch den Nutzer selbst bearbeitet werden. Der Status als abgeschlossen kann von einem Bereichsleiter des dem Nutzer zugewiesenen Bereichs oder für Vollzugriff berechtigten Nutzern gesetzt werden. Die Nutzereinstellungen erlauben die Eingabe von Wochenstunden zugunsten einer zielführenden Berechnung. Die Zeiterfassung findet auf Vertrauensbasis statt, wobei jeder Mitabeiter seine Dienstzeiten manuell einträgt.

Dies soll eine transparente Kommunikation, einen vertraulichen Umgang mit den Daten und eine gemeinsame Übereinkunft über die Zeiterfassung sicherstellen. Ziel ist es allen bekannten Anliegen deutschen Rechts und denen der Personalräte und Gewerkschaften zu entsprechen. Dabei handelt es sich nicht um eine dauerhafte Erfassung, da die Datenbank um Nutzereinträge bei deren Löschung bereinigt wird. Arbeitszeitzusammenfassungen können exportiert werden, was nach aktuellem Stand ein bevorzugter Weg ist und im Sinne einer langfristigeren Datenspeicherung im Sinne von Arbeitszeitgesetzen empfohlen wird. Die Aufzeichnungen entsprechen etablierten Verfahren in Art und Umfang, verbessern die Zugriffssicherheit sensibler Daten auf einen eingeschränkten Personenkreis und vereinfachen die Berechnung ehrlich erfasster Daten. 

Dienstfreie Tage der übereinstimmenden Bereiche werden sowohl bei den geplanten Ereignissen angezeigt als auch andersherum, um für das Arbeitsaufkommen der verbleibenden Belegschaft zu sensibilisieren.

*Warnung: die aktuelle Implementierung berücksichtigt weder mögliche Änderungen gesetzlicher Feiertage noch den Wechsel des Bundeslandes, sofern mehrere in den Profileinstellungen zur Auswahl stehen. Derzeit würden Änderungen auch vergangene Zeiterfassungen betreffen und unterschiedliche Berechnungen ergeben. Bei Änderungen wird empfohlen zuvor die neuesten Zeiterfassungen zu exportieren und innerhalb der Anwendung neu zu beginnen.*

Die Zeiterfassung unterstützt jedoch Änderungen der Wochenarbeitszeit und des Jahresurlaubs. Die jeweiligen Start-Daten und Werte sind Bestandteil der Nutzereinstellungen.

Für eine korrete Berechnung ist es erforderlich Werte als *Startdatum und Jahresurlaub/Wochenarbeitszeit* im ISO 8601 Format `yyyy-mm-dd XX` anzugeben, wobei `XX`für die Anzahl an Urlaubstagen oder Wochenarbeitszeitstunden steht. Falls unterjährig in die Berechnung eingestiegen werden soll muss der erste Eintrag den Resturlaub beinhalten. Danach sollte der volle Jahresurlaub mit Start zum 1. Januar des Folgejahres eingetragen werden. Bei Beendigung der Berechnungen sollte ein weiterer Wert mit den Resttagen zum Ende hin erfolgen. Ein Beispiel für eine dreijährige Periode mit Start und Ende im Sommer und jeweils 30 Tagen Jahresurlaub sähe ertwa folgendermaßen aus:
```
2023-07-01; 15
2024-01-01; 30
2026-01-01; 15
```
Die Wochenarbeitszeit sieht mit `2023-07-01; 39,5` ähnlich aus, Dezimalwerte sind erlaubt, als Trennzeichen gelten Komma oder Punkt. Die Trennung zwischen Datum und Wert ist mit Ausnahme von Zahlen frei wählbar.

Exporte sind nach Nutzernamen alphabetisch aufsteigend sortiert, mit dem exportierenden Nutzer jedoch der Bequemlichkeit halber stets als erstes.

[Übersicht](#übersicht)

## Dateien
![sample files menu](http://toh.erroronline.one/caro/files%20menu%20de.png)

Berechtigte Nutzer können Dateien für alle bereitstellen. Alle Nutzer können zudem zum öffentlichen Sharepoint beitragen. Hier haben Dateien nur eine begrenzte Verweildauer und werden automatisch gelöscht.

STL- und Bild-Dateien haben eine automatische Vorschau.

Diese Quellen können auch dafür verwendet werden um Dokumente bereitzustellen, die [nicht digital ausgefüllt](#datenintegrität) werden können. *Es wird jedoch empfohlen interne Dokumente mit einer Exportberechtigung zu versehen um Versionskonflikte zu vermeiden; dies betrifft auch die ordnungsgemäße Registrierung externer Dokumente.*

Externe Dokumente gemäß ISO 13485 4.2.4 müssen identifiziert und gelenkt werden. Daher erhalten diese Dateien eine besondere Beachtung und sollen mit entsprechenden Eintragungen in Bezug auf die Einführung, den regulatorischen Zusammenhang, mögliche Außerbetriebnahme und dem Nutzernamen der letzten Entscheidung erfasst werden. Im Sinne einer durchgängigen Dokumentation können diese Dateien nicht gelöscht, sondern nur unzugänglich gemacht werden. Insbesondere in Bezug auf Schnittstellen können auch Netzwerkressourcen als Quelle angegeben werden.

![files screenshot](http://toh.erroronline.one/caro/files%20de.png)

[Übersicht](#übersicht)

## Einkauf
![sample purchase menu](http://toh.erroronline.one/caro/purchase%20menu%20de.png)

### Lieferanten- und Artikelverwaltung
Bestellvorgänge bedürfen einer Lieferanten- und Artikeldatenbank. Dies steht auch im Zusammenhang mit einer Produkteinführung, Stichprobenprüfung, Dokumenten- und Zertifikatsverwaltung. Berechtigte Nutzer können diese Kategorien verwalten, neue Lieferanten und Artikel hinzufügen oder bearbeiten, Preislisten importieren, Filter definieren oder Lieferanten und Artikel deaktivieren. Der [Import von Preislisten](#importierung-von-lieferantenpreislisten) nutzt den [CSV-Prozessor](#csv-prozessor).

Lieferanten sollen evaluiert werden. Dazu ist ein entsprechendes Dokument mit dem Kontext *Lieferantenbewertung* erforderlich. Die Evaluation ist automatisch Teil der Lieferantenansicht im Bearbeitungsmodus.

Lieferantenbezogene Dateianhänge können beigefügt werden. Durch die entsprechende Eingabemaske wird dem [Dateinamen ein Ablaufdatum hinzugefügt](#dateinamenkonventionen). Ohne angegebenes Datum und für Dateiuploads innerhalb des Dokuments zur Lieferantenbewertung wird die Gültigkeit für ein Jahr ab Bereitstellung festgelegt.  
Die Anwendung überwacht die angegebenen Verfallsdaten und trägt einen Hinweis in den [Kalender](#kalender) ein, sobald das Datum überschritten ist, um die betroffenen Bereiche an eine Aktualisierung zu erinnern. Dateinamen die der Dateinamenkonvention für diesen Anwendungsfall entsprechen werden zurückgewiesen.  
Die Bearbeitungsansicht für Lieferanten erlaubt die Auswahl von [Textvorschlägen](#textvorschläge). Sofern diese ordnungsgemäß vorbereitet sind können vorbereitete Werte einfach in die Platzhalter eingefügt werden.  
Kleinere Lieferantenportfolios könnten primär oder anfänglich innerhalb der Anwendung verwaltet werden. Artikellisten können zusammen mit dem Import-Filter exportiert werden. Letzterer [wird erzeugt](#standardfilter-bei-export) sofern nicht definiert.
> Erzeugte Filter funktionieren nicht mit Herstellerpreislisten, exportierte Artikellisten funktionieren nicht mit angepassten Filterregeln!

Besondere berechtigte Nutzer (z.B. *Einkaufsassistent*) können Aliasbezeichnungen von Artikeln anpassen um den Einkauf zu entlasten und die Identifikation von Artikeln mit betriebsinternen Gepflogenheiten zu verbessern.

Bei der Anpassung von Artikeln können unter anderem folgende Eigenschaften bearbeitet werden:
* Handelsware,
* Verfallsdatum,
* besondere Beachtung (die konkrete Bedeutung wird in der Sprachdatei festgelegt, z.B. Hautkontakt),
* Lagerware,
* Entzug der Produkteinführung,
* den Artikel als *verfügbar* oder *nicht verfügbar* markieren.

Bei jeder dieser Einstellungen können ähnliche Artikel gewählt werden, auf die diese Einstellungen ebenfalls angewendet werden sollen. Die Auswahl schlägt alle Artikel des gleichen Lieferanten vor, deren Artikelnummern eine in der [config.ini](#laufzeitvariablen) festgelegte Ähnlichkeit aufweisen.

Artikel können ebenfalls mit Dateien bereichert werden, z.B. Konformitätserklärungen oder Biokompatibilitätsnachweise. Wie die Hersteller-Dokumente wird ein [Ablaufdatum eingefügt](#dateinamenkonventionen) und an eine Erneuerung erinnert. Dateinamen die der Dateinamenkonvention für diesen Anwendungsfall entsprechen werden zurückgewiesen.

Deaktivierte Produkte können durch das Bestell-Modul nicht erreicht werden. Artikel können gelöscht werden so lange sie nicht als geschützt markiert sind. Lieferanten können nicht gelöscht werden.

![vendor manager screenshot](http://toh.erroronline.one/caro/vendor%20manager%20de.png)

```mermaid
graph TD;
    manage_vendors((Lieferanten))--->edit_vendor[bestehenden Lieferanten bearbeiten];
    edit_vendor-->vendor_db[("Lieferanten-
    datenbank")];
    manage_vendors-->new_vendor[neuer Lieferant];
    vendor_db-->add_vinfo["Dokumente hinzufügen
    Informationen aktualisieren,
    Preislistenfilter festlegen"];
    new_vendor-->add_vinfo;
    add_vinfo-->vendor_db;
    add_vinfo-->inactivate_vendor[Lieferant deaktivieren];
    inactivate_vendor-.->delete_all_products[alle Produkte löschen];
    add_vinfo-->import_pricelist[Preisliste importieren];
    import_pricelist-->delete_all_products;
    delete_all_products-->has_docs2{"Dokumente vorhanden,
    Einführung erfolgt,
    Stichprobenprüfung erfolgt,
    (geschützt)"};
    delete_all_products-...->has_docs{"Dokumente vorhanden,
    Einführung erfolgt,
    Stichprobenprüfung erfolgt,
    (geschützt)"};
    has_docs2-->|ja|update["Aktualisierung nach Artikelnummer"];
    update-->product_db[("Produkt-
    datenbank")];
    has_docs2-->|nein|delete[Löschung];
    delete-->|Wiedereinfügung aus Preisliste|product_db;
    
    manage_products((Produkte))==>edit_product[bestehendes Produkt bearbeiten];
    edit_product==>product_db;
    product_db==>add_pinfo["Dokumente hinzufügen,
    Informationen aktualisieren"];
    manage_products==>add_product[neues Produkt hinzufügen];
    add_product==>select_vendor[Lieferanten wählen];
    select_vendor==>vendor_db;
    vendor_db==>known_vendor{Lieferant in Datenbank};
    known_vendor==>|ja|add_pinfo;
    known_vendor==>|nein|new_vendor

    add_pinfo==>product_db;

    edit_product==>similar{ähnliche Produkte auswählen};
    similar==>update2["aktualisiere gewählte Produkte
    innerhalb der Datenbank,
    setze Verfügbarkeit,
    Handelware,
    entziehe Einführung"];
    update2==>product_db;
    edit_product==>delete_product(Produkt löschen);
    delete_product==>has_docs;
    has_docs==>|nein|product_deleted["Produkt löschen"];
    has_docs==>|ja|product_inactive["Produkt deaktivieren"];
    product_deleted==>product_db;
    product_inactive==>product_db;

    product_db o-..-o state{Status};
    state-.->|aktiv|orderable[bestellbar];
    state-.->|inaktiv|inorderable[nicht bestellbar];
    state-.->|gelöscht|inorderable;
```

[Übersicht](#übersicht)

### Bestellung
Das Bestellmodul unterstützt alle Parteien. Der Einkauf erhält strukturierte und vollständige Daten für Bestellungen, während die bestellenden Bereiche unmittelbare Informationen über den Bestellstatus erhalten.  
Artikel sollen aus der Datenbank gewählt werden, die durch die Preislistenimporte befüllt wird. Eine manuelle Bestellung ist jedoch möglich. Jedoch können nur Artikel in der Datenbank zusätzliche Informationen bereitstellen:  
Bestellte Artikel erteilen unmittelbar Auskunft über ihren Einführungsstatus oder ob sie für eine Stichprobenprüfung in Frage kommen. Beide Maßnahmen können direkt aus der Auflistung bestellter Artikel ergriffen werden, während des laufenden Betriebs und ohne Verwechslungen. Das Datum der letzten Bestellung wird bei ausgelieferten Artikeln aktualisiert.  
Manuelle Bestellungen erlauben einen direkten Import in den Artikelstamm.

Manchmal weiß der Einkauf besser über Lieferkonditionen bescheid. Falls es dem Besteller egal ist von welchem Lieferanten das Produkt kommt, kann dem Einkauf mitgeteilt werden gegebenenfalls auch ein ähnliches Produkt zu bestellen.

Bestellungen müssen freigegeben werden, vorbereitete Bestellungen sammeln sich an und können von einem Nutzer mit Bestellberechtigung (z.B. PIN, Zugangstoken, Unterschrift, je nach [Konfiguration](#laufzeitvariablen)) gesammelt freigegeben werden.

Freigegebene Bestellungen können als *bestellt*, *teilweise erhalten*, *vollständig erhalten*, *teilweise ausgeliefert*, *ausgeliefert* und *archiviert* markiert werden. Ausgelieferte Bestellungen welche nicht archiviert sind werden nach einer definierten Zeitspanne automatisch gelöscht. Der Einkauf kann Bestellungen auch unter Angabe von Gründen zurückweisen. In diesem Fall werden alle Nutzer des bestellenden Bereichs über die fehlgeschlagene Bearbeitung der Bestellung informiert. Bestellungen die verarbeitet aber noch nicht als erhalten markiert sind werden regelmäßig gemäß [config.ini](#laufzeitvariablen) erinnert um beim Lieferanten ein Lieferdatum zu erfragen.  
Falls der Einkauf auch ein ähnliches Produkt bestellen kann, wird daran erinnert gegebenenfalls die Bestellung zu korrigieren um das System in Bezug auf Produkteinführungen, Stichprobenprüfungen und Rückverfolgung nicht durcheinanderzubringen.

Jeder Bestellung kann jederzeit Informationen angehängt werden.
Bestellte aber noch nicht erhaltene Bestellungen können eine Bestallstatusänderung erfahren, in welchem Fall der bestellende Bereich eine Benachrichtigung erhält. Diese Bestellungen können auch noch storniert werden und werden dann wieder den nicht bestellten Bestellungen mit einem Storno-Kennzeichen zugeordnet. Eine abgeschlossene Stornierung wird automatisch gelöscht. Erhaltene Artikel können zurückgesandt werden. Rücksendungen erzeugen eine neue Retour-Bestellung ohne erforderliche Freigabe und Änderung der Originalbestellung. Eine "bestellte" Rücksendung wird automatisch als "erhalten" gekennzeichet - dies erfasst jedoch bewusst keine Erstattung seitens der Lieferanten, da derartige Vorgänge typischerweise in einem anderen System stattfinden und eine doppelte Bearbeitung vermieden werden soll.  
Es muss ein Rücksendegrund angegeben werden. Kritische Rücksendegründe lösen eine Mitteilung an für die Einführung authorisierte Mitarbeiter aus und initiieren eine Neubewertung der Produkteinführung.  
Alle Maßnahmen bieten an eine Nachricht beizufügen.  
Aus der in der Bestellung angegebenen Kommission kann direkt ein Aufkleberbogen erzeugt werden um bei der internen Auslieferung eine Zuordnung zu unterstützen.

Mit der Bestellnummer kann direkt ein Aufkleberbogen mit Ergänzung einer geeigneten Chargen- oder Lieferscheinnummer erstellt werden um einen scanbaren Code für eine Rückverfolgung zu erhalten.

Die Darstellung von Bestellungen kann innerhalb des [Nutzer-Profils](#nutzer) aus Volldarstellung und kompakten Kacheln mit eingeschränktem Informationsgehalt gewählt werden. Dies soll eine Anpassung an unterschiedliche Bedürfnisse ermöglichen, z.B. der Warenwirtschaft alle erforderlichen Informationen anzeigen, während andere Nutzer eine geringere Informationsfülle bevorzugen.

Bearbeitete Bestellungen werden zusätzlich in reduzierter Form zu einer zusätzlichen Datenbank hinzugefügt. Diese Daten können im [Regulatorische Auswertungen und Zusammenfassungen-Modul](#regulatorische-auswertungen-und-zusammenfassungen) exportiert und für die Lieferantenbewertung genutzt werden.

![orders screenshot](http://toh.erroronline.one/caro/orders%20de.png)

```mermaid
graph TD;
    new_order((neue Bestellung))-->search_products[Artikelsuche];
    search_products-->product_db[("Produkt-
    datenbank")];
    product_db-->product_found{Artikel gefunden};
    product_found-->|ja|add_product["Artikel zur
    Bestellung hinzufügen"];
    new_order-->add_manually[manuell hinzufügen];
    product_found-->|nein|add_manually;
    product_found---->|nein|manage_products(("Produkte
    bearbeiten"));
    add_manually-->add_product;
    add_product-->search_products;
    add_product-->add_info["Bereich wählen,
    Begründung angeben,
    Dateien anhängen"];
    add_info-->approve_order{Bestellung freigeben};
    approve_order-->|mit Unterschrift|approved_orders(("freigegebene Bestellungen
    (nur von eigenen Bereichen, außer Einkäufer oder Admin)"));
    approve_order-->|mit PIN|approved_orders;
    approve_order-->|nein|prepared_orders(("vorbereitete Bestellungen,
    nur von eigenen Bereichen,
    außer bestellberechtigt
    und Bereich ausgewählt"));

    approved_orders==>process_order{Bestellung bearbeiten};

    process_order=====>regulatory{"regulatorische Anforderungen
    sofern anwendbar"};
    regulatory==>incorporate[Produkteinführung];
    incorporate==>incorporate_similar{"ähnline Artikel"};
    incorporate_similar==>|ja|select_similar["ähnliche wählen,
    Daten anfügen"];
    select_similar==>productdb[("Produkt-
    datenbank")]
    incorporate_similar==>|nein|insert_data[Daten anfügen];
    insert_data==>productdb;
    productdb==>checksdb[("Prüfungs-
    datenbank")];
    regulatory==>sample_check[Stichprobenprüfung];
    sample_check==>productdb;

    process_order===>cancel_order[Storno];
    cancel_order==>rewrite_cancel[in Stornierung umwandeln];
    rewrite_cancel==>approved_orders;

    process_order===>return_order[Rücksendung];
    return_order==>clone_order["Bestellung kopieren,
    als Rücksendung markieren"];
    clone_order==>approved_orders;
    return_order======>|kritischer Grund|incorporate;

    process_order==>disapprove[zurückweisen];
    disapprove==>|optional Nachricht anfügen|message_unit["alle Bereichsmitarbeiter
    benachrichtigen"];
    message_unit==>prepared_orders;

    process_order======>mark{markieren};
    mark==>|bestellt|order_type{Bestelltyp};
    order_type==>|Bestellung|auto_delete["automatische Löschung
    nach x Tagen"];
    order_type==>|Rücksendung|auto_delete;
    order_type==>|Service|auto_delete;
    order_type==>|Storno|order_deleted(Bestellung gelöscht)
    mark==>|erhalten|auto_delete;
    mark==>|ausgeliefert|auto_delete;
    mark==>|archiviert|delete[manuelle Löschung];
    auto_delete==>order_deleted;
    
    process_order==>delete;
    delete==>delete_permission{"Berechtigung
    zur Löschung"};
    delete_permission==>|Bereichsmitarbeiter|order_deleted;
    delete_permission==>|Einkauf, unbearbeitete Bestellung|order_deleted;
    delete_permission==>|Einkauf, bearbeitete Bestellung|approved_orders;

    process_order====>update_state[Bestellstatusaktualisierung];
    update_state==>append_inform["Information angeben,
    Bereichsmitarbeiter benachrichtigen"];
    append_inform==>process_order
    
    process_order===>add_orderinfo[Information anfügen];
    add_orderinfo==>process_order;
    process_order===>message(("Besteller
    benachrichtigen"))

    prepared_orders-.->mark_bulk{"Bestellungen für
    Freigabe markieren"};
    mark_bulk-.->|ja|approve_order;
    mark_bulk-.->|nein|prepared_orders;
    prepared_orders-.->add_product;
```
Begonnene Produkteinführungen werden von allen Rollen als freigegeben markiert, die dem initial bewertenden Nutzer innewohnen. Eine vollständige Freigabe kann jedoch durch weitere Rollen erforderlich sein.  
Stichprobenprüfungen werden den Aufzeichnungen beigefügt. Neue Prüfungen lösen eine Benachrichtigung an die berechtigten Nutzer aus. Berechtigte Nutzer können die Prüfung innerhalb des [Regulatorische Auswertungen und Zusammenfassungen-Modul](#regulatorische-auswertungen-und-zusammenfassungen) und der [Artieklverwaltung](#lieferanten--und-artikelverwaltung) widerrufen.

[Übersicht](#übersicht)

## Werkzeuge
![sample tools menu](http://toh.erroronline.one/caro/tools%20menu%20de.png)

Es stehen einige allgemeine Werkzeuge für das Lesen und Erzeugen von 2D-Codes, sowie der Unterstützung bei wiederkehrenden Berechnungen, Bildskalierung und ZIP-Archivierung zur Verfügung.

Weiterhin sind an dieser Stelle ein CSV-Filter und dessen Verwaltung eingeordnet. Der CSV-Filter verarbeitet entsprechende Dateitypen unter Verwendung des [CSV-Prozessors](#csv-prozessor) und kann für eine Vielzahl an Datenvergleichen verwendet werden. Filter sind für berechtigte Nutzer erreichbar.

Nutzer die für Audits und Dokumentenerstellung berechtigt sind finden einen [Markdowneditor](#markdown) mit Vorschau.

## Regulatorische Auswertungen und Zusammenfassungen
Dieses Modul sammelt verfügbare Daten aus der Anwendung und stellt damit Listen zusammen die eine Erfüllung regulatorischer Anforderungen unterstützen:
* eingeführte Produkte
* Erfahrungspunkte basierend auf Schulungen, sofern für Schulungen Punkte vergeben werden (intern oder extern, z.b. [IQZ](https://ot-iqz.de/))
* Erfüllung regulatorischer Anforderungen durch Dokumente
* aktuell gültige, incl. externe Dokumente
* Lieferantenverzeichnis
* Stichprobenprüfungen gemäß MDR §14
* Mitarbeiterqualifikationen und Schulungen mit der Option verpflichtende Schulungen für mehrere Mitarbeiter gleichzeitig einzutragen 
* Qualifikationserfüllung
* Reklamationen
* Problembericht für inkonsistent definierte Risiken, incl. strukturiertem Export aller erfassten Risiken
* Schulungsbewertung
* interne Audits

Ferner hoffentlich hilfreiche Informationen zu
* der Angemessenheit aktueller Dokumente incl. einem Nutzungszähler gemäß Aufzeichnungen
* Bestellstatistiken in Form eines strukturierten Excel-Export um die Lieferantenbewertung basierend auf Lieferdaten zu ergänzen
* Versorgungsstatistiken, bei Export aller verfügbaren Versorgungsaufzeichnungen innerhalb eines wählbaren Zeitraumes, deren Inhalte beliebig ausgewertet werden können, vorausgesetzt die [Dokumente](#dokumente) haben einzigartige Fragestellungen. Die Ausgabedatei ist eine CSV-Datei die unter anderem mit einem entsprechenden Filter auch durch den [CSV-Prozessor](#csv-prozessor) verarbeitet werden kann.

![regulatory screenshot](http://toh.erroronline.one/caro/regulatory%20de.png)

[Übersicht](#übersicht)

## Wartung
Die Anwendung hat einige Optionen für die Wartung durch berechtigte Nutzer:
* Der Aufruf der Startseite löst den 'cron job' für die Bereinigung abgelaufener Dateien und die Erstellung automatischer Benachrichtigungen und Aufgabenplanungen aus. Die Durchführung findet einmal täglich statt. Die Log-Datei `cron.log` innerhalb des API-Verzeichnisses mit Erfolg- oder Fehlermeldungen kann angezeigt und gelöscht werden. Die Löschung der Log-Datei löst das Update erneut aus.
* Bestehende Lieferanten können in Bezug auf ihre Informationen und die Preislisten-Einstellungen (Importfilter und Stichproben-Intervalle) aktualisiert werden. Eine Datei gemäß [Vorlage](#anwendungseinrichtung) kann bereitgestellt werden. Die jeweiligen Aktualisierungen können für jeden übereinstimmenden Lieferanten gewählt werden.
* Dokumente können lernende Eingabefelder beinhalten um vergangene Einträge eines Fachbereichs vorzuschlagen. Es können dabei fehlerhafte Einträge erfolgen. Es kann eine CSV-Datei heruntergeladen, bearbeitet und wieder bereitgestellt werden, oder vorbereitete Empfehlungen bereitgestellt werden. Eine hochgeladene Datei überschreibt die kompletten Datensätze des gewählten Fachbereichs. Tabellenüberschriften entsprechen den Namen der Eingabefelder, die Zeilen den Vorschlägen. Ohne bereitgestellte Datei gibt es den Export.

[Übersicht](#übersicht)

# In der Anwendung verteilte Integrationen

## Erinnerungen und automatische Aufgabenplanung
Die Anwendung vearbeitet einige automatische Erinnerungen und Aufgabenplanungen

* ausgelaufene Verantwortlichkeiten erzeugen eine Mitteilung für die Neuvergabe
* offene Vorgänge erzeugen eine Mitteilung fortzufahren oder abzuschließen
* neue Bestellungen erzeugen eine Mitteilungg für die Bearbeitung
* nicht erhaltene Bestellungen erzeugen eine Mitteilung sich beim Lieferanten nach dem Lieferzeitpunkt zu erkundigen
* erhaltene aber nicht ausgelieferte Bestellungen erbitten die Nachfrage bei der Warenwirtschaft oder die Markierung als ausgeliefert
* Schulungsbewertungen werden in den Kalender eingetragen
* ablaufende Schulungen werden für die Folgeschulung geplant
* abgelaufene Lieferantenzertifikate werden für eine Erneuerung in den Kalender eingetragen
* es wird an eine Reevaluierung oder Erneuerung der Dateien in der Artikelverwaltung erinnert 

Daneben informiert die Startseite und das Menü über offene Themen und Aufgaben. Zeiträume für Benachrichtigungen und Aufgabenplanung können in der [Konfiguration](#laufzeitvariablen) angepasst werden.

Automatische Erinnerungen und Aufgabenplanungen werden einmal bei der ersten Anmeldung des Tages ausgeführt.

## Schulungen
Schulungen können in der [Nutzerverwaltung](#nutzer), aber auch aus den [regulatorischen Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen) heraus eingetragen werden. In Bezug auf ISO 13485 8.5.1 Verbesserung und ISO 13485 8.5.2 Korrekturmaßnahmen können Schulunngen auch im Falle einer Versorgungsdokumentation, welche als Reklamation markiert wurde, eingetragen werden.

Schulungen können geplant werden, wenn kein konkretes Datum eingetragen wurde und später in die tatsächlich stattgefundene Schulung umgewandelt und durch Angaben eines Ablaufdatums, Erfahrungspunkten und einer Datei, z.B. des Zertifikats ergänzt werden.

Es wird automatisch an die Bewertung von Schulungen erinnert. Ablaufende Schulungen werden durch das System selbstständig für eine Folgeschulung geplant.

Schulungen sind keine dauerhaften Aufzeichnungen und können von berechtigten Nutzern gelöscht werden. Es ist zu beachten, dass fehlende Daten keinen Nachweis aus der Anwendung heraus erbringen können. Es kann jedoch passieren, dass eingestellte Zertifizierungen für ausgelaufene Produkte unerfüllbar geplante Folgeschulungen erzeugen. Da die Bearbeitung abgeschlossener Schulungen nicht möglich ist, wäre die Löschung und Neuanlage der Schulung ohne Ablaufdatum eine mögliche Lösung.

Der Abgleich der Schulungen erfolgt über den Namen der Schulung.

## Suche
Die Funktionalität der Suche kann sich innerhalb der Anwendung abhängig vom Zusammenhang unterscheiden.

* die Kalender-Suche, Artikel-Suche und der Filter für Bestellungen suchen aus Leistungsgründen buchstäblich nach dem angegebenen Begriff als Teil der Datenbankinformation. Es muss gegebenenfalls genauer gesucht werden um das gewünschte Ergebnis zu erzielen.
* Bearbeitungsmasken (z.B. Dokumente, CSV-Filter) stellen eine Sucheingabe bereit, welche Vorschläge aus der bisherigen Eingabe präsentiert. Für das gewünschte Ergebnis muss der Volltext aus den Vorschlägen ausgewählt werden.
* alle anderen Such-Möglichkeiten (z.B. Dateien, Aufzeichnungs-Identifikatoren, Dokumentensuche) erlauben Platzhalter wie `*` für eine beliebige Anzahl beliebiger Zeichen oder `?` als beliebiges Zeichen an der angegebenen Position und berücksichtigen zumeist [ähnliche Schreibweisen](#laufzeitvariablen) bei den Ergebnissen.

[Übersicht](#übersicht)

## Markdown
Sofern verfügbar kann text mit einer Markdown-Variante formatiert werden, die dem Original sehr nahe kommt.  
Das ist keine Raketenwissenschaft, eine grundlegende Formatierung kann schnell erzielt werden. Bearbeitungsmasken haben eine Kurzübersicht, eine Schaltfläche um zum Markdowneditor mit Vorschau zu gelangen, sowie eine Umwandlung von CSV-Tabellen in das Markdown-Format und andersherum.

Siehe [Bekannte Schwachstellen](#bekannte-schwachstellen) für Unterschiede zu normalem Markdown.

Unterstütze Formatierungsoptionen beinhalten:

```
# Einfacher Text (h1 Überschrift)

Dies ist eine Markdown-Variante für einfache Textgestaltung.  
Zeilen sollten mit zwei oder mehr Leerzeichen enden  
um einen beabsichtigten Zeilenumbruch zu erzeugen
und nicht einfach fortgeführt zu werden.

Text kann *kursiv*, **fett**, **kursiv und fett, ~~durchgestrichen~~ und im `quelltextstil` mit je zwei oder mehr Zeichen zwischen den Symbolen dargestellt werden.  
Das Maskieren von Formatierungszeichen ist mit einem vorangestellten \ möglich, wie in
**fettes \* Sternchen**, ~~durch \~~ gestrichen~~ und `Code mit einem \`-Zeichen`.  
Außerdem ``Code mit ` maskiert durch umgebende doppelte Gravis'``

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

### Tabellen (h3 Überschrift)

| Tabellenüberschrift 1 | Tabellenüberschrift 2 | Tabellenüberschrift 3 | und 4 |
| --- | --- | --- | --- |
| *Akzentuierung* | **ist** | ***ebenfalls*** | `möglich` |
| Zeilenumbrüche | sind es | jedoch | nicht |

- - -

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
ein `code mit <Klammern>`  
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
> | In Zitatblöcken | verschachtelte |
> | ---------- | ----- |
> | Tabellen sind | möglich |

[erste Überschrift](#einfacher-text)
```

und werden in etwa folgendermaßen angezeigt:

![markdown screenshot](http://toh.erroronline.one/caro/markdown%20de.png)


[Übersicht](#übersicht)

## CSV Prozessor
Der CSV Prozessor ist Bestandteil des CSV-Filter-Moduls und wird für den Artikelimport über die Lieferantenpreislisten genutzt. Es ist ein vielseitiges Werkzeug, erfordert aber Kenntnisse der [JavaScript object notation](https://developer.mozilla.org/en-US/docs/Learn/JavaScript/Objects/JSON), [regulärer Ausdrücke](https://regex101.com/) und [PHP-Datetime-Formate](https://www.php.net/manual/en/datetime.format.php).

Filter und Änderungen werden in der angegebenen Reihenfolge ausgeführt. Änderungen werden zugunsten einer Leistungsoptimierung erst in der gefilterten Liste durchgeführt. Vergleichslisten können genauso gefiltert und geändert werden. Aufgrund einer rekursiven Implementierung kann die ursprüngliche Liste auch als Filterkriterium genutzt werden.

Beschreibung der Optionen:

	"postProcessing": optionale Zeichenkette als Hinweis, was mit der Ergebnisdatei passieren soll
	"filesetting":
		"source": Datei zur Verarbeitung, "SELF" oder ein assoziatives Array (hier spielen die anderen Einstellungen keine Rolle)
		"headerrowindex": Offset für die Titelzeile
		"dialect": Einstellungen gemäß php fgetcsv
		"columns": Liste von Spaltennamen, die verwertet und exportiert werden sollen
		"encoding": kommagetrennte Zeichenkette möglicher Zeichenkodierungen der Quelldatei

	"filter": Liste von Objekten
		"apply": "filter_by_expression"
		"comment": Beschreibung, wird angezeigt
		"keep": Boolescher Wert ob Treffer behalten oder aussortiert werden sollen
		"match":
			"all": Alle Ausdrücke müssen gefunden werden, Objekt mit Spaltenname als Schlüssel und Muster als Wert,
			"any": Wenigstens ein Ausdruck muss gefunden werden. Es kann nur "all" oder "any" genutzt werden

		"apply": "filter_by_monthdiff"
		"comment": Beschreibung, wird angezeigt
		"keep": Boolescher Wert ob Treffer behalten oder aussortiert werden sollen
		"date": Filtert nach identifier und Unterschied zweier Daten in Monaten
			"identifier": Spaltenname mit wiederkehrenden Werten, z.B. Kundennummer,
			"column": Spaltenname mit einem zu vergleichenden Datum,
			"format": gemäß https://www.php.net/manual/en/datetime.format.php,
			"threshold": Ganzzahl für Monate,
			"bias": < kleiner als, > größer als threshold

		"apply": "filter_by_duplicates",
		"comment": Beschreibung, wird angezeigt
		"keep": Boolescher Wert ob Treffer behalten oder aussortiert werden sollen
		"duplicates": Behalte Anzahl an Duplikaten eines Spaltenwerts, sortiert nach den Werten anderer verketteter Spalten (auf- oder absteigend)
			"orderby": Liste von Spaltennamen deren Werte als Vergleich verkettet werden sollen
			"descending": Boolescher Wert,
			"column": Spaltenname mit wiederkehrenden Werten, z.B. Kundennummer, von denen X gleiche Zeilen erlaubt sein sollen
			"amount": Ganzzahl > 0

		"apply": "filter_by_comparison_file",
		"comment": Beschreibung, wird angezeigt
		"keep": Boolescher Wert ob Treffer behalten oder aussortiert werden sollen, nicht gesetzt oder null um nur Werte von Treffern zu übertragen
		"compare": Behalte oder lösche z.B. bestimmte Werte gemäß Vergleichsdatei, basierend auf dem selben Identifikator
			"filesetting": die gleiche Struktur wie allgmein. Wenn source = "SELF" wird die Ursprungsdatei verarbeitet
			"filter": die gleiche Struktur wie allgemein
			"modify": die gleiche Struktur wie allgemein
			"match":
				"all": Object mit ein oder mehreren "ORIGINCOLUMN": "COMPAREFILECOLUMN" - Paaren, alle Vergleiche müssen zutreffen
				"any": Object mit ein oder mehreren "ORIGINCOLUMN": "COMPAREFILECOLUMN" - Paaren, mindestens ein Vergleich muss zutreffen
		"transfer": Füge zur Ursprungsdatei eine Spalte mit Werten der passenden (all) oder ersten gefundenen (any) Zeile der Vergleichsdatei hinzu

		"apply": "filter_by_monthinterval",
		"comment": Beschreibung, wird angezeigt
		"keep": Boolescher Wert ob Treffer behalten oder aussortiert werden sollen
		"interval": Behalte oder lösche Zeilen bei denen ein Monats-Interval nicht zutrifft, mit optionaler Verschiebung vom ursprünglichen Spaltenwert
			"column": Spaltenname mit einem zu vergleichenden Datum,
			"format": gemäß https://www.php.net/manual/en/datetime.format.php,
			"interval": Ganzzahl für Monate,
			"offset": optionale Verschiebung in Monaten (Ganzzahl)

		"apply": "filter_by_rand",
		"comment": Beschreibung, wird angezeigt
		"keep": Boolescher Wert ob Treffer behalten oder aussortiert werden sollen
		"data": Wähle eine Anzahl zufälliger Zeilen aus, deren Spaltenwerte mit dem Suchmuster übereinstimmen (bei mehreren müssen alle zutreffen)
			"columns": Objekt mit Spalten-Muster-Paaren für die Eingrenzung,
			"amount": Ganzzahl > 0

	"modify": Ändert das Ergebnis
		"add": Fügt eine Spalte mit dem angegebenen Wert hinzu. Existiert der Spaltenname bereits wird die Spalte überschrieben!
			   Ist der Wert eine Liste mit Zahlen und mathematischen Operatoren wird versucht dies als Formel zu berechnen
			   Kommas werden in diesem Fall mit einem Dezimalpunkt ersetzt.
		"replace": Ersetzt Ergebnisse regulärer Ausdrücke mit dem angegebenen Wert entweder in einer angebenen Spalte oder an allen
				   abhängig davon ob das erste Listenelement ein Spaltenname oder null ist
				   Falls mehr als eine Ersetzung angegeben wird werden neue Zeilen mit geänderten Zellwerten zum Ergebnis ergänzt
				   Ersetzungen an einer bestimmten Position müssen die zweite Treffergruppe sein (full match, group 1 (^ if necessary), group 2, ...rest)
		"remove": Entfernt Spalten aus dem Ergebnis, die möglicherweise nur für die Filterung erforderlich waren
		"rewrite": Fügt neue Spalten hinzu welche aus Verkettungen der Originalwerte der angegebenen Spalten und anderen Zeichen bestehen
				   Die Originalspalten werden entfernt.
		"translate": Werte die gemäß eines speziellen Übersetzungsobjekts ersetzt werden
		"conditional_and": ändert den Wert einer Spalte wenn alle Ausdrücke zutreffen, fügt ggf. eine leere Spalte ein
		"conditional_or": ändert den Wert einer Spalte wenn einer der Ausdrücke zutrifft, fügt ggf. eine leere Spalte ein

	"split": Teilt das Ergebnis gemäß eines Ausdrucks in mehrere Gruppen auf, die in mehrere CSV-Dateien oder auf mehrere Tabellenblätter (XLSX) verteilt werden können

	"evaluate": Object mit Spalten-Ausdruck-Paaren, die eine Warnung erzeugen (z.B. Verifizierung eines eMail-Formats)

	"translations" : können z.B. numerische Werte mit lesbaren Informationen ersetzen.
					 Auf die Schlüssel dieses Objekts können die o.g. modifier verweisen.
					 Die Schlüssel werden als Ausdruck verarbeitet um eine vielseitige Verwendung zu ermöglichen.

Ein beliebiges Beispiel:

```javascript
{
    "postProcessing": "some message, e.g. do not forget to check and archive",
    "filesetting": {
        "source": "Export.+?\\.csv",
        "headerrowindex": 0,
        "columns": [
            "ORIGININDEX",
            "SOMEDATE",
            "CUSTOMERID",
            "NAME",
            "DEATH",
            "AID",
            "PRICE",
            "DELIVERED",
            "DEPARTMENT",
            "SOMEFILTERCOLUMN"
        ]
    },
    "filter": [
        {
            "apply": "filter_by_expression",
            "comment": "keep if all general patterns match",
            "keep": true,
            "match": {
                "all": {
                    "DELIVERED": "delivered",
                    "NAME": ".+?"
                }
            }
        },
        {
            "apply": "filter_by_expression",
            "comment": "discard if any general exclusions match",
            "keep": false,
            "match": {
                "any": {
                    "DEATH": ".+?",
                    "NAME": "company|special someone",
                    "AID": "repair|cancelling|special.*?names"
                }
            }
        },
        {
            "apply": "filter_by_expression",
            "comment": "discard if value is below 400 unless pattern matches",
            "keep": false,
            "match": {
                "all": {
                    "PRICE": "^[2-9]\\d\\D|^[1-3]\\d{2,2}\\D",
                    "AID": "^(?!(?!.*(not|those)).*(but|these|surely)).*"
                }
            }
        },
        {
            "apply": "filter_by_monthdiff",
            "comment": "discard by date diff in months, do not contact if last event within x months",
            "keep": false,
            "date": {
                "column": "SOMEDATE",
                "format": "d.m.Y",
                "threshold": 6,
                "bias": "<"
            }
        },
        {
            "apply": "filter_by_duplicates",
            "comment": "keep amount of duplicates of column value, ordered by another concatenated column values (asc/desc)",
            "keep": true,
            "duplicates": {
                "orderby": ["ORIGININDEX"],
                "descending": false,
                "column": "CUSTOMERID",
                "amount": 1
            }
        },
        {
            "apply": "filter_by_comparison_file",
            "comment": "discard or keep explicit excemptions as stated in excemption file, based on same identifier. source with absolute path or in the same working directory",
            "keep": false,
            "filesetting": {
                "source": "excemptions.*?.csv",
                "headerrowindex": 0,
                "columns": [
                    "VORGANG"
                ]
            },
            "filter": [],
            "match": {
                "all":{
                    "ORIGININDEX": "COMPAREFILEINDEX"
                },
                "any":{
                    "ORIGININDEX": "COMPAREFILEINDEX"
                }
            },
            "transfer":{
                "NEWPARENTCOLUMN": "COMPARECOLUMN"
            }
        },
        {
            "apply": "filter_by_monthinterval",
            "comment": "discard by not matching interval in months, optional offset from initial column value",
            "keep": false,
            "interval": {
                "column": "SOMEDATE",
                "format": "d.m.Y",
                "interval": 6,
                "offset": 0
            }
        },
        {
            "apply": "filter_by_rand",
            "comment": "keep some random rows",
            "keep": true,
            "data": {
                "columns": {
                    "SOMEFILTERCOLUMN", "hasvalue"
                },
                "amount": 10
            }
        }
    ],
    "modify":{
        "add":{
            "NEWCOLUMNNAME": "string",
            "ANOTHERCOLUMNNAME" : ["PRICE", "*1.5"]
        },
        "replace":[
            ["NAME", "regex", "replacement"],
            [null, ";", ","]
        ],
        "remove": ["SOMEFILTERCOLUMN", "DEATH"],
        "rewrite":[
            {"Customer": ["CUSTOMERID", " separator ", "NAME"]}
        ],
        "translate":{
            "DEPARTMENT": "departments"
        },
        "conditional_and":[
            ["NEWCOLUMNNAME", "anotherstring", ["SOMECOLUMN", "regex"], ["SOMEOTHERCOLUMN", "regex"]]
        ],
        "conditional_or":[
            ["NEWCOLUMNNAME", "anotherstring", ["SOMECOLUMN", "regex"], ["SOMEOTHERCOLUMN", "regex"]]
        ]

    },
    "split":{
        "DEPARTMENT": "(.*)",
        "DELIVERED": "(?:\\d\\d\\.\\d\\d.)(\\d+)"
    },
    "evaluate": {
        "EMAIL": "^((?!@).)*$"
    }
	"translations":{
		"departments":{
			"1": "Central",
			"2": "Department 1",
			"3": "Department 2",
			"4": "Office"
		}
	}
}
```

RegEx-Muster werden unabhängig von der Groß-/Kleinschreibung verarbeitet, es ist jedoch zu beachten, dass dies nur für a-z gilt. Wenn nach `verlängerung` gesucht wird, muss das Muster `verl(?:ä|Ä)ngerung` lauten. Die Zeichencodierung löst dies zu `verl(?:Ã¤|Ã„)ngerung` auf und verfehlt daher die Gruppierung `[äÄ]` die zu `[Ã¤Ã„]` aufgelöst wird.

[Übersicht](#übersicht)

# Löschung von Aufzeichnungen
Aufzeichnungen soll eine Aufbewahrungsfrist zugeordnet werden. Anwendbare Fristen müssen innerhalb der [Sprachdateien](#anpassung) (`record.lifespan.years`) festgelegt werden. Die verfügbaren Optionen sollten an die jeweiligen regulatorischen Umstände und im Einklang mit den festgelegten Prozessen angepasst werden.

Aufbewahrungsfristen sollen durch die Nutzer zugeordnet werden, welche auch für die Anpassung des Vorgangsstatus berechtigt sind. Sofern nicht vorher geschehen, wird regelmäßig an die Angabe der Frist erinnert, sobald ein Vorgang als abgeschlossen markiert wurde. Abgeschlossene Aufzeichnungen, deren letzer Beitrag die Aufbewahrungsfrist überschreitet, werden automatisch und ohne weitere Benachrichtigung gelöscht. Dies schließt auch angehängte Dateien, sowie Bestellungen und deren Anhänge, welche beispielsweise den Identifikator als Kommissionsangabe beinhalten, mit ein.

Aufzeichnungen können aus Gründen der Revisionssicherheit nicht anders gelöscht werden. Gesuchen zur Löschung persönlicher Daten vor Ablauf der Aufbewahrungsfrist steht das berechtigte Interesse der Revisionssicherheit und übliche Aufbewahrungsfristen medizinischer Aufzeichnungen entgegen.

[Übersicht](#übersicht)

# Vorgesehene regulatorische Zielsetzungen
Abgesehen von der Anwendungsarchitektur muss das Qualitätsmanagementsystem selbst aufgestellt werden. Die meisten regulatorischen Anforderungen werden durch Dokumente erfüllt. Auf diese Weise wird eine zuverlässige Versionskontrolle und Freigabe, sowie eine Prüfung der Erfüllung der Anforderungen innerhalb des [Regulatorische Auswertungen und Zusammenfassungen-Moduls](#regulatorische-auswertungen-und-zusammenfassungen) sichergestellt.

Anwendungsunterstützung Legende:
* ja: die Anwendung unterstützt alle Anforderungen des Kapitels
* teilweise: die Anwendung bietet Funktionen um Teilen der Anforderungen des Kapitels zu entsprechen
* strukturell: die Anforderungen können durch entsprechende Dokumente erfüllt werden

| Regulatorische Anforderung | Anwendungs-unterstützung | Methode | Verweis |
| ---- | ---- | ---- | ---- |
| ISO 13485 4.1.1 Allgemeine Anforderungen an das Qualitäts-managementsystem | teilweise, strukturell | &bull; Die Erfüllung regulatorischer Anforderungen kann gegengeprüft werden, sofern Dokumenten die regulatorischen Zusammenhänge zugeordnet wurden.<br/>&bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | [Dokumente](#dokumente), [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen) |
| ISO 13485 4.1.2 Prozessfestlegung | teilweise, strukturell | &bull; Die Anwendung hat ein Modul für die Risikoanalyse um Risken zu erfassen, zu bewerten und Maßnahmen zu beschreiben.<br/>&bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | [Risikomanagement](#risikomanagement) |
| ISO 13485 4.1.3 Prozessüberwachung | teilweise, strukturell | &bull; Nutzerrollen und Schulungen<br/>&bull; Verantwortlichkeiten<br/>&bull; Verbesserungsvorschläge<br/>&bull; Aufzeichnungen<br/>&bull; Dokumentenlenkung<br/>&bull; Interne Audits<br/>&bull; Managementbewertung<br/>&bull; Beschaffung<br/>&bull; Lieferantenbewertung<br/>&bull; Checkliste über regulatorische Erfüllung<br/>&bull; Bestellstatistiken&bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Allgemeine Dokumentation"-Kontext* | [Nutzer](#nutzer), [Verantwortlichkeiten](#verantwortlichkeiten), [Verbesserungsvorschläge](#verbesserungsvorschläge), [Aufzeichnungen](#aufzeichnungen-1), [Dokumente](#dokumente), [Audit](#audit), [Managementbericht](#managementbericht), [Bestellung](#bestellung), [Lieferanten- und Artikelverwaltung](#lieferanten--und-artikelverwaltung), [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen) | 
| ISO 13485 4.1.4 Prozesslenkung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 4.1.5 Ausgegliederte Prozesse | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 4.1.6 Validierung der Anwendung von Computersoftware | strukturell | &bull; Entsprechende Dokumente erfordern einen Identifikator, ähnlich Versorgungsdokumentationen. Software kann ebenfalls einen individuellen Identifikator zugeteilt bekommen.<br/>&bull; Computersoftware und deren Versionsaufzeichnungen können ebenfalls als Arbeitsmittel (7.6) betrachtet werden.<br/>&bull; *Aufzeichnung über Dokumente mit "Überwachung von Arbeitsmitteln"-Kontext* | |
| ISO 13485 4.2.1 Allgemeine Anforderungen an Dokumentation | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 4.2.2 Qualitäts-managementhandbuch | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 4.2.3 Medizinproduktakte | teilweise | &bull; Alle Aufzeichnungen für Versorgungen laufen zusammen. Exporte beinhalten diese Daten und erreichen damit eine vollständige Dokumentation der Maßnahmen.<br/>&bull; Aufzeichnungen für Versorgungen erfordern einen Identifikator um eine Zuordnung sicherzustellen. | [Aufzeichnungen](#aufzeichnungen) |
| ISO 13485 4.2.4 Lenkung von Dokumenten | ja | &bull; Die Anwendung ermöglicht es wiederverwendbare Dokumente und deren Komponenten zu gestalten.<br/>&bull; Nur die jüngsten freigegebenen Komponenten und Dokumente sind für Nutzer erreichbar [so lange eine Netzwerkverbindung besteht](#handhabe-der-netzwerkverbindung).<br/>&bull; Die Erstellung von Komponenten, Dokumenten, Dokumenten-Paketen und Textvorschlägen ist nur berechtigten Nutzern vorbehalten.<br/>&bull; Dokumente und deren Komponenten müssen von [berechtigten Nutzern](#nutzer) freigegeben werden. Die jeweiligen Nutzergruppen werden mit dem Speichern neuer Elemente vom System benachrichtigt. Jedes Mitglied der Gruppen kann die Freigabe erteilen, da davon ausgegangen wird, dass sich diese ihrer Verantwortung bewusst sind. Eine eingeschränkte nutzerspezifische Zuordnung wird hinsichtlich einer Wiederverwendung von Elementen vermieden. Nicht freigegebene Komponenten werden nicht angezeigt, selbst wenn das Dokument selbst freigegeben wurde.<br/>&bull; Neue Dokumente, deren Komponenten, Dokumenten-Pakete und Textvorschläge werden als neuer Eintrag in die Datenbank gelegt. Jeder Eintrag erhält dabei einen Zeitstempel und die Angabe des erstellenden Nutzers. Innerhalb der jeweiligen Verwaltung wird primär eine der jüngsten freigegebenen Versionen ausgewählt. Eine erweiterte Auswahl ermöglicht aber den Zugriff auf eine beliebige vorherige Version. Komponenten und Dokumente können nach einer vollständigen Freigabe nicht mehr gelöscht werden. Nicht freigegebene Komponenten und Dokumente sind für eine Verwendung nicht erreichbar.<br/>&bull; Bilder für Komponenten können nach einer Freigabe nicht mehr gelöscht werden. Ihrem Dateinamen wird der Name der Komponenten und ein Zeitstempel beigefügt. Sie sind dauerhaft auch für ältere Versionen verfügbar, können aber nicht wiederverwendet werden, da sie fester Bestandteil der jeweiligen Komponente sind.<br/>&bull; Dokumente können primär nur von berechtigten Nutzern blanco oder vorausgefüllt exportiert werden um eine Verbreitung veralteter Versionsstände zu vermeiden. Ersteller der Dokumente können jedoch eine allgemeine Erlaubnis erteilen.<br/>&bull; Dokumente können von authorisierten Nutzern zu jedem Gültigkeitstag nachgebildet werden um Unterschiede identifizieren zu können.<br/>&bull; Externe Dokumente werden gelenkt und erhalten die Angabe der Einrichtung, des regulatorischen Zusammenhangs, einer möglichen Außerbetriebnahme und des jeweils letzten bearbeitenden Mitarbeiters. | [Dokumente](#dokumente), [Dateien](#dateien) |
| ISO 13485 4.2.5 Lenkung von Aufzeichnungen | ja | &bull; Aufzeichnungen laufen zusammen und sind innerhalb der Anwendung nicht löschbar. Jeder Eintrag erhält einen Zeitstempel und den Namen des übermittelnden Nutzers. Zusammenfassungen führen alle Daten zusammen und stellen sie in der Reihenfolge ihrer Übermittlung dar.<br/>&bull; Bilder und Dateien für Aufzeichnungen werden nicht gelöscht. Ihren Dateinamen wird der Identifikator und Zeitstempel der Übermittlung beigefügt.<br/>&bull; Es werden nicht nur Aufzeichnungen selbst, sondern auch mögliche Änderungen am Aufzeichnungstyp gespeichert.<br/>&bull; Aufzeichnungen können jederzeit exportiert werden falls eine zusätzliche Revisionssicherheit gewünscht wird oder die Daten mit dritten Parteien geteilt werden müssen.<br/>&bull; Der Zugriff auf die Inhalte der Anwendung inklusive vertraulicher personenbezogener Patientendaten erfordert eine persönliche Anmeldung registrierter Nutzer.<br/>&bull; Aufzeichnungen soll eine Aufbewahrungsfrist zugeordnet werden nach deren Ablauf abgeschlossene Aufzeichnungen gelöscht werden um datenschutzrechtlichen Bestimmungen zu entsprechen | [Nutzer](#nutzer), [Aufzeichnungen](#aufzeichnungen), [Löschung von Aufzeichnungen](#löschung-von-aufzeichnungen) |
| ISO 13485 5.1 Verantwortung der Leitung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 5.2 Kundenorientierung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 5.3 Qualitätspolitik | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 5.4.1 Qualitätsziele | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 5.4.2 Planung des Qualitäts-managementsystems | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 5.5.1 Verantwortung und Befugnis | ja | &bull; Nutzern werden [Berechtigungen](#nutzer) erteilt, die spezielle Zugriffe ausdrücklich erlauben oder das Menü vereinfachen.<br/>&bull; Berechtigungen definieren den Zugriff auf Funktionen der Anwendung.<br/>&bull; Nutzer können eine PIN erhalten um Bestellungen freizugeben.<br/>&bull; Das Mitarbeiterverzeichnis listet alle Nutzer auch gruppiert nach organisatorischen Bereichen und Berechtigungen auf.<br/>&bull; Verantwortlichkeiten können festgelegt werden und sind öffentlich zugänglich | [Nutzer](#nutzer), [Mitarbeiterverzeichnis](#mitarbeiterverzeichnis), [Verantwortlichkeiten](#verantwortlichkeiten), [Laufzeitvariablen](#laufzeitvariablen) |
| ISO 13485 5.5.2 Beauftragter der Leitung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 5.5.3 Interne Kommunikation | ja, strukturell | &bull; Die Anwendung hat einen internen [Nachrichtendienst](#unterhaltungen). Dieser Dienst wird von internen Modulen genutzt um eine zielführende Datenübermittlung sicherzustellen, z.B. für die Benachrichtigung von Nutzergruppen für die Freigabe von Dokumenten und deren Komponenten, die Benachrichtigung von Nutzergruppen über zurückgewiesene Bestellungen und geänderten Bestellstati, Benachrichtigungen zu zusätzlich erforderlichen Rechechen zu einer Bestellung an den Besteller, Benachrichtigung von Nutzergruppen zu geplanten Ereignissen, Benachrichtigungen über länger unbearbeitete Vorgänge<br/>&bull; Die Anwendung hat die Möglichkeit allgemeine oder bereichsbezogene Ankündigungen auf der Startseite einzublenden<br/>&bull; Die Anwendung hat einen integrierten Kalender. Dieser soll die Aufgabenplanung und Bearbeitung zeitkritischer wiederkehrender Ereignisse wie Kalibrationsmaßnahmen und dergleichen unterstützen.<br/>&bull; Die Anwendung hat ein Bestellmodul. Bestellungen können vorbereitet und freigegeben werden. Der Einkauf hat alle erforderlichen Daten aus der Lieferantenpreisliste vorliegen um die Bestellung bearbeiten zu können; die Markierung des Bestellstatus erlaubt eine zeitnahe Rückmeldung an den Besteller.<br/>&bull; Die Anwendung hat einen Sharepoint für Dateien und einen STL-Betrachter für 3D-Modelle um einfach Informationen austauschen zu können, welche die Möglichkeiten des Nachrichtendienstes übersteigen.<br/>&bull; Die Oberfläche informiert über neue Nachrichten, freigegebene neue Bestellungen (Einkauf) und unerledigte Kalenderereignisse. Die Startseite zeigt zudem eine kurze Zusammenfassung offener Versorgungsfälle und geplanter Ereignisse der aktuellen Kalenderwoche sowie unerledigter Ereignisse.<br/>&bull; Dokumente können auf andere Dokumente verweisen. Dabei können diese nur angezeigt werden (z.B. Verfahrens- oder Arbeitsanweisungen) oder mit Übernahme des Identifikators für einen reibungslosen Transfer sorgen.<br/>&bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | [Unterhaltungen](#unterhaltungen), [Ankündigungen](#ankündigungen), [Kalender](#kalender), [Bestellung](#bestellung), [Dateien](#dateien), [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen) |
| ISO 13485 5.6.1 Allgemeine Anforderung an Management-bewertung | teilweise | &bull; Die Anwendung beinhaltete ein Formular für die Erstellung, Bearbeitung und den Abschluss eines Managementbericht, welches standardmäßig alle erforderlichen Themen beinhaltet. | [Managementbericht](#managementbericht), [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen) |
| ISO 13485 5.6.2 Eingaben für die Bewertung | ja | &bull; Alle erforderlichen Themen werden angezeigt und können / sollten kommentiert werden | [Laufzeitvariablen](#laufzeitvariablen) |
| ISO 13485 5.6.3 Ergebnisse der Bewertung | ja | &bull; Alle erforderlichen Themen werden angezeigt und können / sollten kommentiert werden | [Laufzeitvariablen](#laufzeitvariablen) |
| ISO 13485 6.1 Bereitstellung von Ressourcen | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 6.2 Personelle Ressourcen | ja, strukturell | &bull; Der Liste der gewünschten Fähigkeiten sollen die für das Unternehmen angemessenen Punkte [hinzugefügt](#anpassung) werden um eine zielführende Übersicht über die Erfüllung zu ermöglichen.<br/>&bull; innerhalb der Nutzerverwaltung können Schulungen geplant, deren Ablaufdaten, Erfahrungspunkte und anhängende Dokumente hinzugefügt werden.<br/>&bull; Nutzern können Fähigkeiten und deren Niveau gemäß der bestimmten für das Unternehmen [erforderlichen Fähigkeiten](#anpassung) (Aufgabenmatrix) zugeordnet werden.<br/>&bull; Eine Übersicht über die Schulungen und Fähigkeiten ist einsehbar.<br/>&bull; Fähigkeiten und Schulungen können von berechtigen Nutzern gelöscht werden. Eine Übersicht kann exportiert werden.<br/>&bull; Schulungen können durch berechtigte Nutzer durch ein eigenes Dokument bewertet werden. Fällige Bewertungen werden in den Kalender eingetragen.<br/>&bull ablaufende Schulungen werden automatisch als Folgeschulungen geplant, Nutzer und Bereichsleiter zusätzlich per Nachricht informiert<br/>&bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | [Nutzer](#nutzer), [Anpassung](#anpassung), [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen), [Schulungen](#schulungen) |
| ISO 13485 6.3 Infrastruktur | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Allgemeine Dokumentation"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Überwachung von Arbeitsmitteln"-Kontext* | |
| ISO 13485 6.4.1 Arbeitsumgebung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 6.4.2 Lenkung der Kontamination | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 7.1 Planung der Produktrealisierung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"-Kontext* | |
| ISO 13485 7.2.1 Ermittlung der Anforderungen bzgl. des Produkts | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"-Kontext* | |
| ISO 13485 7.2.2 Bewertung der Anforderungen bzgl. des Produkts | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"-Kontext* | |
| ISO 13485 7.2.3 Kommunikation mit Kunden | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"-Kontext* | |
| ISO 13485 7.3.1 Allgemeine Anforderungen an Entwicklung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 7.3.2 Entwicklungsplanung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"- oder Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 7.3.3 Entwicklungseingaben | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"- oder Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 7.3.4 Entwicklungsergebnisse | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"- oder Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 7.3.5 Entwicklungsbewertung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"- oder Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 7.3.6 Entwicklungs-verifizierung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"- oder Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 7.3.7 Entwicklungsvalidierung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"- oder Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 7.3.8 Übertragung der Entwicklung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"- oder Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 7.3.9 Lenkung von Entwicklungs-änderungen | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"- oder Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 7.3.10 Entwicklungsakten | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"- oder Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 7.4.1 Beschaffungsprozess | ja, strukturell | &bull; Die Beschaffung wird durch die Anwendung begleitet. Lieferanten und Produkte können zur Datenbank hinzugefügt werden.<br/>&bull; Eine Lieferantenbewertung ist in der Lieferantenverwaltung durch einschlägige Dokumente entsprechenden Kontexts implementiert und wird durch die Bereitstellung von reduzierten Daten der Bestellungen in Bezug auf Lieferzeiten, Stornos und Rücksendungen unterstützt.<br/>&bull; Lieferanteneinträge können durch Dokumente, Zertifikate und deren Gültigkeitsdaten ergänzt werden. Die Gültigkeit von Zertifikaten können angezeigt und exportiert werden. Lieferanten können als inaktiv markiert, jedoch nicht gelöscht werden. Produkte, die deaktiviert werden sind über das Bestellmodul nicht erreichbar.<br/>&bull; Produkte können mit Dokumenten ergänzt werden, welche nicht gelöscht werden. Den Dateinamen werden der Lieferantenname, der Zeitstempel der Übermittlung und die Artikelnummer angefügt.<br/>&bull; Produkte sollen eingeführt werden. Produkteinführungen können durch berechtigte Nutzer freigegeben, verwehrt oder entzogen werden. Alle Nutzer (außer Gruppen) sammeln zuvor die erforderlichen Informationen. Produkteinführungen werden durch eigene Dokumente mit dem entsprechenden Kontext umgesetzt.<br/>&bull; Produkte werden im Falle einer Preislistenaktualisierung automatisch gelöscht, es sei denn es fand eine Produkteinführung statt, es wurde eine Stichprobenprüfung durchgeführt, es wurde ein Dokument beigefügt, es wurde ein Alias festgelegt, es wurde schon einmal bestellt<br/>&bull; Änderungen an Produkteinträgen ist nur für berechtigte Nutzer möglich.<br/>&bull; Es können Textvorschläge für den Einkauf erstellt werden um Nachrichten zur Anforderung regulatorischer Unterlagen für Produkte mit besonderer Aufmerksamkeit oder eines erneuerten Zertifikats vorzubereiten.<br/>&bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Bereitstellung von Dokumenten mit "Lieferantenbewertung"-Kontext* | [Lieferanten- und Artikelverwaltung](#lieferanten--und-artikelverwaltung), [Bestellung](#bestellung), [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen) |
| ISO 13485 7.4.2 Beschaffungsangaben | teilweise | &bull; Bestellungen nutzen bevorzugt die Angaben der herstellereigenen Preislisten.<br/>&bull; Aufzeichnungen über Bestellungen muss jedoch über eine Drittsoftware (ERP) umgesetzt werden. | [Lieferanten- und Artikelverwaltung](#lieferanten--und-artikelverwaltung), [Bestellung](#bestellung) |
| ISO 13485 7.4.3 Verifizierung von beschafften Produkten | ja, strukturell | &bull; Die Stichprobenprüfung nach MDR §14 erbittet eine Prüfung für jedes [als Handelsware definierte](#importierung-von-lieferantenpreislisten) Produkt, sofern die letzte zurückliegende Prüfung innerhalb des Sortiments dieses Lieferanten die mdr14_sample_interval-Zeitspanne überschreitet, z.B. einmal jährlich. Dies betrifft alle Produkte welche nicht innerhalb der mdr14_sample_reusable-Zeitspanne liegen, welche ebenfalls für jeden Lieferanten individuell festgelegt werden kann, wenn es das Sortiment erfordert. Beide Werte erhalten durch die [config.ini](#laufzeitvariablen) einen Standardwert.<br/>&bull; Stichprobenprüfungen werden durch eigene Dokumente mit dem entsprechenden Kontext umgesetzt. Alle Nutzer (außer Gruppen) sammeln die erforderlichen Informationen.<br/>&bull; Stichprobenprüfungen können durch berechtigte Nutzer zurückgezogen werden.<br/>&bull; *Bereitstellung von Dokumenten mit "MDR §14 Stichprobenprüfung"- und "Produkteinführung"-Kontext* | [Lieferanten- und Artikelverwaltung](#lieferanten--und-artikelverwaltung), [Bestellung](#bestellung) |
| ISO 13485 7.5.1 Lenkung der Produktion und Dienstleistungs-erbringung | teilweise, strukturell | &bull; Entsprechende Dokumente zeichnen die Schritte der Fertigung auf. Mit dem Zugriff auf die Dokumentationen ist der aktuelle Status erkennbar. Ist beispielsweise eine Aufzeichnung für einen Fertigungsabschnitt vorhanden, bei dem die Arbeitsschritte festgelegt werden, kann das Dokument auch ein Auswahlfeld für die Erledigung beinhalten. In einem ersten Dokumentationsschritt können die Schritte festgelegt werden, in einem folgenden kann das Dokument erneut verwendet und das Auswahlfeld markiert werden. Damit wird der Zeitpunkt und eintragende Nutzer aufgezeichnet.<br/>&bull; Dokumenten-Kontexte erlauben eine Zuordnung als Verfahrens- oder Arbeitsanweisungen.<br/>&bull; Der integrierte Kalender unterstützt bei der Planung von Arbeiten. | [Dokumente](#dokumente), [Aufzeichnungen](#aufzeichnungen-1), [Kalender](#kalender) |
| ISO 13485 7.5.2 Sauberkeit von Produkten | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 7.5.3 Tätigkeiten bei der Installation | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"- oder Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 7.5.4 Tätigkeiten zur Instandhaltung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"- oder Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 7.5.5 Anforderungen für sterile Medizinprodukte | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 7.5.6 Validierung der Prozesse zur Produktion und Dienstleistung-erbringung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"- oder Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 7.5.7 Validierung von Sterilisations-prozessen | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"- oder Allgemeine Dokumentation"-Kontext* | |
| ISO 13485 7.5.8 Identifizierung | teilweise | &bull; Aufzeichnungen erfordern einen Identifikator. Dieser Identifikator ist derzeit als QR-Code implementiert, welcher exportiert und ausgedruckt, sowie mit dem integrierten Scanner ausgelesen werden kann. Aufkleberbögen mit dem Code können auch zur Kennzeichnung von Produkten und Komponenten während der Herstellung verwendet werden. | [Aufzeichnungen](#aufzeichnungen-1) |
| ISO 13485 7.5.9 Rückverfolgbarkeit | teilweise | &bull; Mehrfach-Scannerfelder innerhalb der Vorgangsdokumentation ermöglichen eine Rückverfolgung eingesetzter Waren<br/>Voraussetzung sind entweder<br/>&bull; in der ERP-Software generierte Codes die eine Produktzuordnung ermöglichen, oder<br/>&bull; die Erstellung von Labeln für Artikel in der Bestellverwaltung | [Dokumente](#dokumente), [Bestellung](#bestellung)|
| ISO 13485 7.5.10 Eigentum des Kunden | | | |
| ISO 13485 7.5.11 Produkterhaltung | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 7.6 Lenkung von Überwachungs- und Messmitteln | teilweise | &bull; Entsprechende Dokumente erfordern einen Identifikator, ähnlich Versorgungsdokumentationen. Messmittel können ebenfalls einen individuellen Identifikator zugeteilt bekommen.<br/>&bull; Eine Berücksichtigung eines Kalendereintrags innerhalb dieser Dokumente kann dabei unterstützen zukünftige Ereignisse zu planen und Mitarbeiter zu informieren. | [Dokumente](#dokumente), [Aufzeichnungen](#aufzeichnungen-1), [Kalender](#kalender) |
| ISO 13485 8.1 Allgemeine Überwachungs-, Mess-, Analyse- und Verbesserungsprozesse | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 8.2.1 Rückmeldungen | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 8.2.2 Reklamations-bearbeitung | teilweise | &bull; Aufzeichnungen können eine Stellungnahme erfordern ob sie in Zusammenhang mit einer Reklamation erfolgen. Betroffene Aufzeichnungen werden in der Übersicht markiert und der Zeitstempel der jeweiligen Einträge um einen entsprechenden Kommentar ergänzt. Eine Übersicht kann angezeigt werden.<br/>&bull; Das als abgeschlossen Kennzeichnen von Aufzeichnungen, die eine Reklamation beinhalten erfordert eine Aktion aller definierter Rollen.<br/>&bull; Reklamationen beinhalten die Möglichkeit Schulungen zu planen. | [Aufzeichnungen](#aufzeichnungen-1), [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen) |
| ISO 13485 8.2.3 Berichterstattung an Regulierungsbehörden | strukturell | &bull; Innerhalb der Dokumente für die Vorkommnisaufzeichnungen können Links z.B. zu den [IMDRF Adverse Event Terminology Web Browsers](https://www.imdrf.org/working-groups/adverse-event-terminology) eingefügt werden um die erforderlichen Codes zu finden.<br/>&bull; *Aufzeichnung über Dokumente mit "Vorkommnis"-Kontext* | |
| ISO 13485 8.2.4 Internes Audit | teilweise | Interne Audits können vorbereitet, geplant und durchgeführt werden.<br/>Es können Anwendungsdaten zusammengefasst und exportiert werden u.a. für<br/>&bull; Aufzeichnungen über Produkteinführungen. Sofern aktuell bestellte Artikel nicht berücksichtigt sind erfolgt ein Hinweis.<br/>&bull; Aufzeichnungen über Stichprobenprüfungen. Sofern aktuell Lieferanten für eine Prüfung fällig sind erfolgt ein Hinweis.<br/>&bull; eine Übersicht über die aktuell gültigen Dokumente und deren Komponenten.<br/>&bull; Fähigkeiten und Schulungen der Mitarbeiter mit ggf. markierten Ablaufdaten.<br/>&bull; Lieferantenlisten mit den jeweiligen letzten Preislistenaktualisierungen, der letzten Stichprobenprüfung und Details zu Zertifikaten sofern bereitgestellt.<br/>&bull; Bestellstatistiken.<br/>&bull; Reklamationen.<br/>&bull; die Berücksichtung regulatorischer Anforderungen durch verwendete Dokumente und Dokumente.<br/>&bull; Risikoanalysen. | [Audit](#audit), [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen) |
| ISO 13485 8.2.5 Überwachung und Messung von Prozessen | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 8.2.6 Überwachung und Messung des Produkts | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | [Dokumente](#dokumente), [Aufzeichnungen](#aufzeichnungen-1) |
| ISO 13485 8.3.1 Lenkung nichtkonformer Produkte | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 8.3.2 Maßnahmen als Reaktion auf vor der Auslieferung festgestellte nichtkonforme Produkte | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 8.3.3 Maßnahmen als Reaktion auf nach der Auslieferung festgestellte nichtkonforme Produkte | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | |
| ISO 13485 8.3.4 Nacharbeit | ja | &bull; Aufzeichnungen erfordern eine Stellungnahme ob sie in Zusammenhang mit einer Nacharbeit erfolgen. Dokumente unterscheiden sich jedoch primär nicht, daher folgt die Dokumentation von Maßnahmen den selben Aufzeichnungsprozessen wie eine übliche Versorgungsdokumentation. | [Aufzeichnungen](#aufzeichnungen-1) |
| ISO 13485 8.4 Datenanalyse | teilweise | &bull; Eine Lieferantenbewertung wird durch die Bereitstellung von reduzierten Daten der Bestellungen in Bezug auf Lieferzeiten, Stornos und Rücksendungen unterstützt. Es besteht jedoch ein individueller Interpretationsspielraum der bereitgestellten Daten.<br/>&bull; Lieferantenbewertungen und interne Audits sind verfügbar | [Bestellung](#bestellung), [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen) |
| ISO 13485 8.5.1 Verbesserung | teilweise, strukturell | &bull; Jeder registrierte Nutzer kann jederzeit Verbesserungsvorschläge unterbreiten.<br/>&bull; Die Mitarbeiterqualifikation wird laufend auf abgelaufene Zertifikate hin überwacht und sowohl an die Evaluierung, als auch an die Planung von Anschlussschulungen erinnert.<br/>&bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext* | [Verbesserungsvorschläge](#verbesserungsvorschläge), [Schulungen](#schulungen) |
| ISO 13485 8.5.2 Korrekturmaßnahmen | teilweise, strukturell | &bull; Aufzeichnungen müssen von berechtigten Nutzern abgeschlossen werden. Jeder Vorgang kann zu diesem Zeitpunkt oder zuvor zu einer Reklamation umgewandelt werden. Reklamationen an eigenen Produkten bedürfen in der Standardkonfiguration der Anwendung eines Abschlusses durch eine verantwortliche Person und einen Qualitätsmanagementbeauftragten, so dass spätestens hier strukturelle Fehler aufgedeckt und adressiert werden können.<br/>&bull; Eine Übersicht über laufende Reklamationen kann von berechtigten Nutzern eingesehen werden.<br/>&bull; Festgelegte kritische Rücksendegründe für eingekaufte Waren leiten einen neuen Einführungsprozess ein, der in der Standardkonfiguration von qualitäts- und sicherheitsrelvanten Rollen abgeschlossen werden muss.<br/>&bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorkommnis"-Kontext* | [Aufzeichnungen](#aufzeichnungen-1), [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen), [Bestellung](#bestellung) |
| ISO 13485 8.5.3 Vorbeugungsmaßnahmen | strukturell | &bull; *Beschreibung über Dokumente mit "Verfahrens- oder Arbeitsanweisung"-Kontext*<br/>&bull; *Aufzeichnung über Dokumente mit "Vorkommnis"-Kontext* | |
| ISO 14971 4.5 Risikomanagementakte | teilweise | &bull; Die Anwendung hat ein Modul für die Risikoanalyse um Risken zu erfassen, zu bewerten, Maßnahmen und Restrisiken zu beschreiben .<br/>&bull; Beispiele von Ereignissen und Umständen gemäß Anhang C und den Empfehlungen der [DGIHV](https://www.dgihv.org) ist für die Nutzung standardmäßig verbereitet. <br/>&bull; Risiken können nicht gelöscht aber die Anwendbarkeit entzogen werden.<br/>&bull; Änderungen können nur von autorisierten Nutzern vorgenommen, aber von allen Nutzern eingesehen werden.<br/>&bull; Eigenschaften von Medizinprodukten und Risiken können in jeder verfügbaren Fassung exportiert werden. | [Risikomanagement](#risikomanagement), [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen) |
| ISO 14971 5 Risikoanalyse | ja | &bull; Beispiele von Ereignissen und Umständen gemäß Anhang C und den Empfehlungen der [DGIHV](https://www.dgihv.org) ist für die Nutzung standardmäßig verbereitet. | [Risikomanagement](#risikomanagement), [Sprachdateien](#anpassung), [Beispieldateien](#anwendungseinrichtung) |
| ISO 14971 6 Risikobewertung | ja | &bull; Die Anforderungen werden durch die Beispieldateien erfüllt. | [Risikomanagement](#risikomanagement), [Beispieldateien](#anwendungseinrichtung) |
| ISO 14971 7 Riskobeherrschung | ja | &bull; Die Anforderungen werden durch die Beispieldateien erfüllt. | [Risikomanagement](#risikomanagement), [Beispieldateien](#anwendungseinrichtung) |
| ISO 14971 7.6 Vollständigkeit der Risikobeherrschung | ja | &bull; Es können Probleme in Bezug auf Risiken ohne definierte Eigenschaften und anders herum angezeigt werden. | [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen) |
| ISO 19011 5.2 Festlegen der Auditprogrammziele | teilweise | &bull; Es kann ein Programm für interne Audits vorbereitet werden, welches auch die Auditziele und Zusammenfassungen vorausgegangener Audits beinhaltet. | [Audit](#audit) |
| ISO 19011 5.4 Festlegen des Auditprogramms | teilweise | &bull; Es kann ein Programm für interne Audits vorbereitet werden, welches auch die Auditziele und Zusammenfassungen vorausgegangener Audits beinhaltet. | [Audit](#audit) |
| ISO 19011 5.5 Umsetzen des Auditprogramms | ja | &bull; Vorbereitete Audits können dem Kalender hinzugefügt werden und den jeweiligen Bereich addressieren. | [Audit](#audit), [Kalender](#kalender) |
| ISO 19011 5.5.7 Verwalten und Aufrechterhalten von Aufzeichnungen zu Auditprogrammen | ja | &bull; Audits beinhalten alle Daten des jeweiligen Programms welche nach Abschluss nur noch gelesen werden können. | [Audit](#audit), [Regulatorische Auswertungen und Zusammenfassungen](#regulatorische-auswertungen-und-zusammenfassungen) |
| ISO 19011 5.7 Überprüfen und Verbessern des Auditprogramms | teilweise | &bull; Bei der Erstellung oder Bearbeitung von Auditprogrammen können die Zusammenfassungen vorausgehender Audits importiert werden um bei der Planung Berücksichtigung zu finden. | [Audit](#audit) |
| ISO 19011 6.4 Durchführen von Audittätigkeiten | teilweise | &bull; Die Bearbeitung und Abschlussbewertung ist jederzeit möglich, so lange das Audit noch nicht als abgeschlossen markiert wurde. | [Audit](#audit) |
| ISO 19011 6.5 Erstellen und Verteilen des Auditberichts | teilweise | &bull; Der Auditbericht ist eine fest integrierte Eingabe die am Ende des Audits und vor dessen Abschluss vorgesehen ist.<br/>&bull; Die Verteilung des Berichts wird bei Abschluss durch eine systeminterne Nachricht an alle Nutzer mit `regulatory`-Berechtigung und alle Mitglieder des auditierten Bereichs umgesetzt. | [Audit](#audit), [Laufzeitvariablen](#laufzeitvariablen), [Unterhaltungen](#unterhaltungen) |
| ISO 19011 6.6 Abschließen des Audits | teilweise | &bull; Die Bearbeitung und Abschlussbewertung ist jederzeit möglich, so lange das Audit noch nicht als abgeschlossen markiert wurde. | [Audit](#audit) |
| MPDG §83 Medizinprodukteberater | ja | &bull; Medizinprodukteberater werden durch die entsprechende Berechtigung in den Nutzereinstellungen festgelegt und als solche im Mitarbeiterverzeichnis angezeigt. | [Nutzer](#nutzer) |
| SGB 5 §33 Zusätzliche Kosten | strukturell | &bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"-Kontext* | |
| MDR Art. 14 Stichprobenprüfung | ja, teilweise | &bull; Eine Stichprobenprüfung ist implementiert. Ein entsprechendes Dokument muss erstellt werden, in Frage kommende Artikel geben sich bei Bestellung zu erkennen. | [Lieferanten- und Artikelverwaltung](#lieferanten--und-artikelverwaltung), [Bestellung](#bestellung), [Dokumente](#dokumente), [Importierung von Lieferantenpreislisten](#importierung-von-lieferantenpreislisten) |
| MDR Art. 61 Klinische Bewertung | strukturell | &bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"-Kontext* | |
| MDR Art. 83 Nachbeobachtung | teilweise | &bull; Die Überwachung nach dem Inverkehrbringen ist kein direkter Bestandteil der Anwendung. Die regulatorische Anforderung Patienten zur Hilfsmittelkontrolle einzubestellen ist nicht integriert, da eine konsequente Erfassung von Kontaktdaten die Arbeitsbelastung vergrößern würde und redundant wäre, da eine zusätzliche ERP-Software ohnehin erforderlich ist. Statt dessen können deren Datenexporte von Kundendaten genutzt und ein CSV-Filter mit individuellen Regeln erstellt werden um eine Liste passender Empfänger für Serienbriefe zu erhalten. Die Speicherung dieser Liste kann als Nachweis der Erfüllung der regulatorischen Anforderung genutzt werden. | [Werkzeuge](#werkzeuge), [CSV-Filter](#csv-prozessor) |
| MDR Anhang 1 Grundlegende Sicherheits- und Leistungsanforderungen | strukturell | &bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"-Kontext* | |
| MDR Anhang 4 EU-Konformitätserklärung | strukturell | &bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"-Kontext* | |
| MDR Anhang 13 Verfahren für Sonderanfertigungen | strukturell | &bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"-Kontext* | |
| MDR Anhang 14 Klinische Bewertung und klinische Nachbeobachtung nach dem Inverkehrbringen | strukturell | &bull; *Aufzeichnung über Dokumente mit "Vorgangsdokumentation"-Kontext* | |
| MPBetreibV | strukturell | &bull; *Aufzeichnung über Dokumente mit "Überwachung von Arbeitsmitteln"-Kontext* | |
| ArbSchG §3 / BAG Az. 1 ABR 22/21 | ja | &bull; Registrierte Nutzer können ihre Arbeitszeiten, Urlaube, Krankheitsausfälle, etc. erfassen. Die Einträge können unter Berücksichtigung von Arbeitszeitgesetzen als Dokumentation exportiert werden. | [Kalender](#kalender) |
| DSGVO Art.17 Recht auf Löschung | ja | &bull; Abgeschlossene Aufzeichnungen zu Medizinprodukten werden nach Ablauf der Aufbewahrungsfrist gelöscht | [Löschung von Aufzeichnungen](#löschung-von-aufzeichnungen) |
| Richtlinie (EU) 2019/882 Europäische Barrierefreiheitsanforderungen | ja | &bull; Die Anwendung berücksicht die Barrierefreiheitsanforderungen in Bezug auf [WCAG 2.1 Level AA](https://www.w3.org/TR/WCAG21/) | [Erklärung zur Barrierefreiheit](#erklärung-zur-barrierefreiheit) |

[Übersicht](#übersicht)

# Voraussetzungen
* Server mit
    * PHP >= 8.2
    * MySQL/MariaDB oder SQL Server (oder einer anderen Datenbanklösung, dann müssen die Abfragen angepasst/ergänzt werden)
    * SSL (Kamerazugriff für den Scanner, Service-Worker und SHA256-Verschlüsselung können sonst technisch nicht genutzt werden)
* Netzwerkzugriff für Endgeräte und einen Browser
    * Desktop PCs
    * mobile Geräte
    * bevorzugt Firefox, Edge oder einen anderen Chromium-Browser, [Safari ist nicht vollständig kompatibel](#safaris-besondere-bedürfnisse)
    * bestenfalls [keine Löschung der Browserdaten](#handhabe-der-netzwerkverbindung) (Cache, indexedDB) beim Beenden
    * Druckerzugang für alle Endgeräte
* Lieferantenpreislisten als CSV-Dateien ([siehe Details](#importierung-von-lieferantenpreislisten))
* Gelegentlich FTP-Zugang zum Server für Anpassungen der [Laufzeitvariablen](#laufzeitvariablen) und [Sprachdateien](#anpassung)

Getestete Serverumgebungen:
* Apache [Uniform Server Zero XV](https://uniformserver.com) mit PHP 8.2, MySQL 8.0.31 (bis 2024-05-30)
* Apache (nativ) mit PHP 8.2, MariaDB 15.1 (seit 2024-05-30)
* Microsoft IIS mit PHP 8.2, SQL Express (SQL Server 22)

Getestete Betriebssysteme, Browser und Geräte:
* Win 10 Edge 123
* Win 11 Firefox (bis 2024-05-30)
* Linux Mint 21.3 Firefox 133 (seit 2024-05-30)
* Android 12 Firefox 133
* macOS 13 Ventura [Safari 18](#safaris-besondere-bedürfnisse), Edge 131, Firefox 133
* iOS 18.4.1 [Safari](#safaris-special-needs)
* Opticon USB Barcode Reader L-46X (funktioniert am Bildschirm und auf Papier, CODE128 und QR gemäß Spezifikationen, aber scheinbar limitiert auf [ASCII](https://www.asciitable.com/) mit fehlerhafter Auflösung von Sonderzeichen (z.B. Umlaute) bei Standardinstallation an Win10)

Externe Scanner müssen 2D-Codes scannen und UTF-8 Zeichencodierung auswerten können.

Firefox, Edge und vermutlich jeder andere Chromium-Browser sowie Safari haben für Datenlisten bei Eingaben eine Vorschau, welche die Auswahl verfügbarer Optionen (z.B. Wahl von Nachrichtenempfängern) vereinfacht. Andere Browser wurden nicht getestet.

Während die Anwendung technisch betrachtet auf einem beliebigen Webserver funktioniert, können nicht alle Aspekte [erforderlicher Datenschutzanforderungen](#stellungnahme-zu-technischen-richtlinien-zur-datensicherheit) abgebildet werden, daher ist die Verwendung auf einem öffentlich zugänglichen Server ausdrücklich **nicht empfohlen**.

Es wird dringed empfohlen eine zusätzliche Entwicklungsumgebung zu schaffen um Änderungen und CSV-Filter testen zu können und sicherzustellen, dass die Anwendung in der Produktivumgebung stabil bleibt!

[Übersicht](#übersicht)

## Installation

### Servereinrichtung
* php.ini memory_limit ~4096M zur [Verarbeitung großer CSV-Dateien und dem Preislistenimport](#csv-prozessor), open_basedir zumindest für das lokale IIS für die Dateiverarbeitung deaktivieren.
    * [CSV Verarbeitung](#csv-prozessor) von 48mb @ 59k Zeilen mit diversen Filtern, incl. Dateifilter, beansprucht etwa 1.7GB Speicher
    * [CSV Verarbeitung](#importierung-von-lieferantenpreislisten) @ 100MB beansprucht etwa 2.3GB Speicher
* php.ini upload_max_filesize & post_max_size / applicationhost.config | web.config für IIS entsprechend der erwarteten Dateigrößen für z.B. Sharepoint und CSV-Dateien ~350MB. Für IIS sollte die [uploadReadAheadSize](#https://techcommunity.microsoft.com/blog/iis-support-blog/solution-for-%E2%80%9Crequest-entity-too-large%E2%80%9D-error/501134) entsprechend konfiguriert werden.
* php.ini max_input_time -1 für das Teilen großer Uploads mit max_execution_time, abhängig von der erwarteten Verbindungsgeschwindigkeit.
* php.ini max_execution_time / fastCGI timeout (iis) ~ 300 (5min) da die [CSV-Verarbeitung](#csv-prozessor) in Abhängigkeit des Datenaufkommens und jeweiliger Filter eine Weile dauern kann. Möglicherweise muss auch eine Anpassung des Prozessor-Timeouts im Anwendungspool von IIS und der [Session-Dauer](#laufzeitvariablen) vorgenommen werden.
    * Preislistenimport @ 220k Zeilen benötigt etwa 1 Minute mit Uniform Server, 1 Minute mit SQL Server
    * Preislistenimport @ 660k Zeilen benötigt aktuell etwa 2 Minuten mit Uniform Server, 3 Minuten mit SQL Server
    * der Preislistenimport benötigt mehr Zeit für die [Aktualisierung von Artikeln](#importierung-von-lieferantenpreislisten) als für das Löschen und Wiedereinfügen
* php.ini session.cookie_httponly = 1, session.cookie_secure = 1, session.use_strict_mode = 1
* optional php.ini session.gc_maxlifetime im Verhältnis zu [CONFIG[lifespan][session][idle]](#laufzeitvariablen)
* php.ini Aktivierung folgender Erweiterungen:
    * curl
    * fileinfo
    * gd
    * gettext
    * mbstring
    * exif
    * pdo_odbc
    * zip
    * php_pdo_sqlsrv_82_nts_x64.dll (sqlsrv sofern zutreffend)
* my.ini (MySQL) / mysql.conf.d/mysql.cnf (MariaDB) max_allowed_packet = 100M / [SQL SERVER](https://learn.microsoft.com/en-us/sql/database-engine/configure-windows/configure-the-network-packet-size-server-configuration-option?view=sql-server-ver16) 32767
* manuelle Konfiguration den MIME-Typs für das site-webmanifest als application/manifest+json für IIS Server.

### Anwendungseinrichtung
Der Standardumfang der Anwendung stellt [Vorlagen](https://github.com/erroronline1/caro/tree/master/templates) im entsprechenden *template*-Ordner bereit um eine schnelle Verfügbarkeit von Inhalten bei der Inbetriebnahme der Anwendung zu unterstützen. Dateinamen folgen dem Muster `{frei wählbar}.{Typ}.{Standardsprache}.{Dateinamenerweiterung} wobei
* der frei wählbare Teil alles sein kann, was die Struktur bei der Vorbereitung vereinfacht
* der Typ einer der folgenden ist
	* audits
	* csvfilter
	* documents
	* manuals
	* risks
	* texts
	* users
    * vendors
* die Standardsprache der Anwendung, wie sie in der  [Laufzeitvariablen](#laufzeitvariablen) festgelegt ist
* die Dateinamenerweiterung `.json` optional von `.env`-Dateien erweitert wird (siehe auch [Anpassung](#anpassung))
* die Standardautorin von Inhalten *CARO App* ist und diese in einen berechtigten Nutzer geändert werden sollte um Verwirrungen bei Auditoren zu vermeiden

Wenn die Inbetriebnahme der Anwendung mit den Vorlagen vorbereitet wird können mehrere Dateien eines Typs mit einem frei wählbaren Namensteil angelegt werden um die Übersicht zu behalten (vor dieser Designentscheidung hatten die Risiken 30.000 Zeilen, die noch unüberisichtlicher waren). Das ist jedoch nur eine Option, sofern keine Berührungsängste bestehen. Freigaben, Evaluierungen und Preislistenimporte müssen jedoch in jedem Fall nach der Installation weiterhin ordnungsgemäß umgesetzt werden. Templates beinhalten überdies keine Bilder; diese sollten vor der Freigabe manuell eingepflegt werden um eine revisionssichere Speicherung und Verwaltung sicherzustellen.

* Bereitstellung von Firmenlogos (JPG, PNG) für Aufzeichnungsexporte (z.B. Firmenlogo für obere rechte Ecke, Abteilungslogo für untere rechte Ecke, Wasserzeichen-Logo am besten mit transparentem Hintergrund) z.B. im Verzeichnis media/favicon/
* Konfiguration der [Laufzeitvariablen](#laufzeitvariablen), insbesondere das genutzte SQL-Set und dessen Anmeldedaten, Paketgröße gemäß SQL-Konfiguration, Logo-Pfade. Abgleich der Berechtigungen in Manual-Vorlagen.
* [Anpassung](#anpassung) der sachgemäßen Sprachdateien (language.XX.env/.json und Manual-Vorlagen)
* Bereitstellung und Anpassung von Bildern, die auf [Antwort-PopUps](#erwägungen-zur-nutzerakzeptanz) angezeigt werden.
* Auswahl eines Installationskennworts für die Systemnutzerin.

### Installation
* Aufruf api/_install.php, beziehungsweise api/_install.php/installDatabase/*das_gewählte_Installationskennwort*
* Wahl [Templates](#anwendungseinrichtung) zu installieren - keine Sorge, bei einem erneuten Aufruf passiert nichts schlimmes. Inhalte werden nur installiert, sofern die Namen nicht schon vergeben sind. Die Durchführung kann nur erfolgen, wenn ein Nutzer mit Administrator-Berechtigung angemeldet ist.
* Abhängig von der Kennwortstärke kann es empfehlenswert sein, den Zugangstoken der Systemnutzerin auf einen empfohlenden 64-Byte-Token zu aktualisieren. Den Zugangstoken als QR-Code exportieren und sicher verwahren!
* Eine [Installation als Progressive Web App (PWA)](https://developer.mozilla.org/de/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable#installation_aus_dem_web) ist möglich, eine Aufforderung erfolgt ggf. durch den Browser. Erteilung der Browserberechtigungen.

[Übersicht](#übersicht)

## Laufzeitvariablen
Manche Variablen können während der Laufzeit angepasst werden. Dies betrifft alle *Werte* der Sprachdateien und einige Einstellungen der config.ini. Diese Optionen werden in großen Teilen als kritisch für die Anwendungsstabilität und Einhaltung regulatorischer Anforderungen betrachtet und sind daher nicht für eine einfache Anpassung über die Nutzeroberfläche vorgesehen; statt dessen mit Bedacht und moderatem Aufwand.

### Umgebungseinstellungen
Es kann eine **config.env**-Datei als strukturelle Kopie der config.ini-Datei angelegt werden. Einstellungen innerhalb der config.env überschreiben Einstellungen der config.ini. Auf diesem Weg können unterschiedliche Umgebungen eingerichtet werden, z.B. verschiedene Entwicklungsumgebungen sowie die Produktivumgebung. Bei Entwicklungsänderungen ist es selbsterklärend diese Dateien jeweils manuell auf Stand zu halten. Jede Erwähnung der config.ini-Datei betrifft immer auch die config.env-Datei.

In den Umgebungseinstellungen können auch Label, unerlaubte Namen, hide_offduty_reasons, Ostern-bezogene Feiertage und SQL-Einstellungen umgebungsbezogen ergänzt werden. Nicht alle Einstellungen müssen vorhanden sein, fehlende Parameter werden mit den Standardeinstellungen der INI-Datei vervollständigt. Standardmäßig unterliegen ENV-Dateien nicht der Versionskontrolle; wenn der Produktions-Server als Upstream konfiguriert ist müssen diese Dateien manuell bereitgestellt werden (siehe auch [Anpassung](#anpassung)).

Standardmäßig sind folgende Berechtigungen/Rollen in der language.XX.json-Datei definiert:
```
{
    "user": "Mitarbeiter",
    "group": "Gruppe",
    "medicaldeviceconsultant": "Medizinprodukteberater",
    "supervisor": "Bereichsleiter",
    "office": "Verwaltung",
    "human_ressources": "Personalverwaltung",
    "purchase": "Einkauf",
    "purchase_assistant": "Einkaufsassistent",
    "qmo": "Qualitätsmanagementbeauftragter",
    "prrc": "Verantwortliche Person nach MDR",
    "ceo": "Leiter",
    "admin": "Programmadministrator"
}
```

```
; general application settings
[application]
debugging = no ; yes: ja, no: nein; erlaubt oder unterbindet Fehlerbenachrichtigungen
defaultlanguage = "en" ; Standard Anwendungssprache: en, de, etc. entsprechend verfügbarer language.XX.json-Dateien; Nutzer können im Profil individuell wählen
issue_mail = "issues@companymail.tld" ; Kontaktadresse für Meldungen in Bezug auf die Anwendung oder Datenschutz
order_auth = "token, signature" ; Optionen: token, signature; pin ist Standard, da dieser die Bestellberechtigung repräsentiert
order_gtin_barcode = no ; yes: ja, no: nein; stellt einen GTIN/EAN Strichcode da, sofern verfügbar, oder erzwingt statt dessen einen QR-Code mit der Artikelnummer, je nach Zustand des ERP
require_complaint_selection = yes ; yes: ja, no: nein; die Auswahl ob eine Aufzeichnung einen Bezug zu einer Reklamation hat, ist zwingend erforderlich
watermark = "media/favicon/android/android-launchericon-192-192.png" ; .jpg, .jpeg, .png, .gif, wird in Bilder eingefügt sofern ausgewählt, "" um zu verzichten, z.B. Firmenlogo

[calendar]
timezones[europeberlin] = "Europe/Berlin" ; der erste Eintrag muss der Serverstandort sein; um weitere tz Zeitzonen je nach Standorten ergänzen, in den Profileinstellungen wählbar

dateformats["Y-m-d"] = "Y-m-d"; gemäß https://www.php.net/manual/en/datetime.format.php, z.B. "d.m.Y"; leer für ISO 8601 Y-m-d; der oberste Eintrag wird als Standard für Exporte genutzt
dateformats["d.m.Y"] = "d.m.Y" ; um gewünschte Optionen erweitern, in den Profileinstellungen wählbar, Schlüssel dürfen ?{}|&~![()^" nicht enthalten - Werte dürfen

default_due = 7 ; Standardeinstellung für Fälligkeiten von Terminen

hide_offduty_reasons[] = "" ; bitte nicht ändern
; hide_offduty_reasons[] = "sickleave" ; Ursachen gemäß Sprachdatei können in Übereinstimmung mit unternehmensspezifischen Datenschutzbestimmungen ausgeblendet werden

[locations]
; erster Schlüssel ist Bundesland, um Standorte erweitern, in den Profileinstellungen wählbar
D-BW[workdays] = "1, 2, 3, 4, 5" ; Montag=1 bis Sonntag=7, Tage wie z.B. Wochenenden mit der gleichen Markierung wie Feiertage auslassen
D-BW[holidays] = "01-01, 01-06, 05-01, 10-03, 11-01, 12-24, 12-25, 12-26, 12-31"; feste Feiertage, Monat-Tag
D-BW[easter] = "-2, 1, 39, 50, 60" ; anwendbare Abstände zu Ostersonntag, Gründonnerstag -3, Karfreitag -2, Karsamstag -1, Ostermontag 1, Himmelfahrt 39, Pfingsten 50, Frohnleichnahm 60
D-BW[breaks] = "6-30, 9-45" ; gesetzliche Pausenzeiten, aufsteigend [Arbeitsstunden]-[Pausenzeit], z.B. mehr als 6 Stunden: 30 Minuten Pause, mehr als 9 Stunden: 45 Minuten Pause

; Standardeinstellungen für CSV-Verarbeitung falls nicht im Filter definiert
[csv]
headerrowindex = 0
csvprocessor_source_encoding = 'ISO-8859-1, ISO-8859-3, ISO-8859-15, UTF-8'

dialect["separator"] = ";"
dialect["enclosure"] = "\"" ;" coding environments may mess up colouring after this escaped quote
dialect["escape"] = ""
dialect["preg_delimiter"] ='#' ; kann Escape-Zeichen in regulären Ausdrücken reduzieren, je nach erforderlichem Muster

;unzulässige Namen gemäß REGEX-Mustern
[forbidden]
names[characters] = "([^\w\s\d,\.\[\]\(\)\-ÄÖÜäöüß])" ; alles was keine Buchstaben, Leerzeichen, Zahlen oder freigegebene Zeichen sind, gilt auch für Export-Dateinamen
names[length] = "^.{0,3}$" ; weniger als 4 Zeichen

; unveränderliche fest einprogrammierte reservierte Begriffe
names[numeric] = "^\d+$" ; Namen dürfen nicht ausschließlich numerisch sein, da dies für Datenbank-IDs reserviert ist
names[underscorestart] = "^_" ; Namen dürfen nicht mit _ beginnen
names[substrings] = "IDENTIFY_BY_|DEFAULT_" ; besondere Teilzeichenketten, getrennt mit |
names[literal] = "^(caro|search|false|null|sharepoint|selectedID|component|users|context|document|document_name|document_id|bundle|recordaltering|external_documents|CUSTOMERID|PRODUCTS|EXPIREDDOCUMENTS)$" ; buchstäbliche Zeichenfogen, getrennt mit |

filename[characters] = "[,\/\\\]" ; ersetze gefundene Zeichen um Verweisfehler zu vermeiden

[lifespan]
calendar[autodelete] = 365 ; Tage nach denen abgeschlossene Kalendereinträge gelöscht werden sofern nicht anderweitig angegeben

files[sharepoint] = 48 ; Stunden, nach denen Dateien gelöscht werden
files[tmp] = 24 ; Stunden nach denen Dateien gelöscht werden

order[autodelete] = 182 ; Tage nach denen ausgelieferte Bestellung die nicht archiviert sind gelöscht werden
order[undelivered] = 3 ; Tage nach denen Bereiche daran erinnert werden Auslieferungen zu merkieren oder sich nach dem Sachstand zu erkundigen
order[unreceived] = 14 ; Tage nach denen der Einkauf erinnert wird sich nach dem Versanddatum zu erkundigen, für gewöhnliche Bestellungen
service[unreceived] = 21 ; TAGE, nach denen der Einkauf erinnert wird sich nach dem Versanddatum zu erkundigen, für Service und Garantiefälle

product[documents] = 365 ; Tage nach der letzten Bereitstellung einer Datei, nach denen eine Erinnerung zur Verifizierung oder Erneuerung der Aktualität erstellt wird
product[mdr14_sample_interval] = 93 ; Tage als Standardwert bis eine neue Stichprobenprüfung erforderlich ist
product[mdr14_sample_reusable] = 1095 ; Tage als Standardwert bis ein Artikel erneut für eine Stichprobenprüfung verwendet werden darf

records[open_reminder] = 30 ; Tage nach denen per Nachricht and nicht abgeschlossene Aufzeichnungen erinnert wird

session[idle] = 600 ; Sekunden nach denen eine Nichtbenutzung der Anwendung eine erneute Authentifizierung erzwingt
session[records] = 93 ; Tage, nach denen Session-Fingerabdrücke gelöscht und Offline-Fallbacks für Beiträge ungültig werden

training[evaluation] = 62 ; Tage bis an eine Evaluierung erinnert wird
training[renewal] = 365 ; Tage bis eine Schulung abläuft, farbliche Warnung in Übersichten

; Wahrscheinlichkeiten für Ähnlichkeiten von Suchtexten in Prozent
[likeliness]
consumables_article_no_similarity = 70 ; Prozent
consumables_article_name_similarity = 80 ; Prozent
file_search_similarity = 50 ; Prozent
records_identifier_pattern = "^.+?[,\s]+.+?\s" ; z.B. für Nachname, Vorname um die Datalist des Vorgangsfilters zur Leistungsoptimierung vorzuselektieren, vorausgesetzt das Unternehmen kann sich auf einen Standard einigen
record_reidentify_similarity = 50 ; Prozent, Warnung bei geringer Übereinstimmung neu vergebener Identifikatoren
records_search_similarity = 20 ; Prozent

[limits]
form_image = 2048 ; maximale Pixel für längste Seite
identifier = 128 ; Zeichenlänge für Identifikator, je länger desto komplexer und fehleranfälliger wird der QR-Code. 17 Zeichen werden für einen Zeitstempel automatisch angefügt
max_records = 1024 ; maximal angezeigte offene Dokumentationen
order_approvalsignature_image = 2048 ; maximale Pixel für längste Seite
order_approved_archived = 512 ; Plant eine Überprüfung der archivierten Bestellungen um aufzuräumen
qr_errorlevel = 'L'; `'L'`, `'M'`, `'Q'` oder `'H'` - H für höchste Fehlertoleranz, aber auch höhere Pixeldichte
record_image = 2048 ; maximale Pixel für längste Seite
risk_acceptance_level = 4 ; farblich markiertes Produkt aus Eintrittswahrscheinlichkeit * Schadenshöhe
storage_warning = 10 ; Gigabyte, ein niedrigerer Wert für verbleibenden Speicherplatz erzeugt eine Warnung auf der Startseite
user_image = 256 ; maximale Pixel für längste Seite
bundle_files_per_slide = 12
products_per_slide = 6

; Berechtigungen gemäß der in den Sprachdateien aufgeführten permissions
; dynamische Verarbeitung innerhalb der Module
; Anwendungsadministratoren haben grundsätzlich volle Berechtigungen
; Im Falle einer Änderung von Berechtigungen für Freigaben müssen alle Elemente auch Rückwirkend von der neuen Gruppe freigegeben werden!
[permissions]
announcements = "ceo, qmo, prrc" ; Anlegen, Ändern und Löschen von Ankündigungen
appmanual = "qmo" ; Ergänzugen und Änderungen der Anleitung
audit = "ceo, qmo" ; Vorbereiten und Durchführen von internen Audits
auditsoperation = "ceo, qmo, prrc" ; Erlaubnis zum Export, dem Widerruf von Stichprobenprüfungen, dem Löschen von Bestellstatistiken, etc.
calendaredit = "ceo, qmo, supervisor" ; Änderung, Löschung oder Abschluss von Kalenderereignissen oder Arbeitszeiteinträgen
calendaraddforeigntimesheet = "ceo, supervisor, human_ressources" ; z.B. Anlegen von Krankheitstagen nach telefonischer Meldung
calendarfullaccess = "ceo" ; Änderung, Löschung oder Abschluss von Kalenderereignissen oder Arbeitszeiteinträgen
calendarfulltimesheetexport = "ceo, human_ressources" ; Arbeitszeitexporte aller Nutzer, zu fremden Arbeitszeiten beitragen
complaintclosing = "supervisor, qmo, prrc" ; obige Warnung beachten - Dokumentationen mit Reklamationen als abgeschlossen kennzeichnen
csvfilter = "ceo, qmo, purchase, office" ; Zugriff und Anwendung von CSV-Filtern
csvrules = "qmo" ; neue CSV-Filter anlegen
externaldocuments = "office, ceo, qmo" ; Bereitstellung und Verwaltung externer Dokumente
filebundles = "ceo, qmo" ; Dateipakete erstellen
files = "office, ceo, qmo" ; Dateien bereitstellen und Verwalten
formapproval = "ceo, qmo, supervisor" ; obige Warnung beachten - Freigabe von Dokumenten und ihrer Komponenten
documentcomposer = "ceo, qmo" ; Dokumente und Komponenten erstellen
documentexport = "ceo, qmo, supervisor" ; Dokumente als PDF exportieren
incorporation = "ceo, qmo, prrc" ; obige Warnung beachten - Produkteinführung freigeben oder entziehen
longtermplanning = "ceo, qmo, supervisor" ; Anlegen, Ändern und Löschen von Langzeitplanungen
maintenance = "ceo, qmo" ; Werkzeuge zur Anwendungspflege
measureedit = "ceo, qmo, prrc" ; Verbesserungsvorschläge bearbeiten, schließen und löschen
mdrsamplecheck = "ceo, qmo, prrc"; Stichprobenprüfung zurücksetzen - müssen auch Zugriff auf regulatorische Auswertungen haben
orderaddinfo = "ceo, purchase" ; Berechtigung Informationen auch zu Bestellungen anderer Bereiche hinzuzufügen
ordercancel = "ceo" ; Berechtigung Bestellungen anderer Bereiche zu stornieren oder Rücksendungen zu veranlassen
orderdisplayall = "purchase" ; standardmäßig alle Bestellungen anzeigen
orderprocessing = "purchase"; Bestellungen bearbeiten
products = "ceo, qmo, purchase, purchase_assistant, prrc" ; Artikel anlegen und bearbeiten, mindestens die gleichen Gruppen wie incorporation
productslimited = "purchase_assistant" ; eingeschränkte Bearbeitung von Artikeln 
recordsclosing = "ceo, supervisor" ; Dokumentationen als abgeschlossen kennzeichnen, Identifikator ändern (z.B. bei versehentlicher doppelter Anlage)
recordscasestate = "ceo, supervisor, office" ; Fall-Stände bearbeiten
recordsexport = "user"; Export von Aufzeichnungen, ggf. einschränken um ungewollte Datenverbreitung einzuschränken
recordsretyping = "ceo, supervisor, prrc" ; Reklamationen und Nacharbeiten als anderen Dokumentationstyp abändern
regulatory = "ceo, qmo, prrc, supervisor" ; Zugriff auf regulatorische Auswertungen und Zusammenfassungen
regulatoryoperation = "ceo, qmo, prrc" ; Erlaubnis zum Export, dem Widerruf von Stichprobenprüfungen, dem Löschen von Bestellstatistiken, etc.
responsibilities = "ceo, qmo" ; Verantwortlichkeiten anlegen, bearbeiten und löschen
riskmanagement = "ceo, qmo, prrc" ; Risiken anlegen, bearbeiten und löschen
texttemplates = "ceo, qmo" ; Textvorschläge anlegen und bearbeiten
trainingevaluation = "ceo, supervisor" ; Schulungsbewertungen
users = "ceo, qmo" ; Nutzer anlegen, bearbeiten und löschen
vendors = "ceo, qmo, purchase, prrc" ; Lieferanten anlegen und ändern

; Seiteneinstellungen für Klebeetiketten in unterschiedlichen Formaten
; Nach Belieben zu erweitern
[label]
sheet[format] = 'A4'
sheet[orientation] = 'portrait' ; portrait or landscape
sheet[rows] = 11
sheet[columns] = 5
sheet[margintop] = 0 ; in mm
sheet[marginright] = 0 ; in mm
sheet[marginbottom] = 10 ; in mm
sheet[marginleft] = 0 ; in mm
sheet[fontsize] = 10
sheet[header] = no
sheet[footer] = no

label[format] = '85 x 35 Dymo' ; Breite und Höhe in mm, ggf Bezeichnung
label[orientation] = 'landscape' ; portrait or landscape
label[margintop] = 2 ; in mm
label[marginright] = 2 ; in mm
label[marginbottom] = 2 ; in mm
label[marginleft] = 1 ; in mm
label[header] = no
label[footer] = no

; Seiteneinstellungen für PDF-Aufzeichnungen
[pdf]
record[format] = 'A4'
record[header_image] = "media/favicon/android/android-launchericon-192-192.png" ; Anzeige oben rechts, automatisch skaliert auf 20mm Höhe, "" um zu verzichten, z.B. Firmenlogo
record[footer_image] = "" ; Anzeige unten rechts, automatisch skaliert auf 10mm Höhe, "" um zu verzichten, z.B. Abteilungslogo
record[exportimage_maxheight] = 75 ; Je nach typischen Seitenverhältnissen für Querformat, muss ausgetestet werden

appointment[format] = 'A5'
appointment[orientation] = 'landscape' ; portrait or landscape
appointment[header_image] = "media/favicon/android/android-launchericon-192-192.png" ; Anzeige oben rechts, automatisch skaliert auf 20mm Höhe, "" um zu verzichten, z.B. Firmenlogo
appointment[footer_image] = "" ; Anzeige unten rechts, automatisch skaliert auf 10mm Höhe, "" um zu verzichten, z.B. Abteilungslogo
appointment[codesizelimit] = 50
appointment[codepadding] = 10
```

Calendar dateformat wird angewendet wo es angemessen ist. Da das ISO 8601 Format mit YYYY-MM-DD überlegen und zudem besser zu sortieren ist, wird es insbesondere bei Auswahllisten unabhängig von der Konfiguration beibehalten. Eingabefelder in Dokumenten vom Datum-Typ halten sich aufgrund der Datumverarbeitung des Browsers ebenfalls an dieses Format.

PDF-Label können beliebig mit gewünschten Formaten ergänzt werden. Für Label und PDF-Einstellungen sind folgende Optionen verfügbar, wenngleich nicht zwingend für alle Anfragen verwendet:
| Schlüssel | Optionen | Standard sofern nicht gesetzt |
| --------- | -------- | ----------------------------- |
| format | gebräuchliche Papierformate oder Länge und Breite als einfach Zahlen | A4 |
| unit | mm oder point | mm |
| orientation | portrait oder landscape | portrait |
| margintop | einfache Zahl in *unit*, oberer Randabstand | 30 |
| marginright | einfache Zahl in *unit*, rechter Randabstand | 15 |
| marginbottom | einfache Zahl in *unit*, unterer Randabstand | 20 |
| marginleft | einfache Zahl in *unit*, linker Randabstand | 20 |
| header_image | Pfad zur Bilddatei in der oberen rechten Ecke | none |
| footer_image | Pfad zur Bilddatei in der unteren rechten Ecke | none |
| exportimage_maxwidth | einfache Zahl in *unit* für maximale Breite eingebetteter Bilder, kann nicht größer als 135 sein | 135 |
| exportimage_maxheight | einfache Zahl in *unit* für maximale Höhe eingebetteter Bilder | 75 |
| rows | wiederholende Zeilen desselben Inhalts | 1 |
| columns | wiederholende Spalten desselben Inhalts | 1 |
| fontsize | einfache Zahl der Schriftgröße in *unit* | 12 |
| codesizelimit | einfache Zahl in *unit* limitiert die Größe, welche sich sonst am kleineren Wert von Spaltenbreite oder Zeilenhöhe orientiert | none |
| codepadding | plain number in *unit*, zusätzlicher Abstand zwischen Code und Text | none |
| header | yes oder no | yes |
| footer | yes oder no | yes |

[Übersicht](#übersicht)

## Anmerkungen und Hinweise zur Nutzung

### Allgemein
Diese Software wurde in bester Absicht entwickelt. Sie soll die Bearbeitung regulatorischer Anforderungen etwas weniger anstrengend machen. Das Nutzungsszenario ist jedoch auf die persönlichen Erfahrungen des [Teams](#das-team) in einem Medizinprodukte herstellenden Betrieb zugeschnitten, hoffentlich aber auch an jemand anderens Grundbedürfnisse anpassbar.

Das Leben, das medizinische Feld und regulatorische Anforderungen sind kompliziert, agil und unvorhersehbar. Hinter jeder Ecke verbirgt sich möglicherweise eine neue Anordnung. Daher versucht die CARO App ebenso agil zu sein um auch den nächsten Einfall der Auditorin schnell abbilden zu können. Dies kann kaum vollständig in eine einfach verständliche Nutzeroberfläche eingebunden werden. Die persönliche Erfahrung zeigt, dass weniger als ein Prozent der Beschäftigten reguläre Ausdrücke und die bloße Menge and Einstellungen, die eine solche Software zur Bewältigung der vorgesehenen Aufgaben benötigt, verstehen können. Daher wurde nach etlichen fruchtlosen Versuchen letztlich die Entscheidung getroffen diese Stellrädchen so zu belassen. Insbesondere die Datenverarbeitung unterschiedlichster Tabellen mit dem [CSV Prozessor](#csv-prozessor) und die Definition der [Laufzeitvariablen](#laufzeitvariablen) benötigen wahrscheinlich eine einigermaßen fortgeschrittene computerbegeisterte Person.

### Handhabe der Netzwerkverbindung
* Die Anwendung speichert Serveranfragen im Cache. GET-Anfragen erhalten die letzte erfolgreich übermittelte Version, die im Falle eines Verbindungabbruchs möglicherweise nicht die neueste des Systems sein kann, aber besser als keine Antwort. Von einem Risikostandpunkt aus betrachtet ist es zuverlässiger eine leicht veraltete Dokumentenversion zu verwenden als keine Aufzeichnungen machen zu können. POST-, PUT- und DELETE-Anfragen werden in einer indexedDB gespeichert und ein Ausführungsversuch unternommen sobald eine erfolgreiche GET-Anfrage auf eine Wiederherstellung einer Serververbindung schließen lässt. Dies kann zu einer Verzögerung von Daten im System führen, ist aber besser als ein Datenverlust. Es ist aber zu beachten, dass dies nur zuverlässig funktioniert, so lange der Browser beim Beenden keine Daten löscht. Dies kann von der Anwendung nicht beeinflusst werden und hängt von der Systemeinstellung ab. Hier kann gegebenenfalls nur die EDV-Abteilung behilflich sein.
* POST- und PUT-Anfragen fügen dem Datenpaket eine verschlüsselte Nutzeridentifikation hinzu. Diese Identifikation überschreibt im Falle einer erfolgreichen Validierung die Daten des angemeldeten Nutzers (incl. der festgelegten Berechtigungen) für die Service-Worker-Anfragen und stellen eine ordnungsgemäße Identität für das Hinzufügen von (zwischengespeicherten) Aufzeichnungen sicher.

### Verschiedenes
* Eine Festlegung der Paketgröße für die SQL-Umgebung auf einen größeren Wert als die Standardkonfiguration neben der Anpassung des Wertes in der config.ini ist sinnvoll. Es ist vorgesehen, dass Stapel-Abfragen aufgeteilt werden, es kann aber vorkommen, dass einzelne Anfragen mit gelegentlich Base64-codierten Bildern die Standardbegrenzung überschreiten.
* Benachrichtigungen über neue Mitteilungen sind so zuverlässig wie der Lebenszyklus des Service-Workers, welcher kurz ist. Daher gibt es wiederkehrende Anfragen mit einem kleinen Datenpaket um den Service-Worker wieder aufzuwecken, zumindest so lange der Browser geöffnet ist. Es ist keine Implementierung einer Push-Api vorgesehen um die Nutzung von Drittanbieter-Servern und Web-Diensten zu vermeiden. Benachrichtigungen funktionieren nicht im Privatsphären-Modus und [Safari](#safaris-besondere-bedürfnisse).
* Dokumente, welche Artikeln hinzugefügt wurden werden gemäß einer Ähnlichkeit der Artikelnummer zugeordnet. Dies ist unter Umständen etwas ungenau, passt aber möglicherweise zu ähnlichen Artikeln (z.B. bei unterschiedlichen Größen). Es kann aber vorkommen, dass die Dokumente nicht wirklich zum ausgewählten Artikel gehören.
* Unterstützte Bildformate sind JPG, JPEG, GIF und PNG. Sofern andere Bildformate Einzug in die Aufzeichnungen finden sollen, müssen diese als Datei-Upload angefügt werden.

### Dateinamenkonventionen
Dateinamen werden bei der Bereitstellung je nach Anwendungsfall modifiziert.

* Profilbilder der Nutzer werden umbenannt in profilepic_{Nutzername}_{Dateiname}
* Bilder für Dokumente werden umbenannt in {Komponentenname}\_{Upload Zeitstempel YmdHis}_{Dateiname}
* Aufzeichnungsanhänge werden umbenannt in {bereinigter identifikator}\_{Upload Zeitstempel YmdHis}\_{bereinigter Feldname}_{signature oder Dateiname}
* Bestellungsanhänge werden umbenannt in {Upload Zeitstempel YmdHis}_{Dateiname}
* Sharepoint-Dateien werden umbenannt in {Name des bereitstellenden Nutzers}_{Dateiname}
* Lieferantendokumente werden umbenannt in {Lieferantenname}\_{Uploaddatum Ymd}-{Ablaufdatum Ymd}_{Dateiname}
* Artikeldokumente werden umbenannt in {Lieferantenname}\_{Uploaddatum Ymd}-{Ablaufdatum Ymd}\_{Artikelnummer}_{Dateiname}

Insbesondere die letzten enthalten eine Ablaufdatum welches überwacht wird, daher ist die Dateinamenstruktur von besonderer Bedeutung. Bei Aktualisierung der Dokumente sollte der ursprüngliche Dateiname der bereitgestellten Datei beibehalten werden um unerwünschte Ergebnisse der Überwachung zu vermeiden.

### Safaris besondere Bedürfnisse
im Gegensatz zu richtigen Browsern.

Tests:
* rendertest **bestanden**
* toast **bestanden**
* dialog **bestanden**
* Service-Worker **bestanden**
* document composer **bestanden**
* notifications **fehlgeschlagen auf macOS Desktop und iOS aufgrund fehlender Notification-API Unterstützung, nicht integrierter Push-API**
* notification indicators **bestanden**
* scanner **bestanden**
* stlviewer **bestanden**

Anmerkungen:
* iOS PWAs scheinen Frontend-Code nicht zu aktualisieren uns müssen ggf. bei Änderungen neu installiert werden.
* Die Darstellung weicht aufgrund von inkonsequenten Verhalten gegenüber Webstandards leicht ab.

Obwohl Safari in der Lage ist den größte Teil der Inhalte anzuzeigen und zu Aufzeichnungen zuverlässig beizutragen, wird dringend empfohlen einen Webbrowser zu verwenden, der sich an aktuelle Standards hält. Firefox und Edge zeigen keine Schwierigkeiten in der Testumgebung.

[Übersicht](#übersicht)

## Bekannte Schwachstellen
* Das Ziehen von Elementen für die Sortierung funktioniert nicht auf mobilen Geräten, da Berührungsereignisse diese Funktion nicht unterstützen. Safari in iOS kann bei langen Berühren zwar verschieben, dafür jedoch nicht das Kontextmenu öffnen. Dokumente und deren Komponenten, Audits und Textvorschläge sollten daher auf einem Gerät mit Maus oder anderen unterstützen Eingabegeräten erfolgen.
* Verschobene Bildelemente werden im Anschluss nicht länger angezeigt, verschwinden aber nicht vollständig und sind in der Datenstruktur des aktuell bearbeiteten Dokuments weiterhin vorhanden.
* Der Kalender reicht von 1970-01-01 bis 2079-06-06 aufgrund von Einschränkungen von SQL-Server zum Zeitpunkt der Erstellung.
* Es gibt einige Einschränkungen und Unterschiede zu [regulärem](https://www.rfc-editor.org/rfc/rfc7763.html) bzw. [GitHub-flavoured](https://github.github.com/gfm/) Markdown sofern man damit vertraut ist:
    * Bilder können eingebunden werden, aber aufgrund des Service-Workers im CARO-Zusammenhang nicht abgerufen werden
    * Code-Blöcke werden nicht als \<code\>, sondern aufgrund der eingeschränkten Kompatibilität der [TCPDF](#ressources)-Einbindung statt dessen als \<span\> mit inline monospace Style ausgegeben
    * mehrzeilige Listeneinträge müssen in der vorausgehenden Zeile mit einem oder mehreren Leerzeichen enden, das Verhalten von Zeilenumbrüchen innerhalb von Listen unterscheidet sich leicht
    * diese Variante unterstützt derzeit keine
        * Setext Überschriften durch Linien darunter
        * Definitionen
        * Mehrzeiliger Code innerhalb von Listen

[Übersicht](#übersicht)

## Anpassung
Es gibt einige JSON-Dateien für die Spracheinstellungen (language.XX.json) und als Vorlagen für eine schnelle Installation. Jede JSON-Datei kann mit einer ENV-Datei erweitert werden und Standardwerte zu überschreiben oder weitere Einträge zu ergänzen. Es wird dringend empfohlen language.XX.**env**-Dateien zu erstellen, die ausgewählten Schlüsseln andere oder zusätzliche Werte hinzufügen, ohne dabei möglicherweise erforderliche zu löschen. Die JSON-Dateien dienen als Standard-Rückgriff, sind für die Erkennung verfügbarer Sprachoptionen erforderlich und erfüllen erforderliche Werte im Falle zukünftiger Aktualisierungen der Originalquelle.  
Es kann beispielsweise die Sprachen-Standardeinstellung

```json
"company": {
    "address": "Marvellous Aid Manufacturing, In The Meadows 18, 10E89 Meadow Creek, Coderland"
}
```
nur mit dem Eintrag
```json
{
	"company": {
		"address": "Tatsächlicher Firmenname, Tatsächliche Adresse"
	}
}
```
innerhalb der ENV-Datei überschrieben werden um an die tatsächlichen Umgebungsbedingungen anzupassen, während die übrigen Sprachblöcke beibehalten werden.

Das gleiche betrifft auch die config.ini-Datei sowie alle Vorlagen. Da die letztgenannten primär für die Installation genutzt werden, werden auch nur ENV-Dateien verarbeitet, vorausgesetzt die Struktur ist geeignet. ENV-Dateien **entfernen jedoch keine** Standard JSON-Werte. Standardmäßig unterliegen ENV-Dateien nicht der Versionskontrolle; wenn der Produktions-Server als Upstream konfiguriert ist müssen diese Dateien manuell bereitgestellt werden (siehe auch [Umgebungseinstellungen](#umgebungseinstellungen)).

* Die Anleitung ist bewusst bearbeitbar um sie an das technische Verständnis der Nutzer anpassen zu können. Bei der Installation werden Standardeinträge eingefügt. Die Inhalte können vor der Installation in der Datei templates/manual.XX.env/.json entsprechend der gewünschten Standardsprache angepasst werden (siehe _language.md im api-Verzeichnis).
* Manche Teile der config.ini können während der Laufzeit angepasst werden, andere werden das System destabilisieren. Entsprechende Bereiche sind gekennzeichnet.
* Sprachdateien können an die Bedürfnisse angepasst werden. Dabei dürfen im Wesentlichen die Werte angepasst werden. Alle Spachdateien (language.XX.env/.json) müssen angepasst werden und die selben Schlüssel enthalten - oder können bei Nichtbenutzung gelöscht werden. Die Nutzereinstellungen listen alle verfügbaren Sprachdateien für eine individuelle Auswahl auf. Die meisten der Schlüssel sind fest einprogrammiert, es können aber (gemäß _language.md im api-Verzeichnis) teilweise Werte ergänzt (idealerweise aber nicht gekürzt) werden:
    * [permissions] (bleibt ohne Effekt, wenn nicht innerhalb der Rollenverteilung in config.ini berücksichtigt)
    * [units]
    * [skills] (dürfen währen der Laufzeit angepasst werden, z.B. um die Qualifikationsmatrix anzupassen)
    * [documentcontext][anonymous]
    * [calendar][timesheet_pto]
    * [calendar][timesheet_signature]
    * [regulatory] (dürfen während der Laufzeit angepasst werden, z.B um auf geänderte regulatorische Anforderungen zu reagieren)
    * [risks] (dürfen während der Laufzeit angepasst werden, z.B um auf geänderte regulatorische Anforderungen oder neu identifizierte Risiken zu reagieren)

Im Falle einer Anpassung des Quelltexts:
*  Die Anwendung ist für die Verwendung auf Apache2 mit MySQL/MariaDB und IIS mit SQL Server gestaltet und [getested](#voraussetzungen). Für andere Server/Datenbank-Konfigurationen müssen gegebenenfalls zusätzliche vorbereitete Datenbankabfragen und Zugangsbeschränkungen zum Dateispeicher (`UTILITY::createDirectory()`) eingepflegt werden.
* der [CSV-Prozessor](#csv-prozessor) liefert ein assoziatives Array, daher muss eine nachgelagerte Verarbeitung der Daten selbst implementiert werden.
* Änderungen der Datenbankstruktur während der Laufzeit ist bei Nutzung von SQL Server eine Herausforderung, da hier Änderungen an der Struktur verhindert werden (https://learn.microsoft.com/en-us/troubleshoot/sql/ssms/error-when-you-save-table). Das Hinzufügen von Spalten an das Ende erscheint einfacher als zwischen vorhandene. Dynamisch hinzugefügte Spalten müssen nullbar sein, was zu beachten ist, sollen Null-Werte eine Bedeutung erhalten. Während der Entwicklung kann die Änderung von Tabellen [aktiviert werden, falls sie standardmäßig deaktiviert ist](https://learn.microsoft.com/en-us/troubleshoot/sql/ssms/error-when-you-save-table).
* Einstellungen um einen lokalen Server der Entwicklungsumgebung zu erreichen: https://stackoverflow.com/questions/21896534/accessing-a-local-website-from-another-computer-inside-the-local-network-in-iis
* Verfügbare Frontend-Anzeigeoptionen können durch den Import von unittest.js und den Aufruf von `rendertest('documents_de')` oder `rendertest('app_de')` in der Konsole angezeigt werden.
* Das checkbox2text-Widget verkettet die gewählten Optionen mit `, ` (Komma und ein Leerzeichen). Optionen dürfen diese Zeichen daher nicht enthalten (z.B. regulatorische Anforderungen für Audit-Vorlagen) oder das Verarbeiten der Optionen benätigt einen eigenen Handler (Produktverwaltung). Anderfalls kann eine erneute Auswahl zu unerwarteten Ergebnissen führen. Falls möglich sollten die gewählten Optionen einen Wert zugewiesen bekommen, unabhängig von der Bezeichnung.
* UTILITY::parsePayload:
    * Arrays können als GET und DELETE Anforderung nicht mit ?var[]=1&var[]=2 verwendet werden. Nur das letzte Vorkommen wird auf diese Weise verwendet.
    * $_FILES ist immer ein Array aufgrund einer individuellen Verarbeitung von POST und PUT Nutzlast.

[Übersicht](#übersicht)

## Erwägungen zur Nutzerakzeptanz
Die Anwenung soll Dokumentation erträglich machen. Die Gestaltung wurde so umgesetzt, dass Elemente für die Interaktion leicht verständlich und wiedererkennbar sind. Die visuelle Nutzlast wurde reduziert, ebenso ablenkende Bilder und Animationen.

Da eigene Formulare eine großer Teil der Anwendung sind, muss auch die für die Dokumente verantwortliche Person die Nutzer berücksichtiegen und aussagekräftige Namen für Eingabefelder, geeignete Auswahloptionen und Gruppierungen verwenden und unangemessene Verschachtelungen und Verkettungen vermeiden.

Ein Wochenende Psychologiestudium \s führte zu der Entscheidung die Anwendung zu personifizieren. Ziel ist die Nutzung zu steigern indem diese durch die Bereitstellung von süßen Bildchen als etwas unterhaltsamer empfunden wird und möglicherweise die Identifizierung mit der Anwendung zu verbessern. Standardmäßig werden möglicherweise Quellen angegeben die nicht Bestandteil dieses Repositorys sind und einen Fehler erzeugen können, wenn sie nicht gefunden werden. Die Datei js/icons.json muss dafür angepasst, bzw. korrigiert werden. Es wird nicht empfohlen Apps wie ZEPETO zu verwenden, da die Rechte am Bildmaterial beim Betreiber liegen.

[Übersicht](#übersicht)

## Importierung von Lieferantenpreislisten
Lieferantenpreislisten müssen eine einfache Struktur aufweisen um importierbar zu sein. Es kann einer zusätzlichen Anpassung außerhalb dieser Anwendung bedürfen, um Eingabedaten mit folgender Struktur zu erhalten:

| Artikelnummer  | Artikelbezeichnung  | EAN         | Verkaufseinheit |
| :------------- | :------------------ | :---------- | :-------------- |
| 1234           | Shirt               | 90879087    | Piece           |
| 2345           | Trousers            | 23459907    | Package         |
| 3456           | Socks               | 90897345    | Pair            |

Bei der Bearbeitung eines Lieferanten muss eine Import-Regel erstellt werden ähnlich:
```js
{
    "filesetting": {
        "headerrowindex": 0,
        "dialect": {
            "separator": ";",
            "enclosure": "\"",
            "escape": "",
            "preg_delimiter": "#"
        },
        "columns": ["Artikelnummer", "Artikelbezeichnung", "EAN", "Verkaufseinheit"]
    },
    "filter": [
        {
            "apply": "filter_by_comparison_file",
            "comment": "Übertrage ERP-ID. Source wird von der Anwendung gesetzt, sofern eine Datei bereitgestellt wird.",
            "filesetting": {
                "source": "ERPDUMP.csv",
                "headerrowindex": 1,
                "columns": ["STATUS", "REFERENZ", "LIEFERANTENNAME", "BESTELL_NUMMER", "BESTELLSTOP", "WE_DAT_ARTIKELSTAMM"]
            },
            "filter": [
                {
                    "apply": "filter_by_expression",
                    "comment": "lösche inaktive Produkte und ungenutzte Lieferanten",
                    "keep": true,
                    "match": {
                        "all": {
                            "STATUS": "false",
                            "LIEFERANTENNAME": "toller.+?lieferant",
                            "BESTELLSTOP": "false"
                        }
                    }
                },
                {
                    "apply": "filter_by_monthdiff",
                    "comment": "lösche alle Produkte, die vor über fünf Jahren bestellt wurden",
                    "keep": false,
                    "date": {
                        "column": "WE_DAT_ARTIKELSTAMM",
                        "format": "d.m.Y H:i",
                        "threshold": 36,
                        "bias": ">"
                    }
                }
            ],
            "match": {
                "all": {
                    "Artikelnummer": "BESTELL_NUMMER"
                }
            },
            "transfer": {
                "erp_id": "REFERENZ",
                "last_order": "WE_DAT_ARTIKELSTAMM"
            }
        }
    ],
    "modify": {
        "add": {
            "trading_good": "0",
            "has_expiry_date": "0",
            "special_attention": "0",
            "stock_item": "0"
        },
        "replace": [["EAN", "\\s+", ""]],
        "conditional_and": [["trading_good", "1", ["Artikelbezeichnung", "ein beliebiger regulärer Ausdruck, welcher Artikelbezeichnungen erfasst, die als Handelsware erkannt werden sollen"]]],
        "conditional_or": [
            ["has_expiry_date", "1", ["Artikelbezeichnung", "ein beliebiger regulärer Ausdruck, welcher Artikelbezeichnungen erfasst, die ein Verfallsdatum vorweisen"]],
            ["special_attention", "1", ["Artikelnummer", "eine beliebiger regulärer Ausdruck, welcher Artikelnummern erfasst, die eine besondere Aufmwerksamkeit erfordern (z.B. Hautkontakt)"]],
            ["stock_item", "1", ["Article Number", "eine beliebiger regulärer Ausdruck, welcher Artikelnummern erfasst, die lagernd sind"]]
        ],
        "rewrite": [
            {
                "article_no": ["Artikelnummer"],
                "article_name": ["Artikelbezeichnung"],
                "article_ean": ["EAN"],
                "article_unit": ["Verkaufseinheit"]
            }
        ]
    }
}
```
*headerrowindex* und *dialect* werden mit dem Standardwert der config.ini ergänzt, falls sie nicht Teil des Filters sind.

Manche Preislisten enthalten Artikelnummern mit Platzhaltern. Manche Artikel können dabei als *ProduktXYYZ* gelistet sein, wobei Y einen Wert zwischen 0 und 9 darstellt, YY 20 bis 30 und Z für L oder R steht (wie im Falle von Prothesenfüßen). Um die Auswahl und Bestellung zu vereinfachen kann ein Ersatzfilter erstellt werden und vor der rewrite-Regel angewendet werden. Dadurch wird die Preisliste mit allen möglichen Versionen aufgefüllt. Dabei ist es stets die zweite Klammer, welche ersetzt wird. 

```js
"replace": [
    ["Artikelnummer", "(Product)(X)(.*?)", 0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
    ["Artikelnummer", "(Product.)(YY)(.*?)", 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30],
    ["Artikelnummer", "(Product...)(Z)", "L", "R"]
]
```

Andere Lieferanten listen Artikel deren Bestellnummer noch eine Farbvariante angehängt werden muss. Bei der Filterregel muss sichergestellt werden das Zeilenende zu erfassen, um eine rekursive Erkennung, nicht endende Ergänzungen und damit einen Speicherüberlauf zu vermeiden.
```js
"replace":[
    ["Art.Nr.", "(501[BDKJ].+)(L$)", "L1", "L1-2", "L2", "L2-3", "L3", "L3-4", "L4", "L4-5", "L5"]
]
```

Natürlich können auch beliebige andere Filter eingefügt werden, z.B. um ohnehin ungenutzte Artikel von vorneherein beim Import auszuschließen oder den nächsten Import durch den Ausschluss nicht eingeführter Produkte zu beschleunigen.

Produkte werden im Falle einer Preislistenaktualisierung automatisch gelöscht, es sei denn
* es fand eine Produkteinführung statt,
* es wurde eine Stichprobenprüfung durchgeführt,
* es wurde ein Dokument beigefügt,
* es wurde ein Alias festgelegt
* oder es wurde schon einmal bestellt.

### Produkteinführung
Ist der Transfer des letzten Bestelldatums Bestandteil des Filters, wird eine Produkteinführung insofern initiiert, als dass das letzte Bestelldatum vermerkt wird. Im Unterschied zu regulären Produkteinführungen wird die erste Freigabe nur in der Rolle eines Nutzers angelegt, ohne etwaige weitere Rollen des aktuellen Nutzers zu berücksichtigen.

### Stichprobenprüfung, Verfallsdaten und besondere Aufmerksamkeit
*modify.add* und *modify.conditional* definieren Handelswaren für die Stichprobenprüfung nach MDR §14 und Artikel mit Verfallsdaten oder besonderer Aufmerksamkeit. *conditional* kann auch nach dem *rewrite* von "article_name" angewendet werden, sofern diese Spalte aus zusammenhängenden Ursprungsspalten besteht. Sollen alle Artikel des Lieferanten als Handelswaren markiert werden kann "trading_good" als 1 ergänzt (*add*) und *conditional* ausgelassen werden. Wenn bekanntermaßen keine Handelswaren in der Preisliste enthalten sind kann dies komplett entfallen, da "trading_good" standardmäßig mit 0 angelegt wird. Das selbe gilt für Verfallsdaten und besondere Aufmerksamkeit.

Es können auch alle Artikel mit "trading_good" = 1 angelegt und dann eine Bedingung für den Wert 0 erstellt werden, falls das einfacher ist. Das selbe gilt für Verfallsdaten und besondere Aufmerksamkeit.

*special_attention* wird bei den freigegebenen Bestellungen angezeigt und ist dafür vorgesehen auf eine Vergabe von Chargennummern für Produkte mit Hautkontakt hinzuweisen. Dies kann aber in den Sprachdateien eine beliebige andere Aufgabe erhalten.

### Standardfilter bei Export
Falls nicht definiert wird bei einem Export von Artikellisten ein Standardfilter generiert. Wie bei der [Lieferanten- und Artikelverwaltung](#lieferanten--und-artikelverwaltung) beschrieben, kann dies sinnvoll sein, sofern anfänglich keine Preisliste importiert wurde und der Artikelstamm eines Lieferanten primär in der Anwendung bearbeitet wurde. In diesem Fall werden die Informationen ohne Bedingungen, Filter und Änderungen reimportiert. Ein solcher Filter kann nicht auf Preislisten von Lieferanten angewendet werden und erzeugt eine Fehlermeldung.
```js
{
    "filesetting": {
        "headerrowindex": 0,
        "columns": [
            "article_no",
            "article_name",
            "article_unit", 
            "article_ean", 
            "trading_good",
            "has_expiry_date",
            "special_attention",
            "stock_item",
            "last_order"
        ]
    }
}
```

[Übersicht](#übersicht)

# Regulatorische Anforderungen an die Software
Stellungnahmen zu regulatorischen Anforderungen an die Software gemäß [OpenRegulatory](https://github.com/openregulatory/templates) liegen in der englischsprachigen Beschreibung vor.

### Empfehlungen zur Nutzerunterweisung
unter Berücksichtigung der [Standard-Berechtigungen](#umgebungseinstellungen)

| Berechtigungen | Themen |
| -------------- | ------ |
| Nutzer | &bull; Bestellungen, Produkteinführung, Stichprobenprüfung<br/>&bull; Aufzeichnungen, Import, Export<br/>&bull; Kalender, Planung, Terminvereinbarung<br/>&bull; Schulungen, Planung, Bewertung |
| Bereichsleiter, PRRC | &bull; Dokumente, Freigaben<br/>&bull; Laufende Produkteinführungen<br/>&bull; Stichprobenprüfung, Widerruf |
| QMB | &bull; Verantwortlichkeiten<br/>&bull; Dokumente, Erstellung<br/>&bull; Audit<br/>&bull; Managementbewertung<br/>&bull; Nutzerverwaltung<br/>&bull; Regulatorische Regulatory Auswertungen und Zusammenfassungen |
| QMB, Einkauf | &bull; Lieferanten, hinzufügen, bearbeiten, bewerten, Preislistenimport<br/>&bull; Produkte, hinzufügen, bearbeiten, deaktivieren<br/>&bull; Bestellungen |

### Risikoanalyse
Eine Risikoanalyse liegt in der englischsprachigen Beschreibung vor.

[Übersicht](#übersicht)

### Erklärung zur Barrierefreiheit
Folgendes sollte möglich sein:
* bis zu 200 % problemlos zu zoomen
* auf einem Großteil der Anwendung nur mithilfe der Tastatur zu navigieren
* auf einem Großteil der Anwendung mithilfe eines modernen Bildschirmlesers und einer Spracherkennungssoftware (auf ihrem Computer oder Telefon) zu navigieren

Diese Anwendung ist gemäß der technischen Norm für Websites und mobile Apps gestaltet und entspricht weitestgehend der Stufe „AA“ der Richtlinien für barrierefreie Webinhalte (WCAG) Version 2.1.

#### Stand der Vereinbarkeit mit den Anforderungen
Diese Anwendung entspricht teilweise den Richtlinien für [barrierefreie Webinhalte (WCAG) Version 2.1 Stufe AA](https://www.w3.org/TR/WCAG21/). Siehe [Nicht barrierefreie Inhalte](#nicht-barrierefreie-inhalte) für nähere Informationen.

Die Anwendung wurde zuletzt am 24. April 2025 getestet.

#### Erstellung der Erklärung
Diese Erklärung wurde am 24. April 2025 erstellt.

Die Erklärung basiert auf den automatisierten Analyseergebnissen des [axe DevTools Firefox Plugins](https://addons.mozilla.org/en-US/firefox/addon/axe-devtools/) und manuellen Tests mit dem [Orca Screenreader](https://orca.gnome.org/) für Linux.

#### Feedback
Ich begrüße Ihr Feedback bezüglich der Barrierefreiheit der CARO App. Bitte teilen Sie mir mit, wenn Sie Probleme mit der Barrierefreiheit haben:
* [GitHub issues](https://github.com/erroronline1/caro/issues) (bitte geben Sie im Formular keine sensiblen Informationen an, z.B. zu Ihren Finanzen, Ihrer Gesundheit oder anderen Themen, die sehr persönlich sind)

Ich bemühe mich die Probleme schnellstmöglich zu addressieren.

#### Kompatibilität mit Browsern und unterstützenden Technologien
Die CARO App ist mit den folgenden meistgenutzten unterstützenden Technologien kompatibel:
* der neuesten Version der Browser Mozilla Firefox, Microsoft Edge, Google Chrome und Apple Safari
* in Kombination mit den neuesten Versionen von NVDA, VoiceOver und TalkBack

#### Technische Spezifikationen
Die Barrierefreiheit der CARO App wird durch die nachstehenden Technologien unterstützt und beruht auf einer Kombination aus Webbrowser und unterstützenden Technologien oder Plug-ins, die auf Ihrem Computer installiert sind:
* HTML
* WAI-ARIA
* CSS
* JavaScript

#### Nicht barrierefreie Inhalte
Trotz meiner Bemühungen, die CARO App barrierefrei zu gestalten, bin ich mir verschiedener Einschränkungen bewusst, die ich versuche zu beheben sobald ich eine bessere Lösung habe. Nachstehend finden Sie eine Beschreibung der mir bekannten Einschränkungen und potenzieller Lösungen. Bitte teilen Sie mir mit, wenn Sie mit einem Problem konfrontiert werden, das nicht in der Liste aufgeführt ist.

Bekannte Einschränkungen auf der CARO App:
* Farbkontrast-Verhältnisse erreichen standardmäßig nicht die gewünschte Grenze. Sie können ein hoffentlich adäquates Farbschema im Nutzerprofil wählen.
* Manche horizontal scrollbare Bereiche haben keine direkte Tastaturerreichbarkeit. Der Desktopmodus stellt Bedienelemente für die Navigation zur Verfügung. Für Bildschirmleser sind diese verborgen. Horizontal scrollbare Bereiche reduzieren die visuelle Informationsfülle für zweitranginge aber zugehörige Informationen und werden daher als insgesamt vorteilhaft betrachtet, auch trotz möglicher Konflikte mit dem Informationsfluss-Kriterium.
* Der Ablauf von Verbindungen kann durch administrative Nutzer auf das bis zu Dreifache des Standardlimits erhöht werden. Es kann jedoch auch durch die Serverkonfiguration limitiert sein. Bitte besprechen sie diese [allgemein anpassbare Einstellung](#servereinrichtung) mit Ihrem Betreiber falls notwendig.
* Langzeitplanungen werden durch die Einfärbung kleiner Bildschirmbereiche umgesetzt. Die Barrierefreiheit für diese spezielle Funktion kann aufgrund der Dimensionen und Kontraste beeinträchtigt sein. Für die Einfärbung kann neben der Legende keine zusätzliche Beschreibung zur Verfügung gestellt werden.

[Übersicht](#übersicht)

# Code Design Vorlagen
Eine Beschreibung der Code Design Vorlagen für eine statische Quelltextanalyse, Integration-Tests, Stress-Tests und Performancebewertungen sowie des Deployment-Prozesses liegt in der englischsprachigen Beschreibung vor.

[Übersicht](#übersicht)

# API Dokumentation
Eine ausführliche API-Dokumentation liegt in der englischsprachigen Beschreibung vor.

[Übersicht](#übersicht)

# Stellungnahme zu technischen Richtlinien zur Datensicherheit
Eine Stellungnahme zu den technischen Richtlinien für Anwendungen im Gesundheitswesen liegt in der englischsprachigen Beschreibung vor.
Basis für die Bewertung sind die Richtlinien des BSI für [Frontend](https://www.bsi.bund.de/SharedDocs/Downloads/DE/BSI/Publikationen/TechnischeRichtlinien/TR03161/BSI-TR-03161-2.pdf?__blob=publicationFile&v=10) und [Backend](https://www.bsi.bund.de/SharedDocs/Downloads/DE/BSI/Publikationen/TechnischeRichtlinien/TR03161/BSI-TR-03161-3.pdf?__blob=publicationFile&v=9).

## Nutzungsrichtlinien für die Nutzung der Anwendung

### Datenschutz
Diese Anwendung enthält sensible Daten. Bitte lass das Gerät nicht unbeaufsichtigt während du angemeldet bist um eine unbeabsichtigte Verbeitung der Daten zu vermeiden. Beachte, dass sensible Daten durch Bildschirmaufnahmen und Anwendungswechsel kompromittiert werden können und vermeide die Verbreitung außerhalb der Anwendung. Wenn du sensible Daten aus der Anwendung exportierst bist du selbst für eine sichere Behandlung verantwortlich. Auch bei einer Gerätesperre kann die Anwendung im Hintergrund aktiv sein. Bei Verbindungsabbrüchen werden sensible Daten auf dem Gerät gespeichert. Bitte stelle schnellstmöglich eine Netzwerkverbindung her, damit die Daten bereinigt und auf dem Server im Sinne der Datensicherheit und durchgängigen Dokumentation verarbeitet werden können. Melde dich ab, wenn du die Anwendung nicht verwendest und lasse das Gerät nicht unbeaufsichtigt. Verwende nur zugelassene Geräte um die Datensicherheit zu gewährleisten. Informiere im Falle eines Verlusts deiner Zugangskennung für eine Neuvergabe unverzüglich einen Mitarbeiter der folgenden Berechtigungsgruppen: :permissions (*wie in config.ini für permissions->users festgelegt*)

### Personenbezogene Daten (Nutzer)
Diese Anwendung ist Bestandteil des Qualitätsmanagements. Deine Daten sind für die Dokumentation und die Ressourcenplanung erforderlich. Deine Daten sind in deinem Profil einsehbar. Manche Daten können nur durch die Leitung bearbeitet werden, andere von dir selbst. Im Falle einer Löschung deines Nutzerprofils verbleiben gesendete Nachrichten und allgemein genutzte Informationen (Bestellungen, Prüfungen, dein Name als Verfasser und Mitarbeiter für die Dokumentation) aufgrund berechtigten Interesses im Sinne einer durchgängigen Dokumentation und operativer Prozesse im System.

### Berechtigungen
Die Anwendung erbittet die Berechtigung für den Kamerazugriff und Benachrichtigungen. Kamerazugriff kann für die Erfassung von Fotos für die Dokumentation und dem Scannen von Strich- und QR-Codes erforderlich sein. Letztere Anwendung kann auch durch zusätzliche Geräte erfolgen. Andernfalls kannst du dich nicht anmelden und die Dokumentation nicht im erforderlichen Maße sicherstellen. Benachrichtigungen informieren über neue Nachrichten innerhalb der Anwendung und verbessern dadurch die Arbeitsbedingungen. Die Berechtigungen können durch die Browsereinstellungen jederzeit entzogen werden.

### Rückmeldungen
Solltest du Probleme mit der Datensicherheit oder der Anwendung feststellen, wenden dich umgehend an :issue_mail (*wie in config.ini für application->issue_mail festgelegt*), erläutere die Details und ergänze eine Kontaktmöglichkeit.

[Übersicht](#übersicht)

# Bibliotheken
Eine Auflistung der verwendeten Bibliotheken liegt in der englischsprachigen Beschreibung vor.

[Übersicht](#übersicht)

# Lizenz
[CARO - Cloud Assisted Records and Operations](https://github.com/erroronline1/caro)

Copyright (C) 2023-2025 error on line 1 (dev@erroronline.one)

Dieses Programm ist freie Software. Sie können es unter den Bedingungen der GNU Affero General Public License, wie von der Free Software Foundation veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß Version 3 der Lizenz oder jeder späteren Version.

Die Veröffentlichung dieses Programms erfolgt in der Hoffnung, daß es Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN BESTIMMTEN ZWECK. Details finden Sie in der GNU Affero General Public License.

Sie sollten ein Exemplar der GNU Affero General Public License zusammen mit diesem Programm erhalten haben. Falls nicht, siehe <http://www.gnu.org/licenses/>.

# Das Team
| Product Manager | Lead developer | Lead designer | Usability / QA / RA / Testing |
| --------------- | -------------- | ------------- | ----------------------------- |
| error 'i need the kubernetes blockchain ai cloud in orange ASAP' on line 1 | error on line 1 | error 'what do you mean - good practice? i am an artist!' on line 1 | error 'can you do anything right, like at all?' on line 1 |
| ![productmanager](./media/productmanager.png) | ![developer](./media/developer.png) | ![designer](./media/designer.png) | ![tester](./media/tester.png) |
![CARO logo](../media/favicon/icon72.png)

# CARO - Cloud Assisted Records and Operations
Eine Qualitätsmanagement-Software als geräteunabhängige Web-Anwendung für lokale Apache- oder IIS-Server-Umgebungen.

[CARO-App](https://github.com/erroronline1/caro) ist eine Software von error on line 1  
eva/3 viva ist eine Software von OptaData. Der Zugriff erfolgt auf die betriebseigenen Daten auf betriebseigenen Servern. 

# CARO ERP Interface ODEVAVIVA

Dieses Dokument beschreibt die Anbindung der Schnittstelle zwischen CARO und eva/3 viva von OptaData.

Die Schnittstelle greift direkt auf die Datenbanken der ERP-Software zu. Dadurch kann ein unmittelbarer Datenabgleich zwischen Daten der Verwaltung und der Dokumentationssoftware durchgeführt werden, der es den Mitarbeitenden der Produktivbereiche nahtlos ermöglicht die erforderlichen Informationen zu erhalten, ohne dass für die Verwaltung ein Mehraufwand entsteht und Tätigkeiten beider Bereiche durch wiederholte Anfragen unterbrochen werden müssen.

Es bestehen nur Lese-Rechte für die CARO-App, direkte Änderungen der Informationen in der ERP-Software sind in diesem Kontext nicht vorgesehen.

Die CARO-App kann dabei:
* [Patientendaten direkt aus dem System abfragen](#patientendaten-direkt-aus-dem-system-abfragen)
* [Vorgänge von Patienten anzeigen](#vorgänge-von-patienten-anzeigen)
* [Vorgangspositionen direkt aus Vorgängen heraus anzeigen](#vorgangspositionen-direkt-aus-vorgängen-heraus-anzeigen)
* [den Vorgangsstatus direkt mit der Verwaltungssoftware abgleichen](#den-vorgangsstatus-direkt-mit-der-verwaltungssoftware-abgleichen)
* [Artikellisten aus der Verwaltungssoftware importieren oder Daten abgleichen](#artikellisten-aus-der-verwaltungssoftware-importieren-oder-daten-abgleichen)
* [den Bestellstatus von Artikeln abgleichen](#den-bestellstatus-von-artikeln-abgleichen)
* [Kommissionsbestellungen anzeigen](#kommissionsbestellungen-anzeigen)
* [Datenexporte generieren](#datenexporte-generieren)
* [Geburstagsgrüße an Mitarbeiter versenden](#geburtstagsgrüße-an-mitarbeiter-versenden)

# Patientendaten direkt aus dem System abfragen
Mit der Suche nach dem Namen, Geburtsdatum oder FIBU-Nummer können die Patientendaten angezeigt oder in Dokumente importiert werden.  
Die Suchergebnisse beinhalten alle voreingestellten relevanten Informationen, die für die Dokumentation erforderlich sein können, ermöglichen aber auch ein Rechiffrierung der für die Pseudonymisierung verwendeten Patientennummern, etwa bei einem Wareneingang oder Herstellererinnerungen an bevorstehende Passteilservices.

Die Ergebnismengen beinhalten dabei jeweils eine Telefonnummer, Mobilnummer und eMail-Adresse. Bei mehreren hinterlegten Nummern beinhaltet die Ergebnismenge die jeweiligen Kombinationen. Es wird empfohlen beim Informationsimport in die Dokumentation die jeweilig bevorzugte Kombination beim Patienten zu erfragen.

Die Funktion ermöglicht auch insofern einen Abgleich, als dass veraltete oder ungültige Angaben schnell erkannt und der Änderungswunsch an die Verwaltung mitgeteilt werden kann.

Diese Funktion steht sowohl beim Datenimport für Dokumente, als auch als gesondertes Modul zur Verfügung.

# Vorgänge von Patienten anzeigen
Die gleichen Suchoptionen wie für die Patientendaten stehen auch für die Anzeige von Vorgängen zur Verfügung. Dabei können alle Vorgänge, der jeweilige Genehmigungs- und Abrechnungsstatus, Positionsaufstellungen sowie Medien angezeigt werden. So kann eine Recherche über Vorversorgungen (Handschuhfarbe, Fußkategorie, Schuhmodell, etc.) direkt durch die Produktivbereiche erfolgen, ohne dass die Vorgänge bereits in der CARO-App erfasst sein müssten, etwa bei einem Wechsel der Dokumentationsmethode von Papier zur CARO-App-basierten Dokumentation.

Diese Funktion steht als gesondertes Modul zur Verfügung.

# Vorgangspositionen direkt aus Vorgängen heraus anzeigen
Bei der Anlage von Vorgängen innerhalb der CARO-App können die jeweils zutreffenden Vorgangsnummern hinterlegt werden. Dies ermöglicht den Verzicht auf die physische Werkstattkarte, da diese nun nach der Erfassung unmittelbar digital zur Verfügung steht. Bei der Angabe mehrerer Vorgangsnummern werden die jeweiligen Positionsauflistungen nacheinander angezeigt.

Diese Funktion ist innerhalb der Aufzeichnungen der CARO-App direkt erreichbar.

# den Vorgangsstatus direkt mit der Verwaltungssoftware abgleichen
CARO-Vorgänge mit hinterlegten Vorgangsnummern werden in regelmäßigen Abständen mit dem System abgeglichen.  
Dabei werden der Beantragungs-, Genehmigungs- und Fakturierungsstatus abgefragt und bei entsprechend übermittelten Werten automatisch in die Dokumentation eingepflegt. Dies ermöglicht eine zeitnahe Information der Bereiche und Filterung der Vorgänge nach den jeweiligen Eigenschaften. Darüber hinaus reduziert es eine doppelte Pflege durch die Verwaltung, da hier wie gewohnt alle Eingaben in der ERP-Software erfolgen, die Informationen aber ohne weiteres Zutun zeitnah zur Verfügung stehen. Die Markierungen können auch manuell in der CARO-App erfolgen und werden nur aktualisiert, sofern die Markierung nicht vorhanden ist.

Diese Funktion ist Bestandteil der automatischen Hintergrundaktualisierung.

# Artikellisten aus der Verwaltungssoftware importieren oder Daten abgleichen
Angelegte Lieferanten sollten sich wortgetreu sowohl in der ERP-Software, als auch in der CARO-App wiederfinden. Für die Pflege des ERP-Systems bedeutet dies, dass Leerzeichen zu Beginn oder am Ende sowie einige Sonderzeichen nicht erlaubt sind. Daten werden aktuell noch nicht aus den Lieferantendaten übernommen.

Es können jedoch die Artikellisten der Lieferanten direkt importiert werden. Dabei werden in der ERP-Software gelöschte Artikel, Artikel mit Bestellstop, oder Artikel bei denen der gewählte Lieferant nicht der Primärlieferant ist von vorneherein herausgefiltert.  
Angaben wie die interne ERP-Nummer, Bestellbezeichnung, der letzte Wareneingang und die Angabe als Lagerware werden direkt importiert, Eigenschaften wie Mindesthaltbarkeit, Handelsware oder Hautkontakt können über einen zusätzlichen Importfilter festgelegt werden. Neu eingeführte Artikel sollten diesbezüglich an die für den Filter verantwortliche Person kommuniziert werden.

Bei der Änderung von Primärlieferanten sollten sowohl vorherige als auch der neue Primärlieferant aktualisiert werden um adäquate Suchergebnisse für das Bestellmodul zu erhalten.

Diese Funktion ist Bestandteil der Lieferantenverwaltung.

# den Bestellstatus von Artikeln abgleichen
Bestellungen, die über die CARO-App initiiert werden, erhalten ein Bestellkennzeichen. Dieses Bestellkennzeichen soll innerhalb der ERP-Software dem Bestelltext angehängt werden.  
Durch den Abgleich von Lieferantenname, Artikelnummer, Artikelbezeichnung und dem Bestellkennzeichen kann der Bestellstatus regelmäßig aktualisiert werden. Dadurch werden bislang häufig erforderliche Nachfragen reduziert. Neben der Angabe des Bestellkennzeichens, welches aus Datenschutzgründen keinen Rückschluss auf die Kommission zulässt, ensteht für die Warenwirtschaft kein Mehraufwand, da die jeweiligen Daten der Bestellung und Wareneingangsbuchungen direkt ausgewertet werdden und die Produtivbereiche daher zeitnah informieren. Die Markierungen können auch manuell in der CARO-App erfolgen und werden nur aktualisiert, sofern die Markierung nicht vorhanden ist.

Diese Funktion ist Bestandteil der automatischen Hintergrundaktualisierung.

# Kommissionsbestellungen anzeigen
Die CARO-App speichert Bestellungen im Regelfall nur für einen vergleichsweise kurzen Zeitraum, der es erlaubt zeitnah mit wenig Aufwand Rücksendungen zu erstellen.  
Eine langfristige und vollständige Archivierung von Bestellungen ist über die ERP-Software vorgesehen, der Zugriff auf diese Daten erlaubt dabei rasch vergangene Ausführungen von Artikelbestellungen nachzuvollziehen ohne dazu andere Mitarbeitende zwingend einbeziehen zu müssen. Das Bestell- und Lieferdatum, sowie die Liefermenge sind Teil der bereitgestellten Informationen.  
Grundlage der Suche sind die Parameter zur Suche von Patientendaten, zusätzlich kann der Zeitraum eingegrenzt werden.

Diese Funktion steht als gesondertes Modul zur Verfügung.

# Datenexporte generieren
Voreingestellte Datenbankabfragen können direkt als CSV-Datei exportiert werden. Dadurch ist eine weitestgehend unkomplizierte Anpassung an den jeweiligen Bedarf möglich, es besteht keine Abhängigkeit von CSV-Exportfunktionen oder Export-Beschränkungen.

Diese Funktion steht als gesondertes Modul zur Verfügung.

# Geburtstagsgrüße an Mitarbeiter versenden
Es kann ein Abgleich zwischen Nutzernamen in CARO und angelegten und gepflegten Mitarbeitern in EVA erfolgen, auf dessen Basis innerhalb CAROs eine Systemnachirt mit einem Geburtstagsgruß versandt wird. Diese Information wird nur für die Systemnachricht verwendet und nicht weiter veröffentlicht.

Diese Funktion ist Bestandteil der automatischen Hintergrundaktualisierung.
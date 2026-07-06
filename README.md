<hr />

# MITS Cron Database Backups für modified eCommerce Shopsoftware

(c) Copyright by Hetfield - MerZ IT-SerVice

* Author: Hetfield - https://www.merz-it-service.de
* Systemvoraussetzung: modified eCommerce Shopsoftware ab Version 2.0.0.0 rev 9678

<hr />

## Lizenzinformationen

Diese Erweiterung ist unter der GNU/GPL lizenziert. Eine Kopie der Lizenz liegt diesem Modul bei oder kann unter der URL http://www.gnu.org/licenses/gpl-2.0.txt heruntergeladen werden.

Die Copyright-Hinweise müssen erhalten bleiben bzw. mit eingebaut werden. Zuwiderhandlungen verstoßen gegen das Urheberrecht und die GPL und werden zivil- und strafrechtlich verfolgt.

<hr />

## Beschreibung

Mit dem Modul **MITS Cron Database Backups** können Datenbanken einer modified eCommerce Shopsoftware automatisch per Cronjob oder manuell im Administrationsbereich gesichert werden.

Zusätzlich enthält das Modul praktische **MITS Datenbank-Werkzeuge**, mit denen vorhandene Sicherungen verwaltet, heruntergeladen, gelöscht und bei Bedarf wiederhergestellt werden können.

Das Modul hilft dabei, regelmäßige Datenbanksicherungen einfacher zu erstellen und unnötigen Datenverlust zu vermeiden.

Folgende Funktionen bietet das Modul:

* automatische Datenbanksicherung per Cronjob
* manuelle Datenbanksicherung per Knopfdruck
* Sicherung als vollständige SQL-/GZIP-Datei
* optionaler Tabellen-Backup-Modus mit separater Datei je Datenbanktabelle
* Auswahl einzelner Tabellen bei manueller Sicherung
* Wiederherstellung vorhandener Sicherungen über die Datenbank-Werkzeuge
* Wiederherstellung kompletter Tabellen-Backups oder einzelner Tabellen daraus
* automatisches Sicherheitsbackup vor einer Rücksicherung
* Download vorhandener Datenbanksicherungen
* Löschen einzelner oder mehrerer Sicherungen
* optionaler Versand der Datenbanksicherung per E-Mail
* optionaler Upload der Datenbanksicherung per FTP auf einen externen Backup-Server
* optionales automatisches Löschen alter Datenbanksicherungen nach x Tagen
* optionales automatisches Löschen alter LOG-Dateien vom Typ mod_notice, mod_deprecated und mod_strict nach x Tagen
* SQL-Query-Box für einzelne SQL-Anweisungen im Administrationsbereich

<hr />

## Installation

Systemvoraussetzung: Funktionsfähige modified eCommerce Shopsoftware ab Version 2.0.0.0 rev 9678.

Vor der Installation des Moduls sichern Sie bitte Ihre komplette Shopinstallation, bestehend aus Dateien und Datenbank.

Für eventuelle Schäden übernehmen wir keine Haftung. Die Installation und Nutzung des Moduls **MITS Cron Database Backups** erfolgt auf eigene Gefahr.

Die Installation ist in der Regel sehr einfach:

1. Falls der Admin-Ordner des Shops umbenannt wurde, benennen Sie den Ordner `admin` im Verzeichnis `shoproot` des Modulpakets vor dem Hochladen entsprechend um.

2. Kopieren Sie anschließend alle Dateien aus dem Verzeichnis `shoproot` des Modulpakets in das Hauptverzeichnis Ihrer bestehenden modified eCommerce Shopsoftware.

3. Öffnen Sie im Administrationsbereich den Menüpunkt **Module -> Systemmodule**.

4. Installieren Sie dort das Modul
   **MITS Cron Database Backups © by Hetfield (MerZ IT-SerVice)**.

5. Konfigurieren Sie das Modul über **Bearbeiten** nach Ihren Wünschen.

6. Für automatische Datenbanksicherungen legen Sie einen Cronjob mit der im Modul angezeigten Cronjob-URL an.

7. Die zusätzlichen Datenbank-Werkzeuge können direkt über den Button im Systemmodul geöffnet werden.

8. Fertig.

<hr />

## Hinweise zur Nutzung

Bitte prüfen Sie regelmäßig, ob die Sicherungen korrekt erstellt werden und ob ausreichend Speicherplatz vorhanden ist.

Eine Datenbanksicherung sollte nicht ausschließlich auf demselben Server gespeichert werden. Nutzen Sie nach Möglichkeit zusätzlich den E-Mail-Versand, FTP-Upload oder eine externe Sicherungslösung.

Vor einer Rücksicherung wird automatisch ein Sicherheitsbackup erstellt. Trotzdem sollte eine Rücksicherung nur durchgeführt werden, wenn bekannt ist, welche Sicherung eingespielt werden soll.

Die SQL-Query-Box ist ein Werkzeug für Administratoren und sollte nur von Personen genutzt werden, die mit SQL-Anweisungen vertraut sind.

<hr />

## Support

Wir hoffen, dass Ihnen das Modul **MITS Cron Database Backups** für die modified eCommerce Shopsoftware gefällt.

Benötigen Sie Unterstützung bei der individuellen Anpassung des Moduls oder haben Sie Probleme beim Einbau? Gerne können Sie unseren kostenpflichtigen Support in Anspruch nehmen.

Kontaktieren Sie uns einfach unter: <a href="https://www.merz-it-service.de/Kontakt.html">info(at)merz-it-service.de</a>

<hr />

<img src="https://www.merz-it-service.de/images/logo.png" alt="MerZ IT-SerVice" title="MerZ IT-SerVice" />

MerZ IT-SerVice
Nicole Grewe - Am Berndebach 35a - D-57439 Attendorn
Telefon: 0 27 22 - 63 13 63 - Telefax: 0 27 22 - 63 14 00
E-Mail: <a href="https://www.merz-it-service.de/Kontakt.html">Info(at)MerZ-IT-SerVice.de</a> - Internet: <a href="https://www.merz-it-service.de">[www.MerZ-IT-SerVice.de](http://www.MerZ-IT-SerVice.de)</a>

<hr />

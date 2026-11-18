# Error Analyzer

Understand recurring forum log errors and prepare the information needed for support.

**Version 0.1.0-alpha.2 · Flarum 2.0.2 or later · PHP 8.3 or later · MIT**


## Features

- An admin page under **Administration → Extensions → Error Analyzer**.
- Reads multiline Flarum/Monolog logs and one-record-per-line Monolog JSON logs.
- Groups repeated entries by level, redacted message, initial stack frames and identified packages. Numeric values are normalized for grouping.
- Shows occurrence counts and the first and last occurrence within the scanned sample.
- Search by message or package, filter by package and sort by recency or frequency.
- Identifies package paths using Composer's `installed.json`, including local packages with an `install-path` entry.
- Attributes known message prefixes and identifies the evidence in the interface and report. `[depot]` maps to the installed `ernestdefoe/depot`. An explicit installed Composer package prefix such as `[fof/upload]` is also recognized.
- Distinguishes extensions, core and libraries and shows their currently installed versions.
- Prepares an editable report with reproduction notes, Flarum and web PHP versions, the redacted excerpt and packages mentioned in it.
- Optionally includes the complete installed extension list and activation states.
- Copies the reviewed report or saves it as a text file.
- English and German interface translations.

## Usage

1. Open the extension's admin page and select **Analyse logs**.
2. Choose an error group and inspect the packages and captured stack frames.
3. Describe what happened, the expected result and how to reproduce the problem.
4. Prepare the report and review the complete text.
5. Copy the report into your support request or save it as text.

Every attribution shows **Package path in log excerpt**, **Hint from log prefix**, or both. Other short aliases require a reviewed mapping. The referenced package must be installed according to Composer metadata. It does not establish causation. Versions reflect the current installation and may differ from those installed when an older error occurred.

## Scan scope

Scanning starts only when requested. It considers regular `.log` files in Flarum's `storage/logs`, newest by modification time first. Choose five, ten or twenty files.

Each file contributes at most its last 1 MiB. A scan reads at most 8 MiB, examines at most 2,500 recognized records, returns at most 200 groups and enumerates at most 2,000 directory entries. The interface reports limits and skipped files. Counts apply to the captured sample. Older records and large entries can fall outside it. Search filters the loaded snapshot.

Timestamps without an explicit timezone are interpreted as UTC. The interface displays dates in the browser's timezone.

## Sensitive information

Redaction runs on the server before returning data to the browser. Additional request contexts and stack arguments are omitted. Common credential fields, known token patterns, URLs, email and IP addresses, private keys and local installation paths are masked. SQL statement text is omitted.

Automatic redaction can miss unfamiliar secrets and personal information in arbitrary messages. Review the report before sharing. Your own reproduction notes are included as entered.

Only administrators can access the API. Responses use `no-store` cache headers. The extension does not create a database of errors or modify log files. Reports and reproduction notes remain in the open admin page until you copy or download them. No report is sent automatically and no external analysis service is contacted.

## Requirements and limitations

- Flarum `^2.0.2` and PHP `^8.3`. This initial release targets Flarum 2.
- Web PHP needs read access to logs and `vendor/composer/installed.json`.
- Recognizes standard Flarum logs and Monolog JSON records containing `datetime`, `level_name` and `message`.
- External log formats, Docker logs, systemd journals and web server/PHP-FPM logs outside `storage/logs` require additional integrations.
- The admin page requires a working Flarum installation.
- Works independently of Log Viewer and Admin Console.
- No additional worker, scheduled job or database migration is required.

## Development

The installable ZIP includes the compiled frontend. Node.js is required only for development.

```bash
cd js
npm ci
npm run build
npm test
```

Run backend checks with PHP 8.3+ and a separate Flarum 2 dependency autoloader.

```bash
php tests/run.php /path/to/test-forum/vendor/autoload.php
```

Tests create their own temporary log and package fixtures and check grouping, filtering, limits, package attribution, redaction and authorization with real Flarum dependencies. Browser checks use sample data. The maintainer reported repeated successful Alpha 1 tests on Flarum 2.0.2 and web PHP 8.5.12 in the CrazyMax container. Alpha 2 prefix attribution has been checked locally, with its target-container test still pending.


---

# Error Analyzer

Fehler im Forum schneller einordnen und die passenden Informationen für den Support zusammenstellen.

**Version 0.1.0-alpha.2 · Flarum ab 2.0.2 · PHP ab 8.3 · MIT**

[English documentation](README.en.md) · [Deutsche Installation](INSTALL-DE.md) · [English installation](INSTALL-EN.md)

## Funktionen

- Analyse direkt unter **Administration → Erweiterungen → Error Analyzer**.
- Mehrzeilige Flarum-/Monolog-Logs und zeilenweise Monolog-JSON-Logs lesen.
- Wiederkehrende Meldungen nach Schweregrad, bereinigter Meldung, ersten Aufrufstellen und erkannten Paketen gruppieren. Zahlenwerte werden für die Gruppierung vereinheitlicht.
- Häufigkeit sowie erstes und letztes Vorkommen innerhalb des gelesenen Ausschnitts zeigen.
- Nach Meldung oder Paket suchen, nach Paket filtern und nach Häufigkeit oder letztem Auftreten sortieren.
- Paketpfade mit Composers `installed.json` abgleichen. Die angezeigten Versionen stammen aus dem aktuell installierten Paketstand.
- Bekannte Log-Präfixe zuordnen und die Grundlage am Paket sowie im Supportbericht anzeigen. `[depot]` verweist auf das installierte `ernestdefoe/depot`. Präfixe mit einem vollständigen installierten Composer-Paketnamen, beispielsweise `[fof/upload]`, werden ebenfalls erkannt.
- Erweiterungen, Flarum-Kern und Bibliotheken unterscheiden. Lokale Composer-Pakete können über `install-path` zugeordnet werden.
- Einen Supportbericht mit Problembeschreibung, Flarum-Version, Web-PHP-Version, Fehlerauszug und beteiligten Paketen vorbereiten.
- Optional die komplette Liste installierter Erweiterungen mit Aktivierungsstatus aufnehmen.
- Den Bericht vor dem Teilen bearbeiten, kopieren oder als Textdatei herunterladen.
- Deutsche und englische Oberfläche.

## So verwendest du die Erweiterung

1. Öffne Error Analyzer im Adminbereich und klicke **Logs analysieren**.
2. Wähle eine Fehlergruppe. Die Paketliste zeigt, welche Pakete in den erfassten Aufrufstellen vorkommen.
3. Beschreibe, was passiert ist und wie sich das Problem nachstellen lässt.
4. Erzeuge den Bericht und prüfe den gesamten Text.
5. Kopiere ihn in deine Supportanfrage oder speichere ihn als Textdatei.

Bei jeder Paketzuordnung steht **Paketpfad im Logauszug** oder **Hinweis aus Log-Präfix**. Treffen beide zu, werden beide angezeigt. Andere kurze Präfixe werden erst nach einer geprüften Zuordnung ergänzt. Die jeweilige Erweiterung muss in Composers Paketmetadaten installiert sein.

Ein zugeordnetes Paket ist ein Anhaltspunkt für die Untersuchung. Seine Nennung belegt keine Fehlerursache. Die Paketversion beschreibt den aktuellen Stand, der vom Stand zum damaligen Fehlerzeitpunkt abweichen kann.

## Umfang der Analyse

Die Analyse startet ausschließlich durch den Button. Sie liest reguläre `.log`-Dateien unter Flarums `storage/logs`. Standardmäßig werden die fünf zuletzt geänderten Dateien berücksichtigt. Zehn oder zwanzig Dateien sind wählbar.

Pro Datei wird höchstens das letzte MiB gelesen, insgesamt höchstens 8 MiB pro Analyse. Weitere Grenzen sind 2.500 erkannte Einträge, 200 Fehlergruppen und 2.000 Verzeichniseinträge. Erreichte Grenzen und übersprungene Dateien werden angezeigt. Die Häufigkeit ist damit eine Anzahl innerhalb des erfassten Ausschnitts. Große oder alte Einträge können außerhalb liegen.

Fehlerursachen, die erst nach langer Zeit mehrfach auftreten, können in verschiedenen Logrotationen liegen. Die Suche arbeitet nur innerhalb des jeweils geladenen Ergebnisses. Logs ohne Zeitzonenangabe werden als UTC interpretiert. Die Oberfläche zeigt Zeiten in der Zeitzone des Browsers.

## Umgang mit vertraulichen Angaben

Der Server erstellt den bereinigten Auszug vor der Antwort an den Browser. Zusätzliche Anfragekontexte und Argumente von Stack-Aufrufen werden ausgelassen. Häufige Passwort-/Token-Felder, bekannte Tokenformate, URLs, E-Mail-Adressen, IP-Adressen, private Schlüssel und lokale Installationspfade werden maskiert. SQL-Anweisungstexte werden aus dem Auszug entfernt.

Die automatische Maskierung kann unbekannte Geheimnisse und persönliche Angaben in frei formulierten Fehlermeldungen übersehen. Prüfe den Bericht vor dem Teilen. Deine eigene Problembeschreibung wird so übernommen, wie du sie eingibst.

Zugriff auf die API haben ausschließlich Administratoren. Antworten erhalten `no-store`-Header. Die Erweiterung legt keine Fehlerdatenbank an und verändert keine Logdateien. Bericht und Problembeschreibung werden im geöffneten Adminbereich gehalten. Erst Kopieren oder Herunterladen erzeugt eine von dir weiterverwendbare Kopie. Die Erweiterung versendet keine Berichte und ruft keine externen Analysedienste auf.

## Voraussetzungen und Grenzen

- Flarum `^2.0.2` und PHP `^8.3`. Diese erste Ausgabe ist für Flarum 2 gebaut.
- Web-PHP muss die Logdateien und `vendor/composer/installed.json` lesen können.
- Standard-Flarum-Logs und Monolog-JSON mit `datetime`, `level_name` und `message` werden erkannt. Andere Formate, systemd-Journale sowie Docker-, Webserver- und PHP-FPM-Logs außerhalb von `storage/logs` benötigen eine eigene Anbindung.
- Ein nicht mehr startender Flarum-Adminbereich ist über diese Oberfläche nicht erreichbar.
- Log Viewer und Admin Console können parallel installiert sein. Error Analyzer benötigt beide nicht.
- Ein zusätzlicher Worker, Cronjob oder Datenbankmigrationen sind für diese Ausgabe nicht erforderlich.

## Entwicklung und Prüfungen

Das installierbare ZIP enthält den gebauten Admin-Bundle. Nur zum Entwickeln wird Node.js benötigt.

```bash
cd js
npm ci
npm run build
npm test
```

Backend-Prüfung mit einem separaten Flarum-2-Autoloader und PHP 8.3 oder neuer durchführen.

```bash
php tests/run.php /pfad/zum/testforum/vendor/autoload.php
```

Die Tests legen eigene temporäre Log- und Paketdateien an. Sie prüfen Gruppierung, Filter, Begrenzungen, Paketzuordnung, Maskierung und Zugriffsschutz mit echten Flarum-Abhängigkeiten. Browserprüfungen verwenden Beispieldaten. Alpha 1 wurde vom Betreiber mehrfach erfolgreich mit Flarum 2.0.2 und Web-PHP 8.5.12 im CrazyMax-Container getestet. Die Präfixzuordnung von Alpha 2 ist lokal geprüft, der Praxistest dieser Ausgabe im Zielcontainer steht noch aus.

[Update auf Alpha 2](UPDATE-DE.md) · [Upgrade to Alpha 2](UPDATE-EN.md)

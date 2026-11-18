# Error Analyzer

Fehler im Forum schneller einordnen und die passenden Informationen für den Support zusammenstellen.

**Version 0.1.0-alpha.1 · Flarum ab 2.0.2 · PHP ab 8.3 · MIT**

[English documentation](README.en.md) · [Deutsche Installation](INSTALL-DE.md) · [English installation](INSTALL-EN.md)

## Funktionen

- Analyse direkt unter **Administration → Erweiterungen → Error Analyzer**.
- Mehrzeilige Flarum-/Monolog-Logs und zeilenweise Monolog-JSON-Logs lesen.
- Wiederkehrende Meldungen nach Schweregrad, bereinigter Meldung, ersten Aufrufstellen und erkannten Paketen gruppieren. Zahlenwerte werden für die Gruppierung vereinheitlicht.
- Häufigkeit sowie erstes und letztes Vorkommen innerhalb des gelesenen Ausschnitts zeigen.
- Nach Meldung oder Paket suchen, nach Paket filtern und nach Häufigkeit oder letztem Auftreten sortieren.
- Paketpfade mit Composers `installed.json` abgleichen. Die angezeigten Versionen stammen aus dem aktuell installierten Paketstand.
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

Ein Paket im Fehlerverlauf ist ein Anhaltspunkt für die Untersuchung. Seine Nennung belegt keine Fehlerursache. Die Paketversion beschreibt den aktuellen Stand, der vom Stand zum damaligen Fehlerzeitpunkt abweichen kann.

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

Die Tests legen eigene temporäre Log- und Paketdateien an. Sie prüfen Gruppierung, Filter, Begrenzungen, Paketzuordnung, Maskierung und Zugriffsschutz mit echten Flarum-Abhängigkeiten. Browserprüfungen verwenden Beispieldaten. Der Praxistest im Zielcontainer steht vor der ersten Installation noch aus.

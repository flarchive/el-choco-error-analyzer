# Error Analyzer installieren

Version `0.1.0-alpha.1` für **Flarum ab 2.0.2** und **PHP ab 8.3**.
Diese Ausgabe wird aus der lokalen ZIP installiert.

## A. Dein CrazyMax-Container auf DietPi

Die ZIP `error-analyzer-0.1.0-alpha.1.zip` zunächst nach `/tmp` auf den DietPi übertragen.
Die Befehle verwenden deinen Container `flarum2-flarum-1` und den vorhandenen Mount `/mnt/wdblue/flarum2/data:/data`.
Auf dem DietPi-Host als `root` ausführen.

### 1. Entpacken und Zugriff prüfen

```bash
install -d -m 755 /mnt/wdblue/flarum2/data/extension-sources && \
unzip /tmp/error-analyzer-0.1.0-alpha.1.zip -d /mnt/wdblue/flarum2/data/extension-sources && \
docker exec --user flarum flarum2-flarum-1 \
  test -r /data/extension-sources/error-analyzer-0.1.0-alpha.1/composer.json
```

### 2. Paket als Flarum-Benutzer installieren

Andere laufende Composer-Vorgänge zuvor abschließen.

```bash
docker exec --user flarum \
  --env COMPOSER_HOME=/data/extensions/composer-home \
  --workdir /opt/flarum flarum2-flarum-1 \
  composer config --global repositories.el-choco-error-analyzer \
  '{"type":"path","url":"/data/extension-sources/error-analyzer-0.1.0-alpha.1","options":{"symlink":false,"versions":{"el-choco/error-analyzer":"0.1.0-alpha.1"}}}' && \
docker exec --user flarum \
  --env COMPOSER_HOME=/data/extensions/composer-home \
  --workdir /opt/flarum flarum2-flarum-1 \
  composer require 'el-choco/error-analyzer:0.1.0-alpha.1' \
    --update-no-dev --minimal-changes --no-scripts --no-interaction && \
docker exec --user flarum flarum2-flarum-1 extension sync && \
docker exec --user flarum --workdir /opt/flarum flarum2-flarum-1 \
  php flarum extension:enable el-choco-error-analyzer && \
docker exec --user flarum --workdir /opt/flarum flarum2-flarum-1 \
  php flarum cache:clear --force
```

Der dauerhaft gemountete Quellordner muss erhalten bleiben, damit Composer das Paket auch nach einer Container-Neuerstellung wiederfindet. Der ZIP-Inhalt enthält das gebaute JavaScript.

Falls Web-PHP nach der Installation noch den alten Composer-Autoloader verwendet, PHP-FPM im bestehenden Container neu laden.

```bash
docker exec flarum2-flarum-1 s6-svc -r /run/service/php-fpm
```

### 3. Im Browser öffnen

Im Adminbereich **Erweiterungen → Error Analyzer** öffnen, bei Bedarf mit **Strg+F5** neu laden und **Logs analysieren** anklicken. Eine Gruppe auswählen und den Supportbericht vorbereiten. Vor dem Teilen den Text prüfen.

Die Paketversion lässt sich im Terminal kontrollieren.

```bash
docker exec --user flarum --workdir /opt/flarum flarum2-flarum-1 \
  composer show el-choco/error-analyzer
```

## B. Flarum 2 ohne Docker

Dieses Beispiel verwendet ein eigenes Flarum-2-Verzeichnis `/var/www/flarum2` und den PHP-Benutzer `www-data`.
Passe beides an die tatsächliche Installation an. Dein bekanntes `/var/www/flarum` enthält Flarum 1.8 und ist für diese Ausgabe ungeeignet.

Die ZIP zunächst nach `/tmp` übertragen. Als `root` ausführen.

```bash
install -d -m 755 /var/www/flarum-extension-sources && \
unzip /tmp/error-analyzer-0.1.0-alpha.1.zip -d /var/www/flarum-extension-sources
```

Danach im Flarum-2-Verzeichnis installieren.

```bash
cd /var/www/flarum2
runuser -u www-data -- composer config repositories.el-choco-error-analyzer \
  '{"type":"path","url":"/var/www/flarum-extension-sources/error-analyzer-0.1.0-alpha.1","options":{"symlink":false,"versions":{"el-choco/error-analyzer":"0.1.0-alpha.1"}}}' && \
runuser -u www-data -- composer require 'el-choco/error-analyzer:0.1.0-alpha.1' \
  --update-no-dev --minimal-changes --no-scripts --no-interaction && \
runuser -u www-data -- php flarum extension:enable el-choco-error-analyzer && \
runuser -u www-data -- php flarum cache:clear --force
```

## Bei leeren Ergebnissen

Wähle versuchsweise **Alle erkannten Stufen**. Fehlende oder nicht lesbare Logs werden gesondert angezeigt. Web-PHP benötigt Leserechte auf `storage/logs` und Composers Paketmetadaten. Ein leeres Ergebnis kann auch bedeuten, dass der relevante Fehler außerhalb des begrenzten Ausschnitts oder in einem anderen Logformat liegt.

## Deaktivieren

Im Adminbereich die Erweiterung deaktivieren. Die vorhandenen Flarum-Logs bleiben verfügbar.

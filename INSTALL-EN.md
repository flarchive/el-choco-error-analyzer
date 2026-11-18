# Install Error Analyzer

Version `0.1.0-alpha.1` requires **Flarum 2.0.2+** and **PHP 8.3+**.
This initial package is installed from the local ZIP.

## A. CrazyMax Docker on DietPi

Copy `error-analyzer-0.1.0-alpha.1.zip` to `/tmp` on the Docker host.
These commands target container `flarum2-flarum-1` and the existing mount `/mnt/wdblue/flarum2/data:/data`.
Run on the host as `root`.

### 1. Extract and check access

```bash
install -d -m 755 /mnt/wdblue/flarum2/data/extension-sources && \
unzip /tmp/error-analyzer-0.1.0-alpha.1.zip -d /mnt/wdblue/flarum2/data/extension-sources && \
docker exec --user flarum flarum2-flarum-1 \
  test -r /data/extension-sources/error-analyzer-0.1.0-alpha.1/composer.json
```

### 2. Install as the Flarum user

Finish any other running Composer operations first.

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

Keep the source directory in the persistent mount so Composer can find the package after container recreation. The compiled frontend is included in the ZIP.

If web PHP still uses the old Composer autoloader, reload PHP-FPM inside the existing container.

```bash
docker exec flarum2-flarum-1 s6-svc -r /run/service/php-fpm
```

### 3. Open the admin page

Go to **Administration → Extensions → Error Analyzer**, hard-refresh if needed and select **Analyse logs**. Select a group, prepare a report and review the text before sharing it.

Check the installed version from the terminal.

```bash
docker exec --user flarum --workdir /opt/flarum flarum2-flarum-1 \
  composer show el-choco/error-analyzer
```

## B. Native Flarum 2

This example uses a dedicated Flarum 2 directory `/var/www/flarum2` and PHP user `www-data`. Adjust both to the actual installation.
The maintainer's existing `/var/www/flarum` hosts Flarum 1.8 and cannot run this release.

Copy the ZIP to `/tmp` and extract it as `root`.

```bash
install -d -m 755 /var/www/flarum-extension-sources && \
unzip /tmp/error-analyzer-0.1.0-alpha.1.zip -d /var/www/flarum-extension-sources
```

Install in the Flarum 2 directory.

```bash
cd /var/www/flarum2
runuser -u www-data -- composer config repositories.el-choco-error-analyzer \
  '{"type":"path","url":"/var/www/flarum-extension-sources/error-analyzer-0.1.0-alpha.1","options":{"symlink":false,"versions":{"el-choco/error-analyzer":"0.1.0-alpha.1"}}}' && \
runuser -u www-data -- composer require 'el-choco/error-analyzer:0.1.0-alpha.1' \
  --update-no-dev --minimal-changes --no-scripts --no-interaction && \
runuser -u www-data -- php flarum extension:enable el-choco-error-analyzer && \
runuser -u www-data -- php flarum cache:clear --force
```

## Empty results

Try **All recognised levels**. Missing logs and read failures appear as warnings. Web PHP needs read access to `storage/logs` and Composer's installed package metadata. The relevant entry can also be outside the bounded sample or use an unsupported format.

## Disable

Disable the extension through Flarum's admin panel. The existing Flarum logs remain available.

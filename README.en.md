# Error Analyzer

Understand recurring forum log errors and prepare the information needed for support.

**Version 0.1.0-alpha.1 · Flarum 2.0.2 or later · PHP 8.3 or later · MIT**

[Deutsche Dokumentation](README.md) · [English installation](INSTALL-EN.md)

## Features

- An admin page under **Administration → Extensions → Error Analyzer**.
- Reads multiline Flarum/Monolog logs and one-record-per-line Monolog JSON logs.
- Groups repeated entries by level, redacted message, initial stack frames and identified packages. Numeric values are normalized for grouping.
- Shows occurrence counts and the first and last occurrence within the scanned sample.
- Search by message or package, filter by package and sort by recency or frequency.
- Identifies package paths using Composer's `installed.json`, including local packages with an `install-path` entry.
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

Package attribution identifies paths present in the captured message and stack frames. It does not establish causation. Versions reflect the current installation and may differ from those installed when an older error occurred.

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

Tests create their own temporary log and package fixtures and check grouping, filtering, limits, package attribution, redaction and authorization with real Flarum dependencies. Browser checks use sample data. A live installation test on the target container remains pending.

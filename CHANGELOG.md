# Changelog

## 1.0.0 — 2026-11-18

- Publish the tested Alpha 2 functionality as the first stable release.
- Keep path-based attribution and existing explicitly labelled prefix hints unchanged.
- Add public Composer installation and update instructions in English and German.
- Exclude personal deployment guides and local release tools from the repository.
- Build the frontend without code comments or source-map references.

## 0.1.0-alpha.2

- Attribute a leading `[depot]` log prefix to the installed `ernestdefoe/depot` package.
- Support leading prefixes containing an explicit installed Composer package name.
- Show path evidence and prefix hints separately in the interface and support report, combining both when applicable.
- Preserve the logged severity and avoid guessing short aliases or attributing uninstalled packages.
- Add focused regression checks for the reported Depot INFO message and update the German and English guides.

## 0.1.0-alpha.1

- Add an administrator-only, on-demand log analysis page for Flarum 2.0.2+.
- Parse multiline Flarum/Monolog and line-delimited Monolog JSON records.
- Group recurring entries, show sample counts and time bounds, and provide search, package filtering and sorting.
- Identify installed packages from captured stack paths and Composer metadata.
- Omit request contexts and call arguments and mask common sensitive values before returning excerpts.
- Prepare editable support reports with optional extension inventories, clipboard copying and plain-text downloads.
- Include German and English interfaces and installation guides for CrazyMax Docker and native Flarum 2.

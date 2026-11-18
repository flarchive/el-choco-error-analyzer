# Changelog

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

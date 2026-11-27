# Changelog

All notable changes to the Registration Fields plugin (OJS 3.3 line).

## 1.4.0.1 — 2026-11-27

### Added
- English fallback for the plugin's own texts. In an interface language without its own translation file (for example Italian), the plugin name, description, settings and registration form fields appear in English instead of raw `##plugins.generic.registrationFields…##` keys. A key missing from an existing translation also falls back to English. Only keys with the `plugins.generic.registrationFields.` prefix are handled; OJS's own texts and other plugins' texts are not touched.
- Automatic refresh of the plugin's compiled language files. OJS 3.3 rebuilds a compiled copy only when the `.po` file is newer than the copy, so an update whose files carry an older time could keep showing old texts. When the plugin's own `.po` files change (size or time), it deletes only its own compiled copies.
- `CHANGELOG.md`.

### Changed
- `version.xml` compatibility is now `3.3.0.x` (covers every OJS 3.3 patch release) instead of a fixed list ending at 3.3.0.22.
- README (English and Turkish): language section, requirements; the Turkish README now shows the screenshots too.

## 1.4.0.0 — 2025-02-06

### Added
- Debug mode: writes injection diagnostics to the PHP error log.
- Fallback injection: if no known landmark is found in the registration form, fields are inserted before `</form>`.

### Changed
- Theme compatibility: flexible regex patterns with word boundaries find the injection point in themes that add inline styles, several CSS classes or extra attributes.

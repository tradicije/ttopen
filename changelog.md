# Changelog

All notable changes for the OpenTT plugin.

## [Unreleased]

### 2026-03-01

#### Changed

- Removed personal development artifacts from the public plugin package (`tools` folder excluded from release).
- Standardized public release version to `1.0.0`.
- Updated plugin header metadata with AGPL license fields.
- Added local player fallback image handling (no external hardcoded image URL).
- Added admin setting to switch OpenTT admin UI language (`Serbian` / `English`).
- Added request-level admin UI translation layer for OpenTT admin pages when English is selected.
- Switched admin UI translations to file-based `english_reference = translation` format for future language additions.
- Added dynamic language file auto-detection from `languages/admin-ui-<lang_code>.txt`.
- Added translation template file `languages/admin-ui-template.example.txt`.
- Added internal bridge file `languages/admin-ui-source-sr-to-en.txt` for stable migration from existing Serbian source UI strings.
- Removed internal/non-release documentation references to temporary converter tooling.
- Updated public docs to English (`readme.md`, `changelog.md`).

### 2026-02-26

#### Added

- Club fallback image support using local asset (`assets/img/fallback-club.png`).
- Import validation merge preview for potentially duplicate players.
- Bulk delete with shift-range selection in admin lists:
- Players
- Clubs
- Matches

#### Changed

- Match import reliability improvements for DB inserts/updates.
- Diagnostics and repair actions for `played` consistency in competition rounds.
- Additional import/export improvements for DB entities and media linking.
- Improved filtering/sorting UX in Players tab (club and league filters, sorting by league/club).

### 2026-02-25

#### Added

- Competition diagnostics panel in Import/Export.
- Competition-level reset action (matches/games/sets) from admin UI.
- Better import conflict handling for player merge decisions.

#### Changed

- Routing fallback behavior for taxonomy and virtual archive contexts.
- Import token handling and validation flow hardening.
- Compatibility and stability fixes for frontend shortcode rendering in archive contexts.

### 2026-02-24

#### Added

- New Settings tab modules:
- Shortcode catalog with attribute builder
- CSS override panels (global and per shortcode)
- First Time Setup preview actions

#### Changed

- Admin UX improvements for non-technical operators.
- Documentation and setup guidance updates.
- Ongoing unification of legacy modules into OpenTT core architecture.

## Notes

- Internal class/function names intentionally remain unchanged for compatibility.
- This changelog is now maintained in English for public release workflow.

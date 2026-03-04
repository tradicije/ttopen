# Changelog

All notable changes to the OpenTT plugin are documented in this file.

## Unreleased

### Next

#### Naming Consistency

- Replaced remaining non-legacy `stkb` identifiers in shortcode filter/query keys with `opentt_*` equivalents (matches, clubs, competitions, player/team season filters).
- Unified remaining internal admin/UI identifiers to `opentt` (settings form keys, onboarding action key, confirm phrase field, thumbnail picker IDs/selectors, live-search hit class, help modal target, shortcode builder/card classes).

#### Assets

- Renamed frontend CSS override style handle namespace from `stkb-unified-*` to `opentt-unified-*`.
- Updated admin JS initialization dataset/data flags from `stkb*` to `opentt*` keys.
- Switched admin branding logo source (topbar + onboarding) from root `opentt-logo.png` to `assets/img/admin-ui-logo.png` without changing frontend/readme logo usage.
- Enhanced `opentt_matches_grid` (`filter=true`) with a right-aligned popup calendar date filter (`opentt_match_date`) that highlights match days by status: played (green tint) and upcoming (blue tint).
- Updated admin Settings shortcode catalog/help for `opentt_matches_grid` to document calendar behavior and the optional `opentt_match_date` attribute.
- Aligned the matches-grid calendar toggle visual style with existing filter controls and switched its icon to `assets/icons/calendar.svg` rendered in white.
- Simplified the matches-grid calendar trigger to icon-only and switched calendar month/day labels to English.

#### Tooling

- Added standalone legacy export converter CLI app: `tools/convert-stkb-export.php` for transforming older `stkb_*` JSON packages (format/meta/section/table/key names) into OpenTT-compatible import JSON.

#### Import/Export

- Improved import upload error messaging for PHP upload limits (`UPLOAD_ERR_INI_SIZE` / code `1` and form size / code `2`) by showing file size and current `upload_max_filesize` / `post_max_size` values.

#### Localization

- Updated admin UI translation dictionaries in `languages/` to align key strings with the new `opentt-*`/`opentt_*` markup and action/query identifiers.

#### Architecture

- Extracted admin settings/onboarding/data-purge action orchestration from core into `src/WordPress/AdminSettingsActionManager.php`, keeping `includes/class-opentt-unified-core.php` as a delegating entry layer.
- Extracted import/export admin action orchestration into `src/WordPress/DataTransferActionManager.php` while keeping existing transfer/validation/import algorithms unchanged via delegated callbacks.
- Extracted DB schema migration and legacy table sync orchestration into `src/Infrastructure/SchemaMigrationManager.php`, reducing `includes` core DB bootstrap responsibilities.
- Removed redundant core wrapper methods for notice URL and ID/date parsing, and switched core call sites to direct helper/service usage.
- Extracted import payload parsing/summarizing/validation logic into `src/WordPress/ImportPayloadInspector.php`, with `OpenTT_Unified_Core` keeping lightweight delegating wrappers.
- Extracted `opentt_matches_grid` shortcode implementation from `includes/modules/trait-opentt-unified-shortcodes.php` into `src/WordPress/Shortcodes/MatchesGridShortcode.php`, keeping trait as a thin delegator.
- Extracted `opentt_clubs` shortcode implementation from `includes/modules/trait-opentt-unified-shortcodes.php` into `src/WordPress/Shortcodes/ClubsGridShortcode.php`, keeping trait as a thin delegator.

## Releases

### 1.1.0-beta.2 - 2026-03-03

#### Highlights

- Major Phase 2 refactor release focused on splitting monolithic core responsibilities into PSR-4 services, while preserving existing runtime behavior and public API contracts.
- Core architecture is now significantly more modular across bootstrap, onboarding, settings, admin workflows, migration flows, diagnostics, competition rule handling, and frontend assets pipeline.
- Public integration surfaces remain stable (`opentt_*` shortcodes, admin actions, option/meta namespaces, DB compatibility behavior).

#### Engineering

- Added refactor compatibility contract documentation: `docs/refactor/API_CONTRACT.md`.
- Introduced Composer PSR-4 foundation and namespaced plugin bootstrap with safe non-Composer autoload fallback.
- Extracted infrastructure services for DB table resolution, admin UI translation, and visual settings CSS/settings domain.
- Extracted WordPress service layer for legacy content and shortcode registration, onboarding/rewrite lifecycle, settings and notices, league/season and competition admin flows, migration and maintenance actions, and competition rule storage/catalog/profile/query helpers.

#### Assets & UI

- Migrated frontend/admin selector namespace to `opentt-*` and aligned JS bindings.
- Standardized key admin JS microcopy to English.

#### Documentation

- Updated version references to `1.1.0-beta.2` in README files.
- Standardized AGPL file headers across PHP sources.

### 1.1.0-beta.1 - 2026-03-03

#### Highlights

- Full naming standardization to `opentt` across public and internal surfaces.
- Shortcodes are now fully English and use only `opentt_*` tags.
- DB layer now uses canonical `opentt_*` tables with built-in legacy fallback.
- Core was further modularized to reduce monolith size and simplify maintenance.

#### Breaking Changes

- Removed runtime support for old shortcode tags.
- Renamed PHP source files from `stkb` prefix to `opentt` prefix.
- Renamed internal classes from `STKB_Unified_*` to `OpenTT_Unified_*`.
- Renamed internal hooks/nonces/actions/options/transients from `stkb_unified_*` to `opentt_unified_*`.
- Renamed and translated competition meta keys from `stkb_pravila_*` to `opentt_competition_*`.
- DB canonical table names are now `opentt_matches`, `opentt_games`, `opentt_sets`.

#### Migration & Compatibility

- Added shortcode content migration script: `migrations/2026-03-03-shortcode-tags-to-opentt.sql`.
- Added internal key migration script: `migrations/2026-03-03-internal-keys-to-opentt.sql`.
- Added physical DB table migration script: `migrations/2026-03-03-db-tables-stkb-to-opentt.sql`.
- Added runtime DB table resolver (`OpenTT_Unified_Core::db_table`) with legacy fallback.
- Added automatic legacy row sync from `stkb_*` tables to `opentt_*` tables during bootstrap/schema migration.

#### Documentation

- Updated `readme.md` and `readme-sr.md` shortcode examples and branding.
- Added centered logo and removed redundant top-level heading in both README files.

### 1.0.1 - 2026-03-02

#### Highlights

- Refactor release focused on reducing `OpenTT_Unified_Core` size without behavior changes.

#### Changed

- Extracted shared read-only helpers into `class-opentt-unified-readonly-helpers.php`.
- Extracted admin read-only helper layer into `class-opentt-unified-admin-readonly-helpers.php`.
- Preserved compatibility via delegating wrappers in existing core/trait methods.

### 1.0.0 - 2026-03-01

#### Highlights

- First public OpenTT release baseline.

#### Changed

- Standardized public package metadata and license fields (AGPL).
- Finalized public docs in English and cleaned internal-only tooling references.
- Added local fallback assets for player/club visuals.
- Added admin UI language switching and file-based translation pipeline.

### 0.9.2 - 2026-02-26

#### Added

- Club fallback image support using local asset.
- Import validation merge preview for potential duplicate players.
- Bulk delete with shift-range selection for Players, Clubs, and Matches.

#### Changed

- Improved match import reliability for DB writes.
- Added diagnostics/repair flow for `played` consistency.
- Improved filtering/sorting UX in Players admin.

### 0.9.1 - 2026-02-25

#### Added

- Competition diagnostics panel in Import/Export.
- Competition-level reset action for matches/games/sets.

#### Changed

- Better import conflict handling for player merge decisions.
- Hardening of import token/validation flow.
- Routing fallback stability improvements for archive/taxonomy contexts.

### 0.9.0 - 2026-02-24

#### Added

- New Settings tab modules: shortcode catalog, CSS override panels, first-time setup previews.

#### Changed

- Admin UX improvements for non-technical operators.
- Documentation and setup guidance updates.
- Continued legacy-module unification into OpenTT core architecture.

## Notes

- Internal class/function names are standardized to the `OpenTT_*` scheme.
- Changelog heading format: `X.Y.Z(-tag) - YYYY-MM-DD`.
- This changelog is maintained in English for public release workflow.

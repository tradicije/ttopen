# Changelog

All notable changes to the OpenTT plugin are documented in this file.

## Unreleased

### Next

#### Assets & UI

- Migrated frontend/admin asset selector namespace from `stkb-*` to `opentt-*` and aligned PHP-rendered markup/selectors accordingly.
- Updated admin live-search/wizard/player-picker script bindings to the new `opentt-*` selectors and data attributes.
- Standardized key admin asset microcopy in JavaScript to English (search/step/result helper text).

#### Engineering

- Added refactor freeze contract document for public/internal integration boundaries: `docs/refactor/API_CONTRACT.md`.
- Introduced Phase 2 PSR-4 foundation with a new Composer package manifest and `OpenTT\\Unified\\` namespace mapping.
- Added a namespaced plugin bootstrap (`src/Plugin.php`) that preserves existing runtime behavior through a legacy-core bridge.
- Updated root plugin bootstrap to prefer Composer autoload when available, with safe local fallback for non-Composer installs.
- Added a local PSR-4 autoload fallback and extracted DB table resolution/cache logic into `OpenTT\\Unified\\Infrastructure\\DbTableResolver`, reducing core responsibility while preserving legacy table compatibility behavior.
- Continued Phase 2 extraction by moving admin UI translation parsing/replacement to `OpenTT\\Unified\\Infrastructure\\AdminUiTranslator`, with core kept as a compatibility wrapper.
- Extracted legacy CPT/taxonomy registration into `OpenTT\\Unified\\WordPress\\LegacyContentTypeRegistrar`, keeping `OpenTT_Unified_Core::register_legacy_content_types()` as a stable delegation point.
- Standardized AGPL file headers across all PHP sources and aligned main plugin metadata to `1.1.0-beta.1`.

## Releases

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

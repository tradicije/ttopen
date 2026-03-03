# Changelog

All notable changes for the OpenTT plugin.

## [Unreleased]

### 2026-03-03

#### Changed

- Refactor Phase 3 (part 1): extracted shortcode match read/query methods into new `includes/class-opentt-unified-shortcode-match-query-service.php`.
- Refactor Phase 3 (part 1): moved DB read-only methods for matches/games/sets and lookup helpers (`db_get_matches`, `db_get_match_by_legacy_id`, `db_get_match_by_keys`, `db_get_h2h_matches`, `db_get_games_for_match_id`, `db_get_sets_for_game_id`, `db_get_latest_liga_for_club`) from shortcode trait to the new service via delegations.
- Wired the new shortcode match query service into core bootstrap includes.
- Refactor Phase 3 (part 2): extracted shortcode statistics/query methods into new `includes/class-opentt-unified-shortcode-stats-query-service.php`.
- Refactor Phase 3 (part 2): moved remaining read-only stats/data methods from shortcode trait to stats service via delegations (`db_get_top_players_data`, `db_get_played_matches_count_by_club`, competition/player/club season lookups, team stats, MVP stats, competition club IDs).
- Wired the new shortcode stats query service into core bootstrap includes.
- Refactor Core admin actions (matches): extracted match/game/set admin action handlers into new `includes/class-opentt-unified-admin-match-actions.php` and kept `OpenTT_Unified_Core` methods as delegating wrappers for backward-compatible hooks.
- Refactor Core admin actions (clubs/players): extracted club/player save/delete/bulk handlers into new `includes/class-opentt-unified-admin-club-player-actions.php` and kept `OpenTT_Unified_Core` methods as delegating wrappers for backward-compatible hooks.
- BREAKING: renamed all public shortcodes to English `opentt_*` tags and removed legacy tag registrations from runtime.
- Updated plugin fallback/template shortcode usage and shortcode catalog/CSS reference keys to the new `opentt_*` tags.
- Added SQL migration script for existing content: `migrations/2026-03-03-shortcode-tags-to-opentt.sql`.
- BREAKING: renamed all PHP source filenames from `stkb` prefix to `opentt` prefix (root bootstrap, core/services/helpers/modules/trait) and updated all internal include paths accordingly.
- BREAKING: renamed internal PHP symbols from `STKB_Unified_*` to `OpenTT_Unified_*` across core/services/modules/templates.
- BREAKING: renamed internal hook/nonce/action/option/transient keys from `stkb_unified_*` to `opentt_unified_*`.
- BREAKING: renamed and translated competition meta keys from Serbian `stkb_pravila_*` to English `opentt_competition_*`.
- Added SQL migration script for internal key rename: `migrations/2026-03-03-internal-keys-to-opentt.sql`.
- Updated `readme.md` and `readme-sr.md`: removed top-level `# OpenTT` heading and added centered root logo (`opentt-logo.png`) at the top.

### 2026-03-02

#### Changed

- Refactor Phase 1 completed: extracted shared read-only parse/format helpers from `OpenTT_Unified_Core` and shortcode trait into new `includes/class-opentt-unified-readonly-helpers.php`.
- Refactor Phase 1: kept backward compatibility by preserving existing method signatures in `OpenTT_Unified_Core` and `OpenTT_Unified_Shortcodes_Trait` and delegating to the new helper class.
- Refactor Phase 2 completed: extracted admin read-only UI/data helpers (dropdown builders, municipality/country option catalogs, country label/flag utilities, player index helpers) into new `includes/class-opentt-unified-admin-readonly-helpers.php`.
- Refactor Phase 2: updated `OpenTT_Unified_Core` to delegate admin read-only helper methods to the new admin helper class without changing external behavior.
- Reduced monolithic class size by moving non-mutating helper logic out of `includes/class-opentt-unified-core.php`.

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

- Internal class/function names are now standardized to the `OpenTT_*` naming scheme.
- This changelog is now maintained in English for public release workflow.

# Changelog

All notable changes to the OpenTT plugin are documented in this file.

## Unreleased

### Next

#### Assets & UI

- Reverted visible plugin branding back to `OpenTT` (plugin header metadata, admin UI labels, onboarding copy, import messaging, and README text), while keeping existing technical identifiers unchanged.
- Added an explicit project disclaimer in README files clarifying that OpenTT is not affiliated with `opentt.pl`.
- Updated match shortcodes to support `played="true|false"` as the primary public filter attribute (with robust parsing), while keeping `odigrana` as a backward-compatible alias.
- Added new `opentt_matches_list` shortcode with contextual league/season round navigation (chevrons, no page refresh), per-round match rows, row-to-match linking, and optional report/video indicators when related content exists.
- Fixed `opentt_matches_list` score-line alignment so the center separator (`:`) and scores stay visually centered regardless of team-name length.
- Fixed `opentt_matches_list` score rendering and styling parity: `0` scores now render correctly, and winner/loser emphasis now matches `opentt_matches_grid` (winner bold, loser reduced opacity for both name and score).
- Updated `opentt_matches_list` unplayed rows to hide placeholder `0:0` scores and show kickoff time in the center block instead, with tighter team spacing around the middle label.
- Refined `opentt_matches_list` unplayed center label styling to keep the same horizontal spacing as played-score rows and match kickoff-time font size with score typography.
- Fixed `opentt_matches_list` club-name rendering for encoded dash characters so names containing `-`/en dash no longer appear as raw HTML entities (for example `&#8211;`).
- Hardened `opentt_matches_list` club-name entity decoding to handle doubly-encoded legacy titles (for example `&amp;#8211;`) so dash characters render correctly in list rows.
- Updated `opentt_matches_grid` calendar preview so the `+X more` indicator is clickable and applies direct date filtering for that day.
- Improved `opentt_matches_grid` calendar preview positioning to reduce hover gap between day cell and preview panel, preventing accidental preview switches to adjacent days.
- Added new `opentt_featured_match` shortcode with standout card layout (league/season/round meta, team crests/names, countdown center, location footer) and home/away gradient based on club jersey colors.
- Extended `opentt_featured_match` with `mode` selector: `manual` (admin featured flag) and `auto` (context-aware league/season selection of nearest upcoming match with derby tie-break based on standings rank).
- Updated club admin form (`boja_dresa`) to use WordPress color picker for reliable color input used by featured-match gradients.
- Fixed `opentt_featured_match` auto mode SQL filtering order so league+season contextual lookup returns upcoming matches correctly.
- Ensured featured-match CSS module is enqueued on frontend by adding `featured-match` to the module asset loader list.
- Updated `opentt_featured_match` auto mode to support legacy matches without kickoff time by filtering by date and treating `00:00:00` entries as end-of-day for upcoming selection.
- Refined `opentt_featured_match` auto mode selection: it now ignores matches considered played (`played=1` or score not `0:0`), prioritizes upcoming matches with explicit kickoff time, and falls back to date-only matches when needed.
- Aligned `opentt_featured_match` auto context detection with `opentt_matches_grid` by reusing the same match query context builder (`build_match_query_args`) for league/season resolution.
- Enqueued WordPress color picker assets across OpenTT admin pages so club `boja_dresa` consistently renders as a visual color picker (not plain HEX input).
- Updated featured-match location resolution to use match-level location first and fall back to home-club location when match location is empty.
- Fixed `opentt_featured_match` card gradient edge consistency by switching to horizontal gradient direction so left/right accent lines no longer appear color-inverted.
- Refined `opentt_featured_match` visual styling for stronger desktop presentation (enhanced spacing, crest sizing, center countdown panel, hover polish) and improved mobile layout to keep teams/countdown in a compact three-column row instead of stacked blocks.
- Fixed persistent featured-card side accent inversion by enforcing explicit inset edge accents (`home` on left, `away` on right) and simplifying overlay glow.
- Corrected inset shadow direction on `opentt_featured_match` so side accents truly render as home-left and away-right.
- Reworked featured-card side accents to explicit pseudo-element bars (`::before` home-left, `::after` away-right), removing inset-shadow accents for deterministic left/right color rendering.
- Removed duplicated featured-match style blocks from `main.css` and `legacy-ui.css` so `featured-match.css` is the single source of truth, preventing cross-file overrides.
- Updated featured-match card to use uninterrupted edge-to-edge gradient (without solid side bars) while retaining subtle top highlight overlay.
- Added center helper label in `opentt_featured_match` above countdown (`Početak utakmice za:`; `Rezultat` for played matches) for clearer context.
- Redesigned `opentt_matches_grid` cards to a two-row team layout with a right-side date/time status panel (removed third meta row), including conditional behavior: played matches show `Datum + Kraj`, upcoming `0:0` matches hide scores and show `Datum + Vreme`.
- Applied the same two-row card pattern to `opentt_h2h` (team rows + right-side date/status panel), including conditional score hiding for upcoming `0:0` matches and `Kraj` status for played matches.
- Added round-grouped rendering to `opentt_matches_grid`: matches are now grouped with per-round subheadings (`kolo`) that stay in sync with active filters/sorting/infinite loading.
- Hardened round heading labels in `opentt_matches_grid` with numeric fallback (`N. kolo`) and higher-contrast badge styling to ensure subheadings remain visible across all layouts.
- Updated `opentt_ekipe` center block for unplayed matches to use a live countdown (`Početak utakmice za:`) instead of static kickoff time, reusing featured-match countdown behavior with safe fallback.
- Added a new LIVE mode flow for matches after kickoff time expires: red blinking `LIVE` badges across key match shortcodes (`opentt_matches_grid`, `opentt_h2h`, `opentt_ekipe`, `opentt_featured_match`) and a new admin `Uživo` page listing active live matches with quick links for score updates and game entry.
- Updated `opentt_ekipe` LIVE state center display to show score + badge in one row (`home_score LIVE away_score`) while a match is in live mode.
- Fixed frontend match time drift by switching shortcode LIVE/countdown timestamp parsing from raw `strtotime` to WordPress-timezone-aware parsing (`wp_timezone`) across `opentt_ekipe`, `opentt_h2h`, `opentt_matches_grid`, and `opentt_featured_match`.
- Reverted temporary timezone fallback override and restored shortcode timing to rely strictly on WordPress `General Settings > Timezone`.
- Fixed LIVE-mode mismatch between frontend and admin `Uživo` page for legacy non-padded hour values (for example `6:50:00`): match-date save now normalizes to `Y-m-d H:i:s`, shortcode parsers accept both padded/non-padded hour formats, and admin LIVE query uses parsed datetime comparison instead of raw string ordering.
- Hardened LIVE parser behavior across shortcodes by removing permissive `strtotime` fallback from match timestamp detection, preventing false early LIVE states caused by ambiguous date-string interpretation.
- Fixed nondeterministic match-context selection by adding explicit ordering (`updated_at`/`created_at`, then `id DESC`) in `db_get_match_by_legacy_id` and `db_get_match_by_keys`, so frontend shortcodes consistently use the latest match row when historical duplicates exist.
- Switched LIVE workflow to fully manual control: added `live` match flag (schema/import-export), manual LIVE toggle in matches list and match edit form, `Uživo` tab now lists only manually flagged matches, and each LIVE row now has `Završi utakmicu` action to exit LIVE mode explicitly.
- Refined LIVE card visuals across frontend shortcodes: LIVE cards now use a synchronized red-tint pulse on the whole card, while the `LIVE` badge switches to white text-only pulse (no badge background) for cleaner contrast.
- Increased LIVE pulse visibility on full cards (brightness/saturation + border/shadow pulse), keeping synchronized timing with text-only white `LIVE` badge animation.
- Improved mobile `opentt_prikaz_ekipa` LIVE layout: center block now stacks into 3 rows (`home score`, `LIVE`, `away score`) instead of a single horizontal row.
- Added a small animated dot indicator before `LIVE` text inside the badge (synchronized pulse) for clearer live-match affordance across frontend card variants.
- Updated `opentt_featured_match` auto mode to prioritize manually flagged LIVE matches (`live=1`) in active context; when multiple LIVE matches exist, selection now prefers derby quality (better combined standings ranks) with kickoff proximity tie-break.
- Simplified LIVE center content in `opentt_featured_match` by removing redundant `Uživo` helper label above the LIVE badge.
- Restored score visibility in `opentt_featured_match` LIVE state by rendering center row as `home_score LIVE away_score` (with synchronized dot+text LIVE indicator).
- Polished `opentt_featured_match` LIVE center sizing: larger desktop score/indicator presence, plus mobile-specific balance tweak (slightly smaller LIVE indicator, slightly larger scores, and increased score-group spacing/padding).
- Updated `opentt_featured_match` LIVE center layout: `LIVE` indicator now sits above the score group, while scores are rendered on a separate line as `home : away` (with stronger desktop emphasis).
- Refactored featured LIVE center markup to separate indicator and score containers (`LIVE` as sibling above `opentt-featured-center`), ensuring vertical stack consistency.

#### Admin & Data

- Added `featured` match flag to match schema and import/export payloads.
- Added featured controls in admin matches workflow: quick list toggle action (`Feature/Unfeature`), featured indicator column, and featured checkbox in match edit details.
- Bumped schema version to force migration and added runtime fallback for auto-adding missing `featured` column when older installs hit admin featured actions.
- Added dedicated match `location` field in admin match form and persistence layer, and switched featured/match venue rendering to prefer this match-level location over club address fallbacks.
- Added admin helper note on match `Lokacija` field clarifying it should be overridden only when match is not played at the home venue.
- Updated match completion semantics to best-of-4 (`played=1` only when either side reaches 4), so live-mode matches remain editable until final result is reached.

## Releases

### 1.1.0 - 2026-03-04

#### Highlights

- Finalized frontend shortcode UX polish for players list expansion and calendar-assisted match discovery in `opentt_matches_grid`.

#### Assets & UI

- Updated `opentt_players` to render the first 5 player cards by default and added a bottom toggle button (`Prikaži sve` / `Sakrij`) for expanding the full list.
- Fixed shortcode dropdown/toggle form controls to inherit the active user/theme font instead of browser default (applied in both `assets/css/main.css` and `assets/css/modules/legacy-ui.css`).
- Enhanced `opentt_matches_grid` calendar hover behavior with per-day match preview rows (`HOME | SCORE | AWAY`) and compact club naming (`BUB` for single-word names, `TSK` initials for multi-word names).
- Added direct navigation from `opentt_matches_grid` calendar hover preview: each match row is now a clickable link to that match page.
- Fixed round label rendering across frontend shortcodes so `kolo` slugs like `11-kolo` are displayed as `11. kolo`.

### 1.1.0-beta.3 - 2026-03-04

#### Highlights

- Release focused on completing shortcode architecture extraction and finalizing naming/UI consistency across admin, filters, and import/export UX.
- Unified shortcode trait is now a delegating layer, with all shortcode implementations moved into dedicated PSR-4 classes.

#### Engineering

- Completed remaining `stkb` to `opentt` identifier normalization across shortcode filter/query keys and internal admin/UI identifiers.
- Continued core service extraction by moving admin settings/onboarding actions, import/export actions, schema migration orchestration, and import payload inspection into dedicated PSR-4 service classes (`src/WordPress/*`, `src/Infrastructure/*`), with `OpenTT_Unified_Core` kept as a delegating layer.
- Removed redundant core wrappers (notice URL and ID/date parsing) and switched related call sites to direct helper/service usage.
- Completed shortcode architecture migration: all shortcode implementations are now extracted from `includes/modules/trait-opentt-unified-shortcodes.php` into dedicated `src/WordPress/Shortcodes/*` classes.
- Shortcodes now extracted include match views (`matches_grid`, `matches_list`, `match_games`, `match_report`, `match_video`, `show_match_teams`), club/player content (`clubs`, `show_players`, `club_news`, `player_news`, `related_posts`, `club_info`, `player_info`, `club_form`, `player_transfers`), rankings/stats (`standings_table`, `top_players_list`, `mvp`, `player_stats`, `team_stats`), and competition views (`competition_info`, `competitions_grid`, `h2h`).

#### Assets & UI

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

#### Fixes

- Fixed `opentt_matches_grid` contextual league-season filtering so league archives now respect the active `sezona` context instead of aggregating matches from all seasons of the same league.
- Updated `opentt_match_teams` center display logic to show scheduled match time (for example `19h`) instead of `0:0` for not-yet-played matches, using backend `played`, match date, and score fallback checks.

### 1.1.0 - 2026-03-03

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

- Updated version references to `1.1.0` in README files.
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

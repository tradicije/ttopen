<p align="center">
  <img src="opentt-logo.png" alt="OpenTT logo" width="200" />
</p>

**OpenTT** is a free and open WordPress plugin for managing, displaying, and archiving table tennis competitions: matches, clubs, players, and statistics in one unified system.

OpenTT was built from real club/community needs, not as a commercial product.
The goal is simple: **keep tools, knowledge, and data in the hands of the community.**

## Project philosophy

OpenTT is developed with these principles:

- Free & libre software: use, study, modify, and share it.
- AGPL v3 license: if you run OpenTT as a service, your changes must be shared.
- No vendor lock-in: your data stays yours, in DB and open formats.
- Community first: the tool should serve clubs, federations, and operators.

There is no “Pro” version and no hidden feature wall.

## Why OpenTT exists

Many legacy league/result systems share the same problems:

- fragile CPT/ACF structures for large match data,
- complicated and unreliable admin entry,
- performance drop as seasons and records grow,
- closed systems that are hard to adapt.

OpenTT addresses this directly.
Core architectural decision:
**matches, games, and sets are stored in DB tables (not as CPT data).**

## Core goals

- Keep existing frontend behavior and shortcode compatibility.
- Move heavy match/game/set data from CPT/ACF into dedicated DB tables.
- Provide a clean admin workflow for non-technical users.
- Support both fresh installs and legacy migration scenarios.

## Main features

### Admin (backend)

- Unified OpenTT admin area:
  - Dashboard
  - Matches
  - Clubs
  - Players
  - Competitions
  - Import/Export
  - Customize
  - Settings
- First-time onboarding flow for fresh installs.
- Guided data-entry workflow for non-technical operators.
- Batch game/set entry on match edit screen.
- Automatic doubles game position by competition format (A/B).
- Club and player management with featured images and metadata.
- Competition rules with league/season/federation/format logic.
- Live search, filters, and sorting in admin lists.

### Frontend (shortcode system)

OpenTT uses standardized English `opentt_*` shortcode names with the new DB model.

Examples:

- `[opentt_matches_grid]`
- `[opentt_standings_table]`
- `[opentt_match_games]`
- `[opentt_h2h]`
- `[opentt_mvp]`
- `[opentt_match_report]`
- `[opentt_match_video]`
- `[opentt_clubs]`
- `[opentt_players]`
- `[opentt_club_info]`
- `[opentt_player_info]`
- `[opentt_top_players]`
- `[opentt_player_stats]`
- `[opentt_team_stats]`
- `[opentt_player_transfers]`
- `[opentt_competition_info]`
- `[opentt_competitions]`
- `[opentt_club_news]`, `[opentt_player_news]`

## Data model

OpenTT uses dedicated DB tables for match data:

- `wp_opentt_matches`
- `wp_opentt_games`
- `wp_opentt_sets`

Clubs and players remain on CPTs (`klub`, `igrac`) for compatibility and editor workflows.

## Import / Export

OpenTT supports selective export/import in JSON package format.

Sections:

- Competitions
- Clubs
- Players
- Matches (DB)
- Games (DB)
- Sets (DB)

Flow:

1. Validate import package.
2. Review summary and warnings.
3. Confirm import.

Also included:

- Featured media transfer (club/player/competition images).
- Merge preview for potential duplicate players before import confirmation.

## Routing and templates

OpenTT supports:

- New DB-based match routes.
- Legacy route compatibility.
- Theme override priority.
- Plugin fallback templates when theme templates are missing.
- Compatibility with both block and classic PHP themes.

## Styling and customization

- Global visual settings (colors, radius, accent).
- Advanced CSS override:
  - global
  - per shortcode
- Modular frontend CSS in `assets/css/modules/`.
- Admin assets:
  - `assets/css/admin.css`
  - `assets/js/admin.js`

## Localization

- Admin UI language switch is available in **Settings**.
- Translation files are in `languages/admin-ui-<lang_code>.txt`.
- Format is: `english_reference = translation`.
- New language files are auto-detected and listed in Settings.
- Use `languages/admin-ui-template.example.txt` as a template.

## License

**GNU Affero General Public License v3 (AGPL-3.0)**

You can use, modify, and fork OpenTT.
If you provide it as a service (SaaS), AGPL obligations apply.

See: `LICENSE.txt`.

## Author and community

OpenTT is developed by **Aleksa Dimitrijević**,
initially for **STK Bubušinac** and **stkb.rs**.

Contributions are welcome:

- bug reports,
- proposals,
- pull requests,
- license-compliant forks.

## Project status

Current version: **1.0.0**.

## Notes

Function and internal code identifiers intentionally remain unchanged in places for backward compatibility.

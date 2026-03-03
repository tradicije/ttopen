-- OpenTT internal key migration: stkb_* -> opentt_*
-- Date: 2026-03-03
-- IMPORTANT:
-- 1) Back up your database before running.
-- 2) Replace `wp_postmeta`/`wp_options` with your real table names if your prefix is not `wp_`.

-- Competition rule meta keys (translated to English).
UPDATE wp_postmeta SET meta_key = 'opentt_competition_league_slug' WHERE meta_key = 'stkb_pravila_liga_slug';
UPDATE wp_postmeta SET meta_key = 'opentt_competition_season_slug' WHERE meta_key = 'stkb_pravila_sezona_slug';
UPDATE wp_postmeta SET meta_key = 'opentt_competition_match_format' WHERE meta_key = 'stkb_pravila_format_partija';
UPDATE wp_postmeta SET meta_key = 'opentt_competition_scoring_type' WHERE meta_key = 'stkb_pravila_bodovanje_tip';
UPDATE wp_postmeta SET meta_key = 'opentt_competition_promotion_slots' WHERE meta_key = 'stkb_pravila_promocija_broj';
UPDATE wp_postmeta SET meta_key = 'opentt_competition_promotion_playoff_slots' WHERE meta_key = 'stkb_pravila_promocija_baraz_broj';
UPDATE wp_postmeta SET meta_key = 'opentt_competition_relegation_slots' WHERE meta_key = 'stkb_pravila_ispadanje_broj';
UPDATE wp_postmeta SET meta_key = 'opentt_competition_relegation_playoff_slots' WHERE meta_key = 'stkb_pravila_ispadanje_razigravanje_broj';
UPDATE wp_postmeta SET meta_key = 'opentt_competition_federation' WHERE meta_key = 'stkb_pravila_savez';
UPDATE wp_postmeta SET meta_key = 'opentt_competition_rank' WHERE meta_key = 'stkb_pravila_rang';

-- Internal attachment/legacy reference meta keys.
UPDATE wp_postmeta SET meta_key = '_opentt_legacy_ref_id' WHERE meta_key = '_stkb_legacy_ref_id';
UPDATE wp_postmeta SET meta_key = '_opentt_import_source_attachment_id' WHERE meta_key = '_stkb_import_source_attachment_id';

-- Option keys.
UPDATE wp_options
SET option_name = REPLACE(option_name, 'stkb_unified_', 'opentt_unified_')
WHERE option_name LIKE 'stkb_unified_%';

-- Related transients in options table.
UPDATE wp_options
SET option_name = REPLACE(option_name, '_transient_stkb_unified_', '_transient_opentt_unified_')
WHERE option_name LIKE '_transient_stkb_unified_%';

UPDATE wp_options
SET option_name = REPLACE(option_name, '_transient_timeout_stkb_unified_', '_transient_timeout_opentt_unified_')
WHERE option_name LIKE '_transient_timeout_stkb_unified_%';

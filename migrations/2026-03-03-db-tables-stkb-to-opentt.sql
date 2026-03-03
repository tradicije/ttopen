-- OpenTT DB table migration: stkb_* tables -> opentt_* tables
-- Date: 2026-03-03
-- IMPORTANT:
-- 1) Back up your database before running.
-- 2) Replace `wp_` with your real table prefix.
-- 3) Run in this order: matches -> games -> sets.

-- 1) Create new tables as structural clones if they do not exist.
CREATE TABLE IF NOT EXISTS wp_opentt_matches LIKE wp_stkb_matches;
CREATE TABLE IF NOT EXISTS wp_opentt_games LIKE wp_stkb_games;
CREATE TABLE IF NOT EXISTS wp_opentt_sets LIKE wp_stkb_sets;

-- 2) Copy existing data from legacy tables.
INSERT IGNORE INTO wp_opentt_matches SELECT * FROM wp_stkb_matches;
INSERT IGNORE INTO wp_opentt_games SELECT * FROM wp_stkb_games;
INSERT IGNORE INTO wp_opentt_sets SELECT * FROM wp_stkb_sets;

-- 3) Optional sanity checks.
SELECT 'stkb_matches' AS table_name, COUNT(*) AS rows_count FROM wp_stkb_matches
UNION ALL
SELECT 'opentt_matches', COUNT(*) FROM wp_opentt_matches;

SELECT 'stkb_games' AS table_name, COUNT(*) AS rows_count FROM wp_stkb_games
UNION ALL
SELECT 'opentt_games', COUNT(*) FROM wp_opentt_games;

SELECT 'stkb_sets' AS table_name, COUNT(*) AS rows_count FROM wp_stkb_sets
UNION ALL
SELECT 'opentt_sets', COUNT(*) FROM wp_opentt_sets;

-- 4) Optional cleanup (run only after verification).
-- DROP TABLE wp_stkb_sets;
-- DROP TABLE wp_stkb_games;
-- DROP TABLE wp_stkb_matches;

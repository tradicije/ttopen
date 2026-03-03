-- OpenTT shortcode migration: legacy tags -> new opentt_* tags
-- Date: 2026-03-03
-- IMPORTANT:
-- 1) Back up your database before running.
-- 2) Replace `wp_posts` with your real posts table name if your prefix is not `wp_`.

UPDATE wp_posts SET post_content = REPLACE(post_content, '[prikaz_utakmica_grid', '[opentt_matches_grid');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[prikaz_tabela', '[opentt_standings_table');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[lista_partija_nova', '[opentt_match_games');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[h2h', '[opentt_h2h');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[mvp', '[opentt_mvp');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[izvestaj_utakmice', '[opentt_match_report');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[snimak_utakmice', '[opentt_match_video');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[prikaz_domacina', '[opentt_home_club');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[prikaz_gosta', '[opentt_away_club');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[prikaz_kluba', '[opentt_club');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[prikaz_ekipa', '[opentt_match_teams');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[top_igraci_lista', '[opentt_top_players');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[prikaz_igraca', '[opentt_players');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[vesti_kluba', '[opentt_club_news');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[vesti_igraca', '[opentt_player_news');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[related_posts', '[opentt_related_posts');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[info_kluba', '[opentt_club_info');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[info_takmicenja', '[opentt_competition_info');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[forma_kluba', '[opentt_club_form');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[statistika_igraca', '[opentt_player_stats');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[statistika_ekipe', '[opentt_team_stats');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[transferi', '[opentt_player_transfers');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[info_igraca', '[opentt_player_info');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[prikaz_takmicenja', '[opentt_competitions');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[prikaz_klubova', '[opentt_clubs');

-- Optional closing-tag replacements (kept for completeness).
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/prikaz_utakmica_grid]', '[/opentt_matches_grid]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/prikaz_tabela]', '[/opentt_standings_table]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/lista_partija_nova]', '[/opentt_match_games]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/h2h]', '[/opentt_h2h]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/mvp]', '[/opentt_mvp]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/izvestaj_utakmice]', '[/opentt_match_report]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/snimak_utakmice]', '[/opentt_match_video]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/prikaz_domacina]', '[/opentt_home_club]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/prikaz_gosta]', '[/opentt_away_club]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/prikaz_kluba]', '[/opentt_club]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/prikaz_ekipa]', '[/opentt_match_teams]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/top_igraci_lista]', '[/opentt_top_players]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/prikaz_igraca]', '[/opentt_players]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/vesti_kluba]', '[/opentt_club_news]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/vesti_igraca]', '[/opentt_player_news]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/related_posts]', '[/opentt_related_posts]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/info_kluba]', '[/opentt_club_info]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/info_takmicenja]', '[/opentt_competition_info]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/forma_kluba]', '[/opentt_club_form]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/statistika_igraca]', '[/opentt_player_stats]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/statistika_ekipe]', '[/opentt_team_stats]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/transferi]', '[/opentt_player_transfers]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/info_igraca]', '[/opentt_player_info]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/prikaz_takmicenja]', '[/opentt_competitions]');
UPDATE wp_posts SET post_content = REPLACE(post_content, '[/prikaz_klubova]', '[/opentt_clubs]');

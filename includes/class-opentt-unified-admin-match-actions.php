<?php
/**
 * OpenTT - Table Tennis Management Plugin
 * Copyright (C) 2026 Aleksa Dimitrijević
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 */


if (!defined('ABSPATH')) {
    exit;
}

final class OpenTT_Unified_Admin_Match_Actions
{
    public static function handle_save_match()
    {
        self::require_cap();
        check_admin_referer('opentt_unified_save_match');
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $competition_rule_id = isset($_POST['competition_rule_id']) ? (int) $_POST['competition_rule_id'] : 0;
        $liga = '';
        $sezona = '';
        if ($competition_rule_id > 0) {
            $rule_post = get_post($competition_rule_id);
            if ($rule_post && $rule_post->post_type === 'pravilo_takmicenja') {
                $liga = sanitize_title((string) get_post_meta($competition_rule_id, 'opentt_competition_league_slug', true));
                $sezona = sanitize_title((string) get_post_meta($competition_rule_id, 'opentt_competition_season_slug', true));
            }
        }
        if ($liga === '') {
            $liga = sanitize_title((string) ($_POST['liga_slug'] ?? ''));
        }
        if ($sezona === '') {
            $sezona = sanitize_title((string) ($_POST['sezona_slug'] ?? ''));
        }
        $kolo = sanitize_title((string) ($_POST['kolo_slug'] ?? ''));
        $home = isset($_POST['home_club_post_id']) ? (int) $_POST['home_club_post_id'] : 0;
        $away = isset($_POST['away_club_post_id']) ? (int) $_POST['away_club_post_id'] : 0;
        $home_score = max(0, (int) ($_POST['home_score'] ?? 0));
        $away_score = max(0, (int) ($_POST['away_score'] ?? 0));
        $played = ($home_score + $away_score) > 0 ? 1 : 0;
        $featured = !empty($_POST['featured']) ? 1 : 0;
        $match_date = (string) ($_POST['match_date'] ?? '');
        $match_date = $match_date ? str_replace('T', ' ', $match_date) . ':00' : null;
        $location = sanitize_text_field((string) ($_POST['location'] ?? ''));

        if (self::has_any_competition_rules() && $competition_rule_id <= 0) {
            $url = $id > 0
                ? admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $id)
                : admin_url('admin.php?page=stkb-unified-add-match');
            wp_safe_redirect(self::admin_notice_url($url, 'error', 'Izaberi takmičenje.'));
            exit;
        }

        if ($liga === '' || $kolo === '' || $home <= 0 || $away <= 0 || $home === $away) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match'), 'error', 'Proveri obavezna polja utakmice.'));
            exit;
        }
        if ($sezona !== '' && self::has_any_competition_rules() && !self::get_competition_rule_post_by_slugs($liga, $sezona)) {
            $url = $id > 0
                ? admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $id)
                : admin_url('admin.php?page=stkb-unified-add-match');
            wp_safe_redirect(self::admin_notice_url($url, 'error', 'Nedostaju pravila takmičenja za izabranu ligu i sezonu.'));
            exit;
        }

        $base_slug = sanitize_title((string) get_the_title($home) . '-' . (string) get_the_title($away));
        $slug = $base_slug ?: 'utakmica';
        if (!$id) {
            for ($i = 0; $i < 50; $i++) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE liga_slug=%s AND sezona_slug=%s AND kolo_slug=%s AND slug=%s LIMIT 1",
                    $liga,
                    $sezona,
                    $kolo,
                    $slug
                ));
                if (!$exists) {
                    break;
                }
                $slug = $base_slug . '-' . ($i + 2);
            }
        }

        $data = [
            'slug' => $slug,
            'liga_slug' => $liga,
            'sezona_slug' => $sezona,
            'kolo_slug' => $kolo,
            'home_club_post_id' => $home,
            'away_club_post_id' => $away,
            'home_score' => $home_score,
            'away_score' => $away_score,
            'played' => $played,
            'match_date' => $match_date,
            'updated_at' => current_time('mysql'),
        ];
        if (self::has_featured_column($table)) {
            $data['featured'] = $featured;
        }
        if (self::has_location_column($table)) {
            $data['location'] = $location;
        }

        if ($id > 0) {
            $ok = $wpdb->update($table, $data, ['id' => $id]);
            if ($ok === false) {
                wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $id), 'error', 'Greška pri čuvanju utakmice.'));
                exit;
            }
        } else {
            $data['created_at'] = current_time('mysql');
            $ok = $wpdb->insert($table, $data);
            if ($ok === false) {
                wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match'), 'error', 'Greška pri dodavanju utakmice.'));
                exit;
            }
            $id = (int) $wpdb->insert_id;
        }

        wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $id), 'success', 'Utakmica je sačuvana.'));
        exit;
    }

    public static function handle_delete_match()
    {
        self::require_cap();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            wp_die('Nedostaje ID.');
        }
        check_admin_referer('opentt_unified_delete_match_' . $id);

        global $wpdb;
        $matches = OpenTT_Unified_Core::db_table('matches');
        $games = OpenTT_Unified_Core::db_table('games');
        $sets = OpenTT_Unified_Core::db_table('sets');

        $game_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$games} WHERE match_id=%d", $id)) ?: [];
        foreach ($game_ids as $gid) {
            $wpdb->delete($sets, ['game_id' => (int) $gid]);
        }
        $wpdb->delete($games, ['match_id' => $id]);
        $wpdb->delete($matches, ['id' => $id]);

        wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-matches'), 'success', 'Utakmica je obrisana.'));
        exit;
    }

    public static function handle_toggle_featured_match_admin()
    {
        self::require_cap();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            wp_die('Nedostaje ID.');
        }
        check_admin_referer('opentt_unified_toggle_featured_match_' . $id);

        global $wpdb;
        $matches = OpenTT_Unified_Core::db_table('matches');
        $matches_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $matches));
        if ($matches_exists !== $matches) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-matches'), 'error', 'Tabela utakmica nije dostupna.'));
            exit;
        }
        if (!self::has_featured_column($matches)) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-matches'), 'error', 'Featured kolona nije dostupna. Pokreni migraciju šeme.'));
            exit;
        }

        $current = (int) $wpdb->get_var($wpdb->prepare("SELECT featured FROM {$matches} WHERE id=%d LIMIT 1", $id));
        $next = $current === 1 ? 0 : 1;
        $ok = $wpdb->update($matches, ['featured' => $next], ['id' => $id], ['%d'], ['%d']);
        if ($ok === false) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-matches'), 'error', 'Greška pri promeni featured statusa.'));
            exit;
        }

        $message = $next === 1 ? 'Utakmica je postavljena kao featured.' : 'Utakmica više nije featured.';
        wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-matches'), 'success', $message));
        exit;
    }

    private static function has_featured_column($table)
    {
        self::maybe_add_featured_column($table);
        global $wpdb;
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'featured'));
        return !empty($column);
    }

    private static function maybe_add_featured_column($table)
    {
        global $wpdb;
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'featured'));
        if (!empty($column)) {
            return;
        }
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN featured tinyint(1) NOT NULL DEFAULT 0 AFTER played");
    }

    private static function has_location_column($table)
    {
        self::maybe_add_location_column($table);
        global $wpdb;
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'location'));
        return !empty($column);
    }

    private static function maybe_add_location_column($table)
    {
        global $wpdb;
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'location'));
        if (!empty($column)) {
            return;
        }
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN location varchar(255) NOT NULL DEFAULT '' AFTER match_date");
    }

    public static function handle_delete_matches_bulk_admin()
    {
        self::require_cap();
        check_admin_referer('opentt_unified_delete_matches_bulk');

        $ids = isset($_POST['match_ids']) && is_array($_POST['match_ids']) ? array_map('intval', (array) $_POST['match_ids']) : [];
        $ids = array_values(array_unique(array_filter($ids, static function ($v) {
            return $v > 0;
        })));

        $base_url = admin_url('admin.php?page=stkb-unified-matches');
        foreach (['liga_slug', 'sezona_slug', 'kolo_slug', 'club_id', 'games_status', 'sort_by', 'sort_dir'] as $key) {
            if (!isset($_POST[$key])) {
                continue;
            }
            $val = sanitize_text_field((string) wp_unslash($_POST[$key]));
            if ($val !== '') {
                $base_url = add_query_arg($key, $val, $base_url);
            }
        }

        if (empty($ids)) {
            wp_safe_redirect(self::admin_notice_url($base_url, 'error', 'Nije izabrana nijedna utakmica.'));
            exit;
        }

        global $wpdb;
        $matches = OpenTT_Unified_Core::db_table('matches');
        $games = OpenTT_Unified_Core::db_table('games');
        $sets = OpenTT_Unified_Core::db_table('sets');
        $deleted = 0;

        foreach ($ids as $id) {
            $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$matches} WHERE id=%d LIMIT 1", $id));
            if ($exists <= 0) {
                continue;
            }
            $game_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$games} WHERE match_id=%d", $id)) ?: [];
            foreach ($game_ids as $gid) {
                $wpdb->delete($sets, ['game_id' => (int) $gid]);
            }
            $wpdb->delete($games, ['match_id' => $id]);
            $wpdb->delete($matches, ['id' => $id]);
            $deleted++;
        }

        if ($deleted <= 0) {
            wp_safe_redirect(self::admin_notice_url($base_url, 'error', 'Nijedna izabrana utakmica nije obrisana.'));
            exit;
        }

        wp_safe_redirect(self::admin_notice_url($base_url, 'success', 'Obrisano utakmica: ' . $deleted . '.'));
        exit;
    }

    public static function handle_save_game()
    {
        self::require_cap();
        check_admin_referer('opentt_unified_save_game');
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('games');
        $matches_table = OpenTT_Unified_Core::db_table('matches');

        $match_id = isset($_POST['match_id']) ? (int) $_POST['match_id'] : 0;
        $game_id = isset($_POST['game_id']) ? (int) $_POST['game_id'] : 0;
        if ($match_id <= 0) {
            wp_die('Nedostaje match_id.');
        }

        $match = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$matches_table} WHERE id=%d", $match_id));
        if (!$match) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-matches'), 'error', 'Utakmica nije pronađena.'));
            exit;
        }

        $max_games = max(0, min(7, (int) $match->home_score + (int) $match->away_score));
        if ($max_games <= 0) {
            $max_games = 7;
        }

        $order_no = max(1, (int) ($_POST['order_no'] ?? 1));
        $slug = sanitize_title((string) ($_POST['slug'] ?? ''));
        if ($slug === '') {
            $slug = 'partija-' . $order_no;
        }
        $hp = (int) ($_POST['home_player_post_id'] ?? 0);
        $ap = (int) ($_POST['away_player_post_id'] ?? 0);
        $hp2 = (int) ($_POST['home_player2_post_id'] ?? 0);
        $ap2 = (int) ($_POST['away_player2_post_id'] ?? 0);
        $is_doubles = !empty($_POST['is_doubles']) ? 1 : 0;
        $hs = max(0, (int) ($_POST['home_sets'] ?? 0));
        $as = max(0, (int) ($_POST['away_sets'] ?? 0));

        if ($hp <= 0 || $ap <= 0) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'error', 'Izaberi igrače za partiju.'));
            exit;
        }
        if ($is_doubles && ($hp2 <= 0 || $ap2 <= 0 || $hp === $hp2 || $ap === $ap2)) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'error', 'Dubl partija nije validna.'));
            exit;
        }
        $match_format = self::match_competition_format((string) $match->liga_slug, (string) $match->sezona_slug);
        $expected_doubles_order = ($match_format === 'format_b') ? 7 : 4;
        if ($is_doubles && $order_no !== $expected_doubles_order) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'error', 'Za izabrani format dubl mora biti partija #' . $expected_doubles_order . '.'));
            exit;
        }
        if (!$is_doubles && $order_no === $expected_doubles_order) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'error', 'Partija #' . $expected_doubles_order . ' mora biti dubl za izabrani format.'));
            exit;
        }
        if ($order_no > $max_games) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'error', 'Redni broj partije prelazi dozvoljeni maksimum za rezultat utakmice.'));
            exit;
        }

        $existing_order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE match_id=%d AND order_no=%d LIMIT 1",
            $match_id,
            $order_no
        ));
        if ($existing_order_id && (int) $existing_order_id !== $game_id) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'error', 'Partija sa tim rednim brojem već postoji.'));
            exit;
        }

        if ($game_id <= 0) {
            $current_games = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE match_id=%d", $match_id));
            if ($current_games >= $max_games) {
                wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'error', 'Dostignut je maksimalan broj partija za ovaj rezultat.'));
                exit;
            }
        }

        $data = [
            'match_id' => $match_id,
            'order_no' => $order_no,
            'slug' => $slug,
            'is_doubles' => $is_doubles,
            'home_player_post_id' => $hp,
            'away_player_post_id' => $ap,
            'home_player2_post_id' => $is_doubles ? ($hp2 ?: null) : null,
            'away_player2_post_id' => $is_doubles ? ($ap2 ?: null) : null,
            'home_sets' => $hs,
            'away_sets' => $as,
            'updated_at' => current_time('mysql'),
        ];

        if ($game_id > 0) {
            $ok = $wpdb->update($table, $data, ['id' => $game_id]);
            if ($ok === false) {
                wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'error', 'Partija nije sačuvana.'));
                exit;
            }
        } else {
            $data['created_at'] = current_time('mysql');
            $ok = $wpdb->insert($table, $data);
            if ($ok === false) {
                wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'error', 'Partija nije dodata.'));
                exit;
            }
        }

        wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'success', 'Partija je sačuvana.'));
        exit;
    }

    public static function handle_save_games_batch()
    {
        self::require_cap();
        check_admin_referer('opentt_unified_save_games_batch');
        global $wpdb;
        $games_table = OpenTT_Unified_Core::db_table('games');
        $sets_table = OpenTT_Unified_Core::db_table('sets');
        $matches_table = OpenTT_Unified_Core::db_table('matches');

        $match_id = isset($_POST['match_id']) ? (int) $_POST['match_id'] : 0;
        if ($match_id <= 0) {
            wp_die('Nedostaje match_id.');
        }

        $match = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$matches_table} WHERE id=%d", $match_id));
        if (!$match) {
            wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-matches'), 'error', 'Utakmica nije pronađena.'));
            exit;
        }

        $max_games = max(0, min(7, (int) $match->home_score + (int) $match->away_score));
        if ($max_games <= 0) {
            $max_games = 7;
        }
        $match_format = self::match_competition_format((string) $match->liga_slug, (string) $match->sezona_slug);
        $expected_doubles_order = ($match_format === 'format_b') ? 7 : 4;

        $posted_games = isset($_POST['games']) && is_array($_POST['games']) ? $_POST['games'] : [];
        $existing_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$games_table} WHERE match_id=%d", $match_id)) ?: [];
        $existing_by_order = [];
        foreach ($existing_rows as $er) {
            $existing_by_order[(int) $er->order_no] = $er;
        }

        for ($order_no = 1; $order_no <= $max_games; $order_no++) {
            $raw = isset($posted_games[$order_no]) && is_array($posted_games[$order_no]) ? $posted_games[$order_no] : [];
            $hp = isset($raw['home_player_post_id']) ? (int) $raw['home_player_post_id'] : 0;
            $ap = isset($raw['away_player_post_id']) ? (int) $raw['away_player_post_id'] : 0;
            $hp2 = isset($raw['home_player2_post_id']) ? (int) $raw['home_player2_post_id'] : 0;
            $ap2 = isset($raw['away_player2_post_id']) ? (int) $raw['away_player2_post_id'] : 0;
            $hs = max(0, (int) ($raw['home_sets'] ?? 0));
            $as = max(0, (int) ($raw['away_sets'] ?? 0));
            $is_doubles = ($order_no === $expected_doubles_order) ? 1 : 0;

            $sets_raw = isset($raw['sets']) && is_array($raw['sets']) ? $raw['sets'] : [];
            $set_rows = [];
            $wins_home = 0;
            $wins_away = 0;
            for ($set_no = 1; $set_no <= 5; $set_no++) {
                $set_in = isset($sets_raw[$set_no]) && is_array($sets_raw[$set_no]) ? $sets_raw[$set_no] : [];
                $sp_home = max(0, (int) ($set_in['home_points'] ?? 0));
                $sp_away = max(0, (int) ($set_in['away_points'] ?? 0));
                if ($sp_home <= 0 && $sp_away <= 0) {
                    continue;
                }
                $set_rows[] = [
                    'set_no' => $set_no,
                    'home_points' => $sp_home,
                    'away_points' => $sp_away,
                ];
                if ($sp_home > $sp_away) {
                    $wins_home++;
                } elseif ($sp_away > $sp_home) {
                    $wins_away++;
                }
            }

            $has_any = (
                $hp > 0 || $ap > 0 || $hp2 > 0 || $ap2 > 0
                || $hs > 0 || $as > 0 || !empty($set_rows)
            );
            $existing = isset($existing_by_order[$order_no]) ? $existing_by_order[$order_no] : null;

            if (!$has_any) {
                if ($existing) {
                    $wpdb->delete($sets_table, ['game_id' => (int) $existing->id]);
                    $wpdb->delete($games_table, ['id' => (int) $existing->id]);
                }
                continue;
            }

            if ($hp <= 0 || $ap <= 0) {
                $msg = 'Partija #' . $order_no . ': izaberi oba glavna igrača.';
                wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'error', $msg));
                exit;
            }

            if ($is_doubles && ($hp2 <= 0 || $ap2 <= 0 || $hp === $hp2 || $ap === $ap2)) {
                $msg = 'Partija #' . $order_no . ': dubl nije validan (proveri igrače 2).';
                wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'error', $msg));
                exit;
            }

            if (!$is_doubles) {
                $hp2 = 0;
                $ap2 = 0;
            }

            if (($hs + $as) === 0 && !empty($set_rows)) {
                $hs = $wins_home;
                $as = $wins_away;
            }

            $data = [
                'match_id' => $match_id,
                'order_no' => $order_no,
                'slug' => 'partija-' . $order_no,
                'is_doubles' => $is_doubles,
                'home_player_post_id' => $hp,
                'away_player_post_id' => $ap,
                'home_player2_post_id' => $is_doubles ? ($hp2 ?: null) : null,
                'away_player2_post_id' => $is_doubles ? ($ap2 ?: null) : null,
                'home_sets' => $hs,
                'away_sets' => $as,
                'updated_at' => current_time('mysql'),
            ];

            $game_id = 0;
            if ($existing) {
                $game_id = (int) $existing->id;
                $ok = $wpdb->update($games_table, $data, ['id' => $game_id]);
                if ($ok === false) {
                    $msg = 'Greška pri čuvanju partije #' . $order_no . '.';
                    wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'error', $msg));
                    exit;
                }
            } else {
                $data['created_at'] = current_time('mysql');
                $ok = $wpdb->insert($games_table, $data);
                if ($ok === false) {
                    $msg = 'Greška pri dodavanju partije #' . $order_no . '.';
                    wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'error', $msg));
                    exit;
                }
                $game_id = (int) $wpdb->insert_id;
            }

            $wpdb->delete($sets_table, ['game_id' => $game_id]);
            foreach ($set_rows as $sr) {
                $wpdb->insert($sets_table, [
                    'game_id' => $game_id,
                    'set_no' => (int) $sr['set_no'],
                    'home_points' => (int) $sr['home_points'],
                    'away_points' => (int) $sr['away_points'],
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
            }
        }

        $extra_game_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$games_table} WHERE match_id=%d AND order_no > %d", $match_id, $max_games)) ?: [];
        foreach ($extra_game_ids as $gid) {
            $wpdb->delete($sets_table, ['game_id' => (int) $gid]);
        }
        $wpdb->query($wpdb->prepare("DELETE FROM {$games_table} WHERE match_id=%d AND order_no > %d", $match_id, $max_games));
        wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'success', 'Sve partije su sačuvane.'));
        exit;
    }

    public static function handle_delete_game()
    {
        self::require_cap();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $match_id = isset($_GET['match_id']) ? (int) $_GET['match_id'] : 0;
        if ($id <= 0 || $match_id <= 0) {
            wp_die('Nedostaje ID.');
        }
        check_admin_referer('opentt_unified_delete_game_' . $id);

        global $wpdb;
        $games = OpenTT_Unified_Core::db_table('games');
        $sets = OpenTT_Unified_Core::db_table('sets');
        $wpdb->delete($sets, ['game_id' => $id]);
        $wpdb->delete($games, ['id' => $id]);

        wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'success', 'Partija je obrisana.'));
        exit;
    }

    public static function handle_save_set()
    {
        self::require_cap();
        check_admin_referer('opentt_unified_save_set');
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('sets');

        $match_id = isset($_POST['match_id']) ? (int) $_POST['match_id'] : 0;
        $game_id = isset($_POST['game_id']) ? (int) $_POST['game_id'] : 0;
        if ($match_id <= 0 || $game_id <= 0) {
            wp_die('Nedostaje ID.');
        }
        $set_no = max(1, (int) ($_POST['set_no'] ?? 1));
        $hp = max(0, (int) ($_POST['home_points'] ?? 0));
        $ap = max(0, (int) ($_POST['away_points'] ?? 0));

        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE game_id=%d AND set_no=%d LIMIT 1", $game_id, $set_no));
        if ($existing) {
            $wpdb->update($table, ['home_points' => $hp, 'away_points' => $ap, 'updated_at' => current_time('mysql')], ['id' => (int) $existing]);
        } else {
            $wpdb->insert($table, ['game_id' => $game_id, 'set_no' => $set_no, 'home_points' => $hp, 'away_points' => $ap, 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql')]);
        }

        wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'success', 'Set je sačuvan.'));
        exit;
    }

    public static function handle_delete_set()
    {
        self::require_cap();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $match_id = isset($_GET['match_id']) ? (int) $_GET['match_id'] : 0;
        if ($id <= 0 || $match_id <= 0) {
            wp_die('Nedostaje ID.');
        }
        check_admin_referer('opentt_unified_delete_set_' . $id);
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('sets');
        $wpdb->delete($table, ['id' => $id]);
        wp_safe_redirect(self::admin_notice_url(admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . $match_id), 'success', 'Set je obrisan.'));
        exit;
    }

    private static function require_cap()
    {
        if (!current_user_can(OpenTT_Unified_Core::CAP)) {
            wp_die('Nemaš dozvolu za ovu akciju.');
        }
    }

    private static function admin_notice_url($url, $type, $message)
    {
        return add_query_arg([
            'opentt_notice' => sanitize_key((string) $type),
            'opentt_msg' => (string) $message,
        ], $url);
    }

    private static function has_any_competition_rules()
    {
        $rows = get_posts([
            'post_type' => 'pravilo_takmicenja',
            'numberposts' => 1,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'fields' => 'ids',
        ]);
        return !empty($rows);
    }

    private static function get_competition_rule_post_by_slugs($liga_slug, $sezona_slug)
    {
        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);
        if ($liga_slug === '' || $sezona_slug === '') {
            return null;
        }
        $rows = get_posts([
            'post_type' => 'pravilo_takmicenja',
            'numberposts' => 1,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'meta_query' => [
                [
                    'key' => 'opentt_competition_league_slug',
                    'value' => $liga_slug,
                    'compare' => '=',
                ],
                [
                    'key' => 'opentt_competition_season_slug',
                    'value' => $sezona_slug,
                    'compare' => '=',
                ],
            ],
        ]);
        return !empty($rows) ? $rows[0] : null;
    }

    private static function match_competition_format($liga_slug, $sezona_slug)
    {
        $rule = OpenTT_Unified_Core::get_competition_rule_data($liga_slug, $sezona_slug);
        if (is_array($rule)) {
            $format = (string) ($rule['format_partija'] ?? '');
            if ($format === 'format_b') {
                return 'format_b';
            }
        }
        return 'format_a';
    }
}

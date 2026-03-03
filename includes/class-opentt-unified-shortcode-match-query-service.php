<?php

if (!defined('ABSPATH')) {
    exit;
}

final class OpenTT_Unified_Shortcode_Match_Query_Service
{
    public static function db_get_matches($args)
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        $games = $wpdb->prefix . 'stkb_games';

        $limit = isset($args['limit']) ? intval($args['limit']) : 5;
        $liga_slug = isset($args['liga_slug']) ? (string) $args['liga_slug'] : '';
        $sezona_slug = isset($args['sezona_slug']) ? (string) $args['sezona_slug'] : '';
        $kolo_slug = isset($args['kolo_slug']) ? (string) $args['kolo_slug'] : '';
        $played = isset($args['played']) ? (string) $args['played'] : '';
        $club_id = isset($args['club_id']) ? intval($args['club_id']) : 0;
        $player_id = isset($args['player_id']) ? intval($args['player_id']) : 0;

        $where = ['1=1'];
        $params = [];
        $join = '';
        $select = 'SELECT m.*';

        if ($player_id > 0) {
            $select = 'SELECT DISTINCT m.*';
            $join = " INNER JOIN {$games} g ON g.match_id = m.id ";
            $where[] = '(g.home_player_post_id=%d OR g.away_player_post_id=%d OR g.home_player2_post_id=%d OR g.away_player2_post_id=%d)';
            $params[] = $player_id;
            $params[] = $player_id;
            $params[] = $player_id;
            $params[] = $player_id;
        }

        if ($liga_slug !== '') {
            $where[] = 'm.liga_slug=%s';
            $params[] = $liga_slug;
        }
        if ($sezona_slug !== '') {
            $where[] = 'm.sezona_slug=%s';
            $params[] = $sezona_slug;
        }
        if ($kolo_slug !== '') {
            $where[] = 'm.kolo_slug=%s';
            $params[] = $kolo_slug;
        }
        if ($played === '0' || $played === '1') {
            $where[] = 'm.played=%d';
            $params[] = intval($played);
        }
        if ($club_id > 0) {
            $where[] = '(m.home_club_post_id=%d OR m.away_club_post_id=%d)';
            $params[] = $club_id;
            $params[] = $club_id;
        }

        $sql = $select . " FROM {$matches} m {$join} WHERE " . implode(' AND ', $where) . ' ORDER BY m.match_date DESC, m.id DESC';
        if ($limit !== -1) {
            $sql .= ' LIMIT %d';
            $params[] = max(1, $limit);
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql) ?: [];
    }

    public static function db_get_match_by_legacy_id($legacy_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'stkb_matches';
        $legacy_id = intval($legacy_id);
        if ($legacy_id <= 0 || !self::table_exists($table)) {
            return null;
        }
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE legacy_post_id=%d LIMIT 1", $legacy_id);
        $row = $wpdb->get_row($sql);
        return $row ?: null;
    }

    public static function db_get_match_by_keys($liga_slug, $sezona_slug, $kolo_slug, $slug)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'stkb_matches';
        if (!self::table_exists($table)) {
            return null;
        }

        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);
        $kolo_slug = sanitize_title((string) $kolo_slug);
        $slug = sanitize_title((string) $slug);
        if ($liga_slug === '' || $kolo_slug === '' || $slug === '') {
            return null;
        }

        if ($sezona_slug !== '') {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE liga_slug=%s AND sezona_slug=%s AND kolo_slug=%s AND slug=%s LIMIT 1",
                $liga_slug,
                $sezona_slug,
                $kolo_slug,
                $slug
            );
            $row = $wpdb->get_row($sql);
            if ($row) {
                return $row;
            }
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE liga_slug=%s AND kolo_slug=%s AND slug=%s LIMIT 1",
            $liga_slug,
            $kolo_slug,
            $slug
        );
        $row = $wpdb->get_row($sql);
        return $row ?: null;
    }

    public static function db_get_h2h_matches($current_match_db_id, $home_club_id, $away_club_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'stkb_matches';
        if (!self::table_exists($table)) {
            return [];
        }
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE id <> %d AND (
                 (home_club_post_id=%d AND away_club_post_id=%d)
                 OR
                 (home_club_post_id=%d AND away_club_post_id=%d)
             )
             ORDER BY match_date DESC, id DESC",
            intval($current_match_db_id),
            intval($home_club_id),
            intval($away_club_id),
            intval($away_club_id),
            intval($home_club_id)
        );
        return $wpdb->get_results($sql) ?: [];
    }

    public static function db_get_games_for_match_id($match_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'stkb_games';
        $match_id = intval($match_id);
        if ($match_id <= 0 || !self::table_exists($table)) {
            return [];
        }
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE match_id=%d ORDER BY order_no ASC, id ASC",
            $match_id
        );
        return $wpdb->get_results($sql) ?: [];
    }

    public static function db_get_sets_for_game_id($game_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'stkb_sets';
        $game_id = intval($game_id);
        if ($game_id <= 0 || !self::table_exists($table)) {
            return [];
        }
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE game_id=%d ORDER BY set_no ASC",
            $game_id
        );
        return $wpdb->get_results($sql) ?: [];
    }

    public static function db_get_latest_liga_for_club($club_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'stkb_matches';
        $club_id = intval($club_id);
        if ($club_id <= 0 || !self::table_exists($table)) {
            return '';
        }
        $sql = $wpdb->prepare(
            "SELECT liga_slug FROM {$table}
             WHERE home_club_post_id=%d OR away_club_post_id=%d
             ORDER BY match_date DESC, id DESC LIMIT 1",
            $club_id,
            $club_id
        );
        $slug = (string) $wpdb->get_var($sql);
        return sanitize_title($slug);
    }

    private static function table_exists($table_name)
    {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        return $found === $table_name;
    }
}

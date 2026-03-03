<?php

if (!defined('ABSPATH')) {
    exit;
}

final class OpenTT_Unified_Shortcode_Stats_Query_Service
{
    public static function db_get_top_players_data($liga_slug, $sezona_slug = '', $max_kolo = null)
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        $games = $wpdb->prefix . 'stkb_games';
        $sets = $wpdb->prefix . 'stkb_sets';

        if (!self::table_exists($matches) || !self::table_exists($games) || !self::table_exists($sets)) {
            return [];
        }

        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);
        if ($liga_slug === '') {
            return [];
        }

        $where = ['m.liga_slug=%s', 'g.is_doubles=0', 'g.home_player_post_id > 0', 'g.away_player_post_id > 0'];
        $params = [$liga_slug];
        if ($sezona_slug !== '') {
            $where[] = 'm.sezona_slug=%s';
            $params[] = $sezona_slug;
        }
        if ($max_kolo !== null) {
            $where[] = 'CAST(m.kolo_slug AS UNSIGNED) <= %d';
            $params[] = intval($max_kolo);
        }

        $sql = "SELECT g.*, m.home_club_post_id, m.away_club_post_id
                FROM {$games} g
                INNER JOIN {$matches} m ON m.id = g.match_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY g.id ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
        if (empty($rows)) {
            return [];
        }

        $game_ids = [];
        foreach ($rows as $row) {
            $gid = intval($row->id ?? 0);
            if ($gid > 0) {
                $game_ids[] = $gid;
            }
        }
        $game_ids = array_values(array_unique($game_ids));

        $set_sums = [];
        if (!empty($game_ids)) {
            $placeholders = implode(',', array_fill(0, count($game_ids), '%d'));
            $sum_sql = "SELECT game_id, SUM(home_points) AS home_points_sum, SUM(away_points) AS away_points_sum
                        FROM {$sets}
                        WHERE game_id IN ({$placeholders})
                        GROUP BY game_id";
            $sum_rows = $wpdb->get_results($wpdb->prepare($sum_sql, $game_ids)) ?: [];
            foreach ($sum_rows as $sum_row) {
                $gid = intval($sum_row->game_id ?? 0);
                if ($gid <= 0) {
                    continue;
                }
                $set_sums[$gid] = [
                    'home' => intval($sum_row->home_points_sum ?? 0),
                    'away' => intval($sum_row->away_points_sum ?? 0),
                ];
            }
        }

        $igraci = [];
        foreach ($rows as $g) {
            $igrac_domacin = intval($g->home_player_post_id);
            $igrac_gost = intval($g->away_player_post_id);
            if ($igrac_domacin <= 0 || $igrac_gost <= 0) {
                continue;
            }
            if (get_post_type($igrac_domacin) !== 'igrac' || get_post_type($igrac_gost) !== 'igrac') {
                continue;
            }

            $klub_domacina = intval($g->home_club_post_id);
            $klub_gosta = intval($g->away_club_post_id);

            if (!isset($igraci[$igrac_domacin])) {
                $igraci[$igrac_domacin] = ['pobede' => 0, 'porazi' => 0, 'poeni' => 0, 'klub' => $klub_domacina];
            }
            if (!isset($igraci[$igrac_gost])) {
                $igraci[$igrac_gost] = ['pobede' => 0, 'porazi' => 0, 'poeni' => 0, 'klub' => $klub_gosta];
            }

            $gid = intval($g->id);
            $igraci[$igrac_domacin]['poeni'] += intval($set_sums[$gid]['home'] ?? 0);
            $igraci[$igrac_gost]['poeni'] += intval($set_sums[$gid]['away'] ?? 0);

            $d_sets = intval($g->home_sets);
            $g_sets = intval($g->away_sets);
            if ($d_sets > $g_sets) {
                $igraci[$igrac_domacin]['pobede']++;
                $igraci[$igrac_gost]['porazi']++;
            } elseif ($g_sets > $d_sets) {
                $igraci[$igrac_gost]['pobede']++;
                $igraci[$igrac_domacin]['porazi']++;
            }
        }

        if (empty($igraci)) {
            return [];
        }

        $odigrane_po_klubu = self::db_get_played_matches_count_by_club($liga_slug, $sezona_slug, $max_kolo);
        $igraci = array_filter($igraci, function ($info) use ($odigrane_po_klubu) {
            $ukupno_partija = intval($info['pobede']) + intval($info['porazi']);
            $klub_id = intval($info['klub']);
            $odigrane = intval($odigrane_po_klubu[$klub_id] ?? 0);
            $maksimalno_moguce = $odigrane * 2;
            return $maksimalno_moguce > 0 && ($ukupno_partija / $maksimalno_moguce) >= 0.5;
        });

        uasort($igraci, function ($a, $b) {
            $a_total = intval($a['pobede']) + intval($a['porazi']);
            $b_total = intval($b['pobede']) + intval($b['porazi']);
            $a_proc = $a_total > 0 ? intval($a['pobede']) / $a_total : 0;
            $b_proc = $b_total > 0 ? intval($b['pobede']) / $b_total : 0;
            if ($a_proc !== $b_proc) {
                return ($b_proc <=> $a_proc);
            }
            if (intval($a['pobede']) !== intval($b['pobede'])) {
                return intval($b['pobede']) <=> intval($a['pobede']);
            }
            return intval($b['poeni']) <=> intval($a['poeni']);
        });

        return $igraci;
    }

    public static function db_get_played_matches_count_by_club($liga_slug, $sezona_slug = '', $max_kolo = null)
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        if (!self::table_exists($matches)) {
            return [];
        }

        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);
        if ($liga_slug === '') {
            return [];
        }

        $where = ['liga_slug=%s', 'played=1'];
        $params = [$liga_slug];
        if ($sezona_slug !== '') {
            $where[] = 'sezona_slug=%s';
            $params[] = $sezona_slug;
        }
        if ($max_kolo !== null) {
            $where[] = 'CAST(kolo_slug AS UNSIGNED) <= %d';
            $params[] = intval($max_kolo);
        }

        $sql = "SELECT home_club_post_id, away_club_post_id FROM {$matches} WHERE " . implode(' AND ', $where);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
        $counts = [];
        foreach ($rows as $row) {
            $home = intval($row->home_club_post_id);
            $away = intval($row->away_club_post_id);
            if ($home > 0) {
                $counts[$home] = intval($counts[$home] ?? 0) + 1;
            }
            if ($away > 0) {
                $counts[$away] = intval($counts[$away] ?? 0) + 1;
            }
        }
        return $counts;
    }

    public static function db_get_latest_competition_with_games()
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        $games = $wpdb->prefix . 'stkb_games';
        if (!self::table_exists($matches) || !self::table_exists($games)) {
            return null;
        }

        $sql = "SELECT m.liga_slug, m.sezona_slug
                FROM {$matches} m
                INNER JOIN {$games} g ON g.match_id = m.id
                WHERE m.liga_slug <> '' AND g.is_doubles=0
                ORDER BY m.match_date DESC, m.id DESC
                LIMIT 1";
        $row = $wpdb->get_row($sql);
        if (!$row) {
            return null;
        }

        return [
            'liga_slug' => sanitize_title((string) $row->liga_slug),
            'sezona_slug' => sanitize_title((string) $row->sezona_slug),
        ];
    }

    public static function db_get_latest_competition_for_player($player_id)
    {
        global $wpdb;
        $player_id = intval($player_id);
        if ($player_id <= 0) {
            return null;
        }

        $club_id = OpenTT_Unified_Admin_Readonly_Helpers::get_player_club_id($player_id);
        if ($club_id > 0) {
            $for_club = self::db_get_latest_competition_for_club($club_id);
            if ($for_club) {
                return $for_club;
            }
        }

        $matches = $wpdb->prefix . 'stkb_matches';
        $games = $wpdb->prefix . 'stkb_games';
        if (!self::table_exists($matches) || !self::table_exists($games)) {
            return null;
        }

        $sql = $wpdb->prepare(
            "SELECT m.liga_slug, m.sezona_slug
             FROM {$matches} m
             INNER JOIN {$games} g ON g.match_id = m.id
             WHERE m.liga_slug <> ''
               AND (
                    g.home_player_post_id=%d OR g.away_player_post_id=%d
                    OR g.home_player2_post_id=%d OR g.away_player2_post_id=%d
               )
             ORDER BY m.match_date DESC, m.id DESC
             LIMIT 1",
            $player_id,
            $player_id,
            $player_id,
            $player_id
        );
        $row = $wpdb->get_row($sql);
        if (!$row) {
            return null;
        }

        return [
            'liga_slug' => sanitize_title((string) $row->liga_slug),
            'sezona_slug' => sanitize_title((string) $row->sezona_slug),
        ];
    }

    public static function db_get_latest_competition_for_club($club_id)
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        $club_id = intval($club_id);
        if ($club_id <= 0 || !self::table_exists($matches)) {
            return null;
        }

        $sql = $wpdb->prepare(
            "SELECT liga_slug, sezona_slug FROM {$matches}
             WHERE (home_club_post_id=%d OR away_club_post_id=%d) AND liga_slug <> ''
             ORDER BY match_date DESC, id DESC LIMIT 1",
            $club_id,
            $club_id
        );
        $row = $wpdb->get_row($sql);
        if (!$row) {
            return null;
        }

        return [
            'liga_slug' => sanitize_title((string) $row->liga_slug),
            'sezona_slug' => sanitize_title((string) $row->sezona_slug),
        ];
    }

    public static function db_get_recent_club_matches($club_id, $limit = 5)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'stkb_matches';
        $club_id = intval($club_id);
        $limit = max(1, intval($limit));
        if ($club_id <= 0 || !self::table_exists($table)) {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE played=1 AND (home_club_post_id=%d OR away_club_post_id=%d)
             ORDER BY match_date DESC, id DESC
             LIMIT %d",
            $club_id,
            $club_id,
            $limit
        );
        $rows = $wpdb->get_results($sql);
        return is_array($rows) ? $rows : [];
    }

    public static function db_get_player_season_club_history($player_id)
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        $games = $wpdb->prefix . 'stkb_games';
        $player_id = intval($player_id);
        if ($player_id <= 0 || !self::table_exists($matches) || !self::table_exists($games)) {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT m.sezona_slug, m.home_club_post_id, m.away_club_post_id,
                    g.home_player_post_id, g.away_player_post_id, g.home_player2_post_id, g.away_player2_post_id
             FROM {$games} g
             INNER JOIN {$matches} m ON m.id = g.match_id
             WHERE m.sezona_slug <> ''
               AND (g.home_player_post_id=%d OR g.away_player_post_id=%d OR g.home_player2_post_id=%d OR g.away_player2_post_id=%d)",
            $player_id,
            $player_id,
            $player_id,
            $player_id
        );
        $rows = $wpdb->get_results($sql);
        if (empty($rows)) {
            return [];
        }

        $by_season = [];
        foreach ($rows as $r) {
            $season = sanitize_title((string) $r->sezona_slug);
            if ($season === '') {
                continue;
            }
            $club_id = 0;
            $is_home = in_array($player_id, [intval($r->home_player_post_id), intval($r->home_player2_post_id)], true);
            $is_away = in_array($player_id, [intval($r->away_player_post_id), intval($r->away_player2_post_id)], true);
            if ($is_home) {
                $club_id = intval($r->home_club_post_id);
            } elseif ($is_away) {
                $club_id = intval($r->away_club_post_id);
            }
            if ($club_id <= 0) {
                continue;
            }
            if (!isset($by_season[$season])) {
                $by_season[$season] = [];
            }
            if (!isset($by_season[$season][$club_id])) {
                $by_season[$season][$club_id] = 0;
            }
            $by_season[$season][$club_id]++;
        }

        $history = [];
        foreach ($by_season as $season => $clubs) {
            arsort($clubs);
            $club_id = intval(array_key_first($clubs));
            if ($club_id <= 0) {
                continue;
            }
            $history[] = [
                'season_slug' => $season,
                'club_id' => $club_id,
            ];
        }

        usort($history, function ($a, $b) {
            $ak = OpenTT_Unified_Readonly_Helpers::season_sort_key((string) ($a['season_slug'] ?? ''));
            $bk = OpenTT_Unified_Readonly_Helpers::season_sort_key((string) ($b['season_slug'] ?? ''));
            if ($ak === $bk) {
                return strnatcasecmp((string) ($a['season_slug'] ?? ''), (string) ($b['season_slug'] ?? ''));
            }
            return $ak <=> $bk;
        });

        return $history;
    }

    public static function db_get_player_stats($player_id, $season_slug = '')
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        $games = $wpdb->prefix . 'stkb_games';
        $player_id = intval($player_id);
        $season_slug = sanitize_title((string) $season_slug);

        if ($player_id <= 0 || !self::table_exists($matches) || !self::table_exists($games)) {
            return ['wins' => 0, 'losses' => 0];
        }

        $where = [];
        $params = [];
        $where[] = "(g.home_player_post_id=%d OR g.away_player_post_id=%d OR g.home_player2_post_id=%d OR g.away_player2_post_id=%d)";
        $params[] = $player_id;
        $params[] = $player_id;
        $params[] = $player_id;
        $params[] = $player_id;
        if ($season_slug !== '') {
            $where[] = "m.sezona_slug=%s";
            $params[] = $season_slug;
        }

        $sql = "SELECT g.home_player_post_id, g.away_player_post_id, g.home_player2_post_id, g.away_player2_post_id, g.home_sets, g.away_sets
                FROM {$games} g
                INNER JOIN {$matches} m ON m.id = g.match_id
                WHERE " . implode(' AND ', $where);
        $sql = $wpdb->prepare($sql, ...$params);
        $rows = $wpdb->get_results($sql);
        if (empty($rows)) {
            return ['wins' => 0, 'losses' => 0];
        }

        $wins = 0;
        $losses = 0;
        foreach ($rows as $r) {
            $is_home_side = in_array($player_id, [intval($r->home_player_post_id), intval($r->home_player2_post_id)], true);
            $is_away_side = in_array($player_id, [intval($r->away_player_post_id), intval($r->away_player2_post_id)], true);
            if (!$is_home_side && !$is_away_side) {
                continue;
            }
            $home_sets = intval($r->home_sets);
            $away_sets = intval($r->away_sets);
            if ($home_sets === $away_sets) {
                continue;
            }
            $won = ($is_home_side && $home_sets > $away_sets) || ($is_away_side && $away_sets > $home_sets);
            if ($won) {
                $wins++;
            } else {
                $losses++;
            }
        }

        return [
            'wins' => $wins,
            'losses' => $losses,
        ];
    }

    public static function db_get_player_season_options($player_id)
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        $games = $wpdb->prefix . 'stkb_games';
        $player_id = intval($player_id);
        if ($player_id <= 0 || !self::table_exists($matches) || !self::table_exists($games)) {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT DISTINCT m.sezona_slug
             FROM {$games} g
             INNER JOIN {$matches} m ON m.id = g.match_id
             WHERE m.sezona_slug <> ''
               AND (g.home_player_post_id=%d OR g.away_player_post_id=%d OR g.home_player2_post_id=%d OR g.away_player2_post_id=%d)",
            $player_id,
            $player_id,
            $player_id,
            $player_id
        );
        $rows = $wpdb->get_col($sql);
        $rows = array_filter(array_map('sanitize_title', is_array($rows) ? $rows : []));
        if (empty($rows)) {
            return [];
        }

        usort($rows, function ($a, $b) {
            if (preg_match('/^(\d{4})-(\d{2,4})$/', (string) $a, $ma) && preg_match('/^(\d{4})-(\d{2,4})$/', (string) $b, $mb)) {
                if ($ma[1] !== $mb[1]) {
                    return intval($mb[1]) <=> intval($ma[1]);
                }
            }
            return strnatcasecmp((string) $b, (string) $a);
        });

        return array_values(array_unique($rows));
    }

    public static function db_get_club_season_options($club_id)
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        $club_id = intval($club_id);
        if ($club_id <= 0 || !self::table_exists($matches)) {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT DISTINCT sezona_slug
             FROM {$matches}
             WHERE sezona_slug <> ''
               AND (home_club_post_id=%d OR away_club_post_id=%d)",
            $club_id,
            $club_id
        );
        $rows = $wpdb->get_col($sql);
        $rows = array_filter(array_map('sanitize_title', is_array($rows) ? $rows : []));
        if (empty($rows)) {
            return [];
        }

        usort($rows, function ($a, $b) {
            $ak = OpenTT_Unified_Readonly_Helpers::season_sort_key((string) $a);
            $bk = OpenTT_Unified_Readonly_Helpers::season_sort_key((string) $b);
            if ($ak === $bk) {
                return strnatcasecmp((string) $b, (string) $a);
            }
            return $bk <=> $ak;
        });

        return array_values(array_unique($rows));
    }

    public static function db_get_latest_liga_for_player_and_season($player_id, $season_slug = '')
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        $games = $wpdb->prefix . 'stkb_games';
        $player_id = intval($player_id);
        $season_slug = sanitize_title((string) $season_slug);
        if ($player_id <= 0 || !self::table_exists($matches) || !self::table_exists($games) || $season_slug === '') {
            return '';
        }

        $sql = $wpdb->prepare(
            "SELECT m.liga_slug
             FROM {$matches} m
             INNER JOIN {$games} g ON g.match_id = m.id
             WHERE m.liga_slug <> ''
               AND m.sezona_slug=%s
               AND (
                    g.home_player_post_id=%d OR g.away_player_post_id=%d
                    OR g.home_player2_post_id=%d OR g.away_player2_post_id=%d
               )
             ORDER BY m.match_date DESC, m.id DESC
             LIMIT 1",
            $season_slug,
            $player_id,
            $player_id,
            $player_id,
            $player_id
        );
        return sanitize_title((string) $wpdb->get_var($sql));
    }

    public static function db_get_latest_liga_for_club_and_season($club_id, $season_slug = '')
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        $club_id = intval($club_id);
        $season_slug = sanitize_title((string) $season_slug);
        if ($club_id <= 0 || !self::table_exists($matches)) {
            return '';
        }

        if ($season_slug !== '') {
            $sql = $wpdb->prepare(
                "SELECT liga_slug
                 FROM {$matches}
                 WHERE liga_slug <> '' AND sezona_slug=%s
                   AND (home_club_post_id=%d OR away_club_post_id=%d)
                 ORDER BY match_date DESC, id DESC
                 LIMIT 1",
                $season_slug,
                $club_id,
                $club_id
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT liga_slug
                 FROM {$matches}
                 WHERE liga_slug <> ''
                   AND (home_club_post_id=%d OR away_club_post_id=%d)
                 ORDER BY match_date DESC, id DESC
                 LIMIT 1",
                $club_id,
                $club_id
            );
        }

        return sanitize_title((string) $wpdb->get_var($sql));
    }

    public static function db_get_club_team_stats($club_id, $season_slug = '')
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        $games = $wpdb->prefix . 'stkb_games';
        $club_id = intval($club_id);
        $season_slug = sanitize_title((string) $season_slug);

        if ($club_id <= 0 || !self::table_exists($matches)) {
            return self::empty_team_stats();
        }

        $where = ['played=1', '(home_club_post_id=%d OR away_club_post_id=%d)'];
        $params = [$club_id, $club_id];
        if ($season_slug !== '') {
            $where[] = 'sezona_slug=%s';
            $params[] = $season_slug;
        }
        $sql = "SELECT * FROM {$matches} WHERE " . implode(' AND ', $where) . ' ORDER BY match_date ASC, id ASC';
        $sql = $wpdb->prepare($sql, ...$params);
        $rows = $wpdb->get_results($sql);
        if (empty($rows)) {
            return self::empty_team_stats();
        }

        $played = 0;
        $wins = 0;
        $losses = 0;
        $longest = 0;
        $current_streak = 0;
        $home_played = 0;
        $home_wins = 0;
        $away_played = 0;
        $away_wins = 0;

        foreach ($rows as $r) {
            $home_id = intval($r->home_club_post_id);
            $away_id = intval($r->away_club_post_id);
            if ($home_id !== $club_id && $away_id !== $club_id) {
                continue;
            }
            $played++;
            $home_score = intval($r->home_score);
            $away_score = intval($r->away_score);
            $is_home = ($home_id === $club_id);
            $won = ($is_home && $home_score > $away_score) || (!$is_home && $away_score > $home_score);
            $lost = ($is_home && $home_score < $away_score) || (!$is_home && $away_score < $home_score);

            if ($won) {
                $wins++;
                $current_streak++;
                if ($current_streak > $longest) {
                    $longest = $current_streak;
                }
            } elseif ($lost) {
                $losses++;
                $current_streak = 0;
            } else {
                $current_streak = 0;
            }

            if ($is_home) {
                $home_played++;
                if ($won) {
                    $home_wins++;
                }
            } else {
                $away_played++;
                if ($won) {
                    $away_wins++;
                }
            }
        }

        $doubles_win_pct = 0.0;
        if (self::table_exists($games)) {
            $game_where = ['(m.home_club_post_id=%d OR m.away_club_post_id=%d)', '(g.is_doubles=1 OR g.home_player2_post_id>0 OR g.away_player2_post_id>0)'];
            $game_params = [$club_id, $club_id];
            if ($season_slug !== '') {
                $game_where[] = 'm.sezona_slug=%s';
                $game_params[] = $season_slug;
            }
            $game_sql = "SELECT m.home_club_post_id, m.away_club_post_id, g.home_sets, g.away_sets
                         FROM {$games} g
                         INNER JOIN {$matches} m ON m.id = g.match_id
                         WHERE " . implode(' AND ', $game_where);
            $game_sql = $wpdb->prepare($game_sql, ...$game_params);
            $game_rows = $wpdb->get_results($game_sql);
            $doubles_played = 0;
            $doubles_wins = 0;
            foreach ($game_rows as $g) {
                $match_is_home = intval($g->home_club_post_id) === $club_id;
                $hs = intval($g->home_sets);
                $as = intval($g->away_sets);
                if ($hs === $as) {
                    continue;
                }
                $doubles_played++;
                if (($match_is_home && $hs > $as) || (!$match_is_home && $as > $hs)) {
                    $doubles_wins++;
                }
            }
            $doubles_win_pct = $doubles_played > 0 ? ($doubles_wins / $doubles_played) * 100.0 : 0.0;
        }

        return [
            'played' => $played,
            'wins' => $wins,
            'losses' => $losses,
            'longest_win_streak' => $longest,
            'home_win_pct' => $home_played > 0 ? ($home_wins / $home_played) * 100.0 : 0.0,
            'away_win_pct' => $away_played > 0 ? ($away_wins / $away_played) * 100.0 : 0.0,
            'doubles_win_pct' => $doubles_win_pct,
        ];
    }

    public static function db_get_club_season_best_player_by_success($club_id, $season_slug)
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        $games = $wpdb->prefix . 'stkb_games';
        $club_id = intval($club_id);
        $season_slug = sanitize_title((string) $season_slug);
        if ($club_id <= 0 || $season_slug === '' || !self::table_exists($matches) || !self::table_exists($games)) {
            return null;
        }

        $sql = $wpdb->prepare(
            "SELECT t.player_id, SUM(t.wins) AS wins, SUM(t.losses) AS losses
             FROM (
                SELECT g.home_player_post_id AS player_id,
                       CASE WHEN g.home_sets > g.away_sets THEN 1 ELSE 0 END AS wins,
                       CASE WHEN g.home_sets < g.away_sets THEN 1 ELSE 0 END AS losses
                FROM {$games} g
                INNER JOIN {$matches} m ON m.id = g.match_id
                WHERE m.played=1 AND m.sezona_slug=%s AND m.home_club_post_id=%d AND g.home_player_post_id > 0

                UNION ALL

                SELECT g.home_player2_post_id AS player_id,
                       CASE WHEN g.home_sets > g.away_sets THEN 1 ELSE 0 END AS wins,
                       CASE WHEN g.home_sets < g.away_sets THEN 1 ELSE 0 END AS losses
                FROM {$games} g
                INNER JOIN {$matches} m ON m.id = g.match_id
                WHERE m.played=1 AND m.sezona_slug=%s AND m.home_club_post_id=%d AND g.home_player2_post_id > 0

                UNION ALL

                SELECT g.away_player_post_id AS player_id,
                       CASE WHEN g.away_sets > g.home_sets THEN 1 ELSE 0 END AS wins,
                       CASE WHEN g.away_sets < g.home_sets THEN 1 ELSE 0 END AS losses
                FROM {$games} g
                INNER JOIN {$matches} m ON m.id = g.match_id
                WHERE m.played=1 AND m.sezona_slug=%s AND m.away_club_post_id=%d AND g.away_player_post_id > 0

                UNION ALL

                SELECT g.away_player2_post_id AS player_id,
                       CASE WHEN g.away_sets > g.home_sets THEN 1 ELSE 0 END AS wins,
                       CASE WHEN g.away_sets < g.home_sets THEN 1 ELSE 0 END AS losses
                FROM {$games} g
                INNER JOIN {$matches} m ON m.id = g.match_id
                WHERE m.played=1 AND m.sezona_slug=%s AND m.away_club_post_id=%d AND g.away_player2_post_id > 0
             ) t
             GROUP BY t.player_id
             HAVING (SUM(t.wins) + SUM(t.losses)) > 0
             ORDER BY (SUM(t.wins) / (SUM(t.wins) + SUM(t.losses))) DESC, (SUM(t.wins) + SUM(t.losses)) DESC, SUM(t.wins) DESC, t.player_id ASC
             LIMIT 1",
            $season_slug,
            $club_id,
            $season_slug,
            $club_id,
            $season_slug,
            $club_id,
            $season_slug,
            $club_id
        );
        $row = $wpdb->get_row($sql);
        if (!$row) {
            return null;
        }

        $player_id = intval($row->player_id ?? 0);
        $wins = intval($row->wins ?? 0);
        $losses = intval($row->losses ?? 0);
        $played = $wins + $losses;
        if ($player_id <= 0 || $played <= 0) {
            return null;
        }
        $success_pct = ($wins / $played) * 100.0;

        return [
            'player_id' => $player_id,
            'wins' => $wins,
            'losses' => $losses,
            'success_pct' => $success_pct,
            'season_slug' => $season_slug,
        ];
    }

    public static function db_get_competition_club_ids($liga_slug, $sezona_slug = '')
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        if (!self::table_exists($matches)) {
            return [];
        }

        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);
        if ($liga_slug === '') {
            return [];
        }

        $where = ['liga_slug=%s'];
        $params = [$liga_slug];
        if ($sezona_slug !== '') {
            $where[] = 'sezona_slug=%s';
            $params[] = $sezona_slug;
        }
        $where_sql = implode(' AND ', $where);

        $home_sql = "SELECT DISTINCT home_club_post_id AS club_id FROM {$matches} WHERE {$where_sql} AND home_club_post_id > 0";
        $away_sql = "SELECT DISTINCT away_club_post_id AS club_id FROM {$matches} WHERE {$where_sql} AND away_club_post_id > 0";
        $sql = "SELECT DISTINCT club_id FROM (({$home_sql}) UNION ({$away_sql})) clubs ORDER BY club_id ASC";

        $prepared = $wpdb->prepare($sql, ...array_merge($params, $params));
        $rows = $wpdb->get_col($prepared);
        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        $club_ids = array_map('intval', $rows);
        return array_values(array_filter($club_ids, static function ($id) {
            return $id > 0 && get_post_type($id) === 'klub';
        }));
    }

    public static function db_get_player_mvp_count($player_id, $season_slug = '')
    {
        global $wpdb;
        $matches = $wpdb->prefix . 'stkb_matches';
        $games = $wpdb->prefix . 'stkb_games';
        $player_id = intval($player_id);
        $season_slug = sanitize_title((string) $season_slug);
        if ($player_id <= 0 || !self::table_exists($matches) || !self::table_exists($games)) {
            return 0;
        }

        $where = [];
        $params = [];
        $where[] = "(g.home_player_post_id=%d OR g.away_player_post_id=%d OR g.home_player2_post_id=%d OR g.away_player2_post_id=%d)";
        $params[] = $player_id;
        $params[] = $player_id;
        $params[] = $player_id;
        $params[] = $player_id;
        if ($season_slug !== '') {
            $where[] = "m.sezona_slug=%s";
            $params[] = $season_slug;
        }

        $sql = "SELECT DISTINCT m.id
                FROM {$matches} m
                INNER JOIN {$games} g ON g.match_id = m.id
                WHERE " . implode(' AND ', $where);
        $sql = $wpdb->prepare($sql, ...$params);
        $match_ids = $wpdb->get_col($sql);
        if (empty($match_ids)) {
            return 0;
        }

        $count = 0;
        foreach ($match_ids as $mid) {
            $mvp_id = self::db_get_match_mvp_player_id((int) $mid);
            if ($mvp_id === $player_id) {
                $count++;
            }
        }
        return $count;
    }

    public static function db_get_match_mvp_player_id($match_id)
    {
        $match_id = intval($match_id);
        if ($match_id <= 0) {
            return 0;
        }

        $games = OpenTT_Unified_Shortcode_Match_Query_Service::db_get_games_for_match_id($match_id);
        if (empty($games)) {
            return 0;
        }

        $stat = [];
        foreach ($games as $g) {
            if (intval($g->is_doubles) === 1 || intval($g->home_player2_post_id) > 0 || intval($g->away_player2_post_id) > 0) {
                continue;
            }

            $pid_home = intval($g->home_player_post_id);
            $pid_away = intval($g->away_player_post_id);
            if ($pid_home <= 0 || $pid_away <= 0) {
                continue;
            }

            $d_set = intval($g->home_sets);
            $g_set = intval($g->away_sets);
            if ($d_set === 0 && $g_set === 0) {
                continue;
            }

            $sets = OpenTT_Unified_Shortcode_Match_Query_Service::db_get_sets_for_game_id(intval($g->id));
            $poeni_d = 0;
            $poeni_g = 0;
            foreach ($sets as $set) {
                $poeni_d += intval($set->home_points);
                $poeni_g += intval($set->away_points);
            }

            if (!isset($stat[$pid_home])) {
                $stat[$pid_home] = ['pobede' => 0, 'setovi' => 0, 'poeni' => 0];
            }
            if (!isset($stat[$pid_away])) {
                $stat[$pid_away] = ['pobede' => 0, 'setovi' => 0, 'poeni' => 0];
            }

            if ($d_set > $g_set) {
                $stat[$pid_home]['pobede'] += 1;
            } else {
                $stat[$pid_away]['pobede'] += 1;
            }
            $stat[$pid_home]['setovi'] += ($d_set - $g_set);
            $stat[$pid_away]['setovi'] += ($g_set - $d_set);
            $stat[$pid_home]['poeni'] += ($poeni_d - $poeni_g);
            $stat[$pid_away]['poeni'] += ($poeni_g - $poeni_d);
        }

        if (empty($stat)) {
            return 0;
        }

        uasort($stat, function ($a, $b) {
            if ($a['pobede'] !== $b['pobede']) {
                return $b['pobede'] - $a['pobede'];
            }
            if ($a['setovi'] !== $b['setovi']) {
                return $b['setovi'] - $a['setovi'];
            }
            return $b['poeni'] - $a['poeni'];
        });

        return intval(array_key_first($stat));
    }

    private static function empty_team_stats()
    {
        return [
            'played' => 0,
            'wins' => 0,
            'losses' => 0,
            'longest_win_streak' => 0,
            'home_win_pct' => 0,
            'away_win_pct' => 0,
            'doubles_win_pct' => 0,
        ];
    }

    private static function table_exists($table_name)
    {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        return $found === $table_name;
    }
}

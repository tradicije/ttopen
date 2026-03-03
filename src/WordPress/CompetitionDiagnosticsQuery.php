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

namespace OpenTT\Unified\WordPress;

final class CompetitionDiagnosticsQuery
{
    public static function roundDiagnostics($leagueSlug, $seasonSlug)
    {
        global $wpdb;

        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        $gamesTable = \OpenTT_Unified_Core::db_table('games');
        $leagueSlug = sanitize_title((string) $leagueSlug);
        $seasonSlug = sanitize_title((string) $seasonSlug);
        if (
            $leagueSlug === ''
            || $seasonSlug === ''
            || !self::tableExists($matchesTable)
            || !self::tableExists($gamesTable)
        ) {
            return [];
        }

        $sql = $wpdb->prepare(
            "SELECT
                m.kolo_slug AS kolo_slug,
                COUNT(*) AS matches_total,
                SUM(CASE WHEN m.played=1 THEN 1 ELSE 0 END) AS matches_played,
                SUM(CASE WHEN (m.home_score + m.away_score) > 0 THEN 1 ELSE 0 END) AS matches_with_score,
                SUM(COALESCE(g.cnt_games, 0)) AS games_total
             FROM {$matchesTable} m
             LEFT JOIN (
                SELECT match_id, COUNT(*) AS cnt_games
                FROM {$gamesTable}
                GROUP BY match_id
             ) g ON g.match_id = m.id
             WHERE m.liga_slug=%s AND m.sezona_slug=%s
             GROUP BY m.kolo_slug
             ORDER BY CAST(m.kolo_slug AS UNSIGNED) ASC, m.kolo_slug ASC",
            $leagueSlug,
            $seasonSlug
        );
        $rows = $wpdb->get_results($sql) ?: [];
        if (empty($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'kolo_slug' => sanitize_title((string) ($row->kolo_slug ?? '')),
                'matches_total' => intval($row->matches_total ?? 0),
                'matches_played' => intval($row->matches_played ?? 0),
                'matches_with_score' => intval($row->matches_with_score ?? 0),
                'games_total' => intval($row->games_total ?? 0),
            ];
        }

        return $out;
    }

    private static function tableExists($tableName)
    {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName));
        return $found === $tableName;
    }
}

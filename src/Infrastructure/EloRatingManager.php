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

namespace OpenTT\Unified\Infrastructure;

final class EloRatingManager
{
    public const META_KEY = 'opentt_elo_rating';
    public const META_KEY_SCOPED = 'opentt_elo_ratings';
    public const OPTION_BACKFILL_STATE = 'opentt_elo_backfill_state_v1';
    public const DEFAULT_RATING = 1500;
    public const K_FACTOR = 32.0;

    public static function getPlayerRating($playerId, $ligaSlug = '', $sezonaSlug = '')
    {
        $playerId = (int) $playerId;
        if ($playerId <= 0) {
            return self::DEFAULT_RATING;
        }

        $ligaSlug = sanitize_title((string) $ligaSlug);
        $sezonaSlug = sanitize_title((string) $sezonaSlug);
        $ratings = self::getPlayerRatingsMap($playerId);
        $scopeKey = self::scopeKey($ligaSlug, $sezonaSlug);

        if ($scopeKey !== '' && isset($ratings[$scopeKey])) {
            return (int) round((float) $ratings[$scopeKey]);
        }

        if ($scopeKey === '') {
            $resolved = self::resolveRequestScope();
            $resolvedKey = self::scopeKey((string) ($resolved['liga_slug'] ?? ''), (string) ($resolved['sezona_slug'] ?? ''));
            if ($resolvedKey !== '' && isset($ratings[$resolvedKey])) {
                return (int) round((float) $ratings[$resolvedKey]);
            }
        }

        $legacy = get_post_meta($playerId, self::META_KEY, true);
        if ($legacy !== '' && $legacy !== null) {
            return (int) round((float) $legacy);
        }

        return self::DEFAULT_RATING;
    }

    public static function getPlayerRatingsMap($playerId)
    {
        $playerId = (int) $playerId;
        if ($playerId <= 0) {
            return [];
        }

        $raw = get_post_meta($playerId, self::META_KEY_SCOPED, true);
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $key => $value) {
            $scope = sanitize_key((string) $key);
            if ($scope === '') {
                continue;
            }
            $out[$scope] = (int) round((float) $value);
        }

        return $out;
    }

    public static function updateAfterMatch($playerAId, $playerBId, $winnerId, $kFactor = self::K_FACTOR, $ligaSlug = '', $sezonaSlug = '')
    {
        $playerAId = (int) $playerAId;
        $playerBId = (int) $playerBId;
        $winnerId = (int) $winnerId;
        $kFactor = (float) $kFactor;
        $ligaSlug = sanitize_title((string) $ligaSlug);
        $sezonaSlug = sanitize_title((string) $sezonaSlug);
        $scopeKey = self::scopeKey($ligaSlug, $sezonaSlug);

        if ($playerAId <= 0 || $playerBId <= 0 || $playerAId === $playerBId) {
            return null;
        }
        if ($winnerId !== $playerAId && $winnerId !== $playerBId) {
            return null;
        }
        if ($kFactor <= 0) {
            $kFactor = self::K_FACTOR;
        }
        if ($scopeKey === '') {
            return null;
        }

        $ratingA = self::getPlayerRating($playerAId, $ligaSlug, $sezonaSlug);
        $ratingB = self::getPlayerRating($playerBId, $ligaSlug, $sezonaSlug);

        // Expected scores based on current ratings.
        $expectedA = 1.0 / (1.0 + pow(10.0, (($ratingB - $ratingA) / 400.0)));
        $expectedB = 1.0 - $expectedA;

        // Actual scores from winner perspective.
        $scoreA = ($winnerId === $playerAId) ? 1.0 : 0.0;
        $scoreB = 1.0 - $scoreA;

        // New ratings by standard ELO formula.
        $newRatingA = (int) round($ratingA + ($kFactor * ($scoreA - $expectedA)));
        $newRatingB = (int) round($ratingB + ($kFactor * ($scoreB - $expectedB)));

        $ratingsA = self::getPlayerRatingsMap($playerAId);
        $ratingsB = self::getPlayerRatingsMap($playerBId);
        $ratingsA[$scopeKey] = $newRatingA;
        $ratingsB[$scopeKey] = $newRatingB;
        update_post_meta($playerAId, self::META_KEY_SCOPED, $ratingsA);
        update_post_meta($playerBId, self::META_KEY_SCOPED, $ratingsB);

        return [
            'player_a_id' => $playerAId,
            'player_b_id' => $playerBId,
            'winner_id' => $winnerId,
            'scope_key' => $scopeKey,
            'liga_slug' => $ligaSlug,
            'sezona_slug' => $sezonaSlug,
            'old_a' => $ratingA,
            'old_b' => $ratingB,
            'new_a' => $newRatingA,
            'new_b' => $newRatingB,
            'expected_a' => $expectedA,
            'expected_b' => $expectedB,
            'k' => $kFactor,
        ];
    }

    public static function maybeBackfillHistoricalRatings($force = false)
    {
        $force = (bool) $force;
        $state = get_option(self::OPTION_BACKFILL_STATE, []);
        if (!$force && is_array($state) && !empty($state['done'])) {
            return $state;
        }

        global $wpdb;
        $matches = \OpenTT_Unified_Core::db_table('matches');
        $games = \OpenTT_Unified_Core::db_table('games');
        if (!self::tableExists($matches) || !self::tableExists($games)) {
            return ['done' => 0, 'reason' => 'missing_tables'];
        }

        $rows = $wpdb->get_results(
            "SELECT
                m.liga_slug,
                m.sezona_slug,
                g.home_player_post_id,
                g.away_player_post_id,
                g.home_sets,
                g.away_sets
             FROM {$games} g
             INNER JOIN {$matches} m ON m.id = g.match_id
             WHERE g.is_doubles = 0
               AND g.home_player_post_id > 0
               AND g.away_player_post_id > 0
               AND m.liga_slug <> ''
               AND m.sezona_slug <> ''
               AND g.home_sets <> g.away_sets
             ORDER BY
               COALESCE(m.match_date, m.created_at, m.updated_at) ASC,
               m.id ASC,
               g.order_no ASC,
               g.id ASC"
        ) ?: [];

        if (empty($rows)) {
            $state = [
                'done' => 1,
                'processed_games' => 0,
                'updated_players' => 0,
                'generated_at' => current_time('mysql'),
            ];
            update_option(self::OPTION_BACKFILL_STATE, $state, false);
            return $state;
        }

        $player_ids = [];
        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }
            $home = (int) ($row->home_player_post_id ?? 0);
            $away = (int) ($row->away_player_post_id ?? 0);
            if ($home > 0) {
                $player_ids[$home] = true;
            }
            if ($away > 0) {
                $player_ids[$away] = true;
            }
        }

        foreach (array_keys($player_ids) as $pid) {
            delete_post_meta((int) $pid, self::META_KEY_SCOPED);
        }

        $ratings = [];
        $processed_games = 0;

        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }

            $home = (int) ($row->home_player_post_id ?? 0);
            $away = (int) ($row->away_player_post_id ?? 0);
            $home_sets = (int) ($row->home_sets ?? 0);
            $away_sets = (int) ($row->away_sets ?? 0);
            $liga = sanitize_title((string) ($row->liga_slug ?? ''));
            $sezona = sanitize_title((string) ($row->sezona_slug ?? ''));
            $scope = self::scopeKey($liga, $sezona);

            if ($home <= 0 || $away <= 0 || $scope === '') {
                continue;
            }
            if ($home_sets === $away_sets) {
                continue;
            }

            if (!isset($ratings[$home])) {
                $ratings[$home] = [];
            }
            if (!isset($ratings[$away])) {
                $ratings[$away] = [];
            }
            if (!isset($ratings[$home][$scope])) {
                $ratings[$home][$scope] = self::DEFAULT_RATING;
            }
            if (!isset($ratings[$away][$scope])) {
                $ratings[$away][$scope] = self::DEFAULT_RATING;
            }

            $rating_home = (int) $ratings[$home][$scope];
            $rating_away = (int) $ratings[$away][$scope];
            $expected_home = 1.0 / (1.0 + pow(10.0, (($rating_away - $rating_home) / 400.0)));
            $expected_away = 1.0 - $expected_home;
            $score_home = $home_sets > $away_sets ? 1.0 : 0.0;
            $score_away = 1.0 - $score_home;

            $ratings[$home][$scope] = (int) round($rating_home + (self::K_FACTOR * ($score_home - $expected_home)));
            $ratings[$away][$scope] = (int) round($rating_away + (self::K_FACTOR * ($score_away - $expected_away)));
            $processed_games++;
        }

        foreach ($ratings as $pid => $map) {
            update_post_meta((int) $pid, self::META_KEY_SCOPED, $map);
        }

        $state = [
            'done' => 1,
            'processed_games' => $processed_games,
            'updated_players' => count($ratings),
            'generated_at' => current_time('mysql'),
        ];
        update_option(self::OPTION_BACKFILL_STATE, $state, false);
        return $state;
    }

    private static function scopeKey($ligaSlug, $sezonaSlug)
    {
        $ligaSlug = sanitize_title((string) $ligaSlug);
        $sezonaSlug = sanitize_title((string) $sezonaSlug);
        if ($ligaSlug === '' || $sezonaSlug === '') {
            return '';
        }
        return sanitize_key($ligaSlug . '|' . $sezonaSlug);
    }

    private static function resolveRequestScope()
    {
        $liga = sanitize_title((string) (get_query_var('liga') ?: ''));
        $sezona = sanitize_title((string) (get_query_var('sezona') ?: ''));
        return [
            'liga_slug' => $liga,
            'sezona_slug' => $sezona,
        ];
    }

    private static function tableExists($table)
    {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', (string) $table));
        return (string) $found === (string) $table;
    }
}

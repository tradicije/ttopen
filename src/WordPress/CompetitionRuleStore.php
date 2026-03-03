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

final class CompetitionRuleStore
{
    public static function findBySlugs($leagueSlug, $seasonSlug)
    {
        $leagueSlug = sanitize_title((string) $leagueSlug);
        $seasonSlug = sanitize_title((string) $seasonSlug);
        if ($leagueSlug === '' || $seasonSlug === '') {
            return null;
        }

        $rows = get_posts([
            'post_type' => 'pravilo_takmicenja',
            'numberposts' => 1,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'meta_query' => [
                [
                    'key' => 'opentt_competition_league_slug',
                    'value' => $leagueSlug,
                    'compare' => '=',
                ],
                [
                    'key' => 'opentt_competition_season_slug',
                    'value' => $seasonSlug,
                    'compare' => '=',
                ],
            ],
        ]);

        return !empty($rows) ? $rows[0] : null;
    }

    public static function ensureLeagueEntity($leagueSlug, $leagueName = '', ?callable $slugToTitle = null)
    {
        $leagueSlug = sanitize_title((string) $leagueSlug);
        if ($leagueSlug === '') {
            return 0;
        }

        $rows = get_posts([
            'post_type' => 'liga',
            'name' => $leagueSlug,
            'numberposts' => 1,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]);
        if (!empty($rows)) {
            return (int) $rows[0]->ID;
        }

        $title = sanitize_text_field((string) $leagueName);
        if ($title === '') {
            $title = $slugToTitle ? (string) $slugToTitle($leagueSlug) : (string) $leagueSlug;
        }

        $id = wp_insert_post([
            'post_type' => 'liga',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_name' => $leagueSlug,
        ], true);

        return (!is_wp_error($id) && $id) ? (int) $id : 0;
    }

    public static function ensureSeasonEntity($seasonSlug, $seasonName = '', ?callable $slugToTitle = null)
    {
        $seasonSlug = sanitize_title((string) $seasonSlug);
        if ($seasonSlug === '') {
            return 0;
        }

        $rows = get_posts([
            'post_type' => 'sezona',
            'name' => $seasonSlug,
            'numberposts' => 1,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]);
        if (!empty($rows)) {
            return (int) $rows[0]->ID;
        }

        $title = sanitize_text_field((string) $seasonName);
        if ($title === '') {
            $title = $slugToTitle ? (string) $slugToTitle($seasonSlug) : (string) $seasonSlug;
        }

        $id = wp_insert_post([
            'post_type' => 'sezona',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_name' => $seasonSlug,
        ], true);

        return (!is_wp_error($id) && $id) ? (int) $id : 0;
    }
}

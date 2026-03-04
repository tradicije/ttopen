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

final class ShortcodeRegistrar
{
    public static function register($handlerClass)
    {
        $map = [
            'opentt_matches_grid' => 'shortcode_matches_grid',
            'opentt_featured_match' => 'shortcode_featured_match',
            'opentt_standings_table' => 'shortcode_standings_table',
            'opentt_match_games' => 'shortcode_games_list',
            'opentt_h2h' => 'shortcode_h2h',
            'opentt_mvp' => 'shortcode_mvp',
            'opentt_match_report' => 'shortcode_match_report',
            'opentt_match_video' => 'shortcode_match_video',
            'opentt_home_club' => 'shortcode_show_home_club',
            'opentt_away_club' => 'shortcode_show_away_club',
            'opentt_club' => 'shortcode_show_club_by_name',
            'opentt_match_teams' => 'shortcode_show_match_teams',
            'opentt_top_players' => 'shortcode_top_players_list',
            'opentt_players' => 'shortcode_show_players',
            'opentt_club_news' => 'shortcode_club_news',
            'opentt_player_news' => 'shortcode_player_news',
            'opentt_related_posts' => 'shortcode_related_posts',
            'opentt_club_info' => 'shortcode_club_info',
            'opentt_competition_info' => 'shortcode_competition_info',
            'opentt_club_form' => 'shortcode_club_form',
            'opentt_player_stats' => 'shortcode_player_stats',
            'opentt_team_stats' => 'shortcode_team_stats',
            'opentt_player_transfers' => 'shortcode_player_transfers',
            'opentt_player_info' => 'shortcode_player_info',
            'opentt_competitions' => 'shortcode_competitions_grid',
            'opentt_clubs' => 'shortcode_clubs_grid',
        ];

        foreach ($map as $tag => $callback) {
            remove_shortcode($tag);
            add_shortcode($tag, [$handlerClass, $callback]);
        }
    }
}

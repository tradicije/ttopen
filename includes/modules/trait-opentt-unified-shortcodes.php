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

trait OpenTT_Unified_Shortcodes_Trait
{
    private static function shortcode_title_html($title)
    {
        $title = trim((string) $title);
        if ($title === '') {
            return '';
        }
        if (class_exists('OpenTT_Unified_Core') && method_exists('OpenTT_Unified_Core', 'should_show_shortcode_titles') && !OpenTT_Unified_Core::should_show_shortcode_titles()) {
            return '';
        }
        return '<h3 class="opentt-shortcode-title">' . esc_html($title) . '</h3>';
    }

    public static function shortcode_matches_grid($atts)
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MatchesGridShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'build_match_query_args' => static function ($args) {
                return self::build_match_query_args($args);
            },
            'db_get_matches' => static function ($args) {
                return self::db_get_matches($args);
            },
            'kolo_name_from_slug' => static function ($slug) {
                return self::kolo_name_from_slug($slug);
            },
            'extract_round_no' => static function ($slug) {
                return self::extract_round_no($slug);
            },
            'render_matches_grid_html' => static function ($rows, $columns, $with_kolo_attr) {
                return self::render_matches_grid_html($rows, $columns, $with_kolo_attr);
            },
        ]);
    }

    public static function shortcode_matches_list($atts)
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MatchesListShortcode::render($atts, [
            'build_match_query_args' => static function ($args) {
                return self::build_match_query_args($args);
            },
            'db_get_matches' => static function ($args) {
                return self::db_get_matches($args);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'display_match_date' => static function ($match_date) {
                return self::display_match_date($match_date);
            },
            'match_permalink' => static function ($row) {
                return self::match_permalink($row);
            },
        ]);
    }

    public static function shortcode_featured_match($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\FeaturedMatchShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'match_permalink' => static function ($row) {
                return self::match_permalink($row);
            },
            'kolo_name_from_slug' => static function ($slug) {
                return self::kolo_name_from_slug($slug);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'current_archive_context' => static function () {
                return self::current_archive_context();
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'db_build_standings_for_competition' => static function ($liga_slug, $sezona_slug = '', $max_kolo = null) {
                return self::db_build_standings_for_competition($liga_slug, $sezona_slug, $max_kolo);
            },
            'build_match_query_args' => static function ($args) {
                return self::build_match_query_args($args);
            },
        ]);
    }

    public static function shortcode_clubs_grid($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ClubsGridShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'db_get_latest_competition_for_club' => static function ($club_id) {
                return self::db_get_latest_competition_for_club($club_id);
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'render_clubs_grid_html' => static function ($rows, $columns, $with_attrs) {
                return self::render_clubs_grid_html($rows, $columns, $with_attrs);
            },
        ]);
    }

    public static function shortcode_show_players($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ShowPlayersShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'player_fallback_image_url' => static function () {
                return self::player_fallback_image_url();
            },
        ]);
    }

    public static function shortcode_club_news($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ClubNewsShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_player_news($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\PlayerNewsShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_related_posts($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\RelatedPostsShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_standings_table($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\StandingsTableShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'current_archive_context' => static function () {
                return self::current_archive_context();
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'db_get_match_by_legacy_id' => static function ($legacy_id) {
                return self::db_get_match_by_legacy_id($legacy_id);
            },
            'extract_round_no' => static function ($slug) {
                return self::extract_round_no($slug);
            },
            'db_get_latest_liga_for_club' => static function ($club_id) {
                return self::db_get_latest_liga_for_club($club_id);
            },
            'db_table' => static function ($table_alias) {
                return OpenTT_Unified_Core::db_table($table_alias);
            },
            'table_exists' => static function ($table_name) {
                return self::table_exists($table_name);
            },
            'db_get_matches' => static function ($args) {
                return self::db_get_matches($args);
            },
            'get_competition_rule_data' => static function ($liga_slug, $sezona_slug = '') {
                return self::get_competition_rule_data($liga_slug, $sezona_slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
        ]);
    }

    public static function shortcode_games_list($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\GamesListShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'db_get_games_for_match_id' => static function ($match_id) {
                return self::db_get_games_for_match_id($match_id);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'get_competition_rule_data' => static function ($liga_slug, $sezona_slug = '') {
                return self::get_competition_rule_data($liga_slug, $sezona_slug);
            },
            'db_get_sets_for_game_id' => static function ($game_id) {
                return self::db_get_sets_for_game_id($game_id);
            },
            'render_lp2_player' => static function ($player_id) {
                return self::render_lp2_player($player_id);
            },
        ]);
    }

    public static function shortcode_h2h($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\H2hShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'db_get_h2h_matches' => static function ($current_match_db_id, $home_club_id, $away_club_id) {
                return self::db_get_h2h_matches($current_match_db_id, $home_club_id, $away_club_id);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'club_logo_url' => static function ($club_id, $size = 'thumbnail') {
                return self::club_logo_url($club_id, $size);
            },
            'kolo_name_from_slug' => static function ($slug) {
                return self::kolo_name_from_slug($slug);
            },
            'display_match_date_long' => static function ($match_date) {
                return self::display_match_date_long($match_date);
            },
            'match_permalink' => static function ($row) {
                return self::match_permalink($row);
            },
        ]);
    }

    public static function shortcode_mvp($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MvpShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'db_get_games_for_match_id' => static function ($match_id) {
                return self::db_get_games_for_match_id($match_id);
            },
            'db_get_sets_for_game_id' => static function ($game_id) {
                return self::db_get_sets_for_game_id($game_id);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'player_fallback_image_url' => static function () {
                return self::player_fallback_image_url();
            },
            'club_logo_url' => static function ($club_id, $size = 'thumbnail') {
                return self::club_logo_url($club_id, $size);
            },
        ]);
    }

    public static function shortcode_match_report($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MatchReportShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_match_video($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MatchVideoShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_show_home_club($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ShowHomeClubShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'render_klub_card_html' => static function ($club_id) {
                return self::render_klub_card_html($club_id);
            },
        ]);
    }

    public static function shortcode_show_away_club($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ShowAwayClubShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'render_klub_card_html' => static function ($club_id) {
                return self::render_klub_card_html($club_id);
            },
        ]);
    }

    public static function shortcode_show_club_by_name($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ShowClubByNameShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'render_klub_card_html' => static function ($club_id) {
                return self::render_klub_card_html($club_id);
            },
        ]);
    }

    public static function shortcode_club_form($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ClubFormShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'db_get_recent_club_matches' => static function ($club_id, $limit) {
                return self::db_get_recent_club_matches($club_id, $limit);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'display_match_date' => static function ($match_date) {
                return self::display_match_date($match_date);
            },
            'match_permalink' => static function ($row) {
                return self::match_permalink($row);
            },
        ]);
    }

    public static function shortcode_player_stats($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\PlayerStatsShortcode::render($atts, [
            'db_get_player_season_options' => static function ($player_id) {
                return self::db_get_player_season_options($player_id);
            },
            'db_get_player_stats' => static function ($player_id, $season_slug = '') {
                return self::db_get_player_stats($player_id, $season_slug);
            },
            'db_get_player_mvp_count' => static function ($player_id, $season_slug = '') {
                return self::db_get_player_mvp_count($player_id, $season_slug);
            },
            'season_display_name' => static function ($sezona_slug) {
                return self::season_display_name($sezona_slug);
            },
            'db_get_latest_competition_for_player' => static function ($player_id) {
                return self::db_get_latest_competition_for_player($player_id);
            },
            'db_get_latest_liga_for_player_and_season' => static function ($player_id, $season_slug = '') {
                return self::db_get_latest_liga_for_player_and_season($player_id, $season_slug);
            },
            'db_get_top_players_data' => static function ($liga_slug, $sezona_slug = '', $max_kolo = null) {
                return self::db_get_top_players_data($liga_slug, $sezona_slug, $max_kolo);
            },
            'render_top_player_card_list' => static function ($player_id, $rank, $info, $highlight = false) {
                return self::render_top_player_card_list($player_id, $rank, $info, $highlight);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_team_stats($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\TeamStatsShortcode::render($atts, [
            'db_get_club_season_options' => static function ($club_id) {
                return self::db_get_club_season_options($club_id);
            },
            'db_get_club_team_stats' => static function ($club_id, $season_slug = '') {
                return self::db_get_club_team_stats($club_id, $season_slug);
            },
            'season_display_name' => static function ($sezona_slug) {
                return self::season_display_name($sezona_slug);
            },
            'db_get_latest_competition_for_club' => static function ($club_id) {
                return self::db_get_latest_competition_for_club($club_id);
            },
            'db_get_club_season_best_player_by_success' => static function ($club_id, $season_slug) {
                return self::db_get_club_season_best_player_by_success($club_id, $season_slug);
            },
            'db_get_latest_liga_for_club_and_season' => static function ($club_id, $season_slug = '') {
                return self::db_get_latest_liga_for_club_and_season($club_id, $season_slug);
            },
            'db_build_standings_for_competition' => static function ($liga_slug, $sezona_slug = '', $max_kolo = null) {
                return self::db_build_standings_for_competition($liga_slug, $sezona_slug, $max_kolo);
            },
            'find_club_rank_in_standings' => static function ($standings, $club_id) {
                return self::find_club_rank_in_standings($standings, $club_id);
            },
            'build_standings_window_around_club' => static function ($standings, $club_rank, $radius = 2) {
                return self::build_standings_window_around_club($standings, $club_rank, $radius);
            },
            'competition_display_name' => static function ($liga_slug, $sezona_slug) {
                return self::competition_display_name($liga_slug, $sezona_slug);
            },
            'get_competition_rule_data' => static function ($liga_slug, $sezona_slug = '') {
                return self::get_competition_rule_data($liga_slug, $sezona_slug);
            },
            'format_percentage_value' => static function ($value) {
                return self::format_percentage_value($value);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'player_fallback_image_url' => static function () {
                return self::player_fallback_image_url();
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
        ]);
    }

    public static function shortcode_player_transfers($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\PlayerTransfersShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'db_get_player_season_club_history' => static function ($player_id) {
                return self::db_get_player_season_club_history($player_id);
            },
            'build_player_stints' => static function ($history) {
                return self::build_player_stints($history);
            },
            'season_display_name' => static function ($sezona_slug) {
                return self::season_display_name($sezona_slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
        ]);
    }

    public static function shortcode_club_info($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ClubInfoShortcode::render($atts, [
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'db_get_latest_competition_for_club' => static function ($club_id) {
                return self::db_get_latest_competition_for_club($club_id);
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'get_competition_rule_data' => static function ($liga_slug, $sezona_slug = '') {
                return self::get_competition_rule_data($liga_slug, $sezona_slug);
            },
            'competition_federation_data' => static function ($code) {
                return self::competition_federation_data($code);
            },
            'info_link_icon_html' => static function ($icon_file_name, $fallback, $modifier = 'before') {
                return self::info_link_icon_html($icon_file_name, $fallback, $modifier);
            },
            'normalize_phone_for_href' => static function ($raw_phone) {
                return self::normalize_phone_for_href($raw_phone);
            },
            'format_phone_for_display' => static function ($raw_phone) {
                return self::format_phone_for_display($raw_phone);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    private static function info_link_icon_html($icon_file_name, $fallback, $modifier = 'before')
    {
        $icon_file_name = sanitize_file_name((string) $icon_file_name);
        if ($icon_file_name !== '' && substr($icon_file_name, -4) !== '.svg') {
            $icon_file_name .= '.svg';
        }
        $modifier = sanitize_html_class((string) $modifier);
        $classes = 'opentt-info-link-icon opentt-info-link-icon--' . ($modifier !== '' ? $modifier : 'before');
        $fallback = (string) $fallback;

        $rel_path = 'assets/icons/' . $icon_file_name;
        $full_path = self::$plugin_dir . $rel_path;
        if (is_readable($full_path)) {
            $svg = file_get_contents($full_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            if (is_string($svg) && trim($svg) !== '') {
                $svg = preg_replace('/<\?xml.*?\?>/i', '', $svg);
                $svg = preg_replace('/<!DOCTYPE.*?>/i', '', $svg);
                if (is_string($svg) && trim($svg) !== '') {
                    return '<span class="' . esc_attr($classes) . '" aria-hidden="true"><span class="opentt-info-link-icon-svg">' . $svg . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }
        }

        return '<span class="' . esc_attr($classes) . '" aria-hidden="true">' . esc_html($fallback) . '</span>';
    }

    public static function shortcode_player_info($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\PlayerInfoShortcode::render($atts, [
            'player_fallback_image_url' => static function () {
                return self::player_fallback_image_url();
            },
            'get_player_club_id' => static function ($player_id) {
                return self::get_player_club_id($player_id);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'country_label_by_code' => static function ($country_code) {
                return OpenTT_Unified_Core::country_label_by_code($country_code);
            },
            'country_flag_emoji' => static function ($country_code) {
                return OpenTT_Unified_Core::country_flag_emoji($country_code);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    private static function normalize_phone_for_href($raw_phone)
    {
        return OpenTT_Unified_Readonly_Helpers::normalize_phone_for_href($raw_phone);
    }

    private static function format_phone_for_display($raw_phone)
    {
        return OpenTT_Unified_Readonly_Helpers::format_phone_for_display($raw_phone);
    }

    public static function shortcode_show_match_teams($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ShowMatchTeamsShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'competition_display_name' => static function ($liga_slug, $sezona_slug) {
                return self::competition_display_name($liga_slug, $sezona_slug);
            },
            'competition_archive_url' => static function ($liga_slug, $sezona_slug) {
                return self::competition_archive_url($liga_slug, $sezona_slug);
            },
            'kolo_name_from_slug' => static function ($slug) {
                return self::kolo_name_from_slug($slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'display_match_date' => static function ($match_date) {
                return self::display_match_date($match_date);
            },
            'match_venue_label' => static function ($row) {
                return self::match_venue_label($row);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_competition_info($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\CompetitionInfoShortcode::render($atts, [
            'current_archive_context' => static function () {
                return self::current_archive_context();
            },
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'db_table' => static function ($table_alias) {
                return OpenTT_Unified_Core::db_table($table_alias);
            },
            'table_exists' => static function ($table_name) {
                return self::table_exists($table_name);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'season_display_name' => static function ($sezona_slug) {
                return self::season_display_name($sezona_slug);
            },
            'get_competition_rule_data' => static function ($liga_slug, $sezona_slug = '') {
                return self::get_competition_rule_data($liga_slug, $sezona_slug);
            },
            'competition_federation_data' => static function ($code) {
                return self::competition_federation_data($code);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_competitions_grid($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\CompetitionsGridShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'season_sort_key' => static function ($season_slug) {
                return self::season_sort_key($season_slug);
            },
            'season_display_name' => static function ($sezona_slug) {
                return self::season_display_name($sezona_slug);
            },
            'competition_archive_url' => static function ($liga_slug, $sezona_slug) {
                return self::competition_archive_url($liga_slug, $sezona_slug);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'db_get_competition_club_ids' => static function ($liga_slug, $sezona_slug = '') {
                return self::db_get_competition_club_ids($liga_slug, $sezona_slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
        ]);
    }

    private static function competition_display_name($liga_slug, $sezona_slug)
    {
        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);

        if ($liga_slug === '' && $sezona_slug === '') {
            return '';
        }

        $liga_name = $liga_slug !== '' ? self::slug_to_title($liga_slug) : '';
        if ($liga_name === '' && $liga_slug !== '') {
            $liga_name = (string) $liga_slug;
        }

        if ($sezona_slug === '') {
            return $liga_name;
        }

        return trim($liga_name . ', Sezona ' . self::season_display_name($sezona_slug));
    }

    private static function season_display_name($sezona_slug)
    {
        $sezona_slug = sanitize_title((string) $sezona_slug);
        if ($sezona_slug === '') {
            return '';
        }

        if (preg_match('/^(\d{4})-(\d{2,4})$/', $sezona_slug, $m)) {
            $second = (string) $m[2];
            if (strlen($second) === 4) {
                $second = substr($second, 2);
            }
            return $m[1] . '/' . $second;
        }

        return self::slug_to_title($sezona_slug);
    }

    private static function competition_archive_url($liga_slug, $sezona_slug)
    {
        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);

        if ($liga_slug === '') {
            return '';
        }

        $term_candidates = [];
        if ($sezona_slug !== '') {
            $term_candidates[] = $liga_slug . '-' . $sezona_slug;
        }
        $term_candidates[] = $liga_slug;

        foreach ($term_candidates as $term_slug) {
            $term = get_term_by('slug', $term_slug, 'liga_sezona');
            if ($term && !is_wp_error($term)) {
                $term_link = get_term_link($term);
                if (!is_wp_error($term_link)) {
                    return (string) $term_link;
                }
            }
        }

        // Plain permalink fallback (fresh install default): koristi query args.
        if ((string) get_option('permalink_structure', '') === '') {
            $base = home_url('/');
            $args = ['liga' => $liga_slug];
            if ($sezona_slug !== '') {
                $args['sezona'] = $sezona_slug;
            }
            return add_query_arg($args, $base);
        }

        if ($sezona_slug !== '') {
            return home_url('/liga/' . rawurlencode($liga_slug) . '/' . rawurlencode($sezona_slug) . '/');
        }

        return home_url('/liga/' . rawurlencode($liga_slug) . '/');
    }

    private static function match_venue_label($row)
    {
        if (is_object($row)) {
            $direct_keys = ['location', 'lokacija', 'lokacija_utakmice'];
            foreach ($direct_keys as $key) {
                if (!isset($row->{$key})) {
                    continue;
                }
                $value = trim((string) $row->{$key});
                if ($value !== '') {
                    return $value;
                }
            }
        }

        $legacy_id = isset($row->legacy_post_id) ? intval($row->legacy_post_id) : 0;
        if ($legacy_id > 0) {
            $keys = [
                'mesto_odigravanja',
                'mesto_utakmice',
                'lokacija_utakmice',
                'lokacija',
                'hala',
                'sala',
                'teren',
                'mesto',
            ];
            foreach ($keys as $key) {
                $value = trim((string) get_post_meta($legacy_id, $key, true));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    public static function shortcode_top_players_list($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\TopPlayersListShortcode::render($atts, [
            'current_archive_context' => static function () {
                return self::current_archive_context();
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'db_table' => static function ($table_alias) {
                return OpenTT_Unified_Core::db_table($table_alias);
            },
            'table_exists' => static function ($table_name) {
                return self::table_exists($table_name);
            },
            'extract_round_no' => static function ($slug) {
                return self::extract_round_no($slug);
            },
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'db_get_latest_competition_for_player' => static function ($player_id) {
                return self::db_get_latest_competition_for_player($player_id);
            },
            'db_get_latest_competition_with_games' => static function () {
                return self::db_get_latest_competition_with_games();
            },
            'db_get_top_players_data' => static function ($liga_slug, $sezona_slug = '', $max_kolo = null) {
                return self::db_get_top_players_data($liga_slug, $sezona_slug, $max_kolo);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'render_top_player_card_list' => static function ($player_id, $rank, $info, $highlight = false) {
                return self::render_top_player_card_list($player_id, $rank, $info, $highlight);
            },
        ]);
    }

    private static function db_get_top_players_data($liga_slug, $sezona_slug = '', $max_kolo = null)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_top_players_data($liga_slug, $sezona_slug, $max_kolo);
    }

    private static function db_get_played_matches_count_by_club($liga_slug, $sezona_slug = '', $max_kolo = null)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_played_matches_count_by_club($liga_slug, $sezona_slug, $max_kolo);
    }

    private static function db_get_latest_competition_with_games()
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_competition_with_games();
    }

    private static function db_get_latest_competition_for_player($player_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_competition_for_player($player_id);
    }

    private static function db_get_latest_competition_for_club($club_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_competition_for_club($club_id);
    }

    private static function db_get_recent_club_matches($club_id, $limit = 5)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_recent_club_matches($club_id, $limit);
    }

    private static function db_get_player_season_club_history($player_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_player_season_club_history($player_id);
    }

    private static function season_sort_key($season_slug)
    {
        return OpenTT_Unified_Readonly_Helpers::season_sort_key($season_slug);
    }

    private static function build_player_stints($history)
    {
        if (empty($history) || !is_array($history)) {
            return [];
        }
        $stints = [];
        foreach ($history as $row) {
            $season = sanitize_title((string) ($row['season_slug'] ?? ''));
            $club_id = intval($row['club_id'] ?? 0);
            if ($season === '' || $club_id <= 0) {
                continue;
            }
            if (empty($stints)) {
                $stints[] = [
                    'club_id' => $club_id,
                    'from_season' => $season,
                    'to_season' => $season,
                ];
                continue;
            }
            $idx = count($stints) - 1;
            if (intval($stints[$idx]['club_id']) === $club_id) {
                $stints[$idx]['to_season'] = $season;
            } else {
                $stints[] = [
                    'club_id' => $club_id,
                    'from_season' => $season,
                    'to_season' => $season,
                ];
            }
        }
        return $stints;
    }

    private static function db_get_player_stats($player_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_player_stats($player_id, $season_slug);
    }

    private static function db_get_player_season_options($player_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_player_season_options($player_id);
    }

    private static function db_get_club_season_options($club_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_club_season_options($club_id);
    }

    private static function db_get_latest_liga_for_player_and_season($player_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_liga_for_player_and_season($player_id, $season_slug);
    }

    private static function db_get_latest_liga_for_club_and_season($club_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_liga_for_club_and_season($club_id, $season_slug);
    }

    private static function db_get_club_team_stats($club_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_club_team_stats($club_id, $season_slug);
    }

    private static function db_get_club_season_best_player_by_success($club_id, $season_slug)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_club_season_best_player_by_success($club_id, $season_slug);
    }

    private static function db_get_competition_club_ids($liga_slug, $sezona_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_competition_club_ids($liga_slug, $sezona_slug);
    }

    private static function db_build_standings_for_competition($liga_slug, $sezona_slug = '', $max_kolo = null)
    {
        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);
        if ($liga_slug === '') {
            return [];
        }

        $rows = self::db_get_matches([
            'limit' => -1,
            'liga_slug' => $liga_slug,
            'sezona_slug' => $sezona_slug,
            'kolo_slug' => '',
            'played' => '',
            'club_id' => 0,
            'player_id' => 0,
        ]);
        if (empty($rows)) {
            return [];
        }

        $sistem = 'novi';
        $rule = self::get_competition_rule_data($liga_slug, $sezona_slug);
        if (is_array($rule) && !empty($rule['bodovanje_tip'])) {
            $sistem = ((string) $rule['bodovanje_tip'] === '3-0_4-3_2-1') ? 'novi' : 'stari';
        }

        $stat = [];
        foreach ($rows as $r) {
            $home = intval($r->home_club_post_id);
            $away = intval($r->away_club_post_id);
            foreach ([$home, $away] as $club_id) {
                if ($club_id <= 0) {
                    continue;
                }
                if (!isset($stat[$club_id])) {
                    $stat[$club_id] = [
                        'odigrane' => 0,
                        'pobede' => 0,
                        'porazi' => 0,
                        'bodovi' => 0,
                        'meckol' => 0,
                    ];
                }
            }
        }

        foreach ($rows as $r) {
            if (intval($r->played) !== 1) {
                continue;
            }
            $round = self::extract_round_no((string) $r->kolo_slug);
            if ($max_kolo !== null && $round > intval($max_kolo)) {
                continue;
            }

            $home = intval($r->home_club_post_id);
            $away = intval($r->away_club_post_id);
            if ($home <= 0 || $away <= 0) {
                continue;
            }
            $rd = intval($r->home_score);
            $rg = intval($r->away_score);

            $stat[$home]['odigrane']++;
            $stat[$away]['odigrane']++;
            $stat[$home]['meckol'] += ($rd - $rg);
            $stat[$away]['meckol'] += ($rg - $rd);

            $home_win = ($rd > $rg);
            $away_win = ($rg > $rd);
            if ($home_win) {
                $stat[$home]['pobede']++;
                $stat[$away]['porazi']++;
            } elseif ($away_win) {
                $stat[$away]['pobede']++;
                $stat[$home]['porazi']++;
            }

            if ($sistem === 'novi') {
                if ($home_win) {
                    if ($rd === 4 && in_array($rg, [0, 1, 2], true)) {
                        $stat[$home]['bodovi'] += 3;
                    } elseif ($rd === 4 && $rg === 3) {
                        $stat[$home]['bodovi'] += 2;
                        $stat[$away]['bodovi'] += 1;
                    }
                } elseif ($away_win) {
                    if ($rg === 4 && in_array($rd, [0, 1, 2], true)) {
                        $stat[$away]['bodovi'] += 3;
                    } elseif ($rg === 4 && $rd === 3) {
                        $stat[$away]['bodovi'] += 2;
                        $stat[$home]['bodovi'] += 1;
                    }
                }
            } else {
                if ($home_win) {
                    $stat[$home]['bodovi'] += 2;
                    $stat[$away]['bodovi'] += 1;
                } elseif ($away_win) {
                    $stat[$away]['bodovi'] += 2;
                    $stat[$home]['bodovi'] += 1;
                }
            }
        }

        uasort($stat, function ($a, $b) {
            if ($a['bodovi'] === $b['bodovi']) {
                if ($a['meckol'] === $b['meckol']) {
                    return 0;
                }
                return ($a['meckol'] > $b['meckol']) ? -1 : 1;
            }
            return ($a['bodovi'] > $b['bodovi']) ? -1 : 1;
        });

        $out = [];
        $rank = 0;
        foreach ($stat as $club_id => $row) {
            $rank++;
            $out[] = [
                'rank' => $rank,
                'club_id' => intval($club_id),
                'odigrane' => intval($row['odigrane']),
                'pobede' => intval($row['pobede']),
                'porazi' => intval($row['porazi']),
                'bodovi' => intval($row['bodovi']),
                'meckol' => intval($row['meckol']),
            ];
        }

        return $out;
    }

    private static function find_club_rank_in_standings($standings, $club_id)
    {
        $club_id = intval($club_id);
        if ($club_id <= 0 || empty($standings) || !is_array($standings)) {
            return 0;
        }
        foreach ($standings as $row) {
            if (intval($row['club_id'] ?? 0) === $club_id) {
                return intval($row['rank'] ?? 0);
            }
        }
        return 0;
    }

    private static function build_standings_window_around_club($standings, $club_rank, $radius = 2)
    {
        if (empty($standings) || !is_array($standings) || $club_rank <= 0) {
            return [];
        }
        $radius = max(0, intval($radius));
        $from = max(1, intval($club_rank) - $radius);
        $to = intval($club_rank) + $radius;
        $slice = [];
        foreach ($standings as $row) {
            $rank = intval($row['rank'] ?? 0);
            if ($rank >= $from && $rank <= $to) {
                $slice[] = $row;
            }
        }
        return $slice;
    }

    private static function format_percentage_value($value)
    {
        $value = max(0.0, floatval($value));
        if (abs($value - round($value)) < 0.05) {
            return (string) intval(round($value));
        }
        return (string) round($value, 1);
    }

    private static function db_get_player_mvp_count($player_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_player_mvp_count($player_id, $season_slug);
    }

    private static function db_get_match_mvp_player_id($match_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_match_mvp_player_id($match_id);
    }

    private static function render_top_player_card_list($igrac_id, $rank, $info, $highlight = false)
    {
        $igrac_id = intval($igrac_id);
        if ($igrac_id <= 0) {
            return '';
        }

        $full_name = (string) get_the_title($igrac_id);
        $parts = explode(' ', $full_name, 2);
        $ime = isset($parts[0]) ? $parts[0] : '';
        $prezime = isset($parts[1]) ? $parts[1] : '';

        $slika = get_the_post_thumbnail($igrac_id, 'thumbnail', ['class' => 'igrac-slika']);
        if (empty($slika)) {
            $slika = '<img src="' . esc_url(self::player_fallback_image_url()) . '" alt="Igrač" class="igrac-slika" />';
        }

        $klub_id = intval($info['klub'] ?? 0);
        $grb = $klub_id ? self::club_logo_html($klub_id, 'thumbnail', ['class' => 'igrac-klub-grb']) : '';
        $naziv_kluba = $klub_id ? (string) get_the_title($klub_id) : '';
        $wins = intval($info['pobede'] ?? 0);
        $losses = intval($info['porazi'] ?? 0);
        $total = $wins + $losses;
        $score = $wins . '-' . $losses;
        $percent = $total > 0 ? (string) round(($wins / $total) * 100) . '%' : '-';
        $highlight_class = $highlight ? ' highlight' : '';
        $igrac_link = get_permalink($igrac_id);

        ob_start();
        ?>
        <div class="igrac-card-list<?php echo esc_attr($highlight_class); ?>">
            <div class="igrac-rank"><?php echo intval($rank); ?></div>
            <a class="igrac-link" href="<?php echo esc_url($igrac_link); ?>">
                <div class="igrac-slika-wrap"><?php echo $slika; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            </a>
            <div class="igrac-imeprezime">
                <a class="igrac-link" href="<?php echo esc_url($igrac_link); ?>">
                    <div class="ime"><?php echo esc_html($ime); ?></div>
                    <div class="prezime"><?php echo esc_html($prezime); ?></div>
                </a>
                <div class="igrac-klub">
                    <?php if ($grb): ?>
                        <span class="igrac-klub-grb"><?php echo $grb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <?php endif; ?>
                    <span class="igrac-klub-naziv"><?php echo esc_html($naziv_kluba); ?></span>
                </div>
            </div>
            <div class="igrac-skor"><?php echo esc_html($score); ?></div>
            <div class="igrac-procenat"><?php echo esc_html($percent); ?></div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function get_validation_report()
    {
        $report = get_option(self::OPTION_VALIDATION_REPORT, []);
        return is_array($report) ? $report : [];
    }

    private static function build_match_query_args($atts)
    {
        $limit = isset($atts['limit']) ? intval($atts['limit']) : 5;
        $liga = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona_from_atts = !empty($atts['sezona']) ? sanitize_title((string) $atts['sezona']) : '';
        $sezona_from_context = '';
        $kolo = '';
        $odigrana = '';
        $club_id = 0;
        $player_id = 0;
        $archive_ctx = self::current_archive_context();

        if ($liga === '') {
            if (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona') {
                $liga = sanitize_title((string) ($archive_ctx['liga_slug'] ?? ''));
            } elseif (is_tax('liga_sezona')) {
                $term = get_queried_object();
                if ($term && !is_wp_error($term) && !empty($term->slug)) {
                    $liga = sanitize_title((string) $term->slug);
                }
            } else {
                $liga_qv = get_query_var('liga_sezona');
                if ($liga_qv) {
                    $liga = sanitize_title((string) $liga_qv);
                }
            }
        }

        if ($sezona_from_atts === '') {
            if (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona') {
                $sezona_from_context = sanitize_title((string) ($archive_ctx['sezona_slug'] ?? ''));
            } elseif (is_tax('liga_sezona')) {
                $term = get_queried_object();
                if ($term && !is_wp_error($term) && !empty($term->slug)) {
                    $parsed_tax = self::parse_legacy_liga_sezona((string) $term->slug, '');
                    $sezona_from_context = sanitize_title((string) ($parsed_tax['season_slug'] ?? ''));
                }
            } else {
                $sezona_qv = get_query_var('sezona');
                if ($sezona_qv) {
                    $sezona_from_context = sanitize_title((string) $sezona_qv);
                }
            }
        }

        if (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'kolo') {
            $kolo = sanitize_title((string) ($archive_ctx['kolo_slug'] ?? ''));
        } elseif (is_tax('kolo')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $kolo = sanitize_title((string) $term->slug);
            }
        } else {
            $kolo_qv = get_query_var('kolo');
            if ($kolo_qv) {
                $kolo = sanitize_title((string) $kolo_qv);
            }
        }

        if (isset($atts['odigrana']) && $atts['odigrana'] !== '') {
            $val = strtolower(trim((string) $atts['odigrana']));
            if ($val === 'da') {
                $val = '1';
            }
            if ($val === 'ne') {
                $val = '0';
            }
            if ($val === '0' || $val === '1') {
                $odigrana = $val;
            }
        }

        if (!empty($atts['klub'])) {
            $club_slug_or_name = (string) $atts['klub'];
            $club = get_page_by_path(sanitize_title($club_slug_or_name), OBJECT, 'klub');
            if (!$club) {
                $club = get_page_by_title($club_slug_or_name, OBJECT, 'klub');
            }
            if ($club && !is_wp_error($club)) {
                $club_id = intval($club->ID);
            }
        } elseif (is_singular('klub')) {
            $club_id = intval(get_the_ID());
        }

        if (empty($atts['klub']) && is_singular('igrac')) {
            $player_id = intval(get_the_ID());
        }

        // Back-compat: dozvoli i legacy spojeni slug tipa "kvalitetna-liga-2025-26".
        // Ako je sezona već eksplicitno prosleđena, ona ima prioritet.
        $resolved_sezona = $sezona_from_atts !== '' ? $sezona_from_atts : $sezona_from_context;
        $parsed = self::parse_legacy_liga_sezona($liga, $resolved_sezona);
        $liga = sanitize_title((string) ($parsed['league_slug'] ?? $liga));
        $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? $resolved_sezona));
        if ($sezona_from_atts !== '') {
            $sezona_slug = $sezona_from_atts;
        }

        return [
            'limit' => $limit,
            'liga_slug' => $liga,
            'sezona_slug' => $sezona_slug,
            'kolo_slug' => $kolo,
            'played' => $odigrana,
            'club_id' => $club_id,
            'player_id' => $player_id,
        ];
    }

    private static function db_get_matches($args)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_matches($args);
    }

    private static function db_get_match_by_legacy_id($legacy_id)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_match_by_legacy_id($legacy_id);
    }

    private static function db_get_match_by_keys($liga_slug, $sezona_slug, $kolo_slug, $slug)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_match_by_keys($liga_slug, $sezona_slug, $kolo_slug, $slug);
    }

    private static function db_get_h2h_matches($current_match_db_id, $home_club_id, $away_club_id)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_h2h_matches($current_match_db_id, $home_club_id, $away_club_id);
    }

    private static function db_get_games_for_match_id($match_id)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_games_for_match_id($match_id);
    }

    private static function db_get_sets_for_game_id($game_id)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_sets_for_game_id($game_id);
    }

    private static function db_get_latest_liga_for_club($club_id)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_latest_liga_for_club($club_id);
    }

    private static function render_matches_grid_html($rows, $columns, $with_kolo_attr)
    {
        if (empty($rows)) {
            return '<p>Nema utakmica za prikaz.</p>';
        }

        ob_start();
        echo '<div class="opentt-grid-wrapper"><div class="opentt-grid cols-' . intval($columns) . '">';
        $last_kolo_slug = null;
        foreach ($rows as $row) {
            $home_id = intval($row->home_club_post_id);
            $away_id = intval($row->away_club_post_id);
            $rd = intval($row->home_score);
            $rg = intval($row->away_score);
            $is_played = intval($row->played) === 1 || $rd > 0 || $rg > 0;
            $is_live = self::is_match_live($row);
            $is_upcoming_no_score = !$is_played && $rd === 0 && $rg === 0;
            $home_win = ($rd === 4);
            $away_win = ($rg === 4);
            $kolo_slug = sanitize_title((string) $row->kolo_slug);
            $kolo_no = self::extract_round_no($kolo_slug);
            $kolo_name = self::kolo_heading_label($kolo_slug, $kolo_no);
            $date = self::display_match_date($row->match_date);
            $time = self::display_match_time($row->match_date);
            $time_label = $time !== '' ? $time : '--:--';
            $link = self::match_permalink($row);

            if ($kolo_slug !== '' && $kolo_slug !== $last_kolo_slug) {
                echo '<div class="opentt-grid-round-heading" data-kolo-slug="' . esc_attr($kolo_slug) . '"><span>' . esc_html($kolo_name) . '</span></div>';
                $last_kolo_slug = $kolo_slug;
            }

            $attr = '';
            if ($with_kolo_attr) {
                $match_ts = self::parse_match_timestamp((string) $row->match_date);
                if ($match_ts === false) {
                    $match_ts = 0;
                }
                $match_date_iso = substr((string) $row->match_date, 0, 10);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $match_date_iso)) {
                    $match_date_iso = '';
                }
                $attr = ' data-kolo-slug="' . esc_attr($kolo_slug) . '"';
                $attr .= ' data-kolo-name="' . esc_attr($kolo_name) . '"';
                $attr .= ' data-kolo-no="' . esc_attr((string) $kolo_no) . '"';
                $attr .= ' data-match-ts="' . esc_attr((string) intval($match_ts)) . '"';
                $attr .= ' data-match-date="' . esc_attr($match_date_iso) . '"';
                $attr .= ' data-played="' . esc_attr((string) intval($row->played)) . '"';
                $attr .= ' data-home-club-id="' . esc_attr((string) $home_id) . '"';
                $attr .= ' data-away-club-id="' . esc_attr((string) $away_id) . '"';
            }

            echo '<div class="opentt-item"' . $attr . '>';
            echo '<a href="' . esc_url($link) . '">';
            echo '<div class="opentt-item-main">';
            echo '<div class="opentt-item-teams">';
            echo self::render_team_html($home_id, $rd, $home_win, !$is_upcoming_no_score);
            echo self::render_team_html($away_id, $rg, $away_win, !$is_upcoming_no_score);
            echo '</div>';
            echo '<div class="opentt-item-side" aria-label="Vreme utakmice">';
            if ($is_live) {
                echo '<span class="opentt-item-side-top">' . esc_html($date) . '</span>';
                echo '<span class="opentt-item-side-bottom"><span class="opentt-live-badge">LIVE</span></span>';
            } elseif ($is_played) {
                echo '<span class="opentt-item-side-top">' . esc_html($date) . '</span>';
                echo '<span class="opentt-item-side-bottom">Kraj</span>';
            } else {
                echo '<span class="opentt-item-side-top">' . esc_html($date) . '</span>';
                echo '<span class="opentt-item-side-bottom">' . esc_html($time_label) . '</span>';
            }
            echo '</div>';
            echo '</div>';
            echo '</a></div>';
        }
        echo '</div></div>';
        return ob_get_clean();
    }

    private static function render_clubs_grid_html($rows, $columns, $with_attrs)
    {
        if (empty($rows)) {
            return '<p>Nema klubova za prikaz.</p>';
        }

        ob_start();
        echo '<div class="opentt-klubovi">';
        echo '<div class="opentt-klubovi-grid cols-' . intval($columns) . '">';
        foreach ($rows as $row) {
            $club_id = intval($row['id'] ?? 0);
            $url = (string) ($row['url'] ?? '');
            $display_name = (string) ($row['display_name'] ?? '');
            $league_label = (string) ($row['league_label'] ?? 'Bez takmičenja');
            $grad_label = trim((string) ($row['grad_label'] ?? ''));
            $logo_html = (string) ($row['logo_html'] ?? '');
            $sort_name = (string) ($row['sort_name'] ?? '');
            $league_slug = sanitize_title((string) ($row['league_slug'] ?? ''));
            $opstina_slug = sanitize_title((string) ($row['opstina_slug'] ?? ''));

            $attrs = ' data-club-id="' . esc_attr((string) $club_id) . '"';
            if ($with_attrs) {
                $attrs .= ' data-league-slug="' . esc_attr($league_slug) . '"';
                $attrs .= ' data-opstina-slug="' . esc_attr($opstina_slug) . '"';
                $attrs .= ' data-sort-name="' . esc_attr($sort_name) . '"';
            }

            echo '<article class="opentt-klubovi-item"' . $attrs . '>';
            echo '<a class="opentt-klubovi-link" href="' . esc_url($url) . '">';
            echo '<span class="opentt-klubovi-logo-wrap">' . $logo_html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="opentt-klubovi-content">';
            echo '<strong class="opentt-klubovi-name">' . esc_html($display_name) . '</strong>';
            if ($grad_label !== '') {
                echo '<span class="opentt-klubovi-city">' . esc_html($grad_label) . '</span>';
            }
            echo '<span class="opentt-klubovi-league">' . esc_html($league_label) . '</span>';
            echo '</span>';
            echo '</a>';
            echo '</article>';
        }
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    private static function club_fallback_image_url()
    {
        $plugin_dir = is_string(self::$plugin_dir) ? trim(self::$plugin_dir) : '';
        if ($plugin_dir === '') {
            return '';
        }

        $relative_candidates = [
            'assets/img/fallback-club.png',
            'assets/image/fallback-club.png',
        ];

        foreach ($relative_candidates as $relative_path) {
            $absolute_path = trailingslashit($plugin_dir) . $relative_path;
            if (is_readable($absolute_path)) {
                return plugins_url($relative_path, self::$plugin_file);
            }
        }

        return '';
    }

    private static function player_fallback_image_url()
    {
        $plugin_dir = is_string(self::$plugin_dir) ? trim(self::$plugin_dir) : '';
        if ($plugin_dir === '') {
            return '';
        }

        $relative_candidates = [
            'assets/img/fallback-player.png',
            'assets/image/fallback-player.png',
            'assets/img/fallback-club.png',
            'assets/image/fallback-club.png',
        ];

        foreach ($relative_candidates as $relative_path) {
            $absolute_path = trailingslashit($plugin_dir) . $relative_path;
            if (is_readable($absolute_path)) {
                return plugins_url($relative_path, self::$plugin_file);
            }
        }

        return '';
    }

    private static function club_logo_url($club_id, $size = 'thumbnail')
    {
        $club_id = intval($club_id);
        if ($club_id <= 0) {
            return self::club_fallback_image_url();
        }

        $url = get_the_post_thumbnail_url($club_id, $size);
        if (is_string($url) && trim($url) !== '') {
            return $url;
        }

        return self::club_fallback_image_url();
    }

    private static function club_logo_html($club_id, $size = 'thumbnail', $attr = [])
    {
        $club_id = intval($club_id);
        $attr = is_array($attr) ? $attr : [];

        if ($club_id > 0) {
            $html = get_the_post_thumbnail($club_id, $size, $attr);
            if (is_string($html) && trim($html) !== '') {
                return $html;
            }
        }

        $fallback_url = self::club_fallback_image_url();
        if ($fallback_url === '') {
            return '';
        }

        $class = isset($attr['class']) ? trim((string) $attr['class']) : '';
        if ($class === '') {
            $class = 'opentt-club-fallback-image';
        }
        $alt = isset($attr['alt']) ? (string) $attr['alt'] : (string) get_the_title($club_id);

        $img_attr = [
            'src' => $fallback_url,
            'alt' => $alt,
            'class' => $class,
        ];

        foreach (['style', 'loading', 'title', 'width', 'height', 'decoding'] as $key) {
            if (isset($attr[$key]) && $attr[$key] !== '') {
                $img_attr[$key] = (string) $attr[$key];
            }
        }

        $parts = [];
        foreach ($img_attr as $key => $value) {
            $parts[] = $key . '="' . esc_attr($value) . '"';
        }

        return '<img ' . implode(' ', $parts) . ' />';
    }

    private static function render_team_html($club_id, $score, $is_winner, $show_score = true)
    {
        $class = $is_winner ? 'pobednik' : 'gubitnik';
        $name = $club_id ? get_the_title($club_id) : '';
        $crest = $club_id ? self::club_logo_html($club_id, 'thumbnail') : '';

        ob_start();
        echo '<div class="team ' . esc_attr($class) . '">';
        if (!empty($crest)) {
            echo $crest; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '<span>' . esc_html($name) . '</span>';
        if ($show_score) {
            echo '<strong>' . esc_html((string) intval($score)) . '</strong>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    private static function display_match_time($match_date)
    {
        $match_date = (string) $match_date;
        if ($match_date === '' || $match_date === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = self::parse_match_timestamp($match_date);
        if ($ts === false) {
            return '';
        }
        return date_i18n('H:i', $ts);
    }

    private static function kolo_heading_label($kolo_slug, $kolo_no = null)
    {
        $kolo_slug = sanitize_title((string) $kolo_slug);
        $kolo_no = ($kolo_no === null) ? self::extract_round_no($kolo_slug) : intval($kolo_no);
        if ($kolo_no > 0) {
            return $kolo_no . '. kolo';
        }
        $kolo_name = self::kolo_name_from_slug($kolo_slug);
        if ($kolo_name !== '') {
            return $kolo_name;
        }
        return self::slug_to_title($kolo_slug);
    }

    private static function is_match_live($row)
    {
        if (!is_object($row)) {
            return false;
        }
        $home_score = intval($row->home_score ?? 0);
        $away_score = intval($row->away_score ?? 0);
        if ($home_score >= 4 || $away_score >= 4) {
            return false;
        }
        $match_date = trim((string) ($row->match_date ?? ''));
        if ($match_date === '' || $match_date === '0000-00-00 00:00:00') {
            return false;
        }
        $match_ts = self::parse_match_timestamp($match_date);
        if ($match_ts === false) {
            return false;
        }
        return intval($match_ts) <= intval(current_time('timestamp'));
    }

    private static function parse_match_timestamp($match_date, $end_of_day_if_midnight = false)
    {
        $match_date = trim((string) $match_date);
        if ($match_date === '' || $match_date === '0000-00-00 00:00:00') {
            return false;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $match_date)) {
            $match_date .= ' 00:00:00';
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $match_date, wp_timezone());
        if ($dt instanceof \DateTimeImmutable) {
            if ($end_of_day_if_midnight && preg_match('/\s00:00:00$/', $match_date)) {
                $dt = $dt->setTime(23, 59, 59);
            }
            return $dt->getTimestamp();
        }

        $ts = strtotime($match_date);
        if ($ts === false) {
            return false;
        }
        if ($end_of_day_if_midnight && preg_match('/\s00:00:00$/', $match_date)) {
            $fallback = strtotime(substr($match_date, 0, 10) . ' 23:59:59');
            return ($fallback === false) ? intval($ts) : intval($fallback);
        }
        return intval($ts);
    }

    private static function match_permalink($row)
    {
        $legacy_id = isset($row->legacy_post_id) ? intval($row->legacy_post_id) : 0;
        if (
            self::is_legacy_match_cpt_enabled()
            && $legacy_id > 0
            && get_post_type($legacy_id) === 'utakmica'
        ) {
            return get_permalink($legacy_id);
        }

        $liga = isset($row->liga_slug) ? sanitize_title((string) $row->liga_slug) : '';
        $sezona = isset($row->sezona_slug) ? sanitize_title((string) $row->sezona_slug) : '';
        $kolo = isset($row->kolo_slug) ? sanitize_title((string) $row->kolo_slug) : '';
        $slug = isset($row->slug) ? sanitize_title((string) $row->slug) : '';

        if ($liga === '' || $kolo === '' || $slug === '') {
            return home_url('/');
        }

        $path = '/' . $liga . '/';
        if ($sezona !== '') {
            $path .= $sezona . '/';
        }
        $path .= $kolo . '/' . $slug . '/';

        return home_url($path);
    }

    private static function display_match_date($match_date)
    {
        return OpenTT_Unified_Readonly_Helpers::display_match_date($match_date);
    }

    private static function display_match_date_long($match_date)
    {
        return OpenTT_Unified_Readonly_Helpers::display_match_date_long($match_date);
    }

    private static function kolo_name_from_slug($slug)
    {
        $slug = sanitize_title((string) $slug);
        if ($slug === '') {
            return '';
        }

        $term_name = '';
        $term = get_term_by('slug', $slug, 'kolo');
        if ($term && !is_wp_error($term) && !empty($term->name)) {
            $term_name = trim((string) $term->name);
        }

        $candidate = $term_name !== '' ? $term_name : $slug;
        $candidate_slug = sanitize_title($candidate);
        $round_no = self::extract_round_no($candidate_slug !== '' ? $candidate_slug : $candidate);
        if ($round_no > 0) {
            if ($candidate_slug === (string) $round_no || strpos($candidate_slug, 'kolo') !== false) {
                return $round_no . '. kolo';
            }
        }

        if ($term_name !== '') {
            return $term_name;
        }

        return OpenTT_Unified_Readonly_Helpers::slug_to_title($slug);
    }

    private static function extract_round_no($kolo_slug)
    {
        return OpenTT_Unified_Readonly_Helpers::extract_round_no($kolo_slug);
    }

    private static function render_lp2_player($player_id)
    {
        $player_id = intval($player_id);
        if ($player_id <= 0) {
            return '';
        }

        $link = get_permalink($player_id);
        $thumb = has_post_thumbnail($player_id)
            ? get_the_post_thumbnail($player_id, 'thumbnail', ['class' => 'lp2-thumb'])
            : '<img src="' . esc_url(self::player_fallback_image_url()) . '" alt="Igrač" class="lp2-thumb" />';

        $title = (string) get_the_title($player_id);
        $parts = explode(' ', $title, 2);
        $ime = isset($parts[0]) ? $parts[0] : '';
        $prezime = isset($parts[1]) ? $parts[1] : '';

        ob_start();
        echo '<div class="lp2-igrac-wrap">';
        echo '<a class="lp2-igrac" href="' . esc_url($link) . '">';
        echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="lp2-name"><span>' . esc_html($ime) . '</span><span>' . esc_html($prezime) . '</span></div>';
        echo '</a>';
        echo '</div>';
        return ob_get_clean();
    }

    private static function render_klub_card_html($klub_id)
    {
        $klub_id = intval($klub_id);
        if ($klub_id <= 0) {
            return '';
        }
        $naziv = get_the_title($klub_id);
        $grb = self::club_logo_html($klub_id, 'thumbnail', ['class' => 'opentt-grb']);
        $link = get_permalink($klub_id);

        ob_start();
        ?>
        <div class="opentt-klub">
            <a href="<?php echo esc_url($link); ?>">
                <div class="opentt-grb-wrap"><?php echo $grb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <div class="opentt-naziv"><?php echo esc_html((string) $naziv); ?></div>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function current_match_context()
    {
        if (self::$virtual_match_row) {
            return [
                'db_row' => self::$virtual_match_row,
                'legacy_id' => intval(self::$virtual_match_row->legacy_post_id),
            ];
        }

        if (is_singular('utakmica')) {
            $legacy_id = intval(get_the_ID());
            $row = self::db_get_match_by_legacy_id($legacy_id);
            if ($row) {
                return ['db_row' => $row, 'legacy_id' => $legacy_id];
            }
        }

        return null;
    }

    public static function get_template_match_context()
    {
        $ctx = self::current_match_context();
        if (!$ctx || empty($ctx['db_row'])) {
            return null;
        }
        $row = $ctx['db_row'];
        return [
            'db_id' => intval($row->id),
            'legacy_id' => intval($ctx['legacy_id']),
            'slug' => (string) $row->slug,
            'liga_slug' => (string) $row->liga_slug,
            'kolo_slug' => (string) $row->kolo_slug,
            'date' => self::display_match_date($row->match_date),
            'kolo_name' => self::kolo_name_from_slug((string) $row->kolo_slug),
            'home_club_id' => intval($row->home_club_post_id),
            'away_club_id' => intval($row->away_club_post_id),
            'home_score' => intval($row->home_score),
            'away_score' => intval($row->away_score),
            'match_url' => self::match_permalink($row),
        ];
    }

    private static function get_match_block_template()
    {
        if (!function_exists('get_block_template')) {
            return null;
        }

        $theme = get_stylesheet();
        $slug = self::MATCH_BLOCK_TEMPLATE_SLUG;

        $tpl = get_block_template($theme . '//' . $slug, 'wp_template');
        if ($tpl) {
            return $tpl;
        }

        $parent = get_template();
        if ($parent && $parent !== $theme) {
            $tpl = get_block_template($parent . '//' . $slug, 'wp_template');
            if ($tpl) {
                return $tpl;
            }
        }

        $posts = get_posts([
            'post_type' => 'wp_template',
            'name' => $slug,
            'numberposts' => 1,
            'post_status' => ['publish', 'draft'],
        ]);
        if (!empty($posts[0])) {
            return (object) [
                'content' => $posts[0]->post_content,
            ];
        }

        // Ako je post_name upisan kao "theme//slug", fallback pretraga.
        $posts = get_posts([
            'post_type' => 'wp_template',
            'posts_per_page' => 20,
            'post_status' => ['publish', 'draft'],
            's' => '//' . $slug,
        ]);
        if (!empty($posts)) {
            foreach ($posts as $p) {
                if (strpos((string) $p->post_name, '//' . $slug) !== false) {
                    return (object) [
                        'content' => $p->post_content,
                    ];
                }
            }
        }

        return null;
    }

}

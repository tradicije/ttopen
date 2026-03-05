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

final class OpenTT_Unified_Admin_Module
{
    public static function register()
    {
        add_action('admin_init', ['OpenTT_Unified_Core', 'maybe_enable_admin_ui_translation'], 1);
        add_action('admin_menu', ['OpenTT_Unified_Core', 'register_admin_menu']);
        add_action('admin_menu', ['OpenTT_Unified_Core', 'admin_menu_reorder'], 99);
        add_action('admin_enqueue_scripts', ['OpenTT_Unified_Core', 'render_admin_styles']);
        add_action('admin_head', ['OpenTT_Unified_Core', 'render_admin_menu_icon_style']);
        add_action('admin_head', ['OpenTT_Unified_Core', 'render_admin_live_search_head_script']);
        add_action('admin_notices', ['OpenTT_Unified_Core', 'render_admin_notice']);

        add_action('admin_post_opentt_unified_save_match', ['OpenTT_Unified_Core', 'handle_save_match']);
        add_action('admin_post_opentt_unified_delete_match', ['OpenTT_Unified_Core', 'handle_delete_match']);
        add_action('admin_post_opentt_unified_toggle_featured_match', ['OpenTT_Unified_Core', 'handle_toggle_featured_match_admin']);
        add_action('admin_post_opentt_unified_toggle_live_match', ['OpenTT_Unified_Core', 'handle_toggle_live_match_admin']);
        add_action('admin_post_opentt_unified_finish_live_match', ['OpenTT_Unified_Core', 'handle_finish_live_match_admin']);
        add_action('admin_post_opentt_unified_delete_matches_bulk', ['OpenTT_Unified_Core', 'handle_delete_matches_bulk_admin']);
        add_action('admin_post_opentt_unified_save_game', ['OpenTT_Unified_Core', 'handle_save_game']);
        add_action('admin_post_opentt_unified_save_games_batch', ['OpenTT_Unified_Core', 'handle_save_games_batch']);
        add_action('admin_post_opentt_unified_delete_game', ['OpenTT_Unified_Core', 'handle_delete_game']);
        add_action('admin_post_opentt_unified_save_set', ['OpenTT_Unified_Core', 'handle_save_set']);
        add_action('admin_post_opentt_unified_delete_set', ['OpenTT_Unified_Core', 'handle_delete_set']);
        add_action('admin_post_opentt_unified_save_club', ['OpenTT_Unified_Core', 'handle_save_club_admin']);
        add_action('admin_post_opentt_unified_delete_club', ['OpenTT_Unified_Core', 'handle_delete_club_admin']);
        add_action('admin_post_opentt_unified_delete_clubs_bulk', ['OpenTT_Unified_Core', 'handle_delete_clubs_bulk_admin']);
        add_action('admin_post_opentt_unified_save_player', ['OpenTT_Unified_Core', 'handle_save_player_admin']);
        add_action('admin_post_opentt_unified_delete_player', ['OpenTT_Unified_Core', 'handle_delete_player_admin']);
        add_action('admin_post_opentt_unified_delete_players_bulk', ['OpenTT_Unified_Core', 'handle_delete_players_bulk_admin']);
        add_action('admin_post_opentt_unified_save_league', ['OpenTT_Unified_Core', 'handle_save_league_admin']);
        add_action('admin_post_opentt_unified_delete_league', ['OpenTT_Unified_Core', 'handle_delete_league_admin']);
        add_action('admin_post_opentt_unified_save_season', ['OpenTT_Unified_Core', 'handle_save_season_admin']);
        add_action('admin_post_opentt_unified_delete_season', ['OpenTT_Unified_Core', 'handle_delete_season_admin']);
        add_action('admin_post_opentt_unified_save_competition_rule', ['OpenTT_Unified_Core', 'handle_save_competition_rule_admin']);
        add_action('admin_post_opentt_unified_delete_competition_rule', ['OpenTT_Unified_Core', 'handle_delete_competition_rule_admin']);
        add_action('admin_post_opentt_unified_save_settings', ['OpenTT_Unified_Core', 'handle_save_settings_admin']);
        add_action('admin_post_opentt_unified_delete_all_data', ['OpenTT_Unified_Core', 'handle_delete_all_data']);
        add_action('admin_post_opentt_unified_onboarding_action', ['OpenTT_Unified_Core', 'handle_onboarding_action']);
        add_action('admin_post_opentt_unified_export_data', ['OpenTT_Unified_Core', 'handle_export_data_admin']);
        add_action('admin_post_opentt_unified_import_validate', ['OpenTT_Unified_Core', 'handle_import_validate_admin']);
        add_action('admin_post_opentt_unified_import_commit', ['OpenTT_Unified_Core', 'handle_import_commit_admin']);
        add_action('admin_post_opentt_unified_reset_competition_matches', ['OpenTT_Unified_Core', 'handle_reset_competition_matches_admin']);
        add_action('admin_post_opentt_unified_competition_diagnostics', ['OpenTT_Unified_Core', 'handle_competition_diagnostics_admin']);
        add_action('admin_post_opentt_unified_repair_competition_played', ['OpenTT_Unified_Core', 'handle_repair_competition_played_admin']);
    }
}

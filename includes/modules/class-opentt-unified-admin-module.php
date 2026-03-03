<?php

if (!defined('ABSPATH')) {
    exit;
}

final class STKB_Unified_Admin_Module
{
    public static function register()
    {
        add_action('admin_init', ['STKB_Unified_Core', 'maybe_enable_admin_ui_translation'], 1);
        add_action('admin_menu', ['STKB_Unified_Core', 'register_admin_menu']);
        add_action('admin_menu', ['STKB_Unified_Core', 'admin_menu_reorder'], 99);
        add_action('admin_enqueue_scripts', ['STKB_Unified_Core', 'render_admin_styles']);
        add_action('admin_head', ['STKB_Unified_Core', 'render_admin_menu_icon_style']);
        add_action('admin_head', ['STKB_Unified_Core', 'render_admin_live_search_head_script']);
        add_action('admin_notices', ['STKB_Unified_Core', 'render_admin_notice']);

        add_action('admin_post_stkb_unified_save_match', ['STKB_Unified_Core', 'handle_save_match']);
        add_action('admin_post_stkb_unified_delete_match', ['STKB_Unified_Core', 'handle_delete_match']);
        add_action('admin_post_stkb_unified_delete_matches_bulk', ['STKB_Unified_Core', 'handle_delete_matches_bulk_admin']);
        add_action('admin_post_stkb_unified_save_game', ['STKB_Unified_Core', 'handle_save_game']);
        add_action('admin_post_stkb_unified_save_games_batch', ['STKB_Unified_Core', 'handle_save_games_batch']);
        add_action('admin_post_stkb_unified_delete_game', ['STKB_Unified_Core', 'handle_delete_game']);
        add_action('admin_post_stkb_unified_save_set', ['STKB_Unified_Core', 'handle_save_set']);
        add_action('admin_post_stkb_unified_delete_set', ['STKB_Unified_Core', 'handle_delete_set']);
        add_action('admin_post_stkb_unified_save_club', ['STKB_Unified_Core', 'handle_save_club_admin']);
        add_action('admin_post_stkb_unified_delete_club', ['STKB_Unified_Core', 'handle_delete_club_admin']);
        add_action('admin_post_stkb_unified_delete_clubs_bulk', ['STKB_Unified_Core', 'handle_delete_clubs_bulk_admin']);
        add_action('admin_post_stkb_unified_save_player', ['STKB_Unified_Core', 'handle_save_player_admin']);
        add_action('admin_post_stkb_unified_delete_player', ['STKB_Unified_Core', 'handle_delete_player_admin']);
        add_action('admin_post_stkb_unified_delete_players_bulk', ['STKB_Unified_Core', 'handle_delete_players_bulk_admin']);
        add_action('admin_post_stkb_unified_save_league', ['STKB_Unified_Core', 'handle_save_league_admin']);
        add_action('admin_post_stkb_unified_delete_league', ['STKB_Unified_Core', 'handle_delete_league_admin']);
        add_action('admin_post_stkb_unified_save_season', ['STKB_Unified_Core', 'handle_save_season_admin']);
        add_action('admin_post_stkb_unified_delete_season', ['STKB_Unified_Core', 'handle_delete_season_admin']);
        add_action('admin_post_stkb_unified_save_competition_rule', ['STKB_Unified_Core', 'handle_save_competition_rule_admin']);
        add_action('admin_post_stkb_unified_delete_competition_rule', ['STKB_Unified_Core', 'handle_delete_competition_rule_admin']);
        add_action('admin_post_stkb_unified_save_settings', ['STKB_Unified_Core', 'handle_save_settings_admin']);
        add_action('admin_post_stkb_unified_delete_all_data', ['STKB_Unified_Core', 'handle_delete_all_data']);
        add_action('admin_post_stkb_unified_onboarding_action', ['STKB_Unified_Core', 'handle_onboarding_action']);
        add_action('admin_post_stkb_unified_export_data', ['STKB_Unified_Core', 'handle_export_data_admin']);
        add_action('admin_post_stkb_unified_import_validate', ['STKB_Unified_Core', 'handle_import_validate_admin']);
        add_action('admin_post_stkb_unified_import_commit', ['STKB_Unified_Core', 'handle_import_commit_admin']);
        add_action('admin_post_stkb_unified_reset_competition_matches', ['STKB_Unified_Core', 'handle_reset_competition_matches_admin']);
        add_action('admin_post_stkb_unified_competition_diagnostics', ['STKB_Unified_Core', 'handle_competition_diagnostics_admin']);
        add_action('admin_post_stkb_unified_repair_competition_played', ['STKB_Unified_Core', 'handle_repair_competition_played_admin']);
    }
}

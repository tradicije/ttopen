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

require_once __DIR__ . '/modules/class-opentt-unified-assets-module.php';
require_once __DIR__ . '/modules/class-opentt-unified-admin-module.php';
require_once __DIR__ . '/modules/class-opentt-unified-routing-module.php';
require_once __DIR__ . '/modules/class-opentt-unified-shortcodes-module.php';
require_once __DIR__ . '/modules/class-opentt-unified-legacy-module.php';
require_once __DIR__ . '/modules/trait-opentt-unified-shortcodes.php';
require_once __DIR__ . '/class-opentt-unified-readonly-helpers.php';
require_once __DIR__ . '/class-opentt-unified-admin-readonly-helpers.php';
require_once __DIR__ . '/class-opentt-unified-admin-match-actions.php';
require_once __DIR__ . '/class-opentt-unified-admin-club-player-actions.php';
require_once __DIR__ . '/class-opentt-unified-shortcode-match-query-service.php';
require_once __DIR__ . '/class-opentt-unified-shortcode-stats-query-service.php';

final class OpenTT_Unified_Core
{
    use OpenTT_Unified_Shortcodes_Trait;

    const VERSION = '1.1.0';
    const CAP = 'edit_others_posts';
    const OPTION_SCHEMA_VERSION = 'opentt_unified_schema_version';
    const SCHEMA_VERSION = '6';
    const OPTION_MIGRATION_STATE = 'opentt_unified_migration_state';
    const OPTION_VALIDATION_REPORT = 'opentt_unified_validation_report';
    const OPTION_LEAGUE_SEASON_VALIDATION_REPORT = 'opentt_unified_league_season_validation_report';
    const OPTION_LEGACY_ID_MAP = 'opentt_unified_legacy_id_map';
    const OPTION_PLAYER_CITIZENSHIP_BACKFILL_DONE = 'opentt_unified_player_citizenship_backfill_done';
    const OPTION_CUSTOM_SHORTCODE_CSS = 'opentt_unified_custom_shortcode_css';
    const OPTION_CUSTOM_SHORTCODE_CSS_MAP = 'opentt_unified_custom_shortcode_css_map';
    const OPTION_VISUAL_SETTINGS = 'opentt_unified_visual_settings';
    const OPTION_DEFAULT_PAGES_SETUP_DONE = 'opentt_unified_default_pages_setup_done';
    const OPTION_ONBOARDING_STATE = 'opentt_unified_onboarding_state';
    const OPTION_REWRITE_FLUSHED = 'opentt_unified_rewrite_flushed';
    const OPTION_SHOW_SHORTCODE_TITLES = 'opentt_unified_show_shortcode_titles';
    const OPTION_IMPORT_PREVIEW = 'opentt_unified_import_preview';
    const OPTION_COMPETITION_DIAGNOSTICS = 'opentt_unified_competition_diagnostics';
    const OPTION_ADMIN_UI_LANGUAGE = 'opentt_unified_admin_ui_language';
    const TABLE_MATCHES = 'opentt_matches';
    const TABLE_GAMES = 'opentt_games';
    const TABLE_SETS = 'opentt_sets';
    const LEGACY_TABLE_MATCHES = 'stkb_matches';
    const LEGACY_TABLE_GAMES = 'stkb_games';
    const LEGACY_TABLE_SETS = 'stkb_sets';
    const MATCH_BLOCK_TEMPLATE_SLUG = 'stkb-match';

    private static $plugin_file = '';
    private static $plugin_dir = '';
    private static $virtual_match_row = null;
    private static $virtual_archive_context = null;

    public static function init($plugin_file)
    {
        self::$plugin_file = $plugin_file;
        self::$plugin_dir = plugin_dir_path($plugin_file);
        self::maybe_migrate_schema();
        OpenTT_Unified_Assets_Module::register();
        OpenTT_Unified_Admin_Module::register();
        OpenTT_Unified_Routing_Module::register();
        OpenTT_Unified_Shortcodes_Module::register();
        OpenTT_Unified_Legacy_Module::register();
        add_action('init', [__CLASS__, 'maybe_flush_rewrite_rules_once'], 99);
        add_action('init', [__CLASS__, 'maybe_setup_default_pages'], 30);
        add_action('admin_init', [__CLASS__, 'maybe_redirect_to_onboarding']);
        add_action('admin_init', [__CLASS__, 'maybe_backfill_player_citizenship_default']);
    }

    public static function activate($plugin_file)
    {
        self::$plugin_file = $plugin_file;
        self::$plugin_dir = plugin_dir_path($plugin_file);
        self::register_legacy_content_types();
        \OpenTT\Unified\WordPress\RewriteRulesManager::flushAndMark(self::OPTION_REWRITE_FLUSHED);
        self::prepare_onboarding_state_on_activate();
        self::maybe_migrate_schema();
        self::maybe_setup_default_pages();
    }

    public static function maybe_flush_rewrite_rules_once()
    {
        \OpenTT\Unified\WordPress\RewriteRulesManager::flushOnce(self::OPTION_REWRITE_FLUSHED);
    }

    private static function prepare_onboarding_state_on_activate()
    {
        \OpenTT\Unified\WordPress\OnboardingManager::prepareOnActivation([
            'state_option_key' => self::OPTION_ONBOARDING_STATE,
            'schema_option_key' => self::OPTION_SCHEMA_VERSION,
            'redirect_transient_key' => 'opentt_unified_onboarding_redirect',
        ]);
    }

    public static function maybe_redirect_to_onboarding()
    {
        \OpenTT\Unified\WordPress\OnboardingManager::maybeRedirectToOnboarding([
            'state_option_key' => self::OPTION_ONBOARDING_STATE,
            'redirect_transient_key' => 'opentt_unified_onboarding_redirect',
            'capability' => self::CAP,
            'onboarding_page_slug' => 'stkb-unified-onboarding',
        ]);
    }

    public static function maybe_setup_default_pages()
    {
        $done = (string) get_option(self::OPTION_DEFAULT_PAGES_SETUP_DONE, '');
        if ($done === '1') {
            return;
        }

        \OpenTT\Unified\WordPress\DefaultPagesProvisioner::ensureCompetitionsPage();
        update_option(self::OPTION_DEFAULT_PAGES_SETUP_DONE, '1', false);
    }

    public static function enqueue_frontend_assets()
    {
        $visual_css = self::build_visual_settings_css(self::get_visual_settings());
        $custom_css = (string) get_option(self::OPTION_CUSTOM_SHORTCODE_CSS, '');
        $custom_css_map = get_option(self::OPTION_CUSTOM_SHORTCODE_CSS_MAP, []);

        \OpenTT\Unified\WordPress\FrontendAssetsEnqueuer::enqueue(
            self::$plugin_dir,
            self::$plugin_file,
            self::VERSION,
            $visual_css,
            $custom_css,
            $custom_css_map
        );
    }

    private static function default_visual_settings()
    {
        return \OpenTT\Unified\Infrastructure\VisualSettings::defaultSettings();
    }

    private static function sanitize_visual_settings($raw)
    {
        return \OpenTT\Unified\Infrastructure\VisualSettings::sanitize($raw);
    }

    private static function get_visual_settings()
    {
        return \OpenTT\Unified\Infrastructure\VisualSettings::get(self::OPTION_VISUAL_SETTINGS);
    }

    private static function get_admin_ui_language()
    {
        return \OpenTT\Unified\WordPress\AdminUiLanguageManager::resolveCurrentLanguage(
            self::OPTION_ADMIN_UI_LANGUAGE,
            self::get_available_admin_ui_languages(),
            'sr'
        );
    }

    private static function is_admin_ui_translation_enabled()
    {
        return \OpenTT\Unified\WordPress\AdminUiLanguageManager::isTranslationEnabled(
            self::get_admin_ui_language(),
            'sr'
        );
    }

    public static function maybe_enable_admin_ui_translation()
    {
        \OpenTT\Unified\WordPress\AdminUiLanguageManager::maybeStartTranslationBuffer(
            self::is_admin_ui_translation_enabled(),
            'stkb-unified',
            [__CLASS__, 'translate_admin_ui_buffer']
        );
    }

    private static function get_available_admin_ui_languages()
    {
        return \OpenTT\Unified\Infrastructure\AdminUiTranslator::availableLanguages(self::$plugin_dir);
    }

    public static function translate_admin_ui_buffer($html)
    {
        if (!is_string($html) || $html === '' || !self::is_admin_ui_translation_enabled()) {
            return $html;
        }

        $lang = self::get_admin_ui_language();
        if ($lang === 'sr') {
            return $html;
        }

        return \OpenTT\Unified\Infrastructure\AdminUiTranslator::translateHtml(
            $html,
            self::$plugin_dir,
            $lang
        );
    }

    public static function should_show_shortcode_titles()
    {
        return \OpenTT\Unified\Infrastructure\VisualSettings::shouldShowShortcodeTitles(
            self::get_visual_settings()
        );
    }

    private static function build_visual_settings_css($settings)
    {
        return \OpenTT\Unified\Infrastructure\VisualSettings::buildCss($settings);
    }

    public static function register_legacy_content_types()
    {
        \OpenTT\Unified\WordPress\LegacyContentTypeRegistrar::register();
    }

    public static function register_shortcodes()
    {
        \OpenTT\Unified\WordPress\ShortcodeRegistrar::register(__CLASS__);
    }

    public static function capture_virtual_match_context()
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        if (is_singular('utakmica')) {
            return;
        }

        // Back-compat fallback: stari linkovi tipa `?p=1234` za legacy utakmicu.
        $legacy_query_id = isset($_GET['p']) ? intval($_GET['p']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($legacy_query_id <= 0 && isset($_GET['post'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $legacy_query_id = intval($_GET['post']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        if ($legacy_query_id > 0) {
            $row = self::db_get_match_by_legacy_id($legacy_query_id);
            if ($row) {
                self::$virtual_match_row = $row;
                global $wp_query;
                if ($wp_query) {
                    $wp_query->is_404 = false;
                    $wp_query->is_singular = true;
                    $wp_query->is_page = false;
                    $wp_query->is_single = false;
                }
                status_header(200);
                nocache_headers();
                return;
            }
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = trim((string) parse_url($uri, PHP_URL_PATH), '/');
        if ($path === '') {
            return;
        }

        $segments = array_values(array_filter(explode('/', $path), 'strlen'));
        $count = count($segments);
        $row = null;

        if ($count >= 3 && $count <= 6) {
            if ($count === 3) {
                $liga = sanitize_title($segments[0]);
                $kolo = sanitize_title($segments[1]);
                $slug = sanitize_title($segments[2]);
                if (strpos($kolo, 'kolo') !== false) {
                    $row = self::db_get_match_by_keys($liga, '', $kolo, $slug);
                }
            } elseif ($count === 4) {
                $liga = sanitize_title($segments[0]);
                $sezona = sanitize_title($segments[1]);
                $kolo = sanitize_title($segments[2]);
                $slug = sanitize_title($segments[3]);
                if (strpos($kolo, 'kolo') !== false) {
                    $row = self::db_get_match_by_keys($liga, $sezona, $kolo, $slug);
                    if (!$row) {
                        $row = self::db_get_match_by_keys($liga, '', $kolo, $slug);
                    }
                }
            } elseif ($count === 5) {
                // Back-compat: /liga/{liga}/{kolo}/utakmica/{slug}
                if (sanitize_title($segments[0]) === 'liga' && sanitize_title($segments[3]) === 'utakmica') {
                    $liga = sanitize_title($segments[1]);
                    $kolo = sanitize_title($segments[2]);
                    $slug = sanitize_title($segments[4]);
                    $row = self::db_get_match_by_keys($liga, '', $kolo, $slug);
                }
            } elseif ($count === 6) {
                // Back-compat: /liga/{liga}/{sezona}/{kolo}/utakmica/{slug}
                if (sanitize_title($segments[0]) === 'liga' && sanitize_title($segments[4]) === 'utakmica') {
                    $liga = sanitize_title($segments[1]);
                    $sezona = sanitize_title($segments[2]);
                    $kolo = sanitize_title($segments[3]);
                    $slug = sanitize_title($segments[5]);
                    $row = self::db_get_match_by_keys($liga, $sezona, $kolo, $slug);
                    if (!$row) {
                        $row = self::db_get_match_by_keys($liga, '', $kolo, $slug);
                    }
                }
            }
        }

        if ($row) {
            self::$virtual_match_row = $row;
            global $wp_query;
            if ($wp_query) {
                $wp_query->is_404 = false;
                $wp_query->is_singular = true;
                $wp_query->is_page = false;
                $wp_query->is_single = false;
            }
            status_header(200);
            nocache_headers();
            return;
        }

        // Virtual archive routing fallback (radi i bez legacy taksonomija/rewrite pravila).
        $first = $count > 0 ? sanitize_title((string) $segments[0]) : '';
        if ($first === 'liga') {
            $liga = $count > 1 ? sanitize_title((string) $segments[1]) : '';
            $sezona = $count > 2 ? sanitize_title((string) $segments[2]) : '';
            if ($liga !== '') {
                self::$virtual_archive_context = [
                    'type' => 'liga_sezona',
                    'liga_slug' => $liga,
                    'sezona_slug' => $sezona,
                    'kolo_slug' => '',
                ];
                global $wp_query;
                if ($wp_query) {
                    $wp_query->is_404 = false;
                    $wp_query->is_archive = true;
                }
                status_header(200);
                nocache_headers();
                return;
            }
        }

        if ($first === 'kolo' && $count > 1) {
            $kolo = sanitize_title((string) $segments[1]);
            if ($kolo !== '') {
                self::$virtual_archive_context = [
                    'type' => 'kolo',
                    'liga_slug' => '',
                    'sezona_slug' => '',
                    'kolo_slug' => $kolo,
                ];
                global $wp_query;
                if ($wp_query) {
                    $wp_query->is_404 = false;
                    $wp_query->is_archive = true;
                }
                status_header(200);
                nocache_headers();
            }
        }
    }

    private static function current_archive_context()
    {
        if (is_tax('liga_sezona')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $parsed = self::parse_legacy_liga_sezona((string) $term->slug, '');
                return [
                    'type' => 'liga_sezona',
                    'liga_slug' => sanitize_title((string) ($parsed['league_slug'] ?? '')),
                    'sezona_slug' => sanitize_title((string) ($parsed['season_slug'] ?? '')),
                    'kolo_slug' => '',
                ];
            }
        }

        if (is_tax('kolo')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                return [
                    'type' => 'kolo',
                    'liga_slug' => '',
                    'sezona_slug' => '',
                    'kolo_slug' => sanitize_title((string) $term->slug),
                ];
            }
        }

        if (is_array(self::$virtual_archive_context) && !empty(self::$virtual_archive_context['type'])) {
            return self::$virtual_archive_context;
        }

        $liga = sanitize_title((string) (get_query_var('liga') ?: ''));
        $sezona = sanitize_title((string) (get_query_var('sezona') ?: ''));
        $kolo = sanitize_title((string) (get_query_var('kolo') ?: ''));
        if ($liga === '' && isset($_GET['liga'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $liga = sanitize_title((string) wp_unslash($_GET['liga'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        if ($sezona === '' && isset($_GET['sezona'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $sezona = sanitize_title((string) wp_unslash($_GET['sezona'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        if ($kolo === '' && isset($_GET['kolo'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $kolo = sanitize_title((string) wp_unslash($_GET['kolo'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        if ($kolo !== '') {
            return [
                'type' => 'kolo',
                'liga_slug' => '',
                'sezona_slug' => '',
                'kolo_slug' => $kolo,
            ];
        }

        if ($liga === '' && is_singular('liga')) {
            $obj = get_queried_object();
            if ($obj && !empty($obj->post_name)) {
                $liga = sanitize_title((string) $obj->post_name);
            }
        }
        if ($sezona === '' && is_singular('sezona')) {
            $obj = get_queried_object();
            if ($obj && !empty($obj->post_name)) {
                $sezona = sanitize_title((string) $obj->post_name);
            }
        }
        if ($liga === '' && $sezona !== '') {
            global $wpdb;
            $table = OpenTT_Unified_Core::db_table('matches');
            if (self::table_exists($table)) {
                $liga_guess = $wpdb->get_var($wpdb->prepare("SELECT liga_slug FROM {$table} WHERE sezona_slug=%s AND liga_slug<>'' ORDER BY id DESC LIMIT 1", $sezona));
                if (is_string($liga_guess) && $liga_guess !== '') {
                    $liga = sanitize_title($liga_guess);
                }
            }
        }

        if ($liga !== '' || $sezona !== '') {
            return [
                'type' => 'liga_sezona',
                'liga_slug' => $liga,
                'sezona_slug' => $sezona,
                'kolo_slug' => '',
            ];
        }

        return null;
    }

    public static function template_include($template)
    {
        $ctx = self::current_match_context();
        if (!$ctx || empty($ctx['db_row'])) {
            $archive_ctx = self::current_archive_context();
            // Nikada ne prepisuj pravi WP taxonomy template izbor.
            // Plugin fallback za arhive koristimo samo za virtual/query fallback slučajeve.
            if (is_tax('kolo') || is_tax('liga_sezona')) {
                return $template;
            }
            if (is_singular('klub')) {
                if (self::has_user_template_for_context('single-klub')) {
                    return $template;
                }
                return self::plugin_fallback_template_path();
            }
            if (is_singular('igrac')) {
                if (self::has_user_template_for_context('single-igrac')) {
                    return $template;
                }
                return self::plugin_fallback_template_path();
            }
            if (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'kolo') {
                if (function_exists('wp_is_block_theme') && wp_is_block_theme() && self::has_any_archive_block_template_for_type('kolo')) {
                    $bridge = self::$plugin_dir . 'templates/block-archive-template.php';
                    if (is_readable($bridge)) {
                        return $bridge;
                    }
                }
                $php_tpl = self::find_php_template_for_context('taxonomy-kolo');
                if ($php_tpl !== '') {
                    return $php_tpl;
                }
                if (self::has_user_template_for_context('taxonomy-kolo')) {
                    return $template;
                }
                return self::plugin_fallback_template_path();
            }
            if (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona') {
                if (function_exists('wp_is_block_theme') && wp_is_block_theme() && self::has_any_archive_block_template_for_type('liga_sezona')) {
                    $bridge = self::$plugin_dir . 'templates/block-archive-template.php';
                    if (is_readable($bridge)) {
                        return $bridge;
                    }
                }
                $php_tpl = self::find_php_template_for_context('taxonomy-liga_sezona');
                if ($php_tpl === '') {
                    $php_tpl = self::find_php_template_for_context('taxonomy-liga-sezona');
                }
                if ($php_tpl !== '') {
                    return $php_tpl;
                }
                if (self::has_user_template_for_context('taxonomy-liga_sezona')) {
                    return $template;
                }
                return self::plugin_fallback_template_path();
            }
            return $template;
        }

        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            $bridge = self::$plugin_dir . 'templates/block-match-template.php';
            if (is_readable($bridge)) {
                return $bridge;
            }
        }

        $theme_override = trailingslashit(get_stylesheet_directory()) . 'stkb/single-utakmica.php';
        if (is_readable($theme_override)) {
            return $theme_override;
        }

        $plugin_template = self::$plugin_dir . 'templates/single-utakmica.php';
        if (is_readable($plugin_template)) {
            return $plugin_template;
        }

        return $template;
    }

    private static function has_user_template_for_context($slug)
    {
        $slug = sanitize_key((string) $slug);
        if ($slug === '') {
            return false;
        }
        $variants = array_values(array_unique([$slug, str_replace('_', '-', $slug)]));

        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            foreach ($variants as $v) {
                if (self::has_block_template_slug($v)) {
                    return true;
                }
            }
            // Allow custom plugin-specific block template names if user defines them.
            if ($slug === 'single-klub' && self::has_block_template_slug('stkb-club')) {
                return true;
            }
            if ($slug === 'single-igrac' && self::has_block_template_slug('stkb-player')) {
                return true;
            }
            if ($slug === 'taxonomy-kolo' && self::has_block_template_slug('stkb-kolo')) {
                return true;
            }
            if ($slug === 'taxonomy-liga_sezona' && self::has_block_template_slug('stkb-competition')) {
                return true;
            }
            foreach ($variants as $v) {
                if (self::find_php_template_for_context($v) !== '') {
                    return true;
                }
            }
            return false;
        }

        foreach ($variants as $v) {
            if (self::find_php_template_for_context($v) !== '') {
                return true;
            }
        }
        return false;
    }

    private static function find_php_template_for_context($slug)
    {
        $slug = sanitize_key((string) $slug);
        if ($slug === '') {
            return '';
        }
        $php = $slug . '.php';
        $candidates = [
            trailingslashit(get_stylesheet_directory()) . $php,
            trailingslashit(get_stylesheet_directory()) . 'stkb/' . $php,
            trailingslashit(get_template_directory()) . $php,
            trailingslashit(get_template_directory()) . 'stkb/' . $php,
        ];
        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }
        return '';
    }

    private static function get_archive_block_template_slugs_for_type($type)
    {
        $type = sanitize_key((string) $type);
        if ($type === 'liga_sezona') {
            return ['stkb-competition', 'taxonomy-liga-sezona', 'taxonomy-liga_sezona'];
        }
        if ($type === 'kolo') {
            return ['stkb-kolo', 'taxonomy-kolo'];
        }
        return [];
    }

    private static function has_any_archive_block_template_for_type($type)
    {
        $slugs = self::get_archive_block_template_slugs_for_type($type);
        if (empty($slugs)) {
            return false;
        }
        foreach ($slugs as $slug) {
            if (self::has_block_template_slug($slug)) {
                return true;
            }
        }
        return false;
    }

    private static function get_block_template_by_slug($slug)
    {
        if (!function_exists('get_block_template')) {
            return null;
        }

        $slug = trim((string) $slug);
        if ($slug === '') {
            return null;
        }

        $theme = get_stylesheet();
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

    public static function render_archive_block_template_content()
    {
        $archive_ctx = self::current_archive_context();
        if (!is_array($archive_ctx)) {
            return self::render_auto_fallback_content();
        }

        $type = sanitize_key((string) ($archive_ctx['type'] ?? ''));
        $slugs = self::get_archive_block_template_slugs_for_type($type);
        if (empty($slugs)) {
            return self::render_auto_fallback_content();
        }

        foreach ($slugs as $slug) {
            $tpl = self::get_block_template_by_slug($slug);
            if (!$tpl || empty($tpl->content)) {
                continue;
            }
            $html = self::render_blocks_with_shortcode_support((string) $tpl->content);
            return do_shortcode($html);
        }

        return self::render_auto_fallback_content();
    }

    private static function has_block_template_slug($slug)
    {
        if (!function_exists('get_block_template')) {
            return false;
        }
        $slug = trim((string) $slug);
        if ($slug === '') {
            return false;
        }

        $theme = get_stylesheet();
        $tpl = get_block_template($theme . '//' . $slug, 'wp_template');
        if ($tpl) {
            return true;
        }
        $parent = get_template();
        if ($parent && $parent !== $theme) {
            $tpl = get_block_template($parent . '//' . $slug, 'wp_template');
            if ($tpl) {
                return true;
            }
        }

        $posts = get_posts([
            'post_type' => 'wp_template',
            'name' => $slug,
            'numberposts' => 1,
            'post_status' => ['publish', 'draft'],
        ]);
        if (!empty($posts[0])) {
            return true;
        }

        $posts = get_posts([
            'post_type' => 'wp_template',
            'posts_per_page' => 20,
            'post_status' => ['publish', 'draft'],
            's' => '//' . $slug,
        ]);
        if (!empty($posts)) {
            foreach ($posts as $p) {
                if (strpos((string) $p->post_name, '//' . $slug) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function plugin_fallback_template_path()
    {
        $is_block = function_exists('wp_is_block_theme') && wp_is_block_theme();
        $path = self::$plugin_dir . 'templates/fallback-template.php';
        if ($is_block) {
            $block_path = self::$plugin_dir . 'templates/block-fallback-template.php';
            if (is_readable($block_path)) {
                return $block_path;
            }
        }
        if (is_readable($path)) {
            return $path;
        }
        return get_index_template();
    }

    public static function render_match_block_template_content()
    {
        $tpl = self::get_match_block_template();
        if ($tpl && !empty($tpl->content)) {
            $html = self::render_blocks_with_shortcode_support((string) $tpl->content);
            // Safety net: ako su shortcode-i ostali kao plain tekst/paragraph, ipak ih izvrši.
            return do_shortcode($html);
        }

        // Fallback sadržaj ako block template nije pronađen.
        return '<div class="wp-site-blocks"><main class="opentt-match-page" style="max-width:1100px;margin:0 auto;padding:20px 16px;">'
            . do_shortcode('[opentt_match_teams]')
            . do_shortcode('[opentt_standings_table]')
            . do_shortcode('[opentt_match_games]')
            . do_shortcode('[opentt_top_players]')
            . do_shortcode('[opentt_mvp]')
            . do_shortcode('[opentt_h2h]')
            . do_shortcode('[opentt_match_video]')
            . do_shortcode('[opentt_match_report]')
            . '</main></div>';
    }

    public static function render_auto_fallback_content()
    {
        $archive_ctx = self::current_archive_context();

        if (is_singular('klub')) {
            return '<main class="opentt-auto-page opentt-auto-klub" style="max-width:1100px;margin:0 auto;padding:20px 16px;">'
                . do_shortcode('[opentt_club_info]')
                . do_shortcode('[opentt_players]')
                . do_shortcode('[opentt_club_form]')
                . do_shortcode('[opentt_team_stats filter="true"]')
                . do_shortcode('[opentt_club_news limit="6" columns="2"]')
                . '</main>';
        }
        if (is_singular('igrac')) {
            return '<main class="opentt-auto-page opentt-auto-igrac" style="max-width:1100px;margin:0 auto;padding:20px 16px;">'
                . do_shortcode('[opentt_player_info]')
                . do_shortcode('[opentt_player_stats filter="true"]')
                . do_shortcode('[opentt_player_transfers]')
                . do_shortcode('[opentt_player_news limit="6" columns="2"]')
                . '</main>';
        }
        if (is_tax('liga_sezona') || (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona')) {
            $liga = is_array($archive_ctx) ? sanitize_title((string) ($archive_ctx['liga_slug'] ?? '')) : '';
            $sezona = is_array($archive_ctx) ? sanitize_title((string) ($archive_ctx['sezona_slug'] ?? '')) : '';
            $args = '';
            if ($liga !== '') {
                $args .= ' liga="' . esc_attr($liga) . '"';
            }
            if ($sezona !== '') {
                $args .= ' sezona="' . esc_attr($sezona) . '"';
            }
            return '<main class="opentt-auto-page opentt-auto-liga-sezona" style="max-width:1100px;margin:0 auto;padding:20px 16px;">'
                . do_shortcode('[opentt_competition_info' . $args . ']')
                . do_shortcode('[opentt_standings_table' . $args . ']')
                . do_shortcode('[opentt_top_players' . $args . ']')
                . do_shortcode('[opentt_matches_grid columns="4" limit="12" filter="true" infinite="true"' . $args . ']')
                . '</main>';
        }
        if (is_tax('kolo') || (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'kolo')) {
            $kolo = is_array($archive_ctx) ? sanitize_title((string) ($archive_ctx['kolo_slug'] ?? '')) : '';
            $kolo_arg = $kolo !== '' ? ' kolo="' . esc_attr($kolo) . '"' : '';
            return '<main class="opentt-auto-page opentt-auto-kolo" style="max-width:1100px;margin:0 auto;padding:20px 16px;">'
                . do_shortcode('[opentt_matches_grid columns="4" limit="12" filter="true" infinite="true"' . $kolo_arg . ']')
                . do_shortcode('[opentt_standings_table' . $kolo_arg . ']')
                . do_shortcode('[opentt_top_players' . $kolo_arg . ']')
                . '</main>';
        }

        return '';
    }

    private static function render_blocks_with_shortcode_support($content)
    {
        if (!function_exists('parse_blocks') || !function_exists('render_block')) {
            return do_shortcode((string) $content);
        }

        $blocks = parse_blocks((string) $content);
        if (!is_array($blocks) || empty($blocks)) {
            return do_shortcode((string) $content);
        }

        $out = '';
        foreach ($blocks as $block) {
            $name = isset($block['blockName']) ? (string) $block['blockName'] : '';
            if ($name === 'core/shortcode') {
                $raw = '';
                if (!empty($block['innerHTML'])) {
                    $raw = (string) $block['innerHTML'];
                } elseif (!empty($block['innerContent']) && is_array($block['innerContent'])) {
                    $raw = implode('', array_filter($block['innerContent'], 'is_string'));
                }
                $out .= do_shortcode(trim($raw));
                continue;
            }
            $out .= render_block($block);
        }

        return $out;
    }

    public static function register_admin_menu()
    {
        $menu_icon = 'dashicons-awards';
        $icon_path = trailingslashit(self::$plugin_dir) . 'assets/icon-admin.svg';
        if (is_readable($icon_path)) {
            $menu_icon = plugins_url('assets/icon-admin.svg', self::$plugin_file);
        }

        add_menu_page(
            'OpenTT',
            'OpenTT',
            self::CAP,
            'stkb-unified',
            [__CLASS__, 'render_dashboard_page'],
            $menu_icon,
            26
        );

        add_submenu_page('stkb-unified', 'Kontrolna tabla', 'Kontrolna tabla', self::CAP, 'stkb-unified', [__CLASS__, 'render_dashboard_page']);
        add_submenu_page('stkb-unified', 'Utakmice', 'Utakmice', self::CAP, 'stkb-unified-matches', [__CLASS__, 'render_matches_page']);
        add_submenu_page('stkb-unified', 'Uživo', 'Uživo', self::CAP, 'stkb-unified-live', [__CLASS__, 'render_live_page']);
        add_submenu_page('stkb-unified', 'Klubovi', 'Klubovi', self::CAP, 'stkb-unified-clubs', [__CLASS__, 'render_clubs_page']);
        add_submenu_page('stkb-unified', 'Igrači', 'Igrači', self::CAP, 'stkb-unified-players', [__CLASS__, 'render_players_page']);
        add_submenu_page('stkb-unified', 'Takmičenja', 'Takmičenja', self::CAP, 'stkb-unified-competitions', [__CLASS__, 'render_competition_rules_page']);
        add_submenu_page('stkb-unified', 'Uvezi/Izvezi', 'Uvezi/Izvezi', self::CAP, 'stkb-unified-transfer', [__CLASS__, 'render_import_export_page']);
        add_submenu_page('stkb-unified', 'Prilagođavanje', 'Prilagođavanje', self::CAP, 'stkb-unified-customize', [__CLASS__, 'render_customize_page']);
        add_submenu_page('stkb-unified', 'Podešavanja', 'Podešavanja', self::CAP, 'stkb-unified-settings', [__CLASS__, 'render_settings_page']);

        add_submenu_page(null, 'Dodaj utakmicu', 'Dodaj utakmicu', self::CAP, 'stkb-unified-add-match', [__CLASS__, 'render_match_edit_page']);
        add_submenu_page(null, 'Dodaj klub', 'Dodaj klub', self::CAP, 'stkb-unified-add-club', [__CLASS__, 'render_club_edit_page']);
        add_submenu_page(null, 'Dodaj igrača', 'Dodaj igrača', self::CAP, 'stkb-unified-add-player', [__CLASS__, 'render_player_edit_page']);
        add_submenu_page(null, 'Dodaj takmičenje', 'Dodaj takmičenje', self::CAP, 'stkb-unified-add-competition', [__CLASS__, 'render_competition_rule_edit_page']);
        add_submenu_page(null, 'First Time Setup', 'First Time Setup', self::CAP, 'stkb-unified-onboarding', [__CLASS__, 'render_onboarding_page']);
        // Internal pages (hidden): keep entities available as backend model.
        add_submenu_page(null, 'Lige', 'Lige', self::CAP, 'stkb-unified-leagues', [__CLASS__, 'render_leagues_page']);
        add_submenu_page(null, 'Sezone', 'Sezone', self::CAP, 'stkb-unified-seasons', [__CLASS__, 'render_seasons_page']);
        add_submenu_page(null, 'Dodaj ligu', 'Dodaj ligu', self::CAP, 'stkb-unified-add-league', [__CLASS__, 'render_league_edit_page']);
        add_submenu_page(null, 'Dodaj sezonu', 'Dodaj sezonu', self::CAP, 'stkb-unified-add-season', [__CLASS__, 'render_season_edit_page']);
    }

    public static function render_dashboard_page()
    {
        self::require_cap();
        global $wpdb;
        $counts = self::get_migration_counts();
        $matches_table = OpenTT_Unified_Core::db_table('matches');
        $games_table = OpenTT_Unified_Core::db_table('games');
        $latest_players = get_posts([
            'post_type' => 'igrac',
            'numberposts' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];
        $latest_clubs = get_posts([
            'post_type' => 'klub',
            'numberposts' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];
        $latest_matches = [];
        if (self::table_exists($matches_table) && self::table_exists($games_table)) {
            $latest_matches = $wpdb->get_results(
                "SELECT m.*, COALESCE(gc.games_count, 0) AS games_count
                 FROM {$matches_table} m
                 LEFT JOIN (
                    SELECT match_id, COUNT(*) AS games_count
                    FROM {$games_table}
                    GROUP BY match_id
                 ) gc ON gc.match_id = m.id
                 ORDER BY m.match_date DESC, m.id DESC
                 LIMIT 5"
            ) ?: [];
        }
        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>OpenTT Kontrolna Tabla</h1>';
        echo '<button type="button" class="opentt-help-card opentt-help-open">';
        echo '<strong>Vodič za unos</strong>';
        echo '<span>Klikni za korake unosa: takmičenja, klubovi, igrači, utakmice, partije i setovi.</span>';
        echo '</button>';
        echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:8px 0 18px 0;">';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-match')) . '">+ Dodaj utakmicu</a>';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-club')) . '">+ Dodaj klub</a>';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-player')) . '">+ Dodaj igrača</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-competition')) . '">+ Dodaj takmičenje</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-onboarding')) . '">Pokreni First Time Setup</a>';
        echo '</div>';

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">';

        echo '<div class="opentt-panel opentt-dashboard-primary-card"><strong>Ukupno utakmica u bazi</strong><ul>';
        echo '<li>Ukupno: <strong>' . esc_html((string) $counts['db_matches']) . '</strong></li>';
        echo '<li>Partije: <strong>' . esc_html((string) $counts['db_games']) . '</strong></li>';
        echo '<li>Setovi: <strong>' . esc_html((string) $counts['db_sets']) . '</strong></li>';
        echo '</ul></div>';

        echo '<div class="opentt-panel"><div style="display:flex;justify-content:space-between;align-items:center;"><strong>Najnovije utakmice</strong><a href="' . esc_url(admin_url('admin.php?page=stkb-unified-matches')) . '">Vidi sve</a></div>';
        if (!$latest_matches) {
            echo '<p>Nema utakmica.</p>';
        } else {
            echo '<ul>';
            foreach ($latest_matches as $m) {
                $home = (string) get_the_title((int) $m->home_club_post_id);
                $away = (string) get_the_title((int) $m->away_club_post_id);
                $front_url = self::match_permalink($m);
                $date = self::display_match_date((string) $m->match_date);
                $games_ok = intval($m->games_count) > 0 ? '✓' : '✗';
                echo '<li><a href="' . esc_url($front_url) . '" target="_blank" rel="noopener">' . esc_html($home . ' — ' . $away) . '</a> <strong>' . esc_html((string) intval($m->home_score) . ':' . (string) intval($m->away_score)) . '</strong> <span style="opacity:.75;">' . esc_html($date) . '</span> <span style="font-weight:700;">[' . esc_html($games_ok) . ']</span></li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '<div class="opentt-panel"><div style="display:flex;justify-content:space-between;align-items:center;"><strong>Najnoviji igrači</strong><a href="' . esc_url(admin_url('admin.php?page=stkb-unified-players')) . '">Vidi sve</a></div>';
        if (!$latest_players) {
            echo '<p>Nema igrača.</p>';
        } else {
            echo '<ul>';
            foreach ($latest_players as $p) {
                $front_url = get_permalink((int) $p->ID) ?: '';
                $edit_url = admin_url('admin.php?page=stkb-unified-add-player&action=edit&id=' . (int) $p->ID);
                if ($front_url) {
                    echo '<li><a href="' . esc_url($front_url) . '" target="_blank" rel="noopener">' . esc_html((string) $p->post_title) . '</a></li>';
                } else {
                    echo '<li><a href="' . esc_url($edit_url) . '">' . esc_html((string) $p->post_title) . '</a></li>';
                }
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '<div class="opentt-panel"><div style="display:flex;justify-content:space-between;align-items:center;"><strong>Najnoviji klubovi</strong><a href="' . esc_url(admin_url('admin.php?page=stkb-unified-clubs')) . '">Vidi sve</a></div>';
        if (!$latest_clubs) {
            echo '<p>Nema klubova.</p>';
        } else {
            echo '<ul>';
            foreach ($latest_clubs as $c) {
                $front_url = get_permalink((int) $c->ID) ?: '';
                $edit_url = admin_url('admin.php?page=stkb-unified-add-club&action=edit&id=' . (int) $c->ID);
                if ($front_url) {
                    echo '<li><a href="' . esc_url($front_url) . '" target="_blank" rel="noopener">' . esc_html((string) $c->post_title) . '</a></li>';
                } else {
                    echo '<li><a href="' . esc_url($edit_url) . '">' . esc_html((string) $c->post_title) . '</a></li>';
                }
            }
            echo '</ul>';
        }
        echo '</div>';

        $latest_competitions = get_posts([
            'post_type' => 'pravilo_takmicenja',
            'numberposts' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];
        echo '<div class="opentt-panel"><div style="display:flex;justify-content:space-between;align-items:center;"><strong>Takmičenja</strong><a href="' . esc_url(admin_url('admin.php?page=stkb-unified-competitions')) . '">Vidi sve</a></div>';
        if (!$latest_competitions) {
            echo '<p>Nema takmičenja.</p>';
        } else {
            echo '<ul>';
            foreach ($latest_competitions as $t) {
                $liga_slug = (string) get_post_meta((int) $t->ID, 'opentt_competition_league_slug', true);
                $sezona_slug = (string) get_post_meta((int) $t->ID, 'opentt_competition_season_slug', true);
                $edit_url = admin_url('admin.php?page=stkb-unified-add-competition&action=edit&id=' . (int) $t->ID);
                echo '<li><a href="' . esc_url($edit_url) . '">' . esc_html(self::slug_to_title($liga_slug) . ' / ' . self::slug_to_title($sezona_slug)) . '</a></li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '</div>';
        echo '<div class="opentt-panel" style="margin-top:16px;">';
        echo '<h2 style="margin-top:0;">O projektu</h2>';
        echo '<p>OpenTT je open-source sistem namenjen vođenju i prikazu stonoteniskih takmičenja, klubova i igrača na moderan, brz i održiv način.</p>';
        echo '<p>Projekat je nastao iz stvarne potrebe da se stonoteniska takmičenja prikažu onako kako se zaista igraju: kao timski mečevi koji se sastoje od individualnih duela, sa jasnim kontekstom, istorijom i statistikama.</p>';
        echo '<p>Postojeća rešenja uglavnom tretiraju sport ili kao čisto timski, ili kao isključivo individualni. Stoni tenis je specifičan i zahteva drugačiji pristup.</p>';
        echo '<p style="margin-bottom:0;"><strong>Autor projekta:</strong> <a href="https://instagram.com/tradicije" target="_blank" rel="noopener">Aleksa Dimitrijević</a>.</p>';
        echo '</div>';

        echo '<div class="opentt-help-modal" id="opentt-help-modal" hidden>';
        echo '  <div class="opentt-help-dialog" role="dialog" aria-modal="true" aria-label="Vodič za unos podataka">';
        echo '    <div class="opentt-help-head">';
        echo '      <h2>Vodič za unos podataka</h2>';
        echo '      <button type="button" class="button-link opentt-help-close" aria-label="Zatvori">×</button>';
        echo '    </div>';
        echo '    <div class="opentt-help-body">';
        echo '      <button type="button" class="opentt-help-step opentt-help-step-open" data-topic="takmicenja"><h3>1. Takmičenja</h3><p>Dodaj ligu, sezonu i pravila takmičenja.</p></button>';
        echo '      <button type="button" class="opentt-help-step opentt-help-step-open" data-topic="klubovi"><h3>2. Klubovi</h3><p>Unesi osnovne podatke kluba i grb.</p></button>';
        echo '      <button type="button" class="opentt-help-step opentt-help-step-open" data-topic="igraci"><h3>3. Igrači</h3><p>Poveži igrača sa klubom i dopuni profil.</p></button>';
        echo '      <button type="button" class="opentt-help-step opentt-help-step-open" data-topic="utakmice"><h3>4. Utakmice</h3><p>Kreiraj utakmicu i unesi rezultat.</p></button>';
        echo '      <button type="button" class="opentt-help-step opentt-help-step-open" data-topic="partije"><h3>5. Partije</h3><p>Batch unos partija, dubl je automatski.</p></button>';
        echo '      <button type="button" class="opentt-help-step opentt-help-step-open" data-topic="setovi"><h3>6. Setovi</h3><p>Unesi setove po partiji i proveri zbir.</p></button>';
        echo '      <button type="button" class="opentt-help-step opentt-help-step-open" data-topic="retro"><h3>7. Retro unos</h3><p>Za stare sezone koristi listu svih igrača.</p></button>';
        echo '      <button type="button" class="opentt-help-step opentt-help-step-open" data-topic="provera"><h3>8. Provera</h3><p>Na kraju proveri frontend prikaz.</p></button>';
        echo '      <div class="opentt-help-detail" data-topic="takmicenja" hidden><button type="button" class="button opentt-help-back">← Nazad</button><h3>Takmičenja - detaljno</h3><p><strong>Dodavanje:</strong></p><ol><li>Idi na <em>Takmičenja</em> i klikni <strong>+ Dodaj takmičenje</strong>.</li><li>Unesi naziv lige i sezonu (npr. Kvalitetna Liga / 2025-26).</li><li>Izaberi format partija (A ili B), bodovanje i savez.</li><li>Sačuvaj takmičenje.</li></ol><p><strong>Izmena:</strong> U listi takmičenja klikni <strong>Uredi</strong>, promeni pravila i sačuvaj.</p><p><strong>Brisanje:</strong> Koristi <strong>Obriši</strong> samo ako si siguran da takmičenje nije već povezano sa unetim utakmicama.</p><p><strong>Važno:</strong> Najpre unesi takmičenje, pa tek onda utakmice i partije.</p></div>';
        echo '      <div class="opentt-help-detail" data-topic="klubovi" hidden><button type="button" class="button opentt-help-back">← Nazad</button><h3>Klubovi - detaljno</h3><p><strong>Dodavanje:</strong></p><ol><li>Idi na <em>Klubovi</em> i klikni <strong>+ Dodaj klub</strong>.</li><li>Unesi naziv, opis i grb.</li><li>Popuni podatke: grad, kontakt, email, adrese, termin igranja i ostalo.</li><li>Sačuvaj.</li></ol><p><strong>Izmena:</strong> U listi klubova klikni <strong>Uredi</strong>, promeni podatke i sačuvaj.</p><p><strong>Brisanje:</strong> Klikni <strong>Obriši</strong> samo kada si siguran da klub ne koristiš u postojećim unosima.</p><p><strong>Savet:</strong> Grb i tačan naziv kluba odmah proveri na frontend-u.</p></div>';
        echo '      <div class="opentt-help-detail" data-topic="igraci" hidden><button type="button" class="button opentt-help-back">← Nazad</button><h3>Igrači - detaljno</h3><p><strong>Dodavanje:</strong></p><ol><li>Idi na <em>Igrači</em> i klikni <strong>+ Dodaj igrača</strong>.</li><li>Unesi ime i prezime, biografiju i sliku.</li><li>Poveži igrača sa trenutnim klubom.</li><li>Unesi datum rođenja, mesto rođenja i državljanstvo.</li><li>Sačuvaj.</li></ol><p><strong>Izmena:</strong> U listi igrača klikni <strong>Uredi</strong>, promeni podatke i sačuvaj.</p><p><strong>Brisanje:</strong> Koristi <strong>Obriši</strong> oprezno, posebno ako igrač ima istoriju partija.</p><p><strong>Napomena:</strong> Državljanstvo je podrazumevano Srbija, ali možeš ga promeniti po igraču.</p></div>';
        echo '      <div class="opentt-help-detail" data-topic="utakmice" hidden><button type="button" class="button opentt-help-back">← Nazad</button><h3>Utakmice - detaljno</h3><p><strong>Dodavanje:</strong></p><ol><li>Idi na <em>Utakmice</em> i klikni <strong>+ Dodaj utakmicu</strong>.</li><li>Izaberi takmičenje, kolo i datum.</li><li>Izaberi domaći i gostujući klub.</li><li>Unesi konačan rezultat utakmice.</li><li>Sačuvaj utakmicu.</li></ol><p><strong>Izmena:</strong> U listi utakmica klikni <strong>Uredi</strong>, promeni podatke i sačuvaj.</p><p><strong>Brisanje:</strong> Brisanje utakmice briše i povezane partije i setove.</p><p><strong>Važno:</strong> Rezultat određuje maksimalan broj partija koje možeš da uneseš.</p></div>';
        echo '      <div class="opentt-help-detail" data-topic="partije" hidden><button type="button" class="button opentt-help-back">← Nazad</button><h3>Partije - detaljno</h3><p><strong>Kako uneti:</strong></p><ol><li>Otvori utakmicu kroz <strong>Uredi</strong>.</li><li>U sekciji batch unosa unesi igrače za svaku partiju.</li><li>Dubl partija se automatski određuje po pravilima takmičenja.</li><li>Za singl partije unosi se po jedan igrač sa svake strane.</li><li>Na kraju klikni <strong>Sačuvaj sve partije</strong>.</li></ol><p><strong>Izmena:</strong> Ponovo otvori utakmicu, ispravi redove partija i sačuvaj.</p><p><strong>Brisanje:</strong> Ako ostaviš partiju praznom, sistem je briše pri snimanju.</p></div>';
        echo '      <div class="opentt-help-detail" data-topic="setovi" hidden><button type="button" class="button opentt-help-back">← Nazad</button><h3>Setovi - detaljno</h3><p><strong>Unos setova:</strong></p><ol><li>Za svaku partiju imaš polja Set 1 do Set 5.</li><li>Unosi poene domaćina i gosta po setu.</li><li>Proveri da li je broj osvojenih setova logičan u odnosu na rezultat partije.</li><li>Sačuvaj sve partije.</li></ol><p><strong>Izmena:</strong> Ispravi setove i ponovo sačuvaj.</p><p><strong>Napomena:</strong> Ako su setovi uneti, a ukupni setovi prazni, sistem automatski računa zbir.</p></div>';
        echo '      <div class="opentt-help-detail" data-topic="retro" hidden><button type="button" class="button opentt-help-back">← Nazad</button><h3>Retro unos - detaljno</h3><p><strong>Kada koristiti:</strong> Unos starih sezona kada igrači više nisu u trenutnom klubu.</p><p><strong>Koraci:</strong></p><ol><li>U partiji klikni <strong>Lista igrača</strong> pored dropdown-a.</li><li>U popup-u pretraži ime igrača.</li><li>Klikni igrača iz liste i sistem ga ubacuje u polje.</li><li>Nastavi unos partije i setova.</li></ol><p><strong>Napomena:</strong> Ako je igrač van trenutnog kluba, to je podržano i vidljivo u oznaci.</p></div>';
        echo '      <div class="opentt-help-detail" data-topic="provera" hidden><button type="button" class="button opentt-help-back">← Nazad</button><h3>Provera - detaljno</h3><p><strong>Posle svakog unosa proveri:</strong></p><ol><li>Frontend utakmicu: rezultat, partije, setove, MVP i ostale blokove.</li><li>Frontend klub: forma, vesti, statistika ekipe.</li><li>Frontend igrača: info, statistika, opentt_player_transfers i vesti igrača.</li></ol><p><strong>Ako vidiš grešku:</strong></p><ol><li>Vrati se u admin na odgovarajuću stavku.</li><li>Ispravi podatke.</li><li>Sačuvaj i osveži frontend stranu.</li></ol><p><strong>Savet:</strong> Najbrže je proveravati odmah nakon unosa, ne na kraju dana.</p></div>';
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
        echo <<<'JS'
<script>
(function(){
  var modal = document.getElementById('opentt-help-modal');
  if (!modal) { return; }
  var body = modal.querySelector('.opentt-help-body');
  function showOverview(){
    if (!body) { return; }
    body.querySelectorAll('.opentt-help-step-open').forEach(function(el){ el.hidden = false; });
    body.querySelectorAll('.opentt-help-detail').forEach(function(el){ el.hidden = true; });
  }
  function showTopic(topic){
    if (!body || !topic) { return; }
    body.querySelectorAll('.opentt-help-step-open').forEach(function(el){ el.hidden = true; });
    body.querySelectorAll('.opentt-help-detail').forEach(function(el){
      el.hidden = (el.getAttribute('data-topic') !== topic);
    });
  }
  function openModal(){ modal.removeAttribute('hidden'); }
  function closeModal(){ modal.setAttribute('hidden', 'hidden'); showOverview(); }
  document.addEventListener('click', function(e){
    var openBtn = e.target.closest('.opentt-help-open');
    if (openBtn) { openModal(); return; }
    var stepBtn = e.target.closest('.opentt-help-step-open');
    if (stepBtn) { showTopic(stepBtn.getAttribute('data-topic')); return; }
    var backBtn = e.target.closest('.opentt-help-back');
    if (backBtn) { showOverview(); return; }
    var closeBtn = e.target.closest('.opentt-help-close');
    if (closeBtn || e.target === modal) { closeModal(); }
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && !modal.hasAttribute('hidden')) {
      closeModal();
    }
  });
  showOverview();
})();
</script>
JS;
        echo '</div>';
    }

    public static function render_matches_page()
    {
        self::require_cap();
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');
        $rows = [];
        $liga_options = [];
        $sezona_options = [];
        $kolo_options = [];

        $f_liga = isset($_GET['liga_slug']) ? sanitize_title((string) wp_unslash($_GET['liga_slug'])) : '';
        $f_sezona = isset($_GET['sezona_slug']) ? sanitize_title((string) wp_unslash($_GET['sezona_slug'])) : '';
        $f_kolo = isset($_GET['kolo_slug']) ? sanitize_title((string) wp_unslash($_GET['kolo_slug'])) : '';
        $f_club = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;
        $f_games = isset($_GET['games_status']) ? sanitize_key((string) wp_unslash($_GET['games_status'])) : '';
        $sort_by = isset($_GET['sort_by']) ? sanitize_key((string) wp_unslash($_GET['sort_by'])) : 'date';
        $sort_dir = isset($_GET['sort_dir']) ? strtoupper(sanitize_key((string) wp_unslash($_GET['sort_dir']))) : 'DESC';
        $sort_dir = in_array($sort_dir, ['ASC', 'DESC'], true) ? $sort_dir : 'DESC';

        if (self::table_exists($table)) {
            self::ensure_matches_live_column($table);
            $liga_options = $wpdb->get_col("SELECT DISTINCT liga_slug FROM {$table} WHERE liga_slug <> '' ORDER BY liga_slug ASC") ?: [];
            $sezona_options = $wpdb->get_col("SELECT DISTINCT sezona_slug FROM {$table} WHERE sezona_slug <> '' ORDER BY sezona_slug ASC") ?: [];
            $kolo_options = $wpdb->get_col("SELECT DISTINCT kolo_slug FROM {$table} WHERE kolo_slug <> '' ORDER BY CAST(kolo_slug AS UNSIGNED) ASC, kolo_slug ASC") ?: [];

            $where = ['1=1'];
            $params = [];
            if ($f_liga !== '') {
                $where[] = 'm.liga_slug=%s';
                $params[] = $f_liga;
            }
            if ($f_sezona !== '') {
                $where[] = 'm.sezona_slug=%s';
                $params[] = $f_sezona;
            }
            if ($f_kolo !== '') {
                $where[] = 'm.kolo_slug=%s';
                $params[] = $f_kolo;
            }
            if ($f_club > 0) {
                $where[] = '(m.home_club_post_id=%d OR m.away_club_post_id=%d)';
                $params[] = $f_club;
                $params[] = $f_club;
            }
            if ($f_games === 'yes') {
                $where[] = 'COALESCE(gc.games_count,0) > 0';
            } elseif ($f_games === 'no') {
                $where[] = 'COALESCE(gc.games_count,0) = 0';
            }

            $order_sql = '';
            switch ($sort_by) {
                case 'partije':
                    $order_sql = " ORDER BY COALESCE(gc.games_count,0) {$sort_dir}, m.id {$sort_dir}";
                    break;
                case 'kolo':
                    $order_sql = " ORDER BY CAST(m.kolo_slug AS UNSIGNED) {$sort_dir}, m.kolo_slug {$sort_dir}, m.id {$sort_dir}";
                    break;
                case 'id':
                    $order_sql = " ORDER BY m.id {$sort_dir}";
                    break;
                case 'rezultat':
                    $order_sql = " ORDER BY (m.home_score + m.away_score) {$sort_dir}, m.id {$sort_dir}";
                    break;
                case 'liga':
                    $order_sql = " ORDER BY m.liga_slug {$sort_dir}, m.sezona_slug {$sort_dir}, CAST(m.kolo_slug AS UNSIGNED) {$sort_dir}, m.id {$sort_dir}";
                    break;
                case 'date':
                default:
                    $sort_by = 'date';
                    $order_sql = " ORDER BY m.match_date {$sort_dir}, m.id {$sort_dir}";
                    break;
            }

            $games_table = OpenTT_Unified_Core::db_table('games');
            $sql = "SELECT m.*, COALESCE(gc.games_count,0) AS games_count
                    FROM {$table} m
                    LEFT JOIN (
                        SELECT match_id, COUNT(*) AS games_count
                        FROM {$games_table}
                        GROUP BY match_id
                    ) gc ON gc.match_id = m.id
                    WHERE " . implode(' AND ', $where) . $order_sql . ' LIMIT 400';
            if (!empty($params)) {
                $sql = $wpdb->prepare($sql, $params);
            }
            $rows = $wpdb->get_results($sql) ?: [];
        }

        $clubs = get_posts([
            'post_type' => 'klub',
            'numberposts' => 800,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>Utakmice</h1>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-match')) . '">+ Dodaj utakmicu</a></p>';

        echo '<form method="get" class="opentt-panel" style="padding:12px;margin-bottom:12px;">';
        echo '<input type="hidden" name="page" value="stkb-unified-matches">';
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;">';

        echo '<label style="display:flex;flex-direction:column;gap:4px;">Liga';
        echo '<select name="liga_slug">';
        echo '<option value="">Sve</option>';
        foreach ($liga_options as $opt) {
            $opt = sanitize_title((string) $opt);
            echo '<option value="' . esc_attr($opt) . '"' . selected($f_liga, $opt, false) . '>' . esc_html(self::slug_to_title($opt)) . '</option>';
        }
        echo '</select></label>';

        echo '<label style="display:flex;flex-direction:column;gap:4px;">Sezona';
        echo '<select name="sezona_slug">';
        echo '<option value="">Sve</option>';
        foreach ($sezona_options as $opt) {
            $opt = sanitize_title((string) $opt);
            echo '<option value="' . esc_attr($opt) . '"' . selected($f_sezona, $opt, false) . '>' . esc_html(self::slug_to_title($opt)) . '</option>';
        }
        echo '</select></label>';

        echo '<label style="display:flex;flex-direction:column;gap:4px;">Kolo';
        echo '<select name="kolo_slug">';
        echo '<option value="">Sva</option>';
        foreach ($kolo_options as $opt) {
            $opt = sanitize_title((string) $opt);
            echo '<option value="' . esc_attr($opt) . '"' . selected($f_kolo, $opt, false) . '>' . esc_html(self::kolo_name_from_slug($opt)) . '</option>';
        }
        echo '</select></label>';

        echo '<label style="display:flex;flex-direction:column;gap:4px;">Klub';
        echo '<select name="club_id">';
        echo '<option value="0">Svi</option>';
        foreach ($clubs as $club) {
            $cid = intval($club->ID);
            echo '<option value="' . esc_attr((string) $cid) . '"' . selected($f_club, $cid, false) . '>' . esc_html((string) $club->post_title) . '</option>';
        }
        echo '</select></label>';

        echo '<label style="display:flex;flex-direction:column;gap:4px;">Partije';
        echo '<select name="games_status">';
        echo '<option value=""' . selected($f_games, '', false) . '>Sve</option>';
        echo '<option value="yes"' . selected($f_games, 'yes', false) . '>Unete</option>';
        echo '<option value="no"' . selected($f_games, 'no', false) . '>Nisu unete</option>';
        echo '</select></label>';

        echo '<label style="display:flex;flex-direction:column;gap:4px;">Sortiraj po';
        echo '<select name="sort_by">';
        echo '<option value="date"' . selected($sort_by, 'date', false) . '>Datumu</option>';
        echo '<option value="kolo"' . selected($sort_by, 'kolo', false) . '>Kolu</option>';
        echo '<option value="liga"' . selected($sort_by, 'liga', false) . '>Ligi</option>';
        echo '<option value="id"' . selected($sort_by, 'id', false) . '>ID-u</option>';
        echo '<option value="rezultat"' . selected($sort_by, 'rezultat', false) . '>Ukupnom rezultatu</option>';
        echo '<option value="partije"' . selected($sort_by, 'partije', false) . '>Statusu partija</option>';
        echo '</select></label>';

        echo '<label style="display:flex;flex-direction:column;gap:4px;">Smer';
        echo '<select name="sort_dir">';
        echo '<option value="DESC"' . selected($sort_dir, 'DESC', false) . '>DESC</option>';
        echo '<option value="ASC"' . selected($sort_dir, 'ASC', false) . '>ASC</option>';
        echo '</select></label>';

        echo '<button type="submit" class="button button-primary">Primeni</button>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-matches')) . '">Reset</a>';
        echo '</div>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Obrisati izabrane utakmice i povezane partije/setove?\')">';
        wp_nonce_field('opentt_unified_delete_matches_bulk');
        echo '<input type="hidden" name="action" value="opentt_unified_delete_matches_bulk">';
        foreach ([
            'liga_slug' => $f_liga,
            'sezona_slug' => $f_sezona,
            'kolo_slug' => $f_kolo,
            'club_id' => $f_club > 0 ? (string) $f_club : '',
            'games_status' => $f_games,
            'sort_by' => $sort_by,
            'sort_dir' => $sort_dir,
        ] as $k => $v) {
            if ((string) $v === '') {
                continue;
            }
            echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string) $v) . '">';
        }
        echo '<p style="margin:0 0 12px 0;"><button type="submit" class="button button-link-delete">Obriši izabrane</button></p>';

        if (!$rows) {
            echo '<p>Nema unetih utakmica.</p></form></div>';
            return;
        }

        echo '<p class="opentt-mobile-scroll-hint">Na telefonu prevuci tabelu levo/desno za prikaz svih kolona.</p>';
        echo '<div class="opentt-table-scroll">';
        echo '<table id="opentt-matches-table" class="widefat striped opentt-live-search-table"><thead><tr><th style="width:32px;"><input type="checkbox" id="opentt-matches-check-all" aria-label="Izaberi sve utakmice"></th><th>Featured</th><th>LIVE</th><th>Liga</th><th>Sezona</th><th>Kolo</th><th>Utakmica</th><th>Rezultat</th><th>Partije</th><th>Datum</th><th>Akcije</th></tr></thead><tbody>';
        foreach ($rows as $m) {
            $home = get_the_title((int) $m->home_club_post_id);
            $away = get_the_title((int) $m->away_club_post_id);
            $edit_url = admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . (int) $m->id);
            $front_url = self::match_permalink($m);
            $toggle_featured_url = wp_nonce_url(
                admin_url('admin-post.php?action=opentt_unified_toggle_featured_match&id=' . (int) $m->id),
                'opentt_unified_toggle_featured_match_' . (int) $m->id
            );
            $toggle_live_url = wp_nonce_url(
                admin_url('admin-post.php?action=opentt_unified_toggle_live_match&id=' . (int) $m->id),
                'opentt_unified_toggle_live_match_' . (int) $m->id
            );
            $del_url = wp_nonce_url(
                admin_url('admin-post.php?action=opentt_unified_delete_match&id=' . (int) $m->id),
                'opentt_unified_delete_match_' . (int) $m->id
            );
            $is_featured = (int) ($m->featured ?? 0) === 1;
            $is_live = (int) ($m->live ?? 0) === 1;

            echo '<tr>';
            echo '<td><input type="checkbox" class="opentt-match-bulk-checkbox" name="match_ids[]" value="' . intval($m->id) . '" aria-label="Izaberi utakmicu ID ' . intval($m->id) . '"></td>';
            echo '<td>' . ($is_featured ? '★' : '—') . '</td>';
            echo '<td>' . ($is_live ? '<span class="opentt-live-badge">LIVE</span>' : '—') . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<td>' . esc_html((string) $m->liga_slug) . '</td>';
            echo '<td>' . esc_html((string) $m->sezona_slug) . '</td>';
            echo '<td>' . esc_html((string) $m->kolo_slug) . '</td>';
            echo '<td>' . esc_html((string) $home . ' — ' . (string) $away) . '</td>';
            echo '<td>' . esc_html((string) $m->home_score . ' : ' . (string) $m->away_score) . '</td>';
            $games_count = isset($m->games_count) ? intval($m->games_count) : 0;
            echo '<td><strong>' . ($games_count > 0 ? '✓' : '✗') . '</strong> <span style="opacity:.75;">(' . esc_html((string) $games_count) . ')</span></td>';
            echo '<td>' . esc_html(self::display_match_date((string) $m->match_date)) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url($edit_url) . '">Uredi</a> ';
            echo '<a class="button button-small" href="' . esc_url($toggle_featured_url) . '">' . esc_html($is_featured ? 'Unfeature' : 'Feature') . '</a> ';
            echo '<a class="button button-small" href="' . esc_url($toggle_live_url) . '">' . esc_html($is_live ? 'Unset LIVE' : 'Set LIVE') . '</a> ';
            echo '<a class="button button-small" href="' . esc_url($front_url) . '" target="_blank" rel="noopener">Frontend</a> ';
            echo '<a class="button button-small button-link-delete" href="' . esc_url($del_url) . '" onclick="return confirm(\'Obrisati utakmicu i partije/setove?\')">Obriši</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></form></div>';
    }

    public static function render_live_page()
    {
        self::require_cap();
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');
        $games_table = OpenTT_Unified_Core::db_table('games');

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>Uživo</h1>';
        echo '<p class="description">U LIVE modu su utakmice koje su ručno označene kao LIVE u listi utakmica ili u formi za uređivanje utakmice.</p>';

        if (!self::table_exists($table) || !self::table_exists($games_table)) {
            echo '<p>Nema dostupnih tabela za prikaz.</p></div>';
            return;
        }
        self::ensure_matches_live_column($table);

        $rows = $wpdb->get_results(
            "SELECT m.*, COALESCE(gc.games_count,0) AS games_count
             FROM {$table} m
             LEFT JOIN (
                SELECT match_id, COUNT(*) AS games_count
                FROM {$games_table}
                GROUP BY match_id
             ) gc ON gc.match_id = m.id
             WHERE m.live = 1
             ORDER BY m.match_date ASC, m.id ASC
             LIMIT 400"
        ) ?: [];

        if (empty($rows)) {
            echo '<p>Trenutno nema utakmica u LIVE modu.</p></div>';
            return;
        }

        echo '<p class="opentt-mobile-scroll-hint">Na telefonu prevuci tabelu levo/desno za prikaz svih kolona.</p>';
        echo '<div class="opentt-table-scroll">';
        echo '<table class="widefat striped"><thead><tr><th>LIVE</th><th>Liga</th><th>Sezona</th><th>Kolo</th><th>Utakmica</th><th>Rezultat</th><th>Partije</th><th>Datum</th><th>Akcije</th></tr></thead><tbody>';
        foreach ($rows as $m) {
            $home = get_the_title((int) $m->home_club_post_id);
            $away = get_the_title((int) $m->away_club_post_id);
            $edit_url = admin_url('admin.php?page=stkb-unified-add-match&action=edit&id=' . (int) $m->id);
            $result_url = $edit_url . '#opentt-match-score-row';
            $games_url = $edit_url . '#opentt-games-section';
            $front_url = self::match_permalink($m);
            $finish_live_url = wp_nonce_url(
                admin_url('admin-post.php?action=opentt_unified_finish_live_match&id=' . (int) $m->id),
                'opentt_unified_finish_live_match_' . (int) $m->id
            );
            $games_count = isset($m->games_count) ? intval($m->games_count) : 0;

            echo '<tr>';
            echo '<td><span class="opentt-live-badge">LIVE</span></td>';
            echo '<td>' . esc_html((string) $m->liga_slug) . '</td>';
            echo '<td>' . esc_html((string) $m->sezona_slug) . '</td>';
            echo '<td>' . esc_html(self::kolo_name_from_slug((string) $m->kolo_slug)) . '</td>';
            echo '<td>' . esc_html((string) $home . ' — ' . (string) $away) . '</td>';
            echo '<td>' . esc_html((string) $m->home_score . ' : ' . (string) $m->away_score) . '</td>';
            echo '<td><strong>' . ($games_count > 0 ? '✓' : '✗') . '</strong> <span style="opacity:.75;">(' . esc_html((string) $games_count) . ')</span></td>';
            echo '<td>' . esc_html(self::display_match_date((string) $m->match_date)) . '</td>';
            echo '<td>';
            echo '<a class="button button-small" href="' . esc_url($result_url) . '">Rezultat</a> ';
            echo '<a class="button button-small" href="' . esc_url($games_url) . '">Partije</a> ';
            echo '<a class="button button-small" href="' . esc_url($front_url) . '" target="_blank" rel="noopener">Frontend</a> ';
            echo '<a class="button button-small" href="' . esc_url($finish_live_url) . '" onclick="return confirm(\'Završiti LIVE utakmicu?\')">Završi utakmicu</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';
    }

    public static function render_clubs_page()
    {
        self::require_cap();
        $search = isset($_GET['club_search']) ? sanitize_text_field((string) wp_unslash($_GET['club_search'])) : '';
        $query_args = [
            'post_type' => 'klub',
            'numberposts' => 500,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ];
        if ($search !== '') {
            $query_args['s'] = $search;
        }
        $rows = get_posts($query_args) ?: [];

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>Klubovi</h1>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0 0 12px 0;">';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-club')) . '">+ Dodaj klub</a>';
        echo '<form method="get" action="" class="opentt-list-search-form" style="display:flex;gap:8px;align-items:center;">';
        echo '<input type="hidden" name="page" value="stkb-unified-clubs">';
        echo '<input type="search" name="club_search" value="' . esc_attr($search) . '" placeholder="Pretraga klubova..." class="regular-text opentt-live-search-input" data-opentt-live-target="opentt-clubs-table" oninput="window.openttLiveSearchFilter&&window.openttLiveSearchFilter(this)" onkeyup="window.openttLiveSearchFilter&&window.openttLiveSearchFilter(this)">';
        echo '<button type="submit" class="button opentt-search-submit">Pretraži</button>';
        if ($search !== '') {
            echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-clubs')) . '">Reset</a>';
        }
        echo '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Obrisati izabrane klubove?\')">';
        wp_nonce_field('opentt_unified_delete_clubs_bulk');
        echo '<input type="hidden" name="action" value="opentt_unified_delete_clubs_bulk">';
        echo '<button type="submit" class="button button-link-delete">Obriši izabrane</button>';
        echo '</div>';
        if (!$rows) {
            echo '<p>' . ($search !== '' ? 'Nema rezultata za zadatu pretragu klubova.' : 'Nema unetih klubova.') . '</p></form></div>';
            return;
        }
        if ($search !== '') {
            echo '<input type="hidden" name="club_search" value="' . esc_attr($search) . '">';
        }

        echo '<p class="opentt-mobile-scroll-hint">Na telefonu prevuci tabelu levo/desno za prikaz svih kolona.</p>';
        echo '<div class="opentt-table-scroll">';
        echo '<table id="opentt-clubs-table" class="widefat striped opentt-live-search-table"><thead><tr><th style="width:32px;"><input type="checkbox" id="opentt-clubs-check-all" aria-label="Izaberi sve klubove"></th><th>Klub</th><th>Opština</th><th>Grad</th><th>Kontakt</th><th>Akcije</th></tr></thead><tbody>';
        foreach ($rows as $c) {
            $edit_url = admin_url('admin.php?page=stkb-unified-add-club&action=edit&id=' . (int) $c->ID);
            $front_url = get_permalink((int) $c->ID) ?: '';
            $del_url = wp_nonce_url(
                admin_url('admin-post.php?action=opentt_unified_delete_club&id=' . (int) $c->ID),
                'opentt_unified_delete_club_' . (int) $c->ID
            );
            $opstina = (string) get_post_meta($c->ID, 'opstina', true);
            echo '<tr>';
            echo '<td><input type="checkbox" class="opentt-club-bulk-checkbox" name="club_ids[]" value="' . (int) $c->ID . '" aria-label="Izaberi klub ' . esc_attr((string) $c->post_title) . '"></td>';
            echo '<td>' . esc_html((string) $c->post_title) . '</td>';
            echo '<td>' . esc_html($opstina) . '</td>';
            echo '<td>' . esc_html((string) get_post_meta($c->ID, 'grad', true)) . '</td>';
            echo '<td>' . esc_html((string) get_post_meta($c->ID, 'kontakt', true)) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url($edit_url) . '">Uredi</a> ';
            if ($front_url) {
                echo '<a class="button button-small" href="' . esc_url($front_url) . '" target="_blank" rel="noopener">Frontend</a> ';
            }
            echo '<a class="button button-small button-link-delete" href="' . esc_url($del_url) . '" onclick="return confirm(\'Obrisati klub?\')">Obriši</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></form></div>';
    }

    public static function render_players_page()
    {
        self::require_cap();
        global $wpdb;
        $search = isset($_GET['player_search']) ? sanitize_text_field((string) wp_unslash($_GET['player_search'])) : '';
        $f_club = isset($_GET['club_id']) ? intval($_GET['club_id']) : 0;
        $f_liga = isset($_GET['liga_slug']) ? sanitize_title((string) wp_unslash($_GET['liga_slug'])) : '';
        $sort_by = isset($_GET['sort_by']) ? sanitize_key((string) wp_unslash($_GET['sort_by'])) : 'player';
        $sort_dir = isset($_GET['sort_dir']) ? strtoupper(sanitize_key((string) wp_unslash($_GET['sort_dir']))) : 'ASC';
        if (!in_array($sort_by, ['player', 'club', 'league'], true)) {
            $sort_by = 'player';
        }
        if (!in_array($sort_dir, ['ASC', 'DESC'], true)) {
            $sort_dir = 'ASC';
        }

        $matches_table = OpenTT_Unified_Core::db_table('matches');
        $liga_options = [];
        $club_leagues = [];
        if (self::table_exists($matches_table)) {
            $liga_options = $wpdb->get_col("SELECT DISTINCT liga_slug FROM {$matches_table} WHERE liga_slug <> '' ORDER BY liga_slug ASC") ?: [];
            $club_rows = $wpdb->get_results(
                "SELECT DISTINCT liga_slug, home_club_post_id AS club_id FROM {$matches_table} WHERE liga_slug <> '' AND home_club_post_id > 0
                 UNION
                 SELECT DISTINCT liga_slug, away_club_post_id AS club_id FROM {$matches_table} WHERE liga_slug <> '' AND away_club_post_id > 0"
            ) ?: [];
            foreach ($club_rows as $cr) {
                $club_id = isset($cr->club_id) ? intval($cr->club_id) : 0;
                $liga_slug = isset($cr->liga_slug) ? sanitize_title((string) $cr->liga_slug) : '';
                if ($club_id <= 0 || $liga_slug === '') {
                    continue;
                }
                if (!isset($club_leagues[$club_id])) {
                    $club_leagues[$club_id] = [];
                }
                $club_leagues[$club_id][$liga_slug] = true;
            }
        }

        $clubs = get_posts([
            'post_type' => 'klub',
            'numberposts' => 800,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];

        $query_args = [
            'post_type' => 'igrac',
            'numberposts' => 700,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ];
        if ($search !== '') {
            $query_args['s'] = $search;
        }
        if ($f_club > 0) {
            $query_args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => 'povezani_klub',
                    'value' => $f_club,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
                [
                    'key' => 'klub_igraca',
                    'value' => $f_club,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
            ];
        }
        $rows = get_posts($query_args) ?: [];
        if ($f_liga !== '') {
            $rows = array_values(array_filter($rows, function ($p) use ($club_leagues, $f_liga) {
                $club_id = self::get_player_club_id((int) $p->ID);
                if ($club_id <= 0 || empty($club_leagues[$club_id])) {
                    return false;
                }
                return !empty($club_leagues[$club_id][$f_liga]);
            }));
        }
        $sort_meta = [];
        foreach ($rows as $p) {
            $pid = (int) $p->ID;
            $club_id = self::get_player_club_id($pid);
            $club_name = $club_id > 0 ? (string) get_the_title($club_id) : '';
            $league_name = '';
            if ($club_id > 0 && !empty($club_leagues[$club_id])) {
                $slugs = array_keys((array) $club_leagues[$club_id]);
                sort($slugs, SORT_STRING);
                if (!empty($slugs[0])) {
                    $league_name = self::slug_to_title((string) $slugs[0]);
                }
            }
            $sort_meta[$pid] = [
                'player' => strtolower((string) $p->post_title),
                'club' => strtolower($club_name),
                'league' => strtolower($league_name),
            ];
        }
        usort($rows, function ($a, $b) use ($sort_meta, $sort_by, $sort_dir) {
            $a_id = (int) $a->ID;
            $b_id = (int) $b->ID;
            $a_key = (string) ($sort_meta[$a_id][$sort_by] ?? '');
            $b_key = (string) ($sort_meta[$b_id][$sort_by] ?? '');
            $cmp = strcmp($a_key, $b_key);
            if ($cmp === 0) {
                $a_fallback = (string) ($sort_meta[$a_id]['player'] ?? '');
                $b_fallback = (string) ($sort_meta[$b_id]['player'] ?? '');
                $cmp = strcmp($a_fallback, $b_fallback);
            }
            return $sort_dir === 'DESC' ? -$cmp : $cmp;
        });

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>Igrači</h1>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0 0 12px 0;">';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-player')) . '">+ Dodaj igrača</a>';
        echo '<form method="get" action="" class="opentt-list-search-form" style="display:flex;gap:8px;align-items:center;">';
        echo '<input type="hidden" name="page" value="stkb-unified-players">';
        echo '<input type="search" name="player_search" value="' . esc_attr($search) . '" placeholder="Pretraga igrača..." class="regular-text opentt-live-search-input" data-opentt-live-target="opentt-players-table" oninput="window.openttLiveSearchFilter&&window.openttLiveSearchFilter(this)" onkeyup="window.openttLiveSearchFilter&&window.openttLiveSearchFilter(this)">';
        echo '<select name="club_id">';
        echo '<option value="0">Svi klubovi</option>';
        foreach ($clubs as $club) {
            $cid = intval($club->ID);
            echo '<option value="' . esc_attr((string) $cid) . '"' . selected($f_club, $cid, false) . '>' . esc_html((string) $club->post_title) . '</option>';
        }
        echo '</select>';
        echo '<select name="liga_slug">';
        echo '<option value="">Sve lige</option>';
        foreach ($liga_options as $opt) {
            $opt = sanitize_title((string) $opt);
            echo '<option value="' . esc_attr($opt) . '"' . selected($f_liga, $opt, false) . '>' . esc_html(self::slug_to_title($opt)) . '</option>';
        }
        echo '</select>';
        echo '<select name="sort_by">';
        echo '<option value="player"' . selected($sort_by, 'player', false) . '>Sort: Igrač</option>';
        echo '<option value="club"' . selected($sort_by, 'club', false) . '>Sort: Klub</option>';
        echo '<option value="league"' . selected($sort_by, 'league', false) . '>Sort: Liga</option>';
        echo '</select>';
        echo '<select name="sort_dir">';
        echo '<option value="ASC"' . selected($sort_dir, 'ASC', false) . '>A-Z</option>';
        echo '<option value="DESC"' . selected($sort_dir, 'DESC', false) . '>Z-A</option>';
        echo '</select>';
        echo '<button type="submit" class="button opentt-search-submit">Primeni</button>';
        if ($search !== '' || $f_club > 0 || $f_liga !== '' || $sort_by !== 'player' || $sort_dir !== 'ASC') {
            echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-players')) . '">Reset</a>';
        }
        echo '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Obrisati izabrane igrače?\')">';
        wp_nonce_field('opentt_unified_delete_players_bulk');
        echo '<input type="hidden" name="action" value="opentt_unified_delete_players_bulk">';
        echo '<button type="submit" class="button button-link-delete">Obriši izabrane</button>';
        echo '</div>';
        if (!$rows) {
            echo '<p>' . ($search !== '' ? 'Nema rezultata za zadatu pretragu igrača.' : 'Nema unetih igrača.') . '</p></div>';
            return;
        }
        echo '<input type="hidden" name="return_page" value="stkb-unified-players">';
        if ($search !== '') {
            echo '<input type="hidden" name="player_search" value="' . esc_attr($search) . '">';
        }
        if ($f_club > 0) {
            echo '<input type="hidden" name="club_id" value="' . esc_attr((string) $f_club) . '">';
        }
        if ($f_liga !== '') {
            echo '<input type="hidden" name="liga_slug" value="' . esc_attr($f_liga) . '">';
        }
        if ($sort_by !== 'player') {
            echo '<input type="hidden" name="sort_by" value="' . esc_attr($sort_by) . '">';
        }
        if ($sort_dir !== 'ASC') {
            echo '<input type="hidden" name="sort_dir" value="' . esc_attr($sort_dir) . '">';
        }

        echo '<p class="opentt-mobile-scroll-hint">Na telefonu prevuci tabelu levo/desno za prikaz svih kolona.</p>';
        echo '<div class="opentt-table-scroll">';
        echo '<table id="opentt-players-table" class="widefat striped opentt-live-search-table"><thead><tr><th style="width:32px;"><input type="checkbox" id="opentt-players-check-all" aria-label="Izaberi sve igrače"></th><th>Igrač</th><th>Klub</th><th>Datum rođenja</th><th>Akcije</th></tr></thead><tbody>';
        foreach ($rows as $p) {
            $club_id = self::get_player_club_id((int) $p->ID);
            $edit_url = admin_url('admin.php?page=stkb-unified-add-player&action=edit&id=' . (int) $p->ID);
            $front_url = get_permalink((int) $p->ID) ?: '';
            $del_url = wp_nonce_url(
                admin_url('admin-post.php?action=opentt_unified_delete_player&id=' . (int) $p->ID),
                'opentt_unified_delete_player_' . (int) $p->ID
            );
            echo '<tr>';
            echo '<td><input type="checkbox" class="opentt-player-bulk-checkbox" name="player_ids[]" value="' . (int) $p->ID . '" aria-label="Izaberi igrača ' . esc_attr((string) $p->post_title) . '"></td>';
            echo '<td>' . esc_html((string) $p->post_title) . '</td>';
            echo '<td>' . esc_html($club_id ? (string) get_the_title($club_id) : '') . '</td>';
            echo '<td>' . esc_html((string) get_post_meta($p->ID, 'datum_rodjenja', true)) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url($edit_url) . '">Uredi</a> ';
            if ($front_url) {
                echo '<a class="button button-small" href="' . esc_url($front_url) . '" target="_blank" rel="noopener">Frontend</a> ';
            }
            echo '<a class="button button-small button-link-delete" href="' . esc_url($del_url) . '" onclick="return confirm(\'Obrisati igrača?\')">Obriši</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></form></div>';
    }

    public static function render_leagues_page()
    {
        self::require_cap();
        $rows = get_posts([
            'post_type' => 'liga',
            'numberposts' => 400,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>Lige</h1><p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-league')) . '">+ Dodaj ligu</a></p>';

        if (!$rows) {
            echo '<p>Nema unetih liga.</p></div>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Liga</th><th>Slug</th><th>Akcije</th></tr></thead><tbody>';
        foreach ($rows as $t) {
            $edit_url = admin_url('admin.php?page=stkb-unified-add-league&action=edit&id=' . (int) $t->ID);
            $front_url = get_permalink((int) $t->ID) ?: '';
            $del_url = wp_nonce_url(
                admin_url('admin-post.php?action=opentt_unified_delete_league&id=' . (int) $t->ID),
                'opentt_unified_delete_league_' . (int) $t->ID
            );
            echo '<tr><td>' . intval($t->ID) . '</td><td>' . esc_html((string) $t->post_title) . '</td><td><code>' . esc_html((string) $t->post_name) . '</code></td><td>';
            echo '<a class="button button-small" href="' . esc_url($edit_url) . '">Uredi</a> ';
            if ($front_url) {
                echo '<a class="button button-small" href="' . esc_url($front_url) . '" target="_blank" rel="noopener">Frontend</a> ';
            }
            echo '<a class="button button-small button-link-delete" href="' . esc_url($del_url) . '" onclick="return confirm(\'Obrisati ligu?\')">Obriši</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function render_seasons_page()
    {
        self::require_cap();
        $rows = get_posts([
            'post_type' => 'sezona',
            'numberposts' => 500,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>Sezone</h1><p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-season')) . '">+ Dodaj sezonu</a></p>';
        if (!$rows) {
            echo '<p>Nema unetih sezona.</p></div>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Sezona</th><th>Slug</th><th>Akcije</th></tr></thead><tbody>';
        foreach ($rows as $t) {
            $edit_url = admin_url('admin.php?page=stkb-unified-add-season&action=edit&id=' . (int) $t->ID);
            $del_url = wp_nonce_url(
                admin_url('admin-post.php?action=opentt_unified_delete_season&id=' . (int) $t->ID),
                'opentt_unified_delete_season_' . (int) $t->ID
            );

            echo '<tr><td>' . intval($t->ID) . '</td><td>' . esc_html((string) $t->post_title) . '</td><td><code>' . esc_html((string) $t->post_name) . '</code></td><td>';
            echo '<a class="button button-small" href="' . esc_url($edit_url) . '">Uredi</a> ';
            echo '<a class="button button-small button-link-delete" href="' . esc_url($del_url) . '" onclick="return confirm(\'Obrisati sezonu?\')">Obriši</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function admin_menu_reorder()
    {
        global $submenu;
        if (empty($submenu['stkb-unified'])) {
            return;
        }

        $items = $submenu['stkb-unified'];
        $dashboard = null;
        $rest = [];
        foreach ($items as $item) {
            if (isset($item[2]) && $item[2] === 'stkb-unified') {
                $dashboard = $item;
            } else {
                $rest[] = $item;
            }
        }
        if ($dashboard) {
            $submenu['stkb-unified'] = array_merge([$dashboard], $rest);
        }
    }

    public static function render_admin_styles()
    {
        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if (strpos($page, 'stkb-unified') !== 0) {
            return;
        }
        $css_rel = 'assets/css/admin.css';
        $css_path = self::$plugin_dir . $css_rel;
        if (is_readable($css_path)) {
            wp_enqueue_style(
                'stkb-unified-admin',
                plugins_url($css_rel, self::$plugin_file),
                [],
                filemtime($css_path)
            );
        }

        $js_rel = 'assets/js/admin.js';
        $js_path = self::$plugin_dir . $js_rel;
        if (is_readable($js_path)) {
            wp_enqueue_script(
                'stkb-unified-admin',
                plugins_url($js_rel, self::$plugin_file),
                ['jquery'],
                filemtime($js_path),
                true
            );
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        if ($page === 'stkb-unified-settings' || $page === 'stkb-unified-customize') {
            $cm_settings = wp_enqueue_code_editor(['type' => 'text/css']);
            if (!empty($cm_settings)) {
                wp_localize_script('stkb-unified-admin', 'openttCodeEditorSettings', $cm_settings);
            }
        }
    }

    public static function render_admin_menu_icon_style()
    {
        echo '<style id="opentt-admin-menu-icon-size">.folded #adminmenu #toplevel_page_stkb-unified .wp-menu-image img,#adminmenu #toplevel_page_stkb-unified .wp-menu-image img{width:20px;height:20px;padding:7px 0 0 0;object-fit:contain;}</style>';
    }

    public static function render_admin_live_search_head_script()
    {
        echo <<<'HTML'
<script id="opentt-live-search-fallback">
(function(){
  function applyForInput(input){
    if (!input) { return; }
    var targetId = input.getAttribute('data-opentt-live-target');
    if (!targetId) { return; }
    var table = document.getElementById(targetId);
    if (!table) { return; }
    var tbody = table.querySelector('tbody');
    if (!tbody) { return; }
    var q = String(input.value || '').toLowerCase().trim();
    tbody.querySelectorAll('tr').forEach(function(row){
      var txt = String(row.textContent || '').toLowerCase();
      var hit = !q || txt.indexOf(q) !== -1;
      row.style.display = hit ? '' : 'none';
      row.classList.toggle('opentt-live-hit', !!q && hit);
    });
  }

  window.openttLiveSearchFilter = applyForInput;

  document.addEventListener('input', function(e){
    var input = e.target && e.target.closest ? e.target.closest('.opentt-live-search-input') : null;
    if (input) { applyForInput(input); }
  });
  document.addEventListener('keyup', function(e){
    var input = e.target && e.target.closest ? e.target.closest('.opentt-live-search-input') : null;
    if (input) { applyForInput(input); }
  });
  document.addEventListener('search', function(e){
    var input = e.target && e.target.closest ? e.target.closest('.opentt-live-search-input') : null;
    if (input) { applyForInput(input); }
  });
})();
</script>
HTML;
    }

    private static function render_admin_topbar()
    {
        $logo_path = trailingslashit(self::$plugin_dir) . 'assets/img/admin-ui-logo.png';
        echo '<div class="opentt-admin-topbar">';
        echo '<div class="opentt-admin-brand">';
        if (is_readable($logo_path)) {
            $logo_url = plugins_url('assets/img/admin-ui-logo.png', self::$plugin_file);
            echo '<img class="opentt-dashboard-logo" src="' . esc_url($logo_url) . '" alt="OpenTT logo">';
        } else {
            echo '<strong>OpenTT</strong>';
        }
        echo '</div>';
        echo '<div class="opentt-admin-actions">';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified')) . '">Kontrolna tabla</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-matches')) . '">Utakmice</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-live')) . '">Uživo</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-clubs')) . '">Klubovi</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-players')) . '">Igrači</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-competitions')) . '">Takmičenja</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-transfer')) . '">Uvezi/Izvezi</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-customize')) . '">Prilagođavanje</a>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-settings')) . '">Podešavanja</a>';
        echo '</div>';
        echo '</div>';
    }

    public static function render_match_edit_page()
    {
        self::require_cap();
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');
        $games_table = OpenTT_Unified_Core::db_table('games');
        $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : 'new';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        $match = null;
        if ($action === 'edit' && $id > 0) {
            $match = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id));
            if (!$match) {
                echo '<div class="wrap"><h1>Utakmica</h1><p>Utakmica nije pronađena.</p></div>';
                return;
            }
        }
        $m_liga = $match ? (string) $match->liga_slug : '';
        $m_sezona = $match ? (string) $match->sezona_slug : '';
        $m_kolo = $match ? (string) $match->kolo_slug : '';
        $m_home = $match ? (int) $match->home_club_post_id : 0;
        $m_away = $match ? (int) $match->away_club_post_id : 0;
        $m_hs = $match ? (int) $match->home_score : 0;
        $m_as = $match ? (int) $match->away_score : 0;
        $m_featured = $match ? (int) ($match->featured ?? 0) : 0;
        $m_live = $match ? (int) ($match->live ?? 0) : 0;
        $m_location = $match ? trim((string) ($match->location ?? '')) : '';

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>' . ($match ? 'Uredi utakmicu' : 'Dodaj utakmicu') . '</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-wizard-form" data-opentt-steps="3">';
        wp_nonce_field('opentt_unified_save_match');
        echo '<input type="hidden" name="action" value="opentt_unified_save_match">';
        echo '<input type="hidden" name="id" value="' . esc_attr((string) ($match ? (int) $match->id : 0)) . '">';
        echo '<div class="opentt-wizard-steps"><span class="opentt-step-pill">1. Takmičenje</span><span class="opentt-step-pill">2. Ekipe i rezultat</span><span class="opentt-step-pill">3. Potvrda</span></div>';
        echo '<table class="form-table"><tbody>';
        echo '<tr data-opentt-step="1"><th>Takmičenje</th><td>' . self::competition_rules_dropdown_admin('competition_rule_id', self::competition_rule_id_by_slugs($m_liga, $m_sezona), true) . '<p class="description">U meniju <strong>Takmičenja</strong> dodaješ liga+sezona i pravila.</p></td></tr>';
        echo '<tr data-opentt-step="1"><th>Kolo slug</th><td><input type="text" class="regular-text" name="kolo_slug" value="' . esc_attr($m_kolo) . '" required><p class="description">Primer: <code>12-kolo</code>.</p></td></tr>';
        echo '<tr data-opentt-step="1"><th>Datum i vreme</th><td><input name="match_date" type="datetime-local" value="' . esc_attr($match && !empty($match->match_date) ? str_replace(' ', 'T', substr((string) $match->match_date, 0, 16)) : '') . '"></td></tr>';
        echo '<tr data-opentt-step="1"><th>Lokacija</th><td><input name="location" type="text" class="regular-text" value="' . esc_attr($m_location) . '" placeholder="Hala, sala ili adresa"><p class="description">Menjaj ovo polje samo ako se utakmica ne igra kod domaćina.</p></td></tr>';
        echo '<tr data-opentt-step="2"><th>Domaći klub</th><td>' . self::clubs_dropdown_admin('home_club_post_id', $m_home, true) . '</td></tr>';
        echo '<tr data-opentt-step="2"><th>Gostujući klub</th><td>' . self::clubs_dropdown_admin('away_club_post_id', $m_away, true) . '</td></tr>';
        echo '<tr id="opentt-match-score-row" data-opentt-step="2"><th>Rezultat</th><td><input name="home_score" type="number" min="0" max="7" value="' . esc_attr((string) $m_hs) . '" style="width:90px;"> : <input name="away_score" type="number" min="0" max="7" value="' . esc_attr((string) $m_as) . '" style="width:90px;"></td></tr>';
        echo '<tr data-opentt-step="2"><th>Featured match</th><td><label><input type="checkbox" name="featured" value="1" ' . checked($m_featured, 1, false) . '> Istakni ovu utakmicu</label></td></tr>';
        echo '<tr data-opentt-step="2"><th>LIVE match</th><td><label><input type="checkbox" name="live" value="1" ' . checked($m_live, 1, false) . '> Označi ovu utakmicu kao LIVE (ručno)</label></td></tr>';
        echo '<tr data-opentt-step="3"><th>Potvrda</th><td><p class="description">Proveri podatke i klikni na dugme za čuvanje.</p></td></tr>';
        echo '</tbody></table>';
        echo '<div class="opentt-wizard-nav">';
        echo '<button type="button" class="button opentt-wizard-prev">Nazad</button>';
        echo '<button type="button" class="button opentt-wizard-next">Dalje</button>';
        echo '<button type="submit" class="button button-primary opentt-wizard-submit">' . esc_html($match ? 'Sačuvaj izmene' : 'Dodaj utakmicu') . '</button>';
        echo '<span class="opentt-wizard-help"></span>';
        echo '</div>';
        echo '</form>';

        if ($match) {
            $sets_table = OpenTT_Unified_Core::db_table('sets');
            $games = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$games_table} WHERE match_id=%d ORDER BY order_no ASC, id ASC", (int) $match->id)) ?: [];
            $games_by_order = [];
            $sets_by_game = [];
            foreach ($games as $g_row) {
                $games_by_order[(int) $g_row->order_no] = $g_row;
                if ((int) $g_row->id > 0) {
                    $set_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$sets_table} WHERE game_id=%d ORDER BY set_no ASC, id ASC", (int) $g_row->id)) ?: [];
                    $tmp = [];
                    foreach ($set_rows as $sr) {
                        $tmp[(int) $sr->set_no] = $sr;
                    }
                    $sets_by_game[(int) $g_row->id] = $tmp;
                }
            }

            $max_games = max(0, min(7, (int) $match->home_score + (int) $match->away_score));
            if ($max_games <= 0) {
                $max_games = 7;
            }
            $current_games = count($games);
            $match_format = self::match_competition_format((string) $match->liga_slug, (string) $match->sezona_slug);
            $expected_doubles_order = ($match_format === 'format_b') ? 7 : 4;
            $all_players_index = self::all_players_admin_index();

            echo '<hr><h2 id="opentt-games-section">Partije (batch unos)</h2>';
            echo '<p class="description"><strong>Limit partija po rezultatu:</strong> ' . esc_html((string) $max_games) . ' (rezultat ' . esc_html((string) ((int) $match->home_score)) . ':' . esc_html((string) ((int) $match->away_score)) . '). Trenutno uneto: ' . esc_html((string) $current_games) . '.</p>';
            echo '<p class="description">Unesi sve partije i setove odjednom, pa klikni <strong>Sačuvaj sve partije</strong>. Prazna partija se briše (ako je ranije postojala).</p>';
            echo '<p class="description">Dubl partija je automatski određena pravilima takmičenja: <strong>#' . (int) $expected_doubles_order . '</strong>.</p>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-panel">';
            wp_nonce_field('opentt_unified_save_games_batch');
            echo '<input type="hidden" name="action" value="opentt_unified_save_games_batch">';
            echo '<input type="hidden" name="match_id" value="' . (int) $match->id . '">';

            for ($n = 1; $n <= $max_games; $n++) {
                $g = isset($games_by_order[$n]) ? $games_by_order[$n] : null;
                $g_id = $g ? (int) $g->id : 0;
                $is_doubles = ($n === $expected_doubles_order);
                $home_sets = $g ? (int) $g->home_sets : 0;
                $away_sets = $g ? (int) $g->away_sets : 0;
                $existing_sets = ($g_id > 0 && isset($sets_by_game[$g_id])) ? $sets_by_game[$g_id] : [];

                echo '<div class="opentt-games-batch-row">';
                echo '<h3>Partija #' . (int) $n . ($is_doubles ? ' (Dubl)' : '') . '</h3>';
                echo '<input type="hidden" name="games[' . (int) $n . '][game_id]" value="' . (int) $g_id . '">';
                echo '<input type="hidden" name="games[' . (int) $n . '][order_no]" value="' . (int) $n . '">';
                echo '<input type="hidden" name="games[' . (int) $n . '][is_doubles]" value="' . ($is_doubles ? '1' : '0') . '">';

                echo '<div class="opentt-games-batch-grid">';
                echo '<label>Domaći igrač';
                echo self::players_dropdown_admin('games[' . (int) $n . '][home_player_post_id]', $g ? (int) $g->home_player_post_id : 0, (int) $match->home_club_post_id, false);
                echo '</label>';
                echo '<label>Gost igrač';
                echo self::players_dropdown_admin('games[' . (int) $n . '][away_player_post_id]', $g ? (int) $g->away_player_post_id : 0, (int) $match->away_club_post_id, false);
                echo '</label>';
                echo '<label>Domaći setovi';
                echo '<input name="games[' . (int) $n . '][home_sets]" type="number" min="0" max="7" value="' . esc_attr((string) $home_sets) . '" style="width:90px;">';
                echo '</label>';
                echo '<label>Gost setovi';
                echo '<input name="games[' . (int) $n . '][away_sets]" type="number" min="0" max="7" value="' . esc_attr((string) $away_sets) . '" style="width:90px;">';
                echo '</label>';

                if ($is_doubles) {
                    echo '<label>Domaći igrač 2';
                    echo self::players_dropdown_admin('games[' . (int) $n . '][home_player2_post_id]', $g ? (int) $g->home_player2_post_id : 0, (int) $match->home_club_post_id, false);
                    echo '</label>';
                    echo '<label>Gost igrač 2';
                    echo self::players_dropdown_admin('games[' . (int) $n . '][away_player2_post_id]', $g ? (int) $g->away_player2_post_id : 0, (int) $match->away_club_post_id, false);
                    echo '</label>';
                } else {
                    echo '<div class="opentt-games-batch-note">Singl partija: drugi igrač nije potreban.</div>';
                }
                echo '</div>';

                echo '<div class="opentt-sets-batch-grid">';
                for ($s = 1; $s <= 5; $s++) {
                    $set_row = isset($existing_sets[$s]) ? $existing_sets[$s] : null;
                    $hp = $set_row ? (int) $set_row->home_points : '';
                    $ap = $set_row ? (int) $set_row->away_points : '';
                    echo '<label>Set ' . (int) $s . ' (D:G)';
                    echo '<span style="display:flex;gap:6px;align-items:center;">';
                    echo '<input name="games[' . (int) $n . '][sets][' . (int) $s . '][home_points]" type="number" min="0" max="30" value="' . esc_attr((string) $hp) . '" placeholder="11" style="width:80px;">';
                    echo '<span>:</span>';
                    echo '<input name="games[' . (int) $n . '][sets][' . (int) $s . '][away_points]" type="number" min="0" max="30" value="' . esc_attr((string) $ap) . '" placeholder="9" style="width:80px;">';
                    echo '</span>';
                    echo '</label>';
                }
                echo '</div>';
                echo '</div>';
            }

            submit_button('Sačuvaj sve partije', 'primary', 'submit', false);
            echo '</form>';

            echo '<div class="opentt-player-picker-modal" id="opentt-player-picker-modal" hidden>';
            echo '  <div class="opentt-player-picker-dialog" role="dialog" aria-modal="true" aria-label="Lista igrača">';
            echo '    <div class="opentt-player-picker-head">';
            echo '      <strong>Lista igrača</strong>';
            echo '      <button type="button" class="button-link opentt-player-picker-close" aria-label="Zatvori">×</button>';
            echo '    </div>';
            echo '    <input type="search" class="opentt-player-picker-search" placeholder="Pretraga igrača..." aria-label="Pretraga igrača">';
            echo '    <div class="opentt-player-picker-list" role="listbox">';
            if (!empty($all_players_index)) {
                foreach ($all_players_index as $p) {
                    $pid = isset($p['id']) ? (int) $p['id'] : 0;
                    $pname = isset($p['name']) ? (string) $p['name'] : '';
                    $pclub = isset($p['club']) ? (string) $p['club'] : '';
                    if ($pid <= 0 || $pname === '') {
                        continue;
                    }
                    echo '<button type="button" class="opentt-player-picker-item" data-player-id="' . (int) $pid . '" data-player-name="' . esc_attr($pname) . '">';
                    echo '<span class="name">' . esc_html($pname) . '</span>';
                    echo '<span class="club">' . esc_html($pclub !== '' ? $pclub : 'Bez kluba') . '</span>';
                    echo '</button>';
                }
            } else {
                echo '<div class="opentt-player-picker-empty">Nema igrača za prikaz.</div>';
            }
            echo '    </div>';
            echo '  </div>';
            echo '</div>';
        }
        echo '</div>';
    }

    public static function render_club_edit_page()
    {
        self::require_cap();
        wp_enqueue_media();
        $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : 'new';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $club = null;
        if ($action === 'edit' && $id > 0) {
            $club = get_post($id);
            if (!$club || $club->post_type !== 'klub') {
                echo '<div class="wrap"><h1>Klub</h1><p>Klub nije pronađen.</p></div>';
                return;
            }
        }
        $club_id = $club ? (int) $club->ID : 0;
        $club_title = $club ? (string) $club->post_title : '';
        $club_content = $club ? (string) $club->post_content : '';
        $thumb_id = $club ? get_post_thumbnail_id($club->ID) : 0;
        $thumb_html = $thumb_id ? wp_get_attachment_image($thumb_id, 'medium') : '<div style="width:120px;height:120px;background:#f2f2f2;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;">Nema grba</div>';

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>' . ($club ? 'Uredi klub' : 'Dodaj klub') . '</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-wizard-form" data-opentt-steps="3">';
        wp_nonce_field('opentt_unified_save_club');
        echo '<input type="hidden" name="action" value="opentt_unified_save_club">';
        echo '<input type="hidden" name="id" value="' . esc_attr((string) $club_id) . '">';
        echo '<div class="opentt-wizard-steps"><span class="opentt-step-pill">1. Osnovno</span><span class="opentt-step-pill">2. Mediji</span><span class="opentt-step-pill">3. Kontakt i detalji</span></div>';
        echo '<table class="form-table"><tbody>';
        echo '<tr data-opentt-step="1"><th>Naziv kluba</th><td><input name="post_title" type="text" class="regular-text" value="' . esc_attr($club_title) . '" required></td></tr>';
        echo '<tr data-opentt-step="1"><th>Opis</th><td><textarea name="post_content" rows="6" class="large-text">' . esc_textarea($club_content) . '</textarea></td></tr>';
        echo '<tr data-opentt-step="2"><th>Grb</th><td><div id="opentt_club_thumb_preview">' . $thumb_html . '</div><input type="hidden" id="opentt_club_thumb_id" name="featured_image_id" value="' . esc_attr((string) $thumb_id) . '"><p><button type="button" class="button" id="opentt_club_thumb_btn">Izaberi grb</button> <button type="button" class="button" id="opentt_club_thumb_remove">Ukloni</button></p></td></tr>';
        $opstina_selected = (string) get_post_meta($club_id, 'opstina', true);
        if ($opstina_selected === '') {
            $opstina_selected = (string) get_post_meta($club_id, 'grad', true);
        }
        echo '<tr data-opentt-step="3"><th>Opština</th><td>' . self::municipality_dropdown_admin('opstina', $opstina_selected, false) . '</td></tr>';
        echo '<tr data-opentt-step="3"><th>Grad</th><td><input name="grad" type="text" class="regular-text" value="' . esc_attr((string) get_post_meta($club_id, 'grad', true)) . '"><p class="description">Opcionalno: mesto/grad kluba (ako želiš detaljnije od opštine).</p></td></tr>';
        echo '<tr data-opentt-step="3"><th>Kontakt</th><td><input name="kontakt" type="text" class="regular-text" placeholder="+381601234567" value="' . esc_attr((string) get_post_meta($club_id, 'kontakt', true)) . '"><p class="description">Primer formata: <code>+381601234567</code> ili <code>0601234567</code>.</p></td></tr>';
        echo '<tr data-opentt-step="3"><th>Email</th><td><input name="email" type="email" class="regular-text" value="' . esc_attr((string) get_post_meta($club_id, 'email', true)) . '"></td></tr>';
        echo '<tr data-opentt-step="3"><th>Zastupnik kluba</th><td><input name="zastupnik_kluba" type="text" class="regular-text" value="' . esc_attr((string) get_post_meta($club_id, 'zastupnik_kluba', true)) . '"></td></tr>';
        echo '<tr data-opentt-step="3"><th>Website kluba</th><td><input name="website_kluba" type="url" class="regular-text" placeholder="https://..." value="' . esc_attr((string) get_post_meta($club_id, 'website_kluba', true)) . '"></td></tr>';
        echo '<tr data-opentt-step="3"><th>Boja dresa</th><td><input name="boja_dresa" type="text" class="regular-text opentt-color-field" value="' . esc_attr((string) get_post_meta($club_id, 'boja_dresa', true)) . '" placeholder="#0b4db8"></td></tr>';
        echo '<tr data-opentt-step="3"><th>Loptice</th><td><input name="loptice" type="text" class="regular-text" value="' . esc_attr((string) get_post_meta($club_id, 'loptice', true)) . '"></td></tr>';
        echo '<tr data-opentt-step="3"><th>Adresa kluba</th><td><input name="adresa_kluba" type="text" class="regular-text" value="' . esc_attr((string) get_post_meta($club_id, 'adresa_kluba', true)) . '"></td></tr>';
        echo '<tr data-opentt-step="3"><th>Adresa sale</th><td><input name="adresa_sale" type="text" class="regular-text" value="' . esc_attr((string) get_post_meta($club_id, 'adresa_sale', true)) . '"></td></tr>';
        echo '<tr data-opentt-step="3"><th>Termin igranja</th><td><input name="termin_igranja" type="text" class="regular-text" placeholder="npr. Petak 20:00" value="' . esc_attr((string) get_post_meta($club_id, 'termin_igranja', true)) . '"></td></tr>';
        echo '</tbody></table>';
        echo '<div class="opentt-wizard-nav">';
        echo '<button type="button" class="button opentt-wizard-prev">Nazad</button>';
        echo '<button type="button" class="button opentt-wizard-next">Dalje</button>';
        echo '<button type="submit" class="button button-primary opentt-wizard-submit">' . esc_html($club ? 'Sačuvaj izmene' : 'Dodaj klub') . '</button>';
        echo '<span class="opentt-wizard-help"></span>';
        echo '</div>';
        echo '</form></div>';
        echo <<<'JS'
<script>
(function($){
    var frame;
    $('#opentt_club_thumb_btn').on('click', function(e){
        e.preventDefault();
        if (frame) { frame.open(); return; }
        frame = wp.media({ title: 'Izaberi grb', button: { text: 'Postavi grb' }, multiple: false });
        frame.on('select', function(){
            var att = frame.state().get('selection').first().toJSON();
            $('#opentt_club_thumb_id').val(att.id);
            $('#opentt_club_thumb_preview').html('<img src="' + att.url + '" style="max-width:180px;height:auto;" />');
        });
        frame.open();
    });
    $('#opentt_club_thumb_remove').on('click', function(){
        $('#opentt_club_thumb_id').val('');
        $('#opentt_club_thumb_preview').html('<div style="width:120px;height:120px;background:#f2f2f2;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;">Nema grba</div>');
    });
})(jQuery);
</script>
JS;
    }

    public static function render_player_edit_page()
    {
        self::require_cap();
        wp_enqueue_media();
        $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : 'new';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $player = null;
        if ($action === 'edit' && $id > 0) {
            $player = get_post($id);
            if (!$player || $player->post_type !== 'igrac') {
                echo '<div class="wrap"><h1>Igrač</h1><p>Igrač nije pronađen.</p></div>';
                return;
            }
        }
        $player_id = $player ? (int) $player->ID : 0;
        $player_title = $player ? (string) $player->post_title : '';
        $player_content = $player ? (string) $player->post_content : '';
        $thumb_id = $player ? get_post_thumbnail_id($player->ID) : 0;
        $thumb_html = $thumb_id ? wp_get_attachment_image($thumb_id, 'medium') : '<div style="width:120px;height:120px;background:#f2f2f2;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;">Nema slike</div>';

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>' . ($player ? 'Uredi igrača' : 'Dodaj igrača') . '</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-wizard-form" data-opentt-steps="3">';
        wp_nonce_field('opentt_unified_save_player');
        echo '<input type="hidden" name="action" value="opentt_unified_save_player">';
        echo '<input type="hidden" name="id" value="' . esc_attr((string) $player_id) . '">';
        echo '<div class="opentt-wizard-steps"><span class="opentt-step-pill">1. Osnovno</span><span class="opentt-step-pill">2. Klub i slika</span><span class="opentt-step-pill">3. Dodatno</span></div>';
        echo '<table class="form-table"><tbody>';
        echo '<tr data-opentt-step="1"><th>Ime i prezime</th><td><input name="post_title" type="text" class="regular-text" value="' . esc_attr($player_title) . '" required></td></tr>';
        echo '<tr data-opentt-step="1"><th>Biografija</th><td><textarea name="post_content" rows="6" class="large-text">' . esc_textarea($player_content) . '</textarea></td></tr>';
        echo '<tr data-opentt-step="2"><th>Slika</th><td><div id="opentt_player_thumb_preview">' . $thumb_html . '</div><input type="hidden" id="opentt_player_thumb_id" name="featured_image_id" value="' . esc_attr((string) $thumb_id) . '"><p><button type="button" class="button" id="opentt_player_thumb_btn">Izaberi sliku</button> <button type="button" class="button" id="opentt_player_thumb_remove">Ukloni</button></p></td></tr>';
        echo '<tr data-opentt-step="2"><th>Povezani klub</th><td>' . self::clubs_dropdown_admin('povezani_klub', self::get_player_club_id($player_id), false) . '</td></tr>';
        echo '<tr data-opentt-step="3"><th>Datum rođenja</th><td><input name="datum_rodjenja" type="date" value="' . esc_attr((string) get_post_meta($player_id, 'datum_rodjenja', true)) . '"></td></tr>';
        echo '<tr data-opentt-step="3"><th>Mesto rođenja</th><td><input name="mesto_rodjenja" type="text" class="regular-text" value="' . esc_attr((string) get_post_meta($player_id, 'mesto_rodjenja', true)) . '"></td></tr>';
        $player_country = (string) get_post_meta($player_id, 'drzavljanstvo', true);
        if ($player_country === '') {
            $player_country = 'RS';
        }
        echo '<tr data-opentt-step="3"><th>Državljanstvo</th><td>' . self::country_dropdown_admin('drzavljanstvo', $player_country, false) . '</td></tr>';
        echo '</tbody></table>';
        echo '<div class="opentt-wizard-nav">';
        echo '<button type="button" class="button opentt-wizard-prev">Nazad</button>';
        echo '<button type="button" class="button opentt-wizard-next">Dalje</button>';
        echo '<button type="submit" class="button button-primary opentt-wizard-submit">' . esc_html($player ? 'Sačuvaj izmene' : 'Dodaj igrača') . '</button>';
        echo '<span class="opentt-wizard-help"></span>';
        echo '</div>';
        echo '</form></div>';
        echo <<<'JS'
<script>
(function($){
    var frame;
    $('#opentt_player_thumb_btn').on('click', function(e){
        e.preventDefault();
        if (frame) { frame.open(); return; }
        frame = wp.media({ title: 'Izaberi sliku', button: { text: 'Postavi sliku' }, multiple: false });
        frame.on('select', function(){
            var att = frame.state().get('selection').first().toJSON();
            $('#opentt_player_thumb_id').val(att.id);
            $('#opentt_player_thumb_preview').html('<img src="' + att.url + '" style="max-width:180px;height:auto;" />');
        });
        frame.open();
    });
    $('#opentt_player_thumb_remove').on('click', function(){
        $('#opentt_player_thumb_id').val('');
        $('#opentt_player_thumb_preview').html('<div style="width:120px;height:120px;background:#f2f2f2;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;">Nema slike</div>');
    });
})(jQuery);
</script>
JS;
    }

    public static function render_league_edit_page()
    {
        self::require_cap();
        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : 'new';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $league = null;
        if ($action === 'edit' && $id > 0) {
            $league = get_post($id);
            if (!$league || $league->post_type !== 'liga') {
                echo '<h1>Liga</h1><p>Liga nije pronađena.</p></div>';
                return;
            }
        }

        $title = $league ? (string) $league->post_title : '';
        $content = $league ? (string) $league->post_content : '';

        echo '<h1>' . ($league ? 'Uredi ligu' : 'Dodaj ligu') . '</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-wizard-form" data-opentt-steps="2">';
        wp_nonce_field('opentt_unified_save_league');
        echo '<input type="hidden" name="action" value="opentt_unified_save_league">';
        echo '<input type="hidden" name="id" value="' . esc_attr((string) ($league ? (int) $league->ID : 0)) . '">';
        echo '<div class="opentt-wizard-steps"><span class="opentt-step-pill">1. Osnovno</span><span class="opentt-step-pill">2. Potvrda</span></div>';
        echo '<table class="form-table"><tbody>';
        echo '<tr data-opentt-step="1"><th>Naziv lige</th><td><input name="post_title" type="text" class="regular-text" value="' . esc_attr($title) . '" required></td></tr>';
        echo '<tr data-opentt-step="1"><th>Opis</th><td><textarea name="post_content" rows="5" class="large-text">' . esc_textarea($content) . '</textarea></td></tr>';
        echo '<tr data-opentt-step="2"><th>Potvrda</th><td><p class="description">Sačuvaj ligu. Slug će se automatski formirati iz naziva.</p></td></tr>';
        echo '</tbody></table>';
        echo '<div class="opentt-wizard-nav">';
        echo '<button type="button" class="button opentt-wizard-prev">Nazad</button>';
        echo '<button type="button" class="button opentt-wizard-next">Dalje</button>';
        echo '<button type="submit" class="button button-primary opentt-wizard-submit">' . esc_html($league ? 'Sačuvaj izmene' : 'Dodaj ligu') . '</button>';
        echo '<span class="opentt-wizard-help"></span>';
        echo '</div>';
        echo '</form></div>';
    }

    public static function render_season_edit_page()
    {
        self::require_cap();
        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : 'new';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $season = null;
        if ($action === 'edit' && $id > 0) {
            $season = get_post($id);
            if (!$season || $season->post_type !== 'sezona') {
                echo '<h1>Sezona</h1><p>Sezona nije pronađena.</p></div>';
                return;
            }
        }

        $title = $season ? (string) $season->post_title : '';
        echo '<h1>' . ($season ? 'Uredi sezonu' : 'Dodaj sezonu') . '</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-wizard-form" data-opentt-steps="2">';
        wp_nonce_field('opentt_unified_save_season');
        echo '<input type="hidden" name="action" value="opentt_unified_save_season">';
        echo '<input type="hidden" name="id" value="' . esc_attr((string) ($season ? (int) $season->ID : 0)) . '">';
        echo '<div class="opentt-wizard-steps"><span class="opentt-step-pill">1. Osnovno</span><span class="opentt-step-pill">2. Potvrda</span></div>';
        echo '<table class="form-table"><tbody>';
        echo '<tr data-opentt-step="1"><th>Naziv sezone</th><td><input name="post_title" type="text" class="regular-text" value="' . esc_attr($title) . '" required><p class="description">Npr. 2025-26</p></td></tr>';
        echo '<tr data-opentt-step="2"><th>Potvrda</th><td><p class="description">Sačuvaj sezonu. Pravila se podešavaju u meniju <strong>Takmičenja</strong>.</p></td></tr>';
        echo '</tbody></table>';
        echo '<div class="opentt-wizard-nav">';
        echo '<button type="button" class="button opentt-wizard-prev">Nazad</button>';
        echo '<button type="button" class="button opentt-wizard-next">Dalje</button>';
        echo '<button type="submit" class="button button-primary opentt-wizard-submit">' . esc_html($season ? 'Sačuvaj izmene' : 'Dodaj sezonu') . '</button>';
        echo '<span class="opentt-wizard-help"></span>';
        echo '</div>';
        echo '</form></div>';
    }

    public static function render_migration_page()
    {
        self::require_cap();

        $counts = self::get_migration_counts();
        $state = self::get_migration_state();
        $report = self::get_validation_report();

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>Migracija: Legacy CPT -> STKB DB</h1>';

        if (isset($_GET['migrated'])) {
            $migrated_matches = isset($_GET['migrated_matches']) ? intval($_GET['migrated_matches']) : 0;
            $migrated_games = isset($_GET['migrated_games']) ? intval($_GET['migrated_games']) : 0;
            $migrated_sets = isset($_GET['migrated_sets']) ? intval($_GET['migrated_sets']) : 0;
            echo '<div class="notice notice-success"><p>Batch završen. Migrirano: utakmice ' . esc_html((string) $migrated_matches) . ', partije ' . esc_html((string) $migrated_games) . ', setovi ' . esc_html((string) $migrated_sets) . '.</p></div>';
        }

        if (isset($_GET['reset'])) {
            echo '<div class="notice notice-info"><p>Migracioni offset je resetovan.</p></div>';
        }
        if (isset($_GET['validated'])) {
            $is_valid = !empty($report['ok']);
            $cls = $is_valid ? 'notice-success' : 'notice-warning';
            $msg = $is_valid ? 'Validacija importa je prošla bez kritičnih grešaka.' : 'Validacija importa je našla probleme. Proveri izveštaj ispod pre migracije.';
            echo '<div class="notice ' . esc_attr($cls) . '"><p>' . esc_html($msg) . '</p></div>';
        }
        if (isset($_GET['repaired'])) {
            $fixed = isset($_GET['fixed']) ? intval($_GET['fixed']) : 0;
            echo '<div class="notice notice-info"><p>Auto-fix relacija završen. Ispravljeno reference: <strong>' . esc_html((string) $fixed) . '</strong>.</p></div>';
        }
        if (isset($_GET['cleaned_placeholders'])) {
            $cleaned = isset($_GET['cleaned']) ? intval($_GET['cleaned']) : 0;
            echo '<div class="notice notice-info"><p>Cleanup placeholder-a završen. Očišćeno: <strong>' . esc_html((string) $cleaned) . '</strong> referenci/postova.</p></div>';
        }

        echo '<table class="widefat striped" style="max-width:860px">';
        echo '<tbody>';
        echo '<tr><td>Legacy utakmice (utakmica CPT)</td><td><strong>' . esc_html((string) $counts['legacy_matches']) . '</strong></td></tr>';
        echo '<tr><td>Legacy partije (partija CPT)</td><td><strong>' . esc_html((string) $counts['legacy_games']) . '</strong></td></tr>';
        echo '<tr><td>Migrirane utakmice (DB)</td><td><strong>' . esc_html((string) $counts['db_matches']) . '</strong></td></tr>';
        echo '<tr><td>Migrirane partije (DB)</td><td><strong>' . esc_html((string) $counts['db_games']) . '</strong></td></tr>';
        echo '<tr><td>Migrirani setovi (DB)</td><td><strong>' . esc_html((string) $counts['db_sets']) . '</strong></td></tr>';
        echo '<tr><td>Trenutni offset</td><td><strong>' . esc_html((string) $state['offset']) . '</strong></td></tr>';
        echo '</tbody>';
        echo '</table>';

        echo '<p style="margin-top:14px">Migracija je idempotentna: isti legacy zapis se ažurira po `legacy_post_id`, ne pravi duplikate.</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:10px">';
        wp_nonce_field('opentt_unified_validate_import');
        echo '<input type="hidden" name="action" value="opentt_unified_validate_import">';
        submit_button('Proveri import (validacija)', 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:10px">';
        wp_nonce_field('opentt_unified_repair_relations');
        echo '<input type="hidden" name="action" value="opentt_unified_repair_relations">';
        submit_button('Auto-fix relacija', 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:10px">';
        wp_nonce_field('opentt_unified_cleanup_placeholders');
        echo '<input type="hidden" name="action" value="opentt_unified_cleanup_placeholders">';
        submit_button('Cleanup placeholder-a', 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:10px">';
        wp_nonce_field('opentt_unified_migrate_batch');
        echo '<input type="hidden" name="action" value="opentt_unified_migrate_batch">';
        echo '<input type="hidden" name="batch" value="100">';
        submit_button('Pokreni 1 batch (100 utakmica)', 'primary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block">';
        wp_nonce_field('opentt_unified_reset_migration');
        echo '<input type="hidden" name="action" value="opentt_unified_reset_migration">';
        submit_button('Reset offseta', 'secondary', 'submit', false);
        echo '</form>';

        if (!empty($report)) {
            echo '<hr style="margin:20px 0">';
            echo '<h2>Poslednji validation izveštaj</h2>';
            echo '<p><strong>Vreme:</strong> ' . esc_html((string) ($report['generated_at'] ?? '')) . '</p>';
            echo '<p><strong>Status:</strong> ' . (!empty($report['ok']) ? '<span style="color:#2e7d32">OK</span>' : '<span style="color:#b71c1c">PROBLEMI</span>') . '</p>';
            echo '<table class="widefat striped" style="max-width:860px"><tbody>';
            echo '<tr><td>Pregledano utakmica</td><td><strong>' . esc_html((string) intval($report['checked_matches'] ?? 0)) . '</strong></td></tr>';
            echo '<tr><td>Pregledano partija</td><td><strong>' . esc_html((string) intval($report['checked_games'] ?? 0)) . '</strong></td></tr>';
            echo '<tr><td>Broj warning/error stavki</td><td><strong>' . esc_html((string) intval($report['issue_count'] ?? 0)) . '</strong></td></tr>';
            echo '</tbody></table>';

            $issues = isset($report['issues']) && is_array($report['issues']) ? $report['issues'] : [];
            if (!empty($issues)) {
                echo '<h3 style="margin-top:16px">Problemi (prvih 120)</h3>';
                echo '<ol>';
                foreach ($issues as $issue) {
                    echo '<li><code>' . esc_html((string) ($issue['code'] ?? 'unknown')) . '</code> - ' . esc_html((string) ($issue['message'] ?? '')) . '</li>';
                }
                echo '</ol>';
            }
        }

        echo '</div>';
    }

    public static function render_competition_rules_page()
    {
        self::require_cap();
        $search = isset($_GET['competition_search']) ? sanitize_text_field((string) wp_unslash($_GET['competition_search'])) : '';
        $query_args = [
            'post_type' => 'pravilo_takmicenja',
            'numberposts' => 1000,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ];
        if ($search !== '') {
            $query_args['s'] = $search;
        }
        $rows = get_posts($query_args) ?: [];

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>Takmičenja</h1>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0 0 12px 0;">';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-competition')) . '">+ Dodaj takmičenje</a>';
        echo '<form method="get" action="" class="opentt-list-search-form" style="display:flex;gap:8px;align-items:center;">';
        echo '<input type="hidden" name="page" value="stkb-unified-competitions">';
        echo '<input type="search" name="competition_search" value="' . esc_attr($search) . '" placeholder="Pretraga takmičenja..." class="regular-text opentt-live-search-input" data-opentt-live-target="opentt-competitions-table" oninput="window.openttLiveSearchFilter&&window.openttLiveSearchFilter(this)" onkeyup="window.openttLiveSearchFilter&&window.openttLiveSearchFilter(this)">';
        echo '<button type="submit" class="button opentt-search-submit">Pretraži</button>';
        if ($search !== '') {
            echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-competitions')) . '">Reset</a>';
        }
        echo '</form>';
        echo '</div>';

        if (!$rows) {
            echo '<p>' . ($search !== '' ? 'Nema rezultata za zadatu pretragu takmičenja.' : 'Nema unetih takmičenja.') . '</p></div>';
            return;
        }

        echo '<p class="opentt-mobile-scroll-hint">Na telefonu prevuci tabelu levo/desno za prikaz svih kolona.</p>';
        echo '<div class="opentt-table-scroll">';
        echo '<table id="opentt-competitions-table" class="widefat striped opentt-live-search-table"><thead><tr><th>Liga</th><th>Sezona</th><th>Rang</th><th>Savez</th><th>Bodovanje</th><th>Format</th><th>Prom/isp</th><th>Akcije</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $liga_slug = (string) get_post_meta($r->ID, 'opentt_competition_league_slug', true);
            $sezona_slug = (string) get_post_meta($r->ID, 'opentt_competition_season_slug', true);
            $rank = (int) get_post_meta($r->ID, 'opentt_competition_rank', true);
            if ($rank < 1 || $rank > 5) {
                $rank = 3;
            }
            $savez_code = self::normalize_competition_federation((string) get_post_meta($r->ID, 'opentt_competition_federation', true));
            $savez = self::competition_federation_data($savez_code);
            $bodovanje = (string) get_post_meta($r->ID, 'opentt_competition_scoring_type', true);
            if ($bodovanje === '') {
                $bodovanje = '2-1';
            }
            $format = (string) get_post_meta($r->ID, 'opentt_competition_match_format', true);
            if ($format === '') {
                $format = 'format_a';
            }
            $promo = (int) get_post_meta($r->ID, 'opentt_competition_promotion_slots', true);
            $promo_baraz = (int) get_post_meta($r->ID, 'opentt_competition_promotion_playoff_slots', true);
            $releg = (int) get_post_meta($r->ID, 'opentt_competition_relegation_slots', true);
            $releg_razigravanje = (int) get_post_meta($r->ID, 'opentt_competition_relegation_playoff_slots', true);
            $edit_url = admin_url('admin.php?page=stkb-unified-add-competition&action=edit&id=' . (int) $r->ID);
            $del_url = wp_nonce_url(
                admin_url('admin-post.php?action=opentt_unified_delete_competition_rule&id=' . (int) $r->ID),
                'opentt_unified_delete_competition_rule_' . (int) $r->ID
            );

            echo '<tr><td><code>' . esc_html($liga_slug) . '</code></td><td><code>' . esc_html($sezona_slug) . '</code></td><td>' . esc_html((string) $rank) . '</td><td>';
            if (is_array($savez) && !empty($savez['url'])) {
                echo '<a href="' . esc_url((string) $savez['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html((string) $savez['label']) . '</a>';
            } else {
                echo '—';
            }
            $prom_isp_label = $promo . '+' . $promo_baraz . '/' . $releg . '+' . $releg_razigravanje;
            echo '</td><td>' . esc_html($bodovanje) . '</td><td>' . esc_html($format) . '</td><td>' . esc_html($prom_isp_label) . '</td><td>';
            echo '<a class="button button-small" href="' . esc_url($edit_url) . '">Uredi</a> ';
            echo '<a class="button button-small button-link-delete" href="' . esc_url($del_url) . '" onclick="return confirm(\'Obrisati takmičenje?\')">Obriši</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    public static function render_competition_rule_edit_page()
    {
        self::require_cap();
        wp_enqueue_media();
        $action = isset($_GET['action']) ? sanitize_key((string) $_GET['action']) : 'new';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $rule = null;
        if ($action === 'edit' && $id > 0) {
            $rule = get_post($id);
            if (!$rule || $rule->post_type !== 'pravilo_takmicenja') {
                echo '<div class="wrap opentt-admin">';
                self::render_admin_topbar();
                echo '<h1>Takmičenje</h1><p>Zapis nije pronađen.</p></div>';
                return;
            }
        }

        $liga_slug = $rule ? (string) get_post_meta($rule->ID, 'opentt_competition_league_slug', true) : '';
        $sezona_slug = $rule ? (string) get_post_meta($rule->ID, 'opentt_competition_season_slug', true) : '';
        $liga_name = self::slug_to_title($liga_slug);
        $sezona_name = self::slug_to_title($sezona_slug);
        $rank = $rule ? (int) get_post_meta($rule->ID, 'opentt_competition_rank', true) : 3;
        if ($rank < 1 || $rank > 5) {
            $rank = 3;
        }
        $promo = $rule ? (int) get_post_meta($rule->ID, 'opentt_competition_promotion_slots', true) : 0;
        $promo_baraz = $rule ? (int) get_post_meta($rule->ID, 'opentt_competition_promotion_playoff_slots', true) : 0;
        $releg = $rule ? (int) get_post_meta($rule->ID, 'opentt_competition_relegation_slots', true) : 0;
        $releg_razigravanje = $rule ? (int) get_post_meta($rule->ID, 'opentt_competition_relegation_playoff_slots', true) : 0;
        $scoring = $rule ? (string) get_post_meta($rule->ID, 'opentt_competition_scoring_type', true) : '2-1';
        $savez_code = $rule ? self::normalize_competition_federation((string) get_post_meta($rule->ID, 'opentt_competition_federation', true)) : 'STSS';
        if ($savez_code === '') {
            $savez_code = 'STSS';
        }
        $thumb_id = $rule ? (int) get_post_thumbnail_id($rule->ID) : 0;
        $thumb_html = $thumb_id ? wp_get_attachment_image($thumb_id, 'medium') : '<div style="width:120px;height:120px;background:#f2f2f2;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;">Nema grba takmičenja</div>';
        if ($scoring === '') {
            $scoring = '2-1';
        }
        $format = $rule ? (string) get_post_meta($rule->ID, 'opentt_competition_match_format', true) : 'format_a';
        if ($format === '') {
            $format = 'format_a';
        }

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>' . ($rule ? 'Uredi takmičenje' : 'Dodaj takmičenje') . '</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-wizard-form" data-opentt-steps="3">';
        wp_nonce_field('opentt_unified_save_competition_rule');
        echo '<input type="hidden" name="action" value="opentt_unified_save_competition_rule">';
        echo '<input type="hidden" name="id" value="' . esc_attr((string) ($rule ? (int) $rule->ID : 0)) . '">';
        echo '<div class="opentt-wizard-steps"><span class="opentt-step-pill">1. Kontekst</span><span class="opentt-step-pill">2. Pravila</span><span class="opentt-step-pill">3. Potvrda</span></div>';
        echo '<table class="form-table"><tbody>';
        echo '<tr data-opentt-step="1"><th>Naziv takmičenja</th><td><input type="text" name="league_name" class="regular-text" value="' . esc_attr($liga_name) . '" required><p class="description">Npr. Kvalitetna liga</p></td></tr>';
        echo '<tr data-opentt-step="1"><th>Sezona</th><td><input type="text" name="season_name" class="regular-text" value="' . esc_attr($sezona_name) . '" required><p class="description">Npr. 2025-26</p></td></tr>';
        echo '<tr data-opentt-step="1"><th>Savez</th><td><select name="savez" required>';
        foreach (self::competition_federation_options() as $code => $row) {
            echo '<option value="' . esc_attr((string) $code) . '" ' . selected($savez_code, (string) $code, false) . '>' . esc_html((string) $row['label']) . '</option>';
        }
        echo '</select><p class="description">Izaberi savez pod čijim je okriljem takmičenje.</p></td></tr>';
        echo '<tr data-opentt-step="1"><th>Rang takmičenja</th><td><select name="rang" required>';
        for ($i = 1; $i <= 5; $i++) {
            echo '<option value="' . esc_attr((string) $i) . '" ' . selected($rank, $i, false) . '>' . esc_html((string) $i) . '</option>';
        }
        echo '</select><p class="description">1 = najprestižnije, 5 = najniži rang.</p></td></tr>';
        echo '<tr data-opentt-step="1"><th>Grb takmičenja</th><td><div id="opentt_comp_thumb_preview">' . $thumb_html . '</div><input type="hidden" id="opentt_comp_thumb_id" name="featured_image_id" value="' . esc_attr((string) $thumb_id) . '"><p><button type="button" class="button" id="opentt_comp_thumb_btn">Izaberi grb</button> <button type="button" class="button" id="opentt_comp_thumb_remove">Ukloni</button></p></td></tr>';
        echo '<tr data-opentt-step="2"><th>Koliko ekipa ide gore</th><td><input type="number" min="0" name="promocija_broj" value="' . esc_attr((string) $promo) . '" style="width:120px;"></td></tr>';
        echo '<tr data-opentt-step="2"><th>Baraž za ulazak (gore)</th><td><input type="number" min="0" name="promocija_baraz_broj" value="' . esc_attr((string) $promo_baraz) . '" style="width:120px;"><p class="description">Broj ekipa ispod direktne promocije koje igraju baraž za viši rang.</p></td></tr>';
        echo '<tr data-opentt-step="2"><th>Koliko ekipa ispada</th><td><input type="number" min="0" name="ispadanje_broj" value="' . esc_attr((string) $releg) . '" style="width:120px;"></td></tr>';
        echo '<tr data-opentt-step="2"><th>Razigravanje za opstanak</th><td><input type="number" min="0" name="ispadanje_razigravanje_broj" value="' . esc_attr((string) $releg_razigravanje) . '" style="width:120px;"><p class="description">Broj ekipa iznad zone direktnog ispadanja koje igraju razigravanje za opstanak.</p></td></tr>';
        echo '<tr data-opentt-step="2"><th>Bodovanje</th><td><select name="bodovanje_tip"><option value="2-1" ' . selected($scoring, '2-1', false) . '>2-1</option><option value="3-0_4-3_2-1" ' . selected($scoring, '3-0_4-3_2-1', false) . '>3-0 (4:3 = 2/1)</option></select></td></tr>';
        echo '<tr data-opentt-step="2"><th>Format partija</th><td><select name="format_partija"><option value="format_a" ' . selected($format, 'format_a', false) . '>Format A (dubl 4. partija)</option><option value="format_b" ' . selected($format, 'format_b', false) . '>Format B (dubl 7. partija)</option></select><p class="description">Izaberi format prema pravilima takmičenja.</p></td></tr>';
        echo '<tr data-opentt-step="3"><th>Potvrda</th><td><p class="description">Sačuvaj pravila za izabranu ligu i sezonu.</p></td></tr>';
        echo '</tbody></table>';
        echo '<div class="opentt-wizard-nav">';
        echo '<button type="button" class="button opentt-wizard-prev">Nazad</button>';
        echo '<button type="button" class="button opentt-wizard-next">Dalje</button>';
        echo '<button type="submit" class="button button-primary opentt-wizard-submit">' . esc_html($rule ? 'Sačuvaj izmene' : 'Dodaj takmičenje') . '</button>';
        echo '<span class="opentt-wizard-help"></span>';
        echo '</div>';
        echo '</form></div>';
        echo <<<'JS'
<script>
(function($){
    var frame;
    $('#opentt_comp_thumb_btn').on('click', function(e){
        e.preventDefault();
        if (frame) { frame.open(); return; }
        frame = wp.media({ title: 'Izaberi grb takmičenja', button: { text: 'Postavi grb' }, multiple: false });
        frame.on('select', function(){
            var att = frame.state().get('selection').first().toJSON();
            $('#opentt_comp_thumb_id').val(att.id);
            $('#opentt_comp_thumb_preview').html('<img src="' + att.url + '" style="max-width:180px;height:auto;" />');
        });
        frame.open();
    });
    $('#opentt_comp_thumb_remove').on('click', function(){
        $('#opentt_comp_thumb_id').val('');
        $('#opentt_comp_thumb_preview').html('<div style="width:120px;height:120px;background:#f2f2f2;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;">Nema grba takmičenja</div>');
    });
})(jQuery);
</script>
JS;
    }

    private static function shortcode_catalog()
    {
        return [
            [
                'tag' => 'opentt_matches',
                'desc' => 'Kombinovani prikaz utakmica (grid/list switcher u jednom shortcode-u).',
                'attrs' => 'columns, limit, klub, played, liga, season, filter, infinite, opentt_match_date',
                'details' => 'Podrazumevano otvara grid prikaz. Kada je `filter=true`, desno u filter redu dodaje se view switcher (grid/list), a učitavanje dodatnih kartica radi preko dugmeta `Prikaži još`.',
                'builder' => [
                    ['name' => 'columns', 'label' => 'Kolone', 'type' => 'number', 'default' => '3', 'help' => 'Broj kolona u grid prikazu (1-6).'],
                    ['name' => 'limit', 'label' => 'Limit', 'type' => 'number', 'default' => '6', 'help' => 'Inicijalni broj utakmica i veličina sledećeg batch-a na `Prikaži još`.'],
                    ['name' => 'liga', 'label' => 'Liga slug', 'type' => 'text', 'default' => '', 'help' => 'Slug lige/takmičenja.'],
                    ['name' => 'season', 'label' => 'Season slug', 'type' => 'text', 'default' => '', 'help' => 'Slug sezone (npr. 2025-26).'],
                    ['name' => 'played', 'label' => 'Played', 'type' => 'text', 'default' => '', 'help' => 'true = odigrane, false = neodigrane, prazno = sve.'],
                    ['name' => 'filter', 'label' => 'Filter', 'type' => 'text', 'default' => 'true', 'help' => 'Grid filter/sort panel i kalendar (list prikaz koristi round navigator).'],
                    ['name' => 'infinite', 'label' => 'Infinite', 'type' => 'text', 'default' => 'false', 'help' => 'Za ovaj shortcode ostaje opcioni atribut; uz filter=true koristi se `Prikaži još` režim.'],
                    ['name' => 'opentt_match_date', 'label' => 'Datum (YYYY-MM-DD)', 'type' => 'text', 'default' => '', 'help' => 'Opcioni početni datum filtera.'],
                ],
            ],
            [
                'tag' => 'opentt_matches_grid',
                'desc' => 'Grid prikaz utakmica sa filterima/sortiranjem, kalendarskim filterom datuma i infinite opcijom.',
                'attrs' => 'columns, limit, klub, played, liga, season, filter, infinite, opentt_match_date',
                'details' => 'Najčešći shortcode za listing utakmica na početnoj ili liga stranici. Kada je `filter=true`, prikazuje i kalendar (desno) sa obojenim danima: odigrane (zeleno), predstojeće (plavo).',
                'builder' => [
                    ['name' => 'columns', 'label' => 'Kolone', 'type' => 'number', 'default' => '4', 'help' => 'Broj kolona u gridu (1-6).'],
                    ['name' => 'limit', 'label' => 'Limit', 'type' => 'number', 'default' => '8', 'help' => 'Broj utakmica inicijalno (i chunk za infinite).'],
                    ['name' => 'liga', 'label' => 'Liga slug', 'type' => 'text', 'default' => '', 'help' => 'Slug lige/takmičenja.'],
                    ['name' => 'season', 'label' => 'Season slug', 'type' => 'text', 'default' => '', 'help' => 'Slug sezone (npr. 2025-26).'],
                    ['name' => 'played', 'label' => 'Played', 'type' => 'text', 'default' => '', 'help' => 'true = odigrane, false = neodigrane, prazno = sve.'],
                    ['name' => 'filter', 'label' => 'Filter', 'type' => 'text', 'default' => 'true', 'help' => 'Uključuje filter/sort panel i kalendar datuma.'],
                    ['name' => 'infinite', 'label' => 'Infinite', 'type' => 'text', 'default' => 'true', 'help' => 'Učitavanje dodatnih kartica pri skrolu.'],
                    ['name' => 'opentt_match_date', 'label' => 'Datum (YYYY-MM-DD)', 'type' => 'text', 'default' => '', 'help' => 'Opcioni početni datum filtera (npr. 2026-03-03).'],
                ],
            ],
            [
                'tag' => 'opentt_featured_match',
                'desc' => 'Istaknuta utakmica sa countdown karticom i gradijentom boja dresova klubova.',
                'attrs' => 'mode, id, liga, sezona, title',
                'details' => '`mode="manual"` koristi ručno označen featured meč iz admina. `mode="auto"` koristi kontekst liga+sezona i bira najbliži predstojeći meč, uz derby tie-break (bolje rangirani klubovi po tabeli imaju prioritet).',
                'builder' => [
                    ['name' => 'mode', 'label' => 'Mode', 'type' => 'text', 'default' => 'manual', 'help' => 'manual ili auto.'],
                    ['name' => 'id', 'label' => 'ID utakmice', 'type' => 'number', 'default' => '', 'help' => 'Opciono: prisilno prikaži tačno ovaj meč.'],
                    ['name' => 'liga', 'label' => 'Liga slug', 'type' => 'text', 'default' => '', 'help' => 'Opciono ograničenje pri auto-izboru featured meča.'],
                    ['name' => 'sezona', 'label' => 'Sezona slug', 'type' => 'text', 'default' => '', 'help' => 'Opciono ograničenje pri auto-izboru featured meča.'],
                    ['name' => 'title', 'label' => 'Naslov bloka', 'type' => 'text', 'default' => 'Featured match', 'help' => 'Naslov shortcode sekcije.'],
                ],
            ],
            [
                'tag' => 'opentt_standings_table',
                'desc' => 'Tabela lige za kontekst stranice ili zadatu ligu/sezonu.',
                'attrs' => 'liga, sezona, highlight',
                'details' => 'Prikazuje tabelu takmičenja sa zonama promocije/ispadanja prema pravilima.',
                'builder' => [
                    ['name' => 'liga', 'label' => 'Liga slug', 'type' => 'text', 'default' => '', 'help' => 'Slug lige/takmičenja.'],
                    ['name' => 'sezona', 'label' => 'Sezona slug', 'type' => 'text', 'default' => '', 'help' => 'Slug sezone.'],
                    ['name' => 'highlight', 'label' => 'Highlight klubovi', 'type' => 'text', 'default' => '', 'help' => 'Klubovi za naglašavanje (zarez).'],
                ],
            ],
            ['tag' => 'opentt_match_games', 'desc' => 'Prikaz partija za jednu utakmicu (tok meča).', 'attrs' => 'kontekstualno', 'details' => 'Koristi kontekst trenutne utakmice.', 'builder' => []],
            ['tag' => 'opentt_match_teams', 'desc' => 'Header blok utakmice (domaćin/gost, rezultat, liga/kolo).', 'attrs' => 'kontekstualno', 'details' => 'Koristi kontekst trenutne utakmice.', 'builder' => []],
            ['tag' => 'opentt_h2h', 'desc' => 'Međusobni dueli klubova iz konteksta utakmice.', 'attrs' => 'limit', 'details' => 'Prikazuje poslednje međusobne rezultate.', 'builder' => [['name' => 'limit', 'label' => 'Limit', 'type' => 'number', 'default' => '3', 'help' => 'Koliko H2H mečeva.']]],
            ['tag' => 'opentt_mvp', 'desc' => 'Najkorisniji igrač utakmice.', 'attrs' => 'kontekstualno', 'details' => 'Računa MVP iz partija meča.', 'builder' => []],
            ['tag' => 'opentt_match_report', 'desc' => 'Tekstualni izveštaj utakmice.', 'attrs' => 'kontekstualno', 'details' => 'Prikazuje sačuvan izveštaj utakmice.', 'builder' => []],
            ['tag' => 'opentt_match_video', 'desc' => 'Snimak/vizuelni blok utakmice.', 'attrs' => 'kontekstualno', 'details' => 'Prikazuje snimak utakmice kada postoji.', 'builder' => []],
            [
                'tag' => 'opentt_top_players',
                'desc' => 'Rang lista igrača za ligu/sezonu.',
                'attrs' => 'liga, sezona, limit, kolo',
                'details' => 'Koristi DB partije i setove; može raditi kontekstualno.',
                'builder' => [
                    ['name' => 'liga', 'label' => 'Liga slug', 'type' => 'text', 'default' => ''],
                    ['name' => 'sezona', 'label' => 'Sezona slug', 'type' => 'text', 'default' => ''],
                    ['name' => 'limit', 'label' => 'Limit', 'type' => 'number', 'default' => '30'],
                    ['name' => 'kolo', 'label' => 'Do kog kola', 'type' => 'number', 'default' => ''],
                ],
            ],
            ['tag' => 'opentt_players', 'desc' => 'Prikaz igrača kluba.', 'attrs' => 'klub', 'details' => 'Na single-klub radi bez atributa.', 'builder' => [['name' => 'klub', 'label' => 'Klub slug', 'type' => 'text', 'default' => '']]],
            ['tag' => 'opentt_club_news', 'desc' => 'Kartice vesti kluba.', 'attrs' => 'klub, limit, columns', 'details' => 'Vesti povezane sa klubom.', 'builder' => [['name' => 'klub', 'label' => 'Klub slug', 'type' => 'text', 'default' => ''], ['name' => 'limit', 'label' => 'Limit', 'type' => 'number', 'default' => '6'], ['name' => 'columns', 'label' => 'Kolone', 'type' => 'number', 'default' => '3']]],
            ['tag' => 'opentt_player_news', 'desc' => 'Kartice vesti igrača.', 'attrs' => 'igrac, limit, columns', 'details' => 'Vesti povezane sa igračem.', 'builder' => [['name' => 'igrac', 'label' => 'Igrač slug', 'type' => 'text', 'default' => ''], ['name' => 'limit', 'label' => 'Limit', 'type' => 'number', 'default' => '6'], ['name' => 'columns', 'label' => 'Kolone', 'type' => 'number', 'default' => '3']]],
            ['tag' => 'opentt_related_posts', 'desc' => 'Povezane objave.', 'attrs' => 'limit, columns', 'details' => 'Kontekstualno vezane objave.', 'builder' => [['name' => 'limit', 'label' => 'Limit', 'type' => 'number', 'default' => '6'], ['name' => 'columns', 'label' => 'Kolone', 'type' => 'number', 'default' => '3']]],
            ['tag' => 'opentt_club_info', 'desc' => 'Info kartica kluba (osnovni podaci + takmičenje/savez).', 'attrs' => 'klub', 'details' => 'Na single-klub radi bez atributa.', 'builder' => [['name' => 'klub', 'label' => 'Klub slug', 'type' => 'text', 'default' => '']]],
            ['tag' => 'opentt_club_form', 'desc' => 'Poslednje utakmice kluba (forma).', 'attrs' => 'klub, limit', 'details' => 'Pobede/porazi sa stilskim markerima.', 'builder' => [['name' => 'klub', 'label' => 'Klub slug', 'type' => 'text', 'default' => ''], ['name' => 'limit', 'label' => 'Limit', 'type' => 'number', 'default' => '5']]],
            ['tag' => 'opentt_team_stats', 'desc' => 'Statistika ekipe + tabela oko pozicije kluba.', 'attrs' => 'klub, filter', 'details' => 'Prikaz metrika ekipe i skraćene/pune tabele.', 'builder' => [['name' => 'klub', 'label' => 'Klub slug', 'type' => 'text', 'default' => ''], ['name' => 'filter', 'label' => 'Filter', 'type' => 'text', 'default' => 'true']]],
            ['tag' => 'opentt_player_info', 'desc' => 'Info kartica igrača (profil + klub + državljanstvo).', 'attrs' => 'igrac', 'details' => 'Na single-igrac radi bez atributa.', 'builder' => [['name' => 'igrac', 'label' => 'Igrač slug', 'type' => 'text', 'default' => '']]],
            ['tag' => 'opentt_player_stats', 'desc' => 'Statistika igrača sa sezonskim filterom.', 'attrs' => 'igrac, filter', 'details' => 'Uključuje učinak i rang listu oko igrača.', 'builder' => [['name' => 'igrac', 'label' => 'Igrač slug', 'type' => 'text', 'default' => ''], ['name' => 'filter', 'label' => 'Filter', 'type' => 'text', 'default' => 'true']]],
            ['tag' => 'opentt_player_transfers', 'desc' => 'Istorija klubova igrača po sezonama.', 'attrs' => 'igrac', 'details' => 'Automatski gradi transfer istoriju iz mečeva.', 'builder' => [['name' => 'igrac', 'label' => 'Igrač slug', 'type' => 'text', 'default' => '']]],
            ['tag' => 'opentt_competition_info', 'desc' => 'Info kartica takmičenja (logo, sezona, savez).', 'attrs' => 'liga, sezona, show_logo', 'details' => 'Radi na arhivi lige ili sa zadatim slugovima.', 'builder' => [['name' => 'liga', 'label' => 'Liga slug', 'type' => 'text', 'default' => ''], ['name' => 'sezona', 'label' => 'Sezona slug', 'type' => 'text', 'default' => ''], ['name' => 'show_logo', 'label' => 'Prikaži logo', 'type' => 'text', 'default' => '1']]],
            ['tag' => 'opentt_competitions', 'desc' => 'Kartice takmičenja grupisane po rangu (1-5).', 'attrs' => 'limit, filter', 'details' => 'Poređano od ranga 1 ka rangu 5, uz opcioni filter po sezoni.', 'builder' => [['name' => 'limit', 'label' => 'Limit po rangu', 'type' => 'number', 'default' => '0'], ['name' => 'filter', 'label' => 'Filter', 'type' => 'text', 'default' => 'true', 'help' => 'Uključuje filter po sezoni.']]],
            [
                'tag' => 'opentt_clubs',
                'desc' => 'Grid/lista prikaz svih klubova sa filterima i infinite opcijom.',
                'attrs' => 'columns, limit, filter, infinite',
                'details' => 'Prikazuje klubove sa grbom, prefiksom STK i aktuelnom ligom. Filteri: liga i opština/grad, uz sortiranje po nazivu.',
                'builder' => [
                    ['name' => 'columns', 'label' => 'Kolone', 'type' => 'number', 'default' => '4', 'help' => 'Broj kolona na desktop prikazu (1-6).'],
                    ['name' => 'limit', 'label' => 'Limit', 'type' => 'number', 'default' => '-1', 'help' => '-1 učitava sve; uz infinite predstavlja batch veličinu.'],
                    ['name' => 'filter', 'label' => 'Filter', 'type' => 'text', 'default' => 'true', 'help' => 'Uključuje filter po ligi/opštini i sortiranje A-Z / Z-A.'],
                    ['name' => 'infinite', 'label' => 'Infinite', 'type' => 'text', 'default' => 'false', 'help' => 'Učitavanje dodatnih klubova pri skrolu.'],
                ],
            ],
        ];
    }

    private static function shortcode_attribute_help_map()
    {
        return [
            'columns' => 'Broj kolona prikaza (obično 1-6).',
            'limit' => 'Maksimalan broj stavki za prikaz.',
            'id' => 'ID konkretne utakmice.',
            'mode' => 'Režim izbora featured meča: manual ili auto.',
            'liga' => 'Slug lige/takmičenja.',
            'season' => 'Slug sezone (npr. 2025-26).',
            'sezona' => 'Slug sezone (npr. 2025-26).',
            'played' => 'Filter odigranosti: true odigrane, false neodigrane.',
            'odigrana' => 'Legacy alias za played: 1 odigrane, 0 neodigrane.',
            'filter' => 'Uključuje dodatne filtere/sort opcije.',
            'opentt_match_date' => 'Filter po tačnom datumu utakmice (YYYY-MM-DD).',
            'infinite' => 'Uključuje infinite scroll učitavanje.',
            'highlight' => 'Naglašava prosleđene klubove u tabeli.',
            'kolo' => 'Ograničava prikaz do određenog kola.',
            'klub' => 'Slug kluba za koji se povlače podaci.',
            'igrac' => 'Slug igrača za koji se povlače podaci.',
            'show_logo' => '1 prikazuje logo, 0 sakriva.',
            'opstina' => 'Opština/grad kluba (filter kroz polje grad).',
            'title' => 'Naslov sekcije shortcode bloka.',
        ];
    }

    private static function shortcode_css_class_reference()
    {
        return [
            'opentt_matches_grid' => ['module' => 'utakmice.css', 'classes' => ['.opentt-grid', '.opentt-grid-filters', '.opentt-grid-calendar-toggle', '.opentt-grid-calendar-popover', '.opentt-grid-cal-day', '.opentt-item', '.team.pobednik', '.team.gubitnik', '.meta']],
            'opentt_featured_match' => ['module' => 'featured-match.css', 'classes' => ['.opentt-featured-match-wrap', '.opentt-featured-match-card', '.opentt-featured-meta-top', '.opentt-featured-main', '.opentt-featured-team', '.opentt-featured-countdown', '.opentt-featured-meta-bottom']],
            'opentt_standings_table' => ['module' => 'tabela.css', 'classes' => ['.tabela-lige', '.tabela-lige tr.highlight', '.zone-promote-direct', '.zone-promote-playoff', '.zone-relegate-direct', '.zone-relegate-playoff']],
            'opentt_match_teams' => ['module' => 'ekipe.css', 'classes' => ['.opentt-ekipe', '.opentt-ekipe-home', '.opentt-ekipe-away', '.opentt-ekipe-score']],
            'opentt_match_games' => ['module' => 'partije.css', 'classes' => ['.lista-partija', '.partija-row', '.lp2-win', '.lp2-name']],
            'opentt_top_players' => ['module' => 'rang-lista.css', 'classes' => ['.igrac-rang-lista', '.igrac-card-list', '.igrac-card-list.highlight']],
            'opentt_club_info' => ['module' => 'info-kluba.css', 'classes' => ['.opentt-info-kluba', '.opentt-info-kluba-head', '.opentt-info-kluba-meta']],
            'opentt_player_info' => ['module' => 'info-igraca.css', 'classes' => ['.opentt-info-igraca', '.opentt-info-igraca-head', '.opentt-info-igraca-meta']],
            'opentt_club_form' => ['module' => 'forma-kluba.css', 'classes' => ['.opentt-forma-kluba', '.opentt-forma-item', '.opentt-forma-win', '.opentt-forma-loss']],
            'opentt_team_stats' => ['module' => 'statistika-ekipe.css', 'classes' => ['.opentt-stat-ekipe', '.opentt-stat-ekipe-card', '.opentt-stat-ekipe-table', '.opentt-stat-ekipe-table tr.highlight']],
            'opentt_player_stats' => ['module' => 'statistika-igraca.css', 'classes' => ['.opentt-stat-igraca', '.opentt-stat-igraca-cards', '.opentt-stat-igraca-card']],
            'opentt_player_transfers' => ['module' => 'transferi.css', 'classes' => ['.opentt-transferi', '.opentt-transferi-table', '.opentt-transferi-row']],
            'opentt_club_news' => ['module' => 'vesti-kluba.css', 'classes' => ['.stoni-vesti-grid', '.stoni-vesti-kartica', '.vest-klub-slika', '.vest-klub-naslov']],
            'opentt_player_news' => ['module' => 'vesti-kluba.css', 'classes' => ['.stoni-vesti-grid', '.stoni-vesti-kartica', '.vest-klub-slika', '.vest-klub-naslov']],
            'opentt_related_posts' => ['module' => 'related-posts.css', 'classes' => ['.related-posts-grid', '.related-post-item', '.related-post-content']],
            'opentt_players' => ['module' => 'prikaz-igraca.css', 'classes' => ['.stoni-igraci-list', '.stoni-igrac-card', '.stoni-igrac-row']],
            'opentt_competition_info' => ['module' => 'takmicenje-info.css', 'classes' => ['.opentt-takmicenje-info', '.opentt-takmicenje-info-title', '.opentt-takmicenje-info-meta']],
            'opentt_competitions' => ['module' => 'takmicenja-prikaz.css', 'classes' => ['.opentt-prikaz-takmicenja', '.opentt-prikaz-takmicenja-card', '.opentt-prikaz-takmicenja-title', '.opentt-prikaz-takmicenja-club-logo']],
            'opentt_clubs' => ['module' => 'prikaz-klubova.css', 'classes' => ['.opentt-klubovi', '.opentt-klubovi-grid', '.opentt-klubovi-item', '.opentt-klubovi-filters', '.opentt-klubovi-name']],
        ];
    }

    public static function render_settings_page()
    {
        self::require_cap();
        $catalog = self::shortcode_catalog();
        $admin_ui_lang = self::get_admin_ui_language();

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>Podešavanja</h1>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=stkb-unified-onboarding&preview=1')) . '">First Time Setup (pregled)</a></p>';

        echo '<div class="opentt-panel opentt-settings-panel">';
        echo '<h2>Jezik admin interfejsa</h2>';
        echo '<p class="description">Izaberi jezik za OpenTT admin interfejs.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-settings-css-form">';
        wp_nonce_field('opentt_unified_save_settings');
        echo '<input type="hidden" name="action" value="opentt_unified_save_settings">';
        echo '<input type="hidden" name="opentt_settings_section" value="ui_lang">';
        $available_langs = self::get_available_admin_ui_languages();
        echo '<label><span>Jezik</span><select name="admin_ui_language">';
        foreach ($available_langs as $code => $label) {
            echo '<option value="' . esc_attr((string) $code) . '"' . selected($admin_ui_lang, (string) $code, false) . '>' . esc_html((string) $label) . '</option>';
        }
        echo '</select></label>';
        echo '<div class="opentt-settings-actions">';
        echo '<button type="submit" class="button button-primary">Sačuvaj jezik</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        echo '<div class="opentt-panel opentt-settings-panel">';
        echo '<h2>Katalog shortcode-ova</h2>';
        echo '<p class="description">Klikni na shortcode da se otvore detalji, primer i mini builder za generisanje shortcode niza.</p>';
        echo '<div class="opentt-shortcode-details-list">';
        foreach ($catalog as $row) {
            $tag = (string) $row['tag'];
            $builder = isset($row['builder']) && is_array($row['builder']) ? $row['builder'] : [];
            $builder_json = wp_json_encode($builder);
            if (!is_string($builder_json)) {
                $builder_json = '[]';
            }
            echo '<details class="opentt-shortcode-details">';
            echo '<summary><code>[' . esc_html($tag) . ']</code> <span>' . esc_html((string) $row['desc']) . '</span><span class="opentt-shortcode-chevron" aria-hidden="true">▾</span></summary>';
            echo '<div class="opentt-shortcode-details-body">';
            echo '<p><strong>Opis:</strong> ' . esc_html((string) ($row['details'] ?? '')) . '</p>';
            echo '<p><strong>Atributi:</strong> <code>' . esc_html((string) $row['attrs']) . '</code></p>';
            $help_map = self::shortcode_attribute_help_map();
            $attr_names = array_filter(array_map('trim', explode(',', (string) ($row['attrs'] ?? ''))));
            if (!empty($attr_names) && strtolower((string) ($row['attrs'] ?? '')) !== 'kontekstualno') {
                echo '<ul class="opentt-shortcode-attr-help">';
                foreach ($attr_names as $attr_name) {
                    $a = sanitize_key((string) $attr_name);
                    if ($a === '') {
                        continue;
                    }
                    $field_help = '';
                    foreach ($builder as $b) {
                        if (($b['name'] ?? '') === $a && !empty($b['help'])) {
                            $field_help = (string) $b['help'];
                            break;
                        }
                    }
                    $help = $field_help !== '' ? $field_help : (string) ($help_map[$a] ?? 'Nema dodatnog opisa.');
                    echo '<li><code>' . esc_html($a) . '</code> - ' . esc_html($help) . '</li>';
                }
                echo '</ul>';
            }
            echo '<div class="opentt-shortcode-builder" data-tag="' . esc_attr($tag) . '" data-builder="' . esc_attr($builder_json) . '">';
            echo '<div class="opentt-shortcode-builder-fields"></div>';
            echo '<label>Dodatni atributi (opciono)</label>';
            echo '<input type="text" class="opentt-shortcode-extra" placeholder="npr. played=&quot;true&quot; klub=&quot;bubusinci&quot;">';
            echo '<label>Generisani shortcode</label>';
            echo '<textarea class="opentt-shortcode-output" rows="2" readonly></textarea>';
            echo '<div><button type="button" class="button opentt-shortcode-copy">Kopiraj</button></div>';
            echo '</div>';
            echo '</div>';
            echo '</details>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="opentt-panel opentt-settings-panel opentt-danger-panel">';
        echo '<h2>Brisanje svih podataka</h2>';
        echo '<p class="description">Ova akcija briše sve OpenTT podatke (DB tabele, OpenTT opcije, klubove, igrače, takmičenja i povezane taksonomije). Akcija je nepovratna.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-delete-data-form">';
        wp_nonce_field('opentt_unified_delete_all_data');
        echo '<input type="hidden" name="action" value="opentt_unified_delete_all_data">';
        echo '<label><strong>Potvrda:</strong> upiši tačno <code>saglasan sam</code></label>';
        echo '<input type="text" name="opentt_confirm_phrase" class="regular-text" placeholder="saglasan sam" autocomplete="off">';
        echo '<div class="opentt-settings-actions">';
        echo '<button type="submit" class="button button-link-delete" onclick="return confirm(\'Da li ste sigurni? Ovim brišete sve podatke i ne možete ih vratiti.\')">Obriši sve podatke</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo <<<'HTML'
<script>
(function(){
  function escapeAttr(s){
    return String(s || '').replace(/"/g, '&quot;');
  }
  function buildOutput(root){
    var tag = root.getAttribute('data-tag') || '';
    var attrs = [];
    root.querySelectorAll('[data-attr-name]').forEach(function(el){
      var name = el.getAttribute('data-attr-name');
      var val = String(el.value || '').trim();
      if (!name || !val) { return; }
      attrs.push(name + '="' + val.replace(/"/g, '\\"') + '"');
    });
    var extra = root.querySelector('.opentt-shortcode-extra');
    if (extra) {
      var extraVal = String(extra.value || '').trim();
      if (extraVal) { attrs.push(extraVal); }
    }
    var out = '[' + tag + (attrs.length ? ' ' + attrs.join(' ') : '') + ']';
    var outEl = root.querySelector('.opentt-shortcode-output');
    if (outEl) { outEl.value = out; }
  }
  function initBuilder(root){
    var raw = root.getAttribute('data-builder') || '[]';
    var fields = [];
    try { fields = JSON.parse(raw); } catch(e) { fields = []; }
    var wrap = root.querySelector('.opentt-shortcode-builder-fields');
    if (!wrap) { return; }
    fields.forEach(function(field){
      var row = document.createElement('div');
      row.className = 'opentt-shortcode-field-row';
      var label = document.createElement('label');
      label.textContent = field.label || field.name || '';
      var input = document.createElement('input');
      input.type = field.type || 'text';
      input.value = field.default || '';
      input.setAttribute('data-attr-name', field.name || '');
      input.addEventListener('input', function(){ buildOutput(root); });
      row.appendChild(label);
      row.appendChild(input);
      wrap.appendChild(row);
    });
    var extra = root.querySelector('.opentt-shortcode-extra');
    if (extra) { extra.addEventListener('input', function(){ buildOutput(root); }); }
    var copyBtn = root.querySelector('.opentt-shortcode-copy');
    if (copyBtn) {
      copyBtn.addEventListener('click', function(){
        var out = root.querySelector('.opentt-shortcode-output');
        if (!out) { return; }
        out.select();
        try { document.execCommand('copy'); } catch (e) {}
      });
    }
    buildOutput(root);
  }
  document.querySelectorAll('.opentt-shortcode-builder').forEach(initBuilder);
})();
</script>
HTML;
    }

    public static function render_customize_page()
    {
        self::require_cap();
        $custom_css = (string) get_option(self::OPTION_CUSTOM_SHORTCODE_CSS, '');
        $css_map = get_option(self::OPTION_CUSTOM_SHORTCODE_CSS_MAP, []);
        $visual = self::get_visual_settings();
        if (!is_array($css_map)) {
            $css_map = [];
        }
        $css_ref = self::shortcode_css_class_reference();

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>Prilagođavanje</h1>';

        echo '<div class="opentt-panel opentt-settings-panel opentt-visual-panel">';
        echo '<h2>Globalna stilizacija</h2>';
        echo '<p class="description">Za manje napredne korisnike: ovde menjaš osnovni izgled svih OpenTT blokova bez pisanja CSS-a.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-settings-css-form">';
        wp_nonce_field('opentt_unified_save_settings');
        echo '<input type="hidden" name="action" value="opentt_unified_save_settings">';
        echo '<input type="hidden" name="opentt_settings_section" value="visual">';
        echo '<div class="opentt-visual-grid">';
        echo '<label><span>Boja pozadine kontejnera</span><small>Menja pozadinu kartica i glavnih blokova shortcode-ova.</small><input type="text" name="visual_settings[container_bg]" value="' . esc_attr((string) $visual['container_bg']) . '" class="opentt-color-field"></label>';
        echo '<label><span>Boja ivice kontejnera</span><small>Menja linije/okvire kartica, tabela i listi.</small><input type="text" name="visual_settings[container_border]" value="' . esc_attr((string) $visual['container_border']) . '" class="opentt-color-field"></label>';
        echo '<label><span>Boja naslova</span><small>Menja naslove blokova i istaknute tekstove.</small><input type="text" name="visual_settings[title_color]" value="' . esc_attr((string) $visual['title_color']) . '" class="opentt-color-field"></label>';
        echo '<label><span>Boja teksta</span><small>Menja podnaslove, opise i standardni tekst.</small><input type="text" name="visual_settings[text_color]" value="' . esc_attr((string) $visual['text_color']) . '" class="opentt-color-field"></label>';
        echo '<label><span>Akcent boja</span><small>Menja boju linkova i naglašenih elemenata.</small><input type="text" name="visual_settings[accent_color]" value="' . esc_attr((string) $visual['accent_color']) . '" class="opentt-color-field"></label>';
        echo '<label><span>Zaobljenje kontejnera (px)</span><small>Menja koliko su kartice i blokovi zaobljeni.</small><input type="number" min="0" max="32" step="1" name="visual_settings[radius]" value="' . esc_attr((string) $visual['radius']) . '"></label>';
        echo '<label><span>Naslovi shortcode-ova</span><small>Uključi ili isključi automatske naslove sekcija koje plugin prikazuje na frontendu.</small><span style="display:flex;align-items:center;gap:8px;margin-top:8px;"><input type="hidden" name="visual_settings[show_shortcode_titles]" value="0"><input type="checkbox" name="visual_settings[show_shortcode_titles]" value="1" ' . checked((int) ($visual['show_shortcode_titles'] ?? 1), 1, false) . '> Prikaži naslove shortcode-ova</span></label>';
        echo '</div>';
        echo '<div class="opentt-settings-actions">';
        echo '<button type="submit" name="opentt_css_action" value="save" class="button button-primary">Sačuvaj globalni stil</button>';
        echo '<button type="submit" name="opentt_css_action" value="reset" class="button" onclick="return confirm(\'Resetovati globalnu stilizaciju?\')">Reset globalnog stila</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        echo '<div class="opentt-panel opentt-settings-panel">';
        echo '<h2>CSS Override (Shortcode-ovi)</h2>';
        echo '<p class="description">Napredna sekcija: puni CSS override (globalni + po shortcode-u). Uvek ima prioritet nad globalnom stilizacijom.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-settings-css-form">';
        wp_nonce_field('opentt_unified_save_settings');
        echo '<input type="hidden" name="action" value="opentt_unified_save_settings">';
        echo '<input type="hidden" name="opentt_settings_section" value="css">';
        echo '<h3>Globalni CSS Override</h3>';
        echo '<textarea name="custom_shortcode_css" class="opentt-settings-css-editor" spellcheck="false" placeholder=".opentt-item { border-radius: 12px; }">' . esc_textarea($custom_css) . '</textarea>';
        echo '<h3>CSS Override po Shortcode-u</h3>';
        echo '<div class="opentt-css-shortcode-list">';
        $changed_count = 0;
        foreach ($css_ref as $tag => $meta) {
            $value = isset($css_map[$tag]) && is_string($css_map[$tag]) ? (string) $css_map[$tag] : '';
            if (trim($value) !== '') {
                $changed_count++;
            }
        }
        if ($changed_count > 0) {
            echo '<p class="description opentt-css-changed-summary">Promenjeni CSS override-i: <strong>' . intval($changed_count) . '</strong></p>';
        }
        foreach ($css_ref as $tag => $meta) {
            $module = (string) ($meta['module'] ?? '');
            $classes = isset($meta['classes']) && is_array($meta['classes']) ? $meta['classes'] : [];
            $value = isset($css_map[$tag]) && is_string($css_map[$tag]) ? (string) $css_map[$tag] : '';
            $is_changed = trim($value) !== '';
            $card_class = 'opentt-css-shortcode-card' . ($is_changed ? ' is-changed' : '');
            echo '<details class="' . esc_attr($card_class) . '">';
            echo '<summary><code>[' . esc_html($tag) . ']</code> <span>' . esc_html($module) . '</span>';
            if ($is_changed) {
                echo '<span class="opentt-css-changed-badge">(Promenjen CSS)</span>';
            }
            echo '<span class="opentt-shortcode-chevron" aria-hidden="true">▾</span></summary>';
            echo '<div class="opentt-css-shortcode-body">';
            echo '<p class="description">Klase koje najčešće koristi ovaj shortcode:</p>';
            if (!empty($classes)) {
                echo '<ul class="opentt-css-class-list">';
                foreach ($classes as $cls) {
                    echo '<li><code>' . esc_html((string) $cls) . '</code></li>';
                }
                echo '</ul>';
            }
            echo '<textarea name="custom_shortcode_css_map[' . esc_attr((string) $tag) . ']" class="opentt-settings-css-editor opentt-settings-css-editor-small" spellcheck="false" placeholder="/* CSS override za [' . esc_attr((string) $tag) . '] */">' . esc_textarea($value) . '</textarea>';
            echo '</div>';
            echo '</details>';
        }
        echo '</div>';
        echo '<div class="opentt-settings-actions">';
        echo '<button type="submit" name="opentt_css_action" value="save" class="button button-primary">Sačuvaj CSS</button>';
        echo '<button type="submit" name="opentt_css_action" value="reset" class="button" onclick="return confirm(\'Resetovati sav custom CSS override?\')">Reset CSS</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }

    private static function data_transfer_sections()
    {
        return [
            'competitions' => 'Takmičenja',
            'clubs' => 'Klubovi',
            'players' => 'Igrači',
            'matches' => 'Utakmice (DB)',
            'games' => 'Partije (DB)',
            'sets' => 'Setovi (DB)',
        ];
    }

    private static function sanitize_transfer_sections($raw)
    {
        $all = array_keys(self::data_transfer_sections());
        $in = is_array($raw) ? $raw : [];
        $out = [];
        foreach ($in as $s) {
            $s = sanitize_key((string) $s);
            if (in_array($s, $all, true)) {
                $out[] = $s;
            }
        }
        return array_values(array_unique($out));
    }

    private static function render_transfer_sections_checkboxes($name, $selected)
    {
        $selected = is_array($selected) ? $selected : [];
        echo '<div class="opentt-transfer-sections">';
        foreach (self::data_transfer_sections() as $key => $label) {
            echo '<label class="opentt-transfer-check">';
            echo '<input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr($key) . '" ' . checked(in_array($key, $selected, true), true, false) . '>';
            echo '<span>' . esc_html($label) . '</span>';
            echo '</label>';
        }
        echo '</div>';
    }

    public static function render_import_export_page()
    {
        self::require_cap();
        $all_sections = array_keys(self::data_transfer_sections());
        $preview = get_option(self::OPTION_IMPORT_PREVIEW, []);
        if (!is_array($preview)) {
            $preview = [];
        }
        $competition_diag = get_option(self::OPTION_COMPETITION_DIAGNOSTICS, []);
        if (!is_array($competition_diag)) {
            $competition_diag = [];
        }

        echo '<div class="wrap opentt-admin">';
        self::render_admin_topbar();
        echo '<h1>Uvezi/Izvezi</h1>';

        echo '<div class="opentt-panel opentt-settings-panel">';
        echo '<h2>Izvezi podatke</h2>';
        echo '<p class="description">Izaberi šta želiš da izvezeš. Dobijaš jedan JSON fajl koji možeš kasnije uvesti u OpenTT. Featured slike (grbovi, slike igrača, logo takmičenja) se izvoze zajedno sa podacima.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('opentt_unified_export_data');
        echo '<input type="hidden" name="action" value="opentt_unified_export_data">';
        self::render_transfer_sections_checkboxes('sections', $all_sections);
        echo '<div class="opentt-settings-actions">';
        echo '<button type="button" class="button" onclick="(function(btn){var f=btn.form;f.querySelectorAll(\'input[type=checkbox][name=\\\'sections[]\\\']\').forEach(function(c){c.checked=true;});})(this)">Izaberi sve</button>';
        echo '<button type="button" class="button" onclick="(function(btn){var f=btn.form;f.querySelectorAll(\'input[type=checkbox][name=\\\'sections[]\\\']\').forEach(function(c){c.checked=false;});})(this)">Poništi izbor</button>';
        echo '<button type="submit" class="button button-primary">Izvezi JSON</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        echo '<div class="opentt-panel opentt-settings-panel">';
        echo '<h2>Uvezi podatke</h2>';
        echo '<p class="description">1) Izaberi JSON fajl i sekcije. 2) Pokreni validaciju. 3) Pregledaj rezultate i potvrdi uvoz. Featured slike iz paketa će biti automatski uvezene i povezane.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        wp_nonce_field('opentt_unified_import_validate');
        echo '<input type="hidden" name="action" value="opentt_unified_import_validate">';
        echo '<p><input type="file" name="import_file" accept=".json,application/json" required></p>';
        self::render_transfer_sections_checkboxes('sections', $all_sections);
        echo '<div class="opentt-settings-actions">';
        echo '<button type="button" class="button" onclick="(function(btn){var f=btn.form;f.querySelectorAll(\'input[type=checkbox][name=\\\'sections[]\\\']\').forEach(function(c){c.checked=true;});})(this)">Izaberi sve</button>';
        echo '<button type="button" class="button" onclick="(function(btn){var f=btn.form;f.querySelectorAll(\'input[type=checkbox][name=\\\'sections[]\\\']\').forEach(function(c){c.checked=false;});})(this)">Poništi izbor</button>';
        echo '<button type="submit" class="button button-primary">Validiraj uvoz</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        echo '<div class="opentt-panel opentt-settings-panel">';
        echo '<h2>Reset utakmica po takmičenju</h2>';
        echo '<p class="description">Obriši utakmice, partije i setove za jednu ligu/sezonu (bez SQL-a). Koristi pre ponovnog uvoza ako je stanje parcijalno.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Ovo će obrisati sve utakmice/partije/setove za izabrano takmičenje. Nastaviti?\')">';
        wp_nonce_field('opentt_unified_reset_competition_matches');
        echo '<input type="hidden" name="action" value="opentt_unified_reset_competition_matches">';
        echo '<p>' . self::competition_rules_dropdown_admin('competition_rule_id', 0, true) . '</p>';
        echo '<div class="opentt-settings-actions">';
        echo '<button type="submit" class="button button-secondary">Resetuj sezonu (utakmice/partije/setovi)</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        echo '<div class="opentt-panel opentt-settings-panel">';
        echo '<h2>Dijagnostika takmičenja</h2>';
        echo '<p class="description">Proveri stanje po kolima i po potrebi popravi <code>played</code> flag prema rezultatu utakmice.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('opentt_unified_competition_diagnostics');
        echo '<input type="hidden" name="action" value="opentt_unified_competition_diagnostics">';
        echo '<p>' . self::competition_rules_dropdown_admin('competition_rule_id', 0, true) . '</p>';
        echo '<div class="opentt-settings-actions">';
        echo '<button type="submit" class="button">Prikaži dijagnostiku</button>';
        echo '</div>';
        echo '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Popraviti played status po rezultatu za izabrano takmičenje?\')">';
        wp_nonce_field('opentt_unified_repair_competition_played');
        echo '<input type="hidden" name="action" value="opentt_unified_repair_competition_played">';
        echo '<p>' . self::competition_rules_dropdown_admin('competition_rule_id', 0, true) . '</p>';
        echo '<div class="opentt-settings-actions">';
        echo '<button type="submit" class="button button-secondary">Repair played po rezultatu</button>';
        echo '</div>';
        echo '</form>';
        if (!empty($competition_diag['rows']) && is_array($competition_diag['rows'])) {
            $diag_liga = sanitize_title((string) ($competition_diag['liga_slug'] ?? ''));
            $diag_sezona = sanitize_title((string) ($competition_diag['sezona_slug'] ?? ''));
            $diag_rows = (array) $competition_diag['rows'];
            $diag_generated = sanitize_text_field((string) ($competition_diag['generated_at'] ?? ''));
            echo '<h3>Poslednja dijagnostika: ' . esc_html(self::slug_to_title($diag_liga) . ' / ' . self::slug_to_title($diag_sezona)) . '</h3>';
            if ($diag_generated !== '') {
                echo '<p class="description">Generisano: ' . esc_html($diag_generated) . '</p>';
            }
            echo '<div class="opentt-table-scroll">';
            echo '<table class="widefat striped"><thead><tr><th>Kolo</th><th>Utakmice</th><th>Played=1</th><th>Sa rezultatom (&gt;0)</th><th>Partije</th><th>Status</th></tr></thead><tbody>';
            foreach ($diag_rows as $row) {
                $kolo_slug = sanitize_title((string) ($row['kolo_slug'] ?? ''));
                $matches_total = intval($row['matches_total'] ?? 0);
                $matches_played = intval($row['matches_played'] ?? 0);
                $matches_with_score = intval($row['matches_with_score'] ?? 0);
                $games_total = intval($row['games_total'] ?? 0);
                $ok = ($matches_played === $matches_with_score);
                echo '<tr>';
                echo '<td>' . esc_html($kolo_slug) . '</td>';
                echo '<td>' . intval($matches_total) . '</td>';
                echo '<td>' . intval($matches_played) . '</td>';
                echo '<td>' . intval($matches_with_score) . '</td>';
                echo '<td>' . intval($games_total) . '</td>';
                echo '<td>' . ($ok ? '<span style="color:#14c767;">OK</span>' : '<span style="color:#ff4d4f;">Mismatch</span>') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        }
        echo '</div>';

        if (!empty($preview['token']) && !empty($preview['summary']) && is_array($preview['summary'])) {
            $token = sanitize_key((string) $preview['token']);
            $summary = (array) $preview['summary'];
            $issues = isset($preview['issues']) && is_array($preview['issues']) ? $preview['issues'] : [];
            $sections = isset($preview['sections']) && is_array($preview['sections']) ? $preview['sections'] : [];
            $player_conflicts = isset($preview['player_conflicts']) && is_array($preview['player_conflicts']) ? $preview['player_conflicts'] : [];
            $valid = !empty($preview['valid']);

            echo '<div class="opentt-panel opentt-settings-panel">';
            echo '<h2>Validacija uvoza</h2>';
            echo '<p><strong>Status:</strong> ' . ($valid ? '<span style="color:#14c767">Spreman za uvoz</span>' : '<span style="color:#ff4d4f">Ima problema</span>') . '</p>';
            echo '<ul class="opentt-transfer-summary">';
            foreach (self::data_transfer_sections() as $key => $label) {
                if (!in_array($key, $sections, true)) {
                    continue;
                }
                $count = intval($summary[$key] ?? 0);
                echo '<li><strong>' . esc_html($label) . ':</strong> ' . intval($count) . '</li>';
            }
            echo '</ul>';
            if (!empty($issues)) {
                echo '<div class="opentt-transfer-issues"><strong>Problemi / upozorenja:</strong><ul>';
                foreach (array_slice($issues, 0, 120) as $issue) {
                    echo '<li>' . esc_html((string) $issue) . '</li>';
                }
                echo '</ul></div>';
            }

            if ($valid) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('opentt_unified_import_commit');
                echo '<input type="hidden" name="action" value="opentt_unified_import_commit">';
                echo '<input type="hidden" name="import_token" value="' . esc_attr($token) . '">';
                if (!empty($player_conflicts)) {
                    echo '<h3>Podudaranja igrača (merge pregled)</h3>';
                    echo '<p class="description">Pre potvrde uvoza izaberi šta raditi sa igračima koji liče na postojeće unose.</p>';
                    echo '<div class="opentt-settings-actions" style="margin:10px 0 12px 0;">';
                    echo '<button type="button" class="button" data-opentt-bulk-player-resolution="merge">Merge sve sa prvim kandidatom</button>';
                    echo '<button type="button" class="button" data-opentt-bulk-player-resolution="new">Kreiraj sve kao nove</button>';
                    echo '<button type="button" class="button" data-opentt-bulk-player-resolution="skip">Preskoči sve</button>';
                    echo '<button type="button" class="button" data-opentt-bulk-player-resolution="default">Vrati preporučeno</button>';
                    echo '</div>';
                    echo '<div class="opentt-table-scroll">';
                    echo '<table class="widefat striped"><thead><tr><th>Dolazni igrač</th><th>Kandidat(i) u bazi</th><th>Akcija</th></tr></thead><tbody>';
                    foreach ($player_conflicts as $conflict) {
                        $source_id = intval($conflict['source_id'] ?? 0);
                        if ($source_id <= 0) {
                            continue;
                        }
                        $incoming_name = sanitize_text_field((string) ($conflict['incoming_name'] ?? ''));
                        $incoming_slug = sanitize_title((string) ($conflict['incoming_slug'] ?? ''));
                        $incoming_club = sanitize_text_field((string) ($conflict['incoming_club'] ?? ''));
                        $default_resolution = sanitize_text_field((string) ($conflict['default_resolution'] ?? 'new'));
                        $candidates = isset($conflict['candidates']) && is_array($conflict['candidates']) ? $conflict['candidates'] : [];

                        echo '<tr>';
                        echo '<td><strong>' . esc_html($incoming_name) . '</strong>';
                        if ($incoming_club !== '') {
                            echo '<br><small>Klub iz importa: ' . esc_html($incoming_club) . '</small>';
                        }
                        if ($incoming_slug !== '') {
                            echo '<br><small>Slug: <code>' . esc_html($incoming_slug) . '</code></small>';
                        }
                        echo '</td>';
                        echo '<td>';
                        if (!empty($candidates)) {
                            echo '<ul style="margin:0;padding-left:16px;">';
                            foreach ($candidates as $candidate) {
                                $cid = intval($candidate['id'] ?? 0);
                                $cname = sanitize_text_field((string) ($candidate['name'] ?? ''));
                                $cclub = sanitize_text_field((string) ($candidate['club'] ?? ''));
                                $creason = sanitize_text_field((string) ($candidate['reason'] ?? 'name'));
                                echo '<li>#' . intval($cid) . ' ' . esc_html($cname) . ($cclub !== '' ? ' <small>(' . esc_html($cclub) . ')</small>' : '') . ' <small>[' . esc_html($creason) . ']</small></li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<span style="opacity:.7;">Nema kandidata</span>';
                        }
                        echo '</td>';
                        echo '<td>';
                        echo '<select class="opentt-player-resolution-select" name="player_resolution[' . intval($source_id) . ']" data-default-resolution="' . esc_attr($default_resolution) . '">';
                        foreach ($candidates as $candidate) {
                            $cid = intval($candidate['id'] ?? 0);
                            if ($cid <= 0) {
                                continue;
                            }
                            $opt_val = 'merge:' . $cid;
                            $opt_label = 'Merge sa #' . $cid . ' - ' . sanitize_text_field((string) ($candidate['name'] ?? ''));
                            echo '<option value="' . esc_attr($opt_val) . '"' . selected($default_resolution, $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
                        }
                        echo '<option value="new"' . selected($default_resolution, 'new', false) . '>Kreiraj novog igrača</option>';
                        echo '<option value="skip"' . selected($default_resolution, 'skip', false) . '>Preskoči ovog igrača</option>';
                        echo '</select>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '</div>';
                    echo '<script>(function(){'
                        . 'function setSelectValue(sel,target){if(!sel){return;}if(target==="default"){var d=sel.getAttribute("data-default-resolution")||"new";sel.value=d;return;}'
                        . 'if(target==="merge"){var opts=sel.options||[];for(var i=0;i<opts.length;i++){var v=(opts[i].value||"");if(v.indexOf("merge:")===0){sel.value=v;return;}}return;}'
                        . 'sel.value=target;}'
                        . 'document.addEventListener("click",function(e){var btn=e.target&&e.target.closest?e.target.closest("[data-opentt-bulk-player-resolution]"):null;if(!btn){return;}'
                        . 'var target=btn.getAttribute("data-opentt-bulk-player-resolution")||"";if(!target){return;}'
                        . 'var scope=btn.closest("form")||document;var sels=scope.querySelectorAll(".opentt-player-resolution-select");'
                        . 'for(var i=0;i<sels.length;i++){setSelectValue(sels[i],target);}'
                        . '});'
                    . '})();</script>';
                }
                echo '<div class="opentt-settings-actions">';
                echo '<button type="submit" class="button button-primary" onclick="return confirm(\'Da li želiš da potvrdiš uvoz ovih podataka?\')">Potvrdi uvoz</button>';
                echo '</div>';
                echo '</form>';
            }
            echo '</div>';
        }

        echo '</div>';
    }

    private static function export_attachment_payload($attachment_id)
    {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0) {
            return null;
        }
        $att = get_post($attachment_id);
        if (!$att || $att->post_type !== 'attachment') {
            return null;
        }
        $path = get_attached_file($attachment_id);
        if (!is_string($path) || $path === '' || !is_readable($path)) {
            return null;
        }
        $raw = file_get_contents($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        return [
            'source_id' => $attachment_id,
            'post_title' => (string) $att->post_title,
            'mime_type' => (string) $att->post_mime_type,
            'file_name' => wp_basename((string) $path),
            'alt' => (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'data_base64' => base64_encode($raw),
        ];
    }

    private static function export_featured_media_for_post($post_id)
    {
        $thumb_id = (int) get_post_thumbnail_id((int) $post_id);
        if ($thumb_id <= 0) {
            return null;
        }
        return self::export_attachment_payload($thumb_id);
    }

    private static function export_competitions_data()
    {
        $rows = get_posts([
            'post_type' => 'pravilo_takmicenja',
            'numberposts' => -1,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->ID;
            $out[] = [
                'source_id' => $id,
                'post_title' => (string) $r->post_title,
                'post_name' => (string) $r->post_name,
                'post_status' => (string) $r->post_status,
                'featured_media' => self::export_featured_media_for_post($id),
                'meta' => [
                    'opentt_competition_league_slug' => (string) get_post_meta($id, 'opentt_competition_league_slug', true),
                    'opentt_competition_season_slug' => (string) get_post_meta($id, 'opentt_competition_season_slug', true),
                    'opentt_competition_match_format' => (string) get_post_meta($id, 'opentt_competition_match_format', true),
                    'opentt_competition_scoring_type' => (string) get_post_meta($id, 'opentt_competition_scoring_type', true),
                    'opentt_competition_promotion_slots' => (int) get_post_meta($id, 'opentt_competition_promotion_slots', true),
                    'opentt_competition_promotion_playoff_slots' => (int) get_post_meta($id, 'opentt_competition_promotion_playoff_slots', true),
                    'opentt_competition_relegation_slots' => (int) get_post_meta($id, 'opentt_competition_relegation_slots', true),
                    'opentt_competition_relegation_playoff_slots' => (int) get_post_meta($id, 'opentt_competition_relegation_playoff_slots', true),
                    'opentt_competition_federation' => (string) get_post_meta($id, 'opentt_competition_federation', true),
                    'opentt_competition_rank' => (int) get_post_meta($id, 'opentt_competition_rank', true),
                ],
            ];
        }
        return $out;
    }

    private static function export_clubs_data()
    {
        $rows = get_posts([
            'post_type' => 'klub',
            'numberposts' => -1,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];
        $meta_keys = ['grad', 'opstina', 'kontakt', 'email', 'zastupnik_kluba', 'website_kluba', 'boja_dresa', 'loptice', 'adresa_kluba', 'adresa_sale', 'termin_igranja'];
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->ID;
            $meta = [];
            foreach ($meta_keys as $key) {
                $meta[$key] = (string) get_post_meta($id, $key, true);
            }
            $out[] = [
                'source_id' => $id,
                'post_title' => (string) $r->post_title,
                'post_name' => (string) $r->post_name,
                'post_content' => (string) $r->post_content,
                'post_status' => (string) $r->post_status,
                'featured_media' => self::export_featured_media_for_post($id),
                'meta' => $meta,
            ];
        }
        return $out;
    }

    private static function export_players_data()
    {
        $rows = get_posts([
            'post_type' => 'igrac',
            'numberposts' => -1,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];
        $meta_keys = ['datum_rodjenja', 'mesto_rodjenja', 'drzavljanstvo', 'povezani_klub', 'klub_igraca'];
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->ID;
            $meta = [];
            foreach ($meta_keys as $key) {
                $meta[$key] = get_post_meta($id, $key, true);
            }
            $club_id = (int) (OpenTT_Unified_Readonly_Helpers::extract_id($meta['povezani_klub']) ?: OpenTT_Unified_Readonly_Helpers::extract_id($meta['klub_igraca']));
            $out[] = [
                'source_id' => $id,
                'post_title' => (string) $r->post_title,
                'post_name' => (string) $r->post_name,
                'post_content' => (string) $r->post_content,
                'post_status' => (string) $r->post_status,
                'club_source_id' => $club_id,
                'featured_media' => self::export_featured_media_for_post($id),
                'meta' => $meta,
            ];
        }
        return $out;
    }

    private static function export_matches_data()
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');
        if (!self::table_exists($table)) {
            return [];
        }
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC") ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'source_id' => (int) $r->id,
                'liga_slug' => (string) $r->liga_slug,
                'sezona_slug' => (string) $r->sezona_slug,
                'kolo_slug' => (string) $r->kolo_slug,
                'slug' => (string) $r->slug,
                'match_date' => (string) $r->match_date,
                'location' => (string) ($r->location ?? ''),
                'home_score' => (int) $r->home_score,
                'away_score' => (int) $r->away_score,
                'played' => (int) $r->played,
                'featured' => (int) ($r->featured ?? 0),
                'live' => (int) ($r->live ?? 0),
                'home_club_source_id' => (int) $r->home_club_post_id,
                'away_club_source_id' => (int) $r->away_club_post_id,
                'legacy_post_id' => (int) $r->legacy_post_id,
            ];
        }
        return $out;
    }

    private static function export_games_data()
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('games');
        if (!self::table_exists($table)) {
            return [];
        }
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC") ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'source_id' => (int) $r->id,
                'match_source_id' => (int) $r->match_id,
                'order_no' => (int) $r->order_no,
                'is_doubles' => (int) $r->is_doubles,
                'home_sets' => (int) $r->home_sets,
                'away_sets' => (int) $r->away_sets,
                'home_player_source_id' => (int) $r->home_player_post_id,
                'away_player_source_id' => (int) $r->away_player_post_id,
                'home_player2_source_id' => (int) $r->home_player2_post_id,
                'away_player2_source_id' => (int) $r->away_player2_post_id,
                'legacy_post_id' => (int) $r->legacy_post_id,
            ];
        }
        return $out;
    }

    private static function export_sets_data()
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('sets');
        if (!self::table_exists($table)) {
            return [];
        }
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC") ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'source_id' => (int) $r->id,
                'game_source_id' => (int) $r->game_id,
                'set_no' => (int) $r->set_no,
                'home_points' => (int) $r->home_points,
                'away_points' => (int) $r->away_points,
            ];
        }
        return $out;
    }

    private static function build_export_payload($sections)
    {
        $sections = self::sanitize_transfer_sections($sections);
        $data = [];
        if (in_array('competitions', $sections, true)) {
            $data['competitions'] = self::export_competitions_data();
        }
        if (in_array('clubs', $sections, true)) {
            $data['clubs'] = self::export_clubs_data();
        }
        if (in_array('players', $sections, true)) {
            $data['players'] = self::export_players_data();
        }
        if (in_array('matches', $sections, true)) {
            $data['matches'] = self::export_matches_data();
        }
        if (in_array('games', $sections, true)) {
            $data['games'] = self::export_games_data();
        }
        if (in_array('sets', $sections, true)) {
            $data['sets'] = self::export_sets_data();
        }

        return [
            'format' => 'opentt-data-transfer',
            'version' => '1.1.0',
            'generated_at' => gmdate('c'),
            'site' => home_url('/'),
            'sections' => $sections,
            'data' => $data,
        ];
    }

    public static function handle_export_data_admin()
    {
        \OpenTT\Unified\WordPress\DataTransferActionManager::handleExport([
            'capability' => self::CAP,
            'transfer_url' => admin_url('admin.php?page=stkb-unified-transfer'),
            'sanitize_sections' => static function ($raw) {
                return self::sanitize_transfer_sections($raw);
            },
            'build_export_payload' => static function ($sections) {
                return self::build_export_payload($sections);
            },
        ]);
    }

    private static function parse_import_payload_from_upload($file_field)
    {
        return \OpenTT\Unified\WordPress\ImportPayloadInspector::parseFromUpload($file_field);
    }

    private static function summarize_import_payload($payload, $sections)
    {
        $sections = self::sanitize_transfer_sections($sections);
        return \OpenTT\Unified\WordPress\ImportPayloadInspector::summarize($payload, $sections);
    }

    private static function validate_import_payload($payload, $sections)
    {
        $sections = self::sanitize_transfer_sections($sections);
        return \OpenTT\Unified\WordPress\ImportPayloadInspector::validate($payload, $sections);
    }

    private static function normalize_player_name_key($name)
    {
        $name = remove_accents((string) $name);
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/u', ' ', $name);
        $name = trim((string) $name);
        return $name;
    }

    private static function detect_player_merge_conflicts($payload, $sections)
    {
        $sections = self::sanitize_transfer_sections($sections);
        if (!in_array('players', $sections, true)) {
            return [];
        }
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        $incoming_players = isset($data['players']) && is_array($data['players']) ? $data['players'] : [];
        if (empty($incoming_players)) {
            return [];
        }

        $incoming_clubs = [];
        $incoming_club_rows = isset($data['clubs']) && is_array($data['clubs']) ? $data['clubs'] : [];
        foreach ($incoming_club_rows as $club_row) {
            $source = intval($club_row['source_id'] ?? 0);
            if ($source <= 0) {
                continue;
            }
            $incoming_clubs[$source] = sanitize_text_field((string) ($club_row['post_title'] ?? ''));
        }

        $existing_rows = get_posts([
            'post_type' => 'igrac',
            'numberposts' => -1,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'orderby' => 'title',
            'order' => 'ASC',
            'suppress_filters' => true,
        ]) ?: [];

        $existing_by_name = [];
        foreach ($existing_rows as $existing) {
            $pid = (int) $existing->ID;
            $key = self::normalize_player_name_key((string) $existing->post_title);
            if ($key === '') {
                continue;
            }
            if (!isset($existing_by_name[$key])) {
                $existing_by_name[$key] = [];
            }
            $existing_by_name[$key][] = $pid;
        }

        $conflicts = [];
        foreach ($incoming_players as $row) {
            $source_id = intval($row['source_id'] ?? 0);
            if ($source_id <= 0) {
                continue;
            }
            $incoming_name = sanitize_text_field((string) ($row['post_title'] ?? ''));
            $incoming_slug = sanitize_title((string) ($row['post_name'] ?? ''));
            if ($incoming_slug === '') {
                $incoming_slug = sanitize_title($incoming_name);
            }
            $incoming_club_id = intval($row['club_source_id'] ?? 0);
            $incoming_club_name = isset($incoming_clubs[$incoming_club_id]) ? (string) $incoming_clubs[$incoming_club_id] : '';

            $candidates = [];
            $by_slug = $incoming_slug !== '' ? get_page_by_path($incoming_slug, OBJECT, 'igrac') : null;
            if ($by_slug && !is_wp_error($by_slug)) {
                $pid = (int) $by_slug->ID;
                $club_id = self::get_player_club_id($pid);
                $candidates[$pid] = [
                    'id' => $pid,
                    'name' => (string) $by_slug->post_title,
                    'slug' => (string) $by_slug->post_name,
                    'club' => $club_id > 0 ? (string) get_the_title($club_id) : '',
                    'reason' => 'slug',
                ];
            }

            $name_key = self::normalize_player_name_key($incoming_name);
            if ($name_key !== '' && !empty($existing_by_name[$name_key])) {
                foreach ((array) $existing_by_name[$name_key] as $pid) {
                    $ep = get_post((int) $pid);
                    if (!$ep || $ep->post_type !== 'igrac') {
                        continue;
                    }
                    $club_id = self::get_player_club_id((int) $ep->ID);
                    if (!isset($candidates[(int) $ep->ID])) {
                        $candidates[(int) $ep->ID] = [
                            'id' => (int) $ep->ID,
                            'name' => (string) $ep->post_title,
                            'slug' => (string) $ep->post_name,
                            'club' => $club_id > 0 ? (string) get_the_title($club_id) : '',
                            'reason' => 'name',
                        ];
                    }
                }
            }

            if (empty($candidates)) {
                continue;
            }
            $candidates = array_values($candidates);
            usort($candidates, static function ($a, $b) {
                $ra = (string) ($a['reason'] ?? '');
                $rb = (string) ($b['reason'] ?? '');
                if ($ra !== $rb) {
                    return $ra === 'slug' ? -1 : 1;
                }
                return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            });

            $default_resolution = 'new';
            foreach ($candidates as $candidate) {
                if ((string) ($candidate['reason'] ?? '') === 'slug') {
                    $default_resolution = 'merge:' . intval($candidate['id']);
                    break;
                }
            }
            if ($default_resolution === 'new' && count($candidates) === 1) {
                $default_resolution = 'merge:' . intval($candidates[0]['id']);
            }

            $conflicts[] = [
                'source_id' => $source_id,
                'incoming_name' => $incoming_name,
                'incoming_slug' => $incoming_slug,
                'incoming_club' => $incoming_club_name,
                'candidates' => $candidates,
                'default_resolution' => $default_resolution,
            ];
        }

        return $conflicts;
    }

    public static function handle_import_validate_admin()
    {
        \OpenTT\Unified\WordPress\DataTransferActionManager::handleImportValidate([
            'capability' => self::CAP,
            'transfer_url' => admin_url('admin.php?page=stkb-unified-transfer'),
            'import_preview_option_key' => self::OPTION_IMPORT_PREVIEW,
            'sanitize_sections' => static function ($raw) {
                return self::sanitize_transfer_sections($raw);
            },
            'parse_import_payload' => static function ($file_field) {
                return self::parse_import_payload_from_upload($file_field);
            },
            'validate_import_payload' => static function ($payload, $sections) {
                return self::validate_import_payload($payload, $sections);
            },
            'detect_player_conflicts' => static function ($payload, $sections) {
                return self::detect_player_merge_conflicts($payload, $sections);
            },
        ]);
    }

    private static function upsert_post_from_import($post_type, $row, $meta_keys = [], $slug_fallback = '', $forced_post_id = 0)
    {
        $post_type = sanitize_key((string) $post_type);
        $slug = sanitize_title((string) ($row['post_name'] ?? ''));
        if ($slug === '') {
            $slug = sanitize_title((string) ($slug_fallback !== '' ? $slug_fallback : ($row['post_title'] ?? '')));
        }
        $existing = null;
        $forced_post_id = intval($forced_post_id);
        if ($forced_post_id > 0) {
            $forced_post = get_post($forced_post_id);
            if ($forced_post && $forced_post->post_type === $post_type) {
                $existing = $forced_post;
                $slug = (string) $forced_post->post_name;
            }
        }
        if (!$existing) {
            $existing = $slug !== '' ? get_page_by_path($slug, OBJECT, $post_type) : null;
        }
        $postarr = [
            'post_type' => $post_type,
            'post_title' => sanitize_text_field((string) ($row['post_title'] ?? '')),
            'post_name' => $slug,
            'post_content' => (string) ($row['post_content'] ?? ''),
            'post_status' => sanitize_key((string) ($row['post_status'] ?? 'publish')),
        ];
        if ($postarr['post_status'] === '') {
            $postarr['post_status'] = 'publish';
        }
        if ($existing && !is_wp_error($existing)) {
            $postarr['ID'] = (int) $existing->ID;
            $post_id = wp_update_post($postarr, true);
        } else {
            $post_id = wp_insert_post($postarr, true);
        }
        if (is_wp_error($post_id) || !$post_id) {
            return 0;
        }

        $meta = isset($row['meta']) && is_array($row['meta']) ? $row['meta'] : [];
        foreach ($meta_keys as $key) {
            if (array_key_exists($key, $meta)) {
                update_post_meta((int) $post_id, $key, $meta[$key]);
            }
        }
        return (int) $post_id;
    }

    private static function find_imported_attachment_by_source_id($source_id)
    {
        $source_id = (int) $source_id;
        if ($source_id <= 0) {
            return 0;
        }
        $posts = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'numberposts' => 1,
            'fields' => 'ids',
            'meta_key' => '_opentt_import_source_attachment_id',
            'meta_value' => (string) $source_id,
            'suppress_filters' => true,
        ]);
        if (!empty($posts[0])) {
            return (int) $posts[0];
        }
        return 0;
    }

    private static function import_attachment_from_payload($media, $parent_post_id, &$issues = [])
    {
        if (!is_array($media)) {
            return 0;
        }
        $source_id = (int) ($media['source_id'] ?? 0);
        if ($source_id > 0) {
            $existing = self::find_imported_attachment_by_source_id($source_id);
            if ($existing > 0) {
                return $existing;
            }
        }

        $base64 = (string) ($media['data_base64'] ?? '');
        $file_name = sanitize_file_name((string) ($media['file_name'] ?? ''));
        $mime_type = (string) ($media['mime_type'] ?? 'image/jpeg');
        if ($base64 === '') {
            $issues[] = 'Media import: nedostaje data_base64.';
            return 0;
        }
        if ($file_name === '') {
            $ext = '';
            if (strpos($mime_type, '/') !== false) {
                $ext = '.' . sanitize_file_name(substr(strrchr($mime_type, '/'), 1));
            }
            $file_name = 'opentt-media-' . wp_generate_password(8, false, false) . $ext;
        }

        $binary = base64_decode($base64, true);
        if (!is_string($binary) || $binary === '') {
            $issues[] = 'Media import: nevalidan base64 sadržaj za fajl ' . $file_name . '.';
            return 0;
        }

        $upload = wp_upload_bits($file_name, null, $binary);
        if (!empty($upload['error'])) {
            $issues[] = 'Media import: greška upload-a za ' . $file_name . ' (' . (string) $upload['error'] . ').';
            return 0;
        }

        $attachment = [
            'post_mime_type' => $mime_type,
            'post_title' => sanitize_text_field((string) ($media['post_title'] ?? pathinfo($file_name, PATHINFO_FILENAME))),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => (int) $parent_post_id,
        ];
        $attach_id = wp_insert_attachment($attachment, $upload['file'], (int) $parent_post_id, true);
        if (is_wp_error($attach_id) || !$attach_id) {
            $issues[] = 'Media import: nije moguće kreirati attachment za ' . $file_name . '.';
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $meta = wp_generate_attachment_metadata((int) $attach_id, $upload['file']);
        if (is_array($meta) && !empty($meta)) {
            wp_update_attachment_metadata((int) $attach_id, $meta);
        }

        $alt = (string) ($media['alt'] ?? '');
        if ($alt !== '') {
            update_post_meta((int) $attach_id, '_wp_attachment_image_alt', $alt);
        }
        if ($source_id > 0) {
            update_post_meta((int) $attach_id, '_opentt_import_source_attachment_id', (string) $source_id);
        }

        return (int) $attach_id;
    }

    private static function import_payload_apply($payload, $sections, $import_options = [])
    {
        global $wpdb;
        $sections = self::sanitize_transfer_sections($sections);
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        $import_options = is_array($import_options) ? $import_options : [];
        $player_resolution = isset($import_options['player_resolution']) && is_array($import_options['player_resolution'])
            ? (array) $import_options['player_resolution']
            : [];

        self::maybe_migrate_schema();
        $matches_table = OpenTT_Unified_Core::db_table('matches');
        $games_table = OpenTT_Unified_Core::db_table('games');
        $sets_table = OpenTT_Unified_Core::db_table('sets');

        $map = [
            'club' => [],
            'player' => [],
            'match' => [],
            'game' => [],
            'attachment' => [],
        ];
        $result = [
            'competitions' => 0,
            'clubs' => 0,
            'players' => 0,
            'matches' => 0,
            'games' => 0,
            'sets' => 0,
            'issues' => [],
        ];

        if (in_array('clubs', $sections, true) && !empty($data['clubs']) && is_array($data['clubs'])) {
            foreach ($data['clubs'] as $row) {
                $post_id = self::upsert_post_from_import('klub', $row, ['grad', 'opstina', 'kontakt', 'email', 'zastupnik_kluba', 'website_kluba', 'boja_dresa', 'loptice', 'adresa_kluba', 'adresa_sale', 'termin_igranja']);
                if ($post_id > 0) {
                    $source = intval($row['source_id'] ?? 0);
                    if ($source > 0) {
                        $map['club'][$source] = $post_id;
                    }
                    $media = isset($row['featured_media']) && is_array($row['featured_media']) ? $row['featured_media'] : null;
                    if (is_array($media)) {
                        $media_source_id = intval($media['source_id'] ?? 0);
                        $thumb_id = 0;
                        if ($media_source_id > 0 && !empty($map['attachment'][$media_source_id])) {
                            $thumb_id = (int) $map['attachment'][$media_source_id];
                        } else {
                            $thumb_id = self::import_attachment_from_payload($media, $post_id, $result['issues']);
                            if ($thumb_id > 0 && $media_source_id > 0) {
                                $map['attachment'][$media_source_id] = $thumb_id;
                            }
                        }
                        if ($thumb_id > 0) {
                            set_post_thumbnail($post_id, $thumb_id);
                        }
                    }
                    $result['clubs']++;
                }
            }
        }

        if (in_array('players', $sections, true) && !empty($data['players']) && is_array($data['players'])) {
            foreach ($data['players'] as $row) {
                $source = intval($row['source_id'] ?? 0);
                $resolution_raw = $source > 0 && isset($player_resolution[$source]) ? sanitize_text_field((string) $player_resolution[$source]) : '';
                if ($resolution_raw === 'skip') {
                    $result['issues'][] = 'Preskočen igrač po izboru korisnika: ' . sanitize_text_field((string) ($row['post_title'] ?? ''));
                    continue;
                }

                $forced_post_id = 0;
                $row_for_import = $row;
                if (strpos($resolution_raw, 'merge:') === 0) {
                    $forced_post_id = intval(substr($resolution_raw, 6));
                    if ($forced_post_id > 0) {
                        $forced = get_post($forced_post_id);
                        if (!$forced || $forced->post_type !== 'igrac') {
                            $forced_post_id = 0;
                        }
                    }
                } elseif ($resolution_raw === 'new') {
                    $base_slug = sanitize_title((string) ($row_for_import['post_name'] ?? ''));
                    if ($base_slug === '') {
                        $base_slug = sanitize_title((string) ($row_for_import['post_title'] ?? ''));
                    }
                    if ($base_slug !== '') {
                        $existing_slug = get_page_by_path($base_slug, OBJECT, 'igrac');
                        if ($existing_slug && !is_wp_error($existing_slug)) {
                            $row_for_import['post_name'] = $base_slug . '-src-' . max(1, $source);
                        }
                    }
                }

                $post_id = self::upsert_post_from_import('igrac', $row_for_import, ['datum_rodjenja', 'mesto_rodjenja', 'drzavljanstvo'], '', $forced_post_id);
                if ($post_id <= 0) {
                    continue;
                }
                $club_source = intval($row['club_source_id'] ?? 0);
                $club_id = intval($map['club'][$club_source] ?? 0);
                if ($club_id <= 0 && $club_source > 0) {
                    $club_post = get_post($club_source);
                    if ($club_post && $club_post->post_type === 'klub') {
                        $club_id = (int) $club_post->ID;
                    }
                }
                if ($club_id > 0) {
                    update_post_meta($post_id, 'povezani_klub', $club_id);
                    update_post_meta($post_id, 'klub_igraca', $club_id);
                }
                if ($source > 0) {
                    $map['player'][$source] = $post_id;
                }
                $media = isset($row['featured_media']) && is_array($row['featured_media']) ? $row['featured_media'] : null;
                if (is_array($media)) {
                    $media_source_id = intval($media['source_id'] ?? 0);
                    $thumb_id = 0;
                    if ($media_source_id > 0 && !empty($map['attachment'][$media_source_id])) {
                        $thumb_id = (int) $map['attachment'][$media_source_id];
                    } else {
                        $thumb_id = self::import_attachment_from_payload($media, $post_id, $result['issues']);
                        if ($thumb_id > 0 && $media_source_id > 0) {
                            $map['attachment'][$media_source_id] = $thumb_id;
                        }
                    }
                    if ($thumb_id > 0) {
                        set_post_thumbnail($post_id, $thumb_id);
                    }
                }
                $result['players']++;
            }
        }

        if (in_array('competitions', $sections, true) && !empty($data['competitions']) && is_array($data['competitions'])) {
            foreach ($data['competitions'] as $row) {
                $meta = isset($row['meta']) && is_array($row['meta']) ? $row['meta'] : [];
                $liga_slug = sanitize_title((string) ($meta['opentt_competition_league_slug'] ?? ''));
                $sezona_slug = sanitize_title((string) ($meta['opentt_competition_season_slug'] ?? ''));
                if ($liga_slug === '' || $sezona_slug === '') {
                    $result['issues'][] = 'Preskočeno takmičenje bez liga/sezona slug vrednosti.';
                    continue;
                }
                $existing = \OpenTT\Unified\WordPress\CompetitionRuleStore::findBySlugs($liga_slug, $sezona_slug);
                $post_id = self::upsert_post_from_import('pravilo_takmicenja', $row, [
                    'opentt_competition_league_slug',
                    'opentt_competition_season_slug',
                    'opentt_competition_match_format',
                    'opentt_competition_scoring_type',
                    'opentt_competition_promotion_slots',
                    'opentt_competition_promotion_playoff_slots',
                    'opentt_competition_relegation_slots',
                    'opentt_competition_relegation_playoff_slots',
                    'opentt_competition_federation',
                    'opentt_competition_rank',
                ], $liga_slug . '-' . $sezona_slug);
                if ($post_id <= 0 && $existing) {
                    $post_id = (int) $existing->ID;
                }
                if ($post_id > 0) {
                    $media = isset($row['featured_media']) && is_array($row['featured_media']) ? $row['featured_media'] : null;
                    if (is_array($media)) {
                        $media_source_id = intval($media['source_id'] ?? 0);
                        $thumb_id = 0;
                        if ($media_source_id > 0 && !empty($map['attachment'][$media_source_id])) {
                            $thumb_id = (int) $map['attachment'][$media_source_id];
                        } else {
                            $thumb_id = self::import_attachment_from_payload($media, $post_id, $result['issues']);
                            if ($thumb_id > 0 && $media_source_id > 0) {
                                $map['attachment'][$media_source_id] = $thumb_id;
                            }
                        }
                        if ($thumb_id > 0) {
                            set_post_thumbnail($post_id, $thumb_id);
                        }
                    }
                    $result['competitions']++;
                }
            }
        }

        if (in_array('matches', $sections, true) && !empty($data['matches']) && is_array($data['matches']) && self::table_exists($matches_table)) {
            foreach ($data['matches'] as $row) {
                $source = intval($row['source_id'] ?? 0);
                $liga_slug = sanitize_title((string) ($row['liga_slug'] ?? ''));
                $sezona_slug = sanitize_title((string) ($row['sezona_slug'] ?? ''));
                $kolo_slug = sanitize_title((string) ($row['kolo_slug'] ?? ''));
                $slug = sanitize_title((string) ($row['slug'] ?? ''));
                if ($liga_slug === '' || $kolo_slug === '' || $slug === '') {
                    $result['issues'][] = 'Preskočena utakmica (neispravni ključni podaci).';
                    continue;
                }
                $home_source = intval($row['home_club_source_id'] ?? 0);
                $away_source = intval($row['away_club_source_id'] ?? 0);
                $home_id = intval($map['club'][$home_source] ?? $home_source);
                $away_id = intval($map['club'][$away_source] ?? $away_source);
                if ($home_id <= 0 || $away_id <= 0) {
                    $result['issues'][] = 'Preskočena utakmica ' . $slug . ' (nedostaje klub mapiranje).';
                    continue;
                }

                $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$matches_table} WHERE liga_slug=%s AND sezona_slug=%s AND kolo_slug=%s AND slug=%s LIMIT 1", $liga_slug, $sezona_slug, $kolo_slug, $slug));
                $data_row = [
                    'liga_slug' => $liga_slug,
                    'sezona_slug' => $sezona_slug,
                    'kolo_slug' => $kolo_slug,
                    'slug' => $slug,
                    'match_date' => (string) ($row['match_date'] ?? current_time('mysql')),
                    'location' => sanitize_text_field((string) ($row['location'] ?? '')),
                    'home_club_post_id' => $home_id,
                    'away_club_post_id' => $away_id,
                    'home_score' => (int) ($row['home_score'] ?? 0),
                    'away_score' => (int) ($row['away_score'] ?? 0),
                    'played' => (int) ($row['played'] ?? 0),
                    'featured' => (int) ($row['featured'] ?? 0),
                    'live' => (int) ($row['live'] ?? 0),
                    'legacy_post_id' => (int) ($row['legacy_post_id'] ?? 0),
                ];
                if ($existing_id > 0) {
                    $ok = $wpdb->update($matches_table, $data_row, ['id' => $existing_id], ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d'], ['%d']);
                    if ($ok === false) {
                        $result['issues'][] = 'Greška update utakmice ' . $slug . ': ' . (string) $wpdb->last_error;
                        continue;
                    }
                    $new_id = $existing_id;
                } else {
                    $ok = $wpdb->insert($matches_table, $data_row, ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d']);
                    if ($ok === false) {
                        $result['issues'][] = 'Greška insert utakmice ' . $slug . ': ' . (string) $wpdb->last_error;
                        continue;
                    }
                    $new_id = (int) $wpdb->insert_id;
                }
                if ($source > 0 && $new_id > 0) {
                    $map['match'][$source] = $new_id;
                }
                $result['matches']++;
            }
        }

        if (in_array('games', $sections, true) && !empty($data['games']) && is_array($data['games']) && self::table_exists($games_table)) {
            foreach ($data['games'] as $row) {
                $source = intval($row['source_id'] ?? 0);
                $match_source = intval($row['match_source_id'] ?? 0);
                $match_id = intval($map['match'][$match_source] ?? $match_source);
                if ($match_id <= 0) {
                    $result['issues'][] = 'Preskočena partija (nedostaje mapiranje utakmice).';
                    continue;
                }
                $order_no = max(1, (int) ($row['order_no'] ?? 1));
                $home_player = intval($map['player'][intval($row['home_player_source_id'] ?? 0)] ?? intval($row['home_player_source_id'] ?? 0));
                $away_player = intval($map['player'][intval($row['away_player_source_id'] ?? 0)] ?? intval($row['away_player_source_id'] ?? 0));
                $home_player2 = intval($map['player'][intval($row['home_player2_source_id'] ?? 0)] ?? intval($row['home_player2_source_id'] ?? 0));
                $away_player2 = intval($map['player'][intval($row['away_player2_source_id'] ?? 0)] ?? intval($row['away_player2_source_id'] ?? 0));

                $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$games_table} WHERE match_id=%d AND order_no=%d LIMIT 1", $match_id, $order_no));
                $data_row = [
                    'match_id' => $match_id,
                    'order_no' => $order_no,
                    'home_player_post_id' => $home_player,
                    'away_player_post_id' => $away_player,
                    'home_player2_post_id' => $home_player2,
                    'away_player2_post_id' => $away_player2,
                    'home_sets' => (int) ($row['home_sets'] ?? 0),
                    'away_sets' => (int) ($row['away_sets'] ?? 0),
                    'is_doubles' => (int) ($row['is_doubles'] ?? 0),
                    'legacy_post_id' => (int) ($row['legacy_post_id'] ?? 0),
                ];
                if ($existing_id > 0) {
                    $ok = $wpdb->update($games_table, $data_row, ['id' => $existing_id], ['%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d'], ['%d']);
                    if ($ok === false) {
                        $result['issues'][] = 'Greška update partije (match_id=' . intval($match_id) . ', order=' . intval($order_no) . '): ' . (string) $wpdb->last_error;
                        continue;
                    }
                    $new_id = $existing_id;
                } else {
                    $ok = $wpdb->insert($games_table, $data_row, ['%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d']);
                    if ($ok === false) {
                        $result['issues'][] = 'Greška insert partije (match_id=' . intval($match_id) . ', order=' . intval($order_no) . '): ' . (string) $wpdb->last_error;
                        continue;
                    }
                    $new_id = (int) $wpdb->insert_id;
                }
                if ($source > 0 && $new_id > 0) {
                    $map['game'][$source] = $new_id;
                }
                $result['games']++;
            }
        }

        if (in_array('sets', $sections, true) && !empty($data['sets']) && is_array($data['sets']) && self::table_exists($sets_table)) {
            foreach ($data['sets'] as $row) {
                $game_source = intval($row['game_source_id'] ?? 0);
                $game_id = intval($map['game'][$game_source] ?? $game_source);
                if ($game_id <= 0) {
                    $result['issues'][] = 'Preskočen set (nedostaje mapiranje partije).';
                    continue;
                }
                $set_no = max(1, (int) ($row['set_no'] ?? 1));
                $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$sets_table} WHERE game_id=%d AND set_no=%d LIMIT 1", $game_id, $set_no));
                $data_row = [
                    'game_id' => $game_id,
                    'set_no' => $set_no,
                    'home_points' => (int) ($row['home_points'] ?? 0),
                    'away_points' => (int) ($row['away_points'] ?? 0),
                ];
                if ($existing_id > 0) {
                    $wpdb->update($sets_table, $data_row, ['id' => $existing_id], ['%d', '%d', '%d', '%d'], ['%d']);
                } else {
                    $wpdb->insert($sets_table, $data_row, ['%d', '%d', '%d', '%d']);
                }
                $result['sets']++;
            }
        }

        return $result;
    }

    public static function handle_import_commit_admin()
    {
        \OpenTT\Unified\WordPress\DataTransferActionManager::handleImportCommit([
            'capability' => self::CAP,
            'transfer_url' => admin_url('admin.php?page=stkb-unified-transfer'),
            'import_preview_option_key' => self::OPTION_IMPORT_PREVIEW,
            'sanitize_sections' => static function ($raw) {
                return self::sanitize_transfer_sections($raw);
            },
            'import_payload_apply' => static function ($payload, $sections, $import_options) {
                return self::import_payload_apply($payload, $sections, $import_options);
            },
        ]);
    }

    public static function handle_reset_competition_matches_admin()
    {
        \OpenTT\Unified\WordPress\CompetitionMaintenanceManager::handleResetMatches(
            self::CAP,
            static function ($table_name) {
                return self::table_exists($table_name);
            },
            static function ($slug) {
                return self::slug_to_title($slug);
            }
        );
    }

    public static function handle_competition_diagnostics_admin()
    {
        \OpenTT\Unified\WordPress\CompetitionMaintenanceManager::handleDiagnostics(
            self::CAP,
            self::OPTION_COMPETITION_DIAGNOSTICS,
            static function ($liga_slug, $sezona_slug) {
                return \OpenTT\Unified\WordPress\CompetitionDiagnosticsQuery::roundDiagnostics($liga_slug, $sezona_slug);
            },
            static function ($slug) {
                return self::slug_to_title($slug);
            }
        );
    }

    public static function handle_repair_competition_played_admin()
    {
        \OpenTT\Unified\WordPress\CompetitionMaintenanceManager::handleRepairPlayed(
            self::CAP,
            self::OPTION_COMPETITION_DIAGNOSTICS,
            static function ($table_name) {
                return self::table_exists($table_name);
            },
            static function ($liga_slug, $sezona_slug) {
                return \OpenTT\Unified\WordPress\CompetitionDiagnosticsQuery::roundDiagnostics($liga_slug, $sezona_slug);
            },
            static function ($slug) {
                return self::slug_to_title($slug);
            }
        );
    }

    public static function render_onboarding_page()
    {
        self::require_cap();
        $logo_url = '';
        $logo_path = trailingslashit(self::$plugin_dir) . 'assets/img/admin-ui-logo.png';
        if (is_readable($logo_path)) {
            $logo_url = plugins_url('assets/img/admin-ui-logo.png', self::$plugin_file);
        }

        echo '<div class="wrap opentt-admin opentt-onboarding-wrap">';
        echo '<div class="opentt-panel opentt-onboarding-panel">';
        echo '<div class="opentt-onboarding-head">';
        if ($logo_url !== '') {
            echo '<img class="opentt-onboarding-logo" src="' . esc_url($logo_url) . '" alt="OpenTT logo">';
        }
        echo '<h1>Dobrodošao u OpenTT</h1>';
        echo '<p>One-time setup će te provesti kroz prve korake: takmičenje, timove, igrače i utakmice.</p>';
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-wizard-form opentt-onboarding-form" data-opentt-steps="5">';
        wp_nonce_field('opentt_unified_onboarding_action');
        echo '<input type="hidden" name="action" value="opentt_unified_onboarding_action">';
        echo '<div class="opentt-wizard-steps">';
        echo '<span class="opentt-step-pill">1. Uvod</span>';
        echo '<span class="opentt-step-pill">2. Takmičenje</span>';
        echo '<span class="opentt-step-pill">3. Timovi</span>';
        echo '<span class="opentt-step-pill">4. Igrači</span>';
        echo '<span class="opentt-step-pill">5. Utakmice</span>';
        echo '</div>';

        echo '<div class="opentt-onboarding-step" data-opentt-step="1">';
        echo '<h3>OpenTT Setup</h3>';
        echo '<p>OpenTT je nastao iz želje da se zajednici stonog tenisa vrati moderan, otvoren i praktičan alat za vođenje takmičenja. Ideja je jednostavna: klubovi, savezi i ljudi koji vode takmičenja zaslužuju sistem za 21. vek - brz, jasan i održiv, bez komplikovanih dodataka i ručnih workaround-a.</p>';
        echo '<p>Umesto rasutih tabela i nepovezanih podataka, OpenTT objedinjuje ceo tok rada na jednom mestu: takmičenja, klubove, igrače, utakmice, partije i statistiku. Sistem je napravljen da bude lak za svakodnevni unos rezultata, ali i dovoljno moćan za ozbiljno praćenje forme, rangova i istorije.</p>';
        echo '<h4>Šta OpenTT može odmah</h4>';
        echo '<ul>';
        echo '<li>Vođenje takmičenja sa pravilima po sezoni i formatu meča.</li>';
        echo '<li>Jednostavan admin unos klubova, igrača, utakmica, partija i setova.</li>';
        echo '<li>Automatski proračun tabela, forme ekipa, MVP prikaza i rang lista igrača.</li>';
        echo '<li>Kontekstualni frontend shortcode prikazi za single utakmicu, klub i igrača.</li>';
        echo '<li>Brz i skalabilan rad kroz DB model za utakmice/partije/setove.</li>';
        echo '</ul>';
        echo '<p>Kreni redom kroz sledeće korake i za nekoliko minuta imaćeš postavljen kompletan sistem.</p>';
        echo '</div>';

        echo '<div class="opentt-onboarding-step" data-opentt-step="2">';
        echo '<h3>Kreiraj prvo takmičenje</h3>';
        echo '<p>Unesi naziv, sezonu, format i pravila bodovanja.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-competition')) . '">+ Dodaj takmičenje</a></p>';
        echo '</div>';

        echo '<div class="opentt-onboarding-step" data-opentt-step="3">';
        echo '<h3>Dodaj timove (klubove)</h3>';
        echo '<p>Dodaj sve klubove koji učestvuju u takmičenju.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-club')) . '">+ Dodaj klub</a></p>';
        echo '</div>';

        echo '<div class="opentt-onboarding-step" data-opentt-step="4">';
        echo '<h3>Dodaj igrače</h3>';
        echo '<p>Dodaj igrače i poveži ih sa klubovima. Kasnije možeš unositi i istorijske partije.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-player')) . '">+ Dodaj igrača</a></p>';
        echo '</div>';

        echo '<div class="opentt-onboarding-step" data-opentt-step="5">';
        echo '<h3>Dodaj prvu utakmicu</h3>';
        echo '<p>Posle kreiranja utakmice unesi partije i setove iz edit ekrana utakmice.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=stkb-unified-add-match')) . '">+ Dodaj utakmicu</a></p>';
        echo '<p class="description">Kada završiš, klikni <strong>Završi setup</strong>.</p>';
        echo '</div>';

        echo '<div class="opentt-onboarding-actions">';
        echo '<button type="button" class="button opentt-wizard-prev">Nazad</button>';
        echo '<button type="button" class="button opentt-wizard-next">Dalje</button>';
        echo '<button type="submit" name="opentt_onboarding_action" value="complete" class="button button-primary opentt-wizard-submit">Završi setup</button>';
        echo '</div>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-onboarding-skip-form">';
        wp_nonce_field('opentt_unified_onboarding_action');
        echo '<input type="hidden" name="action" value="opentt_unified_onboarding_action">';
        echo '<input type="hidden" name="opentt_onboarding_action" value="skip">';
        echo '<button type="submit" class="button">Preskoči setup</button>';
        echo '</form>';

        echo '</div>';
        echo '</div>';
    }

    public static function handle_onboarding_action()
    {
        \OpenTT\Unified\WordPress\AdminSettingsActionManager::handleOnboardingAction([
            'capability' => self::CAP,
            'action_key' => 'opentt_onboarding_action',
            'state_option_key' => self::OPTION_ONBOARDING_STATE,
            'redirect_transient_key' => 'opentt_unified_onboarding_redirect',
            'dashboard_url' => admin_url('admin.php?page=stkb-unified'),
        ]);
    }

    public static function handle_delete_all_data()
    {
        \OpenTT\Unified\WordPress\AdminSettingsActionManager::handleDeleteAllData([
            'capability' => self::CAP,
            'confirm_phrase_key' => 'opentt_confirm_phrase',
            'settings_url' => admin_url('admin.php?page=stkb-unified-settings'),
            'post_types' => ['pravilo_takmicenja', 'sezona', 'liga', 'igrac', 'klub'],
            'taxonomies' => ['kolo', 'liga_sezona'],
            'option_keys' => [
                self::OPTION_SCHEMA_VERSION,
                self::OPTION_MIGRATION_STATE,
                self::OPTION_VALIDATION_REPORT,
                self::OPTION_LEAGUE_SEASON_VALIDATION_REPORT,
                self::OPTION_LEGACY_ID_MAP,
                self::OPTION_PLAYER_CITIZENSHIP_BACKFILL_DONE,
                self::OPTION_CUSTOM_SHORTCODE_CSS,
                self::OPTION_CUSTOM_SHORTCODE_CSS_MAP,
                self::OPTION_VISUAL_SETTINGS,
                self::OPTION_ADMIN_UI_LANGUAGE,
                self::OPTION_DEFAULT_PAGES_SETUP_DONE,
                self::OPTION_ONBOARDING_STATE,
                self::OPTION_IMPORT_PREVIEW,
            ],
            'transient_keys' => ['opentt_unified_onboarding_redirect'],
        ]);
    }

    public static function handle_save_settings_admin()
    {
        \OpenTT\Unified\WordPress\AdminSettingsActionManager::handleSaveSettings([
            'capability' => self::CAP,
            'section_key' => 'opentt_settings_section',
            'css_action_key' => 'opentt_css_action',
            'settings_url' => admin_url('admin.php?page=stkb-unified-settings'),
            'customize_url' => admin_url('admin.php?page=stkb-unified-customize'),
            'option_visual_settings' => self::OPTION_VISUAL_SETTINGS,
            'option_custom_css' => self::OPTION_CUSTOM_SHORTCODE_CSS,
            'option_custom_css_map' => self::OPTION_CUSTOM_SHORTCODE_CSS_MAP,
            'option_admin_ui_language' => self::OPTION_ADMIN_UI_LANGUAGE,
            'available_languages' => self::get_available_admin_ui_languages(),
        ]);
    }

    public static function render_admin_notice()
    {
        \OpenTT\Unified\WordPress\AdminNoticeManager::renderFromRequest();
    }

    public static function handle_save_match()
    {
        OpenTT_Unified_Admin_Match_Actions::handle_save_match();
    }

    public static function handle_delete_match()
    {
        OpenTT_Unified_Admin_Match_Actions::handle_delete_match();
    }

    public static function handle_toggle_featured_match_admin()
    {
        OpenTT_Unified_Admin_Match_Actions::handle_toggle_featured_match_admin();
    }

    public static function handle_toggle_live_match_admin()
    {
        OpenTT_Unified_Admin_Match_Actions::handle_toggle_live_match_admin();
    }

    public static function handle_finish_live_match_admin()
    {
        OpenTT_Unified_Admin_Match_Actions::handle_finish_live_match_admin();
    }

    public static function handle_delete_matches_bulk_admin()
    {
        OpenTT_Unified_Admin_Match_Actions::handle_delete_matches_bulk_admin();
    }

    public static function handle_save_game()
    {
        OpenTT_Unified_Admin_Match_Actions::handle_save_game();
    }

    public static function handle_save_games_batch()
    {
        OpenTT_Unified_Admin_Match_Actions::handle_save_games_batch();
    }

    public static function handle_delete_game()
    {
        OpenTT_Unified_Admin_Match_Actions::handle_delete_game();
    }

    public static function handle_save_set()
    {
        OpenTT_Unified_Admin_Match_Actions::handle_save_set();
    }

    public static function handle_delete_set()
    {
        OpenTT_Unified_Admin_Match_Actions::handle_delete_set();
    }

    public static function handle_save_club_admin()
    {
        OpenTT_Unified_Admin_Club_Player_Actions::handle_save_club_admin();
    }

    public static function handle_delete_club_admin()
    {
        OpenTT_Unified_Admin_Club_Player_Actions::handle_delete_club_admin();
    }

    public static function handle_delete_clubs_bulk_admin()
    {
        OpenTT_Unified_Admin_Club_Player_Actions::handle_delete_clubs_bulk_admin();
    }

    public static function handle_save_player_admin()
    {
        OpenTT_Unified_Admin_Club_Player_Actions::handle_save_player_admin();
    }

    public static function handle_delete_player_admin()
    {
        OpenTT_Unified_Admin_Club_Player_Actions::handle_delete_player_admin();
    }

    public static function handle_delete_players_bulk_admin()
    {
        OpenTT_Unified_Admin_Club_Player_Actions::handle_delete_players_bulk_admin();
    }

    public static function handle_save_league_admin()
    {
        \OpenTT\Unified\WordPress\LeagueSeasonAdminManager::handleSaveLeague(self::CAP);
    }

    public static function handle_delete_league_admin()
    {
        \OpenTT\Unified\WordPress\LeagueSeasonAdminManager::handleDeleteLeague(self::CAP);
    }

    public static function handle_save_season_admin()
    {
        \OpenTT\Unified\WordPress\LeagueSeasonAdminManager::handleSaveSeason(self::CAP);
    }

    public static function handle_delete_season_admin()
    {
        \OpenTT\Unified\WordPress\LeagueSeasonAdminManager::handleDeleteSeason(self::CAP);
    }

    public static function handle_save_competition_rule_admin()
    {
        \OpenTT\Unified\WordPress\CompetitionRuleAdminManager::handleSave(self::CAP, [
            'find_rule_by_slugs' => static function ($liga_slug, $sezona_slug) {
                return \OpenTT\Unified\WordPress\CompetitionRuleStore::findBySlugs($liga_slug, $sezona_slug);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'ensure_league_entity' => static function ($league_slug, $league_name) {
                \OpenTT\Unified\WordPress\CompetitionRuleStore::ensureLeagueEntity(
                    $league_slug,
                    $league_name,
                    static function ($slug) {
                        return self::slug_to_title($slug);
                    }
                );
            },
            'ensure_season_entity' => static function ($season_slug, $season_name) {
                \OpenTT\Unified\WordPress\CompetitionRuleStore::ensureSeasonEntity(
                    $season_slug,
                    $season_name,
                    static function ($slug) {
                        return self::slug_to_title($slug);
                    }
                );
            },
            'normalize_federation' => static function ($code) {
                return self::normalize_competition_federation($code);
            },
        ]);
    }

    public static function handle_delete_competition_rule_admin()
    {
        \OpenTT\Unified\WordPress\CompetitionRuleAdminManager::handleDelete(self::CAP);
    }

    public static function handle_migrate_competition_rules()
    {
        \OpenTT\Unified\WordPress\CompetitionRuleAdminManager::handleMigrate(
            self::CAP,
            static function () {
                return self::migrate_competition_rules_from_existing_data();
            }
        );
    }

    public static function handle_migrate_league_season_slugs()
    {
        \OpenTT\Unified\WordPress\MigrationActionsManager::handleMigrateLeagueSeasonSlugs(
            self::CAP,
            self::OPTION_LEAGUE_SEASON_VALIDATION_REPORT,
            static function () {
                return self::validate_league_season_migration_from_matches();
            },
            static function () {
                return self::migrate_league_season_from_matches();
            }
        );
    }

    public static function handle_validate_league_season_migration()
    {
        \OpenTT\Unified\WordPress\MigrationActionsManager::handleValidateLeagueSeasonMigration(
            self::CAP,
            self::OPTION_LEAGUE_SEASON_VALIDATION_REPORT,
            static function () {
                return self::validate_league_season_migration_from_matches();
            }
        );
    }

    public static function handle_validate_import()
    {
        \OpenTT\Unified\WordPress\MigrationActionsManager::handleValidateImport(
            self::CAP,
            self::OPTION_VALIDATION_REPORT,
            static function () {
                return self::validate_legacy_import();
            }
        );
    }

    public static function handle_reset_migration()
    {
        \OpenTT\Unified\WordPress\MigrationActionsManager::handleResetMigration(
            self::CAP,
            self::OPTION_MIGRATION_STATE
        );
    }

    public static function handle_repair_relations()
    {
        \OpenTT\Unified\WordPress\MigrationActionsManager::handleRepairRelations(
            self::CAP,
            static function () {
                return self::repair_legacy_relations();
            }
        );
    }

    public static function handle_cleanup_placeholders()
    {
        \OpenTT\Unified\WordPress\MigrationActionsManager::handleCleanupPlaceholders(
            self::CAP,
            static function () {
                return self::cleanup_placeholder_relations();
            }
        );
    }

    public static function handle_migrate_batch()
    {
        \OpenTT\Unified\WordPress\MigrationActionsManager::handleMigrateBatch(
            self::CAP,
            static function () {
                self::maybe_migrate_schema();
            },
            static function ($batch) {
                return self::migrate_batch($batch);
            }
        );
    }

    private static function maybe_migrate_schema()
    {
        \OpenTT\Unified\Infrastructure\SchemaMigrationManager::ensureSchemaAndLegacySync([
            'schema_version_option_key' => self::OPTION_SCHEMA_VERSION,
            'schema_version' => self::SCHEMA_VERSION,
            'sync_state_key' => 'opentt_core_schema_sync',
            'table_name_resolver' => static function ($entity, $legacy) {
                return self::db_table_name($entity, (bool) $legacy);
            },
            'table_exists' => static function ($table_name) {
                return self::table_exists($table_name);
            },
            'reset_cache' => static function () {
                \OpenTT\Unified\Infrastructure\DbTableResolver::resetCache();
            },
        ]);
    }

    private static function migrate_batch($batch_size)
    {
        global $wpdb;

        $state = self::get_migration_state();
        $offset = max(0, intval($state['offset']));

        $query = new WP_Query([
            'post_type' => 'utakmica',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $migrated_matches = 0;
        $migrated_games = 0;
        $migrated_sets = 0;

        if (!empty($query->posts)) {
            foreach ($query->posts as $match_post_id) {
                $db_match_id = self::migrate_single_match((int) $match_post_id);
                if ($db_match_id > 0) {
                    $migrated_matches++;
                    $res = self::migrate_games_for_match((int) $match_post_id, $db_match_id);
                    $migrated_games += $res['games'];
                    $migrated_sets += $res['sets'];
                }
            }
        }

        update_option(self::OPTION_MIGRATION_STATE, ['offset' => $offset + count($query->posts)], false);

        return [
            'matches' => $migrated_matches,
            'games' => $migrated_games,
            'sets' => $migrated_sets,
        ];
    }

    private static function migrate_single_match($post_id)
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');

        $slug = get_post_field('post_name', $post_id);
        $liga_terms = wp_get_post_terms($post_id, 'liga_sezona', ['fields' => 'slugs']);
        $kolo_terms = wp_get_post_terms($post_id, 'kolo', ['fields' => 'slugs']);

        $liga_slug = !empty($liga_terms[0]) ? (string) $liga_terms[0] : '';
        $kolo_slug = !empty($kolo_terms[0]) ? (string) $kolo_terms[0] : '';

        if ($liga_slug === '' || $kolo_slug === '') {
            return 0;
        }

        $home_club = OpenTT_Unified_Readonly_Helpers::extract_id(get_post_meta($post_id, 'klub_domacina', true));
        $away_club = OpenTT_Unified_Readonly_Helpers::extract_id(get_post_meta($post_id, 'klub_gostiju', true));

        $home_score = intval(get_post_meta($post_id, 'rezultat_domacina', true));
        $away_score = intval(get_post_meta($post_id, 'rezultat_gostiju', true));
        $played = intval(get_post_meta($post_id, 'odigrana', true)) ? 1 : 0;

        $date_raw = (string) get_post_meta($post_id, 'datum_utakmice', true);
        $date_sql = OpenTT_Unified_Readonly_Helpers::parse_date_to_sql($date_raw);
        $now = current_time('mysql');

        $existing_id = intval($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE legacy_post_id=%d LIMIT 1",
            $post_id
        )));

        $data = [
            'legacy_post_id' => $post_id,
            'slug' => $slug ?: ('utakmica-' . $post_id),
            'liga_slug' => $liga_slug,
            'sezona_slug' => '',
            'kolo_slug' => $kolo_slug,
            'home_club_post_id' => $home_club,
            'away_club_post_id' => $away_club,
            'home_score' => $home_score,
            'away_score' => $away_score,
            'played' => $played,
            'match_date' => $date_sql,
            'updated_at' => $now,
        ];

        if ($existing_id > 0) {
            $wpdb->update($table, $data, ['id' => $existing_id]);
            return $existing_id;
        }

        $data['created_at'] = $now;
        $wpdb->insert($table, $data);
        return intval($wpdb->insert_id);
    }

    private static function migrate_games_for_match($legacy_match_id, $db_match_id)
    {
        global $wpdb;
        $games_table = OpenTT_Unified_Core::db_table('games');
        $sets_table = OpenTT_Unified_Core::db_table('sets');

        $q = new WP_Query([
            'post_type' => 'partija',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'posts_per_page' => -1,
            'meta_query' => [[
                'key' => 'povezana_utakmica',
                'value' => $legacy_match_id,
                'compare' => '=',
            ]],
            'meta_key' => 'redni_broj',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $migrated_games = 0;
        $migrated_sets = 0;

        foreach ($q->posts as $game_post_id) {
            $game_post_id = (int) $game_post_id;
            $existing_game_id = intval($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$games_table} WHERE legacy_post_id=%d LIMIT 1",
                $game_post_id
            )));

            $home_players = OpenTT_Unified_Readonly_Helpers::extract_ids(get_post_meta($game_post_id, 'igrac_domacin', true));
            $away_players = OpenTT_Unified_Readonly_Helpers::extract_ids(get_post_meta($game_post_id, 'igrac_gost', true));

            $is_doubles = (count($home_players) > 1 || count($away_players) > 1) ? 1 : 0;
            $order_no = intval(get_post_meta($game_post_id, 'redni_broj', true));
            $home_sets = intval(get_post_meta($game_post_id, 'domacin_set', true));
            $away_sets = intval(get_post_meta($game_post_id, 'gost_set', true));
            $slug = get_post_field('post_name', $game_post_id);
            $now = current_time('mysql');

            $game_data = [
                'legacy_post_id' => $game_post_id,
                'match_id' => $db_match_id,
                'order_no' => $order_no > 0 ? $order_no : 0,
                'slug' => $slug ?: ('partija-' . $game_post_id),
                'is_doubles' => $is_doubles,
                'home_player_post_id' => isset($home_players[0]) ? (int) $home_players[0] : null,
                'away_player_post_id' => isset($away_players[0]) ? (int) $away_players[0] : null,
                'home_player2_post_id' => isset($home_players[1]) ? (int) $home_players[1] : null,
                'away_player2_post_id' => isset($away_players[1]) ? (int) $away_players[1] : null,
                'home_sets' => $home_sets,
                'away_sets' => $away_sets,
                'updated_at' => $now,
            ];

            if ($existing_game_id > 0) {
                $wpdb->update($games_table, $game_data, ['id' => $existing_game_id]);
                $db_game_id = $existing_game_id;
            } else {
                $game_data['created_at'] = $now;
                $wpdb->insert($games_table, $game_data);
                $db_game_id = intval($wpdb->insert_id);
            }

            if ($db_game_id <= 0) {
                continue;
            }

            $migrated_games++;

            $wpdb->delete($sets_table, ['game_id' => $db_game_id]);

            $set_rows = self::get_game_sets_rows($game_post_id);
            $set_no = 1;
            foreach ($set_rows as $row) {
                $home_points = isset($row['poeni_domacin']) ? intval($row['poeni_domacin']) : 0;
                $away_points = isset($row['poeni_gost']) ? intval($row['poeni_gost']) : 0;

                $wpdb->insert($sets_table, [
                    'game_id' => $db_game_id,
                    'set_no' => $set_no,
                    'home_points' => $home_points,
                    'away_points' => $away_points,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $set_no++;
                $migrated_sets++;
            }
        }

        return [
            'games' => $migrated_games,
            'sets' => $migrated_sets,
        ];
    }

    private static function get_migration_state()
    {
        $state = get_option(self::OPTION_MIGRATION_STATE, []);
        if (!is_array($state)) {
            $state = [];
        }

        return [
            'offset' => isset($state['offset']) ? max(0, intval($state['offset'])) : 0,
        ];
    }

    private static function get_migration_counts()
    {
        global $wpdb;

        $matches = OpenTT_Unified_Core::db_table('matches');
        $games = OpenTT_Unified_Core::db_table('games');
        $sets = OpenTT_Unified_Core::db_table('sets');
        $posts = $wpdb->posts;
        $legacy_matches = intval($wpdb->get_var("SELECT COUNT(*) FROM {$posts} WHERE post_type='utakmica'"));
        $legacy_games = intval($wpdb->get_var("SELECT COUNT(*) FROM {$posts} WHERE post_type='partija'"));

        $db_matches = self::table_exists($matches) ? intval($wpdb->get_var("SELECT COUNT(*) FROM {$matches}")) : 0;
        $db_games = self::table_exists($games) ? intval($wpdb->get_var("SELECT COUNT(*) FROM {$games}")) : 0;
        $db_sets = self::table_exists($sets) ? intval($wpdb->get_var("SELECT COUNT(*) FROM {$sets}")) : 0;

        return [
            'legacy_matches' => $legacy_matches,
            'legacy_games' => $legacy_games,
            'db_matches' => $db_matches,
            'db_games' => $db_games,
            'db_sets' => $db_sets,
        ];
    }

    private static function validate_legacy_import()
    {
        $issues = [];
        $max_issues = 120;
        $checked_matches = 0;
        $checked_games = 0;

        $matches = get_posts([
            'post_type' => 'utakmica',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'numberposts' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        $games = get_posts([
            'post_type' => 'partija',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'numberposts' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        if (empty($matches)) {
            self::push_issue($issues, $max_issues, 'no_matches', 'Nijedna legacy utakmica nije pronađena (CPT: utakmica).');
        }
        if (empty($games)) {
            self::push_issue($issues, $max_issues, 'no_games', 'Nijedna legacy partija nije pronađena (CPT: partija).');
        }

        foreach ($matches as $match_id) {
            $checked_matches++;

            $liga_terms = wp_get_post_terms($match_id, 'liga_sezona', ['fields' => 'ids']);
            $kolo_terms = wp_get_post_terms($match_id, 'kolo', ['fields' => 'ids']);
            if (empty($liga_terms)) {
                self::push_issue($issues, $max_issues, 'match_missing_liga', 'Utakmica ID ' . intval($match_id) . ' nema liga_sezona termin.');
            }
            if (empty($kolo_terms)) {
                self::push_issue($issues, $max_issues, 'match_missing_kolo', 'Utakmica ID ' . intval($match_id) . ' nema kolo termin.');
            }

            $home = OpenTT_Unified_Readonly_Helpers::extract_id(get_post_meta($match_id, 'klub_domacina', true));
            $away = OpenTT_Unified_Readonly_Helpers::extract_id(get_post_meta($match_id, 'klub_gostiju', true));
            if ($home <= 0 || get_post_type($home) !== 'klub') {
                self::push_issue($issues, $max_issues, 'match_bad_home_club', 'Utakmica ID ' . intval($match_id) . ' ima neispravan klub_domacina.');
            }
            if ($away <= 0 || get_post_type($away) !== 'klub') {
                self::push_issue($issues, $max_issues, 'match_bad_away_club', 'Utakmica ID ' . intval($match_id) . ' ima neispravan klub_gostiju.');
            }
        }

        foreach ($games as $game_id) {
            $checked_games++;

            $match_id = OpenTT_Unified_Readonly_Helpers::extract_id(get_post_meta($game_id, 'povezana_utakmica', true));
            if ($match_id <= 0 || get_post_type($match_id) !== 'utakmica') {
                self::push_issue($issues, $max_issues, 'game_bad_match_ref', 'Partija ID ' . intval($game_id) . ' ima neispravno povezanu utakmicu.');
            }

            $home_players = OpenTT_Unified_Readonly_Helpers::extract_ids(get_post_meta($game_id, 'igrac_domacin', true));
            $away_players = OpenTT_Unified_Readonly_Helpers::extract_ids(get_post_meta($game_id, 'igrac_gost', true));
            if (empty($home_players)) {
                self::push_issue($issues, $max_issues, 'game_no_home_player', 'Partija ID ' . intval($game_id) . ' nema igrac_domacin.');
            }
            if (empty($away_players)) {
                self::push_issue($issues, $max_issues, 'game_no_away_player', 'Partija ID ' . intval($game_id) . ' nema igrac_gost.');
            }

            foreach (array_merge($home_players, $away_players) as $player_id) {
                if (get_post_type($player_id) !== 'igrac') {
                    self::push_issue($issues, $max_issues, 'game_bad_player_ref', 'Partija ID ' . intval($game_id) . ' referencira neispravnog igrača ID ' . intval($player_id) . '.');
                    break;
                }
            }

            $set_rows = self::get_game_sets_rows($game_id);
            $setovi_raw = maybe_unserialize(get_post_meta($game_id, 'setovi', true));
            if (!empty($setovi_raw) && empty($set_rows)) {
                self::push_issue($issues, $max_issues, 'game_bad_sets_format', 'Partija ID ' . intval($game_id) . ' ima setovi u neočekivanom formatu.');
            }
        }

        return [
            'ok' => empty($issues),
            'generated_at' => current_time('mysql'),
            'checked_matches' => $checked_matches,
            'checked_games' => $checked_games,
            'issue_count' => count($issues),
            'issues' => $issues,
        ];
    }

    private static function repair_legacy_relations()
    {
        $fixed = 0;

        $matches = get_posts([
            'post_type' => 'utakmica',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        foreach ($matches as $match_id) {
            $match_id = (int) $match_id;

            $home_raw = get_post_meta($match_id, 'klub_domacina', true);
            $away_raw = get_post_meta($match_id, 'klub_gostiju', true);

            $home_resolved = self::resolve_or_create_reference_id('klub', $home_raw);
            $away_resolved = self::resolve_or_create_reference_id('klub', $away_raw);

            if ($home_resolved > 0 && intval(OpenTT_Unified_Readonly_Helpers::extract_id($home_raw)) !== $home_resolved) {
                update_post_meta($match_id, 'klub_domacina', $home_resolved);
                $fixed++;
            }

            if ($away_resolved > 0 && intval(OpenTT_Unified_Readonly_Helpers::extract_id($away_raw)) !== $away_resolved) {
                update_post_meta($match_id, 'klub_gostiju', $away_resolved);
                $fixed++;
            }
        }

        $games = get_posts([
            'post_type' => 'partija',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        foreach ($games as $game_id) {
            $game_id = (int) $game_id;

            $home_raw = get_post_meta($game_id, 'igrac_domacin', true);
            $away_raw = get_post_meta($game_id, 'igrac_gost', true);

            $home_ids = self::resolve_reference_ids('igrac', $home_raw, 2);
            $away_ids = self::resolve_reference_ids('igrac', $away_raw, 2);

            if (!empty($home_ids)) {
                update_post_meta($game_id, 'igrac_domacin', array_values($home_ids));
                $fixed++;
            }
            if (!empty($away_ids)) {
                update_post_meta($game_id, 'igrac_gost', array_values($away_ids));
                $fixed++;
            }

            $sets_raw = maybe_unserialize(get_post_meta($game_id, 'setovi', true));
            if (!self::is_standard_sets_array($sets_raw)) {
                $rows = self::get_game_sets_rows($game_id);
                if (!empty($rows)) {
                    update_post_meta($game_id, 'setovi', array_values($rows));
                    $fixed++;
                } elseif (!empty($sets_raw)) {
                    self::clear_game_sets_meta($game_id);
                    $fixed++;
                }
            }
        }

        return $fixed;
    }

    private static function cleanup_placeholder_relations()
    {
        $cleaned = 0;

        $matches = get_posts([
            'post_type' => 'utakmica',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        foreach ($matches as $match_id) {
            $match_id = (int) $match_id;
            foreach (['klub_domacina', 'klub_gostiju'] as $key) {
                $raw = get_post_meta($match_id, $key, true);
                $legacy_id = self::extract_legacy_ref_id_from_post_id(OpenTT_Unified_Readonly_Helpers::extract_id($raw));
                if ($legacy_id > 0) {
                    update_post_meta($match_id, $key, $legacy_id);
                    $cleaned++;
                }
            }
        }

        $games = get_posts([
            'post_type' => 'partija',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        foreach ($games as $game_id) {
            $game_id = (int) $game_id;
            foreach (['igrac_domacin', 'igrac_gost'] as $key) {
                $ids = OpenTT_Unified_Readonly_Helpers::extract_ids(get_post_meta($game_id, $key, true));
                if (empty($ids)) {
                    continue;
                }

                $rewritten = [];
                $changed = false;
                foreach ($ids as $id) {
                    $legacy_id = self::extract_legacy_ref_id_from_post_id($id);
                    if ($legacy_id > 0) {
                        $rewritten[] = $legacy_id;
                        $changed = true;
                    } else {
                        $rewritten[] = $id;
                    }
                }

                if ($changed) {
                    update_post_meta($game_id, $key, array_values(array_unique($rewritten)));
                    $cleaned++;
                }
            }
        }

        $placeholder_posts = get_posts([
            'post_type' => ['klub', 'igrac'],
            'post_status' => ['publish', 'private', 'draft', 'pending', 'trash'],
            'numberposts' => -1,
            'meta_query' => [[
                'key' => '_opentt_legacy_ref_id',
                'compare' => 'EXISTS',
            ]],
            'fields' => 'ids',
        ]);

        foreach ($placeholder_posts as $pid) {
            wp_delete_post((int) $pid, true);
            $cleaned++;
        }

        delete_option(self::OPTION_LEGACY_ID_MAP);
        return $cleaned;
    }

    private static function resolve_or_create_reference_id($post_type, $raw)
    {
        $id = OpenTT_Unified_Readonly_Helpers::extract_id($raw);
        if ($id > 0 && get_post_type($id) === $post_type) {
            return $id;
        }

        if ($id <= 0) {
            return 0;
        }

        $mapped = self::get_mapped_id($post_type, $id);
        if ($mapped > 0 && get_post_type($mapped) === $post_type) {
            return $mapped;
        }

        $existing = get_posts([
            'post_type' => $post_type,
            'post_status' => ['publish', 'private', 'draft', 'pending'],
            'numberposts' => 1,
            'meta_query' => [[
                'key' => '_opentt_legacy_ref_id',
                'value' => $id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ]],
            'fields' => 'ids',
        ]);

        if (!empty($existing[0])) {
            $existing_id = (int) $existing[0];
            self::set_mapped_id($post_type, $id, $existing_id);
            return $existing_id;
        }

        if (!in_array($post_type, ['klub', 'igrac'], true)) {
            return 0;
        }

        $title_prefix = $post_type === 'igrac' ? 'Legacy igrač #' : 'Legacy klub #';
        $slug_prefix = $post_type === 'igrac' ? 'legacy-igrac-' : 'legacy-klub-';

        $new_id = wp_insert_post([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'post_title' => $title_prefix . $id,
            'post_name' => $slug_prefix . $id,
        ]);

        if (!is_wp_error($new_id) && intval($new_id) > 0) {
            $new_id = intval($new_id);
            update_post_meta($new_id, '_opentt_legacy_ref_id', $id);
            self::set_mapped_id($post_type, $id, $new_id);
            return $new_id;
        }

        return 0;
    }

    private static function resolve_reference_ids($post_type, $raw, $max_items)
    {
        $ids = OpenTT_Unified_Readonly_Helpers::extract_ids($raw);
        if (empty($ids)) {
            return [];
        }

        $resolved = [];
        foreach ($ids as $id) {
            $rid = self::resolve_or_create_reference_id($post_type, $id);
            if ($rid > 0) {
                $resolved[] = $rid;
            }
            if (count($resolved) >= $max_items) {
                break;
            }
        }

        return array_values(array_unique($resolved));
    }

    private static function get_mapped_id($post_type, $old_id)
    {
        $map = get_option(self::OPTION_LEGACY_ID_MAP, []);
        if (!is_array($map)) {
            return 0;
        }
        if (empty($map[$post_type]) || !is_array($map[$post_type])) {
            return 0;
        }
        return isset($map[$post_type][$old_id]) ? intval($map[$post_type][$old_id]) : 0;
    }

    private static function set_mapped_id($post_type, $old_id, $new_id)
    {
        $map = get_option(self::OPTION_LEGACY_ID_MAP, []);
        if (!is_array($map)) {
            $map = [];
        }
        if (empty($map[$post_type]) || !is_array($map[$post_type])) {
            $map[$post_type] = [];
        }
        $map[$post_type][(int) $old_id] = (int) $new_id;
        update_option(self::OPTION_LEGACY_ID_MAP, $map, false);
    }

    private static function extract_legacy_ref_id_from_post_id($post_id)
    {
        $post_id = intval($post_id);
        if ($post_id <= 0) {
            return 0;
        }
        $legacy_id = intval(get_post_meta($post_id, '_opentt_legacy_ref_id', true));
        return $legacy_id > 0 ? $legacy_id : 0;
    }

    private static function push_issue(&$issues, $max, $code, $message)
    {
        if (count($issues) >= $max) {
            return;
        }
        $issues[] = [
            'code' => (string) $code,
            'message' => (string) $message,
        ];
    }

    public static function get_competition_rule_data($liga_slug, $sezona_slug)
    {
        return \OpenTT\Unified\WordPress\CompetitionRuleProfile::getRuleData($liga_slug, $sezona_slug);
    }

    private static function match_competition_format($liga_slug, $sezona_slug)
    {
        return \OpenTT\Unified\WordPress\CompetitionRuleProfile::resolveMatchFormat(
            $liga_slug,
            $sezona_slug,
            'format_a'
        );
    }

    private static function migrate_competition_rules_from_existing_data()
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');
        if (!self::table_exists($table)) {
            return ['rules' => 0];
        }

        $pairs = $wpdb->get_results("SELECT DISTINCT liga_slug, sezona_slug FROM {$table} WHERE liga_slug <> '' AND sezona_slug <> ''");
        if (!$pairs) {
            return ['rules' => 0];
        }

        $count = 0;
        foreach ($pairs as $pair) {
            $liga_slug = sanitize_title((string) ($pair->liga_slug ?? ''));
            $sezona_slug = sanitize_title((string) ($pair->sezona_slug ?? ''));
            if ($liga_slug === '' || $sezona_slug === '') {
                continue;
            }

            $existing = \OpenTT\Unified\WordPress\CompetitionRuleStore::findBySlugs($liga_slug, $sezona_slug);
            $season_posts = get_posts([
                'post_type' => 'sezona',
                'name' => $sezona_slug,
                'numberposts' => 1,
                'post_status' => ['publish', 'draft', 'pending', 'private'],
            ]);
            $season_id = !empty($season_posts) ? (int) $season_posts[0]->ID : 0;
            $promo = $season_id > 0 ? (int) get_post_meta($season_id, 'promocija_broj', true) : 0;
            $releg = $season_id > 0 ? (int) get_post_meta($season_id, 'ispadanje_broj', true) : 0;
            $scoring = $season_id > 0 ? (string) get_post_meta($season_id, 'bodovanje_tip', true) : '2-1';
            $format = $season_id > 0 ? (string) get_post_meta($season_id, 'format_partija', true) : 'format_a';
            if ($scoring === '') {
                $scoring = '2-1';
            }
            if ($format === '') {
                $format = 'format_a';
            }
            if (!in_array($format, ['format_a', 'format_b'], true)) {
                $format = 'format_a';
            }

            // Inferencija iz već migriranih partija: ako postoji dubl na #7 -> format_b, #4 -> format_a.
            $games_table = OpenTT_Unified_Core::db_table('games');
            $matches_table = OpenTT_Unified_Core::db_table('matches');
            if (self::table_exists($games_table) && self::table_exists($matches_table)) {
                $doubles_orders = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT g.order_no
                     FROM {$games_table} g
                     INNER JOIN {$matches_table} m ON m.id = g.match_id
                     WHERE m.liga_slug=%s AND m.sezona_slug=%s AND g.is_doubles=1",
                    $liga_slug,
                    $sezona_slug
                ));
                $doubles_orders = array_map('intval', is_array($doubles_orders) ? $doubles_orders : []);
                if (in_array(7, $doubles_orders, true)) {
                    $format = 'format_b';
                } elseif (in_array(4, $doubles_orders, true)) {
                    $format = 'format_a';
                }
            }

            if ($existing) {
                $rule_id = (int) $existing->ID;
            } else {
                $rule_id = wp_insert_post([
                    'post_type' => 'pravilo_takmicenja',
                    'post_status' => 'publish',
                    'post_title' => self::slug_to_title($liga_slug) . ' / ' . self::slug_to_title($sezona_slug),
                ], true);
                if (!$rule_id || is_wp_error($rule_id)) {
                    continue;
                }
            }

            update_post_meta($rule_id, 'opentt_competition_league_slug', $liga_slug);
            update_post_meta($rule_id, 'opentt_competition_season_slug', $sezona_slug);
            update_post_meta($rule_id, 'opentt_competition_rank', 3);
            update_post_meta($rule_id, 'opentt_competition_promotion_slots', $promo);
            update_post_meta($rule_id, 'opentt_competition_promotion_playoff_slots', 0);
            update_post_meta($rule_id, 'opentt_competition_relegation_slots', $releg);
            update_post_meta($rule_id, 'opentt_competition_relegation_playoff_slots', 0);
            update_post_meta($rule_id, 'opentt_competition_scoring_type', $scoring);
            update_post_meta($rule_id, 'opentt_competition_match_format', $format);
            $count++;
        }

        return ['rules' => $count];
    }

    private static function migrate_league_season_from_matches()
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');
        if (!self::table_exists($table)) {
            return ['leagues' => 0, 'seasons' => 0];
        }

        $rows = $wpdb->get_results("SELECT id, liga_slug, sezona_slug FROM {$table} WHERE liga_slug <> '' ORDER BY id ASC");
        if (!$rows) {
            return ['leagues' => 0, 'seasons' => 0];
        }

        $created_leagues = 0;
        $created_seasons = 0;
        $league_cache = [];
        $seen_pairs = [];

        foreach ($rows as $row) {
            $raw_liga_slug = sanitize_title((string) ($row->liga_slug ?? ''));
            $raw_sezona_slug = sanitize_title((string) ($row->sezona_slug ?? ''));
            $parsed = self::parse_legacy_liga_sezona($raw_liga_slug, $raw_sezona_slug);
            $liga_slug = $parsed['league_slug'];
            $sezona_slug = $parsed['season_slug'];
            if ($liga_slug === '') {
                continue;
            }

            // Normalizuj mečeve koji su imali sezonu zalepljenu u liga_slug.
            if (
                (string) $raw_liga_slug !== (string) $liga_slug
                || (string) $raw_sezona_slug !== (string) $sezona_slug
            ) {
                $wpdb->update(
                    $table,
                    [
                        'liga_slug' => $liga_slug,
                        'sezona_slug' => $sezona_slug,
                    ],
                    ['id' => (int) $row->id]
                );
            }

            $pair_key = $liga_slug . '||' . $sezona_slug;
            if (isset($seen_pairs[$pair_key])) {
                continue;
            }
            $seen_pairs[$pair_key] = true;

            if (!isset($league_cache[$liga_slug])) {
                $league = get_posts([
                    'post_type' => 'liga',
                    'name' => $liga_slug,
                    'numberposts' => 1,
                    'post_status' => ['publish', 'draft', 'pending', 'private'],
                ]);
                if (!empty($league)) {
                    $league_cache[$liga_slug] = (int) $league[0]->ID;
                } else {
                    $league_id = wp_insert_post([
                        'post_type' => 'liga',
                        'post_status' => 'publish',
                        'post_title' => self::slug_to_title($liga_slug),
                        'post_name' => $liga_slug,
                    ], true);
                    if ($league_id && !is_wp_error($league_id)) {
                        $league_cache[$liga_slug] = (int) $league_id;
                        $created_leagues++;
                    } else {
                        $league_cache[$liga_slug] = 0;
                    }
                }
            }

            $league_id = (int) ($league_cache[$liga_slug] ?? 0);
            if ($league_id <= 0 || $sezona_slug === '') {
                continue;
            }

            $season_posts = get_posts([
                'post_type' => 'sezona',
                'name' => $sezona_slug,
                'numberposts' => 1,
                'post_status' => ['publish', 'draft', 'pending', 'private'],
            ]);
            if (!empty($season_posts)) {
                continue;
            }

            $season_id = wp_insert_post([
                'post_type' => 'sezona',
                'post_status' => 'publish',
                'post_title' => self::slug_to_title($sezona_slug),
                'post_name' => $sezona_slug,
            ], true);
            if ($season_id && !is_wp_error($season_id)) {
                $created_seasons++;
            }
        }

        return [
            'leagues' => $created_leagues,
            'seasons' => $created_seasons,
        ];
    }

    private static function validate_league_season_migration_from_matches()
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');
        $issues = [];
        $max_issues = 120;
        $checked_pairs = 0;

        if (!self::table_exists($table)) {
            self::push_issue($issues, $max_issues, 'table_missing', 'Tabela mečeva ne postoji: ' . $table . '.');
            return [
                'ok' => false,
                'generated_at' => current_time('mysql'),
                'checked_pairs' => 0,
                'issue_count' => count($issues),
                'issues' => $issues,
            ];
        }

        $rows = $wpdb->get_results("SELECT id, liga_slug, sezona_slug FROM {$table} ORDER BY id ASC");
        if (!$rows) {
            self::push_issue($issues, $max_issues, 'no_matches', 'Nema utakmica u DB tabeli: ' . $table . '.');
            return [
                'ok' => false,
                'generated_at' => current_time('mysql'),
                'checked_pairs' => 0,
                'issue_count' => count($issues),
                'issues' => $issues,
            ];
        }

        foreach ($rows as $row) {
            $match_id = (int) $row->id;
            $raw_liga = sanitize_title((string) ($row->liga_slug ?? ''));
            $raw_sezona = sanitize_title((string) ($row->sezona_slug ?? ''));
            $parsed = self::parse_legacy_liga_sezona($raw_liga, $raw_sezona);
            $liga = $parsed['league_slug'];
            $sezona = $parsed['season_slug'];
            $checked_pairs++;

            if ($liga === '') {
                self::push_issue($issues, $max_issues, 'missing_league_slug', 'Utakmica ID ' . $match_id . ' nema liga_slug.');
                continue;
            }

            // NEDOSTAJUĆI liga/sezona entiteti nisu blokirajući problem:
            // upravo ih kreira korak "Migriraj legacy liga/sezona slugove".
            if ($sezona !== '' && \OpenTT\Unified\WordPress\CompetitionRuleCatalog::hasAnyRules() && !\OpenTT\Unified\WordPress\CompetitionRuleStore::findBySlugs($liga, $sezona)) {
                self::push_issue(
                    $issues,
                    $max_issues,
                    'competition_rule_missing',
                    'Utakmica ID ' . $match_id . ': ne postoje pravila takmičenja za kombinaciju liga/sezona "' . $liga . ' / ' . $sezona . '".'
                );
            }
        }

        return [
            'ok' => empty($issues),
            'generated_at' => current_time('mysql'),
            'checked_pairs' => $checked_pairs,
            'issue_count' => count($issues),
            'issues' => $issues,
        ];
    }

    private static function parse_legacy_liga_sezona($liga_slug, $sezona_slug)
    {
        return OpenTT_Unified_Readonly_Helpers::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
    }

    private static function slug_to_title($slug)
    {
        return OpenTT_Unified_Readonly_Helpers::slug_to_title($slug);
    }

    public static function competition_federation_options()
    {
        return \OpenTT\Unified\WordPress\CompetitionRuleCatalog::federationOptions();
    }

    public static function normalize_competition_federation($code)
    {
        return \OpenTT\Unified\WordPress\CompetitionRuleCatalog::normalizeFederation($code);
    }

    public static function competition_federation_data($code)
    {
        return \OpenTT\Unified\WordPress\CompetitionRuleCatalog::federationData($code);
    }

    private static function season_belongs_to_league($season_slug, $league_slug)
    {
        $season_slug = sanitize_title((string) $season_slug);
        $league_slug = sanitize_title((string) $league_slug);
        if ($season_slug === '' || $league_slug === '') {
            return false;
        }

        $season_posts = get_posts([
            'post_type' => 'sezona',
            'name' => $season_slug,
            'numberposts' => 1,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]);
        if (empty($season_posts)) {
            return false;
        }

        $season = $season_posts[0];
        $league_id = (int) get_post_meta($season->ID, 'povezana_liga', true);
        if ($league_id <= 0) {
            return false;
        }

        $league = get_post($league_id);
        if (!$league || $league->post_type !== 'liga') {
            return false;
        }

        return ((string) $league->post_name) === $league_slug;
    }

    private static function season_exists($season_slug)
    {
        $season_slug = sanitize_title((string) $season_slug);
        if ($season_slug === '') {
            return false;
        }
        $season_posts = get_posts([
            'post_type' => 'sezona',
            'name' => $season_slug,
            'numberposts' => 1,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]);
        return !empty($season_posts);
    }

    private static function leagues_dropdown_admin($name, $selected_slug, $required = true)
    {
        return OpenTT_Unified_Admin_Readonly_Helpers::leagues_dropdown_admin($name, $selected_slug, $required);
    }

    private static function competition_rule_id_by_slugs($liga_slug, $sezona_slug)
    {
        return \OpenTT\Unified\WordPress\CompetitionRuleCatalog::ruleIdBySlugs($liga_slug, $sezona_slug);
    }

    private static function competition_rules_dropdown_admin($name, $selected_id, $required = true)
    {
        return OpenTT_Unified_Admin_Readonly_Helpers::competition_rules_dropdown_admin($name, $selected_id, $required);
    }

    private static function seasons_dropdown_admin($name, $selected_slug, $league_slug, $required = false)
    {
        return OpenTT_Unified_Admin_Readonly_Helpers::seasons_dropdown_admin($name, $selected_slug, $required);
    }

    private static function clubs_dropdown_admin($name, $selected, $required)
    {
        return OpenTT_Unified_Admin_Readonly_Helpers::clubs_dropdown_admin($name, $selected, $required);
    }

    private static function municipality_dropdown_admin($name, $selected, $required)
    {
        return OpenTT_Unified_Admin_Readonly_Helpers::municipality_dropdown_admin($name, $selected, $required);
    }

    private static function country_dropdown_admin($name, $selected, $required)
    {
        return OpenTT_Unified_Admin_Readonly_Helpers::country_dropdown_admin($name, $selected, $required);
    }

    private static function municipality_options_admin()
    {
        return OpenTT_Unified_Admin_Readonly_Helpers::municipality_options_admin();
    }

    public static function country_label_by_code($code)
    {
        return OpenTT_Unified_Admin_Readonly_Helpers::country_label_by_code($code);
    }

    public static function country_flag_emoji($code)
    {
        return OpenTT_Unified_Admin_Readonly_Helpers::country_flag_emoji($code);
    }

    private static function codepoint_to_utf8($cp)
    {
        $cp = intval($cp);
        if ($cp <= 0x7F) {
            return chr($cp);
        }
        if ($cp <= 0x7FF) {
            return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
        }
        if ($cp <= 0xFFFF) {
            return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
        }
        if ($cp <= 0x10FFFF) {
            return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
        }
        return '';
    }

    private static function country_options_admin()
    {
        return OpenTT_Unified_Admin_Readonly_Helpers::country_options_admin();
    }

    public static function maybe_backfill_player_citizenship_default()
    {
        $done = get_option(self::OPTION_PLAYER_CITIZENSHIP_BACKFILL_DONE, '');
        if ($done === '1') {
            return;
        }

        $players = get_posts([
            'post_type' => 'igrac',
            'numberposts' => -1,
            'fields' => 'ids',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];

        if (!empty($players)) {
            foreach ($players as $player_id) {
                $player_id = (int) $player_id;
                if ($player_id <= 0) {
                    continue;
                }
                $val = strtoupper(sanitize_key((string) get_post_meta($player_id, 'drzavljanstvo', true)));
                if ($val === '') {
                    update_post_meta($player_id, 'drzavljanstvo', 'RS');
                }
            }
        }

        update_option(self::OPTION_PLAYER_CITIZENSHIP_BACKFILL_DONE, '1', false);
    }

    private static function players_dropdown_admin($name, $selected, $club_id, $required)
    {
        return OpenTT_Unified_Admin_Readonly_Helpers::players_dropdown_admin($name, $selected, $club_id, $required);
    }

    private static function all_players_admin_index()
    {
        return OpenTT_Unified_Admin_Readonly_Helpers::all_players_admin_index();
    }

    private static function get_player_club_id($player_id)
    {
        return OpenTT_Unified_Admin_Readonly_Helpers::get_player_club_id($player_id);
    }

    private static function require_cap()
    {
        if (!current_user_can(self::CAP)) {
            wp_die('Nemaš dozvolu za ovu akciju.');
        }
    }

    private static function is_legacy_match_cpt_enabled()
    {
        return false;
    }

    public static function db_table($entity)
    {
        return \OpenTT\Unified\Infrastructure\DbTableResolver::resolve(
            $entity,
            [
                'matches' => [
                    'new' => self::TABLE_MATCHES,
                    'legacy' => self::LEGACY_TABLE_MATCHES,
                ],
                'games' => [
                    'new' => self::TABLE_GAMES,
                    'legacy' => self::LEGACY_TABLE_GAMES,
                ],
                'sets' => [
                    'new' => self::TABLE_SETS,
                    'legacy' => self::LEGACY_TABLE_SETS,
                ],
            ],
            self::TABLE_MATCHES
        );
    }

    private static function db_table_name($entity, $legacy = false)
    {
        global $wpdb;

        $map = [
            'matches' => [
                'new' => self::TABLE_MATCHES,
                'legacy' => self::LEGACY_TABLE_MATCHES,
            ],
            'games' => [
                'new' => self::TABLE_GAMES,
                'legacy' => self::LEGACY_TABLE_GAMES,
            ],
            'sets' => [
                'new' => self::TABLE_SETS,
                'legacy' => self::LEGACY_TABLE_SETS,
            ],
        ];

        if (!isset($map[$entity])) {
            return '';
        }

        $suffix = $legacy ? $map[$entity]['legacy'] : $map[$entity]['new'];
        return $wpdb->prefix . $suffix;
    }

    private static function table_exists($table_name)
    {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        return $found === $table_name;
    }

    private static function ensure_matches_live_column($table_name)
    {
        global $wpdb;
        $table_name = (string) $table_name;
        if ($table_name === '' || !self::table_exists($table_name)) {
            return;
        }
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", 'live'));
        if (!empty($column)) {
            return;
        }
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN live tinyint(1) NOT NULL DEFAULT 0 AFTER featured");
    }

    private static function get_game_sets_rows($game_id)
    {
        $raw = maybe_unserialize(get_post_meta($game_id, 'setovi', true));

        if (is_array($raw)) {
            $rows = [];
            foreach ($raw as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $normalized = self::normalize_set_row($row);
                if ($normalized !== null) {
                    $rows[] = $normalized;
                }
            }
            if (!empty($rows)) {
                return $rows;
            }
        }

        // ACF repeater fallback: `setovi` može biti broj redova.
        if (is_numeric($raw)) {
            $count = max(0, intval($raw));
            $rows = [];
            for ($i = 0; $i < $count; $i++) {
                $home = get_post_meta($game_id, 'setovi_' . $i . '_poeni_domacin', true);
                $away = get_post_meta($game_id, 'setovi_' . $i . '_poeni_gost', true);
                if ($home === '' && $away === '') {
                    continue;
                }
                $rows[] = [
                    'poeni_domacin' => intval($home),
                    'poeni_gost' => intval($away),
                ];
            }
            return $rows;
        }

        // Heuristika za slučajeve kada import prebaci nestandardne set meta ključeve.
        $all_meta = get_post_meta($game_id);
        if (is_array($all_meta) && !empty($all_meta)) {
            $indexed = [];
            foreach ($all_meta as $key => $vals) {
                if (!preg_match('/^setovi_(\\d+)_(.+)$/', (string) $key, $m)) {
                    continue;
                }
                $index = intval($m[1]);
                $subkey = (string) $m[2];
                $value = is_array($vals) ? reset($vals) : $vals;
                $indexed[$index][$subkey] = $value;
            }

            if (!empty($indexed)) {
                ksort($indexed, SORT_NUMERIC);
                $rows = [];
                foreach ($indexed as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $normalized = self::normalize_set_row($row);
                    if ($normalized !== null) {
                        $rows[] = $normalized;
                    }
                }
                if (!empty($rows)) {
                    return $rows;
                }
            }
        }

        return [];
    }

    private static function normalize_set_row($row)
    {
        if (!is_array($row)) {
            return null;
        }

        $home = null;
        $away = null;

        if (array_key_exists('poeni_domacin', $row) || array_key_exists('poeni_gost', $row)) {
            $home = array_key_exists('poeni_domacin', $row) ? intval($row['poeni_domacin']) : 0;
            $away = array_key_exists('poeni_gost', $row) ? intval($row['poeni_gost']) : 0;
            return ['poeni_domacin' => $home, 'poeni_gost' => $away];
        }

        foreach ($row as $k => $v) {
            $key = strtolower((string) $k);
            if ($home === null && (strpos($key, 'dom') !== false || strpos($key, 'home') !== false)) {
                $home = intval($v);
                continue;
            }
            if ($away === null && (strpos($key, 'gos') !== false || strpos($key, 'away') !== false)) {
                $away = intval($v);
                continue;
            }
        }

        if ($home === null || $away === null) {
            $numeric = [];
            foreach ($row as $v) {
                if ($v === '' || $v === null) {
                    continue;
                }
                if (is_numeric($v)) {
                    $numeric[] = intval($v);
                }
            }
            if ($home === null && isset($numeric[0])) {
                $home = $numeric[0];
            }
            if ($away === null && isset($numeric[1])) {
                $away = $numeric[1];
            }
        }

        if ($home === null && $away === null) {
            return null;
        }

        return [
            'poeni_domacin' => $home === null ? 0 : intval($home),
            'poeni_gost' => $away === null ? 0 : intval($away),
        ];
    }

    private static function is_standard_sets_array($raw)
    {
        if (!is_array($raw) || empty($raw)) {
            return false;
        }
        foreach ($raw as $row) {
            if (!is_array($row)) {
                return false;
            }
            if (!array_key_exists('poeni_domacin', $row) || !array_key_exists('poeni_gost', $row)) {
                return false;
            }
        }
        return true;
    }

    private static function clear_game_sets_meta($game_id)
    {
        $all_meta = get_post_meta($game_id);
        if (!is_array($all_meta) || empty($all_meta)) {
            return;
        }

        foreach ($all_meta as $key => $vals) {
            $key = (string) $key;
            if ($key === 'setovi' || $key === '_setovi' || strpos($key, 'setovi_') === 0 || strpos($key, '_setovi_') === 0) {
                delete_post_meta($game_id, $key);
            }
        }
    }
}

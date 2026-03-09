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

final class AdminSettingsActionManager
{
    public static function handleOnboardingAction(array $config)
    {
        self::requireCapability((string) ($config['capability'] ?? ''));
        check_admin_referer('opentt_unified_onboarding_action');

        $actionKey = (string) ($config['action_key'] ?? 'opentt_onboarding_action');
        $stateOptionKey = (string) ($config['state_option_key'] ?? '');
        $redirectTransientKey = (string) ($config['redirect_transient_key'] ?? '');
        $dashboardUrl = (string) ($config['dashboard_url'] ?? admin_url('admin.php'));

        $state = OnboardingActionManager::resolveStateFromRequest($actionKey, 'completed');
        OnboardingActionManager::persistStateAndClearRedirect($stateOptionKey, $state, $redirectTransientKey);

        $message = ($state === 'skipped')
            ? 'First Time Setup je preskočen.'
            : 'First Time Setup je završen.';

        wp_safe_redirect(AdminNoticeManager::buildUrl($dashboardUrl, 'success', $message));
        exit;
    }

    public static function handleDeleteAllData(array $config)
    {
        self::requireCapability((string) ($config['capability'] ?? ''));
        check_admin_referer('opentt_unified_delete_all_data');

        $confirmKey = (string) ($config['confirm_phrase_key'] ?? 'opentt_confirm_phrase');
        $settingsUrl = (string) ($config['settings_url'] ?? admin_url('admin.php'));

        $phrase = isset($_POST[$confirmKey]) ? sanitize_text_field((string) wp_unslash($_POST[$confirmKey])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (trim($phrase) !== 'saglasan sam') {
            wp_safe_redirect(AdminNoticeManager::buildUrl($settingsUrl, 'error', 'Brisanje nije izvršeno. Upiši tačno: saglasan sam.'));
            exit;
        }

        DataPurgeManager::purgeAll([
            'post_types' => (array) ($config['post_types'] ?? []),
            'taxonomies' => (array) ($config['taxonomies'] ?? []),
            'option_keys' => (array) ($config['option_keys'] ?? []),
            'transient_keys' => (array) ($config['transient_keys'] ?? []),
        ]);

        wp_safe_redirect(AdminNoticeManager::buildUrl($settingsUrl, 'success', 'Svi OpenTT podaci su obrisani.'));
        exit;
    }

    public static function handleSaveSettings(array $config)
    {
        self::requireCapability((string) ($config['capability'] ?? ''));
        check_admin_referer('opentt_unified_save_settings');

        $sectionKey = (string) ($config['section_key'] ?? 'opentt_settings_section');
        $actionKey = (string) ($config['css_action_key'] ?? 'opentt_css_action');
        $section = isset($_POST[$sectionKey]) ? sanitize_key((string) $_POST[$sectionKey]) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $action = isset($_POST[$actionKey]) ? sanitize_key((string) $_POST[$actionKey]) : 'save'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        $settingsUrl = (string) ($config['settings_url'] ?? admin_url('admin.php'));
        $customizeUrl = (string) ($config['customize_url'] ?? admin_url('admin.php'));

        $optionVisualSettings = (string) ($config['option_visual_settings'] ?? '');
        $optionCustomCss = (string) ($config['option_custom_css'] ?? '');
        $optionCustomCssMap = (string) ($config['option_custom_css_map'] ?? '');
        $optionEloEnabled = (string) ($config['option_elo_enabled'] ?? '');
        $optionAdminUiLanguage = (string) ($config['option_admin_ui_language'] ?? '');
        $availableLanguages = isset($config['available_languages']) && is_array($config['available_languages'])
            ? $config['available_languages']
            : ['sr' => 'Srpski', 'en' => 'English'];

        if ($action === 'reset') {
            if ($section === 'visual') {
                SettingsManager::resetVisualSettings($optionVisualSettings);
                wp_safe_redirect(AdminNoticeManager::buildUrl($customizeUrl, 'success', 'Globalna stilizacija je resetovana.'));
                exit;
            }
            if ($section === 'css') {
                SettingsManager::resetCustomCss($optionCustomCss, $optionCustomCssMap);
                wp_safe_redirect(AdminNoticeManager::buildUrl($customizeUrl, 'success', 'CSS override je resetovan.'));
                exit;
            }
            SettingsManager::resetCustomCss($optionCustomCss, $optionCustomCssMap);
            SettingsManager::resetVisualSettings($optionVisualSettings);
            wp_safe_redirect(AdminNoticeManager::buildUrl($settingsUrl, 'success', 'Podešavanja su resetovana.'));
            exit;
        }

        if ($section === 'ui_lang' || $section === 'all') {
            $rawLang = isset($_POST['admin_ui_language']) ? (string) wp_unslash($_POST['admin_ui_language']) : 'sr'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            SettingsManager::saveAdminUiLanguage($optionAdminUiLanguage, $availableLanguages, $rawLang, 'sr');
            if ($section === 'ui_lang') {
                wp_safe_redirect(AdminNoticeManager::buildUrl($settingsUrl, 'success', 'Jezik admin interfejsa je sačuvan.'));
                exit;
            }
        }

        if ($section === 'visual' || $section === 'all') {
            $visualSettings = isset($_POST['visual_settings']) && is_array($_POST['visual_settings'])
                ? (array) wp_unslash($_POST['visual_settings']) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                : [];
            SettingsManager::saveVisualSettings($optionVisualSettings, $visualSettings);
            if ($section === 'visual') {
                wp_safe_redirect(AdminNoticeManager::buildUrl($customizeUrl, 'success', 'Globalna stilizacija je sačuvana.'));
                exit;
            }
        }

        if ($section === 'elo' || $section === 'all') {
            $eloEnabled = !empty($_POST['elo_enabled']) ? '1' : '0'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ($optionEloEnabled !== '') {
                update_option($optionEloEnabled, $eloEnabled, false);
            }
            if ($section === 'elo') {
                wp_safe_redirect(AdminNoticeManager::buildUrl($settingsUrl, 'success', 'ELO podešavanje je sačuvano.'));
                exit;
            }
        }

        if ($section === 'css' || $section === 'all') {
            $cssRaw = isset($_POST['custom_shortcode_css']) ? (string) wp_unslash($_POST['custom_shortcode_css']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $cssMapIn = isset($_POST['custom_shortcode_css_map']) && is_array($_POST['custom_shortcode_css_map'])
                ? (array) $_POST['custom_shortcode_css_map'] // phpcs:ignore WordPress.Security.NonceVerification.Missing
                : [];
            SettingsManager::saveCustomCssOverrides($optionCustomCss, $optionCustomCssMap, $cssRaw, $cssMapIn);
            if ($section === 'css') {
                wp_safe_redirect(AdminNoticeManager::buildUrl($customizeUrl, 'success', 'CSS override je sačuvan.'));
                exit;
            }
        }

        wp_safe_redirect(AdminNoticeManager::buildUrl($settingsUrl, 'success', 'Podešavanja su sačuvana.'));
        exit;
    }

    private static function requireCapability($capability)
    {
        $capability = (string) $capability;
        if ($capability === '' || !current_user_can($capability)) {
            wp_die(esc_html__('Nedovoljna prava.', 'default'));
        }
    }
}

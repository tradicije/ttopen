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

final class OnboardingManager
{
    public static function prepareOnActivation(array $config)
    {
        $stateOptionKey = (string) ($config['state_option_key'] ?? '');
        $schemaOptionKey = (string) ($config['schema_option_key'] ?? '');
        $redirectTransientKey = (string) ($config['redirect_transient_key'] ?? '');

        if ($stateOptionKey === '' || $schemaOptionKey === '' || $redirectTransientKey === '') {
            return;
        }

        $state = (string) get_option($stateOptionKey, '');
        if ($state !== '') {
            return;
        }

        if (self::isExistingInstallDetected($schemaOptionKey)) {
            update_option($stateOptionKey, 'not_needed', false);
            return;
        }

        update_option($stateOptionKey, 'pending', false);
        set_transient($redirectTransientKey, '1', DAY_IN_SECONDS);
    }

    public static function maybeRedirectToOnboarding(array $config)
    {
        $stateOptionKey = (string) ($config['state_option_key'] ?? '');
        $redirectTransientKey = (string) ($config['redirect_transient_key'] ?? '');
        $capability = (string) ($config['capability'] ?? '');
        $onboardingPageSlug = (string) ($config['onboarding_page_slug'] ?? '');

        if ($stateOptionKey === '' || $redirectTransientKey === '' || $capability === '' || $onboardingPageSlug === '') {
            return;
        }

        if (!is_admin() || wp_doing_ajax() || !current_user_can($capability)) {
            return;
        }

        $state = (string) get_option($stateOptionKey, '');
        if ($state !== 'pending') {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($page === $onboardingPageSlug) {
            return;
        }

        delete_transient($redirectTransientKey);
        wp_safe_redirect(admin_url('admin.php?page=' . $onboardingPageSlug));
        exit;
    }

    private static function isExistingInstallDetected($schemaOptionKey)
    {
        $schemaOption = get_option((string) $schemaOptionKey, null);
        if ($schemaOption !== null) {
            return true;
        }

        $hasAnyPosts = static function ($postType) {
            $counts = wp_count_posts($postType);
            if (!$counts || !is_object($counts)) {
                return false;
            }
            foreach ((array) $counts as $status => $count) {
                if ($status === 'auto-draft' || $status === 'trash') {
                    continue;
                }
                if ((int) $count > 0) {
                    return true;
                }
            }
            return false;
        };

        if ($hasAnyPosts('klub') || $hasAnyPosts('igrac') || $hasAnyPosts('pravilo_takmicenja')) {
            return true;
        }

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        if (self::tableExists($matchesTable)) {
            $rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$matchesTable}");
            if ($rows > 0) {
                return true;
            }
        }

        return false;
    }

    private static function tableExists($tableName)
    {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName));
        return $found === $tableName;
    }
}

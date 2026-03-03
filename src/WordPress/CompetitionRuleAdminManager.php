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

final class CompetitionRuleAdminManager
{
    public static function handleSave($capability, array $callbacks)
    {
        self::requireCapability($capability);
        check_admin_referer('opentt_unified_save_competition_rule');

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $leagueName = sanitize_text_field((string) ($_POST['league_name'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $seasonName = sanitize_text_field((string) ($_POST['season_name'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $leagueSlug = sanitize_title($leagueName);
        $seasonSlug = sanitize_title($seasonName);

        if ($leagueSlug === '' || $seasonSlug === '') {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-add-competition'), 'error', 'Liga i sezona su obavezne.'));
            exit;
        }

        $findBySlugs = $callbacks['find_rule_by_slugs'];
        $existing = $findBySlugs($leagueSlug, $seasonSlug);
        if ($existing && (int) $existing->ID !== $id) {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-add-competition'), 'error', 'Takmičenje za ovu ligu i sezonu već postoji.'));
            exit;
        }

        $slugToTitle = $callbacks['slug_to_title'];
        $postData = [
            'post_type' => 'pravilo_takmicenja',
            'post_title' => $slugToTitle($leagueSlug) . ' / ' . $slugToTitle($seasonSlug),
            'post_status' => 'publish',
        ];

        if ($id > 0) {
            $postData['ID'] = $id;
            $ruleId = wp_update_post($postData, true);
        } else {
            $ruleId = wp_insert_post($postData, true);
        }

        if (!$ruleId || is_wp_error($ruleId)) {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-add-competition'), 'error', 'Neuspešno čuvanje takmičenja.'));
            exit;
        }

        $ensureLeagueEntity = $callbacks['ensure_league_entity'];
        $ensureSeasonEntity = $callbacks['ensure_season_entity'];
        $normalizeFederation = $callbacks['normalize_federation'];

        $ensureLeagueEntity($leagueSlug, $leagueName);
        $ensureSeasonEntity($seasonSlug, $seasonName);

        update_post_meta($ruleId, 'opentt_competition_league_slug', $leagueSlug);
        update_post_meta($ruleId, 'opentt_competition_season_slug', $seasonSlug);
        update_post_meta($ruleId, 'opentt_competition_rank', max(1, min(5, (int) ($_POST['rang'] ?? 3)))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_post_meta($ruleId, 'opentt_competition_promotion_slots', max(0, (int) ($_POST['promocija_broj'] ?? 0))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_post_meta($ruleId, 'opentt_competition_promotion_playoff_slots', max(0, (int) ($_POST['promocija_baraz_broj'] ?? 0))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_post_meta($ruleId, 'opentt_competition_relegation_slots', max(0, (int) ($_POST['ispadanje_broj'] ?? 0))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_post_meta($ruleId, 'opentt_competition_relegation_playoff_slots', max(0, (int) ($_POST['ispadanje_razigravanje_broj'] ?? 0))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_post_meta($ruleId, 'opentt_competition_scoring_type', sanitize_text_field((string) ($_POST['bodovanje_tip'] ?? '2-1'))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_post_meta($ruleId, 'opentt_competition_match_format', sanitize_text_field((string) ($_POST['format_partija'] ?? 'format_a'))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        update_post_meta($ruleId, 'opentt_competition_federation', $normalizeFederation((string) ($_POST['savez'] ?? 'STSS'))); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        $thumbId = isset($_POST['featured_image_id']) ? (int) $_POST['featured_image_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($thumbId > 0) {
            set_post_thumbnail($ruleId, $thumbId);
        } else {
            delete_post_thumbnail($ruleId);
        }

        wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-competitions'), 'success', 'Takmičenje je sačuvano.'));
        exit;
    }

    public static function handleDelete($capability)
    {
        self::requireCapability($capability);
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($id <= 0) {
            wp_die('Nedostaje ID.');
        }

        check_admin_referer('opentt_unified_delete_competition_rule_' . $id);
        wp_trash_post($id);
        wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-competitions'), 'success', 'Takmičenje je obrisano.'));
        exit;
    }

    public static function handleMigrate($capability, callable $migrateCallback)
    {
        self::requireCapability($capability);
        check_admin_referer('opentt_unified_migrate_competition_rules');

        $result = $migrateCallback();
        $msg = 'Migracija takmičenja završena. Kreirano/azurirano: ' . (int) ($result['rules'] ?? 0) . '.';
        wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-competitions'), 'success', $msg));
        exit;
    }

    private static function requireCapability($capability)
    {
        if (!current_user_can((string) $capability)) {
            wp_die('Nemaš dozvolu za ovu akciju.');
        }
    }
}

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

final class CompetitionMaintenanceManager
{
    public static function handleResetMatches($capability, callable $tableExistsCallback, callable $slugToTitleCallback)
    {
        self::requireCapability($capability);
        check_admin_referer('opentt_unified_reset_competition_matches');

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        $gamesTable = \OpenTT_Unified_Core::db_table('games');
        $setsTable = \OpenTT_Unified_Core::db_table('sets');

        if (
            !$tableExistsCallback($matchesTable)
            || !$tableExistsCallback($gamesTable)
            || !$tableExistsCallback($setsTable)
        ) {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'error', 'Nedostaju OpenTT DB tabele za reset.'));
            exit;
        }

        $ruleId = intval($_POST['competition_rule_id'] ?? 0); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($ruleId <= 0) {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'error', 'Izaberi takmičenje za reset.'));
            exit;
        }

        $leagueSlug = sanitize_title((string) get_post_meta($ruleId, 'opentt_competition_league_slug', true));
        $seasonSlug = sanitize_title((string) get_post_meta($ruleId, 'opentt_competition_season_slug', true));
        if ($leagueSlug === '' || $seasonSlug === '') {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'error', 'Takmičenje nema validan liga/sezona slug.'));
            exit;
        }

        $matchIds = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$matchesTable} WHERE liga_slug=%s AND sezona_slug=%s",
            $leagueSlug,
            $seasonSlug
        )) ?: [];
        $matchIds = array_values(array_filter(array_map('intval', (array) $matchIds), static function ($id) {
            return $id > 0;
        }));

        if (empty($matchIds)) {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'success', 'Nema utakmica za reset u izabranom takmičenju.'));
            exit;
        }

        $idList = implode(',', $matchIds);
        $gameIds = $wpdb->get_col("SELECT id FROM {$gamesTable} WHERE match_id IN ({$idList})") ?: [];
        $gameIds = array_values(array_filter(array_map('intval', (array) $gameIds), static function ($id) {
            return $id > 0;
        }));

        $deletedSets = 0;
        if (!empty($gameIds)) {
            $gameList = implode(',', $gameIds);
            $deletedSets = (int) $wpdb->query("DELETE FROM {$setsTable} WHERE game_id IN ({$gameList})");
        }

        $deletedGames = (int) $wpdb->query("DELETE FROM {$gamesTable} WHERE match_id IN ({$idList})");
        $deletedMatches = (int) $wpdb->query("DELETE FROM {$matchesTable} WHERE id IN ({$idList})");

        $msg = 'Reset završen za ' . $slugToTitleCallback($leagueSlug) . ' / ' . $slugToTitleCallback($seasonSlug)
            . '. Obrisano utakmica: ' . max(0, $deletedMatches)
            . ', partija: ' . max(0, $deletedGames)
            . ', setova: ' . max(0, $deletedSets) . '.';
        wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'success', $msg));
        exit;
    }

    public static function handleDiagnostics($capability, $diagnosticsOptionKey, callable $getDiagnosticsRowsCallback, callable $slugToTitleCallback)
    {
        self::requireCapability($capability);
        check_admin_referer('opentt_unified_competition_diagnostics');

        $ruleId = intval($_POST['competition_rule_id'] ?? 0); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($ruleId <= 0) {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'error', 'Izaberi takmičenje za dijagnostiku.'));
            exit;
        }
        $leagueSlug = sanitize_title((string) get_post_meta($ruleId, 'opentt_competition_league_slug', true));
        $seasonSlug = sanitize_title((string) get_post_meta($ruleId, 'opentt_competition_season_slug', true));
        if ($leagueSlug === '' || $seasonSlug === '') {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'error', 'Takmičenje nema validan liga/sezona slug.'));
            exit;
        }

        $rows = $getDiagnosticsRowsCallback($leagueSlug, $seasonSlug);
        self::storeDiagnostics((string) $diagnosticsOptionKey, $leagueSlug, $seasonSlug, $rows);

        if (empty($rows)) {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'error', 'Nema utakmica za izabranu ligu/sezonu.'));
            exit;
        }

        $mismatch = 0;
        foreach ($rows as $row) {
            if (intval($row['matches_played'] ?? 0) !== intval($row['matches_with_score'] ?? 0)) {
                $mismatch++;
            }
        }

        $msg = 'Dijagnostika generisana za ' . $slugToTitleCallback($leagueSlug) . ' / ' . $slugToTitleCallback($seasonSlug)
            . '. Kola: ' . count($rows) . '.';
        if ($mismatch > 0) {
            $msg .= ' Mismatch kola: ' . $mismatch . '.';
        }
        wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'success', $msg));
        exit;
    }

    public static function handleRepairPlayed($capability, $diagnosticsOptionKey, callable $tableExistsCallback, callable $getDiagnosticsRowsCallback, callable $slugToTitleCallback)
    {
        self::requireCapability($capability);
        check_admin_referer('opentt_unified_repair_competition_played');

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        if (!$tableExistsCallback($matchesTable)) {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'error', 'Nedostaje tabela utakmica.'));
            exit;
        }

        $ruleId = intval($_POST['competition_rule_id'] ?? 0); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($ruleId <= 0) {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'error', 'Izaberi takmičenje za repair.'));
            exit;
        }
        $leagueSlug = sanitize_title((string) get_post_meta($ruleId, 'opentt_competition_league_slug', true));
        $seasonSlug = sanitize_title((string) get_post_meta($ruleId, 'opentt_competition_season_slug', true));
        if ($leagueSlug === '' || $seasonSlug === '') {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'error', 'Takmičenje nema validan liga/sezona slug.'));
            exit;
        }

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$matchesTable}
             SET played = CASE WHEN (home_score + away_score) > 0 THEN 1 ELSE 0 END
             WHERE liga_slug=%s AND sezona_slug=%s",
            $leagueSlug,
            $seasonSlug
        ));
        if ($updated === false) {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'error', 'Repair nije uspeo.'));
            exit;
        }

        $rows = $getDiagnosticsRowsCallback($leagueSlug, $seasonSlug);
        self::storeDiagnostics((string) $diagnosticsOptionKey, $leagueSlug, $seasonSlug, $rows);

        $msg = 'Repair played završen za ' . $slugToTitleCallback($leagueSlug) . ' / ' . $slugToTitleCallback($seasonSlug)
            . '. Ažurirano redova: ' . intval($updated) . '.';
        wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-transfer'), 'success', $msg));
        exit;
    }

    private static function storeDiagnostics($optionKey, $leagueSlug, $seasonSlug, array $rows)
    {
        update_option($optionKey, [
            'liga_slug' => $leagueSlug,
            'sezona_slug' => $seasonSlug,
            'rows' => $rows,
            'generated_at' => current_time('mysql'),
        ], false);
    }

    private static function requireCapability($capability)
    {
        if (!current_user_can((string) $capability)) {
            wp_die('Nemaš dozvolu za ovu akciju.');
        }
    }
}

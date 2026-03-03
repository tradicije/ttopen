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

final class DataTransferActionManager
{
    public static function handleExport(array $config)
    {
        self::requireCapability((string) ($config['capability'] ?? ''));
        check_admin_referer('opentt_unified_export_data');

        $sanitizeSections = (isset($config['sanitize_sections']) && is_callable($config['sanitize_sections']))
            ? $config['sanitize_sections']
            : static function ($raw) {
                return is_array($raw) ? $raw : [];
            };
        $buildPayload = (isset($config['build_export_payload']) && is_callable($config['build_export_payload']))
            ? $config['build_export_payload']
            : static function () {
                return [];
            };
        $transferUrl = (string) ($config['transfer_url'] ?? admin_url('admin.php'));

        $sections = $sanitizeSections($_POST['sections'] ?? []); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (empty($sections)) {
            wp_safe_redirect(AdminNoticeManager::buildUrl($transferUrl, 'error', 'Izaberi bar jednu sekciju za izvoz.'));
            exit;
        }

        $payload = $buildPayload($sections);
        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            wp_safe_redirect(AdminNoticeManager::buildUrl($transferUrl, 'error', 'Greška pri formiranju izvoznog fajla.'));
            exit;
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="opentt-export-' . gmdate('Ymd-His') . '.json"');
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    public static function handleImportValidate(array $config)
    {
        self::requireCapability((string) ($config['capability'] ?? ''));
        check_admin_referer('opentt_unified_import_validate');

        $sanitizeSections = (isset($config['sanitize_sections']) && is_callable($config['sanitize_sections']))
            ? $config['sanitize_sections']
            : static function ($raw) {
                return is_array($raw) ? $raw : [];
            };
        $parsePayload = (isset($config['parse_import_payload']) && is_callable($config['parse_import_payload']))
            ? $config['parse_import_payload']
            : static function () {
                return [null, 'Import parser nije konfigurisan.'];
            };
        $validatePayload = (isset($config['validate_import_payload']) && is_callable($config['validate_import_payload']))
            ? $config['validate_import_payload']
            : static function () {
                return ['valid' => false, 'summary' => [], 'issues' => []];
            };
        $detectPlayerConflicts = (isset($config['detect_player_conflicts']) && is_callable($config['detect_player_conflicts']))
            ? $config['detect_player_conflicts']
            : static function () {
                return [];
            };
        $importPreviewOptionKey = (string) ($config['import_preview_option_key'] ?? '');
        $transferUrl = (string) ($config['transfer_url'] ?? admin_url('admin.php'));

        delete_option($importPreviewOptionKey);
        $sections = $sanitizeSections($_POST['sections'] ?? []); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (empty($sections)) {
            wp_safe_redirect(AdminNoticeManager::buildUrl($transferUrl, 'error', 'Izaberi bar jednu sekciju za uvoz.'));
            exit;
        }

        [$payload, $err] = $parsePayload('import_file');
        if (!$payload) {
            wp_safe_redirect(AdminNoticeManager::buildUrl($transferUrl, 'error', (string) $err));
            exit;
        }

        $validation = $validatePayload($payload, $sections);
        $playerConflicts = $detectPlayerConflicts($payload, $sections);

        $token = sanitize_key(strtolower(wp_generate_password(24, false, false)));
        if ($token === '') {
            $token = sanitize_key(strtolower(wp_generate_uuid4()));
        }

        set_transient('opentt_unified_import_payload_' . $token, $payload, 30 * MINUTE_IN_SECONDS);
        update_option($importPreviewOptionKey, [
            'token' => $token,
            'sections' => $sections,
            'summary' => (array) ($validation['summary'] ?? []),
            'issues' => (array) ($validation['issues'] ?? []),
            'player_conflicts' => is_array($playerConflicts) ? $playerConflicts : [],
            'valid' => !empty($validation['valid']),
            'created_at' => current_time('mysql'),
        ], false);

        $msg = !empty($validation['valid'])
            ? 'Validacija uspešna. Pregledaj podatke i potvrdi uvoz.'
            : 'Validacija je završena, ali postoje problemi. Proveri listu i ponovo pokušaj.';
        $type = !empty($validation['valid']) ? 'success' : 'error';
        wp_safe_redirect(AdminNoticeManager::buildUrl($transferUrl, $type, $msg));
        exit;
    }

    public static function handleImportCommit(array $config)
    {
        self::requireCapability((string) ($config['capability'] ?? ''));
        check_admin_referer('opentt_unified_import_commit');

        $sanitizeSections = (isset($config['sanitize_sections']) && is_callable($config['sanitize_sections']))
            ? $config['sanitize_sections']
            : static function ($raw) {
                return is_array($raw) ? $raw : [];
            };
        $importApply = (isset($config['import_payload_apply']) && is_callable($config['import_payload_apply']))
            ? $config['import_payload_apply']
            : static function () {
                return [];
            };
        $importPreviewOptionKey = (string) ($config['import_preview_option_key'] ?? '');
        $transferUrl = (string) ($config['transfer_url'] ?? admin_url('admin.php'));

        $token = sanitize_key(strtolower((string) ($_POST['import_token'] ?? ''))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $preview = get_option($importPreviewOptionKey, []);
        $previewToken = '';
        if (is_array($preview) && !empty($preview['token'])) {
            $previewToken = sanitize_key(strtolower((string) $preview['token']));
        }

        if (!is_array($preview) || $previewToken === '' || $token === '' || $token !== $previewToken) {
            wp_safe_redirect(AdminNoticeManager::buildUrl($transferUrl, 'error', 'Nema validnog preview tokena za uvoz.'));
            exit;
        }

        $payload = get_transient('opentt_unified_import_payload_' . $token);
        if (!is_array($payload)) {
            wp_safe_redirect(AdminNoticeManager::buildUrl($transferUrl, 'error', 'Import preview je istekao. Pokreni validaciju ponovo.'));
            exit;
        }

        $sections = $sanitizeSections($preview['sections'] ?? []);
        $playerResolution = self::parsePlayerResolutionFromRequest($_POST['player_resolution'] ?? []); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $result = $importApply($payload, $sections, ['player_resolution' => $playerResolution]);

        delete_transient('opentt_unified_import_payload_' . $token);
        delete_option($importPreviewOptionKey);

        $msg = 'Uvoz završen. Takmičenja: ' . intval($result['competitions'] ?? 0)
            . ', klubovi: ' . intval($result['clubs'] ?? 0)
            . ', igrači: ' . intval($result['players'] ?? 0)
            . ', utakmice: ' . intval($result['matches'] ?? 0)
            . ', partije: ' . intval($result['games'] ?? 0)
            . ', setovi: ' . intval($result['sets'] ?? 0) . '.';
        if (!empty($result['issues']) && is_array($result['issues'])) {
            $msg .= ' Upozorenja: ' . count($result['issues']) . '.';
        }

        wp_safe_redirect(AdminNoticeManager::buildUrl($transferUrl, 'success', $msg));
        exit;
    }

    private static function parsePlayerResolutionFromRequest($resolutionInput)
    {
        if (!is_array($resolutionInput)) {
            return [];
        }

        $playerResolution = [];
        foreach ($resolutionInput as $source => $resolution) {
            $sourceId = intval($source);
            if ($sourceId <= 0) {
                continue;
            }
            $resolution = sanitize_text_field((string) wp_unslash($resolution));
            if ($resolution === 'new' || $resolution === 'skip') {
                $playerResolution[$sourceId] = $resolution;
                continue;
            }
            if (strpos($resolution, 'merge:') === 0) {
                $mergeId = intval(substr($resolution, 6));
                if ($mergeId > 0) {
                    $playerResolution[$sourceId] = 'merge:' . $mergeId;
                }
            }
        }

        return $playerResolution;
    }

    private static function requireCapability($capability)
    {
        $capability = (string) $capability;
        if ($capability === '' || !current_user_can($capability)) {
            wp_die(esc_html__('Nedovoljna prava.', 'default'));
        }
    }
}

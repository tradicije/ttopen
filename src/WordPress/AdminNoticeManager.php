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

final class AdminNoticeManager
{
    public static function buildUrl($url, $type, $message)
    {
        return add_query_arg([
            'opentt_notice' => sanitize_key((string) $type),
            'opentt_msg' => (string) $message,
        ], (string) $url);
    }

    public static function renderFromRequest()
    {
        if (!is_admin() || !isset($_GET['opentt_notice'], $_GET['opentt_msg'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $type = sanitize_key((string) $_GET['opentt_notice']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $msg = sanitize_text_field(rawurldecode(wp_unslash((string) $_GET['opentt_msg']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($msg === '') {
            return;
        }

        $class = $type === 'error' ? 'notice notice-error is-dismissible' : 'notice notice-success is-dismissible';
        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($msg) . '</p></div>';
    }
}

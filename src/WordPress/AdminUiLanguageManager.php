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

final class AdminUiLanguageManager
{
    public static function resolveCurrentLanguage($optionKey, array $available, $default = 'sr')
    {
        $lang = (string) get_option((string) $optionKey, (string) $default);
        if (!isset($available[$lang])) {
            $lang = (string) $default;
        }
        return $lang;
    }

    public static function isTranslationEnabled($lang, $defaultLanguage = 'sr')
    {
        return (string) $lang !== (string) $defaultLanguage;
    }

    public static function maybeStartTranslationBuffer($isEnabled, $pagePrefix, $bufferCallback)
    {
        if (!$isEnabled || !is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($page === '' || strpos($page, (string) $pagePrefix) !== 0) {
            return;
        }

        ob_start($bufferCallback);
    }
}

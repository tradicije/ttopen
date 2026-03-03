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

final class RewriteRulesManager
{
    public static function flushAndMark($optionKey)
    {
        flush_rewrite_rules(false);
        update_option((string) $optionKey, '1', false);
    }

    public static function flushOnce($optionKey)
    {
        $done = (string) get_option((string) $optionKey, '');
        if ($done === '1') {
            return;
        }

        self::flushAndMark($optionKey);
    }
}

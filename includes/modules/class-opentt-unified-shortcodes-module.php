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

final class OpenTT_Unified_Shortcodes_Module
{
    public static function register()
    {
        add_action('init', ['OpenTT_Unified_Core', 'register_shortcodes'], 99);
    }
}

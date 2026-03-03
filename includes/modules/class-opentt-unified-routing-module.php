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

final class OpenTT_Unified_Routing_Module
{
    public static function register()
    {
        add_action('template_redirect', ['OpenTT_Unified_Core', 'capture_virtual_match_context'], 1);
        add_filter('template_include', ['OpenTT_Unified_Core', 'template_include'], 99);
    }
}

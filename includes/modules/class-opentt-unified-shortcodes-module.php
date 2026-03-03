<?php

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

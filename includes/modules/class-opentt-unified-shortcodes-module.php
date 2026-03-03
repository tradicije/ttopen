<?php

if (!defined('ABSPATH')) {
    exit;
}

final class STKB_Unified_Shortcodes_Module
{
    public static function register()
    {
        add_action('init', ['STKB_Unified_Core', 'register_shortcodes'], 99);
    }
}

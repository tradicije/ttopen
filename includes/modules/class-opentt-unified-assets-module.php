<?php

if (!defined('ABSPATH')) {
    exit;
}

final class OpenTT_Unified_Assets_Module
{
    public static function register()
    {
        add_action('wp_enqueue_scripts', ['OpenTT_Unified_Core', 'enqueue_frontend_assets']);
    }
}

<?php

if (!defined('ABSPATH')) {
    exit;
}

final class STKB_Unified_Assets_Module
{
    public static function register()
    {
        add_action('wp_enqueue_scripts', ['STKB_Unified_Core', 'enqueue_frontend_assets']);
    }
}

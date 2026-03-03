<?php

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

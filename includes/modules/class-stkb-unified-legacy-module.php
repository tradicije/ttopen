<?php

if (!defined('ABSPATH')) {
    exit;
}

final class STKB_Unified_Legacy_Module
{
    public static function register()
    {
        add_action('init', ['STKB_Unified_Core', 'register_legacy_content_types'], 1);
    }
}

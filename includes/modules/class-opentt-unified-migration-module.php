<?php

if (!defined('ABSPATH')) {
    exit;
}

final class OpenTT_Unified_Migration_Module
{
    public static function register()
    {
        add_action('admin_post_opentt_unified_migrate_batch', ['OpenTT_Unified_Core', 'handle_migrate_batch']);
        add_action('admin_post_opentt_unified_reset_migration', ['OpenTT_Unified_Core', 'handle_reset_migration']);
        add_action('admin_post_opentt_unified_validate_import', ['OpenTT_Unified_Core', 'handle_validate_import']);
        add_action('admin_post_opentt_unified_repair_relations', ['OpenTT_Unified_Core', 'handle_repair_relations']);
        add_action('admin_post_opentt_unified_cleanup_placeholders', ['OpenTT_Unified_Core', 'handle_cleanup_placeholders']);
    }
}

<?php

if (!defined('ABSPATH')) {
    exit;
}

final class STKB_Unified_Migration_Module
{
    public static function register()
    {
        add_action('admin_post_stkb_unified_migrate_batch', ['STKB_Unified_Core', 'handle_migrate_batch']);
        add_action('admin_post_stkb_unified_reset_migration', ['STKB_Unified_Core', 'handle_reset_migration']);
        add_action('admin_post_stkb_unified_validate_import', ['STKB_Unified_Core', 'handle_validate_import']);
        add_action('admin_post_stkb_unified_repair_relations', ['STKB_Unified_Core', 'handle_repair_relations']);
        add_action('admin_post_stkb_unified_cleanup_placeholders', ['STKB_Unified_Core', 'handle_cleanup_placeholders']);
    }
}

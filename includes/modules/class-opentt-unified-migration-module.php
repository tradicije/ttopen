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

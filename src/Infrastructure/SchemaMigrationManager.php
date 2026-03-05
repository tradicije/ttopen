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

namespace OpenTT\Unified\Infrastructure;

final class SchemaMigrationManager
{
    private static $legacySyncRanByKey = [];

    public static function ensureSchemaAndLegacySync(array $config)
    {
        $schemaVersionOptionKey = (string) ($config['schema_version_option_key'] ?? '');
        $schemaVersion = (string) ($config['schema_version'] ?? '');
        $syncStateKey = (string) ($config['sync_state_key'] ?? 'opentt_default');

        $tableNameResolver = (isset($config['table_name_resolver']) && is_callable($config['table_name_resolver']))
            ? $config['table_name_resolver']
            : null;
        $tableExists = (isset($config['table_exists']) && is_callable($config['table_exists']))
            ? $config['table_exists']
            : null;
        $resetCache = (isset($config['reset_cache']) && is_callable($config['reset_cache']))
            ? $config['reset_cache']
            : null;

        if ($schemaVersionOptionKey === '' || $schemaVersion === '' || !$tableNameResolver || !$tableExists || !$resetCache) {
            return;
        }

        $stored = (string) get_option($schemaVersionOptionKey, '');
        if ($stored !== $schemaVersion) {
            self::migrateSchema($tableNameResolver, $resetCache);
            update_option($schemaVersionOptionKey, $schemaVersion, false);
        }

        self::syncLegacyTables($syncStateKey, $tableNameResolver, $tableExists, $resetCache);
    }

    private static function migrateSchema(callable $tableNameResolver, callable $resetCache)
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $matches = (string) $tableNameResolver('matches', false);
        $games = (string) $tableNameResolver('games', false);
        $sets = (string) $tableNameResolver('sets', false);

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$matches} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            legacy_post_id bigint(20) unsigned DEFAULT NULL,
            slug varchar(190) NOT NULL,
            liga_slug varchar(190) NOT NULL,
            sezona_slug varchar(190) DEFAULT '',
            kolo_slug varchar(190) NOT NULL,
            home_club_post_id bigint(20) unsigned NOT NULL,
            away_club_post_id bigint(20) unsigned NOT NULL,
            home_score smallint(6) NOT NULL DEFAULT 0,
            away_score smallint(6) NOT NULL DEFAULT 0,
            played tinyint(1) NOT NULL DEFAULT 0,
            featured tinyint(1) NOT NULL DEFAULT 0,
            live tinyint(1) NOT NULL DEFAULT 0,
            match_date datetime DEFAULT NULL,
            location varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY legacy_post_id (legacy_post_id),
            KEY liga_sezona_kolo (liga_slug, sezona_slug, kolo_slug),
            KEY match_date (match_date)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$games} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            legacy_post_id bigint(20) unsigned DEFAULT NULL,
            match_id bigint(20) unsigned NOT NULL,
            order_no smallint(6) NOT NULL DEFAULT 0,
            slug varchar(190) NOT NULL,
            is_doubles tinyint(1) NOT NULL DEFAULT 0,
            home_player_post_id bigint(20) unsigned DEFAULT NULL,
            away_player_post_id bigint(20) unsigned DEFAULT NULL,
            home_player2_post_id bigint(20) unsigned DEFAULT NULL,
            away_player2_post_id bigint(20) unsigned DEFAULT NULL,
            home_sets smallint(6) NOT NULL DEFAULT 0,
            away_sets smallint(6) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY legacy_post_id (legacy_post_id),
            KEY match_id_order (match_id, order_no)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$sets} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            game_id bigint(20) unsigned NOT NULL,
            set_no tinyint(3) unsigned NOT NULL,
            home_points smallint(6) NOT NULL DEFAULT 0,
            away_points smallint(6) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY game_set_unique (game_id, set_no),
            KEY game_id (game_id)
        ) {$charsetCollate};");

        $resetCache();
    }

    private static function syncLegacyTables($syncStateKey, callable $tableNameResolver, callable $tableExists, callable $resetCache)
    {
        $syncStateKey = (string) $syncStateKey;
        if ($syncStateKey === '') {
            $syncStateKey = 'opentt_default';
        }
        if (!empty(self::$legacySyncRanByKey[$syncStateKey])) {
            return;
        }
        self::$legacySyncRanByKey[$syncStateKey] = true;

        $entities = ['matches', 'games', 'sets'];
        $missingNewTables = false;
        foreach ($entities as $entity) {
            $newTable = (string) $tableNameResolver($entity, false);
            if (!$tableExists($newTable)) {
                $missingNewTables = true;
                break;
            }
        }

        if ($missingNewTables) {
            self::migrateSchema($tableNameResolver, $resetCache);
        }

        self::copyLegacyRowsIfNeeded(
            (string) $tableNameResolver('matches', true),
            (string) $tableNameResolver('matches', false),
            $tableExists
        );
        self::copyLegacyRowsIfNeeded(
            (string) $tableNameResolver('games', true),
            (string) $tableNameResolver('games', false),
            $tableExists
        );
        self::copyLegacyRowsIfNeeded(
            (string) $tableNameResolver('sets', true),
            (string) $tableNameResolver('sets', false),
            $tableExists
        );

        $resetCache();
    }

    private static function copyLegacyRowsIfNeeded($legacyTable, $newTable, callable $tableExists)
    {
        global $wpdb;

        $legacyTable = (string) $legacyTable;
        $newTable = (string) $newTable;
        if (
            $legacyTable === ''
            || $newTable === ''
            || $legacyTable === $newTable
            || !$tableExists($legacyTable)
            || !$tableExists($newTable)
        ) {
            return;
        }

        $legacyCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$legacyTable}");
        if ($legacyCount <= 0) {
            return;
        }

        $newCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$newTable}");
        if ($newCount >= $legacyCount) {
            return;
        }

        $legacyColumns = $wpdb->get_col("SHOW COLUMNS FROM {$legacyTable}") ?: [];
        $newColumns = $wpdb->get_col("SHOW COLUMNS FROM {$newTable}") ?: [];
        if (empty($legacyColumns) || empty($newColumns)) {
            return;
        }

        $commonColumns = array_values(array_intersect($newColumns, $legacyColumns));
        if (empty($commonColumns)) {
            return;
        }

        $quoted = array_map(static function ($column) {
            return '`' . str_replace('`', '``', (string) $column) . '`';
        }, $commonColumns);
        $columnsSql = implode(', ', $quoted);
        $wpdb->query("INSERT IGNORE INTO {$newTable} ({$columnsSql}) SELECT {$columnsSql} FROM {$legacyTable}");
    }
}

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

final class DbTableResolver
{
    private static $resolved = [];

    public static function resolve($entity, array $map, $defaultTable)
    {
        global $wpdb;

        $entity = sanitize_key((string) $entity);
        if ($entity === '') {
            return $wpdb->prefix . (string) $defaultTable;
        }

        if (isset(self::$resolved[$entity])) {
            return self::$resolved[$entity];
        }

        if (!isset($map[$entity]['new'])) {
            return $wpdb->prefix . (string) $defaultTable;
        }

        $newTable = $wpdb->prefix . (string) $map[$entity]['new'];
        $legacyTable = isset($map[$entity]['legacy']) ? $wpdb->prefix . (string) $map[$entity]['legacy'] : '';

        $newExists = self::tableExists($newTable);
        $legacyExists = ($legacyTable !== '') ? self::tableExists($legacyTable) : false;

        if ($newExists && $legacyExists) {
            $newCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$newTable}");
            $legacyCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$legacyTable}");
            self::$resolved[$entity] = ($newCount <= 0 && $legacyCount > 0) ? $legacyTable : $newTable;
            return self::$resolved[$entity];
        }

        if ($newExists) {
            self::$resolved[$entity] = $newTable;
            return $newTable;
        }

        if ($legacyExists) {
            self::$resolved[$entity] = $legacyTable;
            return $legacyTable;
        }

        self::$resolved[$entity] = $newTable;
        return $newTable;
    }

    public static function resetCache()
    {
        self::$resolved = [];
    }

    private static function tableExists($tableName)
    {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName));
        return $found === $tableName;
    }
}

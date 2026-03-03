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

namespace OpenTT\Unified\WordPress;

final class DataPurgeManager
{
    public static function purgeAll(array $config)
    {
        self::dropOpenTtTables();
        self::deletePostsByTypes((array) ($config['post_types'] ?? []));
        self::deleteTermsByTaxonomies((array) ($config['taxonomies'] ?? []));
        self::deleteOptions((array) ($config['option_keys'] ?? []));
        self::deleteTransients((array) ($config['transient_keys'] ?? []));
    }

    private static function dropOpenTtTables()
    {
        global $wpdb;

        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        $gamesTable = \OpenTT_Unified_Core::db_table('games');
        $setsTable = \OpenTT_Unified_Core::db_table('sets');

        $wpdb->query("DROP TABLE IF EXISTS {$setsTable}");
        $wpdb->query("DROP TABLE IF EXISTS {$gamesTable}");
        $wpdb->query("DROP TABLE IF EXISTS {$matchesTable}");
    }

    private static function deletePostsByTypes(array $postTypes)
    {
        foreach ($postTypes as $postType) {
            $ids = get_posts([
                'post_type' => (string) $postType,
                'posts_per_page' => -1,
                'post_status' => ['publish', 'private', 'draft', 'pending', 'future', 'trash'],
                'fields' => 'ids',
                'suppress_filters' => true,
            ]);
            foreach ((array) $ids as $id) {
                wp_delete_post((int) $id, true);
            }
        }
    }

    private static function deleteTermsByTaxonomies(array $taxonomies)
    {
        foreach ($taxonomies as $taxonomy) {
            $taxonomy = (string) $taxonomy;
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'fields' => 'ids',
            ]);
            if (is_wp_error($terms)) {
                continue;
            }
            foreach ((array) $terms as $termId) {
                wp_delete_term((int) $termId, $taxonomy);
            }
        }
    }

    private static function deleteOptions(array $optionKeys)
    {
        foreach ($optionKeys as $optionKey) {
            delete_option((string) $optionKey);
        }
    }

    private static function deleteTransients(array $transientKeys)
    {
        foreach ($transientKeys as $transientKey) {
            delete_transient((string) $transientKey);
        }
    }
}

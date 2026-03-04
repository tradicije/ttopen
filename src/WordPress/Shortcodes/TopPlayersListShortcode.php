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

namespace OpenTT\Unified\WordPress\Shortcodes;

final class TopPlayersListShortcode
{
    public static function render($atts, array $deps)
    {
        $call = static function ($name, ...$args) use ($deps) {
            $name = (string) $name;
            if (!isset($deps[$name]) || !is_callable($deps[$name])) {
                return null;
            }
            return $deps[$name](...$args);
        };

        global $post, $wpdb;

        $atts = shortcode_atts([
            'limit' => 5,
            'liga' => '',
            'sezona' => '',
        ], $atts);

        $limit = intval($atts['limit']);
        if ($limit === 0) {
            $limit = 5;
        }
        $limit = $limit < -1 ? -1 : $limit;

        $liga_slug = '';
        $sezona_slug = '';
        $max_kolo = null;
        $highlight_klubovi = [];
        $current_igrac_id = (is_singular('igrac') && get_post_type((int) get_the_ID()) === 'igrac') ? (int) get_the_ID() : 0;
        $ctx = null;
        $archive_ctx = $call('current_archive_context');

        $liga_param = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona_param = sanitize_title((string) ($atts['sezona'] ?? ''));

        if ($liga_param !== '') {
            $parsed = (array) $call('parse_legacy_liga_sezona', $liga_param, $sezona_param);
            $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? $liga_param));
            $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? $sezona_param));
        } elseif (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona') {
            $raw_liga = sanitize_title((string) ($archive_ctx['liga_slug'] ?? ''));
            $raw_sezona = sanitize_title((string) ($archive_ctx['sezona_slug'] ?? ''));
            $parsed_ctx = (array) $call('parse_legacy_liga_sezona', $raw_liga, $raw_sezona);
            $liga_slug = sanitize_title((string) ($parsed_ctx['league_slug'] ?? $raw_liga));
            $sezona_slug = sanitize_title((string) ($parsed_ctx['season_slug'] ?? $raw_sezona));
            if ($liga_slug === '' && $sezona_slug !== '') {
                $table = (string) $call('db_table', 'matches');
                if ((bool) $call('table_exists', $table)) {
                    $liga_guess = $wpdb->get_var($wpdb->prepare("SELECT liga_slug FROM {$table} WHERE sezona_slug=%s AND liga_slug<>'' ORDER BY id DESC LIMIT 1", $sezona_slug));
                    if (is_string($liga_guess) && $liga_guess !== '') {
                        $liga_slug = sanitize_title($liga_guess);
                    }
                }
            }

            $kolo_virtual = sanitize_title((string) ($archive_ctx['kolo_slug'] ?? ''));
            if ($kolo_virtual !== '') {
                $max_kolo = $call('extract_round_no', $kolo_virtual);
            }
        } elseif (is_tax('liga_sezona')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $parsed = (array) $call('parse_legacy_liga_sezona', (string) $term->slug, '');
                $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? ''));
                $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? ''));
            }
        } else {
            $ctx = $call('current_match_context');
            if ($ctx && !empty($ctx['db_row'])) {
                $row = $ctx['db_row'];
                $liga_slug = sanitize_title((string) $row->liga_slug);
                $sezona_slug = sanitize_title((string) $row->sezona_slug);
                $max_kolo = $call('extract_round_no', (string) $row->kolo_slug);
                $highlight_klubovi[] = intval($row->home_club_post_id);
                $highlight_klubovi[] = intval($row->away_club_post_id);
            } elseif ($current_igrac_id > 0) {
                $player_comp = $call('db_get_latest_competition_for_player', $current_igrac_id);
                if ($player_comp) {
                    $liga_slug = (string) $player_comp['liga_slug'];
                    $sezona_slug = (string) $player_comp['sezona_slug'];
                }
            } elseif (is_singular('utakmica') && !empty($post->ID)) {
                $legacy_liga_terms = wp_get_post_terms((int) $post->ID, 'liga_sezona', ['fields' => 'slugs']);
                if (!empty($legacy_liga_terms) && !is_wp_error($legacy_liga_terms)) {
                    $parsed = (array) $call('parse_legacy_liga_sezona', (string) $legacy_liga_terms[0], '');
                    $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? ''));
                    $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? ''));
                }
            } else {
                $latest_comp = $call('db_get_latest_competition_with_games');
                if ($latest_comp) {
                    $liga_slug = (string) $latest_comp['liga_slug'];
                    $sezona_slug = (string) $latest_comp['sezona_slug'];
                }
            }
        }

        $liga_slug = sanitize_title($liga_slug);
        $sezona_slug = sanitize_title($sezona_slug);
        if ($liga_slug === '') {
            return '';
        }

        $data = $call('db_get_top_players_data', $liga_slug, $sezona_slug, $max_kolo);
        $data = is_array($data) ? $data : [];
        if (empty($data)) {
            return (string) $call('shortcode_title_html', 'Rang lista igrača') . '<div class="no-players-message">Trenutno nema igrača za prikaz.</div>';
        }

        ob_start();
        echo (string) $call('shortcode_title_html', 'Rang lista igrača');
        echo '<div class="top-igraci-list">';
        $i = 1;
        foreach ($data as $igrac_id => $info) {
            if ($limit !== -1 && $i > $limit) {
                break;
            }
            $highlight = false;
            if ($current_igrac_id > 0 && intval($igrac_id) === $current_igrac_id) {
                $highlight = true;
            }
            if (!empty($highlight_klubovi) && in_array(intval($info['klub']), $highlight_klubovi, true)) {
                $highlight = true;
            }
            if ($igrac_id > 0 && get_post_type($igrac_id) === 'igrac') {
                echo $call('render_top_player_card_list', $igrac_id, $i, $info, $highlight); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            $i++;
        }

        if ($current_igrac_id > 0 && get_post_type($current_igrac_id) === 'igrac' && $limit > 0) {
            $rank = 1;
            foreach ($data as $igrac_id => $info) {
                if (intval($igrac_id) === $current_igrac_id && $rank > $limit) {
                    echo '<div class="top-igraci-separator"></div>';
                    echo $call('render_top_player_card_list', $igrac_id, $rank, $info, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    break;
                }
                $rank++;
            }
        }

        if ($ctx && !empty($highlight_klubovi) && $limit > 0) {
            $rank = 1;
            foreach ($data as $igrac_id => $info) {
                if ($rank <= $limit) {
                    $rank++;
                    continue;
                }
                if (in_array(intval($info['klub']), $highlight_klubovi, true)) {
                    echo '<div class="top-igraci-separator"></div>';
                    echo $call('render_top_player_card_list', $igrac_id, $rank, $info, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                $rank++;
            }
        }

        echo '</div>';
        return ob_get_clean();
    }
}

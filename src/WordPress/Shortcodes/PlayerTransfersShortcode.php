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

final class PlayerTransfersShortcode
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

        $atts = shortcode_atts([
            'igrac' => '',
        ], $atts);

        $player_id = 0;
        if (!empty($atts['igrac'])) {
            $lookup = sanitize_title((string) $atts['igrac']);
            $post = get_page_by_path($lookup, OBJECT, 'igrac');
            if (!$post) {
                $post = get_page_by_title((string) $atts['igrac'], OBJECT, 'igrac');
            }
            if ($post && !is_wp_error($post)) {
                $player_id = intval($post->ID);
            }
        } elseif (is_singular('igrac')) {
            $player_id = intval(get_the_ID());
        }

        if ($player_id <= 0) {
            return '';
        }

        $history = $call('db_get_player_season_club_history', $player_id);
        $history = is_array($history) ? $history : [];
        if (empty($history)) {
            return '<div class="opentt-transferi"><p>Nema podataka o transferima za ovog igrača.</p></div>';
        }

        $stints = $call('build_player_stints', $history);
        $stints = is_array($stints) ? $stints : [];
        if (empty($stints)) {
            return '<div class="opentt-transferi"><p>Nema podataka o transferima za ovog igrača.</p></div>';
        }

        $transfers = [];
        for ($i = 1; $i < count($stints); $i++) {
            $prev = $stints[$i - 1];
            $curr = $stints[$i];
            $transfers[] = [
                'season_slug' => (string) ($curr['from_season'] ?? ''),
                'from_club_id' => intval($prev['club_id'] ?? 0),
                'to_club_id' => intval($curr['club_id'] ?? 0),
            ];
        }
        $stints_desc = array_reverse($stints);
        $transfers_desc = array_reverse($transfers);

        ob_start();
        echo (string) $call('shortcode_title_html', 'Transferi');
        echo '<section class="opentt-transferi">';

        echo '<div class="opentt-transferi-block">';
        echo '<h4>Istorija klubova</h4>';
        echo '<table class="opentt-transferi-table"><thead><tr><th>Period</th><th>Klub</th></tr></thead><tbody>';
        foreach ($stints_desc as $s) {
            $from_slug = (string) ($s['from_season'] ?? '');
            $to_slug = (string) ($s['to_season'] ?? '');
            $period = (string) $call('season_display_name', $from_slug);
            if ($to_slug !== '' && $to_slug !== $from_slug) {
                $period .= ' - ' . (string) $call('season_display_name', $to_slug);
            }
            $club_id = intval($s['club_id'] ?? 0);
            $club_name = $club_id > 0 ? (string) get_the_title($club_id) : '—';
            $club_link = $club_id > 0 ? (string) get_permalink($club_id) : '';
            $club_logo = $club_id > 0 ? (string) $call('club_logo_html', $club_id, 'thumbnail', ['class' => 'opentt-transferi-club-grb']) : '';
            echo '<tr><td>' . esc_html($period) . '</td><td>';
            echo '<span class="opentt-transferi-club">';
            if ($club_logo) {
                echo $club_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            if ($club_link !== '') {
                echo '<a href="' . esc_url($club_link) . '">' . esc_html($club_name) . '</a>';
            } else {
                echo esc_html($club_name);
            }
            echo '</span>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        if (!empty($transfers_desc)) {
            echo '<div class="opentt-transferi-block">';
            echo '<h4>Promene kluba</h4>';
            echo '<table class="opentt-transferi-table"><thead><tr><th>Sezona</th><th>Transfer</th></tr></thead><tbody>';
            foreach ($transfers_desc as $t) {
                $season_label = (string) $call('season_display_name', (string) $t['season_slug']);
                $from_id = intval($t['from_club_id']);
                $to_id = intval($t['to_club_id']);
                $from_name = $from_id > 0 ? (string) get_the_title($from_id) : '—';
                $to_name = $to_id > 0 ? (string) get_the_title($to_id) : '—';
                $from_logo = $from_id > 0 ? (string) $call('club_logo_html', $from_id, 'thumbnail', ['class' => 'opentt-transferi-club-grb']) : '';
                $to_logo = $to_id > 0 ? (string) $call('club_logo_html', $to_id, 'thumbnail', ['class' => 'opentt-transferi-club-grb']) : '';
                echo '<tr><td>' . esc_html($season_label) . '</td><td>';
                echo '<span class="opentt-transferi-move">';
                echo '<span class="opentt-transferi-club">';
                if ($from_logo) {
                    echo $from_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                echo '<span>' . esc_html($from_name) . '</span>';
                echo '</span>';
                echo '<span class="opentt-transferi-arrow">-></span>';
                echo '<span class="opentt-transferi-club">';
                if ($to_logo) {
                    echo $to_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                echo '<span>' . esc_html($to_name) . '</span>';
                echo '</span>';
                echo '</span>';
                echo '</td></tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        }

        echo '</section>';
        return ob_get_clean();
    }
}

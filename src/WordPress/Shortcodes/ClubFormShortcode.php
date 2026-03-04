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

final class ClubFormShortcode
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
            'klub' => '',
            'limit' => 5,
        ], $atts);

        $club_id = 0;
        if (!empty($atts['klub'])) {
            $lookup = sanitize_title((string) $atts['klub']);
            $post = get_page_by_path($lookup, OBJECT, 'klub');
            if (!$post) {
                $post = get_page_by_title((string) $atts['klub'], OBJECT, 'klub');
            }
            if ($post && !is_wp_error($post)) {
                $club_id = intval($post->ID);
            }
        } elseif (is_singular('klub')) {
            $club_id = intval(get_the_ID());
        }

        if ($club_id <= 0) {
            return '';
        }

        $limit = max(1, min(10, intval($atts['limit'])));
        $rows = $call('db_get_recent_club_matches', $club_id, $limit);
        $rows = is_array($rows) ? $rows : [];
        if (empty($rows)) {
            return (string) $call('shortcode_title_html', 'Forma kluba')
                . '<div class="opentt-forma-kluba"><p>Nema odigranih utakmica za formu kluba.</p></div>';
        }

        ob_start();
        echo (string) $call('shortcode_title_html', 'Forma kluba');
        echo '<div class="opentt-forma-kluba">';
        echo '<div class="opentt-forma-kluba-list">';
        foreach ($rows as $row) {
            $is_home = intval($row->home_club_post_id) === $club_id;
            $for_score = $is_home ? intval($row->home_score) : intval($row->away_score);
            $opp_score = $is_home ? intval($row->away_score) : intval($row->home_score);
            $won = $for_score > $opp_score;
            $status = $won ? 'pobeda' : 'poraz';
            $class = $won ? 'is-win' : 'is-loss';
            $home_score = intval($row->home_score);
            $away_score = intval($row->away_score);
            $home_team_class = ($home_score > $away_score) ? 'is-winner' : (($home_score < $away_score) ? 'is-loser' : '');
            $away_team_class = ($away_score > $home_score) ? 'is-winner' : (($away_score < $home_score) ? 'is-loser' : '');
            $home_id = intval($row->home_club_post_id);
            $away_id = intval($row->away_club_post_id);
            $home_name = $home_id > 0 ? (string) get_the_title($home_id) : '—';
            $away_name = $away_id > 0 ? (string) get_the_title($away_id) : '—';
            $home_logo = $home_id > 0 ? (string) $call('club_logo_html', $home_id, 'thumbnail') : '';
            $away_logo = $away_id > 0 ? (string) $call('club_logo_html', $away_id, 'thumbnail') : '';
            $date = (string) $call('display_match_date', (string) $row->match_date);
            $link = (string) $call('match_permalink', $row);

            echo '<a class="opentt-forma-item ' . esc_attr($class) . '" href="' . esc_url($link) . '" title="' . esc_attr($home_name . ' - ' . $away_name . ' • ' . $date . ' • ' . $status) . '">';
            echo '<span class="opentt-forma-main">';
            echo '<span class="opentt-forma-line">';
            echo '<span class="opentt-forma-team opentt-forma-home ' . esc_attr($home_team_class) . '">';
            echo '<span class="opentt-forma-logo">' . ($home_logo ?: '') . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="opentt-forma-name">' . esc_html($home_name) . '</span>';
            echo '</span>';
            echo '<span class="opentt-forma-score ' . esc_attr($home_team_class) . '">' . esc_html((string) intval($row->home_score)) . '</span>';
            echo '</span>';

            echo '<span class="opentt-forma-line">';
            echo '<span class="opentt-forma-team opentt-forma-away ' . esc_attr($away_team_class) . '">';
            echo '<span class="opentt-forma-logo">' . ($away_logo ?: '') . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="opentt-forma-name">' . esc_html($away_name) . '</span>';
            echo '</span>';
            echo '<span class="opentt-forma-score ' . esc_attr($away_team_class) . '">' . esc_html((string) intval($row->away_score)) . '</span>';
            echo '</span>';
            echo '</span>';

            echo '<span class="opentt-forma-side">';
            echo '<span class="opentt-forma-separator"></span>';
            echo '<span class="opentt-forma-status">' . esc_html($status) . '</span>';
            echo '</span>';
            echo '</a>';
        }
        echo '</div>';
        echo '</div>';

        return ob_get_clean();
    }
}

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

final class MatchReportShortcode
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

        $ctx = $call('current_match_context');
        if (!$ctx || empty($ctx['legacy_id'])) {
            return '';
        }
        $legacy_match_id = intval($ctx['legacy_id']);

        $q = new \WP_Query([
            'post_type' => 'post',
            'posts_per_page' => 1,
            'ignore_sticky_posts' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [[
                'key' => 'povezana_utakmica',
                'value' => $legacy_match_id,
                'compare' => '=',
            ]],
        ]);
        if (!$q->have_posts()) {
            return '';
        }

        ob_start();
        echo (string) $call('shortcode_title_html', 'Izveštaj utakmice');
        while ($q->have_posts()) {
            $q->the_post();
            $post_id = get_the_ID();
            $title = get_the_title();
            $excerpt = get_the_excerpt();
            $permalink = get_permalink();
            $thumbnail = get_the_post_thumbnail($post_id, 'medium');
            ?>
            <a href="<?php echo esc_url($permalink); ?>" class="izvestaj-utakmice-blok">
                <div class="izvestaj-leva-kolona">
                    <?php echo $thumbnail ?: ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <div class="izvestaj-desna-kolona">
                    <h3><?php echo esc_html($title); ?></h3>
                    <p><?php echo esc_html($excerpt); ?></p>
                </div>
            </a>
            <?php
        }
        wp_reset_postdata();
        return ob_get_clean();
    }
}

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

final class ShowClubByNameShortcode
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

        $atts = shortcode_atts(['klub' => ''], $atts);
        $name = trim((string) $atts['klub']);
        if ($name === '') {
            return '';
        }

        $post = get_page_by_path($name, OBJECT, 'klub');
        if (!$post) {
            $post = get_page_by_title($name, OBJECT, 'klub');
        }
        if (!$post || is_wp_error($post)) {
            return '<p>Klub nije pronađen: ' . esc_html($name) . '</p>';
        }

        return (string) $call('shortcode_title_html', 'Klub')
            . (string) $call('render_klub_card_html', intval($post->ID));
    }
}

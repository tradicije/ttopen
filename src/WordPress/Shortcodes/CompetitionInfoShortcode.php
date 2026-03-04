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

final class CompetitionInfoShortcode
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
            'liga' => '',
            'sezona' => '',
            'show_logo' => '1',
        ], $atts);

        $liga_slug = '';
        $sezona_slug = '';
        $archive_ctx = $call('current_archive_context');
        $liga_param = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona_param = sanitize_title((string) ($atts['sezona'] ?? ''));

        if ($liga_param !== '') {
            $parsed = (array) $call('parse_legacy_liga_sezona', $liga_param, $sezona_param);
            $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? $liga_param));
            $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? $sezona_param));
            if ($sezona_param !== '') {
                $sezona_slug = $sezona_param;
            }
        } elseif (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona') {
            $raw_liga = sanitize_title((string) ($archive_ctx['liga_slug'] ?? ''));
            $raw_sezona = sanitize_title((string) ($archive_ctx['sezona_slug'] ?? ''));
            $parsed_ctx = (array) $call('parse_legacy_liga_sezona', $raw_liga, $raw_sezona);
            $liga_slug = sanitize_title((string) ($parsed_ctx['league_slug'] ?? $raw_liga));
            $sezona_slug = sanitize_title((string) ($parsed_ctx['season_slug'] ?? $raw_sezona));
            if ($liga_slug === '' && $sezona_slug !== '') {
                global $wpdb;
                $table = (string) $call('db_table', 'matches');
                if ((bool) $call('table_exists', $table)) {
                    $liga_guess = $wpdb->get_var($wpdb->prepare("SELECT liga_slug FROM {$table} WHERE sezona_slug=%s AND liga_slug<>'' ORDER BY id DESC LIMIT 1", $sezona_slug));
                    if (is_string($liga_guess) && $liga_guess !== '') {
                        $liga_slug = sanitize_title($liga_guess);
                    }
                }
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
            }
        }

        if ($liga_slug === '') {
            return '';
        }

        $liga_name = (string) $call('slug_to_title', $liga_slug);
        if ($liga_name === '') {
            $liga_name = $liga_slug;
        }
        $sezona_name = (string) $call('season_display_name', $sezona_slug);

        $rule = null;
        if ($sezona_slug !== '') {
            $rule = $call('get_competition_rule_data', $liga_slug, $sezona_slug);
        }

        $savez_label = '';
        $savez_url = '';
        $thumb_html = '';

        if (is_array($rule)) {
            $savez = $call('competition_federation_data', (string) ($rule['savez'] ?? ''));
            if (is_array($savez)) {
                $savez_label = (string) ($savez['label'] ?? '');
                $savez_url = (string) ($savez['url'] ?? '');
            }

            $rule_id = intval($rule['id'] ?? 0);
            if ($rule_id > 0 && (string) $atts['show_logo'] !== '0' && has_post_thumbnail($rule_id)) {
                $thumb_html = get_the_post_thumbnail($rule_id, 'medium', ['class' => 'opentt-takmicenje-info-logo-img']);
            }
        }

        ob_start();
        ?>
        <?php echo (string) $call('shortcode_title_html', 'Info takmičenja'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <div class="opentt-takmicenje-info">
            <?php if ($thumb_html !== ''): ?>
                <div class="opentt-takmicenje-info-logo"><?php echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
            <div class="opentt-takmicenje-info-body">
                <h2 class="opentt-takmicenje-info-title"><?php echo esc_html($liga_name); ?></h2>
                <?php if ($sezona_name !== ''): ?>
                    <div class="opentt-takmicenje-info-meta"><strong>Sezona:</strong> <?php echo esc_html($sezona_name); ?></div>
                <?php endif; ?>
                <?php if ($savez_label !== ''): ?>
                    <div class="opentt-takmicenje-info-meta">
                        <strong>Savez:</strong>
                        <?php if ($savez_url !== ''): ?>
                            <a href="<?php echo esc_url($savez_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($savez_label); ?></a>
                        <?php else: ?>
                            <?php echo esc_html($savez_label); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

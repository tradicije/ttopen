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

final class VisualSettings
{
    public static function defaultSettings()
    {
        return [
            'container_bg' => '#000a26',
            'container_border' => '#2b3d6c',
            'title_color' => '#ffffff',
            'text_color' => '#c7d7ff',
            'accent_color' => '#0084ff',
            'radius' => 8,
            'show_shortcode_titles' => 1,
        ];
    }

    public static function sanitize($raw)
    {
        $defaults = self::defaultSettings();
        $in = is_array($raw) ? $raw : [];
        $out = $defaults;

        foreach (['container_bg', 'container_border', 'title_color', 'text_color', 'accent_color'] as $key) {
            if (!isset($in[$key])) {
                continue;
            }
            $value = sanitize_hex_color((string) $in[$key]);
            if (is_string($value) && $value !== '') {
                $out[$key] = $value;
            }
        }

        if (isset($in['radius'])) {
            $out['radius'] = max(0, min(32, (int) $in['radius']));
        }

        $showTitles = isset($in['show_shortcode_titles']) ? (int) $in['show_shortcode_titles'] : 0;
        $out['show_shortcode_titles'] = $showTitles === 1 ? 1 : 0;

        return $out;
    }

    public static function get($optionKey)
    {
        $saved = get_option((string) $optionKey, []);
        return self::sanitize($saved);
    }

    public static function shouldShowShortcodeTitles(array $settings)
    {
        return !empty($settings['show_shortcode_titles']);
    }

    public static function buildCss($settings)
    {
        $s = self::sanitize($settings);
        $radius = (int) $s['radius'] . 'px';
        $bg = $s['container_bg'];
        $border = $s['container_border'];
        $title = $s['title_color'];
        $text = $s['text_color'];
        $accent = $s['accent_color'];

        $containers = implode(',', [
            '.opentt-ekipe',
            '.opentt-info-kluba',
            '.opentt-info-igraca',
            '.opentt-forma-kluba',
            '.opentt-stat-igraca',
            '.opentt-stat-ekipe',
            '.opentt-transferi',
            '.opentt-takmicenje-info',
            '.opentt-prikaz-takmicenja-card',
            '.stoni-igraci-list',
            '.top-igraci-list',
            '.no-players-message',
            '.lp2-lista',
            '.mvp-box',
            '.h2h-box',
            '.snimak-utakmice-section',
            '.video-wrapper',
            '.bbs-related-posts',
            '.stoni-vesti-grid',
            '.stoni-vesti-kartica',
            '.izvestaj-utakmice-blok',
            '.tabela-lige',
            '.opentt-stat-ekipe-table',
            '.opentt-item',
            '.opentt-klubovi-item',
        ]);

        $subcards = implode(',', [
            '.stoni-igrac-card',
            '.igrac-card-list',
            '.related-post-item',
            '.opentt-stat-card',
            '.opentt-stat-ekipe-card',
            '.opentt-stat-ekipe-mvp',
            '.opentt-transferi-table',
            '.opentt-grid-filters select',
            '.opentt-stat-igraca-filter select',
            '.opentt-stat-ekipe-filter select',
            '.opentt-grid-filter-reset',
            '.opentt-klubovi-filters select',
            '.opentt-klubovi-filter-reset',
        ]);

        $titleSelectors = implode(',', [
            '.opentt-ekipe h1', '.opentt-ekipe h2', '.opentt-ekipe h3',
            '.opentt-info-kluba h1', '.opentt-info-kluba h2', '.opentt-info-kluba h3',
            '.opentt-info-igraca h1', '.opentt-info-igraca h2', '.opentt-info-igraca h3',
            '.opentt-forma-kluba h1', '.opentt-forma-kluba h2', '.opentt-forma-kluba h3',
            '.opentt-stat-igraca h1', '.opentt-stat-igraca h2', '.opentt-stat-igraca h3',
            '.opentt-stat-ekipe h1', '.opentt-stat-ekipe h2', '.opentt-stat-ekipe h3',
            '.opentt-transferi h1', '.opentt-transferi h2', '.opentt-transferi h3',
            '.opentt-takmicenje-info h1', '.opentt-takmicenje-info h2', '.opentt-takmicenje-info h3',
            '.opentt-prikaz-takmicenja-card h1', '.opentt-prikaz-takmicenja-card h2', '.opentt-prikaz-takmicenja-card h3',
            '.opentt-klubovi h1', '.opentt-klubovi h2', '.opentt-klubovi h3',
            '.stoni-igraci-list h1', '.stoni-igraci-list h2', '.stoni-igraci-list h3',
            '.top-igraci-list h1', '.top-igraci-list h2', '.top-igraci-list h3',
            '.tabela-lige th',
            '.opentt-stat-ekipe-table th',
            '.opentt-item .team strong',
            '.opentt-item .team span',
            '.opentt-prikaz-takmicenja-title',
            '.opentt-info-kluba-ime',
            '.opentt-info-igraca-ime',
            '.vest-klub-naslov',
            '.opentt-klubovi-name',
        ]);

        $textSelectors = implode(',', [
            '.opentt-ekipe',
            '.opentt-info-kluba',
            '.opentt-info-igraca',
            '.opentt-forma-kluba',
            '.opentt-stat-igraca',
            '.opentt-stat-ekipe',
            '.opentt-transferi',
            '.opentt-takmicenje-info',
            '.opentt-prikaz-takmicenja-card',
            '.stoni-igraci-list',
            '.stoni-igrac-card',
            '.top-igraci-list',
            '.igrac-card-list',
            '.lp2-lista',
            '.mvp-box',
            '.h2h-box',
            '.bbs-related-posts',
            '.related-post-item',
            '.tabela-lige td',
            '.opentt-stat-ekipe-table td',
            '.opentt-item .meta',
            '.opentt-item .team span',
            '.opentt-grid-filters label',
            '.opentt-stat-igraca-filter label',
            '.opentt-stat-ekipe-filter label',
            '.opentt-prikaz-takmicenja-season',
            '.opentt-info-kluba-podnaslov',
            '.opentt-info-igraca-klub',
            '.opentt-competition-federation',
            '.vest-klub-datum',
            '.opentt-klubovi-league',
            '.opentt-klubovi-city',
            '.opentt-klubovi-filters label',
        ]);

        $linkSelectors = implode(',', [
            '.opentt-ekipe a',
            '.opentt-info-kluba a',
            '.opentt-info-igraca a',
            '.opentt-forma-kluba a',
            '.opentt-stat-igraca a',
            '.opentt-stat-ekipe a',
            '.opentt-transferi a',
            '.opentt-takmicenje-info a',
            '.opentt-prikaz-takmicenja-card a',
            '.opentt-klubovi a',
            '.stoni-igraci-list a',
            '.stoni-igrac-card a',
            '.top-igraci-list a',
            '.bbs-related-posts a',
            '.stoni-vesti-kartica a',
            '.tabela-lige .klub-cell a',
            '.opentt-stat-ekipe-table .club a',
            '.opentt-item a',
        ]);

        $linkHoverSelectors = implode(',', [
            '.opentt-ekipe a:hover',
            '.opentt-info-kluba a:hover',
            '.opentt-info-igraca a:hover',
            '.opentt-forma-kluba a:hover',
            '.opentt-stat-igraca a:hover',
            '.opentt-stat-ekipe a:hover',
            '.opentt-transferi a:hover',
            '.opentt-takmicenje-info a:hover',
            '.opentt-prikaz-takmicenja-card a:hover',
            '.opentt-klubovi a:hover',
            '.stoni-igraci-list a:hover',
            '.stoni-igrac-card a:hover',
            '.top-igraci-list a:hover',
            '.bbs-related-posts a:hover',
            '.stoni-vesti-kartica a:hover',
            '.tabela-lige .klub-cell a:hover',
            '.opentt-stat-ekipe-table .club a:hover',
            '.opentt-item a:hover',
        ]);

        return implode("\n", [
            ':root{--opentt-box-bg:' . $bg . ';--opentt-box-border:' . $border . ';--opentt-box-title:' . $title . ';--opentt-box-text:' . $text . ';--opentt-box-accent:' . $accent . ';--opentt-box-radius:' . $radius . ';}',
            $containers . '{background:var(--opentt-box-bg);border-color:var(--opentt-box-border);border-radius:var(--opentt-box-radius);}',
            $subcards . '{background:var(--opentt-box-bg);border-color:var(--opentt-box-border);border-radius:calc(var(--opentt-box-radius) - 4px);}',
            $textSelectors . '{color:var(--opentt-box-text);}',
            $titleSelectors . '{color:var(--opentt-box-title);}',
            '.opentt-grid-filters select,.opentt-stat-igraca-filter select,.opentt-stat-ekipe-filter select,.opentt-grid-filter-reset,.opentt-klubovi-filters select,.opentt-klubovi-filter-reset{color:var(--opentt-box-title);}',
            $linkSelectors . '{color:var(--opentt-box-title) !important;}',
            $linkHoverSelectors . '{color:var(--opentt-box-accent) !important;}',
            '.stoni-vesti-kartica,.opentt-item,.opentt-prikaz-takmicenja-card,.opentt-klubovi-item,.related-post-item,.opentt-forma-item,.izvestaj-utakmice-blok{overflow:hidden;}',
        ]);
    }
}

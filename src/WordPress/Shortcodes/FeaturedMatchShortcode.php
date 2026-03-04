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

final class FeaturedMatchShortcode
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
            'id' => 0,
            'liga' => '',
            'sezona' => '',
            'title' => 'Featured match',
        ], $atts);

        $match = self::resolveFeaturedMatch($atts);
        if (!$match) {
            return '<p>Nema featured utakmice za prikaz.</p>';
        }

        $homeId = intval($match->home_club_post_id ?? 0);
        $awayId = intval($match->away_club_post_id ?? 0);
        $homeName = $homeId > 0 ? (string) get_the_title($homeId) : '';
        $awayName = $awayId > 0 ? (string) get_the_title($awayId) : '';
        $homeLogo = $homeId > 0 ? (string) $call('club_logo_html', $homeId, 'thumbnail', ['class' => 'opentt-featured-team-logo']) : '';
        $awayLogo = $awayId > 0 ? (string) $call('club_logo_html', $awayId, 'thumbnail', ['class' => 'opentt-featured-team-logo']) : '';
        $homeColor = self::clubJerseyColor($homeId, '#0b4db8');
        $awayColor = self::clubJerseyColor($awayId, '#0084ff');
        $matchLink = (string) $call('match_permalink', $match);
        if ($matchLink === '') {
            $matchLink = home_url('/');
        }

        $liga = sanitize_title((string) ($match->liga_slug ?? ''));
        $sezona = sanitize_title((string) ($match->sezona_slug ?? ''));
        $kolo = sanitize_title((string) ($match->kolo_slug ?? ''));
        $ligaLabel = (string) $call('slug_to_title', $liga);
        $sezonaLabel = (string) $call('slug_to_title', $sezona);
        $koloLabel = (string) $call('kolo_name_from_slug', $kolo);

        $metaTop = trim(implode(' • ', array_values(array_filter([$ligaLabel, $sezonaLabel, $koloLabel]))));
        $location = self::matchLocationLabel($homeId);
        $centerLabel = self::centerLabel($match);
        $targetDate = self::matchTargetDateAttr((string) ($match->match_date ?? ''));
        $uid = 'opentt-featured-' . wp_unique_id();

        ob_start();
        echo '<div class="opentt-featured-match-wrap">';
        echo (string) $call('shortcode_title_html', (string) $atts['title']);
        echo '<a id="' . esc_attr($uid) . '" class="opentt-featured-match-card" href="' . esc_url($matchLink) . '" style="--opentt-featured-home:' . esc_attr($homeColor) . ';--opentt-featured-away:' . esc_attr($awayColor) . ';">';
        if ($metaTop !== '') {
            echo '<div class="opentt-featured-meta-top">' . esc_html($metaTop) . '</div>';
        }
        echo '<div class="opentt-featured-main">';
        echo '<div class="opentt-featured-team home">';
        echo '<div class="opentt-featured-team-crest">' . $homeLogo . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="opentt-featured-team-name">' . esc_html($homeName) . '</div>';
        echo '</div>';
        echo '<div class="opentt-featured-center">';
        echo '<div class="opentt-featured-countdown" data-opentt-target="' . esc_attr($targetDate) . '">' . esc_html($centerLabel) . '</div>';
        echo '</div>';
        echo '<div class="opentt-featured-team away">';
        echo '<div class="opentt-featured-team-crest">' . $awayLogo . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="opentt-featured-team-name">' . esc_html($awayName) . '</div>';
        echo '</div>';
        echo '</div>';
        if ($location !== '') {
            echo '<div class="opentt-featured-meta-bottom">Lokacija: ' . esc_html($location) . '</div>';
        }
        echo '</a>';
        echo '</div>';
        ?>
        <script>
        (function(){
            var root = document.getElementById('<?php echo esc_js($uid); ?>');
            if (!root) { return; }
            var el = root.querySelector('.opentt-featured-countdown');
            if (!el) { return; }
            var target = String(el.getAttribute('data-opentt-target') || '');
            if (!target) { return; }
            var ts = Date.parse(target);
            if (isNaN(ts)) { return; }

            function pad(n) { return n < 10 ? ('0' + n) : String(n); }
            function update() {
                var diff = ts - Date.now();
                if (diff <= 0) {
                    el.textContent = 'U toku / završena';
                    return;
                }
                var sec = Math.floor(diff / 1000);
                var days = Math.floor(sec / 86400);
                var hours = Math.floor((sec % 86400) / 3600);
                var minutes = Math.floor((sec % 3600) / 60);
                var seconds = sec % 60;
                if (days > 0) {
                    el.textContent = days + 'd ' + pad(hours) + 'h ' + pad(minutes) + 'm';
                } else {
                    el.textContent = pad(hours) + 'h ' + pad(minutes) + 'm ' + pad(seconds) + 's';
                }
            }
            update();
            setInterval(update, 1000);
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    private static function resolveFeaturedMatch(array $atts)
    {
        global $wpdb;
        $table = \OpenTT_Unified_Core::db_table('matches');
        if (!$table) {
            return null;
        }
        $tableExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($tableExists !== $table) {
            return null;
        }

        $id = intval($atts['id'] ?? 0);
        if ($id > 0) {
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d LIMIT 1", $id));
        }

        $liga = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona = sanitize_title((string) ($atts['sezona'] ?? ''));
        $where = ['featured=1'];
        $params = [];
        if ($liga !== '') {
            $where[] = 'liga_slug=%s';
            $params[] = $liga;
        }
        if ($sezona !== '') {
            $where[] = 'sezona_slug=%s';
            $params[] = $sezona;
        }
        $now = current_time('mysql');
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where)
            . " ORDER BY (CASE WHEN match_date >= %s THEN 0 ELSE 1 END) ASC, ABS(TIMESTAMPDIFF(SECOND, %s, match_date)) ASC, id DESC LIMIT 1";
        $params[] = $now;
        $params[] = $now;
        $sql = $wpdb->prepare($sql, $params);
        return $wpdb->get_row($sql);
    }

    private static function clubJerseyColor($clubId, $fallback)
    {
        $clubId = intval($clubId);
        if ($clubId <= 0) {
            return $fallback;
        }
        $raw = (string) get_post_meta($clubId, 'boja_dresa', true);
        $color = sanitize_hex_color($raw);
        return $color ? $color : $fallback;
    }

    private static function centerLabel($match)
    {
        $played = intval($match->played ?? 0) === 1;
        if ($played) {
            return intval($match->home_score ?? 0) . ' : ' . intval($match->away_score ?? 0);
        }
        $dateRaw = (string) ($match->match_date ?? '');
        $ts = strtotime($dateRaw);
        if ($ts !== false) {
            return wp_date('H:i', $ts) . 'h';
        }
        return 'Uskoro';
    }

    private static function matchLocationLabel($homeId)
    {
        $homeId = intval($homeId);
        if ($homeId <= 0) {
            return '';
        }
        $hall = trim((string) get_post_meta($homeId, 'adresa_sale', true));
        if ($hall !== '') {
            return $hall;
        }
        $clubAddress = trim((string) get_post_meta($homeId, 'adresa_kluba', true));
        if ($clubAddress !== '') {
            return $clubAddress;
        }
        return trim((string) get_post_meta($homeId, 'grad', true));
    }

    private static function matchTargetDateAttr($matchDate)
    {
        $matchDate = trim((string) $matchDate);
        if ($matchDate === '') {
            return '';
        }
        $ts = strtotime($matchDate);
        if ($ts === false) {
            return '';
        }
        return wp_date('c', $ts);
    }
}

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

final class ShowMatchTeamsShortcode
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
        if (!$ctx || empty($ctx['db_row'])) {
            return '';
        }

        $row = $ctx['db_row'];
        $home_id = intval($row->home_club_post_id);
        $away_id = intval($row->away_club_post_id);
        if ($home_id <= 0 || $away_id <= 0) {
            return '';
        }

        $liga_slug = sanitize_title((string) $row->liga_slug);
        $sezona_slug = sanitize_title((string) $row->sezona_slug);
        $kolo_slug = sanitize_title((string) $row->kolo_slug);

        $competition_name = (string) $call('competition_display_name', $liga_slug, $sezona_slug);
        $competition_url = (string) $call('competition_archive_url', $liga_slug, $sezona_slug);

        $kolo_name = (string) $call('kolo_name_from_slug', $kolo_slug);
        $kolo_url = '';
        if ($kolo_slug !== '') {
            $kolo_term = get_term_by('slug', $kolo_slug, 'kolo');
            if ($kolo_term && !is_wp_error($kolo_term)) {
                $term_link = get_term_link($kolo_term);
                if (!is_wp_error($term_link)) {
                    $kolo_url = (string) $term_link;
                }
            }
        }

        $home_name = (string) get_the_title($home_id);
        $away_name = (string) get_the_title($away_id);
        $home_url = (string) get_permalink($home_id);
        $away_url = (string) get_permalink($away_id);
        $home_logo = (string) $call('club_logo_html', $home_id, 'thumbnail');
        $away_logo = (string) $call('club_logo_html', $away_id, 'thumbnail');
        $home_score = intval($row->home_score);
        $away_score = intval($row->away_score);
        $played_flag = isset($row->played) ? intval($row->played) : null;
        $is_score_zero = ($home_score === 0 && $away_score === 0);
        $match_raw_date = (string) ($row->match_date ?? '');
        $match_ts = self::matchTimestamp($match_raw_date);
        $now_ts = current_time('timestamp');
        $is_live_match = intval($row->live ?? 0) === 1;
        $is_future_match = ($match_ts !== false && intval($match_ts) > intval($now_ts));
        $is_unplayed = ($played_flag !== null ? $played_flag !== 1 : false) || $is_future_match || $is_score_zero;
        $target_date = self::matchTargetDateAttr($match_raw_date);
        $countdown_uid = 'opentt-ekipe-countdown-' . wp_unique_id();
        $match_time_label = '';
        if (preg_match('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $match_raw_date, $time_parts)) {
            $hour = intval($time_parts[1]);
            $minute = (string) ($time_parts[2] ?? '00');
            $match_time_label = ($minute === '00') ? ($hour . 'h') : (sprintf('%02d:%s h', $hour, $minute));
        }
        $home_state = '';
        $away_state = '';
        if (!$is_unplayed && $home_score > $away_score) {
            $home_state = 'pobednik';
            $away_state = 'gubitnik';
        } elseif (!$is_unplayed && $away_score > $home_score) {
            $home_state = 'gubitnik';
            $away_state = 'pobednik';
        }
        $match_date = (string) $call('display_match_date', (string) $row->match_date);
        $match_venue = (string) $call('match_venue_label', $row);

        ob_start();
        ?>
        <?php echo (string) $call('shortcode_title_html', 'Prikaz ekipa'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <div class="opentt-ekipe">
            <div class="opentt-ekipe-meta">
                <?php if ($competition_url !== ''): ?>
                    <a href="<?php echo esc_url($competition_url); ?>" class="opentt-ekipe-meta-link"><?php echo esc_html($competition_name); ?></a>
                <?php else: ?>
                    <span class="opentt-ekipe-meta-text"><?php echo esc_html($competition_name); ?></span>
                <?php endif; ?>
                <?php if ($kolo_name !== ''): ?>
                    <span class="opentt-ekipe-meta-sep">•</span>
                    <?php if ($kolo_url !== ''): ?>
                        <a href="<?php echo esc_url($kolo_url); ?>" class="opentt-ekipe-meta-link"><?php echo esc_html($kolo_name); ?></a>
                    <?php else: ?>
                        <span class="opentt-ekipe-meta-text"><?php echo esc_html($kolo_name); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="opentt-ekipe-row">
                <a href="<?php echo esc_url($home_url); ?>" class="opentt-ekipe-team opentt-ekipe-home <?php echo esc_attr($home_state); ?>">
                    <span class="opentt-ekipe-logo-wrap">
                        <?php echo $home_logo ? $home_logo : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="opentt-ekipe-name"><?php echo esc_html($home_name); ?></span>
                </a>

                <?php if ($is_live_match): ?>
                    <div class="opentt-ekipe-score opentt-ekipe-score-time opentt-ekipe-score-live">
                        <span class="opentt-ekipe-live-score"><?php echo esc_html((string) $home_score); ?></span>
                        <span class="opentt-live-badge">LIVE</span>
                        <span class="opentt-ekipe-live-score"><?php echo esc_html((string) $away_score); ?></span>
                    </div>
                <?php elseif ($is_unplayed && $match_time_label !== ''): ?>
                    <div class="opentt-ekipe-score opentt-ekipe-score-time">
                        <span class="opentt-ekipe-time-label">Početak utakmice za:</span>
                        <span id="<?php echo esc_attr($countdown_uid); ?>" class="opentt-ekipe-time" data-opentt-target="<?php echo esc_attr($target_date); ?>"><?php echo esc_html($match_time_label); ?></span>
                    </div>
                <?php else: ?>
                    <div class="opentt-ekipe-score">
                        <span class="<?php echo esc_attr($home_state); ?>"><?php echo esc_html((string) $home_score); ?></span>
                        <span class="opentt-ekipe-score-sep">:</span>
                        <span class="<?php echo esc_attr($away_state); ?>"><?php echo esc_html((string) $away_score); ?></span>
                    </div>
                <?php endif; ?>

                <a href="<?php echo esc_url($away_url); ?>" class="opentt-ekipe-team opentt-ekipe-away <?php echo esc_attr($away_state); ?>">
                    <span class="opentt-ekipe-logo-wrap">
                        <?php echo $away_logo ? $away_logo : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="opentt-ekipe-name"><?php echo esc_html($away_name); ?></span>
                </a>
            </div>

            <?php if ($match_venue !== '' || $match_date !== ''): ?>
                <div class="opentt-ekipe-footer">
                    <?php if ($match_venue !== ''): ?>
                        <span class="opentt-ekipe-footer-item opentt-ekipe-footer-venue"><?php echo esc_html($match_venue); ?></span>
                    <?php endif; ?>
                    <?php if ($match_venue !== '' && $match_date !== ''): ?>
                        <span class="opentt-ekipe-footer-sep">•</span>
                    <?php endif; ?>
                    <?php if ($match_date !== ''): ?>
                        <span class="opentt-ekipe-footer-item opentt-ekipe-footer-date"><?php echo esc_html($match_date); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($is_unplayed && !$is_live_match): ?>
        <script>
        (function(){
            var el = document.getElementById('<?php echo esc_js($countdown_uid); ?>');
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
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    private static function matchTargetDateAttr($matchDate)
    {
        $matchDate = trim((string) $matchDate);
        if ($matchDate === '') {
            return '';
        }
        $ts = self::matchTimestamp($matchDate);
        if ($ts === false) {
            return '';
        }
        return wp_date('c', $ts);
    }

    private static function matchTimestamp($matchDate)
    {
        $matchDate = trim((string) $matchDate);
        if ($matchDate === '' || $matchDate === '0000-00-00 00:00:00') {
            return false;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $matchDate)) {
            $matchDate .= ' 00:00:00';
        }

        $tz = wp_timezone();
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T]+(\d{1,2}):(\d{2})(?::(\d{2}))?)?/', $matchDate, $m)) {
            $year = intval($m[1]);
            $month = intval($m[2]);
            $day = intval($m[3]);
            $hour = isset($m[4]) ? intval($m[4]) : 0;
            $minute = isset($m[5]) ? intval($m[5]) : 0;
            $second = isset($m[6]) ? intval($m[6]) : 0;
            if (checkdate($month, $day, $year) && $hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59 && $second >= 0 && $second <= 59) {
                $dt = (new \DateTimeImmutable('now', $tz))
                    ->setDate($year, $month, $day)
                    ->setTime($hour, $minute, $second);
                return $dt->getTimestamp();
            }
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d G:i:s', 'Y-m-d H:i', 'Y-m-d G:i'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $matchDate, $tz);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt->getTimestamp();
            }
        }

        return false;
    }
}

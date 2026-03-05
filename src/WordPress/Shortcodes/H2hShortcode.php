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

final class H2hShortcode
{
    public static function render($atts = [], array $deps = [])
    {
        $call = static function ($name, ...$args) use ($deps) {
            $name = (string) $name;
            if (!isset($deps[$name]) || !is_callable($deps[$name])) {
                return null;
            }
            return $deps[$name](...$args);
        };

        $ctx = $call('current_match_context');
        if (!is_array($ctx) || empty($ctx['db_row'])) {
            return '';
        }

        $cur = $ctx['db_row'];
        $home_id = intval($cur->home_club_post_id);
        $away_id = intval($cur->away_club_post_id);
        if ($home_id <= 0 || $away_id <= 0) {
            return '';
        }

        $rows = $call('db_get_h2h_matches', intval($cur->id), $home_id, $away_id);
        $rows = is_array($rows) ? $rows : [];
        if (empty($rows)) {
            return '';
        }

        ob_start();
        echo (string) $call('shortcode_title_html', 'Međusobni dueli');
        foreach ($rows as $row) {
            $rd = intval($row->home_score);
            $rg = intval($row->away_score);
            $is_played = intval($row->played ?? 0) === 1 || $rd > 0 || $rg > 0;
            $is_live = self::isLiveMatch($row);
            $hide_score = !$is_played && $rd === 0 && $rg === 0;

            $domacin_id = intval($row->home_club_post_id);
            $gost_id = intval($row->away_club_post_id);
            if ($domacin_id <= 0 || $gost_id <= 0) {
                continue;
            }

            $domacin_title = get_the_title($domacin_id);
            $gost_title = get_the_title($gost_id);
            $grb_d = (string) $call('club_logo_url', $domacin_id, 'thumbnail');
            $grb_g = (string) $call('club_logo_url', $gost_id, 'thumbnail');

            $pobednik = null;
            if ($rd === 4) {
                $pobednik = 'domacin';
            } elseif ($rg === 4) {
                $pobednik = 'gost';
            }

            $datum = self::displayMatchDate((string) ($row->match_date ?? ''));
            $vreme = self::displayMatchTime((string) ($row->match_date ?? ''));
            $link = (string) $call('match_permalink', $row);
            ?>
            <a href="<?php echo esc_url($link); ?>" class="h2h-box">
                <div class="h2h-main">
                    <div class="h2h-teams">
                        <div class="h2h-club">
                            <?php if ($grb_d): ?><img src="<?php echo esc_url($grb_d); ?>" alt="<?php echo esc_attr($domacin_title); ?>"><?php endif; ?>
                            <span class="h2h-ime <?php echo esc_attr($pobednik === 'domacin' ? 'pobednik' : 'gubitnik'); ?>"><?php echo esc_html($domacin_title); ?></span>
                            <?php if (!$hide_score): ?>
                                <span class="h2h-rez <?php echo esc_attr($pobednik === 'domacin' ? 'pobednik' : 'gubitnik'); ?>"><?php echo intval($rd); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="h2h-club">
                            <?php if ($grb_g): ?><img src="<?php echo esc_url($grb_g); ?>" alt="<?php echo esc_attr($gost_title); ?>"><?php endif; ?>
                            <span class="h2h-ime <?php echo esc_attr($pobednik === 'gost' ? 'pobednik' : 'gubitnik'); ?>"><?php echo esc_html($gost_title); ?></span>
                            <?php if (!$hide_score): ?>
                                <span class="h2h-rez <?php echo esc_attr($pobednik === 'gost' ? 'pobednik' : 'gubitnik'); ?>"><?php echo intval($rg); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="h2h-side" aria-label="Vreme utakmice">
                        <span class="h2h-side-top"><?php echo esc_html($datum); ?></span>
                        <span class="h2h-side-bottom"><?php echo $is_live ? '<span class="opentt-live-badge">LIVE</span>' : esc_html($is_played ? 'Kraj' : ($vreme !== '' ? $vreme : '--:--')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    </div>
                </div>
            </a>
            <?php
        }

        return ob_get_clean();
    }

    private static function displayMatchDate($matchDate)
    {
        $matchDate = (string) $matchDate;
        if ($matchDate === '' || $matchDate === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = self::matchTimestamp($matchDate);
        if ($ts === false) {
            return '';
        }
        return date_i18n('d.m.Y.', $ts);
    }

    private static function displayMatchTime($matchDate)
    {
        $matchDate = (string) $matchDate;
        if ($matchDate === '' || $matchDate === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = self::matchTimestamp($matchDate);
        if ($ts === false) {
            return '';
        }
        return date_i18n('H:i', $ts);
    }

    private static function isLiveMatch($row)
    {
        if (!is_object($row)) {
            return false;
        }
        $homeScore = intval($row->home_score ?? 0);
        $awayScore = intval($row->away_score ?? 0);
        if ($homeScore >= 4 || $awayScore >= 4) {
            return false;
        }
        $matchDate = trim((string) ($row->match_date ?? ''));
        if ($matchDate === '' || $matchDate === '0000-00-00 00:00:00') {
            return false;
        }
        $ts = self::matchTimestamp($matchDate);
        if ($ts === false) {
            return false;
        }
        return intval($ts) <= intval(current_time('timestamp'));
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

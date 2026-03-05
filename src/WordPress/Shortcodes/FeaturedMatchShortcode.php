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
            'mode' => 'manual',
            'liga' => '',
            'sezona' => '',
            'title' => 'Featured match',
        ], $atts);

        $match = self::resolveMatch($atts, $call);
        if (!$match) {
            return '<p>Nema featured utakmice za prikaz.</p>';
        }

        $homeId = intval($match->home_club_post_id ?? 0);
        $awayId = intval($match->away_club_post_id ?? 0);
        $homeName = $homeId > 0 ? (string) get_the_title($homeId) : '';
        $awayName = $awayId > 0 ? (string) get_the_title($awayId) : '';
        $homeLogo = $homeId > 0 ? (string) $call('club_logo_html', $homeId, 'thumbnail', ['class' => 'opentt-featured-team-logo']) : '';
        $awayLogo = $awayId > 0 ? (string) $call('club_logo_html', $awayId, 'thumbnail', ['class' => 'opentt-featured-team-logo']) : '';
        $homeScore = intval($match->home_score ?? 0);
        $awayScore = intval($match->away_score ?? 0);
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
        $location = self::matchLocationLabel($match);
        $isLive = self::isLiveMatch($match);
        $centerLabel = self::centerLabel($match);
        $centerIntroLabel = self::centerIntroLabel($match);
        $targetDate = self::matchTargetDateAttr((string) ($match->match_date ?? ''));
        $uid = 'opentt-featured-' . wp_unique_id();

        ob_start();
        echo '<div class="opentt-featured-match-wrap">';
        echo (string) $call('shortcode_title_html', (string) $atts['title']);
        echo '<a id="' . esc_attr($uid) . '" class="opentt-featured-match-card' . ($isLive ? ' opentt-featured-live' : '') . '" href="' . esc_url($matchLink) . '" style="--opentt-featured-home:' . esc_attr($homeColor) . ';--opentt-featured-away:' . esc_attr($awayColor) . ';">';
        if ($metaTop !== '') {
            echo '<div class="opentt-featured-meta-top">' . esc_html($metaTop) . '</div>';
        }
        echo '<div class="opentt-featured-main">';
        echo '<div class="opentt-featured-team home">';
        echo '<div class="opentt-featured-team-crest">' . $homeLogo . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="opentt-featured-team-name">' . esc_html($homeName) . '</div>';
        echo '</div>';
        echo '<div class="opentt-featured-center">';
        if ($isLive) {
            echo '<div class="opentt-live-badge">LIVE</div>';
            echo '<div class="opentt-featured-live-score-row">';
            echo '<span class="opentt-featured-live-score">' . esc_html((string) $homeScore) . '</span>';
            echo '<span class="opentt-featured-live-sep">:</span>';
            echo '<span class="opentt-featured-live-score">' . esc_html((string) $awayScore) . '</span>';
            echo '</div>';
        } else {
            echo '<div class="opentt-featured-countdown-label">' . esc_html($centerIntroLabel) . '</div>';
            echo '<div class="opentt-featured-countdown" data-opentt-target="' . esc_attr($targetDate) . '">' . esc_html($centerLabel) . '</div>';
        }
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
        <?php if (!$isLive): ?>
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
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    private static function resolveMatch(array $atts, callable $call)
    {
        $id = intval($atts['id'] ?? 0);
        if ($id > 0) {
            return self::resolveMatchById($id);
        }

        $mode = sanitize_key((string) ($atts['mode'] ?? 'manual'));
        if ($mode !== 'auto' && $mode !== 'manual') {
            $mode = 'manual';
        }

        if ($mode === 'auto') {
            $auto = self::resolveAutoMatch($atts, $call);
            if ($auto) {
                return $auto;
            }
            return self::resolveManualFeaturedMatch($atts);
        }

        return self::resolveManualFeaturedMatch($atts);
    }

    private static function resolveMatchById($id)
    {
        global $wpdb;
        $table = \OpenTT_Unified_Core::db_table('matches');
        if (!self::matchesTableIsValid($table)) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d LIMIT 1", intval($id)));
    }

    private static function resolveManualFeaturedMatch(array $atts)
    {
        global $wpdb;
        $table = \OpenTT_Unified_Core::db_table('matches');
        if (!self::matchesTableIsValid($table, true)) {
            return null;
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

    private static function resolveAutoMatch(array $atts, callable $call)
    {
        global $wpdb;
        $table = \OpenTT_Unified_Core::db_table('matches');
        if (!self::matchesTableIsValid($table)) {
            return null;
        }

        $ctx = self::resolveCompetitionContext($atts, $call);
        $liga = sanitize_title((string) ($ctx['liga_slug'] ?? ''));
        $sezona = sanitize_title((string) ($ctx['sezona_slug'] ?? ''));
        if ($liga === '') {
            return null;
        }

        $rankMap = [];
        $standings = $call('db_build_standings_for_competition', $liga, $sezona, null);
        if (is_array($standings)) {
            foreach ($standings as $r) {
                $clubId = intval($r['club_id'] ?? 0);
                $rank = intval($r['rank'] ?? 0);
                if ($clubId > 0 && $rank > 0) {
                    $rankMap[$clubId] = $rank;
                }
            }
        }

        $nowTs = current_time('timestamp');

        if (self::matchesTableHasColumn($table, 'live')) {
            $liveWhere = ['liga_slug=%s', 'live=1'];
            $liveParams = [$liga];
            if ($sezona !== '') {
                $liveWhere[] = 'sezona_slug=%s';
                $liveParams[] = $sezona;
            }
            $liveSql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $liveWhere) . " ORDER BY match_date ASC, id ASC LIMIT 120";
            $liveRows = $wpdb->get_results($wpdb->prepare($liveSql, $liveParams)) ?: [];
            $bestLive = self::pickBestLiveCandidate($liveRows, $rankMap, $nowTs);
            if ($bestLive) {
                return $bestLive;
            }
        }

        $where = ['liga_slug=%s', 'match_date IS NOT NULL', "match_date <> '0000-00-00 00:00:00'"];
        $params = [$liga];
        if ($sezona !== '') {
            $where[] = 'sezona_slug=%s';
            $params[] = $sezona;
        }
        // Include whole current day to support legacy rows where time wasn't entered (00:00:00).
        $where[] = 'DATE(match_date) >= %s';
        $params[] = current_time('Y-m-d');
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY match_date ASC, id ASC LIMIT 120";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
        if (empty($rows)) {
            return null;
        }

        $timedRows = [];
        $dateOnlyRows = [];

        foreach ($rows as $row) {
            if (self::isMatchPlayedByStatusOrScore($row)) {
                continue;
            }

            $ts = self::autoSelectionTimestamp((string) ($row->match_date ?? ''));
            if ($ts === false || $ts < $nowTs) {
                continue;
            }

            if (self::hasExplicitKickoffTime((string) ($row->match_date ?? ''))) {
                $timedRows[] = $row;
            } else {
                $dateOnlyRows[] = $row;
            }
        }

        $bestTimed = self::pickBestAutoCandidate($timedRows, $rankMap, $nowTs);
        if ($bestTimed) {
            return $bestTimed;
        }
        return self::pickBestAutoCandidate($dateOnlyRows, $rankMap, $nowTs);
    }

    private static function pickBestLiveCandidate(array $rows, array $rankMap, $nowTs)
    {
        $best = null;
        $bestRankSum = null;
        $bestDateDiff = null;
        $bestId = 0;
        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }
            $homeId = intval($row->home_club_post_id ?? 0);
            $awayId = intval($row->away_club_post_id ?? 0);
            $rankSum = intval($rankMap[$homeId] ?? 9999) + intval($rankMap[$awayId] ?? 9999);

            $ts = self::matchTimestamp((string) ($row->match_date ?? ''));
            $dateDiff = ($ts === false) ? PHP_INT_MAX : abs(intval($nowTs) - intval($ts));
            $rowId = intval($row->id ?? 0);

            if (
                $best === null
                || $rankSum < $bestRankSum
                || ($rankSum === $bestRankSum && $dateDiff < $bestDateDiff)
                || ($rankSum === $bestRankSum && $dateDiff === $bestDateDiff && $rowId > $bestId)
            ) {
                $best = $row;
                $bestRankSum = $rankSum;
                $bestDateDiff = $dateDiff;
                $bestId = $rowId;
            }
        }
        return $best;
    }

    private static function pickBestAutoCandidate(array $rows, array $rankMap, $nowTs)
    {
        $best = null;
        $bestDateDiff = null;
        $bestRankSum = null;
        foreach ($rows as $row) {
            $ts = self::autoSelectionTimestamp((string) ($row->match_date ?? ''));
            if ($ts === false) {
                continue;
            }
            $dateDiff = max(0, $ts - intval($nowTs));
            $homeId = intval($row->home_club_post_id ?? 0);
            $awayId = intval($row->away_club_post_id ?? 0);
            $rankSum = intval($rankMap[$homeId] ?? 9999) + intval($rankMap[$awayId] ?? 9999);

            if ($best === null || $dateDiff < $bestDateDiff || ($dateDiff === $bestDateDiff && $rankSum < $bestRankSum)) {
                $best = $row;
                $bestDateDiff = $dateDiff;
                $bestRankSum = $rankSum;
            }
        }
        return $best;
    }

    private static function autoSelectionTimestamp($matchDate)
    {
        $matchDate = trim((string) $matchDate);
        if ($matchDate === '') {
            return false;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+00:00:00$/', $matchDate, $m)) {
            // No explicit kickoff time: treat as end-of-day so today's match is still considered upcoming.
            return self::matchTimestamp($m[1] . ' 00:00:00', true);
        }
        return self::matchTimestamp($matchDate);
    }

    private static function hasExplicitKickoffTime($matchDate)
    {
        $matchDate = trim((string) $matchDate);
        if ($matchDate === '') {
            return false;
        }
        if (preg_match('/\s+(\d{2}):(\d{2}):(\d{2})$/', $matchDate, $m)) {
            return !($m[1] === '00' && $m[2] === '00' && $m[3] === '00');
        }
        return false;
    }

    private static function isMatchPlayedByStatusOrScore($match)
    {
        $playedFlag = intval($match->played ?? 0) === 1;
        $homeScore = intval($match->home_score ?? 0);
        $awayScore = intval($match->away_score ?? 0);
        $hasRealScore = ($homeScore + $awayScore) > 0;
        return $playedFlag || $hasRealScore;
    }

    private static function resolveCompetitionContext(array $atts, callable $call)
    {
        $queryArgs = $call('build_match_query_args', [
            'limit' => 1,
            'liga' => (string) ($atts['liga'] ?? ''),
            'sezona' => (string) ($atts['sezona'] ?? ''),
            'klub' => '',
            'odigrana' => '',
        ]);
        if (is_array($queryArgs)) {
            $qLiga = sanitize_title((string) ($queryArgs['liga_slug'] ?? ''));
            $qSezona = sanitize_title((string) ($queryArgs['sezona_slug'] ?? ''));
            if ($qLiga !== '') {
                return ['liga_slug' => $qLiga, 'sezona_slug' => $qSezona];
            }
        }

        $liga = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona = sanitize_title((string) ($atts['sezona'] ?? ''));
        $parsed = $call('parse_legacy_liga_sezona', $liga, $sezona);
        if (is_array($parsed)) {
            $liga = sanitize_title((string) ($parsed['league_slug'] ?? $liga));
            $sezona = sanitize_title((string) ($parsed['season_slug'] ?? $sezona));
        }
        if ($liga !== '') {
            return ['liga_slug' => $liga, 'sezona_slug' => $sezona];
        }

        $archiveCtx = $call('current_archive_context');
        if (is_array($archiveCtx) && (($archiveCtx['type'] ?? '') === 'liga_sezona')) {
            return [
                'liga_slug' => sanitize_title((string) ($archiveCtx['liga_slug'] ?? '')),
                'sezona_slug' => sanitize_title((string) ($archiveCtx['sezona_slug'] ?? '')),
            ];
        }

        if (is_tax('liga_sezona')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $parsedTerm = $call('parse_legacy_liga_sezona', (string) $term->slug, '');
                if (is_array($parsedTerm)) {
                    return [
                        'liga_slug' => sanitize_title((string) ($parsedTerm['league_slug'] ?? '')),
                        'sezona_slug' => sanitize_title((string) ($parsedTerm['season_slug'] ?? '')),
                    ];
                }
            }
        }

        return ['liga_slug' => '', 'sezona_slug' => ''];
    }

    private static function matchesTableIsValid($table, $requireFeaturedColumn = false)
    {
        global $wpdb;
        if (!$table) {
            return false;
        }
        $table = (string) $table;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return false;
        }
        if ($requireFeaturedColumn) {
            $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'featured'));
            if (empty($col)) {
                return false;
            }
        }
        return true;
    }

    private static function matchesTableHasColumn($table, $columnName)
    {
        global $wpdb;
        $table = (string) $table;
        $columnName = sanitize_key((string) $columnName);
        if ($table === '' || $columnName === '') {
            return false;
        }
        $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $columnName));
        return !empty($col);
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
        $played = self::isMatchPlayedByStatusOrScore($match);
        if ($played) {
            return intval($match->home_score ?? 0) . ' : ' . intval($match->away_score ?? 0);
        }
        $dateRaw = (string) ($match->match_date ?? '');
        $ts = self::matchTimestamp($dateRaw);
        if ($ts !== false) {
            return wp_date('H:i', $ts) . 'h';
        }
        return 'Uskoro';
    }

    private static function centerIntroLabel($match)
    {
        if (self::isLiveMatch($match)) {
            return 'Uživo';
        }
        if (self::isMatchPlayedByStatusOrScore($match)) {
            return 'Rezultat';
        }
        return 'Početak za:';
    }

    private static function isLiveMatch($match)
    {
        if (!is_object($match)) {
            return false;
        }
        return intval($match->live ?? 0) === 1;
    }

    private static function matchLocationLabel($match)
    {
        if (!is_object($match)) {
            return '';
        }

        $location = trim((string) ($match->location ?? ''));
        if ($location !== '') {
            return $location;
        }

        $location = trim((string) ($match->lokacija ?? ''));
        if ($location !== '') {
            return $location;
        }

        $location = trim((string) ($match->lokacija_utakmice ?? ''));
        if ($location !== '') {
            return $location;
        }

        $homeId = intval($match->home_club_post_id ?? 0);
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
        $ts = self::matchTimestamp($matchDate);
        if ($ts === false) {
            return '';
        }
        return wp_date('c', $ts);
    }

    private static function matchTimestamp($matchDate, $endOfDayIfMidnight = false)
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
                if ($endOfDayIfMidnight && $hour === 0 && $minute === 0 && $second === 0) {
                    $dt = $dt->setTime(23, 59, 59);
                }
                return $dt->getTimestamp();
            }
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d G:i:s', 'Y-m-d H:i', 'Y-m-d G:i'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $matchDate, $tz);
            if (!($dt instanceof \DateTimeImmutable)) {
                continue;
            }
            if ($endOfDayIfMidnight && preg_match('/\s00:00(?::00)?$/', $matchDate)) {
                $dt = $dt->setTime(23, 59, 59);
            }
            return $dt->getTimestamp();
        }

        return false;
    }
}

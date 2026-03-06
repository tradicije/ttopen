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

final class MatchesListShortcode
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
            'limit' => -1,
            'klub' => '',
            'odigrana' => '',
            'liga' => '',
            'sezona' => '',
            'kolo' => '',
        ], $atts);

        $query_args = (array) $call('build_match_query_args', $atts);
        $query_args['limit'] = -1;

        if (!empty($atts['kolo'])) {
            $query_args['kolo_slug'] = sanitize_title((string) $atts['kolo']);
        }

        $rows = $call('db_get_matches', $query_args);
        $rows = is_array($rows) ? $rows : [];
        if (empty($rows)) {
            return (string) $call('shortcode_title_html', 'Utakmice') . '<p>Nema utakmica za prikaz.</p>';
        }

        $prepared = self::build_round_data($rows, $call);
        if (empty($prepared['rounds']) || empty($prepared['matches_by_round'])) {
            return (string) $call('shortcode_title_html', 'Utakmice') . '<p>Nema utakmica za prikaz.</p>';
        }

        $default_round = self::resolve_default_round_slug($prepared['rounds'], $query_args, $atts);
        if ($default_round === '') {
            $default_round = (string) ($prepared['rounds'][count($prepared['rounds']) - 1]['slug'] ?? '');
        }

        $payload = [
            'rounds' => $prepared['rounds'],
            'matchesByRound' => $prepared['matches_by_round'],
            'defaultRound' => $default_round,
            'i18n' => [
                'prev' => '&lsaquo;',
                'next' => '&rsaquo;',
                'noMatches' => 'Nema utakmica u ovom kolu.',
                'reportLabel' => 'Izveštaj',
                'videoLabel' => 'Snimak',
            ],
        ];

        $uid = 'opentt-matches-list-' . wp_unique_id();

        ob_start();
        echo (string) $call('shortcode_title_html', 'Utakmice');
        echo '<div id="' . esc_attr($uid) . '" class="opentt-matches-list" data-opentt-matches-list="1">';
        echo '<div class="opentt-matches-list-nav" role="group" aria-label="Kolo navigacija">';
        echo '<button type="button" class="opentt-matches-list-nav-btn is-prev" aria-label="Prethodno kolo">&lsaquo;</button>';
        echo '<div class="opentt-matches-list-round" aria-live="polite"></div>';
        echo '<button type="button" class="opentt-matches-list-nav-btn is-next" aria-label="Sledeće kolo">&rsaquo;</button>';
        echo '</div>';
        echo '<div class="opentt-matches-list-body"></div>';
        echo '</div>';
        ?>
        <script>
        (function(){
          var root = document.getElementById(<?php echo wp_json_encode($uid); ?>);
          if (!root || root.dataset.openttListReady === '1') { return; }
          root.dataset.openttListReady = '1';

          var data = <?php echo wp_json_encode($payload); ?>;
          if (!data || !Array.isArray(data.rounds) || !data.rounds.length) { return; }

          var navPrev = root.querySelector('.opentt-matches-list-nav-btn.is-prev');
          var navNext = root.querySelector('.opentt-matches-list-nav-btn.is-next');
          var roundLabel = root.querySelector('.opentt-matches-list-round');
          var body = root.querySelector('.opentt-matches-list-body');
          var rounds = data.rounds;
          var matchesByRound = data.matchesByRound || {};

          var roundIndex = 0;
          var i;
          for (i = 0; i < rounds.length; i++) {
            if ((rounds[i].slug || '') === (data.defaultRound || '')) {
              roundIndex = i;
              break;
            }
          }

          function esc(v) {
            return String(v || '').replace(/[&<>"']/g, function(ch){
              if (ch === '&') { return '&amp;'; }
              if (ch === '<') { return '&lt;'; }
              if (ch === '>') { return '&gt;'; }
              if (ch === '"') { return '&quot;'; }
              return '&#39;';
            });
          }

          function icon(kind, url, label) {
            return '<a class="opentt-matches-list-icon opentt-matches-list-icon--' + kind + '" href="' + esc(url) + '" aria-label="' + esc(label) + '" title="' + esc(label) + '">' + esc(kind === 'report' ? 'R' : 'V') + '</a>';
          }

          function rowHtml(match) {
            var icons = '';
            if (match.reportUrl) {
              icons += icon('report', match.reportUrl, data.i18n.reportLabel || 'Izveštaj');
            }
            if (match.videoUrl) {
              icons += icon('video', match.videoUrl, data.i18n.videoLabel || 'Snimak');
            }

            return ''
              + '<div class="opentt-matches-list-row" data-link="' + esc(match.link || '#') + '" tabindex="0" role="link">'
              +   '<div class="opentt-matches-list-col opentt-matches-list-col--date">' + esc(match.date) + '</div>'
              +   '<div class="opentt-matches-list-col opentt-matches-list-col--match">'
              +     '<span class="team-name team-name--home">' + esc(match.homeName) + '</span>'
              +     '<span class="team-crest">' + (match.homeLogo || '') + '</span>'
              +     '<span class="team-score">' + esc(match.homeScore) + '</span>'
              +     '<span class="team-sep">:</span>'
              +     '<span class="team-score">' + esc(match.awayScore) + '</span>'
              +     '<span class="team-crest">' + (match.awayLogo || '') + '</span>'
              +     '<span class="team-name team-name--away">' + esc(match.awayName) + '</span>'
              +   '</div>'
              +   '<div class="opentt-matches-list-col opentt-matches-list-col--media">' + icons + '</div>'
              + '</div>';
          }

          function render() {
            var current = rounds[roundIndex] || null;
            if (!current) {
              body.innerHTML = '<p>' + esc(data.i18n.noMatches || 'Nema utakmica.') + '</p>';
              return;
            }

            roundLabel.textContent = current.name || current.slug || '';
            navPrev.disabled = roundIndex <= 0;
            navNext.disabled = roundIndex >= (rounds.length - 1);

            var list = matchesByRound[current.slug] || [];
            if (!list.length) {
              body.innerHTML = '<p>' + esc(data.i18n.noMatches || 'Nema utakmica.') + '</p>';
              return;
            }

            var html = '<div class="opentt-matches-list-items">';
            for (var idx = 0; idx < list.length; idx++) {
              html += rowHtml(list[idx]);
            }
            html += '</div>';
            body.innerHTML = html;
          }

          navPrev.addEventListener('click', function(){
            if (roundIndex > 0) {
              roundIndex -= 1;
              render();
            }
          });

          navNext.addEventListener('click', function(){
            if (roundIndex < rounds.length - 1) {
              roundIndex += 1;
              render();
            }
          });

          root.addEventListener('click', function(e){
            var icon = e.target && e.target.closest ? e.target.closest('.opentt-matches-list-icon') : null;
            if (icon) {
              e.stopPropagation();
              return;
            }
            var row = e.target && e.target.closest ? e.target.closest('.opentt-matches-list-row') : null;
            if (!row) { return; }
            var link = row.getAttribute('data-link') || '';
            if (link) {
              window.location.href = link;
            }
          });

          root.addEventListener('keydown', function(e){
            if (e.key !== 'Enter' && e.key !== ' ') { return; }
            var row = e.target && e.target.closest ? e.target.closest('.opentt-matches-list-row') : null;
            if (!row) { return; }
            e.preventDefault();
            var link = row.getAttribute('data-link') || '';
            if (link) {
              window.location.href = link;
            }
          });

          render();
        })();
        </script>
        <?php

        return ob_get_clean();
    }

    private static function build_round_data(array $rows, callable $call)
    {
        $legacy_ids = [];
        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }
            $legacy_id = intval($row->legacy_post_id ?? 0);
            if ($legacy_id > 0) {
                $legacy_ids[] = $legacy_id;
            }
        }

        $report_map = self::build_report_map($legacy_ids);
        $video_map = self::build_video_map($legacy_ids);

        $matches_by_round = [];
        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }

            $kolo_slug = sanitize_title((string) ($row->kolo_slug ?? ''));
            if ($kolo_slug === '') {
                continue;
            }

            $home_id = intval($row->home_club_post_id ?? 0);
            $away_id = intval($row->away_club_post_id ?? 0);
            $legacy_id = intval($row->legacy_post_id ?? 0);
            $match_link = (string) $call('match_permalink', $row);

            $matches_by_round[$kolo_slug][] = [
                'id' => intval($row->id ?? 0),
                'matchDateRaw' => (string) ($row->match_date ?? ''),
                'date' => (string) $call('display_match_date', $row->match_date ?? ''),
                'homeName' => $home_id > 0 ? (string) get_the_title($home_id) : '',
                'awayName' => $away_id > 0 ? (string) get_the_title($away_id) : '',
                'homeLogo' => $home_id > 0 ? (string) $call('club_logo_html', $home_id, 'thumbnail', ['class' => 'opentt-list-team-crest']) : '',
                'awayLogo' => $away_id > 0 ? (string) $call('club_logo_html', $away_id, 'thumbnail', ['class' => 'opentt-list-team-crest']) : '',
                'homeScore' => intval($row->home_score ?? 0),
                'awayScore' => intval($row->away_score ?? 0),
                'link' => $match_link,
                'reportUrl' => $legacy_id > 0 ? (string) ($report_map[$legacy_id] ?? '') : '',
                'videoUrl' => ($legacy_id > 0 && !empty($video_map[$legacy_id])) ? $match_link : '',
            ];
        }

        if (empty($matches_by_round)) {
            return ['rounds' => [], 'matches_by_round' => []];
        }

        foreach ($matches_by_round as &$round_rows) {
            usort($round_rows, static function ($a, $b) {
                $at = strtotime((string) ($a['matchDateRaw'] ?? '')) ?: 0;
                $bt = strtotime((string) ($b['matchDateRaw'] ?? '')) ?: 0;
                if ($at !== $bt) {
                    return $at <=> $bt;
                }
                return intval($a['id'] ?? 0) <=> intval($b['id'] ?? 0);
            });
        }
        unset($round_rows);

        $rounds = [];
        foreach (array_keys($matches_by_round) as $slug) {
            $num = intval($call('extract_round_no', $slug));
            $name = (string) $call('kolo_name_from_slug', $slug);
            if ($name === '' && $num > 0) {
                $name = $num . '. kolo';
            }
            if ($name === '') {
                $name = $slug;
            }
            $rounds[] = [
                'slug' => $slug,
                'name' => $name,
                'num' => $num,
            ];
        }

        usort($rounds, static function ($a, $b) {
            $an = intval($a['num'] ?? 0);
            $bn = intval($b['num'] ?? 0);
            if ($an > 0 && $bn > 0 && $an !== $bn) {
                return $an <=> $bn;
            }
            if ($an > 0 && $bn <= 0) {
                return -1;
            }
            if ($bn > 0 && $an <= 0) {
                return 1;
            }
            return strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return [
            'rounds' => $rounds,
            'matches_by_round' => $matches_by_round,
        ];
    }

    private static function resolve_default_round_slug(array $rounds, array $query_args, array $atts)
    {
        $target = sanitize_title((string) ($query_args['kolo_slug'] ?? ''));
        if ($target === '') {
            $target = sanitize_title((string) ($atts['kolo'] ?? ''));
        }
        if ($target === '') {
            return '';
        }

        foreach ($rounds as $round) {
            if ((string) ($round['slug'] ?? '') === $target) {
                return $target;
            }
        }

        return '';
    }

    private static function build_report_map(array $legacy_ids)
    {
        $legacy_ids = array_values(array_unique(array_filter(array_map('intval', $legacy_ids), static function ($id) {
            return $id > 0;
        })));
        if (empty($legacy_ids)) {
            return [];
        }

        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'ignore_sticky_posts' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [[
                'key' => 'povezana_utakmica',
                'value' => $legacy_ids,
                'compare' => 'IN',
            ]],
        ]);

        $map = [];
        foreach ($posts as $post) {
            if (!($post instanceof \WP_Post)) {
                continue;
            }
            $legacy_id = intval(get_post_meta($post->ID, 'povezana_utakmica', true));
            if ($legacy_id <= 0 || isset($map[$legacy_id])) {
                continue;
            }
            $map[$legacy_id] = (string) get_permalink($post->ID);
        }

        return $map;
    }

    private static function build_video_map(array $legacy_ids)
    {
        $legacy_ids = array_values(array_unique(array_filter(array_map('intval', $legacy_ids), static function ($id) {
            return $id > 0;
        })));
        if (empty($legacy_ids)) {
            return [];
        }

        $map = [];
        foreach ($legacy_ids as $legacy_id) {
            $video = trim((string) get_post_meta($legacy_id, 'snimak_utakmice', true));
            if ($video !== '') {
                $map[$legacy_id] = true;
            }
        }

        return $map;
    }
}

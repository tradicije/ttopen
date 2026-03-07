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

final class MatchesGridShortcode
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
            'columns' => 3,
            'limit' => 5,
            'klub' => '',
            'played' => '',
            'odigrana' => '',
            'liga' => '',
            'sezona' => '',
            'filter' => '',
            'infinite' => '',
            'opentt_match_date' => '',
        ], $atts);

        $columns = max(1, min(6, intval($atts['columns'])));
        $filter_mode = strtolower(trim((string) $atts['filter']));
        $enable_filters = in_array($filter_mode, ['1', 'true', 'yes', 'da', 'on'], true);
        $legacy_kolo_filter = ($filter_mode === 'kolo');
        $infinite_mode = in_array(strtolower(trim((string) $atts['infinite'])), ['1', 'true', 'yes', 'da', 'on'], true);
        $chunk_size = intval($atts['limit']);
        if ($chunk_size <= 0) {
            $chunk_size = 8;
        }

        $query_args = (array) $call('build_match_query_args', $atts);
        if ($legacy_kolo_filter || $enable_filters || $infinite_mode) {
            $query_args['limit'] = -1;
        }

        $rows = $call('db_get_matches', $query_args);
        $rows = is_array($rows) ? $rows : [];

        if (empty($rows)) {
            return (string) $call('shortcode_title_html', 'Utakmice') . '<p>Nema utakmica za prikaz.</p>';
        }

        if ($legacy_kolo_filter) {
            $uid = 'opentt-grid-' . wp_unique_id();
            $kolo_map = [];
            foreach ($rows as $row) {
                $slug = (string) $row->kolo_slug;
                if ($slug === '') {
                    continue;
                }
                $kolo_map[$slug] = (string) $call('kolo_name_from_slug', $slug);
            }

            $options = [];
            foreach ($kolo_map as $slug => $name) {
                $num = null;
                if (preg_match('/\d+/', $name, $m)) {
                    $num = intval($m[0]);
                }
                $options[] = ['slug' => $slug, 'name' => $name, 'num' => $num];
            }
            usort($options, function ($a, $b) {
                if ($a['num'] !== null && $b['num'] !== null) {
                    return $a['num'] <=> $b['num'];
                }
                if ($a['num'] !== null) {
                    return -1;
                }
                if ($b['num'] !== null) {
                    return 1;
                }
                return strnatcasecmp($a['name'], $b['name']);
            });

            ob_start();
            echo (string) $call('shortcode_title_html', 'Utakmice');
            echo '<div id="' . esc_attr($uid) . '" class="opentt-grid-legacy-filter-block">';
            echo '<div class="opentt-kolo-filter-wrap">';
            echo '<label for="opentt-kolo">Izaberi kolo:</label>';
            echo '<select id="opentt-kolo" onchange="openttFilterKoloChange(this)">';
            echo '<option value="">Sva kola</option>';
            foreach ($options as $opt) {
                echo '<option value="' . esc_attr($opt['slug']) . '">' . esc_html($opt['name']) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo (string) $call('render_matches_grid_html', $rows, $columns, true);
            echo '</div>';
            ?>
            <script>
            var openttLegacyGridRoot = document.getElementById('<?php echo esc_js($uid); ?>');
            function openttRenderRoundHeadings() {
                if (!openttLegacyGridRoot) { return; }
                var grid = openttLegacyGridRoot.querySelector('.opentt-grid');
                if (!grid) { return; }
                grid.querySelectorAll('.opentt-grid-round-heading').forEach(function(node){ node.remove(); });
                var items = Array.prototype.slice.call(grid.querySelectorAll('.opentt-item')).filter(function(item){
                    return item.style.display !== 'none';
                });
                var lastSlug = '';
                items.forEach(function(item){
                    var slug = item.getAttribute('data-kolo-slug') || '';
                    if (!slug || slug === lastSlug) { return; }
                    var title = item.getAttribute('data-kolo-name') || '';
                    var koloNo = parseInt(item.getAttribute('data-kolo-no') || '0', 10);
                    if (!title && koloNo > 0) { title = String(koloNo) + '. kolo'; }
                    if (!title) { title = slug; }
                    var head = document.createElement('div');
                    head.className = 'opentt-grid-round-heading';
                    head.setAttribute('data-kolo-slug', slug);
                    var text = document.createElement('span');
                    text.textContent = title;
                    head.appendChild(text);
                    grid.insertBefore(head, item);
                    lastSlug = slug;
                });
            }
            function openttFilterKoloChange(sel) {
                var selected = sel.value;
                if (!openttLegacyGridRoot) { return; }
                var items = openttLegacyGridRoot.querySelectorAll('.opentt-item');
                items.forEach(function(it){
                    var slug = it.getAttribute('data-kolo-slug') || '';
                    it.style.display = (!selected || slug === selected) ? 'block' : 'none';
                });
                openttRenderRoundHeadings();
            }
            openttRenderRoundHeadings();
            </script>
            <?php
            return ob_get_clean();
        }

        if ($enable_filters) {
            $selected_kolo = isset($_GET['opentt_kolo']) ? sanitize_title((string) wp_unslash($_GET['opentt_kolo'])) : '';
            $selected_club = isset($_GET['opentt_club']) ? intval($_GET['opentt_club']) : 0;
            $selected_sort = isset($_GET['opentt_sort']) ? sanitize_key((string) wp_unslash($_GET['opentt_sort'])) : 'kolo_desc';
            $selected_match_date = isset($_GET['opentt_match_date'])
                ? sanitize_text_field((string) wp_unslash($_GET['opentt_match_date']))
                : sanitize_text_field((string) $atts['opentt_match_date']);
            if (!in_array($selected_sort, ['kolo_desc', 'kolo_asc', 'date_desc', 'date_asc'], true)) {
                $selected_sort = 'kolo_desc';
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_match_date)) {
                $selected_match_date = '';
            } else {
                $date_parts = array_map('intval', explode('-', $selected_match_date));
                if (count($date_parts) !== 3 || !checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
                    $selected_match_date = '';
                }
            }

            $kolo_map = [];
            $club_map = [];
            foreach ($rows as $row) {
                $kolo_slug = sanitize_title((string) $row->kolo_slug);
                if ($kolo_slug !== '') {
                    $kolo_map[$kolo_slug] = (string) $call('kolo_name_from_slug', $kolo_slug);
                }

                $home_id = intval($row->home_club_post_id);
                $away_id = intval($row->away_club_post_id);
                if ($home_id > 0) {
                    $club_map[$home_id] = (string) get_the_title($home_id);
                }
                if ($away_id > 0) {
                    $club_map[$away_id] = (string) get_the_title($away_id);
                }
            }

            $kolo_options = [];
            foreach ($kolo_map as $slug => $name) {
                $kolo_options[] = [
                    'slug' => $slug,
                    'name' => $name,
                    'num' => intval($call('extract_round_no', (string) $slug)),
                ];
            }
            usort($kolo_options, function ($a, $b) {
                if (intval($a['num']) !== intval($b['num'])) {
                    return intval($a['num']) <=> intval($b['num']);
                }
                return strnatcasecmp((string) $a['name'], (string) $b['name']);
            });

            $club_options = [];
            foreach ($club_map as $id => $name) {
                $club_options[] = [
                    'id' => intval($id),
                    'name' => (string) $name,
                ];
            }
            usort($club_options, function ($a, $b) {
                return strnatcasecmp((string) $a['name'], (string) $b['name']);
            });

            $extract_round_no = $deps['extract_round_no'];
            $rows = array_values(array_filter($rows, function ($row) use ($selected_kolo, $selected_club) {
                $kolo_ok = ($selected_kolo === '' || sanitize_title((string) $row->kolo_slug) === $selected_kolo);
                $club_ok = (
                    $selected_club <= 0
                    || intval($row->home_club_post_id) === $selected_club
                    || intval($row->away_club_post_id) === $selected_club
                );
                return $kolo_ok && $club_ok;
            }));

            usort($rows, function ($a, $b) use ($selected_sort, $extract_round_no) {
                $a_ts = strtotime((string) $a->match_date);
                $b_ts = strtotime((string) $b->match_date);
                $a_ts = ($a_ts === false) ? 0 : intval($a_ts);
                $b_ts = ($b_ts === false) ? 0 : intval($b_ts);
                $a_k = intval($extract_round_no((string) $a->kolo_slug));
                $b_k = intval($extract_round_no((string) $b->kolo_slug));

                if ($selected_sort === 'date_asc') {
                    return $a_ts <=> $b_ts;
                }
                if ($selected_sort === 'date_desc') {
                    return $b_ts <=> $a_ts;
                }
                if ($selected_sort === 'kolo_asc') {
                    return ($a_k <=> $b_k) ?: ($a_ts <=> $b_ts);
                }
                return ($b_k <=> $a_k) ?: ($b_ts <=> $a_ts);
            });

            $uid = 'opentt-grid-' . wp_unique_id();

            ob_start();
            echo (string) $call('shortcode_title_html', 'Utakmice');
            echo '<div id="' . esc_attr($uid) . '" class="opentt-grid-filter-block">';
            echo '<form method="get" class="opentt-grid-filters">';
            foreach ($_GET as $k => $v) {
                $k = (string) $k;
                if (in_array($k, ['opentt_kolo', 'opentt_club', 'opentt_sort', 'opentt_match_date'], true)) {
                    continue;
                }
                if (is_array($v)) {
                    continue;
                }
                echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string) wp_unslash($v)) . '">';
            }
            echo '<div class="opentt-grid-filters-left">';
            echo '<label>Kolo <select name="opentt_kolo" class="opentt-grid-filter-kolo" onchange="this.form.submit()"><option value="">Sva kola</option>';
            foreach ($kolo_options as $opt) {
                echo '<option value="' . esc_attr((string) $opt['slug']) . '" ' . selected($selected_kolo, (string) $opt['slug'], false) . '>' . esc_html((string) $opt['name']) . '</option>';
            }
            echo '</select></label>';

            echo '<label>Klub <select name="opentt_club" class="opentt-grid-filter-club" onchange="this.form.submit()"><option value="">Svi klubovi</option>';
            foreach ($club_options as $opt) {
                echo '<option value="' . esc_attr((string) $opt['id']) . '" ' . selected($selected_club, intval($opt['id']), false) . '>' . esc_html((string) $opt['name']) . '</option>';
            }
            echo '</select></label>';

            echo '<label>Sortiranje <select name="opentt_sort" class="opentt-grid-filter-sort" onchange="this.form.submit()">';
            echo '<option value="kolo_desc" ' . selected($selected_sort, 'kolo_desc', false) . '>Kolo: najnovije</option>';
            echo '<option value="kolo_asc" ' . selected($selected_sort, 'kolo_asc', false) . '>Kolo: najstarije</option>';
            echo '<option value="date_desc" ' . selected($selected_sort, 'date_desc', false) . '>Datum: najnovije</option>';
            echo '<option value="date_asc" ' . selected($selected_sort, 'date_asc', false) . '>Datum: najstarije</option>';
            echo '</select></label>';
            if ($selected_kolo !== '' || $selected_club > 0 || $selected_match_date !== '' || isset($_GET['opentt_sort'])) {
                echo '<a class="button opentt-grid-filter-reset" href="' . esc_url(remove_query_arg(['opentt_kolo', 'opentt_club', 'opentt_sort', 'opentt_match_date'])) . '">Reset</a>';
            }
            echo '</div>';
            echo '<div class="opentt-grid-filters-right">';
            echo '<input type="hidden" name="opentt_match_date" class="opentt-grid-filter-date-input" value="' . esc_attr($selected_match_date) . '">';
            echo '<button type="button" class="button opentt-grid-calendar-toggle" aria-label="Calendar filter" title="Calendar filter" aria-haspopup="dialog" aria-expanded="false">';
            echo '<span class="opentt-grid-calendar-icon" aria-hidden="true"></span>';
            echo '</button>';
            echo '<div class="opentt-grid-calendar-popover" role="dialog" aria-label="Filter datuma utakmica" hidden>';
            echo '<div class="opentt-grid-calendar-head">';
            echo '<button type="button" class="opentt-grid-cal-nav" data-opentt-cal-nav="prev" aria-label="Prethodni mesec">&lsaquo;</button>';
            echo '<strong class="opentt-grid-cal-month"></strong>';
            echo '<button type="button" class="opentt-grid-cal-nav" data-opentt-cal-nav="next" aria-label="Sledeći mesec">&rsaquo;</button>';
            echo '</div>';
            echo '<div class="opentt-grid-cal-weekdays">';
            echo '<span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>';
            echo '</div>';
            echo '<div class="opentt-grid-cal-days"></div>';
            echo '<div class="opentt-grid-cal-preview" hidden></div>';
            echo '<div class="opentt-grid-cal-legend">';
            echo '<span class="played">Odigrano</span><span class="upcoming">Predstoji</span>';
            echo '</div>';
            echo '<button type="button" class="button-link opentt-grid-cal-clear">Očisti datum</button>';
            echo '</div>';
            echo '</div>';
            echo '</form>';

            echo (string) $call('render_matches_grid_html', $rows, $columns, true);
            if ($infinite_mode) {
                echo '<div class="opentt-grid-sentinel" aria-hidden="true"></div>';
            }
            echo '</div>';
            ?>
            <script>
            (function(){
                var rootId = '<?php echo esc_js($uid); ?>';
                function init() {
                    var root = document.getElementById(rootId);
                    if (!root) { return false; }
                    var koloSelect = root.querySelector('.opentt-grid-filter-kolo');
                    var clubSelect = root.querySelector('.opentt-grid-filter-club');
                    var sortSelect = root.querySelector('.opentt-grid-filter-sort');
                    var dateInput = root.querySelector('.opentt-grid-filter-date-input');
                    var calToggle = root.querySelector('.opentt-grid-calendar-toggle');
                    var calPopover = root.querySelector('.opentt-grid-calendar-popover');
                    var calMonth = root.querySelector('.opentt-grid-cal-month');
                    var calDays = root.querySelector('.opentt-grid-cal-days');
                    var calPreview = root.querySelector('.opentt-grid-cal-preview');
                    var calClear = root.querySelector('.opentt-grid-cal-clear');
                    var grid = root.querySelector('.opentt-grid');
                    if (!grid) { return false; }
                    var sentinel = root.querySelector('.opentt-grid-sentinel');
                    var infiniteEnabled = <?php echo $infinite_mode ? 'true' : 'false'; ?>;
                    var chunkSize = <?php echo intval($chunk_size); ?>;
                    var visibleCount = chunkSize;
                    var observer = null;
                    var previewHideTimer = null;
                    var allItems = Array.prototype.slice.call(grid.querySelectorAll('.opentt-item'));
                    var calendarMonthDate = (function(){
                        if (dateInput && dateInput.value) {
                            var parts = dateInput.value.split('-');
                            if (parts.length === 3) {
                                var y = parseInt(parts[0], 10);
                                var m = parseInt(parts[1], 10) - 1;
                                if (!isNaN(y) && !isNaN(m)) {
                                    return new Date(y, m, 1);
                                }
                            }
                        }
                        var now = new Date();
                        return new Date(now.getFullYear(), now.getMonth(), 1);
                    })();

                    function getNum(val) {
                        var n = parseInt(val || '0', 10);
                        return isNaN(n) ? 0 : n;
                    }

                    function matchesFilter(item) {
                        var wantKolo = koloSelect ? (koloSelect.value || '') : '';
                        var wantClub = clubSelect ? (clubSelect.value || '') : '';
                        var wantDate = dateInput ? (dateInput.value || '') : '';
                        var itemKolo = item.getAttribute('data-kolo-slug') || '';
                        var homeClub = item.getAttribute('data-home-club-id') || '';
                        var awayClub = item.getAttribute('data-away-club-id') || '';
                        var itemDate = item.getAttribute('data-match-date') || '';
                        var koloOk = !wantKolo || itemKolo === wantKolo;
                        var clubOk = !wantClub || homeClub === wantClub || awayClub === wantClub;
                        var dateOk = !wantDate || itemDate === wantDate;
                        return koloOk && clubOk && dateOk;
                    }

                    function matchesFilterWithoutDate(item) {
                        var wantKolo = koloSelect ? (koloSelect.value || '') : '';
                        var wantClub = clubSelect ? (clubSelect.value || '') : '';
                        var itemKolo = item.getAttribute('data-kolo-slug') || '';
                        var homeClub = item.getAttribute('data-home-club-id') || '';
                        var awayClub = item.getAttribute('data-away-club-id') || '';
                        var koloOk = !wantKolo || itemKolo === wantKolo;
                        var clubOk = !wantClub || homeClub === wantClub || awayClub === wantClub;
                        return koloOk && clubOk;
                    }

                    function compareItems(a, b, sort) {
                        var dateA = getNum(a.getAttribute('data-match-ts'));
                        var dateB = getNum(b.getAttribute('data-match-ts'));
                        var koloA = getNum(a.getAttribute('data-kolo-no'));
                        var koloB = getNum(b.getAttribute('data-kolo-no'));
                        if (sort === 'date_asc') { return dateA - dateB; }
                        if (sort === 'kolo_desc') { return koloB - koloA || dateB - dateA; }
                        if (sort === 'kolo_asc') { return koloA - koloB || dateA - dateB; }
                        return dateB - dateA;
                    }

                    function renderRoundHeadings(renderedItems) {
                        grid.querySelectorAll('.opentt-grid-round-heading').forEach(function(node){ node.remove(); });
                        var lastSlug = '';
                        (renderedItems || []).forEach(function(item){
                            var slug = item.getAttribute('data-kolo-slug') || '';
                            if (!slug || slug === lastSlug) { return; }
                            var title = item.getAttribute('data-kolo-name') || '';
                            var koloNo = parseInt(item.getAttribute('data-kolo-no') || '0', 10);
                            if (!title && koloNo > 0) { title = String(koloNo) + '. kolo'; }
                            if (!title) { title = slug; }
                            var head = document.createElement('div');
                            head.className = 'opentt-grid-round-heading';
                            head.setAttribute('data-kolo-slug', slug);
                            var text = document.createElement('span');
                            text.textContent = title;
                            head.appendChild(text);
                            grid.insertBefore(head, item);
                            lastSlug = slug;
                        });
                    }

                    function render() {
                        var sort = sortSelect ? (sortSelect.value || 'kolo_desc') : 'kolo_desc';
                        var visible = allItems.filter(matchesFilter).sort(function(a, b){
                            return compareItems(a, b, sort);
                        });
                        allItems.forEach(function(item){ item.style.display = 'none'; });
                        var toRender = visible;
                        if (infiniteEnabled) {
                            toRender = visible.slice(0, Math.max(1, visibleCount));
                        }
                        toRender.forEach(function(item){
                            item.style.display = '';
                            grid.appendChild(item);
                        });
                        renderRoundHeadings(toRender);
                        if (infiniteEnabled && sentinel) {
                            sentinel.style.display = (toRender.length < visible.length) ? '' : 'none';
                        }
                        renderCalendar();
                    }

                    function resetAndRender() {
                        visibleCount = chunkSize;
                        render();
                    }

                    function collectCalendarState() {
                        var state = {};
                        allItems.forEach(function(item){
                            if (!matchesFilterWithoutDate(item)) {
                                return;
                            }
                            var iso = item.getAttribute('data-match-date') || '';
                            if (!iso) {
                                return;
                            }
                            if (!state[iso]) {
                                state[iso] = { played: false, upcoming: false, matches: [] };
                            }
                            if ((item.getAttribute('data-played') || '0') === '1') {
                                state[iso].played = true;
                            } else {
                                state[iso].upcoming = true;
                            }
                            state[iso].matches.push(extractPreviewMatch(item));
                        });
                        return state;
                    }

                    function compactClubName(name) {
                        var normalized = (name || '').replace(/\s+/g, ' ').trim();
                        if (!normalized) {
                            return '---';
                        }
                        var words = normalized.split(' ').filter(Boolean);
                        if (words.length > 1) {
                            var initials = words.slice(0, 3).map(function(word){
                                return word.charAt(0);
                            }).join('');
                            return initials.toUpperCase();
                        }
                        return normalized.replace(/[^0-9A-Za-z\u00C0-\u017F]/g, '').slice(0, 3).toUpperCase();
                    }

                    function extractPreviewMatch(item) {
                        var teams = item.querySelectorAll('.team');
                        var homeTeam = teams[0] || null;
                        var awayTeam = teams[1] || null;
                        var linkEl = item.querySelector('a');
                        var homeName = homeTeam ? ((homeTeam.querySelector('span') || {}).textContent || '') : '';
                        var awayName = awayTeam ? ((awayTeam.querySelector('span') || {}).textContent || '') : '';
                        var homeScore = homeTeam ? ((homeTeam.querySelector('strong') || {}).textContent || '-') : '-';
                        var awayScore = awayTeam ? ((awayTeam.querySelector('strong') || {}).textContent || '-') : '-';
                        return {
                            home: compactClubName(homeName),
                            away: compactClubName(awayName),
                            score: String(homeScore).trim() + ':' + String(awayScore).trim(),
                            href: linkEl ? (linkEl.getAttribute('href') || '') : ''
                        };
                    }

                    function monthLabel(date) {
                        return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                    }

                    function pad(n) {
                        return n < 10 ? ('0' + n) : String(n);
                    }

                    function closeCalendar() {
                        if (!calPopover || !calToggle) { return; }
                        hideCalendarPreview();
                        calPopover.hidden = true;
                        calToggle.setAttribute('aria-expanded', 'false');
                    }

                    function openCalendar() {
                        if (!calPopover || !calToggle) { return; }
                        calPopover.hidden = false;
                        calToggle.setAttribute('aria-expanded', 'true');
                    }

                    function applyDateSelection(iso) {
                        if (!dateInput) { return; }
                        dateInput.value = iso || '';
                        closeCalendar();
                        if (dateInput.form) {
                            dateInput.form.submit();
                        } else {
                            resetAndRender();
                        }
                    }

                    function hideCalendarPreview() {
                        if (previewHideTimer) {
                            clearTimeout(previewHideTimer);
                            previewHideTimer = null;
                        }
                        if (!calPreview) { return; }
                        calPreview.hidden = true;
                        calPreview.innerHTML = '';
                    }

                    function scheduleHideCalendarPreview() {
                        if (!calPreview) { return; }
                        if (previewHideTimer) {
                            clearTimeout(previewHideTimer);
                        }
                        previewHideTimer = setTimeout(function(){
                            hideCalendarPreview();
                        }, 140);
                    }

                    function showCalendarPreview(anchorCell, matches, selectedIso) {
                        if (!calPreview || !calPopover || !anchorCell || !matches || !matches.length) {
                            hideCalendarPreview();
                            return;
                        }
                        if (previewHideTimer) {
                            clearTimeout(previewHideTimer);
                            previewHideTimer = null;
                        }

                        calPreview.innerHTML = '';
                        var maxRows = 6;
                        matches.slice(0, maxRows).forEach(function(match){
                            var row = document.createElement(match.href ? 'a' : 'div');
                            row.className = 'opentt-grid-cal-preview-row';
                            if (match.href) {
                                row.href = match.href;
                            }

                            var home = document.createElement('span');
                            home.className = 'home';
                            home.textContent = match.home;

                            var score = document.createElement('span');
                            score.className = 'score';
                            score.textContent = match.score;

                            var away = document.createElement('span');
                            away.className = 'away';
                            away.textContent = match.away;

                            row.appendChild(home);
                            row.appendChild(score);
                            row.appendChild(away);
                            calPreview.appendChild(row);
                        });
                        if (matches.length > maxRows) {
                            var more = document.createElement('a');
                            more.className = 'opentt-grid-cal-preview-more';
                            more.href = '#';
                            more.textContent = '+' + String(matches.length - maxRows) + ' more';
                            more.addEventListener('click', function(ev){
                                ev.preventDefault();
                                applyDateSelection(selectedIso || '');
                            });
                            calPreview.appendChild(more);
                        }

                        calPreview.hidden = false;

                        var popRect = calPopover.getBoundingClientRect();
                        var cellRect = anchorCell.getBoundingClientRect();
                        var previewOffset = 2;
                        var left = (cellRect.right - popRect.left) + previewOffset;
                        var top = (cellRect.top - popRect.top) + (cellRect.height / 2);
                        var previewWidth = calPreview.offsetWidth;
                        var previewHeight = calPreview.offsetHeight;
                        var maxLeft = calPopover.clientWidth - previewWidth - 4;
                        if (left > maxLeft) {
                            left = (cellRect.left - popRect.left) - previewWidth - previewOffset;
                        }
                        if (left < 4) {
                            left = 4;
                        }
                        top = top - (previewHeight / 2);
                        var maxTop = calPopover.clientHeight - previewHeight - 4;
                        if (top > maxTop) {
                            top = maxTop;
                        }
                        if (top < 4) {
                            top = 4;
                        }

                        calPreview.style.left = left + 'px';
                        calPreview.style.top = top + 'px';
                    }

                    function renderCalendar() {
                        if (!calMonth || !calDays) { return; }
                        var map = collectCalendarState();
                        hideCalendarPreview();
                        calMonth.textContent = monthLabel(calendarMonthDate);
                        calDays.innerHTML = '';

                        var year = calendarMonthDate.getFullYear();
                        var month = calendarMonthDate.getMonth();
                        var first = new Date(year, month, 1);
                        var firstWeekday = (first.getDay() + 6) % 7;
                        var daysInMonth = new Date(year, month + 1, 0).getDate();
                        var totalCells = Math.ceil((firstWeekday + daysInMonth) / 7) * 7;

                        for (var i = 0; i < totalCells; i++) {
                            var cell = document.createElement('button');
                            cell.type = 'button';
                            cell.className = 'opentt-grid-cal-day';

                            var dayNo = i - firstWeekday + 1;
                            if (dayNo < 1 || dayNo > daysInMonth) {
                                cell.className += ' is-empty';
                                cell.disabled = true;
                                cell.setAttribute('aria-hidden', 'true');
                                calDays.appendChild(cell);
                                continue;
                            }

                            cell.textContent = String(dayNo);
                            var iso = year + '-' + pad(month + 1) + '-' + pad(dayNo);
                            var state = map[iso] || null;
                            if (state) {
                                if (state.played && state.upcoming) {
                                    cell.className += ' has-both';
                                } else if (state.played) {
                                    cell.className += ' has-played';
                                } else if (state.upcoming) {
                                    cell.className += ' has-upcoming';
                                }
                                if (state.matches && state.matches.length) {
                                    cell.addEventListener('mouseenter', (function(anchorCell, dayMatches, dayIso){
                                        return function(){ showCalendarPreview(anchorCell, dayMatches, dayIso); };
                                    })(cell, state.matches, iso));
                                    cell.addEventListener('mouseleave', scheduleHideCalendarPreview);
                                    cell.addEventListener('focus', (function(anchorCell, dayMatches, dayIso){
                                        return function(){ showCalendarPreview(anchorCell, dayMatches, dayIso); };
                                    })(cell, state.matches, iso));
                                    cell.addEventListener('blur', scheduleHideCalendarPreview);
                                }
                            }
                            if (dateInput && dateInput.value === iso) {
                                cell.className += ' is-selected';
                            }
                            cell.addEventListener('click', (function(selectedIso){
                                return function(){ applyDateSelection(selectedIso); };
                            })(iso));
                            calDays.appendChild(cell);
                        }
                    }

                    if (koloSelect) { koloSelect.addEventListener('change', resetAndRender); }
                    if (clubSelect) { clubSelect.addEventListener('change', resetAndRender); }
                    if (sortSelect) { sortSelect.addEventListener('change', resetAndRender); }
                    if (calToggle && calPopover) {
                        calToggle.addEventListener('click', function(){
                            if (calPopover.hidden) {
                                openCalendar();
                                renderCalendar();
                            } else {
                                closeCalendar();
                            }
                        });
                    }
                    root.querySelectorAll('[data-opentt-cal-nav]').forEach(function(btn){
                        btn.addEventListener('click', function(){
                            var dir = (btn.getAttribute('data-opentt-cal-nav') === 'next') ? 1 : -1;
                            calendarMonthDate = new Date(calendarMonthDate.getFullYear(), calendarMonthDate.getMonth() + dir, 1);
                            renderCalendar();
                        });
                    });
                    if (calClear) {
                        calClear.addEventListener('click', function(){
                            applyDateSelection('');
                        });
                    }
                    if (calPreview) {
                        calPreview.addEventListener('mouseenter', function(){
                            if (previewHideTimer) {
                                clearTimeout(previewHideTimer);
                                previewHideTimer = null;
                            }
                        });
                        calPreview.addEventListener('mouseleave', scheduleHideCalendarPreview);
                    }
                    document.addEventListener('click', function(ev){
                        if (!calPopover || calPopover.hidden) { return; }
                        if (root.contains(ev.target)) { return; }
                        closeCalendar();
                    });
                    document.addEventListener('keydown', function(ev){
                        if (ev.key === 'Escape') {
                            closeCalendar();
                        }
                    });

                    if (infiniteEnabled && sentinel && 'IntersectionObserver' in window) {
                        observer = new IntersectionObserver(function(entries){
                            entries.forEach(function(entry){
                                if (entry.isIntersecting) {
                                    visibleCount += chunkSize;
                                    render();
                                    if (observer && sentinel && sentinel.style.display !== 'none') {
                                        observer.unobserve(sentinel);
                                        setTimeout(function(){
                                            if (observer && sentinel && sentinel.style.display !== 'none') {
                                                observer.observe(sentinel);
                                            }
                                        }, 0);
                                    }
                                }
                            });
                        }, { rootMargin: '240px 0px' });
                        observer.observe(sentinel);
                    }

                    render();
                    return true;
                }

                if (!init()) {
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', init);
                    } else {
                        setTimeout(init, 0);
                    }
                }
            })();
            </script>
            <?php
            return ob_get_clean();
        }

        if ($infinite_mode) {
            $uid = 'opentt-grid-' . wp_unique_id();
            ob_start();
            echo (string) $call('shortcode_title_html', 'Utakmice');
            echo '<div id="' . esc_attr($uid) . '" class="opentt-grid-infinite-block">';
            echo (string) $call('render_matches_grid_html', $rows, $columns, true);
            echo '<div class="opentt-grid-sentinel" aria-hidden="true"></div>';
            echo '</div>';
            ?>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js($uid); ?>');
                if (!root) { return; }
                var grid = root.querySelector('.opentt-grid');
                var sentinel = root.querySelector('.opentt-grid-sentinel');
                if (!grid || !sentinel) { return; }
                var chunkSize = <?php echo intval($chunk_size); ?>;
                var visibleCount = chunkSize;
                var allItems = Array.prototype.slice.call(grid.querySelectorAll('.opentt-item'));

                function renderRoundHeadings(renderedItems) {
                    grid.querySelectorAll('.opentt-grid-round-heading').forEach(function(node){ node.remove(); });
                    var lastSlug = '';
                    (renderedItems || []).forEach(function(item){
                        var slug = item.getAttribute('data-kolo-slug') || '';
                        if (!slug || slug === lastSlug) { return; }
                        var title = item.getAttribute('data-kolo-name') || '';
                        var koloNo = parseInt(item.getAttribute('data-kolo-no') || '0', 10);
                        if (!title && koloNo > 0) { title = String(koloNo) + '. kolo'; }
                        if (!title) { title = slug; }
                        var head = document.createElement('div');
                        head.className = 'opentt-grid-round-heading';
                        head.setAttribute('data-kolo-slug', slug);
                        var text = document.createElement('span');
                        text.textContent = title;
                        head.appendChild(text);
                        grid.insertBefore(head, item);
                        lastSlug = slug;
                    });
                }

                function render() {
                    allItems.forEach(function(item){ item.style.display = 'none'; });
                    var shown = allItems.slice(0, Math.max(1, visibleCount));
                    shown.forEach(function(item){
                        item.style.display = '';
                        grid.appendChild(item);
                    });
                    renderRoundHeadings(shown);
                    sentinel.style.display = shown.length < allItems.length ? '' : 'none';
                }

                if ('IntersectionObserver' in window) {
                    var observer = new IntersectionObserver(function(entries){
                        entries.forEach(function(entry){
                            if (entry.isIntersecting) {
                                visibleCount += chunkSize;
                                render();
                                if (observer && sentinel && sentinel.style.display !== 'none') {
                                    observer.unobserve(sentinel);
                                    setTimeout(function(){
                                        if (observer && sentinel && sentinel.style.display !== 'none') {
                                            observer.observe(sentinel);
                                        }
                                    }, 0);
                                }
                            }
                        });
                    }, { rootMargin: '240px 0px' });
                    observer.observe(sentinel);
                }
                render();
            })();
            </script>
            <?php
            return ob_get_clean();
        }

        return (string) $call('shortcode_title_html', 'Utakmice') . (string) $call('render_matches_grid_html', $rows, $columns, false);
    }
}

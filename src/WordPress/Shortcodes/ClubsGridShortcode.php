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

final class ClubsGridShortcode
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

        $atts = shortcode_atts([
            'columns' => 4,
            'limit' => -1,
            'filter' => '',
            'infinite' => '',
        ], $atts);

        $columns = max(1, min(6, intval($atts['columns'])));
        $limit = intval($atts['limit']);
        if ($limit === 0) {
            $limit = -1;
        }
        $filter_mode = strtolower(trim((string) $atts['filter']));
        $enable_filters = in_array($filter_mode, ['1', 'true', 'yes', 'da', 'on'], true);
        $infinite_mode = in_array(strtolower(trim((string) $atts['infinite'])), ['1', 'true', 'yes', 'da', 'on'], true);
        $chunk_size = $limit > 0 ? $limit : 8;

        $club_posts = get_posts([
            'post_type' => 'klub',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'private', 'draft', 'pending'],
        ]) ?: [];

        if (empty($club_posts)) {
            return (string) $call('shortcode_title_html', 'Klubovi') . '<p>Nema klubova za prikaz.</p>';
        }

        $rows = [];
        foreach ($club_posts as $club) {
            $club_id = intval($club->ID);
            if ($club_id <= 0) {
                continue;
            }

            $title = trim((string) $club->post_title);
            if ($title === '') {
                $title = 'Klub';
            }
            $display_name = preg_match('/^stk\s+/iu', $title) ? $title : ('STK ' . $title);
            $sort_name = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);

            $opstina = trim((string) get_post_meta($club_id, 'opstina', true));
            $opstina_slug = $opstina !== '' ? sanitize_title($opstina) : '';
            $opstina_label = $opstina;
            $grad = trim((string) get_post_meta($club_id, 'grad', true));

            $league_slug = '';
            $league_label = 'Bez takmičenja';
            $comp = $call('db_get_latest_competition_for_club', $club_id);
            if (is_array($comp)) {
                $league_slug = sanitize_title((string) ($comp['liga_slug'] ?? ''));
                $season_slug = sanitize_title((string) ($comp['sezona_slug'] ?? ''));
                if ($league_slug !== '' && $season_slug === '') {
                    $parsed = $call('parse_legacy_liga_sezona', $league_slug, '');
                    $parsed = is_array($parsed) ? $parsed : [];
                    $league_slug = sanitize_title((string) ($parsed['league_slug'] ?? $league_slug));
                }
                if ($league_slug !== '') {
                    $league_label = (string) $call('slug_to_title', $league_slug);
                    if ($league_label === '') {
                        $league_label = $league_slug;
                    }
                }
            }

            $logo_html = (string) $call('club_logo_html', $club_id, 'thumbnail', [
                'class' => 'opentt-klubovi-logo',
                'loading' => 'lazy',
            ]);
            if (trim($logo_html) === '') {
                $logo_html = '<span class="opentt-klubovi-logo-fallback" aria-hidden="true">🏓</span>';
            }

            $rows[] = [
                'id' => $club_id,
                'url' => get_permalink($club_id) ?: '#',
                'title' => $title,
                'display_name' => $display_name,
                'sort_name' => $sort_name,
                'league_slug' => $league_slug,
                'league_label' => $league_label,
                'opstina_slug' => $opstina_slug,
                'opstina_label' => $opstina_label,
                'grad_label' => $grad,
                'logo_html' => $logo_html,
            ];
        }

        if (empty($rows)) {
            return (string) $call('shortcode_title_html', 'Klubovi') . '<p>Nema klubova za prikaz.</p>';
        }

        usort($rows, function ($a, $b) {
            return strnatcasecmp((string) ($a['sort_name'] ?? ''), (string) ($b['sort_name'] ?? ''));
        });

        if (!$infinite_mode && $limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        if (empty($rows)) {
            return (string) $call('shortcode_title_html', 'Klubovi') . '<p>Nema klubova za prikaz.</p>';
        }

        $league_options = [];
        $opstina_options = [];
        foreach ($rows as $row) {
            $l_slug = sanitize_title((string) ($row['league_slug'] ?? ''));
            if ($l_slug !== '') {
                $league_options[$l_slug] = (string) ($row['league_label'] ?? $l_slug);
            }
            $o_slug = sanitize_title((string) ($row['opstina_slug'] ?? ''));
            if ($o_slug !== '') {
                $opstina_options[$o_slug] = (string) ($row['opstina_label'] ?? $o_slug);
            }
        }
        uasort($league_options, function ($a, $b) {
            return strnatcasecmp((string) $a, (string) $b);
        });
        uasort($opstina_options, function ($a, $b) {
            return strnatcasecmp((string) $a, (string) $b);
        });

        if ($enable_filters) {
            $selected_liga = isset($_GET['opentt_club_league']) ? sanitize_title((string) wp_unslash($_GET['opentt_club_league'])) : '';
            $selected_opstina = isset($_GET['opentt_club_municipality']) ? sanitize_title((string) wp_unslash($_GET['opentt_club_municipality'])) : '';
            $selected_sort = isset($_GET['opentt_club_sort']) ? sanitize_key((string) wp_unslash($_GET['opentt_club_sort'])) : 'name_asc';
            if (!in_array($selected_sort, ['name_asc', 'name_desc'], true)) {
                $selected_sort = 'name_asc';
            }

            $rows = array_values(array_filter($rows, function ($row) use ($selected_liga, $selected_opstina) {
                $liga_ok = ($selected_liga === '' || sanitize_title((string) ($row['league_slug'] ?? '')) === $selected_liga);
                $opstina_ok = ($selected_opstina === '' || sanitize_title((string) ($row['opstina_slug'] ?? '')) === $selected_opstina);
                return $liga_ok && $opstina_ok;
            }));

            usort($rows, function ($a, $b) use ($selected_sort) {
                $cmp = strnatcasecmp((string) ($a['sort_name'] ?? ''), (string) ($b['sort_name'] ?? ''));
                return $selected_sort === 'name_desc' ? (-1 * $cmp) : $cmp;
            });

            if (empty($rows)) {
                return (string) $call('shortcode_title_html', 'Klubovi') . '<p>Nema klubova za zadate filtere.</p>';
            }

            $uid = 'opentt-klubovi-' . wp_unique_id();
            ob_start();
            echo (string) $call('shortcode_title_html', 'Klubovi');
            echo '<div id="' . esc_attr($uid) . '" class="opentt-klubovi-block">';
            echo '<form method="get" class="opentt-grid-filters">';
            foreach ($_GET as $k => $v) {
                $k = (string) $k;
                if (in_array($k, ['opentt_club_league', 'opentt_club_municipality', 'opentt_club_sort'], true)) {
                    continue;
                }
                if (is_array($v)) {
                    continue;
                }
                echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string) wp_unslash($v)) . '">';
            }
            echo '<label>Liga <select name="opentt_club_league" onchange="this.form.submit()"><option value="">Sve lige</option>';
            foreach ($league_options as $slug => $label) {
                echo '<option value="' . esc_attr((string) $slug) . '"' . selected($selected_liga, (string) $slug, false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select></label>';

            echo '<label>Opština <select name="opentt_club_municipality" onchange="this.form.submit()"><option value="">Sve opštine</option>';
            foreach ($opstina_options as $slug => $label) {
                echo '<option value="' . esc_attr((string) $slug) . '"' . selected($selected_opstina, (string) $slug, false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select></label>';

            echo '<label>Sortiranje <select name="opentt_club_sort" onchange="this.form.submit()">';
            echo '<option value="name_asc"' . selected($selected_sort, 'name_asc', false) . '>Ime: A-Z</option>';
            echo '<option value="name_desc"' . selected($selected_sort, 'name_desc', false) . '>Ime: Z-A</option>';
            echo '</select></label>';
            if ($selected_liga !== '' || $selected_opstina !== '' || isset($_GET['opentt_club_sort'])) {
                echo '<a class="button opentt-grid-filter-reset" href="' . esc_url(remove_query_arg(['opentt_club_league', 'opentt_club_municipality', 'opentt_club_sort'])) . '">Reset</a>';
            }
            echo '</form>';

            echo (string) $call('render_clubs_grid_html', $rows, $columns, false);
            if ($infinite_mode) {
                echo '<div class="opentt-klubovi-sentinel" aria-hidden="true"></div>';
            }
            echo '</div>';
            if ($infinite_mode) {
                ?>
                <script>
                (function(){
                    var root = document.getElementById('<?php echo esc_js($uid); ?>');
                    if (!root) { return; }
                    var grid = root.querySelector('.opentt-klubovi-grid');
                    var sentinel = root.querySelector('.opentt-klubovi-sentinel');
                    if (!grid || !sentinel) { return; }
                    var chunkSize = <?php echo intval($chunk_size); ?>;
                    var visibleCount = chunkSize;
                    var allItems = Array.prototype.slice.call(grid.querySelectorAll('.opentt-klubovi-item'));

                    function render() {
                        allItems.forEach(function(item){ item.style.display = 'none'; });
                        var shown = allItems.slice(0, Math.max(1, visibleCount));
                        shown.forEach(function(item){
                            item.style.display = '';
                            grid.appendChild(item);
                        });
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
                        }, { rootMargin: '220px 0px' });
                        observer.observe(sentinel);
                    }
                    render();
                })();
                </script>
                <?php
            }
            return ob_get_clean();
        }

        if ($infinite_mode) {
            $uid = 'opentt-klubovi-' . wp_unique_id();
            ob_start();
            echo (string) $call('shortcode_title_html', 'Klubovi');
            echo '<div id="' . esc_attr($uid) . '" class="opentt-klubovi-block">';
            echo (string) $call('render_clubs_grid_html', $rows, $columns, false);
            echo '<div class="opentt-klubovi-sentinel" aria-hidden="true"></div>';
            echo '</div>';
            ?>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js($uid); ?>');
                if (!root) { return; }
                var grid = root.querySelector('.opentt-klubovi-grid');
                var sentinel = root.querySelector('.opentt-klubovi-sentinel');
                if (!grid || !sentinel) { return; }
                var chunkSize = <?php echo intval($chunk_size); ?>;
                var visibleCount = chunkSize;
                var allItems = Array.prototype.slice.call(grid.querySelectorAll('.opentt-klubovi-item'));

                function render() {
                    allItems.forEach(function(item){ item.style.display = 'none'; });
                    var shown = allItems.slice(0, Math.max(1, visibleCount));
                    shown.forEach(function(item){
                        item.style.display = '';
                        grid.appendChild(item);
                    });
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
                    }, { rootMargin: '220px 0px' });
                    observer.observe(sentinel);
                }
                render();
            })();
            </script>
            <?php
            return ob_get_clean();
        }

        return (string) $call('shortcode_title_html', 'Klubovi') . (string) $call('render_clubs_grid_html', $rows, $columns, false);
    }
}

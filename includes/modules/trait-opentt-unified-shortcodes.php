<?php

if (!defined('ABSPATH')) {
    exit;
}

trait OpenTT_Unified_Shortcodes_Trait
{
    private static function shortcode_title_html($title)
    {
        $title = trim((string) $title);
        if ($title === '') {
            return '';
        }
        if (class_exists('OpenTT_Unified_Core') && method_exists('OpenTT_Unified_Core', 'should_show_shortcode_titles') && !OpenTT_Unified_Core::should_show_shortcode_titles()) {
            return '';
        }
        return '<h3 class="stkb-shortcode-title">' . esc_html($title) . '</h3>';
    }

    public static function shortcode_matches_grid($atts)
    {
        $atts = shortcode_atts([
            'columns' => 3,
            'limit' => 5,
            'klub' => '',
            'odigrana' => '',
            'liga' => '',
            'sezona' => '',
            'filter' => '',
            'infinite' => '',
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

        $query_args = self::build_match_query_args($atts);
        if ($legacy_kolo_filter || $enable_filters || $infinite_mode) {
            $query_args['limit'] = -1;
        }

        $rows = self::db_get_matches($query_args);

        if (empty($rows)) {
            return self::shortcode_title_html('Utakmice') . '<p>Nema utakmica za prikaz.</p>';
        }

        if ($legacy_kolo_filter) {
            $kolo_map = [];
            foreach ($rows as $row) {
                $slug = (string) $row->kolo_slug;
                if ($slug === '') {
                    continue;
                }
                $kolo_map[$slug] = self::kolo_name_from_slug($slug);
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
            echo self::shortcode_title_html('Utakmice');
            echo '<div class="stkb-kolo-filter-wrap">';
            echo '<label for="stkb-kolo">Izaberi kolo:</label>';
            echo '<select id="stkb-kolo" onchange="stkbFilterKoloChange(this)">';
            echo '<option value="">Sva kola</option>';
            foreach ($options as $opt) {
                echo '<option value="' . esc_attr($opt['slug']) . '">' . esc_html($opt['name']) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo self::render_matches_grid_html($rows, $columns, true);
            ?>
            <script>
            function stkbFilterKoloChange(sel) {
                var selected = sel.value;
                var items = document.querySelectorAll('.stkb-item');
                items.forEach(function(it){
                    var slug = it.getAttribute('data-kolo-slug') || '';
                    it.style.display = (!selected || slug === selected) ? 'block' : 'none';
                });
            }
            </script>
            <?php
            return ob_get_clean();
        }

        if ($enable_filters) {
            $selected_kolo = isset($_GET['stkb_kolo']) ? sanitize_title((string) wp_unslash($_GET['stkb_kolo'])) : '';
            $selected_club = isset($_GET['stkb_klub']) ? intval($_GET['stkb_klub']) : 0;
            $selected_sort = isset($_GET['stkb_sort']) ? sanitize_key((string) wp_unslash($_GET['stkb_sort'])) : 'kolo_desc';
            if (!in_array($selected_sort, ['kolo_desc', 'kolo_asc', 'date_desc', 'date_asc'], true)) {
                $selected_sort = 'kolo_desc';
            }

            $kolo_map = [];
            $club_map = [];
            foreach ($rows as $row) {
                $kolo_slug = sanitize_title((string) $row->kolo_slug);
                if ($kolo_slug !== '') {
                    $kolo_map[$kolo_slug] = self::kolo_name_from_slug($kolo_slug);
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
                    'num' => self::extract_round_no((string) $slug),
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

            // Server fallback: uvek primeni filter/sort (default je kolo_desc) da prvi load/reset bude stabilan.
            $rows = array_values(array_filter($rows, function ($row) use ($selected_kolo, $selected_club) {
                $kolo_ok = ($selected_kolo === '' || sanitize_title((string) $row->kolo_slug) === $selected_kolo);
                $club_ok = (
                    $selected_club <= 0
                    || intval($row->home_club_post_id) === $selected_club
                    || intval($row->away_club_post_id) === $selected_club
                );
                return $kolo_ok && $club_ok;
            }));

            usort($rows, function ($a, $b) use ($selected_sort) {
                $a_ts = strtotime((string) $a->match_date);
                $b_ts = strtotime((string) $b->match_date);
                $a_ts = ($a_ts === false) ? 0 : intval($a_ts);
                $b_ts = ($b_ts === false) ? 0 : intval($b_ts);
                $a_k = self::extract_round_no((string) $a->kolo_slug);
                $b_k = self::extract_round_no((string) $b->kolo_slug);

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

            $uid = 'stkb-grid-' . wp_unique_id();

            ob_start();
            echo self::shortcode_title_html('Utakmice');
            echo '<div id="' . esc_attr($uid) . '" class="stkb-grid-filter-block">';
            echo '<form method="get" class="stkb-grid-filters">';
            foreach ($_GET as $k => $v) {
                $k = (string) $k;
                if (in_array($k, ['stkb_kolo', 'stkb_klub', 'stkb_sort'], true)) {
                    continue;
                }
                if (is_array($v)) {
                    continue;
                }
                echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string) wp_unslash($v)) . '">';
            }
            echo '<label>Kolo <select name="stkb_kolo" class="stkb-grid-filter-kolo" onchange="this.form.submit()"><option value="">Sva kola</option>';
            foreach ($kolo_options as $opt) {
                echo '<option value="' . esc_attr((string) $opt['slug']) . '" ' . selected($selected_kolo, (string) $opt['slug'], false) . '>' . esc_html((string) $opt['name']) . '</option>';
            }
            echo '</select></label>';

            echo '<label>Klub <select name="stkb_klub" class="stkb-grid-filter-club" onchange="this.form.submit()"><option value="">Svi klubovi</option>';
            foreach ($club_options as $opt) {
                echo '<option value="' . esc_attr((string) $opt['id']) . '" ' . selected($selected_club, intval($opt['id']), false) . '>' . esc_html((string) $opt['name']) . '</option>';
            }
            echo '</select></label>';

            echo '<label>Sortiranje <select name="stkb_sort" class="stkb-grid-filter-sort" onchange="this.form.submit()">';
            echo '<option value="kolo_desc" ' . selected($selected_sort, 'kolo_desc', false) . '>Kolo: najnovije</option>';
            echo '<option value="kolo_asc" ' . selected($selected_sort, 'kolo_asc', false) . '>Kolo: najstarije</option>';
            echo '<option value="date_desc" ' . selected($selected_sort, 'date_desc', false) . '>Datum: najnovije</option>';
            echo '<option value="date_asc" ' . selected($selected_sort, 'date_asc', false) . '>Datum: najstarije</option>';
            echo '</select></label>';
            if ($selected_kolo !== '' || $selected_club > 0 || isset($_GET['stkb_sort'])) {
                echo '<a class="button stkb-grid-filter-reset" href="' . esc_url(remove_query_arg(['stkb_kolo', 'stkb_klub', 'stkb_sort'])) . '">Reset</a>';
            }
            echo '</form>';

            echo self::render_matches_grid_html($rows, $columns, true);
            if ($infinite_mode) {
                echo '<div class="stkb-grid-sentinel" aria-hidden="true"></div>';
            }
            echo '</div>';
            ?>
            <script>
            (function(){
                var rootId = '<?php echo esc_js($uid); ?>';
                function init() {
                    var root = document.getElementById(rootId);
                    if (!root) { return false; }
                    var koloSelect = root.querySelector('.stkb-grid-filter-kolo');
                    var clubSelect = root.querySelector('.stkb-grid-filter-club');
                    var sortSelect = root.querySelector('.stkb-grid-filter-sort');
                    var grid = root.querySelector('.stkb-grid');
                    if (!grid) { return false; }
                    var sentinel = root.querySelector('.stkb-grid-sentinel');
                    var infiniteEnabled = <?php echo $infinite_mode ? 'true' : 'false'; ?>;
                    var chunkSize = <?php echo intval($chunk_size); ?>;
                    var visibleCount = chunkSize;
                    var observer = null;
                    var allItems = Array.prototype.slice.call(grid.querySelectorAll('.stkb-item'));

                    function getNum(val) {
                        var n = parseInt(val || '0', 10);
                        return isNaN(n) ? 0 : n;
                    }

                    function matchesFilter(item) {
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
                        if (infiniteEnabled && sentinel) {
                            sentinel.style.display = (toRender.length < visible.length) ? '' : 'none';
                        }
                    }

                    function resetAndRender() {
                        visibleCount = chunkSize;
                        render();
                    }

                    if (koloSelect) { koloSelect.addEventListener('change', resetAndRender); }
                    if (clubSelect) { clubSelect.addEventListener('change', resetAndRender); }
                    if (sortSelect) { sortSelect.addEventListener('change', resetAndRender); }

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
            $uid = 'stkb-grid-' . wp_unique_id();
            ob_start();
            echo self::shortcode_title_html('Utakmice');
            echo '<div id="' . esc_attr($uid) . '" class="stkb-grid-infinite-block">';
            echo self::render_matches_grid_html($rows, $columns, false);
            echo '<div class="stkb-grid-sentinel" aria-hidden="true"></div>';
            echo '</div>';
            ?>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js($uid); ?>');
                if (!root) { return; }
                var grid = root.querySelector('.stkb-grid');
                var sentinel = root.querySelector('.stkb-grid-sentinel');
                if (!grid || !sentinel) { return; }
                var chunkSize = <?php echo intval($chunk_size); ?>;
                var visibleCount = chunkSize;
                var allItems = Array.prototype.slice.call(grid.querySelectorAll('.stkb-item'));

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
                    }, { rootMargin: '240px 0px' });
                    observer.observe(sentinel);
                }
                render();
            })();
            </script>
            <?php
            return ob_get_clean();
        }

        return self::shortcode_title_html('Utakmice') . self::render_matches_grid_html($rows, $columns, false);
    }

    public static function shortcode_matches_list($atts)
    {
        $atts = shortcode_atts([
            'limit' => 5,
            'klub' => '',
            'odigrana' => '',
            'liga' => '',
            'sezona' => '',
        ], $atts);

        $rows = self::db_get_matches(self::build_match_query_args($atts));
        if (empty($rows)) {
            return self::shortcode_title_html('Utakmice lista') . '<p>Nema utakmica za prikaz.</p>';
        }

        ob_start();
        echo self::shortcode_title_html('Utakmice lista');
        echo '<ul class="stkb-list">';
        foreach ($rows as $row) {
            $home_id = intval($row->home_club_post_id);
            $away_id = intval($row->away_club_post_id);
            $rd = intval($row->home_score);
            $rg = intval($row->away_score);
            $home_win = ($rd === 4);
            $away_win = ($rg === 4);
            $date = self::display_match_date($row->match_date);
            $link = self::match_permalink($row);

            echo '<li><a href="' . esc_url($link) . '">';
            echo '<span class="' . esc_attr($home_win ? 'pobednik' : 'gubitnik') . '">' . esc_html(get_the_title($home_id)) . ' ' . intval($rd) . '</span>';
            echo ' : ';
            echo '<span class="' . esc_attr($away_win ? 'pobednik' : 'gubitnik') . '">' . intval($rg) . ' ' . esc_html(get_the_title($away_id)) . '</span>';
            if ($date !== '') {
                echo ' – ' . esc_html($date);
            }
            echo '</a></li>';
        }
        echo '</ul>';
        return ob_get_clean();
    }

    public static function shortcode_clubs_grid($atts = [])
    {
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
            return self::shortcode_title_html('Klubovi') . '<p>Nema klubova za prikaz.</p>';
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
            $comp = self::db_get_latest_competition_for_club($club_id);
            if (is_array($comp)) {
                $league_slug = sanitize_title((string) ($comp['liga_slug'] ?? ''));
                $season_slug = sanitize_title((string) ($comp['sezona_slug'] ?? ''));
                if ($league_slug !== '' && $season_slug === '') {
                    $parsed = self::parse_legacy_liga_sezona($league_slug, '');
                    $league_slug = sanitize_title((string) ($parsed['league_slug'] ?? $league_slug));
                }
                if ($league_slug !== '') {
                    $league_label = self::slug_to_title($league_slug);
                    if ($league_label === '') {
                        $league_label = $league_slug;
                    }
                }
            }

            $logo_html = self::club_logo_html($club_id, 'thumbnail', [
                'class' => 'stkb-klubovi-logo',
                'loading' => 'lazy',
            ]);
            if (!is_string($logo_html) || trim($logo_html) === '') {
                $logo_html = '<span class="stkb-klubovi-logo-fallback" aria-hidden="true">🏓</span>';
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
            return self::shortcode_title_html('Klubovi') . '<p>Nema klubova za prikaz.</p>';
        }

        usort($rows, function ($a, $b) {
            return strnatcasecmp((string) ($a['sort_name'] ?? ''), (string) ($b['sort_name'] ?? ''));
        });

        if (!$infinite_mode && $limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        if (empty($rows)) {
            return self::shortcode_title_html('Klubovi') . '<p>Nema klubova za prikaz.</p>';
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
            $selected_liga = isset($_GET['stkb_klub_liga']) ? sanitize_title((string) wp_unslash($_GET['stkb_klub_liga'])) : '';
            $selected_opstina = isset($_GET['stkb_klub_opstina']) ? sanitize_title((string) wp_unslash($_GET['stkb_klub_opstina'])) : '';
            $selected_sort = isset($_GET['stkb_klub_sort']) ? sanitize_key((string) wp_unslash($_GET['stkb_klub_sort'])) : 'name_asc';
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
                return self::shortcode_title_html('Klubovi') . '<p>Nema klubova za zadate filtere.</p>';
            }

            $uid = 'stkb-klubovi-' . wp_unique_id();
            ob_start();
            echo self::shortcode_title_html('Klubovi');
            echo '<div id="' . esc_attr($uid) . '" class="stkb-klubovi-block">';
            echo '<form method="get" class="stkb-grid-filters">';
            foreach ($_GET as $k => $v) {
                $k = (string) $k;
                if (in_array($k, ['stkb_klub_liga', 'stkb_klub_opstina', 'stkb_klub_sort'], true)) {
                    continue;
                }
                if (is_array($v)) {
                    continue;
                }
                echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string) wp_unslash($v)) . '">';
            }
            echo '<label>Liga <select name="stkb_klub_liga" onchange="this.form.submit()"><option value="">Sve lige</option>';
            foreach ($league_options as $slug => $label) {
                echo '<option value="' . esc_attr((string) $slug) . '"' . selected($selected_liga, (string) $slug, false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select></label>';

            echo '<label>Opština <select name="stkb_klub_opstina" onchange="this.form.submit()"><option value="">Sve opštine</option>';
            foreach ($opstina_options as $slug => $label) {
                echo '<option value="' . esc_attr((string) $slug) . '"' . selected($selected_opstina, (string) $slug, false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select></label>';

            echo '<label>Sortiranje <select name="stkb_klub_sort" onchange="this.form.submit()">';
            echo '<option value="name_asc"' . selected($selected_sort, 'name_asc', false) . '>Ime: A-Z</option>';
            echo '<option value="name_desc"' . selected($selected_sort, 'name_desc', false) . '>Ime: Z-A</option>';
            echo '</select></label>';
            if ($selected_liga !== '' || $selected_opstina !== '' || isset($_GET['stkb_klub_sort'])) {
                echo '<a class="button stkb-grid-filter-reset" href="' . esc_url(remove_query_arg(['stkb_klub_liga', 'stkb_klub_opstina', 'stkb_klub_sort'])) . '">Reset</a>';
            }
            echo '</form>';

            echo self::render_clubs_grid_html($rows, $columns, false);
            if ($infinite_mode) {
                echo '<div class="stkb-klubovi-sentinel" aria-hidden="true"></div>';
            }
            echo '</div>';
            if ($infinite_mode) {
                ?>
                <script>
                (function(){
                    var root = document.getElementById('<?php echo esc_js($uid); ?>');
                    if (!root) { return; }
                    var grid = root.querySelector('.stkb-klubovi-grid');
                    var sentinel = root.querySelector('.stkb-klubovi-sentinel');
                    if (!grid || !sentinel) { return; }
                    var chunkSize = <?php echo intval($chunk_size); ?>;
                    var visibleCount = chunkSize;
                    var allItems = Array.prototype.slice.call(grid.querySelectorAll('.stkb-klubovi-item'));

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
            $uid = 'stkb-klubovi-' . wp_unique_id();
            ob_start();
            echo self::shortcode_title_html('Klubovi');
            echo '<div id="' . esc_attr($uid) . '" class="stkb-klubovi-block">';
            echo self::render_clubs_grid_html($rows, $columns, false);
            echo '<div class="stkb-klubovi-sentinel" aria-hidden="true"></div>';
            echo '</div>';
            ?>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js($uid); ?>');
                if (!root) { return; }
                var grid = root.querySelector('.stkb-klubovi-grid');
                var sentinel = root.querySelector('.stkb-klubovi-sentinel');
                if (!grid || !sentinel) { return; }
                var chunkSize = <?php echo intval($chunk_size); ?>;
                var visibleCount = chunkSize;
                var allItems = Array.prototype.slice.call(grid.querySelectorAll('.stkb-klubovi-item'));

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

        return self::shortcode_title_html('Klubovi') . self::render_clubs_grid_html($rows, $columns, false);
    }

    public static function shortcode_show_players($atts = [])
    {
        $atts = shortcode_atts([
            'klub' => '',
        ], $atts);

        $klub_id = 0;
        if (!empty($atts['klub'])) {
            $klub_post = get_page_by_path(sanitize_title((string) $atts['klub']), OBJECT, 'klub');
            if (!$klub_post) {
                $klub_post = get_page_by_title((string) $atts['klub'], OBJECT, 'klub');
            }
            if ($klub_post && !is_wp_error($klub_post)) {
                $klub_id = intval($klub_post->ID);
            }
        } elseif (is_singular('klub')) {
            $klub_id = intval(get_the_ID());
        }

        if ($klub_id <= 0) {
            return '<p>Nije pronađen klub.</p>';
        }

        $q = new WP_Query([
            'post_type' => 'igrac',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'povezani_klub',
                    'value' => $klub_id,
                    'compare' => '=',
                ],
                [
                    'key' => 'klub_igraca',
                    'value' => $klub_id,
                    'compare' => '=',
                ],
            ],
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        if (!$q->have_posts()) {
            return self::shortcode_title_html('Igrači') . '<p>Nema registrovanih igrača za ovaj klub.</p>';
        }

        ob_start();
        echo self::shortcode_title_html('Igrači');
        echo '<div class="stoni-igraci-list">';
        while ($q->have_posts()) {
            $q->the_post();
            $id = intval(get_the_ID());
            $slika = get_the_post_thumbnail($id, 'medium', ['class' => 'stoni-igrac-slika']);
            if (!$slika) {
                $slika = '<img src="' . esc_url(self::player_fallback_image_url()) . '" alt="Igrač" class="stoni-igrac-slika" />';
            }
            $ime = (string) get_the_title($id);
            $link = get_permalink($id);

            $ime_ime = $ime;
            $ime_prezime = '';
            if (strpos($ime, ' ') !== false) {
                $parts = explode(' ', $ime, 2);
                $ime_ime = (string) ($parts[0] ?? '');
                $ime_prezime = (string) ($parts[1] ?? '');
            }

            echo '<div class="stoni-igrac-card">';
            echo '<a href="' . esc_url($link) . '" class="stoni-igrac-row">';
            echo '<div class="stoni-igrac-left">';
            echo $slika; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<div class="stoni-igrac-ime">';
            echo '<span class="stoni-igrac-ime-ime">' . esc_html($ime_ime) . '</span>';
            echo '<span class="stoni-igrac-ime-prezime">' . esc_html($ime_prezime) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '<div class="stoni-igrac-right"><span class="stoni-igrac-detalji">Detalji igrača</span></div>';
            echo '</a>';
            echo '</div>';
        }
        echo '</div>';
        wp_reset_postdata();

        return ob_get_clean();
    }

    public static function shortcode_club_news($atts = [])
    {
        $atts = shortcode_atts([
            'klub' => '',
            'limit' => 6,
            'columns' => 3,
        ], $atts);

        $tag_slug = '';
        if (empty($atts['klub']) && is_singular('klub')) {
            $tag_slug = sanitize_title((string) get_the_title(get_the_ID()));
        }
        if (!empty($atts['klub'])) {
            $tag_slug = sanitize_title((string) $atts['klub']);
        }

        if ($tag_slug === '') {
            return '<p>Nema pronađenih vesti za ovaj klub.</p>';
        }

        $limit = intval($atts['limit']);
        if ($limit === 0) {
            $limit = -1;
        }
        $columns = intval($atts['columns']);
        if ($columns < 1 || $columns > 6) {
            $columns = 3;
        }

        $q = new WP_Query([
            'post_type' => 'post',
            'posts_per_page' => $limit,
            'tag' => $tag_slug,
        ]);

        if (!$q->have_posts()) {
            return self::shortcode_title_html('Vesti kluba') . '<p>Trenutno nema vesti za ovaj klub.</p>';
        }

        ob_start();
        echo self::shortcode_title_html('Vesti kluba');
        echo '<div class="stoni-vesti-grid stoni-vesti-cols-' . esc_attr((string) $columns) . '">';
        while ($q->have_posts()) {
            $q->the_post();
            $link = get_permalink();
            $title = get_the_title();
            $date = get_the_date();
            $thumbnail = get_the_post_thumbnail(get_the_ID(), 'medium_large', ['class' => 'vest-klub-slika']);

            echo '<a class="stoni-vesti-kartica" href="' . esc_url($link) . '">';
            echo $thumbnail ?: '<div class="vest-klub-slika prazna"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<div class="vest-klub-naslov">' . esc_html($title) . '</div>';
            echo '<div class="vest-klub-datum">' . esc_html((string) $date) . '</div>';
            echo '</a>';
        }
        echo '</div>';
        wp_reset_postdata();

        return ob_get_clean();
    }

    public static function shortcode_player_news($atts = [])
    {
        $atts = shortcode_atts([
            'igrac' => '',
            'limit' => 6,
            'columns' => 3,
        ], $atts);

        $player_name = '';
        $tag_slug = '';
        if (empty($atts['igrac']) && is_singular('igrac')) {
            $player_name = (string) get_the_title(get_the_ID());
            $tag_slug = sanitize_title($player_name);
        }
        if (!empty($atts['igrac'])) {
            $player_name = (string) $atts['igrac'];
            $tag_slug = sanitize_title((string) $atts['igrac']);
        }

        if ($tag_slug === '' && $player_name === '') {
            return '<p>Nema pronađenih vesti za ovog igrača.</p>';
        }

        $limit = intval($atts['limit']);
        if ($limit === 0) {
            $limit = -1;
        }
        $columns = intval($atts['columns']);
        if ($columns < 1 || $columns > 6) {
            $columns = 3;
        }

        $q = new WP_Query([
            'post_type' => 'post',
            'posts_per_page' => $limit,
            'tag' => $tag_slug,
        ]);

        // Fallback: ako nema tag pogodaka, probaj po nazivu igrača u sadržaju.
        if (!$q->have_posts() && $player_name !== '') {
            $q = new WP_Query([
                'post_type' => 'post',
                'posts_per_page' => $limit,
                's' => $player_name,
            ]);
        }

        if (!$q->have_posts()) {
            return self::shortcode_title_html('Vesti igrača') . '<p>Trenutno nema vesti za ovog igrača.</p>';
        }

        ob_start();
        echo self::shortcode_title_html('Vesti igrača');
        echo '<div class="stoni-vesti-grid stoni-vesti-cols-' . esc_attr((string) $columns) . '">';
        while ($q->have_posts()) {
            $q->the_post();
            $link = get_permalink();
            $title = get_the_title();
            $date = get_the_date();
            $thumbnail = get_the_post_thumbnail(get_the_ID(), 'medium_large', ['class' => 'vest-klub-slika']);

            echo '<a class="stoni-vesti-kartica" href="' . esc_url($link) . '">';
            echo $thumbnail ?: '<div class="vest-klub-slika prazna"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<div class="vest-klub-naslov">' . esc_html($title) . '</div>';
            echo '<div class="vest-klub-datum">' . esc_html((string) $date) . '</div>';
            echo '</a>';
        }
        echo '</div>';
        wp_reset_postdata();

        return ob_get_clean();
    }

    public static function shortcode_related_posts($atts = [])
    {
        if (!is_singular('post')) {
            return '';
        }

        $post_id = intval(get_the_ID());
        if ($post_id <= 0) {
            return '';
        }

        $tags = wp_get_post_tags($post_id, ['fields' => 'ids']);
        $args = [
            'post__not_in' => [$post_id],
            'posts_per_page' => 4,
            'ignore_sticky_posts' => 1,
        ];

        if (!empty($tags)) {
            $args['tag__in'] = $tags;
        } else {
            $categories = wp_get_post_categories($post_id, ['fields' => 'ids']);
            if (!empty($categories)) {
                $args['category__in'] = $categories;
            }
        }

        $related = new WP_Query($args);
        if (!$related->have_posts()) {
            return '';
        }

        ob_start();
        echo self::shortcode_title_html('Povezane objave');
        echo '<div class="bbs-related-posts">';
        while ($related->have_posts()) {
            $related->the_post();
            $category_list = get_the_category_list(', ');
            echo '<div class="related-post-item">';
            echo '<div class="related-post-thumb"><a href="' . esc_url(get_permalink()) . '">';
            if (has_post_thumbnail()) {
                echo get_the_post_thumbnail(get_the_ID(), 'medium'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            echo '</a></div>';
            echo '<div class="related-post-content">';
            echo '<h3><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
            echo '<p class="excerpt">' . esc_html(get_the_excerpt()) . '</p>';
            echo '<div class="meta"><span class="category">' . ($category_list ? $category_list : '') . '</span><span class="date">' . esc_html(get_the_date()) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</div></div>';
        }
        echo '</div>';
        wp_reset_postdata();

        return ob_get_clean();
    }

    public static function shortcode_standings_table($atts = [])
    {
        $atts = shortcode_atts([
            'liga' => '',
            'sezona' => '',
            'highlight' => '',
        ], $atts);

        $highlight_ids = [];
        $liga_slug = '';
        $sezona_slug = '';
        $max_kolo = null;
        $ctx = self::current_match_context();
        $archive_ctx = self::current_archive_context();

        if (!empty($atts['liga'])) {
            $raw_liga = sanitize_title((string) $atts['liga']);
            $raw_sezona = !empty($atts['sezona']) ? sanitize_title((string) $atts['sezona']) : '';

            $parsed = self::parse_legacy_liga_sezona($raw_liga, $raw_sezona);
            $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? $raw_liga));
            $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? $raw_sezona));

            $term = get_term_by('slug', $raw_liga, 'liga_sezona');
            if ($term && !is_wp_error($term)) {
                $parsed_term = self::parse_legacy_liga_sezona((string) $term->slug, $sezona_slug);
                $liga_slug = sanitize_title((string) ($parsed_term['league_slug'] ?? $liga_slug));
                $sezona_slug = sanitize_title((string) ($parsed_term['season_slug'] ?? $sezona_slug));
            }

            if (!empty($atts['highlight'])) {
                $names = array_filter(array_map('trim', explode(',', (string) $atts['highlight'])));
                foreach ($names as $name) {
                    if (is_numeric($name)) {
                        $highlight_ids[] = intval($name);
                        continue;
                    }
                    $post = get_page_by_path($name, OBJECT, 'klub');
                    if (!$post) {
                        $post = get_page_by_title($name, OBJECT, 'klub');
                    }
                    if ($post && !is_wp_error($post)) {
                        $highlight_ids[] = intval($post->ID);
                    }
                }
            }
        } elseif ($ctx && !empty($ctx['db_row'])) {
            $db_row = $ctx['db_row'];
            if (!$db_row && is_singular('utakmica')) {
                $legacy_match_id = intval(get_the_ID());
                $db_row = self::db_get_match_by_legacy_id($legacy_match_id);
            }
            if ($db_row) {
                $liga_slug = (string) $db_row->liga_slug;
                $sezona_slug = (string) $db_row->sezona_slug;
                $max_kolo = self::extract_round_no((string) $db_row->kolo_slug);
                $highlight_ids[] = intval($db_row->home_club_post_id);
                $highlight_ids[] = intval($db_row->away_club_post_id);
            }
        } elseif (is_singular('klub')) {
            $klub_id = intval(get_the_ID());
            $highlight_ids[] = $klub_id;
            $liga_slug = self::db_get_latest_liga_for_club($klub_id);
        } elseif (is_tax('liga_sezona') || (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $parsed_term = self::parse_legacy_liga_sezona((string) $term->slug, '');
                $liga_slug = sanitize_title((string) ($parsed_term['league_slug'] ?? ''));
                $sezona_slug = sanitize_title((string) ($parsed_term['season_slug'] ?? ''));
            } elseif (is_array($archive_ctx)) {
                $liga_slug = sanitize_title((string) ($archive_ctx['liga_slug'] ?? ''));
                $sezona_slug = sanitize_title((string) ($archive_ctx['sezona_slug'] ?? ''));
            }
        } elseif (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'kolo') {
            $kolo_slug = sanitize_title((string) ($archive_ctx['kolo_slug'] ?? ''));
            if ($kolo_slug !== '') {
                $max_kolo = self::extract_round_no($kolo_slug);
                global $wpdb;
                $table = $wpdb->prefix . 'stkb_matches';
                if (self::table_exists($table)) {
                    $row = $wpdb->get_row($wpdb->prepare("SELECT liga_slug, sezona_slug FROM {$table} WHERE kolo_slug=%s ORDER BY id DESC LIMIT 1", $kolo_slug));
                    if ($row) {
                        $liga_slug = sanitize_title((string) ($row->liga_slug ?? ''));
                        $sezona_slug = sanitize_title((string) ($row->sezona_slug ?? ''));
                    }
                }
            }
        } else {
            return '';
        }

        if ($liga_slug === '') {
            return self::shortcode_title_html('Tabela') . '<p>Nema definisanu ligu/sezonu za ovu stranicu.</p>';
        }

        if ($sezona_slug === '') {
            $parsed_comp = self::parse_legacy_liga_sezona($liga_slug, '');
            $parsed_liga = sanitize_title((string) ($parsed_comp['league_slug'] ?? ''));
            $parsed_sezona = sanitize_title((string) ($parsed_comp['season_slug'] ?? ''));
            if ($parsed_liga !== '' && $parsed_liga !== $liga_slug) {
                $liga_slug = $parsed_liga;
            }
            if ($parsed_sezona !== '') {
                $sezona_slug = $parsed_sezona;
            }
        }

        $rows = self::db_get_matches([
            'limit' => -1,
            'liga_slug' => $liga_slug,
            'sezona_slug' => $sezona_slug,
            'kolo_slug' => '',
            'played' => '',
            'club_id' => 0,
            'player_id' => 0,
        ]);

        if (empty($rows)) {
            return self::shortcode_title_html('Tabela') . '<p>Nema utakmica za ovu ligu/sezonu.</p>';
        }

        $sistem = 'novi';
        $rule = self::get_competition_rule_data($liga_slug, $sezona_slug);
        if (is_array($rule) && !empty($rule['bodovanje_tip'])) {
            $sistem = ((string) $rule['bodovanje_tip'] === '3-0_4-3_2-1') ? 'novi' : 'stari';
        } else {
            $term = get_term_by('slug', $liga_slug, 'liga_sezona');
            if ($term && !is_wp_error($term)) {
                $sm = get_term_meta(intval($term->term_id), 'sistem_bodovanja', true);
                if (!empty($sm)) {
                    $sistem = (string) $sm;
                }
            }
        }

        $stat = [];
        foreach ($rows as $r) {
            $home = intval($r->home_club_post_id);
            $away = intval($r->away_club_post_id);
            foreach ([$home, $away] as $club_id) {
                if ($club_id <= 0) {
                    continue;
                }
                if (!isset($stat[$club_id])) {
                    $stat[$club_id] = [
                        'odigrane' => 0,
                        'pobede' => 0,
                        'porazi' => 0,
                        'bodovi' => 0,
                        'meckol' => 0,
                    ];
                }
            }
        }

        foreach ($rows as $r) {
            if (intval($r->played) !== 1) {
                continue;
            }

            $round = self::extract_round_no((string) $r->kolo_slug);
            if ($max_kolo !== null && $round > $max_kolo) {
                continue;
            }

            $home = intval($r->home_club_post_id);
            $away = intval($r->away_club_post_id);
            if ($home <= 0 || $away <= 0) {
                continue;
            }

            $rd = intval($r->home_score);
            $rg = intval($r->away_score);

            $stat[$home]['odigrane']++;
            $stat[$away]['odigrane']++;
            $stat[$home]['meckol'] += ($rd - $rg);
            $stat[$away]['meckol'] += ($rg - $rd);

            $home_win = ($rd > $rg);
            $away_win = ($rg > $rd);

            if ($home_win) {
                $stat[$home]['pobede']++;
                $stat[$away]['porazi']++;
            } elseif ($away_win) {
                $stat[$away]['pobede']++;
                $stat[$home]['porazi']++;
            }

            if ($sistem === 'novi') {
                if ($home_win) {
                    if ($rd === 4 && in_array($rg, [0, 1, 2], true)) {
                        $stat[$home]['bodovi'] += 3;
                    } elseif ($rd === 4 && $rg === 3) {
                        $stat[$home]['bodovi'] += 2;
                        $stat[$away]['bodovi'] += 1;
                    }
                } elseif ($away_win) {
                    if ($rg === 4 && in_array($rd, [0, 1, 2], true)) {
                        $stat[$away]['bodovi'] += 3;
                    } elseif ($rg === 4 && $rd === 3) {
                        $stat[$away]['bodovi'] += 2;
                        $stat[$home]['bodovi'] += 1;
                    }
                }
            } else {
                if ($home_win) {
                    $stat[$home]['bodovi'] += 2;
                    $stat[$away]['bodovi'] += 1;
                } elseif ($away_win) {
                    $stat[$away]['bodovi'] += 2;
                    $stat[$home]['bodovi'] += 1;
                }
            }
        }

        uasort($stat, function ($a, $b) {
            if ($a['bodovi'] === $b['bodovi']) {
                if ($a['meckol'] === $b['meckol']) {
                    return 0;
                }
                return ($a['meckol'] > $b['meckol']) ? -1 : 1;
            }
            return ($a['bodovi'] > $b['bodovi']) ? -1 : 1;
        });

        $promo_direct = 0;
        $promo_playoff = 0;
        $releg_direct = 0;
        $releg_playoff = 0;
        if (is_array($rule)) {
            $promo_direct = max(0, intval($rule['promocija_broj'] ?? 0));
            $promo_playoff = max(0, intval($rule['promocija_baraz_broj'] ?? 0));
            $releg_direct = max(0, intval($rule['ispadanje_broj'] ?? 0));
            $releg_playoff = max(0, intval($rule['ispadanje_razigravanje_broj'] ?? 0));
        }

        ob_start();
        echo self::shortcode_title_html('Tabela');
        echo '<table class="tabela-lige">';
        echo '<thead><tr>';
        echo '<th>#</th>';
        echo '<th class="tabela-klub-left">Klub</th>';
        echo '<th data-tooltip="Odigrane utakmice">P</th>';
        echo '<th data-tooltip="Pobede">W</th>';
        echo '<th data-tooltip="Porazi">L</th>';
        echo '<th data-tooltip="Bod/Poeni">Pts</th>';
        echo '<th data-tooltip="Meč količnik">+/-</th>';
        echo '</tr></thead><tbody>';

        $rank = 0;
        $team_count = count($stat);
        foreach ($stat as $club_id => $data) {
            $rank++;
            $row_classes = [];
            if ($promo_direct > 0 && $rank <= $promo_direct) {
                $row_classes[] = 'zone-promote-direct';
            } elseif ($promo_playoff > 0 && $rank <= ($promo_direct + $promo_playoff)) {
                $row_classes[] = 'zone-promote-playoff';
            }

            if ($releg_direct > 0 && $rank > ($team_count - $releg_direct)) {
                $row_classes[] = 'zone-relegate-direct';
            } elseif ($releg_playoff > 0 && $rank > ($team_count - $releg_direct - $releg_playoff) && $rank <= ($team_count - $releg_direct)) {
                $row_classes[] = 'zone-relegate-playoff';
            }

            if (in_array(intval($club_id), $highlight_ids, true)) {
                $row_classes[] = 'highlight';
            }
            $class_attr = !empty($row_classes) ? ' class="' . esc_attr(implode(' ', $row_classes)) . '"' : '';
            echo '<tr' . $class_attr . '>';
            echo '<td>' . intval($rank) . '</td>';
            echo '<td class="klub-cell">';
            echo '<a href="' . esc_url(get_permalink($club_id)) . '">';
            echo self::club_logo_html($club_id, 'thumbnail', ['style' => 'width:32px;height:32px;object-fit:contain;border-radius:3px;']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span>' . esc_html(get_the_title($club_id)) . '</span>';
            echo '</a>';
            echo '</td>';
            echo '<td>' . intval($data['odigrane']) . '</td>';
            echo '<td>' . intval($data['pobede']) . '</td>';
            echo '<td>' . intval($data['porazi']) . '</td>';
            echo '<td>' . intval($data['bodovi']) . '</td>';
            $kol = intval($data['meckol']);
            echo '<td>' . ($kol > 0 ? '+' : '') . $kol . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        return ob_get_clean();
    }

    public static function shortcode_games_list($atts = [])
    {
        $ctx = self::current_match_context();
        if (!$ctx || empty($ctx['db_row'])) {
            return '';
        }

        $match_row = $ctx['db_row'];
        $legacy_match_id = intval($ctx['legacy_id']);

        $games = self::db_get_games_for_match_id(intval($match_row->id));
        if (empty($games)) {
            return self::shortcode_title_html('Tok utakmice') . '<p>Nema unetih partija.</p>';
        }

        $format = 'format_a';
        $rule = self::get_competition_rule_data((string) $match_row->liga_slug, (string) $match_row->sezona_slug);
        if (is_array($rule) && !empty($rule['format_partija']) && in_array((string) $rule['format_partija'], ['format_a', 'format_b'], true)) {
            $format = (string) $rule['format_partija'];
        } else {
            // Legacy fallback.
            $sistem = strtolower(trim((string) get_post_meta($legacy_match_id, 'sistem', true)));
            if ($sistem === 'stari') {
                $format = 'format_b';
            }
        }

        $mapa_novi = [
            1 => '1. partija (A vs Y)',
            2 => '2. partija (B vs X)',
            3 => '3. partija (C vs Z)',
            4 => '4. partija (Dubl)',
            5 => '5. partija (A vs X)',
            6 => '6. partija (C vs Y)',
            7 => '7. partija (B vs Z)',
        ];
        $mapa_stari = [
            1 => '1. partija (A vs Y)',
            2 => '2. partija (B vs X)',
            3 => '3. partija (C vs Z)',
            4 => '4. partija (A vs X)',
            5 => '5. partija (C vs Y)',
            6 => '6. partija (B vs Z)',
            7 => '7. partija (Dubl)',
        ];
        $mapa_partija = $format === 'format_b' ? $mapa_stari : $mapa_novi;

        ob_start();
        echo self::shortcode_title_html('Tok utakmice');
        echo '<div class="lp2-lista">';

        foreach ($games as $g) {
            $redni_broj = intval($g->order_no);
            if ($redni_broj <= 0) {
                $redni_broj = 0;
            }

            if (isset($mapa_partija[$redni_broj])) {
                echo '<div class="lp2-naziv-partije">' . esc_html($mapa_partija[$redni_broj]) . '</div>';
            } else {
                echo '<div class="lp2-naziv-partije">Partija ' . intval($redni_broj) . '</div>';
            }

            $home_players = [];
            $away_players = [];
            foreach ([intval($g->home_player_post_id), intval($g->home_player2_post_id)] as $pid) {
                if ($pid > 0) {
                    $home_players[] = $pid;
                }
            }
            foreach ([intval($g->away_player_post_id), intval($g->away_player2_post_id)] as $pid) {
                if ($pid > 0) {
                    $away_players[] = $pid;
                }
            }

            $sets_dom = intval($g->home_sets);
            $sets_gos = intval($g->away_sets);
            $pob_dom = ($sets_dom > $sets_gos);
            $pob_gos = ($sets_gos > $sets_dom);
            $set_rows = self::db_get_sets_for_game_id(intval($g->id));

            echo '<div class="lp2-partija">';

            echo '<div class="lp2-item ' . esc_attr($pob_dom ? 'lp2-win' : 'lp2-lose') . '">';
            echo '<div class="lp2-team">';
            foreach ($home_players as $pid) {
                echo self::render_lp2_player($pid);
            }
            echo '</div>';
            echo '<div class="lp2-sets">';
            foreach ($set_rows as $set_row) {
                $pdom = intval($set_row->home_points);
                $pgos = intval($set_row->away_points);
                if ($pdom === 0 && $pgos === 0) {
                    continue;
                }
                $class = ($pdom > $pgos) ? 'lp2-win' : 'lp2-lose';
                echo '<div class="lp2-set ' . esc_attr($class) . '">' . intval($pdom) . '</div>';
            }
            echo '<div class="lp2-ukupno">' . intval($sets_dom) . '</div>';
            echo '</div>';
            echo '</div>';

            echo '<div class="lp2-item ' . esc_attr($pob_gos ? 'lp2-win' : 'lp2-lose') . '">';
            echo '<div class="lp2-team">';
            foreach ($away_players as $pid) {
                echo self::render_lp2_player($pid);
            }
            echo '</div>';
            echo '<div class="lp2-sets">';
            foreach ($set_rows as $set_row) {
                $pdom = intval($set_row->home_points);
                $pgos = intval($set_row->away_points);
                if ($pdom === 0 && $pgos === 0) {
                    continue;
                }
                $class = ($pgos > $pdom) ? 'lp2-win' : 'lp2-lose';
                echo '<div class="lp2-set ' . esc_attr($class) . '">' . intval($pgos) . '</div>';
            }
            echo '<div class="lp2-ukupno">' . intval($sets_gos) . '</div>';
            echo '</div>';
            echo '</div>';

            echo '</div>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    public static function shortcode_h2h($atts = [])
    {
        $ctx = self::current_match_context();
        if (!$ctx || empty($ctx['db_row'])) {
            return '';
        }

        $cur = $ctx['db_row'];
        $home_id = intval($cur->home_club_post_id);
        $away_id = intval($cur->away_club_post_id);
        if ($home_id <= 0 || $away_id <= 0) {
            return '';
        }

        $rows = self::db_get_h2h_matches(intval($cur->id), $home_id, $away_id);
        if (empty($rows)) {
            return '';
        }

        ob_start();
        echo self::shortcode_title_html('Međusobni dueli');
        foreach ($rows as $row) {
            $rd = intval($row->home_score);
            $rg = intval($row->away_score);
            if ($rd === 0 && $rg === 0) {
                continue;
            }

            $domacin_id = intval($row->home_club_post_id);
            $gost_id = intval($row->away_club_post_id);
            if ($domacin_id <= 0 || $gost_id <= 0) {
                continue;
            }

            $domacin_title = get_the_title($domacin_id);
            $gost_title = get_the_title($gost_id);
            $grb_d = self::club_logo_url($domacin_id, 'thumbnail');
            $grb_g = self::club_logo_url($gost_id, 'thumbnail');

            $pobednik = null;
            if ($rd === 4) {
                $pobednik = 'domacin';
            } elseif ($rg === 4) {
                $pobednik = 'gost';
            }

            $kolo = self::kolo_name_from_slug((string) $row->kolo_slug);
            $datum = self::display_match_date_long($row->match_date);
            $link = self::match_permalink($row);
            ?>
            <a href="<?php echo esc_url($link); ?>" class="h2h-box">
                <div class="h2h-club">
                    <?php if ($grb_d): ?><img src="<?php echo esc_url($grb_d); ?>" alt="<?php echo esc_attr($domacin_title); ?>"><?php endif; ?>
                    <span class="h2h-ime <?php echo esc_attr($pobednik === 'domacin' ? 'pobednik' : 'gubitnik'); ?>"><?php echo esc_html($domacin_title); ?></span>
                    <span class="h2h-rez <?php echo esc_attr($pobednik === 'domacin' ? 'pobednik' : 'gubitnik'); ?>"><?php echo intval($rd); ?></span>
                </div>
                <div class="h2h-club">
                    <?php if ($grb_g): ?><img src="<?php echo esc_url($grb_g); ?>" alt="<?php echo esc_attr($gost_title); ?>"><?php endif; ?>
                    <span class="h2h-ime <?php echo esc_attr($pobednik === 'gost' ? 'pobednik' : 'gubitnik'); ?>"><?php echo esc_html($gost_title); ?></span>
                    <span class="h2h-rez <?php echo esc_attr($pobednik === 'gost' ? 'pobednik' : 'gubitnik'); ?>"><?php echo intval($rg); ?></span>
                </div>
                <div class="h2h-meta">
                    <span><?php echo esc_html($kolo); ?></span>
                    <span><?php echo esc_html($datum); ?></span>
                </div>
            </a>
            <?php
        }

        return ob_get_clean();
    }

    public static function shortcode_mvp($atts = [])
    {
        $ctx = self::current_match_context();
        if (!$ctx || empty($ctx['db_row'])) {
            return '';
        }
        $match_row = $ctx['db_row'];
        $games = self::db_get_games_for_match_id(intval($match_row->id));
        if (empty($games)) {
            return self::shortcode_title_html('Najkorisniji igrač') . '<div class="mvp-box">Nema MVP za ovu utakmicu.</div>';
        }

        $stat = [];
        foreach ($games as $g) {
            if (intval($g->is_doubles) === 1 || intval($g->home_player2_post_id) > 0 || intval($g->away_player2_post_id) > 0) {
                continue;
            }

            $pid_home = intval($g->home_player_post_id);
            $pid_away = intval($g->away_player_post_id);
            if ($pid_home <= 0 || $pid_away <= 0) {
                continue;
            }

            $d_set = intval($g->home_sets);
            $g_set = intval($g->away_sets);
            if ($d_set === 0 && $g_set === 0) {
                continue;
            }

            $sets = self::db_get_sets_for_game_id(intval($g->id));
            $poeni_d = 0;
            $poeni_g = 0;
            foreach ($sets as $set) {
                $poeni_d += intval($set->home_points);
                $poeni_g += intval($set->away_points);
            }

            foreach ([$pid_home, $pid_away] as $pid) {
                if (!isset($stat[$pid])) {
                    $stat[$pid] = ['pobede' => 0, 'setovi' => 0, 'poeni' => 0];
                }
            }

            if ($d_set > $g_set) {
                $stat[$pid_home]['pobede'] += 1;
            } else {
                $stat[$pid_away]['pobede'] += 1;
            }
            $stat[$pid_home]['setovi'] += ($d_set - $g_set);
            $stat[$pid_away]['setovi'] += ($g_set - $d_set);
            $stat[$pid_home]['poeni'] += ($poeni_d - $poeni_g);
            $stat[$pid_away]['poeni'] += ($poeni_g - $poeni_d);
        }

        if (empty($stat)) {
            return self::shortcode_title_html('Najkorisniji igrač') . '<div class="mvp-box">Nema MVP za ovu utakmicu.</div>';
        }

        uasort($stat, function ($a, $b) {
            if ($a['pobede'] !== $b['pobede']) {
                return $b['pobede'] - $a['pobede'];
            }
            if ($a['setovi'] !== $b['setovi']) {
                return $b['setovi'] - $a['setovi'];
            }
            return $b['poeni'] - $a['poeni'];
        });

        $mvp_id = intval(array_key_first($stat));
        if ($mvp_id <= 0) {
            return self::shortcode_title_html('Najkorisniji igrač') . '<div class="mvp-box">Nema MVP za ovu utakmicu.</div>';
        }

        $ime = esc_html((string) get_the_title($mvp_id));
        $slika = get_the_post_thumbnail_url($mvp_id, 'medium');
        if (empty($slika)) {
            $slika = self::player_fallback_image_url();
        }
        $igrac_link = get_permalink($mvp_id);

        $klub_id = intval(get_post_meta($mvp_id, 'klub_igraca', true));
        if ($klub_id <= 0) {
            $klub_id = intval(get_post_meta($mvp_id, 'povezani_klub', true));
        }
        $klub_ime = $klub_id > 0 ? esc_html((string) get_the_title($klub_id)) : '';
        $klub_grb = $klub_id > 0 ? self::club_logo_url($klub_id, 'thumbnail') : '';
        $klub_link = $klub_id > 0 ? get_permalink($klub_id) : '';

        ob_start(); ?>
        <?php echo self::shortcode_title_html('Najkorisniji igrač'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <div class="mvp-box">
            <a href="<?php echo esc_url($igrac_link); ?>">
                <img src="<?php echo esc_url($slika); ?>" alt="<?php echo esc_attr($ime); ?>" class="mvp-slika">
            </a>
            <div class="mvp-info">
                <div class="mvp-ime">
                    <a href="<?php echo esc_url($igrac_link); ?>" style="color:white; text-decoration:none;">
                        <?php echo $ime; ?>
                    </a>
                </div>
                <div class="mvp-klub">
                    <?php if ($klub_link): ?>
                    <a href="<?php echo esc_url($klub_link); ?>" style="display:inline-flex; align-items:center; gap:5px; color:#ccc; text-decoration:none;">
                        <?php if ($klub_grb): ?>
                            <img src="<?php echo esc_url($klub_grb); ?>" alt="<?php echo esc_attr($klub_ime); ?>" class="mvp-grb" style="width:20px; height:20px;">
                        <?php endif; ?>
                        <span><?php echo esc_html($klub_ime); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function shortcode_match_report($atts = [])
    {
        $ctx = self::current_match_context();
        if (!$ctx || empty($ctx['legacy_id'])) {
            return '';
        }
        $legacy_match_id = intval($ctx['legacy_id']);

        $q = new WP_Query([
            'post_type' => 'post',
            'posts_per_page' => 1,
            'ignore_sticky_posts' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [[
                'key' => 'povezana_utakmica',
                'value' => $legacy_match_id,
                'compare' => '=',
            ]],
        ]);
        if (!$q->have_posts()) {
            return '';
        }

        ob_start();
        echo self::shortcode_title_html('Izveštaj utakmice');
        while ($q->have_posts()) {
            $q->the_post();
            $post_id = get_the_ID();
            $title = get_the_title();
            $excerpt = get_the_excerpt();
            $permalink = get_permalink();
            $thumbnail = get_the_post_thumbnail($post_id, 'medium');
            ?>
            <a href="<?php echo esc_url($permalink); ?>" class="izvestaj-utakmice-blok">
                <div class="izvestaj-leva-kolona">
                    <?php echo $thumbnail ?: ''; ?>
                </div>
                <div class="izvestaj-desna-kolona">
                    <h3><?php echo esc_html($title); ?></h3>
                    <p><?php echo esc_html($excerpt); ?></p>
                </div>
            </a>
            <?php
        }
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function shortcode_match_video($atts = [])
    {
        $ctx = self::current_match_context();
        if (!$ctx || empty($ctx['legacy_id'])) {
            return '';
        }
        $legacy_match_id = intval($ctx['legacy_id']);
        $video_url = (string) get_post_meta($legacy_match_id, 'snimak_utakmice', true);
        if ($video_url === '') {
            return '';
        }
        $embed = wp_oembed_get($video_url);
        if (!$embed) {
            return '';
        }
        return self::shortcode_title_html('Snimak utakmice') . '<div class="snimak-utakmice-section"><div class="video-wrapper">' . $embed . '</div></div>';
    }

    public static function shortcode_show_home_club($atts = [])
    {
        $ctx = self::current_match_context();
        if (!$ctx || empty($ctx['db_row'])) {
            return '';
        }
        return self::shortcode_title_html('Domaćin') . self::render_klub_card_html(intval($ctx['db_row']->home_club_post_id));
    }

    public static function shortcode_show_away_club($atts = [])
    {
        $ctx = self::current_match_context();
        if (!$ctx || empty($ctx['db_row'])) {
            return '';
        }
        return self::shortcode_title_html('Gost') . self::render_klub_card_html(intval($ctx['db_row']->away_club_post_id));
    }

    public static function shortcode_show_club_by_name($atts = [])
    {
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
        return self::shortcode_title_html('Klub') . self::render_klub_card_html(intval($post->ID));
    }

    public static function shortcode_club_form($atts = [])
    {
        $atts = shortcode_atts([
            'klub' => '',
            'limit' => 5,
        ], $atts);

        $club_id = 0;
        if (!empty($atts['klub'])) {
            $lookup = sanitize_title((string) $atts['klub']);
            $post = get_page_by_path($lookup, OBJECT, 'klub');
            if (!$post) {
                $post = get_page_by_title((string) $atts['klub'], OBJECT, 'klub');
            }
            if ($post && !is_wp_error($post)) {
                $club_id = intval($post->ID);
            }
        } elseif (is_singular('klub')) {
            $club_id = intval(get_the_ID());
        }

        if ($club_id <= 0) {
            return '';
        }

        $limit = max(1, min(10, intval($atts['limit'])));
        $rows = self::db_get_recent_club_matches($club_id, $limit);
        if (empty($rows)) {
            return self::shortcode_title_html('Forma kluba') . '<div class="stkb-forma-kluba"><p>Nema odigranih utakmica za formu kluba.</p></div>';
        }

        ob_start();
        echo self::shortcode_title_html('Forma kluba');
        echo '<div class="stkb-forma-kluba">';
        echo '<div class="stkb-forma-kluba-list">';
        foreach ($rows as $row) {
            $is_home = intval($row->home_club_post_id) === $club_id;
            $for_score = $is_home ? intval($row->home_score) : intval($row->away_score);
            $opp_score = $is_home ? intval($row->away_score) : intval($row->home_score);
            $won = $for_score > $opp_score;
            $status = $won ? 'pobeda' : 'poraz';
            $class = $won ? 'is-win' : 'is-loss';
            $home_score = intval($row->home_score);
            $away_score = intval($row->away_score);
            $home_team_class = ($home_score > $away_score) ? 'is-winner' : (($home_score < $away_score) ? 'is-loser' : '');
            $away_team_class = ($away_score > $home_score) ? 'is-winner' : (($away_score < $home_score) ? 'is-loser' : '');
            $home_id = intval($row->home_club_post_id);
            $away_id = intval($row->away_club_post_id);
            $home_name = $home_id > 0 ? (string) get_the_title($home_id) : '—';
            $away_name = $away_id > 0 ? (string) get_the_title($away_id) : '—';
            $home_logo = $home_id > 0 ? self::club_logo_html($home_id, 'thumbnail') : '';
            $away_logo = $away_id > 0 ? self::club_logo_html($away_id, 'thumbnail') : '';
            $date = self::display_match_date((string) $row->match_date);
            $link = self::match_permalink($row);

            echo '<a class="stkb-forma-item ' . esc_attr($class) . '" href="' . esc_url($link) . '" title="' . esc_attr($home_name . ' - ' . $away_name . ' • ' . $date . ' • ' . $status) . '">';
            echo '<span class="stkb-forma-main">';
            echo '<span class="stkb-forma-line">';
            echo '<span class="stkb-forma-team stkb-forma-home ' . esc_attr($home_team_class) . '">';
            echo '<span class="stkb-forma-logo">' . ($home_logo ?: '') . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="stkb-forma-name">' . esc_html($home_name) . '</span>';
            echo '</span>';
            echo '<span class="stkb-forma-score ' . esc_attr($home_team_class) . '">' . esc_html((string) intval($row->home_score)) . '</span>';
            echo '</span>';

            echo '<span class="stkb-forma-line">';
            echo '<span class="stkb-forma-team stkb-forma-away ' . esc_attr($away_team_class) . '">';
            echo '<span class="stkb-forma-logo">' . ($away_logo ?: '') . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="stkb-forma-name">' . esc_html($away_name) . '</span>';
            echo '</span>';
            echo '<span class="stkb-forma-score ' . esc_attr($away_team_class) . '">' . esc_html((string) intval($row->away_score)) . '</span>';
            echo '</span>';
            echo '</span>';

            echo '<span class="stkb-forma-side">';
            echo '<span class="stkb-forma-separator"></span>';
            echo '<span class="stkb-forma-status">' . esc_html($status) . '</span>';
            echo '</span>';
            echo '</a>';
        }
        echo '</div>';
        echo '</div>';

        return ob_get_clean();
    }

    public static function shortcode_player_stats($atts = [])
    {
        $atts = shortcode_atts([
            'igrac' => '',
            'filter' => 'false',
        ], $atts);

        $player_id = 0;
        if (!empty($atts['igrac'])) {
            $lookup = sanitize_title((string) $atts['igrac']);
            $post = get_page_by_path($lookup, OBJECT, 'igrac');
            if (!$post) {
                $post = get_page_by_title((string) $atts['igrac'], OBJECT, 'igrac');
            }
            if ($post && !is_wp_error($post)) {
                $player_id = intval($post->ID);
            }
        } elseif (is_singular('igrac')) {
            $player_id = intval(get_the_ID());
        }

        if ($player_id <= 0) {
            return '';
        }

        $enable_filter = in_array(strtolower(trim((string) $atts['filter'])), ['1', 'true', 'yes', 'da', 'on'], true);
        $season_key = 'stkb_player_season_' . $player_id;
        $selected_season = isset($_GET[$season_key]) ? sanitize_title((string) wp_unslash($_GET[$season_key])) : '';

        $season_options = [];
        if ($enable_filter) {
            $season_options = self::db_get_player_season_options($player_id);
            if ($selected_season !== '' && !in_array($selected_season, $season_options, true)) {
                $selected_season = '';
            }
        } else {
            $selected_season = '';
        }

        $stats = self::db_get_player_stats($player_id, $selected_season);
        $mvp_count = self::db_get_player_mvp_count($player_id, $selected_season);
        $played = intval($stats['wins']) + intval($stats['losses']);
        $pct = $played > 0 ? round((intval($stats['wins']) / $played) * 100, 1) : 0;
        $season_label = $selected_season !== '' ? self::season_display_name($selected_season) : 'Ukupno';

        $latest_comp = self::db_get_latest_competition_for_player($player_id);
        $ranking_season = $selected_season;
        if ($ranking_season === '') {
            $ranking_season = is_array($latest_comp) ? sanitize_title((string) ($latest_comp['sezona_slug'] ?? '')) : '';
            if ($ranking_season === '' && !empty($season_options)) {
                $ranking_season = (string) $season_options[0];
            }
        }
        $ranking_liga = '';
        if ($ranking_season !== '') {
            $ranking_liga = self::db_get_latest_liga_for_player_and_season($player_id, $ranking_season);
        }
        if ($ranking_liga === '' && is_array($latest_comp)) {
            $ranking_liga = sanitize_title((string) ($latest_comp['liga_slug'] ?? ''));
        }

        $ranking_data = [];
        $ranking_rows = [];
        $ranking_slice = [];
        $player_rank = 0;
        if ($ranking_liga !== '') {
            $ranking_data = self::db_get_top_players_data($ranking_liga, $ranking_season, null);
            if (!empty($ranking_data)) {
                $rank = 0;
                foreach ($ranking_data as $pid => $info) {
                    $rank++;
                    $ranking_rows[] = [
                        'rank' => $rank,
                        'player_id' => intval($pid),
                        'info' => is_array($info) ? $info : [],
                    ];
                    if (intval($pid) === $player_id) {
                        $player_rank = $rank;
                    }
                }
                if ($player_rank > 0) {
                    $from = max(1, $player_rank - 2);
                    $to = $player_rank + 2;
                    foreach ($ranking_rows as $rr) {
                        $rr_rank = intval($rr['rank']);
                        if ($rr_rank >= $from && $rr_rank <= $to) {
                            $ranking_slice[] = $rr;
                        }
                    }
                }
            }
        }
        $ranking_uid = 'stkb-player-ranking-' . wp_unique_id();

        $uid = 'stkb-player-stats-' . wp_unique_id();

        ob_start();
        echo '<div id="' . esc_attr($uid) . '" class="stkb-stat-igraca">';
        echo self::shortcode_title_html('Statistika igrača');
        if ($enable_filter && !empty($season_options)) {
            echo '<div class="stkb-stat-igraca-filter">';
            echo '<label>Sezona ';
            echo '<select class="stkb-stat-igraca-season">';
            echo '<option value="">Ukupno</option>';
            foreach ($season_options as $opt) {
                echo '<option value="' . esc_attr((string) $opt) . '" ' . selected($selected_season, (string) $opt, false) . '>' . esc_html(self::season_display_name((string) $opt)) . '</option>';
            }
            echo '</select>';
            echo '</label>';
            echo '</div>';
        }

        echo '<div class="stkb-stat-igraca-meta">Period: <strong>' . esc_html($season_label) . '</strong></div>';
        echo '<div class="stkb-stat-igraca-cards">';
        echo '<div class="stkb-stat-card"><span class="k">Pobede</span><strong class="v">' . intval($stats['wins']) . '</strong></div>';
        echo '<div class="stkb-stat-card"><span class="k">Porazi</span><strong class="v">' . intval($stats['losses']) . '</strong></div>';
        echo '<div class="stkb-stat-card"><span class="k">Uspešnost</span><strong class="v">' . esc_html((string) $pct) . '%</strong></div>';
        echo '<div class="stkb-stat-card"><span class="k">Igrač utakmice</span><strong class="v">' . intval($mvp_count) . '</strong></div>';
        echo '</div>';

        echo '<div class="stkb-stat-igraca-rang-wrap">';
        echo '<div class="stkb-stat-igraca-rang-head">';
        echo '<h4 class="stkb-stat-igraca-rang-title">Skraćena rang lista</h4>';
        if ($ranking_season !== '') {
            echo '<div class="stkb-stat-igraca-rang-season">Sezona: ' . esc_html(self::season_display_name($ranking_season)) . '</div>';
        }
        if (!empty($ranking_rows) && $player_rank > 0) {
            echo '<button type="button" class="stkb-stat-igraca-toggle" data-target="' . esc_attr($ranking_uid) . '" data-open-text="Vidi celu rang listu" data-close-text="Sakrij celu rang listu">Vidi celu rang listu</button>';
        }
        echo '</div>';
        if (!empty($ranking_slice) && $player_rank > 0) {
            echo '<div class="stkb-stat-igraca-rang-short" id="' . esc_attr($ranking_uid . '-short') . '">';
            echo '<div class="top-igraci-list stkb-stat-igraca-rang-list">';
            foreach ($ranking_slice as $rr) {
                $pid = intval($rr['player_id']);
                $rank = intval($rr['rank']);
                $info = is_array($rr['info']) ? $rr['info'] : [];
                if ($pid > 0 && get_post_type($pid) === 'igrac') {
                    echo self::render_top_player_card_list($pid, $rank, $info, $pid === $player_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }
            echo '</div>';
            echo '</div>';

            echo '<div id="' . esc_attr($ranking_uid) . '" class="stkb-stat-igraca-rang-full" hidden>';
            echo '<div class="top-igraci-list stkb-stat-igraca-rang-list">';
            foreach ($ranking_rows as $rr) {
                $pid = intval($rr['player_id']);
                $rank = intval($rr['rank']);
                $info = is_array($rr['info']) ? $rr['info'] : [];
                if ($pid > 0 && get_post_type($pid) === 'igrac') {
                    echo self::render_top_player_card_list($pid, $rank, $info, $pid === $player_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="stkb-stat-igraca-rang-empty">Nema dovoljno podataka za prikaz rang liste.</div>';
        }
        echo '</div>';
        echo '</div>';

        if ($enable_filter && !empty($season_options)) {
            ?>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js($uid); ?>');
                if (!root) { return; }
                var sel = root.querySelector('.stkb-stat-igraca-season');
                if (!sel) { return; }
                sel.addEventListener('change', function(){
                    var url = new URL(window.location.href);
                    if (sel.value) {
                        url.searchParams.set('<?php echo esc_js($season_key); ?>', sel.value);
                    } else {
                        url.searchParams.delete('<?php echo esc_js($season_key); ?>');
                    }
                    window.location.href = url.toString();
                });

                var toggle = root.querySelector('.stkb-stat-igraca-toggle');
                if (toggle) {
                    toggle.addEventListener('click', function(){
                        var targetId = toggle.getAttribute('data-target');
                        if (!targetId) { return; }
                        var full = document.getElementById(targetId);
                        var shortList = document.getElementById(targetId + '-short');
                        if (!full) { return; }
                        var willOpen = full.hasAttribute('hidden');
                        if (willOpen) {
                            full.removeAttribute('hidden');
                            if (shortList) { shortList.setAttribute('hidden', 'hidden'); }
                            toggle.textContent = toggle.getAttribute('data-close-text') || 'Sakrij celu rang listu';
                        } else {
                            full.setAttribute('hidden', 'hidden');
                            if (shortList) { shortList.removeAttribute('hidden'); }
                            toggle.textContent = toggle.getAttribute('data-open-text') || 'Vidi celu rang listu';
                        }
                    });
                }
            })();
            </script>
            <?php
        } else {
            ?>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js($uid); ?>');
                if (!root) { return; }
                var toggle = root.querySelector('.stkb-stat-igraca-toggle');
                if (!toggle) { return; }
                toggle.addEventListener('click', function(){
                    var targetId = toggle.getAttribute('data-target');
                    if (!targetId) { return; }
                    var full = document.getElementById(targetId);
                    var shortList = document.getElementById(targetId + '-short');
                    if (!full) { return; }
                    var willOpen = full.hasAttribute('hidden');
                    if (willOpen) {
                        full.removeAttribute('hidden');
                        if (shortList) { shortList.setAttribute('hidden', 'hidden'); }
                        toggle.textContent = toggle.getAttribute('data-close-text') || 'Sakrij celu rang listu';
                    } else {
                        full.setAttribute('hidden', 'hidden');
                        if (shortList) { shortList.removeAttribute('hidden'); }
                        toggle.textContent = toggle.getAttribute('data-open-text') || 'Vidi celu rang listu';
                    }
                });
            })();
            </script>
            <?php
        }

        return ob_get_clean();
    }

    public static function shortcode_team_stats($atts = [])
    {
        $atts = shortcode_atts([
            'klub' => '',
            'filter' => 'false',
        ], $atts);

        $club_id = 0;
        if (!empty($atts['klub'])) {
            $lookup = sanitize_title((string) $atts['klub']);
            $post = get_page_by_path($lookup, OBJECT, 'klub');
            if (!$post) {
                $post = get_page_by_title((string) $atts['klub'], OBJECT, 'klub');
            }
            if ($post && !is_wp_error($post)) {
                $club_id = intval($post->ID);
            }
        } elseif (is_singular('klub')) {
            $club_id = intval(get_the_ID());
        }

        if ($club_id <= 0) {
            return '';
        }

        $enable_filter = in_array(strtolower(trim((string) $atts['filter'])), ['1', 'true', 'yes', 'da', 'on'], true);
        $season_key = 'stkb_team_season_' . $club_id;
        $season_options = [];
        if ($enable_filter) {
            $season_options = self::db_get_club_season_options($club_id);
        }
        $selected_season = isset($_GET[$season_key]) ? sanitize_title((string) wp_unslash($_GET[$season_key])) : '';
        if ($selected_season !== '' && !in_array($selected_season, $season_options, true)) {
            $selected_season = '';
        }

        $stats = self::db_get_club_team_stats($club_id, $selected_season);
        $season_label = $selected_season !== '' ? self::season_display_name($selected_season) : 'Ukupno';

        $current_comp = self::db_get_latest_competition_for_club($club_id);
        $current_season = '';
        if (is_array($current_comp) && !empty($current_comp['sezona_slug'])) {
            $current_season = sanitize_title((string) $current_comp['sezona_slug']);
        }
        if ($current_season === '' && !empty($season_options)) {
            $current_season = (string) $season_options[0];
        }
        $best_player = $current_season !== '' ? self::db_get_club_season_best_player_by_success($club_id, $current_season) : null;

        // Tabela prati izabranu sezonu; ako je "Ukupno", koristi najnoviju sezonu.
        $table_liga_slug = '';
        $table_sezona_slug = '';
        $latest_comp = self::db_get_latest_competition_for_club($club_id);
        $latest_season = '';
        if (is_array($latest_comp)) {
            $latest_season = sanitize_title((string) ($latest_comp['sezona_slug'] ?? ''));
        }
        if ($latest_season === '' && !empty($season_options)) {
            $latest_season = (string) $season_options[0];
        }

        $table_sezona_slug = $selected_season !== '' ? $selected_season : $latest_season;
        if ($table_sezona_slug !== '') {
            $table_liga_slug = self::db_get_latest_liga_for_club_and_season($club_id, $table_sezona_slug);
        } elseif (is_array($latest_comp)) {
            $table_liga_slug = sanitize_title((string) ($latest_comp['liga_slug'] ?? ''));
            $table_sezona_slug = sanitize_title((string) ($latest_comp['sezona_slug'] ?? ''));
        }

        $standings = [];
        $standings_slice = [];
        $club_rank = 0;
        $table_label = '';
        $table_uid = 'stkb-team-table-' . wp_unique_id();
        $table_short_uid = 'stkb-team-table-short-' . wp_unique_id();
        if ($table_liga_slug !== '') {
            $standings = self::db_build_standings_for_competition($table_liga_slug, $table_sezona_slug, null);
            if (!empty($standings)) {
                $club_rank = self::find_club_rank_in_standings($standings, $club_id);
                if ($club_rank > 0) {
                    $standings_slice = self::build_standings_window_around_club($standings, $club_rank, 2);
                }
                $table_label = self::competition_display_name($table_liga_slug, $table_sezona_slug);
            }
        }
        $table_rule = ($table_liga_slug !== '' && $table_sezona_slug !== '') ? self::get_competition_rule_data($table_liga_slug, $table_sezona_slug) : null;
        $table_promo_direct = is_array($table_rule) ? max(0, intval($table_rule['promocija_broj'] ?? 0)) : 0;
        $table_promo_playoff = is_array($table_rule) ? max(0, intval($table_rule['promocija_baraz_broj'] ?? 0)) : 0;
        $table_releg_direct = is_array($table_rule) ? max(0, intval($table_rule['ispadanje_broj'] ?? 0)) : 0;
        $table_releg_playoff = is_array($table_rule) ? max(0, intval($table_rule['ispadanje_razigravanje_broj'] ?? 0)) : 0;
        $table_total_teams = !empty($standings) ? count($standings) : 0;

        $uid = 'stkb-team-stats-' . wp_unique_id();
        $home_pct = self::format_percentage_value(floatval($stats['home_win_pct']));
        $away_pct = self::format_percentage_value(floatval($stats['away_win_pct']));
        $doubles_pct = self::format_percentage_value(floatval($stats['doubles_win_pct']));

        ob_start();
        echo '<section id="' . esc_attr($uid) . '" class="stkb-stat-ekipe">';
        echo self::shortcode_title_html('Statistika ekipe');

        echo '<h3 class="stkb-stat-ekipe-title">Najkorisniji igrač</h3>';
        if (is_array($best_player) && !empty($best_player['player_id'])) {
            $mvp_id = intval($best_player['player_id']);
            $mvp_name = $mvp_id > 0 ? (string) get_the_title($mvp_id) : '';
            $mvp_link = $mvp_id > 0 ? (string) get_permalink($mvp_id) : '';
            $mvp_photo = $mvp_id > 0 ? get_the_post_thumbnail($mvp_id, 'thumbnail', ['class' => 'stkb-stat-ekipe-mvp-photo']) : '';
            if ($mvp_photo === '') {
                $mvp_photo = '<img src="' . esc_url(self::player_fallback_image_url()) . '" alt="Igrač" class="stkb-stat-ekipe-mvp-photo" />';
            }
            $mvp_wins = intval($best_player['wins'] ?? 0);
            $mvp_losses = intval($best_player['losses'] ?? 0);
            $mvp_success = self::format_percentage_value(floatval($best_player['success_pct'] ?? 0));
            $mvp_season_label = self::season_display_name((string) ($best_player['season_slug'] ?? $current_season));

            echo '<div class="stkb-stat-ekipe-mvp">';
            echo '<a class="stkb-stat-ekipe-mvp-link" href="' . esc_url($mvp_link) . '">';
            echo '<span class="stkb-stat-ekipe-mvp-photo-wrap">' . $mvp_photo . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="stkb-stat-ekipe-mvp-main">';
            echo '<span class="stkb-stat-ekipe-mvp-name">' . esc_html($mvp_name) . '</span>';
            echo '<span class="stkb-stat-ekipe-mvp-meta">Sezona: ' . esc_html($mvp_season_label) . ' • Učinak: ' . intval($mvp_wins) . '-' . intval($mvp_losses) . ' • Uspešnost: ' . esc_html($mvp_success) . '%</span>';
            echo '</span>';
            echo '</a>';
            echo '</div>';
        } else {
            echo '<div class="stkb-stat-ekipe-empty">Nema dovoljno podataka za obračun uspešnosti igrača u trenutnoj sezoni.</div>';
        }

        echo '<h3 class="stkb-stat-ekipe-title stkb-stat-ekipe-title-secondary">Statistika ekipe</h3>';
        if ($enable_filter && !empty($season_options)) {
            echo '<div class="stkb-stat-ekipe-filter">';
            echo '<label>Sezona ';
            echo '<select class="stkb-stat-ekipe-season">';
            echo '<option value="">Ukupno</option>';
            foreach ($season_options as $opt) {
                echo '<option value="' . esc_attr((string) $opt) . '" ' . selected($selected_season, (string) $opt, false) . '>' . esc_html(self::season_display_name((string) $opt)) . '</option>';
            }
            echo '</select>';
            echo '</label>';
            echo '</div>';
        }

        echo '<div class="stkb-stat-ekipe-meta">Period: <strong>' . esc_html($season_label) . '</strong></div>';
        echo '<div class="stkb-stat-ekipe-cards">';
        echo '<div class="stkb-stat-ekipe-card"><span class="k">Odigrane</span><strong class="v">' . intval($stats['played']) . '</strong></div>';
        echo '<div class="stkb-stat-ekipe-card"><span class="k">Pobede</span><strong class="v">' . intval($stats['wins']) . '</strong></div>';
        echo '<div class="stkb-stat-ekipe-card"><span class="k">Porazi</span><strong class="v">' . intval($stats['losses']) . '</strong></div>';
        echo '<div class="stkb-stat-ekipe-card"><span class="k">Najduži niz pobeda</span><strong class="v">' . intval($stats['longest_win_streak']) . '</strong></div>';
        echo '<div class="stkb-stat-ekipe-card"><span class="k">Kući pobede</span><strong class="v">' . esc_html($home_pct) . '%</strong></div>';
        echo '<div class="stkb-stat-ekipe-card"><span class="k">U gostima pobede</span><strong class="v">' . esc_html($away_pct) . '%</strong></div>';
        echo '<div class="stkb-stat-ekipe-card"><span class="k">Dubl učinak</span><strong class="v">' . esc_html($doubles_pct) . '%</strong></div>';
        echo '</div>';

        echo '<div class="stkb-stat-ekipe-table-wrap">';
        echo '<div class="stkb-stat-ekipe-table-head">';
        echo '<h4 class="stkb-stat-ekipe-table-title">Skraćena tabela</h4>';
        if ($table_sezona_slug !== '') {
            echo '<div class="stkb-stat-ekipe-table-season">Sezona: ' . esc_html(self::season_display_name($table_sezona_slug)) . '</div>';
        }
        if (!empty($standings) && $club_rank > 0) {
            echo '<button type="button" class="stkb-stat-ekipe-toggle" data-target="' . esc_attr($table_uid) . '" data-open-text="Vidi celu tabelu" data-close-text="Sakrij celu tabelu">Vidi celu tabelu</button>';
        }
        echo '</div>';
        if (!empty($standings_slice) && $club_rank > 0) {
            if ($table_label !== '') {
                echo '<div class="stkb-stat-ekipe-table-meta">' . esc_html($table_label) . '</div>';
            }
            echo '<div id="' . esc_attr($table_short_uid) . '" class="stkb-stat-ekipe-short-wrap">';
            echo '<table class="stkb-stat-ekipe-table">';
            echo '<thead><tr><th>#</th><th>Klub</th><th>P</th><th>W</th><th>L</th><th>Pts</th><th>+/-</th></tr></thead><tbody>';
            foreach ($standings_slice as $row) {
                $is_highlight = intval($row['club_id']) === $club_id;
                $row_rank = intval($row['rank']);
                $row_classes = [];
                if ($table_promo_direct > 0 && $row_rank <= $table_promo_direct) {
                    $row_classes[] = 'zone-promote-direct';
                } elseif ($table_promo_playoff > 0 && $row_rank <= ($table_promo_direct + $table_promo_playoff)) {
                    $row_classes[] = 'zone-promote-playoff';
                }
                if ($table_releg_direct > 0 && $table_total_teams > 0 && $row_rank > ($table_total_teams - $table_releg_direct)) {
                    $row_classes[] = 'zone-relegate-direct';
                } elseif ($table_releg_playoff > 0 && $table_total_teams > 0 && $row_rank > ($table_total_teams - $table_releg_direct - $table_releg_playoff) && $row_rank <= ($table_total_teams - $table_releg_direct)) {
                    $row_classes[] = 'zone-relegate-playoff';
                }
                if ($is_highlight) {
                    $row_classes[] = 'highlight';
                }
                $cls = !empty($row_classes) ? ' class="' . esc_attr(implode(' ', $row_classes)) . '"' : '';
                $club_name = (string) get_the_title(intval($row['club_id']));
                $club_link = get_permalink(intval($row['club_id']));
                echo '<tr' . $cls . '>';
                echo '<td>' . intval($row['rank']) . '</td>';
                echo '<td class="club">';
                echo '<a href="' . esc_url((string) $club_link) . '">';
                echo self::club_logo_html(intval($row['club_id']), 'thumbnail', ['style' => 'width:24px;height:24px;object-fit:contain;border-radius:3px;']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '<span>' . esc_html($club_name) . '</span>';
                echo '</a>';
                echo '</td>';
                echo '<td>' . intval($row['odigrane']) . '</td>';
                echo '<td>' . intval($row['pobede']) . '</td>';
                echo '<td>' . intval($row['porazi']) . '</td>';
                echo '<td>' . intval($row['bodovi']) . '</td>';
                $kol = intval($row['meckol']);
                echo '<td>' . ($kol > 0 ? '+' : '') . $kol . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';

            echo '<div id="' . esc_attr($table_uid) . '" class="stkb-stat-ekipe-full-wrap" hidden>';
            echo '<table class="stkb-stat-ekipe-table stkb-stat-ekipe-table-full">';
            echo '<thead><tr><th>#</th><th>Klub</th><th>P</th><th>W</th><th>L</th><th>Pts</th><th>+/-</th></tr></thead><tbody>';
            foreach ($standings as $row) {
                $is_highlight = intval($row['club_id']) === $club_id;
                $row_rank = intval($row['rank']);
                $row_classes = [];
                if ($table_promo_direct > 0 && $row_rank <= $table_promo_direct) {
                    $row_classes[] = 'zone-promote-direct';
                } elseif ($table_promo_playoff > 0 && $row_rank <= ($table_promo_direct + $table_promo_playoff)) {
                    $row_classes[] = 'zone-promote-playoff';
                }
                if ($table_releg_direct > 0 && $table_total_teams > 0 && $row_rank > ($table_total_teams - $table_releg_direct)) {
                    $row_classes[] = 'zone-relegate-direct';
                } elseif ($table_releg_playoff > 0 && $table_total_teams > 0 && $row_rank > ($table_total_teams - $table_releg_direct - $table_releg_playoff) && $row_rank <= ($table_total_teams - $table_releg_direct)) {
                    $row_classes[] = 'zone-relegate-playoff';
                }
                if ($is_highlight) {
                    $row_classes[] = 'highlight';
                }
                $cls = !empty($row_classes) ? ' class="' . esc_attr(implode(' ', $row_classes)) . '"' : '';
                $club_name = (string) get_the_title(intval($row['club_id']));
                $club_link = get_permalink(intval($row['club_id']));
                echo '<tr' . $cls . '>';
                echo '<td>' . intval($row['rank']) . '</td>';
                echo '<td class="club">';
                echo '<a href="' . esc_url((string) $club_link) . '">';
                echo self::club_logo_html(intval($row['club_id']), 'thumbnail', ['style' => 'width:24px;height:24px;object-fit:contain;border-radius:3px;']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '<span>' . esc_html($club_name) . '</span>';
                echo '</a>';
                echo '</td>';
                echo '<td>' . intval($row['odigrane']) . '</td>';
                echo '<td>' . intval($row['pobede']) . '</td>';
                echo '<td>' . intval($row['porazi']) . '</td>';
                echo '<td>' . intval($row['bodovi']) . '</td>';
                $kol = intval($row['meckol']);
                echo '<td>' . ($kol > 0 ? '+' : '') . $kol . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        } else {
            echo '<div class="stkb-stat-ekipe-empty">Nema dovoljno podataka za prikaz skraćene tabele.</div>';
        }
        echo '</div>';

        echo '</section>';

        if ($enable_filter && !empty($season_options)) {
            ?>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js($uid); ?>');
                if (!root) { return; }
                var sel = root.querySelector('.stkb-stat-ekipe-season');
                if (!sel) { return; }
                sel.addEventListener('change', function(){
                    var url = new URL(window.location.href);
                    if (sel.value) {
                        url.searchParams.set('<?php echo esc_js($season_key); ?>', sel.value);
                    } else {
                        url.searchParams.delete('<?php echo esc_js($season_key); ?>');
                    }
                    window.location.href = url.toString();
                });

                var toggle = root.querySelector('.stkb-stat-ekipe-toggle');
                if (toggle) {
                    toggle.addEventListener('click', function(){
                        var targetId = toggle.getAttribute('data-target');
                        if (!targetId) { return; }
                        var full = document.getElementById(targetId);
                        var shortTable = document.getElementById('<?php echo esc_js($table_short_uid); ?>');
                        if (!full) { return; }
                        var willOpen = full.hasAttribute('hidden');
                        if (willOpen) {
                            full.removeAttribute('hidden');
                            if (shortTable) { shortTable.setAttribute('hidden', 'hidden'); }
                            toggle.textContent = toggle.getAttribute('data-close-text') || 'Sakrij celu tabelu';
                        } else {
                            full.setAttribute('hidden', 'hidden');
                            if (shortTable) { shortTable.removeAttribute('hidden'); }
                            toggle.textContent = toggle.getAttribute('data-open-text') || 'Vidi celu tabelu';
                        }
                    });
                }
            })();
            </script>
            <?php
        } else {
            ?>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js($uid); ?>');
                if (!root) { return; }
                var toggle = root.querySelector('.stkb-stat-ekipe-toggle');
                if (!toggle) { return; }
                toggle.addEventListener('click', function(){
                    var targetId = toggle.getAttribute('data-target');
                    if (!targetId) { return; }
                    var full = document.getElementById(targetId);
                    var shortTable = document.getElementById('<?php echo esc_js($table_short_uid); ?>');
                    if (!full) { return; }
                    var willOpen = full.hasAttribute('hidden');
                    if (willOpen) {
                        full.removeAttribute('hidden');
                        if (shortTable) { shortTable.setAttribute('hidden', 'hidden'); }
                        toggle.textContent = toggle.getAttribute('data-close-text') || 'Sakrij celu tabelu';
                    } else {
                        full.setAttribute('hidden', 'hidden');
                        if (shortTable) { shortTable.removeAttribute('hidden'); }
                        toggle.textContent = toggle.getAttribute('data-open-text') || 'Vidi celu tabelu';
                    }
                });
            })();
            </script>
            <?php
        }

        return ob_get_clean();
    }

    public static function shortcode_player_transfers($atts = [])
    {
        $atts = shortcode_atts([
            'igrac' => '',
        ], $atts);

        $player_id = 0;
        if (!empty($atts['igrac'])) {
            $lookup = sanitize_title((string) $atts['igrac']);
            $post = get_page_by_path($lookup, OBJECT, 'igrac');
            if (!$post) {
                $post = get_page_by_title((string) $atts['igrac'], OBJECT, 'igrac');
            }
            if ($post && !is_wp_error($post)) {
                $player_id = intval($post->ID);
            }
        } elseif (is_singular('igrac')) {
            $player_id = intval(get_the_ID());
        }

        if ($player_id <= 0) {
            return '';
        }

        $history = self::db_get_player_season_club_history($player_id);
        if (empty($history)) {
            return '<div class="stkb-transferi"><p>Nema podataka o transferima za ovog igrača.</p></div>';
        }

        $stints = self::build_player_stints($history);
        if (empty($stints)) {
            return '<div class="stkb-transferi"><p>Nema podataka o transferima za ovog igrača.</p></div>';
        }

        $transfers = [];
        for ($i = 1; $i < count($stints); $i++) {
            $prev = $stints[$i - 1];
            $curr = $stints[$i];
            $transfers[] = [
                'season_slug' => (string) ($curr['from_season'] ?? ''),
                'from_club_id' => intval($prev['club_id'] ?? 0),
                'to_club_id' => intval($curr['club_id'] ?? 0),
            ];
        }
        $stints_desc = array_reverse($stints);
        $transfers_desc = array_reverse($transfers);

        ob_start();
        echo self::shortcode_title_html('Transferi');
        echo '<section class="stkb-transferi">';

        echo '<div class="stkb-transferi-block">';
        echo '<h4>Istorija klubova</h4>';
        echo '<table class="stkb-transferi-table"><thead><tr><th>Period</th><th>Klub</th></tr></thead><tbody>';
        foreach ($stints_desc as $s) {
            $from_slug = (string) ($s['from_season'] ?? '');
            $to_slug = (string) ($s['to_season'] ?? '');
            $period = self::season_display_name($from_slug);
            if ($to_slug !== '' && $to_slug !== $from_slug) {
                $period .= ' - ' . self::season_display_name($to_slug);
            }
            $club_id = intval($s['club_id'] ?? 0);
            $club_name = $club_id > 0 ? (string) get_the_title($club_id) : '—';
            $club_link = $club_id > 0 ? (string) get_permalink($club_id) : '';
            $club_logo = $club_id > 0 ? self::club_logo_html($club_id, 'thumbnail', ['class' => 'stkb-transferi-club-grb']) : '';
            echo '<tr><td>' . esc_html($period) . '</td><td>';
            echo '<span class="stkb-transferi-club">';
            if ($club_logo) {
                echo $club_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            if ($club_link !== '') {
                echo '<a href="' . esc_url($club_link) . '">' . esc_html($club_name) . '</a>';
            } else {
                echo esc_html($club_name);
            }
            echo '</span>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        if (!empty($transfers_desc)) {
            echo '<div class="stkb-transferi-block">';
            echo '<h4>Promene kluba</h4>';
            echo '<table class="stkb-transferi-table"><thead><tr><th>Sezona</th><th>Transfer</th></tr></thead><tbody>';
            foreach ($transfers_desc as $t) {
                $season_label = self::season_display_name((string) $t['season_slug']);
                $from_id = intval($t['from_club_id']);
                $to_id = intval($t['to_club_id']);
                $from_name = $from_id > 0 ? (string) get_the_title($from_id) : '—';
                $to_name = $to_id > 0 ? (string) get_the_title($to_id) : '—';
                $from_logo = $from_id > 0 ? self::club_logo_html($from_id, 'thumbnail', ['class' => 'stkb-transferi-club-grb']) : '';
                $to_logo = $to_id > 0 ? self::club_logo_html($to_id, 'thumbnail', ['class' => 'stkb-transferi-club-grb']) : '';
                echo '<tr><td>' . esc_html($season_label) . '</td><td>';
                echo '<span class="stkb-transferi-move">';
                echo '<span class="stkb-transferi-club">';
                if ($from_logo) {
                    echo $from_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                echo '<span>' . esc_html($from_name) . '</span>';
                echo '</span>';
                echo '<span class="stkb-transferi-arrow">-></span>';
                echo '<span class="stkb-transferi-club">';
                if ($to_logo) {
                    echo $to_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                echo '<span>' . esc_html($to_name) . '</span>';
                echo '</span>';
                echo '</span>';
                echo '</td></tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        }

        echo '</section>';
        return ob_get_clean();
    }

    public static function shortcode_club_info($atts = [])
    {
        $atts = shortcode_atts([
            'klub' => '',
        ], $atts);

        $club_id = 0;
        if (!empty($atts['klub'])) {
            $lookup = sanitize_title((string) $atts['klub']);
            $post = get_page_by_path($lookup, OBJECT, 'klub');
            if (!$post) {
                $post = get_page_by_title((string) $atts['klub'], OBJECT, 'klub');
            }
            if ($post && !is_wp_error($post)) {
                $club_id = intval($post->ID);
            }
        } elseif (is_singular('klub')) {
            $club_id = intval(get_the_ID());
        }

        if ($club_id <= 0) {
            return '';
        }

        $club_name = (string) get_the_title($club_id);
        $club_logo = self::club_logo_html($club_id, 'medium', ['class' => 'stkb-info-kluba-grb']);
        $club_link = (string) get_permalink($club_id);
        $club_display_name = 'STK ' . $club_name;

        $fields = [
            'grad' => 'Grad',
            'opstina' => 'Opština',
            'kontakt' => 'Kontakt',
            'email' => 'Email',
            'zastupnik_kluba' => 'Zastupnik kluba',
            'website_kluba' => 'Website kluba',
            'boja_dresa' => 'Boja dresa',
            'loptice' => 'Loptice',
            'adresa_kluba' => 'Adresa kluba',
            'adresa_sale' => 'Adresa sale',
            'termin_igranja' => 'Termin igranja',
        ];

        $rows = [];
        $club_meta_subtitle = '';

        $club_comp = self::db_get_latest_competition_for_club($club_id);
        if (is_array($club_comp)) {
            $liga_slug = sanitize_title((string) ($club_comp['liga_slug'] ?? ''));
            $sezona_slug = sanitize_title((string) ($club_comp['sezona_slug'] ?? ''));
            if ($liga_slug !== '' && $sezona_slug === '') {
                $parsed = self::parse_legacy_liga_sezona($liga_slug, '');
                $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? $liga_slug));
                $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? ''));
            }
            if ($liga_slug !== '') {
                $league_label = self::slug_to_title($liga_slug);
                if ($league_label === '') {
                    $league_label = $liga_slug;
                }
                $league_label = ucwords(strtolower((string) $league_label));
                $subtitle_parts = [trim((string) $league_label)];

                if ($sezona_slug !== '') {
                    $rule = self::get_competition_rule_data($liga_slug, $sezona_slug);
                    if (is_array($rule) && !empty($rule['savez'])) {
                        $savez = self::competition_federation_data((string) $rule['savez']);
                        if (is_array($savez) && !empty($savez['label'])) {
                            $subtitle_parts[] = (string) $savez['label'];
                        }
                    }
                }

                $subtitle_parts = array_values(array_filter(array_map('trim', $subtitle_parts)));
                if (!empty($subtitle_parts)) {
                    $club_meta_subtitle = implode(', ', $subtitle_parts);
                }
            }
        }

        foreach ($fields as $key => $label) {
            $value = trim((string) get_post_meta($club_id, $key, true));
            if ($value === '') {
                continue;
            }
            $prefix_icon = '';
            $suffix_icon = '';
            if ($key === 'website_kluba') {
                $href = esc_url($value);
                if ($href !== '') {
                    $display = preg_replace('#^https?://#i', '', $value);
                    $value = '<a href="' . $href . '" target="_blank" rel="noopener">' . esc_html((string) $display) . '</a>';
                    $suffix_icon = self::info_link_icon_html('external-icon', '↗', 'after');
                } else {
                    $value = esc_html($value);
                }
            } elseif ($key === 'kontakt') {
                $phone_href = self::normalize_phone_for_href($value);
                $phone_display = self::format_phone_for_display($value);
                if ($phone_href !== '') {
                    $value = '<a href="tel:' . esc_attr($phone_href) . '">' . esc_html($phone_display) . '</a>';
                    $suffix_icon = self::info_link_icon_html('external-icon', '↗', 'after');
                } else {
                    $value = esc_html($phone_display);
                }
            } elseif ($key === 'email') {
                $email = sanitize_email($value);
                if ($email !== '') {
                    $value = '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                    $suffix_icon = self::info_link_icon_html('external-icon', '↗', 'after');
                } else {
                    $value = esc_html($value);
                }
            } else {
                $value = esc_html($value);
            }
            if ($prefix_icon !== '' || $suffix_icon !== '') {
                $value = '<span class="stkb-info-link-wrap">' . $prefix_icon . $value . $suffix_icon . '</span>';
            }
            $rows[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        ob_start();
        ?>
        <?php echo self::shortcode_title_html('Info kluba'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <section class="stkb-info-kluba">
            <div class="stkb-info-kluba-head">
                <a href="<?php echo esc_url($club_link); ?>" class="stkb-info-kluba-brand">
                    <span class="stkb-info-kluba-grb-wrap">
                        <?php echo $club_logo ?: ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="stkb-info-kluba-head-text">
                        <span class="stkb-info-kluba-ime"><?php echo esc_html($club_display_name); ?></span>
                        <?php if ($club_meta_subtitle !== ''): ?>
                            <span class="stkb-info-kluba-podnaslov"><?php echo esc_html($club_meta_subtitle); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            </div>
            <?php if (!empty($rows)): ?>
                <dl class="stkb-info-kluba-lista">
                    <?php foreach ($rows as $row): ?>
                        <div class="stkb-info-kluba-row">
                            <dt><?php echo esc_html((string) $row['label']); ?></dt>
                            <dd><?php echo $row['value']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function info_link_icon_html($icon_file_name, $fallback, $modifier = 'before')
    {
        $icon_file_name = sanitize_file_name((string) $icon_file_name);
        if ($icon_file_name !== '' && substr($icon_file_name, -4) !== '.svg') {
            $icon_file_name .= '.svg';
        }
        $modifier = sanitize_html_class((string) $modifier);
        $classes = 'stkb-info-link-icon stkb-info-link-icon--' . ($modifier !== '' ? $modifier : 'before');
        $fallback = (string) $fallback;

        $rel_path = 'assets/icons/' . $icon_file_name;
        $full_path = self::$plugin_dir . $rel_path;
        if (is_readable($full_path)) {
            $svg = file_get_contents($full_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            if (is_string($svg) && trim($svg) !== '') {
                $svg = preg_replace('/<\?xml.*?\?>/i', '', $svg);
                $svg = preg_replace('/<!DOCTYPE.*?>/i', '', $svg);
                if (is_string($svg) && trim($svg) !== '') {
                    return '<span class="' . esc_attr($classes) . '" aria-hidden="true"><span class="stkb-info-link-icon-svg">' . $svg . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }
        }

        return '<span class="' . esc_attr($classes) . '" aria-hidden="true">' . esc_html($fallback) . '</span>';
    }

    public static function shortcode_player_info($atts = [])
    {
        $atts = shortcode_atts([
            'igrac' => '',
        ], $atts);

        $player_id = 0;
        if (!empty($atts['igrac'])) {
            $lookup = sanitize_title((string) $atts['igrac']);
            $post = get_page_by_path($lookup, OBJECT, 'igrac');
            if (!$post) {
                $post = get_page_by_title((string) $atts['igrac'], OBJECT, 'igrac');
            }
            if ($post && !is_wp_error($post)) {
                $player_id = intval($post->ID);
            }
        } elseif (is_singular('igrac')) {
            $player_id = intval(get_the_ID());
        }

        if ($player_id <= 0) {
            return '';
        }

        $player_name = (string) get_the_title($player_id);
        $player_photo = get_the_post_thumbnail($player_id, 'medium', ['class' => 'stkb-info-igraca-slika']);
        if (!$player_photo) {
            $player_photo = '<img src="' . esc_url(self::player_fallback_image_url()) . '" alt="' . esc_attr($player_name) . '" class="stkb-info-igraca-slika" />';
        }
        $player_link = (string) get_permalink($player_id);

        $club_id = self::get_player_club_id($player_id);
        $club_name = $club_id > 0 ? (string) get_the_title($club_id) : '';
        $club_link = $club_id > 0 ? (string) get_permalink($club_id) : '';
        $club_logo = $club_id > 0 ? self::club_logo_html($club_id, 'thumbnail', ['class' => 'stkb-info-igraca-klub-grb']) : '';

        $rows = [];

        $dob = trim((string) get_post_meta($player_id, 'datum_rodjenja', true));
        if ($dob !== '') {
            $ts = strtotime($dob);
            if ($ts !== false) {
                $dob = date_i18n('d.m.Y.', $ts);
            }
            $rows[] = [
                'label' => 'Datum rođenja',
                'value' => esc_html($dob),
            ];
        }

        $pob = trim((string) get_post_meta($player_id, 'mesto_rodjenja', true));
        if ($pob !== '') {
            $rows[] = [
                'label' => 'Mesto rođenja',
                'value' => esc_html($pob),
            ];
        }

        $country_code = strtoupper(sanitize_key((string) get_post_meta($player_id, 'drzavljanstvo', true)));
        if ($country_code !== '') {
            $country_name = OpenTT_Unified_Core::country_label_by_code($country_code);
            if ($country_name !== '') {
                $flag = OpenTT_Unified_Core::country_flag_emoji($country_code);
                $country_value = '<span class="stkb-info-igraca-nacionalnost">';
                if ($flag !== '') {
                    $country_value .= '<span class="flag" aria-hidden="true">' . esc_html($flag) . '</span> ';
                }
                $country_value .= '<span class="name">' . esc_html($country_name) . '</span>';
                $country_value .= '</span>';
                $rows[] = [
                    'label' => 'Državljanstvo',
                    'value' => $country_value,
                ];
            }
        }

        $bio = trim((string) get_post_field('post_content', $player_id));
        if ($bio !== '') {
            $rows[] = [
                'label' => 'Biografija',
                'value' => wp_kses_post(wpautop($bio)),
            ];
        }

        ob_start();
        ?>
        <?php echo self::shortcode_title_html('Info igrača'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <section class="stkb-info-igraca">
            <div class="stkb-info-igraca-head">
                <div class="stkb-info-igraca-brand">
                    <a href="<?php echo esc_url($player_link); ?>" class="stkb-info-igraca-foto-link">
                        <span class="stkb-info-igraca-slika-wrap">
                            <?php echo $player_photo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </span>
                    </a>
                    <span class="stkb-info-igraca-head-text">
                        <a href="<?php echo esc_url($player_link); ?>" class="stkb-info-igraca-ime"><?php echo esc_html($player_name); ?></a>
                        <?php if ($club_name !== ''): ?>
                            <span class="stkb-info-igraca-klub">
                                <?php if ($club_link !== ''): ?>
                                    <a class="stkb-info-igraca-klub-link" href="<?php echo esc_url($club_link); ?>">
                                        <?php if ($club_logo): ?>
                                            <span class="stkb-info-igraca-klub-logo"><?php echo $club_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                        <?php endif; ?>
                                        <span class="stkb-info-igraca-klub-tekst"><?php echo esc_html($club_name); ?></span>
                                    </a>
                                <?php else: ?>
                                    <?php if ($club_logo): ?>
                                        <span class="stkb-info-igraca-klub-logo"><?php echo $club_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <?php endif; ?>
                                    <span class="stkb-info-igraca-klub-tekst"><?php echo esc_html($club_name); ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php if (!empty($rows)): ?>
                <dl class="stkb-info-igraca-lista">
                    <?php foreach ($rows as $row): ?>
                        <div class="stkb-info-igraca-row">
                            <dt><?php echo esc_html((string) $row['label']); ?></dt>
                            <dd><?php echo $row['value']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function normalize_phone_for_href($raw_phone)
    {
        return OpenTT_Unified_Readonly_Helpers::normalize_phone_for_href($raw_phone);
    }

    private static function format_phone_for_display($raw_phone)
    {
        return OpenTT_Unified_Readonly_Helpers::format_phone_for_display($raw_phone);
    }

    public static function shortcode_show_match_teams($atts = [])
    {
        $ctx = self::current_match_context();
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

        $competition_name = self::competition_display_name($liga_slug, $sezona_slug);
        $competition_url = self::competition_archive_url($liga_slug, $sezona_slug);

        $kolo_name = self::kolo_name_from_slug($kolo_slug);
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
        $home_logo = self::club_logo_html($home_id, 'thumbnail');
        $away_logo = self::club_logo_html($away_id, 'thumbnail');
        $home_score = intval($row->home_score);
        $away_score = intval($row->away_score);
        $home_state = '';
        $away_state = '';
        if ($home_score > $away_score) {
            $home_state = 'pobednik';
            $away_state = 'gubitnik';
        } elseif ($away_score > $home_score) {
            $home_state = 'gubitnik';
            $away_state = 'pobednik';
        }
        $match_date = self::display_match_date((string) $row->match_date);
        $match_venue = self::match_venue_label($row);

        ob_start();
        ?>
        <?php echo self::shortcode_title_html('Prikaz ekipa'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <div class="stkb-ekipe">
            <div class="stkb-ekipe-meta">
                <?php if ($competition_url !== ''): ?>
                    <a href="<?php echo esc_url($competition_url); ?>" class="stkb-ekipe-meta-link"><?php echo esc_html($competition_name); ?></a>
                <?php else: ?>
                    <span class="stkb-ekipe-meta-text"><?php echo esc_html($competition_name); ?></span>
                <?php endif; ?>
                <?php if ($kolo_name !== ''): ?>
                    <span class="stkb-ekipe-meta-sep">•</span>
                    <?php if ($kolo_url !== ''): ?>
                        <a href="<?php echo esc_url($kolo_url); ?>" class="stkb-ekipe-meta-link"><?php echo esc_html($kolo_name); ?></a>
                    <?php else: ?>
                        <span class="stkb-ekipe-meta-text"><?php echo esc_html($kolo_name); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="stkb-ekipe-row">
                <a href="<?php echo esc_url($home_url); ?>" class="stkb-ekipe-team stkb-ekipe-home <?php echo esc_attr($home_state); ?>">
                    <span class="stkb-ekipe-logo-wrap">
                        <?php echo $home_logo ? $home_logo : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="stkb-ekipe-name"><?php echo esc_html($home_name); ?></span>
                </a>

                <div class="stkb-ekipe-score">
                    <span class="<?php echo esc_attr($home_state); ?>"><?php echo esc_html((string) $home_score); ?></span>
                    <span class="stkb-ekipe-score-sep">:</span>
                    <span class="<?php echo esc_attr($away_state); ?>"><?php echo esc_html((string) $away_score); ?></span>
                </div>

                <a href="<?php echo esc_url($away_url); ?>" class="stkb-ekipe-team stkb-ekipe-away <?php echo esc_attr($away_state); ?>">
                    <span class="stkb-ekipe-logo-wrap">
                        <?php echo $away_logo ? $away_logo : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="stkb-ekipe-name"><?php echo esc_html($away_name); ?></span>
                </a>
            </div>

            <?php if ($match_venue !== '' || $match_date !== ''): ?>
                <div class="stkb-ekipe-footer">
                    <?php if ($match_venue !== ''): ?>
                        <span class="stkb-ekipe-footer-item stkb-ekipe-footer-venue"><?php echo esc_html($match_venue); ?></span>
                    <?php endif; ?>
                    <?php if ($match_venue !== '' && $match_date !== ''): ?>
                        <span class="stkb-ekipe-footer-sep">•</span>
                    <?php endif; ?>
                    <?php if ($match_date !== ''): ?>
                        <span class="stkb-ekipe-footer-item stkb-ekipe-footer-date"><?php echo esc_html($match_date); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function shortcode_competition_info($atts = [])
    {
        $atts = shortcode_atts([
            'liga' => '',
            'sezona' => '',
            'show_logo' => '1',
        ], $atts);

        $liga_slug = '';
        $sezona_slug = '';
        $archive_ctx = self::current_archive_context();
        $liga_param = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona_param = sanitize_title((string) ($atts['sezona'] ?? ''));

        if ($liga_param !== '') {
            $parsed = self::parse_legacy_liga_sezona($liga_param, $sezona_param);
            $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? $liga_param));
            $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? $sezona_param));
            if ($sezona_param !== '') {
                $sezona_slug = $sezona_param;
            }
        } elseif (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona') {
            $raw_liga = sanitize_title((string) ($archive_ctx['liga_slug'] ?? ''));
            $raw_sezona = sanitize_title((string) ($archive_ctx['sezona_slug'] ?? ''));
            $parsed_ctx = self::parse_legacy_liga_sezona($raw_liga, $raw_sezona);
            $liga_slug = sanitize_title((string) ($parsed_ctx['league_slug'] ?? $raw_liga));
            $sezona_slug = sanitize_title((string) ($parsed_ctx['season_slug'] ?? $raw_sezona));
            if ($liga_slug === '' && $sezona_slug !== '') {
                global $wpdb;
                $table = $wpdb->prefix . 'stkb_matches';
                if (self::table_exists($table)) {
                    $liga_guess = $wpdb->get_var($wpdb->prepare("SELECT liga_slug FROM {$table} WHERE sezona_slug=%s AND liga_slug<>'' ORDER BY id DESC LIMIT 1", $sezona_slug));
                    if (is_string($liga_guess) && $liga_guess !== '') {
                        $liga_slug = sanitize_title($liga_guess);
                    }
                }
            }
        } elseif (is_tax('liga_sezona')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $parsed = self::parse_legacy_liga_sezona((string) $term->slug, '');
                $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? ''));
                $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? ''));
            }
        } else {
            $ctx = self::current_match_context();
            if ($ctx && !empty($ctx['db_row'])) {
                $row = $ctx['db_row'];
                $liga_slug = sanitize_title((string) $row->liga_slug);
                $sezona_slug = sanitize_title((string) $row->sezona_slug);
            }
        }

        if ($liga_slug === '') {
            return '';
        }

        $liga_name = self::slug_to_title($liga_slug);
        if ($liga_name === '') {
            $liga_name = $liga_slug;
        }
        $sezona_name = self::season_display_name($sezona_slug);

        $rule = null;
        if ($sezona_slug !== '') {
            $rule = self::get_competition_rule_data($liga_slug, $sezona_slug);
        }

        $savez_label = '';
        $savez_url = '';
        $thumb_html = '';

        if (is_array($rule)) {
            $savez = self::competition_federation_data((string) ($rule['savez'] ?? ''));
            if (is_array($savez)) {
                $savez_label = (string) ($savez['label'] ?? '');
                $savez_url = (string) ($savez['url'] ?? '');
            }

            $rule_id = intval($rule['id'] ?? 0);
            if ($rule_id > 0 && (string) $atts['show_logo'] !== '0' && has_post_thumbnail($rule_id)) {
                $thumb_html = get_the_post_thumbnail($rule_id, 'medium', ['class' => 'stkb-takmicenje-info-logo-img']);
            }
        }

        ob_start();
        ?>
        <?php echo self::shortcode_title_html('Info takmičenja'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <div class="stkb-takmicenje-info">
            <?php if ($thumb_html !== ''): ?>
                <div class="stkb-takmicenje-info-logo"><?php echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
            <div class="stkb-takmicenje-info-body">
                <h2 class="stkb-takmicenje-info-title"><?php echo esc_html($liga_name); ?></h2>
                <?php if ($sezona_name !== ''): ?>
                    <div class="stkb-takmicenje-info-meta"><strong>Sezona:</strong> <?php echo esc_html($sezona_name); ?></div>
                <?php endif; ?>
                <?php if ($savez_label !== ''): ?>
                    <div class="stkb-takmicenje-info-meta">
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

    public static function shortcode_competitions_grid($atts = [])
    {
        $atts = shortcode_atts([
            'limit' => '0',
            'filter' => '',
        ], $atts);

        $limit = max(0, intval($atts['limit']));
        $filter_mode = strtolower(trim((string) $atts['filter']));
        $enable_filter = in_array($filter_mode, ['1', 'true', 'yes', 'da', 'on'], true);
        $rows = get_posts([
            'post_type' => 'pravilo_takmicenja',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];

        if (empty($rows)) {
            return self::shortcode_title_html('Takmičenja') . '<p>Nema unetih takmičenja.</p>';
        }

        $groups = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
            5 => [],
        ];
        $season_options = [];
        $selected_season = '';
        if ($enable_filter) {
            $selected_season = isset($_GET['stkb_takm_sezona']) ? sanitize_title((string) wp_unslash($_GET['stkb_takm_sezona'])) : '';
            $season_pool = [];
            foreach ($rows as $r) {
                $s = sanitize_title((string) get_post_meta($r->ID, 'opentt_competition_season_slug', true));
                if ($s !== '') {
                    $season_pool[$s] = $s;
                }
            }
            $season_pool = array_values($season_pool);
            usort($season_pool, function ($a, $b) {
                $ak = self::season_sort_key((string) $a);
                $bk = self::season_sort_key((string) $b);
                if ($ak === $bk) {
                    return strnatcasecmp((string) $b, (string) $a);
                }
                return $bk <=> $ak;
            });
            if ($selected_season !== '' && !in_array($selected_season, $season_pool, true)) {
                $selected_season = '';
            }
            if ($selected_season === '' && !empty($season_pool)) {
                $selected_season = (string) $season_pool[0];
            }
        }

        foreach ($rows as $r) {
            $liga_slug = sanitize_title((string) get_post_meta($r->ID, 'opentt_competition_league_slug', true));
            $sezona_slug = sanitize_title((string) get_post_meta($r->ID, 'opentt_competition_season_slug', true));
            if ($liga_slug === '') {
                continue;
            }
            if ($sezona_slug !== '') {
                $season_options[$sezona_slug] = self::season_display_name($sezona_slug);
            }
            if ($enable_filter && $selected_season !== '' && $selected_season !== $sezona_slug) {
                continue;
            }
            $rank = (int) get_post_meta($r->ID, 'opentt_competition_rank', true);
            if ($rank < 1 || $rank > 5) {
                $rank = 3;
            }

            $archive_url = self::competition_archive_url($liga_slug, $sezona_slug);
            $league_name = self::slug_to_title($liga_slug);
            if ($league_name === '') {
                $league_name = $liga_slug;
            }

            $club_ids = self::db_get_competition_club_ids($liga_slug, $sezona_slug);
            $groups[$rank][] = [
                'rule_id' => (int) $r->ID,
                'league_name' => $league_name,
                'season_name' => self::season_display_name($sezona_slug),
                'url' => $archive_url,
                'club_ids' => $club_ids,
            ];
        }

        $rank_titles = [
            1 => 'Prvi rang takmičenja',
            2 => 'Drugi rang takmičenja',
            3 => 'Treći rang takmičenja',
            4 => 'Četvrti rang takmičenja',
            5 => 'Peti rang takmičenja',
        ];
        uasort($season_options, function ($a, $b) {
            return strnatcasecmp((string) $a, (string) $b);
        });

        ob_start();
        echo self::shortcode_title_html('Takmičenja');
        echo '<div class="stkb-prikaz-takmicenja">';
        if ($enable_filter) {
            echo '<form method="get" class="stkb-grid-filters">';
            foreach ($_GET as $k => $v) {
                $k = (string) $k;
                if ($k === 'stkb_takm_sezona') {
                    continue;
                }
                if (is_array($v)) {
                    continue;
                }
                echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string) wp_unslash($v)) . '">';
            }
            echo '<label>Sezona <select name="stkb_takm_sezona" onchange="this.form.submit()">';
            foreach ($season_options as $slug => $label) {
                echo '<option value="' . esc_attr((string) $slug) . '"' . selected($selected_season, (string) $slug, false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select></label>';
            if (isset($_GET['stkb_takm_sezona'])) {
                echo '<a class="button stkb-grid-filter-reset" href="' . esc_url(remove_query_arg(['stkb_takm_sezona'])) . '">Reset</a>';
            }
            echo '</form>';
        }
        $has_items = false;
        for ($rank = 1; $rank <= 5; $rank++) {
            $items = $groups[$rank];
            if (empty($items)) {
                continue;
            }
            $has_items = true;
            if ($limit > 0) {
                $items = array_slice($items, 0, $limit);
            }

            echo '<section class="stkb-prikaz-takmicenja-rank stkb-prikaz-takmicenja-rank-' . intval($rank) . '">';
            echo '<h3 class="stkb-prikaz-takmicenja-rank-title">' . esc_html($rank_titles[$rank]) . '</h3>';
            echo '<div class="stkb-prikaz-takmicenja-grid">';
            foreach ($items as $item) {
                $url = (string) ($item['url'] ?? '');
                $tag = $url !== '' ? 'a' : 'div';
                $open_attrs = $url !== ''
                    ? ' class="stkb-prikaz-takmicenja-card" href="' . esc_url($url) . '"'
                    : ' class="stkb-prikaz-takmicenja-card"';

                echo '<' . $tag . $open_attrs . '>';
                echo '<div class="stkb-prikaz-takmicenja-card-head">';
                echo '<div class="stkb-prikaz-takmicenja-logo">';
                if (!empty($item['rule_id']) && has_post_thumbnail((int) $item['rule_id'])) {
                    echo get_the_post_thumbnail((int) $item['rule_id'], 'thumbnail', ['class' => 'stkb-prikaz-takmicenja-logo-img']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                } else {
                    echo '<div class="stkb-prikaz-takmicenja-logo-fallback"></div>';
                }
                echo '</div>';
                echo '<div class="stkb-prikaz-takmicenja-meta">';
                echo '<div class="stkb-prikaz-takmicenja-title">' . esc_html((string) $item['league_name']) . '</div>';
                echo '<div class="stkb-prikaz-takmicenja-season">Sezona ' . esc_html((string) $item['season_name']) . '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="stkb-prikaz-takmicenja-sep"></div>';
                echo '<div class="stkb-prikaz-takmicenja-clubs">';
                $club_ids = is_array($item['club_ids']) ? $item['club_ids'] : [];
                if (empty($club_ids)) {
                    echo '<span class="stkb-prikaz-takmicenja-no-clubs">Nema klubova</span>';
                } else {
                    foreach ($club_ids as $club_id) {
                        $club_id = (int) $club_id;
                        if ($club_id <= 0) {
                            continue;
                        }
                        $club_name = (string) get_the_title($club_id);
                        echo '<span class="stkb-prikaz-takmicenja-club">';
                        echo self::club_logo_html($club_id, 'thumbnail', ['class' => 'stkb-prikaz-takmicenja-club-logo', 'title' => $club_name]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo '</span>';
                    }
                }
                echo '</div>';
                echo '</' . $tag . '>';
            }
            echo '</div>';
            echo '</section>';
        }
        if (!$has_items) {
            echo '<p>Nema takmičenja za zadatu sezonu.</p>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    private static function competition_display_name($liga_slug, $sezona_slug)
    {
        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);

        if ($liga_slug === '' && $sezona_slug === '') {
            return '';
        }

        $liga_name = $liga_slug !== '' ? self::slug_to_title($liga_slug) : '';
        if ($liga_name === '' && $liga_slug !== '') {
            $liga_name = (string) $liga_slug;
        }

        if ($sezona_slug === '') {
            return $liga_name;
        }

        return trim($liga_name . ', Sezona ' . self::season_display_name($sezona_slug));
    }

    private static function season_display_name($sezona_slug)
    {
        $sezona_slug = sanitize_title((string) $sezona_slug);
        if ($sezona_slug === '') {
            return '';
        }

        if (preg_match('/^(\d{4})-(\d{2,4})$/', $sezona_slug, $m)) {
            $second = (string) $m[2];
            if (strlen($second) === 4) {
                $second = substr($second, 2);
            }
            return $m[1] . '/' . $second;
        }

        return self::slug_to_title($sezona_slug);
    }

    private static function competition_archive_url($liga_slug, $sezona_slug)
    {
        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);

        if ($liga_slug === '') {
            return '';
        }

        $term_candidates = [];
        if ($sezona_slug !== '') {
            $term_candidates[] = $liga_slug . '-' . $sezona_slug;
        }
        $term_candidates[] = $liga_slug;

        foreach ($term_candidates as $term_slug) {
            $term = get_term_by('slug', $term_slug, 'liga_sezona');
            if ($term && !is_wp_error($term)) {
                $term_link = get_term_link($term);
                if (!is_wp_error($term_link)) {
                    return (string) $term_link;
                }
            }
        }

        // Plain permalink fallback (fresh install default): koristi query args.
        if ((string) get_option('permalink_structure', '') === '') {
            $base = home_url('/');
            $args = ['liga' => $liga_slug];
            if ($sezona_slug !== '') {
                $args['sezona'] = $sezona_slug;
            }
            return add_query_arg($args, $base);
        }

        if ($sezona_slug !== '') {
            return home_url('/liga/' . rawurlencode($liga_slug) . '/' . rawurlencode($sezona_slug) . '/');
        }

        return home_url('/liga/' . rawurlencode($liga_slug) . '/');
    }

    private static function match_venue_label($row)
    {
        $legacy_id = isset($row->legacy_post_id) ? intval($row->legacy_post_id) : 0;
        if ($legacy_id > 0) {
            $keys = [
                'mesto_odigravanja',
                'mesto_utakmice',
                'lokacija_utakmice',
                'lokacija',
                'hala',
                'sala',
                'teren',
                'mesto',
            ];
            foreach ($keys as $key) {
                $value = trim((string) get_post_meta($legacy_id, $key, true));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    public static function shortcode_top_players_list($atts = [])
    {
        global $post;

        $atts = shortcode_atts([
            'limit' => 5,
            'liga' => '',
            'sezona' => '',
        ], $atts);

        $limit = intval($atts['limit']);
        if ($limit === 0) {
            $limit = 5;
        }
        $limit = $limit < -1 ? -1 : $limit;

        $liga_slug = '';
        $sezona_slug = '';
        $max_kolo = null;
        $highlight_klubovi = [];
        $current_igrac_id = (is_singular('igrac') && get_post_type((int) get_the_ID()) === 'igrac') ? (int) get_the_ID() : 0;
        $ctx = null;
        $archive_ctx = self::current_archive_context();

        $liga_param = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona_param = sanitize_title((string) ($atts['sezona'] ?? ''));

        if ($liga_param !== '') {
            $parsed = self::parse_legacy_liga_sezona($liga_param, $sezona_param);
            $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? $liga_param));
            $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? $sezona_param));
        } elseif (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona') {
            $raw_liga = sanitize_title((string) ($archive_ctx['liga_slug'] ?? ''));
            $raw_sezona = sanitize_title((string) ($archive_ctx['sezona_slug'] ?? ''));
            $parsed_ctx = self::parse_legacy_liga_sezona($raw_liga, $raw_sezona);
            $liga_slug = sanitize_title((string) ($parsed_ctx['league_slug'] ?? $raw_liga));
            $sezona_slug = sanitize_title((string) ($parsed_ctx['season_slug'] ?? $raw_sezona));
            if ($liga_slug === '' && $sezona_slug !== '') {
                global $wpdb;
                $table = $wpdb->prefix . 'stkb_matches';
                if (self::table_exists($table)) {
                    $liga_guess = $wpdb->get_var($wpdb->prepare("SELECT liga_slug FROM {$table} WHERE sezona_slug=%s AND liga_slug<>'' ORDER BY id DESC LIMIT 1", $sezona_slug));
                    if (is_string($liga_guess) && $liga_guess !== '') {
                        $liga_slug = sanitize_title($liga_guess);
                    }
                }
            }

            $kolo_virtual = sanitize_title((string) ($archive_ctx['kolo_slug'] ?? ''));
            if ($kolo_virtual !== '') {
                $max_kolo = self::extract_round_no($kolo_virtual);
            }
        } elseif (is_tax('liga_sezona')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $parsed = self::parse_legacy_liga_sezona((string) $term->slug, '');
                $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? ''));
                $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? ''));
            }
        } else {
            $ctx = self::current_match_context();
            if ($ctx && !empty($ctx['db_row'])) {
                $row = $ctx['db_row'];
                $liga_slug = sanitize_title((string) $row->liga_slug);
                $sezona_slug = sanitize_title((string) $row->sezona_slug);
                $max_kolo = self::extract_round_no((string) $row->kolo_slug);
                $highlight_klubovi[] = intval($row->home_club_post_id);
                $highlight_klubovi[] = intval($row->away_club_post_id);
            } elseif ($current_igrac_id > 0) {
                $player_comp = self::db_get_latest_competition_for_player($current_igrac_id);
                if ($player_comp) {
                    $liga_slug = (string) $player_comp['liga_slug'];
                    $sezona_slug = (string) $player_comp['sezona_slug'];
                }
            } elseif (is_singular('utakmica') && !empty($post->ID)) {
                $legacy_liga_terms = wp_get_post_terms((int) $post->ID, 'liga_sezona', ['fields' => 'slugs']);
                if (!empty($legacy_liga_terms) && !is_wp_error($legacy_liga_terms)) {
                    $parsed = self::parse_legacy_liga_sezona((string) $legacy_liga_terms[0], '');
                    $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? ''));
                    $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? ''));
                }
            } else {
                $latest_comp = self::db_get_latest_competition_with_games();
                if ($latest_comp) {
                    $liga_slug = (string) $latest_comp['liga_slug'];
                    $sezona_slug = (string) $latest_comp['sezona_slug'];
                }
            }
        }

        $liga_slug = sanitize_title($liga_slug);
        $sezona_slug = sanitize_title($sezona_slug);
        if ($liga_slug === '') {
            return '';
        }

        $data = self::db_get_top_players_data($liga_slug, $sezona_slug, $max_kolo);
        if (empty($data)) {
            return self::shortcode_title_html('Rang lista igrača') . '<div class="no-players-message">Trenutno nema igrača za prikaz.</div>';
        }

        ob_start();
        echo self::shortcode_title_html('Rang lista igrača');
        echo '<div class="top-igraci-list">';
        $i = 1;
        foreach ($data as $igrac_id => $info) {
            if ($limit !== -1 && $i > $limit) {
                break;
            }
            $highlight = false;
            if ($current_igrac_id > 0 && intval($igrac_id) === $current_igrac_id) {
                $highlight = true;
            }
            if (!empty($highlight_klubovi) && in_array(intval($info['klub']), $highlight_klubovi, true)) {
                $highlight = true;
            }
            if ($igrac_id > 0 && get_post_type($igrac_id) === 'igrac') {
                echo self::render_top_player_card_list($igrac_id, $i, $info, $highlight); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            $i++;
        }

        if ($current_igrac_id > 0 && get_post_type($current_igrac_id) === 'igrac' && $limit > 0) {
            $rank = 1;
            foreach ($data as $igrac_id => $info) {
                if (intval($igrac_id) === $current_igrac_id && $rank > $limit) {
                    echo '<div class="top-igraci-separator"></div>';
                    echo self::render_top_player_card_list($igrac_id, $rank, $info, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    break;
                }
                $rank++;
            }
        }

        if ($ctx && !empty($highlight_klubovi) && $limit > 0) {
            $rank = 1;
            foreach ($data as $igrac_id => $info) {
                if ($rank <= $limit) {
                    $rank++;
                    continue;
                }
                if (in_array(intval($info['klub']), $highlight_klubovi, true)) {
                    echo '<div class="top-igraci-separator"></div>';
                    echo self::render_top_player_card_list($igrac_id, $rank, $info, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                $rank++;
            }
        }

        echo '</div>';
        return ob_get_clean();
    }

    private static function db_get_top_players_data($liga_slug, $sezona_slug = '', $max_kolo = null)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_top_players_data($liga_slug, $sezona_slug, $max_kolo);
    }

    private static function db_get_played_matches_count_by_club($liga_slug, $sezona_slug = '', $max_kolo = null)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_played_matches_count_by_club($liga_slug, $sezona_slug, $max_kolo);
    }

    private static function db_get_latest_competition_with_games()
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_competition_with_games();
    }

    private static function db_get_latest_competition_for_player($player_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_competition_for_player($player_id);
    }

    private static function db_get_latest_competition_for_club($club_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_competition_for_club($club_id);
    }

    private static function db_get_recent_club_matches($club_id, $limit = 5)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_recent_club_matches($club_id, $limit);
    }

    private static function db_get_player_season_club_history($player_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_player_season_club_history($player_id);
    }

    private static function season_sort_key($season_slug)
    {
        return OpenTT_Unified_Readonly_Helpers::season_sort_key($season_slug);
    }

    private static function build_player_stints($history)
    {
        if (empty($history) || !is_array($history)) {
            return [];
        }
        $stints = [];
        foreach ($history as $row) {
            $season = sanitize_title((string) ($row['season_slug'] ?? ''));
            $club_id = intval($row['club_id'] ?? 0);
            if ($season === '' || $club_id <= 0) {
                continue;
            }
            if (empty($stints)) {
                $stints[] = [
                    'club_id' => $club_id,
                    'from_season' => $season,
                    'to_season' => $season,
                ];
                continue;
            }
            $idx = count($stints) - 1;
            if (intval($stints[$idx]['club_id']) === $club_id) {
                $stints[$idx]['to_season'] = $season;
            } else {
                $stints[] = [
                    'club_id' => $club_id,
                    'from_season' => $season,
                    'to_season' => $season,
                ];
            }
        }
        return $stints;
    }

    private static function db_get_player_stats($player_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_player_stats($player_id, $season_slug);
    }

    private static function db_get_player_season_options($player_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_player_season_options($player_id);
    }

    private static function db_get_club_season_options($club_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_club_season_options($club_id);
    }

    private static function db_get_latest_liga_for_player_and_season($player_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_liga_for_player_and_season($player_id, $season_slug);
    }

    private static function db_get_latest_liga_for_club_and_season($club_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_liga_for_club_and_season($club_id, $season_slug);
    }

    private static function db_get_club_team_stats($club_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_club_team_stats($club_id, $season_slug);
    }

    private static function db_get_club_season_best_player_by_success($club_id, $season_slug)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_club_season_best_player_by_success($club_id, $season_slug);
    }

    private static function db_get_competition_club_ids($liga_slug, $sezona_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_competition_club_ids($liga_slug, $sezona_slug);
    }

    private static function db_build_standings_for_competition($liga_slug, $sezona_slug = '', $max_kolo = null)
    {
        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);
        if ($liga_slug === '') {
            return [];
        }

        $rows = self::db_get_matches([
            'limit' => -1,
            'liga_slug' => $liga_slug,
            'sezona_slug' => $sezona_slug,
            'kolo_slug' => '',
            'played' => '',
            'club_id' => 0,
            'player_id' => 0,
        ]);
        if (empty($rows)) {
            return [];
        }

        $sistem = 'novi';
        $rule = self::get_competition_rule_data($liga_slug, $sezona_slug);
        if (is_array($rule) && !empty($rule['bodovanje_tip'])) {
            $sistem = ((string) $rule['bodovanje_tip'] === '3-0_4-3_2-1') ? 'novi' : 'stari';
        }

        $stat = [];
        foreach ($rows as $r) {
            $home = intval($r->home_club_post_id);
            $away = intval($r->away_club_post_id);
            foreach ([$home, $away] as $club_id) {
                if ($club_id <= 0) {
                    continue;
                }
                if (!isset($stat[$club_id])) {
                    $stat[$club_id] = [
                        'odigrane' => 0,
                        'pobede' => 0,
                        'porazi' => 0,
                        'bodovi' => 0,
                        'meckol' => 0,
                    ];
                }
            }
        }

        foreach ($rows as $r) {
            if (intval($r->played) !== 1) {
                continue;
            }
            $round = self::extract_round_no((string) $r->kolo_slug);
            if ($max_kolo !== null && $round > intval($max_kolo)) {
                continue;
            }

            $home = intval($r->home_club_post_id);
            $away = intval($r->away_club_post_id);
            if ($home <= 0 || $away <= 0) {
                continue;
            }
            $rd = intval($r->home_score);
            $rg = intval($r->away_score);

            $stat[$home]['odigrane']++;
            $stat[$away]['odigrane']++;
            $stat[$home]['meckol'] += ($rd - $rg);
            $stat[$away]['meckol'] += ($rg - $rd);

            $home_win = ($rd > $rg);
            $away_win = ($rg > $rd);
            if ($home_win) {
                $stat[$home]['pobede']++;
                $stat[$away]['porazi']++;
            } elseif ($away_win) {
                $stat[$away]['pobede']++;
                $stat[$home]['porazi']++;
            }

            if ($sistem === 'novi') {
                if ($home_win) {
                    if ($rd === 4 && in_array($rg, [0, 1, 2], true)) {
                        $stat[$home]['bodovi'] += 3;
                    } elseif ($rd === 4 && $rg === 3) {
                        $stat[$home]['bodovi'] += 2;
                        $stat[$away]['bodovi'] += 1;
                    }
                } elseif ($away_win) {
                    if ($rg === 4 && in_array($rd, [0, 1, 2], true)) {
                        $stat[$away]['bodovi'] += 3;
                    } elseif ($rg === 4 && $rd === 3) {
                        $stat[$away]['bodovi'] += 2;
                        $stat[$home]['bodovi'] += 1;
                    }
                }
            } else {
                if ($home_win) {
                    $stat[$home]['bodovi'] += 2;
                    $stat[$away]['bodovi'] += 1;
                } elseif ($away_win) {
                    $stat[$away]['bodovi'] += 2;
                    $stat[$home]['bodovi'] += 1;
                }
            }
        }

        uasort($stat, function ($a, $b) {
            if ($a['bodovi'] === $b['bodovi']) {
                if ($a['meckol'] === $b['meckol']) {
                    return 0;
                }
                return ($a['meckol'] > $b['meckol']) ? -1 : 1;
            }
            return ($a['bodovi'] > $b['bodovi']) ? -1 : 1;
        });

        $out = [];
        $rank = 0;
        foreach ($stat as $club_id => $row) {
            $rank++;
            $out[] = [
                'rank' => $rank,
                'club_id' => intval($club_id),
                'odigrane' => intval($row['odigrane']),
                'pobede' => intval($row['pobede']),
                'porazi' => intval($row['porazi']),
                'bodovi' => intval($row['bodovi']),
                'meckol' => intval($row['meckol']),
            ];
        }

        return $out;
    }

    private static function find_club_rank_in_standings($standings, $club_id)
    {
        $club_id = intval($club_id);
        if ($club_id <= 0 || empty($standings) || !is_array($standings)) {
            return 0;
        }
        foreach ($standings as $row) {
            if (intval($row['club_id'] ?? 0) === $club_id) {
                return intval($row['rank'] ?? 0);
            }
        }
        return 0;
    }

    private static function build_standings_window_around_club($standings, $club_rank, $radius = 2)
    {
        if (empty($standings) || !is_array($standings) || $club_rank <= 0) {
            return [];
        }
        $radius = max(0, intval($radius));
        $from = max(1, intval($club_rank) - $radius);
        $to = intval($club_rank) + $radius;
        $slice = [];
        foreach ($standings as $row) {
            $rank = intval($row['rank'] ?? 0);
            if ($rank >= $from && $rank <= $to) {
                $slice[] = $row;
            }
        }
        return $slice;
    }

    private static function format_percentage_value($value)
    {
        $value = max(0.0, floatval($value));
        if (abs($value - round($value)) < 0.05) {
            return (string) intval(round($value));
        }
        return (string) round($value, 1);
    }

    private static function db_get_player_mvp_count($player_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_player_mvp_count($player_id, $season_slug);
    }

    private static function db_get_match_mvp_player_id($match_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_match_mvp_player_id($match_id);
    }

    private static function render_top_player_card_list($igrac_id, $rank, $info, $highlight = false)
    {
        $igrac_id = intval($igrac_id);
        if ($igrac_id <= 0) {
            return '';
        }

        $full_name = (string) get_the_title($igrac_id);
        $parts = explode(' ', $full_name, 2);
        $ime = isset($parts[0]) ? $parts[0] : '';
        $prezime = isset($parts[1]) ? $parts[1] : '';

        $slika = get_the_post_thumbnail($igrac_id, 'thumbnail', ['class' => 'igrac-slika']);
        if (empty($slika)) {
            $slika = '<img src="' . esc_url(self::player_fallback_image_url()) . '" alt="Igrač" class="igrac-slika" />';
        }

        $klub_id = intval($info['klub'] ?? 0);
        $grb = $klub_id ? self::club_logo_html($klub_id, 'thumbnail', ['class' => 'igrac-klub-grb']) : '';
        $naziv_kluba = $klub_id ? (string) get_the_title($klub_id) : '';
        $wins = intval($info['pobede'] ?? 0);
        $losses = intval($info['porazi'] ?? 0);
        $total = $wins + $losses;
        $score = $wins . '-' . $losses;
        $percent = $total > 0 ? (string) round(($wins / $total) * 100) . '%' : '-';
        $highlight_class = $highlight ? ' highlight' : '';
        $igrac_link = get_permalink($igrac_id);

        ob_start();
        ?>
        <div class="igrac-card-list<?php echo esc_attr($highlight_class); ?>">
            <div class="igrac-rank"><?php echo intval($rank); ?></div>
            <a class="igrac-link" href="<?php echo esc_url($igrac_link); ?>">
                <div class="igrac-slika-wrap"><?php echo $slika; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            </a>
            <div class="igrac-imeprezime">
                <a class="igrac-link" href="<?php echo esc_url($igrac_link); ?>">
                    <div class="ime"><?php echo esc_html($ime); ?></div>
                    <div class="prezime"><?php echo esc_html($prezime); ?></div>
                </a>
                <div class="igrac-klub">
                    <?php if ($grb): ?>
                        <span class="igrac-klub-grb"><?php echo $grb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <?php endif; ?>
                    <span class="igrac-klub-naziv"><?php echo esc_html($naziv_kluba); ?></span>
                </div>
            </div>
            <div class="igrac-skor"><?php echo esc_html($score); ?></div>
            <div class="igrac-procenat"><?php echo esc_html($percent); ?></div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function get_validation_report()
    {
        $report = get_option(self::OPTION_VALIDATION_REPORT, []);
        return is_array($report) ? $report : [];
    }

    private static function build_match_query_args($atts)
    {
        $limit = isset($atts['limit']) ? intval($atts['limit']) : 5;
        $liga = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona_from_atts = !empty($atts['sezona']) ? sanitize_title((string) $atts['sezona']) : '';
        $kolo = '';
        $odigrana = '';
        $club_id = 0;
        $player_id = 0;
        $archive_ctx = self::current_archive_context();

        if ($liga === '') {
            if (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona') {
                $liga = sanitize_title((string) ($archive_ctx['liga_slug'] ?? ''));
            } elseif (is_tax('liga_sezona')) {
                $term = get_queried_object();
                if ($term && !is_wp_error($term) && !empty($term->slug)) {
                    $liga = sanitize_title((string) $term->slug);
                }
            } else {
                $liga_qv = get_query_var('liga_sezona');
                if ($liga_qv) {
                    $liga = sanitize_title((string) $liga_qv);
                }
            }
        }

        if (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'kolo') {
            $kolo = sanitize_title((string) ($archive_ctx['kolo_slug'] ?? ''));
        } elseif (is_tax('kolo')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $kolo = sanitize_title((string) $term->slug);
            }
        } else {
            $kolo_qv = get_query_var('kolo');
            if ($kolo_qv) {
                $kolo = sanitize_title((string) $kolo_qv);
            }
        }

        if (isset($atts['odigrana']) && $atts['odigrana'] !== '') {
            $val = strtolower(trim((string) $atts['odigrana']));
            if ($val === 'da') {
                $val = '1';
            }
            if ($val === 'ne') {
                $val = '0';
            }
            if ($val === '0' || $val === '1') {
                $odigrana = $val;
            }
        }

        if (!empty($atts['klub'])) {
            $club_slug_or_name = (string) $atts['klub'];
            $club = get_page_by_path(sanitize_title($club_slug_or_name), OBJECT, 'klub');
            if (!$club) {
                $club = get_page_by_title($club_slug_or_name, OBJECT, 'klub');
            }
            if ($club && !is_wp_error($club)) {
                $club_id = intval($club->ID);
            }
        } elseif (is_singular('klub')) {
            $club_id = intval(get_the_ID());
        }

        if (empty($atts['klub']) && is_singular('igrac')) {
            $player_id = intval(get_the_ID());
        }

        // Back-compat: dozvoli i legacy spojeni slug tipa "kvalitetna-liga-2025-26".
        // Ako je sezona već eksplicitno prosleđena, ona ima prioritet.
        $parsed = self::parse_legacy_liga_sezona($liga, $sezona_from_atts);
        $liga = sanitize_title((string) ($parsed['league_slug'] ?? $liga));
        $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? $sezona_from_atts));
        if ($sezona_from_atts !== '') {
            $sezona_slug = $sezona_from_atts;
        }

        return [
            'limit' => $limit,
            'liga_slug' => $liga,
            'sezona_slug' => $sezona_slug,
            'kolo_slug' => $kolo,
            'played' => $odigrana,
            'club_id' => $club_id,
            'player_id' => $player_id,
        ];
    }

    private static function db_get_matches($args)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_matches($args);
    }

    private static function db_get_match_by_legacy_id($legacy_id)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_match_by_legacy_id($legacy_id);
    }

    private static function db_get_match_by_keys($liga_slug, $sezona_slug, $kolo_slug, $slug)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_match_by_keys($liga_slug, $sezona_slug, $kolo_slug, $slug);
    }

    private static function db_get_h2h_matches($current_match_db_id, $home_club_id, $away_club_id)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_h2h_matches($current_match_db_id, $home_club_id, $away_club_id);
    }

    private static function db_get_games_for_match_id($match_id)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_games_for_match_id($match_id);
    }

    private static function db_get_sets_for_game_id($game_id)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_sets_for_game_id($game_id);
    }

    private static function db_get_latest_liga_for_club($club_id)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_latest_liga_for_club($club_id);
    }

    private static function render_matches_grid_html($rows, $columns, $with_kolo_attr)
    {
        if (empty($rows)) {
            return '<p>Nema utakmica za prikaz.</p>';
        }

        ob_start();
        echo '<div class="stkb-grid-wrapper"><div class="stkb-grid cols-' . intval($columns) . '">';
        foreach ($rows as $row) {
            $home_id = intval($row->home_club_post_id);
            $away_id = intval($row->away_club_post_id);
            $rd = intval($row->home_score);
            $rg = intval($row->away_score);
            $home_win = ($rd === 4);
            $away_win = ($rg === 4);
            $kolo_name = self::kolo_name_from_slug((string) $row->kolo_slug);
            $date = self::display_match_date($row->match_date);
            $link = self::match_permalink($row);

            $attr = '';
            if ($with_kolo_attr) {
                $kolo_slug = sanitize_title((string) $row->kolo_slug);
                $match_ts = strtotime((string) $row->match_date);
                if ($match_ts === false) {
                    $match_ts = 0;
                }
                $attr = ' data-kolo-slug="' . esc_attr($kolo_slug) . '"';
                $attr .= ' data-kolo-no="' . esc_attr((string) self::extract_round_no($kolo_slug)) . '"';
                $attr .= ' data-match-ts="' . esc_attr((string) intval($match_ts)) . '"';
                $attr .= ' data-home-club-id="' . esc_attr((string) $home_id) . '"';
                $attr .= ' data-away-club-id="' . esc_attr((string) $away_id) . '"';
            }

            echo '<div class="stkb-item"' . $attr . '>';
            echo '<a href="' . esc_url($link) . '">';
            echo self::render_team_html($home_id, $rd, $home_win);
            echo self::render_team_html($away_id, $rg, $away_win);
            echo '<div class="meta"><span>' . esc_html($kolo_name) . '</span><span>' . esc_html($date) . '</span></div>';
            echo '</a></div>';
        }
        echo '</div></div>';
        return ob_get_clean();
    }

    private static function render_clubs_grid_html($rows, $columns, $with_attrs)
    {
        if (empty($rows)) {
            return '<p>Nema klubova za prikaz.</p>';
        }

        ob_start();
        echo '<div class="stkb-klubovi">';
        echo '<div class="stkb-klubovi-grid cols-' . intval($columns) . '">';
        foreach ($rows as $row) {
            $club_id = intval($row['id'] ?? 0);
            $url = (string) ($row['url'] ?? '');
            $display_name = (string) ($row['display_name'] ?? '');
            $league_label = (string) ($row['league_label'] ?? 'Bez takmičenja');
            $grad_label = trim((string) ($row['grad_label'] ?? ''));
            $logo_html = (string) ($row['logo_html'] ?? '');
            $sort_name = (string) ($row['sort_name'] ?? '');
            $league_slug = sanitize_title((string) ($row['league_slug'] ?? ''));
            $opstina_slug = sanitize_title((string) ($row['opstina_slug'] ?? ''));

            $attrs = ' data-club-id="' . esc_attr((string) $club_id) . '"';
            if ($with_attrs) {
                $attrs .= ' data-league-slug="' . esc_attr($league_slug) . '"';
                $attrs .= ' data-opstina-slug="' . esc_attr($opstina_slug) . '"';
                $attrs .= ' data-sort-name="' . esc_attr($sort_name) . '"';
            }

            echo '<article class="stkb-klubovi-item"' . $attrs . '>';
            echo '<a class="stkb-klubovi-link" href="' . esc_url($url) . '">';
            echo '<span class="stkb-klubovi-logo-wrap">' . $logo_html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="stkb-klubovi-content">';
            echo '<strong class="stkb-klubovi-name">' . esc_html($display_name) . '</strong>';
            if ($grad_label !== '') {
                echo '<span class="stkb-klubovi-city">' . esc_html($grad_label) . '</span>';
            }
            echo '<span class="stkb-klubovi-league">' . esc_html($league_label) . '</span>';
            echo '</span>';
            echo '</a>';
            echo '</article>';
        }
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    private static function club_fallback_image_url()
    {
        $plugin_dir = is_string(self::$plugin_dir) ? trim(self::$plugin_dir) : '';
        if ($plugin_dir === '') {
            return '';
        }

        $relative_candidates = [
            'assets/img/fallback-club.png',
            'assets/image/fallback-club.png',
        ];

        foreach ($relative_candidates as $relative_path) {
            $absolute_path = trailingslashit($plugin_dir) . $relative_path;
            if (is_readable($absolute_path)) {
                return plugins_url($relative_path, self::$plugin_file);
            }
        }

        return '';
    }

    private static function player_fallback_image_url()
    {
        $plugin_dir = is_string(self::$plugin_dir) ? trim(self::$plugin_dir) : '';
        if ($plugin_dir === '') {
            return '';
        }

        $relative_candidates = [
            'assets/img/fallback-player.png',
            'assets/image/fallback-player.png',
            'assets/img/fallback-club.png',
            'assets/image/fallback-club.png',
        ];

        foreach ($relative_candidates as $relative_path) {
            $absolute_path = trailingslashit($plugin_dir) . $relative_path;
            if (is_readable($absolute_path)) {
                return plugins_url($relative_path, self::$plugin_file);
            }
        }

        return '';
    }

    private static function club_logo_url($club_id, $size = 'thumbnail')
    {
        $club_id = intval($club_id);
        if ($club_id <= 0) {
            return self::club_fallback_image_url();
        }

        $url = get_the_post_thumbnail_url($club_id, $size);
        if (is_string($url) && trim($url) !== '') {
            return $url;
        }

        return self::club_fallback_image_url();
    }

    private static function club_logo_html($club_id, $size = 'thumbnail', $attr = [])
    {
        $club_id = intval($club_id);
        $attr = is_array($attr) ? $attr : [];

        if ($club_id > 0) {
            $html = get_the_post_thumbnail($club_id, $size, $attr);
            if (is_string($html) && trim($html) !== '') {
                return $html;
            }
        }

        $fallback_url = self::club_fallback_image_url();
        if ($fallback_url === '') {
            return '';
        }

        $class = isset($attr['class']) ? trim((string) $attr['class']) : '';
        if ($class === '') {
            $class = 'stkb-club-fallback-image';
        }
        $alt = isset($attr['alt']) ? (string) $attr['alt'] : (string) get_the_title($club_id);

        $img_attr = [
            'src' => $fallback_url,
            'alt' => $alt,
            'class' => $class,
        ];

        foreach (['style', 'loading', 'title', 'width', 'height', 'decoding'] as $key) {
            if (isset($attr[$key]) && $attr[$key] !== '') {
                $img_attr[$key] = (string) $attr[$key];
            }
        }

        $parts = [];
        foreach ($img_attr as $key => $value) {
            $parts[] = $key . '="' . esc_attr($value) . '"';
        }

        return '<img ' . implode(' ', $parts) . ' />';
    }

    private static function render_team_html($club_id, $score, $is_winner)
    {
        $class = $is_winner ? 'pobednik' : 'gubitnik';
        $name = $club_id ? get_the_title($club_id) : '';
        $crest = $club_id ? self::club_logo_html($club_id, 'thumbnail') : '';

        ob_start();
        echo '<div class="team ' . esc_attr($class) . '">';
        if (!empty($crest)) {
            echo $crest; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '<span>' . esc_html($name) . '</span>';
        echo '<strong>' . esc_html((string) intval($score)) . '</strong>';
        echo '</div>';
        return ob_get_clean();
    }

    private static function match_permalink($row)
    {
        $legacy_id = isset($row->legacy_post_id) ? intval($row->legacy_post_id) : 0;
        if (
            self::is_legacy_match_cpt_enabled()
            && $legacy_id > 0
            && get_post_type($legacy_id) === 'utakmica'
        ) {
            return get_permalink($legacy_id);
        }

        $liga = isset($row->liga_slug) ? sanitize_title((string) $row->liga_slug) : '';
        $sezona = isset($row->sezona_slug) ? sanitize_title((string) $row->sezona_slug) : '';
        $kolo = isset($row->kolo_slug) ? sanitize_title((string) $row->kolo_slug) : '';
        $slug = isset($row->slug) ? sanitize_title((string) $row->slug) : '';

        if ($liga === '' || $kolo === '' || $slug === '') {
            return home_url('/');
        }

        $path = '/' . $liga . '/';
        if ($sezona !== '') {
            $path .= $sezona . '/';
        }
        $path .= $kolo . '/' . $slug . '/';

        return home_url($path);
    }

    private static function display_match_date($match_date)
    {
        return OpenTT_Unified_Readonly_Helpers::display_match_date($match_date);
    }

    private static function display_match_date_long($match_date)
    {
        return OpenTT_Unified_Readonly_Helpers::display_match_date_long($match_date);
    }

    private static function kolo_name_from_slug($slug)
    {
        $slug = sanitize_title((string) $slug);
        if ($slug === '') {
            return '';
        }
        $term = get_term_by('slug', $slug, 'kolo');
        if ($term && !is_wp_error($term) && !empty($term->name)) {
            return (string) $term->name;
        }
        return $slug;
    }

    private static function extract_round_no($kolo_slug)
    {
        return OpenTT_Unified_Readonly_Helpers::extract_round_no($kolo_slug);
    }

    private static function render_lp2_player($player_id)
    {
        $player_id = intval($player_id);
        if ($player_id <= 0) {
            return '';
        }

        $link = get_permalink($player_id);
        $thumb = has_post_thumbnail($player_id)
            ? get_the_post_thumbnail($player_id, 'thumbnail', ['class' => 'lp2-thumb'])
            : '<img src="' . esc_url(self::player_fallback_image_url()) . '" alt="Igrač" class="lp2-thumb" />';

        $title = (string) get_the_title($player_id);
        $parts = explode(' ', $title, 2);
        $ime = isset($parts[0]) ? $parts[0] : '';
        $prezime = isset($parts[1]) ? $parts[1] : '';

        ob_start();
        echo '<div class="lp2-igrac-wrap">';
        echo '<a class="lp2-igrac" href="' . esc_url($link) . '">';
        echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="lp2-name"><span>' . esc_html($ime) . '</span><span>' . esc_html($prezime) . '</span></div>';
        echo '</a>';
        echo '</div>';
        return ob_get_clean();
    }

    private static function render_klub_card_html($klub_id)
    {
        $klub_id = intval($klub_id);
        if ($klub_id <= 0) {
            return '';
        }
        $naziv = get_the_title($klub_id);
        $grb = self::club_logo_html($klub_id, 'thumbnail', ['class' => 'stkb-grb']);
        $link = get_permalink($klub_id);

        ob_start();
        ?>
        <div class="stkb-klub">
            <a href="<?php echo esc_url($link); ?>">
                <div class="stkb-grb-wrap"><?php echo $grb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <div class="stkb-naziv"><?php echo esc_html((string) $naziv); ?></div>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function current_match_context()
    {
        if (self::$virtual_match_row) {
            return [
                'db_row' => self::$virtual_match_row,
                'legacy_id' => intval(self::$virtual_match_row->legacy_post_id),
            ];
        }

        if (is_singular('utakmica')) {
            $legacy_id = intval(get_the_ID());
            $row = self::db_get_match_by_legacy_id($legacy_id);
            if ($row) {
                return ['db_row' => $row, 'legacy_id' => $legacy_id];
            }
        }

        return null;
    }

    public static function get_template_match_context()
    {
        $ctx = self::current_match_context();
        if (!$ctx || empty($ctx['db_row'])) {
            return null;
        }
        $row = $ctx['db_row'];
        return [
            'db_id' => intval($row->id),
            'legacy_id' => intval($ctx['legacy_id']),
            'slug' => (string) $row->slug,
            'liga_slug' => (string) $row->liga_slug,
            'kolo_slug' => (string) $row->kolo_slug,
            'date' => self::display_match_date($row->match_date),
            'kolo_name' => self::kolo_name_from_slug((string) $row->kolo_slug),
            'home_club_id' => intval($row->home_club_post_id),
            'away_club_id' => intval($row->away_club_post_id),
            'home_score' => intval($row->home_score),
            'away_score' => intval($row->away_score),
            'match_url' => self::match_permalink($row),
        ];
    }

    private static function get_match_block_template()
    {
        if (!function_exists('get_block_template')) {
            return null;
        }

        $theme = get_stylesheet();
        $slug = self::MATCH_BLOCK_TEMPLATE_SLUG;

        $tpl = get_block_template($theme . '//' . $slug, 'wp_template');
        if ($tpl) {
            return $tpl;
        }

        $parent = get_template();
        if ($parent && $parent !== $theme) {
            $tpl = get_block_template($parent . '//' . $slug, 'wp_template');
            if ($tpl) {
                return $tpl;
            }
        }

        $posts = get_posts([
            'post_type' => 'wp_template',
            'name' => $slug,
            'numberposts' => 1,
            'post_status' => ['publish', 'draft'],
        ]);
        if (!empty($posts[0])) {
            return (object) [
                'content' => $posts[0]->post_content,
            ];
        }

        // Ako je post_name upisan kao "theme//slug", fallback pretraga.
        $posts = get_posts([
            'post_type' => 'wp_template',
            'posts_per_page' => 20,
            'post_status' => ['publish', 'draft'],
            's' => '//' . $slug,
        ]);
        if (!empty($posts)) {
            foreach ($posts as $p) {
                if (strpos((string) $p->post_name, '//' . $slug) !== false) {
                    return (object) [
                        'content' => $p->post_content,
                    ];
                }
            }
        }

        return null;
    }

}

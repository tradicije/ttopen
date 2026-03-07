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

final class MatchesShortcode
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
            'limit' => 6,
            'klub' => '',
            'played' => '',
            'odigrana' => '',
            'liga' => '',
            'sezona' => '',
            'season' => '',
            'filter' => '',
            'infinite' => '',
            'opentt_match_date' => '',
        ], $atts);

        $is_filter_enabled = self::toBool((string) ($atts['filter'] ?? ''));
        $grid_atts = $atts;

        if ($is_filter_enabled) {
            // In combined shortcode, filter mode always uses progressive "Prikaži još" batches.
            $grid_atts['infinite'] = 'true';
            $grid_atts['pagination'] = 'button';
        }

        $list_atts = $atts;
        unset($list_atts['columns'], $list_atts['filter'], $list_atts['infinite'], $list_atts['opentt_match_date'], $list_atts['pagination']);

        $grid_html = (string) $call('render_matches_grid', $grid_atts);
        $list_html = (string) $call('render_matches_list', $list_atts);

        if ($grid_html === '' && $list_html === '') {
            return '<p>Nema utakmica za prikaz.</p>';
        }

        $uid = 'opentt-matches-combined-' . wp_unique_id();

        ob_start();
        echo '<div id="' . esc_attr($uid) . '" class="opentt-matches-combined" data-opentt-matches-combined="1">';
        echo '<div class="opentt-matches-view-switch" data-opentt-view-switch hidden>';
        echo '<button type="button" class="opentt-matches-view-trigger" aria-haspopup="true" aria-expanded="false">';
        echo '<span class="opentt-view-icon is-grid" aria-hidden="true">▦</span>';
        echo '<span class="opentt-view-caret" aria-hidden="true">▾</span>';
        echo '</button>';
        echo '<div class="opentt-matches-view-menu" hidden>';
        echo '<button type="button" class="opentt-matches-view-option" data-view="grid"><span class="opentt-view-icon" aria-hidden="true">▦</span><span>Grid</span></button>';
        echo '<button type="button" class="opentt-matches-view-option" data-view="list"><span class="opentt-view-icon" aria-hidden="true">≣</span><span>List</span></button>';
        echo '</div>';
        echo '</div>';

        echo '<div class="opentt-matches-view opentt-matches-view-grid is-active" data-view="grid">';
        echo $grid_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';

        echo '<div class="opentt-matches-view opentt-matches-view-list" data-view="list" hidden>';
        echo $list_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
        echo '</div>';
        ?>
        <script>
        (function(){
          var root = document.getElementById(<?php echo wp_json_encode($uid); ?>);
          if (!root || root.dataset.openttCombinedReady === '1') { return; }
          root.dataset.openttCombinedReady = '1';

          var switchWrap = root.querySelector('[data-opentt-view-switch]');
          var trigger = root.querySelector('.opentt-matches-view-trigger');
          var menu = root.querySelector('.opentt-matches-view-menu');
          var opts = Array.prototype.slice.call(root.querySelectorAll('.opentt-matches-view-option'));
          var views = {
            grid: root.querySelector('.opentt-matches-view-grid'),
            list: root.querySelector('.opentt-matches-view-list')
          };
          var current = 'grid';

          if (!switchWrap || !trigger || !menu || !views.grid || !views.list) { return; }
          switchWrap.hidden = false;

          function setTriggerIcon(mode) {
            var icon = trigger.querySelector('.opentt-view-icon');
            if (!icon) { return; }
            icon.textContent = mode === 'list' ? '≣' : '▦';
            icon.className = 'opentt-view-icon ' + (mode === 'list' ? 'is-list' : 'is-grid');
          }

          function closeMenu() {
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
          }

          function openMenu() {
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
          }

          function placeSwitcherForGridFilters() {
            var filtersRight = root.querySelector('.opentt-grid-filter-block .opentt-grid-filters-right');
            if (filtersRight && switchWrap.parentNode !== filtersRight) {
              filtersRight.appendChild(switchWrap);
            }
          }

          function placeSwitcherFallback() {
            if (switchWrap.parentNode === root) { return; }
            root.insertBefore(switchWrap, root.firstChild);
          }

          function setMode(mode) {
            var next = (mode === 'list') ? 'list' : 'grid';
            current = next;
            var showGrid = next === 'grid';

            views.grid.hidden = !showGrid;
            views.list.hidden = showGrid;
            views.grid.classList.toggle('is-active', showGrid);
            views.list.classList.toggle('is-active', !showGrid);

            setTriggerIcon(next);

            if (showGrid) {
              placeSwitcherForGridFilters();
            } else {
              placeSwitcherFallback();
            }

            closeMenu();
          }

          trigger.addEventListener('click', function(ev){
            ev.preventDefault();
            if (menu.hidden) {
              openMenu();
            } else {
              closeMenu();
            }
          });

          opts.forEach(function(btn){
            btn.addEventListener('click', function(ev){
              ev.preventDefault();
              setMode(btn.getAttribute('data-view') || 'grid');
            });
          });

          document.addEventListener('click', function(ev){
            if (!root.contains(ev.target)) {
              closeMenu();
            }
          });

          document.addEventListener('keydown', function(ev){
            if (ev.key === 'Escape') {
              closeMenu();
            }
          });

          setMode('grid');
        })();
        </script>
        <?php

        return ob_get_clean();
    }

    private static function toBool($value)
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'yes', 'da', 'on'], true);
    }
}

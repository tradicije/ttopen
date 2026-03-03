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

if (!defined('ABSPATH')) {
    exit;
}

$ctx = class_exists('OpenTT_Unified_Core') ? OpenTT_Unified_Core::get_template_match_context() : null;
if (!$ctx) {
    status_header(404);
    nocache_headers();
    include get_404_template();
    exit;
}

$home_id = intval($ctx['home_club_id']);
$away_id = intval($ctx['away_club_id']);
$home_title = $home_id > 0 ? get_the_title($home_id) : 'Domaćin';
$away_title = $away_id > 0 ? get_the_title($away_id) : 'Gost';
$home_logo = $home_id > 0 ? get_the_post_thumbnail($home_id, 'thumbnail', ['class' => 'opentt-match-logo']) : '';
$away_logo = $away_id > 0 ? get_the_post_thumbnail($away_id, 'thumbnail', ['class' => 'opentt-match-logo']) : '';

get_header();
?>
<main id="primary" class="opentt-match-page" style="max-width:1100px;margin:0 auto;padding:20px 16px;">
    <section class="opentt-match-hero" style="margin-bottom:18px;">
        <div class="opentt-match-meta" style="margin-bottom:10px;opacity:.8;display:flex;gap:10px;flex-wrap:wrap;">
            <?php if (!empty($ctx['kolo_name'])): ?><span><?php echo esc_html($ctx['kolo_name']); ?></span><?php endif; ?>
            <?php if (!empty($ctx['date'])): ?><span><?php echo esc_html($ctx['date']); ?></span><?php endif; ?>
        </div>

        <div class="opentt-match-scoreline" style="display:grid;grid-template-columns:1fr auto 1fr;gap:18px;align-items:center;background:#0f172a;color:#fff;border-radius:14px;padding:18px;">
            <a href="<?php echo esc_url($home_id > 0 ? get_permalink($home_id) : '#'); ?>" style="text-decoration:none;color:inherit;display:flex;gap:10px;align-items:center;justify-content:flex-start;min-width:0;">
                <?php echo $home_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <strong style="font-size:1.05rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html($home_title); ?></strong>
            </a>

            <div style="font-size:2rem;font-weight:700;line-height:1;white-space:nowrap;">
                <?php echo intval($ctx['home_score']); ?> : <?php echo intval($ctx['away_score']); ?>
            </div>

            <a href="<?php echo esc_url($away_id > 0 ? get_permalink($away_id) : '#'); ?>" style="text-decoration:none;color:inherit;display:flex;gap:10px;align-items:center;justify-content:flex-end;min-width:0;">
                <strong style="font-size:1.05rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html($away_title); ?></strong>
                <?php echo $away_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </a>
        </div>
    </section>

    <section class="opentt-match-table" style="margin-bottom:20px;">
        <?php echo do_shortcode('[opentt_standings_table]'); ?>
    </section>

    <section class="opentt-match-games" style="margin-bottom:20px;">
        <?php echo do_shortcode('[opentt_match_games]'); ?>
    </section>

    <section class="opentt-match-extras" style="display:grid;grid-template-columns:1fr;gap:16px;margin-bottom:20px;">
        <div class="mvp-utakmice-section">
            <?php echo do_shortcode('[opentt_mvp]'); ?>
        </div>

        <div class="h2h-utakmice-section">
            <?php echo do_shortcode('[opentt_h2h]'); ?>
        </div>

        <div class="snimak-utakmice-section-wrap">
            <?php echo do_shortcode('[opentt_match_video]'); ?>
        </div>

        <div class="izvestaj-utakmice-section">
            <?php echo do_shortcode('[opentt_match_report]'); ?>
        </div>
    </section>
</main>
<?php
get_footer();

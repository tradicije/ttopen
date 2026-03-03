<?php
if (!defined('ABSPATH')) {
    exit;
}

$content = class_exists('OpenTT_Unified_Core') ? OpenTT_Unified_Core::render_auto_fallback_content() : '';
if ($content === '') {
    status_header(404);
    nocache_headers();
    include get_404_template();
    exit;
}

get_header();
echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
get_footer();

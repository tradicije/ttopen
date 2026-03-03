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

$content = class_exists('OpenTT_Unified_Core') ? OpenTT_Unified_Core::render_auto_fallback_content() : '';
if ($content === '') {
    status_header(404);
    nocache_headers();
    include get_404_template();
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
$header_tpl = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->';
$footer_tpl = '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
$header_html = function_exists('do_blocks') ? do_blocks($header_tpl) : '';
$footer_html = function_exists('do_blocks') ? do_blocks($footer_tpl) : '';

if (trim((string) $header_html) !== '') {
    echo $header_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
if (trim((string) $footer_html) !== '') {
    echo $footer_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
?>
<?php wp_footer(); ?>
</body>
</html>

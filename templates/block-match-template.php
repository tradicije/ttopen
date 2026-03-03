<?php
if (!defined('ABSPATH')) {
    exit;
}

$content = class_exists('OpenTT_Unified_Core') ? OpenTT_Unified_Core::render_match_block_template_content() : '';
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
<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php wp_footer(); ?>
</body>
</html>

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

get_header();
echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
get_footer();

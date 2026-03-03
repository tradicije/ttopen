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

/**
 * Plugin Name: OpenTT
 * Description: OpenTT table tennis management plugin for competitions, clubs, players, and match data.
 * Version: 1.1.0-beta.1
 * Author: Aleksa Dimitrijević
 * Author URI: https://instagram.com/tradicije
 * License: AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if (!class_exists('OpenTT\\Unified\\Plugin')) {
    require_once __DIR__ . '/src/Plugin.php';
}

\OpenTT\Unified\Plugin::boot(__FILE__);

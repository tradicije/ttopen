<?php
/**
 * Plugin Name: OpenTT
 * Description: OpenTT sistem za vođenje i prikaz stonoteniskih takmičenja, klubova i igrača: admin meni, DB tabele i kompatibilno učitavanje postojećih shortcode modula.
 * Version: 1.0.0
 * Author: Aleksa Dimitrijević
 * Author URI: https://instagram.com/tradicije
 * License: AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-opentt-unified-core.php';

register_activation_hook(__FILE__, function () {
    STKB_Unified_Core::activate(__FILE__);
});

STKB_Unified_Core::init(__FILE__);

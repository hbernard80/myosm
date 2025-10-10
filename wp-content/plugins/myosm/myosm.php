<?php
/**
 * Plugin Name: MyOsm
 * Description: Affiche une carte OpenStreetMap via Leaflet et gère des centres d'intérêt.
 * Version: 1.0.0
 * Author: OpenAI Assistant
 * Text Domain: myosm
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MYOSM_PLUGIN_FILE', __FILE__);
define('MYOSM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MYOSM_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once MYOSM_PLUGIN_PATH . 'includes/class-myosm-plugin.php';

MyOsm_Plugin::instance();

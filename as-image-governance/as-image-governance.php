<?php
/**
 * Plugin Name: Image Governance
 * Description: Records image source, authority, usage, attribution, and lightweight collections.
 * Version: 0.1.19
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Author: AlphaSys
 * Author URI: https://alphasys.com.au
 * Update URI: https://github.com/cchatterton/as-image-governance
 * AlphaSys Controller API: 1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: as-image-governance
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ASIG_VERSION', '0.1.19');
define('ASIG_PLUGIN_FILE', __FILE__);
define('ASIG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ASIG_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ASIG_PLUGIN_DIR . 'functions/helpers.php';
require_once ASIG_PLUGIN_DIR . 'functions/setup.php';
require_once ASIG_PLUGIN_DIR . 'functions/assets.php';
require_once ASIG_PLUGIN_DIR . 'functions/admin.php';
require_once ASIG_PLUGIN_DIR . 'functions/rest.php';

register_activation_hook(ASIG_PLUGIN_FILE, 'asig_schedule_expiry_cleanup');
register_deactivation_hook(ASIG_PLUGIN_FILE, 'asig_clear_expiry_cleanup');

require_once __DIR__ . '/functions/controller-client.php';
asuc_client_register(__FILE__, 'as-image-governance');

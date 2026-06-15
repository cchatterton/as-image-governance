<?php
/**
 * Plugin Name: Image Governance
 * Plugin URI: https://github.com/cchatterton/as-image-governance
 * Description: Records image source, authority, usage, attribution, and lightweight collections.
 * Version: 0.1.16
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: AlphaSys
 * Author URI: https://alphasys.com.au
 * Text Domain: as-image-governance
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ASIG_VERSION', '0.1.16');
define('ASIG_PLUGIN_FILE', __FILE__);
define('ASIG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ASIG_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ASIG_PLUGIN_DIR . 'functions/helpers.php';
require_once ASIG_PLUGIN_DIR . 'functions/setup.php';
require_once ASIG_PLUGIN_DIR . 'functions/assets.php';
require_once ASIG_PLUGIN_DIR . 'functions/admin.php';
require_once ASIG_PLUGIN_DIR . 'functions/rest.php';
require_once ASIG_PLUGIN_DIR . 'functions/github-updater.php';

register_activation_hook(ASIG_PLUGIN_FILE, 'asig_schedule_expiry_cleanup');
register_deactivation_hook(ASIG_PLUGIN_FILE, 'asig_clear_expiry_cleanup');

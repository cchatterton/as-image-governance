<?php
/**
 * Plugin Name: Image Governance
 * Description: Records image source, authority, usage, attribution, and lightweight collections.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: AlphaSys
 * Text Domain: as-image-governance
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ASIG_VERSION', '0.1.0');
define('ASIG_PLUGIN_FILE', __FILE__);
define('ASIG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ASIG_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ASIG_PLUGIN_DIR . 'functions/helpers.php';
require_once ASIG_PLUGIN_DIR . 'functions/setup.php';
require_once ASIG_PLUGIN_DIR . 'functions/assets.php';
require_once ASIG_PLUGIN_DIR . 'functions/admin.php';
require_once ASIG_PLUGIN_DIR . 'functions/rest.php';

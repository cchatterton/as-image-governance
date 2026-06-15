<?php
/**
 * Asset loading for Image Governance.
 *
 * @package ImageGovernance
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_enqueue_scripts', 'asig_enqueue_admin_assets');
add_action('wp_enqueue_scripts', 'asig_enqueue_frontend_assets');

function asig_enqueue_admin_assets(string $hook): void
{
    if (!in_array($hook, array('upload.php', 'media.php', 'settings_page_as-image-governance', 'tools_page_as-image-governance-tools', 'edit-tags.php'), true)) {
        return;
    }

    wp_enqueue_style(
        'asig-admin',
        ASIG_PLUGIN_URL . 'styles/as-image-governance.css',
        array(),
        ASIG_VERSION
    );

    wp_enqueue_script(
        'asig-admin',
        ASIG_PLUGIN_URL . 'scripts/as-image-governance.js',
        array('jquery'),
        ASIG_VERSION,
        true
    );

    wp_localize_script(
        'asig-admin',
        'ASIG',
        array(
            'restUrl' => esc_url_raw(rest_url('asig/v1/collections/assign')),
            'nonce'   => wp_create_nonce('wp_rest'),
        )
    );
}

function asig_enqueue_frontend_assets(): void
{
    wp_enqueue_style(
        'asig-frontend',
        ASIG_PLUGIN_URL . 'styles/as-image-governance.css',
        array(),
        ASIG_VERSION
    );
}

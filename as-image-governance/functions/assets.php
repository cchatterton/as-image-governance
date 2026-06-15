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
    if (!in_array($hook, array('upload.php', 'media-new.php', 'media.php', 'post.php', 'post-new.php', 'settings_page_as-image-governance', 'edit-tags.php'), true)) {
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
        array('jquery', 'jquery-ui-draggable', 'jquery-ui-droppable'),
        ASIG_VERSION,
        true
    );

    wp_localize_script(
        'asig-admin',
        'ASIG',
        array(
            'assignCollectionUrl' => esc_url_raw(rest_url('asig/v1/collections/assign')),
            'attachmentUrl'       => esc_url_raw(rest_url('asig/v1/attachments')),
            'authorityLevels'     => asig_get_authority_levels(),
            'collections'         => asig_get_collection_options(),
            'enableCollectionUi'  => 'upload.php' === $hook,
            'nonce'               => wp_create_nonce('wp_rest'),
            'strings'             => array(
                'modalTitle'       => __('Image governance required', 'as-image-governance'),
                'modalIntro'       => __('Add governance details before this image moves further through the workflow.', 'as-image-governance'),
                'source'           => __('Source', 'as-image-governance'),
                'authorityLevel'   => __('Authority Level', 'as-image-governance'),
                'authorityNotes'   => __('Authority Notes', 'as-image-governance'),
                'attribution'      => __('Attribution', 'as-image-governance'),
                'collections'      => __('Collections', 'as-image-governance'),
                'save'             => __('Save Governance Details', 'as-image-governance'),
                'dismiss'          => __('Dismiss', 'as-image-governance'),
                'saved'            => __('Governance details saved.', 'as-image-governance'),
                'assignedTo'       => __('Assigned to %s.', 'as-image-governance'),
                'createCollection' => __('Create collections under Manage Collections.', 'as-image-governance'),
            ),
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

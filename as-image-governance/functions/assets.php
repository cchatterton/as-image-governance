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
add_action('enqueue_block_editor_assets', 'asig_enqueue_block_editor_assets');
add_action('wp_enqueue_scripts', 'asig_enqueue_frontend_assets');

function asig_enqueue_admin_assets(string $hook): void
{
    if (!in_array($hook, array('upload.php', 'media-new.php', 'media.php', 'post.php', 'post-new.php', 'settings_page_as-image-governance', 'edit-tags.php'), true)) {
        return;
    }

    asig_enqueue_admin_asset_bundle($hook);
}

function asig_enqueue_block_editor_assets(): void
{
    asig_enqueue_admin_asset_bundle('block-editor');
}

function asig_enqueue_admin_asset_bundle(string $hook): void
{
    wp_enqueue_style(
        'asig-admin',
        ASIG_PLUGIN_URL . 'styles/as-image-governance.css',
        array(),
        ASIG_VERSION
    );

    wp_enqueue_script(
        'asig-admin',
        ASIG_PLUGIN_URL . 'scripts/as-image-governance.js',
        array('jquery', 'jquery-ui-draggable', 'jquery-ui-droppable', 'wp-api-fetch'),
        ASIG_VERSION,
        true
    );

    wp_localize_script(
        'asig-admin',
        'ASIG',
        array(
            'assignCollectionUrl' => esc_url_raw(rest_url('asig/v1/collections/assign')),
            'attachmentUrl'       => esc_url_raw(rest_url('asig/v1/attachments')),
            'pendingUploadsUrl'   => esc_url_raw(rest_url('asig/v1/uploads/pending')),
            'authorityLevels'     => asig_get_authority_levels(),
            'collections'         => asig_get_collection_options(),
            'imageColors'         => asig_get_taxonomy_options('ig_image_color'),
            'imageTags'           => asig_get_taxonomy_options('ig_image_tag'),
            'enableCollectionUi'  => 'upload.php' === $hook,
            'nonce'               => wp_create_nonce('wp_rest'),
            'strings'             => array(
                'modalTitle'       => __('Image governance required', 'as-image-governance'),
                'modalIntro'       => __('Add governance details before this image moves further through the workflow.', 'as-image-governance'),
                'source'           => __('Source', 'as-image-governance'),
                'authorityLevel'   => __('Authority Level', 'as-image-governance'),
                'expiry'           => __('Expiry', 'as-image-governance'),
                'authorityNotes'   => __('Authority Notes', 'as-image-governance'),
                'attribution'      => __('Attribution', 'as-image-governance'),
                'collections'      => __('Collections', 'as-image-governance'),
                'imageColors'      => __('Image Colors', 'as-image-governance'),
                'imageTags'        => __('Image Tags', 'as-image-governance'),
                'save'             => __('Save Governance Details', 'as-image-governance'),
                'dismiss'          => __('Dismiss', 'as-image-governance'),
                'saved'            => __('Governance details saved.', 'as-image-governance'),
                'assignedTo'       => __('Assigned to %s.', 'as-image-governance'),
                'removedFromTerms' => __('Removed from collections.', 'as-image-governance'),
                'createCollection' => __('Create collections under Manage Collections.', 'as-image-governance'),
                'allAuthority'     => __('All authority levels', 'as-image-governance'),
                'allGovernance'    => __('All governance records', 'as-image-governance'),
                'missingSource'    => __('Missing Source', 'as-image-governance'),
                'missingAttribution' => __('Missing Attribution', 'as-image-governance'),
                'allCollections'   => __('All collections', 'as-image-governance'),
                'allImageColors'   => __('All image colors', 'as-image-governance'),
                'allImageTags'    => __('All image tags', 'as-image-governance'),
                'tooltips'         => array(
                    'source'         => __('Where the image came from, such as a URL, client, AI tool, photographer, or internal team.', 'as-image-governance'),
                    'authorityLevel' => __('The level of permission or confidence we have to use this image.', 'as-image-governance'),
                    'expiry'         => __('When the right to use this image ends or should be reviewed.', 'as-image-governance'),
                    'authorityNotes' => __('Details that support the authority level, including licences, approvals, or usage limits.', 'as-image-governance'),
                    'attribution'    => __('The credit, copyright, or licence wording that must be shown when the image is used.', 'as-image-governance'),
                    'imageColors'    => __("Comma-separated list of the image's primary colours for search, filtering, and design reuse.", 'as-image-governance'),
                    'imageTags'      => __('Comma-separated keywords describing the image for search and future reuse.', 'as-image-governance'),
                    'collections'    => __('Groups this image belongs to, used for organising, filtering, and reporting.', 'as-image-governance'),
                ),
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

<?php
/**
 * Optional uninstall cleanup for Image Governance.
 *
 * @package ImageGovernance
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$settings = get_option('asig_settings', array());

if (!is_array($settings) || '1' !== (string) ($settings['enable_uninstall_cleanup'] ?? '0')) {
    return;
}

delete_option('asig_settings');
delete_option('asig_usage_index');

$attachments = get_posts(
    array(
        'post_type'      => 'attachment',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    )
);

foreach ($attachments as $attachment_id) {
    delete_post_meta((int) $attachment_id, '_ig_source');
    delete_post_meta((int) $attachment_id, '_ig_authority_level');
    delete_post_meta((int) $attachment_id, '_ig_authority_notes');
    delete_post_meta((int) $attachment_id, '_ig_attribution');
}

foreach (array('ig_collection', 'ig_image_color', 'ig_subject_matter') as $taxonomy) {
    $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));

    if (is_wp_error($terms)) {
        continue;
    }

    foreach ($terms as $term) {
        wp_delete_term((int) $term->term_id, $taxonomy);
    }
}

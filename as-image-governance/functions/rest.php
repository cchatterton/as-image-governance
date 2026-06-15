<?php
/**
 * REST routes for Image Governance.
 *
 * @package ImageGovernance
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'asig_register_rest_routes');

function asig_register_rest_routes(): void
{
    register_rest_route(
        'asig/v1',
        '/collections/assign',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'asig_rest_assign_collection',
            'permission_callback' => 'asig_rest_upload_permission',
            'args'                => array(
                'attachment_id' => array(
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ),
                'collection_id' => array(
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                ),
            ),
        )
    );
}

function asig_rest_upload_permission(): bool
{
    return current_user_can('upload_files');
}

function asig_rest_assign_collection(WP_REST_Request $request): WP_REST_Response|WP_Error
{
    $attachment_id = (int) $request->get_param('attachment_id');
    $collection_id = (int) $request->get_param('collection_id');

    if (!asig_is_image_attachment($attachment_id)) {
        return new WP_Error('asig_invalid_attachment', __('Attachment must be an image.', 'as-image-governance'), array('status' => 400));
    }

    $term = get_term($collection_id, 'ig_collection');

    if (!$term || is_wp_error($term)) {
        return new WP_Error('asig_invalid_collection', __('Collection not found.', 'as-image-governance'), array('status' => 404));
    }

    wp_set_object_terms($attachment_id, array($collection_id), 'ig_collection', true);

    return rest_ensure_response(
        array(
            'attachment_id' => $attachment_id,
            'collection_id' => $collection_id,
            'message'       => __('Image assigned to collection.', 'as-image-governance'),
        )
    );
}

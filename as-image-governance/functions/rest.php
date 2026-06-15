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

    register_rest_route(
        'asig/v1',
        '/attachments/(?P<attachment_id>\d+)',
        array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => 'asig_rest_get_attachment_governance',
                'permission_callback' => 'asig_rest_upload_permission',
                'args'                => array(
                    'attachment_id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => 'asig_rest_save_attachment_governance',
                'permission_callback' => 'asig_rest_upload_permission',
                'args'                => array(
                    'attachment_id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            ),
        )
    );

    register_rest_route(
        'asig/v1',
        '/uploads/pending',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'asig_rest_get_pending_upload',
            'permission_callback' => 'asig_rest_upload_permission',
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

    if (0 === $collection_id) {
        wp_set_object_terms($attachment_id, array(), 'ig_collection', false);

        return rest_ensure_response(
            array(
                'attachment_id' => $attachment_id,
                'collection_id' => 0,
                'message'       => __('Image removed from collections.', 'as-image-governance'),
            )
        );
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

function asig_rest_get_attachment_governance(WP_REST_Request $request): WP_REST_Response|WP_Error
{
    $attachment_id = (int) $request->get_param('attachment_id');

    if (!asig_is_image_attachment($attachment_id)) {
        return new WP_Error('asig_invalid_attachment', __('Attachment must be an image.', 'as-image-governance'), array('status' => 400));
    }

    return rest_ensure_response(asig_prepare_attachment_governance_response($attachment_id));
}

function asig_rest_save_attachment_governance(WP_REST_Request $request): WP_REST_Response|WP_Error
{
    $attachment_id = (int) $request->get_param('attachment_id');

    if (!asig_is_image_attachment($attachment_id)) {
        return new WP_Error('asig_invalid_attachment', __('Attachment must be an image.', 'as-image-governance'), array('status' => 400));
    }

    update_post_meta($attachment_id, '_ig_source', sanitize_text_field((string) $request->get_param('source')));
    update_post_meta($attachment_id, '_ig_authority_level', asig_sanitize_authority_level($request->get_param('authority_level')));
    update_post_meta($attachment_id, '_ig_expiry_date', asig_sanitize_expiry_date($request->get_param('expiry_date')));
    update_post_meta($attachment_id, '_ig_authority_notes', sanitize_text_field((string) $request->get_param('authority_notes')));
    update_post_meta($attachment_id, '_ig_attribution', sanitize_text_field((string) $request->get_param('attribution')));

    $collections = $request->get_param('collections');

    if (is_array($collections)) {
        wp_set_object_terms($attachment_id, array_map('absint', $collections), 'ig_collection', false);
    }

    asig_rest_save_term_names($attachment_id, 'ig_image_color', $request->get_param('image_colors'));
    asig_rest_save_term_names($attachment_id, 'ig_image_tag', $request->get_param('image_tags'));

    return rest_ensure_response(asig_prepare_attachment_governance_response($attachment_id));
}

function asig_rest_save_term_names(int $attachment_id, string $taxonomy, mixed $value): void
{
    if (null === $value) {
        return;
    }

    if (is_array($value)) {
        $terms = asig_normalize_tag_terms($value);
    } else {
        $terms = asig_normalize_tag_terms($value);
    }

    wp_set_object_terms($attachment_id, $terms, $taxonomy, false);
}

function asig_rest_get_pending_upload(): WP_REST_Response
{
    $user_id = get_current_user_id();
    $pending = $user_id ? get_user_meta($user_id, 'asig_pending_uploads', true) : array();
    $pending = is_array($pending) ? array_values(array_unique(array_filter(array_map('absint', $pending)))) : array();

    while ($pending) {
        $attachment_id = array_shift($pending);

        if (asig_is_image_attachment($attachment_id)) {
            if ($user_id) {
                update_user_meta($user_id, 'asig_pending_uploads', $pending);
            }

            return rest_ensure_response(
                array(
                    'attachment_id' => $attachment_id,
                )
            );
        }
    }

    if ($user_id) {
        delete_user_meta($user_id, 'asig_pending_uploads');
    }

    return rest_ensure_response(
        array(
            'attachment_id' => 0,
        )
    );
}

function asig_prepare_attachment_governance_response(int $attachment_id): array
{
    $metadata = asig_get_attachment_governance($attachment_id);
    $metadata['attachment_id'] = $attachment_id;
    $metadata['collections'] = asig_get_attachment_collection_ids($attachment_id);
    $metadata['image_colors'] = asig_get_attachment_term_names($attachment_id, 'ig_image_color');
    $metadata['image_tags'] = asig_get_attachment_term_names($attachment_id, 'ig_image_tag');
    $metadata['usage_count'] = asig_get_attachment_usage_count($attachment_id);
    $metadata['needs_governance'] = '' === trim($metadata['source'])
        || '' === trim($metadata['attribution'])
        || '' === trim($metadata['authority_level'])
        || '0' === (string) $metadata['authority_level'];

    return $metadata;
}

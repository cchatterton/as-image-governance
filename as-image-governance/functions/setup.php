<?php
/**
 * Setup hooks for Image Governance.
 *
 * @package ImageGovernance
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'asig_register_collection_taxonomy');
add_filter('attachment_fields_to_edit', 'asig_add_attachment_fields', 10, 2);
add_filter('attachment_fields_to_save', 'asig_save_attachment_fields', 10, 2);
add_filter('the_content', 'asig_append_attribution_page_content', 20);
add_action('wp_footer', 'asig_render_footer_attribution_link');
add_action('save_post', 'asig_update_usage_for_saved_post', 20, 2);
add_action('delete_post', 'asig_remove_deleted_post_usage');

function asig_register_collection_taxonomy(): void
{
    register_taxonomy(
        'ig_collection',
        'attachment',
        array(
            'labels'            => array(
                'name'          => __('Image Collections', 'as-image-governance'),
                'singular_name' => __('Image Collection', 'as-image-governance'),
                'search_items'  => __('Search Image Collections', 'as-image-governance'),
                'all_items'     => __('All Image Collections', 'as-image-governance'),
                'edit_item'     => __('Edit Image Collection', 'as-image-governance'),
                'update_item'   => __('Update Image Collection', 'as-image-governance'),
                'add_new_item'  => __('Add New Image Collection', 'as-image-governance'),
                'new_item_name' => __('New Image Collection Name', 'as-image-governance'),
                'menu_name'     => __('Image Collections', 'as-image-governance'),
            ),
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => false,
            'show_in_menu'      => true,
            'hierarchical'      => false,
            'show_in_rest'      => true,
            'capabilities'      => array(
                'manage_terms' => 'upload_files',
                'edit_terms'   => 'upload_files',
                'delete_terms' => 'upload_files',
                'assign_terms' => 'upload_files',
            ),
        )
    );
}

function asig_add_attachment_fields(array $fields, WP_Post $post): array
{
    if (!asig_is_image_attachment((int) $post->ID) || !current_user_can('upload_files')) {
        return $fields;
    }

    $metadata = asig_get_attachment_governance((int) $post->ID);

    $authority_options = '';
    foreach (asig_get_authority_levels() as $value => $label) {
        $authority_options .= sprintf(
            '<option value="%s"%s>%s</option>',
            esc_attr($value),
            selected($metadata['authority_level'], $value, false),
            esc_html($label)
        );
    }

    $fields['asig_source'] = array(
        'label' => __('Source', 'as-image-governance'),
        'input' => 'html',
        'html'  => sprintf(
            '<input type="text" class="widefat" name="attachments[%1$d][_ig_source]" value="%2$s">',
            (int) $post->ID,
            esc_attr($metadata['source'])
        ),
    );

    $fields['asig_authority_level'] = array(
        'label' => __('Authority Level', 'as-image-governance'),
        'input' => 'html',
        'html'  => sprintf(
            '<select class="widefat" name="attachments[%1$d][_ig_authority_level]">%2$s</select>',
            (int) $post->ID,
            $authority_options
        ),
    );

    $fields['asig_authority_notes'] = array(
        'label' => __('Authority Notes', 'as-image-governance'),
        'input' => 'html',
        'html'  => sprintf(
            '<textarea class="widefat" rows="4" name="attachments[%1$d][_ig_authority_notes]">%2$s</textarea>',
            (int) $post->ID,
            esc_textarea($metadata['authority_notes'])
        ),
    );

    $fields['asig_attribution'] = array(
        'label' => __('Attribution', 'as-image-governance'),
        'input' => 'html',
        'html'  => sprintf(
            '<textarea class="widefat" rows="4" name="attachments[%1$d][_ig_attribution]">%2$s</textarea>',
            (int) $post->ID,
            esc_textarea($metadata['attribution'])
        ),
    );

    $fields['asig_collections'] = array(
        'label' => __('Collections', 'as-image-governance'),
        'input' => 'html',
        'html'  => asig_render_attachment_collection_checkboxes((int) $post->ID),
    );

    $fields['asig_usage'] = array(
        'label' => __('Usage', 'as-image-governance'),
        'input' => 'html',
        'html'  => sprintf(
            '<p><strong>%1$d</strong> %2$s</p><p><a class="button" href="%3$s">%4$s</a></p>',
            asig_get_attachment_usage_count((int) $post->ID),
            esc_html__('known uses. Recount Usage rebuilds this from site content.', 'as-image-governance'),
            esc_url(asig_get_recount_url()),
            esc_html__('Recount Usage', 'as-image-governance')
        ),
    );

    return $fields;
}

function asig_render_attachment_collection_checkboxes(int $attachment_id): string
{
    $collections = asig_get_collection_options();
    $selected = asig_get_attachment_collection_ids($attachment_id);

    if (!$collections) {
        return sprintf(
            '<p>%1$s <a href="%2$s">%3$s</a></p>',
            esc_html__('No collections exist yet.', 'as-image-governance'),
            esc_url(admin_url('edit-tags.php?taxonomy=ig_collection&post_type=attachment')),
            esc_html__('Create one', 'as-image-governance')
        );
    }

    $html = '<fieldset class="asig-attachment-collections">';

    foreach ($collections as $collection) {
        $html .= sprintf(
            '<label><input type="checkbox" name="attachments[%1$d][ig_collection][]" value="%2$d"%3$s> %4$s</label>',
            $attachment_id,
            (int) $collection['id'],
            checked(in_array((int) $collection['id'], $selected, true), true, false),
            esc_html($collection['name'])
        );
    }

    $html .= sprintf(
        '<p><a href="%1$s">%2$s</a></p>',
        esc_url(admin_url('edit-tags.php?taxonomy=ig_collection&post_type=attachment')),
        esc_html__('Manage collections', 'as-image-governance')
    );
    $html .= '</fieldset>';

    return $html;
}

function asig_save_attachment_fields(array $post, array $attachment): array
{
    $attachment_id = (int) ($post['ID'] ?? 0);

    if (!$attachment_id || !asig_is_image_attachment($attachment_id) || !current_user_can('upload_files')) {
        return $post;
    }

    $text_fields = array('_ig_source', '_ig_attribution');

    foreach ($text_fields as $field) {
        if (isset($attachment[$field])) {
            update_post_meta($attachment_id, $field, sanitize_text_field($attachment[$field]));
        }
    }

    if (isset($attachment['_ig_authority_level'])) {
        update_post_meta($attachment_id, '_ig_authority_level', asig_sanitize_authority_level($attachment['_ig_authority_level']));
    }

    if (isset($attachment['_ig_authority_notes'])) {
        update_post_meta($attachment_id, '_ig_authority_notes', sanitize_textarea_field($attachment['_ig_authority_notes']));
    }

    if (isset($attachment['ig_collection']) && is_array($attachment['ig_collection'])) {
        wp_set_object_terms($attachment_id, array_map('absint', $attachment['ig_collection']), 'ig_collection', false);
    } else {
        wp_set_object_terms($attachment_id, array(), 'ig_collection', false);
    }

    return $post;
}

function asig_update_usage_for_saved_post(int $post_id, WP_Post $post): void
{
    if (wp_is_post_revision($post_id) || 'attachment' === $post->post_type || !in_array($post->post_status, array('publish', 'future', 'draft', 'pending', 'private'), true)) {
        return;
    }

    $post_types = get_post_types(array('public' => true), 'names');

    if (!in_array($post->post_type, $post_types, true)) {
        return;
    }

    $usage = asig_get_usage_index();
    asig_remove_post_from_usage_index($usage, $post_id);
    asig_add_featured_image_usage($usage, $post_id);
    asig_add_content_image_usage($usage, $post_id);
    update_option('asig_usage_index', $usage, false);
}

function asig_remove_deleted_post_usage(int $post_id): void
{
    $usage = asig_get_usage_index();
    asig_remove_post_from_usage_index($usage, $post_id);
    update_option('asig_usage_index', $usage, false);
}

function asig_remove_post_from_usage_index(array &$usage, int $post_id): void
{
    foreach ($usage as $attachment_id => $items) {
        if (!is_array($items)) {
            unset($usage[$attachment_id]);
            continue;
        }

        $usage[$attachment_id] = array_values(
            array_filter(
                $items,
                static function (array $item) use ($post_id): bool {
                    return (int) ($item['post_id'] ?? 0) !== $post_id;
                }
            )
        );

        if (!$usage[$attachment_id]) {
            unset($usage[$attachment_id]);
        }
    }
}

function asig_append_attribution_page_content(string $content): string
{
    if (!is_singular('page') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    $settings = asig_get_settings();
    $page_id = (int) $settings['attribution_page_id'];

    if (!$page_id || get_queried_object_id() !== $page_id) {
        return $content;
    }

    return $content . asig_render_attribution_details();
}

function asig_render_attribution_details(): string
{
    $ref = isset($_GET['ref']) ? sanitize_text_field(wp_unslash($_GET['ref'])) : '';

    if ('' === trim($ref)) {
        return '<div class="asig-attribution-list"><p>' . esc_html__('Image attribution details are shown when this page is opened from a page with attributed images.', 'as-image-governance') . '</p></div>';
    }

    $post = asig_find_post_by_path($ref);

    if (!$post) {
        return '<div class="asig-attribution-list"><p>' . esc_html__('No image attribution records were found for this page.', 'as-image-governance') . '</p></div>';
    }

    $rows = array();
    foreach (asig_get_usage_index() as $attachment_id => $items) {
        $attribution = trim((string) get_post_meta((int) $attachment_id, '_ig_attribution', true));

        if ('' === $attribution || !is_array($items)) {
            continue;
        }

        foreach ($items as $item) {
            if ((int) ($item['post_id'] ?? 0) !== (int) $post->ID) {
                continue;
            }

            $rows[] = array(
                'attachment_id' => (int) $attachment_id,
                'usage_type'    => (string) ($item['usage_type'] ?? ''),
                'post_type'     => asig_get_post_type_label($item['post_type'] ?? $post->post_type),
                'source'        => (string) get_post_meta((int) $attachment_id, '_ig_source', true),
                'attribution'   => $attribution,
            );
        }
    }

    if (!$rows) {
        return '<div class="asig-attribution-list"><p>' . esc_html__('No image attribution records were found for this page.', 'as-image-governance') . '</p></div>';
    }

    ob_start();
    ?>
    <div class="asig-attribution-list">
        <?php foreach ($rows as $row) : ?>
            <article class="asig-attribution-item">
                <div class="asig-attribution-thumb">
                    <?php echo wp_get_attachment_image((int) $row['attachment_id'], 'thumbnail'); ?>
                </div>
                <div class="asig-attribution-content">
                    <p><strong><?php esc_html_e('Used on:', 'as-image-governance'); ?></strong> <?php echo esc_html(get_the_title($post)); ?></p>
                    <p><strong><?php esc_html_e('Post type:', 'as-image-governance'); ?></strong> <?php echo esc_html($row['post_type']); ?></p>
                    <p><strong><?php esc_html_e('Usage:', 'as-image-governance'); ?></strong> <?php echo esc_html($row['usage_type']); ?></p>
                    <p><strong><?php esc_html_e('Source:', 'as-image-governance'); ?></strong> <?php echo esc_html($row['source']); ?></p>
                    <p><strong><?php esc_html_e('Attribution:', 'as-image-governance'); ?></strong> <?php echo esc_html($row['attribution']); ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

function asig_render_footer_attribution_link(): void
{
    $settings = asig_get_settings();

    if ('1' !== (string) $settings['enable_footer_link'] || !asig_current_page_has_attributed_images()) {
        return;
    }

    $page_id = (int) $settings['attribution_page_id'];
    $url = $page_id ? get_permalink($page_id) : '';

    if (!$url) {
        return;
    }

    $label = trim((string) $settings['footer_link_label']);
    $label = '' !== $label ? $label : __('Image Attribution', 'as-image-governance');
    $url = add_query_arg('ref', rawurlencode(asig_get_current_request_path()), $url);

    printf(
        '<p class="asig-footer-attribution-link"><a href="%s">%s</a></p>',
        esc_url($url),
        esc_html($label)
    );
}

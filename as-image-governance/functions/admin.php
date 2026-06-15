<?php
/**
 * Admin features for Image Governance.
 *
 * @package ImageGovernance
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'asig_register_admin_pages');
add_action('admin_init', 'asig_handle_settings_save');
add_action('admin_post_asig_scan_usage', 'asig_handle_usage_scan');
add_filter('manage_upload_columns', 'asig_add_media_columns');
add_action('manage_media_custom_column', 'asig_render_media_column', 10, 2);
add_action('restrict_manage_posts', 'asig_render_media_filters');
add_action('pre_get_posts', 'asig_filter_media_library');
add_filter('bulk_actions-upload', 'asig_register_bulk_actions');
add_filter('handle_bulk_actions-upload', 'asig_handle_bulk_actions', 10, 3);
add_action('admin_notices', 'asig_render_admin_notices');
add_action('admin_notices', 'asig_render_media_library_governance_panel');
add_filter('manage_edit-ig_collection_columns', 'asig_filter_collection_columns');
add_filter('manage_ig_collection_custom_column', 'asig_render_collection_column', 10, 3);

function asig_register_admin_pages(): void
{
    add_options_page(
        __('Image Governance', 'as-image-governance'),
        __('Image Governance', 'as-image-governance'),
        'manage_options',
        'as-image-governance',
        'asig_render_settings_page'
    );

}

function asig_handle_settings_save(): void
{
    if (!isset($_POST['asig_settings_nonce']) || !current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('asig_save_settings', 'asig_settings_nonce');

    $settings = array(
        'enable_footer_link'       => isset($_POST['enable_footer_link']) ? '1' : '0',
        'attribution_page_id'      => isset($_POST['attribution_page_id']) ? absint($_POST['attribution_page_id']) : 0,
        'footer_link_label'        => isset($_POST['footer_link_label']) ? sanitize_text_field(wp_unslash($_POST['footer_link_label'])) : '',
        'scan_public_post_types'   => isset($_POST['scan_public_post_types']) ? '1' : '0',
        'enable_uninstall_cleanup' => isset($_POST['enable_uninstall_cleanup']) ? '1' : '0',
    );

    asig_update_settings($settings);

    wp_safe_redirect(add_query_arg('asig_notice', 'settings_saved', wp_get_referer() ?: admin_url('options-general.php?page=as-image-governance')));
    exit;
}

function asig_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'as-image-governance'));
    }

    $settings = asig_get_settings();
    ?>
    <div class="wrap asig-admin-wrap">
        <h1><?php esc_html_e('Image Governance', 'as-image-governance'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('asig_save_settings', 'asig_settings_nonce'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable footer attribution link', 'as-image-governance'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_footer_link" value="1" <?php checked($settings['enable_footer_link'], '1'); ?>>
                            <?php esc_html_e('Show an attribution link when the current page has attributed images.', 'as-image-governance'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="asig-attribution-page"><?php esc_html_e('Attribution page', 'as-image-governance'); ?></label></th>
                    <td>
                        <?php
                        wp_dropdown_pages(
                            array(
                                'name'              => 'attribution_page_id',
                                'id'                => 'asig-attribution-page',
                                'selected'          => (int) $settings['attribution_page_id'],
                                'show_option_none'  => __('Select a page', 'as-image-governance'),
                                'option_none_value' => 0,
                            )
                        );
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="asig-footer-link-label"><?php esc_html_e('Footer link label', 'as-image-governance'); ?></label></th>
                    <td><input type="text" class="regular-text" id="asig-footer-link-label" name="footer_link_label" value="<?php echo esc_attr($settings['footer_link_label']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Enable scanner for public CPTs', 'as-image-governance'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="scan_public_post_types" value="1" <?php checked($settings['scan_public_post_types'], '1'); ?>>
                            <?php esc_html_e('Include public custom post type content in usage scans.', 'as-image-governance'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Authority level labels', 'as-image-governance'); ?></th>
                    <td>
                        <ul class="asig-authority-label-list">
                            <?php foreach (asig_get_authority_levels() as $value => $label) : ?>
                                <li><?php echo esc_html($value . ' - ' . $label); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Uninstall cleanup', 'as-image-governance'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_uninstall_cleanup" value="1" <?php checked($settings['enable_uninstall_cleanup'], '1'); ?>>
                            <?php esc_html_e('Delete Image Governance metadata and options during uninstall.', 'as-image-governance'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save Settings', 'as-image-governance')); ?>
        </form>
    </div>
    <?php
}

function asig_render_media_library_governance_panel(): void
{
    $screen = get_current_screen();

    if (!$screen || 'upload' !== $screen->id || !current_user_can('upload_files')) {
        return;
    }

    $collections = asig_get_collection_options();
    ?>
    <div class="notice asig-media-governance-panel">
        <div class="asig-media-governance-panel__collections" aria-label="<?php esc_attr_e('Collection drop targets', 'as-image-governance'); ?>">
            <?php if ($collections) : ?>
                <?php foreach ($collections as $collection) : ?>
                    <button type="button" class="button asig-collection-drop-target" data-collection-id="<?php echo esc_attr((string) $collection['id']); ?>">
                        <?php echo esc_html($collection['name']); ?>
                    </button>
                <?php endforeach; ?>
                <span class="asig-assignment-status" aria-live="polite"></span>
            <?php else : ?>
                <span class="asig-assignment-status"></span>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function asig_add_media_columns(array $columns): array
{
    $columns['asig_source'] = __('Source', 'as-image-governance');
    $columns['asig_authority'] = __('Authority', 'as-image-governance');
    $columns['asig_attribution'] = __('Attribution', 'as-image-governance');
    $columns['asig_usage_count'] = sprintf(
        '%s <a class="asig-recount-link" href="%s" title="%s" aria-label="%s"><span class="dashicons dashicons-update"></span></a>',
        esc_html__('Usage Count', 'as-image-governance'),
        esc_url(asig_get_recount_url()),
        esc_attr__('Recount usage', 'as-image-governance'),
        esc_attr__('Recount usage', 'as-image-governance')
    );
    $columns['asig_collections'] = __('Collections', 'as-image-governance');

    return $columns;
}

function asig_filter_collection_columns(array $columns): array
{
    unset($columns['posts']);
    $columns['asig_image_count'] = __('Count', 'as-image-governance');

    return $columns;
}

function asig_render_collection_column(string $content, string $column_name, int $term_id): string
{
    if ('asig_image_count' !== $column_name) {
        return $content;
    }

    $attachment_ids = get_objects_in_term($term_id, 'ig_collection');
    $count = is_wp_error($attachment_ids) ? 0 : count(array_unique(array_map('intval', $attachment_ids)));
    $url = add_query_arg(
        array(
            'mode'            => 'list',
            'asig_collection' => $term_id,
        ),
        admin_url('upload.php')
    );

    return sprintf('<a href="%s">%d</a>', esc_url($url), (int) $count);
}

function asig_render_media_column(string $column_name, int $post_id): void
{
    if (!asig_is_image_attachment($post_id)) {
        echo '&mdash;';
        return;
    }

    if ('asig_source' === $column_name) {
        echo esc_html((string) get_post_meta($post_id, '_ig_source', true));
    } elseif ('asig_authority' === $column_name) {
        echo esc_html(asig_get_authority_label(get_post_meta($post_id, '_ig_authority_level', true)));
    } elseif ('asig_attribution' === $column_name) {
        echo esc_html(wp_trim_words((string) get_post_meta($post_id, '_ig_attribution', true), 12));
    } elseif ('asig_usage_count' === $column_name) {
        echo esc_html((string) asig_get_attachment_usage_count($post_id));
        asig_render_attachment_usage_details($post_id);
    } elseif ('asig_collections' === $column_name) {
        echo get_the_term_list($post_id, 'ig_collection', '', ', ') ?: '&mdash;';
    }
}

function asig_render_attachment_usage_details(int $post_id): void
{
    $usage = asig_get_attachment_usage($post_id);

    if (!$usage) {
        return;
    }

    echo '<ul class="asig-usage-list">';
    foreach ($usage as $item) {
        $used_post_id = (int) ($item['post_id'] ?? 0);
        if (!$used_post_id) {
            continue;
        }

        printf(
            '<li>%s, %s: <a href="%s">%s</a> <a href="%s">%s</a></li>',
            esc_html((string) ($item['usage_type'] ?? '')),
            esc_html(asig_get_post_type_label($item['post_type'] ?? get_post_type($used_post_id))),
            esc_url(get_edit_post_link($used_post_id, '')),
            esc_html(get_the_title($used_post_id)),
            esc_url(get_permalink($used_post_id)),
            esc_html__('View', 'as-image-governance')
        );
    }
    echo '</ul>';
}

function asig_render_media_filters(string $post_type): void
{
    if ('attachment' !== $post_type) {
        return;
    }

    $selected_authority = isset($_GET['asig_authority_level']) ? sanitize_text_field(wp_unslash($_GET['asig_authority_level'])) : '';
    $missing = isset($_GET['asig_missing']) ? sanitize_text_field(wp_unslash($_GET['asig_missing'])) : '';
    $collection = isset($_GET['asig_collection']) ? absint($_GET['asig_collection']) : 0;
    ?>
    <select name="asig_authority_level">
        <option value=""><?php esc_html_e('All authority levels', 'as-image-governance'); ?></option>
        <?php foreach (asig_get_authority_levels() as $value => $label) : ?>
            <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_authority, $value); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
    </select>
    <select name="asig_missing">
        <option value=""><?php esc_html_e('All governance records', 'as-image-governance'); ?></option>
        <option value="source" <?php selected($missing, 'source'); ?>><?php esc_html_e('Missing Source', 'as-image-governance'); ?></option>
        <option value="attribution" <?php selected($missing, 'attribution'); ?>><?php esc_html_e('Missing Attribution', 'as-image-governance'); ?></option>
    </select>
    <?php
    wp_dropdown_categories(
        array(
            'taxonomy'          => 'ig_collection',
            'name'              => 'asig_collection',
            'show_option_all'   => __('All collections', 'as-image-governance'),
            'hide_empty'        => false,
            'selected'          => $collection,
            'value_field'       => 'term_id',
            'hierarchical'      => false,
        )
    );
}

function asig_filter_media_library(WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query() || 'attachment' !== $query->get('post_type')) {
        return;
    }

    $meta_query = (array) $query->get('meta_query');

    if (isset($_GET['asig_authority_level']) && '' !== $_GET['asig_authority_level']) {
        $meta_query[] = array(
            'key'   => '_ig_authority_level',
            'value' => asig_sanitize_authority_level(wp_unslash($_GET['asig_authority_level'])),
        );
    }

    $missing_filter = isset($_GET['asig_missing']) ? sanitize_text_field(wp_unslash($_GET['asig_missing'])) : '';

    if ('source' === $missing_filter) {
        $meta_query[] = array(
            'relation' => 'OR',
            array('key' => '_ig_source', 'compare' => 'NOT EXISTS'),
            array('key' => '_ig_source', 'value' => '', 'compare' => '='),
        );
    }

    if ('attribution' === $missing_filter) {
        $meta_query[] = array(
            'relation' => 'OR',
            array('key' => '_ig_attribution', 'compare' => 'NOT EXISTS'),
            array('key' => '_ig_attribution', 'value' => '', 'compare' => '='),
        );
    }

    if ($meta_query) {
        $query->set('meta_query', $meta_query);
    }

    if (isset($_GET['asig_collection']) && absint($_GET['asig_collection'])) {
        $query->set(
            'tax_query',
            array(
                array(
                    'taxonomy' => 'ig_collection',
                    'field'    => 'term_id',
                    'terms'    => absint($_GET['asig_collection']),
                ),
            )
        );
    }
}

function asig_register_bulk_actions(array $actions): array
{
    foreach (asig_get_authority_levels() as $value => $label) {
        $actions['asig_authority_' . $value] = sprintf(
            /* translators: %s: authority label. */
            __('Set authority: %s', 'as-image-governance'),
            $label
        );
    }

    $terms = get_terms(array('taxonomy' => 'ig_collection', 'hide_empty' => false));
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            $actions['asig_collection_' . $term->term_id] = sprintf(
                /* translators: %s: collection name. */
                __('Add to collection: %s', 'as-image-governance'),
                $term->name
            );
        }
    }

    return $actions;
}

function asig_handle_bulk_actions(string $redirect_url, string $action, array $post_ids): string
{
    if (!current_user_can('upload_files')) {
        return $redirect_url;
    }

    if (str_starts_with($action, 'asig_authority_')) {
        $level = asig_sanitize_authority_level(str_replace('asig_authority_', '', $action));
        $updated = 0;

        foreach ($post_ids as $post_id) {
            if (asig_is_image_attachment((int) $post_id)) {
                update_post_meta((int) $post_id, '_ig_authority_level', $level);
                $updated++;
            }
        }

        return add_query_arg('asig_bulk_authority', $updated, $redirect_url);
    }

    if (str_starts_with($action, 'asig_collection_')) {
        $term_id = absint(str_replace('asig_collection_', '', $action));
        $updated = 0;

        foreach ($post_ids as $post_id) {
            if (asig_is_image_attachment((int) $post_id)) {
                wp_set_object_terms((int) $post_id, array($term_id), 'ig_collection', true);
                $updated++;
            }
        }

        return add_query_arg('asig_bulk_collection', $updated, $redirect_url);
    }

    return $redirect_url;
}

function asig_handle_usage_scan(): void
{
    if (!current_user_can('upload_files')) {
        wp_die(esc_html__('You do not have permission to scan image usage.', 'as-image-governance'));
    }

    check_admin_referer('asig_scan_usage', 'asig_scan_usage_nonce');

    $result = asig_scan_usage();

    $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : admin_url('upload.php');

    wp_safe_redirect(add_query_arg(array('asig_notice' => 'scan_complete', 'asig_usage_count' => $result['usage_count']), $redirect_to));
    exit;
}

function asig_scan_usage(): array
{
    $settings = asig_get_settings();
    $post_types = array('post', 'page');

    if ('1' === (string) $settings['scan_public_post_types']) {
        $public_types = get_post_types(array('public' => true), 'names');
        unset($public_types['attachment']);
        $post_types = array_values(array_unique(array_merge($post_types, $public_types)));
    }

    $query = new WP_Query(
        array(
            'post_type'      => $post_types,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );

    $usage = array();

    foreach ($query->posts as $post_id) {
        asig_add_featured_image_usage($usage, (int) $post_id);
        asig_add_content_image_usage($usage, (int) $post_id);
    }

    update_option('asig_usage_index', $usage, false);

    return array(
        'image_count' => count($usage),
        'usage_count' => array_sum(array_map('count', $usage)),
    );
}

function asig_add_featured_image_usage(array &$usage, int $post_id): void
{
    $thumbnail_id = (int) get_post_thumbnail_id($post_id);

    if ($thumbnail_id) {
        asig_add_usage_item($usage, $thumbnail_id, $post_id, __('Featured image', 'as-image-governance'));
    }
}

function asig_add_content_image_usage(array &$usage, int $post_id): void
{
    $content = (string) get_post_field('post_content', $post_id);

    if ('' === $content) {
        return;
    }

    if (preg_match_all('/wp-image-([0-9]+)/', $content, $matches)) {
        foreach (array_unique($matches[1]) as $attachment_id) {
            asig_add_usage_item($usage, (int) $attachment_id, $post_id, __('Inline image', 'as-image-governance'));
        }
    }

    if (has_shortcode($content, 'gallery')) {
        preg_match_all('/\[gallery[^\]]*ids=["\']([^"\']+)["\'][^\]]*\]/', $content, $gallery_matches);
        foreach ($gallery_matches[1] ?? array() as $ids) {
            foreach (array_filter(array_map('absint', explode(',', $ids))) as $attachment_id) {
                asig_add_usage_item($usage, $attachment_id, $post_id, __('Gallery image', 'as-image-governance'));
            }
        }
    }
}

function asig_add_usage_item(array &$usage, int $attachment_id, int $post_id, string $usage_type): void
{
    if (!$attachment_id || !asig_is_image_attachment($attachment_id)) {
        return;
    }

    $key = (string) $attachment_id;
    $usage[$key] = $usage[$key] ?? array();

    foreach ($usage[$key] as $item) {
        if ((int) $item['post_id'] === $post_id && (string) $item['usage_type'] === $usage_type) {
            return;
        }
    }

    $usage[$key][] = array(
        'post_id'    => $post_id,
        'post_type'  => get_post_type($post_id),
        'usage_type' => $usage_type,
    );
}

function asig_render_admin_notices(): void
{
    if (isset($_GET['asig_notice']) && 'settings_saved' === $_GET['asig_notice']) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Image Governance settings saved.', 'as-image-governance') . '</p></div>';
    }

    if (isset($_GET['asig_notice']) && 'scan_complete' === $_GET['asig_notice']) {
        $count = isset($_GET['asig_usage_count']) ? absint($_GET['asig_usage_count']) : 0;
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: %d: usage count. */
                __('Image usage scan complete. %d usage records found.', 'as-image-governance'),
                $count
            ))
        );
    }

    if (isset($_GET['asig_bulk_authority'])) {
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: %d: image count. */
                __('Authority level updated for %d images.', 'as-image-governance'),
                absint($_GET['asig_bulk_authority'])
            ))
        );
    }

    if (isset($_GET['asig_bulk_collection'])) {
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: %d: image count. */
                __('Collection assigned for %d images.', 'as-image-governance'),
                absint($_GET['asig_bulk_collection'])
            ))
        );
    }
}

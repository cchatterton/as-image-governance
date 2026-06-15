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
add_filter('ajax_query_attachments_args', 'asig_filter_media_modal_attachments');
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
    $current_collection = isset($_GET['asig_collection']) ? absint($_GET['asig_collection']) : 0;
    $current_mode = isset($_GET['mode']) ? sanitize_key(wp_unslash($_GET['mode'])) : get_user_option('media_library_mode', get_current_user_id());
    $current_mode = in_array($current_mode, array('grid', 'list'), true) ? $current_mode : 'grid';
    $all_media_url = add_query_arg(array('mode' => $current_mode), admin_url('upload.php'));
    ?>
    <div class="notice asig-media-governance-panel">
        <div class="asig-media-governance-panel__collections" aria-label="<?php esc_attr_e('Collection drop targets', 'as-image-governance'); ?>">
            <button
                type="button"
                class="button asig-collection-drop-target <?php echo 0 === $current_collection ? 'is-active' : ''; ?>"
                data-collection-id="0"
                data-filter-url="<?php echo esc_url($all_media_url); ?>"
                aria-pressed="<?php echo 0 === $current_collection ? 'true' : 'false'; ?>"
            >
                <?php esc_html_e('All Media', 'as-image-governance'); ?>
            </button>
            <?php if ($collections) : ?>
                <?php foreach ($collections as $collection) : ?>
                    <?php
                    $filter_url = add_query_arg(
                        array(
                            'mode'            => $current_mode,
                            'asig_collection' => (int) $collection['id'],
                        ),
                        admin_url('upload.php')
                    );
                    ?>
                    <button
                        type="button"
                        class="button asig-collection-drop-target <?php echo $current_collection === (int) $collection['id'] ? 'is-active' : ''; ?>"
                        data-collection-id="<?php echo esc_attr((string) $collection['id']); ?>"
                        data-filter-url="<?php echo esc_url($filter_url); ?>"
                        aria-pressed="<?php echo $current_collection === (int) $collection['id'] ? 'true' : 'false'; ?>"
                    >
                        <?php echo esc_html($collection['name']); ?>
                    </button>
                <?php endforeach; ?>
            <?php endif; ?>
            <span class="asig-assignment-status" aria-live="polite"></span>
        </div>
    </div>
    <?php
}

function asig_add_media_columns(array $columns): array
{
    $columns['asig_source'] = __('Source', 'as-image-governance');
    $columns['asig_authority'] = __('Authority', 'as-image-governance');
    $columns['asig_attribution'] = __('Attribution', 'as-image-governance');
    $columns['asig_usage'] = sprintf(
        '%s <button type="button" class="asig-recount-link" data-recount-url="%s" title="%s" aria-label="%s"><span class="dashicons dashicons-update"></span></button>',
        esc_html__('Usage', 'as-image-governance'),
        esc_url(asig_get_recount_url()),
        esc_attr__('Recount usage', 'as-image-governance'),
        esc_attr__('Recount usage', 'as-image-governance')
    );
    $columns['asig_collections'] = __('Collections', 'as-image-governance');
    $columns['asig_image_colors'] = __('Image Colors', 'as-image-governance');
    $columns['asig_image_tag'] = __('Image Tags', 'as-image-governance');

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
    } elseif ('asig_usage' === $column_name) {
        echo wp_kses_post(asig_get_attachment_usage_details_html($post_id));
    } elseif ('asig_collections' === $column_name) {
        echo get_the_term_list($post_id, 'ig_collection', '', ', ') ?: '&mdash;';
    } elseif ('asig_image_colors' === $column_name) {
        echo get_the_term_list($post_id, 'ig_image_color', '', ', ') ?: '&mdash;';
    } elseif ('asig_image_tag' === $column_name) {
        echo get_the_term_list($post_id, 'ig_image_tag', '', ', ') ?: '&mdash;';
    }
}

function asig_render_attachment_usage_details(int $post_id): void
{
    echo wp_kses_post(asig_get_attachment_usage_details_html($post_id));
}

function asig_get_attachment_usage_details_html(int $post_id): string
{
    $usage = asig_get_attachment_usage($post_id);

    if (!$usage) {
        return '<p class="asig-no-usage">' . esc_html__('No known uses.', 'as-image-governance') . '</p>';
    }

    $html = '<ul class="asig-usage-list">';
    foreach ($usage as $item) {
        $used_post_id = (int) ($item['post_id'] ?? 0);
        if (!$used_post_id) {
            continue;
        }

        $html .= sprintf(
            '<li>%s, %s: <a href="%s">%s</a> <a href="%s">%s</a></li>',
            esc_html((string) ($item['usage_type'] ?? '')),
            esc_html(asig_get_post_type_label($item['post_type'] ?? get_post_type($used_post_id))),
            esc_url(get_edit_post_link($used_post_id, '')),
            esc_html(get_the_title($used_post_id)),
            esc_url(get_permalink($used_post_id)),
            esc_html__('View', 'as-image-governance')
        );
    }
    $html .= '</ul>';

    return $html;
}

function asig_render_media_filters(string $post_type): void
{
    if ('attachment' !== $post_type) {
        return;
    }

    $selected_authority = isset($_GET['asig_authority_level']) ? sanitize_text_field(wp_unslash($_GET['asig_authority_level'])) : '';
    $missing = isset($_GET['asig_missing']) ? sanitize_text_field(wp_unslash($_GET['asig_missing'])) : '';
    $image_color = isset($_GET['asig_image_color']) ? absint($_GET['asig_image_color']) : 0;
    $image_tags = isset($_GET['asig_image_tag']) ? absint($_GET['asig_image_tag']) : 0;
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
            'taxonomy'          => 'ig_image_color',
            'name'              => 'asig_image_color',
            'show_option_all'   => __('All image colors', 'as-image-governance'),
            'hide_empty'        => false,
            'selected'          => $image_color,
            'value_field'       => 'term_id',
            'hierarchical'      => false,
        )
    );
    wp_dropdown_categories(
        array(
            'taxonomy'          => 'ig_image_tag',
            'name'              => 'asig_image_tag',
            'show_option_all'   => __('All image tags', 'as-image-governance'),
            'hide_empty'        => false,
            'selected'          => $image_tags,
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

    $query_vars = asig_apply_governance_attachment_filters(
        array(
            'meta_query' => (array) $query->get('meta_query'),
            'tax_query'  => (array) $query->get('tax_query'),
        ),
        $_GET
    );

    if (!empty($query_vars['meta_query'])) {
        $query->set('meta_query', $query_vars['meta_query']);
    }

    if (!empty($query_vars['tax_query'])) {
        $query->set('tax_query', $query_vars['tax_query']);
    }
}

function asig_filter_media_modal_attachments(array $query): array
{
    $request = array();

    if (isset($_REQUEST['query']) && is_array($_REQUEST['query'])) {
        $request = wp_unslash($_REQUEST['query']);
    }

    return asig_apply_governance_attachment_filters($query, $request);
}

function asig_apply_governance_attachment_filters(array $query, array $request): array
{
    $meta_query = isset($query['meta_query']) && is_array($query['meta_query']) ? $query['meta_query'] : array();
    $tax_query = isset($query['tax_query']) && is_array($query['tax_query']) ? $query['tax_query'] : array();
    $raw_authority_level = isset($request['asig_authority_level']) && is_scalar($request['asig_authority_level']) ? wp_unslash($request['asig_authority_level']) : '';
    $raw_missing_filter = isset($request['asig_missing']) && is_scalar($request['asig_missing']) ? wp_unslash($request['asig_missing']) : '';
    $raw_collection = isset($request['asig_collection']) && is_scalar($request['asig_collection']) ? wp_unslash($request['asig_collection']) : 0;
    $raw_image_color = isset($request['asig_image_color']) && is_scalar($request['asig_image_color']) ? wp_unslash($request['asig_image_color']) : 0;
    $raw_image_tags = isset($request['asig_image_tag']) && is_scalar($request['asig_image_tag']) ? wp_unslash($request['asig_image_tag']) : 0;
    $authority_level = asig_sanitize_authority_level($raw_authority_level);
    $missing_filter = sanitize_text_field($raw_missing_filter);
    $collection = absint($raw_collection);
    $image_color = absint($raw_image_color);
    $image_tags = absint($raw_image_tags);

    if ('' !== (string) $raw_authority_level) {
        $meta_query[] = asig_get_authority_meta_query($authority_level);
    }

    if ('attribution' === $missing_filter) {
        $meta_query[] = array(
            'relation' => 'OR',
            array('key' => '_ig_attribution', 'compare' => 'NOT EXISTS'),
            array('key' => '_ig_attribution', 'value' => '', 'compare' => '='),
        );
    }

    if ('source' === $missing_filter) {
        $meta_query[] = array(
            'relation' => 'OR',
            array('key' => '_ig_source', 'compare' => 'NOT EXISTS'),
            array('key' => '_ig_source', 'value' => '', 'compare' => '='),
        );
    }

    if ($collection) {
        $tax_query[] = array(
            'taxonomy' => 'ig_collection',
            'field'    => 'term_id',
            'terms'    => $collection,
        );
    }

    if ($image_color) {
        $tax_query[] = array(
            'taxonomy' => 'ig_image_color',
            'field'    => 'term_id',
            'terms'    => $image_color,
        );
    }

    if ($image_tags) {
        $tax_query[] = array(
            'taxonomy' => 'ig_image_tag',
            'field'    => 'term_id',
            'terms'    => $image_tags,
        );
    }

    if ($meta_query) {
        $query['meta_query'] = $meta_query;
    }

    if ($tax_query) {
        $query['tax_query'] = $tax_query;
    }

    return $query;
}

function asig_get_authority_meta_query(string $authority_level): array
{
    if ('0' === $authority_level) {
        return array(
            'relation' => 'OR',
            array('key' => '_ig_authority_level', 'compare' => 'NOT EXISTS'),
            array('key' => '_ig_authority_level', 'value' => '', 'compare' => '='),
            array('key' => '_ig_authority_level', 'value' => '0', 'compare' => '='),
            array('key' => '_ig_authority_level', 'value' => 'null', 'compare' => '='),
        );
    }

    return array(
        'key'   => '_ig_authority_level',
        'value' => $authority_level,
    );
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

    $redirect_to = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : admin_url('upload.php');

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

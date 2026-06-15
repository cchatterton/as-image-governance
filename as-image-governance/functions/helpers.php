<?php
/**
 * Shared helpers for Image Governance.
 *
 * @package ImageGovernance
 */

if (!defined('ABSPATH')) {
    exit;
}

function asig_get_authority_levels(): array
{
    return array(
        '0' => __('Unknown / None', 'as-image-governance'),
        '1' => __('Assumed', 'as-image-governance'),
        '2' => __('Public', 'as-image-governance'),
        '3' => __('Client supplied', 'as-image-governance'),
        '4' => __('Licensed', 'as-image-governance'),
        '5' => __('Owned / Explicit permission', 'as-image-governance'),
    );
}

function asig_get_authority_label($value): string
{
    $levels = asig_get_authority_levels();
    $key = (string) $value;

    if ('' === trim($key) || 'null' === strtolower(trim($key))) {
        return '';
    }

    return $levels[$key] ?? $levels['0'];
}

function asig_is_image_attachment(int $attachment_id): bool
{
    return 'attachment' === get_post_type($attachment_id) && wp_attachment_is_image($attachment_id);
}

function asig_get_attachment_governance(int $attachment_id): array
{
    return array(
        'source'          => (string) get_post_meta($attachment_id, '_ig_source', true),
        'authority_level' => (string) get_post_meta($attachment_id, '_ig_authority_level', true),
        'authority_notes' => (string) get_post_meta($attachment_id, '_ig_authority_notes', true),
        'attribution'     => (string) get_post_meta($attachment_id, '_ig_attribution', true),
    );
}

function asig_sanitize_authority_level($value): string
{
    $value = (string) $value;

    return array_key_exists($value, asig_get_authority_levels()) ? $value : '0';
}

function asig_get_settings(): array
{
    $defaults = array(
        'enable_footer_link'       => '0',
        'attribution_page_id'      => 0,
        'footer_link_label'        => __('Image Attribution', 'as-image-governance'),
        'scan_public_post_types'   => '1',
        'enable_uninstall_cleanup' => '0',
    );

    $settings = get_option('asig_settings', array());

    return wp_parse_args(is_array($settings) ? $settings : array(), $defaults);
}

function asig_get_recount_url(): string
{
    $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
    $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $redirect_url = $host && $uri ? set_url_scheme('http://' . $host . $uri) : admin_url('upload.php');

    return wp_nonce_url(
        add_query_arg(
            array(
                'action'      => 'asig_scan_usage',
                'redirect_to' => $redirect_url,
            ),
            admin_url('admin-post.php')
        ),
        'asig_scan_usage',
        'asig_scan_usage_nonce'
    );
}

function asig_get_admin_redirect_url(): string
{
    $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
    $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

    return $host && $uri ? set_url_scheme('http://' . $host . $uri) : admin_url('upload.php');
}

function asig_get_post_type_label($post_type): string
{
    $post_type_object = get_post_type_object((string) $post_type);

    if ($post_type_object && isset($post_type_object->labels->singular_name)) {
        return (string) $post_type_object->labels->singular_name;
    }

    return (string) $post_type;
}

function asig_update_settings(array $settings): void
{
    update_option('asig_settings', $settings, false);
}

function asig_get_usage_index(): array
{
    $usage = get_option('asig_usage_index', array());

    return is_array($usage) ? $usage : array();
}

function asig_get_attachment_usage(int $attachment_id): array
{
    $usage = asig_get_usage_index();
    $key = (string) $attachment_id;

    return isset($usage[$key]) && is_array($usage[$key]) ? $usage[$key] : array();
}

function asig_get_attachment_usage_count(int $attachment_id): int
{
    return count(asig_get_attachment_usage($attachment_id));
}

function asig_get_collection_options(): array
{
    $terms = get_terms(array('taxonomy' => 'ig_collection', 'hide_empty' => false));

    if (is_wp_error($terms)) {
        return array();
    }

    return array_map(
        static function (WP_Term $term): array {
            return array(
                'id'   => (int) $term->term_id,
                'name' => $term->name,
            );
        },
        $terms
    );
}

function asig_get_attachment_collection_ids(int $attachment_id): array
{
    $terms = wp_get_object_terms($attachment_id, 'ig_collection', array('fields' => 'ids'));

    if (is_wp_error($terms)) {
        return array();
    }

    return array_map('intval', $terms);
}

function asig_get_current_request_path(): string
{
    $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
    $path = wp_parse_url($uri, PHP_URL_PATH);

    return $path ? untrailingslashit($path) . '/' : '/';
}

function asig_find_post_by_path(string $path): ?WP_Post
{
    $path = trim($path);

    if ('' === $path) {
        return null;
    }

    $relative_path = trim((string) wp_parse_url($path, PHP_URL_PATH), '/');

    if ('' === $relative_path) {
        return null;
    }

    $post_types = get_post_types(array('public' => true), 'names');
    unset($post_types['attachment']);

    $post = get_page_by_path($relative_path, OBJECT, array_values($post_types));

    return $post instanceof WP_Post ? $post : null;
}

function asig_current_page_has_attributed_images(): bool
{
    if (!is_singular()) {
        return false;
    }

    $post_id = get_queried_object_id();

    if (!$post_id) {
        return false;
    }

    foreach (asig_get_usage_index() as $attachment_id => $items) {
        if (!is_array($items) || '' === trim((string) get_post_meta((int) $attachment_id, '_ig_attribution', true))) {
            continue;
        }

        foreach ($items as $item) {
            if ((int) ($item['post_id'] ?? 0) === (int) $post_id) {
                return true;
            }
        }
    }

    return false;
}

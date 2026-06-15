<?php
/**
 * GitHub release updater for Image Governance.
 *
 * @package ImageGovernance
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ASIG_GitHub_Updater
{
    private const OWNER = 'cchatterton';
    private const REPO = 'as-image-governance';
    private const SLUG = 'as-image-governance';
    private const ASSET_NAME = 'as-image-governance.zip';
    private const RELEASE_TRANSIENT = 'asig_github_latest_release';
    private const GITHUB_URL = 'https://github.com/cchatterton/as-image-governance';
    private const REQUIRES = '6.0';
    private const REQUIRES_PHP = '8.1';

    public static function init(): void
    {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'add_update_data'));
        add_filter('site_transient_update_plugins', array(__CLASS__, 'add_update_data'));
        add_filter('plugins_api', array(__CLASS__, 'add_plugin_information'), 10, 3);
        add_filter('plugin_row_meta', array(__CLASS__, 'add_plugin_row_meta'), 10, 2);
        add_action('upgrader_process_complete', array(__CLASS__, 'clear_release_cache'));
    }

    public static function add_update_data($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }

        $plugin_file = plugin_basename(ASIG_PLUGIN_FILE);
        $transient->response = isset($transient->response) && is_array($transient->response) ? $transient->response : array();
        $transient->no_update = isset($transient->no_update) && is_array($transient->no_update) ? $transient->no_update : array();
        $release = self::get_latest_release();

        if (!self::is_valid_release($release)) {
            return $transient;
        }

        $update = self::build_update_object($release, $plugin_file);

        if ($update) {
            $transient->response[$plugin_file] = $update;
            return $transient;
        }

        unset($transient->response[$plugin_file]);

        $transient->no_update[$plugin_file] = (object) array(
            'id'           => self::GITHUB_URL,
            'slug'         => self::SLUG,
            'plugin'       => $plugin_file,
            'new_version'  => ASIG_VERSION,
            'url'          => self::GITHUB_URL,
            'package'      => '',
            'requires'     => self::REQUIRES,
            'requires_php' => self::REQUIRES_PHP,
        );

        return $transient;
    }

    public static function add_plugin_information($result, string $action, object $args)
    {
        if ('plugin_information' !== $action || self::SLUG !== ($args->slug ?? '')) {
            return $result;
        }

        $release = self::get_latest_release();
        $version = self::is_valid_release($release) ? self::get_release_version($release) : ASIG_VERSION;
        $download_url = self::get_release_asset_url($release);
        $body = is_array($release) ? (string) ($release['body'] ?? '') : '';

        return (object) array(
            'name'          => __('Image Governance', 'as-image-governance'),
            'slug'          => self::SLUG,
            'version'       => $version,
            'author'        => 'AlphaSys',
            'homepage'      => self::GITHUB_URL,
            'download_link' => $download_url,
            'requires'      => self::REQUIRES,
            'requires_php'  => self::REQUIRES_PHP,
            'sections'      => array(
                'description' => __('Records image source, authority, usage, attribution, and lightweight collections.', 'as-image-governance'),
                'changelog'   => $body ?: __('Release notes are available on GitHub.', 'as-image-governance'),
            ),
        );
    }

    public static function add_plugin_row_meta(array $links, string $plugin_file): array
    {
        if (plugin_basename(ASIG_PLUGIN_FILE) !== $plugin_file) {
            return $links;
        }

        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url(self::GITHUB_URL),
            esc_html__('GitHub', 'as-image-governance')
        );

        return $links;
    }

    private static function build_update_object($release, string $plugin_file): ?object
    {
        $version = self::get_release_version($release);
        $download_url = self::get_release_asset_url($release);

        if (!$version || !$download_url || !version_compare($version, ASIG_VERSION, '>')) {
            return null;
        }

        return (object) array(
            'id'           => self::GITHUB_URL,
            'slug'         => self::SLUG,
            'plugin'       => $plugin_file,
            'new_version'  => $version,
            'url'          => (string) ($release['html_url'] ?? self::GITHUB_URL),
            'package'      => $download_url,
            'requires'     => self::REQUIRES,
            'requires_php' => self::REQUIRES_PHP,
        );
    }

    private static function get_latest_release()
    {
        if (self::is_forced_update_check()) {
            delete_site_transient(self::RELEASE_TRANSIENT);
        }

        $cached = get_site_transient(self::RELEASE_TRANSIENT);

        if (is_array($cached) && self::is_usable_cached_release($cached)) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::OWNER . '/' . self::REPO . '/releases/latest',
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'Image-Governance/' . ASIG_VERSION,
                ),
            )
        );

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            set_site_transient(self::RELEASE_TRANSIENT, array('asig_error' => true), 30 * MINUTE_IN_SECONDS);
            return false;
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($release)) {
            set_site_transient(self::RELEASE_TRANSIENT, array('asig_error' => true), 30 * MINUTE_IN_SECONDS);
            return false;
        }

        $version = self::get_release_version($release);
        $cache_ttl = $version && version_compare($version, ASIG_VERSION, '>') ? 6 * HOUR_IN_SECONDS : 10 * MINUTE_IN_SECONDS;
        $release['asig_cached_for_version'] = ASIG_VERSION;
        $release['asig_cached_at'] = time();

        set_site_transient(self::RELEASE_TRANSIENT, $release, $cache_ttl);

        return $release;
    }

    public static function clear_release_cache(): void
    {
        delete_site_transient(self::RELEASE_TRANSIENT);
    }

    private static function get_release_version($release): string
    {
        if (!is_array($release) || !empty($release['asig_error'])) {
            return '';
        }

        return ltrim((string) ($release['tag_name'] ?? ''), 'vV');
    }

    private static function is_valid_release($release): bool
    {
        return is_array($release)
            && empty($release['asig_error'])
            && '' !== self::get_release_version($release)
            && '' !== self::get_release_asset_url($release);
    }

    private static function is_usable_cached_release(array $release): bool
    {
        if (empty($release['asig_cached_for_version']) || ASIG_VERSION !== (string) $release['asig_cached_for_version']) {
            return false;
        }

        $version = self::get_release_version($release);

        if (!$version) {
            return false;
        }

        if (version_compare($version, ASIG_VERSION, '>')) {
            return true;
        }

        $cached_at = isset($release['asig_cached_at']) ? (int) $release['asig_cached_at'] : 0;

        return $cached_at > 0 && (time() - $cached_at) < 10 * MINUTE_IN_SECONDS;
    }

    private static function get_release_asset_url($release): string
    {
        if (!is_array($release) || empty($release['assets']) || !is_array($release['assets'])) {
            return '';
        }

        foreach ($release['assets'] as $asset) {
            if (self::ASSET_NAME === ($asset['name'] ?? '') && !empty($asset['browser_download_url'])) {
                return esc_url_raw((string) $asset['browser_download_url']);
            }
        }

        return '';
    }

    private static function is_forced_update_check(): bool
    {
        if (!is_admin() || !current_user_can('update_plugins')) {
            return false;
        }

        if (isset($_GET['force-check']) || isset($_POST['force-check'])) {
            return true;
        }

        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';

        return in_array($action, array('update-selected', 'upgrade-plugin', 'do-plugin-upgrade'), true);
    }
}

ASIG_GitHub_Updater::init();

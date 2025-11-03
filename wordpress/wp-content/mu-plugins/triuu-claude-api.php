<?php
/**
 * Plugin Name: TRI-UU Claude Code API
 * Description: REST API for Claude Code to interact with the TRI-UU WordPress site
 * Version: 1.0.0
 * Author: TRI-UU Development Team
 */

if (!defined('ABSPATH')) {
    exit;
}

class Triuu_Claude_API {

    const API_NAMESPACE = 'triuu-claude/v1';
    const API_KEY = 'DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n'; // Store securely in production

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Health check endpoint (no auth required)
        register_rest_route(self::API_NAMESPACE, '/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'health_check'),
            'permission_callback' => '__return_true',
        ));

        // Site info endpoint
        register_rest_route(self::API_NAMESPACE, '/site-info', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_site_info'),
            'permission_callback' => array($this, 'check_api_key'),
        ));

        // List files endpoint
        register_rest_route(self::API_NAMESPACE, '/files/list', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_files'),
            'permission_callback' => array($this, 'check_api_key'),
            'args' => array(
                'path' => array(
                    'required' => false,
                    'default' => '/',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // Read file endpoint
        register_rest_route(self::API_NAMESPACE, '/files/read', array(
            'methods' => 'GET',
            'callback' => array($this, 'read_file'),
            'permission_callback' => array($this, 'check_api_key'),
            'args' => array(
                'path' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // Write file endpoint
        register_rest_route(self::API_NAMESPACE, '/files/write', array(
            'methods' => 'POST',
            'callback' => array($this, 'write_file'),
            'permission_callback' => array($this, 'check_api_key'),
            'args' => array(
                'path' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'content' => array(
                    'required' => true,
                ),
            ),
        ));

        // Plugin info endpoint
        register_rest_route(self::API_NAMESPACE, '/plugins/list', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_plugins'),
            'permission_callback' => array($this, 'check_api_key'),
        ));

        // Theme info endpoint
        register_rest_route(self::API_NAMESPACE, '/themes/list', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_themes'),
            'permission_callback' => array($this, 'check_api_key'),
        ));
    }

    /**
     * Check API key authentication
     */
    public function check_api_key($request) {
        $api_key = $request->get_header('X-Claude-API-Key');

        if (empty($api_key)) {
            return new WP_Error(
                'missing_api_key',
                'API key is required. Please provide X-Claude-API-Key header.',
                array('status' => 401)
            );
        }

        if ($api_key !== self::API_KEY) {
            return new WP_Error(
                'invalid_api_key',
                'Invalid API key provided.',
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Health check endpoint
     */
    public function health_check($request) {
        return rest_ensure_response(array(
            'status' => 'ok',
            'message' => 'TRI-UU Claude API is running',
            'timestamp' => current_time('mysql'),
            'wordpress_version' => get_bloginfo('version'),
        ));
    }

    /**
     * Get site information
     */
    public function get_site_info($request) {
        return rest_ensure_response(array(
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
            'admin_email' => get_bloginfo('admin_email'),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'theme' => wp_get_theme()->get('Name'),
            'theme_version' => wp_get_theme()->get('Version'),
            'content_dir' => WP_CONTENT_DIR,
            'plugin_dir' => WP_PLUGIN_DIR,
            'theme_dir' => get_stylesheet_directory(),
        ));
    }

    /**
     * List files in a directory
     */
    public function list_files($request) {
        $path = $request->get_param('path');

        // Security: Ensure path is within wp-content
        $base_path = WP_CONTENT_DIR;
        $full_path = $this->sanitize_path($base_path . $path);

        if (strpos($full_path, $base_path) !== 0) {
            return new WP_Error(
                'invalid_path',
                'Path must be within wp-content directory',
                array('status' => 400)
            );
        }

        if (!file_exists($full_path) || !is_dir($full_path)) {
            return new WP_Error(
                'directory_not_found',
                'Directory not found: ' . $path,
                array('status' => 404)
            );
        }

        $files = array();
        $items = scandir($full_path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $item_path = $full_path . '/' . $item;
            $files[] = array(
                'name' => $item,
                'path' => str_replace($base_path, '', $item_path),
                'type' => is_dir($item_path) ? 'directory' : 'file',
                'size' => is_file($item_path) ? filesize($item_path) : null,
                'modified' => filemtime($item_path),
            );
        }

        return rest_ensure_response(array(
            'path' => $path,
            'full_path' => $full_path,
            'items' => $files,
            'count' => count($files),
        ));
    }

    /**
     * Read a file
     */
    public function read_file($request) {
        $path = $request->get_param('path');

        // Security: Ensure path is within wp-content
        $base_path = WP_CONTENT_DIR;
        $full_path = $this->sanitize_path($base_path . $path);

        if (strpos($full_path, $base_path) !== 0) {
            return new WP_Error(
                'invalid_path',
                'Path must be within wp-content directory',
                array('status' => 400)
            );
        }

        if (!file_exists($full_path) || !is_file($full_path)) {
            return new WP_Error(
                'file_not_found',
                'File not found: ' . $path,
                array('status' => 404)
            );
        }

        $content = file_get_contents($full_path);

        if ($content === false) {
            return new WP_Error(
                'read_error',
                'Failed to read file: ' . $path,
                array('status' => 500)
            );
        }

        return rest_ensure_response(array(
            'path' => $path,
            'full_path' => $full_path,
            'content' => $content,
            'size' => filesize($full_path),
            'modified' => filemtime($full_path),
            'encoding' => mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true),
        ));
    }

    /**
     * Write a file
     */
    public function write_file($request) {
        $path = $request->get_param('path');
        $content = $request->get_param('content');

        // Security: Ensure path is within wp-content
        $base_path = WP_CONTENT_DIR;
        $full_path = $this->sanitize_path($base_path . $path);

        if (strpos($full_path, $base_path) !== 0) {
            return new WP_Error(
                'invalid_path',
                'Path must be within wp-content directory',
                array('status' => 400)
            );
        }

        // Create directory if it doesn't exist
        $dir = dirname($full_path);
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return new WP_Error(
                    'mkdir_error',
                    'Failed to create directory',
                    array('status' => 500)
                );
            }
        }

        $result = file_put_contents($full_path, $content);

        if ($result === false) {
            return new WP_Error(
                'write_error',
                'Failed to write file: ' . $path,
                array('status' => 500)
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'path' => $path,
            'full_path' => $full_path,
            'bytes_written' => $result,
            'message' => 'File written successfully',
        ));
    }

    /**
     * List all plugins
     */
    public function list_plugins($request) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());

        $plugins = array();
        foreach ($all_plugins as $plugin_path => $plugin_data) {
            $plugins[] = array(
                'path' => $plugin_path,
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'author' => $plugin_data['Author'],
                'description' => $plugin_data['Description'],
                'active' => in_array($plugin_path, $active_plugins),
            );
        }

        return rest_ensure_response(array(
            'plugins' => $plugins,
            'count' => count($plugins),
            'active_count' => count($active_plugins),
        ));
    }

    /**
     * List all themes
     */
    public function list_themes($request) {
        $all_themes = wp_get_themes();
        $current_theme = wp_get_theme();

        $themes = array();
        foreach ($all_themes as $theme_slug => $theme_data) {
            $themes[] = array(
                'slug' => $theme_slug,
                'name' => $theme_data->get('Name'),
                'version' => $theme_data->get('Version'),
                'author' => $theme_data->get('Author'),
                'description' => $theme_data->get('Description'),
                'template' => $theme_data->get_template(),
                'stylesheet' => $theme_data->get_stylesheet(),
                'active' => ($theme_slug === $current_theme->get_stylesheet()),
            );
        }

        return rest_ensure_response(array(
            'themes' => $themes,
            'count' => count($themes),
            'current_theme' => $current_theme->get('Name'),
        ));
    }

    /**
     * Sanitize file path
     */
    private function sanitize_path($path) {
        // Remove any directory traversal attempts
        $path = str_replace(array('../', '..\\'), '', $path);
        // Normalize slashes
        $path = str_replace('\\', '/', $path);
        // Remove double slashes
        $path = preg_replace('#/+#', '/', $path);
        return $path;
    }
}

// Initialize the plugin
new Triuu_Claude_API();

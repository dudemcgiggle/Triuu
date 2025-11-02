<?php
/**
 * Plugin Name: TriUU Sandbox
 * Description: Safe minimal version to restore activation; shortcode + admin page + assets.
 * Version: 0.1.0
 * Author: Ken
 */

if (!defined('ABSPATH')) { exit; }

/** Version/paths */
define('TRIUU_SANDBOX_VER', '0.1.0');
define('TRIUU_SANDBOX_DIR', plugin_dir_path(__FILE__));
define('TRIUU_SANDBOX_URL', plugin_dir_url(__FILE__));

/**
 * Enqueue frontend assets (files may or may not exist; register guarded).
 */
add_action('wp_enqueue_scripts', function () {
    $css = TRIUU_SANDBOX_URL . 'assets/css/style.css';
    $js  = TRIUU_SANDBOX_URL . 'assets/js/main.js';

    // Only enqueue if the files actually exist to avoid warnings
    if (file_exists(TRIUU_SANDBOX_DIR . 'assets/css/style.css')) {
        wp_enqueue_style('triuu-sandbox-style', $css, array(), TRIUU_SANDBOX_VER);
    }
    if (file_exists(TRIUU_SANDBOX_DIR . 'assets/js/main.js')) {
        wp_enqueue_script('triuu-sandbox-js', $js, array('jquery'), TRIUU_SANDBOX_VER, true);
    }
});

/**
 * Minimal admin page so you can confirm activation.
 */
add_action('admin_menu', function () {
    add_menu_page(
        'TriUU Tools',
        'TriUU Tools',
        'manage_options',
        'triuu-sandbox',
        function () {
            if (!current_user_can('manage_options')) { return; }
            echo '<div class="wrap"><h1>TriUU Sandbox</h1><p>Plugin is active (safe minimal mode).</p></div>';
        },
        'dashicons-admin-generic',
        81
    );
});

/**
 * Simple shortcode for validation: [triuu_hello name="Ken"]
 */
add_shortcode('triuu_hello', function ($atts) {
    $atts = shortcode_atts(array('name' => 'friend'), $atts, 'triuu_hello');
    return '<span class="triuu-hello">Hello, ' . esc_html($atts['name']) . ' 👋</span>';
});

/**
 * (Optional) REST ping — guarded to avoid fatals on older environments.
 * URL after activation: /wp-json/triuu/v1/ping
 */
add_action('rest_api_init', function () {
    if (function_exists('register_rest_route')) {
        register_rest_route('triuu/v1', '/ping', array(
            'methods'  => 'GET',
            'callback' => function () {
                return array(
                    'ok'      => true,
                    'plugin'  => 'triuu-sandbox',
                    'version' => TRIUU_SANDBOX_VER,
                    'time'    => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
                );
            },
            'permission_callback' => '__return_true',
        ));
    }
});

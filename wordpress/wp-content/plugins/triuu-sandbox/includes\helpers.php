<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Shortcode: [triuu_hello name="Ken"]
 */
add_shortcode('triuu_hello', function ($atts) {
    $atts = shortcode_atts(array('name' => 'friend'), $atts, 'triuu_hello');
    $name = sanitize_text_field($atts['name']);
    return '<span class="triuu-hello">Hello, ' . esc_html($name) . ' 👋</span>';
});

/**
 * REST: GET /wp-json/triuu/v1/ping
 */
add_action('rest_api_init', function () {
    register_rest_route('triuu/v1', '/ping', array(
        'methods'  => 'GET',
        'callback' => function () {
            return array(
                'ok'      => true,
                'plugin'  => 'triuu-sandbox',
                'version' => TRIUU_SANDBOX_VER,
                'time'    => current_time('mysql'),
            );
        },
        'permission_callback' => '__return_true'
    ));
});

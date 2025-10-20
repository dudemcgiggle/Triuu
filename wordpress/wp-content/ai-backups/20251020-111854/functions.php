<?php
// Triuu child theme bootstrap (inert)
/* AI:start:noop */
if (!function_exists('tri_ai_noop_test')) {
    function tri_ai_noop_test() { return 'ok'; }
}
/* AI:end:noop */

/* AI:start:enqueue-child-style */
/**
 * Enqueue Triuu child stylesheet so changes in style.css apply on the front end.
 * Loads after the parent CSS.
 */
add_action('wp_enqueue_scripts', function () {
    $ver = wp_get_theme()->get('Version');
    wp_enqueue_style('triuu-child', get_stylesheet_uri(), array(), $ver);
}, 20);
/* AI:end:enqueue-child-style */

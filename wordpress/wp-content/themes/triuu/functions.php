<?php
/**
 * Triuu Child Theme — functions.php
 * Purpose: minimal, safe bootstrap with one enqueue block (cache-busted) and a no-op token for tests.
 */

defined('ABSPATH') || exit;

/* AI:start:noop */
/**
 * No-op test function (inert). Safe to keep or remove.
 */
if (!function_exists('tri_ai_noop_test')) {
    function tri_ai_noop_test() { return 'ok'; }
}
/* AI:end:noop */

/* AI:start:enqueue-fonts */
/**
 * Enqueue custom fonts stylesheet.
 * Loads early so fonts are available for all other styles.
 */
add_action('wp_enqueue_scripts', function () {
    $fonts_uri  = get_stylesheet_directory_uri() . '/assets/css/fonts.css';
    $fonts_path = get_stylesheet_directory() . '/assets/css/fonts.css';
    $fonts_ver  = file_exists($fonts_path) ? filemtime($fonts_path) : wp_get_theme()->get('Version');

    wp_enqueue_style('triuu-fonts', $fonts_uri, array(), $fonts_ver);
}, 10);
/* AI:end:enqueue-fonts */

/* AI:start:enqueue-child-style */
/**
 * Enqueue the child stylesheet with filemtime-based cache-busting.
 * Loads after the parent Hello Elementor styles and fonts.
 */
add_action('wp_enqueue_scripts', function () {
    // Avoid double-enqueue if another tool already added it.
    if (wp_style_is('triuu-child', 'enqueued')) {
        return;
    }

    $uri  = get_stylesheet_uri(); // points to child style.css
    $path = get_stylesheet_directory() . '/style.css';
    $ver  = file_exists($path) ? filemtime($path) : wp_get_theme()->get('Version');

    // Allow opt-out via filter: add_filter('triuu_enqueue_child_style', '__return_false');
    $should_enqueue = apply_filters('triuu_enqueue_child_style', true);
    if ($should_enqueue) {
        wp_enqueue_style('triuu-child', $uri, array('triuu-fonts'), $ver);
    }
}, 20);
/* AI:end:enqueue-child-style */

/* AI:start:sandbox-php */
/* (reserved for tokenized PHP edits) */
/* AI:end:sandbox-php */
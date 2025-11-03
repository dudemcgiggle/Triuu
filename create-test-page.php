<?php
/**
 * Create a test page in WordPress
 */

// Set up environment for CLI
$_SERVER['HTTP_HOST'] = 'd7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Load WordPress
require_once __DIR__ . '/wordpress/wp-load.php';

// Check if page already exists
$existing_page = get_page_by_path('testing-123', OBJECT, 'page');

if ($existing_page) {
    echo "Page already exists! URL: " . get_permalink($existing_page->ID) . "\n";
    echo "Page ID: " . $existing_page->ID . "\n";
    exit;
}

// Create the page
$page_data = array(
    'post_title'    => 'Testing 1,2,3',
    'post_content'  => '<h1 style="font-size: 4rem; text-align: center; color: #614E6B; font-family: \'Barlow\', sans-serif; padding: 3rem 0;">Testing 1,2,3</h1>',
    'post_status'   => 'publish',
    'post_type'     => 'page',
    'post_author'   => 1,
    'post_name'     => 'testing-123',
    'comment_status' => 'closed',
    'ping_status'   => 'closed',
);

// Insert the page
$page_id = wp_insert_post($page_data);

if ($page_id && !is_wp_error($page_id)) {
    echo "✅ Success! Test page created.\n";
    echo "Page ID: $page_id\n";
    echo "URL: " . get_permalink($page_id) . "\n";
    echo "\nYou can edit this page in WordPress Admin:\n";
    echo "Dashboard → Pages → All Pages → 'Testing 1,2,3'\n";
} else {
    echo "❌ Error creating page.\n";
    if (is_wp_error($page_id)) {
        echo "Error: " . $page_id->get_error_message() . "\n";
    }
}

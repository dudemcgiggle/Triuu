<?php
/**
 * Create Test Page - Web Accessible Script
 * Access this file directly in your browser to create the page
 */

// Load WordPress
require_once __DIR__ . '/wp-load.php';

// Security check - only allow if user is logged in as admin
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Unauthorized access. Please log in as an administrator first.');
}

// Check if page already exists
$existing_page = get_page_by_path('testing-123', OBJECT, 'page');

if ($existing_page) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Test Page Already Exists</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; max-width: 800px; margin: 0 auto; }
            .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 5px; }
            a { color: #007bff; text-decoration: none; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="success">
            <h1>✅ Page Already Exists!</h1>
            <p><strong>Page ID:</strong> <?php echo $existing_page->ID; ?></p>
            <p><strong>View Page:</strong> <a href="<?php echo get_permalink($existing_page->ID); ?>" target="_blank"><?php echo get_permalink($existing_page->ID); ?></a></p>
            <p><strong>Edit Page:</strong> <a href="<?php echo admin_url('post.php?post=' . $existing_page->ID . '&action=edit'); ?>">Edit in WordPress Admin</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Create the page
$page_data = array(
    'post_title'    => 'Testing 1,2,3',
    'post_content'  => '<h1 style="font-size: 4rem; text-align: center; color: #614E6B; font-family: \'Barlow\', sans-serif; padding: 3rem 0; margin: 2rem 0;">Testing 1,2,3</h1>
    <p style="text-align: center; font-size: 1.2rem; color: #666;">This is a test page that can be edited in the WordPress backend.</p>',
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
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Test Page Created</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; max-width: 800px; margin: 0 auto; }
            .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
            .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 20px; border-radius: 5px; }
            a { color: #007bff; text-decoration: none; font-weight: bold; }
            a:hover { text-decoration: underline; }
            .button { display: inline-block; background: #614E6B; color: white; padding: 12px 24px; border-radius: 4px; margin: 10px 5px; }
            .button:hover { background: #A5849F; color: white; }
        </style>
    </head>
    <body>
        <div class="success">
            <h1>✅ Success! Test Page Created</h1>
            <p><strong>Page ID:</strong> <?php echo $page_id; ?></p>
            <p><strong>Page Title:</strong> Testing 1,2,3</p>
            <p><strong>Page URL:</strong> <a href="<?php echo get_permalink($page_id); ?>" target="_blank"><?php echo get_permalink($page_id); ?></a></p>
        </div>

        <div class="info">
            <h2>What to do next:</h2>
            <p>
                <a href="<?php echo get_permalink($page_id); ?>" class="button" target="_blank">View Page</a>
                <a href="<?php echo admin_url('post.php?post=' . $page_id . '&action=edit'); ?>" class="button">Edit in WordPress</a>
                <a href="<?php echo admin_url('edit.php?post_type=page'); ?>" class="button">All Pages</a>
            </p>
            <p><strong>To edit this page:</strong></p>
            <ol>
                <li>Go to WordPress Admin Dashboard</li>
                <li>Click "Pages" → "All Pages"</li>
                <li>Find "Testing 1,2,3" and click "Edit"</li>
            </ol>
        </div>
    </body>
    </html>
    <?php
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Error Creating Page</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; max-width: 800px; margin: 0 auto; }
            .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="error">
            <h1>❌ Error Creating Page</h1>
            <?php if (is_wp_error($page_id)): ?>
                <p><strong>Error:</strong> <?php echo $page_id->get_error_message(); ?></p>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}
?>

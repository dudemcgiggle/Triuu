<?php
/**
 * Update Services Page with New Elementor Content
 * Run this script once to update the Services page
 */

// Load WordPress
require_once 'wordpress/wp-load.php';

// Services page ID (using the first one: 1460)
$page_id = 1460;

// Read the new HTML content
$new_content = file_get_contents('services-page-content.html');

// Create Elementor data structure with HTML widget
$elementor_data = [
    [
        'id' => uniqid(),
        'elType' => 'section',
        'settings' => [
            'layout' => 'full_width',
        ],
        'elements' => [
            [
                'id' => uniqid(),
                'elType' => 'column',
                'settings' => [
                    '_column_size' => 100,
                ],
                'elements' => [
                    [
                        'id' => uniqid(),
                        'elType' => 'widget',
                        'widgetType' => 'html',
                        'settings' => [
                            'html' => $new_content,
                        ],
                    ],
                ],
            ],
        ],
    ],
];

// Convert to JSON
$elementor_json = wp_json_encode($elementor_data);

// Update the Elementor data
update_post_meta($page_id, '_elementor_data', $elementor_json);

// Mark as edited with Elementor
update_post_meta($page_id, '_elementor_edit_mode', 'builder');

// Update version
update_post_meta($page_id, '_elementor_version', '3.25.0');

// Clear Elementor cache
if (class_exists('\Elementor\Plugin')) {
    \Elementor\Plugin::$instance->files_manager->clear_cache();
}

echo "✅ Services page (ID: $page_id) has been updated with the new content!\n";
echo "📝 You can now view or edit the page in Elementor.\n";
echo "\nNext steps:\n";
echo "1. Go to Pages → Services in WordPress admin\n";
echo "2. Click 'Edit with Elementor' to see the new design\n";
echo "3. Make any additional customizations if needed\n";
echo "4. Click 'Update' to publish the changes\n";
?>

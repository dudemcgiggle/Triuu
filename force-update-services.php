<?php
/**
 * Force Update Services Page - Direct Database Update
 */

define('WP_USE_THEMES', false);
require_once(__DIR__ . '/wordpress/wp-load.php');

// Read the new HTML content
$html_content = file_get_contents(__DIR__ . '/ELEMENTOR-SERVICES-REDESIGNED.html');

// Page ID for Services page
$page_id = 1460;

// Get current Elementor data
$elementor_data = get_post_meta($page_id, '_elementor_data', true);
$data_array = json_decode($elementor_data, true);

if (!is_array($data_array)) {
    echo "❌ Error: Could not parse Elementor data.\n";
    exit;
}

// Find and update the HTML widget
$updated = false;
foreach ($data_array as &$section) {
    if (isset($section['elements'])) {
        foreach ($section['elements'] as &$column) {
            if (isset($column['elements'])) {
                foreach ($column['elements'] as &$widget) {
                    if (isset($widget['widgetType']) && $widget['widgetType'] === 'html') {
                        $widget['settings']['html'] = $html_content;
                        $updated = true;
                        echo "✅ Found HTML widget and updated content\n";
                        break 3;
                    }
                }
            }
        }
    }
}

if (!$updated) {
    echo "⚠️  No HTML widget found, creating new structure...\n";
    
    // Create a new section with HTML widget
    $new_section = [
        'id' => uniqid(),
        'elType' => 'section',
        'settings' => [],
        'elements' => [
            [
                'id' => uniqid(),
                'elType' => 'column',
                'settings' => ['_column_size' => 100],
                'elements' => [
                    [
                        'id' => uniqid(),
                        'elType' => 'widget',
                        'widgetType' => 'html',
                        'settings' => [
                            'html' => $html_content
                        ]
                    ]
                ]
            ]
        ]
    ];
    
    $data_array = [$new_section];
    $updated = true;
}

if ($updated) {
    // Update the post meta
    $json_data = json_encode($data_array);
    update_post_meta($page_id, '_elementor_data', wp_slash($json_data));
    
    // Also update the edit date to force cache refresh
    wp_update_post([
        'ID' => $page_id,
        'post_modified' => current_time('mysql'),
        'post_modified_gmt' => current_time('mysql', 1)
    ]);
    
    // Clear Elementor cache
    if (class_exists('\Elementor\Plugin')) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }
    
    echo "✅ Services page FORCEFULLY updated!\n";
    echo "📝 Content length: " . strlen($html_content) . " characters\n";
    echo "🔄 Cache cleared\n";
    echo "\nView at: /services\n";
} else {
    echo "❌ Update failed\n";
}
?>

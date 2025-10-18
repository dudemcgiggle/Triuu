<?php
/**
 * Update Services Page in WordPress Database
 * Redesigned to match About Us page style
 */

// Path to WordPress
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/wordpress/wp-load.php');

// Read the new HTML content
$html_content = file_get_contents(__DIR__ . '/ELEMENTOR-SERVICES-REDESIGNED.html');

// Page ID for Services page
$page_id = 1460;

// Get current Elementor data
$elementor_data = get_post_meta($page_id, '_elementor_data', true);
$data_array = json_decode($elementor_data, true);

// Update the HTML widget content
if (is_array($data_array)) {
    foreach ($data_array as &$section) {
        if (isset($section['elements'])) {
            foreach ($section['elements'] as &$column) {
                if (isset($column['elements'])) {
                    foreach ($column['elements'] as &$widget) {
                        // Find HTML widget
                        if (isset($widget['widgetType']) && $widget['widgetType'] === 'html') {
                            $widget['settings']['html'] = $html_content;
                            break 3;
                        }
                    }
                }
            }
        }
    }
    
    // Update the post meta with new Elementor data
    update_post_meta($page_id, '_elementor_data', wp_slash(json_encode($data_array)));
    
    echo "✅ Services page (ID: $page_id) has been updated with the REDESIGNED content!\n";
    echo "📝 The page now matches the clean style of your About Us page.\n\n";
    echo "View it at: /services\n";
} else {
    echo "❌ Error: Could not parse Elementor data.\n";
}
?>

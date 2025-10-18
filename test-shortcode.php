<?php
/**
 * Test Script for TRIUU Sermons Shortcode
 */

define('WP_USE_THEMES', false);
require_once(__DIR__ . '/wordpress/wp-load.php');

echo "========================================\n";
echo "Testing TRIUU Upcoming Sermons Shortcode\n";
echo "========================================\n\n";

// Check if plugin is active
if (!class_exists('TRIUU_Sermons_Manager')) {
    echo "❌ Plugin not active!\n";
    exit;
}

echo "✅ Plugin is active\n\n";

// Check monthly theme
$theme = get_option('triuu_monthly_theme', '');
echo "Monthly Theme: " . ($theme ? $theme : '(not set)') . "\n\n";

// Check sermons
$today = date('Y-m-d');
$args = array(
    'post_type' => 'sermon',
    'posts_per_page' => 4,
    'post_status' => 'publish',
    'meta_key' => '_sermon_date',
    'orderby' => 'meta_value',
    'order' => 'ASC',
    'meta_query' => array(
        array(
            'key' => '_sermon_date',
            'value' => $today,
            'compare' => '>=',
            'type' => 'DATE'
        )
    )
);

$sermons_query = new WP_Query($args);

echo "Upcoming Sermons (from {$today}):\n";
echo "-----------------------------------\n";

if ($sermons_query->have_posts()) {
    $count = 1;
    while ($sermons_query->have_posts()) {
        $sermons_query->the_post();
        $sermon_date = get_post_meta(get_the_ID(), '_sermon_date', true);
        $reverend = get_post_meta(get_the_ID(), '_sermon_reverend', true);
        $description = get_post_meta(get_the_ID(), '_sermon_description', true);
        
        echo "{$count}. " . get_the_title() . "\n";
        echo "   Date: {$sermon_date}\n";
        echo "   Reverend: {$reverend}\n";
        echo "   Description: " . substr($description, 0, 80) . "...\n\n";
        $count++;
    }
} else {
    echo "No upcoming sermons found.\n";
}

wp_reset_postdata();

echo "\n========================================\n";
echo "Testing Shortcode Output\n";
echo "========================================\n\n";

// Test the shortcode
$shortcode_output = do_shortcode('[triuu_upcoming_sermons]');

// Display first 500 characters
echo "Shortcode HTML Output (first 500 chars):\n";
echo "----------------------------------------\n";
echo substr(strip_tags($shortcode_output), 0, 500) . "...\n\n";

// Check if output contains expected elements
$has_theme = strpos($shortcode_output, 'theme-subtitle') !== false;
$has_cards = strpos($shortcode_output, 'service-cards') !== false;
$has_date = strpos($shortcode_output, 'class="date"') !== false;
$has_title = strpos($shortcode_output, 'class="title"') !== false;
$has_speaker = strpos($shortcode_output, 'class="speaker"') !== false;
$has_description = strpos($shortcode_output, 'class="description"') !== false;

echo "Validation:\n";
echo "✅ Contains theme subtitle: " . ($has_theme ? 'YES' : 'NO') . "\n";
echo "✅ Contains service-cards div: " . ($has_cards ? 'YES' : 'NO') . "\n";
echo "✅ Contains date class: " . ($has_date ? 'YES' : 'NO') . "\n";
echo "✅ Contains title class: " . ($has_title ? 'YES' : 'NO') . "\n";
echo "✅ Contains speaker class: " . ($has_speaker ? 'YES' : 'NO') . "\n";
echo "✅ Contains description class: " . ($has_description ? 'YES' : 'NO') . "\n";

echo "\n========================================\n";
echo "Test Complete!\n";
echo "========================================\n";
?>

<?php
/**
 * Setup Script for TRIUU Sermons Manager
 * This script activates the plugin and adds sample sermon data
 */

define('WP_USE_THEMES', false);
require_once(__DIR__ . '/wordpress/wp-load.php');

echo "========================================\n";
echo "TRIUU Sermons Manager Setup\n";
echo "========================================\n\n";

// Activate the plugin
$plugin_path = 'triuu-sermons-manager/triuu-sermons-manager.php';
$active_plugins = get_option('active_plugins', array());

if (!in_array($plugin_path, $active_plugins)) {
    $active_plugins[] = $plugin_path;
    update_option('active_plugins', $active_plugins);
    echo "✅ Plugin activated: TRIUU Sermons Manager\n";
} else {
    echo "ℹ️  Plugin already active: TRIUU Sermons Manager\n";
}

// Trigger plugin initialization
do_action('activate_' . $plugin_path);

// Flush rewrite rules
flush_rewrite_rules();
echo "✅ Rewrite rules flushed\n\n";

// Set the monthly theme
update_option('triuu_monthly_theme', 'October 2025 - Spiritual theme: Cultivating Compassion');
echo "✅ Monthly theme set: October 2025 - Spiritual theme: Cultivating Compassion\n\n";

// Sample sermon data - using dates from Oct 20, 2025 onwards (future dates)
$sermons = array(
    array(
        'date' => '2025-10-20',
        'title' => 'Strong Like Water',
        'reverend' => 'Rev. Kristina Spaude',
        'description' => 'We have all experienced trauma. Many of us are still grappling with it, along with other challenging circumstances in our individual and collective lives. This morning we will celebrate our annual Ingathering and Water Communion service by considering what lessons we can learn from water. If you have water to bring, you\'re welcome to, but water will be provided during the service.'
    ),
    array(
        'date' => '2025-10-27',
        'title' => 'A Free and Responsible Search for Truth and Meaning',
        'reverend' => 'Rev. Kristina Spaude',
        'description' => 'Drawing lessons about the importance of freedom of the press from journalists Maria Ressa and Masha Gessen, we\'ll explore what it means to search for truth and meaning at a time when journalists and news media are demonized.'
    ),
    array(
        'date' => '2025-11-03',
        'title' => 'Lost, Found, and now I\'m Woke',
        'reverend' => 'Rev. Kathy Schmitz',
        'description' => 'The popular hymn, "Amazing Grace," speaks of the gift of greater understanding that comes with a wider perspective. What might that look like in our world today?'
    ),
    array(
        'date' => '2025-11-10',
        'title' => 'Myths and Monsters',
        'reverend' => 'Rev. Kristina Spaude',
        'description' => 'Myths and monsters are two categories of stories that regularly draw Rev. Kristina\'s attention. Today we\'ll explore what we can learn about ourselves and the world by engaging some of these narratives, and what they can teach us about compassion.'
    ),
);

echo "Adding sermon data...\n";
echo "--------------------\n";

$added_count = 0;
$skipped_count = 0;

foreach ($sermons as $sermon) {
    // Check if sermon already exists
    $existing = get_posts(array(
        'post_type' => 'sermon',
        'post_title' => $sermon['title'],
        'post_status' => 'any',
        'posts_per_page' => 1
    ));
    
    if (!empty($existing)) {
        echo "⏭️  Skipped (already exists): {$sermon['title']}\n";
        $skipped_count++;
        continue;
    }
    
    // Create sermon post
    $post_id = wp_insert_post(array(
        'post_type' => 'sermon',
        'post_title' => $sermon['title'],
        'post_status' => 'publish',
        'post_author' => 1
    ));
    
    if ($post_id && !is_wp_error($post_id)) {
        // Add custom fields
        update_post_meta($post_id, '_sermon_date', $sermon['date']);
        update_post_meta($post_id, '_sermon_reverend', $sermon['reverend']);
        update_post_meta($post_id, '_sermon_description', $sermon['description']);
        
        echo "✅ Added: {$sermon['title']} ({$sermon['date']})\n";
        $added_count++;
    } else {
        echo "❌ Failed: {$sermon['title']}\n";
    }
}

echo "\n========================================\n";
echo "Setup Complete!\n";
echo "========================================\n";
echo "Added: {$added_count} sermons\n";
echo "Skipped: {$skipped_count} sermons\n";
echo "\n";
echo "Next steps:\n";
echo "1. Visit WordPress admin at /wp-admin\n";
echo "2. Go to Sermons menu to manage sermons\n";
echo "3. Go to Sermons > Monthly Theme to update the theme\n";
echo "4. View the frontend at /services page\n";
echo "========================================\n";
?>

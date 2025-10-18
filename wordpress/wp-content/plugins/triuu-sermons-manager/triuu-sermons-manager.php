<?php
/**
 * Plugin Name: TRIUU Sermons Manager
 * Plugin URI: https://triuu.org
 * Description: Manage sermons with custom post type, monthly themes, and dynamic frontend display
 * Version: 1.0.0
 * Author: TRIUU
 * Author URI: https://triuu.org
 * Text Domain: triuu-sermons
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

class TRIUU_Sermons_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_sermon_post_type'));
        add_action('add_meta_boxes', array($this, 'add_sermon_metaboxes'));
        add_action('save_post_sermon', array($this, 'save_sermon_meta'), 10, 2);
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_shortcode('triuu_upcoming_sermons', array($this, 'upcoming_sermons_shortcode'));
    }
    
    public function register_sermon_post_type() {
        $labels = array(
            'name'                  => _x('Sermons', 'Post type general name', 'triuu-sermons'),
            'singular_name'         => _x('Sermon', 'Post type singular name', 'triuu-sermons'),
            'menu_name'             => _x('Sermons', 'Admin Menu text', 'triuu-sermons'),
            'name_admin_bar'        => _x('Sermon', 'Add New on Toolbar', 'triuu-sermons'),
            'add_new'               => __('Add New', 'triuu-sermons'),
            'add_new_item'          => __('Add New Sermon', 'triuu-sermons'),
            'new_item'              => __('New Sermon', 'triuu-sermons'),
            'edit_item'             => __('Edit Sermon', 'triuu-sermons'),
            'view_item'             => __('View Sermon', 'triuu-sermons'),
            'all_items'             => __('All Sermons', 'triuu-sermons'),
            'search_items'          => __('Search Sermons', 'triuu-sermons'),
            'parent_item_colon'     => __('Parent Sermons:', 'triuu-sermons'),
            'not_found'             => __('No sermons found.', 'triuu-sermons'),
            'not_found_in_trash'    => __('No sermons found in Trash.', 'triuu-sermons'),
            'featured_image'        => _x('Sermon Cover Image', 'Overrides the "Featured Image" phrase', 'triuu-sermons'),
            'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'triuu-sermons'),
            'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'triuu-sermons'),
            'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'triuu-sermons'),
            'archives'              => _x('Sermon archives', 'The post type archive label used in nav menus', 'triuu-sermons'),
            'insert_into_item'      => _x('Insert into sermon', 'Overrides the "Insert into post"/"Insert into page" phrase', 'triuu-sermons'),
            'uploaded_to_this_item' => _x('Uploaded to this sermon', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase', 'triuu-sermons'),
            'filter_items_list'     => _x('Filter sermons list', 'Screen reader text for the filter links heading on the post type listing screen', 'triuu-sermons'),
            'items_list_navigation' => _x('Sermons list navigation', 'Screen reader text for the pagination heading on the post type listing screen', 'triuu-sermons'),
            'items_list'            => _x('Sermons list', 'Screen reader text for the items list heading on the post type listing screen', 'triuu-sermons'),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'sermons'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-book-alt',
            'supports'           => array('title', 'editor', 'thumbnail', 'revisions'),
            'show_in_rest'       => true,
        );
        
        register_post_type('sermon', $args);
    }
    
    public function add_sermon_metaboxes() {
        add_meta_box(
            'sermon_details',
            __('Sermon Details', 'triuu-sermons'),
            array($this, 'render_sermon_details_metabox'),
            'sermon',
            'normal',
            'high'
        );
    }
    
    public function render_sermon_details_metabox($post) {
        wp_nonce_field('triuu_sermon_meta_nonce', 'triuu_sermon_nonce');
        
        $sermon_date = get_post_meta($post->ID, '_sermon_date', true);
        $reverend = get_post_meta($post->ID, '_sermon_reverend', true);
        $description = get_post_meta($post->ID, '_sermon_description', true);
        ?>
        <div style="padding: 10px 0;">
            <p>
                <label for="sermon_date" style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php _e('Sermon Date', 'triuu-sermons'); ?> <span style="color: red;">*</span>
                </label>
                <input type="date" id="sermon_date" name="sermon_date" value="<?php echo esc_attr($sermon_date); ?>" style="width: 100%; max-width: 300px; padding: 5px;" required />
            </p>
            
            <p style="margin-top: 15px;">
                <label for="sermon_reverend" style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php _e('Reverend', 'triuu-sermons'); ?> <span style="color: red;">*</span>
                </label>
                <input type="text" id="sermon_reverend" name="sermon_reverend" value="<?php echo esc_attr($reverend); ?>" style="width: 100%; max-width: 500px; padding: 5px;" placeholder="e.g., Rev. Kristina Spaude" required />
            </p>
            
            <p style="margin-top: 15px;">
                <label for="sermon_description" style="display: block; font-weight: 600; margin-bottom: 5px;">
                    <?php _e('Sermon Description', 'triuu-sermons'); ?> <span style="color: red;">*</span>
                </label>
                <textarea id="sermon_description" name="sermon_description" rows="6" style="width: 100%; padding: 5px;" placeholder="Enter the sermon description..." required><?php echo esc_textarea($description); ?></textarea>
            </p>
            
            <p style="margin-top: 10px; color: #666; font-size: 12px;">
                <em><?php _e('* Required fields', 'triuu-sermons'); ?></em>
            </p>
        </div>
        <?php
    }
    
    public function save_sermon_meta($post_id, $post) {
        if (!isset($_POST['triuu_sermon_nonce']) || !wp_verify_nonce($_POST['triuu_sermon_nonce'], 'triuu_sermon_meta_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['sermon_date'])) {
            update_post_meta($post_id, '_sermon_date', sanitize_text_field($_POST['sermon_date']));
        }
        
        if (isset($_POST['sermon_reverend'])) {
            update_post_meta($post_id, '_sermon_reverend', sanitize_text_field($_POST['sermon_reverend']));
        }
        
        if (isset($_POST['sermon_description'])) {
            update_post_meta($post_id, '_sermon_description', sanitize_textarea_field($_POST['sermon_description']));
        }
    }
    
    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=sermon',
            __('Monthly Theme Settings', 'triuu-sermons'),
            __('Monthly Theme', 'triuu-sermons'),
            'manage_options',
            'triuu-sermons-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting(
            'triuu_sermons_settings',
            'triuu_monthly_theme',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );
        
        add_settings_section(
            'triuu_theme_section',
            __('Monthly Spiritual Theme', 'triuu-sermons'),
            array($this, 'theme_section_callback'),
            'triuu-sermons-settings'
        );
        
        add_settings_field(
            'triuu_monthly_theme',
            __('Current Theme', 'triuu-sermons'),
            array($this, 'monthly_theme_field_callback'),
            'triuu-sermons-settings',
            'triuu_theme_section'
        );
    }
    
    public function theme_section_callback() {
        echo '<p>' . __('Set the current monthly spiritual theme that will be displayed above upcoming sermons.', 'triuu-sermons') . '</p>';
    }
    
    public function monthly_theme_field_callback() {
        $value = get_option('triuu_monthly_theme', '');
        ?>
        <input type="text" 
               id="triuu_monthly_theme" 
               name="triuu_monthly_theme" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" 
               placeholder="e.g., October 2025 - Spiritual theme: Cultivating Compassion" 
               style="width: 100%; max-width: 600px; padding: 8px;" />
        <p class="description">
            <?php _e('Example: "October 2025 - Spiritual theme: Cultivating Compassion"', 'triuu-sermons'); ?>
        </p>
        <?php
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'triuu_sermons_messages',
                'triuu_sermons_message',
                __('Settings Saved', 'triuu-sermons'),
                'updated'
            );
        }
        
        settings_errors('triuu_sermons_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <form action="options.php" method="post">
                    <?php
                    settings_fields('triuu_sermons_settings');
                    do_settings_sections('triuu-sermons-settings');
                    submit_button(__('Save Monthly Theme', 'triuu-sermons'));
                    ?>
                </form>
            </div>
            
            <?php
            $current_theme = get_option('triuu_monthly_theme', '');
            if (!empty($current_theme)) :
            ?>
            <div style="background: #f0f0f1; padding: 15px; margin-top: 20px; border-left: 4px solid #2271b1;">
                <h3 style="margin-top: 0;"><?php _e('Current Active Theme', 'triuu-sermons'); ?></h3>
                <p style="font-size: 16px; color: #2271b1; font-weight: 600; margin: 0;">
                    <?php echo esc_html($current_theme); ?>
                </p>
                <p style="margin: 10px 0 0 0; color: #666;">
                    <em><?php _e('This theme is currently being displayed on the frontend.', 'triuu-sermons'); ?></em>
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        if ('sermon' !== $post_type) {
            return;
        }
        
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
        }
    }
    
    public function upcoming_sermons_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 4
        ), $atts);
        
        $today = date('Y-m-d');
        
        $args = array(
            'post_type' => 'sermon',
            'posts_per_page' => intval($atts['limit']),
            'post_status' => 'publish',
            'meta_key' => '_sermon_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_sermon_date',
                    'value' => $today,
                    'compare' => '>=',
                    'type' => 'CHAR'
                )
            )
        );
        
        $sermons_query = new WP_Query($args);
        
        ob_start();
        
        if ($sermons_query->have_posts()) :
            $monthly_theme = get_option('triuu_monthly_theme', '');
            
            if (!empty($monthly_theme)) :
            ?>
            <p class="theme-subtitle"><?php echo esc_html($monthly_theme); ?></p>
            <?php endif; ?>
            
            <div class="service-cards">
                <?php while ($sermons_query->have_posts()) : $sermons_query->the_post(); 
                    $sermon_date = get_post_meta(get_the_ID(), '_sermon_date', true);
                    $reverend = get_post_meta(get_the_ID(), '_sermon_reverend', true);
                    $description = get_post_meta(get_the_ID(), '_sermon_description', true);
                    
                    $formatted_date = '';
                    if (!empty($sermon_date)) {
                        $date_obj = DateTime::createFromFormat('Y-m-d', $sermon_date);
                        if ($date_obj) {
                            $formatted_date = $date_obj->format('M j');
                        }
                    }
                ?>
                <div class="service-card">
                    <div class="date"><?php echo esc_html($formatted_date); ?>:</div>
                    <div class="title"><?php echo esc_html(get_the_title()); ?></div>
                    <div class="speaker"><?php echo esc_html($reverend); ?></div>
                    <div class="description"><?php echo esc_html($description); ?></div>
                </div>
                <?php endwhile; ?>
            </div>
            
        <?php else : ?>
            <div class="service-cards">
                <p style="text-align: center; color: #666; padding: 20px;">
                    <?php _e('No upcoming sermons scheduled at this time.', 'triuu-sermons'); ?>
                </p>
            </div>
        <?php endif;
        
        wp_reset_postdata();
        
        return ob_get_clean();
    }
}

TRIUU_Sermons_Manager::get_instance();

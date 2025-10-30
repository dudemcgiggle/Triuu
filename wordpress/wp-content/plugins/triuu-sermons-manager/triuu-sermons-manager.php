<?php
/**
 * Plugin Name: TRIUU Sermons Manager
 * Plugin URI: https://triuu.org
 * Description: Simple sermon management with monthly themes and dynamic frontend display
 * Version: 2.0.0
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_sermon_actions'));
        add_action('admin_init', array($this, 'register_settings'));
        add_shortcode('triuu_upcoming_sermons', array($this, 'upcoming_sermons_shortcode'));
        add_shortcode('triuu_next_sermon', array($this, 'next_sermon_shortcode'));
        add_shortcode('triuu_featured_sermon', array($this, 'featured_sermon_shortcode'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Sermons', 'triuu-sermons'),
            __('Sermons', 'triuu-sermons'),
            'manage_options',
            'triuu-sermons',
            array($this, 'render_manage_sermons_page'),
            'dashicons-book-alt',
            5
        );
        
        add_submenu_page(
            'triuu-sermons',
            __('Manage Sermons', 'triuu-sermons'),
            __('Manage Sermons', 'triuu-sermons'),
            'manage_options',
            'triuu-sermons',
            array($this, 'render_manage_sermons_page')
        );
        
        add_submenu_page(
            'triuu-sermons',
            __('Monthly Theme', 'triuu-sermons'),
            __('Monthly Theme', 'triuu-sermons'),
            'manage_options',
            'triuu-sermons-theme',
            array($this, 'render_theme_settings_page')
        );
    }
    
    public function handle_sermon_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_POST['triuu_save_sermon']) && check_admin_referer('triuu_sermon_action', 'triuu_sermon_nonce')) {
            $this->save_sermon();
        }
        
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['sermon_id']) && check_admin_referer('triuu_delete_sermon_' . $_GET['sermon_id'], 'nonce')) {
            $this->delete_sermon(sanitize_text_field($_GET['sermon_id']));
        }
    }
    
    private function save_sermon() {
        $sermons = get_option('triuu_sermons_data', array());
        
        $sermon_id = isset($_POST['sermon_id']) ? sanitize_text_field($_POST['sermon_id']) : '';
        
        $sermon_data = array(
            'id' => $sermon_id ? $sermon_id : uniqid('sermon_', true),
            'title' => sanitize_text_field($_POST['sermon_title']),
            'date' => sanitize_text_field($_POST['sermon_date']),
            'reverend' => sanitize_text_field($_POST['sermon_reverend']),
            'description' => sanitize_textarea_field($_POST['sermon_description'])
        );
        
        if ($sermon_id) {
            foreach ($sermons as $key => $sermon) {
                if ($sermon['id'] == $sermon_id) {
                    $sermons[$key] = $sermon_data;
                    break;
                }
            }
            $message = 'updated';
        } else {
            $sermons[] = $sermon_data;
            $message = 'added';
        }
        
        update_option('triuu_sermons_data', $sermons);
        
        wp_redirect(admin_url('admin.php?page=triuu-sermons&message=' . $message));
        exit;
    }
    
    private function delete_sermon($sermon_id) {
        $sermons = get_option('triuu_sermons_data', array());
        
        foreach ($sermons as $key => $sermon) {
            if ($sermon['id'] == $sermon_id) {
                unset($sermons[$key]);
                break;
            }
        }
        
        $sermons = array_values($sermons);
        update_option('triuu_sermons_data', $sermons);
        
        wp_redirect(admin_url('admin.php?page=triuu-sermons&message=deleted'));
        exit;
    }
    
    private function get_sermon_by_id($sermon_id) {
        $sermons = get_option('triuu_sermons_data', array());
        $sermons = wp_unslash($sermons);
        
        foreach ($sermons as $sermon) {
            if ($sermon['id'] == $sermon_id) {
                return $sermon;
            }
        }
        
        return null;
    }
    
    public function render_manage_sermons_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $editing = isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['sermon_id']);
        $sermon_to_edit = $editing ? $this->get_sermon_by_id(sanitize_text_field($_GET['sermon_id'])) : null;
        
        $adding = isset($_GET['action']) && $_GET['action'] === 'add';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php
            if (isset($_GET['message'])) {
                $message = '';
                $type = 'success';
                
                switch ($_GET['message']) {
                    case 'added':
                        $message = __('Sermon added successfully!', 'triuu-sermons');
                        break;
                    case 'updated':
                        $message = __('Sermon updated successfully!', 'triuu-sermons');
                        break;
                    case 'deleted':
                        $message = __('Sermon deleted successfully!', 'triuu-sermons');
                        break;
                }
                
                if ($message) {
                    echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
                }
            }
            ?>
            
            <?php if ($adding || $editing) : ?>
                <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php echo $editing ? __('Edit Sermon', 'triuu-sermons') : __('Add New Sermon', 'triuu-sermons'); ?></h2>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('triuu_sermon_action', 'triuu_sermon_nonce'); ?>
                        
                        <?php if ($editing && $sermon_to_edit) : ?>
                            <input type="hidden" name="sermon_id" value="<?php echo esc_attr($sermon_to_edit['id']); ?>" />
                        <?php endif; ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="sermon_title"><?php _e('Sermon Title', 'triuu-sermons'); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="sermon_title" 
                                           name="sermon_title" 
                                           value="<?php echo $editing && $sermon_to_edit ? esc_attr($sermon_to_edit['title']) : ''; ?>" 
                                           class="regular-text" 
                                           required 
                                           style="width: 100%; max-width: 600px;" />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="sermon_date"><?php _e('Sermon Date', 'triuu-sermons'); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="date" 
                                           id="sermon_date" 
                                           name="sermon_date" 
                                           value="<?php echo $editing && $sermon_to_edit ? esc_attr($sermon_to_edit['date']) : ''; ?>" 
                                           required 
                                           style="padding: 5px;" />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="sermon_reverend"><?php _e('Reverend', 'triuu-sermons'); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="sermon_reverend" 
                                           name="sermon_reverend" 
                                           value="<?php echo $editing && $sermon_to_edit ? esc_attr($sermon_to_edit['reverend']) : ''; ?>" 
                                           class="regular-text" 
                                           placeholder="e.g., Rev. Kristina Spaude" 
                                           required 
                                           style="width: 100%; max-width: 600px;" />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="sermon_description"><?php _e('Sermon Description', 'triuu-sermons'); ?> <span style="color: red;">*</span></label>
                                </th>
                                <td>
                                    <textarea id="sermon_description" 
                                              name="sermon_description" 
                                              rows="8" 
                                              class="large-text" 
                                              placeholder="Enter the sermon description..." 
                                              required 
                                              style="width: 100%; max-width: 800px;"><?php echo $editing && $sermon_to_edit ? esc_textarea($sermon_to_edit['description']) : ''; ?></textarea>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="triuu_save_sermon" class="button button-primary" value="<?php echo $editing ? __('Update Sermon', 'triuu-sermons') : __('Save Sermon', 'triuu-sermons'); ?>" />
                            <a href="<?php echo admin_url('admin.php?page=triuu-sermons'); ?>" class="button"><?php _e('Cancel', 'triuu-sermons'); ?></a>
                        </p>
                    </form>
                </div>
            <?php else : ?>
                <div style="margin: 20px 0;">
                    <a href="<?php echo admin_url('admin.php?page=triuu-sermons&action=add'); ?>" class="button button-primary">
                        <?php _e('Add New Sermon', 'triuu-sermons'); ?>
                    </a>
                </div>
                
                <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php _e('All Sermons', 'triuu-sermons'); ?></h2>
                    
                    <?php
                    $sermons = get_option('triuu_sermons_data', array());
                    $sermons = wp_unslash($sermons);
                    
                    usort($sermons, function($a, $b) {
                        return strcmp($b['date'], $a['date']);
                    });
                    
                    if (!empty($sermons)) :
                    ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 30%;"><?php _e('Title', 'triuu-sermons'); ?></th>
                                    <th style="width: 15%;"><?php _e('Date', 'triuu-sermons'); ?></th>
                                    <th style="width: 20%;"><?php _e('Reverend', 'triuu-sermons'); ?></th>
                                    <th style="width: 25%;"><?php _e('Description', 'triuu-sermons'); ?></th>
                                    <th style="width: 10%;"><?php _e('Actions', 'triuu-sermons'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sermons as $sermon) : 
                                    $description_truncated = strlen($sermon['description']) > 100 ? substr($sermon['description'], 0, 100) . '...' : $sermon['description'];
                                    
                                    $formatted_date = '';
                                    if (!empty($sermon['date'])) {
                                        $date_obj = DateTime::createFromFormat('Y-m-d', $sermon['date']);
                                        if ($date_obj) {
                                            $formatted_date = $date_obj->format('F j, Y');
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($sermon['title']); ?></strong></td>
                                    <td><?php echo esc_html($formatted_date); ?></td>
                                    <td><?php echo esc_html($sermon['reverend']); ?></td>
                                    <td><?php echo esc_html($description_truncated); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=triuu-sermons&action=edit&sermon_id=' . $sermon['id']); ?>" class="button button-small">
                                            <?php _e('Edit', 'triuu-sermons'); ?>
                                        </a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=triuu-sermons&action=delete&sermon_id=' . $sermon['id']), 'triuu_delete_sermon_' . $sermon['id'], 'nonce'); ?>" 
                                           class="button button-small" 
                                           onclick="return confirm('<?php _e('Are you sure you want to delete this sermon?', 'triuu-sermons'); ?>');">
                                            <?php _e('Delete', 'triuu-sermons'); ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p style="padding: 20px 0; color: #666;">
                            <?php _e('No sermons found. Click "Add New Sermon" to create your first sermon.', 'triuu-sermons'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
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
    
    public function render_theme_settings_page() {
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
    
    public function upcoming_sermons_shortcode($atts) {
        $today = current_time('Y-m-d');
        $sermons = get_option('triuu_sermons_data', array());
        $sermons = wp_unslash($sermons);
        
        $upcoming_sermons = array_filter($sermons, function($sermon) use ($today) {
            return $sermon['date'] >= $today;
        });
        
        usort($upcoming_sermons, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        ob_start();
        
        if (!empty($upcoming_sermons)) :
            $monthly_theme = get_option('triuu_monthly_theme', '');
            
            if (!empty($monthly_theme)) :
            ?>
            <p class="theme-subtitle"><?php echo esc_html($monthly_theme); ?></p>
            <?php endif; ?>
            
            <div class="service-cards">
                <?php foreach ($upcoming_sermons as $sermon) : 
                    $formatted_date = '';
                    if (!empty($sermon['date'])) {
                        $date_obj = DateTime::createFromFormat('Y-m-d', $sermon['date']);
                        if ($date_obj) {
                            $formatted_date = $date_obj->format('M j');
                        }
                    }
                ?>
                <div class="service-card">
                    <div class="date"><?php echo esc_html($formatted_date); ?>:</div>
                    <div class="title"><?php echo esc_html($sermon['title']); ?></div>
                    <div class="speaker"><?php echo esc_html($sermon['reverend']); ?></div>
                    <div class="description"><?php echo esc_html($sermon['description']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
        <?php else : ?>
            <div class="service-cards">
                <p style="text-align: center; color: #666; padding: 20px;">
                    <?php _e('No upcoming sermons scheduled at this time.', 'triuu-sermons'); ?>
                </p>
            </div>
        <?php endif;
        
        return ob_get_clean();
    }
    
    public function next_sermon_shortcode($atts) {
        $atts = shortcode_atts(array(
            'format' => 'full', // 'full', 'title', 'date', 'speaker'
        ), $atts);
        
        $today = current_time('Y-m-d');
        $sermons = get_option('triuu_sermons_data', array());
        $sermons = wp_unslash($sermons);
        
        $upcoming_sermons = array_filter($sermons, function($sermon) use ($today) {
            return $sermon['date'] >= $today;
        });
        
        usort($upcoming_sermons, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        if (empty($upcoming_sermons)) {
            return 'Sunday Service (In person &amp; Zoom)';
        }
        
        $next_sermon = reset($upcoming_sermons);
        
        $formatted_date = '';
        if (!empty($next_sermon['date'])) {
            $date_obj = DateTime::createFromFormat('Y-m-d', $next_sermon['date']);
            if ($date_obj) {
                $formatted_date = $date_obj->format('F j, Y');
            }
        }
        
        ob_start();
        
        switch ($atts['format']) {
            case 'title':
                echo esc_html($next_sermon['title']);
                break;
            case 'date':
                echo esc_html($formatted_date);
                break;
            case 'speaker':
                echo esc_html($next_sermon['reverend']);
                break;
            case 'full':
            default:
                ?>
                <strong><?php echo esc_html($next_sermon['title']); ?></strong> by <?php echo esc_html($next_sermon['reverend']); ?>
                <?php
                break;
        }
        
        return ob_get_clean();
    }
    
    public function featured_sermon_shortcode($atts) {
        $today = current_time('Y-m-d');
        $sermons = get_option('triuu_sermons_data', array());
        $sermons = wp_unslash($sermons);
        
        $upcoming_sermons = array_filter($sermons, function($sermon) use ($today) {
            return $sermon['date'] >= $today;
        });
        
        usort($upcoming_sermons, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        if (empty($upcoming_sermons)) {
            // Fallback if no upcoming sermons
            ob_start();
            ?>
            <div class="feature">
                <div class="date-badge" aria-hidden="true">TBD</div>
                <div>
                    <div class="kicker">Sunday Service</div>
                    <h2 id="feature-title">Sunday Service</h2>
                    <div class="meta">Live in person and on Zoom</div>
                    <p>Check back soon for upcoming sermon information.</p>
                    <p style="margin:.75rem 0 0 0;">
                        <a class="btn" href="https://zoom.us/j/95277568906?pwd=PJeDQqyY1WMwoJRrkI9Xn4sQG36P2f.1" target="_blank" rel="noopener">Launch Zoom Service</a>
                    </p>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
        
        $next_sermon = reset($upcoming_sermons);
        
        $date_badge = '';
        if (!empty($next_sermon['date'])) {
            $date_obj = DateTime::createFromFormat('Y-m-d', $next_sermon['date']);
            if ($date_obj) {
                $date_badge = $date_obj->format('M j');
            }
        }
        
        ob_start();
        ?>
        <div class="feature">
            <div class="date-badge" aria-hidden="true"><?php echo esc_html($date_badge); ?></div>
            <div>
                <div class="kicker">Sunday Service</div>
                <h2 id="feature-title"><?php echo esc_html($next_sermon['title']); ?></h2>
                <div class="meta"><?php echo esc_html($next_sermon['reverend']); ?> &middot; Live in person and on Zoom</div>
                <p><?php echo esc_html($next_sermon['description']); ?></p>
                <p style="margin:.75rem 0 0 0;">
                    <a class="btn" href="https://zoom.us/j/95277568906?pwd=PJeDQqyY1WMwoJRrkI9Xn4sQG36P2f.1" target="_blank" rel="noopener">Launch Zoom Service</a>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

TRIUU_Sermons_Manager::get_instance();

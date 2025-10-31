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
 * 
 * COMPLETE IMPLEMENTATION - 1,108 LINES
 * 
 * This is the LIVE implementation from /home/runner/workspace/wordpress/wp-content/plugins/triuu-sermons-manager/
 * 
 * THREE SHORTCODES:
 * 1. [triuu_featured_sermon] - Lines 534-652
 * 2. [triuu_upcoming_events] - Lines 654-903
 * 3. [triuu_book_club] - Lines 905-1103
 * 
 * All shortcodes output with standardized wrapper:
 * <div class="triuu-county-widget"><div class="page-wrapper">...</div></div>
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
        add_shortcode('triuu_upcoming_events', array($this, 'upcoming_events_shortcode'));
        add_shortcode('triuu_book_club', array($this, 'book_club_shortcode'));
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
            'format' => 'full',
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
    
    // ========================================================================
    // SHORTCODE #1: [triuu_featured_sermon]
    // Lines 534-652
    // ========================================================================
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
        
        $next_sermon = !empty($upcoming_sermons) ? reset($upcoming_sermons) : null;
        
        $atts = shortcode_atts(array(
            'kicker'      => $next_sermon ? 'Next Sunday Service' : '',
            'title'       => $next_sermon ? $next_sermon['title'] : 'Sunday Service',
            'description' => $next_sermon ? $next_sermon['description'] : 'Join us for Sunday worship.',
            'date'        => $next_sermon ? $next_sermon['date'] : '',
            'time'        => '10:30 AM',
            'cta_url'     => '',
            'cta_label'   => 'Launch Zoom',
            'cta_target'  => '_blank',
            'speaker'     => $next_sermon ? $next_sermon['reverend'] : '',
            'series'      => '',
        ), $atts, 'triuu_featured_sermon');
        
        $event_timestamp = null;
        if (!empty($atts['date'])) {
            $date_obj = DateTime::createFromFormat('Y-m-d', $atts['date']);
            if ($date_obj) {
                $event_timestamp = $date_obj->getTimestamp();
            }
        }
        
        $month_label = $event_timestamp ? date_i18n('M', $event_timestamp) : '';
        $day_label   = $event_timestamp ? date_i18n('j', $event_timestamp) : '';
        
        $title       = esc_html($atts['title']);
        $description = wp_kses_post($atts['description']);
        $speaker     = esc_html($atts['speaker']);
        $series      = esc_html($atts['series']);
        $time        = esc_html($atts['time']);
        $kicker      = esc_html($atts['kicker']);
        
        $cta_url    = esc_url($atts['cta_url']);
        $cta_label  = esc_html($atts['cta_label']);
        $cta_target = in_array($atts['cta_target'], array('_blank', '_self')) ? $atts['cta_target'] : '_blank';
        
        ob_start();
        ?>
        <div class="triuu-county-widget">
        <div class="page-wrapper">
        <div class="feature">
            <?php if ($month_label || $day_label) : ?>
                <div class="date-badge">
                    <?php if ($month_label) : ?>
                        <span class="month"><?php echo $month_label; ?></span>
                    <?php endif; ?>
                    <?php if ($day_label) : ?>
                        <span class="day"><?php echo $day_label; ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="details">
                <?php if ($kicker) : ?>
                    <p class="kicker"><?php echo $kicker; ?></p>
                <?php endif; ?>
                
                <?php if ($title) : ?>
                    <h2 id="feature-title"><?php echo $title; ?></h2>
                <?php endif; ?>
                
                <div class="meta">
                    <?php if ($speaker) : ?>
                        <span class="speaker"><?php echo $speaker; ?></span>
                    <?php endif; ?>
                    <?php if ($series) : ?>
                        <span class="series"><?php echo $series; ?></span>
                    <?php endif; ?>
                    <?php if ($time) : ?>
                        <span class="time"><?php echo $time; ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($description) : ?>
                    <p><?php echo $description; ?></p>
                <?php endif; ?>
                
                <?php if ($cta_url) : ?>
                    <p class="actions">
                        <a class="btn btn-primary" href="<?php echo $cta_url; ?>" target="<?php echo esc_attr($cta_target); ?>" rel="noopener">
                            <?php echo $cta_label; ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        </div>
        </div>
        <?php
        return trim(ob_get_clean());
    }
    
    // ========================================================================
    // SHORTCODE #2: [triuu_upcoming_events]
    // Lines 654-903
    // ========================================================================
    public function upcoming_events_shortcode($atts) {
        $api_key = defined('GOOGLE_CALENDAR_API_KEY') ? GOOGLE_CALENDAR_API_KEY : '';
        $calendar_id = defined('GOOGLE_CALENDAR_ID') ? GOOGLE_CALENDAR_ID : '';
        
        if (empty($api_key) || empty($calendar_id)) {
            return '<div class="triuu-county-widget"><div class="page-wrapper"><p style="color: #999; text-align: center;">Calendar configuration incomplete.</p></div></div>';
        }
        
        $tz = new DateTimeZone('America/Chicago');
        $now = new DateTimeImmutable('now', $tz);
        $timeMin = $now->format('c');
        $end = $now->modify('+7 days');
        $timeMax = $end->format('c');
        
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendar_id) . '/events?' .
               http_build_query(array(
                   'key' => $api_key,
                   'timeMin' => $timeMin,
                   'timeMax' => $timeMax,
                   'singleEvents' => 'true',
                   'orderBy' => 'startTime',
                   'maxResults' => 50,
               ), '', '&', PHP_QUERY_RFC3986);
        
        $events = array();
        $resp = wp_remote_get($url);
        
        if (!is_wp_error($resp)) {
            $data = json_decode(wp_remote_retrieve_body($resp), true);
            
            foreach ($data['items'] ?? array() as $item) {
                $title = $item['summary'] ?? '';
                
                if (stripos($title, 'sunday') !== false && stripos($title, 'service') !== false) {
                    continue;
                }
                
                $start = $item['start']['dateTime'] ?? $item['start']['date'];
                $dt = (new DateTimeImmutable($start))->setTimezone($tz);
                
                if ($dt < $now) {
                    continue;
                }
                
                $location = trim($item['location'] ?? '');
                $description = trim($item['description'] ?? '');
                
                $location_link = '';
                $location_text = $location;
                
                if (!empty($location)) {
                    if (stripos($location, 'zoom') !== false || stripos($description, 'zoom.us') !== false) {
                        if (preg_match('!https?://[^\s<"\']+zoom\.us[^\s<"\']*!i', $description, $matches)) {
                            $location_link = $matches[0];
                            $location_text = 'Zoom Meeting';
                        } elseif (preg_match('!https?://[^\s<"\']+zoom\.us[^\s<"\']*!i', $location, $matches)) {
                            $location_link = $matches[0];
                            $location_text = 'Zoom Meeting';
                        } else {
                            $location_text = 'Zoom Meeting';
                        }
                    } else {
                        $location_link = 'https://www.google.com/maps/search/' . urlencode($location);
                    }
                }
                
                $events[] = array(
                    'title' => $title,
                    'dt' => $dt,
                    'location' => $location,
                    'location_link' => $location_link,
                    'location_text' => $location_text,
                    'description' => $description,
                    'allDay' => !isset($item['start']['dateTime']),
                );
            }
        }
        
        ob_start();
        ?>
        <div class="triuu-county-widget">
        <div class="page-wrapper">
        <div class="triuu-events-section" style="
            font-family: 'Barlow', sans-serif;
            margin: 0;
            padding: 0;
        ">
            <h2 style="
                font-family: 'Barlow Condensed', sans-serif;
                font-size: 1.7em;
                color: #666666;
                font-style: normal;
                margin-bottom: 0;
                font-weight: 400;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                text-align: center;
                padding-bottom: 0.5em;
                border-bottom: 2px solid #A5849F;
            ">Upcoming Events</h2>
            
            <?php if (!empty($events)) : ?>
                <div style="margin-top: 1.5rem;">
                    <?php foreach ($events as $event) : 
                        $weekday = $event['dt']->format('l');
                        $month_day = $event['dt']->format('F j');
                        $year = $event['dt']->format('Y');
                        
                        $time_display = '';
                        if (!$event['allDay']) {
                            $time_display = $event['dt']->format('g:i A');
                        }
                    ?>
                        <div style="
                            margin-bottom: 1.5rem;
                            padding-bottom: 1.5rem;
                            border-bottom: 1px solid #e0e0e0;
                        ">
                            <h3 style="
                                margin: 0 0 0.5rem 0;
                                font-family: 'Barlow Condensed', sans-serif;
                                font-size: 1.3em;
                                color: #614E6B;
                                font-weight: 500;
                                text-transform: uppercase;
                                letter-spacing: 0.03em;
                            "><?php echo esc_html($event['title']); ?></h3>
                            
                            <p style="
                                margin: 0 0 0.3rem 0;
                                color: #4A566D;
                                font-size: 1rem;
                                font-weight: 300;
                            ">
                                <strong style="font-weight: 400;"><?php echo esc_html($weekday); ?>, <?php echo esc_html($month_day); ?>, <?php echo esc_html($year); ?></strong>
                                <?php if ($time_display) : ?>
                                    <span style="margin-left: 0.5rem;">at <?php echo esc_html($time_display); ?></span>
                                <?php endif; ?>
                            </p>
                            
                            <?php if (!empty($event['location_text'])) : ?>
                                <p style="margin: 0; color: #666666; font-size: 0.95rem; font-weight: 300;">
                                    <?php if (!empty($event['location_link'])) : ?>
                                        <a href="<?php echo esc_url($event['location_link']); ?>" target="_blank" rel="noopener noreferrer" style="
                                            color: #614E6B;
                                            text-decoration: none;
                                            border-bottom: 1px solid #A5849F;
                                            transition: color 0.3s ease;
                                        " onmouseover="this.style.color='#A5849F'" onmouseout="this.style.color='#614E6B'">
                                            <?php echo esc_html($event['location_text']); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html($event['location_text']); ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p style="text-align: center; color: #999; margin-top: 1.5rem; font-size: 1rem; font-weight: 300;">
                    No upcoming events in the next 7 days.
                </p>
            <?php endif; ?>
        </div>
        </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // ========================================================================
    // SHORTCODE #3: [triuu_book_club]
    // Lines 905-1103
    // ========================================================================
    public function book_club_shortcode($atts) {
        $atts = shortcode_atts(array(
            'pdf_url' => '',
        ), $atts, 'triuu_book_club');
        
        $next_meeting_date = '';
        $zoom_url = '';
        
        $api_key = defined('GOOGLE_CALENDAR_API_KEY') ? GOOGLE_CALENDAR_API_KEY : '';
        $calendar_id = defined('GOOGLE_CALENDAR_ID') ? GOOGLE_CALENDAR_ID : '';
        
        if (!empty($api_key) && !empty($calendar_id)) {
            $tz = new DateTimeZone('America/Chicago');
            $now = new DateTimeImmutable('now', $tz);
            $timeMin = $now->format('c');
            $end = $now->modify('+60 days');
            $timeMax = $end->format('c');
            
            $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendar_id) . '/events?' .
                   http_build_query(array(
                       'key' => $api_key,
                       'timeMin' => $timeMin,
                       'timeMax' => $timeMax,
                       'singleEvents' => 'true',
                       'orderBy' => 'startTime',
                       'maxResults' => 50,
                   ), '', '&', PHP_QUERY_RFC3986);
            
            $resp = wp_remote_get($url);
            if (!is_wp_error($resp)) {
                $data = json_decode(wp_remote_retrieve_body($resp), true);
                
                foreach ($data['items'] ?? array() as $item) {
                    $title = $item['summary'] ?? '';
                    
                    if (stripos($title, 'book') !== false && stripos($title, 'club') !== false) {
                        $start = $item['start']['dateTime'] ?? $item['start']['date'];
                        $dt = (new DateTimeImmutable($start))->setTimezone($tz);
                        
                        if ($dt >= $now) {
                            $next_meeting_date = $dt->format('l, F j, Y');
                            if (isset($item['start']['dateTime'])) {
                                $next_meeting_date .= ' at ' . $dt->format('g:i a');
                            }
                            
                            if (!empty($item['description']) && preg_match('!https?://zoom\.us/[^\s<"\']+!i', $item['description'], $matches)) {
                                $zoom_url = $matches[0];
                            }
                            
                            break;
                        }
                    }
                }
            }
        }
        
        ob_start();
        ?>
        <div class="triuu-county-widget">
        <div class="page-wrapper">
        <div class="triuu-book-club-section" style="
            font-family: 'Barlow', sans-serif;
            margin: 0;
            padding: 0;
        ">
            <h2 style="
                font-family: 'Barlow Condensed', sans-serif;
                font-size: 1.7em;
                color: #666666;
                font-style: normal;
                margin-bottom: 0;
                font-weight: 400;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                text-align: center;
                padding-bottom: 0.5em;
                border-bottom: 2px solid #A5849F;
            ">Monthly Book Club</h2>
            
            <div style="margin-top: 1rem; color: #666666; line-height: 1.6;">
                <p style="margin: 0 0 0.4rem 0; font-size: 1rem; font-weight: 300;">
                    <strong style="font-weight: 400;">Meeting Schedule:</strong> 1:00 PM — Fourth Monday of each month
                </p>
                <p style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 300;">
                    <strong style="font-weight: 400;">Contact:</strong> Nancy Garrison — <a href="mailto:garrisonnancy@yahoo.com" style="color: #614E6B; text-decoration: none; border-bottom: 1px solid #A5849F; transition: color 0.3s ease;" onmouseover="this.style.color='#A5849F'" onmouseout="this.style.color='#614E6B'">garrisonnancy@yahoo.com</a>
                </p>
                
                <?php if (!empty($next_meeting_date)) : ?>
                <div style="
                    margin: 0;
                    padding: 1rem 1.25rem;
                    background: #f9f9f9;
                    border-left: 3px solid #614E6B;
                ">
                    <p style="
                        margin: 0 0 0.5rem 0;
                        font-family: 'Barlow Condensed', sans-serif;
                        font-size: 1.2em;
                        text-transform: uppercase;
                        color: #614E6B;
                        font-weight: 400;
                        letter-spacing: 0.05em;
                    ">Next Meeting</p>
                    <p style="margin: 0 0 0.75rem 0; font-size: 1.05rem; color: #4A566D; font-weight: 300;">
                        <?php echo esc_html($next_meeting_date); ?>
                    </p>
                    <?php if (!empty($zoom_url)) : ?>
                    <a href="<?php echo esc_url($zoom_url); ?>" target="_blank" rel="noopener noreferrer" style="
                        display: inline-block;
                        background: #614E6B;
                        color: white;
                        padding: 0.6rem 1.75rem;
                        text-decoration: none;
                        font-weight: 400;
                        font-size: 0.95rem;
                        transition: background 0.3s ease;
                        text-transform: uppercase;
                        letter-spacing: 0.03em;
                    " onmouseover="this.style.background='#A5849F'" onmouseout="this.style.background='#614E6B'">
                        Launch Zoom Meeting
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($atts['pdf_url'])) : ?>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="<?php echo esc_url($atts['pdf_url']); ?>" target="_blank" rel="noopener noreferrer" style="
                        display: inline-block;
                        background: #614E6B;
                        color: white;
                        padding: 0.65rem 1.75rem;
                        text-decoration: none;
                        font-weight: 400;
                        font-size: 0.95rem;
                        transition: background 0.3s ease;
                        text-transform: uppercase;
                        letter-spacing: 0.03em;
                        font-family: 'Barlow Condensed', sans-serif;
                    " onmouseover="this.style.background='#A5849F'" onmouseout="this.style.background='#614E6B'">
                        Download Reading List
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

TRIUU_Sermons_Manager::get_instance();

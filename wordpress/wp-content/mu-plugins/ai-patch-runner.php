<?php
/**
 * Plugin Name: AI Website Styler (MU)
 * Description: User-friendly AI-powered website styling assistant with visual preview and one-click apply. Helps non-technical users style their WordPress site using plain English.
 * Author: Ken + Helper
 * Version: 3.0.0
 *
 * SECURITY MODEL:
 * - Edits are constrained to WP_CONTENT_DIR (themes, plugins, mu-plugins, uploads)
 * - Every change creates a timestamped snapshot under uploads/ai-patch-runner/snapshots/<id>/
 * - Snapshots include manifest.json and before-blobs for rollback capability
 * - Preview uses transients to avoid duplicate AI calls
 * - Admin UI uses nonces + capability checks
 */

if (!defined('ABSPATH')) exit;

define('AI_PR_VERSION', '3.0.0');
define('AI_PR_PLUGIN_SLUG', 'ai-website-styler');
define('AI_PR_SNAP_ROOT', 'ai-patch-runner/snapshots');
define('AI_PR_LOG_ROOT',  'ai-patch-runner/logs');
define('AI_PR_DEFAULT_EXT_REGEX', '/\.(php|js|css|scss|sass|ts|tsx|json|yml|yaml|xml|html?|txt|md|ini|conf|htaccess)$/i');

/* =========================
 * Config/Allowlists (filters)
 * ========================= */
function ai_pr_allowed_ext_regex() {
    return apply_filters('ai_pr_allowed_ext_regex', AI_PR_DEFAULT_EXT_REGEX);
}

function ai_pr_allowed_roots() {
    $roots = [
        'wp-content'   => ['path' => WP_CONTENT_DIR, 'enabled' => true],
        'mu-plugins'   => ['path' => defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins', 'enabled' => true],
        'plugins'      => ['path' => defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins',  'enabled' => true],
        'themes'       => ['path' => function_exists('get_theme_root') ? get_theme_root() : WP_CONTENT_DIR . '/themes', 'enabled' => true],
        'uploads'      => ['path' => wp_upload_dir()['basedir'], 'enabled' => true],
    ];
    foreach ($roots as $k => $r) {
        if (is_array($r) && is_callable($r['path'])) {
            $roots[$k]['path'] = call_user_func($r['path']);
        }
    }
    return apply_filters('ai_pr_allowed_roots', $roots);
}

/* ===============
 * Path + IO utils
 * =============== */
function ai_pr_uploads_paths() {
    $uploads = wp_upload_dir();
    $snapRoot = trailingslashit($uploads['basedir']) . AI_PR_SNAP_ROOT;
    $logRoot  = trailingslashit($uploads['basedir']) . AI_PR_LOG_ROOT;
    wp_mkdir_p($snapRoot);
    wp_mkdir_p($logRoot);
    return [$snapRoot, $logRoot, $uploads];
}

function ai_pr_now_id() {
    return gmdate('Ymd-His') . '-' . wp_generate_password(6, false, false);
}

function ai_pr_is_ext_ok($path) {
    return (bool)preg_match(ai_pr_allowed_ext_regex(), $path);
}

function ai_pr_normalize_path($path) {
    $path = wp_normalize_path($path);
    return rtrim($path, '/');
}

function ai_pr_path_is_inside($abs, $root) {
    $abs  = ai_pr_normalize_path($abs);
    $root = ai_pr_normalize_path($root);
    return (strpos($abs, $root . '/') === 0) || ($abs === $root);
}

function ai_pr_resolve_target($relOrAbs, $allowCore = false) {
    $roots = ai_pr_allowed_roots();
    $candidates = [];
    foreach ($roots as $r) {
        if (!$r['enabled']) continue;
        $candidates[] = $r['path'];
    }
    if ($allowCore) {
        foreach ($roots as $r) {
            if ($r['path'] === ABSPATH) $candidates[] = ABSPATH;
        }
    }

    $path = $relOrAbs;
    if (!preg_match('#^([a-zA-Z]:\\\\|/)#', $path)) {
        $path = WP_CONTENT_DIR . '/' . ltrim($relOrAbs, '/');
    }
    $real = realpath($path);
    $path = $real ? $real : $path;
    $ok = false;
    foreach ($candidates as $root) {
        if (ai_pr_path_is_inside($path, $root)) { $ok = true; break; }
    }
    return $ok ? $path : null;
}

function ai_pr_checksum($data) {
    return hash('sha256', $data);
}

function ai_pr_log($message, $context = []) {
    list(, $logRoot) = ai_pr_uploads_paths();
    $file = trailingslashit($logRoot) . 'ai-pr-' . gmdate('Ymd') . '.log';
    $entry = '[' . gmdate('c') . '] ' . $message;
    if (!empty($context)) $entry .= ' ' . wp_json_encode($context);
    $entry .= PHP_EOL;
    file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
}

/* ===========
 * Diff helper
 * =========== */
function ai_pr_text_diff($old, $new, $title = 'Diff') {
    if (function_exists('wp_text_diff')) {
        return wp_text_diff($old, $new, ['title' => $title]);
    }
    $esc = function($s){ return '<pre style="white-space:pre-wrap;border:1px solid #ddd;padding:12px;overflow:auto;">' . esc_html($s) . '</pre>'; };
    return '<h3>'.esc_html($title).'</h3><div class="ai-pr-diff">'.$esc($old).'<hr/>'.$esc($new).'</div>';
}

/* =================
 * Snapshot manager
 * ================= */
function ai_pr_start_snapshot($label = '') {
    list($snapRoot) = ai_pr_uploads_paths();
    $id = ai_pr_now_id();
    $dir = trailingslashit($snapRoot) . $id;
    wp_mkdir_p($dir);
    $manifest = [
        'id'        => $id,
        'created'   => gmdate('c'),
        'label'     => (string)$label,
        'version'   => AI_PR_VERSION,
        'wordpress' => get_bloginfo('version'),
        'files'     => [],
    ];
    file_put_contents($dir . '/manifest.json', wp_json_encode($manifest, JSON_PRETTY_PRINT), LOCK_EX);
    return [$id, $dir];
}

function ai_pr_snapshot_add_file($snapDir, $absPath, $beforeContent) {
    $manifestFile = $snapDir . '/manifest.json';
    $m = json_decode(file_get_contents($manifestFile), true);
    $exists = file_exists($absPath);
    $m['files'][$absPath] = [
        'exists_before'   => $exists,
        'checksum_before' => $exists ? ai_pr_checksum($beforeContent) : null,
        'size_before'     => $exists ? strlen($beforeContent) : 0,
    ];
    file_put_contents($manifestFile, wp_json_encode($m, JSON_PRETTY_PRINT), LOCK_EX);
    if ($exists) {
        $rel = 'files/' . md5($absPath) . '.before';
        $dest = $snapDir . '/' . $rel;
        wp_mkdir_p(dirname($dest));
        file_put_contents($dest, $beforeContent, LOCK_EX);
    }
}

function ai_pr_finalize_snapshot($snapDir) {
    if (file_exists($snapDir . '/manifest.json')) {
        $m = json_decode(file_get_contents($snapDir . '/manifest.json'), true);
        $m['finalized'] = gmdate('c');
        file_put_contents($snapDir . '/manifest.json', wp_json_encode($m, JSON_PRETTY_PRINT), LOCK_EX);
    }
}

function ai_pr_list_snapshots($limit = 50) {
    list($snapRoot) = ai_pr_uploads_paths();
    if (!is_dir($snapRoot)) return [];
    $dirs = array_filter(glob($snapRoot . '/*'), 'is_dir');
    usort($dirs, function($a,$b){ return strcmp(basename($b), basename($a)); });
    $out = [];
    foreach ($dirs as $d) {
        $mf = $d . '/manifest.json';
        if (file_exists($mf)) {
            $m = json_decode(file_get_contents($mf), true);
            $m['_dir'] = $d;
            $out[] = $m;
            if (count($out) >= $limit) break;
        }
    }
    return $out;
}

function ai_pr_load_snapshot($id) {
    list($snapRoot) = ai_pr_uploads_paths();
    $dir = trailingslashit($snapRoot) . $id;
    $mf = $dir . '/manifest.json';
    if (!file_exists($mf)) return null;
    $m = json_decode(file_get_contents($mf), true);
    $m['_dir'] = $dir;
    return $m;
}

/* =================
 * File operations
 * ================= */
function ai_pr_write_file($abs, $content) {
    wp_mkdir_p(dirname($abs));
    return file_put_contents($abs, $content, LOCK_EX);
}

function ai_pr_delete_file($abs) {
    if (file_exists($abs)) return unlink($abs);
    return true;
}

function ai_pr_rename_file($absFrom, $absTo) {
    wp_mkdir_p(dirname($absTo));
    return @rename($absFrom, $absTo);
}

/* =======================
 * Block parsing
 * ======================= */
function ai_pr_parse_blocks($text) {
    $lines = preg_split("/\r\n|\n|\r/", $text);
    $blocks = [];
    $current = null;
    $buf = [];
    foreach ($lines as $line) {
        if (preg_match('/^===\s*(FILE|APPEND|DELETE|RENAME)\s*:\s*(.+?)\s*(?:=>\s*(.+))?\s*===\s*$/i', $line, $m)) {
            if ($current) {
                $blocks[] = $current + ['content' => implode("\n", $buf)];
                $buf = [];
            }
            $current = ['type' => strtoupper($m[1]), 'path' => trim($m[2]), 'path_to' => isset($m[3]) ? trim($m[3]) : null];
        } elseif (preg_match('/^===\s*END\s*(FILE|APPEND|DELETE|RENAME)\s*===\s*$/i', $line)) {
            if ($current) {
                $blocks[] = $current + ['content' => implode("\n", $buf)];
                $buf = [];
                $current = null;
            }
        } else {
            $buf[] = $line;
        }
    }
    if ($current) $blocks[] = $current + ['content' => implode("\n", $buf)];
    return $blocks;
}

/* =======================
 * Preview Management (v3.0)
 * ======================= */
function ai_pr_generate_preview_key() {
    return 'ai_pr_preview_' . get_current_user_id() . '_' . time();
}

function ai_pr_store_preview($key, $ai_response, $metadata = []) {
    set_transient($key, ['ai_response' => $ai_response, 'metadata' => $metadata], HOUR_IN_SECONDS);
}

function ai_pr_get_preview($key) {
    $data = get_transient($key);
    if (!$data) return null;
    if (strpos($key, 'ai_pr_preview_' . get_current_user_id()) !== 0) return null;
    return $data;
}

function ai_pr_delete_preview($key) {
    delete_transient($key);
}

/* =======================
 * Helper Functions (v3.0)
 * ======================= */
function ai_pr_get_pages_and_posts() {
    $pages = get_pages(['numberposts' => 100]);
    $posts = get_posts(['numberposts' => 100, 'post_type' => 'any']);
    $items = [];
    foreach ($pages as $p) {
        $items[] = ['id' => $p->ID, 'title' => $p->post_title, 'type' => 'Page', 'url' => get_permalink($p)];
    }
    foreach ($posts as $p) {
        $items[] = ['id' => $p->ID, 'title' => $p->post_title, 'type' => ucfirst($p->post_type), 'url' => get_permalink($p)];
    }
    return $items;
}

function ai_pr_detect_elementor($post_id = null) {
    $info = ['active' => false, 'post_uses_elementor' => false];
    if (did_action('elementor/loaded')) {
        $info['active'] = true;
        if ($post_id && get_post_meta($post_id, '_elementor_edit_mode', true)) {
            $info['post_uses_elementor'] = true;
        }
    }
    return $info;
}

function ai_pr_get_theme_files() {
    $theme = wp_get_theme();
    $stylesheet_dir = get_stylesheet_directory();
    return [[
        'path' => $stylesheet_dir . '/style.css',
        'relative' => 'themes/' . $theme->get_stylesheet() . '/style.css',
        'description' => 'Main Stylesheet',
    ]];
}

function ai_pr_build_ai_prompt($template_type, $params, $context = []) {
    $elementor_info = ai_pr_detect_elementor($context['post_id'] ?? null);
    $theme_files = ai_pr_get_theme_files();
    $stylesheet_path = $theme_files[0]['relative'];
    
    $system_prompt = "You are an expert WordPress CSS stylist. You help users style their websites by generating CSS changes.

CRITICAL RULES:
- NEVER modify files in wp-content/uploads/elementor/css/ - these are auto-generated and will be overwritten
- ALWAYS append CSS to the child theme stylesheet: {$stylesheet_path}
- Use page-specific selectors like .page-id-{ID} or body.post-{ID} for page-specific changes
- Use !important if needed to override Elementor styles
- Generate clean, well-commented CSS
- Return JSON format ONLY with this structure: {\"summary\": \"Brief description\", \"operations\": [{\"type\": \"APPEND\", \"path\": \"path/to/file\", \"content\": \"CSS code\"}]}

CONTEXT:
- Theme stylesheet: {$stylesheet_path}
- Elementor active: " . ($elementor_info['active'] ? 'Yes' : 'No') . "
- Current request type: {$template_type}";

    $user_prompt = '';
    
    switch ($template_type) {
        case 'quick_colors':
            $element = $params['element'] ?? 'background';
            $color = $params['color'] ?? '#000000';
            $user_prompt = "Change the {$element} color to {$color}. Generate appropriate CSS selectors for {$element} elements.";
            break;
            
        case 'quick_fonts':
            $element = $params['element'] ?? 'body';
            $font = $params['font'] ?? 'Arial';
            $size = $params['size'] ?? '';
            $user_prompt = "Change the font for {$element} to {$font}" . ($size ? " with size {$size}" : "") . ". Generate appropriate CSS selectors.";
            break;
            
        case 'quick_spacing':
            $element = $params['element'] ?? 'sections';
            $amount = $params['amount'] ?? 'increase';
            $user_prompt = "Adjust spacing for {$element} - {$amount} the current spacing. Generate appropriate margin/padding CSS.";
            break;
            
        case 'page_specific':
            $page_id = $params['page_id'] ?? 0;
            $request = $params['request'] ?? '';
            $user_prompt = "For page ID {$page_id}: {$request}. Use .page-id-{$page_id} selector for page-specific styles.";
            break;
            
        case 'custom':
            $user_prompt = $params['request'] ?? '';
            break;
    }
    
    return ['system' => $system_prompt, 'user' => $user_prompt];
}

function ai_pr_call_ai($system, $user) {
    if (!function_exists('openai_wp_chat')) {
        return new WP_Error('no_ai', 'OpenAI integration not found. Please install the OpenAI plugin.');
    }
    
    $response = openai_wp_chat([
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ]);
    
    if (is_wp_error($response)) return $response;
    
    $content = $response['content'] ?? '';
    
    // Try to extract JSON from markdown code blocks if present
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $content, $matches)) {
        $content = $matches[1];
    }
    
    $json = json_decode($content, true);
    if (!$json || !isset($json['summary']) || !isset($json['operations'])) {
        return new WP_Error('invalid_response', 'AI response not in expected format. Response: ' . substr($content, 0, 200));
    }
    return $json;
}

/* =========
 * Admin UI
 * ========= */
add_action('admin_menu', function(){
    add_management_page(
        'AI Website Styler',
        'AI Website Styler',
        'manage_options',
        AI_PR_PLUGIN_SLUG,
        'ai_pr_admin_page'
    );
});

/* Modern CSS Styling */
add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'tools_page_' . AI_PR_PLUGIN_SLUG) return;
    
    wp_register_style('ai-pr-admin', false);
    wp_enqueue_style('ai-pr-admin');
    
    $css = <<<'CSS'
    .ai-styler-wrap { max-width: 1200px; margin: 20px 0; }
    .ai-styler-card { background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px; margin: 20px 0; border-radius: 4px; }
    .ai-styler-form-row { margin: 15px 0; }
    .ai-styler-form-row label { display: block; font-weight: 600; margin-bottom: 5px; }
    .ai-styler-form-row input[type="text"],
    .ai-styler-form-row input[type="color"],
    .ai-styler-form-row select,
    .ai-styler-form-row textarea { width: 100%; max-width: 500px; }
    .ai-styler-form-row textarea { min-height: 120px; }
    .ai-styler-notice-success { background: #d4edda; border-left: 4px solid #28a745; padding: 12px; margin: 15px 0; color: #155724; }
    .ai-styler-notice-error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 12px; margin: 15px 0; color: #721c24; }
    .ai-styler-notice-info { background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 12px; margin: 15px 0; color: #0c5460; }
    .ai-styler-notice-warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0; color: #856404; }
    .ai-styler-diff-container { background: #f5f5f5; border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 4px; max-height: 500px; overflow: auto; }
    .ai-styler-snapshot-list { list-style: none; padding: 0; margin: 0; }
    .ai-styler-snapshot-item { background: #fafafa; border: 1px solid #e0e0e0; padding: 15px; margin: 10px 0; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; }
    .ai-styler-snapshot-meta { flex: 1; }
    .ai-styler-snapshot-meta strong { display: block; margin-bottom: 5px; }
    .ai-styler-snapshot-meta small { color: #666; }
    .ai-styler-examples { background: #f9f9f9; border-left: 3px solid #0073aa; padding: 10px 15px; margin: 10px 0; font-size: 13px; }
    .ai-styler-examples strong { display: block; margin-bottom: 5px; }
    .ai-styler-button-group { margin-top: 20px; }
    .ai-styler-hidden { display: none; }
    .ai-styler-form-group { margin-bottom: 20px; }
CSS;
    wp_add_inline_style('ai-pr-admin', $css);
    
    // Dynamic form interactions
    wp_register_script('ai-pr-admin', false, [], AI_PR_VERSION, true);
    wp_enqueue_script('ai-pr-admin');
    
    $js = <<<'JS'
    (function(){
        function $(sel) { return document.querySelector(sel); }
        function $$(sel) { return document.querySelectorAll(sel); }
        
        // Quick Tasks dynamic fields
        var taskTypeSelect = $('#quick_task_type');
        if (taskTypeSelect) {
            taskTypeSelect.addEventListener('change', function() {
                $$('.quick-task-fields').forEach(function(el) { el.classList.add('ai-styler-hidden'); });
                var selected = this.value;
                if (selected) {
                    var target = $('#quick-fields-' + selected);
                    if (target) target.classList.remove('ai-styler-hidden');
                }
            });
        }
        
        // Confirmation for restore
        $$('.ai-styler-restore-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to restore this snapshot? Current changes will be backed up first.')) {
                    e.preventDefault();
                }
            });
        });
    })();
JS;
    wp_add_inline_script('ai-pr-admin', $js);
});

/* Main admin page */
function ai_pr_admin_page() {
    if (!current_user_can('manage_options')) return;
    $nonce_action = 'ai_pr_action_' . get_current_user_id();
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'quick';

    echo '<div class="wrap ai-styler-wrap">';
    echo '<h1>✨ AI Website Styler</h1>';
    echo '<p class="description">Style your website using simple, plain-English requests. All changes are previewed before applying and can be rolled back.</p>';
    
    echo '<h2 class="nav-tab-wrapper">';
    foreach ([
        'quick'   => '⚡ Quick Tasks',
        'page'    => '📄 Page Styler',
        'custom'  => '✏️ Custom Request',
        'history' => '🕐 History'
    ] as $t => $label) {
        $cls = $tab === $t ? ' nav-tab nav-tab-active' : ' nav-tab';
        echo '<a class="'.$cls.'" href="'.esc_url(admin_url('tools.php?page='.AI_PR_PLUGIN_SLUG.'&tab='.$t)).'">'.esc_html($label).'</a>';
    }
    echo '</h2>';

    if ($tab === 'quick') {
        ai_pr_ui_quick_tasks($nonce_action);
    } elseif ($tab === 'page') {
        ai_pr_ui_page_styler($nonce_action);
    } elseif ($tab === 'custom') {
        ai_pr_ui_custom_request($nonce_action);
    } else {
        ai_pr_ui_history($nonce_action);
    }
    echo '</div>';
}

/* ==================
 * Tab: Quick Tasks
 * ================== */
function ai_pr_ui_quick_tasks($nonce_action) {
    $do_preview = !empty($_POST['do_preview']);
    $do_apply = !empty($_POST['do_apply']);
    $preview_key = isset($_POST['preview_key']) ? sanitize_text_field($_POST['preview_key']) : '';
    
    // Handle Apply from preview
    if ($do_apply && $preview_key && check_admin_referer($nonce_action)) {
        $preview_data = ai_pr_get_preview($preview_key);
        if (!$preview_data) {
            echo '<div class="ai-styler-notice-error"><strong>Preview Expired</strong><br>Your preview has expired. Please regenerate the preview and try again.</div>';
        } else {
            ai_pr_apply_changes($preview_data['ai_response'], 'Quick Task');
            ai_pr_delete_preview($preview_key);
        }
    }
    // Handle Preview generation
    elseif ($do_preview && check_admin_referer($nonce_action)) {
        $task_type = isset($_POST['task_type']) ? sanitize_text_field($_POST['task_type']) : '';
        $params = [];
        
        if ($task_type === 'colors') {
            $params = [
                'element' => isset($_POST['color_element']) ? sanitize_text_field($_POST['color_element']) : 'background',
                'color' => isset($_POST['color_value']) ? sanitize_hex_color($_POST['color_value']) : '#000000',
            ];
            $ai_response = ai_pr_call_ai(
                ...array_values(ai_pr_build_ai_prompt('quick_colors', $params))
            );
        } elseif ($task_type === 'fonts') {
            $params = [
                'element' => isset($_POST['font_element']) ? sanitize_text_field($_POST['font_element']) : 'body',
                'font' => isset($_POST['font_family']) ? sanitize_text_field($_POST['font_family']) : 'Arial',
                'size' => isset($_POST['font_size']) ? sanitize_text_field($_POST['font_size']) : '',
            ];
            $ai_response = ai_pr_call_ai(
                ...array_values(ai_pr_build_ai_prompt('quick_fonts', $params))
            );
        } elseif ($task_type === 'spacing') {
            $params = [
                'element' => isset($_POST['spacing_element']) ? sanitize_text_field($_POST['spacing_element']) : 'sections',
                'amount' => isset($_POST['spacing_amount']) ? sanitize_text_field($_POST['spacing_amount']) : 'increase',
            ];
            $ai_response = ai_pr_call_ai(
                ...array_values(ai_pr_build_ai_prompt('quick_spacing', $params))
            );
        } else {
            echo '<div class="ai-styler-notice-error">Please select a task type.</div>';
            $ai_response = null;
        }
        
        if ($ai_response && !is_wp_error($ai_response)) {
            $preview_key = ai_pr_generate_preview_key();
            ai_pr_store_preview($preview_key, $ai_response, ['task_type' => $task_type, 'params' => $params]);
            ai_pr_show_preview($ai_response, $preview_key, $nonce_action);
        } elseif (is_wp_error($ai_response)) {
            echo '<div class="ai-styler-notice-error"><strong>Error:</strong> ' . esc_html($ai_response->get_error_message()) . '</div>';
        }
    }
    
    // Show form
    echo '<div class="ai-styler-card">';
    echo '<h2>⚡ Quick Tasks</h2>';
    echo '<p>Choose from common styling tasks with simple controls.</p>';
    
    echo '<form method="post">';
    wp_nonce_field($nonce_action);
    
    echo '<div class="ai-styler-form-row">';
    echo '<label for="quick_task_type">What would you like to change?</label>';
    echo '<select id="quick_task_type" name="task_type" required>';
    echo '<option value="">-- Select a task --</option>';
    echo '<option value="colors">Change Colors</option>';
    echo '<option value="fonts">Change Fonts</option>';
    echo '<option value="spacing">Adjust Spacing</option>';
    echo '</select>';
    echo '</div>';
    
    // Color options
    echo '<div id="quick-fields-colors" class="quick-task-fields ai-styler-hidden">';
    echo '<div class="ai-styler-form-row">';
    echo '<label>What element?</label>';
    echo '<select name="color_element">';
    echo '<option value="background">Background</option>';
    echo '<option value="header">Header</option>';
    echo '<option value="footer">Footer</option>';
    echo '<option value="buttons">Buttons</option>';
    echo '<option value="links">Links</option>';
    echo '<option value="headings">Headings</option>';
    echo '</select>';
    echo '</div>';
    echo '<div class="ai-styler-form-row">';
    echo '<label>Pick a color</label>';
    echo '<input type="color" name="color_value" value="#0073aa">';
    echo '</div>';
    echo '</div>';
    
    // Font options
    echo '<div id="quick-fields-fonts" class="quick-task-fields ai-styler-hidden">';
    echo '<div class="ai-styler-form-row">';
    echo '<label>What element?</label>';
    echo '<select name="font_element">';
    echo '<option value="body">Body Text</option>';
    echo '<option value="headings">All Headings</option>';
    echo '<option value="h1">H1 Only</option>';
    echo '<option value="navigation">Navigation Menu</option>';
    echo '</select>';
    echo '</div>';
    echo '<div class="ai-styler-form-row">';
    echo '<label>Font Name</label>';
    echo '<input type="text" name="font_family" placeholder="e.g., Arial, Roboto, Georgia">';
    echo '</div>';
    echo '<div class="ai-styler-form-row">';
    echo '<label>Font Size (optional)</label>';
    echo '<input type="text" name="font_size" placeholder="e.g., 16px, 1.2em">';
    echo '</div>';
    echo '</div>';
    
    // Spacing options
    echo '<div id="quick-fields-spacing" class="quick-task-fields ai-styler-hidden">';
    echo '<div class="ai-styler-form-row">';
    echo '<label>What element?</label>';
    echo '<select name="spacing_element">';
    echo '<option value="sections">Sections</option>';
    echo '<option value="paragraphs">Paragraphs</option>';
    echo '<option value="header">Header</option>';
    echo '<option value="footer">Footer</option>';
    echo '</select>';
    echo '</div>';
    echo '<div class="ai-styler-form-row">';
    echo '<label>How much?</label>';
    echo '<select name="spacing_amount">';
    echo '<option value="reduce">Reduce spacing</option>';
    echo '<option value="increase">Increase spacing</option>';
    echo '<option value="double">Double spacing</option>';
    echo '<option value="half">Half spacing</option>';
    echo '</select>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="ai-styler-button-group">';
    echo '<button type="submit" name="do_preview" value="1" class="button button-primary">Preview Changes</button>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
}

/* ==================
 * Tab: Page Styler
 * ================== */
function ai_pr_ui_page_styler($nonce_action) {
    $do_preview = !empty($_POST['do_preview']);
    $do_apply = !empty($_POST['do_apply']);
    $preview_key = isset($_POST['preview_key']) ? sanitize_text_field($_POST['preview_key']) : '';
    
    if ($do_apply && $preview_key && check_admin_referer($nonce_action)) {
        $preview_data = ai_pr_get_preview($preview_key);
        if (!$preview_data) {
            echo '<div class="ai-styler-notice-error"><strong>Preview Expired</strong><br>Your preview has expired. Please regenerate the preview and try again.</div>';
        } else {
            ai_pr_apply_changes($preview_data['ai_response'], 'Page Styling');
            ai_pr_delete_preview($preview_key);
        }
    }
    elseif ($do_preview && check_admin_referer($nonce_action)) {
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        $request = isset($_POST['styling_request']) ? wp_unslash($_POST['styling_request']) : '';
        
        if (!$page_id || !$request) {
            echo '<div class="ai-styler-notice-error">Please select a page and describe your styling request.</div>';
        } else {
            $params = ['page_id' => $page_id, 'request' => $request];
            $ai_response = ai_pr_call_ai(
                ...array_values(ai_pr_build_ai_prompt('page_specific', $params, ['post_id' => $page_id]))
            );
            
            if ($ai_response && !is_wp_error($ai_response)) {
                $preview_key = ai_pr_generate_preview_key();
                ai_pr_store_preview($preview_key, $ai_response, ['page_id' => $page_id, 'request' => $request]);
                ai_pr_show_preview($ai_response, $preview_key, $nonce_action);
            } elseif (is_wp_error($ai_response)) {
                echo '<div class="ai-styler-notice-error"><strong>Error:</strong> ' . esc_html($ai_response->get_error_message()) . '</div>';
            }
        }
    }
    
    $pages_and_posts = ai_pr_get_pages_and_posts();
    
    echo '<div class="ai-styler-card">';
    echo '<h2>📄 Page Styler</h2>';
    echo '<p>Style a specific page or post using plain English.</p>';
    
    echo '<form method="post">';
    wp_nonce_field($nonce_action);
    
    echo '<div class="ai-styler-form-row">';
    echo '<label for="page_id">Select Page/Post</label>';
    echo '<select id="page_id" name="page_id" required>';
    echo '<option value="">-- Select a page or post --</option>';
    foreach ($pages_and_posts as $item) {
        echo '<option value="' . esc_attr($item['id']) . '">' . esc_html($item['title']) . ' (' . esc_html($item['type']) . ')</option>';
    }
    echo '</select>';
    echo '</div>';
    
    echo '<div class="ai-styler-form-row">';
    echo '<label for="styling_request">What would you like to change?</label>';
    echo '<textarea id="styling_request" name="styling_request" required placeholder="Describe your styling request in plain English..."></textarea>';
    echo '</div>';
    
    echo '<div class="ai-styler-examples">';
    echo '<strong>Examples:</strong>';
    echo '<ul style="margin: 5px 0 0 20px;">';
    echo '<li>"Make the header purple with white text"</li>';
    echo '<li>"Add more spacing between sections"</li>';
    echo '<li>"Change all buttons to green"</li>';
    echo '<li>"Make the title font larger and bold"</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '<div class="ai-styler-button-group">';
    echo '<button type="submit" name="do_preview" value="1" class="button button-primary">Preview Changes</button>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
}

/* =====================
 * Tab: Custom Request
 * ===================== */
function ai_pr_ui_custom_request($nonce_action) {
    $do_preview = !empty($_POST['do_preview']);
    $do_apply = !empty($_POST['do_apply']);
    $preview_key = isset($_POST['preview_key']) ? sanitize_text_field($_POST['preview_key']) : '';
    
    if ($do_apply && $preview_key && check_admin_referer($nonce_action)) {
        $preview_data = ai_pr_get_preview($preview_key);
        if (!$preview_data) {
            echo '<div class="ai-styler-notice-error"><strong>Preview Expired</strong><br>Your preview has expired. Please regenerate the preview and try again.</div>';
        } else {
            ai_pr_apply_changes($preview_data['ai_response'], 'Custom Request');
            ai_pr_delete_preview($preview_key);
        }
    }
    elseif ($do_preview && check_admin_referer($nonce_action)) {
        $request = isset($_POST['custom_request']) ? wp_unslash($_POST['custom_request']) : '';
        
        if (!$request) {
            echo '<div class="ai-styler-notice-error">Please describe what you would like to change.</div>';
        } else {
            $params = ['request' => $request];
            $ai_response = ai_pr_call_ai(
                ...array_values(ai_pr_build_ai_prompt('custom', $params))
            );
            
            if ($ai_response && !is_wp_error($ai_response)) {
                $preview_key = ai_pr_generate_preview_key();
                ai_pr_store_preview($preview_key, $ai_response, ['request' => $request]);
                ai_pr_show_preview($ai_response, $preview_key, $nonce_action);
            } elseif (is_wp_error($ai_response)) {
                echo '<div class="ai-styler-notice-error"><strong>Error:</strong> ' . esc_html($ai_response->get_error_message()) . '</div>';
            }
        }
    }
    
    echo '<div class="ai-styler-card">';
    echo '<h2>✏️ Custom Request</h2>';
    echo '<p>Describe any styling change you want in plain English. Our AI will figure out the best way to make it happen.</p>';
    
    echo '<form method="post">';
    wp_nonce_field($nonce_action);
    
    echo '<div class="ai-styler-form-row">';
    echo '<label for="custom_request">Describe your styling request</label>';
    echo '<textarea id="custom_request" name="custom_request" required style="min-height: 200px;" placeholder="Tell us what you want to change..."></textarea>';
    echo '</div>';
    
    echo '<div class="ai-styler-examples">';
    echo '<strong>Examples:</strong>';
    echo '<ul style="margin: 5px 0 0 20px;">';
    echo '<li>"Make the homepage header purple with a gradient"</li>';
    echo '<li>"Add more spacing between all sections on the site"</li>';
    echo '<li>"Change all blue buttons to green"</li>';
    echo '<li>"Make the footer darker and increase the font size"</li>';
    echo '<li>"Add rounded corners to all images"</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '<div class="ai-styler-button-group">';
    echo '<button type="submit" name="do_preview" value="1" class="button button-primary button-large">Preview Changes</button>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
}

/* ==============
 * Tab: History
 * ============== */
function ai_pr_ui_history($nonce_action) {
    $do_restore = !empty($_POST['do_restore']);
    $snapshot_id = isset($_POST['snapshot_id']) ? sanitize_text_field($_POST['snapshot_id']) : '';
    
    if ($do_restore && $snapshot_id && check_admin_referer($nonce_action)) {
        $snap = ai_pr_load_snapshot($snapshot_id);
        if (!$snap) {
            echo '<div class="ai-styler-notice-error">Snapshot not found.</div>';
        } else {
            list($newId, $newDir) = ai_pr_start_snapshot('restore-of-' . $snapshot_id);
            $restored = 0;
            
            foreach ($snap['files'] as $abs => $meta) {
                $absAllowed = ai_pr_resolve_target($abs, false);
                if (!$absAllowed) continue;
                
                $before = file_exists($abs) ? file_get_contents($abs) : '';
                ai_pr_snapshot_add_file($newDir, $abs, $before);
                
                $blobFile = $snap['_dir'] . '/files/' . md5($abs) . '.before';
                $after = $meta['exists_before'] ? (file_exists($blobFile) ? file_get_contents($blobFile) : '') : '';
                
                if ($meta['exists_before']) {
                    ai_pr_write_file($abs, $after);
                } else {
                    ai_pr_delete_file($abs);
                }
                $restored++;
            }
            
            ai_pr_finalize_snapshot($newDir);
            ai_pr_log('restore', ['restored' => $snapshot_id, 'snapshot' => $newId]);
            
            echo '<div class="ai-styler-notice-success">';
            echo '<strong>✓ Restored Successfully</strong><br>';
            echo 'Snapshot <code>' . esc_html($snapshot_id) . '</code> has been restored. ';
            echo 'Files restored: ' . intval($restored) . '. ';
            echo 'New backup created: <code>' . esc_html($newId) . '</code>';
            echo '</div>';
        }
    }
    
    $snapshots = ai_pr_list_snapshots(50);
    
    echo '<div class="ai-styler-card">';
    echo '<h2>🕐 History</h2>';
    echo '<p>View and restore previous styling changes. Each change is automatically backed up before applying.</p>';
    
    if (empty($snapshots)) {
        echo '<div class="ai-styler-notice-info">No styling history yet. Make your first styling change to get started!</div>';
    } else {
        echo '<ul class="ai-styler-snapshot-list">';
        foreach ($snapshots as $snap) {
            echo '<li class="ai-styler-snapshot-item">';
            echo '<div class="ai-styler-snapshot-meta">';
            echo '<strong>' . esc_html($snap['label'] ?: $snap['id']) . '</strong>';
            echo '<small>';
            echo 'Date: ' . esc_html(date('F j, Y g:i a', strtotime($snap['created']))) . ' | ';
            echo 'Files: ' . count($snap['files']) . ' | ';
            echo 'ID: ' . esc_html($snap['id']);
            echo '</small>';
            echo '</div>';
            echo '<div>';
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field($nonce_action);
            echo '<input type="hidden" name="snapshot_id" value="' . esc_attr($snap['id']) . '">';
            echo '<button type="submit" name="do_restore" value="1" class="button ai-styler-restore-btn">Restore</button>';
            echo '</form>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    echo '</div>';
}

/* ===================
 * Helper: Show Preview
 * =================== */
function ai_pr_show_preview($ai_response, $preview_key, $nonce_action) {
    echo '<div class="ai-styler-card" style="border-left: 4px solid #0073aa;">';
    echo '<h2>👁️ Preview</h2>';
    
    echo '<div class="ai-styler-notice-info">';
    echo '<strong>Summary:</strong> ' . esc_html($ai_response['summary']);
    echo '</div>';
    
    echo '<h3>Changes to be applied:</h3>';
    
    foreach ($ai_response['operations'] as $op) {
        $type = $op['type'] ?? 'UNKNOWN';
        $path = $op['path'] ?? 'unknown';
        $content = $op['content'] ?? '';
        
        $abs = ai_pr_resolve_target($path, false);
        if (!$abs) {
            echo '<div class="ai-styler-notice-warning">Skip (outside allowed paths): <code>' . esc_html($path) . '</code></div>';
            continue;
        }
        
        $before = file_exists($abs) ? file_get_contents($abs) : '';
        
        if ($type === 'FILE') {
            $after = $content;
        } elseif ($type === 'APPEND') {
            $after = rtrim($before) . "\n\n" . rtrim($content) . "\n";
        } else {
            $after = $content;
        }
        
        echo '<div class="ai-styler-diff-container">';
        echo ai_pr_text_diff($before, $after, 'Changes to: ' . esc_html(wp_make_link_relative($abs)));
        echo '</div>';
    }
    
    echo '<form method="post" style="margin-top: 20px;">';
    wp_nonce_field($nonce_action);
    echo '<input type="hidden" name="preview_key" value="' . esc_attr($preview_key) . '">';
    echo '<button type="submit" name="do_apply" value="1" class="button button-primary button-large">✓ Apply These Changes</button> ';
    echo '<a href="' . esc_url(admin_url('tools.php?page=' . AI_PR_PLUGIN_SLUG)) . '" class="button">Cancel</a>';
    echo '<p class="description">A backup will be created automatically before applying changes.</p>';
    echo '</form>';
    
    echo '</div>';
}

/* =======================
 * Helper: Apply Changes
 * ======================= */
function ai_pr_apply_changes($ai_response, $label = 'AI Styling') {
    list($snapId, $snapDir) = ai_pr_start_snapshot($label);
    $applied = 0;
    
    foreach ($ai_response['operations'] as $op) {
        $type = $op['type'] ?? 'UNKNOWN';
        $path = $op['path'] ?? 'unknown';
        $content = $op['content'] ?? '';
        
        $abs = ai_pr_resolve_target($path, false);
        if (!$abs) continue;
        
        $before = file_exists($abs) ? file_get_contents($abs) : '';
        ai_pr_snapshot_add_file($snapDir, $abs, $before);
        
        if ($type === 'FILE') {
            ai_pr_write_file($abs, $content);
            $applied++;
        } elseif ($type === 'APPEND') {
            $after = rtrim($before) . "\n\n" . rtrim($content) . "\n";
            ai_pr_write_file($abs, $after);
            $applied++;
        }
    }
    
    ai_pr_finalize_snapshot($snapDir);
    ai_pr_log('apply_styling', ['snapshot' => $snapId, 'label' => $label, 'files' => $applied]);
    
    echo '<div class="ai-styler-notice-success">';
    echo '<strong>✓ Changes Applied Successfully!</strong><br>';
    echo 'Snapshot created: <code>' . esc_html($snapId) . '</code><br>';
    echo 'Files modified: ' . intval($applied) . '<br>';
    echo '<a href="' . esc_url(home_url()) . '" target="_blank" class="button button-small" style="margin-top: 10px;">View Your Website</a>';
    echo '</div>';
}

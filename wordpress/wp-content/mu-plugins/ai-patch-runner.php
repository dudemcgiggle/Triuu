<?php
/**
 * Plugin Name: AI Website Styler (MU) — v3.0.0
 * Description: User-friendly AI-powered website styling tool. Make design changes in plain English with automatic snapshots and easy rollback.
 * Author: Ken + Helper
 * Version: 3.0.0
 *
 * WHAT THIS DOES:
 * This plugin helps you style your WordPress website using simple, plain English commands.
 * Just describe what you want (like "make the header purple" or "add more spacing"), 
 * and it will show you a preview before making any changes. Every change is saved automatically 
 * so you can undo it with one click if you don't like it.
 *
 * SECURITY:
 * - Only administrators can use this tool
 * - All changes are previewed before being applied
 * - Every change creates an automatic backup (snapshot)
 * - Changes are limited to your theme and plugin files (not WordPress core)
 * - All operations are logged for safety
 */

if (!defined('ABSPATH')) exit;

define('AI_PR_VERSION', '3.0.0');
define('AI_PR_PLUGIN_SLUG', 'ai-patch-runner');
define('AI_PR_SNAP_ROOT', 'ai-patch-runner/snapshots');
define('AI_PR_LOG_ROOT',  'ai-patch-runner/logs');
define('AI_PR_DEFAULT_EXT_REGEX', '/\.(php|js|css|scss|sass|ts|tsx|json|yml|yaml|xml|html?|txt|md|ini|conf|htaccess)$/i');

/* ========================
 * Core Helper Functions
 * (Path validation, security, snapshots)
 * ======================== */

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
        'wordpress-root (core-sensitive)' => ['path' => ABSPATH, 'enabled' => false],
    ];
    return apply_filters('ai_pr_allowed_roots', $roots);
}

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

function ai_pr_text_diff($old, $new, $title = 'Diff') {
    if (function_exists('wp_text_diff')) {
        return wp_text_diff($old, $new, ['title' => $title]);
    }
    $esc = function($s){ return '<pre style="white-space:pre-wrap;border:1px solid #ddd;padding:12px;overflow:auto;">' . esc_html($s) . '</pre>'; };
    return '<h3>'.esc_html($title).'</h3><div class="ai-pr-diff">'.$esc($old).'<hr/>'.$esc($new).'</div>';
}

/* ================
 * Snapshot System
 * ================ */

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

/* ================
 * File Operations
 * ================ */

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

/* =============
 * Block Parsing
 * ============= */

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

function ai_pr_apply_token_region($original, $tokenName, $replacement) {
    $start = "<!-- AI:start:$tokenName -->";
    $end   = "<!-- AI:end:$tokenName -->";
    $p1 = strpos($original, $start);
    $p2 = strpos($original, $end);
    if ($p1 !== false && $p2 !== false && $p2 > $p1) {
        $before = substr($original, 0, $p1 + strlen($start));
        $after  = substr($original, $p2);
        return $before . "\n" . rtrim($replacement) . "\n" . $after;
    }
    $block = "\n$start\n" . rtrim($replacement) . "\n$end\n";
    return rtrim($original) . $block;
}

/* =============================
 * WordPress Integration Helpers
 * ============================= */

function ai_pr_get_pages_and_posts() {
    $items = [];
    
    $pages = get_pages(['number' => 100]);
    foreach ($pages as $page) {
        $items[] = [
            'id' => $page->ID,
            'title' => $page->post_title,
            'url' => get_permalink($page->ID),
            'type' => 'page',
        ];
    }
    
    $posts = get_posts(['numberposts' => 50, 'post_status' => 'publish']);
    foreach ($posts as $post) {
        $items[] = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'url' => get_permalink($post->ID),
            'type' => 'post',
        ];
    }
    
    return $items;
}

function ai_pr_detect_elementor($post_id = null) {
    $info = [
        'active' => false,
        'post_uses_elementor' => false,
        'custom_css_files' => [],
    ];
    
    if (defined('ELEMENTOR_VERSION')) {
        $info['active'] = true;
        
        if ($post_id && get_post_meta($post_id, '_elementor_edit_mode', true)) {
            $info['post_uses_elementor'] = true;
        }
        
        // Note: We intentionally DO NOT include Elementor's auto-generated CSS files
        // from wp-content/uploads/elementor/css/ because they are regenerated
        // by Elementor and any manual changes would be lost. Instead, we direct
        // the AI to use the theme's style.css file for all styling changes.
    }
    
    return $info;
}

function ai_pr_get_theme_files() {
    $theme = wp_get_theme();
    $stylesheet_dir = get_stylesheet_directory();
    $template_dir = get_template_directory();
    
    $files = [];
    
    $key_files = [
        'style.css' => 'Main Stylesheet',
        'functions.php' => 'Theme Functions',
        'header.php' => 'Header Template',
        'footer.php' => 'Footer Template',
    ];
    
    foreach ($key_files as $filename => $description) {
        $path = $stylesheet_dir . '/' . $filename;
        if (file_exists($path)) {
            $files[] = [
                'path' => $path,
                'relative' => 'themes/' . $theme->get_stylesheet() . '/' . $filename,
                'description' => $description,
            ];
        }
    }
    
    return $files;
}

/* ===============================
 * AI Integration & Task Templates
 * =============================== */

function ai_pr_get_quick_task_templates() {
    return [
        'colors' => [
            'label' => 'Change Colors',
            'fields' => [
                ['name' => 'element', 'label' => 'What to change', 'type' => 'select', 'options' => [
                    'background' => 'Page Background',
                    'header' => 'Header/Navigation',
                    'footer' => 'Footer',
                    'buttons' => 'Buttons',
                    'links' => 'Links',
                    'headings' => 'Headings',
                ]],
                ['name' => 'color', 'label' => 'New Color', 'type' => 'color', 'default' => '#3498db'],
            ],
        ],
        'fonts' => [
            'label' => 'Change Fonts',
            'fields' => [
                ['name' => 'element', 'label' => 'What to change', 'type' => 'select', 'options' => [
                    'body' => 'Body Text',
                    'headings' => 'All Headings',
                    'h1' => 'H1 Only',
                    'navigation' => 'Navigation Menu',
                ]],
                ['name' => 'font', 'label' => 'Font Name', 'type' => 'text', 'default' => 'Arial, sans-serif'],
                ['name' => 'size', 'label' => 'Size (optional)', 'type' => 'text', 'placeholder' => 'e.g., 16px'],
            ],
        ],
        'spacing' => [
            'label' => 'Adjust Spacing',
            'fields' => [
                ['name' => 'element', 'label' => 'What to adjust', 'type' => 'select', 'options' => [
                    'sections' => 'Between Sections',
                    'paragraphs' => 'Between Paragraphs',
                    'header' => 'Header Padding',
                    'footer' => 'Footer Padding',
                ]],
                ['name' => 'amount', 'label' => 'Amount', 'type' => 'select', 'options' => [
                    'reduce' => 'Reduce Spacing',
                    'increase' => 'Increase Spacing',
                    'double' => 'Double Current Spacing',
                    'half' => 'Half Current Spacing',
                ]],
            ],
        ],
    ];
}

function ai_pr_build_ai_prompt($template_type, $params, $context = []) {
    $system_prompt = "You are a WordPress styling assistant. You help users modify their website appearance safely and conservatively. 

Your response MUST be valid JSON with this exact structure:
{
    \"summary\": \"A plain English explanation of what you will change (2-3 sentences)\",
    \"operations\": \"Block syntax with file operations\"
}

The operations should use this block syntax:
=== FILE: path/to/file.css ===
complete file content here
=== END FILE ===

=== APPEND: path/to/file.css ===
content to append
=== END APPEND ===

CRITICAL RULES:
- NEVER modify files in wp-content/uploads/elementor/css/ - these are auto-generated and will be overwritten
- NEVER modify files in wp-content/plugins/ - these get overwritten during updates
- ALWAYS use the active child theme's style.css file for CSS changes
- Append CSS rules to wp-content/themes/triuu/style.css (the active child theme)
- Use specific CSS selectors with !important if needed to override Elementor styles
- Make conservative, minimal changes
- Always add CSS comments explaining what was changed and why
- Never modify WordPress core files
- For page-specific styling, use body classes like .page-id-1460 or .post-1460";


    $user_prompt = '';
    
    if ($template_type === 'colors') {
        $element = $params['element'] ?? 'background';
        $color = $params['color'] ?? '#3498db';
        $user_prompt = "Change the {$element} color to {$color}. ";
    } elseif ($template_type === 'fonts') {
        $element = $params['element'] ?? 'body';
        $font = $params['font'] ?? 'Arial';
        $size = !empty($params['size']) ? " and size to {$params['size']}" : '';
        $user_prompt = "Change the {$element} font to {$font}{$size}. ";
    } elseif ($template_type === 'spacing') {
        $element = $params['element'] ?? 'sections';
        $amount = $params['amount'] ?? 'increase';
        $user_prompt = "{$amount} spacing for {$element}. ";
    } elseif ($template_type === 'custom') {
        $user_prompt = $params['request'] ?? '';
    }
    
    if (!empty($context['theme_files'])) {
        $user_prompt .= "\n\nAvailable theme files:\n";
        foreach ($context['theme_files'] as $file) {
            $user_prompt .= "- {$file['relative']} ({$file['description']})\n";
        }
    }
    
    if (!empty($context['elementor'])) {
        $user_prompt .= "\n\nElementor is active on this site.";
        if ($context['elementor']['post_uses_elementor']) {
            $user_prompt .= " The selected page uses Elementor.";
        }
    }
    
    if (!empty($context['page_info'])) {
        $user_prompt .= "\n\nTarget: {$context['page_info']['title']} ({$context['page_info']['type']})";
    }
    
    return [
        'system' => $system_prompt,
        'user' => $user_prompt,
    ];
}

function ai_pr_call_ai($system_prompt, $user_prompt) {
    if (!function_exists('openai_wp_chat')) {
        return new WP_Error('no_ai', 'AI integration not available. Install OpenAI integration to use AI features.');
    }
    
    $messages = [
        ['role' => 'system', 'content' => $system_prompt],
        ['role' => 'user', 'content' => $user_prompt],
    ];
    
    $result = openai_wp_chat($messages, [
        'model' => 'gpt-4o-mini',
        'max_tokens' => 3000,
        'temperature' => 0.3,
    ]);
    
    if (is_wp_error($result)) {
        return $result;
    }
    
    $content = $result['content'] ?? '';
    
    $content = preg_replace('/```json\s*/', '', $content);
    $content = preg_replace('/```\s*$/', '', $content);
    $content = trim($content);
    
    $parsed = json_decode($content, true);
    if (!$parsed || !isset($parsed['summary']) || !isset($parsed['operations'])) {
        return new WP_Error('invalid_response', 'AI returned invalid format. Raw response: ' . substr($content, 0, 200));
    }
    
    return $parsed;
}

/* ============================
 * Preview Management Helpers
 * ============================ */

function ai_pr_generate_preview_key() {
    return 'ai_pr_preview_' . get_current_user_id() . '_' . time();
}

function ai_pr_store_preview($preview_key, $ai_response, $context = []) {
    $preview_data = [
        'ai_response' => $ai_response,
        'context' => $context,
        'created' => gmdate('c'),
        'user_id' => get_current_user_id(),
    ];
    set_transient($preview_key, $preview_data, HOUR_IN_SECONDS);
}

function ai_pr_get_preview($preview_key) {
    $preview_data = get_transient($preview_key);
    if (!$preview_data || !is_array($preview_data)) {
        return null;
    }
    if (!isset($preview_data['ai_response']) || !isset($preview_data['created'])) {
        return null;
    }
    if (isset($preview_data['user_id']) && $preview_data['user_id'] != get_current_user_id()) {
        return null;
    }
    return $preview_data;
}

function ai_pr_delete_preview($preview_key) {
    delete_transient($preview_key);
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

add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'tools_page_' . AI_PR_PLUGIN_SLUG) return;
    
    wp_enqueue_style('ai-pr-admin', false, [], AI_PR_VERSION);
    wp_add_inline_style('ai-pr-admin', '
        .ai-pr-wrap { max-width: 1200px; margin: 20px 0; }
        .ai-pr-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
        .ai-pr-card h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .ai-pr-form-group { margin-bottom: 20px; }
        .ai-pr-form-group label { display: block; font-weight: 600; margin-bottom: 8px; }
        .ai-pr-form-group input[type="text"],
        .ai-pr-form-group input[type="color"],
        .ai-pr-form-group select,
        .ai-pr-form-group textarea { width: 100%; max-width: 500px; }
        .ai-pr-form-group textarea { max-width: 100%; min-height: 150px; }
        .ai-pr-preview { background: #f8f9fa; border-left: 4px solid #00a0d2; padding: 15px; margin: 20px 0; }
        .ai-pr-preview h4 { margin-top: 0; color: #00a0d2; }
        .ai-pr-summary { font-size: 14px; line-height: 1.6; color: #555; }
        .ai-pr-buttons { display: flex; gap: 10px; margin-top: 20px; }
        .ai-pr-help { background: #fffbcc; border-left: 4px solid #ffb900; padding: 12px; margin: 15px 0; font-size: 13px; }
        .ai-pr-snapshot-list { margin: 0; padding: 0; list-style: none; }
        .ai-pr-snapshot-item { background: #fafafa; border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 3px; }
        .ai-pr-snapshot-header { display: flex; justify-content: space-between; align-items: center; }
        .ai-pr-snapshot-title { font-weight: 600; color: #333; }
        .ai-pr-snapshot-date { color: #666; font-size: 12px; }
        .ai-pr-snapshot-meta { color: #888; font-size: 13px; margin-top: 8px; }
        .ai-pr-diff-toggle { cursor: pointer; color: #0073aa; text-decoration: underline; font-size: 12px; }
        .ai-pr-diff-content { display: none; margin-top: 15px; max-height: 400px; overflow: auto; }
        .ai-pr-error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 12px; margin: 15px 0; color: #721c24; }
        .ai-pr-success { background: #d4edda; border-left: 4px solid #28a745; padding: 12px; margin: 15px 0; color: #155724; }
    ');
    
    wp_enqueue_script('ai-pr-admin', false, [], AI_PR_VERSION, true);
    wp_add_inline_script('ai-pr-admin', '
        (function($) {
            $(document).ready(function() {
                $(".ai-pr-diff-toggle").on("click", function(e) {
                    e.preventDefault();
                    $(this).next(".ai-pr-diff-content").slideToggle();
                });
                
                $("#ai_pr_task_template").on("change", function() {
                    var val = $(this).val();
                    $(".ai-pr-template-fields").hide();
                    if (val) {
                        $("#ai-pr-fields-" + val).show();
                    }
                });
            });
        })(jQuery);
    ');
});

function ai_pr_admin_page() {
    if (!current_user_can('manage_options')) {
        echo '<div class="wrap"><h1>Access Denied</h1><p>You do not have permission to access this page.</p></div>';
        return;
    }
    
    $nonce_action = 'ai_pr_action_' . get_current_user_id();
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'quick';
    
    echo '<div class="wrap ai-pr-wrap">';
    echo '<h1>🎨 AI Website Styler</h1>';
    echo '<p class="description">Make your website look amazing using simple, plain English commands. Every change is previewed before being applied, and you can undo anything with one click.</p>';
    
    echo '<h2 class="nav-tab-wrapper">';
    $tabs = [
        'quick' => '⚡ Quick Tasks',
        'page-styler' => '📄 Page Styler',
        'custom' => '✍️ Custom Request',
        'history' => '🕐 History',
    ];
    foreach ($tabs as $t => $label) {
        $cls = $tab === $t ? 'nav-tab nav-tab-active' : 'nav-tab';
        echo '<a class="' . $cls . '" href="' . esc_url(admin_url('tools.php?page=' . AI_PR_PLUGIN_SLUG . '&tab=' . $t)) . '">' . esc_html($label) . '</a>';
    }
    echo '</h2>';
    
    if ($tab === 'quick') {
        ai_pr_ui_quick_tasks($nonce_action);
    } elseif ($tab === 'page-styler') {
        ai_pr_ui_page_styler($nonce_action);
    } elseif ($tab === 'custom') {
        ai_pr_ui_custom_request($nonce_action);
    } elseif ($tab === 'history') {
        ai_pr_ui_history($nonce_action);
    }
    
    echo '</div>';
}

/* =================
 * UI: Quick Tasks
 * ================= */

function ai_pr_ui_quick_tasks($nonce_action) {
    $do_preview = !empty($_POST['do_preview']);
    $do_apply = !empty($_POST['do_apply']);
    
    if ($do_preview && check_admin_referer($nonce_action)) {
        $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : '';
        $params = isset($_POST['params']) && is_array($_POST['params']) ? array_map('sanitize_text_field', $_POST['params']) : [];
        
        $theme_files = ai_pr_get_theme_files();
        $elementor = ai_pr_detect_elementor();
        
        $prompts = ai_pr_build_ai_prompt($template, $params, [
            'theme_files' => $theme_files,
            'elementor' => $elementor,
        ]);
        
        $ai_result = ai_pr_call_ai($prompts['system'], $prompts['user']);
        
        if (is_wp_error($ai_result)) {
            echo '<div class="ai-pr-error"><strong>Error:</strong> ' . esc_html($ai_result->get_error_message()) . '</div>';
        } else {
            $preview_key = ai_pr_generate_preview_key();
            ai_pr_store_preview($preview_key, $ai_result, [
                'template' => $template,
                'params' => $params,
            ]);
            
            $summary = $ai_result['summary'];
            $operations = $ai_result['operations'];
            
            $preview_time = date('g:i a');
            echo '<div class="ai-pr-preview">';
            echo '<h4>📋 Preview Generated (' . esc_html($preview_time) . ')</h4>';
            echo '<div class="ai-pr-summary">' . nl2br(esc_html($summary)) . '</div>';
            echo '<p style="font-size: 12px; color: #666; margin-top: 10px;">⏱ This preview will expire in 1 hour. Apply the changes before then.</p>';
            echo '</div>';
            
            $blocks = ai_pr_parse_blocks($operations);
            if (!$blocks) {
                echo '<div class="ai-pr-error">AI did not generate valid file operations.</div>';
            } else {
                $diff_html = '';
                foreach ($blocks as $b) {
                    $abs = ai_pr_resolve_target($b['path'], false);
                    if (!$abs) continue;
                    
                    $before = file_exists($abs) ? file_get_contents($abs) : '';
                    
                    if ($b['type'] === 'FILE') {
                        $after = $b['content'];
                        $diff_html .= ai_pr_text_diff($before, $after, $b['path']);
                    } elseif ($b['type'] === 'APPEND') {
                        $after = rtrim($before) . "\n\n" . rtrim($b['content']) . "\n";
                        $diff_html .= ai_pr_text_diff($before, $after, $b['path']);
                    }
                }
                
                echo '<div class="ai-pr-card">';
                echo '<a href="#" class="ai-pr-diff-toggle">👁️ Show code changes (for advanced users)</a>';
                echo '<div class="ai-pr-diff-content">' . $diff_html . '</div>';
                echo '</div>';
                
                echo '<form method="post" style="margin-top: 20px;">';
                wp_nonce_field($nonce_action);
                echo '<input type="hidden" name="preview_key" value="' . esc_attr($preview_key) . '">';
                echo '<p><strong>Ready to apply these changes?</strong> You can undo this anytime from the History tab.</p>';
                echo '<button type="submit" name="do_apply" value="1" class="button button-primary button-large">✅ Apply Changes Now</button> ';
                echo '<a href="' . esc_url(admin_url('tools.php?page=' . AI_PR_PLUGIN_SLUG . '&tab=quick')) . '" class="button">Cancel</a>';
                echo '</form>';
            }
        }
        return;
    }
    
    if ($do_apply && check_admin_referer($nonce_action)) {
        $preview_key = isset($_POST['preview_key']) ? sanitize_text_field($_POST['preview_key']) : '';
        
        if (!$preview_key) {
            echo '<div class="ai-pr-error"><strong>Error:</strong> No preview found. Please generate a preview first.</div>';
            return;
        }
        
        $preview_data = ai_pr_get_preview($preview_key);
        
        if (!$preview_data) {
            echo '<div class="ai-pr-error"><strong>Preview Expired:</strong> Your preview has expired or is no longer valid. Please generate a new preview before applying changes.</div>';
            echo '<p><a href="' . esc_url(admin_url('tools.php?page=' . AI_PR_PLUGIN_SLUG . '&tab=quick')) . '" class="button">← Go Back</a></p>';
            return;
        }
        
        $ai_result = $preview_data['ai_response'];
        $template = $preview_data['context']['template'] ?? 'unknown';
        
        $summary = $ai_result['summary'];
        $operations = $ai_result['operations'];
        
        $blocks = ai_pr_parse_blocks($operations);
        if (!$blocks) {
            echo '<div class="ai-pr-error">AI did not generate valid file operations.</div>';
        } else {
            list($snapId, $snapDir) = ai_pr_start_snapshot('quick-task-' . $template);
            
            $diff_html = '';
            foreach ($blocks as $b) {
                $abs = ai_pr_resolve_target($b['path'], false);
                if (!$abs) continue;
                
                $before = file_exists($abs) ? file_get_contents($abs) : '';
                ai_pr_snapshot_add_file($snapDir, $abs, $before);
                
                if ($b['type'] === 'FILE') {
                    $after = $b['content'];
                    $diff_html .= ai_pr_text_diff($before, $after, $b['path']);
                    ai_pr_write_file($abs, $after);
                } elseif ($b['type'] === 'APPEND') {
                    $after = rtrim($before) . "\n\n" . rtrim($b['content']) . "\n";
                    $diff_html .= ai_pr_text_diff($before, $after, $b['path']);
                    ai_pr_write_file($abs, $after);
                }
            }
            
            ai_pr_finalize_snapshot($snapDir);
            ai_pr_delete_preview($preview_key);
            
            echo '<div class="ai-pr-success"><strong>✅ Changes Applied!</strong> Snapshot ID: <code>' . esc_html($snapId) . '</code><br>';
            echo 'Check your website to see the changes. If you don\'t like them, go to the History tab to undo.</div>';
            
            echo '<div class="ai-pr-card">';
            echo '<a href="#" class="ai-pr-diff-toggle">👁️ Show what was changed</a>';
            echo '<div class="ai-pr-diff-content">' . $diff_html . '</div>';
            echo '</div>';
            
            echo '<p><a href="' . esc_url(admin_url('tools.php?page=' . AI_PR_PLUGIN_SLUG . '&tab=quick')) . '" class="button">← Make Another Change</a> ';
            echo '<a href="' . esc_url(admin_url('tools.php?page=' . AI_PR_PLUGIN_SLUG . '&tab=history')) . '" class="button">View History</a></p>';
            
            ai_pr_log('quick_task_applied', ['template' => $template, 'snapshot' => $snapId]);
        }
        return;
    }
    
    echo '<div class="ai-pr-card">';
    echo '<h3>⚡ Quick Styling Tasks</h3>';
    echo '<p>Choose a common styling task below. The AI will make the changes for you and show a preview before applying.</p>';
    
    echo '<div class="ai-pr-help">💡 <strong>Tip:</strong> All changes create an automatic backup. You can undo any change from the History tab.</div>';
    
    echo '<form method="post">';
    wp_nonce_field($nonce_action);
    
    echo '<div class="ai-pr-form-group">';
    echo '<label for="ai_pr_task_template">What would you like to change?</label>';
    echo '<select name="template" id="ai_pr_task_template" required>';
    echo '<option value="">-- Choose a task --</option>';
    
    $templates = ai_pr_get_quick_task_templates();
    foreach ($templates as $key => $tmpl) {
        echo '<option value="' . esc_attr($key) . '">' . esc_html($tmpl['label']) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    
    foreach ($templates as $key => $tmpl) {
        echo '<div id="ai-pr-fields-' . esc_attr($key) . '" class="ai-pr-template-fields" style="display:none;">';
        foreach ($tmpl['fields'] as $field) {
            echo '<div class="ai-pr-form-group">';
            echo '<label>' . esc_html($field['label']) . '</label>';
            
            if ($field['type'] === 'select') {
                echo '<select name="params[' . esc_attr($field['name']) . ']">';
                foreach ($field['options'] as $val => $label) {
                    echo '<option value="' . esc_attr($val) . '">' . esc_html($label) . '</option>';
                }
                echo '</select>';
            } elseif ($field['type'] === 'color') {
                $default = $field['default'] ?? '#000000';
                echo '<input type="color" name="params[' . esc_attr($field['name']) . ']" value="' . esc_attr($default) . '">';
            } else {
                $default = $field['default'] ?? '';
                $placeholder = $field['placeholder'] ?? '';
                echo '<input type="text" name="params[' . esc_attr($field['name']) . ']" value="' . esc_attr($default) . '" placeholder="' . esc_attr($placeholder) . '">';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    
    echo '<div class="ai-pr-buttons">';
    echo '<button type="submit" name="do_preview" value="1" class="button button-primary button-large">👁️ Preview Changes</button>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
}

/* =================
 * UI: Page Styler
 * ================= */

function ai_pr_ui_page_styler($nonce_action) {
    $do_preview = !empty($_POST['do_preview']);
    $do_apply = !empty($_POST['do_apply']);
    
    if ($do_preview && check_admin_referer($nonce_action)) {
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        $style_request = isset($_POST['style_request']) ? sanitize_textarea_field($_POST['style_request']) : '';
        
        if (!$page_id || !$style_request) {
            echo '<div class="ai-pr-error">Please select a page and describe what you want to change.</div>';
            return;
        }
        
        $page = get_post($page_id);
        if (!$page) {
            echo '<div class="ai-pr-error">Page not found.</div>';
            return;
        }
        
        $page_info = [
            'id' => $page->ID,
            'title' => $page->post_title,
            'type' => $page->post_type,
            'url' => get_permalink($page->ID),
        ];
        
        $elementor = ai_pr_detect_elementor($page_id);
        $theme_files = ai_pr_get_theme_files();
        
        $custom_prompt = "For the page '{$page_info['title']}': {$style_request}";
        
        $prompts = ai_pr_build_ai_prompt('custom', ['request' => $custom_prompt], [
            'page_info' => $page_info,
            'elementor' => $elementor,
            'theme_files' => $theme_files,
        ]);
        
        $ai_result = ai_pr_call_ai($prompts['system'], $prompts['user']);
        
        if (is_wp_error($ai_result)) {
            echo '<div class="ai-pr-error"><strong>Error:</strong> ' . esc_html($ai_result->get_error_message()) . '</div>';
        } else {
            $preview_key = ai_pr_generate_preview_key();
            ai_pr_store_preview($preview_key, $ai_result, [
                'page_id' => $page_id,
                'style_request' => $style_request,
                'page_info' => $page_info,
            ]);
            
            $summary = $ai_result['summary'];
            $operations = $ai_result['operations'];
            
            $preview_time = date('g:i a');
            echo '<div class="ai-pr-preview">';
            echo '<h4>📋 Preview Generated for "' . esc_html($page_info['title']) . '" (' . esc_html($preview_time) . ')</h4>';
            echo '<div class="ai-pr-summary">' . nl2br(esc_html($summary)) . '</div>';
            if ($elementor['post_uses_elementor']) {
                echo '<p style="color:#666;font-size:12px;margin-top:10px;">ℹ️ This page uses Elementor. Changes will be made to the theme or custom CSS files.</p>';
            }
            echo '<p style="font-size: 12px; color: #666; margin-top: 10px;">⏱ This preview will expire in 1 hour. Apply the changes before then.</p>';
            echo '</div>';
            
            $blocks = ai_pr_parse_blocks($operations);
            if (!$blocks) {
                echo '<div class="ai-pr-error">AI did not generate valid file operations.</div>';
            } else {
                $diff_html = '';
                foreach ($blocks as $b) {
                    $abs = ai_pr_resolve_target($b['path'], false);
                    if (!$abs) continue;
                    
                    $before = file_exists($abs) ? file_get_contents($abs) : '';
                    
                    if ($b['type'] === 'FILE') {
                        $after = $b['content'];
                        $diff_html .= ai_pr_text_diff($before, $after, $b['path']);
                    } elseif ($b['type'] === 'APPEND') {
                        $after = rtrim($before) . "\n\n" . rtrim($b['content']) . "\n";
                        $diff_html .= ai_pr_text_diff($before, $after, $b['path']);
                    }
                }
                
                echo '<div class="ai-pr-card">';
                echo '<a href="#" class="ai-pr-diff-toggle">👁️ Show code changes</a>';
                echo '<div class="ai-pr-diff-content">' . $diff_html . '</div>';
                echo '</div>';
                
                echo '<form method="post" style="margin-top: 20px;">';
                wp_nonce_field($nonce_action);
                echo '<input type="hidden" name="preview_key" value="' . esc_attr($preview_key) . '">';
                echo '<p><strong>Ready to apply?</strong></p>';
                echo '<button type="submit" name="do_apply" value="1" class="button button-primary button-large">✅ Apply Changes</button> ';
                echo '<a href="' . esc_url(admin_url('tools.php?page=' . AI_PR_PLUGIN_SLUG . '&tab=page-styler')) . '" class="button">Cancel</a>';
                echo '</form>';
            }
        }
        return;
    }
    
    if ($do_apply && check_admin_referer($nonce_action)) {
        $preview_key = isset($_POST['preview_key']) ? sanitize_text_field($_POST['preview_key']) : '';
        
        if (!$preview_key) {
            echo '<div class="ai-pr-error"><strong>Error:</strong> No preview found. Please generate a preview first.</div>';
            return;
        }
        
        $preview_data = ai_pr_get_preview($preview_key);
        
        if (!$preview_data) {
            echo '<div class="ai-pr-error"><strong>Preview Expired:</strong> Your preview has expired or is no longer valid. Please generate a new preview before applying changes.</div>';
            echo '<p><a href="' . esc_url(admin_url('tools.php?page=' . AI_PR_PLUGIN_SLUG . '&tab=page-styler')) . '" class="button">← Go Back</a></p>';
            return;
        }
        
        $ai_result = $preview_data['ai_response'];
        $page_id = $preview_data['context']['page_id'] ?? 0;
        $page_info = $preview_data['context']['page_info'] ?? [];
        
        $page = get_post($page_id);
        if (!$page) {
            echo '<div class="ai-pr-error">Page not found.</div>';
            return;
        }
        
        $summary = $ai_result['summary'];
        $operations = $ai_result['operations'];
        
        $blocks = ai_pr_parse_blocks($operations);
        if (!$blocks) {
            echo '<div class="ai-pr-error">AI did not generate valid file operations.</div>';
        } else {
            list($snapId, $snapDir) = ai_pr_start_snapshot('page-styler-' . $page->post_name);
            
            $diff_html = '';
            foreach ($blocks as $b) {
                $abs = ai_pr_resolve_target($b['path'], false);
                if (!$abs) continue;
                
                $before = file_exists($abs) ? file_get_contents($abs) : '';
                ai_pr_snapshot_add_file($snapDir, $abs, $before);
                
                if ($b['type'] === 'FILE') {
                    $after = $b['content'];
                    $diff_html .= ai_pr_text_diff($before, $after, $b['path']);
                    ai_pr_write_file($abs, $after);
                } elseif ($b['type'] === 'APPEND') {
                    $after = rtrim($before) . "\n\n" . rtrim($b['content']) . "\n";
                    $diff_html .= ai_pr_text_diff($before, $after, $b['path']);
                    ai_pr_write_file($abs, $after);
                }
            }
            
            ai_pr_finalize_snapshot($snapDir);
            ai_pr_delete_preview($preview_key);
            
            echo '<div class="ai-pr-success"><strong>✅ Changes Applied!</strong> Snapshot: <code>' . esc_html($snapId) . '</code></div>';
            echo '<p><a href="' . esc_url($page_info['url']) . '" target="_blank" class="button">View Page</a> ';
            echo '<a href="' . esc_url(admin_url('tools.php?page=' . AI_PR_PLUGIN_SLUG . '&tab=history')) . '" class="button">History</a></p>';
            
            ai_pr_log('page_styler_applied', ['page_id' => $page_id, 'snapshot' => $snapId]);
        }
        return;
    }
    
    echo '<div class="ai-pr-card">';
    echo '<h3>📄 Style a Specific Page or Post</h3>';
    echo '<p>Select a page or post, then describe the styling changes you want to make. The AI will focus changes on just that page.</p>';
    
    echo '<form method="post">';
    wp_nonce_field($nonce_action);
    
    $pages = ai_pr_get_pages_and_posts();
    
    echo '<div class="ai-pr-form-group">';
    echo '<label for="ai_pr_page_select">Select Page or Post</label>';
    echo '<select name="page_id" id="ai_pr_page_select" required>';
    echo '<option value="">-- Choose a page or post --</option>';
    
    $current_type = '';
    foreach ($pages as $item) {
        if ($current_type !== $item['type']) {
            if ($current_type) echo '</optgroup>';
            echo '<optgroup label="' . esc_attr(ucfirst($item['type']) . 's') . '">';
            $current_type = $item['type'];
        }
        echo '<option value="' . esc_attr($item['id']) . '">' . esc_html($item['title']) . '</option>';
    }
    if ($current_type) echo '</optgroup>';
    
    echo '</select>';
    echo '</div>';
    
    echo '<div class="ai-pr-form-group">';
    echo '<label for="ai_pr_style_request">What would you like to change?</label>';
    echo '<textarea name="style_request" id="ai_pr_style_request" rows="4" placeholder="Example: Make the header background blue and increase the font size of the title" required></textarea>';
    echo '<p class="description">Describe the styling changes in plain English. Be as specific as you can.</p>';
    echo '</div>';
    
    $elementor_info = ai_pr_detect_elementor();
    if ($elementor_info['active']) {
        echo '<div class="ai-pr-help">ℹ️ <strong>Elementor Detected:</strong> Styling changes will be added to your child theme\'s CSS file (triuu/style.css) to ensure they persist and override Elementor\'s auto-generated styles.</div>';
    }
    
    echo '<div class="ai-pr-buttons">';
    echo '<button type="submit" name="do_preview" value="1" class="button button-primary button-large">👁️ Preview Changes</button>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
}

/* ====================
 * UI: Custom Request
 * ==================== */

function ai_pr_ui_custom_request($nonce_action) {
    $do_preview = !empty($_POST['do_preview']);
    $do_apply = !empty($_POST['do_apply']);
    
    if ($do_preview && check_admin_referer($nonce_action)) {
        $request = isset($_POST['custom_request']) ? sanitize_textarea_field($_POST['custom_request']) : '';
        
        if (!$request) {
            echo '<div class="ai-pr-error">Please describe what you want to change.</div>';
            return;
        }
        
        $theme_files = ai_pr_get_theme_files();
        $elementor = ai_pr_detect_elementor();
        
        $prompts = ai_pr_build_ai_prompt('custom', ['request' => $request], [
            'theme_files' => $theme_files,
            'elementor' => $elementor,
        ]);
        
        $ai_result = ai_pr_call_ai($prompts['system'], $prompts['user']);
        
        if (is_wp_error($ai_result)) {
            echo '<div class="ai-pr-error"><strong>Error:</strong> ' . esc_html($ai_result->get_error_message()) . '</div>';
        } else {
            $preview_key = ai_pr_generate_preview_key();
            ai_pr_store_preview($preview_key, $ai_result, [
                'custom_request' => $request,
            ]);
            
            $summary = $ai_result['summary'];
            $operations = $ai_result['operations'];
            
            $preview_time = date('g:i a');
            echo '<div class="ai-pr-preview">';
            echo '<h4>📋 Preview Generated (' . esc_html($preview_time) . ')</h4>';
            echo '<div class="ai-pr-summary">' . nl2br(esc_html($summary)) . '</div>';
            echo '<p style="font-size: 12px; color: #666; margin-top: 10px;">⏱ This preview will expire in 1 hour. Apply the changes before then.</p>';
            echo '</div>';
            
            $blocks = ai_pr_parse_blocks($operations);
            if (!$blocks) {
                echo '<div class="ai-pr-error">AI did not generate valid file operations.</div>';
            } else {
                $diff_html = '';
                foreach ($blocks as $b) {
                    $abs = ai_pr_resolve_target($b['path'], false);
                    if (!$abs) continue;
                    
                    $before = file_exists($abs) ? file_get_contents($abs) : '';
                    
                    if ($b['type'] === 'FILE') {
                        $after = $b['content'];
                        $diff_html .= ai_pr_text_diff($before, $after, $b['path']);
                    } elseif ($b['type'] === 'APPEND') {
                        $after = rtrim($before) . "\n\n" . rtrim($b['content']) . "\n";
                        $diff_html .= ai_pr_text_diff($before, $after, $b['path']);
                    }
                }
                
                echo '<div class="ai-pr-card">';
                echo '<a href="#" class="ai-pr-diff-toggle">👁️ Show code changes</a>';
                echo '<div class="ai-pr-diff-content">' . $diff_html . '</div>';
                echo '</div>';
                
                echo '<form method="post" style="margin-top: 20px;">';
                wp_nonce_field($nonce_action);
                echo '<input type="hidden" name="preview_key" value="' . esc_attr($preview_key) . '">';
                echo '<p><strong>Ready to apply?</strong></p>';
                echo '<button type="submit" name="do_apply" value="1" class="button button-primary button-large">✅ Apply Changes</button> ';
                echo '<a href="' . esc_url(admin_url('tools.php?page=' . AI_PR_PLUGIN_SLUG . '&tab=custom')) . '" class="button">Cancel</a>';
                echo '</form>';
            }
        }
        return;
    }
    
    if ($do_apply && check_admin_referer($nonce_action)) {
        $preview_key = isset($_POST['preview_key']) ? sanitize_text_field($_POST['preview_key']) : '';
        
        if (!$preview_key) {
            echo '<div class="ai-pr-error"><strong>Error:</strong> No preview found. Please generate a preview first.</div>';
            return;
        }
        
        $preview_data = ai_pr_get_preview($preview_key);
        
        if (!$preview_data) {
            echo '<div class="ai-pr-error"><strong>Preview Expired:</strong> Your preview has expired or is no longer valid. Please generate a new preview before applying changes.</div>';
            echo '<p><a href="' . esc_url(admin_url('tools.php?page=' . AI_PR_PLUGIN_SLUG . '&tab=custom')) . '" class="button">← Go Back</a></p>';
            return;
        }
        
        $ai_result = $preview_data['ai_response'];
        
        $summary = $ai_result['summary'];
        $operations = $ai_result['operations'];
        
        $blocks = ai_pr_parse_blocks($operations);
        if (!$blocks) {
            echo '<div class="ai-pr-error">AI did not generate valid file operations.</div>';
        } else {
            list($snapId, $snapDir) = ai_pr_start_snapshot('custom-request');
            
            $diff_html = '';
            foreach ($blocks as $b) {
                $abs = ai_pr_resolve_target($b['path'], false);
                if (!$abs) continue;
                
                $before = file_exists($abs) ? file_get_contents($abs) : '';
                ai_pr_snapshot_add_file($snapDir, $abs, $before);
                
                if ($b['type'] === 'FILE') {
                    $after = $b['content'];
                    $diff_html .= ai_pr_text_diff($before, $after, $b['path']);
                    ai_pr_write_file($abs, $after);
                } elseif ($b['type'] === 'APPEND') {
                    $after = rtrim($before) . "\n\n" . rtrim($b['content']) . "\n";
                    $diff_html .= ai_pr_text_diff($before, $after, $b['path']);
                    ai_pr_write_file($abs, $after);
                }
            }
            
            ai_pr_finalize_snapshot($snapDir);
            ai_pr_delete_preview($preview_key);
            
            echo '<div class="ai-pr-success"><strong>✅ Changes Applied!</strong> Snapshot: <code>' . esc_html($snapId) . '</code></div>';
            echo '<p><a href="' . esc_url(home_url()) . '" target="_blank" class="button">View Website</a> ';
            echo '<a href="' . esc_url(admin_url('tools.php?page=' . AI_PR_PLUGIN_SLUG . '&tab=history')) . '" class="button">History</a></p>';
            
            ai_pr_log('custom_request_applied', ['snapshot' => $snapId]);
        }
        return;
    }
    
    echo '<div class="ai-pr-card">';
    echo '<h3>✍️ Custom Styling Request</h3>';
    echo '<p>Describe any styling change you want in plain English. The AI will figure out what files need to be modified.</p>';
    
    echo '<form method="post">';
    wp_nonce_field($nonce_action);
    
    echo '<div class="ai-pr-form-group">';
    echo '<label for="ai_pr_custom_request">What would you like to change?</label>';
    echo '<textarea name="custom_request" id="ai_pr_custom_request" rows="6" placeholder="Examples:
- Make the homepage header purple with white text
- Add more spacing between sections on all pages  
- Change all buttons to have rounded corners
- Make the footer background darker
- Increase the font size of all headings" required></textarea>';
    echo '<p class="description">Be as specific as possible. Mention colors, sizes, or specific elements you want to change.</p>';
    echo '</div>';
    
    echo '<div class="ai-pr-help">💡 <strong>Tips:</strong> The more specific you are, the better the results. You can mention specific pages, colors (like "navy blue" or "#0066cc"), sizes (like "larger" or "20px"), and elements (like "header", "footer", "buttons").</div>';
    
    $elementor_info = ai_pr_detect_elementor();
    if ($elementor_info['active']) {
        echo '<div class="ai-pr-help">ℹ️ <strong>Elementor Detected:</strong> All styling changes will be added to your child theme\'s CSS file (triuu/style.css) to ensure they persist and properly override Elementor\'s styles.</div>';
    }
    
    echo '<div class="ai-pr-buttons">';
    echo '<button type="submit" name="do_preview" value="1" class="button button-primary button-large">👁️ Preview Changes</button>';
    echo '</div>';
    
    echo '</form>';
    echo '</div>';
}

/* =============
 * UI: History
 * ============= */

function ai_pr_ui_history($nonce_action) {
    if (!empty($_POST['do_restore']) && check_admin_referer($nonce_action)) {
        $snapshot_id = isset($_POST['snapshot_id']) ? sanitize_text_field($_POST['snapshot_id']) : '';
        
        $snap = ai_pr_load_snapshot($snapshot_id);
        if (!$snap) {
            echo '<div class="ai-pr-error">Snapshot not found.</div>';
        } else {
            list($newId, $newDir) = ai_pr_start_snapshot('restore-of-' . $snapshot_id);
            
            $restored_count = 0;
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
                $restored_count++;
            }
            
            ai_pr_finalize_snapshot($newDir);
            
            echo '<div class="ai-pr-success"><strong>✅ Restored Successfully!</strong><br>';
            echo 'Restored ' . $restored_count . ' file(s) from snapshot <code>' . esc_html($snapshot_id) . '</code>.<br>';
            echo 'A new snapshot was created in case you want to undo this restore: <code>' . esc_html($newId) . '</code></div>';
            echo '<p><a href="' . esc_url(home_url()) . '" target="_blank" class="button">View Website</a></p>';
            
            ai_pr_log('snapshot_restored', ['restored' => $snapshot_id, 'new_snapshot' => $newId]);
        }
    }
    
    echo '<div class="ai-pr-card">';
    echo '<h3>🕐 Change History</h3>';
    echo '<p>Every change you make is automatically saved. Click "Restore" to undo any change.</p>';
    
    $snapshots = ai_pr_list_snapshots(50);
    
    if (!$snapshots) {
        echo '<div class="ai-pr-help">No changes have been made yet. Make your first styling change from one of the other tabs!</div>';
    } else {
        echo '<ul class="ai-pr-snapshot-list">';
        foreach ($snapshots as $snap) {
            $label = !empty($snap['label']) ? $snap['label'] : 'Untitled Change';
            $file_count = count($snap['files']);
            $created = date('F j, Y \a\t g:i a', strtotime($snap['created']));
            
            echo '<li class="ai-pr-snapshot-item">';
            echo '<div class="ai-pr-snapshot-header">';
            echo '<div>';
            echo '<div class="ai-pr-snapshot-title">' . esc_html($label) . '</div>';
            echo '<div class="ai-pr-snapshot-date">' . esc_html($created) . '</div>';
            echo '</div>';
            echo '<div>';
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field($nonce_action);
            echo '<input type="hidden" name="snapshot_id" value="' . esc_attr($snap['id']) . '">';
            echo '<button type="submit" name="do_restore" value="1" class="button" onclick="return confirm(\'Are you sure you want to restore this snapshot? This will undo your current changes.\')">🔄 Restore</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
            echo '<div class="ai-pr-snapshot-meta">';
            echo $file_count . ' file(s) changed • ID: ' . esc_html($snap['id']);
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    echo '</div>';
}

/* ===============
 * Helper: glob fs
 * =============== */

function ai_pr_glob_recursive($baseDir, array $patterns, $allow_core) {
    $out = [];
    $flags = FilesystemIterator::SKIP_DOTS;
    try {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir, $flags));
    } catch (Exception $e) {
        return $out;
    }
    foreach ($rii as $file) {
        if (!$file->isFile()) continue;
        $abs = ai_pr_normalize_path($file->getPathname());
        if (!$allow_core && !ai_pr_path_is_inside($abs, WP_CONTENT_DIR)) continue;
        $ok = false;
        foreach ($patterns as $pat) {
            $pat = trim($pat);
            if ($pat === '') continue;
            if (fnmatch($pat, basename($abs))) { $ok = true; break; }
        }
        if ($ok) $out[] = $abs;
    }
    return $out;
}

/* =========
 * WP-CLI
 * ========= */

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('ai-pr apply', function($args, $assoc){
        $text   = isset($assoc['text']) ? $assoc['text'] : '';
        $file   = isset($assoc['file']) ? $assoc['file'] : '';
        $token  = isset($assoc['token']) ? $assoc['token'] : '';
        $dry    = isset($assoc['dry']) ? (bool)$assoc['dry'] : false;
        $label  = isset($assoc['label']) ? $assoc['label'] : 'apply-patch';
        $allow_core = !empty($assoc['allow-core']);

        if (!$text && $file) $text = @file_get_contents($file);
        if (!$text) WP_CLI::error('Provide --text or --file to supply blocks.');

        $blocks = ai_pr_parse_blocks($text);
        if (!$blocks) WP_CLI::error('No blocks recognized.');

        list($snapId, $snapDir) = ai_pr_start_snapshot($label);
        foreach ($blocks as $b) {
            $type = $b['type']; $path = $b['path']; $pathTo = $b['path_to']; $content = $b['content'];
            $abs = ai_pr_resolve_target($path, $allow_core);
            if (!$abs) { WP_CLI::warning("Skip (outside allowed): $path"); continue; }
            if ($type !== 'DELETE' && $type !== 'RENAME' && !ai_pr_is_ext_ok($abs)) { WP_CLI::warning("Skip (extension policy): $path"); continue; }

            $before = file_exists($abs) ? file_get_contents($abs) : '';
            ai_pr_snapshot_add_file($snapDir, $abs, $before);

            if ($type === 'FILE') {
                $after = $content;
                $dry ? WP_CLI::line("FILE → $path") : ai_pr_write_file($abs, $after);

            } elseif ($type === 'APPEND') {
                $after = $token !== '' ? ai_pr_apply_token_region($before, $token, $content) : (rtrim($before) . "\n\n" . rtrim($content) . "\n");
                $dry ? WP_CLI::line("APPEND → $path") : ai_pr_write_file($abs, $after);

            } elseif ($type === 'DELETE') {
                $dry ? WP_CLI::line("DELETE → $path") : ai_pr_delete_file($abs);

            } elseif ($type === 'RENAME') {
                $absTo = ai_pr_resolve_target($pathTo, $allow_core);
                if (!$absTo) { WP_CLI::warning("Skip RENAME (target outside allowed): {$b['path_to']}"); continue; }
                if ($dry) {
                    WP_CLI::line("RENAME: $path → {$b['path_to']}");
                } else {
                    ai_pr_rename_file($abs, $absTo);
                    $after = file_exists($absTo) ? file_get_contents($absTo) : '';
                    ai_pr_snapshot_add_file($snapDir, $absTo, $after);
                }
            }
        }
        ai_pr_finalize_snapshot($snapDir);
        WP_CLI::success("Snapshot: $snapId " . ($dry ? '(dry-run)' : '(applied)'));
    });

    WP_CLI::add_command('ai-pr revert', function($args, $assoc){
        $id = $args[0] ?? '';
        if (!$id) WP_CLI::error('Usage: wp ai-pr revert <snapshot-id> [--dry]');
        $dry = !empty($assoc['dry']);
        $snap = ai_pr_load_snapshot($id);
        if (!$snap) WP_CLI::error('Snapshot not found.');
        list($newId, $newDir) = ai_pr_start_snapshot('revert-of-'.$id);

        foreach (array_keys($snap['files']) as $abs) {
            $absAllowed = ai_pr_resolve_target($abs, false);
            if (!$absAllowed) { WP_CLI::warning("Skip (outside allowed): $abs"); continue; }
            $before = file_exists($abs) ? file_get_contents($abs) : '';
            ai_pr_snapshot_add_file($newDir, $abs, $before);

            $meta = $snap['files'][$abs];
            $blob = $snap['_dir'] . '/files/' . md5($abs) . '.before';
            $after = $meta['exists_before'] ? (file_exists($blob) ? file_get_contents($blob) : '') : '';

            if ($dry) {
                WP_CLI::line("REVERT → $abs");
            } else {
                if ($meta['exists_before']) {
                    ai_pr_write_file($abs, $after);
                } else {
                    ai_pr_delete_file($abs);
                }
            }
        }
        ai_pr_finalize_snapshot($newDir);
        WP_CLI::success("Snapshot: $newId " . ($dry ? '(dry-run)' : '(applied)'));
    });
}

/* ==================
 * Admin Notice: AI
 * ================== */

add_action('admin_notices', function(){
    if (!current_user_can('manage_options')) return;
    $screen = get_current_screen();
    if ($screen && $screen->id === 'tools_page_' . AI_PR_PLUGIN_SLUG) {
        if (!function_exists('openai_wp_chat')) {
            echo '<div class="notice notice-warning"><p><strong>AI Website Styler:</strong> AI features require the OpenAI integration. <a href="' . admin_url('admin.php?page=integrations') . '">Install OpenAI integration</a> to use AI-powered styling.</p></div>';
        }
    }
});

/* END v3.0.0 */
